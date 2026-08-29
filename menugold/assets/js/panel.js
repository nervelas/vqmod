/* ============================================================
   MenúGold · panel de administración
   ============================================================ */
(function () {
  'use strict';

  var cfg = window.MG_PANEL || {};
  function el(s, r) { return (r || document).querySelector(s); }
  function els(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function post(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': cfg.csrf || '', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(data || {}),
      credentials: 'same-origin'
    }).then(function (r) { return r.json().catch(function () { return { ok: false }; }); });
  }
  function toast(msg, kind) {
    var t = el('#p-toast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'p-toast';
      t.setAttribute('role', 'status');
      t.style.cssText = 'position:fixed;right:1.2rem;bottom:1.2rem;z-index:900;pointer-events:none;background:var(--carbon-2);border:1px solid var(--line);'
        + 'color:var(--cream);padding:.85rem 1.2rem;border-radius:12px;font-size:.85rem;box-shadow:var(--shadow);opacity:0;transform:translateY(12px);'
        + 'transition:opacity .3s,transform .3s;max-width:min(360px,calc(100vw - 2rem))';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.borderColor = kind === 'error' ? 'rgba(196,80,43,.6)' : 'var(--line)';
    requestAnimationFrame(function () { t.style.opacity = '1'; t.style.transform = 'none'; });
    clearTimeout(t._timer);
    t._timer = setTimeout(function () { t.style.opacity = '0'; t.style.transform = 'translateY(12px)'; }, 3400);
  }

  /* ---------- Barra lateral en móvil ---------- */
  function initSidebar() {
    var side = el('.side');
    var burger = el('#burger');
    if (!side || !burger) { return; }
    var scrim = document.createElement('div');
    scrim.className = 'side-scrim';
    document.body.appendChild(scrim);
    function toggle(open) {
      side.classList.toggle('is-open', open);
      scrim.classList.toggle('is-on', open);
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.style.overflow = open ? 'hidden' : '';
    }
    burger.addEventListener('click', function () { toggle(!side.classList.contains('is-open')); });
    scrim.addEventListener('click', function () { toggle(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { toggle(false); } });
  }

  /* ---------- Confirmaciones ---------- */
  function initConfirm() {
    document.addEventListener('submit', function (e) {
      var form = e.target;
      var msg = form.dataset.confirm;
      if (msg && !window.confirm(msg)) { e.preventDefault(); }
    });
    document.addEventListener('click', function (e) {
      var a = e.target.closest('a[data-confirm]');
      if (a && !window.confirm(a.dataset.confirm)) { e.preventDefault(); }
    });
  }

  /* ---------- Modales ---------- */
  function initModals() {
    document.addEventListener('click', function (e) {
      var open = e.target.closest('[data-modal-open]');
      if (open) {
        var m = document.getElementById(open.dataset.modalOpen);
        if (m) {
          m.classList.add('is-open');
          document.body.style.overflow = 'hidden';
          var f = m.querySelector('input,select,textarea,button');
          if (f) { f.focus(); }
        }
        return;
      }
      if (e.target.closest('[data-modal-close]') || e.target.classList.contains('modal-backdrop')) {
        var mm = e.target.closest('.modal');
        if (mm) { mm.classList.remove('is-open'); document.body.style.overflow = ''; }
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') { return; }
      els('.modal.is-open').forEach(function (m) { m.classList.remove('is-open'); });
      document.body.style.overflow = '';
    });
  }

  /* ---------- Ordenar arrastrando ---------- */
  function initSortable() {
    els('[data-sortable]').forEach(function (list) {
      var dragging = null;
      els('li', list).forEach(function (li) { li.draggable = true; });

      list.addEventListener('dragstart', function (e) {
        var li = e.target.closest('li');
        if (!li) { return; }
        dragging = li;
        li.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', li.dataset.id || ''); } catch (err) {}
      });
      list.addEventListener('dragend', function () {
        if (dragging) { dragging.classList.remove('is-dragging'); }
        els('li', list).forEach(function (li) { li.classList.remove('is-over'); });
        dragging = null;
        persist(list);
      });
      list.addEventListener('dragover', function (e) {
        e.preventDefault();
        var li = e.target.closest('li');
        if (!li || li === dragging || !dragging) { return; }
        els('li', list).forEach(function (n) { n.classList.remove('is-over'); });
        li.classList.add('is-over');
        var rect = li.getBoundingClientRect();
        var after = (e.clientY - rect.top) > rect.height / 2;
        list.insertBefore(dragging, after ? li.nextSibling : li);
      });

      // Alternativa accesible con el teclado
      list.addEventListener('keydown', function (e) {
        var li = e.target.closest('li');
        if (!li || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') || !e.altKey) { return; }
        e.preventDefault();
        if (e.key === 'ArrowUp' && li.previousElementSibling) { list.insertBefore(li, li.previousElementSibling); }
        if (e.key === 'ArrowDown' && li.nextElementSibling) { list.insertBefore(li.nextElementSibling, li); }
        li.focus();
        persist(list);
      });
      els('li', list).forEach(function (li) { li.tabIndex = 0; });
    });

    function persist(list) {
      var url = list.dataset.sortable;
      if (!url) { return; }
      var ids = els('li', list).map(function (li) { return Number(li.dataset.id); }).filter(Boolean);
      post(url, { order: ids }).then(function (r) {
        toast(r && r.ok ? 'Orden guardado.' : 'No se pudo guardar el orden.', r && r.ok ? '' : 'error');
      });
    }
  }

  /* ---------- Vista previa de imágenes ---------- */
  function initImagePreview() {
    els('input[type="file"][data-preview]').forEach(function (input) {
      input.addEventListener('change', function () {
        var box = document.getElementById(input.dataset.preview);
        if (!box || !input.files || !input.files[0]) { return; }
        var f = input.files[0];
        if (f.size > (cfg.maxUpload || 8388608)) {
          toast('La imagen pesa más de lo permitido.', 'error');
          input.value = '';
          return;
        }
        var url = URL.createObjectURL(f);
        box.innerHTML = '<img src="' + url + '" alt="Vista previa" style="width:100%;border-radius:12px;aspect-ratio:4/3;object-fit:cover">';
      });
    });
  }

  /* ---------- Interruptores que guardan solos ---------- */
  function initToggles() {
    els('[data-toggle-url]').forEach(function (input) {
      input.addEventListener('change', function () {
        post(input.dataset.toggleUrl, { value: input.checked ? 1 : 0 }).then(function (r) {
          if (r && r.ok) { toast(r.message || 'Guardado.'); }
          else { input.checked = !input.checked; toast((r && r.error) || 'No se pudo guardar.', 'error'); }
        });
      });
    });
  }

  /* ---------- Cocina en tiempo real ---------- */
  function initKitchen() {
    var board = el('#kds-board');
    if (!board) { return; }
    var lastHash = board.dataset.hash || '';
    var sound = el('#kds-sound');
    var soundOn = localStorage.getItem('mg.kds.sound') !== '0';
    var soundBtn = el('#kds-sound-toggle');
    var es = null;
    var pollTimer = null;

    if (soundBtn) {
      paintSound();
      soundBtn.addEventListener('click', function () {
        soundOn = !soundOn;
        localStorage.setItem('mg.kds.sound', soundOn ? '1' : '0');
        paintSound();
        if (soundOn) { beep(); }
      });
    }
    function paintSound() {
      if (!soundBtn) { return; }
      soundBtn.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
      soundBtn.textContent = soundOn ? 'Sonido activado' : 'Sonido apagado';
    }

    function beep() {
      if (!soundOn) { return; }
      try {
        var AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) { return; }
        var ctx = window._mgAudio || (window._mgAudio = new AC());
        if (ctx.state === 'suspended') { ctx.resume(); }
        [880, 1180].forEach(function (f, i) {
          var o = ctx.createOscillator(), g = ctx.createGain();
          o.type = 'sine'; o.frequency.value = f;
          g.gain.setValueAtTime(0.0001, ctx.currentTime + i * 0.16);
          g.gain.exponentialRampToValueAtTime(0.16, ctx.currentTime + i * 0.16 + 0.02);
          g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + i * 0.16 + 0.24);
          o.connect(g); g.connect(ctx.destination);
          o.start(ctx.currentTime + i * 0.16);
          o.stop(ctx.currentTime + i * 0.16 + 0.26);
        });
      } catch (e) {}
    }

    function refresh(announce) {
      fetch(cfg.base + '/panel/cocina/datos', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (!r || !r.ok) { return; }
          var previousNew = els('.ticket', el('#col-new')).length;
          board.innerHTML = r.html;
          board.dataset.hash = r.hash;
          paintTimers();
          var nowNew = els('.ticket', el('#col-new')).length;
          if (announce && nowNew > previousNew) { beep(); }
        })
        .catch(function () {});
    }

    /* Tiempo real.
       El sondeo cada 5 s está SIEMPRE activo: es lo único que funciona igual
       en todos los hosting compartidos, y una consulta a /api/pulso son dos
       agregados sobre índices. El flujo SSE se conecta encima como acelerador:
       cuando está vivo, el pedido aparece en menos de un segundo. */
    function connect() {
      startPolling();
      if (!window.EventSource) { return; }
      try {
        es = new EventSource(cfg.base + '/api/stream');
        es.addEventListener('pulse', function (ev) {
          var data = JSON.parse(ev.data || '{}');
          if (data.hash && data.hash !== lastHash) { lastHash = data.hash; refresh(true); }
        });
        es.onerror = function () {
          // readyState 0/1: el navegador reconecta solo cuando el servidor
          // cierra la conexión larga. Solo se abandona si se rinde (CLOSED).
          if (es && es.readyState === 2) { es = null; }
        };
      } catch (e) { es = null; }
    }

    function startPolling() {
      if (pollTimer) { return; }
      pollTimer = setInterval(function () {
        if (document.hidden) { return; }
        fetch(cfg.base + '/api/pulso', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) { if (d && d.hash && d.hash !== lastHash) { lastHash = d.hash; refresh(true); } })
          .catch(function () {});
      }, 5000);
    }

    // Cronómetro y color por retraso
    function paintTimers() {
      els('.ticket[data-placed]').forEach(function (t) {
        var mins = Math.floor((Date.now() - Number(t.dataset.placed) * 1000) / 60000);
        var span = el('.ticket-time', t);
        if (span) { span.textContent = mins + ' min'; }
        t.classList.toggle('warn', mins >= 10 && mins < 18);
        t.classList.toggle('late', mins >= 18);
      });
    }
    setInterval(paintTimers, 20000);
    paintTimers();

    board.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-status]');
      if (!btn) { return; }
      btn.disabled = true;
      post(cfg.base + '/panel/pedidos/' + btn.dataset.order + '/estado', { status: btn.dataset.status })
        .then(function (r) {
          if (r && r.ok) { refresh(false); } else { btn.disabled = false; toast((r && r.error) || 'No se pudo actualizar.', 'error'); }
        })
        .catch(function () { btn.disabled = false; });
    });

    connect();
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) { refresh(false); }
    });
  }

  /* ---------- Salón del mesero ---------- */
  function initFloor() {
    var floor = el('#floor-board');
    if (!floor) { return; }
    setInterval(function () {
      fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var fresh = doc.querySelector('#floor-board');
          if (fresh) { floor.innerHTML = fresh.innerHTML; }
        })
        .catch(function () {});
    }, 15000);
  }

  /* ---------- Gráficas ---------- */
  function initCharts() {
    if (typeof window.Chart === 'undefined') { return; }
    var gold = '#D8B26E', cream = 'rgba(244,237,225,.65)', grid = 'rgba(244,237,225,.07)';
    window.Chart.defaults.color = cream;
    window.Chart.defaults.borderColor = grid;
    window.Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    window.Chart.defaults.font.size = 11;

    els('canvas[data-chart]').forEach(function (canvas) {
      var spec;
      try { spec = JSON.parse(canvas.dataset.chart); } catch (e) { return; }
      var type = spec.type || 'line';
      var ctx = canvas.getContext('2d');
      var fill = null;
      if (type === 'line') {
        fill = ctx.createLinearGradient(0, 0, 0, canvas.height || 220);
        fill.addColorStop(0, 'rgba(216,178,110,.34)');
        fill.addColorStop(1, 'rgba(216,178,110,0)');
      }
      var palette = [gold, '#C4502B', '#6FBF8B', '#E8CE9C', '#8E7CC3', '#7FA8C9', '#C9A227'];
      new window.Chart(ctx, {
        type: type,
        data: {
          labels: spec.labels || [],
          datasets: (spec.datasets || []).map(function (ds, i) {
            return Object.assign({
              backgroundColor: type === 'line' ? fill : (spec.multicolor ? palette : palette[i % palette.length]),
              borderColor: type === 'doughnut' ? 'rgba(12,11,9,.9)' : palette[i % palette.length],
              borderWidth: type === 'line' ? 2 : (type === 'doughnut' ? 2 : 0),
              borderRadius: type === 'bar' ? 5 : 0,
              tension: 0.35,
              fill: type === 'line',
              pointRadius: 0,
              pointHoverRadius: 5,
              pointBackgroundColor: gold
            }, ds);
          })
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { intersect: false, mode: 'index' },
          plugins: {
            legend: { display: !!spec.legend, labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } },
            tooltip: {
              backgroundColor: '#1E1B18',
              borderColor: 'rgba(216,178,110,.3)',
              borderWidth: 1,
              padding: 11,
              titleColor: '#F4EDE1',
              bodyColor: 'rgba(244,237,225,.8)',
              displayColors: false,
              callbacks: spec.money ? {
                label: function (c) { return (spec.currency || 'Q') + Number(c.parsed.y != null ? c.parsed.y : c.parsed).toFixed(2); }
              } : undefined
            }
          },
          scales: type === 'doughnut' ? {} : {
            x: { grid: { display: false }, border: { display: false } },
            y: { grid: { color: grid }, border: { display: false }, beginAtZero: true }
          }
        }
      });
    });
  }

  /* ---------- Rangos de fecha rápidos ---------- */
  function initDatePresets() {
    els('[data-range]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var days = Number(btn.dataset.range);
        var to = new Date();
        var from = new Date();
        if (days === 0) { from = new Date(to.getFullYear(), to.getMonth(), 1); }
        else { from.setDate(to.getDate() - days); }
        var f = el('#f-from'), t = el('#f-to');
        if (f && t) { f.value = iso(from); t.value = iso(to); f.form.submit(); }
      });
    });
    function iso(d) {
      return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
  }

  /* ---------- Campos dependientes ---------- */
  function initDependents() {
    els('[data-depends-on]').forEach(function (box) {
      var source = el('#' + box.dataset.dependsOn);
      if (!source) { return; }
      var want = (box.dataset.dependsValue || '').split(',');
      function sync() {
        var v;
        if (source.type === 'checkbox' || source.type === 'radio') {
          v = source.checked ? '1' : '0';
        } else {
          v = source.value;
        }
        box.hidden = want.indexOf(v) === -1;
      }
      // Los radios sólo emiten "change" en el que se marca: se escucha a todo el grupo.
      if (source.type === 'radio' && source.name) {
        els('input[name="' + source.name + '"]').forEach(function (r) { r.addEventListener('change', sync); });
      } else {
        source.addEventListener('change', sync);
      }
      sync();
    });
  }

  /* ---------- Guardado sin recargar (interruptor de agotado) ---------- */
  function initQuickActions() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-quick]');
      if (!btn) { return; }
      e.preventDefault();
      btn.disabled = true;
      post(btn.dataset.quick, {}).then(function (r) {
        btn.disabled = false;
        if (r && r.ok) {
          toast(r.message || 'Listo.');
          if (r.label) { btn.textContent = r.label; }
          if (r.reload) { window.location.reload(); }
          if (r.state !== undefined) { btn.classList.toggle('chip-ember', !!r.state); }
        } else { toast((r && r.error) || 'No se pudo completar.', 'error'); }
      }).catch(function () { btn.disabled = false; });
    });
  }

  function boot() {
    initSidebar();
    initConfirm();
    initModals();
    initSortable();
    initImagePreview();
    initToggles();
    initKitchen();
    initFloor();
    initCharts();
    initDatePresets();
    initDependents();
    initQuickActions();
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); }
  else { boot(); }

  window.MGPanel = { toast: toast, post: post };
})();
