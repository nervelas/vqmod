<div class="acceso-pantalla">
  <section class="acceso-arte">
    <div>
      <h1>Cree una contraseña nueva</h1>
      <p>Use al menos 10 caracteres combinando letras y números. Evite datos fáciles de adivinar.</p>
    </div>
  </section>
  <section class="acceso-forma">
    <div class="caja">
      <?php if (!empty($invalido)): ?>
        <div class="aviso-caja error"><?= ico('equisCirculo', 20) ?>
          <div><strong>Este enlace ya no es válido</strong>
            Puede haber vencido o haberse usado antes. Solicite uno nuevo.</div>
        </div>
        <p class="centrado mt-3"><a class="btn btn-oro" href="<?= e(url('/recuperar')) ?>">Solicitar enlace nuevo</a></p>
      <?php else: ?>
        <h2>Nueva contraseña</h2>
        <?php if ($error !== ''): ?>
          <div class="aviso-caja error mb-2" role="alert"><?= ico('alerta', 19) ?><div><?= e($error) ?></div></div>
        <?php endif; ?>
        <form method="post">
          <?= csrf() ?>
          <div class="campo">
            <label for="clave">Contraseña nueva</label>
            <div class="entrada-icono"><?= ico('candado', 18) ?>
              <input type="password" id="clave" name="clave" minlength="10" required autocomplete="new-password" autofocus>
            </div>
            <span class="ayuda">Mínimo 10 caracteres, con letras y números.</span>
          </div>
          <div class="campo">
            <label for="clave2">Repita la contraseña</label>
            <div class="entrada-icono"><?= ico('candado', 18) ?>
              <input type="password" id="clave2" name="clave2" minlength="10" required autocomplete="new-password">
            </div>
          </div>
          <button class="btn btn-oro btn-lg btn-bloque" type="submit"><?= ico('guardar', 19) ?> Guardar contraseña</button>
        </form>
      <?php endif; ?>
    </div>
  </section>
</div>
