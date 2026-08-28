/*!
 * grafica.js — Motor de gráficas para ResidencialPro
 * Canvas 2D, sin dependencias. Acepta configuración compatible con Chart.js
 * (type / data.labels / data.datasets / options), de modo que si algún día se
 * coloca el archivo oficial chart.umd.min.js en /assets/vendor/, la aplicación
 * lo usará automáticamente sin cambiar una sola línea de las vistas.
 *
 * Tipos: 'line', 'bar', 'doughnut'.
 */
(function (global) {
  'use strict';

  const PX = () => (global.devicePixelRatio || 1);

  function css(nombre, alterno) {
    try {
      const v = getComputedStyle(document.documentElement).getPropertyValue(nombre);
      return (v && v.trim()) || alterno;
    } catch (e) { return alterno; }
  }

  function nice(max) {
    if (max <= 0) return 1;
    const exp = Math.floor(Math.log10(max));
    const base = Math.pow(10, exp);
    const n = max / base;
    let paso;
    if (n <= 1) paso = 1; else if (n <= 2) paso = 2; else if (n <= 2.5) paso = 2.5;
    else if (n <= 5) paso = 5; else paso = 10;
    return paso * base;
  }

  function abreviar(v) {
    const a = Math.abs(v);
    if (a >= 1e6) return (v / 1e6).toFixed(a >= 1e7 ? 0 : 1) + 'M';
    if (a >= 1e3) return (v / 1e3).toFixed(a >= 1e4 ? 0 : 1) + 'k';
    return String(Math.round(v * 100) / 100);
  }

  class Grafica {
    constructor(canvas, config) {
      this.canvas = typeof canvas === 'string' ? document.getElementById(canvas) : canvas;
      if (!this.canvas) throw new Error('Lienzo no encontrado');
      this.ctx = this.canvas.getContext('2d');
      this.config = config || {};
      this.tipo = this.config.type || 'line';
      this.datos = this.config.data || { labels: [], datasets: [] };
      this.opciones = this.config.options || {};
      this.activo = -1;
      this.progreso = 0;
      this._ligar();
      this._animar();
    }

    _ligar() {
      this._redibujar = () => this.dibujar();
      global.addEventListener('resize', this._redibujar, { passive: true });
      this.canvas.addEventListener('mousemove', (e) => {
        const r = this.canvas.getBoundingClientRect();
        this.raton = { x: e.clientX - r.left, y: e.clientY - r.top };
        this.dibujar();
      });
      this.canvas.addEventListener('mouseleave', () => { this.raton = null; this.dibujar(); });
    }

    _animar() {
      const reduce = global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (reduce) { this.progreso = 1; this.dibujar(); return; }
      const inicio = performance.now();
      const paso = (t) => {
        const p = Math.min(1, (t - inicio) / 620);
        this.progreso = 1 - Math.pow(1 - p, 3);
        this.dibujar();
        if (p < 1) requestAnimationFrame(paso);
      };
      requestAnimationFrame(paso);
    }

    update(datos) {
      if (datos) this.datos = datos;
      this.progreso = 0;
      this._animar();
    }

    destroy() {
      global.removeEventListener('resize', this._redibujar);
    }

    _preparar() {
      const dpr = PX();
      const rect = this.canvas.getBoundingClientRect();
      const an = Math.max(220, Math.round(rect.width) || this.canvas.width || 300);
      const al = Math.max(140, Math.round(rect.height) || parseInt(this.canvas.getAttribute('height') || '0', 10) || 200);
      this.canvas.width = Math.round(an * dpr);
      this.canvas.height = Math.round(al * dpr);
      this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      this.ctx.clearRect(0, 0, an, al);
      return { an, al };
    }

    dibujar() {
      const { an, al } = this._preparar();
      const c = this.ctx;
      c.font = '11px Inter, system-ui, sans-serif';
      c.textBaseline = 'middle';
      if (this.tipo === 'doughnut' || this.tipo === 'pie') this._dona(an, al);
      else this._ejes(an, al);
    }

    // -------------------------------------------------- líneas y barras
    _ejes(an, al) {
      const c = this.ctx;
      const sets = (this.datos.datasets || []).filter(d => d && d.data);
      const etiquetas = this.datos.labels || [];
      if (!sets.length || !etiquetas.length) { this._vacio(an, al); return; }

      const colorTexto = css('--texto-3', '#8A8F8B');
      const colorRejilla = css('--linea', '#E3DDCE');

      let max = 0;
      sets.forEach(d => d.data.forEach(v => { if (+v > max) max = +v; }));
      const pasoY = nice(max / 4) || 1;
      const tope = Math.max(pasoY * 4, Math.ceil(max / pasoY) * pasoY) || 1;

      const izq = 46, der = 12, arr = 14, aba = 30;
      const w = Math.max(10, an - izq - der);
      const h = Math.max(10, al - arr - aba);

      // Rejilla horizontal
      c.strokeStyle = colorRejilla;
      c.fillStyle = colorTexto;
      c.lineWidth = 1;
      c.textAlign = 'right';
      const lineas = 4;
      for (let i = 0; i <= lineas; i++) {
        const y = Math.round(arr + h - (h * i / lineas)) + 0.5;
        c.beginPath(); c.moveTo(izq, y); c.lineTo(izq + w, y); c.stroke();
        c.fillText(abreviar(tope * i / lineas), izq - 8, y);
      }

      // Etiquetas del eje X, sin superposiciones.
      c.textAlign = 'center';
      const posX = (i) => this.tipo === 'bar'
        ? izq + (w * (i + 0.5) / etiquetas.length)
        : izq + (etiquetas.length === 1 ? w / 2 : (w * i / (etiquetas.length - 1)));
      let ultimoFin = -Infinity;
      etiquetas.forEach((et, i) => {
        const texto = String(et);
        const medio = posX(i);
        const mitad = c.measureText(texto).width / 2;
        if (medio - mitad < ultimoFin + 8) return;      // se solaparía con la anterior
        if (medio + mitad > izq + w + 10) return;        // se saldría del área
        c.fillText(texto, medio, al - aba + 13);
        ultimoFin = medio + mitad;
      });

      // Punto más cercano al ratón
      let idxActivo = -1;
      if (this.raton && this.raton.x >= izq && this.raton.x <= izq + w) {
        const rel = (this.raton.x - izq) / w;
        idxActivo = Math.round(rel * (etiquetas.length - 1));
        if (this.tipo === 'bar') idxActivo = Math.min(etiquetas.length - 1, Math.floor(rel * etiquetas.length));
      }

      if (this.tipo === 'bar') this._barras(sets, etiquetas, izq, arr, w, h, tope, idxActivo);
      else this._lineas(sets, etiquetas, izq, arr, w, h, tope, idxActivo);

      if (idxActivo >= 0) this._tooltip(sets, etiquetas, idxActivo, izq, arr, w, h, tope, an, al);
      this._leyenda(sets, an, al);
    }

    _lineas(sets, etiquetas, izq, arr, w, h, tope, idxActivo) {
      const c = this.ctx;
      const n = etiquetas.length;
      sets.forEach((d) => {
        const color = d.borderColor || css('--arcilla', '#C9A961');
        const puntos = d.data.map((v, i) => ({
          x: izq + (n === 1 ? w / 2 : w * i / (n - 1)),
          y: arr + h - (h * Math.max(0, +v) / tope) * this.progreso,
        }));
        if (d.fill !== false) {
          const g = c.createLinearGradient(0, arr, 0, arr + h);
          g.addColorStop(0, this._alfa(color, .26));
          g.addColorStop(1, this._alfa(color, 0));
          c.fillStyle = g;
          c.beginPath();
          c.moveTo(puntos[0].x, arr + h);
          puntos.forEach(p => c.lineTo(p.x, p.y));
          c.lineTo(puntos[puntos.length - 1].x, arr + h);
          c.closePath(); c.fill();
        }
        c.strokeStyle = color;
        c.lineWidth = d.borderWidth || 2.4;
        c.lineJoin = 'round'; c.lineCap = 'round';
        if (d.borderDash) c.setLineDash(d.borderDash); else c.setLineDash([]);
        c.beginPath();
        puntos.forEach((p, i) => (i ? c.lineTo(p.x, p.y) : c.moveTo(p.x, p.y)));
        c.stroke();
        c.setLineDash([]);
        puntos.forEach((p, i) => {
          const r = i === idxActivo ? 5 : 3;
          c.fillStyle = css('--lienzo', '#fff');
          c.beginPath(); c.arc(p.x, p.y, r + 1.4, 0, Math.PI * 2); c.fill();
          c.fillStyle = color;
          c.beginPath(); c.arc(p.x, p.y, r, 0, Math.PI * 2); c.fill();
        });
      });
    }

    _barras(sets, etiquetas, izq, arr, w, h, tope, idxActivo) {
      const c = this.ctx;
      const n = etiquetas.length;
      const anchoGrupo = w / n;
      const cuantos = sets.length;
      const anchoBarra = Math.max(5, (anchoGrupo * 0.62) / cuantos);
      sets.forEach((d, s) => {
        const color = d.backgroundColor || css('--arcilla', '#C9A961');
        d.data.forEach((v, i) => {
          const alto = Math.max(0, (h * Math.max(0, +v) / tope) * this.progreso);
          const x = izq + anchoGrupo * i + anchoGrupo * 0.19 + anchoBarra * s;
          const y = arr + h - alto;
          c.fillStyle = Array.isArray(color) ? color[i % color.length] : color;
          if (i === idxActivo) c.globalAlpha = 1; else c.globalAlpha = idxActivo >= 0 ? .72 : 1;
          const r = Math.max(0, Math.min(4, anchoBarra / 2, alto));
          const ancho = Math.max(1, anchoBarra - 2);
          c.beginPath();
          if (c.roundRect) c.roundRect(x, y, ancho, alto, [r, r, 0, 0]);
          else c.rect(x, y, ancho, alto);
          c.fill();
          c.globalAlpha = 1;
        });
      });
    }

    _tooltip(sets, etiquetas, i, izq, arr, w, h, tope, an, al) {
      const c = this.ctx;
      const lineas = sets.map(d => (d.label || '') + ': ' + this._fmt(d.data[i]));
      const titulo = String(etiquetas[i]);
      c.font = '600 11px Inter, system-ui, sans-serif';
      let ancho = c.measureText(titulo).width;
      c.font = '11px Inter, system-ui, sans-serif';
      lineas.forEach(l => { ancho = Math.max(ancho, c.measureText(l).width); });
      const pad = 9;
      const cajaW = ancho + pad * 2;
      const cajaH = 20 + lineas.length * 15;
      let x = izq + (etiquetas.length === 1 ? w / 2 : w * i / (etiquetas.length - 1)) + 12;
      if (this.tipo === 'bar') x = izq + (w * (i + 0.5) / etiquetas.length) + 12;
      if (x + cajaW > an - 4) x -= cajaW + 24;
      const y = Math.max(4, Math.min(al - cajaH - 4, arr + 8));

      c.fillStyle = css('--petroleo', '#0F2E24');
      c.globalAlpha = .96;
      c.beginPath();
      if (c.roundRect) c.roundRect(x, y, cajaW, cajaH, 8); else c.rect(x, y, cajaW, cajaH);
      c.fill();
      c.globalAlpha = 1;
      c.textAlign = 'left';
      c.fillStyle = css('--arcilla-3', '#E0C489');
      c.font = '600 11px Inter, system-ui, sans-serif';
      c.fillText(titulo, x + pad, y + 13);
      c.fillStyle = '#E9EEE9';
      c.font = '11px Inter, system-ui, sans-serif';
      lineas.forEach((l, k) => c.fillText(l, x + pad, y + 30 + k * 15));
    }

    _leyenda(sets, an, al) {
      if (this.opciones.leyenda === false || sets.length < 2) return;
      const c = this.ctx;
      c.font = '11px Inter, system-ui, sans-serif';
      c.textAlign = 'left';
      let x = 46;
      sets.forEach(d => {
        const color = d.borderColor || d.backgroundColor || css('--arcilla', '#C9A961');
        c.fillStyle = Array.isArray(color) ? color[0] : color;
        c.beginPath(); c.arc(x + 4, al - 6, 4, 0, Math.PI * 2); c.fill();
        c.fillStyle = css('--texto-3', '#8A8F8B');
        const t = d.label || '';
        c.fillText(t, x + 13, al - 5);
        x += 13 + c.measureText(t).width + 18;
      });
    }

    _dona(an, al) {
      const c = this.ctx;
      const set = (this.datos.datasets || [])[0];
      const etiquetas = this.datos.labels || [];
      if (!set || !set.data || !set.data.length) { this._vacio(an, al); return; }
      const total = set.data.reduce((a, b) => a + Math.max(0, +b), 0);
      if (total <= 0) { this._vacio(an, al); return; }

      const leyendaAncho = an > 420 ? Math.min(190, an * 0.42) : 0;
      const cx = (an - leyendaAncho) / 2;
      const cy = al / 2;
      const radio = Math.max(30, Math.min(cx, cy) - 12);
      const interior = this.tipo === 'pie' ? 0 : radio * 0.62;
      let ang = -Math.PI / 2;
      const colores = set.backgroundColor || [css('--arcilla', '#C9A961')];

      set.data.forEach((v, i) => {
        const porcion = (Math.max(0, +v) / total) * Math.PI * 2 * this.progreso;
        c.beginPath();
        c.moveTo(cx, cy);
        c.arc(cx, cy, radio, ang, ang + porcion);
        c.closePath();
        c.fillStyle = Array.isArray(colores) ? colores[i % colores.length] : colores;
        c.fill();
        ang += porcion;
      });
      if (interior > 0) {
        c.globalCompositeOperation = 'destination-out';
        c.beginPath(); c.arc(cx, cy, interior, 0, Math.PI * 2); c.fill();
        c.globalCompositeOperation = 'source-over';
        if (this.opciones.centro) {
          c.textAlign = 'center';
          c.fillStyle = css('--texto-3', '#8A8F8B');
          c.font = '10px Inter, system-ui, sans-serif';
          c.fillText(this.opciones.centro.etiqueta || '', cx, cy - 9);
          c.fillStyle = css('--petroleo', '#0F2E24');
          c.font = '600 17px Inter, system-ui, sans-serif';
          c.fillText(this.opciones.centro.valor || '', cx, cy + 10);
        }
      }
      if (leyendaAncho > 0) {
        c.textAlign = 'left';
        c.font = '11.5px Inter, system-ui, sans-serif';
        const x = an - leyendaAncho + 6;
        const altura = Math.min(22, (al - 16) / Math.max(1, etiquetas.length));
        let y = cy - (etiquetas.length * altura) / 2 + altura / 2;
        etiquetas.forEach((et, i) => {
          c.fillStyle = Array.isArray(colores) ? colores[i % colores.length] : colores;
          c.beginPath();
          if (c.roundRect) c.roundRect(x, y - 4, 9, 9, 2); else c.rect(x, y - 4, 9, 9);
          c.fill();
          c.fillStyle = css('--texto-2', '#545B51');
          const pct = Math.round((set.data[i] / total) * 100);
          let t = String(et);
          while (c.measureText(t + '  ' + pct + '%').width > leyendaAncho - 30 && t.length > 4) {
            t = t.slice(0, -2) + '…';
          }
          c.fillText(t, x + 15, y);
          c.textAlign = 'right';
          c.fillStyle = css('--texto-3', '#8A8F8B');
          c.fillText(pct + '%', an - 6, y);
          c.textAlign = 'left';
          y += altura;
        });
      }
    }

    _vacio(an, al) {
      const c = this.ctx;
      c.textAlign = 'center';
      c.fillStyle = css('--texto-3', '#8A8F8B');
      c.font = '12px Inter, system-ui, sans-serif';
      c.fillText('Sin datos para mostrar', an / 2, al / 2);
    }

    _fmt(v) {
      const f = this.opciones.formato;
      if (f === 'moneda') return 'Q' + Number(v).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      return Number(v).toLocaleString('es-GT');
    }

    _alfa(color, a) {
      if (color.startsWith('#')) {
        const h = color.slice(1);
        const f = h.length === 3 ? h.split('').map(x => x + x).join('') : h;
        const n = parseInt(f, 16);
        return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${a})`;
      }
      if (color.startsWith('rgb(')) return color.replace('rgb(', 'rgba(').replace(')', `,${a})`);
      return color;
    }
  }

  global.Grafica = Grafica;
})(window);
