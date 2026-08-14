<?php
/** Homepage template. */
if (!defined('BASE_PATH')) { exit; }
$map = Content::sectionMap($sections);
$platforms = Content::platforms();
$albums = Content::albums(4);

/** Render a button if the section defines one. */
function section_button(array $s, string $class = 'btn btn--primary'): string
{
    if (empty($s['button_text']) || empty($s['button_url'])) { return ''; }
    $target = ($s['button_target'] ?? '_self') === '_blank' ? ' target="_blank" rel="noopener"' : '';
    return '<a class="' . $class . '" href="' . e(Content::url($s['button_url'])) . '"' . $target . '>' . e($s['button_text']) . '</a>';
}

$hero = $map['hero'] ?? null;
?>
<?php if ($hero): ?>
<section class="hero" <?= !empty($hero['background']) ? 'style="--hero-bg:url(\''.e(asset_url($hero['background'])).'\')"' : '' ?>>
  <div class="hero__overlay"></div>
  <div class="container hero__inner">
    <p class="hero__eyebrow"><?= e($hero['subtitle']) ?></p>
    <h1 class="hero__title"><?= e($hero['title']) ?></h1>
    <p class="hero__text"><?= e($hero['body']) ?></p>
    <div class="hero__actions">
      <?= section_button($hero, 'btn btn--secondary btn--lg') ?>
      <a class="btn btn--ghost btn--lg" href="<?= e(base_url('nosotros')) ?>">Conócenos</a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($w = ($map['welcome'] ?? null)): if (!empty($w['is_active'])): ?>
<section class="section welcome">
  <div class="container welcome__grid">
    <div class="welcome__media">
      <img src="<?= e(asset_url($w['image'] ?: 'assets/img/welcome.svg')) ?>" alt="<?= e($w['title']) ?>" loading="lazy">
    </div>
    <div class="welcome__body">
      <p class="eyebrow"><?= e($w['subtitle']) ?></p>
      <h2><?= e($w['title']) ?></h2>
      <p><?= nl2br(e($w['body'])) ?></p>
      <?= section_button($w) ?>
    </div>
  </div>
</section>
<?php endif; endif; ?>

<?php
$levels = array_values(array_filter([
    $map['level_pre'] ?? null,
    $map['level_pri'] ?? null,
    $map['level_medio'] ?? null,
], fn($s) => $s && !empty($s['is_active'])));
if ($levels):
  $lh = $map['levels_header'] ?? ['title'=>'Niveles educativos','subtitle'=>''];
?>
<section class="section levels">
  <div class="container">
    <div class="section-head">
      <p class="eyebrow"><?= e($lh['subtitle'] ?? '') ?></p>
      <h2><?= e($lh['title'] ?? '') ?></h2>
    </div>
    <div class="levels__grid">
      <?php foreach ($levels as $lv): ?>
        <article class="level-card">
          <div class="level-card__media">
            <img src="<?= e(asset_url($lv['image'] ?: 'assets/img/nivel.svg')) ?>" alt="<?= e($lv['title']) ?>" loading="lazy">
          </div>
          <div class="level-card__body">
            <h3><?= e($lv['title']) ?></h3>
            <p><?= e($lv['body']) ?></p>
            <?= section_button($lv, 'btn btn--outline btn--sm') ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($platforms):
  $ph = $map['platforms_header'] ?? ['title'=>'Accesos y plataformas','subtitle'=>''];
?>
<section class="section platforms" id="plataformas">
  <div class="container">
    <div class="section-head">
      <p class="eyebrow"><?= e($ph['subtitle'] ?? '') ?></p>
      <h2><?= e($ph['title'] ?? '') ?></h2>
    </div>
    <div class="platforms__grid">
      <?php foreach ($platforms as $pl): ?>
        <a class="platform-card" href="<?= e(Content::url($pl['url'])) ?>" <?= $pl['target']==='_blank'?'target="_blank" rel="noopener"':'' ?>>
          <span class="platform-card__icon"><?= platform_icon($pl['icon'] ?? '') ?></span>
          <span class="platform-card__name"><?= e($pl['name']) ?></span>
          <?php if (!empty($pl['description'])): ?><span class="platform-card__desc"><?= e($pl['description']) ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($cta = ($map['cta_admisiones'] ?? null)): if (!empty($cta['is_active'])): ?>
<section class="section cta" <?= !empty($cta['background']) ? 'style="--cta-bg:url(\''.e(asset_url($cta['background'])).'\')"' : '' ?>>
  <div class="cta__overlay"></div>
  <div class="container cta__inner">
    <p class="eyebrow eyebrow--light"><?= e($cta['subtitle']) ?></p>
    <h2><?= e($cta['title']) ?></h2>
    <p><?= e($cta['body']) ?></p>
    <?= section_button($cta, 'btn btn--secondary btn--lg') ?>
  </div>
</section>
<?php endif; endif; ?>

<?php if ($albums):
  $gh = $map['gallery_header'] ?? ['title'=>'Galería','subtitle'=>''];
?>
<section class="section gallery-preview">
  <div class="container">
    <div class="section-head">
      <p class="eyebrow"><?= e($gh['subtitle'] ?? '') ?></p>
      <h2><?= e($gh['title'] ?? '') ?></h2>
    </div>
    <div class="gallery-preview__grid">
      <?php foreach ($albums as $al): ?>
        <a class="album-card" href="<?= e(base_url('galeria/' . $al['slug'])) ?>">
          <img src="<?= e(asset_url($al['cover_image'] ?: 'assets/img/gallery/g1.svg')) ?>" alt="<?= e($al['title']) ?>" loading="lazy">
          <span class="album-card__title"><?= e($al['title']) ?></span>
          <span class="album-card__count"><?= (int)$al['photo_count'] ?> fotos</span>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="section-actions">
      <a class="btn btn--outline" href="<?= e(base_url('galeria')) ?>">Ver toda la galería</a>
    </div>
  </div>
</section>
<?php endif; ?>
