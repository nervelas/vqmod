/* Agenda Premium · perfil: código QR de la verificación en dos pasos (generado en el navegador). */
(function () {
  'use strict';
  var box = document.getElementById('qr');
  if (!box || typeof window.qrcode !== 'function') { return; }
  try {
    var qr = window.qrcode(0, 'M');
    qr.addData(box.getAttribute('data-otpauth') || '');
    qr.make();
    var n = qr.getModuleCount(), cell = 5, margin = 4, size = (n + margin * 2) * cell;
    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 ' + size + ' ' + size);
    svg.setAttribute('width', '200'); svg.setAttribute('height', '200');
    svg.setAttribute('aria-hidden', 'true');
    var bg = document.createElementNS(ns, 'rect');
    bg.setAttribute('width', size); bg.setAttribute('height', size); bg.setAttribute('fill', '#ffffff');
    svg.appendChild(bg);
    var path = '';
    for (var r = 0; r < n; r++) {
      for (var c = 0; c < n; c++) {
        if (qr.isDark(r, c)) { path += 'M' + ((c + margin) * cell) + ' ' + ((r + margin) * cell) + 'h' + cell + 'v' + cell + 'h-' + cell + 'z'; }
      }
    }
    var p = document.createElementNS(ns, 'path');
    p.setAttribute('d', path); p.setAttribute('fill', '#06080d');
    svg.appendChild(p);
    box.appendChild(svg);
  } catch (e) {
    box.textContent = 'No pudimos dibujar el código QR. Usa la clave manual.';
  }
})();
