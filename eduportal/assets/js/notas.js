/* ============================================================
   EduPortal · Cuadrícula de notas con autoguardado y teclado
   ============================================================ */
(function () {
  'use strict';
  const tabla = document.querySelector('[data-notas]');
  if (!tabla) return;

  const raiz = document.documentElement;
  const BASE = raiz.dataset.base || '/';
  const CSRF = raiz.dataset.csrf || '';
  const url = (r) => BASE.replace(/\/$/, '') + '/' + String(r).replace(/^\//, '');
  const celdas = Array.from(tabla.querySelectorAll('input.nota'));
  const columnas = parseInt(tabla.dataset.columnas || '1', 10);
  const estado = document.querySelector('[data-notas-estado]');

  function indicar(texto, tipo) {
    if (!estado) return;
    estado.textContent = texto;
    estado.className = 'sm ' + (tipo === 'bad' ? 'nota-baja' : tipo === 'ok' ? 'nota-alta' : 'txt-3');
  }

  async function guardar(input) {
    const previo = input.dataset.previo || '';
    const valor = input.value.trim();
    if (valor === previo) return;
    if (valor !== '') {
      const n = parseFloat(valor.replace(',', '.'));
      const max = parseFloat(input.dataset.max || '100');
      if (isNaN(n) || n < 0 || n > max) {
        input.classList.add('error');
        indicar('El punteo debe estar entre 0 y ' + max + '.', 'bad');
        return;
      }
    }
    input.classList.remove('error');
    input.classList.add('guardando');
    const cuerpo = new URLSearchParams();
    cuerpo.set('_csrf', CSRF);
    cuerpo.set('actividad_id', input.dataset.actividad);
    cuerpo.set('alumno_id', input.dataset.alumno);
    cuerpo.set('punteo', valor);
    try {
      const r = await fetch(url('notas/guardar'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: cuerpo.toString(),
        credentials: 'same-origin'
      });
      const d = await r.json();
      input.classList.remove('guardando');
      if (!d.ok) {
        input.classList.add('error');
        indicar(d.error || 'No se pudo guardar el punteo.', 'bad');
        return;
      }
      input.dataset.previo = valor;
      input.classList.add('guardado');
      setTimeout(() => input.classList.remove('guardado'), 800);
      const fila = input.closest('tr');
      if (fila) {
        const z = fila.querySelector('[data-total="zona"]');
        const e = fila.querySelector('[data-total="examen"]');
        const t = fila.querySelector('[data-total="total"]');
        if (z) z.textContent = Number(d.zona).toFixed(2);
        if (e) e.textContent = Number(d.examen).toFixed(2);
        if (t) {
          t.textContent = Number(d.total).toFixed(2);
          const min = parseFloat(tabla.dataset.minima || '60');
          t.className = 'total ' + (d.total >= min + 20 ? 'nota-alta' : d.total >= min ? 'nota-ok' : 'nota-baja');
        }
      }
      indicar('Guardado automáticamente · ' + new Date().toLocaleTimeString('es-GT'), 'ok');
    } catch (err) {
      input.classList.remove('guardando');
      input.classList.add('error');
      indicar('Sin conexión: el punteo no se guardó.', 'bad');
    }
  }

  celdas.forEach((input, i) => {
    input.dataset.previo = input.value.trim();
    input.dataset.indice = String(i);
    input.addEventListener('blur', () => guardar(input));
    input.addEventListener('keydown', (ev) => {
      let destino = null;
      if (ev.key === 'Enter' || (ev.key === 'ArrowDown' && !ev.shiftKey)) destino = celdas[i + columnas];
      else if (ev.key === 'ArrowUp') destino = celdas[i - columnas];
      else if (ev.key === 'ArrowRight' && input.selectionStart === input.value.length) destino = celdas[i + 1];
      else if (ev.key === 'ArrowLeft' && input.selectionStart === 0) destino = celdas[i - 1];
      else if (ev.key === 'Escape') { input.value = input.dataset.previo || ''; input.blur(); return; }
      if (destino) {
        ev.preventDefault();
        guardar(input);
        destino.focus();
        destino.select();
      }
    });
    input.addEventListener('focus', () => input.select());
  });

  if (celdas.length) {
    indicar(celdas.length + ' casillas · use Enter o las flechas para desplazarse', 'txt');
  }
})();
