<?php
/** Menú: categorías y platillos. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Menú');
$cur = $restaurant['currency'];
?>
<?php $view->start('actions') ?>
  <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/menu/importar')) ?>">Importar</a>
  <a class="btn btn-sm" href="<?= e(mg_url('/panel/menu/producto/nuevo')) ?>">Nuevo platillo</a>
<?php $view->stop() ?>

<?php $view->start('content') ?>
<div class="grid grid-side">
  <div>
    <div class="card">
      <div class="card-head">
        <div>
          <h2>Platillos</h2>
          <p><?= count($products) ?> en la carta<?= $usage['plan'] && (int)$usage['plan']['max_products'] > 0 ? ' · límite ' . (int)$usage['plan']['max_products'] : '' ?></p>
        </div>
        <form class="filters" method="get" action="<?= e(mg_url('/panel/menu')) ?>" style="margin-bottom:0">
          <select class="select" name="categoria" onchange="this.form.submit()" aria-label="Filtrar por categoría">
            <option value="0">Todas las categorías</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar platillo">
        </form>
      </div>

      <?php if ($products): ?>
        <p class="muted" style="font-size:12px;margin-bottom:.8rem">Arrastra para cambiar el orden en que se ven en el menú (o usa Alt + flechas).</p>
        <ul class="sortable" data-sortable="<?= e(mg_url('/panel/menu/productos/orden')) ?>">
          <?php foreach ($products as $p): ?>
            <li data-id="<?= (int)$p['id'] ?>">
              <span class="drag-handle" aria-hidden="true">
                <svg width="12" height="16" viewBox="0 0 12 16" fill="currentColor"><circle cx="3" cy="4" r="1.3"/><circle cx="9" cy="4" r="1.3"/><circle cx="3" cy="8" r="1.3"/><circle cx="9" cy="8" r="1.3"/><circle cx="3" cy="12" r="1.3"/><circle cx="9" cy="12" r="1.3"/></svg>
              </span>
              <span class="cell-thumb"><?= mg_img($p['image'], array('alt' => '', 'sizes' => '46px')) ?></span>
              <span style="flex:1;min-width:0">
                <a class="cell-title link-line" href="<?= e(mg_url('/panel/menu/producto/' . (int)$p['id'])) ?>"><?= e($p['name']) ?></a>
                <span class="muted" style="display:block;font-size:12px"><?= e($p['category_name'] ? $p['category_name'] : 'Sin categoría') ?><?= (int)$p['is_featured'] === 1 ? ' · destacado' : '' ?></span>
              </span>
              <span class="tabular gold nowrap"><?= e(mg_money($p['price'], $cur)) ?></span>
              <button class="chip <?= (int)$p['is_out_of_stock'] === 1 ? 'chip-ember' : 'chip-dim' ?>" type="button"
                      data-quick="<?= e(mg_url('/panel/menu/producto/' . (int)$p['id'] . '/agotado')) ?>">
                <?= (int)$p['is_out_of_stock'] === 1 ? 'Agotado' : 'Disponible' ?>
              </button>
              <?php if ((int)$p['is_active'] === 0): ?><span class="chip chip-dim">Oculto</span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty">
          <h3>Tu carta está vacía</h3>
          <p>Agrega tu primer platillo o importa el menú completo desde un Excel.</p>
          <p class="mt-2"><a class="btn btn-sm" href="<?= e(mg_url('/panel/menu/producto/nuevo')) ?>">Crear el primero</a></p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <h3>Categorías</h3>
      <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/menu/categoria/nueva')) ?>">Nueva</a>
    </div>
    <?php if ($categories): ?>
      <ul class="sortable" data-sortable="<?= e(mg_url('/panel/menu/categorias/orden')) ?>">
        <?php foreach ($categories as $c): ?>
          <li data-id="<?= (int)$c['id'] ?>">
            <span class="numeral"><?= e($c['roman'] !== '' ? $c['roman'] : '·') ?></span>
            <span style="flex:1;min-width:0">
              <a class="link-line" href="<?= e(mg_url('/panel/menu/categoria/' . (int)$c['id'])) ?>"><?= e($c['name']) ?></a>
              <span class="muted" style="display:block;font-size:11.5px"><?= (int)$c['products_count'] ?> platillos</span>
            </span>
            <?php if ((int)$c['is_active'] === 0): ?><span class="chip chip-dim">Oculta</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted" style="font-size:var(--step--1)">Crea al menos una categoría: entradas, fuertes, postres…</p>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop() ?>
