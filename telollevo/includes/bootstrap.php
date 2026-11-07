<?php
/**
 * Te Lo Llevo - Núcleo: seguridad, almacenamiento, utilidades.
 * Compatible con PHP 7.4+ (sin dependencias, sin base de datos).
 */
if (defined('TLL_BOOT')) { return; }
define('TLL_BOOT', 1);

define('TLL_ROOT', dirname(__DIR__));
define('TLL_DATA', TLL_ROOT . '/data');
define('TLL_UPLOADS', TLL_ROOT . '/uploads');
define('TLL_VERSION', '1.0.0');

if (!defined('TLL_DEPTH')) { define('TLL_DEPTH', 0); } // 0 = raíz, 1 = /admin

// Compatibilidad: algunos hostings no traen la extensión mbstring
if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $enc = null) { return preg_match_all('/./us', (string)$s, $m); }
    function mb_substr($s, $start, $len = null, $enc = null) {
        $a = preg_split('//u', (string)$s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode('', array_slice($a, $start, $len));
    }
    function mb_strtoupper($s, $enc = null) { return strtoupper((string)$s); }
    function mb_internal_encoding($enc = null) { return true; }
}
mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Guatemala');
@ini_set('display_errors', '0');
@ini_set('expose_php', '0');
error_reporting(E_ALL);

/* ------------------------------------------------------------------ */
/*  Utilidades                                                         */
/* ------------------------------------------------------------------ */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
    return false;
}

/** Ruta base del sitio (funciona en la raíz del dominio o en una subcarpeta). */
function base_path(): string {
    static $b = null;
    if ($b !== null) return $b;
    $d = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    for ($i = 0; $i < TLL_DEPTH; $i++) { $d = dirname($d); }
    $d = rtrim($d, '/');
    if ($d === '.' || $d === '\\') $d = '';
    return $b = $d;
}
function url(string $path = ''): string { return base_path() . '/' . ltrim($path, '/'); }
function asset(string $path): string {
    $f = TLL_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($f) ? filemtime($f) : TLL_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}
function site_origin(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) $host = 'localhost';
    return (is_https() ? 'https://' : 'http://') . $host;
}

function client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function security_headers(bool $admin = false): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    $csp = "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; "
         . "font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'";
    header('Content-Security-Policy: ' . $csp);
    if (is_https()) header('Strict-Transport-Security: max-age=31536000');
    if ($admin) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow');
    }
}

/* ------------------------------------------------------------------ */
/*  Almacenamiento (archivos .php con guarda anti-lectura)             */
/* ------------------------------------------------------------------ */
const TLL_GUARD = "<?php http_response_code(403); exit; ?>\n";

function data_init(): void {
    if (!is_dir(TLL_DATA)) { @mkdir(TLL_DATA, 0755, true); }
    $ht = TLL_DATA . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n");
    }
    $ix = TLL_DATA . '/index.html';
    if (!is_file($ix)) { @file_put_contents($ix, ''); }
    if (!is_dir(TLL_UPLOADS)) { @mkdir(TLL_UPLOADS, 0755, true); }
}

function store_path(string $name): string {
    if (!preg_match('/^[a-z0-9_\-]+$/', $name)) { throw new InvalidArgumentException('Nombre inválido'); }
    return TLL_DATA . '/' . $name . '.php';
}

function store_read(string $name, $default = []) {
    $f = store_path($name);
    if (!is_file($f)) return $default;
    $raw = @file_get_contents($f);
    if ($raw === false) return $default;
    if (strpos($raw, TLL_GUARD) === 0) $raw = substr($raw, strlen(TLL_GUARD));
    $d = json_decode($raw, true);
    return ($d === null && trim($raw) !== 'null') ? $default : $d;
}

