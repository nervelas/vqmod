/* AUREA · panel: tema, menú móvil, confirmaciones, atajos, horario dinámico, selector de horarios. */
(() => {
  'use strict';
  document.documentElement.classList.remove('no-js');
  const root = document.documentElement;
  const themeBtn = document.querySelector('[data-theme-toggle]');
  if (themeBtn) themeBtn.addEventListener('click', () => {
    const dark = root.getAttribute('data-theme') === 'dark';
    if (dark) root.removeAttribute('data-theme'); else root.setAttribute('data-theme', 'dark');
    try { localStorage.setItem('aurea-theme', dark ? 'light' : 'dark'); } catch (e) { /* noop */ }
  });
  const side = document.getElementById('side');
  document.querySelectorAll('[data-side-toggle]').forEach((b) => b.addEventListener('click', () => { const o = side.classList.toggle('open'); b.setAttribute('aria-expanded', String(o)); }));

  // Confirmación en formularios/botones destructivos
  document.addEventListener('submit', (e) => { const m = e.target.getAttribute('data-confirm'); if (m && !window.confirm(m)) e.preventDefault(); });
  document.addEventListener('click', (e) => { const b = e.target.closest('[data-confirm]'); if (b && b.tagName === 'BUTTON' && !b.form?.hasAttribute('data-confirm') && !window.confirm(b.getAttribute('data-confirm'))) e.preventDefault(); });

  // Copiar al portapapeles
  document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
    const t = document.querySelector(b.getAttribute('data-copy'));
    if (!t) return;
    try { await navigator.clipboard.writeText(t.value || t.textContent); const o = b.textContent; b.textContent = '¡Copiado!'; setTimeout(() => { b.textContent = o; }, 1500); } catch (e) { t.select?.(); }
  }));

  document.querySelectorAll('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));

  // Filtros que envían al cambiar
  document.querySelectorAll('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));

  // Atajos de agenda
  const sc = document.querySelector('[data-shortcuts]');
  if (sc) {
    const map = JSON.parse(sc.getAttribute('data-shortcuts'));
    document.addEventListener('keydown', (e) => {
      if (e.ctrlKey || e.metaKey || e.altKey || /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) return;
      const u = map[e.key.toLowerCase()] || (e.key === 'ArrowLeft' ? map.prev : e.key === 'ArrowRight' ? map.next : null);
      if (u) { e.preventDefault(); location.href = u; }
    });
  }

  // Horario semanal: agregar / quitar bloques
  document.querySelectorAll('[data-add-block]').forEach((b) => b.addEventListener('click', () => {
    const tpl = document.getElementById('blk-tpl'); const wd = b.getAttribute('data-add-block');
    const holder = document.querySelector(`[data-blocks="${wd}"]`);
    const idx = Date.now() % 1e7 + holder.children.length;
    const node = tpl.content.firstElementChild.cloneNode(true);
    node.querySelectorAll('[data-n]').forEach((i) => { i.name = `sch[${wd}][${idx}][${i.getAttribute('data-n')}]`; });
    holder.append(node);
  }));
  document.addEventListener('click', (e) => { const r = e.target.closest('[data-rm-block]'); if (r) r.closest('.blk').remove(); });

  // Selector de horarios (nueva cita / reprogramar en el panel)
  const picker = document.querySelector('[data-slot-picker]');
  if (picker) {
    const out = picker.querySelector('[data-slot-list]'); const hidden = document.querySelector('[data-slot-value]');
    const f = { svc: document.querySelector('[name=service_id]'), pro: document.querySelector('[name=professional_id]'), date: document.querySelector('[data-slot-date]') };
    const ex = picker.getAttribute('data-exclude') || '';
    const load = async () => {
      if (!f.svc.value || !f.date.value) { out.textContent = 'Elige servicio y fecha para ver horarios.'; return; }
      out.textContent = 'Buscando horarios…';
      const qs = new URLSearchParams({ service: f.svc.value, professional: f.pro && f.pro.value ? f.pro.value : 'any', date: f.date.value, exclude: ex });
      try {
        const r = await (await fetch(`${picker.getAttribute('data-api')}?${qs}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })).json();
        out.replaceChildren();
        if (!r.ok || !r.slots.length) { out.textContent = 'Sin horarios libres ese día. Puedes escribir una hora manual abajo.'; return; }
        r.slots.forEach((s) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-line btn-sm'; b.textContent = s.time; b.addEventListener('click', () => { hidden.value = s.start.slice(0, 16).replace(' ', 'T'); out.querySelectorAll('.btn-gold').forEach((x) => x.classList.replace('btn-gold', 'btn-line')); b.classList.replace('btn-line', 'btn-gold'); }); out.append(b); out.append(' '); });
      } catch (e) { out.textContent = 'No se pudieron cargar los horarios.'; }
    };
    [f.svc, f.pro, f.date].forEach((el) => el && el.addEventListener('change', load));
    load();
  }

  // Centro de mensajes: al abrir WhatsApp se marca como enviado (CSRF por cabecera)
  const msgBox = document.querySelector('[data-csrf]');
  if (msgBox) {
    document.addEventListener('click', async (e) => {
      const a = e.target.closest('a[data-wa-id]'); if (!a) return;
      const fd = new FormData(); fd.append('action', 'sent');
      try {
        await fetch(`${msgBox.getAttribute('data-base')}/admin/mensajes/${a.getAttribute('data-wa-id')}/accion`, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': msgBox.getAttribute('data-csrf'), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const row = a.closest('.msg'); if (row) { row.style.opacity = '.45'; row.querySelector('.sent-flag')?.removeAttribute('hidden'); }
      } catch (err) { /* el enlace igualmente se abre */ }
    });
  }
})();
