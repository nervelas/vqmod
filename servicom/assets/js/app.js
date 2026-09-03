/* =============================================================================
   SERVICOM — Motor de interacción (JavaScript puro, sin dependencias)
   ========================================================================== */
(function () {
  'use strict';

  var cfg    = window.SERVICOM || {};
  var doc    = document;
  var body   = doc.body;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var coarse = window.matchMedia('(hover: none), (pointer: coarse)').matches;
  var $  = function (s, c) { return (c || doc).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); };

  /* ------------------------------------------------------------ Preloader */
  function preloader() {
    var end = function () {
      body.classList.add('is-loaded');
      window.setTimeout(function () {
        var p = $('.preloader');
        if (p && p.parentNode) { p.parentNode.removeChild(p); }
      }, 900);
    };
    if (doc.readyState === 'complete') { window.setTimeout(end, 260); }
    else { window.addEventListener('load', function () { window.setTimeout(end, 260); }); }
  }

  /* -------------------------------------------------------- Cursor propio */
  function cursor() {
    if (coarse || reduce || cfg.cursor === false) {
      doc.documentElement.classList.add('no-cursor-fx');
      return;
    }
    var dot  = $('.cursor-dot');
    var ring = $('.cursor-ring');
    var label = ring ? ring.querySelector('i') : null;
    if (!dot || !ring) { return; }

    var mx = window.innerWidth / 2, my = window.innerHeight / 2;
    var rx = mx, ry = my, raf = null;

    function loop() {
      rx += (mx - rx) * 0.16;
      ry += (my - ry) * 0.16;
      dot.style.transform  = 'translate3d(' + mx + 'px,' + my + 'px,0)';
      ring.style.transform = 'translate3d(' + rx + 'px,' + ry + 'px,0)' + (body.classList.contains('cursor-down') ? ' scale(.82)' : '');
      raf = window.requestAnimationFrame(loop);
    }

    doc.addEventListener('mousemove', function (ev) {
      mx = ev.clientX; my = ev.clientY;
      if (raf === null) { loop(); }
      dot.style.opacity = ring.style.opacity = '1';
    }, { passive: true });

    doc.addEventListener('mouseleave', function () { dot.style.opacity = ring.style.opacity = '0'; });
    doc.addEventListener('mousedown', function () { body.classList.add('cursor-down'); });
    doc.addEventListener('mouseup',   function () { body.classList.remove('cursor-down'); });

    var hoverSel = 'a,button,input,textarea,select,summary,[data-cursor],.card,.folio__item,.faq__q';
    doc.addEventListener('mouseover', function (ev) {
      var t = ev.target.closest ? ev.target.closest(hoverSel) : null;
      if (!t) { return; }
      body.classList.add('cursor-hover');
      var text = t.getAttribute('data-cursor');
      if (text && label) { label.textContent = text; body.classList.add('cursor-label'); }
    });
    doc.addEventListener('mouseout', function (ev) {
      var t = ev.target.closest ? ev.target.closest(hoverSel) : null;
      if (!t) { return; }
      body.classList.remove('cursor-hover', 'cursor-label');
    });
  }

  /* ------------------------------------------------------- Header dinámico */
  function header() {
    var el = $('.header');
    if (!el) { return; }
    var last = 0;
    var onScroll = function () {
      var y = window.pageYOffset || doc.documentElement.scrollTop;
      el.classList.toggle('is-stuck', y > 24);
      body.classList.toggle('is-scrolled', y > 420);
      if (!body.classList.contains('nav-open')) {
        el.classList.toggle('is-hidden', y > last && y > 320);
      }
      last = y;
      var bar = $('.scroll-progress');
      if (bar) {
        var h = doc.documentElement.scrollHeight - window.innerHeight;
        bar.style.transform = 'scaleX(' + (h > 0 ? Math.min(1, y / h) : 0) + ')';
      }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* --------------------------------------------------- Menú hamburguesa */
  function mobileNav() {
    var burger = $('.burger');
    var nav    = $('.mobile-nav');
    if (!burger || !nav) { return; }
    var toggle = function (open) {
      body.classList.toggle('nav-open', open);
      body.classList.toggle('is-locked', open);
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      nav.setAttribute('aria-hidden', open ? 'false' : 'true');
    };
    burger.addEventListener('click', function () { toggle(!body.classList.contains('nav-open')); });
    $$('a', nav).forEach(function (a) { a.addEventListener('click', function () { toggle(false); }); });
    doc.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && body.classList.contains('nav-open')) { toggle(false); }
    });
  }

  /* ------------------------------------------------ Revelado al hacer scroll */
  function reveals() {
    var items = $$('[data-reveal],[data-stagger],.split-wrap');
    if (!items.length) { return; }
    if (reduce || !('IntersectionObserver' in window)) {
      items.forEach(function (i) { i.classList.add('is-in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) { return; }
        en.target.classList.add('is-in');
        io.unobserve(en.target);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    items.forEach(function (i) { io.observe(i); });
  }

  /* --------------------------------------------------- Texto por palabras */
  function splitText() {
    $$('[data-split]').forEach(function (el) {
      if (el.dataset.splitDone) { return; }
      var words = el.textContent.trim().split(/\s+/);
      el.textContent = '';
      words.forEach(function (w, i) {
        var wrap = doc.createElement('span');
        wrap.className = 'split';
        wrap.style.setProperty('--w', String(i));
        var inner = doc.createElement('span');
        inner.textContent = w;
        wrap.appendChild(inner);
        el.appendChild(wrap);
        if (i < words.length - 1) { el.appendChild(doc.createTextNode(' ')); }
      });
      el.dataset.splitDone = '1';
    });
  }

  /* ------------------------------------------------------ Contadores */
  function counters() {
    var nums = $$('[data-count]');
    if (!nums.length) { return; }
    var animate = function (el) {
      var target = parseFloat(el.getAttribute('data-count'));
      if (isNaN(target)) { el.textContent = el.getAttribute('data-count'); return; }
      var dur = 1600, t0 = null;
      var dec = (String(target).split('.')[1] || '').length;
      var step = function (ts) {
        if (t0 === null) { t0 = ts; }
        var p = Math.min(1, (ts - t0) / dur);
        var eased = 1 - Math.pow(1 - p, 4);
        el.textContent = (target * eased).toFixed(dec);
        if (p < 1) { window.requestAnimationFrame(step); }
        else { el.textContent = target.toFixed(dec); }
      };
      window.requestAnimationFrame(step);
    };
    if (reduce || !('IntersectionObserver' in window)) {
      nums.forEach(function (n) { n.textContent = n.getAttribute('data-count'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) { return; }
        animate(en.target);
        io.unobserve(en.target);
      });
    }, { threshold: 0.55 });
    nums.forEach(function (n) { io.observe(n); });
  }

  /* --------------------------------------------------------- Parallax */
  function parallax() {
    var items = $$('[data-parallax]');
    if (!items.length || reduce || cfg.parallax === false) { return; }
    var ticking = false;
    var run = function () {
      var vh = window.innerHeight;
      items.forEach(function (el) {
        var r = el.getBoundingClientRect();
        if (r.bottom < -200 || r.top > vh + 200) { return; }
        var speed = parseFloat(el.getAttribute('data-parallax')) || 0.12;
        var off = (r.top + r.height / 2 - vh / 2) * speed * -1;
        el.style.transform = 'translate3d(0,' + off.toFixed(2) + 'px,0)';
      });
      ticking = false;
    };
    window.addEventListener('scroll', function () {
      if (!ticking) { ticking = true; window.requestAnimationFrame(run); }
    }, { passive: true });
    window.addEventListener('resize', run);
    run();
  }

  /* --------------------------------------------- Brillo que sigue al cursor */
  function cardGlow() {
    if (coarse) { return; }
    $$('.card').forEach(function (card) {
      card.addEventListener('mousemove', function (ev) {
        var r = card.getBoundingClientRect();
        card.style.setProperty('--mx', ((ev.clientX - r.left) / r.width * 100).toFixed(1) + '%');
        card.style.setProperty('--my', ((ev.clientY - r.top) / r.height * 100).toFixed(1) + '%');
      });
    });
  }

  /* ------------------------------------------------------ Botones magnéticos */
  function magnetic() {
    if (coarse || reduce) { return; }
    $$('[data-magnetic]').forEach(function (el) {
      var strength = parseFloat(el.getAttribute('data-magnetic')) || 0.28;
      el.addEventListener('mousemove', function (ev) {
        var r = el.getBoundingClientRect();
        var x = (ev.clientX - r.left - r.width / 2) * strength;
        var y = (ev.clientY - r.top - r.height / 2) * strength;
        el.style.transform = 'translate3d(' + x.toFixed(1) + 'px,' + (y - 3).toFixed(1) + 'px,0)';
      });
      el.addEventListener('mouseleave', function () { el.style.transform = ''; });
    });
  }

  /* ---------------------------------------------------------------- Slider */
  function slider() {
    var root = $('[data-slider]');
    if (!root) { return; }
    var slides = $$('.hero__media .hero__slide', root);
    var panels = $$('.hero__panel', root);
    if (slides.length === 0) { return; }
    var dots  = $$('.hero__dot', root);
    var cur   = $('[data-slider-current]', root);
    var prev  = $('[data-slider-prev]', root);
    var next  = $('[data-slider-next]', root);
    var interval = parseInt(root.getAttribute('data-interval') || '6500', 10);
    var auto = root.getAttribute('data-autoplay') === '1' && slides.length > 1 && !reduce;
    var index = 0, timer = null, paused = false;

    function activateContent(i) {
      panels.forEach(function (p, n) {
        p.classList.toggle('is-active', n === i);
        p.setAttribute('aria-hidden', n === i ? 'false' : 'true');
      });
      var content = panels[i] ? $('.hero__content', panels[i]) : null;
      if (!content) { return; }
      content.classList.remove('is-in');
      void content.offsetWidth;
      content.classList.add('is-in');
    }

    function go(i, dir) {
      index = (i + slides.length) % slides.length;
      slides.forEach(function (s, n) {
        s.classList.toggle('is-active', n === index);
        s.setAttribute('aria-hidden', n === index ? 'false' : 'true');
        var img = $('.hero__img', s);
        if (img && n === index) {
          img.style.clipPath = dir === -1 ? 'inset(0 100% 0 0)' : 'inset(0 0 0 100%)';
          void img.offsetWidth;
          img.style.clipPath = 'inset(0 0 0 0)';
        }
      });
      dots.forEach(function (d, n) {
        d.classList.toggle('is-active', n === index);
        d.setAttribute('aria-selected', n === index ? 'true' : 'false');
        var fill = d.querySelector('i');
        if (fill) { fill.style.animation = 'none'; void fill.offsetWidth; fill.style.animation = ''; }
      });
      if (cur) { cur.textContent = String(index + 1).padStart(2, '0'); }
      activateContent(index);
      restart();
    }

    function restart() {
      if (timer) { window.clearTimeout(timer); timer = null; }
      if (auto && !paused) { timer = window.setTimeout(function () { go(index + 1, 1); }, interval); }
    }

    if (prev) { prev.addEventListener('click', function () { go(index - 1, -1); }); }
    if (next) { next.addEventListener('click', function () { go(index + 1, 1); }); }
    dots.forEach(function (d, n) { d.addEventListener('click', function () { go(n, n > index ? 1 : -1); }); });

    root.addEventListener('mouseenter', function () {
      paused = true;
      dots.forEach(function (d) { d.classList.add('is-paused'); });
      if (timer) { window.clearTimeout(timer); timer = null; }
    });
    root.addEventListener('mouseleave', function () {
      paused = false;
      dots.forEach(function (d) { d.classList.remove('is-paused'); });
      restart();
    });

    doc.addEventListener('keydown', function (ev) {
      if (ev.key === 'ArrowLeft')  { go(index - 1, -1); }
      if (ev.key === 'ArrowRight') { go(index + 1, 1); }
    });

    // Arrastre / deslizamiento táctil
    var sx = 0, sy = 0, dragging = false;
    root.addEventListener('touchstart', function (ev) {
      sx = ev.touches[0].clientX; sy = ev.touches[0].clientY; dragging = true;
    }, { passive: true });
    root.addEventListener('touchend', function (ev) {
      if (!dragging) { return; }
      dragging = false;
      var dx = ev.changedTouches[0].clientX - sx;
      var dy = ev.changedTouches[0].clientY - sy;
      if (Math.abs(dx) > 55 && Math.abs(dx) > Math.abs(dy)) { go(index + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1); }
    }, { passive: true });

    doc.addEventListener('visibilitychange', function () {
      paused = doc.hidden;
      if (doc.hidden) { if (timer) { window.clearTimeout(timer); timer = null; } } else { restart(); }
    });

    slides[0].classList.add('is-active');
    activateContent(0);
    if (cur) { cur.textContent = '01'; }
    restart();
  }

  /* ------------------------------------------------------------------ FAQ */
  function faq() {
    $$('.faq__item').forEach(function (item) {
      var btn = $('.faq__q', item);
      if (!btn) { return; }
      btn.addEventListener('click', function () {
        var open = item.classList.contains('is-open');
        var group = item.parentNode;
        if (group && group.getAttribute('data-accordion') === 'single') {
          $$('.faq__item.is-open', group).forEach(function (o) {
            o.classList.remove('is-open');
            var b = $('.faq__q', o);
            if (b) { b.setAttribute('aria-expanded', 'false'); }
          });
        }
        item.classList.toggle('is-open', !open);
        btn.setAttribute('aria-expanded', !open ? 'true' : 'false');
      });
    });
  }

  /* ------------------------------------------------- Línea del proceso */
  function processLine() {
    var wrap = $('[data-process]');
    if (!wrap) { return; }
    var line = $('.process__line', wrap);
    if (!line || !('IntersectionObserver' in window)) { return; }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) { return; }
        if (window.innerWidth >= 800) { line.style.width = '100%'; }
        else { line.style.height = '100%'; }
        io.unobserve(en.target);
      });
    }, { threshold: 0.25 });
    io.observe(wrap);
  }

  /* ------------------------------------------------- Selector de temas */
  function themer() {
    var root = $('.themer');
    if (!root) { return; }
    var btn = $('[data-themer-toggle]', root);
    if (btn) {
      btn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        root.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', root.classList.contains('is-open') ? 'true' : 'false');
      });
    }
    doc.addEventListener('click', function (ev) {
      if (!root.contains(ev.target)) { root.classList.remove('is-open'); }
    });
    $$('[data-theme-key]', root).forEach(function (opt) {
      opt.addEventListener('click', function () {
        var key = opt.getAttribute('data-theme-key');
        try { window.localStorage.setItem('servicom_theme', key); } catch (e) {}
        var u = new URL(window.location.href);
        u.searchParams.set('preview_theme', key);
        window.location.href = u.toString();
      });
    });
  }

  /* ----------------------------------------------- Desplazamiento suave */
  function anchors() {
    doc.addEventListener('click', function (ev) {
      var a = ev.target.closest ? ev.target.closest('a[href^="#"]') : null;
      if (!a) { return; }
      var id = a.getAttribute('href');
      if (!id || id === '#' || id.length < 2) { return; }
      var target = doc.getElementById(id.slice(1));
      if (!target) { return; }
      ev.preventDefault();
      var top = target.getBoundingClientRect().top + window.pageYOffset - 90;
      window.scrollTo({ top: top, behavior: reduce ? 'auto' : 'smooth' });
    });
  }

  /* ------------------------------------------------- Validación de formularios */
  function forms() {
    $$('form[data-validate]').forEach(function (form) {
      form.addEventListener('submit', function (ev) {
        var ok = true;
        $$('[required]', form).forEach(function (input) {
          var field = input.closest('.field');
          var valid = input.type === 'checkbox' ? input.checked : input.value.trim() !== '';
          if (valid && input.type === 'email') {
            valid = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(input.value.trim());
          }
          if (field) { field.classList.toggle('field--error', !valid); }
          if (!valid && ok) { input.focus(); }
          if (!valid) { ok = false; }
        });
        if (!ok) { ev.preventDefault(); return; }
        var btn = form.querySelector('[type="submit"]');
        if (btn) { btn.disabled = true; btn.style.opacity = '.7'; }
      });
      $$('input,textarea,select', form).forEach(function (input) {
        input.addEventListener('input', function () {
          var f = input.closest('.field');
          if (f) { f.classList.remove('field--error'); }
        });
      });
    });
  }

  /* ------------------------------------------------------- Año automático */
  function year() {
    $$('[data-year]').forEach(function (el) { el.textContent = String(new Date().getFullYear()); });
  }

  /* -------------------------------------------------------------- Arranque */
  function init() {
    preloader(); header(); mobileNav(); splitText(); reveals(); counters();
    parallax(); cardGlow(); magnetic(); slider(); faq(); processLine();
    themer(); anchors(); forms(); year(); cursor();
  }

  if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', init); }
  else { init(); }
})();
