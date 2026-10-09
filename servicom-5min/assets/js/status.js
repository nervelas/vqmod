/* Estado de construcción de la vista previa */
(function () {
  'use strict';
  var b = S5.boot(), $ = function (i) { return document.getElementById(i); };
  if (!b.token) return;
  var bar = $('st-bar'), pct = $('st-pct'), steps = $('st-steps'), msg = $('st-m'), title = $('st-t'), ico = $('st-ico'),
    pb = document.querySelector('[role=progressbar]'), acts = $('st-acts'), retry = $('st-retry');
  var delay = 2000, tries = 0, timer = null, fails = 0;
  $('st-pay').href = '/continuar/' + b.token + '?paso=pago';
  $('st-edit').href = '/continuar/' + b.token + '?paso=negocio';

  function render(r) {
    var p = Math.max(0, Math.min(100, Math.round(+r.progreso || 0)));
    bar.style.width = p + '%'; pct.textContent = p + ' %'; pb.setAttribute('aria-valuenow', p);
    steps.textContent = '';
    (r.pasos || []).forEach(function (s) {
      var st = String(s.estado || ''), cls = /ok|listo|hecho|complet/.test(st) ? 'ok' : /error|fall/.test(st) ? 'bad' : /curso|proces|run|trabaj/.test(st) ? 'run' : '';
      steps.appendChild(S5.h('li', { class: cls }, [S5.h('i', { text: cls === 'ok' ? '✓' : cls === 'bad' ? '!' : '' }), String(s.titulo || s.clave || '')]));
    });
  }
  function ready(url) {
    ico.textContent = '✓'; ico.classList.add('ico-ok');
    title.textContent = '¡Tu vista previa está lista!';
    msg.textContent = 'Revísala con calma. Si algo no te convence, edita tus datos y la regeneramos.';
    var v = $('st-view'); if (url) v.href = url; else v.hidden = true;
    acts.hidden = false; retry.hidden = true; document.title = 'Vista previa lista';
    title.setAttribute('tabindex', '-1'); title.focus();
  }
  function failed(m) {
    clearTimeout(timer);
    title.textContent = 'Estamos preparando tu vista previa';
    msg.textContent = m || 'Está tardando más de lo normal. No te preocupes: tus datos están a salvo. Vuelve a intentarlo en unos minutos o escríbenos.';
    retry.hidden = false; acts.hidden = true;
  }
  function poll() {
    S5.api('GET', '/api/borrador/' + encodeURIComponent(b.token) + '/construccion').then(function (r) {
      if (!r.ok) { fails++; if (r._net && fails < 6) { msg.textContent = 'Reconectando…'; } else if (fails >= 4) { return failed(); } }
      else {
        fails = 0; render(r);
        var e = String(r.estado || '');
        if (/listo|lista|completa|vista_lista|preview/.test(e) && !/fall/.test(e)) return ready(r.url || b.url);
        if (/error|fall/.test(e)) return failed(r.mensaje);
        if (r.mensaje) msg.textContent = r.mensaje;
      }
      tries++; delay = Math.min(6000, 2000 + tries * 250);
      timer = setTimeout(poll, delay);
    });
  }
  $('st-again').addEventListener('click', function () {
    retry.hidden = true; tries = 0; fails = 0; title.textContent = 'Preparando tu vista previa'; msg.textContent = 'Reintentando…';
    S5.api('POST', '/api/borrador/' + encodeURIComponent(b.token) + '/regenerar', {}).then(poll);
  });
  if (b.estado === 'listo' && b.url) ready(b.url); else poll();
})();
