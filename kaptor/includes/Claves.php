<?php
/**
 * Kaptor - Palabras clave de un sitio.
 *
 * Responde dos preguntas distintas que la gente suele confundir:
 *
 *   1. ¿Qué palabras clave tiene CONFIGURADAS la página?
 *      Lo que el dueño escribió a mano: <meta name="keywords">, las etiquetas
 *      de artículo y el campo "keywords" de los datos estructurados.
 *
 *   2. ¿Para qué palabras se está posicionando DE VERDAD?
 *      Eso no lo declara nadie: sale del propio texto. Se cuenta cada término
 *      y cada frase de dos y tres palabras, pero no todas valen lo mismo: lo
 *      que está en el <title> pesa ocho veces más que lo que está en un
 *      párrafo, porque para Google tampoco vale lo mismo.
 *
 * La diferencia entre las dos listas es justo el argumento de venta: "usted
 * declara 'colegio bilingüe' y su web habla de 'inscripciones 2026'".
 *
 * No hace falta ninguna API: todo sale del HTML que ya se descargó.
 */
declare(strict_types=1);

final class Claves
{
    /** Términos que se guardan por página. */
    public const MAX_POR_PAGINA = 14;

    /** Términos que se muestran del sitio entero. */
    public const MAX_SITIO = 25;

    /** Longitud mínima de una palabra para contar. */
    private const MIN_LETRAS = 3;

    /** Peso de cada zona de la página. */
    private const PESOS = [
        'titulo'      => 8,
        'h1'          => 6,
        'h2'          => 4,
        'descripcion' => 3,
        'alt'         => 2,
        'enlace'      => 2,
        'texto'       => 1,
    ];

    /**
     * Las únicas palabras de relleno que pueden ir DENTRO de una frase.
     *
     * "material de construcción" es un tema; "manual y eléctrica" son dos
     * cosas que el texto puso seguidas. La diferencia es la preposición: une,
     * mientras que la conjunción separa.
     */
    private const PUENTES = [
        'de', 'del', 'la', 'el', 'las', 'los', 'en', 'al', 'a', 'para', 'por', 'con',
    ];

    /**
     * Palabras vacías: no son un tema, son pegamento.
     *
     * Van sin tilde porque la comparación se hace sobre el texto normalizado.
     */
    private const VACIAS = [
        // castellano
        'para','por','con','sin','sobre','entre','desde','hasta','hacia','segun',
        'los','las','del','una','uno','unos','unas','este','esta','estos','estas',
        'ese','esa','esos','esas','aquel','aquella','que','quien','cual','cuando',
        'donde','como','porque','pero','sino','aunque','mas','muy','tan','tanto',
        'todo','toda','todos','todas','otro','otra','otros','otras','mismo','misma',
        'cada','algun','alguna','algunos','algunas','ningun','ninguna','nada','algo',
        'ser','soy','eres','son','somos','era','eran','fue','fueron','sera','seran',
        'estar','esta','estan','estamos','estaba','estuvo','haber','hay','habia',
        'tiene','tienen','tenemos','tener','hace','hacen','hacer','puede','pueden',
        'poder','debe','deben','ver','vea','vez','veces','sus','nos','les','lo',
        'le','se','su','al','el','la','de','en','un','y','o','a','no','si','ya',
        'me','mi','tu','te','nuestro','nuestra','nuestros','nuestras','usted',
        'ustedes','ellos','ellas','yo','él','ella','aqui','alli','ahi','ahora',
        'antes','despues','siempre','nunca','tambien','solo','solamente','bien',
        'mejor','mayor','menor','gran','grande','nuevo','nueva','primer','primera',
        'ademas','entonces','asi','cualquier','cualquiera','mientras','durante',
        // web
        'inicio','home','menu','pagina','paginas','sitio','web','www','http','https',
        'clic','click','aqui','leer','mas','ver','enlace','enlaces','contacto',
        'siguiente','anterior','buscar','busqueda','cookies','cookie','politica',
        'aviso','legal','privacidad','terminos','condiciones','copyright','todos',
        'derechos','reservados','compartir','facebook','twitter','instagram',
        'whatsapp','youtube','tiktok','linkedin','correo','email','telefono','tel',
        'skip','content','toggle','navigation','search','close','open','loading',
        // inglés de plantilla
        'the','and','for','you','your','with','from','this','that','are','was',
        'our','all','not','can','has','have','will','more','about','page','site',
        'read','click','here','menu','post','posts','comment','comments','reply',
    ];

