<?php
declare(strict_types=1);

/** Biblioteca de medios con subida segura. */
final class Media
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'image/svg+xml' => 'svg',
        'image/avif' => 'avif',
    ];

    public const MAX_BYTES = 6291456; // 6 MB

    public static function dir(): string
    {
        return dirname(__DIR__) . '/uploads/media';
    }

    /**
     * Procesa un archivo de $_FILES y devuelve la ruta relativa guardada.
     * @return array{ok:bool,path?:string,error?:string}
     */
    public static function upload(array $file, string $alt = ''): array
    {
        if (!isset($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No se selecciono ningun archivo.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Error al subir el archivo (codigo ' . (int) $file['error'] . ').'];
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'El archivo supera el limite de 6 MB.'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Origen de archivo no valido.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            return ['ok' => false, 'error' => 'Formato no permitido. Use JPG, PNG, WEBP, AVIF, GIF o SVG.'];
        }

        // Los SVG se sanean para evitar scripts incrustados.
        if ($mime === 'image/svg+xml') {
            $svg = (string) file_get_contents($file['tmp_name']);
            if (preg_match('/<script|onload\s*=|onerror\s*=|javascript:/i', $svg) === 1) {
                return ['ok' => false, 'error' => 'El SVG contiene codigo no permitido.'];
            }
        }

        $ext  = self::ALLOWED[$mime];
        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dir  = self::dir();

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'No se pudo crear la carpeta uploads/media.'];
        }
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'No se pudo guardar el archivo. Revise permisos de uploads/media.'];
        }
        @chmod($dir . '/' . $name, 0644);

        $rel  = 'uploads/media/' . $name;
        $size = (int) ($file['size'] ?? 0);
        [$w, $h] = self::dimensions($dir . '/' . $name);

        Database::insert('media', [
            'filename'   => $name,
            'path'       => $rel,
            'mime'       => $mime,
            'size'       => $size,
            'width'      => $w,
            'height'     => $h,
            'alt'        => $alt !== '' ? $alt : pathinfo($name, PATHINFO_FILENAME),
            'created_at' => Database::now(),
        ]);

        return ['ok' => true, 'path' => $rel];
    }

    private static function dimensions(string $file): array
    {
        $info = @getimagesize($file);
        return is_array($info) ? [(int) $info[0], (int) $info[1]] : [0, 0];
    }

    /** @return list<array<string,mixed>> */
    public static function all(int $limit = 200): array
    {
        return Database::all('SELECT * FROM media ORDER BY id DESC LIMIT ' . (int) $limit);
    }

    public static function remove(int $id): bool
    {
        $row = Database::first('SELECT * FROM media WHERE id = :id', ['id' => $id]);
        if ($row === null) {
            return false;
        }
        $file = dirname(__DIR__) . '/' . ltrim((string) $row['path'], '/');
        if (is_file($file) && str_starts_with(realpath($file) ?: '', realpath(self::dir()) ?: 'x')) {
            @unlink($file);
        }
        Database::delete('media', 'id = :id', ['id' => $id]);
        return true;
    }

    public static function altFor(?string $path, string $fallback = ''): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return $fallback;
        }
        $alt = Database::value('SELECT alt FROM media WHERE path = :p LIMIT 1', ['p' => ltrim($path, '/')], '');
        return (string) ($alt !== '' && $alt !== null ? $alt : $fallback);
    }
}
