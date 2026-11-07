<?php
/**
 * Autenticación del panel: sesiones endurecidas, CSRF, límite de intentos.
 */
if (!defined('TLL_BOOT')) { http_response_code(403); exit; }

const TLL_SESSION_IDLE = 7200;      // 2 horas sin actividad
const TLL_SESSION_MAX  = 43200;     // 12 horas máximo
const TLL_LOGIN_MAX    = 5;         // intentos fallidos
const TLL_LOGIN_WINDOW = 900;       // en 15 minutos

function auth_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    // Carpeta de sesiones propia (algunos hostings compartidos bloquean la ruta por defecto)
    $dir = TLL_DATA . '/sessions';
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
    if (is_dir($dir) && is_writable($dir)) {
        $ht = $dir . '/.htaccess';
        if (!is_file($ht)) { @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n"); }
        session_save_path($dir);
    }
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.use_trans_sid', '0');
    @ini_set('session.gc_maxlifetime', (string)TLL_SESSION_MAX);
    @ini_set('session.gc_probability', '1');
    @ini_set('session.gc_divisor', '50');
    session_name('tllsid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------- Usuarios ---------- */
function users_load(): array {
    $d = store_read('users', null);
    if (!is_array($d) || empty($d['users'])) {
        $d = ['users' => [[
            'username' => TLL_DEFAULT_ADMIN_USER,
            'hash'     => TLL_DEFAULT_ADMIN_HASH,
            'updated'  => date('Y-m-d H:i:s'),
        ]]];
        store_write('users', $d);
    }
    return $d;
}
function user_admin(): array { $u = users_load(); return $u['users'][0]; }
function using_default_password(): bool { return hash_equals(TLL_DEFAULT_ADMIN_HASH, (string)user_admin()['hash']); }

function ua_fingerprint(): string { return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . app_secret()); }

function auth_check(): bool {
    auth_session_start();
    if (empty($_SESSION['uid'])) return false;
    $now = time();
    if (($now - ($_SESSION['last'] ?? 0)) > TLL_SESSION_IDLE || ($now - ($_SESSION['login_at'] ?? 0)) > TLL_SESSION_MAX) { auth_logout(); return false; }
    if (!hash_equals($_SESSION['ua'] ?? '', ua_fingerprint())) { auth_logout(); return false; }
    $_SESSION['last'] = $now;
    return true;
}

function auth_require(): void {
    if (!auth_check()) {
        header('Location: ' . url('admin/login.php'), true, 302);
        exit;
    }
}

function auth_logout(): void {
    auth_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    @session_destroy();
}

/**
 * @return array [bool ok, string error]
 */
function auth_login(string $username, string $password): array {
    $ip = client_ip();
    $uk = 'lu:' . strtolower(substr($username, 0, 64));
    $ik = 'li:' . $ip;
    if (rl_hits($ik, TLL_LOGIN_WINDOW) >= TLL_LOGIN_MAX * 2 || rl_hits($uk, TLL_LOGIN_WINDOW) >= TLL_LOGIN_MAX) {
        return [false, 'Demasiados intentos fallidos. Por seguridad, espera 15 minutos e inténtalo de nuevo.'];
    }
    $u = user_admin();
    $okUser = hash_equals((string)$u['username'], $username);
    // Siempre se verifica un hash (evita ataques por tiempo de respuesta)
    $okPass = password_verify($password, $okUser ? (string)$u['hash'] : TLL_DEFAULT_ADMIN_HASH);
    if ($okUser && $okPass) {
        rl_clear($uk); rl_clear($ik);
        auth_session_start();
        session_regenerate_id(true);
        $_SESSION = [
            'uid'      => 1,
            'login_at' => time(),
            'last'     => time(),
            'ua'       => ua_fingerprint(),
            'csrf'     => bin2hex(random_bytes(32)),
        ];
        // Re-hash si el algoritmo mejoró
        if (password_needs_rehash((string)$u['hash'], PASSWORD_DEFAULT) && !using_default_password()) {
            $d = users_load(); $d['users'][0]['hash'] = password_hash($password, PASSWORD_DEFAULT); store_write('users', $d);
        }
        return [true, ''];
    }
    rl_add($uk, TLL_LOGIN_WINDOW); rl_add($ik, TLL_LOGIN_WINDOW);
    usleep(400000);
    return [false, 'Usuario o contraseña incorrectos.'];
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
    auth_session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_valid(): bool {
    $tok = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($tok) || $tok === '' || !hash_equals(csrf_token(), $tok)) return false;
    // Comprobación extra de origen cuando el navegador la envía
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        $oh = parse_url($origin, PHP_URL_HOST);
        $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
        if ($oh && strcasecmp($oh, $host) !== 0) return false;
    }
    return true;
}
function csrf_require(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_valid()) {
        http_response_code(400);
        flash('error', 'La sesión expiró o la solicitud no es válida. Recarga la página e inténtalo de nuevo.');
        header('Location: ' . url('admin/'));
        exit;
    }
}

/* ---------- Mensajes flash ---------- */
function flash(string $type, string $msg): void { auth_session_start(); $_SESSION['flash'][] = [$type, $msg]; }
function flash_take(): array { auth_session_start(); $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
