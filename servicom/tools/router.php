<?php
/**
 * Router para el servidor embebido de PHP (solo pruebas locales):
 *   php -S 127.0.0.1:8080 -t . tools/router.php
 * Reproduce el comportamiento del .htaccess de Apache.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/..' . $path;

if (preg_match('#^/(config|app|vendor|storage|database|tools)(/|$)#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
if ($path !== '/' && is_file($file)) {
    if (str_ends_with($file, '.php')) {
        return false;
    }
    $mimes = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml',
        'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'woff2' => 'font/woff2', 'ico' => 'image/x-icon', 'json' => 'application/json', 'txt' => 'text/plain',
        'webmanifest' => 'application/manifest+json'];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
    }
    readfile($file);
    return true;
}
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
    $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
    require rtrim($file, '/') . '/index.php';
    return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../index.php';
