<?php
declare(strict_types=1);
namespace S5\Services;

use S5\Core\Db;
use S5\Core\Fs;
use S5\Core\Log;
use S5\Core\Settings;

/**
 * Fotos de stock descargadas en línea para rellenar los espacios sin foto del cliente.
 * Cascada: Pexels (con clave) -> Openverse (CC0/dominio público, sin atribución) -> nada (el tema usa arte generado).
 * Tolerante a fallos: cualquier error se registra y se salta; jamás lanza hacia la construcción.
 *
 * Contrato con Manifest (CONTRATO-LUXE §7), guardado en orders.data._stock:
 *   {"assets":[{"file":fileId,"role":"stock","credit":""}],
 *    "slots":{"hero":[fileId],"about":[fileId],"servicios":[fileId|0 alineado con contenido.servicios],"galeria":[fileId]},
 *    "done":{clave:fileId|0}, "used":[...], "down":[proveedores caídos], "n":int}
 */
final class StockImages
{
    public const MAX_PER_ORDER = 24;
    public const MAX_BYTES = 6291456;       // 6 MB por descarga
    public const MIN_W = 1000;
    public const MIN_H = 600;
    public const MAX_SIDE = 1920;
    public const MAX_PIXELS = 30000000;
    public const QUALITY = 82;
    public const TIMEOUT = 12;
    public const SEARCH_TTL = 1209600;      // 14 días
    public const GALLERY = 6;
    public const UA = 'Servicom5min/1.0 (+https://servicom.gt)';

    private const PEXELS = 'https://api.pexels.com/v1/search';
    private const OPENVERSE = 'https://api.openverse.org/v1/images/';
    private const HOSTS = ['images.pexels.com', 'upload.wikimedia.org', 'live.staticflickr.com'];

    /** Motivos de rechazo/errores de la última ejecución (pruebas y diagnóstico). */
    public static array $log = [];

    /** Consultas por rubro (en inglés): hero (2), about (1), galería (6) y término genérico para servicios. */
    private const Q = [
        'abogado' => ['hero' => ['lawyer office interior', 'law books courthouse'], 'about' => ['lawyers team meeting'],
            'gal' => ['legal documents signing', 'law library', 'business handshake', 'courtroom', 'lawyer consultation client', 'scales of justice'], 'svc' => 'law legal'],
        'clinica' => ['hero' => ['modern medical clinic', 'doctor patient consultation'], 'about' => ['medical team doctors'],
            'gal' => ['dentist clinic', 'stethoscope doctor', 'medical laboratory', 'nurse patient care', 'clinic reception', 'health checkup'], 'svc' => 'medical health'],
        'taller' => ['hero' => ['auto repair shop mechanic', 'car engine repair'], 'about' => ['mechanic workshop team'],
            'gal' => ['car tire change', 'engine maintenance', 'oil change car', 'mechanic tools', 'car diagnostics', 'auto garage'], 'svc' => 'car repair'],
        'ropa' => ['hero' => ['fashion boutique clothing store', 'clothes rack boutique'], 'about' => ['fashion designer atelier'],
            'gal' => ['clothing store display', 'dress fashion', 'tailor sewing', 'shoes fashion', 'fashion accessories', 'woman fashion shopping'], 'svc' => 'fashion clothing'],
        'restaurante' => ['hero' => ['elegant restaurant interior', 'chef plating gourmet dish'], 'about' => ['restaurant kitchen chefs'],
            'gal' => ['gourmet food dish', 'restaurant table dinner', 'fresh ingredients cooking', 'coffee dessert', 'wine glass restaurant', 'grill barbecue food'], 'svc' => 'food restaurant'],
        'transporte' => ['hero' => ['cargo truck highway', 'logistics warehouse trucks'], 'about' => ['truck drivers fleet'],
            'gal' => ['delivery truck', 'cargo containers port', 'warehouse logistics', 'freight trucks road', 'courier delivery', 'fleet of trucks'], 'svc' => 'transport logistics'],
        'contabilidad' => ['hero' => ['accounting office calculator', 'financial documents desk'], 'about' => ['accountants team office'],
            'gal' => ['tax documents calculator', 'financial charts laptop', 'business meeting finance', 'invoice receipts', 'audit paperwork', 'office computer spreadsheet'], 'svc' => 'accounting finance'],
        'importaciones' => ['hero' => ['container ship port cargo', 'import export shipping containers'], 'about' => ['logistics team warehouse'],
            'gal' => ['shipping containers port', 'cargo airplane', 'warehouse inventory', 'customs cargo', 'forklift warehouse', 'global trade map'], 'svc' => 'import export shipping'],
        'otro' => ['hero' => ['modern business office', 'professional team meeting'], 'about' => ['small business team'],
            'gal' => ['business handshake', 'office workspace', 'customer service', 'teamwork meeting', 'laptop office desk', 'modern city business'], 'svc' => 'business service'],
    ];

