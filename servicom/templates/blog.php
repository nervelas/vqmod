<?php
declare(strict_types=1);
/** @var array $page */
$headTitle  = (string) $page['title'];
$headSub    = (string) $page['subtitle'];
$headCrumbs = [['name' => $headTitle]];
require __DIR__ . '/layout/pagehead.php';

$perPage = 9;
$pageNum = max(1, (int) get('p', '1'));
$total   = Content::postsCount();
$pages   = max(1, (int) ceil($total / $perPage));
$pageNum = min($pageNum, $pages);
$posts   = Content::posts($perPage, ($pageNum - 1) * $perPage);
?>
<section class="section">
  <div class="wrap">
    <?php if ($posts === []): ?>
      <p class="muted center">Aún no hay publicaciones. Vuelva pronto.</p>
    <?php else: ?>
      <div class="posts" data-stagger>
        <?php foreach ($posts as $p): ?>
          <article class="card post">
            <a class="post__img" href="<?= e(base('actualidad-web/' . $p['slug'] . '/')) ?>" data-cursor="Leer" aria-label="<?= e($p['title']) ?>">
              <?php if (($img = (string) $p['image']) !== ''): ?>
                <img src="<?= e(asset_url($img)) ?>" alt="<?= e($p['image_alt'] ?: $p['title']) ?>" width="900" height="620" loading="lazy" decoding="async">
              <?php endif; ?>
            </a>
            <div class="post__body">
              <span class="post__date"><?= e(fecha_larga((string) $p['published_at'])) ?></span>
              <h2 class="post__title"><a href="<?= e(base('actualidad-web/' . $p['slug'] . '/')) ?>"><?= e($p['title']) ?></a></h2>
              <p class="post__excerpt"><?= e(excerpt((string) ($p['excerpt'] ?: $p['body']), 160)) ?></p>
              <a class="link-arrow" href="<?= e(base('actualidad-web/' . $p['slug'] . '/')) ?>"><span>Leer artículo</span><?= icon('flecha', 17) ?></a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Paginación">
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <?php if ($i === $pageNum): ?>
              <span class="is-current" aria-current="page"><?= $i ?></span>
            <?php else: ?>
              <a href="<?= e(base('actualidad-web/?p=' . $i)) ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/sections/cta.php'; ?>
