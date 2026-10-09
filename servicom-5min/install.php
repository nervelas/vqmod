<?php
declare(strict_types=1);
/**
 * Instalador web de "Tu web en 5 minutos". Se auto-elimina al terminar.
 * Verifica requisitos, pide datos de BD / dominio base / subdominio del portal, crea al dueño y escribe la configuración.
 */
define('S5_ROOT', __DIR__);
if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Se requiere PHP 8.0 o superior. Versión actual: ' . PHP_VERSION);
}
require S5_ROOT . '/app/bootstrap.php';

use S5\Core\Config;
use S5\Core\Crypto;
use S5\Core\Schema;
use S5\Core\Settings;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'; img-src data:");
session_name('s5inst');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
if (empty($_SESSION['t'])) {
    $_SESSION['t'] = bin2hex(random_bytes(24));
}
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

if (Config::installed()) {
    @unlink(__FILE__);
    http_response_code(404);
    exit('El sistema ya está instalado. Este instalador se eliminó.');
}

function s5_requirements(): array
{
    $r = [];
    $r[] = ['PHP 8.0 o superior', PHP_VERSION_ID >= 80000, PHP_VERSION];
    foreach (['zip', 'dom', 'xml', 'mbstring', 'fileinfo', 'curl', 'pdo_mysql', 'sodium', 'openssl', 'json'] as $e) {
        $r[] = ['Extensión ' . $e, extension_loaded($e), ''];
    }
    $r[] = ['Extensión gd o imagick', extension_loaded('gd') || extension_loaded('imagick'), ''];
    foreach (['storage', 'storage/logs', 'storage/sessions', 'storage/uploads', 'storage/jobs', 'storage/work'] as $d) {
        $p = S5_ROOT . '/' . $d;
        @mkdir($p, 0750, true);
        $r[] = ['Escritura en ' . $d, is_dir($p) && is_writable($p), ''];
    }
    $free = @disk_free_space(S5_ROOT);
    $r[] = ['Espacio en disco (mínimo 500 MB)', $free === false ? true : $free > 500 * 1048576, $free === false ? 'no se pudo medir' : round($free / 1048576) . ' MB libres'];
    $up = (string) ini_get('upload_max_filesize');
    $r[] = ['upload_max_filesize ≥ 12M (recomendado)', true, $up];
    return $r;
}

$err = [];
$ok = false;
$reqs = s5_requirements();
$reqOk = true;
foreach ($reqs as $q) {
    if (!$q[1] && !str_contains($q[0], 'recomendado')) {
        $reqOk = false;
    }
}

