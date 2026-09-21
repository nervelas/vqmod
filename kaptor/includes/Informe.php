<?php
/**
 * Kaptor - Del montón de hallazgos al informe que se entrega.
 *
 * Aquí se hacen tres cosas:
 *   1. Poner nota por área y una nota global.
 *   2. Ordenar los problemas por lo que de verdad importa, para poder decir
 *      "arregla ESTOS TRES primero" en vez de soltar una lista de cincuenta.
 *   3. Traducir la nota a un veredicto en castellano.
 *
 * Lo tercero parece lo menos técnico y es lo que hace que el informe se venda.
 */
declare(strict_types=1);

final class Informe
{
    /**
     * Techo de la nota cuando el sitio tiene algo grave (código malicioso,
     * contenido encubierto, marcado por Google). Por encima de esto, la nota
     * transmitiría tranquilidad donde no la hay.
     */
    public const TOPE_CRITICO = 30;

    /**
     * Notas de 0 a 100.
     *
     * Un hallazgo 'bien' suma su peso entero; uno 'aviso', la mitad; uno 'mal',
     * nada. Los que no se pudieron medir salen del reparto para no castigar a
     * un sitio por algo que nunca se comprobó.
     *
     * @return array{global:int,areas:array<string,int>}
     */
    public static function notas(array $hallazgos): array
    {
        $suma = [];
        $tope = [];

        foreach ($hallazgos as $h) {
            $area = (string) ($h['area'] ?? '');
            if ($area === '' || ($h['estado'] ?? '') === Chequeos::NA) { continue; }

            $peso = (int) ($h['peso'] ?? 0);
            $tope[$area] = ($tope[$area] ?? 0) + $peso;
            $suma[$area] = ($suma[$area] ?? 0) + match ($h['estado']) {
                Chequeos::BIEN  => $peso,
                Chequeos::AVISO => $peso / 2,
                default         => 0,
            };
        }

        $areas = [];
        foreach (Chequeos::AREAS as $clave => $_) {
            $areas[$clave] = ($tope[$clave] ?? 0) > 0
                ? (int) round(($suma[$clave] / $tope[$clave]) * 100)
                : 0;
        }

        // La nota global pondera cada área por lo que pesa en el negocio, y
        // solo cuenta las áreas que se pudieron medir.
        $acum = 0.0;
        $pesoTotal = 0;
        foreach (Chequeos::AREAS as $clave => $info) {
            if (($tope[$clave] ?? 0) <= 0) { continue; }
            $acum += $areas[$clave] * $info['peso'];
            $pesoTotal += $info['peso'];
        }

        $global = $pesoTotal > 0 ? (int) round($acum / $pesoTotal) : 0;

        // Un sitio infectado no puede sacar buena nota por tener bien puestas
        // las etiquetas. Cuando hay un hallazgo crítico la nota global se tapa,
        // porque promediarlo con lo demás daría una cifra que miente.
        if (self::hayCritico($hallazgos)) { $global = min($global, self::TOPE_CRITICO); }

        return ['global' => $global, 'areas' => $areas];
    }

    /** ¿Hay algún hallazgo crítico sin resolver? */
    public static function hayCritico(array $hallazgos): bool
    {
        foreach ($hallazgos as $h) {
            if (!empty($h['critico']) && ($h['estado'] ?? '') === Chequeos::MAL) { return true; }
        }
        return false;
    }

    /** Los críticos, para el aviso de arriba del informe. */
    public static function criticos(array $hallazgos): array
    {
        return array_values(array_filter(
            $hallazgos,
            static fn($h) => !empty($h['critico']) && ($h['estado'] ?? '') === Chequeos::MAL
        ));
    }

    /**
     * Los problemas, del más caro al más barato de ignorar.
     *
     * El orden es: primero lo que está mal, luego los avisos; y dentro de cada
     * grupo, lo que más pesa en el área que más pesa en el negocio.
     *
     * @return array<int,array>
     */
    public static function problemas(array $hallazgos, int $limite = 0): array
    {
        $malos = array_values(array_filter(
            $hallazgos,
            static fn($h) => in_array($h['estado'] ?? '', [Chequeos::MAL, Chequeos::AVISO], true)
        ));

        usort($malos, static function ($a, $b) {
            // Lo crítico manda sobre todo lo demás.
            $ca = !empty($a['critico']) && $a['estado'] === Chequeos::MAL ? 0 : 1;
            $cb = !empty($b['critico']) && $b['estado'] === Chequeos::MAL ? 0 : 1;
            if ($ca !== $cb) { return $ca <=> $cb; }

            // 'mal' siempre antes que 'aviso'.
            $ra = $a['estado'] === Chequeos::MAL ? 0 : 1;
            $rb = $b['estado'] === Chequeos::MAL ? 0 : 1;
            if ($ra !== $rb) { return $ra <=> $rb; }

            return self::urgencia($b) <=> self::urgencia($a);
        });

        return $limite > 0 ? array_slice($malos, 0, $limite) : $malos;
    }

