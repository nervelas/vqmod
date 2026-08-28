<?php
/** @var array $cupones */
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Cupones');
View::set('subtitulo', 'Códigos de descuento para tus clientes');
$s = (string)($r['simbolo'] ?? 'Q');
$tipos = ['porcentaje' => 'Porcentaje', 'monto' => 'Monto fijo', 'envio_gratis' => 'Envío gratis'];

View::start('acciones');
?>
<button class="bt bt--oro" type="button" data-modal="modalCupon" data-limpiar="1" data-titulo="Nuevo cupón">
  <?= icon('plus') ?><span>Nuevo</span>
</button>
<?php View::stop(); ?>

<?php if (!$cupones): ?>
  <div class="tarjeta-p">
    <div class="vacio-p">
      <?= icon('ticket', 'ico-lg') ?>
      <h3>Sin cupones</h3>
      <p>Crea códigos como BIENVENIDO10 o ENVIOGRATIS.<br>El cliente los escribe al pedir y el descuento se aplica solo.</p>
      <button class="bt bt--oro" type="button" data-modal="modalCupon" data-limpiar="1"><?= icon('plus') ?> Crear cupón</button>
    </div>
  </div>
<?php else: ?>
  <div class="rejilla rejilla--3">
    <?php foreach ($cupones as $c): ?>
      <?php
        $hoy = date('Y-m-d');
        $vigente = (int)$c['activo'] === 1
          && (empty($c['desde']) || $c['desde'] <= $hoy)
          && (empty($c['hasta']) || $c['hasta'] >= $hoy)
          && ((int)$c['usos_max'] === 0 || (int)$c['usos'] < (int)$c['usos_max']);
      ?>
      <div class="tarjeta-p" style="<?= $vigente ? 'border-color:var(--p-oro)' : '' ?>">
        <div class="entre" style="margin-bottom:10px">
          <strong class="mono" style="font-size:17px;letter-spacing:1px;color:var(--p-oro)"><?= e((string)$c['codigo']) ?></strong>
          <span class="insignia insignia--<?= $vigente ? 'exito' : 'peligro' ?>"><?= $vigente ? 'Vigente' : 'Inactivo' ?></span>
        </div>
        <p style="margin:0 0 8px;font-size:13.5px;color:var(--p-suave)"><?= e((string)$c['descripcion']) ?></p>
        <div style="font-size:13px;color:var(--p-tenue);line-height:1.7">
          <?= e($tipos[$c['tipo']] ?? '') ?>:
          <strong><?= $c['tipo'] === 'porcentaje' ? (float)$c['valor'] . '%' : ($c['tipo'] === 'envio_gratis' ? 'gratis' : money($c['valor'], $s)) ?></strong><br>
          <?php if ((float)$c['min_compra'] > 0): ?>Compra mínima: <?= e(money($c['min_compra'], $s)) ?><br><?php endif; ?>
          Usos: <?= (int)$c['usos'] ?><?= (int)$c['usos_max'] > 0 ? ' / ' . (int)$c['usos_max'] : ' (ilimitado)' ?><br>
          <?php if (!empty($c['hasta'])): ?>Vence: <?= e(dt((string)$c['hasta'], 'd/m/Y')) ?><?php endif; ?>
        </div>
        <div class="acciones" style="margin-top:12px">
          <button class="bt bt--sm bt--linea" type="button" data-modal="modalCupon" data-titulo="Editar cupón"
                  data-rellenar='<?= e(json_encode([
                      'id' => (int)$c['id'], 'codigo' => $c['codigo'], 'descripcion' => $c['descripcion'],
                      'tipo' => $c['tipo'], 'valor' => (float)$c['valor'], 'min_compra' => (float)$c['min_compra'],
                      'usos_max' => (int)$c['usos_max'], 'desde' => $c['desde'], 'hasta' => $c['hasta'],
                      'activo' => (int)$c['activo'],
                  ], JSON_UNESCAPED_UNICODE)) ?>'><?= icon('edit', 'ico-sm') ?> Editar</button>
          <button class="bt bt--sm bt--suave" type="button" data-borrar-cupon="<?= (int)$c['id'] ?>"
                  data-codigo="<?= e((string)$c['codigo']) ?>"><?= icon('trash', 'ico-sm') ?></button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="modal-p" id="modalCupon" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja" style="width:min(480px,calc(100vw - 28px))">
    <form data-ajax action="<?= e(url('panel/cupones/guardar')) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="" data-limpiable>
      <div class="modal-p__cab">
        <h2 class="modal-p__titulo">Nuevo cupón</h2>
        <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
      </div>
      <div class="modal-p__cuerpo">
        <div class="campo-p"><label for="cuCod">Código *</label>
          <input type="text" id="cuCod" name="codigo" required maxlength="40" style="text-transform:uppercase" placeholder="BIENVENIDO10"></div>
        <div class="campo-p"><label for="cuDesc">Descripción</label>
          <input type="text" id="cuDesc" name="descripcion" maxlength="190" placeholder="10% en tu primer pedido"></div>
        <div class="fila-campos">
          <div class="campo-p"><label for="cuTipo">Tipo</label>
            <select id="cuTipo" name="tipo">
              <?php foreach ($tipos as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
            </select></div>
          <div class="campo-p"><label for="cuValor">Valor</label>
            <input type="number" id="cuValor" name="valor" step="0.01" min="0" value="10" inputmode="decimal"></div>
        </div>
        <div class="fila-campos">
          <div class="campo-p"><label for="cuMin">Compra mínima</label>
            <div class="grupo-prefijo"><span><?= e($s) ?></span>
              <input type="number" id="cuMin" name="min_compra" step="0.01" min="0" value="0" inputmode="decimal"></div></div>
          <div class="campo-p"><label for="cuUsos">Usos máximos</label>
            <input type="number" id="cuUsos" name="usos_max" min="0" value="0" inputmode="numeric">
            <p class="ayuda-p">0 = ilimitado</p></div>
        </div>
        <div class="fila-campos">
          <div class="campo-p"><label for="cuDesde">Desde</label><input type="date" id="cuDesde" name="desde"></div>
          <div class="campo-p"><label for="cuHasta">Hasta</label><input type="date" id="cuHasta" name="hasta"></div>
        </div>
        <label class="interruptor"><input type="checkbox" name="activo" value="1" checked>
          <span class="interruptor__pista"></span><span class="interruptor__texto">Cupón activo</span></label>
      </div>
      <div class="modal-p__pie">
        <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
        <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar</button>
      </div>
    </form>
  </div>
</div>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
document.addEventListener('click', function (ev) {
  var b = ev.target.closest('[data-borrar-cupon]');
  if (!b) return;
  var M = window.MGPanel;
  M.confirmar('Se eliminará el cupón ' + b.dataset.codigo + '.', 'Eliminar cupón', 'Sí, eliminar').then(function (ok) {
    if (!ok) return;
    M.pedir('panel/cupones/borrar', { id: Number(b.dataset.borrarCupon) }).then(function (r) {
      if (r.ok) location.reload(); else M.avisar(r.error, 'error');
    });
  });
});
</script>
<?php View::stop(); ?>
