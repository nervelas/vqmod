<?php
/**
 * Kaptor - Validación estricta de correos.
 *
 * Objetivo: que no se escape ningun correo real y que no entre ninguna basura.
 *   - Normaliza (minusculas, sin espacios invisibles, sin puntuacion sobrante).
 *   - Descarta nombres de archivo (logo@2x.png), extensiones y TLD inexistentes.
 *   - Descarta correos de ejemplo y de servicios técnicos (example.com, sentry,
 *     wixpress, placeholders en español e inglés...).
 *   - Válida con filter_var y, opcionalmente, comprueba el registro MX del
 *     dominio (con respaldo por DNS-over-HTTPS si el hosting bloquea las
 *     consultas DNS nativas).
 *   - Clasifica el correo (genérico o personal) y calcula una puntuacion de
 *     confianza que ayuda a priorizar los resultados.
 */
declare(strict_types=1);

final class Validador
{
    /** Extensiones de archivo que NUNCA son un TLD válido. */
    private const EXTENSIONES = [
        'png','jpg','jpeg','gif','webp','svg','bmp','ico','tif','tiff','avif','heic',
        'css','js','mjs','cjs','json','xml','html','htm','xhtml','php','phtml','asp','aspx','jsp','cgi',
        'txt','pdf','doc','docx','xls','xlsx','ppt','pptx','csv','rtf','odt','ods',
        'rar','tar','gz','bz2','7z','iso','dmg','exe','msi','apk','deb','rpm',
        'woff','woff2','ttf','otf','eot','swf','psd','eps','indd','sketch','fig',
        'mp3','mp4','m4a','m4v','avi','wmv','webm','ogg','oga','ogv','wav','flac','mkv','mpg','mpeg','3gp',
        'map','scss','sass','less','styl','vue','jsx','tsx','coffee','hbs','twig','ejs','pug',
        'yml','yaml','toml','ini','conf','env','lock','bak','tmp','temp','cache','log','dist','min',
        'webmanifest','htaccess','gitignore','npmignore','sql','db','sqlite','bin','dat','pack',
    ];

    /**
     * Dominios que nunca son un contacto real: reservados para ejemplos,
     * marcadores de posición tipicos y servicios técnicos.
     *
     * Criterio: solo se listan dominios que en la práctica JAMAS pertenecen a
     * una empresa real. Dominios ambiguos como empresa.com o company.com se
     * dejan pasar a proposito para no perder ningun contacto autentico; si
     * molestan, se pueden añadir desde Ajustes > Motor > Dominios excluidos.
     */
    private const DOMINIOS_BASURA = [
        'example.com','example.org','example.net','example.edu','example.co','example.tld','example',
        'ejemplo.com','ejemplo.es','ejemplo.org','dominio.com','dominio.es','domain.com','domain.tld',
        'tudominio.com','sudominio.com','midominio.com','tuempresa.com','miempresa.com','suempresa.com',
        'yourdomain.com','your-domain.com','yourwebsite.com','yoursite.com','your-site.com',
        'mydomain.com','mysite.com','sitename.com','tusitio.com','tuweb.com','suweb.com',
        'placeholder.com','placehold.it','placeholder.net','test.test','localhost.com','localhost.local',
        'no-reply.com','nomail.com','nowhere.com','null.com','void.com','none.com',
        'sentry.io','sentry-cdn.com','sentry.wixpress.com','wixpress.com',
        'schema.org','w3.org','www.w3.org','email.tld','correo.tld',
    ];

    /** Fragmentos que, si aparecen en el dominio, delatan un servicio técnico. */
    private const FRAGMENTOS_BASURA = ['sentry.', '.sentry', 'wixpress', 'example.', 'ejemplo.', '.invalid', '.local', '.localhost', 'nodomain'];

