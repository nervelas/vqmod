/* S5: utilidades comunes (API con CSRF, subida con progreso, compresión de imágenes, DOM seguro, pago) */
(function () {
  'use strict';
  var S5 = window.S5 = {};
  var NET = 'Sin conexión. Revisa tu internet e inténtalo de nuevo.';

  S5.csrf = function () { var m = document.querySelector('meta[name=csrf]'); return m ? m.content : ''; };

  S5.api = function (method, url, body) {
    var o = { method: method, credentials: 'same-origin', headers: { 'X-CSRF': S5.csrf(), 'Accept': 'application/json' } };
    if (body !== undefined) { o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(body); }
    return fetch(url, o).then(function (r) {
      return r.json().then(function (j) { if (!j || typeof j !== 'object') j = { ok: false, error: 'Respuesta inesperada del servidor.' }; j._status = r.status; return j; },
        function () { return { ok: false, error: 'Respuesta inesperada del servidor. Inténtalo de nuevo.', _status: r.status }; });
    }, function () { return { ok: false, error: NET, _net: true }; });
  };

  S5.upload = function (token, tipo, file, prog) {
    return new Promise(function (res) {
      var fd = new FormData(), x = new XMLHttpRequest();
      fd.append('tipo', tipo); fd.append('archivo', file, file.name || 'archivo');
      x.open('POST', '/api/borrador/' + encodeURIComponent(token) + '/subir');
      x.setRequestHeader('X-CSRF', S5.csrf()); x.setRequestHeader('Accept', 'application/json');
      if (prog) x.upload.onprogress = function (e) { if (e.lengthComputable) prog(e.loaded / e.total); };
      x.onload = function () { var j; try { j = JSON.parse(x.responseText); } catch (_) { j = { ok: false, error: 'No pudimos subir el archivo. Inténtalo de nuevo.' }; } res(j); };
      x.onerror = function () { res({ ok: false, error: NET, _net: true }); };
      x.send(fd);
    });
  };

  var webp;
  function hasWebp() {
    if (webp === undefined) { try { webp = document.createElement('canvas').toDataURL('image/webp').indexOf('data:image/webp') === 0; } catch (_) { webp = false; } }
    return webp;
  }
  /* Reduce a máx. 1600 px y recomprime. opts.logo => conserva PNG con transparencia. */
  S5.compress = function (file, opts) {
    opts = opts || {};
    return new Promise(function (res) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return res(file);
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function () {
        URL.revokeObjectURL(url);
        var w = img.naturalWidth, h = img.naturalHeight, max = opts.max || 1600, s = Math.min(1, max / Math.max(w, h));
        if (!w || !h || (s === 1 && file.size < 260 * 1024)) return res(file);
        var c = document.createElement('canvas'); c.width = Math.round(w * s); c.height = Math.round(h * s);
        var g = c.getContext('2d'), type;
        if (opts.logo && file.type === 'image/png') type = 'image/png';
        else if (hasWebp()) type = 'image/webp';
        else { type = 'image/jpeg'; g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height); }
        g.drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(function (b) {
          if (!b || (s === 1 && b.size >= file.size)) return res(file);
          var ext = type === 'image/png' ? '.png' : type === 'image/webp' ? '.webp' : '.jpg';
          res(new File([b], (file.name || 'foto').replace(/\.[^.]+$/, '') + ext, { type: type }));
        }, type, opts.q || 0.82);
      };
      img.onerror = function () { URL.revokeObjectURL(url); res(file); };
      img.src = url;
    });
  };

  /* DOM seguro: nunca innerHTML con datos; textos vía textContent. */
  S5.h = function (tag, attrs, kids) {
    var n = document.createElement(tag), k;
    if (attrs) for (k in attrs) {
      var v = attrs[k];
      if (v === null || v === undefined || v === false) continue;
      if (k === 'class') n.className = v;
      else if (k === 'text') n.textContent = v;
      else if (k.slice(0, 2) === 'on' && typeof v === 'function') n.addEventListener(k.slice(2), v);
      else n.setAttribute(k, v === true ? '' : v);
    }
    if (kids) (Array.isArray(kids) ? kids : [kids]).forEach(function (c) {
      if (c === null || c === undefined || c === false) return;
      n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return n;
  };
  S5.debounce = function (fn, ms) { var t; var f = function () { var a = arguments, s = this; clearTimeout(t); t = setTimeout(function () { fn.apply(s, a); }, ms); }; f.flush = function () { clearTimeout(t); }; return f; };
  S5.bytes = function (n) { return n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; };
  S5.copy = function (txt) {
    if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(txt).then(function () { return true; }, function () { return false; });
    return new Promise(function (res) {
      var t = document.createElement('textarea'); t.value = txt; t.setAttribute('readonly', ''); t.className = 'sr'; document.body.appendChild(t); t.select();
      var ok = false; try { ok = document.execCommand('copy'); } catch (_) {} t.remove(); res(ok);
    });
  };
  S5.boot = function () { var n = document.getElementById('s5-boot'); try { return n ? JSON.parse(n.textContent) : {}; } catch (_) { return {}; } };

  /* Pago: raíz con [data-pay]. cb(ok) al terminar. */
  S5.initPay = function (root, token, cb) {
    var q = function (s) { return root.querySelector(s); };
    var nit = q('[name=nombre_nit]'), file = q('[type=file]'), btn = q('[data-pay-submit]'), err = q('[data-pay-err]'),
      st = q('[data-pay-status]'), info = q('[data-pay-file]'), done = q('[data-pay-done]'), form = q('[data-pay-form]');
    var picked = null, busy = false;
    function msg(t) { err.textContent = t || ''; err.classList.toggle('on', !!t); }
    function sync() { btn.disabled = busy || !picked || !nit.value.trim(); }
    nit.addEventListener('input', function () { sync(); msg(''); });
    var cp = q('[data-copy-acc]');
    if (cp) cp.addEventListener('click', function () { S5.copy(cp.getAttribute('data-copy-acc')).then(function (ok) { cp.textContent = ok ? 'Copiado ✓' : 'Copia manual'; setTimeout(function () { cp.textContent = 'Copiar cuenta'; }, 1800); }); });
    file.addEventListener('change', function () {
      var f = file.files[0]; picked = null; info.textContent = ''; msg('');
      if (!f) return sync();
      var okT = /^(image\/(jpeg|png)|application\/pdf)$/.test(f.type) || /\.(jpe?g|png|pdf)$/i.test(f.name);
      if (!okT) { msg('Sube tu comprobante como foto (JPG o PNG) o PDF.'); file.value = ''; return sync(); }
      S5.compress(f, { max: 2000 }).then(function (g) {
        if (g.size > 5 * 1048576) { msg('El archivo pesa ' + S5.bytes(g.size) + ' y el máximo es 5 MB.'); file.value = ''; return sync(); }
        picked = g; info.textContent = f.name + ' · ' + S5.bytes(g.size); sync();
      });
    });
    btn.addEventListener('click', function () {
      if (busy || !picked) return;
      var v = nit.value.trim();
      if (v.length < 2) { msg('Escribe el nombre o NIT para tu recibo.'); nit.focus(); return; }
      busy = true; sync(); msg(''); st.textContent = 'Subiendo comprobante…';
      S5.upload(token, 'comprobante', picked).then(function (r) {
        if (!r.ok) { throw new Error(r.error || 'No pudimos subir el comprobante.'); }
        st.textContent = 'Enviando…';
        return S5.api('POST', '/api/borrador/' + encodeURIComponent(token) + '/pagar', { nombre_nit: v, comprobante: r.id });
      }).then(function (r) {
        if (!r.ok) throw new Error(r.error || 'No pudimos registrar tu pago.');
        st.textContent = ''; form.hidden = true; done.hidden = false; done.setAttribute('tabindex', '-1'); done.focus();
        if (cb) cb(true);
      }).catch(function (e) { busy = false; st.textContent = ''; msg(e.message); sync(); });
    });
    sync();
  };
})();
