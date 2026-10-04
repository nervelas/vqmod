/* Agenda Premium · calendario (día, semana, mes). Vanilla ES2020.
 * Las fechas viajan en UTC ISO; todo se muestra en la zona horaria del negocio (boot.tz).
 * Todo el contenido dinámico se arma con textContent (nunca innerHTML) para evitar XSS. */
(function () {
  'use strict';
  var d = document, A = window.A1, Ap = window.Ap;
  var root = d.querySelector('[data-calendar]');
  if (!root || !A || !Ap) { return; }
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var h = A.h;
  var boot = JSON.parse($('#boot', root).textContent);
  var TZ = boot.tz, HH = 56, SNAP = 15;
  var DAYS = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
  var MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  var VIEWS = { dia: 'Día', semana: 'Semana', mes: 'Mes' };
  var hostColor = {};
  boot.hosts.forEach(function (x) { hostColor[x.id] = x.color; });
  var eventColor = {};
  boot.events.forEach(function (x) { eventColor[x.id] = x.color; });

  var stage = $('[data-cal-view-root]', root), loading = $('[data-cal-loading]', root), errBox = $('[data-cal-error]', root), panel = $('#cal-panel'), titleEl = $('#cal-title');
  var hostSel = $('[data-cal-host]', root), evSel = $('[data-cal-event]', root);
  var narrow = window.matchMedia && matchMedia('(max-width: 719px)').matches;
  var state = { view: boot.view || (narrow ? 'dia' : 'semana'), date: boot.date, host: '', event: '', data: null, req: 0, selected: 0, justDragged: false };

  /* ---------- Fechas (cadenas Y-m-d con aritmética en UTC) ---------- */
  function parse(s) { var p = s.split('-'); return new Date(Date.UTC(+p[0], +p[1] - 1, +p[2])); }
  function fmt(dt) { return dt.toISOString().slice(0, 10); }
  function add(s, n) { var dt = parse(s); dt.setUTCDate(dt.getUTCDate() + n); return fmt(dt); }
  function dow(s) { return (parse(s).getUTCDay() + 6) % 7; }
  function monday(s) { return add(s, -dow(s)); }
  function addMonth(s, n) { var dt = parse(s.slice(0, 8) + '01'); dt.setUTCMonth(dt.getUTCMonth() + n); return fmt(dt); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function longDate(s) { var dt = parse(s); return DAYS[dow(s)] + ' ' + dt.getUTCDate() + ' de ' + MONTHS[dt.getUTCMonth()] + ' de ' + dt.getUTCFullYear(); }
  function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

  var tzFmt = new Intl.DateTimeFormat('en-GB', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
  function local(iso) {
    var o = {};
    tzFmt.formatToParts(new Date(iso)).forEach(function (p) { o[p.type] = p.value; });
    return { date: o.year + '-' + o.month + '-' + o.day, min: (+o.hour % 24) * 60 + (+o.minute) };
  }
  function clock(min) {
    var hh = Math.floor(min / 60) % 24, mm = min % 60;
    if (boot.timeFormat === '24') { return pad(hh) + ':' + pad(mm); }
    return ((hh % 12) || 12) + ':' + pad(mm) + ' ' + (hh < 12 ? 'a. m.' : 'p. m.');
  }
  function hm(min) { return pad(Math.floor(min / 60)) + ':' + pad(min % 60); }

  /* ---------- Rango visible ---------- */
  function range() {
    var s = state.date;
    if (state.view === 'dia') { return { from: s, to: add(s, 1), days: [s] }; }
    if (state.view === 'semana') { var m = monday(s), ds = []; for (var i = 0; i < 7; i++) { ds.push(add(m, i)); } return { from: m, to: add(m, 7), days: ds }; }
    var g = monday(s.slice(0, 8) + '01'), all = []; for (var k = 0; k < 42; k++) { all.push(add(g, k)); }
    return { from: g, to: add(g, 42), days: all };
  }
  function title() {
    var r = range(), dt = parse(state.date);
    if (state.view === 'dia') { return cap(longDate(state.date)); }
    if (state.view === 'mes') { return cap(MONTHS[dt.getUTCMonth()]) + ' de ' + dt.getUTCFullYear(); }
    var a = parse(r.days[0]), b = parse(r.days[6]);
    return a.getUTCMonth() === b.getUTCMonth() ? a.getUTCDate() + ' – ' + b.getUTCDate() + ' de ' + MONTHS[b.getUTCMonth()] + ' de ' + b.getUTCFullYear()
      : a.getUTCDate() + ' de ' + MONTHS[a.getUTCMonth()] + ' – ' + b.getUTCDate() + ' de ' + MONTHS[b.getUTCMonth()] + ' de ' + b.getUTCFullYear();
  }

  /* ---------- Carga ---------- */
  function load() {
    var r = range(), my = ++state.req;
    loading.hidden = false; errBox.hidden = true; stage.parentNode.setAttribute('aria-busy', 'true');
    var qs = '?from=' + r.from + '&to=' + r.to + (state.host ? '&host=' + state.host : '') + (state.event ? '&event=' + state.event : '');
    return Ap.fetchJson(boot.urls.data + qs).then(function (data) {
      if (my !== state.req) { return; }
      state.data = data; loading.hidden = true; stage.parentNode.setAttribute('aria-busy', 'false');
      draw();
    }, function (err) {
      if (my !== state.req) { return; }
      loading.hidden = true; stage.parentNode.setAttribute('aria-busy', 'false');
      errBox.textContent = err.message; errBox.hidden = false;
      state.data = { bookings: [], timeoff: [], holidays: [] }; draw();
    });
  }

  function syncUrl() {
    try {
      var u = new URL(window.location.href);
      u.searchParams.set('vista', state.view); u.searchParams.set('fecha', state.date);
      history.replaceState(null, '', u.toString());
    } catch (e) { /* sin historial */ }
  }

  function setState(patch, reload) {
    Object.keys(patch).forEach(function (k) { state[k] = patch[k]; });
    titleEl.textContent = title();
    $$('[data-cal-view]', root).forEach(function (b) { var on = b.getAttribute('data-cal-view') === state.view; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', on ? 'true' : 'false'); b.tabIndex = on ? 0 : -1; });
    syncUrl(); closePanel();
    if (reload !== false) { load(); }
  }

  /* ---------- Datos por día ---------- */
  function bookingsByDay() {
    var map = {};
    (state.data ? state.data.bookings : []).forEach(function (b) {
      var s = local(b.start), e = local(b.end);
      var item = { b: b, date: s.date, startMin: s.min, endMin: e.date === s.date ? Math.max(e.min, s.min + 15) : 24 * 60 };
      (map[s.date] = map[s.date] || []).push(item);
    });
    return map;
  }
  function colorOf(b) { return hostColor[b.host_id] || eventColor[b.event_id] || '#C9A050'; }
  function label(b, startMin) { return clock(startMin) + ', ' + b.guest + ', ' + b.event_name + ', ' + b.status_label; }
  function holidayOf(date) { var f = (state.data.holidays || []).filter(function (x) { return x.date === date; })[0]; return f || null; }

  /* ---------- Dibujo ---------- */
  function draw() {
    stage.textContent = '';
    if (!state.data) { return; }
    if (state.view === 'mes') { drawMonth(); } else { drawGrid(); }
    var n = state.data.bookings.length;
    if (!n && errBox.hidden) { stage.appendChild(h('p', { class: 'cal-empty muted', text: 'No hay citas en este periodo. Haz clic en un hueco del calendario o pulsa N para agendar una.' })); }
  }

  function drawGrid() {
    var r = range(), map = bookingsByDay(), cols = r.days.length;
    var startH = boot.hours.start, endH = boot.hours.end;
    r.days.forEach(function (day) { (map[day] || []).forEach(function (it) { startH = Math.min(startH, Math.floor(it.startMin / 60)); endH = Math.max(endH, Math.ceil(it.endMin / 60)); }); });
    endH = Math.min(24, endH);
    var startMin = startH * 60, total = (endH - startH) * HH;
    var scroll = h('div', { class: 'cal-scroll' });
    var grid = h('div', { class: 'cal-grid cal-cols-' + cols, role: 'group', 'aria-label': 'Calendario de ' + title() });
    scroll.appendChild(grid);

    var head = h('div', { class: 'cal-head' }, [h('span', { class: 'cal-corner' })]);
    r.days.forEach(function (day) {
      var dt = parse(day), hol = holidayOf(day), today = day === boot.today;
      var btn = h('button', { type: 'button', class: 'cal-dayhead' + (today ? ' is-today' : ''), 'aria-label': cap(longDate(day)) + (hol ? ', feriado: ' + hol.name : ''), onclick: function () { setState({ view: 'dia', date: day }); } }, [
        h('span', { class: 'cal-dow', text: DAYS[dow(day)].slice(0, 3) }),
        h('span', { class: 'cal-dom serif', text: String(dt.getUTCDate()) }),
        hol ? h('span', { class: 'cal-hol', text: hol.half ? 'Medio día' : 'Feriado', title: hol.name }) : null
      ]);
      head.appendChild(btn);
    });
    grid.appendChild(head);

    var body = h('div', { class: 'cal-body' });
    body.style.setProperty('--hh', HH + 'px');
    body.style.height = total + 'px';
    var gutter = h('div', { class: 'cal-gutter', 'aria-hidden': 'true' });
    for (var hr = startH; hr < endH; hr++) { var lab = h('span', { class: 'cal-hr mono', text: clock(hr * 60) }); lab.style.top = ((hr - startH) * HH) + 'px'; gutter.appendChild(lab); }
    body.appendChild(gutter);

    var nowInfo = local(new Date().toISOString());
    r.days.forEach(function (day) {
      var col = h('div', { class: 'cal-col' + (day === boot.today ? ' is-today' : '') + (holidayOf(day) && !holidayOf(day).half ? ' is-closed' : ''), 'data-date': day });
      col.addEventListener('click', function (e) {
        if (e.target !== col && !e.target.classList.contains('cal-off')) { return; }
        var y = e.clientY - col.getBoundingClientRect().top, min = startMin + Math.floor(y / HH * 60 / 30) * 30;
        window.location.href = boot.urls.new + '?fecha=' + day + '&hora=' + hm(min) + (state.host ? '&anfitrion=' + state.host : '');
      });
      // ausencias
      (state.data.timeoff || []).forEach(function (t) {
        if (state.host && t.host_id && String(t.host_id) !== state.host) { return; }
        var a = local(t.start), b = local(t.end);
        if (a.date > day || b.date < day) { return; }
        var s0 = a.date === day ? a.min : 0, e0 = b.date === day ? b.min : 1440;
        var top = Math.max(0, (s0 - startMin) / 60 * HH), hgt = Math.min(total, (e0 - startMin) / 60 * HH) - top;
        if (hgt <= 0) { return; }
        var off = h('div', { class: 'cal-off', title: t.reason || 'Ausencia' });
        off.style.top = top + 'px'; off.style.height = hgt + 'px'; col.appendChild(off);
      });
      // citas con reparto de columnas para las que se traslapan
      var items = (map[day] || []).slice().sort(function (x, y) { return x.startMin - y.startMin || y.endMin - x.endMin; });
      var cluster = [], clusterEnd = -1;
      function flush() {
        var lanes = [];
        cluster.forEach(function (it) {
          var placed = false;
          for (var i = 0; i < lanes.length; i++) { if (lanes[i] <= it.startMin) { lanes[i] = it.endMin; it.lane = i; placed = true; break; } }
          if (!placed) { it.lane = lanes.length; lanes.push(it.endMin); }
        });
        cluster.forEach(function (it) { it.lanes = lanes.length; });
        cluster = []; clusterEnd = -1;
      }
      items.forEach(function (it) { if (cluster.length && it.startMin >= clusterEnd) { flush(); } cluster.push(it); clusterEnd = Math.max(clusterEnd, it.endMin); });
      flush();
      items.forEach(function (it) { col.appendChild(eventBlock(it, startMin, col)); });
      if (day === boot.today && nowInfo.min >= startMin && nowInfo.min <= endH * 60) {
        var nl = h('div', { class: 'cal-now', 'aria-hidden': 'true' }); nl.style.top = ((nowInfo.min - startMin) / 60 * HH) + 'px'; col.appendChild(nl);
      }
      body.appendChild(col);
    });
    grid.appendChild(body);
    stage.appendChild(scroll);
    // llevar la vista a la primera cita o a la hora actual
    var firstMin = nowInfo.min;
    var all = []; r.days.forEach(function (x) { all = all.concat(map[x] || []); });
    if (all.length) { firstMin = Math.min.apply(null, all.map(function (x) { return x.startMin; })); }
    var st = Math.max(0, (firstMin - startMin) / 60 * HH - HH);
    var sc = $('.cal-scroll', stage); if (sc && sc.scrollHeight > sc.clientHeight) { sc.scrollTop = st; }
  }

  function eventBlock(it, startMin, col) {
    var b = it.b, top = (it.startMin - startMin) / 60 * HH, hgt = Math.max(24, (it.endMin - it.startMin) / 60 * HH - 2);
    var el = h('button', { type: 'button', class: 'cal-ev st-' + b.status + (state.selected === b.id ? ' is-selected' : ''), 'data-id': String(b.id), 'aria-label': label(b, it.startMin) }, [
      h('span', { class: 'cal-ev-time mono', text: clock(it.startMin) }),
      h('span', { class: 'cal-ev-title', text: b.guest }),
      hgt > 44 ? h('span', { class: 'cal-ev-sub', text: b.event_name }) : null
    ]);
    el.style.top = top + 'px'; el.style.height = hgt + 'px';
    el.style.left = (it.lane / it.lanes * 100) + '%'; el.style.width = (100 / it.lanes) + '%';
    el.style.setProperty('--c', colorOf(b));
    el.addEventListener('click', function () { if (state.justDragged) { return; } openPanel(b, el); });
    if (b.movable) { enableDrag(el, b, it, startMin); }
    return el;
  }

  function drawMonth() {
    var r = range(), map = bookingsByDay(), cur = parse(state.date).getUTCMonth();
    var wrap = h('div', { class: 'cal-month', role: 'grid', 'aria-label': 'Calendario de ' + title() });
    var head = h('div', { class: 'cal-mhead', role: 'row' });
    DAYS.forEach(function (n) { head.appendChild(h('span', { role: 'columnheader', text: n.slice(0, 3) })); });
    wrap.appendChild(head);
    var gridEl = h('div', { class: 'cal-mgrid' });
    r.days.forEach(function (day) {
      var dt = parse(day), list = (map[day] || []).sort(function (a, b) { return a.startMin - b.startMin; }), hol = holidayOf(day);
      var cell = h('div', { class: 'cal-cell' + (dt.getUTCMonth() !== cur ? ' is-out' : '') + (day === boot.today ? ' is-today' : '') + (hol && !hol.half ? ' is-closed' : ''), role: 'gridcell' }, [
        h('div', { class: 'cal-cell-top' }, [
          h('button', { type: 'button', class: 'cal-num', 'aria-label': cap(longDate(day)) + (list.length ? ', ' + list.length + (list.length === 1 ? ' cita' : ' citas') : '') + (hol ? ', feriado: ' + hol.name : ''), onclick: function () { setState({ view: 'dia', date: day }); }, text: String(dt.getUTCDate()) }),
          hol ? h('span', { class: 'cal-hol', text: hol.half ? 'Medio día' : 'Feriado', title: hol.name }) : null,
          h('a', { class: 'cal-add', href: boot.urls.new + '?fecha=' + day + (state.host ? '&anfitrion=' + state.host : ''), 'aria-label': 'Nueva cita el ' + longDate(day) }, [A.icon('plus')])
        ])
      ]);
      list.slice(0, 3).forEach(function (it) {
        var chip = h('button', { type: 'button', class: 'cal-chip st-' + it.b.status, 'aria-label': label(it.b, it.startMin) }, [h('span', { class: 'mono', text: clock(it.startMin) }), ' ' + it.b.guest]);
        chip.style.setProperty('--c', colorOf(it.b));
        chip.addEventListener('click', function () { openPanel(it.b, chip); });
        cell.appendChild(chip);
      });
      if (list.length > 3) { cell.appendChild(h('button', { type: 'button', class: 'cal-more', text: '+' + (list.length - 3) + ' más', onclick: function () { setState({ view: 'dia', date: day }); } })); }
      gridEl.appendChild(cell);
    });
    wrap.appendChild(gridEl);
    stage.appendChild(wrap);
  }

  /* ---------- Arrastrar para mover ---------- */
  function enableDrag(el, b, it, startMin) {
    el.addEventListener('pointerdown', function (e) {
      if (e.button !== 0 || e.pointerType === 'touch') { return; }
      var sx = e.clientX, sy = e.clientY, moved = false, cols = $$('.cal-col', stage), from = el.parentNode, target = from, dMin = 0;
      var fromRect = from.getBoundingClientRect();
      function onMove(ev) {
        var dx = ev.clientX - sx, dy = ev.clientY - sy;
        if (!moved && Math.hypot(dx, dy) < 6) { return; }
        if (!moved) { moved = true; el.classList.add('is-dragging'); el.setPointerCapture && el.setPointerCapture(e.pointerId); }
        dMin = Math.round(dy / HH * 60 / SNAP) * SNAP;
        target = from;
        cols.forEach(function (c) { var r = c.getBoundingClientRect(); if (ev.clientX >= r.left && ev.clientX < r.right) { target = c; } });
        var shiftX = target.getBoundingClientRect().left - fromRect.left;
        el.style.transform = 'translate(' + shiftX + 'px,' + (dMin / 60 * HH) + 'px)';
        el.dataset.drop = clock(Math.max(0, it.startMin + dMin));
      }
      function end() {
        d.removeEventListener('pointermove', onMove); d.removeEventListener('pointerup', end); d.removeEventListener('pointercancel', end);
        if (!moved) { return; }
        el.classList.remove('is-dragging'); el.style.transform = ''; delete el.dataset.drop;
        state.justDragged = true; setTimeout(function () { state.justDragged = false; }, 60);
        var newMin = it.startMin + dMin, newDate = target.getAttribute('data-date');
        if (newMin < 0 || newMin >= 1440) { A.say('Esa hora queda fuera del día.', 'warn'); return; }
        if (newDate === it.date && dMin === 0) { return; }
        move(b, newDate, newMin, false);
      }
      d.addEventListener('pointermove', onMove); d.addEventListener('pointerup', end); d.addEventListener('pointercancel', end);
    });
  }

  function move(b, date, min, force) {
    return Ap.fetchJson(boot.urls.booking + '/' + b.id + '/mover', { method: 'POST', body: { start_local: date + ' ' + hm(min), force: force ? 1 : 0 } }).then(function (res) {
      Ap.toast(res.message || 'Cita reprogramada.', 'ok'); closePanel(); load();
    }, function (err) {
      if (err.data && err.data.can_force) {
        Ap.confirm(err.message + ' ¿Moverla de todos modos, fuera de horario?', { title: 'Horario no disponible', label: 'Mover igualmente' }).then(function (yes) { if (yes) { move(b, date, min, true); } else { draw(); } });
      } else { Ap.toast(err.message, 'err'); draw(); }
    });
  }

  /* ---------- Panel lateral ---------- */
  function closePanel() {
    if (panel.hidden) { return; }
    panel.hidden = true; state.selected = 0; panel.textContent = '';
    root.querySelector('.cal-layout').classList.remove('has-panel');
    $$('.cal-ev.is-selected', stage).forEach(function (x) { x.classList.remove('is-selected'); });
  }
  function act(b, path, body, okMsg) {
    return Ap.fetchJson(boot.urls.booking + '/' + b.id + '/' + path, { method: 'POST', body: body }).then(function (res) { Ap.toast(res.message || okMsg, 'ok'); closePanel(); load(); }, function (err) { Ap.toast(err.message, 'err'); });
  }
  function openPanel(b, opener) {
    state.selected = b.id; panel._opener = opener;
    $$('.cal-ev', stage).forEach(function (x) { x.classList.toggle('is-selected', x.getAttribute('data-id') === String(b.id)); });
    var s = local(b.start), e = local(b.end), digits = (b.phone || '').replace(/\D/g, '');
    var started = new Date(b.start).getTime() <= Date.now();
    panel.textContent = '';
    var acts = h('div', { class: 'cal-actions stack' });
    if (b.status === 'pending') {
      acts.appendChild(h('button', { type: 'button', class: 'btn btn-gold', onclick: function () { act(b, 'estado', { status: 'confirmed' }, 'Cita aprobada.'); } }, [A.icon('check'), 'Aprobar']));
      acts.appendChild(h('button', { type: 'button', class: 'btn btn-outline', onclick: function () { Ap.confirm('¿Rechazar esta cita? Se avisará a la persona.', { label: 'Rechazar', danger: true }).then(function (y) { if (y) { act(b, 'estado', { status: 'rejected' }, 'Cita rechazada.'); } }); } }, [A.icon('x'), 'Rechazar']));
    }
    if (b.status === 'confirmed' && started) {
      acts.appendChild(h('button', { type: 'button', class: 'btn btn-gold', onclick: function () { act(b, 'estado', { status: 'completed' }, 'Cita completada.'); } }, [A.icon('check'), 'Marcar completada']));
      acts.appendChild(h('button', { type: 'button', class: 'btn btn-outline', onclick: function () { act(b, 'estado', { status: 'no_show' }, 'Marcada como no asistió.'); } }, [A.icon('alert'), 'No asistió']));
    }
    if (b.movable && window.ApBookings) {
      acts.appendChild(h('button', { type: 'button', class: 'btn btn-outline', onclick: function () { window.ApBookings.openReschedule({ id: b.id, event: b.event_id, duration: Math.round((new Date(b.end) - new Date(b.start)) / 60000), host: b.host_id, name: b.guest, date: s.date, time: hm(s.min) }); } }, [A.icon('refresh'), 'Reprogramar']));
      acts.appendChild(h('button', { type: 'button', class: 'btn btn-danger', onclick: function () { Ap.confirm('¿Cancelar esta cita? Se liberará el horario.', { label: 'Cancelar cita', cancel: 'Volver', danger: true }).then(function (y) { if (y) { act(b, 'cancelar', { reason: 'Cancelada por el negocio' }, 'Cita cancelada.'); } }); } }, [A.icon('x'), 'Cancelar cita']));
    }
    var dl = h('dl', { class: 'dl' }, [
      h('div', {}, [h('dt', { text: 'Fecha' }), h('dd', { text: cap(longDate(s.date)) })]),
      h('div', {}, [h('dt', { text: 'Hora' }), h('dd', { class: 'mono', text: clock(s.min) + ' – ' + clock(e.min) })]),
      h('div', {}, [h('dt', { text: 'Tipo de cita' }), h('dd', { text: b.event_name })]),
      h('div', {}, [h('dt', { text: 'Atiende' }), h('dd', { text: b.host_name })]),
      b.phone ? h('div', {}, [h('dt', { text: 'Teléfono' }), h('dd', {}, [h('a', { href: 'tel:+' + digits, class: 'mono', text: b.phone })])]) : null,
      b.email ? h('div', {}, [h('dt', { text: 'Correo' }), h('dd', {}, [h('a', { href: 'mailto:' + b.email, text: b.email })])]) : null,
      b.total ? h('div', {}, [h('dt', { text: 'Importe' }), h('dd', { class: 'mono', text: b.total })]) : null,
      b.note ? h('div', {}, [h('dt', { text: 'Nota interna' }), h('dd', { class: 'pre', text: b.note })]) : null
    ]);
    var head = h('div', { class: 'card-head row row-between' }, [
      h('h2', { class: 'serif', text: b.guest }),
      h('button', { type: 'button', class: 'btn btn-ghost btn-icon', 'aria-label': 'Cerrar detalle', onclick: function () { var o = panel._opener; closePanel(); if (o && o.focus) { o.focus(); } } }, [A.icon('x')])
    ]);
    panel.appendChild(head);
    panel.appendChild(h('div', { class: 'card-body stack' }, [
      h('p', {}, [h('span', { class: 'badge st-badge-' + b.status, text: b.status_label })]),
      dl,
      acts,
      h('div', { class: 'row row-wrap gap-2' }, [
        h('a', { class: 'btn btn-ghost btn-sm', href: boot.urls.booking + '/' + b.id }, [A.icon('arrow-right'), 'Ver detalle completo']),
        digits ? h('a', { class: 'btn btn-ghost btn-sm', href: 'https://wa.me/' + digits, target: '_blank', rel: 'noopener noreferrer' }, [A.icon('whatsapp'), 'WhatsApp']) : null
      ])
    ]));
    panel.hidden = false;
    root.querySelector('.cal-layout').classList.add('has-panel');
    panel.focus({ preventScroll: false });
  }

  /* ---------- Controles y atajos ---------- */
  function step(dir) {
    if (state.view === 'dia') { setState({ date: add(state.date, dir) }); }
    else if (state.view === 'semana') { setState({ date: add(state.date, dir * 7) }); }
    else { setState({ date: addMonth(state.date, dir) }); }
  }
  $('[data-cal-prev]', root).addEventListener('click', function () { step(-1); });
  $('[data-cal-next]', root).addEventListener('click', function () { step(1); });
  $('[data-cal-today]', root).addEventListener('click', function () { setState({ date: boot.today }); });
  $$('[data-cal-view]', root).forEach(function (b) { b.addEventListener('click', function () { setState({ view: b.getAttribute('data-cal-view') }); }); });
  if (hostSel) { hostSel.addEventListener('change', function () { setState({ host: hostSel.value }); }); }
  if (evSel) { evSel.addEventListener('change', function () { setState({ event: evSel.value }); }); }
  $('.cal-views', root).addEventListener('keydown', function (e) {
    var tabs = $$('[data-cal-view]', root), i = tabs.indexOf(d.activeElement);
    if (i < 0 || (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft')) { return; }
    e.preventDefault(); e.stopPropagation();
    var n = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length]; n.focus(); setState({ view: n.getAttribute('data-cal-view') });
  });
  d.addEventListener('keydown', function (e) {
    if (e.ctrlKey || e.metaKey || e.altKey || A.typing(e.target) || d.querySelector('dialog[open]') || d.body.classList.contains('cmdk-open')) { return; }
    var k = e.key, lower = k.toLowerCase();
    if (k === 'Escape' && !panel.hidden) { var o = panel._opener; closePanel(); if (o && o.focus) { o.focus(); } return; }
    if (e.target.closest && e.target.closest('.cal-views')) { if (k === 'ArrowLeft' || k === 'ArrowRight') { return; } }
    if (lower === 't') { e.preventDefault(); setState({ date: boot.today }); }
    else if (k === 'ArrowLeft') { e.preventDefault(); step(-1); }
    else if (k === 'ArrowRight') { e.preventDefault(); step(1); }
    else if (lower === 'd') { setState({ view: 'dia' }); }
    else if (lower === 's') { setState({ view: 'semana' }); }
    else if (lower === 'm') { setState({ view: 'mes' }); }
    else if (lower === 'n') { e.preventDefault(); window.location.href = boot.urls.new + '?fecha=' + state.date + (state.host ? '&anfitrion=' + state.host : ''); }
  });
  d.addEventListener('ap:rescheduled', function (e) { e.preventDefault(); closePanel(); load(); });

  setState({}, true);
})();
