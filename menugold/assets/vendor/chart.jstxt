/*!
 * MenuGold Charts - libreria de graficas local, sin dependencias ni CDN.
 * Expone el objeto global `Chart` con una interfaz compatible con el
 * subconjunto de Chart.js que usa el panel: bar, line, doughnut y pie.
 *
 *   new Chart(canvas, { type:'bar', data:{labels:[], datasets:[{label,data,backgroundColor}]},
 *                       options:{ moneda:true, leyenda:true } });
 */
(function (global) {
  'use strict';

  var PALETA = ['#D4AF37', '#8C6B2F', '#3E6B5A', '#7A2E3B', '#2C4A6E', '#B4643C', '#5A5A5A', '#C9A227'];

  function css(el, prop, fb) {
    try { var v = getComputedStyle(el).getPropertyValue(prop).trim(); return v || fb; } catch (e) { return fb; }
  }
  function fmt(v, moneda) {
    if (v === null || v === undefined || isNaN(v)) return '0';
    var n = Number(v);
    if (moneda) return 'Q' + n.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (Math.abs(n) >= 1000) return n.toLocaleString('es-GT');
    return (Math.round(n * 100) / 100).toString();
  }
  function niceMax(max) {
    if (max <= 0) return 10;
    var exp = Math.floor(Math.log10(max));
    var base = Math.pow(10, exp);
    var f = max / base;
    var mult = f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10;
    return mult * base;
  }
  function lerp(a, b, t) { return a + (b - a) * t; }
  function easeOut(t) { return 1 - Math.pow(1 - t, 3); }

  function Chart(target, config) {
    var canvas = typeof target === 'string' ? document.getElementById(target)
              : (target && target.canvas ? target.canvas : target);
    if (!canvas || !canvas.getContext) throw new Error('Chart: canvas no valido');
    if (canvas.__mgChart) canvas.__mgChart.destroy();
    canvas.__mgChart = this;

    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    this.config = config || {};
    this.data = this.config.data || { labels: [], datasets: [] };
    this.options = this.config.options || {};
    this.type = this.config.type || 'bar';
    this.hover = -1;
    this.progreso = 0;
    this._destruido = false;

    var self = this;
    this._onMove = function (ev) { self._mover(ev); };
    this._onLeave = function () { if (self.hover !== -1) { self.hover = -1; self._pintar(); self._tip(null); } };
    canvas.addEventListener('mousemove', this._onMove);
    canvas.addEventListener('mouseleave', this._onLeave);
    canvas.addEventListener('touchstart', this._onMove, { passive: true });
    canvas.addEventListener('touchmove', this._onMove, { passive: true });

    this._onResize = function () { self.resize(); };
    global.addEventListener('resize', this._onResize);

    this.resize();
    this._animar();
  }

  Chart.defaults = { paleta: PALETA };

  Chart.prototype.resize = function () {
    if (this._destruido) return;
    var c = this.canvas;
    var dpr = global.devicePixelRatio || 1;
    var padre = c.parentElement;
    var w = (padre ? padre.clientWidth : c.clientWidth) || 320;
    var h = parseInt(c.getAttribute('height') || '0', 10) || c.clientHeight || 260;
    if (this.options.alto) h = this.options.alto;
    c.width = Math.max(120, Math.round(w * dpr));
    c.height = Math.max(100, Math.round(h * dpr));
    c.style.width = '100%';
    c.style.height = h + 'px';
    this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    this.W = w; this.H = h;
    this._pintar();
  };

  Chart.prototype.update = function (data) {
    if (data) this.data = data;
    this.progreso = 0;
    this._animar();
  };

  Chart.prototype.destroy = function () {
    this._destruido = true;
    this.canvas.removeEventListener('mousemove', this._onMove);
    this.canvas.removeEventListener('mouseleave', this._onLeave);
    global.removeEventListener('resize', this._onResize);
    this._tip(null);
    try { this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height); } catch (e) {}
    if (this.canvas.__mgChart === this) this.canvas.__mgChart = null;
  };

  Chart.prototype._animar = function () {
    var self = this;
    var ini = null;
    var dur = this.options.animacion === false ? 0 : 520;
    function paso(ts) {
      if (self._destruido) return;
      if (ini === null) ini = ts;
      var t = dur === 0 ? 1 : Math.min(1, (ts - ini) / dur);
      self.progreso = easeOut(t);
      self._pintar();
      if (t < 1) global.requestAnimationFrame(paso);
    }
    global.requestAnimationFrame(paso);
  };

  // ------------------------------------------------------------------ pintar
  Chart.prototype._pintar = function () {
    if (this._destruido || !this.W) return;
    var ctx = this.ctx;
    ctx.clearRect(0, 0, this.W, this.H);
    this.tinta = css(document.documentElement, '--grafica-texto', '#6b6b6b');
    this.rejilla = css(document.documentElement, '--grafica-rejilla', 'rgba(140,140,140,.18)');
    if (this.type === 'doughnut' || this.type === 'pie') this._pintarDona();
    else if (this.type === 'line' || this.type === 'area') this._pintarLinea();
    else this._pintarBarras();
  };

  Chart.prototype._ejes = function (maxVal) {
    var ctx = this.ctx;
    var moneda = !!this.options.moneda;
    var max = niceMax(maxVal);
    var pasos = 4;
    ctx.font = '11px Inter, system-ui, sans-serif';
    var anchoEtiq = 0;
    for (var i = 0; i <= pasos; i++) {
      anchoEtiq = Math.max(anchoEtiq, ctx.measureText(fmt(max * i / pasos, moneda)).width);
    }
    var L = anchoEtiq + 14, R = 10, T = 12, B = 34;
    var area = { x: L, y: T, w: Math.max(20, this.W - L - R), h: Math.max(20, this.H - T - B) };

    ctx.strokeStyle = this.rejilla;
    ctx.fillStyle = this.tinta;
    ctx.lineWidth = 1;
    ctx.textAlign = 'right';
    ctx.textBaseline = 'middle';
    for (var s = 0; s <= pasos; s++) {
      var y = Math.round(area.y + area.h - (area.h * s / pasos)) + 0.5;
      ctx.beginPath(); ctx.moveTo(area.x, y); ctx.lineTo(area.x + area.w, y); ctx.stroke();
      ctx.fillText(fmt(max * s / pasos, moneda), area.x - 8, y);
    }
    return { area: area, max: max };
  };

  Chart.prototype._etiquetasX = function (area, n) {
    var ctx = this.ctx, labels = this.data.labels || [];
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.fillStyle = this.tinta;
    ctx.font = '11px Inter, system-ui, sans-serif';
    var paso = Math.ceil(n / Math.max(2, Math.floor(area.w / 58)));
    var ultimoX = -1e9;
    for (var i = 0; i < n; i++) {
      if (i % paso !== 0 && i !== n - 1) continue;
      var x = area.x + (area.w / n) * (i + 0.5);
      if (x - ultimoX < 46) continue;   // evita etiquetas encimadas
      ultimoX = x;
      var txt = String(labels[i] === undefined ? '' : labels[i]);
      if (ctx.measureText(txt).width > area.w / n * 1.6) {
        while (txt.length > 2 && ctx.measureText(txt + '…').width > area.w / n * 1.6) txt = txt.slice(0, -1);
        txt += '…';
      }
      ctx.fillText(txt, x, area.y + area.h + 8);
    }
  };

  Chart.prototype._maximo = function () {
    var m = 0, ds = this.data.datasets || [];
    for (var d = 0; d < ds.length; d++) {
      var arr = ds[d].data || [];
      for (var i = 0; i < arr.length; i++) m = Math.max(m, Number(arr[i]) || 0);
    }
    return m || 1;
  };

  Chart.prototype._pintarBarras = function () {
    var ctx = this.ctx;
    var ds = this.data.datasets || [];
    var n = (this.data.labels || []).length || (ds[0] && ds[0].data ? ds[0].data.length : 0);
    if (!n) return this._vacio();
    var ej = this._ejes(this._maximo());
    var area = ej.area, max = ej.max;
    var grupo = area.w / n;
    var nds = ds.length;
    var anchoBarra = Math.max(4, Math.min(46, (grupo * 0.62) / nds));
    this._puntos = [];

    for (var d = 0; d < nds; d++) {
      var set = ds[d];
      var color = set.backgroundColor || PALETA[d % PALETA.length];
      for (var i = 0; i < n; i++) {
        var v = Number((set.data || [])[i]) || 0;
        var h = (v / max) * area.h * this.progreso;
        var cx = area.x + grupo * (i + 0.5) - (anchoBarra * nds) / 2 + anchoBarra * d;
        var y = area.y + area.h - h;
        var activo = this.hover === i;
        ctx.fillStyle = typeof color === 'string' ? color : PALETA[d % PALETA.length];
        ctx.globalAlpha = this.hover === -1 || activo ? 1 : 0.42;
        this._rectRedondo(cx, y, anchoBarra - 2, h, Math.min(6, anchoBarra / 3));
        ctx.fill();
        ctx.globalAlpha = 1;
        if (d === 0) this._puntos.push({ i: i, x: area.x + grupo * (i + 0.5), y: y, w: grupo });
      }
    }
    this._etiquetasX(area, n);
    this._leyenda();
  };

  Chart.prototype._pintarLinea = function () {
    var ctx = this.ctx;
    var ds = this.data.datasets || [];
    var n = (this.data.labels || []).length || (ds[0] && ds[0].data ? ds[0].data.length : 0);
    if (!n) return this._vacio();
    var ej = this._ejes(this._maximo());
    var area = ej.area, max = ej.max;
    this._puntos = [];

    for (var d = 0; d < ds.length; d++) {
      var set = ds[d];
      var color = set.borderColor || set.backgroundColor || PALETA[d % PALETA.length];
      var pts = [];
      for (var i = 0; i < n; i++) {
        var v = Number((set.data || [])[i]) || 0;
        var x = area.x + (n === 1 ? area.w / 2 : (area.w / (n - 1)) * i);
        var y = area.y + area.h - (v / max) * area.h * this.progreso;
        pts.push([x, y]);
        if (d === 0) this._puntos.push({ i: i, x: x, y: y, w: area.w / n });
      }
      if (set.fill !== false) {
        var g = ctx.createLinearGradient(0, area.y, 0, area.y + area.h);
        g.addColorStop(0, this._alpha(color, 0.28));
        g.addColorStop(1, this._alpha(color, 0.02));
        ctx.beginPath();
        ctx.moveTo(pts[0][0], area.y + area.h);
        for (var p = 0; p < pts.length; p++) ctx.lineTo(pts[p][0], pts[p][1]);
        ctx.lineTo(pts[pts.length - 1][0], area.y + area.h);
        ctx.closePath();
        ctx.fillStyle = g; ctx.fill();
      }
      ctx.beginPath();
      ctx.strokeStyle = color;
      ctx.lineWidth = 2.4;
      ctx.lineJoin = 'round'; ctx.lineCap = 'round';
      for (var q = 0; q < pts.length; q++) {
        if (q === 0) ctx.moveTo(pts[q][0], pts[q][1]); else ctx.lineTo(pts[q][0], pts[q][1]);
      }
      ctx.stroke();
      for (var r = 0; r < pts.length; r++) {
        var act = this.hover === r;
        ctx.beginPath();
        ctx.arc(pts[r][0], pts[r][1], act ? 5 : 3, 0, Math.PI * 2);
        ctx.fillStyle = act ? color : '#fff';
        ctx.strokeStyle = color; ctx.lineWidth = 2;
        ctx.fill(); ctx.stroke();
      }
    }
    this._etiquetasX(area, n);
    this._leyenda();
  };

  Chart.prototype._pintarDona = function () {
    var ctx = this.ctx;
    var set = (this.data.datasets || [])[0] || { data: [] };
    var datos = set.data || [];
    var total = 0;
    for (var i = 0; i < datos.length; i++) total += Math.max(0, Number(datos[i]) || 0);
    if (!total) return this._vacio();

    var leyendaAncho = this.options.leyenda === false ? 0 : Math.min(190, this.W * 0.42);
    var cx = (this.W - leyendaAncho) / 2;
    var cy = this.H / 2;
    var rad = Math.min(cx, cy) - 12;
    var interior = this.type === 'doughnut' ? rad * 0.6 : 0;
    var ang = -Math.PI / 2;
    this._sectores = [];

    for (var s = 0; s < datos.length; s++) {
      var v = Math.max(0, Number(datos[s]) || 0);
      var barrido = (v / total) * Math.PI * 2 * this.progreso;
      var color = (set.backgroundColor && set.backgroundColor[s]) || PALETA[s % PALETA.length];
      var activo = this.hover === s;
      var rr = activo ? rad + 4 : rad;
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, rr, ang, ang + barrido);
      ctx.closePath();
      ctx.fillStyle = color;
      ctx.globalAlpha = this.hover === -1 || activo ? 1 : 0.5;
      ctx.fill();
      ctx.globalAlpha = 1;
      this._sectores.push({ i: s, ini: ang, fin: ang + barrido, v: v });
      ang += barrido;
    }
    if (interior > 0) {
      ctx.globalCompositeOperation = 'destination-out';
      ctx.beginPath(); ctx.arc(cx, cy, interior, 0, Math.PI * 2); ctx.fill();
      ctx.globalCompositeOperation = 'source-over';
      ctx.fillStyle = this.tinta;
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.font = '600 15px Inter, system-ui, sans-serif';
      ctx.fillText(fmt(total, this.options.moneda), cx, cy - 6);
      ctx.font = '11px Inter, system-ui, sans-serif';
      ctx.fillText(this.options.totalTexto || 'Total', cx, cy + 12);
    }
    this._centro = { x: cx, y: cy, r: rad, ri: interior };
    if (leyendaAncho > 0) this._leyendaLateral(this.W - leyendaAncho + 8, datos, total, set);
  };

  Chart.prototype._leyendaLateral = function (x, datos, total, set) {
    var ctx = this.ctx;
    var labels = this.data.labels || [];
    var alturaItem = 22;
    var y = Math.max(10, (this.H - datos.length * alturaItem) / 2);
    ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
    ctx.font = '12px Inter, system-ui, sans-serif';
    for (var i = 0; i < datos.length; i++) {
      var color = (set.backgroundColor && set.backgroundColor[i]) || PALETA[i % PALETA.length];
      ctx.fillStyle = color;
      this._rectRedondo(x, y + i * alturaItem - 5, 10, 10, 3); ctx.fill();
      ctx.fillStyle = this.tinta;
      var pct = total ? Math.round((datos[i] / total) * 100) : 0;
      var sufijo = '  ' + pct + '%';
      var etq = String(labels[i] === undefined || labels[i] === null ? '' : labels[i]);
      var maxW = Math.max(24, this.W - x - 16);
      // Recortamos solo la etiqueta, letra por letra: el porcentaje siempre se ve.
      if (ctx.measureText(etq + sufijo).width > maxW) {
        while (etq.length > 1 && ctx.measureText(etq + '…' + sufijo).width > maxW) etq = etq.slice(0, -1);
        etq += '…';
      }
      ctx.fillText(etq + sufijo, x + 16, y + i * alturaItem);
    }
  };

  Chart.prototype._leyenda = function () {
    if (this.options.leyenda === false) return;
    var ds = this.data.datasets || [];
    if (ds.length < 2) return;
    var ctx = this.ctx;
    ctx.font = '11px Inter, system-ui, sans-serif';
    ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
    var x = 10, y = 8;
    for (var d = 0; d < ds.length; d++) {
      var color = ds[d].borderColor || ds[d].backgroundColor || PALETA[d % PALETA.length];
      ctx.fillStyle = typeof color === 'string' ? color : PALETA[d % PALETA.length];
      this._rectRedondo(x, y - 4, 9, 9, 3); ctx.fill();
      ctx.fillStyle = this.tinta;
      var t = ds[d].label || ('Serie ' + (d + 1));
      ctx.fillText(t, x + 14, y);
      x += 24 + ctx.measureText(t).width;
    }
  };

  Chart.prototype._vacio = function () {
    var ctx = this.ctx;
    ctx.fillStyle = this.tinta;
    ctx.globalAlpha = 0.6;
    ctx.font = '13px Inter, system-ui, sans-serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(this.options.vacio || 'Aún no hay datos para mostrar', this.W / 2, this.H / 2);
    ctx.globalAlpha = 1;
  };

  Chart.prototype._rectRedondo = function (x, y, w, h, r) {
    var ctx = this.ctx;
    if (h < 0) { y += h; h = -h; }
    r = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + w - r, y); ctx.quadraticCurveTo(x + w, y, x + w, y + r);
    ctx.lineTo(x + w, y + h - r); ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
    ctx.lineTo(x + r, y + h); ctx.quadraticCurveTo(x, y + h, x, y + h - r);
    ctx.lineTo(x, y + r); ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
  };

  Chart.prototype._alpha = function (color, a) {
    if (typeof color !== 'string') return 'rgba(212,175,55,' + a + ')';
    if (color.charAt(0) === '#') {
      var h = color.slice(1);
      if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
      var n = parseInt(h, 16);
      return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
    }
    return color;
  };

  // ------------------------------------------------------------------ hover
  Chart.prototype._mover = function (ev) {
    var r = this.canvas.getBoundingClientRect();
    var punto = ev.touches && ev.touches[0] ? ev.touches[0] : ev;
    var mx = punto.clientX - r.left, my = punto.clientY - r.top;
    var idx = -1;

    if ((this.type === 'doughnut' || this.type === 'pie') && this._centro) {
      var dx = mx - this._centro.x, dy = my - this._centro.y;
      var dist = Math.sqrt(dx * dx + dy * dy);
      if (dist <= this._centro.r + 6 && dist >= this._centro.ri) {
        var a = Math.atan2(dy, dx);
        if (a < -Math.PI / 2) a += Math.PI * 2;
        for (var s = 0; s < (this._sectores || []).length; s++) {
          if (a >= this._sectores[s].ini && a <= this._sectores[s].fin) { idx = s; break; }
        }
      }
    } else if (this._puntos) {
      var mejor = 1e9;
      for (var i = 0; i < this._puntos.length; i++) {
        var d = Math.abs(this._puntos[i].x - mx);
        if (d < mejor && d < this._puntos[i].w) { mejor = d; idx = this._puntos[i].i; }
      }
    }
    if (idx !== this.hover) {
      this.hover = idx;
      this._pintar();
    }
    this._tip(idx === -1 ? null : { x: punto.clientX, y: punto.clientY, i: idx });
  };

  Chart.prototype._tip = function (info) {
    var el = document.getElementById('mg-chart-tip');
    if (!info) { if (el) el.style.display = 'none'; return; }
    if (!el) {
      el = document.createElement('div');
      el.id = 'mg-chart-tip';
      el.setAttribute('role', 'status');
      el.style.cssText = 'position:fixed;z-index:9999;pointer-events:none;background:rgba(20,20,20,.94);' +
        'color:#F7F3EA;padding:8px 11px;border-radius:9px;font:12px/1.45 Inter,system-ui,sans-serif;' +
        'box-shadow:0 8px 24px rgba(0,0,0,.28);max-width:230px;white-space:nowrap';
      document.body.appendChild(el);
    }
    var labels = this.data.labels || [];
    var ds = this.data.datasets || [];
    var html = '<b>' + this._esc(String(labels[info.i] === undefined ? '' : labels[info.i])) + '</b>';
    for (var d = 0; d < ds.length; d++) {
      var v = (ds[d].data || [])[info.i];
      if (v === undefined) continue;
      var color = ds[d].borderColor || (Array.isArray(ds[d].backgroundColor) ? ds[d].backgroundColor[info.i] : ds[d].backgroundColor) || PALETA[d % PALETA.length];
      html += '<div style="margin-top:3px"><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:' +
        color + ';margin-right:6px"></span>' +
        (ds.length > 1 && ds[d].label ? this._esc(ds[d].label) + ': ' : '') +
        fmt(v, this.options.moneda) + (this.options.sufijo || '') + '</div>';
    }
    el.innerHTML = html;
    el.style.display = 'block';
    var w = el.offsetWidth, h = el.offsetHeight;
    var x = Math.min(global.innerWidth - w - 8, Math.max(8, info.x + 14));
    var y = Math.max(8, info.y - h - 12);
    el.style.left = x + 'px';
    el.style.top = y + 'px';
  };

  Chart.prototype._esc = function (s) {
    return String(s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  };

  global.Chart = Chart;
})(window);
