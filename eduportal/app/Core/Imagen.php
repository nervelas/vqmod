<?php
declare(strict_types=1);

namespace App\Core;

/** Utilidades de imagen con GD (redimensionado e iconos PWA). */
final class Imagen
{
    public static function disponible(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    private static function abrir(string $ruta): ?\GdImage
    {
        $info = @getimagesize($ruta);
        if (!$info) {
            return null;
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($ruta),
            IMAGETYPE_PNG  => @imagecreatefrompng($ruta),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : null,
            default        => null,
        };
        return $img instanceof \GdImage ? $img : null;
    }

    public static function redimensionar(string $ruta, int $maxAncho, int $maxAlto): bool
    {
        if (!self::disponible()) {
            return false;
        }
        $src = self::abrir($ruta);
        if (!$src) {
            return false;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w <= $maxAncho && $h <= $maxAlto) {
            imagedestroy($src);
            return true;
        }
        $ratio = min($maxAncho / $w, $maxAlto / $h);
        $nw = max(1, (int)round($w * $ratio));
        $nh = max(1, (int)round($h * $ratio));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $ok = match ($ext) {
            'png'  => imagepng($dst, $ruta, 8),
            'webp' => function_exists('imagewebp') ? imagewebp($dst, $ruta, 85) : imagejpeg($dst, $ruta, 85),
            default => imagejpeg($dst, $ruta, 88),
        };
        imagedestroy($src);
        imagedestroy($dst);
        return (bool)$ok;
    }

    /**
     * Regenera los iconos PWA a partir del logo del colegio.
     * @return int cantidad de iconos generados
     */
    public static function generarIconos(string $logoRuta, string $destinoDir, string $fondo = '#0B1F3A'): int
    {
        if (!self::disponible() || !is_file($logoRuta)) {
            return 0;
        }
        $src = self::abrir($logoRuta);
        if (!$src) {
            return 0;
        }
        if (!is_dir($destinoDir)) {
            @mkdir($destinoDir, 0755, true);
        }
        [$r, $g, $b] = self::hexRgb($fondo);
        $tam = [72, 96, 128, 144, 152, 180, 192, 256, 384, 512];
        $n = 0;
        foreach ($tam as $t) {
            $canvas = imagecreatetruecolor($t, $t);
            $bg = imagecolorallocate($canvas, $r, $g, $b);
            imagefilledrectangle($canvas, 0, 0, $t, $t, $bg);
            // Zona segura maskable: el logo ocupa el 62% central.
            $inner = (int)round($t * 0.62);
            $w = imagesx($src);
            $h = imagesy($src);
            $ratio = min($inner / $w, $inner / $h);
            $nw = max(1, (int)round($w * $ratio));
            $nh = max(1, (int)round($h * $ratio));
            $x = (int)(($t - $nw) / 2);
            $y = (int)(($t - $nh) / 2);
            imagecopyresampled($canvas, $src, $x, $y, 0, 0, $nw, $nh, $w, $h);
            if (imagepng($canvas, rtrim($destinoDir, '/') . '/icon-' . $t . '.png', 8)) {
                $n++;
            }
            imagedestroy($canvas);
        }
        imagedestroy($src);
        return $n;
    }

    public static function hexRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [11, 31, 58];
        }
        return [
            (int)hexdec(substr($hex, 0, 2)),
            (int)hexdec(substr($hex, 2, 2)),
            (int)hexdec(substr($hex, 4, 2)),
        ];
    }

    /** Genera un PNG de codigo QR simple (para el carne del alumno). */
    public static function qrPng(string $texto, int $escala = 6): ?string
    {
        $m = Qr::matriz($texto);
        if ($m === null || !self::disponible()) {
            return null;
        }
        $n = count($m);
        $quiet = 4;
        $size = ($n + $quiet * 2) * $escala;
        $img = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $size, $size, $white);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($m[$y][$x]) {
                    imagefilledrectangle(
                        $img,
                        ($x + $quiet) * $escala,
                        ($y + $quiet) * $escala,
                        ($x + $quiet + 1) * $escala - 1,
                        ($y + $quiet + 1) * $escala - 1,
                        $black
                    );
                }
            }
        }
        ob_start();
        imagepng($img);
        $data = (string)ob_get_clean();
        imagedestroy($img);
        return $data;
    }
}
