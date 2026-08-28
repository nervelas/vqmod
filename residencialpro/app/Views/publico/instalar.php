<?php
use App\Core\Ajustes;
$nombre = Ajustes::get('nombre', 'el residencial');
?>
<section class="instalar-cab">
  <div class="contenedor">
    <p class="eyebrow-inst">Portal de residentes</p>
    <h1>Lleve <?= e($nombre) ?><br>en su teléfono</h1>
    <p class="bajada-inst">Consulte su saldo, pague, autorice visitas con código QR y reserve
      áreas comunes. Se instala desde el navegador: no ocupa casi espacio y no hay que
      buscarlo en ninguna tienda.</p>
    <button class="btn btn-oro btn-lg" type="button" id="btn-instalar" hidden>
      <?= ico('descargar', 20) ?> Instalar ahora
    </button>
    <a class="btn btn-claro btn-lg" href="<?= e(url('/acceso')) ?>"><?= ico('entrar', 18) ?> Entrar sin instalar</a>
  </div>
</section>

<section class="seccion">
  <div class="contenedor inst-rejilla">

    <div class="inst-qr">
      <div class="qr-marco"><?= $qr ?></div>
      <p class="qr-pie">Apunte la cámara de su teléfono a este código</p>
      <p class="qr-url"><?= e($enlace) ?></p>
    </div>

    <div>
      <div class="paso-grupo" data-so="android">
        <h2><?= ico('telefono', 20) ?> Android · Chrome</h2>
        <ol class="pasos-inst">
          <li>Abra la dirección de arriba en <b>Chrome</b>.</li>
          <li>Toque el menú <b>⋮</b> arriba a la derecha.</li>
          <li>Elija <b>Instalar aplicación</b> (o <i>Añadir a pantalla de inicio</i>).</li>
          <li>Confirme. El icono queda junto a sus otras aplicaciones.</li>
        </ol>
      </div>

      <div class="paso-grupo" data-so="ios">
        <h2><?= ico('telefono', 20) ?> iPhone · Safari</h2>
        <ol class="pasos-inst">
          <li>Abra la dirección en <b>Safari</b>. En iPhone debe ser Safari, no otro navegador.</li>
          <li>Toque el botón <b>Compartir</b> (el cuadrado con la flecha hacia arriba).</li>
          <li>Deslice y elija <b>Añadir a pantalla de inicio</b>.</li>
          <li>Toque <b>Añadir</b>.</li>
        </ol>
      </div>

      <div class="paso-grupo">
        <h2><?= ico('panel', 20) ?> Computadora</h2>
        <ol class="pasos-inst">
          <li>Abra la dirección en Chrome, Edge o Brave.</li>
          <li>Pulse el icono de <b>instalar</b> que aparece en la barra de direcciones.</li>
        </ol>
      </div>

      <div class="aviso-caja info">
        <?= ico('info', 20) ?>
        <div>Sus datos de acceso se los entrega la administración del residencial.
          Si los perdió, use <b>¿Olvidó su contraseña?</b> en la pantalla de acceso.</div>
      </div>
    </div>
  </div>
</section>

<script<?= nonce() ?>>
(function () {
  // El navegador avisa cuando la instalación es posible: hasta entonces el
  // botón no se muestra, para no ofrecer algo que no va a funcionar.
  var pendiente = null;
  var boton = document.getElementById('btn-instalar');
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); pendiente = e; boton.hidden = false;
  });
  boton.addEventListener('click', async function () {
    if (!pendiente) return;
    pendiente.prompt();
    var r = await pendiente.userChoice;
    pendiente = null; boton.hidden = true;
    if (r && r.outcome === 'accepted') { RP.aviso('Instalado. Búsquelo en su pantalla de inicio.'); }
  });
  window.addEventListener('appinstalled', function () {
    boton.hidden = true;
    RP.aviso('Ya quedó instalado en este dispositivo.');
  });
  // En iPhone no existe beforeinstallprompt: se resaltan sus pasos.
  var esIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
  var grupo = document.querySelector('[data-so="' + (esIos ? 'ios' : 'android') + '"]');
  if (grupo) { grupo.classList.add('destacado'); }
})();
</script>
