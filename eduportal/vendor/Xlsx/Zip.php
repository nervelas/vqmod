<?php
declare(strict_types=1);

namespace Vendor\Xlsx;

/** Escritor ZIP minimo en PHP puro (metodo deflate), sin depender de la extension zip. */
final class Zip
{
    /** @var array<int,array{nombre:string,datos:string,crc:int,comp:string,metodo:int}> */
    private array $entradas = [];

    public function agregar(string $nombre, string $datos): void
    {
        $crc = crc32($datos);
        $comp = function_exists('gzdeflate') ? (string)gzdeflate($datos, 6) : $datos;
        $metodo = 8;
        if (!function_exists('gzdeflate') || strlen($comp) >= strlen($datos)) {
            $comp = $datos;
            $metodo = 0;
        }
        $this->entradas[] = [
            'nombre' => $nombre,
            'datos'  => $datos,
            'crc'    => $crc,
            'comp'   => $comp,
            'metodo' => $metodo,
        ];
    }

    public function salida(): string
    {
        $archivo = '';
        $central = '';
        $offset = 0;
        $fecha = $this->fechaDos();
        foreach ($this->entradas as $e) {
            $nombre = $e['nombre'];
            $local = "\x50\x4b\x03\x04"
                . pack('v', 20)
                . pack('v', 0)
                . pack('v', $e['metodo'])
                . pack('V', $fecha)
                . pack('V', $e['crc'])
                . pack('V', strlen($e['comp']))
                . pack('V', strlen($e['datos']))
                . pack('v', strlen($nombre))
                . pack('v', 0)
                . $nombre
                . $e['comp'];
            $archivo .= $local;

            $central .= "\x50\x4b\x01\x02"
                . pack('v', 20)
                . pack('v', 20)
                . pack('v', 0)
                . pack('v', $e['metodo'])
                . pack('V', $fecha)
                . pack('V', $e['crc'])
                . pack('V', strlen($e['comp']))
                . pack('V', strlen($e['datos']))
                . pack('v', strlen($nombre))
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('V', 32)
                . pack('V', $offset)
                . $nombre;
            $offset += strlen($local);
        }
        $fin = "\x50\x4b\x05\x06"
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', count($this->entradas))
            . pack('v', count($this->entradas))
            . pack('V', strlen($central))
            . pack('V', $offset)
            . pack('v', 0);
        return $archivo . $central . $fin;
    }

    private function fechaDos(): int
    {
        $t = getdate();
        $anio = max(1980, (int)$t['year']);
        return (($anio - 1980) << 25) | ((int)$t['mon'] << 21) | ((int)$t['mday'] << 16)
             | ((int)$t['hours'] << 11) | ((int)$t['minutes'] << 5) | ((int)$t['seconds'] >> 1);
    }

    /**
     * Lee un ZIP en memoria y devuelve [nombre => contenido].
     * Soporta los metodos 0 (store) y 8 (deflate).
     * @return array<string,string>
     */
    public static function leer(string $ruta): array
    {
        $datos = @file_get_contents($ruta);
        if ($datos === false || strlen($datos) < 22) {
            throw new \RuntimeException('No se pudo leer el archivo comprimido.');
        }
        $pos = strrpos($datos, "\x50\x4b\x05\x06");
        if ($pos === false) {
            throw new \RuntimeException('El archivo no es un ZIP valido.');
        }
        $fin = unpack('vdisco/vdiscoCd/ventradasDisco/ventradas/VtamCd/VoffsetCd/vcomentario', substr($datos, $pos + 4, 18));
        $p = (int)$fin['offsetCd'];
        $salida = [];
        for ($i = 0; $i < (int)$fin['entradas']; $i++) {
            if (substr($datos, $p, 4) !== "\x50\x4b\x01\x02") {
                break;
            }
            $cab = unpack(
                'vversion/vversionNec/vbandera/vmetodo/vhora/vfecha/Vcrc/VtamComp/VtamOrig/'
                . 'vlargoNombre/vlargoExtra/vlargoComentario/vdiscoIni/vatrInt/VatrExt/VoffsetLocal',
                substr($datos, $p + 4, 42)
            );
            $nombre = substr($datos, $p + 46, (int)$cab['largoNombre']);
            $offsetLocal = (int)$cab['offsetLocal'];
            $local = unpack(
                'vversion/vbandera/vmetodo/vhora/vfecha/Vcrc/VtamComp/VtamOrig/vlargoNombre/vlargoExtra',
                substr($datos, $offsetLocal + 4, 26)
            );
            $inicio = $offsetLocal + 30 + (int)$local['largoNombre'] + (int)$local['largoExtra'];
            $crudo = substr($datos, $inicio, (int)$cab['tamComp']);
            if ((int)$cab['metodo'] === 8) {
                $contenido = @gzinflate($crudo);
            } else {
                $contenido = $crudo;
            }
            if ($contenido !== false) {
                $salida[$nombre] = $contenido;
            }
            $p += 46 + (int)$cab['largoNombre'] + (int)$cab['largoExtra'] + (int)$cab['largoComentario'];
        }
        return $salida;
    }
}
