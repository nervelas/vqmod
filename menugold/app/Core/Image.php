<?php
namespace MenuGold\Core;

/**
 * Canalización de imágenes: validación real de MIME, recompresión
 * (que elimina los metadatos), variantes responsivas en WebP con
 * respaldo JPG y marcador difuminado para el "blur-up".
 */
final class Image
{
    /** Anchos generados para srcset. */
    const WIDTHS = array(480, 960, 1600);

    /** @var array<string,string> extensiones permitidas por tipo MIME real */
    private static $allowed = array(
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    );

    public static function uploadsDir()
    {
        return MG_ROOT . '/uploads';
    }

    /**
     * Procesa un archivo de $_FILES y devuelve la ruta base relativa
     * (sin sufijo de tamaño ni extensión), o lanza una excepción.
     *
     * @return string ej. "uploads/3/platillos/lomo-a1b2c3"
     */
    public static function store(array $file, $restaurantId, $folder = 'general', $maxWidth = 1600)
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            // Permite también rutas locales durante la instalación de la demo.
            if (empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
                throw new \RuntimeException('No se recibió ningún archivo.');
            }
        }
        if (isset($file['error']) && (int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(self::uploadErrorMessage((int)$file['error']));
        }
        $maxBytes = (int)Config::get('uploads.max_bytes', 8 * 1024 * 1024);
        if (filesize($file['tmp_name']) > $maxBytes) {
            throw new \RuntimeException('La imagen supera el tamaño máximo de ' . round($maxBytes / 1048576) . ' MB.');
        }

        $mime = self::detectMime($file['tmp_name']);
        if (!isset(self::$allowed[$mime])) {
            throw new \RuntimeException('Formato no permitido. Usa JPG, PNG o WebP.');
        }

        $src = self::openImage($file['tmp_name'], $mime);
        if (!$src) {
            throw new \RuntimeException('La imagen está dañada o no se pudo leer.');
        }

        $base = self::relativeBase($restaurantId, $folder, isset($file['name']) ? $file['name'] : 'imagen');
        self::writeVariants($src, $base, $maxWidth);
        imagedestroy($src);
        return $base;
    }

    /** Igual que store(), pero a partir de un archivo ya presente en disco. */
    public static function storePath($path, $restaurantId, $folder = 'general', $maxWidth = 1600, $originalName = null)
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Archivo no encontrado: ' . $path);
        }
        $mime = self::detectMime($path);
        if (!isset(self::$allowed[$mime])) {
            throw new \RuntimeException('Formato no permitido: ' . $mime);
        }
        $src = self::openImage($path, $mime);
        if (!$src) {
            throw new \RuntimeException('No se pudo leer la imagen.');
        }
        $base = self::relativeBase($restaurantId, $folder, $originalName !== null ? $originalName : basename($path));
        self::writeVariants($src, $base, $maxWidth);
        imagedestroy($src);
        return $base;
    }

    private static function relativeBase($restaurantId, $folder, $originalName)
    {
        $folder = preg_replace('/[^a-z0-9\-]/', '', strtolower($folder));
        if ($folder === '') { $folder = 'general'; }
        $name = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $name = substr($name, 0, 40) . '-' . substr(bin2hex(random_bytes(5)), 0, 8);
        $rel  = 'uploads/' . (int)$restaurantId . '/' . $folder;
        $abs  = MG_ROOT . '/' . $rel;
        if (!is_dir($abs) && !@mkdir($abs, 0755, true) && !is_dir($abs)) {
            throw new \RuntimeException('No se pudo crear la carpeta de imágenes. Revisa permisos en /uploads.');
        }
        return $rel . '/' . $name;
    }

    /** Genera las variantes y el marcador difuminado. */
    private static function writeVariants($src, $relBase, $maxWidth)
    {
        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $widths = array();
        foreach (self::WIDTHS as $w) {
            if ($w <= $maxWidth) { $widths[] = $w; }
        }
        if (!$widths) { $widths = array($maxWidth); }

        foreach ($widths as $w) {
            $targetW = min($w, $srcW);
            $targetH = (int)max(1, round($srcH * ($targetW / $srcW)));
            $dst = self::resample($src, $targetW, $targetH);
            $path = MG_ROOT . '/' . $relBase . '-' . $w;
            if (function_exists('imagewebp')) {
                @imagewebp($dst, $path . '.webp', 82);
            }
            imagejpeg($dst, $path . '.jpg', 82);
            imagedestroy($dst);
        }

        // LQIP: miniatura de 24 px guardada como data URI en un archivo .txt
        $tiny = self::resample($src, 24, (int)max(1, round($srcH * (24 / $srcW))));
        ob_start();
        imagejpeg($tiny, null, 40);
        $data = ob_get_clean();
        imagedestroy($tiny);
        @file_put_contents(MG_ROOT . '/' . $relBase . '.lqip.txt', 'data:image/jpeg;base64,' . base64_encode($data));
    }

    private static function resample($src, $w, $h)
    {
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $w, $h, $white);
        imagealphablending($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        return $dst;
    }

    private static function openImage($path, $mime)
    {
        switch ($mime) {
            case 'image/jpeg': $im = @imagecreatefromjpeg($path); break;
            case 'image/png':  $im = @imagecreatefrompng($path);  break;
            case 'image/gif':  $im = @imagecreatefromgif($path);  break;
            case 'image/webp': $im = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false; break;
            default: return false;
        }
        if (!$im) { return false; }
        // Corrige la orientación EXIF antes de descartar los metadatos.
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            if (!empty($exif['Orientation'])) {
                switch ((int)$exif['Orientation']) {
                    case 3: $im = imagerotate($im, 180, 0); break;
                    case 6: $im = imagerotate($im, -90, 0); break;
                    case 8: $im = imagerotate($im, 90, 0);  break;
                }
            }
        }
        return $im;
    }

    public static function detectMime($path)
    {
        $info = @getimagesize($path);
        if (is_array($info) && !empty($info['mime'])) {
            return strtolower($info['mime']);
        }
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            $m = finfo_file($f, $path);
            finfo_close($f);
            return strtolower((string)$m);
        }
        return 'application/octet-stream';
    }

    /** Borra todas las variantes de una imagen. */
    public static function remove($relBase)
    {
        if (!$relBase || strpos($relBase, 'uploads/') !== 0 || strpos($relBase, '..') !== false) {
            return;
        }
        foreach (self::WIDTHS as $w) {
            @unlink(MG_ROOT . '/' . $relBase . '-' . $w . '.webp');
            @unlink(MG_ROOT . '/' . $relBase . '-' . $w . '.jpg');
        }
        @unlink(MG_ROOT . '/' . $relBase . '.lqip.txt');
    }

    public static function lqip($relBase)
    {
        if (!$relBase) { return ''; }
        $f = MG_ROOT . '/' . $relBase . '.lqip.txt';
        return is_file($f) ? (string)file_get_contents($f) : '';
    }

    public static function exists($relBase)
    {
        return $relBase && is_file(MG_ROOT . '/' . $relBase . '-480.jpg');
    }

    /** Ruta absoluta al archivo de un ancho concreto (o el mayor disponible). */
    public static function file($relBase, $width = 960)
    {
        $f = MG_ROOT . '/' . $relBase . '-' . $width . '.jpg';
        if (is_file($f)) { return $f; }
        foreach (array_reverse(self::WIDTHS) as $w) {
            $f = MG_ROOT . '/' . $relBase . '-' . $w . '.jpg';
            if (is_file($f)) { return $f; }
        }
        return null;
    }

    /** Genera los iconos PWA (incluidos maskable) a partir del logo. */
    public static function generatePwaIcons($relBase, $restaurantId, $bg = '#0C0B09')
    {
        $source = self::file($relBase, 960);
        if (!$source) { return false; }
        $src = @imagecreatefromjpeg($source);
        if (!$src) { return false; }
        $dir = MG_ROOT . '/uploads/' . (int)$restaurantId . '/icons';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) { return false; }
        list($r, $g, $b) = self::hexToRgb($bg);
        foreach (array(72, 96, 128, 144, 152, 192, 384, 512) as $size) {
            $canvas = imagecreatetruecolor($size, $size);
            imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocate($canvas, $r, $g, $b));
            // 80 % de área segura: el logo nunca se recorta en iconos maskable.
            $inner = (int)round($size * 0.62);
            $ratio = imagesx($src) / imagesy($src);
            $w = $ratio >= 1 ? $inner : (int)round($inner * $ratio);
            $h = $ratio >= 1 ? (int)round($inner / $ratio) : $inner;
            imagecopyresampled($canvas, $src, (int)(($size - $w) / 2), (int)(($size - $h) / 2), 0, 0, $w, $h, imagesx($src), imagesy($src));
            imagepng($canvas, $dir . '/icon-' . $size . '.png', 8);
            imagedestroy($canvas);
        }
        imagedestroy($src);
        return true;
    }

    public static function hexToRgb($hex)
    {
        $hex = ltrim((string)$hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) { return array(12, 11, 9); }
        return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    private static function uploadErrorMessage($code)
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE: return 'El archivo es demasiado grande para el servidor.';
            case UPLOAD_ERR_PARTIAL:   return 'La subida se interrumpió. Intenta de nuevo.';
            case UPLOAD_ERR_NO_FILE:   return 'No seleccionaste ningún archivo.';
            default:                   return 'No se pudo subir la imagen (código ' . $code . ').';
        }
    }
}
