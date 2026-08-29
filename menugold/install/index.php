<?php
/**
 * MenúGold · asistente de instalación en 3 pasos.
 * Se bloquea solo al terminar (crea install.lock).
 */
declare(strict_types=1);

define('MG_ROOT', dirname(__DIR__));
define('MG_APP', MG_ROOT . '/app');
define('MG_STORAGE', MG_ROOT . '/storage');
define('MG_VERSION', '1.0.0');

require MG_APP . '/Core/Autoloader.php';
Autoloader::register();
Autoloader::addNamespace('MenuGold\\Core',   MG_APP . '/Core');
Autoloader::addNamespace('MenuGold\\Models', MG_APP . '/Models');

use MenuGold\Core\Security;

$configFile = MG_ROOT . '/config/config.php';
$lockFile   = __DIR__ . '/install.lock';
$installed  = is_file($configFile);
$locked     = is_file($lockFile);

$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
if ($basePath === '.') { $basePath = ''; }
$selfUrl = $basePath . '/install/';

session_name('mginstall');
@session_start();

// Ya instalado: solo se deja ver la pantalla final de «listo», y únicamente a
// quien acaba de terminar la instalación en esta misma sesión.
$recienInstalado = isset($_GET['listo']) && !empty($_SESSION['done']);
if ($locked && $installed && !$recienInstalado) {
    header('Location: ' . ($basePath === '' ? '/' : $basePath . '/'));
    exit;
}

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }

$step   = isset($_GET['paso']) ? max(1, min(3, (int)$_GET['paso'])) : 1;
$errors = array();
$notice = '';

/* ---------------- Comprobaciones ---------------- */
function mg_checks()
{
    $writable = function ($p) { return is_dir($p) ? is_writable($p) : @mkdir($p, 0755, true); };
    return array(
        array('PHP 8.0 o superior', PHP_VERSION_ID >= 80000, 'Tienes ' . PHP_VERSION . '. Cámbialo en cPanel → Select PHP Version.', true),
        array('Extensión PDO MySQL', extension_loaded('pdo_mysql'), 'Actívala en cPanel → Select PHP Version → Extensions.', true),
        array('Extensión GD (imágenes)', extension_loaded('gd'), 'Necesaria para comprimir fotos y generar iconos.', true),
        array('Soporte WebP', function_exists('imagewebp'), 'Sin WebP las fotos se sirven solo en JPG (algo más pesadas).', false),
        array('Extensión mbstring', extension_loaded('mbstring'), 'Necesaria para acentos y textos en dos idiomas.', true),
        array('Extensión ZIP', class_exists('ZipArchive'), 'Necesaria para importar y exportar Excel.', false),
        array('Extensión OpenSSL', extension_loaded('openssl'), 'Recomendada para el envío de correo cifrado.', false),
        array('Argon2id disponible', defined('PASSWORD_ARGON2ID'), 'Si falta, las contraseñas usarán bcrypt (también seguro).', false),
        array('/config con escritura', $writable(MG_ROOT . '/config'), 'chmod 755 a la carpeta config.', true),
        array('/storage con escritura', $writable(MG_STORAGE), 'chmod 755 a la carpeta storage.', true),
        array('/uploads con escritura', $writable(MG_ROOT . '/uploads'), 'chmod 755 a la carpeta uploads.', true),
    );
}

$checks = mg_checks();
$blocking = false;
foreach ($checks as $c) { if ($c[3] && !$c[1]) { $blocking = true; } }

