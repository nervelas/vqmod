/* AUREA · reprogramación por token: calendario + horarios del mismo servicio y profesional. */
(() => {
  'use strict';
  const boot = JSON.parse(document.getElementById('boot').textContent);
  const root = document.getElementById('resched-root');
  const form = document.getElementById('resched-form');
  const startInput = document.getElementById('resched-start');
  const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  const DAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
  const pad = (n) => String(n).padStart(2, '0');
  const h = (tag, props = {}, ...kids) => {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(props)) { if (v == null || v === false) continue; if (k === 'class') n.className = v; else if (k === 'text') n.textContent = v; else if (k.startsWith('on')) n.addEventListener(k.slice(2), v); else if (k === 'style') Object.entries(v).forEach(([a, b]) => n.style.setProperty(a, b)); else n.setAttribute(k, v === true ? '' : v); }
    kids.flat(Infinity).forEach((c) => { if (c != null) n.append(c.nodeType ? c : document.createTextNode(String(c))); });
    return n;
  };
  const dateLong = (iso) => { const [y, m, d] = iso.split('-').map(Number); return `${DAYS[new Date(y, m - 1, d).getDay()]} ${d} de ${MONTHS[m - 1]} de ${y}`; };
  const qs = (extra) => new URLSearchParams({ service: boot.service, professional: boot.professional, location: boot.location || '', exclude: boot.token, ...extra }).toString();
  const get = async (path) => (await fetch(boot.base + path, { credentials: 'same-origin', headers: { Accept: 'application/json' } })).json();
  const st = { month: boot.today.slice(0, 7), days: {}, date: '', slot: '' };

  async function load() {
    root.replaceChildren(h('div', { class: 'skeleton' }));
    const d = await get(`/api/days?${qs({ month: st.month })}`);
    st.days = d.ok ? d.days : {};
    draw();
  }
  function draw() {
    const [y, m] = st.month.split('-').map(Number);
    const offset = (new Date(y, m - 1, 1).getDay() + 6) % 7;
    const dim = new Date(y, m, 0).getDate();
    const grid = h('div', { class: 'cal-grid' }, ['L', 'M', 'M', 'J', 'V', 'S', 'D'].map((x) => h('div', { class: 'dow', 'aria-hidden': 'true', text: x })));
    for (let i = 0; i < offset; i++) grid.append(h('span', { class: 'd' }));
    for (let d = 1; d <= dim; d++) {
      const iso = `${y}-${pad(m)}-${pad(d)}`;
      grid.append(st.days[iso] ? h('button', { type: 'button', class: 'has', 'aria-pressed': String(st.date === iso), 'aria-label': dateLong(iso), text: String(d), onclick: () => pick(iso) }) : h('span', { class: 'd', text: String(d) }));
    }
    const shift = (dlt) => { const dt = new Date(y, m - 1 + dlt, 1); st.month = `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}`; load(); };
    const cal = h('div', { class: 'cal' }, h('div', { class: 'cal-head' }, h('button', { type: 'button', 'aria-label': 'Mes anterior', disabled: st.month <= boot.today.slice(0, 7) ? true : null, onclick: () => shift(-1), text: '‹' }), h('div', { class: 'm', text: `${MONTHS[m - 1]} ${y}` }), h('button', { type: 'button', 'aria-label': 'Mes siguiente', onclick: () => shift(1), text: '›' })), grid, h('div', { class: 'tz', text: `🕑 Horarios en ${boot.tzLabel}` }));
    root.replaceChildren(h('div', { class: 'cal-wrap' }, cal, h('div', { id: 'rs-slots' }, Object.keys(st.days).length ? h('p', { class: 'sub', text: 'Selecciona un día marcado.' }) : h('div', { class: 'empty' }, h('h3', { text: 'Sin horarios este mes' }), h('p', { text: 'Prueba con el mes siguiente.' })))));
  }
  async function pick(iso) {
    st.date = iso; st.slot = ''; startInput.value = ''; form.classList.add('hidden'); draw();
    const area = document.getElementById('rs-slots');
    area.replaceChildren(h('div', { class: 'slots' }, Array.from({ length: 6 }, () => h('div', { class: 'skeleton' }))));
    const d = await get(`/api/slots?${qs({ date: iso })}`);
    area.replaceChildren(h('div', { class: 'slots-title', text: dateLong(iso) }), h('div', { class: 'slots' }, (d.slots || []).map((s, i) => h('button', { type: 'button', class: 'slot', style: { '--i': i }, 'aria-pressed': 'false', text: s.time, onclick: (e) => {
      area.querySelectorAll('.slot').forEach((b) => b.setAttribute('aria-pressed', 'false')); e.currentTarget.setAttribute('aria-pressed', 'true'); startInput.value = s.start; form.classList.remove('hidden');
    } }))));
    if (!(d.slots || []).length) area.append(h('div', { class: 'empty' }, h('h3', { text: 'Sin horarios para este día' })));
  }
  load();
})();
