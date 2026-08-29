<?php
declare(strict_types=1);

namespace MenuGold\Vendor\Xlsx;

use RuntimeException;

/**
 * Lectura y escritura de archivos ZIP para los XLSX (Excel).
 *
 * Se apoya siempre en ZipArchive, la extension de PHP que traen practicamente
 * todos los hosting con cPanel. Antes habia aqui un lector/escritor ZIP escrito
 * a mano; se quito a proposito: comprimir y descomprimir bytes
 * dentro de un archivo PHP es justo lo que hacen los programas maliciosos
 * para esconderse, y los antivirus de los hosting compartidos lo marcan aunque
 * el uso sea legitimo. Usar la extension del sistema es mas seguro, mas rapido
 * y deja el codigo limpio ante cualquier analisis.
 */
final class Zip
{
    /** ¿Puede este servidor leer y escribir Excel? */
    public static function disponible(): bool
    {
        return class_exists('\ZipArchive');
    }

    private static function exigir(): void
    {
        if (!self::disponible()) {
            throw new RuntimeException(
                'Este servidor no tiene activada la extension «zip» de PHP, '
                . 'necesaria para importar y exportar archivos de Excel. '
                . 'Pidele a tu proveedor de hosting que la active.'
            );
        }
    }

    /**
     * Crea un ZIP en memoria.
     * @param array<string,string> $archivos ruta dentro del zip => contenido
     */
    public static function create(array $archivos): string
    {
        self::exigir();

        $tmp = tempnam(sys_get_temp_dir(), 'mgz');
        if ($tmp === false) {
            throw new RuntimeException('No se pudo crear un archivo temporal para el Excel.');
        }

        try {
            $z = new \ZipArchive();
            if ($z->open($tmp, \ZipArchive::OVERWRITE | \ZipArchive::CREATE) !== true) {
                throw new RuntimeException('No se pudo crear el archivo de Excel.');
            }
            foreach ($archivos as $ruta => $contenido) {
                $z->addFromString($ruta, $contenido);
            }
            $z->close();

            $datos = file_get_contents($tmp);
            if ($datos === false) {
                throw new RuntimeException('No se pudo leer el archivo de Excel recien creado.');
            }
            return $datos;
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }

    /**
     * Lee un ZIP y devuelve [ruta dentro del zip => contenido].
     * @return array<string,string>
     */
    public static function read(string $rutaArchivo): array
    {
        self::exigir();

        if (!is_file($rutaArchivo)) {
            return [];
        }

        $z = new \ZipArchive();
        if ($z->open($rutaArchivo) !== true) {
            throw new RuntimeException('El archivo no es un Excel valido (no se pudo abrir).');
        }

        $out = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $nombre = (string)$z->getNameIndex($i);

            // Nada de rutas raras ni de salir de la carpeta: solo entradas normales
            if ($nombre === '' || substr($nombre, -1) === '/') continue;
            if (strpos($nombre, '..') !== false || strpos($nombre, "\0") !== false) continue;

            $contenido = $z->getFromIndex($i);
            if ($contenido !== false) {
                $out[$nombre] = $contenido;
            }
        }
        $z->close();

        return $out;
    }
}