    /** Cuánto duele este hallazgo: su peso multiplicado por el de su área. */
    private static function urgencia(array $h): float
    {
        $pesoArea = Chequeos::AREAS[$h['area'] ?? '']['peso'] ?? 1;
        return ((int) ($h['peso'] ?? 0)) * $pesoArea;
    }

    /** Lo que está bien, para que el informe no sea solo malas noticias. */
    public static function aciertos(array $hallazgos): array
    {
        $buenos = array_values(array_filter($hallazgos, static fn($h) => ($h['estado'] ?? '') === Chequeos::BIEN));
        usort($buenos, static fn($a, $b) => self::urgencia($b) <=> self::urgencia($a));
        return $buenos;
    }

    /** Agrupa los hallazgos por área, en el orden de Chequeos::AREAS. */
    public static function porArea(array $hallazgos): array
    {
        $grupos = [];
        foreach (array_keys(Chequeos::AREAS) as $area) { $grupos[$area] = []; }
        foreach ($hallazgos as $h) {
            $area = (string) ($h['area'] ?? '');
            if (isset($grupos[$area])) { $grupos[$area][] = $h; }
        }
        // Dentro de cada área, primero lo que hay que arreglar.
        foreach ($grupos as $area => $lista) {
            usort($lista, static function ($a, $b) {
                $orden = [Chequeos::MAL => 0, Chequeos::AVISO => 1, Chequeos::BIEN => 2, Chequeos::NA => 3];
                $ra = $orden[$a['estado']] ?? 3;
                $rb = $orden[$b['estado']] ?? 3;
                if ($ra !== $rb) { return $ra <=> $rb; }
                return ((int) $b['peso']) <=> ((int) $a['peso']);
            });
            $grupos[$area] = $lista;
        }
        return array_filter($grupos, static fn($l) => $l !== []);
    }

    /** Cuántos hay de cada estado. */
    public static function recuento(array $hallazgos): array
    {
        $r = [Chequeos::MAL => 0, Chequeos::AVISO => 0, Chequeos::BIEN => 0];
        foreach ($hallazgos as $h) {
            $e = (string) ($h['estado'] ?? '');
            if (isset($r[$e])) { $r[$e]++; }
        }
        return $r;
    }

    // =====================================================================
    //  Cómo se cuenta la nota
    // =====================================================================

    /**
     * La nota que manda en cada modo.
     *
     * En el análisis completo, la global. Pero si alguien pidió un análisis de
     * SEO, el número que quiere ver es el de SEO: decirle "tu sitio saca 66"
     * cuando ese 66 incluye la velocidad y el botón de WhatsApp no responde a
     * lo que preguntó. En los modos a fondo manda el área, y la global queda
     * de acompañamiento.
     */
    public static function notaDeModo(string $modo, int $global, array $areas): int
    {
        return match ($modo) {
            'seo'     => (int) ($areas['seo'] ?? $global),
            'malware' => (int) ($areas['malware'] ?? $global),
            default   => $global,
        };
    }

    /** Cómo se llama la nota que se está enseñando. */
    public static function nombreNota(string $modo): string
    {
        return match ($modo) {
            'seo'     => 'Nota de SEO',
            'malware' => 'Seguridad del sitio',
            default   => 'Nota global',
        };
    }

    /**
     * Las áreas que entran en el plan de mejora de cada modo.
     *
     * Tiene que ser EXACTAMENTE el área de la nota que se está enseñando: si
     * el plan abarcara más áreas que la nota, la cuenta dejaría de cuadrar y
     * la suma de los arreglos no daría cien.
     */
    public static function areasDeModo(string $modo): array
    {
        return match ($modo) {
            'seo'     => ['seo'],
            'malware' => ['malware'],
            default   => [],
        };
    }

