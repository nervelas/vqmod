<?php
/**
 * Global helper functions.
 */

/** Escape for HTML output (XSS protection). */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape for use inside an HTML attribute. */
function eattr($value): string
{
    return e($value);
}

/** Build a URL-safe slug. */
function slugify(string $text): string
{
    $text = trim($text);
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($conv !== false) {
            $text = $conv;
        }
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text === '' ? 'item' : $text;
}

/** Base URL of the installation (auto-detected). */
function base_url(string $path = ''): string
{
    $configured = defined('FL_BASE_URL') ? FL_BASE_URL : '';
    if ($configured) {
        return rtrim($configured, '/') . '/' . ltrim($path, '/');
    }
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
              || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Determine the app root relative to the executing script.
    $dir = str_replace('\\', '/', dirname($script));
    // Strip /admin or /install from the root so links resolve to app root.
    $dir = preg_replace('#/(admin|install)(/.*)?$#', '', $dir);
    $dir = rtrim($dir, '/');
    return $scheme . '://' . $host . $dir . '/' . ltrim($path, '/');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Read a request value with a default. */
function input(string $key, $default = null)
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

/** Trimmed string input. */
function str_input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/** Integer input or null. */
function int_input(string $key, ?int $default = null): ?int
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    if ($v === null || $v === '') {
        return $default;
    }
    return (int)$v;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Format date for display, gracefully handling nulls. */
function fmt_date(?string $date, string $fmt = 'd/m/Y'): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($fmt, $ts) : '—';
}

function fmt_time(?string $time): string
{
    if (empty($time)) {
        return '—';
    }
    $ts = strtotime($time);
    return $ts ? date('H:i', $ts) : '—';
}

/** Human-readable match status label. */
function match_status_label(string $status): string
{
    return [
        'pending'      => 'Pendiente',
        'in_progress'  => 'En juego',
        'finished'     => 'Finalizado',
        'suspended'    => 'Suspendido',
        'rescheduled'  => 'Reprogramado',
        'cancelled'    => 'Cancelado',
    ][$status] ?? $status;
}

/** JSON response helper. */
function json_response($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Echo ' selected' when values match (loose). */
function selected($a, $b): string
{
    return (string)$a === (string)$b ? ' selected' : '';
}

/** Echo ' checked' when truthy match. */
function checked($cond): string
{
    return $cond ? ' checked' : '';
}

/** Render <option> tags from id=>label pairs. */
function options(array $pairs, $current = null, ?string $placeholder = null): string
{
    $out = '';
    if ($placeholder !== null) {
        $out .= '<option value="">' . e($placeholder) . '</option>';
    }
    foreach ($pairs as $val => $label) {
        $out .= '<option value="' . e($val) . '"' . selected($val, $current) . '>' . e($label) . '</option>';
    }
    return $out;
}

/**
 * Single-league mode: the platform administers exactly ONE league.
 * Returns the (only) league row, or null if it hasn't been created yet.
 */
function the_league(): ?array
{
    static $loaded = false;
    static $row = null;
    if (!$loaded) {
        try { $row = Database::one("SELECT * FROM leagues ORDER BY id ASC LIMIT 1"); }
        catch (Throwable $e) { $row = null; }
        $loaded = true;
    }
    return $row ?: null;
}

function the_league_id(): ?int
{
    $l = the_league();
    return $l ? (int)$l['id'] : null;
}

/** Player display name. */
function player_name(array $p): string
{
    if (!empty($p['nickname'])) {
        return $p['nickname'];
    }
    return trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
}

/** Team display (short name preferred where compact). */
function team_display(?array $t, bool $short = false): string
{
    if (!$t) {
        return '—';
    }
    if ($short && !empty($t['short_name'])) {
        return $t['short_name'];
    }
    return $t['name'] ?? '—';
}

/** Small logo/avatar element with initials fallback. */
function media_thumb(?string $path, string $fallbackText = '', string $class = 'team-logo', bool $round = false): string
{
    $cls = $class . ($round ? ' avatar-round' : '');
    if ($path) {
        return '<img class="' . e($cls) . '" src="' . e(base_url($path)) . '" alt="">';
    }
    $init = mb_strtoupper(mb_substr(trim($fallbackText), 0, 2));
    return '<span class="' . e($cls) . '">' . e($init) . '</span>';
}
