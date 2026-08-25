/**
 * Editor de líneas del documento y cálculo de totales en pantalla.
 * El cálculo definitivo lo hace el servidor (src/Dte/Calculator.php);
 * esto es solo una vista previa para el operador.
 */
(function () {
  'use strict';

  var TASA_IVA = 0.12;

  var cuerpo = document.getElementById('lineas');
  if (!cuerpo) { return; }

  var plantilla = document.getElementById('plantilla-linea');
  var contador = cuerpo.querySelectorAll('tr').length;

  function moneda(valor) {
    return valor.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function desglosaIva() {
    var select = document.getElementById('tipo');
    var opcion = select ? select.options[select.selectedIndex] : null;
    return !opcion || opcion.dataset.iva !== '0';
  }

  function recalcular() {
    var totalGeneral = 0, totalDescuento = 0, totalIva = 0, totalGravable = 0;
    var conIva = desglosaIva();

    Array.prototype.forEach.call(cuerpo.querySelectorAll('tr'), function (fila) {
      var cantidad = parseFloat(fila.querySelector('.f-cantidad').value) || 0;
      var precio = parseFloat(fila.querySelector('.f-precio').value) || 0;
      var descuento = parseFloat(fila.querySelector('.f-descuento').value) || 0;
      var exento = fila.querySelector('.f-exento').checked;

      var bruto = Math.round(cantidad * precio * 100) / 100;
      var total = Math.round((bruto - descuento) * 100) / 100;
      if (total < 0) { total = 0; }

      var gravable = total, iva = 0;
      if (conIva && !exento) {
        gravable = Math.round((total / (1 + TASA_IVA)) * 100) / 100;
        iva = Math.round((total - gravable) * 100) / 100;
      }

      fila.querySelector('.f-total').textContent = moneda(total);

      totalGeneral += total;
      totalDescuento += descuento;
      totalIva += iva;
      totalGravable += gravable;
    });

    document.getElementById('t-gravable').textContent = moneda(totalGravable);
    document.getElementById('t-descuento').textContent = moneda(totalDescuento);
    document.getElementById('t-iva').textContent = moneda(totalIva);
    document.getElementById('t-total').textContent = moneda(totalGeneral);

    var avisoIva = document.getElementById('aviso-iva');
    if (avisoIva) { avisoIva.hidden = conIva; }
  }

  function agregarLinea() {
    var html = plantilla.innerHTML.replace(/__i__/g, String(contador++));
    var fila = document.createElement('tbody');
    fila.innerHTML = '<table><tbody>' + html + '</tbody></table>';
    cuerpo.appendChild(fila.querySelector('tr'));
    recalcular();
  }

  function aplicarProducto(select) {
    var opcion = select.options[select.selectedIndex];
    if (!opcion || !opcion.value) { return; }

    var fila = select.closest('tr');
    fila.querySelector('.f-descripcion').value = opcion.dataset.descripcion || '';
    fila.querySelector('.f-precio').value = opcion.dataset.precio || '0';
    fila.querySelector('.f-unidad').value = opcion.dataset.unidad || 'UNI';
    fila.querySelector('.f-tipo').value = opcion.dataset.tipo || 'B';
    fila.querySelector('.f-exento').checked = opcion.dataset.exento === '1';
    recalcular();
  }

  function aplicarCliente(select) {
    var opcion = select.options[select.selectedIndex];
    if (!opcion || !opcion.value) { return; }

    document.getElementById('receptor_id').value = opcion.dataset.identificador || '';
    document.getElementById('receptor_nombre').value = opcion.dataset.nombre || '';
    document.getElementById('receptor_correo').value = opcion.dataset.correo || '';
    document.getElementById('receptor_direccion').value = opcion.dataset.direccion || 'Ciudad';
    document.getElementById('receptor_municipio').value = opcion.dataset.municipio || 'Guatemala';
    document.getElementById('receptor_departamento').value = opcion.dataset.departamento || 'Guatemala';
    document.getElementById('receptor_tipo_especial').value = opcion.dataset.tipoEspecial || '';
  }

  document.addEventListener('input', function (evento) {
    if (evento.target.closest('#lineas')) { recalcular(); }
  });

  document.addEventListener('change', function (evento) {
    if (evento.target.id === 'tipo') { recalcular(); }
    if (evento.target.classList.contains('f-producto')) { aplicarProducto(evento.target); }
    if (evento.target.classList.contains('f-exento')) { recalcular(); }
    if (evento.target.id === 'cliente_id') { aplicarCliente(evento.target); }
  });

  document.addEventListener('click', function (evento) {
    if (evento.target.classList.contains('quitar-linea')) {
      evento.preventDefault();
      if (cuerpo.querySelectorAll('tr').length > 1) {
        evento.target.closest('tr').remove();
        recalcular();
      }
    }
    if (evento.target.id === 'agregar-linea') {
      evento.preventDefault();
      agregarLinea();
    }
  });

  // Consumidor final: limpia el nombre para que el operador lo confirme.
  var receptorId = document.getElementById('receptor_id');
  if (receptorId) {
    receptorId.addEventListener('blur', function () {
      var nombre = document.getElementById('receptor_nombre');
      if (receptorId.value.toUpperCase() === 'CF' && nombre.value.trim() === '') {
        nombre.value = 'Consumidor Final';
      }
    });
  }

  recalcular();
})();
