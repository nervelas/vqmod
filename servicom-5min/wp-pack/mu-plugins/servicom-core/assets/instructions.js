/* Servicom - buscador y filtros de INSTRUCCIONES (sin dependencias) */
(function () {
  'use strict';
  var q = document.getElementById('sc-ins-q');
  var grid = document.getElementById('sc-ins-grid');
  if (!q || !grid) { return; }
  var cards = Array.prototype.slice.call(grid.querySelectorAll('.sc-ins__card'));
  var chips = Array.prototype.slice.call(document.querySelectorAll('.sc-ins__chip'));
  var empty = document.getElementById('sc-ins-empty');
  var count = document.getElementById('sc-ins-count');
  var group = '';

  function norm(s) {
    s = String(s || '').toLowerCase();
    try { s = s.normalize('NFD').replace(/[̀-ͯ]/g, ''); } catch (e) {}
    return s.replace(/\s+/g, ' ').trim();
  }

  function apply() {
    var terms = norm(q.value).split(' ').filter(Boolean);
    var shown = 0;
    cards.forEach(function (c) {
      var hay = c.getAttribute('data-k') || '';
      var ok = (!group || c.getAttribute('data-group') === group) &&
        terms.every(function (t) { return hay.indexOf(t) !== -1; });
      c.hidden = !ok;
      if (ok) { shown++; }
    });
    if (empty) { empty.hidden = shown !== 0; }
    if (count) {
      count.textContent = (terms.length || group)
        ? (shown === 1 ? '1 resultado' : shown + ' resultados')
        : '';
    }
  }

  q.addEventListener('input', apply);
  q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
  chips.forEach(function (b) {
    b.addEventListener('click', function () {
      group = b.getAttribute('data-group') || '';
      chips.forEach(function (x) {
        var on = x === b;
        x.classList.toggle('is-active', on);
        x.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      apply();
    });
  });
  apply();
})();
