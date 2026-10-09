/* Portal público: menú móvil, revelado al scroll, contadores, parallax suave, barra flotante */
(function () {
  'use strict';
  var d = document, de = d.documentElement;
  de.classList.remove('no-js'); de.classList.add('js');
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  // menú móvil
  var burger = d.querySelector('.burger'), mnav = d.getElementById('mnav');
  if (burger && mnav) {
    var setMenu = function (open) {
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      burger.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
      mnav.classList.toggle('open', open);
      d.body.classList.toggle('menu-open', open);
      d.body.style.overflow = open ? 'hidden' : '';
      mnav.toggleAttribute('inert', !open);
    };
    setMenu(false);
    burger.addEventListener('click', function () { setMenu(burger.getAttribute('aria-expanded') !== 'true'); });
    mnav.addEventListener('click', function (e) { if (e.target.closest('a')) setMenu(false); });
    d.addEventListener('keydown', function (e) { if (e.key === 'Escape' && mnav.classList.contains('open')) { setMenu(false); burger.focus(); } });
    matchMedia('(min-width:980px)').addEventListener('change', function (m) { if (m.matches) setMenu(false); });
  }

  // revelado
  var rv = d.querySelectorAll('.rv');
  if (rv.length) {
    if (reduce || !('IntersectionObserver' in window)) { rv.forEach(function (n) { n.classList.add('in'); }); }
    else {
      var io = new IntersectionObserver(function (es) {
        es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
      }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
      rv.forEach(function (n) { io.observe(n); });
    }
  }

  // contadores
  var nums = d.querySelectorAll('[data-count]');
  nums.forEach(function (n) {
    var to = parseInt(n.getAttribute('data-count'), 10) || 0;
    if (reduce || !('IntersectionObserver' in window)) { n.textContent = to; return; }
    n.textContent = '0';
    var o = new IntersectionObserver(function (es) {
      if (!es[0].isIntersecting) return;
      o.disconnect();
      var t0 = performance.now(), dur = 1300;
      (function tick(t) {
        var p = Math.min(1, (t - t0) / dur), v = 1 - Math.pow(1 - p, 3);
        n.textContent = Math.round(to * v);
        if (p < 1) requestAnimationFrame(tick);
      })(t0);
    }, { threshold: 0.6 });
    o.observe(n);
  });

  // parallax suave
  var pars = d.querySelectorAll('[data-par]');
  if (pars.length && !reduce) {
    var ticking = false;
    var upd = function () {
      var y = window.scrollY || 0;
      if (y < 1200) pars.forEach(function (n) { n.style.transform = 'translate3d(0,' + (y * parseFloat(n.getAttribute('data-par'))).toFixed(1) + 'px,0)'; });
      ticking = false;
    };
    window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(upd); } }, { passive: true });
  }

  // barra flotante: aparece cuando el CTA del hero sale de vista
  var fab = d.getElementById('fab'), cta = d.getElementById('cta-hero');
  if (fab && cta && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (es) {
      var gone = !es[0].isIntersecting && es[0].boundingClientRect.top < 0;
      fab.classList.toggle('on', gone);
    }).observe(cta);
  } else if (fab) { fab.classList.add('on'); }

  // copiar (botones con data-copy)
  d.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (!b) return;
    var txt = b.getAttribute('data-copy'), ok = function () { var t = b.textContent; b.textContent = 'Copiado ✓'; setTimeout(function () { b.textContent = t; }, 1800); };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(txt).then(ok, function () {});
  });
})();
