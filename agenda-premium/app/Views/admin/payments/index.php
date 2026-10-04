<?php
use App\Core\Fmt;
use App\Services\PaymentService;

$tz = \App\Core\Settings::tz();
$stBadge = ['pending' => 'badge-warn', 'verified' => 'badge-ok', 'rejected' => 'badge-err', 'refunded' => 'badge-muted'];
$methods = ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'card_onsite' => 'Tarjeta en el local', 'link' => 'Enlace de pago', 'package' => 'Paquete', 'gift_card' => 'Certificado', 'other' => 'Otro'];
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">Pagos</h1><p class="page-sub">Cobros por cita y por paquete, comprobantes por verificar y reembolsos. Para registrar un pago, abre la cita correspondiente.</p></div>
  </div>

  <div class="grid cols-3 mb-3">
    <div class="stat"><div class="stat-label">Cobrado (verificado)</div><div class="stat-value serif"><?= e(money($totals['verified'])) ?></div></div>
    <div class="stat"><div class="stat-label">Reembolsado</div><div class="stat-value serif"><?= e(money($totals['refunded'])) ?></div></div>
    <div class="stat"><div class="stat-label">Por verificar</div><div class="stat-value serif"><?= (int) $totals['pending'] ?></div></div>
  </div>

  <section class="card card-gold mb-4" aria-labelledby="h-pend">
    <div class="card-head"><h2 id="h-pend" class="serif">Comprobantes por verificar</h2></div>
    <?php if (!$pending) : ?>
      <div class="card-body"><div class="empty"><?= icon('check') ?><p class="empty-title">Todo al día</p><p class="empty-text">Cuando alguien suba un comprobante de transferencia, aparecerá aquí para que lo revises.</p></div></div>
    <?php else : ?>
      <ul class="p3-proofs">
        <?php foreach ($pending as $pp) : ?>
          <li class="p3-proof">
            <div class="p3-proof-file">
              <?php if ($pp['proof_token'] && strpos((string) $pp['proof_mime'], 'image/') === 0) : ?>
                <a href="<?= e(url('/f/' . $pp['proof_token'])) ?>" target="_blank" rel="noopener"><img src="<?= e(url('/f/' . $pp['proof_token'])) ?>" alt="Comprobante de <?= e($pp['guest_name'] ?: 'pago') ?>" loading="lazy" width="96" height="96"></a>
              <?php elseif ($pp['proof_token']) : ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/f/' . $pp['proof_token'])) ?>" target="_blank" rel="noopener"><?= icon('file') ?>Abrir PDF</a>
              <?php else : ?>
                <span class="muted">Sin archivo</span>
              <?php endif; ?>
            </div>
            <div class="p3-proof-info">
              <p><strong><?= e($pp['guest_name'] ?: 'Sin cita') ?></strong> · <span class="mono"><?= e(money($pp['amount'])) ?></span></p>
              <p class="muted"><?= e($pp['event_name'] ?: '') ?><?= $pp['starts_at'] ? ' · cita ' . e(Fmt::dateShort((string) $pp['starts_at'], $tz)) : '' ?> · <?= e(PaymentService::methodLabel((string) $pp['method'])) ?><?= $pp['reference'] ? ' · ' . e($pp['reference']) : '' ?></p>
              <?php if ($pp['booking_id']) : ?><p><a class="text-gold" href="<?= e(url('/admin/citas/' . (int) $pp['booking_id'])) ?>">Ver cita</a></p><?php endif; ?>
            </div>
            <div class="p3-proof-actions row gap-2">
              <form method="post" action="<?= e(url('/admin/pagos/' . (int) $pp['id'] . '/verificar')) ?>"><?= csrf_field() ?><input type="hidden" name="volver" value="/admin/pagos"><button class="btn btn-gold btn-sm" type="submit"><?= icon('check') ?>Verificar</button></form>
              <form method="post" action="<?= e(url('/admin/pagos/' . (int) $pp['id'] . '/rechazar')) ?>" data-confirm="¿Rechazar este comprobante?"><?= csrf_field() ?><input type="hidden" name="volver" value="/admin/pagos"><button class="btn btn-ghost btn-sm" type="submit"><?= icon('x') ?>Rechazar</button></form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <form class="card mb-3" method="get" action="<?= e(url('/admin/pagos')) ?>">
    <div class="card-body">
      <div class="form-row p3-filters">
        <div class="field"><label for="pf-q">Buscar</label><input class="input" id="pf-q" name="q" value="<?= e($f['q']) ?>" placeholder="Cliente o referencia"></div>
        <div class="field"><label for="pf-st">Estado</label><select class="select" id="pf-st" name="estado"><option value="">Todos</option><?php foreach ($statuses as $k => $l) : ?><option value="<?= e($k) ?>"<?= sel($f['estado'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="pf-me">Método</label><select class="select" id="pf-me" name="metodo"><option value="">Todos</option><?php foreach ($methods as $k => $l) : ?><option value="<?= e($k) ?>"<?= sel($f['metodo'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="pf-d">Desde</label><input class="input" type="date" id="pf-d" name="desde" value="<?= e($f['desde']) ?>"></div>
        <div class="field"><label for="pf-h">Hasta</label><input class="input" type="date" id="pf-h" name="hasta" value="<?= e($f['hasta']) ?>"></div>
        <div class="field p3-filter-btn"><button class="btn btn-outline" type="submit"><?= icon('filter') ?>Filtrar</button></div>
      </div>
    </div>
  </form>

  <section class="card" aria-labelledby="h-hist">
    <div class="card-head"><h2 id="h-hist" class="serif">Historial de pagos</h2></div>
    <?php if (!$rows) : ?>
      <div class="card-body"><div class="empty"><?= icon('dollar') ?><p class="empty-title">No hay pagos con esos filtros</p><p class="empty-text">Prueba quitando algún filtro o ampliando las fechas.</p></div></div>
    <?php else : ?>
      <div class="table-wrap"><table class="table">
        <caption class="sr-only">Historial de pagos</caption>
        <thead><tr><th scope="col">Fecha</th><th scope="col">Cliente</th><th scope="col" class="hide-sm">Concepto</th><th scope="col">Método</th><th scope="col" class="right">Monto</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r) : ?>
          <tr>
            <td class="nowrap mono"><?= e(Fmt::dateShort((string) $r['created_at'], $tz)) ?></td>
            <th scope="row"><?= e($r['guest_name'] ?: ($r['client_name'] ?: '—')) ?></th>
            <td class="hide-sm"><?= $r['booking_id'] ? '<a class="text-gold" href="' . e(url('/admin/citas/' . (int) $r['booking_id'])) . '">' . e($r['event_name'] ?: 'Cita') . '</a>' : ($r['client_package_id'] ? 'Venta de paquete' : '—') ?></td>
            <td><?= e(PaymentService::methodLabel((string) $r['method'])) ?><?php if ($r['reference']) : ?><div class="muted"><?= e($r['reference']) ?></div><?php endif; ?><?php if ($r['proof_token']) : ?><div><a class="text-gold" href="<?= e(url('/f/' . $r['proof_token'])) ?>" target="_blank" rel="noopener">Comprobante</a></div><?php endif; ?></td>
            <td class="right mono nowrap"><?= $r['status'] === 'refunded' ? '−' : '' ?><?= e(money($r['amount'])) ?></td>
            <td><span class="badge <?= e($stBadge[$r['status']] ?? 'badge-muted') ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
            <td class="right">
              <?php if ($r['booking_id']) : ?><a class="btn btn-ghost btn-sm btn-icon" href="<?= e(url('/admin/pagos/' . (int) $r['booking_id'] . '/recibo')) ?>" target="_blank" rel="noopener" aria-label="Recibo de <?= e($r['guest_name'] ?: 'la cita') ?>"><?= icon('receipt') ?></a><?php endif; ?>
              <?php if ($r['status'] === 'verified' && $r['booking_id']) : ?>
                <details class="p3-refund">
                  <summary class="btn btn-ghost btn-sm">Reembolsar</summary>
                  <form class="p3-refund-form" method="post" action="<?= e(url('/admin/pagos/' . (int) $r['id'] . '/reembolso')) ?>"><?= csrf_field() ?><input type="hidden" name="volver" value="/admin/pagos">
                    <div class="field"><label for="rf-a-<?= (int) $r['id'] ?>">Monto (vacío = todo)</label><input class="input mono" id="rf-a-<?= (int) $r['id'] ?>" name="amount" inputmode="decimal" placeholder="<?= e(number_format((float) $r['amount'], 2, '.', '')) ?>"></div>
                    <div class="field"><label for="rf-n-<?= (int) $r['id'] ?>">Motivo</label><input class="input" id="rf-n-<?= (int) $r['id'] ?>" name="note" maxlength="255"></div>
                    <button class="btn btn-danger btn-sm" type="submit">Confirmar reembolso</button>
                  </form>
                </details>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>
</div>
