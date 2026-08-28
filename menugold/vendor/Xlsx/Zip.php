<?php
declare(strict_types=1);

namespace MenuGold\Vendor\Xlsx;

/**
 * Lector y escritor ZIP minimo en PHP puro (metodo deflate).
 * Usa ZipArchive cuando esta disponible y, si no, escribe/lee el formato
 * directamente: asi los XLSX funcionan en cualquier hosting compartido.
 */
final class Zip
{
    /** @param array<string,string> $archivos ruta => contenido */
    public static function create(array $archivos): string
    {
        if (class_exists('\ZipArchive')) {
            $tmp = tempnam(sys_get_temp_dir(), 'mgz');
            if ($tmp !== false) {
                $z = new \ZipArchive();
                if ($z->open($tmp, \ZipArchive::OVERWRITE | \ZipArchive::CREATE) === true) {
                    foreach ($archivos as $ruta => $contenido) $z->addFromString($ruta, $contenido);
                    $z->close();
                    $datos = (string)file_get_contents($tmp);
                    @unlink($tmp);
                    return $datos;
                }
                @unlink($tmp);
            }
        }
        return self::createPuro($archivos);
    }

    /** Escritor ZIP nativo (sin extension zip). */
    private static function createPuro(array $archivos): string
    {
        $locales = '';
        $central = '';
        $offset = 0;
        $n = 0;
        $fecha = self::dosTime(time());

        foreach ($archivos as $ruta => $contenido) {
            $ruta = str_replace('\\', '/', $ruta);
            $crc = crc32($contenido);
            $sinComprimir = strlen($contenido);
            $comprimido = function_exists('gzdeflate') ? (string)gzdeflate($contenido, 6) : $contenido;
            $metodo = function_exists('gzdeflate') ? 8 : 0;
            if ($metodo === 8 && strlen($comprimido) >= $sinComprimir) {
                $comprimido = $contenido;
                $metodo = 0;
            }
            $tamComp = strlen($comprimido);

            $cabLocal = "\x50\x4b\x03\x04"
                . pack('v', 20) . pack('v', 0) . pack('v', $metodo)
                . pack('V', $fecha)
                . pack('V', $crc) . pack('V', $tamComp) . pack('V', $sinComprimir)
                . pack('v', strlen($ruta)) . pack('v', 0)
                . $ruta;

            $locales .= $cabLocal . $comprimido;

            $central .= "\x50\x4b\x01\x02"
                . pack('v', 20) . pack('v', 20) . pack('v', 0) . pack('v', $metodo)
                . pack('V', $fecha)
                . pack('V', $crc) . pack('V', $tamComp) . pack('V', $sinComprimir)
                . pack('v', strlen($ruta)) . pack('v', 0) . pack('v', 0)
                . pack('v', 0) . pack('v', 0) . pack('V', 32)
                . pack('V', $offset)
                . $ruta;

            $offset += strlen($cabLocal) + $tamComp;
            $n++;
        }

        $fin = "\x50\x4b\x05\x06" . pack('v', 0) . pack('v', 0)
            . pack('v', $n) . pack('v', $n)
            . pack('V', strlen($central)) . pack('V', $offset)
            . pack('v', 0);

        return $locales . $central . $fin;
    }

    /**
     * Lee un ZIP y devuelve [ruta => contenido].
     * @return array<string,string>
     */
    public static function read(string $rutaArchivo): array
    {
        if (!is_file($rutaArchivo)) return [];
        if (class_exists('\ZipArchive')) {
            $z = new \ZipArchive();
            if ($z->open($rutaArchivo) === true) {
                $out = [];
                for ($i = 0; $i < $z->numFiles; $i++) {
                    $nombre = (string)$z->getNameIndex($i);
                    if (substr($nombre, -1) === '/') continue;
                    $c = $z->getFromIndex($i);
                    if ($c !== false) $out[$nombre] = $c;
                }
                $z->close();
                return $out;
            }
        }
        return self::readPuro((string)file_get_contents($rutaArchivo));
    }

    /** @return array<string,string> */
    private static function readPuro(string $datos): array
    {
        $out = [];
        $pos = 0;
        $len = strlen($datos);
        while ($pos < $len - 4) {
            $firma = substr($datos, $pos, 4);
            if ($firma !== "\x50\x4b\x03\x04") break;
            $cab = unpack('vversion/vflags/vmetodo/vtiempo/vfecha/Vcrc/Vcomp/Vsin/vnlen/vxlen', substr($datos, $pos + 4, 26));
            if (!$cab) break;
            $nombre = substr($datos, $pos + 30, (int)$cab['nlen']);
            $inicio = $pos + 30 + (int)$cab['nlen'] + (int)$cab['xlen'];
            $comp = (int)$cab['comp'];
            $bruto = substr($datos, $inicio, $comp);
            if (($cab['flags'] & 0x08) && $comp === 0) break; // descriptor de datos: no soportado
            $contenido = (int)$cab['metodo'] === 8 ? (string)@gzinflate($bruto) : $bruto;
            if (substr($nombre, -1) !== '/') $out[$nombre] = $contenido;
            $pos = $inicio + $comp;
        }
        return $out;
    }

    private static function dosTime(int $ts): int
    {
        $d = getdate($ts);
        if ($d['year'] < 1980) return (1 << 21) | (1 << 16);
        return (($d['year'] - 1980) << 25) | ($d['mon'] << 21) | ($d['mday'] << 16)
             | ($d['hours'] << 11) | ($d['minutes'] << 5) | ((int)($d['seconds'] / 2));
    }
}
