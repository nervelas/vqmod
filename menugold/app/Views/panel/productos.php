<?php
/** @var array $productos, $cats, $mapaCats, $limites; string $q, $filtro; int $catId, $totalProd */
use MenuGold\Core\Security;
use MenuGold\Core\View;
use MenuGold\Models\Product;

View::set('titulo', 'Platillos');
View::set('subtitulo', $totalProd . ' platillo(s) en tu menú'
    . ((int)$limites['max_productos'] > 0 ? ' · tu plan permite ' . (int)$limites['max_productos'] : ''));
$s = (string)($r['simbolo'] ?? 'Q');

View::start('acciones');
?>
<a class="bt bt--oro" href="<?= e(url('panel/productos/nuevo')) ?>"><?= icon('plus') ?><span class="oculto-movil">Nuevo platillo</span></a>
<?php View::stop(); ?>

<form class="filtros-barra" method="get" action="<?= e(url('panel/productos')) ?>">
  <div class="campo-p" style="flex:2 1 220px">
    <label for="buscarPanel">Buscar</label>
    <input type="search" id="buscarPanel" name="q" value="<?= e($q) ?>" placeholder="Nombre, descripción o código">
  </div>
  <div class="campo-p">
    <label for="cat">Categoría</label>
    <select id="cat" name="cat">
      <option value="0">Todas</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= e((string)$c['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="campo-p">
    <label for="f">Mostrar</label>
    <select id="f" name="f">
      <option value="">Todos</option>
      <option value="agotados"   <?= $filtro === 'agotados' ? 'selected' : '' ?>>Solo agotados</option>
      <option value="inactivos"  <?= $filtro === 'inactivos' ? 'selected' : '' ?>>Solo ocultos</option>
      <option value="destacados" <?= $filtro === 'destacados' ? 'selected' : '' ?>>Solo recomendados</option>
    </select>
  </div>
  <button class="bt bt--linea" type="submit"><?= icon('filter') ?> Filtrar</button>
  <?php if ($q !== '' || $catId > 0 || $filtro !== ''): ?>
    <a class="bt bt--suave" href="<?= e(url('panel/productos')) ?>"><?= icon('x') ?> Limpiar</a>
  <?php endif; ?>
</form>

<div class="tarjeta-p tarjeta-p--plana">
  <?php if (!$productos): ?>
    <div class="vacio-p">
      <?= icon('utensils', 'ico-lg') ?>
      <h3><?= $q !== '' || $catId > 0 ? 'Sin resultados' : 'Tu menú está vacío' ?></h3>
      <p><?= $q !== '' || $catId > 0 ? 'Prueba con otra búsqueda.' : 'Crea tu primer platillo y aparecerá al instante en el menú QR.' ?></p>
      <a class="bt bt--oro" href="<?= e(url('panel/productos/nuevo')) ?>"><?= icon('plus') ?> Crear platillo</a>
    </div>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr>
          <th style="width:30px"></th>
          <th>Platillo</th><th>Categoría</th>
          <th class="num">Precio</th>
          <th style="text-align:center">Agotado</th>
          <th style="text-align:center">Visible</th>
          <th></th>
        </tr></thead>
        <tbody id="listaProductos">
          <?php foreach ($productos as $p): ?>
            <tr draggable="true" data-id="<?= (int)$p['id'] ?>">
              <td><span class="arrastrable" title="Arrastra para reordenar"><?= icon('grip', 'ico-sm') ?></span></td>
              <td>
                <div class="celda-producto">
                  <?php if (!empty($p['imagen'])): ?>
                    <img class="mini-foto" src="<?= e(uploaded((string)$p['imagen'])) ?>" alt="" loading="lazy" width="42" height="42">
                  <?php else: ?>
                    <span class="mini-foto" style="display:grid;place-items:center;color:var(--p-tenue)"><?= icon('image', 'ico-sm') ?></span>
                  <?php endif; ?>
                  <div class="crece truncar">
                    <strong class="truncar"><?= e((string)$p['nombre']) ?></strong>
                    <small class="truncar">
                      <?= (int)$p['tiempo_prep'] ?> min · <?= e(ucfirst((string)$p['estacion'])) ?>
                      <?php foreach (array_slice(Product::etiquetasArray($p), 0, 2) as $t): ?>
                        · <?= e(Product::ETIQUETAS[$t][0]) ?>
                      <?php endforeach; ?>
                    </small>
                  </div>
                </div>
              </td>
              <td style="color:var(--p-suave)"><?= e($mapaCats[(int)$p['category_id']] ?? 'Sin categoría') ?></td>
              <td class="num">
                <?php if (Product::tieneDescuento($p)): ?>
                  <span style="text-decoration:line-through;color:var(--p-tenue);font-size:12px"><?= e(money($p['precio'], $s)) ?></span><br>
                  <strong style="color:var(--p-oro)"><?= e(money(Product::precioVigente($p), $s)) ?></strong>
                <?php else: ?>
                  <strong><?= e(money($p['precio'], $s)) ?></strong>
                <?php endif; ?>
              </td>
              <td style="text-align:center">
                <label class="interruptor" title="Marcar como agotado">
                  <input type="checkbox" data-alternar="panel/productos/agotado" data-campo="agotado"
                         data-id="<?= (int)$p['id'] ?>" <?= (int)$p['agotado'] === 1 ? 'checked' : '' ?>>
                  <span class="interruptor__pista" style="--x:1"></span>
                </label>
              </td>
              <td style="text-align:center">
                <label class="interruptor" title="Visible en el menú">
                  <input type="checkbox" data-alternar="panel/productos/agotado" data-campo="activo"
                         data-id="<?= (int)$p['id'] ?>" <?= (int)$p['activo'] === 1 ? 'checked' : '' ?>>
                  <span class="interruptor__pista"></span>
                </label>
              </td>
              <td class="tabla__acciones">
                <a class="bt bt--sm bt--suave" href="<?= e(url('panel/productos/' . $p['id'])) ?>" title="Editar"><?= icon('edit', 'ico-sm') ?></a>
                <button class="bt bt--sm bt--suave" type="button" data-duplicar="<?= (int)$p['id'] ?>" title="Duplicar"><?= icon('copy', 'ico-sm') ?></button>
                <button class="bt bt--sm bt--suave" type="button" data-borrar="<?= (int)$p['id'] ?>"
                        data-nombre="<?= e((string)$p['nombre']) ?>" title="Eliminar"><?= icon('trash', 'ico-sm') ?></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  var M = window.MGPanel;
  var lista = document.getElementById('listaProductos');
  if (lista) {
    M.ordenable(lista, function (ids) {
      M.pedir('panel/productos/ordenar', { ids: ids }).then(function (r) {
        if (r.ok) M.avisar('Orden guardado', 'ok');
      });
    });
  }

  document.addEventListener('click', function (ev) {
    var d = ev.target.closest('[data-duplicar]');
    if (d) {
      M.pedir('panel/productos/duplicar', { id: Number(d.dataset.duplicar) }).then(function (r) {
        if (r.ok) { M.avisar(r.mensaje, 'ok'); setTimeout(function () { location.href = r.url; }, 500); }
        else M.avisar(r.error, 'error');
      });
      return;
    }
    var b = ev.target.closest('[data-borrar]');
    if (b) {
      M.confirmar('Se eliminará "' + b.dataset.nombre + '" de tu menú. Esta acción no se puede deshacer.',
                  'Eliminar platillo', 'Sí, eliminar').then(function (ok) {
        if (!ok) return;
        M.pedir('panel/productos/borrar', { id: Number(b.dataset.borrar) }).then(function (r) {
          if (r.ok) { M.avisar(r.mensaje, 'ok'); b.closest('tr').remove(); }
          else M.avisar(r.error, 'error');
        });
      });
    }
  });
})();
</script>
<?php View::stop(); ?>
