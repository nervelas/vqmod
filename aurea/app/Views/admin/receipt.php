<?php $paid = 0; foreach ($payments as $p) { $paid += (float)$p['amount']; } ?>
<div class="no-print" style="text-align:right;margin-bottom:14px"><button class="btn btn-gold btn-sm" type="button" data-print><?= e(__('Imprimir / guardar como PDF')) ?></button></div>
<div style="border:1px solid #d9ccae;padding:34px">
  <div style="display:flex;justify-content:space-between;align-items:start;border-bottom:2px solid #B8924A;padding-bottom:16px;margin-bottom:22px">
    <div><div class="eyebrow">Recibo</div><h1 style="margin:4px 0 0;font-size:1.8rem"><?= e((string)setting('business_name')) ?></h1><div class="hint"><?= e((string)setting('business_address')) ?><br><?= e((string)setting('business_phone')) ?> <?= e((string)setting('business_email')) ?></div></div>
    <div style="text-align:right"><b class="num" style="font-size:1.2rem">No. <?= str_pad((string)$a['id'], 6, '0', STR_PAD_LEFT) ?></b><br><span class="hint"><?= e(fdate(date('Y-m-d'))) ?></span></div>
  </div>
  <dl class="kv">
    <dt><?= e(term('client')) ?></dt><dd><?= e($a['client_name']) ?></dd>
    <dt>NIT</dt><dd><?= e($a['client_nit'] !== '' ? $a['client_nit'] : 'CF') ?></dd>
    <dt><?= e(__('Servicio')) ?></dt><dd><?= e($a['service_name']) ?> · <?= e($a['prof_name']) ?></dd>
    <dt><?= e(__('Fecha')) ?></dt><dd><?= e(fdatetime($a['start_at'])) ?></dd>
  </dl>
  <div class="tbl-wrap" style="margin-top:22px"><table class="tbl"><thead><tr><th><?= e(__('Fecha de pago')) ?></th><th><?= e(__('Método')) ?></th><th><?= e(__('Referencia')) ?></th><th class="r"><?= e(__('Monto')) ?></th></tr></thead><tbody>
    <?php foreach ($payments as $p): ?><tr><td><?= e(fdatetime($p['paid_at'])) ?></td><td><?= e($methods[$p['method']] ?? $p['method']) ?></td><td><?= e($p['reference']) ?></td><td class="r"><?= e(money($p['amount'])) ?></td></tr><?php endforeach; ?>
    <?php if (!$payments): ?><tr><td colspan="4" class="hint"><?= e(__('Sin pagos confirmados.')) ?></td></tr><?php endif; ?>
  </tbody></table></div>
  <div style="display:flex;justify-content:flex-end;gap:30px;margin-top:18px;font-size:1.05rem"><span><?= e(__('Total del servicio')) ?>: <b><?= e(money($a['total'])) ?></b></span><span><?= e(__('Pagado')) ?>: <b><?= e(money($paid)) ?></b></span><span><?= e(__('Saldo')) ?>: <b><?= e(money(max(0, (float)$a['total'] - $paid))) ?></b></span></div>
  <p class="hint" style="margin-top:28px"><?= e(__('Este recibo es un comprobante interno de pago y no sustituye una factura.')) ?></p>
</div>
