<?php
/** Contact page with form + map + info. */
if (!defined('BASE_PATH')) { exit; }
$old = $_SESSION['_old'] ?? []; unset($_SESSION['_old']);
$val = fn($k) => e($old[$k] ?? '');
?>
<section class="page-hero">
  <div class="container">
    <h1><?= e($page['h1'] ?: $page['title']) ?></h1>
    <?php if (!empty($page['intro'])): ?><p class="page-hero__intro"><?= e($page['intro']) ?></p><?php endif; ?>
  </div>
</section>

<section class="section contact">
  <div class="container contact__grid">
    <div class="contact__info">
      <h2>Información de contacto</h2>
      <ul class="contact__list">
        <li><span class="i i-pin"></span> <?= e(Settings::get('address')) ?></li>
        <li><span class="i i-phone"></span> <a href="tel:+502<?= e(Settings::get('phone_link','50222775656')) ?>"><?= e(Settings::get('phone')) ?></a></li>
        <li><span class="i i-mail"></span> <a href="mailto:<?= e(Settings::get('email')) ?>"><?= e(Settings::get('email')) ?></a></li>
      </ul>
      <?php if (Settings::bool('whatsapp_enabled', true)): ?>
        <a class="btn btn--whatsapp" href="<?= e(whatsapp_link(Settings::get('whatsapp_number'), Settings::get('whatsapp_message'))) ?>" target="_blank" rel="noopener">
          <?= e(Settings::get('whatsapp_button_text','WhatsApp')) ?>
        </a>
      <?php endif; ?>
      <?php if ($map = Settings::get('map_embed')): ?>
        <div class="contact__map">
          <iframe src="<?= e($map) ?>" width="100%" height="260" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Ubicación"></iframe>
        </div>
      <?php endif; ?>
    </div>

    <div class="contact__form form-card">
      <h2>Escríbenos</h2>
      <form method="post" action="<?= e(base_url('contactenos')) ?>" class="form" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="contacto">
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="form-group"><label>Nombre *</label><input type="text" name="name" value="<?= $val('name') ?>" required></div>
        <div class="form-group"><label>Correo electrónico *</label><input type="email" name="email" value="<?= $val('email') ?>" required></div>
        <div class="form-group"><label>Teléfono</label><input type="tel" name="phone" value="<?= $val('phone') ?>"></div>
        <div class="form-group"><label>Asunto</label><input type="text" name="subject" value="<?= $val('subject') ?>"></div>
        <div class="form-group"><label>Mensaje *</label><textarea name="message" rows="5" required><?= $val('message') ?></textarea></div>
        <button type="submit" class="btn btn--primary btn--lg">Enviar mensaje</button>
      </form>
    </div>
  </div>
</section>
