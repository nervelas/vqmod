<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Config;
use S5\Core\Db;
use S5\Core\Fs;
use S5\Core\Log;
use S5\Core\Sanitize;

/** Subidas seguras: MIME real, tamaño, re-codificación de imágenes, nombres aleatorios, almacenamiento fuera del alcance web. */
final class Files
{
    public const MAX_IMG_BYTES = 12582912;      // 12 MB entrada (el navegador ya comprime)
    public const MAX_PROOF_BYTES = 5242880;     // 5 MB comprobante
    public const MAX_PER_ORDER = 600;
    public const MAX_ORDER_BYTES = 262144000;   // 250 MB

    public static function root(): string
    {
        $p = (string) Config::get('data_path', '');
        return rtrim($p !== '' ? $p : S5_ROOT . '/storage', '/');
    }

    public static function dir(int $orderId): string
    {
        return self::root() . '/uploads/' . $orderId;
    }

    public static function path(array $f): string
    {
        return self::dir((int) $f['order_id']) . '/' . $f['stored'];
    }

    public static function thumbPath(array $f): string
    {
        return self::path($f) . '.t.jpg';
    }

    public static function get(int $id, int $orderId): ?array
    {
        return Db::one('SELECT * FROM ' . Db::t('files') . ' WHERE id=? AND order_id=?', [$id, $orderId]);
    }

    public static function forOrder(int $orderId, ?string $kind = null): array
    {
        if ($kind) {
            return Db::all('SELECT * FROM ' . Db::t('files') . ' WHERE order_id=? AND kind=? ORDER BY id', [$orderId, $kind]);
        }
        return Db::all('SELECT * FROM ' . Db::t('files') . ' WHERE order_id=? ORDER BY id', [$orderId]);
    }

    private static function quota(int $orderId, int $newBytes): ?string
    {
        $r = Db::one('SELECT COUNT(*) c, COALESCE(SUM(size),0) s FROM ' . Db::t('files') . ' WHERE order_id=?', [$orderId]);
        if ((int) $r['c'] >= self::MAX_PER_ORDER) {
            return 'Alcanzó el máximo de archivos permitidos.';
        }
        if ((int) $r['s'] + $newBytes > self::MAX_ORDER_BYTES) {
            return 'Alcanzó el espacio máximo permitido para sus archivos.';
        }
        return null;
    }

    private static function finfo(string $path): string
    {
        $fi = new \finfo(FILEINFO_MIME_TYPE);
        return (string) $fi->file($path);
    }

