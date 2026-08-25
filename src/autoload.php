<?php
/**
 * Autoloader PSR-4 sin dependencias externas (sin Composer).
 * Namespace raiz: Fel\  ->  src/
 */
declare(strict_types=1);

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Fel\\';
    $baseDir = __DIR__ . '/';

    if (!str_starts_with($clase, $prefijo)) {
        return;
    }

    $relativa = substr($clase, strlen($prefijo));
    $ruta = $baseDir . str_replace('\\', '/', $relativa) . '.php';

    if (is_file($ruta)) {
        require $ruta;
    }
});
