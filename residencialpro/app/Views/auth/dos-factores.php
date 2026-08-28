<div class="acceso-pantalla">
  <section class="acceso-arte">
    <div>
      <h1>Verificación en dos pasos</h1>
      <p>Enviamos un código de seis dígitos a su correo electrónico. Es válido por 10 minutos y solo puede usarse una vez.</p>
    </div>
  </section>
  <section class="acceso-forma">
    <div class="caja">
      <h2>Ingrese su código</h2>
      <p class="texto-3" style="font-size:.92rem">Revise su bandeja de entrada y la carpeta de correo no deseado.</p>
      <?php if ($error !== ''): ?>
        <div class="aviso-caja error mb-2" role="alert"><?= ico('alerta', 19) ?><div><?= e($error) ?></div></div>
      <?php endif; ?>
      <form method="post">
        <?= csrf() ?>
        <div class="campo">
          <label for="codigo">Código de verificación</label>
          <input type="text" id="codigo" name="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6" required
                 autocomplete="one-time-code" style="font-size:1.7rem;letter-spacing:.5em;text-align:center" autofocus>
        </div>
        <button class="btn btn-oro btn-lg btn-bloque" type="submit"><?= ico('checkCirculo', 19) ?> Verificar</button>
      </form>
      <p class="centrado mt-3"><a href="<?= e(url('/acceso')) ?>" style="font-size:.85rem">Volver al inicio de sesión</a></p>
    </div>
  </section>
</div>
