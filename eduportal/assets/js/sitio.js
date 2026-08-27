/* EduPortal · interacciones del sitio público */
(function () {
  'use strict';
  const btn = document.querySelector('[data-nav-publico]');
  const nav = document.getElementById('nav-publico');
  if (btn && nav) {
    btn.addEventListener('click', () => {
      const abierta = nav.classList.toggle('abierta');
      btn.setAttribute('aria-expanded', abierta ? 'true' : 'false');
    });
  }
  const observador = 'IntersectionObserver' in window
    ? new IntersectionObserver((entradas) => {
        entradas.forEach((e) => {
          if (e.isIntersecting) {
            e.target.style.opacity = '1';
            e.target.style.transform = 'none';
            observador.unobserve(e.target);
          }
        });
      }, { threshold: 0.12 })
    : null;
  if (observador && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('[data-animar]').forEach((el) => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(18px)';
      el.style.transition = 'opacity 520ms cubic-bezier(.22,1,.36,1), transform 520ms cubic-bezier(.22,1,.36,1)';
      observador.observe(el);
    });
  }
})();
