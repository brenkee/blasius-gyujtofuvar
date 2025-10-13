const state = {
  basePath: computeBasePath(),
  apiUrl: null,
  config: null,
  original: null,
  originalHash: '',
  serverHash: '',
  latestRemoteHash: '',
  dirtyPaths: new Set(),
  invalidPaths: new Set(),
  saving: false,
  restoring: false,
  loading: false,
  metaPoller: null,
  backupAvailable: false,
  remoteChangedNotified: false,
  lastUpdatedAt: null,
  lastLoadedAt: null,
};

state.apiUrl = joinBase(state.basePath, 'api/admin/update-config.php');

const elements = {
  root: document.getElementById('settingsRoot'),
  saveBtn: document.getElementById('saveBtn'),
  resetBtn: document.getElementById('resetBtn'),
  refreshBtn: document.getElementById('refreshBtn'),
  statusBanner: document.getElementById('statusBanner'),
  metaInfo: document.getElementById('metaInfo'),
  toastHost: document.getElementById('toastHost'),
};

const COOKIE_CSRF = 'GF-CSRF';

init();

function init() {
  bindEvents();
  updateButtons();
  loadConfig();
  startMetaPolling();
  window.addEventListener('beforeunload', (event) => {
    if (state.dirtyPaths.size > 0) {
      event.preventDefault();
      event.returnValue = '';
    }
  });
}

function bindEvents() {
  if (elements.saveBtn) {
    elements.saveBtn.addEventListener('click', handleSave);
  }
  if (elements.resetBtn) {
    elements.resetBtn.addEventListener('click', handleRestore);
  }
  if (elements.refreshBtn) {
    elements.refreshBtn.addEventListener('click', () => {
      if (state.dirtyPaths.size > 0) {
        const confirmRefresh = window.confirm('Vannak mentetlen módosítások. Biztosan frissíted az adatokat?');
        if (!confirmRefresh) {
          return;
        }
      }
      loadConfig(true);
    });
  }
}

function computeBasePath() {
  const path = window.location.pathname || '/';
  const suffix = '/public/settings.html';
  if (path.endsWith(suffix)) {
    const base = path.slice(0, -suffix.length);
    return base.endsWith('/') ? base : base + '/';
  }
  const parts = path.split('/');
  parts.pop();
  const base = parts.join('/');
  if (!base || base === '') {
    return '/';
  }
  return base.endsWith('/') ? base : base + '/';
}

function joinBase(base, relative) {
  if (/^https?:/i.test(relative) || relative.startsWith('/')) {
    return relative;
  }
  if (!base.endsWith('/')) {
    base += '/';
  }
  return base + relative;
}

function loadConfig(isRefresh = false) {
  state.loading = true;
  updateButtons();
  if (!isRefresh) {
    renderLoading();
  }
  fetch(state.apiUrl, {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error('A konfiguráció betöltése sikertelen.');
      }
      return response.json();
    })
    .then((payload) => {
      if (!payload || payload.ok !== true || !isPlainObject(payload.config)) {
        throw new Error('Érvénytelen konfigurációs válasz.');
      }
      state.config = deepClone(payload.config);
      state.original = deepClone(payload.config);
      state.originalHash = payload.hash || '';
      state.serverHash = payload.hash || '';
      state.latestRemoteHash = payload.hash || '';
      state.backupAvailable = Boolean(payload.backupAvailable);
      state.lastUpdatedAt = payload.updatedAt || null;
      state.lastLoadedAt = new Date().toISOString();
      state.dirtyPaths.clear();
      state.invalidPaths.clear();
      state.remoteChangedNotified = false;
      hideBanner();
      renderConfig();
      updateDirtyIndicators();
      updateInvalidIndicators();
      updateButtons();
      updateMeta();
      if (isRefresh) {
        showToast('🔄 Beállítások frissítve', 'success');
      }
    })
    .catch((error) => {
      console.error(error);
      showToast(error.message || 'Nem sikerült betölteni a konfigurációt.', 'error');
      renderError(error.message || 'Nem sikerült betölteni a konfigurációt.');
    })
    .finally(() => {
      state.loading = false;
      updateButtons();
      updateResetState();
    });
}

