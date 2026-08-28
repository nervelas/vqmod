/* =====================================================================
   MenúGold · Menú del cliente
   Carrito, ficha de platillo con modificadores, envío de pedido y
   seguimiento en vivo. JavaScript vanilla ES2020, sin dependencias.
   ===================================================================== */
(function () {
  'use strict';

  var MG = window.MG || {};
  var LLAVE = 'mg_carrito_' + (MG.slug || 'x');
  var T = MG.textos || {};

  // ------------------------------------------------------------- utilidades
  function $(s, ctx) { return (ctx || document).querySelector(s); }
  function $$(s, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(s)); }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function money(n) {
    return (MG.simbolo || 'Q') + (Math.round(Number(n) * 100) / 100).toLocaleString('es-GT', {
      minimumFractionDigits: 2, maximumFractionDigits: 2
    });
  }
  function url(p) { return (MG.base || '') + '/' + String(p).replace(/^\//, ''); }

  var ICONOS = {
    ok:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/></svg>',
    error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/></svg>',
    aviso: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 2 18a2 2 0 0 0 1.7 3h16.6A2 2 0 0 0 22 18L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>',
    menos: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14"/></svg>',
    mas:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m20 6-11 11-5-5"/></svg>',
    carro: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.4 12.1a2 2 0 0 0 2 1.6h8.5a2 2 0 0 0 2-1.6L22 7H6"/></svg>',
    mesa:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h18M5 8V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2M7 8v12M17 8v12"/></svg>',
    bolsa: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 4 6v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6l-2-4z"/><path d="M4 6h16M16 10a4 4 0 0 1-8 0"/></svg>',
    moto:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3"/><circle cx="18.5" cy="17.5" r="3"/><path d="M5.5 17.5h5l3-9h3M12 8.5h5l1.5 9M15 5h3"/></svg>',
    wa:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20.5 3.5A11 11 0 0 0 3.2 17L2 22l5.2-1.2A11 11 0 1 0 20.5 3.5z"/></svg>',
    reloj: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>'
  };

  // ------------------------------------------------------------- avisos
  var contTostadas = $('#tostadas');
  function avisar(texto, tipo) {
    if (!contTostadas) { return; }
    tipo = tipo || 'ok';
    var el = document.createElement('div');
    el.className = 'tostada tostada--' + tipo;
    el.innerHTML = (ICONOS[tipo] || ICONOS.ok) + '<span>' + esc(texto) + '</span>';
    contTostadas.appendChild(el);
    setTimeout(function () {
      el.classList.add('saliendo');
      setTimeout(function () { el.remove(); }, 220);
    }, 3200);
  }

  // ------------------------------------------------------------- red
  function pedir(ruta, datos, metodo) {
    var opts = {
      method: metodo || (datos ? 'POST' : 'GET'),
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      credentials: 'same-origin'
    };
    if (datos) {
      opts.headers['Content-Type'] = 'application/json';
      datos._token = MG.token;
      opts.body = JSON.stringify(datos);
    }
    return fetch(url(ruta), opts).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: T.error }; })
        .then(function (j) {
          if (!r.ok && !j.error) j.error = T.error;
          return j;
        });
    }).catch(function () { return { ok: false, error: 'Sin conexión. Revisa tu internet.' }; });
  }

  // ------------------------------------------------------------- carrito
  var carrito = [];
  try {
    var guardado = localStorage.getItem(LLAVE);
    if (guardado) carrito = JSON.parse(guardado) || [];
  } catch (e) { carrito = []; }
  if (!Array.isArray(carrito)) carrito = [];

  function guardarCarrito() {
    try { localStorage.setItem(LLAVE, JSON.stringify(carrito)); } catch (e) {}
    pintarBarra();
  }
  function totalCarrito() {
    return carrito.reduce(function (s, l) { return s + Number(l.precio_total || 0); }, 0);
  }
  function piezasCarrito() {
    return carrito.reduce(function (s, l) { return s + Number(l.cantidad || 0); }, 0);
  }
  function producto(id) {
    var c = MG.catalogo || [];
    for (var i = 0; i < c.length; i++) if (c[i].id === Number(id)) return c[i];
    return null;
  }

  var barra = $('#barraCarrito');
  var elConteo = $('#carritoConteo');
  var elTotal = $('#carritoTotal');
  var accionesMesa = $('#accionesMesa');

  function pintarBarra() {
    var n = piezasCarrito();
    if (elConteo) {
      if (elConteo.textContent !== String(n)) {
        elConteo.classList.remove('brinca');
        void elConteo.offsetWidth;
        elConteo.classList.add('brinca');
      }
      elConteo.textContent = String(n);
    }
    if (elTotal) elTotal.textContent = money(totalCarrito());
    if (barra) barra.classList.toggle('visible', n > 0);
    if (accionesMesa) accionesMesa.classList.toggle('subida', n > 0);
  }

  // ------------------------------------------------------------- modal ficha
  var modal = $('#modalPlatillo');
  var modalScroll = $('#modalScroll');
  var modalPie = $('#modalPie');
  var fichaActual = null;
  var cantidadActual = 1;
  var ultimoFoco = null;

  function abrirModal() {
    if (!modal) return;
    ultimoFoco = document.activeElement;
    modal.hidden = false;
    modal.classList.add('abierto');
    document.body.style.overflow = 'hidden';
    var f = modal.querySelector('.modal__cerrar');
    if (f) f.focus();
  }
  function cerrarModal() {
    if (!modal) return;
    modal.classList.remove('abierto');
    modal.hidden = true;
    document.body.style.overflow = '';
    fichaActual = null;
    if (ultimoFoco && ultimoFoco.focus) ultimoFoco.focus();
  }

  function verFicha(id) {
    var p = producto(id);
    if (p && p.agotado) { avisar('Ese platillo está agotado por hoy.', 'aviso'); return; }
    cantidadActual = 1;
    modalScroll.innerHTML = '<div style="padding:26px"><div class="esqueleto" style="height:190px;border-radius:16px"></div>'
      + '<div class="esqueleto" style="height:24px;width:65%;margin-top:16px"></div>'
      + '<div class="esqueleto" style="height:16px;width:90%;margin-top:10px"></div></div>';
    modalPie.innerHTML = '';
    abrirModal();

    pedir('api/producto/' + encodeURIComponent(MG.slug) + '/' + Number(id)).then(function (res) {
      if (!res.ok) { cerrarModal(); avisar(res.error || T.error, 'error'); return; }
      fichaActual = res.producto;
      fichaActual.grupos = res.grupos || [];
      pintarFicha();
    });
  }

  function pintarFicha() {
    var p = fichaActual;
    if (!p) return;
    var h = '';
    if (p.imagen) {
      h += '<img class="modal__foto" src="' + esc(p.imagen) + '" alt="' + esc(p.nombre) + '" width="540" height="226">';
    }
    h += '<div class="modal__cuerpo">';
    if (p.etiquetas && p.etiquetas.length) {
      h += '<div class="etiquetas">';
      p.etiquetas.forEach(function (t) {
        h += '<span class="etiqueta etiqueta--' + esc(t.clave) + '">' + esc(t.texto) + '</span>';
      });
      h += '</div>';
    }
    h += '<h2 class="modal__titulo" id="modalTitulo">' + esc(p.nombre) + '</h2>';
    h += '<div class="modal__precio">' + money(p.precio) + (p.precio_antes ? ' <span class="precio__antes">' + money(p.precio_antes) + '</span>' : '') + '</div>';
    if (p.descripcion) h += '<p class="modal__desc">' + esc(p.descripcion) + '</p>';

    var datos = [];
    if (p.tiempo_prep) datos.push(ICONOS.reloj + '<span>' + p.tiempo_prep + ' min</span>');
    if (p.calorias) datos.push('<span>' + p.calorias + ' kcal</span>');
    if (p.alergenos && p.alergenos.length) datos.push('<span>Alérgenos: ' + esc(p.alergenos.join(', ')) + '</span>');
    if (datos.length) h += '<div class="dato-fila">' + datos.map(function (d) { return '<span>' + d + '</span>'; }).join('') + '</div>';

    (p.grupos || []).forEach(function (g) {
      var multiple = g.tipo === 'multiple';
      var regla = g.obligatorio
        ? '<span class="grupo-mod__regla grupo-mod__regla--req">Obligatorio</span>'
        : (multiple ? '<span class="grupo-mod__regla">Elige hasta ' + g.max_sel + '</span>' : '<span class="grupo-mod__regla">Opcional</span>');
      h += '<div class="grupo-mod" data-grupo="' + g.id + '" data-tipo="' + esc(g.tipo)
         + '" data-min="' + g.min_sel + '" data-max="' + g.max_sel + '" data-obligatorio="' + (g.obligatorio ? 1 : 0) + '">';
      h += '<div class="grupo-mod__cab"><h3 class="grupo-mod__nombre">' + esc(g.nombre) + '</h3>' + regla + '</div>';
      g.opciones.forEach(function (o) {
        var tipo = multiple ? 'checkbox' : 'radio';
        var forma = multiple ? 'check' : 'radio';
        h += '<label class="opcion' + (o.agotado ? ' opcion--agotada' : '') + '">'
           + '<input type="' + tipo + '" name="g' + g.id + '" value="' + o.id + '"'
           + (o.agotado ? ' disabled' : '') + (o.predeterminado && !o.agotado ? ' checked' : '') + '>'
           + '<span class="opcion__marca opcion__marca--' + forma + '">' + ICONOS.check + '</span>'
           + '<span class="opcion__nombre">' + esc(o.nombre) + (o.agotado ? ' · agotado' : '') + '</span>'
           + (Number(o.precio_extra) > 0 ? '<span class="opcion__precio">+' + money(o.precio_extra) + '</span>' : '')
           + '</label>';
      });
      h += '</div>';
    });

    if (MG.notas) {
      h += '<div class="grupo-mod"><div class="grupo-mod__cab"><h3 class="grupo-mod__nombre">Notas para la cocina</h3></div>'
         + '<textarea class="campo-notas" id="notasPlatillo" maxlength="200" placeholder="Ej. sin cebolla, término medio…"></textarea></div>';
    }
    h += '</div>';
    modalScroll.innerHTML = h;

    if (MG.acepta) {
      modalPie.innerHTML =
        '<div class="contador">'
        + '<button type="button" id="menos" aria-label="Quitar uno">' + ICONOS.menos + '</button>'
        + '<output id="cantidad" aria-live="polite">1</output>'
        + '<button type="button" id="mas" aria-label="Agregar uno">' + ICONOS.mas + '</button>'
        + '</div>'
        + '<button class="btn btn--oro crece" type="button" id="confirmarAgregar">'
        + '<span>Agregar</span> <span id="totalFicha">' + money(p.precio) + '</span></button>';
      $('#menos').addEventListener('click', function () { cambiarCantidad(-1); });
      $('#mas').addEventListener('click', function () { cambiarCantidad(1); });
      $('#confirmarAgregar').addEventListener('click', agregarAlCarrito);
      modalScroll.addEventListener('change', recalcularFicha);
      recalcularFicha();
      marcarElegidas(modalScroll);
    } else if (!MG.soloConsulta) {
      // El menú acepta pedidos, pero ahora mismo está cerrado
      modalPie.innerHTML = '<button class="btn btn--fantasma btn--bloque" type="button" disabled>'
        + esc(MG.motivoCerrado || T.cerrado) + '</button>';
    } else {
      modalPie.innerHTML = '<button class="btn btn--fantasma btn--bloque" type="button" data-cerrar>'
        + esc(T.cerrar || 'Cerrar') + '</button>';
    }
  }

  function cambiarCantidad(d) {
    cantidadActual = Math.max(1, Math.min(50, cantidadActual + d));
    var out = $('#cantidad');
    if (out) out.textContent = String(cantidadActual);
    var m = $('#menos');
    if (m) m.disabled = cantidadActual <= 1;
    recalcularFicha();
  }

  function extrasElegidos() {
    var extra = 0, ids = [], textos = [];
    $$('.grupo-mod', modalScroll).forEach(function (g) {
      $$('input:checked', g).forEach(function (inp) {
        var lbl = inp.closest('.opcion');
        var pr = lbl.querySelector('.opcion__precio');
        var val = pr ? parseFloat(pr.textContent.replace(/[^\d.]/g, '')) || 0 : 0;
        extra += val;
        ids.push(Number(inp.value));
        textos.push(lbl.querySelector('.opcion__nombre').textContent.trim());
      });
    });
    return { extra: extra, ids: ids, textos: textos };
  }

  function recalcularFicha() {
    if (!fichaActual) return;
    marcarElegidas(modalScroll);
    var ex = extrasElegidos();
    var total = (Number(fichaActual.precio) + ex.extra) * cantidadActual;
    var el = $('#totalFicha');
    if (el) el.textContent = money(total);
  }

  function validarGrupos() {
    var ok = true, faltante = '';
    $$('.grupo-mod', modalScroll).forEach(function (g) {
      if (!g.dataset.grupo) return;
      var n = $$('input:checked', g).length;
      var min = Number(g.dataset.obligatorio) === 1 ? Math.max(1, Number(g.dataset.min)) : Number(g.dataset.min);
      if (n < min) {
        ok = false;
        if (!faltante) faltante = g.querySelector('.grupo-mod__nombre').textContent;
        g.style.outline = '2px solid var(--peligro)';
        g.style.outlineOffset = '4px';
        g.style.borderRadius = '10px';
        setTimeout(function () { g.style.outline = ''; }, 2200);
      }
    });
    if (!ok) avisar('Elige "' + faltante + '" para continuar.', 'aviso');
    return ok;
  }

  function agregarAlCarrito() {
    if (!fichaActual || !validarGrupos()) return;
    var ex = extrasElegidos();
    var notasEl = $('#notasPlatillo');
    var notas = notasEl ? notasEl.value.trim().slice(0, 200) : '';
    var firma = fichaActual.id + '|' + ex.ids.slice().sort(function (a, b) { return a - b; }).join(',') + '|' + notas;

    var existente = null;
    for (var i = 0; i < carrito.length; i++) if (carrito[i].firma === firma) { existente = carrito[i]; break; }

    if (existente) {
      existente.cantidad = Math.min(50, existente.cantidad + cantidadActual);
      existente.precio_total = (Number(fichaActual.precio) + ex.extra) * existente.cantidad;
    } else {
      carrito.push({
        firma: firma,
        product_id: fichaActual.id,
        nombre: fichaActual.nombre,
        imagen: fichaActual.imagen || '',
        precio: Number(fichaActual.precio),
        extra: ex.extra,
        opciones: ex.ids,
        opciones_texto: ex.textos,
        notas: notas,
        cantidad: cantidadActual,
        precio_total: (Number(fichaActual.precio) + ex.extra) * cantidadActual
      });
    }
    guardarCarrito();
    cerrarModal();
    avisar(T.agregado || 'Agregado a tu pedido', 'ok');
  }

  // ------------------------------------------------------------- hoja carrito
  var hoja = $('#hojaCarrito');
  var hojaScroll = $('#hojaScroll');
  var hojaPie = $('#hojaPie');
  var paso = 'lista';
  var estadoPago = { modo: null, propina: 0, cupon: null, descuento: 0, zona: null, metodo: '' };

  function abrirHoja() {
    if (!hoja) return;
    if (!carrito.length) { avisar(T.vacio || 'Tu pedido está vacío', 'aviso'); return; }
    hoja.hidden = false;
    hoja.classList.add('abierta');
    document.body.style.overflow = 'hidden';
    paso = 'lista';
    if (!estadoPago.modo) {
      estadoPago.modo = MG.mesa && MG.modos.indexOf('mesa') >= 0 ? 'mesa'
        : (MG.modos.indexOf('llevar') >= 0 ? 'llevar'
        : (MG.modos.indexOf('delivery') >= 0 ? 'delivery'
        : (MG.modos.indexOf('whatsapp') >= 0 ? 'whatsapp' : null)));
    }
    pintarHoja();
  }
  function cerrarHoja() {
    if (!hoja) return;
    hoja.classList.remove('abierta');
    hoja.hidden = true;
    document.body.style.overflow = '';
  }

  function calcularTotales() {
    var sub = totalCarrito();
    var envio = 0;
    if (estadoPago.modo === 'delivery' && estadoPago.zona) {
      var z = (MG.zonas || []).filter(function (x) { return x.id === Number(estadoPago.zona); })[0];
      if (z) envio = Number(z.costo);
    }
    var desc = Number(estadoPago.descuento || 0);
    if (desc > sub) desc = sub;
    var base = Math.max(0, sub - desc);
    var propina = Math.round(base * (Number(estadoPago.propina) / 100) * 100) / 100;
    return { sub: sub, envio: envio, desc: desc, propina: propina, total: base + envio + propina };
  }

  function pintarHoja() {
    if (paso === 'lista') pintarLista();
    else pintarDatos();
  }

  function pintarLista() {
    if (!carrito.length) {
      hojaScroll.innerHTML = '<div class="vacio">' + ICONOS.carro + '<p>' + esc(T.vacio || 'Tu pedido está vacío') + '</p></div>';
      hojaPie.innerHTML = '';
      return;
    }
    var h = '';
    carrito.forEach(function (l, i) {
      h += '<div class="linea-carrito">';
      if (l.imagen) h += '<img class="linea-carrito__foto" src="' + esc(l.imagen) + '" alt="" width="58" height="58" loading="lazy">';
      h += '<div class="crece">';
      h += '<p class="linea-carrito__nombre">' + esc(l.nombre) + '</p>';
      if (l.opciones_texto && l.opciones_texto.length) {
        h += '<p class="linea-carrito__mods">' + esc(l.opciones_texto.join(' · ')) + '</p>';
      }
      if (l.notas) h += '<p class="linea-carrito__mods">📝 ' + esc(l.notas) + '</p>';
      h += '<div class="linea-carrito__pie">';
      h += '<div class="mini-contador">'
         + '<button type="button" data-menos="' + i + '" aria-label="Quitar uno">' + ICONOS.menos + '</button>'
         + '<output>' + l.cantidad + '</output>'
         + '<button type="button" data-mas="' + i + '" aria-label="Agregar uno">' + ICONOS.mas + '</button>'
         + '</div>';
      h += '<span class="precio">' + money(l.precio_total) + '</span>';
      h += '</div></div></div>';
    });
    hojaScroll.innerHTML = h;

    var t = calcularTotales();
    var pie = '<div class="resumen">';
    pie += fila('Subtotal', money(t.sub));
    pie += '</div>';
    if (MG.acepta) {
      pie += '<button class="btn btn--oro btn--bloque" type="button" id="irDatos">Continuar</button>';
    } else if (!MG.abierto) {
      pie += '<p class="campo__ayuda centro">' + esc(T.cerrado) + '</p>';
    }
    hojaPie.innerHTML = pie;

    var b = $('#irDatos');
    if (b) b.addEventListener('click', function () { paso = 'datos'; pintarHoja(); });
  }

  function fila(etiqueta, valor, clase) {
    return '<div class="resumen__fila ' + (clase || '') + '"><span>' + esc(etiqueta) + '</span><span class="valor mono">' + valor + '</span></div>';
  }

  function pintarDatos() {
    var modos = MG.modos || [];
    var h = '';

    // --- Modo de pedido ---
    var opcionesModo = [];
    if (MG.mesa && modos.indexOf('mesa') >= 0) opcionesModo.push(['mesa', ICONOS.mesa, 'Comer aquí', 'Mesa ' + MG.mesa.nombre.replace(/^Mesa\s*/i, '')]);
    if (modos.indexOf('llevar') >= 0)   opcionesModo.push(['llevar', ICONOS.bolsa, 'Para llevar', 'Lo recoges en el restaurante']);
    if (modos.indexOf('delivery') >= 0) opcionesModo.push(['delivery', ICONOS.moto, 'A domicilio', 'Te lo llevamos']);
    if (modos.indexOf('whatsapp') >= 0 && MG.whatsapp) opcionesModo.push(['whatsapp', ICONOS.wa, 'Enviar por WhatsApp', 'Confirmamos por mensaje']);

    if (opcionesModo.length > 1) {
      h += '<h3 style="font-size:14px;margin:2px 0 10px;color:var(--texto-suave)">¿Cómo quieres tu pedido?</h3><div class="opciones-modo">';
      opcionesModo.forEach(function (m) {
        h += '<button class="modo-btn" type="button" data-modo="' + m[0] + '" aria-pressed="' + (estadoPago.modo === m[0]) + '">'
           + m[1] + '<span class="crece"><strong>' + esc(m[2]) + '</strong><small>' + esc(m[3]) + '</small></span></button>';
      });
      h += '</div>';
    }

    // --- Datos del cliente ---
    var pideDatos = estadoPago.modo === 'llevar' || estadoPago.modo === 'delivery' || estadoPago.modo === 'whatsapp';
    if (pideDatos) {
      h += '<div class="campo"><label for="cNombre">Tu nombre</label><input id="cNombre" type="text" maxlength="80" autocomplete="name" required></div>';
      h += '<div class="campo"><label for="cTel">Teléfono</label><input id="cTel" type="tel" maxlength="20" autocomplete="tel" inputmode="tel" required></div>';
    }
    if (estadoPago.modo === 'delivery') {
      if ((MG.zonas || []).length) {
        h += '<div class="campo"><label for="cZona">Zona de entrega</label><select id="cZona">';
        h += '<option value="">Elige tu zona…</option>';
        MG.zonas.forEach(function (z) {
          h += '<option value="' + z.id + '"' + (Number(estadoPago.zona) === z.id ? ' selected' : '') + '>'
             + esc(z.nombre) + ' · ' + money(z.costo) + (z.tiempo ? ' · ' + z.tiempo + ' min' : '') + '</option>';
        });
        h += '</select></div>';
      }
      h += '<div class="campo"><label for="cDir">Dirección</label><textarea id="cDir" maxlength="200" required></textarea></div>';
      h += '<div class="campo"><label for="cRef">Punto de referencia</label><input id="cRef" type="text" maxlength="120"></div>';
    }

    // --- Cupón ---
    h += '<div class="campo"><label for="cCupon">¿Tienes un cupón?</label>'
       + '<div style="display:flex;gap:8px"><input id="cCupon" type="text" maxlength="30" placeholder="CÓDIGO" style="text-transform:uppercase" value="' + esc(estadoPago.cupon || '') + '">'
       + '<button class="btn btn--fantasma" type="button" id="aplicarCupon" style="flex:0 0 auto">Aplicar</button></div>'
       + '<p class="campo__ayuda" id="msgCupon"></p></div>';

    // --- Propina ---
    if ((MG.propinas || []).length > 1 && estadoPago.modo !== 'whatsapp') {
      h += '<h3 style="font-size:14px;margin:14px 0 6px;color:var(--texto-suave)">¿Deseas dejar propina?</h3><div class="propinas">';
      MG.propinas.forEach(function (p) {
        h += '<button class="propina-btn" type="button" data-propina="' + p + '" aria-pressed="' + (Number(estadoPago.propina) === Number(p)) + '">'
           + (p === 0 ? 'No, gracias' : p + '%') + '</button>';
      });
      h += '</div>';
    }

    // --- Método de pago ---
    var pagos = MG.pagos || [];
    if (pagos.length && estadoPago.modo !== 'whatsapp') {
      var nombres = { efectivo: 'Efectivo', tarjeta: 'Tarjeta en mesa', transferencia: 'Transferencia', link: 'Pago en línea' };
      h += '<div class="campo"><label for="cPago">¿Cómo vas a pagar?</label><select id="cPago">';
      pagos.forEach(function (m) {
        h += '<option value="' + esc(m) + '"' + (estadoPago.metodo === m ? ' selected' : '') + '>' + esc(nombres[m] || m) + '</option>';
      });
      h += '</select></div>';
      if (MG.banco) h += '<div id="datosBanco" class="campo__ayuda" style="white-space:pre-line;padding:10px 12px;background:var(--superficie-2);border-radius:10px"></div>';
    }

    h += '<div class="campo"><label for="cNotas">Notas del pedido</label><textarea id="cNotas" maxlength="300" placeholder="Alguna indicación especial…"></textarea></div>';

    hojaScroll.innerHTML = h;
    pintarPieDatos();
    conectarDatos();
  }

  function pintarPieDatos() {
    var t = calcularTotales();
    var h = '<div class="resumen">';
    h += fila('Subtotal', money(t.sub));
    if (t.desc > 0)    h += fila('Descuento', '− ' + money(t.desc), 'resumen__fila--desc');
    if (t.envio > 0)   h += fila('Envío', money(t.envio));
    if (t.propina > 0) h += fila('Propina', money(t.propina));
    h += fila('Total', money(t.total), 'resumen__fila--total');
    h += '</div>';
    h += '<div style="display:flex;gap:9px">'
       + '<button class="btn btn--fantasma" type="button" id="volverLista" style="flex:0 0 auto">Volver</button>'
       + '<button class="btn ' + (estadoPago.modo === 'whatsapp' ? 'btn--wa' : 'btn--oro') + ' crece" type="button" id="enviarPedido">'
       + (estadoPago.modo === 'whatsapp' ? 'Enviar por WhatsApp' : 'Enviar pedido') + '</button></div>';
    hojaPie.innerHTML = h;
    $('#volverLista').addEventListener('click', function () { paso = 'lista'; pintarHoja(); });
    $('#enviarPedido').addEventListener('click', enviarPedido);
  }

  function conectarDatos() {
    $$('[data-modo]', hojaScroll).forEach(function (b) {
      b.addEventListener('click', function () {
        estadoPago.modo = b.dataset.modo;
        if (estadoPago.modo !== 'delivery') estadoPago.zona = null;
        pintarDatos();
      });
    });
    $$('[data-propina]', hojaScroll).forEach(function (b) {
      b.addEventListener('click', function () {
        estadoPago.propina = Number(b.dataset.propina);
        $$('[data-propina]', hojaScroll).forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
        pintarPieDatos();
      });
    });
    var zona = $('#cZona');
    if (zona) zona.addEventListener('change', function () { estadoPago.zona = zona.value; pintarPieDatos(); });

    var pago = $('#cPago');
    var banco = $('#datosBanco');
    function pintarBanco() {
      if (!banco) return;
      var esTransfer = pago && pago.value === 'transferencia';
      banco.textContent = esTransfer ? MG.banco : '';
      banco.style.display = esTransfer && MG.banco ? 'block' : 'none';
    }
    if (pago) { pago.addEventListener('change', function () { estadoPago.metodo = pago.value; pintarBanco(); }); }
    pintarBanco();

    var btnCupon = $('#aplicarCupon');
    if (btnCupon) btnCupon.addEventListener('click', function () {
      var codigo = ($('#cCupon').value || '').trim().toUpperCase();
      var msg = $('#msgCupon');
      if (!codigo) { estadoPago.cupon = null; estadoPago.descuento = 0; msg.textContent = ''; pintarPieDatos(); return; }
      btnCupon.disabled = true;
      pedir('api/cupon', { slug: MG.slug, codigo: codigo, subtotal: totalCarrito() }).then(function (res) {
        btnCupon.disabled = false;
        if (res.ok) {
          estadoPago.cupon = codigo;
          estadoPago.descuento = Number(res.descuento || 0);
          msg.textContent = '✓ ' + (res.mensaje || 'Cupón aplicado');
          msg.style.color = 'var(--exito)';
        } else {
          estadoPago.cupon = null; estadoPago.descuento = 0;
          msg.textContent = res.error || 'Cupón no válido';
          msg.style.color = 'var(--peligro)';
        }
        pintarPieDatos();
      });
    });
  }

  function enviarPedido() {
    var btn = $('#enviarPedido');
    var pideDatos = estadoPago.modo === 'llevar' || estadoPago.modo === 'delivery' || estadoPago.modo === 'whatsapp';
    var datos = {
      slug: MG.slug,
      modo: estadoPago.modo,
      mesa_id: MG.mesa ? MG.mesa.id : null,
      propina_pct: estadoPago.propina,
      cupon: estadoPago.cupon,
      zona_id: estadoPago.zona ? Number(estadoPago.zona) : null,
      metodo_pago: (($('#cPago') || {}).value) || '',
      notas: (($('#cNotas') || {}).value || '').trim(),
      cliente: {
        nombre: (($('#cNombre') || {}).value || '').trim(),
        telefono: (($('#cTel') || {}).value || '').trim(),
        direccion: (($('#cDir') || {}).value || '').trim(),
        referencia: (($('#cRef') || {}).value || '').trim()
      },
      lineas: carrito.map(function (l) {
        return { product_id: l.product_id, cantidad: l.cantidad, opciones: l.opciones, notas: l.notas };
      })
    };

    if (!estadoPago.modo) { avisar('Elige cómo quieres tu pedido.', 'aviso'); return; }
    if (pideDatos && datos.cliente.nombre.length < 2) { avisar('Escribe tu nombre.', 'aviso'); focoEn('#cNombre'); return; }
    if (pideDatos && datos.cliente.telefono.replace(/\D/g, '').length < 7) { avisar('Escribe un teléfono válido.', 'aviso'); focoEn('#cTel'); return; }
    if (estadoPago.modo === 'delivery') {
      if ((MG.zonas || []).length && !estadoPago.zona) { avisar('Elige tu zona de entrega.', 'aviso'); focoEn('#cZona'); return; }
      if (datos.cliente.direccion.length < 8) { avisar('Escribe tu dirección completa.', 'aviso'); focoEn('#cDir'); return; }
    }
    var t = calcularTotales();
    if (MG.pedidoMinimo > 0 && t.sub < MG.pedidoMinimo) {
      avisar('El pedido mínimo es ' + money(MG.pedidoMinimo) + '.', 'aviso'); return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="cargador"></span> ' + esc(T.enviando || 'Enviando…');

    pedir('api/pedido', datos).then(function (res) {
      btn.disabled = false;
      if (!res.ok) {
        btn.textContent = estadoPago.modo === 'whatsapp' ? 'Enviar por WhatsApp' : 'Enviar pedido';
        avisar(res.error || T.error, 'error');
        return;
      }
      carrito = [];
      guardarCarrito();
      try { localStorage.removeItem(LLAVE); } catch (e) {}
      if (res.whatsapp) { window.location.href = res.whatsapp; return; }
      window.location.href = res.redirect;
    });
  }

  function focoEn(sel) { var el = $(sel); if (el) { el.focus(); el.scrollIntoView({ block: 'center', behavior: 'smooth' }); } }

  // ------------------------------------------------------------- eventos
  document.addEventListener('click', function (ev) {
    var t = ev.target;

    var card = t.closest ? t.closest('[data-producto]') : null;
    if (card) { verFicha(card.dataset.producto); return; }

    if (t.closest && t.closest('[data-cerrar]')) { cerrarModal(); return; }
    if (t.closest && t.closest('[data-cerrar-hoja]')) { cerrarHoja(); return; }

    var ab = t.closest && t.closest('#abrirCarrito');
    if (ab) { abrirHoja(); return; }

    var menos = t.closest && t.closest('[data-menos]');
    if (menos) {
      var i = Number(menos.dataset.menos);
      if (carrito[i]) {
        carrito[i].cantidad--;
        if (carrito[i].cantidad <= 0) carrito.splice(i, 1);
        else carrito[i].precio_total = (carrito[i].precio + carrito[i].extra) * carrito[i].cantidad;
        guardarCarrito();
        if (!carrito.length) { cerrarHoja(); } else { pintarHoja(); }
      }
      return;
    }
    var mas = t.closest && t.closest('[data-mas]');
    if (mas) {
      var j = Number(mas.dataset.mas);
      if (carrito[j] && carrito[j].cantidad < 50) {
        carrito[j].cantidad++;
        carrito[j].precio_total = (carrito[j].precio + carrito[j].extra) * carrito[j].cantidad;
        guardarCarrito();
        pintarHoja();
      }
      return;
    }

    var ir = t.closest && t.closest('[data-ir]');
    if (ir) {
      var destino = document.getElementById(ir.dataset.ir);
      if (destino) {
        var y = destino.getBoundingClientRect().top + window.pageYOffset - 118;
        window.scrollTo({ top: y, behavior: 'smooth' });
      }
      return;
    }
  });

  var btnVaciar = $('#vaciarCarrito');
  if (btnVaciar) btnVaciar.addEventListener('click', function () {
    if (!carrito.length) return;
    if (!window.confirm(T.confirmar_vaciar || '¿Vaciar el pedido?')) return;
    carrito = [];
    guardarCarrito();
    cerrarHoja();
    avisar('Tu pedido quedó vacío', 'ok');
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape') return;
    if (hoja && hoja.classList.contains('abierta')) cerrarHoja();
    else if (modal && modal.classList.contains('abierto')) cerrarModal();
  });

  // ------------------------------------------------------------- llamar mesero
  function llamar(tipo, boton) {
    if (!MG.mesa) return;
    boton.disabled = true;
    pedir('api/llamar', { slug: MG.slug, mesa_id: MG.mesa.id, tipo: tipo }).then(function (res) {
      if (res.ok) {
        boton.classList.add('fab--ok');
        avisar(res.mensaje || 'Un mesero viene en camino', 'ok');
        setTimeout(function () { boton.classList.remove('fab--ok'); boton.disabled = false; }, 20000);
      } else {
        boton.disabled = false;
        avisar(res.error || T.error, 'error');
      }
    });
  }
  var bMesero = $('#btnMesero'); if (bMesero) bMesero.addEventListener('click', function () { llamar('mesero', bMesero); });
  var bCuenta = $('#btnCuenta'); if (bCuenta) bCuenta.addEventListener('click', function () { llamar('cuenta', bCuenta); });

  // ------------------------------------------------------------- buscar
  var inputBuscar = $('#buscar');
  var btnLimpiar = $('#limpiarBuscar');
  var sinResultados = $('#sinResultados');
  function filtrar() {
    var q = (inputBuscar.value || '').trim().toLowerCase();
    if (btnLimpiar) btnLimpiar.classList.toggle('oculto', q === '');
    var visibles = 0;
    $$('.platillo').forEach(function (card) {
      var ok = q === '' || (card.dataset.nombre || '').indexOf(q) >= 0;
      card.style.display = ok ? '' : 'none';
      if (ok) visibles++;
    });
    $$('.seccion').forEach(function (sec) {
      var hay = $$('.platillo', sec).some(function (c) { return c.style.display !== 'none'; });
      sec.style.display = hay ? '' : 'none';
    });
    if (sinResultados) sinResultados.classList.toggle('oculto', visibles > 0 || q === '');
    var dest = $('.destacados');
    if (dest) dest.style.display = q === '' ? '' : 'none';
  }
  if (inputBuscar) {
    var temporizador = null;
    inputBuscar.addEventListener('input', function () {
      clearTimeout(temporizador);
      temporizador = setTimeout(filtrar, 140);
    });
  }
  if (btnLimpiar) btnLimpiar.addEventListener('click', function () {
    inputBuscar.value = '';
    filtrar();
    inputBuscar.focus();
  });

  // ------------------------------------------------------------- scroll-spy
  var barraCat = $('#barraCat');
  var pistas = $$('.pista');
  var secciones = $$('.seccion[id]');
  function alDesplazar() {
    if (barraCat) barraCat.classList.toggle('pegada', window.pageYOffset > 12);
    if (!secciones.length) return;
    var y = window.pageYOffset + 150;
    var actual = secciones[0].id;
    secciones.forEach(function (s) { if (s.offsetTop <= y) actual = s.id; });
    pistas.forEach(function (p) {
      var activo = p.dataset.ir === actual;
      p.setAttribute('aria-current', activo ? 'true' : 'false');
      if (activo && p.parentElement) {
        var pr = p.parentElement.getBoundingClientRect();
        var br = p.getBoundingClientRect();
        if (br.left < pr.left + 8 || br.right > pr.right - 8) {
          p.parentElement.scrollTo({ left: p.offsetLeft - 60, behavior: 'smooth' });
        }
      }
    });
  }
  var esperando = false;
  window.addEventListener('scroll', function () {
    if (esperando) return;
    esperando = true;
    window.requestAnimationFrame(function () { alDesplazar(); esperando = false; });
  }, { passive: true });

  // ---------------------------------------------- respaldo para navegadores sin :has()
  var soportaHas = (function () {
    try { return CSS.supports('selector(:has(*))'); } catch (e) { return false; }
  })();
  function marcarElegidas(raiz) {
    if (soportaHas) return;
    $$('.opcion', raiz || document).forEach(function (l) {
      var i = l.querySelector('input');
      if (i) l.classList.toggle('elegida', i.checked);
    });
  }
  document.addEventListener('change', function () { marcarElegidas(); });

  // ------------------------------------------------------------- inicio
  pintarBarra();
  alDesplazar();
  window.MGavisar = avisar;
})();
