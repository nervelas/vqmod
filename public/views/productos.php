<?php
/** @var list<array<string,mixed>> $productos @var string $busqueda @var string $csrf
 *  @var array<string,string> $unidades @var array<string,mixed>|null $edicion */
use Fel\Web\Vista;
$e = $edicion ?? [];
?>
<div class="encabezado-pagina">
  <h1>Productos y servicios</h1>
  <form method="get" action="index.php" class="acciones">
    <input type="hidden" name="r" value="productos">
    <input name="q" value="<?= Vista::e($busqueda) ?>" placeholder="Buscar por código o descripción" style="width:250px">
    <button class="boton secundario" type="submit">Buscar</button>
  </form>
</div>

<div class="tarjeta">
  <h2><?= $edicion ? 'Editar producto' : 'Agregar producto' ?></h2>
  <form method="post" action="index.php?r=producto_guardar">
    <input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) ($e['id'] ?? 0) ?>">
    <div class="fila">
      <div class="campo">
        <label for="p-codigo">Código</label>
        <input id="p-codigo" name="codigo" value="<?= Vista::e($e['codigo'] ?? '') ?>" maxlength="50">
      </div>
      <div class="campo" style="grid-column:span 2">
        <label for="p-descripcion">Descripción</label>
        <input id="p-descripcion" name="descripcion" value="<?= Vista::e($e['descripcion'] ?? '') ?>" required maxlength="255">
      </div>
      <div class="campo">
        <label for="p-tipo">Bien o servicio</label>
        <select id="p-tipo" name="tipo">
          <option value="B" <?= ($e['tipo'] ?? 'B') === 'B' ? 'selected' : '' ?>>Bien</option>
          <option value="S" <?= ($e['tipo'] ?? '') === 'S' ? 'selected' : '' ?>>Servicio</option>
        </select>
      </div>
      <div class="campo">
        <label for="p-unidad">Unidad de medida</label>
        <select id="p-unidad" name="unidad_medida">
          <?php foreach ($unidades as $codigo => $nombre): ?>
            <option value="<?= Vista::e($codigo) ?>" <?= ($e['unidad_medida'] ?? 'UNI') === $codigo ? 'selected' : '' ?>>
              <?= Vista::e($codigo . ' — ' . $nombre) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="p-precio">Precio con IVA incluido</label>
        <input id="p-precio" name="precio_unitario" type="number" step="0.000001" min="0"
               value="<?= Vista::e(number_format((float) ($e['precio_unitario'] ?? 0), 2, '.', '')) ?>">
      </div>
    </div>
    <label class="casilla">
      <input type="checkbox" name="exento" value="1" <?= !empty($e['exento']) ? 'checked' : '' ?>>
      Exento de IVA
    </label>
    <div class="acciones" style="margin-top:12px">
      <button class="boton" type="submit"><?= $edicion ? 'Guardar cambios' : 'Agregar' ?></button>
      <?php if ($edicion): ?><a class="boton secundario" href="index.php?r=productos">Cancelar</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="tarjeta">
  <?php if ($productos === []): ?>
    <p class="vacio">No hay productos registrados.</p>
  <?php else: ?>
    <table class="datos">
      <thead><tr><th>Código</th><th>Descripción</th><th>Tipo</th><th>U/M</th><th class="num">Precio</th><th>IVA</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($productos as $producto): ?>
          <tr>
            <td><?= Vista::e($producto['codigo'] ?: '—') ?></td>
            <td><?= Vista::e($producto['descripcion']) ?></td>
            <td><?= $producto['tipo'] === 'S' ? 'Servicio' : 'Bien' ?></td>
            <td><?= Vista::e($producto['unidad_medida']) ?></td>
            <td class="num"><?= Vista::moneda($producto['precio_unitario']) ?></td>
            <td><?= !empty($producto['exento']) ? 'Exento' : 'Gravado' ?></td>
            <td class="acciones">
              <a class="boton pequeno secundario" href="index.php?r=productos&amp;editar=<?= (int) $producto['id'] ?>">Editar</a>
              <form method="post" action="index.php?r=producto_eliminar" style="margin:0"
                    onsubmit="return confirm('¿Desactivar este producto?')">
                <input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $producto['id'] ?>">
                <button class="boton pequeno secundario" type="submit">Desactivar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
