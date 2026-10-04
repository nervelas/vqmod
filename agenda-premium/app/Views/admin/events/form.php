<?php
/**
 * Formulario de tipo de evento (pestañas + vista previa en vivo).
 * @var array $ev @var ?int $id @var ?string $error @var array $hosts @var array $teams @var array $team_hosts
 * @var array $resources @var array $schedules @var array $presets @var string $jitsi @var string $public_url @var string $base_url @var array $biz
 */
use App\Controllers\Admin\A2Controller;
use App\Controllers\Admin\EventsController;

$v = static fn (string $k, $d = '') => $ev[$k] ?? $d;
$sw = static function (string $name, string $label, bool $on, string $attrs = ''): string {
    $id = 'sw-' . preg_replace('/[^a-z0-9]+/i', '-', $name);
    return '<div class="switch-row"><label class="switch"><input type="checkbox" id="' . e($id) . '" name="' . e($name) . '" value="1"' . ($attrs !== '' ? ' ' . $attrs : '') . ($on ? ' checked' : '') . '><span></span></label><label for="' . e($id) . '">' . e($label) . '</label></div>';
};
$durations = (array) $v('durations', [30]);
$extra = array_values(array_diff($durations, $presets));
$action = $id ? url('/admin/eventos/' . $id . '/editar') : url('/admin/eventos/nuevo');
$questions = (array) $v('questions', []);
$tabs = [
    'general' => 'General',
    'duracion' => 'Duración',
    'disponibilidad' => 'Disponibilidad',
    'equipo' => 'Anfitriones y recursos',
    'preguntas' => 'Preguntas',
    'reserva' => 'Reserva y pagos',
];
$kindHelp = [
    'individual' => 'Una persona por horario, con un solo anfitrión.',
    'group' => 'Varias personas comparten el mismo horario hasta llenar los cupos.',
    'round_robin' => 'Las citas se reparten entre los anfitriones que estén libres.',
    'collective' => 'Todos los anfitriones elegidos atienden la misma cita.',
];
$modeIcon = ['in_person' => 'map-pin', 'video_auto' => 'video', 'video_custom' => 'video', 'phone' => 'phone', 'home' => 'home'];
$modeHelp = ['video_auto' => 'Creamos una sala única en ' . $jitsi . ' para cada cita; el enlace llega por correo.'];
$selHosts = (array) $v('hosts_sel', []);
$selRes = array_map('intval', (array) $v('res_sel', []));
$slotOptions = [5, 10, 15, 20, 30, 45, 60, 90, 120];
$paymentsNote = 'El cobro se registra desde Pagos; aquí defines el precio y el anticipo.';
?>
<div class="page p2-page">
  <header class="page-head">
    <div>
      <p class="p2-crumb"><a href="<?= e(url('/admin/eventos')) ?>"><?= icon('chevron-left') ?> Tipos de evento</a></p>
      <h1 class="page-title"><?= $id ? 'Editar evento' : 'Nuevo evento' ?></h1>
      <p class="page-sub">Define cómo se reserva, quién atiende y qué le preguntas a tu cliente. A la derecha ves cómo se verá.</p>
    </div>
    <?php if ($id) : ?>
      <div class="page-actions">
        <div class="input-group p2-publink">
          <input class="input mono" type="text" readonly id="public-link" value="<?= e($public_url) ?>" aria-label="Enlace público del evento">
          <button class="btn btn-outline btn-icon" type="button" data-copy="#public-link" aria-label="Copiar enlace público"><?= icon('copy') ?></button>
          <a class="btn btn-outline btn-icon" href="<?= e($public_url) ?>" target="_blank" rel="noopener" aria-label="Abrir página de reserva"><?= icon('external') ?></a>
        </div>
      </div>
    <?php endif; ?>
  </header>

  <?php if (!empty($error)) : ?>
    <div class="alert alert-err" role="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="p2-editor">
    <form method="post" action="<?= e($action) ?>" id="event-form" class="p2-form" data-p2-event-form data-slug-base="<?= e($base_url) ?>" data-jitsi="<?= e($jitsi) ?>" data-currency="<?= e((string) setting('currency_symbol', 'Q')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <div class="tabs p2-tabs" role="tablist" aria-label="Secciones del evento" data-p2-tabs>
        <?php $first = true; foreach ($tabs as $key => $label) : ?>
          <button type="button" class="tab<?= $first ? ' is-active' : '' ?>" role="tab" id="tab-<?= e($key) ?>" aria-controls="panel-<?= e($key) ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" tabindex="<?= $first ? '0' : '-1' ?>"><?= e($label) ?></button>
        <?php $first = false; endforeach; ?>
      </div>

      <!-- GENERAL -->
      <section class="p2-tabpanel stack" role="tabpanel" id="panel-general" aria-labelledby="tab-general">
        <div class="form-grid">
          <div class="field">
            <label for="f-name">Nombre del evento</label>
            <input class="input" type="text" id="f-name" name="name" maxlength="160" required value="<?= e($v('name')) ?>" placeholder="Ej. Consulta inicial">
          </div>
          <div class="field">
            <label for="f-slug">Enlace</label>
            <div class="input-group">
              <span class="input-addon mono hide-sm" aria-hidden="true">/e/</span>
              <input class="input mono" type="text" id="f-slug" name="slug" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= e($v('slug')) ?>" placeholder="se-genera-solo" aria-describedby="slug-hint">
            </div>
            <p class="hint" id="slug-hint">Solo minúsculas, números y guiones. Si lo dejas vacío lo generamos con el nombre.</p>
          </div>
        </div>

        <div class="field">
          <label for="f-description">Descripción</label>
          <div class="p2-rt" role="group" aria-label="Formato del texto">
            <button class="btn btn-ghost btn-sm" type="button" data-wrap="**" aria-label="Negrita"><strong>N</strong></button>
            <button class="btn btn-ghost btn-sm" type="button" data-wrap="*" aria-label="Cursiva"><em>C</em></button>
            <button class="btn btn-ghost btn-sm" type="button" data-link aria-label="Insertar enlace"><?= icon('link') ?></button>
          </div>
          <textarea class="textarea" id="f-description" name="description" rows="5" maxlength="4000" placeholder="Cuenta qué incluye, qué debe traer el cliente, etc."><?= e($v('description')) ?></textarea>
          <p class="hint">Formato básico: **negrita**, *cursiva* y [texto](https://enlace.com).</p>
        </div>

        <div class="form-grid">
          <div class="field">
            <label for="f-color">Color del evento</label>
            <div class="row gap-2 p2-color">
              <input type="color" id="f-color" name="color" value="<?= e($v('color', '#C9A050')) ?>" class="p2-colorinput" aria-label="Elegir color">
              <?php foreach (['#C9A050', '#1F5C45', '#7A1F2B', '#3B5B8C', '#6B4C8F', '#B5651D', '#2E7D7A'] as $c) : ?>
                <button class="p2-swatch" type="button" data-color="<?= e($c) ?>" <?= vars(['--sw' => $c]) ?> aria-label="Usar el color <?= e($c) ?>"></button>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="field">
            <span class="p2-label">Estado</span>
            <?= $sw('active', 'Evento activo y visible para reservar', (int) $v('active', 1) === 1) ?>
            <p class="hint">Desactívalo para trabajar en borrador.</p>
          </div>
        </div>

        <fieldset class="fieldset">
          <legend>Tipo de evento</legend>
          <div class="p2-radios" role="radiogroup">
            <?php foreach (A2Controller::KINDS as $k => $label) : ?>
              <label class="p2-radio">
                <input type="radio" name="kind" value="<?= e($k) ?>"<?= chk($v('kind', 'individual') === $k) ?>>
                <span class="p2-radio-body"><strong><?= e($label) ?></strong><small><?= e($kindHelp[$k]) ?></small></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="form-grid p2-kindopts">
            <div class="field" data-show-kind="group">
              <label for="f-capacity">Cupos por horario</label>
              <input class="input" type="number" id="f-capacity" name="capacity" min="1" max="1000" value="<?= (int) $v('capacity', 1) ?>">
              <p class="hint">Cuántas personas pueden reservar el mismo horario.</p>
            </div>
            <div class="field" data-show-kind="round_robin">
              <label for="f-rr">Cómo se reparten las citas</label>
              <select class="select" id="f-rr" name="rr_mode">
                <option value="equitable"<?= sel($v('rr_mode'), 'equitable') ?>>Equitativo: al que menos citas lleva</option>
                <option value="weighted"<?= sel($v('rr_mode'), 'weighted') ?>>Ponderado: según el peso de cada anfitrión</option>
                <option value="priority"<?= sel($v('rr_mode'), 'priority') ?>>Por prioridad: primero el de mayor prioridad</option>
              </select>
            </div>
          </div>
        </fieldset>

        <fieldset class="fieldset">
          <legend>Serie de sesiones</legend>
          <div class="form-grid">
            <div class="field">
              <label for="f-series">Sesiones por reserva</label>
              <input class="input" type="number" id="f-series" name="series_sessions" min="1" max="52" value="<?= (int) $v('series_sessions', 1) ?>">
              <p class="hint">Con 1 es una cita normal. Con más, se agendan varias sesiones seguidas.</p>
            </div>
            <div class="field">
              <label for="f-series-int">Días entre sesiones</label>
              <input class="input" type="number" id="f-series-int" name="series_interval_days" min="1" max="90" value="<?= (int) $v('series_interval_days', 7) ?>">
            </div>
          </div>
        </fieldset>

        <fieldset class="fieldset">
          <legend>Modalidad</legend>
          <div class="chips p2-modes" role="radiogroup" aria-label="Modalidad">
            <?php foreach (A2Controller::MODES as $m => $label) : ?>
              <label class="p2-chipradio"><input type="radio" name="mode" value="<?= e($m) ?>"<?= chk($v('mode', 'in_person') === $m) ?>><span><?= icon($modeIcon[$m]) ?> <?= e($label) ?></span></label>
            <?php endforeach; ?>
          </div>
          <div class="stack p2-modeopts">
            <div class="field" data-show-mode="in_person,home,phone">
              <label for="f-location" data-mode-label>Dirección o lugar</label>
              <input class="input" type="text" id="f-location" name="location" maxlength="255" value="<?= e($v('location')) ?>" placeholder="Ej. 6a avenida 12-34, zona 1, Ciudad de Guatemala">
              <p class="hint" data-mode-hint></p>
            </div>
            <div class="field" data-show-mode="video_auto">
              <p class="alert alert-info"><?= e($modeHelp['video_auto']) ?></p>
            </div>
            <div class="field" data-show-mode="video_custom">
              <label for="f-video">Enlace de la videollamada</label>
              <input class="input" type="url" id="f-video" name="video_url" maxlength="500" value="<?= e($v('video_url')) ?>" placeholder="https://…">
              <p class="hint">Pega el enlace fijo de tu sala (Meet, Zoom, Teams u otra).</p>
            </div>
            <div class="field" data-show-mode="home">
              <label for="f-travel">Tiempo de traslado (minutos)</label>
              <input class="input" type="number" id="f-travel" name="travel_minutes" min="0" max="240" value="<?= (int) $v('travel_minutes', 0) ?>">
              <p class="hint">Se reserva antes y después de la cita para que llegues a tiempo.</p>
            </div>
          </div>
        </fieldset>
      </section>

      <!-- DURACIÓN -->
      <section class="p2-tabpanel stack" role="tabpanel" id="panel-duracion" aria-labelledby="tab-duracion">
        <fieldset class="fieldset">
          <legend>Duraciones que puede elegir el invitado</legend>
          <div class="p2-pills" data-p2-durations>
            <?php foreach ($presets as $d) : ?>
              <label class="p2-pill"><input type="checkbox" name="durations[]" value="<?= (int) $d ?>"<?= chk(in_array($d, $durations, true)) ?>><span><?= e(\App\Core\Fmt::duration($d)) ?></span></label>
            <?php endforeach; ?>
          </div>
          <div class="form-grid">
            <div class="field">
              <label for="f-extra">Otras duraciones (minutos)</label>
              <input class="input mono" type="text" id="f-extra" name="duration_extra" inputmode="numeric" maxlength="60" value="<?= e(implode(', ', $extra)) ?>" placeholder="Ej. 25, 75">
              <p class="hint">Sepáralas con comas. Entre 5 y 720 minutos.</p>
            </div>
            <div class="field">
              <label for="f-defdur">Duración sugerida</label>
              <select class="select" id="f-defdur" name="default_duration" data-default="<?= (int) $v('default_duration', 30) ?>">
                <?php foreach ($durations as $d) : ?><option value="<?= (int) $d ?>"<?= sel($v('default_duration', 30), $d) ?>><?= e(\App\Core\Fmt::duration((int) $d)) ?></option><?php endforeach; ?>
              </select>
              <p class="hint">Es la que aparece marcada al abrir la página de reserva.</p>
            </div>
          </div>
        </fieldset>
      </section>

      <!-- DISPONIBILIDAD -->
      <section class="p2-tabpanel stack" role="tabpanel" id="panel-disponibilidad" aria-labelledby="tab-disponibilidad">
        <div class="field">
          <label for="f-schedule">Horario de atención</label>
          <select class="select" id="f-schedule" name="schedule_id">
            <option value="">El horario de cada anfitrión</option>
            <?php foreach ($schedules as $s) : ?><option value="<?= (int) $s['id'] ?>"<?= sel($v('schedule_id'), $s['id']) ?>><?= e($s['name']) ?><?= (int) $s['is_default'] === 1 ? ' (predeterminado)' : '' ?> · <?= e($s['timezone']) ?></option><?php endforeach; ?>
          </select>
          <p class="hint">Los horarios se crean y editan en <a href="<?= e(url('/admin/horarios')) ?>">Horarios</a>.</p>
        </div>
        <div class="form-grid">
          <div class="field">
            <label for="f-notice">Anticipación mínima</label>
            <div class="input-group">
              <input class="input" type="number" id="f-notice" name="min_notice_value" min="0" max="100000" value="<?= (int) $v('min_notice_value', 2) ?>">
              <select class="select" name="min_notice_unit" aria-label="Unidad de la anticipación mínima">
                <option value="minutes"<?= sel($v('min_notice_unit'), 'minutes') ?>>minutos</option>
                <option value="hours"<?= sel($v('min_notice_unit'), 'hours') ?>>horas</option>
                <option value="days"<?= sel($v('min_notice_unit'), 'days') ?>>días</option>
              </select>
            </div>
            <p class="hint">Cuánto antes de la cita deben reservar como mínimo.</p>
          </div>
          <div class="field">
            <label for="f-advance">Anticipación máxima (días)</label>
            <input class="input" type="number" id="f-advance" name="max_advance_days" min="1" max="730" value="<?= (int) $v('max_advance_days', 60) ?>">
            <p class="hint">Con cuántos días de adelanto pueden reservar como máximo.</p>
          </div>
          <div class="field">
            <label for="f-wstart">Disponible desde</label>
            <input class="input" type="date" id="f-wstart" name="window_start" value="<?= e((string) $v('window_start', '')) ?>">
          </div>
          <div class="field">
            <label for="f-wend">Disponible hasta</label>
            <input class="input" type="date" id="f-wend" name="window_end" value="<?= e((string) $v('window_end', '')) ?>">
            <p class="hint">Opcional: úsalo para campañas o temporadas.</p>
          </div>
          <div class="field">
            <label for="f-interval">Intervalo entre horarios</label>
            <select class="select" id="f-interval" name="slot_interval">
              <?php foreach ($slotOptions as $o) : ?><option value="<?= $o ?>"<?= sel($v('slot_interval', 30), $o) ?>>Cada <?= e(\App\Core\Fmt::duration($o)) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field p2-hidden-spacer" aria-hidden="true"></div>
          <div class="field">
            <label for="f-bb">Margen antes de la cita (min)</label>
            <input class="input" type="number" id="f-bb" name="buffer_before" min="0" max="240" value="<?= (int) $v('buffer_before', 0) ?>">
          </div>
          <div class="field">
            <label for="f-ba">Margen después de la cita (min)</label>
            <input class="input" type="number" id="f-ba" name="buffer_after" min="0" max="240" value="<?= (int) $v('buffer_after', 0) ?>">
          </div>
          <div class="field">
            <label for="f-dl">Máximo de citas por día</label>
            <input class="input" type="number" id="f-dl" name="daily_limit" min="1" max="1000" value="<?= e((string) ($v('daily_limit') ?? '')) ?>" placeholder="Sin límite">
          </div>
          <div class="field">
            <label for="f-wl">Máximo de citas por semana</label>
            <input class="input" type="number" id="f-wl" name="weekly_limit" min="1" max="5000" value="<?= e((string) ($v('weekly_limit') ?? '')) ?>" placeholder="Sin límite">
          </div>
        </div>
        <?= $sw('respect_holidays', 'No ofrecer horarios en feriados de Guatemala', (int) $v('respect_holidays', 1) === 1) ?>
      </section>

      <!-- ANFITRIONES Y RECURSOS -->
      <section class="p2-tabpanel stack" role="tabpanel" id="panel-equipo" aria-labelledby="tab-equipo">
        <?php if (!$hosts) : ?>
          <div class="empty"><?= icon('user') ?><h2 class="empty-title">Todavía no hay anfitriones</h2><p class="empty-text">Agrega al menos uno para poder activar el evento.</p><a class="btn btn-gold" href="<?= e(url('/admin/anfitriones/nuevo')) ?>">Crear anfitrión</a></div>
        <?php else : ?>
          <?php if ($teams) : ?>
            <div class="field">
              <label for="f-team">Equipo</label>
              <select class="select" id="f-team" name="team_id" data-team-hosts="<?= ej($team_hosts) ?>">
                <option value="">Sin equipo</option>
                <?php foreach ($teams as $t) : ?><option value="<?= (int) $t['id'] ?>"<?= sel($v('team_id'), $t['id']) ?>><?= e($t['name']) ?></option><?php endforeach; ?>
              </select>
              <p class="hint">Al elegir un equipo se marcan sus anfitriones; luego puedes ajustar la lista.</p>
            </div>
          <?php endif; ?>
          <fieldset class="fieldset">
            <legend>Anfitriones que atienden este evento</legend>
            <p class="hint" data-hosts-hint></p>
            <ul class="p2-hostlist">
              <?php foreach ($hosts as $h) :
                  $on = isset($selHosts[(int) $h['id']]);
                  $cfg = $selHosts[(int) $h['id']] ?? ['weight' => 1, 'priority' => 1]; ?>
                <li class="p2-hostrow<?= (int) $h['active'] === 0 ? ' is-off' : '' ?>">
                  <label class="check">
                    <input type="checkbox" name="hosts[<?= (int) $h['id'] ?>][on]" value="1" data-host-id="<?= (int) $h['id'] ?>"<?= chk($on) ?>>
                    <span class="p2-hostname"><span class="p2-dot" <?= vars(['--c' => (string) $h['color']]) ?>></span><strong><?= e($h['name']) ?></strong><?php if (!empty($h['title'])) : ?><small class="muted"><?= e($h['title']) ?></small><?php endif; ?><?php if ((int) $h['active'] === 0) : ?><span class="badge badge-muted">Inactivo</span><?php endif; ?></span>
                  </label>
                  <div class="p2-hostopts" data-show-kind="round_robin">
                    <label class="p2-mini">Peso <input class="input" type="number" min="1" max="100" name="hosts[<?= (int) $h['id'] ?>][weight]" value="<?= (int) $cfg['weight'] ?>" aria-label="Peso de <?= e($h['name']) ?>"></label>
                    <label class="p2-mini">Prioridad <input class="input" type="number" min="1" max="100" name="hosts[<?= (int) $h['id'] ?>][priority]" value="<?= (int) $cfg['priority'] ?>" aria-label="Prioridad de <?= e($h['name']) ?>"></label>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </fieldset>
        <?php endif; ?>
        <fieldset class="fieldset">
          <legend>Salas y recursos</legend>
          <?php if (!$resources) : ?>
            <p class="muted">No hay salas todavía. Si tu servicio las necesita, créalas en <a href="<?= e(url('/admin/recursos')) ?>">Recursos y salas</a>.</p>
          <?php else : ?>
            <p class="hint">Si eliges una o más salas, cada cita ocupa una de ellas mientras dure.</p>
            <div class="p2-checklist">
              <?php foreach ($resources as $r) : ?>
                <label class="check"><input type="checkbox" name="resources[]" value="<?= (int) $r['id'] ?>"<?= chk(in_array((int) $r['id'], $selRes, true)) ?>><span><?= e($r['name']) ?> <small class="muted">· capacidad <?= (int) $r['capacity'] ?><?= (int) $r['active'] === 0 ? ' · inactiva' : '' ?></small></span></label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </fieldset>
      </section>

      <!-- PREGUNTAS -->
      <section class="p2-tabpanel stack" role="tabpanel" id="panel-preguntas" aria-labelledby="tab-preguntas">
        <p class="muted">Lo que le preguntas al cliente al reservar, además de nombre, correo y teléfono. Arrastra para ordenar o usa las flechas. Las preguntas marcadas «Todos los eventos» se muestran en cada evento.</p>
        <div class="p2-qlist" data-p2-questions data-next="<?= count($questions) ?>" aria-live="polite">
          <?php foreach ($questions as $i => $q) : ?>
            <?= \App\Core\View::partial('admin/events/question', ['q' => $q, 'i' => $i, 'types' => EventsController::FIELD_TYPES]) ?>
          <?php endforeach; ?>
        </div>
        <div class="empty p2-qempty" data-q-empty<?= $questions ? ' hidden' : '' ?>>
          <?= icon('list') ?>
          <p class="empty-text">Sin preguntas adicionales. Agrega una si necesitas, por ejemplo, el motivo de la consulta.</p>
        </div>
        <div><button class="btn btn-outline" type="button" data-q-add><?= icon('plus') ?> Agregar pregunta</button></div>
        <template id="p2-q-template"><?= \App\Core\View::partial('admin/events/question', ['q' => ['id' => '', 'scope' => 'event', 'name' => '', 'label' => '', 'type' => 'text', 'options' => '', 'help' => '', 'required' => 0, 'condition_field' => '', 'condition_value' => '', 'active' => 1, 'del' => 0], 'i' => '__I__', 'types' => EventsController::FIELD_TYPES]) ?></template>
      </section>

      <!-- RESERVA Y PAGOS -->
      <section class="p2-tabpanel stack" role="tabpanel" id="panel-reserva" aria-labelledby="tab-reserva">
        <?= $sw('approval', 'Aprobar cada reserva manualmente antes de confirmarla', (int) $v('approval', 0) === 1) ?>

        <fieldset class="fieldset">
          <legend>Precio y anticipo</legend>
          <div class="form-grid">
            <div class="field">
              <label for="f-price">Precio (<?= e((string) setting('currency_symbol', 'Q')) ?>)</label>
              <input class="input mono" type="text" inputmode="decimal" id="f-price" name="price" maxlength="14" value="<?= (float) $v('price', 0) > 0 ? e(number_format((float) $v('price'), 2, '.', '')) : '' ?>" placeholder="0.00 (gratis)">
            </div>
            <div class="field">
              <label for="f-dep">Anticipo</label>
              <select class="select" id="f-dep" name="deposit_type">
                <option value="none"<?= sel($v('deposit_type'), 'none') ?>>Sin anticipo</option>
                <option value="fixed"<?= sel($v('deposit_type'), 'fixed') ?>>Monto fijo</option>
                <option value="percent"<?= sel($v('deposit_type'), 'percent') ?>>Porcentaje del precio</option>
              </select>
            </div>
            <div class="field" data-show-deposit>
              <label for="f-depv" data-deposit-label>Monto del anticipo</label>
              <input class="input mono" type="text" inputmode="decimal" id="f-depv" name="deposit_value" maxlength="14" value="<?= (float) $v('deposit_value', 0) > 0 ? e(number_format((float) $v('deposit_value'), 2, '.', '')) : '' ?>">
            </div>
          </div>
          <p class="hint"><?= e($paymentsNote) ?></p>
        </fieldset>

        <fieldset class="fieldset">
          <legend>Política de cancelación</legend>
          <div class="form-grid">
            <div class="field">
              <label for="f-ch">Horas mínimas para cancelar o reprogramar</label>
              <input class="input" type="number" id="f-ch" name="cancel_hours" min="0" max="8760" value="<?= (int) $v('cancel_hours', 24) ?>">
              <p class="hint">Con 0, el cliente puede cancelar en cualquier momento.</p>
            </div>
          </div>
          <div class="field">
            <label for="f-cp">Texto de la política</label>
            <textarea class="textarea" id="f-cp" name="cancel_policy_text" rows="3" maxlength="2000" placeholder="Ej. Las cancelaciones con menos de 24 horas pierden el anticipo."><?= e($v('cancel_policy_text')) ?></textarea>
          </div>
        </fieldset>

        <fieldset class="fieldset">
          <legend>Después de reservar</legend>
          <div class="field">
            <label for="f-cm">Mensaje de confirmación</label>
            <textarea class="textarea" id="f-cm" name="confirm_message" rows="3" maxlength="2000" placeholder="Ej. Llega 10 minutos antes y trae tu DPI."><?= e($v('confirm_message')) ?></textarea>
          </div>
          <div class="field">
            <label for="f-ru">Redirigir a una dirección (opcional)</label>
            <input class="input" type="url" id="f-ru" name="redirect_url" maxlength="500" value="<?= e($v('redirect_url')) ?>" placeholder="https://tusitio.com/gracias">
            <p class="hint">Si lo llenas, el cliente va allí en lugar de ver la pantalla de confirmación.</p>
          </div>
        </fieldset>

        <fieldset class="fieldset">
          <legend>Opciones para el cliente</legend>
          <div class="stack">
            <?= $sw('require_phone', 'Pedir teléfono como obligatorio', (int) $v('require_phone', 1) === 1) ?>
            <?= $sw('allow_coupon', 'Permitir cupones y certificados de regalo', (int) $v('allow_coupon', 1) === 1) ?>
            <?= $sw('allow_guests', 'Permitir invitados adicionales', (int) $v('allow_guests', 0) === 1, 'data-toggle-guests') ?>
            <div class="field" data-show-guests>
              <label for="f-mg">Máximo de invitados adicionales</label>
              <input class="input" type="number" id="f-mg" name="max_guests" min="0" max="50" value="<?= (int) $v('max_guests', 0) ?>">
            </div>
          </div>
        </fieldset>

        <fieldset class="fieldset">
          <legend>Visibilidad</legend>
          <div class="p2-radios" role="radiogroup">
            <label class="p2-radio"><input type="radio" name="visibility" value="public"<?= chk($v('visibility', 'public') === 'public') ?>><span class="p2-radio-body"><strong>Público</strong><small>Aparece en tu página de reservas.</small></span></label>
            <label class="p2-radio"><input type="radio" name="visibility" value="secret"<?= chk($v('visibility') === 'secret') ?>><span class="p2-radio-body"><strong>Secreto</strong><small>Solo lo ve quien tenga el enlace.</small></span></label>
          </div>
          <?= $sw('single_use', 'Enlace de un solo uso (se desactiva tras la primera reserva)', (int) $v('single_use', 0) === 1, 'data-toggle-single') ?>
          <div class="field" data-show-single>
            <label for="f-exp">Fecha límite del enlace</label>
            <input class="input" type="date" id="f-exp" name="expires" value="<?= e((string) $v('expires_local', '')) ?>">
            <p class="hint">Opcional. Zona horaria del negocio: <?= e($biz['tz']) ?>.</p>
          </div>
        </fieldset>
      </section>

      <div class="form-actions p2-sticky">
        <button class="btn btn-gold btn-lg" type="submit"><?= icon('check') ?> <?= $id ? 'Guardar cambios' : 'Crear evento' ?></button>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('/admin/eventos')) ?>">Cancelar</a>
      </div>
    </form>

    <aside class="p2-preview" aria-label="Vista previa de la página de reserva">
      <p class="p2-preview-title"><?= icon('eye') ?> Vista previa en vivo</p>
      <article class="card card-gold p2-pcard" id="ev-preview" <?= vars(['--ev' => (string) $v('color', '#C9A050')]) ?>>
        <div class="p2-pbar" aria-hidden="true"></div>
        <div class="card-body stack">
          <p class="p2-pbrand"><?= e($biz['name']) ?></p>
          <h2 class="p2-ptitle serif" data-pv="name">Nombre del evento</h2>
          <div class="p2-pdesc" data-pv="description"></div>
          <ul class="p2-pmeta">
            <li><?= icon('clock') ?><span data-pv="durations">30 min</span></li>
            <li><?= icon('map-pin') ?><span data-pv="mode">Presencial</span></li>
            <li data-pv-row="price"><?= icon('dollar') ?><span data-pv="price"></span></li>
          </ul>
          <div class="p2-pchips" data-pv="chips" aria-hidden="true"></div>
          <span class="btn btn-gold btn-block p2-pbtn" aria-hidden="true">Elegir horario</span>
        </div>
      </article>
      <p class="hint p2-preview-note">Se actualiza mientras escribes; no se guarda hasta que pulses «<?= $id ? 'Guardar cambios' : 'Crear evento' ?>».</p>
    </aside>
  </div>
</div>
