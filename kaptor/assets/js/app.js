/* ==========================================================================
   Kaptor · JavaScript de la portada
   Sin librerias externas. Se encarga de:
     · modo claro / oscuro con memoria
     · lanzar el escaneo y seguir su progreso en tiempo real (AJAX por pasos)
     · pintar los correos con efecto "ping" segun van apareciendo
     · buscador, filtro por dominio, seleccion, copiar y exportar
   ========================================================================== */
(function () {
  'use strict';

  var CR = window.CR || {};
  var $  = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  /* ------------------------------------------------------ 1. Tema claro/oscuro */
  var TEMA_CLAVE = 'kaptor-tema';

  function aplicarTema(tema) {
    document.documentElement.setAttribute('data-tema', tema);
    try { localStorage.setItem(TEMA_CLAVE, tema); } catch (e) { /* modo privado */ }
    var btn = $('.tema');
    if (btn) {
      btn.setAttribute('aria-label', tema === 'oscuro' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
    }
  }

  function iniciarTema() {
    /* El tema ya lo dejo puesto el guion que va en la cabecera, antes de
       pintar. Aqui solo se lee lo que hay: asi la pagina no parpadea ni
       cambia de color al volver de una pagina que no carga este archivo. */
    var actual = document.documentElement.getAttribute('data-tema');
    if (actual !== 'claro' && actual !== 'oscuro') {
      actual = (CR.temaPorDefecto === 'claro') ? 'claro' : 'oscuro';
    }
    aplicarTema(actual);

    var btn = $('.tema');
    if (btn) {
      btn.addEventListener('click', function () {
        aplicarTema(document.documentElement.getAttribute('data-tema') === 'oscuro' ? 'claro' : 'oscuro');
      });
    }
  }


  /* ------------------------------------------------------------- 2. Utilidades */
  function esc(t) {
    return String(t == null ? '' : t)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  var brindisTiempo = null;
  function brindis(mensaje, tipo) {
    var caja = $('#brindis');
    if (!caja) { return; }
    caja.querySelector('span').textContent = mensaje;
    caja.classList.toggle('error', tipo === 'error');
    caja.classList.add('visible');
    clearTimeout(brindisTiempo);
    brindisTiempo = setTimeout(function () { caja.classList.remove('visible'); }, 2800);
  }

  function api(datos, senal) {
    var opciones = {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CR.csrf },
      body: JSON.stringify(Object.assign({ csrf: CR.csrf }, datos)),
      credentials: 'same-origin'
    };
    if (senal) { opciones.signal = senal; }
    return fetch(CR.apiEscaneo, opciones).then(function (r) {
      return r.json().catch(function () {
        throw new Error('El servidor devolvió una respuesta inesperada.');
      }).then(function (j) {
        if (!r.ok && !j.error) { throw new Error('Error ' + r.status); }
        return j;
      });
    });
  }

  function recorta(txt, max) {
    txt = String(txt || '');
    return txt.length > max ? txt.slice(0, max - 1) + '…' : txt;
  }

  /* ------------------------------------------- 3. Estado del escaneo en curso */
  var estado = {
    id: 0,
    token: '',
    host: '',
    corriendo: false,
    cancelado: false,
    aborto: null,                     // AbortController del paso en curso
    correos: [],                      // lista completa de correos
    telefonos: [],                    // lista completa de telefonos y WhatsApp
    vistos: Object.create(null),
    vistosTel: Object.create(null)
  };

  /* --------------------------------------------------------- 4. Lanzar escaneo */
  function iniciarEscaneo(e) {
    if (e) { e.preventDefault(); }
    if (estado.corriendo) { return; }

    var campo = $('#url');
    var url = (campo.value || '').trim();
    if (!url) {
      campo.focus();
      brindis('Pega una web, una lista de webs o unas palabras para buscar.', 'error');
      return;
    }

    var profundo = $('#profundo') ? $('#profundo').checked : false;
    var objetivo = $('#objetivo') ? ($('#objetivo').value || '').trim() : '';

    reiniciarPanel();
    estado.corriendo = true;
    estado.cancelado = false;
    document.body.classList.add('escaneando');
    $('#btn-extraer').classList.add('cargando');
    $('#btn-extraer').setAttribute('aria-busy', 'true');

    api({ accion: 'iniciar', url: url, profundo: profundo, objetivo: objetivo })
      .then(function (r) {
        if (!r.ok) {
          if (r.requiere_login && CR.urlLogin) {
            window.location.href = CR.urlLogin;
            return;
          }
          var msg = r.error || 'No se pudo iniciar la extracción.';
          // El detalle dice qué contestó cada buscador: sin eso, un fallo de
          // búsqueda no hay manera de diagnosticarlo.
          if (r.detalle) { msg += '\n\nRespuesta de cada buscador — ' + r.detalle; }
          throw new Error(msg);
        }
        estado.id    = r.escaneo_id;
        estado.token = r.token;
        estado.host  = r.host;

        $('#panel-progreso').classList.remove('oculto');
        $('#destino').textContent = r.url;

        // "31 webs encontradas en DuckDuckGo para «colegios Guatemala»"
        var avisoB = $('#aviso-busqueda');
        if (avisoB) {
          if (r.aviso) { avisoB.textContent = r.aviso; avisoB.hidden = false; }
          else { avisoB.hidden = true; }
        }
        if (r.aviso_js) { mostrarAvisoJs(r.aviso_js); }

        // Lo que se pidió en la búsqueda inteligente queda ya escrito en el
        // filtro de la tabla: lo que se ve y lo que se descarga coinciden.
        if (r.objetivo && $('#filtro-ext')) { $('#filtro-ext').value = r.objetivo; }

        $('#panel-progreso').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return siguientePaso();
      })
      .catch(function (err) {
        terminar();
        mostrarError(err.message || 'Se produjo un error inesperado.');
      });
  }

  /* ------------------------------------------------- 5. Bucle de pasos (AJAX) */
  function siguientePaso() {
    if (estado.cancelado) { return Promise.resolve(); }

    // Cada paso se lanza con su propio controlador, para poder abortarlo
    // en cuanto se pulsa "Detener" en lugar de esperar a que responda.
    estado.aborto = (typeof AbortController !== 'undefined') ? new AbortController() : null;

    return api({ accion: 'paso', escaneo_id: estado.id }, estado.aborto ? estado.aborto.signal : null)
      .then(function (p) {
        // Si se canceló mientras la petición viajaba, su respuesta se ignora:
        // pintarla haría parecer que el escaneo sigue avanzando.
        if (estado.cancelado) { return; }
        if (!p.ok) { throw new Error(p.error || 'Error durante el escaneo.'); }

        pintarProgreso(p);
        if (p.nuevos && p.nuevos.length) { anadirCorreos(p.nuevos); }
        if (p.nuevos_tel && p.nuevos_tel.length) { anadirTelefonos(p.nuevos_tel); }

        if (p.terminado) { return cargarResultado(); }
        return siguientePaso();
      })
      .catch(function (err) {
        // Aborto pedido por el usuario: no es un error que mostrar.
        if (estado.cancelado || (err && err.name === 'AbortError')) { return; }
        terminar();
        mostrarError(err.message || 'Se interrumpió el escaneo.');
      });
  }

  function pintarProgreso(p) {
    var barra = $('#barra i');
    if (barra) { barra.style.width = Math.max(3, p.porcentaje) + '%'; }
    $('#c-revisadas').textContent  = p.revisadas;
    $('#c-correos').textContent    = p.correos;
    $('#c-pendientes').textContent = p.pendientes;
    if ($('#c-whatsapp')) { $('#c-whatsapp').textContent = p.whatsapps || 0; }
    var urlActual = $('#url-actual');
    if (urlActual) {
      urlActual.textContent = p.url_actual ? 'Analizando ' + recorta(p.url_actual, 90) : 'Preparando el radar…';
    }
  }

  /* ----------------------------------------------- 6. Pintado de los resultados */
  function anadirCorreos(lista) {
    var cuerpo = $('#tabla-cuerpo');
    if (!cuerpo) { return; }

    $('#panel-resultados').classList.remove('oculto');
    $('#sin-resultados').classList.add('oculto');

    lista.forEach(function (c) {
      if (estado.vistos[c.correo]) { return; }
      estado.vistos[c.correo] = true;
      estado.correos.push(c);
      cuerpo.appendChild(crearFila(c, true));
    });

    actualizarChipsExt();
    actualizarResumen();
    // Las filas nuevas también obedecen al filtro que ya esté puesto, y las
    // cuentas de descarga se mantienen al día mientras el escaneo avanza.
    aplicarFiltros();
  }

  function crearFila(c, conPing) {
    var tr = document.createElement('tr');
    tr.className = conPing ? 'ping' : '';
    tr.dataset.correo  = c.correo;
    tr.dataset.dominio = c.dominio;
    tr.dataset.tipo    = c.tipo || 'personal';
    tr.dataset.busca   = (c.correo + ' ' + c.dominio + ' ' + (c.metodo || '') + ' ' + (c.url || '')).toLowerCase();

    var mx = c.mx === null || typeof c.mx === 'undefined'
      ? '<span class="chip chip-gris">sin verificar</span>'
      : (c.mx ? '<span class="chip chip-neon">MX ok</span>' : '<span class="chip chip-rojo">sin MX</span>');

    // El método de detección ya no ocupa una columna: viaja en el "title"
    // del correo para que la tabla quepa entera en pantalla.
    var metodosTexto = String(c.metodo || '').split(',').filter(Boolean).join(' · ');
    if (metodosTexto) { metodosTexto = 'Detectado por: ' + metodosTexto; }

    var conf = parseInt(c.confianza, 10) || 0;

    tr.dataset.ext     = extensionDe(c.dominio);
    tr.dataset.niveles = c.niveles || '';

    tr.innerHTML =
      '<td class="col-check"><input type="checkbox" class="sel" aria-label="Seleccionar ' + esc(c.correo) + '"></td>' +
      '<td><span class="celda-correo" title="' + esc(metodosTexto) + '">' + esc(c.correo) + '</span></td>' +
      '<td class="col-dominio">' + esc(c.dominio) + '</td>' +
      '<td class="celda-niveles">' + pintarNiveles(c.niveles) + '</td>' +
      '<td><span class="chip ' + (c.tipo === 'generico' ? '' : 'chip-neon') + '">' +
          (c.tipo === 'generico' ? 'Genérico' : 'Personal') + '</span></td>' +
      '<td class="col-conf"><div class="confianza"><span class="pista"><i style="width:' + conf + '%"></i></span><b>' + conf + '</b></div></td>' +
      '<td class="col-mx">' + mx + '</td>' +
      '<td class="col-url celda-url"><a href="' + esc(c.url) + '" target="_blank" rel="noopener nofollow" title="' + esc(c.url) + '">' + esc(recorta(c.url, 44)) + '</a></td>';

    tr.querySelector('.sel').addEventListener('change', function () {
      tr.classList.toggle('marcada', this.checked);
      actualizarResumen();
    });
    return tr;
  }

  /* ------------------------------ Telefonos y WhatsApp ------------------- */
  function anadirTelefonos(lista) {
    var cuerpo = $('#tabla-tel');
    if (!cuerpo) { return; }

    $('#panel-resultados').classList.remove('oculto');
    $('#sin-telefonos').classList.add('oculto');

    lista.forEach(function (t) {
      if (estado.vistosTel[t.numero]) { return; }
      estado.vistosTel[t.numero] = true;
      estado.telefonos.push(t);
      cuerpo.appendChild(crearFilaTel(t, true));
    });

    actualizarFiltroPaises();
    actualizarResumen();
  }

  function crearFilaTel(t, conPing) {
    var tr = document.createElement('tr');
    tr.className = conPing ? 'ping' : '';
    tr.dataset.numero = t.numero;
    tr.dataset.pais   = t.pais || '';
    tr.dataset.wa     = t.whatsapp ? 'si' : 'no';
    tr.dataset.busca  = (t.numero + ' ' + t.formato + ' ' + (t.pais || '') + ' ' +
                         (t.metodo || '') + ' ' + (t.url || '')).toLowerCase();

    var conf = parseInt(t.confianza, 10) || 0;
    var tipo = t.whatsapp
      ? '<span class="chip chip-wa">WhatsApp</span>'
      : '<span class="chip chip-gris">Teléfono</span>';

    tr.innerHTML =
      '<td class="col-check"><input type="checkbox" class="sel-tel" aria-label="Seleccionar ' + esc(t.numero) + '"></td>' +
      '<td><span class="celda-correo">' + esc(t.formato || t.numero) + '</span></td>' +
      '<td>' + esc(t.pais || '—') + '</td>' +
      '<td>' + tipo + '</td>' +
      '<td class="col-conf"><div class="confianza"><span class="pista"><i style="width:' + conf + '%"></i></span><b>' + conf + '</b></div></td>' +
      '<td class="col-url celda-url"><a href="' + esc(t.url) + '" target="_blank" rel="noopener nofollow" title="' + esc(t.url) + '">' + esc(recorta(t.url, 40)) + '</a></td>' +
      '<td><a class="btn btn-neon btn-peq" href="' + esc(t.enlace_wa) + '" target="_blank" rel="noopener nofollow">Chat</a></td>';

    tr.querySelector('.sel-tel').addEventListener('change', function () {
      tr.classList.toggle('marcada', this.checked);
      actualizarResumen();
    });
    return tr;
  }

  /* Carga la tabla definitiva (ordenada por confianza) al terminar */
  function cargarResultado() {
    return api({ accion: 'resultado', escaneo_id: estado.id })
      .then(function (r) {
        terminar();
        if (!r.ok) { throw new Error(r.error || 'No se pudieron cargar los resultados.'); }

        estado.correos   = r.correos || [];
        estado.telefonos = r.telefonos || [];
        estado.vistos    = Object.create(null);
        estado.vistosTel = Object.create(null);

        var cuerpo = $('#tabla-cuerpo');
        cuerpo.innerHTML = '';
        estado.correos.forEach(function (c) {
          estado.vistos[c.correo] = true;
          cuerpo.appendChild(crearFila(c, false));
        });

        var cuerpoTel = $('#tabla-tel');
        if (cuerpoTel) {
          cuerpoTel.innerHTML = '';
          estado.telefonos.forEach(function (t) {
            estado.vistosTel[t.numero] = true;
            cuerpoTel.appendChild(crearFilaTel(t, false));
          });
        }

        $('#panel-resultados').classList.remove('oculto');
        $('#sin-resultados').classList.toggle('oculto', estado.correos.length > 0);
        $('#sin-telefonos').classList.toggle('oculto', estado.telefonos.length > 0);

        pintarExtras(r.enlaces_wa, r.redes);
        actualizarChipsExt();
        actualizarFiltroPaises();
        actualizarResumen();
        aplicarFiltros();
        aplicarFiltrosTel();

        $('#barra i').style.width = '100%';
        $('#url-actual').textContent = 'Escaneo completado · ' + r.revisadas + ' página(s) analizada(s)';
        $('#panel-resultados').scrollIntoView({ behavior: 'smooth', block: 'start' });
      })
      .catch(function (err) {
        terminar();
        mostrarError(err.message);
      });
  }

  /**
   * Pinta los enlaces de WhatsApp sin numero (enlaces cortos y grupos) y los
   * perfiles sociales encontrados durante el escaneo.
   */
  function pintarExtras(enlacesWa, redes) {
    var cajaWa = $('#caja-enlaces-wa');
    if (cajaWa) {
      if (enlacesWa && enlacesWa.length) {
        cajaWa.classList.remove('oculto');
        $('#lista-enlaces-wa').innerHTML = enlacesWa.map(function (u) {
          var nombre = u.indexOf('chat.whatsapp.com') !== -1 ? 'Grupo de WhatsApp' : 'Enlace corto';
          return '<li><a href="' + esc(u) + '" target="_blank" rel="noopener nofollow">' + nombre + ' · ' +
                 esc(recorta(u.replace(/^https?:\/\//, ''), 38)) + '</a></li>';
        }).join('');
      } else {
        cajaWa.classList.add('oculto');
      }
    }

    var caja = $('#extras');
    if (!caja) { return; }
    if (redes && redes.length) {
      caja.classList.remove('oculto');
      var lista = $('#lista-redes');
      if (lista) {
        lista.innerHTML = redes.map(function (u) {
          var nombre = (u.replace(/^https?:\/\/(www\.)?/, '').split('/')[0] || u);
          return '<li><a href="' + esc(u) + '" target="_blank" rel="noopener nofollow">' + esc(nombre) + '</a></li>';
        }).join('');
      }
    } else {
      caja.classList.add('oculto');
    }
  }

  function terminar() {
    estado.corriendo = false;
    document.body.classList.remove('escaneando');
    var btn = $('#btn-extraer');
    btn.classList.remove('cargando');
    btn.removeAttribute('aria-busy');
  }

  /** Igual que terminar(), pero además congela el panel de progreso. */
  function detener() {
    terminar();
    var panel = $('#panel-progreso');
    if (panel) { panel.classList.add('detenido'); }
  }

  function mostrarError(mensaje) {
    var caja = $('#error-escaneo');
    caja.querySelector('span').textContent = mensaje;
    caja.classList.remove('oculto');
    caja.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function mostrarAvisoJs(mensaje) {
    var caja = $('#aviso-js');
    if (!caja) { return; }
    caja.querySelector('span').textContent = mensaje;
    caja.classList.remove('oculto');
  }

  function reiniciarPanel() {
    estado.correos   = [];
    estado.telefonos = [];
    estado.vistos    = Object.create(null);
    estado.vistosTel = Object.create(null);

    $('#error-escaneo').classList.add('oculto');
    $('#panel-resultados').classList.add('oculto');

    // El panel puede venir congelado de un escaneo detenido a mano.
    var panelProg = $('#panel-progreso');
    if (panelProg) { panelProg.classList.remove('detenido'); }
    var btnCancelar = $('#btn-cancelar');
    if (btnCancelar) { btnCancelar.disabled = false; btnCancelar.textContent = 'Detener'; }
    ['#sin-resultados', '#sin-telefonos', '#sin-coincidencias', '#sin-coincidencias-tel',
     '#extras', '#caja-enlaces-wa'].forEach(function (sel) {
      if ($(sel)) { $(sel).classList.add('oculto'); }
    });

    $('#tabla-cuerpo').innerHTML = '';
    if ($('#tabla-tel')) { $('#tabla-tel').innerHTML = ''; }
    $('#barra i').style.width = '3%';
    ['#c-revisadas', '#c-correos', '#c-pendientes', '#c-whatsapp'].forEach(function (sel) {
      if ($(sel)) { $(sel).textContent = '0'; }
    });

    if ($('#chips-ext-res')) { $('#chips-ext-res').hidden = true; $('#chips-ext-res').innerHTML = ''; }
    if ($('#filtro-ext')) { $('#filtro-ext').value = ''; }
    if ($('#filtro-pais'))    { $('#filtro-pais').innerHTML = '<option value="">Todos los países</option>'; }
    ['#buscar', '#buscar-tel'].forEach(function (sel) { if ($(sel)) { $(sel).value = ''; } });
  }

  /* --------------------------------------------- 7. Buscador, filtros y seleccion */

  /* Niveles educativos: se resaltan los que interesan para vender a secundaria. */
  var NIVELES = {
    preprimaria:   'Preprimaria',
    primaria:      'Primaria',
    basicos:       'Básicos',
    diversificado: 'Diversificado',
    superior:      'Superior'
  };

  function pintarNiveles(guardados) {
    var lista = String(guardados || '').split(',').filter(Boolean);
    if (!lista.length) { return '<span class="chip chip-gris">—</span>'; }
    return lista.map(function (n) {
      var fuerte = (n === 'basicos' || n === 'diversificado');
      return '<span class="chip ' + (fuerte ? 'chip-neon' : 'chip-gris') + '">' +
             esc(NIVELES[n] || n) + '</span>';
    }).join(' ');
  }

  /* Segundos niveles habituales: colegio.edu.gt => "edu.gt", tienda.com => "com". */
  var SEGUNDOS = ['com','edu','gob','gov','org','net','mil','int','ac','co','or','ne','go','nom','info','web'];

  function extensionDe(dominio) {
    var p = String(dominio || '').toLowerCase().split('.');
    if (p.length < 2) { return p[0] || ''; }
    var tld = p[p.length - 1], sld = p[p.length - 2];
    if (p.length >= 3 && tld.length <= 3 && SEGUNDOS.indexOf(sld) !== -1) { return sld + '.' + tld; }
    return tld;
  }

  /* Convierte ".com, edu.gt  *.org" en ['com','edu.gt','org']. '' o "todos" = sin filtro. */
  function listaExtensiones(texto) {
    texto = String(texto || '').toLowerCase().trim();
    if (!texto || texto === '*' || texto === 'todos' || texto === 'todas' || texto === 'todo') { return []; }
    return texto.split(/[\s,;|/]+/).map(function (x) {
      return x.replace(/^[*@]+/, '').replace(/^\.+|\.+$/g, '').replace(/[^a-z0-9.\-]/g, '');
    }).filter(function (x) { return x && x !== '*' && x !== 'todos' && x !== 'todas' && x !== 'todo'; });
  }

  function coincideExt(dominio, exts) {
    if (!exts.length) { return true; }
    dominio = String(dominio || '').toLowerCase();
    for (var i = 0; i < exts.length; i++) {
      if (dominio === exts[i] || dominio.slice(-(exts[i].length + 1)) === '.' + exts[i]) { return true; }
    }
    return false;
  }

  /**
   * Botones con las terminaciones de dominio realmente encontradas.
   *
   * Importante: se agrupa por TERMINACIÓN, no por dominio. Una sola ficha
   * «.edu.gt» reúne colegio1.edu.gt, liceo.edu.gt, sub.universidad.edu.gt y
   * cuantos dominios haya acabados así; por eso cada ficha enseña los correos
   * y, entre paréntesis, de cuántos dominios distintos salen.
   */
  function actualizarChipsExt() {
    var caja = $('#chips-ext-res');
    if (!caja) { return; }

    var cuenta = {};     // terminación -> nº de correos
    var dominios = {};   // terminación -> { dominio: true }
    estado.correos.forEach(function (c) {
      var x = extensionDe(c.dominio);
      if (!x) { return; }
      cuenta[x] = (cuenta[x] || 0) + 1;
      if (!dominios[x]) { dominios[x] = {}; }
      dominios[x][c.dominio] = true;
    });

    var claves = Object.keys(cuenta).sort(function (a, b) { return cuenta[b] - cuenta[a] || a.localeCompare(b); });
    if (!claves.length) { caja.hidden = true; caja.innerHTML = ''; return; }

    caja.hidden = false;
    caja.innerHTML = '<button type="button" class="chip-ext chip-todos" data-ext="">Todas (' + estado.correos.length + ')</button>' +
      claves.slice(0, 20).map(function (x) {
        var nDom = Object.keys(dominios[x]).length;
        var titulo = cuenta[x] + ' correo' + (cuenta[x] === 1 ? '' : 's') + ' de ' + nDom +
                     ' dominio' + (nDom === 1 ? '' : 's') + ' distinto' + (nDom === 1 ? '' : 's') +
                     ': ' + Object.keys(dominios[x]).slice(0, 8).join(', ');
        return '<button type="button" class="chip-ext" data-ext="' + esc(x) + '" title="' + esc(titulo) + '">' +
               '.' + esc(x) + ' <i>' + cuenta[x] + '</i>' +
               (nDom > 1 ? '<u>' + nDom + ' dominios</u>' : '') + '</button>';
      }).join('');

    marcarChipsActivos();
  }

  function marcarChipsActivos() {
    var exts = listaExtensiones($('#filtro-ext') ? $('#filtro-ext').value : '');
    $$('#chips-ext-res .chip-ext').forEach(function (b) {
      var x = b.dataset.ext;
      b.classList.toggle('activo', x ? exts.indexOf(x) !== -1 : exts.length === 0);
    });
  }

  function actualizarFiltroPaises() {
    var sel = $('#filtro-pais');
    if (!sel) { return; }
    var actual = sel.value;
    var paises = {};
    estado.telefonos.forEach(function (t) { paises[t.pais || '—'] = (paises[t.pais || '—'] || 0) + 1; });

    var claves = Object.keys(paises).sort();
    sel.innerHTML = '<option value="">Todos los países (' + estado.telefonos.length + ')</option>' +
      claves.map(function (p) {
        return '<option value="' + esc(p) + '">' + esc(p) + ' (' + paises[p] + ')</option>';
      }).join('');
    if (actual && paises[actual]) { sel.value = actual; }
  }

  function aplicarFiltrosTel() {
    if (!$('#tabla-tel')) { return; }
    var texto = ($('#buscar-tel') ? $('#buscar-tel').value : '').trim().toLowerCase();
    var pais  = $('#filtro-pais') ? $('#filtro-pais').value : '';
    var wa    = $('#filtro-wa') ? $('#filtro-wa').value : '';
    var visibles = 0;

    $$('#tabla-tel tr').forEach(function (tr) {
      var ver = (!texto || tr.dataset.busca.indexOf(texto) !== -1)
             && (!pais  || tr.dataset.pais === pais)
             && (!wa    || tr.dataset.wa === wa);
      tr.style.display = ver ? '' : 'none';
      if (ver) { visibles++; }
    });

    var vacio = $('#sin-coincidencias-tel');
    if (vacio) { vacio.classList.toggle('oculto', visibles > 0 || !estado.telefonos.length); }
  }

  function filasTelVisibles() {
    return $$('#tabla-tel tr').filter(function (tr) { return tr.style.display !== 'none'; });
  }

  function seleccionadosTel() {
    return filasTelVisibles()
      .filter(function (tr) { return tr.querySelector('.sel-tel').checked; })
      .map(function (tr) { return tr.dataset.numero; });
  }

  function aplicarFiltros() {
    var texto = ($('#buscar') ? $('#buscar').value : '').trim().toLowerCase();
    var exts  = listaExtensiones($('#filtro-ext') ? $('#filtro-ext').value : '');
    var tipo  = $('#filtro-tipo') ? $('#filtro-tipo').value : '';
    var nivel = $('#filtro-nivel') ? $('#filtro-nivel').value : '';
    var buscados = nivel && nivel !== '_sin' ? nivel.split(',') : [];
    var visibles = 0;

    $$('#tabla-cuerpo tr').forEach(function (tr) {
      var suyos = String(tr.dataset.niveles || '').split(',').filter(Boolean);
      var okNivel = true;
      if (nivel === '_sin')      { okNivel = suyos.length === 0; }
      else if (buscados.length)  {
        okNivel = buscados.some(function (n) { return suyos.indexOf(n) !== -1; });
      }

      var okTexto = !texto || tr.dataset.busca.indexOf(texto) !== -1;
      var okExt   = coincideExt(tr.dataset.dominio, exts);
      var okTipo  = !tipo || tr.dataset.tipo === tipo;
      var ver = okTexto && okExt && okTipo && okNivel;
      tr.style.display = ver ? '' : 'none';
      if (ver) { visibles++; }
    });

    marcarChipsActivos();

    var vacio = $('#sin-coincidencias');
    if (vacio) { vacio.classList.toggle('oculto', visibles > 0 || !estado.correos.length); }

    // La descarga sigue exactamente a lo que se ve: se dice cuántos saldrían
    // y de cuántos dominios distintos, que es la duda de siempre.
    var dominiosVistos = {};
    $$('#tabla-cuerpo tr').forEach(function (tr) {
      if (tr.style.display !== 'none') { dominiosVistos[tr.dataset.dominio] = true; }
    });
    var nDom = Object.keys(dominiosVistos).length;

    var aviso = $('#n-filtrados');
    if (aviso) {
      aviso.textContent = (exts.length || tipo || texto || nivel)
        ? visibles + ' de ' + estado.correos.length + ' tras el filtro'
        : '';
    }

    var estadoExt = $('#filtro-ext-estado');
    if (estadoExt) {
      if (!estado.correos.length) {
        estadoExt.textContent = '';
      } else if (exts.length) {
        estadoExt.textContent = 'Se descargarán ' + visibles + ' correo' + (visibles === 1 ? '' : 's') +
          ' de ' + nDom + ' dominio' + (nDom === 1 ? '' : 's') + ' acabado' + (nDom === 1 ? '' : 's') +
          ' en ' + exts.map(function (x) { return '.' + x; }).join(', ');
      } else {
        estadoExt.textContent = 'Se descargarán los ' + visibles + ' correos de ' + nDom +
          ' dominio' + (nDom === 1 ? '' : 's') + ' (todas las terminaciones)';
      }
    }

    // Los botones de descarga llevan escrito cuántos correos se llevan.
    $$('.n-bajar').forEach(function (b) { b.textContent = estado.correos.length ? '(' + visibles + ')' : ''; });

    return visibles;
  }

  function filasVisibles() {
    return $$('#tabla-cuerpo tr').filter(function (tr) { return tr.style.display !== 'none'; });
  }

  function seleccionados() {
    return filasVisibles()
      .filter(function (tr) { return tr.querySelector('.sel').checked; })
      .map(function (tr) { return tr.dataset.correo; });
  }

  function actualizarResumen() {
    var n = seleccionados().length;
    if ($('#n-seleccionados')) {
      $('#n-seleccionados').textContent = n ? n + ' seleccionado' + (n === 1 ? '' : 's') : '';
    }
    var nt = seleccionadosTel().length;
    if ($('#n-sel-tel')) {
      $('#n-sel-tel').textContent = nt ? nt + ' seleccionado' + (nt === 1 ? '' : 's') : '';
    }

    if ($('#n-correos'))   { $('#n-correos').textContent = estado.correos.length; }
    if ($('#n-telefonos')) { $('#n-telefonos').textContent = estado.telefonos.length; }

    var wa = estado.telefonos.filter(function (t) { return t.whatsapp; }).length;
    var resumen = $('#resumen-hallazgos');
    if (resumen) {
      var partes = [];
      partes.push(estado.correos.length + ' correo' + (estado.correos.length === 1 ? '' : 's'));
      if (wa) { partes.push(wa + ' WhatsApp'); }
      var soloTel = estado.telefonos.length - wa;
      if (soloTel > 0) { partes.push(soloTel + (soloTel === 1 ? ' teléfono' : ' teléfonos')); }
      resumen.textContent = partes.join(' · ');
    }

    aplicarFiltros();
    aplicarFiltrosTel();
  }

  function copiar(lista, etiqueta) {
    if (!lista.length) { brindis('No hay correos que copiar.', 'error'); return; }
    var texto = lista.join('\n');

    var plural = lista.length === 1 ? etiqueta : (etiqueta === 'número' ? 'números' : etiqueta + 's');
    var exito = function () {
      brindis(lista.length + ' ' + plural + ' copiado' + (lista.length === 1 ? '' : 's') + ' al portapapeles');
    };

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(texto).then(exito).catch(function () { copiarRespaldo(texto, exito); });
    } else {
      copiarRespaldo(texto, exito);
    }
  }

  /* Respaldo para navegadores o conexiones sin API de portapapeles */
  function copiarRespaldo(texto, exito) {
    var area = document.createElement('textarea');
    area.value = texto;
    area.setAttribute('readonly', '');
    area.style.cssText = 'position:fixed;left:-9999px;opacity:0';
    document.body.appendChild(area);
    area.select();
    try { document.execCommand('copy'); exito(); }
    catch (e) { brindis('Tu navegador no permite copiar automáticamente.', 'error'); }
    document.body.removeChild(area);
  }

  /* --------------------------------------------------------- 8. Exportaciones */
  function exportar(formato, datos) {
    datos = datos || 'correos';
    if (!estado.id) { brindis('Primero haz una extracción.', 'error'); return; }

    var hay = (datos === 'correos') ? estado.correos.length
            : (datos === 'telefonos') ? estado.telefonos.length
            : (estado.correos.length + estado.telefonos.length);
    if (!hay) { brindis('No hay datos que descargar.', 'error'); return; }

    // Si hay filas marcadas se bajan esas; si no, se baja exactamente lo que
    // se está viendo (el filtro de extensiones manda sobre la descarga).
    var marcados = (datos === 'telefonos') ? seleccionadosTel() : seleccionados();
    if (!marcados.length && datos !== 'todo') {
      marcados = (datos === 'telefonos')
        ? filasTelVisibles().map(function (tr) { return tr.dataset.numero; })
        : filasVisibles().map(function (tr) { return tr.dataset.correo; });
    }
    var totalDatos = (datos === 'telefonos') ? estado.telefonos.length : estado.correos.length;
    if (datos !== 'todo' && !marcados.length) { brindis('Ningún dato pasa el filtro actual.', 'error'); return; }
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = CR.apiExportar;
    form.style.display = 'none';

    var campos = {
      csrf: CR.csrf,
      escaneo_id: estado.id,
      token: estado.token,
      formato: formato,
      datos: datos,
      seleccion: (datos !== 'todo' && marcados.length && marcados.length < totalDatos) ? marcados.join(',') : ''
    };
    Object.keys(campos).forEach(function (k) {
      var i = document.createElement('input');
      i.type = 'hidden'; i.name = k; i.value = campos[k];
      form.appendChild(i);
    });

    document.body.appendChild(form);
    form.submit();
    setTimeout(function () { document.body.removeChild(form); }, 1500);

    brindis('Preparando la descarga en ' + formato.toUpperCase() + '…');
  }

  /* ------------------------------------------------------------ 9. Conexiones */
  function conectar() {
    var form = $('#form-radar');
    if (form) { form.addEventListener('submit', iniciarEscaneo); }

    var cancelar = $('#btn-cancelar');
    if (cancelar) {
      cancelar.addEventListener('click', function () {
        if (estado.cancelado || !estado.corriendo) { return; }
        estado.cancelado = true;

        // 1. Se corta la petición en curso para que su respuesta no repinte
        //    el progreso y parezca que el escaneo continúa.
        if (estado.aborto) {
          try { estado.aborto.abort(); } catch (err) { /* navegador antiguo */ }
          estado.aborto = null;
        }

        // 2. Se congela el panel y se deja claro que ya está detenido.
        detener();
        cancelar.disabled = true;
        cancelar.textContent = 'Detenido';
        var urlActual = $('#url-actual');
        if (urlActual) { urlActual.textContent = 'Escaneo detenido. Se conservan los resultados encontrados hasta aquí.'; }

        // 3. Se avisa al servidor para que no siga rastreando páginas.
        api({ accion: 'cancelar', escaneo_id: estado.id }).catch(function () {});

        brindis('Escaneo detenido.');
        if (estado.correos.length || estado.telefonos.length) { cargarResultado(); }
      });
    }

    ['#buscar', '#filtro-ext', '#filtro-tipo', '#filtro-nivel'].forEach(function (sel) {
      var el = $(sel);
      if (el) { el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', aplicarFiltros); }
    });
    ['#buscar-tel', '#filtro-pais', '#filtro-wa'].forEach(function (sel) {
      var el = $(sel);
      if (el) { el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', aplicarFiltrosTel); }
    });

    /* Chips de extensión: suman o quitan esa extensión del filtro escrito. */
    var cajaChips = $('#chips-ext-res');
    if (cajaChips) {
      cajaChips.addEventListener('click', function (ev) {
        var b = ev.target.closest('.chip-ext');
        if (!b) { return; }
        var campo = $('#filtro-ext');
        if (!campo) { return; }

        var x = b.dataset.ext;
        if (!x) { campo.value = ''; }
        else {
          var exts = listaExtensiones(campo.value);
          var i = exts.indexOf(x);
          if (i === -1) { exts.push(x); } else { exts.splice(i, 1); }
          campo.value = exts.map(function (e2) { return '.' + e2; }).join(', ');
        }
        aplicarFiltros();
      });
    }

    /* Vista compacta <-> vista con todos los detalles. */
    var btnVista = $('#btn-vista');
    if (btnVista) {
      btnVista.addEventListener('click', function () {
        var detalle = false;
        ['#tabla-correos', '#tabla-telefonos'].forEach(function (sel) {
          var t = $(sel);
          if (t) { t.classList.toggle('tabla-compacta'); detalle = !t.classList.contains('tabla-compacta'); }
        });
        btnVista.textContent = detalle ? 'Vista compacta' : 'Ver detalles';
        btnVista.setAttribute('aria-pressed', detalle ? 'true' : 'false');
        try { localStorage.setItem('cr_vista', detalle ? 'detalle' : 'compacta'); } catch (e) { /* sin permiso */ }
      });

      try {
        if (localStorage.getItem('cr_vista') === 'detalle') { btnVista.click(); }
      } catch (e) { /* sin permiso */ }
    }

    /* Pestanas de resultados: correos / WhatsApp */
    $$('.pestanas-res button').forEach(function (b) {
      b.addEventListener('click', function () {
        $$('.pestanas-res button').forEach(function (x) {
          x.classList.remove('activa');
          x.setAttribute('aria-selected', 'false');
        });
        $$('.panel-res').forEach(function (p) { p.classList.remove('activo'); });
        b.classList.add('activa');
        b.setAttribute('aria-selected', 'true');
        var destino = document.getElementById(b.dataset.panel);
        if (destino) { destino.classList.add('activo'); }
      });
    });

    var todosTel = $('#sel-todos-tel');
    if (todosTel) {
      todosTel.addEventListener('change', function () {
        var v = this.checked;
        filasTelVisibles().forEach(function (tr) {
          tr.querySelector('.sel-tel').checked = v;
          tr.classList.toggle('marcada', v);
        });
        actualizarResumen();
      });
    }

    var copiarTel = $('#btn-copiar-tel');
    if (copiarTel) {
      copiarTel.addEventListener('click', function () {
        var marcados = seleccionadosTel();
        if (marcados.length) { copiar(marcados, 'número'); }
        else { copiar(filasTelVisibles().map(function (tr) { return tr.dataset.numero; }), 'número'); }
      });
    }

    var todos = $('#sel-todos');
    if (todos) {
      todos.addEventListener('change', function () {
        var v = this.checked;
        filasVisibles().forEach(function (tr) {
          tr.querySelector('.sel').checked = v;
          tr.classList.toggle('marcada', v);
        });
        actualizarResumen();
      });
    }

    var copiarTodo = $('#btn-copiar');
    if (copiarTodo) {
      copiarTodo.addEventListener('click', function () {
        var marcados = seleccionados();
        if (marcados.length) { copiar(marcados, 'correo'); }
        else { copiar(filasVisibles().map(function (tr) { return tr.dataset.correo; }), 'correo'); }
      });
    }

    $$('[data-exportar]').forEach(function (b) {
      b.addEventListener('click', function () {
        exportar(this.getAttribute('data-exportar'), this.getAttribute('data-datos') || 'correos');
      });
    });

    // Atajo: Ctrl/Cmd + Enter lanza la extraccion desde el campo de URL
    var campo = $('#url');
    if (campo) {
      campo.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { iniciarEscaneo(e); }
      });
      // Pegar un enlace y extraer: si el campo esta vacio, foco automatico
      if (!campo.value) { setTimeout(function () { campo.focus({ preventScroll: true }); }, 350); }
    }
  }

  /* ------------------------------------------------------------- 10. Arranque */
  iniciarTema();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', conectar);
  } else {
    conectar();
  }
})();

/* ===========================================================================
   13. Aplicación instalable (PWA)
   Registra el service worker y ofrece la instalación nada más entrar, sin
   que haya que buscarla en el menú del navegador.
   =========================================================================== */
(function () {
  var CR = window.CR || {};

  /* --- Service worker: hace que la app se pueda instalar y abra sin red --- */
  /* Los service workers solo funcionan en contextos seguros: HTTPS, o
     localhost durante las pruebas. */
  if ('serviceWorker' in navigator && CR.sw && window.isSecureContext) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(CR.sw, { scope: CR.base || './' })
        .catch(function () { /* sin service worker la web sigue funcionando */ });
    });
  }

  var caja = document.getElementById('instalar-app');
  if (!caja) { return; }

  var CLAVE = 'kaptor-instalar-oculto';
  var boton = document.getElementById('instalar-si');
  var luego = document.getElementById('instalar-no');
  var pista = document.getElementById('instalar-pista');
  var evento = null;

  function yaInstalada() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
  }

  function descartada() {
    try {
      var hasta = parseInt(localStorage.getItem(CLAVE) || '0', 10);
      return hasta > Date.now();
    } catch (e) { return false; }
  }

  function ocultar(dias) {
    caja.hidden = true;
    if (dias) {
      try { localStorage.setItem(CLAVE, String(Date.now() + dias * 86400000)); } catch (e) {}
    }
  }

  function mostrar() {
    if (yaInstalada() || descartada()) { return; }
    caja.hidden = false;
    requestAnimationFrame(function () { caja.classList.add('visible'); });
  }

  /* Android, Windows, macOS y Linux con Chrome o Edge. */
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    evento = e;
    if (boton) { boton.hidden = false; }
    mostrar();
  });

  if (boton) {
    boton.addEventListener('click', function () {
      if (!evento) { return; }
      evento.prompt();
      evento.userChoice.then(function (r) {
        ocultar(r && r.outcome === 'accepted' ? 365 : 7);
        evento = null;
      });
    });
  }
  if (luego) { luego.addEventListener('click', function () { ocultar(7); }); }

  window.addEventListener('appinstalled', function () { ocultar(365); });

  /* iPhone y iPad: Safari no ofrece instalación automática, hay que explicar
     los dos toques. Se muestra solo en Safari, no dentro de otras apps. */
  var esIOS = /iPad|iPhone|iPod/.test(navigator.userAgent)
           || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var esSafari = /^((?!chrome|android|crios|fxios|edgios).)*safari/i.test(navigator.userAgent);
  if (esIOS && esSafari && !yaInstalada() && !descartada()) {
    if (boton) { boton.hidden = true; }
    if (pista) {
      pista.textContent = 'Toca el botón Compartir y elige «Añadir a pantalla de inicio».';
    }
    setTimeout(mostrar, 1200);
  }
})();

