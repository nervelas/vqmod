<?php
declare(strict_types=1);

// Front controller de AUREA. Todas las rutas pasan por aquí (ver .htaccess).
if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('AUREA requiere PHP 8.0 o superior.');
}
require __DIR__ . '/app/bootstrap.php';
\Aurea\Core\App::run();
