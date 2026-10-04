<?php use Aurea\Services\BookingService; ?>
<form method="get" class="toolbar no-print">
  <div class="field grow"><label for="q"><?= e(__('Buscar')) ?></label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('Nombre, teléfono o correo')) ?>"></div>
  <div class="field"><label for="st"><?= e(__('Estado')) ?></label><select id="st" name="estado"><option value=""><?= e(__('Todos')) ?></option><?php foreach (BookingService::STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= sel($k, $status) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <?php if (!\Aurea\Core\Auth::scopeProfessional()): ?><div class="field"><label for="pr"><?= e(term('professional')) ?></label><select id="pr" name="prof"><option value="0"><?= e(__('Todos')) ?></option><?php foreach ($profs as $p): ?><option value="<?= (int)$p['id'] ?>"<?= sel($p['id'], $prof) ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <div class="field"><label for="d1"><?= e(__('Desde')) ?></label><input id="d1" type="date" name="desde" value="<?= e($from) ?>"></div>
  <div class="field"><label for="d2"><?= e(__('Hasta')) ?></label><input id="d2" type="date" name="hasta" value="<?= e($to) ?>"></div>
  <button class="btn btn-ink" type="submit"><?= e(__('Filtrar')) ?></button>
  <a class="btn btn-gold" href="<?= e(url('/admin/citas/nueva')) ?>">+ <?= e(__('Nueva')) ?></a>
</form>
<?php if (!$rows): ?><div class="card empty-state"><div class="big"><?= e(__('Sin resultados')) ?></div><p><?= e(__('No hay %s con esos filtros.', mb_strtolower(term('appts')))) ?></p></div><?php else: ?>
<div class="tbl-wrap"><table class="tbl"><thead><tr><th><?= e(__('Fecha')) ?></th><th><?= e(term('client')) ?></th><th><?= e(__('Servicio')) ?></th><th><?= e(term('professional')) ?></th><th><?= e(__('Estado')) ?></th><th><?= e(__('Pago')) ?></th><th class="r"><?= e(__('Total')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $a): ?><tr>
  <td class="num"><a class="strong" href="<?= e(url('/admin/citas/' . $a['id'])) ?>"><?= e(fdate($a['start_at'])) ?> <?= e(ftime($a['start_at'])) ?></a></td>
  <td><?= e($a['client_name']) ?><br><span class="hint">+<?= e($a['phone_cc']) ?> <?= e($a['client_phone']) ?></span></td>
  <td><?= e($a['service_name']) ?></td><td><span class="dot" style="background:<?= e($a['prof_color']) ?>"></span><?= e($a['prof_name']) ?></td>
  <td><span class="st-<?= e($a['status']) ?>"><?= e(BookingService::STATUSES[$a['status']]) ?></span></td>
  <td><?= e(['unpaid' => __('Pendiente'), 'partial' => __('Parcial'), 'paid' => __('Pagado')][$a['payment_status']] ?? '') ?></td><td class="r"><?= e(money($a['total'])) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php include __DIR__ . '/../partials/pager.php'; endif; ?>
