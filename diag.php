<?php
/**
 * Diagnóstico temporal. Sube este archivo a la raíz del sitio, ábrelo en el
 * navegador (https://tudominio.com/diag.php) y comparte el resultado.
 * ELIMÍNALO después de usarlo — no debe quedar público.
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

function line($label, $ok, $extra = '') {
    echo str_pad($label, 34) . ($ok ? 'OK ' : 'FALLA ') . $extra . "\n";
}

echo "=== DIAGNÓSTICO FUENTE DE VIDA ===\n\n";
echo "PHP version: " . PHP_VERSION . "\n";
line('PHP >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>='));
foreach (['pdo_mysql','mbstring','fileinfo','gd','json'] as $ext) {
    line("ext $ext", extension_loaded($ext));
}

$root = __DIR__;
$configFile = $root . '/config/config.php';
line('config/config.php existe', file_exists($configFile));
if (!file_exists($configFile)) { echo "\n>> No hay config. El sitio no está instalado.\n"; exit; }

$cfg = require $configFile;
echo "\nDB host: " . ($cfg['db']['host'] ?? '?') . " / db: " . ($cfg['db']['name'] ?? '?') . "\n";
echo "base_url configurado: " . ($cfg['app']['base_url'] ?? '(vacío)') . "\n";
echo "env: " . ($cfg['app']['env'] ?? '?') . "\n\n";

// --- DB connection ---
try {
    $dsn = "mysql:host={$cfg['db']['host']};port=" . ($cfg['db']['port'] ?? 3306) . ";dbname={$cfg['db']['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    line('Conexión a MySQL', true);
    foreach (['admins','settings','pages','sections','platforms','albums','photos','media','submissions','menu_items','admin_logs'] as $t) {
        try { $n = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); line("tabla $t", true, "($n filas)"); }
        catch (Throwable $e) { line("tabla $t", false, $e->getMessage()); }
    }
    try { $adm = $pdo->query("SELECT username,email,is_active FROM admins LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        echo "administradores: " . json_encode($adm, JSON_UNESCAPED_UNICODE) . "\n"; } catch (Throwable $e) {}
} catch (Throwable $e) {
    line('Conexión a MySQL', false, $e->getMessage());
}

// --- Sesiones ---
echo "\n";
$sessDir = $root . '/storage/sessions';
if (!is_dir($sessDir)) { @mkdir($sessDir, 0700, true); }
line('storage/sessions escribible', is_dir($sessDir) && is_writable($sessDir), $sessDir);
line('session.save_path por defecto escribible', is_writable(session_save_path() ?: sys_get_temp_dir()), (string)(session_save_path() ?: '(vacío)'));
if (is_dir($sessDir) && is_writable($sessDir)) { session_save_path($sessDir); }
@session_start();
$_SESSION['diag_test'] = 'ok';
line('escritura de sesión', ($_SESSION['diag_test'] ?? '') === 'ok');

// --- .htaccess / mod_rewrite ---
echo "\n";
line('mod_rewrite (apache_get_modules)', function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules()) : true,
     function_exists('apache_get_modules') ? '' : '(no detectable, probablemente FPM)');
line('archivo .htaccess raíz existe', file_exists($root . '/.htaccess'));
line('carpeta admin existe', is_dir($root . '/admin'));
line('admin/index.php existe', file_exists($root . '/admin/index.php'));

// --- Cargar el bootstrap real para capturar el error exacto del admin ---
echo "\n=== PRUEBA DE CARGA DEL NÚCLEO (bootstrap) ===\n";
try {
    ob_start();
    require $root . '/includes/bootstrap.php';
    ob_end_clean();
    line('bootstrap.php carga', true);
    line('Settings accesible', class_exists('Settings'));
    echo "site_name: " . Settings::get('site_name') . "\n";
} catch (Throwable $e) {
    ob_end_clean();
    line('bootstrap.php carga', false);
    echo ">> ERROR: " . $e->getMessage() . "\n>> en " . $e->getFile() . ":" . $e->getLine() . "\n";
}

$last = error_get_last();
if ($last) { echo "\nÚltimo error PHP: " . json_encode($last, JSON_UNESCAPED_UNICODE) . "\n"; }
echo "\n=== FIN. Elimina este archivo (diag.php) del servidor. ===\n";
