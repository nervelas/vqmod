<?php
$base = rtrim(url('/'), '/');
$main = $images[0] ?? null;
$mainSrc = $main ? upload($main['path_webp'] ?: $main['path']) : url('/assets/img/cards/generico.svg');
$sym = (string) $company['currency_symbol'];
$ld = [
    '@context' => 'https://schema.org',
    '@type'    => 'Product',
    'name'     => $p['name'],
    'sku'      => $p['code'],
    'description' => str_limit((string) ($p['short_desc'] ?: $p['description']), 300),
    'image'    => $main ? absUrl(parse_url(upload($main['path']), PHP_URL_PATH) ?: '') : absUrl('/assets/img/cards/generico.svg'),
    'url'      => absUrl('/producto/' . $p['slug']),
    'brand'    => ['@type' => 'Brand', 'name' => $p['brand_name'] ?: $company['name']],
    'category' => $p['category_name'] ?: 'Industrial',
];
if ($attributes) {
    $ld['additionalProperty'] = array_map(static fn ($a) => [
        '@type' => 'PropertyValue', 'name' => $a['label'], 'value' => $a['value'] . ($a['unit'] ? ' ' . $a['unit'] : ''),
    ], $attributes);
}
if ($showPrice && (float) $p['price'] > 0) {
    $ld['offers'] = [
        '@type' => 'Offer', 'price' => number_format((float) $p['price'], 2, '.', ''),
        'priceCurrency' => 'GTQ', 'availability' => 'https://schema.org/InStock',
        'url' => $ld['url'], 'seller' => ['@type' => 'Organization', 'name' => $company['name']],
    ];
} else {
    $ld['offers'] = ['@type' => 'Offer', 'availability' => 'https://schema.org/InStock', 'url' => $ld['url'],
        'priceSpecification' => ['@type' => 'PriceSpecification', 'priceCurrency' => 'GTQ']];
}
$org = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $company['name'],
    'url' => absUrl('/'),
    'telephone' => (string) $company['phone'], 'email' => (string) $company['email'],
    'address' => ['@type' => 'PostalAddress', 'streetAddress' => (string) $company['address'],
        'addressLocality' => (string) $company['city'], 'addressCountry' => (string) $company['country']]];
