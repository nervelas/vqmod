/* =====================================================================
   MenúGold · Panel de administración
   Utilidades compartidas: avisos, peticiones, modales, orden arrastrable,
   subida de imágenes, confirmaciones y modo oscuro.
   ===================================================================== */
(function () {
  'use strict';

  var P = window.MGP || {};

  function $(s, c) { return (c || document).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function money(n) {
    return (P.simbolo || 'Q') + (Math.round(Number(n) * 100) / 100)
      .toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function url(p) { return (P.base || '') + '/' + String(p).replace(/^\//, ''); }

  var IC = {
    ok:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/></svg>',
    error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/></svg>',
    aviso: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 2 18a2 2 0 0 0 1.7 3h16.6A2 2 0 0 0 22 18L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>'
  };

  // ------------------------------------------------------------- avisos
  function avisar(texto, tipo) {
    var cont = $('#tostadasP');
    if (!cont) { return; }
    tipo = tipo || 'ok';
    var el = document.createElement('div');
    el.className = 'tostada-p tostada-p--' + tipo;
    el.innerHTML = (IC[tipo] || IC.ok) + '<span>' + esc(texto) + '</span>';
    cont.appendChild(el);
    setTimeout(function () {
      el.classList.add('saliendo');
      setTimeout(function () { el.remove(); }, 200);
    }, 3400);
  }

  // ------------------------------------------------------------- red
  function pedir(ruta, datos, opciones) {
    opciones = opciones || {};
    var o = {
      method: opciones.metodo || (datos ? 'POST' : 'GET'),
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      credentials: 'same-origin'
    };
    if (datos instanceof FormData) {
      datos.append('_token', P.token);
      o.body = datos;
    } else if (datos) {
      o.headers['Content-Type'] = 'application/json';
      datos._token = P.token;
      o.body = JSON.stringify(datos);
    }
    return fetch(url(ruta), o).then(function (r) {
      if (r.status === 401) { window.location.href = url('ingresar'); return { ok: false }; }
      return r.json().catch(function () { return { ok: false, error: 'Respuesta no válida del servidor.' }; });
    }).catch(function () {
      return { ok: false, error: 'Sin conexión con el servidor.' };
    });
  }

  // ------------------------------------------------------------- confirmar
  var confResolver = null;
  function confirmar(texto, titulo, textoBoton) {
    var m = $('#modalConfirmar');
    if (!m) return Promise.resolve(window.confirm(texto));
    $('#confTexto').textContent = texto;
    $('#confTitulo').textContent = titulo || 'Confirmar';
    $('#confOk').textContent = textoBoton || 'Sí, continuar';
    m.classList.add('abierto');
    document.body.style.overflow = 'hidden';
    return new Promise(function (res) { confResolver = res; });
  }
  function cerrarConfirmar(valor) {
    var m = $('#modalConfirmar');
    if (m) m.classList.remove('abierto');
    document.body.style.overflow = '';
    if (confResolver) { confResolver(valor); confResolver = null; }
  }
  document.addEventListener('click', function (ev) {
    if (ev.target.closest('#confOk')) { cerrarConfirmar(true); return; }
    if (ev.target.closest('#modalConfirmar [data-cerrar-modal]')) { cerrarConfirmar(false); return; }
  });

  // ------------------------------------------------------------- modales
  function abrirModal(id) {
    var m = document.getElementById(id);
    if (!m) return null;
    m.classList.add('abierto');
    document.body.style.overflow = 'hidden';
    var f = m.querySelector('input:not([type=hidden]):not([disabled]), select, textarea, button');
    if (f) setTimeout(function () { f.focus(); }, 60);
    return m;
  }
  function cerrarModal(id) {
    var m = id ? document.getElementById(id) : $('.modal-p.abierto');
    if (!m) return;
    m.classList.remove('abierto');
    if (!$('.modal-p.abierto')) document.body.style.overflow = '';
  }

  document.addEventListener('click', function (ev) {
    var ab = ev.target.closest('[data-modal]');
    if (ab) {
      ev.preventDefault();
      var m = abrirModal(ab.dataset.modal);
      if (m && ab.dataset.rellenar) {
        try { rellenarFormulario(m, JSON.parse(ab.dataset.rellenar)); } catch (e) {}
      }
      if (m && ab.dataset.limpiar === '1') limpiarFormulario(m);
      if (m && ab.dataset.titulo) {
        var t = m.querySelector('.modal-p__titulo');
        if (t) t.textContent = ab.dataset.titulo;
      }
      return;
    }
    if (ev.target.closest('[data-cerrar-modal]')) {
      var mm = ev.target.closest('.modal-p');
      if (mm && mm.id !== 'modalConfirmar') { cerrarModal(mm.id); }
      return;
    }
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
      var abierto = $('.modal-p.abierto');
      if (abierto && abierto.id === 'modalConfirmar') cerrarConfirmar(false);
      else if (abierto) cerrarModal(abierto.id);
    }
  });

  function rellenarFormulario(caja, datos) {
    Object.keys(datos).forEach(function (k) {
      var campos = $$('[name="' + k + '"], [name="' + k + '[]"]', caja);
      if (!campos.length) return;
      var v = datos[k];
      campos.forEach(function (c) {
        if (c.type === 'checkbox') {
          if (Array.isArray(v)) c.checked = v.map(String).indexOf(String(c.value)) >= 0;
          else c.checked = !!v && v !== '0';
        } else if (c.type === 'radio') {
          c.checked = String(c.value) === String(v);
        } else if (c.tagName === 'SELECT' && c.multiple && Array.isArray(v)) {
          $$('option', c).forEach(function (o) { o.selected = v.map(String).indexOf(o.value) >= 0; });
        } else {
          c.value = v == null ? '' : v;
        }
        c.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    // Previsualización de imagen existente
    var prev = caja.querySelector('[data-previa]');
    if (prev) {
      var img = prev.querySelector('img');
      if (img) {
        if (datos._imagen) { img.src = datos._imagen; prev.style.display = ''; }
        else { prev.style.display = 'none'; }
      }
    }
  }

  function limpiarFormulario(caja) {
    $$('form', caja).forEach(function (f) { f.reset(); });
    $$('input[type=hidden][data-limpiable]', caja).forEach(function (i) { i.value = ''; });
    var prev = caja.querySelector('[data-previa]');
    if (prev) prev.style.display = 'none';
  }

  // ------------------------------------------------------------- confirmación en enlaces/botones
  document.addEventListener('click', function (ev) {
    var el = ev.target.closest('[data-confirmar]');
    if (!el || el.dataset.confirmado === '1') return;
    ev.preventDefault();
    ev.stopPropagation();
    confirmar(el.dataset.confirmar, el.dataset.confirmarTitulo || '¿Estás seguro?', el.dataset.confirmarBoton)
      .then(function (ok) {
        if (!ok) return;
        el.dataset.confirmado = '1';
        if (el.tagName === 'A') window.location.href = el.href;
        else if (el.form) el.form.submit();
        else el.click();
        setTimeout(function () { delete el.dataset.confirmado; }, 1500);
      });
  }, true);

  // ------------------------------------------------------------- barra lateral
  var body = document.body;
  var colapsar = $('#colapsarMenu');
  var abrirM = $('#abrirMenu');

  function esEscritorio() { return window.innerWidth >= 900; }

  function aplicarLateral() {
    if (esEscritorio()) {
      body.classList.remove('menu-abierto');
      try {
        if (localStorage.getItem('mg_lateral') === 'mini') body.classList.add('lateral-mini');
        else body.classList.remove('lateral-mini');
      } catch (e) {}
    } else {
      body.classList.remove('lateral-mini');
    }
  }
  if (colapsar) colapsar.addEventListener('click', function () {
    if (esEscritorio()) {
      body.classList.toggle('lateral-mini');
      try { localStorage.setItem('mg_lateral', body.classList.contains('lateral-mini') ? 'mini' : 'full'); } catch (e) {}
    } else {
      body.classList.toggle('menu-abierto');
      colapsar.setAttribute('aria-expanded', String(body.classList.contains('menu-abierto')));
    }
  });
  var velo = $('#veloLateral');
  if (velo) velo.addEventListener('click', function () { body.classList.remove('menu-abierto'); });
  window.addEventListener('resize', aplicarLateral);
  aplicarLateral();

  // ------------------------------------------------------------- modo oscuro
  var btnTema = $('#cambiarTema');
  if (btnTema) btnTema.addEventListener('click', function () {
    var actual = body.dataset.modo || 'auto';
    var oscuroAhora = actual === 'oscuro' ||
      (actual === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    var nuevo = oscuroAhora ? 'claro' : 'oscuro';
    body.dataset.modo = nuevo;
    pedir('panel/tema', { modo: nuevo });
  });

  // ------------------------------------------------------------- subida de imagen
  document.addEventListener('change', function (ev) {
    var inp = ev.target;
    if (!inp.matches('input[type=file][data-previsualizar]')) return;
    var destino = document.querySelector(inp.dataset.previsualizar);
    if (!destino || !inp.files || !inp.files[0]) return;
    var f = inp.files[0];
    if (f.size > 12 * 1024 * 1024) {
      avisar('La imagen supera los 12 MB permitidos.', 'error');
      inp.value = '';
      return;
    }
    var img = destino.querySelector('img');
    if (img) {
      img.src = URL.createObjectURL(f);
      destino.style.display = '';
    }
  });

  document.addEventListener('click', function (ev) {
    var q = ev.target.closest('[data-quitar-previa]');
    if (!q) return;
    var caja = document.querySelector(q.dataset.quitarPrevia);
    if (caja) caja.style.display = 'none';
    var inp = document.querySelector(q.dataset.campo || '');
    if (inp) inp.value = '';
    var marca = document.querySelector(q.dataset.marca || '');
    if (marca) marca.value = '1';
  });

  // Arrastrar y soltar sobre la caja de subida
  $$('.subir-foto').forEach(function (caja) {
    ['dragenter', 'dragover'].forEach(function (e) {
      caja.addEventListener(e, function (ev) { ev.preventDefault(); caja.classList.add('encima'); });
    });
    ['dragleave', 'drop'].forEach(function (e) {
      caja.addEventListener(e, function () { caja.classList.remove('encima'); });
    });
  });

  // ------------------------------------------------------------- orden arrastrable
  function hacerOrdenable(lista, alSoltar) {
    var arrastrado = null;
    $$('[draggable=true]', lista).forEach(function (el) {
      el.addEventListener('dragstart', function () { arrastrado = el; el.classList.add('arrastrando'); });
      el.addEventListener('dragend', function () {
        el.classList.remove('arrastrando');
        arrastrado = null;
        if (alSoltar) alSoltar(ids(lista));
      });
      el.addEventListener('dragover', function (ev) {
        ev.preventDefault();
        if (!arrastrado || arrastrado === el) return;
        var r = el.getBoundingClientRect();
        var despues = (ev.clientY - r.top) > r.height / 2;
        lista.insertBefore(arrastrado, despues ? el.nextSibling : el);
      });
    });
    // Alternativa accesible con teclado
    $$('[data-subir]', lista).forEach(function (b) {
      b.addEventListener('click', function () {
        var li = b.closest('[draggable=true]');
        if (li && li.previousElementSibling) {
          lista.insertBefore(li, li.previousElementSibling);
          if (alSoltar) alSoltar(ids(lista));
        }
      });
    });
    $$('[data-bajar]', lista).forEach(function (b) {
      b.addEventListener('click', function () {
        var li = b.closest('[draggable=true]');
        if (li && li.nextElementSibling) {
          lista.insertBefore(li.nextElementSibling, li);
          if (alSoltar) alSoltar(ids(lista));
        }
      });
    });
  }
  function ids(lista) {
    return $$('[draggable=true]', lista).map(function (el) { return el.dataset.id; });
  }

  // ------------------------------------------------------------- envío AJAX de formularios
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!f.matches('form[data-ajax]')) return;
    ev.preventDefault();
    var btn = f.querySelector('[type=submit]');
    var textoOriginal = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="cargador" style="width:16px;height:16px"></span>'; }

    pedir(f.getAttribute('action') || location.pathname, new FormData(f)).then(function (res) {
      if (btn) { btn.disabled = false; btn.innerHTML = textoOriginal; }
      if (res.ok) {
        avisar(res.mensaje || 'Guardado', 'ok');
        if (res.recargar !== false) setTimeout(function () { location.reload(); }, 550);
        else cerrarModal();
      } else {
        avisar(res.error || 'No se pudo guardar.', 'error');
      }
    });
  });

  // ------------------------------------------------------------- interruptores rápidos
  document.addEventListener('change', function (ev) {
    var sw = ev.target;
    if (!sw.matches('[data-alternar]')) return;
    var datos = { id: Number(sw.dataset.id), valor: sw.checked ? 1 : 0 };
    if (sw.dataset.campo) datos.campo = sw.dataset.campo;
    pedir(sw.dataset.alternar, datos).then(function (res) {
      if (res.ok) avisar(res.mensaje || 'Actualizado', 'ok');
      else { sw.checked = !sw.checked; avisar(res.error || 'No se pudo actualizar.', 'error'); }
    });
  });

  // ---------------------------------------------- respaldo para navegadores sin :has()
  var soportaHas = (function () {
    try { return CSS.supports('selector(:has(*))'); } catch (e) { return false; }
  })();
  function marcarElegidas() {
    if (soportaHas) return;
    $$('.pastilla-sel, .tema-opcion').forEach(function (l) {
      var i = l.querySelector('input');
      if (i) l.classList.toggle('elegida', i.checked);
    });
  }
  document.addEventListener('change', marcarElegidas);
  document.addEventListener('DOMContentLoaded', marcarElegidas);
  marcarElegidas();

  // ------------------------------------------------------------- utilidades públicas
  window.MGPanel = {
    avisar: avisar, pedir: pedir, confirmar: confirmar,
    abrirModal: abrirModal, cerrarModal: cerrarModal,
    money: money, esc: esc, url: url, $: $, $$: $$,
    ordenable: hacerOrdenable, rellenar: rellenarFormulario
  };

  // ------------------------------------------------------------- atajos
  document.addEventListener('keydown', function (ev) {
    if ((ev.ctrlKey || ev.metaKey) && ev.key === 'k') {
      var b = $('#buscarPanel');
      if (b) { ev.preventDefault(); b.focus(); b.select(); }
    }
  });

  // Marca activo el enlace del menú móvil por coincidencia exacta
  var ruta = location.pathname.replace(/\/+$/, '');
  $$('.nav-movil a').forEach(function (a) {
    var href = a.getAttribute('href').replace(P.base, '').replace(/\/+$/, '');
    if (href && ruta === href) a.setAttribute('aria-current', 'page');
  });
})();
