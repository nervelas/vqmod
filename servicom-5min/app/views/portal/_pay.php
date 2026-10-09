<?php
/**
 * Bloque de pago reutilizable. Variables: $cfg (banco[banco,cuenta,titular,tipo], precio_*), $total (opcional), $plan, $tarjeta.
 */
require_once __DIR__ . '/_icons.php';
$bk = is_array($cfg['banco'] ?? null) ? $cfg['banco'] : [];
$cuenta = (string)($bk['cuenta'] ?? ($bk['numero'] ?? ''));
if (!isset($total) || !is_numeric($total)) {
    $total = (($plan ?? 'info') === 'tienda' ? (float)($cfg['precio_tienda'] ?? 1750) : (float)($cfg['precio_info'] ?? 1250))
        + (!empty($tarjeta) ? (float)($cfg['precio_tarjeta'] ?? 750) : 0);
}
?>
<div class="paybox" data-pay>
  <div data-pay-form>
    <p class="help">Total a pagar por 1 año</p>
    <p class="total gold-text" data-total><?= e(money_q($total)) ?></p>
    <p class="help">Paga por <b>transferencia o depósito</b> a esta cuenta y sube tu comprobante.</p>
    <dl class="bank">
      <div><dt>Banco</dt><dd><?= e($bk['banco'] ?? '') ?></dd></div>
      <div><dt>Cuenta</dt><dd><?= e($cuenta) ?></dd></div>
      <div><dt>A nombre de</dt><dd><?= e($bk['titular'] ?? '') ?></dd></div>
      <div><dt>Tipo</dt><dd><?= e($bk['tipo'] ?? '') ?></dd></div>
    </dl>
<?php if ($cuenta !== ''): ?>
    <button type="button" class="btn btn--ghost btn--sm" data-copy-acc="<?= e($cuenta) ?>">Copiar cuenta</button>
<?php endif; ?>
    <div class="field payf">
      <label for="pay-nit">Nombre o NIT para tu recibo <span class="req">*</span></label>
      <input class="inp" id="pay-nit" name="nombre_nit" maxlength="120" autocomplete="name" placeholder="Ej. Juan Pérez o 1234567-8">
    </div>
    <div class="field payf">
      <span class="lbl" id="pay-lbl">Comprobante de pago <span class="req">*</span></span>
      <label class="drop" for="pay-file"><?= ico('upload') ?><span>Toca para subir tu foto o PDF</span><small>JPG, PNG o PDF · máx. 5 MB</small>
        <input class="sr" type="file" id="pay-file" accept="image/jpeg,image/png,application/pdf" aria-describedby="pay-fi"></label>
      <p class="help" id="pay-fi" data-pay-file aria-live="polite"></p>
    </div>
    <p class="err" role="alert" data-pay-err></p>
    <button type="button" class="btn btn--gold btn--block" data-pay-submit disabled>Enviar comprobante</button>
    <p class="help" role="status" aria-live="polite" data-pay-status></p>
  </div>
  <div data-pay-done hidden class="paydone">
    <span class="ico-big"><?= ico('check') ?></span>
    <h2 class="serif">¡Recibimos tu comprobante!</h2>
    <p>Lo estamos revisando. En cuanto lo confirmemos publicamos tu web y activamos tu dominio .com y tus correos (máximo 2 días hábiles). Te avisaremos por WhatsApp o correo.</p>
  </div>
</div>
