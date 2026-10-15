/* Panel — JS propio, sin librerías, compatible con CSP estricta */
(function () {
  'use strict';
  var d = document;
  function $(s, c) { return (c || d).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); }

  var side = $('#side'), st = $('#side-t');
  if (st && side) {
    st.addEventListener('click', function () { side.classList.toggle('open'); });
    d.addEventListener('click', function (e) { if (side.classList.contains('open') && !side.contains(e.target) && e.target !== st && !st.contains(e.target)) { side.classList.remove('open'); } });
  }
  d.addEventListener('click', function (e) {
    var c = e.target.closest ? e.target.closest('[data-confirm]') : null;
    if (c && !window.confirm(c.getAttribute('data-confirm'))) { e.preventDefault(); }
  });

  /* barra de formato para textos con HTML permitido */
  function wrap(ta, open, close) {
    var s = ta.selectionStart, e = ta.selectionEnd, v = ta.value;
    ta.value = v.slice(0, s) + open + v.slice(s, e) + close + v.slice(e);
    ta.focus(); ta.selectionStart = s + open.length; ta.selectionEnd = e + open.length;
  }
  $$('[data-tb]').forEach(function (bar) {
    var ta = bar.parentNode.querySelector('textarea'); if (!ta) { return; }
    [['Negrita', '<strong>', '</strong>'], ['Cursiva', '<em>', '</em>'], ['Párrafo', '<p>', '</p>'], ['Salto de línea', '<br>', ''], ['Lista', '<ul>\n<li>', '</li>\n</ul>']].forEach(function (b) {
      var x = d.createElement('button'); x.type = 'button'; x.textContent = b[0];
      x.addEventListener('click', function () { wrap(ta, b[1], b[2]); }); bar.appendChild(x);
    });
    var l = d.createElement('button'); l.type = 'button'; l.textContent = 'Enlace';
    l.addEventListener('click', function () {
      var u = window.prompt('Dirección del enlace (https://…, tel:…, mailto:… o /pagina/):', 'https://');
      if (u) { wrap(ta, '<a href="' + u.replace(/"/g, '') + '">', '</a>'); }
    });
    bar.appendChild(l);
  });

  /* bloques: subir, bajar, eliminar */
  var blocks = $('#blocks');
  if (blocks) {
    blocks.addEventListener('click', function (e) {
      var t = e.target, blk = t.closest ? t.closest('[data-blk]') : null; if (!blk) { return; }
      if (t.hasAttribute('data-up') && blk.previousElementSibling) { blocks.insertBefore(blk, blk.previousElementSibling); blk.scrollIntoView({ block: 'center' }); }
      else if (t.hasAttribute('data-down') && blk.nextElementSibling) { blocks.insertBefore(blk.nextElementSibling, blk); blk.scrollIntoView({ block: 'center' }); }
      else if (t.hasAttribute('data-rm')) {
        if (window.confirm('¿Eliminar este bloque? Se quitará al guardar.')) { $('[data-del]', blk).value = '1'; blk.hidden = true; }
      }
    });
  }

  /* selector de imágenes (modal) */
  var data = [];
  try { data = JSON.parse(($('#media-data') || { textContent: '[]' }).textContent); } catch (err) { data = []; }
  var modal = null, onPick = null, multi = false, picked = [];
  function build() {
    modal = d.createElement('div'); modal.className = 'mm'; modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true');
    var box = d.createElement('div'); box.className = 'mm-box';
    var h = d.createElement('div'); h.className = 'mm-h';
    var t = d.createElement('strong'); t.textContent = 'Biblioteca de imágenes';
    var x = d.createElement('button'); x.type = 'button'; x.className = 'b b-s b-g'; x.textContent = 'Cerrar'; x.addEventListener('click', close);
    h.appendChild(t); h.appendChild(x);
    var g = d.createElement('div'); g.className = 'mm-g'; g.id = 'mm-g';
    var f = d.createElement('div'); f.className = 'mm-f';
    var ok = d.createElement('button'); ok.type = 'button'; ok.className = 'b b-p'; ok.id = 'mm-ok'; ok.textContent = 'Aceptar'; ok.addEventListener('click', function () { var p = picked.slice(); close(); if (onPick) { onPick(p); } });
    f.appendChild(ok);
    box.appendChild(h); box.appendChild(g); box.appendChild(f); modal.appendChild(box); d.body.appendChild(modal);
    modal.addEventListener('click', function (e) { if (e.target === modal) { close(); } });
    d.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('on')) { close(); } });
  }
  function close() { modal.classList.remove('on'); }
  function openPicker(isMulti, cb) {
    if (!modal) { build(); }
    multi = isMulti; onPick = cb; picked = [];
    var g = $('#mm-g'); g.textContent = '';
    data.forEach(function (m) {
      var b = d.createElement('button'); b.type = 'button'; b.className = 'mm-i'; b.title = m.n;
      var im = d.createElement('img'); im.src = m.t; im.alt = m.a || m.n; im.loading = 'lazy'; b.appendChild(im);
      b.addEventListener('click', function () {
        var i = picked.indexOf(m.id);
        if (!multi) { $$('.mm-i', g).forEach(function (n) { n.classList.remove('sel'); }); picked = [m.id]; b.classList.add('sel'); return; }
        if (i >= 0) { picked.splice(i, 1); b.classList.remove('sel'); } else { picked.push(m.id); b.classList.add('sel'); }
      });
      g.appendChild(b);
    });
    modal.classList.add('on');
  }
  function thumbOf(id) { for (var i = 0; i < data.length; i++) { if (data[i].id === id) { return data[i].t; } } return ''; }

  $$('[data-mp]').forEach(function (mp) {
    var inp = $('[data-mp-in]', mp), img = $('[data-mp-img]', mp);
    $('[data-mp-open]', mp).addEventListener('click', function () {
      openPicker(false, function (p) { if (p.length) { inp.value = p[0]; img.src = thumbOf(p[0]); img.hidden = false; } });
    });
    $('[data-mp-clear]', mp).addEventListener('click', function () { inp.value = '0'; img.hidden = true; });
  });

  /* galería: agregar, mover y quitar */
  $$('[data-gal]').forEach(function (g) {
    var list = $('[data-gal-list]', g), name = g.getAttribute('data-name');
    function item(id) {
      var w = d.createElement('div'); w.className = 'gi'; w.setAttribute('data-gi', '1');
      var im = d.createElement('img'); im.src = thumbOf(id); im.alt = ''; w.appendChild(im);
      var h = d.createElement('input'); h.type = 'hidden'; h.name = name; h.value = String(id); w.appendChild(h);
      var b = d.createElement('span'); b.className = 'gi-b';
      [['data-gl', '←', ''], ['data-gr', '→', ''], ['data-gx', '×', ' b-d']].forEach(function (a) {
        var x = d.createElement('button'); x.type = 'button'; x.className = 'b b-s b-g' + a[2]; x.setAttribute(a[0], '1'); x.textContent = a[1]; b.appendChild(x);
      });
      w.appendChild(b); return w;
    }
    $('[data-gal-add]', g).addEventListener('click', function () { openPicker(true, function (p) { p.forEach(function (id) { list.appendChild(item(id)); }); }); });
    list.addEventListener('click', function (e) {
      var t = e.target, gi = t.closest ? t.closest('[data-gi]') : null; if (!gi) { return; }
      if (t.hasAttribute('data-gx')) { gi.remove(); }
      else if (t.hasAttribute('data-gl') && gi.previousElementSibling) { list.insertBefore(gi, gi.previousElementSibling); }
      else if (t.hasAttribute('data-gr') && gi.nextElementSibling) { list.insertBefore(gi.nextElementSibling, gi); }
    });
  });
})();
