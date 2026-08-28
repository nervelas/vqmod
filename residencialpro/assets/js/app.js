/*!
 * ResidencialPro — interfaz (JavaScript vanilla ES2020, sin dependencias)
 */
(function () {
  'use strict';

  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const base = document.documentElement.dataset.base || '';
  const ruta = (p) => base + (p.startsWith('/') ? p : '/' + p);

  const RP = {
    base,
    ruta,
    token: () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
  };
  window.RP = RP;

  /* ------------------------------------------------------------ 1. FETCH */
  RP.pedir = async function (url, opciones = {}) {
    const cfg = Object.assign({ headers: {} }, opciones);
    cfg.headers['X-Requested-With'] = 'XMLHttpRequest';
    cfg.headers['Accept'] = 'application/json';
    if (cfg.body && !(cfg.body instanceof FormData)) {
      cfg.headers['Content-Type'] = 'application/json';
      cfg.headers['X-CSRF-Token'] = RP.token();
      if (typeof cfg.body !== 'string') cfg.body = JSON.stringify(cfg.body);
    } else if (cfg.body instanceof FormData) {
      cfg.headers['X-CSRF-Token'] = RP.token();
    }
    const r = await fetch(ruta(url), cfg);
    const texto = await r.text();
    let datos = {};
    try { datos = texto ? JSON.parse(texto) : {}; } catch (e) { datos = { ok: false, error: 'Respuesta no válida del servidor.' }; }
    if (!r.ok && !datos.error) datos.error = 'Error ' + r.status;
    return datos;
  };

  /* ------------------------------------------------------------ 2. TOASTS */
  RP.aviso = function (mensaje, tipo = 'ok', segundos = 5) {
    let caja = $('.toasts');
    if (!caja) {
      caja = document.createElement('div');
      caja.className = 'toasts';
      caja.setAttribute('role', 'status');
      caja.setAttribute('aria-live', 'polite');
      document.body.appendChild(caja);
    }
    const t = document.createElement('div');
    t.className = 'toast ' + tipo;
    t.innerHTML = '<div class="crecer"></div><button type="button" aria-label="Cerrar aviso">✕</button>';
    t.firstChild.textContent = mensaje;
    t.querySelector('button').addEventListener('click', () => quitar());
    caja.appendChild(t);
    const quitar = () => {
      t.classList.add('sale');
      setTimeout(() => t.remove(), 240);
    };
    setTimeout(quitar, segundos * 1000);
  };

  /* ------------------------------------------------------------ 3. MODALES */
  RP.modal = function (opciones) {
    const fondo = document.createElement('div');
    fondo.className = 'modal-fondo';
    fondo.innerHTML =
      '<div class="modal' + (opciones.ancho ? ' ancho' : '') + '" role="dialog" aria-modal="true">' +
        '<div class="modal-cab"><h3></h3><button type="button" class="cerrar-modal" aria-label="Cerrar">✕</button></div>' +
        '<div class="modal-cuerpo"></div>' +
        (opciones.pie === false ? '' : '<div class="modal-pie"></div>') +
      '</div>';
    fondo.querySelector('h3').textContent = opciones.titulo || '';
    const cuerpo = fondo.querySelector('.modal-cuerpo');
    if (typeof opciones.contenido === 'string') cuerpo.innerHTML = opciones.contenido;
    else if (opciones.contenido) cuerpo.appendChild(opciones.contenido);

    const pie = fondo.querySelector('.modal-pie');
    const cerrar = () => { fondo.remove(); document.body.style.overflow = ''; if (previo) previo.focus(); };
    if (pie) {
      (opciones.botones || [{ texto: 'Cerrar', clase: 'btn-claro' }]).forEach((b) => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = 'btn ' + (b.clase || 'btn-claro');
        el.textContent = b.texto;
        el.addEventListener('click', () => {
          if (!b.accion || b.accion() !== false) cerrar();
        });
        pie.appendChild(el);
      });
    }
    fondo.querySelector('.cerrar-modal').addEventListener('click', cerrar);
    fondo.addEventListener('mousedown', (e) => { if (e.target === fondo) cerrar(); });
    document.addEventListener('keydown', function esc(e) {
      if (e.key === 'Escape') { cerrar(); document.removeEventListener('keydown', esc); }
    });
    const previo = document.activeElement;
    document.body.appendChild(fondo);
    document.body.style.overflow = 'hidden';
    const foco = cuerpo.querySelector('input,select,textarea,button') || fondo.querySelector('.cerrar-modal');
    if (foco) setTimeout(() => foco.focus(), 60);
    return { cerrar, elemento: fondo };
  };

  RP.confirmar = function (titulo, texto, alAceptar, textoBoton = 'Confirmar', clase = 'btn-peligro') {
    RP.modal({
      titulo,
      contenido: '<p style="margin:0">' + texto + '</p>',
      botones: [
        { texto: 'Cancelar', clase: 'btn-claro' },
        { texto: textoBoton, clase, accion: alAceptar },
      ],
    });
  };

  /* --------------------------------------------------- 4. NAVEGACIÓN / UI */
  function iniciarInterfaz() {
    const app = $('.app');

    // Alternar barra lateral
    // El umbral es el mismo que usa la hoja de estilos para sacar la barra
    // de la pantalla; con otro número el botón alterna una clase que no pinta
    // nada y parece averiado.
    const barraFuera = window.matchMedia('(max-width: 720px)');

    const cerrarBarra = () => {
      const barra = $('.barra');
      if (barra) barra.classList.remove('abierta');
      $$('[data-alternar-barra]').forEach((x) => x.setAttribute('aria-expanded', 'false'));
      const v = $('.velo'); if (v) v.remove();
    };

    $$('[data-alternar-barra]').forEach((b) => {
      b.setAttribute('aria-expanded', 'false');
      b.addEventListener('click', () => {
        const barra = $('.barra');
        if (!barra) return;
        if (barraFuera.matches) {
          const abierta = barra.classList.toggle('abierta');
          b.setAttribute('aria-expanded', abierta ? 'true' : 'false');
          if (abierta) {
            const velo = document.createElement('div');
            velo.className = 'velo';
            velo.addEventListener('click', cerrarBarra);
            document.body.appendChild(velo);
          } else {
            const v = $('.velo'); if (v) v.remove();
          }
        } else if (app) {
          app.classList.toggle('compacta');
          try { localStorage.setItem('rp_barra', app.classList.contains('compacta') ? '1' : '0'); } catch (e) {}
        }
      });
    });
    // Al elegir un destino la barra se cierra sola; si no, tapa la página
    // recién cargada en el teléfono.
    $$('.barra a[href]').forEach((a) => a.addEventListener('click', cerrarBarra));
    barraFuera.addEventListener('change', cerrarBarra);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrarBarra(); });
    try {
      if (app && localStorage.getItem('rp_barra') === '1' && window.innerWidth > 980) app.classList.add('compacta');
    } catch (e) {}

    // Menús desplegables
    $$('[data-desplegable]').forEach((disparador) => {
      const menu = document.getElementById(disparador.dataset.desplegable);
      if (!menu) return;
      disparador.setAttribute('aria-expanded', 'false');
      // La clase va en el envoltorio .desplegable: la hoja de estilos muestra
      // el panel con «.desplegable.abierto .desplegable-menu». Ponerla en el
      // panel no lo hace visible y el botón parece muerto.
      const envoltorio = disparador.closest('.desplegable') || menu.parentElement;
      disparador.addEventListener('click', (e) => {
        e.stopPropagation();
        const abierto = !envoltorio.classList.contains('abierto');
        $$('.desplegable.abierto').forEach((d) => {
          d.classList.remove('abierto');
          const t = d.querySelector('[data-desplegable]');
          if (t) t.setAttribute('aria-expanded', 'false');
        });
        envoltorio.classList.toggle('abierto', abierto);
        disparador.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        if (abierto && disparador.dataset.alAbrir === 'notificaciones') cargarNotificaciones();
      });
      menu.addEventListener('click', (e) => e.stopPropagation());
    });
    const cerrarDesplegables = () => $$('.desplegable.abierto').forEach((d) => {
      d.classList.remove('abierto');
      const t = d.querySelector('[data-desplegable]');
      if (t) t.setAttribute('aria-expanded', 'false');
    });
    document.addEventListener('click', cerrarDesplegables);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrarDesplegables(); });

    // Confirmaciones en formularios sensibles
    $$('form[data-confirmar]').forEach((f) => {
      f.addEventListener('submit', (e) => {
        if (f.dataset.confirmado === '1') return;
        e.preventDefault();
        RP.confirmar(
          f.dataset.confirmarTitulo || '¿Confirma la operación?',
          f.dataset.confirmar,
          () => { f.dataset.confirmado = '1'; f.submit(); },
          f.dataset.confirmarBoton || 'Sí, continuar'
        );
      });
    });

    // Evitar doble envío
    $$('form').forEach((f) => {
      f.addEventListener('submit', () => {
        if (f.dataset.confirmar && f.dataset.confirmado !== '1') return;
        const b = f.querySelector('button[type=submit]');
        if (b && !b.disabled) {
          setTimeout(() => {
            b.disabled = true;
            b.dataset.textoPrevio = b.innerHTML;
            b.innerHTML = 'Procesando…';
          }, 30);
          setTimeout(() => {
            if (b.disabled && b.dataset.textoPrevio) { b.disabled = false; b.innerHTML = b.dataset.textoPrevio; }
          }, 12000);
        }
      });
    });

    // Filtro instantáneo de tablas
    $$('[data-filtra]').forEach((entrada) => {
      const tabla = document.querySelector(entrada.dataset.filtra);
      if (!tabla) return;
      entrada.addEventListener('input', () => {
        const q = entrada.value.trim().toLowerCase();
        let visibles = 0;
        $$('tbody tr', tabla).forEach((tr) => {
          const coincide = !q || tr.textContent.toLowerCase().includes(q);
          tr.style.display = coincide ? '' : 'none';
          if (coincide) visibles++;
        });
        const cont = document.querySelector(entrada.dataset.contador || '');
        if (cont) cont.textContent = visibles;
      });
    });

    // Envío automático de filtros
    $$('[data-auto-enviar]').forEach((el) => el.addEventListener('change', () => el.form && el.form.submit()));

    // Vista previa de imágenes
    $$('input[type=file][data-previa]').forEach((inp) => {
      inp.addEventListener('change', () => {
        const destino = document.querySelector(inp.dataset.previa);
        if (!destino || !inp.files[0]) return;
        const url = URL.createObjectURL(inp.files[0]);
        destino.src = url;
        destino.hidden = false;
      });
    });

    // Contadores de caracteres
    $$('textarea[maxlength]').forEach((t) => {
      const c = document.createElement('small');
      c.className = 'ayuda derecha';
      const act = () => { c.textContent = t.value.length + ' / ' + t.maxLength; };
      t.after(c); t.addEventListener('input', act); act();
    });

    // Botones que envían un formulario por su selector
    $$('[data-enviar]').forEach((b) => b.addEventListener('click', () => {
      const f = document.querySelector(b.dataset.enviar);
      if (f) { if (f.requestSubmit) { f.requestSubmit(); } else { f.submit(); } }
    }));

    // Botón de recarga
    $$('[data-recargar]').forEach((b) => b.addEventListener('click', () => location.reload()));

    // Tablas con desplazamiento horizontal: accesibles con el teclado
    $$('.tabla-caja, .garita-lista, .notif-lista, .desplaza').forEach((caja) => {
      const revisar = () => {
        const desborda = caja.scrollWidth > caja.clientWidth + 2 || caja.scrollHeight > caja.clientHeight + 2;
        if (desborda && !caja.hasAttribute('tabindex')) {
          caja.setAttribute('tabindex', '0');
          caja.setAttribute('role', 'region');
          if (!caja.getAttribute('aria-label')) caja.setAttribute('aria-label', caja.dataset.etiqueta || 'Contenido con desplazamiento');
        } else if (!desborda && caja.getAttribute('role') === 'region') {
          caja.removeAttribute('tabindex');
          caja.removeAttribute('role');
          caja.removeAttribute('aria-label');
        }
      };
      revisar();
      window.addEventListener('resize', revisar, { passive: true });
    });

    // Cerrar mensajes flash automáticamente
    $$('.aviso-caja[data-auto-cerrar]').forEach((a) => {
      setTimeout(() => { a.style.transition = 'opacity .4s'; a.style.opacity = '0'; setTimeout(() => a.remove(), 420); }, 7000);
    });
  }

  /* ------------------------------------------------------- 5. TEMA / MODO */
  RP.modoOscuro = function (activar) {
    const raiz = document.documentElement;
    raiz.dataset.modo = activar ? 'oscuro' : 'claro';
    try { localStorage.setItem('rp_modo', activar ? 'oscuro' : 'claro'); } catch (e) {}
    RP.pedir('/api/tema', { method: 'POST', body: { modo_oscuro: activar ? 1 : 0 } }).catch(() => {});
    const m = document.querySelector('meta[name="theme-color"]');
    if (m) m.content = activar ? '#101310' : (raiz.dataset.colorMarca || '#0F2E24');
  };

  RP.tema = function (nombre) {
    document.documentElement.dataset.tema = nombre;
    try { localStorage.setItem('rp_tema', nombre); } catch (e) {}
    RP.pedir('/api/tema', { method: 'POST', body: { tema: nombre } }).catch(() => {});
  };

  function iniciarTema() {
    $$('[data-modo-oscuro]').forEach((b) => b.addEventListener('click', () => {
      RP.modoOscuro(document.documentElement.dataset.modo !== 'oscuro');
    }));
    $$('[data-tema-op]').forEach((b) => b.addEventListener('click', () => {
      RP.tema(b.dataset.temaOp);
      $$('[data-tema-op]').forEach((o) => o.classList.remove('is-activo'));
      b.classList.add('is-activo');
    }));
  }

  /* ------------------------------------------------- 6. NOTIFICACIONES */
  async function cargarNotificaciones() {
    const caja = $('#notif-lista');
    if (!caja) return;
    caja.innerHTML = '<div class="tarjeta-cuerpo compacto"><div class="esqueleto esqueleto-linea"></div><div class="esqueleto esqueleto-linea"></div></div>';
    const d = await RP.pedir('/api/notificaciones');
    if (!d.ok) { caja.innerHTML = '<p class="vacio">No se pudieron cargar las notificaciones.</p>'; return; }
    if (!d.items.length) { caja.innerHTML = '<div class="vacio" style="padding:28px 16px"><p style="margin:0">Sin notificaciones nuevas.</p></div>'; return; }
    caja.innerHTML = d.items.map((n) =>
      '<a class="notif' + (n.leido ? '' : ' sin-leer') + '" href="' + (n.url || '#') + '">' +
      '<div class="crecer"><b></b><span class="texto-2" style="font-size:.82rem"></span><br><small></small></div></a>'
    ).join('');
    $$('#notif-lista .notif').forEach((el, i) => {
      el.querySelector('b').textContent = d.items[i].titulo;
      el.querySelector('span').textContent = d.items[i].cuerpo || '';
      el.querySelector('small').textContent = d.items[i].hace;
    });
    RP.pedir('/api/notificaciones/leer', { method: 'POST', body: {} }).then(() => {
      const p = $('#notif-punto'); if (p) p.remove();
    }).catch(() => {});
  }
  RP.cargarNotificaciones = cargarNotificaciones;

  /* --------------------------------------------------------- 7. GRÁFICAS */
  RP.grafica = function (canvas, config) {
    const el = typeof canvas === 'string' ? document.getElementById(canvas) : canvas;
    if (!el) return null;
    // Si se instala el Chart.js oficial en /assets/vendor/, se usa ese.
    if (window.Chart) return new window.Chart(el, config);
    if (window.Grafica) return new window.Grafica(el, config);
    return null;
  };

  function iniciarGraficas() {
    $$('canvas[data-grafica]').forEach((c) => {
      try {
        const cfg = JSON.parse(c.dataset.grafica);
        RP.grafica(c, cfg);
      } catch (e) { /* configuración inválida: se ignora */ }
    });
  }

  /* --------------------------------------------------------- 8. PWA/PUSH */
  function registrarServicio() {
    if (!('serviceWorker' in navigator)) return;
    // El aviso de instalación es el NATIVO del navegador: no se intercepta
    // beforeinstallprompt ni se muestra ningún banner propio.
    window.addEventListener('load', () => {
      navigator.serviceWorker.register(ruta('/service-worker.js'), { scope: base + '/' })
        .catch(() => { /* sin service worker la aplicación sigue funcionando */ });
    });
  }

  RP.activarPush = async function () {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      RP.aviso('Su navegador no admite notificaciones push.', 'error');
      return false;
    }
    const permiso = await Notification.requestPermission();
    if (permiso !== 'granted') {
      RP.aviso('No se otorgó permiso para enviar notificaciones.', 'error');
      return false;
    }
    const clave = await RP.pedir('/api/push/clave');
    if (!clave.ok || !clave.clave) {
      RP.aviso('Las notificaciones push no están configuradas en el sistema.', 'error');
      return false;
    }
    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: base64ABytes(clave.clave),
    });
    const j = sub.toJSON();
    const r = await RP.pedir('/api/push/suscribir', {
      method: 'POST',
      body: { endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth },
    });
    if (r.ok) { RP.aviso('Notificaciones activadas en este dispositivo.'); return true; }
    RP.aviso(r.error || 'No se pudo activar las notificaciones.', 'error');
    return false;
  };

  function base64ABytes(b64) {
    const relleno = '='.repeat((4 - (b64.length % 4)) % 4);
    const s = (b64 + relleno).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(s);
    const out = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }

  /* -------------------------------------------------------- 9. UTILIDADES */
  RP.moneda = (n) => 'Q' + Number(n || 0).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  RP.copiar = async function (texto, mensaje = 'Copiado al portapapeles.') {
    try {
      await navigator.clipboard.writeText(texto);
      RP.aviso(mensaje);
    } catch (e) {
      const t = document.createElement('textarea');
      t.value = texto; document.body.appendChild(t); t.select();
      try { document.execCommand('copy'); RP.aviso(mensaje); } catch (x) { RP.aviso('No se pudo copiar.', 'error'); }
      t.remove();
    }
  };

  function iniciarCopiar() {
    $$('[data-copiar]').forEach((b) => b.addEventListener('click', () => RP.copiar(b.dataset.copiar)));
  }

  /* Reloj de garita */
  function iniciarReloj() {
    const el = $('[data-reloj]');
    if (!el) return;
    const act = () => {
      const d = new Date();
      el.textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') + ':' + String(d.getSeconds()).padStart(2, '0');
    };
    act(); setInterval(act, 1000);
  }

  /* Indicador de conexión */
  function iniciarConexion() {
    const el = $('[data-conexion]');
    if (!el) return;
    const act = () => {
      const enLinea = navigator.onLine;
      el.classList.toggle('sin-red', !enLinea);
      const t = el.querySelector('span');
      if (t) t.textContent = enLinea ? 'En línea' : 'Sin conexión — se guardará localmente';
      if (enLinea && window.Garita && window.Garita.sincronizar) window.Garita.sincronizar();
    };
    window.addEventListener('online', act);
    window.addEventListener('offline', act);
    act();
  }


  /* ------------------------------------------------- 11. SITIO PÚBLICO
     Movimiento del sitio: la cabecera se asienta al desplazar, los bloques
     aparecen una sola vez y las cifras cuentan hasta su valor. Todo se
     desactiva si el sistema pide menos movimiento. */

  const menosMovimiento = window.matchMedia
    ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
    : false;

  function iniciarCabeceraWeb() {
    const tope = document.querySelector('[data-tope-web]');
    if (!tope) return;
    // En las páginas sin fotografía de portada la cabecera nace asentada:
    // dejarla transparente pintaría texto blanco sobre papel.
    const fijaSiempre = tope.hasAttribute('data-tope-fijo');
    const revisar = () => tope.classList.toggle('fijo', fijaSiempre || window.scrollY > 24);
    revisar();
    window.addEventListener('scroll', revisar, { passive: true });

    $$('[data-menu-web]').forEach((b) => {
      b.addEventListener('click', () => {
        const nav = document.getElementById('web-nav');
        if (!nav) return;
        const abierto = nav.classList.toggle('abierto');
        b.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        if (abierto) tope.classList.add('fijo'); else revisar();
      });
    });
    $$('#web-nav a').forEach((a) => a.addEventListener('click', () => {
      const nav = document.getElementById('web-nav');
      if (nav) nav.classList.remove('abierto');
      $$('[data-menu-web]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
    }));
  }

  function iniciarApariciones() {
    const piezas = $$('[data-surge]');
    if (!piezas.length) return;
    if (menosMovimiento || !('IntersectionObserver' in window)) {
      piezas.forEach((p) => p.classList.add('visible'));
      return;
    }
    const obs = new IntersectionObserver((filas) => {
      filas.forEach((f) => {
        if (!f.isIntersecting) return;
        f.target.classList.add('visible');
        obs.unobserve(f.target);
      });
    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });
    piezas.forEach((p) => obs.observe(p));
  }

  function iniciarContadores() {
    const cifras = $$('[data-contador]');
    if (!cifras.length) return;
    const pintar = (el, v) => { el.textContent = new Intl.NumberFormat('es-GT').format(v); };
    if (menosMovimiento || !('IntersectionObserver' in window)) {
      cifras.forEach((el) => pintar(el, parseInt(el.dataset.contador, 10) || 0));
      return;
    }
    const obs = new IntersectionObserver((filas) => {
      filas.forEach((f) => {
        if (!f.isIntersecting) return;
        const el = f.target;
        obs.unobserve(el);
        const meta = parseInt(el.dataset.contador, 10) || 0;
        const inicio = performance.now();
        const dura = 900;
        const paso = (ahora) => {
          const t = Math.min(1, (ahora - inicio) / dura);
          pintar(el, Math.round(meta * (1 - Math.pow(1 - t, 3))));
          if (t < 1) requestAnimationFrame(paso);
        };
        requestAnimationFrame(paso);
      });
    }, { threshold: 0.4 });
    cifras.forEach((el) => obs.observe(el));
  }

  /* --------------------------------------------------------- 12. ARRANQUE */
  document.addEventListener('DOMContentLoaded', () => {
    iniciarInterfaz();
    iniciarTema();
    iniciarGraficas();
    iniciarCopiar();
    iniciarReloj();
    iniciarConexion();
    registrarServicio();
    iniciarCabeceraWeb();
    iniciarApariciones();
    iniciarContadores();
    $$('[data-abrir-notificaciones]').forEach((b) => b.addEventListener('click', cargarNotificaciones));
    $$('[data-activar-push]').forEach((b) => b.addEventListener('click', () => RP.activarPush()));
  });
})();
