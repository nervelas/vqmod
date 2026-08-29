/* ============================================================
   MenúGold · movimiento
   Vanilla ES2020. Todo se apaga con prefers-reduced-motion.
   ============================================================ */
(function () {
  'use strict';

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var coarse  = window.matchMedia('(hover: none), (pointer: coarse)').matches;
  var rafId   = null;
  var scrollHandlers = [];

  /* ---------- Utilidades ---------- */
  function onScroll(fn) { scrollHandlers.push(fn); }

  function tick() {
    var y = window.scrollY || window.pageYOffset;
    var vh = window.innerHeight;
    for (var i = 0; i < scrollHandlers.length; i++) {
      try { scrollHandlers[i](y, vh); } catch (e) {}
    }
    rafId = null;
  }
  window.addEventListener('scroll', function () {
    if (rafId === null) { rafId = requestAnimationFrame(tick); }
  }, { passive: true });
  window.addEventListener('resize', function () {
    if (rafId === null) { rafId = requestAnimationFrame(tick); }
  }, { passive: true });

  /* ---------- Aparición escalonada ---------- */
  function initReveal() {
    var items = document.querySelectorAll('.reveal, .reveal-mask, [data-reveal]');
    if (!items.length) { return; }
    if (reduced || !('IntersectionObserver' in window)) {
      items.forEach(function (el) { el.classList.add('is-in'); });
      return;
    }
    var pending = [];

    function mostrar(el) {
      if (el.classList.contains('is-in')) { return; }
      if (el.hasAttribute('data-stagger')) {
        Array.prototype.forEach.call(el.children, function (child, i) {
          child.style.setProperty('--d', (i * 90) + 'ms');
          child.classList.add('is-in');
        });
      }
      el.classList.add('is-in');
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) { return; }
        mostrar(entry.target);
        io.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.01 });

    items.forEach(function (el, i) {
      if (!el.style.getPropertyValue('--d') && el.parentElement && el.parentElement.hasAttribute('data-stagger-children')) {
        el.style.setProperty('--d', (i % 6) * 80 + 'ms');
      }
      pending.push(el);
      io.observe(el);
    });

    // Red de seguridad: si por lo que sea el observador no dispara (scroll muy
    // rápido, posición restaurada, pestaña en segundo plano), nada se queda
    // invisible. Se revisa en cada scroll y se descartan los ya mostrados.
    function barrer(y, vh) {
      if (!pending.length) { return; }
      var restantes = [];
      for (var i = 0; i < pending.length; i++) {
        var el = pending[i];
        if (el.classList.contains('is-in')) { continue; }
        var r = el.getBoundingClientRect();
        if (r.top < vh * 0.94 && r.bottom > -vh) {
          mostrar(el);
          io.unobserve(el);
        } else {
          restantes.push(el);
        }
      }
      pending = restantes;
    }
    onScroll(barrer);
    // Y una última pasada por si la página carga ya desplazada.
    setTimeout(function () { barrer(window.scrollY || 0, window.innerHeight); }, 400);
  }

  /* ---------- Título letra por letra ---------- */
  function splitText() {
    var nodes = document.querySelectorAll('[data-split]');
    if (!nodes.length) { return; }
    nodes.forEach(function (node) {
      if (node.dataset.splitDone) { return; }
      node.dataset.splitDone = '1';
      var html = '';
      var words = node.textContent.trim().split(/\s+/);
      var idx = 0;
      words.forEach(function (word, w) {
        html += '<span class="split-word" style="display:inline-block;white-space:nowrap">';
        Array.from(word).forEach(function (ch) {
          html += '<span class="split-char" style="transition-delay:' + (idx * 26) + 'ms">' + escapeHtml(ch) + '</span>';
          idx++;
        });
        html += '</span>';
        if (w < words.length - 1) { html += '<span class="split-char" style="transition-delay:' + (idx * 26) + 'ms">&nbsp;</span>'; idx++; }
      });
      node.innerHTML = '<span class="split-line">' + html + '</span>';
      if (reduced) { node.classList.add('is-in'); }
    });
  }

  function escapeHtml(s) {
    return s.replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------- Parallax ---------- */
  function initParallax() {
    if (reduced) { return; }
    var nodes = Array.prototype.slice.call(document.querySelectorAll('[data-parallax]'));
    if (!nodes.length) { return; }
    nodes.forEach(function (n) { n.classList.add('parallax'); });

    onScroll(function (y, vh) {
      nodes.forEach(function (el) {
        var rect = el.getBoundingClientRect();
        if (rect.bottom < -200 || rect.top > vh + 200) { return; }
        var speed = parseFloat(el.dataset.parallax) || 0.15;
        var center = rect.top + rect.height / 2 - vh / 2;
        var shift = -center * speed;
        el.style.transform = 'translate3d(0,' + shift.toFixed(2) + 'px,0)';
      });
    });
  }

  /* ---------- Números que cuentan ---------- */
  function initCounters() {
    var nodes = document.querySelectorAll('[data-count]');
    if (!nodes.length) { return; }
    if (reduced || !('IntersectionObserver' in window)) {
      nodes.forEach(function (el) { el.textContent = formatNumber(parseFloat(el.dataset.count), el); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) { return; }
        countUp(entry.target);
        io.unobserve(entry.target);
      });
    }, { threshold: 0.5 });
    nodes.forEach(function (el) { el.textContent = '0'; io.observe(el); });
  }

  function formatNumber(n, el) {
    var suffix = el.dataset.suffix || '';
    var prefix = el.dataset.prefix || '';
    return prefix + Math.round(n).toLocaleString('es-GT') + suffix;
  }

  function countUp(el) {
    var target = parseFloat(el.dataset.count) || 0;
    var dur = parseInt(el.dataset.duration, 10) || 1800;
    var start = performance.now();
    function frame(now) {
      var p = Math.min(1, (now - start) / dur);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = formatNumber(target * eased, el);
      if (p < 1) { requestAnimationFrame(frame); }
    }
    requestAnimationFrame(frame);
  }

  /* ---------- Marquesina infinita ---------- */
  function initMarquee() {
    document.querySelectorAll('.marquee').forEach(function (m) {
      var track = m.querySelector('.marquee-track');
      if (!track || track.dataset.cloned) { return; }
      track.dataset.cloned = '1';
      track.innerHTML += track.innerHTML;
    });
  }

  /* ---------- Cursor dorado ---------- */
  function initCursor() {
    if (coarse || reduced) { return; }
    var dot = document.createElement('div');
    dot.className = 'cursor';
    dot.setAttribute('aria-hidden', 'true');
    document.body.appendChild(dot);

    var tx = -100, ty = -100, cx = -100, cy = -100;
    document.addEventListener('mousemove', function (e) { tx = e.clientX; ty = e.clientY; }, { passive: true });
    (function loop() {
      cx += (tx - cx) * 0.18;
      cy += (ty - cy) * 0.18;
      dot.style.transform = 'translate3d(' + (cx - 5) + 'px,' + (cy - 5) + 'px,0)';
      requestAnimationFrame(loop);
    })();

    document.addEventListener('mouseover', function (e) {
      var t = e.target.closest('a, button, .dish, .g-item, [role="button"], input, select, textarea, label');
      dot.classList.toggle('is-big', !!t);
    });
  }

  /* ---------- Cortina entre páginas ---------- */
  function initCurtain() {
    if (reduced) { return; }
    var curtain = document.createElement('div');
    curtain.className = 'curtain';
    curtain.setAttribute('aria-hidden', 'true');
    document.body.appendChild(curtain);

    requestAnimationFrame(function () { curtain.classList.add('is-in'); });

    document.addEventListener('click', function (e) {
      var a = e.target.closest('a');
      if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) { return; }
      var href = a.getAttribute('href');
      if (!href || href.charAt(0) === '#' || a.target === '_blank' || a.hasAttribute('download')
          || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0 || a.dataset.noCurtain !== undefined) { return; }
      if (a.hostname && a.hostname !== window.location.hostname) { return; }
      e.preventDefault();
      curtain.classList.remove('is-in');
      curtain.classList.add('is-out');
      setTimeout(function () { window.location.href = a.href; }, 400);
    });

    window.addEventListener('pageshow', function (ev) {
      if (ev.persisted) { curtain.classList.remove('is-out'); curtain.classList.add('is-in'); }
    });
  }

  /* ---------- Navegación pegada ---------- */
  function initNav() {
    var nav = document.querySelector('.nav');
    if (!nav) { return; }
    onScroll(function (y) { nav.classList.toggle('is-stuck', y > 40); });
    nav.classList.toggle('is-stuck', (window.scrollY || 0) > 40);
  }

  /* ---------- Teléfono con inclinación al hacer scroll ---------- */
  function initPhoneTilt() {
    var phone = document.querySelector('[data-tilt]');
    if (!phone || reduced) { return; }
    onScroll(function (y, vh) {
      var rect = phone.getBoundingClientRect();
      if (rect.bottom < 0 || rect.top > vh) { return; }
      var p = (rect.top + rect.height / 2 - vh / 2) / vh;  // -0.5 … 0.5
      phone.style.setProperty('--ry', (-16 + p * 14).toFixed(2) + 'deg');
      phone.style.setProperty('--rx', (6 - p * 10).toFixed(2) + 'deg');
    });
  }

  /* ---------- Línea dorada de los pasos ---------- */
  function initSteps() {
    var wrap = document.querySelector('[data-steps]');
    if (!wrap) { return; }
    var line = wrap.querySelector('.steps-line');
    var steps = wrap.querySelectorAll('.step');
    onScroll(function (y, vh) {
      var rect = wrap.getBoundingClientRect();
      var p = (vh * 0.78 - rect.top) / Math.max(1, rect.height * 0.72);
      p = Math.max(0, Math.min(1, p));
      if (line) { line.style.setProperty('--p', p.toFixed(3)); }
      steps.forEach(function (s, i) {
        s.classList.toggle('is-active', p >= (i / steps.length) + 0.04);
      });
    });
  }

  /* ---------- Anclas internas ---------- */
  function initAnchors() {
    document.addEventListener('click', function (e) {
      var a = e.target.closest('a[href^="#"]');
      if (!a) { return; }
      var id = a.getAttribute('href').slice(1);
      if (!id) { return; }
      var target = document.getElementById(id);
      if (!target) { return; }
      e.preventDefault();
      target.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
      history.replaceState(null, '', '#' + id);
    });
  }

  /* ---------- Arranque ---------- */
  function boot() {
    document.documentElement.classList.add('js');
    splitText();
    initReveal();
    initParallax();
    initCounters();
    initMarquee();
    initNav();
    initPhoneTilt();
    initSteps();
    initAnchors();
    if (document.body.dataset.cursor !== 'off') { initCursor(); }
    if (document.body.dataset.curtain !== 'off') { initCurtain(); }
    tick();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.MGMotion = { onScroll: onScroll, reduced: reduced, refresh: function () { splitText(); initReveal(); tick(); } };
})();
