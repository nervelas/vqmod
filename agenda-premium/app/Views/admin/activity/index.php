<?php
/** Centro de actividad. */
use App\Controllers\Admin\A4Controller;

$hasFilter = (bool) $query;
$exportQuery = $query;
?>
<div class="page p4-page">
  <div class="page-head">
    <div>
      <h1 class="page-title">Actividad</h1>
      <p class="page-sub">Quién cambió qué en el panel y cómo se han movido tus citas más recientes.</p>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline" href="<?= e(url('/admin/actividad/exportar', $exportQuery)) ?>"><?= icon('download') ?>Exportar CSV</a>
    </div>
  </div>

  <div class="grid p4-activity">
    <section class="card" aria-labelledby="h-aud">
      <div class="card-head"><h2 id="h-aud" class="serif">Bitácora de auditoría</h2><span class="badge badge-muted"><?= number_format($total) ?> registros</span></div>
      <div class="card-body stack">
        <form class="form-row p4-filters" method="get" action="<?= e(url('/admin/actividad')) ?>">
          <div class="field">
            <label for="f-usuario">Usuario</label>
            <select class="select" id="f-usuario" name="usuario">
              <option value="">Todos</option>
              <option value="sistema"<?= sel($filters['usuario'], 'sistema') ?>>Sistema</option>
              <?php foreach ($users as $u) : ?><option value="<?= (int) $u['id'] ?>"<?= sel($filters['usuario'], (string) $u['id']) ?>><?= e($u['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="f-accion">Acción</label>
            <input class="input" type="text" id="f-accion" name="accion" list="dl-acciones" value="<?= e($filters['accion']) ?>" maxlength="60" placeholder="settings, booking…" autocomplete="off">
            <datalist id="dl-acciones"><?php foreach ($actions as $a) : ?><option value="<?= e($a) ?>"><?php endforeach; ?></datalist>
          </div>
          <div class="field">
            <label for="f-desde">Desde</label>
            <input class="input" type="date" id="f-desde" name="desde" value="<?= e($filters['desde']) ?>">
          </div>
          <div class="field">
            <label for="f-hasta">Hasta</label>
            <input class="input" type="date" id="f-hasta" name="hasta" value="<?= e($filters['hasta']) ?>">
          </div>
          <div class="field p4-filter-actions">
            <button class="btn btn-gold" type="submit"><?= icon('filter') ?>Filtrar</button>
            <?php if ($hasFilter) : ?><a class="btn btn-ghost" href="<?= e(url('/admin/actividad')) ?>">Limpiar</a><?php endif; ?>
          </div>
        </form>

        <?php if (!$rows) : ?>
          <div class="empty">
            <?= icon('bell') ?>
            <p class="empty-title"><?= $hasFilter ? 'Sin resultados' : 'Todavía no hay actividad' ?></p>
            <p class="empty-text"><?= $hasFilter ? 'Prueba con otro rango de fechas o quita algún filtro.' : 'Aquí aparecerán los cambios importantes: ajustes, usuarios, respaldos y más.' ?></p>
          </div>
        <?php else : ?>
          <div class="table-wrap">
            <table class="table table-sm">
              <thead><tr><th scope="col">Fecha</th><th scope="col">Usuario</th><th scope="col">Acción</th><th scope="col">Detalle</th></tr></thead>
              <tbody>
              <?php foreach ($rows as $r) : ?>
                <tr>
                  <td class="mono nowrap"><?= e(A4Controller::local($r['created_at'], 'd/m/Y H:i')) ?></td>
                  <td><?= e($r['user_label'] ?: 'Sistema') ?><?php if ($r['ip_trunc']) : ?><br><span class="muted mono"><?= e($r['ip_trunc']) ?></span><?php endif; ?></td>
                  <td><span class="badge badge-gold mono"><?= e($r['action']) ?></span><?php if ($r['entity']) : ?><br><span class="muted"><?= e($r['entity']) ?><?= $r['entity_id'] !== null && $r['entity_id'] !== '' ? ' #' . e($r['entity_id']) : '' ?></span><?php endif; ?></td>
                  <td class="p4-detail"><?= e($r['detail'] ?: '—') ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($pages > 1) : ?>
            <nav class="pagination" aria-label="Páginas de la bitácora">
              <?php if ($page > 1) : ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/actividad', $query + ['pagina' => $page - 1])) ?>"><?= icon('chevron-left') ?>Anterior</a><?php endif; ?>
              <span class="muted">Página <?= (int) $page ?> de <?= (int) $pages ?></span>
              <?php if ($page < $pages) : ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/actividad', $query + ['pagina' => $page + 1])) ?>">Siguiente<?= icon('chevron-right') ?></a><?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="card" aria-labelledby="h-tl">
      <div class="card-head"><h2 id="h-tl" class="serif">Línea de tiempo de citas</h2></div>
      <div class="card-body">
        <?php if (!$timeline) : ?>
          <div class="empty">
            <?= icon('calendar') ?>
            <p class="empty-title">Sin movimientos de citas</p>
            <p class="empty-text">Cuando alguien reserve, cambie o cancele una cita, lo verás aquí en orden.</p>
          </div>
        <?php else : ?>
          <ol class="p4-timeline">
            <?php foreach ($timeline as $t) : $act = (string) $t['action']; ?>
              <li class="p4-tl-item is-<?= e(preg_replace('/[^a-z_]/', '', $act)) ?>">
                <span class="p4-tl-dot" aria-hidden="true"></span>
                <div class="p4-tl-body">
                  <p class="p4-tl-title"><strong><?= e($historyLabels[$act] ?? $act) ?></strong> · <?= e($t['guest_name']) ?><?= $t['event_name'] ? ' · ' . e($t['event_name']) : '' ?></p>
                  <p class="muted p4-tl-meta"><span class="mono"><?= e(A4Controller::local($t['created_at'], 'd/m H:i')) ?></span><?= $t['actor'] ? ' · ' . e($t['actor']) : '' ?><?= $t['detail'] ? ' · ' . e($t['detail']) : '' ?></p>
                  <a class="p4-tl-link text-gold" href="<?= e(url('/admin/citas/' . (int) $t['booking_id'])) ?>">Ver cita #<?= (int) $t['booking_id'] ?></a>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>
