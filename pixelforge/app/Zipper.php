<?php
declare(strict_types=1);

/** Empaqueta imágenes en ZIP. Usa ZipArchive y, si falta, un escritor propio (sin compresión). */
final class Zipper
{
    /**
     * @param array<int,array{path:string,name:string}> $files
     * @return string ruta del archivo ZIP temporal
     */
    public static function build(array $files, string $baseName = 'pixelforge'): string
    {
        $files = array_values(array_filter($files, static fn (array $f): bool => is_file($f['path'])));
        if (!$files) {
            throw new RuntimeException('No hay imágenes que empaquetar.');
        }
        $target = PF_STORAGE . '/tmp/' . $baseName . '-' . date('Ymd-His') . '-' . substr(Support::uuid(), 0, 8) . '.zip';

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($files as $file) {
                    $zip->addFile($file['path'], $file['name']);
                }
                $zip->close();
                if (is_file($target)) {
                    return $target;
                }
            }
            Logger::write('zip', 'ZipArchive no pudo crear el archivo, se usa el empaquetador propio');
        }
        self::writeStoredZip($target, $files);
        return $target;
    }

    /** ZIP mínimo válido con método "stored" (sin compresión). */
    private static function writeStoredZip(string $target, array $files): void
    {
        $handle = @fopen($target, 'wb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo crear el archivo ZIP temporal.');
        }
        $entries = [];
        $offset = 0;
        foreach ($files as $file) {
            $content = (string) @file_get_contents($file['path']);
            $name = str_replace('\\', '/', $file['name']);
            $crc = crc32($content);
            $size = strlen($content);
            [$time, $date] = self::dosTime(filemtime($file['path']) ?: time());

            $local = "\x50\x4b\x03\x04"
                . pack('v', 20) . pack('v', 0) . pack('v', 0)
                . pack('v', $time) . pack('v', $date)
                . pack('V', $crc) . pack('V', $size) . pack('V', $size)
                . pack('v', strlen($name)) . pack('v', 0)
                . $name;
            fwrite($handle, $local . $content);

            $entries[] = "\x50\x4b\x01\x02"
                . pack('v', 20) . pack('v', 20) . pack('v', 0) . pack('v', 0)
                . pack('v', $time) . pack('v', $date)
                . pack('V', $crc) . pack('V', $size) . pack('V', $size)
                . pack('v', strlen($name)) . pack('v', 0) . pack('v', 0)
                . pack('v', 0) . pack('v', 0) . pack('V', 32)
                . pack('V', $offset) . $name;
            $offset += strlen($local) + $size;
        }
        $central = implode('', $entries);
        fwrite($handle, $central);
        fwrite($handle, "\x50\x4b\x05\x06" . pack('v', 0) . pack('v', 0)
            . pack('v', count($entries)) . pack('v', count($entries))
            . pack('V', strlen($central)) . pack('V', $offset) . pack('v', 0));
        fclose($handle);
    }

    private static function dosTime(int $timestamp): array
    {
        $parts = getdate($timestamp);
        $time = ($parts['hours'] << 11) | ($parts['minutes'] << 5) | (int) ($parts['seconds'] / 2);
        $date = (($parts['year'] - 1980) << 9) | ($parts['mon'] << 5) | $parts['mday'];
        return [$time, $date];
    }

    /** Borra ZIP temporales de más de una hora. */
    public static function cleanup(): void
    {
        $files = @glob(PF_STORAGE . '/tmp/*.zip') ?: [];
        foreach ($files as $file) {
            if (is_file($file) && (time() - (int) filemtime($file)) > 3600) {
                @unlink($file);
            }
        }
    }
}
