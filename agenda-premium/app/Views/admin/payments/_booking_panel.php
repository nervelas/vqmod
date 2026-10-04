<?php
/**
 * Panel de pagos de una cita. Se incluye en el detalle de cita: partial('admin/payments/_booking_panel', ['booking' => $b]).
 * Variables: $booking (fila de bookings; solo se usa su id y se recarga lo necesario).
 */
use App\Core\Auth;
use App\Core\Fmt;
use App\Services\PackageService;
use App\Services\PaymentService;

$bid = (int) $booking['id'];
$b = \App\Core\Db::one('SELECT id, client_id, event_type_id, status, total, deposit_due, paid_amount, payment_status FROM bookings WHERE id = ?', [$bid]) ?: $booking;
$pays = \App\Core\Db::all('SELECT p.*, f.token AS proof_token, f.mime AS proof_mime FROM payments p LEFT JOIN files f ON f.id = p.proof_file_id WHERE p.booking_id = ? ORDER BY p.id', [$bid]);
$tzb = \App\Core\Settings::tz();
$canManage = Auth::can('payments');
$total = (float) $b['total'];
$paid = (float) $b['paid_amount'];
$deposit = (float) $b['deposit_due'];
$balance = max(0.0, $total - $paid);
$stMap = ['none' => ['Sin pagos', 'badge-muted'], 'pending' => ['Por verificar', 'badge-warn'], 'partial' => ['Pago parcial', 'badge-gold'], 'paid' => ['Pagada', 'badge-ok'], 'refunded' => ['Reembolsada', 'badge-err']];
$st = $stMap[$b['payment_status']] ?? ['—', 'badge-muted'];
$stPay = ['pending' => ['Por verificar', 'badge-warn'], 'verified' => ['Verificado', 'badge-ok'], 'rejected' => ['Rechazado', 'badge-err'], 'refunded' => ['Reembolso', 'badge-muted']];
$self = '/admin/citas/' . $bid;
$packages = [];
if ($canManage && $b['client_id'] !== null) {
    foreach (PackageService::balance((int) $b['client_id']) as $cp) {
        if ($cp['state'] === 'usable' && ($cp['event_type_id'] === null || (int) $cp['event_type_id'] === (int) $b['event_type_id'])) {
            $packages[] = $cp;
        }
    }
}
$open = !in_array($b['status'], ['cancelled', 'rejected'], true);
?>
<section class="card p3-paypanel" aria-labelledby="pp-title-<?= $bid ?>" id="pagos-cita">
  <div class="card-head row row-between row-wrap">
    <h2 id="pp-title-<?= $bid ?>" class="serif">Pagos</h2>
    <span class="badge <?= e($st[1]) ?>"><?= e($st[0]) ?></span>
  </div>
  <div class="card-body stack">
    <dl class="p3-pay-sum">
      <div><dt>Total</dt><dd class="mono"><?= e(money($total)) ?></dd></div>
      <div><dt>Depósito</dt><dd class="mono"><?= $deposit > 0 ? e(money($deposit)) : '—' ?></dd></div>
      <div><dt>Pagado</dt><dd class="mono text-ok"><?= e(money($paid)) ?></dd></div>
      <div><dt>Saldo</dt><dd class="mono<?= $balance > 0 ? ' text-gold' : '' ?>"><?= e(money($balance)) ?></dd></div>
    </dl>

    <?php if (!$pays) : ?>
      <p class="muted">Todavía no hay pagos registrados para esta cita.</p>
    <?php else : ?>
      <div class="table-wrap"><table class="table table-sm">
        <caption class="sr-only">Pagos de la cita</caption>
        <thead><tr><th scope="col">Fecha</th><th scope="col">Método</th><th scope="col" class="right">Monto</th><th scope="col">Estado</th><?php if ($canManage) : ?><th scope="col"><span class="sr-only">Acciones</span></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($pays as $p) : $ps = $stPay[$p['status']] ?? ['—', 'badge-muted']; ?>
          <tr>
            <td class="nowrap mono"><?= e(Fmt::dateShort((string) $p['created_at'], $tzb)) ?></td>
            <td><?= e(PaymentService::methodLabel((string) $p['method'])) ?><?php if ($p['reference']) : ?><div class="muted"><?= e($p['reference']) ?></div><?php endif; ?><?php if ($p['proof_token']) : ?><div class="nowrap"><a class="text-gold" href="<?= e(url('/f/' . $p['proof_token'])) ?>" target="_blank" rel="noopener"><?= icon('paperclip') ?>Ver comprobante</a></div><?php endif; ?></td>
            <td class="right mono nowrap"><?= $p['status'] === 'refunded' ? '−' : '' ?><?= e(money($p['amount'])) ?></td>
            <td><span class="badge <?= e($ps[1]) ?>"><?= e($ps[0]) ?></span></td>
            <?php if ($canManage) : ?>
              <td class="right nowrap">
                <?php if ($p['status'] === 'pending') : ?>
                  <form class="inline" method="post" action="<?= e(url('/admin/pagos/' . (int) $p['id'] . '/verificar')) ?>"><?= csrf_field() ?><input type="hidden" name="volver" value="<?= e($self) ?>"><button class="btn btn-outline btn-sm" type="submit"><?= icon('check') ?>Verificar</button></form>
                  <form class="inline" method="post" action="<?= e(url('/admin/pagos/' . (int) $p['id'] . '/rechazar')) ?>" data-confirm="¿Rechazar este pago?"><?= csrf_field() ?><input type="hidden" name="volver" value="<?= e($self) ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= icon('x') ?>Rechazar</button></form>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>

    <?php if ($pays) : ?><p><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/pagos/' . $bid . '/recibo')) ?>" target="_blank" rel="noopener"><?= icon('receipt') ?>Ver recibo imprimible</a></p><?php endif; ?>

    <?php if ($canManage && $open) : ?>
      <form class="fieldset p3-payform" method="post" action="<?= e(url('/admin/citas/' . $bid . '/pagos')) ?>" enctype="multipart/form-data" data-payform>
        <?= csrf_field() ?><input type="hidden" name="volver" value="<?= e($self) ?>">
        <p class="p3-form-title serif">Registrar pago</p>
        <div class="form-row">
          <div class="field"><label for="pf-method-<?= $bid ?>">Método</label>
            <select class="select" id="pf-method-<?= $bid ?>" name="method" data-pay-method>
              <option value="cash">Efectivo</option>
              <option value="transfer">Transferencia o depósito</option>
              <option value="card_onsite">Tarjeta en el local</option>
              <option value="link">Enlace de pago externo</option>
              <?php if ($packages) : ?><option value="package">Paquete de sesiones</option><?php endif; ?>
              <option value="gift_card">Certificado de regalo</option>
              <option value="other">Otro</option>
            </select></div>
          <div class="field"><label for="pf-amount-<?= $bid ?>">Monto (Q)</label><input class="input mono" id="pf-amount-<?= $bid ?>" name="amount" inputmode="decimal" value="<?= $balance > 0 ? e(number_format($balance, 2, '.', '')) : '' ?>"></div>
        </div>
        <div class="field" data-pay-show="transfer link card_onsite other"><label for="pf-ref-<?= $bid ?>">Referencia o número de boleta</label><input class="input" id="pf-ref-<?= $bid ?>" name="reference" maxlength="190"></div>
        <div class="field" data-pay-show="transfer link" hidden><label for="pf-proof-<?= $bid ?>">Comprobante (imagen o PDF)</label><input class="input" id="pf-proof-<?= $bid ?>" type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf"></div>
        <?php if ($packages) : ?>
          <div class="field" data-pay-show="package" hidden><label for="pf-pkg-<?= $bid ?>">Paquete del cliente</label>
            <select class="select" id="pf-pkg-<?= $bid ?>" name="client_package_id"><?php foreach ($packages as $cp) : ?><option value="<?= (int) $cp['id'] ?>"><?= e($cp['name'] . ' · ' . $cp['remaining'] . ' sesiones') ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <div class="field" data-pay-show="gift_card" hidden><label for="pf-gift-<?= $bid ?>">Código del certificado</label><input class="input mono" id="pf-gift-<?= $bid ?>" name="gift_code" maxlength="40" placeholder="AB12-CD34-EF56"></div>
        <div class="field"><label for="pf-note-<?= $bid ?>">Nota (opcional)</label><input class="input" id="pf-note-<?= $bid ?>" name="note" maxlength="255"></div>
        <label class="check" data-pay-show="transfer link"><input type="checkbox" name="confirmed" value="1"><span>Ya confirmé que el dinero llegó</span></label>
        <div class="form-actions"><button class="btn btn-gold" type="submit"><?= icon('dollar') ?>Registrar pago</button></div>
      </form>
    <?php endif; ?>
  </div>
</section>
