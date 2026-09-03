<?php
/**
 * MenúGold · instalación en una sola pantalla.
 *
 * Solo pide los datos de la base de datos y la cuenta del dueño. Crea sus
 * tablas con prefijo mg_, así que puede convivir con lo que ya haya en esa
 * misma base sin tocarlo.
 */
declare(strict_types=1);

define('MG_ROOT', dirname(__DIR__));
define('MG_APP', MG_ROOT . '/app');
define('MG_STORAGE', MG_ROOT . '/storage');
define('MG_VERSION', '1.0.0');

require MG_APP . '/Core/Autoloader.php';
Autoloader::register();
Autoloader::addNamespace('MenuGold\\Core', MG_APP . '/Core');

/**
 * ¿Hay una instalación COMPLETA? Config + tablas.
 *
 * Antes bastaba con que existiera config/config.php, y eso era una trampa: si
 * el archivo estaba pero las tablas no (una base distinta, un restore a
 * medias), la aplicación reventaba con un 500 y desde aquí se redirigía al
 * panel, así que no quedaba ninguna forma de arreglarlo. Ahora, si falta algo,
 * esta pantalla sigue accesible en modo reparación.
 *
 * @return array{config:bool, conecta:bool, faltan:array|null, completa:bool}
 */
function mg_estado_instalacion()
{
    $cfgFile = MG_ROOT . '/config/config.php';
    $estado = array('config' => is_file($cfgFile), 'conecta' => false, 'faltan' => null, 'completa' => false);
    if (!$estado['config']) { return $estado; }
    $cfg = @include $cfgFile;
    if (!is_array($cfg) || empty($cfg['db']['name'])) { return $estado; }
    try {
        $db = $cfg['db'];
        $dsn = 'mysql:host=' . $db['host'] . ';port=' . (int)$db['port'] . ';dbname=' . $db['name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $db['user'], $db['pass'], array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5,
        ));
    } catch (Throwable $e) {
        return $estado;
    }
    $estado['conecta'] = true;
    $estado['faltan'] = MenuGold\Core\Schema::faltantes($pdo);
    $estado['completa'] = is_array($estado['faltan']) && count($estado['faltan']) === 0;
    $estado['cfg'] = $cfg;
    $estado['pdo'] = $pdo;
    return $estado;
}

$estadoPrevio = mg_estado_instalacion();

