<?php
/** @var array $schedules @var bool $is_host @var bool $can_create */
?>
<div class="page p2-page">
  <header class="page-head">
    <div>
      <h1 class="page-title"><?= $is_host ? 'Mi horario' : 'Horarios' ?></h1>
      <p class="page-sub"><?= $is_host ? 'Los días y horas en que atiendes citas.' : 'Plantillas de horas de atención que puedes asignar a anfitriones y eventos.' ?></p>
    </div>
    <?php if ($can_create) : ?>
      <div class="page-actions"><a class="btn btn-gold" href="<?= e(url('/admin/horarios/nuevo')) ?>"><?= icon('plus') ?> <?= $is_host ? 'Crear mi horario propio' : 'Nuevo horario' ?></a></div>
    <?php endif; ?>
  </header>

  <?php if (!$schedules) : ?>
    <div class="empty p2-empty">
      <?= icon('clock') ?>
      <h2 class="empty-title"><?= $is_host ? 'Todavía no tienes un horario propio' : 'Aún no hay horarios' ?></h2>
      <p class="empty-text"><?= $is_host ? 'Ahora usas el horario general del negocio. Crea el tuyo para decidir en qué días y horas atiendes.' : 'Crea el primero para decir cuándo se atienden las citas.' ?></p>
      <?php if ($can_create) : ?><a class="btn btn-gold" href="<?= e(url('/admin/horarios/nuevo')) ?>"><?= icon('plus') ?> Crear horario</a><?php endif; ?>
    </div>
  <?php else : ?>
    <div class="grid cols-2 p2-cards">
      <?php foreach ($schedules as $s) : ?>
        <article class="card">
          <div class="card-body stack">
            <div class="row row-between">
              <h2 class="p2-h3"><?= e($s['name']) ?></h2>
              <?php if ((int) $s['is_default'] === 1) : ?><span class="badge badge-gold">Predeterminado</span><?php endif; ?>
            </div>
            <p class="muted"><?= icon('globe') ?> <?= e($s['timezone']) ?></p>
            <table class="p2-week" aria-label="Resumen semanal de <?= e($s['name']) ?>">
              <tbody>
                <?php foreach (\App\Controllers\Admin\SchedulesController::DAYS as $d => $dn) : $bl = $s['summary']['days'][$d]; ?>
                  <tr><th scope="row"><?= e(mb_substr($dn, 0, 3)) ?></th><td class="mono"><?= $bl ? e(implode(' · ', array_map(static fn (array $b): string => $b['start'] . '–' . $b['end'], $bl))) : '<span class="muted">Cerrado</span>' ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <p class="muted"><?= (int) $s['summary']['exceptions'] ?> <?= (int) $s['summary']['exceptions'] === 1 ? 'excepción' : 'excepciones' ?> · usado por <?= (int) $s['hosts_count'] ?> <?= (int) $s['hosts_count'] === 1 ? 'anfitrión' : 'anfitriones' ?> y <?= (int) $s['events_count'] ?> <?= (int) $s['events_count'] === 1 ? 'evento' : 'eventos' ?></p>
          </div>
          <div class="card-foot p2-actions">
            <?php if ($s['can_edit']) : ?>
              <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/horarios/' . (int) $s['id'] . '/editar')) ?>"><?= icon('edit') ?> Editar</a>
            <?php elseif ($is_host) : ?>
              <span class="muted">Este horario lo comparten otras personas; pide al administrador que lo ajuste o crea el tuyo.</span>
            <?php endif; ?>
            <?php if (!$is_host && (int) $s['is_default'] === 0) : ?>
              <form method="post" action="<?= e(url('/admin/horarios/' . (int) $s['id'] . '/predeterminado')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('star') ?> Hacer predeterminado</button></form>
              <form method="post" action="<?= e(url('/admin/horarios/' . (int) $s['id'] . '/eliminar')) ?>" class="inline" data-confirm="¿Eliminar el horario «<?= e($s['name']) ?>»?">
                <?= csrf_field() ?><button class="btn btn-ghost btn-sm text-err" type="submit"><?= icon('trash') ?> Eliminar</button>
              </form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
