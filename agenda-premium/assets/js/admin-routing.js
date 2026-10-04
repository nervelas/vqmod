/* Constructor de formularios de enrutamiento: preguntas y reglas SI/ENTONCES. */
(function () {
  'use strict';

  var form = document.getElementById('routing-form');
  var bootEl = document.getElementById('boot');
  if (!form || !bootEl) { return; }

  var boot;
  try { boot = JSON.parse(bootEl.textContent || '{}'); } catch (e) { boot = {}; }
  var state = {
    questions: (boot.questions || []).map(function (q) { return { id: q.id, label: q.label, type: q.type, options: (q.options || []).slice(), required: !!q.required }; }),
    rules: (boot.rules || []).map(function (r) {
      return { id: r.id || 0, match: r.match || 'all', active: r.active === 0 ? 0 : 1, action: r.action || 'event', target: r.target || '', message: r.message || '',
        conditions: (r.conditions || []).map(function (c) { return { question: c.question, op: c.op, value: Array.isArray(c.value) ? c.value.join(', ') : String(c.value == null ? '' : c.value) }; }) };
    })
  };

  var TYPES = [['text', 'Texto libre'], ['single', 'Opción única'], ['multi', 'Opción múltiple'], ['number', 'Número'], ['yesno', 'Sí / No']];
  var OPS = [['eq', 'es igual a'], ['neq', 'es distinto de'], ['contains', 'contiene'], ['gt', 'es mayor que'], ['lt', 'es menor que'], ['in', 'está en la lista']];
  var ACTIONS = [['event', 'Mostrar un tipo de cita'], ['host', 'Enviar a un anfitrión'], ['team', 'Enviar a un equipo'], ['message', 'Mostrar un mensaje'], ['url', 'Ir a una dirección web']];
  var qBox = form.querySelector('[data-questions]');
  var rBox = form.querySelector('[data-rules]');
  var uid = 0;

  function el(tag, attrs, children) {
    var n = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') { n.textContent = attrs[k]; }
      else if (k === 'class') { n.className = attrs[k]; }
      else if (attrs[k] !== false && attrs[k] != null) { n.setAttribute(k, attrs[k] === true ? '' : attrs[k]); }
    });
    (children || []).forEach(function (c) { if (c) { n.appendChild(c); } });
    return n;
  }
  function icon(name) {
    var base = (document.querySelector('meta[name="base-path"]') || {}).content || '';
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'ic'); svg.setAttribute('aria-hidden', 'true'); svg.setAttribute('focusable', 'false');
    var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    var sprite = form.getAttribute('data-sprite') || (base + '/assets/img/icons.svg');
    use.setAttributeNS('http://www.w3.org/1999/xlink', 'href', sprite + '#i-' + name);
    use.setAttribute('href', sprite + '#i-' + name);
    svg.appendChild(use);
    return svg;
  }
  function iconBtn(name, label, onClick, extra) {
    var b = el('button', { type: 'button', class: 'btn btn-ghost btn-sm btn-icon' + (extra ? ' ' + extra : ''), 'aria-label': label, title: label });
    b.appendChild(icon(name));
    b.addEventListener('click', onClick);
    return b;
  }
  function field(labelText, control, id, hint) {
    control.id = id;
    var f = el('div', { class: 'field' }, [el('label', { for: id, text: labelText }), control]);
    if (hint) { f.appendChild(el('p', { class: 'hint', text: hint })); }
    return f;
  }
  function select(opts, value) {
    var s = el('select', { class: 'select' });
    opts.forEach(function (o) {
      var op = el('option', { value: o[0], text: o[1] });
      if (String(o[0]) === String(value)) { op.selected = true; }
      s.appendChild(op);
    });
    return s;
  }
  function move(arr, i, d) {
    var j = i + d;
    if (j < 0 || j >= arr.length) { return false; }
    var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
    return true;
  }
  function nextQid() {
    var max = 0;
    state.questions.forEach(function (q) { var m = /^p(\d+)$/.exec(q.id); if (m) { max = Math.max(max, parseInt(m[1], 10)); } });
    return 'p' + (max + 1);
  }
  function qLabel(id) {
    for (var i = 0; i < state.questions.length; i++) { if (state.questions[i].id === id) { return state.questions[i].label || 'Pregunta sin texto'; } }
    return id;
  }
  function qById(id) {
    for (var i = 0; i < state.questions.length; i++) { if (state.questions[i].id === id) { return state.questions[i]; } }
    return null;
  }
  function qOptions(q) { return !q ? [] : (q.type === 'yesno' ? ['Sí', 'No'] : (q.options || [])); }

  // ---------------------------------------------------------------- preguntas
  function renderQuestions(focusIdx) {
    qBox.textContent = '';
    if (!state.questions.length) {
      qBox.appendChild(el('p', { class: 'muted p3-builder-empty', text: 'Todavía no hay preguntas. Agrega la primera para empezar.' }));
    }
    state.questions.forEach(function (q, i) {
      var uidp = 'q' + (++uid);
      var optsWrap = el('div', { class: 'field' + (q.type === 'single' || q.type === 'multi' ? '' : ' is-hidden') });
      var ta = el('textarea', { class: 'textarea', rows: '3', placeholder: 'Una opción por línea' });
      ta.value = (q.options || []).join('\n');
      ta.addEventListener('input', function () { q.options = ta.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean); });
      ta.addEventListener('change', function () { renderRules(); });
      optsWrap.appendChild(el('label', { for: uidp + '-o', text: 'Opciones' }));
      ta.id = uidp + '-o';
      optsWrap.appendChild(ta);
      var label = el('input', { class: 'input', type: 'text', maxlength: '190', placeholder: '¿Qué necesitas?' });
      label.value = q.label;
      label.addEventListener('input', function () { q.label = label.value; });
      label.addEventListener('change', function () { renderRules(); });
      var type = select(TYPES, q.type);
      type.addEventListener('change', function () { q.type = type.value; renderQuestions(i); renderRules(); });
      var req = el('input', { type: 'checkbox' });
      req.checked = q.required;
      req.addEventListener('change', function () { q.required = req.checked; });
      var card = el('div', { class: 'p3-bcard', role: 'group', 'aria-label': 'Pregunta ' + (i + 1) }, [
        el('div', { class: 'p3-bcard-head' }, [
          el('strong', { class: 'serif', text: 'Pregunta ' + (i + 1) }),
          el('span', { class: 'p3-bcard-tools' }, [
            iconBtn('chevron-up', 'Subir la pregunta ' + (i + 1), function () { if (move(state.questions, i, -1)) { renderQuestions(i - 1); renderRules(); } }),
            iconBtn('chevron-down', 'Bajar la pregunta ' + (i + 1), function () { if (move(state.questions, i, 1)) { renderQuestions(i + 1); renderRules(); } }),
            iconBtn('trash', 'Quitar la pregunta ' + (i + 1), function () {
              var id = q.id;
              state.questions.splice(i, 1);
              state.rules.forEach(function (r) { r.conditions = r.conditions.filter(function (c) { return c.question !== id; }); });
              renderQuestions(Math.max(0, i - 1)); renderRules();
            })
          ])
        ]),
        el('div', { class: 'form-grid' }, [field('Texto de la pregunta', label, uidp + '-l'), field('Tipo de respuesta', type, uidp + '-t')]),
        optsWrap,
        el('label', { class: 'check' }, [req, el('span', { text: 'Respuesta obligatoria' })])
      ]);
      qBox.appendChild(card);
      if (focusIdx === i) { label.focus(); }
    });
  }

  // -------------------------------------------------------------------- reglas
  function condControl(c, idPrefix) {
    var q = qById(c.question);
    var opts = qOptions(q);
    var useSelect = opts.length && (c.op === 'eq' || c.op === 'neq');
    var ctl;
    if (useSelect) {
      ctl = select([['', 'Elige una respuesta…']].concat(opts.map(function (o) { return [o, o]; })), c.value);
      ctl.addEventListener('change', function () { c.value = ctl.value; });
    } else {
      ctl = el('input', { class: 'input', type: 'text', maxlength: '200', placeholder: c.op === 'in' ? 'valor1, valor2, valor3' : 'Valor' });
      ctl.value = c.value;
      ctl.addEventListener('input', function () { c.value = ctl.value; });
    }
    ctl.id = idPrefix + '-v';
    ctl.setAttribute('aria-label', 'Valor de la condición');
    return ctl;
  }

  function targetControl(r, idPrefix) {
    var wrap = el('div', { class: 'field' });
    var ctl;
    var lbl = 'Destino';
    if (r.action === 'event' || r.action === 'host' || r.action === 'team') {
      var src = r.action === 'event' ? boot.events : (r.action === 'host' ? boot.hosts : boot.teams);
      lbl = r.action === 'event' ? 'Tipo de cita' : (r.action === 'host' ? 'Anfitrión' : 'Equipo');
      ctl = select([['', 'Elige…']].concat((src || []).map(function (x) { return [String(x.id), x.name]; })), r.target);
      ctl.addEventListener('change', function () { r.target = ctl.value; });
    } else if (r.action === 'url') {
      lbl = 'Dirección web';
      ctl = el('input', { class: 'input', type: 'url', maxlength: '500', placeholder: 'https://…' });
      ctl.value = r.target;
      ctl.addEventListener('input', function () { r.target = ctl.value; });
    } else {
      lbl = 'Mensaje para la persona';
      ctl = el('textarea', { class: 'textarea', rows: '3', maxlength: '2000' });
      ctl.value = r.message;
      ctl.addEventListener('input', function () { r.message = ctl.value; });
    }
    ctl.id = idPrefix + '-d';
    wrap.appendChild(el('label', { for: idPrefix + '-d', text: lbl }));
    wrap.appendChild(ctl);
    return wrap;
  }

  function renderRules(focusIdx) {
    rBox.textContent = '';
    if (!state.rules.length) {
      rBox.appendChild(el('p', { class: 'muted p3-builder-empty', text: 'Sin reglas todavía. Sin reglas, todas las personas reciben la respuesta por defecto.' }));
    }
    state.rules.forEach(function (r, i) {
      var pid = 'r' + (++uid);
      var conds = el('div', { class: 'stack p3-conds' });
      r.conditions.forEach(function (c, ci) {
        var cid = pid + '-c' + ci;
        var qSel = select([['', 'Elige la pregunta…']].concat(state.questions.map(function (q) { return [q.id, q.label || 'Pregunta sin texto']; })), c.question);
        qSel.id = cid + '-q'; qSel.setAttribute('aria-label', 'Pregunta de la condición ' + (ci + 1));
        qSel.addEventListener('change', function () { c.question = qSel.value; c.value = ''; renderRules(i); });
        var opSel = select(OPS, c.op);
        opSel.id = cid + '-o'; opSel.setAttribute('aria-label', 'Comparación de la condición ' + (ci + 1));
        opSel.addEventListener('change', function () { c.op = opSel.value; renderRules(i); });
        conds.appendChild(el('div', { class: 'p3-cond' }, [
          el('span', { class: 'p3-cond-word', text: ci === 0 ? 'Si' : (r.match === 'any' ? 'o' : 'y') }),
          qSel, opSel, condControl(c, cid),
          iconBtn('x', 'Quitar la condición ' + (ci + 1), function () { r.conditions.splice(ci, 1); renderRules(i); })
        ]));
      });
      var addC = el('button', { type: 'button', class: 'btn btn-outline btn-sm' }, [icon('plus'), document.createTextNode('Agregar condición')]);
      addC.addEventListener('click', function () { r.conditions.push({ question: state.questions.length ? state.questions[0].id : '', op: 'eq', value: '' }); renderRules(i); });
      var match = select([['all', 'todas'], ['any', 'alguna']], r.match);
      match.id = pid + '-m'; match.setAttribute('aria-label', 'Cuántas condiciones deben cumplirse');
      match.addEventListener('change', function () { r.match = match.value; renderRules(i); });
      var act = select(ACTIONS, r.action);
      act.addEventListener('change', function () { r.action = act.value; r.target = ''; renderRules(i); });
      var active = el('input', { type: 'checkbox' });
      active.checked = !!r.active;
      active.addEventListener('change', function () { r.active = active.checked ? 1 : 0; });
      var card = el('div', { class: 'p3-bcard p3-rule', role: 'group', 'aria-label': 'Regla ' + (i + 1) }, [
        el('div', { class: 'p3-bcard-head' }, [
          el('strong', { class: 'serif', text: 'Regla ' + (i + 1) }),
          el('span', { class: 'p3-bcard-tools' }, [
            iconBtn('chevron-up', 'Subir la regla ' + (i + 1) + ' (más prioridad)', function () { if (move(state.rules, i, -1)) { renderRules(i - 1); } }),
            iconBtn('chevron-down', 'Bajar la regla ' + (i + 1), function () { if (move(state.rules, i, 1)) { renderRules(i + 1); } }),
            iconBtn('trash', 'Quitar la regla ' + (i + 1), function () { state.rules.splice(i, 1); renderRules(Math.max(0, i - 1)); })
          ])
        ]),
        el('div', { class: 'p3-rule-match' }, [el('span', { text: 'Se cumplen' }), match, el('span', { text: 'de estas condiciones:' })]),
        conds, addC,
        el('p', { class: 'p3-rule-then serif', text: 'Entonces' }),
        el('div', { class: 'form-grid' }, [field('Acción', act, pid + '-a'), targetControl(r, pid)]),
        el('label', { class: 'check' }, [active, el('span', { text: 'Regla activa' })])
      ]);
      rBox.appendChild(card);
      if (focusIdx === i) { var f = card.querySelector('select, input'); if (f) { f.focus(); } }
    });
  }

  form.querySelector('[data-add-question]').addEventListener('click', function () {
    state.questions.push({ id: nextQid(), label: '', type: 'single', options: [], required: true });
    renderQuestions(state.questions.length - 1);
  });
  form.querySelector('[data-add-rule]').addEventListener('click', function () {
    if (!state.questions.length) { window.alert('Primero agrega al menos una pregunta.'); return; }
    state.rules.push({ id: 0, match: 'all', active: 1, action: 'event', target: '', message: '', conditions: [{ question: state.questions[0].id, op: 'eq', value: '' }] });
    renderRules(state.rules.length - 1);
  });

  // Acción por defecto: muestra el campo que corresponde
  var defAction = form.querySelector('[name="default_action"]');
  function syncDefault() {
    var a = defAction.value;
    form.querySelectorAll('[data-default-for]').forEach(function (n) {
      var list = n.getAttribute('data-default-for').split(' ');
      n.hidden = list.indexOf(a) === -1;
    });
  }
  if (defAction) { defAction.addEventListener('change', syncDefault); syncDefault(); }

  form.addEventListener('submit', function () {
    form.querySelector('[name="questions_json"]').value = JSON.stringify(state.questions);
    form.querySelector('[name="rules_json"]').value = JSON.stringify(state.rules);
  });

  renderQuestions();
  renderRules();
})();
