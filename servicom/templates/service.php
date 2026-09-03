<?php
declare(strict_types=1);
/** @var array $service */
$headTitle  = (string) $service['title'];
$headSub    = (string) $service['excerpt'];
$headCrumbs = [['name' => 'Servicios', 'url' => 'servicios/'], ['name' => $headTitle]];
require __DIR__ . '/layout/pagehead.php';
$others = array_values(array_filter(Content::services(), static fn($s) => $s['slug'] !== $service['slug']));
$feats  = lines((string) $service['features']);
$phone  = Settings::get('phone');
$waLink = whatsapp_link(Settings::get('whatsapp', $phone), 'Hola Servicom, me interesa el servicio de ' . $service['title'] . '.');
?>
<section class="section">
  <div class="wrap layout-side">
    <div>
      <?php if (($img = (string) $service['image']) !== ''): ?>
        <figure class="article__hero" data-reveal="clip">
          <img src="<?= e(asset_url($img)) ?>" alt="<?= e($service['image_alt'] ?: $service['title']) ?>" width="900" height="620" loading="eager" decoding="async">
        </figure>
      <?php endif; ?>

      <div class="prose" data-reveal>
        <?= paragraphs((string) $service['body']) ?>

        <?php if ($feats !== []): ?>
          <h2>Qué incluye este servicio</h2>
          <ul>
            <?php foreach ($feats as $f): ?>
              <li><?= icon('check', 17) ?><span><?= e($f) ?></span></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="cta" style="margin-top:clamp(2.2rem,5vw,3.5rem)" data-reveal="zoom">
        <h2 class="cta__title">¿Le interesa <?= e(mb_strtolower((string) ($service['short_title'] ?: $service['title']))) ?>?</h2>
        <p class="cta__text">Solicite su cotización por escrito, sin compromiso. Le respondemos con una propuesta clara.</p>
        <div class="cta__actions">
          <a class="btn btn--lg" data-magnetic=".22" href="<?= e(base('contacto/')) ?>"><?= icon('cotizar', 19) ?><span><?= e($service['btn_text'] ?: 'Solicitar cotización') ?></span></a>
          <a class="btn btn--wa btn--lg" data-magnetic=".18" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 19) ?><span>WhatsApp</span></a>
        </div>
      </div>
    </div>

    <aside class="sidebar" data-reveal="right">
      <div class="sidebar__box">
        <h3>Otros servicios</h3>
        <ul class="footer__list">
          <?php foreach ($others as $o): ?>
            <li><a href="<?= e(base('servicios/' . $o['slug'] . '/')) ?>"><?= icon((string) $o['icon'], 16) ?><?= e($o['short_title'] ?: $o['title']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="sidebar__box">
        <h3>Contacto directo</h3>
        <ul class="footer__list">
          <?php if ($phone !== ''): ?><li><a href="tel:+<?= e(digits($phone)) ?>"><?= icon('telefono', 16) ?><?= e($phone) ?></a></li><?php endif; ?>
          <li><a href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 16) ?>WhatsApp</a></li>
          <?php if (($m = Settings::get('email')) !== ''): ?><li><a href="mailto:<?= e($m) ?>"><?= icon('contacto', 16) ?><?= e($m) ?></a></li><?php endif; ?>
        </ul>
        <a class="btn btn--block btn--sm" style="margin-top:1.1rem" href="<?= e(base('contacto/')) ?>"><?= icon('cotizar', 17) ?><span>Cotizar ahora</span></a>
      </div>

      <?php if (($price = (string) $service['price_text']) !== ''): ?>
        <div class="sidebar__box">
          <h3>Inversión</h3>
          <p class="muted" style="font-size:.94rem"><?= e($price) ?></p>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</section>
<?php $limitFaqs = 5; require __DIR__ . '/sections/faq.php'; ?>
