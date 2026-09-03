<?php
declare(strict_types=1);
/** @var array|null $page */
$headTitle  = (string) ($page['title'] ?? 'Servicios');
$headSub    = (string) ($page['subtitle'] ?? '');
$headCrumbs = [['name' => $headTitle]];
require __DIR__ . '/layout/pagehead.php';
$services = Content::services();
?>
<section class="section">
  <div class="wrap">
    <?php $cols = count($services) % 3 === 0 ? 3 : (count($services) % 2 === 0 ? 2 : 0); ?>
    <div class="svc-grid<?= $cols ? " svc-grid--$cols" : "" ?>" data-stagger>
      <?php foreach ($services as $i => $s): ?>
        <article class="card svc">
          <span class="svc__num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <div class="svc__icon"><?= icon((string) $s['icon'], 27) ?></div>
          <h2 class="svc__title"><?= e($s['title']) ?></h2>
          <p class="svc__text"><?= e($s['excerpt']) ?></p>
          <ul class="svc__list">
            <?php foreach (array_slice(lines((string) $s['features']), 0, 5) as $f): ?>
              <li><?= icon('check', 16) ?><span><?= e($f) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <div class="svc__foot">
            <?php if (($p = (string) $s['price_text']) !== ''): ?><span class="svc__price"><?= e($p) ?></span><?php endif; ?>
            <a class="link-arrow" href="<?= e(base('servicios/' . $s['slug'] . '/')) ?>" data-cursor="Ver"><span>Ver detalle</span><?= icon('flecha', 17) ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/sections/process.php'; ?>
<?php $limitFaqs = null; require __DIR__ . '/sections/faq.php'; ?>
<?php require __DIR__ . '/sections/cta.php'; ?>