/** Tablas completas Y una cuenta de dueño con la que entrar. */
function mg_es_usable(array $estado)
{
    if (empty($estado['completa']) || empty($estado['pdo'])) { return false; }
    try {
        return (int)$estado['pdo']
            ->query("SELECT COUNT(*) FROM mg_users WHERE role = 'owner' AND is_active = 1")
            ->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

// Solo se cierra la puerta cuando la instalación está entera y se puede entrar
// a ella. Y solo en GET: cortar un POST aquí abortaría la propia instalación a
// mitad, que es justo el fallo que tenía esto.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['listo']) && mg_es_usable($estadoPrevio)) {
    header('Location: ../panel/entrar');
    exit;
}

// Modo reparación: la configuración sirve y la base conecta, solo faltan tablas.
// Si las tablas están pero no hay dueño, se sigue al alta normal de abajo.
$reparar = !isset($_GET['listo']) && $estadoPrevio['config']
        && $estadoPrevio['conecta'] && !$estadoPrevio['completa'];

session_name('mginstall');
session_start();
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
if ($basePath === '.' || $basePath === '/') { $basePath = ''; }

$errors = array();
$done   = isset($_GET['listo']) && !empty($_SESSION['done']) ? $_SESSION['done'] : null;

/* ---------------- Comprobación del servidor ---------------- */
function mg_checks()
{
    return array(
        array('PHP 8.0 o superior', version_compare(PHP_VERSION, '8.0.0', '>='), 'Tienes ' . PHP_VERSION . '.', true),
        array('Extensión pdo_mysql', extension_loaded('pdo_mysql'), 'Necesaria para hablar con MySQL.', true),
        array('Extensión mbstring',  extension_loaded('mbstring'),  'Para acentos y textos en español.', true),
        array('Extensión GD',        extension_loaded('gd'),        'Para comprimir fotos y generar códigos QR.', true),
        array('Extensión zip',       extension_loaded('zip'),       'Para exportar a Excel.', true),
        array('Soporte WebP',        function_exists('imagewebp'),  'Sin WebP las fotos pesan algo más.', false),
        array('Carpeta /config escribible',  is_writable(MG_ROOT . '/config'),  'chmod 755 a la carpeta config.', true),
        array('Carpeta /storage escribible', is_writable(MG_STORAGE),           'chmod 755 a la carpeta storage.', true),
        array('Carpeta /uploads escribible', is_writable(MG_ROOT . '/uploads'), 'chmod 755 a la carpeta uploads.', true),
    );
}

$checks = mg_checks();
$blocking = false;
foreach ($checks as $c) { if ($c[3] && !$c[1]) { $blocking = true; } }

/* ---------------- Reparar: solo crear las tablas que faltan ---------------- */
$reparado = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$done
    && ($_POST['accion'] ?? '') === 'reparar' && $reparar) {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['_token'] ?? ''))) {
        $errors[] = 'La sesión expiró. Recarga la página e inténtalo otra vez.';
    } else {
        try {
            // schema.sql usa CREATE TABLE IF NOT EXISTS: crear lo que falta
            // no toca ni una fila de lo que ya hubiera en esa base.
            mg_run_sql($estadoPrevio['pdo'], MG_ROOT . '/database/schema.sql');
            $estadoPrevio = mg_estado_instalacion();
            $reparar = !$estadoPrevio['completa'];
            if ($estadoPrevio['completa']) {
                if (mg_es_usable($estadoPrevio)) {
                    // Todo en su sitio: de vuelta al panel, sin tocar nada más.
                    header('Location: ../panel/entrar');
                    exit;
                }
                // Tablas listas pero sin dueño: sigue el alta normal de abajo.
                $reparado = true;
            }
        } catch (Throwable $e) {
            $errors[] = 'No se pudieron crear las tablas: ' . $e->getMessage();
        }
    }
}

