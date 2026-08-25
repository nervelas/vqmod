<?php
/** Una fila del detalle. Se reutiliza para el render inicial y para la plantilla JS.
 * @var int|string $indice @var array<string,string> $unidades @var list<array<string,mixed>> $productos */
use Fel\Web\Vista;
?>
<tr>
  <td>
    <select class="f-producto">
      <option value="">— Libre —</option>
      <?php foreach ($productos as $producto): ?>
        <option value="<?= (int) $producto['id'] ?>"
                data-descripcion="<?= Vista::e($producto['descripcion']) ?>"
                data-precio="<?= Vista::e(number_format((float) $producto['precio_unitario'], 6, '.', '')) ?>"
                data-unidad="<?= Vista::e($producto['unidad_medida']) ?>"
                data-tipo="<?= Vista::e($producto['tipo']) ?>"
                data-exento="<?= (int) $producto['exento'] ?>">
          <?= Vista::e(($producto['codigo'] ? $producto['codigo'] . ' — ' : '') . $producto['descripcion']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </td>
  <td><input class="f-descripcion" name="items[<?= $indice ?>][descripcion]" maxlength="255" placeholder="Descripción del bien o servicio"></td>
  <td><input class="f-cantidad" name="items[<?= $indice ?>][cantidad]" type="number" step="0.000001" min="0" value="1"></td>
  <td>
    <select class="f-unidad" name="items[<?= $indice ?>][unidad_medida]">
      <?php foreach ($unidades as $codigo => $nombre): ?>
        <option value="<?= Vista::e($codigo) ?>"><?= Vista::e($codigo) ?></option>
      <?php endforeach; ?>
    </select>
  </td>
  <td>
    <select class="f-tipo" name="items[<?= $indice ?>][tipo]">
      <option value="B">Bien</option>
      <option value="S">Servicio</option>
    </select>
  </td>
  <td><input class="f-precio" name="items[<?= $indice ?>][precio_unitario]" type="number" step="0.000001" min="0" value="0"></td>
  <td><input class="f-descuento" name="items[<?= $indice ?>][descuento]" type="number" step="0.01" min="0" value="0"></td>
  <td style="text-align:center">
    <input class="f-exento" name="items[<?= $indice ?>][exento]" type="checkbox" value="1" style="width:auto">
  </td>
  <td class="num f-total">0.00</td>
  <td><button class="boton pequeno secundario quitar-linea" type="button" title="Quitar línea">×</button></td>
</tr>
