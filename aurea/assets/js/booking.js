/* AUREA · asistente de reserva (4 pasos). Vanilla ES2020; el DOM se construye con textContent (sin innerHTML). */
(() => {
  'use strict';
  const boot = JSON.parse(document.getElementById('boot').textContent);
  const root = document.getElementById('panel');
  const stepper = document.getElementById('stepper');
  const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  const DAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
  const T = boot.terms;
  const lc = (s) => s.toLowerCase();
  const st = {
    step: 1, service: null, pro: 'any', loc: 0, month: boot.today.slice(0, 7), date: '', slot: null,
    days: {}, next: null, slots: null, form: { cc: '502' }, errors: {}, coupon: null, answers: {}, busy: false,
  };

  /* ---------- utilidades ---------- */
  const h = (tag, props = {}, ...kids) => {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(props || {})) {
      if (v === false || v == null) continue;
      if (k === 'class') n.className = v;
      else if (k === 'text') n.textContent = v;
      else if (k.startsWith('on')) n.addEventListener(k.slice(2), v);
      else if (k === 'style') Object.entries(v).forEach(([a, b]) => n.style.setProperty(a, b));
      else n.setAttribute(k, v === true ? '' : v);
    }
    kids.flat(Infinity).forEach((c) => { if (c != null && c !== false) n.append(c.nodeType ? c : document.createTextNode(String(c))); });
    return n;
  };
  const money = (n) => boot.currency + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const pad = (n) => String(n).padStart(2, '0');
  const dateLong = (iso) => { const [y, m, d] = iso.slice(0, 10).split('-').map(Number); const w = new Date(y, m - 1, d).getDay(); return `${DAYS[w]} ${d} de ${MONTHS[m - 1]} de ${y}`; };
  const url = (p) => boot.base + p;
  const api = async (path, opts = {}) => {
    const res = await fetch(url(path), { credentials: 'same-origin', headers: { 'X-CSRF-Token': boot.csrf, Accept: 'application/json' }, ...opts });
    let data = null;
    try { data = await res.json(); } catch (e) { data = { ok: false, error: 'Respuesta inesperada del servidor.' }; }
    return { status: res.status, data };
  };
  const resetAvail = () => { st.days = {}; st.date = ''; st.slot = null; st.slots = null; st.next = null; st.nextLoaded = false; };
  const svcById = (id) => boot.services.find((s) => s.id === id);
  const eligiblePros = () => boot.professionals.filter((p) => st.service && Object.prototype.hasOwnProperty.call(p.services, String(st.service.id))
    && (!st.loc || !p.locations.length || p.locations.includes(st.loc)));
  const proPrice = (p) => { const o = p ? p.services[String(st.service.id)] : null; return o == null ? st.service.price : o; };
  const curPrice = () => {
    if (st.pro === 'any') return st.service.price;
    const p = boot.professionals.find((x) => x.id === st.pro);
    return p ? proPrice(p) : st.service.price;
  };
  const depositFor = (total) => {
    const s = st.service; if (total <= 0 || s.deposit_type === 'none') return 0;
    const d = s.deposit_type === 'percent' ? total * Math.min(100, s.deposit_value) / 100 : s.deposit_value;
    return Math.round(Math.min(total, Math.max(0, d)) * 100) / 100;
  };
  const modalityLabel = { presencial: 'Presencial', virtual: 'Virtual', domicilio: 'A domicilio' };
  const heading = (n, text, sub) => [h('h2', { tabindex: '-1', id: 'step-h' }, h('span', { class: 'n num', text: n }), text), sub ? h('p', { class: 'sub', text: sub }) : null];
  const postHeight = () => { if (boot.embed && window.parent !== window) window.parent.postMessage({ aurea: 'height', h: document.documentElement.scrollHeight }, '*'); };
  if (boot.embed && 'ResizeObserver' in window) new ResizeObserver(postHeight).observe(document.body);

  /* ---------- navegación ---------- */
  const needsProStep = () => eligiblePros().length > 1 || boot.locations.length > 1;
  const go = (n, focus = true) => {
    st.step = n;
    render(focus);
  };
  const next = () => { if (st.step === 1 && !needsProStep()) { const e = eligiblePros(); st.pro = e.length === 1 ? e[0].id : 'any'; go(3); } else go(st.step + 1); };
  const back = () => { if (st.step === 3 && !needsProStep()) go(1); else go(st.step - 1); };

  function drawStepper() {
    stepper.querySelectorAll('li').forEach((li) => {
      const n = Number(li.dataset.step);
      li.classList.toggle('done', n < st.step);
      li.classList.toggle('cur', n === st.step);
      const b = li.querySelector('button');
      b.disabled = n >= st.step;
      if (n === st.step) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
    });
  }
  stepper.querySelectorAll('li').forEach((li) => li.querySelector('button').addEventListener('click', () => {
    const n = Number(li.dataset.step); if (n < st.step) go(n);
  }));

  function render(focus) {
    drawStepper();
    root.replaceChildren();
    const panel = h('div', { class: 'panel' });
    root.append(panel);
    ({ 1: step1, 2: step2, 3: step3, 4: step4 })[st.step](panel);
    if (focus) { const hd = document.getElementById('step-h'); if (hd) { hd.focus({ preventScroll: true }); stepper.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }
    postHeight();
  }

  const navRow = (opts = {}) => h('div', { class: 'nav-row' },
    st.step > 1 ? h('button', { type: 'button', class: 'btn btn-ghost', onclick: back, text: '← Atrás' }) : h('span'),
    opts.next ? h('button', { type: 'button', class: 'btn btn-gold', onclick: opts.next, disabled: opts.disabled ? true : null, text: opts.label || 'Continuar' }) : null);

  /* ---------- Paso 1: servicio ---------- */
  let catFilter = 0;
  function step1(p) {
    p.append(...heading('01 —', 'Servicio', `¿Qué ${lc(T.appt)} necesitas?`));
    const cats = [...new Map(boot.services.filter((s) => s.category).map((s) => [s.category, s.category_name])).entries()];
    if (cats.length > 1) {
      const tabs = h('div', { class: 'cat-tabs', role: 'group', 'aria-label': 'Categorías' },
        h('button', { type: 'button', 'aria-pressed': String(catFilter === 0), onclick: () => { catFilter = 0; render(false); }, text: 'Todos' }),
        cats.map(([id, name]) => h('button', { type: 'button', 'aria-pressed': String(catFilter === id), onclick: () => { catFilter = id; render(false); }, text: name })));
      p.append(tabs);
    }
    const list = boot.services.filter((s) => !catFilter || s.category === catFilter);
    if (!list.length) p.append(h('div', { class: 'empty' }, h('h3', { text: 'Aún no hay servicios disponibles' }), h('p', { text: 'Escríbenos y con gusto te atendemos.' })));
    p.append(h('div', { class: 'cards two', role: 'radiogroup', 'aria-label': 'Servicios' }, list.map((s) => {
      const chips = h('div', { class: 'chips' }, h('span', { class: 'badge', text: `${s.duration} min` }), h('span', { class: 'badge', text: modalityLabel[s.modality] }),
        s.capacity > 1 ? h('span', { class: 'badge badge-gold', text: 'Sesión grupal' }) : null,
        s.deposit_type !== 'none' ? h('span', { class: 'badge badge-warn', text: 'Requiere anticipo' }) : null);
      return h('button', { type: 'button', class: 'opt', role: 'radio', 'aria-checked': String(st.service && st.service.id === s.id), onclick: () => { selectService(s); } },
        h('span', { class: 't', text: s.name }),
        s.desc ? h('span', { class: 'd', text: s.desc }) : h('span', { class: 'd', text: '' }),
        boot.showPrices ? h('span', { class: 'p', text: s.price > 0 ? money(s.price) : 'Sin costo' }, s.price > 0 ? h('small', { text: 'desde' }) : null) : null, chips);
    })));
  }
  function selectService(s) {
    if (!st.service || st.service.id !== s.id) { st.service = s; st.pro = 'any'; resetAvail(); st.coupon = null; st.answers = {}; st.month = boot.today.slice(0, 7); }
    next();
  }

  /* ---------- Paso 2: profesional / sede ---------- */
  function step2(p) {
    p.append(...heading('02 —', T.professional, `Elige quién te atenderá o deja que asignemos a la persona disponible.`));
    if (boot.locations.length > 1) {
      const sel = h('select', { id: 'loc', onchange: (e) => { st.loc = Number(e.target.value); st.pro = 'any'; resetAvail(); render(false); } },
        h('option', { value: '0', text: 'Cualquier sede' }), boot.locations.map((l) => h('option', { value: String(l.id), text: `${l.name}${l.address ? ' — ' + l.address : ''}`, selected: st.loc === l.id ? true : null })));
      p.append(h('div', { class: 'field' }, h('label', { for: 'loc', text: 'Sede' }), sel));
    }
    const pros = eligiblePros();
    const cards = [h('button', { type: 'button', class: 'opt person-opt', role: 'radio', 'aria-checked': String(st.pro === 'any'), onclick: () => { st.pro = 'any'; resetAvail(); go(3); } },
      h('span', { class: 'av', 'aria-hidden': 'true', text: '✦' }), h('span', {}, h('span', { class: 't', text: 'Cualquiera disponible' }), h('br'), h('span', { class: 'd', text: 'Asignamos de forma equitativa y te mostramos más horarios.' })))];
    pros.forEach((pr) => cards.push(h('button', { type: 'button', class: 'opt person-opt', role: 'radio', 'aria-checked': String(st.pro === pr.id), onclick: () => { st.pro = pr.id; resetAvail(); go(3); } },
      h('span', { class: 'av' }, pr.photo ? h('img', { src: pr.photo, alt: '', width: '54', height: '54', loading: 'lazy' }) : (pr.name.split(/\s+/).find((w) => !/^(dr|dra|lic|lcda|ing|arq|msc|mtro|mtra|psic|nut|not|abg)\.?$/i.test(w)) || pr.name).charAt(0).toUpperCase()),
      h('span', {}, h('span', { class: 't', text: pr.name }), h('br'), h('span', { class: 'd', text: pr.title + (boot.showPrices && proPrice(pr) !== st.service.price ? ` · ${money(proPrice(pr))}` : '') })))));
    p.append(h('div', { class: 'cards two', role: 'radiogroup', 'aria-label': T.professional }, cards), navRow());
  }

  /* ---------- Paso 3: fecha y hora ---------- */
  const q = (extra = {}) => new URLSearchParams({ service: st.service.id, professional: st.pro, location: st.loc || '', ...extra }).toString();
  async function loadDays(month, withNext) {
    const key = `${st.service.id}|${st.pro}|${st.loc}|${month}`;
    if (st.days[key] && !withNext) return st.days[key];
    const { data } = await api(`/api/days?${q({ month, next: withNext ? '1' : '' })}`);
    if (data.ok) { st.days[key] = data.days; if (withNext) st.next = data.next || null; return data.days; }
    return {};
  }
  const dayKey = () => `${st.service.id}|${st.pro}|${st.loc}|${st.month}`;

  async function step3(p) {
    p.append(...heading('03 —', 'Fecha y hora', 'Elige el día y la hora que mejor te acomoden.'));
    const wrap = h('div', { id: 'cal-area' }, h('div', { class: 'skeleton' }));
    p.append(wrap, navRow({ next: () => next(), label: 'Continuar', disabled: !st.slot }));
    const firstLoad = !st.nextLoaded;
    const days = await loadDays(st.month, firstLoad);
    if (firstLoad) {
      st.nextLoaded = true;
      if (st.next && !Object.keys(days).length && !st.date) { st.month = st.next.date.slice(0, 7); await loadDays(st.month, false); }
    }
    drawCal();
  }
  function drawCal() {
    const area = document.getElementById('cal-area'); if (!area) return;
    const days = st.days[dayKey()] || {};
    const [y, m] = st.month.split('-').map(Number);
    const first = new Date(y, m - 1, 1);
    const offset = (first.getDay() + 6) % 7;
    const dim = new Date(y, m, 0).getDate();
    const minMonth = boot.today.slice(0, 7);
    const grid = h('div', { class: 'cal-grid', role: 'grid', 'aria-label': `${MONTHS[m - 1]} ${y}` }, ['L', 'M', 'M', 'J', 'V', 'S', 'D'].map((d) => h('div', { class: 'dow', 'aria-hidden': 'true', text: d })));
    for (let i = 0; i < offset; i++) grid.append(h('span', { class: 'd' }));
    for (let d = 1; d <= dim; d++) {
      const iso = `${y}-${pad(m)}-${pad(d)}`;
      if (days[iso]) grid.append(h('button', { type: 'button', class: 'has', 'aria-pressed': String(st.date === iso), 'aria-label': `${dateLong(iso)}, ${days[iso]} horarios`, text: String(d), onclick: () => pickDay(iso) }));
      else grid.append(h('span', { class: `d${iso === boot.today ? ' today' : ''}`, 'aria-label': `${dateLong(iso)}, sin horarios`, text: String(d) }));
    }
    const cal = h('div', { class: 'cal' },
      h('div', { class: 'cal-head' },
        h('button', { type: 'button', 'aria-label': 'Mes anterior', disabled: st.month <= minMonth ? true : null, onclick: () => shiftMonth(-1), text: '‹' }),
        h('div', { class: 'm', 'aria-live': 'polite', text: `${MONTHS[m - 1]} ${y}` }),
        h('button', { type: 'button', 'aria-label': 'Mes siguiente', onclick: () => shiftMonth(1), text: '›' })),
      grid, h('div', { class: 'tz', text: `🕑 Horarios en ${boot.tzLabel}` }));
    const right = h('div', { id: 'slots-area' });
    const kids = [];
    if (st.next && !st.slot) {
      kids.push(h('div', { class: 'next-box' }, h('div', {}, h('span', { class: 'eyebrow', text: 'Próximo horario disponible' }), h('strong', { text: `${dateLong(st.next.start)} · ${st.next.time}` })),
        h('button', { type: 'button', class: 'btn btn-gold btn-sm', onclick: () => { st.month = st.next.date.slice(0, 7); pickDay(st.next.date, st.next.start); }, text: 'Elegir este horario' })));
    }
    area.replaceChildren(...kids, h('div', { class: 'cal-wrap' }, cal, right));
    if (st.date && st.slots) drawSlots(); else if (!Object.keys(days).length) noDays(right); else right.append(h('p', { class: 'sub', text: 'Selecciona un día marcado en el calendario.' }));
  }
  async function shiftMonth(delta) {
    const [y, m] = st.month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    st.month = `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
    document.getElementById('cal-area').querySelector('.cal')?.classList.add('is-loading');
    await loadDays(st.month, false);
    drawCal();
  }
  async function pickDay(iso, preselect) {
    st.date = iso; st.slot = null; st.slots = null;
    drawCal();
    const area = document.getElementById('slots-area');
    area.replaceChildren(h('div', { class: 'slots' }, Array.from({ length: 8 }, () => h('div', { class: 'skeleton' }))));
    const { data } = await api(`/api/slots?${q({ date: iso })}`);
    st.slots = data.ok ? data.slots : [];
    if (preselect) st.slot = st.slots.find((s) => s.start === preselect) || null;
    drawCal();
    if (st.slot) updateContinue();
  }
  function updateContinue() {
    const b = document.querySelector('.nav-row .btn-gold'); if (b) b.disabled = !st.slot;
  }
  function drawSlots() {
    const area = document.getElementById('slots-area'); area.replaceChildren();
    area.append(h('div', { class: 'slots-title', text: dateLong(st.date) }));
    if (!st.slots.length) { noDays(area, true); return; }
    area.append(h('div', { class: 'slots', role: 'group', 'aria-label': 'Horarios disponibles' }, st.slots.map((s, i) =>
      h('button', { type: 'button', class: 'slot', style: { '--i': i }, 'aria-pressed': String(st.slot && st.slot.start === s.start), onclick: () => {
        st.slot = s; area.querySelectorAll('.slot').forEach((b) => b.setAttribute('aria-pressed', 'false')); area.querySelectorAll('.slot')[i].setAttribute('aria-pressed', 'true'); updateContinue();
      } }, s.time, st.service.capacity > 1 ? h('small', { text: `${s.left} cupos` }) : null))));
  }
  function noDays(area, dayOnly) {
    const box = h('div', { class: 'empty' }, h('h3', { text: dayOnly ? 'Sin horarios para este día' : 'No hay horarios disponibles en este mes' }),
      h('p', { text: 'Prueba con otro día o mes' + (boot.waitlist ? ', o anótate en la lista de espera y te avisamos si se libera uno.' : '.') }));
    if (boot.waitlist) box.append(h('button', { type: 'button', class: 'btn btn-line btn-sm', onclick: () => waitlistForm(area), text: 'Anotarme en lista de espera' }));
    area.append(box);
  }
  function waitlistForm(area) {
    const f = h('form', { class: 'box', novalidate: true });
    const add = (label, input, id) => f.append(h('div', { class: 'field' }, h('label', { for: id, text: label }), input));
    const to = new Date(boot.today + 'T12:00:00'); to.setDate(to.getDate() + 30);
    const inName = h('input', { id: 'wl-n', type: 'text', required: true, autocomplete: 'name', maxlength: '150' });
    const inPh = h('input', { id: 'wl-p', type: 'tel', inputmode: 'tel', required: true, autocomplete: 'tel', placeholder: '5555 1234' });
    const inEm = h('input', { id: 'wl-e', type: 'email', autocomplete: 'email' });
    const inFrom = h('input', { id: 'wl-f', type: 'date', value: boot.today, min: boot.today });
    const inTo = h('input', { id: 'wl-t', type: 'date', value: `${to.getFullYear()}-${pad(to.getMonth() + 1)}-${pad(to.getDate())}`, min: boot.today });
    const inCons = h('input', { id: 'wl-c', type: 'checkbox' });
    const msg = h('div', { role: 'status' });
    add('Nombre', inName, 'wl-n'); add('Teléfono (WhatsApp)', inPh, 'wl-p'); add('Correo (opcional)', inEm, 'wl-e');
    f.append(h('div', { class: 'row row-2' }, h('div', { class: 'field' }, h('label', { for: 'wl-f', text: 'Desde' }), inFrom), h('div', { class: 'field' }, h('label', { for: 'wl-t', text: 'Hasta' }), inTo)));
    f.append(h('label', { class: 'check' }, inCons, h('span', {}, 'Acepto el ', h('a', { href: url('/privacidad'), target: '_blank', rel: 'noopener', text: 'aviso de privacidad' }))), msg,
      h('button', { class: 'btn btn-gold btn-sm', type: 'submit', text: 'Anotarme' }));
    f.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(); fd.append('_csrf', boot.csrf); fd.append('service_id', st.service.id); fd.append('professional_id', st.pro === 'any' ? '' : st.pro);
      fd.append('name', inName.value); fd.append('phone', inPh.value); fd.append('email', inEm.value); fd.append('date_from', inFrom.value); fd.append('date_to', inTo.value); fd.append('consent', inCons.checked ? '1' : '0'); fd.append('website', '');
      const capTok = f.querySelector('[name="cf-turnstile-response"],[name="h-captcha-response"]'); if (capTok) fd.append(capTok.name, capTok.value);
      const { data } = await api('/api/waitlist', { method: 'POST', body: fd });
      msg.className = `alert ${data.ok ? 'alert-ok' : 'alert-err'}`;
      msg.textContent = data.ok ? data.message : (data.error + ' ' + Object.values(data.fields || {}).join(' '));
      if (data.ok) f.querySelector('button[type=submit]').disabled = true;
    });
    area.append(f);
    mountCaptcha(f);
    inName.focus();
  }

  /* ---------- Paso 4: datos ---------- */
  const ccList = [['502', '🇬🇹 +502'], ['1', '🇺🇸 +1'], ['52', '🇲🇽 +52'], ['503', '🇸🇻 +503'], ['504', '🇭🇳 +504'], ['505', '🇳🇮 +505'], ['506', '🇨🇷 +506'], ['507', '🇵🇦 +507'], ['34', '🇪🇸 +34']];
  function fieldRow(id, label, input, opts = {}) {
    const err = st.errors[opts.key || id];
    if (err) input.setAttribute('aria-invalid', 'true');
    return h('div', { class: 'field', 'data-field': opts.key || id }, h('label', { for: id, class: opts.req ? 'req' : null, text: label }), input, opts.help ? h('div', { class: 'help', text: opts.help }) : null, err ? h('div', { class: 'err', role: 'alert', text: err }) : null);
  }
  function condVisible(f) {
    if (!f.cond_field) return true;
    const v = st.answers[f.cond_field];
    return Array.isArray(v) ? v.includes(f.cond_value) : String(v ?? '') === f.cond_value;
  }
  function renderDynamicFields(container) {
    container.replaceChildren();
    (boot.fields[String(st.service.id)] || []).forEach((f) => {
      if (!condVisible(f)) return;
      const id = `f${f.id}`; let input; const val = st.answers[f.id];
      if (f.type === 'textarea') input = h('textarea', { id, name: `answers[${f.id}]`, maxlength: '3000', oninput: (e) => { st.answers[f.id] = e.target.value; } }, val || '');
      else if (f.type === 'select') { input = h('select', { id, name: `answers[${f.id}]`, onchange: (e) => { st.answers[f.id] = e.target.value; renderDynamicFields(container); } }, h('option', { value: '', text: 'Selecciona…' }), f.options.map((o) => h('option', { value: o, text: o, selected: val === o ? true : null }))); }
      else if (f.type === 'checkbox' && f.options.length) {
        input = h('div', { id }, f.options.map((o, i) => h('label', { class: 'check' }, h('input', { type: 'checkbox', name: `answers[${f.id}][]`, value: o, checked: (val || []).includes(o) ? true : null, onchange: (e) => { const set = new Set(st.answers[f.id] || []); e.target.checked ? set.add(o) : set.delete(o); st.answers[f.id] = [...set]; renderDynamicFields(container); } }), h('span', { text: o }))));
      } else if (f.type === 'checkbox' || f.type === 'consent') {
        input = h('label', { class: 'check', id }, h('input', { type: 'checkbox', name: `answers[${f.id}]`, value: '1', checked: val === '1' ? true : null, onchange: (e) => { st.answers[f.id] = e.target.checked ? '1' : ''; renderDynamicFields(container); } }), h('span', { text: f.label }));
        container.append(h('div', { class: 'field', 'data-field': `f${f.id}` }, input, f.help ? h('div', { class: 'help', text: f.help }) : null, st.errors[`f${f.id}`] ? h('div', { class: 'err', role: 'alert', text: st.errors[`f${f.id}`] }) : null));
        return;
      } else if (f.type === 'file') input = h('input', { id, type: 'file', name: `file_${f.id}`, accept: '.pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xlsx,.txt' });
      else input = h('input', { id, name: `answers[${f.id}]`, type: f.type === 'number' ? 'number' : (f.type === 'date' ? 'date' : 'text'), step: f.type === 'number' ? 'any' : null, maxlength: '255', value: val || '', oninput: (e) => { st.answers[f.id] = e.target.value; renderDynamicFields(container); } });
      container.append(fieldRow(id, f.label, input, { req: f.required, help: f.help, key: `f${f.id}` }));
    });
  }
  function summaryBox() {
    const price = curPrice(); const disc = st.coupon ? st.coupon.discount : 0; const total = Math.max(0, price - disc); const dep = depositFor(total);
    const prof = st.pro === 'any' ? 'Primer profesional disponible' : (boot.professionals.find((p) => p.id === st.pro) || {}).name;
    const rows = [['Servicio', st.service.name], [T.professional, prof], ['Fecha', dateLong(st.slot.start)], ['Hora', `${st.slot.time} (${st.service.duration} min)`], ['Modalidad', modalityLabel[st.service.modality]]];
    const dl = h('dl', {}, rows.map(([a, b]) => [h('dt', { text: a }), h('dd', { text: b })]));
    const box = h('aside', { class: 'summary', 'aria-label': 'Resumen' }, h('h3', { text: 'Resumen' }), dl);
    if (boot.showPrices) {
      if (disc > 0) box.append(h('dl', { style: { 'margin-top': '10px' } }, h('dt', { text: 'Descuento' }), h('dd', { text: `− ${money(disc)}` })));
      box.append(h('div', { class: 'tot' }, h('span', { text: 'Total' }), h('b', { class: 'num', text: total > 0 ? money(total) : 'Sin costo' })));
      if (dep > 0) box.append(h('p', { style: { 'margin-top': '10px', 'font-size': '.85rem', color: 'var(--on-dark-muted)' }, text: `Anticipo requerido: ${money(dep)}. Te indicaremos cómo pagarlo al terminar.` }));
    }
    if (!st.service.auto_confirm || dep > 0) box.append(h('p', { style: { 'margin-top': '10px', 'font-size': '.85rem', color: 'var(--on-dark-muted)' }, text: 'Tu solicitud quedará pendiente hasta que la confirmemos.' }));
    return box;
  }
  function step4(p) {
    p.append(...heading('04 —', 'Tus datos', 'Casi listo. Solo necesitamos unos datos para confirmar tu reserva.'));
    const f = st.form;
    const inName = h('input', { id: 'name', name: 'name', type: 'text', autocomplete: 'name', required: true, maxlength: '150', value: f.name || '', oninput: (e) => { f.name = e.target.value; } });
    const cc = h('select', { id: 'cc', name: 'cc', 'aria-label': 'Código de país', onchange: (e) => { f.cc = e.target.value; } }, ccList.map(([v, l]) => h('option', { value: v, text: l, selected: f.cc === v ? true : null })));
    const inPhone = h('input', { id: 'phone', name: 'phone', type: 'tel', inputmode: 'tel', autocomplete: 'tel-national', required: true, placeholder: '5555 1234', value: f.phone || '', oninput: (e) => { f.phone = e.target.value; } });
    const inEmail = h('input', { id: 'email', name: 'email', type: 'email', autocomplete: 'email', maxlength: '190', value: f.email || '', oninput: (e) => { f.email = e.target.value; } });
    const phoneField = h('div', { class: 'field', 'data-field': 'phone' }, h('label', { for: 'phone', class: 'req', text: 'Teléfono / WhatsApp' }), h('div', { class: 'phone-row' }, cc, inPhone),
      h('div', { class: 'help', text: 'Te enviaremos la confirmación por WhatsApp.' }), st.errors.phone ? h('div', { class: 'err', role: 'alert', text: st.errors.phone }) : null);
    if (st.errors.phone) inPhone.setAttribute('aria-invalid', 'true');
    const dyn = h('div', { id: 'dyn' });
    const nit = h('input', { id: 'nit', name: 'nit', type: 'text', maxlength: '20', value: f.nit || '', placeholder: 'CF', oninput: (e) => { f.nit = e.target.value; } });
    const consent = h('input', { id: 'consent', name: 'consent', type: 'checkbox', value: '1', checked: f.consent ? true : null, onchange: (e) => { f.consent = e.target.checked; } });
    const coupon = h('input', { id: 'coupon', name: 'coupon', type: 'text', maxlength: '40', autocapitalize: 'characters', value: f.coupon || '', oninput: (e) => { f.coupon = e.target.value; st.coupon = null; } });
    const couponMsg = h('div', { class: 'help', 'aria-live': 'polite' });
    const applyBtn = h('button', { type: 'button', class: 'btn btn-line btn-sm', text: 'Aplicar', onclick: async () => {
      if (!coupon.value.trim()) return;
      applyBtn.classList.add('is-loading');
      const fd = new FormData(); fd.append('service_id', st.service.id); fd.append('professional_id', st.pro === 'any' ? '' : st.pro); fd.append('code', coupon.value);
      const { data } = await api('/api/coupon', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': boot.csrf, Accept: 'application/json' } });
      applyBtn.classList.remove('is-loading');
      if (data.ok) { st.coupon = { code: coupon.value, discount: data.discount }; render(false); } else { st.coupon = null; couponMsg.className = 'err'; couponMsg.textContent = data.error; }
    } });
    const form = h('form', { id: 'book-form', novalidate: true, enctype: 'multipart/form-data' });
    form.append(h('input', { type: 'text', name: 'website', class: 'hp', tabindex: '-1', autocomplete: 'off', 'aria-hidden': 'true' }));
    form.append(fieldRow('name', 'Nombre completo', inName, { req: true }), phoneField, fieldRow('email', boot.emailRequired ? 'Correo electrónico' : 'Correo electrónico (opcional)', inEmail, { req: boot.emailRequired, help: 'Para enviarte el comprobante y recordatorios.' }));
    if (st.service.modality === 'domicilio') form.append(fieldRow('home', 'Dirección del servicio a domicilio', h('input', { id: 'home', name: 'home_address', type: 'text', maxlength: '255', value: f.home || '', oninput: (e) => { f.home = e.target.value; } }), { req: true, key: 'home_address' }));
    renderDynamicFields(dyn);
    form.append(dyn);
    form.append(fieldRow('client_note', 'Comentarios (opcional)', h('textarea', { id: 'client_note', name: 'client_note', maxlength: '1000', oninput: (e) => { f.note = e.target.value; } }, f.note || '')));
    const nitWrap = h('details', { open: f.nit ? true : null }, h('summary', { style: { cursor: 'pointer', margin: '0 0 12px', 'font-weight': '600' }, text: 'Necesito factura con NIT' }), fieldRow('nit', 'NIT', nit, { help: 'Opcional. Para datos de facturación.' }));
    form.append(nitWrap);
    form.append(h('div', { class: 'field', 'data-field': 'coupon' }, h('label', { for: 'coupon', text: 'Cupón o certificado de regalo' }), h('div', { style: { display: 'flex', gap: '10px' } }, coupon, applyBtn), couponMsg,
      st.coupon ? h('div', { class: 'help', style: { color: 'var(--ok)' }, text: `✓ Cupón aplicado: − ${money(st.coupon.discount)}` }) : null, st.errors.coupon ? h('div', { class: 'err', text: st.errors.coupon }) : null));
    if (boot.privacyRequired) form.append(h('div', { class: 'field', 'data-field': 'consent' }, h('label', { class: 'check' }, consent, h('span', {}, 'Acepto el ', h('a', { href: url('/privacidad'), target: '_blank', rel: 'noopener', text: 'aviso de privacidad' }), ' y el tratamiento de mis datos para gestionar mi ' + lc(T.appt) + '.')), st.errors.consent ? h('div', { class: 'err', role: 'alert', text: st.errors.consent }) : null));
    const capBox = h('div', { id: 'captcha-box' }); form.append(capBox);
    const err = h('div', { id: 'form-err', role: 'alert' });
    const submit = h('button', { type: 'submit', class: 'btn btn-gold', text: st.service.auto_confirm && depositFor(Math.max(0, curPrice() - (st.coupon ? st.coupon.discount : 0))) <= 0 ? 'Confirmar reserva' : 'Enviar solicitud' });
    form.append(err, h('div', { class: 'nav-row' }, h('button', { type: 'button', class: 'btn btn-ghost', onclick: back, text: '← Atrás' }), submit));
    form.addEventListener('submit', (ev) => { ev.preventDefault(); submitBooking(form, submit, err); });
    p.append(h('div', { class: 'form-grid' }, form, summaryBox()));
    mountCaptcha(form);
    const firstErr = form.querySelector('[aria-invalid="true"]'); if (firstErr) firstErr.focus();
  }

  async function submitBooking(form, btn, errBox) {
    if (st.busy) return; st.busy = true; btn.classList.add('is-loading'); errBox.className = ''; errBox.textContent = '';
    const fd = new FormData(form);
    fd.append('_csrf', boot.csrf); fd.append('service_id', st.service.id); fd.append('professional_id', st.pro === 'any' ? 'any' : st.pro);
    fd.append('location_id', st.loc || ''); fd.append('start', st.slot.start); fd.set('consent', st.form.consent ? '1' : '0');
    const { status, data } = await api('/api/book', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': boot.csrf, Accept: 'application/json' } });
    st.busy = false; btn.classList.remove('is-loading');
    if (data.ok) { location.href = data.url; return; }
    st.errors = data.fields || {};
    if (status === 409 || data.code === 'slot_taken') {
      st.slot = null; st.slots = null; st.days = {}; st.nextLoaded = false; st.step = 3; render(true);
      const a = h('div', { class: 'alert alert-err', role: 'alert', text: data.error }); root.querySelector('.panel').prepend(a); return;
    }
    if (data.code === 'csrf') { errBox.className = 'alert alert-err'; errBox.textContent = data.error; return; }
    const keys = Object.keys(st.errors);
    if (keys.length) { render(false); const e2 = document.getElementById('form-err'); if (e2) { e2.className = 'alert alert-err'; e2.textContent = data.error || 'Revisa los datos marcados.'; } return; }
    errBox.className = 'alert alert-err'; errBox.textContent = data.error || 'No pudimos completar la reserva.';
    if (window.turnstile && st.capId != null) { try { window.turnstile.reset(st.capId); } catch (e) { /* noop */ } }
  }

  /* ---------- Captcha opcional (Turnstile / hCaptcha) ---------- */
  function mountCaptcha(container) {
    const c = boot.captcha; if (!c || c.provider === 'none') return;
    const box = h('div', { class: 'field' }); container.insertBefore(box, container.querySelector('button[type=submit]')?.closest('.nav-row') || container.lastChild);
    const src = c.provider === 'turnstile' ? 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit' : 'https://js.hcaptcha.com/1/api.js?render=explicit';
    const draw = () => { const api2 = c.provider === 'turnstile' ? window.turnstile : window.hcaptcha; if (api2) st.capId = api2.render(box, { sitekey: c.siteKey }); };
    if ((c.provider === 'turnstile' && window.turnstile) || (c.provider === 'hcaptcha' && window.hcaptcha)) { draw(); return; }
    const s = document.createElement('script'); s.src = src; s.async = true; s.onload = draw; document.head.append(s);
  }

  /* ---------- arranque ---------- */
  const pre = boot.pre || {};
  if (pre.service) {
    const s = svcById(pre.service);
    if (s) { st.service = s; if (pre.professional && eligiblePros().some((p) => p.id === pre.professional)) st.pro = pre.professional; st.step = needsProStep() && !(pre.professional && st.pro !== 'any') ? 2 : 3; }
  }
  render(false);
})();
