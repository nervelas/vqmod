<?php
/** Asistente de inicio. Variables: $n, $steps, $errors, $old, $reached, $done + datos del paso. */
$fieldErr = static function (array $errors, string $key): string {
    return isset($errors[$key]) ? '<p class="error" id="err-' . e($key) . '" role="alert">' . e($errors[$key]) . '</p>' : '';
};
$pct = (int) round((($n - 1) / (count($steps) - 1)) * 100);
?>
<div class="page p4-page p4-wizard">
  <div class="page-head">
    <div>
      <h1 class="page-title">Asistente de inicio</h1>
      <p class="page-sub">Cinco pasos para dejar tu agenda lista. Puedes saltar cualquier paso y retomarlo cuando quieras.<?= $done ? ' Ya lo completaste: puedes repetirlo si quieres ajustar algo.' : '' ?></p>
    </div>
    <div class="page-actions"><a class="btn btn-ghost" href="<?= e(url('/admin')) ?>">Salir del asistente</a></div>
  </div>

  <nav class="p4-stepper" aria-label="Progreso del asistente">
    <div class="progress p4-stepper-bar" role="progressbar" aria-valuemin="1" aria-valuemax="<?= count($steps) ?>" aria-valuenow="<?= (int) $n ?>" aria-label="Paso <?= (int) $n ?> de <?= count($steps) ?>"><span <?= vars(['--p' => $pct]) ?>></span></div>
    <ol class="p4-steps-list">
      <?php foreach ($steps as $i => $label) :
          $cls = $i === $n ? 'is-current' : ($i < $reached || $i < $n ? 'is-done' : ''); ?>
        <li class="p4-step <?= e($cls) ?>"<?= $i === $n ? ' aria-current="step"' : '' ?>>
          <a href="<?= e(url('/admin/asistente', ['paso' => $i])) ?>"><span class="p4-step-num mono"><?= $i < $n ? icon('check') : (int) $i ?></span><span class="p4-step-label"><?= e($label) ?></span></a>
        </li>
      <?php endforeach; ?>
    </ol>
  </nav>

  <?php if ($errors) : ?><div class="alert alert-err" role="alert">Revisa lo marcado antes de continuar. No se guardó nada de este paso.</div><?php endif; ?>

  <section class="card card-gold p4-step-card" aria-labelledby="h-step">
    <div class="card-head"><h2 id="h-step" class="serif">Paso <?= (int) $n ?> de <?= count($steps) ?> · <?= e($steps[$n]) ?></h2></div>

    <?php if ($n === 1) : ?>
      <form method="post" action="<?= e(url('/admin/asistente/1')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="card-body stack">
          <p class="muted">Elige tu profesión y dejamos listos los eventos, las preguntas del formulario, los recordatorios y las palabras que usa tu sector. Todo se puede cambiar después.</p>
          <?php if (!$professions) : ?>
            <div class="empty"><?= icon('info') ?><p class="empty-title">El catálogo de profesiones no está disponible</p><p class="empty-text">Puedes continuar y configurar tus eventos manualmente.</p></div>
          <?php else : ?>
            <fieldset class="fieldset p4-profs" aria-describedby="<?= isset($errors['profession']) ? 'err-profession' : '' ?>">
              <legend class="sr-only">Profesión</legend>
              <div class="grid cols-3">
                <?php foreach ($professions as $key => $pr) : ?>
                  <label class="p4-prof<?= $picked === $key ? ' is-picked' : '' ?>">
                    <input class="sr-only" type="radio" name="profession" value="<?= e($key) ?>"<?= chk($picked === $key) ?> data-prof-radio>
                    <span class="p4-prof-icon"><?= icon((string) ($pr['icon'] ?? 'sparkle')) ?></span>
                    <span class="p4-prof-name serif"><?= e($pr['name']) ?></span>
                    <span class="p4-prof-short muted"><?= e($pr['short']) ?></span>
                    <span class="p4-prof-meta mono"><?= (int) ($pr['counts']['events'] ?? 0) ?> eventos · <?= (int) ($pr['counts']['fields'] ?? 0) ?> preguntas</span>
                    <?php if ($current === $key) : ?><span class="badge badge-gold p4-prof-badge">Actual</span><?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>
            <?= $fieldErr($errors, 'profession') ?>
            <label class="check"><input type="checkbox" name="demo" value="1"<?= chk($demo) ?>><span><strong>Agregar datos de ejemplo</strong> (clientes y citas ficticias) para explorar el sistema. Podrás borrarlos después.</span></label>
            <p class="hint">Aplicarlo no borra nada de lo que ya tienes: solo agrega lo que falta.</p>
          <?php endif; ?>
        </div>
        <div class="card-foot row row-between row-wrap">
          <span></span>
          <div class="row row-wrap gap-2">
            <a class="btn btn-ghost" href="<?= e(url('/admin/asistente', ['paso' => 2])) ?>">Saltar este paso</a>
            <button class="btn btn-gold" type="submit"<?= $professions ? '' : ' disabled' ?>>Aplicar y continuar<?= icon('arrow-right') ?></button>
          </div>
        </div>
      </form>

    <?php elseif ($n === 2) : ?>
      <form method="post" action="<?= e(url('/admin/asistente/2')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="card-body stack">
          <p class="muted">Activa los servicios que sí ofreces y ajusta su duración y precio (<?= e((string) setting('currency_symbol', 'Q')) ?>). Para cambios finos, cada evento tiene su propia página de edición.</p>
          <?php if (!$events) : ?>
            <div class="empty"><?= icon('layers') ?><p class="empty-title">Todavía no hay eventos</p><p class="empty-text">Vuelve al paso 1 para cargar los eventos sugeridos de tu profesión, o créalos a mano más tarde.</p><a class="btn btn-outline" href="<?= e(url('/admin/asistente', ['paso' => 1])) ?>">Ir al paso 1</a></div>
          <?php else : ?>
            <div class="table-wrap">
              <table class="table p4-events">
                <thead><tr><th scope="col">Activo</th><th scope="col">Evento</th><th scope="col">Duración (min)</th><th scope="col">Precio</th></tr></thead>
                <tbody>
                  <?php foreach ($events as $ev) : $id = (int) $ev['id'];
                      $o = $old['ev'][$id] ?? null;
                      $active = $o !== null ? !empty($o['active']) : (int) $ev['active'] === 1;
                      $dur = $o['duration'] ?? (string) $ev['default_duration'];
                      $price = $o['price'] ?? (string) (float) $ev['price']; ?>
                    <tr>
                      <td data-label="Activo"><label class="switch"><input type="checkbox" name="ev[<?= $id ?>][active]" value="1"<?= chk($active) ?> aria-label="Activar <?= e($ev['name']) ?>"><span></span></label></td>
                      <th scope="row" data-label="Evento"><?= e($ev['name']) ?></th>
                      <td data-label="Duración">
                        <input class="input mono p4-num" type="number" min="5" max="480" step="5" name="ev[<?= $id ?>][duration]" value="<?= e($dur) ?>" aria-label="Duración de <?= e($ev['name']) ?> en minutos"<?= isset($errors['ev_' . $id . '_duration']) ? ' aria-invalid="true"' : '' ?>>
                        <?= $fieldErr($errors, 'ev_' . $id . '_duration') ?>
                      </td>
                      <td data-label="Precio">
                        <input class="input mono p4-num" type="text" inputmode="decimal" name="ev[<?= $id ?>][price]" value="<?= e($price) ?>" aria-label="Precio de <?= e($ev['name']) ?>"<?= isset($errors['ev_' . $id . '_price']) ? ' aria-invalid="true"' : '' ?>>
                        <?= $fieldErr($errors, 'ev_' . $id . '_price') ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
        <div class="card-foot row row-between row-wrap">
          <a class="btn btn-ghost" href="<?= e(url('/admin/asistente', ['paso' => 1])) ?>"><?= icon('arrow-left') ?>Volver</a>
          <div class="row row-wrap gap-2">
            <a class="btn btn-ghost" href="<?= e(url('/admin/asistente', ['paso' => 3])) ?>">Saltar este paso</a>
            <button class="btn btn-gold" type="submit"<?= $events ? '' : ' disabled' ?>>Guardar y continuar<?= icon('arrow-right') ?></button>
          </div>
        </div>
      </form>

    <?php elseif ($n === 3) : ?>
      <form method="post" action="<?= e(url('/admin/asistente/3')) ?>" novalidate data-schedule-form>
        <?= csrf_field() ?>
        <div class="card-body stack">
          <p class="muted">Marca los días que atiendes y el horario de cada uno. Usa un segundo bloque para separar la hora de almuerzo. Zona horaria: <strong><?= e($tz) ?></strong>. Este es tu horario general; cada persona del equipo puede tener el suyo más adelante.</p>
          <?= $fieldErr($errors, 'd_all') ?>
          <div class="p4-week">
            <?php foreach ($days as $d => $row) : ?>
              <div class="p4-day<?= $row['on'] ? ' is-on' : '' ?>" data-day>
                <label class="check p4-day-name"><input type="checkbox" name="d[<?= (int) $d ?>][on]" value="1"<?= chk($row['on']) ?> data-day-toggle><span><?= e($dayNames[$d]) ?></span></label>
                <div class="p4-blocks" role="group" aria-label="Horario del <?= e(mb_strtolower($dayNames[$d])) ?>">
                  <?php foreach ($row['blocks'] as $i => $b) : ?>
                    <div class="p4-block">
                      <input class="input mono" type="time" name="d[<?= (int) $d ?>][b][<?= (int) $i ?>][s]" value="<?= e($b['s']) ?>" aria-label="<?= e($dayNames[$d]) ?>, bloque <?= $i + 1 ?>, desde" data-day-input<?= $row['on'] ? '' : ' disabled' ?>>
                      <span class="muted" aria-hidden="true">a</span>
                      <input class="input mono" type="time" name="d[<?= (int) $d ?>][b][<?= (int) $i ?>][e]" value="<?= e($b['e']) ?>" aria-label="<?= e($dayNames[$d]) ?>, bloque <?= $i + 1 ?>, hasta" data-day-input<?= $row['on'] ? '' : ' disabled' ?>>
                    </div>
                  <?php endforeach; ?>
                </div>
                <span class="muted p4-closed" data-closed<?= $row['on'] ? ' hidden' : '' ?>>Cerrado</span>
                <?= $fieldErr($errors, 'd_' . $d) ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="card-foot row row-between row-wrap">
          <a class="btn btn-ghost" href="<?= e(url('/admin/asistente', ['paso' => 2])) ?>"><?= icon('arrow-left') ?>Volver</a>
          <div class="row row-wrap gap-2">
            <a class="btn btn-ghost" href="<?= e(url('/admin/asistente', ['paso' => 4])) ?>">Saltar este paso</a>
            <button class="btn btn-gold" type="submit">Guardar y continuar<?= icon('arrow-right') ?></button>
          </div>
        </div>
      </form>

    <?php elseif ($n === 4) : ?>
      <form method="post" action="<?= e(url('/admin/asistente/4')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="card-body stack">
          <p class="muted">Así se presentará quien atiende en tu página de reservas.</p>
          <?php if (!$host) : ?>
            <div class="empty"><?= icon('user') ?><p class="empty-title">Aún no hay anfitriones</p><p class="empty-text">Crea el primero desde la sección Equipo.</p><a class="btn btn-outline" href="<?= e(url('/admin/anfitriones')) ?>">Ir a anfitriones</a></div>
          <?php else : ?>
            <div class="form-grid">
              <div class="field">
                <label for="f-host_name">Nombre de quien atiende</label>
                <input class="input" type="text" id="f-host_name" name="host_name" maxlength="120" required value="<?= e($old['host_name'] ?? $host['name']) ?>"<?= isset($errors['host_name']) ? ' aria-invalid="true" aria-describedby="err-host_name"' : '' ?>>
                <?= $fieldErr($errors, 'host_name') ?>
              </div>
              <div class="field">
                <label for="f-host_title">Cargo o especialidad</label>
                <input class="input" type="text" id="f-host_title" name="host_title" maxlength="160" value="<?= e($old['host_title'] ?? (string) $host['title']) ?>" placeholder="Ej.: Psicóloga clínica"<?= isset($errors['host_title']) ? ' aria-invalid="true" aria-describedby="err-host_title"' : '' ?>>
                <?= $fieldErr($errors, 'host_title') ?>
              </div>
            </div>
          <?php endif; ?>
          <?php if ($others) : ?>
            <div>
              <h3 class="serif">Otras personas en tu equipo</h3>
              <ul class="p4-people"><?php foreach ($others as $o) : ?><li><span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $o['name'], 0, 1))) ?></span><span><?= e($o['name']) ?><?= $o['title'] ? ' <span class="muted">· ' . e($o['title']) . '</span>' : '' ?></span><?= (int) $o['active'] === 1 ? '' : ' <span class="badge badge-muted">Inactivo</span>' ?></li><?php endforeach; ?></ul>
            </div>
          <?php endif; ?>
          <div class="alert alert-info" role="note"><?= icon('users') ?><span>¿Trabajas con más <?= e($hostLabel) ?>s o con recepción? Invítalos desde <a class="text-gold" href="<?= e(url('/admin/usuarios')) ?>">Usuarios y roles</a> o crea sus fichas en <a class="text-gold" href="<?= e(url('/admin/anfitriones')) ?>">Anfitriones</a>. Tu avance de este asistente queda guardado.</span></div>
        </div>
        <div class="card-foot row row-between row-wrap">
          <a class="btn btn-ghost" href="<?= e(url('/admin/asistente', ['paso' => 3])) ?>"><?= icon('arrow-left') ?>Volver</a>
          <div class="row row-wrap gap-2">
            <a class="btn btn-ghost" href="<?= e(url('/admin/asistente', ['paso' => 5])) ?>">Saltar este paso</a>
            <button class="btn btn-gold" type="submit"<?= $host ? '' : ' disabled' ?>>Guardar y continuar<?= icon('arrow-right') ?></button>
          </div>
        </div>
      </form>

    <?php else : ?>
      <div class="card-body stack">
        <p class="muted">Tu agenda ya tiene dirección. Compártela por WhatsApp, redes sociales o imprime el código QR para tu local.</p>
        <div class="p4-share" data-qr-root data-url="<?= e($publicUrl) ?>" data-name="<?= e($business) ?>">
          <div class="p4-qr">
            <div class="p4-qr-box" data-qr-box role="img" aria-label="Código QR de tu página de reservas"><span class="muted" data-qr-fallback>Generando código QR…</span></div>
            <div class="row row-wrap gap-2 center">
              <button class="btn btn-gold btn-sm" type="button" data-qr-download="svg"><?= icon('download') ?>Descargar SVG</button>
              <button class="btn btn-outline btn-sm" type="button" data-qr-download="png"><?= icon('download') ?>Descargar PNG</button>
            </div>
          </div>
          <div class="stack">
            <div class="field">
              <label for="f-public">Enlace de tu página pública</label>
              <div class="input-group">
                <input class="input mono" id="f-public" type="text" readonly value="<?= e($publicUrl) ?>">
                <button class="btn btn-outline" type="button" data-copy="#f-public"><?= icon('copy') ?>Copiar</button>
              </div>
            </div>
            <?php if ($links) : ?>
              <div>
                <h3 class="serif">Enlaces directos por servicio</h3>
                <ul class="p4-links">
                  <?php foreach ($links as $i => $l) : ?>
                    <li>
                      <span><?= e($l['name']) ?></span>
                      <span class="input-group"><input class="input mono" id="f-link-<?= (int) $i ?>" type="text" readonly value="<?= e($l['url']) ?>" aria-label="Enlace de <?= e($l['name']) ?>"><button class="btn btn-ghost btn-icon" type="button" data-copy="#f-link-<?= (int) $i ?>" aria-label="Copiar enlace de <?= e($l['name']) ?>"><?= icon('copy') ?></button></span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
            <a class="btn btn-outline" href="<?= e(url('/admin/insertar')) ?>"><?= icon('code') ?>Insertar el botón de reservas en mi sitio web</a>
          </div>
        </div>
      </div>
      <div class="card-foot row row-between row-wrap">
        <a class="btn btn-ghost" href="<?= e(url('/admin/asistente', ['paso' => 4])) ?>"><?= icon('arrow-left') ?>Volver</a>
        <form method="post" action="<?= e(url('/admin/asistente/terminar')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-gold btn-lg" type="submit"><?= icon('check') ?>Terminar asistente</button>
        </form>
      </div>
    <?php endif; ?>
  </section>
</div>
