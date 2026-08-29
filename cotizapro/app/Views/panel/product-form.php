<?php
$sym = (string) $company['currency_symbol'];
$action = $p ? url('/panel/productos/' . $p['id']) : url('/panel/productos/nuevo');
$v = static fn (string $k, mixed $d = ''): mixed => $p[$k] ?? old($k, $d);
page('barActions', $p
    ? '<a class="btn btn--ghost btn--sm" href="' . e(url('/e/' . $company['slug'] . '/producto/' . $p['slug'])) . '" target="_blank" rel="noopener">Ver en el sitio</a>'
    : '');
?>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="cols cols--sidebar">
    <div class="stack">
      <div class="card">
        <div class="card__head"><span class="secnum">01/</span><h2>Identificación</h2></div>
        <div class="card__body">
          <div class="row-2">
            <div class="field"><label for="code">Código / SKU</label>
              <input class="input" id="code" name="code" maxlength="60" value="<?= e($v('code')) ?>" placeholder="SM-T21-25"></div>
            <div class="field"><label for="unit">Unidad de venta</label>
              <input class="input" id="unit" name="unit" maxlength="20" value="<?= e($v('unit', 'unidad')) ?>" placeholder="unidad, metro, juego"></div>
          </div>
          <div class="field"><label for="name">Nombre del producto *</label>
            <input class="input" id="name" name="name" maxlength="200" required value="<?= e($v('name')) ?>"></div>
          <div class="field"><label for="short_desc">Descripción corta</label>
            <input class="input" id="short_desc" name="short_desc" maxlength="300" value="<?= e($v('short_desc')) ?>" placeholder="Sello de fuelle para bombas centrífugas"></div>
          <div class="field"><label for="description">Descripción técnica</label>
            <textarea class="textarea" id="description" name="description" rows="6" maxlength="12000"><?= e($v('description')) ?></textarea></div>
          <div class="row-2">
            <div class="field"><label for="application">Aplicación</label>
              <input class="input" id="application" name="application" maxlength="255" value="<?= e($v('application')) ?>" placeholder="Bombas de proceso, agua tratada"></div>
            <div class="field"><label for="lead_time">Tiempo de entrega</label>
              <input class="input" id="lead_time" name="lead_time" maxlength="60" value="<?= e($v('lead_time')) ?>" placeholder="Inmediato / 8 días"></div>
          </div>
        </div>
      </div>

      <?php if ($attrs): ?>
      <div class="card">
        <div class="card__head"><span class="secnum">02/</span><h2>Atributos técnicos</h2>
          <a class="btn btn--ghost btn--xs ml-auto" href="<?= e(url('/panel/atributos')) ?>">Administrar atributos</a></div>
        <div class="card__body">
          <div class="row">
            <?php foreach ($attrs as $a): $val = (string) ($values[$a['id']] ?? ''); $opts = \App\Models\AttributeDef::options($a); ?>
              <div class="field">
                <label for="attr<?= e($a['id']) ?>"><?= e($a['label']) ?><?= $a['unit'] ? ' (' . e($a['unit']) . ')' : '' ?></label>
                <?php if ($a['type'] === 'lista' && $opts): ?>
                  <select class="select" id="attr<?= e($a['id']) ?>" name="attr[<?= e($a['id']) ?>]">
                    <option value="">—</option>
                    <?php foreach ($opts as $o): ?><option value="<?= e($o) ?>"<?= $val === $o ? ' selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
                  </select>
                <?php elseif ($a['type'] === 'booleano'): ?>
                  <select class="select" id="attr<?= e($a['id']) ?>" name="attr[<?= e($a['id']) ?>]">
                    <option value="">—</option>
                    <option value="Sí"<?= $val === 'Sí' ? ' selected' : '' ?>>Sí</option>
                    <option value="No"<?= $val === 'No' ? ' selected' : '' ?>>No</option>
                  </select>
                <?php else: ?>
                  <input class="input" id="attr<?= e($a['id']) ?>" name="attr[<?= e($a['id']) ?>]" maxlength="190" value="<?= e($val) ?>"
                         <?= $a['type'] === 'numero' ? 'inputmode="decimal"' : '' ?>>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($p && (int) $p['category_id'] === 0): ?>
            <p class="hint">Elija una categoría y guarde para ver los atributos específicos de esa línea.</p>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="card">
        <div class="card__head"><span class="secnum">03/</span><h2>Fotografías</h2></div>
        <div class="card__body">
          <?php if ($images): ?>
            <div class="imggrid" style="margin-bottom:16px">
              <?php foreach ($images as $i => $im): ?>
                <div class="imgcell">
                  <img src="<?= e(upload($im['path_thumb'] ?: $im['path'])) ?>" alt="<?= e($im['alt']) ?>" loading="lazy" width="128" height="96">
                  <?php if ($i === 0): ?><span class="main-flag">PRINCIPAL</span><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="flex flex-wrap" style="gap:6px;margin-bottom:16px">
              <?php foreach ($images as $im): ?>
                <button class="btn btn--ghost btn--xs" type="submit" form="delimg<?= e($im['id']) ?>">Quitar <?= e(str_limit((string) $im['alt'], 16)) ?></button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="field"><label for="images">Agregar imágenes (JPG, PNG o WebP)</label>
            <input class="input" id="images" name="images[]" type="file" accept="image/*" multiple data-preview="imgprev">
            <p class="hint">Se recomprimen automáticamente a WebP con respaldo JPG y se les eliminan los metadatos.</p></div>
          <div class="imggrid" id="imgprev"></div>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><span class="secnum">04/</span><h2>Documentos (fichas técnicas)</h2></div>
        <div class="card__body">
          <?php if ($documents): ?>
            <div class="stack-sm" style="margin-bottom:16px">
              <?php foreach ($documents as $d): ?>
                <div class="doclink">
                  <span aria-hidden="true">▤</span>
                  <span><b><?= e($d['name']) ?></b><br><small><?= e(\App\Controllers\Super\PlatformController::human((int) $d['size'])) ?></small></span>
                  <button class="btn btn--ghost btn--xs ml-auto" type="submit" form="deldoc<?= e($d['id']) ?>">Quitar</button>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="field"><label for="documents">Agregar PDF</label>
            <input class="input" id="documents" name="documents[]" type="file" accept="application/pdf" multiple>
            <p class="hint">Solo PDF, hasta 15 MB por archivo.</p></div>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><span class="secnum">05/</span><h2>SEO</h2></div>
        <div class="card__body">
          <div class="field"><label for="seo_title">Título SEO</label>
            <input class="input" id="seo_title" name="seo_title" maxlength="190" value="<?= e($v('seo_title')) ?>"></div>
          <div class="field"><label for="seo_description">Descripción SEO</label>
            <input class="input" id="seo_description" name="seo_description" maxlength="300" value="<?= e($v('seo_description')) ?>"></div>
          <div class="field"><label for="slug">URL amigable</label>
            <input class="input" id="slug" name="slug" maxlength="220" value="<?= e($v('slug')) ?>"></div>
        </div>
      </div>
    </div>

    <!-- lateral -->
    <div class="stack">
      <div class="card">
        <div class="card__head"><h2>Publicación</h2></div>
        <div class="card__body">
          <label class="check"><input type="checkbox" name="active" value="1"<?= ($p ? (int) $p['active'] : 1) ? ' checked' : '' ?>><span>Visible en el catálogo público</span></label>
          <label class="check"><input type="checkbox" name="featured" value="1"<?= ($p && $p['featured']) ? ' checked' : '' ?>><span>Destacado en la portada</span></label>
          <hr style="margin:14px 0">
          <div class="field"><label for="category_id">Categoría</label>
            <select class="select" id="category_id" name="category_id">
              <option value="">— Sin categoría —</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= e($c['id']) ?>"<?= (int) ($p['category_id'] ?? 0) === $c['id'] ? ' selected' : '' ?>><?= e($c['label']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="field"><label for="brand_id">Marca</label>
            <select class="select" id="brand_id" name="brand_id">
              <option value="">— Sin marca —</option>
              <?php foreach ($brands as $b): ?>
                <option value="<?= e($b['id']) ?>"<?= (int) ($p['brand_id'] ?? 0) === (int) $b['id'] ? ' selected' : '' ?>><?= e($b['name']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <button class="btn btn--accent btn--block" type="submit">Guardar producto</button>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><h2>Precio y visibilidad</h2></div>
        <div class="card__body">
          <div class="row-2">
            <div class="field"><label for="price">Precio de lista (<?= e($sym) ?>)</label>
              <input class="input" id="price" name="price" type="number" step="0.01" min="0" value="<?= e((float) $v('price', 0)) ?>"></div>
            <div class="field"><label for="cost">Costo (interno)</label>
              <input class="input" id="cost" name="cost" type="number" step="0.01" min="0" value="<?= e((float) $v('cost', 0)) ?>"></div>
          </div>
          <div class="field"><label for="price_visibility">Visibilidad del precio</label>
            <select class="select" id="price_visibility" name="price_visibility">
              <?php foreach ([
                'heredar'  => 'Como la empresa (' . ['publico' => 'público', 'clientes' => 'solo clientes', 'oculto' => 'oculto'][$company['price_visibility']] . ')',
                'publico'  => 'Visible para todos',
                'clientes' => 'Solo para clientes registrados',
                'oculto'   => 'Oculto (solo a cotizar)',
              ] as $k => $lbl): ?>
                <option value="<?= e($k) ?>"<?= ($p['price_visibility'] ?? 'heredar') === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="row-2">
            <div class="field"><label for="min_qty">Cantidad mínima</label>
              <input class="input" id="min_qty" name="min_qty" type="number" step="0.01" min="0.01" value="<?= e((float) $v('min_qty', 1)) ?>"></div>
            <div class="field"><label for="stock_note">Nota de existencia</label>
              <input class="input" id="stock_note" name="stock_note" maxlength="60" value="<?= e($v('stock_note')) ?>" placeholder="En existencia"></div>
          </div>

          <?php if ($priceLists): ?>
            <hr style="margin:14px 0">
            <div class="label" style="margin-bottom:10px">Precio por lista de clientes</div>
            <?php foreach ($priceLists as $pl): ?>
              <div class="field">
                <label for="pl<?= e($pl['id']) ?>"><?= e($pl['name']) ?><?= (float) $pl['discount_pct'] > 0 ? ' (−' . e(qty((float) $pl['discount_pct'])) . '% por defecto)' : '' ?></label>
                <input class="input" id="pl<?= e($pl['id']) ?>" name="plist[<?= e($pl['id']) ?>]" type="number" step="0.01" min="0"
                       value="<?= e(isset($listPrices[$pl['id']]) ? (float) $listPrices[$pl['id']] : '') ?>" placeholder="Automático">
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($p): ?>
        <div class="card">
          <div class="card__head"><h2>Acciones</h2></div>
          <div class="card__body stack-sm">
            <p class="small muted" style="margin:0"><?= e((int) $p['views']) ?> vistas · <?= e((int) $p['quote_count']) ?> veces cotizado</p>
            <button class="btn btn--ghost btn--block" type="submit" form="dupform">Duplicar producto</button>
            <?php if (\App\Core\Auth::isAdmin()): ?>
              <button class="btn btn--danger btn--block" type="submit" form="delform">Eliminar producto</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php if ($p): ?>
  <form id="dupform" method="post" action="<?= e(url('/panel/productos/' . $p['id'] . '/duplicar')) ?>" class="hide"><?= csrf_field() ?></form>
  <form id="delform" method="post" action="<?= e(url('/panel/productos/' . $p['id'] . '/eliminar')) ?>" class="hide" data-confirm="¿Eliminar este producto y todas sus imágenes? No se puede deshacer."><?= csrf_field() ?></form>
  <?php foreach ($images as $im): ?>
    <form id="delimg<?= e($im['id']) ?>" method="post" action="<?= e(url('/panel/productos/imagen/' . $im['id'] . '/eliminar')) ?>" class="hide"><?= csrf_field() ?></form>
  <?php endforeach; ?>
  <?php foreach ($documents as $d): ?>
    <form id="deldoc<?= e($d['id']) ?>" method="post" action="<?= e(url('/panel/productos/documento/' . $d['id'] . '/eliminar')) ?>" class="hide"><?= csrf_field() ?></form>
  <?php endforeach; ?>
<?php endif; ?>
