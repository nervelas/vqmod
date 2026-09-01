<?php
declare(strict_types=1);

/** Ajuste de imágenes al tamaño exacto pedido, sin deformar (recorte centrado). */
final class Imaging
{
    public const FORMATS = ['png', 'jpg', 'webp'];

    public static function engine(): string
    {
        if (class_exists('Imagick')) {
            return 'imagick';
        }
        if (extension_loaded('gd')) {
            return 'gd';
        }
        return 'none';
    }

    public static function available(): bool
    {
        return self::engine() !== 'none';
    }

    public static function formatSupported(string $format): bool
    {
        $format = strtolower($format);
        if (!in_array($format, self::FORMATS, true)) {
            return false;
        }
        if ($format === 'webp') {
            if (self::engine() === 'gd') {
                return function_exists('imagewebp');
            }
            if (self::engine() === 'imagick') {
                try {
                    return in_array('WEBP', Imagick::queryFormats('WEBP'), true);
                } catch (Throwable $e) {
                    return false;
                }
            }
            return false;
        }
        return true;
    }

    public static function mimeFor(string $format): string
    {
        return match (strtolower($format)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    /** Dimensiones reales de los bytes recibidos. */
    public static function dimensions(string $bytes): array
    {
        $info = @getimagesizefromstring($bytes);
        if (is_array($info) && isset($info[0], $info[1])) {
            return [(int) $info[0], (int) $info[1]];
        }
        if (class_exists('Imagick')) {
            try {
                $img = new Imagick();
                $img->readImageBlob($bytes);
                $w = (int) $img->getImageWidth();
                $h = (int) $img->getImageHeight();
                $img->clear();
                return [$w, $h];
            } catch (Throwable $e) {
                Logger::write('imaging', 'No se pudieron leer las dimensiones: ' . $e->getMessage());
            }
        }
        return [0, 0];
    }

    public static function looksLikeImage(string $bytes): bool
    {
        if (strlen($bytes) < 64) {
            return false;
        }
        [$w, $h] = self::dimensions($bytes);
        return $w > 0 && $h > 0;
    }

    /**
     * Devuelve la imagen con el tamaño exacto pedido y en el formato solicitado.
     * Escala por el lado que falta y recorta el sobrante desde el centro.
     */
    public static function toExact(string $bytes, int $width, int $height, string $format, int $quality = 92): string
    {
        $format = strtolower($format) === 'jpeg' ? 'jpg' : strtolower($format);
        if (!self::available()) {
            return $bytes; // sin GD ni Imagick se conserva el original
        }
        try {
            if (self::engine() === 'imagick') {
                return self::imagickResize($bytes, $width, $height, $format, $quality);
            }
            return self::gdResize($bytes, $width, $height, $format, $quality);
        } catch (Throwable $e) {
            Logger::write('imaging', 'Fallo al ajustar la imagen: ' . $e->getMessage());
            return $bytes;
        }
    }

    /** Miniatura para el historial (lado mayor = $max). */
    public static function thumbnail(string $bytes, int $max = 420): string
    {
        if (!self::available()) {
            return $bytes;
        }
        [$w, $h] = self::dimensions($bytes);
        if ($w <= 0 || $h <= 0) {
            return $bytes;
        }
        $scale = min(1.0, $max / max($w, $h));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));
        $format = self::formatSupported('webp') ? 'webp' : 'jpg';
        return self::toExact($bytes, $tw, $th, $format, 82);
    }

    private static function imagickResize(string $bytes, int $width, int $height, string $format, int $quality): string
    {
        $img = new Imagick();
        $img->readImageBlob($bytes);
        $img->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
        // "cover": llena el destino y recorta el sobrante desde el centro.
        $img->setImageBackgroundColor(new ImagickPixel('transparent'));
        $img->cropThumbnailImage($width, $height);
        $img->setImagePage(0, 0, 0, 0);
        if ($format === 'jpg') {
            $flat = new Imagick();
            $flat->newImage($width, $height, new ImagickPixel('white'));
            $flat->compositeImage($img, Imagick::COMPOSITE_OVER, 0, 0);
            $img->clear();
            $img = $flat;
            $img->setImageFormat('jpeg');
            $img->setImageCompressionQuality($quality);
        } elseif ($format === 'webp') {
            $img->setImageFormat('webp');
            $img->setImageCompressionQuality($quality);
        } else {
            $img->setImageFormat('png');
        }
        $img->stripImage();
        $out = $img->getImageBlob();
        $img->clear();
        return $out;
    }

    private static function gdResize(string $bytes, int $width, int $height, string $format, int $quality): string
    {
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            throw new RuntimeException('GD no pudo leer la imagen recibida.');
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($width / $sw, $height / $sh);
        $rw = max(1, (int) ceil($sw * $scale));
        $rh = max(1, (int) ceil($sh * $scale));
        $offsetX = (int) floor(($rw - $width) / 2);
        $offsetY = (int) floor(($rh - $height) / 2);

        $dst = imagecreatetruecolor($width, $height);
        if ($format === 'jpg') {
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $width, $height, $white);
        } else {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);
            imagealphablending($dst, true);
        }
        imagecopyresampled($dst, $src, -$offsetX, -$offsetY, 0, 0, $rw, $rh, $sw, $sh);
        if ($format !== 'jpg') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagedestroy($src);

        ob_start();
        if ($format === 'jpg') {
            imagejpeg($dst, null, $quality);
        } elseif ($format === 'webp' && function_exists('imagewebp')) {
            imagewebp($dst, null, $quality);
        } else {
            imagepng($dst, null, 6);
        }
        $out = (string) ob_get_clean();
        imagedestroy($dst);
        if ($out === '') {
            throw new RuntimeException('GD no produjo datos de salida.');
        }
        return $out;
    }
}