    // =====================================================================
    //  1. Lo que la página declara
    // =====================================================================

    /**
     * Palabras clave escritas a mano en el código.
     *
     * Devuelve también cuáles de ellas aparecen de verdad en el texto de la
     * página: declarar «robótica» y no nombrarla nunca no posiciona nada, y
     * ese desajuste es lo que hay que poder enseñar.
     *
     * @return array{lista:string[],origen:string[],usadas:string[]}
     */
    public static function declaradas(Pagina $p): array
    {
        // Cada palabra se anota CON la etiqueta de la que salió. Decir solo
        // «este sitio declara estas palabras» no basta: para poder enseñarlo
        // sin que nadie tenga que fiarse, hay que poder señalar la etiqueta
        // exacta de donde se leyó cada una.
        $crudas = [];   // [texto, origen]

        $meta = $p->meta('keywords');
        if (trim($meta) !== '') {
            foreach (self::trocear($meta) as $k) { $crudas[] = [$k, 'meta keywords']; }
        }

        $noticias = $p->meta('news_keywords');
        if (trim($noticias) !== '') {
            foreach (self::trocear($noticias) as $k) { $crudas[] = [$k, 'news_keywords']; }
        }

        // Open Graph de artículo: cada etiqueta va en su propia <meta>, así que
        // el método meta() solo devolvería la primera. Se leen del HTML.
        if (preg_match_all(
            '~<meta[^>]+property\s*=\s*["\']article:tag["\'][^>]*content\s*=\s*["\']([^"\']+)~i',
            $p->html(), $m
        )) {
            foreach (self::trocear(implode(',', $m[1])) as $k) { $crudas[] = [$k, 'article:tag']; }
        }

        // Las etiquetas de WordPress, que es donde el 90 % de los sitios de
        // verdad tienen puestas sus palabras clave. Salen como enlaces con
        // rel="tag", y son tan «configuradas» como la etiqueta keywords: las
        // escribió una persona a mano.
        if (preg_match_all(
            '~<a[^>]+rel\s*=\s*["\'][^"\']*\btag\b[^"\']*["\'][^>]*>(.*?)</a>~is',
            $p->html(), $mt
        )) {
            foreach ($mt[1] as $txt) {
                $txt = trim(html_entity_decode(strip_tags($txt), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($txt !== '' && mb_strlen($txt, 'UTF-8') <= 60) {
                    $crudas[] = [$txt, 'etiquetas del gestor'];
                }
            }
        }

        foreach ($p->jsonLd() as $bloque) {
            foreach (self::clavesDeJson($bloque) as $k) { $crudas[] = [$k, 'datos estructurados']; }
        }

        // Sin repetir, respetando cómo las escribió el dueño la primera vez.
        // Una misma palabra puede venir de dos sitios: se guardan los dos.
        $limpia  = [];
        $fuentes = [];
        $origen  = [];
        $indice  = [];

        foreach ($crudas as [$texto, $de]) {
            $clave = self::normalizar($texto);
            if ($clave === '') { continue; }

            if (!isset($indice[$clave])) {
                if (count($limpia) >= 40) { continue; }
                $indice[$clave] = $texto;
                $limpia[] = $texto;
                $fuentes[$texto] = [];
            }
            $cual = $indice[$clave];
            if (!in_array($de, $fuentes[$cual], true)) { $fuentes[$cual][] = $de; }
            if (!in_array($de, $origen, true)) { $origen[] = $de; }
        }

        // ¿Cuáles de esas palabras aparecen en la página? Se mira sobre el
        // texto normalizado para que «Robótica» y «robotica» cuenten igual.
        $enc = $p->encabezados();
        $cuerpo = ' ' . self::normalizar(
            $p->titulo() . ' ' . implode(' ', $enc[1] ?? []) . ' ' . implode(' ', $enc[2] ?? [])
            . ' ' . $p->meta('description') . ' ' . mb_substr($p->texto(), 0, 40000)
        ) . ' ';

        $usadas = [];
        foreach ($limpia as $k) {
            $n = self::normalizar($k);
            if ($n !== '' && str_contains($cuerpo, ' ' . $n . ' ')) { $usadas[] = $k; }
        }

        return ['lista' => $limpia, 'origen' => $origen, 'usadas' => $usadas, 'fuentes' => $fuentes];
    }

    /** Separa "uno, dos | tres" en sus términos. */
    private static function trocear(string $crudo): array
    {
        $partes = preg_split('~[,;|]+~u', $crudo) ?: [];
        $salida = [];
        foreach ($partes as $parte) {
            $parte = trim(preg_replace('~\s+~u', ' ', $parte) ?? '');
            // Una "keyword" de 80 caracteres es una frase suelta, no un término.
            if ($parte === '' || mb_strlen($parte, 'UTF-8') > 60) { continue; }
            $salida[] = mb_substr($parte, 0, 60);
        }
        return $salida;
    }

    /** Busca "keywords" en cualquier nivel de un bloque JSON-LD. */
    private static function clavesDeJson(array $bloque, int $hondo = 0): array
    {
        if ($hondo > 6) { return []; }
        $salida = [];
        foreach ($bloque as $clave => $valor) {
            if (is_string($clave) && strtolower($clave) === 'keywords') {
                if (is_string($valor)) {
                    $salida = array_merge($salida, self::trocear($valor));
                } elseif (is_array($valor)) {
                    foreach ($valor as $v) {
                        if (is_string($v)) { $salida = array_merge($salida, self::trocear($v)); }
                    }
                }
                continue;
            }
            if (is_array($valor)) {
                $salida = array_merge($salida, self::clavesDeJson($valor, $hondo + 1));
            }
        }
        return $salida;
    }

    // =====================================================================
    //  2. Para lo que la página habla de verdad
    // =====================================================================

    /**
     * Términos y frases con más peso en una página.
     *
     * @return array<int,array{t:string,p:int,n:int}> t=término, p=peso, n=veces
     */
    public static function delTexto(Pagina $p): array
    {
        $enc = $p->encabezados();

        $zonas = [
            'titulo'      => [$p->titulo()],
            'h1'          => $enc[1] ?? [],
            'h2'          => $enc[2] ?? [],
            'descripcion' => [$p->meta('description')],
            'alt'         => array_column($p->imagenes(), 'alt'),
            'enlace'      => array_column(
                array_filter($p->enlaces(), static fn($e) => !empty($e['interno'])),
                'texto'
            ),
            // El cuerpo se recorta: con 40.000 caracteres ya está decidido el
            // tema, y así una página enorme no dispara el tiempo de análisis.
            'texto'       => [mb_substr($p->texto(), 0, 40000)],
        ];

        $peso  = [];
        $veces = [];
        $forma = [];

        foreach ($zonas as $zona => $textos) {
            $w = self::PESOS[$zona];
            foreach ($textos as $texto) {
                if (!is_string($texto) || trim($texto) === '') { continue; }
                foreach (self::frases($texto) as $clave => $datos) {
                    $peso[$clave]  = ($peso[$clave] ?? 0) + $datos['n'] * $w;
                    $veces[$clave] = ($veces[$clave] ?? 0) + $datos['n'];
                    $forma[$clave] ??= $datos['t'];
                }
            }
        }

        if (!$peso) { return []; }

        $orden = self::ordenar($peso);
        $salida = [];
        foreach ($orden as $clave) {
            // Un término que aparece una sola vez en todo el cuerpo es ruido,
            // salvo que venga del título o de un encabezado (peso alto).
            if ($veces[$clave] < 2 && $peso[$clave] < self::PESOS['h2']) { continue; }
            // Y si ya está dentro de una frase mejor colocada, sobra: sin esto
            // la lista sale con «colegio», «colegio bilingüe» y «bilingüe» como
            // si fueran tres temas distintos.
            if (self::absorbida($clave, $veces, $salida)) { continue; }

            $salida[] = ['t' => $forma[$clave], 'p' => (int) $peso[$clave], 'n' => (int) $veces[$clave]];
            if (count($salida) >= self::MAX_POR_PAGINA) { break; }
        }
        return $salida;
    }

    /**
     * Claves ordenadas por peso y, a igualdad, la frase más larga primero.
     *
     * Importa para el recorte: si «colegio» va antes que «colegio bilingüe»,
     * se queda la palabra suelta y se tira la frase, que es lo contrario de
     * lo que interesa.
     *
     * @param array<string,int|float> $peso
     * @return string[]
     */
    private static function ordenar(array $peso): array
    {
        $claves = array_keys($peso);
        usort($claves, static function ($a, $b) use ($peso) {
            if ($peso[$a] !== $peso[$b]) { return $peso[$b] <=> $peso[$a]; }
            return substr_count($b, ' ') <=> substr_count($a, ' ');
        });
        return $claves;
    }

    /**
     * ¿Este término ya está contenido en una frase que va por delante?
     *
     * Solo se descarta si además no aparece mucho por su cuenta: «bilingüe»
     * diez veces y «colegio bilingüe» seis significa que la palabra suelta
     * también es un tema, y se conserva.
     *
     * @param array<string,int> $veces
     * @param array<int,array{t:string,p:int,n:int}> $yaPuestas
     */
    private static function absorbida(string $clave, array $veces, array $yaPuestas): bool
    {
        $n = (int) ($veces[$clave] ?? 0);
        foreach ($yaPuestas as $puesta) {
            $otra = self::normalizar($puesta['t']);
            if ($otra === $clave || substr_count($otra, ' ') <= substr_count($clave, ' ')) { continue; }
            if (!str_contains(' ' . $otra . ' ', ' ' . $clave . ' ')) { continue; }
            if ($n <= (int) round($puesta['n'] * 1.2)) { return true; }
        }
        return false;
    }

    /**
     * Palabras y frases de 2 y 3 palabras de un texto.
     *
     * @return array<string,array{t:string,n:int}>
     */
    private static function frases(string $texto): array
    {
        $tokens = self::tokenizar($texto);
        if (!$tokens) { return []; }

        $cuenta = [];
        $total  = count($tokens);

        for ($i = 0; $i < $total; $i++) {
            for ($n = 1; $n <= 3; $n++) {
                if ($i + $n > $total) { break; }
                $trozo = array_slice($tokens, $i, $n);

                // Una frase no empieza en palabra vacía ni es una palabra vacía
                // suelta. Sí puede terminar en número, porque "zona 12" y
                // "inscripciones 2026" son justo lo que la gente busca, y sí
                // puede llevar un "de" o un "y" en medio: sin eso, "material de
                // construcción" se pierde y quedan "material" y "construcción"
                // como si fueran dos temas distintos.
                if (self::esVacia($trozo[0]['k'])) { continue; }
                if (!self::valeDeFinal($trozo[$n - 1]['k'])) { continue; }
                // Si el texto tenía un punto o una coma en medio, no es frase;
                // y en el hueco central solo caben preposiciones.
                if ($n > 1) {
                    $corta = false;
                    for ($j = 1; $j < $n; $j++) {
                        if ($trozo[$j]['corte']) { $corta = true; break; }
                        if ($j < $n - 1
                            && self::esVacia($trozo[$j]['k'])
                            && !in_array($trozo[$j]['k'], self::PUENTES, true)) {
                            $corta = true; break;
                        }
                    }
                    if ($corta) { continue; }
                }

                $clave = implode(' ', array_column($trozo, 'k'));
                $forma = implode(' ', array_column($trozo, 't'));
                if (!isset($cuenta[$clave])) { $cuenta[$clave] = ['t' => $forma, 'n' => 0]; }
                $cuenta[$clave]['n']++;
            }
        }
        return $cuenta;
    }

    /**
     * Parte un texto en palabras, marcando dónde hay puntuación.
     *
     * @return array<int,array{t:string,k:string,corte:bool}>
     */
    private static function tokenizar(string $texto): array
    {
        $texto = preg_replace('~\s+~u', ' ', $texto) ?? $texto;
        if (!preg_match_all('~[\p{L}\p{N}][\p{L}\p{N}\'’-]*|[.,;:!?¡¿()\[\]«»"/|–—]~u', $texto, $m)) {
            return [];
        }

        $salida = [];
        $corte  = false;
        foreach ($m[0] as $pieza) {
            if (!preg_match('~^[\p{L}\p{N}]~u', $pieza)) { $corte = true; continue; }

            $mostrar = mb_strtolower($pieza, 'UTF-8');
            $clave   = self::normalizar($mostrar);

            if ($clave === '') { $corte = true; continue; }

            $salida[] = ['t' => $mostrar, 'k' => $clave, 'corte' => $corte];
            $corte = false;
        }
        return $salida;
    }

    /** Sin tildes, sin mayúsculas, sin nada raro: para comparar. */
    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c', '’' => '', '\'' => '',
        ]);
        return (string) preg_replace('~[^a-z0-9 -]~', '', $texto);
    }

    /** ¿Es una palabra de relleno, o demasiado corta para ser un tema? */
    private static function esVacia(string $clave): bool
    {
        return mb_strlen($clave, 'UTF-8') < self::MIN_LETRAS
            || is_numeric($clave)
            || in_array($clave, self::VACIAS, true);
    }

    /** ¿Puede una frase terminar aquí? Los números sí; el relleno no. */
    private static function valeDeFinal(string $clave): bool
    {
        if (is_numeric($clave)) { return mb_strlen($clave, 'UTF-8') >= 2; }
        return !self::esVacia($clave);
    }

    // =====================================================================
    //  3. El sitio entero
    // =====================================================================

    /**
     * Junta las palabras clave de todas las páginas rastreadas.
     *
     * @param array<int,array> $paginas fichas con 'claves' y 'claves_meta'
     * @return array{
     *   reales:array<int,array{t:string,p:int,n:int,paginas:int}>,
     *   declaradas:array<int,array{t:string,paginas:int}>,
     *   origen:string[],
     *   con_meta:int,
     *   analizadas:int,
     *   sin_usar:string[],
     *   sin_declarar:string[]
     * }
     */
    public static function delSitio(array $paginas): array
    {
        $peso = []; $veces = []; $enPag = []; $forma = [];
        $decl = []; $declPag = []; $origen = []; $conMeta = 0;
        $usadas = []; $analizadas = 0;

        foreach ($paginas as $pag) {
            if (!empty($pag['error'])) { continue; }
            $analizadas++;

            foreach ($pag['claves'] ?? [] as $c) {
                $t = (string) ($c['t'] ?? '');
                if ($t === '') { continue; }
                $k = self::normalizar($t);
                if ($k === '') { continue; }
                $peso[$k]  = ($peso[$k] ?? 0) + (int) ($c['p'] ?? 0);
                $veces[$k] = ($veces[$k] ?? 0) + (int) ($c['n'] ?? 0);
                $enPag[$k] = ($enPag[$k] ?? 0) + 1;
                $forma[$k] ??= $t;
            }

            $meta = $pag['claves_meta'] ?? [];
            $lista = is_array($meta['lista'] ?? null) ? $meta['lista'] : [];
            if ($lista) { $conMeta++; }
            foreach ($lista as $t) {
                $k = self::normalizar((string) $t);
                if ($k === '') { continue; }
                $decl[$k] ??= (string) $t;
                $declPag[$k] = ($declPag[$k] ?? 0) + 1;
            }
            foreach ($meta['usadas'] ?? [] as $t) {
                $k = self::normalizar((string) $t);
                if ($k !== '') { $usadas[$k] = true; }
            }
            foreach ($meta['origen'] ?? [] as $o) {
                if (!in_array($o, $origen, true)) { $origen[] = (string) $o; }
            }
        }

        $reales = [];
        foreach (self::ordenar($peso) as $k) {
            $n    = (int) ($veces[$k] ?? 0);
            $enN  = (int) ($enPag[$k] ?? 0);
            if ($n < 2) { continue; }
            // Lo que sale en media web pero una sola vez en cada página es el
            // menú o el pie, no un tema: "servicios", "contacto", "blog".
            if ($enN >= 3 && $n <= $enN) { continue; }
            if (self::absorbida($k, $veces, $reales)) { continue; }
            $reales[] = ['t' => $forma[$k], 'p' => (int) $peso[$k], 'n' => $n, 'paginas' => $enN];
            if (count($reales) >= self::MAX_SITIO) { break; }
        }

        arsort($declPag);
        $declaradas = [];
        foreach ($declPag as $k => $n) {
            $declaradas[] = ['t' => $decl[$k], 'paginas' => (int) $n];
        }

        // Cruce: lo declarado que el texto no menciona en ninguna página, y lo
        // que el texto grita sin que nadie lo haya declarado.
        $sinUsar = [];
        foreach ($decl as $k => $t) {
            if (!isset($usadas[$k])) { $sinUsar[] = $t; }
            if (count($sinUsar) >= 12) { break; }
        }

        // Solo se señalan los temas de verdad dominantes: con un umbral bajo,
        // la lista se llena de palabras sueltas del pie de página.
        $sinDeclarar = [];
        if ($decl && $reales) {
            $corte = (int) round($reales[0]['p'] * 0.4);
            foreach (array_slice($reales, 0, 6) as $r) {
                if ($r['p'] < $corte) { break; }
                if (!self::apareceEn(self::normalizar($r['t']), $decl)) { $sinDeclarar[] = $r['t']; }
                if (count($sinDeclarar) >= 5) { break; }
            }
        }

        return [
            'reales'       => $reales,
            'declaradas'   => array_slice($declaradas, 0, 40),
            'origen'       => $origen,
            'con_meta'     => $conMeta,
            'analizadas'   => $analizadas,
            'sin_usar'     => $sinUsar,
            'sin_declarar' => $sinDeclarar,
        ];
    }

    /**
     * ¿Está este término en el conjunto, aunque sea dentro de otro?
     *
     * "colegio" cuenta como presente si el sitio habla de "colegio bilingüe":
     * marcarlo como ausente sería mentir.
     */
    private static function apareceEn(string $clave, array $conjunto): bool
    {
        if ($clave === '') { return false; }
        if (isset($conjunto[$clave])) { return true; }
        foreach (array_keys($conjunto) as $otra) {
            $otra = (string) $otra;
            if (str_contains(' ' . $otra . ' ', ' ' . $clave . ' ')
             || str_contains(' ' . $clave . ' ', ' ' . $otra . ' ')) {
                return true;
            }
        }
        return false;
    }
}
