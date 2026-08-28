<?php
/** @var list<array<string,mixed>> $usuarios @var list<\Fel\Plataforma\Empresa> $empresas @var string $csrf */
use Fel\Web\Vista;
?>
<div class="encabezado-pagina">
  <h1>Usuarios</h1>
</div>

<div class="tarjeta">
  <h2>Agregar usuario o cambiar contraseña</h2>
  <p style="font-size:12.5px;color:#5b6875;margin-top:-8px">
    Si el usuario ya existe, solo se actualiza su contraseña.
  </p>
  <form method="post" action="index.php?r=usuario_guardar">
    <input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">
    <div class="fila">
      <div class="campo">
        <label for="u-usuario">Usuario</label>
        <input id="u-usuario" name="usuario" required maxlength="60" autocomplete="off">
      </div>
      <div class="campo">
        <label for="u-nombre">Nombre completo</label>
        <input id="u-nombre" name="nombre" maxlength="120">
      </div>
      <div class="campo">
        <label for="u-clave">Contraseña (mínimo 10)</label>
        <input id="u-clave" name="clave" type="password" required autocomplete="new-password">
      </div>
      <div class="campo">
        <label for="u-rol">Rol</label>
        <select id="u-rol" name="rol">
          <option value="operador">Operador — solo factura</option>
          <option value="admin">Administrador de la empresa</option>
          <option value="superadmin">Administrador de la plataforma</option>
        </select>
      </div>
      <div class="campo">
        <label for="u-empresa">Empresa</label>
        <select id="u-empresa" name="empresa_id">
          <option value="">— Ninguna (plataforma) —</option>
          <?php foreach ($empresas as $empresa): ?>
            <option value="<?= $empresa->id() ?>"><?= Vista::e($empresa->nombreInterno()) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <button class="boton" type="submit">Guardar</button>
  </form>
</div>

<div class="tarjeta">
  <table class="datos">
    <thead><tr><th>Usuario</th><th>Nombre</th><th>Empresa</th><th>Rol</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($usuarios as $u): ?>
        <tr>
          <td><?= Vista::e($u['usuario']) ?></td>
          <td><?= Vista::e($u['nombre']) ?></td>
          <td><?= Vista::e($u['empresa'] ?? '— plataforma —') ?></td>
          <td><?= Vista::e($u['rol']) ?></td>
          <td>
            <span class="etiqueta <?= $u['activo'] ? 'CERTIFICADO' : 'ANULADO' ?>">
              <?= $u['activo'] ? 'activo' : 'inactivo' ?>
            </span>
          </td>
          <td>
            <form method="post" action="index.php?r=usuario_estado" style="margin:0">
              <input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="activo" value="<?= $u['activo'] ? '0' : '1' ?>">
              <button class="boton pequeno secundario" type="submit">
                <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
