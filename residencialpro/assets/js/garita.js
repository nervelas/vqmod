/*!
 * ResidencialPro — pantalla de garita.
 * Lectura de códigos QR con la cámara, captura de fotografía,
 * funcionamiento sin conexión y sincronización automática.
 */
(function () {
  'use strict';
  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const CLAVE_COLA = 'rp_garita_cola';

  const Garita = {};
  window.Garita = Garita;

  /* ----------------------------------------------------------- Cola local */
  function cola() {
    try { return JSON.parse(localStorage.getItem(CLAVE_COLA) || '[]'); } catch (e) { return []; }
  }
  function guardarCola(c) {
    try { localStorage.setItem(CLAVE_COLA, JSON.stringify(c.slice(-200))); } catch (e) {}
    pintarPendientes();
  }
  function pintarPendientes() {
    const n = cola().length;
    $$('[data-pendientes]').forEach((el) => {
      el.textContent = n;
      el.closest('[data-pendientes-caja]')?.toggleAttribute('hidden', n === 0);
    });
  }

  Garita.encolar = function (registro) {
    const c = cola();
    registro._local = Date.now() + '-' + Math.random().toString(36).slice(2, 8);
    c.push(registro);
    guardarCola(c);
    RP.aviso('Ingreso guardado en el dispositivo. Se enviará al recuperar la conexión.', 'info', 7);
  };

  Garita.sincronizar = async function () {
    const c = cola();
    if (!c.length || !navigator.onLine) return;
    const r = await RP.pedir('/api/garita/sincronizar', { method: 'POST', body: { registros: c } });
    if (r.ok) {
      guardarCola([]);
      if (r.guardados) RP.aviso(r.guardados + ' registro(s) sincronizados con el servidor.');
      if (typeof Garita.alSincronizar === 'function') Garita.alSincronizar();
    }
  };

  /* -------------------------------------------------------------- Cámara */
  let flujo = null;
  let leyendo = false;
  let detector = null;

  Garita.abrirCamara = async function (video, alLeer) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      RP.aviso('Este dispositivo no permite usar la cámara. Ingrese el código de 6 dígitos.', 'error');
      return false;
    }
    try {
      flujo = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false,
      });
    } catch (e) {
      RP.aviso('No se pudo abrir la cámara. Verifique los permisos del navegador.', 'error');
      return false;
    }
    video.srcObject = flujo;
    video.setAttribute('playsinline', '');
    await video.play().catch(() => {});
    if ('BarcodeDetector' in window) {
      try {
        detector = new window.BarcodeDetector({ formats: ['qr_code'] });
        leyendo = true;
        bucle(video, alLeer);
      } catch (e) { detector = null; }
    }
    // Safari en iPhone y Firefox no traen BarcodeDetector. Sin respaldo, la
    // cámara se abría y no leía nada: la función principal del sistema no
    // servía en medio parque de teléfonos. Se usa un decodificador propio.
    if (!detector && typeof jsQR === 'function') {
      leyendo = true;
      bucleJs(video, alLeer);
    } else if (!detector) {
      RP.aviso('Su navegador no lee códigos QR automáticamente. Escriba el código de 6 dígitos.', 'info', 8);
    }
    return true;
  };

  async function bucle(video, alLeer) {
    if (!leyendo) return;
    try {
      const codigos = await detector.detect(video);
      if (codigos && codigos.length) {
        const valor = codigos[0].rawValue;
        if (valor) {
          if (navigator.vibrate) navigator.vibrate(120);
          leyendo = false;
          alLeer(valor);
          return;
        }
      }
    } catch (e) { /* fotograma no legible */ }
    requestAnimationFrame(() => bucle(video, alLeer));
  }

  /**
   * Lectura por programa, para navegadores sin BarcodeDetector.
   * Analiza un fotograma cada 250 ms sobre un lienzo reducido: suficiente para
   * leer y liviano para un teléfono de garita.
   */
  function bucleJs(video, alLeer) {
    const lienzo = document.createElement('canvas');
    const ctx = lienzo.getContext('2d', { willReadFrequently: true });
    const paso = function () {
      if (!leyendo || !flujo) { return; }
      if (video.videoWidth) {
        const an = Math.min(480, video.videoWidth);
        const al = Math.round((video.videoHeight / video.videoWidth) * an);
        lienzo.width = an; lienzo.height = al;
        ctx.drawImage(video, 0, 0, an, al);
        try {
          const d = ctx.getImageData(0, 0, an, al);
          const r = jsQR(d.data, an, al, { inversionAttempts: 'dontInvert' });
          if (r && r.data) { leyendo = false; alLeer(r.data); return; }
        } catch (e) { /* un fotograma ilegible no debe cortar la lectura */ }
      }
      setTimeout(paso, 250);
    };
    setTimeout(paso, 250);
  }

  Garita.reanudar = function (video, alLeer) {
    if (!flujo) { return; }
    if (detector) { leyendo = true; bucle(video, alLeer); }
    else if (typeof jsQR === 'function') { leyendo = true; bucleJs(video, alLeer); }
  };

  Garita.cerrarCamara = function () {
    leyendo = false;
    if (flujo) { flujo.getTracks().forEach((t) => t.stop()); flujo = null; }
  };

  /**
   * Cámara sólo para la fotografía del visitante.
   *
   * No arranca la lectura de códigos: si lo hiciera, al encuadrar a alguien
   * que lleva el QR a la vista el lector dispararía el envío del formulario y
   * la pantalla se recargaría en plena captura.
   */
  Garita.abrirCamaraFoto = async function (video) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      RP.aviso('Este dispositivo no permite usar la cámara.', 'error');
      return false;
    }
    if (flujo) { Garita.cerrarCamara(); }
    try {
      flujo = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false,
      });
    } catch (e) {
      RP.aviso('No se pudo abrir la cámara. Revise los permisos del navegador.', 'error');
      return false;
    }
    leyendo = false;
    video.srcObject = flujo;
    video.setAttribute('playsinline', '');
    await video.play().catch(() => {});
    await Garita.esperarImagen(video);
    return true;
  };

  /** Sin fotogramas todavía, la captura saldría en blanco. */
  Garita.esperarImagen = function (video, ms) {
    if (video.videoWidth) return Promise.resolve(true);
    return new Promise((resolver) => {
      const reloj = setTimeout(() => resolver(!!video.videoWidth), ms || 4000);
      video.addEventListener('loadeddata', () => { clearTimeout(reloj); resolver(true); }, { once: true });
    });
  };

  /* Fotografía del visitante */
  Garita.tomarFoto = async function (video, canvas) {
    if (!video) return null;
    if (!video.videoWidth) { await Garita.esperarImagen(video, 2500); }
    if (!video.videoWidth) return null;
    const an = 640;
    const al = Math.round((video.videoHeight / video.videoWidth) * an);
    canvas.width = an; canvas.height = al;
    canvas.getContext('2d').drawImage(video, 0, 0, an, al);
    return canvas.toDataURL('image/jpeg', 0.78);
  };

  /* ----------------------------------------------------------- Validación */
  Garita.validar = async function (codigo) {
    if (!navigator.onLine) {
      return { ok: false, sinRed: true, mensaje: 'Sin conexión: registre el ingreso manualmente.' };
    }
    return RP.pedir('/api/garita/validar', { method: 'POST', body: { codigo } });
  };

  /* --------------------------------------------------------- Autocompletar */
  Garita.buscarPlaca = async function (placa) {
    if (!placa || placa.length < 4 || !navigator.onLine) return null;
    const r = await RP.pedir('/api/garita/placa?placa=' + encodeURIComponent(placa));
    return r.ok ? r.datos : null;
  };

  /* ------------------------------------------------------------- Arranque */
  document.addEventListener('DOMContentLoaded', () => {
    pintarPendientes();
    if (navigator.onLine) setTimeout(Garita.sincronizar, 1500);
    setInterval(() => { if (navigator.onLine) Garita.sincronizar(); }, 60000);

    // Teclado numérico grande
    $$('[data-teclado]').forEach((tec) => {
      const destino = document.querySelector(tec.dataset.teclado);
      tec.addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b || !destino) return;
        if (b.dataset.tecla === 'borrar') destino.value = destino.value.slice(0, -1);
        else if (b.dataset.tecla === 'limpiar') destino.value = '';
        else if (b.dataset.tecla) destino.value = (destino.value + b.dataset.tecla).slice(0, 6);
        destino.dispatchEvent(new Event('input', { bubbles: true }));
        if (navigator.vibrate) navigator.vibrate(12);
      });
    });

    // Autocompletado por placa
    $$('[data-placa]').forEach((inp) => {
      let temporizador = null;
      inp.addEventListener('input', () => {
        inp.value = inp.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '');
        clearTimeout(temporizador);
        temporizador = setTimeout(async () => {
          const d = await Garita.buscarPlaca(inp.value);
          if (!d) return;
          const casa = document.querySelector('[name=casa_id]');
          if (casa && d.casa_id) casa.value = d.casa_id;
          const veh = document.querySelector('[name=vehiculo]');
          if (veh && !veh.value) veh.value = [d.marca, d.linea, d.color].filter(Boolean).join(' ');
          RP.aviso('Vehículo registrado en la casa ' + (d.casa || ''), 'info', 4);
        }, 420);
      });
    });
  });
})();