    /** Buzones genéricos de empresa (válidos, pero se marcan como "generico"). */
    private const BUZONES_GENERICOS = [
        'info','informacion','contacto','contact','contactos','hola','hello','hi','mail','correo','email',
        'ventas','sales','comercial','pedidos','orders','compras','purchasing','facturacion','billing','pagos',
        'admin','administracion','administration','soporte','support','ayuda','help','servicio','service',
        'atencion','atencionalcliente','clientes','customers','sac','postventa','rrhh','hr','empleo','jobs',
        'careers','trabajo','curriculum','cv','prensa','press','marketing','publicidad','ads','social',
        'webmaster','postmaster','hostmaster','abuse','noc','root','sysadmin','it','sistemas','tecnico',
        'direccion','gerencia','gerente','oficina','office','secretaria','recepcion','reception','reservas',
        'booking','citas','agenda','eventos','events','newsletter','suscripciones','contabilidad','finanzas',
        'legal','juridico','privacidad','privacy','dpo','gdpr','calidad','logistica','almacen','envios',
        'noreply','no-reply','no_reply','donotreply','nepasrepondre','mailer','mailerdaemon','bounce',
    ];

    /** Dominios de correo temporal o desechable. */
    private const DESECHABLES = [
        'mailinator.com','guerrillamail.com','10minutemail.com','yopmail.com','tempmail.com','temp-mail.org',
        'trashmail.com','sharklasers.com','throwawaymail.com','getnada.com','dispostable.com','maildrop.cc',
        'fakeinbox.com','mailnesia.com','mytemp.email','moakt.com','emailondeck.com','spamgourmet.com',
        'discard.email','mailcatch.com','tempinbox.com','burnermail.io','anonaddy.me','1secmail.com',
    ];

    /** @var array<string,bool> Cache en memoria de comprobaciones MX. */
    private static array $cacheMx = [];
    /** @var string[]|null */
    private static ?array $tlds = null;

    // ------------------------------------------------------------- normalizacion

    /**
     * Limpia y normaliza un correo. Devuelve '' si no se puede aprovechar.
     */
    public static function normalizar(string $correo): string
    {
        // Caracteres invisibles usados para romper los buscadores automaticos.
        $correo = str_replace(
            ["\u{200B}", "\u{200C}", "\u{200D}", "\u{2060}", "\u{FEFF}", "\u{00AD}", "\u{202A}", "\u{202B}", "\u{202C}", "\u{202D}", "\u{202E}"],
            '',
            $correo
        );
        $correo = trim($correo);
        $correo = trim($correo, " \t\n\r\0\x0B\"'<>()[]{}«»,;:!?");
        // Prefijos tipicos arrastrados desde el HTML.
        $correo = preg_replace('~^(?:mailto:|correo:|email:|e-mail:|mail:)~i', '', $correo) ?? $correo;
        $correo = rtrim($correo, '.');

        if ($correo === '' || !str_contains($correo, '@')) { return ''; }

        // Solo la última arroba separa buzon y dominio.
        $pos     = strrpos($correo, '@');
        $buzon   = substr($correo, 0, $pos);
        $dominio = substr($correo, $pos + 1);

        $buzon   = trim($buzon);
        $dominio = strtolower(trim(rtrim($dominio, '.')));
        // Un punto final o labels vacios invalidan el dominio.
        if ($buzon === '' || $dominio === '') { return ''; }

        // El buzon conserva mayusculas según el RFC, pero en la práctica todos los
        // proveedores son insensibles: normalizamos para no duplicar resultados.
        return mb_strtolower($buzon, 'UTF-8') . '@' . $dominio;
    }

    // --------------------------------------------------------------- validación

