<?php
declare(strict_types=1);
/**
 * Arranque de pruebas del agente P. Autoload S5\Services\* desde app/Services y
 * S5\Core\* desde stubs MÍNIMOS (tests/ai/stubs) con la API del contrato §2.
 * Con S5_REAL_CORE=1 se usa app/Core real si existe (requiere su configuración).
 */
define('S5_ROOT', dirname(__DIR__, 2));
spl_autoload_register(function (string $cls): void {
    if (strncmp($cls, 'S5\\', 3) !== 0) {
        return;
    }
    $parts = explode('\\', substr($cls, 3));
    $ns = array_shift($parts);
    $name = implode('/', $parts);
    if ($ns === 'Core') {
        $real = S5_ROOT . '/app/Core/' . $name . '.php';
        $f = (getenv('S5_REAL_CORE') && is_file($real)) ? $real : __DIR__ . '/stubs/' . $name . '.php';
    } else {
        $f = S5_ROOT . '/app/' . $ns . '/' . $name . '.php';
        // Clases auxiliares definidas junto a otra (ParserError).
        if (!is_file($f) && $cls === 'S5\\Services\\ParserError') {
            $f = S5_ROOT . '/app/Services/PresentationParser.php';
        }
    }
    if (is_file($f)) {
        require_once $f;
    }
});
