<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

const GX_IDLE_SECONDS = 1200;       // expiración por inactividad: 20 min
const GX_ABS_SECONDS = 28800;       // vida máxima de sesión: 8 h
const GX_LOGIN_WINDOW = 900;        // ventana de intentos fallidos: 15 min
const GX_LOGIN_MAX_IP = 8;
const GX_LOGIN_MAX_USER = 6;

/* ---------------- contraseñas ---------------- */

function gx_hash_password(string $pw): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($pw, PASSWORD_ARGON2ID);
    }
    return password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
}

/** @return string|null mensaje de error o null si la contraseña es aceptable */
function gx_password_policy(string $pw, string $user = ''): ?string
{
    if (mb_strlen($pw) < 12) {
        return 'La contraseña debe tener al menos 12 caracteres.';
    }
    if (mb_strlen($pw) > 200) {
        return 'La contraseña es demasiado larga.';
    }
    $classes = (preg_match('/[a-z]/', $pw) ? 1 : 0) + (preg_match('/[A-Z]/', $pw) ? 1 : 0)
        + (preg_match('/\d/', $pw) ? 1 : 0) + (preg_match('/[^a-zA-Z0-9]/', $pw) ? 1 : 0);
    if ($classes < 3) {
        return 'Use al menos 3 tipos de caracteres: minúsculas, mayúsculas, números y símbolos.';
    }
    if ($user !== '' && stripos($pw, $user) !== false) {
        return 'La contraseña no debe contener el nombre de usuario.';
    }
    return null;
}

function gx_random_password(int $len = 20): string
{
    $sets = ['abcdefghijkmnopqrstuvwxyz', 'ABCDEFGHJKLMNPQRSTUVWXYZ', '23456789', '!#$%*+-=?@^_'];
    $all = implode('', $sets);
    $out = [];
    foreach ($sets as $s) {
        $out[] = $s[random_int(0, strlen($s) - 1)];
    }
    while (count($out) < $len) {
        $out[] = $all[random_int(0, strlen($all) - 1)];
    }
    for ($i = count($out) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$out[$i], $out[$j]] = [$out[$j], $out[$i]];
    }
    return implode('', $out);
}

/** Hash apr1 (MD5 de Apache) para .htpasswd: compatible con Apache y LiteSpeed. */
function gx_apr1(string $pw, ?string $salt = null): string
{
    $alpha = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    if ($salt === null) {
        $salt = '';
        for ($i = 0; $i < 8; $i++) {
            $salt .= $alpha[random_int(0, 63)];
        }
    }
    $len = strlen($pw);
    $text = $pw . '$apr1$' . $salt;
    $bin = pack('H*', md5($pw . $salt . $pw));
    for ($i = $len; $i > 0; $i -= 16) {
        $text .= substr($bin, 0, min(16, $i));
    }
    for ($i = $len; $i > 0; $i >>= 1) {
        $text .= ($i & 1) ? chr(0) : $pw[0];
    }
    $bin = pack('H*', md5($text));
    for ($i = 0; $i < 1000; $i++) {
        $new = ($i & 1) ? $pw : $bin;
        if ($i % 3) {
            $new .= $salt;
        }
        if ($i % 7) {
            $new .= $pw;
        }
        $new .= ($i & 1) ? $bin : $pw;
        $bin = pack('H*', md5($new));
    }
    $tmp = '';
    for ($i = 0; $i < 5; $i++) {
        $k = $i + 6;
        $j = $i + 12;
        if ($j === 16) {
            $j = 5;
        }
        $tmp = $bin[$i] . $bin[$k] . $bin[$j] . $tmp;
    }
    $tmp = chr(0) . chr(0) . $bin[11] . $tmp;
    $enc = strtr(strrev(substr(base64_encode($tmp), 2)),
        'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/', $alpha);
    return '$apr1$' . $salt . '$' . $enc;
}

/* ---------------- TOTP (RFC 6238) sin librerías ---------------- */

