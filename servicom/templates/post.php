<?php
declare(strict_types=1);
/** @var array $postRow */
$headTitle  = (string) $postRow['title'];
$headSub    = (string) $postRow['excerpt'];
$headCrumbs = [['name' => 'Actualidad Web', 'url' => 'actualidad-web/'], ['name' => excerpt($headTitle, 46)]];
require __DIR__ . '/layout/pagehead.php';
$related = array_values(array_filter(Content::posts(4), static fn($p) => $p['slug'] !== $postRow['slug']));
$words   = str_word_count(strip_tags((string) $postRow['body']));
$minutes = max(1, (int) round($words / 200));
?>
<article class="section">
  <div class="wrap layout-side">
    <div>
      <div class="article__meta" data-reveal>
        <span><?= icon('reloj', 15) ?><?= e(fecha_larga((string) $postRow['published_at'])) ?></span>
        <span><?= icon('documento', 15) ?><?= $minutes ?> min de lectura</span>
        <?php if (($a = (string) $postRow['author']) !== ''): ?><span><?= icon('usuarios', 15) ?><?= e($a) ?></span><?php endif; ?>
      </div>

      <?php if (($img = (string) $postRow['image']) !== ''): ?>
        <figure class="article__hero" data-reveal="clip">
          <img src="<?= e(asset_url($img)) ?>" alt="<?= e($postRow['image_alt'] ?: $postRow['title']) ?>" width="900" height="620" fetchpriority="high" decoding="async">
        </figure>
      <?php endif; ?>

      <div class="prose" data-reveal><?= paragraphs((string) $postRow['body']) ?></div>

      <div class="cta" style="margin-top:clamp(2.2rem,5vw,3.5rem)" data-reveal="zoom">
        <h2 class="cta__title">¿Quiere una página web así de clara para su negocio?</h2>
        <p class="cta__text">Solicite su cotización por escrito, sin compromiso.</p>
        <div class="cta__actions">
          <a class="btn btn--lg" data-magnetic=".22" href="<?= e(base('contacto/')) ?>"><?= icon('cotizar', 19) ?><span>Solicitar cotización</span></a>
        </div>
      </div>
    </div>

    <aside class="sidebar" data-reveal="right">
      <?php if ($related !== []): ?>
        <div class="sidebar__box">
          <h3>Más publicaciones</h3>
          <ul class="footer__list">
            <?php foreach (array_slice($related, 0, 3) as $r): ?>
              <li><a href="<?= e(base('actualidad-web/' . $r['slug'] . '/')) ?>"><?= icon('blog', 16) ?><?= e(excerpt((string) $r['title'], 52)) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <div class="sidebar__box">
        <h3>Servicios</h3>
        <ul class="footer__list">
          <?php foreach (Content::services() as $s): ?>
            <li><a href="<?= e(base('servicios/' . $s['slug'] . '/')) ?>"><?= icon((string) $s['icon'], 16) ?><?= e($s['short_title'] ?: $s['title']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </aside>
  </div>
</article>
