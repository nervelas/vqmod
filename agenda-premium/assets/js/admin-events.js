/* Tipos de evento: lista (enlace de un solo uso) y formulario (pestañas, vista previa, preguntas). */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function slugify(s, sep) {
    var t = String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    t = t.replace(/[^a-z0-9]+/g, sep).replace(new RegExp('^' + sep + '+|' + sep + '+$', 'g'), '');
    return t;
  }

  /* Misma regla que Str::richText del servidor (negrita, cursiva, enlaces, párrafos). */
  function richText(src) {
    var h = esc(src);
    h = h.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    h = h.replace(/(^|[^*\w])\*(?!\s)(.+?)(?!\s)\*(?![*\w])/g, '$1<em>$2</em>');
    h = h.replace(/\[([^\]]{1,120})\]\((https?:\/\/[^\s)]{1,300})\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
    return h.trim().split(/\n{2,}/).filter(Boolean).map(function (p) { return '<p>' + p.replace(/\n/g, '<br>') + '</p>'; }).join('');
  }

  /* ---------------------------------------------------------------- lista */
  function initList() {
    var dlg = $('#single-use-modal');
    if (!dlg) { return; }
    var form = $('#single-use-form');
    $$('[data-single-use]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        form.setAttribute('action', btn.getAttribute('data-single-use'));
        $('#single-use-name').textContent = '«' + (btn.getAttribute('data-name') || 'este evento') + '»';
        if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
        var d = $('#single-use-date');
        if (d) { d.focus(); }
      });
    });
    $$('[data-p2-close]', dlg).forEach(function (b) {
      b.addEventListener('click', function () { if (dlg.close) { dlg.close(); } else { dlg.removeAttribute('open'); } });
    });
  }

  /* ----------------------------------------------------------- formulario */
  function initForm(form) {
    var el = form.elements;
    var preview = $('#ev-preview');
    var currency = form.getAttribute('data-currency') || 'Q';

    /* Pestañas */
    var tablist = $('[data-p2-tabs]', form);
    var tabs = $$('[role=tab]', tablist);
    var panels = tabs.map(function (t) { return document.getElementById(t.getAttribute('aria-controls')); });
    function selectTab(i, focus) {
      tabs.forEach(function (t, k) {
        var on = k === i;
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        panels[k].hidden = !on;
      });
      if (focus) { tabs[i].focus(); }
      try { sessionStorage.setItem('p2-ev-tab', String(i)); } catch (e) { /* sin almacenamiento */ }
    }
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { selectTab(i, false); });
      t.addEventListener('keydown', function (ev) {
        var n = null;
        if (ev.key === 'ArrowRight') { n = (i + 1) % tabs.length; }
        if (ev.key === 'ArrowLeft') { n = (i + tabs.length - 1) % tabs.length; }
        if (ev.key === 'Home') { n = 0; }
        if (ev.key === 'End') { n = tabs.length - 1; }
        if (n !== null) { ev.preventDefault(); selectTab(n, true); }
      });
    });
    var start = 0;
    try { start = Math.min(tabs.length - 1, Math.max(0, parseInt(sessionStorage.getItem('p2-ev-tab') || '0', 10) || 0)); } catch (e) { start = 0; }
    if (form.querySelector('.alert-err') || document.querySelector('.p2-page > .alert-err')) { start = 0; }
    selectTab(start, false);
    form.addEventListener('invalid', function (ev) {
      var p = ev.target.closest('[role=tabpanel]');
      var i = panels.indexOf(p);
      if (i >= 0 && panels[i].hidden) { selectTab(i, false); }
    }, true);

    /* Mostrar/ocultar por tipo y modalidad */
    function checked(name) { var r = form.querySelector('input[name="' + name + '"]:checked'); return r ? r.value : ''; }
    var modeLabels = {
      in_person: ['Dirección o lugar', 'Se muestra al cliente en la confirmación y en el correo.'],
      home: ['Dirección o zona de servicio', 'Indica desde dónde sales o qué zonas cubres. Si el cliente da su dirección, pídela en una pregunta.'],
      phone: ['Indicaciones de la llamada', 'Por ejemplo, quién llama a quién y a qué número.']
    };
    function syncVisibility() {
      var kind = checked('kind');
      var mode = checked('mode');
      $$('[data-show-kind]', form).forEach(function (n) {
        n.hidden = n.getAttribute('data-show-kind').split(',').indexOf(kind) < 0;
      });
      $$('[data-show-mode]', form).forEach(function (n) {
        n.hidden = n.getAttribute('data-show-mode').split(',').indexOf(mode) < 0;
      });
      var ml = modeLabels[mode];
      if (ml) {
        $('[data-mode-label]', form).textContent = ml[0];
        $('[data-mode-hint]', form).textContent = ml[1];
      }
      var depBox = $('[data-show-deposit]', form);
      depBox.hidden = el.deposit_type.value === 'none';
      $('[data-deposit-label]', form).textContent = el.deposit_type.value === 'percent' ? 'Porcentaje del anticipo (%)' : 'Monto del anticipo (' + currency + ')';
      var g = $('[data-toggle-guests]', form);
      if (g) { $('[data-show-guests]', form).hidden = !g.checked; }
      var s = $('[data-toggle-single]', form);
      if (s) { $('[data-show-single]', form).hidden = !s.checked; }
      var hint = $('[data-hosts-hint]', form);
      if (hint) {
        hint.textContent = {
          individual: 'Elige un solo anfitrión para este evento.',
          group: 'Elige un solo anfitrión: los cupos se comparten en su horario.',
          round_robin: 'Elige dos o más anfitriones. Ajusta peso o prioridad según el modo de reparto.',
          collective: 'Elige dos o más anfitriones: todos asisten a la misma cita.'
        }[kind] || '';
      }
    }
    form.addEventListener('change', syncVisibility);
    syncVisibility();

    /* Enlace automático desde el nombre */
    var slugTouched = !!el.slug.value;
    el.slug.addEventListener('input', function () { slugTouched = true; });
    el.name.addEventListener('input', function () {
      if (!slugTouched) { el.slug.placeholder = slugify(el.name.value, '-') || 'se-genera-solo'; }
    });

    /* Color */
    $$('[data-color]', form).forEach(function (b) {
      b.addEventListener('click', function () { el.color.value = b.getAttribute('data-color'); render(); });
    });

    /* Formato de la descripción */
    var ta = el.description;
    function wrapSel(mark) {
      var s = ta.selectionStart, e2 = ta.selectionEnd, v = ta.value;
      var sel = v.slice(s, e2) || 'texto';
      ta.value = v.slice(0, s) + mark + sel + mark + v.slice(e2);
      ta.focus();
      ta.setSelectionRange(s + mark.length, s + mark.length + sel.length);
      render();
    }
    $$('[data-wrap]', form).forEach(function (b) { b.addEventListener('click', function () { wrapSel(b.getAttribute('data-wrap')); }); });
    var linkBtn = $('[data-link]', form);
    if (linkBtn) {
      linkBtn.addEventListener('click', function () {
        var s = ta.selectionStart, e2 = ta.selectionEnd, v = ta.value;
        var txt = v.slice(s, e2) || 'texto del enlace';
        var ins = '[' + txt + '](https://)';
        ta.value = v.slice(0, s) + ins + v.slice(e2);
        ta.focus();
        var pos = s + txt.length + 3;
        ta.setSelectionRange(pos, pos + 8);
        render();
      });
    }

    /* Duraciones: la sugerida siempre sale de las elegidas */
    function currentDurations() {
      var list = {};
      $$('[data-p2-durations] input:checked', form).forEach(function (c) { list[parseInt(c.value, 10)] = 1; });
      String(el.duration_extra.value).split(/[\s,;]+/).forEach(function (t) {
        var n = parseInt(t, 10);
        if (/^\d+$/.test(t) && n >= 5 && n <= 720) { list[n] = 1; }
      });
      return Object.keys(list).map(Number).sort(function (a, b) { return a - b; });
    }
    function fmtDur(m) {
      if (m < 60) { return m + ' min'; }
      return Math.floor(m / 60) + ' h' + (m % 60 ? ' ' + (m % 60) + ' min' : '');
    }
    function syncDefault() {
      var list = currentDurations();
      var sel = el.default_duration;
      var cur = parseInt(sel.value || sel.getAttribute('data-default'), 10);
      sel.innerHTML = '';
      list.forEach(function (d) {
        var o = document.createElement('option');
        o.value = String(d);
        o.textContent = fmtDur(d);
        if (d === cur) { o.selected = true; }
        sel.appendChild(o);
      });
      if (!list.length) {
        var o2 = document.createElement('option');
        o2.value = '30';
        o2.textContent = 'Elige al menos una duración';
        sel.appendChild(o2);
      }
    }
    form.addEventListener('change', function (ev) {
      if (ev.target.closest('[data-p2-durations]')) { syncDefault(); }
    });
    el.duration_extra.addEventListener('input', syncDefault);

    /* Equipo -> anfitriones */
    var team = el.team_id;
    if (team) {
      var map = {};
      try { map = JSON.parse(team.getAttribute('data-team-hosts') || '{}'); } catch (e) { map = {}; }
      team.addEventListener('change', function () {
        var ids = map[team.value] || [];
        if (!ids.length) { return; }
        $$('input[data-host-id]', form).forEach(function (c) { c.checked = ids.indexOf(parseInt(c.getAttribute('data-host-id'), 10)) >= 0; });
      });
    }

    /* ------------------------------------------------------ preguntas */
    var qlist = $('[data-p2-questions]', form);
    var qempty = $('[data-q-empty]', form);
    var qtpl = $('#p2-q-template');
    var nextIdx = parseInt(qlist.getAttribute('data-next') || '0', 10);

    function rows() { return $$('[data-q-row]', qlist).filter(function (r) { return !r.hidden; }); }
    function rowName(r) {
      var n = $('[data-q-name]', r).value.trim();
      if (!n) { n = slugify($('input[id$="-label"]', r).value, '_'); }
      return n;
    }
    function syncConditions() {
      var all = rows();
      all.forEach(function (r) {
        var sel = $('[data-q-cond]', r);
        var current = sel.value || sel.getAttribute('data-value') || '';
        sel.innerHTML = '';
        var o0 = document.createElement('option');
        o0.value = '';
        o0.textContent = 'Siempre se muestra';
        sel.appendChild(o0);
        var mine = rowName(r);
        var found = false;
        all.forEach(function (other) {
          if (other === r) { return; }
          var n = rowName(other);
          if (!n) { return; }
          var o = document.createElement('option');
          o.value = n;
          o.textContent = ($('input[id$="-label"]', other).value || n) + ' (' + n + ')';
          if (n === current) { o.selected = true; found = true; }
          sel.appendChild(o);
        });
        if (current && !found && current !== mine) {
          var oc = document.createElement('option');
          oc.value = current;
          oc.textContent = current + ' (no encontrada)';
          oc.selected = true;
          sel.appendChild(oc);
        }
        sel.setAttribute('data-value', '');
      });
      qempty.hidden = all.length > 0;
    }
    function bindRow(r) {
      var nameInput = $('[data-q-name]', r);
      var label = $('input[id$="-label"]', r);
      var auto = !nameInput.value;
      nameInput.addEventListener('input', function () { auto = false; syncConditions(); });
      label.addEventListener('input', function () {
        if (auto) { nameInput.value = slugify(label.value, '_').slice(0, 60); }
        syncConditions();
      });
      var type = $('[data-q-type]', r);
      function opts() { $('[data-q-options]', r).hidden = ['select', 'radio', 'checkbox'].indexOf(type.value) < 0; }
      type.addEventListener('change', opts);
      opts();
      $('[data-q-remove]', r).addEventListener('click', function () {
        if ($('input[name$="[id]"]', r).value) {
          $('[data-q-del]', r).value = '1';
          r.hidden = true;
        } else {
          r.remove();
        }
        syncConditions();
      });
      $('[data-q-up]', r).addEventListener('click', function () { move(r, -1); });
      $('[data-q-down]', r).addEventListener('click', function () { move(r, 1); });
      var handle = $('[data-q-handle]', r);
      handle.addEventListener('pointerdown', function () { r.draggable = true; });
      r.addEventListener('dragend', function () { r.draggable = false; r.classList.remove('is-dragging'); syncConditions(); });
      r.addEventListener('dragstart', function (ev) {
        r.classList.add('is-dragging');
        if (ev.dataTransfer) { ev.dataTransfer.effectAllowed = 'move'; ev.dataTransfer.setData('text/plain', 'q'); }
      });
    }
    function move(r, dir) {
      var vis = rows();
      var i = vis.indexOf(r);
      var t = vis[i + dir];
      if (!t) { return; }
      if (dir < 0) { qlist.insertBefore(r, t); } else { qlist.insertBefore(t, r); }
      var b = $(dir < 0 ? '[data-q-up]' : '[data-q-down]', r);
      if (b) { b.focus(); }
      syncConditions();
    }
    qlist.addEventListener('dragover', function (ev) {
      var dragging = $('.is-dragging', qlist);
      if (!dragging) { return; }
      ev.preventDefault();
      var after = null;
      rows().forEach(function (r) {
        if (r === dragging) { return; }
        var box = r.getBoundingClientRect();
        if (ev.clientY > box.top + box.height / 2) { after = r; }
      });
      var ref = after ? after.nextElementSibling : rows()[0];
      if (ref !== dragging) { qlist.insertBefore(dragging, ref || null); }
    });
    $$('[data-q-row]', qlist).forEach(bindRow);
    $('[data-q-add]', form).addEventListener('click', function () {
      var wrap = document.createElement('div');
      wrap.innerHTML = qtpl.innerHTML.replace(/__I__/g, String(nextIdx++));
      var r = wrap.firstElementChild;
      qlist.appendChild(r);
      bindRow(r);
      syncConditions();
      var l = $('input[id$="-label"]', r);
      if (l) { l.focus(); }
    });
    syncConditions();

    /* Reindexa las preguntas en el orden visual antes de enviar */
    form.addEventListener('submit', function () {
      $$('[data-q-row]', qlist).forEach(function (r, i) {
        $$('[name^="q["]', r).forEach(function (f) { f.name = f.name.replace(/^q\[[^\]]*\]/, 'q[' + i + ']'); });
      });
    });

    /* ----------------------------------------------------- vista previa */
    var modeText = {};
    $$('input[name="mode"]', form).forEach(function (r) { modeText[r.value] = r.parentNode.textContent.trim(); });
    function render() {
      if (!preview) { return; }
      preview.style.setProperty('--ev', el.color.value || '#C9A050');
      $('[data-pv=name]', preview).textContent = el.name.value.trim() || 'Nombre del evento';
      var d = el.description.value.trim();
      var dBox = $('[data-pv=description]', preview);
      dBox.innerHTML = d ? richText(d) : '<p class="muted">Aquí aparecerá la descripción de tu evento.</p>';
      var list = currentDurations();
      $('[data-pv=durations]', preview).textContent = list.length ? list.map(fmtDur).join(' · ') : 'Elige una duración';
      var chips = $('[data-pv=chips]', preview);
      chips.innerHTML = '';
      var def = parseInt(el.default_duration.value, 10);
      list.forEach(function (m) {
        var c = document.createElement('span');
        c.className = 'chip' + (m === def ? ' is-active' : '');
        c.textContent = fmtDur(m);
        chips.appendChild(c);
      });
      var mode = checked('mode') || 'in_person';
      var mt = modeText[mode] || '';
      if (mode === 'video_auto') { mt += ' · sala única por cita'; }
      if (mode === 'home' && parseInt(el.travel_minutes.value, 10) > 0) { mt += ' · traslado de ' + el.travel_minutes.value + ' min'; }
      $('[data-pv=mode]', preview).textContent = mt;
      var price = parseFloat(String(el.price.value).replace(',', '.'));
      var row = $('[data-pv-row=price]', preview);
      var pr = $('[data-pv=price]', preview);
      if (price > 0) {
        var txt = currency + price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        var dv = parseFloat(String(el.deposit_value.value).replace(',', '.'));
        if (el.deposit_type.value !== 'none' && dv > 0) {
          txt += ' · anticipo ' + (el.deposit_type.value === 'percent' ? dv + ' %' : currency + dv.toFixed(2));
        }
        pr.textContent = txt;
      } else {
        pr.textContent = 'Sin costo';
      }
      row.hidden = false;
    }
    form.addEventListener('input', render);
    form.addEventListener('change', render);
    render();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initList();
    var f = $('[data-p2-event-form]');
    if (f) { initForm(f); }
  });
}());
