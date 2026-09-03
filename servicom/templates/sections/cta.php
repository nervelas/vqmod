<?php
declare(strict_types=1);
if (!Content::blockEnabled('cta')) { return; }
$phone  = Settings::get('phone');
$waLink = whatsapp_link(Settings::get('whatsapp', $phone), Settings::get('whatsapp_message'));
$u = static fn(string $x): string => $x === 'whatsapp' ? $waLink : (preg_match('#^(https?://|tel:|mailto:)#i', $x) ? $x : base(ltrim($x, '/')));
?>
<section class="section" aria-labelledby="tit-cta">
  <div class="wrap">
    <div class="cta" data-reveal="zoom">
      <h2 class="cta__title" id="tit-cta"><?= e(Content::b('cta', 'title')) ?></h2>
      <p class="cta__text"><?= e(Content::b('cta', 'subtitle')) ?></p>
      <div class="cta__actions">
        <?php if (($b1 = Content::b('cta', 'btn_text')) !== ''): ?>
          <a class="btn btn--lg" data-magnetic=".25" data-cursor="Cotizar" href="<?= e($u(Content::b('cta', 'btn_url', '/contacto/'))) ?>">
            <?= icon('cotizar', 19) ?><span><?= e($b1) ?></span></a>
        <?php endif; ?>
        <?php if (($b2 = Content::b('cta', 'btn2_text')) !== ''): ?>
          <a class="btn btn--wa btn--lg" data-magnetic=".2" href="<?= e($u(Content::b('cta', 'btn2_url', 'whatsapp'))) ?>" target="_blank" rel="noopener">
            <?= icon('whatsapp', 19) ?><span><?= e($b2) ?></span></a>
        <?php endif; ?>
      </div>
      <div class="cta__note">
        <span><?= icon('check', 15) ?>Cotización por escrito</span>
        <span><?= icon('reloj', 15) ?>Entrega en pocos días</span>
        <span><?= icon('movil', 15) ?>Adaptable a todos los dispositivos</span>
      </div>
    </div>
  </div>
</section>
