/* Horarios, ausencias, feriados, recursos y calendarios externos: comportamientos del panel. */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  var DAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

  function fieldId(input) {
    input.id = input.name.replace(/[^a-z0-9]+/gi, '-');
    var lab = input.previousElementSibling;
    while (lab && lab.tagName !== 'LABEL') { lab = lab.previousElementSibling; }
    if (lab) { lab.setAttribute('for', input.id); }
  }

  function csrf() {
    var m = $('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  /* ------------------------------------------------------------ horarios */
  function initSchedule(form) {
    var blockTpl = $('#p2-block-tpl');
    var exTpl = $('#p2-ex-tpl');
    var exList = $('[data-ex-list]', form);
    var exEmpty = $('[data-ex-empty]', form);

    function addBlock(container, prefix, data) {
      var wrap = document.createElement('div');
      wrap.innerHTML = blockTpl.innerHTML.replace(/__P__/g, prefix);
      var b = wrap.firstElementChild;
      var ins = $$('input', b);
      ins[0].value = data.start;
      ins[1].value = data.end;
      ins.forEach(fieldId);
      container.appendChild(b);
      return b;
    }
    function dayState(day) {
      var has = $$('[data-block]', day).length > 0;
      $('[data-closed]', day).hidden = has;
    }
    function nextEnd(day) {
      var last = $$('[data-block]', day).pop();
      if (!last) { return { start: '09:00', end: '17:00' }; }
      var e = $$('input', last)[1].value || '17:00';
      var h = parseInt(e.slice(0, 2), 10);
      if (h >= 21) { return { start: e, end: '23:59' }; }
      return { start: e, end: String(Math.min(h + 4, 23)).padStart(2, '0') + e.slice(2) };
    }

    form.addEventListener('click', function (ev) {
      var t = ev.target.closest('button');
      if (!t) { return; }
      var day = t.closest('[data-day]');
      if (t.hasAttribute('data-add-block')) {
        var n = parseInt(t.getAttribute('data-next'), 10);
        t.setAttribute('data-next', String(n + 1));
        var b = addBlock($('[data-blocks]', day), 'rules[' + day.getAttribute('data-day') + '][' + n + ']', nextEnd(day));
        dayState(day);
        $$('input', b)[0].focus();
        drawPreview();
      } else if (t.hasAttribute('data-block-remove')) {
        var blk = t.closest('[data-block]');
        var dd = t.closest('[data-day]');
        blk.remove();
        if (dd) { dayState(dd); }
        drawPreview();
        drawCal();
      } else if (t.hasAttribute('data-copy-apply')) {
        var targets = $$('[data-copy-to]:checked', day).map(function (c) { return c.value; });
        var src = $$('[data-block]', day).map(function (bl) { var i = $$('input', bl); return { start: i[0].value, end: i[1].value }; });
        targets.forEach(function (d) {
          var dest = $('[data-day="' + d + '"]', form);
          var cont = $('[data-blocks]', dest);
          cont.innerHTML = '';
          src.forEach(function (blk2, k) { addBlock(cont, 'rules[' + d + '][' + k + ']', blk2); });
          $('[data-add-block]', dest).setAttribute('data-next', String(src.length));
          dayState(dest);
        });
        var det = t.closest('details');
        if (det) { det.open = false; }
        $$('[data-copy-to]', day).forEach(function (c) { c.checked = false; });
        drawPreview();
        drawCal();
      } else if (t.hasAttribute('data-ex-add')) {
        addEx('');
      } else if (t.hasAttribute('data-ex-remove')) {
        t.closest('[data-ex-row]').remove();
        exState();
        drawCal();
      } else if (t.hasAttribute('data-ex-addblock')) {
        var row = t.closest('[data-ex-row]');
        var x = $('[data-ex-date]', row).name.match(/^ex\[([^\]]+)\]/)[1];
        var k = parseInt(t.getAttribute('data-next'), 10);
        t.setAttribute('data-next', String(k + 1));
        var nb = addBlock($('[data-blocks]', row), 'ex[' + x + '][blocks][' + k + ']', { start: '09:00', end: '13:00' });
        $$('input', nb)[0].focus();
      }
    });

    form.addEventListener('input', function (ev) {
      if (ev.target.type === 'time') { drawPreview(); }
      if (ev.target.hasAttribute('data-ex-date')) { drawCal(); }
    });
    form.addEventListener('change', function (ev) {
      if (ev.target.hasAttribute('data-ex-open')) {
        var row = ev.target.closest('[data-ex-row]');
        var open = ev.target.value === '1';
        $('[data-ex-blocks]', row).hidden = !open;
        var cont = $('[data-blocks]', row);
        if (open && !$$('[data-block]', cont).length) { $('[data-ex-addblock]', row).click(); }
      }
    });

    function exState() { exEmpty.hidden = $$('[data-ex-row]', exList).length > 0; }
    function addEx(date) {
      var btn = $('[data-ex-add]', form);
      var n = parseInt(btn.getAttribute('data-next'), 10);
      btn.setAttribute('data-next', String(n + 1));
      var wrap = document.createElement('div');
      wrap.innerHTML = exTpl.innerHTML.replace(/__X__/g, String(n));
      var row = wrap.firstElementChild;
      $('[data-ex-date]', row).value = date;
      exList.appendChild(row);
      exState();
      drawCal();
      $('[data-ex-date]', row).focus();
      return row;
    }

    /* Vista previa semanal */
    var prev = $('[data-p2-weekprev]', form);
    var FROM = 6 * 60;
    var TO = 22 * 60;
    function mins(v) { var p = String(v).split(':'); return parseInt(p[0], 10) * 60 + parseInt(p[1] || '0', 10); }
    function drawPreview() {
      prev.innerHTML = '';
      $$('[data-day]', form).forEach(function (day) {
        var col = document.createElement('div');
        col.className = 'p2-pw-col';
        var lab = document.createElement('span');
        lab.className = 'p2-pw-label';
        lab.textContent = DAYS[parseInt(day.getAttribute('data-day'), 10) - 1];
        var track = document.createElement('div');
        track.className = 'p2-pw-track';
        $$('[data-block]', day).forEach(function (bl) {
          var i = $$('input', bl);
          if (!i[0].value || !i[1].value) { return; }
          var s = Math.max(FROM, mins(i[0].value));
          var e = Math.min(TO, mins(i[1].value));
          if (e <= s) { return; }
          var bar = document.createElement('span');
          bar.className = 'p2-pw-bar';
          bar.style.setProperty('--t', ((s - FROM) / (TO - FROM) * 100).toFixed(2));
          bar.style.setProperty('--h', ((e - s) / (TO - FROM) * 100).toFixed(2));
          track.appendChild(bar);
        });
        col.appendChild(track);
        col.appendChild(lab);
        prev.appendChild(col);
      });
    }

    /* Calendario para elegir fechas */
    var cal = $('[data-p2-cal]', form);
    var view = new Date();
    view = new Date(Date.UTC(view.getFullYear(), view.getMonth(), 1));
    function pad(n) { return String(n).padStart(2, '0'); }
    function drawCal() {
      var existing = {};
      $$('[data-ex-date]', form).forEach(function (i) { if (i.value) { existing[i.value] = true; } });
      var openDays = {};
      $$('[data-day]', form).forEach(function (d) { if ($$('[data-block]', d).length) { openDays[d.getAttribute('data-day')] = true; } });
      var y = view.getUTCFullYear();
      var m = view.getUTCMonth();
      var first = (new Date(Date.UTC(y, m, 1)).getUTCDay() + 6) % 7;
      var count = new Date(Date.UTC(y, m + 1, 0)).getUTCDate();
      var now = new Date();
      var today = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
      var html = '<div class="p2-cal-head"><button class="btn btn-ghost btn-icon btn-sm" type="button" data-cal-prev aria-label="Mes anterior">‹</button>' +
        '<strong aria-live="polite">' + MONTHS[m] + ' ' + y + '</strong>' +
        '<button class="btn btn-ghost btn-icon btn-sm" type="button" data-cal-next aria-label="Mes siguiente">›</button></div><div class="p2-cal-grid" role="grid">';
      DAYS.forEach(function (d) { html += '<span class="p2-cal-dow" role="columnheader">' + d.slice(0, 2) + '</span>'; });
      for (var i = 0; i < first; i++) { html += '<span></span>'; }
      for (var d = 1; d <= count; d++) {
        var iso = y + '-' + pad(m + 1) + '-' + pad(d);
        var wd = ((first + d - 1) % 7) + 1;
        var cls = 'p2-cal-day' + (existing[iso] ? ' is-ex' : '') + (!openDays[wd] ? ' is-off' : '') + (iso === today ? ' is-today' : '');
        html += '<button type="button" class="' + cls + '" data-cal-day="' + iso + '" aria-label="' + d + ' de ' + MONTHS[m] + (existing[iso] ? ', con excepción' : '') + '"' + (existing[iso] ? ' aria-pressed="true"' : '') + '>' + d + '</button>';
      }
      cal.innerHTML = html + '</div>';
    }
    cal.addEventListener('click', function (ev) {
      var b = ev.target.closest('button');
      if (!b) { return; }
      if (b.hasAttribute('data-cal-prev')) { view = new Date(Date.UTC(view.getUTCFullYear(), view.getUTCMonth() - 1, 1)); drawCal(); return; }
      if (b.hasAttribute('data-cal-next')) { view = new Date(Date.UTC(view.getUTCFullYear(), view.getUTCMonth() + 1, 1)); drawCal(); return; }
      var iso = b.getAttribute('data-cal-day');
      if (!iso) { return; }
      var found = $$('[data-ex-date]', form).filter(function (i) { return i.value === iso; })[0];
      if (found) { found.focus(); return; }
      addEx(iso);
      var sel = $('[data-cal-day="' + iso + '"]', cal);
      if (sel) { sel.focus(); }
    });
    $$('[data-day]', form).forEach(dayState);
    drawPreview();
    drawCal();
    exState();
  }

  /* ----------------------------------------- diálogos con datos (editar) */
  function initDialogs() {
    function open(dlg) {
      if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
    }
    document.addEventListener('click', function (ev) {
      var t = ev.target.closest('[data-fill-dialog],[data-p2-open],[data-p2-close]');
      if (!t) { return; }
      if (t.hasAttribute('data-p2-close')) {
        var dd = t.closest('dialog');
        if (dd) { if (dd.close) { dd.close(); } else { dd.removeAttribute('open'); } }
        return;
      }
      if (t.hasAttribute('data-p2-open')) {
        var d0 = $(t.getAttribute('data-p2-open'));
        if (d0) { open(d0); var f0 = $('input:not([type=hidden]),select,textarea', d0); if (f0) { f0.focus(); } }
        return;
      }
      var dlg = $(t.getAttribute('data-fill-dialog'));
      if (!dlg) { return; }
      var data = {};
      try { data = JSON.parse(t.getAttribute('data-fill') || '{}'); } catch (e) { data = {}; }
      var form = $('form', dlg);
      $$('input,select,textarea', form).forEach(function (f) {
        if (!f.name || f.name === '_csrf') { return; }
        var v = Object.prototype.hasOwnProperty.call(data, f.name) ? data[f.name] : (f.getAttribute('data-default') || '');
        if (f.type === 'checkbox') { f.checked = String(v) === '1'; } else { f.value = v; }
      });
      var title = $('[data-dialog-title]', dlg);
      if (title && t.getAttribute('data-title')) { title.textContent = t.getAttribute('data-title'); }
      open(dlg);
      var first = $('input:not([type=hidden]):not([type=checkbox]),select', form);
      if (first) { first.focus(); }
    });
  }

  /* ----------------------------------- ausencias: día completo / horas */
  function initTimeOff() {
    var f = $('[data-p2-timeoff]');
    if (!f) { return; }
    var all = $('[name=all_day]', f);
    function sync() { $$('[data-time-only]', f).forEach(function (n) { n.hidden = all.checked; }); }
    all.addEventListener('change', sync);
    sync();
  }

  /* --------------------------- calendarios externos: probar un enlace */
  function initCalendars() {
    $$('[data-cal-test]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var form = btn.closest('form');
        var url = $('[name=url]', form).value.trim();
        var out = $('[data-test-result]', form);
        if (!url) { out.className = 'alert alert-warn'; out.textContent = 'Pega primero el enlace del calendario.'; out.hidden = false; return; }
        btn.disabled = true;
        out.className = 'alert alert-info';
        out.textContent = 'Probando el enlace…';
        out.hidden = false;
        var body = new FormData();
        body.append('url', url);
        body.append('_csrf', csrf());
        fetch(btn.getAttribute('data-cal-test'), { method: 'POST', body: body, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf() }, credentials: 'same-origin' })
          .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'No pudimos leer la respuesta.' }; }); })
          .then(function (j) {
            out.className = 'alert ' + (j.ok ? 'alert-ok' : 'alert-err');
            out.textContent = j.ok ? (j.message || 'El enlace funciona.') : (j.error || 'No pudimos leer ese enlace.');
          })
          .catch(function () { out.className = 'alert alert-err'; out.textContent = 'No hubo conexión con el servidor. Inténtalo de nuevo.'; })
          .then(function () { btn.disabled = false; });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var s = $('[data-p2-sched]');
    if (s) { initSchedule(s); }
    initDialogs();
    initTimeOff();
    initCalendars();
  });
}());
