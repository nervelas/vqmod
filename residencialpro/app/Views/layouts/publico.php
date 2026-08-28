<?php
use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Url;
use App\Core\Vista;

$logo    = Ajustes::get('logo', '');
$nombre  = Ajustes::get('nombre', 'ResidencialPro');
$iniciales = mb_strtoupper(mb_substr(preg_replace('/^(Residencial|Condominio)\s+/iu', '', $nombre) ?: $nombre, 0, 1));
?><!DOCTYPE html>
<html lang="es" data-tema="<?= e(Ajustes::get('tema', 'verde-oro')) ?>" data-modo="claro"
      data-base="<?= e(Url::basePath()) ?>">
<head><?= Vista::parcial('partials/head', ['titulo' => $tituloPagina ?? $nombre, 'indexable' => true,
    'precargarPortada' => !empty($conPortada) && empty($hayPortadaPropia),
    'precargarFuentes' => true]) ?></head>
<body class="web">
<a class="saltar-a" href="#contenido-principal">Ir al contenido</a>

<header class="web-tope <?= empty($conPortada) ? 'fijo asentado' : '' ?>" data-tope-web<?= empty($conPortada) ? ' data-tope-fijo' : '' ?>>
  <a class="web-marca" href="<?= e(url('/')) ?>">
    <span class="escudo">
      <?php if ($logo !== '' && is_file(RUTA_BASE . '/uploads/logos/' . $logo)): ?>
        <img src="<?= e(subida($logo, 'logos')) ?>" alt="" width="38" height="38">
      <?php else: ?><?= e($iniciales) ?><?php endif; ?>
    </span>
    <span>
      <span class="n"><?= e($nombre) ?></span>
      <span class="sub"><?= e(recortar(Ajustes::get('lema', 'Residencial privado'), 26)) ?></span>
    </span>
  </a>

  <nav id="web-nav" aria-label="Secciones del sitio">
    <a href="<?= e(url('/')) ?>#residencial">El residencial</a>
    <a href="<?= e(url('/')) ?>#amenidades">Amenidades</a>
    <a href="<?= e(url('/')) ?>#administracion">Administración</a>
    <a href="<?= e(url('/')) ?>#ubicacion">Ubicación</a>
    <a href="<?= e(url('/contacto')) ?>">Contacto</a>
  </nav>

  <div class="web-acciones">
    <a class="btn btn-claro btn-sm solo-escritorio" href="<?= e(url('/verificar')) ?>">Verificar un recibo</a>
    <a class="btn btn-oro btn-sm" href="<?= e(url(Auth::invitado() ? '/acceso' : '/portal')) ?>">
      <?= ico('entrar', 16) ?>
      <span><?= Auth::invitado() ? 'Portal' : 'Mi portal' ?></span>
    </a>
    <button class="web-menu-btn" type="button" data-menu-web aria-expanded="false"
            aria-controls="web-nav" aria-label="Mostrar u ocultar el menú"><?= ico('menu', 21) ?></button>
  </div>
</header>

<main id="contenido-principal"><?= $contenido ?></main>

<footer class="web-pie">
  <div class="contenedor">
    <div class="pie-rejilla">
      <div class="pie-ancho">
        <h3>El residencial</h3>
        <p style="font-size:.9375rem;line-height:1.7;max-width:46ch;color:color-mix(in srgb,#fff 70%,transparent)">
          <?= e(recortar(Ajustes::get('descripcion', ''), 230)) ?>
        </p>
      </div>
      <div>
        <h3>Contacto</h3>
        <ul class="lista-limpia">
          <?php if (Ajustes::get('direccion', '') !== ''): ?><li><?= e(Ajustes::get('direccion')) ?></li><?php endif; ?>
          <?php if (Ajustes::get('telefono', '') !== ''): ?><li><a href="tel:<?= e(preg_replace('/\s+/', '', Ajustes::get('telefono'))) ?>"><?= e(Ajustes::get('telefono')) ?></a></li><?php endif; ?>
          <?php if (Ajustes::get('correo', '') !== ''): ?><li><a href="mailto:<?= e(Ajustes::get('correo')) ?>"><?= e(Ajustes::get('correo')) ?></a></li><?php endif; ?>
        </ul>
      </div>
      <div>
        <h3>Residentes</h3>
        <ul class="lista-limpia">
          <li><a href="<?= e(url('/acceso')) ?>">Portal del residente</a></li>
          <li><a href="<?= e(url('/instalar')) ?>">Instalar en el teléfono</a></li>
          <li><a href="<?= e(url('/verificar')) ?>">Verificar un recibo</a></li>
          <li><a href="<?= e(url('/reglamento')) ?>">Reglamento interno</a></li>
          <li><a href="<?= e(url('/contacto')) ?>">Escribir a la administración</a></li>
        </ul>
      </div>
    </div>

    <div class="pie-legal">
      <span>© <?= date('Y') ?> <?= e($nombre) ?>. Todos los derechos reservados.</span>
      <a class="firma" href="https://deerflow.tech" target="_blank" rel="noopener">Created by Deerflow</a>
    </div>
  </div>
</footer>

<script<?= nonce() ?> src="<?= e(url('/assets/js/app.js')) ?>?v=<?= RPRO_VERSION ?>"></script>
<?php if (!empty($scripts)): ?><?= $scripts ?><?php endif; ?>
</body>
</html>
