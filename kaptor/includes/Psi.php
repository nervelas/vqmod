<?php
/**
 * Kaptor - Datos de velocidad de Google (PageSpeed Insights).
 *
 * El auditor mide por su cuenta todo lo que se puede medir desde PHP, pero hay
 * cosas que solo se saben abriendo la página en un navegador de verdad: cuánto
 * tarda en pintarse, cuánto se mueve el diseño mientras carga, y sobre todo qué
 * NOTA le pone Google, que es la que decide posiciones.
 *
 * Eso no se puede hacer en un hosting compartido, así que se le pregunta a
 * Google. Su interfaz es gratuita: 25.000 consultas al día con una clave propia
 * (console.cloud.google.com, servicio "PageSpeed Insights API"). Sin clave
 * también responde, pero la cuota es compartida entre todo el mundo y casi
 * siempre está agotada.
 *
 * Si no hay clave, si Google no contesta o si se agota la cuota, el auditor
 * sigue adelante con sus propias mediciones: los datos de Google SUMAN, nunca
 * son imprescindibles.
 */
declare(strict_types=1);

final class Psi
{
    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /** Google tarda entre 10 y 40 segundos en contestar: es normal. */
    public const TIMEOUT = 60;

    /**
     * Pide el análisis de una dirección.
     *
     * @param string $estrategia 'mobile' u 'desktop'
     * @return array|null null si no se pudo consultar (y entonces no se usa)
     */
    public static function analizar(string $url, string $estrategia = 'mobile'): ?array
    {
        if (!Ajustes::activo('psi_activo', true)) { return null; }

        $clave = trim(Ajustes::obtener('psi_clave'));
        $consulta = [
            'url'      => $url,
            'strategy' => $estrategia === 'desktop' ? 'desktop' : 'mobile',
            'locale'   => 'es',
        ];
        if ($clave !== '') { $consulta['key'] = $clave; }

        // Las categorías se repiten como parámetro, que http_build_query no sabe
        // generar: se añaden a mano.
        $destino = self::ENDPOINT . '?' . http_build_query($consulta)
            . '&category=performance&category=accessibility&category=seo&category=best-practices';

        $r = Http::obtener($destino, [
            'timeout'   => self::TIMEOUT,
            'max_bytes' => 8000000,      // la respuesta de Google es enorme
            'cabeceras' => ['Accept: application/json'],
        ]);

        if ($r['cuerpo'] === '') { return null; }

        $j = json_decode($r['cuerpo'], true);
        if (!is_array($j)) { return null; }

        if (isset($j['error'])) {
            // 429 = cuota agotada; es el caso normal cuando no hay clave propia.
            $codigo = (int) ($j['error']['code'] ?? 0);
            error_log('Kaptor / PageSpeed ' . $codigo . ': ' . (string) ($j['error']['message'] ?? ''));
            return ['error' => self::mensajeError($codigo, $clave !== '')];
        }

        return self::extraer($j);
    }

    /** Traduce el fallo de Google a algo que el usuario pueda resolver. */
    private static function mensajeError(int $codigo, bool $conClave): string
    {
        if ($codigo === 429) {
            return $conClave
                ? 'Se agotó la cuota diaria de tu clave de Google (25.000 consultas).'
                : 'La cuota de Google sin clave está agotada. Pon tu propia clave gratuita en Ajustes para obtener los datos de velocidad.';
        }
        if ($codigo === 400) {
            return 'Google no pudo abrir esa dirección (puede estar caída o bloquear robots).';
        }
        if ($codigo === 403) {
            return 'Google rechazó la clave. Revisa que la clave sea válida y que el servicio PageSpeed Insights esté activado.';
        }
        return 'Google no pudo analizar la página en este momento.';
    }

    /**
     * Se queda solo con lo que el informe usa.
     *
     * La respuesta de Google trae megas de detalle; guardarla entera en la base
     * de datos por cada auditoría no tiene sentido.
     */
    private static function extraer(array $j): array
    {
        $out = [];
        $lh  = $j['lighthouseResult'] ?? [];

        // --- Notas de 0 a 100 -------------------------------------------------
        $cats = [
            'rendimiento'   => 'performance',
            'accesibilidad' => 'accessibility',
            'buenas'        => 'best-practices',
            'seo'           => 'seo',
        ];
        foreach ($cats as $nuestro => $suyo) {
            $nota = $lh['categories'][$suyo]['score'] ?? null;
            // Google devuelve 0..1, o null cuando esa categoría no se pudo medir.
            if (is_numeric($nota)) { $out[$nuestro] = (int) round(((float) $nota) * 100); }
        }

        // --- Mediciones de laboratorio ------------------------------------------
        $aud = $lh['audits'] ?? [];
        $num = static function (array $aud, string $clave) {
            $v = $aud[$clave]['numericValue'] ?? null;
            return is_numeric($v) ? (float) $v : null;
        };

        $lcp = $num($aud, 'largest-contentful-paint');
        $cls = $num($aud, 'cumulative-layout-shift');
        $tbt = $num($aud, 'total-blocking-time');
        $fcp = $num($aud, 'first-contentful-paint');

        if ($lcp !== null) { $out['lcp'] = (int) round($lcp); }
        if ($cls !== null) { $out['cls'] = round($cls, 3); }
        if ($tbt !== null) { $out['tbt'] = (int) round($tbt); }
        if ($fcp !== null) { $out['fcp'] = (int) round($fcp); }

        // --- Datos de usuarios reales (CrUX) -------------------------------------
        // Solo existen si el sitio tiene tráfico suficiente. Cuando están, valen
        // más que los de laboratorio: son personas de verdad entrando al sitio.
        $campo = $j['loadingExperience']['metrics'] ?? null;
        if (is_array($campo)) {
            $real = [];
            if (isset($campo['LARGEST_CONTENTFUL_PAINT_MS']['percentile'])) {
                $real['lcp'] = (int) $campo['LARGEST_CONTENTFUL_PAINT_MS']['percentile'];
            }
            if (isset($campo['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'])) {
                // Google lo manda multiplicado por cien.
                $real['cls'] = round(((int) $campo['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile']) / 100, 3);
            }
            if (isset($campo['INTERACTION_TO_NEXT_PAINT']['percentile'])) {
                $real['inp'] = (int) $campo['INTERACTION_TO_NEXT_PAINT']['percentile'];
            }
            if ($real) {
                $real['veredicto'] = (string) ($j['loadingExperience']['overall_category'] ?? '');
                $out['real'] = $real;
            }
        }

        // --- Las tres mejoras que más pesan, según Google ---------------------------
        $ahorros = [];
        foreach ($aud as $clave => $a) {
            if (!is_array($a)) { continue; }
            $ms = $a['details']['overallSavingsMs'] ?? null;
            if (!is_numeric($ms) || (float) $ms < 150) { continue; }
            $ahorros[] = [
                'titulo' => (string) ($a['title'] ?? $clave),
                'ms'     => (int) round((float) $ms),
            ];
        }
        usort($ahorros, static fn($a, $b) => $b['ms'] <=> $a['ms']);
        if ($ahorros) { $out['mejoras'] = array_slice($ahorros, 0, 5); }

        return $out;
    }
}
