/* Agenda Premium · Sistema (Admin4): vista previa de marca, contraste, formularios. Vanilla ES2020, sin dependencias. */
(function () {
  'use strict';
  var d = document;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };

  /* ---------- Contraste WCAG ---------- */
  function lum(hex) {
    var h = hex.replace('#', '');
    var c = [0, 2, 4].map(function (i) {
      var v = parseInt(h.substr(i, 2), 16) / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }
  function contrast(a, b) {
    var la = lum(a), lb = lum(b);
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
  }
  var HEX = /^#[0-9a-fA-F]{6}$/;

  /* ---------- Vista previa de marca ---------- */
  function initBrand() {
    var prev = $('[data-brand-preview]');
    if (!prev) { return; }
    var hero = $('.p4-pv-hero', prev);
    $$('[data-preview-src]').forEach(function (inp) {
      var target = $('[data-preview="' + inp.getAttribute('data-preview-src') + '"]', prev);
      if (!target) { return; }
      inp.addEventListener('input', function () { target.textContent = inp.value; });
    });

    var txt = $('[data-gold-input]'), pick = $('[data-gold-picker]'), info = $('[data-contrast]');
    function paint(hex) {
      prev.style.setProperty('--p4-gold', hex);
      if (hero) { hero.style.setProperty('--p4-gold', hex); }
      if (info) {
        var ratio = contrast(hex, info.getAttribute('data-bg') || '#06080D');
        var v = $('[data-contrast-value]', info), b = $('[data-contrast-badge]', info);
        if (v) { v.textContent = ratio.toFixed(1).replace('.', ',') + ':1'; }
        if (b) {
          var ok = ratio >= 4.5;
          b.textContent = ok ? 'Cumple AA' : 'Bajo para texto';
          b.classList.toggle('badge-ok', ok);
          b.classList.toggle('badge-warn', !ok);
        }
      }
    }
    if (txt) {
      if (HEX.test(txt.value)) { paint(txt.value); }
      txt.addEventListener('input', function () {
        var v = txt.value.trim();
        if (v.charAt(0) !== '#') { v = '#' + v; }
        if (HEX.test(v)) {
          if (pick) { pick.value = v.toLowerCase(); }
          paint(v);
          txt.removeAttribute('aria-invalid');
        } else {
          txt.setAttribute('aria-invalid', 'true');
        }
      });
      txt.addEventListener('blur', function () {
        var v = txt.value.trim();
        if (v && v.charAt(0) !== '#') { txt.value = '#' + v; }
        txt.value = txt.value.toUpperCase();
      });
    }
    if (pick) {
      pick.addEventListener('input', function () {
        if (txt) { txt.value = pick.value.toUpperCase(); txt.removeAttribute('aria-invalid'); }
        paint(pick.value);
      });
    }
    var reset = $('[data-gold-reset]');
    if (reset) {
      reset.addEventListener('click', function () {
        var v = reset.getAttribute('data-default') || '#C9A050';
        if (txt) { txt.value = v; txt.removeAttribute('aria-invalid'); }
        if (pick) { pick.value = v.toLowerCase(); }
        paint(v);
      });
    }

    $$('[data-asset]').forEach(function (box) {
      var input = $('[data-asset-input]', box), img = $('[data-asset-img]', box), none = $('[data-asset-none]', box), frame = $('.p4-asset-box', box);
      if (!input || !img) { return; }
      var slot = box.getAttribute('data-asset');
      var url = null;
      input.addEventListener('change', function () {
        var f = input.files && input.files[0];
        if (url) { URL.revokeObjectURL(url); url = null; }
        if (!f || !/^image\//.test(f.type)) { return; }
        url = URL.createObjectURL(f);
        img.src = url; img.hidden = false; img.alt = 'Vista previa del archivo elegido';
        if (none) { none.hidden = true; }
        if (frame) { frame.classList.remove('is-empty'); }
        if (slot === 'logo') {
          var pl = $('[data-preview-logo]', prev), mark = $('[data-preview-mark]', prev);
          if (pl) { pl.src = url; pl.hidden = false; }
          if (mark) { mark.hidden = true; }
        }
      });
    });
  }

  /* ---------- Grupos que se muestran según una casilla ---------- */
  function initToggles() {
    $$('[data-toggle-group]').forEach(function (cb) {
      var grp = $('[data-group="' + cb.getAttribute('data-toggle-group') + '"]');
      if (!grp) { return; }
      function sync() { grp.hidden = !cb.checked; }
      cb.addEventListener('change', sync);
      sync();
    });
  }

  /* ---------- Contador de caracteres ---------- */
  function initCounters() {
    $$('textarea[data-count-target]').forEach(function (ta) {
      var out = d.getElementById(ta.getAttribute('data-count-target'));
      if (!out) { return; }
      function upd() { out.textContent = ta.value.length.toLocaleString('es-GT') + ' caracteres'; }
      ta.addEventListener('input', upd);
      upd();
    });
  }

  /* ---------- Importación: confirmación fuerte al reemplazar ---------- */
  function initImport() {
    var form = $('[data-import-form]');
    if (!form) { return; }
    var box = $('[data-import-confirm]', form), input = box ? $('input', box) : null;
    function sync() {
      var rep = $('[data-import-mode]:checked', form);
      var on = !!rep && rep.value === 'replace';
      if (box) { box.hidden = !on; }
      if (input) { input.required = on; }
    }
    $$('[data-import-mode]', form).forEach(function (r) { r.addEventListener('change', sync); });
    sync();
    var prev = d.getElementById('vista-previa');
    if (prev && prev.scrollIntoView) { prev.scrollIntoView({ block: 'start' }); }
  }

  /* ---------- Aviso de cambios sin guardar y botón ocupado ---------- */
  function initForms() {
    var dirty = false, submitting = false;
    $$('[data-settings-form]').forEach(function (f) {
      f.addEventListener('input', function () { dirty = true; });
      f.addEventListener('change', function () { dirty = true; });
      f.addEventListener('submit', function () { submitting = true; });
    });
    window.addEventListener('beforeunload', function (e) {
      if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; }
    });
    d.addEventListener('submit', function (e) {
      var f = e.target;
      if (!f || !f.hasAttribute || !f.hasAttribute('data-busy')) { return; }
      var btn = $('button[type=submit]', f);
      if (btn && !btn.classList.contains('is-loading')) { btn.classList.add('is-loading'); btn.setAttribute('aria-busy', 'true'); }
    });
  }

  function init() { initBrand(); initToggles(); initCounters(); initImport(); initForms(); }
  if (d.readyState === 'loading') { d.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