function renderLoading() {
  if (!elements.root) return;
  elements.root.innerHTML = '';
  const wrapper = document.createElement('div');
  wrapper.className = 'card p-10 flex flex-col items-center justify-center text-center text-slate-500 dark:text-slate-300';
  wrapper.innerHTML = '<div class="animate-spin h-10 w-10 rounded-full border-4 border-slate-200 border-t-sky-500 mb-4"></div><p>Beállítások betöltése…</p>';
  elements.root.appendChild(wrapper);
}

function renderError(message) {
  if (!elements.root) return;
  elements.root.innerHTML = '';
  const wrapper = document.createElement('div');
  wrapper.className = 'card p-8 text-center text-red-600 dark:text-red-300';
  wrapper.textContent = message;
  elements.root.appendChild(wrapper);
}

function renderConfig() {
  if (!elements.root || !isPlainObject(state.config)) {
    return;
  }
  elements.root.innerHTML = '';
  Object.entries(state.config).forEach(([key, value]) => {
    const section = renderSection(key, value, [key]);
    if (section) {
      elements.root.appendChild(section);
    }
  });
  updateDirtyIndicators();
  updateInvalidIndicators();
}

function renderSection(key, value, path) {
  const section = document.createElement('section');
  section.className = 'card p-6 space-y-4';
  section.dataset.section = pathToString(path);

  const header = document.createElement('div');
  header.className = 'flex items-center justify-between gap-3';

  const title = document.createElement('h2');
  title.className = 'text-xl font-semibold text-slate-800 dark:text-slate-100';
  title.textContent = formatKey(key);
  header.appendChild(title);

  const body = document.createElement('div');
  body.className = 'space-y-4';

  if (isPlainObject(value) || Array.isArray(value)) {
    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'rounded-full border border-slate-300 dark:border-slate-700 px-3 py-1 text-xs font-medium text-slate-500 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition';
    toggleBtn.textContent = 'Összecsukás';
    toggleBtn.addEventListener('click', () => {
      body.classList.toggle('hidden');
      toggleBtn.textContent = body.classList.contains('hidden') ? 'Kinyitás' : 'Összecsukás';
    });
    header.appendChild(toggleBtn);
  }

  section.appendChild(header);
  section.appendChild(body);

  const content = renderField(key, value, path);
  if (content) {
    body.appendChild(content);
  }

  return section;
}

function renderField(key, value, path) {
  if (isPlainObject(value)) {
    return renderObjectField(key, value, path);
  }
  if (Array.isArray(value)) {
    return renderArrayField(key, value, path);
  }
  return renderPrimitiveField(key, value, path);
}

function renderObjectField(key, value, path) {
  const details = document.createElement('details');
  details.open = true;
  details.className = 'field-row space-y-3 bg-white/60 dark:bg-slate-900/30';
  details.dataset.fieldPath = pathToString(path);

  const summary = document.createElement('summary');
  summary.className = 'cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-100 flex items-center justify-between';
  const name = document.createElement('span');
  name.textContent = formatKey(path[path.length - 1]);
  const badge = document.createElement('span');
  badge.className = 'ml-2 text-xs text-slate-400 dark:text-slate-500';
  badge.textContent = 'objektum';
  summary.appendChild(name);
  summary.appendChild(badge);
  details.appendChild(summary);

  const container = document.createElement('div');
  container.className = 'mt-3 space-y-3 border-l border-slate-200/60 dark:border-slate-700/60 pl-4';
  Object.entries(value).forEach(([childKey, childValue]) => {
    const childPath = path.concat(childKey);
    const field = renderField(childKey, childValue, childPath);
    if (field) {
      container.appendChild(field);
    }
  });
  details.appendChild(container);
  return details;
}

function renderArrayField(key, value, path) {
  const wrapper = document.createElement('div');
  wrapper.className = 'field-row space-y-3 bg-white/60 dark:bg-slate-900/30';
  wrapper.dataset.fieldPath = pathToString(path);

  const header = document.createElement('div');
  header.className = 'flex flex-wrap items-center justify-between gap-2';

  const label = document.createElement('div');
  label.className = 'text-sm font-semibold text-slate-700 dark:text-slate-100';
  label.textContent = formatKey(path[path.length - 1]);
  header.appendChild(label);

  const controls = document.createElement('div');
  controls.className = 'flex items-center gap-2';

  const addBtn = document.createElement('button');
  addBtn.type = 'button';
  addBtn.className = 'inline-flex items-center gap-1 rounded-full border border-slate-300 dark:border-slate-700 px-3 py-1 text-xs font-medium text-slate-600 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition';
  addBtn.innerHTML = '<span aria-hidden="true">＋</span> Új elem';
  addBtn.addEventListener('click', () => {
    addArrayItem(path);
  });

  controls.appendChild(addBtn);
  header.appendChild(controls);
  wrapper.appendChild(header);

  const list = document.createElement('div');
  list.className = 'space-y-3';
  wrapper.appendChild(list);

  renderArrayItems(list, path);
  return wrapper;
}

