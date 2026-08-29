<?php
$sym = (string) $company['currency_symbol'];
$action = $cu ? url('/panel/clientes/' . $cu['id']) : url('/panel/clientes/nuevo');
$v = static fn (string $k, mixed $d = ''): mixed => $cu[$k] ?? old($k, $d);
$statuses = \App\Models\Quote::STATUSES;
?>
<div class="cols cols--sidebar">
  <div class="stack">
    <form class="card" method="post" action="<?= e($action) ?>">
      <?= csrf_field() ?>
      <div class="card__head"><span class="secnum">01/</span><h2>Datos del cliente</h2>
        <button class="btn btn--accent btn--sm ml-auto" type="submit">Guardar</button></div>
      <div class="card__body">
        <div class="row-2">
          <div class="field"><label for="name">Nombre comercial *</label>
            <input class="input" id="name" name="name" maxlength="160" required value="<?= e($v('name')) ?>"></div>
          <div class="field"><label for="legal_name">Razón social</label>
            <input class="input" id="legal_name" name="legal_name" maxlength="200" value="<?= e($v('legal_name')) ?>"></div>
        </div>
        <div class="row-3">
          <div class="field"><label for="nit">NIT</label><input class="input" id="nit" name="nit" maxlength="30" value="<?= e($v('nit')) ?>"></div>
          <div class="field"><label for="phone">Teléfono</label><input class="input" id="phone" name="phone" maxlength="40" value="<?= e($v('phone')) ?>"></div>
          <div class="field"><label for="whatsapp">WhatsApp</label><input class="input" id="whatsapp" name="whatsapp" maxlength="30" value="<?= e($v('whatsapp')) ?>"></div>
        </div>
        <div class="row-2">
          <div class="field"><label for="email">Correo</label><input class="input" id="email" name="email" type="email" maxlength="150" value="<?= e($v('email')) ?>"></div>
          <div class="field"><label for="sector">Sector</label><input class="input" id="sector" name="sector" maxlength="90" value="<?= e($v('sector')) ?>" placeholder="Azucarero, alimentos, minería…"></div>
        </div>
        <div class="row-2">
          <div class="field"><label for="address">Dirección</label><input class="input" id="address" name="address" maxlength="220" value="<?= e($v('address')) ?>"></div>
          <div class="field"><label for="city">Ciudad</label><input class="input" id="city" name="city" maxlength="90" value="<?= e($v('city')) ?>"></div>
        </div>
        <div class="row-3">
          <div class="field"><label for="price_list_id">Lista de precios</label>
            <select class="select" id="price_list_id" name="price_list_id">
              <option value="">— Precio de lista —</option>
              <?php foreach ($priceLists as $pl): ?>
                <option value="<?= e($pl['id']) ?>"<?= (int) ($cu['price_list_id'] ?? 0) === (int) $pl['id'] ? ' selected' : '' ?>><?= e($pl['name']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <?php if (!\App\Core\Auth::isSeller()): ?>
            <div class="field"><label for="assigned_user_id">Vendedor asignado</label>
              <select class="select" id="assigned_user_id" name="assigned_user_id">
                <option value="">— Sin asignar —</option>
                <?php foreach ($sellers as $s): ?>
                  <option value="<?= e($s['id']) ?>"<?= (int) ($cu['assigned_user_id'] ?? 0) === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
              </select></div>
          <?php endif; ?>
          <div class="field"><label for="next_followup">Próximo seguimiento</label>
            <input class="input" id="next_followup" name="next_followup" type="date" value="<?= e($v('next_followup')) ?>"></div>
        </div>
        <div class="field"><label for="notes">Notas</label>
          <textarea class="textarea" id="notes" name="notes" rows="4" maxlength="4000"><?= e($v('notes')) ?></textarea></div>
      </div>
    </form>

    <?php if ($cu): ?>
      <div class="card">
        <div class="card__head"><span class="secnum">02/</span><h2>Historial de cotizaciones</h2></div>
        <div class="card__body card__body--flush tablescroll">
          <?php if (!$quotes): ?>
            <p class="muted" style="padding:30px;text-align:center;margin:0">Sin cotizaciones registradas.</p>
          <?php else: ?>
            <table class="datatable" style="border:0;border-radius:0">
              <caption class="sr-only">Cotizaciones del cliente</caption>
              <thead><tr><th scope="col">Número</th><th scope="col">Fecha</th><th scope="col">Estado</th><th scope="col">Vendedor</th><th scope="col" class="num">Monto</th></tr></thead>
              <tbody>
                <?php foreach ($quotes as $q): ?>
                  <tr>
                    <td><a href="<?= e(url('/panel/cotizaciones/' . $q['id'])) ?>"><strong><?= e($q['number']) ?></strong></a></td>
                    <td class="small"><?= e(fechaCorta((string) $q['created_at'])) ?></td>
                    <td><span class="badge<?= $q['status'] === 'aprobada' ? ' badge--ok' : ($q['status'] === 'perdida' ? ' badge--bad' : '') ?>"><?= e($statuses[$q['status']]['short']) ?></span></td>
                    <td class="small"><?= e($q['seller_name'] ?: '—') ?></td>
                    <td class="num nowrap"><?= e(money((float) $q['total'], $sym)) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="stack">
    <?php if ($cu): ?>
      <div class="card">
        <div class="card__head"><h2>Contactos</h2></div>
        <div class="card__body">
          <?php foreach ($contacts as $ct): ?>
            <div class="stat-line" style="align-items:flex-start">
              <span style="flex:1"><strong><?= e($ct['name']) ?></strong><?= $ct['is_primary'] ? ' <span class="badge badge--accent">Principal</span>' : '' ?>
                <br><span class="small muted"><?= e($ct['position'] ?: '') ?><?= $ct['email'] ? ' · ' . e($ct['email']) : '' ?><?= $ct['phone'] ? ' · ' . e($ct['phone']) : '' ?></span></span>
              <button class="btn btn--ghost btn--xs" type="submit" form="delct<?= e($ct['id']) ?>">Quitar</button>
            </div>
          <?php endforeach; ?>
          <form method="post" action="<?= e(url('/panel/clientes/' . $cu['id'] . '/contacto')) ?>" style="margin-top:14px">
            <?= csrf_field() ?>
            <div class="field"><label for="ct_name">Nombre *</label><input class="input" id="ct_name" name="name" maxlength="120" required></div>
            <div class="field"><label for="ct_position">Puesto</label><input class="input" id="ct_position" name="position" maxlength="90" placeholder="Jefe de compras"></div>
            <div class="field"><label for="ct_email">Correo</label><input class="input" id="ct_email" name="email" type="email" maxlength="150"></div>
            <div class="field"><label for="ct_phone">Teléfono</label><input class="input" id="ct_phone" name="phone" maxlength="40"></div>
            <label class="check"><input type="checkbox" name="is_primary" value="1"><span>Contacto principal</span></label>
            <button class="btn btn--ghost btn--block" type="submit">Agregar contacto</button>
          </form>
        </div>
      </div>

      <?php if (\App\Core\Auth::isAdmin()): ?>
        <div class="card">
          <div class="card__head"><h2>Zona sensible</h2></div>
          <div class="card__body">
            <form method="post" action="<?= e(url('/panel/clientes/' . $cu['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar este cliente? Sus cotizaciones quedarán sin cliente vinculado.">
              <?= csrf_field() ?><button class="btn btn--danger btn--block" type="submit">Eliminar cliente</button>
            </form>
          </div>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="card"><div class="card__body">
        <p class="small muted" style="margin:0">Guarde el cliente para poder agregarle contactos y ver su historial.</p>
      </div></div>
    <?php endif; ?>
  </div>
</div>
<?php foreach ($contacts as $ct): ?>
  <form id="delct<?= e($ct['id']) ?>" method="post" action="<?= e(url('/panel/clientes/contacto/' . $ct['id'] . '/eliminar')) ?>" class="hide"><?= csrf_field() ?></form>
<?php endforeach; ?>
