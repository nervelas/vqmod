<?php
/** @var array $rows @var int $year @var bool $enabled @var bool $capital @var int $this_year */
$back = static fn (int $y): string => url('/admin/feriados', ['anio' => $y]);
?>
<div class="page p2-page">
  <header class="page-head">
    <div>
      <h1 class="page-title">Feriados</h1>
      <p class="page-sub">Los días feriados de Guatemala ya vienen cargados. Cierra o abre cada uno según tu negocio.</p>
    </div>
    <div class="page-actions">
      <button class="btn btn-gold" type="button" data-fill-dialog="#holiday-modal" data-fill="<?= ej(['id' => '', 'date' => $year . '-01-01', 'name' => '', 'kind' => 'full', 'half_day_end' => '12:00', 'scope' => 'national', 'active' => '1']) ?>" data-title="Agregar feriado"><?= icon('plus') ?> Agregar feriado</button>
    </div>
  </header>

  <section class="card"><form method="post" action="<?= e(url('/admin/feriados/interruptor')) ?>" class="card-body p2-master">
    <?= csrf_field() ?><input type="hidden" name="anio" value="<?= (int) $year ?>">
    <div class="switch-row"><label class="switch"><input type="checkbox" id="m-on" name="holidays_enabled" value="1"<?= chk($enabled) ?>><span></span></label><label for="m-on"><strong>Respetar los feriados</strong><br><small class="muted">Si lo apagas, ningún feriado cierra la agenda.</small></label></div>
    <div class="switch-row"><label class="switch"><input type="checkbox" id="m-cap" name="holidays_capital" value="1"<?= chk($capital) ?>><span></span></label><label for="m-cap"><strong>Incluir feriados solo de la ciudad capital</strong><br><small class="muted">Por ejemplo, el 15 de agosto.</small></label></div>
    <button class="btn btn-outline" type="submit">Guardar</button>
  </form></section>

  <nav class="p2-yearnav" aria-label="Año">
    <a class="btn btn-ghost btn-sm" href="<?= e($back($year - 1)) ?>"><?= icon('chevron-left') ?> <?= (int) $year - 1 ?></a>
    <h2 class="serif p2-year"><?= (int) $year ?></h2>
    <a class="btn btn-ghost btn-sm" href="<?= e($back($year + 1)) ?>"><?= (int) $year + 1 ?> <?= icon('chevron-right') ?></a>
    <?php if ($year !== $this_year) : ?><a class="btn btn-outline btn-sm" href="<?= e($back($this_year)) ?>">Ir a <?= (int) $this_year ?></a><?php endif; ?>
  </nav>

  <?php if (!$rows) : ?>
    <div class="empty p2-empty"><?= icon('flag') ?><h2 class="empty-title">No hay feriados en <?= (int) $year ?></h2><p class="empty-text">Agrega los que apliquen a tu negocio o restablece el año para cargar los de Guatemala.</p></div>
  <?php else : ?>
    <div class="card"><div class="table-wrap">
      <table class="table p2-holidays">
        <caption class="sr-only">Feriados de <?= (int) $year ?></caption>
        <thead><tr><th scope="col">Fecha</th><th scope="col">Feriado</th><th scope="col" class="hide-sm">Tipo</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r) : $on = (int) $r['active'] === 1; ?>
            <tr class="<?= $on ? '' : 'is-off' ?>">
              <td class="nowrap"><span class="mono"><?= e($r['dmy']) ?></span><br><small class="muted"><?= e($r['dow']) ?></small></td>
              <td><strong><?= e($r['name']) ?></strong><?= $r['source'] === 'manual' ? ' <span class="badge badge-gold">Manual</span>' : '' ?></td>
              <td class="hide-sm"><?= $r['kind'] === 'half' ? 'Medio día (hasta las ' . e($r['end']) . ')' : 'Día completo' ?><?= $r['scope'] === 'capital' ? '<br><small class="muted">Solo ciudad capital</small>' : '' ?></td>
              <td><?= $on ? '<span class="badge badge-ok">Cierra la agenda</span>' : '<span class="badge badge-muted">Se atiende normal</span>' ?></td>
              <td class="right nowrap">
                <form method="post" action="<?= e(url('/admin/feriados/' . (int) $r['id'] . '/activo')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= $on ? 'Desactivar' : 'Activar' ?></button></form>
                <button class="btn btn-ghost btn-sm" type="button" data-fill-dialog="#holiday-modal" data-title="Editar feriado" data-fill="<?= ej(['id' => (string) $r['id'], 'date' => $r['date'], 'name' => $r['name'], 'kind' => $r['kind'], 'half_day_end' => $r['end'] ?: '12:00', 'scope' => $r['scope'], 'active' => (string) $r['active']]) ?>" aria-label="Editar <?= e($r['name']) ?>"><?= icon('edit') ?></button>
                <form method="post" action="<?= e(url('/admin/feriados/' . (int) $r['id'] . '/eliminar')) ?>" class="inline" data-confirm="¿Quitar «<?= e($r['name']) ?>» de la lista?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm text-err" type="submit" aria-label="Quitar <?= e($r['name']) ?>"><?= icon('trash') ?></button></form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('/admin/feriados/restablecer')) ?>" class="p2-reset" data-confirm="¿Restablecer los feriados de <?= (int) $year ?>? Se pierden los cambios hechos a los feriados automáticos de ese año; los manuales se conservan.">
    <?= csrf_field() ?><input type="hidden" name="anio" value="<?= (int) $year ?>">
    <button class="btn btn-ghost btn-sm" type="submit"><?= icon('undo') ?> Restablecer los feriados de <?= (int) $year ?></button>
  </form>

  <dialog class="modal" id="holiday-modal" aria-labelledby="holiday-title">
    <form method="post" action="<?= e(url('/admin/feriados/guardar')) ?>" class="stack">
      <?= csrf_field() ?><input type="hidden" name="id" value="">
      <div class="modal-head"><h2 class="p2-h3" id="holiday-title" data-dialog-title>Agregar feriado</h2></div>
      <div class="modal-body stack">
        <div class="field"><label for="hd-name">Nombre</label><input class="input" type="text" id="hd-name" name="name" maxlength="120" required></div>
        <div class="form-row">
          <div class="field"><label for="hd-date">Fecha</label><input class="input" type="date" id="hd-date" name="date" required></div>
          <div class="field"><label for="hd-kind">Cierre</label><select class="select" id="hd-kind" name="kind"><option value="full">Día completo</option><option value="half">Medio día</option></select></div>
        </div>
        <div class="form-row">
          <div class="field"><label for="hd-end">Si es medio día, se atiende hasta</label><input class="input mono" type="time" id="hd-end" name="half_day_end" data-default="12:00"></div>
          <div class="field"><label for="hd-scope">Aplica a</label><select class="select" id="hd-scope" name="scope"><option value="national">Todo el país</option><option value="capital">Solo ciudad capital</option></select></div>
        </div>
        <div class="switch-row"><label class="switch"><input type="checkbox" id="hd-active" name="active" value="1" checked><span></span></label><label for="hd-active">Cerrar la agenda ese día</label></div>
      </div>
      <div class="modal-foot"><button class="btn btn-ghost" type="button" data-modal-close data-p2-close>Cancelar</button><button class="btn btn-gold" type="submit">Guardar</button></div>
    </form>
  </dialog>
</div>
