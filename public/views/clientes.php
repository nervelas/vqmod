<?php
/** @var list<array<string,mixed>> $clientes @var string $busqueda @var string $csrf
 *  @var list<string> $departamentos @var array<string,mixed>|null $edicion */
use Fel\Web\Vista;
$e = $edicion ?? [];
?>
<div class="encabezado-pagina">
  <h1>Clientes</h1>
  <form method="get" action="index.php" class="acciones">
    <input type="hidden" name="r" value="clientes">
    <input name="q" value="<?= Vista::e($busqueda) ?>" placeholder="Buscar por NIT o nombre" style="width:230px">
    <button class="boton secundario" type="submit">Buscar</button>
  </form>
</div>

<div class="tarjeta">
  <h2><?= $edicion ? 'Editar cliente' : 'Agregar cliente' ?></h2>
  <form method="post" action="index.php?r=cliente_guardar">
    <input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) ($e['id'] ?? 0) ?>">
    <div class="fila">
      <div class="campo">
        <label for="c-id">NIT / CF / CUI</label>
        <input id="c-id" name="identificador" value="<?= Vista::e($e['identificador'] ?? 'CF') ?>" maxlength="25">
      </div>
      <div class="campo">
        <label for="c-tipo">Tipo de identificación</label>
        <select id="c-tipo" name="tipo_especial">
          <option value="" <?= ($e['tipo_especial'] ?? '') === '' ? 'selected' : '' ?>>NIT (o CF)</option>
          <option value="CUI" <?= ($e['tipo_especial'] ?? '') === 'CUI' ? 'selected' : '' ?>>CUI / DPI</option>
          <option value="EXT" <?= ($e['tipo_especial'] ?? '') === 'EXT' ? 'selected' : '' ?>>Extranjero</option>
        </select>
      </div>
      <div class="campo" style="grid-column:span 2">
        <label for="c-nombre">Nombre o razón social</label>
        <input id="c-nombre" name="nombre" value="<?= Vista::e($e['nombre'] ?? '') ?>" required maxlength="255">
      </div>
    </div>
    <div class="fila">
      <div class="campo">
        <label for="c-correo">Correo</label>
        <input id="c-correo" name="correo" type="email" value="<?= Vista::e($e['correo'] ?? '') ?>" maxlength="255">
      </div>
      <div class="campo">
        <label for="c-telefono">Teléfono</label>
        <input id="c-telefono" name="telefono" value="<?= Vista::e($e['telefono'] ?? '') ?>" maxlength="50">
      </div>
      <div class="campo">
        <label for="c-direccion">Dirección</label>
        <input id="c-direccion" name="direccion" value="<?= Vista::e($e['direccion'] ?? 'Ciudad') ?>" maxlength="255">
      </div>
      <div class="campo">
        <label for="c-municipio">Municipio</label>
        <input id="c-municipio" name="municipio" value="<?= Vista::e($e['municipio'] ?? 'Guatemala') ?>" maxlength="100">
      </div>
      <div class="campo">
        <label for="c-departamento">Departamento</label>
        <select id="c-departamento" name="departamento">
          <?php foreach ($departamentos as $departamento): ?>
            <option value="<?= Vista::e($departamento) ?>" <?= ($e['departamento'] ?? 'Guatemala') === $departamento ? 'selected' : '' ?>>
              <?= Vista::e($departamento) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="acciones">
      <button class="boton" type="submit"><?= $edicion ? 'Guardar cambios' : 'Agregar' ?></button>
      <?php if ($edicion): ?><a class="boton secundario" href="index.php?r=clientes">Cancelar</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="tarjeta">
  <?php if ($clientes === []): ?>
    <p class="vacio">No hay clientes registrados.</p>
  <?php else: ?>
    <table class="datos">
      <thead><tr><th>Identificación</th><th>Nombre</th><th>Correo</th><th>Municipio</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($clientes as $cliente): ?>
          <tr>
            <td><?= Vista::e($cliente['identificador']) ?></td>
            <td><?= Vista::e($cliente['nombre']) ?></td>
            <td><?= Vista::e($cliente['correo'] ?: '—') ?></td>
            <td><?= Vista::e($cliente['municipio']) ?></td>
            <td class="acciones">
              <a class="boton pequeno secundario" href="index.php?r=clientes&amp;editar=<?= (int) $cliente['id'] ?>">Editar</a>
              <form method="post" action="index.php?r=cliente_eliminar" style="margin:0"
                    onsubmit="return confirm('¿Desactivar este cliente?')">
                <input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
                <button class="boton pequeno secundario" type="submit">Desactivar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
