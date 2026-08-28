<?php
/** @var array $mesa, $pedidos, $totales, $llamadas, $cats, $productos, $propinas, $metodos */
use MenuGold\Core\Security;
use MenuGold\Core\View;
use MenuGold\Models\Order;
use MenuGold\Models\Product;

View::set('titulo', (string)$mesa['nombre']);
View::set('subtitulo', ucfirst((string)$mesa['estado'])
    . ($mesa['abierta_desde'] ? ' desde las ' . dt((string)$mesa['abierta_desde'], 'H:i') : '')
    . ' · ' . count($pedidos) . ' pedido(s) abierto(s)');
$s = (string)($r['simbolo'] ?? 'Q');

View::start('acciones');
?>
<a class="bt bt--suave" href="<?= e(url('panel/mesero')) ?>"><?= icon('arrow-left') ?><span class="oculto-movil">Mesas</span></a>
<button class="bt bt--linea" type="button" id="btnNuevoPedido"><?= icon('plus') ?><span>Tomar pedido</span></button>
<?php View::stop(); ?>

<?php if ($llamadas): ?>
  <?php foreach ($llamadas as $l): ?>
    <div class="llamada-fila">
      <?= icon($l['tipo'] === 'cuenta' ? 'receipt' : 'bell') ?>
      <span class="crece">Esta mesa <?= $l['tipo'] === 'cuenta' ? 'pidió la cuenta' : 'llamó al mesero' ?>
        · hace <?= (int)floor((time() - strtotime((string)$l['creado'])) / 60) ?> min</span>
      <button class="bt bt--sm bt--exito" type="button" data-atender="<?= (int)$l['id'] ?>">Atendida</button>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<div class="rejilla" style="grid-template-columns:minmax(0,1.7fr) minmax(290px,1fr);align-items:start">
  <div>
    <?php if (!$pedidos): ?>
      <div class="tarjeta-p">
        <div class="vacio-p">
          <?= icon('receipt', 'ico-lg') ?>
          <h3>Mesa sin pedidos</h3>
          <p>Toma el pedido desde aquí o espera a que el cliente pida escaneando su QR.</p>
          <button class="bt bt--oro" type="button" id="btnNuevoPedido2"><?= icon('plus') ?> Tomar pedido</button>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($pedidos as $o): ?>
        <div class="tarjeta-p" data-pedido="<?= (int)$o['id'] ?>">
          <div class="tarjeta-p__cab">
            <h2 class="tarjeta-p__titulo">
              <?= icon('receipt') ?> <?= e((string)$o['codigo']) ?>
              <span class="insignia insignia--<?= $o['estado'] === 'listo' ? 'exito' : ($o['estado'] === 'nuevo' ? 'aviso' : 'info') ?>">
                <?= e(Order::ETIQUETA_ESTADO[$o['estado']] ?? '') ?>
              </span>
            </h2>
            <span style="font-size:12.5px;color:var(--p-tenue)"><?= e(dt((string)$o['creado'], 'H:i')) ?></span>
          </div>

          <?php foreach ($o['items'] as $l): ?>
            <div class="entre" style="padding:8px 0;border-bottom:1px solid var(--p-borde);align-items:flex-start">
              <span style="flex:0 0 auto;font-weight:700;color:var(--p-oro)"><?= (int)$l['cantidad'] ?>×</span>
              <div class="crece">
                <div><?= e((string)$l['nombre']) ?></div>
                <?php if (!empty($l['modificadores'])): ?>
                  <div style="font-size:12px;color:var(--p-tenue)">
                    <?= e(implode(' · ', array_map(static fn($m) => (string)$m['opcion'], $l['modificadores']))) ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($l['notas'])): ?>
                  <div style="font-size:12px;color:var(--p-aviso)">📝 <?= e((string)$l['notas']) ?></div>
                <?php endif; ?>
              </div>
              <span class="mono" style="flex:0 0 auto"><?= e(money($l['subtotal'], $s)) ?></span>
            </div>
          <?php endforeach; ?>

          <div class="entre" style="padding-top:12px">
            <div class="acciones">
              <button class="bt bt--sm bt--linea" type="button" data-descuento="<?= (int)$o['id'] ?>"
                      data-subtotal="<?= e((string)$o['subtotal']) ?>"><?= icon('percent', 'ico-sm') ?> Descuento</button>
              <a class="bt bt--sm bt--suave" href="<?= e(url('panel/mesero/precuenta/' . $o['id'])) ?>" target="_blank">
                <?= icon('printer', 'ico-sm') ?> Precuenta</a>
            </div>
            <strong style="font-size:17px;color:var(--p-oro)" data-total-pedido><?= e(money($o['total'], $s)) ?></strong>
          </div>
          <?php if ((float)$o['descuento'] > 0): ?>
            <p class="ayuda-p" style="text-align:right;margin:4px 0 0;color:var(--p-exito)">
              Descuento aplicado: −<?= e(money($o['descuento'], $s)) ?>
              <?= $o['cupon_codigo'] ? '(' . e((string)$o['cupon_codigo']) . ')' : '' ?>
            </p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ============ Cuenta ============ -->
  <div>
    <div class="tarjeta-p" style="position:sticky;top:78px">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('wallet') ?> Cuenta de la mesa</h2></div>
      <div class="entre" style="padding:5px 0"><span>Subtotal</span><span class="mono"><?= e(money($totales['subtotal'], $s)) ?></span></div>
      <?php if ($totales['descuento'] > 0): ?>
        <div class="entre" style="padding:5px 0;color:var(--p-exito)"><span>Descuentos</span><span class="mono">−<?= e(money($totales['descuento'], $s)) ?></span></div>
      <?php endif; ?>
      <?php if ($totales['propina'] > 0): ?>
        <div class="entre" style="padding:5px 0"><span>Propina</span><span class="mono"><?= e(money($totales['propina'], $s)) ?></span></div>
      <?php endif; ?>
      <div class="entre" style="padding:12px 0;border-top:1px solid var(--p-borde);margin-top:8px">
        <strong style="font-size:16px">Total</strong>
        <strong style="font-size:23px;color:var(--p-oro)" id="totalMesa"><?= e(money($totales['total'], $s)) ?></strong>
      </div>

      <?php if ($pedidos): ?>
        <?php if (count($propinas) > 1): ?>
          <label class="etiqueta-campo">Propina</label>
          <div class="pastillas-sel" id="propinasMesa" style="margin-bottom:14px">
            <?php foreach ($propinas as $p): ?>
              <label class="pastilla-sel">
                <input type="radio" name="propinaPct" value="<?= (int)$p ?>" <?= (int)$p === 0 ? 'checked' : '' ?>>
                <?= $p === 0 ? 'Sin propina' : (int)$p . '%' ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="campo-p">
          <label for="metodoPago">Método de pago</label>
          <select id="metodoPago">
            <?php foreach ($metodos as $m): ?>
              <option value="<?= e($m) ?>"><?= e(ucfirst($m)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo-p">
          <label for="recibido">Recibe</label>
          <div class="grupo-prefijo">
            <span><?= e($s) ?></span>
            <input type="number" id="recibido" step="0.01" min="0" inputmode="decimal" placeholder="0.00">
          </div>
          <p class="ayuda-p" id="cambioMesa"></p>
        </div>

        <label class="etiqueta-campo">¿Qué vas a cobrar?</label>
        <div id="listaCobro" style="margin-bottom:12px">
          <?php foreach ($pedidos as $o): ?>
            <label class="casilla" style="width:100%">
              <input type="checkbox" class="cobrar-pedido" value="<?= (int)$o['id'] ?>"
                     data-total="<?= e((string)$o['total']) ?>" checked>
              <span><?= e((string)$o['codigo']) ?> · <?= e(money($o['total'], $s)) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="ayuda-p" style="margin-top:-6px">Desmarca los pedidos para dividir la cuenta.</p>

        <button class="bt bt--exito bt--bloque bt--grande" type="button" id="btnCobrar">
          <?= icon('money') ?> Cobrar y cerrar
        </button>
      <?php else: ?>
        <button class="bt bt--linea bt--bloque" type="button" id="btnLiberar"><?= icon('check') ?> Liberar mesa</button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ============ Modal tomar pedido ============ -->
<div class="modal-p modal-p--ancho" id="modalPedido" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja">
    <div class="modal-p__cab">
      <h2 class="modal-p__titulo">Tomar pedido · <?= e((string)$mesa['nombre']) ?></h2>
      <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
    </div>
    <div class="modal-p__cuerpo">
      <div class="campo-p">
        <input type="search" id="buscarPlato" placeholder="Buscar platillo…" autocomplete="off">
      </div>
      <div class="pastillas-sel" id="filtroCats" style="margin-bottom:12px">
        <label class="pastilla-sel"><input type="radio" name="fcat" value="0" checked>Todos</label>
        <?php foreach ($cats as $c): ?>
          <label class="pastilla-sel"><input type="radio" name="fcat" value="<?= (int)$c['id'] ?>"><?= e((string)$c['nombre']) ?></label>
        <?php endforeach; ?>
      </div>
      <div class="rejilla rejilla--4" id="listaPlatos" style="gap:8px;max-height:280px;overflow-y:auto;padding:2px">
        <?php foreach ($productos as $p): ?>
          <button class="bt bt--linea" type="button" style="flex-direction:column;align-items:flex-start;height:auto;padding:10px;text-align:left"
                  data-agregar="<?= (int)$p['id'] ?>" data-nombre="<?= e((string)$p['nombre']) ?>"
                  data-precio="<?= e((string)Product::precioVigente($p)) ?>"
                  data-cat="<?= (int)$p['category_id'] ?>"
                  data-busca="<?= e(mb_strtolower((string)$p['nombre'])) ?>">
            <strong style="font-size:13px;white-space:normal"><?= e((string)$p['nombre']) ?></strong>
            <span style="color:var(--p-oro);font-size:13px"><?= e(money(Product::precioVigente($p), $s)) ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:16px;border-top:1px solid var(--p-borde);padding-top:12px">
        <h3 style="font-size:14px;margin:0 0 8px">Pedido</h3>
        <div id="carritoMesero"><p style="color:var(--p-tenue);margin:0;font-size:13.5px">Aún no agregas platillos.</p></div>
        <div class="entre" style="padding-top:10px;border-top:1px solid var(--p-borde);margin-top:10px">
          <strong>Total</strong><strong style="color:var(--p-oro);font-size:18px" id="totalNuevo"><?= e(money(0, $s)) ?></strong>
        </div>
        <div class="campo-p" style="margin-top:12px">
          <label for="notasPedido">Notas para la cocina</label>
          <input type="text" id="notasPedido" maxlength="300" placeholder="Ej. servir todo junto">
        </div>
      </div>
    </div>
    <div class="modal-p__pie">
      <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
      <button class="bt bt--oro" type="button" id="enviarPedidoMesero"><?= icon('send') ?> Enviar a cocina</button>
    </div>
  </div>
</div>

<!-- ============ Modal descuento ============ -->
<div class="modal-p" id="modalDescuento" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja" style="width:min(420px,calc(100vw - 28px))">
    <div class="modal-p__cab">
      <h2 class="modal-p__titulo">Aplicar descuento</h2>
      <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
    </div>
    <div class="modal-p__cuerpo">
      <div class="campo-p">
        <label for="descTipo">Tipo</label>
        <select id="descTipo">
          <option value="porcentaje">Porcentaje</option>
          <option value="monto">Monto fijo</option>
          <option value="cupon">Código de cupón</option>
        </select>
      </div>
      <div class="campo-p" id="cajaDescValor">
        <label for="descValor">Valor</label>
        <input type="number" id="descValor" step="0.01" min="0" value="10" inputmode="decimal">
      </div>
      <div class="campo-p oculto" id="cajaDescCupon">
        <label for="descCupon">Código</label>
        <input type="text" id="descCupon" maxlength="40" style="text-transform:uppercase">
      </div>
      <p class="ayuda-p">Todo descuento queda registrado en la auditoría con tu usuario.</p>
    </div>
    <div class="modal-p__pie">
      <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
      <button class="bt bt--oro" type="button" id="aplicarDescuento"><?= icon('check') ?> Aplicar</button>
    </div>
  </div>
</div>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  var M = window.MGPanel;
  var mesaId = <?= (int)$mesa['id'] ?>;
  var carrito = [];
  var pedidoDesc = null;

  // ---------------------------------------------------- tomar pedido
  function abrirPedido() { M.abrirModal('modalPedido'); }
  ['btnNuevoPedido', 'btnNuevoPedido2'].forEach(function (id) {
    var b = document.getElementById(id);
    if (b) b.addEventListener('click', abrirPedido);
  });

  function pintarCarrito() {
    var caja = document.getElementById('carritoMesero');
    if (!carrito.length) {
      caja.innerHTML = '<p style="color:var(--p-tenue);margin:0;font-size:13.5px">Aún no agregas platillos.</p>';
      document.getElementById('totalNuevo').textContent = M.money(0);
      return;
    }
    var total = 0;
    caja.innerHTML = carrito.map(function (l, i) {
      total += l.precio * l.cantidad;
      return '<div class="entre" style="padding:7px 0;border-bottom:1px solid var(--p-borde)">'
        + '<span class="crece truncar">' + M.esc(l.nombre) + '</span>'
        + '<div class="mini-contador" style="display:flex;align-items:center;gap:2px">'
        + '<button class="bt bt--sm bt--suave" type="button" data-menos="' + i + '">−</button>'
        + '<span style="min-width:24px;text-align:center;font-weight:700">' + l.cantidad + '</span>'
        + '<button class="bt bt--sm bt--suave" type="button" data-mas="' + i + '">+</button></div>'
        + '<span class="mono" style="min-width:70px;text-align:right">' + M.money(l.precio * l.cantidad) + '</span></div>';
    }).join('');
    document.getElementById('totalNuevo').textContent = M.money(total);
  }

  document.addEventListener('click', function (ev) {
    var a = ev.target.closest('[data-agregar]');
    if (a) {
      var id = Number(a.dataset.agregar);
      var ex = carrito.filter(function (l) { return l.product_id === id; })[0];
      if (ex) ex.cantidad++;
      else carrito.push({ product_id: id, nombre: a.dataset.nombre, precio: Number(a.dataset.precio), cantidad: 1 });
      pintarCarrito();
      return;
    }
    var m = ev.target.closest('[data-menos]');
    if (m) {
      var i = Number(m.dataset.menos);
      carrito[i].cantidad--;
      if (carrito[i].cantidad <= 0) carrito.splice(i, 1);
      pintarCarrito();
      return;
    }
    var p = ev.target.closest('[data-mas]');
    if (p) { carrito[Number(p.dataset.mas)].cantidad++; pintarCarrito(); return; }

    var d = ev.target.closest('[data-descuento]');
    if (d) {
      pedidoDesc = { id: Number(d.dataset.descuento), subtotal: Number(d.dataset.subtotal) };
      M.abrirModal('modalDescuento');
      return;
    }
    var at = ev.target.closest('[data-atender]');
    if (at) {
      at.disabled = true;
      M.pedir('panel/mesero/llamada', { id: Number(at.dataset.atender) }).then(function (r) {
        if (r.ok) { at.closest('.llamada-fila').remove(); M.avisar(r.mensaje, 'ok'); }
        else { at.disabled = false; M.avisar(r.error, 'error'); }
      });
    }
  });

  // Filtro de platillos
  function filtrar() {
    var q = (document.getElementById('buscarPlato').value || '').toLowerCase().trim();
    var cat = (document.querySelector('input[name=fcat]:checked') || {}).value || '0';
    M.$$('#listaPlatos [data-agregar]').forEach(function (b) {
      var ok = (q === '' || b.dataset.busca.indexOf(q) >= 0) && (cat === '0' || b.dataset.cat === cat);
      b.style.display = ok ? '' : 'none';
    });
  }
  var bp = document.getElementById('buscarPlato');
  if (bp) bp.addEventListener('input', filtrar);
  M.$$('input[name=fcat]').forEach(function (i) { i.addEventListener('change', filtrar); });

  var env = document.getElementById('enviarPedidoMesero');
  if (env) env.addEventListener('click', function () {
    if (!carrito.length) { M.avisar('Agrega al menos un platillo.', 'aviso'); return; }
    env.disabled = true;
    M.pedir('panel/mesero/pedido', {
      table_id: mesaId, modo: 'mesa',
      notas: document.getElementById('notasPedido').value,
      lineas: carrito.map(function (l) { return { product_id: l.product_id, cantidad: l.cantidad, opciones: [] }; })
    }).then(function (r) {
      env.disabled = false;
      if (r.ok) { M.avisar(r.mensaje, 'ok'); setTimeout(function () { location.reload(); }, 700); }
      else M.avisar(r.error, 'error');
    });
  });

  // ---------------------------------------------------- descuento
  var dt = document.getElementById('descTipo');
  if (dt) dt.addEventListener('change', function () {
    document.getElementById('cajaDescValor').classList.toggle('oculto', dt.value === 'cupon');
    document.getElementById('cajaDescCupon').classList.toggle('oculto', dt.value !== 'cupon');
  });
  var ad = document.getElementById('aplicarDescuento');
  if (ad) ad.addEventListener('click', function () {
    if (!pedidoDesc) return;
    ad.disabled = true;
    M.pedir('panel/mesero/descuento', {
      order_id: pedidoDesc.id,
      tipo: dt.value,
      valor: parseFloat(document.getElementById('descValor').value) || 0,
      codigo: (document.getElementById('descCupon').value || '').toUpperCase()
    }).then(function (r) {
      ad.disabled = false;
      if (r.ok) { M.avisar(r.mensaje, 'ok'); setTimeout(function () { location.reload(); }, 700); }
      else M.avisar(r.error, 'error');
    });
  });

  // ---------------------------------------------------- cobrar
  function seleccionados() {
    return M.$$('.cobrar-pedido:checked').map(function (c) { return Number(c.value); });
  }
  function totalSeleccionado() {
    var t = 0;
    M.$$('.cobrar-pedido:checked').forEach(function (c) { t += Number(c.dataset.total); });
    var pct = Number((document.querySelector('input[name=propinaPct]:checked') || {}).value || 0);
    return t + t * pct / 100;
  }
  function refrescarTotal() {
    var el = document.getElementById('totalMesa');
    if (el) el.textContent = M.money(totalSeleccionado());
    calcularCambio();
  }
  M.$$('.cobrar-pedido').forEach(function (c) { c.addEventListener('change', refrescarTotal); });
  M.$$('input[name=propinaPct]').forEach(function (c) { c.addEventListener('change', refrescarTotal); });

  function calcularCambio() {
    var rec = document.getElementById('recibido');
    var caja = document.getElementById('cambioMesa');
    if (!rec || !caja) return;
    var v = parseFloat(rec.value) || 0;
    if (v <= 0) { caja.textContent = ''; return; }
    var d = v - totalSeleccionado();
    caja.textContent = d >= 0 ? 'Cambio: ' + M.money(d) : 'Faltan ' + M.money(-d);
    caja.style.color = d >= 0 ? 'var(--p-exito)' : 'var(--p-peligro)';
  }
  var rec = document.getElementById('recibido');
  if (rec) rec.addEventListener('input', calcularCambio);

  var bc = document.getElementById('btnCobrar');
  if (bc) bc.addEventListener('click', function () {
    var ids = seleccionados();
    if (!ids.length) { M.avisar('Elige al menos un pedido para cobrar.', 'aviso'); return; }
    M.confirmar('Se cobrarán ' + ids.length + ' pedido(s) por ' + M.money(totalSeleccionado()) + '.',
                'Confirmar cobro', 'Sí, cobrar').then(function (ok) {
      if (!ok) return;
      bc.disabled = true;
      M.pedir('panel/mesero/cobrar', {
        order_ids: ids,
        table_id: mesaId,
        metodo_pago: document.getElementById('metodoPago').value,
        propina_pct: Number((document.querySelector('input[name=propinaPct]:checked') || {}).value || 0),
        pagado_con: parseFloat((document.getElementById('recibido') || {}).value) || 0,
        cerrar_mesa: true
      }).then(function (r) {
        bc.disabled = false;
        if (r.ok) {
          M.avisar(r.mensaje + (r.cambio > 0 ? ' · Cambio ' + M.money(r.cambio) : ''), 'ok');
          if (r.ticket) window.open(r.ticket, '_blank');
          setTimeout(function () { location.href = M.url('panel/mesero'); }, 1100);
        } else M.avisar(r.error, 'error');
      });
    });
  });

  var bl = document.getElementById('btnLiberar');
  if (bl) bl.addEventListener('click', function () {
    M.pedir('panel/mesero/cerrar', { id: mesaId }).then(function (r) {
      if (r.ok) location.href = M.url('panel/mesero');
      else M.avisar(r.error, 'error');
    });
  });

  refrescarTotal();
})();
</script>
<?php View::stop(); ?>
