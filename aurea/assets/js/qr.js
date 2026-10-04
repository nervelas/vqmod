/* AUREA · genera códigos QR localmente (librería qrcode-generator, MIT) en [data-qr]. */
(() => {
  'use strict';
  const build = (el) => {
    if (typeof qrcode === 'undefined') return;
    const qr = qrcode(0, 'M'); qr.addData(el.getAttribute('data-qr')); qr.make();
    const n = qr.getModuleCount(); const size = Number(el.getAttribute('data-size') || 220); const cell = Math.floor(size / (n + 8));
    const dim = cell * (n + 8);
    const c = document.createElement('canvas'); c.width = dim; c.height = dim; c.setAttribute('role', 'img'); c.setAttribute('aria-label', 'Código QR');
    const x = c.getContext('2d'); x.fillStyle = '#ffffff'; x.fillRect(0, 0, dim, dim); x.fillStyle = '#0B0A08';
    for (let r = 0; r < n; r++) for (let q = 0; q < n; q++) if (qr.isDark(r, q)) x.fillRect((q + 4) * cell, (r + 4) * cell, cell, cell);
    el.replaceChildren(c);
    const dl = document.querySelector(el.getAttribute('data-download') || '');
    if (dl) dl.addEventListener('click', (e) => { e.preventDefault(); const a = document.createElement('a'); a.href = c.toDataURL('image/png'); a.download = 'qr-reservas.png'; a.click(); });
  };
  document.querySelectorAll('[data-qr]').forEach(build);
})();
