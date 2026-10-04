/* Editor de flujos: campos según la acción, variables y vista previa en vivo. */
(function () {
  'use strict';

  var page = document.querySelector('[data-workflow-page]');
  if (!page) { return; }
  var trigger = page.querySelector('[data-wf-trigger]');
  var action = page.querySelector('[data-wf-action]');
  var offset = page.querySelector('[data-wf-offset]');
  var hint = page.querySelector('[data-wf-offset-hint]');
  var template = document.getElementById('wf-template');
  var subject = document.getElementById('wf-subject');
  var lastTarget = template;

  function syncTrigger() {
    var timed = trigger.value === 'booking.before_start' || trigger.value === 'booking.after_end';
    offset.hidden = !timed;
    if (hint) {
      hint.textContent = trigger.value === 'booking.before_start' ? 'Se ejecuta ese tiempo ANTES de que empiece la cita.' : 'Se ejecuta ese tiempo DESPUÉS de que termina la cita.';
    }
  }
  function syncAction() {
    var a = action.value;
    page.querySelectorAll('[data-wf-show]').forEach(function (n) {
      var list = n.getAttribute('data-wf-show').split(' ');
      n.hidden = list.indexOf(a) === -1;
    });
    // el asunto solo aplica a correos
    if (a !== 'email' && a !== 'review_request' && subject) { subject.closest('.field').hidden = true; }
    schedulePreview();
  }

  [template, subject].forEach(function (t) {
    if (!t) { return; }
    t.addEventListener('focus', function () { lastTarget = t; });
    t.addEventListener('input', schedulePreview);
  });
  page.querySelectorAll('[data-wf-var]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = lastTarget && !lastTarget.closest('[hidden]') ? lastTarget : template;
      var v = b.getAttribute('data-wf-var');
      var s = t.selectionStart == null ? t.value.length : t.selectionStart;
      var e = t.selectionEnd == null ? s : t.selectionEnd;
      t.value = t.value.slice(0, s) + v + t.value.slice(e);
      t.focus();
      t.setSelectionRange(s + v.length, s + v.length);
      schedulePreview();
    });
  });

  // --- vista previa
  var timer = null;
  var seq = 0;
  var prevSel = page.querySelector('[data-wf-prev-booking]');
  var note = page.querySelector('[data-wf-prev-note]');
  var pSubject = page.querySelector('[data-wf-prev-subject]');
  var pBody = page.querySelector('[data-wf-prev-body]');
  var pErr = page.querySelector('[data-wf-prev-error]');

  function schedulePreview() {
    if (!pBody) { return; }
    clearTimeout(timer);
    timer = setTimeout(runPreview, 350);
  }
  function runPreview() {
    var a = action.value;
    if (['email', 'whatsapp', 'whatsapp_api', 'review_request'].indexOf(a) === -1) { return; }
    var my = ++seq;
    note.textContent = 'Preparando la vista previa…';
    var body = { template: template.value, subject: (a === 'email' || a === 'review_request') && subject ? subject.value : '', booking_id: prevSel ? parseInt(prevSel.value, 10) || 0 : 0 };
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    fetch(page.getAttribute('data-preview-url'), {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body)
    }).then(function (r) { return r.json(); }).then(function (d) {
      show(d, my);
    }).catch(function () {
      if (my !== seq) { return; }
      pErr.textContent = 'No pudimos generar la vista previa. Revisa tu conexión e inténtalo de nuevo.';
      pErr.hidden = false; pBody.hidden = true; pSubject.hidden = true; note.hidden = true;
    });
  }
  function show(d, my) {
    if (my !== seq) { return; }
    if (!d || !d.ok) {
      pErr.textContent = (d && d.error) || 'No pudimos generar la vista previa.';
      pErr.hidden = false; pBody.hidden = true; pSubject.hidden = true; note.hidden = true;
      return;
    }
    pErr.hidden = true;
    note.hidden = false;
    note.textContent = d.sample ? 'Así se vería con una cita de ejemplo (María López).' : 'Así se vería con la cita elegida.';
    if ((action.value === 'email' || action.value === 'review_request') && d.subject) { pSubject.textContent = d.subject; pSubject.hidden = false; } else { pSubject.hidden = true; }
    pBody.textContent = d.body;
    pBody.hidden = false;
  }
  if (prevSel) { prevSel.addEventListener('change', runPreview); }

  trigger.addEventListener('change', syncTrigger);
  action.addEventListener('change', syncAction);
  syncTrigger();
  syncAction();
  runPreview();
})();
