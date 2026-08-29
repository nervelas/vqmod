<?php
/** Ficha del producto sin JavaScript (o abierta directamente por enlace). */
use MenuGold\Core\Lang;
$view->extend('layouts/menu');
$r = $cfg;
$p = $product;
$view->set('canonical', \MenuGold\Core\Url::abs('/producto/' . (int)$p['id']));
$view->set('title', $p['label'] . ' · ' . $r['name']);
$view->set('description', mb_substr($p['about'] !== '' ? $p['about'] : $p['label'], 0, 160));
?>
<div class="sheet" style="margin-top:0;border-radius:0">
  <div class="menu-bar">
    <div class="menu-bar-top">
      <a class="icon-btn" href="<?= e(mg_url('/')) ?>" aria-label="Volver al menú">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 3 5 8l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
      <span class="menu-bar-title"><?= e($r['name']) ?></span>
    </div>
  </div>

  <div style="max-width:760px;margin-inline:auto">
    <div class="sheet-photo">
      <?= mg_img($p['image'], array('alt' => $p['label'], 'sizes' => '(min-width: 760px) 760px, 100vw', 'loading' => 'eager')) ?>
    </div>
    <div class="sheet-content">
      <?php if ($p['category_label'] !== ''): ?>
        <p class="eyebrow is-plain" style="margin-bottom:.7rem"><?= e($p['category_label']) ?></p>
      <?php endif; ?>
      <h1 class="display" style="font-size:var(--step-3)"><?= e($p['label']) ?></h1>
      <?php if ($p['about'] !== ''): ?><p class="sheet-about"><?= e($p['about']) ?></p><?php endif; ?>

      <?php if ($p['tags_list']): ?>
        <div class="row" style="margin-top:1rem">
          <?php foreach ($p['tags_list'] as $t): ?><span class="chip"><?= e(mg_tag_label($t)) ?></span><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php foreach ($p['groups'] as $g): ?>
        <div class="opt-group">
          <div class="opt-head">
            <h2 class="opt-group-title"><?= e($g['label']) ?></h2>
            <?php if ((int)$g['is_required'] === 1): ?><span class="chip"><?= e(Lang::get('menu.required')) ?></span><?php endif; ?>
          </div>
          <div class="opt-list">
            <?php foreach ($g['options'] as $o): ?>
              <div class="opt">
                <span class="opt-mark<?= $g['type'] === 'multi' ? ' is-square' : '' ?>" aria-hidden="true"></span>
                <span class="opt-name"><?= e($o['label']) ?></span>
                <?php if ((float)$o['price_delta'] > 0): ?><span class="opt-price">+<?= e(mg_money($o['price_delta'], $r['currency'])) ?></span><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="totals" style="margin-top:2rem">
        <div class="is-total"><span><?= e(Lang::get('menu.total')) ?></span><span><?= e(mg_money($p['final_price'], $r['currency'])) ?></span></div>
      </div>

      <p class="mt-2">
        <a class="btn btn-block" href="<?= e(mg_url('/')) ?>#<?= e('cat-' . (int)$p['category_id']) ?>">Volver al menú y pedir</a>
      </p>
      <p class="field-hint" style="text-align:center">Para agregar al pedido, abre el platillo desde el menú.</p>
    </div>
  </div>
</div>
