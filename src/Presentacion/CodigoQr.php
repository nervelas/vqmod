<?php
declare(strict_types=1);

namespace Fel\Presentacion;

/**
 * Generador de codigo QR en PHP puro, sin dependencias ni extensiones graficas.
 *
 * Implementa la norma ISO/IEC 18004 en modo byte (8 bits) para versiones 1 a 15,
 * que cubre de sobra el contenido de una factura FEL (URL de verificacion + UUID).
 *
 * Se usa para imprimir en la representacion grafica el QR de consulta del DTE,
 * como lo hacen las facturas del regimen FEL. Genera SVG (para pantalla y PDF)
 * y una matriz de modulos por si se quiere renderizar de otra forma.
 */
final class CodigoQr
{
    public const NIVEL_L = 'L';
    public const NIVEL_M = 'M';
    public const NIVEL_Q = 'Q';
    public const NIVEL_H = 'H';

    /** Total de codewords (datos + correccion) por version. Indice = version. */
    private const TOTAL_CODEWORDS = [
        1 => 26, 2 => 44, 3 => 70, 4 => 100, 5 => 134, 6 => 172, 7 => 196, 8 => 242,
        9 => 292, 10 => 346, 11 => 404, 12 => 466, 13 => 532, 14 => 581, 15 => 655,
    ];

    /**
     * Bloques de correccion de errores.
     * [version][nivel] = [codewords EC por bloque, bloques grupo 1, datos grupo 1,
     *                     bloques grupo 2, datos grupo 2]
     */
    private const BLOQUES_EC = [
        1  => ['L' => [7, 1, 19, 0, 0],    'M' => [10, 1, 16, 0, 0],   'Q' => [13, 1, 13, 0, 0],   'H' => [17, 1, 9, 0, 0]],
        2  => ['L' => [10, 1, 34, 0, 0],   'M' => [16, 1, 28, 0, 0],   'Q' => [22, 1, 22, 0, 0],   'H' => [28, 1, 16, 0, 0]],
        3  => ['L' => [15, 1, 55, 0, 0],   'M' => [26, 1, 44, 0, 0],   'Q' => [18, 2, 17, 0, 0],   'H' => [22, 2, 13, 0, 0]],
        4  => ['L' => [20, 1, 80, 0, 0],   'M' => [18, 2, 32, 0, 0],   'Q' => [26, 2, 24, 0, 0],   'H' => [16, 4, 9, 0, 0]],
        5  => ['L' => [26, 1, 108, 0, 0],  'M' => [24, 2, 43, 0, 0],   'Q' => [18, 2, 15, 2, 16],  'H' => [22, 2, 11, 2, 12]],
        6  => ['L' => [18, 2, 68, 0, 0],   'M' => [16, 4, 27, 0, 0],   'Q' => [24, 4, 19, 0, 0],   'H' => [28, 4, 15, 0, 0]],
        7  => ['L' => [20, 2, 78, 0, 0],   'M' => [18, 4, 31, 0, 0],   'Q' => [18, 2, 14, 4, 15],  'H' => [26, 4, 13, 1, 14]],
        8  => ['L' => [24, 2, 97, 0, 0],   'M' => [22, 2, 38, 2, 39],  'Q' => [22, 4, 18, 2, 19],  'H' => [26, 4, 14, 2, 15]],
        9  => ['L' => [30, 2, 116, 0, 0],  'M' => [22, 3, 36, 2, 37],  'Q' => [20, 4, 16, 4, 17],  'H' => [24, 4, 12, 4, 13]],
        10 => ['L' => [18, 2, 68, 2, 69],  'M' => [26, 4, 43, 1, 44],  'Q' => [24, 6, 19, 2, 20],  'H' => [28, 6, 15, 2, 16]],
        11 => ['L' => [20, 4, 81, 0, 0],   'M' => [30, 1, 50, 4, 51],  'Q' => [28, 4, 22, 4, 23],  'H' => [24, 3, 12, 8, 13]],
        12 => ['L' => [24, 2, 92, 2, 93],  'M' => [22, 6, 36, 2, 37],  'Q' => [26, 4, 20, 6, 21],  'H' => [28, 7, 14, 4, 15]],
        13 => ['L' => [26, 4, 107, 0, 0],  'M' => [22, 8, 37, 1, 38],  'Q' => [24, 8, 20, 4, 21],  'H' => [22, 12, 11, 4, 12]],
        14 => ['L' => [30, 3, 115, 1, 116],'M' => [24, 4, 40, 5, 41],  'Q' => [20, 11, 16, 5, 17], 'H' => [24, 11, 12, 5, 13]],
        15 => ['L' => [22, 5, 87, 1, 88],  'M' => [24, 5, 41, 5, 42],  'Q' => [30, 5, 24, 7, 25],  'H' => [24, 11, 12, 7, 13]],
    ];

