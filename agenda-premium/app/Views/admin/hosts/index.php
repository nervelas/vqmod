<?php
/** @var array $hosts */
use App\Core\Str;

?>
<div class="page p2-page">
  <header class="page-head">
    <div>
      <h1 class="page-title">Anfitriones</h1>
      <p class="page-sub">Las personas que atienden las citas. Cada una tiene su zona horaria, su horario y su propio calendario.</p>
    </div>
    <div class="page-actions">
      <a class="btn btn-gold" href="<?= e(url('/admin/anfitriones/nuevo')) ?>"><?= icon('plus') ?> Nuevo anfitrión</a>
    </div>
  </header>

  <?php if (!$hosts) : ?>
    <div class="empty p2-empty">
      <?= icon('user') ?>
      <h2 class="empty-title">Todavía no hay anfitriones</h2>
      <p class="empty-text">Agrega a la primera persona que atenderá citas para poder activar tus eventos.</p>
      <a class="btn btn-gold" href="<?= e(url('/admin/anfitriones/nuevo')) ?>"><?= icon('plus') ?> Agregar anfitrión</a>
    </div>
  <?php else : ?>
    <ul class="p2-people" data-sortable data-url="<?= e(url('/admin/anfitriones/orden')) ?>" aria-label="Lista de anfitriones">
      <?php foreach ($hosts as $h) : ?>
        <li class="card p2-person<?= (int) $h['active'] === 0 ? ' is-paused' : '' ?>" data-id="<?= (int) $h['id'] ?>" <?= vars(['--ev' => (string) $h['color']]) ?>>
          <div class="card-body">
            <div class="p2-person-head">
              <span class="p2-handle" data-handle title="Arrastra para reordenar" aria-hidden="true"><?= icon('drag') ?></span>
              <?php if ($h['photo'] !== '') : ?>
                <img class="p2-photo" src="<?= e($h['photo']) ?>" alt="" width="56" height="56" loading="lazy">
              <?php else : ?>
                <span class="avatar p2-photo" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $h['name'], 0, 1))) ?></span>
              <?php endif; ?>
              <div class="p2-person-name">
                <h2 class="p2-h3"><?= e($h['name']) ?></h2>
                <p class="muted"><?= e($h['title'] ?: 'Sin cargo indicado') ?></p>
              </div>
            </div>
            <ul class="p2-meta">
              <?php if (!empty($h['email'])) : ?><li><?= icon('mail') ?><span><?= e($h['email']) ?></span></li><?php endif; ?>
              <?php if (!empty($h['phone'])) : ?><li><?= icon('phone') ?><span class="mono"><?= e(Str::phoneDisplay((string) $h['phone'])) ?></span></li><?php endif; ?>
              <li><?= icon('globe') ?><span><?= e($h['timezone']) ?></span></li>
              <li><?= icon('clock') ?><span><?= e($h['schedule_name'] ?: 'Horario predeterminado') ?></span></li>
              <li><?= icon('layers') ?><span><?= (int) $h['events_count'] ?> <?= (int) $h['events_count'] === 1 ? 'evento' : 'eventos' ?></span></li>
            </ul>
            <div class="row row-wrap gap-1">
              <?php if ((int) $h['active'] === 1) : ?><span class="badge badge-ok">Activo</span><?php else : ?><span class="badge badge-muted">Inactivo</span><?php endif; ?>
              <?php if ((int) $h['public_profile'] === 1) : ?><span class="badge badge-gold">Perfil público</span><?php endif; ?>
              <?php if (!empty($h['user_email'])) : ?><span class="badge badge-muted">Usuario: <?= e($h['user_email']) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="card-foot">
            <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/anfitriones/' . (int) $h['id'] . '/editar')) ?>"><?= icon('edit') ?> Editar</a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
