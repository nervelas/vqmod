<?php
/** Grupos de modificadores reutilizables. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Modificadores');
$cur = $restaurant['currency'];
?>
<?php $view->start('actions') ?>
  <a class="btn btn-sm" href="<?= e(mg_url('/panel/menu/modificador/nuevo')) ?>">Nuevo grupo</a>
<?php $view->stop() ?>

<?php $view->start('content') ?>
<p class="page-intro">Un grupo de modificadores se define una vez y se enlaza a los platillos que lo necesiten: término de la carne, extras con precio, ingredientes que se quitan.</p>

<?php if ($groups): ?>
  <div class="grid grid-2">
    <?php foreach ($groups as $g): ?>
      <div class="card">
        <div class="card-head">
          <div>
            <h3><?= e($g['name']) ?></h3>
            <p><?= e($g['type'] === 'multi' ? 'Varias opciones' : 'Una sola opción') ?><?= (int)$g['is_required'] === 1 ? ' · obligatorio' : '' ?> · usado en <?= (int)$g['used'] ?> platillos</p>
          </div>
          <a class="btn btn-sm btn-ghost" href="<?= e(mg_url('/panel/menu/modificador/' . (int)$g['id'])) ?>">Editar</a>
        </div>
        <ul class="stack" style="gap:.45rem">
          <?php foreach ($g['options'] as $o): ?>
            <li class="row-between" style="font-size:var(--step--1)">
              <span class="muted"><?= e($o['name']) ?></span>
              <span class="tabular <?= (float)$o['price_delta'] > 0 ? 'gold' : 'faint' ?>">
                <?= (float)$o['price_delta'] > 0 ? '+' . e(mg_money($o['price_delta'], $cur)) : 'sin costo' ?>
              </span>
            </li>
          <?php endforeach; ?>
          <?php if (!$g['options']): ?><li class="faint" style="font-size:var(--step--1)">Sin opciones todavía.</li><?php endif; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="empty">
    <h3>Sin grupos de modificadores</h3>
    <p>Crea el primero: «Término de la carne», «Extras», «Quitar ingredientes».</p>
    <p class="mt-2"><a class="btn btn-sm" href="<?= e(mg_url('/panel/menu/modificador/nuevo')) ?>">Crear grupo</a></p>
  </div>
<?php endif; ?>
<?php $view->stop() ?>
