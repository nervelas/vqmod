/* Esfera de reloj para elegir la hora (SVG propio, sin dependencias).
 * API: var d = new AgendaDial(host, {onSelect(slot), label(slot)});
 *      d.setSlots([{id, mins, start, seats}]); d.setHalf('am'|'pm'); d.select(id); d.clear(); d.refresh(); d.counts()
 * Accesible: grupo de opciones con tabindex itinerante, flechas / Inicio / Fin / Enter / Espacio, y arrastre con puntero. */
(function (global) {
  'use strict';
  var NS = 'http://www.w3.org/2000/svg';
  var C = 160, uid = 0;

  function el(name, attrs, parent) {
    var n = document.createElementNS(NS, name);
    for (var k in attrs) { if (Object.prototype.hasOwnProperty.call(attrs, k)) n.setAttribute(k, attrs[k]); }
    if (parent) parent.appendChild(n);
    return n;
  }
  function pt(r, deg) { var a = (deg - 90) * Math.PI / 180; return [C + r * Math.cos(a), C + r * Math.sin(a)]; }
  function f(n) { return Math.round(n * 100) / 100; }
  function ang(mins) { return ((mins % 720) / 720) * 360; }
  function reduced() { return global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches; }

  function arcPath(r, a0, a1) {
    var p0 = pt(r, a0), p1 = pt(r, a1), large = (a1 - a0) > 180 ? 1 : 0;
    return 'M' + f(p0[0]) + ' ' + f(p0[1]) + 'A' + r + ' ' + r + ' 0 ' + large + ' 1 ' + f(p1[0]) + ' ' + f(p1[1]);
  }

  function Dial(host, opts) {
    this.host = host;
    this.opts = opts || {};
    this.slots = [];
    this.half = 'am';
    this.selId = null;
    this.rot = 0;
    this.focusIdx = 0;
    this.dragging = false;
    this.build();
  }

  Dial.prototype.build = function () {
    var self = this, id = 'dl' + (++uid);
    var svg = el('svg', { viewBox: '0 0 320 320', class: 'dial-svg', role: 'radiogroup', 'aria-label': 'Horarios disponibles en esfera de reloj. Usa las flechas para moverte entre horas.', focusable: 'false' });
    this.svg = svg;
    var defs = el('defs', {}, svg);
    var g1 = el('linearGradient', { id: id + 'g', x1: '0', y1: '0', x2: '1', y2: '1' }, defs);
    el('stop', { offset: '0', class: 'dg-gold3' }, g1); el('stop', { offset: '.5', class: 'dg-gold2' }, g1); el('stop', { offset: '1', class: 'dg-gold1' }, g1);
    var g2 = el('radialGradient', { id: id + 'f', cx: '.5', cy: '.42', r: '.65' }, defs);
    el('stop', { offset: '0', class: 'dg-face0' }, g2); el('stop', { offset: '1', class: 'dg-face1' }, g2);
    this.gradId = id + 'g';

    // Caja del reloj: aro de latón y fondo
    el('circle', { cx: C, cy: C, r: 156, class: 'dial-case' }, svg);
    el('circle', { cx: C, cy: C, r: 150, class: 'dial-bezel', stroke: 'url(#' + this.gradId + ')' }, svg);
    el('circle', { cx: C, cy: C, r: 146, class: 'dial-face', fill: 'url(#' + id + 'f)' }, svg);

    // Guilloché: dos rosetas superpuestas
    var d1 = '', d2 = '', i, t, r;
    for (i = 0; i <= 360; i += 1) {
      t = i * Math.PI / 180;
      r = 139 + 3.2 * Math.sin(30 * t) + 1.6 * Math.sin(60 * t);
      var p = [C + r * Math.cos(t), C + r * Math.sin(t)];
      d1 += (i ? 'L' : 'M') + f(p[0]) + ' ' + f(p[1]);
      r = 139 + 3.2 * Math.sin(30 * t + 1.57) + 1.6 * Math.cos(60 * t);
      p = [C + r * Math.cos(t), C + r * Math.sin(t)];
      d2 += (i ? 'L' : 'M') + f(p[0]) + ' ' + f(p[1]);
    }
    el('path', { d: d1 + 'Z', class: 'dial-guil' }, svg);
    el('path', { d: d2 + 'Z', class: 'dial-guil dial-guil-2' }, svg);

    // Marcas de minutos (cada 10 min de la esfera de 12 h): 72 marcas
    var minor = '', mid = '', major = '', a, o, n;
    for (i = 0; i < 72; i++) {
      a = i * 5;
      if (i % 6 === 0) { o = pt(128, a); n = pt(114, a); major += 'M' + f(o[0]) + ' ' + f(o[1]) + 'L' + f(n[0]) + ' ' + f(n[1]); }
      else if (i % 3 === 0) { o = pt(128, a); n = pt(118, a); mid += 'M' + f(o[0]) + ' ' + f(o[1]) + 'L' + f(n[0]) + ' ' + f(n[1]); }
      else { o = pt(128, a); n = pt(122, a); minor += 'M' + f(o[0]) + ' ' + f(o[1]) + 'L' + f(n[0]) + ' ' + f(n[1]); }
    }
    el('path', { d: minor, class: 'dial-tick' }, svg);
    el('path', { d: mid, class: 'dial-tick dial-tick-mid' }, svg);
    el('path', { d: major, class: 'dial-tick dial-tick-major' }, svg);

    // Números
    this.nums = [];
    for (i = 0; i < 12; i++) {
      var q = pt(100, i * 30);
      var tx = el('text', { x: f(q[0]), y: f(q[1]), class: 'dial-num', 'text-anchor': 'middle', 'dominant-baseline': 'central', 'aria-hidden': 'true' }, svg);
      this.nums.push(tx);
    }

    el('circle', { cx: C, cy: C, r: 84, class: 'dial-track' }, svg);
    this.arcsG = el('g', { class: 'dial-arcs', 'aria-hidden': 'true' }, svg);
    this.needle = el('g', { class: 'dial-needle is-idle', 'aria-hidden': 'true' }, svg);
    el('path', { d: 'M160 176 L160 70', class: 'dial-hand-glow' }, this.needle);
    el('path', { d: 'M156 176 L160 62 L164 176 Z', class: 'dial-hand', fill: 'url(#' + this.gradId + ')' }, this.needle);
    el('circle', { cx: C, cy: 62, r: 3.4, class: 'dial-tip' }, this.needle);
    el('circle', { cx: C, cy: 176, r: 7, class: 'dial-tail' }, this.needle);
    this.slotsG = el('g', { class: 'dial-slots' }, svg);
    el('circle', { cx: C, cy: C, r: 9, class: 'dial-hub', fill: 'url(#' + this.gradId + ')' }, svg);
    el('circle', { cx: C, cy: C, r: 3, class: 'dial-hub-in' }, svg);
    this.readout = el('text', { x: C, y: 214, class: 'dial-read', 'text-anchor': 'middle', 'aria-hidden': 'true' }, svg);
    this.sub = el('text', { x: C, y: 232, class: 'dial-sub', 'text-anchor': 'middle', 'aria-hidden': 'true' }, svg);

    this.host.appendChild(svg);

    // Arrastre con puntero
    svg.addEventListener('pointerdown', function (e) { self.onPointer(e, true); });
    svg.addEventListener('pointermove', function (e) { if (self.dragging) self.onPointer(e, false); });
    var end = function (e) { if (self.dragging) { self.dragging = false; try { svg.releasePointerCapture(e.pointerId); } catch (x) { /* nada */ } self.svg.classList.remove('is-drag'); } };
    svg.addEventListener('pointerup', end); svg.addEventListener('pointercancel', end);
    svg.addEventListener('keydown', function (e) { self.onKey(e); });
    this.setNumbers();
  };

  Dial.prototype.label = function (s) { return this.opts.label ? this.opts.label(s) : String(s.mins); };

  Dial.prototype.setNumbers = function () {
    var is24 = !!this.is24;
    for (var i = 0; i < 12; i++) {
      var v;
      if (is24) { v = (this.half === 'pm' ? 12 : 0) + i; v = (v < 10 ? '0' : '') + v; } else { v = i === 0 ? 12 : i; }
      this.nums[i].textContent = String(v);
    }
  };

  Dial.prototype.setFormat = function (is24) { this.is24 = !!is24; this.setNumbers(); this.refresh(); };

  Dial.prototype.inHalf = function () {
    var h = this.half; return this.slots.filter(function (s) { return (s.mins < 720) === (h === 'am'); });
  };

  Dial.prototype.counts = function () {
    var am = 0, pm = 0; this.slots.forEach(function (s) { if (s.mins < 720) am++; else pm++; }); return { am: am, pm: pm };
  };

  Dial.prototype.setSlots = function (slots) {
    this.slots = (slots || []).slice().sort(function (a, b) { return a.mins - b.mins; });
    this.selId = null;
    var c = this.counts();
    this.half = c.am > 0 || c.pm === 0 ? 'am' : 'pm';
    this.render();
  };

  Dial.prototype.setHalf = function (h, silent) {
    if (h !== 'am' && h !== 'pm') return;
    this.half = h; this.setNumbers(); this.render();
    if (!silent && this.opts.onHalf) this.opts.onHalf(h);
  };

  Dial.prototype.refresh = function () { this.render(); };

  Dial.prototype.render = function () {
    var self = this, list = this.inHalf();
    this.setNumbers();
    while (this.arcsG.firstChild) this.arcsG.removeChild(this.arcsG.firstChild);
    while (this.slotsG.firstChild) this.slotsG.removeChild(this.slotsG.firstChild);

    // Franjas libres: arcos continuos de oro (se unen los horarios contiguos)
    var step = 30;
    if (list.length > 1) { step = 720; for (var i = 1; i < list.length; i++) { var dd = list[i].mins - list[i - 1].mins; if (dd > 0 && dd < step) step = dd; } step = Math.max(5, Math.min(step, 60)); }
    var ranges = [], cur = null;
    list.forEach(function (s) {
      var a = s.mins, b = s.mins + step;
      if (cur && a <= cur[1]) { cur[1] = Math.max(cur[1], b); } else { cur = [a, b]; ranges.push(cur); }
    });
    ranges.forEach(function (r) {
      var a0 = ang(r[0]), a1 = ang(r[0]) + Math.min(359, (r[1] - r[0]) / 720 * 360);
      el('path', { d: arcPath(84, a0, a1), class: 'dial-arc-glow' }, self.arcsG);
      el('path', { d: arcPath(84, a0, a1), class: 'dial-arc', stroke: 'url(#' + self.gradId + ')' }, self.arcsG);
    });

    // Puntos accesibles
    var selIdx = -1;
    list.forEach(function (s, idx) {
      var a = ang(s.mins), p = pt(84, a);
      var checked = s.id === self.selId;
      if (checked) selIdx = idx;
      var g = el('g', { class: 'dial-slot' + (checked ? ' is-sel' : ''), role: 'radio', tabindex: '-1', 'aria-checked': checked ? 'true' : 'false', 'aria-label': self.slotLabel(s), 'data-id': s.id }, self.slotsG);
      el('circle', { cx: f(p[0]), cy: f(p[1]), r: 14, class: 'dial-focus' }, g);
      el('circle', { cx: f(p[0]), cy: f(p[1]), r: 4.6, class: 'dial-dot' }, g);
      el('circle', { cx: f(p[0]), cy: f(p[1]), r: 11, class: 'dial-hit' }, g);
      g.addEventListener('click', function (e) { e.stopPropagation(); self.choose(s.id); });
      g.addEventListener('focus', function () { self.focusIdx = idx; });
    });
    this.focusIdx = selIdx >= 0 ? selIdx : 0;
    this.syncTab();
    this.syncNeedle(false);
    this.syncReadout();
  };

  Dial.prototype.slotLabel = function (s) {
    var t = this.label(s) + ', disponible';
    if (s.seats !== null && s.seats !== undefined) t += ', quedan ' + s.seats + (s.seats === 1 ? ' lugar' : ' lugares');
    return t;
  };

  Dial.prototype.syncTab = function () {
    var nodes = this.slotsG.children, i;
    for (i = 0; i < nodes.length; i++) nodes[i].setAttribute('tabindex', i === this.focusIdx ? '0' : '-1');
  };

  Dial.prototype.syncNeedle = function (animate) {
    var list = this.inHalf(), s = null, i;
    for (i = 0; i < list.length; i++) { if (list[i].id === this.selId) s = list[i]; }
    var idle = !s;
    if (!s) s = list[0];
    this.needle.classList.toggle('is-idle', idle);
    if (!s) { this.needle.classList.add('is-idle'); return; }
    var target = ang(s.mins), delta = ((target - this.rot) % 360 + 540) % 360 - 180;
    this.rot = this.rot + delta;
    this.needle.style.transition = animate && !reduced() ? '' : 'none';
    this.needle.style.transform = 'rotate(' + this.rot + 'deg)';
    if (!animate || reduced()) { /* forzar reflujo para que la transición vuelva a activarse */ void this.needle.getBoundingClientRect(); this.needle.style.transition = ''; }
  };

  Dial.prototype.syncReadout = function () {
    var s = null, i;
    for (i = 0; i < this.slots.length; i++) { if (this.slots[i].id === this.selId) s = this.slots[i]; }
    if (s) {
      var parts = this.label(s).split(' ');
      this.readout.textContent = parts[0];
      this.sub.textContent = parts.slice(1).join(' ');
    } else {
      this.readout.textContent = '--:--';
      this.sub.textContent = this.inHalf().length ? 'elige una hora' : 'sin horarios';
    }
  };

  Dial.prototype.choose = function (id, silent) {
    var s = null, i;
    for (i = 0; i < this.slots.length; i++) { if (this.slots[i].id === id) s = this.slots[i]; }
    if (!s) return;
    var wantHalf = s.mins < 720 ? 'am' : 'pm';
    if (wantHalf !== this.half) { this.half = wantHalf; this.selId = id; this.setNumbers(); this.render(); if (this.opts.onHalf) this.opts.onHalf(wantHalf); }
    else {
      this.selId = id;
      var nodes = this.slotsG.children, list = this.inHalf();
      for (i = 0; i < nodes.length; i++) {
        var on = list[i] && list[i].id === id;
        nodes[i].classList.toggle('is-sel', on); nodes[i].setAttribute('aria-checked', on ? 'true' : 'false');
        if (on) this.focusIdx = i;
      }
      this.syncTab(); this.syncNeedle(true); this.syncReadout();
    }
    if (!silent && this.opts.onSelect) this.opts.onSelect(s);
  };

  Dial.prototype.select = function (id) { this.choose(id, true); };
  Dial.prototype.clear = function () { this.selId = null; this.render(); };

  Dial.prototype.focusCurrent = function () {
    var n = this.slotsG.children[this.focusIdx]; if (n && n.focus) n.focus();
  };

  Dial.prototype.nearest = function (e) {
    var list = this.inHalf(); if (!list.length) return null;
    var rect = this.svg.getBoundingClientRect(), k = 320 / rect.width;
    var x = (e.clientX - rect.left) * k - C, y = (e.clientY - rect.top) * k - C, dist = Math.sqrt(x * x + y * y);
    var a = (Math.atan2(y, x) * 180 / Math.PI + 90 + 360) % 360, best = null, bd = 1e9;
    list.forEach(function (s) { var d = Math.abs(((ang(s.mins) - a) % 360 + 540) % 360 - 180); if (d < bd) { bd = d; best = s; } });
    return { slot: best, dist: dist };
  };

  /* Ratón y lápiz: arrastrar la aguja. Táctil: un toque elige el horario más cercano (así no se bloquea el desplazamiento). */
  Dial.prototype.onPointer = function (e, start) {
    var self = this;
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    if (e.pointerType === 'touch') {
      if (!start) return;
      var x0 = e.clientX, y0 = e.clientY;
      var up = function (u) {
        self.svg.removeEventListener('pointerup', up); self.svg.removeEventListener('pointercancel', cancel);
        if (Math.abs(u.clientX - x0) < 10 && Math.abs(u.clientY - y0) < 10) {
          var r = self.nearest(u); if (r && r.dist >= 24 && r.dist <= 152 && r.slot.id !== self.selId) self.choose(r.slot.id); else if (r && r.slot && r.dist >= 24 && r.dist <= 152) self.choose(r.slot.id);
        }
      };
      var cancel = function () { self.svg.removeEventListener('pointerup', up); self.svg.removeEventListener('pointercancel', cancel); };
      this.svg.addEventListener('pointerup', up); this.svg.addEventListener('pointercancel', cancel);
      return;
    }
    var r2 = this.nearest(e); if (!r2) return;
    if (start && (r2.dist < 24 || r2.dist > 152)) return;
    if (start) { this.dragging = true; this.svg.classList.add('is-drag'); try { this.svg.setPointerCapture(e.pointerId); } catch (x2) { /* nada */ } }
    if (r2.slot.id !== this.selId) this.choose(r2.slot.id);
    if (e.cancelable) e.preventDefault();
  };

  Dial.prototype.onKey = function (e) {
    var key = e.key, list = this.inHalf(), all = this.slots, idx = this.focusIdx, nodes = this.slotsG.children;
    if (!list.length) return;
    var move = 0;
    if (key === 'ArrowRight' || key === 'ArrowDown') move = 1;
    else if (key === 'ArrowLeft' || key === 'ArrowUp') move = -1;
    else if (key === 'Home') { idx = 0; }
    else if (key === 'End') { idx = list.length - 1; }
    else if (key === 'Enter' || key === ' ') { if (list[idx]) { this.choose(list[idx].id); } e.preventDefault(); return; }
    else if (key === 'PageUp' || key === 'PageDown') {
      var to = key === 'PageDown' ? 'pm' : 'am';
      if (to !== this.half && this.counts()[to] > 0) { this.setHalf(to); this.focusIdx = 0; this.syncTab(); this.focusCurrent(); }
      e.preventDefault(); return;
    } else return;
    e.preventDefault();
    if (move) {
      idx += move;
      if (idx < 0 || idx >= list.length) {
        // pasar a la otra mitad del día si tiene horarios
        var to2 = this.half === 'am' ? 'pm' : 'am';
        if (this.counts()[to2] > 0) {
          this.setHalf(to2); var l2 = this.inHalf(); this.focusIdx = move > 0 ? 0 : l2.length - 1; this.syncTab(); this.focusCurrent(); return;
        }
        idx = Math.max(0, Math.min(list.length - 1, idx));
      }
    }
    this.focusIdx = idx; this.syncTab();
    if (nodes[idx] && nodes[idx].focus) nodes[idx].focus();
  };

  global.AgendaDial = Dial;
})(window);
