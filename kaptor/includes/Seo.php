<?php
/**
 * Kaptor - Análisis SEO del sitio entero.
 *
 * Los chequeos de Chequeos::seo() miran UNA página, la portada. Esto mira el
 * sitio: lo que solo se ve comparando páginas entre sí.
 *
 * Es la diferencia entre "tu portada está bien" y "tienes el mismo título en
 * dieciocho páginas, cuarenta enlaces rotos y nueve páginas a las que no llega
 * nadie". Lo primero no vende; lo segundo es un presupuesto.
 *
 * CÓMO SE CUENTA LA NOTA
 * ----------------------
 * Las dos herramientas de referencia lo hacen distinto: una divide las páginas
 * sin errores entre el total, y la otra pondera errores y avisos con pesos
 * diferentes. Aquí se usa lo segundo, porque premiar solo los errores deja
 * fuera media docena de cosas que sí mueven posiciones.
 *
 * Y se añade lo que ninguna de las dos da hecho: el CAMINO al 100 %. Como cada
 * hallazgo tiene su peso, se puede decir exactamente cuántos puntos devuelve
 * arreglarlo. La suma de todo lo pendiente es justo lo que falta para el 100,
 * ni un punto más.
 */
declare(strict_types=1);

final class Seo
{
    /** A partir de aquí una página se considera de contenido pobre. */
    public const MIN_PALABRAS = 300;

    /** Más clics que esto desde la portada y Google ya casi no llega. */
    public const MAX_PROFUNDIDAD = 3;

    /**
     * Chequeos del sitio entero, a partir del rastreo.
     *
     * @return array<int,array>
     */
    public static function chequeos(array $d): array
    {
        $paginas = array_values(array_filter(
            $d['paginas'] ?? [],
            static fn($p) => empty($p['error'])
        ));

        // Sin rastreo no hay análisis de sitio: se devuelve vacío y la nota se
        // calcula solo con lo de la portada, que sigue valiendo.
        if (count($paginas) < 2) { return []; }

        return array_merge(
            self::cobertura($d, $paginas),
            self::duplicados($paginas),
            self::contenido($paginas),
            self::estructura($paginas),
            self::vinculos($d, $paginas),
            self::indexacion($paginas)
        );
    }

    /** Atajo para armar un hallazgo del área de SEO. */
    private static function h(string $clave, string $estado, int $peso, string $titulo,
                              string $cuesta = '', string $arreglo = '', string $valor = ''): array
    {
        return [
            'clave' => $clave, 'area' => 'seo', 'estado' => $estado, 'peso' => $peso,
            'titulo' => $titulo, 'cuesta' => $cuesta, 'arreglo' => $arreglo, 'valor' => $valor,
            'critico' => false,
        ];
    }

    /** Cuenta cuántos valores se repiten en una lista. */
    private static function repetidos(array $valores): array
    {
        $cuenta = [];
        foreach ($valores as $v) {
            $v = trim((string) $v);
            if ($v === '') { continue; }
            $cuenta[$v] = ($cuenta[$v] ?? 0) + 1;
        }
        return array_filter($cuenta, static fn($n) => $n > 1);
    }

    // =====================================================================
    //  Cuánto del sitio se llegó a ver
    // =====================================================================