/* ===========================================================================
   15. Campo de entrada múltiple
   El mismo campo admite una web, una lista de webs (una por línea) o unas
   palabras para buscar. Crece solo según lo que se escriba.
   =========================================================================== */
(function () {
  var campo = document.getElementById('url');
  if (!campo || campo.tagName !== 'TEXTAREA') { return; }

  function ajustarAlto() {
    campo.style.height = 'auto';
    var alto = Math.min(campo.scrollHeight, 260);
    campo.style.height = Math.max(54, alto) + 'px';
  }

  campo.addEventListener('input', ajustarAlto);
  campo.addEventListener('paste', function () { setTimeout(ajustarAlto, 0); });
  ajustarAlto();

  /* Enter envía; Mayúsculas+Enter añade otra línea para seguir la lista. */
  campo.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      var form = document.getElementById('form-radar');
      if (form) { form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true })); }
    }
  });

  /* Botones de la búsqueda inteligente: .edu.gt, .com.gt, .gob.gt… */
  var objetivo = document.getElementById('objetivo');
  var chipsObj = document.getElementById('chips-objetivo');
  if (objetivo && chipsObj) {
    var leerObj = function () {
      var t = (objetivo.value || '').toLowerCase().trim();
      if (!t || t === '*' || t === 'todos' || t === 'todas') { return []; }
      return t.split(/[\s,;|/]+/).map(function (x) {
        return x.replace(/^[*@]+/, '').replace(/^\.+|\.+$/g, '').replace(/[^a-z0-9.\-]/g, '');
      }).filter(Boolean);
    };
    var pintarObj = function () {
      var act = leerObj();
      Array.prototype.forEach.call(chipsObj.querySelectorAll('.chip-ext'), function (b) {
        var x = b.dataset.ext;
        b.classList.toggle('activo', x ? act.indexOf(x) !== -1 : act.length === 0);
      });
    };
    chipsObj.addEventListener('click', function (ev) {
      var b = ev.target.closest ? ev.target.closest('.chip-ext') : null;
      if (!b) { return; }
      var x = b.dataset.ext;
      if (!x) { objetivo.value = ''; pintarObj(); return; }
      var lista = leerObj();
      var i = lista.indexOf(x);
      if (i === -1) { lista.push(x); } else { lista.splice(i, 1); }
      objetivo.value = lista.map(function (e2) { return '.' + e2; }).join(', ');
      pintarObj();
    });
    objetivo.addEventListener('input', pintarObj);
    pintarObj();
  }

  /* Los tres botones de ejemplo rellenan el campo para enseñar cómo se usa. */
  Array.prototype.forEach.call(document.querySelectorAll('.caja-modos .modo'), function (b) {
    b.addEventListener('click', function () {
      campo.value = b.dataset.ejemplo || '';
      ajustarAlto();
      campo.focus();
      campo.setSelectionRange(campo.value.length, campo.value.length);
    });
  });
})();

