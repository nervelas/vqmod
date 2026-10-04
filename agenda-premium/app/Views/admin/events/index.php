<?php
/** @var array $events */
/** @var ?array $link */
use App\Controllers\Admin\A2Controller;
use App\Core\Fmt;
use App\Core\Tz;

?>
<div class="page p2-page">
  <header class="page-head">
    <div>
      <h1 class="page-title">Tipos de evento</h1>
      <p class="page-sub">Lo que tus clientes pueden reservar. Arrastra las tarjetas para cambiar el orden en que aparecen en tu página.</p>
    </div>
    <div class="page-actions">
      <a class="btn btn-gold" href="<?= e(url('/admin/eventos/nuevo')) ?>"><?= icon('plus') ?> Nuevo evento</a>
    </div>
  </header>

  <?php if (!empty($link)) : ?>
    <section class="card card-gold p2-linkcard" aria-label="Enlace de un solo uso">
      <div class="card-body stack">
        <div>
          <h2 class="p2-h3">Tu enlace de un solo uso está listo</h2>
          <p class="muted">Basado en «<?= e($link['name']) ?>». Deja de funcionar apenas alguien reserve<?= !empty($link['expires_at']) ? ' o el ' . e(Fmt::dateShort((string) $link['expires_at'], \App\Core\Settings::tz())) : '' ?>.</p>
        </div>
        <div class="input-group">
          <input class="input mono" type="text" readonly value="<?= e($link['url']) ?>" id="single-link" aria-label="Enlace de un solo uso">
          <button class="btn btn-gold" type="button" data-copy="#single-link"><?= icon('copy') ?> Copiar enlace</button>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!$events) : ?>
    <div class="empty p2-empty">
      <?= icon('layers') ?>
      <h2 class="empty-title">Aún no hay tipos de evento</h2>
      <p class="empty-text">Crea el primero, por ejemplo una consulta de 30 minutos, y comparte el enlace con tus clientes.</p>
      <a class="btn btn-gold" href="<?= e(url('/admin/eventos/nuevo')) ?>"><?= icon('plus') ?> Crear mi primer evento</a>
    </div>
  <?php else : ?>
    <ul class="p2-events" data-sortable data-url="<?= e(url('/admin/eventos/orden')) ?>" aria-label="Lista de tipos de evento">
      <?php foreach ($events as $ev) :
          $paused = (int) $ev['active'] === 0;
          $hasBookings = (int) $ev['bookings_count'] > 0; ?>
        <li class="p2-event card<?= $paused ? ' is-paused' : '' ?>" data-id="<?= (int) $ev['id'] ?>" <?= vars(['--ev' => (string) $ev['color']]) ?>>
          <div class="card-body stack">
            <div class="row row-between">
              <div class="row gap-2 p2-event-title">
                <span class="p2-handle" data-handle title="Arrastra para reordenar" aria-hidden="true"><?= icon('drag') ?></span>
                <h2 class="p2-h3"><?= e($ev['name']) ?></h2>
              </div>
              <div class="row row-wrap gap-1">
                <?php if ($paused) : ?><span class="badge badge-muted">Pausado</span><?php else : ?><span class="badge badge-ok">Activo</span><?php endif; ?>
                <?php if ($ev['visibility'] === 'secret') : ?><span class="badge badge-gold">Secreto</span><?php endif; ?>
                <?php if ((int) $ev['single_use'] === 1) : ?><span class="badge badge-gold">Un solo uso</span><?php endif; ?>
                <?php if ($ev['expired']) : ?><span class="badge badge-err">Caducado</span><?php endif; ?>
              </div>
            </div>
            <ul class="p2-meta" aria-label="Resumen">
              <li><?= icon('layers') ?><span><?= e(A2Controller::KINDS[$ev['kind']] ?? $ev['kind']) ?></span></li>
              <li><?= icon('clock') ?><span class="mono"><?= e(implode(' · ', array_map(static fn (int $d): string => Fmt::duration($d), $ev['durations']))) ?></span></li>
              <li><?= icon($ev['mode'] === 'phone' ? 'phone' : ($ev['mode'] === 'in_person' ? 'map-pin' : ($ev['mode'] === 'home' ? 'home' : 'video'))) ?><span><?= e(A2Controller::MODES[$ev['mode']] ?? $ev['mode']) ?></span></li>
              <li><?= icon('dollar') ?><span class="mono"><?= (float) $ev['price'] > 0 ? e(money($ev['price'])) : 'Gratis' ?></span></li>
              <li><?= icon('users') ?><span><?= (int) $ev['hosts_count'] ?> <?= (int) $ev['hosts_count'] === 1 ? 'anfitrión' : 'anfitriones' ?></span></li>
              <li><?= icon('calendar') ?><span><?= (int) $ev['bookings_count'] ?> <?= (int) $ev['bookings_count'] === 1 ? 'cita' : 'citas' ?></span></li>
            </ul>
            <div class="input-group">
              <input class="input mono" type="text" readonly value="<?= e($ev['url']) ?>" id="link-<?= (int) $ev['id'] ?>" aria-label="Enlace público de <?= e($ev['name']) ?>">
              <button class="btn btn-outline btn-icon" type="button" data-copy="#link-<?= (int) $ev['id'] ?>" aria-label="Copiar enlace de <?= e($ev['name']) ?>"><?= icon('copy') ?></button>
            </div>
          </div>
          <div class="card-foot p2-actions">
            <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/eventos/' . (int) $ev['id'] . '/editar')) ?>"><?= icon('edit') ?> Editar</a>
            <form method="post" action="<?= e(url('/admin/eventos/' . (int) $ev['id'] . '/duplicar')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('copy') ?> Duplicar</button></form>
            <button class="btn btn-ghost btn-sm" type="button" data-single-use="<?= e(url('/admin/eventos/' . (int) $ev['id'] . '/enlace-unico')) ?>" data-name="<?= e($ev['name']) ?>"><?= icon('link') ?> Enlace de un solo uso</button>
            <form method="post" action="<?= e(url('/admin/eventos/' . (int) $ev['id'] . '/pausar')) ?>" class="inline"><?= csrf_field() ?>
              <button class="btn btn-ghost btn-sm" type="submit"><?= icon($paused ? 'play' : 'pause') ?> <?= $paused ? 'Activar' : 'Pausar' ?></button>
            </form>
            <?php if ($hasBookings) : ?>
              <span class="muted p2-note" title="Tiene citas: solo se puede pausar"><?= icon('lock') ?> Con citas</span>
            <?php else : ?>
              <form method="post" action="<?= e(url('/admin/eventos/' . (int) $ev['id'] . '/eliminar')) ?>" class="inline" data-confirm="¿Eliminar el evento «<?= e($ev['name']) ?>»? Esta acción no se puede deshacer.">
                <?= csrf_field() ?><button class="btn btn-ghost btn-sm text-err" type="submit"><?= icon('trash') ?> Eliminar</button>
              </form>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <dialog class="modal" id="single-use-modal" aria-labelledby="single-use-title">
    <form method="post" action="" id="single-use-form" class="stack">
      <?= csrf_field() ?>
      <div class="modal-head"><h2 class="p2-h3" id="single-use-title">Enlace de un solo uso</h2></div>
      <div class="modal-body stack">
        <p class="muted">Creamos una copia secreta de <strong id="single-use-name">este evento</strong> que solo permite una reserva. Ideal para clientes a quienes quieres dar un horario exclusivo.</p>
        <div class="field">
          <label for="single-use-date">Fecha límite (opcional)</label>
          <input class="input" type="date" name="expires" id="single-use-date">
          <p class="hint">Si nadie lo usa antes de esa fecha, el enlace deja de funcionar. Zona: <?= e(Tz::safe(\App\Core\Settings::tz())) ?>.</p>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-ghost" type="button" data-modal-close data-p2-close>Cancelar</button>
        <button class="btn btn-gold" type="submit">Crear enlace</button>
      </div>
    </form>
  </dialog>
</div>