    /**
     * Hasta dónde llegó el análisis.
     *
     * No puntúa: no es un fallo del sitio, es información sobre el análisis.
     * Va delante de todo porque quien lee el informe tiene derecho a saber si
     * se miró el sitio entero o una parte, antes de creerse ningún número.
     */
    private static function cobertura(array $d, array $paginas): array
    {
        $c = $d['cobertura'] ?? [];
        $mapa = $d['sitemap'] ?? [];

        $analizadas = count($paginas);
        $declaradas = (int) ($mapa['declaradas'] ?? 0);
        $pendientes = (int) ($c['en_cola'] ?? 0);

        if ($declaradas > 0) {
            $completo = $pendientes === 0 && $analizadas >= $declaradas;
            $texto = $completo
                ? 'Se analizó el sitio entero: ' . $analizadas . ' páginas'
                : 'Se analizaron ' . $analizadas . ' de las ' . number_format($declaradas, 0, ',', '.')
                  . ' páginas que declara el mapa del sitio';
            $arreglo = $completo ? '' :
                'Para cubrirlo entero, sube el tope de páginas en Ajustes → Auditor. '
                . 'Los fallos encontrados suelen repetirse en las demás páginas, así que '
                . 'lo que sale aquí vale igual: solo cambia el recuento.';

            return [self::h('cobertura', Chequeos::NA, 0, $texto, '', $arreglo,
                $analizadas . ' de ' . number_format($declaradas, 0, ',', '.')
                . ($mapa['origen'] ? ' · mapa hallado por ' . $mapa['origen'] : ''))];
        }

        // Sin mapa, lo único seguro es lo que se alcanzó navegando.
        return [self::h('cobertura', Chequeos::NA, 0,
            'Se analizaron ' . $analizadas . ' páginas siguiendo los enlaces del sitio',
            '',
            'Sin mapa del sitio, el análisis solo llega a lo que está enlazado. '
            . 'Crear un sitemap.xml haría que se cubriera entero, y de paso que Google lo encuentre todo.',
            'sin mapa del sitio')];
    }

    // =====================================================================
    //  Repetidos
    // =====================================================================

    private static function duplicados(array $paginas): array
    {
        $r = [];
        $n = count($paginas);

        // --- Títulos repetidos ---------------------------------------------
        $conTitulo = count(array_filter($paginas, static fn($p) => trim((string) $p['titulo']) !== ''));
        $dupT = self::repetidos(array_column($paginas, 'titulo'));
        $afectadas = array_sum($dupT);
        if ($conTitulo < 2) {
            // Sin al menos dos títulos que comparar no hay nada que decir. Dar
            // esto por bueno sería felicitar a un sitio por no repetir algo
            // que sencillamente no tiene.
            $r[] = self::h('titulos_repetidos', Chequeos::NA, 14,
                'No hay títulos suficientes para comparar', '', '', 'sin datos');
        } elseif ($dupT) {
            $r[] = self::h('titulos_repetidos', $afectadas > $n / 3 ? Chequeos::MAL : Chequeos::AVISO, 14,
                $afectadas . ' páginas comparten el título con otra',
                'Cuando varias páginas llevan el mismo título, Google no sabe cuál enseñar y acaba eligiendo mal, o no enseñando ninguna. Las páginas compiten entre ellas en vez de sumar, que es el error más caro y más común de un sitio hecho con plantilla.',
                'Dale a cada página un título propio que diga lo que esa página, y solo esa, ofrece.',
                count($dupT) . ' títulos repetidos en ' . $afectadas . ' páginas');
        } else {
            $r[] = self::h('titulos_repetidos', Chequeos::BIEN, 14,
                'Cada página tiene su propio título', '', '', 'sin repetidos');
        }

        // --- Descripciones repetidas -----------------------------------------
        $conDesc = count(array_filter($paginas, static fn($p) => trim((string) $p['descripcion']) !== ''));
        $dupD = self::repetidos(array_column($paginas, 'descripcion'));
        $afectadasD = array_sum($dupD);
        if ($conDesc < 2) {
            $r[] = self::h('desc_repetidas', Chequeos::NA, 8,
                'No hay descripciones suficientes para comparar', '', '', 'sin datos');
        } elseif ($dupD) {
            $r[] = self::h('desc_repetidas', $afectadasD > $n / 3 ? Chequeos::MAL : Chequeos::AVISO, 8,
                $afectadasD . ' páginas comparten la descripción con otra',
                'La descripción es el anuncio gratis que Google enseña bajo cada resultado. Repetida en media web, deja de describir nada y baja los clics de todas.',
                'Escribe una descripción distinta por página, de 70 a 160 caracteres.',
                count($dupD) . ' descripciones repetidas');
        } else {
            $r[] = self::h('desc_repetidas', Chequeos::BIEN, 8,
                'Cada página tiene su propia descripción', '', '', 'sin repetidos');
        }

        return $r;
    }