/* ---------------- Procesamiento ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || !hash_equals($_SESSION['csrf'], (string)$_POST['_token'])) {
        $errors[] = 'La sesión expiró. Vuelve a enviar el formulario.';
        $step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
    } else {
        $step = isset($_POST['step']) ? (int)$_POST['step'] : 1;

        if ($step === 2) {
            $db = array(
                'host' => trim((string)($_POST['db_host'] ?? 'localhost')),
                'port' => (int)($_POST['db_port'] ?? 3306),
                'name' => trim((string)($_POST['db_name'] ?? '')),
                'user' => trim((string)($_POST['db_user'] ?? '')),
                'pass' => (string)($_POST['db_pass'] ?? ''),
            );
            if ($db['name'] === '' || $db['user'] === '') {
                $errors[] = 'Escribe el nombre de la base de datos y su usuario.';
            } else {
                try {
                    $pdo = new PDO(
                        'mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['name'] . ';charset=utf8mb4',
                        $db['user'], $db['pass'],
                        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
                    );
                    $_SESSION['db'] = $db;
                    header('Location: ' . $selfUrl . '?paso=3');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'No se pudo conectar: ' . $e->getMessage();
                }
            }
        }

        if ($step === 3) {
            $db = isset($_SESSION['db']) ? $_SESSION['db'] : null;
            if (!$db) {
                $errors[] = 'Faltan los datos de la base de datos.';
                $step = 2;
            } else {
                $adminName  = trim((string)($_POST['admin_name'] ?? 'Administrador'));
                $adminEmail = trim((string)($_POST['admin_email'] ?? ''));
                $adminUser  = preg_replace('/[^A-Za-z0-9._@\-]/', '', (string)($_POST['admin_user'] ?? ''));
                $adminPass  = (string)($_POST['admin_pass'] ?? '');
                $withDemo   = !empty($_POST['demo']);

                if ($adminUser === '')                          { $errors[] = 'Escribe el usuario del administrador.'; }
                if (strlen($adminPass) < 8)                     { $errors[] = 'La contraseña debe tener al menos 8 caracteres.'; }
                if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Escribe un correo válido.'; }

                if (!$errors) {
                    try {
                        $pdo = new PDO(
                            'mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['name'] . ';charset=utf8mb4',
                            $db['user'], $db['pass'],
                            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
                        );
                        $pdo->exec('SET NAMES utf8mb4');

                        mg_run_sql($pdo, MG_ROOT . '/database/schema.sql');
                        mg_run_sql($pdo, MG_ROOT . '/database/seed.sql');
                        if ($withDemo && is_file(MG_ROOT . '/database/database_demo.sql')) {
                            mg_run_sql($pdo, MG_ROOT . '/database/database_demo.sql');
                        }

                        // Superadministrador
                        $hash = Security::hashPassword($adminPass);
                        $st = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
                        $st->execute(array($adminUser));
                        if ($st->fetchColumn()) {
                            $up = $pdo->prepare("UPDATE users SET password_hash = ?, email = ?, name = ?, role = 'superadmin', restaurant_id = NULL, is_active = 1 WHERE username = ?");
                            $up->execute(array($hash, $adminEmail, $adminName, $adminUser));
                        } else {
                            $in = $pdo->prepare("INSERT INTO users (restaurant_id, role, name, username, email, password_hash, is_active, created_at)
                                                 VALUES (NULL, 'superadmin', ?, ?, ?, ?, 1, NOW())");
                            $in->execute(array($adminName, $adminUser, $adminEmail, $hash));
                        }

                        // config/config.php
                        $config = array(
                            'app' => array(
                                'name' => 'MenúGold', 'url' => '', 'debug' => false,
                                'locale' => 'es', 'timezone' => 'America/Guatemala',
                            ),
                            'db' => $db,
                            'security' => array(
                                'app_key'     => bin2hex(random_bytes(32)),
                                'cron_token'  => bin2hex(random_bytes(16)),
                                'session_ttl' => 7200,
                            ),
                            'mail' => array('host' => '', 'port' => 587, 'user' => '', 'pass' => '', 'secure' => 'tls', 'from' => '', 'from_name' => 'MenúGold'),
                            'uploads' => array('max_bytes' => 8388608),
                        );
                        $php = "<?php\n// Generado por el instalador de MenúGold el " . date('Y-m-d H:i') . ".\n// No compartas este archivo: contiene credenciales.\nreturn " . mg_export($config) . ";\n";
                        if (@file_put_contents($configFile, $php) === false) {
                            throw new RuntimeException('No se pudo escribir config/config.php. Da permiso de escritura a la carpeta /config.');
                        }
                        @chmod($configFile, 0640);

                        @file_put_contents($lockFile, date('c'));
                        $_SESSION['done'] = array('user' => $adminUser, 'cron' => $config['security']['cron_token']);
                        header('Location: ' . $selfUrl . '?paso=3&listo=1');
                        exit;
                    } catch (Throwable $e) {
                        $errors[] = $e->getMessage();
                    }
                }
            }
        }
    }
}

/** Ejecuta un archivo .sql respetando comillas y comentarios. */
function mg_run_sql(PDO $pdo, $file)
{
    if (!is_file($file)) { return; }
    $sql = (string)file_get_contents($file);
    $statements = mg_split_sql($sql);
    foreach ($statements as $s) {
        $s = trim($s);
        if ($s === '') { continue; }
        $pdo->exec($s);
    }
}

function mg_split_sql($sql)
{
    $out = array();
    $buffer = '';
    $inString = false;
    $quote = '';
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        if ($inString) {
            $buffer .= $ch;
            if ($ch === '\\') { $i++; if ($i < $len) { $buffer .= $sql[$i]; } continue; }
            if ($ch === $quote) { $inString = false; }
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') { $inString = true; $quote = $ch; $buffer .= $ch; continue; }
        if ($ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
            while ($i < $len && $sql[$i] !== "\n") { $i++; }
            continue;
        }
        if ($ch === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
            $i += 2;
            while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) { $i++; }
            $i++;
            continue;
        }
        if ($ch === ';') { $out[] = $buffer; $buffer = ''; continue; }
        $buffer .= $ch;
    }
    if (trim($buffer) !== '') { $out[] = $buffer; }
    return $out;
}

