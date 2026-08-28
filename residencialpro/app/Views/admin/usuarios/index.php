<div class="fila-entre mb-3">
  <form class="fila envolver crecer" method="get" style="gap:10px">
    <div class="entrada-icono" style="max-width:280px">
      <?= ico('buscar', 18) ?>
      <input type="search" name="buscar" aria-label="Buscar por nombre, usuario o correo" value="<?= e($buscar) ?>" placeholder="Nombre, usuario o correo">
    </div>
    <select aria-label="Filtrar por perfil" name="rol" data-auto-enviar style="max-width:200px">
      <option value="">Todos los perfiles</option>
      <?php foreach (['admin', 'junta', 'contabilidad', 'garita', 'residente'] as $r): ?>
        <option value="<?= e($r) ?>" <?= $rol === $r ? 'selected' : '' ?>><?= e(rolNombre($r)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-claro btn-sm" type="submit"><?= ico('filtro', 16) ?> Filtrar</button>
  </form>
  <a class="btn btn-oro" href="<?= e(url('/admin/usuarios/nuevo')) ?>"><?= ico('mas', 17) ?> Nuevo usuario</a>
</div>

<div class="tarjeta">
  <div class="tabla-caja">
    <table class="tabla apilar">
      <thead><tr><th>Usuario</th><th>Perfil</th><th>Contacto</th><th>Viviendas</th><th class="c">Último acceso</th><th class="c">Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
          <tr>
            <td data-et="Usuario">
              <div class="fila" style="gap:10px">
                <span class="avatar sm"><?= e(iniciales((string) $u['nombre'])) ?></span>
                <div><b><?= e($u['nombre']) ?></b><div class="meta texto-3"><?= e($u['usuario']) ?></div></div>
              </div>
            </td>
            <td data-et="Perfil">
              <span class="chip <?= $u['rol'] === 'admin' ? 'oro' : ($u['rol'] === 'garita' ? 'info' : 'neutro') ?>"><?= e(rolNombre((string) $u['rol'])) ?></span>
            </td>
            <td data-et="Contacto" class="texto-2" style="font-size:.85rem">
              <?= e($u['correo'] ?? '—') ?><?php if (!empty($u['telefono'])): ?><div class="texto-3"><?= e($u['telefono']) ?></div><?php endif; ?>
            </td>
            <td data-et="Viviendas" class="texto-3" style="font-size:.85rem"><?= e(recortar((string) $u['casas'], 26) ?: '—') ?></td>
            <td data-et="Último acceso" class="c texto-3" style="font-size:.84rem"><?= $u['ultimo_acceso'] ? e(hace((string) $u['ultimo_acceso'])) : 'nunca' ?></td>
            <td data-et="Estado" class="c"><span class="chip <?= (int) $u['activo'] === 1 ? 'ok' : 'neutro' ?>"><?= (int) $u['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
            <td data-et="" class="d">
              <a class="btn btn-sm btn-fantasma" href="<?= e(url('/admin/usuarios/' . (int) $u['id'] . '/editar')) ?>" aria-label="Editar usuario"><?= ico('editar', 15) ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
