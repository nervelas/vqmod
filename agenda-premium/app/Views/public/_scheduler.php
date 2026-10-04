<?php
/**
 * Selector de fecha y hora: calendario + esfera de reloj + lista (comparten estado en booking.js).
 * Variables: $sid (prefijo de ids), $tzGroups, $waitlist (bool)
 */
$sid = $sid ?? 'sch';
$waitlist = !empty($waitlist);
?>
<div class="sch" data-scheduler>
  <div class="sch-tools">
    <div class="field sch-tz">
      <label for="<?= e($sid) ?>-tz">Zona horaria</label>
      <select class="select" id="<?= e($sid) ?>-tz" data-tz autocomplete="off">
        <?php foreach ($tzGroups as $region => $zones) : ?>
          <optgroup label="<?= e($region) ?>">
            <?php foreach ($zones as $z) : ?><option value="<?= e($z['id']) ?>"><?= e($z['label']) ?></option><?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
        <option value="UTC">UTC</option>
      </select>
    </div>
    <div class="seg" role="group" aria-label="Formato de hora">
      <button type="button" class="seg-btn" data-fmt="12" aria-pressed="true">12 h</button>
      <button type="button" class="seg-btn" data-fmt="24" aria-pressed="false">24 h</button>
    </div>
  </div>

  <div class="sch-grid">
    <div class="cal" data-cal>
      <div class="cal-head">
        <button type="button" class="btn btn-ghost btn-icon" data-cal-prev aria-label="Mes anterior"><?= icon('chevron-left') ?></button>
        <h3 class="cal-title serif" data-cal-title aria-live="polite"></h3>
        <button type="button" class="btn btn-ghost btn-icon" data-cal-next aria-label="Mes siguiente"><?= icon('chevron-right') ?></button>
      </div>
      <div class="cal-dow" aria-hidden="true"><span>lun</span><span>mar</span><span>mié</span><span>jue</span><span>vie</span><span>sáb</span><span>dom</span></div>
      <div class="cal-days" data-cal-days role="group" aria-label="Días del mes"></div>
      <p class="cal-legend muted"><span class="cal-dot" aria-hidden="true"></span> Días con horarios disponibles</p>
    </div>

    <div class="times" data-times>
      <div class="next" data-next hidden>
        <span class="next-label">Próximo horario disponible</span>
        <button type="button" class="next-btn" data-next-btn><span class="next-when serif" data-next-when></span><span class="btn btn-gold btn-sm" aria-hidden="true">Elegir</span></button>
      </div>
      <div class="times-head">
        <h3 class="times-title serif" data-day-title tabindex="-1">Elige un día</h3>
        <div class="seg" role="group" aria-label="Vista de horarios">
          <button type="button" class="seg-btn" data-view="dial" aria-pressed="true">Esfera</button>
          <button type="button" class="seg-btn" data-view="list" aria-pressed="false">Lista</button>
        </div>
      </div>
      <div class="times-body">
        <div class="state" data-state="idle"><?= icon('calendar') ?><p>Elige un día resaltado del calendario para ver sus horarios.</p></div>
        <div class="state" data-state="loading" hidden role="status"><span class="skeleton sk-dial"></span><p class="muted">Buscando horarios…</p></div>
        <div class="state state-err" data-state="error" hidden role="alert"><?= icon('alert') ?><p data-error-text>No pudimos cargar los horarios.</p><button type="button" class="btn btn-outline btn-sm" data-retry>Intentar de nuevo</button></div>
        <div class="state" data-state="none" hidden><?= icon('clock') ?><p data-none-text>No hay horarios para este día.</p>
          <?php if ($waitlist) : ?><button type="button" class="btn btn-outline btn-sm" data-open-waitlist>Avísame si se libera uno</button><?php endif; ?>
        </div>
        <div class="view-dial" data-view-dial hidden>
          <div class="dial-host" data-dial></div>
          <div class="ampm seg" role="group" aria-label="Mañana o tarde">
            <button type="button" class="seg-btn" data-ampm="am" aria-pressed="true">a. m.</button>
            <button type="button" class="seg-btn" data-ampm="pm" aria-pressed="false">p. m.</button>
          </div>
        </div>
        <div class="view-list" data-view-list hidden></div>
      </div>
      <p class="sr-only" role="status" aria-live="polite" data-live></p>
    </div>
  </div>
</div>
