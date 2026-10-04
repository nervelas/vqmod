<?php /** @var array $u @var ?array $host @var array $roles @var string $setup @var string $otpauth @var string $error @var string $tab */
$on2fa = (int) $u['totp_enabled'] === 1;
$err = static fn (string $t) => $error !== '' && $tab === $t ? '<div class="alert alert-err" role="alert">' . e($error) . '</div>' : '';
?>
<div class="page page-narrow">
  <div class="page-head"><div>
    <h1 class="page-title serif">Mi perfil</h1>
    <p class="page-sub"><?= e($u['email']) ?> · <?= e($roles[$u['role']] ?? $u['role']) ?><?= $host ? ' · ' . e($host['name']) : '' ?></p>
  </div></div>

  <section class="card" id="datos">
    <div class="card-head"><h2 class="serif">Mis datos</h2></div>
    <form class="card-body stack" method="post" action="<?= e(url('/admin/perfil')) ?>">
      <?= csrf_field() ?><?= $err('datos') ?>
      <div class="form-grid">
        <div class="field"><label for="name">Nombre</label><input class="input" id="name" name="name" value="<?= e($u['name']) ?>" maxlength="120" required></div>
        <div class="field"><label for="email">Correo electrónico</label><input class="input" id="email" name="email" type="email" value="<?= e($u['email']) ?>" maxlength="190" required></div>
        <div class="field"><label for="current-data">Contraseña actual <span class="muted">(solo si cambias el correo)</span></label><input class="input" id="current-data" name="current" type="password" autocomplete="current-password"></div>
      </div>
      <div><button class="btn btn-gold" type="submit">Guardar datos</button></div>
    </form>
  </section>

  <section class="card" id="clave">
    <div class="card-head"><h2 class="serif">Cambiar contraseña</h2></div>
    <form class="card-body stack" method="post" action="<?= e(url('/admin/perfil/clave')) ?>">
      <?= csrf_field() ?><?= $err('clave') ?>
      <div class="form-grid">
        <div class="field"><label for="current">Contraseña actual</label><input class="input" id="current" name="current" type="password" autocomplete="current-password" required></div>
        <div class="field"><label for="password">Contraseña nueva</label><input class="input" id="password" name="password" type="password" autocomplete="new-password" required data-strength="#pw-rules"><p class="hint">Mínimo 10 caracteres, con mayúsculas, minúsculas y números.</p>
          <ul class="pw-rules" id="pw-rules"><li data-rule="len">Al menos 10 caracteres</li><li data-rule="lower">Una minúscula</li><li data-rule="upper">Una mayúscula</li><li data-rule="num">Un número</li></ul></div>
        <div class="field"><label for="password2">Repite la nueva</label><input class="input" id="password2" name="password2" type="password" autocomplete="new-password" required></div>
      </div>
      <div><button class="btn btn-gold" type="submit">Cambiar contraseña</button></div>
    </form>
  </section>

  <section class="card" id="seguridad">
    <div class="card-head row row-between"><h2 class="serif">Verificación en dos pasos</h2>
      <span class="badge <?= $on2fa ? 'badge-ok' : 'badge-muted' ?>"><?= $on2fa ? 'Activada' : 'Desactivada' ?></span></div>
    <div class="card-body stack">
      <?= $err('2fa') ?>
      <?php if ($on2fa) : ?>
        <p class="muted">Al iniciar sesión te pediremos un código de tu aplicación de autenticación. Para desactivarla confirma con tu contraseña.</p>
        <form class="stack" method="post" action="<?= e(url('/admin/perfil/2fa/desactivar')) ?>">
          <?= csrf_field() ?>
          <div class="field"><label for="current-2fa">Contraseña actual</label><input class="input" id="current-2fa" name="current" type="password" autocomplete="current-password" required></div>
          <div><button class="btn btn-danger" type="submit" data-confirm="¿Desactivar la verificación en dos pasos? Tu cuenta quedará menos protegida.">Desactivar</button></div>
        </form>
      <?php elseif ($setup !== '') : ?>
        <p>1. Escanea este código con tu aplicación de autenticación (cualquier aplicación de códigos de un solo uso compatible con TOTP).</p>
        <div class="qr-box"><div id="qr" class="qr" data-otpauth="<?= e($otpauth) ?>" role="img" aria-label="Código QR para la aplicación de autenticación"></div>
          <div><p class="muted small">¿No puedes escanear? Escribe esta clave a mano:</p><p class="mono qr-key" id="totp-key"><?= e(trim(chunk_split($setup, 4, ' '))) ?></p>
          <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e($setup) ?>"><?= icon('copy') ?>Copiar clave</button></div></div>
        <p>2. Escribe el código de 6 dígitos que aparece en la aplicación.</p>
        <form class="stack" method="post" action="<?= e(url('/admin/perfil/2fa/activar')) ?>">
          <?= csrf_field() ?>
          <div class="field"><label for="code">Código de verificación</label><input class="input mono auth-code" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required></div>
          <div><button class="btn btn-gold" type="submit">Activar verificación</button></div>
        </form>
      <?php else : ?>
        <p class="muted">Suma una capa extra de seguridad: además de tu contraseña, se pedirá un código temporal de tu teléfono.</p>
        <form method="post" action="<?= e(url('/admin/perfil/2fa/iniciar')) ?>"><?= csrf_field() ?><button class="btn btn-outline" type="submit"><?= icon('shield') ?>Configurar</button></form>
      <?php endif; ?>
    </div>
  </section>

  <section class="card" id="tema">
    <div class="card-head"><h2 class="serif">Apariencia</h2></div>
    <div class="card-body row row-between row-wrap">
      <p class="muted">El panel puede verse oscuro (medianoche y oro) o claro (marfil). Se guarda en tu cuenta.</p>
      <button class="btn btn-outline" type="button" data-theme-toggle data-theme-url="<?= e(url('/admin/perfil/tema')) ?>"><?= icon('moon') ?>Cambiar tema</button>
    </div>
  </section>
</div>
