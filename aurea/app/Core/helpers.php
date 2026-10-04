<?php
declare(strict_types=1);

use Aurea\Core\Lang;
use Aurea\Core\Settings;
use Aurea\Core\Csrf;

/** Ruta base si el sistema vive en una subcarpeta ('' si está en la raíz). */
function base_path(): string
{
    static $b = null;
    if ($b === null) {
        $s = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $d = str_replace('\\', '/', dirname($s));
        // Cuando se ejecuta desde /instalar/index.php la base es el directorio padre
        if (substr($d, -9) === '/instalar') {
            $d = substr($d, 0, -9);
        }
        $b = ($d === '/' || $d === '.') ? '' : rtrim($d, '/');
    }
    return $b;
}

function config(string $key, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $f = AUREA_ROOT . '/config/config.php';
        $cfg = is_file($f) ? (array)(include $f) : [];
    }
    return $cfg[$key] ?? $default;
}

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Traducción/texto editable: __('Texto', args...) -> lang/es.php o el propio texto. */
function __(string $text, ...$args): string
{
    $t = Lang::get($text);
    return $args ? vsprintf($t, $args) : $t;
}

function url(string $path = '/'): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function abs_url(string $path = '/'): string
{
    $override = '';
    try { $override = (string)\Aurea\Core\Settings::get('base_url', ''); } catch (\Throwable $e) { /* sin BD (instalador) */ }
    if ($override === '') { $override = (string)config('base_url', ''); }
    if ($override !== '') {
        return rtrim($override, '/') . '/' . ltrim($path, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (config('trust_proxy', false) && strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
        $host = 'localhost';
    }
    return ($https ? 'https' : 'http') . '://' . $host . url($path);
}

function asset(string $path): string
{
    $f = AUREA_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($f) ? (string)filemtime($f) : AUREA_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function setting(string $key, $default = '')
{
    return Settings::get($key, $default);
}

/** Terminología editable: term('client') => "Paciente". */
function term(string $key): string
{
    $defaults = ['client' => 'Cliente', 'clients' => 'Clientes', 'appt' => 'Cita', 'appts' => 'Citas',
        'professional' => 'Profesional', 'professionals' => 'Profesionales'];
    return (string)Settings::get('term_' . $key, $defaults[$key] ?? $key);
}

function money($n): string
{
    $sym = Settings::get('currency', 'GTQ') === 'USD' ? 'US$' : 'Q';
    return $sym . number_format((float)$n, 2, '.', ',');
}

function fdate(?string $d): string
{
    if (!$d || $d === '0000-00-00') { return ''; }
    $t = strtotime(substr($d, 0, 10));
    return $t ? date('d/m/Y', $t) : '';
}

function ftime(?string $d): string
{
    if (!$d) { return ''; }
    $t = strtotime($d);
    return $t ? date('H:i', $t) : '';
}

function fdatetime(?string $d): string
{
    return $d ? fdate($d) . ' ' . ftime($d) : '';
}

function csrf_field(string $scope = 'admin'): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token($scope)) . '">';
}

function old(array $src, string $key, $default = '')
{
    return $src[$key] ?? $default;
}

function sel($a, $b): string { return (string)$a === (string)$b ? ' selected' : ''; }
function chk($v): string { return $v ? ' checked' : ''; }

/** Iconos de línea (estilo Feather, MIT) en SVG inline. */
function icon(string $name): string
{
    static $p = [
        'home' => '<path d="M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'wallet' => '<path d="M20 12V8H6a2 2 0 0 1 0-4h12v4M4 6v12a2 2 0 0 0 2 2h14v-4"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/>',
        'star' => '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
        'chart' => '<path d="M18 20V10M12 20V4M6 20v-6"/>',
        'tag' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1"/>',
        'folder' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        'form' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
        'gift' => '<path d="M20 12v10H4V12M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>',
        'pin' => '<path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'sun' => '<circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/>',
        'off' => '<circle cx="12" cy="12" r="10"/><path d="M4.9 4.9l14.2 14.2"/>',
        'mail' => '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M22 6l-10 7L2 6"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'share' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($p[$name] ?? '') . '</svg>';
}

/** Inicial para avatares: omite títulos (Dr., Dra., Lic., Ing., Arq., Lcda.…). */
function name_initial(string $name): string
{
    foreach (preg_split('/\s+/', trim($name)) ?: [] as $w) {
        if ($w !== '' && !preg_match('/^(dr|dra|lic|lcda|ing|arq|msc|mtro|mtra|psic|nut|not|abg|t\.?s)\.?$/iu', $w)) { return mb_strtoupper(mb_substr($w, 0, 1)); }
    }
    return mb_strtoupper(mb_substr($name, 0, 1));
}
