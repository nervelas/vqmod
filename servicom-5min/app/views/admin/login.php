<?php /** Vars: $need2fa, $error */ ?>
<div class="login"><div class="card">
<h1 style="color:var(--gold)">Servicom · Panel</h1>
<?php if (!empty($error)): ?><div class="flash err" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/admin/login" autocomplete="on">
<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<?php if (!empty($need2fa)): ?>
<label for="code">Código de verificación (aplicación de autenticación)</label>
<input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
<?php else: ?>
<label for="email">Correo</label><input id="email" type="email" name="email" autocomplete="username" required autofocus>
<label for="password">Contraseña</label><input id="password" type="password" name="password" autocomplete="current-password" required>
<?php endif; ?>
<p><button class="btn" type="submit" style="width:100%">Entrar</button></p>
</form></div></div>
