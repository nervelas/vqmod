/* Agenda Premium · Estado del sistema (Admin4). Vanilla ES2020. */
(function () {
  'use strict';
  var d = document;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };

  function init() {
    // Botones con trabajo largo: evita doble envío y muestra el indicador de carga
    d.addEventListener('submit', function (e) {
      var f = e.target;
      if (!f || !f.hasAttribute || !f.hasAttribute('data-busy')) { return; }
      var btn = $('button[type=submit]', f);
      if (btn && !btn.classList.contains('is-loading')) {
        btn.classList.add('is-loading');
        btn.setAttribute('aria-busy', 'true');
        btn.setAttribute('aria-label', f.getAttribute('data-busy') || 'Procesando…');
      }
    });
    // El registro se muestra desde la parte superior (más reciente primero)
    var log = $('.p4-log');
    if (log) { log.scrollTop = 0; }
    // Resalta líneas de error en el registro
    $$('.p4-log-line').forEach(function (l) {
      if (/error|exception|fatal|falló|fallo/i.test(l.textContent)) { l.style.setProperty('color', 'var(--err-fg)'); }
    });
  }
  if (d.readyState === 'loading') { d.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
