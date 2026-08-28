<?php use App\Core\Ajustes; use App\Core\Sesion; ?>
<section class="seccion">
  <div class="contenedor-sm">
    <div class="seccion-tit">
      <div class="lema">Estamos para servirle</div>
      <h2>Contactar a la administración</h2>
      <p>Escríbanos y le responderemos en el menor tiempo posible.</p>
    </div>

    <?php if ($enviado): ?>
      <div class="aviso-caja ok mb-3"><?= ico('checkCirculo', 20) ?>
        <div><strong>Mensaje enviado</strong> Gracias por escribirnos. La administración le responderá pronto.</div>
      </div>
    <?php endif; ?>

    <?php foreach ($errores as $er): ?>
      <div class="aviso-caja error mb-2"><?= ico('alerta', 19) ?><div><?= e($er) ?></div></div>
    <?php endforeach; ?>

    <div class="tarjeta">
      <div class="tarjeta-cuerpo">
        <form method="post" novalidate>
          <?= csrf() ?>
          <div style="position:absolute;left:-9999px" aria-hidden="true">
            <label for="sitio_web">No llenar</label>
            <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
          </div>
          <div class="campos">
            <div class="campo">
              <label for="nombre">Nombre completo *</label>
              <input type="text" id="nombre" name="nombre" required maxlength="140" value="<?= e(Sesion::viejo('nombre')) ?>">
            </div>
            <div class="campo">
              <label for="telefono">Teléfono</label>
              <input type="tel" id="telefono" name="telefono" maxlength="40" value="<?= e(Sesion::viejo('telefono')) ?>">
            </div>
            <div class="campo campo-ancho">
              <label for="correo">Correo electrónico</label>
              <input type="email" id="correo" name="correo" maxlength="160" value="<?= e(Sesion::viejo('correo')) ?>">
            </div>
            <div class="campo campo-ancho">
              <label for="mensaje">Mensaje *</label>
              <textarea id="mensaje" name="mensaje" required maxlength="3000" rows="6"></textarea>
            </div>
            <div class="campo">
              <label for="captcha">Verificación: ¿cuánto es <?= (int) $sumaA ?> + <?= (int) $sumaB ?>? *</label>
              <input type="number" id="captcha" name="captcha" required inputmode="numeric" style="max-width:140px">
            </div>
          </div>
          <div class="fila-fin mt-2">
            <button class="btn btn-oro btn-lg" type="submit"><?= ico('enviar', 19) ?> Enviar mensaje</button>
          </div>
        </form>
      </div>
    </div>

    <div class="rejilla rejilla-3 mt-3">
      <?php if (Ajustes::get('telefono', '') !== ''): ?>
        <div class="tarjeta"><div class="tarjeta-cuerpo centrado">
          <div style="color:var(--arcilla)"><?= ico('telefono', 24) ?></div>
          <div class="mayus mt-1">Teléfono</div>
          <b><?= e(Ajustes::get('telefono')) ?></b>
        </div></div>
      <?php endif; ?>
      <?php if (Ajustes::get('correo', '') !== ''): ?>
        <div class="tarjeta"><div class="tarjeta-cuerpo centrado">
          <div style="color:var(--arcilla)"><?= ico('correo', 24) ?></div>
          <div class="mayus mt-1">Correo</div>
          <b style="font-size:.9rem"><?= e(Ajustes::get('correo')) ?></b>
        </div></div>
      <?php endif; ?>
      <div class="tarjeta"><div class="tarjeta-cuerpo centrado">
        <div style="color:var(--arcilla)"><?= ico('pin', 24) ?></div>
        <div class="mayus mt-1">Dirección</div>
        <b style="font-size:.88rem"><?= e(recortar(Ajustes::get('direccion', 'Consulte con la administración'), 70)) ?></b>
      </div></div>
    </div>
  </div>
</section>
