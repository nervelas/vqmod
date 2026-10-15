<div class="page-head"><div><p class="eyebrow">Mi empresa</p><h1>Datos para tus ofertas</h1></div></div>
<form method="post" class="card form narrow-form"><?= csrf_field() ?>
  <p class="muted">Estos datos se usan para armar tus borradores de oferta.</p>
  <?= f_input('company', 'Nombre de la empresa', $p['company']) ?>
  <?= f_input('nit', 'NIT', $p['nit']) ?>
  <?= f_input('legal_rep', 'Representante legal', $p['legal_rep']) ?>
  <?= f_input('address', 'Dirección', $p['address']) ?>
  <h3>Tu cuenta</h3>
  <?= f_input('name', 'Tu nombre', $u['name'], 'text', 'required') ?>
  <?= f_input('phone', 'Teléfono', $u['phone'], 'tel') ?>
  <details><summary>Cambiar contraseña</summary>
    <?= f_input('cur_pass', 'Contraseña actual', '', 'password', 'autocomplete="current-password"') ?>
    <?= f_input('new_pass', 'Nueva contraseña', '', 'password', 'minlength="8" autocomplete="new-password"') ?>
  </details>
  <button class="btn btn-gold">Guardar</button>
</form>
