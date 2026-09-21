/**
 * Kaptor - Auditor web (navegador).
 *
 * Arranca la auditoría y va pidiendo pasos hasta que todas terminan. El
 * servidor hace una fase por llamada porque un hosting compartido no aguanta
 * veinticinco peticiones de red en una sola carga; aquí solo se encadena y se
 * va pintando el progreso.
 *
 * Sin dependencias: el resto de Kaptor tampoco las tiene.
 */
(function () {
  'use strict';

  var form = document.getElementById('form-auditor');
  if (!form || !window.CR_AUDITOR) { return; }

  var API      = window.CR_AUDITOR.api;
  var sitios   = document.getElementById('sitios');
  var rivales  = document.getElementById('rivales');
  var btn      = document.getElementById('btn-auditar');
  var btnParar = document.getElementById('btn-parar');
  var panel    = document.getElementById('aud-panel');
  var titulo   = document.getElementById('aud-titulo');
  var aviso    = document.getElementById('aud-aviso');
  var lista    = document.getElementById('aud-lista');
  var historial = document.getElementById('aud-historial');

  var enMarcha = false;
  var ids      = [];
  var parar    = false;

  // ------------------------------------------------------------------ ayudas

  function csrf() {
    var c = form.querySelector('input[name="csrf"]');
    return c ? c.value : '';
  }

  function pedir(cuerpo) {
    cuerpo.csrf = csrf();
    return fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(cuerpo)
    }).then(function (r) {
      return r.json().catch(function () {
        throw new Error('El servidor respondió algo que no se entiende.');
      });
    });
  }

  function texto(el, txt) { if (el) { el.textContent = txt; } }

  function mostrarAviso(msg) {
    if (!aviso) { return; }
    if (msg) { aviso.textContent = msg; aviso.classList.remove('oculto'); }
    else { aviso.classList.add('oculto'); }
  }

  function color(nota) {
    if (nota === null || nota === undefined) { return 'gris'; }
    if (nota >= 80) { return 'verde'; }
    if (nota >= 55) { return 'ambar'; }
    return 'rojo';
  }

  // ------------------------------------------------------------------ pintado

  function pintar(auditorias) {
    if (!lista) { return; }

    // Los competidores se cuelgan de su sitio principal en vez de salir sueltos.
    var principales = auditorias.filter(function (a) { return a.papel === 'principal'; });
    var comp = auditorias.filter(function (a) { return a.papel === 'competidor'; });

    lista.innerHTML = '';

    principales.forEach(function (a) {
      lista.appendChild(fila(a, false));
      comp.filter(function (c) { return c.lote === a.lote; })
          .forEach(function (c) { lista.appendChild(fila(c, true)); });
    });
  }

  function fila(a, esRival) {
    var art = document.createElement('article');
    art.className = 'aud-fila' + (esRival ? ' aud-rival' : '');

    var nota = document.createElement('div');
    nota.className = 'aud-nota aud-' + color(a.nota);
    nota.textContent = (a.nota === null || a.nota === undefined) ? '·' : String(a.nota);

    var datos = document.createElement('div');
    datos.className = 'aud-datos';

    var host = document.createElement('p');
    host.className = 'aud-host';
    host.textContent = (esRival ? 'vs ' : '') + (a.host || '—');

    var meta = document.createElement('p');
    meta.className = 'aud-meta';

    if (a.estado === 'listo') {
      meta.textContent = 'Listo';
    } else if (a.estado === 'error') {
      meta.textContent = a.error || 'No se pudo analizar';
      meta.classList.add('aud-meta-error');
    } else {
      meta.textContent = a.etiqueta || 'En cola';
    }

    datos.appendChild(host);
    datos.appendChild(meta);

    // Barra de avance, solo mientras se trabaja.
    if (a.estado !== 'listo' && a.estado !== 'error') {
      var barra = document.createElement('div');
      barra.className = 'aud-barra';
      var relleno = document.createElement('span');
      relleno.style.width = (a.progreso || 0) + '%';
      barra.appendChild(relleno);
      datos.appendChild(barra);
    }

    var botones = document.createElement('div');
    botones.className = 'aud-botones';
    if (a.estado === 'listo' && a.informe) {
      var ver = document.createElement('a');
      ver.className = 'btn btn-fino';
      ver.href = a.informe;
      ver.textContent = 'Ver informe';
      botones.appendChild(ver);
    }

    art.appendChild(nota);
    art.appendChild(datos);
    art.appendChild(botones);
    return art;
  }

  // ------------------------------------------------------------------- bucle

  function siguientePaso() {
    if (parar) { terminar('Detenido.'); return; }

    pedir({ accion: 'paso', ids: ids }).then(function (r) {
      if (!r.ok) {
        if (r.requiere_login) { window.location.href = 'login.php'; return; }
        terminar(r.error || 'No se pudo continuar.');
        return;
      }

      pintar(r.auditorias || []);

      var hechas = (r.auditorias || []).filter(function (a) {
        return a.estado === 'listo' || a.estado === 'error';
      }).length;
      var total = (r.auditorias || []).length;

      texto(titulo, r.terminado
        ? 'Análisis terminado'
        : 'Analizando… ' + hechas + ' de ' + total);

      if (r.terminado) { terminar(''); return; }

      // Una pausa corta: no sirve de nada machacar al servidor, que de todas
      // formas solo hace una fase por llamada.
      setTimeout(siguientePaso, 350);
    }).catch(function (e) {
      terminar(e.message || 'Se perdió la conexión con el servidor.');
    });
  }

  function terminar(mensaje) {
    enMarcha = false;
    parar = false;
    document.body.classList.remove('auditando');
    if (btn) { btn.disabled = false; btn.classList.remove('cargando'); }
    if (btnParar) { btnParar.classList.add('oculto'); }
    if (mensaje) { mostrarAviso(mensaje); }
  }

  // ------------------------------------------------------------------ arranque

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (enMarcha) { return; }

    var valor = (sitios && sitios.value || '').trim();
    if (!valor) {
      mostrarAviso('Escribe al menos una dirección web.');
      if (panel) { panel.classList.remove('oculto'); }
      if (sitios) { sitios.focus(); }
      return;
    }

    enMarcha = true;
    parar = false;
    ids = [];
    document.body.classList.add('auditando');
    if (btn) { btn.disabled = true; btn.classList.add('cargando'); }
    if (btnParar) { btnParar.classList.remove('oculto'); }
    if (panel) { panel.classList.remove('oculto'); }
    if (historial) { historial.classList.add('oculto'); }
    if (lista) { lista.innerHTML = ''; }
    mostrarAviso('');
    texto(titulo, 'Preparando…');

    pedir({
      accion: 'iniciar',
      sitios: valor,
      rivales: (rivales && rivales.value || '').trim()
    }).then(function (r) {
      if (!r.ok) {
        if (r.requiere_login) { window.location.href = 'login.php'; return; }
        terminar(r.error || 'No se pudo iniciar la auditoría.');
        return;
      }

      // El servidor devuelve los ids de todo lo que creó, sitios y
      // competidores incluidos: no hay nada que adivinar.
      ids = r.ids || (r.auditorias || []).map(function (a) { return a.id; });

      if (r.aviso) { mostrarAviso(r.aviso); }
      if (!ids.length) { terminar('No se pudo iniciar ninguna auditoría.'); return; }

      // Se marca el panel como en marcha y se lanza el bucle.
      panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      siguientePaso();
    }).catch(function (e) {
      terminar(e.message || 'No se pudo conectar con el servidor.');
    });
  });

  if (btnParar) {
    btnParar.addEventListener('click', function () {
      parar = true;
      texto(titulo, 'Deteniendo…');
      pedir({ accion: 'cancelar', ids: ids }).catch(function () { /* da igual */ });
    });
  }

  // Si alguien se va con una auditoría a medias, se avisa: el trabajo ya hecho
  // se guarda, pero lo que falte se queda sin terminar.
  window.addEventListener('beforeunload', function (ev) {
    if (!enMarcha) { return; }
    ev.preventDefault();
    ev.returnValue = '';
  });
})();
