/* CotizaPro B2B — panel: Kanban arrastrable (ratón y táctil), editor de
   cotización, buscador de productos y ordenamiento de categorías. */
(function () {
  'use strict';

  var cfg = window.CP || {};
  var $  = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var post = window.cpPost;
  var toast = window.cpToast || function (m) { window.alert(m); };
  var esc = window.cpEsc || function (s) { return String(s == null ? '' : s); };

  /* ------------------------------------------------------ menú lateral */
  var sideBtn = $('.sidetoggle');
  if (sideBtn) {
    var scrim = document.createElement('div');
    scrim.className = 'sidescrim';
    document.body.appendChild(scrim);
    var toggleSide = function (on) {
      var side = $('.side');
      if (!side) { return; }
      side.classList.toggle('is-open', on);
      scrim.classList.toggle('is-on', on);
      sideBtn.setAttribute('aria-expanded', String(on));
    };
    sideBtn.addEventListener('click', function () { toggleSide(!$('.side').classList.contains('is-open')); });
    scrim.addEventListener('click', function () { toggleSide(false); });
  }

  /* =====================================================================
     KANBAN — arrastre unificado con Pointer Events (ratón, lápiz y dedo)
     ===================================================================== */
  var board = $('[data-board]');
  if (board) {
    var dragging = null, ghost = null, startX = 0, startY = 0, offX = 0, offY = 0, active = false, srcCol = null;

    function columnsUnder(x, y) {
      return $$('.bcol', board).find(function (col) {
        var r = col.getBoundingClientRect();
        return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
      }) || null;
    }

    function cleanup() {
      if (ghost) { ghost.remove(); ghost = null; }
      if (dragging) { dragging.classList.remove('is-drag'); }
      $$('.bcol', board).forEach(function (c) { c.classList.remove('is-over'); });
      dragging = null; active = false; srcCol = null;
    }

    board.addEventListener('pointerdown', function (ev) {
      var card = ev.target.closest ? ev.target.closest('.qcard') : null;
      if (!card || ev.button > 0) { return; }
      if (ev.target.closest('a, button')) { return; }
      dragging = card;
      srcCol = card.closest('.bcol');
      startX = ev.clientX; startY = ev.clientY;
      var r = card.getBoundingClientRect();
      offX = ev.clientX - r.left; offY = ev.clientY - r.top;
      card.setPointerCapture(ev.pointerId);
    });

    board.addEventListener('pointermove', function (ev) {
      if (!dragging) { return; }
      var dx = ev.clientX - startX, dy = ev.clientY - startY;
      if (!active) {
        if (Math.abs(dx) + Math.abs(dy) < 8) { return; }
        active = true;
        var r = dragging.getBoundingClientRect();
        ghost = dragging.cloneNode(true);
        ghost.classList.add('is-ghost');
        ghost.style.position = 'fixed';
        ghost.style.width = r.width + 'px';
        ghost.style.pointerEvents = 'none';
        ghost.style.zIndex = '500';
        document.body.appendChild(ghost);
        dragging.classList.add('is-drag');
      }
      ev.preventDefault();
      ghost.style.left = (ev.clientX - offX) + 'px';
      ghost.style.top = (ev.clientY - offY) + 'px';
      var col = columnsUnder(ev.clientX, ev.clientY);
      $$('.bcol', board).forEach(function (c) { c.classList.toggle('is-over', c === col && c !== srcCol); });
    });

    function finish(ev) {
      if (!dragging) { return; }
      if (!active) { cleanup(); return; }
      var col = columnsUnder(ev.clientX, ev.clientY);
      var card = dragging;
      if (!col || col === srcCol) { cleanup(); return; }
      var status = col.dataset.status;
      var list = $('.bcol__list', col);
      cleanup();
      moveCard(card, list, status, srcColStatus(card));
    }
    function srcColStatus(card) {
      var c = card.closest('.bcol');
      return c ? c.dataset.status : '';
    }
    board.addEventListener('pointerup', finish);
    board.addEventListener('pointercancel', function () { cleanup(); });

    function moveCard(card, list, status, from) {
      var id = card.dataset.id;
      var lostReason = '', lostDetail = '';
      if (status === 'perdida') {
        lostReason = window.prompt('Motivo de pérdida (precio, tiempo de entrega, competencia, sin presupuesto, sin respuesta, cambio de proyecto, otro):', 'precio');
        if (!lostReason) { return; }
        lostDetail = window.prompt('Detalle (opcional):', '') || '';
      }
      var prev = card.parentNode, prevNext = card.nextSibling;
      list.appendChild(card);
      post(cfg.boardMoveUrl, { id: id, status: status, lost_reason: lostReason, lost_detail: lostDetail })
        .then(function (r) {
          if (!r.ok) {
            if (prev) { prev.insertBefore(card, prevNext); }
            toast(r.error || 'No se pudo mover la cotización.', 'error');
            return;
          }
          card.className = 'qcard qcard--' + (r.light || 'verde');
          updateTotals(r.totals || {});
          toast('Cotización movida a ' + (list.closest('.bcol').dataset.label || status) + '.');
        })
        .catch(function () {
          if (prev) { prev.insertBefore(card, prevNext); }
          toast('Error de conexión.', 'error');
        });
    }

    function updateTotals(totals) {
      $$('.bcol', board).forEach(function (col) {
        var st = col.dataset.status;
        var t = totals[st] || { n: 0, monto: 0 };
        var nEl = $('.bcol__n', col), sEl = $('.bcol__sum', col);
        if (nEl) { nEl.textContent = t.n; }
        if (sEl) { sEl.textContent = (cfg.currency || 'Q') + Number(t.monto).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
      });
    }
  }

  /* =====================================================================
     EDITOR DE COTIZACIÓN
     ===================================================================== */
  var editor = $('[data-quote-editor]');
  if (editor) {
    var url = editor.dataset.itemUrl;

    function renderItems(items) {
      var tb = $('[data-items]', editor);
      if (!tb) { return; }
      if (!items.length) {
        tb.innerHTML = '<tr><td colspan="7" style="padding:26px;text-align:center;color:var(--steel)">Aún no hay productos. Búsquelos por código arriba.</td></tr>';
        return;
      }
      tb.innerHTML = items.map(function (it, i) {
        return '<tr data-item="' + it.id + '">' +
          '<td style="color:var(--steel);font-size:.75rem">' + String(i + 1).padStart(2, '0') + '</td>' +
          '<td><span class="code-chip">' + esc(it.code || '—') + '</span></td>' +
          '<td><input class="txt" data-f="name" value="' + esc(it.name) + '" aria-label="Descripción">' +
            (it.specs ? '<div class="small muted" style="padding:0 9px">' + esc(it.specs) + '</div>' : '') +
            '<input class="txt small" data-f="notes" placeholder="Nota para esta línea…" value="' + esc(it.notes || '') + '" aria-label="Nota">' +
          '</td>' +
          '<td style="width:88px"><input type="number" step="0.01" min="0.01" data-f="qty" value="' + Number(it.qty) + '" aria-label="Cantidad"></td>' +
          '<td style="width:112px"><input type="number" step="0.01" min="0" data-f="unit_price" value="' + Number(it.unit_price) + '" aria-label="Precio unitario"></td>' +
          '<td style="width:80px"><input type="number" step="0.01" min="0" max="100" data-f="discount_pct" value="' + Number(it.discount_pct) + '" aria-label="Descuento %"></td>' +
          '<td class="num line" style="width:118px">' + fmt(it.line_total) + '</td>' +
          '<td style="width:42px"><button type="button" class="del" data-del="' + it.id + '" aria-label="Quitar línea">✕</button></td>' +
          '</tr>';
      }).join('');
    }

    function fmt(n) {
      return (cfg.currency || 'Q') + Number(n).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function apply(r) {
      if (!r.ok) { toast(r.error || 'No se pudo guardar.', 'error'); return; }
      renderItems(r.items);
      if (r.totals) {
        var set = function (sel, v) { var el = $(sel, editor); if (el) { el.textContent = v; } };
        set('[data-t-subtotal]', r.totals.subtotal);
        set('[data-t-discount]', r.totals.discount);
        set('[data-t-tax]', r.totals.tax);
        set('[data-t-total]', r.totals.total);
      }
    }

    editor.addEventListener('change', function (ev) {
      var input = ev.target.closest ? ev.target.closest('[data-f]') : null;
      if (!input) { return; }
      var row = input.closest('[data-item]');
      if (!row) { return; }
      var data = { op: 'update', item_id: row.dataset.item };
      $$('[data-f]', row).forEach(function (i) { data[i.dataset.f] = i.value; });
      post(url, data).then(apply);
    });

    editor.addEventListener('click', function (ev) {
      var del = ev.target.closest ? ev.target.closest('[data-del]') : null;
      if (del) {
        ev.preventDefault();
        if (!window.confirm('¿Quitar esta línea de la cotización?')) { return; }
        post(url, { op: 'delete', item_id: del.dataset.del }).then(apply);
      }
    });

    // Alta de línea libre (servicio / flete)
    var freeBtn = $('[data-free-line]', editor);
    if (freeBtn) {
      freeBtn.addEventListener('click', function () {
        var name = window.prompt('Descripción de la línea (servicio, flete, mano de obra…):', '');
        if (!name) { return; }
        var price = window.prompt('Precio unitario:', '0');
        post(url, { op: 'add', name: name, qty: 1, unit_price: price || 0, unit: 'servicio' }).then(apply);
      });
    }

    /* Buscador de productos por código o nombre */
    var ps = $('[data-psearch]', editor);
    if (ps) {
      var input = $('input', ps), list = $('.psearch__list', ps), timer = null, idx = -1;
      var close = function () { list.classList.remove('is-on'); list.innerHTML = ''; idx = -1; };
      var add = function (id) {
        post(url, { op: 'add', product_id: id, qty: 1 }).then(function (r) {
          apply(r);
          input.value = '';
          close();
          input.focus();
        });
      };
      input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (!q) { close(); return; }
        timer = setTimeout(function () {
          fetch(cfg.productSearchUrl + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
              var items = (d && d.items) || [];
              if (!items.length) { list.innerHTML = '<button type="button" disabled style="color:var(--steel)">Sin resultados para “' + esc(q) + '”</button>'; list.classList.add('is-on'); return; }
              list.innerHTML = items.map(function (it) {
                return '<button type="button" data-pid="' + it.id + '"><span class="code-chip">' + esc(it.code) + '</span><span>' + esc(it.name) + '</span><em>' + esc(it.price) + '</em></button>';
              }).join('');
              list.classList.add('is-on');
            });
        }, 200);
      });
      list.addEventListener('click', function (ev) {
        var b = ev.target.closest ? ev.target.closest('[data-pid]') : null;
        if (b) { add(b.dataset.pid); }
      });
      input.addEventListener('keydown', function (ev) {
        var opts = $$('[data-pid]', list);
        if (ev.key === 'Escape') { close(); return; }
        if (!opts.length) { return; }
        if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
          ev.preventDefault();
          idx = ev.key === 'ArrowDown' ? Math.min(opts.length - 1, idx + 1) : Math.max(0, idx - 1);
          opts.forEach(function (o, i) { o.classList.toggle('is-active', i === idx); });
        } else if (ev.key === 'Enter') {
          ev.preventDefault();
          add((opts[idx >= 0 ? idx : 0]).dataset.pid);
        }
      });
      document.addEventListener('click', function (ev) { if (!ps.contains(ev.target)) { close(); } });
    }
  }

  /* =====================================================================
     ORDENAR CATEGORÍAS (arrastre vertical simple)
     ===================================================================== */
  var sortable = $('[data-sortable]');
  if (sortable) {
    var dragEl = null;
    sortable.addEventListener('pointerdown', function (ev) {
      var h = ev.target.closest ? ev.target.closest('[data-handle]') : null;
      if (!h) { return; }
      dragEl = h.closest('[data-row]');
      dragEl.style.opacity = '.45';
      h.setPointerCapture(ev.pointerId);
    });
    sortable.addEventListener('pointermove', function (ev) {
      if (!dragEl) { return; }
      ev.preventDefault();
      var rows = $$('[data-row]', sortable);
      var target = rows.find(function (r) {
        if (r === dragEl) { return false; }
        var rect = r.getBoundingClientRect();
        return ev.clientY > rect.top && ev.clientY < rect.bottom;
      });
      if (target) {
        var rect = target.getBoundingClientRect();
        var after = ev.clientY > rect.top + rect.height / 2;
        target.parentNode.insertBefore(dragEl, after ? target.nextSibling : target);
      }
    });
    sortable.addEventListener('pointerup', function () {
      if (!dragEl) { return; }
      dragEl.style.opacity = '';
      dragEl = null;
      var order = $$('[data-row]', sortable).map(function (r) { return { id: r.dataset.id, parent: r.dataset.parent || 0 }; });
      var body = new URLSearchParams();
      order.forEach(function (o, i) {
        body.append('order[' + i + '][id]', o.id);
        body.append('order[' + i + '][parent]', o.parent);
      });
      body.append('_token', cfg.token || '');
      fetch(cfg.categoryOrderUrl, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(), credentials: 'same-origin'
      }).then(function () { toast('Orden guardado.'); });
    });
  }

  /* ------------------------------------------------------------ modales */
  $$('[data-modal-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var m = document.getElementById(btn.dataset.modalOpen);
      if (!m) { return; }
      m.classList.add('is-on');
      var f = m.querySelector('input,select,textarea,button');
      if (f) { f.focus(); }
    });
  });
  document.addEventListener('click', function (ev) {
    if (ev.target.classList && (ev.target.classList.contains('modal__scrim') || (ev.target.dataset && ev.target.dataset.modalClose !== undefined))) {
      var m = ev.target.closest('.modal');
      if (m) { m.classList.remove('is-on'); }
    }
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') { $$('.modal.is-on').forEach(function (m) { m.classList.remove('is-on'); }); }
  });

  /* ------------------------------------------- previsualización de subidas */
  $$('[data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      var box = document.getElementById(input.dataset.preview);
      if (!box) { return; }
      box.innerHTML = '';
      Array.prototype.slice.call(input.files || []).slice(0, 12).forEach(function (f) {
        if (!/^image\//.test(f.type)) { return; }
        var d = document.createElement('div');
        d.className = 'imgcell';
        var img = document.createElement('img');
        img.alt = f.name;
        img.src = URL.createObjectURL(f);
        img.onload = function () { URL.revokeObjectURL(img.src); };
        d.appendChild(img);
        box.appendChild(d);
      });
    });
  });

  /* ------------------------------------------- selector de tema (ajustes) */
  $$('[data-theme-pick]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      var a = radio.dataset.accent, i = radio.dataset.ink, p = radio.dataset.paper;
      var setv = function (id, v) { var el = document.getElementById(id); if (el && v) { el.value = v; } };
      setv('color_accent', a); setv('color_ink', i); setv('color_paper', p);
      document.documentElement.style.setProperty('--accent', a);
    });
  });
})();
