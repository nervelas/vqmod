<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

const GX_IMG_MAX_BYTES = 8388608;      // 8 MB
const GX_IMG_MAX_SIDE = 1920;          // lado mayor máximo del archivo principal
const GX_IMG_THUMB_SIDE = 640;

/**
 * Valida y reprocesa una imagen subida. Devuelve ['file','thumb','w','h','tw','th'] o un string con el error.
 * Pasos: tamaño, extensión (jpg/png/webp, sin extensiones peligrosas intermedias), MIME real (finfo),
 * firma de bytes, dimensiones, recodificación completa con GD (destruye cualquier dato incrustado),
 * nombre aleatorio y verificación del resultado.
 *
 * @return array<string,mixed>|string
 */
function gx_process_image(string $tmpPath, string $origName, string $destDir, ?string $baseName = null)
{
    if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')) {
        return 'Este servidor no tiene soporte WEBP en GD. Pida a su hosting activar PHP-GD con WebP.';
    }
    if (!is_file($tmpPath) || !is_readable($tmpPath)) {
        return 'Archivo no válido.';
    }
    $size = filesize($tmpPath);
    if ($size === false || $size < 100 || $size > GX_IMG_MAX_BYTES) {
        return 'La imagen debe pesar entre 100 bytes y 8 MB.';
    }

    // 1) extensión: solo jpg/jpeg/png/webp y ningún segmento peligroso en el nombre
    $name = strtolower(basename(str_replace('\\', '/', $origName)));
    $segs = explode('.', $name);
    $ext = array_pop($segs);
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return 'Formato no permitido. Use JPG, PNG o WEBP.';
    }
    foreach ($segs as $s) {
        if (preg_match('/^(php\d?|phtml|phar|pht|phps|cgi|pl|py|sh|bash|asp|aspx|jsp|exe|js|html?|htaccess|ini)$/', $s)) {
            return 'Nombre de archivo no permitido.';
        }
    }

    // 2) MIME real y firma
    $head = (string)file_get_contents($tmpPath, false, null, 0, 16);
    $kind = '';
    if (strncmp($head, "\xFF\xD8\xFF", 3) === 0) {
        $kind = 'image/jpeg';
    } elseif (strncmp($head, "\x89PNG\r\n\x1A\n", 8) === 0) {
        $kind = 'image/png';
    } elseif (strncmp($head, 'RIFF', 4) === 0 && substr($head, 8, 4) === 'WEBP') {
        $kind = 'image/webp';
    }
    if ($kind === '') {
        return 'El archivo no es una imagen válida.';
    }
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$fi->file($tmpPath);
    if ($mime !== $kind) {
        return 'El tipo real del archivo no coincide con una imagen válida.';
    }
    $map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    if ($map[$ext] !== $kind) {
        return 'La extensión no coincide con el contenido de la imagen.';
    }

    // 3) dimensiones razonables
    $info = @getimagesize($tmpPath);
    if (!is_array($info) || $info[0] < 1 || $info[1] < 1 || $info[0] > 9000 || $info[1] > 9000 || ($info[0] * $info[1]) > 50000000) {
        return 'Dimensiones de imagen no válidas.';
    }

    // 4) recodificación con GD
    $raw = file_get_contents($tmpPath);
    $img = ($raw === false) ? false : @imagecreatefromstring($raw);
    unset($raw);
    if ($img === false) {
        return 'No se pudo procesar la imagen.';
    }
    if ($kind === 'image/jpeg' && function_exists('exif_read_data')) {
        $ex = @exif_read_data($tmpPath);
        $o = is_array($ex) ? (int)($ex['Orientation'] ?? 1) : 1;
        $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($deg !== 0) {
            $rot = imagerotate($img, $deg, 0);
            if ($rot !== false) {
                $img = $rot;
            }
        }
    }
    if (function_exists('imagepalettetotruecolor')) {
        @imagepalettetotruecolor($img);
    }
    imagealphablending($img, false);
    imagesavealpha($img, true);

    $w = imagesx($img);
    $h = imagesy($img);
    $main = gx_scale_to($img, $w, $h, GX_IMG_MAX_SIDE);
    $thumb = gx_scale_to($img, $w, $h, GX_IMG_THUMB_SIDE);

    $base = $baseName ?? ('i' . gx_rand_hex(10));
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,60}$/', $base)) {
        return 'Nombre interno no válido.';
    }
    $fMain = $base . '.webp';
    $fThumb = $base . '-t.webp';
    $okA = imagewebp($main, rtrim($destDir, '/') . '/' . $fMain, 82);
    $okB = imagewebp($thumb, rtrim($destDir, '/') . '/' . $fThumb, 78);
    $dims = [imagesx($main), imagesy($main), imagesx($thumb), imagesy($thumb)];
    imagedestroy($img);
    if ($main !== $img) {
        imagedestroy($main);
    }
    if ($thumb !== $img && $thumb !== $main) {
        imagedestroy($thumb);
    }
    if (!$okA || !$okB) {
        return 'No se pudo guardar la imagen.';
    }
    foreach ([$fMain, $fThumb] as $f) {
        $sig = (string)file_get_contents(rtrim($destDir, '/') . '/' . $f, false, null, 0, 12);
        if (strncmp($sig, 'RIFF', 4) !== 0 || substr($sig, 8, 4) !== 'WEBP') {
            @unlink(rtrim($destDir, '/') . '/' . $fMain);
            @unlink(rtrim($destDir, '/') . '/' . $fThumb);
            return 'Error al verificar la imagen generada.';
        }
        @chmod(rtrim($destDir, '/') . '/' . $f, 0644);
    }
    return ['file' => $fMain, 'thumb' => $fThumb, 'w' => $dims[0], 'h' => $dims[1], 'tw' => $dims[2], 'th' => $dims[3]];
}

