<?php
/**
 * Web installer — creates config.php, imports the schema, seeds content,
 * and creates the first administrator. Self-locks after completion.
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ROOT', dirname(__DIR__));
$configFile = ROOT . '/config/config.php';
$lockFile   = ROOT . '/config/install.lock';

session_start();

/* ---- Already installed? Block. ---- */
$alreadyInstalled = file_exists($configFile) || file_exists($lockFile);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ---- Requirements ---- */
$requirements = [
    'PHP >= 8.1'          => version_compare(PHP_VERSION, '8.1.0', '>='),
    'Extensión PDO'       => extension_loaded('pdo'),
    'PDO MySQL'           => extension_loaded('pdo_mysql'),
    'Extensión mbstring'  => extension_loaded('mbstring'),
    'Extensión fileinfo'  => extension_loaded('fileinfo'),
    'Extensión GD'        => extension_loaded('gd'),
    'config/ escribible'  => is_writable(ROOT . '/config'),
    'uploads/ escribible' => is_writable(ROOT . '/uploads'),
];
$reqOk = !in_array(false, $requirements, true);

$errors = [];
$done = false;

/* ---- Handle submission ---- */
if (!$alreadyInstalled && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // Simple CSRF for the installer
    if (($_POST['_t'] ?? '') !== ($_SESSION['it'] ?? 'x')) {
        $errors[] = 'Token inválido. Recargue la página.';
    }
    $db = [
        'host' => trim($_POST['db_host'] ?? 'localhost'),
        'port' => (int)($_POST['db_port'] ?? 3306),
        'name' => trim($_POST['db_name'] ?? ''),
        'user' => trim($_POST['db_user'] ?? ''),
        'pass' => (string)($_POST['db_pass'] ?? ''),
    ];
    $admin = [
        'name'  => trim($_POST['admin_name'] ?? ''),
        'email' => trim($_POST['admin_email'] ?? ''),
        'user'  => trim($_POST['admin_user'] ?? ''),
        'pass'  => (string)($_POST['admin_pass'] ?? ''),
    ];
    $baseUrl = trim($_POST['base_url'] ?? '');

    if (!$errors) {
        if ($db['name'] === '' || $db['user'] === '') { $errors[] = 'Complete los datos de la base de datos.'; }
        if ($admin['name'] === '' || !filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'Datos del administrador incompletos o correo inválido.'; }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $admin['user'])) { $errors[] = 'Usuario administrador inválido (3-60 caracteres).'; }
        if (strlen($admin['pass']) < 8) { $errors[] = 'La contraseña del administrador debe tener al menos 8 caracteres.'; }
    }

    if (!$errors) {
        try {
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $e) {
            $errors[] = 'No se pudo conectar a la base de datos: ' . $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            // Import schema + seed (multi-statement).
            $schema = file_get_contents(ROOT . '/database/schema.sql');
            $seed   = file_get_contents(ROOT . '/database/seed.sql');
            $pdo->exec($schema);
            $pdo->exec($seed);

            // Create the administrator.
            $stmt = $pdo->prepare('INSERT INTO admins (name, username, email, password_hash, role, is_active, created_at)
                                   VALUES (?,?,?,?,?,1,?)');
            $stmt->execute([
                $admin['name'], $admin['user'], $admin['email'],
                password_hash($admin['pass'], PASSWORD_DEFAULT), 'superadmin', date('Y-m-d H:i:s'),
            ]);

            // Write config.php.
            $appKey = bin2hex(random_bytes(24));
            $tpl = require ROOT . '/config/config.sample.php';
            $tpl['db']['host'] = $db['host'];
            $tpl['db']['port'] = $db['port'];
            $tpl['db']['name'] = $db['name'];
            $tpl['db']['user'] = $db['user'];
            $tpl['db']['pass'] = $db['pass'];
            $tpl['db']['driver'] = 'mysql';
            $tpl['app']['base_url'] = rtrim($baseUrl, '/');
            $tpl['app']['env'] = 'production';
            $tpl['security']['app_key'] = $appKey;

            $php = "<?php\n/* Generado por el instalador el " . date('Y-m-d H:i:s') . " */\nreturn " . var_export($tpl, true) . ";\n";
            if (file_put_contents($configFile, $php) === false) {
                throw new RuntimeException('No se pudo escribir config/config.php. Verifique permisos.');
            }
            @chmod($configFile, 0640);

            // Lock the installer.
            file_put_contents($lockFile, 'installed ' . date('c'));
            $done = true;
        } catch (Throwable $e) {
            $errors[] = 'Error durante la instalación: ' . $e->getMessage();
        }
    }
}

