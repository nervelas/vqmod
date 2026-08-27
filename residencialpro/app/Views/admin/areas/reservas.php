<?php use App\Core\Vista; ?>
<?php if ($pendientes !== []): ?>
  <div class="tarjeta mb-3">
    <div class="tarjeta-cab"><h3>Solicitudes por aprobar</h3><span class="chip aviso"><?= count($pendientes) ?></span></div>
    <div class="tarjeta-cuerpo compacto">
      <?php foreach ($pendientes as $r): ?>
        <div class="item-lista">
          <span style="color:var(--acento-3)"><?= ico('calendario', 22) ?></span>
          <div class="crecer">
            <b><?= e($r['area']) ?> · <?= e(fecha((string) $r['fecha'])) ?></b>
            <div class="meta">
              Casa <?= e($r['casa']) ?> · <?= e(recortar((string) $r['residente'], 26)) ?> ·
              <?= e(hora((string) $r['hora_desde'])) ?> a <?= e(hora((string) $r['hora_hasta'])) ?> ·
              <?= (int) $r['personas'] ?> personas
              <?= (float) $r['costo'] > 0 ? ' · ' . e(q((float) $r['costo'])) : '' ?>
            </div>
            <?php if (!empty($r['motivo'])): ?><div class="meta">“<?= e($r['motivo']) ?>”</div><?php endif; ?>
          </div>
          <?php if (esRol('admin')): ?>
            <form method="post" action="<?= e(url('/admin/reservas/' . (int) $r['id'] . '/aprobar')) ?>" style="display:inline">
              <?= csrf() ?>
              <button class="btn btn-sm btn-ok" type="submit"><?= ico('check', 15) ?> <span>Aprobar</span></button>
            </form>
            <form method="post" action="<?= e(url('/admin/reservas/' . (int) $r['id'] . '/rechazar')) ?>" class="fila" style="gap:6px">
              <?= csrf() ?>
              <input type="text" name="motivo" placeholder="Motivo" required minlength="5" style="width:150px;padding:8px 10px">
              <button class="btn btn-sm btn-peligro" type="submit" aria-label="Rechazar la reserva">
                <?= ico('equis', 15) ?><span class="solo-lectores">Rechazar</span>
              </button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<form method="get" class="fila envolver mb-3" style="gap:10px">
  <label class="solo-lectores" for="f-area">Área común</label>
  <select id="f-area" name="area" data-auto-enviar style="max-width:220px">
    <option value="">Todas las áreas</option>
    <?php foreach ($areas as $a): ?>
      <option value="<?= (int) $a['id'] ?>" <?= $filtros['area'] === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="solo-lectores" for="f-estado">Estado de la reserva</label>
  <select id="f-estado" name="estado" data-auto-enviar style="max-width:180px">
    <option value="">Todos los estados</option>
    <?php foreach (['pendiente' => 'Pendientes', 'aprobada' => 'Aprobadas', 'rechazada' => 'Rechazadas', 'cancelada' => 'Canceladas', 'completada' => 'Completadas'] as $k => $et): ?>
      <option value="<?= e($k) ?>" <?= $filtros['estado'] === $k ? 'selected' : '' ?>><?= e($et) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="desde" value="<?= e($filtros['desde']) ?>" style="max-width:170px" aria-label="Desde">
  <button class="btn btn-claro btn-sm" type="submit"><?= ico('filtro', 16) ?> Filtrar</button>
</form>

<div class="tarjeta">
  <?php if ($reservas === []): ?>
    <?= Vista::parcial('partials/vacio', ['icono' => 'calendario', 'titulo' => 'No hay reservas con esos filtros']) ?>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla apilar">
        <thead><tr><th>Área</th><th>Casa</th><th>Residente</th><th class="c">Fecha</th><th class="c">Horario</th><th class="c">Personas</th><th class="d">Costo</th><th class="c">Estado</th></tr></thead>
        <tbody>
          <?php foreach ($reservas as $r): ?>
            <tr>
              <td data-et="Área" class="fuerte"><?= e($r['area']) ?></td>
              <td data-et="Casa"><a href="<?= e(url('/admin/casas/' . (int) $r['casa_id'])) ?>"><?= e($r['casa']) ?></a></td>
              <td data-et="Residente"><?= e(recortar((string) $r['residente'], 24)) ?></td>
              <td data-et="Fecha" class="c"><?= e(fecha((string) $r['fecha'])) ?></td>
              <td data-et="Horario" class="c texto-3"><?= e(hora((string) $r['hora_desde'])) ?>–<?= e(hora((string) $r['hora_hasta'])) ?></td>
              <td data-et="Personas" class="c num"><?= (int) $r['personas'] ?></td>
              <td data-et="Costo" class="d num"><?= (float) $r['costo'] > 0 ? e(q((float) $r['costo'])) : '—' ?></td>
              <td data-et="Estado" class="c"><span class="chip <?= e(estadoBadge((string) $r['estado'])) ?>"><?= e(ucfirst((string) $r['estado'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