function renderArrayItems(container, path) {
  container.innerHTML = '';
  const arr = getValueAtPath(state.config, path);
  if (!Array.isArray(arr) || arr.length === 0) {
    const empty = document.createElement('div');
    empty.className = 'text-xs text-slate-400 dark:text-slate-500 italic';
    empty.textContent = 'Nincs elem';
    container.appendChild(empty);
    return;
  }

  arr.forEach((item, index) => {
    const itemPath = path.concat(index);
    const itemWrapper = document.createElement('div');
    itemWrapper.className = 'array-item space-y-3';
    itemWrapper.dataset.fieldPath = pathToString(itemPath);

    const row = document.createElement('div');
    row.className = 'flex items-center justify-between gap-2';

    const title = document.createElement('div');
    title.className = 'text-sm font-medium text-slate-600 dark:text-slate-200';
    title.textContent = `${formatKey(path[path.length - 1])} #${index + 1}`;
    row.appendChild(title);

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'inline-flex items-center justify-center rounded-full border border-red-400 text-red-500 px-2 py-1 text-xs hover:bg-red-50 dark:hover:bg-red-900/40 transition';
    removeBtn.innerHTML = '<span aria-hidden="true">🗑️</span>';
    removeBtn.addEventListener('click', () => {
      removeArrayItem(path, index);
    });
    row.appendChild(removeBtn);
    itemWrapper.appendChild(row);

    if (isPlainObject(item)) {
      Object.entries(item).forEach(([childKey, childValue]) => {
        const childPath = itemPath.concat(childKey);
        const field = renderField(childKey, childValue, childPath);
        if (field) {
          itemWrapper.appendChild(field);
        }
      });
    } else if (Array.isArray(item)) {
      const field = renderField(String(index), item, itemPath);
      if (field) {
        itemWrapper.appendChild(field);
      }
    } else {
      const field = renderPrimitiveField(path[path.length - 1], item, itemPath, {
        labelText: `Elem #${index + 1}`,
      });
      if (field) {
        itemWrapper.appendChild(field);
      }
    }

    container.appendChild(itemWrapper);
  });
}

function renderPrimitiveField(key, value, path, options = {}) {
  const fieldId = pathToId(path);
  const wrapper = document.createElement('div');
  wrapper.className = 'field-row flex flex-col gap-2 bg-white/60 dark:bg-slate-900/30';
  wrapper.dataset.fieldPath = pathToString(path);

  const label = document.createElement('label');
  label.className = 'text-sm font-medium text-slate-600 dark:text-slate-200 flex items-center gap-2';
  label.setAttribute('for', fieldId);
  label.textContent = options.labelText || formatKey(path[path.length - 1]);
  wrapper.appendChild(label);

  if (options.showTypeBadge !== false) {
    const typeBadge = document.createElement('span');
    typeBadge.className = 'rounded-full border border-slate-300 dark:border-slate-700 px-2 py-0.5 text-[10px] uppercase tracking-wide text-slate-400 dark:text-slate-500';
    typeBadge.textContent = detectPrimitiveType(value);
    label.appendChild(typeBadge);
  }

  let control = null;

  if (typeof value === 'boolean') {
    control = createBooleanControl(fieldId, value, path);
  } else if (typeof value === 'number') {
    control = createNumberControl(fieldId, value, path);
  } else if (typeof value === 'string') {
    if (isColorField(path[path.length - 1], value)) {
      control = createColorControl(fieldId, value, path);
    } else if (isLongText(value)) {
      control = createTextareaControl(fieldId, value, path);
    } else {
      control = createTextControl(fieldId, value, path);
    }
  } else {
    const info = document.createElement('div');
    info.className = 'text-xs text-slate-400 dark:text-slate-500';
    info.textContent = 'Nem támogatott típus';
    control = info;
  }

  wrapper.appendChild(control);
  return wrapper;
}