function gx_b32enc(string $bin): string
{
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $a[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function gx_b32dec(string $s): string
{
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s) ?? '');
    $bits = '';
    foreach (str_split($s) as $c) {
        $bits .= str_pad(decbin((int)strpos($a, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function gx_totp_secret(): string
{
    return gx_b32enc(random_bytes(20));
}

function gx_totp_code(string $secretB32, int $step): string
{
    $key = gx_b32dec($secretB32);
    $h = hash_hmac('sha1', pack('N2', 0, $step), $key, true);
    $o = ord($h[19]) & 0x0F;
    $n = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Verifica el código con tolerancia de ±1 paso y evita reutilizar un código ya aceptado. */
function gx_totp_verify(int $userId, string $secretB32, string $code): bool
{
    $code = preg_replace('/\s+/', '', $code) ?? '';
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    $now = intdiv(gx_now(), 30);
    $st = gx_db()->prepare('SELECT totp_last FROM users WHERE id = :i');
    $st->execute([':i' => $userId]);
    $last = (int)$st->fetchColumn();
    for ($d = -1; $d <= 1; $d++) {
        $step = $now + $d;
        if ($step > $last && hash_equals(gx_totp_code($secretB32, $step), $code)) {
            gx_db()->prepare('UPDATE users SET totp_last = :s WHERE id = :i')->execute([':s' => $step, ':i' => $userId]);
            return true;
        }
    }
    return false;
}

/* ---------------- sesión ---------------- */

function gx_admin_base(): string
{
    $p = dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));
    $p = '/' . trim(str_replace('\\', '/', $p), '/');
    return $p === '/' ? '/' : $p . '/';
}

function gx_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $dir = GX_DATA . '/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.use_trans_sid', '0');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.gc_maxlifetime', (string)GX_ABS_SECONDS);
    @ini_set('session.sid_length', '48');
    @ini_set('session.sid_bits_per_character', '5');
    session_save_path($dir);
    session_name('gxsid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => gx_admin_base(),
        'secure' => gx_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    $ua = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $now = gx_now();
    if (isset($_SESSION['gx_born'])) {
        $bad = ($_SESSION['gx_ua'] ?? '') !== $ua
            || $now - (int)($_SESSION['gx_last'] ?? 0) > GX_IDLE_SECONDS
            || $now - (int)$_SESSION['gx_born'] > GX_ABS_SECONDS;
        if ($bad) {
            gx_session_destroy();
            session_start();
        }
    }
    if (!isset($_SESSION['gx_born'])) {
        $_SESSION['gx_born'] = $now;
        $_SESSION['gx_ua'] = $ua;
        $_SESSION['gx_rot'] = $now;
    }
    $_SESSION['gx_last'] = $now;
    if ($now - (int)($_SESSION['gx_rot'] ?? 0) > 900) {
        session_regenerate_id(true);
        $_SESSION['gx_rot'] = $now;
    }
}

function gx_session_destroy(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    if (!headers_sent()) {
        setcookie('gxsid', '', ['expires' => 1, 'path' => gx_admin_base(), 'secure' => gx_is_https(), 'httponly' => true, 'samesite' => 'Strict']);
    }
}

function gx_csrf(): string
{
    if (empty($_SESSION['gx_csrf'])) {
        $_SESSION['gx_csrf'] = gx_rand_hex(24);
    }
    return (string)$_SESSION['gx_csrf'];
}

function gx_csrf_ok(): bool
{
    $t = $_POST['_t'] ?? '';
    if (!is_string($t) || empty($_SESSION['gx_csrf']) || !hash_equals((string)$_SESSION['gx_csrf'], $t)) {
        return false;
    }
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $h) {
        if (!empty($_SERVER[$h])) {
            $host = (string)parse_url((string)$_SERVER[$h], PHP_URL_HOST);
            $port = parse_url((string)$_SERVER[$h], PHP_URL_PORT);
            $cmp = $host . ($port ? ':' . $port : '');
            $mine = (string)($_SERVER['HTTP_HOST'] ?? '');
            if (strcasecmp($cmp, $mine) !== 0 && strcasecmp($host, preg_replace('/:\d+$/', '', $mine) ?? '') !== 0) {
                return false;
            }
            break;
        }
    }
    return true;
}

/* ---------------- control de acceso ---------------- */

function gx_ip_allowed(string $ip, string $list): bool
{
    $list = trim($list);
    if ($list === '') {
        return true;
    }
    foreach (preg_split('/[\s,;]+/', $list, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $rule) {
        if ($rule === $ip) {
            return true;
        }
        if (preg_match('~^(\d{1,3}(?:\.\d{1,3}){3})/(\d{1,2})$~', $rule, $m) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $bits = (int)$m[2];
            if ($bits >= 0 && $bits <= 32) {
                $mask = $bits === 0 ? 0 : (~0 << (32 - $bits)) & 0xFFFFFFFF;
                if ((ip2long($ip) & $mask) === (ip2long($m[1]) & $mask)) {
                    return true;
                }
            }
        }
    }
    return false;
}

function gx_login_locked(string $ip, string $user): bool
{
    $since = gx_now() - GX_LOGIN_WINDOW;
    $a = gx_db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = :i AND ok = 0 AND ts > :t');
    $a->execute([':i' => $ip, ':t' => $since]);
    if ((int)$a->fetchColumn() >= GX_LOGIN_MAX_IP) {
        return true;
    }
    if ($user !== '') {
        $b = gx_db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE username = :u AND ok = 0 AND ts > :t');
        $b->execute([':u' => $user, ':t' => $since]);
        if ((int)$b->fetchColumn() >= GX_LOGIN_MAX_USER) {
            return true;
        }
    }
    return false;
}

function gx_login_record(string $ip, string $user, bool $ok): void
{
    $st = gx_db()->prepare('INSERT INTO login_attempts (ip, username, ts, ok) VALUES (:i, :u, :t, :o)');
    $st->execute([':i' => $ip, ':u' => mb_substr($user, 0, 60), ':t' => gx_now(), ':o' => $ok ? 1 : 0]);
    if ($ok) {
        gx_db()->prepare('DELETE FROM login_attempts WHERE ip = :i AND ok = 0')->execute([':i' => $ip]);
    }
    if (random_int(1, 30) === 1) {
        gx_db()->prepare('DELETE FROM login_attempts WHERE ts < :t')->execute([':t' => gx_now() - 604800]);
    }
}

function gx_user_by_name(string $u): ?array
{
    $st = gx_db()->prepare('SELECT * FROM users WHERE username = :u');
    $st->execute([':u' => $u]);
    $r = $st->fetch();
    return $r ?: null;
}

function gx_user_by_id(int $id): ?array
{
    $st = gx_db()->prepare('SELECT * FROM users WHERE id = :i');
    $st->execute([':i' => $id]);
    $r = $st->fetch();
    return $r ?: null;
}

/** Comprueba usuario y contraseña con tiempo constante aproximado. @return array|null usuario */
function gx_check_credentials(string $user, string $pw): ?array
{
    static $dummy = '$2y$12$abcdefghijklmnopqrstuuJ7mQ1c1j9uVQ9o0g8m3XJb3p5Q6o8e2';
    $u = gx_user_by_name($user);
    $ok = password_verify($pw, $u ? (string)$u['pass_hash'] : $dummy);
    return ($u && $ok) ? $u : null;
}

function gx_login_session(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['gx_uid'] = (int)$u['id'];
    $_SESSION['gx_user'] = (string)$u['username'];
    $_SESSION['gx_born'] = gx_now();
    $_SESSION['gx_rot'] = gx_now();
    unset($_SESSION['gx_pre']);
    gx_db()->prepare('UPDATE users SET last_login = :t WHERE id = :i')->execute([':t' => gx_now(), ':i' => $u['id']]);
}

function gx_current_user(): ?array
{
    if (empty($_SESSION['gx_uid'])) {
        return null;
    }
    return gx_user_by_id((int)$_SESSION['gx_uid']);
}
