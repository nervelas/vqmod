<?php
/** @var array $promos, $cats, $productos */
use MenuGold\Core\Security;
use MenuGold\Core\View;
use MenuGold\Models\Promotion;
View::set('titulo', 'Promociones');
View::set('subtitulo', 'Descuentos, 2x1 y combos con fecha de vigencia');
$s = (string)($r['simbolo'] ?? 'Q');
$diasNombre = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];

View::start('acciones');
?>
<button class="bt bt--oro" type="button" data-modal="modalPromo" data-limpiar="1" data-titulo="Nueva promoción">
  <?= icon('plus') ?><span>Nueva</span>
</button>
<?php View::stop(); ?>

<?php if (!$promos): ?>
  <div class="tarjeta-p">
    <div class="vacio-p">
      <?= icon('percent', 'ico-lg') ?>
      <h3>Sin promociones activas</h3>
      <p>Crea un 2x1 los martes, un descuento en entradas o un combo del día.<br>
         Aparecen destacadas al inicio del menú.</p>
      <button class="bt bt--oro" type="button" data-modal="modalPromo" data-limpiar="1"><?= icon('plus') ?> Crear promoción</button>
    </div>
  </div>
<?php else: ?>
  <div class="rejilla rejilla--2">
    <?php foreach ($promos as $p): ?>
      <?php
        $hoy = date('Y-m-d');
        $vigente = (int)$p['activo'] === 1
            && (empty($p['desde']) || $p['desde'] <= $hoy)
            && (empty($p['hasta']) || $p['hasta'] >= $hoy);
      ?>
      <div class="tarjeta-p" style="<?= $vigente ? 'border-color:var(--p-oro)' : '' ?>">
        <div class="entre" style="align-items:flex-start;margin-bottom:10px">
          <span class="insignia insignia--oro" style="font-size:15px;padding:8px 14px">
            <?= e(Promotion::etiquetaTipo((string)$p['tipo'], $p['valor'], $s)) ?>
          </span>
          <span class="insignia insignia--<?= $vigente ? 'exito' : 'peligro' ?>">
            <?= $vigente ? 'Vigente' : 'No vigente' ?>
          </span>
        </div>
        <h3 style="margin:0 0 5px;font-size:16px"><?= e((string)$p['nombre']) ?></h3>
        <?php if (!empty($p['descripcion'])): ?>
          <p style="margin:0 0 10px;color:var(--p-suave);font-size:13.5px"><?= e((string)$p['descripcion']) ?></p>
        <?php endif; ?>
        <div style="font-size:12.5px;color:var(--p-tenue);margin-bottom:12px">
          <?php if (!empty($p['desde']) || !empty($p['hasta'])): ?>
            <?= icon('calendar', 'ico-sm') ?>
            <?= e($p['desde'] ? dt((string)$p['desde'], 'd/m/Y') : 'siempre') ?> —
            <?= e($p['hasta'] ? dt((string)$p['hasta'], 'd/m/Y') : 'sin fin') ?><br>
          <?php endif; ?>
          <?php if (!empty($p['dias'])): ?>
            Solo: <?= e(implode(', ', array_map(static fn($d) => $diasNombre[(int)$d] ?? '', array_filter(explode(',', (string)$p['dias']), 'strlen')))) ?>
          <?php endif; ?>
        </div>
        <div class="acciones">
          <button class="bt bt--sm bt--linea" type="button" data-modal="modalPromo" data-titulo="Editar promoción"
                  data-rellenar='<?= e(json_encode([
                      'id' => (int)$p['id'], 'nombre' => $p['nombre'], 'descripcion' => $p['descripcion'],
                      'tipo' => $p['tipo'], 'valor' => (float)$p['valor'],
                      'desde' => $p['desde'], 'hasta' => $p['hasta'],
                      'activo' => (int)$p['activo'],
                      'dias' => array_filter(explode(',', (string)$p['dias']), 'strlen'),
                      'category_ids' => array_filter(explode(',', (string)$p['category_ids']), 'strlen'),
                      'product_ids' => array_filter(explode(',', (string)$p['product_ids']), 'strlen'),
                  ], JSON_UNESCAPED_UNICODE)) ?>'><?= icon('edit', 'ico-sm') ?> Editar</button>
          <button class="bt bt--sm bt--suave" type="button" data-borrar-promo="<?= (int)$p['id'] ?>"
                  data-nombre="<?= e((string)$p['nombre']) ?>"><?= icon('trash', 'ico-sm') ?></button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="modal-p modal-p--ancho" id="modalPromo" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja">
    <form data-ajax action="<?= e(url('panel/promociones/guardar')) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="" data-limpiable>
      <div class="modal-p__cab">
        <h2 class="modal-p__titulo">Nueva promoción</h2>
        <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
      </div>
      <div class="modal-p__cuerpo">
        <div class="campo-p">
          <label for="pNombre">Nombre *</label>
          <input type="text" id="pNombre" name="nombre" required maxlength="120" placeholder="Ej. Martes de 2x1 en cócteles">
        </div>
        <div class="campo-p">
          <label for="pDesc">Descripción</label>
          <input type="text" id="pDesc" name="descripcion" maxlength="255" placeholder="Ej. Pide un cóctel y el segundo va por nuestra cuenta">
        </div>
        <div class="fila-campos">
          <div class="campo-p">
            <label for="pTipo">Tipo</label>
            <select id="pTipo" name="tipo">
              <option value="descuento">Descuento por porcentaje</option>
              <option value="2x1">2 x 1</option>
              <option value="precio_fijo">Precio fijo</option>
              <option value="combo">Combo</option>
            </select>
          </div>
          <div class="campo-p" id="cajaValor">
            <label for="pValor">Valor</label>
            <input type="number" id="pValor" name="valor" step="0.01" min="0" value="10" inputmode="decimal">
            <p class="ayuda-p" id="ayudaValor">Porcentaje de descuento (1 a 100).</p>
          </div>
        </div>
        <div class="fila-campos">
          <div class="campo-p">
            <label for="pDesde">Desde</label>
            <input type="date" id="pDesde" name="desde">
          </div>
          <div class="campo-p">
            <label for="pHasta">Hasta</label>
            <input type="date" id="pHasta" name="hasta">
          </div>
        </div>
        <div class="campo-p">
          <label>Días de la semana</label>
          <div class="pastillas-sel">
            <?php foreach ($diasNombre as $i => $d): ?>
              <label class="pastilla-sel"><input type="checkbox" name="dias[]" value="<?= $i ?>"><?= e($d) ?></label>
            <?php endforeach; ?>
          </div>
          <p class="ayuda-p">Sin marcar = todos los días.</p>
        </div>
        <div class="fila-campos">
          <div class="campo-p">
            <label for="pCats">Aplica a categorías</label>
            <select id="pCats" name="category_ids[]" multiple size="5">
              <?php foreach ($cats as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= e((string)$c['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo-p">
            <label for="pProds">Aplica a platillos</label>
            <select id="pProds" name="product_ids[]" multiple size="5">
              <?php foreach ($productos as $pr): ?>
                <option value="<?= (int)$pr['id'] ?>"><?= e((string)$pr['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <label class="interruptor">
          <input type="checkbox" name="activo" value="1" checked>
          <span class="interruptor__pista"></span><span class="interruptor__texto">Promoción activa</span>
        </label>
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
(function () {
  var M = window.MGPanel;
  var tipo = document.getElementById('pTipo');
  var caja = document.getElementById('cajaValor');
  var ayuda = document.getElementById('ayudaValor');
  function alternar() {
    var t = tipo.value;
    caja.style.display = (t === '2x1' || t === 'combo') ? 'none' : '';
    ayuda.textContent = t === 'descuento' ? 'Porcentaje de descuento (1 a 100).' : 'Precio final del platillo en promoción.';
  }
  if (tipo) { tipo.addEventListener('change', alternar); alternar(); }

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-borrar-promo]');
    if (!b) return;
    M.confirmar('Se eliminará la promoción "' + b.dataset.nombre + '".', 'Eliminar promoción', 'Sí, eliminar')
      .then(function (ok) {
        if (!ok) return;
        M.pedir('panel/promociones/borrar', { id: Number(b.dataset.borrarPromo) }).then(function (r) {
          if (r.ok) location.reload(); else M.avisar(r.error, 'error');
        });
      });
  });
})();
</script>
<?php View::stop(); ?>
