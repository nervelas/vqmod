<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Procesado de imágenes con GD: recompresión (elimina metadatos EXIF),
 * variantes WebP + JPEG de respaldo y miniatura con blur-up en base64.
 */
final class Img
{
    public static function hasGd(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagejpeg');
    }

    public static function hasWebp(): bool
    {
        return function_exists('imagewebp');
    }

    /** @return \GdImage|null */
    public static function load(string $file): ?\GdImage
    {
        $info = @getimagesize($file);
        if (!$info) {
            return null;
        }
        $im = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG  => @imagecreatefrompng($file),
            IMAGETYPE_GIF  => @imagecreatefromgif($file),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : null,
            default        => null,
        };
        if (!$im) {
            return null;
        }
        // Corrige la orientación EXIF antes de descartar los metadatos.
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file);
            $o = (int) ($exif['Orientation'] ?? 1);
            if (in_array($o, [3, 6, 8], true)) {
                $deg = $o === 3 ? 180 : ($o === 6 ? -90 : 90);
                $rot = @imagerotate($im, $deg, 0);
                if ($rot) {
                    imagedestroy($im);
                    $im = $rot;
                }
            }
        }
        return $im;
    }

    public static function resize(\GdImage $src, int $maxW, int $maxH, bool $cover = false): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        if ($cover) {
            $scale = max($maxW / $w, $maxH / $h);
            $nw = (int) ceil($w * $scale);
            $nh = (int) ceil($h * $scale);
            $dst = imagecreatetruecolor($maxW, $maxH);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $tmp = imagecreatetruecolor($nw, $nh);
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            imagecopyresampled($tmp, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagecopy($dst, $tmp, 0, 0, (int) (($nw - $maxW) / 2), (int) (($nh - $maxH) / 2), $maxW, $maxH);
            imagedestroy($tmp);
            return $dst;
        }
        $scale = min(1.0, min($maxW / $w, $maxH / $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }

    /** Aplana la transparencia sobre un fondo claro (fichas de catálogo). */
    public static function flatten(\GdImage $im, array $rgb = [245, 246, 244]): \GdImage
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $dst = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($dst, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($dst, 0, 0, $w, $h, $bg);
        imagealphablending($dst, true);
        imagecopy($dst, $im, 0, 0, 0, 0, $w, $h);
        return $dst;
    }

    /**
     * Guarda una imagen subida en sus variantes.
     * @return array{path:string,path_webp:?string,path_thumb:?string,width:int,height:int,blur:string}|null
     */
    public static function store(string $srcFile, string $destDir, string $basename, int $maxW = 1600, int $maxH = 1600): ?array
    {
        if (!self::hasGd()) {
            return null;
        }
        $im = self::load($srcFile);
        if (!$im) {
            return null;
        }
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
        $big = self::resize($im, $maxW, $maxH);
        $flat = self::flatten($big);
        imagedestroy($big);

        $jpg = $destDir . '/' . $basename . '.jpg';
        imagejpeg($flat, $jpg, 82);
        $webp = null;
        if (self::hasWebp()) {
            $webp = $destDir . '/' . $basename . '.webp';
            imagewebp($flat, $webp, 80);
        }
        $thumbIm = self::resize($flat, 600, 600);
        $thumb = $destDir . '/' . $basename . '-t.' . (self::hasWebp() ? 'webp' : 'jpg');
        if (self::hasWebp()) {
            imagewebp($thumbIm, $thumb, 76);
        } else {
            imagejpeg($thumbIm, $thumb, 78);
        }

        $blur = self::blurData($flat);

        $w = imagesx($flat);
        $h = imagesy($flat);
        imagedestroy($flat);
        imagedestroy($thumbIm);
        imagedestroy($im);

        return [
            'path'       => self::rel($jpg),
            'path_webp'  => $webp ? self::rel($webp) : null,
            'path_thumb' => self::rel($thumb),
            'width'      => $w,
            'height'     => $h,
            'blur'       => $blur,
        ];
    }

    /** Miniatura diminuta en data-URI para el efecto blur-up. */
    public static function blurData(\GdImage $im): string
    {
        $tiny = self::resize($im, 20, 20);
        ob_start();
        imagejpeg($tiny, null, 40);
        $data = (string) ob_get_clean();
        imagedestroy($tiny);
        return 'data:image/jpeg;base64,' . base64_encode($data);
    }

    private static function rel(string $abs): string
    {
        $base = STORAGE_PATH . '/uploads/';
        return str_starts_with($abs, $base) ? substr($abs, strlen($base)) : basename($abs);
    }

    /** Genera el juego de iconos PWA a partir del logo de la empresa. */
    public static function pwaIcons(string $srcFile, string $destDir, array $sizes = [72, 96, 128, 144, 152, 192, 384, 512], string $bg = '#1C1F22'): int
    {
        if (!self::hasGd()) {
            return 0;
        }
        $im = self::load($srcFile);
        if (!$im) {
            return 0;
        }
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
        [$r, $g, $b] = self::hex2rgb($bg);
        $made = 0;
        foreach ($sizes as $s) {
            $canvas = imagecreatetruecolor($s, $s);
            $c = imagecolorallocate($canvas, $r, $g, $b);
            imagefilledrectangle($canvas, 0, 0, $s, $s, $c);
            // Zona segura del icono maskable: el arte ocupa el 62% central.
            $inner = (int) round($s * 0.62);
            $logo = self::resize($im, $inner, $inner);
            $lw = imagesx($logo);
            $lh = imagesy($logo);
            imagealphablending($canvas, true);
            imagecopy($canvas, $logo, (int) (($s - $lw) / 2), (int) (($s - $lh) / 2), 0, 0, $lw, $lh);
            imagepng($canvas, $destDir . '/icon-' . $s . '.png', 7);
            imagedestroy($logo);
            imagedestroy($canvas);
            $made++;
        }
        imagedestroy($im);
        return $made;
    }

    public static function hex2rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return [28, 31, 34];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