function store_write(string $name, $data): bool {
    data_init();
    $f = store_path($name);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) return false;
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, TLL_GUARD . $json, LOCK_EX) === false) return false;
    @chmod($tmp, 0644);
    if (!@rename($tmp, $f)) { // Windows: rename no sobrescribe
        $ok = @copy($tmp, $f); @unlink($tmp); return $ok;
    }
    return true;
}

/** Lectura-modificación-escritura con bloqueo (evita perder datos con envíos simultáneos). */
function store_update(string $name, callable $fn, $default = []) {
    data_init();
    $lock = @fopen(TLL_DATA . '/.lock-' . preg_replace('/[^a-z0-9_\-]/', '', $name), 'c');
    if ($lock) { @flock($lock, LOCK_EX); }
    try {
        $new = $fn(store_read($name, $default));
        store_write($name, $new);
        return $new;
    } finally {
        if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
    }
}

/** Secreto de la app (para firmar tokens). */
function app_secret(): string {
    static $s = null;
    if ($s !== null) return $s;
    $d = store_read('secret', []);
    if (empty($d['k']) || strlen($d['k']) < 32) {
        $d = ['k' => bin2hex(random_bytes(32))];
        if (!store_write('secret', $d)) {
            // Sin permisos de escritura: secreto estable derivado del servidor (solo para tokens anti-spam)
            return $s = hash('sha256', TLL_ROOT . '|' . php_uname('n') . '|' . (string)@filemtime(__FILE__));
        }
    }
    return $s = $d['k'];
}

/* ------------------------------------------------------------------ */
/*  Contenido / ajustes                                                */
/* ------------------------------------------------------------------ */
require_once __DIR__ . '/defaults.php';

function deep_merge(array $base, array $over): array {
    foreach ($over as $k => $v) {
        if (is_array($v) && isset($base[$k]) && is_array($base[$k]) && !tll_is_list($v) && !tll_is_list($base[$k])) {
            $base[$k] = deep_merge($base[$k], $v);
        } else {
            $base[$k] = $v;
        }
    }
    return $base;
}
function tll_is_list(array $a): bool {
    if ($a === []) return true;
    return array_keys($a) === range(0, count($a) - 1);
}

function get_content(bool $fresh = false): array {
    static $c = null;
    if ($c !== null && !$fresh) return $c;
    $saved = store_read('content', null);
    $def = default_content();
    $c = is_array($saved) ? deep_merge($def, $saved) : $def;
    return $c;
}
function save_content(array $c): bool { return store_write('content', $c); }

function get_settings(): array {
    $saved = store_read('settings', []);
    return deep_merge(default_settings(), is_array($saved) ? $saved : []);
}
function save_settings(array $s): bool { return store_write('settings', $s); }

/* ------------------------------------------------------------------ */
/*  Texto con formato mínimo y seguro                                  */
/* ------------------------------------------------------------------ */
/** Escapa y convierte *palabra* en resaltado. */
function fmt($s): string {
    $t = e($s);
    $t = preg_replace('/\*([^*\n]{1,80})\*/u', '<mark class="hl">$1</mark>', $t);
    return $t;
}
function fmt_br($s): string { return nl2br(fmt($s), false); }

/** Solo permite http(s), mailto, tel, anclas y rutas relativas. */
function safe_url($u): string {
    $u = trim((string)$u);
    if ($u === '') return '';
    if (preg_match('/^(#|\/(?!\/)|\.\/|\.\.\/)/', $u)) return $u;
    if (preg_match('/^(https?:\/\/|mailto:|tel:)/i', $u)) return $u;
    if (preg_match('/^[A-Za-z0-9_\-\/]+(\.php|\.html)?(\?[A-Za-z0-9_=&%\-]*)?(#[A-Za-z0-9_\-]*)?$/', $u)) return $u;
    return '';
}
/** Resuelve un href del contenido (anclas relativas a la home, rutas con base). */
function href($u): string {
    $u = safe_url($u);
    if ($u === '') return '#';
    if ($u[0] === '#') return $u;
    if ($u[0] === '/' ) return $u;
    if (preg_match('/^(https?:|mailto:|tel:)/i', $u)) return $u;
    return url($u);
}
function is_external(string $u): bool { return (bool)preg_match('/^https?:\/\//i', $u); }

