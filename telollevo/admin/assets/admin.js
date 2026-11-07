/* Panel de administración — Te Lo Llevo (sin dependencias) */
(function () {
  'use strict';
  var d = document;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var meta = function (n) { var m = $('meta[name="' + n + '"]'); return m ? m.content : ''; };
  var BASE = (meta('base') || '/').replace(/\/?$/, '/');
  var CSRF = meta('csrf');
  var el = function (tag, attrs, kids) {
    var n = d.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) { if (k === 'text') n.textContent = attrs[k]; else if (k === 'class') n.className = attrs[k]; else n.setAttribute(k, attrs[k]); });
    (kids || []).forEach(function (c) { n.appendChild(c); });
    return n;
  };

  /* ---------- Menú lateral móvil ---------- */
  var side = $('#side'), scrim = $('#scrim'), mbtn = $('#menuBtn');
  function toggleSide(open) {
    if (!side) return;
    side.classList.toggle('open', open); scrim.classList.toggle('open', open);
    if (mbtn) mbtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  if (mbtn) mbtn.addEventListener('click', function () { toggleSide(!side.classList.contains('open')); });
  if (scrim) scrim.addEventListener('click', function () { toggleSide(false); });
  d.addEventListener('keydown', function (e) { if (e.key === 'Escape') { toggleSide(false); closeModals(); } });

  /* ---------- Avisos, contraseña, confirmaciones ---------- */
  $$('.toast-x').forEach(function (b) { b.addEventListener('click', function () { b.parentNode.remove(); }); });
  setTimeout(function () { $$('.toast.ok').forEach(function (t) { t.style.transition = 'opacity .5s'; t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 500); }); }, 6000);
  $$('[data-toggle-pass]').forEach(function (b) {
    b.addEventListener('click', function () { var i = b.parentNode.querySelector('input'); i.type = i.type === 'password' ? 'text' : 'password'; });
  });
  d.addEventListener('click', function (e) {
    var b = e.target.closest('[data-confirm]');
    if (b && !window.confirm(b.getAttribute('data-confirm'))) { e.preventDefault(); e.stopPropagation(); }
  }, true);

  /* ---------- Aviso de cambios sin guardar ---------- */
  var guard = $('[data-dirty-guard]'), dirty = false, submitting = false;
  if (guard) {
    var msg = $('.dirty-msg');
    var mark = function () { dirty = true; if (msg) msg.hidden = false; };
    guard.addEventListener('input', mark); guard.addEventListener('change', mark);
    guard.addEventListener('submit', function () { submitting = true; });
    window.addEventListener('beforeunload', function (e) { if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; } });
    guard.addEventListener('click', function (e) { if (e.target.closest('[data-add],[data-del],[data-up],[data-down]')) mark(); });
    guard.markDirty = mark;
  }
  function markDirty() { if (guard && guard.markDirty) guard.markDirty(); }

  /* ---------- Colores ---------- */
  function setThemeVars() {
    var map = { blue: '--c-blue', navy: '--c-navy', yellow: '--c-yellow' };
    $$('[data-color-hex]').forEach(function (i) {
      var m = /\[(blue|navy|yellow)\]$/.exec(i.name);
      if (m && /^#[0-9a-f]{6}$/i.test(i.value)) d.documentElement.style.setProperty(map[m[1]], i.value);
    });
  }
  $$('[data-color-pick]').forEach(function (p) {
    var h = p.parentNode.querySelector('[data-color-hex]');
    p.addEventListener('input', function () { h.value = p.value.toUpperCase(); setThemeVars(); markDirty(); });
    h.addEventListener('input', function () { if (/^#[0-9a-f]{6}$/i.test(h.value)) { p.value = h.value; setThemeVars(); } });
  });
  setThemeVars();
  var rt = $('[data-reset-theme]');
  if (rt) rt.addEventListener('click', function () {
    ['blue', 'navy', 'yellow'].forEach(function (k) {
      var h = $('[data-color-hex][name$="[' + k + ']"]'); if (!h) return;
      h.value = rt.getAttribute('data-' + k); h.parentNode.querySelector('[data-color-pick]').value = h.value;
    });
    setThemeVars(); markDirty();
  });

  /* ---------- Listas editables ---------- */
  function refreshList(list) {
    var items = $$('[data-item]', list.querySelector('.list-items'));
    items.forEach(function (it, i) {
      $('.li-n', it).textContent = i + 1;
      var up = $('[data-up]', it), dn = $('[data-down]', it);
      if (up) up.disabled = i === 0; if (dn) dn.disabled = i === items.length - 1;
    });
    var cnt = list.parentNode.querySelector('[data-count]');
    if (cnt) cnt.textContent = items.length + ' elemento(s)';
    var add = $('[data-add]', list), max = parseInt(list.getAttribute('data-max'), 10) || 30;
    if (add) add.hidden = items.length >= max;
  }
  function liveTitle(item) {
    var key = item.getAttribute('data-il'), inp = $('[name$="[' + key + ']"]', item), t = $('.li-title', item);
    if (inp && t) t.textContent = inp.value;
  }
  var uid = 0;
  $$('[data-list]').forEach(function (list) {
    refreshList(list);
    list.addEventListener('input', function (e) { var it = e.target.closest('[data-item]'); if (it) liveTitle(it); });
    list.addEventListener('click', function (e) {
      var items = list.querySelector('.list-items');
      var it = e.target.closest('[data-item]');
      if (e.target.closest('[data-add]')) {
        var tpl = $('[data-tpl]', list), html = tpl.innerHTML.replace(/__i__/g, 'n' + (++uid) + Date.now().toString(36));
        var wrap = d.createElement('div'); wrap.innerHTML = html;
        var node = wrap.firstElementChild; items.appendChild(node); refreshList(list);
        node.querySelector('.li-toggle').setAttribute('aria-expanded', 'true');
        var f = node.querySelector('input[type=text],textarea'); if (f) f.focus({ preventScroll: true });
        node.scrollIntoView({ behavior: 'smooth', block: 'center' }); return;
      }
      if (!it) return;
      if (e.target.closest('[data-del]')) { if (window.confirm('¿Eliminar este elemento?')) { it.remove(); refreshList(list); } return; }
      if (e.target.closest('[data-up]') && it.previousElementSibling) { items.insertBefore(it, it.previousElementSibling); refreshList(list); return; }
      if (e.target.closest('[data-down]') && it.nextElementSibling) { items.insertBefore(it.nextElementSibling, it); refreshList(list); return; }
      var tg = e.target.closest('.li-toggle');
      if (tg) { var o = it.classList.toggle('open'); tg.setAttribute('aria-expanded', o ? 'true' : 'false'); }
    });
  });

  /* ---------- Orden de secciones ---------- */
  $$('[data-sort]').forEach(function (ul) {
    var refresh = function () {
      var rows = $$('[data-row]', ul);
      rows.forEach(function (r, i) { $('[data-up]', r).disabled = i === 0; $('[data-down]', r).disabled = i === rows.length - 1; });
    };
    refresh();
    ul.addEventListener('click', function (e) {
      var r = e.target.closest('[data-row]'); if (!r) return;
      if (e.target.closest('[data-up]') && r.previousElementSibling) ul.insertBefore(r, r.previousElementSibling);
      else if (e.target.closest('[data-down]') && r.nextElementSibling) ul.insertBefore(r.nextElementSibling, r);
      else return;
      refresh(); markDirty();
    });
  });

  /* ---------- Ventanas modales ---------- */
  var modals = [];
  function closeModals() { modals.forEach(function (m) { m.classList.remove('open'); }); }
  function makeModal(title, toolsHtml) {
    var m = el('div', { 'class': 'modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': title });
    var bg = el('div', { 'class': 'modal-bg' });
    var box = el('div', { 'class': 'modal-box' });
    var head = el('div', { 'class': 'modal-head' }, [el('h3', { text: title })]);
    var x = el('button', { 'class': 'ib', type: 'button', 'aria-label': 'Cerrar', text: '×' });
    head.appendChild(x);
    var tools = el('div', { 'class': 'modal-tools' }), body = el('div', { 'class': 'modal-body' });
    box.appendChild(head); box.appendChild(tools); box.appendChild(body); m.appendChild(bg); m.appendChild(box);
    d.body.appendChild(m); modals.push(m);
    bg.addEventListener('click', closeModals); x.addEventListener('click', closeModals);
    return { root: m, tools: tools, body: body, open: function () { m.classList.add('open'); }, close: function () { m.classList.remove('open'); } };
  }

  /* Selector de iconos */
  var iconModal = null, iconData = null, iconTarget = null;
  var alias = { envio: 'package delivery', envios: 'package delivery', paquete: 'package box', caja: 'package box cube', moto: 'moped motorcycle', motocicleta: 'motorcycle moped', telefono: 'phone call', llamar: 'phone call', correo: 'envelope mail', mensaje: 'chat message', reloj: 'clock timer', tiempo: 'clock timer', rapido: 'lightning speed', rayo: 'lightning', escudo: 'shield', seguro: 'shield check', estrella: 'star', corazon: 'heart', ubicacion: 'map-pin location', mapa: 'map', casa: 'house home', tienda: 'storefront shop', negocio: 'storefront buildings', edificio: 'buildings building', dinero: 'money coins cash', precio: 'tag receipt currency', pago: 'credit-card wallet money', calendario: 'calendar', documento: 'file-text document', usuario: 'user', persona: 'user people', personas: 'users people', camion: 'truck', carro: 'car', auto: 'car', comida: 'fork-knife pizza hamburger', regalo: 'gift', check: 'check seal', ok: 'check', whatsapp: 'whatsapp-logo', facebook: 'facebook-logo', instagram: 'instagram-logo', tiktok: 'tiktok-logo', ayuda: 'headset question lifebuoy', soporte: 'headset', compras: 'shopping-bag cart basket', bolsa: 'bag shopping', cohete: 'rocket', etiqueta: 'tag', llave: 'key', candado: 'lock', grafica: 'chart', grafico: 'chart' };
  function openIconPicker(target) {
    iconTarget = target;
    if (!iconModal) {
      iconModal = makeModal('Elegir un icono');
      var q = el('input', { type: 'search', placeholder: 'Buscar: envío, teléfono, reloj, tienda…', 'aria-label': 'Buscar icono' });
      iconModal.tools.appendChild(q);
      var grid = el('div', { 'class': 'icon-grid' }); iconModal.body.appendChild(grid);
      iconModal.grid = grid;
      var render = function () {
        var term = q.value.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        var words = term ? (term + ' ' + (alias[term] || '')).split(/\s+/) : [];
        grid.innerHTML = '';
        var n = 0;
        iconData.forEach(function (ic) {
          if (words.length) { var hay = ic.n + ' ' + ic.t; if (!words.some(function (w) { return w && hay.indexOf(w) !== -1; })) return; }
          if (++n > 400) return;
          var b = el('button', { type: 'button', 'class': 'icon-cell', title: ic.n });
          var im = el('img', { src: BASE + 'assets/icons/duotone/' + ic.n + '.svg', alt: '', loading: 'lazy', width: 30, height: 30 });
          b.appendChild(im); b.appendChild(el('span', { text: ic.n }));
          b.addEventListener('click', function () { chooseIcon(ic.n); });
          grid.appendChild(b);
        });
        if (!n) grid.appendChild(el('p', { 'class': 'muted', text: 'No se encontraron iconos con esa búsqueda.' }));
      };
      iconModal.render = render;
      q.addEventListener('input', render);
      iconModal.q = q;
    }
    var go = function () { iconModal.q.value = ''; iconModal.render(); iconModal.open(); iconModal.q.focus(); };
    if (iconData) go();
    else fetch(BASE + 'admin/icons.php', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { iconData = j; go(); })
      .catch(function () { window.alert('No se pudo cargar la lista de iconos.'); });
  }
  function chooseIcon(name) {
    if (!iconTarget) return;
    $('input', iconTarget).value = name;
    $('.icon-name', iconTarget).textContent = name;
    var prev = $('.icon-prev', iconTarget); prev.innerHTML = '';
    prev.appendChild(el('img', { src: BASE + 'assets/icons/duotone/' + name + '.svg', alt: '', width: 30, height: 30 }));
    iconModal.close(); markDirty();
  }

  /* Selector / subida de imágenes */
  var imgModal = null, imgTarget = null;
  function postFiles(files, cb) {
    var fd = new FormData();
    fd.append('action', 'upload'); fd.append('json', '1'); fd.append('_csrf', CSRF);
    Array.prototype.forEach.call(files, function (f) { fd.append('files[]', f); });
    fetch(BASE + 'admin/media.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': CSRF } })
      .then(function (r) { return r.json(); }).then(cb).catch(function () { cb({ ok: false, message: 'Error de conexión al subir.' }); });
  }
  function openImagePicker(target) {
    imgTarget = target;
    if (!imgModal) {
      imgModal = makeModal('Elegir o subir una imagen');
      var up = el('label', { 'class': 'btn btn-primary' }, [el('span', { text: 'Subir nueva imagen' })]);
      var file = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', hidden: '' });
      up.appendChild(file); imgModal.tools.appendChild(up);
      imgModal.status = el('span', { 'class': 'muted' }); imgModal.tools.appendChild(imgModal.status);
      imgModal.body.appendChild(el('h4', { text: 'Tus imágenes' }));
      imgModal.mine = el('div', { 'class': 'pick-grid' }); imgModal.body.appendChild(imgModal.mine);
      imgModal.body.appendChild(el('h4', { text: 'Imágenes originales del sitio' }));
      imgModal.built = el('div', { 'class': 'pick-grid' }); imgModal.body.appendChild(imgModal.built);
      file.addEventListener('change', function () {
        if (!file.files.length) return;
        imgModal.status.textContent = 'Subiendo…';
        postFiles(file.files, function (r) {
          file.value = '';
          if (r.ok) { imgModal.status.textContent = ''; choose(r.path, r.url); }
          else imgModal.status.textContent = r.message || 'No se pudo subir.';
        });
      });
      imgModal.load = function () {
        fetch(BASE + 'admin/media.php?json=1', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
          var fill = function (box, list) {
            box.innerHTML = '';
            if (!list.length) box.appendChild(el('p', { 'class': 'muted', text: 'Todavía no hay imágenes.' }));
            list.forEach(function (it) {
              var b = el('button', { type: 'button', 'class': 'pick' });
              b.appendChild(el('img', { src: it.url, alt: '', loading: 'lazy' })); b.appendChild(el('span', { text: it.name }));
              b.addEventListener('click', function () { choose(it.path, it.url); });
              box.appendChild(b);
            });
          };
          fill(imgModal.mine, j.uploads || []); fill(imgModal.built, j.builtin || []);
        });
      };
    }
    imgModal.status.textContent = ''; imgModal.load(); imgModal.open();
  }
  function choose(path, url) {
    if (!imgTarget) return;
    $('input[type=hidden]', imgTarget).value = path;
    var prev = $('.img-prev', imgTarget); prev.innerHTML = ''; prev.appendChild(el('img', { src: url, alt: '' }));
    imgModal.close(); markDirty();
  }

  d.addEventListener('click', function (e) {
    var pi = e.target.closest('[data-pick-icon]'); if (pi) { openIconPicker(pi.closest('[data-icon]')); return; }
    var pm = e.target.closest('[data-pick-image]'); if (pm) { openImagePicker(pm.closest('[data-image]')); return; }
    var ci = e.target.closest('[data-clear-image]');
    if (ci) { var w = ci.closest('[data-image]'); $('input[type=hidden]', w).value = ''; var p = $('.img-prev', w); p.innerHTML = ''; p.appendChild(el('span', { text: 'Sin imagen' })); markDirty(); }
  });

  /* ---------- Zona de arrastre (página Imágenes) ---------- */
  $$('[data-dropzone]').forEach(function (dz) {
    var input = $('input[type=file]', dz), status = $('.dz-status', dz);
    var send = function (files) {
      if (!files || !files.length) return;
      status.hidden = false; status.textContent = 'Subiendo ' + files.length + ' imagen(es)…';
      postFiles(files, function (r) {
        if (r.ok) { status.textContent = 'Listo. Actualizando…'; window.location.reload(); }
        else { status.textContent = (r.errors && r.errors.length ? r.errors.join(' · ') : r.message) || 'No se pudo subir.'; }
      });
    };
    input.addEventListener('change', function () { send(input.files); });
    ['dragenter', 'dragover'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('drag'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('drag'); }); });
    dz.addEventListener('drop', function (e) { send(e.dataTransfer.files); });
  });
})();