    // =====================================================================
    //  Contenido
    // =====================================================================

    private static function contenido(array $paginas): array
    {
        $r = [];
        $n = count($paginas);

        // --- Sin título o sin descripción --------------------------------------
        $sinT = count(array_filter($paginas, static fn($p) => trim((string) $p['titulo']) === ''));
        if ($sinT > 0) {
            $r[] = self::h('sin_titulo', Chequeos::MAL, 12,
                $sinT . ' de ' . $n . ' páginas no tienen título',
                'Sin título, Google escribe el que le parece a partir de la dirección o del menú. Es el renglón azul sobre el que la gente hace clic: dejarlo al azar es regalar visitas.',
                'Pon un título a cada página, de 30 a 60 caracteres.',
                $sinT . ' páginas');
        }

        $sinD = count(array_filter($paginas, static fn($p) => trim((string) $p['descripcion']) === ''));
        if ($sinD > 0) {
            $r[] = self::h('sin_descripcion', $sinD > $n / 2 ? Chequeos::MAL : Chequeos::AVISO, 8,
                $sinD . ' de ' . $n . ' páginas no tienen descripción',
                'Google rellena el hueco con un trozo cualquiera de la página, que suele ser el menú o el aviso de cookies. No invita a entrar a nadie.',
                'Escribe una descripción por página.',
                $sinD . ' páginas');
        }

        // --- Contenido pobre ----------------------------------------------------
        $pobres = array_values(array_filter($paginas, static fn($p) => (int) $p['palabras'] < self::MIN_PALABRAS));
        if ($pobres) {
            $prop = count($pobres) / $n;
            $r[] = self::h('contenido_pobre', $prop > 0.5 ? Chequeos::MAL : Chequeos::AVISO, 12,
                count($pobres) . ' de ' . $n . ' páginas tienen muy poco texto',
                'Google no puede posicionar lo que no puede leer. Una página con cuatro frases y muchas fotos no tiene por dónde entrar en ninguna búsqueda, y de paso arrastra hacia abajo la valoración del sitio entero.',
                'Sube esas páginas a 300 palabras útiles como mínimo: qué ofreces, para quién, dónde y por qué tú.',
                'menos de ' . self::MIN_PALABRAS . ' palabras');
        } else {
            $r[] = self::h('contenido_pobre', Chequeos::BIEN, 12,
                'Todas las páginas tienen contenido suficiente', '', '', 'todas por encima de ' . self::MIN_PALABRAS . ' palabras');
        }

        // --- Imágenes sin describir ----------------------------------------------
        $totalImg = array_sum(array_column($paginas, 'imgs'));
        $sinAlt   = array_sum(array_column($paginas, 'imgs_sin_alt'));
        if ($totalImg > 0) {
            $prop = $sinAlt / $totalImg;
            $estado = $sinAlt === 0 ? Chequeos::BIEN : ($prop <= 0.25 ? Chequeos::AVISO : Chequeos::MAL);
            $r[] = self::h('alt_sitio', $estado, 7,
                $sinAlt === 0
                    ? 'Todas las imágenes del sitio están descritas'
                    : $sinAlt . ' de ' . $totalImg . ' imágenes del sitio no están descritas',
                'El texto alternativo es lo que Google lee de una foto y lo que oye quien navega con lector de pantalla. Sin él, las imágenes no aportan nada al posicionamiento y el sitio queda fuera de la normativa de accesibilidad.',
                'Describe en pocas palabras lo que se ve en cada imagen.',
                $sinAlt . ' sin describir');
        }

        return $r;
    }

    // =====================================================================
    //  Estructura
    // =====================================================================

