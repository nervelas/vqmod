/* Te Lo Llevo — comportamiento del sitio público (sin dependencias) */
(function () {
  'use strict';
  var d = document;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };

  var DATA = {};
  try { DATA = JSON.parse(($('#tll-data') || {}).textContent || '{}'); } catch (e) { DATA = {}; }

  /* ---------- Encabezado: sombra al hacer scroll ---------- */
  var header = $('.site-header');
  function onScroll() { if (header) header.classList.toggle('scrolled', window.scrollY > 12); }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* ---------- Menú móvil ---------- */
  var burger = $('#burger'), nav = $('#nav');
  function setNav(open) {
    d.body.classList.toggle('nav-open', open);
    if (burger) { burger.setAttribute('aria-expanded', open ? 'true' : 'false'); burger.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú'); }
  }
  if (burger) {
    burger.addEventListener('click', function () { setNav(!d.body.classList.contains('nav-open')); });
    $$('a', nav).forEach(function (a) { a.addEventListener('click', function () { setNav(false); }); });
    d.addEventListener('keydown', function (e) { if (e.key === 'Escape') setNav(false); });
    d.addEventListener('click', function (e) { if (d.body.classList.contains('nav-open') && !nav.contains(e.target) && !burger.contains(e.target)) setNav(false); });
  }

  /* ---------- Aparición al hacer scroll ---------- */
  var revealEls = $$('.reveal');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add('in'); });
  }

  /* ---------- Navegación activa (scrollspy) ---------- */
  var links = $$('.nav ul a[data-nav]');
  var spyTargets = [];
  links.forEach(function (a) {
    var id = a.getAttribute('data-nav');
    var t = id && /^[A-Za-z0-9_-]+$/.test(id) ? d.getElementById(id) : null;
    if (t) spyTargets.push({ a: a, t: t });
  });
  if (spyTargets.length && 'IntersectionObserver' in window) {
    var spy = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        spyTargets.forEach(function (x) { x.a.classList.toggle('active', x.t === en.target); });
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    spyTargets.forEach(function (x) { spy.observe(x.t); });
  }

  /* ---------- Contadores ---------- */
  function fmtNum(n) { try { return n.toLocaleString('es-GT'); } catch (e) { return String(n); } }
  var counters = $$('[data-count]');
  function runCounter(el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    if (reduce) { el.textContent = fmtNum(target); return; }
    var dur = 1500, t0 = null;
    function step(ts) {
      if (!t0) t0 = ts;
      var p = Math.min(1, (ts - t0) / dur), eased = 1 - Math.pow(1 - p, 3);
      el.textContent = fmtNum(Math.round(target * eased));
      if (p < 1) requestAnimationFrame(step); else el.textContent = fmtNum(target);
    }
    el.textContent = '0';
    requestAnimationFrame(step);
  }
  if ('IntersectionObserver' in window) {
    var cio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { runCounter(en.target); cio.unobserve(en.target); } });
    }, { threshold: 0.6 });
    counters.forEach(function (el) { cio.observe(el); });
  }

  /* ---------- Efecto sutil en la imagen principal ---------- */
  var stage = $('.stage'), stageImg = $('.stage-img');
  if (stage && stageImg && !reduce && window.matchMedia('(hover:hover)').matches) {
    stage.addEventListener('pointermove', function (e) {
      var r = stage.getBoundingClientRect();
      var x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
      stageImg.style.transform = 'translate(' + (x * -10).toFixed(1) + 'px,' + (y * -6).toFixed(1) + 'px)';
    });
    stage.addEventListener('pointerleave', function () { stageImg.style.transform = ''; });
  }

  /* ---------- Cotizador ---------- */
  var calc = $('#calc');
  if (calc && DATA.distances) {
    var cur = DATA.currency || 'Q';
    var totalEl = $('#calcTotal'), linesEl = $('#calcLines'), btnEl = $('#calcBtn');
    var shown = 0, anim = null;
    var money = function (n) { return (Math.round(n * 100) % 100 === 0) ? fmtNum(Math.round(n)) : n.toFixed(2); };
    var pick = function (name, arr) {
      var r = calc.querySelector('input[name="' + name + '"]:checked');
      return arr[r ? parseInt(r.value, 10) : 0] || arr[0];
    };
    var animateTo = function (to) {
      if (anim) cancelAnimationFrame(anim);
      if (reduce) { totalEl.textContent = money(to); shown = to; return; }
      var from = shown, t0 = null;
      (function step(ts) {
        if (!t0) t0 = ts;
        var p = Math.min(1, (ts - t0) / 450), v = from + (to - from) * (1 - Math.pow(1 - p, 3));
        totalEl.textContent = money(v); shown = v;
        if (p < 1) anim = requestAnimationFrame(step); else { shown = to; totalEl.textContent = money(to); }
      })(performance.now());
    };
    var update = function () {
      var dist = pick('calc_d', DATA.distances), size = pick('calc_s', DATA.sizes), sp = pick('calc_u', DATA.speeds);
      var total = (+dist.price || 0) + (+size.extra || 0) + (+sp.extra || 0);
      animateTo(total);
      var rows = [
        ['Distancia: ' + dist.label, cur + money(+dist.price || 0)],
        ['Paquete: ' + size.label, (+size.extra > 0) ? '+' + cur + money(+size.extra) : 'Incluido'],
        ['Servicio: ' + sp.label, (+sp.extra > 0) ? '+' + cur + money(+sp.extra) : 'Incluido']
      ];
      linesEl.innerHTML = '';
      rows.forEach(function (r) {
        var li = d.createElement('li'), a = d.createElement('span'), b = d.createElement('b');
        a.textContent = r[0]; b.textContent = r[1];
        li.appendChild(a); li.appendChild(b); linesEl.appendChild(li);
      });
      var msg = 'Hola, quiero solicitar un envío.\n• Distancia: ' + dist.label + '\n• Paquete: ' + size.label + '\n• Servicio: ' + sp.label + '\n• Precio estimado: ' + cur + money(total);
      btnEl.href = 'https://wa.me/' + (DATA.wa || '') + '?text=' + encodeURIComponent(msg);
    };
    calc.addEventListener('change', update);
    update();
  }

  /* ---------- Preguntas frecuentes ---------- */
  $$('.faq-q').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var open = btn.getAttribute('aria-expanded') === 'true';
      $$('.faq-q').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); b.closest('.faq-item').classList.remove('open'); });
      if (!open) { btn.setAttribute('aria-expanded', 'true'); btn.closest('.faq-item').classList.add('open'); }
    });
  });

  /* ---------- Formulario ---------- */
  var form = $('#orderForm');
  if (form) {
    var msgBox = $('#formMsg'), submit = $('#fSubmit'), tokenEl = $('#fToken');
    var interest = $('#fInteres'), chip = $('#interestChip'), chipText = $('#interestText');

    // Token fresco (permite páginas en caché y cumple el tiempo mínimo anti-spam)
    function refreshToken() {
      if (!DATA.tokenUrl) return Promise.resolve();
      return fetch(DATA.tokenUrl, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); })
        .then(function (j) { if (j && j.token) tokenEl.value = j.token; }).catch(function () {});
    }
    refreshToken();

    // Servicio de interés desde las tarjetas
    function setInterest(v) {
      interest.value = v || '';
      chip.hidden = !v; chipText.textContent = v || '';
    }
    $$('[data-interest]').forEach(function (a) {
      a.addEventListener('click', function () { setInterest(a.getAttribute('data-interest')); });
    });
    var clr = $('#interestClear'); if (clr) clr.addEventListener('click', function () { setInterest(''); });

    // Programar fecha
    var when = $('#f_when'), sched = $('#schedBox');
    function toggleSched() { sched.hidden = when.value.indexOf('Programar') !== 0; }
    when.addEventListener('change', toggleSched); toggleSched();

    // Teléfono: solo caracteres válidos
    var tel = $('#f_tel');
    tel.addEventListener('input', function () { tel.value = tel.value.replace(/[^0-9+()\-.\s]/g, ''); });

    function showMsg(ok, text) {
      msgBox.hidden = false; msgBox.className = 'form-msg ' + (ok ? 'ok' : 'bad'); msgBox.textContent = text;
      msgBox.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
    }
    function validate() {
      var ok = true, first = null;
      $$('[required]', form).forEach(function (el) {
        var bad = el.type === 'checkbox' ? !el.checked : !el.value.trim();
        if (el.type === 'tel' && !bad) bad = el.value.replace(/\D/g, '').length < 7;
        el.classList.toggle('invalid', bad);
        if (bad) { ok = false; if (!first) first = el; }
      });
      var em = $('#f_email');
      if (em.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em.value)) { em.classList.add('invalid'); ok = false; first = first || em; } else { em.classList.remove('invalid'); }
      if (first) { first.focus({ preventScroll: false }); }
      return ok;
    }
    form.addEventListener('input', function (e) { if (e.target.classList) e.target.classList.remove('invalid'); });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      msgBox.hidden = true;
      if (!validate()) { showMsg(false, 'Por favor completa los campos marcados en rojo.'); return; }
      var label = submit.querySelector('span'), old = label.textContent;
      submit.disabled = true; label.textContent = 'Enviando…';
      fetch(form.action, {
        method: 'POST', body: new FormData(form), credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' }
      }).then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Respuesta inesperada del servidor.' }; }); })
        .then(function (j) {
          if (j.token) tokenEl.value = j.token;
          if (j.ok) { form.reset(); setInterest(''); toggleSched(); showMsg(true, j.message); }
          else { showMsg(false, j.message || 'No pudimos enviar tu solicitud.'); refreshToken(); }
        })
        .catch(function () { showMsg(false, 'No hay conexión con el servidor. Inténtalo de nuevo o escríbenos por WhatsApp.'); })
        .then(function () { submit.disabled = false; label.textContent = old; });
    });

    // Enviar el pedido por WhatsApp
    var wbtn = $('#fWhats');
    if (wbtn) wbtn.addEventListener('click', function () {
      var v = function (n) { var el = form.elements[n]; return el ? el.value.trim() : ''; };
      var L = ['Hola ' + (DATA.brand || '') + ', quiero solicitar un servicio:'];
      var add = function (k, val) { if (val) L.push('• ' + k + ': ' + val); };
      add('Nombre', v('nombre')); add('Teléfono', v('telefono')); add('Servicio', v('servicio') || v('interes'));
      add('Recogida', v('recogida')); add('Entrega', v('entrega')); add('Envío', v('descripcion')); add('Tamaño', v('tamano'));
      var cuando = v('cuando'); if (v('fecha')) cuando += ' (' + v('fecha') + (v('hora') ? ' ' + v('hora') : '') + ')';
      add('Cuándo', cuando); add('Comentarios', v('comentarios'));
      window.open('https://wa.me/' + (DATA.wa || '') + '?text=' + encodeURIComponent(L.join('\n')), '_blank', 'noopener');
    });
  }
})();
