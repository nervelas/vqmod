<?php
// Router para el servidor embebido de PHP (solo desarrollo/pruebas): php -S 127.0.0.1:8081 -t . tests/router.php
$root = dirname(__DIR__);
$path = rawurldecode((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('#^/(app|config|database|storage|tests|extras)(/|$)#', $path) || preg_match('#/\.[^/]#', $path)) {
    http_response_code(403);
    exit('Prohibido');
}
$file = $root . $path;
if ($path !== '/' && is_file($file)) {
    if (substr($file, -4) === '.php') {
        $_SERVER['SCRIPT_NAME'] = $path;
        $_SERVER['SCRIPT_FILENAME'] = $file;
        require $file;
        return true;
    }
    return false;
}
if (is_dir($file) && is_file($file . '/index.php') && $path !== '/') {
    $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
    require $file . '/index.php';
    return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
require $root . '/index.php';
