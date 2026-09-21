<?php
/**
 * Kaptor - Lectura de una página HTML para el auditor.
 *
 * El extractor de correos trabaja con expresiones regulares porque solo busca
 * cadenas sueltas. Auditar es otra cosa: hay que contar los <h1>, saber qué
 * imágenes llevan alt o si un <script> bloquea la carga, y para eso hace falta
 * entender la estructura del documento.
 *
 * Se usa DOMDocument cuando está disponible (lo está en casi todos los hosting)
 * y se cae a expresiones regulares para lo imprescindible cuando no lo está,
 * de forma que el auditor nunca se queda mudo.
 */
declare(strict_types=1);

final class Pagina
{
    /** Tamaño máximo de HTML que se analiza (2 MB). Más allá no aporta nada. */
    public const MAX_HTML = 2097152;

    private string $html;
    private string $base;
    private ?DOMXPath $xp = null;

    /** Texto visible, calculado una sola vez. */
    private ?string $texto = null;

    public function __construct(string $html, string $urlBase)
    {
        $this->html = strlen($html) > self::MAX_HTML ? substr($html, 0, self::MAX_HTML) : $html;
        $this->base = $urlBase;

        if (class_exists('DOMDocument') && $this->html !== '') {
            $this->xp = self::analizar($this->html);
        }
    }

    /** El HTML tal cual, por si alguien necesita buscar algo a mano. */
    public function html(): string
    {
        return $this->html;
    }

    /** ¿Se pudo leer el documento con DOM? */
    public function conDom(): bool
    {
        return $this->xp instanceof DOMXPath;
    }

    /** Construye el DOMXPath a partir del HTML, silenciando los avisos. */
    private static function analizar(string $html): ?DOMXPath
    {
        $doc = new DOMDocument();

        // El HTML de la vida real viene roto: libxml escupiría cientos de
        // avisos. Se silencian y se restaura el estado anterior al salir.
        $antes = libxml_use_internal_errors(true);
        try {
            // El prefijo obliga a interpretarlo como UTF-8: sin él, DOMDocument
            // asume ISO-8859-1 y destroza todos los acentos.
            $ok = $doc->loadHTML(
                '<?xml encoding="UTF-8">' . $html,
                LIBXML_NONET | (defined('LIBXML_COMPACT') ? LIBXML_COMPACT : 0)
            );
        } catch (Throwable $e) {
            $ok = false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($antes);
        }

        return $ok ? new DOMXPath($doc) : null;
    }

    // ---------------------------------------------------------------- básicos