/* ==========================================================================
   16. Depurar una lista de correos (depurar.php)
   Los botones de extensión escriben en el campo, la descarga reenvía el
   mismo formulario y el botón "Copiar" se lleva la lista ya limpia.
   ========================================================================== */
(function () {
  'use strict';

  var form = document.getElementById('form-depurar');
  if (!form) { return; }

  var campo = document.getElementById('extensiones');
  var chips = document.getElementById('chips-ext');

  /** Extensiones escritas en el campo, ya normalizadas. */
  function leer() {
    var t = (campo && campo.value ? campo.value : '').toLowerCase().trim();
    if (!t || t === '*' || t === 'todos' || t === 'todas' || t === 'todo') { return []; }
    return t.split(/[\s,;|/]+/).map(function (x) {
      return x.replace(/^[*@]+/, '').replace(/^\.+|\.+$/g, '').replace(/[^a-z0-9.\-]/g, '');
    }).filter(Boolean);
  }

  function escribir(lista) {
    if (!campo) { return; }
    campo.value = lista.map(function (x) { return '.' + x; }).join(', ');
    pintar();
  }

  function pintar() {
    if (!chips) { return; }
    var actuales = leer();
    Array.prototype.forEach.call(chips.querySelectorAll('.chip-ext'), function (b) {
      var x = b.dataset.ext;
      b.classList.toggle('activo', x ? actuales.indexOf(x) !== -1 : actuales.length === 0);
    });
  }

  if (chips) {
    chips.addEventListener('click', function (ev) {
      var b = ev.target.closest ? ev.target.closest('.chip-ext') : null;
      if (!b) { return; }
      var x = b.dataset.ext;
      if (!x) { escribir([]); return; }

      var lista = leer();
      var i = lista.indexOf(x);
      if (i === -1) { lista.push(x); } else { lista.splice(i, 1); }
      escribir(lista);
    });
  }
  if (campo) { campo.addEventListener('input', pintar); }
  pintar();

  /* Botones de palabras del modo web: colegios, universidades, academias. */
  var chipsPal = document.getElementById('chips-palabras');
  var campoPal = document.getElementById('contiene');
  if (chipsPal && campoPal) {
    chipsPal.addEventListener('click', function (ev) {
      var b = ev.target.closest ? ev.target.closest('.chip-ext') : null;
      if (!b) { return; }
      campoPal.value = b.dataset.palabras || '';
      Array.prototype.forEach.call(chipsPal.querySelectorAll('.chip-ext'), function (x) {
        x.classList.toggle('activo', x === b);
      });
      campoPal.focus();
    });
  }

  /* Descargas: se marca el formato y se reenvía el mismo formulario, así el
     servidor depura otra vez con los mismos filtros y devuelve el archivo. */
  var oculto = document.getElementById('descargar');
  Array.prototype.forEach.call(document.querySelectorAll('[data-bajar]'), function (b) {
    b.addEventListener('click', function () {
      if (!oculto) { return; }
      oculto.value = b.dataset.bajar;
      form.submit();
      // El navegador ya serializó el formulario: se limpia enseguida para que
      // el siguiente envío recalcule en vez de volver a bajar el archivo.
      oculto.value = '';
    });
  });

  /* Red de seguridad: un envío normal nunca descarga. */
  form.addEventListener('submit', function (ev) {
    if (!ev.submitter || ev.submitter.hasAttribute('data-bajar')) { return; }
    if (oculto) { oculto.value = ''; }
  });

  /* Copiar la lista limpia. */
  var btnCopiar = document.getElementById('btn-copiar-lista');
  var caja      = document.getElementById('lista-limpia');
  if (btnCopiar && caja) {
    btnCopiar.addEventListener('click', function () {
      var texto = caja.value;
      var n = texto ? texto.split('\n').length : 0;
      var listo = function () { avisar(n + ' correo' + (n === 1 ? '' : 's') + ' copiado' + (n === 1 ? '' : 's')); };

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(texto).then(listo).catch(function () { respaldo(texto, listo); });
      } else {
        respaldo(texto, listo);
      }
    });
  }

  function respaldo(texto, listo) {
    var area = document.createElement('textarea');
    area.value = texto;
    area.setAttribute('readonly', '');
    area.style.cssText = 'position:fixed;left:-9999px;opacity:0';
    document.body.appendChild(area);
    area.select();
    try { document.execCommand('copy'); listo(); }
    catch (e) { avisar('Tu navegador no permite copiar automáticamente.'); }
    document.body.removeChild(area);
  }

  function avisar(mensaje) {
    var brindis = document.getElementById('brindis');
    if (!brindis) { alert(mensaje); return; }
    brindis.querySelector('span').textContent = mensaje;
    brindis.classList.add('visible');
    setTimeout(function () { brindis.classList.remove('visible'); }, 3200);
  }

  /* "Vaciar" limpia también los resultados de la pantalla. */
  var limpiar = document.getElementById('btn-limpiar-todo');
  if (limpiar) {
    limpiar.addEventListener('click', function () {
      setTimeout(function () {
        var lista = document.getElementById('lista');
        if (lista) { lista.focus(); }
        pintar();
      }, 0);
    });
  }
})();

