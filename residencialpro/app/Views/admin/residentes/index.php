<?php use App\Core\Vista; ?>
<div class="fila-entre mb-3">
  <form class="fila envolver crecer" method="get" style="gap:10px">
    <div class="entrada-icono" style="max-width:300px">
      <?= ico('buscar', 18) ?>
      <input type="search" name="buscar" aria-label="Buscar por nombre, DPI, teléfono o casa" value="<?= e($buscar) ?>" placeholder="Nombre, DPI, teléfono o casa">
    </div>
    <select aria-label="Filtrar por tipo" name="tipo" data-auto-enviar style="max-width:170px">
      <option value="">Todos los tipos</option>
      <?php foreach (['propietario' => 'Propietarios', 'inquilino' => 'Inquilinos', 'familiar' => 'Familiares'] as $k => $et): ?>
        <option value="<?= e($k) ?>" <?= $tipo === $k ? 'selected' : '' ?>><?= e($et) ?></option>
      <?php endforeach; ?>
    </select>
    <select aria-label="Filtrar por estado del residente" name="activo" data-auto-enviar style="max-width:150px">
      <option value="1" <?= $activo === '1' ? 'selected' : '' ?>>Activos</option>
      <option value="0" <?= $activo === '0' ? 'selected' : '' ?>>Inactivos</option>
      <option value=""  <?= $activo === ''  ? 'selected' : '' ?>>Todos</option>
    </select>
    <button class="btn btn-claro btn-sm" type="submit"><?= ico('filtro', 16) ?> Filtrar</button>
  </form>
  <?php if (esRol('admin')): ?>
    <a class="btn btn-oro" href="<?= e(url('/admin/residentes/nuevo')) ?>"><?= ico('mas', 17) ?> Nuevo residente</a>
  <?php endif; ?>
</div>

<div class="tarjeta">
  <?php if ($residentes === []): ?>
    <?= Vista::parcial('partials/vacio', ['icono' => 'usuarios', 'titulo' => 'No se encontraron residentes',
        'texto' => 'Ajuste la búsqueda o registre al primer residente.',
        'accion' => esRol('admin') ? '/admin/residentes/nuevo' : null, 'accionTexto' => 'Registrar residente']) ?>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla apilar">
        <thead><tr><th>Residente</th><th>Casa</th><th>Tipo</th><th>Contacto</th><th class="c">Portal</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($residentes as $r): ?>
            <tr>
              <td data-et="Residente">
                <div class="fila" style="gap:10px">
                  <span class="avatar sm"><?= e(iniciales((string) $r['nombre'])) ?></span>
                  <div>
                    <b><?= e($r['nombre']) ?></b>
                    <?php if (!empty($r['dpi'])): ?><div class="meta texto-3">DPI <?= e($r['dpi']) ?></div><?php endif; ?>
                  </div>
                </div>
              </td>
              <td data-et="Casa">
                <a href="<?= e(url('/admin/casas/' . (int) $r['casa_id'])) ?>" class="fuerte"><?= e($r['casa']) ?></a>
                <div class="meta texto-3"><?= e($r['fase'] ?? '') ?></div>
              </td>
              <td data-et="Tipo"><span class="chip <?= $r['tipo'] === 'propietario' ? 'oro' : 'neutro' ?>"><?= e(ucfirst((string) $r['tipo'])) ?></span></td>
              <td data-et="Contacto" class="texto-2" style="font-size:.85rem">
                <?= e($r['telefono'] ?? '—') ?><?php if (!empty($r['correo'])): ?><div class="texto-3"><?= e($r['correo']) ?></div><?php endif; ?>
              </td>
              <td data-et="Portal" class="c">
                <?php if (!empty($r['acceso'])): ?>
                  <span class="chip <?= (int) ($r['acceso_activo'] ?? 0) === 1 ? 'ok' : 'neutro' ?>"><?= e($r['acceso']) ?></span>
                <?php elseif (esRol('admin') && !empty($r['correo'])): ?>
                  <form method="post" action="<?= e(url('/admin/residentes/' . (int) $r['id'] . '/acceso')) ?>" style="display:inline"
                        data-confirmar="Se creará un usuario y se enviará la contraseña temporal al correo del residente."
                        data-confirmar-titulo="¿Crear acceso al portal?" data-confirmar-boton="Sí, crear">
                    <?= csrf() ?>
                    <button class="btn btn-sm btn-fantasma" type="submit"><?= ico('llave', 14) ?> Crear</button>
                  </form>
                <?php else: ?><span class="texto-3">—</span><?php endif; ?>
              </td>
              <td data-et="" class="d nowrap">
                <?php if (!empty($r['telefono'])): ?>
                  <a class="btn btn-sm btn-fantasma" target="_blank" rel="noopener" href="<?= e(whatsapp((string) $r['telefono'])) ?>" aria-label="WhatsApp"><?= ico('chat', 15) ?></a>
                <?php endif; ?>
                <?php if (esRol('admin')): ?>
                  <a class="btn btn-sm btn-fantasma" href="<?= e(url('/admin/residentes/' . (int) $r['id'] . '/editar')) ?>" aria-label="Editar"><?= ico('editar', 15) ?></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?= Vista::parcial('partials/paginacion', ['pagina' => $pagina, 'total' => $total, 'porPagina' => $porPagina]) ?>
