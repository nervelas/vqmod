<?php
/** @var array $hosts @var array $upcoming @var array $past @var bool $can_business @var string $biz_tz @var string $today */
$list = static function (array $rows, string $title, bool $showEmpty): void { ?>
  <section class="card">
    <div class="card-head"><h2 class="p2-h3"><?= e($title) ?></h2></div>
    <?php if (!$rows) : ?>
      <?php if ($showEmpty) : ?><div class="card-body"><div class="empty"><?= icon('sun') ?><h3 class="empty-title">Sin ausencias próximas</h3><p class="empty-text">Cuando registres vacaciones, permisos o cierres aparecerán aquí.</p></div></div><?php endif; ?>
    <?php else : ?>
      <ul class="p2-list">
        <?php foreach ($rows as $r) : ?>
          <li class="p2-list-row">
            <div>
              <strong><?= e($r['host_id'] === null ? 'Todo el negocio' : $r['host_name']) ?></strong>
              <span class="mono muted"> · <?= e($r['label']) ?></span>
              <?php if (!empty($r['reason'])) : ?><br><span class="muted"><?= e($r['reason']) ?></span><?php endif; ?>
            </div>
            <?php if ($r['can_delete']) : ?>
              <form method="post" action="<?= e(url('/admin/ausencias/' . (int) $r['id'] . '/eliminar')) ?>" class="inline" data-confirm="¿Quitar esta ausencia? Ese tiempo volverá a estar disponible para reservas.">
                <?= csrf_field() ?><button class="btn btn-ghost btn-sm text-err" type="submit" aria-label="Quitar ausencia"><?= icon('trash') ?></button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
<?php };
?>
<div class="page p2-page">
  <header class="page-head">
    <div>
      <h1 class="page-title">Ausencias</h1>
      <p class="page-sub">Vacaciones, permisos o cierres del negocio. En esos lapsos no se ofrecen horarios para reservar.</p>
    </div>
  </header>
  <div class="p2-split">
    <div class="stack">
      <?php $list($upcoming, 'Próximas y en curso', true); ?>
      <?php if ($past) : ?><?php $list($past, 'Pasadas recientes', false); ?><?php endif; ?>
    </div>
    <section class="card card-gold p2-side">
      <form method="post" action="<?= e(url('/admin/ausencias/guardar')) ?>" class="card-body stack" data-p2-timeoff autocomplete="off">
        <?= csrf_field() ?>
        <h2 class="p2-h3">Registrar una ausencia</h2>
        <div class="field">
          <label for="to-host">¿Quién se ausenta?</label>
          <select class="select" id="to-host" name="host_id">
            <?php if ($can_business) : ?><option value="business">Todo el negocio (cierre general)</option><?php endif; ?>
            <?php foreach ($hosts as $h) : ?><option value="<?= (int) $h['id'] ?>"><?= e($h['name']) ?></option><?php endforeach; ?>
          </select>
          <p class="hint">Las horas se leen en la zona horaria de la persona (para el negocio, <?= e($biz_tz) ?>).</p>
        </div>
        <div class="form-row">
          <div class="field"><label for="to-d1">Desde</label><input class="input" type="date" id="to-d1" name="start_date" required value="<?= e($today) ?>"></div>
          <div class="field"><label for="to-d2">Hasta</label><input class="input" type="date" id="to-d2" name="end_date" value="<?= e($today) ?>"></div>
        </div>
        <div class="switch-row"><label class="switch"><input type="checkbox" id="to-all" name="all_day" value="1" checked><span></span></label><label for="to-all">Todo el día</label></div>
        <div class="form-row" data-time-only hidden>
          <div class="field"><label for="to-t1">Hora de inicio</label><input class="input mono" type="time" id="to-t1" name="start_time" value="08:00"></div>
          <div class="field"><label for="to-t2">Hora de fin</label><input class="input mono" type="time" id="to-t2" name="end_time" value="12:00"></div>
        </div>
        <div class="field"><label for="to-reason">Motivo (opcional)</label><input class="input" type="text" id="to-reason" name="reason" maxlength="190" placeholder="Ej. Vacaciones"></div>
        <button class="btn btn-gold" type="submit"><?= icon('check') ?> Registrar ausencia</button>
      </form>
    </section>
  </div>
</div>
