<?php
/** @var array $groups @var array $hosts @var bool $unlinked @var int $failing @var int $max */
$multi = count($hosts) > 1;
?>
<div class="page p2-page">
  <header class="page-head">
    <div>
      <h1 class="page-title">Calendarios externos</h1>
      <p class="page-sub">Conecta el calendario personal con un enlace privado: lo que ya tengas ocupado deja de ofrecerse para reservas.</p>
    </div>
  </header>

  <?php if ($unlinked) : ?>
    <div class="empty p2-empty"><?= icon('link') ?><h2 class="empty-title">Tu usuario aún no tiene ficha de anfitrión</h2><p class="empty-text">Pide a quien administra que vincule tu usuario a un anfitrión para conectar tus calendarios.</p></div>
  <?php else : ?>
    <?php if ($failing > 0) : ?>
      <div class="alert alert-err" role="alert"><strong><?= (int) $failing ?> <?= $failing === 1 ? 'calendario no se pudo' : 'calendarios no se pudieron' ?> actualizar.</strong> Mientras tanto se usa la última lectura buena, pero podrían ofrecerse horarios que ya tienes ocupados. Revisa el enlace abajo.</div>
    <?php endif; ?>

    <div class="p2-split">
      <div class="stack">
        <?php foreach ($groups as $g) : $h = $g['host']; ?>
          <section class="card">
            <div class="card-head row row-between"><h2 class="p2-h3"><?= e($h['name']) ?></h2><span class="muted"><?= count($g['calendars']) ?> <?= count($g['calendars']) === 1 ? 'calendario' : 'calendarios' ?></span></div>
            <?php if (!$g['calendars']) : ?>
              <div class="card-body"><div class="empty"><?= icon('link') ?><p class="empty-text">Sin calendarios conectados. Agrega uno desde el formulario.</p></div></div>
            <?php else : ?>
              <ul class="p2-list">
                <?php foreach ($g['calendars'] as $c) : ?>
                  <li class="p2-cal-item">
                    <div class="p2-cal-main">
                      <div class="row row-between row-wrap gap-2">
                        <strong><?= e($c['name']) ?></strong>
                        <?php if ((int) $c['active'] === 0) : ?><span class="badge badge-muted">En pausa</span>
                        <?php elseif ($c['last_status'] === null) : ?><span class="badge badge-warn">Sin sincronizar</span>
                        <?php elseif ($c['failed']) : ?><span class="badge badge-err">Con error</span>
                        <?php else : ?><span class="badge badge-ok">Al día</span><?php endif; ?>
                      </div>
                      <p class="muted mono p2-url"><?= e($c['masked']) ?></p>
                      <?php if ($c['last_ok'] !== '') : ?><p class="muted">Última lectura correcta: <?= e($c['last_ok']) ?></p><?php endif; ?>
                      <?php if ($c['failed'] && !empty($c['last_error'])) : ?>
                        <div class="alert alert-err" role="alert">No pudimos leer este calendario<?= $c['last_try'] !== '' ? ' (' . e($c['last_try']) . ')' : '' ?>: <?= e($c['last_error']) ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="p2-actions">
                      <form method="post" action="<?= e(url('/admin/calendarios/' . (int) $c['id'] . '/sincronizar')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit"><?= icon('refresh') ?> Sincronizar</button></form>
                      <form method="post" action="<?= e(url('/admin/calendarios/' . (int) $c['id'] . '/activo')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon((int) $c['active'] === 1 ? 'pause' : 'play') ?> <?= (int) $c['active'] === 1 ? 'Pausar' : 'Activar' ?></button></form>
                      <form method="post" action="<?= e(url('/admin/calendarios/' . (int) $c['id'] . '/eliminar')) ?>" class="inline" data-confirm="¿Quitar «<?= e($c['name']) ?>»? Sus eventos dejarán de bloquear horarios."><?= csrf_field() ?><button class="btn btn-ghost btn-sm text-err" type="submit"><?= icon('trash') ?> Quitar</button></form>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <div class="card-foot stack">
              <p class="muted"><strong>Tu agenda en tu calendario:</strong> suscribe este feed para ver estas citas en tu teléfono.</p>
              <div class="input-group">
                <input class="input mono" type="text" readonly id="feed-<?= (int) $h['id'] ?>" value="<?= e($g['feed']) ?>" aria-label="Feed de citas de <?= e($h['name']) ?>">
                <button class="btn btn-outline" type="button" data-copy="#feed-<?= (int) $h['id'] ?>"><?= icon('copy') ?> Copiar</button>
              </div>
            </div>
          </section>
        <?php endforeach; ?>
      </div>

      <div class="stack p2-side">
        <section class="card card-gold">
          <form method="post" action="<?= e(url('/admin/calendarios/guardar')) ?>" class="card-body stack" autocomplete="off">
            <?= csrf_field() ?>
            <h2 class="p2-h3">Conectar un calendario</h2>
            <?php if ($multi) : ?>
              <div class="field"><label for="cal-host">Anfitrión</label><select class="select" id="cal-host" name="host_id"><?php foreach ($hosts as $h) : ?><option value="<?= (int) $h['id'] ?>"><?= e($h['name']) ?></option><?php endforeach; ?></select></div>
            <?php elseif ($hosts) : ?>
              <input type="hidden" name="host_id" value="<?= (int) $hosts[0]['id'] ?>">
            <?php endif; ?>
            <div class="field"><label for="cal-name">Nombre</label><input class="input" type="text" id="cal-name" name="name" maxlength="120" required placeholder="Ej. Calendario personal"></div>
            <div class="field">
              <label for="cal-url">Enlace secreto (ICS)</label>
              <input class="input mono" type="url" id="cal-url" name="url" maxlength="2000" required placeholder="https://…/basic.ics">
              <p class="hint">Es privado: quien lo tenga puede ver tus eventos. Nunca lo publiques.</p>
            </div>
            <div class="alert" data-test-result hidden role="status"></div>
            <div class="form-actions">
              <button class="btn btn-outline" type="button" data-cal-test="<?= e(url('/admin/calendarios/probar')) ?>">Probar enlace</button>
              <button class="btn btn-gold" type="submit"><?= icon('check') ?> Conectar</button>
            </div>
          </form>
        </section>
        <section class="card"><div class="card-body stack">
          <h2 class="p2-h3">¿De dónde saco el enlace?</h2>
          <details class="p2-more" open><summary>Google Calendar</summary>
            <ol class="p2-steps"><li>En el computador, abre Configuración y elige tu calendario.</li><li>Baja a «Integrar el calendario».</li><li>Copia la «Dirección secreta en formato iCal».</li></ol>
          </details>
          <details class="p2-more"><summary>Outlook</summary>
            <ol class="p2-steps"><li>Entra a Configuración, «Calendario» y «Calendarios compartidos».</li><li>En «Publicar un calendario», elige el calendario y el permiso «Puede ver todos los detalles».</li><li>Publica y copia el enlace ICS.</li></ol>
          </details>
          <details class="p2-more"><summary>Apple Calendar (iCloud)</summary>
            <ol class="p2-steps"><li>En iCloud.com abre Calendario y toca el ícono de compartir junto al calendario.</li><li>Activa «Calendario público».</li><li>Copia el enlace y cambia «webcal://» por «https://» si hace falta.</li></ol>
          </details>
          <p class="hint">Se actualizan cada cierto tiempo, no al instante. Google Calendar, Outlook y Apple Calendar son marcas de sus respectivos dueños; sin afiliación.</p>
        </div></section>
      </div>
    </div>
  <?php endif; ?>
</div>
