<?php
use App\Core\Fmt;

$tz = \App\Core\Settings::tz();
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">Certificados de regalo</h1><p class="page-sub">Crea certificados con código único, imprímelos con un diseño elegante y consulta su saldo cuando quieras.</p></div>
  </div>

  <?php if (!empty($justCreated)) : ?>
    <div class="alert alert-ok" role="status">El certificado está listo. <a class="text-gold" href="<?= e(url('/admin/certificados/' . (int) $justCreated . '/imprimir')) ?>" target="_blank" rel="noopener">Abrir versión imprimible</a></div>
  <?php endif; ?>

  <div class="grid cols-2 mb-4">
    <section class="card" aria-labelledby="h-nuevo">
      <div class="card-head"><h2 id="h-nuevo" class="serif">Nuevo certificado</h2></div>
      <form method="post" action="<?= e(url('/admin/certificados/crear')) ?>" class="card-body stack">
        <?= csrf_field() ?>
        <div class="form-row">
          <div class="field"><label for="gc-amount">Monto (Q)</label><input class="input mono" id="gc-amount" name="amount" inputmode="decimal" required placeholder="500.00"></div>
          <div class="field"><label for="gc-exp">Vence el</label><input class="input" id="gc-exp" type="date" name="expires"><p class="hint">Déjalo vacío si no vence.</p></div>
        </div>
        <div class="form-row">
          <div class="field"><label for="gc-buyer">De parte de</label><input class="input" id="gc-buyer" name="buyer_name" maxlength="160"></div>
          <div class="field"><label for="gc-rec">Para</label><input class="input" id="gc-rec" name="recipient_name" maxlength="160"></div>
        </div>
        <div class="field"><label for="gc-msg">Mensaje</label><input class="input" id="gc-msg" name="message" maxlength="255" placeholder="Feliz cumpleaños, te mereces un buen rato."></div>
        <div class="form-actions"><button class="btn btn-gold" type="submit"><?= icon('gift') ?>Crear certificado</button></div>
      </form>
    </section>

    <section class="card" aria-labelledby="h-saldo">
      <div class="card-head"><h2 id="h-saldo" class="serif">Consultar saldo</h2></div>
      <div class="card-body stack">
        <form method="get" action="<?= e(url('/admin/certificados')) ?>" class="stack">
          <div class="field"><label for="gc-q">Código del certificado</label>
            <div class="input-group"><input class="input mono" id="gc-q" name="consultar" value="<?= e($code) ?>" maxlength="40" placeholder="AB12-CD34-EF56" autocapitalize="characters" required><button class="btn btn-outline" type="submit"><?= icon('search') ?>Consultar</button></div></div>
        </form>
        <?php if ($lookupDone && !$lookup) : ?>
          <div class="alert alert-warn" role="status">No encontramos ningún certificado con ese código. Revisa que esté bien escrito.</div>
        <?php elseif ($lookup) :
            $exp = $lookup['expires_at'] && $lookup['expires_at'] < $now; ?>
          <div class="p3-lookup" role="status">
            <p class="muted">Saldo disponible</p>
            <p class="stat-value serif"><?= e(money($lookup['balance'])) ?></p>
            <p class="muted">De <?= e(money($lookup['initial_amount'])) ?> ·
              <?= !(int) $lookup['active'] ? 'desactivado' : ($exp ? 'vencido' : ($lookup['expires_at'] ? 'vence el ' . e(Fmt::dateShort((string) $lookup['expires_at'], $tz)) : 'sin vencimiento')) ?></p>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <section class="card" aria-labelledby="h-lista">
    <div class="card-head"><h2 id="h-lista" class="serif">Certificados emitidos</h2></div>
    <?php if (!$cards) : ?>
      <div class="card-body"><div class="empty"><?= icon('gift') ?><p class="empty-title">Aún no has emitido certificados</p><p class="empty-text">Son un regalo perfecto: el destinatario canjea el código al reservar y se descuenta de su saldo.</p></div></div>
    <?php else : ?>
      <div class="table-wrap"><table class="table">
        <caption class="sr-only">Certificados de regalo emitidos</caption>
        <thead><tr><th scope="col">Código</th><th scope="col" class="right">Monto</th><th scope="col" class="right">Saldo</th><th scope="col" class="hide-sm">Para</th><th scope="col" class="hide-sm">Vence</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody>
        <?php foreach ($cards as $g) :
            $expired = $g['expires_at'] && $g['expires_at'] < $now;
            $st = !(int) $g['active'] ? ['Inactivo', 'badge-muted'] : ($expired ? ['Vencido', 'badge-err'] : ((float) $g['balance'] <= 0 ? ['Canjeado', 'badge-warn'] : ['Vigente', 'badge-ok'])); ?>
          <tr>
            <th scope="row" class="mono"><?= e($g['code']) ?></th>
            <td class="right mono nowrap"><?= e(money($g['initial_amount'])) ?></td>
            <td class="right mono nowrap"><?= e(money($g['balance'])) ?></td>
            <td class="hide-sm"><?= e($g['recipient_name'] ?: '—') ?></td>
            <td class="hide-sm nowrap"><?= $g['expires_at'] ? e(Fmt::dateShort((string) $g['expires_at'], $tz)) : 'Sin vencimiento' ?></td>
            <td><span class="badge <?= e($st[1]) ?>"><?= e($st[0]) ?></span></td>
            <td class="right nowrap">
              <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/certificados/' . (int) $g['id'] . '/imprimir')) ?>" target="_blank" rel="noopener"><?= icon('download') ?>Imprimir</a>
              <form class="inline" method="post" action="<?= e(url('/admin/certificados/' . (int) $g['id'] . '/estado')) ?>"<?= (int) $g['active'] ? ' data-confirm="¿Desactivar este certificado? Ya no se podrá canjear."' : '' ?>><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= (int) $g['active'] ? 'Desactivar' : 'Activar' ?></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>
</div>
