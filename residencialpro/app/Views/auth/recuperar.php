<div class="acceso-pantalla">
  <section class="acceso-arte">
    <div>
      <h1>Recupere el acceso a su cuenta</h1>
      <p>Le enviaremos un enlace seguro para crear una contraseña nueva. El enlace vence en 30 minutos y solo funciona una vez.</p>
    </div>
  </section>
  <section class="acceso-forma">
    <div class="caja">
      <?php if ($enviado): ?>
        <div class="aviso-caja ok"><?= ico('checkCirculo', 20) ?>
          <div><strong>Revise su correo</strong>
            Si el correo está registrado en el sistema, recibirá el enlace en unos minutos.</div>
        </div>
        <p class="centrado mt-3"><a class="btn btn-claro" href="<?= e(url('/acceso')) ?>">Volver al acceso</a></p>
      <?php else: ?>
        <h2>Recuperar contraseña</h2>
        <p class="texto-3" style="font-size:.92rem">Escriba el correo electrónico con el que está registrado.</p>
        <?php if ($error !== ''): ?>
          <div class="aviso-caja error mb-2" role="alert"><?= ico('alerta', 19) ?><div><?= e($error) ?></div></div>
        <?php endif; ?>
        <form method="post">
          <?= csrf() ?>
          <div class="campo">
            <label for="correo">Correo electrónico</label>
            <div class="entrada-icono"><?= ico('correo', 18) ?>
              <input type="email" id="correo" name="correo" required autofocus placeholder="nombre@correo.com">
            </div>
          </div>
          <button class="btn btn-oro btn-lg btn-bloque" type="submit"><?= ico('enviar', 19) ?> Enviar enlace</button>
        </form>
        <p class="centrado mt-3"><a href="<?= e(url('/acceso')) ?>" style="font-size:.85rem">Volver al acceso</a></p>
      <?php endif; ?>
    </div>
  </section>
</div>
