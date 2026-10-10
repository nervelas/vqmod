<?php
declare(strict_types=1);

namespace S5\Services;

/**
 * Validación de la salida de la IA.
 *
 * - `validar()`: textos de la web (contrato §6). Ignora claves desconocidas,
 *   sanea con TextClean, recorta a los máximos, exige tantos `servicios` como el
 *   brief y rellena faltantes con BaseTexts. Reemplaza por texto base los
 *   campos que parezcan inventar datos (cifras, URLs, correos, "X años de
 *   experiencia", certificaciones...) o que obedezcan instrucciones ajenas.
 * - `validarExtraccion()`: JSON de extracción de presentaciones (contrato §5.2).
 */
class TextSchema
{
    /** Resumen de la última validación (para registro/pruebas). */
    public static array $ultimo = ['reconocidos' => 0, 'reemplazados' => []];

    /**
     * Claves de iconos permitidas (CONTRATO-LUXE §1; el tema las implementa como SVG).
     * Única definición para validar `icono` en textos, servicios y catálogo.
     */
    public const ICONOS = [
        'star', 'shield', 'check', 'heart', 'clock', 'phone', 'mail', 'pin', 'calendar', 'users', 'user', 'handshake',
        'award', 'crown', 'gem', 'sparkles', 'lightbulb', 'target', 'rocket', 'chat', 'globe', 'link', 'lock', 'key',
        'home', 'building', 'briefcase', 'document', 'pen', 'book', 'graduation', 'chart', 'trend', 'coins', 'wallet', 'card',
        'receipt', 'calculator', 'percent', 'tag', 'gift', 'bag', 'cart', 'truck', 'box', 'plane', 'ship', 'route',
        'map', 'compass', 'camera', 'image', 'video', 'music', 'play', 'headset', 'wrench', 'gear', 'hammer', 'bolt',
        'car', 'tools', 'oil', 'tire', 'battery', 'shirt', 'scissors', 'ruler', 'hanger', 'utensils', 'coffee', 'wine',
        'cake', 'chef', 'leaf', 'flame', 'droplet', 'sun', 'moon', 'stethoscope', 'pulse', 'tooth', 'pill', 'syringe',
        'microscope', 'eye', 'brain', 'bone', 'baby', 'paw', 'scales', 'gavel', 'columns', 'contract', 'stamp', 'fingerprint',
        'container', 'warehouse', 'barcode', 'flag', 'medal', 'diamond', 'thumbs', 'trophy', 'percent-badge', 'support', 'wifi', 'cloud',
        'code', 'printer', 'facebook', 'instagram', 'tiktok', 'youtube', 'x', 'linkedin', 'whatsapp', 'telegram',
    ];

    /** Iconos típicos por rubro (CONTRATO-LUXE §1), los primeros son los más frecuentes. */
    public const ICONOS_RUBRO = [
        'abogado' => ['scales', 'gavel', 'columns', 'contract', 'shield', 'handshake', 'book'],
        'clinica' => ['stethoscope', 'pulse', 'tooth', 'pill', 'heart', 'microscope', 'calendar'],
        'taller' => ['wrench', 'gear', 'car', 'oil', 'tire', 'battery', 'bolt'],
        'ropa' => ['shirt', 'scissors', 'bag', 'ruler', 'gem', 'tag', 'sparkles'],
        'restaurante' => ['utensils', 'wine', 'cake', 'coffee', 'flame', 'leaf', 'chef'],
        'transporte' => ['truck', 'route', 'box', 'compass', 'clock', 'shield', 'plane'],
        'contabilidad' => ['calculator', 'chart', 'coins', 'receipt', 'percent', 'document', 'wallet'],
        'importaciones' => ['globe', 'ship', 'container', 'box', 'plane', 'barcode', 'warehouse'],
        'otro' => ['star', 'sparkles', 'award', 'briefcase', 'check', 'handshake', 'gem'],
    ];

