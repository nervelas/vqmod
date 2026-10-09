<?php /** Vars: $token, $hasFile, $error, $ok, $email */ ?>
<div class="login"><div class="card">
<h1 style="color:var(--gold)">Recuperar acceso</h1>
<?php if (!empty($error)): ?><div class="flash err" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (!empty($ok)): ?>
<div class="flash ok" role="status"><?= e($ok) ?></div>
<p><a class="btn" href="/admin/login" style="display:block;text-align:center">Ir a entrar</a></p>
<?php elseif (!$hasFile): ?>
<p>Para comprobar que usted es el dueño del hosting:</p>
<ol>
<li>Entre a <b>cPanel → Administrador de archivos</b> y abra la carpeta de este portal (la misma donde está <code>index.php</code>).</li>
<li>Pulse <b>+ Archivo</b> y créelo con este nombre exacto:<br><code style="word-break:break-all;user-select:all">recuperar-<?= e($token) ?>.txt</code></li>
<li>Vuelva a esta página y pulse <b>Ya lo creé</b>.</li>
</ol>
<p><a class="btn" href="/admin/recuperar" style="display:block;text-align:center">Ya lo creé</a></p>
<?php else: ?>
<p>Archivo encontrado. Defina su correo y una contraseña nueva (mínimo 12 caracteres).</p>
<form method="post" action="/admin/recuperar" autocomplete="off">
<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<label for="email">Correo</label><input id="email" type="email" name="email" value="<?= e($email) ?>" autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false" required>
<label for="password">Contraseña nueva</label><input id="password" type="password" name="password" minlength="12" autocomplete="new-password" autocapitalize="none" autocorrect="off" spellcheck="false" required>
<label for="password2">Repita la contraseña</label><input id="password2" type="password" name="password2" minlength="12" autocomplete="new-password" autocapitalize="none" autocorrect="off" spellcheck="false" required>
<p><label style="display:flex;gap:.5rem;align-items:center"><input type="checkbox" data-show-pass> Mostrar contraseñas</label></p>
<p><button class="btn" type="submit" style="width:100%">Guardar y recuperar acceso</button></p>
</form>
<p><small>Esto reemplaza el acceso del dueño y desactiva la verificación en dos pasos. El archivo de comprobación se borra solo al terminar.</small></p>
<?php endif; ?>
<p style="text-align:center"><a href="/admin/login">Volver</a></p>
</div></div>
<script src="<?= e(asset('admin/admin.js')) ?>" nonce="<?= e($nonce ?? '') ?>"></script>
