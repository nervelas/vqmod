<?php
declare(strict_types=1);

/** Escapa para salida HTML. Se usa en el 100% de las vistas. */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Escapa para atributo de URL. */
function eu(mixed $value): string
{
    return rawurlencode((string) $value);
}

/** Escapa para contexto JS (dentro de <script>). */
function ejs(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: 'null';
}

/** URL absoluta dentro de la aplicación, respetando subcarpetas. */
function url(string $path = '/'): string
{
    $base = \App\Core\App::basePath();
    if ($path === '' || $path === '/') {
        return $base === '' ? '/' : $base . '/';
    }
    return $base . '/' . ltrim($path, '/');
}

/** URL absoluta con esquema y host (para correos, PDF, sitemap). */
function absUrl(string $path = '/'): string
{
    return rtrim(\App\Core\App::origin(), '/') . url($path);
}

/** URL de un archivo estático con cache-busting por mtime. */
function asset(string $path): string
{
    $rel = '/assets/' . ltrim($path, '/');
    $abs = BASE_PATH . $rel;
    $v = is_file($abs) ? substr((string) filemtime($abs), -6) : '1';
    return url($rel) . '?v=' . $v;
}

/** URL pública de un archivo subido a /storage/uploads. */
function upload(?string $path): string
{
    if (!$path) {
        return url('/assets/img/plates/generico.svg');
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    // "assets:img/plates/x.svg" apunta a un archivo del tema, no a una subida.
    if (str_starts_with($path, 'assets:')) {
        return url('/assets/' . ltrim(substr($path, 7), '/'));
    }
    return url('/media/' . ltrim($path, '/'));
}

function config(string $key, mixed $default = null): mixed
{
    return \App\Core\Config::get($key, $default);
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(\App\Core\Csrf::token()) . '">';
}

function csrf_token(): string
{
    return \App\Core\Csrf::token();
}

function old(string $key, mixed $default = ''): mixed
{
    return \App\Core\Flash::old($key, $default);
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#i', $path) ? $path : url($path)), true, 302);
    exit;
}

function jsonOut(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Formato de moneda de la empresa: Q1,250.00 */
function money(float|int|string|null $n, string $symbol = 'Q', int $dec = 2): string
{
    return $symbol . number_format((float) $n, $dec, '.', ',');
}

function qty(float|int|string|null $n): string
{
    $f = (float) $n;
    return rtrim(rtrim(number_format($f, 2, '.', ','), '0'), '.') ?: '0';
}

function slugify(string $text, string $sep = '-'): string
{
    $from = ['á','à','ä','â','ã','å','é','è','ë','ê','í','ì','ï','î','ó','ò','ö','ô','õ','ú','ù','ü','û','ñ','ç','ý',
             'Á','À','Ä','Â','Ã','Å','É','È','Ë','Ê','Í','Ì','Ï','Î','Ó','Ò','Ö','Ô','Õ','Ú','Ù','Ü','Û','Ñ','Ç','Ý','º','ª','°','"','\''];
    $to   = ['a','a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','n','c','y',
             'a','a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','n','c','y','','','','',''];
    $text = str_replace($from, $to, $text);
    $text = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', $sep, $text));
    return trim($text, $sep) ?: 'x';
}

function nowSql(): string
{
    return date('Y-m-d H:i:s');
}

/** "hace 3 días" legible en español. */
function humanDays(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $d = (int) floor((time() - strtotime($datetime)) / 86400);
    if ($d <= 0) {
        return 'hoy';
    }
    if ($d === 1) {
        return 'ayer';
    }
    return "hace {$d} días";
}

function daysSince(?string $datetime): int
{
    if (!$datetime) {
        return 999;
    }
    return (int) floor((time() - strtotime($datetime)) / 86400);
}

function fechaLarga(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $t = strtotime($datetime);
    return (int) date('j', $t) . ' de ' . $meses[(int) date('n', $t)] . ' de ' . date('Y', $t);
}

function fechaCorta(?string $datetime): string
{
    return $datetime ? date('d/m/Y', strtotime($datetime)) : '—';
}

function fechaHora(?string $datetime): string
{
    return $datetime ? date('d/m/Y H:i', strtotime($datetime)) : '—';
}

function arrGet(?array $a, string $k, mixed $d = null): mixed
{
    return is_array($a) && array_key_exists($k, $a) ? $a[$k] : $d;
}

function str_limit(?string $s, int $len = 120): string
{
    $s = trim((string) $s);
    return mb_strlen($s) <= $len ? $s : mb_substr($s, 0, $len - 1) . '…';
}

/** Envía una variable desde la vista al layout (se renderiza después). */
function page(string $key, mixed $value): void
{
    \App\Core\View::share($key, $value);
}
