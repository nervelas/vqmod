<?php /** @var string $mode 'reset'|'invite' @var array $person @var string $error */
$invite = $mode === 'invite';
$action = $invite ? '/admin/invitacion/' : '/admin/restablecer/';
$token = basename((string) ($GLOBALS['__request']->path ?? ''));
?>
<h1 class="serif auth-title"><?= $invite ? 'Te damos la bienvenida' : 'Crea una contraseña nueva' ?></h1>
<p class="muted auth-sub"><?= $invite ? 'Hola, ' . e((string) $person['name']) . '. Elige una contraseña para activar tu cuenta.' : 'Elige una contraseña segura para ' . e((string) $person['email']) . '.' ?></p>
<?php if ($error !== '') : ?><div class="alert alert-err" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= e(url($action . $token)) ?>" class="stack" novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <label for="password">Contraseña nueva</label>
    <div class="input-group">
      <input class="input" id="password" name="password" type="password" autocomplete="new-password" required autofocus data-strength="#pw-rules">
      <button class="btn btn-ghost btn-icon" type="button" data-toggle-password="#password" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false"><?= icon('eye') ?></button>
    </div>
    <ul class="pw-rules" id="pw-rules">
      <li data-rule="len">Al menos 10 caracteres</li>
      <li data-rule="lower">Una letra minúscula</li>
      <li data-rule="upper">Una letra mayúscula</li>
      <li data-rule="num">Un número</li>
    </ul>
  </div>
  <div class="field">
    <label for="password2">Repite la contraseña</label>
    <input class="input" id="password2" name="password2" type="password" autocomplete="new-password" required>
  </div>
  <button class="btn btn-gold btn-lg btn-block" type="submit"><?= $invite ? 'Activar mi cuenta' : 'Guardar contraseña' ?></button>
</form>
