/* AUREA · widget embebible. Uso:
   <script src="https://TU-DOMINIO/assets/js/widget.js" data-label="Agendar cita" defer></script>   (botón flotante)
   <div data-aurea-inline></div> + el script anterior                                                   (reserva incrustada)  */
(() => {
  'use strict';
  const me = document.currentScript || document.querySelector('script[src*="widget.js"]');
  if (!me) return;
  const origin = new URL(me.src).origin;
  const base = new URL(me.src).pathname.replace(/\/assets\/js\/widget\.js.*$/, '');
  const embedUrl = origin + base + '/embed';
  const label = me.getAttribute('data-label') || 'Agendar cita';
  const color = /^#[0-9a-fA-F]{6}$/.test(me.getAttribute('data-color') || '') ? me.getAttribute('data-color') : '#B8924A';

  const makeFrame = (inline) => {
    const f = document.createElement('iframe');
    f.src = embedUrl; f.title = label; f.loading = 'lazy'; f.setAttribute('allow', 'clipboard-write');
    f.style.cssText = 'border:0;width:100%;display:block;background:#F6F0E4;' + (inline ? 'min-height:640px' : 'height:100%');
    return f;
  };
  window.addEventListener('message', (ev) => {
    if (ev.origin !== origin || !ev.data || ev.data.aurea !== 'height') return;
    document.querySelectorAll('iframe[data-aurea]').forEach((f) => { if (f.contentWindow === ev.source && f.dataset.inline) f.style.height = Math.max(520, Number(ev.data.h) || 0) + 'px'; });
  });

  document.querySelectorAll('[data-aurea-inline]').forEach((box) => { const f = makeFrame(true); f.dataset.aurea = '1'; f.dataset.inline = '1'; box.append(f); });
  if (document.querySelector('[data-aurea-inline]') && me.getAttribute('data-button') !== 'true') return;

  const btn = document.createElement('button');
  btn.type = 'button'; btn.textContent = label;
  btn.style.cssText = `position:fixed;right:20px;bottom:20px;z-index:2147483000;background:linear-gradient(115deg,#8C6A2B,${color} 50%,#E9D29A);color:#1a1409;border:0;border-radius:4px;padding:14px 22px;font:600 15px/1 system-ui,sans-serif;letter-spacing:.04em;cursor:pointer;box-shadow:0 12px 30px -10px rgba(0,0,0,.5)`;
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483001;background:rgba(11,10,8,.72);display:none;align-items:center;justify-content:center;padding:12px';
  overlay.setAttribute('role', 'dialog'); overlay.setAttribute('aria-modal', 'true'); overlay.setAttribute('aria-label', label);
  const card = document.createElement('div');
  card.style.cssText = 'position:relative;width:min(960px,100%);height:min(92vh,860px);background:#F6F0E4;border-radius:6px;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.6)';
  const close = document.createElement('button');
  close.type = 'button'; close.textContent = '✕'; close.setAttribute('aria-label', 'Cerrar');
  close.style.cssText = 'position:absolute;top:8px;right:10px;z-index:2;background:#0B0A08;color:#E9D29A;border:0;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:16px';
  card.append(close); overlay.append(card); document.body.append(btn, overlay);
  let frame = null;
  const open = () => { if (!frame) { frame = makeFrame(false); frame.dataset.aurea = '1'; card.append(frame); } overlay.style.display = 'flex'; close.focus(); };
  const hide = () => { overlay.style.display = 'none'; btn.focus(); };
  btn.addEventListener('click', open); close.addEventListener('click', hide);
  overlay.addEventListener('click', (e) => { if (e.target === overlay) hide(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && overlay.style.display === 'flex') hide(); });
})();
