/* AGROINCO — interacciones sobrias: header compacto, reveals, contadores. 60fps, respeta prefers-reduced-motion */
(function () {
  'use strict';
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Header que se compacta al bajar */
  var ticking = false;
  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      document.documentElement.classList.toggle('agro-compact', window.scrollY > 90);
      ticking = false;
    });
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  document.addEventListener('DOMContentLoaded', function () {
    /* Elementos que aparecen al hacer scroll */
    var targets = document.querySelectorAll(
      '.agro-cat, .agro-step, .agro-ind, .agro-stat, .agro-brand, ul.products li.product, .agro-sec .agro-h2'
    );
    if (!reduced && 'IntersectionObserver' in window) {
      targets.forEach(function (el, i) {
        el.classList.add('agro-reveal');
        el.style.transitionDelay = Math.min((i % 6) * 60, 300) + 'ms';
      });
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) { e.target.classList.add('agro-in'); io.unobserve(e.target); }
        });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
      targets.forEach(function (el) { io.observe(el); });
    }

    /* Contadores animados de la sección "desde 1976" */
    var counters = document.querySelectorAll('.agro-stat b[data-count]');
    if (counters.length) {
      var animate = function (el) {
        var end = parseInt(el.getAttribute('data-count'), 10);
        var suf = el.getAttribute('data-suffix') || '';
        if (reduced) { el.innerHTML = end + '<em>' + suf + '</em>'; return; }
        var t0 = null, dur = 1400;
        function step(t) {
          if (!t0) t0 = t;
          var p = Math.min((t - t0) / dur, 1);
          var eased = 1 - Math.pow(1 - p, 3);
          el.innerHTML = Math.round(end * eased) + '<em>' + suf + '</em>';
          if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
      };
      if ('IntersectionObserver' in window) {
        var io2 = new IntersectionObserver(function (entries) {
          entries.forEach(function (e) {
            if (e.isIntersecting) { animate(e.target); io2.unobserve(e.target); }
          });
        }, { threshold: 0.4 });
        counters.forEach(function (el) { io2.observe(el); });
      } else counters.forEach(animate);
    }
  });
})();
