/* Fuente de Vida — public scripts */
(function () {
  'use strict';

  // Mobile navigation
  var toggle = document.getElementById('navToggle');
  var nav = document.getElementById('mainNav');
  var backdrop;
  if (toggle && nav) {
    backdrop = document.createElement('div');
    backdrop.className = 'nav-backdrop';
    document.body.appendChild(backdrop);

    function closeNav() {
      nav.classList.remove('is-open');
      backdrop.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }
    function openNav() {
      nav.classList.add('is-open');
      backdrop.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    }
    toggle.addEventListener('click', function () {
      nav.classList.contains('is-open') ? closeNav() : openNav();
    });
    backdrop.addEventListener('click', closeNav);
    nav.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', closeNav);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeNav();
    });
  }

  // Shrink header on scroll
  var header = document.getElementById('siteHeader');
  if (header) {
    window.addEventListener('scroll', function () {
      header.classList.toggle('is-scrolled', window.scrollY > 20);
    }, { passive: true });
  }

  // Lightbox for gallery
  var lb = document.getElementById('lightbox');
  if (lb) {
    var img = lb.querySelector('.lightbox__img');
    var cap = lb.querySelector('.lightbox__caption');
    var close = lb.querySelector('.lightbox__close');
    document.querySelectorAll('.photo-grid__item').forEach(function (item) {
      item.addEventListener('click', function (e) {
        e.preventDefault();
        img.src = item.getAttribute('href');
        cap.textContent = item.getAttribute('data-caption') || '';
        lb.classList.add('is-open');
        lb.setAttribute('aria-hidden', 'false');
      });
    });
    function closeLb() { lb.classList.remove('is-open'); lb.setAttribute('aria-hidden', 'true'); img.src = ''; }
    close.addEventListener('click', closeLb);
    lb.addEventListener('click', function (e) { if (e.target === lb) closeLb(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeLb(); });
  }
})();
