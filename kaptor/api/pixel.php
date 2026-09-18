<?php
/**
 * Kaptor - Píxel de seguimiento de aperturas.
 *
 * Devuelve siempre un GIF transparente de 1x1, exista o no el token: así nunca
 * se filtra información y el mensaje del destinatario no muestra un hueco roto.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$token = trim((string) ($_GET['t'] ?? ''));
if (preg_match('/^[a-f0-9]{32}$/', $token) && Ajustes::activo('seguimiento_aperturas', true)) {
    try {
        Campana::registrarApertura($token);
    } catch (Throwable $e) {
        error_log('Kaptor / apertura: ' . $e->getMessage());
    }
}

// GIF transparente de 1x1 píxel.
$gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('Content-Length: ' . strlen($gif));   // el tamaño real, nunca uno fijo

echo $gif;