/* ==========================================================================
   17. Luz que sigue al cursor (solo decoración)
   Le pasa a la tarjeta dónde está el ratón en dos variables CSS. Si esto no
   se ejecutara, el degradado se queda centrado y no pasa nada: ninguna
   función de la página depende de ello.
   ========================================================================== */
(function () {
  'use strict';

  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
  if (!window.matchMedia || !window.matchMedia('(hover: hover)').matches) { return; }

  var tarjetas = document.querySelectorAll('.ventaja, .marca-dato, .modo-opcion');
  if (!tarjetas.length) { return; }

  Array.prototype.forEach.call(tarjetas, function (t) {
    t.addEventListener('pointermove', function (e) {
      var c = t.getBoundingClientRect();
      t.style.setProperty('--mx', ((e.clientX - c.left) / c.width * 100).toFixed(1) + '%');
      t.style.setProperty('--my', ((e.clientY - c.top) / c.height * 100).toFixed(1) + '%');
    });
    t.addEventListener('pointerleave', function () {
      t.style.removeProperty('--mx');
      t.style.removeProperty('--my');
    });
  });
})();

/* ==========================================================================
   18. Textos cortos en pantallas pequeñas
   El marcador de posición del campo principal está escrito para un monitor:
   en un teléfono ocupaba tres renglones y empujaba el botón fuera de la
   vista. Aquí se cambia por su versión corta, y se devuelve el largo si la
   pantalla crece (girar el teléfono, abrir en una tableta).
   ========================================================================== */
