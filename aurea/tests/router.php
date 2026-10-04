<?php
// Router para el servidor embebido de PHP (solo desarrollo/pruebas): emula las reglas de .htaccess.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$docroot = getenv('AUREA_DOCROOT') ?: realpath(__DIR__ . '/..');
$file = $docroot . $path;
foreach (['/app/', '/config/', '/storage/', '/database/', '/lang/', '/tools/', '/tests/'] as $deny) {
    if (strpos($path, $deny) === 0) { http_response_code(403); echo 'Forbidden'; return true; }
}
if ($path !== '/' && is_file($file) && !preg_match('/\.(php\d?|phtml)$/', $path)) { return false; }
if (preg_match('#^/instalar(/|$)#', $path)) { $_SERVER['SCRIPT_NAME'] = '/instalar/index.php'; require $docroot . '/instalar/index.php'; return true; }
if ($path === '/cron.php') { $_SERVER['SCRIPT_NAME'] = '/cron.php'; require $docroot . '/cron.php'; return true; }
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $docroot . '/index.php';
return true;