/* ---------------- Guardar ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$done && ($_POST['accion'] ?? '') !== 'reparar') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['_token'] ?? ''))) {
        $errors[] = 'La sesión expiró. Recarga la página e inténtalo otra vez.';
    } elseif ($blocking) {
        $errors[] = 'Faltan requisitos del servidor. Corrígelos y recarga.';
    } else {
        $host = trim((string)($_POST['db_host'] ?? 'localhost'));
        $port = (int)($_POST['db_port'] ?? 3306);
        $name = trim((string)($_POST['db_name'] ?? ''));
        $user = trim((string)($_POST['db_user'] ?? ''));
        $pass = (string)($_POST['db_pass'] ?? '');

        $adminName  = trim((string)($_POST['admin_name'] ?? ''));
        $adminUser  = trim((string)($_POST['admin_user'] ?? ''));
        $adminEmail = trim((string)($_POST['admin_email'] ?? ''));
        $adminPass  = (string)($_POST['admin_pass'] ?? '');
        $restName   = trim((string)($_POST['rest_name'] ?? ''));
        $withDemo   = !empty($_POST['demo']);

        if ($name === '' || $user === '') { $errors[] = 'Escribe el nombre y el usuario de la base de datos.'; }
        if ($restName === '')   { $errors[] = 'Escribe el nombre de tu restaurante.'; }
        if ($adminName === '')  { $errors[] = 'Escribe tu nombre.'; }
        if ($adminUser === '')  { $errors[] = 'Escribe un usuario para entrar al panel.'; }
        if (strlen($adminPass) < 8) { $errors[] = 'La contraseña debe tener al menos 8 caracteres.'; }
        if ($adminEmail !== '' && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Ese correo no es válido.'; }

        $pdo = null;
        if (!$errors) {
            try {
                $pdo = new PDO(
                    'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4',
                    $user, $pass,
                    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false)
                );
            } catch (Throwable $e) {
                $errors[] = 'No se pudo conectar a la base de datos: ' . $e->getMessage();
            }
        }

        if (!$errors && $pdo) {
            try {
                mg_run_sql($pdo, MG_ROOT . '/database/schema.sql');
                if ($withDemo && is_file(MG_ROOT . '/database/demo.sql')) {
                    mg_run_sql($pdo, MG_ROOT . '/database/demo.sql');
                }

                // Horario por omisión si la demo no lo trajo.
                $tieneHoras = (int)$pdo->query('SELECT COUNT(*) FROM mg_hours')->fetchColumn();
                if ($tieneHoras === 0) {
                    for ($d = 0; $d <= 6; $d++) {
                        $st = $pdo->prepare('INSERT INTO mg_hours (weekday, opens_at, closes_at, is_closed) VALUES (?,?,?,0)');
                        $st->execute(array($d, '12:00:00', '22:00:00'));
                    }
                }

                // Cuenta del dueño: si el usuario ya existe, se actualiza.
                $hash = password_hash($adminPass, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
                $st = $pdo->prepare('SELECT id FROM mg_users WHERE username = ? LIMIT 1');
                $st->execute(array($adminUser));
                $existing = $st->fetchColumn();
                if ($existing) {
                    $up = $pdo->prepare("UPDATE mg_users SET name=?, email=?, password_hash=?, role='owner', is_active=1 WHERE id=?");
                    $up->execute(array($adminName, $adminEmail, $hash, $existing));
                } else {
                    $in = $pdo->prepare("INSERT INTO mg_users (role, name, username, email, password_hash, is_active, created_at)
                                         VALUES ('owner', ?, ?, ?, ?, 1, NOW())");
                    $in->execute(array($adminName, $adminUser, $adminEmail, $hash));
                }

                // Identidad del restaurante. Si se cargó la demostración, sus
                // datos de contacto son de ejemplo: se borran para que nadie
                // publique sin querer el teléfono ni la cuenta de otro.
                $st = $pdo->prepare('INSERT INTO mg_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
                $st->execute(array('name', $restName));
                if ($withDemo) {
                    foreach (array('tagline', 'description', 'phone', 'whatsapp', 'email', 'address',
                                   'city', 'map_url', 'review_url', 'bank_info', 'instagram', 'facebook') as $campo) {
                        $st->execute(array($campo, ''));
                    }
                    if ($adminEmail !== '') { $st->execute(array('email', $adminEmail)); }
                }
                // Monograma con las iniciales del restaurante.
                mg_logo_inicial($pdo, $restName);

                $cronToken = bin2hex(random_bytes(16));
                $config = array(
                    'app' => array(
                        'name' => 'MenúGold', 'url' => '', 'debug' => false,
                        'locale' => 'es', 'timezone' => 'America/Guatemala',
                    ),
                    'db' => array('host' => $host, 'port' => $port, 'name' => $name, 'user' => $user, 'pass' => $pass),
                    'security' => array(
                        'app_key'     => bin2hex(random_bytes(24)),
                        'cron_token'  => $cronToken,
                        'session_ttl' => 7200,
                    ),
                    'mail' => array('host' => '', 'port' => 587, 'user' => '', 'pass' => '', 'secure' => 'tls', 'from' => '', 'from_name' => $restName),
                    'uploads' => array('max_bytes' => 8388608),
                );
                $php = "<?php\n// Generado por el instalador de MenúGold. No lo compartas: trae credenciales.\nreturn " . mg_export($config) . ";\n";
                if (@file_put_contents(MG_ROOT . '/config/config.php', $php) === false) {
                    throw new RuntimeException('No se pudo escribir config/config.php. Da permiso de escritura a la carpeta /config.');
                }
                @chmod(MG_ROOT . '/config/config.php', 0640);
                @file_put_contents(MG_ROOT . '/install/install.lock', date('c'));

                $_SESSION['done'] = array('user' => $adminUser, 'cron' => $cronToken);
                header('Location: ?listo=1');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Error al instalar: ' . $e->getMessage();
            }
        }
    }
}

/* ---------------- Utilidades ---------------- */

