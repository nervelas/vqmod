<?php
declare(strict_types=1);

namespace MenuGold\Controllers;

use MenuGold\Core\Controller;
use MenuGold\Core\HttpException;

/**
 * Sirve los archivos subidos desde /storage/uploads, que nunca es
 * accesible directamente por Apache. Valida la ruta y el tipo real.
 */
class Archivo extends Controller
{
    private const TIPOS = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
    ];

    public function servir(array $p = []): void
    {
        // Servir una imagen no necesita la sesión. Si la mantenemos abierta,
        // PHP bloquea el archivo de sesión y las fotos del menú se cargarían
        // de una en una en lugar de en paralelo.
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

        $partes = array_filter([
            (string)($p['carpeta'] ?? ''),
            (string)($p['sub'] ?? ''),
            (string)($p['nombre'] ?? ''),
        ], static fn($x) => $x !== '');

        foreach ($partes as $x) {
            if (!preg_match('/^[A-Za-z0-9._-]{1,120}$/', $x) || strpos($x, '..') !== false) {
                throw HttpException::notFound();
            }
        }
        $rel = implode('/', $partes);
        $baseDir = realpath(MG_ROOT . '/storage/uploads');
        $file = realpath(MG_ROOT . '/storage/uploads/' . $rel);
        if (!$baseDir || !$file || strncmp($file, $baseDir, strlen($baseDir)) !== 0 || !is_file($file)) {
            throw HttpException::notFound('Archivo no encontrado.');
        }

        $ext = strtolower((string)pathinfo($file, PATHINFO_EXTENSION));
        if (!isset(self::TIPOS[$ext])) throw HttpException::notFound();

        // Confirma que sea realmente una imagen
        if ($ext !== 'svg') {
            $info = @getimagesize($file);
            if (!$info) throw HttpException::notFound();
        }

        $etag = '"' . substr(md5((string)filemtime($file) . (string)filesize($file)), 0, 20) . '"';
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: ' . self::TIPOS[$ext]);
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: public, max-age=2592000, immutable');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline');
        readfile($file);
        exit;
    }
}