(function () {
  'use strict';

  var consulta = window.matchMedia ? window.matchMedia('(max-width: 620px)') : null;
  if (!consulta) { return; }

  // Cada campo con su versión corta. Si el campo no está en la página, se
  // salta sin más: esta lista vale para la portada y para el depurador.
  var campos = [
    // El campo principal va vacio a proposito, tambien en el telefono.
    { id: 'url',         corto: '' },
    { id: 'objetivo',    corto: 'Ej.: .edu.gt  ·  vacío = todos' },
    { id: 'extensiones', corto: 'Ej.: .com, .edu.gt  ·  vacío = todas' },
    { id: 'excluir',     corto: 'Ej.: .ru, .cn' },
    { id: 'contiene',    corto: 'Ej.: colegio, liceo, instituto' },
    { id: 'sin_palabra', corto: 'Ej.: tienda, banco' },
    { id: 'filtro-ext',  corto: 'Extensiones: .edu.gt, .com…' }
  ].map(function (c) {
    var el = document.getElementById(c.id);
    return el ? { el: el, corto: c.corto, largo: el.getAttribute('placeholder') || '' } : null;
  }).filter(Boolean);

  if (!campos.length) { return; }

  function ajustar() {
    campos.forEach(function (c) {
      c.el.setAttribute('placeholder', consulta.matches ? c.corto : c.largo);
    });
  }
  ajustar();

  if (consulta.addEventListener) { consulta.addEventListener('change', ajustar); }
  else if (consulta.addListener) { consulta.addListener(ajustar); }
})();

