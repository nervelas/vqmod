<?php
/** Cupones. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Cupones');
$cur = $restaurant['currency'];
?>
<?php $view->start('content') ?>
<div class="grid grid-side">
  <div class="card">
    <div class="card-head"><h2>Cupones</h2><p>El comensal los escribe al confirmar su pedido.</p></div>
    <?php if ($coupons): ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Código</th><th>Descuento</th><th>Mínimo</th><th class="num">Usos</th><th>Vigencia</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($coupons as $c): ?>
              <tr>
                <td><span class="cell-title tabular"><?= e($c['code']) ?></span>
                  <?php if ((int)$c['is_active'] === 0): ?><span class="chip chip-dim">Inactivo</span><?php endif; ?></td>
                <td class="gold tabular"><?= $c['type'] === 'percent' ? e(rtrim(rtrim(number_format((float)$c['value'], 2, '.', ''), '0'), '.')) . '%'
                    : ($c['type'] === 'amount' ? e(mg_money($c['value'], $cur)) : 'Envío gratis') ?></td>
                <td class="tabular muted"><?= (float)$c['min_total'] > 0 ? e(mg_money($c['min_total'], $cur)) : '—' ?></td>
                <td class="num tabular"><?= (int)$c['used'] ?><?= (int)$c['max_uses'] > 0 ? ' / ' . (int)$c['max_uses'] : '' ?></td>
                <td class="muted" style="font-size:12px"><?= e(($c['starts_at'] ? mg_date($c['starts_at'], 'd/m/y') : '—') . ' → ' . ($c['ends_at'] ? mg_date($c['ends_at'], 'd/m/y') : '—')) ?></td>
                <td class="num">
                  <form method="post" action="<?= e(mg_url('/panel/cupon/' . (int)$c['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar el cupón <?= e($c['code']) ?>?">
                    <?= Csrf::field() ?><button class="cart-remove" type="submit">Eliminar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty"><h3>Sin cupones</h3><p>Crea uno para tu próxima campaña.</p></div>
    <?php endif; ?>
  </div>

  <form class="card" method="post" action="<?= e(mg_url('/panel/cupones')) ?>">
    <?= Csrf::field() ?>
    <div class="card-head"><h3>Nuevo cupón</h3></div>
    <div class="field"><label for="code">Código *</label>
      <input type="text" class="input" id="code" name="code" required maxlength="40" style="text-transform:uppercase" placeholder="BIENVENIDA10"></div>
    <div class="grid grid-2">
      <div class="field"><label for="type">Tipo</label>
        <select class="select" id="type" name="type">
          <option value="percent">Porcentaje</option><option value="amount">Monto</option><option value="free_delivery">Envío gratis</option>
        </select></div>
      <div class="field"><label for="value">Valor</label><input class="input" id="value" name="value" type="number" step="0.01" min="0" value="10"></div>
      <div class="field"><label for="min_total">Compra mínima</label><input class="input" id="min_total" name="min_total" type="number" step="0.01" min="0" value="0"></div>
      <div class="field"><label for="max_uses">Usos máximos</label><input class="input" id="max_uses" name="max_uses" type="number" min="0" value="0">
        <p class="field-hint">0 = sin límite</p></div>
      <div class="field"><label for="starts_at">Desde</label><input class="input" id="starts_at" name="starts_at" type="date"></div>
      <div class="field"><label for="ends_at">Hasta</label><input class="input" id="ends_at" name="ends_at" type="date"></div>
    </div>
    <label class="switch"><input type="checkbox" name="is_active" value="1" checked><span class="switch-track" aria-hidden="true"></span><span>Activo</span></label>
    <button class="btn btn-block mt-2" type="submit">Crear cupón</button>
  </form>
</div>
<?php $view->stop() ?>
