<?php
/** @var array $categorias */
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Categorías');
View::set('subtitulo', 'Ordénalas arrastrando: así se verán en el menú');
$diasNombre = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
$iconos = ['utensils','sparkles','leaf','fire','cake','bar','chef','store','sun','moon','gift','box'];

View::start('acciones');
?>
<button class="bt bt--oro" type="button" data-modal="modalCat" data-limpiar="1" data-titulo="Nueva categoría">
  <?= icon('plus') ?><span>Nueva</span>
</button>
<?php View::stop(); ?>

<div class="tarjeta-p">
  <?php if (!$categorias): ?>
    <div class="vacio-p">
      <?= icon('layers', 'ico-lg') ?>
      <h3>Aún no tienes categorías</h3>
      <p>Agrupa tus platillos: Entradas, Fuertes, Postres, Bebidas…</p>
      <button class="bt bt--oro" type="button" data-modal="modalCat" data-limpiar="1"><?= icon('plus') ?> Crear la primera</button>
    </div>
  <?php else: ?>
    <ul class="lista-orden" id="listaCats">
      <?php foreach ($categorias as $c): ?>
        <li draggable="true" data-id="<?= (int)$c['id'] ?>">
          <?= icon('grip', 'ico asa') ?>
          <span style="width:34px;height:34px;border-radius:9px;background:var(--p-oro-suave);color:var(--p-oro);display:grid;place-items:center;flex:0 0 auto">
            <?= icon((string)($c['icono'] ?: 'utensils')) ?>
          </span>
          <div class="crece truncar">
            <strong><?= e((string)$c['nombre']) ?></strong>
            <?php if ((int)$c['activo'] !== 1): ?><span class="insignia">Oculta</span><?php endif; ?>
            <div style="font-size:12.5px;color:var(--p-tenue)">
              <?= (int)$c['productos'] ?> platillo(s)
              <?php $h = \MenuGold\Models\Category::textoHorario($c); if ($h): ?> · <?= e($h) ?><?php endif; ?>
              <?php if (!empty($c['dias'])): ?>
                · <?= e(implode(', ', array_map(static fn($d) => $diasNombre[(int)$d] ?? '', array_filter(explode(',', (string)$c['dias']), 'strlen')))) ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="acciones">
            <button class="bt bt--sm bt--suave" type="button" data-subir aria-label="Subir"><?= icon('chevron-up', 'ico-sm') ?></button>
            <button class="bt bt--sm bt--suave" type="button" data-bajar aria-label="Bajar"><?= icon('chevron-down', 'ico-sm') ?></button>
            <button class="bt bt--sm bt--suave" type="button" data-modal="modalCat" data-titulo="Editar categoría"
                    data-rellenar='<?= e(json_encode([
                        'id' => (int)$c['id'], 'nombre' => $c['nombre'], 'nombre_en' => $c['nombre_en'],
                        'descripcion' => $c['descripcion'], 'descripcion_en' => $c['descripcion_en'],
                        'icono' => $c['icono'], 'activo' => (int)$c['activo'],
                        'hora_inicio' => substr((string)$c['hora_inicio'], 0, 5),
                        'hora_fin' => substr((string)$c['hora_fin'], 0, 5),
                        'dias' => array_filter(explode(',', (string)$c['dias']), 'strlen'),
                    ], JSON_UNESCAPED_UNICODE)) ?>'><?= icon('edit', 'ico-sm') ?></button>
            <button class="bt bt--sm bt--suave" type="button" data-borrar-cat="<?= (int)$c['id'] ?>"
                    data-nombre="<?= e((string)$c['nombre']) ?>" data-productos="<?= (int)$c['productos'] ?>">
              <?= icon('trash', 'ico-sm') ?>
            </button>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<!-- ============ Modal ============ -->
<div class="modal-p" id="modalCat" role="dialog" aria-modal="true" aria-labelledby="tCat">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja">
    <form data-ajax action="<?= e(url('panel/categorias/guardar')) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="" data-limpiable>
      <div class="modal-p__cab">
        <h2 class="modal-p__titulo" id="tCat">Nueva categoría</h2>
        <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
      </div>
      <div class="modal-p__cuerpo">
        <div class="campo-p">
          <label for="catNombre">Nombre *</label>
          <input type="text" id="catNombre" name="nombre" required maxlength="120" placeholder="Ej. Entradas">
        </div>
        <div class="campo-p">
          <label for="catDesc">Descripción corta</label>
          <input type="text" id="catDesc" name="descripcion" maxlength="255" placeholder="Ej. Para empezar, algo que se comparte">
        </div>
        <div class="campo-p">
          <label>Ícono</label>
          <div class="pastillas-sel">
            <?php foreach ($iconos as $i => $ic): ?>
              <label class="pastilla-sel" title="<?= e($ic) ?>">
                <input type="radio" name="icono" value="<?= e($ic) ?>" <?= $i === 0 ? 'checked' : '' ?>>
                <?= icon($ic) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="fila-campos">
          <div class="campo-p">
            <label for="catIni">Disponible desde</label>
            <input type="time" id="catIni" name="hora_inicio">
          </div>
          <div class="campo-p">
            <label for="catFin">Hasta</label>
            <input type="time" id="catFin" name="hora_fin">
          </div>
        </div>
        <p class="ayuda-p" style="margin-top:-8px">Ej. desayunos de 06:00 a 11:00. Vacío = todo el día.</p>
        <div class="campo-p">
          <label>Días</label>
          <div class="pastillas-sel">
            <?php foreach ($diasNombre as $i => $d): ?>
              <label class="pastilla-sel"><input type="checkbox" name="dias[]" value="<?= $i ?>"><?= e($d) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <label class="interruptor">
          <input type="checkbox" name="activo" value="1" checked>
          <span class="interruptor__pista"></span>
          <span class="interruptor__texto">Visible en el menú</span>
        </label>
        <details style="margin-top:14px">
          <summary style="cursor:pointer;font-size:13.5px;color:var(--p-suave)">Versión en inglés</summary>
          <div class="campo-p" style="margin-top:10px">
            <label for="catNombreEn">Nombre en inglés</label>
            <input type="text" id="catNombreEn" name="nombre_en" maxlength="120">
          </div>
          <div class="campo-p">
            <label for="catDescEn">Descripción en inglés</label>
            <input type="text" id="catDescEn" name="descripcion_en" maxlength="255">
          </div>
        </details>
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
  var lista = document.getElementById('listaCats');
  if (lista) M.ordenable(lista, function (ids) {
    M.pedir('panel/categorias/ordenar', { ids: ids }).then(function (r) {
      if (r.ok) M.avisar('Orden guardado', 'ok');
    });
  });

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-borrar-cat]');
    if (!b) return;
    var n = Number(b.dataset.productos);
    var texto = n > 0
      ? 'La categoría "' + b.dataset.nombre + '" tiene ' + n + ' platillo(s). Muévelos a otra categoría antes de eliminarla.'
      : 'Se eliminará la categoría "' + b.dataset.nombre + '".';
    if (n > 0) { M.avisar(texto, 'aviso'); return; }
    M.confirmar(texto, 'Eliminar categoría', 'Sí, eliminar').then(function (ok) {
      if (!ok) return;
      M.pedir('panel/categorias/borrar', { id: Number(b.dataset.borrarCat) }).then(function (r) {
        if (r.ok) { b.closest('li').remove(); M.avisar(r.mensaje, 'ok'); }
        else M.avisar(r.error, 'error');
      });
    });
  });
})();
</script>
<?php View::stop(); ?>
