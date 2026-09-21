<?php
/**
 * Kaptor - El mapa del sitio.
 *
 * Para analizar un sitio ENTERO no basta con seguir enlaces desde la portada:
 * así se llega a lo que está bien enlazado y se queda fuera todo lo demás, que
 * suele ser justo donde están los problemas. El atajo bueno es el mapa del
 * sitio, que es la lista que el propio sitio le entrega a Google.
 *
 * Aquí se hace lo que hacen los rastreadores serios:
 *
 *   1. Se busca el mapa donde puede estar: lo que declare robots.txt (que es
 *      lo que manda) y, si no dice nada, las rutas de siempre, incluidas las
 *      que usan WordPress y los plugins más extendidos.
 *   2. Se siguen los ÍNDICES de mapas. Un sitio grande no tiene un mapa: tiene
 *      un índice que apunta a diez, y cada uno con mil direcciones. Leer solo
 *      el primero y parar es quedarse con el 10 % del sitio.
 *   3. Se descomprimen los .xml.gz, que es como los sirve media Internet.
 *
 * Todo con topes: un sitio puede declarar cien mil direcciones y esto corre en
 * un hosting compartido.
 */
declare(strict_types=1);

final class Mapa
{
    /** Niveles de índices que se siguen (un índice que apunta a otro índice). */
    public const MAX_PROFUNDIDAD = 3;

    /** Mapas que se llegan a abrir en total. */
    public const MAX_MAPAS = 40;

    /** Direcciones que se guardan como mucho. */
    public const MAX_URLS = 5000;

    /**
     * Rutas donde suele vivir el mapa cuando robots.txt no lo dice.
     *
     * El orden importa: primero los índices, que traen el sitio entero, y
     * luego los mapas sueltos.
     */
    private const RUTAS = [
        '/sitemap_index.xml',      // Yoast y RankMath, los dos más usados
        '/sitemap-index.xml',
        '/sitemap.xml',
        '/wp-sitemap.xml',         // WordPress desde la versión 5.5
        '/sitemapindex.xml',
        '/sitemap/sitemap.xml',
        '/sitemap1.xml',
        '/sitemap.xml.gz',
        '/sitemap.php',            // algunos gestores lo generan al vuelo
    ];

    /**
     * Busca el mapa y devuelve TODAS las direcciones que declara.
     *
     * @param string $raiz      Esquema y host, sin barra final
     * @param string $robotsTxt Contenido de robots.txt (puede venir vacío)
     * @param int    $tope      Cuántas direcciones interesan como mucho
     *
     * @return array{
     *   existe:bool, urls:string[], mapas:string[],
     *   declaradas:int, recortado:bool, origen:string
     * }
     */
    public static function descubrir(string $raiz, string $robotsTxt = '', int $tope = self::MAX_URLS): array
    {
        $vacio = [
            'existe' => false, 'urls' => [], 'mapas' => [],
            'declaradas' => 0, 'recortado' => false, 'origen' => '',
        ];
        if ($raiz === '') { return $vacio; }

        $tope = max(10, min(self::MAX_URLS, $tope));

        // 1) Lo que diga robots.txt manda: es donde el sitio lo declara.
        $candidatos = [];
        if (preg_match_all('~^\s*sitemap:\s*(\S+)~mi', $robotsTxt, $m)) {
            foreach ($m[1] as $u) {
                $u = trim($u);
                if ($u !== '') { $candidatos[] = $u; }
            }
        }
        $origen = $candidatos ? 'robots.txt' : '';

        // 2) Y si no, las rutas de siempre.
        foreach (self::RUTAS as $ruta) { $candidatos[] = $raiz . $ruta; }

        $urls      = [];
        $abiertos  = [];
        $pendientes = [];
        $declaradas = 0;

        // Se prueban los candidatos hasta que uno conteste algo aprovechable.
        foreach (array_values(array_unique($candidatos)) as $cand) {
            if (count($abiertos) >= self::MAX_MAPAS) { break; }

            $leido = self::leer($cand, $raiz);
            if (!$leido['ok']) { continue; }

            $abiertos[] = $cand;
            if ($origen === '') { $origen = 'ruta habitual'; }

            if ($leido['es_indice']) {
                // Un índice: sus entradas son OTROS mapas.
                foreach ($leido['urls'] as $hijo) { $pendientes[] = ['url' => $hijo, 'nivel' => 1]; }
            } else {
                $declaradas += count($leido['urls']);
                foreach ($leido['urls'] as $u) { $urls[$u] = true; }
            }

            // Con un mapa bueno encontrado, no hace falta seguir probando
            // rutas a ciegas; lo que queda por abrir son sus hijos.
            if ($leido['urls']) { break; }
        }

        // 3) Los hijos del índice, y los hijos de los hijos.
        while ($pendientes && count($abiertos) < self::MAX_MAPAS && count($urls) < $tope) {
            $item = array_shift($pendientes);
            if ($item['nivel'] > self::MAX_PROFUNDIDAD) { continue; }
            if (in_array($item['url'], $abiertos, true)) { continue; }

            $leido = self::leer($item['url'], $raiz);
            $abiertos[] = $item['url'];
            if (!$leido['ok']) { continue; }

            if ($leido['es_indice']) {
                foreach ($leido['urls'] as $hijo) {
                    $pendientes[] = ['url' => $hijo, 'nivel' => $item['nivel'] + 1];
                }
            } else {
                $declaradas += count($leido['urls']);
                foreach ($leido['urls'] as $u) {
                    if (count($urls) >= $tope) { break; }
                    $urls[$u] = true;
                }
            }
        }

        $lista = array_keys($urls);

        return [
            'existe'     => $lista !== [] || $abiertos !== [],
            'urls'       => $lista,
            'mapas'      => $abiertos,
            'declaradas' => max($declaradas, count($lista)),
            'recortado'  => $declaradas > count($lista),
            'origen'     => $origen,
        ];
    }

