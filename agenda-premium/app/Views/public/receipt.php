<?php
/** @var array $biz @var array $b @var array $d @var string $tz @var array $payments @var string $number @var string $issued */
$event = (array) ($d['event'] ?? []);
$host = (array) ($d['host'] ?? []);
$paid = (float) $b['paid_amount'];
$pending = max(0.0, (float) $b['total'] - $paid);
?>
<main id="main" class="rc">
  <div class="rc-bar no-print"><a class="btn btn-ghost btn-sm" href="<?= e(url('/reserva/' . $b['token'])) ?>"><?= icon('arrow-left') ?> Volver a mi cita</a><button type="button" class="btn btn-gold btn-sm" data-print><?= icon('file') ?> Imprimir o guardar PDF</button></div>
  <article class="rc-sheet">
    <header class="rc-head">
      <div><p class="serif rc-biz"><?= e($biz['name']) ?></p>
        <?php if ($biz['address'] !== '') : ?><p class="muted"><?= e($biz['address']) ?></p><?php endif; ?>
        <p class="muted"><?= e(trim(($biz['phone_display'] ?: $biz['wa_display']) . ($biz['email'] !== '' ? ' · ' . $biz['email'] : ''), ' ·')) ?></p></div>
      <div class="rc-num"><p class="eyebrow">Recibo</p><p class="mono rc-no"><?= e($number) ?></p><p class="muted">Emitido el <?= e($issued) ?></p></div>
    </header>
    <section>
      <h1 class="serif rc-title">Comprobante de cita</h1>
      <dl class="bk-dl">
        <div><dt>A nombre de</dt><dd><?= e($b['guest_name']) ?></dd></div>
        <div><dt>Servicio</dt><dd><?= e($event['name'] ?? '') ?></dd></div>
        <div><dt>Fecha y hora</dt><dd><?= e(\App\Core\Fmt::dateTime((string) $b['starts_at'], $tz)) ?></dd></div>
        <div><dt>Duración</dt><dd><?= e(\App\Core\Fmt::duration((int) $b['duration'])) ?></dd></div>
        <?php if (!empty($host['name'])) : ?><div><dt>Atiende</dt><dd><?= e($host['name']) ?></dd></div><?php endif; ?>
        <div><dt>Estado</dt><dd><?= e(['pending' => 'Pendiente de aprobación', 'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada', 'completed' => 'Completada', 'no_show' => 'No asistió', 'rejected' => 'No aprobada'][$b['status']] ?? $b['status']) ?></dd></div>
      </dl>
    </section>
    <section>
      <table class="table rc-table">
        <thead><tr><th scope="col">Concepto</th><th scope="col" class="right">Importe</th></tr></thead>
        <tbody>
          <tr><td>Precio del servicio</td><td class="right mono"><?= e(\App\Core\Fmt::money((float) $b['price'])) ?></td></tr>
          <?php if ((float) $b['discount'] > 0) : ?><tr><td>Descuento</td><td class="right mono">−<?= e(\App\Core\Fmt::money((float) $b['discount'])) ?></td></tr><?php endif; ?>
          <tr class="rc-total"><th scope="row">Total</th><td class="right mono"><?= e(\App\Core\Fmt::money((float) $b['total'])) ?></td></tr>
          <?php foreach ($payments as $p) : ?><tr><td>Pago del <?= e($p['_date']) ?> · <?= e($p['_method']) ?><?= $p['reference'] ? ' · ref. ' . e($p['reference']) : '' ?></td><td class="right mono"><?= e(\App\Core\Fmt::money((float) $p['amount'])) ?></td></tr><?php endforeach; ?>
          <tr class="rc-total"><th scope="row">Saldo pendiente</th><td class="right mono"><?= e(\App\Core\Fmt::money($pending)) ?></td></tr>
        </tbody>
      </table>
      <?php if ((float) $b['total'] <= 0) : ?><p class="muted">Esta cita no tiene costo.</p><?php endif; ?>
    </section>
    <footer class="rc-foot muted"><p>Este documento es un comprobante informativo de tu cita. Gracias por confiar en <?= e($biz['name']) ?>.</p></footer>
  </article>
</main>
