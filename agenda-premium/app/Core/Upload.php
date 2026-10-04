<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Subida segura de archivos: lista blanca de extensiones, verificación de MIME con finfo,
 * renombrado aleatorio, almacenamiento fuera del alcance web (storage/uploads) y entrega por script autorizado (/f/{token}).
 * Las imágenes se re-codifican con GD (elimina cargas ocultas) cuando GD está disponible.
 */
final class Upload
{
    /** extensión => MIME real permitido */
    public const ALLOWED = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ];
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * @param array $file entrada de $_FILES
     * @param array $opts kind, max_bytes, images_only, is_public, owner_type, owner_id, uploaded_by
     * @return array fila de files (id, token, mime, size, original_name...)
     * @throws \RuntimeException con mensaje amable en español
     */
    public static function store(array $file, array $opts = []): array
    {
        $max = (int) ($opts['max_bytes'] ?? 5 * 1024 * 1024);
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new \RuntimeException('No pudimos recibir el archivo. Inténtalo de nuevo.');
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new \RuntimeException('El archivo es demasiado grande.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            throw new \RuntimeException('No pudimos recibir el archivo. Inténtalo de nuevo.');
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp) && empty($opts['allow_local'])) {
            throw new \RuntimeException('El archivo no es válido.');
        }
        $size = (int) filesize($tmp);
        if ($size <= 0 || $size > $max) {
            throw new \RuntimeException('El archivo supera el tamaño permitido (' . round($max / 1048576, 1) . ' MB).');
        }
        $orig = Str::clean(basename((string) ($file['name'] ?? 'archivo')), 200);
        // Doble extensión o ejecutables: rechazo total (archivo.php.jpg, .phtml, etc.)
        $lower = strtolower($orig);
        if (preg_match('/\.(php\d?|phtml|phar|pht|phps|cgi|pl|py|sh|exe|js|html?|htaccess|asp|aspx|jsp|svg)(\.|$)/', $lower)) {
            throw new \RuntimeException('Ese tipo de archivo no está permitido.');
        }
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            throw new \RuntimeException('Formato no permitido. Usa imágenes (JPG, PNG, WebP), PDF o documentos de oficina.');
        }
        $fi = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $fi->file($tmp);
        if (!in_array($mime, self::ALLOWED[$ext], true)) {
            throw new \RuntimeException('El contenido del archivo no coincide con su extensión.');
        }
        $isImage = in_array($mime, self::IMAGE_MIMES, true);
        if (!empty($opts['images_only']) && !$isImage) {
            throw new \RuntimeException('Solo se permiten imágenes (JPG, PNG o WebP).');
        }
        if ($isImage && @getimagesize($tmp) === false) {
            throw new \RuntimeException('La imagen no es válida.');
        }

        $token = Str::token(16);
        $stored = Str::token(20);
        $dir = APP_ROOT . '/storage/uploads/' . gmdate('Y') . '/' . gmdate('m');
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            throw new \RuntimeException('No pudimos guardar el archivo (permisos de carpeta).');
        }
        $rel = gmdate('Y') . '/' . gmdate('m') . '/' . $stored;
        $dest = APP_ROOT . '/storage/uploads/' . $rel;

        $finalMime = $mime;
        $finalSize = $size;
        if ($isImage && function_exists('imagecreatefromstring') && $mime !== 'image/gif') {
            $img = @imagecreatefromstring((string) file_get_contents($tmp));
            if ($img === false) {
                throw new \RuntimeException('La imagen no es válida.');
            }
            $maxSide = (int) ($opts['max_side'] ?? 2000);
            $w = imagesx($img);
            $h = imagesy($img);
            if (max($w, $h) > $maxSide) {
                $ratio = $maxSide / max($w, $h);
                $nw = max(1, (int) round($w * $ratio));
                $nh = max(1, (int) round($h * $ratio));
                $res = imagecreatetruecolor($nw, $nh);
                imagealphablending($res, false);
                imagesavealpha($res, true);
                imagecopyresampled($res, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img);
                $img = $res;
            }
            $ok = false;
            if (function_exists('imagewebp') && ($opts['to_webp'] ?? true)) {
                imagesavealpha($img, true);
                $ok = @imagewebp($img, $dest, 82);
                $finalMime = 'image/webp';
            }
            if (!$ok) {
                if ($mime === 'image/png') {
                    $ok = @imagepng($img, $dest, 6);
                    $finalMime = 'image/png';
                } else {
                    $ok = @imagejpeg($img, $dest, 85);
                    $finalMime = 'image/jpeg';
                }
            }
            imagedestroy($img);
            if (!$ok) {
                throw new \RuntimeException('No pudimos procesar la imagen.');
            }
            $finalSize = (int) filesize($dest);
        } else {
            $moved = is_uploaded_file($tmp) ? @move_uploaded_file($tmp, $dest) : @copy($tmp, $dest);
            if (!$moved) {
                throw new \RuntimeException('No pudimos guardar el archivo.');
            }
        }
        @chmod($dest, 0640);

        $id = Db::insert('files', [
            'token' => $token,
            'original_name' => $orig,
            'stored_name' => $rel,
            'mime' => $finalMime,
            'size' => $finalSize,
            'kind' => (string) ($opts['kind'] ?? 'attachment'),
            'is_public' => !empty($opts['is_public']) ? 1 : 0,
            'owner_type' => $opts['owner_type'] ?? null,
            'owner_id' => $opts['owner_id'] ?? null,
            'uploaded_by' => $opts['uploaded_by'] ?? null,
            'created_at' => Clock::utc(),
        ]);
        return (array) Db::one('SELECT * FROM files WHERE id = ?', [$id]);
    }

    public static function path(array $fileRow): string
    {
        return APP_ROOT . '/storage/uploads/' . $fileRow['stored_name'];
    }

    public static function url(?int $fileId): string
    {
        if (!$fileId) {
            return '';
        }
        $t = Db::val('SELECT token FROM files WHERE id = ? AND is_public = 1', [$fileId]);
        return $t ? url('/f/' . $t) : '';
    }

    public static function delete(int $fileId): void
    {
        $row = Db::one('SELECT * FROM files WHERE id = ?', [$fileId]);
        if ($row) {
            $p = self::path($row);
            if (is_file($p)) {
                @unlink($p);
            }
            Db::delete('files', 'id = ?', [$fileId]);
        }
    }
}
