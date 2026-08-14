<?php
/** Gallery: album grid, or a single album's photos. */
if (!defined('BASE_PATH')) { exit; }
$albumSlug = $GLOBALS['current_album_slug'] ?? null;

if ($albumSlug):
  $album = Content::album($albumSlug);
  if (!$album):
    http_response_code(404); ?>
    <section class="page-hero"><div class="container"><h1>Álbum no encontrado</h1>
    <p><a class="btn btn--outline" href="<?= e(base_url('galeria')) ?>">Volver a la galería</a></p></div></section>
  <?php else:
    $photos = Content::photos((int)$album['id']); ?>
    <section class="page-hero">
      <div class="container">
        <p class="eyebrow"><a href="<?= e(base_url('galeria')) ?>">Galería</a></p>
        <h1><?= e($album['title']) ?></h1>
        <?php if (!empty($album['description'])): ?><p class="page-hero__intro"><?= e($album['description']) ?></p><?php endif; ?>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <div class="photo-grid" id="photoGrid">
          <?php foreach ($photos as $ph): ?>
            <a class="photo-grid__item" href="<?= e(asset_url($ph['image'])) ?>" data-caption="<?= e($ph['caption']) ?>">
              <img src="<?= e(asset_url($ph['image'])) ?>" alt="<?= e($ph['caption'] ?: $album['title']) ?>" loading="lazy">
            </a>
          <?php endforeach; ?>
        </div>
        <?php if (!$photos): ?><p class="muted">Este álbum aún no tiene fotografías.</p><?php endif; ?>
      </div>
    </section>
  <?php endif; ?>
<?php else: ?>
  <section class="page-hero">
    <div class="container">
      <h1><?= e($page['h1'] ?: $page['title']) ?></h1>
      <?php if (!empty($page['intro'])): ?><p class="page-hero__intro"><?= e($page['intro']) ?></p><?php endif; ?>
    </div>
  </section>
  <section class="section">
    <div class="container">
      <?php $albums = Content::albums(); ?>
      <div class="gallery-preview__grid">
        <?php foreach ($albums as $al): ?>
          <a class="album-card" href="<?= e(base_url('galeria/' . $al['slug'])) ?>">
            <img src="<?= e(asset_url($al['cover_image'] ?: 'assets/img/gallery/g1.svg')) ?>" alt="<?= e($al['title']) ?>" loading="lazy">
            <span class="album-card__title"><?= e($al['title']) ?></span>
            <span class="album-card__count"><?= (int)$al['photo_count'] ?> fotos</span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php if (!$albums): ?><p class="muted">Pronto publicaremos nuestras fotografías.</p><?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" aria-hidden="true">
  <button class="lightbox__close" aria-label="Cerrar">&times;</button>
  <img class="lightbox__img" src="" alt="">
  <p class="lightbox__caption"></p>
</div>
