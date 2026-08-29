/* CotizaPro B2B — movimiento de precisión. JS vanilla ES2020, sin dependencias. */
(function () {
  'use strict';

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var cfg = window.CP || {};
  var $  = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ------------------------------------------------------ utilidades red */
  function post(url, data) {
    var body = new URLSearchParams();
    Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
    body.append('_token', cfg.token || '');
    return fetch(url, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Respuesta inválida' }; }); });
  }
  window.cpPost = post;

  /* -------------------------------------------------------------- avisos */
  function toast(msg, kind) {
    var el = document.createElement('div');
    el.className = 'cp-toast' + (kind === 'error' ? ' is-error' : '');
    el.setAttribute('role', 'status');
    el.textContent = msg;
    document.body.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('is-on'); });
    setTimeout(function () {
      el.classList.remove('is-on');
      setTimeout(function () { el.remove(); }, 400);
    }, 3600);
  }
  window.cpToast = toast;

  /* ------------------------------------------------------- menú principal */
  var navBtn = $('.navtoggle');
  if (navBtn) {
    navBtn.addEventListener('click', function () {
      var open = navBtn.getAttribute('aria-expanded') === 'true';
      navBtn.setAttribute('aria-expanded', String(!open));
      var nav = $('#mainnav');
      if (nav) { nav.classList.toggle('is-open', !open); }
    });
  }

  /* ------------------------------------------------ revelado al hacer scroll */
  var revealables = $$('.reveal');
  if (revealables.length) {
    if (reduce || !('IntersectionObserver' in window)) {
      revealables.forEach(function (el) { el.classList.add('is-in'); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
        });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
      revealables.forEach(function (el) { io.observe(el); });
    }
  }

  /* ------------------------------------------------------- contadores */
  function runCounter(el) {
    var target = parseFloat(el.dataset.count || '0');
    var suffix = el.dataset.suffix || '';
    var dur = 1400;
    if (reduce) { el.textContent = target.toLocaleString('es-GT') + suffix; return; }
    var t0 = null;
    function step(ts) {
      if (t0 === null) { t0 = ts; }
      var p = Math.min(1, (ts - t0) / dur);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * eased).toLocaleString('es-GT') + suffix;
      if (p < 1) { requestAnimationFrame(step); }
    }
    requestAnimationFrame(step);
  }
  var counters = $$('[data-count]');
  if (counters.length) {
    if (!('IntersectionObserver' in window)) {
      counters.forEach(runCounter);
    } else {
      var cio = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) { runCounter(en.target); cio.unobserve(en.target); }
        });
      }, { threshold: 0.4 });
      counters.forEach(function (el) { cio.observe(el); });
    }
  }

  /* -------------------------------------- líneas de plano dibujadas al scroll */
  $$('.tracer path').forEach(function (p) {
    var len = 0;
    try { len = p.getTotalLength(); } catch (e) { len = 2000; }
    p.style.setProperty('--len', len);
    p.style.strokeDasharray = len;
    p.style.strokeDashoffset = reduce ? 0 : len;
  });
  if (!reduce) {
    var tracers = $$('.tracer');
    if (tracers.length) {
      var draw = function () {
        tracers.forEach(function (svg) {
          var r = svg.getBoundingClientRect();
          var vh = window.innerHeight || 800;
          var progress = (vh - r.top) / (vh + r.height);
          progress = Math.max(0, Math.min(1, progress));
          $$('path', svg).forEach(function (p) {
            var len = parseFloat(p.style.getPropertyValue('--len')) || 2000;
            p.style.strokeDashoffset = String(len * (1 - progress));
          });
        });
      };
      var ticking = false;
      window.addEventListener('scroll', function () {
        if (ticking) { return; }
        ticking = true;
        requestAnimationFrame(function () { draw(); ticking = false; });
      }, { passive: true });
      draw();
    }
  }

  /* ------------------------------------------------------------- blur-up */
  $$('.blurup').forEach(function (box) {
    var img = $('img:not(.blurup__ph)', box);
    if (!img) { return; }
    var done = function () { img.classList.add('is-loaded'); box.classList.add('is-done'); };
    if (img.complete && img.naturalWidth > 0) { done(); } else { img.addEventListener('load', done, { once: true }); img.addEventListener('error', done, { once: true }); }
  });

  /* -------------------------------------------------- barrido entre páginas */
  var sweep = $('.sweep');
  if (sweep && !reduce) {
    sweep.classList.add('is-in');
    setTimeout(function () { sweep.classList.remove('is-in'); }, 300);
    document.addEventListener('click', function (ev) {
      var a = ev.target.closest ? ev.target.closest('a') : null;
      if (!a || ev.defaultPrevented || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button !== 0) { return; }
      var href = a.getAttribute('href') || '';
      if (!href || href.charAt(0) === '#' || a.target === '_blank' || a.hasAttribute('download') || a.dataset.nosweep !== undefined) { return; }
      if (a.host !== window.location.host) { return; }
      if (/\.(pdf|xlsx|csv|zip|sql|gz|png|jpg|webp)$/i.test(href)) { return; }
      ev.preventDefault();
      sweep.classList.remove('is-in');
      sweep.classList.add('is-out');
      setTimeout(function () { window.location.href = a.href; }, 230);
    });
  }

  /* ------------------------------------------------- buscador con sugerencias */
  $$('[data-suggest]').forEach(function (input) {
    var url = input.dataset.suggest;
    var box = $('#' + input.getAttribute('aria-controls'));
    if (!box) { return; }
    var timer = null, idx = -1;

    function close() { box.classList.remove('is-on'); box.innerHTML = ''; idx = -1; input.setAttribute('aria-expanded', 'false'); }

    function render(items) {
      if (!items.length) { close(); return; }
      box.innerHTML = items.map(function (it) {
        return '<a href="' + it.url + '"><span class="code-chip">' + esc(it.code) + '</span><span>' + esc(it.name) + '</span><em>' + esc(it.cat || '') + '</em></a>';
      }).join('');
      box.classList.add('is-on');
      input.setAttribute('aria-expanded', 'true');
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 2) { close(); return; }
      timer = setTimeout(function () {
        fetch(url + (url.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) { render((d && d.items) || []); })
          .catch(function () { close(); });
      }, 220);
    });
    input.addEventListener('keydown', function (ev) {
      var links = $$('a', box);
      if (ev.key === 'Escape') { close(); return; }
      if (!links.length) { return; }
      if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
        ev.preventDefault();
        idx = ev.key === 'ArrowDown' ? Math.min(links.length - 1, idx + 1) : Math.max(0, idx - 1);
        links.forEach(function (l, i) { l.classList.toggle('is-active', i === idx); });
      } else if (ev.key === 'Enter' && idx >= 0) {
        ev.preventDefault();
        window.location.href = links[idx].href;
      }
    });
    document.addEventListener('click', function (ev) {
      if (!box.contains(ev.target) && ev.target !== input) { close(); }
    });
  });

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  window.cpEsc = esc;

  /* -------------------------------------------- carrito de cotización (AJAX) */
  function setCartCount(n) {
    var fab = $('.cartfab');
    $$('[data-cart-count]').forEach(function (el) { el.textContent = String(n); });
    if (fab) { fab.classList.toggle('is-on', n > 0); }
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest ? ev.target.closest('[data-add-cart]') : null;
    if (!btn || !cfg.cartUrl) { return; }
    ev.preventDefault();
    var id = btn.dataset.addCart;
    var qtyEl = btn.dataset.qtyFrom ? $('#' + btn.dataset.qtyFrom) : null;
    var noteEl = btn.dataset.noteFrom ? $('#' + btn.dataset.noteFrom) : null;
    var label = btn.innerHTML;
    btn.setAttribute('aria-disabled', 'true');
    post(cfg.cartUrl, {
      action: 'add', id: id,
      qty: qtyEl ? qtyEl.value : (btn.dataset.qty || 1),
      note: noteEl ? noteEl.value : ''
    }).then(function (r) {
      btn.removeAttribute('aria-disabled');
      if (!r.ok) { toast(r.error || 'No se pudo agregar.', 'error'); return; }
      setCartCount(r.count);
      btn.innerHTML = '<span>Agregado ✓</span>';
      setTimeout(function () { btn.innerHTML = label; }, 1800);
      toast('Agregado a su solicitud de cotización.');
    }).catch(function () {
      btn.removeAttribute('aria-disabled');
      toast('Error de conexión.', 'error');
    });
  });

  $$('[data-cart-qty]').forEach(function (input) {
    input.addEventListener('change', function () {
      post(cfg.cartUrl, { action: 'qty', id: input.dataset.cartQty, qty: input.value }).then(function () { window.location.reload(); });
    });
  });
  $$('[data-cart-note]').forEach(function (input) {
    var t = null;
    input.addEventListener('input', function () {
      clearTimeout(t);
      t = setTimeout(function () { post(cfg.cartUrl, { action: 'note', id: input.dataset.cartNote, note: input.value }); }, 700);
    });
  });
  $$('[data-cart-remove]').forEach(function (btn) {
    btn.addEventListener('click', function (ev) {
      ev.preventDefault();
      post(cfg.cartUrl, { action: 'remove', id: btn.dataset.cartRemove }).then(function () { window.location.reload(); });
    });
  });

  /* ------------------------------------------------------ selector de cantidad */
  $$('.qtybox').forEach(function (box) {
    var input = $('input', box);
    $$('button', box).forEach(function (b) {
      b.addEventListener('click', function () {
        var step = parseFloat(input.step || '1') || 1;
        var min = parseFloat(input.min || '0.01');
        var v = parseFloat(input.value || '1') || 1;
        v = b.dataset.step === '-' ? Math.max(min, v - step) : v + step;
        input.value = String(Math.round(v * 100) / 100);
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  });

  /* ------------------------------------------------------ galería de producto */
  var gal = $('[data-gallery]');
  if (gal) {
    var main = $('[data-gallery-main]', gal);
    $$('[data-gallery-thumb]', gal).forEach(function (btn) {
      btn.addEventListener('click', function () {
        $$('[data-gallery-thumb]', gal).forEach(function (b) { b.setAttribute('aria-current', 'false'); });
        btn.setAttribute('aria-current', 'true');
        if (main) { main.src = btn.dataset.galleryThumb; main.alt = btn.dataset.alt || main.alt; }
      });
    });
  }

  /* --------------------------------------------------------- desplegables */
  $$('[data-dropdown]').forEach(function (btn) {
    var menu = document.getElementById(btn.dataset.dropdown);
    if (!menu) { return; }
    btn.addEventListener('click', function (ev) {
      ev.stopPropagation();
      var on = menu.classList.toggle('is-on');
      btn.setAttribute('aria-expanded', String(on));
    });
    document.addEventListener('click', function (ev) {
      if (!menu.contains(ev.target) && ev.target !== btn) {
        menu.classList.remove('is-on');
        btn.setAttribute('aria-expanded', 'false');
      }
    });
  });

  /* ------------------------------------------------- filtros del catálogo */
  var fToggle = $('[data-filters-toggle]');
  if (fToggle) {
    fToggle.addEventListener('click', function () {
      var el = $('.filters');
      if (!el) { return; }
      var on = el.classList.toggle('is-open');
      fToggle.setAttribute('aria-expanded', String(on));
    });
  }
  $$('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form && el.form.submit(); });
  });

  /* ------------------------------------------------------------- copiar */
  $$('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.copy);
      if (!input) { return; }
      var text = input.value || input.textContent;
      var done = function () { var t = btn.textContent; btn.textContent = 'Copiado'; setTimeout(function () { btn.textContent = t; }, 1600); };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done).catch(function () { input.select(); document.execCommand('copy'); done(); });
      } else {
        input.select(); document.execCommand('copy'); done();
      }
    });
  });

  /* ---------------------------------------------- confirmaciones de borrado */
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (f && f.dataset && f.dataset.confirm) {
      if (!window.confirm(f.dataset.confirm)) { ev.preventDefault(); }
    }
  });

  /* --------------------------------------------------------- hero listo */
  document.documentElement.classList.add('is-ready');

  /* --------------------------------------------------- registro del PWA */
  if ('serviceWorker' in navigator && cfg.sw) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(cfg.sw, { scope: cfg.base || '/' }).catch(function () { /* silencioso */ });
    });
  }
  // No se intercepta beforeinstallprompt: el aviso de instalación es el nativo.
})();
