/* Agenda Premium · interfaz compartida (vanilla ES2020, sin dependencias, compatible con CSP estricta).
 * Atributos: data-vars · data-modal-open/close · data-copy · data-confirm (data-confirm-label, data-confirm-danger)
 *   data-tabs (.tab[aria-controls|data-tab]) · .dropdown · data-theme-toggle[data-theme-url] · data-ticker[data-format]
 *   data-sortable[data-url] · data-sidebar-toggle/close.  API global: window.Ap */
(function () {
  'use strict';
  var d = document, root = d.documentElement;
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var meta = function (n) { var m = $('meta[name="' + n + '"]'); return m ? m.getAttribute('content') || '' : ''; };
  var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

  function ready(fn) { if (d.readyState === 'loading') { d.addEventListener('DOMContentLoaded', fn); } else { fn(); } }

  /* ---------- Ap: base, rutas ---------- */
  var base = meta('base-path') || (function () { var e = $('[data-base]'); return e ? e.getAttribute('data-base') || '' : ''; })();
  base = base.replace(/\/+$/, '');
  function url(path) { path = String(path || ''); if (/^(https?:)?\/\//i.test(path)) { return path; } return base + (path.charAt(0) === '/' ? path : '/' + path); }

  /* ---------- Ap.fetchJson ---------- */
  function friendly(status) {
    if (status === 401 || status === 419) { return 'Tu sesión caducó. Recarga la página e inicia sesión de nuevo.'; }
    if (status === 403) { return 'No tienes permiso para realizar esta acción.'; }
    if (status === 404) { return 'No encontramos lo que buscas.'; }
    if (status === 422) { return 'Revisa los datos e inténtalo otra vez.'; }
    if (status === 429) { return 'Demasiados intentos. Espera un momento e inténtalo de nuevo.'; }
    if (status >= 500) { return 'Algo salió mal de nuestro lado. Inténtalo de nuevo en unos minutos.'; }
    return 'No pudimos completar la acción. Inténtalo de nuevo.';
  }
  function fetchJson(u, opts) {
    opts = opts || {};
    var method = (opts.method || 'GET').toUpperCase();
    var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    var token = meta('csrf-token');
    if (token) { headers['X-CSRF-Token'] = token; }
    Object.keys(opts.headers || {}).forEach(function (k) { headers[k] = opts.headers[k]; });
    var body = opts.body;
    if (body !== undefined && body !== null && !(body instanceof FormData) && !(body instanceof URLSearchParams) && typeof body !== 'string' && !(body instanceof Blob)) {
      headers['Content-Type'] = 'application/json'; body = JSON.stringify(body);
    }
    var ctrl = window.AbortController ? new AbortController() : null;
    var timer = ctrl && setTimeout(function () { ctrl.abort(); }, opts.timeout || 30000);
    return fetch(/^(https?:)?\/\//i.test(u) || u.indexOf(base + '/') === 0 || !base ? u : url(u), {
      method: method, headers: headers, body: method === 'GET' || method === 'HEAD' ? undefined : body,
      credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined
    }).then(function (res) {
      if (timer) { clearTimeout(timer); }
      return res.text().then(function (txt) {
        var data = null;
        if (txt) { try { data = JSON.parse(txt); } catch (e) { data = null; } }
        if (!res.ok || (data && data.ok === false && data.error)) {
          var err = new Error((data && (data.error || data.message)) || friendly(res.status));
          err.status = res.status; err.data = data; throw err;
        }
        if (data === null) { var e2 = new Error('El servidor respondió de forma inesperada. Inténtalo de nuevo.'); e2.status = res.status; throw e2; }
        return data;
      });
    }, function (e) {
      if (timer) { clearTimeout(timer); }
      var err = new Error(e && e.name === 'AbortError' ? 'La solicitud tardó demasiado. Inténtalo de nuevo.' : 'No pudimos conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.');
      err.status = 0; throw err;
    });
  }

  /* ---------- Toasts ---------- */
  var TOAST_ICON = { ok: 'check', success: 'check', err: 'alert', error: 'alert', warn: 'alert', info: 'info' };
  function icon(name) {
    var ns = 'http://www.w3.org/2000/svg', s = d.createElementNS(ns, 'svg'), u = d.createElementNS(ns, 'use');
    s.setAttribute('class', 'ic'); s.setAttribute('aria-hidden', 'true'); s.setAttribute('focusable', 'false');
    var sprite = $('use[href*="icons.svg"]'); var href = sprite ? sprite.getAttribute('href').split('#')[0] : url('/assets/img/icons.svg');
    u.setAttribute('href', href + '#i-' + name); s.appendChild(u); return s;
  }
  function toastBox() {
    var b = $('#toasts') || $('.toasts');
    if (!b) { b = d.createElement('div'); b.id = 'toasts'; b.className = 'toasts'; b.setAttribute('aria-live', 'polite'); d.body.appendChild(b); }
    return b;
  }
  function toast(msg, type) {
    type = type || 'info';
    var box = toastBox(), t = d.createElement('div');
    t.className = 'toast toast-' + type; t.setAttribute('role', type === 'err' || type === 'error' ? 'alert' : 'status');
    t.appendChild(icon(TOAST_ICON[type] || 'info'));
    var m = d.createElement('div'); m.className = 'toast-msg'; m.textContent = String(msg); t.appendChild(m);
    var x = d.createElement('button'); x.type = 'button'; x.className = 'toast-close'; x.setAttribute('aria-label', 'Cerrar aviso'); x.appendChild(icon('x')); t.appendChild(x);
    function close() { if (t.parentNode) { t.classList.add('is-leaving'); setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, reduce ? 0 : 320); } }
    x.addEventListener('click', close);
    box.appendChild(t);
    while (box.children.length > 4) { box.removeChild(box.firstChild); }
    setTimeout(close, type === 'err' || type === 'error' ? 8000 : 5000);
    return t;
  }

  /* ---------- Modales ---------- */
  function openModal(dlg, opener) {
    if (!dlg || dlg.open) { return; }
    dlg._opener = opener || d.activeElement;
    if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
    var f = $('[autofocus]', dlg) || $(FOCUSABLE, $('.modal-body', dlg) || dlg) || $(FOCUSABLE, dlg);
    if (f) { f.focus(); }
    dlg.dispatchEvent(new CustomEvent('modal:open'));
  }
  function closeModal(dlg, result) {
    if (!dlg) { return; }
    if (result !== undefined) { dlg.returnValue = String(result); }
    if (typeof dlg.close === 'function') { dlg.close(dlg.returnValue); } else { dlg.removeAttribute('open'); dlg.dispatchEvent(new Event('close')); }
  }
  d.addEventListener('close', function (e) {
    var dlg = e.target;
    if (dlg && dlg.tagName === 'DIALOG' && dlg._opener && d.contains(dlg._opener) && dlg._opener.focus) { try { dlg._opener.focus(); } catch (x) { /* nada */ } }
  }, true);
  d.addEventListener('click', function (e) {
    var op = e.target.closest && e.target.closest('[data-modal-open]');
    if (op) { var dlg = $(op.getAttribute('data-modal-open')); if (dlg) { e.preventDefault(); openModal(dlg, op); } return; }
    var cl = e.target.closest && e.target.closest('[data-modal-close]');
    if (cl) { var own = cl.closest('dialog'); var tgt = cl.getAttribute('data-modal-close'); closeModal(tgt && tgt.charAt(0) === '#' ? $(tgt) : own, cl.getAttribute('data-modal-value') || ''); return; }
    var t = e.target; // clic en el fondo (fuera de la caja)
    if (t && t.tagName === 'DIALOG' && t.classList.contains('modal') && !t.hasAttribute('data-modal-static')) {
      var r = t.getBoundingClientRect();
      if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) { closeModal(t, 'cancel'); }
    }
  });

  /* ---------- Ap.confirm ---------- */
  var confirmDlg = null;
  function confirmBox(msg, o) {
    o = o || {};
    return new Promise(function (resolve) {
      if (!confirmDlg) {
        confirmDlg = d.createElement('dialog'); confirmDlg.className = 'modal modal-sm'; confirmDlg.setAttribute('aria-labelledby', 'ap-cf-t'); confirmDlg.setAttribute('aria-describedby', 'ap-cf-m');
        confirmDlg.innerHTML = '<div class="modal-head"><h2 id="ap-cf-t"></h2></div><div class="modal-body"><p id="ap-cf-m" class="muted"></p></div><div class="modal-foot"><button type="button" class="btn btn-ghost" data-r="0"></button><button type="button" class="btn" data-r="1"></button></div>';
        d.body.appendChild(confirmDlg);
      }
      var dlg = confirmDlg, ok = $('[data-r="1"]', dlg), no = $('[data-r="0"]', dlg), done = false;
      $('#ap-cf-t', dlg).textContent = o.title || 'Confirma la acción';
      $('#ap-cf-m', dlg).textContent = msg;
      ok.textContent = o.label || 'Confirmar'; no.textContent = o.cancel || 'Cancelar';
      ok.className = 'btn ' + (o.danger ? 'btn-danger' : 'btn-gold');
      function finish(v) {
        if (done) { return; } done = true;
        ok.removeEventListener('click', yes); no.removeEventListener('click', nope); dlg.removeEventListener('cancel', nope); dlg.removeEventListener('close', nope);
        if (dlg.open) { closeModal(dlg); }
        resolve(v);
      }
      function yes() { finish(true); } function nope() { finish(false); }
      ok.addEventListener('click', yes); no.addEventListener('click', nope); dlg.addEventListener('cancel', nope); dlg.addEventListener('close', nope);
      openModal(dlg, d.activeElement);
      no.focus();
    });
  }

  /* data-confirm */
  var approved = new WeakSet();
  function confOpts(el) { return { label: el.getAttribute('data-confirm-label') || undefined, danger: el.hasAttribute('data-confirm-danger') || /elimin|borr|cancel|quit/i.test(el.getAttribute('data-confirm') || ''), title: el.getAttribute('data-confirm-title') || undefined }; }
  d.addEventListener('click', function (e) {
    var el = e.target.closest && e.target.closest('button[data-confirm],a[data-confirm],input[type=submit][data-confirm]');
    if (!el) { return; }
    if (approved.has(el)) { approved.delete(el); return; }
    e.preventDefault(); e.stopImmediatePropagation();
    confirmBox(el.getAttribute('data-confirm'), confOpts(el)).then(function (yes) { if (yes) { approved.add(el); el.click(); } });
  }, true);
  d.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.hasAttribute || !f.hasAttribute('data-confirm')) { return; }
    if (approved.has(f)) { approved.delete(f); return; }
    e.preventDefault(); e.stopImmediatePropagation();
    var sub = e.submitter;
    confirmBox(f.getAttribute('data-confirm'), confOpts(f)).then(function (yes) {
      if (!yes) { return; } approved.add(f);
      if (f.requestSubmit) { f.requestSubmit(sub || undefined); } else { f.submit(); }
    });
  }, true);

  /* ---------- data-copy ---------- */
  function copyText(txt) {
    if (navigator.clipboard && window.isSecureContext) { return navigator.clipboard.writeText(txt); }
    return new Promise(function (res, rej) {
      var ta = d.createElement('textarea'); ta.value = txt; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0'; d.body.appendChild(ta); ta.select();
      try { d.execCommand('copy') ? res() : rej(); } catch (e) { rej(e); } d.body.removeChild(ta);
    });
  }
  d.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-copy]'); if (!b) { return; }
    e.preventDefault();
    var v = b.getAttribute('data-copy'), txt = v;
    if (v.charAt(0) === '#' && v.length > 1 && /^#[\w-]+$/.test(v)) { var src = $(v); if (src) { txt = 'value' in src && src.value !== undefined ? src.value : src.textContent.trim(); } }
    copyText(txt).then(function () {
      toast(b.getAttribute('data-copied') || 'Copiado al portapapeles.', 'ok'); b.classList.add('is-copied'); setTimeout(function () { b.classList.remove('is-copied'); }, 1500);
    }, function () { toast('No pudimos copiar. Selecciona el texto y cópialo manualmente.', 'warn'); });
  });

  /* ---------- Tabs ---------- */
  function initTabs(c) {
    if (c._tabs) { return; } c._tabs = true;
    var tabs = $$('.tab', c); if (!tabs.length) { return; }
    var list = $('.tabs', c) || tabs[0].parentNode; list.setAttribute('role', 'tablist');
    function panelOf(t) { var id = t.getAttribute('aria-controls') || t.getAttribute('data-tab'); return id ? ($('#' + id, c) || d.getElementById(id) || $('[data-panel="' + id + '"]', c)) : null; }
    function show(t, focus) {
      tabs.forEach(function (x) { var on = x === t, p = panelOf(x); x.classList.toggle('is-active', on); x.setAttribute('aria-selected', on ? 'true' : 'false'); x.tabIndex = on ? 0 : -1; if (p) { p.hidden = !on; } });
      if (focus) { t.focus(); }
      c.dispatchEvent(new CustomEvent('tabs:change', { detail: { tab: t } }));
    }
    tabs.forEach(function (t, i) {
      var p = panelOf(t); t.setAttribute('role', 'tab'); if (!t.id) { t.id = 'tab-' + Math.random().toString(36).slice(2, 8); }
      if (p) { p.setAttribute('role', 'tabpanel'); p.setAttribute('aria-labelledby', t.id); if (!t.hasAttribute('aria-controls') && p.id) { t.setAttribute('aria-controls', p.id); } }
      t.addEventListener('click', function (e) { if (t.tagName === 'A' && !p) { return; } e.preventDefault(); show(t); });
      t.addEventListener('keydown', function (e) {
        var n = null;
        if (e.key === 'ArrowRight') { n = tabs[(i + 1) % tabs.length]; } else if (e.key === 'ArrowLeft') { n = tabs[(i - 1 + tabs.length) % tabs.length]; } else if (e.key === 'Home') { n = tabs[0]; } else if (e.key === 'End') { n = tabs[tabs.length - 1]; }
        if (n) { e.preventDefault(); show(n, true); }
      });
    });
    var hash = location.hash && tabs.filter(function (t) { return (t.getAttribute('aria-controls') || t.getAttribute('data-tab')) === location.hash.slice(1); })[0];
    show(hash || tabs.filter(function (t) { return t.classList.contains('is-active'); })[0] || tabs[0]);
  }

  /* ---------- Dropdown ---------- */
  function initDropdown(dd) {
    if (dd._dd) { return; } dd._dd = true;
    var tg = $('[data-dropdown-toggle]', dd) || dd.firstElementChild, menu = $('.dropdown-menu', dd);
    if (!tg || !menu) { return; }
    tg.setAttribute('aria-haspopup', 'menu'); tg.setAttribute('aria-expanded', 'false'); menu.setAttribute('role', 'menu');
    var items = function () { return $$('a[href],button:not([disabled]),[role=menuitem]', menu); };
    items().forEach(function (i) { i.setAttribute('role', 'menuitem'); i.tabIndex = -1; });
    function open(focusFirst) {
      $$('.dropdown.is-open').forEach(function (o) { if (o !== dd) { o._close && o._close(); } });
      dd.classList.add('is-open'); tg.setAttribute('aria-expanded', 'true');
      var r = menu.getBoundingClientRect(); if (r.left < 8) { dd.classList.add('is-left'); }
      if (focusFirst) { var f = items()[0]; if (f) { f.focus(); } }
    }
    function close(back) { dd.classList.remove('is-open'); tg.setAttribute('aria-expanded', 'false'); if (back) { tg.focus(); } }
    dd._close = close;
    tg.addEventListener('click', function (e) { e.preventDefault(); dd.classList.contains('is-open') ? close() : open(false); });
    tg.addEventListener('keydown', function (e) { if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); open(true); } });
    menu.addEventListener('keydown', function (e) {
      var l = items(), i = l.indexOf(d.activeElement);
      if (e.key === 'ArrowDown') { e.preventDefault(); l[(i + 1) % l.length].focus(); } else if (e.key === 'ArrowUp') { e.preventDefault(); l[(i - 1 + l.length) % l.length].focus(); }
      else if (e.key === 'Home') { e.preventDefault(); l[0].focus(); } else if (e.key === 'End') { e.preventDefault(); l[l.length - 1].focus(); }
      else if (e.key === 'Escape') { e.preventDefault(); close(true); } else if (e.key === 'Tab') { close(); }
    });
    menu.addEventListener('click', function (e) { if (e.target.closest('a,button')) { close(); } });
  }
  d.addEventListener('click', function (e) { $$('.dropdown.is-open').forEach(function (o) { if (!o.contains(e.target)) { o._close && o._close(); } }); });
  d.addEventListener('keydown', function (e) { if (e.key === 'Escape') { $$('.dropdown.is-open').forEach(function (o) { o._close && o._close(true); }); closeSidebar(); } });

  /* ---------- Tema ---------- */
  function paintThemeBtn(btn) {
    var light = root.getAttribute('data-theme') === 'light', u = $('use', btn);
    if (u) { u.setAttribute('href', u.getAttribute('href').replace(/#i-.*$/, '#i-' + (light ? 'sun' : 'moon'))); }
    btn.setAttribute('aria-pressed', light ? 'true' : 'false'); btn.setAttribute('title', light ? 'Modo claro activo. Cambiar a oscuro' : 'Modo oscuro activo. Cambiar a claro');
  }
  d.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-theme-toggle]'); if (!b) { return; }
    var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('ap-theme', next); } catch (x) { /* nada */ }
    var m = $('meta[name="theme-color"]'); if (m) { m.setAttribute('content', next === 'light' ? '#F4EEDF' : '#06080D'); }
    $$('[data-theme-toggle]').forEach(paintThemeBtn);
    var u = b.getAttribute('data-theme-url');
    if (u) { fetchJson(u, { method: 'POST', body: { theme: next } }).catch(function () { /* la preferencia local ya quedó aplicada */ }); }
  });

  /* ---------- Contador mecánico ---------- */
  function fmtNum(n, fmt, dec, prefix) {
    var s = Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    return (n < 0 ? '-' : '') + (fmt === 'money' ? prefix : '') + s;
  }
  function initTicker(el) {
    if (el._tk) { return; } el._tk = true;
    var val = parseFloat(el.getAttribute('data-ticker')); if (isNaN(val)) { return; }
    var fmt = el.getAttribute('data-format') || 'int', dec = el.hasAttribute('data-decimals') ? parseInt(el.getAttribute('data-decimals'), 10) : (fmt === 'money' ? 2 : 0);
    var prefix = el.hasAttribute('data-prefix') ? el.getAttribute('data-prefix') : ((el.textContent.match(/^[^\d-]*/) || [''])[0].trim() || 'Q');
    var finalTxt = fmtNum(val, fmt, dec, prefix);
    if (reduce) { el.textContent = finalTxt; return; }
    el.setAttribute('role', 'img'); el.setAttribute('aria-label', finalTxt); el.textContent = '';
    var wrap = d.createElement('span'); wrap.className = 'ticker'; wrap.setAttribute('aria-hidden', 'true'); el.appendChild(wrap);
    var cols = [], chars = finalTxt.split('');
    chars.forEach(function (ch) {
      if (/\d/.test(ch)) {
        var col = d.createElement('span'); col.className = 'ticker-col'; var st = d.createElement('span'); st.className = 'ticker-strip';
        for (var i = 0; i <= 9; i++) { var s = d.createElement('span'); s.textContent = i; st.appendChild(s); }
        col.appendChild(st); wrap.appendChild(col); cols.push([st, parseInt(ch, 10)]);
      } else { var sep = d.createElement('span'); sep.className = 'ticker-sep'; sep.textContent = ch; wrap.appendChild(sep); }
    });
    function run() { cols.forEach(function (c, i) { var rev = cols.length - 1 - i; c[0].style.setProperty('--dur', (0.9 + rev * 0.14) + 's'); c[0].style.setProperty('--d', c[1]); }); }
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (en) { if (en[0].isIntersecting) { io.disconnect(); setTimeout(run, 60); } }, { threshold: 0.2 }); io.observe(el);
    } else { run(); }
  }

  /* ---------- Ordenar arrastrando ---------- */
  function initSortable(list) {
    if (list._sort) { return; } list._sort = true;
    var url_ = list.getAttribute('data-url'), drag = null, timer = null;
    var live = d.createElement('div'); live.className = 'sr-only'; live.setAttribute('aria-live', 'polite'); list.parentNode.insertBefore(live, list.nextSibling);
    var items = function () { return Array.prototype.slice.call(list.children).filter(function (c) { return c.nodeType === 1 && !c.hasAttribute('data-sortable-skip'); }); };
    function idOf(it) { return it.getAttribute('data-id') || it.getAttribute('data-sort-id') || (it.dataset && it.dataset.id) || ''; }
    function save() {
      list.dispatchEvent(new CustomEvent('sortable:change', { detail: { ids: items().map(idOf) } }));
      if (!url_) { return; }
      clearTimeout(timer);
      timer = setTimeout(function () {
        var fd = new URLSearchParams(); items().forEach(function (it) { fd.append('ids[]', idOf(it)); }); fd.append('_csrf', meta('csrf-token'));
        fetchJson(url_, { method: 'POST', body: fd }).then(function () { toast('Orden guardado.', 'ok'); }, function (err) { toast(err.message, 'err'); });
      }, 350);
    }
    function clear() { items().forEach(function (i) { i.classList.remove('drop-before', 'drop-after'); }); }
    items().forEach(function (it) { if (!it.hasAttribute('draggable')) { it.setAttribute('draggable', 'true'); } if (!it.hasAttribute('tabindex') && !$(FOCUSABLE, it)) { it.tabIndex = 0; } });
    list.addEventListener('dragstart', function (e) {
      var it = e.target.closest && e.target.closest('[draggable]'); if (!it || it.parentNode !== list) { return; }
      drag = it; it.classList.add('is-dragging'); if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', idOf(it)); } catch (x) { /* nada */ } }
    });
    list.addEventListener('dragover', function (e) {
      if (!drag) { return; } e.preventDefault(); clear();
      var over = e.target.closest && e.target.closest('[draggable]'); if (!over || over === drag || over.parentNode !== list) { return; }
      var r = over.getBoundingClientRect(), after = (e.clientY - r.top) > r.height / 2;
      over.classList.add(after ? 'drop-after' : 'drop-before');
    });
    list.addEventListener('drop', function (e) {
      if (!drag) { return; } e.preventDefault();
      var over = e.target.closest && e.target.closest('[draggable]');
      if (over && over !== drag && over.parentNode === list) { var r = over.getBoundingClientRect(); list.insertBefore(drag, (e.clientY - r.top) > r.height / 2 ? over.nextSibling : over); save(); }
      clear();
    });
    list.addEventListener('dragend', function () { if (drag) { drag.classList.remove('is-dragging'); } drag = null; clear(); });
    function move(it, dir) {
      var all = items(), i = all.indexOf(it), j = i + dir; if (j < 0 || j >= all.length) { return; }
      list.insertBefore(it, dir < 0 ? all[j] : all[j].nextSibling); (it.matches(FOCUSABLE) ? it : (it.querySelector('[data-move]') || it)).focus && it.focus();
      it.classList.add('is-moved'); setTimeout(function () { it.classList.remove('is-moved'); }, 700);
      live.textContent = 'Elemento movido a la posición ' + (j + 1) + ' de ' + all.length + '.'; save();
    }
    list.addEventListener('keydown', function (e) {
      if (!(e.altKey || e.ctrlKey) || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) { return; }
      var it = e.target.closest && e.target.closest('[draggable]'); if (!it || it.parentNode !== list) { return; }
      e.preventDefault(); move(it, e.key === 'ArrowUp' ? -1 : 1);
    });
    list.addEventListener('click', function (e) {
      var b = e.target.closest && e.target.closest('[data-move]'); if (!b) { return; }
      var it = b.closest('[draggable]'); if (it && it.parentNode === list) { e.preventDefault(); move(it, b.getAttribute('data-move') === 'up' ? -1 : 1); b.focus(); }
    });
  }

  /* ---------- Barra lateral ---------- */
  function closeSidebar() { if (d.body.classList.contains('sb-open')) { d.body.classList.remove('sb-open'); $$('[data-sidebar-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); }); } }
  d.addEventListener('click', function (e) {
    var t = e.target.closest && e.target.closest('[data-sidebar-toggle]');
    if (t) { var o = d.body.classList.toggle('sb-open'); t.setAttribute('aria-expanded', o ? 'true' : 'false'); if (o) { var f = $('.sb-link.is-active') || $('.sb-link'); if (f) { f.focus(); } } return; }
    if (e.target.closest && (e.target.closest('[data-sidebar-close]') || e.target.closest('.sidebar .sb-link'))) { closeSidebar(); }
  });
  window.addEventListener('resize', function () { if (window.innerWidth >= 1024) { closeSidebar(); } });

  /* ---------- data-vars ---------- */
  function applyVars(el) {
    var s = el.getAttribute('data-vars'); if (!s) { return; }
    s.split(';').forEach(function (p) {
      var i = p.indexOf(':'); if (i < 1) { return; }
      var k = p.slice(0, i).trim(), v = p.slice(i + 1).trim();
      if (/^--[a-z0-9-]+$/i.test(k)) { el.style.setProperty(k, v); }
    });
  }

  /* ---------- Brillo especular (solo ratón) ---------- */
  if (window.matchMedia && matchMedia('(hover: hover) and (pointer: fine)').matches) {
    d.addEventListener('pointermove', function (e) {
      if (e.pointerType !== 'mouse') { return; }
      var b = e.target.closest && e.target.closest('.btn-gold'); if (!b) { return; }
      var r = b.getBoundingClientRect(); b.style.setProperty('--mx', (e.clientX - r.left) + 'px'); b.style.setProperty('--my', (e.clientY - r.top) + 'px');
    }, { passive: true });
  }

  /* ---------- Inicio ---------- */
  function scan(ctx) {
    if (ctx.nodeType !== 1 && ctx !== d) { return; }
    if (ctx.nodeType === 1 && ctx.hasAttribute('data-vars')) { applyVars(ctx); }
    $$('[data-vars]', ctx).forEach(applyVars);
    $$('[data-tabs]', ctx).forEach(initTabs); $$('.dropdown', ctx).forEach(initDropdown); $$('[data-ticker]', ctx).forEach(initTicker); $$('[data-sortable]', ctx).forEach(initSortable);
  }
  ready(function () {
    scan(d); $$('[data-theme-toggle]').forEach(paintThemeBtn);
    var tg = $('[data-sidebar-toggle]'); if (tg) { tg.setAttribute('aria-expanded', 'false'); tg.setAttribute('aria-controls', 'sidebar'); }
    if ('MutationObserver' in window) {
      new MutationObserver(function (ms) { ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { scan(n); } }); }); }).observe(d.body, { childList: true, subtree: true });
    }
  });

  window.Ap = { base: base, url: url, ready: ready, fetchJson: fetchJson, toast: toast, confirm: function (m, o) { return confirmBox(m, o); }, modal: { open: openModal, close: closeModal }, copy: copyText };
})();
