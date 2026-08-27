<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Subida de archivos con validación MIME real y renombrado aleatorio.
 */
final class Subida
{
    public const IMAGENES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    public const DOCS     = ['application/pdf'];
    public const TODOS    = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/msword', 'application/vnd.ms-excel', 'text/plain',
    ];

    private const EXT = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        'application/pdf' => 'pdf', 'text/plain' => 'txt',
        'application/msword' => 'doc', 'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    public static string $ultimoError = '';

    /**
     * Guarda $_FILES[$campo] en /uploads/{$carpeta}. Devuelve el nombre generado o null.
     */
    public static function guardar(string $campo, string $carpeta, array $permitidos = self::IMAGENES, int $maxMb = 8): ?string
    {
        self::$ultimoError = '';
        if (!isset($_FILES[$campo]) || !is_array($_FILES[$campo])) {
            return null;
        }
        $f = $_FILES[$campo];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) {
            self::$ultimoError = 'No se pudo recibir el archivo (código ' . (int) $f['error'] . ').';
            return null;
        }
        if (!is_uploaded_file((string) $f['tmp_name'])) {
            self::$ultimoError = 'Archivo no válido.';
            return null;
        }
        if ((int) $f['size'] > $maxMb * 1024 * 1024) {
            self::$ultimoError = 'El archivo supera ' . $maxMb . ' MB.';
            return null;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file((string) $f['tmp_name']);
        if (!in_array($mime, $permitidos, true)) {
            self::$ultimoError = 'Tipo de archivo no permitido (' . $mime . ').';
            return null;
        }
        // Doble verificación para imágenes.
        if (str_starts_with($mime, 'image/') && @getimagesize((string) $f['tmp_name']) === false) {
            self::$ultimoError = 'La imagen está dañada.';
            return null;
        }
        $ext    = self::EXT[$mime] ?? 'bin';
        $nombre = date('Ymd') . '-' . bin2hex(random_bytes(10)) . '.' . $ext;
        $dir    = self::directorio($carpeta);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            self::$ultimoError = 'No se pudo crear la carpeta de destino.';
            return null;
        }
        if (!@move_uploaded_file((string) $f['tmp_name'], $dir . '/' . $nombre)) {
            self::$ultimoError = 'No se pudo guardar el archivo. Revise permisos de /uploads.';
            return null;
        }
        @chmod($dir . '/' . $nombre, 0644);
        if (str_starts_with($mime, 'image/')) {
            self::redimensionar($dir . '/' . $nombre, 1600);
        }
        return $nombre;
    }

    /** Guarda una imagen enviada como dataURL (cámara del dispositivo). */
    public static function guardarDataUrl(string $dataUrl, string $carpeta): ?string
    {
        self::$ultimoError = '';
        if (!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#', $dataUrl, $m)) {
            self::$ultimoError = 'Formato de imagen no válido.';
            return null;
        }
        $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($bin === false || strlen($bin) > 6 * 1024 * 1024) {
            self::$ultimoError = 'La imagen es demasiado grande.';
            return null;
        }
        $ext    = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $nombre = date('Ymd') . '-' . bin2hex(random_bytes(10)) . '.' . $ext;
        $dir    = self::directorio($carpeta);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (@file_put_contents($dir . '/' . $nombre, $bin) === false) {
            self::$ultimoError = 'No se pudo guardar la fotografía.';
            return null;
        }
        if (@getimagesize($dir . '/' . $nombre) === false) {
            @unlink($dir . '/' . $nombre);
            self::$ultimoError = 'La imagen está dañada.';
            return null;
        }
        self::redimensionar($dir . '/' . $nombre, 1024);
        return $nombre;
    }

    public static function eliminar(?string $archivo, string $carpeta): void
    {
        if (!$archivo) {
            return;
        }
        $archivo = basename($archivo);
        $ruta    = self::directorio($carpeta) . '/' . $archivo;
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    public static function directorio(string $carpeta): string
    {
        $carpeta = preg_replace('/[^a-z0-9_\-]/i', '', $carpeta) ?? '';
        return RUTA_BASE . '/uploads/' . $carpeta;
    }

    /** Reduce el lado mayor a $max px conservando proporción. */
    public static function redimensionar(string $ruta, int $max): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            return;
        }
        $info = @getimagesize($ruta);
        if ($info === false) {
            return;
        }
        [$an, $al] = $info;
        if ($an <= $max && $al <= $max) {
            return;
        }
        $escala = $max / max($an, $al);
        $nAn = (int) round($an * $escala);
        $nAl = (int) round($al * $escala);
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($ruta),
            IMAGETYPE_PNG  => @imagecreatefrompng($ruta),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : false,
            IMAGETYPE_GIF  => @imagecreatefromgif($ruta),
            default        => false,
        };
        if (!$src) {
            return;
        }
        $dst = imagecreatetruecolor($nAn, $nAl);
        if ($info[2] === IMAGETYPE_PNG || $info[2] === IMAGETYPE_WEBP) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nAn, $nAl, $an, $al);
        match ($info[2]) {
            IMAGETYPE_JPEG => imagejpeg($dst, $ruta, 86),
            IMAGETYPE_PNG  => imagepng($dst, $ruta, 6),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($dst, $ruta, 86) : imagejpeg($dst, $ruta, 86),
            IMAGETYPE_GIF  => imagegif($dst, $ruta),
            default        => null,
        };
        imagedestroy($src);
        imagedestroy($dst);
    }
}