$_SESSION['it'] = $_SESSION['it'] ?? bin2hex(random_bytes(8));
$autoBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(str_replace('/install', '', dirname($_SERVER['SCRIPT_NAME'])), '/');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Instalador — Fuente de Vida</title>
<style>
:root{--p:#0f5a3c;--pd:#0b3d2a;--s:#f6a800;--line:#e1e8e4}
*{box-sizing:border-box}body{margin:0;font-family:'Segoe UI',system-ui,Arial,sans-serif;background:linear-gradient(135deg,var(--p),var(--pd));min-height:100vh;padding:2rem 1rem;color:#243b34}
.wrap{max-width:640px;margin:0 auto;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden}
.head{background:var(--pd);color:#fff;padding:1.6rem 2rem}
.head h1{margin:0;font-size:1.4rem}.head p{margin:.3rem 0 0;opacity:.85;font-size:.9rem}
.body{padding:1.8rem 2rem}
h2{color:var(--pd);font-size:1.05rem;margin:1.4rem 0 .7rem}
.req{list-style:none;padding:0;margin:0}
.req li{display:flex;justify-content:space-between;padding:.45rem 0;border-bottom:1px solid var(--line);font-size:.92rem}
.ok{color:#0d6b43;font-weight:700}.bad{color:#c0392b;font-weight:700}
label{display:block;font-weight:600;font-size:.85rem;margin:.8rem 0 .3rem}
input{width:100%;padding:.6rem .7rem;border:1px solid var(--line);border-radius:8px;font-size:.95rem;font-family:inherit}
input:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 3px rgba(15,90,60,.12)}
.row{display:grid;grid-template-columns:2fr 1fr;gap:.8rem}
.btn{display:inline-block;background:var(--p);color:#fff;border:0;border-radius:8px;padding:.8rem 1.6rem;font-weight:700;font-size:1rem;cursor:pointer;margin-top:1.4rem;width:100%}
.btn:hover{background:var(--pd)}
.alert{background:#f7dada;color:#a5281b;padding:.8rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:.9rem}
.alert--ok{background:#d4f3e2;color:#0d6b43}
.note{font-size:.82rem;color:#6b7b74;margin-top:.4rem}
code{background:#eef2f0;padding:.1rem .4rem;border-radius:4px}
.card-sec{border:1px solid var(--line);border-radius:10px;padding:1rem 1.2rem;margin-bottom:1rem}
</style>
</head>
<body>
<div class="wrap">
  <div class="head"><h1>Instalador · Fuente de Vida</h1><p>Configura tu sitio en pocos pasos.</p></div>
  <div class="body">
  <?php if ($alreadyInstalled): ?>
    <div class="alert alert--ok">El sitio ya está instalado.</div>
    <p>Por seguridad, <strong>elimina la carpeta <code>/install/</code></strong> de tu servidor.</p>
    <p><a class="btn" href="<?= h(dirname($_SERVER['SCRIPT_NAME'], 2) ?: '/') ?>">Ir al sitio</a></p>
  <?php elseif ($done): ?>
    <div class="alert alert--ok">¡Instalación completada correctamente!</div>
    <h2>Siguientes pasos</h2>
    <ol>
      <li><strong>Elimina la carpeta <code>/install/</code></strong> del servidor.</li>
      <li>Ingresa al panel de administración con el usuario que creaste.</li>
    </ol>
    <a class="btn" href="../admin/login.php">Ir al panel de administración</a>
    <p class="note">Sitio público: <a href="../">Ver sitio</a></p>
  <?php else: ?>
    <?php foreach ($errors as $er): ?><div class="alert"><?= h($er) ?></div><?php endforeach; ?>

    <h2>1. Requisitos del servidor</h2>
    <ul class="req">
      <?php foreach ($requirements as $name => $pass): ?>
        <li><span><?= h($name) ?></span><span class="<?= $pass?'ok':'bad' ?>"><?= $pass?'✓ OK':'✗ Falta' ?></span></li>
      <?php endforeach; ?>
    </ul>
    <?php if (!$reqOk): ?><div class="alert" style="margin-top:1rem">Corrige los requisitos marcados antes de continuar.</div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="_t" value="<?= h($_SESSION['it']) ?>">
      <div class="card-sec">
        <h2 style="margin-top:0">2. Base de datos MySQL</h2>
        <div class="row">
          <div><label>Servidor</label><input name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>" required></div>
          <div><label>Puerto</label><input name="db_port" value="<?= h($_POST['db_port'] ?? '3306') ?>"></div>
        </div>
        <label>Nombre de la base de datos</label><input name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" required>
        <label>Usuario MySQL</label><input name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required>
        <label>Contraseña MySQL</label><input type="password" name="db_pass">
      </div>

      <div class="card-sec">
        <h2 style="margin-top:0">3. Administrador</h2>
        <label>Nombre completo</label><input name="admin_name" value="<?= h($_POST['admin_name'] ?? '') ?>" required>
        <label>Correo electrónico</label><input type="email" name="admin_email" value="<?= h($_POST['admin_email'] ?? '') ?>" required>
        <label>Usuario</label><input name="admin_user" value="<?= h($_POST['admin_user'] ?? 'admin') ?>" required>
        <label>Contraseña (mínimo 8 caracteres)</label><input type="password" name="admin_pass" required>
      </div>

      <div class="card-sec">
        <h2 style="margin-top:0">4. URL del sitio</h2>
        <label>URL base (sin barra final)</label><input name="base_url" value="<?= h($_POST['base_url'] ?? $autoBase) ?>">
        <p class="note">Ej: <code>https://fuentedevida.edu.gt</code>. Puedes dejar la detección automática.</p>
      </div>

      <button class="btn" <?= $reqOk?'':'disabled' ?>>Instalar ahora</button>
    </form>
  <?php endif; ?>
  </div>
</div>
</body>
</html>
