(function(){
  const EP = window.APP_BOOTSTRAP?.endpoints || {};
  const homeUrl = window.APP_BOOTSTRAP?.homeUrl || 'index.php';

  const settingsView = document.getElementById('settingsView');
  const settingsContent = document.getElementById('settingsContent');
  const settingsStatus = document.getElementById('settingsStatus');
  const settingsBackBtn = document.getElementById('settingsBackBtn');
  const settingsCancelBtn = document.getElementById('settingsCancelBtn');
  const settingsSaveBtn = document.getElementById('settingsSaveBtn');

  if (!settingsView || !settingsContent) {
    return;
  }

  const settingsState = {
    original: null,
    working: null,
    dirty: false,
    loading: false
  };

  const SETTINGS_LABEL_OVERRIDES = {
    app: 'Alkalmazás',
    'app.title': 'Alkalmazás címe',
    'app.auto_sort_by_round': 'Automatikus rendezés kör szerint',
    'app.round_zero_at_bottom': 'Nulladik kör a lista alján',
    'app.default_collapsed': 'Körök induláskor összezárva',
    history: 'Előzmények',
    'history.undo_enabled': 'Visszavonás engedélyezése',
    'history.max_steps': 'Visszavonási lépések száma',
    features: 'Funkciók',
    'features.toolbar': 'Eszköztár',
    'features.group_actions': 'Csoport műveletek',
    files: 'Fájlok',
    'files.data_file': 'Adatbázis fájl',
    'files.export_file': 'Export fájl neve',
    'files.export_download_name': 'Letöltési fájlnév',
    'files.archive_file': 'Archív fájl',
    backup: 'Biztonsági mentés',
    'backup.enabled': 'Biztonsági mentés engedélyezve',
    'backup.dir': 'Mentési könyvtár',
    'backup.min_interval_minutes': 'Minimum időköz (perc)',
    'backup.retention_policy': 'Megőrzési szabályok',
    'backup.retention_policy.*': 'Megőrzési szabály',
    'backup.retention_policy.*.min_age_hours': 'Minimum kor (óra)',
    'backup.retention_policy.*.period_hours': 'Mentési gyakoriság (óra)',
    map: 'Térkép',
    'map.tiles': 'Csempe beállítások',
    'map.tiles.url': 'Csempe URL',
    'map.tiles.attribution': 'Forráshivatkozás',
    'map.fit_bounds': 'Kezdő nézet határai',
    'map.fit_bounds.*': 'Koordináta-pár',
    'map.fit_bounds.*.*': 'Koordináta érték',
    'map.max_bounds_pad': 'Határpuffer arány',
    geocode: 'Geokódolás',
    'geocode.countrycodes': 'Országkódok',
    'geocode.language': 'Nyelv',
    'geocode.user_agent': 'Felhasználói ügynök',
    ui: 'Felhasználói felület',
    'ui.toolbar': 'Eszköztár megjelenés',
    'ui.toolbar.menu_icon': 'Menü ikon',
    'ui.toolbar.menu_icon.width': 'Menü ikon szélessége',
    'ui.toolbar.menu_icon.height': 'Menü ikon magassága',
    'ui.toolbar.menu_icon.bar_height': 'Menü ikon csík magassága',
    'ui.toolbar.menu_icon.color': 'Menü ikon színe',
    'ui.toolbar.menu_icon.bar_radius': 'Menü ikon csík lekerekítése',
    'ui.panel_min_px': 'Panel minimum szélesség (px)',
    'ui.panel_pref_vw': 'Panel preferált szélesség (vw)',
    'ui.panel_max_px': 'Panel maximum szélesség (px)',
    'ui.colors': 'Színtémák',
    'ui.colors.light': 'Világos téma',
    'ui.colors.dark': 'Sötét téma',
    'ui.marker': 'Térképes jelölő',
    'ui.marker.icon_size': 'Ikon méret',
    'ui.marker.font_size': 'Felirat betűmérete',
    'ui.marker.font_family': 'Felirat betűcsaládja',
    'ui.marker.font_weight': 'Felirat betűvastagsága',
    'ui.marker.auto_contrast': 'Automatikus kontraszt',
    'ui.marker.default_text_color': 'Alap szövegszín',
    'ui.marker.view_box_size': 'SVG nézet méret',
    'ui.marker.icon_path': 'Ikon vektor útvonal',
    'ui.marker.stroke_color': 'Körvonal színe',
    'ui.marker.stroke_opacity': 'Körvonal átlátszósága',
    'ui.marker.stroke_width': 'Körvonal vastagsága',
    'ui.marker.icon_anchor_x': 'Ikon horgony X',
    'ui.marker.icon_anchor_y': 'Ikon horgony Y',
    'ui.marker.popup_anchor_x': 'Felugró horgony X',
    'ui.marker.popup_anchor_y': 'Felugró horgony Y',
    'ui.marker.overlap_badge': 'Átfedés jelvény',
    'ui.marker.overlap_badge.size': 'Jelvény mérete',
    'ui.marker.overlap_badge.margin_right': 'Jelvény jobb margó',
    'ui.marker.overlap_badge.offset_y': 'Jelvény függőleges eltolás',
    'ui.marker.overlap_badge.font_scale': 'Jelvény betű skála',
    'ui.marker.overlap_badge.corner_radius': 'Jelvény sarok lekerekítés',
    'ui.marker.overlap_badge.fill': 'Jelvény kitöltés',
    'ui.marker.overlap_badge.fill_opacity': 'Jelvény kitöltés átlátszóság',
    rounds: 'Körök',
    'rounds.*': 'Kör',
    'rounds.*.id': 'Kör azonosító',
    'rounds.*.label': 'Kör neve',
    'rounds.*.color': 'Kör színe',
    items: 'Tételek',
    'items.address_field_id': 'Cím mező azonosító',
    'items.label_field_id': 'Felirat mező azonosító',
    'items.note_field_id': 'Megjegyzés mező azonosító',
    'items.fields': 'Tétel mezők',
    'items.fields.*': 'Mező beállítás',
    'items.fields.*.id': 'Mező azonosító',
    'items.fields.*.type': 'Mező típusa',
    'items.fields.*.label': 'Mező felirata',
    'items.fields.*.placeholder': 'Mező helykitöltő',
    'items.fields.*.default': 'Alapértelmezett érték',
    'items.fields.*.required': 'Kötelező mező',
    'items.metrics': 'Mérőszámok',
    'items.metrics.*': 'Mérőszám',
    'items.metrics.*.id': 'Mérőszám azonosító',
    'items.metrics.*.type': 'Mérőszám típusa',
    'items.metrics.*.label': 'Mérőszám felirata',
    'items.metrics.*.placeholder': 'Mérőszám helykitöltő',
    'items.metrics.*.step': 'Lépésköz',
    'items.metrics.*.min': 'Minimum érték',
    'items.metrics.*.precision': 'Pontosság',
    'items.metrics.*.unit': 'Mértékegység',
    'items.metrics.*.row_format': 'Sor formátum',
    'items.metrics.*.group_format': 'Csoport formátum',
    'items.round_field': 'Kör mező',
    'items.round_field.label': 'Kör mező felirata',
    'items.round_field.placeholder': 'Kör mező helykitöltő',
    'items.meta_display': 'Meta megjelenítés',
    'items.meta_display.separator': 'Elválasztó',
    'items.meta_display.missing_warning': 'Hiány figyelmeztetés',
    'items.meta_display.missing_warning.enabled': 'Figyelmeztetés engedélyezése',
    'items.meta_display.missing_warning.text': 'Figyelmeztető jelzés',
    'items.meta_display.missing_warning.title': 'Figyelmeztetés magyarázata',
    'items.meta_display.missing_warning.class': 'Figyelmeztetés CSS osztály',
    'items.deadline_indicator': 'Határidő jelző',
    'items.deadline_indicator.enabled': 'Határidő jelző engedélyezése',
    'items.deadline_indicator.field_id': 'Határidő mező azonosító',
    'items.deadline_indicator.icon_size': 'Ikon méret',
    'items.deadline_indicator.steps': 'Határidő lépések',
    'items.deadline_indicator.steps.*': 'Lépés',
    'items.deadline_indicator.steps.*.min_days': 'Minimum nap',
    'items.deadline_indicator.steps.*.color': 'Lépés színe',
    export: 'Export beállítások',
    'export.include_label': 'Felirat exportálása',
    'export.include_address': 'Cím exportálása',
    'export.include_note': 'Megjegyzés exportálása',
    'export.group_header_template': 'Csoport fejléc sablon',
    print: 'Nyomtatási beállítások',
    'print.title_suffix': 'Nyomtatási cím utótag',
    'print.list_title': 'Lista címe',
    text: 'Szövegkészlet',
    'text.toolbar': 'Eszköztár szövegek',
    'text.badges': 'Jelvény feliratok',
    'text.actions': 'Gomb feliratok',
    'text.items': 'Tételek szövegei',
    'text.round': 'Kör szövegek',
    'text.group': 'Csoport szövegek',
    'text.quick_search': 'Gyorskereső szövegek',
    'text.messages': 'Rendszerüzenetek',
    'text.import': 'Import szövegek',
    'text.map': 'Térképes szövegek',
    'text.save_status': 'Mentés állapot szövegek'
  };

  const SETTINGS_SEGMENT_OVERRIDES = {
    app: 'Alkalmazás',
    history: 'Előzmények',
    features: 'Funkciók',
    toolbar: 'Eszköztár',
    expand_all: 'Összes kinyitása',
    collapse_all: 'Összes összezárása',
    import_all: 'Összes importálása',
    export_all: 'Összes exportálása',
    print_all: 'Összes nyomtatása',
    download_archive: 'Archívum letöltése',
    theme_toggle: 'Téma váltása',
    undo: 'Visszavonás',
    quick_search: 'Gyorskereső',
    marker_popup_on_click: 'Felugró ablak kattintásra',
    marker_popup_on_focus: 'Felugró ablak fókuszkor',
    marker_focus_feedback: 'Fókusz visszajelzés',
    marker_overlap_indicator: 'Átfedés jelző',
    group_actions: 'Csoport műveletek',
    group_totals: 'Csoportösszesítők',
    round_planned_date: 'Tervezett dátum',
    round_planned_time: 'Tervezett idő',
    data_file: 'Adatfájl',
    export_file: 'Export fájl',
    export_download_name: 'Letöltési fájlnév',
    archive_file: 'Archív fájl',
    min_interval_minutes: 'Minimum időköz (perc)',
    retention_policy: 'Megőrzési szabály',
    min_age_hours: 'Minimum kor (óra)',
    period_hours: 'Gyakoriság (óra)',
    fit_bounds: 'Kezdő nézet határai',
    max_bounds_pad: 'Határpuffer',
    countrycodes: 'Országkódok',
    user_agent: 'Felhasználói ügynök',
    menu_icon: 'Menü ikon',
    bar_height: 'Csík magasság',
    bar_radius: 'Csík lekerekítés',
    default_text_color: 'Alap szövegszín',
    view_box_size: 'SVG nézet méret',
    icon_path: 'Ikon útvonal',
    stroke_color: 'Körvonal szín',
    stroke_opacity: 'Körvonal átlátszóság',
    stroke_width: 'Körvonal vastagság',
    icon_anchor_x: 'Ikon horgony X',
    icon_anchor_y: 'Ikon horgony Y',
    popup_anchor_x: 'Felugró horgony X',
    popup_anchor_y: 'Felugró horgony Y',
    overlap_badge: 'Átfedés jelvény',
    margin_right: 'Jobb margó',
    offset_y: 'Függőleges eltolás',
    font_scale: 'Betű skála',
    corner_radius: 'Sarok lekerekítés',
    fill_opacity: 'Kitöltés átlátszóság',
    address_field_id: 'Cím mező azonosító',
    label_field_id: 'Felirat mező azonosító',
    note_field_id: 'Megjegyzés mező azonosító',
    round_field: 'Kör mező',
    meta_display: 'Meta megjelenítés',
    missing_warning: 'Hiány figyelmeztetés',
    field_id: 'Mező azonosító',
    row_format: 'Sor formátum',
    group_format: 'Csoport formátum',
    group_header_template: 'Csoport fejléc sablon',
    title_suffix: 'Cím utótag',
    list_title: 'Lista címe',
    pin_counter_label: 'Pin számláló felirat',
    pin_counter_title: 'Pin számláló címke',
    custom_sort_handle_hint: 'Egyedi sorrend súgó',
    sum_template: 'Összeg sablon',
    sum_separator: 'Összeg elválasztó',
    sort_mode_label: 'Rendezés mód felirat',
    sort_mode_default: 'Alapértelmezett rendezés',
    sort_mode_custom: 'Egyedi rendezés',
    sort_mode_custom_hint: 'Egyedi rendezés súgó',
    planned_date_label: 'Tervezett dátum felirat',
    planned_date_hint: 'Tervezett dátum súgó',
    planned_time_label: 'Tervezett idő felirat',
    planned_time_hint: 'Tervezett idő súgó',
    quick_search_placeholder: 'Gyorskereső helykitöltő',
    clear_label: 'Törlés gomb felirat',
    clear_title: 'Törlés gomb buborék',
    delete_disabled_hint: 'Törlés tiltás súgó',
    label_missing: 'Hiányzó felirat',
    deadline_label: 'Határidő felirat',
    deadline_missing: 'Hiányzó határidő',
    deadline_relative_future: 'Határidő – hátralévő idő',
    deadline_relative_today: 'Határidő – ma',
    deadline_relative_past: 'Határidő – lejárt',
    address_required: 'Cím kötelező üzenet',
    load_error: 'Betöltési hiba',
    delete_round_confirm: 'Kör törlés megerősítése',
    delete_round_success: 'Kör törlés siker',
    delete_round_error: 'Kör törlés hiba',
    navigation_empty: 'Navigáció – nincs cím',
    navigation_skip: 'Navigáció kihagyás',
    geocode_failed: 'Geokódolás sikertelen',
    geocode_failed_detailed: 'Geokódolás részletes hiba',
    undo_unavailable: 'Visszavonás nem érhető el',
    import_success: 'Import siker',
    import_error: 'Import hiba',
    import_in_progress: 'Import folyamatban',
    import_mode_prompt: 'Import mód kérdés',
    import_mode_replace: 'Import – csere',
    import_mode_append: 'Import – hozzáfűzés',
    import_mode_confirm_replace: 'Import csere megerősítés',
    import_mode_confirm_append: 'Import hozzáfűzés megerősítés',
    import_geocode_partial: 'Import – részleges geokódolás',
    import_geocode_partial_detail: 'Import – részleges geokódolás részletei',
    import_geocode_partial_list_title: 'Import – geokódolás lista címe',
    import_geocode_use_city: 'Import – település használata',
    import_geocode_skip_addresses: 'Import – címek kihagyása',
    import_geocode_copy: 'Import – címek másolása',
    import_geocode_copy_success: 'Import – másolás siker',
    import_geocode_copy_error: 'Import – másolás hiba',
    import_geocode_reset: 'Import – visszaállítás',
    import_geocode_skip_city: 'Import – település kihagyása',
    import_city_fallback_progress: 'Import – település alapú folyamat',
    import_city_fallback_result: 'Import – település alapú eredmény',
    import_skip_progress: 'Import – kihagyás folyamat',
    import_skip_result: 'Import – kihagyás eredmény',
    import_skip_none: 'Import – nincs módosítás',
    import_skip_error: 'Import – kihagyás hiba',
    import_reset_progress: 'Import – visszaállítás folyamat',
    import_reset_success: 'Import – visszaállítás siker',
    import_reset_error: 'Import – visszaállítás hiba',
    import_reset_missing: 'Import – hiányzó eredeti adatok',
    save_status: 'Mentés állapot',
    quick_search: 'Gyorskereső',
    planned_date_hint: 'Tervezett dátum súgó',
    planned_time_hint: 'Tervezett idő súgó'
  };

  const SETTINGS_WORD_TRANSLATIONS = {
    accent: 'kiemelő',
    actions: 'műveletek',
    address: 'cím',
    addresses: 'címek',
    after: 'után',
    age: 'kor',
    agent: 'ügynök',
    all: 'összes',
    anchor: 'horgony',
    app: 'alkalmazás',
    append: 'hozzáfűzés',
    archive: 'archívum',
    at: '',
    attribution: 'forráshivatkozás',
    auto: 'automatikus',
    background: 'háttér',
    backup: 'biztonsági mentés',
    badge: 'jelvény',
    badges: 'jelvények',
    bar: 'csík',
    bg: 'háttér',
    border: 'szegély',
    bottom: 'alul',
    bounds: 'határok',
    box: 'doboz',
    by: '',
    city: 'település',
    class: 'osztály',
    clear: 'törlés',
    click: 'kattintás',
    close: 'bezárás',
    collapse: 'összezárás',
    collapsed: 'összezárt',
    color: 'szín',
    colors: 'színek',
    confirm: 'megerősítés',
    contrast: 'kontraszt',
    coordinates: 'koordináták',
    copy: 'másolás',
    corner: 'sarok',
    counter: 'számláló',
    countrycodes: 'országkódok',
    custom: 'egyedi',
    dark: 'sötét',
    data: 'adat',
    date: 'dátum',
    days: 'nap',
    deadline: 'határidő',
    default: 'alapértelmezett',
    delete: 'törlés',
    detail: 'részletek',
    detailed: 'részletes',
    dir: 'könyvtár',
    disabled: 'letiltva',
    display: 'megjelenítés',
    distance: 'távolság',
    download: 'letöltés',
    empty: 'üres',
    enabled: 'engedélyezve',
    err: 'hiba',
    error: 'hiba',
    expand: 'kinyitás',
    export: 'export',
    fade: 'halványítás',
    failed: 'sikertelen',
    fallback: 'tartalék',
    family: 'család',
    features: 'funkciók',
    feedback: 'visszajelzés',
    field: 'mező',
    fields: 'mezők',
    file: 'fájl',
    files: 'fájlok',
    fill: 'kitöltés',
    fit: 'illesztés',
    focus: 'fókusz',
    font: 'betű',
    format: 'formátum',
    future: 'jövő',
    geocode: 'geokódolás',
    group: 'csoport',
    handle: 'fogantyú',
    header: 'fejléc',
    height: 'magasság',
    hide: 'elrejtés',
    highlight: 'kiemelés',
    hint: 'súgó',
    history: 'előzmények',
    hours: 'óra',
    icon: 'ikon',
    id: 'azonosító',
    import: 'import',
    include: 'tartalmaz',
    indicator: 'jelző',
    initial: 'kezdeti',
    interval: 'időköz',
    items: 'tételek',
    label: 'felirat',
    language: 'nyelv',
    lat: 'szélesség (lat)',
    left: 'bal',
    lifetime: 'élettartam',
    light: 'világos',
    list: 'lista',
    load: 'betöltés',
    lon: 'hosszúság (lon)',
    map: 'térkép',
    margin: 'margó',
    marker: 'jelölő',
    max: 'maximális',
    menu: 'menü',
    messages: 'üzenetek',
    meta: 'meta',
    metrics: 'mérőszámok',
    min: 'minimális',
    minutes: 'perc',
    missing: 'hiányzó',
    mode: 'mód',
    more: 'további',
    ms: 'ms',
    muted: 'visszafogott',
    name: 'név',
    navigate: 'navigáció',
    navigation: 'navigáció',
    none: 'nincs',
    note: 'megjegyzés',
    offset: 'eltolás',
    ok: 'ok',
    opacity: 'átlátszóság',
    open: 'megnyitás',
    origin: 'kiindulási',
    overlap: 'átfedés',
    pad: 'párna',
    padding: 'belső margó',
    panel: 'panel',
    partial: 'részleges',
    past: 'múlt',
    path: 'útvonal',
    period: 'időtartam',
    pin: 'pin',
    placeholder: 'helykitöltő',
    planned: 'tervezett',
    policy: 'szabály',
    popup: 'felugró',
    position: 'pozíció',
    precision: 'pontosság',
    pref: 'preferált',
    print: 'nyomtatás',
    progress: 'folyamat',
    prompt: 'kérdés',
    px: 'px',
    quick: 'gyors',
    radius: 'sugár',
    ratio: 'arány',
    relative: 'relatív',
    replace: 'csere',
    required: 'kötelező',
    reset: 'visszaállítás',
    result: 'eredmény',
    retention: 'megőrzés',
    right: 'jobb',
    ring: 'gyűrű',
    round: 'kör',
    rounds: 'körök',
    routing: 'útvonaltervezés',
    row: 'sor',
    save: 'mentés',
    scale: 'skála',
    search: 'keresés',
    separator: 'elválasztó',
    size: 'méret',
    skip: 'kihagyás',
    sort: 'rendezés',
    start: 'kezdet',
    status: 'állapot',
    step: 'lépés',
    steps: 'lépések',
    sticky: 'rögzített',
    stroke: 'körvonal',
    style: 'stílus',
    success: 'siker',
    suffix: 'utótag',
    sum: 'összeg',
    template: 'sablon',
    text: 'szöveg',
    theme: 'téma',
    threshold: 'küszöb',
    tiles: 'csempék',
    time: 'idő',
    title: 'cím',
    today: 'ma',
    toggle: 'váltó',
    top: 'felső',
    totals: 'összesítők',
    type: 'típus',
    ui: 'felület',
    unavailable: 'nem érhető el',
    unit: 'mértékegység',
    url: 'URL',
    use: 'használat',
    user: 'felhasználó',
    view: 'nézet',
    vw: 'vw',
    warning: 'figyelmeztetés',
    waypoints: 'útvonalpontok',
    weight: 'súly',
    width: 'szélesség',
    x: 'X',
    y: 'Y',
    zIndex: 'Z-index',
    zero: 'nulladik'
  };

  function pathKey(path, wildcard){
    return path.map(seg => {
      if (typeof seg === 'number') {
        return wildcard ? '*' : String(seg);
      }
      return String(seg);
    }).join('.');
  }

  function translateSegment(segment){
    if (segment == null) return '';
    if (typeof segment !== 'string') return String(segment);
    if (SETTINGS_SEGMENT_OVERRIDES[segment]) {
      return SETTINGS_SEGMENT_OVERRIDES[segment];
    }
    const parts = segment.replace(/-/g, '_').split('_').filter(Boolean);
    if (!parts.length) {
      return segment;
    }
    const translated = parts.map((part) => {
      const lower = part.toLowerCase();
      const mapped = Object.prototype.hasOwnProperty.call(SETTINGS_WORD_TRANSLATIONS, lower)
        ? SETTINGS_WORD_TRANSLATIONS[lower]
        : part;
      return mapped;
    }).filter(Boolean);
    if (!translated.length) return segment;
    const joined = translated.join(' ').trim();
    if (!joined) return segment;
    return joined.charAt(0).toUpperCase() + joined.slice(1);
  }

  function getLabelForPath(path, fallback){
    if (!Array.isArray(path) || path.length === 0) {
      return typeof fallback === 'string' && fallback ? fallback : '';
    }
    const exact = pathKey(path, false);
    if (Object.prototype.hasOwnProperty.call(SETTINGS_LABEL_OVERRIDES, exact)) {
      return SETTINGS_LABEL_OVERRIDES[exact];
    }
    const wildcard = pathKey(path, true);
    if (Object.prototype.hasOwnProperty.call(SETTINGS_LABEL_OVERRIDES, wildcard)) {
      return SETTINGS_LABEL_OVERRIDES[wildcard];
    }
    if (path[0] === 'text') {
      const category = path[1];
      const categoryLabel = category ? translateSegment(category) : translateSegment('text');
      const remainder = path.slice(2).map(translateSegment).filter(Boolean);
      if (remainder.length === 0) {
        return categoryLabel || translateSegment('text');
      }
      return `${categoryLabel} – ${remainder.join(' – ')}`;
    }
    const last = path[path.length - 1];
    if (Object.prototype.hasOwnProperty.call(SETTINGS_SEGMENT_OVERRIDES, last)) {
      return SETTINGS_SEGMENT_OVERRIDES[last];
    }
    if (typeof last === 'string') {
      const translated = translateSegment(last);
      if (translated) return translated;
    }
    if (typeof fallback === 'string' && fallback.trim()) {
      return fallback;
    }
    return prettifyKey(path[path.length - 1]);
  }

  function prettifyKey(key){
    if (key == null) return '';
    if (typeof key === 'number') return `#${key + 1}`;
    const str = String(key);
    const base = str.replace(/[_-]+/g, ' ').trim();
    if (base.length === 0) return str;
    if (base.toLowerCase() === 'id') return 'ID';
    if (base.toLowerCase() === 'url') return 'URL';
    return base.replace(/\b\w/g, ch => ch.toUpperCase());
  }

  function pathToId(path){
    return 'cfg_' + path.map(part => String(part).replace(/[^a-z0-9]+/gi, '_')).join('_');
  }

  function getAtPath(target, path){
    if (!target || !Array.isArray(path)) return undefined;
    let cursor = target;
    for (const key of path) {
      if (cursor == null) return undefined;
      cursor = cursor[key];
    }
    return cursor;
  }

  function valuesEqual(a, b){
    if (a === b) return true;
    if (typeof a === 'number' && typeof b === 'number' && Number.isNaN(a) && Number.isNaN(b)) return true;
    if (typeof a !== 'object' || a === null || typeof b !== 'object' || b === null) return false;
    try {
      return JSON.stringify(a) === JSON.stringify(b);
    } catch (err) {
      return false;
    }
  }

  function setAtPath(target, path, newValue){
    if (!target || !Array.isArray(path) || !path.length) return false;
    let cursor = target;
    for (let i = 0; i < path.length - 1; i += 1) {
      const key = path[i];
      if (cursor[key] == null || typeof cursor[key] !== 'object') {
        cursor[key] = typeof path[i + 1] === 'number' ? [] : {};
      }
      cursor = cursor[key];
    }
    const lastKey = path[path.length - 1];
    const prev = cursor[lastKey];
    if (valuesEqual(prev, newValue)) return false;
    cursor[lastKey] = newValue;
    return true;
  }

  function deepClone(value){
    if (window.structuredClone) {
      try {
        return window.structuredClone(value);
      } catch (err) {
        // fall through to manual clone
      }
    }
    if (Array.isArray(value)) {
      return value.map(item => deepClone(item));
    }
    if (value && typeof value === 'object') {
      const out = {};
      Object.keys(value).forEach(key => {
        out[key] = deepClone(value[key]);
      });
      return out;
    }
    return value;
  }

  function markSettingsDirty(){
    settingsState.dirty = true;
    updateSettingsButtons();
  }

  function setSettingsStatus(message, type = ''){
    if (!settingsStatus) return;
    settingsStatus.textContent = message || '';
    if (type) {
      settingsStatus.dataset.type = type;
    } else {
      delete settingsStatus.dataset.type;
    }
  }

  function updateSettingsButtons(){
    if (settingsCancelBtn) {
      settingsCancelBtn.disabled = !settingsState.dirty || settingsState.loading;
    }
    if (settingsSaveBtn) {
      settingsSaveBtn.disabled = settingsState.loading || !settingsState.dirty;
    }
  }

  function renderSettings(){
    if (!settingsContent) return;
    settingsContent.textContent = '';
    const config = settingsState.working;
    if (!config || typeof config !== 'object') {
      const placeholder = document.createElement('p');
      placeholder.className = 'settings-placeholder';
      placeholder.textContent = 'Nincs megjeleníthető beállítás.';
      settingsContent.appendChild(placeholder);
      return;
    }
    Object.keys(config).forEach(key => {
      const sectionNode = renderSection(key, config[key], [key]);
      if (sectionNode) settingsContent.appendChild(sectionNode);
    });
  }

  function renderSection(key, value, path){
    const wrapper = document.createElement('section');
    wrapper.className = 'settings-section';
    const heading = document.createElement('h2');
    heading.textContent = getLabelForPath(path, prettifyKey(key));
    wrapper.appendChild(heading);
    const content = document.createElement('div');
    content.className = 'settings-section-content';
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      Object.keys(value).forEach(childKey => {
        const childNode = renderNode(childKey, value[childKey], path.concat(childKey));
        if (childNode) content.appendChild(childNode);
      });
    } else {
      const node = renderScalarField(key, value, path);
      if (node) content.appendChild(node);
    }
    if (!content.children.length) {
      const placeholder = document.createElement('div');
      placeholder.className = 'settings-placeholder';
      placeholder.textContent = 'Nincs további beállítás.';
      content.appendChild(placeholder);
    }
    wrapper.appendChild(content);
    return wrapper;
  }

  function renderNode(key, value, path, level = 0){
    if (Array.isArray(value)) {
      return renderArrayField(key, value, path, level + 1);
    }
    if (value && typeof value === 'object') {
      return renderGroup(key, value, path, level + 1);
    }
    return renderScalarField(key, value, path);
  }

  function renderGroup(key, value, path){
    const wrapper = document.createElement('div');
    wrapper.className = 'settings-group';
    const heading = document.createElement('h3');
    heading.textContent = getLabelForPath(path, prettifyKey(key));
    wrapper.appendChild(heading);
    Object.keys(value || {}).forEach(childKey => {
      const childNode = renderNode(childKey, value[childKey], path.concat(childKey));
      if (childNode) wrapper.appendChild(childNode);
    });
    return wrapper;
  }

  function renderArrayField(key, value, path){
    const wrapper = document.createElement('div');
    wrapper.className = 'settings-group';
    const heading = document.createElement('h3');
    heading.textContent = getLabelForPath(path, prettifyKey(key));
    wrapper.appendChild(heading);
    const list = document.createElement('div');
    list.className = 'settings-array';
    const arr = Array.isArray(value) ? value : [];
    if (!arr.length) {
      const empty = document.createElement('p');
      empty.className = 'settings-placeholder';
      empty.textContent = 'Nincs elem.';
      list.appendChild(empty);
    }
    arr.forEach((item, index) => {
      const itemWrap = document.createElement('div');
      itemWrap.className = 'settings-array-item';
      const header = document.createElement('div');
      header.className = 'settings-array-item-header';
      const fallbackTitle = `${prettifyKey(key)} ${index + 1}`;
      const title = document.createElement('h4');
      if (key === 'rounds' && item && typeof item === 'object' && 'label' in item) {
        title.textContent = String(item.label || fallbackTitle);
      } else if (item && typeof item === 'object' && 'id' in item) {
        title.textContent = `${getLabelForPath(path, prettifyKey(key))} ${item.id}`;
      } else {
        title.textContent = fallbackTitle;
      }
      header.appendChild(title);
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'settings-array-item-remove';
      removeBtn.textContent = 'Eltávolítás';
      removeBtn.addEventListener('click', () => {
        const arrRef = getAtPath(settingsState.working, path);
        if (!Array.isArray(arrRef)) return;
        if (!window.confirm('Biztosan törlöd ezt az elemet?')) return;
        arrRef.splice(index, 1);
        markSettingsDirty();
        renderSettings();
      });
      header.appendChild(removeBtn);
      itemWrap.appendChild(header);
      const body = document.createElement('div');
      body.className = 'settings-array-item-body';
      if (item && typeof item === 'object' && !Array.isArray(item)) {
        Object.keys(item).forEach(childKey => {
          const childNode = renderNode(childKey, item[childKey], path.concat(index, childKey));
          if (childNode) {
            if (key === 'rounds' && childKey === 'label') {
              const inputEl = childNode.querySelector('input, textarea');
              if (inputEl) {
                inputEl.addEventListener('input', () => {
                  title.textContent = inputEl.value.trim() || fallbackTitle;
                });
              }
            }
            body.appendChild(childNode);
          }
        });
      } else {
        const valueNode = renderScalarField(`${index + 1}. elem`, item, path.concat(index), {labelOverride: `${index + 1}. elem`});
        if (valueNode) body.appendChild(valueNode);
      }
      itemWrap.appendChild(body);
      list.appendChild(itemWrap);
    });
    wrapper.appendChild(list);
    if (key === 'rounds') {
      const addBtn = document.createElement('button');
      addBtn.type = 'button';
      addBtn.className = 'settings-add-btn';
      addBtn.textContent = 'Új kör hozzáadása';
      addBtn.addEventListener('click', () => {
        const arrRef = getAtPath(settingsState.working, path);
        if (!Array.isArray(arrRef)) return;
        const ids = arrRef.map(entry => Number(entry?.id)).filter(num => Number.isFinite(num));
        const nextId = ids.length ? Math.max(...ids) + 1 : arrRef.length;
        const defaultLabel = nextId === 0 ? '0. kör' : `${nextId}. kör`;
        arrRef.push({id: nextId, label: defaultLabel, color: '#2563eb'});
        markSettingsDirty();
        renderSettings();
      });
      wrapper.appendChild(addBtn);
    }
    return wrapper;
  }

  function renderScalarField(key, value, path, opts = {}){
    const field = document.createElement('div');
    field.className = 'settings-field';
    const id = pathToId(path);
    const labelEl = document.createElement('label');
    labelEl.className = 'settings-label';
    const labelOverride = opts?.labelOverride;
    const labelText = labelOverride || getLabelForPath(path, key != null ? key : path[path.length - 1]);
    labelEl.textContent = labelText;
    labelEl.setAttribute('for', id);
    field.appendChild(labelEl);
    const control = document.createElement('div');
    control.className = 'settings-control';
    if (typeof value === 'boolean') {
      const toggleLabel = document.createElement('label');
      toggleLabel.className = 'switch';
      const input = document.createElement('input');
      input.type = 'checkbox';
      input.id = id;
      input.checked = Boolean(value);
      input.dataset.path = JSON.stringify(path);
      input.addEventListener('change', event => {
        const newVal = !!event.target.checked;
        if (setAtPath(settingsState.working, path, newVal)) {
          markSettingsDirty();
        }
      });
      const slider = document.createElement('span');
      slider.className = 'slider';
      toggleLabel.appendChild(input);
      toggleLabel.appendChild(slider);
      control.appendChild(toggleLabel);
      field.appendChild(control);
      return field;
    }
    if (typeof value === 'number') {
      const input = document.createElement('input');
      input.type = 'number';
      input.id = id;
      input.value = Number.isFinite(value) ? String(value) : '';
      input.step = Number.isInteger(value) ? '1' : 'any';
      input.dataset.path = JSON.stringify(path);
      input.addEventListener('input', event => {
        const raw = event.target.value;
        if (raw === '') {
          if (setAtPath(settingsState.working, path, null)) markSettingsDirty();
          return;
        }
        const parsed = Number(raw);
        if (!Number.isFinite(parsed)) return;
        if (setAtPath(settingsState.working, path, parsed)) {
          markSettingsDirty();
        }
      });
      control.appendChild(input);
      field.appendChild(control);
      return field;
    }
    const stringValue = value == null ? '' : String(value);
    const isColor = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(stringValue.trim());
    if (isColor) {
      const colorWrap = document.createElement('div');
      colorWrap.className = 'settings-color-control';
      const colorInput = document.createElement('input');
      colorInput.type = 'color';
      colorInput.id = id;
      colorInput.value = stringValue.trim();
      const textInput = document.createElement('input');
      textInput.type = 'text';
      textInput.value = stringValue;
      textInput.dataset.path = JSON.stringify(path);
      colorInput.addEventListener('input', event => {
        const newVal = event.target.value;
        textInput.value = newVal;
        if (setAtPath(settingsState.working, path, newVal)) {
          markSettingsDirty();
        }
      });
      textInput.addEventListener('input', event => {
        const newVal = event.target.value;
        colorInput.value = newVal && /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(newVal) ? newVal : colorInput.value;
        if (setAtPath(settingsState.working, path, newVal)) {
          markSettingsDirty();
        }
      });
      colorWrap.appendChild(colorInput);
      colorWrap.appendChild(textInput);
      control.appendChild(colorWrap);
      field.appendChild(control);
      return field;
    }
    if (stringValue.length > 120 || stringValue.includes('\n')) {
      const textarea = document.createElement('textarea');
      textarea.id = id;
      textarea.value = stringValue;
      textarea.dataset.path = JSON.stringify(path);
      textarea.addEventListener('input', event => {
        if (setAtPath(settingsState.working, path, event.target.value)) {
          markSettingsDirty();
        }
      });
      control.appendChild(textarea);
      field.appendChild(control);
      return field;
    }
    const input = document.createElement('input');
    input.type = 'text';
    input.id = id;
    input.value = stringValue;
    input.dataset.path = JSON.stringify(path);
    input.addEventListener('input', event => {
      if (setAtPath(settingsState.working, path, event.target.value)) {
        markSettingsDirty();
      }
    });
    control.appendChild(input);
    field.appendChild(control);
    return field;
  }

  function handleSettingsCancel(){
    if (settingsState.loading || !settingsState.dirty) return;
    const confirmReset = window.confirm('Biztosan elveted a módosításokat?');
    if (!confirmReset) return;
    settingsState.working = deepClone(settingsState.original);
    settingsState.dirty = false;
    renderSettings();
    updateSettingsButtons();
    setSettingsStatus('Változtatások visszavonva.');
  }

  function handleSettingsBack(){
    if (settingsState.loading) return;
    if (settingsState.dirty) {
      const confirmLeave = window.confirm('Nem mentett módosítások vannak. Biztosan visszalépsz?');
      if (!confirmLeave) return;
    }
    window.location.href = settingsBackBtn?.dataset?.href || homeUrl;
  }

  async function loadSettingsConfig(force = false){
    if (settingsState.loading) return;
    if (settingsState.original && !force) {
      renderSettings();
      updateSettingsButtons();
      return;
    }
    settingsState.loading = true;
    updateSettingsButtons();
    setSettingsStatus('Beállítások betöltése...');
    try {
      const response = await fetch(EP.configGet || 'api.php?action=config_get');
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const payload = await response.json();
      const config = payload && typeof payload === 'object' && payload.config && typeof payload.config === 'object'
        ? payload.config
        : payload;
      if (!config || typeof config !== 'object') {
        throw new Error('invalid_config_payload');
      }
      settingsState.original = deepClone(config);
      settingsState.working = deepClone(config);
      settingsState.dirty = false;
      renderSettings();
      setSettingsStatus('Beállítások betöltve.');
    } catch (err) {
      console.error(err);
      setSettingsStatus('Nem sikerült betölteni a beállításokat.', 'error');
    } finally {
      settingsState.loading = false;
      updateSettingsButtons();
    }
  }

  async function saveSettingsConfig(){
    if (!settingsState.dirty || settingsState.loading) return;
    settingsState.loading = true;
    updateSettingsButtons();
    setSettingsStatus('Mentés folyamatban...');
    try {
      const response = await fetch(EP.configSave || 'api.php?action=config_save', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(settingsState.working)
      });
      if (!response.ok) {
        const text = await response.text();
        throw new Error(text || `HTTP ${response.status}`);
      }
      const payload = await response.json();
      const newConfig = payload && typeof payload === 'object' && payload.config && typeof payload.config === 'object'
        ? payload.config
        : payload;
      if (!newConfig || typeof newConfig !== 'object') {
        throw new Error('invalid_config_payload');
      }
      settingsState.original = deepClone(newConfig);
      settingsState.working = deepClone(newConfig);
      settingsState.dirty = false;
      setSettingsStatus('Beállítások elmentve.', 'success');
      renderSettings();
    } catch (err) {
      console.error(err);
      setSettingsStatus('Nem sikerült elmenteni a beállításokat.', 'error');
    } finally {
      settingsState.loading = false;
      updateSettingsButtons();
    }
  }

  function initSettingsUI(){
    if (settingsBackBtn) {
      settingsBackBtn.addEventListener('click', handleSettingsBack);
    }
    if (settingsCancelBtn) {
      settingsCancelBtn.addEventListener('click', handleSettingsCancel);
    }
    if (settingsSaveBtn) {
      settingsSaveBtn.addEventListener('click', saveSettingsConfig);
    }
    window.addEventListener('beforeunload', event => {
      if (!settingsState.dirty) return;
      event.preventDefault();
      event.returnValue = '';
    });
    loadSettingsConfig();
  }

  initSettingsUI();
})();
