<?php
use App\Core\Fmt;

$tz = \App\Core\Settings::tz();
$e = $edit ?: ['id' => 0, 'code' => '', 'type' => 'percent', 'value' => '', 'max_uses' => '', 'valid_from' => null, 'valid_to' => null, 'event_type_id' => null, 'active' => 1];
$vf = $e['valid_from'] ? \App\Core\Tz::format((string) $e['valid_from'], $tz, 'Y-m-d') : '';
$vt = $e['valid_to'] ? \App\Core\Tz::format((string) $e['valid_to'], $tz, 'Y-m-d') : '';
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">Cupones</h1><p class="page-sub">Descuentos por porcentaje o monto fijo, con vigencia, límite de usos y, si quieres, para un solo servicio.</p></div>
    <div class="page-actions"><a class="btn btn-gold" href="#cupon-form"><?= icon('plus') ?>Nuevo cupón</a></div>
  </div>

  <section class="card mb-4" aria-labelledby="h-cupones">
    <div class="card-head"><h2 id="h-cupones" class="serif">Cupones creados</h2></div>
    <?php if (!$coupons) : ?>
      <div class="card-body"><div class="empty"><?= icon('tag') ?><p class="empty-title">Todavía no hay cupones</p><p class="empty-text">Crea uno para campañas, referidos o clientes especiales. Tus clientes lo escriben al reservar.</p></div></div>
    <?php else : ?>
      <div class="table-wrap"><table class="table">
        <caption class="sr-only">Lista de cupones</caption>
        <thead><tr><th scope="col">Código</th><th scope="col">Descuento</th><th scope="col">Usos</th><th scope="col" class="hide-sm">Vigencia</th><th scope="col" class="hide-sm">Servicio</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody>
        <?php foreach ($coupons as $c) :
            $expired = $c['valid_to'] && $c['valid_to'] < $now;
            $exhausted = $c['max_uses'] !== null && (int) $c['used'] >= (int) $c['max_uses'];
            $state = !(int) $c['active'] ? ['Pausado', 'badge-muted'] : ($expired ? ['Vencido', 'badge-err'] : ($exhausted ? ['Agotado', 'badge-warn'] : ['Activo', 'badge-ok'])); ?>
          <tr>
            <th scope="row" class="mono"><?= e($c['code']) ?></th>
            <td class="mono"><?= $c['type'] === 'percent' ? e(rtrim(rtrim(number_format((float) $c['value'], 2, '.', ''), '0'), '.')) . ' %' : e(money($c['value'])) ?></td>
            <td class="mono"><?= (int) $c['used'] ?><?= $c['max_uses'] !== null ? ' / ' . (int) $c['max_uses'] : '' ?></td>
            <td class="hide-sm nowrap"><?= $c['valid_from'] ? e(Fmt::dateShort((string) $c['valid_from'], $tz)) : 'Siempre' ?> → <?= $c['valid_to'] ? e(Fmt::dateShort((string) $c['valid_to'], $tz)) : 'sin fin' ?></td>
            <td class="hide-sm"><?= e($c['event_name'] ?: 'Todos') ?></td>
            <td><span class="badge <?= e($state[1]) ?>"><?= e($state[0]) ?></span></td>
            <td class="right nowrap">
              <a class="btn btn-ghost btn-sm btn-icon" href="<?= e(url('/admin/cupones', ['editar' => $c['id']])) ?>#cupon-form" aria-label="Editar <?= e($c['code']) ?>"><?= icon('edit') ?></a>
              <form class="inline" method="post" action="<?= e(url('/admin/cupones/' . (int) $c['id'] . '/estado')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= (int) $c['active'] ? 'Pausar' : 'Activar' ?></button></form>
              <form class="inline" method="post" action="<?= e(url('/admin/cupones/' . (int) $c['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar el cupón <?= e($c['code']) ?>?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-icon" type="submit" aria-label="Eliminar <?= e($c['code']) ?>"><?= icon('trash') ?></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>

  <section class="card" id="cupon-form" aria-labelledby="h-cform">
    <div class="card-head"><h2 id="h-cform" class="serif"><?= $e['id'] ? 'Editar cupón' : 'Nuevo cupón' ?></h2></div>
    <form method="post" action="<?= e(url('/admin/cupones/guardar')) ?>" class="card-body stack">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
      <div class="form-grid">
        <div class="field"><label for="cp-code">Código</label><input class="input mono" id="cp-code" name="code" maxlength="40" required value="<?= e($e['code']) ?>" placeholder="BIENVENIDA10" autocapitalize="characters"><p class="hint">Solo letras, números y guiones. Se guarda en mayúsculas.</p></div>
        <div class="field"><label for="cp-type">Tipo</label><select class="select" id="cp-type" name="type"><option value="percent"<?= sel($e['type'], 'percent') ?>>Porcentaje (%)</option><option value="fixed"<?= sel($e['type'], 'fixed') ?>>Monto fijo (Q)</option></select></div>
        <div class="field"><label for="cp-val">Valor</label><input class="input mono" id="cp-val" name="value" inputmode="decimal" required value="<?= e($e['value'] === '' ? '' : rtrim(rtrim(number_format((float) $e['value'], 2, '.', ''), '0'), '.')) ?>"></div>
        <div class="field"><label for="cp-max">Límite de usos</label><input class="input mono" id="cp-max" name="max_uses" inputmode="numeric" value="<?= e($e['max_uses'] ?? '') ?>" placeholder="Sin límite"></div>
        <div class="field"><label for="cp-min">Compra mínima (Q)</label><input class="input mono" id="cp-min" name="min_amount" inputmode="decimal" value="<?= e(!empty($e['min_amount']) && (float) $e['min_amount'] > 0 ? number_format((float) $e['min_amount'], 2, '.', '') : '') ?>" placeholder="Sin mínimo"></div>
        <div class="field"><label for="cp-from">Válido desde</label><input class="input" id="cp-from" type="date" name="valid_from" value="<?= e($vf) ?>"></div>
        <div class="field"><label for="cp-to">Válido hasta</label><input class="input" id="cp-to" type="date" name="valid_to" value="<?= e($vt) ?>"></div>
        <div class="field"><label for="cp-ev">Solo para el servicio</label>
          <select class="select" id="cp-ev" name="event_type_id"><option value="0">Todos los servicios</option>
            <?php foreach ($events as $ev) : ?><option value="<?= (int) $ev['id'] ?>"<?= sel($e['event_type_id'], $ev['id']) ?>><?= e($ev['name']) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <label class="check"><input type="checkbox" name="active" value="1"<?= chk((int) $e['active'] === 1) ?>><span>Cupón activo</span></label>
      <div class="form-actions"><button class="btn btn-gold" type="submit">Guardar cupón</button><?php if ($e['id']) : ?><a class="btn btn-ghost" href="<?= e(url('/admin/cupones')) ?>">Cancelar</a><?php endif; ?></div>
    </form>
  </section>
</div>
