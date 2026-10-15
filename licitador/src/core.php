<?php
/**
 * Licitador - Núcleo: configuración, base de datos, sesión, ayudantes, enrutador.
 */
define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');
if (PHP_VERSION_ID < 80000) { http_response_code(500); exit('Licitador requiere PHP 8.0 o superior.'); }

// ---- Errores: nunca visibles al público, se registran en storage/logs ----
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('error_log', APP_ROOT . '/storage/logs/php.log');
mb_internal_encoding('UTF-8');

function cfg(?string $k = null, $d = null) {
    static $c = null;
    if ($k === '__reload') { $c = null; return null; }
    if ($c === null) {
        $f = APP_ROOT . '/config/config.php';
        $c = is_file($f) ? (array) include $f : [];
    }
    return $k === null ? $c : ($c[$k] ?? $d);
}
function is_installed(): bool { return is_file(APP_ROOT . '/storage/installed.lock') && cfg('db_name'); }

// ---- Base de datos ----
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('mysql:host=' . cfg('db_host', 'localhost') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4',
        cfg('db_user'), cfg('db_pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    $pdo->exec("SET NAMES utf8mb4");
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function row(string $sql, array $p = []) { return q($sql, $p)->fetch() ?: null; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; }
function insert(string $t, array $d): int {
    $cols = array_keys($d);
    q("INSERT INTO `$t` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")", array_values($d));
    return (int) db()->lastInsertId();
}
function update(string $t, array $d, string $where, array $wp = []): void {
    $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($d)));
    q("UPDATE `$t` SET $set WHERE $where", array_merge(array_values($d), $wp));
}

// ---- Ajustes (clave/valor) ----
function settings_all(bool $reload = false): array {
    static $s = null;
    if ($s === null || $reload) {
        $s = [];
        try { foreach (rows("SELECT k,v FROM settings") as $r) $s[$r['k']] = $r['v']; } catch (Throwable $e) {}
    }
    return $s;
}
function S(string $k, $d = '') { $a = settings_all(); return (isset($a[$k]) && $a[$k] !== '') ? $a[$k] : $d; }
function set_setting(string $k, $v): void {
    q("INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)", [$k, (string) $v]);
    settings_all(true);
}

