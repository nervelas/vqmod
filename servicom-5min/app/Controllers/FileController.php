<?php
declare(strict_types=1);
namespace S5\Controllers;

use S5\Core\Auth;
use S5\Core\Fs;
use S5\Services\Files;
use S5\Services\Orders;

/** Entrega de archivos privados (miniaturas del cliente, comprobante para el dueño). */
final class FileController
{
    private static function send(string $path, string $mime, string $name, bool $inline = true): void
    {
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=300');
        header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; sandbox");
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Miniatura / imagen del borrador del cliente. */
    public static function client(array $p): void
    {
        $o = Orders::byToken((string) ($p['token'] ?? ''));
        $f = $o ? Files::get((int) ($p['id'] ?? 0), (int) $o['id']) : null;
        if (!$f || !str_starts_with((string) $f['mime'], 'image/')) {
            http_response_code(404);
            exit;
        }
        $thumb = !empty($_GET['t']) && is_file(Files::thumbPath($f));
        if ($thumb) {
            self::send(Files::thumbPath($f), 'image/jpeg', 'miniatura.jpg');
        }
        self::send(Files::path($f), (string) $f['mime'], 'imagen');
    }

    public static function clientThumb(array $p): void
    {
        $_GET['t'] = '1';
        self::client($p);
    }

    /** Comprobante / archivos para el dueño (requiere sesión). */
    public static function admin(array $p): void
    {
        if (!Auth::check()) {
            http_response_code(403);
            exit;
        }
        $db = \S5\Core\Db::one('SELECT * FROM ' . \S5\Core\Db::t('files') . ' WHERE id=?', [(int) ($p['id'] ?? 0)]);
        if (!$db) {
            http_response_code(404);
            exit;
        }
        $path = Files::path($db);
        if (!Fs::inside($path, Files::dir((int) $db['order_id']))) {
            http_response_code(404);
            exit;
        }
        $inline = str_starts_with((string) $db['mime'], 'image/') || $db['mime'] === 'application/pdf';
        self::send($path, (string) $db['mime'], (string) ($db['orig_name'] ?: 'archivo'), $inline);
    }
}
