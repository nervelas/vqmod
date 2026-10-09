<?php /** Vars: $user $secret $uri */ $csrf = '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; ?>
<h1>Mi cuenta</h1>
<div class="card"><h2 style="margin-top:0">Cambiar contraseña</h2>
<form method="post" action="/admin/cuenta"><?= $csrf ?><input type="hidden" name="accion" value="password">
<label>Contraseña actual</label><input type="password" name="actual" autocomplete="current-password" required>
<label>Nueva contraseña (mínimo 12 caracteres)</label><input type="password" name="nueva" autocomplete="new-password" minlength="12" required>
<p><button class="btn">Cambiar</button></p></form></div>
<div class="card"><h2 style="margin-top:0">Verificación en dos pasos (opcional)</h2>
<?php if (!empty($user['totp_enabled'])): ?>
<p class="ok">Activada.</p>
<form method="post" action="/admin/cuenta"><?= $csrf ?><input type="hidden" name="accion" value="totp_quitar"><label>Contraseña actual</label><input type="password" name="actual" required><p><button class="btn danger">Desactivar</button></p></form>
<?php elseif ($secret): ?>
<p>Agregue esta cuenta en su aplicación de autenticación (Google Authenticator, Authy, etc.) con esta clave:</p>
<p><code style="font-size:1.1rem;word-break:break-all"><?= e($secret) ?></code></p><p class="mut" style="word-break:break-all">o este enlace: <?= e($uri) ?></p>
<form method="post" action="/admin/cuenta"><?= $csrf ?><input type="hidden" name="accion" value="totp_activar"><label>Código de 6 dígitos</label><input name="code" inputmode="numeric" maxlength="6" required><p><button class="btn">Activar</button></p></form>
<?php else: ?>
<form method="post" action="/admin/cuenta"><?= $csrf ?><input type="hidden" name="accion" value="totp_iniciar"><button class="btn sec">Configurar</button></form>
<?php endif; ?></div>
