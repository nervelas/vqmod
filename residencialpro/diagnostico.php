<?php
declare(strict_types=1);

/**
 * ResidencialPro — diagnóstico de acceso.
 *
 * Archivo suelto de apoyo: se sube a la raíz de la instalación, se abre con el
 * token del cron y responde por qué no se puede entrar. También permite fijar
 * una contraseña nueva cuando ya no hay forma de recuperarla por correo.
 *
 * Uso:   https://SUDOMINIO/diagnostico.php?token=EL_TOKEN_DEL_CRON
 *
 * El token está en config/config.php, en la línea 'cron' => ['token' => '...'].
 * BORRE ESTE ARCHIVO EN CUANTO TERMINE.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$raiz    = __DIR__;
$config  = $raiz . '/config/config.php';
$errores = [];

function fila(string $etiqueta, string $valor, ?bool $ok = null): void
{
    $color = $ok === null ? '#3A464B' : ($ok ? '#47713F' : '#93251E');
    $marca = $ok === null ? '·' : ($ok ? '✓' : '✗');
    printf(
        '<tr><td style="color:%s;width:26px">%s</td><td>%s</td><td style="color:%s"><b>%s</b></td></tr>',
        $color, $marca, htmlspecialchars($etiqueta), $color, htmlspecialchars($valor)
    );
}

if (!is_file($config)) {
    http_response_code(503);
    exit('<p style="font:15px system-ui;padding:40px">No existe <code>config/config.php</code>. El sistema todavía no está instalado: abra <code>/install/</code>.</p>');
}

$cfg   = require $config;
$token = (string) ($cfg['cron']['token'] ?? '');
$dado  = (string) ($_GET['token'] ?? '');

if ($token === '' || !hash_equals($token, $dado)) {
    http_response_code(403);
    exit('<p style="font:15px system-ui;padding:40px">Token incorrecto.<br><br>'
       . 'Ábralo así: <code>diagnostico.php?token=EL_TOKEN</code><br>'
       . 'El token está en <code>config/config.php</code>, en la línea '
       . "<code>'cron' =&gt; ['token' =&gt; '...']</code>.</p>");
}

$d = $cfg['db'] ?? [];
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $d['host'] ?? '', $d['puerto'] ?? '3306', $d['nombre'] ?? ''),
        (string) ($d['usuario'] ?? ''),
        (string) ($d['clave'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (Throwable $e) {
    $pdo = null;
    $errores[] = 'No se pudo conectar con la base de datos: ' . $e->getMessage();
}

/* ---------------------------------------------- Acciones sobre una cuenta */
$aviso = '';
if ($pdo !== null && ($_GET['accion'] ?? '') === 'clave') {
    $usuario = trim((string) ($_GET['usuario'] ?? ''));
    $nueva   = (string) ($_GET['nueva'] ?? '');
    if (mb_strlen($nueva) < 10) {
        $aviso = 'La contraseña nueva debe tener al menos 10 caracteres.';
    } else {
        $hash = defined('PASSWORD_ARGON2ID')
            ? password_hash($nueva, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2])
            : password_hash($nueva, PASSWORD_DEFAULT);
        $st = $pdo->prepare('UPDATE usuarios SET password_hash = :h, activo = 1 WHERE usuario = :u OR correo = :u2');
        $st->execute(['h' => $hash, 'u' => $usuario, 'u2' => $usuario]);
        $aviso = $st->rowCount() > 0
            ? 'Contraseña actualizada para «' . $usuario . '». Ya puede entrar en /acceso.'
            : 'No existe ninguna cuenta con usuario o correo «' . $usuario . '».';
    }
}
if ($pdo !== null && ($_GET['accion'] ?? '') === 'desbloquear') {
    $pdo->exec('DELETE FROM intentos_acceso');
    $aviso = 'Se borraron los intentos fallidos: el bloqueo por reintentos quedó levantado.';
}
?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diagnóstico de acceso · ResidencialPro</title>
<style>
  body { font: 15px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; color: #12181B;
         background: #F7F4EF; margin: 0; padding: 30px 18px; }
  main { max-width: 860px; margin: 0 auto; }
  h1 { font-size: 1.5rem; margin: 0 0 4px; }
  h2 { font-size: 1.05rem; margin: 30px 0 10px; }
  .caja { background: #fff; border: 1px solid #E2D9CB; border-radius: 8px; padding: 18px 20px; margin-bottom: 18px; }
  table { width: 100%; border-collapse: collapse; font-size: .9rem; }
  td, th { padding: 7px 8px; border-bottom: 1px solid #EFE9DE; text-align: left; vertical-align: top; }
  th { font-size: .72rem; letter-spacing: .1em; text-transform: uppercase; color: #6E6A61; }
  code { background: #F1ECE3; padding: 2px 6px; border-radius: 4px; font-size: .86em; }
  .mal { background: #FAEAE7; border-color: #E4C4BF; }
  .bien { background: #EBF1E7; border-color: #CBDCC4; }
  .avisa { background: #FBF1DC; border-color: #EBDCB4; }
  input, button { font: inherit; padding: 9px 11px; border: 1px solid #D3C7B4; border-radius: 5px; }
  button { background: #B94E27; color: #fff; border-color: #B94E27; cursor: pointer; }
</style></head><body><main>

<h1>Diagnóstico de acceso</h1>
<p style="color:#6E6A61;margin-top:0">Borre este archivo del servidor cuando termine.</p>

<?php if ($aviso !== ''): ?>
  <div class="caja avisa"><b><?= htmlspecialchars($aviso) ?></b></div>
<?php endif; ?>

<?php foreach ($errores as $er): ?>
  <div class="caja mal"><b><?= htmlspecialchars($er) ?></b></div>
<?php endforeach; ?>

<h2>1. Servidor</h2>
<div class="caja"><table>
<?php
$v81 = version_compare(PHP_VERSION, '8.1.0', '>=');
fila('Versión de PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.0.0', '>='));
if (!$v81) {
    fila('Aviso', 'Con PHP 8.0 debe tener aplicado el parche de compatibilidad', false);
}
foreach (['pdo_mysql', 'mbstring', 'openssl', 'gd', 'zip', 'fileinfo'] as $ext) {
    fila('Extensión ' . $ext, extension_loaded($ext) ? 'presente' : 'FALTA', extension_loaded($ext));
}
fila('Argon2id disponible', defined('PASSWORD_ARGON2ID') ? 'sí' : 'no (se usa bcrypt)', true);
fila('Escritura en storage/', is_writable($raiz . '/storage') ? 'sí' : 'NO', is_writable($raiz . '/storage'));
fila('Escritura en uploads/', is_writable($raiz . '/uploads') ? 'sí' : 'NO', is_writable($raiz . '/uploads'));
$sesiones = $raiz . '/storage/tmp/sesiones';
fila('Carpeta de sesiones', is_dir($sesiones) ? (is_writable($sesiones) ? 'escribible' : 'SIN PERMISO') : 'se creará sola',
     !is_dir($sesiones) || is_writable($sesiones));
fila('Base de datos', $pdo !== null ? 'conectada (' . ($d['nombre'] ?? '') . ')' : 'SIN CONEXIÓN', $pdo !== null);
?>
</table></div>

<?php if ($pdo !== null): ?>
<h2>2. Cuentas que existen en su base</h2>
<?php
$us = $pdo->query('SELECT id, rol, nombre, usuario, correo, activo FROM usuarios ORDER BY FIELD(rol,"admin","junta","contabilidad","garita","residente"), id')->fetchAll(PDO::FETCH_ASSOC);
$hayDemo = false;
foreach ($us as $u) { if (str_ends_with((string) $u['correo'], '@residencial.gt')) { $hayDemo = true; break; } }
?>
<div class="caja <?= $us === [] ? 'mal' : 'bien' ?>">
  <p style="margin-top:0">
    <b><?= count($us) ?> cuenta(s)</b> en la base.
    <?= $hayDemo
      ? 'Se detectaron cuentas de demostración: las credenciales del LEEME deberían servir.'
      : '<b>No hay cuentas de demostración.</b> Usted instaló sin marcar «Cargar datos de demostración», así que las únicas credenciales válidas son las que definió en el paso 3 del instalador.' ?>
  </p>
  <table>
    <thead><tr><th></th><th>Perfil</th><th>Usuario</th><th>Correo</th><th>Estado</th></tr></thead>
    <tbody>
      <?php foreach ($us as $u): ?>
        <tr>
          <td></td>
          <td><?= htmlspecialchars((string) $u['rol']) ?></td>
          <td><code><?= htmlspecialchars((string) $u['usuario']) ?></code></td>
          <td><code><?= htmlspecialchars((string) ($u['correo'] ?? '—')) ?></code></td>
          <td><?= $u['activo'] ? 'activa' : '<b style="color:#93251E">DESACTIVADA</b>' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<h2>3. Bloqueo por intentos fallidos</h2>
<?php
$n = 0;
try { $n = (int) $pdo->query('SELECT COUNT(*) FROM intentos_acceso WHERE creado_en > DATE_SUB(NOW(), INTERVAL 15 MINUTE)')->fetchColumn(); } catch (Throwable) {}
?>
<div class="caja <?= $n >= 5 ? 'avisa' : '' ?>">
  <p style="margin:0 0 10px">
    <?= $n ?> intento(s) fallido(s) en los últimos 15 minutos.
    <?= $n >= 5 ? '<b>El acceso está bloqueado temporalmente.</b>' : '' ?>
  </p>
  <a href="?token=<?= htmlspecialchars($dado) ?>&amp;accion=desbloquear"><button type="button">Levantar el bloqueo ahora</button></a>
</div>

<h2>4. Poner una contraseña nueva</h2>
<div class="caja">
  <p style="margin-top:0">Escriba el usuario o el correo de la cuenta y la contraseña que quiere usar
     (mínimo 10 caracteres). Queda activa de inmediato.</p>
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <input type="hidden" name="token" value="<?= htmlspecialchars($dado) ?>">
    <input type="hidden" name="accion" value="clave">
    <label>Usuario o correo<br><input type="text" name="usuario" required style="min-width:230px"></label>
    <label>Contraseña nueva<br><input type="text" name="nueva" required minlength="10" style="min-width:200px"></label>
    <button type="submit">Cambiar contraseña</button>
  </form>
</div>
<?php endif; ?>

<h2>5. Últimos errores registrados</h2>
<div class="caja">
<?php
$log = $raiz . '/storage/logs/app-' . date('Y-m') . '.log';
if (is_file($log)) {
    $lineas = array_slice(file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -25);
    echo $lineas === []
        ? '<p style="margin:0">El registro está vacío: no hay errores.</p>'
        : '<pre style="margin:0;white-space:pre-wrap;font-size:.8rem;color:#3A464B">' . htmlspecialchars(implode("\n", $lineas)) . '</pre>';
} else {
    echo '<p style="margin:0">Todavía no hay archivo de registro para este mes.</p>';
}
?>
</div>

<p style="color:#93251E"><b>Cuando termine, borre <code>diagnostico.php</code> del servidor.</b></p>
</main></body></html>
