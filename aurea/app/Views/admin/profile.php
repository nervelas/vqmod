<div class="grid grid-2">
  <div class="card">
    <h2><?= e(__('Cambiar contraseña')) ?></h2>
    <form method="post" action="<?= e(url('/admin/perfil/clave')) ?>">
      <?= csrf_field() ?>
      <div class="field"><label for="c"><?= e(__('Contraseña actual')) ?></label><input id="c" name="current" type="password" required autocomplete="current-password"></div>
      <div class="field"><label for="n"><?= e(__('Nueva contraseña')) ?></label><input id="n" name="new" type="password" required autocomplete="new-password"><div class="help"><?= e(__('Mínimo 10 caracteres con mayúsculas, minúsculas y números.')) ?></div></div>
      <div class="field"><label for="n2"><?= e(__('Repite la nueva contraseña')) ?></label><input id="n2" name="new2" type="password" required autocomplete="new-password"></div>
      <button class="btn btn-gold" type="submit"><?= e(__('Actualizar')) ?></button>
    </form>
  </div>
  <?php if ($user['role'] === 'admin'): ?>
  <div class="card">
    <h2><?= e(__('Verificación en dos pasos (TOTP)')) ?></h2>
    <?php if ((int)$user['totp_enabled']): ?>
      <p><span class="badge badge-ok"><?= e(__('Activada')) ?></span></p>
      <form method="post" action="<?= e(url('/admin/perfil/2fa')) ?>" data-confirm="<?= e(__('¿Desactivar la verificación en dos pasos?')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="disable">
        <div class="field"><label for="dc"><?= e(__('Contraseña actual')) ?></label><input id="dc" name="current" type="password" required></div>
        <div class="field"><label for="dcode"><?= e(__('Código actual')) ?></label><input id="dcode" name="code" type="text" inputmode="numeric" required></div>
        <button class="btn btn-danger" type="submit"><?= e(__('Desactivar')) ?></button>
      </form>
    <?php else: ?>
      <p class="hint"><?= e(__('Escanea el código con Google Authenticator, Authy o similar y escribe el código de 6 dígitos para activarlo.')) ?></p>
      <div data-qr="<?= e((string)$uri) ?>" data-size="200" style="margin:12px 0"></div>
      <p class="hint"><?= e(__('Clave manual')) ?>: <code><?= e((string)$secret) ?></code></p>
      <form method="post" action="<?= e(url('/admin/perfil/2fa')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="enable">
        <div class="field"><label for="ecode"><?= e(__('Código de 6 dígitos')) ?></label><input id="ecode" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required></div>
        <button class="btn btn-gold" type="submit"><?= e(__('Activar')) ?></button>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
