<?php
/**
 * CorreoRadar - Baja de la lista de correo.
 *
 * Es la página a la que lleva el enlace "Darse de baja" de cada mensaje y
 * también el destino de la baja en un clic (RFC 8058): los proveedores mandan
 * un POST y esperan que se procese sin pedir confirmación.
 *
 * La baja es inmediata y definitiva: la dirección pasa a la lista de supresión
 * y ninguna campaña volverá a escribirle.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once CR_INCLUDES . '/plantilla.php';

$token = trim((string) ($_GET['t'] ?? $_POST['t'] ?? ''));
$esUnClic = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

$resultado = ['ok' => false];
if ($token !== '' && preg_match('/^[a-f0-9]{32}$/', $token)) {
    $resultado = Campana::procesarBaja($token);
}

// Baja en un clic: el proveedor solo espera un 200, sin página.
if ($esUnClic) {
    http_response_code($resultado['ok'] ? 200 : 400);
    header('Content-Type: text/plain; charset=utf-8');
    exit($resultado['ok'] ? 'Baja registrada' : 'Enlace no válido');
}

cr_cabecera(['titulo' => 'Baja de la lista de correo']);
?>
<section class="seccion">
  <div class="contenedor">
    <div class="tarjeta form-caja centrado">
      <?php if (!empty($resultado['ok'])): ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--neon)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
             style="width:56px;height:56px;margin:0 auto 18px" aria-hidden="true">
          <circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.5 2.5L16 9.5"/>
        </svg>
        <h1>Baja confirmada</h1>
        <p class="suave">
          La dirección <span class="mono oro"><?= e((string) ($resultado['correo'] ?? '')) ?></span>
          ha quedado eliminada de nuestra lista.
        </p>
        <p class="pequeno suave" style="margin-top:14px">
          No volverás a recibir ningún mensaje nuestro. No hace falta que hagas nada más.
        </p>
      <?php else: ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--error)" stroke-width="1.8" stroke-linecap="round"
             style="width:56px;height:56px;margin:0 auto 18px" aria-hidden="true">
          <circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/>
        </svg>
        <h1>Enlace no válido</h1>
        <p class="suave">
          Este enlace de baja ha caducado o no es correcto. Si sigues recibiendo mensajes,
          responde a cualquiera de ellos con la palabra <b>BAJA</b> y te sacaremos de la lista a mano.
        </p>
      <?php endif; ?>
      <a class="btn" style="margin-top:22px" href="<?= e(cr_url('index.php')) ?>">Volver al inicio</a>
    </div>
  </div>
</section>
<?php cr_pie(false); ?>
