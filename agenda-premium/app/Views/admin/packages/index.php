<?php
use App\Core\Fmt;

$tz = \App\Core\Settings::tz();
$e = $edit ?: ['id' => 0, 'name' => '', 'description' => '', 'sessions' => 5, 'price' => '', 'validity_days' => 180, 'event_type_id' => null, 'active' => 1];
?>
<div class="page">
  <div class="page-head">
    <div>
      <h1 class="page-title">Paquetes de sesiones</h1>
      <p class="page-sub">Vende bonos de varias sesiones con vigencia y lleva el saldo de cada cliente.</p>
    </div>
    <div class="page-actions"><a class="btn btn-gold" href="#paquete-form"><?= icon('plus') ?>Nuevo paquete</a></div>
  </div>

  <section class="card mb-4" aria-labelledby="h-catalogo">
    <div class="card-head"><h2 id="h-catalogo" class="serif">Catálogo</h2></div>
    <?php if (!$packages) : ?>
      <div class="card-body"><div class="empty">
        <?= icon('package') ?>
        <p class="empty-title">Aún no hay paquetes</p>
        <p class="empty-text">Crea el primero, por ejemplo "5 sesiones" con un precio especial, y véndelo a tus clientes frecuentes.</p>
      </div></div>
    <?php else : ?>
      <div class="table-wrap"><table class="table">
        <caption class="sr-only">Paquetes de sesiones disponibles</caption>
        <thead><tr><th scope="col">Paquete</th><th scope="col" class="right">Sesiones</th><th scope="col" class="right">Precio</th><th scope="col" class="hide-sm">Vigencia</th><th scope="col" class="hide-sm">Aplica a</th><th scope="col" class="right hide-sm">Vendidos</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody>
        <?php foreach ($packages as $pk) : ?>
          <tr>
            <th scope="row"><?= e($pk['name']) ?><?php if ($pk['description']) : ?><div class="muted"><?= e($pk['description']) ?></div><?php endif; ?></th>
            <td class="right mono"><?= (int) $pk['sessions'] ?></td>
            <td class="right mono nowrap"><?= e(money($pk['price'])) ?></td>
            <td class="hide-sm"><?= (int) $pk['validity_days'] ?> días</td>
            <td class="hide-sm"><?= e($pk['event_name'] ?: 'Todos los servicios') ?></td>
            <td class="right mono hide-sm"><?= (int) $pk['sold'] ?></td>
            <td><span class="badge <?= (int) $pk['active'] ? 'badge-ok' : 'badge-muted' ?>"><?= (int) $pk['active'] ? 'Activo' : 'Pausado' ?></span></td>
            <td class="right nowrap">
              <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/paquetes', ['editar' => $pk['id']])) ?>#paquete-form" aria-label="Editar <?= e($pk['name']) ?>"><?= icon('edit') ?></a>
              <form class="inline" method="post" action="<?= e(url('/admin/paquetes/' . (int) $pk['id'] . '/estado')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= (int) $pk['active'] ? 'Pausar' : 'Activar' ?></button></form>
              <form class="inline" method="post" action="<?= e(url('/admin/paquetes/' . (int) $pk['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar este paquete? Esta acción no se puede deshacer."><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-icon" type="submit" aria-label="Eliminar <?= e($pk['name']) ?>"><?= icon('trash') ?></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>

  <div class="grid cols-2 mb-4">
    <section class="card" id="paquete-form" aria-labelledby="h-form">
      <div class="card-head"><h2 id="h-form" class="serif"><?= $e['id'] ? 'Editar paquete' : 'Nuevo paquete' ?></h2></div>
      <form method="post" action="<?= e(url('/admin/paquetes/guardar')) ?>" class="card-body stack">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
        <div class="field"><label for="pk-name">Nombre</label><input class="input" id="pk-name" name="name" maxlength="160" required value="<?= e($e['name']) ?>" placeholder="Bono de 5 sesiones"></div>
        <div class="field"><label for="pk-desc">Descripción corta</label><input class="input" id="pk-desc" name="description" maxlength="255" value="<?= e($e['description']) ?>"></div>
        <div class="form-row">
          <div class="field"><label for="pk-ses">Sesiones</label><input class="input mono" id="pk-ses" name="sessions" type="number" min="1" max="999" required value="<?= e($e['sessions']) ?>"></div>
          <div class="field"><label for="pk-price">Precio (Q)</label><input class="input mono" id="pk-price" name="price" inputmode="decimal" required value="<?= e($e['price'] === '' ? '' : number_format((float) $e['price'], 2, '.', '')) ?>"></div>
          <div class="field"><label for="pk-val">Vigencia (días)</label><input class="input mono" id="pk-val" name="validity_days" type="number" min="1" max="3650" required value="<?= e($e['validity_days']) ?>"></div>
        </div>
        <div class="field"><label for="pk-ev">Aplica a</label>
          <select class="select" id="pk-ev" name="event_type_id"><option value="0">Todos los servicios</option>
            <?php foreach ($events as $ev) : ?><option value="<?= (int) $ev['id'] ?>"<?= sel($e['event_type_id'], $ev['id']) ?>><?= e($ev['name']) ?></option><?php endforeach; ?>
          </select></div>
        <label class="check"><input type="checkbox" name="active" value="1"<?= chk((int) $e['active'] === 1) ?>><span>Disponible para la venta</span></label>
        <div class="form-actions"><button class="btn btn-gold" type="submit">Guardar paquete</button><?php if ($e['id']) : ?><a class="btn btn-ghost" href="<?= e(url('/admin/paquetes')) ?>">Cancelar</a><?php endif; ?></div>
      </form>
    </section>

    <section class="card" aria-labelledby="h-vender">
      <div class="card-head"><h2 id="h-vender" class="serif">Vender a un cliente</h2></div>
      <form method="post" action="<?= e(url('/admin/paquetes/vender')) ?>" class="card-body stack">
        <?= csrf_field() ?>
        <?php if (!$clients) : ?>
          <div class="alert alert-info">Todavía no tienes clientes registrados. Aparecerán aquí cuando reserven su primera cita.</div>
        <?php endif; ?>
        <div class="field"><label for="sv-cl">Cliente</label>
          <select class="select" id="sv-cl" name="client_id" required><option value="">Elige un cliente…</option>
            <?php foreach ($clients as $c) : ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name'] . ($c['phone'] ? ' · ' . $c['phone'] : '')) ?></option><?php endforeach; ?>
          </select></div>
        <div class="field"><label for="sv-pk">Paquete</label>
          <select class="select" id="sv-pk" name="package_id" required><option value="">Elige un paquete…</option>
            <?php foreach ($packages as $pk) : if (!(int) $pk['active']) { continue; } ?><option value="<?= (int) $pk['id'] ?>"><?= e($pk['name'] . ' · ' . $pk['sessions'] . ' sesiones · ' . money($pk['price'])) ?></option><?php endforeach; ?>
          </select></div>
        <div class="field"><label for="sv-m">Método de pago</label>
          <select class="select" id="sv-m" name="method"><option value="cash">Efectivo</option><option value="transfer">Transferencia o depósito</option><option value="card_onsite">Tarjeta en el local</option><option value="link">Enlace de pago</option><option value="other">Otro</option></select></div>
        <label class="check"><input type="checkbox" name="paid" value="1" checked><span>Ya se recibió el pago</span></label>
        <div class="form-actions"><button class="btn btn-gold" type="submit"<?= $clients ? '' : ' disabled' ?>><?= icon('package') ?>Vender paquete</button></div>
      </form>
    </section>
  </div>

  <section class="card" aria-labelledby="h-saldos">
    <div class="card-head row row-between row-wrap"><h2 id="h-saldos" class="serif">Saldos de clientes</h2>
      <div class="chips" role="group" aria-label="Filtrar saldos">
        <?php foreach (['activos' => 'Vigentes', 'vencidos' => 'Vencidos', 'agotados' => 'Agotados', 'todos' => 'Todos'] as $k => $l) : ?>
          <a class="chip<?= $filter === $k ? ' is-active' : '' ?>" href="<?= e(url('/admin/paquetes', ['ver' => $k])) ?>#h-saldos"<?= $filter === $k ? ' aria-current="true"' : '' ?>><?= e($l) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if (!$sold) : ?>
      <div class="card-body"><div class="empty"><?= icon('users') ?><p class="empty-title">Sin paquetes en esta vista</p><p class="empty-text">Cuando vendas un paquete, el saldo y el vencimiento aparecerán aquí.</p></div></div>
    <?php else : ?>
      <div class="table-wrap"><table class="table">
        <caption class="sr-only">Saldos de paquetes por cliente</caption>
        <thead><tr><th scope="col">Cliente</th><th scope="col">Paquete</th><th scope="col">Saldo</th><th scope="col">Vence</th><th scope="col" class="hide-sm">Pago</th></tr></thead>
        <tbody>
        <?php foreach ($sold as $s) :
            $expired = $s['expires_at'] && $s['expires_at'] < $now;
            $pct = (int) $s['sessions'] > 0 ? (int) round(100 * (int) $s['remaining'] / (int) $s['sessions']) : 0; ?>
          <tr>
            <th scope="row"><?= e($s['client_name']) ?></th>
            <td><?= e($s['package_name']) ?></td>
            <td><span class="mono"><?= (int) $s['remaining'] ?> / <?= (int) $s['sessions'] ?></span> <div class="progress" role="img" aria-label="<?= (int) $pct ?> % disponible"><span <?= vars(['--w' => $pct . '%']) ?>></span></div></td>
            <td class="nowrap"><?= $s['expires_at'] ? e(Fmt::dateShort((string) $s['expires_at'], $tz)) : 'Sin vencimiento' ?> <?php if ($expired) : ?><span class="badge badge-err">Vencido</span><?php endif; ?></td>
            <td class="hide-sm"><span class="badge <?= (int) $s['paid'] ? 'badge-ok' : 'badge-warn' ?>"><?= (int) $s['paid'] ? 'Pagado' : 'Por cobrar' ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>
</div>
