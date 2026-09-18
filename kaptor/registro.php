<?php
/** Kaptor - Registro público de usuarios (se puede desactivar en Ajustes). */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

if (Auth::autenticado()) { cr_redirigir('index.php'); }
if (!Ajustes::activo('registro_publico', true)) {
    cr_flash('error', 'El registro de nuevas cuentas esta desactivado.');
    cr_redirigir('login.php');
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Seguridad::exigirCsrf();

    if (Seguridad::intentosFallidos() >= 10) {
        $error = 'Demasiados intentos. Espera unos minutos.';
    } else {
        $res = Auth::crear(
            (string) cr_post('usuario'),
            (string) cr_post('email'),
            (string) cr_post('clave'),
            'usuario',
            (string) cr_post('nombre')
        );
        if ($res['ok']) {
            Auth::entrar((string) cr_post('usuario'), (string) cr_post('clave'));
            cr_flash('exito', '¡Cuenta creada! Ya puedes extraer correos.');
            cr_redirigir('index.php');
        }
        $error = $res['error'] ?? 'No se pudo crear la cuenta.';
        Seguridad::registrarIntento((string) cr_post('usuario'), false);
    }
}

cr_cabecera(['titulo' => 'Crear cuenta', 'activo' => 'registro']);
?>
<section class="seccion">
  <div class="contenedor">
    <div class="tarjeta form-caja">
      <h1>Crear cuenta</h1>
      <p class="sub">Gratis y en menos de un minuto.</p>

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
          <label class="etiqueta" for="nombre">Nombre (opcional)</label>
          <input type="text" id="nombre" name="nombre" class="campo" value="<?= e((string) cr_post('nombre')) ?>" autocomplete="name">
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="usuario">Usuario</label>
          <input type="text" id="usuario" name="usuario" class="campo" required minlength="3" maxlength="64"
                 pattern="[a-zA-Z0-9._\-]+" value="<?= e((string) cr_post('usuario')) ?>" autocomplete="username">
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="email">Correo electrónico</label>
          <input type="email" id="email" name="email" class="campo" required
                 value="<?= e((string) cr_post('email')) ?>" autocomplete="email">
        </div>
        <div class="campo-grupo">
          <label class="etiqueta" for="clave">Contraseña (mínimo 8 caracteres)</label>
          <input type="password" id="clave" name="clave" class="campo" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-bloque">Crear mi cuenta</button>
      </form>

      <p class="centrado pequeno suave" style="margin-top:20px">
        ¿Ya tienes cuenta? <a href="<?= e(cr_url('login.php')) ?>">Acceder</a>
      </p>
    </div>
  </div>
</section>
<?php cr_pie(); ?>
