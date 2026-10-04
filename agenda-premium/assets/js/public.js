/* Utilidades de las páginas públicas: contador mecánico, sello, aviso informativo, hora local, impresión. Sin cookies. */
(function (global) {
  'use strict';
  var DAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
  var MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

  function reduced() { return global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches; }
  function store(op, k, v) {
    try { if (op === 'get') return global.localStorage.getItem(k); global.localStorage.setItem(k, v); } catch (e) { /* sin almacenamiento */ }
    return null;
  }
  function pad(n) { return n < 10 ? '0' + n : String(n); }

  /* Contador tipo mecánico: cada cifra rueda al cambiar. */
  function mech(root, iso) {
    var target = Date.parse(iso);
    if (!root || isNaN(target)) return;
    var cells = {};
    ['d', 'h', 'm', 's'].forEach(function (u) { cells[u] = root.querySelector('[data-u="' + u + '"]'); });
    var last = {};
    function set(u, v) {
      var c = cells[u]; if (!c || last[u] === v) return;
      var first = last[u] === undefined; last[u] = v;
      c.textContent = v;
      if (!first && !reduced()) { c.classList.remove('is-tick'); void c.offsetWidth; c.classList.add('is-tick'); }
    }
    function tick() {
      var diff = Math.max(0, Math.floor((target - Date.now()) / 1000));
      set('d', String(Math.floor(diff / 86400)));
      set('h', pad(Math.floor(diff % 86400 / 3600)));
      set('m', pad(Math.floor(diff % 3600 / 60)));
      set('s', pad(diff % 60));
      if (diff === 0) { root.setAttribute('aria-label', 'Tu cita es ahora'); return false; }
      return true;
    }
    tick();
    var id = global.setInterval(function () { if (!tick()) global.clearInterval(id); }, 1000);
  }

  function stamp(seal) {
    if (!seal) return;
    seal.classList.add('is-stamped');
    if (!reduced() && global.navigator && global.navigator.vibrate) { try { global.navigator.vibrate([14, 60, 24]); } catch (e) { /* nada */ } }
  }

  function localTimes() {
    var nodes = document.querySelectorAll('[data-local-time]');
    if (!nodes.length || !global.Intl) return;
    var tz = '';
    try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { return; }
    Array.prototype.forEach.call(nodes, function (n) {
      var d = new Date(n.getAttribute('data-local-time'));
      if (isNaN(d)) return;
      var h = d.getHours(), m = d.getMinutes();
      n.textContent = '(en tu zona: ' + DAYS[d.getDay()] + ' ' + d.getDate() + ' de ' + MONTHS[d.getMonth()] + ', ' + (h % 12 || 12) + ':' + pad(m) + ' ' + (h < 12 ? 'a. m.' : 'p. m.') + ')';
    });
  }

  function offerCountdown() {
    var n = document.querySelector('[data-offer-countdown]');
    if (!n) return;
    var t = Date.parse(n.getAttribute('data-offer-countdown'));
    function tick() {
      var s = Math.max(0, Math.floor((t - Date.now()) / 1000));
      n.textContent = pad(Math.floor(s / 60)) + ':' + pad(s % 60);
      if (s === 0) { global.location.reload(); }
    }
    tick(); global.setInterval(tick, 1000);
  }

  function init() {
    var note = document.getElementById('cookie-note');
    if (note && store('get', 'ap_cookie_note') !== '1') {
      note.hidden = false;
      var b = note.querySelector('[data-dismiss-cookie]');
      if (b) b.addEventListener('click', function () { note.hidden = true; store('set', 'ap_cookie_note', '1'); });
    }
    var pr = document.querySelector('[data-print]');
    if (pr) pr.addEventListener('click', function () { global.print(); });
    localTimes();
    offerCountdown();
    // Páginas de gestión: el sello se estampa al cargar y corre el contador
    var m = document.querySelector('[data-manage]');
    if (m) {
      var s = m.querySelector('[data-seal]'); if (s) global.setTimeout(function () { stamp(s); }, 250);
      var mc = m.querySelector('[data-mech][data-target]'); if (mc) mech(mc, mc.getAttribute('data-target'));
    }
    // Confirmación de cancelación (si ui.js no la gestiona)
    Array.prototype.forEach.call(document.querySelectorAll('[data-cancel-form]'), function (f) {
      f.addEventListener('submit', function (e) {
        if (f.getAttribute('data-ok') === '1') return;
        if (global.Ap && typeof global.Ap.confirm === 'function') return; // ui.js ya intercepta [data-confirm]
        if (!global.confirm('¿Seguro que quieres cancelar esta cita?')) e.preventDefault();
      });
    });
  }

  global.ApPublic = { mech: mech, stamp: stamp, reduced: reduced, store: store };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})(window);