function createBooleanControl(fieldId, value, path) {
  const container = document.createElement('label');
  container.className = 'inline-flex items-center gap-3 cursor-pointer select-none';

  const input = document.createElement('input');
  input.type = 'checkbox';
  input.id = fieldId;
  input.className = 'toggle-input';
  input.checked = Boolean(value);

  const slider = document.createElement('span');
  slider.className = 'toggle-slider';

  const text = document.createElement('span');
  text.className = 'text-xs font-medium text-slate-500 dark:text-slate-300';
  text.textContent = input.checked ? 'Be' : 'Ki';

  input.addEventListener('change', () => {
    setValueAtPath(path, input.checked);
    text.textContent = input.checked ? 'Be' : 'Ki';
    updateDirtyForPath(path);
    updateDirtyIndicators();
    updateSaveState();
    updateMeta();
  });

  container.appendChild(input);
  container.appendChild(slider);
  container.appendChild(text);
  return container;
}

function createNumberControl(fieldId, value, path) {
  const input = document.createElement('input');
  input.type = 'number';
  input.id = fieldId;
  input.value = Number.isFinite(value) ? String(value) : '';
  input.step = Number.isInteger(value) ? '1' : '0.01';
  input.className = 'w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white/70 dark:bg-slate-900/50 px-3 py-2 text-sm text-slate-700 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500';
  input.addEventListener('input', () => {
    const raw = input.value.trim();
    const pathKey = pathToString(path);
    if (raw === '' || Number.isNaN(Number(raw))) {
      state.invalidPaths.add(pathKey);
      updateInvalidIndicators();
      updateSaveState();
      return;
    }
    state.invalidPaths.delete(pathKey);
    const parsed = raw.includes('.') ? parseFloat(raw) : parseInt(raw, 10);
    setValueAtPath(path, parsed);
    updateDirtyForPath(path);
    updateDirtyIndicators();
    updateInvalidIndicators();
    updateSaveState();
    updateMeta();
  });
  return input;
}

function createTextControl(fieldId, value, path) {
  const input = document.createElement('input');
  input.type = 'text';
  input.id = fieldId;
  input.value = value ?? '';
  input.className = 'w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white/70 dark:bg-slate-900/50 px-3 py-2 text-sm text-slate-700 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500';
  input.addEventListener('input', () => {
    setValueAtPath(path, input.value);
    updateDirtyForPath(path);
    updateDirtyIndicators();
    updateSaveState();
    updateMeta();
  });
  return input;
}

function createTextareaControl(fieldId, value, path) {
  const textarea = document.createElement('textarea');
  textarea.id = fieldId;
  textarea.value = value ?? '';
  textarea.rows = Math.min(12, Math.max(4, textarea.value.split('\n').length + 1));
  textarea.className = 'w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white/70 dark:bg-slate-900/50 px-3 py-2 text-sm text-slate-700 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500';
  textarea.addEventListener('input', () => {
    setValueAtPath(path, textarea.value);
    updateDirtyForPath(path);
    updateDirtyIndicators();
    updateSaveState();
    updateMeta();
  });
  return textarea;
}

function createColorControl(fieldId, value, path) {
  const container = document.createElement('div');
  container.className = 'flex flex-wrap items-center gap-3';

  const pathKey = pathToString(path);

  const colorInput = document.createElement('input');
  colorInput.type = 'color';
  colorInput.id = `${fieldId}_color`;
  const initialColor = isHexColor(value) ? normalizeHex(value) : '#000000';
  colorInput.value = initialColor;
  colorInput.className = 'h-10 w-14 cursor-pointer rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent p-1';

  const textInput = document.createElement('input');
  textInput.type = 'text';
  textInput.id = fieldId;
  textInput.value = value ?? '';
  textInput.className = 'flex-1 min-w-[12rem] rounded-xl border border-slate-300 dark:border-slate-700 bg-white/70 dark:bg-slate-900/50 px-3 py-2 text-sm text-slate-700 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500';

  const applyValue = (next) => {
    setValueAtPath(path, next);
    updateDirtyForPath(path);
    updateDirtyIndicators();
    updateInvalidIndicators();
    updateSaveState();
    updateMeta();
  };

  colorInput.addEventListener('input', () => {
    const hex = normalizeHex(colorInput.value);
    textInput.value = hex;
    state.invalidPaths.delete(pathKey);
    applyValue(hex);
  });

  textInput.addEventListener('input', () => {
    const raw = textInput.value.trim();
    if (raw === '') {
      state.invalidPaths.delete(pathKey);
      applyValue('');
      return;
    }
    if (isHexColor(raw)) {
      const normalized = normalizeHex(raw);
      colorInput.value = normalized;
      state.invalidPaths.delete(pathKey);
      applyValue(normalized);
    } else {
      state.invalidPaths.delete(pathKey);
      applyValue(raw);
    }
  });

  container.appendChild(colorInput);
  container.appendChild(textInput);
  return container;
}