    /** Cantidades de los bloques de lista (CONTRATO-LUXE §2). */
    public const VALORES_MIN = 4;
    public const VALORES_MAX = 6;
    public const PROCESO_MIN = 3;
    public const PROCESO_MAX = 4;
    public const FAQ_MIN = 4;
    public const FAQ_MAX = 6;

    private const REDES = [
        'facebook' => ['facebook.com', 'fb.com', 'fb.me'],
        'instagram' => ['instagram.com'],
        'tiktok' => ['tiktok.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'x' => ['x.com', 'twitter.com'],
        'linkedin' => ['linkedin.com'],
    ];

    /** Frases que delatan afirmaciones que el cliente no dio. */
    private const PATRON_INVENTOS = '/\b(?:d[eé]cadas?|decades?|a[ñn]os\s+de\s+(?:experiencia|trayectoria|historia)|years\s+of\s+(?:experience|history)|premiad[oa]s?|galardonad[oa]s?|award[- ]winning|awards?|certificad[oa]s?|certificaci[oó]n(?:es)?|certified|certifications?|ISO\s*\d+|n[uú]mero\s+(?:uno|1)|number\s+(?:one|1)|l[ií]deres?\s+(?:en|del|de)|(?:market\s+)?leaders?\s+in|testimonios?|testimonials?|garant[ií]a\s+total|fundad[oa]\s+en|founded\s+in|desde\s+(?:el\s+)?(?:19|20)\d\d|since\s+(?:19|20)\d\d|miles\s+de\s+(?:clientes|pacientes|casos)|thousands\s+of\s+(?:clients|customers|patients))\b/iu';

    /** Instrucciones dirigidas a la IA que no deben pasar a la web. */
    private const PATRON_INSTRUCCION = '/(?:ignor[ae]\w*\s+(?:\w+\s+){0,4}(?:instrucci|indicaci|reglas|prompt|anterior)|ignore\s+(?:\w+\s+){0,4}(?:instructions|prompt|rules|above)|olvida\s+(?:\w+\s+){0,4}(?:instrucci|anterior)|forget\s+(?:\w+\s+){0,4}instructions|disregard\s|system\s+prompt|prompt\s+del\s+sistema|responde\s+(?:solo|[uú]nicamente)|devuelve\s+(?:solo|[uú]nicamente|el\s+siguiente)|you\s+are\s+now|ahora\s+eres|nuevas\s+instrucciones|new\s+instructions)/iu';

    // ------------------------------------------------------------------
    // Utilidades JSON
    // ------------------------------------------------------------------

    /**
     * Extrae un objeto JSON de una respuesta de modelo, aunque venga con
     * ```json ... ``` o con texto antes/después.
     */
    public static function extraerJson(string $raw): ?array
    {
        $raw = trim(TextClean::utf8($raw));
        if ($raw === '') {
            return null;
        }
        $d = json_decode($raw, true);
        if (is_array($d)) {
            return $d;
        }
        if (preg_match('/```(?:json|JSON)?\s*(.*?)```/s', $raw, $m)) {
            $d = json_decode(trim($m[1]), true);
            if (is_array($d)) {
                return $d;
            }
        }
        $ini = strpos($raw, '{');
        while ($ini !== false) {
            $fin = self::cierreLlave($raw, $ini);
            if ($fin !== null) {
                $d = json_decode(substr($raw, $ini, $fin - $ini + 1), true);
                if (is_array($d)) {
                    return $d;
                }
            }
            $ini = strpos($raw, '{', $ini + 1);
            if ($ini !== false && $ini > 200000) {
                break;
            }
        }
        return null;
    }

    private static function cierreLlave(string $s, int $ini): ?int
    {
        $n = strlen($s);
        $prof = 0;
        $enStr = false;
        $esc = false;
        for ($i = $ini; $i < $n; $i++) {
            $c = $s[$i];
            if ($enStr) {
                if ($esc) {
                    $esc = false;
                } elseif ($c === '\\') {
                    $esc = true;
                } elseif ($c === '"') {
                    $enStr = false;
                }
                continue;
            }
            if ($c === '"') {
                $enStr = true;
            } elseif ($c === '{') {
                $prof++;
            } elseif ($c === '}') {
                $prof--;
                if ($prof === 0) {
                    return $i;
                }
            }
        }
        return null;
    }

    /** Cuántas claves de texto reconocidas y no vacías trae el JSON. */
    public static function reconocidos(array $json): int
    {
        $n = 0;
        foreach (BaseTexts::LIMITES as $k => $_) {
            if (isset($json[$k]) && is_string($json[$k]) && trim($json[$k]) !== '') {
                $n++;
            }
        }
        return $n;
    }

    // ------------------------------------------------------------------
    // Textos de la web (§6)
    // ------------------------------------------------------------------

    /**
     * @param array $json  JSON devuelto por la IA (ya decodificado).
     * @param array $brief Brief del cliente (contrato §4).
     * @return array<string,mixed> textos válidos (esquema §6), siempre completos.
     */
    public static function validar(array $json, array $brief): array
    {
        $base = BaseTexts::textosCompletos($brief);
        $corpus = self::corpus($brief);
        $nums = self::numeros($corpus);
        $rep = [];
        $out = [];
        foreach (BaseTexts::LIMITES as $k => $max) {
            if (!array_key_exists($k, $base)) {
                continue; // tienda_* solo con plan tienda
            }
            $v = $json[$k] ?? null;
            $t = (is_string($v) || is_int($v) || is_float($v)) ? TextClean::limpiar((string)$v, $max, $k === 'nosotros_texto') : '';
            if ($t === '' || !self::textoSeguro($t, $corpus, $nums)) {
                $t = (string)$base[$k];
                $rep[] = $k;
            }
            $out[$k] = $t;
        }
        $baseS = $base['servicios'];
        $js = (isset($json['servicios']) && is_array($json['servicios'])) ? array_values($json['servicios']) : [];
        $srv = [];
        foreach ($baseS as $i => $bs) {
            $it = (isset($js[$i]) && is_array($js[$i])) ? $js[$i] : [];
            $r = TextClean::limpiar($it['resumen'] ?? '', BaseTexts::LIM_RESUMEN);
            $d = TextClean::limpiar($it['descripcion'] ?? '', BaseTexts::LIM_DESCRIPCION, true);
            if ($r === '' || !self::textoSeguro($r, $corpus, $nums)) {
                $r = $bs['resumen'];
                $rep[] = "servicios.$i.resumen";
            }
            if ($d === '' || !self::textoSeguro($d, $corpus, $nums)) {
                $d = $bs['descripcion'];
                $rep[] = "servicios.$i.descripcion";
            }
            $ic = self::icono($it['icono'] ?? '');
            if ($ic === '') {
                $ic = (string)($bs['icono'] ?? 'star');
            }
            $srv[] = ['resumen' => $r, 'descripcion' => $d, 'icono' => $ic];
        }
        $out['servicios'] = $srv;
        self::validarLuxe($json, $base, $corpus, $nums, $out, $rep);
        self::$ultimo = ['reconocidos' => self::reconocidos($json), 'reemplazados' => $rep];
        return $out;
    }

    // ------------------------------------------------------------------
    // Bloques ampliados (CONTRATO-LUXE §2)
    // ------------------------------------------------------------------

    /** Devuelve la clave de icono si pertenece al set cerrado (§1); si no, "". */
    public static function icono($v): string
    {
        if (!is_string($v)) {
            return '';
        }
        $k = strtolower(trim($v));
        return ($k !== '' && in_array($k, self::ICONOS, true)) ? $k : '';
    }

    /**
     * Completa/valida SOLO los bloques ampliados de unos textos ya existentes
     * (p. ej. textos guardados antes de LUXE o producidos por BaseTexts::textos).
     * Cualquier bloque ausente o corrupto se rellena con textos base. No toca
     * `servicios` ni las claves del contrato §6.
     *
     * @return array<string,mixed> $texts con los bloques ampliados garantizados
     */
    public static function completarLuxe(array $texts, array $brief): array
    {
        $base = BaseTexts::textosCompletos($brief);
        $corpus = self::corpus($brief);
        $nums = self::numeros($corpus);
        $rep = [];
        $out = $texts;
        self::validarLuxe($texts, $base, $corpus, $nums, $out, $rep);
        return $out;
    }

    /**
     * @param array $json   entrada (IA o textos guardados)
     * @param array $base   BaseTexts::textosCompletos()
     * @param array $out    se le añaden los bloques ampliados
     * @param array $rep    claves reemplazadas por texto base
     */
    private static function validarLuxe(array $json, array $base, string $corpus, array $nums, array &$out, array &$rep): void
    {
        foreach (BaseTexts::LIMITES_LUXE as $k => $max) {
            $v = $json[$k] ?? null;
            $t = (is_string($v) || is_int($v) || is_float($v)) ? TextClean::limpiar((string)$v, $max) : '';
            if ($t === '' || !self::textoSeguro($t, $corpus, $nums)) {
                $t = (string)$base[$k];
                $rep[] = $k;
            }
            $out[$k] = $t;
        }
        // listas
        $out['valores'] = self::lista3($json['valores'] ?? null, $base['valores'], BaseTexts::LIM_VALOR_TITULO, BaseTexts::LIM_VALOR_TEXTO, true, self::VALORES_MIN, self::VALORES_MAX, $corpus, $nums, 'valores', $rep);
        $out['proceso'] = self::lista3($json['proceso'] ?? null, $base['proceso'], BaseTexts::LIM_PASO_TITULO, BaseTexts::LIM_PASO_TEXTO, false, self::PROCESO_MIN, self::PROCESO_MAX, $corpus, $nums, 'proceso', $rep);
        $out['faq'] = self::listaFaq($json['faq'] ?? null, $base['faq'], $corpus, $nums, $rep);
    }

    /**
     * Lista de {titulo,texto[,icono]} saneada. Descarta ítems corruptos o
     * inseguros; si quedan menos del mínimo se completa con los base (sin
     * repetir títulos); recorta al máximo.
     */
    private static function lista3($x, array $base, int $maxT, int $maxX, bool $conIcono, int $min, int $max, string $corpus, array $nums, string $nombre, array &$rep): array
    {
        $o = [];
        $vistos = [];
        $items = is_array($x) ? array_slice(array_values($x), 0, 20) : [];
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $t = TextClean::limpiar($it['titulo'] ?? '', $maxT);
            $tx = TextClean::limpiar($it['texto'] ?? '', $maxX);
            $kk = mb_strtolower($t, 'UTF-8');
            if ($t === '' || $tx === '' || isset($vistos[$kk]) || !self::textoSeguro($t, $corpus, $nums) || !self::textoSeguro($tx, $corpus, $nums)) {
                continue;
            }
            $vistos[$kk] = true;
            $e = ['titulo' => $t, 'texto' => $tx];
            if ($conIcono) {
                $ic = self::icono($it['icono'] ?? '');
                if ($ic === '') {
                    $ic = (string)($base[count($o) % max(1, count($base))]['icono'] ?? 'star');
                }
                $e['icono'] = $ic;
            }
            $o[] = $e;
            if (count($o) >= $max) {
                break;
            }
        }
        if (!$o) {
            $rep[] = $nombre;
            $o = array_slice($base, 0, $max);   // bloque ausente o corrupto por completo: se usa el texto base completo
        } elseif (count($o) < $min) {
            $rep[] = $nombre;
            foreach ($base as $b) {
                if (count($o) >= $min) {
                    break;
                }
                if (!isset($vistos[mb_strtolower($b['titulo'], 'UTF-8')])) {
                    $o[] = $b;
                }
            }
        }
        return array_values($o);
    }

