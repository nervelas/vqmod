<?php
/**
 * Global helper functions.
 */

declare(strict_types=1);

/** Escape output for safe HTML. */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape for use inside a HTML attribute (same as e, kept for readability). */
function eattr($value): string
{
    return e($value);
}

/** Base URL of the site (no trailing slash). Auto-detects if not configured. */
function base_url(string $path = ''): string
{
    $cfg = $GLOBALS['config'] ?? [];
    $base = $cfg['app']['base_url'] ?? '';
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Directory the app lives in (supports subfolder installs).
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        // Strip known sub-entry dirs so base points at web root.
        $script = preg_replace('#/(admin|install)(/.*)?$#', '', $script);
        $script = rtrim($script, '/');
        $base = $scheme . '://' . $host . $script;
    }
    $base = rtrim($base, '/');
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

/** URL to an uploaded/media asset. */
function asset_url(string $path): string
{
    if ($path === '') { return ''; }
    if (preg_match('#^https?://#i', $path)) { return $path; }
    return base_url($path);
}

/** Redirect helper. */
function redirect(string $url): void
{
    if (!preg_match('#^https?://#i', $url)) {
        $url = base_url($url);
    }
    header('Location: ' . $url);
    exit;
}

/** Read a POST field trimmed. */
function post(string $key, $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : (string)$v;
}

/** Read a GET field trimmed. */
function get(string $key, $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : (string)$v;
}

/** Slugify a string. */
function slugify(string $text): string
{
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'item';
}

/** Validate an email. */
function is_email(string $email): bool
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

/** Flash message helpers (stored in session). */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}
function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Build a WhatsApp click-to-chat link. */
function whatsapp_link(string $number, string $message = ''): string
{
    $num = preg_replace('/[^0-9]/', '', $number);
    $url = 'https://wa.me/' . $num;
    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }
    return $url;
}

/** Human readable file size. */
function human_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $n = (float)$bytes;
    while ($n >= 1024 && $i < count($units) - 1) { $n /= 1024; $i++; }
    return round($n, $n < 10 && $i > 0 ? 1 : 0) . ' ' . $units[$i];
}

/** Current request path relative to base. */
function current_slug(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $base = parse_url(base_url(), PHP_URL_PATH) ?? '';
    if ($base && strpos($uri, $base) === 0) {
        $uri = substr($uri, strlen($base));
    }
    return trim($uri, '/');
}
