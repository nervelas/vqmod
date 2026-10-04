/* Agenda Premium · citas: formulario de cita manual, selector de horarios libres y reprogramación. */
(function () {
  'use strict';
  var d = document, A = window.A1;
  if (!A) { return; }
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var h = A.h;

  /* ---------- Horarios libres (compartido por el formulario y el reprogramador) ---------- */
  function loadSlots(slotsUrl, params, box, stateEl, picked, onPick) {
    box.textContent = '';
    if (!params.evento || !params.fecha) { stateEl.textContent = 'Elige el tipo de cita y la fecha para ver los horarios disponibles.'; return Promise.resolve([]); }
    stateEl.textContent = 'Buscando horarios libres…';
    box.setAttribute('aria-busy', 'true');
    var qs = Object.keys(params).filter(function (k) { return params[k]; }).map(function (k) { return k + '=' + encodeURIComponent(params[k]); }).join('&');
    return fetch(slotsUrl + '?' + qs, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Respuesta inesperada del servidor.' }; }); })
      .then(function (data) {
        box.removeAttribute('aria-busy');
        if (!data.ok) { stateEl.textContent = data.error || 'No pudimos cargar los horarios.'; return []; }
        var slots = (data.slots || []).filter(function (s) { return !s.past; });
        if (!slots.length) { stateEl.textContent = 'No hay horarios libres ese día. Prueba otra fecha o permite fuera de horario.'; return []; }
        stateEl.textContent = slots.length + (slots.length === 1 ? ' horario libre' : ' horarios libres') + '.';
        slots.forEach(function (s) {
          var on = picked && picked === s.local;
          var b = h('button', { type: 'button', class: 'chip mono', 'aria-pressed': on ? 'true' : 'false', text: s.label });
          b.addEventListener('click', function () {
            $$('button', box).forEach(function (x) { x.setAttribute('aria-pressed', 'false'); });
            b.setAttribute('aria-pressed', 'true');
            onPick(s);
          });
          box.appendChild(b);
        });
        return slots;
      }).catch(function () { box.removeAttribute('aria-busy'); stateEl.textContent = 'No pudimos conectar para buscar horarios. Revisa tu conexión.'; return []; });
  }

  /* ---------- Formulario de cita manual ---------- */
  var form = $('#booking-form');
  if (form) {
    var boot = {};
    try { boot = JSON.parse($('#boot', form).textContent); } catch (e) { boot = {}; }
    var ev = $('#event_id'), dur = $('#duration'), host = $('#host_id'), fecha = $('#fecha'), hora = $('#hora'), startUtc = $('#start_utc'), force = $('#force');
    var box = $('#slots'), state = $('#slots-state'), warn = $('#force-warn');
    var slotsUrl = form.getAttribute('data-slots-url');
    var hostOpts = host ? $$('option', host).map(function (o) { return { v: o.value, t: o.textContent }; }) : [];
    var first = true;

    function fillDurations() {
      var info = boot.eventDur[ev.value];
      dur.textContent = '';
      if (!info) { dur.appendChild(h('option', { value: '', text: 'Según el tipo de cita' })); return; }
      info.list.forEach(function (m) {
        var want = first && boot.old.duration ? boot.old.duration : info.def;
        var o = h('option', { value: String(m), text: m < 60 ? m + ' min' : (Math.floor(m / 60) + ' h' + (m % 60 ? ' ' + (m % 60) + ' min' : '')) });
        if (m === want) { o.selected = true; }
        dur.appendChild(o);
      });
    }
    function fillHosts() {
      if (!host) { return; }
      var allowed = boot.eventHosts[ev.value] || [], cur = first ? String(boot.old.host || '') : host.value;
      host.textContent = '';
      hostOpts.forEach(function (o) {
        if (o.v === '' || !ev.value || allowed.indexOf(parseInt(o.v, 10)) !== -1) {
          var op = h('option', { value: o.v, text: o.t }); if (o.v === cur) { op.selected = true; } host.appendChild(op);
        }
      });
    }
    function refresh() {
      var picked = hora.value;
      loadSlots(slotsUrl, { evento: ev.value, duracion: dur.value, fecha: fecha.value, anfitrion: host ? host.value : '' }, box, state, picked, function (s) {
        startUtc.value = s.start; hora.value = s.local;
      }).then(function (slots) { lockTime(slots); });
    }
    function lockTime(slots) {
      var free = force.checked || !slots || !slots.length;
      hora.readOnly = !free;
      hora.classList.toggle('is-locked', !free);
    }
    function syncForce() { warn.hidden = !force.checked; lockTime(null); if (!force.checked) { refresh(); } }

    ev.addEventListener('change', function () { first = false; fillDurations(); fillHosts(); startUtc.value = ''; refresh(); });
    dur.addEventListener('change', function () { first = false; startUtc.value = ''; refresh(); });
    if (host) { host.addEventListener('change', function () { first = false; startUtc.value = ''; refresh(); }); }
    fecha.addEventListener('change', function () { hora.value = ''; startUtc.value = ''; refresh(); });
    hora.addEventListener('input', function () { startUtc.value = ''; });
    force.addEventListener('change', syncForce);
    fillDurations(); fillHosts(); first = false;
    warn.hidden = !force.checked;
    if (ev.value && fecha.value) { refresh(); }

    /* Cliente existente */
    var cs = $('#client-search'), chosen = $('#client-chosen'), cid = $('#client_id');
    function setClient(c) {
      cid.value = c.id; $('#name').value = c.title; $('#email').value = c.email || ''; $('#phone').value = c.phone ? c.phone.replace(/^502(\d{4})(\d{4})$/, '$1 $2') : ''; $('#nit').value = c.nit || '';
      $('[data-client-label]', chosen).textContent = 'Cliente existente: ' + c.title + '.'; chosen.hidden = false;
    }
    A.clientSearch({ input: cs, list: $('#client-results'), url: form.getAttribute('data-search-url'), onPick: setClient });
    $('[data-client-clear]', chosen).addEventListener('click', function () { cid.value = '0'; chosen.hidden = true; cs.focus(); });
    if (parseInt(cid.value, 10) > 0) { $('[data-client-label]', chosen).textContent = 'Cliente existente: ' + $('#name').value + '.'; chosen.hidden = false; }
    var err = $('#form-error'); if (err) { err.setAttribute('tabindex', '-1'); err.focus(); }
  }

  /* ---------- Reprogramar ---------- */
  var dlg = $('#reschedule-modal');
  if (dlg) {
    var rf = $('#reschedule-form', dlg), rDate = $('#rs-fecha', dlg), rTime = $('#rs-hora', dlg), rForce = $('#rs-force', dlg), rBox = $('[data-rs-slots]', dlg), rState = $('[data-rs-state]', dlg), rErr = $('[data-rs-error]', dlg), rSum = $('[data-rs-summary]', dlg);
    var cur = null;
    function rLoad() {
      loadSlots(dlg.getAttribute('data-slots-url'), { evento: cur.event, duracion: cur.duration, fecha: rDate.value, anfitrion: cur.host, excluir: cur.id }, rBox, rState, rTime.value, function (s) { rTime.value = s.local; });
    }
    function openReschedule(info) {
      cur = info; rErr.hidden = true; rForce.checked = false;
      rf.action = dlg.getAttribute('data-move-base') + '/' + info.id + '/mover';
      rSum.textContent = 'Cita de ' + (info.name || 'la persona') + '. Elige la nueva fecha y hora.';
      rDate.value = info.date; rTime.value = info.time;
      window.Ap.modal.open(dlg);
      rLoad();
    }
    rDate.addEventListener('change', function () { rTime.value = ''; rLoad(); });
    rf.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!rDate.value || !rTime.value) { rErr.textContent = 'Elige la fecha y la hora nuevas.'; rErr.hidden = false; return; }
      rErr.hidden = true;
      var btn = $('button[type=submit]', rf); btn.disabled = true;
      window.Ap.fetchJson(rf.action, { method: 'POST', body: { fecha: rDate.value, hora: rTime.value, force: rForce.checked ? 1 : 0 } }).then(function (res) {
        btn.disabled = false; window.Ap.modal.close(dlg);
        A.say(res.message || 'Cita reprogramada.', 'ok');
        var ev2 = new CustomEvent('ap:rescheduled', { detail: res, cancelable: true });
        if (d.dispatchEvent(ev2)) { setTimeout(function () { window.location.reload(); }, 600); }
      }, function (err) {
        btn.disabled = false;
        rErr.textContent = err.message + (err.data && err.data.can_force ? ' Si es una excepción, marca «Permitir fuera de horario».' : '');
        rErr.hidden = false;
      });
    });
    d.addEventListener('click', function (e) {
      var b = e.target.closest && e.target.closest('[data-reschedule]');
      if (!b) { return; }
      e.preventDefault();
      try { openReschedule(JSON.parse(b.getAttribute('data-reschedule'))); } catch (x) { /* datos inválidos */ }
    });
    window.ApBookings = { openReschedule: openReschedule };
  }
})();
