<?php
/** @var array $boot @var array $hosts @var array $events */
$many = count($hosts) > 1 && empty($boot['scoped']);
?>
<div class="page page-wide cal-page" id="calendar" data-calendar>
  <script type="application/json" id="boot"><?= json_script($boot) ?></script>
  <div class="page-head">
    <div>
      <h1 class="page-title serif">Calendario</h1>
      <p class="page-sub" id="cal-title" aria-live="polite">&nbsp;</p>
    </div>
    <div class="page-actions">
      <a class="btn btn-gold" href="<?= e(url('/admin/citas/nueva')) ?>"><?= icon('plus') ?>Nueva cita</a>
    </div>
  </div>

  <div class="cal-toolbar card">
    <div class="cal-nav row gap-2">
      <button class="btn btn-ghost btn-icon" type="button" data-cal-prev aria-label="Periodo anterior"><?= icon('chevron-left') ?></button>
      <button class="btn btn-outline btn-sm" type="button" data-cal-today>Hoy <kbd class="kbd hide-sm">T</kbd></button>
      <button class="btn btn-ghost btn-icon" type="button" data-cal-next aria-label="Periodo siguiente"><?= icon('chevron-right') ?></button>
    </div>
    <div class="tabs cal-views" role="tablist" aria-label="Vista del calendario">
      <button class="tab" type="button" role="tab" data-cal-view="dia" aria-selected="false">Día</button>
      <button class="tab" type="button" role="tab" data-cal-view="semana" aria-selected="false">Semana</button>
      <button class="tab" type="button" role="tab" data-cal-view="mes" aria-selected="false">Mes</button>
    </div>
    <div class="cal-filters row gap-2 row-wrap">
      <?php if ($many) : ?>
      <label class="sr-only" for="cal-host"><?= e(ucfirst((string) setting('host_label', 'profesional'))) ?></label>
      <select class="select" id="cal-host" data-cal-host><option value="">Todos los anfitriones</option>
        <?php foreach ($hosts as $h) : ?><option value="<?= (int) $h['id'] ?>"><?= e($h['name']) ?></option><?php endforeach; ?>
      </select>
      <?php endif; ?>
      <label class="sr-only" for="cal-event">Tipo de cita</label>
      <select class="select" id="cal-event" data-cal-event><option value="">Todos los tipos</option>
        <?php foreach ($events as $ev) : ?><option value="<?= (int) $ev['id'] ?>"><?= e($ev['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>

  <?php if ($many) : ?>
  <ul class="cal-legend" aria-label="Colores por anfitrión">
    <?php foreach ($hosts as $h) : ?><li <?= vars(['--c' => $h['color']]) ?>><span class="dot-c"></span><?= e($h['name']) ?></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <div class="cal-layout">
    <div class="cal-stage card" id="cal-stage" aria-live="polite" aria-busy="false">
      <div class="cal-loading" data-cal-loading hidden><span class="skeleton"></span></div>
      <div class="cal-error alert alert-err" data-cal-error role="alert" hidden></div>
      <div class="cal-view" data-cal-view-root></div>
    </div>
    <aside class="cal-panel card" id="cal-panel" aria-label="Detalle de la cita" tabindex="-1" hidden></aside>
  </div>

  <p class="cal-help muted small">
    Atajos: <kbd class="kbd">T</kbd> hoy · <kbd class="kbd">←</kbd> <kbd class="kbd">→</kbd> navegar · <kbd class="kbd">D</kbd> <kbd class="kbd">S</kbd> <kbd class="kbd">M</kbd> día, semana, mes · <kbd class="kbd">N</kbd> nueva cita.
    Arrastra una cita para moverla, o ábrela y usa «Reprogramar».
  </p>
</div>
<?php partial('admin/bookings/_reschedule'); ?>