$in = $_POST + [
    'db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'db_pass' => '', 'db_prefix' => 's5_',
    'dominio' => 'servicom.gt', 'portal' => 'crear', 'email' => '', 'pass' => '', 'webs' => dirname(S5_ROOT) . '/webs-clientes',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['t'], (string) ($_POST['t'] ?? ''))) {
        $err[] = 'La sesión expiró. Recargue la página.';
    }
    if (!$reqOk) {
        $err[] = 'Corrija los requisitos marcados antes de continuar.';
    }
    $dom = strtolower(trim((string) $in['dominio']));
    $sub = strtolower(trim((string) $in['portal']));
    $pref = (string) $in['db_prefix'];
    if (!preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,24}$/', $dom)) { $err[] = 'Dominio base no válido (ejemplo: servicom.gt).'; }
    if (!preg_match('/^[a-z0-9-]{1,40}$/', $sub)) { $err[] = 'Subdominio del portal no válido (ejemplo: crear).'; }
    if (!preg_match('/^[a-z0-9_]{1,12}$/i', $pref)) { $err[] = 'Prefijo de tablas no válido.'; }
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) { $err[] = 'Correo del dueño no válido.'; }
    if (strlen((string) $in['pass']) < 12) { $err[] = 'La contraseña debe tener al menos 12 caracteres.'; }
    $webs = rtrim((string) $in['webs'], '/');
    if ($webs === '' || $webs[0] !== '/' || str_contains($webs, '..')) { $err[] = 'La carpeta de webs debe ser una ruta absoluta.'; }
    if (!$err && (realpath($webs) === realpath(S5_ROOT) || str_starts_with($webs . '/', S5_ROOT . '/'))) { $err[] = 'La carpeta de webs no puede estar dentro de la carpeta del portal.'; }

    $pdo = null;
    if (!$err) {
        try {
            $pdo = new PDO('mysql:host=' . $in['db_host'] . ';dbname=' . $in['db_name'] . ';charset=utf8mb4', (string) $in['db_user'], (string) $in['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (Throwable $e) {
            $err[] = 'No se pudo conectar a la base de datos. Revise host, nombre, usuario y contraseña.';
        }
    }
    if (!$err) {
        try {
            @mkdir($webs, 0755, true);
            if (!is_dir($webs) || !is_writable($webs)) {
                throw new RuntimeException('No se puede escribir en la carpeta de webs: ' . $webs);
            }
            $key = Crypto::newKey();
            $cfg = [
                'db' => ['host' => $in['db_host'], 'name' => $in['db_name'], 'user' => $in['db_user'], 'pass' => $in['db_pass'], 'prefix' => $pref],
                'secret_key' => $key,
                'installed_at' => gmdate('c'),
            ];
            $envFile = getenv('S5_CONFIG_FILE');
            $secDir = dirname(S5_ROOT) . '/servicom-secrets';
            if ($envFile) {
                $target = $envFile;
                @mkdir(dirname($target), 0750, true);
            } elseif ((is_dir($secDir) && is_writable($secDir)) || (!file_exists($secDir) && is_writable(dirname(S5_ROOT)) && @mkdir($secDir, 0750))) {
                $target = $secDir . '/config.php';
            } else {
                $target = S5_ROOT . '/app/config.php';
            }
            $code = "<?php\n// Generado por el instalador. Contiene secretos: nunca debe ser accesible por web.\nreturn " . var_export($cfg, true) . ";\n";
            if (@file_put_contents($target, $code, LOCK_EX) === false) {
                throw new RuntimeException('No se pudo escribir la configuración en ' . $target);
            }
            @chmod($target, 0640);
            Config::override($cfg);
            \S5\Core\Db::reset();
            Schema::install($pdo, $pref);
            $pdo->prepare('INSERT INTO `' . $pref . 'users` (email, pass_hash, created_at) VALUES (?,?,?)')->execute([strtolower((string) $in['email']), password_hash((string) $in['pass'], PASSWORD_DEFAULT), gmdate('Y-m-d H:i:s')]);
            Settings::flush();
            Settings::set('dominio_base', $dom);
            Settings::set('portal_sub', $sub);
            Settings::set('owner_email', strtolower((string) $in['email']));
            Settings::set('webs_path', $webs);
            @mkdir($webs . '/_base', 0755, true);
            @file_put_contents($webs . '/.htaccess', "# Servicom: esta carpeta no se sirve directamente\nOptions -Indexes\n");
            $ok = true;
            $loc = $target;
            // auto-eliminación
            @unlink(__FILE__);
        } catch (Throwable $e) {
            $err[] = 'No se pudo completar la instalación: ' . $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Instalación · Tu web en 5 minutos</title>
<style>body{font-family:system-ui,sans-serif;background:#0d0d10;color:#f1ebdc;margin:0;padding:16px}main{max-width:640px;margin:0 auto}h1{color:#c9a45c}label{display:block;margin:.8rem 0 .2rem;font-size:.9rem;color:#a79f8b}input{width:100%;min-height:44px;padding:.5rem .7rem;border-radius:10px;border:1px solid #2b2b33;background:#101015;color:#f1ebdc;font:inherit;box-sizing:border-box}button{min-height:48px;padding:.6rem 1.2rem;border-radius:10px;border:0;background:#c9a45c;color:#16120a;font-weight:700;font-size:1rem;cursor:pointer;margin-top:1rem}.ok{color:#6fcf97}.no{color:#ff7b7b}.err{background:#2a1111;border:1px solid #7a2a2a;padding:.7rem 1rem;border-radius:10px;margin:.5rem 0}.card{background:#1a1a20;border:1px solid #2b2b33;border-radius:12px;padding:1rem;margin:1rem 0}small{color:#a79f8b}</style></head><body><main>
<h1>Instalación</h1>
<?php if ($ok): ?>
<div class="card"><p class="ok"><b>¡Listo!</b> El sistema quedó instalado y este instalador se eliminó.</p>
<p>Configuración guardada en:<br><code><?= $h($loc) ?></code></p>
<p><a href="/admin/login" style="color:#e8cf94">Entrar al panel</a>. Siguientes pasos (ver LEEME.md): configurar el hosting (token de cPanel), construir el paquete base (<code>php tools/build_base.php</code>), el cron diario y completar datos bancarios y clave de IA en Ajustes.</p></div>
<?php else: ?>
<div class="card"><b>Requisitos</b><?php foreach ($reqs as $q): ?><div><?= $q[1] ? '<span class="ok">✔</span>' : '<span class="no">✖</span>' ?> <?= $h($q[0]) ?> <small><?= $h($q[2]) ?></small></div><?php endforeach; ?></div>
<?php foreach ($err as $e): ?><div class="err" role="alert"><?= $h($e) ?></div><?php endforeach; ?>
<form method="post" autocomplete="off"><input type="hidden" name="t" value="<?= $h($_SESSION['t']) ?>">
<div class="card"><b>Base de datos MySQL/MariaDB</b>
<label>Servidor</label><input name="db_host" value="<?= $h($in['db_host']) ?>" required>
<label>Nombre de la base</label><input name="db_name" value="<?= $h($in['db_name']) ?>" required>
<label>Usuario</label><input name="db_user" value="<?= $h($in['db_user']) ?>" required>
<label>Contraseña</label><input type="password" name="db_pass" value="" autocomplete="new-password">
<label>Prefijo de tablas</label><input name="db_prefix" value="<?= $h($in['db_prefix']) ?>" required></div>
<div class="card"><b>Dominios</b>
<label>Dominio base de las webs de clientes</label><input name="dominio" value="<?= $h($in['dominio']) ?>" required><small>Cada web vivirá en slug.<i>este dominio</i>. Nunca se tocan sus archivos, DNS ni correos.</small>
<label>Subdominio del portal</label><input name="portal" value="<?= $h($in['portal']) ?>" required>
<label>Carpeta de las webs de clientes (distinta de sus demás páginas)</label><input name="webs" value="<?= $h($in['webs']) ?>" required></div>
<div class="card"><b>Dueño del sistema</b>
<label>Correo</label><input type="email" name="email" value="<?= $h($in['email']) ?>" required>
<label>Contraseña (mínimo 12 caracteres)</label><input type="password" name="pass" minlength="12" required autocomplete="new-password"></div>
<button type="submit"<?= $reqOk ? '' : ' disabled' ?>>Instalar</button></form>
<?php endif; ?>
</main></body></html>
