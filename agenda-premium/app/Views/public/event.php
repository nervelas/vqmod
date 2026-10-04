<?php
/**
 * Página de reserva. Variables: $biz $event $hosts $durations $duration $hostPick $fields $consent $boot $tzGroups $embed
 * $captchaScript $captchaKey $captchaProvider $colorOverride
 */
$ev = $boot['event'];
$free = (float) $event['price'] <= 0;
$multiDur = count($durations) > 1;
$multiHost = count($hosts) > 1;
$isGroup = $ev['is_group'];
$policy = trim((string) ($event['cancel_policy_text'] ?? ''));
$cancelHours = (int) $event['cancel_hours'];
?>
<?php if ($colorOverride !== '') : ?><style nonce="<?= e(nonce()) ?>">:root{--gold-user:<?= e($colorOverride) ?>}</style><?php endif; ?>
<?php if (!$embed) { partial('public/_header', ['biz' => $biz]); } ?>
<main id="main" class="bk" data-bk <?= vars(['--ev' => $ev['color']]) ?>>
  <noscript><div class="alert alert-warn"><div>Para reservar necesitas activar JavaScript en tu navegador. También puedes escribirnos y te ayudamos a agendar.</div></div></noscript>
  <div class="bk-wrap">
    <aside class="bk-side" aria-label="Detalles de la cita">
      <div class="bk-side-card">
        <span class="bk-side-bar" aria-hidden="true"></span>
        <p class="eyebrow"><?= e($biz['name']) ?></p>
        <h1 class="bk-title serif"><?= e($event['name']) ?></h1>
        <ul class="bk-facts">
          <li><?= icon('clock') ?> <span data-fact-duration><?= e(\App\Core\Fmt::duration($duration)) ?></span></li>
          <li><?= icon(\App\Controllers\Pub\PubSupport::modeIcon((string) $event['mode'])) ?> <span><?= e($boot['event']['mode_label']) ?><?= $event['mode'] === 'in_person' && trim((string) $event['location']) !== '' ? ' · ' . e($event['location']) : '' ?></span></li>
          <li><?= icon('dollar') ?> <span class="mono" data-fact-price><?= $free ? 'Sin costo' : e(\App\Core\Fmt::money((float) $event['price'])) ?></span></li>
          <?php if ($ev['series_sessions'] > 1) : ?><li><?= icon('repeat') ?> <span><?= e((string) $ev['series_sessions']) ?> sesiones, una cada <?= e((string) $ev['series_interval_days']) ?> días</span></li><?php endif; ?>
          <?php if ($isGroup) : ?><li><?= icon('users') ?> <span>Sesión grupal</span></li><?php endif; ?>
          <?php if ($ev['approval']) : ?><li><?= icon('shield') ?> <span>Se confirma tras revisión</span></li><?php endif; ?>
        </ul>
        <button type="button" class="bk-side-toggle btn btn-ghost btn-sm" aria-expanded="false" aria-controls="bk-more" data-side-toggle>Ver detalles <?= icon('chevron-down') ?></button>
        <div class="bk-side-more" id="bk-more" data-side-more>
          <?php if (trim((string) ($event['description'] ?? '')) !== '') : ?><div class="prose"><?= \App\Core\Str::richText($event['description']) ?></div><?php endif; ?>
          <?php if ($hosts && !$multiHost) : ?>
            <p class="bk-host"><span class="host-photo host-photo-sm"><?php if (!empty($hosts[0]['_photo'])) : ?><img src="<?= e($hosts[0]['_photo']) ?>" alt="" width="40" height="40" loading="lazy" decoding="async"><?php else : ?><span class="host-initial serif" aria-hidden="true"><?= e($hosts[0]['_initial']) ?></span><?php endif; ?></span> <span>Con <strong><?= e($hosts[0]['name']) ?></strong><?= !empty($hosts[0]['title']) ? '<br><span class="muted">' . e($hosts[0]['title']) . '</span>' : '' ?></span></p>
          <?php endif; ?>
          <?php if ($policy !== '' || $cancelHours > 0) : ?>
            <div class="bk-policy"><p class="eyebrow">Política de cancelación</p>
              <?php if ($policy !== '') : ?><div class="prose"><?= \App\Core\Str::richText($policy) ?></div><?php else : ?><p class="muted">Puedes cancelar o reprogramar hasta <?= e((string) $cancelHours) ?> horas antes de tu cita.</p><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </aside>

    <div class="bk-main">
      <ol class="bk-progress" aria-label="Progreso de tu reserva" data-progress>
        <li data-prog="1"><span class="bk-num mono">1</span><span class="bk-lab">Servicio</span></li>
        <li data-prog="2"><span class="bk-num mono">2</span><span class="bk-lab">Fecha y hora</span></li>
        <li data-prog="3"><span class="bk-num mono">3</span><span class="bk-lab">Tus datos</span></li>
        <li data-prog="4"><span class="bk-num mono">4</span><span class="bk-lab">Confirmación</span></li>
      </ol>
      <div class="sr-only" role="status" aria-live="polite" data-step-live></div>

      <div class="bk-stage" data-stage>
        <span class="bk-curtain" aria-hidden="true"></span>

        <!-- Paso 1 -->
        <section class="bk-step" data-step="1" aria-labelledby="st1">
          <h2 id="st1" class="bk-h serif" tabindex="-1">Tu cita, a tu medida</h2>
          <?php if ($multiDur) : ?>
          <fieldset class="fieldset bk-fs">
            <legend>Duración</legend>
            <div class="chips" role="radiogroup" aria-label="Duración de la cita">
              <?php foreach ($durations as $d) : ?>
                <label class="chip chip-lg"><input type="radio" name="duration" value="<?= e((string) $d) ?>"<?= chk($d === $duration) ?>> <span class="mono"><?= e(\App\Core\Fmt::duration($d)) ?></span></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <?php endif; ?>
          <?php if ($multiHost) : ?>
          <fieldset class="fieldset bk-fs">
            <legend>¿Con quién?</legend>
            <div class="chips" role="radiogroup" aria-label="Anfitrión">
              <label class="chip chip-lg"><input type="radio" name="host" value="0"<?= chk($hostPick === 0) ?>> <span>Cualquiera disponible</span></label>
              <?php foreach ($hosts as $h) : ?>
                <label class="chip chip-lg"><input type="radio" name="host" value="<?= e((string) $h['id']) ?>"<?= chk($hostPick === (int) $h['id']) ?>> <span><?= e($h['name']) ?></span></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <?php endif; ?>
          <?php if (!$multiDur && !$multiHost) : ?>
            <p class="bk-lead">Esta cita dura <strong><?= e(\App\Core\Fmt::duration($duration)) ?></strong><?= $hosts ? ' y será con <strong>' . e($hosts[0]['name']) . '</strong>' : '' ?>. Elige el día y la hora que mejor te quede.</p>
          <?php endif; ?>
          <?php if ($ev['mode'] === 'home') : ?><p class="bk-note"><?= icon('home') ?> Te atendemos en tu domicilio; en el paso de datos nos dirás la dirección.</p><?php endif; ?>
          <div class="bk-actions"><button type="button" class="btn btn-gold btn-lg" data-next-step>Elegir fecha y hora <?= icon('arrow-right') ?></button></div>
        </section>

        <!-- Paso 2 -->
        <section class="bk-step" data-step="2" aria-labelledby="st2" hidden>
          <h2 id="st2" class="bk-h serif" tabindex="-1">¿Cuándo te queda mejor?</h2>
          <?php partial('public/_scheduler', ['sid' => 'bk', 'tzGroups' => $tzGroups, 'waitlist' => true]); ?>

          <div class="bk-wait" data-waitlist hidden>
            <h3 class="serif">Lista de espera</h3>
            <p class="muted">Déjanos tus datos y te avisamos en cuanto se libere un horario. Tendrás unos minutos para confirmarlo.</p>
            <form class="stack" data-waitlist-form novalidate>
              <div class="hp-wrap" aria-hidden="true"><label>No llenar este campo<input type="text" name="company_site" tabindex="-1" autocomplete="off"></label></div>
              <div class="form-grid">
                <div class="field"><label for="w-name">Nombre</label><input class="input" id="w-name" name="name" autocomplete="name" required></div>
                <div class="field"><label for="w-email">Correo</label><input class="input" id="w-email" name="email" type="email" autocomplete="email"></div>
                <div class="field"><label for="w-phone">Teléfono</label><input class="input" id="w-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" placeholder="5555 1234"></div>
                <div class="field"><label for="w-date">Fecha que prefieres (opcional)</label><input class="input" id="w-date" name="want_date" type="date"></div>
              </div>
              <label class="check"><input type="checkbox" name="consent" value="1"> <span>Autorizo que me contacten para avisarme de un horario (<a href="<?= e(url('/privacidad')) ?>" target="_blank" rel="noopener">aviso de privacidad</a>).</span></label>
              <p class="error" role="alert" data-waitlist-error hidden></p>
              <div class="form-actions"><button class="btn btn-gold" type="submit">Anotarme en la lista</button><button class="btn btn-ghost" type="button" data-close-waitlist>Cancelar</button></div>
            </form>
            <p class="alert alert-ok" role="status" data-waitlist-ok hidden></p>
          </div>

          <div class="bk-actions">
            <button type="button" class="btn btn-ghost" data-prev-step><?= icon('arrow-left') ?> Atrás</button>
            <button type="button" class="btn btn-gold btn-lg" data-next-step disabled>Continuar <?= icon('arrow-right') ?></button>
          </div>
        </section>

        <!-- Paso 3 -->
        <section class="bk-step" data-step="3" aria-labelledby="st3" hidden>
          <h2 id="st3" class="bk-h serif" tabindex="-1">Tus datos</h2>
          <div class="bk-chosen" data-chosen>
            <span class="bk-chosen-ic" aria-hidden="true"><?= icon('calendar') ?></span>
            <div><strong data-chosen-when></strong><br><span class="muted" data-chosen-meta></span></div>
            <button type="button" class="btn btn-ghost btn-sm" data-change-slot>Cambiar</button>
          </div>
          <?php if ($ev['series_sessions'] > 1) : ?>
            <div class="bk-series"><p class="eyebrow">Se reservarán <?= e((string) $ev['series_sessions']) ?> sesiones</p><ol class="bk-series-list mono" data-series></ol></div>
          <?php endif; ?>

          <form class="bk-form stack" data-form novalidate autocomplete="on">
            <div class="alert alert-err" role="alert" tabindex="-1" data-form-error hidden></div>
            <div class="hp-wrap" aria-hidden="true"><label>No llenar este campo<input type="text" name="company_site" tabindex="-1" autocomplete="off"></label></div>
            <div class="form-grid">
              <div class="field full" data-f="name"><label for="f-name">Nombre completo <span class="req" aria-hidden="true">*</span></label><input class="input" id="f-name" name="name" type="text" autocomplete="name" required maxlength="160" value="<?= e($boot['prefill']['name']) ?>"><p class="error" hidden></p></div>
              <div class="field" data-f="email"><label for="f-email">Correo electrónico <span class="req" aria-hidden="true">*</span></label><input class="input" id="f-email" name="email" type="email" autocomplete="email" inputmode="email" required maxlength="190" value="<?= e($boot['prefill']['email']) ?>"><p class="hint">Aquí te enviamos la confirmación.</p><p class="error" hidden></p></div>
              <div class="field" data-f="phone"><label for="f-phone">Teléfono<?= $ev['require_phone'] ? ' <span class="req" aria-hidden="true">*</span>' : ' (opcional)' ?></label>
                <div class="input-group"><span class="input-group-text mono">+<?= e($boot['phone_cc']) ?></span><input class="input" id="f-phone" name="phone" type="tel" autocomplete="tel-national" inputmode="tel" maxlength="30" placeholder="5555 1234"<?= $ev['require_phone'] ? ' required' : '' ?> value="<?= e($boot['prefill']['phone']) ?>"></div>
                <p class="hint">8 dígitos, o escribe tu código de país con +.</p><p class="error" hidden></p></div>
              <?php if ($ev['mode'] === 'home') : ?>
              <div class="field full" data-f="location"><label for="f-loc">Dirección donde te atendemos <span class="req" aria-hidden="true">*</span></label><input class="input" id="f-loc" name="location" type="text" autocomplete="street-address" maxlength="255" required placeholder="Zona, calle, número y referencias"><p class="error" hidden></p></div>
              <?php endif; ?>
            </div>

            <?php if ($fields) : ?>
            <div class="bk-questions stack">
              <?php foreach ($fields as $f) : $fid = 'cf-' . $f['id']; $req = $f['required']; ?>
              <div class="field" data-cf="<?= e((string) $f['id']) ?>" data-f="f_<?= e((string) $f['id']) ?>" data-name="<?= e($f['name']) ?>" data-type="<?= e($f['type']) ?>" data-cond-field="<?= e($f['cond_field']) ?>" data-cond-value="<?= e($f['cond_value']) ?>"<?= $f['cond_field'] !== '' ? ' hidden' : '' ?>>
                <?php if ($f['type'] === 'consent' || ($f['type'] === 'checkbox' && !$f['options'])) : ?>
                  <label class="check"><input type="checkbox" id="<?= e($fid) ?>" name="<?= e($fid) ?>" value="1"<?= $req ? ' required' : '' ?>> <span><?= e($f['label']) ?><?= $req ? ' <span class="req" aria-hidden="true">*</span>' : '' ?></span></label>
                <?php elseif (in_array($f['type'], ['radio', 'checkbox'], true)) : ?>
                  <fieldset class="fieldset"><legend><?= e($f['label']) ?><?= $req ? ' <span class="req" aria-hidden="true">*</span>' : '' ?></legend>
                    <div class="stack"><?php foreach ($f['options'] as $o) : ?><label class="check"><input type="<?= e($f['type']) ?>" name="<?= e($fid) ?>" value="<?= e($o) ?>"> <span><?= e($o) ?></span></label><?php endforeach; ?></div>
                  </fieldset>
                <?php else : ?>
                  <label for="<?= e($fid) ?>"><?= e($f['label']) ?><?= $req ? ' <span class="req" aria-hidden="true">*</span>' : '' ?></label>
                  <?php if ($f['type'] === 'select') : ?>
                    <select class="select" id="<?= e($fid) ?>" name="<?= e($fid) ?>"><option value="">Elige una opción</option><?php foreach ($f['options'] as $o) : ?><option value="<?= e($o) ?>"><?= e($o) ?></option><?php endforeach; ?></select>
                  <?php elseif ($f['type'] === 'textarea') : ?>
                    <textarea class="textarea" id="<?= e($fid) ?>" name="<?= e($fid) ?>" rows="3" maxlength="3000"></textarea>
                  <?php elseif ($f['type'] === 'file') : ?>
                    <input class="input" id="<?= e($fid) ?>" type="file" data-upload accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                    <input type="hidden" name="<?= e($fid) ?>" data-file-token>
                    <p class="hint" data-upload-status>Imágenes, PDF o documentos de oficina, hasta 5 MB.</p>
                  <?php else :
                      $t = ['number' => 'number', 'email' => 'email', 'phone' => 'tel', 'date' => 'date'][$f['type']] ?? 'text'; ?>
                    <input class="input" id="<?= e($fid) ?>" name="<?= e($fid) ?>" type="<?= e($t) ?>" <?= $f['type'] === 'number' ? 'inputmode="decimal" step="any"' : '' ?>>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if ($f['help'] !== '') : ?><p class="hint"><?= e($f['help']) ?></p><?php endif; ?>
                <p class="error" hidden></p>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($isGroup) : ?>
            <div class="field seats" data-f="seats"><label for="f-seats">Lugares a reservar</label>
              <div class="stepper"><button type="button" class="btn btn-outline btn-icon" data-seats-dec aria-label="Menos lugares">−</button><input class="input mono" id="f-seats" name="seats" type="number" inputmode="numeric" min="1" max="1" value="1" readonly><button type="button" class="btn btn-outline btn-icon" data-seats-inc aria-label="Más lugares">+</button></div>
              <p class="hint" data-seats-hint></p></div>
            <?php endif; ?>

            <?php if ($ev['allow_guests'] && $ev['max_guests'] > 0) : ?>
            <div class="bk-guests" data-guests data-max="<?= e((string) $ev['max_guests']) ?>">
              <p class="eyebrow">Invitados adicionales (opcional)</p>
              <div class="stack" data-guest-list></div>
              <button type="button" class="btn btn-outline btn-sm" data-add-guest><?= icon('plus') ?> Agregar invitado</button>
            </div>
            <?php endif; ?>

            <div class="field" data-f="notes"><label for="f-notes">Notas para nosotros (opcional)</label><textarea class="textarea" id="f-notes" name="notes" rows="3" maxlength="1500" placeholder="Algo que debamos saber antes de tu cita"></textarea></div>

            <?php if ($ev['allow_coupon']) : ?>
            <div class="field bk-coupon" data-f="coupon"><label for="f-coupon">¿Tienes un cupón?</label>
              <div class="input-group"><input class="input mono" id="f-coupon" name="coupon" type="text" maxlength="40" autocomplete="off" autocapitalize="characters" spellcheck="false"><button type="button" class="btn btn-outline" data-coupon-apply>Aplicar</button></div>
              <p class="hint" data-coupon-msg role="status"></p></div>
            <?php endif; ?>

            <div class="bk-price" data-price hidden>
              <dl>
                <div><dt>Precio</dt><dd class="mono" data-p-price></dd></div>
                <div data-p-disc-row hidden><dt>Descuento</dt><dd class="mono" data-p-disc></dd></div>
                <div class="bk-price-total"><dt>Total</dt><dd class="mono" data-p-total></dd></div>
                <div data-p-dep-row hidden><dt>Anticipo para confirmar</dt><dd class="mono" data-p-dep></dd></div>
              </dl>
              <p class="hint">Después de reservar verás cómo realizar el pago.</p>
            </div>

            <div class="field" data-f="consent">
              <label class="check bk-consent"><input type="checkbox" name="consent" value="1" required> <span><?= e($consent['text']) ?> Consulta el <a href="<?= e(url('/privacidad')) ?>" target="_blank" rel="noopener">aviso de privacidad</a> y los <a href="<?= e(url('/terminos')) ?>" target="_blank" rel="noopener">términos del servicio</a>. <span class="req" aria-hidden="true">*</span></span></label>
              <p class="error" hidden></p>
            </div>

            <?php if ($captchaProvider !== 'none') : ?>
              <div class="bk-captcha"><div class="<?= $captchaProvider === 'hcaptcha' ? 'h-captcha' : 'cf-turnstile' ?>" data-sitekey="<?= e($captchaKey) ?>" data-theme="dark"></div></div>
            <?php endif; ?>

            <div class="bk-actions">
              <button type="button" class="btn btn-ghost" data-prev-step><?= icon('arrow-left') ?> Atrás</button>
              <button type="submit" class="btn btn-gold btn-lg" data-submit><span data-submit-label><?= $ev['approval'] ? 'Enviar solicitud' : 'Confirmar mi cita' ?></span></button>
            </div>
          </form>
        </section>

        <!-- Paso 4 -->
        <section class="bk-step bk-done" data-step="4" aria-labelledby="st4" hidden>
          <div class="seal-wrap">
            <div data-seal-ok><?php partial('public/_seal', ['mode' => 'ok']); ?></div>
            <div data-seal-wait hidden><?php partial('public/_seal', ['mode' => 'wait']); ?></div>
          </div>
          <h2 id="st4" class="bk-h bk-h-center serif" tabindex="-1" data-done-title>¡Tu cita está confirmada!</h2>
          <p class="bk-done-lead center" data-done-lead></p>
          <div class="bk-summary card">
            <div class="card-body">
              <dl class="bk-dl">
                <div><dt>Servicio</dt><dd data-o="event"></dd></div>
                <div><dt>Fecha</dt><dd data-o="date"></dd></div>
                <div><dt>Hora</dt><dd class="mono" data-o="time"></dd></div>
                <div><dt>Duración</dt><dd data-o="duration"></dd></div>
                <div data-o-row="host"><dt>Con</dt><dd data-o="host"></dd></div>
                <div><dt>Modalidad</dt><dd data-o="mode"></dd></div>
                <div data-o-row="location"><dt>Lugar</dt><dd data-o="location"></dd></div>
                <div data-o-row="video"><dt>Videollamada</dt><dd><a data-o-link="video" target="_blank" rel="noopener noreferrer">Entrar a la sala</a></dd></div>
                <div><dt>Zona horaria</dt><dd data-o="tz"></dd></div>
              </dl>
              <div data-o-sessions hidden><p class="eyebrow">Tus sesiones</p><ol class="mono bk-series-list" data-o-sessions-list></ol></div>
            </div>
          </div>
          <div class="bk-counter" data-counter hidden aria-label="Tiempo para tu cita">
            <p class="eyebrow center">Faltan</p>
            <div class="mech" data-mech role="timer" aria-live="off">
              <span class="mech-unit"><span class="mech-d mono" data-u="d">0</span><small>días</small></span>
              <span class="mech-unit"><span class="mech-d mono" data-u="h">00</span><small>horas</small></span>
              <span class="mech-unit"><span class="mech-d mono" data-u="m">00</span><small>min</small></span>
              <span class="mech-unit"><span class="mech-d mono" data-u="s">00</span><small>seg</small></span>
            </div>
          </div>
          <div class="bk-confirm-msg prose" data-confirm-msg hidden></div>
          <div class="alert alert-warn" data-pay-note hidden><?= icon('dollar') ?><div>Para asegurar tu cita falta realizar el pago. <a data-o-link="manage">Ver cómo pagar</a>.</div></div>
          <div class="bk-cal-btns" data-cal-btns>
            <p class="eyebrow center">Agrégala a tu calendario</p>
            <div class="row row-wrap bk-center">
              <a class="btn btn-outline" data-o-link="google" target="_blank" rel="noopener noreferrer"><?= icon('calendar') ?> Google</a>
              <a class="btn btn-outline" data-o-link="ics"><?= icon('download') ?> Apple / .ics</a>
              <a class="btn btn-outline" data-o-link="outlook" target="_blank" rel="noopener noreferrer"><?= icon('calendar') ?> Outlook</a>
            </div>
          </div>
          <div class="row row-wrap bk-center bk-actions-end">
            <a class="btn btn-gold" data-o-link="manage"><?= icon('eye') ?> Ver o gestionar mi cita</a>
            <?php if ($biz['wa_link'] !== '') : ?><a class="btn btn-outline" data-o-link="wa" target="_blank" rel="noopener noreferrer"><?= icon('whatsapp') ?> Escribir por WhatsApp</a><?php endif; ?>
          </div>
          <p class="center muted bk-redirect" data-redirect-note hidden>En un momento te llevaremos a la siguiente página. <a data-o-link="redirect" target="_top">Ir ahora</a></p>
        </section>
      </div>
    </div>
  </div>
</main>
<?php if (!$embed) { partial('public/_footer', ['biz' => $biz]); } ?>
<script type="application/json" id="boot"><?= json_script($boot) ?></script>
<?php if ($captchaScript) : ?><script src="<?= e($captchaScript) ?>" async defer></script><?php endif; ?>