/**
 * Dibuja el monograma del restaurante y regenera los iconos de la app.
 * Si algo falla se deja el logotipo que venga: nunca corta la instalación.
 */
function mg_logo_inicial(PDO $pdo, $nombre)
{
    try {
        if (!extension_loaded('gd') || !is_file(MG_ROOT . '/tools/lib-arte.php')) { return; }
        require_once MG_APP . '/Core/Autoloader.php';
        Autoloader::register();
        Autoloader::addNamespace('MenuGold\\Core', MG_APP . '/Core');
        require_once MG_ROOT . '/tools/lib-arte.php';

        $palabras = preg_split('/\s+/u', trim((string)$nombre));
        $letras = '';
        foreach ($palabras as $w) {
            if ($w === '' || mb_strlen($w) < 2) { continue; }
            $letras .= mb_strtoupper(mb_substr($w, 0, 1));
            if (mb_strlen($letras) >= 2) { break; }
        }
        if ($letras === '') { $letras = mb_strtoupper(mb_substr(trim((string)$nombre) . 'M', 0, 2)); }

        $ttf = MG_ROOT . '/tools/fuentes/Fraunces.ttf';
        $im = mg_arte_logo(900, $letras, '#0C0B09', '#D8B26E', is_file($ttf) ? $ttf : null);
        $tmp = sys_get_temp_dir() . '/mg-logo-' . bin2hex(random_bytes(4)) . '.png';
        imagepng($im, $tmp);
        imagedestroy($im);

        \MenuGold\Core\Config::load(array(
            'app' => array('timezone' => 'America/Guatemala'),
            'uploads' => array('max_bytes' => 8388608),
        ));
        $base = \MenuGold\Core\Image::storePath($tmp, 'marca', 960, 'logo.png');
        @unlink($tmp);
        \MenuGold\Core\Image::generatePwaIcons($base, '#0C0B09');

        $st = $pdo->prepare('INSERT INTO mg_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
        $st->execute(array('logo', $base));
    } catch (Throwable $e) {
        // Sin logotipo propio se sigue adelante: el dueño puede subirlo luego.
    }
}
function mg_run_sql(PDO $pdo, $file)
{
    if (!is_file($file)) { return; }
    foreach (mg_split_sql((string)file_get_contents($file)) as $s) {
        $s = trim($s);
        if ($s !== '') { $pdo->exec($s); }
    }
}

function mg_split_sql($sql)
{
    $out = array(); $buffer = ''; $inString = false; $quote = '';
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
    if (is_bool($value)) { return $value ? 'true' : 'false'; }
    if (is_null($value)) { return 'null'; }
    if (is_int($value) || is_float($value)) { return (string)$value; }
    return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string)$value) . "'";
}

$token = $_SESSION['csrf'];
// Si ya hay un config/config.php que conecta, los datos de la base se
// rellenan solos: nadie tiene que ir a buscar su contraseña otra vez para
// terminar de arreglar algo que ya estaba configurado.
$dbPrevia = isset($estadoPrevio['cfg']['db']) && $estadoPrevio['conecta']
    ? $estadoPrevio['cfg']['db'] : array();
