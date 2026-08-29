<?php
/** Menú del comensal. @var \MenuGold\Core\View $view */
use MenuGold\Core\Lang;
use MenuGold\Models\Restaurant;

$view->extend('layouts/menu');
$r = $cfg;
$canOrder = $r['order_mode'] !== 'catalog';
$logo = $r['logo'];
?>

<!-- Portada -->
<header class="cover">
  <div class="cover-bg" data-parallax="0.1">
    <?= mg_img($r['cover'], array('alt' => '', 'sizes' => '100vw', 'loading' => 'eager', 'fetchpriority' => 'high')) ?>
  </div>
  <div class="cover-veil" aria-hidden="true"></div>

  <div>
    <div class="cover-logo">
      <?php if ($logo !== ''): ?>
        <?= mg_img($logo, array('alt' => $r['name'], 'sizes' => '110px', 'loading' => 'eager')) ?>
      <?php else: ?>
        <?= e(\MenuGold\Core\Str::initials($r['name'])) ?>
      <?php endif; ?>
    </div>
    <h1 class="display" data-split data-reveal><?= e($r['name']) ?></h1>
    <?php if ($r['tagline'] !== ''): ?>
      <p class="cover-tagline reveal" style="--d:200ms"><?= e($r['tagline']) ?></p>
    <?php endif; ?>

    <p class="cover-state reveal" style="--d:300ms">
      <span class="dot-live<?= $state['open'] ? '' : ' is-off' ?>" aria-hidden="true"></span>
      <span><?= e($state['open'] ? Lang::get('menu.open') : Lang::get('menu.closed')) ?><?php
        if ($state['open'] && $state['closes_at']) { echo ' · hasta ' . e(substr($state['closes_at'], 0, 5)); }
        elseif (!$state['open'] && $state['opens_at']) { echo ' · abre ' . e(substr($state['opens_at'], 0, 5)); }
      ?></span>
    </p>

    <div class="cover-actions reveal" style="--d:400ms">
      <a class="btn" id="enter-menu" href="#menu-sheet"><?= e(Lang::get('menu.view_menu')) ?></a>
      <?php if ($table): ?>
        <span class="cover-table">Estás en <?= e($table['name']) ?></span>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="sheet" id="menu-sheet">

  <?php if (!empty($badTableLink)): ?>
    <div style="padding:1rem var(--gutter) 0">
      <div class="alert alert-error">El enlace de la mesa no es válido o caducó. Puedes ver el menú, pero para pedir desde el salón escanea de nuevo el QR de tu mesa.</div>
    </div>
  <?php endif; ?>

  <?php if (!$state['open']): ?>
    <div style="padding:1rem var(--gutter) 0">
      <div class="alert">Ahora mismo estamos cerrados. Puedes ver el menú completo<?= $state['opens_at'] ? ' y volver a partir de las ' . e(substr($state['opens_at'], 0, 5)) : '' ?>.</div>
    </div>
  <?php endif; ?>

  <!-- Barra pegajosa -->
  <div class="menu-bar">
    <div class="menu-bar-top">
      <span class="menu-bar-title"><?= e($r['name']) ?></span>

      <button class="icon-btn" id="search-toggle" type="button" aria-expanded="false" aria-controls="search-wrap" aria-label="<?= e(Lang::get('menu.search')) ?>">
        <svg width="17" height="17" viewBox="0 0 17 17" fill="none" aria-hidden="true"><circle cx="7.5" cy="7.5" r="5.2" stroke="currentColor" stroke-width="1.5"/><path d="m11.6 11.6 3.2 3.2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      </button>

      <?php if (count($langs) > 1): ?>
        <div class="lang-switch">
          <?php foreach ($langs as $l): ?>
            <a href="<?= e(mg_url('/idioma/' . $l)) ?>" class="<?= Lang::locale() === $l ? 'is-on' : '' ?>"><?= e($l) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="search-wrap" id="search-wrap">
      <input class="input" id="search-input" type="search" placeholder="<?= e(Lang::get('menu.search')) ?>" autocomplete="off" aria-label="<?= e(Lang::get('menu.search')) ?>">
    </div>

    <?php if ($categories): ?>
      <nav class="cats" id="cat-bar" aria-label="Categorías del menú">
        <?php foreach ($categories as $i => $c): ?>
          <button type="button" class="cat-link<?= $i === 0 ? ' is-on' : '' ?>" data-target="<?= e($c['anchor']) ?>"><?= e($c['label']) ?></button>
        <?php endforeach; ?>
        <span class="cat-underline" aria-hidden="true"></span>
      </nav>
    <?php endif; ?>
  </div>

  <?php if (!$categories): ?>
    <div style="padding:4rem var(--gutter);text-align:center">
      <h2 class="display" style="font-size:var(--step-2)">El menú se está preparando</h2>
      <p class="muted" style="margin-top:.8rem">Vuelve en un momento.</p>
    </div>
  <?php endif; ?>

  <p id="search-empty" class="muted" style="display:none;text-align:center;padding:3rem var(--gutter)"><?= e(Lang::get('menu.no_results')) ?></p>

  <?php foreach ($categories as $ci => $c): ?>
    <section class="cat-block" id="<?= e($c['anchor']) ?>">
      <div class="cat-head">
        <span class="numeral"><?= e($c['roman'] !== '' ? $c['roman'] : mg_roman($ci + 1)) ?></span>
        <div>
          <h2><?= e($c['label']) ?></h2>
          <?php if ($c['blurb'] !== ''): ?><p><?= e($c['blurb']) ?></p><?php endif; ?>
        </div>
      </div>

      <div class="dishes">
        <?php foreach ($c['products'] as $pi => $p):
          $out = (int)$p['is_sold_out'] === 1;
          $search = mb_strtolower($p['label'] . ' ' . $p['blurb'] . ' ' . $c['label']);
        ?>
          <button type="button"
                  class="dish zoomer reveal<?= $out ? ' is-out' : '' ?>"
                  style="--d:<?= ($pi % 3) * 70 ?>ms"
                  data-dish="<?= (int)$p['id'] ?>"
                  data-search="<?= e($search) ?>"
                  aria-label="<?= e($p['label'] . ' · ' . mg_money($p['final_price'], $r['currency'])) ?>">
            <span class="dish-photo">
              <?= mg_img($p['image'], array('alt' => $p['label'], 'sizes' => '(min-width: 1080px) 33vw, (min-width: 660px) 50vw, 100vw')) ?>
              <?php if ($p['tags_list'] || $p['has_discount']): ?>
                <span class="dish-tags">
                  <?php if ($p['has_discount']): ?><span class="chip chip-solid">Promo</span><?php endif; ?>
                  <?php foreach (array_slice($p['tags_list'], 0, 2) as $t): ?>
                    <span class="chip"><?= e(mg_tag_label($t)) ?></span>
                  <?php endforeach; ?>
                </span>
              <?php endif; ?>
              <?php if ($out): ?><span class="out-flag"><?= e(Lang::get('menu.sold_out')) ?></span><?php endif; ?>
            </span>

            <span class="dish-body">
              <span class="dish-name"><?= e($p['label']) ?></span>
              <?php if ($p['blurb'] !== ''): ?><span class="dish-desc"><?= e($p['blurb']) ?></span><?php endif; ?>
              <span class="dish-foot">
                <span class="dish-price">
                  <?php if ($p['has_discount']): ?><s><?= e(mg_money($p['price'], $r['currency'])) ?></s><?php endif; ?>
                  <?= e(mg_money($p['final_price'], $r['currency'])) ?>
                </span>
                <?php if ($canOrder && !$out): ?><span class="dish-plus" aria-hidden="true">+</span><?php endif; ?>
              </span>
            </span>
          </button>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <?php if ($table && $canOrder): ?>
    <div class="table-actions">
      <button class="btn btn-ghost btn-sm" type="button" data-call="waiter"><?= e(Lang::get('menu.call_waiter')) ?></button>
      <button class="btn btn-ghost btn-sm" type="button" data-call="bill"><?= e(Lang::get('menu.request_bill')) ?></button>
    </div>
  <?php endif; ?>

  <footer class="menu-footer">
    <div class="brand" style="justify-content:center">
      <span class="brand-mark" aria-hidden="true">M</span><span><?= e($r['name']) ?></span>
    </div>
    <?php if ($r['address'] !== ''): ?>
      <p><?= e($r['address']) ?><?= $r['city'] !== '' ? ', ' . e($r['city']) : '' ?></p>
    <?php endif; ?>
    <?php if ($r['phone'] !== ''): ?><p><a class="link-line" href="tel:<?= e(preg_replace('/\s+/', '', $r['phone'])) ?>"><?= str_replace(' ', '&nbsp;', e($r['phone'])) ?></a></p><?php endif; ?>
    <?php if ($r['map_url'] !== ''): ?><p style="margin-top:.6rem"><a class="link-line gold" href="<?= e($r['map_url']) ?>" target="_blank" rel="noopener">Cómo llegar</a></p><?php endif; ?>
    <p style="margin-top:1.4rem;font-size:11px">Menú digital por <a class="link-line" href="<?= e(mg_url('/')) ?>">MenúGold</a></p>
  </footer>
</div>

<?php if ($canOrder): ?>
  <button class="cart-fab" id="cart-fab" type="button">
    <span class="cart-count" id="cart-count" aria-live="polite">0</span>
    <span><?= e(Lang::get('menu.cart')) ?></span>
    <span class="cart-total" id="cart-total">—</span>
  </button>
<?php endif; ?>
