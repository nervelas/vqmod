/* ============================================================
   MenúGold · menú del comensal
   Carrito, ficha de producto, checkout y seguimiento.
   ============================================================ */
(function () {
  'use strict';

  var cfg = window.MG_MENU || {};
  var KEY = 'mg.cart.' + (cfg.slug || 'x');
  var cart = [];
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Persistencia ---------- */
  function load() {
    try {
      var raw = localStorage.getItem(KEY);
      cart = raw ? JSON.parse(raw) : [];
      if (!Array.isArray(cart)) { cart = []; }
    } catch (e) { cart = []; }
  }
  function save() {
    try { localStorage.setItem(KEY, JSON.stringify(cart)); } catch (e) {}
  }
  function clear() {
    cart = [];
    save();
    paintFab();
  }

  /* ---------- Utilidades ---------- */
  function money(v) {
    var n = Math.round((Number(v) + Number.EPSILON) * 100) / 100;
    return (cfg.currency || 'Q') + n.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function el(sel, root) { return (root || document).querySelector(sel); }
  function els(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function post(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': cfg.csrf || '',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(data || {}),
      credentials: 'same-origin'
    }).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Respuesta inesperada del servidor.' }; }); });
  }
  function toast(message, kind) {
    var t = el('#mg-toast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'mg-toast';
      t.setAttribute('role', 'status');
      t.setAttribute('aria-live', 'polite');
      t.style.cssText = 'position:fixed;left:50%;bottom:calc(5.4rem + env(safe-area-inset-bottom));transform:translateX(-50%) translateY(20px);z-index:400;pointer-events:none;'
        + 'background:var(--carbon-2);border:1px solid var(--line);color:var(--cream);padding:.85rem 1.2rem;border-radius:999px;'
        + 'font-size:.85rem;box-shadow:var(--shadow);opacity:0;transition:opacity .3s,transform .3s;max-width:calc(100vw - 2rem);text-align:center';
      document.body.appendChild(t);
    }
    t.textContent = message;
    t.style.borderColor = kind === 'error' ? 'rgba(196,80,43,.6)' : 'var(--line)';
    requestAnimationFrame(function () { t.style.opacity = '1'; t.style.transform = 'translateX(-50%)'; });
    clearTimeout(t._timer);
    t._timer = setTimeout(function () { t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(20px)'; }, 3200);
  }

  /* ---------- Botón flotante ---------- */
  function cartCount() { return cart.reduce(function (s, i) { return s + i.qty; }, 0); }
  function cartTotal() { return cart.reduce(function (s, i) { return s + i.unit * i.qty; }, 0); }

  function paintFab() {
    var fab = el('#cart-fab');
    if (!fab) { return; }
    var n = cartCount();
    el('#cart-count', fab).textContent = n;
    el('#cart-total', fab).textContent = money(cartTotal());
    fab.classList.toggle('is-visible', n > 0);
    // Sin aria-label: el nombre accesible sale del propio contenido visible
    // («3 · Mi pedido · Q912.00»), que ya es descriptivo y no se contradice
    // con lo que se ve en pantalla.
  }

  function bump() {
    var fab = el('#cart-fab');
    if (!fab || reduced) { return; }
    fab.classList.remove('is-bump');
    void fab.offsetWidth;
    fab.classList.add('is-bump');
  }

  /* ---------- Espía de categorías ---------- */
  function initScrollSpy() {
    var bar = el('#cat-bar');
    if (!bar) { return; }
    var links = els('.cat-link', bar);
    var underline = el('.cat-underline', bar);
    var blocks = links.map(function (l) { return document.getElementById(l.dataset.target); }).filter(Boolean);
    if (!blocks.length) { return; }

    function moveUnderline(link) {
      if (!underline || !link) { return; }
      underline.style.width = link.offsetWidth + 'px';
      underline.style.transform = 'translateX(' + link.offsetLeft + 'px)';
    }
    function setActive(i) {
      links.forEach(function (l, j) { l.classList.toggle('is-on', i === j); });
      moveUnderline(links[i]);
      var link = links[i];
      if (link) {
        var left = link.offsetLeft - bar.clientWidth / 2 + link.offsetWidth / 2;
        bar.scrollTo({ left: Math.max(0, left), behavior: reduced ? 'auto' : 'smooth' });
      }
    }

    links.forEach(function (link, i) {
      link.addEventListener('click', function () {
        var target = document.getElementById(link.dataset.target);
        if (target) { target.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' }); }
        setActive(i);
      });
    });

    var current = 0;
    function onScroll() {
      var probe = window.innerHeight * 0.32;
      var found = 0;
      for (var i = 0; i < blocks.length; i++) {
        if (blocks[i].getBoundingClientRect().top <= probe) { found = i; }
      }
      if (found !== current) { current = found; setActive(found); }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', function () { moveUnderline(links[current]); }, { passive: true });
    setActive(0);
    setTimeout(function () { moveUnderline(links[current]); }, 120);
  }

  /* ---------- Ficha de producto ---------- */
  var sheet, sheetPanel, lastFocused = null, currentProduct = null;

  function ensureSheet() {
    if (sheet) { return; }
    sheet = document.createElement('div');
    sheet.className = 'sheet-modal';
    sheet.id = 'dish-sheet';
    sheet.setAttribute('role', 'dialog');
    sheet.setAttribute('aria-modal', 'true');
    sheet.setAttribute('aria-label', 'Detalle del platillo');
    sheet.innerHTML = '<div class="sheet-backdrop" data-close></div><div class="sheet-panel" id="dish-panel"></div>';
    document.body.appendChild(sheet);
    sheetPanel = el('#dish-panel', sheet);

    sheet.addEventListener('click', function (e) {
      if (e.target.closest('[data-close]')) { closeSheet(); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sheet.classList.contains('is-open')) { closeSheet(); }
      if (e.key === 'Tab' && sheet.classList.contains('is-open')) { trapFocus(e); }
    });
  }

  function trapFocus(e) {
    var focusables = els('a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])', sheetPanel)
      .filter(function (n) { return n.offsetParent !== null; });
    if (!focusables.length) { return; }
    var first = focusables[0], last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  function openSheet(html) {
    ensureSheet();
    lastFocused = document.activeElement;
    sheetPanel.innerHTML = html;
    sheet.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    var focusTarget = el('.sheet-close', sheetPanel);
    if (focusTarget) { focusTarget.focus(); }
  }

  function closeSheet() {
    if (!sheet) { return; }
    limpiarViewTransition();
    sheet.classList.remove('is-open');
    document.body.style.overflow = '';
    currentProduct = null;
    if (lastFocused && lastFocused.focus) { lastFocused.focus(); }
  }

  function limpiarViewTransition() {
    els('[style*="view-transition-name"]').forEach(function (n) { n.style.viewTransitionName = ''; });
  }

  function showDish(id, triggerEl) {
    var url = cfg.base + '/producto/' + id;
    // El nombre de transición debe ser único en la página: se limpia el anterior.
    limpiarViewTransition();
    if (triggerEl && document.startViewTransition && !reduced) {
      var pic = triggerEl.querySelector('.mg-pic, .ph-img');
      if (pic) { pic.style.viewTransitionName = 'dish-photo'; }
    }
    fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) { toast((data && data.error) || 'No se pudo abrir el platillo.', 'error'); return; }
        currentProduct = data.product;
        var render = function () { openSheet(dishHtml(data.product)); wireDishSheet(); };
        if (document.startViewTransition && !reduced) {
          document.startViewTransition(render).finished.then(limpiarViewTransition, limpiarViewTransition);
        } else {
          render();
        }
      })
      .catch(function () { toast('Revisa tu conexión e inténtalo otra vez.', 'error'); });
  }

  function dishHtml(p) {
    var h = '';
    h += '<div class="sheet-photo">' + (p.photo_html || '<span class="ph-img"></span>')
       + '<button class="sheet-close" data-close aria-label="Cerrar">&times;</button></div>';
    h += '<div class="sheet-content">';
    if (p.category_label) { h += '<p class="eyebrow is-plain" style="margin-bottom:.7rem">' + esc(p.category_label) + '</p>'; }
    h += '<h2 class="display">' + esc(p.label) + '</h2>';
    if (p.about) { h += '<p class="sheet-about">' + esc(p.about) + '</p>'; }

    if (p.tags && p.tags.length) {
      h += '<div class="row" style="margin-top:1rem">';
      p.tags.forEach(function (t) { h += '<span class="chip">' + esc(t) + '</span>'; });
      h += '</div>';
    }

    if (p.variants && p.variants.length) {
      h += '<div class="opt-group"><div class="opt-head"><h3>Presentación</h3><span class="chip chip-dim">' + esc(cfg.i18n.required) + '</span></div><div class="opt-list">';
      p.variants.forEach(function (v, i) {
        h += '<label class="opt"><input type="radio" name="variant" value="' + v.id + '"' + ((v.is_default || i === 0) ? ' checked' : '') + '>'
           + '<span class="opt-mark"></span><span class="opt-name">' + esc(v.name) + '</span>'
           + (Number(v.price_delta) ? '<span class="opt-price">+' + money(v.price_delta) + '</span>' : '') + '</label>';
      });
      h += '</div></div>';
    }

    (p.groups || []).forEach(function (g) {
      var multi = g.type === 'multi';
      h += '<div class="opt-group" data-group="' + g.id + '" data-max="' + g.max_select + '" data-min="' + g.min_select + '" data-required="' + (g.is_required ? 1 : 0) + '">';
      h += '<div class="opt-head"><h3>' + esc(g.label) + '</h3>';
      if (g.is_required) { h += '<span class="chip">' + esc(cfg.i18n.required) + '</span>'; }
      else if (multi && g.max_select > 0) { h += '<span class="chip chip-dim">' + esc(cfg.i18n.chooseUpTo.replace(':n', g.max_select)) + '</span>'; }
      h += '</div><div class="opt-list">';
      (g.options || []).forEach(function (o) {
        h += '<label class="opt"><input type="' + (multi ? 'checkbox' : 'radio') + '" name="g' + g.id + '" value="' + o.id + '"'
           + (o.is_default ? ' checked' : '') + '>'
           + '<span class="opt-mark' + (multi ? ' is-square' : '') + '"></span>'
           + '<span class="opt-name">' + esc(o.label) + '</span>'
           + (Number(o.price_delta) ? '<span class="opt-price">+' + money(o.price_delta) + '</span>' : '') + '</label>';
      });
      h += '</div></div>';
    });

    h += '<div class="opt-group"><label class="label" for="dish-note">' + esc(cfg.i18n.notes) + '</label>'
       + '<textarea class="textarea" id="dish-note" maxlength="200" rows="2" placeholder="Sin cebolla, término medio…"></textarea></div>';

    h += '<div class="sheet-foot"><div class="row-between" style="gap:1rem">'
       + '<div class="qty"><button type="button" data-qty="-1" aria-label="Quitar uno">−</button>'
       + '<output id="dish-qty" aria-live="polite">1</output>'
       + '<button type="button" data-qty="1" aria-label="Agregar uno">+</button></div>'
       + '<button class="btn" id="dish-add" style="flex:1">' + esc(cfg.i18n.add) + ' <span id="dish-price">' + money(p.final_price) + '</span></button>'
       + '</div></div>';
    h += '</div>';
    return h;
  }

  function wireDishSheet() {
    var qty = 1;
    var out = el('#dish-qty', sheetPanel);

    function recompute() {
      var p = currentProduct;
      if (!p) { return 0; }
      var unit = Number(p.final_price);
      var variant = el('input[name="variant"]:checked', sheetPanel);
      if (variant) {
        var v = (p.variants || []).filter(function (x) { return String(x.id) === variant.value; })[0];
        if (v) { unit += Number(v.price_delta); }
      }
      els('.opt-group[data-group]', sheetPanel).forEach(function (grp) {
        var g = (p.groups || []).filter(function (x) { return String(x.id) === grp.dataset.group; })[0];
        if (!g) { return; }
        els('input:checked', grp).forEach(function (input) {
          var o = (g.options || []).filter(function (x) { return String(x.id) === input.value; })[0];
          if (o) { unit += Number(o.price_delta); }
        });
      });
      el('#dish-price', sheetPanel).textContent = money(unit * qty);
      return unit;
    }

    sheetPanel.addEventListener('change', recompute);
    els('[data-qty]', sheetPanel).forEach(function (b) {
      b.addEventListener('click', function () {
        qty = Math.max(1, Math.min(50, qty + Number(b.dataset.qty)));
        out.textContent = qty;
        recompute();
      });
    });

    // Respeta el máximo de cada grupo de casillas.
    els('.opt-group[data-group]', sheetPanel).forEach(function (grp) {
      var max = Number(grp.dataset.max) || 0;
      grp.addEventListener('change', function (e) {
        if (max <= 0 || e.target.type !== 'checkbox') { return; }
        var checked = els('input:checked', grp);
        if (checked.length > max) {
          e.target.checked = false;
          toast(cfg.i18n.chooseUpTo.replace(':n', max), 'error');
        }
      });
    });

    el('#dish-add', sheetPanel).addEventListener('click', function () {
      var p = currentProduct;
      if (!p) { return; }
      // Valida los grupos obligatorios antes de agregar.
      var missing = null;
      els('.opt-group[data-group]', sheetPanel).forEach(function (grp) {
        if (missing) { return; }
        if (grp.dataset.required === '1' && !el('input:checked', grp)) {
          var title = el('h3', grp);
          missing = title ? title.textContent : 'una opción';
        }
      });
      if (missing) { toast('Elige ' + missing + '.', 'error'); return; }

      var unit = recompute();
      var chosen = [];
      var optionIds = [];
      var variantEl = el('input[name="variant"]:checked', sheetPanel);
      var variantId = 0;
      if (variantEl) {
        variantId = Number(variantEl.value);
        var v = (p.variants || []).filter(function (x) { return String(x.id) === variantEl.value; })[0];
        if (v) { chosen.push(v.name); }
      }
      els('.opt-group[data-group]', sheetPanel).forEach(function (grp) {
        var g = (p.groups || []).filter(function (x) { return String(x.id) === grp.dataset.group; })[0];
        if (!g) { return; }
        els('input:checked', grp).forEach(function (input) {
          optionIds.push(Number(input.value));
          var o = (g.options || []).filter(function (x) { return String(x.id) === input.value; })[0];
          if (o) { chosen.push(o.label); }
        });
      });

      cart.push({
        product_id: p.id,
        name: p.label,
        image_html: p.thumb_html || '',
        qty: qty,
        unit: unit,
        variant_id: variantId,
        options: optionIds,
        summary: chosen.join(' · '),
        notes: (el('#dish-note', sheetPanel).value || '').slice(0, 200)
      });
      save();
      paintFab();
      bump();
      closeSheet();
      toast(p.label + ' agregado a tu pedido.');
    });

    recompute();
  }

  /* ---------- Carrito y pago ---------- */
  function openCart() {
    if (!cart.length) { toast(cfg.i18n.emptyCart); return; }
    openSheet(cartHtml());
    wireCart();
  }

  function cartHtml() {
    var h = '<div class="sheet-content"><div class="row-between" style="margin-bottom:1.4rem">'
          + '<h2 class="display">' + esc(cfg.i18n.cart) + '</h2>'
          + '<button class="sheet-close" data-close aria-label="Cerrar" style="position:static">&times;</button></div>';

    h += '<div id="cart-lines">';
    cart.forEach(function (line, i) {
      h += '<div class="cart-line">'
         + (line.image_html || '<span class="ph-img" style="width:62px;height:62px;border-radius:10px"></span>')
         + '<div><b>' + esc(line.name) + '</b>'
         + (line.summary ? '<small>' + esc(line.summary) + '</small>' : '')
         + (line.notes ? '<small>“' + esc(line.notes) + '”</small>' : '')
         + '<small>' + line.qty + ' × ' + money(line.unit) + '</small>'
         + '<button class="cart-remove" data-remove="' + i + '">Quitar</button></div>'
         + '<div class="cart-line-price">' + money(line.unit * line.qty) + '</div></div>';
    });
    h += '</div>';

    // Modo de servicio
    var modes = cfg.modes || ['dine_in'];
    if (modes.length > 1 && !cfg.table) {
      h += '<div class="mt-3"><p class="label">¿Cómo lo quieres?</p><div class="mode-tabs" id="mode-tabs">';
      modes.forEach(function (m, i) {
        var labels = { dine_in: 'En mesa', takeaway: 'Para llevar', delivery: 'A domicilio' };
        h += '<button type="button" class="mode-tab' + (i === 0 ? ' is-on' : '') + '" data-mode="' + m + '">' + labels[m] + '</button>';
      });
      h += '</div></div>';
    }

    h += '<div id="mode-fields"></div>';

    // Cupón
    h += '<div class="mt-2"><label class="label" for="coupon">Cupón</label>'
       + '<div class="row" style="flex-wrap:nowrap"><input class="input" id="coupon" placeholder="Código" autocomplete="off" style="text-transform:uppercase">'
       + '<button class="btn btn-ghost btn-sm" id="apply-coupon" type="button">Aplicar</button></div>'
       + '<p class="field-hint" id="coupon-msg"></p></div>';

    // Propina
    if (cfg.tip && cfg.tipOptions && cfg.tipOptions.length) {
      h += '<div class="mt-2"><p class="label">' + esc(cfg.i18n.tip) + ' (opcional)</p><div class="tip-row" id="tip-row">'
         + '<button type="button" class="tip-opt is-on" data-tip="0">Sin propina</button>';
      cfg.tipOptions.forEach(function (t) {
        h += '<button type="button" class="tip-opt" data-tip="' + t + '">' + t + '%</button>';
      });
      h += '</div></div>';
    }

    h += '<div class="mt-2"><label class="label" for="order-notes">Notas del pedido</label>'
       + '<textarea class="textarea" id="order-notes" rows="2" maxlength="500" placeholder="Alergias, indicaciones para llegar…"></textarea></div>';

    h += '<div class="totals" id="cart-totals"></div>';

    h += '<div class="sheet-foot"><button class="btn btn-block" id="place-order">' + esc(cfg.i18n.checkout) + '</button>'
       + '<p class="field-hint" style="text-align:center;margin-top:.8rem">Los precios se confirman en el servidor antes de cobrar.</p></div>';
    h += '</div>';
    return h;
  }

  function wireCart() {
    var mode = cfg.table ? 'dine_in' : ((cfg.modes && cfg.modes[0]) || 'dine_in');
    var tip = 0;
    var coupon = '';
    var quoting = false;

    function payload() {
      return {
        items: cart.map(function (l) {
          return { product_id: l.product_id, qty: l.qty, variant_id: l.variant_id, options: l.options, notes: l.notes };
        }),
        mode: mode,
        zone_id: Number((el('#zone') || {}).value || 0),
        coupon: coupon,
        tip_percent: tip,
        table_id: cfg.table ? cfg.table.id : 0,
        table_key: cfg.tableKey || '',
        name: (el('#c-name') || {}).value || '',
        phone: (el('#c-phone') || {}).value || '',
        address: (el('#c-address') || {}).value || '',
        notes: (el('#order-notes') || {}).value || '',
        lang: cfg.lang || 'es'
      };
    }

    function paintModeFields() {
      var box = el('#mode-fields', sheetPanel);
      if (!box) { return; }
      var h = '';
      if (mode !== 'dine_in') {
        h += '<div class="grid grid-2 mt-2">'
           + '<div class="field"><label for="c-name">Tu nombre</label><input class="input" id="c-name" autocomplete="name" maxlength="120"></div>'
           + '<div class="field"><label for="c-phone">Teléfono</label><input class="input" id="c-phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="20"></div>'
           + '</div>';
      }
      if (mode === 'delivery') {
        h += '<div class="field"><label for="c-address">Dirección de entrega</label><textarea class="textarea" id="c-address" rows="2" maxlength="255"></textarea></div>';
        if (cfg.zones && cfg.zones.length) {
          h += '<div class="field"><label for="zone">Zona</label><select class="select" id="zone">';
          cfg.zones.forEach(function (z) {
            h += '<option value="' + z.id + '">' + esc(z.name) + ' · ' + money(z.fee) + (Number(z.min_order) > 0 ? ' · mínimo ' + money(z.min_order) : '') + '</option>';
          });
          h += '</select></div>';
        }
      }
      box.innerHTML = h;
      quote();
    }

    function paintTotals(t) {
      var box = el('#cart-totals', sheetPanel);
      if (!box) { return; }
      var h = '<div><span>' + esc(cfg.i18n.subtotal) + '</span><span>' + money(t.subtotal) + '</span></div>';
      if (Number(t.discount) > 0)     { h += '<div><span>' + esc(cfg.i18n.discount) + '</span><span>−' + money(t.discount) + '</span></div>'; }
      if (Number(t.delivery_fee) > 0) { h += '<div><span>' + esc(cfg.i18n.delivery) + '</span><span>' + money(t.delivery_fee) + '</span></div>'; }
      if (Number(t.tax) > 0)          { h += '<div><span>Impuesto</span><span>' + money(t.tax) + '</span></div>'; }
      if (Number(t.tip) > 0)          { h += '<div><span>' + esc(cfg.i18n.tip) + '</span><span>' + money(t.tip) + '</span></div>'; }
      h += '<div class="is-total"><span>' + esc(cfg.i18n.total) + '</span><span>' + money(t.total) + '</span></div>';
      box.innerHTML = h;
    }

    function quote() {
      if (quoting) { return; }
      quoting = true;
      post(cfg.base + '/cotizar', payload()).then(function (r) {
        quoting = false;
        if (r && r.ok) { paintTotals(r.totals); }
      }).catch(function () { quoting = false; });
    }

    sheetPanel.addEventListener('click', function (e) {
      var rm = e.target.closest('[data-remove]');
      if (rm) {
        cart.splice(Number(rm.dataset.remove), 1);
        save();
        paintFab();
        if (!cart.length) { closeSheet(); toast(cfg.i18n.emptyCart); return; }
        openSheet(cartHtml());
        wireCart();
        return;
      }
      var mt = e.target.closest('[data-mode]');
      if (mt) {
        mode = mt.dataset.mode;
        els('.mode-tab', sheetPanel).forEach(function (b) { b.classList.toggle('is-on', b === mt); });
        paintModeFields();
        return;
      }
      var tp = e.target.closest('[data-tip]');
      if (tp) {
        tip = Number(tp.dataset.tip);
        els('.tip-opt', sheetPanel).forEach(function (b) { b.classList.toggle('is-on', b === tp); });
        quote();
        return;
      }
      if (e.target.id === 'apply-coupon') {
        var code = (el('#coupon', sheetPanel).value || '').trim().toUpperCase();
        if (!code) { return; }
        post(cfg.base + '/cupon', { code: code, items: payload().items }).then(function (r) {
          var msg = el('#coupon-msg', sheetPanel);
          if (r && r.ok) {
            coupon = code;
            msg.textContent = r.message || 'Cupón aplicado.';
            msg.className = 'field-hint gold';
            quote();
          } else {
            coupon = '';
            msg.textContent = (r && r.error) || 'Cupón no válido.';
            msg.className = 'field-error';
            quote();
          }
        });
      }
    });

    var placeBtn = el('#place-order', sheetPanel);
    placeBtn.addEventListener('click', function () {
      placeBtn.disabled = true;
      placeBtn.textContent = 'Enviando…';
      post(cfg.base + '/pedido', payload()).then(function (r) {
        if (!r || !r.ok) {
          placeBtn.disabled = false;
          placeBtn.textContent = cfg.i18n.checkout;
          toast((r && r.error) || 'No se pudo enviar el pedido.', 'error');
          return;
        }
        clear();
        if (r.whatsapp_url) { window.open(r.whatsapp_url, '_blank', 'noopener'); }
        window.location.href = r.track_url;
      }).catch(function () {
        placeBtn.disabled = false;
        placeBtn.textContent = cfg.i18n.checkout;
        toast('Revisa tu conexión e inténtalo otra vez.', 'error');
      });
    });

    paintModeFields();
  }

  /* ---------- Llamar al mesero / pedir la cuenta ---------- */
  function initServiceCalls() {
    els('[data-call]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        btn.disabled = true;
        post(cfg.base + '/llamar', { type: btn.dataset.call, table_id: cfg.table ? cfg.table.id : 0, table_key: cfg.tableKey || '' })
          .then(function (r) {
            toast((r && r.ok) ? (r.message || 'Aviso enviado.') : ((r && r.error) || 'No se pudo avisar.'), (r && r.ok) ? '' : 'error');
            setTimeout(function () { btn.disabled = false; }, 12000);
          })
          .catch(function () { btn.disabled = false; toast('Revisa tu conexión.', 'error'); });
      });
    });
  }

  /* ---------- Buscador ---------- */
  function initSearch() {
    var toggle = el('#search-toggle');
    var wrap = el('#search-wrap');
    var input = el('#search-input');
    if (!toggle || !wrap || !input) { return; }
    toggle.addEventListener('click', function () {
      var open = wrap.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) { input.focus(); } else { input.value = ''; filter(''); }
    });
    var timer;
    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { filter(input.value.trim().toLowerCase()); }, 140);
    });

    function filter(term) {
      var anyGlobal = false;
      els('.cat-block').forEach(function (block) {
        var any = false;
        els('.dish', block).forEach(function (d) {
          var hit = !term || (d.dataset.search || '').indexOf(term) !== -1;
          d.style.display = hit ? '' : 'none';
          if (hit) { any = true; }
        });
        block.style.display = any ? '' : 'none';
        if (any) { anyGlobal = true; }
      });
      var empty = el('#search-empty');
      if (empty) { empty.style.display = (term && !anyGlobal) ? '' : 'none'; }
    }
  }

  /* ---------- Seguimiento en vivo ---------- */
  function initTracking() {
    var wrap = el('[data-track]');
    if (!wrap) { return; }
    var token = wrap.dataset.track;
    var current = wrap.dataset.status;
    var delay = 8000;

    function poll() {
      fetch(cfg.root + '/api/pedido/' + token, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (r && r.ok && r.status !== current) { window.location.reload(); }
          if (r && r.ok && (r.status === 'paid' || r.status === 'cancelled')) { return; }
          setTimeout(poll, delay);
        })
        .catch(function () { delay = Math.min(delay * 1.6, 45000); setTimeout(poll, delay); });
    }
    setTimeout(poll, delay);
  }

  /* ---------- Copiar al portapapeles ---------- */
  function initCopy() {
    els('[data-copy]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var text = btn.dataset.copy;
        var done = function () { var old = btn.textContent; btn.textContent = 'Copiado'; setTimeout(function () { btn.textContent = old; }, 1800); };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(done).catch(fallback);
        } else { fallback(); }
        function fallback() {
          var ta = document.createElement('textarea');
          ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
          document.body.appendChild(ta); ta.select();
          try { document.execCommand('copy'); done(); } catch (e) {}
          document.body.removeChild(ta);
        }
      });
    });
  }

  /* ---------- Portada → menú ---------- */
  function initCover() {
    var btn = el('#enter-menu');
    var sheetEl = el('#menu-sheet');
    if (!btn || !sheetEl) { return; }
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      sheetEl.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
    });
  }

  /* ---------- Arranque ---------- */
  function boot() {
    load();
    paintFab();
    initScrollSpy();
    initSearch();
    initServiceCalls();
    initTracking();
    initCopy();
    initCover();

    document.addEventListener('click', function (e) {
      var dish = e.target.closest('[data-dish]');
      if (dish) { showDish(dish.dataset.dish, dish); return; }
      if (e.target.closest('#cart-fab')) { openCart(); }
    });
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); }
  else { boot(); }

  window.MGMenu = { open: openCart, clear: clear };
})();
