<?php
/** Instalador web de Licitador. Se bloquea solo al terminar (storage/installed.lock). */
$root = dirname(__DIR__);
error_reporting(E_ALL); ini_set('display_errors', '0'); ini_set('log_errors', '1');
@ini_set('error_log', $root . '/storage/logs/php.log');
if (PHP_VERSION_ID < 80000) { exit('<meta charset="utf-8"><p style="font-family:sans-serif">Licitador necesita PHP 8.0 o superior. Tu servidor usa PHP ' . htmlspecialchars(PHP_VERSION) . '. Cámbialo en cPanel → "Seleccionar versión de PHP".</p>'); }
$locked = is_file($root . '/storage/installed.lock');
$sp = $root . '/storage/sessions'; if (!is_dir($sp)) @mkdir($sp, 0750, true); if (is_writable($sp)) session_save_path($sp);
session_name('LICINST'); session_start();
function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/'); if ($base === '\\' || $base === '.') $base = '';
$page = function (string $title, string $body) use ($base) {
    header('Content-Type: text/html; charset=utf-8'); header('X-Frame-Options: DENY');
    echo '<!doctype html><html lang="es" class="no-js" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . h($title) . ' · Licitador</title><link rel="stylesheet" href="../assets/css/app.min.css"></head><body class="install"><main class="install-box"><div class="brand brand-lg"><span class="brand-dot"></span>LICITADOR</div>' . $body . '</main></body></html>';
    exit;
};
if ($locked) {
    $page('Instalación completa', '<h1>Licitador ya está instalado</h1><p class="muted">Por seguridad, el instalador quedó bloqueado. Para reinstalar, borra el archivo <code>storage/installed.lock</code> y <code>config/config.php</code> con el Administrador de archivos.</p><p><a class="btn btn-gold" href="' . h($base) . '/">Ir al sitio</a></p>');
}
// ---- Requisitos ----
$req = [];
$req[] = ['PHP 8.0 o superior', PHP_VERSION_ID >= 80000, 'Versión actual: ' . PHP_VERSION, true];
foreach (['pdo_mysql' => 'pdo_mysql (conexión MySQL)', 'mbstring' => 'mbstring', 'curl' => 'curl (correo, WhatsApp, IA y fuente de datos)', 'json' => 'json', 'openssl' => 'openssl'] as $ext => $lbl) $req[] = [$lbl, extension_loaded($ext), extension_loaded($ext) ? 'Disponible' : 'Falta. Actívala en cPanel → Seleccionar versión de PHP → Extensiones.', true];
$req[] = ['zip (descarga de borradores en DOCX)', class_exists('ZipArchive'), class_exists('ZipArchive') ? 'Disponible' : 'Opcional: sin ella solo se podrá descargar PDF.', false];
foreach (['config' => 'config/', 'storage' => 'storage/', 'storage/logs' => 'storage/logs/', 'storage/uploads' => 'storage/uploads/', 'storage/tmp' => 'storage/tmp/'] as $d => $lbl) {
    $path = $root . '/' . $d; if (!is_dir($path)) @mkdir($path, 0750, true);
    $ok = is_dir($path) && is_writable($path);
    $req[] = ['Permiso de escritura en ' . $lbl, $ok, $ok ? 'Correcto' : 'Da permisos 755 (o 775) a esta carpeta en cPanel.', true];
}
$reqOk = true; foreach ($req as $r) if ($r[3] && !$r[1]) $reqOk = false;
$v = ['db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'admin_name' => '', 'admin_email' => '', 'site_name' => 'Licitador'];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['t']) || !hash_equals($_SESSION['t'], (string) ($_POST['_csrf'] ?? ''))) { $errors[] = 'La página expiró. Recárgala e intenta de nuevo.'; }
    foreach ($v as $k => $_) $v[$k] = trim((string) ($_POST[$k] ?? $v[$k]));
    $dbpass = (string) ($_POST['db_pass'] ?? ''); $apass = (string) ($_POST['admin_pass'] ?? '');
    if (!$reqOk) $errors[] = 'Corrige los requisitos marcados en rojo antes de continuar.';
    if ($v['db_name'] === '' || $v['db_user'] === '') $errors[] = 'Escribe el nombre de la base de datos y su usuario (los creaste en cPanel → Bases de datos MySQL).';
    if ($v['admin_name'] === '' || !filter_var($v['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Escribe tu nombre y un correo válido para el administrador.';
    if (strlen($apass) < 8 || !preg_match('/[A-Za-z]/', $apass) || !preg_match('/\d/', $apass)) $errors[] = 'La contraseña del administrador debe tener 8 o más caracteres, con letras y números.';
    if (!$errors) {
        try {
            $pdo = new PDO('mysql:host=' . $v['db_host'] . ';dbname=' . $v['db_name'] . ';charset=utf8mb4', $v['db_user'], $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            $c = (int) ($e->errorInfo[1] ?? 0);
            $errors[] = match (true) {
                $c === 1045 => 'El usuario o la contraseña de la base de datos no son correctos. Revísalos en cPanel → Bases de datos MySQL.',
                $c === 1049 => 'La base de datos "' . h($v['db_name']) . '" no existe. Créala en cPanel → Bases de datos MySQL (recuerda el prefijo de tu cuenta).',
                $c === 1044 => 'El usuario no tiene permisos sobre esa base de datos. En cPanel, agrégalo a la base con "Todos los privilegios".',
                $c === 2002 || $c === 2006 => 'No se pudo conectar al servidor de base de datos "' . h($v['db_host']) . '". Normalmente es "localhost".',
                default => 'No pudimos conectar con la base de datos. Revisa servidor, nombre, usuario y contraseña.',
            };
            error_log('install db: ' . $e->getMessage());
        }
    }
    if (!$errors) {
        try {
            $cfgFile = $root . '/config/config.php';
            $config = ['db_host' => $v['db_host'], 'db_name' => $v['db_name'], 'db_user' => $v['db_user'], 'db_pass' => $dbpass, 'app_key' => bin2hex(random_bytes(32)), 'cron_token' => bin2hex(random_bytes(20)), 'clean_urls' => true];
            if (file_put_contents($cfgFile, "<?php\n// Generado por el instalador. No compartir.\nreturn " . var_export($config, true) . ";\n", LOCK_EX) === false) throw new RuntimeException('No se pudo escribir config/config.php');
            @chmod($cfgFile, 0640);
            require $root . '/src/bootstrap.php';
            cfg('__reload');
            foreach ((require $root . '/src/schema.php') as $sql) db()->exec($sql);
            seed_defaults();
            set_setting('site_name', $v['site_name'] ?: 'Licitador');
            set_setting('contact_email', $v['admin_email']); set_setting('admin_alert_email', $v['admin_email']);
            set_setting('cron_token', $config['cron_token']);
            if (!val("SELECT COUNT(*) FROM users WHERE role='admin'")) {
                insert('users', ['role' => 'admin', 'admin_role' => 'superadmin', 'name' => $v['admin_name'], 'email' => mb_strtolower($v['admin_email']), 'pass' => hash_pass($apass), 'email_verified' => 1, 'created_at' => now()]);
            }
            if (!val("SELECT COUNT(*) FROM tenders")) seed_demo_tenders();
            // ¿Funcionan las URLs limpias?
            $clean = 0; $ch = curl_init((is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $base . '/ping-rewrite');
            if ($ch) { curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true]); $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
                if ($r !== false && $code > 0) $clean = trim($r) === 'ok' ? 1 : 0;               // respondió: sabemos si reescribe
                else $clean = preg_match('/apache|litespeed|nginx/i', $_SERVER['SERVER_SOFTWARE'] ?? '') ? 1 : 0; // el hosting bloquea la auto-consulta: confiamos en .htaccess
            }
            set_setting('clean_urls', (string) $clean);
            file_put_contents($root . '/storage/installed.lock', date('c'));
            @mkdir($root . '/storage/logs', 0750, true);
            $cronLine = '*/30 * * * * /usr/local/bin/php ' . $root . '/cron/sync.php >/dev/null 2>&1';
            $page('¡Listo!', '<h1>¡Licitador quedó instalado!</h1><p class="muted">Se crearon las tablas, tu usuario administrador y ' . (int) val("SELECT COUNT(*) FROM tenders") . ' concursos de ejemplo (DEMO). El instalador se bloqueó automáticamente.</p>' . ($clean ? '' : '<div class="flash warn">Tu servidor no aplicó URLs limpias; activamos el modo compatible (index.php?r=). Puedes cambiarlo en Admin → Ajustes.</div>') .
                '<h3>Último paso: el cron</h3><p>En cPanel → <strong>Tareas Cron</strong>, pega esta línea (cada 30 minutos):</p><pre class="code">' . h($cronLine) . '</pre><p class="muted">También verás la línea exacta, y la versión por URL, en Admin → Fuente de datos.</p><p><a class="btn btn-gold" href="' . h($base . ($clean ? '/ingresar' : '/index.php?r=ingresar')) . '">Entrar al panel</a></p>');
        } catch (Throwable $e) {
            error_log('install: ' . $e->getMessage());
            $errors[] = 'Ocurrió un problema al crear las tablas. Verifica que el usuario tenga todos los privilegios sobre la base y que esté vacía. (Detalle técnico guardado en storage/logs/php.log)';
            @unlink($root . '/config/config.php');
        }
    }
}
$_SESSION['t'] = $_SESSION['t'] ?? bin2hex(random_bytes(16));
ob_start(); ?>
<h1>Instalar Licitador</h1>
<p class="muted">Un solo formulario: revisamos tu servidor, conectamos la base de datos y creamos tu cuenta de administrador.</p>
<h3>1. Requisitos del servidor</h3>
<ul class="reqs"><?php foreach ($req as $r): ?><li class="<?= $r[1] ? 'ok' : ($r[3] ? 'bad' : 'warn') ?>"><span class="ico"><?= $r[1] ? '✓' : ($r[3] ? '✕' : '!') ?></span><div><strong><?= h($r[0]) ?></strong><small><?= h($r[2]) ?></small></div></li><?php endforeach; ?></ul>
<?php foreach ($errors as $er): ?><div class="flash err"><?= $er ?></div><?php endforeach; ?>
<form method="post" class="form" autocomplete="off">
<input type="hidden" name="_csrf" value="<?= h($_SESSION['t']) ?>">
<h3>2. Base de datos</h3>
<p class="muted">Créala antes en cPanel → <em>Bases de datos MySQL</em> (base, usuario y asignar el usuario a la base).</p>
<label>Servidor<input name="db_host" value="<?= h($v['db_host']) ?>" required></label>
<label>Nombre de la base de datos<input name="db_name" value="<?= h($v['db_name']) ?>" required></label>
<label>Usuario de la base<input name="db_user" value="<?= h($v['db_user']) ?>" required></label>
<label>Contraseña de la base<input type="password" name="db_pass"></label>
<h3>3. Administrador y sitio</h3>
<label>Nombre del sitio<input name="site_name" value="<?= h($v['site_name']) ?>"></label>
<label>Tu nombre<input name="admin_name" value="<?= h($v['admin_name']) ?>" required></label>
<label>Tu correo<input type="email" name="admin_email" value="<?= h($v['admin_email']) ?>" required></label>
<label>Contraseña (8+ caracteres, letras y números)<input type="password" name="admin_pass" minlength="8" required></label>
<button class="btn btn-gold" <?= $reqOk ? '' : 'disabled' ?>>Instalar ahora</button>
</form>
<?php $page('Instalador', ob_get_clean());
