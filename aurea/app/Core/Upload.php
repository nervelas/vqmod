<?php
declare(strict_types=1);

namespace Aurea\Core;

/**
 * Subidas seguras. Documentos privados: storage/private (fuera de acceso web, se entregan por script autorizado).
 * Imágenes públicas (logo, fotos): uploads/ (se re-codifican con GD para eliminar cualquier carga útil).
 */
final class Upload
{
    // extensión => mimes permitidos (verificados con finfo, nunca se confía en el cliente)
    private const DOCS = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'txt' => ['text/plain'],
    ];

    public static function maxBytes(): int
    {
        return max(1, Settings::int('upload_max_mb', 5)) * 1024 * 1024;
    }

    /** Valida un documento sin guardarlo. Devuelve [extensión, mime] o lanza \RuntimeException. */
    public static function check(array $f): array
    {
        self::basicChecks($f);
        $orig = (string)$f['name'];
        $parts = explode('.', strtolower($orig));
        $ext = end($parts);
        // Rechaza extensiones ejecutables en cualquier posición (shell.php.jpg)
        foreach (array_slice($parts, 1) as $p) {
            if (preg_match('/^(php\d?|phtml|phar|pht|phps|cgi|pl|py|sh|exe|js|html?|svg|htaccess|asp|aspx|jsp)$/', $p)) {
                throw new \RuntimeException('Tipo de archivo no permitido.');
            }
        }
        if (count($parts) < 2 || !isset(self::DOCS[$ext])) {
            throw new \RuntimeException('Tipo de archivo no permitido. Usa PDF, imágenes, Word, Excel o texto.');
        }
        $mime = self::mime((string)$f['tmp_name']);
        if (!in_array($mime, self::DOCS[$ext], true)) {
            throw new \RuntimeException('El contenido del archivo no coincide con su extensión.');
        }
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && @getimagesize((string)$f['tmp_name']) === false) {
            throw new \RuntimeException('La imagen no es válida.');
        }
        if (self::looksExecutable((string)$f['tmp_name'])) {
            throw new \RuntimeException('El archivo contiene código no permitido.');
        }
        return [$ext, $mime];
    }

    /** Guarda un documento privado (validado). Devuelve el id en files o lanza \RuntimeException. */
    public static function privateDoc(array $f, string $ownerType, int $ownerId, ?int $userId = null): int
    {
        [, $mime] = self::check($f);
        $orig = (string)$f['name'];
        $dir = AUREA_ROOT . '/storage/private';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }
        $stored = Util::token(20);
        if (!move_uploaded_file((string)$f['tmp_name'], $dir . '/' . $stored) && !self::moveFallback((string)$f['tmp_name'], $dir . '/' . $stored)) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }
        return Db::insert('files', [
            'owner_type' => $ownerType, 'owner_id' => $ownerId,
            'original_name' => mb_substr(preg_replace('/[^\p{L}\p{N}._ \-()]/u', '_', basename($orig)) ?? 'archivo', 0, 180),
            'stored_name' => $stored, 'mime' => $mime, 'size' => (int)$f['size'],
            'uploaded_by' => $userId, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Imagen pública (logo, foto): se valida y se re-codifica a WebP/PNG/JPG con GD. Devuelve la ruta relativa en uploads/. */
    public static function publicImage(array $f, string $prefix, int $maxDim = 1600): string
    {
        self::basicChecks($f);
        $mime = self::mime((string)$f['tmp_name']);
        $ok = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($ok[$mime])) {
            throw new \RuntimeException('Sube una imagen JPG, PNG o WebP.');
        }
        $info = @getimagesize((string)$f['tmp_name']);
        if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40_000_000) {
            throw new \RuntimeException('La imagen no es válida o es demasiado grande.');
        }
        $dir = AUREA_ROOT . '/uploads';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new \RuntimeException('No se pudo guardar la imagen.');
        }
        $name = preg_replace('/[^a-z0-9]/', '', strtolower($prefix)) . '-' . Util::token(8);
        if (!extension_loaded('gd')) {
            // Sin GD no se puede re-codificar: se guarda el original ya validado por finfo/getimagesize
            $name .= '.' . $ok[$mime];
            if (!move_uploaded_file((string)$f['tmp_name'], $dir . '/' . $name) && !self::moveFallback((string)$f['tmp_name'], $dir . '/' . $name)) {
                throw new \RuntimeException('No se pudo guardar la imagen.');
            }
            return $name;
        }
        $src = $mime === 'image/jpeg' ? @imagecreatefromjpeg((string)$f['tmp_name'])
            : ($mime === 'image/png' ? @imagecreatefrompng((string)$f['tmp_name']) : @imagecreatefromwebp((string)$f['tmp_name']));
        if (!$src) { throw new \RuntimeException('No se pudo procesar la imagen.'); }
        $w = imagesx($src); $h = imagesy($src);
        $scale = min(1, $maxDim / max($w, $h));
        $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        if (function_exists('imagewebp')) {
            $name .= '.webp';
            $saved = @imagewebp($dst, $dir . '/' . $name, 82);
        } else {
            $name .= '.png';
            $saved = @imagepng($dst, $dir . '/' . $name, 7);
        }
        if (!$saved) { throw new \RuntimeException('No se pudo guardar la imagen.'); }
        return $name;
    }

    private static function basicChecks(array $f): void
    {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($f['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE
                ? 'El archivo excede el tamaño permitido.' : 'No se pudo recibir el archivo.');
        }
        if ((int)$f['size'] > self::maxBytes()) {
            throw new \RuntimeException('El archivo excede ' . Settings::int('upload_max_mb', 5) . ' MB.');
        }
        if ((int)$f['size'] < 1 || !is_uploaded_file((string)$f['tmp_name']) && !defined('AUREA_TESTING')) {
            throw new \RuntimeException('Archivo inválido.');
        }
    }

    private static function moveFallback(string $from, string $to): bool
    {
        // Solo en pruebas automatizadas (los archivos no llegan por HTTP real)
        return defined('AUREA_TESTING') && @rename($from, $to);
    }

    public static function mime(string $path): string
    {
        $fi = new \finfo(FILEINFO_MIME_TYPE);
        return (string)$fi->file($path);
    }

    /** Busca marcas de código ejecutable PHP dentro del archivo. */
    private static function looksExecutable(string $path): bool
    {
        $h = @fopen($path, 'rb');
        if (!$h) { return true; }
        $buf = (string)fread($h, 1048576);
        fclose($h);
        return (bool)preg_match('/<\?php|<\?=|<script\b/i', $buf);
    }

    public static function deleteFile(int $id): void
    {
        $f = Db::one('SELECT stored_name FROM files WHERE id=?', [$id]);
        if ($f) {
            @unlink(AUREA_ROOT . '/storage/private/' . $f['stored_name']);
            Db::delete('files', $id);
        }
    }
}