    /** @return array{ok:bool,file?:array,error?:string} */
    public static function storeImage(int $orderId, string $tmp, string $origName, string $kind, string $source = 'cliente'): array
    {
        if (!is_file($tmp)) {
            return ['ok' => false, 'error' => 'No se recibió el archivo.'];
        }
        $size = (int) filesize($tmp);
        if ($size <= 0) {
            return ['ok' => false, 'error' => 'El archivo está vacío.'];
        }
        if ($size > self::MAX_IMG_BYTES) {
            return ['ok' => false, 'error' => 'La imagen es demasiado grande (máximo 12 MB).'];
        }
        if ($q = self::quota($orderId, $size)) {
            return ['ok' => false, 'error' => $q];
        }
        $mime = self::finfo($tmp);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return ['ok' => false, 'error' => 'Solo se aceptan imágenes JPG, PNG o WebP.'];
        }
        $info = @getimagesize($tmp);
        if (!$info || $info[0] < 1 || $info[1] < 1) {
            return ['ok' => false, 'error' => 'La imagen no se pudo leer.'];
        }
        if ($info[0] * $info[1] > 50000000) {
            return ['ok' => false, 'error' => 'La imagen es demasiado grande en píxeles.'];
        }
        if (!function_exists('imagecreatefromstring')) {
            return ['ok' => false, 'error' => 'El servidor no puede procesar imágenes.'];
        }
        $im = @imagecreatefromstring((string) file_get_contents($tmp));
        if (!$im) {
            return ['ok' => false, 'error' => 'La imagen no se pudo leer.'];
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $ex = @exif_read_data($tmp);
            $o = (int) ($ex['Orientation'] ?? 1);
            $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($rot !== 0) {
                $r = imagerotate($im, $rot, 0);
                if ($r) {
                    $im = $r;
                }
            }
        }
        $max = $kind === 'logo' ? 900 : 1600;
        $res = self::encode($im, $max, $kind === 'logo');
        if (!$res) {
            return ['ok' => false, 'error' => 'No se pudo procesar la imagen.'];
        }
        [$data, $ext, $outMime, $w, $h] = $res;
        $dir = self::dir($orderId);
        Fs::mkdir($dir, 0750);
        $stored = Fs::randomName($ext);
        if (@file_put_contents($dir . '/' . $stored, $data) === false) {
            return ['ok' => false, 'error' => 'No se pudo guardar el archivo (¿espacio en disco?).'];
        }
        @chmod($dir . '/' . $stored, 0640);
        $id = Db::insert('files', [
            'order_id' => $orderId, 'kind' => $kind, 'orig_name' => Sanitize::text(basename($origName), 120),
            'stored' => $stored, 'mime' => $outMime, 'size' => strlen($data), 'w' => $w, 'h' => $h, 'source' => $source, 'created_at' => Db::now(),
        ]);
        $f = Db::one('SELECT * FROM ' . Db::t('files') . ' WHERE id=?', [$id]);
        self::makeThumb($f);
        return ['ok' => true, 'file' => $f];
    }

    /** @return array{0:string,1:string,2:string,3:int,4:int}|null */
    private static function encode($im, int $max, bool $keepAlpha): ?array
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $scale = min(1.0, $max / max($w, $h));
        if ($scale < 1.0) {
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $im = $dst;
            $w = $nw;
            $h = $nh;
        }
        $hasAlpha = self::hasAlpha($im);
        ob_start();
        if (function_exists('imagewebp') && ($hasAlpha || $keepAlpha || true)) {
            imagepalettetotruecolor($im);
            imagealphablending($im, false);
            imagesavealpha($im, true);
            $ok = @imagewebp($im, null, 84);
            $ext = 'webp';
            $mime = 'image/webp';
        } else {
            if ($hasAlpha || $keepAlpha) {
                imagesavealpha($im, true);
                $ok = @imagepng($im, null, 7);
                $ext = 'png';
                $mime = 'image/png';
            } else {
                $ok = @imagejpeg($im, null, 85);
                $ext = 'jpg';
                $mime = 'image/jpeg';
            }
        }
        $data = (string) ob_get_clean();
        if (!$ok || $data === '') {
            return null;
        }
        return [$data, $ext, $mime, $w, $h];
    }

    private static function hasAlpha($im): bool
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $step = max(1, (int) floor(max($w, $h) / 40));
        for ($x = 0; $x < $w; $x += $step) {
            for ($y = 0; $y < $h; $y += $step) {
                if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function makeThumb(array $f): void
    {
        if (!str_starts_with((string) $f['mime'], 'image/')) {
            return;
        }
        $im = @imagecreatefromstring((string) @file_get_contents(self::path($f)));
        if (!$im) {
            return;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $s = min(1.0, 360 / max($w, $h));
        $nw = max(1, (int) round($w * $s));
        $nh = max(1, (int) round($h * $s));
        $t = imagecreatetruecolor($nw, $nh);
        imagefill($t, 0, 0, imagecolorallocate($t, 255, 255, 255));
        imagecopyresampled($t, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        @imagejpeg($t, self::thumbPath($f), 78);
    }

    /** Comprobante de pago: JPG/PNG/PDF, máx 5 MB, MIME real. */
    public static function storeProof(int $orderId, string $tmp, string $origName): array
    {
        $size = (int) @filesize($tmp);
        if ($size <= 0) {
            return ['ok' => false, 'error' => 'El archivo está vacío.'];
        }
        if ($size > self::MAX_PROOF_BYTES) {
            return ['ok' => false, 'error' => 'El comprobante es demasiado grande (máximo 5 MB).'];
        }
        if ($q = self::quota($orderId, $size)) {
            return ['ok' => false, 'error' => $q];
        }
        $mime = self::finfo($tmp);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
            return ['ok' => false, 'error' => 'El comprobante debe ser JPG, PNG o PDF.'];
        }
        $dir = self::dir($orderId);
        Fs::mkdir($dir, 0750);
        if ($mime === 'application/pdf') {
            $head = (string) file_get_contents($tmp, false, null, 0, 8);
            if (!str_starts_with($head, '%PDF-')) {
                return ['ok' => false, 'error' => 'El PDF no es válido.'];
            }
            $stored = Fs::randomName('pdf');
            if (!@copy($tmp, $dir . '/' . $stored)) {
                return ['ok' => false, 'error' => 'No se pudo guardar el archivo.'];
            }
            $w = $h = null;
            $outMime = 'application/pdf';
            $outSize = $size;
        } else {
            $info = @getimagesize($tmp);
            if (!$info || $info[0] * $info[1] > 50000000) {
                return ['ok' => false, 'error' => 'La imagen no es válida.'];
            }
            $im = @imagecreatefromstring((string) file_get_contents($tmp));
            if (!$im) {
                return ['ok' => false, 'error' => 'La imagen no se pudo leer.'];
            }
            $res = self::encode($im, 2000, false);
            if (!$res) {
                return ['ok' => false, 'error' => 'No se pudo procesar la imagen.'];
            }
            [$data, $ext, $outMime, $w, $h] = $res;
            $stored = Fs::randomName($ext);
            if (@file_put_contents($dir . '/' . $stored, $data) === false) {
                return ['ok' => false, 'error' => 'No se pudo guardar el archivo.'];
            }
            $outSize = strlen($data);
        }
        @chmod($dir . '/' . $stored, 0640);
        $id = Db::insert('files', ['order_id' => $orderId, 'kind' => 'comprobante', 'orig_name' => Sanitize::text(basename($origName), 120), 'stored' => $stored, 'mime' => $outMime, 'size' => $outSize, 'w' => $w, 'h' => $h, 'source' => 'cliente', 'created_at' => Db::now()]);
        $f = Db::one('SELECT * FROM ' . Db::t('files') . ' WHERE id=?', [$id]);
        self::makeThumb($f);
        return ['ok' => true, 'file' => $f];
    }

    /** Presentación original: ya inspeccionada por PresentationParser; se guarda con nombre aleatorio y sin extensión ejecutable. */
    public static function storePresentation(int $orderId, string $tmp, string $origName, string $tipo): array
    {
        $dir = self::dir($orderId) . '/pres';
        Fs::mkdir($dir, 0750);
        $stored = 'pres/' . Fs::randomName('dat');
        $size = (int) filesize($tmp);
        if ($q = self::quota($orderId, $size)) {
            return ['ok' => false, 'error' => $q];
        }
        if (!@copy($tmp, self::dir($orderId) . '/' . $stored)) {
            return ['ok' => false, 'error' => 'No se pudo guardar el archivo.'];
        }
        @chmod(self::dir($orderId) . '/' . $stored, 0640);
        $mime = ['pdf' => 'application/pdf', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'][$tipo] ?? 'application/octet-stream';
        $id = Db::insert('files', ['order_id' => $orderId, 'kind' => 'presentacion', 'orig_name' => Sanitize::text(basename($origName), 120), 'stored' => $stored, 'mime' => $mime, 'size' => $size, 'w' => null, 'h' => null, 'source' => 'cliente', 'created_at' => Db::now()]);
        return ['ok' => true, 'file' => Db::one('SELECT * FROM ' . Db::t('files') . ' WHERE id=?', [$id])];
    }

    /** Imagen ya procesada en disco (p. ej. extraída de una presentación) que se registra como archivo del pedido. */
    public static function adoptImage(int $orderId, string $path, string $kind, string $source, string $label = ''): ?array
    {
        $r = self::storeImage($orderId, $path, $label !== '' ? $label : basename($path), $kind, $source);
        return $r['ok'] ? $r['file'] : null;
    }

    public static function delete(array $f): void
    {
        $p = self::path($f);
        $root = self::dir((int) $f['order_id']);
        if (Fs::inside($p, $root)) {
            @unlink($p);
            @unlink(self::thumbPath($f));
        }
        Db::delete('files', 'id=?', [$f['id']]);
    }

    public static function purgeKind(int $orderId, string $kind): int
    {
        $n = 0;
        foreach (self::forOrder($orderId, $kind) as $f) {
            self::delete($f);
            $n++;
        }
        return $n;
    }

    /** Elimina TODOS los archivos subidos de un pedido (borrado de vista previa vencida). */
    public static function purgeAll(int $orderId): void
    {
        $dir = self::dir($orderId);
        if (is_dir($dir) && Fs::inside($dir, self::root() . '/uploads')) {
            Fs::rmTreeSafe($dir, [self::root() . '/uploads']);
        }
        Db::delete('files', 'order_id=?', [$orderId]);
        $jobs = self::root() . '/jobs/' . $orderId;
        if (is_dir($jobs) && Fs::inside($jobs, self::root() . '/jobs')) {
            Fs::rmTreeSafe($jobs, [self::root() . '/jobs']);
        }
        $pres = self::root() . '/work/' . $orderId;
        if (is_dir($pres) && Fs::inside($pres, self::root() . '/work')) {
            Fs::rmTreeSafe($pres, [self::root() . '/work']);
        }
    }

    public static function publicInfo(array $f, string $token): array
    {
        return [
            'id' => (int) $f['id'],
            'nombre' => $f['orig_name'],
            'tipo' => $f['kind'],
            'mime' => $f['mime'],
            'w' => $f['w'] !== null ? (int) $f['w'] : null,
            'h' => $f['h'] !== null ? (int) $f['h'] : null,
            'url_miniatura' => str_starts_with((string) $f['mime'], 'image/') ? '/f/' . $token . '/' . (int) $f['id'] . '?t=1' : '',
        ];
    }
}
