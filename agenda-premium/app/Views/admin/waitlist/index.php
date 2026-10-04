<?php
use App\Core\Fmt;
use App\Core\Tz;

$tzb = \App\Core\Settings::tz();
$badge = ['waiting' => 'badge-gold', 'offered' => 'badge-warn', 'booked' => 'badge-ok', 'expired' => 'badge-muted', 'cancelled' => 'badge-muted'];
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">Lista de espera</h1><p class="page-sub">Personas que quieren un horario cuando se libere. Al cancelarse una cita, el sistema ofrece el espacio al siguiente en la fila.</p></div>
  </div>

  <div class="grid cols-3 mb-3">
    <div class="stat"><div class="stat-label">En espera</div><div class="stat-value serif"><?= (int) ($counts['waiting'] ?? 0) ?></div></div>
    <div class="stat"><div class="stat-label">Con oferta vigente</div><div class="stat-value serif"><?= count($offers) ?></div></div>
    <div class="stat"><div class="stat-label">Reservaron desde la lista</div><div class="stat-value serif"><?= (int) ($counts['booked'] ?? 0) ?></div></div>
  </div>

  <?php if ($offers) : ?>
    <section class="card card-gold mb-4" aria-labelledby="h-of">
      <div class="card-head"><h2 id="h-of" class="serif">Ofertas vigentes</h2></div>
      <ul class="p3-list">
        <?php foreach ($offers as $o) : $left = max(0, (int) ceil((Tz::ts((string) $o['offer_expires_at']) - Tz::ts($now)) / 60)); ?>
          <li class="p3-list-item">
            <div><strong><?= e($o['name']) ?></strong> <span class="muted">· <?= e($o['event_name']) ?></span>
              <p class="muted"><?= e(Fmt::dateTime((string) $o['offer_starts_at'], $tzb)) ?><?= $o['offer_host_name'] ? ' con ' . e($o['offer_host_name']) : '' ?></p></div>
            <span class="badge badge-warn">Vence en <?= (int) $left ?> min</span>
            <form method="post" action="<?= e(url('/admin/espera/' . (int) $o['id'] . '/cancelar')) ?>" data-confirm="¿Retirar esta oferta? El horario pasará a la siguiente persona."><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Retirar oferta</button></form>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <form class="card mb-3" method="get" action="<?= e(url('/admin/espera')) ?>">
    <div class="card-body"><div class="form-row p3-filters">
      <div class="field"><label for="w-ev">Servicio</label><select class="select" id="w-ev" name="evento"><option value="0">Todos</option><?php foreach ($events as $ev) : ?><option value="<?= (int) $ev['id'] ?>"<?= sel($eventId, $ev['id']) ?>><?= e($ev['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="w-st">Estado</label><select class="select" id="w-st" name="estado"><option value="">Activas y recientes</option><?php foreach ($statuses as $k => $l) : ?><option value="<?= e($k) ?>"<?= sel($status, $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field p3-filter-btn"><button class="btn btn-outline" type="submit"><?= icon('filter') ?>Filtrar</button></div>
    </div></div>
  </form>

  <section class="card" aria-labelledby="h-w">
    <div class="card-head"><h2 id="h-w" class="serif">Entradas</h2></div>
    <?php if (!$rows) : ?>
      <div class="card-body"><div class="empty"><?= icon('clock') ?><p class="empty-title">Nadie en la lista de espera</p><p class="empty-text">Cuando un horario esté lleno, tus clientes podrán anotarse desde la página de reservas y aparecerán aquí.</p></div></div>
    <?php else : ?>
      <div class="table-wrap"><table class="table">
        <caption class="sr-only">Entradas de la lista de espera</caption>
        <thead><tr><th scope="col">Persona</th><th scope="col">Servicio</th><th scope="col" class="hide-sm">Prefiere</th><th scope="col" class="hide-sm">Se anotó</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r) : ?>
          <tr>
            <th scope="row"><?= e($r['name']) ?><div class="muted"><?= e(trim(($r['email'] ?: '') . ($r['email'] && $r['phone'] ? ' · ' : '') . ($r['phone'] ? \App\Core\Str::phoneDisplay($r['phone']) : ''))) ?></div></th>
            <td><?= e($r['event_name']) ?><div class="muted"><?= e(Fmt::duration((int) $r['duration'])) ?></div></td>
            <td class="hide-sm"><?= $r['want_date'] ? e(Fmt::dateShort($r['want_date'] . ' 12:00:00', 'UTC')) : 'Cualquier fecha' ?><?= $r['host_name'] ? '<div class="muted">' . e($r['host_name']) . '</div>' : '' ?></td>
            <td class="hide-sm nowrap"><?= e(Fmt::dateShort((string) $r['created_at'], $tzb)) ?></td>
            <td><span class="badge <?= e($badge[$r['status']] ?? 'badge-muted') ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
            <td class="right nowrap">
              <?php if ($r['status'] === 'waiting') : ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/espera/' . (int) $r['id'] . '/ofrecer')) ?>"><?= icon('clock') ?>Ofrecer horario</a><?php endif; ?>
              <?php if (in_array($r['status'], ['waiting', 'offered'], true)) : ?>
                <form class="inline" method="post" action="<?= e(url('/admin/espera/' . (int) $r['id'] . '/cancelar')) ?>" data-confirm="¿Cancelar la entrada de <?= e($r['name']) ?>?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-icon" type="submit" aria-label="Cancelar la entrada de <?= e($r['name']) ?>"><?= icon('x') ?></button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>
</div>
