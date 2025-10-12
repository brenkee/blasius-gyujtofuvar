/* global L */
(function(){
  const EP = window.APP_BOOTSTRAP?.endpoints || {};
  const CSRF_TOKEN = window.APP_BOOTSTRAP?.csrfToken || null;
  const state = {
    cfg: null,
    items: [],                 // {id,label,address,city,note,lat,lon,round,weight,volume,_pendingRound}
    roundMeta: {},             // { [roundId]: {planned_date:string, planned_time:string, sort_mode:string, custom_order:string[]} }
    markersById: new Map(),
    rowsById: new Map(),
    ORIGIN: {lat:47.4500, lon:19.3500}, // Maglód tartalék
    filterText: '',
    clientId: null,
    baselineRevision: 0,
    latestRevision: 0,
    changeWatcher: null,
    foreignRevisionSet: new Set(),
    activeEditor: null,
    conflictNotified: new Set(),
    conflictOverlay: null,
    markerOverlapCounts: new Map(),
    displayIndexById: new Map(),
    user: window.APP_BOOTSTRAP?.user || null,
    csrfToken: CSRF_TOKEN
  };

  const history = [];
  let importRollbackSnapshot = null;
  const undoBtn = document.getElementById('undoBtn');

  const newAddressEl = document.getElementById('newAddress');
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

  function makeRequestId(){
    if (window.crypto?.randomUUID) {
      return 'req_' + window.crypto.randomUUID().replace(/-/g, '');
    }
    return 'req_' + Math.random().toString(36).slice(2, 14);
  }

  function makeBatchId(){
    if (window.crypto?.randomUUID) {
      return 'batch_' + window.crypto.randomUUID().replace(/-/g, '');
    }
    return 'batch_' + Math.random().toString(36).slice(2, 14);
  }

  function buildHeaders(raw){
    const headers = new Headers();
    if (raw instanceof Headers) {
      raw.forEach((value, key)=>{ headers.set(key, value); });
    } else if (raw && typeof raw === 'object') {
      Object.entries(raw).forEach(([key, value])=>{
        if (value == null) return;
        headers.set(key, value);
      });
    }
    if (state.clientId) {
      headers.set('X-Client-ID', state.clientId);
    }
    if (CSRF_TOKEN && !headers.has('X-CSRF-Token')) {
      headers.set('X-CSRF-Token', CSRF_TOKEN);
    }
    return headers;
  }

  function updateKnownRevision(rev){
    if (!Number.isFinite(rev)) return;
    const num = Number(rev);
    if (num > state.latestRevision) {
      state.latestRevision = num;
    }
    if (num > state.baselineRevision) {
      state.baselineRevision = num;
    }
    if (state.changeWatcher) {
      state.changeWatcher.setBaseline(num);
    }
  }

  function registerForeignRevision(rev){
    if (!Number.isFinite(rev)) return false;
    const num = Number(rev);
    if (state.foreignRevisionSet.has(num)) return false;
    state.foreignRevisionSet.add(num);
    return true;
  }

  function resetForeignRevisions(){
    state.foreignRevisionSet.clear();
  }

  class ChangeWatcher {
    constructor(opts){
      this.clientId = opts?.clientId || null;
      this.changesUrl = opts?.changesUrl || '';
      this.revisionUrl = opts?.revisionUrl || '';
      this.onForeignChange = typeof opts?.onForeignChange === 'function' ? opts.onForeignChange : ()=>{};
      this.since = Number(opts?.baselineRev || 0);
      this.batchIds = new Set();
      this.active = false;
      this.pollingPromise = null;
      this.visibilityListener = this.handleVisibilityChange.bind(this);
      this.revisionTimer = null;
    }

    setBaseline(rev){
      if (Number.isFinite(rev) && Number(rev) > this.since) {
        this.since = Number(rev);
      }
    }

    registerBatch(batchId){
      if (batchId) this.batchIds.add(batchId);
    }

    unregisterBatch(batchId){
      if (batchId) this.batchIds.delete(batchId);
    }

    start(){
      if (this.active) return;
      this.active = true;
      document.addEventListener('visibilitychange', this.visibilityListener);
      this.loop();
      this.revisionTimer = setInterval(()=> this.checkRevision(), 12000);
    }

    stop(){
      if (!this.active) return;
      this.active = false;
      document.removeEventListener('visibilitychange', this.visibilityListener);
      if (this.revisionTimer) clearInterval(this.revisionTimer);
      this.revisionTimer = null;
    }

    handleVisibilityChange(){
      if (!this.active) return;
      if (this.isVisible() && !this.pollingPromise) {
        this.loop();
      }
    }

    isVisible(){
      return document.visibilityState !== 'hidden';
    }

    loop(){
      if (!this.active) return;
      if (this.pollingPromise) return;
      this.pollingPromise = (async ()=>{
        while (this.active) {
          if (!this.isVisible()) {
            await this.waitUntilVisible();
            if (!this.active) break;
          }
          try {
            await this.poll();
          } catch (err) {
            console.warn('változásfigyelés hiba', err);
            await this.delay(1200);
          }
        }
        this.pollingPromise = null;
      })();
    }

    async poll(){
      const params = new URLSearchParams({since: String(this.since)});
      if (this.clientId) params.set('exclude_actor', this.clientId);
      if (this.batchIds.size) params.set('exclude_batch', Array.from(this.batchIds).join(','));
      const url = this.buildUrl(this.changesUrl, params.toString());
      const resp = await fetch(url, {cache:'no-store', headers: buildHeaders()});
      if (resp.status === 204) {
        return;
      }
      if (!resp.ok) {
        throw new Error('changes_fetch_failed');
      }
      const data = await resp.json();
      const events = Array.isArray(data.events) ? data.events : [];
      const latest = Number.isFinite(Number(data.latest_rev)) ? Number(data.latest_rev) : this.since;
      if (latest > this.since) {
        this.since = latest;
      }
      if (events.length) {
        const foreign = events.filter(ev => !ev.actor_id || ev.actor_id !== this.clientId);
        if (foreign.length) {
          this.onForeignChange(foreign, latest || this.since);
        }
      }
    }

    async checkRevision(){
      if (!this.active) return;
      try {
        const resp = await fetch(this.revisionUrl, {cache:'no-store', headers: buildHeaders()});
        if (!resp.ok) return;
        const data = await resp.json();
        const rev = Number(data.rev);
        if (Number.isFinite(rev) && rev > this.since) {
          this.since = rev;
          this.onForeignChange([], rev, {fromRevisionPing: true});
        }
      } catch (err) {
        console.warn('revision lekérdezés hiba', err);
      }
    }

    buildUrl(base, qs){
      if (!qs) return base;
      return base + (base.includes('?') ? '&' : '?') + qs;
    }

    waitUntilVisible(){
      if (this.isVisible()) return Promise.resolve();
      return new Promise(resolve => {
        const handler = ()=>{
          if (this.isVisible()) {
            document.removeEventListener('visibilitychange', handler);
            resolve();
          }
        };
        document.addEventListener('visibilitychange', handler);
      });
    }

    delay(ms){
      return new Promise(resolve => setTimeout(resolve, ms));
    }
  }

  const changeNotice = (function(){
    const wrap = document.createElement('div');
    wrap.id = 'changeNoticeBanner';
    wrap.className = 'change-notice change-notice--hidden';
    wrap.setAttribute('role', 'status');
    wrap.setAttribute('aria-live', 'polite');
    const msg = document.createElement('span');
    msg.className = 'change-notice__message';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'change-notice__button';
    btn.textContent = 'Frissítés';
    btn.addEventListener('click', ()=>{
      window.location.reload();
    });
    wrap.appendChild(msg);
    wrap.appendChild(btn);
    document.body.appendChild(wrap);
    return {
      show(count){
        const tpl = count > 1 ? `Közben ${count} módosítás történt` : 'Közben változás történt';
        msg.textContent = `${tpl} – kérlek frissíts!`;
        wrap.classList.remove('change-notice--hidden');
      },
      hide(){
        wrap.classList.add('change-notice--hidden');
      }
    };
  })();

  async function ensureClientId(){
    if (state.clientId) return state.clientId;
    const resp = await fetchJSON(EP.session);
    if (resp && resp.ok && typeof resp.client_id === 'string') {
      state.clientId = resp.client_id;
      return state.clientId;
    }
    if (resp && typeof resp.client_id === 'string') {
      state.clientId = resp.client_id;
      return state.clientId;
    }
    throw new Error('client_id_missing');
  }

  function initChangeWatcher(){
    if (!state.clientId) return;
    const opts = {
      clientId: state.clientId,
      changesUrl: EP.changes,
      revisionUrl: EP.revision,
      baselineRev: state.baselineRevision,
      onForeignChange: handleForeignChange
    };
    if (!state.changeWatcher) {
      state.changeWatcher = new ChangeWatcher(opts);
      state.changeWatcher.start();
    } else {
      state.changeWatcher.clientId = state.clientId;
      state.changeWatcher.setBaseline(state.baselineRevision);
    }
  }

  function closeConflictOverlay(){
    if (state.conflictOverlay) {
      state.conflictOverlay.remove();
      state.conflictOverlay = null;
    }
  }

  function fieldLabel(fieldId){
    const fields = getFieldDefs();
    const foundField = fields.find(f => f?.id === fieldId);
    if (foundField && foundField.label) return foundField.label;
    const metrics = getMetricDefs();
    const foundMetric = metrics.find(m => m?.id === fieldId);
    if (foundMetric && foundMetric.label) return foundMetric.label;
    const fallback = {
      round: cfg('items.round_field.label', 'Kör'),
      city: 'Város',
      note: 'Megjegyzés',
      address: 'Cím',
      label: 'Címke'
    };
    return fallback[fieldId] || fieldId;
  }

  function formatValueForDiff(value){
    if (value == null) return '—';
    if (typeof value === 'number') return String(value);
    if (value === false) return 'nem';
    if (value === true) return 'igen';
    if (value instanceof Date) return value.toISOString();
    if (typeof value === 'object') {
      try { return JSON.stringify(value); }
      catch(_) { return String(value); }
    }
    return String(value);
  }

  function computeConflictDiff(original, remote, local){
    const before = original || {};
    const after = remote || {};
    const keys = new Set([...Object.keys(before), ...Object.keys(after)]);
    const diffs = [];
    keys.forEach(key => {
      if (key === 'id' || key === '_pendingRound' || key === 'collapsed') return;
      const prevVal = before[key];
      const newVal = after[key];
      const prevJson = JSON.stringify(prevVal);
      const newJson = JSON.stringify(newVal);
      if (prevJson === newJson) return;
      const entry = {
        field: key,
        label: fieldLabel(key),
        before: formatValueForDiff(prevVal),
        after: formatValueForDiff(newVal),
        local: local ? formatValueForDiff(local[key]) : '—'
      };
      diffs.push(entry);
    });
    return diffs;
  }

  function showConflictDialog(itemId, original, remote, local, options={}){
    closeConflictOverlay();
    const overlay = document.createElement('div');
    overlay.className = 'conflict-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'conflictDialogTitle');

    const box = document.createElement('div');
    box.className = 'conflict-dialog';

    const title = document.createElement('h2');
    title.id = 'conflictDialogTitle';
    title.textContent = 'Konfliktus észlelve';
    box.appendChild(title);

    const intro = document.createElement('p');
    if (remote == null) {
      intro.textContent = 'A tételt időközben törölték vagy másik körbe helyezték.';
    } else if (options?.loadFailed) {
      intro.textContent = 'A frissített adatok lekérése nem sikerült, de másik felhasználó módosította a tételt.';
    } else {
      intro.textContent = 'Miközben szerkesztetted, egy másik felhasználó módosította ezt a tételt.';
    }
    box.appendChild(intro);

    const diff = computeConflictDiff(original, remote, local);
    if (diff.length) {
      const list = document.createElement('ul');
      list.className = 'conflict-diff';
      diff.forEach(entry => {
        const li = document.createElement('li');
        li.innerHTML = `<strong>${entry.label}:</strong> <span class="conflict-before">${entry.before}</span> → <span class="conflict-after">${entry.after}</span> <span class="conflict-local">(helyi: ${entry.local})</span>`;
        list.appendChild(li);
      });
      box.appendChild(list);
    }

    const actions = document.createElement('div');
    actions.className = 'conflict-actions';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.textContent = 'Bezár';
    closeBtn.addEventListener('click', ()=>{
      closeConflictOverlay();
    });

    const reloadBtn = document.createElement('button');
    reloadBtn.type = 'button';
    reloadBtn.className = 'primary';
    reloadBtn.textContent = 'Frissítés';
    reloadBtn.addEventListener('click', ()=>{
      window.location.reload();
    });

    actions.appendChild(reloadBtn);
    actions.appendChild(closeBtn);
    box.appendChild(actions);
    overlay.appendChild(box);
    overlay.addEventListener('click', (event)=>{
      if (event.target === overlay) {
        closeConflictOverlay();
      }
    });
    document.addEventListener('keydown', function escHandler(ev){
      if (ev.key === 'Escape') {
        document.removeEventListener('keydown', escHandler);
        closeConflictOverlay();
      }
    }, {once:true});
    document.body.appendChild(overlay);
    state.conflictOverlay = overlay;
  }

  async function triggerConflictForItem(itemId){
    if (!itemId) return;
    if (state.conflictNotified.has(itemId)) return;
    state.conflictNotified.add(itemId);
    try {
      const payload = await fetchJSON(EP.load, {cache:'no-store'});
      const remoteItems = Array.isArray(payload.items) ? payload.items : [];
      const remote = remoteItems.find(it => it && it.id === itemId) || null;
      const local = state.items.find(it => it && it.id === itemId) || null;
      const original = state.activeEditor?.snapshot || null;
      showConflictDialog(itemId, original, remote, local);
    } catch (err) {
      console.error('Konfliktus frissítés hiba', err);
      const local = state.items.find(it => it && it.id === itemId) || null;
      showConflictDialog(itemId, state.activeEditor?.snapshot || null, null, local, {loadFailed: true});
    }
  }

  function handleForeignChange(events, latestRev, opts={}){
    if (Number.isFinite(latestRev)) {
      state.latestRevision = Math.max(state.latestRevision, Number(latestRev));
    }
    let anyNew = false;
    if (Array.isArray(events)) {
      events.forEach(ev => {
        if (Number.isFinite(ev?.rev)) {
          if (registerForeignRevision(Number(ev.rev))) {
            anyNew = true;
          }
        }
      });
    }
    if (!anyNew && Number.isFinite(latestRev)) {
      if (registerForeignRevision(Number(latestRev))) {
        anyNew = true;
      }
    }
    if (anyNew) {
      changeNotice.show(state.foreignRevisionSet.size);
    }
    if (Array.isArray(events) && state.activeEditor) {
      const affected = events.some(ev => ev?.entity === 'item' && ev.entity_id === state.activeEditor.id);
      if (affected) {
        triggerConflictForItem(state.activeEditor.id);
      }
    }
  }

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

  function ensureRoundMetaContainer(){
    if (!state.roundMeta || typeof state.roundMeta !== 'object') {
      state.roundMeta = {};
    }
  }

  function getRoundMetaEntry(rid){
    const entry = state.roundMeta?.[roundMetaKey(rid)];
    return (entry && typeof entry === 'object') ? entry : null;
  }

  function ensureRoundMetaEntry(rid){
    ensureRoundMetaContainer();
    const key = roundMetaKey(rid);
    if (!state.roundMeta[key] || typeof state.roundMeta[key] !== 'object') {
      state.roundMeta[key] = {};
    }
    return state.roundMeta[key];
  }

  function cleanupRoundMetaEntry(rid){
    const key = roundMetaKey(rid);
    if (!state.roundMeta || typeof state.roundMeta !== 'object') return;
    const entry = state.roundMeta[key];
    if (!entry || typeof entry !== 'object') return;
    if (Object.keys(entry).length === 0) {
      delete state.roundMeta[key];
    }
  }

  function sanitizeRoundMetaEntry(entryRaw){
    if (!entryRaw || typeof entryRaw !== 'object') return null;
    const sanitized = {};
    const dateRaw = typeof entryRaw.planned_date === 'string' ? entryRaw.planned_date.trim() : '';
    if (dateRaw) {
      sanitized.planned_date = dateRaw.slice(0, 120);
    }
    const timeRaw = typeof entryRaw.planned_time === 'string' ? entryRaw.planned_time.trim() : '';
    if (timeRaw) {
      sanitized.planned_time = timeRaw.slice(0, 40);
    }
    let customOrderSource = entryRaw.custom_order;
    if (!Array.isArray(customOrderSource) && typeof customOrderSource === 'string' && customOrderSource.trim() !== '') {
      try {
        const parsed = JSON.parse(customOrderSource);
        if (Array.isArray(parsed)) customOrderSource = parsed;
      } catch (_) {
        customOrderSource = [];
      }
    }
    const order = [];
    if (Array.isArray(customOrderSource)) {
      const seen = new Set();
      customOrderSource.forEach(val => {
        if (val == null) return;
        const str = String(val).trim();
        if (!str || seen.has(str)) return;
        seen.add(str);
        order.push(str);
      });
      if (order.length) {
        sanitized.custom_order = order;
      }
    }
    let modeRaw = typeof entryRaw.sort_mode === 'string' ? entryRaw.sort_mode.trim().toLowerCase() : '';
    if (modeRaw !== 'custom' && modeRaw !== 'default') {
      modeRaw = order.length ? 'custom' : '';
    }
    sanitized.sort_mode = modeRaw || 'default';
    return Object.keys(sanitized).length ? sanitized : null;
  }

  function applyRoundMeta(metaObj){
    state.roundMeta = {};
    if (!metaObj || typeof metaObj !== 'object' || Array.isArray(metaObj)) return;
    Object.entries(metaObj).forEach(([rid, entry]) => {
      const normalized = sanitizeRoundMetaEntry(entry);
      if (!normalized) return;
      state.roundMeta[String(rid)] = normalized;
    });
  }

  function getPlannedDateForRound(rid){
    const entry = getRoundMetaEntry(rid);
    if (entry && typeof entry.planned_date === 'string') {
      return entry.planned_date;
    }
    return '';
  }

  function setPlannedDateForRound(rid, value){
    const entry = ensureRoundMetaEntry(rid);
    const val = (value || '').trim();
    const limited = val.length > 120 ? val.slice(0, 120) : val;
    if (limited) {
      entry.planned_date = limited;
    } else {
      delete entry.planned_date;
      cleanupRoundMetaEntry(rid);
    }
  }

  function getPlannedTimeForRound(rid){
    const entry = getRoundMetaEntry(rid);
    if (entry && typeof entry.planned_time === 'string') {
      return entry.planned_time;
    }
    return '';
  }

  function setPlannedTimeForRound(rid, value){
    const entry = ensureRoundMetaEntry(rid);
    const val = (value || '').trim();
    const limited = val.length > 40 ? val.slice(0, 40) : val;
    if (limited) {
      entry.planned_time = limited;
    } else {
      delete entry.planned_time;
      cleanupRoundMetaEntry(rid);
    }
  }

  function getRoundSortMode(rid){
    const entry = getRoundMetaEntry(rid);
    if (!entry || typeof entry.sort_mode !== 'string') return 'default';
    return entry.sort_mode === 'custom' ? 'custom' : 'default';
  }

  function setRoundSortMode(rid, mode){
    const entry = ensureRoundMetaEntry(rid);
    entry.sort_mode = mode === 'custom' ? 'custom' : 'default';
  }

  function sanitizeCustomOrderList(order){
    const out = [];
    if (!Array.isArray(order)) return out;
    const seen = new Set();
    order.forEach(val => {
      if (val == null) return;
      const str = String(val).trim();
      if (!str || seen.has(str)) return;
      seen.add(str);
      out.push(str);
    });
    return out;
  }

  function getRoundCustomOrder(rid){
    const entry = getRoundMetaEntry(rid);
    if (!entry || !Array.isArray(entry.custom_order)) return [];
    return sanitizeCustomOrderList(entry.custom_order);
  }

  function setRoundCustomOrder(rid, order){
    const entry = ensureRoundMetaEntry(rid);
    const sanitized = sanitizeCustomOrderList(order);
    if (sanitized.length) {
      entry.custom_order = sanitized;
    } else {
      delete entry.custom_order;
      cleanupRoundMetaEntry(rid);
    }
  }

  function syncCustomOrderWithItems(rid, itemIds){
    const ids = Array.isArray(itemIds) ? itemIds.map(id => String(id)).filter(id => id !== '') : [];
    const existing = getRoundCustomOrder(rid);
    const filtered = existing.filter(id => ids.includes(id));
    const missing = ids.filter(id => !filtered.includes(id));
    const combined = [...filtered, ...missing];
    if (combined.length) {
      setRoundCustomOrder(rid, combined);
    } else {
      setRoundCustomOrder(rid, []);
    }
    return combined;
  }

  function removeItemFromCustomOrder(rid, itemId){
    if (!itemId) return;
    const entry = getRoundMetaEntry(rid);
    if (!entry || !Array.isArray(entry.custom_order)) return;
    const next = entry.custom_order.filter(id => String(id) !== String(itemId));
    if (next.length !== entry.custom_order.length) {
      setRoundCustomOrder(rid, next);
    }
  }

  function maybeAddItemToCustomOrder(rid, itemId){
    if (!itemId) return;
    const entry = getRoundMetaEntry(rid);
    const existing = getRoundCustomOrder(rid);
    const mode = getRoundSortMode(rid);
    if (mode !== 'custom' && existing.length === 0) return;
    if (existing.includes(String(itemId))) return;
    setRoundCustomOrder(rid, [...existing, String(itemId)]);
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
      const normalized = sanitizeRoundMetaEntry(entry);
      if (!normalized) continue;
      out[rid] = normalized;
    }
    return out;
  }

  function captureImportRollbackSnapshot(){
    importRollbackSnapshot = {
      items: JSON.parse(JSON.stringify(state.items)),
      roundMeta: JSON.parse(JSON.stringify(normalizedRoundMeta())),
      baselineRevision: state.baselineRevision,
      latestRevision: state.latestRevision
    };
  }

  function hasImportRollbackSnapshot(){
    return !!(importRollbackSnapshot && Array.isArray(importRollbackSnapshot.items));
  }

  function clearImportRollbackSnapshot(){
    importRollbackSnapshot = null;
  }

  async function restoreImportRollbackSnapshot(){
    if (!hasImportRollbackSnapshot()) {
      return {restored: false, saveOk: false};
    }
    const payload = {
      items: JSON.parse(JSON.stringify(importRollbackSnapshot.items || [])),
      round_meta: JSON.parse(JSON.stringify(importRollbackSnapshot.roundMeta || {}))
    };
    applyLoadedData(payload);
    const base = Number(importRollbackSnapshot.baselineRevision ?? state.baselineRevision);
    const latest = Number(importRollbackSnapshot.latestRevision ?? state.latestRevision);
    state.baselineRevision = Number.isFinite(base) ? base : state.baselineRevision;
    state.latestRevision = Number.isFinite(latest) ? latest : state.latestRevision;
    history.length = 0;
    ensureBlankRowInDefaultRound();
    renderEverything();
    const ok = await saveAll();
    if (ok) {
      resetForeignRevisions();
      changeNotice.hide();
    } else {
      showSaveStatus(false);
    }
    clearImportRollbackSnapshot();
    return {restored: true, saveOk: ok};
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
    quickSearchClearBtn.dataset.theme = dark ? 'dark' : 'light';
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
      applyRoundMeta(null);
    } else if (prev && typeof prev === 'object') {
      state.items = Array.isArray(prev.items) ? prev.items : [];
      applyRoundMeta(prev.roundMeta);
    } else {
      state.items = [];
      applyRoundMeta(null);
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
  let markerOverlapRefreshTimer = null;
  function updatePinCount(){ if (pinCountEl) pinCountEl.textContent = markerLayer.getLayers().length.toString(); }

  function refreshMarkerOverlapIndicators(){
    const perId = new Map();
    const coordsList = [];
    state.items.forEach(it => {
      const lat = Number(it?.lat);
      const lon = Number(it?.lon);
      if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;
      coordsList.push({id: it.id, lat, lon});
    });
    const thresholdRatio = Math.max(0, cfgNumber('ui.marker.overlap_badge.distance_threshold_ratio', 0) || 0);
    const thresholdMeters = zoomScaledThreshold(thresholdRatio);
    if (thresholdMeters <= 0) {
      const coords = new Map();
      coordsList.forEach(entry => {
        const key = `${entry.lat.toFixed(6)}|${entry.lon.toFixed(6)}`;
        const arr = coords.get(key);
        if (arr) arr.push(entry.id);
        else coords.set(key, [entry.id]);
      });
      coords.forEach(ids => {
        const count = ids.length;
        ids.forEach(id => perId.set(id, count));
      });
    } else {
      const thresholdKm = thresholdMeters / 1000;
      const visited = new Set();
      for (let i = 0; i < coordsList.length; i++) {
        const start = coordsList[i];
        if (!start || visited.has(start.id)) continue;
        const cluster = [];
        const queue = [start];
        visited.add(start.id);
        while (queue.length) {
          const current = queue.pop();
          if (!current) continue;
          cluster.push(current);
          for (let j = 0; j < coordsList.length; j++) {
            const candidate = coordsList[j];
            if (!candidate || visited.has(candidate.id)) continue;
            const distKm = haversineKm(current.lat, current.lon, candidate.lat, candidate.lon);
            if (distKm <= thresholdKm) {
              visited.add(candidate.id);
              queue.push(candidate);
            }
          }
        }
        const size = cluster.length || 1;
        cluster.forEach(entry => perId.set(entry.id, size));
      }
    }
    state.markerOverlapCounts = perId;
    state.items.forEach((it, idx)=>{
      const mk = state.markersById.get(it.id);
      if (!mk) return;
      const iconIndex = displayIndexZeroBased(it, idx);
      mk.setIcon(iconForItem(it, iconIndex));
    });
  }

  function requestMarkerOverlapRefresh(){
    if (markerOverlapRefreshTimer != null) return;
    markerOverlapRefreshTimer = setTimeout(()=>{
      markerOverlapRefreshTimer = null;
      refreshMarkerOverlapIndicators();
    }, 0);
  }

  map.on('zoom', requestMarkerOverlapRefresh);
  map.on('zoomend', requestMarkerOverlapRefresh);
  map.whenReady(requestMarkerOverlapRefresh);

  function metersPerPixelAtCenter(){
    if (!map || typeof map.getSize !== 'function') return 0;
    const size = map.getSize();
    if (!size || size.x <= 0 || size.y <= 0) return 0;
    const centerPoint = L.point(size.x / 2, size.y / 2);
    const onePixelPoint = L.point(centerPoint.x + 1, centerPoint.y);
    const centerLatLng = map.containerPointToLatLng(centerPoint);
    const onePixelLatLng = map.containerPointToLatLng(onePixelPoint);
    if (!centerLatLng || !onePixelLatLng) return 0;
    const meters = haversineKm(
      centerLatLng.lat,
      centerLatLng.lng,
      onePixelLatLng.lat,
      onePixelLatLng.lng
    ) * 1000;
    return Number.isFinite(meters) && meters > 0 ? meters : 0;
  }

  function zoomScaledThreshold(ratio){
    if (!Number.isFinite(ratio) || ratio <= 0) return 0;
    const metersPerPixel = metersPerPixelAtCenter();
    if (!Number.isFinite(metersPerPixel) || metersPerPixel <= 0) return 0;
    return ratio * metersPerPixel;
  }

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

  function colorWithOpacity(hexColor, opacity){
    const color = (hexColor || '').toString().trim();
    const rawOpacity = Number(opacity);
    const safeOpacity = Number.isFinite(rawOpacity) ? Math.min(Math.max(rawOpacity, 0), 1) : 1;
    if (!color) return 'rgba(0,0,0,' + safeOpacity + ')';
    if (!/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(color) || safeOpacity >= 0.999) {
      return color;
    }
    let hex = color.slice(1);
    if (hex.length === 3) {
      hex = hex.split('').map(ch => ch + ch).join('');
    }
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    return `rgba(${r},${g},${b},${safeOpacity})`;
  }
  function cfgNumber(path, fallback){
    const raw = cfg(path, undefined);
    if (typeof raw === 'number' && Number.isFinite(raw)) return raw;
    if (typeof raw === 'string' && raw.trim() !== '') {
      const num = Number(raw);
      if (Number.isFinite(num)) return num;
    }
    return fallback;
  }
  function numberedIcon(hex, num, overlapCount=0){
    const n = (''+num).slice(0,3);
    const defaultText = cfg('ui.marker.default_text_color', '#fff') || '#fff';
    const textCol = cfg('ui.marker.auto_contrast', true) ? idealTextColor(hex) : defaultText;
    const sz = cfgNumber('ui.marker.icon_size', 38) || 38;
    const fsz = cfgNumber('ui.marker.font_size', 14) || 14;
    const fontFamily = cfg('ui.marker.font_family', 'Arial,Helvetica,sans-serif') || 'Arial,Helvetica,sans-serif';
    const fontWeight = cfg('ui.marker.font_weight', 800) || 800;
    const viewBoxSizeCandidate = cfgNumber('ui.marker.view_box_size', 32);
    const viewBoxSize = Number.isFinite(viewBoxSizeCandidate) && viewBoxSizeCandidate > 0 ? viewBoxSizeCandidate : 32;
    const strokeColor = cfg('ui.marker.stroke_color', '#333') || '#333';
    const strokeOpacity = cfgNumber('ui.marker.stroke_opacity', 0.25);
    const strokeWidth = cfgNumber('ui.marker.stroke_width', 1);
    const pathDef = (cfg('ui.marker.icon_path', '') || '').toString().trim() || 'M16 2c6.1 0 11 4.9 11 11 0 7.5-11 17-11 17S5 20.5 5 13c0-6.1 4.9-11 11-11z';
    const textX = viewBoxSize / 2;
    const textY = viewBoxSize / 2;
    const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" width="${sz}" height="${sz}" viewBox="0 0 ${viewBoxSize} ${viewBoxSize}">
      <g fill="none">
        <path d="${pathDef}" fill="${hex}" stroke="${strokeColor}" stroke-opacity="${strokeOpacity}" stroke-width="${strokeWidth}"/>
        <text x="${textX}" y="${textY}" text-anchor="middle" dominant-baseline="middle" font-size="${fsz}" font-family="${fontFamily}" font-weight="${fontWeight}" fill="${textCol}">${n}</text>
      </g>
    </svg>`;
    const defaultAnchorX = Math.round(sz/2);
    let anchorX = defaultAnchorX;
    const iconAnchorXRaw = cfg('ui.marker.icon_anchor_x', null);
    if (typeof iconAnchorXRaw === 'number' && Number.isFinite(iconAnchorXRaw)) {
      anchorX = iconAnchorXRaw;
    } else if (typeof iconAnchorXRaw === 'string' && iconAnchorXRaw.trim() !== '') {
      if (iconAnchorXRaw.trim().toLowerCase() === 'center') {
        anchorX = defaultAnchorX;
      } else {
        const parsed = Number(iconAnchorXRaw);
        if (Number.isFinite(parsed)) anchorX = parsed;
      }
    }
    const defaultAnchorY = sz - 1;
    let anchorY = defaultAnchorY;
    const iconAnchorYRaw = cfg('ui.marker.icon_anchor_y', null);
    if (typeof iconAnchorYRaw === 'number' && Number.isFinite(iconAnchorYRaw)) {
      anchorY = iconAnchorYRaw;
    } else if (typeof iconAnchorYRaw === 'string' && iconAnchorYRaw.trim() !== '') {
      if (iconAnchorYRaw.trim().toLowerCase() === 'bottom') {
        anchorY = defaultAnchorY;
      } else {
        const parsed = Number(iconAnchorYRaw);
        if (Number.isFinite(parsed)) anchorY = parsed;
      }
    }
    let popupAnchorX = 0;
    const popupAnchorXRaw = cfg('ui.marker.popup_anchor_x', 0);
    if (typeof popupAnchorXRaw === 'number' && Number.isFinite(popupAnchorXRaw)) {
      popupAnchorX = popupAnchorXRaw;
    } else if (typeof popupAnchorXRaw === 'string' && popupAnchorXRaw.trim() !== '') {
      const parsed = Number(popupAnchorXRaw);
      if (Number.isFinite(parsed)) popupAnchorX = parsed;
    }
    const popupDefaultY = -Math.max(33, sz-5);
    let popupAnchorY = popupDefaultY;
    const popupAnchorYRaw = cfg('ui.marker.popup_anchor_y', null);
    if (typeof popupAnchorYRaw === 'number' && Number.isFinite(popupAnchorYRaw)) {
      popupAnchorY = popupAnchorYRaw;
    } else if (typeof popupAnchorYRaw === 'string' && popupAnchorYRaw.trim() !== '') {
      if (popupAnchorYRaw.trim().toLowerCase() === 'auto') {
        popupAnchorY = popupDefaultY;
      } else {
        const parsed = Number(popupAnchorYRaw);
        if (Number.isFinite(parsed)) popupAnchorY = parsed;
      }
    }
    const wrapperStyles = [`--marker-size:${sz}px`];
    const overlapHtml = (()=>{
      if (overlapCount <= 1) return '';
      const indicatorText = overlapCount > 99 ? '99+' : String(overlapCount);
      const badgeSize = Math.max(1, cfgNumber('ui.marker.overlap_badge.size', 16));
      const badgeMargin = Math.max(0, cfgNumber('ui.marker.overlap_badge.margin_right', 1.5));
      const badgeY = cfgNumber('ui.marker.overlap_badge.offset_y', 2.5);
      const badgeFontScale = cfgNumber('ui.marker.overlap_badge.font_scale', 0.7);
      const badgeFontSize = Math.max(6, Math.round(fsz * Math.max(0, badgeFontScale)));
      const badgeFill = cfg('ui.marker.overlap_badge.fill', '#0f172a') || '#0f172a';
      const badgeFillOpacity = cfgNumber('ui.marker.overlap_badge.fill_opacity', 0.92);
      const badgeStroke = cfg('ui.marker.overlap_badge.stroke', '#fff') || '#fff';
      const badgeStrokeOpacity = cfgNumber('ui.marker.overlap_badge.stroke_opacity', 0.65);
      const badgeStrokeWidth = cfgNumber('ui.marker.overlap_badge.stroke_width', 0.8);
      const badgeTextColor = cfg('ui.marker.overlap_badge.text_color', '#fff') || '#fff';
      const badgeFontFamily = cfg('ui.marker.overlap_badge.font_family', fontFamily) || fontFamily;
      const badgeFontWeight = cfg('ui.marker.overlap_badge.font_weight', 700) || 700;
      const badgeCornerRadius = Math.max(0, cfgNumber('ui.marker.overlap_badge.corner_radius', 8));
      const styleParts = [
        `--overlap-size:${badgeSize}px`,
        `--overlap-margin-right:${badgeMargin}px`,
        `--overlap-offset-y:${badgeY}px`,
        `--overlap-font-size:${badgeFontSize}px`,
        `--overlap-bg:${colorWithOpacity(badgeFill, badgeFillOpacity)}`,
        `--overlap-border:${colorWithOpacity(badgeStroke, badgeStrokeOpacity)}`,
        `--overlap-border-width:${badgeStrokeWidth}px`,
        `--overlap-text-color:${badgeTextColor}`,
        `--overlap-font-family:${JSON.stringify(badgeFontFamily)}`,
        `--overlap-font-weight:${badgeFontWeight}`,
        `--overlap-corner-radius:${badgeCornerRadius}px`
      ];
      const styleAttr = styleParts.length ? ` style="${styleParts.join(';')}"` : '';
      return `<span class="marker-overlap-badge"${styleAttr}>${indicatorText}</span>`;
    })();
    const wrapperStyleAttr = wrapperStyles.length ? ` style="${wrapperStyles.join(';')}"` : '';
    const html = `<div class="marker-pin-wrapper"${wrapperStyleAttr}><img class="marker-pin-image" src="data:image/svg+xml;base64,${btoa(svg)}" alt="" aria-hidden="true" />${overlapHtml}</div>`;
    return L.divIcon({
      className:'marker-pin-icon',
      html,
      iconSize:[sz,sz],
      iconAnchor:[anchorX, anchorY],
      popupAnchor:[popupAnchorX, popupAnchorY]
    });
  }

  function displayIndexZeroBased(it, fallbackIndex){
    if (!it || !it.id) return fallbackIndex;
    const map = state.displayIndexById;
    if (map instanceof Map){
      const raw = map.get(it.id);
      const num = Number(raw);
      if (Number.isFinite(num) && num > 0) return num - 1;
    }
    return fallbackIndex;
  }

  function iconForItem(it, index){
    const color = colorForRound(+it.round||0);
    const overlapCount = state.markerOverlapCounts instanceof Map ? (state.markerOverlapCounts.get(it.id) || 1) : 1;
    const showCount = feature('marker_overlap_indicator', false) && overlapCount > 1 ? overlapCount : 0;
    return numberedIcon(color, index+1, showCount);
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
    const options = opts ? {...opts} : {};
    options.headers = buildHeaders(options.headers || {});
    const r = await fetch(url, options);
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
    applyRoundMeta(j.round_meta);
    markerLayer.clearLayers();
    state.markersById.clear();
    state.markerOverlapCounts = new Map();
    updatePinCount();
    requestMarkerOverlapRefresh();
  }
  async function loadAll(){
    const j = await fetchJSON(EP.load, {cache:'no-store'});
    applyLoadedData(j);
    const rev = Number(j.revision ?? 0) || 0;
    state.baselineRevision = rev;
    state.latestRevision = Math.max(state.latestRevision, rev);
    resetForeignRevisions();
    changeNotice.hide();
    closeConflictOverlay();
    state.conflictNotified.clear();
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
      const headers = buildHeaders({'Content-Type':'application/json'});
      const requestId = makeRequestId();
      headers.set('X-Request-ID', requestId);
      const r = await fetch(EP.save, {method:'POST', headers, body: payload});
      const t = await r.text();
      let j=null; try{ j = JSON.parse(t); }catch(_){}
      const ok = !!(r.ok && j && j.ok===true);
      showSaveStatus(ok);
      const revNum = Number(j?.rev);
      if (ok && j && Number.isFinite(revNum)) {
        updateKnownRevision(revNum);
        resetForeignRevisions();
        changeNotice.hide();
      }
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
        const fallbackCity = cityFromDisplay(address, item.city);
        updated.city = geo.city || fallbackCity;
        state.items[i] = updated;
        changed = true;
      } catch (err) {
        console.error('auto geocode failed for import', err);
        failed += 1;
        const idPart = item.id != null ? `#${item.id} ` : '';
        const label = (item[labelFieldId] ?? '').toString().trim();
        const labelPart = label ? `${label} – ` : '';
        const fallbackCity = cityFromDisplay(address, item.city);
        failures.push({
          index: i,
          id: idStr,
          label,
          address,
          city: typeof item.city === 'string' ? item.city.trim() : '',
          fallbackCity,
          summary: `${idPart}${labelPart}${address}`.trim() || fallbackCity || `${idPart}${label}`.trim()
        });
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
      const mode = getRoundSortMode(rid);
      if (mode === 'custom') {
        const active = entries.filter(entry => !isItemCompletelyBlank(entry.it));
        const placeholders = entries.filter(entry => isItemCompletelyBlank(entry.it));
        const ids = active.map(entry => entry.it?.id).filter(id => id != null);
        const order = syncCustomOrderWithItems(rid, ids);
        const orderIndex = new Map(order.map((id, index) => [id, index]));
        active.sort((a, b) => {
          const aKey = orderIndex.has(a.it.id) ? orderIndex.get(a.it.id) : (order.length + a.idx);
          const bKey = orderIndex.has(b.it.id) ? orderIndex.get(b.it.id) : (order.length + b.idx);
          if (aKey !== bKey) return aKey - bKey;
          return a.idx - b.idx;
        });
        const placeholderSorted = placeholders.sort((a,b)=>a.idx-b.idx);
        active.concat(placeholderSorted).forEach(entry => ordered.push(entry.it));
        return;
      }

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
    const plannedTimeEnabled = feature('round_planned_time', false);
    const plannedTimeLabel = text('round.planned_time_label', 'Tervezett idő');
    const plannedTimeHint = cfg('text.round.planned_time_hint', '');
    const plannedTimeValue = getPlannedTimeForRound(rid);
    const plannedTimeInputId = `round_${cssId(String(rid))}_planned_time`;
    const showPlannedDate = plannedDateEnabled && !isRoundZero(rid);
    const showPlannedTime = plannedTimeEnabled && !isRoundZero(rid);
    const sortLabel = text('round.sort_mode_label', 'Rendezés');
    const sortDefaultLabel = text('round.sort_mode_default', 'Alapértelmezett (távolság)');
    const sortCustomLabel = text('round.sort_mode_custom', 'Egyéni (drag & drop)');
    const sortHint = text('round.sort_mode_custom_hint', 'Fogd és vidd a címeket a rendezéshez');
    const sortSelectId = `round_${cssId(String(rid))}_sort_mode`;
    const sortMode = getRoundSortMode(rid);
    const dragIndicator = sortMode === 'custom'
      ? `<span class="group-drag-indicator" title="${esc(sortHint)}" aria-hidden="true">⠿</span>`
      : '';
    const actionButtons = [];
    if (feature('group_actions.open', true)) actionButtons.push(`<button class="iconbtn grp-open" data-round="${rid}">${esc(actionsText.open ?? 'Kinyit')}</button>`);
    if (feature('group_actions.close', true)) actionButtons.push(`<button class="iconbtn grp-close" data-round="${rid}">${esc(actionsText.close ?? 'Összezár')}</button>`);
    if (feature('group_actions.print', true)) actionButtons.push(`<button class="iconbtn grp-print" data-round="${rid}">${esc(actionsText.print ?? 'Nyomtatás')}</button>`);
    if (feature('group_actions.navigate', true)) actionButtons.push(`<button class="iconbtn grp-nav" data-round="${rid}">${esc(actionsText.navigate ?? 'Navigáció')}</button>`);
    if (feature('group_actions.delete', true)) actionButtons.push(`<button class="iconbtn grp-del" data-round="${rid}" style="border-color:#fecaca;background:rgba(248,113,113,0.12);">${esc(actionsText.delete ?? 'Kör törlése')}</button>`);
    g.innerHTML = `
      <div class="group-header" data-group-header="${rid}" data-sort-mode="${esc(sortMode)}">
        <div class="group-title">
          <span style="display:inline-block;width:12px;height:12px;border-radius:999px;background:${color};border:1px solid #d1d5db;margin-right:8px;vertical-align:middle"></span>
          ${esc(roundLabel(rid))}
          ${dragIndicator}
          ${sumTxt ? `<span class="__sum" style="margin-left:8px;color:#6b7280;font-weight:600;font-size:12px;">${esc(sumTxt)}</span>` : ''}
        </div>
        <div class="group-controls">
          ${showPlannedDate ? `
            <div class="group-planned-date">
              <label class="planned-date-label" for="${plannedDateInputId}">${esc(plannedDateLabel)}</label>
              <input type="text" id="${plannedDateInputId}" class="planned-date-input" data-round="${rid}" value="${esc(plannedDateValue)}"${plannedDateHint ? ` title="${esc(plannedDateHint)}"` : ''}>
            </div>` : ''}
          ${showPlannedTime ? `
            <div class="group-planned-time">
              <label class="planned-time-label" for="${plannedTimeInputId}">${esc(plannedTimeLabel)}</label>
              <input type="time" id="${plannedTimeInputId}" class="planned-time-input" data-round="${rid}" value="${esc(plannedTimeValue)}"${plannedTimeHint ? ` title="${esc(plannedTimeHint)}"` : ''}>
            </div>` : ''}
          <div class="group-sort">
            <label class="sort-mode-label" for="${sortSelectId}">${esc(sortLabel)}</label>
            <select id="${sortSelectId}" class="round-sort-mode" data-round="${rid}"${sortMode==='custom' && sortHint ? ` title="${esc(sortHint)}"` : ''}>
              <option value="default"${sortMode==='default' ? ' selected' : ''}>${esc(sortDefaultLabel)}</option>
              <option value="custom"${sortMode==='custom' ? ' selected' : ''}>${esc(sortCustomLabel)}</option>
            </select>
          </div>
        </div>
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
    const iconIndex = displayIndexZeroBased(it, index);
    const icon = iconForItem(it, iconIndex);
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
    requestMarkerOverlapRefresh();
  }

  function renumberAll(){
    let i=0;
    const displayMap = new Map();
    groupsEl.querySelectorAll('.row').forEach(row=>{
      const nEl = row.querySelector('[data-num]'); if (!nEl) return;
      const isPlaceholder = row.dataset.placeholder === 'true';
      if (isPlaceholder){
        nEl.textContent = '';
        nEl.style.background = 'transparent';
        nEl.style.color = 'transparent';
        nEl.style.visibility = 'hidden';
        return;
      }
      nEl.style.visibility = '';
      const id = row.dataset.rowId;
      const idx = ++i;
      nEl.textContent = String(idx).padStart(2,'0');
      if (id) displayMap.set(id, idx);
      const it = state.items.find(x=>x.id===id);
      if (it){
        const c = colorForRound(+it.round||0);
        nEl.style.background = c;
        nEl.style.color = idealTextColor(c);
      }
    });
    state.displayIndexById = displayMap;
    state.items.forEach((it,idx)=>{
      const mk = state.markersById.get(it.id);
      if (mk){
        const iconIndex = displayIndexZeroBased(it, idx);
        mk.setIcon(iconForItem(it, iconIndex));
      }
    });
    requestMarkerOverlapRefresh();
  }

  function highlightRow(id, flash=false){
    groupsEl.querySelectorAll('.row').forEach(r=>r.classList.remove('highlight'));
    if (newAddressEl) {
      newAddressEl.querySelectorAll('.row').forEach(r=>r.classList.remove('highlight'));
    }
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
    const radiusLegacy = cfgNumber('ui.marker.focus_ring_radius', 80);
    const radiusCfg = cfgNumber('ui.marker.focus_ring.radius', radiusLegacy);
    const radius = Number.isFinite(radiusCfg) && radiusCfg > 0 ? radiusCfg : radiusLegacy;
    const colorSetting = cfg('ui.marker.focus_ring_color', 'auto');
    const baseColor = (typeof colorSetting === 'string' && colorSetting.toLowerCase() !== 'auto') ? colorSetting : colorForRound(+it.round||0);
    const weight = Math.max(0, cfgNumber('ui.marker.focus_ring.weight', 2));
    const strokeOpacity = Math.max(0, cfgNumber('ui.marker.focus_ring.stroke_opacity', 0.8));
    const fillOpacity = Math.max(0, cfgNumber('ui.marker.focus_ring.fill_opacity', 0.25));
    const initialOpacity = Math.max(0, cfgNumber('ui.marker.focus_ring.initial_opacity', strokeOpacity || 0.8));
    const initialFillOpacity = Math.max(0, cfgNumber('ui.marker.focus_ring.initial_fill_opacity', fillOpacity || 0.25));
    const fadeStep = Math.max(0, cfgNumber('ui.marker.focus_ring.fade_step', 0.12));
    const fillFadeStep = Math.max(0, cfgNumber('ui.marker.focus_ring.fill_fade_step', 0.06));
    const fadeInterval = Math.max(16, cfgNumber('ui.marker.focus_ring.fade_interval_ms', 60));
    const lifetime = Math.max(fadeInterval, cfgNumber('ui.marker.focus_ring.lifetime_ms', 800));
    const c = L.circle([it.lat, it.lon], {radius, color: baseColor, weight, fillColor: baseColor, fillOpacity: initialFillOpacity, opacity: initialOpacity});
    c.addTo(map);
    let op = initialOpacity;
    let fo = initialFillOpacity;
    const iv = setInterval(()=>{
      op = Math.max(0, op - fadeStep);
      fo = Math.max(0, fo - fillFadeStep);
      c.setStyle({opacity: op, fillOpacity: fo});
      if (op <= 0 && fo <= 0){
        clearInterval(iv);
        markerLayer.removeLayer(c);
        map.removeLayer(c);
      }
    }, fadeInterval);
    setTimeout(()=>{
      try{
        clearInterval(iv);
        markerLayer.removeLayer(c);
        map.removeLayer(c);
      }catch(_){ }
    }, lifetime);
  }

  async function doOk(id, overrideRound=null){
    const idx = state.items.findIndex(x=>x.id===id);
    if (idx<0) return;
    const it = state.items[idx];
    const prevRound = +it.round || 0;
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
      removeItemFromCustomOrder(prevRound, updated.id);
      maybeAddItemToCustomOrder(+updated.round || 0, updated.id);
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

  let activeDragContext = null;
  let dragReadyRowId = null;

  function clearDragHighlights(){
    document.querySelectorAll('.row.drag-over-before, .row.drag-over-after, .row.dragging').forEach(el => {
      el.classList.remove('drag-over-before', 'drag-over-after', 'dragging');
    });
  }

  function handleRowDragStart(e){
    const row = e.currentTarget;
    if (!(row instanceof HTMLElement)) return;
    const roundId = Number(row.dataset.roundId || '0');
    if (getRoundSortMode(roundId) !== 'custom') { e.preventDefault(); return; }
    if (row.dataset.placeholder === 'true') { e.preventDefault(); return; }
    if (dragReadyRowId !== row.dataset.rowId) { e.preventDefault(); return; }
    dragReadyRowId = null;
    const sourceId = row.dataset.rowId || '';
    if (!sourceId) { e.preventDefault(); return; }
    activeDragContext = {
      sourceId,
      roundId,
      dropBefore: null,
      targetId: null
    };
    row.classList.add('dragging');
    if (e.dataTransfer) {
      e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', sourceId); } catch (_) {}
    }
  }

  function handleRowDragEnd(){
    clearDragHighlights();
    activeDragContext = null;
    dragReadyRowId = null;
  }

  function handleRowDragOver(e){
    if (!activeDragContext) return;
    const row = e.currentTarget;
    if (!(row instanceof HTMLElement)) return;
    const roundId = Number(row.dataset.roundId || '0');
    if (roundId !== activeDragContext.roundId) return;
    const targetId = row.dataset.rowId || '';
    if (!targetId || targetId === activeDragContext.sourceId) return;
    e.preventDefault();
    if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
    const rect = row.getBoundingClientRect();
    const before = (e.clientY - rect.top) < rect.height / 2;
    activeDragContext.dropBefore = before;
    activeDragContext.targetId = targetId;
    row.classList.toggle('drag-over-before', before);
    row.classList.toggle('drag-over-after', !before);
  }

  function handleRowDragLeave(e){
    const row = e.currentTarget;
    if (!(row instanceof HTMLElement)) return;
    row.classList.remove('drag-over-before', 'drag-over-after');
  }

  async function handleRowDrop(e){
    if (!activeDragContext) return;
    const row = e.currentTarget;
    if (!(row instanceof HTMLElement)) return;
    const roundId = Number(row.dataset.roundId || '0');
    if (roundId !== activeDragContext.roundId) {
      clearDragHighlights();
      activeDragContext = null;
      return;
    }
    e.preventDefault();
    const targetId = row.dataset.rowId || '';
    const sourceId = activeDragContext.sourceId;
    if (!sourceId || !targetId || sourceId === targetId) {
      clearDragHighlights();
      activeDragContext = null;
      return;
    }
    const body = row.closest('.group-body');
    if (!body) {
      clearDragHighlights();
      activeDragContext = null;
      return;
    }
    const rows = Array.from(body.querySelectorAll('.row')).filter(r => r.dataset.placeholder !== 'true');
    let order = rows.map(r => r.dataset.rowId).filter(id => id);
    order = order.filter(id => id !== sourceId);
    let insertIdx = order.indexOf(targetId);
    if (insertIdx < 0) insertIdx = order.length;
    if (!activeDragContext.dropBefore) insertIdx += 1;
    order.splice(insertIdx, 0, sourceId);
    clearDragHighlights();
    activeDragContext = null;
    dragReadyRowId = null;
    pushSnapshot();
    setRoundSortMode(roundId, 'custom');
    setRoundCustomOrder(roundId, order);
    renderEverything();
    await saveAll();
  }

  function renderRow(it, globalIndex, options = {}){
    const addressFieldId = getAddressFieldId();
    const labelFieldId = getLabelFieldId();
    const city = cityFromDisplay(it[addressFieldId], it.city);
    const row = document.createElement('div');
    row.className = 'row';
    row.dataset.rowId = it.id;
    const opts = options || {};
    const isPlaceholderItem = isItemCompletelyBlank(it);
    const allowDragFeature = opts.dragEnabled === true;
    const dragEnabled = allowDragFeature && !isPlaceholderItem;
    const dragHandleTitle = text('round.custom_sort_handle_hint', 'Fogd meg és húzd a cím átrendezéséhez');
    const dragCellHtml = allowDragFeature
      ? (dragEnabled
          ? `<button class="iconbtn drag-handle" type="button" title="${esc(dragHandleTitle)}" aria-label="${esc(dragHandleTitle)}"><span aria-hidden="true">⠿</span></button>`
          : `<span class="drag-handle drag-handle--inactive" aria-hidden="true">⠿</span>`)
      : `<span class="drag-handle drag-handle--hidden" aria-hidden="true">⠿</span>`;
    if (isPlaceholderItem) row.classList.add('row--placeholder');
    if (opts.newAddress) row.classList.add('row--new-address');
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
    const showNumber = !(opts.suppressNumber ?? false) && !isPlaceholderItem;
    const rawIndex = Number.isFinite(globalIndex) ? globalIndex + 1 : NaN;
    const numberText = showNumber && Number.isFinite(rawIndex) && rawIndex > 0
      ? String(rawIndex).padStart(2, '0')
      : '';
    let numStyle = `background:${roundColor}; color:${numTextColor};`;
    if (!showNumber) {
      numStyle = 'background:transparent; color:transparent; visibility:hidden;';
    }
    const numAria = showNumber ? '' : ' aria-hidden="true"';

    row.dataset.roundId = String(+it.round || 0);
    row.dataset.placeholder = isPlaceholderItem ? 'true' : 'false';

    row.innerHTML = `
      <div class="header">
        <div class="drag-cell">${dragCellHtml}</div>
        <div class="num" data-num${numAria} style="${numStyle}">${numberText}</div>
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

    const dragHandle = row.querySelector('.drag-handle');
    if (dragHandle instanceof HTMLElement) {
      dragHandle.setAttribute('draggable', 'false');
    }
    if (dragEnabled && dragHandle instanceof HTMLElement && dragHandle.tagName === 'BUTTON') {
      const resetReady = ()=>{ dragReadyRowId = null; };
      dragHandle.addEventListener('pointerdown', ()=>{ dragReadyRowId = row.dataset.rowId || null; });
      dragHandle.addEventListener('pointerup', resetReady);
      dragHandle.addEventListener('pointercancel', resetReady);
      dragHandle.addEventListener('click', e => e.preventDefault());
    }
    if (allowDragFeature) {
      row.addEventListener('dragover', handleRowDragOver);
      row.addEventListener('dragleave', handleRowDragLeave);
      row.addEventListener('drop', handleRowDrop);
    }
    if (dragEnabled) {
      row.draggable = true;
      row.classList.add('row--draggable');
      row.addEventListener('dragstart', handleRowDragStart);
      row.addEventListener('dragend', handleRowDragEnd);
    } else {
      row.draggable = false;
    }

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
      removeItemFromCustomOrder(prevRound, it.id);
      maybeAddItemToCustomOrder(selRound, it.id);
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
        removeItemFromCustomOrder(rPrev, it.id);
        clearCollapsePref(it.id);
        renderGroupHeaderTotalsForRound(rPrev);
      }
      const mk = state.markersById.get(it.id);
      if (mk){ markerLayer.removeLayer(mk); state.markersById.delete(it.id); updatePinCount(); requestMarkerOverlapRefresh(); }
      saveAll();
      renderEverything();
    });

    updateRowHeaderMeta(row, it);
    state.rowsById.set(it.id, row);
    refreshDeleteButtonState(row, it);

    row.addEventListener('focusin', ()=>{
      const current = state.items.find(x => x?.id === it.id);
      state.activeEditor = {
        id: it.id,
        snapshot: current ? JSON.parse(JSON.stringify(current)) : null
      };
      state.conflictNotified.delete(it.id);
    });
    row.addEventListener('focusout', (event)=>{
      if (!row.contains(event.relatedTarget)) {
        state.activeEditor = null;
      }
    });

    return row;
  }

  // ======= GYORS KERESŐ / SZŰRŐ
  function injectQuickSearch(){
    if (!feature('quick_search', true)) return;
    // csak egyszer
    if (document.getElementById('quickSearchWrap')) return;
    const wrap = document.createElement('div');
    wrap.id = 'quickSearchWrap';
    wrap.className = 'quick-search-wrapper';

    const form = document.createElement('form');
    form.className = 'quick-search-form';
    form.addEventListener('submit', event => event.preventDefault());

    const label = document.createElement('label');
    label.className = 'quick-search-label';
    label.setAttribute('for', 'quickSearch');

    const inp = document.createElement('input');
    inp.id = 'quickSearch';
    inp.type = 'search';
    inp.className = 'quick-search-input';
    inp.placeholder = text('quick_search.placeholder', 'Keresés…');
    inp.autocomplete = 'off';

    const fancyBg = document.createElement('div');
    fancyBg.className = 'quick-search-fancy-bg';

    const icon = document.createElement('div');
    icon.className = 'quick-search-icon';
    icon.innerHTML = `
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M21.53 20.47l-3.66-3.66C19.195 15.24 20 13.214 20 11c0-4.97-4.03-9-9-9s-9 4.03-9 9 4.03 9 9 9c2.215 0 4.24-.804 5.808-2.13l3.66 3.66c.147.146.34.22.53.22s.385-.073.53-.22c.295-.293.295-.767.002-1.06zM3.5 11c0-4.135 3.365-7.5 7.5-7.5s7.5 3.365 7.5 7.5-3.365 7.5-7.5 7.5-7.5-3.365-7.5-7.5z"></path>
      </svg>
    `;

    const clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'quick-search-close';
    clearBtn.title = text('quick_search.clear_title', 'Szűrés törlése');
    clearBtn.setAttribute('aria-label', text('quick_search.clear_title', 'Szűrés törlése'));
    clearBtn.innerHTML = `
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
      </svg>
    `;

    label.appendChild(inp);
    label.appendChild(fancyBg);
    label.appendChild(icon);
    label.appendChild(clearBtn);

    form.appendChild(label);
    wrap.appendChild(form);

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
    status.className = 'quick-search-status';
    status.setAttribute('role', 'status');
    if (panelTopEl) {
      panelTopEl.appendChild(status);
    } else {
      parent.insertBefore(status, groupsEl);
    }

    const updateValueState = ()=>{
      wrap.classList.toggle('has-value', inp.value.trim().length > 0);
    };

    function updateIndicator(active, visibleCount){
      wrap.classList.toggle('has-filter', active);
      status.classList.toggle('is-active', active);
      status.classList.toggle('is-empty', active && visibleCount === 0);
      if (active) {
        const tpl = text('quick_search.filtered_notice', 'Szűrt találatok: {count}');
        const emptyTpl = text('quick_search.filtered_empty', 'Nincs találat a megadott szűrőre.');
        status.textContent = visibleCount > 0
          ? format(tpl, {count: visibleCount})
          : emptyTpl;
      } else {
        status.textContent = '';
      }
    }

    function applyFilter(){
      const q = (state.filterText || '').trim().toLowerCase();
      const matchCache = new Map();
      function matchesFilter(it){
        if (!it) return false;
        const cacheKey = it.id ?? it;
        if (matchCache.has(cacheKey)) return matchCache.get(cacheKey);
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
        matchCache.set(cacheKey, match);
        return match;
      }
      let visibleCount = 0;
      // sorok
      const rows = groupsEl.querySelectorAll('.row');
      rows.forEach(row=>{
        const id = row.dataset.rowId;
        const it = state.items.find(x=>x.id===id);
        if (!it){ row.style.display=''; return; }
        const match = matchesFilter(it);
        row.style.display = match ? '' : 'none';
        if (match) visibleCount++;
      });
      // körök: ha egy körben nincs látható sor → rejt
      groupsEl.querySelectorAll('.group').forEach(g=>{
        const body = g.querySelector('.group-body');
        const anyVisible = Array.from(body.children).some(ch => ch.classList.contains('row') && ch.style.display!=='none');
        g.style.display = anyVisible ? '' : 'none';
      });
      let markerLayerChanged = false;
      state.items.forEach(it => {
        const mk = state.markersById.get(it.id);
        if (!mk) return;
        const match = matchesFilter(it);
        const hasLayer = markerLayer.hasLayer(mk);
        if (match && !hasLayer) {
          markerLayer.addLayer(mk);
          markerLayerChanged = true;
        } else if (!match && hasLayer) {
          markerLayer.removeLayer(mk);
          markerLayerChanged = true;
        }
      });
      if (markerLayerChanged) {
        updatePinCount();
        requestMarkerOverlapRefresh();
      }
      updateIndicator(!!q, visibleCount);
      updateValueState();
    }

    inp.addEventListener('input', ()=>{
      state.filterText = inp.value;
      applyFilter();
    });
    inp.addEventListener('change', updateValueState);
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
      updateValueState();
    }
  }

  function renderGroups(){
    state.rowsById.clear();
    groupsEl.innerHTML = '';
    if (newAddressEl) {
      newAddressEl.innerHTML = '';
      newAddressEl.style.display = 'none';
    }
    ensureBlankRowInDefaultRound();
    autoSortItems();

    let placeholderItem = null;
    for (const item of state.items) {
      if (!isItemCompletelyBlank(item)) continue;
      placeholderItem = item;
      if ((+item.round || 0) === 0) break;
    }
    if (newAddressEl && placeholderItem) {
      const placeholderRow = renderRow(placeholderItem, 0, {suppressNumber: true, newAddress: true});
      if (placeholderRow) {
        newAddressEl.appendChild(placeholderRow);
        newAddressEl.style.display = '';
      }
    }
    const placeholderId = placeholderItem?.id;

    const orderNonZero = state.cfg.rounds.map(r=>Number(r.id)).filter(id=>id!==0).sort((a,b)=>a-b);
    ROUND_ORDER = state.cfg.app.round_zero_at_bottom ? [...orderNonZero, ...(ROUND_MAP.has(0)?[0]:[])] : state.cfg.rounds.map(r=>Number(r.id));

    const order = ROUND_ORDER.slice();
    const unknown = [...new Set(state.items.map(it=>+it.round||0))].filter(id=>!order.includes(id));
    unknown.forEach(id=>order.push(id));

    let globalIndex = 0;
    order.forEach(rid=>{
      const inRound = state.items.filter(it => (+it.round||0) === rid && it.id !== placeholderId);
      if (rid !== 0 && inRound.length === 0) return;

      const totals = totalsForRound(rid);
      const groupEl = makeGroupHeader(rid, totals);
      const body = groupEl.querySelector(`[data-group-body="${rid}"]`);
      const sortMode = getRoundSortMode(rid);
      const allowDrag = sortMode === 'custom';
      inRound.forEach(it => {
        body.appendChild(renderRow(it, globalIndex, {dragEnabled: allowDrag, sortMode}));
        globalIndex++;
      });

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
          const headers = buildHeaders({'Content-Type':'application/json'});
          const requestId = makeRequestId();
          headers.set('X-Request-ID', requestId);
          const r = await fetch(EP.deleteRound, {
            method:'POST',
            headers,
            body: JSON.stringify({round:rid})
          });
          const t = await r.text();
          let ok=false, j=null;
          try { j = JSON.parse(t); ok = !!j.ok; }
          catch(_) { ok = r.ok; }
          if (!ok) throw new Error('delete_failed');

          const revNum = Number(j?.rev);
          if (Number.isFinite(revNum)) {
            updateKnownRevision(revNum);
            resetForeignRevisions();
            changeNotice.hide();
          }

          state.items = state.items.filter(it => (+it.round||0)!==rid);
          clearRoundMeta(rid);
          removedIds.forEach(id=>{
            const mk = state.markersById.get(id);
            if (mk){ markerLayer.removeLayer(mk); state.markersById.delete(id); updatePinCount(); requestMarkerOverlapRefresh(); }
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

      const plannedTimeInput = groupEl.querySelector('.planned-time-input');
      if (plannedTimeInput) {
        plannedTimeInput.addEventListener('change', async ()=>{
          const newVal = plannedTimeInput.value.trim();
          const prevVal = getPlannedTimeForRound(rid);
          if (newVal === prevVal) return;
          pushSnapshot();
          setPlannedTimeForRound(rid, newVal);
          const ok = await saveAll();
          if (ok) {
            plannedTimeInput.value = getPlannedTimeForRound(rid);
            flashSaved(plannedTimeInput);
          }
        });
      }

      const sortSelect = groupEl.querySelector('.round-sort-mode');
      if (sortSelect) {
        sortSelect.addEventListener('change', async ()=>{
          const newMode = sortSelect.value === 'custom' ? 'custom' : 'default';
          const prevMode = getRoundSortMode(rid);
          if (newMode === prevMode) return;
          pushSnapshot();
          setRoundSortMode(rid, newMode);
          if (newMode === 'custom') {
            const ids = state.items
              .filter(item => (+item.round||0) === rid && !isItemCompletelyBlank(item))
              .map(item => item.id)
              .filter(id => id != null);
            syncCustomOrderWithItems(rid, ids);
          }
          renderEverything();
          await saveAll();
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

  function showImportProgressOverlay(initialMessage){
    const body = document.body;
    if (!body) {
      return {
        update(){},
        remove(){},
      };
    }
    const overlay = document.createElement('div');
    overlay.className = 'import-progress-overlay';
    const box = document.createElement('div');
    box.className = 'import-progress-overlay__box';
    const spinner = document.createElement('div');
    spinner.className = 'import-progress-overlay__spinner';
    const textEl = document.createElement('div');
    textEl.className = 'import-progress-overlay__text';
    textEl.textContent = initialMessage || '';
    box.appendChild(spinner);
    box.appendChild(textEl);
    overlay.appendChild(box);

    const updateBodyState = (delta)=>{
      const current = Number(body.dataset.importOverlayCount || 0);
      const next = Math.max(0, current + delta);
      if (next > 0) {
        body.dataset.importOverlayCount = String(next);
        body.classList.add('import-busy');
      } else {
        body.classList.remove('import-busy');
        body.dataset.importOverlayCount = '';
        delete body.dataset.importOverlayCount;
      }
    };

    let active = true;
    updateBodyState(1);
    body.appendChild(overlay);

    return {
      update(message){
        if (!active) return;
        if (typeof message === 'string') {
          textEl.textContent = message;
        }
      },
      remove(){
        if (!active) return;
        active = false;
        if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        updateBodyState(-1);
      }
    };
  }

  function formatMultilineText(container, message){
    const lines = (message || '').toString().split(/\n+/);
    lines.forEach((line, idx) => {
      const p = document.createElement('p');
      p.textContent = line;
      if (idx === 0) {
        p.style.marginTop = '0';
      } else {
        p.style.marginTop = '10px';
      }
      container.appendChild(p);
    });
  }

  function geocodeFailureClipboardText(failures){
    if (!Array.isArray(failures)) return '';
    const lines = failures
      .map(entry => {
        if (!entry || typeof entry !== 'object') return '';
        const parts = [entry.summary, entry.address, entry.label, entry.fallbackCity, entry.city, entry.id]
          .map(part => (part == null ? '' : String(part).trim()))
          .filter(Boolean);
        return parts.join(' – ');
      })
      .filter(line => line && line.trim());
    return lines.join('\n');
  }

  async function copyImportFailuresToClipboard(failures){
    const text = geocodeFailureClipboardText(failures);
    if (!text) throw new Error('empty');
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
      return true;
    }
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    const ok = document.execCommand('copy');
    document.body.removeChild(ta);
    if (!ok) throw new Error('copy_failed');
    return true;
  }

  async function dropImportFailures(failures){
    const indices = Array.isArray(failures)
      ? failures
          .map(entry => (Number.isInteger(entry?.index) && entry.index >= 0 ? entry.index : null))
          .filter(idx => idx != null)
      : [];
    if (!indices.length) {
      return {changed: false, removed: 0, saveOk: true};
    }
    const unique = Array.from(new Set(indices)).sort((a, b) => b - a);
    pushSnapshot();
    let removed = 0;
    unique.forEach(idx => {
      if (idx < 0 || idx >= state.items.length) return;
      const item = state.items[idx];
      if (!item || typeof item !== 'object') return;
      removed += 1;
      if (item.id != null) {
        clearCollapsePref(item.id);
        const marker = state.markersById.get(item.id);
        if (marker) {
          markerLayer.removeLayer(marker);
          state.markersById.delete(item.id);
          updatePinCount();
          requestMarkerOverlapRefresh();
        }
      }
      state.items.splice(idx, 1);
    });
    if (!removed) {
      return {changed: false, removed: 0, saveOk: true};
    }
    ensureBlankRowInDefaultRound();
    renderEverything();
    const saveOk = await saveAll();
    if (!saveOk) {
      showSaveStatus(false);
    }
    return {changed: true, removed, saveOk};
  }

  function showGeocodeFailureDialog(message, failures, options = {}){
    if (!Array.isArray(failures) || failures.length === 0) {
      alert(message);
      return Promise.resolve('dismiss');
    }
    return new Promise(resolve => {
      const overlay = document.createElement('div');
      overlay.className = 'import-dialog-overlay';
      const box = document.createElement('div');
      box.className = 'import-dialog';
      const textWrap = document.createElement('div');
      textWrap.className = 'import-dialog__text';
      formatMultilineText(textWrap, message);

      const listTitle = document.createElement('h3');
      listTitle.textContent = text('messages.import_geocode_partial_list_title', 'Nem sikerült geokódolni:');
      listTitle.style.marginBottom = '8px';
      const list = document.createElement('ul');
      list.className = 'import-dialog__list';
      failures.forEach(entry => {
        const li = document.createElement('li');
        li.textContent = entry.summary || entry.address || entry.label || entry.id || '';
        list.appendChild(li);
      });

      const buttonRow = document.createElement('div');
      buttonRow.className = 'import-dialog__actions import-dialog__actions--wrap';
      const copyBtn = document.createElement('button');
      copyBtn.type = 'button';
      copyBtn.className = 'ghost';
      copyBtn.textContent = text('messages.import_geocode_copy', 'Címek másolása');
      const skipBtn = document.createElement('button');
      skipBtn.type = 'button';
      skipBtn.className = 'secondary';
      skipBtn.textContent = text('messages.import_geocode_skip_addresses', 'Címek kihagyása');
      const useCityBtn = document.createElement('button');
      useCityBtn.type = 'button';
      useCityBtn.className = 'primary';
      useCityBtn.textContent = text('messages.import_geocode_use_city', 'Település alapján helyezze el');
      const resetBtn = document.createElement('button');
      resetBtn.type = 'button';
      resetBtn.className = 'danger';
      resetBtn.textContent = text('messages.import_geocode_reset', 'Import visszaállítása');
      const closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'ghost';
      closeBtn.textContent = text('messages.import_geocode_skip_city', 'Bezárás');

      let finished = false;
      const escListener = (ev)=>{
        if (ev.key === 'Escape') {
          ev.preventDefault();
          cleanup('dismiss');
        }
      };

      const cleanup = (result)=>{
        if (finished) return;
        finished = true;
        document.removeEventListener('keydown', escListener);
        overlay.remove();
        resolve(result);
      };

      copyBtn.addEventListener('click', async ()=>{
        if (copyBtn.disabled) return;
        try {
          copyBtn.disabled = true;
          await (typeof options.onCopy === 'function'
            ? options.onCopy(failures)
            : copyImportFailuresToClipboard(failures));
          const successLabel = text('messages.import_geocode_copy_success', 'Címek a vágólapra kerültek');
          const originalLabel = text('messages.import_geocode_copy', 'Címek másolása');
          copyBtn.classList.add('success');
          copyBtn.textContent = successLabel;
          setTimeout(()=>{
            copyBtn.classList.remove('success');
            copyBtn.textContent = originalLabel;
            copyBtn.disabled = false;
          }, 2200);
        } catch (err) {
          console.error('copy geocode failures', err);
          copyBtn.disabled = false;
          alert(text('messages.import_geocode_copy_error', 'Nem sikerült a másolás.'));
        }
      });
      skipBtn.addEventListener('click', ()=> cleanup('skip'));
      useCityBtn.addEventListener('click', ()=> cleanup('city'));
      resetBtn.addEventListener('click', ()=> cleanup('reset'));
      if (options.allowReset === false) {
        resetBtn.disabled = true;
      }
      closeBtn.addEventListener('click', ()=> cleanup('dismiss'));
      overlay.addEventListener('click', (event)=>{ if (event.target === overlay) cleanup('dismiss'); });
      document.addEventListener('keydown', escListener);

      buttonRow.appendChild(copyBtn);
      buttonRow.appendChild(skipBtn);
      buttonRow.appendChild(useCityBtn);
      buttonRow.appendChild(resetBtn);
      buttonRow.appendChild(closeBtn);

      box.appendChild(textWrap);
      box.appendChild(listTitle);
      box.appendChild(list);
      box.appendChild(buttonRow);
      overlay.appendChild(box);
      document.body.appendChild(overlay);
      useCityBtn.focus();
    });
  }

  async function geocodeFailuresByCity(failures){
    const validEntries = Array.isArray(failures) ? failures.filter(entry => Number.isInteger(entry.index)) : [];
    if (!validEntries.length) {
      return {changed:false, saveOk:true, attempted:0, failed:0, success:0};
    }
    pushSnapshot();
    const addressFieldId = getAddressFieldId();
    let changed = false;
    let attempted = 0;
    let failed = 0;
    let success = 0;
    for (const entry of validEntries) {
      const idx = entry.index;
      const item = state.items[idx];
      if (!item || typeof item !== 'object') continue;
      const cityCandidate = (entry.city && entry.city.trim()) || (entry.fallbackCity && entry.fallbackCity.trim());
      if (!cityCandidate) {
        failed += 1;
        continue;
      }
      attempted += 1;
      const updated = {...item};
      updated[addressFieldId] = cityCandidate;
      updated.city = cityCandidate;
      updated.lat = null;
      updated.lon = null;
      try {
        const geo = await geocodeRobust(cityCandidate);
        updated.lat = geo.lat;
        updated.lon = geo.lon;
        if (geo.city) {
          updated.city = geo.city;
        }
        success += 1;
      } catch (err) {
        console.error('city-level geocode failed for import', err);
        failed += 1;
      }
      state.items[idx] = updated;
      changed = true;
    }
    let saveOk = true;
    if (changed) {
      saveOk = await saveAll();
    }
    return {changed, saveOk, attempted, failed, success};
  }

  const toolbarMenuToggle = document.getElementById('toolbarMenuToggle');
  const toolbarMenu = document.getElementById('toolbarMenu');
  if (toolbarMenuToggle && toolbarMenu) {
    const closeToolbarMenu = ()=>{
      toolbarMenu.hidden = true;
      toolbarMenuToggle.setAttribute('aria-expanded', 'false');
    };
    const openToolbarMenu = ()=>{
      toolbarMenu.hidden = false;
      toolbarMenuToggle.setAttribute('aria-expanded', 'true');
    };
    toolbarMenuToggle.addEventListener('click', event => {
      event.stopPropagation();
      const isExpanded = toolbarMenuToggle.getAttribute('aria-expanded') === 'true';
      if (isExpanded) {
        closeToolbarMenu();
        toolbarMenuToggle.focus();
      } else {
        openToolbarMenu();
        const firstFocusable = toolbarMenu.querySelector('button:not([disabled])');
        if (firstFocusable instanceof HTMLElement) firstFocusable.focus();
      }
    });
    toolbarMenu.addEventListener('click', event => {
      const target = event.target instanceof Element ? event.target.closest('button') : null;
      if (target) {
        closeToolbarMenu();
        toolbarMenuToggle.focus();
      }
      event.stopPropagation();
    });
    document.addEventListener('click', event => {
      if (toolbarMenu.hidden) return;
      if (event.target instanceof Node && (toolbarMenu.contains(event.target) || toolbarMenuToggle.contains(event.target))) {
        return;
      }
      closeToolbarMenu();
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && toolbarMenuToggle.getAttribute('aria-expanded') === 'true') {
        closeToolbarMenu();
        toolbarMenuToggle.focus();
      }
    });
    toolbarMenuToggle.addEventListener('keydown', event => {
      if (event.key === 'ArrowDown' && toolbarMenu.hidden) {
        event.preventDefault();
        openToolbarMenu();
        const firstFocusable = toolbarMenu.querySelector('button:not([disabled])');
        if (firstFocusable instanceof HTMLElement) firstFocusable.focus();
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
      captureImportRollbackSnapshot();
      importBtn.disabled = true;
      let loaderCtrl = null;
      const ensureLoaderRemoved = ()=>{
        if (loaderCtrl) {
          loaderCtrl.remove();
          loaderCtrl = null;
        }
      };
      try {
        const form = new FormData();
        form.append('file', file);
        form.append('mode', mode);
        const batchId = makeBatchId();
        if (state.changeWatcher) state.changeWatcher.registerBatch(batchId);
        let resp;
        try {
          const headers = buildHeaders();
          const requestId = makeRequestId();
          headers.set('X-Request-ID', requestId);
          headers.set('X-Batch-ID', batchId);
          loaderCtrl = showImportProgressOverlay(text('messages.import_in_progress', 'Import folyamatban…'));
          resp = await fetch(EP.importCsv, {method:'POST', headers, body: form});
        } finally {
          if (state.changeWatcher) state.changeWatcher.unregisterBatch(batchId);
        }
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
        const revNum = Number(data?.rev);
        if (Number.isFinite(revNum)) {
          updateKnownRevision(revNum);
          resetForeignRevisions();
          changeNotice.hide();
        }
        applyLoadedData(data);
        history.length = 0;
        const geo = await autoGeocodeImported(importedIds);
        renderEverything();
        if (!geo.changed) {
          showSaveStatus(true);
        } else if (!geo.saveOk) {
          showSaveStatus(false);
        }
        ensureLoaderRemoved();
        let successMsg = text('messages.import_success', 'Import kész.');
        if (geo.failed > 0 && geo.attempted > 0) {
          const tpl = text('messages.import_geocode_partial', 'Figyelem: {count} címet nem sikerült automatikusan térképre tenni.');
          successMsg += `\n\n${format(tpl, {count: geo.failed})}`;
          const decision = await showGeocodeFailureDialog(successMsg, geo.failures, {
            allowReset: hasImportRollbackSnapshot()
          });
          if (decision === 'city') {
            loaderCtrl = showImportProgressOverlay(text('messages.import_city_fallback_progress', 'Települések geokódolása…'));
            const fallback = await geocodeFailuresByCity(geo.failures);
            ensureLoaderRemoved();
            renderEverything();
            if (fallback.changed && !fallback.saveOk) {
              showSaveStatus(false);
            }
            const resultTpl = text('messages.import_city_fallback_result', 'Település-alapú geokódolás – siker: {success}, sikertelen: {failed}.');
            alert(format(resultTpl, {
              success: fallback.success ?? 0,
              failed: fallback.failed ?? 0
            }));
          } else if (decision === 'skip') {
            loaderCtrl = showImportProgressOverlay(text('messages.import_skip_progress', 'Címek eltávolítása…'));
            const dropped = await dropImportFailures(geo.failures);
            ensureLoaderRemoved();
            if (!dropped.changed) {
              alert(text('messages.import_skip_none', 'Nem történt módosítás.'));
            } else {
              if (!dropped.saveOk) {
                alert(text('messages.import_skip_error', 'A címek eltávolítása nem mentődött el teljesen.'));
              } else {
                const skipTpl = text('messages.import_skip_result', '{count} cím kihagyva az importból.');
                alert(format(skipTpl, {count: dropped.removed ?? 0}));
              }
            }
          } else if (decision === 'reset') {
            loaderCtrl = showImportProgressOverlay(text('messages.import_reset_progress', 'Import visszaállítása…'));
            const resetResult = await restoreImportRollbackSnapshot();
            ensureLoaderRemoved();
            if (!resetResult.restored) {
              alert(text('messages.import_reset_missing', 'Az eredeti adatok nem érhetők el.'));
            } else if (!resetResult.saveOk) {
              alert(text('messages.import_reset_error', 'Az import visszaállítása nem sikerült teljesen.'));
            } else {
              alert(text('messages.import_reset_success', 'Az import visszaállítása megtörtént.'));
            }
          }
        } else {
          alert(successMsg);
        }
        clearImportRollbackSnapshot();
      } catch (e) {
        console.error(e);
        showSaveStatus(false);
        ensureLoaderRemoved();
        let msg = text('messages.import_error', 'Az importálás nem sikerült.');
        const extra = e && (e.detail || e.message);
        if (extra && typeof extra === 'string' && extra.trim() && !['import_failed','bad_json'].includes(extra)) {
          msg += `\n\n${extra.trim()}`;
        }
        alert(msg);
        clearImportRollbackSnapshot();
      } finally {
        ensureLoaderRemoved();
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

      await ensureClientId();
      // load data
      await loadAll();
      initChangeWatcher();

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
