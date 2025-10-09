/* global L */
(function(){
  const EP = window.APP_BOOTSTRAP?.endpoints || {};
  const state = {
    cfg: null,
    items: [],                 // {id,label,address,city,note,lat,lon,round,weight,volume,collapsed,_pendingRound}
    markersById: new Map(),
    rowsById: new Map(),
    ORIGIN: {lat:47.4500, lon:19.3500}, // Maglód tartalék
    filterText: ''
  };

  // ======= UNDO (max 3 lépés)
  const history = [];
  const UNDO_LIMIT = 3;
  const undoBtn = document.getElementById('undoBtn');

  function pushSnapshot() {
    const snap = JSON.parse(JSON.stringify(state.items));
    if (history.length >= UNDO_LIMIT) history.shift();
    history.push(snap);
    updateUndoButton();
  }
  function canUndo(){ return history.length > 0; }
  function updateUndoButton(){
    if (!undoBtn) return;
    undoBtn.disabled = !canUndo();
    undoBtn.title = canUndo() ? 'Visszavonás' : 'Nincs visszavonható művelet';
  }
  async function doUndo(){
    if (!canUndo()) return;
    const prev = history.pop();
    state.items = prev;
    await saveAll();
    renderEverything();
    updateUndoButton();
  }
  if (undoBtn){
    undoBtn.addEventListener('click', doUndo);
    updateUndoButton();
  }

  // ======= DOM
  const groupsEl = document.getElementById('groups');
  const pinCountEl = document.getElementById('pinCount');
  const themeToggle = document.getElementById('themeToggle');

  // ======= THEME
  (function initTheme(){
    const root = document.documentElement;
    const saved = localStorage.getItem('fuvar_theme');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    root.classList.toggle('dark', saved ? (saved==='dark') : prefersDark);
    if (themeToggle) {
      themeToggle.addEventListener('click', ()=>{
        root.classList.toggle('dark');
        localStorage.setItem('fuvar_theme', root.classList.contains('dark') ? 'dark' : 'light');
      });
    }
  })();

  // ======= MAP
  const map = L.map('map',{zoomControl:true, preferCanvas:true});
  const markerLayer = L.featureGroup().addTo(map);
  function updatePinCount(){ pinCountEl.textContent = markerLayer.getLayers().length.toString(); }

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
    const textCol = idealTextColor(hex);
    const sz = state.cfg.ui.marker.icon_size || 38;
    const fsz = state.cfg.ui.marker.font_size || 14;
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
    const wOk = (typeof it.weight === 'number' && !isNaN(it.weight));
    const vOk = (typeof it.volume === 'number' && !isNaN(it.volume));
    if (!wOk && !vOk) {
      return `<span class="warn" title="Hiányzó súly és térfogat" style="color:#ef4444;font-weight:800;">!</span>`;
    }
    const parts = [];
    if (wOk) parts.push(`${it.weight.toFixed(1)} kg`);
    if (vOk) parts.push(`${it.volume.toFixed(2)} m³`);
    return `<span class="vals" style="color:#374151;font-weight:600;font-size:12px;">${parts.join(' · ')}</span>`;
  }
  function updateRowHeaderMeta(row, it){
    const meta = row.querySelector('[data-meta]');
    if (meta) meta.innerHTML = metaHTML(it);
  }

  // ======= SAVE STATUS (pill a jobb felső sarokban)
  let savePillEl=null, savePillTimer=null;
  function ensureSavePill(){
    if (savePillEl) return savePillEl;
    const el = document.createElement('div');
    el.id = 'saveStatusPill';
    el.style.position = 'fixed';
    el.style.top = '10px';
    el.style.right = '12px';
    el.style.zIndex = '9999';
    el.style.padding = '6px 10px';
    el.style.borderRadius = '999px';
    el.style.fontSize = '12px';
    el.style.fontWeight = '600';
    el.style.boxShadow = '0 2px 6px rgba(0,0,0,.15)';
    el.style.transition = 'opacity .25s ease';
    el.style.opacity = '0';
    el.style.pointerEvents = 'none';
    document.body.appendChild(el);
    savePillEl = el;
    return el;
  }
  function showSaveStatus(ok){
    const el = ensureSavePill();
    el.textContent = ok ? 'Mentve ✓' : 'Mentés sikertelen ✗';
    el.style.color = ok ? '#065f46' : '#7f1d1d';
    el.style.background = ok ? '#d1fae5' : '#fee2e2';
    el.style.border = ok ? '1px solid #a7f3d0' : '1px solid #fecaca';
    el.style.opacity = '1';
    clearTimeout(savePillTimer);
    savePillTimer = setTimeout(()=>{ el.style.opacity='0'; }, 1600);
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
  async function loadAll(){
    const j = await fetchJSON(EP.load);
    state.items = Array.isArray(j.items)? j.items : [];
  }
  async function saveAll(){
    try{
      const payload = JSON.stringify(state.items);
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

  // ======= AUTO SORT (kör + körön belül távolság)
  function autoSortItems(){
    if (!state.cfg.app.auto_sort_by_round) return;
    const zeroBottom = !!state.cfg.app.round_zero_at_bottom;
    const withIdx = state.items.map((it, idx)=>({it, idx}));
    withIdx.sort((a,b)=>{
      const ra = +a.it.round || 0, rb = +b.it.round || 0;
      if (zeroBottom) {
        const az=(ra===0)?1:0, bz=(rb===0)?1:0;
        if (az!==bz) return az-bz;
      }
      if (ra!==rb) return ra-rb;
      const da = haversineKm(state.ORIGIN.lat,state.ORIGIN.lon, a.it.lat, a.it.lon);
      const db = haversineKm(state.ORIGIN.lat,state.ORIGIN.lon, b.it.lat, b.it.lon);
      if (isFinite(da) || isFinite(db)){
        if (!isFinite(da)) return 1;
        if (!isFinite(db)) return -1;
        if (da!==db) return da-db;
      }
      return a.idx-b.idx;
    });
    state.items = withIdx.map(x=>x.it);
  }

  // ======= ROUNDS
  let ROUND_MAP, ROUND_ORDER;
  const roundLabel = (r)=> (ROUND_MAP.get(Number(r))?.label) ?? String(r);
  const colorForRound = (r)=> (ROUND_MAP.get(Number(r))?.color) ?? '#374151';

  function hasBlankInDefaultRound(){
    return state.items.some(it => ((+it.round||0)===0) && !it.address && it.lat==null && it.lon==null);
  }
  function ensureBlankRowInDefaultRound(){
    if (!hasBlankInDefaultRound()){
      state.items.push({
        id:'row_'+Math.random().toString(36).slice(2),
        label:'', address:'', city:'', note:'',
        weight:null, volume:null,
        lat:null, lon:null,
        round:0, collapsed:state.cfg.app.default_collapsed
      });
    }
  }

  function totalsForRound(rid){
    let w=0, v=0;
    state.items.forEach(it=>{
      if ((+it.round||0)!==rid) return;
      if (typeof it.weight==='number' && !isNaN(it.weight)) w += it.weight;
      if (typeof it.volume==='number' && !isNaN(it.volume)) v += it.volume;
    });
    return {w, v};
  }

  function makeGroupHeader(rid, totals){
    const g = document.createElement('div');
    g.className = 'group';
    const color = colorForRound(rid);
    const sumTxt = `Összesen: ${Number.isFinite(totals.w)?totals.w.toFixed(1):'0.0'} kg · ${Number.isFinite(totals.v)?totals.v.toFixed(1):'0.0'} m³`;
    g.innerHTML = `
      <div class="group-header" data-group-header="${rid}">
        <div class="group-title">
          <span style="display:inline-block;width:12px;height:12px;border-radius:999px;background:${color};border:1px solid #d1d5db;margin-right:8px;vertical-align:middle"></span>
          ${esc(roundLabel(rid))}
          <span class="__sum" style="margin-left:8px;color:#6b7280;font-weight:600;font-size:12px;">${sumTxt}</span>
        </div>
        <div class="group-tools">
          <button class="iconbtn grp-open" data-round="${rid}">Kinyit</button>
          <button class="iconbtn grp-close" data-round="${rid}">Összezár</button>
          <button class="iconbtn grp-print" data-round="${rid}">Nyomtatás (kör)</button>
          <button class="iconbtn grp-export" data-round="${rid}">Export (kör)</button>
          <button class="iconbtn grp-nav" data-round="${rid}">Navigáció (GMaps)</button>
          <button class="iconbtn grp-del" data-round="${rid}" style="border-color:#fecaca;background:rgba(248,113,113,0.12);">Kör törlése</button>
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
    span.textContent = `Összesen: ${t.w.toFixed(1)} kg · ${t.v.toFixed(1)} m³`;
  }

  function refreshDeleteButtonState(row, it){
    const delBtn = row.querySelector('.del');
    const notSaved = (it.lat==null || it.lon==null);
    const isDefaultBlank = (+it.round||0)===0 && notSaved && !it.address;
    delBtn.disabled = isDefaultBlank;
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
        pingMarker(it.id); // fókusz szinkron: pin ping
        const row = state.rowsById.get(it.id);
        if (row){
          row.scrollIntoView({behavior:'smooth', block:'center'});
          row.classList.add('active'); setTimeout(()=>row.classList.remove('active'), 1000);
          const body = row.querySelector('.body'); const tog = row.querySelector('.toggle');
          if (body && body.style.display==='none'){ body.style.display=''; if (tog) tog.textContent='▼'; }
          const first = row.querySelector('input,select'); if (first){ first.focus(); }
        }
      });
      state.markersById.set(it.id, mk);
    } else mk.setLatLng([it.lat, it.lon]);
    mk.setIcon(icon);
    const extras = [];
    if (it.weight!=null && it.weight!=='') extras.push(`${Number(it.weight)} kg`);
    if (it.volume!=null && it.volume!=='') extras.push(`${Number(it.volume)} m³`);
    const html = `
      <div style="font-size:14px;line-height:1.35;">
        ${it.label ? `<div style="font-weight:600">${esc(it.label)}</div>` : ''}
        <div style="color:#4b5563">${esc(it.address||'')}</div>
        ${extras.length? `<div style="margin-top:4px;color:#334155">${extras.join(' · ')}</div>` : ''}
        ${it.note ? `<div style="margin-top:4px;">${esc(it.note)}</div>` : ''}
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
    const it = state.items.find(x=>x.id===id);
    if (!it || it.lat==null || it.lon==null) return;
    const color = colorForRound(+it.round||0);
    const c = L.circle([it.lat, it.lon], {radius: 80, color, weight: 2, fillColor: color, fillOpacity: 0.25, opacity: 0.8});
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
    const addrI = row?.querySelector('#addr_'+cssId(id));
    const labelI= row?.querySelector('#label_'+cssId(id));
    const weightI= row?.querySelector('#weight_'+cssId(id));
    const volumeI= row?.querySelector('#volume_'+cssId(id));
    const address = (addrI ? addrI.value : it.address || '').trim();
    if (!address){ alert('Adj meg teljes címet!'); if (addrI) addrI.focus(); return; }
    if (okBtn){ okBtn.disabled=true; okBtn.textContent='...'; }
    try{
      const g = await geocodeRobust(address);
      const newRound = (overrideRound!=null) ? overrideRound : (typeof it._pendingRound!=='undefined' ? it._pendingRound : it.round);
      pushSnapshot();
      state.items[idx] = {
        ...it,
        address,
        label: (labelI ? labelI.value : it.label || '').trim(),
        city: g.city || cityFromDisplay(address, it.city),
        weight: weightI ? (weightI.value.trim()===''?null:parseFloat(weightI.value)) : it.weight ?? null,
        volume: volumeI ? (volumeI.value.trim()===''?null:parseFloat(volumeI.value)) : it.volume ?? null,
        lat: g.lat, lon: g.lon,
        round: +newRound || 0
      };
      delete state.items[idx]._pendingRound;
      upsertMarker(state.items[idx], idx);
      ensureBlankRowInDefaultRound();
      await saveAll();
      if (row) updateRowHeaderMeta(row, state.items[idx]);
      renderEverything();
    } finally{
      if (okBtn){ okBtn.disabled=false; okBtn.textContent='OK'; }
    }
  }

  function renderRow(it, globalIndex){
    const city = cityFromDisplay(it.address, it.city);
    const row = document.createElement('div');
    row.className = 'row';
    row.dataset.rowId = it.id;
    const collapsed = (typeof it.collapsed==='boolean') ? it.collapsed : !!state.cfg.app.default_collapsed;
    const roundColor = colorForRound(+it.round||0);
    const numTextColor = idealTextColor(roundColor);

    const wVal = (it.weight ?? '');
    const vVal = (it.volume ?? '');

    row.innerHTML = `
      <div class="header">
        <div class="num" data-num style="background:${roundColor}; color:${numTextColor}">${String(globalIndex+1).padStart(2,'0')}</div>
        <div class="city" data-city title="${esc(city||'—')}">${esc(city || '—')}</div>
        <div class="meta" data-meta style="margin-left:auto;margin-right:8px;"></div>
        <div class="tools"><button class="iconbtn toggle" title="${collapsed?'Kinyit':'Összezár'}">${collapsed?'▶':'▼'}</button></div>
      </div>
      <div class="body" style="${collapsed?'display:none':''}">
        <div class="grid3">
          <div class="f">
            <label for="label_${cssId(it.id)}">Címke</label>
            <input id="label_${cssId(it.id)}" type="text" value="${esc(it.label||'')}" placeholder="pl. Ügyfél neve / kód">
          </div>
          <div class="f">
            <label for="addr_${cssId(it.id)}">Teljes cím</label>
            <input id="addr_${cssId(it.id)}" type="text" value="${esc(it.address||'')}" placeholder="pl. 2234 Maglód, Fő utca 1.">
          </div>
          ${state.cfg.ui.show_note_field ? `
          <div class="f">
            <label for="note_${cssId(it.id)}">Megjegyzés</label>
            <input id="note_${cssId(it.id)}" type="text" value="${esc(it.note||'')}" placeholder="időablak, kapucsengő, stb.">
          </div>` : `<div></div>`}
        </div>
        <div class="grid3" style="align-items:end;">
          <div class="f" style="max-width:160px">
            <label for="round_${cssId(it.id)}">Kör</label>
            <select id="round_${cssId(it.id)}" class="select-round">
              ${Array.from(ROUND_MAP.values()).map(r => {
                const sel = (+it.round===+r.id) ? 'selected' : '';
                return `<option value="${r.id}" ${sel}>${esc(r.label)}</option>`;
              }).join('')}
            </select>
          </div>
          <div class="f">
            <label for="weight_${cssId(it.id)}">Súly (kg)</label>
            <input id="weight_${cssId(it.id)}" type="number" step="0.1" min="0" value="${wVal!==''?esc(wVal):''}" placeholder="pl. 12.5">
          </div>
          <div class="f">
            <label for="volume_${cssId(it.id)}">Térfogat (m³)</label>
            <input id="volume_${cssId(it.id)}" type="number" step="0.01" min="0" value="${vVal!==''?esc(vVal):''}" placeholder="pl. 0.80">
          </div>
        </div>
        <div class="grid" style="margin-top:6px;">
          <div></div>
          <div class="btns">
            <button class="ok">OK</button>
            <button class="del">Törlés</button>
          </div>
        </div>
      </div>
    `;

    // Lista → Térkép fókusz: kattintás és fókusz események
    row.addEventListener('click', (e) => {
      if ((e.target instanceof HTMLElement) && e.target.classList.contains('iconbtn')) return;
      highlightRow(it.id);
      const mk = state.markersById.get(it.id);
      if (mk) { mk.openPopup(); map.panTo(mk.getLatLng()); pingMarker(it.id); }
    });
    row.addEventListener('focusin', ()=>{
      // bármely input/elem fókuszba kerül a soron belül → pin ping + popup
      const mk = state.markersById.get(it.id);
      if (mk) { mk.openPopup(); pingMarker(it.id); }
    });

    row.querySelector('.toggle').addEventListener('click', (e)=>{
      e.stopPropagation();
      const body = row.querySelector('.body');
      const btn  = e.currentTarget;
      const hidden = body.style.display === 'none';
      body.style.display = hidden ? '' : 'none';
      btn.textContent = hidden ? '▼' : '▶';
      const idx = state.items.findIndex(x => x.id===it.id);
      if (idx>=0){ pushSnapshot(); state.items[idx].collapsed = !hidden; saveAll(); }
    });

    const labelI = row.querySelector('#label_'+cssId(it.id));
    const addrI  = row.querySelector('#addr_'+cssId(it.id));
    const noteI  = row.querySelector('#note_'+cssId(it.id));
    const roundS = row.querySelector('#round_'+cssId(it.id));
    const weightI= row.querySelector('#weight_'+cssId(it.id));
    const volumeI= row.querySelector('#volume_'+cssId(it.id));
    const okBtn  = row.querySelector('.ok');
    const delBtn = row.querySelector('.del');

    [labelI, addrI].forEach(inp => {
      inp.addEventListener('change', ()=>{
        const idx = state.items.findIndex(x=>x.id===it.id);
        if (idx<0) return;
        pushSnapshot();
        state.items[idx].label = labelI.value.trim();
        state.items[idx].address = addrI.value.trim();
        const cityNow = cityFromDisplay(state.items[idx].address, state.items[idx].city);
        state.items[idx].city = cityNow;
        row.querySelector('[data-city]').textContent = cityNow || '—';
        saveAll();
        renderGroupHeaderTotalsForRound(+state.items[idx].round||0);
        updateRowHeaderMeta(row, state.items[idx]);
        refreshDeleteButtonState(row, state.items[idx]);
      });
    });
    if (noteI) noteI.addEventListener('change', ()=>{
      const idx = state.items.findIndex(x=>x.id===it.id);
      if (idx<0) return;
      pushSnapshot();
      state.items[idx].note = noteI.value.trim();
      saveAll();
    });

    [weightI, volumeI].forEach(inp=>{
      inp.addEventListener('change', ()=>{
        const idx = state.items.findIndex(x=>x.id===it.id);
        if (idx<0) return;
        pushSnapshot();
        const v = inp.value.trim();
        state.items[idx][inp===weightI?'weight':'volume'] = v==='' ? null : parseFloat(v);
        saveAll();
        renderGroupHeaderTotalsForRound(+state.items[idx].round||0);
        updateRowHeaderMeta(row, state.items[idx]);
      });
    });

    roundS.addEventListener('change', async ()=>{
      const idx = state.items.findIndex(x=>x.id===it.id); if (idx<0) return;
      const selRound = +roundS.value;
      const hasAddress = !!state.items[idx].address?.trim();
      const hasPin = (state.items[idx].lat!=null && state.items[idx].lon!=null);

      if (!hasAddress) { state.items[idx]._pendingRound = selRound; return; }
      if (!hasPin) {
        try{ await doOk(state.items[idx].id, selRound); }
        catch(e){ console.error(e); alert('Geokódolás sikertelen.'); roundS.value = String(state.items[idx].round ?? 0); }
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

    okBtn.addEventListener('click', async ()=>{ try{ await doOk(it.id, null); } catch(e){ console.error(e); alert('Geokódolás sikertelen. Próbáld pontosítani a címet.'); } });

    delBtn.addEventListener('click', ()=>{
      if (delBtn.disabled) return;
      const idx = state.items.findIndex(x=>x.id===it.id);
      if (idx>=0) {
        pushSnapshot();
        const rPrev = +state.items[idx].round||0;
        state.items.splice(idx,1);
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
    // csak egyszer
    if (document.getElementById('quickSearchWrap')) return;
    const wrap = document.createElement('div');
    wrap.id = 'quickSearchWrap';
    wrap.style.display = 'flex';
    wrap.style.gap = '6px';
    wrap.style.alignItems = 'center';
    wrap.style.margin = '8px 8px 6px 8px';

    const inp = document.createElement('input');
    inp.id = 'quickSearch';
    inp.type = 'search';
    inp.placeholder = 'Keresés: címke, város, cím…';
    inp.style.width = '100%';
    inp.style.padding = '6px 8px';
    inp.style.border = '1px solid #d1d5db';
    inp.style.borderRadius = '8px';
    inp.autocomplete = 'off';

    const clearBtn = document.createElement('button');
    clearBtn.textContent = '✕';
    clearBtn.title = 'Szűrés törlése';
    clearBtn.style.padding = '6px 10px';
    clearBtn.style.border = '1px solid #d1d5db';
    clearBtn.style.borderRadius = '8px';
    clearBtn.style.background = '#f3f4f6';
    clearBtn.style.cursor = 'pointer';

    wrap.appendChild(inp);
    wrap.appendChild(clearBtn);

    // a groupsEl elé tesszük
    const p = groupsEl.parentNode;
    p.insertBefore(wrap, groupsEl);

    function applyFilter(){
      const q = (state.filterText || '').trim().toLowerCase();
      // sorok
      const rows = groupsEl.querySelectorAll('.row');
      rows.forEach(row=>{
        const id = row.dataset.rowId;
        const it = state.items.find(x=>x.id===id);
        if (!it){ row.style.display=''; return; }
        const hay = `${it.label||''} ${it.city||''} ${it.address||''}`.toLowerCase();
        const match = !q || hay.includes(q);
        row.style.display = match ? '' : 'none';
      });
      // körök: ha egy körben nincs látható sor → rejt
      groupsEl.querySelectorAll('.group').forEach(g=>{
        const body = g.querySelector('.group-body');
        const anyVisible = Array.from(body.children).some(ch => ch.classList.contains('row') && ch.style.display!=='none');
        g.style.display = anyVisible ? '' : 'none';
      });
    }

    inp.addEventListener('input', ()=>{
      state.filterText = inp.value;
      applyFilter();
    });
    clearBtn.addEventListener('click', ()=>{
      state.filterText = '';
      inp.value = '';
      applyFilter();
    });

    // első render után is alkalmazzuk, ha lenne mentett filter
    if (state.filterText) {
      inp.value = state.filterText;
      applyFilter();
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

      groupEl.querySelector('.grp-open').addEventListener('click', ()=>{
        body.querySelectorAll('.body').forEach(b=>b.style.display='');
        pushSnapshot();
        state.items.filter(it => (+it.round||0)===rid).forEach(it=>{ it.collapsed = false; });
        saveAll();
      });
      groupEl.querySelector('.grp-close').addEventListener('click', ()=>{
        body.querySelectorAll('.body').forEach(b=>b.style.display='none');
        pushSnapshot();
        state.items.filter(it => (+it.round||0)===rid).forEach(it=>{ it.collapsed = true; });
        saveAll();
      });
      groupEl.querySelector('.grp-export').addEventListener('click', ()=>{ window.open(EP.exportRound(rid), '_blank'); });
      groupEl.querySelector('.grp-print').addEventListener('click', ()=>{ window.open(EP.printRound(rid), '_blank'); });

      groupEl.querySelector('.grp-del').addEventListener('click', async ()=>{
        const name = (ROUND_MAP.get(rid)?.label) || String(rid);
        if (!confirm(`Biztosan törlöd a(z) "${name}" kör összes címét?`)) return;
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
          removedIds.forEach(id=>{
            const mk = state.markersById.get(id);
            if (mk){ markerLayer.removeLayer(mk); state.markersById.delete(id); }
          });

          ensureBlankRowInDefaultRound();
          await saveAll();
          renderEverything();
          alert(`Kör törölve. Tételek: ${j?.deleted ?? removedIds.length}.`);
        }catch(e){ console.error(e); alert('A kör törlése nem sikerült.'); }
      });

      groupEl.querySelector('.grp-nav').addEventListener('click', ()=>{ openGmapsForRound(rid); });

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
    const origin = state.cfg.routing?.origin || 'Maglód';
    const maxW = state.cfg.routing?.max_waypoints || 10;
    const pts = state.items.filter(it => (+it.round||0)===rid && it.lat!=null && it.lon!=null);
    if (pts.length===0){ alert('Nincs navigálható cím ebben a körben.'); return; }
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
    if (skipped>0) alert(`Figyelem: ${skipped} cím nem került bele (nincs geolokáció).`);
  }

  // ======= GLOBAL BUTTONS
  document.getElementById('exportBtn').addEventListener('click', ()=>{ window.open(EP.exportAll, '_blank'); });
  document.getElementById('printBtn').addEventListener('click', ()=>{ window.open(EP.printAll, '_blank'); });
  document.getElementById('downloadArchiveBtn').addEventListener('click', ()=>{ window.open(EP.downloadArchive, '_blank'); });
  document.getElementById('expandAll').addEventListener('click', ()=>{
    groupsEl.querySelectorAll('.body').forEach(b=>b.style.display='');
    pushSnapshot();
    state.items.forEach(it=>it.collapsed=false); saveAll();
  });
  document.getElementById('collapseAll').addEventListener('click', ()=>{
    groupsEl.querySelectorAll('.body').forEach(b=>b.style.display='none');
    pushSnapshot();
    state.items.forEach(it=>it.collapsed=true); saveAll();
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
      await loadCfg();

      // tile layer
      L.tileLayer(state.cfg.map.tiles.url,{maxZoom:19, attribution:state.cfg.map.tiles.attribution}).addTo(map);
      if (state.cfg.map.fit_bounds) {
        const b = L.latLngBounds(state.cfg.map.fit_bounds);
        map.fitBounds(b.pad(0.15));
        map.setMaxBounds(b.pad(state.cfg.map.max_bounds_pad || 0.6));
        map.on('drag', ()=> map.panInsideBounds(map.options.maxBounds,{animate:false}));
      }

      // rounds
      ROUND_MAP = new Map(state.cfg.rounds.map(r=>[Number(r.id), r]));

      // origin geocode cache
      try{
        const r = await fetch(EP.geocode + '&' + new URLSearchParams({q:'Maglód'}), {cache:'force-cache'});
        if (r.ok){
          const j = await r.json();
          if (j && j.lat && j.lon){ state.ORIGIN = {lat:j.lat, lon:j.lon}; }
        }
      }catch(e){}

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
      alert('Betöltési hiba: kérlek frissítsd az oldalt.');
    }
  })();

})();
