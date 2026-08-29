<?php
$base = url('/e/' . $company['slug']);
$qs = static function (array $over = []) use ($filters, $attrFilters, $view): string {
    $p = array_filter([
        'q'      => $filters['q'],
        'marca'  => $filters['brand_id'] ?: '',
        'orden'  => $filters['sort'],
        'vista'  => $view === 'lista' ? 'lista' : '',
    ], static fn ($v) => $v !== '' && $v !== 0);
    foreach ($attrFilters as $k => $v) { $p['a[' . $k . ']'] = $v; }
    foreach ($over as $k => $v) {
        if ($v === null) { unset($p[$k]); } else { $p[$k] = $v; }
    }
    $out = [];
    foreach ($p as $k => $v) { $out[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v); }
    // Los índices de a[...] ya vienen codificados dentro de la clave.
    $str = implode('&', $out);
    return $str === '' ? '' : '?' . str_replace(['a%5B', '%5D='], ['a[', ']='], $str);
};
$catUrl = $category ? $base . '/categoria/' . $category['slug'] : $base . '/catalogo';
?>
<div class="section section--tight blueprint" style="padding-top:34px">
  <div class="wrap">
    <nav class="crumbs" aria-label="Ruta">
      <a href="<?= e($base) ?>">Inicio</a><span aria-hidden="true">/</span>
      <a href="<?= e($base . '/catalogo') ?>">Catálogo</a>
      <?php foreach ($crumbs as $c): ?>
        <span aria-hidden="true">/</span><a href="<?= e($base . '/categoria/' . $c['slug']) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="section__head" style="margin-bottom:26px">
      <div>
        <span class="secnum"><?= e(str_pad((string) count($crumbs) ?: '01', 2, '0', STR_PAD_LEFT)) ?>/</span>
        <h1 class="h1" style="margin-top:12px"><?= e($category['name'] ?? 'Catálogo técnico') ?></h1>
        <?php if (!empty($category['description'])): ?>
          <p class="lead" style="margin-top:14px"><?= e(str_limit((string) $category['description'], 260)) ?></p>
        <?php endif; ?>
      </div>
      <form class="searchbox searchbox--light" method="get" action="<?= e($base . '/catalogo') ?>" role="search" style="margin:0;min-width:min(100%,380px)">
        <label class="sr-only" for="qc">Buscar en el catálogo</label>
        <input id="qc" name="q" type="search" value="<?= e($filters['q']) ?>" placeholder="Código, nombre o medida…" autocomplete="off"
               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="sugc" aria-haspopup="listbox"
               data-suggest="<?= e($base . '/sugerencias') ?>">
        <button class="btn btn--accent btn--sm" type="submit">Buscar</button>
        <div class="suggest" id="sugc" role="listbox" aria-label="Sugerencias"></div>
      </form>
    </div>

    <div class="catalog">
      <!-- filtros -->
      <aside>
        <h2 class="sr-only">Filtros del catálogo</h2>
        <button class="btn btn--ghost btn--block hide-md" style="display:none" type="button" data-filters-toggle aria-expanded="false" id="ftoggle">Filtros técnicos</button>
        <div class="filters">
          <div class="filters__group">
            <h3>Categorías</h3>
            <ul>
              <li><a href="<?= e($base . '/catalogo' . $qs()) ?>"<?= !$category ? ' aria-current="true"' : '' ?>>Todo el catálogo</a></li>
              <?php
              $renderTree = static function (array $nodes, int $depth) use (&$renderTree, $base, $category, $qs): void {
                  echo '<ul>';
                  foreach ($nodes as $n) {
                      $cur = $category && (int) $category['id'] === (int) $n['id'];
                      echo '<li><a href="' . e($base . '/categoria/' . $n['slug'] . $qs()) . '"' . ($cur ? ' aria-current="true"' : '') . '>'
                         . '<span>' . e($n['name']) . '</span><span>' . e((int) $n['product_count']) . '</span></a>';
                      if ($n['children']) { $renderTree($n['children'], $depth + 1); }
                      echo '</li>';
                  }
                  echo '</ul>';
              };
              foreach ($tree as $n) {
                  $cur = $category && (int) $category['id'] === (int) $n['id'];
                  echo '<li><a href="' . e($base . '/categoria/' . $n['slug'] . $qs()) . '"' . ($cur ? ' aria-current="true"' : '') . '>'
                     . '<span>' . e($n['name']) . '</span><span>' . e((int) $n['product_count']) . '</span></a>';
                  if ($n['children']) { $renderTree($n['children'], 1); }
                  echo '</li>';
              }
              ?>
            </ul>
          </div>

          <?php if ($brands): ?>
          <div class="filters__group">
            <h3>Marca</h3>
            <ul>
              <li><a href="<?= e($catUrl . $qs(['marca' => null])) ?>"<?= !$filters['brand_id'] ? ' aria-current="true"' : '' ?>>Todas</a></li>
              <?php foreach ($brands as $b): ?>
                <li><a href="<?= e($catUrl . $qs(['marca' => $b['id']])) ?>"<?= (int) $filters['brand_id'] === (int) $b['id'] ? ' aria-current="true"' : '' ?>>
                  <span><?= e($b['name']) ?></span><span><?= e((int) $b['product_count']) ?></span></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>

          <?php foreach ($facets as $f): $def = $f['def']; ?>
            <div class="filters__group">
              <h3><?= e($def['label']) ?><?= $def['unit'] ? ' (' . e($def['unit']) . ')' : '' ?></h3>
              <div class="pill-row">
                <?php foreach ($f['values'] as $v):
                  $on = ($attrFilters[$def['code']] ?? '') === $v['value']; ?>
                  <a class="pill<?= $on ? ' is-on' : '' ?>" href="<?= e($catUrl . $qs(['a[' . $def['code'] . ']' => $on ? null : $v['value']])) ?>">
                    <?= e($v['value']) ?><span class="muted small"><?= e((int) $v['n']) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>

          <?php if ($filters['q'] !== '' || $filters['brand_id'] || $attrFilters): ?>
            <div class="filters__group">
              <a class="btn btn--ghost btn--sm btn--block" href="<?= e($catUrl) ?>">Limpiar filtros</a>
            </div>
          <?php endif; ?>
        </div>
      </aside>

      <!-- resultados -->
      <div>
        <h2 class="sr-only">Productos encontrados</h2>
        <div class="toolbar">
          <span class="count"><strong><?= e(number_format($total)) ?></strong> producto<?= $total === 1 ? '' : 's' ?><?= $filters['q'] !== '' ? ' para “' . e($filters['q']) . '”' : '' ?></span>
          <div class="ml-auto flex flex-wrap" style="gap:10px">
            <form method="get" action="<?= e($catUrl) ?>" style="margin:0">
              <?php foreach (['q' => $filters['q'], 'marca' => $filters['brand_id'], 'vista' => $view === 'lista' ? 'lista' : ''] as $k => $v): ?>
                <?php if ($v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; ?>
              <?php endforeach; ?>
              <?php foreach ($attrFilters as $k => $v): ?><input type="hidden" name="a[<?= e($k) ?>]" value="<?= e($v) ?>"><?php endforeach; ?>
              <label class="sr-only" for="orden">Ordenar</label>
              <select class="select" id="orden" name="orden" data-autosubmit>
                <?php foreach (['' => 'Relevancia', 'nombre' => 'Nombre A–Z', 'codigo' => 'Código', 'cotizados' => 'Más cotizados', 'nuevos' => 'Más recientes'] as $k => $lbl): ?>
                  <option value="<?= e($k) ?>"<?= $filters['sort'] === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
            <div class="viewtoggle" role="group" aria-label="Modo de vista">
              <a href="<?= e($catUrl . $qs(['vista' => null])) ?>"<?= $view !== 'lista' ? ' aria-current="true"' : '' ?>>Cuadrícula</a>
              <a href="<?= e($catUrl . $qs(['vista' => 'lista'])) ?>"<?= $view === 'lista' ? ' aria-current="true"' : '' ?>>Lista técnica</a>
            </div>
          </div>
        </div>

        <?php if (!$products): ?>
          <div class="empty">
            <h3 class="h3">Sin resultados</h3>
            <p>No encontramos productos con esos criterios. Pruebe con el código parcial o escríbanos y lo buscamos por usted.</p>
            <a class="btn btn--accent" style="margin-top:20px" href="<?= e($base . '/contacto') ?>">Solicitar búsqueda <span class="arw" aria-hidden="true">&rarr;</span></a>
          </div>
        <?php elseif ($view === 'lista'): ?>
          <div class="plist">
            <?php foreach ($products as $p):
              $img = $p['image'] ?? null;
              $show = \App\Models\Product::priceVisible($company, $p); ?>
              <div class="plist__row">
                <img src="<?= e($img ? upload($img['path_thumb'] ?: $img['path']) : url('/assets/img/plates/sello-mecanico.svg')) ?>" alt="" aria-hidden="true" loading="lazy" decoding="async" width="104" height="78">
                <div>
                  <div class="flex flex-wrap" style="gap:8px;align-items:baseline">
                    <span class="code-chip"><?= e($p['code']) ?></span>
                    <a style="font-family:var(--f-display);font-size:1rem;letter-spacing:-.015em" href="<?= e($base . '/producto/' . $p['slug']) ?>"><?= e($p['name']) ?></a>
                  </div>
                  <?php if (!empty($p['short_desc'])): ?><p class="small muted" style="margin:5px 0 0"><?= e(str_limit((string) $p['short_desc'], 120)) ?></p><?php endif; ?>
                  <div class="plist__specs">
                    <?php if (!empty($p['category_name'])): ?><span><?= e($p['category_name']) ?></span><?php endif; ?>
                    <?php if (!empty($p['brand_name'])): ?><span>Marca: <?= e($p['brand_name']) ?></span><?php endif; ?>
                    <?php if (!empty($p['lead_time'])): ?><span>Entrega: <?= e($p['lead_time']) ?></span><?php endif; ?>
                  </div>
                </div>
                <div class="plist__actions">
                  <?php if ($show && (float) $p['price'] > 0): ?>
                    <span class="pcard__price"><?= e(money((float) $p['price'], (string) $company['currency_symbol'])) ?></span>
                  <?php else: ?>
                    <span class="pcard__ask">A cotizar</span>
                  <?php endif; ?>
                  <button class="btn btn--accent btn--sm" type="button" data-add-cart="<?= e($p['id']) ?>" data-qty="<?= e(qty((float) $p['min_qty'])) ?>">Agregar</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="pgrid">
            <?php foreach ($products as $i => $p): ?>
              <?= \App\Core\View::partial('partials/pcard', ['p' => $p, 'company' => $company, 'eager' => $i < 3]) ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
          <nav class="pager" aria-label="Paginación">
            <?php
            $mk = static fn (int $n): string => $catUrl . $qs(['p' => $n > 1 ? $n : null]);
            if ($page > 1) { echo '<a href="' . e($mk($page - 1)) . '" rel="prev">Anterior</a>'; }
            $from = max(1, $page - 2); $to = min($pages, $page + 2);
            if ($from > 1) { echo '<a href="' . e($mk(1)) . '">1</a><span>…</span>'; }
            for ($i = $from; $i <= $to; $i++) {
                echo $i === $page ? '<span class="is-cur" aria-current="page">' . $i . '</span>' : '<a href="' . e($mk($i)) . '">' . $i . '</a>';
            }
            if ($to < $pages) { echo '<span>…</span><a href="' . e($mk($pages)) . '">' . $pages . '</a>'; }
            if ($page < $pages) { echo '<a href="' . e($mk($page + 1)) . '" rel="next">Siguiente</a>'; }
            ?>
          </nav>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