/** @param resource|\GdImage $img */
function gx_scale_to($img, int $w, int $h, int $maxSide)
{
    $long = max($w, $h);
    if ($long <= $maxSide) {
        return $img;
    }
    $r = $maxSide / $long;
    $nw = max(1, (int)round($w * $r));
    $nh = max(1, (int)round($h * $r));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

/** Borra los archivos de un medio (solo nombres seguros dentro de uploads). */
function gx_delete_media_files(array $m): void
{
    foreach (['file', 'thumb'] as $k) {
        $f = (string)($m[$k] ?? '');
        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,70}(-t)?\.webp$/', $f)) {
            @unlink(GX_ROOT . '/uploads/' . $f);
        }
    }
}

/** Genera favicon.png (32), apple-touch-icon.png (180) y favicon.ico (PNG incrustado) desde un medio. */
function gx_make_favicons(array $m): bool
{
    $path = GX_ROOT . '/uploads/' . $m['file'];
    if (!is_file($path)) {
        return false;
    }
    $src = @imagecreatefromwebp($path);
    if ($src === false) {
        return false;
    }
    $sw = imagesx($src);
    $sh = imagesy($src);
    $side = min($sw, $sh);
    // logos apaisados: el emblema está a la izquierda
    $sx = ($sw > $sh * 1.5) ? 0 : (int)(($sw - $side) / 2);
    $sy = (int)(($sh - $side) / 2);
    $out = [];
    foreach ([32 => 'favicon.png', 180 => 'apple-touch-icon.png'] as $px => $fname) {
        $d = imagecreatetruecolor($px, $px);
        imagefill($d, 0, 0, imagecolorallocate($d, 246, 241, 228));
        imagealphablending($d, true);
        imagecopyresampled($d, $src, 0, 0, $sx, $sy, $px, $px, $side, $side);
        ob_start();
        imagepng($d, null, 9);
        $out[$fname] = (string)ob_get_clean();
        imagedestroy($d);
    }
    imagedestroy($src);
    foreach ($out as $fname => $bin) {
        file_put_contents(GX_ROOT . '/' . $fname, $bin, LOCK_EX);
    }
    // ICO con una imagen PNG de 32x32
    $png = $out['favicon.png'];
    $ico = pack('vvv', 0, 1, 1) . pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 22) . $png;
    file_put_contents(GX_ROOT . '/favicon.ico', $ico, LOCK_EX);
    return true;
}
