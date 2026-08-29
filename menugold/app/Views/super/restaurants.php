<?php
/** Listado de restaurantes. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Restaurantes');
?>
<?php $view->start('actions') ?>
  <a class="btn btn-sm" href="<?= e(mg_url('/super/restaurante/nuevo')) ?>">Nuevo restaurante</a>
<?php $view->stop() ?>

<?php $view->start('content') ?>
<form class="filters" method="get" action="<?= e(mg_url('/super/restaurantes')) ?>">
  <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o dirección">
  <button class="btn btn-sm" type="submit">Buscar</button>
</form>

<div class="card">
  <?php if ($rows): ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Restaurante</th><th>Plan</th><th>Vence</th><th class="num">Platillos</th><th class="num">Pedidos/mes</th><th>Estado</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <a class="cell-title link-line" href="<?= e(mg_url('/super/restaurante/' . (int)$r['id'])) ?>"><?= e($r['name']) ?></a>
                <span class="faint" style="display:block;font-size:11.5px">/r/<?= e($r['slug']) ?></span>
              </td>
              <td class="muted"><?= e($r['plan_name'] ? $r['plan_name'] : '—') ?></td>
              <td class="muted" style="font-size:12px"><?= e($r['plan_expires_at'] ? mg_date($r['plan_expires_at'], 'd/m/Y') : '—') ?></td>
              <td class="num tabular"><?= (int)$r['products'] ?></td>
              <td class="num tabular"><?= (int)$r['orders_month'] ?></td>
              <td><span class="chip <?= $r['status'] === 'active' ? 'chip-green' : ($r['status'] === 'suspended' ? 'chip-ember' : 'chip-dim') ?>"><?= e($r['status']) ?></span></td>
              <td class="num nowrap">
                <a class="btn btn-ghost btn-sm" href="<?= e(mg_url('/super/entrar/' . (int)$r['id'])) ?>">Entrar</a>
                <form style="display:inline" method="post" action="<?= e(mg_url('/super/restaurante/' . (int)$r['id'] . '/estado')) ?>"
                      data-confirm="¿Cambiar el estado de <?= e($r['name']) ?>?">
                  <?= Csrf::field() ?>
                  <button class="btn btn-ghost btn-sm" type="submit"><?= $r['status'] === 'suspended' ? 'Reactivar' : 'Suspender' ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><h3>Sin restaurantes</h3><p>Crea el primero para empezar.</p></div>
  <?php endif; ?>
</div>
<?php $view->stop() ?>
