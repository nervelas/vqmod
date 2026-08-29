<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Subidas seguras: valida el MIME real con finfo, renombra al azar y
 * recomprime las imágenes (lo que elimina cualquier carga incrustada).
 */
final class Uploader
{
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    public const PDF_MIMES   = ['application/pdf'];
    public const SHEET_MIMES = [
        'text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream',
    ];

    public static function baseDir(int $companyId, string $kind): string
    {
        $dir = STORAGE_PATH . '/uploads/e' . $companyId . '/' . preg_replace('/[^a-z0-9]/', '', $kind);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public static function mimeOf(string $file): string
    {
        if (!function_exists('finfo_open')) {
            return (string) (@mime_content_type($file) ?: 'application/octet-stream');
        }
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $m = $fi ? (string) finfo_file($fi, $file) : 'application/octet-stream';
        if ($fi) {
            finfo_close($fi);
        }
        return $m;
    }

    public static function randomName(string $prefix = ''): string
    {
        return ($prefix !== '' ? preg_replace('/[^a-z0-9\-]/i', '', $prefix) . '-' : '') . bin2hex(random_bytes(10));
    }

    /** Normaliza $_FILES para campos simples y múltiples. */
    public static function files(string $field): array
    {
        if (!isset($_FILES[$field])) {
            return [];
        }
        $f = $_FILES[$field];
        if (!is_array($f['name'])) {
            return $f['error'] === UPLOAD_ERR_OK ? [$f] : [];
        }
        $out = [];
        foreach ($f['name'] as $i => $name) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $out[] = [
                'name'     => $name,
                'type'     => $f['type'][$i],
                'tmp_name' => $f['tmp_name'][$i],
                'error'    => $f['error'][$i],
                'size'     => $f['size'][$i],
            ];
        }
        return $out;
    }

    /**
     * Guarda una imagen validada y recomprimida.
     * @return array|null datos para product_images o null si es inválida
     */
    public static function image(array $file, int $companyId, string $kind = 'productos', int $maxW = 1600, int $maxH = 1600, int $maxBytes = 8388608): ?array
    {
        if (!is_uploaded_file($file['tmp_name']) && !is_file($file['tmp_name'])) {
            return null;
        }
        if ((int) $file['size'] > $maxBytes) {
            return null;
        }
        if (!in_array(self::mimeOf($file['tmp_name']), self::IMAGE_MIMES, true)) {
            return null;
        }
        if (@getimagesize($file['tmp_name']) === false) {
            return null;
        }
        $dir  = self::baseDir($companyId, $kind);
        $name = self::randomName();
        $res  = Img::store($file['tmp_name'], $dir, $name, $maxW, $maxH);
        if (!$res) {
            return null;
        }
        $res['alt'] = mb_substr(pathinfo((string) $file['name'], PATHINFO_FILENAME), 0, 180);
        return $res;
    }

    /** Guarda un PDF validado. */
    public static function pdf(array $file, int $companyId, string $kind = 'documentos', int $maxBytes = 15728640): ?array
    {
        if ((int) $file['size'] > $maxBytes) {
            return null;
        }
        if (!in_array(self::mimeOf($file['tmp_name']), self::PDF_MIMES, true)) {
            return null;
        }
        $fh = @fopen($file['tmp_name'], 'rb');
        $head = $fh ? (string) fread($fh, 5) : '';
        if ($fh) {
            fclose($fh);
        }
        if ($head !== '%PDF-') {
            return null;
        }
        $dir  = self::baseDir($companyId, $kind);
        $name = self::randomName() . '.pdf';
        if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $name) && !@copy($file['tmp_name'], $dir . '/' . $name)) {
            return null;
        }
        @chmod($dir . '/' . $name, 0644);
        return [
            'name' => mb_substr((string) $file['name'], 0, 160),
            'path' => 'e' . $companyId . '/' . preg_replace('/[^a-z0-9]/', '', $kind) . '/' . $name,
            'size' => (int) $file['size'],
        ];
    }

    /** Guarda un CSV/XLSX en /storage/tmp para procesarlo. */
    public static function sheet(array $file, int $maxBytes = 20971520): ?string
    {
        if ((int) $file['size'] > $maxBytes || (int) $file['size'] === 0) {
            return null;
        }
        $mime = self::mimeOf($file['tmp_name']);
        if (!in_array($mime, self::SHEET_MIMES, true)) {
            return null;
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt', 'xlsx'], true)) {
            return null;
        }
        $dir = STORAGE_PATH . '/tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $dest = $dir . '/' . self::randomName('imp') . '.' . $ext;
        if (!@move_uploaded_file($file['tmp_name'], $dest) && !@copy($file['tmp_name'], $dest)) {
            return null;
        }
        @chmod($dest, 0600);
        return $dest;
    }

    /** Borra los archivos derivados de una imagen. */
    public static function deleteImage(array $img): void
    {
        foreach (['path', 'path_webp', 'path_thumb'] as $k) {
            $p = (string) ($img[$k] ?? '');
            if ($p === '') {
                continue;
            }
            $abs = STORAGE_PATH . '/uploads/' . ltrim($p, '/');
            if (is_file($abs) && str_starts_with(realpath($abs) ?: '', realpath(STORAGE_PATH . '/uploads') ?: 'x')) {
                @unlink($abs);
            }
        }
    }
}
