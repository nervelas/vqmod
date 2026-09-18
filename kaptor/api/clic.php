<?php
/**
 * Kaptor - Seguimiento de clics.
 *
 * Cada enlace del mensaje pasa por aquí. La URL de destino viaja firmada con
 * HMAC, de modo que nadie puede usar esta dirección como redirector abierto
 * hacia sitios de phishing.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$token = trim((string) ($_GET['t'] ?? ''));
$firma = trim((string) ($_GET['f'] ?? ''));
$url   = (string) ($_GET['u'] ?? '');

$destino = '';
if (preg_match('/^[a-f0-9]{32}$/', $token) && $firma !== '' && $url !== '') {
    try {
        $destino = Campana::registrarClic($token, $url, $firma);
    } catch (Throwable $e) {
        error_log('Kaptor / clic: ' . $e->getMessage());
    }
}

// Sin firma válida no se redirige a ninguna parte: se vuelve a la portada.
if ($destino === '' || !preg_match('~^https?://~i', $destino)) {
    cr_redirigir('index.php');
}

header('Location: ' . $destino, true, 302);
header('Referrer-Policy: no-referrer');
exit;
