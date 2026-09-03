<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErrorHandler;

/**
 * Sirve archivos de /storage/uploads sin exponer la carpeta al servidor web.
 */
final class MediaController extends Controller
{
    private const TYPES = [
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif'  => 'image/gif',  'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        'pdf'  => 'application/pdf', 'ico' => 'image/x-icon',
    ];

    public function serve(array $params): void
    {
        $rel = (string) ($params['path'] ?? '');
        if ($rel === '' || str_contains($rel, "\0") || str_contains($rel, '..')) {
            ErrorHandler::render(404);
        }
        $root = realpath(STORAGE_PATH . '/uploads');
        $abs  = realpath(STORAGE_PATH . '/uploads/' . $rel);
        if (!$root || !$abs || !str_starts_with($abs, $root . DIRECTORY_SEPARATOR) || !is_file($abs)) {
            ErrorHandler::render(404);
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if (!isset(self::TYPES[$ext])) {
            ErrorHandler::render(404);
        }
        $mtime = (int) filemtime($abs);
        $etag  = '"' . substr(hash('sha256', $abs . $mtime), 0, 24) . '"';
        header('Content-Type: ' . self::TYPES[$ext]);
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; sandbox');
        header('Cache-Control: public, max-age=31536000, immutable');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Length: ' . filesize($abs));
        readfile($abs);
        exit;
    }
}
