/* Panel de administración — interacciones */
(function () {
  'use strict';
  var doc = document;
  var $  = function (s, c) { return (c || doc).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); };

  /* Menú lateral en móvil */
  var toggle = $('[data-side-toggle]');
  if (toggle) {
    toggle.addEventListener('click', function () { doc.body.classList.toggle('side-open'); });
    doc.addEventListener('click', function (ev) {
      if (doc.body.classList.contains('side-open') && !ev.target.closest('.side') && !ev.target.closest('[data-side-toggle]')) {
        doc.body.classList.remove('side-open');
      }
    });
  }

  /* Apariencia clara / oscura del panel */
  var themeBtn = $('[data-admin-theme-toggle]');
  var root = doc.documentElement;
  try {
    var saved = window.localStorage.getItem('servicom_admin_theme');
    if (saved) { root.setAttribute('data-admin-theme', saved); }
  } catch (e) {}
  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      var next = root.getAttribute('data-admin-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-admin-theme', next);
      try { window.localStorage.setItem('servicom_admin_theme', next); } catch (e) {}
    });
  }

  /* Confirmación antes de acciones destructivas */
  doc.addEventListener('click', function (ev) {
    var el = ev.target.closest ? ev.target.closest('[data-confirm]') : null;
    if (!el) { return; }
    if (!window.confirm(el.getAttribute('data-confirm'))) { ev.preventDefault(); ev.stopPropagation(); }
  }, true);

  /* Biblioteca de imágenes */
  var mediaDlg = $('#dlg-media');
  var mediaTarget = null;
  $$('[data-media-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      mediaTarget = btn.closest('[data-media-field]');
      if (mediaDlg && mediaDlg.showModal) { mediaDlg.showModal(); }
    });
  });
  if (mediaDlg) {
    $$('.media-item', mediaDlg).forEach(function (item) {
      item.addEventListener('click', function () {
        if (!mediaTarget) { return; }
        var path = item.getAttribute('data-path');
        var input = $('[data-media-input]', mediaTarget);
        var prev  = $('[data-media-preview]', mediaTarget);
        if (input) { input.value = path; }
        if (prev) { prev.src = (window.ADMIN_BASE || '/') + path; prev.hidden = false; }
        mediaDlg.close();
      });
    });
  }
  $$('[data-media-clear]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var f = btn.closest('[data-media-field]');
      var input = $('[data-media-input]', f);
      var prev  = $('[data-media-preview]', f);
      if (input) { input.value = ''; }
      if (prev) { prev.hidden = true; prev.removeAttribute('src'); }
    });
  });
  /* Vista previa inmediata al elegir un archivo nuevo */
  $$('[data-media-field] input[type="file"]').forEach(function (input) {
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) { return; }
      var f = input.closest('[data-media-field]');
      var prev = $('[data-media-preview]', f);
      if (prev) { prev.src = URL.createObjectURL(file); prev.hidden = false; }
    });
  });

  /* Selector de iconos */
  var iconDlg = $('#dlg-icons');
  var iconTarget = null;
  $$('[data-icon-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      iconTarget = btn.closest('[data-icon-field]');
      if (iconDlg && iconDlg.showModal) { iconDlg.showModal(); }
    });
  });
  if (iconDlg) {
    $$('.icon-opt', iconDlg).forEach(function (opt) {
      opt.addEventListener('click', function () {
        if (!iconTarget) { return; }
        var name = opt.getAttribute('data-icon');
        var input = $('[data-icon-input]', iconTarget);
        var prev  = $('[data-icon-preview]', iconTarget);
        var lbl   = $('[data-icon-name]', iconTarget);
        if (input) { input.value = name; }
        if (prev) { prev.innerHTML = opt.querySelector('svg').outerHTML; }
        if (lbl) { lbl.textContent = name; }
        iconDlg.close();
      });
    });
  }

  /* Cerrar diálogos */
  $$('dialog [data-close]').forEach(function (b) {
    b.addEventListener('click', function () { b.closest('dialog').close(); });
  });
  $$('dialog').forEach(function (d) {
    d.addEventListener('click', function (ev) { if (ev.target === d) { d.close(); } });
  });

  /* Contador de caracteres para campos SEO */
  $$('textarea[name="meta_description"], input[name="meta_title"], textarea[name="seo_default_description"], input[name="seo_default_title"]').forEach(function (el) {
    var ideal = el.name.indexOf('description') !== -1 ? [140, 160] : [45, 60];
    var out = doc.createElement('span');
    out.className = 'hint';
    el.parentNode.appendChild(out);
    var update = function () {
      var n = el.value.length;
      var state = n === 0 ? 'vacío' : (n < ideal[0] ? 'corto' : (n > ideal[1] ? 'largo' : 'óptimo'));
      var color = state === 'óptimo' ? 'var(--a-ok)' : (state === 'largo' ? 'var(--a-danger)' : 'var(--a-warn)');
      out.innerHTML = 'Caracteres: <b style="color:' + color + '">' + n + '</b> — ' + state + ' (ideal ' + ideal[0] + '–' + ideal[1] + ')';
    };
    el.addEventListener('input', update);
    update();
  });

  /* Aviso al salir con cambios sin guardar */
  $$('form.panel').forEach(function (form) {
    var dirty = false;
    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (ev) {
      if (dirty) { ev.preventDefault(); ev.returnValue = ''; }
    });
  });
})();