    /** Palabras clave de servicios en español (sin acentos) -> términos en inglés. */
    private const DICT = [
        'laboral' => 'labor employment law', 'civil' => 'civil law', 'penal' => 'criminal law', 'mercantil' => 'commercial business law',
        'familia' => 'family law', 'notar' => 'notary documents', 'inmobil' => 'real estate', 'tribut' => 'tax', 'impuest' => 'tax',
        'contab' => 'accounting', 'audit' => 'audit', 'planilla' => 'payroll', 'nomina' => 'payroll', 'dental' => 'dentist', 'odont' => 'dentist',
        'pediatr' => 'pediatrician child', 'ginecol' => 'gynecology', 'laborator' => 'medical laboratory', 'cardio' => 'cardiology',
        'dermat' => 'dermatology', 'nutric' => 'nutrition', 'psico' => 'psychology therapy', 'freno' => 'car brakes', 'aceite' => 'oil change',
        'llanta' => 'tire', 'neumat' => 'tire', 'motor' => 'car engine', 'alinea' => 'wheel alignment', 'balanceo' => 'wheel alignment',
        'pintura' => 'car paint', 'electric' => 'electrician', 'diagnost' => 'diagnostics', 'camis' => 'shirt fashion', 'vestid' => 'dress fashion',
        'zapato' => 'shoes', 'calzado' => 'shoes', 'ropa' => 'clothing', 'confecc' => 'sewing tailor', 'costur' => 'sewing tailor',
        'pastel' => 'cake bakery', 'reposter' => 'cake bakery', 'cafe' => 'coffee', 'pizza' => 'pizza', 'parrill' => 'grill barbecue',
        'marisco' => 'seafood', 'desayun' => 'breakfast', 'catering' => 'catering event', 'evento' => 'event catering', 'flete' => 'cargo truck',
        'carga' => 'cargo truck', 'mudanza' => 'moving truck', 'encomienda' => 'courier parcel', 'paqueter' => 'courier parcel',
        'mensajer' => 'courier delivery', 'import' => 'import export cargo', 'aduan' => 'customs', 'contenedor' => 'shipping container',
        'maritim' => 'ocean freight ship', 'aere' => 'air freight cargo', 'consult' => 'consulting', 'asesor' => 'consulting advice',
    ];

    // ------------------------------------------------------------------ configuración
    private static function env(string $k): string
    {
        $v = getenv($k);
        return is_string($v) ? trim($v) : '';
    }

    private static function pexelsUrl(): string
    {
        return self::env('S5_STOCK_PEXELS_URL') ?: self::PEXELS;
    }

    private static function openverseUrl(): string
    {
        return self::env('S5_STOCK_OPENVERSE_URL') ?: self::OPENVERSE;
    }

    /** Hosts de pruebas (solo loopback) habilitados por las variables de entorno. */
    private static function testHosts(): array
    {
        $out = [];
        foreach (['S5_STOCK_PEXELS_URL', 'S5_STOCK_OPENVERSE_URL'] as $k) {
            $h = strtolower((string) parse_url(self::env($k), PHP_URL_HOST));
            if (in_array($h, ['127.0.0.1', 'localhost', '[::1]'], true)) {
                $out[] = $h;
            }
        }
        return $out;
    }

    public static function cacheDir(): string
    {
        $d = self::env('S5_STOCK_CACHE');
        if ($d === '') {
            $d = S5_ROOT . '/storage/cache/stock';
        }
        Fs::mkdir($d, 0750);
        return $d;
    }

    public static function enabled(): bool
    {
        return Settings::bool('stock_online', true);
    }

