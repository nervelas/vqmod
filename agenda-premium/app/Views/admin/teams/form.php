<?php
/** @var array $t @var ?int $id @var array $members @var ?string $error @var array $hosts */
$v = static fn (string $k, $d = '') => $t[$k] ?? $d;
$action = $id ? url('/admin/equipos/' . $id . '/editar') : url('/admin/equipos/nuevo');
?>
<div class="page p2-page">
  <header class="page-head">
    <div>
      <p class="p2-crumb"><a href="<?= e(url('/admin/equipos')) ?>"><?= icon('chevron-left') ?> Equipos</a></p>
      <h1 class="page-title"><?= $id ? 'Editar equipo' : 'Nuevo equipo' ?></h1>
    </div>
  </header>
  <?php if (!empty($error)) : ?><div class="alert alert-err" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e($action) ?>" class="stack p2-form-narrow" autocomplete="off">
    <?= csrf_field() ?>
    <section class="card"><div class="card-body stack">
      <div class="form-grid">
        <div class="field"><label for="t-name">Nombre del equipo</label><input class="input" type="text" id="t-name" name="name" maxlength="120" required value="<?= e($v('name')) ?>"></div>
        <div class="field"><label for="t-slug">Enlace</label><input class="input mono" type="text" id="t-slug" name="slug" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= e($v('slug')) ?>" placeholder="se-genera-solo"></div>
      </div>
      <div class="field"><label for="t-desc">Descripción</label><textarea class="textarea" id="t-desc" name="description" rows="3" maxlength="2000"><?= e($v('description')) ?></textarea></div>
      <div class="switch-row"><label class="switch"><input type="checkbox" id="t-active" name="active" value="1"<?= chk((int) $v('active', 1) === 1) ?>><span></span></label><label for="t-active">Equipo activo</label></div>
    </div></section>
    <section class="card"><div class="card-body stack">
      <h2 class="p2-h3">Miembros</h2>
      <?php if (!$hosts) : ?>
        <p class="muted">Aún no hay anfitriones. <a href="<?= e(url('/admin/anfitriones/nuevo')) ?>">Agrega el primero</a> para armar equipos.</p>
      <?php else : ?>
        <div class="p2-checklist">
          <?php foreach ($hosts as $h) : ?>
            <label class="check"><input type="checkbox" name="hosts[]" value="<?= (int) $h['id'] ?>"<?= chk(in_array((int) $h['id'], $members, true)) ?>><span><span class="p2-dot" <?= vars(['--c' => (string) $h['color']]) ?>></span> <strong><?= e($h['name']) ?></strong><?php if (!empty($h['title'])) : ?> <small class="muted">· <?= e($h['title']) ?></small><?php endif; ?></span></label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div></section>
    <div class="form-actions p2-sticky">
      <button class="btn btn-gold btn-lg" type="submit"><?= icon('check') ?> <?= $id ? 'Guardar cambios' : 'Crear equipo' ?></button>
      <a class="btn btn-ghost btn-lg" href="<?= e(url('/admin/equipos')) ?>">Cancelar</a>
    </div>
  </form>
</div>