$crumbLd = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => []];
$pos = 1;
$crumbLd['itemListElement'][] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => 'Catálogo', 'item' => absUrl('/catalogo')];
foreach ($crumbs as $c) {
    $crumbLd['itemListElement'][] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $c['name'], 'item' => absUrl('/categoria/' . $c['slug'])];
}
$crumbLd['itemListElement'][] = ['@type' => 'ListItem', 'position' => $pos, 'name' => $p['name'], 'item' => $ld['url']];
?>
<script type="application/ld+json" nonce="<?= e($nonce ?? '') ?>"><?= json_encode([$ld, $org, $crumbLd], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<div class="section section--tight blueprint" style="padding-top:34px">
  <div class="wrap">
    <nav class="crumbs" aria-label="Ruta">
      <a href="<?= e(url("/")) ?>">Inicio</a><span aria-hidden="true">/</span>
      <a href="<?= e($base . '/catalogo') ?>">Catálogo</a>
      <?php foreach ($crumbs as $c): ?>
        <span aria-hidden="true">/</span><a href="<?= e($base . '/categoria/' . $c['slug']) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <span aria-hidden="true">/</span><span><?= e($p['code']) ?></span>
    </nav>

    <div class="pdp">
      <!-- galería -->
      <div data-gallery>
        <div class="gallery__main brackets">
          <img data-gallery-main src="<?= e($mainSrc) ?>" alt="<?= e($main['alt'] ?? $p['name']) ?>" width="900" height="675" fetchpriority="high" decoding="async">
        </div>
        <?php if (count($images) > 1): ?>
          <div class="gallery__thumbs" role="group" aria-label="Miniaturas">
            <?php foreach ($images as $i => $im): ?>
              <button type="button" data-gallery-thumb="<?= e(upload($im['path_webp'] ?: $im['path'])) ?>" data-alt="<?= e($im['alt'] ?: $p['name']) ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="Ver imagen <?= e($i + 1) ?>">
                <img src="<?= e(upload($im['path_thumb'] ?: $im['path'])) ?>" alt="" aria-hidden="true" loading="lazy" decoding="async" width="74" height="58">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- ficha -->
      <div>
        <div class="flex flex-wrap" style="gap:8px;margin-bottom:14px">
          <span class="code-chip"><?= e($p['code']) ?></span>
          <?php if ($p['brand_name']): ?><span class="badge"><?= e($p['brand_name']) ?></span><?php endif; ?>
          <?php if ($p['stock_note']): ?><span class="badge badge--ok"><?= e($p['stock_note']) ?></span><?php endif; ?>
        </div>
        <h1 class="h1" style="font-size:clamp(1.7rem,3vw,2.5rem)"><?= e($p['name']) ?></h1>
        <?php if ($p['short_desc']): ?><p class="lead" style="margin-top:16px"><?= e($p['short_desc']) ?></p><?php endif; ?>

        <div class="flex flex-wrap" style="gap:18px;align-items:flex-end;margin:26px 0 22px;padding-bottom:22px;border-bottom:1px solid var(--line)">
          <div>
            <div class="label" style="margin-bottom:4px">Precio</div>
            <?php if ($showPrice && (float) $p['price'] > 0): ?>
              <div style="font-family:var(--f-display);font-size:1.9rem;letter-spacing:-.03em"><?= e(money((float) $p['price'], $sym)) ?>
                <span class="small muted" style="font-family:var(--f-text);letter-spacing:0"> / <?= e($p['unit']) ?></span></div>
            <?php else: ?>
              <div style="font-family:var(--f-display);font-size:1.35rem;letter-spacing:-.02em">Solicite cotización</div>
              <p class="small muted" style="margin:4px 0 0">Le enviamos precio y disponibilidad el mismo día.</p>
            <?php endif; ?>
          </div>
          <?php if ($p['lead_time']): ?>
            <div>
              <div class="label" style="margin-bottom:4px">Tiempo de entrega</div>
              <div style="font-weight:600"><?= e($p['lead_time']) ?></div>
            </div>
          <?php endif; ?>
        </div>

        <div class="flex flex-wrap" style="gap:12px;align-items:stretch">
          <div class="qtybox">
            <button type="button" data-step="-" aria-label="Disminuir cantidad">&minus;</button>
            <label class="sr-only" for="qty">Cantidad</label>
            <input id="qty" type="number" min="<?= e(qty((float) $p['min_qty'])) ?>" step="<?= e((float) $p['min_qty'] < 1 ? '0.01' : '1') ?>" value="<?= e(qty((float) $p['min_qty'])) ?>">
            <button type="button" data-step="+" aria-label="Aumentar cantidad">+</button>
          </div>
          <button class="btn btn--accent" type="button" data-add-cart="<?= e($p['id']) ?>" data-qty-from="qty" data-note-from="pnote" style="flex:1;min-width:200px">
            Agregar a mi cotización <span class="arw" aria-hidden="true">&rarr;</span>
          </button>
        </div>
        <div class="field" style="margin-top:14px">
          <label for="pnote">Nota para este producto (opcional)</label>
          <input class="input" id="pnote" maxlength="300" placeholder="Ej.: para bomba Goulds 3196, eje de 1 1/8&quot;">
        </div>

        <?php if ($attributes): ?>
          <div style="margin-top:32px">
            <div class="cota" style="margin-bottom:14px">Especificaciones técnicas</div>
            <table class="spectable">
              <caption class="sr-only">Especificaciones técnicas de <?= e($p['name']) ?></caption>
              <tbody>
                <?php foreach ($attributes as $a): ?>
                  <tr><th scope="row"><?= e($a['label']) ?></th><td><?= e($a['value']) ?><?= $a['unit'] ? ' ' . e($a['unit']) : '' ?></td></tr>
                <?php endforeach; ?>
                <?php
                $labelsUsados = array_map(static fn ($a) => mb_strtolower((string) $a['label']), $attributes);
                if ($p['application'] && !in_array('aplicación', $labelsUsados, true)): ?>
                  <tr><th scope="row">Aplicación</th><td><?= e($p['application']) ?></td></tr>
                <?php endif; ?>
                <tr><th scope="row">Unidad de venta</th><td><?= e($p['unit']) ?></td></tr>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <?php if ($documents): ?>
          <div style="margin-top:30px">
            <div class="cota" style="margin-bottom:14px">Documentos</div>
            <div class="stack-sm">
              <?php foreach ($documents as $d): ?>
                <a class="doclink" href="<?= e(upload($d['path'])) ?>" target="_blank" rel="noopener">
                  <span aria-hidden="true" style="font-size:1.1rem">▤</span>
                  <span><b><?= e($d['name']) ?></b><br><small>PDF · <?= e(\App\Controllers\Super\PlatformController::human((int) $d['size'])) ?></small></span>
                  <span class="ml-auto" aria-hidden="true">&darr;</span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($p['description']): ?>
      <div class="grid12" style="margin-top:clamp(44px,6vw,80px)">
        <div style="grid-column:span 7">
          <span class="secnum">01/</span>
          <h2 class="h2" style="margin:12px 0 18px">Descripción</h2>
          <div style="max-width:66ch;color:var(--ink-soft)"><?= nl2br(e($p['description'])) ?></div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($related): ?>
<section class="section section--tight" style="background:var(--paper-2)">
  <div class="wrap">
    <div class="section__head">
      <div><span class="secnum">02/</span><h2 class="h2" style="margin-top:12px">Productos relacionados</h2></div>
      <a class="linkarrow" href="<?= e($base . '/catalogo') ?>">Ver todo <span aria-hidden="true">&rarr;</span></a>
    </div>
    <div class="pgrid">
      <?php foreach ($related as $r): ?>
        <?= \App\Core\View::partial('partials/pcard', ['p' => $r, 'company' => $company]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
