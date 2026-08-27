/* ============================================================
   EduPortal · JavaScript de la interfaz (ES2020, sin dependencias)
   ============================================================ */
(function () {
  'use strict';

  const raiz = document.documentElement;
  const BASE = raiz.dataset.base || '/';
  const CSRF = raiz.dataset.csrf || '';

  const $ = (sel, ctx) => (ctx || document).querySelector(sel);
  const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));
  const url = (ruta) => BASE.replace(/\/$/, '') + '/' + String(ruta).replace(/^\//, '');

  /* ---------------- Toasts ---------------- */
  function contenedorToasts() {
    let c = $('.toasts');
    if (!c) {
      c = document.createElement('div');
      c.className = 'toasts';
      c.setAttribute('role', 'status');
      c.setAttribute('aria-live', 'polite');
      document.body.appendChild(c);
    }
    return c;
  }

  function toast(texto, tipo) {
    const el = document.createElement('div');
    el.className = 'toast toast--' + (tipo || 'info');
    const p = document.createElement('div');
    p.style.flex = '1';
    p.textContent = texto;
    const b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('aria-label', 'Cerrar aviso');
    b.innerHTML = '&times;';
    b.addEventListener('click', () => cerrar());
    el.append(p, b);
    contenedorToasts().appendChild(el);
    const t = setTimeout(cerrar, 5200);
    function cerrar() {
      clearTimeout(t);
      el.classList.add('saliendo');
      setTimeout(() => el.remove(), 220);
    }
    return el;
  }
  window.eduToast = toast;

  /* ---------------- Toasts renderizados por el servidor ---------------- */
  $$('.toast[data-autocerrar]').forEach((el) => {
    const cerrar = () => {
      el.classList.add('saliendo');
      setTimeout(() => el.remove(), 220);
    };
    const b = $('[data-cerrar-toast]', el);
    if (b) b.addEventListener('click', cerrar);
    setTimeout(cerrar, 6000);
  });

  /* ---------------- Menú lateral ---------------- */
  const btnMenu = $('[data-menu]');
  if (btnMenu) {
    btnMenu.addEventListener('click', () => {
      if (window.matchMedia('(max-width: 900px)').matches) {
        document.body.classList.toggle('menu-abierto');
        btnMenu.setAttribute('aria-expanded', document.body.classList.contains('menu-abierto') ? 'true' : 'false');
      } else {
        document.body.classList.toggle('compacta');
        try { localStorage.setItem('edu_compacta', document.body.classList.contains('compacta') ? '1' : '0'); } catch (e) {}
      }
    });
  }
  try {
    if (localStorage.getItem('edu_compacta') === '1' && window.innerWidth > 900) {
      document.body.classList.add('compacta');
    }
  } catch (e) {}
  document.addEventListener('click', (ev) => {
    if (document.body.classList.contains('menu-abierto') &&
        !ev.target.closest('.lateral') && !ev.target.closest('[data-menu]')) {
      document.body.classList.remove('menu-abierto');
    }
  });

  /* ---------------- Modales ---------------- */
  function abrirModal(id, datos) {
    const m = document.getElementById(id);
    if (!m) return;
    if (datos) {
      Object.keys(datos).forEach((k) => {
        const campo = m.querySelector('[name="' + k + '"]');
        if (!campo) return;
        if (campo.type === 'checkbox') campo.checked = datos[k] === '1' || datos[k] === 1 || datos[k] === true;
        else campo.value = datos[k] === null || datos[k] === undefined ? '' : datos[k];
      });
    }
    m.classList.add('abierto');
    m.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    const primero = m.querySelector('input:not([type=hidden]),select,textarea,button');
    if (primero) setTimeout(() => primero.focus(), 60);
  }
  function cerrarModal(m) {
    if (!m) return;
    m.classList.remove('abierto');
    m.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
  window.eduModal = abrirModal;

  document.addEventListener('click', (ev) => {
    const abrir = ev.target.closest('[data-modal]');
    if (abrir) {
      ev.preventDefault();
      let datos = null;
      if (abrir.dataset.valores) {
        try { datos = JSON.parse(abrir.dataset.valores); } catch (e) { datos = null; }
      }
      abrirModal(abrir.dataset.modal, datos);
      return;
    }
    if (ev.target.closest('[data-cerrar]') || ev.target.classList.contains('modal__fondo')) {
      cerrarModal(ev.target.closest('.modal'));
    }
  });
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') {
      const abierto = $('.modal.abierto');
      if (abierto) cerrarModal(abierto);
      const notif = $('.notif.abierto');
      if (notif) notif.classList.remove('abierto');
    }
  });

  /* ---------------- Pestañas ---------------- */
  $$('[data-tabs]').forEach((grupo) => {
    const botones = $$('[data-tab]', grupo);
    botones.forEach((b) => {
      b.addEventListener('click', () => {
        botones.forEach((x) => { x.classList.remove('activo'); x.setAttribute('aria-selected', 'false'); });
        b.classList.add('activo');
        b.setAttribute('aria-selected', 'true');
        $$('.panel-tab', grupo.parentElement || document).forEach((p) => p.classList.remove('activo'));
        const destino = document.getElementById(b.dataset.tab);
        if (destino) destino.classList.add('activo');
      });
    });
  });

  /* ---------------- Confirmaciones ---------------- */
  document.addEventListener('submit', (ev) => {
    const form = ev.target;
    if (form.dataset.confirmar && !window.confirm(form.dataset.confirmar)) {
      ev.preventDefault();
      return;
    }
    const btn = form.querySelector('button[type=submit]:not([data-sin-bloqueo])');
    if (btn && !form.dataset.sinBloqueo) {
      setTimeout(() => { btn.disabled = true; btn.dataset.textoPrevio = btn.textContent; btn.textContent = 'Procesando…'; }, 0);
      setTimeout(() => {
        if (btn.disabled) { btn.disabled = false; if (btn.dataset.textoPrevio) btn.textContent = btn.dataset.textoPrevio; }
      }, 12000);
    }
  });

  /* ---------------- Apariencia (tema y modo oscuro) ---------------- */
  const btnOscuro = $('[data-oscuro-toggle]');
  if (btnOscuro) {
    btnOscuro.addEventListener('click', () => {
      const activo = raiz.getAttribute('data-oscuro') === '1' ? '0' : '1';
      raiz.setAttribute('data-oscuro', activo);
      try { localStorage.setItem('edu_oscuro', activo); } catch (e) {}
      const cuerpo = new URLSearchParams();
      cuerpo.set('_csrf', CSRF);
      cuerpo.set('tema', raiz.getAttribute('data-tema') || 'default');
      cuerpo.set('modo_oscuro', activo);
      fetch(url('perfil/apariencia'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: cuerpo.toString(),
        credentials: 'same-origin'
      }).catch(() => {});
    });
  }

  /* ---------------- Notificaciones ---------------- */
  const btnNotif = $('[data-notif]');
  const panelNotif = $('#panel-notificaciones');
  if (btnNotif && panelNotif) {
    btnNotif.addEventListener('click', async () => {
      const abierto = panelNotif.classList.toggle('abierto');
      btnNotif.setAttribute('aria-expanded', abierto ? 'true' : 'false');
      if (!abierto) return;
      const lista = $('[data-notif-lista]', panelNotif);
      lista.innerHTML = '<div style="padding:14px"><div class="skel" style="margin-bottom:8px"></div>'
        + '<div class="skel" style="width:70%"></div></div>';
      try {
        const r = await fetch(url('api/notificaciones'), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
        });
        const d = await r.json();
        if (!d.ok || !d.items.length) {
          lista.innerHTML = '<div class="vacio sm">No tiene notificaciones.</div>';
        } else {
          lista.innerHTML = '';
          d.items.forEach((n) => {
            const a = document.createElement('a');
            a.className = 'notif__item' + (n.leido ? '' : ' nuevo');
            a.href = n.url || '#';
            const t = document.createElement('strong');
            t.textContent = n.titulo;
            const c = document.createElement('span');
            c.className = 'sm txt-2';
            c.textContent = n.cuerpo || '';
            const f = document.createElement('span');
            f.className = 'f';
            f.textContent = n.fecha;
            a.append(t, c, f);
            lista.appendChild(a);
          });
        }
        const cuerpo = new URLSearchParams();
        cuerpo.set('_csrf', CSRF);
        await fetch(url('api/notificaciones/leer'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
          body: cuerpo.toString(), credentials: 'same-origin'
        });
        const punto = $('.punto', btnNotif);
        if (punto) punto.remove();
      } catch (e) {
        lista.innerHTML = '<div class="vacio sm">No se pudieron cargar las notificaciones.</div>';
      }
    });
    document.addEventListener('click', (ev) => {
      if (!ev.target.closest('#panel-notificaciones') && !ev.target.closest('[data-notif]')) {
        panelNotif.classList.remove('abierto');
        btnNotif.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---------------- Envío automático de filtros ---------------- */
  $$('[data-auto-envio]').forEach((campo) => {
    campo.addEventListener('change', () => { if (campo.form) campo.form.submit(); });
  });

  /* ---------------- Navegación por selector ---------------- */
  $$('[data-ir-a]').forEach((sel) => {
    sel.addEventListener('change', () => { if (sel.value) window.location.href = sel.value; });
  });

  /* ---------------- Vista previa de imágenes ---------------- */
  $$('[data-previsualizar]').forEach((input) => {
    input.addEventListener('change', () => {
      const destino = document.querySelector(input.dataset.previsualizar);
      if (!destino || !input.files || !input.files[0]) return;
      const lector = new FileReader();
      lector.onload = (e) => { destino.src = String(e.target.result); };
      lector.readAsDataURL(input.files[0]);
    });
  });

  /* ---------------- Totales dinámicos en cobros ---------------- */
  const formCobro = $('[data-total-cobro]');
  if (formCobro) {
    const salida = $(formCobro.dataset.totalCobro);
    const moneda = formCobro.dataset.moneda || 'Q';
    const recalcular = () => {
      let t = 0;
      $$('input[data-monto]', formCobro).forEach((i) => {
        const v = parseFloat(String(i.value).replace(/[^\d.-]/g, ''));
        if (!isNaN(v) && v > 0) t += v;
      });
      if (salida) salida.textContent = moneda + t.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    formCobro.addEventListener('input', recalcular);
    $$('[data-pagar-todo]', formCobro).forEach((b) => {
      b.addEventListener('click', () => {
        $$('input[data-monto]', formCobro).forEach((i) => { i.value = i.dataset.saldo || ''; });
        recalcular();
      });
    });
    recalcular();
  }

  /* ---------------- Marcar todos (asistencia) ---------------- */
  $$('[data-marcar-todos]').forEach((b) => {
    b.addEventListener('click', () => {
      const valor = b.dataset.marcarTodos;
      $$('input[type=radio][value="' + valor + '"]').forEach((r) => { r.checked = true; });
      toast('Se marcaron todos como ' + valor + '.', 'ok');
    });
  });

  /* ---------------- Búsqueda con retardo ---------------- */
  $$('[data-buscar]').forEach((input) => {
    let temp = null;
    input.addEventListener('input', () => {
      clearTimeout(temp);
      temp = setTimeout(() => { if (input.form) input.form.submit(); }, 550);
    });
  });

  /* ---------------- PWA ---------------- */
  // Registro del service worker en la primera carga de cualquier página.
  const contextoSeguro = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
  if ('serviceWorker' in navigator && contextoSeguro) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register(url('service-worker.js'), { scope: BASE })
        .catch(() => { /* silencioso: la aplicación funciona igual sin caché */ });
    });
  }
  // No se llama preventDefault: el navegador muestra su propio aviso de instalación.
  window.addEventListener('beforeinstallprompt', function () { /* comportamiento nativo */ });

  /* ---------------- Suscripción a notificaciones push ---------------- */
  async function suscribirPush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
    if (Notification.permission !== 'granted') return;
    try {
      const r = await fetch(url('api/push/clave'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const d = await r.json();
      if (!d.ok || !d.clave) return;
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: base64ToUint8(d.clave)
      });
      const json = sub.toJSON();
      const cuerpo = new URLSearchParams();
      cuerpo.set('_csrf', CSRF);
      cuerpo.set('endpoint', json.endpoint);
      cuerpo.set('p256dh', (json.keys && json.keys.p256dh) || '');
      cuerpo.set('auth', (json.keys && json.keys.auth) || '');
      await fetch(url('api/push'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: cuerpo.toString(), credentials: 'same-origin'
      });
    } catch (e) { /* opcional */ }
  }
  function base64ToUint8(base64) {
    const relleno = '='.repeat((4 - (base64.length % 4)) % 4);
    const b64 = (base64 + relleno).replace(/-/g, '+').replace(/_/g, '/');
    const bruto = window.atob(b64);
    const salida = new Uint8Array(bruto.length);
    for (let i = 0; i < bruto.length; i++) salida[i] = bruto.charCodeAt(i);
    return salida;
  }
  const btnPush = $('[data-activar-push]');
  if (btnPush && 'Notification' in window) {
    btnPush.addEventListener('click', async () => {
      const permiso = await Notification.requestPermission();
      if (permiso === 'granted') { await suscribirPush(); toast('Notificaciones activadas en este dispositivo.', 'ok'); }
      else { toast('Su navegador bloqueo las notificaciones.', 'warn'); }
    });
  }
  if ('Notification' in window && Notification.permission === 'granted') { suscribirPush(); }

  /* ---------------- Fuentes web (sin bloquear el render) ---------------- */
  // Las copias locales de /assets/fonts se aplican de inmediato. Esta peticion
  // solo trae la version de Google Fonts cuando hay conexion; si falla, no pasa nada.
  (function fuentesWeb() {
    try {
      const l = document.createElement('link');
      l.rel = 'stylesheet';
      l.href = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700'
             + '&family=Playfair+Display:wght@500;600;700&display=swap';
      l.crossOrigin = 'anonymous';
      document.head.appendChild(l);
    } catch (e) { /* se usan las fuentes locales */ }
  })();

  /* ---------------- Mensajería: desplazar al final ---------------- */
  const chat = $('.chat__mensajes');
  if (chat) chat.scrollTop = chat.scrollHeight;
})();
