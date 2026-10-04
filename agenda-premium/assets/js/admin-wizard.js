/* Agenda Premium · Asistente de inicio (Admin4): horario por días y código QR. Vanilla ES2020. */
(function () {
  'use strict';
  var d = document;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };

  /* ---------- Paso 3: activar o desactivar un día ---------- */
  function initSchedule() {
    $$('[data-day]').forEach(function (row) {
      var toggle = $('[data-day-toggle]', row);
      if (!toggle) { return; }
      function sync() {
        row.classList.toggle('is-on', toggle.checked);
        $$('[data-day-input]', row).forEach(function (i) { i.disabled = !toggle.checked; });
        var closed = $('[data-closed]', row);
        if (closed) { closed.hidden = toggle.checked; }
        if (toggle.checked) {
          var inputs = $$('[data-day-input]', row);
          if (inputs.length >= 2 && !inputs[0].value && !inputs[1].value) { inputs[0].value = '08:00'; inputs[1].value = '17:00'; }
        }
      }
      toggle.addEventListener('change', sync);
    });
  }

  /* ---------- Paso 5: código QR descargable ---------- */
  function initQr() {
    var root = $('[data-qr-root]');
    if (!root) { return; }
    var box = $('[data-qr-box]', root), url = root.getAttribute('data-url') || '';
    var name = (root.getAttribute('data-name') || 'agenda').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'agenda';
    if (typeof window.qrcode !== 'function' || !url) {
      if (box) { box.textContent = 'No pudimos generar el código QR. Copia el enlace.'; }
      return;
    }
    var qr = window.qrcode(0, 'M');
    qr.addData(url);
    qr.make();
    var n = qr.getModuleCount(), q = 4, size = n + q * 2;
    var path = '';
    for (var r = 0; r < n; r++) {
      for (var c = 0; c < n; c++) { if (qr.isDark(r, c)) { path += 'M' + (c + q) + ' ' + (r + q) + 'h1v1h-1z'; } }
    }
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" shape-rendering="crispEdges" width="512" height="512">' +
      '<rect width="100%" height="100%" fill="#ffffff"/><path d="' + path + '" fill="#06080D"/></svg>';
    var blob = new Blob([svg], { type: 'image/svg+xml' });
    var svgUrl = URL.createObjectURL(blob);
    box.textContent = '';
    var img = d.createElement('img');
    img.src = svgUrl; img.alt = 'Código QR que lleva a ' + url; img.width = 240; img.height = 240;
    box.appendChild(img);

    function save(href, file) {
      var a = d.createElement('a');
      a.href = href; a.download = file; d.body.appendChild(a); a.click(); d.body.removeChild(a);
    }
    $$('[data-qr-download]', root).forEach(function (b) {
      b.addEventListener('click', function () {
        if (b.getAttribute('data-qr-download') === 'svg') { save(svgUrl, 'qr-' + name + '.svg'); return; }
        var cv = d.createElement('canvas'); cv.width = 1024; cv.height = 1024;
        var ctx = cv.getContext('2d'), im = new Image();
        im.onload = function () {
          ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, 1024, 1024); ctx.imageSmoothingEnabled = false; ctx.drawImage(im, 0, 0, 1024, 1024);
          cv.toBlob(function (bl) { if (!bl) { return; } var u = URL.createObjectURL(bl); save(u, 'qr-' + name + '.png'); setTimeout(function () { URL.revokeObjectURL(u); }, 2000); }, 'image/png');
        };
        im.src = svgUrl;
      });
    });
  }

  function init() { initSchedule(); initQr(); }
  if (d.readyState === 'loading') { d.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