    private static function listaFaq($x, array $base, string $corpus, array $nums, array &$rep): array
    {
        $o = [];
        $vistos = [];
        $items = is_array($x) ? array_slice(array_values($x), 0, 20) : [];
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $p = TextClean::limpiar($it['p'] ?? '', BaseTexts::LIM_FAQ_P);
            $r = TextClean::limpiar($it['r'] ?? '', BaseTexts::LIM_FAQ_R);
            $kk = mb_strtolower($p, 'UTF-8');
            if ($p === '' || $r === '' || isset($vistos[$kk]) || !self::textoSeguro($p, $corpus, $nums) || !self::textoSeguro($r, $corpus, $nums)) {
                continue;
            }
            $vistos[$kk] = true;
            $o[] = ['p' => $p, 'r' => $r];
            if (count($o) >= self::FAQ_MAX) {
                break;
            }
        }
        if (!$o) {
            $rep[] = 'faq';
            $o = array_slice($base, 0, self::FAQ_MAX);
        } elseif (count($o) < self::FAQ_MIN) {
            $rep[] = 'faq';
            foreach ($base as $b) {
                if (count($o) >= self::FAQ_MIN) {
                    break;
                }
                if (!isset($vistos[mb_strtolower($b['p'], 'UTF-8')])) {
                    $o[] = $b;
                }
            }
        }
        return array_values($o);
    }

    /** ¿El texto parece una instrucción dirigida a la IA? (para no reutilizarlo como contenido) */
    public static function pareceInstruccion(string $t): bool
    {
        return preg_match(self::PATRON_INSTRUCCION, $t) === 1;
    }

    /** ¿El texto evita inventos e instrucciones ajenas? */
    public static function textoSeguro(string $t, string $corpus, array $numsPermitidos): bool
    {
        if (preg_match(self::PATRON_INSTRUCCION, $t)) {
            return false;
        }
        if (preg_match_all('/\d+/u', $t, $m)) {
            foreach ($m[0] as $d) {
                if (!isset($numsPermitidos[$d]) && !isset($numsPermitidos[ltrim($d, '0')])) {
                    return false;
                }
            }
        }
        if (preg_match_all('~(?:https?://|www\.)[^\s]+|[\w.+-]+@[\w-]+\.[\w.-]+~iu', $t, $m)) {
            foreach ($m[0] as $u) {
                if (strpos($corpus, mb_strtolower($u, 'UTF-8')) === false) {
                    return false;
                }
            }
        }
        if (preg_match_all(self::PATRON_INVENTOS, $t, $m)) {
            foreach ($m[0] as $frase) {
                if (strpos($corpus, mb_strtolower($frase, 'UTF-8')) === false) {
                    return false;
                }
            }
        }
        return true;
    }

    /** Texto de referencia: todo lo escrito por el cliente en el brief. */
    public static function corpus(array $brief): string
    {
        $partes = [];
        $f = function ($x) use (&$f, &$partes): void {
            if (is_array($x)) {
                foreach ($x as $v) {
                    $f($v);
                }
            } elseif (is_string($x) || is_int($x) || is_float($x)) {
                $partes[] = (string)$x;
            }
        };
        foreach (['negocio', 'contenido', 'tienda', 'contacto'] as $k) {
            if (isset($brief[$k])) {
                $f($brief[$k]);
            }
        }
        return mb_strtolower(implode("\n", $partes), 'UTF-8');
    }

    /** @return array<string,true> */
    public static function numeros(string $corpus): array
    {
        $o = [];
        if (preg_match_all('/\d+/u', $corpus, $m)) {
            foreach ($m[0] as $d) {
                $o[$d] = true;
                $o[ltrim($d, '0')] = true;
            }
        }
        return $o;
    }

    // ------------------------------------------------------------------
    // Extracción de presentaciones (§5.2)
    // ------------------------------------------------------------------

    /**
     * Normaliza el JSON de extracción al esquema §5.2. Nunca devuelve claves
     * fuera del esquema; todo campo no encontrado es ""/[]. `imagenes` y
     * `conflictos` quedan vacíos (los completa PresentationAnalyzer).
     */
    public static function validarExtraccion(array $j): array
    {
        $vt = function ($x, int $max, bool $multi = false): array {
            $v = $x;
            $tx = false;
            if (is_array($x)) {
                $v = $x['v'] ?? ($x['valor'] ?? '');
                $tx = !empty($x['textual']) && $x['textual'] !== 'false';
            }
            $v = (is_string($v) || is_int($v) || is_float($v)) ? TextClean::limpiar((string)$v, $max, $multi) : '';
            if ($v !== '' && preg_match(self::PATRON_INSTRUCCION, $v)) {
                $v = '';
            }
            return ['v' => $v, 'textual' => $v !== '' && $tx];
        };
        $out = [
            'nombre' => $vt($j['nombre'] ?? '', 80),
            'rubro_sugerido' => $vt($j['rubro_sugerido'] ?? '', 60),
            'frase_principal' => $vt($j['frase_principal'] ?? '', 170),
            'quienes_somos' => $vt($j['quienes_somos'] ?? '', 900, true),
        ];

        $servicios = [];
        $vistos = [];
        foreach (self::lista($j['servicios'] ?? null, 200) as $s) {
            $s = is_array($s) ? $s : ['nombre' => $s];
            $n = TextClean::limpiar($s['nombre'] ?? '', 80);
            $d = TextClean::limpiar($s['descripcion'] ?? '', 600, true);
            if ($n === '' || preg_match(self::PATRON_INSTRUCCION, $n . ' ' . $d)) {
                continue;
            }
            $k = mb_strtolower($n, 'UTF-8');
            if (isset($vistos[$k])) {
                continue;
            }
            $vistos[$k] = true;
            $servicios[] = ['nombre' => $n, 'descripcion' => $d, 'textual' => !empty($s['textual']) && $s['textual'] !== 'false'];
        }
        $out['servicios'] = $servicios;

        $productos = [];
        $vistos = [];
        foreach (self::lista($j['productos'] ?? null, 500) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $n = TextClean::limpiar($p['nombre'] ?? '', 120);
            if ($n === '' || preg_match(self::PATRON_INSTRUCCION, $n)) {
                continue;
            }
            $k = mb_strtolower($n, 'UTF-8');
            if (isset($vistos[$k])) {
                continue;
            }
            $vistos[$k] = true;
            $productos[] = [
                'nombre' => $n,
                'descripcion' => TextClean::limpiar($p['descripcion'] ?? '', 600, true),
                'precio' => self::precio($p['precio'] ?? ''),
                'categoria' => TextClean::limpiar($p['categoria'] ?? '', 80),
                'textual' => !empty($p['textual']) && $p['textual'] !== 'false',
            ];
        }
        $out['productos'] = $productos;
        $out['categorias'] = TextClean::limpiarLista($j['categorias'] ?? [], 100, 80);

        $c = is_array($j['contacto'] ?? null) ? $j['contacto'] : [];
        $contacto = [];
        foreach (['telefono', 'whatsapp'] as $k) {
            $r = $vt($c[$k] ?? '', 60);
            $r['v'] = self::telefono($r['v']);
            $r['textual'] = $r['v'] !== '' && $r['textual'];
            $contacto[$k] = $r;
        }
        $r = $vt($c['correo'] ?? '', 120);
        $r['v'] = self::correo($r['v']);
        $r['textual'] = $r['v'] !== '' && $r['textual'];
        $contacto['correo'] = $r;
        $contacto['direccion'] = $vt($c['direccion'] ?? '', 200);
        $contacto['horario'] = $vt($c['horario'] ?? '', 200);
        $redes = is_array($c['redes'] ?? null) ? $c['redes'] : [];
        $ro = [];
        foreach (array_keys(self::REDES) as $red) {
            $raw = $redes[$red] ?? '';
            if (is_array($raw)) {
                $raw = $raw['v'] ?? '';
            }
            $ro[$red] = self::urlRed($red, is_string($raw) ? $raw : '');
        }
        $contacto['redes'] = $ro;
        $out['contacto'] = $contacto;

        $cols = [];
        foreach (self::lista($j['colores_marca'] ?? null, 6) as $cc) {
            $cc = is_string($cc) ? strtoupper(trim($cc)) : '';
            if (preg_match('/^#?([0-9A-F]{6})$/', $cc, $mm) && !in_array('#' . $mm[1], $cols, true)) {
                $cols[] = '#' . $mm[1];
            }
            if (count($cols) >= 3) { break; }
        }
        $out['colores'] = $cols;
        $out['imagenes'] = [];
        $out['conflictos'] = [];
        $id = strtolower(TextClean::limpiar(is_array($j['idioma'] ?? null) ? '' : ($j['idioma'] ?? ''), 10));
        $out['idioma'] = ($id === 'es' || $id === 'en') ? $id : ($id === '' ? 'es' : 'otro');
        return $out;
    }

    private static function lista($x, int $max): array
    {
        if (!is_array($x)) {
            return [];
        }
        return array_slice(array_values($x), 0, $max);
    }

    /** Teléfono con solo dígitos, "+" inicial y espacios; "" si no es válido. */
    public static function telefono(string $s): string
    {
        foreach (preg_split('~[/,;|]|\s+(?:y|o|and|or)\s+~iu', $s) as $parte) {
            $p = preg_replace('/[^0-9+ ]/', '', str_replace(['-', '.', '(', ')'], ' ', $parte)) ?? '';
            $p = trim(preg_replace('/\s+/', ' ', $p) ?? '');
            $p = ($p !== '' && $p[0] === '+' ? '+' : '') . str_replace('+', '', $p);
            $dig = strlen(preg_replace('/\D/', '', $p) ?? '');
            if ($dig >= 7 && $dig <= 15) {
                return $p;
            }
        }
        return '';
    }

    public static function correo(string $s): string
    {
        $s = strtolower(trim($s));
        if ($s === '' || strlen($s) > 120 || !filter_var($s, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        return $s;
    }

    /** URL de red social solo si el host es el esperado. */
    public static function urlRed(string $red, string $v): string
    {
        $v = TextClean::limpiar($v, 250);
        if ($v === '' || !isset(self::REDES[$red]) || preg_match('/\s/', $v)) {
            return '';
        }
        if (!preg_match('~^https?://~i', $v)) {
            $v = 'https://' . ltrim($v, '/');
        }
        if (!filter_var($v, FILTER_VALIDATE_URL)) {
            return '';
        }
        $p = parse_url($v);
        $host = strtolower((string)($p['host'] ?? ''));
        if ($host === '' || isset($p['user']) || isset($p['pass']) || isset($p['port'])) {
            return '';
        }
        foreach (self::REDES[$red] as $dom) {
            if ($host === $dom || substr($host, -strlen($dom) - 1) === '.' . $dom) {
                return 'https://' . $host . ($p['path'] ?? '') . (isset($p['query']) ? '?' . $p['query'] : '');
            }
        }
        return '';
    }

    /** @return float|string número >= 0 o "" si no hay precio. */
    public static function precio($x)
    {
        if (is_int($x) || is_float($x)) {
            return $x >= 0 && $x < 100000000 ? round((float)$x, 2) : '';
        }
        if (!is_string($x)) {
            return '';
        }
        $s = preg_replace('/[^0-9.,]/', '', $x) ?? '';
        if ($s === '' || !preg_match('/\d/', $s)) {
            return '';
        }
        $c = strrpos($s, ',');
        $p = strrpos($s, '.');
        if ($c !== false && $p !== false) {
            if ($c > $p) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif ($c !== false) {
            $s = (strlen($s) - $c - 1 === 3 && substr_count($s, ',') >= 1 && $c > 0) ? str_replace(',', '', $s) : str_replace(',', '.', $s);
        } elseif ($p !== false && substr_count($s, '.') > 1) {
            $s = str_replace('.', '', $s);
        }
        if (!is_numeric($s)) {
            return '';
        }
        $f = (float)$s;
        return $f >= 0 && $f < 100000000 ? round($f, 2) : '';
    }
}
