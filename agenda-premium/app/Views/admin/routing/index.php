<?php /** @var array $forms */ ?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">Formularios de enrutamiento</h1><p class="page-sub">Haz unas preguntas y envía a cada persona al servicio, anfitrión o equipo correcto según sus respuestas.</p></div>
    <div class="page-actions"><a class="btn btn-gold" href="<?= e(url('/admin/enrutamiento/nuevo')) ?>"><?= icon('plus') ?>Nuevo formulario</a></div>
  </div>
  <?php if (!$forms) : ?>
    <div class="empty"><?= icon('route') ?><p class="empty-title">Todavía no tienes formularios</p><p class="empty-text">Por ejemplo: "¿Qué necesitas?" y según la respuesta se muestra la cita adecuada. Todo queda registrado en una bitácora.</p><a class="btn btn-gold" href="<?= e(url('/admin/enrutamiento/nuevo')) ?>">Crear el primero</a></div>
  <?php else : ?>
    <ul class="stack">
      <?php foreach ($forms as $f) : ?>
        <li class="card">
          <div class="card-body">
            <div class="row row-between row-wrap gap-2">
              <div><h2 class="serif p3-card-title"><a href="<?= e(url('/admin/enrutamiento/' . (int) $f['id'] . '/editar')) ?>"><?= e($f['name']) ?></a></h2>
                <p class="muted"><?= (int) $f['q_count'] ?> preguntas · <?= (int) $f['rule_count'] ?> reglas · <?= (int) $f['log_count'] ?> respuestas registradas</p></div>
              <span class="badge <?= (int) $f['active'] ? 'badge-ok' : 'badge-muted' ?>"><?= (int) $f['active'] ? 'Activo' : 'Pausado' ?></span>
            </div>
            <div class="input-group mt-2"><input class="input mono" id="rl-<?= (int) $f['id'] ?>" readonly value="<?= e($f['link']) ?>" aria-label="Enlace público de <?= e($f['name']) ?>"><button class="btn btn-outline" type="button" data-copy="#rl-<?= (int) $f['id'] ?>"><?= icon('copy') ?>Copiar</button><a class="btn btn-ghost" href="<?= e($f['link']) ?>" target="_blank" rel="noopener"><?= icon('external') ?>Abrir</a></div>
          </div>
          <div class="card-foot row row-wrap gap-2">
            <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/enrutamiento/' . (int) $f['id'] . '/editar')) ?>"><?= icon('edit') ?>Editar</a>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/enrutamiento/' . (int) $f['id'] . '/bitacora')) ?>"><?= icon('list') ?>Bitácora y estadísticas</a>
            <form method="post" action="<?= e(url('/admin/enrutamiento/' . (int) $f['id'] . '/estado')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= (int) $f['active'] ? 'Pausar' : 'Activar' ?></button></form>
            <form method="post" action="<?= e(url('/admin/enrutamiento/' . (int) $f['id'] . '/eliminar')) ?>" class="inline" data-confirm="¿Eliminar el formulario &quot;<?= e($f['name']) ?>&quot; y su bitácora?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-icon" type="submit" aria-label="Eliminar <?= e($f['name']) ?>"><?= icon('trash') ?></button></form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
