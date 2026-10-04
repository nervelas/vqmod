<?php /** @var array $rows */ ?>
<div class="page p2-page">
  <header class="page-head">
    <div>
      <h1 class="page-title">Recursos y salas</h1>
      <p class="page-sub">Consultorios, salas o equipos que una cita ocupa mientras dura. Asígnalos desde cada tipo de evento.</p>
    </div>
    <div class="page-actions"><button class="btn btn-gold" type="button" data-fill-dialog="#resource-modal" data-title="Nuevo recurso" data-fill="<?= ej(['id' => '', 'name' => '', 'description' => '', 'capacity' => '1', 'active' => '1']) ?>"><?= icon('plus') ?> Nuevo recurso</button></div>
  </header>
  <?php if (!$rows) : ?>
    <div class="empty p2-empty"><?= icon('building') ?><h2 class="empty-title">Aún no hay salas ni recursos</h2><p class="empty-text">Si tus citas necesitan un consultorio o equipo, créalo aquí y el sistema evitará que dos citas lo ocupen a la vez.</p></div>
  <?php else : ?>
    <div class="grid cols-3 p2-cards">
      <?php foreach ($rows as $r) : ?>
        <article class="card<?= (int) $r['active'] === 0 ? ' is-paused' : '' ?>">
          <div class="card-body stack">
            <div class="row row-between"><h2 class="p2-h3"><?= e($r['name']) ?></h2><?= (int) $r['active'] === 1 ? '<span class="badge badge-ok">Activo</span>' : '<span class="badge badge-muted">Inactivo</span>' ?></div>
            <?php if (!empty($r['description'])) : ?><p class="muted"><?= e($r['description']) ?></p><?php endif; ?>
            <p class="muted"><?= icon('users') ?> Capacidad: <?= (int) $r['capacity'] ?> · <?= (int) $r['events_count'] ?> <?= (int) $r['events_count'] === 1 ? 'evento' : 'eventos' ?></p>
          </div>
          <div class="card-foot p2-actions">
            <button class="btn btn-outline btn-sm" type="button" data-fill-dialog="#resource-modal" data-title="Editar recurso" data-fill="<?= ej(['id' => (string) $r['id'], 'name' => $r['name'], 'description' => (string) $r['description'], 'capacity' => (string) $r['capacity'], 'active' => (string) $r['active']]) ?>"><?= icon('edit') ?> Editar</button>
            <form method="post" action="<?= e(url('/admin/recursos/' . (int) $r['id'] . '/eliminar')) ?>" class="inline" data-confirm="¿Eliminar «<?= e($r['name']) ?>»?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm text-err" type="submit"><?= icon('trash') ?> Eliminar</button></form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <dialog class="modal" id="resource-modal" aria-labelledby="resource-title">
    <form method="post" action="<?= e(url('/admin/recursos/guardar')) ?>" class="stack">
      <?= csrf_field() ?><input type="hidden" name="id" value="">
      <div class="modal-head"><h2 class="p2-h3" id="resource-title" data-dialog-title>Nuevo recurso</h2></div>
      <div class="modal-body stack">
        <div class="field"><label for="rs-name">Nombre</label><input class="input" type="text" id="rs-name" name="name" maxlength="120" required placeholder="Ej. Consultorio 2"></div>
        <div class="field"><label for="rs-desc">Descripción</label><input class="input" type="text" id="rs-desc" name="description" maxlength="255"></div>
        <div class="field"><label for="rs-cap">Capacidad (personas)</label><input class="input" type="number" id="rs-cap" name="capacity" min="1" max="1000" value="1"></div>
        <div class="switch-row"><label class="switch"><input type="checkbox" id="rs-active" name="active" value="1" checked><span></span></label><label for="rs-active">Disponible para reservas</label></div>
      </div>
      <div class="modal-foot"><button class="btn btn-ghost" type="button" data-modal-close data-p2-close>Cancelar</button><button class="btn btn-gold" type="submit">Guardar</button></div>
    </form>
  </dialog>
</div>
