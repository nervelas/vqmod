<?php
/**
 * Media library manager — secure uploads + records.
 */

declare(strict_types=1);

class Media
{
    public const DIR = 'uploads/media';

    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'image/svg+xml' => 'svg',
    ];

    /**
     * Handle a single $_FILES entry. Returns the stored web path or throws.
     */
    public static function upload(array $file, ?string $alt = null): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo subir el archivo (código ' . ($file['error'] ?? '?') . ').');
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 6 * 1024 * 1024) {
            throw new RuntimeException('El archivo excede el tamaño máximo permitido (6 MB).');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Origen de archivo no válido.');
        }

        // Detect real MIME — never trust the client-provided type/extension.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Tipo de archivo no permitido. Use JPG, PNG, WEBP, GIF o SVG.');
        }
        $ext = self::ALLOWED[$mime];

        // SVG: sanitize (strip scripts/handlers) before storing.
        $tmp = $file['tmp_name'];
        if ($mime === 'image/svg+xml') {
            $clean = self::sanitizeSvg((string)file_get_contents($tmp));
            $tmp = tempnam(sys_get_temp_dir(), 'svg');
            file_put_contents($tmp, $clean);
        }

        $width = $height = null;
        if ($mime !== 'image/svg+xml') {
            $info = @getimagesize($file['tmp_name']);
            if ($info === false) {
                throw new RuntimeException('El archivo no es una imagen válida.');
            }
            $width = $info[0]; $height = $info[1];
        }

        $base = self::safeName(pathinfo($file['name'] ?? 'imagen', PATHINFO_FILENAME));
        $filename = $base . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $absDir = BASE_PATH . '/' . self::DIR;
        if (!is_dir($absDir)) { @mkdir($absDir, 0755, true); }
        $absPath = $absDir . '/' . $filename;

        if ($mime === 'image/svg+xml') {
            copy($tmp, $absPath); @unlink($tmp);
        } else {
            move_uploaded_file($file['tmp_name'], $absPath);
        }
        @chmod($absPath, 0644);

        $webPath = self::DIR . '/' . $filename;
        $id = Database::insert('media', [
            'filename'      => $filename,
            'original_name' => mb_substr((string)($file['name'] ?? ''), 0, 255),
            'path'          => $webPath,
            'mime'          => $mime,
            'size'          => filesize($absPath) ?: $size,
            'width'         => $width,
            'height'        => $height,
            'alt'           => $alt,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return ['id' => $id, 'path' => $webPath, 'width' => $width, 'height' => $height];
    }

    /** Delete a media record and its file. */
    public static function delete(int $id): bool
    {
        $m = Database::first('SELECT * FROM media WHERE id = ?', [$id]);
        if (!$m) { return false; }
        $abs = BASE_PATH . '/' . $m['path'];
        if (is_file($abs) && strpos(realpath($abs) ?: '', realpath(BASE_PATH . '/' . self::DIR) ?: 'x') === 0) {
            @unlink($abs);
        }
        Database::delete('media', ['id' => $id]);
        return true;
    }

    public static function all(): array
    {
        return Database::all('SELECT * FROM media ORDER BY created_at DESC, id DESC');
    }

    private static function safeName(string $name): string
    {
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', strtolower($name));
        $name = trim($name, '-.') ?: 'archivo';
        return substr($name, 0, 60);
    }

    /** Remove scripts, event handlers and external refs from SVG markup. */
    private static function sanitizeSvg(string $svg): string
    {
        // Drop script/foreignObject and any on* handlers or javascript: URIs.
        $svg = preg_replace('#<script.*?>.*?</script>#is', '', $svg);
        $svg = preg_replace('#<foreignObject.*?>.*?</foreignObject>#is', '', $svg);
        $svg = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\')#i', '', $svg);
        $svg = preg_replace('#(href|xlink:href)\s*=\s*("|\')\s*javascript:[^"\']*("|\')#i', '', $svg);
        $svg = preg_replace('#<!ENTITY.*?>#is', '', $svg);
        return $svg;
    }
}
