<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Subida segura: valida MIME real con finfo, extension permitida,
 * renombra aleatoriamente y guarda dentro de /storage (sin ejecucion de PHP).
 */
final class Upload
{
    public const IMAGENES  = ['jpg', 'jpeg', 'png', 'webp'];
    public const DOCUMENTOS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'xlsx', 'csv'];
    public const HOJAS     = ['xlsx', 'csv'];

    private const MIMES = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
    ];

    /**
     * @return array{ok:bool, archivo?:string, mime?:string, tamano?:int, error?:string}
     */
    public static function store(?array $file, string $carpeta, array $permitidas = self::IMAGENES): array
    {
        if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No se recibio ningun archivo.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Error al subir el archivo (codigo ' . (int)$file['error'] . ').'];
        }
        $maxMb = max(1, Settings::int('subida_max_mb', 8));
        if ((int)$file['size'] > $maxMb * 1024 * 1024) {
            return ['ok' => false, 'error' => "El archivo supera el limite de {$maxMb} MB."];
        }
        if (!is_uploaded_file($file['tmp_name']) && !is_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Archivo temporal invalido.'];
        }

        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $permitidas, true)) {
            return ['ok' => false, 'error' => 'Extension no permitida (' . implode(', ', $permitidas) . ').'];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string)$finfo->file($file['tmp_name']);
        if (!in_array($mime, self::MIMES[$ext] ?? [], true)) {
            return ['ok' => false, 'error' => 'El contenido del archivo no coincide con su extension.'];
        }
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $info = @getimagesize($file['tmp_name']);
            if ($info === false) {
                return ['ok' => false, 'error' => 'La imagen no es valida.'];
            }
        }

        $dir = BASE_PATH . '/storage/uploads/' . trim($carpeta, '/');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'No se pudo crear la carpeta de destino.'];
        }
        $nombre = bin2hex(random_bytes(16)) . '.' . $ext;
        $destino = $dir . '/' . $nombre;
        $movido = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $destino)
            : rename($file['tmp_name'], $destino);
        if (!$movido) {
            return ['ok' => false, 'error' => 'No se pudo guardar el archivo.'];
        }
        @chmod($destino, 0644);
        return [
            'ok'      => true,
            'archivo' => trim($carpeta, '/') . '/' . $nombre,
            'mime'    => $mime,
            'tamano'  => (int)$file['size'],
        ];
    }

    public static function delete(?string $relativo): void
    {
        if (!$relativo) {
            return;
        }
        $relativo = str_replace(['..', "\0"], '', $relativo);
        $path = BASE_PATH . '/storage/uploads/' . ltrim($relativo, '/');
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function ruta(?string $relativo): ?string
    {
        if (!$relativo) {
            return null;
        }
        $relativo = str_replace(['..', "\0"], '', $relativo);
        $path = BASE_PATH . '/storage/uploads/' . ltrim($relativo, '/');
        return is_file($path) ? $path : null;
    }
}
