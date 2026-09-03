<?php
declare(strict_types=1);
if (!Content::blockEnabled('nosotros')) { return; }
$img    = Content::b('nosotros', 'image', 'assets/img/nosotros.svg');
$points = array_slice(lines(Content::b('nosotros', 'extra')), 0, 5);
if ($points === []) {
    $points = [
        'Diseño visual profesional y secciones claras',
        'Adaptación correcta a celular, tablet y computadora',
        'Formulario de contacto y enlace directo a WhatsApp',
        'Entrega en pocos días una vez recibida la información',
    ];
}
?>
<section class="section" id="seccion-nosotros" aria-labelledby="tit-nosotros">
  <div class="wrap">
    <div class="about">
      <div class="about__media" data-reveal="left">
        <div class="about__frame">
          <img src="<?= e(asset_url($img)) ?>" alt="<?= e(Media::altFor($img, 'Servicom, diseño de páginas web en Guatemala')) ?>"
               width="900" height="620" loading="lazy" decoding="async" data-parallax=".05">
        </div>
        <div class="about__badge">
          <b>16+</b><span>años diseñando páginas web en Guatemala</span>
        </div>
      </div>

      <div data-reveal="right">
        <span class="shead__index">02</span>
        <div class="shead__eyebrow"><?= e(Content::b('nosotros', 'eyebrow')) ?></div>
        <h2 id="tit-nosotros"><?= e(Content::b('nosotros', 'title')) ?></h2>
        <div class="about__body" style="margin-top:1.2rem"><?= paragraphs(Content::b('nosotros', 'body')) ?></div>
        <ul class="about__points">
          <?php foreach ($points as $p): ?>
            <li><?= icon('check', 20) ?><span><?= e($p) ?></span></li>
          <?php endforeach; ?>
        </ul>
        <div class="about__actions">
          <?php if (($b1 = Content::b('nosotros', 'btn_text')) !== ''): ?>
            <a class="btn" data-magnetic=".22" href="<?= e(base(ltrim(Content::b('nosotros', 'btn_url', '/nosotros/'), '/'))) ?>">
              <?= icon('nosotros', 18) ?><span><?= e($b1) ?></span></a>
          <?php endif; ?>
          <?php if (($b2 = Content::b('nosotros', 'btn2_text')) !== ''): ?>
            <a class="btn btn--ghost" data-magnetic=".18" href="<?= e(base(ltrim(Content::b('nosotros', 'btn2_url', '/contacto/'), '/'))) ?>">
              <?= icon('cotizar', 18) ?><span><?= e($b2) ?></span></a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
