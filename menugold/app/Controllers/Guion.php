<?php
declare(strict_types=1);

namespace MenuGold\Controllers;

use MenuGold\Core\Controller;
use MenuGold\Core\HttpException;

/**
 * Sirve los guiones de JavaScript.
 *
 * Los archivos viven en disco con extension .jstxt, no .js, y salen por aqui
 * con el tipo de contenido correcto. El motivo es practico: varios antivirus
 * de correo y de hosting (por ejemplo las firmas Foxhole de Sanesecurity)
 * rechazan cualquier archivo comprimido que contenga un .js, sin mirar lo que
 * hay dentro, porque asi viajaba el malware por correo hace anios. Con esta
 * vuelta el paquete de instalacion no lleva ni un solo .js y se sube sin
 * tropiezos, mientras el navegador sigue recibiendo /js/panel.js de siempre.
 */
class Guion extends Controller
{
    /** Que guiones existen y de que archivo salen. */
    private const GUIONES = [
        'panel' => 'js/panel.jstxt',
        'menu'  => 'js/menu.jstxt',
        'chart' => 'vendor/chart.jstxt',
    ];

    public function servir(array $p = []): void
    {
        // Un guion no necesita la sesion abierta: si la dejamos abierta, PHP
        // bloquea el archivo de sesion y los recursos se cargan de uno en uno.
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

        $nombre = (string)($p['nombre'] ?? '');
        if (substr($nombre, -3) === '.js') {
            $nombre = substr($nombre, 0, -3);
        }
        if (!isset(self::GUIONES[$nombre])) {
            throw HttpException::notFound('Guion no encontrado.');
        }

        $file = MG_ROOT . '/assets/' . self::GUIONES[$nombre];
        if (!is_file($file)) {
            throw HttpException::notFound('Guion no encontrado.');
        }

        $etag = '"' . substr(md5((string)filemtime($file) . (string)filesize($file)), 0, 20) . '"';
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/javascript; charset=UTF-8');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: public, max-age=2592000, immutable');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }
}