    public static function hasPexelsKey(): bool
    {
        return trim((string) Settings::get('pexels_key', '')) !== '';
    }

    // ------------------------------------------------------------------ plan
    private static function deaccent(string $s): string
    {
        $s = mb_strtolower($s);
        return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    private static function serviceQuery(string $name, array $q): array
    {
        $n = self::deaccent($name);
        $terms = [];
        foreach (self::DICT as $k => $v) {
            if (str_contains($n, $k)) {
                $terms[$v] = true;
                if (count($terms) >= 2) {
                    break;
                }
            }
        }
        $name = trim((string) preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $name));
        $name = trim((string) preg_replace('/\s+/', ' ', $name));
        if ($terms) {
            $primary = implode(' ', array_keys($terms));
            $alt = $q['svc'];
        } else {
            $primary = trim(mb_substr($name, 0, 40) . ' ' . $q['svc']);
            $alt = $q['svc'];
        }
        return [$primary, $alt];
    }

    /**
     * Espacios por rellenar. Cada ítem: key, slot (hero|about|servicios|galeria), idx, query, alt.
     * Las fotos del cliente/presentación tienen prioridad: solo se piden los espacios que faltan.
     */
    public static function plan(array $brief, int $orderId): array
    {
        $rubro = (string) ($brief['negocio']['rubro'] ?? 'otro');
        $q = self::Q[$rubro] ?? self::Q['otro'];
        $c = $brief['contenido'] ?? [];
        $items = [];
        if (empty($c['banner'])) {
            foreach ($q['hero'] as $i => $qq) {
                $items[] = ['key' => 'hero:' . $i, 'slot' => 'hero', 'idx' => $i, 'query' => $qq, 'alt' => $q['hero'][0] === $qq ? $q['svc'] : $q['hero'][0]];
            }
        }
        $items[] = ['key' => 'about:0', 'slot' => 'about', 'idx' => 0, 'query' => $q['about'][0], 'alt' => $q['svc']];
        foreach (($c['servicios'] ?? []) as $i => $s) {
            if (!is_array($s) || empty($s['nombre']) || !empty($s['foto'])) {
                continue;
            }
            [$p, $a] = self::serviceQuery((string) $s['nombre'], $q);
            $items[] = ['key' => 'svc:' . $i, 'slot' => 'servicios', 'idx' => (int) $i, 'query' => $p, 'alt' => $a];
        }
        $have = count(array_filter((array) ($c['galeria'] ?? [])));
        for ($i = 0; $i < max(0, self::GALLERY - $have); $i++) {
            $items[] = ['key' => 'gal:' . $i, 'slot' => 'galeria', 'idx' => $i, 'query' => $q['gal'][$i % count($q['gal'])], 'alt' => $q['svc']];
        }
        return $items;
    }

    // ------------------------------------------------------------------ HTTP
    private static function urlAllowed(string $url, string $firstHost = ''): ?string
    {
        $p = parse_url($url);
        if (!$p || empty($p['host']) || empty($p['scheme']) || isset($p['user']) || isset($p['pass'])) {
            return 'URL no válida';
        }
        $host = strtolower($p['host']);
        $scheme = strtolower($p['scheme']);
        $test = in_array($host, self::testHosts(), true);
        if ($scheme !== 'https' && !($test && $scheme === 'http')) {
            return 'esquema no permitido';
        }
        if (isset($p['port']) && !$test && (int) $p['port'] !== 443) {
            return 'puerto no permitido';
        }
        if ($firstHost !== '' && $host !== $firstHost) {
            return 'redirección a otro host (' . $host . ')';
        }
        if ($test) {
            return null;
        }
        $ok = in_array($host, self::HOSTS, true) || in_array($host, ['api.pexels.com', 'api.openverse.org'], true)
            || str_ends_with($host, '.staticflickr.com') || str_ends_with($host, '.wikimedia.org');
        if (!$ok) {
            return 'host no permitido (' . $host . ')';
        }
        return null;
    }

