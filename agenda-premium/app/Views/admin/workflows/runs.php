<?php
use App\Core\Fmt;

$tzb = \App\Core\Settings::tz();
$st = ['pending' => ['En cola', 'badge-warn'], 'done' => ['Hecho', 'badge-ok'], 'failed' => ['Con error', 'badge-err'], 'skipped' => ['Omitido', 'badge-muted']];
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">Ejecuciones de flujos</h1><p class="page-sub">Bitácora de cada acción automática: cuándo se programó, si salió bien y qué pasó si falló.</p></div>
    <div class="page-actions"><a class="btn btn-ghost" href="<?= e(url('/admin/flujos')) ?>"><?= icon('arrow-left') ?>Volver a flujos</a></div>
  </div>
  <div class="grid cols-4 mb-3">
    <?php foreach ($st as $k => $l) : ?><div class="stat"><div class="stat-label"><?= e($l[0]) ?></div><div class="stat-value serif"><?= (int) ($counts[$k] ?? 0) ?></div></div><?php endforeach; ?>
  </div>
  <form class="card mb-3" method="get" action="<?= e(url('/admin/flujos/ejecuciones')) ?>"><div class="card-body"><div class="form-row p3-filters">
    <div class="field"><label for="ru-w">Flujo</label><select class="select" id="ru-w" name="flujo"><option value="0">Todos</option><?php foreach ($workflows as $w) : ?><option value="<?= (int) $w['id'] ?>"<?= sel($wfId, $w['id']) ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="ru-s">Estado</label><select class="select" id="ru-s" name="estado"><option value="">Todos</option><?php foreach ($st as $k => $l) : ?><option value="<?= e($k) ?>"<?= sel($status, $k) ?>><?= e($l[0]) ?></option><?php endforeach; ?></select></div>
    <div class="field p3-filter-btn"><button class="btn btn-outline" type="submit"><?= icon('filter') ?>Filtrar</button></div>
  </div></div></form>
  <?php if (!$rows) : ?>
    <div class="empty"><?= icon('zap') ?><p class="empty-title">Sin ejecuciones</p><p class="empty-text">Cuando se cree o se acerque una cita, aquí verás lo que hizo cada flujo.</p></div>
  <?php else : ?>
    <div class="card"><div class="table-wrap"><table class="table table-sm">
      <caption class="sr-only">Bitácora de ejecuciones</caption>
      <thead><tr><th scope="col">Programada</th><th scope="col">Flujo</th><th scope="col">Cita</th><th scope="col">Estado</th><th scope="col" class="right">Intentos</th><th scope="col" class="hide-sm">Detalle</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r) : $s = $st[$r['status']] ?? ['—', 'badge-muted']; ?>
        <tr>
          <td class="nowrap mono"><?= e(Fmt::dateShort((string) $r['scheduled_at'], $tzb)) ?> <?= e(Fmt::time((string) $r['scheduled_at'], $tzb)) ?></td>
          <th scope="row"><?= e($r['wf_name']) ?><div class="muted"><?= e($actions[$r['wf_action']] ?? '') ?></div></th>
          <td><a class="text-gold" href="<?= e(url('/admin/citas/' . (int) $r['booking_id'])) ?>"><?= e($r['guest_name']) ?></a></td>
          <td><span class="badge <?= e($s[1]) ?>"><?= e($s[0]) ?></span></td>
          <td class="right mono"><?= (int) $r['attempts'] ?></td>
          <td class="hide-sm p3-resp"><?= $r['last_error'] ? '<span class="text-err">' . e($r['last_error']) . '</span>' : ($r['executed_at'] ? '<span class="muted">Ejecutado ' . e(Fmt::time((string) $r['executed_at'], $tzb)) . '</span>' : '') ?></td>
          <td class="right"><?php if (in_array($r['status'], ['failed', 'skipped'], true)) : ?><form class="inline" method="post" action="<?= e(url('/admin/flujos/ejecuciones/' . (int) $r['id'] . '/reintentar')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh') ?>Reintentar</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div></div>
  <?php endif; ?>
</div>