    /** Coordenadas de los patrones de alineacion por version. */
    private const ALINEACION = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
        10 => [6, 28, 50], 11 => [6, 30, 54], 12 => [6, 32, 58], 13 => [6, 34, 62],
        14 => [6, 26, 46, 66], 15 => [6, 26, 48, 70],
    ];

    /** Bits del indicador de nivel de correccion dentro de la informacion de formato. */
    private const BITS_NIVEL = ['L' => 0b01, 'M' => 0b00, 'Q' => 0b11, 'H' => 0b10];

    /** @var list<list<int>> Matriz de modulos: 1 = oscuro, 0 = claro. */
    private array $matriz = [];

    private int $tamano  = 0;
    private int $version = 0;

    /**
     * @param int|null $mascaraForzada Fija el patron de mascara (0-7) en lugar de
     *                                 elegir el de menor penalizacion. Solo para
     *                                 pruebas: en produccion dejelo en null.
     */
    public function __construct(
        private string $contenido,
        private string $nivel = self::NIVEL_M,
        private ?int $mascaraForzada = null,
    ) {
        if ($contenido === '') {
            throw new \InvalidArgumentException('El contenido del codigo QR no puede estar vacio.');
        }

        if (!isset(self::BITS_NIVEL[$nivel])) {
            throw new \InvalidArgumentException("Nivel de correccion no valido: {$nivel}");
        }

        $this->generar();
    }

    public function version(): int
    {
        return $this->version;
    }

    public function tamano(): int
    {
        return $this->tamano;
    }

    /** @return list<list<int>> */
    public function matriz(): array
    {
        return $this->matriz;
    }

    /**
     * SVG listo para incrustar. El margen va en modulos (la norma pide 4).
     */
    public function svg(int $lado = 120, int $margen = 4, string $colorOscuro = '#000000'): string
    {
        $total = $this->tamano + ($margen * 2);
        $rutas = [];

        for ($y = 0; $y < $this->tamano; $y++) {
            $x = 0;
            while ($x < $this->tamano) {
                if ($this->matriz[$y][$x] !== 1) {
                    $x++;
                    continue;
                }

                // Se agrupan modulos contiguos en un solo rectangulo: SVG mas liviano.
                $inicio = $x;
                while ($x < $this->tamano && $this->matriz[$y][$x] === 1) {
                    $x++;
                }

                $rutas[] = sprintf('M%d %dh%dv1h-%dz', $inicio + $margen, $y + $margen, $x - $inicio, $x - $inicio);
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" '
            . 'shape-rendering="crispEdges" role="img" aria-label="Código QR del documento">'
            . '<rect width="%d" height="%d" fill="#ffffff"/><path d="%s" fill="%s"/></svg>',
            $lado,
            $lado,
            $total,
            $total,
            $total,
            $total,
            implode('', $rutas),
            $colorOscuro
        );
    }

    /** SVG como data URI, para usarlo directamente en un <img src="..."> */
    public function dataUri(int $lado = 120, int $margen = 4): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode($this->svg($lado, $margen));
    }

    // ------------------------------------------------------------------ nucleo

    private function generar(): void
    {
        $bytes = array_values(unpack('C*', $this->contenido) ?: []);

        $this->version = $this->versionMinima(count($bytes));
        $this->tamano  = 17 + ($this->version * 4);

        $codewords = $this->codewordsFinales($bytes);

        $this->matriz = array_fill(0, $this->tamano, array_fill(0, $this->tamano, 0));
        $reservado    = array_fill(0, $this->tamano, array_fill(0, $this->tamano, false));

        $this->dibujarPatronesFijos($reservado);
        $this->colocarDatos($codewords, $reservado);

        $mascara = $this->mascaraForzada ?? $this->mejorMascara($reservado);
        $this->aplicarMascara($mascara, $reservado);
        $this->dibujarFormato($mascara);

        if ($this->version >= 7) {
            $this->dibujarVersion();
        }
    }

    private function versionMinima(int $largoDatos): int
    {
        foreach (array_keys(self::TOTAL_CODEWORDS) as $version) {
            $capacidad = $this->capacidadBytes($version);

            if ($largoDatos <= $capacidad) {
                return $version;
            }
        }

        throw new \InvalidArgumentException(
            'El contenido es demasiado largo para un codigo QR de hasta version 15 '
            . '(' . $largoDatos . ' bytes). Acorte la URL o baje el nivel de correccion.'
        );
    }

    /** Bytes de datos que caben en una version, descontando cabecera y terminador. */
    private function capacidadBytes(int $version): int
    {
        [$ecPorBloque, $bloques1, $datos1, $bloques2, $datos2] = self::BLOQUES_EC[$version][$this->nivel];

        $codewordsDatos = ($bloques1 * $datos1) + ($bloques2 * $datos2);
        $bitsCabecera   = 4 + ($version < 10 ? 8 : 16);

        return intdiv(($codewordsDatos * 8) - $bitsCabecera, 8);
    }

    /**
     * Arma el flujo final de codewords: datos codificados, correccion de errores,
     * bloques intercalados y bits de remanente.
     *
     * @param list<int> $bytes
     * @return list<int>
     */
    private function codewordsFinales(array $bytes): array
    {
        [$ecPorBloque, $bloques1, $datos1, $bloques2, $datos2] = self::BLOQUES_EC[$this->version][$this->nivel];

        $codewordsDatos = ($bloques1 * $datos1) + ($bloques2 * $datos2);
        $bitsLongitud   = $this->version < 10 ? 8 : 16;

        // 1. Cabecera: modo byte (0100) + longitud
        $bits = '0100' . str_pad(decbin(count($bytes)), $bitsLongitud, '0', STR_PAD_LEFT);

        // 2. Datos
        foreach ($bytes as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        // 3. Terminador (hasta 4 ceros) y relleno hasta completar bytes
        $capacidadBits = $codewordsDatos * 8;
        $bits .= str_repeat('0', min(4, $capacidadBits - strlen($bits)));
        $bits .= str_repeat('0', (8 - (strlen($bits) % 8)) % 8);

        // 4. Bytes de relleno alternados 0xEC / 0x11
        $relleno = ['11101100', '00010001'];
        $indice  = 0;
        while (strlen($bits) < $capacidadBits) {
            $bits .= $relleno[$indice % 2];
            $indice++;
        }

        $datos = [];
        for ($i = 0; $i < $capacidadBits; $i += 8) {
            $datos[] = (int) bindec(substr($bits, $i, 8));
        }

        // 5. Reparto en bloques y correccion de errores por bloque
        $bloquesDatos = [];
        $bloquesEc    = [];
        $posicion     = 0;

        foreach ([[$bloques1, $datos1], [$bloques2, $datos2]] as [$cantidad, $largo]) {
            for ($b = 0; $b < $cantidad; $b++) {
                $bloque         = array_slice($datos, $posicion, $largo);
                $posicion      += $largo;
                $bloquesDatos[] = $bloque;
                $bloquesEc[]    = $this->correccionErrores($bloque, $ecPorBloque);
            }
        }

        // 6. Intercalado
        $salida     = [];
        $maximoDatos = max(array_map('count', $bloquesDatos));

        for ($i = 0; $i < $maximoDatos; $i++) {
            foreach ($bloquesDatos as $bloque) {
                if (isset($bloque[$i])) {
                    $salida[] = $bloque[$i];
                }
            }
        }

        for ($i = 0; $i < $ecPorBloque; $i++) {
            foreach ($bloquesEc as $bloque) {
                if (isset($bloque[$i])) {
                    $salida[] = $bloque[$i];
                }
            }
        }

        return $salida;
    }

    /**
     * Correccion de errores Reed-Solomon sobre GF(256).
     *
     * @param list<int> $datos
     * @return list<int>
     */
    private function correccionErrores(array $datos, int $cantidad): array
    {
        [$exp, $log] = self::tablasGalois();

        // Polinomio generador
        $generador = [1];
        for ($i = 0; $i < $cantidad; $i++) {
            $nuevo = array_fill(0, count($generador) + 1, 0);
            foreach ($generador as $j => $coeficiente) {
                $nuevo[$j]     ^= $coeficiente;
                $nuevo[$j + 1] ^= $coeficiente === 0 ? 0 : $exp[($log[$coeficiente] + $i) % 255];
            }
            $generador = $nuevo;
        }

        $residuo = array_merge($datos, array_fill(0, $cantidad, 0));

        for ($i = 0; $i < count($datos); $i++) {
            $factor = $residuo[$i];
            if ($factor === 0) {
                continue;
            }

            $logFactor = $log[$factor];
            foreach ($generador as $j => $coeficiente) {
                if ($coeficiente !== 0) {
                    $residuo[$i + $j] ^= $exp[($log[$coeficiente] + $logFactor) % 255];
                }
            }
        }

        return array_values(array_slice($residuo, count($datos)));
    }

    /**
     * Tablas de exponentes y logaritmos de GF(256) con el polinomio 0x11D.
     *
     * @return array{0:list<int>,1:array<int,int>}
     */
    private static function tablasGalois(): array
    {
        static $exp = null;
        static $log = null;

        if ($exp !== null && $log !== null) {
            return [$exp, $log];
        }

        $exp = array_fill(0, 256, 0);
        $log = array_fill(0, 256, 0);
        $x   = 1;

        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }

        return [$exp, $log];
    }

    // ------------------------------------------------------------- dibujo

    /** @param list<list<bool>> $reservado */
    private function dibujarPatronesFijos(array &$reservado): void
    {
        // Patrones de deteccion de posicion y separadores
        foreach ([[0, 0], [$this->tamano - 7, 0], [0, $this->tamano - 7]] as [$x, $y]) {
            $this->dibujarBuscador($x, $y, $reservado);
        }

        // Patrones de sincronizacion
        for ($i = 8; $i < $this->tamano - 8; $i++) {
            $valor = $i % 2 === 0 ? 1 : 0;
            $this->fijar($i, 6, $valor, $reservado);
            $this->fijar(6, $i, $valor, $reservado);
        }

        // Patrones de alineacion
        $centros = self::ALINEACION[$this->version];
        foreach ($centros as $cy) {
            foreach ($centros as $cx) {
                if ($this->esEsquinaDeBuscador($cx, $cy)) {
                    continue;
                }
                $this->dibujarAlineacion($cx, $cy, $reservado);
            }
        }

        // Modulo oscuro fijo
        $this->fijar(8, $this->tamano - 8, 1, $reservado);

        // Zonas reservadas para la informacion de formato
        for ($i = 0; $i < 9; $i++) {
            if ($i !== 6) {
                $this->reservar(8, $i, $reservado);
                $this->reservar($i, 8, $reservado);
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $this->reservar($this->tamano - 1 - $i, 8, $reservado);
            $this->reservar(8, $this->tamano - 1 - $i, $reservado);
        }

        // Zonas reservadas para la informacion de version
        if ($this->version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $this->reservar($this->tamano - 11 + $j, $i, $reservado);
                    $this->reservar($i, $this->tamano - 11 + $j, $reservado);
                }
            }
        }
    }

    private function esEsquinaDeBuscador(int $cx, int $cy): bool
    {
        $limite = $this->tamano - 7;

        return ($cx < 8 && $cy < 8)
            || ($cx < 8 && $cy >= $limite)
            || ($cx >= $limite && $cy < 8);
    }

    /** @param list<list<bool>> $reservado */
    private function dibujarBuscador(int $x0, int $y0, array &$reservado): void
    {
        for ($y = -1; $y <= 7; $y++) {
            for ($x = -1; $x <= 7; $x++) {
                $px = $x0 + $x;
                $py = $y0 + $y;

                if ($px < 0 || $py < 0 || $px >= $this->tamano || $py >= $this->tamano) {
                    continue;
                }

                $enBorde  = ($x === 0 || $x === 6) && $y >= 0 && $y <= 6;
                $enBorde  = $enBorde || (($y === 0 || $y === 6) && $x >= 0 && $x <= 6);
                $enCentro = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;

                $this->fijar($px, $py, ($enBorde || $enCentro) ? 1 : 0, $reservado);
            }
        }
    }

    /** @param list<list<bool>> $reservado */
    private function dibujarAlineacion(int $cx, int $cy, array &$reservado): void
    {
        for ($y = -2; $y <= 2; $y++) {
            for ($x = -2; $x <= 2; $x++) {
                $borde = abs($x) === 2 || abs($y) === 2;
                $this->fijar($cx + $x, $cy + $y, ($borde || ($x === 0 && $y === 0)) ? 1 : 0, $reservado);
            }
        }
    }

    /** @param list<list<bool>> $reservado */
    private function fijar(int $x, int $y, int $valor, array &$reservado): void
    {
        $this->matriz[$y][$x] = $valor;
        $reservado[$y][$x]    = true;
    }

    /** @param list<list<bool>> $reservado */
    private function reservar(int $x, int $y, array &$reservado): void
    {
        $reservado[$y][$x] = true;
    }

    /**
     * Recorrido en zigzag de derecha a izquierda, dos columnas a la vez.
     *
     * @param list<int> $codewords
     * @param list<list<bool>> $reservado
     */
    private function colocarDatos(array $codewords, array $reservado): void
    {
        $bits = '';
        foreach ($codewords as $codeword) {
            $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        }

        $indice = 0;
        $arriba = true;

        for ($columna = $this->tamano - 1; $columna > 0; $columna -= 2) {
            if ($columna === 6) {
                $columna--;   // la columna 6 es el patron de sincronizacion
            }

            for ($paso = 0; $paso < $this->tamano; $paso++) {
                $y = $arriba ? $this->tamano - 1 - $paso : $paso;

                foreach ([$columna, $columna - 1] as $x) {
                    if ($reservado[$y][$x]) {
                        continue;
                    }

                    $this->matriz[$y][$x] = $indice < strlen($bits) ? (int) $bits[$indice] : 0;
                    $indice++;
                }
            }

            $arriba = !$arriba;
        }
    }

    /** @param list<list<bool>> $reservado */
    private function mejorMascara(array $reservado): int
    {
        $mejor      = 0;
        $menorCosto = PHP_INT_MAX;
        $original   = $this->matriz;

        for ($mascara = 0; $mascara < 8; $mascara++) {
            $this->matriz = $original;
            $this->aplicarMascara($mascara, $reservado);
            $this->dibujarFormato($mascara);

            $costo = $this->penalizacion();

            if ($costo < $menorCosto) {
                $menorCosto = $costo;
                $mejor      = $mascara;
            }
        }

        $this->matriz = $original;

        return $mejor;
    }

    /** @param list<list<bool>> $reservado */
    private function aplicarMascara(int $mascara, array $reservado): void
    {
        for ($y = 0; $y < $this->tamano; $y++) {
            for ($x = 0; $x < $this->tamano; $x++) {
                if ($reservado[$y][$x]) {
                    continue;
                }

                $invertir = match ($mascara) {
                    0 => ($y + $x) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($y + $x) % 3 === 0,
                    4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
                    5 => (($y * $x) % 2) + (($y * $x) % 3) === 0,
                    6 => ((($y * $x) % 2) + (($y * $x) % 3)) % 2 === 0,
                    7 => ((($y + $x) % 2) + (($y * $x) % 3)) % 2 === 0,
                    default => false,
                };

                if ($invertir) {
                    $this->matriz[$y][$x] ^= 1;
                }
            }
        }
    }

    private function dibujarFormato(int $mascara): void
    {
        $datos = (self::BITS_NIVEL[$this->nivel] << 3) | $mascara;
        $resto = $datos << 10;

        for ($i = 14; $i >= 10; $i--) {
            if ((($resto >> $i) & 1) === 1) {
                $resto ^= 0b10100110111 << ($i - 10);
            }
        }

        $formato = (($datos << 10) | $resto) ^ 0b101010000010010;

        for ($i = 0; $i < 15; $i++) {
            $bit = ($formato >> $i) & 1;

            // Copia junto al buscador superior izquierdo
            if ($i < 6) {
                $this->matriz[$i][8] = $bit;
            } elseif ($i === 6) {
                $this->matriz[7][8] = $bit;
            } elseif ($i === 7) {
                $this->matriz[8][8] = $bit;
            } elseif ($i === 8) {
                $this->matriz[8][7] = $bit;
            } else {
                $this->matriz[8][14 - $i] = $bit;
            }

            // Copia repartida entre los otros dos buscadores
            if ($i < 8) {
                $this->matriz[8][$this->tamano - 1 - $i] = $bit;
            } else {
                $this->matriz[$this->tamano - 15 + $i][8] = $bit;
            }
        }
    }

    private function dibujarVersion(): void
    {
        $resto = $this->version << 12;

        for ($i = 17; $i >= 12; $i--) {
            if ((($resto >> $i) & 1) === 1) {
                $resto ^= 0b1111100100101 << ($i - 12);
            }
        }

        $informacion = ($this->version << 12) | $resto;

        for ($i = 0; $i < 18; $i++) {
            $bit = ($informacion >> $i) & 1;
            $fila = intdiv($i, 3);
            $col  = $i % 3;

            $this->matriz[$fila][$this->tamano - 11 + $col] = $bit;
            $this->matriz[$this->tamano - 11 + $col][$fila] = $bit;
        }
    }

    /** Penalizacion de la norma para elegir la mascara con menos ruido visual. */
    private function penalizacion(): int
    {
        $total = 0;
        $n     = $this->tamano;

        // Regla 1: corridas de 5 o mas modulos del mismo color
        for ($i = 0; $i < $n; $i++) {
            foreach ([true, false] as $porFila) {
                $anterior = -1;
                $corrida  = 0;

                for ($j = 0; $j < $n; $j++) {
                    $valor = $porFila ? $this->matriz[$i][$j] : $this->matriz[$j][$i];

                    if ($valor === $anterior) {
                        $corrida++;
                    } else {
                        if ($corrida >= 5) {
                            $total += 3 + ($corrida - 5);
                        }
                        $anterior = $valor;
                        $corrida  = 1;
                    }
                }

                if ($corrida >= 5) {
                    $total += 3 + ($corrida - 5);
                }
            }
        }

        // Regla 2: bloques de 2x2 del mismo color
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $valor = $this->matriz[$y][$x];
                if ($valor === $this->matriz[$y][$x + 1]
                    && $valor === $this->matriz[$y + 1][$x]
                    && $valor === $this->matriz[$y + 1][$x + 1]
                ) {
                    $total += 3;
                }
            }
        }

        // Regla 3: falsos patrones de deteccion (1:1:3:1:1 con cuatro modulos
        // claros a un lado). Se extiende la linea con cuatro modulos claros en
        // cada extremo porque la zona de silencio que rodea al simbolo tambien
        // es clara: sin esto no se detectan los falsos buscadores pegados al
        // borde, que son justamente los que confunden a los lectores.
        $patronA = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $patronB = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
        $silencio = [0, 0, 0, 0];

        for ($i = 0; $i < $n; $i++) {
            $columna = [];
            for ($k = 0; $k < $n; $k++) {
                $columna[] = $this->matriz[$k][$i];
            }

            foreach ([$this->matriz[$i], $columna] as $linea) {
                $extendida = array_merge($silencio, $linea, $silencio);
                $largo     = count($extendida);

                for ($j = 0; $j <= $largo - 11; $j++) {
                    $ventana = array_slice($extendida, $j, 11);

                    if ($ventana === $patronA || $ventana === $patronB) {
                        $total += 40;
                    }
                }
            }
        }

        // Regla 4: desbalance entre modulos oscuros y claros
        $oscuros = 0;
        foreach ($this->matriz as $fila) {
            $oscuros += array_sum($fila);
        }

        $porcentaje = ($oscuros * 100) / ($n * $n);
        $total     += intdiv((int) abs($porcentaje - 50), 5) * 10;

        return $total;
    }
}
