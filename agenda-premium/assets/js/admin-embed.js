/* Insertar y compartir: genera el código, la vista previa y el código QR descargable. */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  function attr(s) { return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

  document.addEventListener('DOMContentLoaded', function () {
    var root = $('[data-p2-embed]');
    var bootEl = $('#embed-boot');
    if (!root || !bootEl) { return; }
    var boot = JSON.parse(bootEl.textContent);
    var evSel = $('#em-event');
    var hostSel = $('#em-host');
    var colorIn = $('#em-color');
    var labelIn = $('#em-label');
    var targetIn = $('#em-target');
    var code = $('#em-code');
    var stage = $('[data-stage]');
    var desc = $('[data-mode-desc]');
    var tabs = $$('[data-mode]', root);
    var mode = 'link';
    var DESC = {
      link: 'Un enlace limpio para WhatsApp, redes sociales, firmas de correo o tu biografía.',
      inline: 'La página de reserva aparece dentro de tu sitio web, adaptada al ancho disponible.',
      popup: 'Un botón en tu sitio que abre la reserva en una ventana emergente, sin sacar al cliente de tu página.',
      float: 'Un botón fijo en una esquina de todas las páginas donde pegues el código.',
      wp: 'Pega el shortcode en cualquier página o entrada de WordPress.',
      qr: 'Un código para imprimir o mostrar en pantalla.'
    };

    function directUrl() {
      var host = hostSel ? hostSel.value : '';
      var ev = evSel.value;
      if (!ev) { return host ? boot.base + '/h/' + host : boot.base + '/'; }
      return boot.base + '/e/' + ev + (host ? '?host=' + encodeURIComponent(host) : '');
    }
    function embedUrl() { return directUrl() + (directUrl().indexOf('?') > -1 ? '&' : '?') + 'embed=1'; }

    function scriptTag(m) {
      var a = ['src="' + attr(boot.base + '/assets/js/embed.js') + '"'];
      if (evSel.value) { a.push('data-event="' + attr(evSel.value) + '"'); }
      a.push('data-mode="' + m + '"');
      if (m === 'popup' || m === 'float') { a.push('data-label="' + attr(labelIn.value || 'Reservar cita') + '"'); }
      a.push('data-color="' + attr(colorIn.value) + '"');
      if (m === 'inline') { a.push('data-target="' + attr(targetIn.value || '#agenda-premium') + '"'); }
      if (hostSel && hostSel.value) { a.push('data-host="' + attr(hostSel.value) + '"'); }
      a.push('async');
      return '<script ' + a.join(' ') + '></script>';
    }
    function shortcode() {
      var a = ['[agenda_premium'];
      if (evSel.value) { a.push(' evento="' + evSel.value + '"'); }
      a.push(' modo="' + (mode === 'wp' ? 'inline' : mode) + '"');
      a.push(' texto="' + (labelIn.value || 'Reservar cita').replace(/"/g, '') + '"');
      a.push(' color="' + colorIn.value + '"');
      if (hostSel && hostSel.value) { a.push(' anfitrion="' + hostSel.value + '"'); }
      return a.join('') + ']';
    }
    function wpPhp() {
      return "add_shortcode('agenda_premium', function ($a) {\n" +
        "    $a = shortcode_atts(array('evento' => '', 'modo' => 'inline', 'texto' => 'Reservar cita', 'color' => '" + colorIn.value + "', 'anfitrion' => ''), $a);\n" +
        "    $id = 'agenda-' . wp_rand();\n" +
        "    $attrs = ' data-mode=\"' . esc_attr($a['modo']) . '\" data-color=\"' . esc_attr($a['color']) . '\" data-label=\"' . esc_attr($a['texto']) . '\"';\n" +
        "    if ($a['evento'] !== '') { $attrs .= ' data-event=\"' . esc_attr($a['evento']) . '\"'; }\n" +
        "    if ($a['anfitrion'] !== '') { $attrs .= ' data-host=\"' . esc_attr($a['anfitrion']) . '\"'; }\n" +
        "    $target = $a['modo'] === 'inline' ? '<div id=\"' . esc_attr($id) . '\"></div>' : '';\n" +
        "    return $target . '<script src=\"" + boot.base + "/assets/js/embed.js\"' . $attrs . ' data-target=\"#' . esc_attr($id) . '\" async></script>';\n" +
        "});";
    }

    function setMode(m) {
      mode = m;
      tabs.forEach(function (t) {
        var on = t.getAttribute('data-mode') === m;
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
      });
      render();
    }
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { setMode(t.getAttribute('data-mode')); });
      t.addEventListener('keydown', function (ev) {
        var n = null;
        if (ev.key === 'ArrowRight') { n = (i + 1) % tabs.length; }
        if (ev.key === 'ArrowLeft') { n = (i + tabs.length - 1) % tabs.length; }
        if (n !== null) { ev.preventDefault(); tabs[n].focus(); setMode(tabs[n].getAttribute('data-mode')); }
      });
    });

    /* ------------------------------------------------------------ QR */
    var qr = null;
    function makeQr(text) {
      try {
        var q = window.qrcode(0, 'M');
        q.addData(text);
        q.make();
        return q;
      } catch (e) { return null; }
    }
    function drawQr(canvas, px) {
      var n = qr.getModuleCount();
      var quiet = 4;
      var cell = Math.max(1, Math.floor(px / (n + quiet * 2)));
      var size = cell * (n + quiet * 2);
      canvas.width = size;
      canvas.height = size;
      var c = canvas.getContext('2d');
      c.fillStyle = '#ffffff';
      c.fillRect(0, 0, size, size);
      c.fillStyle = '#06080D';
      for (var r = 0; r < n; r++) {
        for (var col = 0; col < n; col++) {
          if (qr.isDark(r, col)) { c.fillRect((col + quiet) * cell, (r + quiet) * cell, cell, cell); }
        }
      }
    }
    function svgQr() {
      var n = qr.getModuleCount();
      var quiet = 4;
      var d = '';
      for (var r = 0; r < n; r++) {
        for (var c = 0; c < n; c++) {
          if (qr.isDark(r, c)) { d += 'M' + (c + quiet) + ' ' + (r + quiet) + 'h1v1h-1z'; }
        }
      }
      var s = n + quiet * 2;
      return '<?xml version="1.0" encoding="UTF-8"?>\n<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + s + ' ' + s + '" width="' + s * 10 + '" height="' + s * 10 + '" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#ffffff"/><path d="' + d + '" fill="#06080D"/></svg>';
    }
    function download(name, blob) {
      var a = document.createElement('a');
      var u = URL.createObjectURL(blob);
      a.href = u;
      a.download = name;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout(function () { URL.revokeObjectURL(u); }, 2000);
    }
    function fileBase() { return 'qr-' + (evSel.value || 'agenda'); }
    $('[data-qr-png]', root).addEventListener('click', function () {
      if (!qr) { return; }
      var cv = document.createElement('canvas');
      drawQr(cv, parseInt($('#em-qr-size').value, 10) || 1024);
      cv.toBlob(function (b) { if (b) { download(fileBase() + '.png', b); } }, 'image/png');
    });
    $('[data-qr-svg]', root).addEventListener('click', function () {
      if (qr) { download(fileBase() + '.svg', new Blob([svgQr()], { type: 'image/svg+xml' })); }
    });

    /* ------------------------------------------------------ vista previa */
    function frame(src, h) {
      var f = document.createElement('iframe');
      f.src = src;
      f.title = 'Vista previa de la página de reserva';
      f.loading = 'lazy';
      f.className = 'p2-frame';
      f.style.setProperty('--h', h + 'px');
      return f;
    }
    function button(cls) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'p2-fakebtn ' + cls;
      b.textContent = labelIn.value || 'Reservar cita';
      b.style.setProperty('--bc', colorIn.value);
      return b;
    }
    function renderStage() {
      stage.innerHTML = '';
      if (mode === 'link' || mode === 'qr' || mode === 'wp') {
        var box = document.createElement('div');
        box.className = 'p2-linkprev';
        var t = document.createElement('p');
        t.className = 'mono';
        t.textContent = directUrl();
        var go = document.createElement('a');
        go.className = 'btn btn-outline btn-sm';
        go.href = directUrl();
        go.target = '_blank';
        go.rel = 'noopener';
        go.textContent = 'Abrir página de reserva';
        box.appendChild(t);
        box.appendChild(go);
        stage.appendChild(box);
        if (mode === 'wp') {
          stage.appendChild(frame(embedUrl(), 520));
        }
        return;
      }
      if (mode === 'inline') {
        stage.appendChild(frame(embedUrl(), 560));
        return;
      }
      var page = document.createElement('div');
      page.className = 'p2-fakepage';
      page.innerHTML = '<div class="p2-fakebar"></div><div class="p2-fakeline"></div><div class="p2-fakeline p2-short"></div><div class="p2-fakeline"></div>';
      var b = button(mode === 'float' ? 'is-float' : 'is-inline');
      page.appendChild(b);
      var overlay = document.createElement('div');
      overlay.className = 'p2-fakemodal';
      overlay.hidden = true;
      var close = document.createElement('button');
      close.type = 'button';
      close.className = 'btn btn-ghost btn-sm';
      close.textContent = 'Cerrar vista previa';
      overlay.appendChild(close);
      overlay.appendChild(frame(embedUrl(), 420));
      page.appendChild(overlay);
      b.addEventListener('click', function () { overlay.hidden = false; });
      close.addEventListener('click', function () { overlay.hidden = true; });
      stage.appendChild(page);
    }

    function render() {
      $$('[data-only]', root).forEach(function (n) { n.hidden = n.getAttribute('data-only') !== mode; });
      desc.textContent = DESC[mode];
      var isQr = mode === 'qr';
      $('[data-qr-box]', root).hidden = !isQr;
      $('[data-code-box]', root).hidden = isQr;
      $('[data-wp-extra]', root).hidden = mode !== 'wp';
      var open = $('[data-open-link]', root);
      open.hidden = mode !== 'link';
      open.href = directUrl();
      if (mode === 'link') { code.value = directUrl(); }
      else if (mode === 'wp') { code.value = shortcode(); $('#em-wp-php').value = wpPhp(); }
      else if (mode === 'qr') {
        qr = makeQr(directUrl());
        if (qr) { drawQr($('#em-qr'), 640); }
        $('[data-qr-url]', root).textContent = directUrl();
      } else {
        var tag = scriptTag(mode);
        code.value = (mode === 'inline' ? '<div id="' + (targetIn.value || '#agenda-premium').replace(/^#/, '') + '"></div>\n' : '') + tag;
      }
      renderStage();
    }

    var timer = null;
    function later() { clearTimeout(timer); timer = setTimeout(render, 200); }
    [evSel, hostSel, colorIn, labelIn, targetIn].forEach(function (el) {
      if (!el) { return; }
      el.addEventListener('input', later);
      el.addEventListener('change', later);
    });
    setMode('link');
  });
}());
