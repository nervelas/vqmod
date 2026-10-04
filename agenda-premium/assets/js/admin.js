/* Agenda Premium · cascarón del panel: paleta de comandos, atajos globales, service worker y ayudas de formularios.
 * Vanilla ES2020, sin dependencias. Expone window.A1 con ayudas que reutilizan las demás pantallas. */
(function () {
  'use strict';
  var d = document;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var meta = function (n) { var m = $('meta[name="' + n + '"]'); return m ? m.getAttribute('content') || '' : ''; };
  var base = (meta('base-path') || '').replace(/\/+$/, '');

  /* ---------- Ayudas compartidas ---------- */
  function h(tag, attrs, kids) {
    var el = d.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      var v = attrs[k];
      if (v === null || v === undefined || v === false) { return; }
      if (k === 'class') { el.className = v; } else if (k === 'text') { el.textContent = v; } else if (k.slice(0, 2) === 'on' && typeof v === 'function') { el.addEventListener(k.slice(2), v); } else { el.setAttribute(k, v === true ? '' : v); }
    });
    (kids || []).forEach(function (c) { if (c === null || c === undefined || c === false) { return; } el.appendChild(typeof c === 'string' ? d.createTextNode(c) : c); });
    return el;
  }
  function icon(name) {
    var ns = 'http://www.w3.org/2000/svg', s = d.createElementNS(ns, 'svg'), u = d.createElementNS(ns, 'use');
    var ref = $('use[href*="icons.svg"]');
    var href = ref ? ref.getAttribute('href').split('#')[0] : base + '/assets/img/icons.svg';
    s.setAttribute('class', 'ic'); s.setAttribute('aria-hidden', 'true'); s.setAttribute('focusable', 'false');
    u.setAttribute('href', href + '#i-' + name); s.appendChild(u);
    return s;
  }
  function url(p) { return base + (p.charAt(0) === '/' ? p : '/' + p); }
  function api(path, opts) {
    return window.Ap.fetchJson(/^https?:/.test(path) ? path : path, opts || {});
  }
  function say(msg, type) { if (window.Ap) { window.Ap.toast(msg, type || 'info'); } }
  function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  function typing(el) {
    if (!el) { return false; }
    var t = el.tagName;
    return t === 'INPUT' || t === 'TEXTAREA' || t === 'SELECT' || el.isContentEditable;
  }

  /* Autocompletado accesible de clientes: opts = { input, list, url, onPick(item) } */
  function clientSearch(o) {
    var input = o.input, list = o.list, items = [], active = -1, timer = null, seq = 0;
    function hide() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; }
    function mark(i) {
      var els = $$('[role=option]', list);
      els.forEach(function (el, k) { el.classList.toggle('is-active', k === i); el.setAttribute('aria-selected', k === i ? 'true' : 'false'); });
      active = i;
      if (els[i]) { input.setAttribute('aria-activedescendant', els[i].id); els[i].scrollIntoView({ block: 'nearest' }); }
    }
    function pick(i) { if (items[i]) { o.onPick(items[i]); hide(); input.value = ''; } }
    function show(rows, q) {
      items = rows; list.textContent = '';
      if (!rows.length) { list.appendChild(h('li', { class: 'client-empty', role: 'presentation', text: 'No encontramos a nadie con «' + q + '». Escribe sus datos abajo para crear su ficha.' })); }
      rows.forEach(function (c, i) {
        list.appendChild(h('li', { role: 'option', id: list.id + '-o' + i, 'aria-selected': 'false', class: 'client-opt', onclick: function () { pick(i); } }, [h('strong', { text: c.title }), h('span', { class: 'muted small', text: ' ' + (c.sub || '') })]));
      });
      list.hidden = false; input.setAttribute('aria-expanded', 'true');
      if (rows.length) { mark(0); }
    }
    input.addEventListener('input', function () {
      var q = input.value.trim(); clearTimeout(timer);
      if (q.length < 2) { hide(); return; }
      var my = ++seq;
      timer = setTimeout(function () {
        fetch(o.url + '?scope=clients&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
          .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
          .then(function (data) { if (my === seq) { show(data.results || [], q); } })
          .catch(function () { hide(); });
      }, 200);
    });
    input.addEventListener('keydown', function (e) {
      if (list.hidden) { return; }
      var n = $$('[role=option]', list).length;
      if (e.key === 'ArrowDown' && n) { e.preventDefault(); mark((active + 1) % n); }
      else if (e.key === 'ArrowUp' && n) { e.preventDefault(); mark((active - 1 + n) % n); }
      else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); pick(active); }
      else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); hide(); }
    });
    input.addEventListener('blur', function () { setTimeout(hide, 150); });
  }
  window.A1 = { h: h, icon: icon, url: url, api: api, say: say, norm: norm, typing: typing, base: base, clientSearch: clientSearch };

  /* ---------- Contraseña: mostrar/ocultar y reglas ---------- */
  d.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-toggle-password]');
    if (!b) { return; }
    var inp = $(b.getAttribute('data-toggle-password'));
    if (!inp) { return; }
    var show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    b.setAttribute('aria-pressed', show ? 'true' : 'false');
  });
  $$('[data-strength]').forEach(function (inp) {
    var list = $(inp.getAttribute('data-strength'));
    if (!list) { return; }
    function check() {
      var v = inp.value, ok = { len: v.length >= 10, lower: /[a-z]/.test(v), upper: /[A-Z]/.test(v), num: /\d/.test(v) };
      $$('[data-rule]', list).forEach(function (li) { li.classList.toggle('is-ok', !!ok[li.getAttribute('data-rule')]); });
    }
    inp.addEventListener('input', check); check();
  });

  /* ---------- Service worker (carcasa instalable) ---------- */
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' }).catch(function () { /* sin soporte: el panel funciona igual */ });
    });
  }

  /* ---------- Paleta de comandos ---------- */
  var root = $('#cmdk-root');
  if (!root) { return; }
  var searchUrl = root.getAttribute('data-search-url');
  var nav = [];
  try { nav = JSON.parse(root.getAttribute('data-nav') || '[]'); } catch (e) { nav = []; }
  var pages = [], paths = {};
  nav.forEach(function (sec) { sec.items.forEach(function (it) { paths[it.path] = true; pages.push({ group: 'Páginas', title: it.label, sub: sec.title, icon: it.icon, url: url(it.path), kw: norm(it.label + ' ' + (it.keywords || '') + ' ' + sec.title) }); }); });
  var actions = [];
  function addAction(path, title, ic, kw, url2) { if (paths[path]) { actions.push({ group: 'Acciones', title: title, sub: 'Acción rápida', icon: ic, url: url(url2 || path), kw: norm(title + ' ' + kw) }); } }
  addAction('/admin/citas', 'Nueva cita', 'plus', 'agendar crear cita manual', '/admin/citas/nueva');
  if (paths['/admin/clientes'] && paths['/admin/eventos']) { addAction('/admin/clientes', 'Nuevo cliente', 'user', 'agregar crear cliente ficha', '/admin/clientes/nuevo'); }
  addAction('/admin/eventos', 'Nuevo evento', 'layers', 'crear tipo de evento servicio', '/admin/eventos/nuevo');
  actions.push({ group: 'Acciones', title: 'Cambiar tema', sub: 'Claro u oscuro', icon: 'moon', type: 'theme', kw: norm('cambiar tema claro oscuro modo') });

  var overlay = null, input, list, items = [], active = -1, timer = null, ctrl = null, lastFocus = null, remote = [], seq = 0;

  function build() {
    input = h('input', { class: 'cmdk-input', type: 'text', role: 'combobox', 'aria-expanded': 'true', 'aria-controls': 'cmdk-list', 'aria-autocomplete': 'list', 'aria-label': 'Buscar o ir a…', placeholder: 'Busca clientes, citas o páginas, o escribe una acción…', autocomplete: 'off', spellcheck: 'false' });
    list = h('ul', { class: 'cmdk-list', id: 'cmdk-list', role: 'listbox', 'aria-label': 'Resultados' });
    var hint = h('div', { class: 'cmdk-hint' }, [
      h('span', {}, [h('kbd', { class: 'kbd', text: '↑' }), ' ', h('kbd', { class: 'kbd', text: '↓' }), ' navegar']),
      h('span', {}, [h('kbd', { class: 'kbd', text: 'Enter' }), ' abrir']),
      h('span', {}, [h('kbd', { class: 'kbd', text: 'Esc' }), ' cerrar'])
    ]);
    var status = h('div', { class: 'sr-only', 'aria-live': 'polite', id: 'cmdk-status' });
    var box = h('div', { class: 'cmdk-box', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Paleta de comandos' }, [input, list, hint, status]);
    overlay = h('div', { class: 'cmdk-overlay', hidden: true }, [box]);
    root.appendChild(overlay);
    overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) { close(); } });
    input.addEventListener('input', onInput);
    input.addEventListener('keydown', onKey);
    list.addEventListener('click', function (e) { var li = e.target.closest('[data-i]'); if (li) { run(items[+li.getAttribute('data-i')]); } });
    list.addEventListener('mousemove', function (e) { var li = e.target.closest('[data-i]'); if (li && +li.getAttribute('data-i') !== active) { setActive(+li.getAttribute('data-i'), false); } });
    overlay.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); close(); }
      if (e.key === 'Tab') { e.preventDefault(); input.focus(); }
    });
  }

  function open() {
    if (!overlay) { build(); }
    if (!overlay.hidden) { return; }
    lastFocus = d.activeElement;
    overlay.hidden = false;
    d.body.classList.add('cmdk-open');
    input.value = ''; remote = [];
    render();
    input.focus();
  }
  function close() {
    if (!overlay || overlay.hidden) { return; }
    overlay.hidden = true;
    d.body.classList.remove('cmdk-open');
    if (ctrl) { ctrl.abort(); }
    if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) { /* nada */ } }
  }

  function local(q) {
    var n = norm(q).trim();
    var pick = function (arr) { return arr.filter(function (x) { return !n || x.kw.indexOf(n) !== -1; }); };
    return n ? pick(actions).concat(pick(pages).slice(0, 8)) : actions.concat(pages.slice(0, 7));
  }

  function render() {
    var q = input.value.trim();
    var all = local(q).concat(remote);
    items = all;
    list.textContent = '';
    if (!all.length) {
      list.appendChild(h('li', { class: 'cmdk-empty', role: 'presentation', text: q.length < 2 ? 'Escribe al menos 2 letras para buscar.' : 'No encontramos nada para «' + q + '».' }));
      input.removeAttribute('aria-activedescendant');
      announce('Sin resultados');
      return;
    }
    var lastGroup = '';
    all.forEach(function (it, i) {
      if (it.group !== lastGroup) { lastGroup = it.group; list.appendChild(h('li', { class: 'cmdk-group', role: 'presentation', text: it.group })); }
      list.appendChild(h('li', { class: 'cmdk-item', role: 'option', id: 'cmdk-o' + i, 'data-i': String(i), 'aria-selected': 'false' }, [
        icon(it.icon || 'dot'),
        h('span', { class: 'cmdk-text' }, [it.title, it.sub ? h('small', { text: it.sub }) : null])
      ]));
    });
    setActive(0, true);
    announce(all.length + (all.length === 1 ? ' resultado' : ' resultados'));
  }
  function announce(t) { var s = $('#cmdk-status'); if (s) { s.textContent = t; } }

  function setActive(i, scroll) {
    var els = $$('.cmdk-item', list);
    if (!els.length) { return; }
    i = (i + els.length) % els.length;
    els.forEach(function (el, k) { var on = k === i; el.classList.toggle('is-active', on); el.setAttribute('aria-selected', on ? 'true' : 'false'); });
    active = i;
    input.setAttribute('aria-activedescendant', els[i].id);
    if (scroll !== false) { els[i].scrollIntoView({ block: 'nearest' }); }
  }

  function onKey(e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
    else if (e.key === 'Home') { e.preventDefault(); setActive(0); }
    else if (e.key === 'End') { e.preventDefault(); setActive(items.length - 1); }
    else if (e.key === 'Enter') { e.preventDefault(); if (items[active]) { run(items[active]); } }
  }

  function run(it) {
    if (!it) { return; }
    close();
    if (it.type === 'theme') { var b = $('[data-theme-toggle]'); if (b) { b.click(); } return; }
    if (it.url) { window.location.href = it.url; }
  }

  function onInput() {
    var q = input.value.trim();
    clearTimeout(timer);
    if (ctrl) { ctrl.abort(); ctrl = null; }
    if (q.length < 2) { remote = []; render(); return; }
    render();
    var my = ++seq;
    timer = setTimeout(function () {
      ctrl = window.AbortController ? new AbortController() : null;
      fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function (data) {
          if (my !== seq) { return; }
          remote = (data.results || []).filter(function (x) { return x.group !== 'Páginas' && x.group !== 'Acciones'; });
          render();
        }).catch(function () { /* búsqueda remota no disponible: quedan los resultados locales */ });
    }, 180);
  }

  d.addEventListener('click', function (e) { if (e.target.closest && e.target.closest('[data-cmdk-open]')) { e.preventDefault(); open(); } });

  /* ---------- Atajos globales ---------- */
  var gPending = false, gTimer = null;
  function help() {
    var dlg = $('#shortcuts-dialog');
    if (!dlg) {
      var rows = [['Ctrl/⌘ K  o  /', 'Buscar o ir a…'], ['G luego I', 'Ir al inicio'], ['G luego C', 'Ir al calendario'], ['G luego A', 'Ir a las citas'], ['G luego L', 'Ir a los clientes'], ['N', 'Nueva cita'], ['?', 'Ver esta ayuda'], ['En el calendario', 'T hoy · ← → navegar · D S M vistas']];
      var dl = h('dl', { class: 'shortcut-list' });
      rows.forEach(function (r) { dl.appendChild(h('div', {}, [h('dt', {}, [h('kbd', { class: 'kbd', text: r[0] })]), h('dd', { text: r[1] })])); });
      dlg = h('dialog', { class: 'modal modal-sm', id: 'shortcuts-dialog', 'aria-labelledby': 'sc-title' }, [
        h('div', { class: 'modal-head' }, [h('h2', { id: 'sc-title', class: 'serif', text: 'Atajos de teclado' }), h('button', { type: 'button', class: 'btn btn-ghost btn-icon', 'data-modal-close': '', 'aria-label': 'Cerrar' }, [icon('x')])]),
        h('div', { class: 'modal-body' }, [dl])
      ]);
      d.body.appendChild(dlg);
    }
    window.Ap.modal.open(dlg);
  }
  function go(path) { if (paths[path]) { window.location.href = url(path); return true; } return false; }
  d.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && !e.altKey && e.key.toLowerCase() === 'k') { e.preventDefault(); overlay && !overlay.hidden ? close() : open(); return; }
    if (e.ctrlKey || e.metaKey || e.altKey || e.defaultPrevented) { return; }
    if (typing(e.target) || (overlay && !overlay.hidden) || d.querySelector('dialog[open]')) { return; }
    var k = e.key;
    if (gPending) {
      gPending = false; clearTimeout(gTimer);
      var map = { c: '/admin/calendario', i: '/admin', a: '/admin/citas', l: '/admin/clientes' };
      var t = map[k.toLowerCase()];
      if (t && go(t)) { e.preventDefault(); }
      return;
    }
    if (k === '/') { e.preventDefault(); open(); }
    else if (k === '?') { e.preventDefault(); help(); }
    else if (k.toLowerCase() === 'g') { gPending = true; gTimer = setTimeout(function () { gPending = false; }, 1200); }
    else if (k.toLowerCase() === 'n' && !$('[data-calendar]')) { if (go('/admin/citas')) { e.preventDefault(); window.location.href = url('/admin/citas/nueva'); } }
  });
})();
