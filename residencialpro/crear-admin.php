<?php
declare(strict_types=1);

/**
 * ResidencialPro — alta del administrador, de una sola pasada.
 *
 * Se abre una vez en el navegador y deja lista la cuenta de administración:
 * la crea si no existe, le fija la contraseña si ya estaba, la reactiva y
 * levanta el bloqueo por intentos fallidos. Al terminar se borra solo, para
 * no dejar en el servidor un archivo capaz de crear administradores.
 *
 * Uso:  https://SUDOMINIO/crear-admin.php
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

const CORREO = 'info@servicom.gt';
const CLAVE  = 'Servicom*2026*';
const NOMBRE = 'Servicom';

$raiz   = __DIR__;
$config = $raiz . '/config/config.php';
$pasos  = [];
$fallo  = null;
$borrado = false;

function paso(array &$p, string $texto, bool $ok = true): void { $p[] = [$ok, $texto]; }

if (!is_file($config)) {
    $fallo = 'No existe config/config.php: el sistema todavía no está instalado. '
           . 'Abra /install/ primero y vuelva aquí después.';
} else {
    $cfg = require $config;
    $d   = $cfg['db'] ?? [];
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $d['host'] ?? 'localhost', $d['puerto'] ?? '3306', $d['nombre'] ?? ''),
            (string) ($d['usuario'] ?? ''), (string) ($d['clave'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );
        paso($pasos, 'Conectado a la base «' . ($d['nombre'] ?? '') . '».');

        // El hash se calcula con lo que este PHP sepa verificar después. En un
        // hosting sin Argon2, password_verify() rechaza un hash $argon2id$
        // aunque la contraseña sea la correcta.
        $hash = defined('PASSWORD_ARGON2ID')
            ? password_hash(CLAVE, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2])
            : password_hash(CLAVE, PASSWORD_DEFAULT);
        paso($pasos, 'Contraseña cifrada con ' . (defined('PASSWORD_ARGON2ID') ? 'Argon2id' : 'bcrypt') . '.');

        $login = strstr(CORREO, '@', true) ?: 'admin';

        $st = $pdo->prepare('UPDATE usuarios SET password_hash = :h, rol = "admin", activo = 1,
                                    nombre = :n, correo = :c
                              WHERE correo = :c2 OR usuario = :u');
        $st->execute(['h' => $hash, 'n' => NOMBRE, 'c' => CORREO, 'c2' => CORREO, 'u' => $login]);

        if ($st->rowCount() > 0) {
            paso($pasos, 'La cuenta ya existía: se le fijó la contraseña nueva y quedó activa como administradora.');
        } else {
            $ins = $pdo->prepare('INSERT INTO usuarios (rol, nombre, usuario, correo, password_hash, activo, onboarding)
                                  VALUES ("admin", :n, :u, :c, :h, 1, 1)');
            $ins->execute(['n' => NOMBRE, 'u' => $login, 'c' => CORREO, 'h' => $hash]);
            paso($pasos, 'La cuenta no existía: se creó como administradora.');
        }

        try {
            $n = $pdo->exec('DELETE FROM intentos_acceso');
            paso($pasos, 'Bloqueo por intentos fallidos levantado (' . (int) $n . ' intento(s) borrado(s)).');
        } catch (Throwable) {
            paso($pasos, 'No hizo falta levantar ningún bloqueo.');
        }

        $u = $pdo->prepare('SELECT usuario, correo FROM usuarios WHERE correo = :c');
        $u->execute(['c' => CORREO]);
        $fila = $u->fetch(PDO::FETCH_ASSOC);
        paso($pasos, 'Comprobado en la base: usuario «' . ($fila['usuario'] ?? '?') . '», correo «' . ($fila['correo'] ?? '?') . '».');

        $borrado = @unlink(__FILE__);
        paso($pasos, $borrado
            ? 'Este archivo se borró solo del servidor.'
            : 'No se pudo borrar este archivo solo: bórrelo a mano desde el Administrador de archivos.', $borrado);
    } catch (Throwable $e) {
        $fallo = 'No se pudo completar: ' . $e->getMessage();
    }
}
?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Alta del administrador · ResidencialPro</title>
<style>
  body { font: 16px/1.65 system-ui, -apple-system, "Segoe UI", sans-serif; color: #12181B;
         background: #F7F4EF; margin: 0; padding: 28px 18px; }
  main { max-width: 620px; margin: 0 auto; }
  h1 { font-size: 1.4rem; margin: 0 0 18px; }
  .caja { background: #fff; border: 1px solid #E2D9CB; border-radius: 10px; padding: 20px 22px; margin-bottom: 16px; }
  .mal { background: #FAEAE7; border-color: #E4C4BF; }
  .bien { background: #EBF1E7; border-color: #CBDCC4; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { padding: 7px 0 7px 28px; position: relative; border-bottom: 1px solid #F0EBE2; }
  li:last-child { border-bottom: 0; }
  li::before { content: "✓"; position: absolute; left: 4px; color: #47713F; font-weight: 700; }
  li.x::before { content: "✗"; color: #93251E; }
  code { background: #F1ECE3; padding: 3px 8px; border-radius: 5px; font-size: .95em; }
  .btn { display: inline-block; margin-top: 6px; padding: 13px 22px; border-radius: 7px;
         background: #B94E27; color: #fff; text-decoration: none; font-weight: 600; }
  .dato { display: flex; justify-content: space-between; gap: 14px; padding: 9px 0; border-bottom: 1px solid #F0EBE2; }
  .dato:last-child { border-bottom: 0; }
</style></head><body><main>

<?php if ($fallo !== null): ?>
  <h1>No se pudo completar</h1>
  <div class="caja mal"><b><?= htmlspecialchars($fallo) ?></b></div>
<?php else: ?>
  <h1>Listo. Ya puede entrar.</h1>
  <div class="caja bien">
    <ul>
      <?php foreach ($pasos as [$ok, $t]): ?>
        <li class="<?= $ok ? '' : 'x' ?>"><?= htmlspecialchars($t) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="caja">
    <div class="dato"><span>Usuario</span><code><?= htmlspecialchars(CORREO) ?></code></div>
    <div class="dato"><span>o también</span><code><?= htmlspecialchars(strstr(CORREO, '@', true) ?: '') ?></code></div>
    <div class="dato"><span>Contraseña</span><code><?= htmlspecialchars(CLAVE) ?></code></div>
  </div>
  <p><a class="btn" href="acceso">Entrar al sistema</a></p>
  <?php if (!$borrado): ?>
    <div class="caja mal"><b>Borre <code>crear-admin.php</code></b> desde el Administrador de archivos de cPanel:
      mientras esté ahí, cualquiera que abra esa dirección reinicia esta cuenta.</div>
  <?php endif; ?>
<?php endif; ?>

</main></body></html>