    /**
     * ¿El correo es real y aprovechable?
     *
     * @param string $contexto Fragmento de HTML alrededor del hallazgo (ayuda a
     *                         detectar nombres de archivo dentro de src/url()).
     * @return array{ok:bool,motivo?:string,correo?:string,buzon?:string,dominio?:string,tld?:string}
     */
    public static function validar(string $correo, string $contexto = ''): array
    {
        $correo = self::normalizar($correo);
        if ($correo === '') { return ['ok' => false, 'motivo' => 'formato']; }

        if (strlen($correo) > 254) { return ['ok' => false, 'motivo' => 'longitud']; }

        [$buzon, $dominio] = explode('@', $correo, 2);

        if ($buzon === '' || strlen($buzon) > 64) { return ['ok' => false, 'motivo' => 'buzon']; }
        if (str_contains($buzon, '..') || $buzon[0] === '.' || str_ends_with($buzon, '.')) {
            return ['ok' => false, 'motivo' => 'buzon'];
        }
        if (!preg_match('/^[a-z0-9!#$%&\'*+\/=?^_`{|}~.\-]+$/', $buzon)) {
            return ['ok' => false, 'motivo' => 'buzon'];
        }

        // Estructura del dominio: al menos dos etiquetas, sin guiones al borde.
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $dominio)) {
            return ['ok' => false, 'motivo' => 'dominio'];
        }
        if (str_contains($dominio, '..') || strlen($dominio) > 190) {
            return ['ok' => false, 'motivo' => 'dominio'];
        }

        $etiquetas = explode('.', $dominio);
        $tld       = end($etiquetas);

        // 1) Extensiones de archivo: logo@2x.png, icono@sprite.svg, script@app.js...
        if (in_array($tld, self::EXTENSIONES, true)) {
            return ['ok' => false, 'motivo' => 'archivo'];
        }
        // 2) Dominios tipo "2x", "3x" o solo números => medida de imagen o versión.
        if (preg_match('/^\d+x?$/', $etiquetas[0]) || preg_match('/^v?\d+(\.\d+)*$/', $etiquetas[0])) {
            return ['ok' => false, 'motivo' => 'archivo'];
        }
        // 3) Contexto de archivo aunque el TLD sea válido (ej. icono@2x.ai dentro de src="...").
        if (self::pareceArchivo($correo, $contexto)) {
            return ['ok' => false, 'motivo' => 'archivo'];
        }
        // 4) TLD inexistente (lista blanca configurable).
        if (Ajustes::activo('tld_estricto', true) && !in_array($tld, self::listaTlds(), true)) {
            return ['ok' => false, 'motivo' => 'tld'];
        }
        // 5) Dominios de ejemplo, de servicios técnicos o excluidos por el admin.
        if (in_array($dominio, self::dominiosExcluidos(), true)) {
            return ['ok' => false, 'motivo' => 'ejemplo'];
        }
        foreach (self::FRAGMENTOS_BASURA as $frag) {
            if (str_contains($dominio, $frag)) { return ['ok' => false, 'motivo' => 'ejemplo']; }
        }
        // 6) Buzones que son claramente marcadores de posición.
        if (in_array($buzon, ['tucorreo', 'tuemail', 'sucorreo', 'youremail', 'your-email', 'yourname', 'nombre.apellido', 'name.surname', 'usuario', 'username', 'ejemplo', 'example'], true)
            && !self::dominioEsReal($dominio)) {
            return ['ok' => false, 'motivo' => 'ejemplo'];
        }
        // 7) Comprobacion final del propio PHP.
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'motivo' => 'formato'];
        }

        return ['ok' => true, 'correo' => $correo, 'buzon' => $buzon, 'dominio' => $dominio, 'tld' => $tld];
    }

    /**
     * Lista completa de dominios excluidos: los del código mas los que el
     * administrador anada en Ajustes > Motor (uno por linea).
     *
     * @return string[]
     */
    public static function dominiosExcluidos(): array
    {
        static $lista = null;
        if ($lista !== null) { return $lista; }

        $lista = self::DOMINIOS_BASURA;
        $extra = trim(Ajustes::obtener('dominios_excluidos', ''));
        if ($extra !== '') {
            foreach (preg_split('~[\r\n,;]+~', $extra) ?: [] as $d) {
                $d = strtolower(trim($d, " \t.@"));
                if ($d !== '') { $lista[] = $d; }
            }
            $lista = array_values(array_unique($lista));
        }
        return $lista;
    }

    /** Heuristica: ¿el hallazgo es en realidad la ruta de un archivo? */
    private static function pareceArchivo(string $correo, string $contexto): bool
    {
        if ($contexto === '') { return false; }
        $pos = stripos($contexto, $correo);
        if ($pos === false) { return false; }

        $antes   = substr($contexto, max(0, $pos - 60), min(60, $pos));
        $despues = substr($contexto, $pos + strlen($correo), 30);

        // Justo antes hay una barra de ruta o un atributo de recurso.
        if (preg_match('~(src|srcset|href|url|data-src|data-srcset|poster|content)\s*=?\s*["\'(][^"\')]{0,80}$~i', $antes)
            && preg_match('~^[^\s"\']*\.(png|jpe?g|gif|webp|svg|css|js|woff2?|mp4|pdf)~i', $despues . 'x')) {
            return true;
        }
        if (preg_match('~[/\\\\]$~', $antes) && preg_match('~^[^\s"\']*\.[a-z]{2,4}["\')\s]~i', $despues)) {
            return true;
        }
        return false;
    }

    /**
     * ¿La cadena corresponde en realidad a un nombre de archivo?
     * Se usa al revertir el cifrado ROT13, donde "logo@2x.png" reaparece
     * disfrazado como un correo aparentemente válido.
     */
    public static function pareceArchivoInverso(string $texto): bool
    {
        $texto = strtolower(trim($texto));
        if (!str_contains($texto, '@') || !str_contains($texto, '.')) { return false; }

        $dominio   = substr($texto, strrpos($texto, '@') + 1);
        $etiquetas = explode('.', $dominio);
        $tld       = end($etiquetas);

        if (in_array($tld, self::EXTENSIONES, true)) { return true; }
        if (preg_match('/^\d+x?$/', $etiquetas[0] ?? '')) { return true; }
        return false;
    }

    /**
     * Puntúa lo "creíble" que resulta un correo como texto humano (0-100).
     *
     * Sirve para deshacer los empates que provoca ROT13: al cifrar
     * "meta@colegio.edu.gt" sale "zrgn@pbyrtvb.rqh.tg", y como .tg es un TLD
     * real ambas formas superan la validación. La versión legible siempre
     * puntúa más alto, así que es la que se conserva.
     */
    public static function plausibilidad(string $correo): int
    {
        $correo = strtolower(trim($correo));
        $arroba = strrpos($correo, '@');
        if ($arroba === false) { return 0; }

        $buzon     = substr($correo, 0, $arroba);
        $dominio   = substr($correo, $arroba + 1);
        $etiquetas = explode('.', $dominio);
        $tld       = array_pop($etiquetas);
        $puntos    = 50;

        // Segundos niveles institucionales muy frecuentes (edu.gt, com.mx, ...).
        $niveles = ['com', 'edu', 'org', 'net', 'gob', 'gov', 'mil', 'ac', 'co', 'info', 'biz'];
        foreach ($etiquetas as $etiqueta) {
            if (in_array($etiqueta, $niveles, true)) { $puntos += 18; break; }
        }

        // TLD de uso masivo frente a TLD marginales.
        if (in_array($tld, ['com', 'net', 'org', 'info', 'edu', 'gov'], true)) { $puntos += 10; }

        // Buzones habituales: son la firma inequívoca de un correo real.
        $buzones = ['info', 'contacto', 'ventas', 'admin', 'hola', 'soporte', 'direccion',
                    'administracion', 'gerencia', 'mail', 'correo', 'colegio', 'secretaria'];
        if (in_array($buzon, $buzones, true)) { $puntos += 15; }

        // Estructura de lenguaje natural: vocales y consonantes alternadas.
        $texto = preg_replace('~[^a-z]~', '', $buzon . implode('', $etiquetas)) ?? '';
        $largo = strlen($texto);
        if ($largo >= 4) {
            $vocales = preg_match_all('~[aeiou]~', $texto);
            $ratio   = $vocales / $largo;
            if ($ratio >= 0.25 && $ratio <= 0.60) { $puntos += 18; }
            elseif ($ratio < 0.12 || $ratio > 0.75) { $puntos -= 22; }

            // Rachas largas de consonantes (pbyrtvb, rqh...) delatan el cifrado.
            if (preg_match('~[bcdfghjklmnpqrstvwxyz]{5,}~', $texto)) { $puntos -= 25; }
            elseif (preg_match('~[bcdfghjklmnpqrstvwxyz]{4}~', $texto)) { $puntos -= 10; }

            // Letras raras en español/inglés apiladas.
            if (preg_match_all('~[qwxzkjvy]~', $texto) > max(1, (int) ($largo / 6))) { $puntos -= 12; }
        }

        return max(0, min(100, $puntos));
    }

    /** ¿El dominio tiene pinta de ser real (no un placeholder)? */
    private static function dominioEsReal(string $dominio): bool
    {
        return !in_array($dominio, self::dominiosExcluidos(), true);
    }

    /** Lista blanca de TLD (se carga una vez). @return string[] */
    public static function listaTlds(): array
    {
        if (self::$tlds === null) {
            $archivo = CR_INCLUDES . '/datos/tlds.php';
            $lista   = is_file($archivo) ? require $archivo : [];
            self::$tlds = is_array($lista) ? $lista : [];
        }
        return self::$tlds;
    }

    // ------------------------------------------------------------- clasificacion

    /** 'generico' (info@, ventas@...) o 'personal' (juan.perez@...). */
    public static function tipo(string $correo): string
    {
        $buzon = explode('@', $correo)[0] ?? '';
        $base  = preg_replace('/[._\-].*$/', '', $buzon) ?? $buzon;
        if (in_array($buzon, self::BUZONES_GENERICOS, true) || in_array($base, self::BUZONES_GENERICOS, true)) {
            return 'generico';
        }
        return 'personal';
    }

    /** ¿Es un buzon que no acepta respuestas (noreply@)? */
    public static function esNoReply(string $correo): bool
    {
        return (bool) preg_match('/^(no[._\-]?reply|donotreply|nepasrepondre|mailer[._\-]?daemon|bounce)/i', $correo);
    }

    /** ¿El dominio es de correo temporal? */
    public static function esDesechable(string $dominio): bool
    {
        return in_array($dominio, self::DESECHABLES, true);
    }

    /**
     * Puntuacion de confianza 5-99: cuanto mas alta, mas probable es que el
     * correo sea real y este operativo.
     */
    public static function confianza(string $correo, string $metodo, ?bool $mx, string $hostSitio = '', string $urlOrigen = ''): int
    {
        $p = 55;
        $dominio = explode('@', $correo)[1] ?? '';

        if (str_contains($metodo, 'mailto'))                      { $p += 25; }
        if (str_contains($metodo, 'texto'))                       { $p += 8; }
        if (str_contains($metodo, 'jsonld') || str_contains($metodo, 'meta')) { $p += 10; }
        if (str_contains($metodo, 'cloudflare'))                  { $p += 18; }
        if (str_contains($metodo, 'ofuscado') || str_contains($metodo, 'entidad')) { $p += 12; }
        if (str_contains($metodo, 'js') || str_contains($metodo, 'css'))          { $p -= 8; }
        if (str_contains($metodo, 'base64') || str_contains($metodo, 'rot13'))    { $p -= 4; }

        // Mismo dominio que el sitio analizado: señal muy fuerte.
        if ($hostSitio !== '' && ($dominio === $hostSitio || str_ends_with($hostSitio, '.' . $dominio) || str_ends_with($dominio, '.' . $hostSitio))) {
            $p += 15;
        }
        // Página de contacto: normalmente es el correo bueno.
        if ($urlOrigen !== '' && preg_match('~(contact|contacto|nosotros|about|equipo|team|staff|directorio|soporte|ayuda|impressum)~i', $urlOrigen)) {
            $p += 6;
        }
        if ($mx === true)  { $p += 12; }
        if ($mx === false) { $p -= 35; }
        if (self::esNoReply($correo))     { $p -= 18; }
        if (self::esDesechable($dominio)) { $p -= 30; }

        return max(5, min(99, $p));
    }

    // --------------------------------------------------------------------- MX

    /**
     * ¿El dominio puede recibir correo? Usa cache en memoria + tabla cr_dns.
     * Si el hosting bloquea las funciones DNS, consulta por DNS-over-HTTPS.
     */
    public static function tieneMx(string $dominio): bool
    {
        $dominio = strtolower($dominio);
        if (isset(self::$cacheMx[$dominio])) { return self::$cacheMx[$dominio]; }

        // Cache persistente (30 días).
        try {
            $fila = BD::fila('SELECT `mx` FROM `cr_dns` WHERE `dominio` = ? AND `revisado` > (NOW() - INTERVAL 30 DAY)', [$dominio]);
            if ($fila) { return self::$cacheMx[$dominio] = ((int) $fila['mx'] === 1); }
        } catch (PDOException) { /* la tabla puede no existir durante la instalación */ }

        // Si el resolutor del servidor funciona, su respuesta es definitiva: no
        // tiene sentido gastar dos peticiones HTTPS extra por cada dominio.
        if (self::dnsOperativo()) {
            $tiene = @checkdnsrr($dominio, 'MX') || @checkdnsrr($dominio, 'A');
        } else {
            $tiene = self::mxPorDoh($dominio);
        }

        self::$cacheMx[$dominio] = $tiene;
        try {
            BD::ejecutar(
                'INSERT INTO `cr_dns` (`dominio`,`mx`,`revisado`) VALUES (?,?,NOW())
                 ON DUPLICATE KEY UPDATE `mx` = VALUES(`mx`), `revisado` = NOW()',
                [$dominio, $tiene ? 1 : 0]
            );
        } catch (PDOException) { /* silencioso */ }

        return $tiene;
    }

    /**
     * ¿Funciona el resolutor DNS del servidor?
     *
     * Se comprueba una sola vez por petición contra dominios que siempre tienen
     * MX. Si falla, el hosting bloquea las consultas DNS y se pasa a resolver
     * por DNS-over-HTTPS.
     */
    private static function dnsOperativo(): bool
    {
        static $ok = null;
        if ($ok !== null) { return $ok; }

        if (!function_exists('checkdnsrr')) { return $ok = false; }
        foreach (['google.com', 'outlook.com'] as $referencia) {
            if (@checkdnsrr($referencia, 'MX')) { return $ok = true; }
        }
        return $ok = false;
    }

    /** Consulta MX mediante DNS-over-HTTPS (respaldo si el servidor bloquea DNS). */
    private static function mxPorDoh(string $dominio): bool
    {
        if (!function_exists('curl_init')) { return false; }
        foreach (['https://cloudflare-dns.com/dns-query', 'https://dns.google/resolve'] as $servicio) {
            $url = $servicio . '?name=' . rawurlencode($dominio) . '&type=MX';
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_HTTPHEADER     => ['Accept: application/dns-json'],
                CURLOPT_USERAGENT      => 'Kaptor/' . CR_VERSION,
            ]);
            if (defined('CR_PROXY') && CR_PROXY !== '') { curl_setopt($ch, CURLOPT_PROXY, CR_PROXY); }
            $resp = curl_exec($ch);
            curl_close($ch);
            if (!is_string($resp) || $resp === '') { continue; }
            $datos = json_decode($resp, true);
            if (is_array($datos) && !empty($datos['Answer'])) { return true; }
            if (is_array($datos) && isset($datos['Status']) && (int) $datos['Status'] === 0) {
                // Respuesta válida sin MX: puede haber registro A (correo en el mismo host).
                return false;
            }
        }
        return false;
    }
}
