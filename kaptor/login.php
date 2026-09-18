<?php
/** Kaptor - Acceso de usuarios. */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

if (Auth::autenticado()) { cr_redirigir('index.php'); }

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Seguridad::exigirCsrf();
    $res = Auth::entrar((string) cr_post('usuario'), (string) cr_post('clave'), false, cr_post('recordar') !== '');
    if ($res['ok']) {
        cr_flash('exito', 'Sesión iniciada. Ya puedes extraer correos.');
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
