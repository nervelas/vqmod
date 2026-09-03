<?php
declare(strict_types=1);
/** @var array $FORM */
$phone   = Settings::get('phone');
$phone2  = Settings::get('phone_alt');
$mail    = Settings::get('email');
$waLink  = whatsapp_link(Settings::get('whatsapp', $phone), Settings::get('whatsapp_message'));
$old     = $FORM['old'] ?? [];
$errors  = $FORM['errors'] ?? [];
$val     = static fn(string $k): string => (string) ($old[$k] ?? '');
$services = Content::services();
?>
<section class="section" id="seccion-contacto" aria-labelledby="tit-contacto">
  <div class="wrap">
    <header class="shead" data-reveal>
      <div class="shead__eyebrow"><?= e(Content::b('contacto', 'eyebrow', 'Hablemos')) ?></div>
      <h2 class="shead__title" id="tit-contacto"><?= e(Content::b('contacto', 'title', 'Solicite su cotización por escrito')) ?></h2>
      <p class="shead__sub"><?= e(Content::b('contacto', 'subtitle')) ?></p>
    </header>

    <div class="contact-grid">
      <div class="contact-card" data-reveal="left">
        <?php if ($phone !== ''): ?>
          <div class="contact-item">
            <span class="contact-item__icon"><?= icon('telefono', 21) ?></span>
            <div><h3>Teléfono</h3><a href="tel:+<?= e(digits($phone)) ?>"><?= e($phone) ?></a>
              <?php if ($phone2 !== ''): ?><br><a href="tel:+<?= e(digits($phone2)) ?>"><?= e($phone2) ?></a><?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (Settings::get('whatsapp') !== ''): ?>
          <div class="contact-item">
            <span class="contact-item__icon"><?= icon('whatsapp', 21) ?></span>
            <div><h3>WhatsApp</h3><a href="<?= e($waLink) ?>" target="_blank" rel="noopener">Escribir ahora</a></div>
          </div>
        <?php endif; ?>

        <?php if ($mail !== ''): ?>
          <div class="contact-item">
            <span class="contact-item__icon"><?= icon('contacto', 21) ?></span>
            <div><h3>Correo electrónico</h3><a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a></div>
          </div>
        <?php endif; ?>

        <?php if (($addr = Settings::get('address_line')) !== ''): ?>
          <div class="contact-item">
            <span class="contact-item__icon"><?= icon('ubicacion', 21) ?></span>
            <div><h3>Ubicación</h3><p><?= e($addr) ?></p></div>
          </div>
        <?php endif; ?>

        <?php if (($sched = Settings::get('schedule')) !== ''): ?>
          <div class="contact-item">
            <span class="contact-item__icon"><?= icon('reloj', 21) ?></span>
            <div><h3>Horario</h3><p><?= e($sched) ?></p></div>
          </div>
        <?php endif; ?>

        <?php if (($map = trim(Settings::get('map_embed'))) !== '' && preg_match('#^https://(www\.)?google\.com/maps/embed#i', $map) === 1): ?>
          <div class="map-frame">
            <iframe src="<?= e($map) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Ubicación en el mapa"></iframe>
          </div>
        <?php endif; ?>
      </div>

      <div data-reveal="right">
        <?php if (($FORM['sent'] ?? false) === true): ?>
          <div class="alert alert--ok" role="status">
            <?= icon('check', 20) ?><span><?= e($FORM['message']) ?></span>
          </div>
        <?php elseif (($FORM['message'] ?? '') !== ''): ?>
          <div class="alert alert--error" role="alert">
            <?= icon('cerrar', 20) ?><span><?= e($FORM['message']) ?></span>
          </div>
        <?php endif; ?>

        <form class="form" method="post" action="<?= e(base('contacto/')) ?>#seccion-contacto" data-validate novalidate style="margin-top:1.1rem">
          <?= Csrf::field() ?>
          <input type="hidden" name="form" value="contacto">
          <div class="form__hp" aria-hidden="true">
            <label>No complete este campo <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            <input type="text" name="empresa_fax" tabindex="-1" autocomplete="off">
          </div>

          <div class="form__row">
            <div class="field<?= isset($errors['nombre']) ? ' field--error' : '' ?>">
              <label for="f-nombre">Nombre completo <span class="req">*</span></label>
              <input id="f-nombre" name="nombre" type="text" required maxlength="120" autocomplete="name" placeholder="Su nombre" value="<?= e($val('nombre')) ?>">
              <?php if (isset($errors['nombre'])): ?><span class="field__error"><?= e($errors['nombre']) ?></span><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['email']) ? ' field--error' : '' ?>">
              <label for="f-email">Correo electrónico <span class="req">*</span></label>
              <input id="f-email" name="email" type="email" required maxlength="160" autocomplete="email" placeholder="correo@empresa.com" value="<?= e($val('email')) ?>">
              <?php if (isset($errors['email'])): ?><span class="field__error"><?= e($errors['email']) ?></span><?php endif; ?>
            </div>
          </div>

          <div class="form__row">
            <div class="field<?= isset($errors['telefono']) ? ' field--error' : '' ?>">
              <label for="f-telefono">Teléfono / WhatsApp</label>
              <input id="f-telefono" name="telefono" type="tel" maxlength="40" autocomplete="tel" placeholder="0000 0000" value="<?= e($val('telefono')) ?>">
              <?php if (isset($errors['telefono'])): ?><span class="field__error"><?= e($errors['telefono']) ?></span><?php endif; ?>
            </div>
            <div class="field">
              <label for="f-servicio">Servicio de interés</label>
              <select id="f-servicio" name="servicio">
                <option value="">Seleccione una opción</option>
                <?php foreach ($services as $s): ?>
                  <option value="<?= e($s['title']) ?>"<?= $val('servicio') === $s['title'] ? ' selected' : '' ?>><?= e($s['title']) ?></option>
                <?php endforeach; ?>
                <option value="Otro"<?= $val('servicio') === 'Otro' ? ' selected' : '' ?>>Otro</option>
              </select>
            </div>
          </div>

          <div class="field">
            <label for="f-asunto">Asunto</label>
            <input id="f-asunto" name="asunto" type="text" maxlength="150" placeholder="Ej. Cotización de página web" value="<?= e($val('asunto')) ?>">
          </div>

          <div class="field<?= isset($errors['mensaje']) ? ' field--error' : '' ?>">
            <label for="f-mensaje">Cuéntenos sobre su proyecto <span class="req">*</span></label>
            <textarea id="f-mensaje" name="mensaje" required maxlength="4000" placeholder="¿A qué se dedica su negocio? ¿Qué secciones necesita en su sitio?"><?= e($val('mensaje')) ?></textarea>
            <?php if (isset($errors['mensaje'])): ?><span class="field__error"><?= e($errors['mensaje']) ?></span><?php endif; ?>
          </div>

          <label class="form__consent">
            <input type="checkbox" name="consent" required>
            <span>Autorizo el uso de mis datos para responder a esta solicitud, según el <a href="<?= e(base('aviso-legal/')) ?>">aviso de privacidad</a>.</span>
          </label>

          <button class="btn btn--lg" type="submit" data-magnetic=".18">
            <?= icon('cotizar', 19) ?><span>Enviar solicitud</span>
          </button>
          <p class="muted" style="font-size:.83rem"><?= icon('candado', 14) ?> Sus datos se envían de forma segura y solo se utilizan para responder su consulta.</p>
        </form>
      </div>
    </div>
  </div>
</section>