function detectPrimitiveType(value) {
  if (typeof value === 'boolean') return 'boolean';
  if (typeof value === 'number') return 'number';
  if (typeof value === 'string') return 'string';
  if (Array.isArray(value)) return 'array';
  if (isPlainObject(value)) return 'object';
  return typeof value;
}

function isPlainObject(value) {
  return Object.prototype.toString.call(value) === '[object Object]';
}

function isLongText(value) {
  if (typeof value !== 'string') return false;
  return value.length > 120 || value.includes('\n');
}

function isColorField(key, value) {
  if (typeof value === 'string' && isHexColor(value)) {
    return true;
  }
  if (typeof key === 'string' && key.toLowerCase().includes('color')) {
    return true;
  }
  return false;
}

function isHexColor(value) {
  if (typeof value !== 'string') return false;
  return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(value.trim());
}

function normalizeHex(value) {
  if (!value) return '#000000';
  const lower = value.toLowerCase();
  if (lower.length === 4) {
    return '#' + lower.slice(1).split('').map((c) => c + c).join('');
  }
  return lower;
}

function setValueAtPath(path, newValue) {
  let target = state.config;
  for (let i = 0; i < path.length - 1; i += 1) {
    target = target[path[i]];
  }
  target[path[path.length - 1]] = newValue;
}

function getValueAtPath(obj, path) {
  return path.reduce((acc, key) => (acc != null ? acc[key] : undefined), obj);
}

function updateDirtyForPath(path) {
  for (let i = path.length; i > 0; i -= 1) {
    const subPath = path.slice(0, i);
    const key = pathToString(subPath);
    const currentValue = getValueAtPath(state.config, subPath);
    const originalValue = getValueAtPath(state.original, subPath);
    if (!deepEqual(currentValue, originalValue)) {
      state.dirtyPaths.add(key);
    } else {
      state.dirtyPaths.delete(key);
    }
  }
  if (state.dirtyPaths.size === 0) {
    state.remoteChangedNotified = false;
  }
}

function addArrayItem(path) {
  const arr = getValueAtPath(state.config, path);
  if (!Array.isArray(arr)) return;
  const template = deriveArrayTemplate(path);
  arr.push(template);
  updateDirtyForPath(path);
  renderConfig();
  updateDirtyIndicators();
  updateInvalidIndicators();
  updateSaveState();
  updateMeta();
}

function removeArrayItem(path, index) {
  const arr = getValueAtPath(state.config, path);
  if (!Array.isArray(arr)) return;
  arr.splice(index, 1);
  const prefix = pathToString(path) + '.';
  state.invalidPaths = new Set(Array.from(state.invalidPaths).filter((itemPath) => !itemPath.startsWith(prefix)));
  updateDirtyForPath(path);
  renderConfig();
  updateDirtyIndicators();
  updateInvalidIndicators();
  updateSaveState();
  updateMeta();
}

function deriveArrayTemplate(path) {
  const current = getValueAtPath(state.config, path);
  let sample = null;
  if (Array.isArray(current) && current.length > 0) {
    sample = current[0];
  }
  if (sample == null) {
    const original = getValueAtPath(state.original, path);
    if (Array.isArray(original) && original.length > 0) {
      sample = original[0];
    }
  }
  if (sample == null) {
    return '';
  }
  return deriveEmptyValue(sample);
}