/* ==========================================================================
   19. El menú del teléfono
   Abre y cierra el panel de navegación. Se cierra solo al pulsar un enlace,
   al tocar fuera, con la tecla Escape y al ensanchar la ventana, para que
   nunca quede un panel abierto donde ya no hace falta.
   ========================================================================== */
(function () {
  'use strict';

  var boton = document.getElementById('menu-movil');
  var menu  = document.getElementById('menu-principal');
  if (!boton || !menu) { return; }

  function abrir(si) {
    menu.classList.toggle('abierto', si);
    boton.setAttribute('aria-expanded', si ? 'true' : 'false');
    boton.setAttribute('aria-label', si ? 'Cerrar el menú' : 'Abrir el menú');
    document.body.classList.toggle('menu-abierto', si);
  }

  boton.addEventListener('click', function (e) {
    e.stopPropagation();
    abrir(boton.getAttribute('aria-expanded') !== 'true');
  });

  // Pulsar un enlace cierra el panel: si no, se queda abierto sobre la
  // página nueva durante un instante y parece que algo falla.
  menu.addEventListener('click', function (e) {
    if (e.target.closest('a')) { abrir(false); }
  });

  document.addEventListener('click', function (e) {
    if (!menu.classList.contains('abierto')) { return; }
    if (!menu.contains(e.target) && e.target !== boton) { abrir(false); }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && menu.classList.contains('abierto')) {
      abrir(false);
      boton.focus();
    }
  });

  var ancha = window.matchMedia('(min-width: 901px)');
  var alCambiar = function () { if (ancha.matches) { abrir(false); } };
  if (ancha.addEventListener) { ancha.addEventListener('change', alCambiar); }
  else if (ancha.addListener) { ancha.addListener(alCambiar); }
})();

