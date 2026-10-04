/* Gráfica de citas por periodo, dibujada con SVG propio (sin librerías). */
(function () {
  'use strict';

  var host = document.querySelector('[data-chart="period"]');
  var bootEl = document.getElementById('boot');
  if (!host || !bootEl) { return; }
  var data;
  try { data = JSON.parse(bootEl.textContent || '{}'); } catch (e) { data = {}; }
  var rows = data.period || [];
  var group = data.group || 'day';
  var NS = 'http://www.w3.org/2000/svg';
  var MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

  function s(tag, attrs, text) {
    var n = document.createElementNS(NS, tag);
    Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    if (text != null) { n.textContent = text; }
    return n;
  }
  function label(period) {
    if (group === 'month') { var m = period.split('-'); return MONTHS[parseInt(m[1], 10) - 1] + ' ' + m[0].slice(2); }
    var p = period.split('-');
    return parseInt(p[2], 10) + ' ' + MONTHS[parseInt(p[1], 10) - 1];
  }
  function niceMax(v) {
    if (v <= 4) { return 4; }
    var pow = Math.pow(10, Math.floor(Math.log10(v)));
    var f = v / pow;
    var n = f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10;
    return n * pow;
  }

  function draw() {
    host.textContent = '';
    var total = rows.reduce(function (a, r) { return a + r.bookings; }, 0);
    if (!rows.length || total === 0) {
      var p = document.createElement('p');
      p.className = 'empty-text p3-chart-empty';
      p.textContent = 'No hay citas en este periodo. Prueba con un rango de fechas más amplio.';
      host.appendChild(p);
      return;
    }
    var W = Math.max(280, host.clientWidth || 600);
    var H = W < 480 ? 220 : 280;
    var m = { t: 12, r: 8, b: 30, l: 34 };
    var iw = W - m.l - m.r;
    var ih = H - m.t - m.b;
    var max = niceMax(Math.max.apply(null, rows.map(function (r) { return r.bookings; })));
    var svg = s('svg', { class: 'p3-svg', viewBox: '0 0 ' + W + ' ' + H, width: W, height: H, role: 'img', 'aria-label': 'Gráfica de barras: ' + total + ' citas en ' + rows.length + ' periodos. Los datos están en la tabla de abajo.' });
    var ticks = 4;
    for (var i = 0; i <= ticks; i++) {
      var v = Math.round(max / ticks * i);
      var y = m.t + ih - (ih * i / ticks);
      svg.appendChild(s('line', { x1: m.l, x2: W - m.r, y1: y, y2: y, class: i === 0 ? 'p3-axis' : 'p3-grid' }));
      svg.appendChild(s('text', { x: m.l - 6, y: y + 4, 'text-anchor': 'end', class: 'p3-tick' }, String(v)));
    }
    var step = iw / rows.length;
    var bw = Math.max(2, Math.min(36, step * 0.68));
    var every = Math.max(1, Math.ceil(rows.length / Math.max(2, Math.floor(iw / 58))));
    rows.forEach(function (r, idx) {
      var cx = m.l + step * idx + step / 2;
      var lost = r.cancelled + r.no_show;
      var good = Math.max(0, r.bookings - lost);
      var hGood = ih * good / max;
      var hLost = ih * Math.min(lost, r.bookings) / max;
      var g = s('g', { class: 'p3-bar' });
      g.appendChild(s('title', {}, label(r.period) + ': ' + r.bookings + ' citas' + (lost ? ' (' + r.cancelled + ' canceladas, ' + r.no_show + ' no asistieron)' : '')));
      if (hGood > 0) { g.appendChild(s('rect', { x: cx - bw / 2, y: m.t + ih - hGood, width: bw, height: hGood, rx: 2, class: 'p3-bar-main' })); }
      if (hLost > 0) { g.appendChild(s('rect', { x: cx - bw / 2, y: m.t + ih - hGood - hLost, width: bw, height: hLost, rx: 2, class: 'p3-bar-lost' })); }
      if (r.bookings > 0 && step >= 26 && rows.length <= 31) {
        g.appendChild(s('text', { x: cx, y: m.t + ih - hGood - hLost - 4, 'text-anchor': 'middle', class: 'p3-val' }, String(r.bookings)));
      }
      svg.appendChild(g);
      if (idx % every === 0) {
        svg.appendChild(s('text', { x: cx, y: H - 10, 'text-anchor': 'middle', class: 'p3-tick' }, label(r.period)));
      }
    });
    host.appendChild(svg);
  }

  draw();
  var t = null;
  var last = host.clientWidth;
  function again() {
    clearTimeout(t);
    t = setTimeout(function () { if (host.clientWidth !== last) { last = host.clientWidth; draw(); } }, 120);
  }
  if (typeof ResizeObserver !== 'undefined') { new ResizeObserver(again).observe(host); } else { window.addEventListener('resize', again); }
})();
