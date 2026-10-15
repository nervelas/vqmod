<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

/** Conexión PDO a SQLite. El nombre del archivo es aleatorio y está dentro de _core/data (bloqueado). */
function gx_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $cfg = gx_config();
    $file = (string)($cfg['db_file'] ?? '');
    if (!preg_match('/^[a-f0-9]{20,64}\.sqlite$/', $file)) {
        throw new RuntimeException('db not configured');
    }
    $pdo = new PDO('sqlite:' . GX_DATA . '/' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function gx_schema(PDO $db): void
{
    $db->exec('CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS pages (slug TEXT PRIMARY KEY, sort INTEGER NOT NULL DEFAULT 0, data TEXT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS media (
        id INTEGER PRIMARY KEY AUTOINCREMENT, file TEXT NOT NULL, thumb TEXT NOT NULL,
        w INTEGER NOT NULL, h INTEGER NOT NULL, tw INTEGER NOT NULL, th INTEGER NOT NULL,
        alt TEXT NOT NULL DEFAULT \'\', name TEXT NOT NULL DEFAULT \'\', created INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT, created INTEGER NOT NULL, name TEXT NOT NULL, email TEXT NOT NULL,
        phone TEXT NOT NULL, service TEXT NOT NULL DEFAULT \'\', place TEXT NOT NULL DEFAULT \'\',
        message TEXT NOT NULL, ip TEXT NOT NULL, mail_status TEXT NOT NULL DEFAULT \'pendiente\',
        is_read INTEGER NOT NULL DEFAULT 0)');
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, pass_hash TEXT NOT NULL,
        totp_secret TEXT NOT NULL DEFAULT \'\', totp_enabled INTEGER NOT NULL DEFAULT 0,
        must_change INTEGER NOT NULL DEFAULT 1, created INTEGER NOT NULL, last_login INTEGER NOT NULL DEFAULT 0,
        totp_last INTEGER NOT NULL DEFAULT 0)');
    $db->exec('CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT NOT NULL, username TEXT NOT NULL, ts INTEGER NOT NULL, ok INTEGER NOT NULL)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_la ON login_attempts (ts)');
    $db->exec('CREATE TABLE IF NOT EXISTS rate_limit (kind TEXT NOT NULL, k TEXT NOT NULL, ts INTEGER NOT NULL)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_rl ON rate_limit (kind, k, ts)');
    $db->exec('CREATE TABLE IF NOT EXISTS used_tokens (nonce TEXT PRIMARY KEY, ts INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS audit (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ts INTEGER NOT NULL, usr TEXT NOT NULL, ip TEXT NOT NULL,
        event TEXT NOT NULL, detail TEXT NOT NULL DEFAULT \'\')');
}

/* ---------- ajustes ---------- */

function gx_settings_all(bool $reload = false): array
{
    static $cache = null;
    if ($cache === null || $reload) {
        $cache = [];
        foreach (gx_db()->query('SELECT k, v FROM settings') as $r) {
            $d = json_decode($r['v'], true);
            $cache[$r['k']] = ($d === null && $r['v'] !== 'null') ? $r['v'] : $d;
        }
    }
    return $cache;
}

function gx_setting(string $k, $default = null)
{
    $all = gx_settings_all();
    return array_key_exists($k, $all) ? $all[$k] : $default;
}

function gx_set(string $k, $v): void
{
    $st = gx_db()->prepare('INSERT INTO settings (k, v) VALUES (:k, :v) ON CONFLICT(k) DO UPDATE SET v = excluded.v');
    $st->execute([':k' => $k, ':v' => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    gx_settings_all(true);
}

/* ---------- páginas ---------- */

function gx_pages(): array
{
    $out = [];
    foreach (gx_db()->query('SELECT slug, sort, data FROM pages ORDER BY sort ASC') as $r) {
        $d = json_decode($r['data'], true);
        if (is_array($d)) {
            $d['slug'] = $r['slug'];
            $out[$r['slug']] = $d;
        }
    }
    return $out;
}

function gx_page(string $slug): ?array
{
    $st = gx_db()->prepare('SELECT slug, data FROM pages WHERE slug = :s');
    $st->execute([':s' => $slug]);
    $r = $st->fetch();
    if (!$r) {
        return null;
    }
    $d = json_decode($r['data'], true);
    if (!is_array($d)) {
        return null;
    }
    $d['slug'] = $r['slug'];
    return $d;
}

function gx_page_save(string $slug, array $data): void
{
    unset($data['slug']);
    $st = gx_db()->prepare('UPDATE pages SET data = :d WHERE slug = :s');
    $st->execute([':d' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':s' => $slug]);
}

/* ---------- medios ---------- */

function gx_media_get(int $id): ?array
{
    $st = gx_db()->prepare('SELECT * FROM media WHERE id = :i');
    $st->execute([':i' => $id]);
    $r = $st->fetch();
    return $r ?: null;
}

function gx_media_all(): array
{
    return gx_db()->query('SELECT * FROM media ORDER BY id DESC')->fetchAll();
}

/* ---------- auditoría y límites de frecuencia ---------- */

function gx_audit(string $event, string $detail = '', string $user = ''): void
{
    try {
        if ($user === '' && isset($_SESSION['gx_user'])) {
            $user = (string)$_SESSION['gx_user'];
        }
        $st = gx_db()->prepare('INSERT INTO audit (ts, usr, ip, event, detail) VALUES (:t, :u, :i, :e, :d)');
        $st->execute([':t' => gx_now(), ':u' => mb_substr($user, 0, 60), ':i' => gx_ip(),
            ':e' => mb_substr($event, 0, 60), ':d' => mb_substr($detail, 0, 500)]);
        if (random_int(1, 50) === 1) {
            gx_db()->exec('DELETE FROM audit WHERE id < (SELECT MAX(id) - 5000 FROM audit)');
        }
    } catch (Throwable $t) {
        error_log('audit fail: ' . $t->getMessage());
    }
}

function gx_rate_hit(string $kind, string $key): void
{
    $st = gx_db()->prepare('INSERT INTO rate_limit (kind, k, ts) VALUES (:a, :b, :c)');
    $st->execute([':a' => $kind, ':b' => $key, ':c' => gx_now()]);
    if (random_int(1, 40) === 1) {
        gx_db()->prepare('DELETE FROM rate_limit WHERE ts < :t')->execute([':t' => gx_now() - 172800]);
        gx_db()->prepare('DELETE FROM used_tokens WHERE ts < :t')->execute([':t' => gx_now() - 14400]);
    }
}

function gx_rate_count(string $kind, string $key, int $window): int
{
    $st = gx_db()->prepare('SELECT COUNT(*) FROM rate_limit WHERE kind = :a AND k = :b AND ts > :t');
    $st->execute([':a' => $kind, ':b' => $key, ':t' => gx_now() - $window]);
    return (int)$st->fetchColumn();
}
