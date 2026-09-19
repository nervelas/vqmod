<?php
/** Kaptor - Acceso de usuarios. */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

/**
 * A dónde iba el visitante antes de que se le pidiera la sesión.
 *
 * Solo se admiten rutas de este mismo sitio: nada de "//otro.com" ni de
 * direcciones absolutas, para que el acceso no sirva de trampolín a una
 * página ajena.
 */
function cr_destino_seguro(string $volver): string
{
    $volver = trim($volver);
    if ($volver === '' || $volver[0] !== '/' || str_starts_with($volver, '//')) { return ''; }
    if (str_contains($volver, "\n") || str_contains($volver, "\r")) { return ''; }

    $base = rtrim((string) parse_url(cr_url_base(), PHP_URL_PATH), '/');
    if ($base !== '' && str_starts_with($volver, $base . '/')) {
        $volver = substr($volver, strlen($base));
    }
    $volver = ltrim($volver, '/');

    // Solo páginas de verdad, nunca el propio acceso (daría una vuelta infinita).
    if (!preg_match('~^[A-Za-z0-9._/-]{1,120}(\?[^\s]{0,200})?$~', $volver)) { return ''; }
    if (str_contains($volver, '..') || str_starts_with($volver, 'login.php')) { return ''; }

    return $volver;
}

$volver = cr_destino_seguro((string) ($_GET['volver'] ?? ($_POST['volver'] ?? '')));

if (Auth::autenticado()) { cr_redirigir($volver !== '' ? $volver : 'index.php'); }

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Seguridad::exigirCsrf();
    $res = Auth::entrar((string) cr_post('usuario'), (string) cr_post('clave'), false, cr_post('recordar') !== '');
    if ($res['ok']) {
        cr_flash('exito', 'Sesión iniciada. Ya puedes extraer correos.');
        if ($volver !== '') { cr_redirigir($volver); }
        cr_redirigir(Auth::esAdmin() ? 'admin/index.php' : 'index.php');
    }
    $error = $res['error'] ?? 'No se pudo iniciar sesión.';
}

cr_cabecera(['titulo' => 'Acceder', 'activo' => 'login']);
?>
<section class="seccion">
  <div class="contenedor">
    <div class="tarjeta form-caja">
      <h1>Acceder</h1>
      <p class="sub">Entra con tu cuenta de <?= e(Ajustes::obtener('sitio_nombre')) ?>.</p>

      <?php if ($error !== ''): ?>
        <div class="aviso aviso-error" role="alert">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5h.01"/>
          </svg>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="on">
        <?= Seguridad::campoCsrf() ?>
        <?php if ($volver !== ''): ?><input type="hidden" name="volver" value="<?= e($volver) ?>"><?php endif; ?>
        <div class="campo-grupo">
          <label class="etiqueta" for="usuario">Usuario o correo</label>
          <input type="text" id="usuario" name="usuario" class="campo" required autofocus
                 value="<?= e((string) cr_post('usuario')) ?>" autocomplete="username">
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="clave">Contraseña</label>
          <input type="password" id="clave" name="clave" class="campo" required autocomplete="current-password">
        </div>
        <label class="recordar">
          <input type="checkbox" name="recordar" value="1" checked>
          <span>Mantener la sesión iniciada en este equipo</span>
        </label>
        <button type="submit" class="btn btn-bloque">Entrar</button>
      </form>

    </div>
  </div>
</section>
<?php cr_pie(); ?>