function deriveEmptyValue(sample) {
  if (Array.isArray(sample)) {
    return sample.map((item) => deriveEmptyValue(item));
  }
  if (isPlainObject(sample)) {
    const result = {};
    Object.entries(sample).forEach(([key, value]) => {
      result[key] = deriveEmptyValue(value);
    });
    return result;
  }
  if (typeof sample === 'number') return 0;
  if (typeof sample === 'boolean') return false;
  if (typeof sample === 'string') return '';
  return null;
}

function updateDirtyIndicators() {
  document.querySelectorAll('[data-field-path]').forEach((el) => {
    const fieldPath = el.getAttribute('data-field-path');
    el.classList.toggle('changed', hasDirtyPath(fieldPath));
  });
  document.querySelectorAll('[data-section]').forEach((el) => {
    const sectionPath = el.getAttribute('data-section');
    el.classList.toggle('changed', hasDirtyPath(sectionPath));
  });
}

function updateInvalidIndicators() {
  document.querySelectorAll('[data-field-path]').forEach((el) => {
    const fieldPath = el.getAttribute('data-field-path');
    el.classList.toggle('invalid', hasInvalidPath(fieldPath));
  });
}

function hasDirtyPath(prefix) {
  if (!prefix) return false;
  if (state.dirtyPaths.has(prefix)) return true;
  const dot = prefix + '.';
  for (const entry of state.dirtyPaths) {
    if (entry.startsWith(dot)) {
      return true;
    }
  }
  return false;
}

function hasInvalidPath(prefix) {
  if (!prefix) return false;
  if (state.invalidPaths.has(prefix)) return true;
  const dot = prefix + '.';
  for (const entry of state.invalidPaths) {
    if (entry.startsWith(dot)) {
      return true;
    }
  }
  return false;
}

function updateButtons() {
  updateSaveState();
  updateResetState();
}

function updateSaveState() {
  if (!elements.saveBtn) return;
  const disabled = state.saving || state.loading || state.invalidPaths.size > 0 || state.dirtyPaths.size === 0 || !state.config;
  elements.saveBtn.disabled = disabled;
  elements.saveBtn.setAttribute('aria-disabled', String(disabled));
  if (state.saving) {
    elements.saveBtn.innerHTML = '<span aria-hidden="true">⏳</span> Mentés folyamatban…';
  } else {
    elements.saveBtn.innerHTML = '<span aria-hidden="true">💾</span> Mentés';
  }
}

function updateResetState() {
  if (!elements.resetBtn) return;
  const disabled = state.restoring || state.loading || !state.backupAvailable;
  elements.resetBtn.disabled = disabled;
  elements.resetBtn.setAttribute('aria-disabled', String(disabled));
  if (state.restoring) {
    elements.resetBtn.innerHTML = '<span aria-hidden="true">⏳</span> Visszaállítás…';
  } else {
    elements.resetBtn.innerHTML = '<span aria-hidden="true">↺</span> Visszaállítás';
  }
}

function updateMeta() {
  if (!elements.metaInfo) return;
  const parts = [];
  if (state.lastUpdatedAt) {
    parts.push(`Szerveren módosítva: ${formatDate(state.lastUpdatedAt)}`);
  }
  if (state.lastLoadedAt) {
    parts.push(`Utolsó betöltés: ${formatDate(state.lastLoadedAt)}`);
  }
  if (state.dirtyPaths.size > 0) {
    parts.push(`${state.dirtyPaths.size} módosított mező`);
  }
  elements.metaInfo.textContent = parts.join(' · ');
}

function formatDate(iso) {
  if (!iso) return '';
  try {
    const date = new Date(iso);
    return new Intl.DateTimeFormat('hu-HU', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date);
  } catch (err) {
    return iso;
  }
}

function deepClone(value) {
  if (typeof structuredClone === 'function') {
    return structuredClone(value);
  }
  return JSON.parse(JSON.stringify(value));
}

function deepEqual(a, b) {
  return JSON.stringify(a) === JSON.stringify(b);
}

function pathToString(path) {
  return path.map((segment) => String(segment)).join('.');
}

function pathToId(path) {
  return 'field_' + path.map((segment) => String(segment).replace(/[^a-zA-Z0-9_]+/g, '_')).join('_');
}

