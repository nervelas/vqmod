<?php
/**
 * Kaptor - Motor de extracción de correos.
 *
 * Analiza un documento (HTML, JS, CSS, JSON, XML o texto) y devuelve todos los
 * correos que contiene, indicando con que técnica se encontro cada uno.
 *
 * Técnicas IMPLEMENTADAS
 *  1.  mailto:            enlaces mailto, incluidos ?subject=, ?cc= y ?bcc=
 *  2.  cloudflare         data-cfemail y /cdn-cgi/l/email-protection#HEX
 *  3.  atributos          data-*, value, content, title, alt, placeholder, aria-label
 *  4.  meta / jsonld      metadatos, JSON-LD y microdatos
 *  5.  comentarios        comentarios HTML (incluidos los que parten un correo)
 *  6.  texto              texto visible de la página
 *  7.  html               código fuente completo
 *  8.  etiquetas          correos partidos por etiquetas: info<span>@</span>web.com
 *  9.  entidades          &#64; &#x40; y entidades de cada carácter
 * 10.  urlencode          %40 y %2E (también doble codificación)
 * 11.  ofuscado           [at] (at) {at} " at " [arroba] [dot] (punto) [.] (@)...
 * 12.  escapes            \x40, @, \u{40} y escapes CSS \0040
 * 13.  charcode           String.fromCharCode(105,110,102,111,...)
 * 14.  concatenado        "info" + "@" + "dominio" + ".com"
 * 15.  base64             cadenas base64 y atob("...")
 * 16.  rot13              cifrado ROT13 (muy usado en scripts anti-robot)
 * 17.  invertido          texto al revés (truco CSS direction:rtl)
 * 18.  svg                texto dentro de gráficos SVG
 * 19.  json               respuestas de API/JSON enlazadas desde la página
 * 20.  sitemap            direcciones descubiertas en sitemap.xml
 *
 * Además devuelve los enlaces internos (para el rastreo profundo), los recursos
 * JS/CSS/SVG a revisar, los teléfonos y los perfiles sociales encontrados.
 */
declare(strict_types=1);

final class Extractor
{
    /**
     * Expresión principal. El buzon se limita a los caracteres que se usan en la
     * práctica: ampliarlo al RFC completo solo genera falsos positivos en código.
     */
    public const RE_CORREO = '~(?<![A-Za-z0-9._%+\-@])([A-Za-z0-9](?:[A-Za-z0-9._%+\-]{0,62}[A-Za-z0-9])?)@((?:[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,24})(?![A-Za-z0-9\-])~';

    /** Tamaño máximo del contenido para las pasadas mas costosas. */
    /**
     * Números de teléfono en texto. Solo se aceptan los que llevan prefijo
     * internacional: sin él es imposible saber a qué país pertenecen y se
     * dispararían los falsos positivos (precios, fechas, identificadores).
     * Los números nacionales se admiten si el administrador configura un
     * prefijo de país por defecto en los ajustes.
     */
    public const RE_TELEFONO_INTL = '~(?<![\d/.\-])(\+\s?\d[\d\s().\-]{6,20}\d)(?![\d])~';
    public const RE_TELEFONO_NAC  = '~(?<![\d/.\-])(\d[\d\s().\-]{6,16}\d)(?![\d])~';

    private const LIMITE_PASADAS_CARAS = 1600000;

    /** @var array<string,array{métodos:array<string,bool>,veces:int}> */
    private array $correos = [];
    /** @var array<string,bool> */
    private array $enlaces = [];
    /** @var array<string,bool> */
    private array $recursos = [];
    /** @var array<string,bool> */
    private array $sitemaps = [];
    /**
     * Teléfonos y WhatsApp encontrados.
     * @var array<string,array{metodos:array<string,bool>,veces:int,whatsapp:bool,datos:array}>
     */
    private array $telefonos = [];
    /** Enlaces de WhatsApp sin número visible (wa.me/message/…, grupos). @var array<string,bool> */
    private array $enlacesWa = [];
    /** @var array<string,bool> */
    private array $redes = [];

    private string $hostSitio;

    public function __construct(string $hostSitio = '')
    {
        $this->hostSitio = $hostSitio;
    }

    // ==========================================================================
    //  ENTRADA PRINCIPAL
    // ==========================================================================

    /**
     * Analiza un documento completo.
     *
     * @return array{correos:array,enlaces:string[],recursos:string[],sitemaps:string[],teléfonos:string[],redes:string[]}
     */
    public function analizar(string $contenido, string $url, string $tipoMime = 'text/html'): array
    {
        if ($contenido === '') { return $this->resultado(); }

        $esHtml = str_contains($tipoMime, 'html') || str_contains($tipoMime, 'xml')
            || preg_match('~<(?:html|body|div|a|p|span|meta)\b~i', substr($contenido, 0, 4000)) === 1;

        if ($esHtml) {
            $this->analizarHtml($contenido, $url);
        } else {
            // JS, CSS, JSON o texto plano.
            $this->analizarCodigo($contenido, $url, $tipoMime);
        }

        // Pasadas universales (valen para cualquier tipo de documento).
        $this->pasadasUniversales($contenido);

        return $this->resultado();
    }

    // ==========================================================================
    //  Análisis DE HTML
    // ==========================================================================

