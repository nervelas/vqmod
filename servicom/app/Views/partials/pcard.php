<?php
/** Ficha de producto para cuadrícula. @var array $p */
$base = rtrim(url('/'), '/');
$img   = $p['image'] ?? null;
$src   = $img ? upload($img['path_thumb'] ?: $img['path']) : url('/assets/img/cards/generico.svg');
$alt   = $img && $img['alt'] ? $img['alt'] : $p['name'];
$show  = \App\Models\Product::priceVisible($company, $p);
$eager = !empty($eager);
?>
<article class="pcard">
  <div class="pcard__media blurup">
    <?php if ($img && !empty($img['blur'])): ?>
      <img class="blurup__ph" src="<?= e($img['blur']) ?>" alt="" aria-hidden="true">
    <?php endif; ?>
    <img src="<?= e($src) ?>" alt="<?= e($alt) ?>" width="600" height="450"<?= $eager ? ' fetchpriority="high" decoding="async"' : ' loading="lazy" decoding="async"' ?>>
    <span class="pcard__code"><?= e($p['code']) ?></span>
    <?php if (!empty($p['featured'])): ?><span class="pcard__flag badge badge--accent">Destacado</span><?php endif; ?>
  </div>
  <div class="pcard__body">
    <?php if (!empty($p['category_name'])): ?><span class="pcard__cat"><?= e($p['category_name']) ?></span><?php endif; ?>
    <h3 class="pcard__name"><a href="<?= e($base . '/producto/' . $p['slug']) ?>"><?= e($p['name']) ?></a></h3>
    <?php if (!empty($p['short_desc'])): ?><p class="pcard__desc"><?= e(str_limit((string) $p['short_desc'], 88)) ?></p><?php endif; ?>
    <div class="pcard__foot">
      <?php if ($show && (float) $p['price'] > 0): ?>
        <span class="pcard__price"><?= e(money((float) $p['price'], (string) $company['currency_symbol'])) ?></span>
      <?php else: ?>
        <span class="pcard__ask">Precio a cotizar</span>
      <?php endif; ?>
      <button class="btn btn--accent btn--xs pcard__btn" type="button" data-add-cart="<?= e($p['id']) ?>" data-qty="<?= e(qty((float) $p['min_qty'])) ?>" >
        Solicitar cotización
      </button>
    </div>
  </div>
</article>