    /** Contenido de <title>. */
    public function titulo(): string
    {
        if ($this->xp) {
            $n = $this->xp->query('//title');
            if ($n && $n->length > 0) { return self::limpiar($n->item(0)->textContent); }
            return '';
        }
        return preg_match('~<title[^>]*>(.*?)</title>~is', $this->html, $m)
            ? self::limpiar(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : '';
    }

    /** Valor de una <meta name="..."> (o property, para Open Graph). */
    public function meta(string $nombre): string
    {
        $nombre = strtolower($nombre);

        if ($this->xp) {
            // translate() hace el equivalente a strtolower dentro de XPath 1.0,
            // que no tiene lower-case(). Así "Description" y "description" valen.
            $may = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $min = 'abcdefghijklmnopqrstuvwxyz';
            $n = $this->xp->query(
                "//meta[translate(@name,'$may','$min')='$nombre' or translate(@property,'$may','$min')='$nombre'][@content]"
            );
            if ($n && $n->length > 0) {
                return self::limpiar((string) $n->item(0)->attributes->getNamedItem('content')?->nodeValue);
            }
            return '';
        }

        $esc = preg_quote($nombre, '~');
        if (preg_match('~<meta[^>]+(?:name|property)\s*=\s*["\']' . $esc . '["\'][^>]*content\s*=\s*["\']([^"\']*)~i', $this->html, $m)) {
            return self::limpiar(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('~<meta[^>]+content\s*=\s*["\']([^"\']*)["\'][^>]*(?:name|property)\s*=\s*["\']' . $esc . '["\']~i', $this->html, $m)) {
            return self::limpiar(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return '';
    }

    /** Idioma declarado en <html lang="...">. */
    public function idioma(): string
    {
        if ($this->xp) {
            $n = $this->xp->query('//html[@lang]');
            if ($n && $n->length > 0) {
                return strtolower(self::limpiar((string) $n->item(0)->attributes->getNamedItem('lang')?->nodeValue));
            }
            return '';
        }
        return preg_match('~<html[^>]+lang\s*=\s*["\']([^"\']+)~i', $this->html, $m) ? strtolower(trim($m[1])) : '';
    }

    /**
     * Encabezados por nivel.
     *
     * @return array<int,string[]> [1 => ['Título'], 2 => [...], ...]
     */
    public function encabezados(): array
    {
        $salida = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => []];

        if ($this->xp) {
            for ($n = 1; $n <= 6; $n++) {
                foreach ($this->xp->query('//h' . $n) ?: [] as $nodo) {
                    $txt = self::limpiar($nodo->textContent);
                    if ($txt !== '') { $salida[$n][] = $txt; }
                }
            }
            return $salida;
        }

        for ($n = 1; $n <= 6; $n++) {
            if (preg_match_all('~<h' . $n . '[^>]*>(.*?)</h' . $n . '>~is', $this->html, $m)) {
                foreach ($m[1] as $bruto) {
                    $txt = self::limpiar(strip_tags($bruto));
                    if ($txt !== '') { $salida[$n][] = $txt; }
                }
            }
        }
        return $salida;
    }

    /**
     * Imágenes con lo que hace falta para auditarlas.
     *
     * @return array<int,array{url:string,alt:bool,dimensiones:bool,lazy:bool,posicion:int}>
     */
    public function imagenes(): array
    {
        $salida = [];

        if ($this->xp) {
            $i = 0;
            foreach ($this->xp->query('//img') ?: [] as $img) {
                /** @var DOMElement $img */
                $src = trim($img->getAttribute('src'));
                // Carga diferida por atributo propio de muchas plantillas.
                if ($src === '' || str_starts_with($src, 'data:')) {
                    $src = trim($img->getAttribute('data-src'));
                }
                $abs = $src !== '' ? Http::urlAbsoluta($src, $this->base) : '';
                $salida[] = [
                    'url'         => $abs,
                    // Un alt vacío es válido para imágenes decorativas, pero
                    // que NO exista el atributo sí es un fallo.
                    'alt'         => $img->hasAttribute('alt'),
                    'dimensiones' => $img->hasAttribute('width') && $img->hasAttribute('height'),
                    'lazy'        => strtolower($img->getAttribute('loading')) === 'lazy',
                    'posicion'    => $i++,
                ];
            }
            return $salida;
        }

        if (preg_match_all('~<img\b([^>]*)>~i', $this->html, $m)) {
            foreach ($m[1] as $i => $attrs) {
                $src = preg_match('~\bsrc\s*=\s*["\']([^"\']+)~i', $attrs, $s) ? $s[1] : '';
                if ($src === '' || str_starts_with($src, 'data:')) {
                    $src = preg_match('~\bdata-src\s*=\s*["\']([^"\']+)~i', $attrs, $s2) ? $s2[1] : $src;
                }
                $salida[] = [
                    'url'         => $src !== '' ? Http::urlAbsoluta($src, $this->base) : '',
                    'alt'         => (bool) preg_match('~\balt\s*=~i', $attrs),
                    'dimensiones' => preg_match('~\bwidth\s*=~i', $attrs) && preg_match('~\bheight\s*=~i', $attrs),
                    'lazy'        => (bool) preg_match('~\bloading\s*=\s*["\']lazy~i', $attrs),
                    'posicion'    => $i,
                ];
            }
        }
        return $salida;
    }

    /**
     * Enlaces <a href>.
     *
     * @return array<int,array{url:string,texto:string,interno:bool,externo:bool}>
     */
    public function enlaces(): array
    {
        $hostBase = strtolower((string) parse_url($this->base, PHP_URL_HOST));
        $salida   = [];
        $crudos   = [];

        if ($this->xp) {
            foreach ($this->xp->query('//a[@href]') ?: [] as $a) {
                /** @var DOMElement $a */
                $crudos[] = [$a->getAttribute('href'), self::limpiar($a->textContent)];
            }
        } elseif (preg_match_all('~<a\b[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)</a>~is', $this->html, $m, PREG_SET_ORDER)) {
            foreach ($m as $par) { $crudos[] = [$par[1], self::limpiar(strip_tags($par[2]))]; }
        }

        foreach ($crudos as [$href, $texto]) {
            $abs = Http::urlAbsoluta($href, $this->base);
            if ($abs === '') {
                // mailto:, tel:, whatsapp:... no son navegables pero sí
                // interesan al auditor, así que se conservan en crudo.
                $salida[] = ['url' => trim($href), 'texto' => $texto, 'interno' => false, 'externo' => false];
                continue;
            }
            $host = strtolower((string) parse_url($abs, PHP_URL_HOST));
            $mismo = $host === $hostBase
                  || str_ends_with($host, '.' . $hostBase)
                  || str_ends_with($hostBase, '.' . $host);
            $salida[] = ['url' => $abs, 'texto' => $texto, 'interno' => $mismo, 'externo' => !$mismo];
        }
        return $salida;
    }

    /**
     * Hojas de estilo enlazadas.
     *
     * @return string[] URLs absolutas
     */
    public function estilos(): array
    {
        $urls = [];
        if ($this->xp) {
            foreach ($this->xp->query('//link[@rel][@href]') ?: [] as $l) {
                /** @var DOMElement $l */
                if (!str_contains(strtolower($l->getAttribute('rel')), 'stylesheet')) { continue; }
                $abs = Http::urlAbsoluta($l->getAttribute('href'), $this->base);
                if ($abs !== '') { $urls[] = $abs; }
            }
        } elseif (preg_match_all('~<link\b[^>]*rel\s*=\s*["\'][^"\']*stylesheet[^"\']*["\'][^>]*>~i', $this->html, $m)) {
            foreach ($m[0] as $tag) {
                if (preg_match('~href\s*=\s*["\']([^"\']+)~i', $tag, $h)) {
                    $abs = Http::urlAbsoluta($h[1], $this->base);
                    if ($abs !== '') { $urls[] = $abs; }
                }
            }
        }
        return array_values(array_unique($urls));
    }

    /**
     * Scripts, separando los que frenan el pintado de la página.
     *
     * Un <script src> dentro de <head> sin defer ni async obliga al navegador
     * a parar, descargarlo y ejecutarlo antes de enseñar nada.
     *
     * @return array{total:int,externos:int,bloqueantes:int,urls:string[],en_linea:int}
     */
    public function scripts(): array
    {
        $r = ['total' => 0, 'externos' => 0, 'bloqueantes' => 0, 'urls' => [], 'en_linea' => 0];

        if ($this->xp) {
            foreach ($this->xp->query('//script') ?: [] as $s) {
                /** @var DOMElement $s */
                $r['total']++;
                $src = trim($s->getAttribute('src'));
                if ($src === '') { $r['en_linea']++; continue; }

                $r['externos']++;
                $abs = Http::urlAbsoluta($src, $this->base);
                if ($abs !== '') { $r['urls'][] = $abs; }

                $difiere = $s->hasAttribute('defer') || $s->hasAttribute('async')
                        || strtolower($s->getAttribute('type')) === 'module';
                if (!$difiere && self::dentroDeCabeza($s)) { $r['bloqueantes']++; }
            }
            return $r;
        }

        if (preg_match_all('~<script\b([^>]*)>~i', $this->html, $m)) {
            $cabeza = stripos($this->html, '</head>');
            foreach ($m[1] as $i => $attrs) {
                $r['total']++;
                if (!preg_match('~\bsrc\s*=\s*["\']([^"\']+)~i', $attrs, $s)) { $r['en_linea']++; continue; }
                $r['externos']++;
                $abs = Http::urlAbsoluta($s[1], $this->base);
                if ($abs !== '') { $r['urls'][] = $abs; }
                $difiere = preg_match('~\b(defer|async)\b~i', $attrs) || preg_match('~type\s*=\s*["\']module~i', $attrs);
                $pos = stripos($this->html, $m[0][$i]);
                if (!$difiere && $cabeza !== false && $pos !== false && $pos < $cabeza) { $r['bloqueantes']++; }
            }
        }
        return $r;
    }

    /** ¿El nodo cuelga de <head>? */
    private static function dentroDeCabeza(DOMNode $nodo): bool
    {
        for ($p = $nodo->parentNode; $p !== null; $p = $p->parentNode) {
            if (strtolower($p->nodeName) === 'head') { return true; }
        }
        return false;
    }

    /**
     * Bloques JSON-LD (schema.org) ya descodificados.
     *
     * @return array<int,array> Cada elemento es el JSON de un <script type="application/ld+json">
     */
    public function jsonLd(): array
    {
        $crudos = [];

        if ($this->xp) {
            foreach ($this->xp->query('//script[@type]') ?: [] as $s) {
                /** @var DOMElement $s */
                if (stripos($s->getAttribute('type'), 'ld+json') === false) { continue; }
                $crudos[] = $s->textContent;
            }
        } elseif (preg_match_all('~<script\b[^>]*type\s*=\s*["\'][^"\']*ld\+json[^"\']*["\'][^>]*>(.*?)</script>~is', $this->html, $m)) {
            $crudos = $m[1];
        }

        $salida = [];
        foreach ($crudos as $txt) {
            $datos = json_decode(trim($txt), true);
            if (is_array($datos)) { $salida[] = $datos; }
        }
        return $salida;
    }

    /**
     * Tipos de schema.org declarados, en minúsculas y sin repetir.
     *
     * Recorre el JSON entero porque los @type aparecen anidados dentro de
     * @graph, de "mainEntity", de "publisher"...
     *
     * @return string[]
     */
    public function tiposSchema(): array
    {
        $tipos = [];
        $ver = static function ($nodo) use (&$ver, &$tipos): void {
            if (!is_array($nodo)) { return; }
            foreach ($nodo as $clave => $valor) {
                if ($clave === '@type') {
                    foreach ((array) $valor as $t) {
                        if (is_string($t) && $t !== '') { $tipos[] = strtolower($t); }
                    }
                } elseif (is_array($valor)) {
                    $ver($valor);
                }
            }
        };
        foreach ($this->jsonLd() as $bloque) { $ver($bloque); }

        // Microdatos: la otra forma de declarar schema, todavía muy usada.
        if (preg_match_all('~itemtype\s*=\s*["\']https?://schema\.org/([A-Za-z]+)~i', $this->html, $m)) {
            foreach ($m[1] as $t) { $tipos[] = strtolower($t); }
        }

        return array_values(array_unique($tipos));
    }

    /** Número de formularios y si alguno envía sin cifrar. */
    public function formularios(): array
    {
        $r = ['total' => 0, 'inseguros' => 0, 'con_campos' => 0];

        if ($this->xp) {
            foreach ($this->xp->query('//form') ?: [] as $f) {
                /** @var DOMElement $f */
                $r['total']++;
                $accion = trim($f->getAttribute('action'));
                if (stripos($accion, 'http://') === 0) { $r['inseguros']++; }
                $campos = $this->xp->query('.//input | .//textarea | .//select', $f);
                if ($campos && $campos->length > 0) { $r['con_campos']++; }
            }
            return $r;
        }

        if (preg_match_all('~<form\b([^>]*)>~i', $this->html, $m)) {
            $r['total'] = count($m[1]);
            foreach ($m[1] as $attrs) {
                if (preg_match('~action\s*=\s*["\']http://~i', $attrs)) { $r['inseguros']++; }
            }
            $r['con_campos'] = $r['total'];
        }
        return $r;
    }

    /**
     * Marcos incrustados, diciendo si están escondidos y por qué.
     *
     * Lo usa la búsqueda de código malicioso: un marco de tamaño cero que carga
     * otro sitio es la forma clásica de colgar contenido ajeno de una página
     * sin que el dueño se entere.
     *
     * @return array<int,array{src:string,oculto:bool,motivo:string}>
     */
    public function iframes(): array
    {
        $salida = [];

        $mirar = function (string $src, string $estilo, string $ancho, string $alto) use (&$salida): void {
            if ($src === '') { return; }
            $abs = Http::urlAbsoluta($src, $this->base);
            if ($abs === '') { return; }

            $motivo = '';
            $e = strtolower(preg_replace('~\s+~', '', $estilo) ?? '');

            if ($ancho !== '' && (int) $ancho <= 2 && $alto !== '' && (int) $alto <= 2) {
                $motivo = 'mide ' . (int) $ancho . '×' . (int) $alto . ' píxeles';
            } elseif (str_contains($e, 'display:none')) {
                $motivo = 'está oculto con display:none';
            } elseif (str_contains($e, 'visibility:hidden')) {
                $motivo = 'está oculto con visibility:hidden';
            } elseif (preg_match('~(width|height):0(px|%)?(;|$)~', $e)) {
                $motivo = 'tiene tamaño cero';
            } elseif (preg_match('~(left|top):-\d{3,}~', $e)) {
                $motivo = 'está colocado fuera de la pantalla';
            } elseif (str_contains($e, 'opacity:0')) {
                $motivo = 'es completamente transparente';
            }

            $salida[] = ['src' => $abs, 'oculto' => $motivo !== '', 'motivo' => $motivo];
        };

        if ($this->xp) {
            foreach ($this->xp->query('//iframe') ?: [] as $f) {
                /** @var DOMElement $f */
                $mirar(trim($f->getAttribute('src')), $f->getAttribute('style'),
                       $f->getAttribute('width'), $f->getAttribute('height'));
            }
            return $salida;
        }

        if (preg_match_all('~<iframe\b([^>]*)>~i', $this->html, $m)) {
            foreach ($m[1] as $attrs) {
                $at = static fn(string $n): string
                    => preg_match('~\b' . $n . '\s*=\s*["\']([^"\']*)~i', $attrs, $x) ? $x[1] : '';
                $mirar($at('src'), $at('style'), $at('width'), $at('height'));
            }
        }
        return $salida;
    }

    /**
     * Bloques escondidos a la vista, con los enlaces que llevan dentro.
     *
     * Esconder cosas es de lo más normal (menús desplegables, ventanas,
     * pestañas), así que esto NO significa nada por sí solo: quien lo usa
     * decide si importa mirando cuántos enlaces hacia fuera hay dentro.
     *
     * @return array<int,array{enlaces:string[],texto:string,motivo:string}>
     */
    public function bloquesOcultos(): array
    {
        if (!$this->xp) { return []; }

        $salida = [];
        // Solo los contenedores: mirar cada etiqueta escondida del documento
        // costaría un mundo y devolvería lo mismo repetido.
        foreach ($this->xp->query('//div[@style] | //span[@style] | //p[@style] | //section[@style]') ?: [] as $nodo) {
            /** @var DOMElement $nodo */
            $e = strtolower(preg_replace('~\s+~', '', $nodo->getAttribute('style')) ?? '');

            $motivo = '';
            if (str_contains($e, 'display:none'))            { $motivo = 'display:none'; }
            elseif (str_contains($e, 'visibility:hidden'))   { $motivo = 'visibility:hidden'; }
            elseif (preg_match('~text-indent:-\d{3,}~', $e)) { $motivo = 'texto desplazado fuera'; }
            elseif (preg_match('~(left|top|margin-left|margin-top):-\d{4,}~', $e)) { $motivo = 'colocado fuera de la pantalla'; }
            elseif (preg_match('~font-size:0(px|em|rem)?(;|$)~', $e)) { $motivo = 'letra de tamaño cero'; }
            elseif (preg_match('~height:0(px)?;?.*overflow:hidden~', $e)) { $motivo = 'alto cero con desbordamiento oculto'; }

            if ($motivo === '') { continue; }

            $enlaces = [];
            foreach ($this->xp->query('.//a[@href]', $nodo) ?: [] as $a) {
                /** @var DOMElement $a */
                $abs = Http::urlAbsoluta($a->getAttribute('href'), $this->base);
                if ($abs !== '') { $enlaces[] = $abs; }
            }
            if (!$enlaces) { continue; }

            $salida[] = [
                'enlaces' => $enlaces,
                'texto'   => self::limpiar(mb_substr($nodo->textContent, 0, 1500)),
                'motivo'  => $motivo,
            ];
            // Con unos pocos bloques basta para saber si el sitio está infectado.
            if (count($salida) >= 12) { break; }
        }
        return $salida;
    }

    /** Número de tablas y de listas: es lo que una IA cita con facilidad. */
    public function tablasYListas(): array
    {
        if ($this->xp) {
            return [
                'tablas' => ($this->xp->query('//table') ?: new DOMNodeList())->length,
                'listas' => ($this->xp->query('//ul | //ol') ?: new DOMNodeList())->length,
            ];
        }
        return [
            'tablas' => preg_match_all('~<table\b~i', $this->html),
            'listas' => preg_match_all('~<(ul|ol)\b~i', $this->html),
        ];
    }

    /** Direcciones de recursos servidos por http:// dentro de una página https. */
    public function contenidoMixto(): array
    {
        if (stripos($this->base, 'https://') !== 0) { return []; }

        $mixtos = [];
        // Solo cuentan los atributos que el navegador carga de verdad; un
        // <a href="http://..."> es un enlace normal, no contenido mixto.
        if (preg_match_all('~\b(?:src|data-src)\s*=\s*["\'](http://[^"\']+)~i', $this->html, $m)) {
            $mixtos = array_merge($mixtos, $m[1]);
        }
        if (preg_match_all('~<link\b[^>]*href\s*=\s*["\'](http://[^"\']+)~i', $this->html, $m2)) {
            $mixtos = array_merge($mixtos, $m2[1]);
        }
        return array_values(array_unique($mixtos));
    }

    /** Texto visible de la página, sin menús repetidos ni código. */
    public function texto(): string
    {
        if ($this->texto !== null) { return $this->texto; }

        if ($this->xp) {
            // Se recorre el árbol saltando lo que no es contenido para el
            // lector. NO se borran esos nodos del documento: el DOM lo comparten
            // todos los métodos de esta clase, y arrancarle los <script> aquí
            // dejaba sin datos estructurados al chequeo que los mira después.
            $cuerpo = $this->xp->query('//body');
            $bruto  = $cuerpo && $cuerpo->length > 0 ? self::textoDe($cuerpo->item(0)) : '';
        } else {
            $bruto = (string) preg_replace('~<(script|style|noscript)\b[^>]*>.*?</\1>~is', ' ', $this->html);
            $bruto = strip_tags($bruto);
            $bruto = html_entity_decode($bruto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $this->texto = self::limpiar($bruto);
    }

    /** Número de palabras del texto visible. */
    public function palabras(): int
    {
        $txt = $this->texto();
        if ($txt === '') { return 0; }
        return count(preg_split('~\s+~u', $txt, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /** ¿Aparece alguna de estas palabras en el texto visible? */
    public function mencionaAlguna(array $palabras): bool
    {
        $txt = ' ' . mb_strtolower($this->texto(), 'UTF-8') . ' ';
        foreach ($palabras as $p) {
            if ($p !== '' && str_contains($txt, mb_strtolower($p, 'UTF-8'))) { return true; }
        }
        return false;
    }

    /**
     * Texto de un nodo y sus hijos, saltando lo que no se lee.
     *
     * Hace lo mismo que textContent pero sin arrastrar el contenido de los
     * <script> y los <style>, y sin tocar el documento.
     */
    private static function textoDe(DOMNode $nodo, int $profundidad = 0): string
    {
        // Tope de seguridad: un HTML roto puede anidarse sin fin.
        if ($profundidad > 100) { return ''; }

        if ($nodo->nodeType === XML_TEXT_NODE || $nodo->nodeType === XML_CDATA_SECTION_NODE) {
            return (string) $nodo->nodeValue;
        }
        if ($nodo->nodeType !== XML_ELEMENT_NODE && $nodo->nodeType !== XML_DOCUMENT_NODE) {
            return '';
        }
        if (in_array(strtolower($nodo->nodeName), ['script', 'style', 'noscript', 'template', 'svg', 'iframe'], true)) {
            return '';
        }

        $txt = '';
        foreach ($nodo->childNodes as $hijo) {
            $txt .= self::textoDe($hijo, $profundidad + 1) . ' ';
        }
        return $txt;
    }

    /** Colapsa espacios y recorta. */
    private static function limpiar(string $txt): string
    {
        $txt = str_replace(["\xC2\xA0", "\r"], [' ', ' '], $txt);
        return trim((string) preg_replace('~\s+~u', ' ', $txt));
    }
}
