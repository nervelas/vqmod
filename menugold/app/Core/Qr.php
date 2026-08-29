<?php
namespace MenuGold\Core;

/** Envoltura sobre phpqrcode con caché en /storage/cache/qr. */
final class Qr
{
    private static $booted = false;

    private static function boot()
    {
        if (self::$booted) { return; }
        $cache = MG_STORAGE . '/cache/qr';
        if (!is_dir($cache)) { @mkdir($cache, 0755, true); }
        if (!defined('QR_CACHE_DIR')) { define('QR_CACHE_DIR', $cache . '/'); }
        if (!defined('QR_LOG_DIR'))   { define('QR_LOG_DIR', MG_STORAGE . '/logs/'); }
        require_once MG_ROOT . '/vendor/phpqrcode/qrlib.php';
        self::$booted = true;
    }

    /** Matriz del código: filas de caracteres '1' y '0'. */
    public static function matrix($text, $level = 'M')
    {
        self::boot();
        $levels = array('L' => QR_ECLEVEL_L, 'M' => QR_ECLEVEL_M, 'Q' => QR_ECLEVEL_Q, 'H' => QR_ECLEVEL_H);
        $l = isset($levels[$level]) ? $levels[$level] : QR_ECLEVEL_M;
        return \QRcode::text((string)$text, false, $l);
    }

    /**
     * PNG del código, en negro sobre blanco (lo que exigen los lectores).
     * @return string datos binarios PNG
     */
    public static function png($text, $cellSize = 8, $quietZone = 3, $dark = '#000000', $light = '#FFFFFF')
    {
        $matrix = self::matrix($text);
        $n = count($matrix);
        if ($n === 0) { return ''; }
        $cellSize = max(2, min(24, (int)$cellSize));
        $size = ($n + $quietZone * 2) * $cellSize;

        $im = imagecreatetruecolor($size, $size);
        list($lr, $lg, $lb) = Image::hexToRgb($light);
        list($dr, $dg, $db) = Image::hexToRgb($dark);
        $bg = imagecolorallocate($im, $lr, $lg, $lb);
        $fg = imagecolorallocate($im, $dr, $dg, $db);
        imagefilledrectangle($im, 0, 0, $size, $size, $bg);
        for ($row = 0; $row < $n; $row++) {
            for ($col = 0; $col < $n; $col++) {
                if ($matrix[$row][$col] !== '1') { continue; }
                $x = ($quietZone + $col) * $cellSize;
                $y = ($quietZone + $row) * $cellSize;
                imagefilledrectangle($im, $x, $y, $x + $cellSize - 1, $y + $cellSize - 1, $fg);
            }
        }
        ob_start();
        imagepng($im, null, 9);
        $data = ob_get_clean();
        imagedestroy($im);
        return $data;
    }

    /** PNG en base64 listo para usar en <img src>. */
    public static function dataUri($text, $cellSize = 6)
    {
        $png = self::png($text, $cellSize);
        return $png === '' ? '' : 'data:image/png;base64,' . base64_encode($png);
    }
}