$post = function ($k, $d = '') use ($dbPrevia) {
    if (!isset($_POST[$k]) && $dbPrevia) {
        $mapa = array('db_host' => 'host', 'db_port' => 'port', 'db_name' => 'name',
                      'db_user' => 'user', 'db_pass' => 'pass');
        if (isset($mapa[$k], $dbPrevia[$mapa[$k]])) {
            $d = (string)$dbPrevia[$mapa[$k]];
        }
    }
    return htmlspecialchars((string)($_POST[$k] ?? $d), ENT_QUOTES);
};
$httpsUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'TUDOMINIO');
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
  <div style="width:min(100%,820px)">
    <p class="eyebrow" style="margin-bottom:1.2rem">Instalación</p>
    <h1 class="display" style="font-size:var(--step-3);margin-bottom:.6rem">MenúGold</h1>

    <?php if ($reparar): ?>
      <p class="muted" style="margin-bottom:2rem">Tu configuración ya está. Solo faltan las tablas.</p>
      <div class="card">
        <?php foreach ($errors as $e): ?>
          <div class="alert alert-error"><span><?= htmlspecialchars($e, ENT_QUOTES) ?></span></div>
        <?php endforeach; ?>

        <h2 style="font-family:var(--font-display);font-weight:400;margin:0 0 .6rem">Preparar la base de datos</h2>
        <p class="muted">
          MenúGold conecta bien con tu base de datos <b><?= htmlspecialchars($estadoPrevio['cfg']['db']['name'], ENT_QUOTES) ?></b>,
          pero le faltan <b><?= count($estadoPrevio['faltan']) ?></b> de sus tablas.
          Suele pasar al subir esta versión a un dominio donde antes había otra.
        </p>
        <p class="muted" style="font-size:var(--step--1);margin-top:.8rem">
          Al pulsar el botón solo se crean las tablas que empiezan por <code>mg_</code>.
          <b>No se borra ni se cambia nada</b> de lo que ya tengas ahí, ni tu configuración,
          ni tus usuarios, ni tus pedidos.
        </p>

        <form method="post" style="margin-top:1.6rem">
          <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
          <input type="hidden" name="accion" value="reparar">
          <button class="btn" type="submit">Crear las tablas que faltan</button>
        </form>

        <details style="margin-top:1.6rem">
          <summary style="cursor:pointer;color:var(--text-faint);font-size:var(--step--1)">Ver qué tablas faltan</summary>
          <p style="font-size:12px;color:var(--text-faint);line-height:1.8;margin-top:.6rem;word-break:break-all">
            <?= htmlspecialchars(implode(' · ', $estadoPrevio['faltan']), ENT_QUOTES) ?>
          </p>
        </details>
      </div>

    <?php elseif ($done): ?>
      <p class="muted" style="margin-bottom:2rem">Listo. Tu menú ya está en línea.</p>
      <div class="card">
        <div class="alert alert-success"><span>MenúGold quedó instalado.</span></div>
        <p class="muted" style="font-size:var(--step--1)">Entra al panel con el usuario <b><?= htmlspecialchars($done['user'], ENT_QUOTES) ?></b>.</p>

        <p class="label mt-3">Antes de nada: borra la carpeta <code>/install</code></p>
        <p class="muted" style="font-size:var(--step--1)">Desde el File Manager de cPanel. Es obligatorio.</p>

        <p class="label mt-3">Tarea programada (cPanel → Cron Jobs, cada 10 minutos)</p>
        <div class="copy-box">
          <pre>*/10 * * * * curl -s "<?= htmlspecialchars($httpsUrl . $basePath . '/cron/run.php?token=' . $done['cron'], ENT_QUOTES) ?>"</pre>
        </div>
        <p class="muted" style="font-size:var(--step--1);margin-top:.6rem">
          Cierra llamadas olvidadas, libera mesas y crea un respaldo semanal.
        </p>

        <div class="row mt-3">
          <a class="btn" href="<?= htmlspecialchars($basePath, ENT_QUOTES) ?>/panel/entrar">Entrar al panel</a>
          <a class="btn btn-ghost" href="<?= htmlspecialchars($basePath === '' ? '/' : $basePath, ENT_QUOTES) ?>">Ver mi menú</a>
        </div>
      </div>

    <?php else: ?>
      <p class="muted" style="margin-bottom:2rem">Una sola pantalla. Unos treinta segundos.</p>

      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error" role="alert"><span><?= htmlspecialchars($err, ENT_QUOTES) ?></span></div>
      <?php endforeach; ?>

      <div class="card">
        <div class="card-head"><h2>Tu servidor</h2></div>
        <ul class="check-list">
          <?php foreach ($checks as $c): ?>
            <li class="<?= $c[1] ? 'ok' : ($c[3] ? 'bad' : 'warn') ?>">
              <span><?= htmlspecialchars($c[0], ENT_QUOTES) ?></span>
              <em><?= $c[1] ? 'Correcto' : htmlspecialchars($c[2], ENT_QUOTES) ?></em>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <?php if ($reparado): ?>
        <div class="alert alert-success" style="margin-bottom:1.4rem">
          <span>Las tablas quedaron creadas. Solo falta tu cuenta de dueño.</span>
        </div>
      <?php endif; ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="accion" value="instalar">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">

        <div class="card mt-2">
          <div class="card-head">
            <h2>Base de datos</h2>
            <p>La que ya tienes en cPanel sirve: MenúGold crea sus propias tablas con el prefijo <code>mg_</code> y no toca nada más.</p>
          </div>
          <div class="grid grid-2">
            <div class="field"><label for="db_host">Servidor</label>
              <input class="input" id="db_host" name="db_host" type="text" value="<?= $post('db_host', 'localhost') ?>"></div>
            <div class="field"><label for="db_port">Puerto</label>
              <input class="input" id="db_port" name="db_port" type="number" value="<?= $post('db_port', '3306') ?>"></div>
            <div class="field"><label for="db_name">Nombre de la base *</label>
              <input class="input" id="db_name" name="db_name" type="text" required value="<?= $post('db_name') ?>"></div>
            <div class="field"><label for="db_user">Usuario *</label>
              <input class="input" id="db_user" name="db_user" type="text" required value="<?= $post('db_user') ?>"></div>
            <div class="field" style="grid-column:1/-1"><label for="db_pass">Contraseña</label>
              <input class="input" id="db_pass" name="db_pass" type="password" value="<?= $post('db_pass') ?>"></div>
          </div>
        </div>

        <div class="card mt-2">
          <div class="card-head"><h2>Tu restaurante y tu cuenta</h2></div>
          <div class="grid grid-2">
            <div class="field" style="grid-column:1/-1"><label for="rest_name">Nombre del restaurante *</label>
              <input class="input" id="rest_name" name="rest_name" type="text" required maxlength="120" value="<?= $post('rest_name') ?>"></div>
            <div class="field"><label for="admin_name">Tu nombre *</label>
              <input class="input" id="admin_name" name="admin_name" type="text" required value="<?= $post('admin_name') ?>"></div>
            <div class="field"><label for="admin_email">Tu correo</label>
              <input class="input" id="admin_email" name="admin_email" type="email" value="<?= $post('admin_email') ?>"></div>
            <div class="field"><label for="admin_user">Usuario para entrar *</label>
              <input class="input" id="admin_user" name="admin_user" type="text" required value="<?= $post('admin_user') ?>"></div>
            <div class="field"><label for="admin_pass">Contraseña *</label>
              <input class="input" id="admin_pass" name="admin_pass" type="password" required minlength="8">
              <p class="field-hint">Mínimo 8 caracteres.</p></div>
          </div>
          <label class="switch mt-2"><input type="checkbox" name="demo" value="1" <?= isset($_POST['demo']) || $_SERVER['REQUEST_METHOD'] !== 'POST' ? 'checked' : '' ?>>
            <span class="switch-track" aria-hidden="true"></span>
            <span>Cargar el menú de ejemplo con sus fotografías (puedes borrarlo después)</span></label>
        </div>

        <div class="row mt-3">
          <button class="btn" type="submit" <?= $blocking ? 'disabled' : '' ?>>Instalar</button>
          <?php if ($blocking): ?><span class="muted" style="font-size:var(--step--1)">Corrige primero los requisitos marcados en rojo.</span><?php endif; ?>
        </div>
      </form>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
