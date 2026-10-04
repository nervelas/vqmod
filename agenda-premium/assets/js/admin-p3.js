/* Comportamientos compartidos de las pantallas de ventas, mensajes, encuestas y pagos. */
(function () {
  'use strict';

  // Imprimir (recibo y certificado)
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-print]');
    if (b) { e.preventDefault(); window.print(); }
  });

  // Formulario de pago: campos según el método
  document.querySelectorAll('[data-payform]').forEach(function (form) {
    var sel = form.querySelector('[data-pay-method]');
    if (!sel) { return; }
    function sync() {
      form.querySelectorAll('[data-pay-show]').forEach(function (n) {
        n.hidden = n.getAttribute('data-pay-show').split(' ').indexOf(sel.value) === -1;
      });
    }
    sel.addEventListener('change', sync);
    sync();
  });

  // Encuesta: agregar opciones y duración sugerida por tipo de cita
  var poll = document.querySelector('[data-poll-form]');
  if (poll) {
    var box = poll.querySelector('[data-poll-options]');
    var add = poll.querySelector('[data-poll-add]');
    var MAX = 20;
    add.addEventListener('click', function () {
      var n = box.querySelectorAll('input').length;
      if (n >= MAX) { add.disabled = true; return; }
      var id = 'po-o' + (n + 1);
      var wrap = document.createElement('div');
      wrap.className = 'field p3-opt';
      var lab = document.createElement('label');
      lab.setAttribute('for', id);
      lab.textContent = 'Opción ' + (n + 1);
      var inp = document.createElement('input');
      inp.className = 'input'; inp.type = 'datetime-local'; inp.name = 'opts[]'; inp.id = id;
      wrap.appendChild(lab); wrap.appendChild(inp);
      box.appendChild(wrap);
      inp.focus();
      if (n + 1 >= MAX) { add.disabled = true; }
    });
    var ev = poll.querySelector('[data-poll-event]');
    var dur = poll.querySelector('[data-poll-duration]');
    if (ev && dur) {
      ev.addEventListener('change', function () {
        var o = ev.options[ev.selectedIndex];
        if (o && o.getAttribute('data-duration')) { dur.value = o.getAttribute('data-duration'); }
      });
    }
  }

  // Mensajes de hoy: al abrir WhatsApp, recordar marcar como enviado
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('[data-wa-open]');
    if (a && window.Ap && typeof window.Ap.toast === 'function') {
      setTimeout(function () { window.Ap.toast('Cuando lo envíes, toca "Marcar como enviado" para sacarlo de la lista.', 'info'); }, 600);
    }
  });
})();