    private static function estructura(array $paginas): array
    {
        $r = [];
        $n = count($paginas);

        // --- Titular principal --------------------------------------------------
        $malH1 = count(array_filter($paginas, static fn($p) => (int) $p['h1'] !== 1));
        if ($malH1 > 0) {
            $r[] = self::h('h1_sitio', $malH1 > $n / 3 ? Chequeos::MAL : Chequeos::AVISO, 9,
                $malH1 . ' de ' . $n . ' páginas no tienen un titular principal claro',
                'El titular principal le dice a Google de qué va cada página. Si falta, o si hay varios compitiendo, Google tiene que adivinarlo, y adivina mal.',
                'Deja exactamente un titular principal por página, con el tema de esa página.',
                $malH1 . ' páginas');
        } else {
            $r[] = self::h('h1_sitio', Chequeos::BIEN, 9,
                'Cada página tiene un titular principal claro', '', '', 'todas correctas');
        }

        // --- Profundidad de clic -------------------------------------------------
        $hondas = array_values(array_filter(
            $paginas,
            static fn($p) => (int) $p['nivel'] > self::MAX_PROFUNDIDAD && (int) $p['nivel'] < 9
        ));
        if ($hondas) {
            $r[] = self::h('profundidad', Chequeos::AVISO, 7,
                count($hondas) . ' páginas están a más de ' . self::MAX_PROFUNDIDAD . ' clics de la portada',
                'Cuanto más hondo está algo, menos lo visita Google y menos fuerza le llega. Lo que importa tiene que estar a tres clics o menos de la entrada.',
                'Enlaza esas páginas desde el menú, desde la portada o desde otras páginas relacionadas.',
                count($hondas) . ' páginas hondas');
        }

        // --- Páginas sin salida ---------------------------------------------------
        $sinSalida = count(array_filter($paginas, static fn($p) => (int) $p['internos'] === 0));
        if ($sinSalida > 0) {
            $r[] = self::h('sin_enlaces', Chequeos::AVISO, 5,
                $sinSalida . ' páginas no enlazan a ninguna otra del sitio',
                'Son callejones sin salida: quien llega ahí no tiene a dónde seguir, y la fuerza que traía esa página se queda encerrada en vez de repartirse.',
                'Añade enlaces desde esas páginas hacia otras relacionadas.',
                $sinSalida . ' páginas');
        }

        // --- Dirección oficial declarada -------------------------------------------
        $sinCanonical = count(array_filter($paginas, static fn($p) => trim((string) $p['canonical']) === ''));
        if ($sinCanonical > 0) {
            $r[] = self::h('canonical_sitio', $sinCanonical > $n / 2 ? Chequeos::AVISO : Chequeos::BIEN, 6,
                $sinCanonical > $n / 2
                    ? $sinCanonical . ' de ' . $n . ' páginas no declaran su dirección oficial'
                    : 'Casi todas las páginas declaran su dirección oficial',
                'Sin esa indicación, la misma página con y sin "www", con y sin barra final o con parámetros de campaña cuenta como páginas distintas. El posicionamiento se reparte entre todas en vez de sumarse.',
                'Añade la etiqueta canonical a cada página, apuntando a su dirección definitiva.',
                $sinCanonical . ' sin declarar');
        }

        // --- Datos estructurados ----------------------------------------------------
        $conSchema = count(array_filter($paginas, static fn($p) => (int) $p['schema'] > 0));
        $r[] = self::h('schema_sitio', $conSchema >= $n / 2 ? Chequeos::BIEN : Chequeos::AVISO, 6,
            $conSchema . ' de ' . $n . ' páginas tienen datos estructurados',
            'Son la ficha oculta que le dice a Google y a las IA qué es esto. Es lo que hace que salgan las estrellas, los precios y los horarios directamente en los resultados.',
            'Añade la ficha que corresponda a cada tipo de página: LocalBusiness, Product, Article, FAQPage.',
            $conSchema . ' de ' . $n);

        return $r;
    }

    // =====================================================================
    //  Enlaces
    // =====================================================================

