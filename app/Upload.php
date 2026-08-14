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

    /**
     * Handle an uploaded image for a given subfolder (e.g. 'leagues').
     * Returns the web-relative path (e.g. 'uploads/leagues/ab12.jpg') or null.
     *
     * @throws RuntimeException on validation failure.
     */
    public static function image(string $field, string $subfolder): ?string
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

        return 'uploads/' . $subfolder . '/' . $name;
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