function formatKey(key) {
  if (typeof key !== 'string') {
    return String(key);
  }
  return key
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function handleSave() {
  if (state.saving || state.invalidPaths.size > 0 || state.dirtyPaths.size === 0) {
    return;
  }
  state.saving = true;
  updateSaveState();
  const payload = {
    mode: 'update',
    config: state.config,
    expectedHash: state.originalHash,
  };
  fetch(state.apiUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': getCsrfToken() || '',
    },
    body: JSON.stringify(payload),
  })
    .then((response) => {
      if (response.status === 409) {
        return response.json().then((data) => ({ conflict: true, data }));
      }
      if (!response.ok) {
        return response.json().catch(() => ({})).then((data) => ({ error: true, data }));
      }
      return response.json().then((data) => ({ success: true, data }));
    })
    .then((result) => {
      if (result.conflict) {
        const hash = result.data?.hash;
        state.serverHash = hash || state.serverHash;
        state.latestRemoteHash = hash || state.latestRemoteHash;
        showBanner('A beállítások megváltoztak a szerveren. Kérjük, frissítsd az oldalt a továbblépéshez.', 'warning');
        showToast('⚠️ A konfiguráció időközben módosult. Töltsd be újra az adatokat.', 'error');
        return;
      }
      if (result.error || !result.success || !result.data?.ok) {
        const message = result.data?.error ? translateError(result.data.error) : 'A mentés nem sikerült.';
        showToast(message, 'error');
        return;
      }
      const data = result.data;
      state.original = deepClone(data.config);
      state.config = deepClone(data.config);
      state.originalHash = data.hash || '';
      state.serverHash = data.hash || '';
      state.latestRemoteHash = data.hash || '';
      state.backupAvailable = Boolean(data.backupAvailable);
      state.lastUpdatedAt = data.updatedAt || null;
      state.lastLoadedAt = new Date().toISOString();
      state.dirtyPaths.clear();
      state.invalidPaths.clear();
      state.remoteChangedNotified = false;
      renderConfig();
      updateDirtyIndicators();
      updateInvalidIndicators();
      updateButtons();
      updateMeta();
      hideBanner();
      showToast('✅ Beállítások elmentve', 'success');
    })
    .catch((error) => {
      console.error(error);
      showToast('Ismeretlen hiba történt mentés közben.', 'error');
    })
    .finally(() => {
      state.saving = false;
      updateButtons();
      updateResetState();
    });
}

function handleRestore() {
  if (state.restoring || !state.backupAvailable) {
    return;
  }
  if (!window.confirm('A visszaállítás a jelenlegi config.json tartalmát felülírja a mentéssel. Folytatod?')) {
    return;
  }
  state.restoring = true;
  updateResetState();
  fetch(state.apiUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': getCsrfToken() || '',
    },
    body: JSON.stringify({ mode: 'restore' }),
  })
    .then((response) => {
      if (!response.ok) {
        return response.json().catch(() => ({})).then((data) => ({ error: true, data }));
      }
      return response.json().then((data) => ({ success: true, data }));
    })
    .then((result) => {
      if (result.error || !result.success || !result.data?.ok) {
        const message = result.data?.error ? translateError(result.data.error) : 'Nem sikerült visszaállítani a konfigurációt.';
        showToast(message, 'error');
        return;
      }
      const data = result.data;
      state.original = deepClone(data.config);
      state.config = deepClone(data.config);
      state.originalHash = data.hash || '';
      state.serverHash = data.hash || '';
      state.latestRemoteHash = data.hash || '';
      state.backupAvailable = Boolean(data.backupAvailable);
      state.lastUpdatedAt = data.updatedAt || null;
      state.lastLoadedAt = new Date().toISOString();
      state.dirtyPaths.clear();
      state.invalidPaths.clear();
      state.remoteChangedNotified = false;
      renderConfig();
      updateDirtyIndicators();
      updateInvalidIndicators();
      updateButtons();
      updateMeta();
      hideBanner();
      showToast('♻️ Beállítások visszaállítva a biztonsági mentésből', 'success');
    })
    .catch((error) => {
      console.error(error);
      showToast('Ismeretlen hiba történt visszaállítás közben.', 'error');
    })
    .finally(() => {
      state.restoring = false;
      updateResetState();
      updateButtons();
    });
}

function startMetaPolling() {
  if (state.metaPoller) {
    clearInterval(state.metaPoller);
  }
  state.metaPoller = window.setInterval(checkRemoteChanges, 30000);
}

