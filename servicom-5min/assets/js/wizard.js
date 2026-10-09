/* Wizard "Tu web en 5 minutos": una pantalla por paso, autoguardado, presentación con IA, listas dinámicas, construcción en vivo. */
(function () {
  'use strict';
  var h = S5.h, boot = S5.boot(), CFG = boot.cfg || {}, token = boot.token || '';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var LS = {
    g: function (k) { try { return localStorage.getItem(k); } catch (_) { return null; } },
    s: function (k, v) { try { localStorage.setItem(k, v); } catch (_) {} }
  };
  var ICONS = {
    up: '<path d="M6 15l6-6 6 6"/>', down: '<path d="M6 9l6 6 6-6"/>', copy: '<rect x="9" y="9" width="11.5" height="11.5" rx="2.5"/><path d="M5 15V6.5A2.5 2.5 0 017.500 4H15"/>',
    trash: '<path d="M4 7h16M10 3.500h4M6.500 7l1 13h9l1-13M10 11v6M14 11v6"/>', x: '<path d="M6 6l12 12M18 6L6 18"/>', image: '<rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="9" cy="10" r="1.6"/><path d="M21 16l-5-5-9 9"/>',
    plus: '<path d="M12 5v14M5 12h14"/>', up2: '<path d="M12 19V5m0 0l-6 6m6-6l6 6"/>', dup: '<rect x="8" y="8" width="12" height="12" rx="2.500"/><path d="M4 16V6a2 2 0 012-2h10"/>'
  };
  function ic(n) { var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); s.setAttribute('viewBox', '0 0 24 24'); s.setAttribute('class', 'ic'); s.setAttribute('fill', 'none'); s.setAttribute('stroke', 'currentColor'); s.setAttribute('stroke-width', '1.8'); s.setAttribute('stroke-linecap', 'round'); s.setAttribute('stroke-linejoin', 'round'); s.setAttribute('aria-hidden', 'true'); s.innerHTML = ICONS[n]; return s; }
  function isObj(x) { return x && typeof x === 'object' && !Array.isArray(x); }

  /* ---------- datos ---------- */
  function defaults() {
    return {
      plan: '', tarjeta_extra: false,
      negocio: { nombre: '', rubro: '', rubro_otro: '', idioma: 'es', estilo: 1, logo: null },
      dominio: { tiene: false, dominio: '', deseado: '' }, correos: [], correo_contacto: '',
      contenido: { frase: '', apoyo: '', banner: [], quienes: '', servicios: [], galeria: [], youtube: '' },
      tienda: { categorias: [], productos: [], umbral_stock: 5, correo_alertas: '', correo_pedidos: '', banco: { banco: '', numero: '', titular: '', tipo: '' }, contra_entrega: false, nota_entrega: '' },
      contacto: { whatsapp: '', whatsapp_msg: 'Hola, quiero más información.', telefono: '', direccion: '', mapa_url: '', horario: '', redes: { facebook: '', instagram: '', tiktok: '', youtube: '', x: '', linkedin: '' } },
      pago: { nombre_nit: '', comprobante: null },
      presentacion: { file: null, acepto: false, estado: 'ninguna', confirmada: false, usar: {}, fotos_usar: [] }, origen: {}
    };
  }
  function merge(a, b) { if (!isObj(b)) return a; Object.keys(b).forEach(function (k) { if (isObj(b[k]) && isObj(a[k])) merge(a[k], b[k]); else if (b[k] !== null || a[k] === null || a[k] === undefined) a[k] = b[k]; }); return a; }
  var draft = boot.draft || {};
  var data = merge(defaults(), isObj(draft.data) ? draft.data : {});
  if (!Array.isArray(data.correos)) data.correos = [];
  if (!isObj(data.origen)) data.origen = {};
  if (!data.plan && boot.plan) data.plan = boot.plan;
  var files = {}; try { files = JSON.parse(LS.g('s5_files_' + token) || '{}') || {}; } catch (_) {}
  if (isObj(draft.archivos)) Object.keys(draft.archivos).forEach(function (id) { var a = draft.archivos[id]; files[id] = { url: (a && (a.url_miniatura || a.url)) || (files[id] && files[id].url) || '' }; });
  function saveFiles() { var o = {}, n = 0; Object.keys(files).slice(-300).forEach(function (k) { if (files[k].url && files[k].url.indexOf('blob:') !== 0) { o[k] = { url: files[k].url }; n++; } }); LS.s('s5_files_' + token, JSON.stringify(o)); }
  function fileUrl(id) { return (files[id] && files[id].url) || ('/api/borrador/' + token + '/archivo/' + encodeURIComponent(id)); }
  function get(p) { return p.split('.').reduce(function (o, k) { return o == null ? o : o[k]; }, data); }
  function set(p, v) { var a = p.split('.'), o = data, i; for (i = 0; i < a.length - 1; i++) { if (!isObj(o[a[i]])) o[a[i]] = {}; o = o[a[i]]; } o[a[a.length - 1]] = v; }

  /* ---------- estado del wizard ---------- */
  var cur = '', built = false, building = false, paid = false, analysis = { estado: 'none', resultado: null, msg: '', dismissed: false }, returnTo = '', visited = {};
  try { visited = JSON.parse(LS.g('s5_vis_' + token) || '{}') || {}; } catch (_) {}
  var t0 = parseInt(draft.creado_en, 10) || parseInt(LS.g('s5_t0_' + token), 10) || Math.floor(Date.now() / 1000);
  if (token) LS.s('s5_t0_' + token, String(t0));
  var dEstado = String(draft.estado || '');
  if (/listo|lista|pago|publica/.test(dEstado)) built = true;
  if (/pago_revisar|publica/.test(dEstado)) paid = true;
  if (/constru/.test(dEstado)) building = true;

  var ORDER = ['plan', 'pres', 'revision', 'negocio', 'dominio', 'contenido', 'productos', 'cobros', 'contacto', 'resumen', 'pago'];
  var TITLES = { plan: 'Plan', pres: 'Presentación', revision: 'Revisión', negocio: 'Tu negocio', dominio: 'Dominio y correos', contenido: 'Contenido', productos: 'Productos', cobros: 'Cobros', contacto: 'Contacto', resumen: 'Resumen', pago: 'Pago' };
  function tienda() { return data.plan === 'tienda'; }
  function revAvail() { return analysis.estado === 'lista' && analysis.resultado && !data.presentacion.confirmada && data.presentacion.estado !== 'omitida' && !!data.presentacion.file; }
  function steps() {
    if (!data.plan || !token) return ['plan'];
    return ORDER.filter(function (k) { return k === 'revision' ? revAvail() : (k === 'productos' || k === 'cobros') ? tienda() : true; });
  }

  /* ---------- guardado automático ---------- */
  var sv = { dirty: false, busy: false, again: false, fails: 0, timer: null }, saveEl = $('#save'), saveT = $('#save-t');
  function setSave(c, t) { saveEl.className = 'wz-save ' + c; saveT.textContent = t; }
  function dirty() { sv.dirty = true; clearTimeout(sv.timer); sv.timer = setTimeout(flush, 800); }
  function flush() {
    clearTimeout(sv.timer);
    if (!token || !sv.dirty) return Promise.resolve();
    if (sv.busy) { sv.again = true; return Promise.resolve(); }
    sv.busy = true; sv.dirty = false; setSave('busy', 'Guardando…');
    return S5.api('POST', '/api/borrador/' + token + '/guardar', { data: data, paso: cur }).then(function (r) {
      sv.busy = false;
      if (r.ok) { sv.fails = 0; if (!sv.dirty) setSave('ok', 'Guardado ✓'); }
      else { sv.dirty = true; sv.fails++; setSave('bad', r._net ? 'Sin conexión, reintentando…' : (sv.fails > 3 ? 'No se pudo guardar' : 'Reintentando…')); clearTimeout(sv.timer); sv.timer = setTimeout(flush, Math.min(15000, 2000 * sv.fails)); }
      if (sv.again) { sv.again = false; sv.dirty = true; return flush(); }
    });
  }
  window.addEventListener('pagehide', function () {
    if (token && sv.dirty) { try { fetch('/api/borrador/' + token + '/guardar', { method: 'POST', keepalive: true, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF': S5.csrf() }, body: JSON.stringify({ data: data, paso: cur }) }); } catch (_) {} }
  });
  document.addEventListener('visibilitychange', function () { if (document.hidden) flush(); });

  /* ---------- validación ---------- */
  var RULES = {
    req: function (v) { return String(v == null ? '' : v).trim() ? '' : 'Este dato es obligatorio.'; },
    email: function (v) { v = String(v).trim(); return !v || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) ? '' : 'Escribe un correo válido, por ejemplo nombre@correo.com.'; },
    url: function (v) { v = String(v).trim(); return !v || /^https?:\/\/[^\s/.]+\.[^\s]{2,}$/i.test(v) ? '' : 'Escribe el enlace completo, empezando con https://'; },
    domain: function (v) { v = String(v).trim(); return !v || /^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+com(\.[a-z]{2})?$/.test(v) ? '' : 'Escribe tu dominio .com, por ejemplo tunegocio.com'; },
    domname: function (v) { v = String(v).trim(); return !v || /^[a-z0-9]([a-z0-9-]{0,58}[a-z0-9])?$/.test(v) ? '' : 'Usa solo letras, números y guiones, sin espacios.'; },
    phone: function (v) { v = String(v).trim(); var d = v.replace(/\D/g, ''); return !v || (/^[\d\s()+-]+$/.test(v) && d.length >= 7 && d.length <= 15) ? '' : 'Escribe un teléfono válido, solo números.'; },
    int: function (v) { v = String(v).trim(); return v === '' || /^\d{1,6}$/.test(v) ? '' : 'Escribe un número entero, por ejemplo 5.'; },
    acct: function (v) { v = String(v).trim(); return !v || /^[\d-]{4,30}$/.test(v) ? '' : 'Escribe solo números (y guiones si los usa tu banco).'; },
    social: function (v) { v = String(v).trim(); return !v || (!/\s/.test(v) && v.length >= 3) ? '' : 'Escribe el enlace o @usuario, sin espacios.'; },
    price: function (v) { var n = parsePrice(v); return n === null ? 'Escribe el precio en quetzales, por ejemplo 125.50' : ''; }
  };
  function parsePrice(v) {
    v = String(v == null ? '' : v).replace(/[Qq\s]/g, '');
    if (!v) return null;
    if (/^\d+,\d{1,2}$/.test(v)) v = v.replace(',', '.'); else v = v.replace(/,/g, '');
    if (!/^\d{1,7}(\.\d{1,2})?$/.test(v)) return null;
    return parseFloat(v);
  }
  function show(inp, er, msg) { if (er) { er.textContent = msg || ''; er.classList.toggle('on', !!msg); } if (inp) { if (msg) inp.setAttribute('aria-invalid', 'true'); else inp.removeAttribute('aria-invalid'); } }
  function errOf(el) { return el.id ? document.getElementById(el.id + '-e') : null; }
  function validateEl(el) {
    if (!el.offsetParent && el.type !== 'radio') return true;
    var msg = '', v = el.value;
    (el.getAttribute('data-v') || '').split(' ').forEach(function (r) { if (!msg && r && RULES[r]) msg = RULES[r](v); });
    show(el, errOf(el), msg); return !msg;
  }
  function fmt(el) {
    var f = el.getAttribute('data-fmt'), v = el.value, o = v;
    if (f === 'domain') v = v.trim().toLowerCase().replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/[/?#].*$/, '').replace(/\s+/g, '');
    else if (f === 'domname') v = v.trim().toLowerCase().replace(/\.com.*$/, '').replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '').replace(/^-+|-+$/g, '');
    else v = v.trim();
    if (v !== o) { el.value = v; if (el.dataset.k) set(el.dataset.k, v); dirty(); }
  }

  /* ---------- enlace de datos <-> DOM ---------- */
  function readEl(el) {
    var t = el.dataset.t;
    if (el.type === 'checkbox') return el.checked;
    if (el.type === 'radio') return t === 'bool' ? el.value === '1' : t === 'int' ? parseInt(el.value, 10) : el.value;
    if ((el.getAttribute('data-v') || '').split(' ').indexOf('int') > -1) return /^\d{1,6}$/.test(el.value.trim()) ? parseInt(el.value, 10) : el.value;
    return el.value;
  }
  function paintEl(el) {
    var v = get(el.dataset.k);
    if (el.type === 'checkbox') el.checked = !!v;
    else if (el.type === 'radio') el.checked = (el.dataset.t === 'bool' ? (v ? '1' : '0') : String(v)) === el.value;
    else el.value = v == null ? '' : v;
  }
  function paint() { $$('[data-k]').forEach(paintEl); paintOrigen(); derived(); }
  function paintOrigen() { $$('[data-o]').forEach(function (n) { n.hidden = data.origen[n.getAttribute('data-o')] !== 'presentacion'; }); }
  var root = $('#wz');
  function onIn(e) {
    var el = e.target, k = el.dataset && el.dataset.k;
    if (!k) return;
    if (el.type === 'radio' && !el.checked) return;
    set(k, readEl(el));
    if (data.origen[k]) { delete data.origen[k]; paintOrigen(); }
    if (el.type === 'radio' || el.type === 'checkbox' || el.tagName === 'SELECT') $$('[data-k="' + k + '"]').forEach(function (o) { if (o !== el) paintEl(o); });
    derived(k); dirty();
    if (e.type === 'input' && el.getAttribute('aria-invalid') === 'true') validateEl(el);
    if (el.type === 'radio') { var ee = document.getElementById('f-' + k.replace(/[._]/g, '-') + '-e'); if (ee) show(null, ee, ''); }
  }
  root.addEventListener('input', onIn); root.addEventListener('change', onIn);
  root.addEventListener('focusout', function (e) { var el = e.target; if (el.dataset && el.dataset.k && el.type !== 'radio' && el.type !== 'checkbox' && el.tagName !== 'SELECT') { fmt(el); validateEl(el); } });

  /* efectos dependientes */
  function derived(k) {
    var otro = data.negocio.rubro === 'otro'; $('#rubro-otro').hidden = !otro;
    $('#dom-si').hidden = !data.dominio.tiene; $('#dom-no').hidden = !!data.dominio.tiene;
    var d = data.dominio.tiene ? data.dominio.dominio : (data.dominio.deseado ? data.dominio.deseado + '.com' : '');
    $('#mail-sfx').textContent = '@' + (d || 'tudominio.com');
    var t = tienda(); $('#srv-req').hidden = t; $('#srv-opt').hidden = !t;
    $('#pres-h').hidden = !!data.presentacion.acepto;
    var dr = $('#pres-drop'), pf = $('#pres-file'), on = !!data.presentacion.acepto && !data.presentacion.file;
    dr.setAttribute('aria-disabled', on ? 'false' : 'true'); pf.disabled = !on;
    if (k === 'plan') { drawSide(); }
  }

  /* ---------- navegación ---------- */
  var navBox = $('#nav'), btnBack = $('#nav-back'), btnNext = $('#nav-next'), nextT = $('#nav-next-t');
  function go(key, o) {
    o = o || {};
    if (!key) return;
    var list = steps();
    if (list.indexOf(key) < 0) key = key === 'revision' ? 'negocio' : list[0];
    if (key === 'pago' && !built) key = 'resumen';
    cur = key; visited[key] = 1; LS.s('s5_vis_' + token, JSON.stringify(visited)); if (token) LS.s('s5_paso_' + token, key);
    $$('.step').forEach(function (s) { s.hidden = s.getAttribute('data-step') !== key; });
    if (!o.noPush) { try { history.pushState({ s: key }, '', location.pathname + location.search); } catch (_) {} }
    chrome(); stepEnter(key);
    if (!o.quiet) { window.scrollTo(0, 0); var t = $('#t-' + key); if (t) t.focus({ preventScroll: true }); }
    if (token && !o.quiet) { sv.dirty = true; dirty(); }
  }
  window.addEventListener('popstate', function (e) { var s = e.state && e.state.s; if (s) go(s, { noPush: true }); });
  function chrome() {
    var list = steps(), i = Math.max(0, list.indexOf(cur)), n = list.length;
    if (n < 2) n = 9;
    $('#prog-n').textContent = 'Paso ' + (i + 1) + ' de ' + n; $('#prog-name').textContent = TITLES[cur] || '';
    var p = Math.round(((i + 1) / n) * 100); $('#prog-bar').style.width = p + '%'; $('#prog').setAttribute('aria-valuenow', p);
    var hideNav = cur === 'revision' || cur === 'pago' && paid;
    navBox.hidden = hideNav;
    btnBack.hidden = i === 0;
    var nx = cur !== 'resumen' && cur !== 'pago' && !(cur === 'pres' && !data.presentacion.file);
    btnNext.hidden = !nx; nextT.textContent = cur === 'pres' ? 'Continuar mientras la leemos' : 'Continuar';
    $('#pres-skip').hidden = !!data.presentacion.file;
    drawSide(); drawPill(); derived();
    $('#copy-link').hidden = !token;
    $('#tip').hidden = !token || cur === 'plan' || LS.g('s5_tip') === '1';
  }
  function drawSide() {
    var side = $('.wz-side'), ol = $('#side'), list = steps();
    side.hidden = list.length < 2; ol.textContent = '';
    list.forEach(function (k, i) {
      var idx = list.indexOf(cur), ok = k !== 'pago' || built;
      var b = h('button', { type: 'button', text: TITLES[k], disabled: !(visited[k] || i < idx) || !ok || k === cur ? true : null, onclick: function () { go(k); } });
      if (k === cur) b.setAttribute('aria-current', 'step');
      ol.appendChild(h('li', { class: k === cur ? 'cur' : (i < idx || visited[k]) && k !== cur ? 'done' : '' }, b));
    });
  }
  btnBack.addEventListener('click', function () { var l = steps(), i = l.indexOf(cur); if (i > 0) go(l[i - 1]); });
  btnNext.addEventListener('click', next);
  function next() {
    if (!validateStep(cur)) return;
    var run = function () { var l = steps(), i = l.indexOf(cur); go(l[Math.min(l.length - 1, i + 1)]); };
    if (cur === 'plan' && !token) { btnNext.disabled = true; createDraft().then(function (ok) { btnNext.disabled = false; if (ok) run(); }); return; }
    run();
  }
  function createDraft() {
    return S5.api('POST', '/api/borrador', { plan: data.plan }).then(function (r) {
      if (!r.ok || !r.token) { show(null, $('#f-plan-e'), r.error || 'No pudimos iniciar tu borrador. Inténtalo de nuevo.'); return false; }
      token = r.token; LS.s('s5_t0_' + token, String(t0));
      try { history.replaceState({ s: 'plan' }, '', '/continuar/' + token); } catch (_) {}
      sv.dirty = true; flush(); return true;
    });
  }

  /* ---------- validación por paso ---------- */
  function firstBad(sel) { var b = $(sel); if (b) { b.scrollIntoView({ block: 'center', behavior: 'smooth' }); b.focus({ preventScroll: true }); } }
  function pruneList(arr, empty) { for (var i = arr.length - 1; i >= 0; i--) if (empty(arr[i])) arr.splice(i, 1); }
  function waNational() { return ($('#wa-n').value || '').replace(/\D/g, ''); }
  function waErr() {
    var cc = $('#wa-cc').value, n = waNational(), m = '';
    if (!n) m = 'Escribe tu número de WhatsApp.';
    else if (cc === '502' && n.length !== 8) m = 'En Guatemala el número tiene 8 dígitos, por ejemplo 5555 1234.';
    else if (n.length < 6 || n.length > 12) m = 'Revisa el número: parece incompleto.';
    return m;
  }
  var CHK = {
    plan: function () { if (!data.plan) { show(null, $('#f-plan-e'), 'Elige el plan que más te conviene.'); firstBad('input[name=plan]'); return false; } show(null, $('#f-plan-e'), ''); return true; },
    negocio: function () {
      var ok = true, bad = null;
      $$('.step[data-step=negocio] [data-v]').forEach(function (el) { if (!validateEl(el)) { ok = false; bad = bad || el; } });
      if (!data.negocio.rubro) { show(null, $('#f-negocio-rubro-e'), 'Elige el rubro de tu negocio.'); ok = false; bad = bad || $('input[name="negocio.rubro"]'); } else show(null, $('#f-negocio-rubro-e'), '');
      if (data.negocio.rubro === 'otro') { var o = $('#f-negocio-rubro-otro'); if (!String(data.negocio.rubro_otro).trim()) { show(o, errOf(o), 'Cuéntanos cuál es tu rubro.'); ok = false; bad = bad || o; } }
      if (bad) bad.focus(); return ok;
    },
    dominio: function () {
      var ok = true, bad = null;
      $$('.step[data-step=dominio] [data-v]').forEach(function (el) { if (!validateEl(el)) { ok = false; bad = bad || el; } });
      var d = $('#f-dominio-dominio');
      if (data.dominio.tiene && !String(data.dominio.dominio).trim()) { show(d, errOf(d), 'Escribe tu dominio, por ejemplo tunegocio.com'); ok = false; bad = bad || d; }
      if (bad) bad.focus(); return ok;
    },
    contenido: function () {
      var ok = true; var arr = data.contenido.servicios;
      pruneList(arr, function (s) { return !String(s.nombre).trim() && !String(s.descripcion).trim() && !s.foto; }); lists.srv.render();
      var bad = null;
      $$('.step[data-step=contenido] [data-v]').forEach(function (el) { if (!validateEl(el)) { ok = false; bad = bad || el; } });
      if (!tienda() && !arr.length) { show(null, $('#srv-e'), 'Agrega al menos un servicio.'); ok = false; bad = bad || $('#srv-add'); }
      else { var i = arr.findIndex(function (s) { return !String(s.nombre).trim(); }); if (i > -1) { lists.srv.open(i, true); ok = false; bad = null; } else show(null, $('#srv-e'), ''); }
      if (bad) bad.focus(); return ok;
    },
    productos: function () {
      var ok = true, arr = data.tienda.productos, bad = null;
      pruneList(arr, function (p) { return !String(p.nombre).trim() && p.precio === '' && !p.foto && !String(p.descripcion).trim(); }); lists.prd.render();
      $$('.step[data-step=productos] [data-v]').forEach(function (el) { if (!validateEl(el)) { ok = false; bad = bad || el; } });
      if (!arr.length) { show(null, $('#prd-e'), 'Agrega al menos un producto.'); ok = false; bad = bad || $('#prd-add'); }
      else { var i = arr.findIndex(function (p) { return !String(p.nombre).trim() || parsePrice(p.precio) === null; }); if (i > -1) { lists.prd.open(i, true); ok = false; bad = null; } else show(null, $('#prd-e'), ''); }
      if (bad) bad.focus(); return ok;
    },
    cobros: function () {
      var ok = true, bad = null;
      $$('.step[data-step=cobros] [data-v]').forEach(function (el) { if (!validateEl(el)) { ok = false; bad = bad || el; } });
      if (bad) bad.focus(); return ok;
    },
    contacto: function () {
      var ok = true, bad = null, m = waErr();
      show($('#wa-n'), $('#wa-e'), m); if (m) { ok = false; bad = $('#wa-n'); }
      $$('.step[data-step=contacto] [data-v]').forEach(function (el) { if (el.closest('details') && !el.closest('details').open && !el.value) return; if (!validateEl(el)) { ok = false; bad = bad || el; } });
      if (bad) { var dt = bad.closest('details'); if (dt) dt.open = true; bad.focus(); } return ok;
    }
  };
  function validateStep(k) { return CHK[k] ? CHK[k]() : true; }
  function missing() {
    var m = [];
    if (!data.plan) m.push(['plan', 'Elige tu plan']);
    if (!String(data.negocio.nombre).trim()) m.push(['negocio', 'Nombre del negocio']);
    if (!data.negocio.rubro || (data.negocio.rubro === 'otro' && !String(data.negocio.rubro_otro).trim())) m.push(['negocio', 'Rubro']);
    var wa = String(data.contacto.whatsapp || '').replace(/\D/g, ''); if (wa.length < 8) m.push(['contacto', 'WhatsApp']);
    if (tienda()) { if (!data.tienda.productos.some(function (p) { return String(p.nombre).trim(); })) m.push(['productos', 'Al menos 1 producto']); if (!String(data.tienda.correo_pedidos).trim()) m.push(['cobros', 'Correo para recibir pedidos']); }
    else if (!data.contenido.servicios.some(function (s) { return String(s.nombre).trim(); })) m.push(['contenido', 'Al menos 1 servicio']);
    return m;
  }

  /* ---------- subidas ---------- */
  var IMG_OK = /^image\/(jpeg|png|webp|gif)$/;
  function sendImage(file, tipo, prog) {
    if (!IMG_OK.test(file.type)) return Promise.resolve({ ok: false, error: /heic|heif/i.test(file.type + file.name) ? 'Esa foto está en formato HEIC. Guárdala como JPG o PNG e inténtalo de nuevo.' : 'Sube una imagen JPG, PNG o WebP.' });
    if (file.size > 30 * 1048576) return Promise.resolve({ ok: false, error: 'La imagen pesa más de 30 MB.' });
    return S5.compress(file, { logo: tipo === 'logo' }).then(function (f) {
      var local = URL.createObjectURL(f);
      return S5.upload(token, tipo, f, prog).then(function (r) { if (r.ok && r.id) { files[r.id] = { url: r.url_miniatura || local }; saveFiles(); } return r; });
    });
  }
  function delFile(id) { if (id && token) S5.api('DELETE', '/api/borrador/' + token + '/archivo/' + encodeURIComponent(id)); }
  function imgEl(id, alt) {
    var i = h('img', { src: fileUrl(id), alt: alt || '', loading: 'lazy', decoding: 'async' });
    i.addEventListener('error', function () { i.remove(); }); return i;
  }
  /* campo de una sola foto sobre obj[key] */
  function photoField(obj, key, tipo, label) {
    var th = h('span', { class: 'thumb' }), err = h('p', { class: 'err', role: 'alert' }), inp = h('input', { type: 'file', accept: 'image/*', class: 'sr', tabindex: '-1', 'aria-label': 'Elegir ' + label });
    var btn = h('button', { type: 'button', class: 'btn btn--ghost btn--sm', onclick: function () { inp.click(); } });
    var rm = h('button', { type: 'button', class: 'btn btn--ghost btn--sm', text: 'Quitar', onclick: function () { delFile(obj[key]); obj[key] = null; draw(); dirty(); } });
    function draw(busy) {
      th.textContent = ''; if (obj[key]) th.appendChild(imgEl(obj[key])); else th.appendChild(ic('image'));
      btn.textContent = busy ? 'Subiendo…' : obj[key] ? 'Cambiar foto' : 'Subir ' + label; btn.disabled = !!busy; rm.hidden = !obj[key] || !!busy;
    }
    inp.addEventListener('change', function () {
      var f = inp.files[0]; inp.value = ''; if (!f) return; err.textContent = ''; err.classList.remove('on'); draw(true);
      sendImage(f, tipo).then(function (r) { if (r.ok) { if (obj[key]) delFile(obj[key]); obj[key] = r.id; dirty(); } else { err.textContent = r.error || 'No pudimos subir la foto.'; err.classList.add('on'); } draw(); });
    });
    draw();
    return h('div', { class: 'field' }, [h('div', { class: 'pf' }, [th, h('div', { class: 'logo-f__b' }, [btn, rm, inp])]), err]);
  }
  /* galería de varias fotos sobre un arreglo de ids */
  function gallery(box, path, max, errEl) {
    function arr() { var a = get(path); if (!Array.isArray(a)) { a = []; set(path, a); } return a; }
    var inp = h('input', { type: 'file', accept: 'image/*', multiple: true, class: 'sr', tabindex: '-1', 'aria-label': 'Elegir fotos' });
    var busy = 0;
    function say(m) { if (errEl) { errEl.textContent = m || ''; errEl.classList.toggle('on', !!m); } }
    function draw() {
      box.textContent = '';
      arr().forEach(function (id, i) {
        box.appendChild(h('div', { class: 'gi' }, [h('span', { class: 'thumb' }, imgEl(id, 'Foto ' + (i + 1))), h('button', { type: 'button', class: 'rm', 'aria-label': 'Quitar foto ' + (i + 1), onclick: function () { arr().splice(i, 1); delFile(id); draw(); dirty(); } }, ic('x'))]));
      });
      if (busy) box.appendChild(h('div', { class: 'add', text: 'Subiendo ' + busy + '…', role: 'status' }));
      else if (arr().length < max) box.appendChild(h('button', { type: 'button', class: 'add', onclick: function () { inp.click(); } }, [ic('plus'), 'Agregar foto']));
      box.appendChild(inp);
    }
    inp.addEventListener('change', function () {
      var fs = [].slice.call(inp.files).slice(0, max - arr().length); inp.value = ''; say('');
      var chain = Promise.resolve();
      fs.forEach(function (f) { busy++; draw(); chain = chain.then(function () { return sendImage(f, 'foto'); }).then(function (r) { busy--; if (r.ok) { arr().push(r.id); dirty(); } else say(r.error || 'No pudimos subir una de las fotos.'); draw(); }); });
    });
    draw(); return { draw: draw };
  }

  /* ---------- listas dinámicas ---------- */
  var uid = 0;
  function fld(label, o) {
    var id = 'i' + (++uid), inp = h(o.area ? 'textarea' : 'input', { class: 'inp', id: id, maxlength: o.max, inputmode: o.im, placeholder: o.ph, autocomplete: 'off', 'aria-describedby': id + '-e', rows: o.area ? 3 : null, type: o.area ? null : 'text', enterkeyhint: 'next' });
    var er = h('p', { class: 'err', id: id + '-e' }); inp.value = o.obj[o.key] == null ? '' : o.obj[o.key];
    function chk() { var m = o.req && !String(inp.value).trim() ? 'Este dato es obligatorio.' : (o.v && RULES[o.v] ? RULES[o.v](inp.value) : ''); show(inp, er, m); return !m; }
    inp.addEventListener('input', function () { o.obj[o.key] = o.conv ? o.conv(inp.value) : inp.value; if (o.on) o.on(); dirty(); if (inp.getAttribute('aria-invalid') === 'true') chk(); });
    inp.addEventListener('blur', chk);
    var lab = h('label', { for: id }, [label, o.req ? h('span', { class: 'req', text: ' *', 'aria-hidden': 'true' }) : null, o.opt ? h('span', { class: 'opt', text: ' (opcional)' }) : null]);
    return h('div', { class: 'field' }, [lab, o.pre ? h('div', { class: 'pfx' }, [h('span', { text: o.pre, 'aria-hidden': 'true' }), inp]) : inp, er]);
  }
  function List(c) {
    var box = c.box, openIdx = -1, self = {};
    function arr() { return get(c.path); }
    function upd() { if (c.count) c.count.textContent = arr().length ? '(' + arr().length + ')' : ''; }
    function row(it, i) {
      var isOpen = i === openIdx, bid = 'lb' + (++uid);
      var t = h('b', { text: c.title(it, i) }), s = h('small', { text: c.sub(it) });
      var head = h('button', { type: 'button', class: 'item__h', 'aria-expanded': isOpen ? 'true' : 'false', 'aria-controls': bid, onclick: function () { openIdx = isOpen ? -1 : i; self.render(); if (openIdx > -1) focusRow(); } },
        [h('span', { class: 'item__n', text: String(i + 1) }), h('span', { class: 'item__t' }, [t, s, it.origen === 'pres' ? h('span', { class: 'tag', text: 'Tomado de tu presentación' }) : null]), ic('down').cloneNode(true)]);
      head.lastChild.setAttribute('class', 'ic chev');
      var el = h('div', { class: 'item' + (isOpen ? ' open' : '') }, head);
      if (isOpen) {
        var body = h('div', { class: 'item__b', id: bid, role: 'group', 'aria-label': c.noun + ' ' + (i + 1) });
        c.body(it, i, body, function () { t.textContent = c.title(it, i); s.textContent = c.sub(it); });
        var del = h('button', { type: 'button', class: 'ib del' }, [ic('trash'), h('span', { text: 'Eliminar' })]), armed = 0;
        del.addEventListener('click', function () {
          if (!armed) { armed = 1; del.lastChild.textContent = '¿Seguro?'; setTimeout(function () { armed = 0; del.lastChild.textContent = 'Eliminar'; }, 3000); return; }
          arr().splice(i, 1); delFile(it.foto); openIdx = -1; self.render(); dirty();
        });
        body.appendChild(h('div', { class: 'item__a' }, [
          h('button', { type: 'button', class: 'ib', disabled: i === 0 ? true : null, 'aria-label': 'Mover arriba', onclick: function () { mv(i, -1); } }, ic('up')),
          h('button', { type: 'button', class: 'ib', disabled: i === arr().length - 1 ? true : null, 'aria-label': 'Mover abajo', onclick: function () { mv(i, 1); } }, ic('down')),
          h('button', { type: 'button', class: 'ib', onclick: function () { var cp = JSON.parse(JSON.stringify(it)); delete cp.origen; arr().splice(i + 1, 0, cp); openIdx = i + 1; self.render(); dirty(); focusRow(); } }, [ic('dup'), h('span', { text: 'Duplicar' })]), del]));
        el.appendChild(body);
      }
      return el;
    }
    function mv(i, d) { var a = arr(), x = a.splice(i, 1)[0]; a.splice(i + d, 0, x); openIdx = i + d; self.render(); dirty(); }
    function focusRow() { var r = box.querySelector('.item.open'); if (r) { r.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); var f = r.querySelector('input,textarea,select'); if (f) f.focus({ preventScroll: true }); } }
    self.render = function () { if (arr().length && c.err) show(null, c.err, ''); box.textContent = ''; var fr = document.createDocumentFragment(); arr().forEach(function (it, i) { fr.appendChild(row(it, i)); }); box.appendChild(fr); upd(); };
    self.open = function (i, validate) { openIdx = i; self.render(); var r = box.querySelector('.item.open'); if (r && validate) { $$('input,textarea,select', r).forEach(function (f) { f.dispatchEvent(new Event('blur')); }); } focusRow(); };
    self.add = function () { arr().push(c.blank()); openIdx = arr().length - 1; self.render(); dirty(); focusRow(); };
    c.addBtn.addEventListener('click', self.add);
    return self;
  }
  var lists = {};
  function initLists() {
    lists.srv = List({
      box: $('#l-srv'), err: $('#srv-e'), path: 'contenido.servicios', addBtn: $('#srv-add'), count: $('#srv-n'), noun: 'Servicio',
      blank: function () { return { nombre: '', descripcion: '', foto: null, origen: 'form' }; },
      title: function (s, i) { return String(s.nombre).trim() || 'Servicio ' + (i + 1) + ' (sin nombre)'; },
      sub: function (s) { return String(s.descripcion || '').trim(); },
      body: function (s, i, b, upd) {
        b.appendChild(fld('Nombre del servicio', { obj: s, key: 'nombre', max: 60, req: 1, ph: 'Ej. Asesoría legal', on: upd }));
        b.appendChild(fld('Descripción de una línea', { obj: s, key: 'descripcion', max: 140, opt: 1, on: upd, ph: 'Qué incluye o en qué ayuda' }));
        b.appendChild(photoField(s, 'foto', 'foto', 'foto'));
      }
    });
    lists.prd = List({
      box: $('#l-prd'), err: $('#prd-e'), path: 'tienda.productos', addBtn: $('#prd-add'), count: $('#prd-n'), noun: 'Producto',
      blank: function () { return { nombre: '', categoria: '', precio: '', descripcion: '', foto: null, stock: '', origen: 'form' }; },
      title: function (p, i) { return String(p.nombre).trim() || 'Producto ' + (i + 1) + ' (sin nombre)'; },
      sub: function (p) { var n = parsePrice(p.precio); return (n !== null ? 'Q' + n.toLocaleString('en-US') : '') + (p.categoria ? ' · ' + p.categoria : ''); },
      body: function (p, i, b, upd) {
        b.appendChild(fld('Nombre del producto', { obj: p, key: 'nombre', max: 80, req: 1, on: upd }));
        var sel = h('select', { class: 'inp', id: 'c' + (++uid) }, [h('option', { value: '', text: 'Sin categoría' })]);
        data.tienda.categorias.forEach(function (c) { if (String(c.nombre).trim()) sel.appendChild(h('option', { value: c.nombre, text: (c.padre ? c.padre + ' › ' : '') + c.nombre })); });
        if (p.categoria && ![].some.call(sel.options, function (o) { return o.value === p.categoria; })) sel.appendChild(h('option', { value: p.categoria, text: p.categoria }));
        sel.value = p.categoria || ''; sel.addEventListener('change', function () { p.categoria = sel.value; upd(); dirty(); });
        var r2 = h('div', { class: 'row2' }, [
          fld('Precio', { obj: p, key: 'precio', req: 1, pre: 'Q', im: 'decimal', max: 12, v: 'price', conv: function (v) { var n = parsePrice(v); return n === null ? v : n; }, on: upd, ph: '125.50' }),
          fld('Existencias', { obj: p, key: 'stock', opt: 1, im: 'numeric', max: 6, v: 'int', conv: function (v) { return /^\d{1,6}$/.test(v.trim()) ? parseInt(v, 10) : v; }, ph: '10' })]);
        b.appendChild(h('div', { class: 'field' }, [h('label', { for: sel.id, text: 'Categoría' }), sel]));
        b.appendChild(r2);
        b.appendChild(fld('Descripción', { obj: p, key: 'descripcion', max: 300, opt: 1, area: 1 }));
        b.appendChild(photoField(p, 'foto', 'foto', 'foto'));
      }
    });
    initCats();
  }
  function initCats() {
    var box = $('#l-cat');
    function draw() {
      box.textContent = '';
      data.tienda.categorias.forEach(function (c, i) {
        var id = 'k' + (++uid), old = c.nombre;
        var inp = h('input', { class: 'inp', id: id, value: c.nombre, maxlength: 60, placeholder: 'Nombre de la categoría', 'aria-label': 'Nombre de la categoría ' + (i + 1), autocomplete: 'off' });
        inp.addEventListener('input', function () { c.nombre = inp.value; dirty(); });
        inp.addEventListener('focus', function () { old = c.nombre; });
        inp.addEventListener('change', function () {
          if (old && old !== c.nombre) { data.tienda.productos.forEach(function (p) { if (p.categoria === old) p.categoria = c.nombre; }); data.tienda.categorias.forEach(function (x) { if (x.padre === old) x.padre = c.nombre; }); }
          draw(); lists.prd.render();
        });
        var sel = h('select', { class: 'inp', 'aria-label': 'Categoría principal de ' + (c.nombre || 'esta categoría') }, [h('option', { value: '', text: 'Es una categoría principal' })]);
        data.tienda.categorias.forEach(function (o, j) { if (j !== i && !o.padre && String(o.nombre).trim()) sel.appendChild(h('option', { value: o.nombre, text: 'Subcategoría de ' + o.nombre })); });
        sel.value = c.padre || ''; sel.addEventListener('change', function () { c.padre = sel.value; dirty(); });
        box.appendChild(h('div', { class: 'crow' }, [inp, h('button', { type: 'button', class: 'ib', 'aria-label': 'Eliminar categoría ' + (c.nombre || i + 1), onclick: function () { var nm = c.nombre; data.tienda.categorias.splice(i, 1); data.tienda.productos.forEach(function (p) { if (p.categoria === nm) p.categoria = ''; }); data.tienda.categorias.forEach(function (x) { if (x.padre === nm) x.padre = ''; }); draw(); dirty(); } }, ic('trash')), sel]));
      });
      $('#cat-n').textContent = data.tienda.categorias.length ? '(' + data.tienda.categorias.length + ')' : '';
    }
    $('#cat-add').addEventListener('click', function () { data.tienda.categorias.push({ nombre: '', padre: '', origen: 'form' }); draw(); dirty(); var r = box.lastChild; if (r) r.querySelector('input').focus(); });
    lists.cat = { render: draw };
  }

  /* ---------- correos (chips) ---------- */
  function drawMails() {
    var ul = $('#mail-chips'); ul.textContent = '';
    data.correos.forEach(function (c, i) { ul.appendChild(h('li', null, [h('span', { text: c }), h('button', { type: 'button', 'aria-label': 'Quitar el correo ' + c, onclick: function () { data.correos.splice(i, 1); drawMails(); dirty(); } }, ic('x'))])); });
  }
  function addMail() {
    var inp = $('#mail-in'), v = inp.value.trim().toLowerCase().replace(/@.*$/, '').replace(/[,;\s]+$/g, ''), er = $('#mail-e');
    if (!v) { if (inp.value.trim()) show(inp, er, 'Escribe solo lo anterior al @, por ejemplo ventas.'); return; }
    var m = !/^[a-z0-9][a-z0-9._-]{0,29}$/.test(v) ? 'Usa letras, números, punto o guion, sin espacios ni acentos.' : data.correos.indexOf(v) > -1 ? 'Ese correo ya está en la lista.' : data.correos.length >= 10 ? 'Puedes tener hasta 10 correos.' : '';
    show(inp, er, m); if (m) return;
    data.correos.push(v); inp.value = ''; drawMails(); dirty(); inp.focus();
  }
  $('#mail-add').addEventListener('click', addMail);
  $('#mail-in').addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addMail(); } });
  $('#mail-in').addEventListener('input', function () { if (this.getAttribute('aria-invalid')) show(this, $('#mail-e'), ''); });

  /* ---------- WhatsApp ---------- */
  function waPaint() {
    var w = String(data.contacto.whatsapp || '').replace(/\D/g, ''), sel = $('#wa-cc'), best = '';
    [].forEach.call(sel.options, function (o) { if (w.indexOf(o.value) === 0 && o.value.length > best.length) best = o.value; });
    sel.value = best || '502'; $('#wa-n').value = best ? w.slice(best.length) : w;
  }
  function waSet() {
    var cc = $('#wa-cc').value, n = waNational();
    if (n.length > 8 && n.indexOf(cc) === 0) n = n.slice(cc.length);
    data.contacto.whatsapp = n ? cc + n : ''; if (data.origen['contacto.whatsapp']) { delete data.origen['contacto.whatsapp']; paintOrigen(); } dirty();
    if ($('#wa-n').getAttribute('aria-invalid') === 'true') show($('#wa-n'), $('#wa-e'), waErr());
  }
  $('#wa-cc').addEventListener('change', waSet); $('#wa-n').addEventListener('input', waSet);
  $('#wa-n').addEventListener('blur', function () { show(this, $('#wa-e'), waErr()); });

  /* ---------- logo y banners ---------- */
  var logoFile = $('#logo-file');
  function logoDraw() {
    var th = $('#logo-th'); th.textContent = ''; if (data.negocio.logo) th.appendChild(imgEl(data.negocio.logo, 'Tu logo')); else th.appendChild(ic('image'));
    $('#logo-up').textContent = data.negocio.logo ? 'Cambiar logo' : 'Subir logo'; $('#logo-rm').hidden = !data.negocio.logo;
    $$('#logo-grid button').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-id') === data.negocio.logo ? 'true' : 'false'); });
  }
  $('#logo-up').addEventListener('click', function () { logoFile.click(); });
  $('#logo-rm').addEventListener('click', function () { delFile(data.negocio.logo); data.negocio.logo = null; logoDraw(); dirty(); });
  logoFile.addEventListener('change', function () {
    var f = logoFile.files[0]; logoFile.value = ''; if (!f) return; var er = $('#logo-e'); er.textContent = ''; er.classList.remove('on'); $('#logo-up').textContent = 'Subiendo…'; $('#logo-up').disabled = true;
    sendImage(f, 'logo').then(function (r) { $('#logo-up').disabled = false; if (r.ok) { data.negocio.logo = r.id; dirty(); } else { er.textContent = r.error || 'No pudimos subir el logo.'; er.classList.add('on'); } logoDraw(); });
  });
  function logoCands() {
    var imgs = (analysis.resultado && analysis.resultado.imagenes) || [], g = $('#logo-grid'); g.textContent = '';
    var use = imgs.filter(function (i) { return i && i.id; }).slice(0, 10);
    $('#logo-cands').hidden = !use.length || !data.presentacion.confirmada;
    use.forEach(function (i) {
      var b = h('button', { type: 'button', 'data-id': i.id, 'aria-label': 'Usar esta imagen como logo', onclick: function () { data.negocio.logo = data.negocio.logo === i.id ? null : i.id; logoDraw(); dirty(); } }, h('span', { class: 'thumb' }, imgEl(i.id, '')));
      g.appendChild(b);
    });
  }

  /* ---------- presentación y análisis ---------- */
  var PRES_BAD = /\.(ppt|doc|key|pages|numbers|odp|odt|rtf|pps|pot|canva)$/i, PRES_OK = /\.(pdf|pptx|docx)$/i, pollT = null, pollN = 0, pollStart = 0;
  function presErr(m) { var e = $('#pres-e'); e.textContent = m || ''; e.classList.toggle('on', !!m); }
  function presDraw() {
    var f = data.presentacion.file, c = $('#pres-card');
    c.hidden = !f; $('#pres-drop').hidden = !!f; $('#pres-h').hidden = !!f || !!data.presentacion.acepto;
    if (f) { $('#pres-name').textContent = presName || 'Tu presentación'; $('#pres-meta').textContent = analysisLabel(); }
    chrome();
  }
  var presName = LS.g('s5_pname_' + token) || '';
  function analysisLabel() { return { subiendo: 'Subiendo…', procesando: 'Leyendo tu presentación…', lista: 'Lista para revisar', error: 'No pudimos leerla ahora', none: 'Subida' }[analysis.estado] || ''; }
  $('#pres-file').addEventListener('change', function () {
    var f = this.files[0]; this.value = ''; presErr('');
    if (!f) return;
    if (PRES_BAD.test(f.name)) return presErr('Ese formato no lo podemos leer. Guárdala como PDF y vuelve a subirla.');
    if (!PRES_OK.test(f.name)) return presErr('Solo aceptamos PDF, PowerPoint (.pptx) o Word (.docx). Si usas Keynote o Canva, guárdala como PDF y vuelve a subirla.');
    if (f.size > (CFG.pres_max_mb || 10) * 1048576) return presErr('Tu archivo pesa ' + S5.bytes(f.size) + ' y el máximo es ' + (CFG.pres_max_mb || 10) + ' MB. Reduce su tamaño o guárdalo como PDF más liviano.');
    if (!f.size) return presErr('El archivo está vacío.');
    presName = f.name; LS.s('s5_pname_' + token, f.name);
    var up = $('#pres-up'); up.hidden = false; $('#pres-card').hidden = false; $('#pres-drop').hidden = true; $('#pres-name').textContent = f.name; $('#pres-meta').textContent = 'Subiendo…'; $('#pres-skip').hidden = true;
    S5.upload(token, 'presentacion', f, function (p) { up.firstChild.style.width = Math.round(p * 100) + '%'; }).then(function (r) {
      up.hidden = true;
      if (!r.ok) { presErr(r.mensaje || r.error || 'No pudimos subir tu archivo.'); $('#pres-card').hidden = true; $('#pres-drop').hidden = false; $('#pres-skip').hidden = false; return; }
      data.presentacion.file = r.id; data.presentacion.estado = 'pendiente'; data.presentacion.confirmada = false; analysis = { estado: 'procesando', resultado: null, msg: '', dismissed: false }; dirty(); flush();
      S5.api('POST', '/api/borrador/' + token + '/analizar', {}).then(function (a) {
        if (!a.ok) { analysis.estado = 'error'; analysis.msg = a.error || ''; data.presentacion.estado = 'error'; dirty(); presDraw(); return; }
        data.presentacion.estado = 'analizando'; pollN = 0; pollStart = Date.now(); schedPoll(1200); presDraw();
      });
      presDraw();
    });
  });
  $('#pres-rm').addEventListener('click', function () {
    delFile(data.presentacion.file); clearTimeout(pollT); data.presentacion.file = null; data.presentacion.estado = 'ninguna'; data.presentacion.confirmada = false; analysis = { estado: 'none', resultado: null, msg: '', dismissed: false }; presDraw(); dirty();
  });
  $('#pres-skip').addEventListener('click', function () { data.presentacion.estado = 'omitida'; dirty(); var l = steps(), i = l.indexOf('pres'); go(l[i + 1]); });
  function schedPoll(ms) { clearTimeout(pollT); pollT = setTimeout(pollAnalysis, ms); }
  function pollAnalysis() {
    S5.api('GET', '/api/borrador/' + token + '/analisis').then(function (r) {
      var e = String(r.estado || '');
      if (r.ok && e === 'lista') { analysis = { estado: 'lista', resultado: r.resultado || {}, msg: '', dismissed: false }; data.presentacion.estado = 'lista'; dirty(); presDraw(); if (cur === 'pres') go('revision'); return; }
      if ((r.ok && (e === 'error')) || (!r.ok && !r._net && r._status >= 400 && r._status !== 429)) { analysis.estado = 'error'; analysis.msg = r.mensaje || r.error || ''; data.presentacion.estado = 'error'; dirty(); presDraw(); return; }
      if (e === 'omitida') { analysis.estado = 'none'; return; }
      pollN++;
      if (Date.now() - pollStart > 300000) { analysis.estado = 'error'; analysis.msg = 'Está tardando más de lo normal.'; presDraw(); return; }
      analysis.estado = 'procesando'; drawPill(); schedPoll(Math.min(7000, 2000 * Math.pow(1.25, pollN)));
    });
  }
  function drawPill() {
    var p = $('#pill'), st = analysis.estado, hide = !data.presentacion.file || data.presentacion.confirmada || data.presentacion.estado === 'omitida' || analysis.dismissed || st === 'none' || st === 'subiendo' || cur === 'revision';
    p.hidden = hide; if (hide) return; p.textContent = '';
    if (st === 'procesando') { p.className = 'wz-pill'; p.appendChild(h('span', { class: 'sp' })); p.appendChild(h('span', { text: 'Leyendo tu presentación… sigue llenando tus datos.' })); }
    else if (st === 'lista') { p.className = 'wz-pill ok'; p.appendChild(h('span', { text: 'Tu presentación está lista.' })); p.appendChild(h('button', { type: 'button', text: 'Revisar', onclick: function () { returnTo = cur; go('revision'); } })); }
    else if (st === 'error') { p.className = 'wz-pill bad'; p.appendChild(h('span', { text: 'No pudimos leer tu presentación ahora; puedes llenar los datos manualmente.' })); p.appendChild(h('button', { type: 'button', text: 'Entendido', onclick: function () { analysis.dismissed = true; drawPill(); } })); }
  }

  /* ---- revisión ---- */
  var rev = null;
  var CONF = { nombre: 'un nombre de negocio', telefono: 'un teléfono', whatsapp: 'un WhatsApp', correo: 'un correo', direccion: 'una dirección', horario: 'un horario', frase: 'una frase principal', quienes: 'un texto de “Quiénes somos”' };
  function rubroKey(t) {
    var s = String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    return /abog|legal|juridic|notari/.test(s) ? 'abogado' : /clinic|medic|salud|doctor|dental|dentis|hospital|pediatr/.test(s) ? 'clinica' : /taller|mecanic|automotr|repuest/.test(s) ? 'taller' : /ropa|moda|boutique|vestid|calzado|textil/.test(s) ? 'ropa' : /restaur|comida|cafe|pizz|cocina|gastron/.test(s) ? 'restaurante' : /transport|logistic|flete|carga|bus/.test(s) ? 'transporte' : /contab|auditor|fiscal|tributar|impuesto/.test(s) ? 'contabilidad' : /import|export|aduan/.test(s) ? 'importaciones' : 'otro';
  }
  function vv(x) { return x && typeof x === 'object' ? String(x.v == null ? '' : x.v) : String(x == null ? '' : x); }
  function buildRevision() {
    var R = analysis.resultado || {}, cards = $('#rev-cards'); cards.textContent = ''; rev = { items: {}, srv: [], prd: [], contacto: {}, redes: {}, fotos: [], any: false };
    var conf = $('#rev-conf'); conf.textContent = ''; var cf = Array.isArray(R.conflictos) ? R.conflictos : [];
    conf.hidden = !cf.length; cf.forEach(function (c) { conf.appendChild(h('p', null, [h('b', { text: 'Ojo: ' }), 'Tienes ' + (CONF[c.campo] || 'un dato distinto en «' + String(c.campo || 'campo') + '»') + ' distinto en el formulario; usaremos el del formulario.'])); });
    function card(title, sub, bodyFn, key) {
      var cb = h('input', { type: 'checkbox', checked: true, id: 'rv-' + key }), body = h('div', { class: 'rc__b' });
      var c = h('div', { class: 'rc' }, [h('label', { class: 'rc__h', for: 'rv-' + key }, [cb, h('span', { text: title }), sub ? h('small', { text: sub }) : null]), body]);
      cb.addEventListener('change', function () { c.classList.toggle('off', !cb.checked); $$('input,textarea', body).forEach(function (i) { i.disabled = !cb.checked; }); });
      bodyFn(body, cb); cards.appendChild(c); rev.any = true; return cb;
    }
    function scalar(key, title, v, area) {
      if (!String(v).trim()) return;
      var o = { v: v }; rev.items[key] = o;
      o.cb = card(title, '', function (b) { o.inp = h(area ? 'textarea' : 'input', { class: 'inp', 'aria-label': title, value: v, rows: area ? 4 : null }); o.inp.value = v; b.appendChild(o.inp); }, key);
    }
    scalar('nombre', 'Nombre del negocio', vv(R.nombre)); scalar('rubro', 'Rubro sugerido', vv(R.rubro_sugerido)); scalar('frase', 'Frase principal', vv(R.frase_principal)); scalar('quienes', 'Quiénes somos', vv(R.quienes_somos), true);
    var sv = Array.isArray(R.servicios) ? R.servicios.filter(function (s) { return s && String(s.nombre).trim(); }) : [];
    if (sv.length) card('Servicios', sv.length + ' encontrados', function (b) { R.servicios.forEach(function (s, i) { if (!s || !String(s.nombre).trim()) return; var o = { i: i, s: s, cb: h('input', { type: 'checkbox', checked: true, 'aria-label': 'Incluir ' + s.nombre }) }; o.n = h('input', { class: 'inp', value: s.nombre, 'aria-label': 'Nombre del servicio', maxlength: 60 }); o.d = h('input', { class: 'inp', value: s.descripcion || '', 'aria-label': 'Descripción', maxlength: 140, placeholder: 'Descripción' }); o.n.value = s.nombre; o.d.value = s.descripcion || ''; rev.srv.push(o); b.appendChild(h('div', { class: 'rr' }, [o.cb, h('div', { class: 'field' }, [o.n, o.d])])); }); }, 'srv');
    if (tienda()) {
      var pr = Array.isArray(R.productos) ? R.productos.filter(function (p) { return p && String(p.nombre).trim(); }) : [];
      if (pr.length) card('Productos', pr.length + ' encontrados', function (b) { R.productos.forEach(function (p, i) { if (!p || !String(p.nombre).trim()) return; var o = { i: i, p: p, cb: h('input', { type: 'checkbox', checked: true, 'aria-label': 'Incluir ' + p.nombre }) }; o.n = h('input', { class: 'inp', value: p.nombre, 'aria-label': 'Nombre del producto', maxlength: 80 }); o.pr = h('input', { class: 'inp', value: p.precio === '' || p.precio == null ? '' : String(p.precio), 'aria-label': 'Precio en quetzales', inputmode: 'decimal', placeholder: 'Precio Q' }); o.n.value = p.nombre; o.pr.value = p.precio == null ? '' : String(p.precio); rev.prd.push(o); b.appendChild(h('div', { class: 'rr' }, [o.cb, h('div', { class: 'row2' }, [o.n, o.pr])])); }); }, 'prd');
      if (Array.isArray(R.categorias) && R.categorias.length) rev.cat = card('Categorías', R.categorias.length + ' encontradas', function (b) { b.appendChild(h('ul', { class: 'chips' }, R.categorias.map(function (c) { return h('li', null, h('span', { text: String(c) })); }))); }, 'cat');
    }
    var C = R.contacto || {}, CL = { telefono: 'Teléfono', whatsapp: 'WhatsApp', correo: 'Correo', direccion: 'Dirección', horario: 'Horario' }, cs = Object.keys(CL).filter(function (k) { return vv(C[k]).trim(); });
    if (cs.length) card('Contacto', '', function (b) { cs.forEach(function (k) { var o = { cb: h('input', { type: 'checkbox', checked: true, 'aria-label': 'Incluir ' + CL[k] }), inp: h('input', { class: 'inp', 'aria-label': CL[k] }) }; o.inp.value = vv(C[k]); rev.contacto[k] = o; b.appendChild(h('div', { class: 'rr' }, [o.cb, h('div', { class: 'field' }, [h('span', { class: 'rr-lbl', text: CL[k] }), o.inp])])); }); }, 'ct');
    var RS = C.redes || R.redes || {}, rs = Object.keys(RS).filter(function (k) { return String(RS[k] || '').trim(); });
    if (rs.length) card('Redes sociales', '', function (b) { rs.forEach(function (k) { var o = { cb: h('input', { type: 'checkbox', checked: true, 'aria-label': 'Incluir ' + k }), inp: h('input', { class: 'inp', 'aria-label': k }) }; o.inp.value = String(RS[k]); rev.redes[k] = o; b.appendChild(h('div', { class: 'rr' }, [o.cb, h('div', { class: 'field' }, [h('span', { class: 'rr-lbl', text: k }), o.inp])])); }); }, 'rs');
    var im = Array.isArray(R.imagenes) ? R.imagenes.filter(function (i) { return i && i.id; }) : [];
    if (im.length) card('Fotos de tu archivo', 'Toca para elegir', function (b) {
      var g = h('div', { class: 'imgsel' }); im.forEach(function (i) { var bt = h('button', { type: 'button', 'aria-pressed': 'false', 'aria-label': 'Foto ' + i.id, onclick: function () { bt.setAttribute('aria-pressed', bt.getAttribute('aria-pressed') === 'true' ? 'false' : 'true'); } }, imgEl(i.id, '')); bt.setAttribute('data-id', i.id); g.appendChild(bt); }); b.appendChild(g); }, 'fo');
    if (!rev.any) cards.appendChild(h('div', { class: 'notice', text: 'No encontramos datos claros en tu presentación. No hay problema: puedes llenar todo a mano en los siguientes pasos.' }));
    $('#rev-use').textContent = rev.any ? 'Usar esto' : 'Continuar';
  }
  $('#rev-no').addEventListener('click', function () { data.presentacion.estado = 'omitida'; data.presentacion.confirmada = false; dirty(); var to = returnTo || steps()[steps().indexOf('revision') + 1] || 'negocio'; returnTo = ''; analysis.dismissed = true; go(to); });
  $('#rev-use').addEventListener('click', function () {
    var btn = this, fileId = data.presentacion.file; if (!rev.any) { data.presentacion.estado = 'omitida'; dirty(); var to0 = returnTo || 'negocio'; returnTo = ''; return go(to0); }
    var u = { contacto: {}, redes: {}, servicios: [], productos: [] }, ed = { servicios: {}, productos: {}, contacto: {}, redes: {} }, I = rev.items, on = function (o) { return o && o.cb.checked; };
    ['nombre', 'rubro', 'frase', 'quienes'].forEach(function (k) { u[k] = !!on(I[k]); if (on(I[k])) ed[k] = I[k].inp.value.trim(); });
    rev.srv.forEach(function (o) { if (o.cb.checked && $('#rv-srv').checked) { u.servicios.push(o.i); ed.servicios[o.i] = { nombre: o.n.value.trim(), descripcion: o.d.value.trim() }; } });
    rev.prd.forEach(function (o) { if (o.cb.checked && $('#rv-prd').checked) { u.productos.push(o.i); ed.productos[o.i] = { nombre: o.n.value.trim(), precio: o.pr.value.trim() }; } });
    u.categorias = !!(rev.cat && rev.cat.checked);
    Object.keys(rev.contacto).forEach(function (k) { var o = rev.contacto[k]; u.contacto[k] = o.cb.checked && $('#rv-ct').checked; if (u.contacto[k]) ed.contacto[k] = o.inp.value.trim(); });
    Object.keys(rev.redes).forEach(function (k) { var o = rev.redes[k]; u.redes[k] = o.cb.checked && $('#rv-rs').checked; if (u.redes[k]) ed.redes[k] = o.inp.value.trim(); });
    var fotos = $('#rv-fo') && $('#rv-fo').checked ? $$('.imgsel button[aria-pressed=true]').map(function (b) { return b.getAttribute('data-id'); }) : [];
    btn.disabled = true; btn.textContent = 'Aplicando…'; show(null, $('#rev-e'), '');
    flush().then(function () { return S5.api('POST', '/api/borrador/' + token + '/confirmar-presentacion', { usar: u, fotos: fotos, editado: ed }); }).then(function (r) {
      btn.disabled = false; btn.textContent = 'Usar esto';
      if (!r.ok) return show(null, $('#rev-e'), r.error || 'No pudimos aplicar tu presentación. Inténtalo de nuevo.');
      if (isObj(r.data)) { data = merge(defaults(), r.data); if (!isObj(data.origen)) data.origen = {}; }
      applyEdits(u, ed, R0());
      data.presentacion.confirmada = true; data.presentacion.estado = 'confirmada'; data.presentacion.file = data.presentacion.file || fileId;
      rebuildUI(); dirty(); var to = returnTo || 'negocio'; returnTo = ''; go(to === 'pres' || to === 'revision' ? 'negocio' : to);
    });
  });
  function R0() { return analysis.resultado || {}; }
  function applyEdits(u, ed, R) {
    function mark(k) { data.origen[k] = 'presentacion'; }
    if (u.nombre && ed.nombre && !String(data.negocio.nombre).trim()) { data.negocio.nombre = ed.nombre; mark('negocio.nombre'); }
    if (u.nombre && ed.nombre && data.origen['negocio.nombre'] === 'presentacion') data.negocio.nombre = ed.nombre;
    if (u.rubro && ed.rubro && (!data.negocio.rubro || data.origen['negocio.rubro'] === 'presentacion')) { var k = rubroKey(ed.rubro); data.negocio.rubro = k; if (k === 'otro') data.negocio.rubro_otro = ed.rubro.slice(0, 60); mark('negocio.rubro'); }
    if (u.frase && ed.frase && (!String(data.contenido.frase).trim() || data.origen['contenido.frase'] === 'presentacion')) { data.contenido.frase = ed.frase; mark('contenido.frase'); }
    if (u.quienes && ed.quienes && (!String(data.contenido.quienes).trim() || data.origen['contenido.quienes'] === 'presentacion')) { data.contenido.quienes = ed.quienes; mark('contenido.quienes'); }
    var mapC = { telefono: 'contacto.telefono', direccion: 'contacto.direccion', horario: 'contacto.horario', correo: 'correo_contacto', whatsapp: 'contacto.whatsapp' };
    Object.keys(ed.contacto).forEach(function (k) { var p = mapC[k]; if (!p || !ed.contacto[k]) return; var cur0 = String(get(p) || '').trim(); if (!cur0 || data.origen[p] === 'presentacion') { set(p, k === 'whatsapp' ? ed.contacto[k].replace(/\D/g, '') : ed.contacto[k]); mark(p); } });
    Object.keys(ed.redes).forEach(function (k) { var p = 'contacto.redes.' + k; if (ed.redes[k] && !String(get(p) || '').trim()) { set(p, ed.redes[k]); mark(p); } });
    var used = {};
    Object.keys(ed.servicios).forEach(function (i) { var o = R.servicios && R.servicios[i]; if (!o) return; var e = ed.servicios[i], f = false;
      data.contenido.servicios.forEach(function (s, j) { if (!f && !used[j] && s.origen === 'pres' && s.nombre === o.nombre) { f = true; used[j] = 1; s.nombre = e.nombre || s.nombre; s.descripcion = e.descripcion; } });
      if (!f && !data.contenido.servicios.some(function (s) { return s.nombre === e.nombre; })) data.contenido.servicios.push({ nombre: e.nombre, descripcion: e.descripcion, foto: null, origen: 'pres' }); });
    var used2 = {};
    Object.keys(ed.productos).forEach(function (i) { var o = R.productos && R.productos[i]; if (!o) return; var e = ed.productos[i], pr = parsePrice(e.precio), f = false;
      data.tienda.productos.forEach(function (p, j) { if (!f && !used2[j] && p.origen === 'pres' && p.nombre === o.nombre) { f = true; used2[j] = 1; p.nombre = e.nombre || p.nombre; if (pr !== null) p.precio = pr; } });
      if (!f && !data.tienda.productos.some(function (p) { return p.nombre === e.nombre; })) data.tienda.productos.push({ nombre: e.nombre, categoria: '', precio: pr === null ? '' : pr, descripcion: o.descripcion || '', foto: null, stock: '', origen: 'pres' }); });
    if (!data.negocio.rubro_otro && data.negocio.rubro !== 'otro') data.negocio.rubro_otro = '';
  }
  function rebuildUI() { $$('.err.on').forEach(function (n) { n.textContent = ''; n.classList.remove('on'); }); $$('[aria-invalid]').forEach(function (n) { n.removeAttribute('aria-invalid'); }); paint(); waPaint(); drawMails(); lists.srv.render(); lists.prd.render(); lists.cat.render(); logoDraw(); logoCands(); gBanner.draw(); gGal.draw(); }

  /* ---------- resumen y construcción ---------- */
  var RUB = { abogado: 'Abogado/a', clinica: 'Clínica / médico', taller: 'Taller', ropa: 'Ropa', restaurante: 'Restaurante', transporte: 'Transporte', contabilidad: 'Contabilidad', importaciones: 'Importaciones' };
  var EST = { 1: 'Oscuro elegante', 2: 'Claro editorial', 3: 'Oscuro moderno', 4: 'Claro clásico', 5: 'Claro audaz' };
  function q(n) { return 'Q' + Number(n).toLocaleString('en-US'); }
  function total() { return (tienda() ? CFG.precio_tienda : CFG.precio_info) + (data.tarjeta_extra ? CFG.precio_tarjeta : 0); }
  function drawSummary() {
    var box = $('#res-cards'); box.textContent = ''; var d = data, mi = missing();
    function sc(title, step, lines, bad) {
      var c = h('div', { class: 'sc' + (bad ? ' bad' : '') }, [h('h3', { text: title })].concat(lines.map(function (l) { return h('p', { class: l[1] ? 'm' : '', text: l[0] }); })).concat([h('button', { type: 'button', class: 'btn btn--ghost btn--sm', text: 'Editar', 'aria-label': 'Editar ' + title, onclick: function () { go(step); } })]));
      box.appendChild(c);
    }
    sc('Plan', 'plan', [[(tienda() ? 'Tienda virtual' : 'Página informativa') + ' · ' + q(total()) + ' al año', 0], [d.tarjeta_extra ? 'Incluye pago con tarjeta (+' + q(CFG.precio_tarjeta) + ')' : 'Sin pago con tarjeta', 1]], !d.plan);
    sc('Negocio', 'negocio', [[d.negocio.nombre || 'Falta el nombre', 0], [(d.negocio.rubro === 'otro' ? d.negocio.rubro_otro : RUB[d.negocio.rubro]) || 'Falta el rubro', 1], [(d.negocio.idioma === 'en' ? 'English' : 'Español') + ' · Estilo ' + (EST[d.negocio.estilo] || ''), 1]], !d.negocio.nombre || !d.negocio.rubro);
    var dom = d.dominio.tiene ? d.dominio.dominio : (d.dominio.deseado ? d.dominio.deseado + '.com (nuevo)' : 'Dominio nuevo a definir');
    sc('Dominio y correos', 'dominio', [[dom, 0], [(d.correos.length ? d.correos.map(function (c) { return c + '@'; }).join(', ') : 'info@ (por defecto)'), 1]]);
    sc('Contenido', 'contenido', [[d.contenido.servicios.length + ' servicio(s) · ' + (d.contenido.banner.length) + ' foto(s) de banner · ' + d.contenido.galeria.length + ' en galería', 0], [d.contenido.frase || 'Sin frase principal', 1]], !tienda() && !d.contenido.servicios.some(function (s) { return String(s.nombre).trim(); }));
    if (tienda()) {
      sc('Productos', 'productos', [[d.tienda.productos.length + ' producto(s) · ' + d.tienda.categorias.length + ' categoría(s)', 0]], !d.tienda.productos.some(function (p) { return String(p.nombre).trim(); }));
      sc('Cobros', 'cobros', [[d.tienda.correo_pedidos || 'Falta el correo de pedidos', 0], [(d.tienda.banco.banco ? d.tienda.banco.banco + ' · ' : '') + (d.tienda.contra_entrega ? 'Contra entrega: sí' : 'Contra entrega: no'), 1]], !d.tienda.correo_pedidos);
    }
    sc('Contacto', 'contacto', [[d.contacto.whatsapp ? 'WhatsApp +' + d.contacto.whatsapp : 'Falta el WhatsApp', 0], [d.contacto.direccion || d.contacto.telefono || 'Sin dirección ni teléfono', 1]], !d.contacto.whatsapp);
    var pe = $('#res-pend'); pe.hidden = !(data.presentacion.file && !data.presentacion.confirmada && analysis.estado === 'procesando'); pe.textContent = 'Aún estamos leyendo tu presentación. Puedes esperar a que termine para aprovecharla, o crear tu vista previa ya.';
    $('#build-go').lastChild.textContent = built ? 'Actualizar mi vista previa' : 'Crear mi vista previa';
    $('#build-idle').hidden = building;
    $('#build').hidden = !building && !built;
    show(null, $('#res-e'), '');
  }
  $('#build-go').addEventListener('click', function () {
    var mi = missing(), er = $('#res-e');
    if (mi.length) { er.textContent = ''; er.classList.add('on'); er.appendChild(document.createTextNode('Antes de crear tu vista previa completa: ')); mi.forEach(function (m, i) { er.appendChild(h('button', { type: 'button', class: 'btn btn--ghost btn--sm', text: m[1], onclick: function () { go(m[0]); } })); er.appendChild(document.createTextNode(' ')); }); return; }
    var b = this; b.disabled = true; show(null, er, '');
    flush().then(function () { return S5.api('POST', '/api/borrador/' + token + (built ? '/regenerar' : '/crear'), { t0: t0, web_sitio: $('#web_sitio').value }); }).then(function (r) {
      b.disabled = false;
      if (!r.ok) return show(null, er, r.error || 'No pudimos iniciar la construcción. Inténtalo de nuevo.');
      built = false; building = true; $('#build').hidden = false; $('#build-idle').hidden = true; $('#b-done').hidden = true; $('#b-fail').hidden = true; $('#b-title').textContent = 'Armando tu web'; bN = 0; pollBuild(); $('#build').scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });
  var bT = null, bN = 0, bFail = 0;
  function pollBuild() {
    clearTimeout(bT);
    S5.api('GET', '/api/borrador/' + token + '/construccion').then(function (r) {
      if (!r.ok) { bFail++; if (bFail > 5 && !r._net) { $('#b-fail').hidden = false; return; } bT = setTimeout(pollBuild, 3000); return; }
      bFail = 0; var p = Math.max(0, Math.min(100, Math.round(+r.progreso || 0)));
      $('#b-bar').style.width = p + '%'; $('#b-pct').textContent = p + ' %'; $('#b-prog').setAttribute('aria-valuenow', p); if (r.mensaje) $('#b-msg').textContent = r.mensaje;
      var ul = $('#b-steps'); ul.textContent = ''; (r.pasos || []).forEach(function (s) { var st = String(s.estado || ''), c = /ok|listo|hecho|complet/.test(st) ? 'ok' : /error|fall/.test(st) ? 'bad' : /curso|proces|run|trabaj/.test(st) ? 'run' : ''; ul.appendChild(h('li', { class: c }, [h('i', { text: c === 'ok' ? '✓' : c === 'bad' ? '!' : '' }), String(s.titulo || s.clave || '')])); });
      var e = String(r.estado || '');
      if (/listo|lista|completa/.test(e) && !/fall/.test(e)) { building = false; built = true; $('#b-title').textContent = 'Tu vista previa'; $('#b-msg').textContent = 'Lista'; $('#b-done').hidden = false; var v = $('#b-view'); if (r.url) { v.href = r.url; v.hidden = false; } else v.hidden = true; $('#build-go').lastChild.textContent = 'Actualizar mi vista previa'; drawSide(); $('#b-done').scrollIntoView({ behavior: 'smooth', block: 'nearest' }); return; }
      if (/error|fall/.test(e)) { building = false; $('#b-fail').hidden = false; $('#b-msg').textContent = r.mensaje || 'Algo tardó más de lo normal.'; return; }
      bN++; bT = setTimeout(pollBuild, Math.min(5000, 1500 + bN * 200));
    });
  }
  $('#b-retry').addEventListener('click', function () { $('#b-fail').hidden = true; building = false; built = false; $('#build-go').click(); });
  $('#b-pay').addEventListener('click', function () { go('pago'); });
  $('#b-edit').addEventListener('click', function () { go('negocio'); });

  /* ---------- pago ---------- */
  function payEnter() {
    $$('[data-total]').forEach(function (n) { n.textContent = q(total()); });
    if (paid) { $('[data-pay-form]').hidden = true; $('[data-pay-done]').hidden = false; }
  }
  var payInit = false;
  function initPay() {
    if (payInit) return; payInit = true;
    S5.initPay($('[data-pay]'), token, function () { paid = true; chrome(); });
  }

  /* ---------- entrada a cada paso ---------- */
  function stepEnter(k) {
    if (k === 'revision') buildRevision();
    if (k === 'resumen') drawSummary();
    if (k === 'pago') { initPay(); payEnter(); }
    if (k === 'pres') presDraw();
    if (k === 'negocio') { logoDraw(); logoCands(); }
    if (k === 'productos') { lists.cat.render(); lists.prd.render(); }
    if (k === 'contenido') { lists.srv.render(); }
    if (k === 'contacto') waPaint();
    if (k === 'dominio') drawMails();
  }

  /* ---------- copiar enlace / aviso ---------- */
  $('#copy-link').addEventListener('click', function () {
    var b = this, s = $('span', b);
    S5.copy(location.origin + '/continuar/' + token).then(function (ok) { s.textContent = ok ? 'Enlace copiado ✓' : 'Copia la dirección del navegador'; setTimeout(function () { s.textContent = 'Copiar enlace'; }, 2200); });
  });
  $('#tip-x').addEventListener('click', function () { LS.s('s5_tip', '1'); $('#tip').hidden = true; });

  /* ---------- arranque ---------- */
  var gBanner, gGal;
  function init() {
    initLists();
    gBanner = gallery($('#g-banner'), 'contenido.banner', 3, $('#g-banner-e'));
    gGal = gallery($('#g-gal'), 'contenido.galeria', 30);
    // análisis desde el borrador
    var a = draft.analisis;
    if (isObj(a)) {
      var est = String(a.estado || '');
      if (est === 'lista' || (!est && (a.servicios || a.nombre))) analysis = { estado: 'lista', resultado: a.resultado || a, msg: '', dismissed: false };
      else if (/proces|pend|analiz/.test(est)) analysis.estado = 'procesando';
      else if (est === 'error') analysis.estado = 'error';
    }
    if (data.presentacion.file && analysis.estado === 'none') {
      var pe = data.presentacion.estado;
      if (/pend|analiz/.test(pe)) analysis.estado = 'procesando'; else if (pe === 'lista') analysis.estado = 'procesando'; else if (pe === 'error') analysis.estado = 'error';
    }
    paint(); waPaint(); drawMails();
    if (analysis.estado === 'procesando' && token) { pollStart = Date.now(); schedPoll(600); }
    var qs = new URLSearchParams(location.search), want = qs.get('paso') || (token && LS.g('s5_paso_' + token)) || draft.paso || 'plan';
    if (typeof want !== 'string' || ORDER.indexOf(want) < 0) want = 'plan';
    if (data.plan && token && want === 'plan' && !qs.get('paso') && !draft.paso) want = 'pres';
    var l = steps(); if (l.indexOf(want) < 0) want = want === 'revision' ? 'negocio' : l[0];
    if (want === 'pago' && !built) want = 'resumen';
    cur = want; try { history.replaceState({ s: want }, '', location.pathname + location.search); } catch (_) {}
    go(want, { noPush: true, quiet: true });
    if (building && token) { $('#build').hidden = false; pollBuild(); }
    if (token) setSave('ok', 'Guardado ✓');
    document.documentElement.classList.add('wz-ready');
  }
  init();
})();
