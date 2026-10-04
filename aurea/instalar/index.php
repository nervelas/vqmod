<?php
declare(strict_types=1);

/**
 * AUREA · Asistente de instalación (4 pasos). Se bloquea solo al terminar.
 * Después de instalar, ELIMINA esta carpeta (/instalar) del servidor.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use Aurea\Core\Db;
use Aurea\Core\Util;
use Aurea\Services\SetupService;
use Aurea\Services\PresetService;

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'; form-action 'self'");
ini_set('display_errors', '0');

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function page(string $title, string $body): void
{
    $b = rtrim(str_replace('\\', '/', dirname(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/instalar/index.php')))), '/');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . h($title) . ' · Instalación AUREA</title>'
        . '<link rel="stylesheet" href="' . h($b) . '/assets/css/base.css"><link rel="stylesheet" href="' . h($b) . '/assets/css/public.css"><link rel="stylesheet" href="' . h($b) . '/assets/css/admin.css"></head>'
        . '<body class="admin"><main style="max-width:760px;margin:0 auto;padding:40px 20px 80px">'
        . '<div style="text-align:center;margin-bottom:28px"><span class="mono" style="margin:0 auto 12px;width:54px;height:54px;font-size:1.8rem">A</span><div class="eyebrow">AUREA · Agenda profesional premium</div></div>'
        . $body . '</main></body></html>';
}

$root = dirname(__DIR__);
if (is_file($root . '/config/config.php') || is_file($root . '/config/installed.lock')) {
    http_response_code(403);
    page('Instalación bloqueada', '<div class="card"><h1>Instalación bloqueada</h1><p>AUREA ya está instalado. Por seguridad, <b>elimina la carpeta <code>/instalar</code></b> de tu servidor.</p><p><a class="btn btn-gold" href="' . h(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/instalar/index.php')) === '/' ? '' : dirname(dirname($_SERVER['SCRIPT_NAME']))) . '/admin">Ir al panel</a></p></div>');
    exit;
}

$sp = $root . '/storage/sessions';
if (is_dir($sp) && is_writable($sp)) { session_save_path($sp); }
session_name('aurea_inst');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
if (empty($_SESSION['t'])) { $_SESSION['t'] = bin2hex(random_bytes(24)); }
$csrf = '<input type="hidden" name="_t" value="' . h($_SESSION['t']) . '">';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post && !hash_equals($_SESSION['t'], (string)($_POST['_t'] ?? ''))) { http_response_code(419); page('Error', '<div class="card"><p>La sesión expiró. Recarga la página.</p></div>'); exit; }

$step = max(1, min(4, (int)($_GET['paso'] ?? 1)));
$err = '';
$s = &$_SESSION['inst'];
$s = $s ?? [];

// ---------- Requisitos ----------
function requirements(string $root): array
{
    $w = static fn(string $p): bool => (is_dir($p) || @mkdir($p, 0755, true)) && is_writable($p);
    $r = [
        ['PHP 8.0 o superior (tienes ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.0.0', '>='), true],
        ['Extensión pdo_mysql', extension_loaded('pdo_mysql'), true],
        ['Extensión mbstring', extension_loaded('mbstring'), true],
        ['Extensión json', extension_loaded('json'), true],
        ['Extensión fileinfo', extension_loaded('fileinfo'), true],
        ['Extensión openssl', extension_loaded('openssl'), true],
        ['Extensión gd o imagick (recomendada: optimiza imágenes)', extension_loaded('gd') || extension_loaded('imagick'), false],
        ['Escritura en /config', $w($root . '/config'), true],
        ['Escritura en /storage', $w($root . '/storage') && $w($root . '/storage/logs') && $w($root . '/storage/cache') && $w($root . '/storage/private') && $w($root . '/storage/sessions') && $w($root . '/storage/backups'), true],
        ['Escritura en /uploads', $w($root . '/uploads'), true],
    ];
    return $r;
}

// ---------- Paso 2: BD ----------
if ($post && $step === 2) {
    $db = ['host' => trim((string)$_POST['host']), 'port' => (int)($_POST['port'] ?: 3306), 'name' => trim((string)$_POST['name']), 'user' => trim((string)$_POST['user']), 'pass' => (string)$_POST['pass']];
    try {
        $pdo = Db::connect($db);
        $ver = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
        $isMaria = stripos($ver, 'mariadb') !== false;
        preg_match('/(\d+)\.(\d+)/', $ver, $m);
        $okVer = $isMaria ? ((int)$m[1] > 10 || ((int)$m[1] === 10 && (int)$m[2] >= 3)) : ((int)$m[1] > 5 || ((int)$m[1] === 5 && (int)$m[2] >= 7));
        if (!$okVer) { throw new RuntimeException('Se requiere MySQL 5.7+ o MariaDB 10.3+ (tu servidor: ' . $ver . ').'); }
        $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('settings','appointments','clients')")->fetchColumn();
        if ($exists > 0 && empty($_POST['wipe'])) { throw new RuntimeException('La base de datos ya contiene tablas de AUREA. Marca la casilla para reemplazarlas (se borrarán sus datos) o usa otra base de datos.'); }
        $s['db'] = $db; $s['db_ver'] = $ver; $s['wipe'] = $exists > 0;
        header('Location: ?paso=3'); exit;
    } catch (Throwable $e) {
        $msg = $e instanceof PDOException ? 'No se pudo conectar: revisa servidor, nombre de base, usuario y contraseña.' : $e->getMessage();
        $err = $msg;
    }
}
// ---------- Paso 3: negocio ----------
if ($post && $step === 3) {
    $biz = ['name' => Util::limit((string)$_POST['bname'], 150), 'phone' => Util::limit((string)$_POST['bphone'], 30), 'whatsapp' => Util::limit((string)$_POST['bwa'], 30),
        'email' => trim((string)$_POST['bemail']), 'address' => Util::limit((string)$_POST['baddr'], 255), 'timezone' => (string)$_POST['tz']];
    $adm = ['name' => Util::limit((string)$_POST['aname'], 150), 'email' => trim((string)$_POST['aemail']), 'password' => (string)$_POST['apass']];
    if ($biz['name'] === '') { $err = 'Escribe el nombre de tu negocio.'; }
    elseif ($biz['email'] !== '' && !Util::isEmail($biz['email'])) { $err = 'El correo del negocio no es válido.'; }
    elseif (!in_array($biz['timezone'], DateTimeZone::listIdentifiers(), true)) { $err = 'Zona horaria inválida.'; }
    elseif (!Util::isEmail($adm['email'])) { $err = 'El correo del administrador no es válido.'; }
    elseif ($e = Aurea\Core\Auth::strongPassword($adm['password'])) { $err = $e; }
    elseif ($adm['password'] !== (string)$_POST['apass2']) { $err = 'Las contraseñas no coinciden.'; }
    else { $s['biz'] = $biz; $s['adm'] = $adm; header('Location: ?paso=4'); exit; }
}
// ---------- Paso 4: instalar ----------
if ($post && $step === 4 && !$err) {
    $preset = (string)$_POST['preset'];
    if (!isset(PresetService::labels()[$preset])) { $err = 'Elige un tipo de profesión.'; }
    elseif (empty($s['db']) || empty($s['biz'])) { header('Location: ?paso=1'); exit; }
    else {
        try {
            $pdo = Db::connect($s['db']);
            if (!empty($s['wipe'])) {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                foreach ($pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchAll(PDO::FETCH_COLUMN) as $t) {
                    if (preg_match('/^[a-z_]+$/', (string)$t)) { $pdo->exec('DROP TABLE IF EXISTS `' . $t . '`'); }
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
            $tzName = $s['biz']['timezone'];
            date_default_timezone_set($tzName);
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $host = preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', (string)($_SERVER['HTTP_HOST'] ?? '')) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $baseUrl = ($https ? 'https' : 'http') . '://' . $host . base_path();
            $appKey = bin2hex(random_bytes(32));
            // La clave debe existir antes de cifrar secretos; se escribe el config temporal en memoria
            $cfg = ['db' => $s['db'], 'app_key' => $appKey, 'trust_proxy' => false, 'installed_at' => date('c')];
            $res = SetupService::install($s['biz'] + ['base_url' => $baseUrl], $s['adm'], $preset, !empty($_POST['demo']));
            if (!$res['ok']) { throw new RuntimeException($res['error'] ?? 'Error de instalación'); }
            $php = "<?php\n// Generado por el instalador de AUREA. No compartir este archivo.\nreturn " . var_export($cfg, true) . ";\n";
            if (file_put_contents($root . '/config/config.php', $php, LOCK_EX) === false) { throw new RuntimeException('No se pudo escribir config/config.php'); }
            @chmod($root . '/config/config.php', 0640);
            @file_put_contents($root . '/config/installed.lock', date('c') . "\n");
            Aurea\Core\Settings::flush();
            $s = [];
            session_destroy();
            page('Instalación completada', '<div class="card"><h1>¡Instalación completada!</h1><p>AUREA quedó listo. El instalador se <b>bloqueó automáticamente</b>.</p>'
                . '<div class="alert alert-err"><b>Importante:</b> elimina ahora la carpeta <code>/instalar</code> de tu servidor.</div>'
                . '<p><a class="btn btn-gold" href="' . h(base_path()) . '/admin">Entrar al panel</a> &nbsp; <a class="btn btn-line" href="' . h(base_path()) . '/">Ver sitio público</a></p>'
                . '<p class="hint">Configura el cron (ver README) para recordatorios automáticos. Sin cron, el sistema usa el modo de respaldo por visitas.</p></div>');
            exit;
        } catch (Throwable $e) {
            Aurea\Core\Logger::exception($e);
            @unlink($root . '/config/config.php');
            $err = 'No se pudo completar la instalación: ' . ($e instanceof PDOException ? 'error de base de datos (revisa storage/logs/error.log).' : $e->getMessage());
        }
    }
}

// ---------- Vistas ----------
$bar = '<ol class="stepper" style="margin-bottom:26px">';
foreach (['Requisitos', 'Base de datos', 'Negocio', 'Profesión'] as $i => $l) { $bar .= '<li class="' . ($i + 1 < $step ? 'done' : ($i + 1 === $step ? 'cur' : '')) . '"><span class="n">0' . ($i + 1) . '</span><span class="lbl">' . h($l) . '</span></li>'; }
$bar .= '</ol>';
$errHtml = $err !== '' ? '<div class="alert alert-err" role="alert">' . h($err) . '</div>' : '';

if ($step === 1) {
    $rows = ''; $block = false;
    foreach (requirements($root) as [$label, $ok, $must]) {
        if (!$ok && $must) { $block = true; }
        $rows .= '<tr><td>' . h($label) . '</td><td style="text-align:right"><span class="badge ' . ($ok ? 'badge-ok' : ($must ? 'badge-bad' : 'badge-warn')) . '">' . ($ok ? 'OK' : ($must ? 'Falta' : 'Recomendado')) . '</span></td></tr>';
    }
    page('Requisitos', $bar . '<div class="card"><h1>Requisitos del servidor</h1><div class="tbl-wrap"><table class="tbl"><tbody>' . $rows . '</tbody></table></div><p style="margin-top:20px">'
        . ($block ? '<span class="badge badge-bad">Corrige los puntos marcados y recarga</span>' : '<a class="btn btn-gold" href="?paso=2">Continuar</a>') . '</p></div>');
} elseif ($step === 2) {
    $d = $s['db'] ?? ['host' => 'localhost', 'port' => 3306, 'name' => '', 'user' => ''];
    page('Base de datos', $bar . '<form method="post" class="card">' . $csrf . '<h1>Base de datos</h1><p class="hint">Crea una base MySQL/MariaDB (utf8mb4) desde tu panel de hosting y escribe sus datos.</p>' . $errHtml
        . '<div class="row row-2"><div class="field"><label for="host">Servidor</label><input id="host" name="host" required value="' . h($d['host']) . '"></div><div class="field"><label for="port">Puerto</label><input id="port" name="port" type="number" value="' . h($d['port']) . '"></div></div>'
        . '<div class="field"><label for="name">Nombre de la base de datos</label><input id="name" name="name" required value="' . h($d['name']) . '"></div>'
        . '<div class="row row-2"><div class="field"><label for="user">Usuario</label><input id="user" name="user" required autocomplete="off" value="' . h($d['user']) . '"></div><div class="field"><label for="pass">Contraseña</label><input id="pass" name="pass" type="password" autocomplete="off"></div></div>'
        . '<label class="check"><input type="checkbox" name="wipe" value="1"><span>Si la base ya tiene tablas de AUREA, reemplazarlas (borra sus datos)</span></label>'
        . '<p><a class="btn btn-ghost" href="?paso=1">← Atrás</a> <button class="btn btn-gold" type="submit">Probar conexión y continuar</button></p></form>');
} elseif ($step === 3) {
    if (empty($s['db'])) { header('Location: ?paso=2'); exit; }
    $b = $s['biz'] ?? ['name' => '', 'phone' => '', 'whatsapp' => '', 'email' => '', 'address' => '', 'timezone' => 'America/Guatemala'];
    $a = $s['adm'] ?? ['name' => '', 'email' => ''];
    $tzs = ['America/Guatemala', 'America/Mexico_City', 'America/El_Salvador', 'America/Tegucigalpa', 'America/Costa_Rica', 'America/Panama', 'America/Bogota', 'America/Lima', 'America/Santiago', 'America/Argentina/Buenos_Aires', 'Europe/Madrid', 'America/New_York', 'America/Chicago', 'America/Los_Angeles'];
    $opts = ''; foreach ($tzs as $z) { $opts .= '<option' . ($z === $b['timezone'] ? ' selected' : '') . '>' . h($z) . '</option>'; }
    page('Negocio', $bar . '<form method="post" class="card">' . $csrf . '<h1>Tu negocio y administrador</h1>' . $errHtml
        . '<div class="fieldset"><legend>Negocio</legend><div class="field"><label for="bname" class="req">Nombre del consultorio / firma</label><input id="bname" name="bname" required value="' . h($b['name']) . '"></div>'
        . '<div class="row row-2"><div class="field"><label for="bphone">Teléfono</label><input id="bphone" name="bphone" value="' . h($b['phone']) . '"></div><div class="field"><label for="bwa">WhatsApp</label><input id="bwa" name="bwa" placeholder="5555 1234" value="' . h($b['whatsapp']) . '"></div></div>'
        . '<div class="row row-2"><div class="field"><label for="bemail">Correo de contacto</label><input id="bemail" name="bemail" type="email" value="' . h($b['email']) . '"></div><div class="field"><label for="tz">Zona horaria</label><select id="tz" name="tz">' . $opts . '</select></div></div>'
        . '<div class="field"><label for="baddr">Dirección</label><input id="baddr" name="baddr" value="' . h($b['address']) . '"></div></div>'
        . '<div class="fieldset"><legend>Administrador</legend><div class="row row-2"><div class="field"><label for="aname">Nombre</label><input id="aname" name="aname" required value="' . h($a['name']) . '"></div><div class="field"><label for="aemail" class="req">Correo (usuario)</label><input id="aemail" name="aemail" type="email" required value="' . h($a['email']) . '"></div></div>'
        . '<div class="row row-2"><div class="field"><label for="apass" class="req">Contraseña</label><input id="apass" name="apass" type="password" required autocomplete="new-password"><div class="help">Mínimo 10 caracteres, con mayúsculas, minúsculas y números.</div></div><div class="field"><label for="apass2" class="req">Repetir contraseña</label><input id="apass2" name="apass2" type="password" required autocomplete="new-password"></div></div></div>'
        . '<p><a class="btn btn-ghost" href="?paso=2">← Atrás</a> <button class="btn btn-gold" type="submit">Continuar</button></p></form>');
} else {
    if (empty($s['db']) || empty($s['biz'])) { header('Location: ?paso=1'); exit; }
    $opts = ''; foreach (PresetService::labels() as $k => $l) { $opts .= '<option value="' . h($k) . '"' . ($k === 'otro' ? ' selected' : '') . '>' . h($l) . '</option>'; }
    page('Profesión', $bar . '<form method="post" class="card">' . $csrf . '<h1>Tipo de profesión</h1><p class="hint">Cargamos servicios, textos, formulario y mensajes sugeridos. Todo se puede editar después.</p>' . $errHtml
        . '<div class="field"><label for="preset">Profesión</label><select id="preset" name="preset">' . $opts . '</select></div>'
        . '<label class="check"><input type="checkbox" name="demo" value="1"><span>Cargar datos de demostración (2 profesionales, clientes y citas de ejemplo). Puedes borrarlos luego.</span></label>'
        . '<p><a class="btn btn-ghost" href="?paso=3">← Atrás</a> <button class="btn btn-gold" type="submit">Instalar AUREA</button></p></form>');
}
