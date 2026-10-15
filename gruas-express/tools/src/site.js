/* Grúas Express Towing — JS propio, sin librerías. Compatible con CSP estricta (sin inline). */
(function () {
  'use strict';
  var d = document, h = d.documentElement;
  function $(s, c) { return (c || d).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); }
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- revelado al scroll ---- */
  var items = $$('.reveal');
  if ('IntersectionObserver' in window && !reduce) {
    h.classList.add('rv');
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
      });
    }, { rootMargin: '0px 0px -6% 0px', threshold: 0.08 });
    items.forEach(function (el) { io.observe(el); });
  }

  /* ---- cabecera y botón subir ---- */
  var hdr = $('#hdr'), top = $('#totop');
  var ticking = false;
  var heroImg = $('.hero-img');
  if (heroImg && !reduce) { heroImg.style.transform = 'scale(1.06)'; }
  function onScroll() {
    var y = window.pageYOffset || 0;
    if (hdr) { hdr.classList.toggle('stuck', y > 8); }
    if (top) { top.hidden = y < 500; }
    if (heroImg && !reduce && y < 900) {
      heroImg.style.transform = 'translate3d(0,' + Math.round(y * 0.05) + 'px,0) scale(1.06)';
    }
    ticking = false;
  }
  window.addEventListener('scroll', function () {
    if (!ticking) { ticking = true; window.requestAnimationFrame(onScroll); }
  }, { passive: true });
  onScroll();
  if (top) { top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); }); }

  /* ---- menú móvil ---- */
  var burger = $('#burger'), mnav = $('#mnav'), ov = $('#mnav-ov'), mx = $('#mnav-x');
  function openMenu() {
    mnav.classList.add('open'); mnav.setAttribute('aria-hidden', 'false');
    ov.hidden = false; window.requestAnimationFrame(function () { ov.classList.add('on'); });
    burger.setAttribute('aria-expanded', 'true'); d.body.classList.add('noscroll');
    var a = $('a', mnav); if (a) { a.focus(); }
  }
  function closeMenu() {
    mnav.classList.remove('open'); mnav.setAttribute('aria-hidden', 'true');
    ov.classList.remove('on'); window.setTimeout(function () { ov.hidden = true; }, 300);
    burger.setAttribute('aria-expanded', 'false'); d.body.classList.remove('noscroll');
  }
  if (burger && mnav && ov) {
    burger.addEventListener('click', function () { (mnav.classList.contains('open') ? closeMenu : openMenu)(); });
    if (mx) { mx.addEventListener('click', function () { closeMenu(); burger.focus(); }); }
    ov.addEventListener('click', closeMenu);
    $$('a', mnav).forEach(function (a) { a.addEventListener('click', closeMenu); });
    window.addEventListener('resize', function () { if (window.innerWidth >= 960 && mnav.classList.contains('open')) { closeMenu(); } });
  }

  /* ---- contador del precio (Q250) ---- */
  var price = $('.price-t');
  if (price && !reduce && 'IntersectionObserver' in window) {
    var w = d.createTreeWalker(price, NodeFilter.SHOW_TEXT, null), n, list = [];
    while ((n = w.nextNode())) { if (/Q\d/.test(n.nodeValue)) { list.push(n); } }
    list.forEach(function (node) {
      var m = node.nodeValue.match(/^([\s\S]*?)Q(\d[\d,]*)([\s\S]*)$/); if (!m) { return; }
      var span = d.createElement('span'); span.className = 'cnt';
      var target = parseInt(m[2].replace(/,/g, ''), 10); span.textContent = 'Q' + m[2];
      var frag = d.createDocumentFragment();
      frag.appendChild(d.createTextNode(m[1])); frag.appendChild(span); frag.appendChild(d.createTextNode(m[3]));
      node.parentNode.replaceChild(frag, node);
      var seen = new IntersectionObserver(function (es) {
        if (!es[0].isIntersecting) { return; }
        seen.disconnect();
        var t0 = null;
        (function step(t) {
          if (t0 === null) { t0 = t; }
          var p = Math.min(1, (t - t0) / 1200), v = Math.round(target * (1 - Math.pow(1 - p, 3)));
          span.textContent = 'Q' + v;
          if (p < 1) { window.requestAnimationFrame(step); } else { span.textContent = 'Q' + m[2]; }
        })(t0);
        window.requestAnimationFrame(function (t) { t0 = t; });
      }, { threshold: 0.6 });
      seen.observe(span);
    });
  }

  /* ---- lightbox de galería ---- */
  var links = $$('a[data-lb]');
  if (links.length) {
    var lb = d.createElement('div'); lb.className = 'lb'; lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-modal', 'true'); lb.setAttribute('aria-label', 'Galería de imágenes');
    var img = d.createElement('img'); img.className = 'lb-img'; img.alt = '';
    function btn(cls, label, svg) {
      var b = d.createElement('button'); b.type = 'button'; b.className = 'lb-b ' + cls; b.setAttribute('aria-label', label);
      b.innerHTML = svg; return b;
    }
    var sv = function (p) { return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>'; };
    var bx = btn('lb-x', 'Cerrar', sv('<path d="M18 6 6 18M6 6l12 12"/>'));
    var bp = btn('lb-p', 'Anterior', sv('<path d="m15 18-6-6 6-6"/>'));
    var bn = btn('lb-n', 'Siguiente', sv('<path d="m9 18 6-6-6-6"/>'));
    var cnt = d.createElement('div'); cnt.className = 'lb-c';
    [img, bx, bp, bn, cnt].forEach(function (x) { lb.appendChild(x); });
    d.body.appendChild(lb);
    var idx = 0, last = null;
    function show(i) {
      idx = (i + links.length) % links.length;
      var a = links[idx], im = $('img', a);
      img.style.opacity = '0';
      img.onload = function () { img.style.opacity = '1'; };
      img.src = a.getAttribute('href'); img.alt = im ? im.alt : '';
      cnt.textContent = (idx + 1) + ' / ' + links.length;
      var nx = new Image(); nx.src = links[(idx + 1) % links.length].getAttribute('href');
    }
    function open(i) { last = d.activeElement; lb.classList.add('on'); d.body.classList.add('noscroll'); show(i); bx.focus(); }
    function close() { lb.classList.remove('on'); d.body.classList.remove('noscroll'); if (last) { last.focus(); } }
    links.forEach(function (a, i) { a.addEventListener('click', function (e) { e.preventDefault(); open(i); }); });
    bx.addEventListener('click', close);
    bp.addEventListener('click', function () { show(idx - 1); });
    bn.addEventListener('click', function () { show(idx + 1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) { close(); } });
    d.addEventListener('keydown', function (e) {
      if (!lb.classList.contains('on')) { return; }
      if (e.key === 'Escape') { close(); } else if (e.key === 'ArrowLeft') { show(idx - 1); } else if (e.key === 'ArrowRight') { show(idx + 1); }
    });
    var sx = 0;
    lb.addEventListener('touchstart', function (e) { sx = e.changedTouches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - sx;
      if (Math.abs(dx) > 50) { show(idx + (dx < 0 ? 1 : -1)); }
    }, { passive: true });
  }

  /* ---- formulario: guarda copia y abre WhatsApp con los datos ---- */
  var form = $('#cf');
  if (form) {
    var status = $('#cf-status'), tokenIn = form.elements.token, tokenAt = 0, wa = form.getAttribute('data-wa') || '';
    function getToken() {
      return fetch('/enviar.php?t=1', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (j && j.t) { tokenIn.value = j.t; tokenAt = Date.now(); } })
        .catch(function () {});
    }
    getToken();
    function say(kind, text, href) {
      status.className = 'cf-msgbox ' + kind; status.textContent = text;
      if (href) {
        status.appendChild(d.createTextNode(' '));
        var a = d.createElement('a'); a.href = href; a.textContent = 'Abrir WhatsApp'; a.rel = 'noopener noreferrer'; status.appendChild(a);
      }
    }
    function fieldErr(name, msg) {
      var el = form.elements[name]; if (!el) { return; }
      var f = el.closest('.fld'); if (!f) { return; }
      var em = $('.em', f);
      if (msg) {
        f.classList.add('bad'); el.setAttribute('aria-invalid', 'true');
        if (!em) { em = d.createElement('span'); em.className = 'em'; f.appendChild(em); }
        em.textContent = msg;
      } else {
        f.classList.remove('bad'); el.removeAttribute('aria-invalid'); if (em) { em.textContent = ''; }
      }
    }
    function val() {
      var v = {}, ok = true, first = null;
      ['name', 'phone', 'email', 'service', 'place', 'message'].forEach(function (k) { v[k] = (form.elements[k].value || '').replace(/\s+/g, ' ').trim(); });
      v.message = (form.elements.message.value || '').replace(/\r/g, '').trim();
      function bad(k, m) { fieldErr(k, m); ok = false; if (!first) { first = k; } }
      ['name', 'phone', 'email', 'service', 'place', 'message'].forEach(function (k) { fieldErr(k, ''); });
      if (v.name.length < 2) { bad('name', 'Escriba su nombre.'); }
      if (!/^[0-9+()\s.\-]{6,25}$/.test(v.phone) || (v.phone.replace(/\D/g, '').length < 6)) { bad('phone', 'Escriba un teléfono válido.'); }
      if (v.email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.email)) { bad('email', 'Correo electrónico no válido.'); }
      if (!v.service) { bad('service', 'Seleccione el tipo de servicio.'); }
      if (v.message.length < 5) { bad('message', 'Escriba su mensaje.'); }
      if (!ok && first) { form.elements[first].focus(); }
      return ok ? v : null;
    }
    form.addEventListener('input', function (e) { if (e.target && e.target.name) { fieldErr(e.target.name, ''); } });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (form.classList.contains('busy')) { return; }
      var v = val(); if (!v) { say('bad', 'Revise los campos marcados.'); return; }
      var lines = [form.getAttribute('data-intro') || '', '',
        '*Nombre:* ' + v.name, '*Teléfono:* ' + v.phone, '*Tipo de servicio:* ' + v.service];
      if (v.place) { lines.push('*Ubicación / destino:* ' + v.place); }
      if (v.email) { lines.push('*Correo:* ' + v.email); }
      lines.push('*Mensaje:* ' + v.message);
      var url = 'https://api.whatsapp.com/send?phone=' + encodeURIComponent(wa) + '&text=' + encodeURIComponent(lines.join('\n').replace(/^\n/, ''));
      form.classList.add('busy'); say('ok', 'Abriendo WhatsApp con sus datos…');
      var save = Promise.resolve();
      if (tokenIn.value) {
        var fd = new FormData(form); fd.set('wa', '1');
        save = fetch('/enviar.php', { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true, headers: { 'Accept': 'application/json' } }).catch(function () {});
      }
      var timeout = new Promise(function (res) { window.setTimeout(res, 2200); });
      Promise.race([save, timeout]).then(function () {
        window.location.href = url;
        window.setTimeout(function () {
          form.classList.remove('busy'); say('ok', 'Si WhatsApp no se abrió automáticamente,', url);
          form.reset(); tokenIn.value = ''; getToken();
        }, 1800);
      });
    });
  }
})();
