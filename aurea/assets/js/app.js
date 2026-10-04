/* AUREA · comportamiento común del sitio público */
(() => {
  'use strict';
  document.documentElement.classList.remove('no-js');
  const btn = document.querySelector('[data-menu]');
  const nav = document.getElementById('nav');
  if (btn && nav) {
    btn.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      btn.setAttribute('aria-expanded', String(open));
    });
  }
  const targets = document.querySelectorAll('.reveal,.rule');
  if (!('IntersectionObserver' in window)) { targets.forEach((t) => t.classList.add('in')); return; }
  const io = new IntersectionObserver((entries) => {
    entries.forEach((en) => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
  }, { threshold: 0.15, rootMargin: '0px 0px -6% 0px' });
  targets.forEach((t) => io.observe(t));
})();
