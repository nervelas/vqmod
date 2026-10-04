<?php
/** @var array $s @var ?int $id @var array $rules @var array $ex @var ?string $error @var array $days @var bool $is_admin @var array $in_use */
$action = $id ? url('/admin/horarios/' . $id . '/editar') : url('/admin/horarios/nuevo');
$blank = ['start' => '09:00', 'end' => '17:00'];
?>
<div class="page p2-page">
  <header class="page-head">
    <div>
      <p class="p2-crumb"><a href="<?= e(url('/admin/horarios')) ?>"><?= icon('chevron-left') ?> Horarios</a></p>
      <h1 class="page-title"><?= $id ? 'Editar horario' : 'Nuevo horario' ?></h1>
      <p class="page-sub">Las horas son del reloj local de la zona horaria elegida. Un día sin bloques queda cerrado.</p>
    </div>
  </header>
  <?php if (!empty($error)) : ?><div class="alert alert-err" role="alert"><?= e($error) ?></div><?php endif; ?>
  <?php if ($id && ($in_use['hosts'] + $in_use['events']) > 1) : ?>
    <div class="alert alert-info">Este horario lo usan <?= (int) $in_use['hosts'] ?> anfitrión(es) y <?= (int) $in_use['events'] ?> evento(s). Los cambios aplican a todos.</div>
  <?php endif; ?>

  <form method="post" action="<?= e($action) ?>" class="p2-sched" data-p2-sched autocomplete="off">
    <?= csrf_field() ?>
    <div class="p2-sched-main stack">
      <section class="card"><div class="card-body stack">
        <div class="form-grid">
          <div class="field"><label for="s-name">Nombre</label><input class="input" type="text" id="s-name" name="name" maxlength="120" required value="<?= e($s['name'] ?? '') ?>" placeholder="Ej. Horario de consultorio"></div>
          <div class="field"><label for="s-tz">Zona horaria</label><?= \App\Core\View::partial('admin/schedules/tzselect', ['name' => 'timezone', 'value' => (string) ($s['timezone'] ?? 'America/Guatemala'), 'id' => 's-tz']) ?></div>
        </div>
        <?php if ($is_admin) : ?>
          <div class="switch-row"><label class="switch"><input type="checkbox" id="s-def" name="is_default" value="1"<?= chk((int) ($s['is_default'] ?? 0) === 1) ?>><span></span></label><label for="s-def">Usar como horario predeterminado</label></div>
        <?php endif; ?>
      </div></section>

      <section class="card"><div class="card-body stack">
        <h2 class="p2-h3">Horario semanal</h2>
        <div class="p2-days">
          <?php foreach ($days as $d => $dn) : $blocks = $rules[$d] ?? []; ?>
            <div class="p2-day" data-day="<?= (int) $d ?>" role="group" aria-labelledby="day-<?= (int) $d ?>-t">
              <div class="p2-day-head">
                <h3 id="day-<?= (int) $d ?>-t" class="p2-day-name"><?= e($dn) ?></h3>
                <span class="muted p2-closed" data-closed<?= $blocks ? ' hidden' : '' ?>>Cerrado</span>
              </div>
              <div class="p2-day-blocks" data-blocks>
                <?php foreach ($blocks as $i => $b) : ?>
                  <?= \App\Core\View::partial('admin/schedules/block', ['prefix' => 'rules[' . $d . '][' . $i . ']', 'b' => $b]) ?>
                <?php endforeach; ?>
              </div>
              <div class="p2-day-tools">
                <button class="btn btn-outline btn-sm" type="button" data-add-block data-next="<?= count($blocks) ?>"><?= icon('plus') ?> <span>Agregar bloque</span></button>
                <details class="p2-copy">
                  <summary class="btn btn-ghost btn-sm"><?= icon('copy') ?> Copiar a…</summary>
                  <div class="p2-copy-box">
                    <?php foreach ($days as $d2 => $dn2) : if ($d2 === $d) { continue; } ?>
                      <label class="check"><input type="checkbox" value="<?= (int) $d2 ?>" data-copy-to><span><?= e($dn2) ?></span></label>
                    <?php endforeach; ?>
                    <button class="btn btn-gold btn-sm" type="button" data-copy-apply>Aplicar</button>
                  </div>
                </details>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div></section>

      <section class="card"><div class="card-body stack">
        <h2 class="p2-h3">Excepciones por fecha</h2>
        <p class="muted">Cierra un día puntual o dale horas distintas a las de la semana. Toca una fecha del calendario para agregarla.</p>
        <div class="p2-excal">
          <div class="p2-cal" data-p2-cal aria-label="Calendario para elegir fechas de excepción"></div>
          <div class="p2-exlist">
            <div data-ex-list>
              <?php foreach ($ex as $x => $o) : ?><?= \App\Core\View::partial('admin/schedules/exrow', ['x' => $x, 'o' => $o]) ?><?php endforeach; ?>
            </div>
            <div class="empty p2-ex-empty" data-ex-empty<?= $ex ? ' hidden' : '' ?>><p class="empty-text">Sin excepciones. Útil para jornadas especiales, feriados propios o cierres por capacitación.</p></div>
            <button class="btn btn-outline" type="button" data-ex-add data-next="<?= count($ex) ?>"><?= icon('plus') ?> Agregar excepción</button>
          </div>
        </div>
      </div></section>
    </div>

    <aside class="p2-sched-side">
      <section class="card card-gold"><div class="card-body stack">
        <h2 class="p2-h3">Vista previa semanal</h2>
        <div class="p2-preview-week" data-p2-weekprev role="img" aria-label="Vista previa de las horas de atención de la semana"></div>
        <p class="hint">De 6 a. m. a 10 p. m. Las barras doradas son horas de atención.</p>
      </div></section>
    </aside>

    <div class="form-actions p2-sticky">
      <button class="btn btn-gold btn-lg" type="submit"><?= icon('check') ?> <?= $id ? 'Guardar horario' : 'Crear horario' ?></button>
      <a class="btn btn-ghost btn-lg" href="<?= e(url('/admin/horarios')) ?>">Cancelar</a>
    </div>

    <template id="p2-block-tpl"><?= \App\Core\View::partial('admin/schedules/block', ['prefix' => '__P__', 'b' => $blank]) ?></template>
    <template id="p2-ex-tpl"><?= \App\Core\View::partial('admin/schedules/exrow', ['x' => '__X__', 'o' => ['date' => '', 'open' => 0, 'note' => '', 'blocks' => []]]) ?></template>
  </form>
</div>
