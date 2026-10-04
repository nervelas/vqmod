/* Reserva pública: pasos con cortina, calendario, esfera/lista, datos, confirmación. JS vanilla ES2020, sin cookies. */
(function (global) {
  'use strict';
  var doc = document;
  var DAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
  var MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

  // ---------------------------------------------------------------- utilidades
  function $(s, r) { return (r || doc).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || doc).querySelectorAll(s)); }
  function pad(n) { return n < 10 ? '0' + n : String(n); }
  function reduced() { return global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches; }
  function store(op, k, v) {
    try { if (op === 'get') return global.localStorage.getItem(k); global.localStorage.setItem(k, v); } catch (e) { /* sin almacenamiento */ }
    return null;
  }
  function parseYmd(s) { var p = s.split('-'); return { y: +p[0], m: +p[1], d: +p[2] }; }
  function ymd(y, m, d) { return y + '-' + pad(m) + '-' + pad(d); }
  function dow(y, m, d) { return new Date(Date.UTC(y, m - 1, d)).getUTCDay(); }
  function daysIn(y, m) { return new Date(Date.UTC(y, m, 0)).getUTCDate(); }
  function dayLabel(s, withYear) {
    var p = parseYmd(s);
    return DAYS[dow(p.y, p.m, p.d)] + ' ' + p.d + ' de ' + MONTHS[p.m - 1] + (withYear ? ' de ' + p.y : '');
  }
  function fmtTime(t, is24) {
    var h = +t.slice(0, 2), m = t.slice(3, 5);
    if (is24) return pad(h) + ':' + m;
    return (h % 12 || 12) + ':' + m + ' ' + (h < 12 ? 'a. m.' : 'p. m.');
  }
  function todayIn(tz) {
    try {
      var parts = new Intl.DateTimeFormat('en-CA', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
      if (/^\d{4}-\d{2}-\d{2}$/.test(parts)) return parts;
    } catch (e) { /* zona no soportada */ }
    var d = new Date(); return ymd(d.getFullYear(), d.getMonth() + 1, d.getDate());
  }
  function addDays(s, n) { var p = parseYmd(s), d = new Date(Date.UTC(p.y, p.m - 1, p.d + n)); return ymd(d.getUTCFullYear(), d.getUTCMonth() + 1, d.getUTCDate()); }
  function guessTz() { try { return Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { return ''; } }
  function hex16() {
    var a = new Uint8Array(8), s = '';
    if (global.crypto && global.crypto.getRandomValues) { global.crypto.getRandomValues(a); } else { for (var i = 0; i < 8; i++) a[i] = Math.floor(Math.random() * 256); }
    for (var j = 0; j < 8; j++) s += (a[j] < 16 ? '0' : '') + a[j].toString(16);
    return s;
  }
  function request(url, opts) {
    opts = opts || {};
    var h = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
    if (opts.csrf) h['X-CSRF-Token'] = opts.csrf;
    var init = { method: opts.method || 'GET', headers: h, credentials: 'omit' };
    if (opts.json) { h['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.json); }
    if (opts.form) { init.body = opts.form; }
    return fetch(url, init).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'Respuesta inesperada del servidor.' }; }).then(function (d) { return { status: r.status, data: d || {} }; });
    }).catch(function () { return { status: 0, data: { ok: false, error: 'Parece que no hay conexión. Revisa tu internet e inténtalo de nuevo.' } }; });
  }
  function el(tag, cls, text) { var n = doc.createElement(tag); if (cls) n.className = cls; if (text !== undefined) n.textContent = text; return n; }

  // ---------------------------------------------------------------- Selector de fecha y hora
  function Scheduler(root, cfg) {
    this.root = root; this.cfg = cfg;
    this.tz = cfg.tz; this.is24 = !!cfg.is24;
    this.view = store('get', 'ap_view') === 'list' ? 'list' : 'dial';
    this.month = null; this.days = {}; this.loaded = {}; this.ctx = '';
    this.date = null; this.slot = null; this.focusDate = null; this.reqId = 0; this.nextSlot = null;
    this.q = {
      calTitle: $('[data-cal-title]', root), calDays: $('[data-cal-days]', root), prev: $('[data-cal-prev]', root), next: $('[data-cal-next]', root),
      tz: $('[data-tz]', root), dayTitle: $('[data-day-title]', root), live: $('[data-live]', root),
      nextBox: $('[data-next]', root), nextBtn: $('[data-next-btn]', root), nextWhen: $('[data-next-when]', root),
      dialView: $('[data-view-dial]', root), listView: $('[data-view-list]', root), dialHost: $('[data-dial]', root),
      errText: $('[data-error-text]', root), noneText: $('[data-none-text]', root)
    };
    this.dial = new global.AgendaDial(this.q.dialHost, {
      label: function (s) { return fmtTime(s.t, this.is24); }.bind(this),
      onSelect: function (s) { this.pickSlot(this.byId(s.id), true); }.bind(this),
      onHalf: function () { this.syncAmPm(); }.bind(this)
    });
    this.dial.is24 = this.is24;
    this.bind();
  }

  Scheduler.prototype.ctxKey = function () { return [this.cfg.getDuration(), this.cfg.getHost(), this.tz].join('|'); };
  Scheduler.prototype.byId = function (id) { var l = this.days[this.date] || []; for (var i = 0; i < l.length; i++) { if (l[i].s === id) return l[i]; } return null; };

  Scheduler.prototype.bind = function () {
    var self = this, q = this.q;
    q.prev.addEventListener('click', function () { self.shiftMonth(-1); });
    q.next.addEventListener('click', function () { self.shiftMonth(1); });
    q.tz.addEventListener('change', function () { self.setTz(q.tz.value); });
    $$('[data-fmt]', this.root).forEach(function (b) { b.addEventListener('click', function () { self.setFormat(b.getAttribute('data-fmt') === '24'); }); });
    $$('[data-view]', this.root).forEach(function (b) { b.addEventListener('click', function () { self.setView(b.getAttribute('data-view')); }); });
    $$('[data-ampm]', this.root).forEach(function (b) { b.addEventListener('click', function () {
      if (b.getAttribute('aria-disabled') === 'true') return;
      self.dial.setHalf(b.getAttribute('data-ampm')); self.syncAmPm();
    }); });
    var retry = $('[data-retry]', this.root); if (retry) retry.addEventListener('click', function () { self.refresh(); });
    var ow = $('[data-open-waitlist]', this.root); if (ow) ow.addEventListener('click', function () { if (self.cfg.onWaitlist) self.cfg.onWaitlist(); });
    q.nextBtn.addEventListener('click', function () { if (self.nextSlot) self.jumpTo(self.nextSlot); });
    q.calDays.addEventListener('keydown', function (e) { self.onCalKey(e); });
    q.calDays.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('.cal-day') : null;
      if (b && !b.disabled) self.pickDate(b.getAttribute('data-date'));
    });
    // Listado: flechas entre horarios
    q.listView.addEventListener('keydown', function (e) {
      var btns = $$('.slot-btn', q.listView), i = btns.indexOf(doc.activeElement);
      if (i < 0) return;
      var n = -1;
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown') n = Math.min(btns.length - 1, i + 1);
      else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') n = Math.max(0, i - 1);
      else if (e.key === 'Home') n = 0; else if (e.key === 'End') n = btns.length - 1;
      if (n >= 0) { e.preventDefault(); btns[n].focus(); }
    });
    $$('[data-fmt]', this.root).forEach(function (b) { b.setAttribute('aria-pressed', (b.getAttribute('data-fmt') === '24') === self.is24 ? 'true' : 'false'); });
  };

  Scheduler.prototype.start = function () {
    var self = this, t = todayIn(this.tz), p = parseYmd(t);
    this.q.tz.value = this.tz;
    if (this.q.tz.value !== this.tz) { var o = el('option', '', this.tz); o.value = this.tz; this.q.tz.insertBefore(o, this.q.tz.firstChild); this.q.tz.value = this.tz; }
    this.month = { y: p.y, m: p.m };
    this.ctx = this.ctxKey();
    this.setView(this.view, true);
    this.setState('loading');
    this.fetchRange(this.month, true).then(function (res) {
      if (!res) return;
      if (res.next) { self.nextSlot = res.next; self.showNext(); }
      var keys = Object.keys(self.days).sort();
      if (!keys.length && res.next) { self.jumpTo(res.next, true); return; }
      self.renderCal();
      if (!keys.length) { self.setState('idle'); if (!res.next && self.cfg.onEmpty) self.cfg.onEmpty(); self.q.nextBox.hidden = true; }
      else { self.setState('idle'); }
    });
  };

  Scheduler.prototype.refresh = function () {
    var keepDate = this.date; this.days = {}; this.loaded = {}; this.nextSlot = null; this.q.nextBox.hidden = true;
    this.ctx = this.ctxKey();
    this.slot = null; if (this.cfg.onPick) this.cfg.onPick(null);
    var self = this; this.setState('loading');
    this.fetchRange(this.month, true).then(function (res) {
      if (!res) return;
      if (res.next) { self.nextSlot = res.next; self.showNext(); }
      self.renderCal();
      if (keepDate && self.days[keepDate]) { self.pickDate(keepDate, true); } else { self.date = null; self.q.dayTitle.textContent = 'Elige un día'; self.setState('idle'); if (!Object.keys(self.days).length && res.next) self.jumpTo(res.next, true); else if (!Object.keys(self.days).length && !res.next && self.cfg.onEmpty) self.cfg.onEmpty(); }
    });
  };

  Scheduler.prototype.fetchRange = function (mo, withNext) {
    var self = this, key = mo.y + '-' + mo.m, ctx = this.ctx;
    if (this.loaded[key] && !withNext) return Promise.resolve({});
    var first = ymd(mo.y, mo.m, 1), last = ymd(mo.y, mo.m, daysIn(mo.y, mo.m));
    var u = this.cfg.urls.slots + '?event=' + encodeURIComponent(this.cfg.event) + '&duration=' + this.cfg.getDuration() + '&from=' + first + '&to=' + last + '&tz=' + encodeURIComponent(this.tz);
    var h = this.cfg.getHost(); if (h) u += '&host=' + h;
    if (this.cfg.exclude) u += '&exclude=' + this.cfg.exclude;
    if (withNext) u += '&next=1';
    var id = ++this.reqId;
    return request(u).then(function (r) {
      if (ctx !== self.ctx || (id !== self.reqId && !withNext)) return null;
      if (!r.data.ok) { self.showError(r.data.error || 'No pudimos cargar los horarios.'); return null; }
      self.loaded[key] = true;
      var d = r.data.days || {}; Object.keys(d).forEach(function (k) {
        self.days[k] = d[k].map(function (s) { return { s: s.s, t: s.t, n: s.n, mins: (+s.t.slice(0, 2)) * 60 + (+s.t.slice(3, 5)), id: s.s }; });
      });
      if (r.data.csrf && self.cfg.onCsrf) self.cfg.onCsrf(r.data.csrf);
      return { next: r.data.next || null, count: r.data.count };
    });
  };

  Scheduler.prototype.showError = function (msg) { this.q.errText.textContent = msg; this.setState('error'); };

  Scheduler.prototype.setState = function (name) {
    $$('[data-state]', this.root).forEach(function (n) { n.hidden = n.getAttribute('data-state') !== name; });
    var show = name === 'dial' || name === 'list';
    this.q.dialView.hidden = !(show && this.view === 'dial');
    this.q.listView.hidden = !(show && this.view === 'list');
    this.state = name;
  };

  Scheduler.prototype.showNext = function () {
    var n = this.nextSlot; if (!n) return;
    this.q.nextWhen.textContent = dayLabel(n.date) + ' · ' + fmtTime(n.t, this.is24);
    this.q.nextBox.hidden = false;
  };

  Scheduler.prototype.jumpTo = function (n, silent) {
    var self = this, p = parseYmd(n.date);
    this.month = { y: p.y, m: p.m };
    this.fetchRange(this.month).then(function () {
      self.renderCal(); self.pickDate(n.date, silent);
      if (!silent) { var s = self.byId(n.s); if (s) { self.pickSlot(s, false); } }
    });
  };

  Scheduler.prototype.shiftMonth = function (d) {
    var self = this, m = this.month.m + d, y = this.month.y;
    if (m < 1) { m = 12; y--; } if (m > 12) { m = 1; y++; }
    this.month = { y: y, m: m };
    this.q.calDays.setAttribute('aria-busy', 'true');
    this.fetchRange(this.month).then(function () { self.q.calDays.removeAttribute('aria-busy'); self.renderCal(); });
    this.renderCal();
  };

  Scheduler.prototype.renderCal = function () {
    var mo = this.month, q = this.q, self = this;
    q.calTitle.textContent = MONTHS[mo.m - 1].charAt(0).toUpperCase() + MONTHS[mo.m - 1].slice(1) + ' ' + mo.y;
    var today = todayIn(this.tz), maxDay = this.cfg.maxAdvance ? addDays(today, this.cfg.maxAdvance) : null;
    var tp = parseYmd(today);
    q.prev.disabled = (mo.y < tp.y) || (mo.y === tp.y && mo.m <= tp.m);
    q.next.disabled = !!maxDay && ymd(mo.y, mo.m, 1) > maxDay;
    var lead = (dow(mo.y, mo.m, 1) + 6) % 7, total = daysIn(mo.y, mo.m), frag = doc.createDocumentFragment(), i;
    for (i = 0; i < lead; i++) frag.appendChild(el('span', 'cal-pad'));
    var focus = this.focusDate && this.focusDate.slice(0, 7) === mo.y + '-' + pad(mo.m) ? this.focusDate : null;
    if (!focus) { for (i = 1; i <= total && !focus; i++) { var dd = ymd(mo.y, mo.m, i); if (this.days[dd]) focus = dd; } }
    if (!focus) focus = this.date && this.date.slice(0, 7) === mo.y + '-' + pad(mo.m) ? this.date : ymd(mo.y, mo.m, 1);
    for (i = 1; i <= total; i++) {
      var ds = ymd(mo.y, mo.m, i), list = this.days[ds], past = ds < today;
      var b = el('button', 'cal-day' + (list ? ' has' : '') + (ds === today ? ' is-today' : '') + (ds === this.date ? ' is-sel' : ''));
      b.type = 'button'; b.setAttribute('data-date', ds);
      b.appendChild(el('span', 'cal-num mono', String(i)));
      if (list) b.appendChild(el('span', 'cal-dot', ''));
      b.setAttribute('aria-label', dayLabel(ds) + (past ? ', fecha pasada' : list ? ', ' + list.length + (list.length === 1 ? ' horario disponible' : ' horarios disponibles') : ', sin horarios'));
      if (!list) { b.disabled = true; }
      b.tabIndex = ds === focus && list ? 0 : -1;
      if (ds === this.date) b.setAttribute('aria-pressed', 'true');
      frag.appendChild(b);
    }
    // si ningún día habilitado recibe el foco, el primero habilitado lo recibe
    q.calDays.textContent = ''; q.calDays.appendChild(frag);
    if (!$('.cal-day[tabindex="0"]', q.calDays)) { var f = $('.cal-day:not(:disabled)', q.calDays); if (f) f.tabIndex = 0; }
    this.focusDate = focus;
  };

  Scheduler.prototype.onCalKey = function (e) {
    var cur = e.target.closest ? e.target.closest('.cal-day') : null; if (!cur) return;
    var enabled = $$('.cal-day:not(:disabled)', this.q.calDays), i = enabled.indexOf(cur), n = -1;
    if (e.key === 'ArrowRight') n = i + 1; else if (e.key === 'ArrowLeft') n = i - 1;
    else if (e.key === 'ArrowDown') n = i + 7; else if (e.key === 'ArrowUp') n = i - 7;
    else if (e.key === 'Home') n = 0; else if (e.key === 'End') n = enabled.length - 1;
    else if (e.key === 'PageDown') { e.preventDefault(); this.shiftMonth(1); return; } else if (e.key === 'PageUp') { e.preventDefault(); if (!this.q.prev.disabled) this.shiftMonth(-1); return; }
    else return;
    e.preventDefault();
    // ±7 se interpreta por fecha real, no por posición entre habilitados
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      var want = addDays(cur.getAttribute('data-date'), e.key === 'ArrowDown' ? 7 : -7), t = $('.cal-day[data-date="' + want + '"]', this.q.calDays);
      if (t && !t.disabled) { this.moveFocus(t); return; }
      var after = enabled.filter(function (b) { return e.key === 'ArrowDown' ? b.getAttribute('data-date') > want : b.getAttribute('data-date') < want; });
      t = e.key === 'ArrowDown' ? after[0] : after[after.length - 1];
      if (t) this.moveFocus(t);
      return;
    }
    if (n >= 0 && n < enabled.length) this.moveFocus(enabled[n]);
  };
  Scheduler.prototype.moveFocus = function (b) {
    $$('.cal-day', this.q.calDays).forEach(function (x) { x.tabIndex = -1; });
    b.tabIndex = 0; b.focus(); this.focusDate = b.getAttribute('data-date');
  };

  Scheduler.prototype.pickDate = function (date, silent) {
    this.date = date; this.focusDate = date;
    var self = this;
    this.slot = null; if (this.cfg.onPick && !silent) this.cfg.onPick(null);
    $$('.cal-day', this.q.calDays).forEach(function (b) { var on = b.getAttribute('data-date') === date; b.classList.toggle('is-sel', on); if (on) b.setAttribute('aria-pressed', 'true'); else b.removeAttribute('aria-pressed'); b.tabIndex = on ? 0 : -1; });
    this.q.dayTitle.textContent = dayLabel(date, true);
    var list = this.days[date] || [];
    if (!list.length) { this.q.noneText.textContent = 'No hay horarios para este día. Prueba con otro día resaltado.'; this.setState('none'); this.speak('Sin horarios para ' + dayLabel(date)); return; }
    this.renderTimes(list);
    this.setState(this.view);
    this.speak(dayLabel(date) + ': ' + list.length + (list.length === 1 ? ' horario disponible' : ' horarios disponibles'));
  };

  Scheduler.prototype.renderTimes = function (list) {
    var self = this, is24 = this.is24;
    this.dial.is24 = is24;
    this.dial.setSlots(list.map(function (s) { return { id: s.s, mins: s.mins, t: s.t, seats: s.n }; }));
    this.syncAmPm();
    // Lista accesible
    var box = this.q.listView; box.textContent = '';
    var grp = box;
    box.setAttribute('role', 'radiogroup'); box.setAttribute('aria-label', 'Horarios disponibles');
    var groups = [['Mañana', 0, 720], ['Tarde', 720, 1080], ['Noche', 1080, 1440]];
    groups.forEach(function (g) {
      var items = list.filter(function (s) { return s.mins >= g[1] && s.mins < g[2]; });
      if (!items.length) return;
      var wrap = el('div', 'slot-group'); wrap.appendChild(el('p', 'slot-group-title eyebrow', g[0]));
      var grid = el('div', 'slot-grid');
      items.forEach(function (s) {
        var b = el('button', 'slot-btn mono'); b.type = 'button'; b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', 'false'); b.setAttribute('data-id', s.s);
        b.setAttribute('aria-label', fmtTime(s.t, is24) + ', disponible' + (s.n != null ? ', quedan ' + s.n + (s.n === 1 ? ' lugar' : ' lugares') : ''));
        b.textContent = fmtTime(s.t, is24);
        if (s.n != null) { b.appendChild(el('small', 'slot-seats', s.n + (s.n === 1 ? ' lugar' : ' lugares'))); }
        b.addEventListener('click', function () { self.pickSlot(s, false); });
        grid.appendChild(b);
      });
      wrap.appendChild(grid); box.appendChild(wrap);
    });
    var first = $('.slot-btn', box); if (first) first.tabIndex = 0; $$('.slot-btn', box).forEach(function (b, i) { if (i) b.tabIndex = -1; });
  };

  Scheduler.prototype.syncAmPm = function () {
    var c = this.dial.counts(), half = this.dial.half;
    $$('[data-ampm]', this.root).forEach(function (b) {
      var k = b.getAttribute('data-ampm'), n = c[k];
      b.setAttribute('aria-pressed', k === half ? 'true' : 'false');
      b.setAttribute('aria-disabled', n === 0 ? 'true' : 'false');
      b.classList.toggle('is-empty', n === 0);
      b.title = n === 0 ? 'Sin horarios en esta parte del día' : n + (n === 1 ? ' horario' : ' horarios');
    });
  };

  Scheduler.prototype.pickSlot = function (s, fromDial) {
    if (!s) return;
    this.slot = { s: s.s, t: s.t, n: s.n, date: this.date };
    var list = this.q.listView;
    $$('.slot-btn', list).forEach(function (b) {
      var on = b.getAttribute('data-id') === s.s; b.setAttribute('aria-checked', on ? 'true' : 'false'); b.classList.toggle('is-sel', on); b.tabIndex = on ? 0 : -1;
    });
    if (!$('.slot-btn[tabindex="0"]', list)) { var f = $('.slot-btn', list); if (f) f.tabIndex = 0; }
    if (!fromDial) this.dial.select(s.s);
    this.syncAmPm();
    this.speak('Elegiste ' + dayLabel(this.date) + ' a las ' + fmtTime(s.t, this.is24));
    if (this.cfg.onPick) this.cfg.onPick(this.slot);
  };

  Scheduler.prototype.speak = function (t) { this.q.live.textContent = ''; var l = this.q.live; global.setTimeout(function () { l.textContent = t; }, 40); };

  Scheduler.prototype.setView = function (v, silent) {
    this.view = v === 'list' ? 'list' : 'dial';
    store('set', 'ap_view', this.view);
    $$('[data-view]', this.root).forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-view') === v ? 'true' : 'false'); });
    if (this.state === 'dial' || this.state === 'list') this.setState(this.view);
  };

  Scheduler.prototype.setFormat = function (is24) {
    this.is24 = is24;
    store('set', 'ap_fmt', is24 ? '24' : '12');
    $$('[data-fmt]', this.root).forEach(function (b) { b.setAttribute('aria-pressed', (b.getAttribute('data-fmt') === '24') === is24 ? 'true' : 'false'); });
    this.dial.setFormat(is24);
    if (this.date && this.days[this.date]) { var sel = this.slot && this.slot.s; this.renderTimes(this.days[this.date]); if (sel) { this.dial.select(sel); $$('.slot-btn', this.q.listView).forEach(function (b) { var on = b.getAttribute('data-id') === sel; b.setAttribute('aria-checked', on ? 'true' : 'false'); b.classList.toggle('is-sel', on); }); } }
    if (this.nextSlot) this.showNext();
    if (this.cfg.onFormat) this.cfg.onFormat(is24);
  };

  Scheduler.prototype.setTz = function (tz) {
    this.tz = tz; this.focusDate = null; this.date = null;
    var p = parseYmd(todayIn(tz)); this.month = { y: p.y, m: p.m };
    this.q.dayTitle.textContent = 'Elige un día';
    if (this.cfg.onTz) this.cfg.onTz(tz);
    this.refresh();
  };

  // ---------------------------------------------------------------- Página de reserva
  function initBooking(root, boot) {
    var ev = boot.event, urls = boot.urls;
    var st = {
      step: 1, duration: ev.duration, host: boot.host || 0, slot: null, tz: '', is24: boot.time_format === '24',
      csrf: boot.csrf, quote: boot.quotes[String(ev.duration)] || null, coupon: '', couponOk: false, seats: 1, seatsMax: 1,
      submitting: false, tracked: {}, visit: ''
    };
    try { st.visit = global.sessionStorage.getItem('ap_visit') || ''; } catch (e) { st.visit = ''; }
    if (!/^[a-f0-9]{16}$/.test(st.visit)) { st.visit = hex16(); try { global.sessionStorage.setItem('ap_visit', st.visit); } catch (e) { /* memoria */ } }
    var f12 = store('get', 'ap_fmt'); if (f12 === '24' || f12 === '12') st.is24 = f12 === '24';
    st.tz = guessTz() || boot.business_tz;

    var stage = $('[data-stage]', root), panels = {}, prog = $('[data-progress]', root), live = $('[data-step-live]', root);
    [1, 2, 3, 4].forEach(function (n) { panels[n] = $('[data-step="' + n + '"]', stage); });
    var form = $('[data-form]', root);
    var titles = { 1: 'Paso 1 de 4: servicio', 2: 'Paso 2 de 4: fecha y hora', 3: 'Paso 3 de 4: tus datos', 4: 'Reserva confirmada' };

    // -------- embudo y envío de altura
    function track(step) {
      if (st.tracked[step]) return; st.tracked[step] = true;
      var body = { step: step, event: ev.slug, host: st.host || 0, visit_id: st.visit, referrer_host: refHost() };
      Object.keys(boot.utm || {}).forEach(function (k) { body[k] = boot.utm[k]; });
      request(urls.track, { method: 'POST', json: body, csrf: st.csrf.book });
    }
    function refHost() { try { var h = doc.referrer ? new URL(doc.referrer).hostname : ''; return h && h !== global.location.hostname ? h : ''; } catch (e) { return ''; } }
    function postHeight() {
      if (!boot.embed || global.parent === global) return;
      var h = Math.ceil(doc.documentElement.getBoundingClientRect().height);
      global.parent.postMessage({ type: 'agenda-premium:height', height: h, slug: ev.slug }, '*');
    }
    if (boot.embed && global.ResizeObserver) { var raf = 0; new ResizeObserver(function () { global.cancelAnimationFrame(raf); raf = global.requestAnimationFrame(postHeight); }).observe(doc.body); }
    track('view');

    // -------- panel lateral (móvil)
    var tg = $('[data-side-toggle]', root), more = $('[data-side-more]', root);
    if (tg && more) {
      var mq = global.matchMedia('(min-width: 62rem)');
      var toggleMore = function (open) { more.classList.toggle('is-open', open); tg.setAttribute('aria-expanded', open ? 'true' : 'false'); };
      tg.addEventListener('click', function () { toggleMore(!more.classList.contains('is-open')); });
      var sync = function () { if (mq.matches) { toggleMore(true); } };
      if (mq.addEventListener) mq.addEventListener('change', sync); sync();
    }

    // -------- navegación entre pasos con cortina
    function setProgress(n) {
      prog.setAttribute('data-step', String(n));
      $$('li', prog).forEach(function (li) {
        var k = +li.getAttribute('data-prog');
        li.classList.toggle('is-done', k < n); li.classList.toggle('is-current', k === n);
        if (k === n) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
      });
    }
    function go(n, opts) {
      opts = opts || {};
      var from = panels[st.step], to = panels[n];
      if (!to || n === st.step && !opts.force) return;
      var back = n < st.step;
      st.step = n; setProgress(n);
      var finish = function () {
        from.hidden = true; from.classList.remove('is-out');
        to.hidden = false;
        if (!reduced() && !opts.instant) { to.classList.add(back ? 'is-in-back' : 'is-in'); stage.classList.add('is-sweeping'); global.setTimeout(function () { to.classList.remove('is-in', 'is-in-back'); stage.classList.remove('is-sweeping'); postHeight(); }, 620); }
        var h = $('h2', to); if (h && !opts.silent) { try { h.focus({ preventScroll: true }); } catch (e) { h.focus(); } }
        live.textContent = titles[n];
        if (!opts.silent) { var top = root.getBoundingClientRect().top; if (top < 0 || boot.embed) { try { root.scrollIntoView({ block: 'start', behavior: reduced() ? 'auto' : 'smooth' }); } catch (e) { root.scrollIntoView(); } } }
        if (n === 2) { ensureScheduler(); }
        postHeight();
      };
      if (from && from !== to && !reduced() && !opts.instant) { from.classList.add('is-out'); global.setTimeout(finish, 220); } else { finish(); }
      if (!opts.nopush && n > 1 && n < 4) { try { global.history.pushState({ bk: n }, '', '#paso-' + n); } catch (e) { /* nada */ } }
    }
    global.addEventListener('popstate', function (e) {
      if (st.step === 4) return;
      var n = e.state && e.state.bk ? e.state.bk : 1;
      if (n >= st.step) n = Math.max(1, st.step - 1);
      go(n, { nopush: true });
    });
    $$('[data-next-step]', root).forEach(function (b) { b.addEventListener('click', function () { if (b.disabled) return; if (st.step === 1) go(2); else if (st.step === 2 && st.slot) { prepareStep3(); go(3); } }); });
    $$('[data-prev-step]', root).forEach(function (b) { b.addEventListener('click', function () { go(Math.max(1, st.step - 1)); }); });
    var chg = $('[data-change-slot]', root); if (chg) chg.addEventListener('click', function () { go(2); });

    // -------- Paso 1
    function durLabel(m) { if (m < 60) return m + ' min'; var h = Math.floor(m / 60), r = m % 60; return h + ' h' + (r ? ' ' + r + ' min' : ''); }
    function setQuoteFacts() {
      var q = st.quote, p = $('[data-fact-price]', root);
      if (p) p.textContent = q ? (q.price > 0 ? q.price_text : 'Sin costo') : p.textContent;
      var d = $('[data-fact-duration]', root); if (d) d.textContent = durLabel(st.duration);
    }
    $$('input[name="duration"]', root).forEach(function (r) { r.addEventListener('change', function () {
      st.duration = +r.value; st.quote = boot.quotes[String(st.duration)] || st.quote; setQuoteFacts();
      if (sched) { sched.refresh(); }
    }); });
    $$('input[name="host"]', root).forEach(function (r) { r.addEventListener('change', function () { st.host = +r.value; if (sched) sched.refresh(); }); });
    setQuoteFacts();

    // -------- Paso 2
    var sched = null, nextBtn2 = $('[data-step="2"] [data-next-step]', root);
    function ensureScheduler() {
      if (sched) return;
      sched = new Scheduler($('[data-scheduler]', root), {
        event: ev.slug, urls: urls, tz: st.tz, is24: st.is24, maxAdvance: ev.max_advance_days,
        getDuration: function () { return st.duration; }, getHost: function () { return st.host; },
        onPick: function (slot) {
          st.slot = slot; nextBtn2.disabled = !slot;
          if (slot) { track('slot'); }
          postHeight();
        },
        onFormat: function (is24) { st.is24 = is24; if (st.slot) fillChosen(); },
        onTz: function (tz) { st.tz = tz; },
        onCsrf: function (c) { st.csrf = c; },
        onEmpty: function () { openWaitlist(true); },
        onWaitlist: function () { openWaitlist(false); }
      });
      sched.start();
    }
    var wl = $('[data-waitlist]', root);
    function openWaitlist(auto) {
      if (!wl) return; wl.hidden = false; postHeight();
      if (!auto) { var i = $('input[name="name"]', wl); if (i) { i.focus(); wl.scrollIntoView && wl.scrollIntoView({ block: 'center', behavior: reduced() ? 'auto' : 'smooth' }); } }
    }
    var wlForm = $('[data-waitlist-form]', root);
    if (wlForm) {
      $('[data-close-waitlist]', root).addEventListener('click', function () { wl.hidden = true; postHeight(); });
      wlForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var err = $('[data-waitlist-error]', wl), ok = $('[data-waitlist-ok]', wl), fd = new FormData(wlForm);
        err.hidden = true;
        var body = { event: ev.slug, duration: st.duration, host: st.host || 0, tz: st.tz, name: fd.get('name') || '', email: fd.get('email') || '', phone: fd.get('phone') || '', want_date: fd.get('want_date') || '', consent: fd.get('consent') ? 1 : 0, company_site: fd.get('company_site') || '' };
        var btn = $('button[type="submit"]', wlForm); btn.classList.add('is-loading'); btn.disabled = true;
        request(urls.waitlist, { method: 'POST', json: body, csrf: st.csrf.book }).then(function (r) {
          btn.classList.remove('is-loading'); btn.disabled = false;
          if (r.data.ok) { wlForm.hidden = true; ok.textContent = r.data.message; ok.hidden = false; ok.focus && ok.setAttribute('tabindex', '-1'); ok.focus(); }
          else { var m = r.data.error || 'No pudimos registrar tu solicitud.'; if (r.data.errors) m = Object.keys(r.data.errors).map(function (k) { return r.data.errors[k]; }).join(' '); err.textContent = m; err.hidden = false; }
        });
      });
    }

    // -------- Paso 3
    function chosenText() { var s = st.slot; return dayLabel(s.date, true) + ' · ' + fmtTime(s.t, st.is24); }
    function fillChosen() {
      $('[data-chosen-when]', root).textContent = chosenText();
      $('[data-chosen-meta]', root).textContent = durLabel(st.duration) + ' · ' + st.tz.replace(/_/g, ' ');
      var ser = $('[data-series]', root);
      if (ser && ev.series_sessions > 1) {
        ser.textContent = '';
        for (var i = 0; i < ev.series_sessions; i++) { var d = addDays(st.slot.date, i * ev.series_interval_days); ser.appendChild(el('li', '', dayLabel(d, true) + ' · ' + fmtTime(st.slot.t, st.is24))); }
      }
    }
    function prepareStep3() {
      fillChosen();
      if (ev.is_group) {
        var left = st.slot.n; st.seatsMax = Math.max(1, Math.min(left == null ? 10 : left, 10));
        if (st.seats > st.seatsMax) st.seats = st.seatsMax;
        var inp = $('#f-seats', root); if (inp) { inp.value = st.seats; inp.max = st.seatsMax; }
        var hint = $('[data-seats-hint]', root); if (hint) hint.textContent = left == null ? '' : (left === 1 ? 'Queda 1 lugar en este horario.' : 'Quedan ' + left + ' lugares en este horario.');
      }
      renderPrice();
    }
    // precio
    function renderPrice() {
      var box = $('[data-price]', root), q = st.quote; if (!box) return;
      if (!q || (q.total <= 0 && q.discount <= 0 && q.price <= 0)) { box.hidden = true; return; }
      box.hidden = false;
      $('[data-p-price]', box).textContent = q.price_text;
      var dr = $('[data-p-disc-row]', box); dr.hidden = !(q.discount > 0); $('[data-p-disc]', box).textContent = '−' + q.discount_text;
      $('[data-p-total]', box).textContent = q.total_text;
      var dp = $('[data-p-dep-row]', box); dp.hidden = !(q.deposit_due > 0 && q.deposit_due < q.total + 0.001); $('[data-p-dep]', box).textContent = q.deposit_text;
    }
    function refreshQuote(showMsg) {
      var msg = $('[data-coupon-msg]', root), code = (($('#f-coupon', root) || {}).value || '').trim();
      return request(urls.coupon, { method: 'POST', json: { event: ev.slug, duration: st.duration, seats: st.seats, coupon: code }, csrf: st.csrf.book }).then(function (r) {
        if (r.data.ok) { st.quote = r.data.quote; st.coupon = code; st.couponOk = !!code; renderPrice(); if (msg && showMsg) { msg.textContent = code ? 'Cupón aplicado. ¡Se aplicó tu descuento!' : ''; msg.className = 'hint text-ok'; } }
        else if (msg && showMsg) { msg.textContent = r.data.error || 'Ese cupón no es válido.'; msg.className = 'hint text-err'; st.couponOk = false; }
      });
    }
    var capply = $('[data-coupon-apply]', root);
    if (capply) capply.addEventListener('click', function () {
      var c = ($('#f-coupon', root).value || '').trim(); var msg = $('[data-coupon-msg]', root);
      if (!c) { msg.textContent = 'Escribe tu código de cupón.'; msg.className = 'hint text-err'; return; }
      capply.classList.add('is-loading'); refreshQuote(true).then(function () { capply.classList.remove('is-loading'); });
    });
    // lugares
    var sdec = $('[data-seats-dec]', root), sinc = $('[data-seats-inc]', root);
    function setSeats(n) { st.seats = Math.max(1, Math.min(st.seatsMax, n)); $('#f-seats', root).value = st.seats; refreshQuote(false); }
    if (sdec) sdec.addEventListener('click', function () { setSeats(st.seats - 1); });
    if (sinc) sinc.addEventListener('click', function () { setSeats(st.seats + 1); });
    // invitados
    var gbox = $('[data-guests]', root);
    if (gbox) {
      var glist = $('[data-guest-list]', gbox), gmax = +gbox.getAttribute('data-max'), gadd = $('[data-add-guest]', gbox);
      gadd.addEventListener('click', function () {
        var n = $$('.guest-row', glist).length; if (n >= gmax) return;
        var row = el('div', 'guest-row form-grid'), i = n + 1;
        var a = el('div', 'field'), l1 = el('label', '', 'Nombre del invitado ' + i), i1 = el('input', 'input'); i1.type = 'text'; i1.maxLength = 160; i1.id = 'g-n-' + i; l1.htmlFor = i1.id; i1.setAttribute('data-g', 'name'); a.appendChild(l1); a.appendChild(i1);
        var b = el('div', 'field'), l2 = el('label', '', 'Correo del invitado ' + i + ' (opcional)'), i2 = el('input', 'input'); i2.type = 'email'; i2.maxLength = 190; i2.id = 'g-e-' + i; l2.htmlFor = i2.id; i2.setAttribute('data-g', 'email'); b.appendChild(l2); b.appendChild(i2);
        var rm = el('button', 'btn btn-ghost btn-sm', 'Quitar'); rm.type = 'button'; rm.addEventListener('click', function () { row.remove(); gadd.hidden = false; gadd.focus(); postHeight(); });
        row.appendChild(a); row.appendChild(b); row.appendChild(rm); glist.appendChild(row); i1.focus();
        if ($$('.guest-row', glist).length >= gmax) gadd.hidden = true; postHeight();
      });
    }
    // lógica condicional
    function fieldValue(wrap) {
      var type = wrap.getAttribute('data-type'), name = 'cf-' + wrap.getAttribute('data-cf');
      if (type === 'radio') { var r = $('input:checked', wrap); return r ? r.value : ''; }
      if (type === 'checkbox' || type === 'consent') {
        var boxes = $$('input[type="checkbox"]', wrap);
        if (boxes.length === 1 && !wrap.querySelector('fieldset')) return boxes[0].checked ? 'Sí' : '';
        return boxes.filter(function (b) { return b.checked; }).map(function (b) { return b.value; });
      }
      var inp = $('[name="' + name + '"]', wrap); return inp ? inp.value : '';
    }
    function applyConditions() {
      var wraps = $$('[data-cf]', form);
      for (var pass = 0; pass < 3; pass++) {
        wraps.forEach(function (w) {
          var cf = w.getAttribute('data-cond-field'); if (!cf) return;
          var ctl = wraps.filter(function (x) { return x.getAttribute('data-name') === cf; })[0], show = true;
          if (ctl) {
            show = !ctl.hidden; if (show) {
              var v = fieldValue(ctl), cv = w.getAttribute('data-cond-value');
              show = Array.isArray(v) ? v.indexOf(cv) >= 0 : (v === cv || (v === 'Sí' && /^(1|si|sí|true)$/i.test(cv)));
            }
          }
          w.hidden = !show;
          $$('input,select,textarea', w).forEach(function (i) { i.disabled = !show; });
        });
      }
      postHeight();
    }
    if (form) { form.addEventListener('input', function (e) { clearErr(e.target); applyConditions(); }); form.addEventListener('change', function (e) { clearErr(e.target); applyConditions(); }); applyConditions(); }
    // archivos
    $$('[data-upload]', root).forEach(function (inp) {
      inp.addEventListener('change', function () {
        var wrap = inp.closest('[data-cf]'), tok = $('[data-file-token]', wrap), msg = $('[data-upload-status]', wrap), f = inp.files && inp.files[0];
        tok.value = ''; if (!f) return;
        if (f.size > 5 * 1024 * 1024) { msg.textContent = 'El archivo supera los 5 MB. Elige uno más liviano.'; msg.classList.add('text-err'); inp.value = ''; return; }
        msg.classList.remove('text-err'); msg.textContent = 'Subiendo «' + f.name + '»…';
        var fd = new FormData(); fd.append('file', f);
        request(urls.upload, { method: 'POST', form: fd, csrf: st.csrf.book }).then(function (r) {
          if (r.data.ok) { tok.value = r.data.file_token; msg.textContent = 'Adjuntado: ' + r.data.name; }
          else { msg.textContent = r.data.error || 'No pudimos subir el archivo.'; msg.classList.add('text-err'); inp.value = ''; }
        });
      });
    });

    // -------- errores de formulario
    function fieldWrap(key) { return $('[data-f="' + key + '"]', root); }
    function showErr(key, msg) {
      var w = fieldWrap(key); if (!w) return null;
      w.classList.add('has-error'); var e = $('.error', w); if (e) { e.textContent = msg; e.hidden = false; if (!e.id) e.id = 'err-' + key; }
      var i = $('input:not([type=hidden]),select,textarea', w); if (i) { i.setAttribute('aria-invalid', 'true'); if (e) i.setAttribute('aria-describedby', e.id); }
      return i || w;
    }
    function clearErr(t) {
      var w = t && t.closest ? t.closest('[data-f]') : null; if (!w || !w.classList.contains('has-error')) return;
      w.classList.remove('has-error'); var e = $('.error', w); if (e) e.hidden = true; $$('[aria-invalid]', w).forEach(function (i) { i.removeAttribute('aria-invalid'); });
    }
    function clearAllErr() { $$('.has-error', root).forEach(function (w) { w.classList.remove('has-error'); var e = $('.error', w); if (e) e.hidden = true; }); var fe = $('[data-form-error]', root); fe.hidden = true; }

    function collect() {
      var fd = new FormData(form), answers = {};
      $$('[data-cf]', form).forEach(function (w) {
        if (w.hidden) return;
        var v, type = w.getAttribute('data-type');
        if (type === 'file') { v = ($('[data-file-token]', w) || {}).value || ''; }
        else if (type === 'consent' || (type === 'checkbox' && $$('input[type="checkbox"]', w).length === 1 && !w.querySelector('fieldset'))) { v = $('input', w).checked ? true : ''; }
        else v = fieldValue(w);
        answers[w.getAttribute('data-cf')] = v;
      });
      var guests = $$('.guest-row', form).map(function (r) { return { name: $('[data-g="name"]', r).value.trim(), email: $('[data-g="email"]', r).value.trim() }; });
      var phone = String(fd.get('phone') || '').trim();
      var body = {
        event: ev.slug, duration: st.duration, start: st.slot.s, host: st.host || 0, tz: st.tz,
        name: String(fd.get('name') || '').trim(), email: String(fd.get('email') || '').trim(), phone: phone,
        notes: String(fd.get('notes') || '').trim(), answers: answers, guests: guests, seats: st.seats,
        coupon: st.couponOk ? st.coupon : '', consent: fd.get('consent') ? 1 : 0, location: String(fd.get('location') || '').trim(),
        company_site: String(fd.get('company_site') || ''), form_ts: boot.form_ts, visit_id: st.visit, referrer_host: refHost()
      };
      Object.keys(boot.utm || {}).forEach(function (k) { body[k] = boot.utm[k]; });
      if (boot.captcha) body[boot.captcha.field] = String(fd.get(boot.captcha.field) || '');
      return body;
    }

    function clientValidate(b) {
      var errs = {}, first = null;
      function bad(k, m) { errs[k] = m; }
      if (b.name.length < 2) bad('name', 'Escribe tu nombre completo.');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(b.email)) bad('email', 'Revisa tu correo: parece que le falta algo (por ejemplo, nombre@correo.com).');
      var digits = b.phone.replace(/\D/g, '');
      if (!b.phone) { if (ev.require_phone) bad('phone', 'Déjanos un teléfono para poder contactarte si hay algún cambio.'); }
      else if (!(b.phone.charAt(0) === '+' ? digits.length >= 9 && digits.length <= 15 : digits.length === 8 || (digits.length > 8 && digits.length <= 15))) bad('phone', 'Revisa tu teléfono: en Guatemala son 8 dígitos (por ejemplo, 5555 1234) o escribe tu código de país con +.');
      if (ev.mode === 'home' && (b.location || '').length < 5) bad('location', 'Escribe la dirección donde te atenderemos (zona, calle y referencias).');
      boot.fields.forEach(function (f) {
        var w = $('[data-cf="' + f.id + '"]', form); if (!w || w.hidden) return;
        var v = b.answers[f.id], empty = v === '' || v === undefined || v === false || (Array.isArray(v) && !v.length);
        if (f.required && empty) bad('f_' + f.id, f.type === 'consent' ? 'Para continuar necesitamos que aceptes: ' + f.label : f.type === 'file' ? 'Adjunta el archivo solicitado.' : 'Esta respuesta es obligatoria.');
        else if (!empty && f.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) bad('f_' + f.id, 'Revisa el correo electrónico.');
        else if (!empty && f.type === 'number' && isNaN(Number(String(v).replace(',', '.')))) bad('f_' + f.id, 'Escribe solo números.');
      });
      if (!b.consent) bad('consent', 'Necesitamos tu autorización para guardar tus datos y confirmar la cita.');
      return errs;
    }

    function setLoading(on) {
      st.submitting = on; var b = $('[data-submit]', root); b.disabled = on; b.classList.toggle('is-loading', on); b.setAttribute('aria-busy', on ? 'true' : 'false');
    }
    function formError(msg) { var fe = $('[data-form-error]', root); fe.textContent = msg; fe.hidden = false; try { fe.focus({ preventScroll: false }); } catch (e) { fe.focus(); } }

    function submit(retry) {
      var body = collect();
      request(urls.book, { method: 'POST', json: body, csrf: st.csrf.book }).then(function (r) {
        var d = r.data;
        if (r.status === 419 && !retry) { // token caducado: se renueva con una consulta de horarios y se reintenta una vez
          request(urls.slots + '?event=' + encodeURIComponent(ev.slug) + '&duration=' + st.duration).then(function (x) { if (x.data.csrf) st.csrf = x.data.csrf; submit(true); });
          return;
        }
        if (d.ok) { setLoading(false); showDone(d); return; }
        setLoading(false);
        if (d.reload) { formError(d.error); return; }
        if (d.code === 'slot_unavailable' || r.status === 409) {
          st.slot = null; nextBtn2.disabled = true; go(2); if (sched) sched.refresh();
          live.textContent = 'Ese horario acaba de ocuparse. Elige otro, por favor.';
          var note = $('[data-step="2"] [data-live]', root); if (note) note.textContent = 'Ese horario acaba de ocuparse. Elige otro, por favor.';
          return;
        }
        if (d.errors) {
          var firstEl = null, msgs = [];
          Object.keys(d.errors).forEach(function (k) { var n = showErr(k, d.errors[k]); if (!firstEl && n) firstEl = n; msgs.push(d.errors[k]); });
          formError(d.error || 'Revisa los campos marcados.'); if (firstEl) firstEl.focus();
          return;
        }
        formError(d.error || 'No pudimos completar tu reserva. Inténtalo de nuevo.');
      });
    }

    if (form) form.addEventListener('submit', function (e) {
      e.preventDefault(); if (st.submitting) return;
      clearAllErr();
      if (!st.slot) { go(2); return; }
      var body = collect(), errs = clientValidate(body), keys = Object.keys(errs);
      if (keys.length) {
        var firstEl = null; keys.forEach(function (k) { var n = showErr(k, errs[k]); if (!firstEl && n) firstEl = n; });
        formError('Revisa ' + (keys.length === 1 ? 'el campo marcado' : 'los campos marcados') + ' para continuar.'); if (firstEl) firstEl.focus(); return;
      }
      setLoading(true); submit(false);
    });

    // -------- Paso 4
    function link(name, href) { var a = $('[data-o-link="' + name + '"]', root); if (!a) return; if (href) { a.href = href; a.hidden = false; } else { a.hidden = true; } }
    function showDone(d) {
      var s = d.summary, pending = d.status === 'pending';
      $$('[data-o]', root).forEach(function (n) { var k = n.getAttribute('data-o'); n.textContent = s[k] || ''; });
      ['host', 'location'].forEach(function (k) { var r = $('[data-o-row="' + k + '"]', root); if (r) r.hidden = !s[k]; });
      var vr = $('[data-o-row="video"]', root); if (vr) vr.hidden = !s.video_url; link('video', s.video_url);
      var sl = $('[data-o-sessions]', root);
      if (s.sessions && s.sessions.length) { sl.hidden = false; var ol = $('[data-o-sessions-list]', root); ol.textContent = ''; s.sessions.forEach(function (x) { ol.appendChild(el('li', '', x.date + ' · ' + x.time)); }); }
      var tEl = $('[data-done-title]', root), lead = $('[data-done-lead]', root);
      tEl.textContent = pending ? 'Recibimos tu solicitud' : '¡Tu cita está confirmada!';
      lead.textContent = pending ? 'El equipo la revisará y te avisaremos por correo en cuanto la apruebe.' : 'Te enviamos los detalles a tu correo. ¡Te esperamos!';
      $('[data-seal-ok]', root).hidden = pending; $('[data-seal-wait]', root).hidden = !pending;
      var cm = $('[data-confirm-msg]', root); if (d.confirm_html) { cm.innerHTML = d.confirm_html; cm.hidden = false; }
      $('[data-pay-note]', root).hidden = !d.needs_payment;
      link('manage', d.manage_url); link('ics', d.ics_url); link('google', d.google_url); link('outlook', d.outlook_url); link('wa', d.wa_url);
      var cb = $('[data-cal-btns]', root); cb.hidden = !(d.ics_url || d.google_url);
      var cnt = $('[data-counter]', root);
      if (s.iso && global.ApPublic) { cnt.hidden = false; global.ApPublic.mech($('[data-mech]', cnt), s.iso); }
      go(4, { nopush: true });
      try { global.history.replaceState({ bk: 4 }, '', '#reserva-lista'); } catch (e) { /* nada */ }
      var seal = $(pending ? '[data-seal-wait] [data-seal]' : '[data-seal-ok] [data-seal]', root);
      global.setTimeout(function () { if (global.ApPublic) global.ApPublic.stamp(seal); else if (seal) seal.classList.add('is-stamped'); }, reduced() ? 0 : 420);
      if (d.redirect_url) {
        var note = $('[data-redirect-note]', root); note.hidden = false; link('redirect', d.redirect_url);
        global.setTimeout(function () {
          if (boot.embed && global.parent !== global) { global.parent.postMessage({ type: 'agenda-premium:redirect', url: d.redirect_url }, '*'); } else { global.location.href = d.redirect_url; }
        }, 2000);
      }
      if (boot.embed && global.parent !== global) global.parent.postMessage({ type: 'agenda-premium:booked', slug: ev.slug, status: d.status }, '*');
    }

    // -------- arranque
    var multiChoice = ev.durations.length > 1 || (boot.hosts.length > 1);
    setProgress(1);
    if (boot.embed && !multiChoice) { st.step = 1; go(2, { instant: true, silent: true, nopush: true, force: true }); }
    else { panels[1].hidden = false; live.textContent = ''; }
    postHeight();
  }

  // ---------------------------------------------------------------- Reprogramar (página de gestión)
  function initManage(root, boot) {
    var rs = boot.reschedule; if (!rs) return;
    var fold = $('[data-resched-fold]', root), formEl = $('[data-resched-form]', root); if (!fold || !formEl) return;
    var sched = null, start = $('[data-start]', formEl), submit = $('[data-resched-submit]', formEl), line = $('[data-resched-chosen]', formEl), tzf = $('[data-tzfield]', formEl);
    fold.addEventListener('toggle', function () {
      if (!fold.open || sched) return;
      var f12 = store('get', 'ap_fmt');
      sched = new Scheduler($('[data-scheduler]', formEl), {
        event: rs.event, urls: boot.urls, tz: rs.tz, is24: f12 ? f12 === '24' : boot.time_format === '24',
        exclude: rs.exclude, maxAdvance: 90,
        getDuration: function () { return rs.duration; }, getHost: function () { return rs.host; },
        onPick: function (slot) {
          start.value = slot ? slot.s : ''; submit.disabled = !slot;
          if (slot) { line.hidden = false; line.textContent = 'Nuevo horario: ' + dayLabel(slot.date, true) + ' · ' + fmtTime(slot.t, sched.is24); } else { line.hidden = true; }
        },
        onTz: function (tz) { tzf.value = tz; }
      });
      sched.start();
    });
  }

  function init() {
    var bootEl = $('#boot'), boot = null;
    if (!bootEl) return;
    try { boot = JSON.parse(bootEl.textContent); } catch (e) { return; }
    var bk = $('[data-bk]'); if (bk) initBooking(bk, boot);
    var mg = $('[data-manage]'); if (mg) initManage(mg, boot);
  }
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init); else init();
})(window);
