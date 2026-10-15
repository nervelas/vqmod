<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

define('GX_CORE', GX_ROOT . '/_core');
define('GX_DATA', GX_CORE . '/data');
const GX_VERSION = '1.0.0';

error_reporting(E_ALL);
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_log', GX_DATA . '/php-error.log');
@ini_set('allow_url_include', '0');
mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Guatemala');
umask(0022);

/* ---------- utilidades básicas ---------- */

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function gx_now(): int
{
    return time();
}

function gx_rand_hex(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

function gx_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** IP del cliente. Solo se confía en una cabecera de proxy si el propietario la configuró. */
function gx_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $hdr = (string)(gx_config()['proxy_header'] ?? '');
    if ($hdr === 'CF-Connecting-IP' && isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = (string)$_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif ($hdr === 'X-Forwarded-For' && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function gx_json_read(string $path, $default = null)
{
    if (!is_file($path)) {
        return $default;
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $default;
    }
    $d = json_decode($raw, true);
    return $d === null ? $default : $d;
}

function gx_json_write(string $path, $data): bool
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    return $json !== false && file_put_contents($path, $json, LOCK_EX) !== false;
}

function gx_config(?array $set = null): array
{
    static $cfg = null;
    if ($set !== null) {
        $cfg = $set;
        return $cfg;
    }
    if ($cfg === null) {
        $cfg = gx_json_read(GX_DATA . '/config.json', []);
        if (!is_array($cfg)) {
            $cfg = [];
        }
    }
    return $cfg;
}

function gx_config_save(array $cfg): void
{
    gx_json_write(GX_DATA . '/config.json', $cfg);
    gx_config($cfg);
}

/* ---------- cabeceras de seguridad ---------- */

function gx_security_headers(bool $noindex = false): void
{
    if (headers_sent()) {
        return;
    }
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; media-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    if (gx_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    if ($noindex) {
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

/** Respuesta de error genérica: nunca revela rutas ni versiones. */
function gx_fail(int $code, string $msg = 'Error'): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/plain; charset=UTF-8');
        gx_security_headers(true);
    }
    echo $msg;
    exit;
}

set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    error_log('[PHP ' . $no . '] ' . $str . ' @ ' . basename($file) . ':' . $line);
    return true;
});

set_exception_handler(static function (Throwable $t): void {
    error_log('[EXC] ' . get_class($t) . ': ' . $t->getMessage() . ' @ ' . basename($t->getFile()) . ':' . $t->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        gx_security_headers(true);
        $wantsJson = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
        header('Content-Type: ' . ($wantsJson ? 'application/json; charset=UTF-8' : 'text/plain; charset=UTF-8'));
        echo $wantsJson ? '{"ok":false,"msg":"Error interno. Intente más tarde."}' : 'Error interno.';
    }
    exit;
});

/* ---------- carga de módulos (rutas estáticas) ---------- */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sanitize.php';
