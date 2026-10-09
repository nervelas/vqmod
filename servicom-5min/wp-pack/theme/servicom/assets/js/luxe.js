/* Servicom LUXE: revelado, paralaje, carrusel del héroe, luz en tarjetas, lightbox, video, botón subir. Sin dependencias. */
(function () {
  'use strict';
  var d = document, w = window;
  var reduce = w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var editing = d.body.classList.contains('sc-editing');

  /* Revelado al hacer scroll */
  var rv = d.querySelectorAll('.lx-rv');
  if (rv.length) {
    if (!('IntersectionObserver' in w) || reduce || editing) {
      rv.forEach(function (e) { e.classList.add('is-in'); });
    } else {
      var io = new IntersectionObserver(function (es) {
        es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
      rv.forEach(function (e) { io.observe(e); });
      setTimeout(function () { rv.forEach(function (e) { if (!e.classList.contains('is-in') && e.getBoundingClientRect().top < w.innerHeight) { e.classList.add('is-in'); } }); }, 1200);
    }
  }

  /* Cabecera con sombra y botón subir */
  var header = d.getElementById('sc-header');
  var top = d.querySelector('.lx-top');
  var ticking = false;
  function onScroll() {
    var y = w.pageYOffset || d.documentElement.scrollTop;
    if (header) { header.classList.toggle('is-scrolled', y > 24); }
    if (top) { top.hidden = y < 700; }
    if (!reduce) {
      d.querySelectorAll('[data-lx-parallax]').forEach(function (p) {
        var r = p.parentNode.getBoundingClientRect();
        if (r.bottom < 0 || r.top > w.innerHeight) { return; }
        p.style.transform = 'translate3d(0,' + (-r.top * 0.18).toFixed(1) + 'px,0)';
      });
    }
    ticking = false;
  }
  w.addEventListener('scroll', function () { if (!ticking) { ticking = true; w.requestAnimationFrame(onScroll); } }, { passive: true });
  onScroll();
  if (top) { top.addEventListener('click', function () { w.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); }); }

  /* Carrusel del héroe */
  var slides = d.querySelectorAll('.lx-slide');
  if (slides.length > 1 && !reduce && !editing) {
    var cur = 0;
    setInterval(function () {
      slides[cur].classList.remove('is-on');
      cur = (cur + 1) % slides.length;
      slides[cur].classList.add('is-on');
    }, 6500);
  }

  /* Luz que sigue al cursor en las tarjetas */
  if (!reduce && w.matchMedia && w.matchMedia('(hover: hover)').matches) {
    d.querySelectorAll('.lx-card').forEach(function (c) {
      c.addEventListener('pointermove', function (e) {
        var r = c.getBoundingClientRect();
        c.style.setProperty('--mx', (e.clientX - r.left) + 'px');
        c.style.setProperty('--my', (e.clientY - r.top) + 'px');
      });
    });
  }

  /* Anclas suaves */
  d.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[href^="#"]') : null;
    if (!a || editing) { return; }
    var id = a.getAttribute('href').slice(1);
    var t = id ? d.getElementById(id) : null;
    if (t) {
      e.preventDefault();
      var y = t.getBoundingClientRect().top + (w.pageYOffset || 0) - (header ? header.offsetHeight : 0) + 1;
      w.scrollTo({ top: y, behavior: reduce ? 'auto' : 'smooth' });
    }
  });

  /* Lightbox */
  var lb = d.querySelector('.lx-lightbox');
  if (lb) {
    var img = d.createElement('img');
    img.alt = '';
    lb.appendChild(img);
    img.removeAttribute('src');
    var close = function () { lb.hidden = true; img.removeAttribute('src'); };
    d.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('[data-lx-lightbox]') : null;
      if (a && !editing) { e.preventDefault(); img.src = a.getAttribute('href'); lb.hidden = false; }
    });
    lb.addEventListener('click', function (e) { if (e.target === lb || (e.target.closest && e.target.closest('.lx-lightbox__x'))) { close(); } });
    d.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !lb.hidden) { close(); } });
  }

  /* Video (carga al hacer clic) */
  d.querySelectorAll('[data-lx-yt]').forEach(function (v) {
    v.addEventListener('click', function () {
      if (v.querySelector('iframe')) { return; }
      var f = d.createElement('iframe');
      f.src = 'https://www.youtube-nocookie.com/embed/' + v.getAttribute('data-lx-yt') + '?autoplay=1&rel=0';
      f.allow = 'autoplay; encrypted-media; picture-in-picture';
      f.allowFullscreen = true;
      f.title = 'Video';
      v.appendChild(f);
    });
  });
})();
