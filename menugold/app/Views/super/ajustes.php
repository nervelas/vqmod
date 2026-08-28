<?php
/** @var array $v, $slugs; string $cron */
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Ajustes de la plataforma');
View::set('subtitulo', 'Marca, textos de venta, correo y automatizaciones');
?>
<form method="post" action="<?= e(url('super/ajustes')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="rejilla rejilla--2">
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('crown') ?> Tu marca</h2></div>
      <div class="campo-p"><label for="aNombre">Nombre de la plataforma</label>
        <input type="text" id="aNombre" name="nombre_plataforma" maxlength="80" value="<?= e($v['nombre_plataforma']) ?>"></div>
      <div class="campo-p"><label for="aEslogan">Eslogan</label>
        <input type="text" id="aEslogan" name="eslogan" maxlength="190" value="<?= e($v['eslogan']) ?>"></div>
      <div class="campo-p"><label for="aDesc">Descripción</label>
        <textarea id="aDesc" name="descripcion" maxlength="600"><?= e($v['descripcion']) ?></textarea></div>
      <div class="fila-campos">
        <div class="campo-p"><label for="aEmail">Correo de contacto</label>
          <input type="email" id="aEmail" name="email_contacto" maxlength="190" value="<?= e($v['email_contacto']) ?>">
          <p class="ayuda-p">Aquí llegan los mensajes de la página de venta.</p></div>
        <div class="campo-p"><label for="aWa">WhatsApp</label>
          <input type="tel" id="aWa" name="whatsapp" maxlength="30" value="<?= e($v['whatsapp']) ?>"></div>
      </div>
      <div class="fila-campos">
        <div class="campo-p"><label for="aTel">Teléfono</label>
          <input type="tel" id="aTel" name="telefono" maxlength="30" value="<?= e($v['telefono']) ?>"></div>
        <div class="campo-p"><label for="aDir">Dirección</label>
          <input type="text" id="aDir" name="direccion" maxlength="190" value="<?= e($v['direccion']) ?>"></div>
      </div>
      <div class="fila-campos">
        <div class="campo-p"><label for="aFb">Facebook</label>
          <input type="url" id="aFb" name="facebook" maxlength="190" value="<?= e($v['facebook']) ?>"></div>
        <div class="campo-p"><label for="aIg">Instagram</label>
          <input type="url" id="aIg" name="instagram" maxlength="190" value="<?= e($v['instagram']) ?>"></div>
      </div>
    </div>

    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('target') ?> Página de venta</h2></div>
      <div class="campo-p"><label for="aHero">Título principal</label>
        <input type="text" id="aHero" name="hero_titulo" maxlength="190" value="<?= e($v['hero_titulo']) ?>"></div>
      <div class="campo-p"><label for="aHeroSub">Subtítulo</label>
        <textarea id="aHeroSub" name="hero_subtitulo" maxlength="400"><?= e($v['hero_subtitulo']) ?></textarea></div>
      <div class="campo-p"><label for="aCta">Texto del botón principal</label>
        <input type="text" id="aCta" name="cta_texto" maxlength="60" value="<?= e($v['cta_texto']) ?>"></div>
      <div class="campo-p"><label for="aDemo">Restaurante de demostración</label>
        <select id="aDemo" name="demo_slug">
          <option value="">Ninguno</option>
          <?php foreach ($slugs as $slug => $nombre): ?>
            <option value="<?= e((string)$slug) ?>" <?= $v['demo_slug'] === $slug ? 'selected' : '' ?>><?= e((string)$nombre) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="ayuda-p">Es el menú que se abre al tocar «Ver demostración».</p></div>

      <label class="etiqueta-campo">Logo de la plataforma</label>
      <div class="previa-foto" id="previaLogoPlat" style="<?= empty($v['landing_logo']) ? 'display:none' : '' ?>;margin-bottom:10px">
        <img src="<?= e($v['landing_logo'] ? uploaded($v['landing_logo']) : '') ?>" alt="">
      </div>
      <label class="subir-foto" style="margin-bottom:14px">
        <input type="file" name="landing_logo" accept="image/*" data-previsualizar="#previaLogoPlat">
        <?= icon('upload') ?><span class="subir-foto__texto">Sube tu logo</span>
      </label>

      <label class="etiqueta-campo">Imagen principal de la página</label>
      <div class="previa-foto" id="previaImgPlat" style="<?= empty($v['landing_imagen']) ? 'display:none' : '' ?>;margin-bottom:10px">
        <img src="<?= e($v['landing_imagen'] ? uploaded($v['landing_imagen']) : '') ?>" alt="">
      </div>
      <label class="subir-foto">
        <input type="file" name="landing_imagen" accept="image/*" data-previsualizar="#previaImgPlat">
        <?= icon('image') ?><span class="subir-foto__texto">Una foto que venda: mesa servida, QR en uso…</span>
      </label>
    </div>
  </div>

  <div class="rejilla rejilla--2">
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('mail') ?> Correo de la plataforma</h2></div>
      <p class="ayuda-p" style="margin-top:0">Se usa para todos los restaurantes que no tengan SMTP propio.</p>
      <div class="fila-campos">
        <div class="campo-p"><label for="sHost">Servidor SMTP</label>
          <input type="text" id="sHost" name="smtp_host" maxlength="190" value="<?= e($v['smtp_host']) ?>"></div>
        <div class="campo-p"><label for="sPuerto">Puerto</label>
          <input type="number" id="sPuerto" name="smtp_puerto" min="1" max="65535" value="<?= e($v['smtp_puerto'] ?: '587') ?>"></div>
      </div>
      <div class="fila-campos">
        <div class="campo-p"><label for="sUsuario">Usuario</label>
          <input type="text" id="sUsuario" name="smtp_usuario" maxlength="190" value="<?= e($v['smtp_usuario']) ?>" autocomplete="off"></div>
        <div class="campo-p"><label for="sClave">Contraseña</label>
          <input type="password" id="sClave" name="smtp_clave" maxlength="190" autocomplete="new-password"
                 placeholder="<?= !empty($v['tiene_smtp_clave']) ? '•••••••• (guardada)' : '' ?>"></div>
      </div>
      <div class="fila-campos">
        <div class="campo-p"><label for="sSeg">Seguridad</label>
          <select id="sSeg" name="smtp_seguridad">
            <option value="tls" <?= $v['smtp_seguridad'] === 'tls' ? 'selected' : '' ?>>TLS</option>
            <option value="ssl" <?= $v['smtp_seguridad'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
            <option value="" <?= $v['smtp_seguridad'] === '' ? 'selected' : '' ?>>Ninguna</option>
          </select></div>
        <div class="campo-p"><label for="sDesde">Correo remitente</label>
          <input type="email" id="sDesde" name="smtp_desde" maxlength="190" value="<?= e($v['smtp_desde']) ?>"></div>
      </div>
    </div>

    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('refresh') ?> Automatizaciones</h2></div>
      <label class="interruptor" style="margin-bottom:14px">
        <input type="checkbox" name="backup_semanal" value="1" <?= $v['backup_semanal'] === '1' ? 'checked' : '' ?>>
        <span class="interruptor__pista"></span>
        <span class="interruptor__texto">Respaldo automático semanal (domingos)</span>
      </label>
      <div class="campo-p"><label for="aAviso">Avisar el vencimiento con (días de anticipación)</label>
        <input type="number" id="aAviso" name="aviso_vencimiento_dias" min="0" max="60"
               value="<?= e($v['aviso_vencimiento_dias'] ?: '7') ?>">
        <p class="ayuda-p">Se envía un correo al dueño del restaurante.</p></div>

      <label class="etiqueta-campo">Tarea programada (cron)</label>
      <input class="entrada mono" type="text" readonly id="cronCmd" style="font-size:12px"
             value='*/10 * * * * curl -s "<?= e(url('cron/run.php', ['token' => $cron])) ?>"'>
      <button class="bt bt--sm bt--linea bt--bloque" type="button" data-copiar="#cronCmd" style="margin-top:8px">
        <?= icon('copy') ?> Copiar comando
      </button>
      <p class="ayuda-p">
        Pégalo en cPanel → Trabajos cron. Se encarga de los vencimientos, los avisos,
        la limpieza de pedidos abandonados y los respaldos automáticos.
      </p>
    </div>
  </div>

  <div class="rejilla rejilla--2">
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('file') ?> Términos y condiciones</h2></div>
      <textarea class="entrada" name="terminos" rows="8" maxlength="2500"><?= e($v['terminos']) ?></textarea>
    </div>
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('shield') ?> Aviso de privacidad</h2></div>
      <textarea class="entrada" name="privacidad" rows="8" maxlength="2500"><?= e($v['privacidad']) ?></textarea>
    </div>
  </div>

  <div class="tarjeta-p" style="text-align:right">
    <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar ajustes</button>
  </div>
</form>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
document.addEventListener('click', function (ev) {
  var c = ev.target.closest('[data-copiar]');
  if (!c) return;
  var i = document.querySelector(c.dataset.copiar);
  if (!i) return;
  i.select();
  if (navigator.clipboard) navigator.clipboard.writeText(i.value);
  window.MGPanel.avisar('Comando copiado', 'ok');
});
</script>
<?php View::stop(); ?>