    /** Color del semáforo: verde, ambar o rojo. */
    public static function color(int $nota): string
    {
        if ($nota >= 80) { return 'verde'; }
        if ($nota >= 55) { return 'ambar'; }
        return 'rojo';
    }

    /** Una palabra para la nota. */
    public static function etiqueta(int $nota): string
    {
        if ($nota >= 90) { return 'Excelente'; }
        if ($nota >= 80) { return 'Bien'; }
        if ($nota >= 65) { return 'Aceptable'; }
        if ($nota >= 45) { return 'Deficiente'; }
        if ($nota >= 25) { return 'Malo'; }
        return 'Crítico';
    }

    /**
     * El párrafo de arriba del informe: el que lee el dueño del negocio antes
     * de decidir si sigue leyendo.
     */
    public static function veredicto(int $nota, array $hallazgos): string
    {
        $r = self::recuento($hallazgos);
        $graves = $r[Chequeos::MAL];

        // Con algo crítico encima, lo demás no es la noticia.
        $criticos = self::criticos($hallazgos);
        if ($criticos) {
            return 'El sitio tiene un problema grave que hay que atender antes que cualquier otra cosa: '
                . mb_strtolower(mb_substr($criticos[0]['titulo'], 0, 1), 'UTF-8')
                . mb_substr($criticos[0]['titulo'], 1) . '. '
                . 'Mientras eso siga así, ninguna mejora de diseño ni de publicidad va a servir de nada.';
        }

        if ($nota >= 90) {
            return 'El sitio está en muy buen estado. Lo que queda son detalles de afinado, no problemas.';
        }
        if ($nota >= 80) {
            return 'El sitio está bien construido. Hay ' . $graves . ' ' . ($graves === 1 ? 'punto' : 'puntos')
                . ' que conviene corregir para no ceder terreno frente a la competencia.';
        }
        if ($nota >= 65) {
            return 'El sitio funciona, pero está dejando oportunidades sobre la mesa. Con ' . $graves
                . ' ' . ($graves === 1 ? 'corrección' : 'correcciones') . ' subiría de forma notable.';
        }
        if ($nota >= 45) {
            return 'El sitio tiene problemas que le están costando visitas y clientes todos los días. '
                . 'Hay ' . $graves . ' ' . ($graves === 1 ? 'fallo importante' : 'fallos importantes')
                . ' que se pueden corregir sin rehacer nada.';
        }
        return 'El sitio tiene fallos graves que lo están dejando fuera de las búsquedas y espantando visitas. '
            . 'Son ' . $graves . ' ' . ($graves === 1 ? 'problema serio' : 'problemas serios')
            . ': conviene atenderlos antes de invertir un quetzal en publicidad, porque hoy ese dinero se perdería.';
    }

    /**
     * Comparativa con los competidores: en qué áreas se gana y en cuáles se pierde.
     *
     * @param array $principal Fila de la auditoría principal
     * @param array $rivales   Filas de las auditorías de los competidores
     */
    public static function comparativa(array $principal, array $rivales): array
    {
        $mias = self::areasDe($principal);
        $filas = [];

        foreach (Chequeos::AREAS as $clave => $info) {
            $fila = ['area' => $clave, 'nombre' => $info['nombre'], 'mia' => $mias[$clave] ?? 0, 'rivales' => []];
            foreach ($rivales as $r) {
                $suyas = self::areasDe($r);
                $fila['rivales'][] = ['host' => (string) $r['host'], 'nota' => $suyas[$clave] ?? 0];
            }
            $notasRivales = array_map(static fn($x) => $x['nota'], $fila['rivales']);
            $fila['mejor_rival'] = $notasRivales ? max($notasRivales) : 0;
            $fila['gana'] = $fila['mia'] >= $fila['mejor_rival'];
            $filas[] = $fila;
        }
        return $filas;
    }

    /** Notas por área guardadas en una fila. */
    public static function areasDe(array $fila): array
    {
        $j = json_decode((string) ($fila['notas_area'] ?? ''), true);
        return is_array($j) ? $j : [];
    }

    /** Hallazgos guardados en una fila. */
    public static function hallazgosDe(array $fila): array
    {
        $j = json_decode((string) ($fila['hallazgos'] ?? ''), true);
        return is_array($j) ? $j : [];
    }

    /** Datos recogidos guardados en una fila. */
    public static function datosDe(array $fila): array
    {
        $j = json_decode((string) ($fila['datos'] ?? ''), true);
        return is_array($j) ? $j : [];
    }
}
