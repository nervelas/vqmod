/* Servicom — Editor en el sitio (barra + modo edición). Sin librerías. Textos en español. */
(function () {
  'use strict';
  var C = window.SC_ED;
  if (!C) { return; }
  var d = document, w = window;
  var editing = !!C.editing;
  var BAR_H = 56;
  var state = null, statePromise = null, icons = null, iconsPromise = null;
  var cur = null;            // edición de texto activa
  var peek = false;          // «Ver sin edición»
  var undoCount = 0;
  var H = { t: null, img: null, li: null, item: null }; // elemento bajo el puntero
  var layer, hl, hlLbl, chipImg, chipLi, chipItem, bar, pill, statusEl, undoBtn, toastEl, tourEl;
  var openUi = null;         // cajón/diálogo abierto
  var pending = 0, queue = Promise.resolve();
  var sticky = { li: null, item: null };
  var supportsPlain = (function () { try { var t = d.createElement('div'); t.contentEditable = 'plaintext-only'; return t.contentEditable === 'plaintext-only'; } catch (e) { return false; } })();

  /* ------------------------------------------------------------ utilidades */
  function q(s, r) { return (r || d).querySelector(s); }
  function qa(s, r) { return Array.prototype.slice.call((r || d).querySelectorAll(s)); }
  function h(tag, props, kids) {
    var e = d.createElement(tag), k, v;
    if (props) {
      for (k in props) {
        if (!Object.prototype.hasOwnProperty.call(props, k)) { continue; }
        v = props[k];
        if (v === false || v === null || v === undefined) { continue; }
        if (k === 'class') { e.className = v; }
        else if (k === 'text') { e.textContent = v; }
        else if (k === 'html') { e.innerHTML = v; }
        else if (k.slice(0, 2) === 'on' && typeof v === 'function') { e.addEventListener(k.slice(2), v); }
        else { e.setAttribute(k, v === true ? '' : v); }
      }
    }
    (kids || []).forEach(function (c) { if (c) { e.appendChild(typeof c === 'string' ? d.createTextNode(c) : c); } });
    return e;
  }
  var P = {
    pencil: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/>',
    undo: '<path d="M3 7v6h6"/><path d="M21 17a9 9 0 00-15-6.7L3 13"/>',
    brief: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/><path d="M3 13h18"/>',
    palette: '<circle cx="13.5" cy="6.5" r="1"/><circle cx="17.5" cy="10.5" r="1"/><circle cx="8.5" cy="7.5" r="1"/><circle cx="6.5" cy="12.5" r="1"/><path d="M12 22a10 10 0 110-20c5.5 0 10 3.6 10 8 0 3-2.5 4-4.5 4H15a2 2 0 00-1.4 3.4c.5.5.4 1.2.1 1.8-.4.6-1 .8-1.7.8z"/>',
    layers: '<path d="M12 2l10 5-10 5L2 7l10-5z"/><path d="M2 12l10 5 10-5"/><path d="M2 17l10 5 10-5"/>',
    eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
    eyeoff: '<path d="M17.9 17.9A10.9 10.9 0 0112 20C5 20 1 12 1 12a18.5 18.5 0 015.1-5.9M9.9 4.2A10.9 10.9 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.2 3.2M14.1 14.1a3 3 0 11-4.2-4.2"/><path d="M1 1l22 22"/>',
    up: '<path d="M12 19V5M5 12l7-7 7 7"/>',
    down: '<path d="M12 5v14M19 12l-7 7-7-7"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    x: '<path d="M18 6L6 18M6 6l12 12"/>',
    copy: '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>',
    trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/>',
    image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
    panel: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
    help: '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 015.8 1c0 2-3 3-3 3M12 17h.01"/>',
    check: '<path d="M20 6L9 17l-5-5"/>',
    link: '<path d="M10 13a5 5 0 007.5.5l3-3a5 5 0 00-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 00-7.5-.5l-3 3a5 5 0 007 7l1.7-1.7"/>',
    dots: '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
    star: '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8-6.2-3.3-6.2 3.3L8 14.2 3 9.3l6.9-1L12 2z"/>',
    spin: '<path d="M21 12a9 9 0 11-6.2-8.6"/>'
  };
  function ic(name, size) {
    return '<svg class="sc-ed-i" width="' + (size || 18) + '" height="' + (size || 18) + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + (P[name] || '') + '</svg>';
  }
  function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  function lsGet(k) { try { return w.localStorage.getItem(k); } catch (e) { return null; } }
  function lsSet(k, v) { try { w.localStorage.setItem(k, v); } catch (e) { /* sin almacenamiento */ } }
  function ssGet(k) { try { return w.sessionStorage.getItem(k); } catch (e) { return null; } }
  function ssSet(k, v) { try { w.sessionStorage.setItem(k, v); } catch (e) { /* nada */ } }
  function ssDel(k) { try { w.sessionStorage.removeItem(k); } catch (e) { /* nada */ } }
  function isUi(n) { return !!(n && n.closest && n.closest('.sc-ed-ui, .media-modal, .media-modal-backdrop, .supports-drag-drop, #wpadminbar')); }
  function siteUrl(edit) {
    var u = new URL(w.location.href);
    u.searchParams.delete('sc_focus');
    if (edit) { u.searchParams.set('sc_edit', '1'); } else { u.searchParams.delete('sc_edit'); }
    return u.toString();
  }
  function reloadKeep(focus) {
    ssSet('sc_ed_ret', JSON.stringify({ y: w.pageYOffset || 0, p: w.location.pathname }));
    var u = new URL(w.location.href);
    u.searchParams.delete('sc_focus');
    u.searchParams.set('sc_edit', '1');
    if (focus) { u.searchParams.set('sc_focus', focus); ssDel('sc_ed_ret'); }
    if (u.toString() === w.location.href) { w.location.reload(); } else { w.location.href = u.toString(); }
  }
  function btn(cls, iconName, label, props) {
    var p = props || {};
    p['class'] = cls;
    p.type = 'button';
    return h('button', p, [h('span', { class: 'sc-ed-ico', html: ic(iconName) }), label ? h('span', { class: 'sc-ed-lbl', text: label }) : null]);
  }

  /* ------------------------------------------------------------ API y guardado */
  var lastOk = { kind: 'ok', msg: 'Guardado ✓' };
  function setStatus(kind, msg, title) {
    if (kind === 'ok') { lastOk = { kind: kind, msg: msg }; }
    if (!statusEl) { return; }
    statusEl.className = 'sc-ed-status is-' + kind;
    var t = q('.sc-ed-status__t', statusEl);
    if (t) { t.textContent = msg; }
    statusEl.setAttribute('title', title || msg);
  }
  function toast(msg, kind) {
    if (!toastEl) { toastEl = h('div', { class: 'sc-ed-toast sc-ed-ui', role: 'status', 'aria-live': 'polite' }); d.body.appendChild(toastEl); }
    toastEl.className = 'sc-ed-toast sc-ed-ui is-show' + (kind === 'err' ? ' is-err' : '');
    toastEl.textContent = msg;
    toastEl.setAttribute('role', kind === 'err' ? 'alert' : 'status');
    clearTimeout(toast.t);
    toast.t = setTimeout(function () { toastEl.classList.remove('is-show'); }, kind === 'err' ? 6500 : 3200);
  }
  function refreshUndo() { if (undoBtn) { undoBtn.disabled = !(undoCount > 0); } }
  function request(method, path, body) {
    return fetch(C.rest + path, {
      method: method, credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) {
      var nn = r.headers.get('X-WP-Nonce');
      if (nn) { C.nonce = nn; }
      return r.json().catch(function () { return {}; }).then(function (j) { return { status: r.status, j: j }; });
    });
  }
  function send(ops, opt) {
    opt = opt || {};
    pending++;
    setStatus('saving', 'Guardando…');
    var p = queue.then(function () { return request('POST', 'edit', { ops: ops }); }).then(function (x) {
      pending--;
      if (x.status >= 200 && x.status < 300 && x.j && x.j.ok) {
        if (typeof x.j.can_undo === 'number') { undoCount = x.j.can_undo; refreshUndo(); }
        if (pending === 0) { setStatus('ok', 'Guardado ✓'); if (statusEl) { statusEl.setAttribute('title', 'Guardado a las ' + (x.j.saved_label || '')); } }
        return x.j;
      }
      var msg = (x.j && (x.j.msg || x.j.message)) || 'No se pudo guardar. Intente de nuevo.';
      if (x.status === 401 || x.status === 403) { msg = 'Su sesión caducó. Recargue la página e inicie sesión de nuevo.'; }
      if (opt.quiet) { setStatus(lastOk.kind, lastOk.msg); } else { setStatus('err', 'No se guardó', msg); toast(msg, 'err'); }
      var e = new Error(msg); e.handled = true; throw e;
    }, function (err) {
      pending--;
      var msg = 'Sin conexión. Revise su internet e intente de nuevo.';
      setStatus('err', 'Sin conexión', msg);
      toast(msg, 'err');
      err.handled = true; err.message = msg; throw err;
    });
    queue = p.catch(function () { /* la cola sigue */ });
    return p;
  }
  function loadState(force) {
    if (statePromise && !force) { return statePromise; }
    statePromise = request('GET', 'state').then(function (x) {
      if (x.status >= 200 && x.status < 300 && x.j && x.j.ok) { state = x.j; undoCount = x.j.can_undo || 0; refreshUndo(); return state; }
      statePromise = null; throw new Error('state');
    }, function (e) { statePromise = null; throw e; });
    return statePromise;
  }
  function loadIcons() {
    if (icons) { return Promise.resolve(icons); }
    if (iconsPromise) { return iconsPromise; }
    iconsPromise = request('GET', 'icons').then(function (x) {
      if (x.status >= 200 && x.status < 300 && Array.isArray(x.j)) { icons = x.j; return icons; }
      iconsPromise = null; throw new Error('icons');
    }, function (e) { iconsPromise = null; throw e; });
    return iconsPromise;
  }

  /* ------------------------------------------------------------ diálogos y cajones */
  function closeUi() {
    if (!openUi) { return; }
    var u = openUi; openUi = null;
    u.root.classList.remove('is-open');
    setTimeout(function () { if (u.root.parentNode) { u.root.parentNode.removeChild(u.root); } }, 220);
    d.removeEventListener('keydown', u.keys, true);
    if (u.onClose) { try { u.onClose(); } catch (e) { /* nada */ } }
    if (u.back && u.back.focus) { try { u.back.focus(); } catch (e2) { /* nada */ } }
  }
  function openPanel(kind, title, body, opts) {
    closeUi();
    opts = opts || {};
    var back = d.activeElement;
    var closeB = h('button', { type: 'button', class: 'sc-ed-x', 'aria-label': 'Cerrar', title: 'Cerrar', html: ic('x', 20), onclick: closeUi });
    var panel = h('div', { class: 'sc-ed-panel sc-ed-panel--' + kind + (opts.wide ? ' is-wide' : ''), role: 'dialog', 'aria-modal': 'true', 'aria-label': title, tabindex: '-1' }, [
      h('header', { class: 'sc-ed-panel__h' }, [h('h2', { text: title }), closeB]),
      h('div', { class: 'sc-ed-panel__b' }, [body])
    ]);
    var root = h('div', { class: 'sc-ed-ui sc-ed-overlay sc-ed-overlay--' + kind + (opts.clear ? ' is-clear' : '') }, [
      h('div', { class: 'sc-ed-scrim', onclick: closeUi }), panel
    ]);
    var keys = function (e) {
      if (e.key === 'Escape') { e.stopPropagation(); closeUi(); return; }
      if (e.key === 'Tab') {
        var f = qa('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]', panel).filter(function (x) { return x.offsetParent !== null; });
        if (!f.length) { return; }
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && (d.activeElement === first || d.activeElement === panel)) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    };
    d.addEventListener('keydown', keys, true);
    d.body.appendChild(root);
    openUi = { root: root, keys: keys, onClose: opts.onClose, back: back };
    requestAnimationFrame(function () {
      root.classList.add('is-open');
      var f = opts.focus ? q(opts.focus, panel) : q('input:not([type=hidden]), select, textarea', panel);
      (f || panel).focus();
    });
    clearHover();
    return { root: root, panel: panel, close: closeUi };
  }
  function confirmBox(title, text, okLabel, danger) {
    return new Promise(function (resolve) {
      var done = false;
      var ok = h('button', { type: 'button', class: 'sc-ed-b ' + (danger ? 'sc-ed-b--danger' : 'sc-ed-b--pri'), text: okLabel || 'Aceptar' });
      var no = h('button', { type: 'button', class: 'sc-ed-b', text: 'Cancelar' });
      var body = h('div', { class: 'sc-ed-confirm' }, [h('p', { text: text }), h('div', { class: 'sc-ed-actions' }, [no, ok])]);
      var p = openPanel('dialog', title, body, { onClose: function () { if (!done) { done = true; resolve(false); } }, focus: 'button.sc-ed-b:first-child' });
      ok.addEventListener('click', function () { done = true; resolve(true); p.close(); });
      no.addEventListener('click', function () { p.close(); });
    });
  }

  /* ------------------------------------------------------------ lectura y escritura de textos */
  function nodeText(root) {
    var s = '';
    (function walk(n) {
      for (var c = n.firstChild; c; c = c.nextSibling) {
        if (c.nodeType === 3) { s += c.nodeValue; }
        else if (c.nodeType === 1) {
          if (c.nodeName === 'BR') { s += '\n'; }
          else if (c.hasAttribute && c.hasAttribute('data-sc-ui')) { continue; }
          else {
            var blk = /^(DIV|P|LI)$/.test(c.nodeName);
            if (blk && s && s.slice(-1) !== '\n') { s += '\n'; }
            walk(c);
          }
        }
      }
    })(root);
    return s.replace(/​/g, '').replace(/ /g, ' ').replace(/\r/g, '');
  }
  function setNodeText(n, val, multi) {
    while (n.firstChild) { n.removeChild(n.firstChild); }
    if (!multi) { n.textContent = val; return; }
    val.split('\n').forEach(function (line, i) {
      if (i) { n.appendChild(d.createElement('br')); }
      n.appendChild(d.createTextNode(line));
    });
  }
  function byPath(path) { return qa('[data-sc]').filter(function (e) { return e.getAttribute('data-sc') === path; }); }

  /* ------------------------------------------------------------ edición de texto en el sitio */
  function caretEnd(el) {
    try {
      var r = d.createRange(); r.selectNodeContents(el); r.collapse(false);
      var s = w.getSelection(); s.removeAllRanges(); s.addRange(r);
    } catch (e) { /* nada */ }
  }
  function startText(el) {
    if (cur && cur.node === el) { return; }
    if (cur) { endText(true); }
    clearHover();
    var multi = el.hasAttribute('data-sc-m');
    cur = { node: el, path: el.getAttribute('data-sc'), orig: el.innerHTML, text: nodeText(el), multi: multi };
    cur.hint = h('div', { class: 'sc-ed-hint sc-ed-ui', 'data-sc-ui': '', text: multi ? 'Ctrl+Enter guarda · Esc cancela' : 'Enter guarda · Esc cancela' });
    d.body.appendChild(cur.hint);
    el.textContent = cur.text;
    el.classList.add('sc-ed-editing-node');
    el.setAttribute('contenteditable', supportsPlain ? 'plaintext-only' : 'true');
    el.setAttribute('spellcheck', 'true');
    el.setAttribute('role', 'textbox');
    if (multi) { el.setAttribute('aria-multiline', 'true'); }
    cur.k = function (e) {
      e.stopPropagation();
      if (e.key === 'Escape') { e.preventDefault(); endText(false); return; }
      if (e.key === 'Enter' && (!multi || e.ctrlKey || e.metaKey)) { e.preventDefault(); endText(true); }
    };
    cur.stop = function (e) { e.stopPropagation(); if (e.type === 'keyup' && (e.key === ' ' || e.key === 'Enter')) { e.preventDefault(); } };
    cur.paste = function (e) {
      e.preventDefault();
      var t = ((e.clipboardData || w.clipboardData).getData('text/plain') || '').replace(/\r/g, '');
      if (!multi) { t = t.replace(/\s*\n+\s*/g, ' '); }
      d.execCommand('insertText', false, t);
    };
    cur.bi = function (e) { if (/^format/.test(e.inputType || '')) { e.preventDefault(); } };
    cur.drop = function (e) { e.preventDefault(); };
    cur.blur = function () { setTimeout(function () { if (cur && cur.node === el) { endText(true); } }, 0); };
    el.addEventListener('keydown', cur.k);
    el.addEventListener('keyup', cur.stop);
    el.addEventListener('keypress', cur.stop);
    el.addEventListener('paste', cur.paste);
    el.addEventListener('beforeinput', cur.bi);
    el.addEventListener('drop', cur.drop);
    el.addEventListener('blur', cur.blur);
    el.focus();
    caretEnd(el);
    placeHint();
  }
  function placeHint() {
    if (!cur) { return; }
    var r = cur.node.getBoundingClientRect();
    var top = r.bottom + 6;
    if (top > w.innerHeight - 40) { top = Math.max(BAR_H + 6, r.top - 34); }
    cur.hint.style.left = Math.max(8, Math.min(w.innerWidth - 230, r.left)) + 'px';
    cur.hint.style.top = top + 'px';
  }
  function endText(save) {
    if (!cur) { return; }
    var c = cur; cur = null;
    var el = c.node;
    el.removeEventListener('keydown', c.k); el.removeEventListener('keyup', c.stop); el.removeEventListener('keypress', c.stop);
    el.removeEventListener('paste', c.paste); el.removeEventListener('beforeinput', c.bi); el.removeEventListener('drop', c.drop); el.removeEventListener('blur', c.blur);
    el.removeAttribute('contenteditable'); el.removeAttribute('spellcheck'); el.removeAttribute('role'); el.removeAttribute('aria-multiline');
    el.classList.remove('sc-ed-editing-node');
    if (c.hint && c.hint.parentNode) { c.hint.parentNode.removeChild(c.hint); }
    var val = nodeText(el).replace(/^\s+|\s+$/g, '');
    if (!c.multi) { val = val.replace(/\s*\n+\s*/g, ' '); }
    if (!save || val === c.text.replace(/^\s+|\s+$/g, '')) { el.innerHTML = c.orig; return; }
    setNodeText(el, val, c.multi);
    byPath(c.path).forEach(function (o) { if (o !== el) { setNodeText(o, val, o.hasAttribute('data-sc-m')); } });
    send([{ op: 'text', path: c.path, value: val }]).then(function (r) {
      var res = r.results && r.results[0];
      if (res && typeof res.value === 'string' && res.value !== val) {
        byPath(c.path).forEach(function (o) { setNodeText(o, res.value, o.hasAttribute('data-sc-m')); });
      }
    }, function () {
      byPath(c.path).forEach(function (o) { o.innerHTML = c.orig; });
    });
  }

  /* ------------------------------------------------------------ imágenes */
  function chooseImage(title, cb) {
    if (!w.wp || !w.wp.media) { toast('El selector de imágenes no está disponible. Recargue la página.', 'err'); return; }
    var f = w.wp.media({ title: title || 'Elegir imagen', button: { text: 'Usar esta imagen' }, library: { type: 'image' }, multiple: false });
    f.on('select', function () {
      var a = f.state().get('selection').first();
      if (a) { cb(a.toJSON()); }
    });
    f.open();
  }
  function imgUrl(a) {
    var s = a.sizes || {};
    return (s.large && s.large.url) || (s.full && s.full.url) || a.url || '';
  }
  function setMediaImg(el, a) {
    var url = imgUrl(a);
    if (!url) { return; }
    el.classList.remove('is-art'); el.classList.add('has-img');
    while (el.firstChild) { el.removeChild(el.firstChild); }
    var im = d.createElement('img'); im.className = 'lx-img'; im.alt = a.alt || ''; im.src = url;
    el.appendChild(im);
    var hero = el.closest ? el.closest('.lx-hero') : null;
    if (hero) { hero.classList.add('has-photo'); }
  }
  function changeImage(el) {
    var path = el.getAttribute('data-sc');
    chooseImage('Elegir imagen', function (a) {
      byPath(path).forEach(function (o) { setMediaImg(o, a); });
      send([{ op: 'img', path: path, id: a.id }]).then(function () { /* ya actualizado */ }, function () { toast('No se cambió la imagen.', 'err'); });
    });
  }
  function removeImage(el) {
    var path = el.getAttribute('data-sc');
    var li = el.closest ? el.closest('[data-sc-li]') : null;
    confirmBox('Quitar imagen', li ? 'Se quitará esta foto de la galería.' : 'Se mostrará un diseño decorativo en lugar de la foto. Podrá agregar otra cuando quiera.', 'Quitar', true).then(function (ok) {
      if (!ok) { return; }
      var op = li ? { op: 'list_del', path: li.getAttribute('data-sc-li'), index: parseInt(li.getAttribute('data-sc-i'), 10) } : { op: 'img', path: path, id: 0 };
      send([op]).then(function () { reloadKeep(); }, function () { /* error ya mostrado */ });
    });
  }

  /* ------------------------------------------------------------ iconos */
  function openIcon(el) {
    var path = el.getAttribute('data-sc');
    var grid = h('div', { class: 'sc-ed-icons', role: 'listbox', 'aria-label': 'Íconos' }, [h('p', { class: 'sc-ed-muted', text: 'Cargando íconos…' })]);
    var search = h('input', { type: 'search', class: 'sc-ed-in', placeholder: 'Buscar: escudo, reloj, llave…', 'aria-label': 'Buscar ícono', autocomplete: 'off' });
    var body = h('div', {}, [h('div', { class: 'sc-ed-field' }, [search]), grid]);
    var p = openPanel('dialog', 'Elegir un ícono', body, { wide: true });
    loadIcons().then(function (list) {
      function draw() {
        var t = norm(search.value);
        grid.innerHTML = '';
        var n = 0;
        list.forEach(function (it) {
          if (t && norm(it.label + ' ' + it.key).indexOf(t) < 0) { return; }
          n++;
          var b = h('button', { type: 'button', class: 'sc-ed-icon', role: 'option', title: it.label, 'data-k': it.key, html: it.svg }, [h('span', { text: it.label })]);
          b.addEventListener('click', function () {
            p.close();
            byPath(path).forEach(function (o) { o.innerHTML = it.svg; });
            send([{ op: 'icon', path: path, key: it.key }]).then(function () { /* ok */ }, function () { reloadKeep(); });
          });
          grid.appendChild(b);
        });
        if (!n) { grid.appendChild(h('p', { class: 'sc-ed-muted', text: 'No encontramos ese ícono. Pruebe con otra palabra.' })); }
      }
      search.addEventListener('input', draw);
      draw();
    }, function () { grid.innerHTML = ''; grid.appendChild(h('p', { class: 'sc-ed-err', text: 'No se pudieron cargar los íconos. Recargue la página.' })); });
  }

  /* ------------------------------------------------------------ enlaces y botones */
  function openLink(el) {
    var path = el.getAttribute('data-sc');
    loadState().then(function (st) {
      var lk = (st.links || {})[path] || { text: (el.textContent || '').trim(), url: '' };
      var kind = 'url', val = lk.url || '';
      if (val === 'wa' || val === 'tel' || val === 'mail') { kind = val; }
      else if (/^page:/.test(val)) { kind = 'page'; }
      else if (/^svc:/.test(val)) { kind = 'svc'; }
      var text = h('input', { type: 'text', class: 'sc-ed-in', maxlength: '120', value: lk.text, id: 'sc-ed-lk-t' });
      var sel = h('select', { class: 'sc-ed-in', id: 'sc-ed-lk-k' });
      [['wa', 'WhatsApp'], ['tel', 'Llamar por teléfono'], ['mail', 'Enviar un correo'], ['page', 'Una página del sitio'], ['svc', 'Un servicio'], ['url', 'Otra dirección web (URL)']].forEach(function (o) {
        sel.appendChild(h('option', { value: o[0], text: o[1], selected: o[0] === kind }));
      });
      var pageSel = h('select', { class: 'sc-ed-in', id: 'sc-ed-lk-p' });
      (st.tokens || []).forEach(function (t) { pageSel.appendChild(h('option', { value: t.token, text: t.label, selected: t.token === val })); });
      var svcSel = h('select', { class: 'sc-ed-in', id: 'sc-ed-lk-s' });
      (st.services || []).forEach(function (s) { svcSel.appendChild(h('option', { value: 'svc:' + s.n, text: s.nombre + (s.on ? '' : ' (oculto)'), selected: 'svc:' + s.n === val })); });
      var urlIn = h('input', { type: 'text', class: 'sc-ed-in', id: 'sc-ed-lk-u', placeholder: 'https://…', value: kind === 'url' ? val : '', autocomplete: 'off' });
      var note = h('p', { class: 'sc-ed-muted' });
      var err = h('p', { class: 'sc-ed-err', role: 'alert' });
      var rowP = h('div', { class: 'sc-ed-field' }, [h('label', { 'for': 'sc-ed-lk-p', text: 'Página' }), pageSel]);
      var rowS = h('div', { class: 'sc-ed-field' }, [h('label', { 'for': 'sc-ed-lk-s', text: 'Servicio' }), svcSel]);
      var rowU = h('div', { class: 'sc-ed-field' }, [h('label', { 'for': 'sc-ed-lk-u', text: 'Dirección web' }), urlIn]);
      function sync() {
        var k = sel.value;
        rowP.hidden = k !== 'page'; rowS.hidden = k !== 'svc'; rowU.hidden = k !== 'url';
        var b = st.biz || {};
        note.textContent = k === 'wa' ? (b.whatsapp ? 'Abrirá WhatsApp con el número +' + b.whatsapp.replace(/\D/g, '') + '.' : 'Aún no escribió su número de WhatsApp: agréguelo en «Datos del negocio».')
          : k === 'tel' ? (b.telefono ? 'Marcará el teléfono ' + b.telefono + '.' : 'Aún no escribió su teléfono: agréguelo en «Datos del negocio».')
          : k === 'mail' ? (b.correo ? 'Escribirá un correo a ' + b.correo + '.' : 'Aún no escribió su correo: agréguelo en «Datos del negocio».') : '';
      }
      sel.addEventListener('change', sync); sync();
      var save = h('button', { type: 'button', class: 'sc-ed-b sc-ed-b--pri', text: 'Guardar' });
      var cancel = h('button', { type: 'button', class: 'sc-ed-b', text: 'Cancelar' });
      var body = h('form', { class: 'sc-ed-form', novalidate: '' }, [
        h('div', { class: 'sc-ed-field' }, [h('label', { 'for': 'sc-ed-lk-t', text: 'Texto del botón' }), text]),
        h('div', { class: 'sc-ed-field' }, [h('label', { 'for': 'sc-ed-lk-k', text: '¿A dónde lleva?' }), sel]),
        rowP, rowS, rowU, note, err,
        h('div', { class: 'sc-ed-actions' }, [cancel, save])
      ]);
      var p = openPanel('dialog', 'Editar botón o enlace', body, { focus: '#sc-ed-lk-t' });
      cancel.addEventListener('click', p.close);
      function go(e) {
        if (e) { e.preventDefault(); }
        var k = sel.value;
        var url = k === 'page' ? pageSel.value : k === 'svc' ? svcSel.value : k === 'url' ? urlIn.value.trim() : k;
        if (!text.value.trim()) { err.textContent = 'Escriba el texto del botón.'; text.focus(); return; }
        if (!url) { err.textContent = 'Elija o escriba el destino.'; return; }
        err.textContent = ''; save.disabled = true;
        send([{ op: 'link', path: path, text: text.value, url: url }], { quiet: true }).then(function (r) {
          var res = r.results && r.results[0];
          if (!res) { p.close(); return; }
          st.links[path] = { text: res.text, url: res.url };
          byPath(path).forEach(function (o) {
            var s = o.querySelector('span') || o;
            s.textContent = res.text;
            if (o.tagName === 'A' && res.href) {
              o.setAttribute('href', res.href);
              if (/^https?:/i.test(res.href) && res.href.indexOf(C.home.replace(/\/$/, '')) !== 0) { o.setAttribute('target', '_blank'); o.setAttribute('rel', 'noopener noreferrer'); } else { o.removeAttribute('target'); }
            }
          });
          p.close();
        }, function (e2) { save.disabled = false; err.textContent = e2.message || 'No se pudo guardar.'; });
      }
      save.addEventListener('click', go);
      body.addEventListener('submit', go);
    }, function () { toast('No se pudo cargar la información del sitio. Recargue la página.', 'err'); });
  }

  /* ------------------------------------------------------------ acciones sobre listas, secciones y servicios */
  function listAdd(path, extra) {
    var op = { op: 'list_add', path: path };
    if (extra) { for (var k in extra) { op[k] = extra[k]; } }
    send([op]).then(function (r) {
      var res = r.results && r.results[0];
      reloadKeep(res && res.focus ? res.focus : '');
    }, function () { /* error mostrado */ });
  }
  function addService() {
    send([{ op: 'svc_add' }]).then(function (r) {
      var res = r.results && r.results[0];
      toast('Servicio creado. Ahora cambie su nombre y su descripción.');
      if (res && res.url) { w.location.href = res.url + (res.url.indexOf('?') < 0 ? '?' : '&') + 'sc_edit=1&sc_focus=' + encodeURIComponent(res.focus || ''); } else { reloadKeep(); }
    }, function () { /* error mostrado */ });
  }
  function itemOp(li, dir) {
    var list = li.getAttribute('data-sc-li'), i = parseInt(li.getAttribute('data-sc-i'), 10);
    send([{ op: 'list_move', path: list, index: i, dir: dir }]).then(function () { reloadKeep(); }, function () { /* ya mostrado */ });
  }
  function itemDel(li) {
    var list = li.getAttribute('data-sc-li'), i = parseInt(li.getAttribute('data-sc-i'), 10);
    confirmBox('Eliminar elemento', '¿Quiere eliminar este elemento? Si se equivoca, puede usar «Deshacer».', 'Eliminar', true).then(function (ok) {
      if (!ok) { return; }
      send([{ op: 'list_del', path: list, index: i }]).then(function () { reloadKeep(); }, function () { /* ya mostrado */ });
    });
  }
  function secMove(sec, dir) {
    var all = qa('[data-sc-sec]');
    var i = all.indexOf(sec), t = all[i + dir];
    if (!t) { return; }
    var to = parseInt(t.getAttribute('data-sc-sec').split('.').pop(), 10);
    send([{ op: 'sec_move', path: sec.getAttribute('data-sc-sec'), sid: sec.id, to: to }]).then(function () { reloadKeep(); }, function () { /* ya mostrado */ });
  }
  function secToggle(sec, bt) {
    var on = sec.classList.contains('lx-off');
    send([{ op: 'sec_on', path: sec.getAttribute('data-sc-sec'), sid: sec.id, on: on }]).then(function () {
      sec.classList.toggle('lx-off', !on);
      if (bt) { bt.innerHTML = '<span class="sc-ed-ico">' + ic(on ? 'eye' : 'eyeoff') + '</span>'; bt.setAttribute('title', on ? 'Ocultar esta sección' : 'Mostrar esta sección'); bt.setAttribute('aria-label', on ? 'Ocultar esta sección' : 'Mostrar esta sección'); }
      toast(on ? 'Sección visible para sus clientes.' : 'Sección oculta. Sus clientes ya no la ven.');
      if (state) { state.pages.forEach(function (pg) { pg.sections.forEach(function (s) { if (s.path === sec.getAttribute('data-sc-sec')) { s.on = on; } }); }); }
    }, function () { /* ya mostrado */ });
  }
  var ADD = {
    faq: { list: 'items', label: 'Pregunta' }, values: { list: 'items', label: 'Punto' }, process: { list: 'items', label: 'Paso' },
    about: { list: 'points', label: 'Punto' }, strip: { list: 'items', label: 'Palabra' }, gallery: { list: 'items', label: 'Foto', media: true }, services: { svc: true, label: 'Servicio' }
  };
  function secType(sec) { var m = (sec.className || '').match(/lx-sec--([a-z]+)/); return m ? m[1] : ''; }
  function doAdd(type, secPath) {
    var a = ADD[type];
    if (!a) { return; }
    if (a.svc) { addService(); return; }
    var path = secPath + '.data.' + a.list;
    if (a.media) { chooseImage('Agregar una foto a la galería', function (img) { listAdd(path, { id: img.id }); }); return; }
    listAdd(path);
  }
  function svcAction(card, act) {
    var path = card.getAttribute('data-sc-item'), n = parseInt(path.split('.')[1], 10);
    var go = function (op, after) { send([op]).then(after, function () { /* ya mostrado */ }); };
    if (act === 'dup') {
      go({ op: 'svc_dup', n: n }, function (r) {
        var res = r.results && r.results[0];
        toast('Servicio duplicado.');
        if (res && res.url) { w.location.href = res.url + (res.url.indexOf('?') < 0 ? '?' : '&') + 'sc_edit=1&sc_focus=' + encodeURIComponent(res.focus || ''); } else { reloadKeep(); }
      });
    } else if (act === 'toggle') {
      var off = card.classList.contains('sc-ed-off');
      go({ op: 'svc_on', n: n, on: off }, function () { card.classList.toggle('sc-ed-off', !off); toast(off ? 'Servicio visible de nuevo.' : 'Servicio oculto. Sus clientes ya no lo ven.'); reloadKeep(); });
    } else if (act === 'del') {
      confirmBox('Eliminar servicio', 'El servicio dejará de verse en el sitio y en el menú. Si se equivoca, puede usar «Deshacer».', 'Eliminar', true).then(function (ok) {
        if (ok) { go({ op: 'svc_del', n: n }, function () { toast('Servicio eliminado.'); reloadKeep(); }); }
      });
    }
  }

  /* ------------------------------------------------------------ resaltado y herramientas flotantes */
  function ensureLayer() {
    if (layer) { return; }
    layer = h('div', { id: 'sc-ed-layer', class: 'sc-ed-ui', 'data-sc-ui': '' });
    hl = h('div', { class: 'sc-ed-hl', 'aria-hidden': 'true' });
    hlLbl = h('span', { class: 'sc-ed-hl__l' });
    hl.appendChild(hlLbl);
    chipImg = h('div', { class: 'sc-ed-chip sc-ed-chip--img sc-ed-ui', role: 'toolbar', 'aria-label': 'Imagen' });
    chipLi = h('div', { class: 'sc-ed-chip sc-ed-chip--li sc-ed-ui', role: 'toolbar', 'aria-label': 'Elemento de la lista' });
    chipItem = h('div', { class: 'sc-ed-chip sc-ed-chip--item sc-ed-ui', role: 'toolbar', 'aria-label': 'Servicio' });
    [hl, chipImg, chipLi, chipItem].forEach(function (e) { layer.appendChild(e); });
    d.body.appendChild(layer);
    function mk(host, cls, ico, label, fn) {
      var b = btn('sc-ed-cb ' + cls, ico, label, { title: label, 'aria-label': label });
      b.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); fn(); });
      host.appendChild(b); return b;
    }
    mk(chipImg, '', 'image', 'Cambiar imagen', function () { if (H.img) { changeImage(H.img); } });
    chipImg.rm = mk(chipImg, 'sc-ed-cb--warn', 'x', 'Quitar', function () { if (H.img) { removeImage(H.img); } });
    mk(chipLi, '', 'up', 'Subir', function () { var l = H.li || sticky.li; if (l) { itemOp(l, -1); } });
    mk(chipLi, '', 'down', 'Bajar', function () { var l = H.li || sticky.li; if (l) { itemOp(l, 1); } });
    mk(chipLi, 'sc-ed-cb--warn', 'x', 'Eliminar', function () { var l = H.li || sticky.li; if (l) { itemDel(l); } });
    mk(chipItem, '', 'copy', 'Duplicar', function () { var c = H.item || sticky.item; if (c) { svcAction(c, 'dup'); } });
    chipItem.eye = mk(chipItem, '', 'eye', 'Ocultar', function () { var c = H.item || sticky.item; if (c) { svcAction(c, 'toggle'); } });
    mk(chipItem, 'sc-ed-cb--warn', 'trash', 'Eliminar', function () { var c = H.item || sticky.item; if (c) { svcAction(c, 'del'); } });
  }
  function labelFor(el) {
    var t = el.getAttribute('data-sc-t');
    return t === 'link' ? 'Clic para editar el botón' : t === 'icon' ? 'Clic para cambiar el ícono' : 'Clic para editar';
  }
  function placeChip(chip, el, corner) {
    var r = el.getBoundingClientRect();
    chip.classList.add('is-show');
    var cw = chip.offsetWidth, ch = chip.offsetHeight;
    var top = Math.max(r.top + 8, BAR_H + 8);
    if (top > r.bottom - ch - 4) { top = Math.max(BAR_H + 8, Math.min(r.top + 8, r.bottom - ch - 4)); }
    var left = corner === 'left' ? r.left + 8 : r.right - cw - 8;
    left = Math.max(6, Math.min(w.innerWidth - cw - 6, left));
    top = Math.max(BAR_H + 6, Math.min(w.innerHeight - ch - 6, top));
    chip.style.left = left + 'px'; chip.style.top = top + 'px';
  }
  var raf = 0;
  function render() {
    raf = 0;
    if (!layer) { return; }
    if (!editing || peek || cur || openUi) { clearHover(true); return; }
    var t = H.t, img = H.img;
    if (t && t.isConnected) {
      var r = t.getBoundingClientRect();
      hl.className = 'sc-ed-hl is-show is-' + t.getAttribute('data-sc-t');
      hl.style.left = r.left - 3 + 'px'; hl.style.top = r.top - 3 + 'px'; hl.style.width = r.width + 6 + 'px'; hl.style.height = r.height + 6 + 'px';
      hlLbl.textContent = labelFor(t);
      hlLbl.className = 'sc-ed-hl__l' + (r.top < BAR_H + 30 ? ' is-in' : '');
    } else if (img && img.isConnected) {
      var r2 = img.getBoundingClientRect();
      var vt = Math.max(r2.top, BAR_H), vb = Math.min(r2.bottom, w.innerHeight);
      hl.className = 'sc-ed-hl is-show is-img';
      hl.style.left = r2.left + 'px'; hl.style.top = vt + 'px'; hl.style.width = r2.width + 'px'; hl.style.height = Math.max(0, vb - vt) + 'px';
      hlLbl.textContent = '';
    } else { hl.classList.remove('is-show'); }
    if (img && img.isConnected && !t) {
      chipImg.rm.hidden = !img.classList.contains('has-img');
      placeChip(chipImg, img, 'left');
    } else { chipImg.classList.remove('is-show'); }
    var li = H.li || sticky.li;
    if (li && li.isConnected && li.offsetParent !== null) { placeChip(chipLi, li, 'right'); } else { chipLi.classList.remove('is-show'); }
    var it = H.item || sticky.item;
    if (it && it.isConnected) {
      var off = it.classList.contains('sc-ed-off');
      chipItem.eye.innerHTML = '<span class="sc-ed-ico">' + ic(off ? 'eyeoff' : 'eye') + '</span><span class="sc-ed-lbl">' + (off ? 'Mostrar' : 'Ocultar') + '</span>';
      chipItem.eye.setAttribute('title', off ? 'Mostrar este servicio' : 'Ocultar este servicio');
      placeChip(chipItem, it, 'right');
      if (li && li.isConnected) { chipLi.style.top = (parseFloat(chipLi.style.top) + 0) + 'px'; }
    } else { chipItem.classList.remove('is-show'); }
  }
  function schedule() { if (!raf) { raf = requestAnimationFrame(render); } }
  function clearHover(keepStick) {
    H.t = H.img = H.li = H.item = null;
    if (!keepStick) { sticky.li = sticky.item = null; }
    if (layer) { hl.classList.remove('is-show'); chipImg.classList.remove('is-show'); if (!(sticky.li && !openUi && !cur)) { chipLi.classList.remove('is-show'); } if (!(sticky.item && !openUi && !cur)) { chipItem.classList.remove('is-show'); } }
  }
  var BLOCK = '.sc-header, .lx-fabs, .lx-top, .lx-lightbox, .sc-ed-ui, .media-modal, #wpadminbar';
  function pick(x, y) {
    var out = { t: null, img: null, li: null, item: null };
    var stack = d.elementsFromPoint ? d.elementsFromPoint(x, y) : [];
    for (var i = 0; i < stack.length; i++) {
      var n = stack[i];
      if (!n.closest) { continue; }
      if (n.closest(BLOCK)) { break; }
      var s = n.closest('[data-sc]');
      if (s && !out.t && !out.img) {
        var ty = s.getAttribute('data-sc-t');
        if (ty === 'img') { out.img = s; } else { out.t = s; }
      } else if (s && out.img && !out.t) {
        var ty2 = s.getAttribute('data-sc-t');
        if (ty2 !== 'img') { out.t = s; }
      }
      if (!out.li) { var l = n.closest('[data-sc-li]'); if (l) { out.li = l; } }
      if (!out.item) { var it = n.closest('[data-sc-item]'); if (it) { out.item = it; } }
    }
    return out;
  }
  function onMove(e) {
    if (!editing || peek || cur || openUi) { return; }
    if (e.target && e.target.closest && e.target.closest('.sc-ed-ui, .media-modal')) { return; }
    var p = pick(e.clientX, e.clientY);
    if (p.t !== H.t || p.img !== H.img || p.li !== H.li || p.item !== H.item) {
      H.t = p.t; H.img = p.img; H.li = p.li; H.item = p.item;
      if (p.li || p.item) { sticky.li = null; sticky.item = null; }
      schedule();
    }
  }
  function activate(el) {
    var t = el.getAttribute('data-sc-t');
    if (t === 'text') { startText(el); }
    else if (t === 'link') { openLink(el); }
    else if (t === 'icon') { openIcon(el); }
    else if (t === 'img') { changeImage(el); }
  }
  function onClick(e) {
    if (!editing || peek) { return; }
    var tg = e.target;
    if (!tg || !tg.closest) { return; }
    if (tg.closest('.sc-ed-ui, .media-modal, .media-modal-backdrop, #wpadminbar')) { return; }
    if (cur && e.detail === 0 && cur.node.closest('summary')) { e.preventDefault(); e.stopPropagation(); return; } // activación por teclado de <summary>
    if (cur && cur.node.contains(tg)) { e.preventDefault(); return; }
    if (cur) { endText(true); }
    var p = pick(e.clientX, e.clientY);
    var mod = e.ctrlKey || e.metaKey;
    if (tg.closest('summary i') && !p.t) { return; }
    var a = tg.closest('a[href]');
    if (a && mod && !p.t && !p.img) { return; }
    if (p.li) { sticky.li = p.li; }
    if (p.item) { sticky.item = p.item; }
    if (p.t) { e.preventDefault(); e.stopPropagation(); activate(p.t); schedule(); return; }
    if (p.img && !tg.closest('button, input, select, textarea')) {
      e.preventDefault(); e.stopPropagation();
      H.img = p.img; H.li = p.li; H.item = p.item; schedule();
      return;
    }
    if (a || tg.closest('summary, form button[type=submit], [data-lx-lightbox]')) { e.preventDefault(); e.stopPropagation(); }
    schedule();
  }

  /* ------------------------------------------------------------ decoración de secciones, listas y servicios */
  function hasAdd(par) { for (var c = par.firstElementChild; c; c = c.nextElementSibling) { if (c.classList.contains('sc-ed-add')) { return true; } } return false; }
  function decorate() {
    // todas las preguntas abiertas para poder editarlas
    qa('details.lx-qa').forEach(function (x) { x.open = true; });
    qa('[data-sc]').forEach(function (e) { if (!e.hasAttribute('tabindex') && e.tagName !== 'A') { e.setAttribute('tabindex', '0'); } });
    var secs = qa('[data-sc-sec]');
    secs.forEach(function (sec, idx) {
      if (q('.sc-ed-secbar', sec)) { return; }
      var type = secType(sec), path = sec.getAttribute('data-sc-sec');
      var on = !sec.classList.contains('lx-off');
      var label = sec.getAttribute('data-sc-sec-label') || type;
      var up = btn('sc-ed-sb', 'up', '', { title: 'Subir esta sección', 'aria-label': 'Subir la sección ' + label });
      var dn = btn('sc-ed-sb', 'down', '', { title: 'Bajar esta sección', 'aria-label': 'Bajar la sección ' + label });
      var eye = btn('sc-ed-sb', on ? 'eye' : 'eyeoff', '', { title: on ? 'Ocultar esta sección' : 'Mostrar esta sección', 'aria-label': on ? 'Ocultar la sección ' + label : 'Mostrar la sección ' + label });
      up.disabled = idx === 0; dn.disabled = idx === secs.length - 1;
      up.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); secMove(sec, -1); });
      dn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); secMove(sec, 1); });
      eye.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); secToggle(sec, eye); });
      var kids = [h('span', { class: 'sc-ed-sb__l', text: label }), up, dn, eye];
      if (ADD[type]) {
        var ad = btn('sc-ed-sb sc-ed-sb--add', 'plus', ADD[type].svc ? 'Agregar servicio' : 'Agregar ' + ADD[type].label.toLowerCase(), { title: 'Agregar ' + ADD[type].label.toLowerCase() });
        ad.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); doAdd(type, path); });
        kids.push(ad);
      }
      var b = h('div', { class: 'sc-ed-secbar sc-ed-ui', 'data-sc-ui': '', contenteditable: 'false' }, kids);
      sec.insertBefore(b, sec.firstChild);
    });
    // botones «＋ Agregar» al final de cada lista
    var seen = [];
    qa('[data-sc-li]').forEach(function (li) {
      var par = li.parentElement;
      if (!par || li.offsetParent === null && !li.closest('.lx-strip')) { return; }
      var list = li.getAttribute('data-sc-li');
      if (seen.indexOf(par) >= 0 || hasAdd(par)) { return; }
      if (par.closest('[aria-hidden="true"]') && !li.closest('.lx-strip__g:first-child')) { return; }
      seen.push(par);
      var sec = li.closest('[data-sc-sec]');
      var type = sec ? secType(sec) : '';
      var a = ADD[type];
      if (!a || a.svc) { return; }
      var tag = /^(UL|OL)$/.test(par.tagName) ? 'li' : (par.classList.contains('lx-strip__g') ? 'span' : 'div');
      var add = h(tag, { class: 'sc-ed-add sc-ed-ui', 'data-sc-ui': '', role: 'button', tabindex: '0', 'aria-label': 'Agregar ' + a.label.toLowerCase() }, [
        h('span', { class: 'sc-ed-ico', html: ic('plus', 20) }), h('span', { text: 'Agregar ' + a.label.toLowerCase() })
      ]);
      var run = function (e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; }
        e.preventDefault(); e.stopPropagation();
        if (a.media) { chooseImage('Agregar una foto a la galería', function (img) { listAdd(list, { id: img.id }); }); } else { listAdd(list); }
      };
      add.addEventListener('click', run); add.addEventListener('keydown', run);
      par.appendChild(add);
    });
    // tarjetas de servicios
    var cards = qa('[data-sc-item]');
    var gridDone = [];
    cards.forEach(function (c) {
      var n = parseInt(c.getAttribute('data-sc-item').split('.')[1], 10);
      if (state) { var sv = state.services.filter(function (s) { return s.n === n; })[0]; if (sv && !sv.on) { c.classList.add('sc-ed-off'); } }
      var par = c.parentElement;
      if (par && gridDone.indexOf(par) < 0 && !hasAdd(par)) {
        gridDone.push(par);
        var add = h('div', { class: 'sc-ed-add sc-ed-add--svc sc-ed-ui', 'data-sc-ui': '', role: 'button', tabindex: '0', 'aria-label': 'Agregar servicio' }, [h('span', { class: 'sc-ed-ico', html: ic('plus', 20) }), h('span', { text: 'Agregar servicio' })]);
        var run = function (e) { if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; } e.preventDefault(); e.stopPropagation(); addService(); };
        add.addEventListener('click', run); add.addEventListener('keydown', run);
        par.appendChild(add);
      }
    });
  }

  /* ------------------------------------------------------------ cajón: datos del negocio */
  var BIZ = [
    ['Su negocio', [
      ['nombre', 'Nombre del negocio', 'text', 'Tal como quiere que aparezca en el sitio.']
    ]],
    ['Contacto', [
      ['telefono', 'Teléfono', 'tel', 'Ejemplo: 2222-3333. Se puede tocar para llamar.'],
      ['whatsapp', 'WhatsApp (con código de país)', 'tel', 'Solo números. Ejemplo: 50255551234.'],
      ['whatsapp_msg', 'Mensaje inicial de WhatsApp', 'text', 'Texto que aparece escrito cuando un cliente le escribe.'],
      ['correo', 'Correo de contacto', 'email', 'Donde quiere recibir los mensajes.'],
      ['direccion', 'Dirección', 'area', 'Puede usar varias líneas.'],
      ['mapa_url', 'Enlace de Google Maps', 'url', 'En Google Maps busque su negocio, toque Compartir y copie el enlace.'],
      ['horario', 'Horario de atención', 'area', 'Ejemplo: Lunes a viernes, 8:00 a 17:00.']
    ]],
    ['Redes sociales', [
      ['facebook', 'Facebook', 'url', 'Enlace de su página.'],
      ['instagram', 'Instagram', 'text', 'Enlace o @usuario.'],
      ['tiktok', 'TikTok', 'text', 'Enlace o @usuario.'],
      ['youtube', 'YouTube', 'url', 'Enlace de su canal.'],
      ['x', 'X (Twitter)', 'text', 'Enlace o @usuario.'],
      ['linkedin', 'LinkedIn', 'url', 'Enlace de su perfil o empresa.']
    ]]
  ];
  function openBiz() {
    loadState().then(function (st) {
      var dirty = false;
      var body = h('div', { class: 'sc-ed-form' });
      // logo
      var logoImg = h('img', { class: 'sc-ed-logo__i', alt: 'Logo actual', src: (st.logo && st.logo.url) || '' });
      var logoWrap = h('div', { class: 'sc-ed-logo' }, [(st.logo && st.logo.url) ? logoImg : h('span', { class: 'sc-ed-muted', text: 'Sin logo' })]);
      var chg = h('button', { type: 'button', class: 'sc-ed-b', text: 'Cambiar logo' });
      var rem = h('button', { type: 'button', class: 'sc-ed-b', text: 'Quitar logo' });
      rem.hidden = !(st.logo && st.logo.id);
      chg.addEventListener('click', function () {
        chooseImage('Elegir el logo', function (a) {
          send([{ op: 'logo', id: a.id }]).then(function () {
            dirty = true; st.logo = { id: a.id, url: imgUrl(a) };
            logoWrap.innerHTML = ''; logoWrap.appendChild(h('img', { class: 'sc-ed-logo__i', alt: 'Logo actual', src: st.logo.url })); rem.hidden = false;
          }, function () { /* ya mostrado */ });
        });
      });
      rem.addEventListener('click', function () {
        send([{ op: 'logo', id: 0 }]).then(function () { dirty = true; st.logo = { id: 0, url: '' }; logoWrap.innerHTML = ''; logoWrap.appendChild(h('span', { class: 'sc-ed-muted', text: 'Sin logo' })); rem.hidden = true; }, function () { /* ya mostrado */ });
      });
      body.appendChild(h('section', { class: 'sc-ed-sect' }, [h('h3', { text: 'Logo' }), logoWrap, h('div', { class: 'sc-ed-actions sc-ed-actions--l' }, [chg, rem])]));
      BIZ.forEach(function (grp) {
        var sec = h('section', { class: 'sc-ed-sect' }, [h('h3', { text: grp[0] })]);
        grp[1].forEach(function (f) {
          var key = f[0], id = 'sc-ed-b-' + key;
          var inp = f[2] === 'area' ? h('textarea', { class: 'sc-ed-in', id: id, rows: '3', maxlength: '400' }) : h('input', { class: 'sc-ed-in', id: id, type: f[2] === 'tel' ? 'tel' : f[2] === 'email' ? 'email' : 'text', autocomplete: 'off', inputmode: f[2] === 'tel' ? 'tel' : f[2] === 'email' ? 'email' : false, maxlength: '300' });
          inp.value = (st.biz && st.biz[key]) || '';
          var msg = h('span', { class: 'sc-ed-fmsg', 'aria-live': 'polite' });
          var last = inp.value;
          var commit = function () {
            if (inp.value === last) { return; }
            msg.className = 'sc-ed-fmsg'; msg.textContent = 'Guardando…';
            var o = {}; o[key] = inp.value;
            send([{ op: 'biz', values: o }], { quiet: true }).then(function () {
              last = inp.value; st.biz[key] = inp.value; dirty = true;
              msg.className = 'sc-ed-fmsg is-ok'; msg.textContent = 'Guardado ✓';
              inp.classList.remove('is-bad');
            }, function (e) { msg.className = 'sc-ed-fmsg is-err'; msg.textContent = e.message || 'No se pudo guardar.'; inp.classList.add('is-bad'); });
          };
          inp.addEventListener('change', commit);
          if (f[2] !== 'area') { inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); inp.blur(); } }); }
          sec.appendChild(h('div', { class: 'sc-ed-field' }, [h('label', { 'for': id, text: f[1] }), inp, h('small', { text: f[3] }), msg]));
        });
        body.appendChild(sec);
      });
      body.appendChild(h('p', { class: 'sc-ed-muted', text: 'Los cambios se guardan solos al salir de cada campo. Al cerrar, la página se actualiza para mostrarlos.' }));
      openPanel('drawer', 'Datos del negocio', body, { onClose: function () { if (dirty) { reloadKeep(); } } });
    }, function () { toast('No se pudo cargar la información del negocio. Recargue la página.', 'err'); });
  }

  /* ------------------------------------------------------------ cajón: diseño */
  function applyDesignResult(res) {
    if (!res) { return; }
    var st = q('#sc-luxe-vars');
    if (st && res.css) { st.textContent = res.css; }
    var meta = q('meta[name="theme-color"]');
    if (meta && res.bg) { meta.setAttribute('content', res.bg); }
    qa('main.lx').forEach(function (m) { m.classList.remove('lx-dark', 'lx-light'); m.classList.add('lx-' + res.mood); });
    d.body.classList.remove('lx-body-dark', 'lx-body-light'); d.body.classList.add('lx-body-' + res.mood);
  }
  function openDesign() {
    loadState().then(function (st) {
      var ds = st.design;
      var timer = 0;
      var info = h('p', { class: 'sc-ed-contrast', 'aria-live': 'polite' });
      function showContrast(c) {
        if (!c) { return; }
        var worst = Math.min(c.ink || 21, c.muted || 21, c.primary || 21, c.accent || 21);
        info.className = 'sc-ed-contrast is-ok';
        info.textContent = 'Legibilidad verificada ✓ (contraste mínimo ' + worst.toFixed(1) + ':1; lo recomendado es 4,5:1).';
      }
      function apply(op) {
        op.op = 'design';
        send([op], { quiet: true }).then(function (r) {
          var res = r.results && r.results[0];
          applyDesignResult(res); showContrast(res && res.contrast);
          if (res) { ds.mood = res.mood; }
        }, function (e) { info.className = 'sc-ed-contrast is-err'; info.textContent = e.message || 'No se pudo aplicar el diseño.'; });
      }
      function colorRow(label, id, value, onChange, extra) {
        var col = h('input', { type: 'color', class: 'sc-ed-color', id: id, value: /^#[0-9a-f]{6}$/i.test(value) ? value : '#3b3f8f', 'aria-label': label });
        var hex = h('input', { type: 'text', class: 'sc-ed-in sc-ed-hex', value: value, maxlength: '7', 'aria-label': label + ' (código)', autocomplete: 'off' });
        col.addEventListener('input', function () { hex.value = col.value; });
        col.addEventListener('change', function () { hex.value = col.value; clearTimeout(timer); timer = setTimeout(function () { onChange(col.value); }, 250); });
        hex.addEventListener('change', function () { var v = hex.value.trim(); if (/^#?[0-9a-f]{3}([0-9a-f]{3})?$/i.test(v)) { if (v[0] !== '#') { v = '#' + v; } hex.value = v; if (/^#[0-9a-f]{6}$/i.test(v)) { col.value = v; } onChange(v); } else { info.className = 'sc-ed-contrast is-err'; info.textContent = 'Escriba un color como #1d476b.'; } });
        return h('div', { class: 'sc-ed-field' }, [h('label', { 'for': id, text: label }), h('div', { class: 'sc-ed-colorrow' }, [col, hex, extra || null])]);
      }
      var autoAcc = h('input', { type: 'checkbox', id: 'sc-ed-acc-auto', checked: !!ds.auto });
      var autoLbl = h('label', { 'class': 'sc-ed-check', 'for': 'sc-ed-acc-auto' }, [autoAcc, h('span', { text: 'Automático' })]);
      var accRow = colorRow('Color de acento (detalles y destacados)', 'sc-ed-acc', ds.accent || (ds.palette && ds.palette.accent) || '#caa55e', function (v) { autoAcc.checked = false; apply({ accent: v }); }, autoLbl);
      autoAcc.addEventListener('change', function () { if (autoAcc.checked) { apply({ accent: '' }); } });
      var priRow = colorRow('Color principal de su marca', 'sc-ed-pri', ds.primary, function (v) { apply({ primary: v }); });
      // ambiente
      var moodB = {};
      var moodWrap = h('div', { class: 'sc-ed-moods', role: 'group', 'aria-label': 'Ambiente' });
      [['dark', 'Oscuro', 'Elegante y dramático'], ['light', 'Claro', 'Limpio y luminoso']].forEach(function (m) {
        var b = h('button', { type: 'button', class: 'sc-ed-mood is-' + m[0], 'aria-pressed': ds.mood === m[0] ? 'true' : 'false' }, [h('span', { class: 'sc-ed-mood__s' }), h('strong', { text: m[1] }), h('small', { text: m[2] })]);
        b.addEventListener('click', function () { Object.keys(moodB).forEach(function (k) { moodB[k].setAttribute('aria-pressed', k === m[0] ? 'true' : 'false'); }); apply({ mood: m[0] }); });
        moodB[m[0]] = b; moodWrap.appendChild(b);
      });
      // tipografías
      function fontGroup(kind, title) {
        var wrap = h('div', { class: 'sc-ed-fonts', role: 'group', 'aria-label': title });
        var btns = {};
        ds.catalog[kind].forEach(function (f) {
          var b = h('button', { type: 'button', class: 'sc-ed-font', 'aria-pressed': (ds.fonts && ds.fonts[kind]) === f.key ? 'true' : 'false', style: 'font-family:' + f.stack }, [
            h('span', { class: 'sc-ed-font__a', text: 'Aa' }), h('span', { class: 'sc-ed-font__n', text: f.label }), h('span', { class: 'sc-ed-font__s', text: kind === 'head' ? 'Un título elegante' : 'Texto claro y fácil de leer' })
          ]);
          b.addEventListener('click', function () {
            Object.keys(btns).forEach(function (k) { btns[k].setAttribute('aria-pressed', k === f.key ? 'true' : 'false'); });
            ds.fonts = ds.fonts || {}; ds.fonts[kind] = f.key;
            var o = {}; o[kind] = f.key; apply(o);
          });
          btns[f.key] = b; wrap.appendChild(b);
        });
        return h('section', { class: 'sc-ed-sect' }, [h('h3', { text: title }), wrap]);
      }
      var body = h('div', { class: 'sc-ed-form' }, [
        h('section', { class: 'sc-ed-sect' }, [h('h3', { text: 'Colores de la marca' }), priRow, accRow,
          h('p', { class: 'sc-ed-muted', text: 'Con sus colores calculamos automáticamente fondos y textos para que siempre se lean bien.' }), info]),
        h('section', { class: 'sc-ed-sect' }, [h('h3', { text: 'Ambiente' }), moodWrap]),
        fontGroup('head', 'Letra de los títulos'),
        fontGroup('body', 'Letra de los textos')
      ]);
      openPanel('drawer', 'Diseño', body, { clear: true });
    }, function () { toast('No se pudo cargar el diseño. Recargue la página.', 'err'); });
  }

  /* ------------------------------------------------------------ cajón: secciones */
  function openSections() {
    loadState(true).then(function (st) {
      var key = C.page;
      var pg = st.pages.filter(function (p) { return p.key === key; })[0];
      var body = h('div', { class: 'sc-ed-form' });
      if (pg) {
        var list = h('ol', { class: 'sc-ed-seclist' });
        var rows = pg.sections;
        rows.forEach(function (s, idx) {
          var present = !!q('[data-sc-sec="' + s.path + '"]');
          var eye = btn('sc-ed-sb sc-ed-sb--dk', s.on ? 'eye' : 'eyeoff', '', { title: s.on ? 'Ocultar' : 'Mostrar', 'aria-label': (s.on ? 'Ocultar ' : 'Mostrar ') + s.label });
          var up = btn('sc-ed-sb sc-ed-sb--dk', 'up', '', { title: 'Subir', 'aria-label': 'Subir ' + s.label }); up.disabled = idx === 0;
          var dn = btn('sc-ed-sb sc-ed-sb--dk', 'down', '', { title: 'Bajar', 'aria-label': 'Bajar ' + s.label }); dn.disabled = idx === rows.length - 1;
          var go = btn('sc-ed-sb sc-ed-sb--dk', 'link', '', { title: 'Ir a la sección', 'aria-label': 'Ir a ' + s.label }); go.disabled = !present;
          eye.addEventListener('click', function () {
            send([{ op: 'sec_on', path: s.path, sid: s.id, on: !s.on }]).then(function () {
              s.on = !s.on;
              eye.innerHTML = '<span class="sc-ed-ico">' + ic(s.on ? 'eye' : 'eyeoff') + '</span>';
              li.classList.toggle('is-off', !s.on);
              var el = q('[data-sc-sec="' + s.path + '"]'); if (el) { el.classList.toggle('lx-off', !s.on); }
            }, function () { /* ya mostrado */ });
          });
          up.addEventListener('click', function () { send([{ op: 'sec_move', path: s.path, sid: s.id, dir: -1 }]).then(function () { reloadKeep(); }, function () { /* ya mostrado */ }); });
          dn.addEventListener('click', function () { send([{ op: 'sec_move', path: s.path, sid: s.id, dir: 1 }]).then(function () { reloadKeep(); }, function () { /* ya mostrado */ }); });
          go.addEventListener('click', function () { closeUi(); var el = q('[data-sc-sec="' + s.path + '"]'); if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); pulse(el); } });
          var li = h('li', { class: 'sc-ed-secrow' + (s.on ? '' : ' is-off') }, [h('span', { class: 'sc-ed-secrow__n', text: String(idx + 1) }), h('span', { class: 'sc-ed-secrow__l' }, [h('strong', { text: s.label }), h('small', { text: s.on ? 'Visible' : 'Oculta' })]), go, up, dn, eye]);
          list.appendChild(li);
        });
        body.appendChild(h('section', { class: 'sc-ed-sect' }, [h('h3', { text: 'Secciones de «' + pg.title + '»' }), h('p', { class: 'sc-ed-muted', text: 'Use el ojo para mostrar u ocultar y las flechas para cambiar el orden.' }), list]));
      } else {
        body.appendChild(h('section', { class: 'sc-ed-sect' }, [h('h3', { text: 'Esta página' }), h('p', { class: 'sc-ed-muted', text: 'Esta página no tiene secciones que ordenar. Puede cambiar sus textos, imagen e ícono directamente.' })]));
      }
      var pgs = h('ul', { class: 'sc-ed-pages' });
      st.pages.forEach(function (p) {
        var u = new URL(p.url, w.location.href); u.searchParams.set('sc_edit', '1');
        pgs.appendChild(h('li', {}, [h('a', { href: u.toString(), text: p.title, 'aria-current': p.key === key ? 'page' : false })]));
      });
      st.services.forEach(function (s) {
        if (!s.on || !s.url) { return; }
        var u = new URL(s.url, w.location.href); u.searchParams.set('sc_edit', '1');
        pgs.appendChild(h('li', { class: 'is-svc' }, [h('a', { href: u.toString(), text: 'Servicio: ' + s.nombre })]));
      });
      body.appendChild(h('section', { class: 'sc-ed-sect' }, [h('h3', { text: 'Editar otra página' }), pgs]));
      openPanel('drawer', 'Secciones', body, {});
    }, function () { toast('No se pudo cargar las secciones. Recargue la página.', 'err'); });
  }

  /* ------------------------------------------------------------ barra superior y botón flotante */
  function buildBar() {
    statusEl = h('div', { class: 'sc-ed-status is-ok', role: 'status', 'aria-live': 'polite' }, [h('span', { class: 'sc-ed-status__d' }), h('span', { class: 'sc-ed-status__t', text: 'Guardado ✓' })]);
    var tog = btn('sc-ed-btn sc-ed-btn--main is-on', 'pencil', 'Editar mi web', { 'aria-pressed': 'true', title: 'Está editando. Pulse para salir del modo de edición.' });
    tog.addEventListener('click', function () { flush().then(function () { w.location.href = siteUrl(false); }); });
    undoBtn = btn('sc-ed-btn', 'undo', 'Deshacer', { title: 'Deshacer el último cambio', disabled: true });
    undoBtn.addEventListener('click', function () {
      if (undoBtn.disabled) { return; }
      flush().then(function () { return send([{ op: 'undo' }]); }).then(function () { reloadKeep(); }, function () { /* ya mostrado */ });
    });
    var biz = btn('sc-ed-btn', 'brief', 'Datos del negocio', { title: 'Teléfono, WhatsApp, dirección, redes sociales y logo' });
    biz.addEventListener('click', openBiz);
    var des = btn('sc-ed-btn', 'palette', 'Diseño', { title: 'Colores de la marca y tipografías' });
    des.addEventListener('click', openDesign);
    var sec = btn('sc-ed-btn', 'layers', 'Secciones', { title: 'Mostrar, ocultar y reordenar las secciones de esta página' });
    sec.addEventListener('click', openSections);
    var pk = btn('sc-ed-btn', 'eye', 'Ver sin edición', { 'aria-pressed': 'false', title: 'Vea su web como la ven sus clientes' });
    pk.addEventListener('click', function () {
      peek = !peek; d.body.classList.toggle('sc-ed-peek', peek);
      pk.setAttribute('aria-pressed', peek ? 'true' : 'false'); pk.classList.toggle('is-on', peek);
      pk.querySelector('.sc-ed-lbl').textContent = peek ? 'Volver a editar' : 'Ver sin edición';
      pk.querySelector('.sc-ed-ico').innerHTML = ic(peek ? 'pencil' : 'eye');
      endText(true); clearHover(); closeUi();
    });
    var pn = h('a', { class: 'sc-ed-btn', href: C.panel, title: 'Ir al panel de administración' }, [h('span', { class: 'sc-ed-ico', html: ic('panel') }), h('span', { class: 'sc-ed-lbl', text: 'Panel' })]);
    var hp = btn('sc-ed-btn', 'help', 'Ayuda', { title: 'Ver el recorrido de ayuda' });
    hp.addEventListener('click', function () { startTour(true); });
    bar = h('div', { id: 'sc-ed-bar', class: 'sc-ed-ui', role: 'toolbar', 'aria-label': 'Editor de la web' }, [
      h('div', { class: 'sc-ed-grp' }, [tog, undoBtn, statusEl]),
      h('div', { class: 'sc-ed-grp sc-ed-grp--r' }, [biz, des, sec, pk, pn, hp])
    ]);
    d.body.insertBefore(bar, d.body.firstChild);
    refreshUndo();
  }
  function buildPill() {
    var main = h('a', { class: 'sc-ed-pill__main', href: siteUrl(true), title: 'Cambiar textos, fotos y colores de su web' }, [h('span', { class: 'sc-ed-ico', html: ic('pencil', 18) }), h('span', { text: 'Editar mi web' })]);
    var more = h('button', { type: 'button', class: 'sc-ed-pill__more', 'aria-label': 'Más opciones', 'aria-expanded': 'false', title: 'Panel e instrucciones', html: ic('dots', 18) });
    var menu = h('div', { class: 'sc-ed-pill__menu', hidden: '' }, [
      h('a', { href: C.panel, text: 'Panel' }),
      h('a', { href: C.instr, text: 'Instrucciones' })
    ]);
    more.addEventListener('click', function (e) { e.stopPropagation(); var o = menu.hidden; menu.hidden = !o; more.setAttribute('aria-expanded', o ? 'true' : 'false'); });
    d.addEventListener('click', function (e) { if (!pill.contains(e.target)) { menu.hidden = true; more.setAttribute('aria-expanded', 'false'); } });
    d.addEventListener('keydown', function (e) { if (e.key === 'Escape') { menu.hidden = true; more.setAttribute('aria-expanded', 'false'); } });
    pill = h('div', { id: 'sc-ed-pill', class: 'sc-ed-ui', role: 'region', 'aria-label': 'Edición de la web' }, [h('div', { class: 'sc-ed-pill__bar' }, [main, more]), menu]);
    d.body.appendChild(pill);
  }
  function flush() {
    if (cur) { endText(true); }
    return queue.then(function () { return true; });
  }

  /* ------------------------------------------------------------ recorrido inicial */
  var TOUR = [
    ['Haga clic en cualquier texto para cambiarlo', 'Escriba el nuevo texto y pulse Enter para guardar. Con Esc cancela. Todo se guarda solo.'],
    ['Cambie fotos, íconos y botones', 'Pase el ratón (o toque) una imagen, un ícono o un botón: aparecerán las opciones para cambiarlo.'],
    ['Cada sección tiene su barra', 'Con ella puede moverla, ocultarla o agregar elementos. Las listas y los servicios tienen su botón «Agregar».'],
    ['Arriba está todo lo demás', 'En «Datos del negocio» cambia teléfono, redes y logo; en «Diseño», colores y letras. Si se equivoca, «Deshacer» lo arregla.']
  ];
  function startTour(force) {
    if (!force && lsGet('sc_ed_tour') === '1') { return; }
    if (tourEl) { return; }
    var i = 0;
    var title = h('h2', { id: 'sc-ed-tour-t' }), text = h('p'), dots = h('div', { class: 'sc-ed-tour__d' });
    var skip = h('button', { type: 'button', class: 'sc-ed-b', text: 'Omitir' });
    var next = h('button', { type: 'button', class: 'sc-ed-b sc-ed-b--pri', text: 'Siguiente' });
    var instr = h('a', { class: 'sc-ed-tour__a', href: C.instr, text: 'Abrir las instrucciones completas', hidden: '' });
    tourEl = h('div', { class: 'sc-ed-tour sc-ed-ui', role: 'dialog', 'aria-labelledby': 'sc-ed-tour-t', 'aria-live': 'polite' }, [dots, title, text, instr, h('div', { class: 'sc-ed-actions' }, [skip, next])]);
    function show() {
      title.textContent = TOUR[i][0]; text.textContent = TOUR[i][1];
      dots.innerHTML = ''; TOUR.forEach(function (_, k) { dots.appendChild(h('span', { class: k === i ? 'is-on' : '' })); });
      next.textContent = i === TOUR.length - 1 ? 'Entendido' : 'Siguiente';
      skip.hidden = i === TOUR.length - 1; instr.hidden = i !== TOUR.length - 1;
    }
    function end() { lsSet('sc_ed_tour', '1'); if (tourEl && tourEl.parentNode) { tourEl.parentNode.removeChild(tourEl); } tourEl = null; }
    next.addEventListener('click', function () { if (i >= TOUR.length - 1) { end(); } else { i++; show(); } });
    skip.addEventListener('click', end);
    d.body.appendChild(tourEl); show();
    next.focus();
  }

  /* ------------------------------------------------------------ foco desde ?sc_focus= */
  function pulse(el) {
    el.classList.add('sc-ed-pulse');
    setTimeout(function () { el.classList.remove('sc-ed-pulse'); }, 3200);
  }
  function focusFromUrl() {
    var u = new URL(w.location.href);
    var path = u.searchParams.get('sc_focus');
    if (!path) { return false; }
    u.searchParams.delete('sc_focus');
    try { w.history.replaceState(null, '', u.toString()); } catch (e) { /* nada */ }
    var el = byPath(path)[0] || q('[data-sc-sec="' + path.replace(/"/g, '') + '"]');
    if (!el) { return true; }
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    pulse(el);
    setTimeout(function () {
      if (el.hasAttribute('data-sc-t')) { activate(el); }
    }, 750);
    return true;
  }

  /* ------------------------------------------------------------ arranque */
  function init() {
    if (!editing) { buildPill(); return; }
    d.body.classList.add('sc-ed-on', 'sc-ed-editing');
    buildBar();
    ensureLayer();
    decorate();
    loadState().then(function () { decorate(); }, function () { setStatus('err', 'No se pudo cargar el editor. Recargue la página.'); });
    d.addEventListener('pointermove', onMove, { passive: true });
    d.addEventListener('click', onClick, true);
    d.addEventListener('submit', function (e) { if (editing && !peek) { e.preventDefault(); } }, true);
    d.addEventListener('scroll', function () { schedule(); placeHint(); }, { passive: true, capture: true });
    w.addEventListener('resize', function () { schedule(); placeHint(); });
    d.documentElement.addEventListener('pointerleave', function () { if (!openUi) { clearHover(true); } });
    d.addEventListener('focusin', function (e) {
      if (!editing || peek || cur || openUi) { return; }
      var t = e.target;
      if (t && t.closest && !isUi(t)) {
        var s = t.closest('[data-sc]');
        if (s) { var ty = s.getAttribute('data-sc-t'); H.t = ty === 'img' ? null : s; H.img = ty === 'img' ? s : null; H.li = t.closest('[data-sc-li]'); H.item = t.closest('[data-sc-item]'); schedule(); }
      }
    });
    d.addEventListener('keydown', function (e) {
      if (!editing || peek || cur || openUi || e.target.closest('input, textarea, select, [contenteditable]')) { return; }
      if ((e.key === 'Enter' || e.key === ' ') && e.target.hasAttribute && e.target.hasAttribute('data-sc')) { e.preventDefault(); activate(e.target); }
    });
    w.addEventListener('beforeunload', function (e) { if (pending > 0 || cur) { e.preventDefault(); e.returnValue = ''; } });
    // volver al punto donde estaba después de recargar
    var ret = ssGet('sc_ed_ret');
    if (ret) {
      try { var o = JSON.parse(ret); if (o && o.p === w.location.pathname) { w.scrollTo(0, o.y || 0); w.addEventListener('load', function () { w.scrollTo(0, o.y || 0); }); } } catch (e) { /* nada */ }
      ssDel('sc_ed_ret');
    }
    var focused = false;
    var doFocus = function () { if (!focused) { focused = true; var f = focusFromUrl(); if (!f) { setTimeout(function () { startTour(false); }, 700); } } };
    if (d.readyState === 'complete') { setTimeout(doFocus, 300); } else { w.addEventListener('load', function () { setTimeout(doFocus, 300); }); }
  }
  if (d.readyState === 'loading') { d.addEventListener('DOMContentLoaded', init); } else { init(); }
}());
