<?php /** @var array $teams */ ?>
<div class="page p2-page">
  <header class="page-head">
    <div>
      <h1 class="page-title">Equipos</h1>
      <p class="page-sub">Agrupa anfitriones para asignarlos de una vez a un evento, por ejemplo «Odontología» o «Asesoría legal».</p>
    </div>
    <div class="page-actions"><a class="btn btn-gold" href="<?= e(url('/admin/equipos/nuevo')) ?>"><?= icon('plus') ?> Nuevo equipo</a></div>
  </header>
  <?php if (!$teams) : ?>
    <div class="empty p2-empty">
      <?= icon('users') ?>
      <h2 class="empty-title">Aún no hay equipos</h2>
      <p class="empty-text">Un equipo te ahorra marcar uno por uno a los anfitriones cuando creas eventos con varias personas.</p>
      <a class="btn btn-gold" href="<?= e(url('/admin/equipos/nuevo')) ?>"><?= icon('plus') ?> Crear equipo</a>
    </div>
  <?php else : ?>
    <div class="grid cols-3 p2-cards">
      <?php foreach ($teams as $t) : ?>
        <article class="card<?= (int) $t['active'] === 0 ? ' is-paused' : '' ?>">
          <div class="card-body stack">
            <div class="row row-between"><h2 class="p2-h3"><?= e($t['name']) ?></h2><?php if ((int) $t['active'] === 0) : ?><span class="badge badge-muted">Inactivo</span><?php endif; ?></div>
            <?php if (!empty($t['description'])) : ?><p class="muted"><?= e(\App\Core\Str::truncate((string) $t['description'], 140)) ?></p><?php endif; ?>
            <p class="p2-members"><?= $t['members'] ? e(implode(', ', $t['members'])) : 'Sin miembros todavía' ?></p>
            <p class="muted"><?= (int) $t['hosts_count'] ?> <?= (int) $t['hosts_count'] === 1 ? 'anfitrión' : 'anfitriones' ?> · <?= (int) $t['events_count'] ?> <?= (int) $t['events_count'] === 1 ? 'evento' : 'eventos' ?></p>
          </div>
          <div class="card-foot p2-actions">
            <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/equipos/' . (int) $t['id'] . '/editar')) ?>"><?= icon('edit') ?> Editar</a>
            <form method="post" action="<?= e(url('/admin/equipos/' . (int) $t['id'] . '/eliminar')) ?>" class="inline" data-confirm="¿Eliminar el equipo «<?= e($t['name']) ?>»? Los anfitriones no se borran.">
              <?= csrf_field() ?><button class="btn btn-ghost btn-sm text-err" type="submit"><?= icon('trash') ?> Eliminar</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
