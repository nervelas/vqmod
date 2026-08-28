<?php
/** @var array $yo */
use MenuGold\Core\View;
use MenuGold\Models\User;
View::set('titulo', 'Mi perfil');
View::set('subtitulo', User::etiquetaRol((string)$yo['rol']));
?>
<form method="post" action="<?= e(url('panel/perfil')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="rejilla rejilla--2">
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('user') ?> Tus datos</h2></div>
      <div class="campo-p"><label for="pNombre">Nombre completo</label>
        <input type="text" id="pNombre" name="nombre" required maxlength="120" value="<?= e((string)$yo['nombre']) ?>"></div>
      <div class="campo-p"><label for="pEmail">Correo</label>
        <input type="email" id="pEmail" name="email" maxlength="190" value="<?= e((string)$yo['email']) ?>"></div>
      <div class="campo-p"><label for="pTel">Teléfono</label>
        <input type="tel" id="pTel" name="telefono" maxlength="30" value="<?= e((string)$yo['telefono']) ?>"></div>
      <div class="campo-p"><label for="pTema">Apariencia del panel</label>
        <select id="pTema" name="tema_panel">
          <option value="auto" <?= $yo['tema_panel'] === 'auto' ? 'selected' : '' ?>>Automático (según tu dispositivo)</option>
          <option value="claro" <?= $yo['tema_panel'] === 'claro' ? 'selected' : '' ?>>Siempre claro</option>
          <option value="oscuro" <?= $yo['tema_panel'] === 'oscuro' ? 'selected' : '' ?>>Siempre oscuro</option>
        </select></div>
      <div class="campo-p"><label>Nombre de usuario</label>
        <input class="entrada mono" type="text" value="<?= e((string)$yo['usuario']) ?>" readonly>
        <p class="ayuda-p">Solo un administrador puede cambiarlo.</p></div>
    </div>

    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('lock') ?> Cambiar contraseña</h2></div>
      <p class="ayuda-p" style="margin-top:0">Déjalo vacío si no quieres cambiarla.</p>
      <div class="campo-p"><label for="pActual">Contraseña actual</label>
        <input type="password" id="pActual" name="password_actual" maxlength="200" autocomplete="current-password"></div>
      <div class="campo-p"><label for="pNueva">Nueva contraseña</label>
        <input type="password" id="pNueva" name="password" maxlength="200" autocomplete="new-password">
        <p class="ayuda-p">Mínimo 8 caracteres, con letras y números.</p></div>
      <div class="campo-p"><label for="pNueva2">Repite la nueva</label>
        <input type="password" id="pNueva2" name="password2" maxlength="200" autocomplete="new-password"></div>
      <div class="aviso aviso--info" style="margin-top:14px">
        <?= icon('info') ?>
        <span>Al cambiar tu contraseña se cerrarán las demás sesiones que hayas dejado abiertas.</span>
      </div>
    </div>
  </div>
  <div class="tarjeta-p" style="text-align:right">
    <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar cambios</button>
  </div>
</form>