    private static function vinculos(array $d, array $paginas): array
    {
        $r = [];
        $v = $d['vinculos'] ?? null;
        if (!is_array($v) || (int) ($v['revisados'] ?? 0) === 0) { return $r; }

        $rotos = $v['rotos'] ?? [];
        $rotosInt = array_values(array_filter($rotos, static fn($x) => !empty($x['interno'])));
        $rotosExt = array_values(array_filter($rotos, static fn($x) => empty($x['interno'])));

        // --- Rotos hacia dentro -------------------------------------------------
        if ($rotosInt) {
            $r[] = self::h('rotos_internos', count($rotosInt) > 3 ? Chequeos::MAL : Chequeos::AVISO, 13,
                count($rotosInt) . ' ' . (count($rotosInt) === 1 ? 'enlace interno roto' : 'enlaces internos rotos'),
                'Un enlace roto dentro del sitio manda al visitante a una página de error y corta el paso a Google. Quien buscaba los precios o el contacto se encuentra un "no encontrado" y se va con la impresión de que el negocio está abandonado.',
                'Corrige o quita esos enlaces. Si la página se movió, pon una redirección desde la dirección vieja.',
                count($rotosInt) . ' de ' . $v['internos'] . ' internos');
        } else {
            $r[] = self::h('rotos_internos', Chequeos::BIEN, 13,
                'Todos los enlaces internos funcionan', '', '', $v['internos'] . ' revisados');
        }

        // --- Rotos hacia fuera ---------------------------------------------------
        if ($rotosExt) {
            $r[] = self::h('rotos_externos', count($rotosExt) > 5 ? Chequeos::AVISO : Chequeos::BIEN, 5,
                count($rotosExt) . ' ' . (count($rotosExt) === 1 ? 'enlace externo roto' : 'enlaces externos rotos'),
                'Enlazar a sitios que ya no existen da sensación de abandono, y Google lo lee como una señal de que la página no se mantiene.',
                'Actualiza o quita esos enlaces.',
                count($rotosExt) . ' de ' . $v['externos'] . ' externos');
        }

        // --- Redirecciones de más ---------------------------------------------------
        $red = $v['redirigidos'] ?? [];
        $redInt = array_values(array_filter($red, static fn($x) => !empty($x['interno'])));
        if ($redInt) {
            $r[] = self::h('redirecciones', count($redInt) > 5 ? Chequeos::AVISO : Chequeos::BIEN, 5,
                count($redInt) . ' enlaces internos pasan por una redirección',
                'Cada salto de más es tiempo que el visitante espera y fuerza que se pierde por el camino. Se arregla enlazando directo al destino.',
                'Cambia esos enlaces para que apunten ya a la dirección final.',
                count($redInt) . ' con salto');
        }

        // --- Todo revisado -------------------------------------------------------
        $r[] = self::h('cobertura_enlaces', Chequeos::BIEN, 1,
            'Se comprobaron ' . (int) $v['revisados'] . ' enlaces uno a uno',
            '', '', (int) $v['internos'] . ' internos · ' . (int) $v['externos'] . ' externos');

        return $r;
    }

    // =====================================================================
    //  Indexación
    // =====================================================================

    private static function indexacion(array $paginas): array
    {
        $r = [];
        $n = count($paginas);

        // --- Páginas que se esconden de Google ---------------------------------
        $noindex = array_values(array_filter($paginas, static fn($p) => !empty($p['noindex'])));
        if ($noindex) {
            $r[] = self::h('noindex_sitio', count($noindex) > $n / 3 ? Chequeos::MAL : Chequeos::AVISO, 12,
                count($noindex) . ' de ' . $n . ' páginas le piden a Google que no las incluya',
                'Esas páginas no existen en Google. A veces es a propósito (un aviso legal, un carrito), pero casi siempre es un descuido que quedó de cuando el sitio estaba en construcción, y puede llevar años ahí sin que nadie lo note.',
                'Quita la instrucción noindex de las páginas que sí quieres que se encuentren.',
                count($noindex) . ' páginas ocultas');
        } else {
            $r[] = self::h('noindex_sitio', Chequeos::BIEN, 12,
                'Ninguna página se esconde de Google', '', '', 'todas indexables');
        }

        // --- Huérfanas: en el mapa pero sin un solo enlace que lleve a ellas ------
        $huerfanas = array_values(array_filter($paginas, static fn($p) => !empty($p['huerfana'])));
        if ($huerfanas) {
            $r[] = self::h('huerfanas', count($huerfanas) > 5 ? Chequeos::MAL : Chequeos::AVISO, 7,
                count($huerfanas) . ' páginas están en el mapa pero no las enlaza nadie',
                'Google las conoce por el mapa del sitio, pero como no hay ningún enlace que lleve a ellas, las trata como de segunda: las visita poco y casi no les da fuerza. Para un visitante, sencillamente no existen.',
                'Enlázalas desde el menú, desde la portada o desde las páginas con las que tengan que ver.',
                count($huerfanas) . ' huérfanas');
        }

        return $r;
    }