function checkRemoteChanges() {
  fetch(`${state.apiUrl}?meta=1`, {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  })
    .then((response) => {
      if (!response.ok) return null;
      return response.json();
    })
    .then((payload) => {
      if (!payload || payload.ok !== true) return;
      const hash = payload.hash || '';
      if (state.serverHash && hash && hash !== state.serverHash) {
        state.latestRemoteHash = hash;
        if (!state.remoteChangedNotified) {
          state.remoteChangedNotified = true;
          showBanner('A beállítások megváltoztak, kérlek frissítsd az oldalt a naprakész adatokhoz.', 'warning');
          showToast('ℹ️ A config.json megváltozott egy másik munkamenetben.', 'error');
        }
      }
      if (payload.updatedAt) {
        state.lastUpdatedAt = payload.updatedAt;
        updateMeta();
      }
      state.backupAvailable = Boolean(payload.backupAvailable);
      updateResetState();
    })
    .catch(() => {});
}

function showToast(message, type = 'success') {
  if (!elements.toastHost) return;
  const toast = document.createElement('div');
  toast.className = `toast ${type === 'error' ? 'toast-error' : 'toast-success'}`;
  toast.textContent = message;
  toast.style.opacity = '0';
  toast.style.transform = 'translateY(10px)';
  elements.toastHost.appendChild(toast);
  requestAnimationFrame(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';
  });
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    setTimeout(() => {
      toast.remove();
    }, 200);
  }, 4000);
}

function showBanner(message, variant = 'info') {
  if (!elements.statusBanner) return;
  elements.statusBanner.textContent = message;
  elements.statusBanner.classList.remove('hidden');
  elements.statusBanner.classList.remove('border-amber-400', 'bg-amber-100', 'text-amber-800', 'border-red-500', 'bg-red-100', 'text-red-700', 'border-emerald-500', 'bg-emerald-100', 'text-emerald-700');
  if (variant === 'warning') {
    elements.statusBanner.classList.add('border-amber-400', 'bg-amber-100', 'text-amber-800');
  } else if (variant === 'error') {
    elements.statusBanner.classList.add('border-red-500', 'bg-red-100', 'text-red-700');
  } else if (variant === 'success') {
    elements.statusBanner.classList.add('border-emerald-500', 'bg-emerald-100', 'text-emerald-700');
  }
}

function hideBanner() {
  if (!elements.statusBanner) return;
  elements.statusBanner.classList.add('hidden');
}

function translateError(code) {
  if (!code) return 'Ismeretlen hiba.';
  if (code.startsWith('invalid_type:')) {
    const field = code.slice('invalid_type:'.length);
    return `Érvénytelen típus: ${field}`;
  }
  if (code.startsWith('missing_key:')) {
    const field = code.slice('missing_key:'.length);
    return `Hiányzó kulcs: ${field}`;
  }
  if (code.startsWith('unknown_key:')) {
    const field = code.slice('unknown_key:'.length);
    return `Ismeretlen kulcs: ${field}`;
  }
  const map = {
    backup_unavailable: 'Nem található biztonsági mentés.',
    backup_invalid: 'A biztonsági mentés sérült vagy érvénytelen.',
    restore_failed: 'Nem sikerült visszaállítani a konfigurációt.',
    write_failed: 'A konfiguráció mentése nem sikerült.',
    config_read_failed: 'A config.json nem olvasható.',
    config_invalid: 'A config.json formátuma érvénytelen.',
    lock_failed: 'A konfiguráció zárolása nem sikerült.',
  };
  return map[code] || `Hiba: ${code}`;
}

function getCsrfToken() {
  return readCookie(COOKIE_CSRF);
}

function readCookie(name) {
  if (typeof document === 'undefined' || !document.cookie) {
    return null;
  }
  const parts = document.cookie.split(';');
  for (const part of parts) {
    const trimmed = part.trim();
    if (!trimmed) continue;
    const eq = trimmed.indexOf('=');
    const key = eq === -1 ? trimmed : trimmed.slice(0, eq);
    if (key === name) {
      const value = eq === -1 ? '' : trimmed.slice(eq + 1);
      try {
        return decodeURIComponent(value);
      } catch (err) {
        return value;
      }
    }
  }
  return null;
}