    private function analizarHtml(string $html, string $url): void
    {
        // ---- 1. Enlaces mailto: (con sus parámetros cc, bcc y subject) -------
        if (preg_match_all('~(?:href|data-href|data-mailto)\s*=\s*["\']?\s*mailto:([^"\'>\s]+)~i', $html, $m)) {
            foreach ($m[1] as $bruto) {
                $this->desdeMailto($bruto);
            }
        }
        // mailto sueltos dentro de scripts o atributos JS.
        if (preg_match_all('~mailto:([A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,24})~i', $html, $m2)) {
            foreach ($m2[1] as $correo) { $this->anotar($correo, 'mailto', $html); }
        }

        // ---- 2. Correos protegidos por Cloudflare ----------------------------
        $this->desdeCloudflare($html);

        // ---- 3. Comentarios HTML ---------------------------------------------
        if (preg_match_all('~<!--(.*?)-->~s', $html, $mc)) {
            foreach ($mc[1] as $comentario) {
                if (str_contains($comentario, '@') || str_contains($comentario, '&#')) {
                    $this->buscar($this->decodificarTodo($comentario), 'comentario', $comentario);
                }
            }
        }

        // ---- 8. Correos partidos por etiquetas o comentarios ------------------
        // info<!-- x -->@web.com  /  info<span>@</span>web.com
        $sinComentarios = (string) preg_replace('~<!--.*?-->~s', '', $html);
        $sinScripts     = (string) preg_replace('~<(script|style|noscript)\b[^>]*>.*?</\1>~is', ' ', $sinComentarios);
        // Solo se eliminan "en seco" las etiquetas en linea (span, b, em...), que son
        // las que parten un correo. Las estructurales se cambian por un espacio para
        // no pegar palabras de bloques distintos (<title>Iframe</title><p>correo@...).
        $enLinea        = 'span|b|i|em|strong|u|small|sub|sup|font|wbr|s|mark|tt|big|var|samp|kbd|abbr|code|nobr|bdo|bdi|ins|del';
        $unido          = (string) preg_replace('~</?(?!(?:' . $enLinea . ')\b)[a-zA-Z][^>]*>~', ' ', $sinComentarios);
        $unido          = (string) preg_replace('~<[^>]*>~', '', $unido);
        $unido          = $this->limpiarInvisibles(html_entity_decode($unido, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $this->buscar($unido, 'etiquetas', $unido);

        // ---- 6. Texto visible -------------------------------------------------
        $visible = (string) preg_replace('~<(br|p|div|li|td|tr|h[1-6]|section|article|footer|header)\b[^>]*>~i', "\n", $sinScripts);
        $visible = (string) preg_replace('~<[^>]*>~', ' ', $visible);
        $visible = $this->limpiarInvisibles(html_entity_decode($visible, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $this->buscar($visible, 'texto', $visible);
        $this->buscarTelefonos($visible, 'texto', $visible);
        // Ofuscaciones textuales sobre el texto visible (es donde suelen estar).
        $this->buscar($this->desofuscar($visible), 'ofuscado', $visible);

        // ---- 3b. Atributos que suelen esconder correos -----------------------
        $this->desdeAtributos($html);

        // ---- 4. JSON-LD y microdatos ------------------------------------------
        if (preg_match_all('~<script[^>]+type\s*=\s*["\']application/(?:ld\+json|json)["\'][^>]*>(.*?)</script>~is', $html, $mj)) {
            foreach ($mj[1] as $json) {
                $this->buscar($this->decodificarTodo($json), 'jsonld', $json);
                $datos = json_decode(trim($json), true);
                if (is_array($datos)) {
                    $this->buscar($this->aplanar($datos), 'jsonld', $json);
                }
            }
        }
        // Microdatos itemprop="email"
        if (preg_match_all('~itemprop\s*=\s*["\']email["\'][^>]*content\s*=\s*["\']([^"\']+)~i', $html, $mi)) {
            foreach ($mi[1] as $v) { $this->anotar($v, 'meta', $html); }
        }

        // ---- 18. Texto dentro de SVG ------------------------------------------
        if (preg_match_all('~<svg\b.*?</svg>~is', $html, $ms)) {
            foreach ($ms[0] as $svg) {
                if (!str_contains($svg, '@') && !str_contains($svg, '&#')) { continue; }
                $texto = (string) preg_replace('~<[^>]*>~', '', $svg);
                $this->buscar($this->decodificarTodo($texto), 'svg', $svg);
            }
        }

        // ---- 7. Código fuente completo ----------------------------------------
        $this->buscar($html, 'html', $html);
        $this->buscarTelefonos($html, 'html', $html);

        // ---- Scripts en linea: todas las técnicas de JavaScript ---------------
        if (preg_match_all('~<script\b[^>]*>(.*?)</script>~is', $html, $msc)) {
            foreach ($msc[1] as $js) {
                if ($js === '') { continue; }
                $this->analizarCodigo($js, $url, 'application/javascript');
            }
        }
        // ---- Estilos en linea: content: "\0040" -------------------------------
        if (preg_match_all('~<style\b[^>]*>(.*?)</style>~is', $html, $mst)) {
            foreach ($mst[1] as $css) {
                $this->analizarCodigo($css, $url, 'text/css');
            }
        }

        // ---- Enlaces, recursos, teléfonos y redes ------------------------------
        $this->recolectarEnlaces($html, $url);
        $this->recolectarRecursos($html, $url);
        $this->recolectarTelefonos($html);
        $this->recolectarRedes($html);
    }

    // ==========================================================================
    //  Análisis DE JS / CSS / JSON
    // ==========================================================================

    private function analizarCodigo(string $codigo, string $url, string $tipoMime): void
    {
        $etiqueta = str_contains($tipoMime, 'css') ? 'css'
            : (str_contains($tipoMime, 'json') ? 'json' : 'js');

        // Texto tal cual.
        $this->buscar($codigo, $etiqueta, $codigo);
        $this->buscarTelefonos($codigo, $etiqueta, $codigo);
        $this->recolectarTelefonos($codigo);        // wa.me y widgets dentro del JS

        // 14. Concatenaciones: "info" + "@" + "dominio.com"
        $concat = $this->unirConcatenaciones($codigo);
        if ($concat !== $codigo) {
            $this->buscar($concat, 'concatenado', $codigo);
            $this->buscar($this->desofuscar($concat), 'concatenado', $codigo);
            $this->buscarTelefonos($concat, 'concatenado', $concat);
            $this->recolectarTelefonos($concat);
        }

        // 14b. Arrays unidos con join(""): ["info","@","web",".com"].join("")
        $this->desdeJoin($codigo);

        // 13. String.fromCharCode(...) y arrays de códigos
        $this->desdeCharCode($codigo);

        // 12. Escapes \x40 @ \u{40} y escapes CSS \0040
        $escapado = $this->decodificarEscapes($codigo);
        if ($escapado !== $codigo) {
            $this->buscar($escapado, 'escapes', $codigo);
            $this->buscar($this->desofuscar($escapado), 'escapes', $codigo);
            $this->buscarTelefonos($escapado, 'escapes', $escapado);
            $this->recolectarTelefonos($escapado);
        }

        // 15. base64 / atob
        $this->desdeBase64($codigo);

        // 11. Ofuscaciones textuales dentro del código.
        $this->buscar($this->desofuscar($codigo), 'ofuscado', $codigo);

        // Rutas de API que conviene revisar después.
        if (Ajustes::activo('analizar_json', true)) {
            $this->recolectarEndpoints($codigo, $url);
        }
    }

    // ==========================================================================
    //  PASADAS UNIVERSALES
    // ==========================================================================

    private function pasadasUniversales(string $contenido): void
    {
        $largo = strlen($contenido);

        // 9. Entidades HTML (&#64; &#x40; y entidades de cada letra).
        if (str_contains($contenido, '&#') || str_contains($contenido, '&amp;')) {
            $dec = html_entity_decode($contenido, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $dec = html_entity_decode($dec, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // doble codificación
            $this->buscar($this->limpiarInvisibles($dec), 'entidades', $contenido);
            $this->buscar($this->desofuscar($dec), 'entidades', $contenido);
            $this->buscarTelefonos($this->limpiarInvisibles($dec), 'entidades', $contenido);
        }

        // 10. URL-encoding: %40 y %2E (también %2540).
        if (preg_match('~%[0-9A-Fa-f]{2}~', $contenido) === 1) {
            $url1 = rawurldecode(str_replace('%25', '%', $contenido));
            $this->buscar($url1, 'urlencode', $contenido);
            $this->buscarTelefonos($url1, 'urlencode', $contenido);
            $this->recolectarTelefonos($url1);
        }

        // 17. Texto invertido (truco CSS direction:rtl / unicode-bidi).
        if ($largo <= self::LIMITE_PASADAS_CARAS && str_contains($contenido, '@')) {
            $invertido = strrev($contenido);
            $this->buscar($invertido, 'invertido', $invertido);
        }

        // 16. ROT13 (el simbolo @ y los digitos no cambian, la búsqueda es fiable).
        if ($largo <= self::LIMITE_PASADAS_CARAS && str_contains($contenido, '@')) {
            $this->buscar(str_rot13($contenido), 'rot13', $contenido);
        }

        // 11. Ofuscaciones sobre el documento entero (por si están en atributos).
        $this->buscar($this->desofuscar($contenido), 'ofuscado', $contenido);
    }

    // ==========================================================================
    //  Técnicas CONCRETAS
    // ==========================================================================

    /** 1. Extrae el destinatario y los parámetros cc/bcc de un enlace mailto. */
    private function desdeMailto(string $bruto): void
    {
        $bruto = html_entity_decode($bruto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $bruto = rawurldecode($bruto);

        [$destinos, $consulta] = array_pad(explode('?', $bruto, 2), 2, '');
        foreach (preg_split('~[,;]~', $destinos) ?: [] as $correo) {
            $this->anotar(trim($correo), 'mailto', $bruto);
        }
        if ($consulta !== '') {
            parse_str(str_replace('&amp;', '&', $consulta), $params);
            foreach (['cc', 'bcc', 'to', 'CC', 'BCC'] as $clave) {
                if (empty($params[$clave])) { continue; }
                foreach (preg_split('~[,;]~', (string) $params[$clave]) ?: [] as $correo) {
                    $this->anotar(trim($correo), 'mailto-cc', $bruto);
                }
            }
            // Algun correo puede ir dentro del asunto o del cuerpo.
            $this->buscar($consulta, 'mailto-cc', $consulta);
        }
    }

    /** 2. Descifra los correos protegidos por Cloudflare Scrape Shield. */
    private function desdeCloudflare(string $html): void
    {
        // a) Atributo data-cfemail="a1b2c3..."
        if (preg_match_all('~data-cfemail\s*=\s*["\']([0-9a-fA-F]{6,})["\']~', $html, $m)) {
            foreach ($m[1] as $hex) {
                $correo = self::descifrarCfemail($hex);
                if ($correo !== '') { $this->anotar($correo, 'cloudflare', $html); }
            }
        }
        // b) Enlace /cdn-cgi/l/email-protection#a1b2c3...
        if (preg_match_all('~/cdn-cgi/l/email-protection[#\~]([0-9a-fA-F]{6,})~', $html, $m2)) {
            foreach ($m2[1] as $hex) {
                $correo = self::descifrarCfemail($hex);
                if ($correo !== '') { $this->anotar($correo, 'cloudflare', $html); }
            }
        }
    }

    /**
     * Algoritmo oficial de Cloudflare: el primer byte es la clave y el resto se
     * descifra con un XOR byte a byte.
     */
    public static function descifrarCfemail(string $hex): string
    {
        $hex = strtolower(preg_replace('~[^0-9a-f]~i', '', $hex) ?? '');
        if (strlen($hex) < 4 || strlen($hex) % 2 !== 0) { return ''; }

        $clave = (int) hexdec(substr($hex, 0, 2));
        $salida = '';
        for ($i = 2, $n = strlen($hex); $i < $n; $i += 2) {
            $salida .= chr(((int) hexdec(substr($hex, $i, 2))) ^ $clave);
        }
        return str_contains($salida, '@') ? $salida : '';
    }

    /** 3. Recorre los atributos donde los sitios suelen esconder el correo. */
    private function desdeAtributos(string $html): void
    {
        $re = '~\b(data-[a-z0-9_\-]+|value|content|title|alt|placeholder|aria-label|data-email|data-mail|data-correo)\s*=\s*'
            . '(?:"([^"]*)"|\'([^\']*)\')~i';
        if (!preg_match_all($re, $html, $m, PREG_SET_ORDER)) { return; }

        foreach ($m as $par) {
            $valor = $par[2] !== '' ? $par[2] : ($par[3] ?? '');
            if ($valor === '' || strlen($valor) > 2000) { continue; }
            // Solo merece la pena si hay algo que pueda ser un correo... o si el
            // valor entero tiene pinta de bloque base64 (los hay sin relleno "=").
            if (!preg_match('~[@]|&#|%40|\[at\]|\(at\)|arroba|=~i', $valor)
                && !preg_match('~^[A-Za-z0-9+/_\-]{16,}$~', trim($valor))) { continue; }

            $this->buscar($valor, 'atributo', $valor);
            $dec = $this->decodificarTodo($valor);
            if ($dec !== $valor) { $this->buscar($dec, 'atributo', $valor); }
            $this->buscar($this->desofuscar($dec), 'atributo', $valor);
            // Algunos temas guardan el correo en base64 dentro de data-*.
            $this->desdeBase64($valor, 'atributo-base64');
        }
    }

    /**
     * 14. Une los literales de texto concatenados en el código.
     * "info" + "@" + "web.com"  =>  "info@web.com"
     * '+' + '502' + '44445555'  =>  '+50244445555'
     * Se repite hasta que no quedan concatenaciones (máximo 8 vueltas).
     */
    private function unirConcatenaciones(string $codigo): string
    {
        if (!str_contains($codigo, '+') && !str_contains($codigo, '.')) { return $codigo; }

        $re = '~(["\'])((?:[^"\'\\\\\n]|\\\\.)*)\1\s*[+.]\s*(["\'])((?:[^"\'\\\\\n]|\\\\.)*)\3~';
        $anterior = '';
        $actual   = $codigo;
        for ($i = 0; $i < 8 && $actual !== $anterior; $i++) {
            $anterior = $actual;
            $nuevo = preg_replace_callback($re, static function (array $m): string {
                return $m[1] . $m[2] . $m[4] . $m[1];
            }, $actual);
            if ($nuevo === null) { break; }
            $actual = $nuevo;
        }
        return $actual;
    }

    /**
     * 14b. Reconstruye correos partidos en un array y unidos con join("").
     * Cubre ["info","@","web",".com"].join("") y la variante .reverse().join("").
     */
    private function desdeJoin(string $codigo): void
    {
        if (stripos($codigo, 'join') === false) { return; }

        $re = '~\[\s*((?:["\'][^"\']*["\']\s*,\s*){1,40}["\'][^"\']*["\'])\s*\]\s*(\.\s*reverse\s*\(\s*\)\s*)?\.\s*join\s*\(\s*["\']([^"\']*)["\']\s*\)~i';
        if (!preg_match_all($re, $codigo, $m, PREG_SET_ORDER)) { return; }

        foreach ($m as $bloque) {
            if (!preg_match_all('~["\']([^"\']*)["\']~', $bloque[1], $partes)) { continue; }
            $trozos = $partes[1];
            if (($bloque[2] ?? '') !== '') { $trozos = array_reverse($trozos); }
            $unido = implode($bloque[3] ?? '', $trozos);
            if ($unido !== '') {
                $this->buscar($unido, 'concatenado', $unido);
                $this->buscar($this->desofuscar($unido), 'concatenado', $unido);
            }
        }
    }

    /** 13. String.fromCharCode(105,110,...) y arrays de códigos de carácter. */
    private function desdeCharCode(string $codigo): void
    {
        if (stripos($codigo, 'fromCharCode') !== false
            && preg_match_all('~fromCharCode\s*\(\s*([0-9,\s]+)\)~i', $codigo, $m)) {
            foreach ($m[1] as $lista) {
                $this->buscar($this->numerosATexto($lista), 'charcode', $codigo);
            }

            // Muy habitual: String.fromCharCode(...) + "@dominio.com". Se sustituye
            // cada llamada por su texto ya descifrado, entre comillas, y se deja que
            // el unificador de concatenaciones arme el correo completo.
            $sustituido = (string) preg_replace_callback(
                '~(?:String\s*\.\s*)?fromCharCode\s*\(\s*([0-9,\s]+)\)~i',
                fn(array $c): string => '"' . $this->numerosATexto($c[1]) . '"',
                $codigo
            );
            if ($sustituido !== $codigo) {
                $unido = $this->unirConcatenaciones($sustituido);
                $this->buscar($unido, 'charcode', $codigo);
                $this->buscar($this->desofuscar($unido), 'charcode', $codigo);
                $this->buscarTelefonos($unido, 'charcode', $unido);
            }
        }
        // Arrays largos de números seguidos de charCodeAt/join: [105,110,102,111,64,...]
        if (stripos($codigo, 'charCode') !== false || stripos($codigo, 'String.fromCode') !== false) {
            if (preg_match_all('~\[\s*((?:\d{2,3}\s*,\s*){5,}\d{2,3})\s*\]~', $codigo, $m2)) {
                foreach ($m2[1] as $lista) {
                    $this->buscar($this->numerosATexto($lista), 'charcode', $codigo);
                }
            }
        }
    }

    /** Convierte "105,110,102,111" en el texto correspondiente. */
    private function numerosATexto(string $lista): string
    {
        $salida = '';
        foreach (preg_split('~\s*,\s*~', trim($lista)) ?: [] as $n) {
            if ($n === '' || !ctype_digit($n)) { continue; }
            $v = (int) $n;
            if ($v >= 32 && $v <= 126) { $salida .= chr($v); }
        }
        return $salida;
    }

    /** 15. Decodifica cadenas base64 (incluido atob("...")). */
    private function desdeBase64(string $texto, string $etiqueta = 'base64'): void
    {
        if (strlen($texto) > self::LIMITE_PASADAS_CARAS) { return; }

        $candidatos = [];
        // atob("...") es siempre sospechoso: se decodifica aunque sea corto.
        if (preg_match_all('~atob\s*\(\s*["\']([A-Za-z0-9+/=_\-]{8,})["\']~i', $texto, $m)) {
            foreach ($m[1] as $c) { $candidatos[] = $c; }
        }
        if (preg_match_all('~base64[,"\']\s*([A-Za-z0-9+/=]{16,})~i', $texto, $m2)) {
            foreach ($m2[1] as $c) { $candidatos[] = $c; }
        }
        // Bloques base64 sueltos (por ejemplo en data-* o en variables JS).
        if (preg_match_all('~["\']([A-Za-z0-9+/]{16,400}={0,2})["\']~', $texto, $m3)) {
            foreach ($m3[1] as $c) { $candidatos[] = $c; }
        }
        // El valor completo también puede ser base64 (atributos data-* sin comillas).
        $solo = trim($texto);
        if (preg_match('~^[A-Za-z0-9+/_\-]{12,400}={0,2}$~', $solo)) { $candidatos[] = $solo; }

        $vistos = [];
        foreach ($candidatos as $c) {
            if (isset($vistos[$c])) { continue; }
            $vistos[$c] = true;
            if (count($vistos) > 400) { break; }              // tope de seguridad

            $normal = strtr($c, '-_', '+/');                   // base64url
            $plano  = @base64_decode($normal, true);
            if ($plano === false || $plano === '' || strlen($plano) < 6) { continue; }
            // Nos interesa si contiene un correo, un WhatsApp o un teléfono.
            if (!str_contains($plano, '@') && !str_contains($plano, '%40')
                && !preg_match('~wa\.me|whatsapp|tel:|\+\d{7,}~i', $plano)) {
                continue;
            }
            // Descarta binarios.
            if (preg_match('~[\x00-\x08\x0E-\x1F]~', $plano)) { continue; }

            $this->buscar($plano, $etiqueta, $plano);
            $this->buscar(rawurldecode($plano), $etiqueta, $plano);
            $this->buscarTelefonos($plano, $etiqueta, $plano);
            $this->recolectarTelefonos($plano);
        }
    }

    // ==========================================================================
    //  DECODIFICADORES
    // ==========================================================================

    /** Aplica entidades HTML + URL-decode + limpieza de invisibles. */
    private function decodificarTodo(string $texto): string
    {
        $t = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_contains($t, '%')) { $t = rawurldecode($t); }
        return $this->limpiarInvisibles($t);
    }

    /** 12. Decodifica escapes \x40, @, \u{40} y escapes CSS \0040. */
    private function decodificarEscapes(string $texto): string
    {
        if (!str_contains($texto, '\\')) { return $texto; }

        $t = preg_replace_callback('~\\\\x([0-9a-fA-F]{2})~', static fn($m) => chr((int) hexdec($m[1])), $texto) ?? $texto;
        $t = preg_replace_callback('~\\\\u\{([0-9a-fA-F]{1,6})\}~', static function ($m) {
            $cp = (int) hexdec($m[1]);
            return $cp > 0 && $cp < 0x110000 ? (function_exists('mb_chr') ? (string) mb_chr($cp, 'UTF-8') : '') : '';
        }, $t) ?? $t;
        $t = preg_replace_callback('~\\\\u([0-9a-fA-F]{4})~', static function ($m) {
            $cp = (int) hexdec($m[1]);
            return function_exists('mb_chr') ? (string) mb_chr($cp, 'UTF-8') : '';
        }, $t) ?? $t;
        // Escapes CSS: content:"\0040" o \40
        $t = preg_replace_callback('~\\\\([0-9a-fA-F]{2,6})\s?~', static function ($m) {
            $cp = (int) hexdec($m[1]);
            return ($cp >= 32 && $cp <= 126) ? chr($cp) : $m[0];
        }, $t) ?? $t;

        return $t;
    }

    /** 11. Deshace las ofuscaciones escritas: [at], (arroba), " punto ", (.)... */
    public function desofuscar(string $texto): string
    {
        if ($texto === '') { return ''; }

        // Arrobas y puntos de ancho completo usados para despistar.
        // Ojo: las variantes (at) y (dot) las resuelven las expresiones de abajo,
        // que además eliminan los espacios sobrantes alrededor.
        $texto = str_replace(['＠', '．'], ['@', '.'], $texto);

        $reemplazos = [
            // [@] (@) {@}  y  [.] (.) {.}
            '~(?<=[A-Za-z0-9])\s*[\[\(\{]\s*@\s*[\]\)\}]\s*(?=[A-Za-z0-9])~u'                                  => '@',
            '~(?<=[A-Za-z0-9])\s*[\[\(\{]\s*\.\s*[\]\)\}]\s*(?=[A-Za-z0-9])~u'                                 => '.',
            // [at] (at) {at} <at> [arroba] [en]
            '~(?<=[A-Za-z0-9])\s*[\[\(\{<]\s*(?:at|arroba|apestaartje|chiocciola|snabel-?a|klammeraffe)\s*[\]\)\}>]\s*(?=[A-Za-z0-9])~iu' => '@',
            // [dot] (dot) [punto] (punto) [d0t]
            '~(?<=[A-Za-z0-9])\s*[\[\(\{<]\s*(?:dot|punto|d0t|ponto|punkt|puntto)\s*[\]\)\}>]\s*(?=[A-Za-z0-9])~iu' => '.',
            // -at-  _at_  -arroba-
            '~(?<=[A-Za-z0-9])\s*[-_]\s*(?:at|arroba)\s*[-_]\s*(?=[A-Za-z0-9])~iu'                             => '@',
            '~(?<=[A-Za-z0-9])\s*[-_]\s*(?:dot|punto)\s*[-_]\s*(?=[A-Za-z0-9])~iu'                             => '.',
            // " at "  " arroba "  (con espacios obligatorios a ambos lados)
            '~(?<=[A-Za-z0-9])\s+(?:at|arroba)\s+(?=[A-Za-z0-9])~iu'                                            => '@',
            '~(?<=[A-Za-z0-9])\s+(?:dot|punto)\s+(?=[A-Za-z0-9])~iu'                                            => '.',
            // Variantes con la palabra pegada: nombreATdominioDOTcom
            '~(?<=[a-z0-9])\s*\bAT\b\s*(?=[a-z0-9])~u'                                                          => '@',
        ];

        $resultado = $texto;
        foreach ($reemplazos as $patron => $sustituto) {
            $nuevo = preg_replace($patron, $sustituto, $resultado);
            if ($nuevo !== null) { $resultado = $nuevo; }
        }
        return $resultado;
    }

    /** Elimina caracteres invisibles usados para romper las búsquedas. */
    private function limpiarInvisibles(string $texto): string
    {
        return str_replace(
            ["\u{200B}", "\u{200C}", "\u{200D}", "\u{2060}", "\u{FEFF}", "\u{00AD}",
             "\u{202A}", "\u{202B}", "\u{202C}", "\u{202D}", "\u{202E}", "\u{200E}", "\u{200F}"],
            '',
            $texto
        );
    }

    /** Aplana un array (JSON-LD) a texto para poder buscar dentro. */
    private function aplanar(array $datos): string
    {
        $salida = '';
        array_walk_recursive($datos, static function ($valor) use (&$salida) {
            if (is_scalar($valor)) { $salida .= ' ' . $valor; }
        });
        return $salida;
    }

    // ==========================================================================
    //  RECOLECCION DE ENLACES Y RECURSOS
    // ==========================================================================

    /** Enlaces internos para el rastreo profundo. */
    private function recolectarEnlaces(string $html, string $base): void
    {
        $re = '~<(?:a|area|iframe|frame)\b[^>]*?\b(?:href|src)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s">]+))~i';
        if (!preg_match_all($re, $html, $m, PREG_SET_ORDER)) { return; }

        foreach ($m as $par) {
            $bruto = $par[1] !== '' ? $par[1] : (($par[2] ?? '') !== '' ? $par[2] : ($par[3] ?? ''));
            $abs   = Http::urlAbsoluta($bruto, $base);
            if ($abs === '' || !$this->esMismoSitio($abs)) { continue; }
            if ($this->esDescarga($abs)) { continue; }
            // El endpoint de Cloudflare no es una página real del sitio.
            if (str_contains($abs, '/cdn-cgi/')) { continue; }
            $this->enlaces[$this->limpiarUrl($abs)] = true;
        }
    }

    /** Archivos JS, CSS y SVG enlazados desde la página. */
    private function recolectarRecursos(string $html, string $base): void
    {
        if (!Ajustes::activo('analizar_js_css', true)) { return; }

        $patrones = [
            '~<script\b[^>]*\bsrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\')~i',
            '~<link\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\')[^>]*>~i',
            '~<(?:object|embed)\b[^>]*\b(?:data|src)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')~i',
            '~<img\b[^>]*\bsrc\s*=\s*(?:"([^"]*\.svg[^"]*)"|\'([^\']*\.svg[^\']*)\')~i',
        ];
        foreach ($patrones as $re) {
            if (!preg_match_all($re, $html, $m, PREG_SET_ORDER)) { continue; }
            foreach ($m as $par) {
                $bruto = ($par[1] ?? '') !== '' ? $par[1] : ($par[2] ?? '');
                if ($bruto === '') { continue; }
                $abs = Http::urlAbsoluta($bruto, $base);
                if ($abs === '' || !$this->esMismoSitio($abs)) { continue; }
                if (!preg_match('~\.(js|mjs|css|svg|json)(\?|$)~i', $abs)) { continue; }
                $this->recursos[$this->limpiarUrl($abs)] = true;
            }
        }
    }

    /** 19. Endpoints JSON / API detectados dentro del código JavaScript. */
    private function recolectarEndpoints(string $codigo, string $base): void
    {
        $patrones = [
            '~["\']([^"\']*/wp-json/[^"\']*)["\']~i',
            '~fetch\s*\(\s*["\']([^"\']+)["\']~i',
            '~(?:url|endpoint|api|ajaxurl)\s*[:=]\s*["\']([^"\']+\.json[^"\']*)["\']~i',
            '~["\']([^"\'\s]+\.json)(?:\?[^"\']*)?["\']~i',
        ];
        $contador = 0;
        foreach ($patrones as $re) {
            if (!preg_match_all($re, $codigo, $m)) { continue; }
            foreach ($m[1] as $bruto) {
                if ($contador >= 12) { return; }               // no saturamos la cola
                $abs = Http::urlAbsoluta($bruto, $base);
                if ($abs === '' || !$this->esMismoSitio($abs)) { continue; }
                $this->recursos[$this->limpiarUrl($abs)] = true;
                $contador++;
            }
        }
    }

    // ==========================================================================
    //  WHATSAPP Y TELÉFONOS
    // ==========================================================================

    /**
     * Detecta números de WhatsApp y teléfonos en todas sus formas habituales:
     * enlaces wa.me, api.whatsapp.com, whatsapp://, widgets de los plugins más
     * usados, enlaces tel:, JSON-LD, atributos data-* y texto plano.
     */
    private function recolectarTelefonos(string $html): void
    {
        if (!Ajustes::activo('buscar_whatsapp', true)) { return; }

        // ---- 1. wa.me / wa.link ------------------------------------------
        if (preg_match_all('~(?:https?:)?//(?:www\.)?wa\.me/(?:send/?\?phone=)?\+?(\d{7,15})~i', $html, $m)) {
            foreach ($m[1] as $n) { $this->anotarTelefono($n, 'wa.me', true, true, $html); }
        }
        // Enlaces cortos de WhatsApp sin número a la vista.
        if (preg_match_all('~(?:https?:)?//(?:www\.)?(?:wa\.me/message/[A-Z0-9]+|wa\.link/[A-Za-z0-9]+|walink\.co/[A-Za-z0-9]+)~i', $html, $mc)) {
            foreach ($mc[0] as $enlace) { $this->enlacesWa[$this->normalizarEnlace($enlace)] = true; }
        }

        // ---- 2. api.whatsapp.com / web.whatsapp.com / whatsapp:// --------
        // Se admiten separadores dentro del parámetro phone= (espacios, guiones,
        // %20 o paréntesis); hay plantillas que los dejan tal cual en el enlace.
        $patrones = [
            '~(?:api|web|chat)\.whatsapp\.com/send/?\?[^"\'<>]*?phone=%?2?B?\+?([\d][\d\s().\-]{5,20}\d)~i',
            '~whatsapp://send/?\?[^"\'<>]*?phone=%?2?B?\+?([\d][\d\s().\-]{5,20}\d)~i',
            '~["\'](?:https?://)?(?:api|web)\.whatsapp\.com/send\?phone=\+?([\d][\d\s().\-]{5,20}\d)~i',
        ];
        foreach ($patrones as $re) {
            if (preg_match_all($re, $html, $ma)) {
                foreach ($ma[1] as $n) {
                    $limpio = preg_replace('~[^\d]~', '', $n) ?? '';
                    if (strlen($limpio) < 7 || strlen($limpio) > 15) { continue; }
                    $this->anotarTelefono($limpio, 'api-whatsapp', true, true, $html);
                }
            }
        }

        // ---- 3. Invitaciones a grupos ------------------------------------
        if (preg_match_all('~(?:https?:)?//(?:www\.)?chat\.whatsapp\.com/[A-Za-z0-9]{10,}~i', $html, $mg)) {
            foreach ($mg[0] as $enlace) { $this->enlacesWa[$this->normalizarEnlace($enlace)] = true; }
        }

        // ---- 4. Widgets de los plugins más usados -------------------------
        // Joinchat / Creame, WP Social Chat, Elementor, Chaty, Getbutton…
        $widgets = [
            '~"telephone"\s*:\s*"\+?(\d{7,15})"~i',
            '~"number"\s*:\s*"\+?(\d{7,15})"~i',
            '~"phone(?:_number|Number)?"\s*:\s*"\+?(\d{7,15})"~i',
            '~"whatsapp(?:_number)?"\s*:\s*"\+?(\d{7,15})"~i',
            '~data-(?:phone|tel|telefono|number|whatsapp|wa|wanumber|wp-number)\s*=\s*["\']\s*\+?(\d{7,15})\s*["\']~i',
        ];
        foreach ($widgets as $re) {
            if (preg_match_all($re, $html, $mw)) {
                foreach ($mw[1] as $n) { $this->anotarTelefono($n, 'widget-whatsapp', true, true, $html); }
            }
        }

        // ---- 5. Enlaces tel:, callto: y sms: ------------------------------
        // Con comillas el número puede llevar espacios: tel:+56 2 2345 6789
        if (preg_match_all('~(?:href|data-href)\s*=\s*(["\'])\s*(?:tel|callto|sms):([^"\']+)\1~i', $html, $mt)) {
            foreach ($mt[2] as $n) { $this->anotarTelefono(rawurldecode($n), 'tel', false, false, $html); }
        }
        // Variante sin comillas.
        if (preg_match_all('~(?:href|data-href)\s*=\s*(?:tel|callto|sms):([^"\'>\s]+)~i', $html, $mt2)) {
            foreach ($mt2[1] as $n) { $this->anotarTelefono(rawurldecode($n), 'tel', false, false, $html); }
        }

        // ---- 6. JSON-LD y microdatos --------------------------------------
        if (preg_match_all('~itemprop\s*=\s*["\']telephone["\'][^>]*content\s*=\s*["\']([^"\']+)~i', $html, $mi)) {
            foreach ($mi[1] as $n) { $this->anotarTelefono($n, 'meta', false, false, $html); }
        }

        // ---- 7. Atributos con teléfono en texto libre ---------------------
        if (preg_match_all('~data-(phone|tel|telefono|number|whatsapp|wa)\s*=\s*["\']([^"\']{7,30})["\']~i', $html, $md, PREG_SET_ORDER)) {
            foreach ($md as $par) {
                $atributo = strtolower($par[1]);
                $v        = $par[2];
                $esWa     = $atributo === 'whatsapp' || $atributo === 'wa';
                // Los widgets guardan el número ya con prefijo pero sin el "+"
                // y a menudo con espacios ("502 2200 1100"). Si al limpiarlo
                // quedan 10 o más dígitos y no empieza por 0, es internacional.
                $digitos = preg_replace('~\D~', '', $v) ?? '';
                $intl    = strlen($digitos) >= 10 && !str_starts_with($digitos, '0');
                $this->anotarTelefono($v, $esWa ? 'widget-whatsapp' : 'atributo', $intl, $esWa, $v);
            }
        }
    }

    /** Busca números de teléfono dentro de un texto ya decodificado. */
    private function buscarTelefonos(string $texto, string $metodo, string $contexto = ''): void
    {
        if ($texto === '' || !Ajustes::activo('buscar_whatsapp', true)) { return; }
        $contexto = $contexto !== '' ? $contexto : $texto;

        if (preg_match_all(self::RE_TELEFONO_INTL, $texto, $m)) {
            foreach ($m[1] as $n) { $this->anotarTelefono($n, $metodo, false, false, $contexto); }
        }

        // Solo si el administrador configuró un país por defecto tiene sentido
        // mirar los números escritos en formato nacional.
        if (Ajustes::obtener('prefijo_pais', '') !== '' && preg_match_all(self::RE_TELEFONO_NAC, $texto, $m2)) {
            foreach ($m2[1] as $n) { $this->anotarTelefono($n, $metodo, false, false, $contexto); }
        }
    }

    /** Valida y guarda un número con su método de detección. */
    private function anotarTelefono(string $bruto, string $metodo, bool $internacional, bool $esWhatsapp, string $contexto = ''): void
    {
        $bruto = trim($bruto);
        if ($bruto === '') { return; }

        // Los métodos basados en enlaces o widgets son fuentes fiables: no hace
        // falta analizar el contexto para descartar rutas o versiones.
        $fuenteFiable = in_array($metodo, ['wa.me', 'api-whatsapp', 'widget-whatsapp', 'tel', 'atributo', 'meta', 'jsonld'], true);

        $v = Telefono::validar($bruto, $internacional, $contexto, !$fuenteFiable);
        if (!$v['ok']) { return; }

        $clave = $v['e164'];
        if (!isset($this->telefonos[$clave])) {
            $this->telefonos[$clave] = [
                'metodos'  => [],
                'veces'    => 0,
                'whatsapp' => false,
                'datos'    => [
                    'e164'    => $v['e164'],
                    'formato' => $v['formato'],
                    'pais'    => $v['pais'],
                    'iso'     => $v['iso'],
                ],
            ];
        }
        $this->telefonos[$clave]['metodos'][$metodo] = true;
        $this->telefonos[$clave]['veces']++;
        if ($esWhatsapp) { $this->telefonos[$clave]['whatsapp'] = true; }
    }

    /** Perfiles en redes sociales encontrados en la página. */
    private function recolectarRedes(string $html): void
    {
        $re = '~href\s*=\s*["\']((?:https?:)?//(?:www\.)?(?:facebook|instagram|twitter|x|linkedin|youtube|tiktok|pinterest|telegram|t\.me|threads)\.[a-z.]{2,8}[^"\']*)["\']~i';
        if (!preg_match_all($re, $html, $m)) { return; }
        foreach ($m[1] as $red) {
            $red = (string) preg_replace('~^//~', 'https://', $red);
            if (strlen($red) < 250) { $this->redes[$red] = true; }
        }
    }

    /** Normaliza un enlace de WhatsApp para no repetirlo. */
    private function normalizarEnlace(string $enlace): string
    {
        $enlace = (string) preg_replace('~^//~', 'https://', trim($enlace));
        if (!preg_match('~^https?://~i', $enlace)) { $enlace = 'https://' . $enlace; }
        return $enlace;
    }

    /** Añade direcciones descubiertas en un sitemap.xml. */
    public function analizarSitemap(string $xml, string $base): void
    {
        if (preg_match_all('~<loc>\s*([^<\s]+)\s*</loc>~i', $xml, $m)) {
            foreach ($m[1] as $loc) {
                $abs = Http::urlAbsoluta(html_entity_decode($loc, ENT_QUOTES, 'UTF-8'), $base);
                if ($abs === '' || !$this->esMismoSitio($abs)) { continue; }
                if (preg_match('~\.xml(\.gz)?$~i', $abs)) {
                    $this->sitemaps[$abs] = true;
                } elseif (!$this->esDescarga($abs)) {
                    $this->enlaces[$this->limpiarUrl($abs)] = true;
                }
            }
        }
        // Un sitemap también puede llevar correos en etiquetas de contacto.
        $this->buscar($xml, 'sitemap', $xml);
    }

    // ==========================================================================
    //  UTILIDADES INTERNAS
    // ==========================================================================

    /** Ejecuta la expresión principal sobre un texto y anota los hallazgos. */
    private function buscar(string $texto, string $metodo, string $contexto = ''): void
    {
        if ($texto === '' || !str_contains($texto, '@')) { return; }
        if (!preg_match_all(self::RE_CORREO, $texto, $m, PREG_SET_ORDER)) { return; }

        foreach ($m as $hallazgo) {
            $this->anotar($hallazgo[0], $metodo, $contexto !== '' ? $contexto : $texto);
        }
    }

    /** Válida un candidato y lo guarda con su método de detección. */
    private function anotar(string $candidato, string $metodo, string $contexto = ''): void
    {
        if ($candidato === '') { return; }

        // En la pasada ROT13 hay que comprobar el texto original: si al revertir
        // el cifrado aparece un nombre de archivo (logo@2x.png -> ybtb@2k.cat),
        // se trata de un falso positivo.
        if ($metodo === 'rot13' && Validador::pareceArchivoInverso(str_rot13($candidato))) { return; }

        $val = Validador::validar($candidato, $contexto);
        if (!$val['ok']) { return; }

        $correo = $val['correo'];
        if (!isset($this->correos[$correo])) {
            $this->correos[$correo] = ['metodos' => [], 'veces' => 0];
        }
        $this->correos[$correo]['metodos'][$metodo] = true;
        $this->correos[$correo]['veces']++;
    }

    /** ¿La URL pertenece al mismo sitio (mismo dominio o subdominio)? */
    private function esMismoSitio(string $url): bool
    {
        if ($this->hostSitio === '') { return true; }
        $host = cr_host_de_url($url);
        if ($host === '') { return false; }
        $base = $this->hostSitio;
        return $host === $base || str_ends_with($host, '.' . $base) || str_ends_with($base, '.' . $host);
    }

    /** ¿Es un archivo que no merece la pena descargar? */
    private function esDescarga(string $url): bool
    {
        return (bool) preg_match(
            '~\.(jpg|jpeg|png|gif|webp|avif|bmp|ico|tiff|mp4|webm|avi|mov|mkv|mp3|wav|ogg|flac|'
            . 'pdf|docx?|xlsx?|pptx?|zip|rar|7z|tar|gz|exe|dmg|apk|woff2?|ttf|otf|eot)(\?|$)~i',
            $url
        );
    }

    /** Normaliza la URL para no repetir la misma página en la cola. */
    private function limpiarUrl(string $url): string
    {
        $url = (string) preg_replace('~#.*$~', '', $url);
        // Parámetros de campaña que no cambian el contenido.
        $url = (string) preg_replace('~([?&])(utm_[a-z]+|fbclid|gclid|msclkid|ref|_ga)=[^&]*~i', '$1', $url);
        $url = (string) preg_replace('~[?&]+$~', '', $url);
        $url = str_replace('?&', '?', $url);
        return $url;
    }

    /**
     * Devuelve todo lo acumulado por esta instancia (se puede llamar después de
     * analizar varios documentos seguidos: HTML + su JS + su CSS).
     */
    public function resultados(): array
    {
        $this->resolverParesRot13();
        return $this->resultado();
    }

    /**
     * Descarta los correos "fantasma" que genera la pasada ROT13.
     *
     * Si en el texto aparece "meta@colegio.edu.gt", al cifrar la página en
     * ROT13 aparece "zrgn@pbyrtvb.rqh.tg", que también supera la validación
     * porque .tg es un TLD real. De cada pareja se conserva únicamente la
     * forma más creíble como texto humano; con ello desaparecen los falsos
     * positivos en dominios .gt, .tg, .es, .se y similares, y a la vez se
     * sigue recuperando el correo cuando la ofuscación ROT13 es la de verdad.
     */
    private function resolverParesRot13(): void
    {
        foreach (array_keys($this->correos) as $correo) {
            if (!isset($this->correos[$correo])) { continue; }

            $inverso = str_rot13($correo);
            if ($inverso === $correo || !isset($this->correos[$inverso])) { continue; }

            $puntosA = Validador::plausibilidad($correo);
            $puntosB = Validador::plausibilidad($inverso);
            if ($puntosA === $puntosB) { continue; }

            $sobra = $puntosA > $puntosB ? $inverso : $correo;
            unset($this->correos[$sobra]);
        }
    }

    /** Estructura de salida del análisis. */
    private function resultado(): array
    {
        return [
            'correos'   => $this->correos,
            'enlaces'   => array_keys($this->enlaces),
            'recursos'  => array_keys($this->recursos),
            'sitemaps'  => array_keys($this->sitemaps),
            'telefonos' => $this->telefonos,
            'enlaces_wa'=> array_keys($this->enlacesWa),
            'redes'     => array_keys($this->redes),
        ];
    }
}
