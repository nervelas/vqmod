<?php
/**
 * Secure image upload handling.
 *
 * - Accepts only JPG/JPEG/PNG/WEBP.
 * - Validates real MIME (finfo + getimagesize), extension, size, dimensions.
 * - Rejects executable/dangerous content.
 * - Renames files randomly.
 * - Never allows PHP execution inside the uploads directory (see .htaccess).
 */
class Upload
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    private const MAX_BYTES = 6291456; // 6 MB
    private const MAX_DIM   = 4000;

    /** Fixed banner canvas (all banners are normalised to this exact size). */
    public const BANNER_W = 2172;
    public const BANNER_H = 724;

    /**
     * Handle an uploaded image for a given subfolder (e.g. 'leagues').
     * Returns the web-relative path (e.g. 'uploads/leagues/ab12.jpg') or null.
     *
     * When $fit is [w, h] the stored image is normalised to exactly those
     * pixels using a centred cover-crop (fills the frame, never distorts).
     * Used so every banner ends up at the same dimensions (2172x724).
     *
     * @throws RuntimeException on validation failure.
     */
    public static function image(string $field, string $subfolder, ?array $fit = null): ?string
    {
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $file = $_FILES[$field];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error al subir el archivo (código ' . $file['error'] . ').');
        }
        if ($file['size'] <= 0 || $file['size'] > self::MAX_BYTES) {
            throw new RuntimeException('El archivo supera el tamaño máximo permitido (6 MB).');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Origen de archivo inválido.');
        }

        // Real MIME type via finfo.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Formato no permitido. Use JPG, PNG o WEBP.');
        }

        // Must be a real, decodable image with sane dimensions.
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            throw new RuntimeException('El archivo no es una imagen válida.');
        }
        [$w, $h] = $info;
        if ($w < 1 || $h < 1 || $w > self::MAX_DIM || $h > self::MAX_DIM) {
            throw new RuntimeException('Dimensiones de imagen no válidas (máx. 4000px).');
        }
        // getimagesize MIME must agree with finfo (blocks polyglot files).
        if (($info['mime'] ?? '') !== $mime) {
            throw new RuntimeException('El tipo de imagen no coincide con su contenido.');
        }

        // Scan the head of the file for embedded script markers.
        $head = (string)file_get_contents($file['tmp_name'], false, null, 0, 4096);
        if (preg_match('/<\?php|<\?=|<script|shell_exec|passthru|base64_decode\s*\(/i', $head)) {
            throw new RuntimeException('El archivo contiene contenido no permitido.');
        }

        $ext  = self::ALLOWED[$mime];
        $dir  = self::baseDir() . '/' . $subfolder;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('No se pudo guardar el archivo.');
        }
        @chmod($dest, 0644);

        // Normalise banners to an exact canvas (e.g. 2172x724) with a cover-crop.
        if ($fit && is_array($fit) && count($fit) === 2 && function_exists('imagecreatetruecolor')) {
            self::coverResize($dest, $mime, (int)$fit[0], (int)$fit[1]);
        }

        return 'uploads/' . $subfolder . '/' . $name;
    }

    /**
     * Resize $path in place to exactly {$tw}x{$th} using a centred cover-crop:
     * the source is scaled to fully cover the frame, then cropped — so the
     * output is always the requested size and never stretched. Preserves the
     * file's format (alpha kept for PNG/WEBP). Best-effort: on any failure the
     * original file is left untouched.
     */
    private static function coverResize(string $path, string $mime, int $tw, int $th): void
    {
        if ($tw < 1 || $th < 1) { return; }
        try {
            switch ($mime) {
                case 'image/jpeg': $src = @imagecreatefromjpeg($path); break;
                case 'image/png':  $src = @imagecreatefrompng($path);  break;
                case 'image/webp': $src = @imagecreatefromwebp($path); break;
                default: return;
            }
            if (!$src) { return; }
            $sw = imagesx($src); $sh = imagesy($src);
            if ($sw < 1 || $sh < 1) { imagedestroy($src); return; }

            // Scale to cover, then centre-crop the overflow.
            $scale = max($tw / $sw, $th / $sh);
            $cropW = (int)round($tw / $scale);
            $cropH = (int)round($th / $scale);
            $srcX  = (int)max(0, floor(($sw - $cropW) / 2));
            $srcY  = (int)max(0, floor(($sh - $cropH) / 2));

            $dst = imagecreatetruecolor($tw, $th);
            if ($mime === 'image/png' || $mime === 'image/webp') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            }
            imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $tw, $th, $cropW, $cropH);

            switch ($mime) {
                case 'image/jpeg': imagejpeg($dst, $path, 88); break;
                case 'image/png':  imagepng($dst, $path, 6);   break;
                case 'image/webp': imagewebp($dst, $path, 88); break;
            }
            imagedestroy($src);
            imagedestroy($dst);
        } catch (Throwable $e) {
            // Leave the original file in place on any GD failure.
        }
    }

    /** Delete a previously stored upload (safe, scoped to uploads dir). */
    public static function delete(?string $relPath): void
    {
        if (empty($relPath) || strpos($relPath, 'uploads/') !== 0) {
            return;
        }
        $real = realpath(self::rootDir() . '/' . $relPath);
        $base = realpath(self::baseDir());
        if ($real && $base && strpos($real, $base) === 0 && is_file($real)) {
            @unlink($real);
        }
    }

    private static function rootDir(): string
    {
        return dirname(__DIR__);
    }

    private static function baseDir(): string
    {
        return self::rootDir() . '/uploads';
    }
}