/** var_export legible, con arreglos cortos. */
function mg_export($value, $indent = 1)
{
    if (is_array($value)) {
        $pad = str_repeat('    ', $indent);
        $out = "array(\n";
        foreach ($value as $k => $v) {
            $out .= $pad . (is_int($k) ? '' : "'" . addslashes((string)$k) . "' => ") . mg_export($v, $indent + 1) . ",\n";
        }
        return $out . str_repeat('    ', $indent - 1) . ')';
    }
    if (is_bool($value))  { return $value ? 'true' : 'false'; }
    if (is_null($value))  { return 'null'; }
    if (is_int($value) || is_float($value)) { return (string)$value; }
    return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string)$value) . "'";
}

$done = isset($_GET['listo']) && !empty($_SESSION['done']) ? $_SESSION['done'] : null;
$token = $_SESSION['csrf'];
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalar MenúGold</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES) ?>/assets/css/fonts.css">
<link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES) ?>/assets/css/core.css">
<link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES) ?>/assets/css/panel.css">
</head>
<body class="panel-body">
<div class="grain" aria-hidden="true"></div>

<main style="min-height:100svh;display:grid;place-items:center;padding:2rem 1rem">
  <div style="width:min(100%,780px)">
    <p class="eyebrow" style="margin-bottom:1.2rem">Instalación</p>
    <h1 class="display" style="font-size:var(--step-3);margin-bottom:.6rem">MenúGold</h1>
    <p class="muted" style="margin-bottom:2rem">Tres pasos y tu menú digital queda funcionando.</p>

    <div class="steps-bar" aria-hidden="true">
      <?php for ($i = 1; $i <= 3; $i++): ?><i class="<?= $i <= $step ? 'is-done' : '' ?>"></i><?php endfor; ?>
    </div>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error" role="alert"><span><?= htmlspecialchars($err, ENT_QUOTES) ?></span></div>
    <?php endforeach; ?>

    <?php if ($done): ?>
      <div class="card">
        <div class="card-head"><h2 class="display" style="font-size:var(--step-2)">Listo</h2></div>
        <div class="alert alert-success"><span>MenúGold quedó instalado. Ya puedes entrar a tu panel.</span></div>
        <p class="muted" style="font-size:var(--step--1)">Usuario del superadministrador: <b><?= htmlspecialchars($done['user'], ENT_QUOTES) ?></b></p>

        <?php
        $hayRed = false;
        try { $hayRed = \MenuGold\Core\PhotoFetcher::hayInternet(); } catch (Throwable $e) {}
        ?>
        <div class="card" style="border-color:var(--gold);background:rgba(216,178,110,.06);margin-top:1.4rem">
          <h3 style="font-family:var(--font-display);font-weight:400;font-size:1.15rem">Siguiente: las fotografías</h3>
          <p class="muted" style="font-size:.86rem;margin-top:.4rem">
            El menú se instaló sin fotos a propósito: se descargan reales, desde bancos con licencia libre,
            en cuanto entres al panel. Ve a <b>Menú → Fotografía</b> y pulsa «Descargar fotografías».
          </p>
          <p style="font-size:.8rem;margin-top:.6rem;color:<?= $hayRed ? '#BFE6CD' : '#F2C3B2' ?>">
            <?= $hayRed
              ? '✓ Este servidor sí tiene salida a internet: la descarga funcionará.'
              : '⚠ No se detectó salida a internet desde este servidor. Pide a tu hosting que permita conexiones salientes HTTPS, o sube tus propias fotos desde el panel.' ?>
          </p>
        </div>

        <p class="label mt-3">Tarea programada (cPanel → Cron Jobs, cada 10 minutos)</p>
        <div class="copy-box">
          <pre>*/10 * * * * curl -s "<?= htmlspecialchars(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/cron/run.php?token=' . $done['cron'], ENT_QUOTES) ?>"</pre>
        </div>

        <div class="alert mt-3"><span><b>Último paso obligatorio:</b> borra la carpeta <code>/install</code> de tu hosting.</span></div>
        <a class="btn btn-block mt-2" href="<?= htmlspecialchars($basePath . '/panel/entrar', ENT_QUOTES) ?>">Entrar al panel</a>
      </div>

    <?php elseif ($step === 1): ?>
      <div class="card">
        <div class="card-head"><h2 class="display" style="font-size:var(--step-2)">1 · Revisión del servidor</h2></div>
        <ul class="stack" style="gap:.55rem">
          <?php foreach ($checks as $c): ?>
            <li class="row-between" style="font-size:var(--step--1);padding:.4rem 0;border-bottom:1px solid var(--line-soft)">
              <span><?= htmlspecialchars($c[0], ENT_QUOTES) ?>
                <?php if (!$c[1]): ?><span class="faint" style="display:block;font-size:11.5px"><?= htmlspecialchars($c[2], ENT_QUOTES) ?></span><?php endif; ?>
              </span>
              <span class="chip <?= $c[1] ? 'chip-green' : ($c[3] ? 'chip-ember' : 'chip-dim') ?>">
                <?= $c[1] ? 'Bien' : ($c[3] ? 'Falta' : 'Opcional') ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($blocking): ?>
          <div class="alert alert-error mt-2"><span>Corrige los requisitos marcados como «Falta» y recarga esta página.</span></div>
          <a class="btn btn-ghost btn-block mt-2" href="<?= htmlspecialchars($selfUrl, ENT_QUOTES) ?>">Volver a revisar</a>
        <?php else: ?>
          <a class="btn btn-block mt-2" href="<?= htmlspecialchars($selfUrl . '?paso=2', ENT_QUOTES) ?>">Continuar</a>
        <?php endif; ?>
      </div>

    <?php elseif ($step === 2): ?>
      <form class="card" method="post" action="<?= htmlspecialchars($selfUrl . '?paso=2', ENT_QUOTES) ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <input type="hidden" name="step" value="2">
        <div class="card-head"><h2 class="display" style="font-size:var(--step-2)">2 · Base de datos</h2>
          <p>Créala antes en cPanel → MySQL Databases y anota usuario y contraseña.</p></div>
        <div class="grid grid-2">
          <div class="field"><label for="db_host">Servidor</label>
            <input class="input" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost', ENT_QUOTES) ?>"></div>
          <div class="field"><label for="db_port">Puerto</label>
            <input class="input" id="db_port" name="db_port" type="number" value="<?= htmlspecialchars((string)($_POST['db_port'] ?? 3306), ENT_QUOTES) ?>"></div>
        </div>
        <div class="field"><label for="db_name">Nombre de la base *</label>
          <input class="input" id="db_name" name="db_name" required value="<?= htmlspecialchars($_POST['db_name'] ?? '', ENT_QUOTES) ?>" placeholder="usuario_menugold"></div>
        <div class="grid grid-2">
          <div class="field"><label for="db_user">Usuario *</label>
            <input class="input" id="db_user" name="db_user" required value="<?= htmlspecialchars($_POST['db_user'] ?? '', ENT_QUOTES) ?>" autocomplete="off"></div>
          <div class="field"><label for="db_pass">Contraseña</label>
            <input class="input" id="db_pass" name="db_pass" type="password" autocomplete="off"></div>
        </div>
        <button class="btn btn-block" type="submit">Probar conexión y continuar</button>
      </form>

    <?php else: ?>
      <form class="card" method="post" action="<?= htmlspecialchars($selfUrl . '?paso=3', ENT_QUOTES) ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <input type="hidden" name="step" value="3">
        <div class="card-head"><h2 class="display" style="font-size:var(--step-2)">3 · Tu cuenta</h2>
          <p>Serás el administrador de la plataforma: creas restaurantes, planes y editas el sitio de venta.</p></div>
        <div class="grid grid-2">
          <div class="field"><label for="admin_name">Nombre</label>
            <input class="input" id="admin_name" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? 'Administrador', ENT_QUOTES) ?>"></div>
          <div class="field"><label for="admin_email">Correo *</label>
            <input class="input" id="admin_email" name="admin_email" type="email" required value="<?= htmlspecialchars($_POST['admin_email'] ?? '', ENT_QUOTES) ?>"></div>
          <div class="field"><label for="admin_user">Usuario *</label>
            <input class="input" id="admin_user" name="admin_user" required autocomplete="off" value="<?= htmlspecialchars($_POST['admin_user'] ?? '', ENT_QUOTES) ?>"></div>
          <div class="field"><label for="admin_pass">Contraseña *</label>
            <input class="input" id="admin_pass" name="admin_pass" type="password" minlength="8" required autocomplete="new-password"></div>
        </div>
        <label class="switch"><input type="checkbox" name="demo" value="1" checked>
          <span class="switch-track" aria-hidden="true"></span>
          <span>Instalar los datos de demostración (Brasa Negra y Café Central)</span></label>
        <p class="field-hint">Recomendado la primera vez: te deja un menú completo para ver cómo se ve todo.</p>
        <button class="btn btn-block mt-2" type="submit">Instalar</button>
      </form>
    <?php endif; ?>

    <p class="faint" style="text-align:center;margin-top:2rem;font-size:12px">MenúGold <?= MG_VERSION ?></p>
  </div>
</main>
</body>
</html>