// ---- Ayudantes generales ----
function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_path(): string {
    $d = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    return rtrim($d === '/' || $d === '.' ? '' : $d, '/');
}
function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}
function site_url(string $path = ''): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', '', $host);
    return (is_https() ? 'https://' : 'http://') . $host . url($path);
}
function url(string $path = ''): string {
    $path = ltrim($path, '/');
    if (cfg('clean_urls', true) && S('clean_urls', '1') !== '0') return base_path() . '/' . $path;
    if ($path === '') return base_path() . '/index.php';
    return base_path() . '/index.php?r=' . str_replace('?', '&', $path);
}
function asset(string $p): string {
    $f = APP_ROOT . '/assets/' . $p;
    return base_path() . '/assets/' . $p . (is_file($f) ? '?v=' . filemtime($f) : '');
}
function redirect(string $path, int $code = 302): void {
    if (!preg_match('#^https?://#', $path)) $path = url($path);
    header('Location: ' . $path, true, $code); exit;
}
function flash(?string $msg = null, string $type = 'ok') {
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return; }
    $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function post(string $k, string $d = ''): string { $v = $_POST[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function get(string $k, string $d = ''): string { $v = $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function postarr(string $k): array { $v = $_POST[$k] ?? []; return is_array($v) ? array_values(array_filter(array_map(fn($x) => is_string($x) ? trim($x) : '', $v), 'strlen')) : []; }
function old(string $k, $d = '') { return e($_POST[$k] ?? $d); }
function now(): string { return date('Y-m-d H:i:s'); }
function money($n, string $cur = ''): string {
    if ($n === null || $n === '') return '—';
    return 'Q ' . number_format((float) $n, 2, '.', ',');
}
$GLOBALS['MESES'] = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
function fdate($d, bool $time = false): string {
    if (!$d) return '—';
    $t = strtotime($d); if (!$t) return '—';
    $s = date('j', $t) . ' ' . substr($GLOBALS['MESES'][(int) date('n', $t) - 1], 0, 3) . ' ' . date('Y', $t);
    return $time ? $s . ' ' . date('H:i', $t) : $s;
}
function days_left($deadline): ?int {
    if (!$deadline) return null;
    return (int) floor((strtotime($deadline) - time()) / 86400) + (strtotime($deadline) >= time() ? 0 : 0);
}
function json_out($data, int $code = 200): void {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE); exit;
}
function slugify(string $s): string { return trim(preg_replace('/[^a-z0-9]+/', '-', norm($s)), '-'); }
function deptos(): array {
    return ['Alta Verapaz','Baja Verapaz','Chimaltenango','Chiquimula','El Progreso','Escuintla','Guatemala','Huehuetenango','Izabal','Jalapa','Jutiapa','Petén','Quetzaltenango','Quiché','Retalhuleu','Sacatepéquez','San Marcos','Santa Rosa','Sololá','Suchitepéquez','Totonicapán','Zacapa'];
}
function modalidades(): array {
    return ['compra_directa' => 'Compra directa', 'cotizacion' => 'Cotización', 'licitacion' => 'Licitación', 'contrato_abierto' => 'Contrato abierto'];
}
function categorias(): array {
    return ['construccion' => 'Construcción y obra', 'transporte' => 'Transporte y combustibles', 'medico' => 'Insumos médicos', 'escolar' => 'Útiles escolares y librería', 'ferreteria' => 'Ferretería y materiales', 'servicios' => 'Servicios profesionales', 'tecnologia' => 'Tecnología y equipo', 'alimentos' => 'Alimentos y refacciones', 'limpieza' => 'Limpieza y mantenimiento', 'otros' => 'Otros'];
}

// ---- Normalización de texto (motor de coincidencias) ----
function norm(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, ['á'=>'a','à'=>'a','ä'=>'a','â'=>'a','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','ñ'=>'n']);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
}
function stem(string $w): string {
    $n = strlen($w);
    if ($n > 5 && substr($w, -3) === 'ces') return substr($w, 0, -3) . 'z';
    if ($n > 5 && substr($w, -2) === 'es') return substr($w, 0, -2);
    if ($n > 3 && substr($w, -1) === 's') return substr($w, 0, -1);
    return $w;
}
function tokens(string $s): array {
    $out = [];
    foreach (explode(' ', norm($s)) as $w) { if ($w === '' ) continue; if (strlen($w) < 2 && !ctype_digit($w)) continue; $out[stem($w)] = true; }
    return array_keys($out);
}
function stop_words(): array { return ['de','del','la','el','los','las','y','e','en','para','con','por','un','una','a','al','o']; }

// ---- Sesión y cabeceras de seguridad ----
function boot_http(): void {
    header_remove('X-Powered-By');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    if (is_https()) header('Strict-Transport-Security: max-age=15552000');
    $sp = APP_ROOT . '/storage/sessions';
    if (!is_dir($sp)) @mkdir($sp, 0750, true);
    if (is_writable($sp)) session_save_path($sp);
    session_name('LICSESS');
    session_set_cookie_params(['lifetime' => 0, 'path' => base_path() ?: '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// ---- Autenticación ----
function user(): ?array {
    static $u = false;
    if ($u === false || (($u['id'] ?? 0) != ($_SESSION['uid'] ?? 0))) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $u = row("SELECT * FROM users WHERE id=? AND status='activo'", [$_SESSION['uid']]);
            if (!$u) { unset($_SESSION['uid']); }
        }
    }
    return $u;
}
function user_reset_cache(): void { }
function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    unset($_SESSION['impersonator']);
    q("UPDATE users SET last_login=? WHERE id=?", [now(), $u['id']]);
}
function hash_pass(string $p): string {
    return defined('PASSWORD_ARGON2ID') ? password_hash($p, PASSWORD_ARGON2ID) : password_hash($p, PASSWORD_BCRYPT);
}
function client_has_access(array $u): bool { return $u['sub_ends'] && strtotime($u['sub_ends']) >= time(); }
function admin_perms(): array {
    return ['superadmin' => ['*'], 'gestor' => ['dashboard','clientes','planes','pagos','concursos','fuente','notificaciones'], 'editor' => ['dashboard','contenido']];
}
function can(string $mod): bool {
    $u = user(); if (!$u || $u['role'] !== 'admin') return false;
    $p = admin_perms()[$u['admin_role'] ?: 'superadmin'] ?? [];
    return in_array('*', $p, true) || in_array($mod, $p, true);
}
function require_client(): array {
    $u = user();
    if (!$u) { flash('Inicia sesión para continuar.', 'err'); redirect('ingresar'); }
    if ($u['role'] === 'admin' && empty($_SESSION['impersonator'])) redirect('admin');
    return $u;
}
function require_admin(string $mod = 'dashboard'): array {
    $u = user();
    if (!$u || $u['role'] !== 'admin') { redirect('ingresar'); }
    if (!can($mod)) { http_response_code(403); render('admin/forbidden', [], 'admin'); exit; }
    return $u;
}

// ---- Vistas ----
function render(string $view, array $data = [], string $layout = 'public'): void {
    require_once APP_ROOT . '/src/views/partials.php';
    extract($data, EXTR_SKIP);
    ob_start();
    include APP_ROOT . '/src/views/' . $view . '.php';
    $content = ob_get_clean();
    if ($layout === 'none') { echo $content; return; }
    include APP_ROOT . '/src/views/layout_' . $layout . '.php';
}
function not_found(): void {
    http_response_code(404);
    $data = ['title' => 'No encontrado'];
    render('public/404', $data, 'public'); exit;
}

// ---- Enrutador ----
$GLOBALS['ROUTES'] = [];
function route(string $methods, string $pattern, callable $fn): void {
    $GLOBALS['ROUTES'][] = [explode('|', $methods), '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#', $fn];
}
function request_path(): string {
    if (isset($_GET['r']) && is_string($_GET['r'])) return '/' . trim($_GET['r'], '/');
    $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $p = rawurldecode($p);
    $b = base_path();
    if ($b !== '' && strpos($p, $b) === 0) $p = substr($p, strlen($b));
    $p = preg_replace('#^/index\.php#', '', $p);
    return '/' . trim($p, '/');
}
function dispatch(): void {
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = request_path();
    if ($m === 'POST') {
        $t = $_POST['_csrf'] ?? '';
        if (!is_string($t) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
            http_response_code(403);
            render('public/csrf', ['title' => 'Sesión expirada'], 'public'); exit;
        }
    }
    foreach ($GLOBALS['ROUTES'] as [$ms, $re, $fn]) {
        if (in_array($m, $ms, true) && preg_match($re, $path, $mm)) {
            $args = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
            $fn(...array_values($args)); return;
        }
    }
    not_found();
}
function ip(): string { return substr(preg_replace('/[^0-9a-fA-F:.]/', '', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45); }

// ---- Subidas seguras (fuera de la carpeta ejecutable) ----
function save_upload(string $field, string $dir = 'uploads', array $ext = ['jpg','jpeg','png','pdf','webp'], int $max = 3145728): ?string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir el archivo. Intenta de nuevo.');
    if ($f['size'] > $max) throw new RuntimeException('El archivo es muy pesado (máximo ' . round($max / 1048576) . ' MB).');
    $e = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($e, $ext, true)) throw new RuntimeException('Tipo de archivo no permitido. Usa: ' . implode(', ', $ext) . '.');
    $mime = function_exists('finfo_open') ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']) : '';
    $okm = ['image/jpeg','image/png','image/webp','application/pdf','text/plain','text/csv','application/csv','application/vnd.ms-excel','application/json'];
    if ($mime && !in_array($mime, $okm, true)) throw new RuntimeException('El contenido del archivo no coincide con su tipo.');
    $d = APP_ROOT . '/storage/' . $dir; if (!is_dir($d)) @mkdir($d, 0750, true);
    $name = bin2hex(random_bytes(12)) . '.' . $e;
    if (!move_uploaded_file($f['tmp_name'], $d . '/' . $name)) throw new RuntimeException('No se pudo guardar el archivo.');
    return $name;
}