    // =====================================================================
    //  El camino al 100 %
    // =====================================================================

    /**
     * Qué hay que cambiar y cuántos puntos devuelve cada cambio.
     *
     * Esto es lo que ninguna herramienta del mercado da masticado: no una lista
     * de problemas, sino el precio de cada uno en puntos de la nota. Y la
     * cuenta cuadra: la suma de todo lo pendiente es exactamente lo que falta
     * para llegar al 100.
     *
     * @param array $areas Áreas que entran en la cuenta (vacío = todas)
     * @return array<int,array{titulo:string,puntos:float,area:string,estado:string,arreglo:string,valor:string}>
     */
    public static function plan(array $hallazgos, array $areas = []): array
    {
        // 1) Cuánto pesa, en total, cada área que se pudo medir.
        $tope = [];
        foreach ($hallazgos as $h) {
            $a = (string) ($h['area'] ?? '');
            if ($a === '' || ($h['estado'] ?? '') === Chequeos::NA) { continue; }
            if ($areas && !in_array($a, $areas, true)) { continue; }
            $tope[$a] = ($tope[$a] ?? 0) + (int) ($h['peso'] ?? 0);
        }
        if (!$tope) { return []; }

        // 2) Lo que pesa cada área dentro de la nota global, contando solo las
        //    áreas medidas (igual que hace Informe::notas).
        $pesoTotal = 0;
        foreach (array_keys($tope) as $a) {
            $pesoTotal += Chequeos::AREAS[$a]['peso'] ?? 0;
        }
        if ($pesoTotal <= 0) { return []; }

        // 3) Y ahora, cada problema con su precio en puntos.
        $plan = [];
        foreach ($hallazgos as $h) {
            $estado = (string) ($h['estado'] ?? '');
            if (!in_array($estado, [Chequeos::MAL, Chequeos::AVISO], true)) { continue; }

            $a = (string) ($h['area'] ?? '');
            if (!isset($tope[$a]) || $tope[$a] <= 0) { continue; }

            // Un 'mal' devuelve su peso entero; un 'aviso', la mitad que le falta.
            $peso     = (int) ($h['peso'] ?? 0);
            $pendiente = $estado === Chequeos::MAL ? $peso : $peso / 2;

            $puntos = ($pendiente / $tope[$a]) * (Chequeos::AREAS[$a]['peso'] ?? 0) / $pesoTotal * 100;
            if ($puntos < 0.05) { continue; }

            $plan[] = [
                'clave'   => (string) ($h['clave'] ?? ''),
                'titulo'  => (string) ($h['titulo'] ?? ''),
                'area'    => $a,
                'estado'  => $estado,
                'arreglo' => (string) ($h['arreglo'] ?? ''),
                'valor'   => (string) ($h['valor'] ?? ''),
                'puntos'  => round($puntos, 1),
                'critico' => !empty($h['critico']),
            ];
        }

        // Lo que más puntos devuelve, primero.
        usort($plan, static function ($a, $b) {
            if (!empty($a['critico']) !== !empty($b['critico'])) { return !empty($a['critico']) ? -1 : 1; }
            return $b['puntos'] <=> $a['puntos'];
        });

        return $plan;
    }
}
