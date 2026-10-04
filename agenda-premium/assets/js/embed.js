/* Agenda Premium · insertar la reserva en otro sitio.
 * <script src="https://TU-DOMINIO/assets/js/embed.js" data-event="mi-evento" data-mode="inline|popup|float"
 *         data-label="Reservar cita" data-color="#C9A050" data-target="#contenedor" data-host="slug-o-id"></script>
 * Script clásico, sin dependencias. La dirección base se deduce de su propio src. */
(function (global) {
  'use strict';
  var doc = global.document;
  var script = doc.currentScript;
  if (!script) { return; }

  var src = script.getAttribute('src') || '';
  var base = '';
  try {
    var u = new URL(src, global.location.href);
    base = u.origin + u.pathname.replace(/\/assets\/js\/embed\.js$/, '').replace(/\/+$/, '');
  } catch (e) { return; }
  var origin = base.replace(/^(https?:\/\/[^/]+).*$/, '$1');

  var slug = (script.getAttribute('data-event') || '').trim();
  if (!/^[a-z0-9-]{1,80}$/.test(slug)) { if (global.console) { global.console.warn('Agenda Premium: falta data-event con el slug del evento.'); } return; }
  var mode = (script.getAttribute('data-mode') || 'inline').toLowerCase();
  if (['inline', 'popup', 'float'].indexOf(mode) < 0) { mode = 'inline'; }
  var label = script.getAttribute('data-label') || 'Reservar cita';
  var color = script.getAttribute('data-color') || '';
  if (!/^#?[0-9A-Fa-f]{6}$/.test(color)) { color = ''; }
  var host = (script.getAttribute('data-host') || '').trim();
  if (host && !/^[A-Za-z0-9-]{1,80}$/.test(host)) { host = ''; }
  var target = script.getAttribute('data-target') || '';
  var nonce = script.nonce || script.getAttribute('nonce') || '';

  function pageUrl() {
    var q = ['embed=1'];
    if (host) { q.push('host=' + encodeURIComponent(host)); }
    if (color) { q.push('color=' + encodeURIComponent(color.replace('#', ''))); }
    return base + '/e/' + slug + '?' + q.join('&');
  }

  function injectStyles() {
    if (doc.getElementById('ap-embed-css')) { return; }
    var st = doc.createElement('style');
    st.id = 'ap-embed-css';
    if (nonce) { st.setAttribute('nonce', nonce); }
    var gold = color ? '#' + color.replace('#', '') : '#C9A050';
    st.textContent =
      '.ap-btn{all:initial;box-sizing:border-box;display:inline-flex;align-items:center;gap:.5rem;font:700 15px/1.1 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;letter-spacing:.01em;padding:.85rem 1.4rem;border-radius:999px;cursor:pointer;color:#06080D;background:linear-gradient(135deg,#F0D9A0 0%,' + gold + ' 55%,#A67C37 100%);border:1px solid #F0D9A0;box-shadow:0 8px 24px rgba(0,0,0,.28),inset 0 1px 0 rgba(255,255,255,.5);transition:transform .2s,box-shadow .2s}' +
      '.ap-btn:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(0,0,0,.35),inset 0 1px 0 rgba(255,255,255,.5)}' +
      '.ap-btn:focus-visible{outline:3px solid #06080D;outline-offset:3px;box-shadow:0 0 0 6px #F0D9A0}' +
      '.ap-float{position:fixed;right:18px;bottom:18px;z-index:2147483000}' +
      '.ap-frame{display:block;width:100%;border:0;background:transparent;min-height:420px;color-scheme:normal}' +
      '.ap-overlay{position:fixed;inset:0;z-index:2147483600;display:flex;align-items:center;justify-content:center;padding:12px;background:rgba(3,4,8,.72)}' +
      '.ap-overlay[hidden]{display:none}' +
      '.ap-modal{position:relative;width:min(100%,980px);height:min(100%,780px);background:#06080D;border:1px solid rgba(201,160,80,.5);border-radius:16px;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.6)}' +
      '.ap-modal iframe{width:100%;height:100%;border:0;display:block;background:#06080D}' +
      '.ap-close{all:initial;box-sizing:border-box;position:absolute;top:8px;right:8px;z-index:2;width:40px;height:40px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font:700 20px/1 system-ui,sans-serif;color:#F0D9A0;background:rgba(6,8,13,.85);border:1px solid rgba(201,160,80,.6)}' +
      '.ap-close:focus-visible{outline:3px solid #F0D9A0;outline-offset:2px}' +
      '@media (prefers-reduced-motion:reduce){.ap-btn{transition:none}.ap-btn:hover{transform:none}}';
    (doc.head || doc.documentElement).appendChild(st);
  }

  function makeFrame(title) {
    var f = doc.createElement('iframe');
    f.className = 'ap-frame';
    f.title = title;
    f.src = pageUrl();
    f.setAttribute('loading', 'lazy');
    f.setAttribute('allow', 'clipboard-write');
    f.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    return f;
  }

  var frames = [];
  function onMessage(e) {
    if (e.origin !== origin || !e.data || typeof e.data !== 'object') { return; }
    var d = e.data;
    for (var i = 0; i < frames.length; i++) {
      if (frames[i].contentWindow !== e.source) { continue; }
      if (d.type === 'agenda-premium:height' && typeof d.height === 'number' && frames[i].getAttribute('data-auto') === '1') {
        frames[i].style.height = Math.max(420, Math.min(5000, Math.ceil(d.height))) + 'px';
      } else if (d.type === 'agenda-premium:redirect' && typeof d.url === 'string' && /^https?:\/\//i.test(d.url)) {
        global.location.href = d.url;
      } else if (d.type === 'agenda-premium:booked') {
        try { frames[i].dispatchEvent(new CustomEvent('agenda-premium:booked', { bubbles: true, detail: d })); } catch (x) { /* navegador antiguo */ }
      }
    }
  }
  global.addEventListener('message', onMessage);

  function inline() {
    var holder = target ? doc.querySelector(target) : null;
    if (!holder) {
      holder = doc.createElement('div');
      script.parentNode.insertBefore(holder, script.nextSibling);
    }
    var f = makeFrame('Reserva de cita en línea');
    f.setAttribute('data-auto', '1');
    holder.appendChild(f);
    frames.push(f);
  }

  function button(cls) {
    var b = doc.createElement('button');
    b.type = 'button';
    b.className = 'ap-btn ' + (cls || '');
    b.textContent = label;
    b.setAttribute('aria-haspopup', 'dialog');
    return b;
  }

  function popup(btn) {
    var overlay = null, frame = null, opener = null;
    function close() {
      if (!overlay) { return; }
      overlay.hidden = true;
      doc.removeEventListener('keydown', onKey, true);
      doc.documentElement.style.overflow = '';
      if (opener && opener.focus) { opener.focus(); }
    }
    function onKey(e) {
      if (e.key === 'Escape') { e.preventDefault(); close(); return; }
      if (e.key === 'Tab' && overlay) {
        // el foco se queda en el botón de cerrar o dentro del iframe
        var closeBtn = overlay.querySelector('.ap-close');
        if (doc.activeElement !== closeBtn && doc.activeElement !== frame) { e.preventDefault(); closeBtn.focus(); }
      }
    }
    function open() {
      opener = doc.activeElement;
      if (!overlay) {
        overlay = doc.createElement('div');
        overlay.className = 'ap-overlay';
        var modal = doc.createElement('div');
        modal.className = 'ap-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', 'Reservar cita');
        var x = doc.createElement('button');
        x.type = 'button'; x.className = 'ap-close'; x.setAttribute('aria-label', 'Cerrar'); x.textContent = '×';
        x.addEventListener('click', close);
        frame = makeFrame('Reserva de cita en línea');
        frame.removeAttribute('loading');
        frame.style.minHeight = '0';
        frames.push(frame);
        modal.appendChild(x); modal.appendChild(frame); overlay.appendChild(modal);
        overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) { close(); } });
        doc.body.appendChild(overlay);
      }
      overlay.hidden = false;
      doc.documentElement.style.overflow = 'hidden';
      doc.addEventListener('keydown', onKey, true);
      overlay.querySelector('.ap-close').focus();
    }
    btn.addEventListener('click', open);
  }

  function init() {
    injectStyles();
    if (mode === 'inline') { inline(); return; }
    var b;
    if (mode === 'float') {
      b = button('ap-float');
      doc.body.appendChild(b);
    } else {
      b = button('');
      script.parentNode.insertBefore(b, script.nextSibling);
    }
    popup(b);
  }

  if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', init); } else { init(); }
})(window);