    /**
     * Lee un mapa (o un índice de mapas).
     *
     * @return array{ok:bool,es_indice:bool,urls:string[]}
     */
    public static function leer(string $url, string $raiz): array
    {
        $fallo = ['ok' => false, 'es_indice' => false, 'urls' => []];

        $r = Http::obtener($url, ['timeout' => 15, 'max_bytes' => 12000000]);
        if (!$r['ok'] || $r['cuerpo'] === '') { return $fallo; }

        $cuerpo = $r['cuerpo'];

        // Los .xml.gz llegan comprimidos como ARCHIVO, no como codificación de
        // transporte, así que cURL no los descomprime: hay que hacerlo aquí.
        if (str_starts_with($cuerpo, "\x1f\x8b")) {
            $abierto = @gzdecode($cuerpo);
            if ($abierto === false || $abierto === '') { return $fallo; }
            $cuerpo = $abierto;
        }

        // Una página de error devuelta con código 200 no es un mapa.
        if (!preg_match('~<(?:urlset|sitemapindex)\b~i', $cuerpo)) { return $fallo; }

        $esIndice = (bool) preg_match('~<sitemapindex\b~i', $cuerpo);

        $urls = [];
        if (preg_match_all('~<loc>\s*(.*?)\s*</loc>~is', $cuerpo, $m)) {
            foreach ($m[1] as $bruto) {
                $u = html_entity_decode(trim(strip_tags($bruto)), ENT_QUOTES | ENT_XML1 | ENT_HTML5, 'UTF-8');
                if ($u === '' || stripos($u, 'http') !== 0) { continue; }
                // Solo del mismo sitio: hay mapas que enlazan dominios ajenos.
                if (!self::mismoSitio($u, $raiz)) { continue; }
                $urls[$u] = true;
            }
        }

        return ['ok' => true, 'es_indice' => $esIndice, 'urls' => array_keys($urls)];
    }

    /**
     * ¿La dirección pertenece al sitio?
     *
     * Se compara solo el host, no el esquema ni el puerto: muchos mapas
     * declaran http cuando el sitio ya va por https, o se olvidan del "www",
     * y descartarlos por eso dejaría el análisis a cero.
     */
    private static function mismoSitio(string $url, string $raiz): bool
    {
        $a = strtolower((string) parse_url($url, PHP_URL_HOST));
        $b = strtolower((string) parse_url($raiz, PHP_URL_HOST));
        if ($a === '' || $b === '') { return false; }

        $a = preg_replace('~^www\.~', '', $a) ?? $a;
        $b = preg_replace('~^www\.~', '', $b) ?? $b;

        return $a === $b || str_ends_with($a, '.' . $b) || str_ends_with($b, '.' . $a);
    }
}