/* ---------------------------------------------------------------------------
   20. Los grupos del menú.

   Los <details> ya se abren y se cierran solos: esto solo añade los detalles
   que un menú de verdad necesita y que el navegador no da de fábrica —que al
   abrir uno se cierre el otro, que un clic fuera los cierre y que Escape
   devuelva el foco donde estaba—. Si este archivo no llegara a cargarse, el
   menú seguiría funcionando; solo perdería estos remates.
--------------------------------------------------------------------------- */
(function () {
  'use strict';

  var grupos = Array.prototype.slice.call(document.querySelectorAll('.menu-grupo'));
  if (!grupos.length) { return; }

  function cerrarTodos(menos) {
    grupos.forEach(function (g) { if (g !== menos) { g.open = false; } });
  }

  grupos.forEach(function (g) {
    g.addEventListener('toggle', function () {
      if (g.open) { cerrarTodos(g); }
    });
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.menu-grupo')) { cerrarTodos(null); }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    var abierto = grupos.filter(function (g) { return g.open; })[0];
    if (!abierto) { return; }
    // Escape aquí no debe llegar al panel del teléfono y cerrarlo entero:
    // primero se cierra el grupo y el foco vuelve a su botón.
    e.stopPropagation();
    abierto.open = false;
    var s = abierto.querySelector('summary');
    if (s) { s.focus(); }
  }, true);

  // En escritorio, el grupo se abre al pasar el ratón y se cierra al salir,
  // como cualquier menú de toda la vida. Se hace aquí y no con :hover en el
  // CSS porque así hay una sola condición que mande —el atributo `open`— y
  // porque el CSS a secas no llegaba a abrirlo.
  //
  // En el teléfono no se toca: allí se abre tocando, y un "pasar el ratón"
  // simulado por el navegador táctil dejaría todo desplegado.
  var ancha = window.matchMedia('(min-width: 901px)');
  var fino  = window.matchMedia('(hover: hover) and (pointer: fine)');

  grupos.forEach(function (g) {
    g.addEventListener('mouseenter', function () {
      if (ancha.matches && fino.matches) { g.open = true; }
    });
    g.addEventListener('mouseleave', function () {
      if (ancha.matches && fino.matches) { g.open = false; }
    });

    // En escritorio, el clic sobre el título NO debe cerrar lo que el ratón
    // acaba de abrir: pasas por encima, se despliega, haces clic en el título
    // y se te cierra en las narices. Con el ratón manda el ratón, así que el
    // clic no hace nada. En el teléfono y con el teclado sigue funcionando
    // como siempre, que es la única forma de abrirlo que hay allí.
    var titulo = g.querySelector('summary');
    if (titulo) {
      titulo.addEventListener('click', function (ev) {
        // Pulsar Intro sobre el título también dispara un 'click', y ese SÍ
        // tiene que abrirlo: es la única forma que tiene quien navega con
        // teclado. Se distinguen por `detail`, que vale 0 cuando el clic no
        // viene de un ratón de verdad.
        if (ev.detail === 0) { return; }
        if (ancha.matches && fino.matches) { ev.preventDefault(); }
      });
    }
  });
})();