    /** GET con tope de bytes, sin seguir redirecciones fuera del host original. @return array{status:int,body:string,error:string} */
    private static function http(string $url, array $headers = [], int $max = 1048576, bool $strictHost = false): array
    {
        if (!function_exists('curl_init')) {
            return ['status' => 0, 'body' => '', 'error' => 'sin cURL'];
        }
        if ($e = self::urlAllowed($url)) {
            return ['status' => 0, 'body' => '', 'error' => $e];
        }
        $first = strtolower((string) parse_url($url, PHP_URL_HOST));
        for ($hop = 0; $hop < 4; $hop++) {
            $body = '';
            $loc = '';
            $over = false;
            $ch = curl_init($url);
            if (in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), self::testHosts(), true)) {
                curl_setopt($ch, CURLOPT_PROXY, '');   // pruebas locales: nunca por proxy
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_USERAGENT => self::UA, CURLOPT_HTTPHEADER => $headers,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$loc) {
                    if (stripos($line, 'location:') === 0) {
                        $loc = trim(substr($line, 9));
                    }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => function ($c, $d) use (&$body, &$over, $max) {
                    if (strlen($body) + strlen($d) > $max) {
                        $over = true;
                        return 0;
                    }
                    $body .= $d;
                    return strlen($d);
                },
            ]);
            $ok = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($over) {
                return ['status' => $status, 'body' => '', 'error' => 'supera el tamaño máximo'];
            }
            if ($ok === false && $status === 0) {
                return ['status' => 0, 'body' => '', 'error' => 'red: ' . $err];
            }
            if ($status >= 300 && $status < 400 && $loc !== '') {
                $next = self::resolve($url, $loc);
                if ($e = self::urlAllowed($next, $first)) {
                    return ['status' => $status, 'body' => '', 'error' => $e];
                }
                $url = $next;
                continue;
            }
            return ['status' => $status, 'body' => $body, 'error' => ''];
        }
        return ['status' => 0, 'body' => '', 'error' => 'demasiadas redirecciones'];
    }

    private static function resolve(string $base, string $loc): string
    {
        if (preg_match('#^https?://#i', $loc)) {
            return $loc;
        }
        $p = parse_url($base);
        $root = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($loc, '//')) {
            return $p['scheme'] . ':' . $loc;
        }
        if (str_starts_with($loc, '/')) {
            return $root . $loc;
        }
        return $root . rtrim(dirname($p['path'] ?? '/'), '/') . '/' . $loc;
    }

    // ------------------------------------------------------------------ proveedores
    /** @return array{ok:bool,cands:array,error:string} ok=false => proveedor caído */
    private static function search(string $provider, string $query): array
    {
        $cf = self::cacheDir() . '/q-' . sha1($provider . '|' . $query) . '.json';
        if (is_file($cf) && time() - (int) filemtime($cf) < self::SEARCH_TTL) {
            $j = json_decode((string) @file_get_contents($cf), true);
            if (is_array($j)) {
                return ['ok' => true, 'cands' => $j, 'error' => ''];
            }
        }
        if ($provider === 'pexels') {
            $url = self::pexelsUrl() . (str_contains(self::pexelsUrl(), '?') ? '&' : '?') . http_build_query(['query' => $query, 'per_page' => 15, 'orientation' => 'landscape']);
            $r = self::http($url, ['Authorization: ' . trim((string) Settings::get('pexels_key', '')), 'Accept: application/json']);
        } else {
            $url = self::openverseUrl() . (str_contains(self::openverseUrl(), '?') ? '&' : '?') . http_build_query([
                'q' => $query, 'license' => 'cc0,pdm', 'category' => 'photograph', 'extension' => 'jpg', 'size' => 'large',
                'aspect_ratio' => 'wide', 'page_size' => 15, 'mature' => 'false',
            ]);
            $r = self::http($url, ['Accept: application/json']);
        }
        if ($r['error'] !== '' || $r['status'] !== 200) {
            return ['ok' => false, 'cands' => [], 'error' => $provider . ': HTTP ' . $r['status'] . ' ' . $r['error']];
        }
        $j = json_decode($r['body'], true);
        if (!is_array($j)) {
            return ['ok' => false, 'cands' => [], 'error' => $provider . ': respuesta no válida'];
        }
        $c = [];
        if ($provider === 'pexels') {
            foreach ((array) ($j['photos'] ?? []) as $p) {
                if (!is_array($p)) {
                    continue;
                }
                foreach (['large2x', 'large'] as $sz) {
                    $u = (string) ($p['src'][$sz] ?? '');
                    if ($u !== '') {
                        $c[] = ['id' => 'p:' . ($p['id'] ?? sha1($u)), 'url' => $u,
                            'credit' => mb_substr('Foto de ' . trim((string) ($p['photographer'] ?? '')) . ' en Pexels ' . (string) ($p['url'] ?? ''), 0, 250)];
                        break;
                    }
                }
            }
        } else {
            foreach ((array) ($j['results'] ?? []) as $p) {
                if (!is_array($p) || empty($p['url'])) {
                    continue;
                }
                if ((int) ($p['width'] ?? 0) > 0 && ((int) $p['width'] < self::MIN_W || (int) ($p['height'] ?? 0) < self::MIN_H)) {
                    continue;
                }
                $c[] = ['id' => 'o:' . ($p['id'] ?? sha1((string) $p['url'])), 'url' => (string) $p['url'],
                    'credit' => mb_substr(trim((string) ($p['creator'] ?? 'Anónimo')) . ' - ' . (string) ($p['foreign_landing_url'] ?? '') . ' (' . strtoupper((string) ($p['license'] ?? 'cc0')) . ', Openverse)', 0, 250)];
            }
        }
        @file_put_contents($cf, json_encode($c, JSON_UNESCAPED_SLASHES), LOCK_EX);
        return ['ok' => true, 'cands' => $c, 'error' => ''];
    }

    /** Descarga y valida; devuelve la ruta del JPEG ya procesado en caché o null (y deja el motivo en self::$log). */
    private static function download(string $url): ?string
    {
        $cf = self::cacheDir() . '/' . sha1($url) . '.jpg';
        if (is_file($cf) && filesize($cf) > 0) {
            return $cf;
        }
        $r = self::http($url, ['Accept: image/jpeg,image/png,image/webp'], self::MAX_BYTES);
        if ($r['error'] !== '' || $r['status'] !== 200) {
            self::$log[] = 'descarga rechazada: ' . ($r['error'] !== '' ? $r['error'] : 'HTTP ' . $r['status']);
            return null;
        }
        $jpeg = self::process($r['body']);
        if ($jpeg === null) {
            return null;
        }
        if (@file_put_contents($cf, $jpeg, LOCK_EX) === false) {
            self::$log[] = 'no se pudo escribir la caché';
            return null;
        }
        return $cf;
    }

    /** Valida (mime real, tamaño mínimo) y recodifica a JPEG sin EXIF. */
    private static function process(string $bin): ?string
    {
        if ($bin === '' || strlen($bin) > self::MAX_BYTES) {
            self::$log[] = 'tamaño no válido';
            return null;
        }
        $info = @getimagesizefromstring($bin);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            self::$log[] = 'no es una imagen jpeg/png/webp';
            return null;
        }
        [$w, $h] = $info;
        if ($w < self::MIN_W || $h < self::MIN_H) {
            self::$log[] = 'imagen demasiado pequeña (' . $w . 'x' . $h . ')';
            return null;
        }
        if ($w * $h > self::MAX_PIXELS) {
            self::$log[] = 'imagen demasiado grande en píxeles';
            return null;
        }
        $im = @imagecreatefromstring($bin);
        if (!$im) {
            self::$log[] = 'imagen corrupta';
            return null;
        }
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $ex = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bin));
            $rot = [3 => 180, 6 => -90, 8 => 90][(int) ($ex['Orientation'] ?? 1)] ?? 0;
            if ($rot !== 0 && ($r = imagerotate($im, $rot, 0))) {
                $im = $r;
            }
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $s = min(1.0, self::MAX_SIDE / max($w, $h));
        $nw = max(1, (int) round($w * $s));
        $nh = max(1, (int) round($h * $s));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imageinterlace($dst, true);
        ob_start();
        $ok = @imagejpeg($dst, null, self::QUALITY);
        $out = (string) ob_get_clean();
        if (!$ok || $out === '') {
            self::$log[] = 'no se pudo recodificar';
            return null;
        }
        return $out;
    }

    // ------------------------------------------------------------------ orquestación
    public static function normalize($s): array
    {
        $s = is_array($s) ? $s : [];
        return [
            'assets' => array_values(array_filter((array) ($s['assets'] ?? []), 'is_array')),
            'slots' => ['hero' => [], 'about' => [], 'servicios' => [], 'galeria' => []] + (array) ($s['slots'] ?? []),
            'done' => (array) ($s['done'] ?? []),
            'used' => array_values((array) ($s['used'] ?? [])),
            'down' => array_values((array) ($s['down'] ?? [])),
            'n' => (int) ($s['n'] ?? 0),
        ];
    }

    /** Quita de _stock los archivos que ya no existen (para que se vuelvan a pedir). */
    public static function prune(array $stock, callable $exists): array
    {
        $stock = self::normalize($stock);
        $gone = [];
        foreach ($stock['assets'] as $k => $a) {
            if (!$exists((int) ($a['file'] ?? 0))) {
                $gone[(int) $a['file']] = true;
                unset($stock['assets'][$k]);
            }
        }
        if ($gone) {
            $stock['assets'] = array_values($stock['assets']);
            foreach ($stock['slots'] as $sl => $ids) {
                $stock['slots'][$sl] = array_map(fn($i) => isset($gone[(int) $i]) ? 0 : $i, (array) $ids);
            }
            foreach ($stock['done'] as $k => $fid) {
                if (isset($gone[(int) $fid])) {
                    unset($stock['done'][$k]);
                }
            }
        }
        return $stock;
    }

    /**
     * Rellena los espacios pendientes dentro de $budget segundos. Reanudable: el estado va en el valor devuelto.
     * @param callable|null $store fn(string $jpegPath, array $meta): int fileId  (por defecto registra en `files`)
     * @return array{done:bool,stock:array,added:int}
     */
    public static function fetch(array $brief, int $orderId, float $budget = 18.0, ?callable $store = null): array
    {
        self::$log = [];
        $stock = self::normalize($brief['_stock'] ?? []);
        $added = 0;
        try {
            $deadline = microtime(true) + $budget;
            $items = self::plan($brief, $orderId);
            $store = $store ?: function (string $path, array $meta) use ($orderId): int {
                return self::register($orderId, $path, $meta);
            };
            foreach ($items as $it) {
                if (isset($stock['done'][$it['key']])) {
                    continue;
                }
                if (microtime(true) >= $deadline) {
                    return ['done' => false, 'stock' => $stock, 'added' => $added];
                }
                $fid = 0;
                if (count($stock['assets']) < self::MAX_PER_ORDER) {
                    try {
                        $fid = self::one($it, $stock, $store, $deadline);
                    } catch (\Throwable $e) {
                        Log::error('StockImages: ' . $e->getMessage());
                    }
                }
                $stock['done'][$it['key']] = $fid;
                if ($fid > 0) {
                    $added++;
                }
            }
        } catch (\Throwable $e) {
            Log::error('StockImages fetch: ' . $e->getMessage());
        }
        // Reconstruye slots (servicios alineados con el índice del servicio; 0 = sin foto)
        $slots = ['hero' => [], 'about' => [], 'servicios' => [], 'galeria' => []];
        $nsvc = count($brief['contenido']['servicios'] ?? []);
        for ($i = 0; $i < $nsvc; $i++) {
            $slots['servicios'][$i] = (int) ($stock['done']['svc:' . $i] ?? 0);
        }
        foreach ($stock['done'] as $k => $fid) {
            if ((int) $fid <= 0) {
                continue;
            }
            if (str_starts_with($k, 'hero:')) { $slots['hero'][] = (int) $fid; }
            elseif (str_starts_with($k, 'about:')) { $slots['about'][] = (int) $fid; }
            elseif (str_starts_with($k, 'gal:')) { $slots['galeria'][] = (int) $fid; }
        }
        if (!array_filter($slots['servicios'])) {
            $slots['servicios'] = [];
        }
        $stock['slots'] = $slots;
        $stock['down'] = [];
        return ['done' => true, 'stock' => $stock, 'added' => $added];
    }

    /** Busca y descarga una foto para el ítem. Devuelve fileId o 0. */
    private static function one(array $it, array &$stock, callable $store, float $deadline): int
    {
        $providers = [];
        if (self::hasPexelsKey() && !in_array('pexels', $stock['down'], true)) {
            $providers[] = 'pexels';
        }
        if (!in_array('openverse', $stock['down'], true)) {
            $providers[] = 'openverse';
        }
        foreach ($providers as $prov) {
            $cands = [];
            foreach (array_unique([$it['query'], $it['alt']]) as $qq) {
                $r = self::search($prov, $qq);
                if (!$r['ok']) {
                    $stock['down'][] = $prov;
                    self::$log[] = $r['error'];
                    Log::error('StockImages: proveedor caído, ' . $r['error']);
                    continue 2;
                }
                $cands = $r['cands'];
                if ($cands) {
                    break;
                }
            }
            $tries = 0;
            foreach ($cands as $c) {
                if ($tries >= 4 || microtime(true) >= $deadline + 12) {
                    break;
                }
                $uid = 'u:' . sha1($c['url']);
                if (in_array($c['id'], $stock['used'], true) || in_array($uid, $stock['used'], true)) {
                    continue;
                }
                $tries++;
                $path = self::download($c['url']);
                if ($path === null) {
                    $stock['used'][] = $uid;   // no se vuelve a intentar
                    continue;
                }
                $hash = 'h:' . sha1_file($path);
                if (in_array($hash, $stock['used'], true)) {
                    continue;
                }
                $info = @getimagesize($path);
                $fid = $store($path, ['slot' => $it['slot'], 'idx' => $it['idx'], 'w' => (int) ($info[0] ?? 0), 'h' => (int) ($info[1] ?? 0), 'credit' => $c['credit']]);
                if ($fid > 0) {
                    array_push($stock['used'], $c['id'], $uid, $hash);
                    $stock['assets'][] = ['file' => $fid, 'role' => 'stock', 'credit' => $c['credit']];
                    $stock['n']++;
                    return $fid;
                }
            }
        }
        return 0;
    }

    /** Registra el JPEG como archivo del pedido (tipo 'stock'; se copia al sitio igual que las fotos del cliente). */
    public static function register(int $orderId, string $path, array $meta): int
    {
        $dir = Files::dir($orderId);
        Fs::mkdir($dir, 0750);
        $stored = Fs::randomName('jpg');
        if (!@copy($path, $dir . '/' . $stored)) {
            return 0;
        }
        @chmod($dir . '/' . $stored, 0640);
        $id = Db::insert('files', [
            'order_id' => $orderId, 'kind' => 'stock', 'orig_name' => 'stock-' . $meta['slot'] . '-' . $meta['idx'] . '.jpg',
            'stored' => $stored, 'mime' => 'image/jpeg', 'size' => (int) filesize($dir . '/' . $stored),
            'w' => $meta['w'] ?: null, 'h' => $meta['h'] ?: null, 'source' => 'stock', 'created_at' => Db::now(),
        ]);
        $f = Db::one('SELECT * FROM ' . Db::t('files') . ' WHERE id=?', [$id]);
        if ($f) {
            Files::makeThumb($f);
        }
        return $id;
    }

    // ------------------------------------------------------------------ diagnóstico
    /** Búsqueda real mínima (solo desde el botón del panel). @return array{ok:bool,msg:string} */
    public static function test(): array
    {
        self::$log = [];
        $parts = [];
        $any = false;
        foreach (self::hasPexelsKey() ? ['pexels', 'openverse'] : ['openverse'] as $prov) {
            $name = $prov === 'pexels' ? 'Pexels' : 'Openverse';
            $r = self::search($prov, 'lawyer office');
            if (!$r['ok']) {
                $parts[] = $name . ': falló (' . $r['error'] . ')';
                continue;
            }
            if (!$r['cands']) {
                $parts[] = $name . ': sin resultados';
                continue;
            }
            $path = null;
            foreach (array_slice($r['cands'], 0, 3) as $c) {
                if (($path = self::download($c['url'])) !== null) {
                    break;
                }
            }
            if ($path) {
                $i = getimagesize($path);
                $parts[] = $name . ': OK, ' . count($r['cands']) . ' resultados, descarga de ' . $i[0] . 'x' . $i[1];
                $any = true;
            } else {
                $parts[] = $name . ': ' . count($r['cands']) . ' resultados pero no se pudo descargar (' . implode('; ', array_slice(self::$log, -2)) . ')';
            }
        }
        return ['ok' => $any, 'msg' => implode(' | ', $parts)];
    }
}
