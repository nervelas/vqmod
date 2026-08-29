<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Subida segura de imagenes: valida MIME real, recomprime (elimina metadatos
 * y cualquier cosa escondida dentro), renombra al azar y genera miniaturas.
 */
final class Image
{
    public const MAX_BYTES = 12582912; // 12 MB
    private const PERMITIDOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'jpg'];

    /**
     * @param array $file  Entrada de $_FILES
     * @param string $carpeta  Subcarpeta dentro de /storage/uploads
     * @return array{0:bool,1:string} [ok, rutaRelativa|mensajeError]
     */
    public static function upload(array $file, string $carpeta = 'productos', int $maxAncho = 1400, int $maxAlto = 1400, int $calidad = 82): array
    {
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return [false, self::errorTexto((int)($file['error'] ?? 1))];
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return [false, 'La imagen supera el máximo de 12 MB.'];
        }
        $tmp = (string)$file['tmp_name'];
        if (!is_uploaded_file($tmp) && !is_file($tmp)) {
            return [false, 'Archivo no válido.'];
        }

        // MIME real (no confiamos en la extension ni en el cliente)
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string)finfo_file($fi, $tmp);
            finfo_close($fi);
        }
        $info = @getimagesize($tmp);
        if (!$info || empty($info[0])) return [false, 'El archivo no es una imagen válida.'];
        if ($mime === '') $mime = (string)($info['mime'] ?? '');
        if (!isset(self::PERMITIDOS[$mime])) {
            return [false, 'Formato no permitido. Usa JPG, PNG o WEBP.'];
        }
        if (!function_exists('imagecreatetruecolor')) {
            return [false, 'El servidor no tiene la extensión GD activa.'];
        }

        $src = self::abrir($tmp, $mime);
        if (!$src) return [false, 'No se pudo procesar la imagen.'];

        // Corrige orientacion EXIF antes de recomprimir
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($tmp);
            $or = (int)($exif['Orientation'] ?? 0);
            if ($or === 3)      $src = imagerotate($src, 180, 0);
            elseif ($or === 6)  $src = imagerotate($src, -90, 0);
            elseif ($or === 8)  $src = imagerotate($src, 90, 0);
        }

        $dst = self::redimensionar($src, $maxAncho, $maxAlto);
        if ($dst !== $src) imagedestroy($src);

        $dir = self::dir($carpeta);
        $nombre = date('Ym') . '-' . bin2hex(random_bytes(10)) . '.jpg';
        $ruta = $dir . '/' . $nombre;

        // Siempre reescribimos como JPEG plano: al redibujar la imagen se
        // pierden los metadatos y cualquier cosa que viniera escondida.
        $bg = imagecreatetruecolor(imagesx($dst), imagesy($dst));
        imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
        imagecopy($bg, $dst, 0, 0, 0, 0, imagesx($dst), imagesy($dst));
        imagedestroy($dst);
        $ok = imagejpeg($bg, $ruta, $calidad);
        imagedestroy($bg);
        if (!$ok) return [false, 'No se pudo guardar la imagen en el servidor.'];
        @chmod($ruta, 0644);

        return [true, $carpeta . '/' . $nombre];
    }

    /** Guarda una imagen ya en memoria (para iconos PWA generados). */
    public static function saveResource($img, string $rutaAbsoluta, string $tipo = 'png'): bool
    {
        $dir = dirname($rutaAbsoluta);
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        return $tipo === 'png' ? imagepng($img, $rutaAbsoluta, 6) : imagejpeg($img, $rutaAbsoluta, 85);
    }

    private static function abrir(string $file, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg': return @imagecreatefromjpeg($file);
            case 'image/png':  return @imagecreatefrompng($file);
            case 'image/gif':  return @imagecreatefromgif($file);
            case 'image/webp': return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;
        }
        return false;
    }

    private static function redimensionar($src, int $maxW, int $maxH)
    {
        $w = imagesx($src); $h = imagesy($src);
        if ($w <= $maxW && $h <= $maxH) return $src;
        $ratio = min($maxW / $w, $maxH / $h);
        $nw = max(1, (int)round($w * $ratio));
        $nh = max(1, (int)round($h * $ratio));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }

    /** Recorte cuadrado centrado (para logos e iconos). */
    public static function square(string $rutaRelativa, int $lado = 512)
    {
        $file = self::path($rutaRelativa);
        if (!is_file($file)) return null;
        $info = @getimagesize($file);
        if (!$info) return null;
        $src = self::abrir($file, (string)$info['mime']);
        if (!$src) return null;
        $w = imagesx($src); $h = imagesy($src);
        $lado = max(16, $lado);
        $dst = imagecreatetruecolor($lado, $lado);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $trans = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $lado, $lado, $trans);
        imagealphablending($dst, true);
        $ratio = min($lado / $w, $lado / $h);
        $nw = (int)round($w * $ratio); $nh = (int)round($h * $ratio);
        imagecopyresampled($dst, $src, (int)(($lado - $nw) / 2), (int)(($lado - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        return $dst;
    }

    /** Genera el juego completo de iconos PWA a partir del logo. */
    public static function generarIconosPwa(string $logoRelativo, int $restaurantId, string $colorFondo = '#141414'): array
    {
        $tam = [72, 96, 128, 144, 152, 192, 256, 384, 512];
        $out = [];
        $base = self::dir('iconos/r' . $restaurantId);
        foreach ($tam as $s) {
            $img = self::square($logoRelativo, $s);
            if (!$img) continue;
            // Version maskable: fondo solido con margen de seguridad (safe zone 80%)
            $mask = imagecreatetruecolor($s, $s);
            [$r, $g, $b] = self::hex2rgb($colorFondo);
            imagefill($mask, 0, 0, imagecolorallocate($mask, $r, $g, $b));
            $inner = (int)round($s * 0.72);
            $img2 = self::square($logoRelativo, $inner);
            if ($img2) {
                imagealphablending($mask, true);
                imagecopy($mask, $img2, (int)(($s - $inner) / 2), (int)(($s - $inner) / 2), 0, 0, $inner, $inner);
                imagedestroy($img2);
            }
            self::saveResource($img, $base . '/icon-' . $s . '.png');
            self::saveResource($mask, $base . '/maskable-' . $s . '.png');
            imagedestroy($img);
            imagedestroy($mask);
            $out[] = $s;
        }
        return $out;
    }

    public static function hex2rgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return [20, 20, 20];
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    public static function dir(string $carpeta): string
    {
        $carpeta = preg_replace('~[^A-Za-z0-9/_-]~', '', $carpeta) ?? 'varios';
        $dir = MG_ROOT . '/storage/uploads/' . trim($carpeta, '/');
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        return $dir;
    }

    /** Ruta absoluta de un archivo subido a partir de su ruta relativa. */
    public static function path(string $rutaRelativa): string
    {
        $rel = str_replace(['..', "\0"], '', ltrim($rutaRelativa, '/'));
        return MG_ROOT . '/storage/uploads/' . $rel;
    }

    public static function delete(?string $rutaRelativa): void
    {
        if (!$rutaRelativa) return;
        $f = self::path($rutaRelativa);
        if (is_file($f) && strpos(realpath($f) ?: '', realpath(MG_ROOT . '/storage/uploads') ?: 'x') === 0) {
            @unlink($f);
        }
    }

    private static function errorTexto(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE: return 'La imagen es demasiado grande para el servidor.';
            case UPLOAD_ERR_PARTIAL:   return 'La subida se interrumpió. Intenta de nuevo.';
            case UPLOAD_ERR_NO_FILE:   return 'No se seleccionó ninguna imagen.';
            case UPLOAD_ERR_NO_TMP_DIR:return 'El servidor no tiene carpeta temporal disponible.';
            case UPLOAD_ERR_CANT_WRITE:return 'El servidor no pudo escribir el archivo.';
        }
        return 'No se pudo subir la imagen.';
    }
}
