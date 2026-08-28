<?php
/** @var array $pedido, $eventos; array|null $mesero */
use MenuGold\Core\Security;
use MenuGold\Core\View;
use MenuGold\Models\Order;
View::set('titulo', 'Pedido ' . $pedido['codigo']);
View::set('subtitulo', Order::etiquetaModo((string)$pedido['modo']) . ' · ' . dt((string)$pedido['creado']));
$s = (string)($r['simbolo'] ?? 'Q');
$colores = ['nuevo'=>'aviso','preparando'=>'info','listo'=>'exito','entregado'=>'exito','pagado'=>'exito','anulado'=>'peligro'];

View::start('acciones');
?>
<a class="bt bt--suave" href="<?= e(url('panel/pedidos')) ?>"><?= icon('arrow-left') ?></a>
<a class="bt bt--linea" href="<?= e(url('panel/mesero/ticket/' . $pedido['id'])) ?>" target="_blank"><?= icon('printer') ?><span class="oculto-movil">Ticket</span></a>
<?php View::stop(); ?>

<div class="rejilla" style="grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr);align-items:start">
  <div>
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab">
        <h2 class="tarjeta-p__titulo"><?= icon('utensils') ?> Detalle</h2>
        <span class="insignia insignia--<?= e($colores[$pedido['estado']] ?? '') ?>"><?= e(Order::ETIQUETA_ESTADO[$pedido['estado']] ?? '') ?></span>
      </div>
      <?php foreach ($pedido['items'] as $l): ?>
        <div class="entre" style="padding:10px 0;border-bottom:1px solid var(--p-borde);align-items:flex-start">
          <span style="flex:0 0 auto;font-weight:700;color:var(--p-oro);min-width:32px"><?= (int)$l['cantidad'] ?>×</span>
          <div class="crece">
            <strong><?= e((string)$l['nombre']) ?></strong>
            <?php if (!empty($l['modificadores'])): ?>
              <div style="font-size:12.5px;color:var(--p-tenue)">
                <?php foreach ($l['modificadores'] as $m): ?>
                  <?= e((string)$m['opcion']) ?><?= (float)$m['precio'] > 0 ? ' (+' . e(money($m['precio'], $s)) . ')' : '' ?> ·
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($l['notas'])): ?>
              <div style="font-size:12.5px;color:var(--p-aviso)">📝 <?= e((string)$l['notas']) ?></div>
            <?php endif; ?>
          </div>
          <span class="mono" style="flex:0 0 auto"><?= e(money($l['subtotal'], $s)) ?></span>
        </div>
      <?php endforeach; ?>

      <div style="padding-top:14px">
        <div class="entre" style="padding:4px 0"><span>Subtotal</span><span class="mono"><?= e(money($pedido['subtotal'], $s)) ?></span></div>
        <?php if ((float)$pedido['descuento'] > 0): ?>
          <div class="entre" style="padding:4px 0;color:var(--p-exito)"><span>Descuento <?= $pedido['cupon_codigo'] ? '(' . e((string)$pedido['cupon_codigo']) . ')' : '' ?></span><span class="mono">−<?= e(money($pedido['descuento'], $s)) ?></span></div>
        <?php endif; ?>
        <?php if ((float)$pedido['costo_envio'] > 0): ?>
          <div class="entre" style="padding:4px 0"><span>Envío</span><span class="mono"><?= e(money($pedido['costo_envio'], $s)) ?></span></div>
        <?php endif; ?>
        <?php if ((float)$pedido['impuesto'] > 0): ?>
          <div class="entre" style="padding:4px 0;color:var(--p-tenue);font-size:13px"><span>Impuesto incluido</span><span class="mono"><?= e(money($pedido['impuesto'], $s)) ?></span></div>
        <?php endif; ?>
        <?php if ((float)$pedido['propina'] > 0): ?>
          <div class="entre" style="padding:4px 0"><span>Propina</span><span class="mono"><?= e(money($pedido['propina'], $s)) ?></span></div>
        <?php endif; ?>
        <div class="entre" style="padding:12px 0 0;border-top:1px solid var(--p-borde);margin-top:8px">
          <strong style="font-size:16px">Total</strong>
          <strong style="font-size:22px;color:var(--p-oro)"><?= e(money($pedido['total'], $s)) ?></strong>
        </div>
      </div>
    </div>

    <?php if (!empty($pedido['notas'])): ?>
      <div class="aviso aviso--aviso"><?= icon('info') ?><span><strong>Notas del cliente:</strong> <?= e((string)$pedido['notas']) ?></span></div>
    <?php endif; ?>
    <?php if ($pedido['estado'] === 'anulado' && !empty($pedido['motivo_anulacion'])): ?>
      <div class="aviso aviso--error"><?= icon('alert') ?><span><strong>Anulado:</strong> <?= e((string)$pedido['motivo_anulacion']) ?></span></div>
    <?php endif; ?>

    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('history') ?> Seguimiento</h2></div>
      <?php foreach ($eventos as $ev): ?>
        <div class="entre" style="padding:8px 0;border-bottom:1px solid var(--p-borde)">
          <span><strong><?= e(Order::ETIQUETA_ESTADO[$ev['estado']] ?? ucfirst((string)$ev['estado'])) ?></strong>
            <?php if (!empty($ev['nota'])): ?><span style="color:var(--p-tenue)"> · <?= e((string)$ev['nota']) ?></span><?php endif; ?></span>
          <span style="color:var(--p-tenue);font-size:13px"><?= e((string)$ev['usuario']) ?> · <?= e(dt((string)$ev['creado'], 'd/m H:i')) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div>
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('info') ?> Información</h2></div>
      <?php
      $datos = [
        'Código'  => (string)$pedido['codigo'],
        'Origen'  => Order::etiquetaModo((string)$pedido['modo']),
        'Mesa'    => (string)$pedido['mesa_nombre'],
        'Cliente' => (string)$pedido['cliente_nombre'],
        'Teléfono'=> (string)$pedido['cliente_telefono'],
        'Dirección' => (string)$pedido['cliente_direccion'],
        'Referencia'=> (string)$pedido['cliente_referencia'],
        'Pago'    => ucfirst((string)$pedido['metodo_pago']),
        'Atendió' => (string)($mesero['nombre'] ?? ''),
        'Tomado por' => ucfirst((string)$pedido['creado_por']),
        'Preparación' => $pedido['minutos_prep'] !== null ? $pedido['minutos_prep'] . ' min' : '',
        'Calificación' => $pedido['calificacion'] ? str_repeat('★', (int)$pedido['calificacion']) : '',
      ];
      foreach ($datos as $k => $v): if ($v === '') continue; ?>
        <div class="entre" style="padding:7px 0;border-bottom:1px solid var(--p-borde);font-size:13.5px">
          <span style="color:var(--p-tenue)"><?= e($k) ?></span><span style="text-align:right"><?= e($v) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!empty($pedido['comentario'])): ?>
        <p class="ayuda-p" style="margin-top:10px">“<?= e((string)$pedido['comentario']) ?>”</p>
      <?php endif; ?>
    </div>

    <?php if ($pedido['estado'] !== 'anulado'): ?>
      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('zap') ?> Acciones</h2></div>
        <div class="campo-p">
          <label for="nuevoEstado">Cambiar estado</label>
          <select id="nuevoEstado">
            <?php foreach (Order::ETIQUETA_ESTADO as $k => $v): if ($k === 'anulado') continue; ?>
              <option value="<?= e($k) ?>" <?= $pedido['estado'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="bt bt--oro bt--bloque" type="button" id="btnEstado"><?= icon('refresh') ?> Actualizar estado</button>
        <button class="bt bt--peligro bt--bloque" type="button" id="btnAnular" style="margin-top:8px"><?= icon('x-circle') ?> Anular pedido</button>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="modal-p" id="modalAnular" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja" style="width:min(430px,calc(100vw - 28px))">
    <div class="modal-p__cab">
      <h2 class="modal-p__titulo">Anular pedido</h2>
      <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
    </div>
    <div class="modal-p__cuerpo">
      <div class="campo-p">
        <label for="motivoAnular">Motivo de la anulación *</label>
        <textarea id="motivoAnular" maxlength="255" required placeholder="Ej. el cliente canceló antes de servir"></textarea>
      </div>
      <p class="ayuda-p">Quedará registrado en la auditoría con tu usuario, tu IP y la fecha.</p>
    </div>
    <div class="modal-p__pie">
      <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
      <button class="bt bt--peligro" type="button" id="confirmarAnular">Anular</button>
    </div>
  </div>
</div>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  var M = window.MGPanel;
  var id = <?= (int)$pedido['id'] ?>;
  var be = document.getElementById('btnEstado');
  if (be) be.addEventListener('click', function () {
    be.disabled = true;
    M.pedir('panel/pedidos/estado', { id: id, estado: document.getElementById('nuevoEstado').value })
      .then(function (r) {
        be.disabled = false;
        if (r.ok) { M.avisar(r.mensaje, 'ok'); setTimeout(function () { location.reload(); }, 700); }
        else M.avisar(r.error, 'error');
      });
  });
  var ba = document.getElementById('btnAnular');
  if (ba) ba.addEventListener('click', function () { M.abrirModal('modalAnular'); });
  var ca = document.getElementById('confirmarAnular');
  if (ca) ca.addEventListener('click', function () {
    var motivo = document.getElementById('motivoAnular').value.trim();
    if (motivo.length < 4) { M.avisar('Escribe el motivo.', 'aviso'); return; }
    ca.disabled = true;
    M.pedir('panel/pedidos/anular', { id: id, motivo: motivo }).then(function (r) {
      ca.disabled = false;
      if (r.ok) { M.avisar(r.mensaje, 'ok'); setTimeout(function () { location.reload(); }, 700); }
      else M.avisar(r.error, 'error');
    });
  });
})();
</script>
<?php View::stop(); ?>
