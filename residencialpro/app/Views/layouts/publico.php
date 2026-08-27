<?php
use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Url;
use App\Core\Vista;

$logo = Ajustes::get('logo', '');
?><!DOCTYPE html>
<html lang="es" data-tema="<?= e(Ajustes::get('tema', 'verde-oro')) ?>" data-modo="claro"
      data-base="<?= e(Url::basePath()) ?>" data-color-marca="<?= e(Ajustes::get('color_primario', '#0F2E24')) ?>">
<head><?= Vista::parcial('partials/head', ['titulo' => $tituloPagina ?? Ajustes::get('nombre', 'ResidencialPro'), 'indexable' => true]) ?></head>
<body>
<a class="saltar-a" href="#contenido-principal">Ir al contenido</a>
<header class="web-tope">
  <div class="contenedor">
    <a class="web-marca" href="<?= e(url('/')) ?>">
      <?php if ($logo !== '' && is_file(RUTA_BASE . '/uploads/logos/' . $logo)): ?>
        <img src="<?= e(subida($logo, 'logos')) ?>" alt="" width="42" height="42" style="border-radius:10px">
      <?php endif; ?>
      <span>
        <b><?= e(Ajustes::get('nombre', 'ResidencialPro')) ?></b>
        <span><?= e(Ajustes::get('lema', 'Residencial privado')) ?></span>
      </span>
    </a>
    <nav id="web-nav" aria-label="Secciones del sitio">
      <a href="<?= e(url('/')) ?>#amenidades">Amenidades</a>
      <a href="<?= e(url('/')) ?>#galeria">Galería</a>
      <a href="<?= e(url('/')) ?>#ubicacion">Ubicación</a>
      <a href="<?= e(url('/contacto')) ?>">Contacto</a>
    </nav>
    <div class="web-acciones">
      <a class="btn btn-oro btn-sm" href="<?= e(url(Auth::invitado() ? '/acceso' : '/portal')) ?>">
        <?= ico('entrar', 16) ?> <span class="solo-escritorio"><?= Auth::invitado() ? 'Portal de residentes' : 'Mi portal' ?></span><span class="solo-movil">Portal</span>
      </a>
      <button class="icono-btn web-menu-btn" type="button" data-menu-web aria-expanded="false"
              aria-controls="web-nav" aria-label="Mostrar u ocultar el menú"><?= ico('menu', 21) ?></button>
    </div>
  </div>
</header>

<main id="contenido-principal"><?= $contenido ?></main>

<footer class="web-pie">
  <div class="contenedor">
    <div class="rejilla rejilla-3">
      <div>
        <h4><?= e(Ajustes::get('nombre', 'ResidencialPro')) ?></h4>
        <p style="font-size:.9rem"><?= e(recortar(Ajustes::get('descripcion', ''), 190)) ?></p>
      </div>
      <div>
        <h4>Contacto</h4>
        <p style="font-size:.9rem">
          <?= e(Ajustes::get('direccion', '')) ?><br>
          <?php if (Ajustes::get('telefono', '') !== ''): ?>Tel. <?= e(Ajustes::get('telefono')) ?><br><?php endif; ?>
          <?php if (Ajustes::get('correo', '') !== ''): ?><a href="mailto:<?= e(Ajustes::get('correo')) ?>"><?= e(Ajustes::get('correo')) ?></a><?php endif; ?>
        </p>
      </div>
      <div>
        <h4>Residentes</h4>
        <p style="font-size:.9rem">
          <a href="<?= e(url('/acceso')) ?>">Portal del residente</a><br>
          <a href="<?= e(url('/contacto')) ?>">Escribir a la administración</a><br>
          <a href="<?= e(url('/reglamento')) ?>">Reglamento interno</a>
        </p>
      </div>
    </div>
    <div class="abajo">
      <span>© <?= date('Y') ?> <?= e(Ajustes::get('nombre', 'ResidencialPro')) ?>. Todos los derechos reservados.</span>
      <span>Administrado con ResidencialPro</span>
    </div>
  </div>
</footer>
<script<?= nonce() ?> src="<?= e(url('/assets/js/app.js')) ?>?v=<?= RPRO_VERSION ?>"></script>
<script<?= nonce() ?>>
document.querySelectorAll('[data-menu-web]').forEach(function (b) {
  b.addEventListener('click', function () {
    var nav = document.getElementById('web-nav');
    var abierto = nav.classList.toggle('abierto');
    b.setAttribute('aria-expanded', abierto ? 'true' : 'false');
  });
});
</script>
<?php if (!empty($scripts)): ?><?= $scripts ?><?php endif; ?>
</body>
</html>
