/* global L */
(function(){
  const EP = window.APP_BOOTSTRAP?.endpoints || {};
  const state = {
    cfg: null,
    items: [],                 // {id,label,address,city,note,lat,lon,round,weight,volume,_pendingRound}
    roundMeta: {},             // { [roundId]: {planned_date:string} }
    markersById: new Map(),
    rowsById: new Map(),
    ORIGIN: {lat:47.4500, lon:19.3500}, // Maglód tartalék
    filterText: ''
  };

  const history = [];
  const undoBtn = document.getElementById('undoBtn');

  const groupsEl = document.getElementById('groups');
  const pinCountEl = document.getElementById('pinCount');
  const themeToggle = document.getElementById('themeToggle');
  const panelTopEl = document.getElementById('panelTop');
  let quickSearchClearBtn = null;

  const flashTimers = new WeakMap();

  const collapsePrefs = new Map();
  const COLLAPSE_STORAGE_KEY = 'app_panel_collapsed_rows_v1';
  const DEFAULT_COLLAPSED = true;
  let collapsePrefsStorageError = false;

  function loadCollapsePrefs(){
    collapsePrefs.clear();
    try {
      const storage = window.localStorage;
      if (!storage) return;
      const raw = storage.getItem(COLLAPSE_STORAGE_KEY);
      if (!raw) return;
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') return;
      Object.entries(parsed).forEach(([key, val])=>{
        collapsePrefs.set(String(key), !!val);
      });
    } catch (err) {
      if (!collapsePrefsStorageError) {
        console.warn('Failed to load collapse preferences', err);
        collapsePrefsStorageError = true;
      }
      collapsePrefs.clear();
    }
  }

  function persistCollapsePrefs(){
    try {
      const storage = window.localStorage;
      if (!storage) return;
      const obj = {};
      collapsePrefs.forEach((val, key)=>{ obj[key] = !!val; });
      storage.setItem(COLLAPSE_STORAGE_KEY, JSON.stringify(obj));
    } catch (err) {
      if (!collapsePrefsStorageError) {
        console.warn('Failed to persist collapse preferences', err);
        collapsePrefsStorageError = true;
      }
    }
  }

  function setCollapsePref(id, collapsed){
    if (id == null) return;
    const key = String(id);
    collapsePrefs.set(key, !!collapsed);
    persistCollapsePrefs();
  }

  function getCollapsePref(id){
    if (id == null) return null;
    const key = String(id);
    return collapsePrefs.has(key) ? !!collapsePrefs.get(key) : null;
  }

  function clearCollapsePref(id){
    if (id == null) return;
    const key = String(id);
    const deleted = collapsePrefs.delete(key);
    if (deleted) persistCollapsePrefs();
  }

  function pruneCollapsePrefs(validIds){
    if (!Array.isArray(validIds)) return;
    const valid = new Set(validIds.map(id => String(id)));
    let changed = false;
    collapsePrefs.forEach((_, key)=>{
      if (!valid.has(key)){
        collapsePrefs.delete(key);
        changed = true;
      }
    });
    if (changed) persistCollapsePrefs();
  }

  const roundMetaKey = (rid)=> {
    const num = Number(rid);
    return Number.isFinite(num) ? String(num) : String(rid);
  };

  function getPlannedDateForRound(rid){
    const meta = state.roundMeta || {};
    const entry = meta[roundMetaKey(rid)];
    if (entry && typeof entry === 'object' && typeof entry.planned_date === 'string') {
      return entry.planned_date;
    }
    return '';
  }

  function setPlannedDateForRound(rid, value){
    const key = roundMetaKey(rid);
    if (!state.roundMeta || typeof state.roundMeta !== 'object') {
      state.roundMeta = {};
    }
    const val = (value || '').trim();
    const limited = val.length > 120 ? val.slice(0, 120) : val;
    if (limited) {
      state.roundMeta[key] = {...(state.roundMeta[key] || {}), planned_date: limited};
    } else if (state.roundMeta[key]) {
      delete state.roundMeta[key].planned_date;
      if (Object.keys(state.roundMeta[key]).length === 0) {
        delete state.roundMeta[key];
      }
    }
  }

  function clearRoundMeta(rid){
    const key = roundMetaKey(rid);
    if (state.roundMeta && Object.prototype.hasOwnProperty.call(state.roundMeta, key)) {
      delete state.roundMeta[key];
    }
  }

  function normalizedRoundMeta(){
    const meta = state.roundMeta;
    const out = {};
    if (!meta || typeof meta !== 'object') return out;
    for (const [rid, entry] of Object.entries(meta)){
      if (!entry || typeof entry !== 'object') continue;
      const date = typeof entry.planned_date === 'string' ? entry.planned_date.trim() : '';
      if (date) out[rid] = {planned_date: date.slice(0, 120)};
    }
    return out;
  }

  function cfg(path, fallback){
    if (!state.cfg) return fallback;
    const parts = path.split('.');
    let node = state.cfg;
    for (const p of parts){
      if (node && typeof node === 'object' && p in node){
        node = node[p];
      } else {
        return fallback;
      }
    }
    return node;
  }

  function feature(path, fallback=true){
    const val = cfg(`features.${path}`, undefined);
    return typeof val === 'boolean' ? val : fallback;
  }

  function text(path, fallback=''){
    const val = cfg(`text.${path}`, undefined);
    if (typeof val === 'string') return val;
    if (val && typeof val === 'object' && 'label' in val && typeof val.label === 'string') return val.label;
    return fallback;
  }

  function format(template, vars={}){
    return (template || '').replace(/\{(\w+)\}/g, (_, k)=> (k in vars ? String(vars[k]) : ''));
  }

  const getFieldDefs = ()=> (cfg('items.fields', []) || []).filter(f => f && f.enabled !== false);
  const getMetricDefs = ()=> (cfg('items.metrics', []) || []).filter(f => f && f.enabled !== false);
  const getAddressFieldId = ()=> cfg('items.address_field_id', 'address');
  const getLabelFieldId = ()=> cfg('items.label_field_id', 'label');
  const getNoteFieldId = ()=> cfg('items.note_field_id', 'note');
  const getFieldDefById = (fid)=> getFieldDefs().find(field => field && field.id === fid);

  const DEFAULT_DEADLINE_STEPS = [
    {minDays: 7, color: '#16a34a'},
    {minDays: 3, color: '#f97316'},
    {minDays: null, color: '#dc2626'}
  ];

  function deadlineIndicatorCfg(){
    const raw = cfg('items.deadline_indicator', null);
    return (raw && typeof raw === 'object') ? raw : {};
  }

  function getDeadlineFieldId(){
    const node = deadlineIndicatorCfg();
    const candidate = node.field_id ?? cfg('items.deadline_field_id', null);
    if (typeof candidate === 'string' && candidate.trim()) return candidate.trim();
    return 'deadline';
  }

  function isDeadlineFeatureEnabled(){
    const node = deadlineIndicatorCfg();
    if (node.enabled === false) return false;
    const fid = getDeadlineFieldId();
    if (!fid) return false;
    const field = getFieldDefById(fid);
    if (!field || field.enabled === false) return false;
    return true;
  }

  function getDeadlineIconSize(){
    const node = deadlineIndicatorCfg();
    let size = node.icon_size;
    if ((size == null) && node.icon && typeof node.icon === 'object') {
      size = node.icon.size;
    }
    if (typeof size === 'string' && size.trim() !== '') {
      const parsed = Number(size);
      if (Number.isFinite(parsed)) size = parsed;
    }
    size = Number(size);
    if (!Number.isFinite(size) || size <= 0) size = 16;
    return size;
  }

  function normalizeNumber(val){
    if (val == null || val === '') return null;
    if (typeof val === 'number') return Number.isFinite(val) ? val : null;
    if (typeof val === 'string') {
      const trimmed = val.trim().toLowerCase();
      if (!trimmed) return null;
      if (['inf','infinity','+inf','+infinity'].includes(trimmed)) return Infinity;
      if (['-inf','-infinity'].includes(trimmed)) return -Infinity;
      const num = Number(trimmed);
      return Number.isFinite(num) ? num : null;
    }
    return null;
  }

  function deadlineSteps(){
    const node = deadlineIndicatorCfg();
    const raw = Array.isArray(node.steps) ? node.steps : null;
    if (!raw || !raw.length) return DEFAULT_DEADLINE_STEPS;
    const normalized = [];
    raw.forEach(step => {
      if (!step || typeof step !== 'object') return;
      const colorRaw = step.color;
      if (typeof colorRaw !== 'string' || !colorRaw.trim()) return;
      const min = normalizeNumber(step.min_days ?? step.min ?? step.days ?? step.from ?? step.start);
      const max = normalizeNumber(step.max_days ?? step.max ?? step.to ?? step.end);
      const label = typeof step.label === 'string' ? step.label : null;
      normalized.push({
        minDays: min,
        maxDays: max,
        color: colorRaw.trim(),
        label,
        isDefault: step.default === true
      });
    });
    return normalized.length ? normalized : DEFAULT_DEADLINE_STEPS;
  }

  function chooseDeadlineStep(diffDays){
    const steps = deadlineSteps();
    let fallback = steps[steps.length - 1] || null;
    for (const step of steps){
      const min = step.minDays != null ? step.minDays : -Infinity;
      const max = step.maxDays != null ? step.maxDays : Infinity;
      if (diffDays >= min && diffDays <= max) return step;
      if (step.isDefault) fallback = step;
    }
    return fallback;
  }

  function parseDeadlineValue(raw){
    if (raw == null) return null;
    const str = String(raw).trim();
    if (!str) return null;
    const m = str.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!m) return null;
    const year = Number(m[1]);
    const month = Number(m[2]);
    const day = Number(m[3]);
    if (!Number.isFinite(year) || !Number.isFinite(month) || !Number.isFinite(day)) return null;
    const dateUtc = Date.UTC(year, month - 1, day);
    if (!Number.isFinite(dateUtc)) return null;
    const now = new Date();
    const todayUtc = Date.UTC(now.getFullYear(), now.getMonth(), now.getDate());
    const diffDays = Math.round((dateUtc - todayUtc) / 86400000);
    const step = chooseDeadlineStep(diffDays) || {};
    return {
      value: str,
      year,
      month,
      day,
      diffDays,
      color: step.color || null,
      stepLabel: step.label || null
    };
  }

  function deadlineLabelText(){
    const fid = getDeadlineFieldId();
    const field = getFieldDefById(fid);
    const fallback = field && typeof field.label === 'string' ? field.label : 'Határidő';
    return text('items.deadline_label', fallback || 'Határidő');
  }

  function deadlineRelativeText(info){
    if (!info) return '';
    const days = info.diffDays;
    if (days > 0) return format(text('items.deadline_relative_future', 'hátra: {days} nap'), {days});
    if (days === 0) return text('items.deadline_relative_today', 'ma esedékes');
    return format(text('items.deadline_relative_past', 'lejárt: {days} napja'), {days: Math.abs(days)});
  }

  function deadlineTooltip(info){
    if (!info) return '';
    const label = deadlineLabelText();
    const rel = deadlineRelativeText(info);
    if (rel) return `${label}: ${info.value} · ${rel}`;
    return `${label}: ${info.value}`;
  }

  function deadlineDisplay(info){
    if (!info) return '';
    const label = deadlineLabelText();
    const rel = deadlineRelativeText(info);
    if (rel) return `${label}: ${info.value} (${rel})`;
    return `${label}: ${info.value}`;
  }

  function defaultValueForField(def){
    if (!def) return '';
    if (def.hasOwnProperty('default')) return def.default;
    if (def.type === 'number') return null;
    return '';
  }

  let themeStyleEl = null;
  function applyThemeVariables(){
    const colors = cfg('ui.colors', null);
    if (!colors) return;
    if (!themeStyleEl){
      themeStyleEl = document.createElement('style');
      themeStyleEl.id = 'cfg-theme-vars';
      document.head.appendChild(themeStyleEl);
    }
    const toCss = (vars)=> Object.entries(vars || {}).map(([k,v])=>`--${k}:${v};`).join('');
    let css = '';
    if (colors.light) css += `:root{${toCss(colors.light)}}`;
    if (colors.dark) css += `:root.dark{${toCss(colors.dark)}}`;
    themeStyleEl.textContent = css;
  }

  function applyPanelSizes(){
    const root = document.documentElement;
    const min = cfg('ui.panel_min_px', null);
    const pref = cfg('ui.panel_pref_vw', null);
    const max = cfg('ui.panel_max_px', null);
    if (min!=null) root.style.setProperty('--panel-min', typeof min === 'number' ? `${min}px` : String(min));
    if (pref!=null) root.style.setProperty('--panel-pref', typeof pref === 'number' ? `${pref}vw` : String(pref));
    if (max!=null) root.style.setProperty('--panel-max', typeof max === 'number' ? `${max}px` : String(max));
  }

  function applyPanelSticky(){
    if (!panelTopEl) return;
    const raw = cfg('ui.panel.sticky_top', false);
    let enabled = false;
    let opts = {};
    if (typeof raw === 'boolean') {
      enabled = raw;
    } else if (raw && typeof raw === 'object') {
      enabled = !!raw.enabled;
      opts = raw;
    }
    panelTopEl.classList.toggle('sticky', enabled);
    if (enabled && opts && typeof opts === 'object') {
      const topVal = opts.top ?? opts.offset;
      if (topVal != null) {
        panelTopEl.style.top = typeof topVal === 'number' ? `${topVal}px` : String(topVal);
      } else {
        panelTopEl.style.top = '';
      }
      const shadowVal = opts.shadow;
      if (shadowVal != null) {
        panelTopEl.style.boxShadow = shadowVal === '' ? '' : String(shadowVal);
      } else {
        panelTopEl.style.boxShadow = '';
      }
      const bgVal = opts.background;
      if (bgVal != null) {
        panelTopEl.style.background = bgVal === '' ? '' : String(bgVal);
      } else {
        panelTopEl.style.background = '';
      }
      const zi = opts.z_index ?? opts.zIndex;
      if (zi != null) {
        panelTopEl.style.zIndex = String(zi);
      } else {
        panelTopEl.style.zIndex = '';
      }
    } else {
      panelTopEl.style.top = '';
      panelTopEl.style.boxShadow = '';
      panelTopEl.style.background = '';
      panelTopEl.style.zIndex = '';
    }
  }

  function applyQuickSearchClearStyles(){
    if (!quickSearchClearBtn) return;
    const root = document.documentElement;
    const dark = root.classList.contains('dark');
    quickSearchClearBtn.style.background = dark ? '#1f2937' : '#f3f4f6';
    quickSearchClearBtn.style.color = dark ? '#f8fafc' : '#111827';
    quickSearchClearBtn.style.borderColor = dark ? '#475569' : '#d1d5db';
  }

  // ======= UNDO
  function undoLimit(){
    const limit = Number(cfg('history.max_steps', 3));
    return Number.isFinite(limit) && limit > 0 ? Math.floor(limit) : 0;
  }
  function undoFeatureEnabled(){
    return cfg('history.undo_enabled', true) && feature('toolbar.undo', true) && undoBtn;
  }
  function pushSnapshot() {
    if (!undoFeatureEnabled()) return;
    const snap = {
      items: JSON.parse(JSON.stringify(state.items)),
      roundMeta: JSON.parse(JSON.stringify(normalizedRoundMeta()))
    };
    const limit = undoLimit();
    if (limit <= 0) return;
    if (history.length >= limit) history.shift();
    history.push(snap);
    updateUndoButton();
  }
  function canUndo(){ return history.length > 0; }
  function updateUndoButton(){
    if (!undoBtn) return;
    const enabled = undoFeatureEnabled();
    undoBtn.style.display = enabled ? '' : 'none';
    const hasUndo = canUndo();
    undoBtn.disabled = !enabled || !hasUndo;
    const title = cfg('text.toolbar.undo.title', text('toolbar.undo', 'Visszavonás'));
    undoBtn.title = enabled
      ? (hasUndo ? title : (cfg('text.messages.undo_unavailable', 'Nincs visszavonható művelet')))
      : title;
  }
  async function doUndo(){
    if (!undoFeatureEnabled() || !canUndo()) return;
    const prev = history.pop();
    if (Array.isArray(prev)) {
      state.items = prev;
    } else if (prev && typeof prev === 'object') {
      state.items = Array.isArray(prev.items) ? prev.items : [];
      state.roundMeta = {};
      const prevMeta = prev.roundMeta;
      if (prevMeta && typeof prevMeta === 'object' && !Array.isArray(prevMeta)) {
        Object.entries(prevMeta).forEach(([rid, entry])=>{
          if (!entry || typeof entry !== 'object') return;
          const date = typeof entry.planned_date === 'string' ? entry.planned_date.trim() : '';
          const limited = date ? date.slice(0, 120) : '';
          if (limited) {
            state.roundMeta[String(rid)] = {planned_date: limited};
          }
        });
      }
    } else {
      state.items = [];
      state.roundMeta = {};
    }
    await saveAll();
    renderEverything();
    updateUndoButton();
  }
  if (undoBtn){
    undoBtn.addEventListener('click', doUndo);
  }

  // ======= DOM
  // ======= THEME
  (function initTheme(){
    const root = document.documentElement;
    const saved = localStorage.getItem('fuvar_theme');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    root.classList.toggle('dark', saved ? (saved==='dark') : prefersDark);
    applyQuickSearchClearStyles();
    if (themeToggle) {
      themeToggle.addEventListener('click', ()=>{
        root.classList.toggle('dark');
        localStorage.setItem('fuvar_theme', root.classList.contains('dark') ? 'dark' : 'light');
        applyQuickSearchClearStyles();
      });
    }
  })();

  // ======= MAP
  const map = L.map('map',{zoomControl:true, preferCanvas:true});
  const markerLayer = L.featureGroup().addTo(map);
  function updatePinCount(){ if (pinCountEl) pinCountEl.textContent = markerLayer.getLayers().length.toString(); }

  function openMarkerPopup(mk, featureKey){
    if (!mk) return;
    if (featureKey && !feature(featureKey, true)) return;
    mk.openPopup();
    setTimeout(()=>{
      if (!mk.isPopupOpen()) mk.openPopup();
    }, 0);
  }

  // ======= HELPERS
  const esc = (s)=> (s??'').toString()
    .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');
  const cssId = (s)=> s.replace(/[^a-zA-Z0-9_-]/g, '_');

  function idealTextColor(hex){
    hex = (hex||'#000').replace('#','');
    const r=parseInt(hex.substr(0,2),16), g=parseInt(hex.substr(2,2),16), b=parseInt(hex.substr(4,2),16);
    const yiq=(r*299 + g*587 + b*114)/1000;
    return yiq >= 140 ? '#111' : '#fff';
  }
  function numberedIcon(hex, num){
    const n = (''+num).slice(0,3);
    const textCol = cfg('ui.marker.auto_contrast', true) ? idealTextColor(hex) : '#fff';
    const sz = cfg('ui.marker.icon_size', 38) || 38;
    const fsz = cfg('ui.marker.font_size', 14) || 14;
    const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" width="${sz}" height="${sz}" viewBox="0 0 32 32">
      <g fill="none">
        <path d="M16 2c6.1 0 11 4.9 11 11 0 7.5-11 17-11 17S5 20.5 5 13c0-6.1 4.9-11 11-11z" fill="${hex}" stroke="#333" stroke-opacity=".25"/>
        <text x="16" y="16" text-anchor="middle" dominant-baseline="middle" font-size="${fsz}" font-family="Arial,Helvetica,sans-serif" font-weight="800" fill="${textCol}">${n}</text>
      </g>
    </svg>`;
    const anchor = Math.round(sz/2);
    return L.icon({iconUrl:'data:image/svg+xml;base64,'+btoa(svg), iconSize:[sz,sz], iconAnchor:[anchor, sz-1], popupAnchor:[0, -Math.max(33, sz-5)]});
  }

  function cityFromDisplay(address, currentCity){
    if (currentCity && currentCity.trim()) return currentCity;
    const bits = (address||'').split(',').map(s=>s.trim());
    const m = bits[0] && bits[0].match(/\b(\d{4})\s+(.+)$/u);
    if (m) return m[2].trim();
    return bits[1] || bits[0] || '';
  }

  function haversineKm(aLat,aLon,bLat,bLon){
    if ([aLat,aLon,bLat,bLon].some(v=>v==null)) return Number.POSITIVE_INFINITY;
    const toRad = d=>d*Math.PI/180;
    const R = 6371;
    const dLat = toRad(bLat - aLat);
    const dLon = toRad(bLon - aLon);
    const sinDL = Math.sin(dLat/2);
    const sinDo = Math.sin(dLon/2);
    const a = sinDL*sinDL + Math.cos(toRad(aLat))*Math.cos(toRad(bLat))*sinDo*sinDo;
    return 2*R*Math.asin(Math.sqrt(a));
  }

  // --- Sorfejléc „meta” (súly/térfogat, vagy piros „!” ha mindkettő hiányzik)
  function metaHTML(it){
    const metrics = getMetricDefs();
    if (!metrics.length) return '';
    const separator = cfg('items.meta_display.separator', ' · ');
    const parts = [];
    metrics.forEach(metric => {
      const raw = it[metric.id];
      if (raw === '' || raw == null) return;
      const num = Number(raw);
      if (!Number.isFinite(num)) return;
      const formatted = formatMetricSum(metric, num, 'row');
      parts.push(esc(formatted));
    });
    if (parts.length){
      return `<span class="vals" style="color:#374151;font-weight:600;font-size:12px;">${parts.join(separator)}</span>`;
    }
    const warnCfg = cfg('items.meta_display.missing_warning', {});
    if (!warnCfg || warnCfg.enabled === false) return '';
    const textVal = warnCfg.text ?? '!';
    const style = warnCfg.style ? ` style="${warnCfg.style}"` : ' style="color:#ef4444;font-weight:800;"';
    const title = warnCfg.title ? ` title="${esc(warnCfg.title)}"` : '';
    const cls = warnCfg.class ? ` class="${esc(warnCfg.class)}"` : '';
    return `<span${cls}${style}${title}>${esc(textVal)}</span>`;
  }
  function updateRowHeaderMeta(row, it){
    const meta = row.querySelector('[data-meta]');
    if (meta) meta.innerHTML = metaHTML(it);
  }

  function valueHasContent(val){
    if (val == null) return false;
    if (typeof val === 'string') return val.trim() !== '';
    if (typeof val === 'number') return Number.isFinite(val);
    if (typeof val === 'boolean') return true;
    if (val && typeof val === 'object') return true;
    return false;
  }

  function isItemCompletelyBlank(it){
    if (!it || typeof it !== 'object') return true;
    const addressFieldId = getAddressFieldId();
    const labelFieldId = getLabelFieldId();
    if (valueHasContent(it[addressFieldId])) return false;
    if (labelFieldId && valueHasContent(it[labelFieldId])) return false;
    for (const field of getFieldDefs()){
      if (!field || !field.id) continue;
      if (field.id === addressFieldId || field.id === labelFieldId) continue;
      if (valueHasContent(it[field.id])) return false;
    }
    for (const metric of getMetricDefs()){
      if (!metric || !metric.id) continue;
      if (valueHasContent(it[metric.id])) return false;
    }
    return true;
  }

  function updateRowPlaceholderState(row, it, forced){
    if (!row) return false;
    const prev = row.dataset.placeholder === 'true';
    const placeholder = typeof forced === 'boolean' ? forced : isItemCompletelyBlank(it);
    row.dataset.placeholder = placeholder ? 'true' : 'false';
    const toggleBtn = row.querySelector('.toggle');
    const body = row.querySelector('.body');
    if (toggleBtn){
      if (placeholder){
        toggleBtn.disabled = true;
        toggleBtn.style.visibility = 'hidden';
        toggleBtn.setAttribute('aria-hidden', 'true');
      } else {
        toggleBtn.disabled = false;
        toggleBtn.style.visibility = '';
        toggleBtn.removeAttribute('aria-hidden');
      }
      if (body) toggleBtn.textContent = body.style.display === 'none' ? '▶' : '▼';
    }
    if (placeholder){
      if (body) body.style.display = '';
      clearCollapsePref(it?.id);
    } else if (placeholder !== prev && body && it){
      setCollapsePref(it.id, body.style.display === 'none');
    }
    return placeholder;
  }

  function updateRowHeaderLabel(row, it){
    const labelEl = row?.querySelector('[data-label-display]');
    if (!labelEl) return;
    const labelFieldId = getLabelFieldId();
    const raw = labelFieldId && it ? it[labelFieldId] : '';
    const str = raw != null ? String(raw).trim() : '';
    if (str) {
      labelEl.textContent = str;
      labelEl.title = str;
      labelEl.classList.remove('placeholder');
    } else {
      const placeholder = isItemCompletelyBlank(it);
      const fallback = placeholder
        ? text('items.new_address_placeholder', 'Új cím hozzáadása')
        : text('items.label_missing', 'Címke nélkül');
      labelEl.textContent = fallback;
      labelEl.title = fallback;
      labelEl.classList.add('placeholder');
    }
  }

  function updateDeadlineIndicator(row, it){
    const indicator = row?.querySelector('[data-deadline-indicator]');
    if (!indicator) return;
    if (!isDeadlineFeatureEnabled()) {
      indicator.style.display = 'none';
      indicator.dataset.visible = 'false';
      indicator.removeAttribute('title');
      indicator.removeAttribute('aria-label');
      indicator.removeAttribute('role');
      indicator.setAttribute('aria-hidden', 'true');
      return;
    }
    indicator.style.display = '';
    indicator.style.setProperty('--deadline-icon-size', `${getDeadlineIconSize()}px`);
    const fid = getDeadlineFieldId();
    const info = it && fid ? parseDeadlineValue(it[fid]) : null;
    if (info) {
      const color = info.color || '#2563eb';
      const tooltip = deadlineTooltip(info);
      indicator.dataset.visible = 'true';
      indicator.style.setProperty('--deadline-color', color);
      indicator.setAttribute('title', tooltip);
      indicator.setAttribute('aria-label', tooltip);
      indicator.setAttribute('role', 'img');
      indicator.setAttribute('aria-hidden', 'false');
    } else {
      indicator.dataset.visible = 'false';
      indicator.style.setProperty('--deadline-color', 'transparent');
      indicator.removeAttribute('title');
      indicator.removeAttribute('aria-label');
      indicator.removeAttribute('role');
      indicator.setAttribute('aria-hidden', 'true');
    }
  }

  // ======= SAVE STATUS (pill a jobb felső sarokban)
  let savePillEl=null, savePillTimer=null;
  function ensureSavePill(){
    if (savePillEl) return savePillEl;
    const el = document.createElement('div');
    el.id = 'saveStatusPill';
    const baseStyle = {
      position: 'fixed',
      top: '10px',
      right: '12px',
      zIndex: '9999',
      padding: '6px 10px',
      borderRadius: '999px',
      fontSize: '12px',
      fontWeight: '600',
      boxShadow: '0 2px 6px rgba(0,0,0,.15)',
      transition: 'opacity .25s ease',
      opacity: '0',
      pointerEvents: 'none'
    };
    const cfgNode = cfg('ui.save_status', {}) || {};
    const positionOverrides = (cfgNode.position && typeof cfgNode.position === 'object') ? cfgNode.position : {};
    const styleOverrides = (cfgNode.style && typeof cfgNode.style === 'object') ? cfgNode.style : {};
    const merged = {...baseStyle, ...positionOverrides, ...styleOverrides};
    Object.entries(merged).forEach(([prop, val])=>{
      if (val == null) return;
      el.style[prop] = typeof val === 'number' ? `${val}` : String(val);
    });
    document.body.appendChild(el);
    savePillEl = el;
    return el;
  }
  function showSaveStatus(ok){
    const el = ensureSavePill();
    el.textContent = ok ? 'Mentve ✓' : 'Mentés sikertelen ✗';
    el.style.border = '';
    el.style.borderColor = '';
    el.style.background = '';
    el.style.color = '';
    const colorsCfg = cfg('ui.save_status.colors', {}) || {};
    const okCfgRaw = (colorsCfg.success && typeof colorsCfg.success === 'object') ? {...colorsCfg.success} : {};
    const failCfgRaw = (colorsCfg.error && typeof colorsCfg.error === 'object') ? {...colorsCfg.error} : {};
    if ('text' in okCfgRaw && !('color' in okCfgRaw)) okCfgRaw.color = okCfgRaw.text;
    if ('text' in failCfgRaw && !('color' in failCfgRaw)) failCfgRaw.color = failCfgRaw.text;
    const okStyles = {...{color:'#065f46', background:'#d1fae5', border:'1px solid #a7f3d0'}, ...okCfgRaw};
    const failStyles = {...{color:'#7f1d1d', background:'#fee2e2', border:'1px solid #fecaca'}, ...failCfgRaw};
    const styles = ok ? okStyles : failStyles;
    Object.entries(styles).forEach(([prop, val])=>{
      if (val == null) return;
      el.style[prop] = typeof val === 'number' ? `${val}` : String(val);
    });
    el.style.opacity = '1';
    clearTimeout(savePillTimer);
    const hideAfterRaw = cfg('ui.save_status.hide_after_ms', null);
    let hideDelay = 1600;
    if (typeof hideAfterRaw === 'number' && hideAfterRaw >= 0) {
      hideDelay = hideAfterRaw;
    } else if (typeof hideAfterRaw === 'string' && hideAfterRaw.trim() !== '') {
      const parsed = Number(hideAfterRaw);
      if (Number.isFinite(parsed) && parsed >= 0) hideDelay = parsed;
    }
    savePillTimer = setTimeout(()=>{ el.style.opacity='0'; }, hideDelay);
  }

  function flashSaved(el){
    if (!el) return;
    if (flashTimers.has(el)) {
      clearTimeout(flashTimers.get(el));
      flashTimers.delete(el);
    }
    el.classList.remove('saved-flash');
    void el.offsetWidth;
    el.classList.add('saved-flash');
    const timer = setTimeout(()=>{
      el.classList.remove('saved-flash');
      flashTimers.delete(el);
    }, 1600);
    flashTimers.set(el, timer);
  }

  // ======= BACKEND
  async function fetchJSON(url, opts){
    const r = await fetch(url, opts);
    const text = await r.text();
    let j; try{ j = JSON.parse(text); } catch(e){ throw new Error('bad_json'); }
    j.__http_ok = r.ok; j.__status = r.status;
    return j;
  }
  async function loadCfg(){ state.cfg = await fetchJSON(EP.cfg); }
  function applyLoadedData(payload){
    const j = payload || {};
    state.items = Array.isArray(j.items)
      ? j.items.map(item => {
          if (!item || typeof item !== 'object') return item;
          const copy = {...item};
          if (Object.prototype.hasOwnProperty.call(copy, 'collapsed')) {
            delete copy.collapsed;
          }
          return copy;
        })
      : [];
    pruneCollapsePrefs(state.items.map(it => it?.id).filter(id => id != null));
    state.roundMeta = {};
    const meta = j.round_meta;
    if (meta && typeof meta === 'object' && !Array.isArray(meta)){
      Object.entries(meta).forEach(([rid, entry])=>{
        if (!entry || typeof entry !== 'object') return;
        const date = typeof entry.planned_date === 'string' ? entry.planned_date.trim() : '';
        const limited = date ? date.slice(0, 120) : '';
        if (limited) {
          state.roundMeta[String(rid)] = {planned_date: limited};
        }
      });
    }
    markerLayer.clearLayers();
    state.markersById.clear();
    updatePinCount();
  }
  async function loadAll(){
    const j = await fetchJSON(EP.load);
    applyLoadedData(j);
  }
  async function saveAll(){
    try{
      const sanitizedItems = state.items.map(item => {
        if (!item || typeof item !== 'object') return item;
        const copy = {...item};
        if (Object.prototype.hasOwnProperty.call(copy, 'collapsed')) {
          delete copy.collapsed;
        }
        return copy;
      });
      const payload = JSON.stringify({items: sanitizedItems, round_meta: normalizedRoundMeta()});
      const r = await fetch(EP.save, {method:'POST', headers:{'Content-Type':'application/json'}, body: payload});
      const t = await r.text();
      let j=null; try{ j = JSON.parse(t); }catch(_){}
      const ok = !!(r.ok && j && j.ok===true);
      showSaveStatus(ok);
      return ok;
    }catch(e){
      console.error(e);
      showSaveStatus(false);
      return false;
    }
  }
  async function geocodeRobust(q){
    const qNorm = q.replace(/^\s*([^,]+)\s*,\s*(.+?)\s*,\s*(\d{4})\s*$/u, '$3 $1, $2');
    const url = EP.geocode + '&' + new URLSearchParams({q:qNorm});
    async function one(){ const r = await fetch(url,{cache:'no-store'}); const t=await r.text(); let j; try{ j=JSON.parse(t);}catch(e){throw new Error('geocode_error');} if(!r.ok||j.error) throw new Error('geocode_error'); return j; }
    try{ return await one(); } catch(_){ return await one(); }
  }

  async function autoGeocodeImported(targetIds){
    const idSet = Array.isArray(targetIds) && targetIds.length ? new Set(targetIds.map(id => String(id))) : null;
    const addressFieldId = getAddressFieldId();
    const labelFieldId = getLabelFieldId();
    let changed = false;
    let attempted = 0;
    let failed = 0;
    const failures = [];
    for (let i = 0; i < state.items.length; i += 1) {
      const item = state.items[i];
      if (!item || typeof item !== 'object') continue;
      const idStr = item.id != null ? String(item.id) : '';
      if (idSet && !idSet.has(idStr)) continue;
      if (item.lat != null && item.lon != null) continue;
      const address = (item[addressFieldId] ?? '').toString().trim();
      if (!address) continue;
      attempted += 1;
      try {
        const geo = await geocodeRobust(address);
        const updated = {...item};
        updated.lat = geo.lat;
        updated.lon = geo.lon;
        updated.city = geo.city || cityFromDisplay(address, item.city);
        state.items[i] = updated;
        changed = true;
      } catch (err) {
        console.error('auto geocode failed for import', err);
        failed += 1;
        const idPart = item.id != null ? `#${item.id} ` : '';
        const label = (item[labelFieldId] ?? '').toString().trim();
        const labelPart = label ? `${label} – ` : '';
        failures.push(`${idPart}${labelPart}${address}`.trim());
      }
    }
    let saveOk = true;
    if (changed) {
      saveOk = await saveAll();
    }
    return {changed, saveOk, failed, attempted, failures};
  }

  // ======= AUTO SORT (kör + körön belül távolság)
  function autoSortItems(){
    if (!cfg('app.auto_sort_by_round', true)) return;
    const zeroBottom = !!cfg('app.round_zero_at_bottom', false);
    const groups = new Map();
    state.items.forEach((it, idx)=>{
      const rid = +it.round || 0;
      if (!groups.has(rid)) groups.set(rid, []);
      groups.get(rid).push({it, idx});
    });

    const roundIds = Array.from(groups.keys()).sort((a,b)=>a-b);
    if (zeroBottom){
      const zeroIdx = roundIds.indexOf(0);
      if (zeroIdx !== -1){
        roundIds.splice(zeroIdx, 1);
        roundIds.push(0);
      }
    }

    const hasCoords = (entry)=>{
      const {lat, lon} = entry.it;
      return lat !== '' && lon !== '' && lat != null && lon != null;
    };

    const ordered = [];
    roundIds.forEach(rid => {
      const entries = groups.get(rid) || [];
      const withCoords = entries.filter(hasCoords);
      const withoutCoords = entries.filter(entry => !hasCoords(entry));

      const sorted = [];
      if (withCoords.length){
        const remaining = withCoords.slice();
        let currentIdx = 0;
        let bestStart = Infinity;
        for (let i=0;i<remaining.length;i++){
          const candidate = remaining[i];
          const distRaw = haversineKm(state.ORIGIN.lat, state.ORIGIN.lon, candidate.it.lat, candidate.it.lon);
          const dist = Number.isFinite(distRaw) ? distRaw : Infinity;
          if (dist < bestStart){
            bestStart = dist;
            currentIdx = i;
          }
        }
        let current = remaining.splice(currentIdx,1)[0];
        sorted.push(current);
        while (remaining.length){
          let nextIdx = 0;
          let bestDist = Infinity;
          for (let i=0;i<remaining.length;i++){
            const candidate = remaining[i];
            const distRaw = haversineKm(current.it.lat, current.it.lon, candidate.it.lat, candidate.it.lon);
            const dist = Number.isFinite(distRaw) ? distRaw : Infinity;
            if (dist < bestDist){
              bestDist = dist;
              nextIdx = i;
            }
          }
          current = remaining.splice(nextIdx,1)[0];
          sorted.push(current);
        }
      }

      withoutCoords.sort((a,b)=>a.idx-b.idx);
      sorted.concat(withoutCoords).forEach(entry => ordered.push(entry.it));
    });

    state.items = ordered;
  }

  // ======= ROUNDS
  let ROUND_MAP, ROUND_ORDER;
  const roundLabel = (r)=> (ROUND_MAP.get(Number(r))?.label) ?? String(r);
  const colorForRound = (r)=> (ROUND_MAP.get(Number(r))?.color) ?? '#374151';
  const isRoundZero = (r)=> Number(r) === 0;

  function hasBlankInDefaultRound(){
    const addrField = getAddressFieldId();
    return state.items.some(it => ((+it.round||0)===0) && !(it[addrField] && it[addrField].toString().trim()) && it.lat==null && it.lon==null);
  }
  function ensureBlankRowInDefaultRound(){
    if (!hasBlankInDefaultRound()){
      const blank = {
        id:'row_'+Math.random().toString(36).slice(2),
        city:'',
        lat:null, lon:null,
        round:0
      };
      getFieldDefs().forEach(field => {
        blank[field.id] = defaultValueForField(field);
      });
      getMetricDefs().forEach(metric => {
        blank[metric.id] = null;
      });
      state.items.push(blank);
    }
  }

  function totalsForRound(rid){
    const metrics = getMetricDefs();
    const sums = {};
    const counts = {};
    metrics.forEach(metric => { sums[metric.id] = 0; counts[metric.id] = 0; });
    state.items.forEach(it=>{
      if ((+it.round||0)!==rid) return;
      metrics.forEach(metric => {
        const raw = it[metric.id];
        if (raw === '' || raw == null) return;
        const num = Number(raw);
        if (!Number.isFinite(num)) return;
        sums[metric.id] += num;
        counts[metric.id] += 1;
      });
    });
    return {sums, counts};
  }

  function formatMetricSum(metric, value, context='group'){
    const precision = Number.isFinite(Number(metric.precision)) ? Number(metric.precision) : 0;
    const val = Number.isFinite(value) ? value.toFixed(precision) : (0).toFixed(precision);
    const tplKey = context === 'row' ? 'row_format' : 'group_format';
    const tpl = metric[tplKey];
    if (tpl) return format(tpl, {value: val, sum: val, unit: metric.unit ?? '', label: metric.label ?? ''});
    return `${val}${metric.unit ? ' '+metric.unit : ''}`;
  }

  function groupTotalsText(rid, totals){
    if (!feature('group_totals', true)) return '';
    const metrics = getMetricDefs();
    if (!metrics.length) return '';
    const sep = cfg('text.group.sum_separator', ' · ');
    const parts = metrics.map(metric => {
      const sum = Number(totals.sums?.[metric.id] ?? 0);
      return formatMetricSum(metric, sum, 'group');
    }).filter(Boolean);
    if (!parts.length) return '';
    const template = cfg('text.group.sum_template', 'Összesen: {parts}');
    return format(template, {parts: parts.join(sep), round: roundLabel(rid)});
  }

  function makeGroupHeader(rid, totals){
    const g = document.createElement('div');
    g.className = 'group';
    const color = colorForRound(rid);
    const sumTxt = groupTotalsText(rid, totals);
    const actionsText = cfg('text.group.actions', {});
    const plannedDateEnabled = feature('round_planned_date', false);
    const plannedDateLabel = text('round.planned_date_label', 'Tervezett dátum');
    const plannedDateHint = cfg('text.round.planned_date_hint', '');
    const plannedDateValue = getPlannedDateForRound(rid);
    const plannedDateInputId = `round_${cssId(String(rid))}_planned_date`;
    const showPlannedDate = plannedDateEnabled && !isRoundZero(rid);
    const actionButtons = [];
    if (feature('group_actions.open', true)) actionButtons.push(`<button class="iconbtn grp-open" data-round="${rid}">${esc(actionsText.open ?? 'Kinyit')}</button>`);
    if (feature('group_actions.close', true)) actionButtons.push(`<button class="iconbtn grp-close" data-round="${rid}">${esc(actionsText.close ?? 'Összezár')}</button>`);
    if (feature('group_actions.print', true)) actionButtons.push(`<button class="iconbtn grp-print" data-round="${rid}">${esc(actionsText.print ?? 'Nyomtatás')}</button>`);
    if (feature('group_actions.navigate', true)) actionButtons.push(`<button class="iconbtn grp-nav" data-round="${rid}">${esc(actionsText.navigate ?? 'Navigáció')}</button>`);
    if (feature('group_actions.delete', true)) actionButtons.push(`<button class="iconbtn grp-del" data-round="${rid}" style="border-color:#fecaca;background:rgba(248,113,113,0.12);">${esc(actionsText.delete ?? 'Kör törlése')}</button>`);
    g.innerHTML = `
      <div class="group-header" data-group-header="${rid}">
        <div class="group-title">
          <span style="display:inline-block;width:12px;height:12px;border-radius:999px;background:${color};border:1px solid #d1d5db;margin-right:8px;vertical-align:middle"></span>
          ${esc(roundLabel(rid))}
          ${sumTxt ? `<span class="__sum" style="margin-left:8px;color:#6b7280;font-weight:600;font-size:12px;">${esc(sumTxt)}</span>` : ''}
        </div>
        ${showPlannedDate ? `
        <div class="group-planned-date">
          <label class="planned-date-label" for="${plannedDateInputId}">${esc(plannedDateLabel)}</label>
          <input type="text" id="${plannedDateInputId}" class="planned-date-input" data-round="${rid}" value="${esc(plannedDateValue)}"${plannedDateHint ? ` title="${esc(plannedDateHint)}"` : ''}>
        </div>` : ''}
        <div class="group-tools">
          ${actionButtons.join('')}
        </div>
      </div>
      <div class="group-body" data-group-body="${rid}"></div>
    `;
    return g;
  }

  // --- Körfejlécek összeg frissítése (duplikáció nélkül)
  function renderGroupHeaderTotalsForRound(rid){
    const hdr = document.querySelector(`[data-group-header="${rid}"] .group-title`);
    if (!hdr) return;
    const t = totalsForRound(rid);
    const sums = hdr.querySelectorAll('span.__sum');
    let span;
    if (sums.length === 0) {
      span = document.createElement('span');
      span.className = '__sum';
      span.style.marginLeft = '8px';
      span.style.color = '#6b7280';
      span.style.fontWeight = '600';
      span.style.fontSize = '12px';
      hdr.appendChild(span);
    } else {
      span = sums[0];
      for (let i=1;i<sums.length;i++) sums[i].remove();
    }
    const txt = groupTotalsText(rid, t);
    if (txt){
      span.style.display = '';
      span.textContent = txt;
    } else {
      span.style.display = 'none';
    }
  }

  function refreshDeleteButtonState(row, it){
    const delBtn = row.querySelector('.del');
    const notSaved = (it.lat==null || it.lon==null);
    const addrField = getAddressFieldId();
    const hasAddr = !!((it[addrField] ?? '').toString().trim());
    const isDefaultBlank = (+it.round||0)===0 && notSaved && !hasAddr;
    delBtn.disabled = isDefaultBlank;
    if (delBtn.disabled){
      delBtn.title = text('actions.delete_disabled_hint', '');
    } else {
      delBtn.title = '';
    }
  }
  function refreshDeleteButtonsAll(){
    groupsEl.querySelectorAll('.row').forEach(row=>{
      const id = row.dataset.rowId;
      const it = state.items.find(x=>x.id===id);
      if (it) refreshDeleteButtonState(row, it);
    });
  }

  function upsertMarker(it, index){
    if (it.lat==null || it.lon==null) return;
    const color = colorForRound(+it.round||0);
    const icon = numberedIcon(color, index+1);
    let mk = state.markersById.get(it.id);
    if (!mk){
      mk = L.marker([it.lat, it.lon], {icon}).addTo(markerLayer);
      mk.on('click', ()=>{
        highlightRow(it.id, true);
        if (feature('marker_focus_feedback', true)) pingMarker(it.id); // fókusz szinkron: pin ping
        const row = state.rowsById.get(it.id);
        if (row){
          row.scrollIntoView({behavior:'smooth', block:'center'});
          row.classList.add('active'); setTimeout(()=>row.classList.remove('active'), 1000);
          const body = row.querySelector('.body'); const tog = row.querySelector('.toggle');
          if (body && body.style.display==='none'){ body.style.display=''; if (tog) tog.textContent='▼'; }
          const first = row.querySelector('input,select'); if (first){ first.focus(); }
        }
        openMarkerPopup(mk, 'marker_popup_on_click');
        map.panTo(mk.getLatLng());
      });
      state.markersById.set(it.id, mk);
    } else mk.setLatLng([it.lat, it.lon]);
    mk.setIcon(icon);
    const labelField = getLabelFieldId();
    const addressField = getAddressFieldId();
    const noteField = getNoteFieldId();
    const extras = [];
    getMetricDefs().forEach(metric => {
      const raw = it[metric.id];
      if (raw === '' || raw == null) return;
      const num = Number(raw);
      if (!Number.isFinite(num)) return;
      extras.push(formatMetricSum(metric, num, 'row'));
    });
    const labelVal = it[labelField] ?? '';
    const addrVal = it[addressField] ?? '';
    const noteVal = noteField ? (it[noteField] ?? '') : '';
    const popupFields = [];
    if (labelVal) popupFields.push(`<div style="font-weight:600">${esc(labelVal)}</div>`);
    if (addrVal) popupFields.push(`<div style="color:#4b5563">${esc(addrVal)}</div>`);
    if (extras.length) popupFields.push(`<div style="margin-top:4px;color:#334155">${extras.map(esc).join(' · ')}</div>`);
    if (noteVal) popupFields.push(`<div style="margin-top:4px;">${esc(noteVal)}</div>`);
    if (isDeadlineFeatureEnabled()) {
      const deadlineFieldId = getDeadlineFieldId();
      const info = deadlineFieldId ? parseDeadlineValue(it[deadlineFieldId]) : null;
      if (info) {
        popupFields.push(`<div style="margin-top:4px;color:#1f2937;">${esc(deadlineDisplay(info))}</div>`);
      }
    }
    let googleQuery = '';
    const latNum = Number(it.lat);
    const lonNum = Number(it.lon);
    if (typeof addrVal === 'string' && addrVal.trim()) {
      googleQuery = addrVal.trim();
    } else if (Number.isFinite(latNum) && Number.isFinite(lonNum)) {
      googleQuery = `${latNum},${lonNum}`;
    }
    if (googleQuery) {
      const mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(googleQuery);
      const linkLabel = text('marker.google_maps_link', 'Megnyitás Google Térképen');
      popupFields.push(`<div style="margin-top:6px;"><a href="${esc(mapsUrl)}" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600;text-decoration:none;">${esc(linkLabel)}</a></div>`);
    }
    const html = `
      <div style="font-size:14px;line-height:1.35;">
        ${popupFields.join('')}
      </div>`;
    mk.bindPopup(html, {maxWidth:320});
    updatePinCount();
  }

  function renumberAll(){
    let i=0;
    groupsEl.querySelectorAll('.row').forEach(row=>{
      const nEl = row.querySelector('[data-num]'); if (!nEl) return;
      nEl.textContent = String(++i).padStart(2,'0');
      const id = row.dataset.rowId;
      const it = state.items.find(x=>x.id===id);
      if (it){
        const c = colorForRound(+it.round||0);
        nEl.style.background = c;
        nEl.style.color = idealTextColor(c);
      }
    });
    state.items.forEach((it,idx)=>{
      const mk = state.markersById.get(it.id);
      if (mk){ mk.setIcon(numberedIcon(colorForRound(+it.round||0), idx+1)); }
    });
  }

  function highlightRow(id, flash=false){
    groupsEl.querySelectorAll('.row').forEach(r=>r.classList.remove('highlight'));
    const row = state.rowsById.get(id);
    if (row){
      row.classList.add('highlight');
      if (flash){ setTimeout(()=>row.classList.remove('highlight'), 800); }
    }
  }

  // ======= Pin „ping” (fókusz-visszajelzés)
  function pingMarker(id){
    if (!feature('marker_focus_feedback', true)) return;
    const it = state.items.find(x=>x.id===id);
    if (!it || it.lat==null || it.lon==null) return;
    const radiusCfg = Number(cfg('ui.marker.focus_ring_radius', 80));
    const radius = Number.isFinite(radiusCfg) && radiusCfg > 0 ? radiusCfg : 80;
    const colorSetting = cfg('ui.marker.focus_ring_color', 'auto');
    const baseColor = (typeof colorSetting === 'string' && colorSetting.toLowerCase() !== 'auto') ? colorSetting : colorForRound(+it.round||0);
    const c = L.circle([it.lat, it.lon], {radius, color: baseColor, weight: 2, fillColor: baseColor, fillOpacity: 0.25, opacity: 0.8});
    c.addTo(map);
    let op = 0.6, fo = 0.25;
    const iv = setInterval(()=>{
      op -= 0.12; fo -= 0.06;
      c.setStyle({opacity: Math.max(0,op), fillOpacity: Math.max(0,fo)});
      if (op <= 0){ clearInterval(iv); markerLayer.removeLayer(c); map.removeLayer(c); }
    }, 60);
    setTimeout(()=>{ try{ markerLayer.removeLayer(c); map.removeLayer(c);}catch(_){ } }, 800);
  }

  async function doOk(id, overrideRound=null){
    const idx = state.items.findIndex(x=>x.id===id);
    if (idx<0) return;
    const it = state.items[idx];
    const row = state.rowsById.get(id);
    const okBtn = row?.querySelector('.ok');
    const fields = getFieldDefs();
    const metrics = getMetricDefs();
    const addressFieldId = getAddressFieldId();
    const labelFieldId = getLabelFieldId();
    const addressInput = row?.querySelector(`[data-field="${addressFieldId}"]`);
    const address = (addressInput ? addressInput.value : it[addressFieldId] || '').toString().trim();
    if (!address){ alert(text('messages.address_required', 'Adj meg teljes címet!')); if (addressInput) addressInput.focus(); return; }
    if (okBtn){ okBtn.disabled=true; okBtn.textContent='...'; }
    try{
      const g = await geocodeRobust(address);
      const newRound = (overrideRound!=null) ? overrideRound : (typeof it._pendingRound!=='undefined' ? it._pendingRound : it.round);
      pushSnapshot();
      const updated = {...it};
      fields.forEach(field => {
        const el = row?.querySelector(`[data-field="${field.id}"]`);
        if (!el) return;
        if (field.type === 'number'){
          const raw = el.value.trim();
          updated[field.id] = raw === '' ? null : parseFloat(raw);
        } else if (field.type === 'textarea'){
          updated[field.id] = el.value.trim();
        } else {
          updated[field.id] = el.value.trim();
        }
      });
      metrics.forEach(metric => {
        const el = row?.querySelector(`[data-field="${metric.id}"]`);
        if (!el) return;
        const raw = el.value.trim();
        if (raw === '') updated[metric.id] = null;
        else {
          const num = parseFloat(raw);
          updated[metric.id] = Number.isFinite(num) ? num : null;
        }
      });
      updated[addressFieldId] = address;
      if (labelFieldId && updated[labelFieldId] != null) {
        updated[labelFieldId] = updated[labelFieldId].toString().trim();
      }
      updated.city = g.city || cityFromDisplay(address, it.city);
      updated.lat = g.lat;
      updated.lon = g.lon;
      updated.round = +newRound || 0;
      state.items[idx] = updated;
      delete state.items[idx]._pendingRound;
      upsertMarker(state.items[idx], idx);
      ensureBlankRowInDefaultRound();
      setCollapsePref(state.items[idx].id, false);
      await saveAll();
      if (row) updateRowHeaderMeta(row, state.items[idx]);
      renderEverything();
    } finally{
      if (okBtn){ okBtn.disabled=false; okBtn.textContent='OK'; }
    }
  }

  function renderRow(it, globalIndex){
    const addressFieldId = getAddressFieldId();
    const labelFieldId = getLabelFieldId();
    const city = cityFromDisplay(it[addressFieldId], it.city);
    const row = document.createElement('div');
    row.className = 'row';
    row.dataset.rowId = it.id;
    const isPlaceholderItem = isItemCompletelyBlank(it);
    const storedCollapsed = getCollapsePref(it.id);
    const collapsed = isPlaceholderItem ? false : (storedCollapsed == null ? DEFAULT_COLLAPSED : storedCollapsed);
    const roundColor = colorForRound(+it.round||0);
    const numTextColor = idealTextColor(roundColor);

    const fields = getFieldDefs();
    const metrics = getMetricDefs();
    const makeInputId = (fid)=> `${fid}_${cssId(it.id)}`;

    const fieldHtml = fields.map(field => {
      const fid = field.id;
      const value = it[fid] ?? defaultValueForField(field);
      const idAttr = makeInputId(fid);
      const label = field.label ?? fid;
      const placeholder = field.placeholder ?? '';
      const attrs = [];
      if (field.maxlength) attrs.push(`maxlength="${field.maxlength}"`);
      if (field.autocomplete) attrs.push(`autocomplete="${field.autocomplete}"`);
      if (field.required) attrs.push('required');
      const attrStr = attrs.join(' ');
      if (field.type === 'textarea'){
        return `
          <div class="f">
            <label for="${idAttr}">${esc(label)}</label>
            <textarea id="${idAttr}" data-field="${esc(fid)}" placeholder="${esc(placeholder)}" ${attrStr}>${esc(value ?? '')}</textarea>
          </div>`;
      }
      if (field.type === 'select' && Array.isArray(field.options)){
        const opts = field.options.map(opt => {
          const val = typeof opt === 'object' ? opt.value : opt;
          const textVal = typeof opt === 'object' ? opt.label : opt;
          const selected = String(value) === String(val) ? 'selected' : '';
          return `<option value="${esc(val)}" ${selected}>${esc(textVal)}</option>`;
        }).join('');
        return `
          <div class="f">
            <label for="${idAttr}">${esc(label)}</label>
            <select id="${idAttr}" data-field="${esc(fid)}">${opts}</select>
          </div>`;
      }
      const typeAttr = field.type === 'number' ? 'number' : (field.type === 'date' ? 'date' : 'text');
      const extra = [];
      if (field.step != null) extra.push(`step="${field.step}"`);
      if (field.min != null) extra.push(`min="${field.min}"`);
      if (field.max != null) extra.push(`max="${field.max}"`);
      const extraStr = extra.join(' ');
      const valueAttr = value != null ? `value="${typeAttr==='number' && value!=='' ? esc(value) : esc(value)}"` : '';
      return `
        <div class="f">
          <label for="${idAttr}">${esc(label)}</label>
          <input id="${idAttr}" type="${typeAttr}" data-field="${esc(fid)}" placeholder="${esc(placeholder)}" ${valueAttr} ${attrStr} ${extraStr}>
        </div>`;
    }).join('');

    const metricsHtml = metrics.map(metric => {
      const fid = metric.id;
      const idAttr = makeInputId(fid);
      const value = it[fid] ?? '';
      const placeholder = metric.placeholder ?? '';
      const extra = [];
      if (metric.step != null) extra.push(`step="${metric.step}"`);
      if (metric.min != null) extra.push(`min="${metric.min}"`);
      if (metric.max != null) extra.push(`max="${metric.max}"`);
      const extraStr = extra.join(' ');
      const valueAttr = value!=='' && value!=null ? `value="${esc(value)}"` : '';
      return `
        <div class="f">
          <label for="${idAttr}">${esc(metric.label ?? fid)}</label>
          <input id="${idAttr}" type="number" data-field="${esc(fid)}" placeholder="${esc(placeholder)}" ${valueAttr} ${extraStr}>
        </div>`;
    }).join('');

    const actionsText = cfg('text.actions', {});
    const okLabel = actionsText.ok ?? 'OK';
    const delLabel = actionsText.delete ?? 'Törlés';
    const roundLabelText = cfg('items.round_field.label', 'Kör');

    const rawLabelValue = labelFieldId ? (it[labelFieldId] ?? '') : '';
    const labelString = rawLabelValue != null ? String(rawLabelValue).trim() : '';
    const labelPlaceholder = isPlaceholderItem
      ? text('items.new_address_placeholder', 'Új cím hozzáadása')
      : text('items.label_missing', 'Címke nélkül');
    const deadlineFieldId = getDeadlineFieldId();
    const deadlineEnabled = isDeadlineFeatureEnabled();
    const deadlineSize = getDeadlineIconSize();
    const toggleExtraAttrs = isPlaceholderItem ? ' disabled aria-hidden="true" style="visibility:hidden;"' : '';
    const bodyStyle = (!isPlaceholderItem && collapsed) ? 'display:none' : '';

    row.innerHTML = `
      <div class="header">
        <div class="num" data-num style="background:${roundColor}; color:${numTextColor}">${String(globalIndex+1).padStart(2,'0')}</div>
        <div class="header-main">
          <div class="title-label-row">
            <span class="title-label${labelString ? '' : ' placeholder'}" data-label-display title="${esc(labelString || labelPlaceholder)}">${esc(labelString || labelPlaceholder)}</span>
            <span class="deadline-indicator" data-deadline-indicator style="--deadline-icon-size:${deadlineSize}px;${deadlineEnabled?'':'display:none;'}" aria-hidden="true"></span>
          </div>
          <div class="title-city" data-city title="${esc(city||'—')}">${esc(city || '—')}</div>
        </div>
        <div class="meta" data-meta style="margin-left:auto;margin-right:8px;"></div>
        <div class="tools"><button class="iconbtn toggle"${toggleExtraAttrs} title="${collapsed?esc(text('toolbar.expand_all.title','Kinyit')):esc(text('toolbar.collapse_all.title','Összezár'))}">${collapsed?'▶':'▼'}</button></div>
      </div>
      <div class="body" style="${bodyStyle}">
        <div class="form-grid">
          ${fieldHtml}
        </div>
        <div class="metrics-grid" style="align-items:end;${metrics.length?'':'display:none'}">
          <div class="f" style="max-width:140px">
            <label for="round_${cssId(it.id)}">${esc(roundLabelText)}</label>
            <select id="round_${cssId(it.id)}" class="select-round">
              ${Array.from(ROUND_MAP.values()).map(r => {
                const sel = (+it.round===+r.id) ? 'selected' : '';
                return `<option value="${r.id}" ${sel}>${esc(r.label)}</option>`;
              }).join('')}
            </select>
          </div>
          ${metricsHtml}
        </div>
        <div class="grid" style="margin-top:6px;">
          <div></div>
          <div class="btns">
            <button class="ok">${esc(okLabel)}</button>
            <button class="del">${esc(delLabel)}</button>
          </div>
        </div>
      </div>
    `;

    updateRowHeaderMeta(row, it);
    updateRowPlaceholderState(row, it, isPlaceholderItem);
    updateRowHeaderLabel(row, it);
    updateDeadlineIndicator(row, it);

    // Lista → Térkép fókusz: kattintás és fókusz események
    row.addEventListener('click', (e) => {
      if ((e.target instanceof HTMLElement) && e.target.classList.contains('iconbtn')) return;
      highlightRow(it.id);
      const mk = state.markersById.get(it.id);
      if (mk) {
        openMarkerPopup(mk, 'marker_popup_on_click');
        if (feature('marker_focus_feedback', true)) pingMarker(it.id);
        map.panTo(mk.getLatLng());
      }
    });
    row.addEventListener('focusin', ()=>{
      // bármely input/elem fókuszba kerül a soron belül → pin ping + popup
      const mk = state.markersById.get(it.id);
      if (mk) {
        openMarkerPopup(mk, 'marker_popup_on_focus');
        if (feature('marker_focus_feedback', true)) pingMarker(it.id);
      }
    });

    const toggleBtn = row.querySelector('.toggle');
    if (toggleBtn){
      toggleBtn.addEventListener('click', (e)=>{
        e.stopPropagation();
        if (!(e.currentTarget instanceof HTMLButtonElement)) return;
        if (e.currentTarget.disabled) return;
        const body = row.querySelector('.body');
        if (!body) return;
        const hidden = body.style.display === 'none';
        body.style.display = hidden ? '' : 'none';
        e.currentTarget.textContent = hidden ? '▼' : '▶';
        setCollapsePref(it.id, !hidden);
      });
    }

    const fieldInputs = new Map();
    const metricInputs = new Map();
    const fieldsMap = new Map(fields.map(f=>[f.id, f]));
    fields.forEach(field => {
      const el = row.querySelector(`[data-field="${field.id}"]`);
      if (el) fieldInputs.set(field.id, el);
    });
    metrics.forEach(metric => {
      const el = row.querySelector(`[data-field="${metric.id}"]`);
      if (el) metricInputs.set(metric.id, el);
    });
    const roundS = row.querySelector('#round_'+cssId(it.id));
    const okBtn  = row.querySelector('.ok');
    const delBtn = row.querySelector('.del');

    fieldInputs.forEach((inp, fid)=>{
      inp.addEventListener('change', ()=>{
        const idx = state.items.findIndex(x=>x.id===it.id);
        if (idx<0) return;
        pushSnapshot();
        const def = fieldsMap.get(fid) || {};
        let val;
        if (def.type === 'number'){
          const trimmed = inp.value.trim();
          const num = parseFloat(trimmed);
          val = trimmed==='' || !Number.isFinite(num) ? null : num;
        } else {
          val = inp.value.trim();
        }
        state.items[idx][fid] = val;
        if (fid === addressFieldId){
          const cityNow = cityFromDisplay(state.items[idx][fid], state.items[idx].city);
          state.items[idx].city = cityNow;
          const cityEl = row.querySelector('[data-city]');
          if (cityEl){
            cityEl.textContent = cityNow || '—';
            cityEl.setAttribute('title', cityNow || '—');
          }
          refreshDeleteButtonState(row, state.items[idx]);
        }
        updateRowPlaceholderState(row, state.items[idx]);
        updateRowHeaderLabel(row, state.items[idx]);
        if (fid === deadlineFieldId){
          updateDeadlineIndicator(row, state.items[idx]);
        }
        upsertMarker(state.items[idx], idx);
        saveAll();
        updateRowHeaderMeta(row, state.items[idx]);
      });
    });

    metricInputs.forEach((inp, fid)=>{
      inp.addEventListener('change', ()=>{
        const idx = state.items.findIndex(x=>x.id===it.id);
        if (idx<0) return;
        pushSnapshot();
        const raw = inp.value.trim();
        if (raw==='') state.items[idx][fid] = null;
        else {
          const num = parseFloat(raw);
          state.items[idx][fid] = Number.isFinite(num) ? num : null;
        }
        updateRowPlaceholderState(row, state.items[idx]);
        updateRowHeaderLabel(row, state.items[idx]);
        upsertMarker(state.items[idx], idx);
        saveAll();
        renderGroupHeaderTotalsForRound(+state.items[idx].round||0);
        updateRowHeaderMeta(row, state.items[idx]);
      });
    });

    roundS.addEventListener('change', async ()=>{
      const idx = state.items.findIndex(x=>x.id===it.id); if (idx<0) return;
      const selRound = +roundS.value;
      const addrVal = state.items[idx][addressFieldId];
      const hasAddress = !!(addrVal && addrVal.toString().trim());
      const hasPin = (state.items[idx].lat!=null && state.items[idx].lon!=null);

      if (!hasAddress) { state.items[idx]._pendingRound = selRound; return; }
      if (!hasPin) {
        try{ await doOk(state.items[idx].id, selRound); }
        catch(e){ console.error(e); alert(text('messages.geocode_failed', 'Geokódolás sikertelen.')); roundS.value = String(state.items[idx].round ?? 0); }
        return;
      }
      const prevRound = +state.items[idx].round||0;
      pushSnapshot();
      state.items[idx].round = selRound;
      await saveAll();
      renderEverything();
      renderGroupHeaderTotalsForRound(prevRound);
      renderGroupHeaderTotalsForRound(selRound);
    });

    okBtn.addEventListener('click', async ()=>{ try{ await doOk(it.id, null); } catch(e){ console.error(e); alert(text('messages.geocode_failed_detailed', 'Geokódolás sikertelen. Próbáld pontosítani a címet.')); } });

    delBtn.addEventListener('click', ()=>{
      if (delBtn.disabled) return;
      const idx = state.items.findIndex(x=>x.id===it.id);
      if (idx>=0) {
        pushSnapshot();
        const rPrev = +state.items[idx].round||0;
        state.items.splice(idx,1);
        clearCollapsePref(it.id);
        renderGroupHeaderTotalsForRound(rPrev);
      }
      const mk = state.markersById.get(it.id);
      if (mk){ markerLayer.removeLayer(mk); state.markersById.delete(it.id); updatePinCount(); }
      saveAll();
      renderEverything();
    });

    updateRowHeaderMeta(row, it);
    state.rowsById.set(it.id, row);
    refreshDeleteButtonState(row, it);
    return row;
  }

  // ======= GYORS KERESŐ / SZŰRŐ
  function injectQuickSearch(){
    if (!feature('quick_search', true)) return;
    // csak egyszer
    if (document.getElementById('quickSearchWrap')) return;
    const wrap = document.createElement('div');
    wrap.id = 'quickSearchWrap';
    wrap.style.display = 'flex';
    wrap.style.gap = '6px';
    wrap.style.alignItems = 'center';
    wrap.style.margin = '8px 8px 4px 8px';

    const inp = document.createElement('input');
    inp.id = 'quickSearch';
    inp.type = 'search';
    inp.placeholder = text('quick_search.placeholder', 'Keresés…');
    inp.style.width = '100%';
    inp.style.padding = '6px 8px';
    inp.style.border = '1px solid #d1d5db';
    inp.style.borderRadius = '8px';
    inp.style.transition = 'border-color .2s ease, box-shadow .2s ease';
    inp.autocomplete = 'off';

    const clearBtn = document.createElement('button');
    clearBtn.textContent = text('quick_search.clear_label', '✕');
    clearBtn.title = text('quick_search.clear_title', 'Szűrés törlése');
    clearBtn.style.padding = '6px 10px';
    clearBtn.style.border = '1px solid #d1d5db';
    clearBtn.style.borderRadius = '8px';
    clearBtn.style.background = '#f3f4f6';
    clearBtn.style.cursor = 'pointer';

    wrap.appendChild(inp);
    wrap.appendChild(clearBtn);

    quickSearchClearBtn = clearBtn;
    applyQuickSearchClearStyles();

    // a groupsEl elé tesszük vagy a panel tetejére
    const parent = groupsEl.parentNode;
    if (panelTopEl) {
      panelTopEl.appendChild(wrap);
    } else {
      parent.insertBefore(wrap, groupsEl);
    }

    const status = document.createElement('div');
    status.id = 'quickSearchStatus';
    status.style.display = 'none';
    status.style.margin = '0 8px 8px 8px';
    status.style.padding = '6px 8px';
    status.style.borderRadius = '8px';
    status.style.border = '1px solid rgba(37,99,235,0.3)';
    status.style.background = 'rgba(37,99,235,0.08)';
    status.style.fontSize = '12px';
    status.style.fontWeight = '600';
    status.style.color = '#1d4ed8';
    status.setAttribute('role', 'status');
    if (panelTopEl) {
      panelTopEl.appendChild(status);
    } else {
      parent.insertBefore(status, groupsEl);
    }

    function updateIndicator(active, visibleCount){
      if (active){
        inp.style.borderColor = '#2563eb';
        inp.style.boxShadow = '0 0 0 2px rgba(37,99,235,0.2)';
        status.style.display = '';
        const tpl = text('quick_search.filtered_notice', 'Szűrt találatok: {count}');
        const emptyTpl = text('quick_search.filtered_empty', 'Nincs találat a megadott szűrőre.');
        status.textContent = visibleCount > 0 ? format(tpl, {count: visibleCount}) : emptyTpl;
      } else {
        inp.style.borderColor = '#d1d5db';
        inp.style.boxShadow = 'none';
        status.style.display = 'none';
        status.textContent = '';
      }
    }

    function applyFilter(){
      const q = (state.filterText || '').trim().toLowerCase();
      // sorok
      const rows = groupsEl.querySelectorAll('.row');
      rows.forEach(row=>{
        const id = row.dataset.rowId;
        const it = state.items.find(x=>x.id===id);
        if (!it){ row.style.display=''; return; }
        const parts = [];
        getFieldDefs().forEach(field => {
          const val = it[field.id];
          if (val != null) parts.push(String(val));
        });
        getMetricDefs().forEach(metric => {
          const val = it[metric.id];
          if (val != null) parts.push(String(val));
        });
        parts.push(it.city || '');
        const hay = parts.join(' ').toLowerCase();
        const match = !q || hay.includes(q);
        row.style.display = match ? '' : 'none';
      });
      // körök: ha egy körben nincs látható sor → rejt
      groupsEl.querySelectorAll('.group').forEach(g=>{
        const body = g.querySelector('.group-body');
        const anyVisible = Array.from(body.children).some(ch => ch.classList.contains('row') && ch.style.display!=='none');
        g.style.display = anyVisible ? '' : 'none';
      });
      const visibleCount = Array.from(groupsEl.querySelectorAll('.row')).filter(row => row.style.display !== 'none').length;
      updateIndicator(!!q, visibleCount);
    }

    inp.addEventListener('input', ()=>{
      state.filterText = inp.value;
      applyFilter();
    });
    clearBtn.addEventListener('click', ()=>{
      state.filterText = '';
      inp.value = '';
      applyFilter();
      inp.focus();
    });

    // első render után is alkalmazzuk, ha lenne mentett filter
    if (state.filterText) {
      inp.value = state.filterText;
      applyFilter();
    } else {
      updateIndicator(false, Array.from(groupsEl.querySelectorAll('.row')).length);
    }
  }

  function renderGroups(){
    state.rowsById.clear(); groupsEl.innerHTML = '';
    ensureBlankRowInDefaultRound(); autoSortItems();

    const orderNonZero = state.cfg.rounds.map(r=>Number(r.id)).filter(id=>id!==0).sort((a,b)=>a-b);
    ROUND_ORDER = state.cfg.app.round_zero_at_bottom ? [...orderNonZero, ...(ROUND_MAP.has(0)?[0]:[])] : state.cfg.rounds.map(r=>Number(r.id));

    const order = ROUND_ORDER.slice();
    const unknown = [...new Set(state.items.map(it=>+it.round||0))].filter(id=>!order.includes(id));
    unknown.forEach(id=>order.push(id));

    let globalIndex = 0;
    order.forEach(rid=>{
      const inRound = state.items.filter(it => (+it.round||0) === rid);
      if (rid !== 0 && inRound.length === 0) return;

      const totals = totalsForRound(rid);
      const groupEl = makeGroupHeader(rid, totals);
      const body = groupEl.querySelector(`[data-group-body="${rid}"]`);
      inRound.forEach(it => { body.appendChild(renderRow(it, globalIndex)); globalIndex++; });

      const btnOpen = groupEl.querySelector('.grp-open');
      if (btnOpen) btnOpen.addEventListener('click', ()=>{
        body.querySelectorAll('.row').forEach(rowEl => {
          const bodyEl = rowEl.querySelector('.body');
          const toggle = rowEl.querySelector('.toggle');
          if (bodyEl) bodyEl.style.display = '';
          if (toggle instanceof HTMLButtonElement && !toggle.disabled) toggle.textContent = '▼';
        });
        state.items
          .filter(item => (+item.round||0)===rid && !isItemCompletelyBlank(item))
          .forEach(item => setCollapsePref(item.id, false));
      });
      const btnClose = groupEl.querySelector('.grp-close');
      if (btnClose) btnClose.addEventListener('click', ()=>{
        body.querySelectorAll('.row').forEach(rowEl => {
          const bodyEl = rowEl.querySelector('.body');
          const toggle = rowEl.querySelector('.toggle');
          const isPlaceholderRow = rowEl.dataset.placeholder === 'true';
          if (isPlaceholderRow) {
            if (bodyEl) bodyEl.style.display = '';
            if (toggle instanceof HTMLButtonElement) toggle.textContent = '▼';
            return;
          }
          if (bodyEl) bodyEl.style.display = 'none';
          if (toggle instanceof HTMLButtonElement && !toggle.disabled) toggle.textContent = '▶';
        });
        state.items
          .filter(item => (+item.round||0)===rid && !isItemCompletelyBlank(item))
          .forEach(item => setCollapsePref(item.id, true));
      });
      const btnPrint = groupEl.querySelector('.grp-print');
      if (btnPrint) btnPrint.addEventListener('click', ()=>{ window.open(EP.printRound(rid), '_blank'); });

      const btnDelete = groupEl.querySelector('.grp-del');
      if (btnDelete) btnDelete.addEventListener('click', async ()=>{
        const name = (ROUND_MAP.get(rid)?.label) || String(rid);
        const msgTpl = cfg('text.messages.delete_round_confirm', 'Biztosan törlöd a(z) "{name}" kör összes címét?');
        if (!confirm(format(msgTpl, {name}))) return;
        try{
          pushSnapshot();
          const removedIds = state.items.filter(it => (+it.round||0)===rid).map(it=>it.id);
          const r = await fetch(EP.deleteRound, {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({round:rid})
          });
          const t = await r.text();
          let ok=false, j=null;
          try { j = JSON.parse(t); ok = !!j.ok; }
          catch(_) { ok = r.ok; }
          if (!ok) throw new Error('delete_failed');

          state.items = state.items.filter(it => (+it.round||0)!==rid);
          clearRoundMeta(rid);
          removedIds.forEach(id=>{
            const mk = state.markersById.get(id);
            if (mk){ markerLayer.removeLayer(mk); state.markersById.delete(id); }
          });

          ensureBlankRowInDefaultRound();
          await saveAll();
          renderEverything();
          const successTpl = cfg('text.messages.delete_round_success', 'Kör törölve. Tételek: {count}.');
          alert(format(successTpl, {count: j?.deleted ?? removedIds.length}));
        }catch(e){ console.error(e); alert(cfg('text.messages.delete_round_error', 'A kör törlése nem sikerült.')); }
      });

      const btnNav = groupEl.querySelector('.grp-nav');
      if (btnNav) btnNav.addEventListener('click', ()=>{ openGmapsForRound(rid); });

      const plannedDateInput = groupEl.querySelector('.planned-date-input');
      if (plannedDateInput) {
        plannedDateInput.addEventListener('change', async ()=>{
          const newVal = plannedDateInput.value.trim();
          const prevVal = getPlannedDateForRound(rid);
          if (newVal === prevVal) return;
          pushSnapshot();
          setPlannedDateForRound(rid, newVal);
          const ok = await saveAll();
          if (ok) {
            plannedDateInput.value = getPlannedDateForRound(rid);
            flashSaved(plannedDateInput);
          }
        });
      }

      groupsEl.appendChild(groupEl);
    });

    renumberAll();
    state.items.forEach((it,idx)=>{ if (it.lat!=null&&it.lon!=null) upsertMarker(it, idx); });
    refreshDeleteButtonsAll();

    // gyors kereső injektálása (ha még nincs) és azonnali alkalmazása
    injectQuickSearch();
    if (state.filterText) {
      // alkalmazzuk az aktív szűrést a friss renderre
      const inp = document.getElementById('quickSearch');
      if (inp) inp.dispatchEvent(new Event('input', {bubbles:true}));
    }
  }

  function openGmapsForRound(rid){
    const origin = cfg('routing.origin', 'Maglód');
    const maxW = cfg('routing.max_waypoints', 10) || 10;
    const pts = state.items.filter(it => (+it.round||0)===rid && it.lat!=null && it.lon!=null);
    if (pts.length===0){ alert(cfg('text.messages.navigation_empty', 'Nincs navigálható cím ebben a körben.')); return; }
    const batches = [];
    for (let i=0;i<pts.length;i+=maxW){ batches.push(pts.slice(i, i+maxW)); }
    batches.forEach((batch)=>{
      const last = batch[batch.length-1];
      const wp = batch.slice(0,-1).map(p => `${p.lat},${p.lon}`).join('|');
      const url = new URL('https://www.google.com/maps/dir/');
      url.searchParams.set('api','1');
      url.searchParams.set('origin', origin);
      url.searchParams.set('destination', `${last.lat},${last.lon}`);
      if (wp) url.searchParams.set('waypoints', wp);
      url.searchParams.set('travelmode','driving');
      window.open(url.toString(), '_blank');
    });
    const skipped = state.items.filter(it => (+it.round||0)===rid && (it.lat==null || it.lon==null)).length;
    if (skipped>0) {
      const warnTpl = cfg('text.messages.navigation_skip', 'Figyelem: {count} cím nem került bele (nincs geolokáció).');
      alert(format(warnTpl, {count: skipped}));
    }
  }

  // ======= GLOBAL BUTTONS
  function showImportModeDialog(message, {replaceLabel, appendLabel} = {}){
    return new Promise((resolve)=>{
      const overlay = document.createElement('div');
      overlay.style.position = 'fixed';
      overlay.style.inset = '0';
      overlay.style.background = 'rgba(15,23,42,0.45)';
      overlay.style.backdropFilter = 'blur(2px)';
      overlay.style.display = 'flex';
      overlay.style.alignItems = 'center';
      overlay.style.justifyContent = 'center';
      overlay.style.zIndex = '10000';

      const dark = document.documentElement.classList.contains('dark');
      const box = document.createElement('div');
      box.style.color = dark ? '#e5e7eb' : '#111827';
      box.style.padding = '24px';
      box.style.borderRadius = '12px';
      box.style.boxShadow = '0 12px 32px rgba(15,23,42,0.35)';
      box.style.maxWidth = '420px';
      box.style.width = 'calc(100% - 32px)';
      box.style.fontSize = '16px';
      box.style.lineHeight = '1.5';
      box.style.background = dark ? '#111827' : '#ffffff';

      const textEl = document.createElement('div');
      textEl.textContent = message || '';
      textEl.style.marginBottom = '20px';

      const btnWrap = document.createElement('div');
      btnWrap.style.display = 'flex';
      btnWrap.style.gap = '12px';
      btnWrap.style.justifyContent = 'flex-end';
      btnWrap.style.flexWrap = 'wrap';

      function makeBtn(label, variant){
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = label;
        btn.style.border = 'none';
        btn.style.padding = '8px 16px';
        btn.style.borderRadius = '8px';
        btn.style.cursor = 'pointer';
        btn.style.fontSize = '15px';
        btn.style.fontWeight = '600';
        if (variant === 'primary') {
          btn.style.background = '#2563eb';
          btn.style.color = '#ffffff';
        } else if (variant === 'ghost') {
          btn.style.background = 'transparent';
          btn.style.color = dark ? '#e5e7eb' : '#111827';
          btn.style.border = '1px solid rgba(148, 163, 184, 0.5)';
        } else {
          btn.style.background = dark ? '#1f2937' : '#f3f4f6';
          btn.style.color = dark ? '#f8fafc' : '#111827';
        }
        btn.addEventListener('mouseenter', ()=>{ btn.style.filter = 'brightness(0.95)'; });
        btn.addEventListener('mouseleave', ()=>{ btn.style.filter = ''; });
        return btn;
      }

      let finished = false;
      const handleResult = (result)=>{
        if (finished) return;
        finished = true;
        cleanup();
        resolve(result);
      };

      const replaceBtn = makeBtn(replaceLabel || 'Felülírás', 'primary');
      replaceBtn.addEventListener('click', ()=> handleResult('replace'));

      const appendBtn = makeBtn(appendLabel || 'Hozzáadás');
      appendBtn.addEventListener('click', ()=> handleResult('append'));

      btnWrap.appendChild(replaceBtn);
      btnWrap.appendChild(appendBtn);

      box.appendChild(textEl);
      box.appendChild(btnWrap);
      overlay.appendChild(box);
      document.body.appendChild(overlay);

      const onKey = (event)=>{
        if (event.key === 'Escape') {
          event.preventDefault();
          handleResult(null);
        }
      };
      document.addEventListener('keydown', onKey);

      overlay.addEventListener('click', (event)=>{
        if (event.target === overlay) {
          handleResult(null);
        }
      });

      setTimeout(()=>{ replaceBtn.focus(); }, 0);

      function cleanup(){
        overlay.remove();
        document.removeEventListener('keydown', onKey);
      }
    });
  }

  const importBtn = document.getElementById('importBtn');
  const importInput = document.getElementById('importFileInput');
  if (importBtn && importInput) {
    importBtn.addEventListener('click', ()=>{ if (!importBtn.disabled) importInput.click(); });
    importInput.addEventListener('change', async ()=>{
      const file = importInput.files && importInput.files[0];
      if (!file) return;
      const primaryPrompt = text('messages.import_mode_prompt', 'Felülírjuk a jelenlegi adatokat az importált CSV-vel, vagy hozzáadjuk az új sorokat?');
      const mode = await showImportModeDialog(primaryPrompt, {
        replaceLabel: text('messages.import_mode_replace', 'Felülírás'),
        appendLabel: text('messages.import_mode_append', 'Hozzáadás')
      });
      if (!mode) {
        importInput.value = '';
        return;
      }
      const replaceSelected = mode === 'replace';
      const confirmPrompt = replaceSelected
        ? text('messages.import_mode_confirm_replace', 'Biztosan felülírjuk a jelenlegi adatokat a CSV tartalmával?')
        : text('messages.import_mode_confirm_append', 'Biztosan hozzáadjuk az új sorokat a meglévő listához?');
      const proceed = window.confirm(confirmPrompt);
      if (!proceed) {
        importInput.value = '';
        return;
      }
      importBtn.disabled = true;
      try {
        const form = new FormData();
        form.append('file', file);
        form.append('mode', mode);
        const resp = await fetch(EP.importCsv, {method:'POST', body: form});
        const raw = await resp.text();
        let data;
        try { data = JSON.parse(raw); }
        catch(_) { throw Object.assign(new Error('bad_json'), {raw}); }
        if (!resp.ok || !data || data.ok !== true) {
          const detail = (data && typeof data.error === 'string') ? data.error : null;
          const err = new Error('import_failed');
          if (detail) err.detail = detail;
          throw err;
        }
        const importedIds = Array.isArray(data.imported_ids) ? data.imported_ids : null;
        applyLoadedData(data);
        history.length = 0;
        const geo = await autoGeocodeImported(importedIds);
        renderEverything();
        if (!geo.changed) {
          showSaveStatus(true);
        } else if (!geo.saveOk) {
          showSaveStatus(false);
        }
        let successMsg = text('messages.import_success', 'Import kész.');
        if (geo.failed > 0 && geo.attempted > 0) {
          const tpl = text('messages.import_geocode_partial', 'Figyelem: {count} címet nem sikerült automatikusan térképre tenni.');
          successMsg += `\n\n${format(tpl, {count: geo.failed})}`;
          if (Array.isArray(geo.failures) && geo.failures.length) {
            const detailTpl = text('messages.import_geocode_partial_detail', 'Nem sikerült geokódolni:\n{list}');
            const list = geo.failures.map((entry, idx)=> `${idx + 1}. ${entry}`).join('\n');
            successMsg += `\n${format(detailTpl, {list})}`;
          }
        }
        alert(successMsg);
      } catch (e) {
        console.error(e);
        showSaveStatus(false);
        let msg = text('messages.import_error', 'Az importálás nem sikerült.');
        const extra = e && (e.detail || e.message);
        if (extra && typeof extra === 'string' && extra.trim() && !['import_failed','bad_json'].includes(extra)) {
          msg += `\n\n${extra.trim()}`;
        }
        alert(msg);
      } finally {
        importInput.value = '';
        importBtn.disabled = false;
      }
    });
  }
  const exportBtn = document.getElementById('exportBtn');
  if (exportBtn) exportBtn.addEventListener('click', ()=>{ window.open(EP.exportAll, '_blank'); });
  const printBtn = document.getElementById('printBtn');
  if (printBtn) printBtn.addEventListener('click', ()=>{ window.open(EP.printAll, '_blank'); });
  const archiveBtn = document.getElementById('downloadArchiveBtn');
  if (archiveBtn) archiveBtn.addEventListener('click', ()=>{ window.open(EP.downloadArchive, '_blank'); });
  const expandAllBtn = document.getElementById('expandAll');
  if (expandAllBtn) expandAllBtn.addEventListener('click', ()=>{
    groupsEl.querySelectorAll('.row').forEach(rowEl => {
      const body = rowEl.querySelector('.body');
      const toggle = rowEl.querySelector('.toggle');
      if (body) body.style.display = '';
      if (toggle instanceof HTMLButtonElement && !toggle.disabled) toggle.textContent = '▼';
    });
    state.items.filter(it => !isItemCompletelyBlank(it)).forEach(it => setCollapsePref(it.id, false));
  });
  const collapseAllBtn = document.getElementById('collapseAll');
  if (collapseAllBtn) collapseAllBtn.addEventListener('click', ()=>{
    groupsEl.querySelectorAll('.row').forEach(rowEl => {
      const body = rowEl.querySelector('.body');
      const toggle = rowEl.querySelector('.toggle');
      const isPlaceholderRow = rowEl.dataset.placeholder === 'true';
      if (isPlaceholderRow){
        if (body) body.style.display = '';
        if (toggle instanceof HTMLButtonElement) toggle.textContent = '▼';
        return;
      }
      if (body) body.style.display = 'none';
      if (toggle instanceof HTMLButtonElement && !toggle.disabled) toggle.textContent = '▶';
    });
    state.items.filter(it => !isItemCompletelyBlank(it)).forEach(it => setCollapsePref(it.id, true));
  });

  function renderEverything(){
    autoSortItems();
    renderGroups();
    state.items.forEach((it,idx)=>{ if (it.lat!=null&&it.lon!=null) upsertMarker(it, idx); });
    const b = markerLayer.getBounds(); if (b.isValid()) map.fitBounds(b.pad(0.2));
    updateUndoButton();
  }

  (async function start(){
    try{
      loadCollapsePrefs();
      await loadCfg();
      applyThemeVariables();
      applyPanelSizes();
      applyPanelSticky();
      updateUndoButton();

      // tile layer
      L.tileLayer(state.cfg.map.tiles.url,{maxZoom:19, attribution:state.cfg.map.tiles.attribution}).addTo(map);
      if (state.cfg.map.fit_bounds) {
        const b = L.latLngBounds(state.cfg.map.fit_bounds);
        map.fitBounds(b.pad(0.15));
        const pad = cfg('map.max_bounds_pad', 0.6) || 0.6;
        map.setMaxBounds(b.pad(pad));
        map.on('drag', ()=> map.panInsideBounds(map.options.maxBounds,{animate:false}));
      }

      // rounds
      ROUND_MAP = new Map(state.cfg.rounds.map(r=>[Number(r.id), r]));

      // origin geocode cache
      const originCoords = cfg('routing.origin_coordinates', null);
      if (originCoords && Number.isFinite(originCoords.lat) && Number.isFinite(originCoords.lon)) {
        state.ORIGIN = {lat: Number(originCoords.lat), lon: Number(originCoords.lon)};
      }
      if (cfg('routing.geocode_origin_on_start', true)) {
        const originName = cfg('routing.origin', 'Maglód');
        try{
          const r = await fetch(EP.geocode + '&' + new URLSearchParams({q:originName}), {cache:'force-cache'});
          if (r.ok){
            const j = await r.json();
            if (j && j.lat && j.lon){ state.ORIGIN = {lat:j.lat, lon:j.lon}; }
          }
        }catch(e){}
      }

      // load data
      await loadAll();

      // mindig legyen üres sor a 0-s körben
      if (!Array.isArray(state.items) || state.items.length===0){
        state.items = [];
      }
      ensureBlankRowInDefaultRound();

      renderEverything();
    }catch(e){
      console.error(e);
      alert(text('messages.load_error', 'Betöltési hiba: kérlek frissítsd az oldalt.'));
    }
  })();

})();
