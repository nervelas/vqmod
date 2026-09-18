/* ==========================================================================
   CorreoRadar · Graficas del panel
   Dibujadas sobre <canvas> con JavaScript puro: sin librerias externas ni
   peticiones a servicios de terceros. Se adaptan al tema claro/oscuro y se
   redibujan al cambiar el tamano de la ventana.
   ========================================================================== */
(function () {
  'use strict';

  /** Lee un color del tema actual. */
  function color(nombre, respaldo) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(nombre).trim();
    return v || respaldo;
  }

  /** Prepara el canvas para pantallas de alta densidad. */
  function lienzo(cv, alto) {
    var dpr = window.devicePixelRatio || 1;
    var ancho = cv.parentNode.clientWidth || 600;
    cv.width = Math.round(ancho * dpr);
    cv.height = Math.round(alto * dpr);
    cv.style.height = alto + 'px';
    var ctx = cv.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, ancho, alto);
    return { ctx: ctx, w: ancho, h: alto };
  }

  function redondeado(ctx, x, y, w, h, r) {
    r = Math.min(r, h / 2, w / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  /* ------------------------------------------------- Grafica de área/lineas */
  function areas(cv, datos) {
    var alto = 230;
    var l = lienzo(cv, alto), ctx = l.ctx, w = l.w, h = l.h;
    var oro  = color('--oro', '#D8B36A');
    var neon = color('--neon', '#6EF3A5');
    var borde = color('--borde', 'rgba(255,255,255,.09)');
    var suave = color('--suave', '#98A0AE');

    var m = { t: 16, r: 14, b: 30, i: 40 };
    var gw = w - m.i - m.r, gh = h - m.t - m.b;
    var series = datos.series, etiquetas = datos.etiquetas;

    var max = 1;
    series.forEach(function (s) { s.valores.forEach(function (v) { if (v > max) { max = v; } }); });
    max = Math.ceil(max * 1.18) || 1;

    // Rejilla y escala
    ctx.strokeStyle = borde; ctx.lineWidth = 1;
    ctx.fillStyle = suave; ctx.font = '11px Inter, sans-serif'; ctx.textAlign = 'right';
    for (var i = 0; i <= 4; i++) {
      var y = m.t + gh - (gh * i / 4);
      ctx.beginPath(); ctx.moveTo(m.i, y + 0.5); ctx.lineTo(m.i + gw, y + 0.5); ctx.stroke();
      ctx.fillText(String(Math.round(max * i / 4)), m.i - 8, y + 4);
    }

    // Etiquetas del eje X (una de cada N para que no se amontonen)
    ctx.textAlign = 'center';
    var salto = Math.ceil(etiquetas.length / 7);
    etiquetas.forEach(function (et, k) {
      if (k % salto !== 0 && k !== etiquetas.length - 1) { return; }
      var x = m.i + (etiquetas.length === 1 ? gw / 2 : gw * k / (etiquetas.length - 1));
      ctx.fillText(et, x, h - 10);
    });

    series.forEach(function (s, idx) {
      var c = idx === 0 ? oro : neon;
      var puntos = s.valores.map(function (v, k) {
        return {
          x: m.i + (s.valores.length === 1 ? gw / 2 : gw * k / (s.valores.length - 1)),
          y: m.t + gh - (gh * v / max)
        };
      });

      // Relleno degradado
      var grad = ctx.createLinearGradient(0, m.t, 0, m.t + gh);
      grad.addColorStop(0, c + (c.charAt(0) === '#' ? '44' : ''));
      grad.addColorStop(1, c + (c.charAt(0) === '#' ? '00' : ''));
      ctx.beginPath();
      ctx.moveTo(puntos[0].x, m.t + gh);
      puntos.forEach(function (p) { ctx.lineTo(p.x, p.y); });
      ctx.lineTo(puntos[puntos.length - 1].x, m.t + gh);
      ctx.closePath();
      ctx.fillStyle = grad; ctx.fill();

      // Linea
      ctx.beginPath();
      puntos.forEach(function (p, k) { k ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y); });
      ctx.strokeStyle = c; ctx.lineWidth = 2.2; ctx.lineJoin = 'round'; ctx.stroke();

      // Puntos
      ctx.fillStyle = c;
      puntos.forEach(function (p) { ctx.beginPath(); ctx.arc(p.x, p.y, 2.8, 0, Math.PI * 2); ctx.fill(); });
    });
  }

  /* ------------------------------------------------ Barras horizontales */
  function barras(cv, datos) {
    var filas = datos.filas || [];
    var alto = Math.max(120, filas.length * 34 + 16);
    var l = lienzo(cv, alto), ctx = l.ctx, w = l.w;
    var oro  = color('--oro', '#D8B36A');
    var neon = color('--neon', '#6EF3A5');
    var texto = color('--texto', '#EDEAE3');
    var suave = color('--suave', '#98A0AE');

    var max = 1;
    filas.forEach(function (f) { if (f.valor > max) { max = f.valor; } });

    var etiquetaAncho = Math.min(190, Math.max(90, w * 0.34));
    ctx.font = '12px Inter, sans-serif';

    filas.forEach(function (f, i) {
      var y = 8 + i * 34;
      ctx.fillStyle = texto; ctx.textAlign = 'left';
      var nombre = f.etiqueta.length > 26 ? f.etiqueta.slice(0, 25) + '…' : f.etiqueta;
      ctx.fillText(nombre, 0, y + 15);

      var bx = etiquetaAncho, bw = Math.max(3, (w - etiquetaAncho - 46) * f.valor / max);
      var grad = ctx.createLinearGradient(bx, 0, bx + bw, 0);
      grad.addColorStop(0, oro); grad.addColorStop(1, neon);
      ctx.fillStyle = grad;
      redondeado(ctx, bx, y + 4, bw, 15, 7); ctx.fill();

      ctx.fillStyle = suave; ctx.textAlign = 'right';
      ctx.fillText(String(f.valor), w, y + 15);
    });
  }

  /* --------------------------------------------------------- Donut */
  function donut(cv, datos) {
    var filas = datos.filas || [];
    var alto = 230;
    var l = lienzo(cv, alto), ctx = l.ctx, w = l.w, h = l.h;
    var paleta = datos.colores || [color('--oro', '#D8B36A'), color('--neon', '#6EF3A5'), '#8FA2C4', '#C98F6A', '#9B7FD4', '#6AC9C0', '#D46A9B'];

    var total = 0;
    filas.forEach(function (f) { total += f.valor; });
    if (!total) {
      ctx.fillStyle = color('--suave', '#98A0AE'); ctx.font = '13px Inter, sans-serif'; ctx.textAlign = 'center';
      ctx.fillText('Sin datos todavia', w / 2, h / 2);
      return;
    }

    var cx = w / 2, cy = h / 2, r = Math.min(w, h) / 2 - 14, grosor = r * 0.38;
    var ang = -Math.PI / 2;

    filas.forEach(function (f, i) {
      var porcion = f.valor / total * Math.PI * 2;
      ctx.beginPath();
      ctx.arc(cx, cy, r, ang, ang + porcion);
      ctx.arc(cx, cy, r - grosor, ang + porcion, ang, true);
      ctx.closePath();
      ctx.fillStyle = paleta[i % paleta.length];
      ctx.fill();
      ang += porcion;
    });

    ctx.fillStyle = color('--texto', '#EDEAE3');
    ctx.textAlign = 'center';
    ctx.font = '700 26px Fraunces, Georgia, serif';
    ctx.fillText(String(total), cx, cy + 4);
    ctx.font = '11px Inter, sans-serif';
    ctx.fillStyle = color('--suave', '#98A0AE');
    ctx.fillText(datos.pie || '', cx, cy + 22);
  }

  /* ------------------------------------------------------------- Arranque */
  var pendientes = [];

  function dibujar() {
    pendientes.forEach(function (t) {
      var cv = document.getElementById(t.id);
      if (!cv) { return; }
      if (t.tipo === 'areas')  { areas(cv, t.datos); }
      if (t.tipo === 'barras') { barras(cv, t.datos); }
      if (t.tipo === 'donut')  { donut(cv, t.datos); }
    });
  }

  window.CRGrafica = function (tipo, id, datos) {
    pendientes.push({ tipo: tipo, id: id, datos: datos });
    if (document.readyState !== 'loading') { dibujar(); }
  };

  var temporizador = null;
  window.addEventListener('resize', function () {
    clearTimeout(temporizador);
    temporizador = setTimeout(dibujar, 180);
  });

  // Redibuja al cambiar de tema (los colores vienen de las variables CSS)
  var btn = document.querySelector('.tema');
  if (btn) { btn.addEventListener('click', function () { setTimeout(dibujar, 60); }); }

  document.addEventListener('DOMContentLoaded', dibujar);
})();