function media_url($p): string {
    $p = trim((string)$p);
    if ($p === '') return '';
    if (preg_match('#^(uploads|assets)/[A-Za-z0-9_\-./]+$#', $p) && strpos($p, '..') === false) {
        $f = TLL_ROOT . '/' . $p;
        $v = is_file($f) ? filemtime($f) : '';
        return url($p) . ($v ? '?v=' . $v : '');
    }
    return '';
}

/* ------------------------------------------------------------------ */
/*  Iconos (Phosphor Icons, MIT) - SVG en línea                        */
/* ------------------------------------------------------------------ */
function icon(string $name, string $class = '', string $style = 'duotone'): string {
    static $cache = [];
    $name = preg_replace('/[^a-z0-9\-]/', '', strtolower($name));
    if ($name === '') return '';
    $key = $style . '/' . $name;
    if (!isset($cache[$key])) {
        $f = TLL_ROOT . '/assets/icons/' . $style . '/' . $name . '.svg';
        if (!is_file($f) && $style === 'duotone') { $f = TLL_ROOT . '/assets/icons/fill/' . $name . '.svg'; }
        $cache[$key] = is_file($f) ? trim(file_get_contents($f)) : '';
    }
    $svg = $cache[$key];
    if ($svg === '') return '';
    return str_replace('<svg ', '<svg class="ic ' . e($class) . '" aria-hidden="true" focusable="false" ', $svg);
}
function icon_exists(string $name): bool {
    $name = preg_replace('/[^a-z0-9\-]/', '', strtolower($name));
    return $name !== '' && (is_file(TLL_ROOT . '/assets/icons/duotone/' . $name . '.svg') || is_file(TLL_ROOT . '/assets/icons/fill/' . $name . '.svg'));
}

/* ------------------------------------------------------------------ */
/*  Tokens firmados (formulario público, sin sesión)                   */
/* ------------------------------------------------------------------ */
function form_token(): string {
    $ts = time();
    $nonce = bin2hex(random_bytes(6));
    $sig = hash_hmac('sha256', $ts . '.' . $nonce, app_secret());
    return $ts . '.' . $nonce . '.' . $sig;
}
function form_token_check(string $tok, int $minAge = 3, int $maxAge = 14400): bool {
    $p = explode('.', $tok);
    if (count($p) !== 3 || !ctype_digit($p[0])) return false;
    $sig = hash_hmac('sha256', $p[0] . '.' . $p[1], app_secret());
    if (!hash_equals($sig, $p[2])) return false;
    $age = time() - (int)$p[0];
    return $age >= $minAge && $age <= $maxAge;
}

/* ------------------------------------------------------------------ */
/*  Límite de intentos (por clave) basado en archivo                   */
/* ------------------------------------------------------------------ */
function rl_load(): array { $d = store_read('ratelimit', []); return is_array($d) ? $d : []; }
function rl_hits(string $key, int $window): int {
    $d = rl_load(); $now = time(); $n = 0;
    foreach (($d[$key] ?? []) as $t) { if ($t > $now - $window) $n++; }
    return $n;
}
function rl_add(string $key, int $window = 3600): void {
    $d = rl_load(); $now = time();
    foreach ($d as $k => $arr) {
        $d[$k] = array_values(array_filter($arr, function ($t) use ($now) { return $t > $now - 86400; }));
        if (!$d[$k]) unset($d[$k]);
    }
    $d[$key][] = $now;
    store_write('ratelimit', $d);
}
function rl_clear(string $key): void {
    $d = rl_load(); if (isset($d[$key])) { unset($d[$key]); store_write('ratelimit', $d); }
}

data_init();
