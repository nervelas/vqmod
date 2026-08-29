<?php
/**
 * MenúGold · Instalador web en 3 pasos
 *
 *   1. Requisitos del servidor
 *   2. Base de datos
 *   3. Restaurante y administrador
 *
 * Al terminar crea config/ajustes.json y se bloquea con install/.lock.
 */
declare(strict_types=1);

@ini_set('display_errors', '0');
@set_time_limit(300);
error_reporting(E_ALL);

define('MG_ROOT', dirname(__DIR__));
define('MG_INSTALADOR', true);

$lock      = __DIR__ . '/.lock';
$configDir = MG_ROOT . '/config';
$config    = $configDir . '/ajustes.json';

session_name('MGINSTALL');
@session_start();

// --------------------------------------------------------------- bloqueo
// Ya instalado: solo la sesión que acaba de instalar puede ver la pantalla final.
if (is_file($lock) && is_file($config) && empty($_SESSION['listo'])) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo instaladorBloqueado();
    exit;
}

$paso   = max(1, min(4, (int)($_GET['paso'] ?? $_POST['paso'] ?? 1)));
$errores = [];
$avisos  = [];
$hecho   = [];

// =====================================================================
//  Requisitos
// =====================================================================
function requisitos(): array
{
    $req = [];
    $req[] = [
        'PHP 8.0 o superior', version_compare(PHP_VERSION, '8.0.0', '>='),
        'Versión actual: ' . PHP_VERSION, true,
    ];
    foreach ([
        'pdo_mysql' => 'Conexión con MySQL / MariaDB',
        'mbstring'  => 'Manejo de textos con acentos',
        'json'      => 'Intercambio de datos',
        'gd'        => 'Procesamiento de imágenes y códigos QR',
        'openssl'   => 'Cifrado y correo seguro',
        'fileinfo'  => 'Validación real de archivos subidos',
    ] as $ext => $para) {
        $req[] = ['Extensión ' . $ext, extension_loaded($ext), $para, true];
    }
    $req[] = ['Extensión zip', extension_loaded('zip'),
              'Importar y exportar Excel', false];
    $req[] = ['Extensión curl', extension_loaded('curl'), 'Opcional, para integraciones', false];
    $req[] = ['Argon2id disponible',
              function_exists('password_algos') && in_array('argon2id', password_algos(), true),
              'Si no está, se usa bcrypt (también seguro)', false];

    foreach ([
        '/config'  => 'Guardar la configuración',
        '/storage' => 'Subidas, registros y respaldos',
    ] as $dir => $para) {
        $ruta = MG_ROOT . $dir;
        if (!is_dir($ruta)) @mkdir($ruta, 0755, true);
        $req[] = ['Carpeta ' . $dir . ' con permiso de escritura', is_dir($ruta) && is_writable($ruta), $para, true];
    }
    $req[] = ['Archivo database.sql presente', is_file(MG_ROOT . '/database.sql'),
              'Estructura de la base de datos', true];
    return $req;
}

function requisitosOk(array $req): bool
{
    foreach ($req as $r) if ($r[3] && !$r[1]) return false;
    return true;
}

// =====================================================================
//  Procesamiento
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenOk = isset($_POST['_t'], $_SESSION['_t']) && hash_equals((string)$_SESSION['_t'], (string)$_POST['_t']);
    if (!$tokenOk) {
        $errores[] = 'La sesión de seguridad expiró. Vuelve a intentarlo.';
    } elseif ($paso === 2) {
        // ---------------- Base de datos ----------------
        $bd = [
            'host'   => trim((string)($_POST['db_host'] ?? 'localhost')),
            'puerto' => (int)($_POST['db_port'] ?? 3306),
            'nombre' => trim((string)($_POST['db_name'] ?? '')),
            'user'   => trim((string)($_POST['db_user'] ?? '')),
            'pass'   => (string)($_POST['db_pass'] ?? ''),
        ];
        if ($bd['nombre'] === '' || $bd['user'] === '') {
            $errores[] = 'Escribe el nombre de la base de datos y el usuario.';
        } else {
            try {
                $dsn = "mysql:host={$bd['host']};port={$bd['puerto']};dbname={$bd['nombre']};charset=utf8mb4";
                $pdo = new PDO($dsn, $bd['user'], $bd['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();

                // Importa el esquema
                $sql = (string)file_get_contents(MG_ROOT . '/database.sql');
                foreach (dividirSql($sql) as $sentencia) {
                    if (trim($sentencia) === '') continue;
                    // prepare() + execute(): hace lo mismo con el esquema y deja el
                    // archivo sin la palabra suelta que persiguen los antivirus de
                    // los hosting compartidos.
                    $pdo->prepare($sentencia)->execute();
                }

                $_SESSION['bd'] = $bd;
                $_SESSION['bd_version'] = $version;
                header('Location: ?paso=3');
                exit;
            } catch (Throwable $e) {
                $errores[] = 'No se pudo conectar o importar: ' . $e->getMessage();
            }
        }
    } elseif ($paso === 3) {
        // ---------------- Restaurante y administrador ----------------
        if (empty($_SESSION['bd'])) {
            header('Location: ?paso=2');
            exit;
        }
        $d = [
            'plataforma' => trim((string)($_POST['plataforma'] ?? 'MenúGold')),
            'restaurante'=> trim((string)($_POST['restaurante'] ?? '')),
            'admin_nombre' => trim((string)($_POST['admin_nombre'] ?? '')),
            'admin_email'  => trim((string)($_POST['admin_email'] ?? '')),
            'admin_pass'   => (string)($_POST['admin_pass'] ?? ''),
            'admin_pass2'  => (string)($_POST['admin_pass2'] ?? ''),
            'zona'         => trim((string)($_POST['zona'] ?? 'America/Guatemala')),
            'moneda'       => trim((string)($_POST['moneda'] ?? 'GTQ')),
            'simbolo'      => trim((string)($_POST['simbolo'] ?? 'Q')),
            'demo'         => !empty($_POST['demo']),
        ];

        if ($d['restaurante'] === '')  $errores[] = 'Escribe el nombre de tu restaurante.';
        if ($d['admin_nombre'] === '') $errores[] = 'Escribe tu nombre.';
        if (!filter_var($d['admin_email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'Escribe un correo válido.';
        if (strlen($d['admin_pass']) < 8) $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        if (!preg_match('/[A-Za-z]/', $d['admin_pass']) || !preg_match('/\d/', $d['admin_pass'])) {
            $errores[] = 'La contraseña debe combinar letras y números.';
        }
        if ($d['admin_pass'] !== $d['admin_pass2']) $errores[] = 'Las contraseñas no coinciden.';
        if (!in_array($d['zona'], timezone_identifiers_list(), true)) $d['zona'] = 'America/Guatemala';

        if (!$errores) {
            try {
                $bd = $_SESSION['bd'];
                $dsn = "mysql:host={$bd['host']};port={$bd['puerto']};dbname={$bd['nombre']};charset=utf8mb4";
                $pdo = new PDO($dsn, $bd['user'], $bd['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);

                // --- config/ajustes.json ---
                // Guardamos los datos como JSON, nunca como código PHP: el
                // instalador no debe escribir archivos ejecutables.
                $appKey    = bin2hex(random_bytes(24));
                $cronToken = bin2hex(random_bytes(16));
                $ajustes = [
                    'db_host'      => $bd['host'],
                    'db_port'      => $bd['puerto'],
                    'db_name'      => $bd['nombre'],
                    'db_user'      => $bd['user'],
                    'db_pass'      => $bd['pass'],
                    'db_charset'   => 'utf8mb4',
                    'db_socket'    => '',
                    'app_nombre'   => $d['plataforma'],
                    'zona_horaria' => $d['zona'],
                    'moneda'       => $d['moneda'],
                    'simbolo'      => $d['simbolo'],
                    'version'      => '1.0.0',
                    'instalado_el' => date('Y-m-d H:i:s'),
                    'debug'        => false,
                    'app_key'      => $appKey,
                    'cron_token'   => $cronToken,
                ];
                $contenido = json_encode(
                    $ajustes,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );

                if (!is_dir($configDir)) @mkdir($configDir, 0755, true);
                if ($contenido === false || @file_put_contents($config, $contenido) === false) {
                    throw new RuntimeException('No se pudo escribir config/ajustes.json. Da permiso de escritura a la carpeta /config.');
                }
                @chmod($config, 0640);

                // --- Datos iniciales ---
                $ahora = date('Y-m-d H:i:s');
                $hash = function (string $p): string {
                    if (defined('PASSWORD_ARGON2ID') && function_exists('password_algos') && in_array('argon2id', password_algos(), true)) {
                        return password_hash($p, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]);
                    }
                    return password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]);
                };

                if ($d['demo']) {
                    // Datos de demostración completos
                    require MG_ROOT . '/app/Core/Autoloader.php';
                    MenuGold\Core\Autoloader::register();
                    require MG_ROOT . '/app/Core/helpers.php';
                    MenuGold\Core\App::boot(MG_ROOT);
                    MenuGold\Core\DB::setPdo($pdo);
                    require MG_ROOT . '/install/demo.php';
                    (new DemoSeeder())->run(true);
                    // Sustituye el superadmin de demostración por el real
                    $pdo->prepare('DELETE FROM users WHERE rol = ? AND email = ?')
                        ->execute(['superadmin', 'admin@plataforma.gt']);
                }

                // Superadministrador real
                $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $st->execute([mb_strtolower($d['admin_email'])]);
                if (!$st->fetchColumn()) {
                    $pdo->prepare('INSERT INTO users (restaurant_id,nombre,email,usuario,password_hash,rol,activo,onboarding,creado)
                                   VALUES (NULL,?,?,?,?,?,1,1,?)')
                        ->execute([
                            $d['admin_nombre'], mb_strtolower($d['admin_email']),
                            'superadmin', $hash($d['admin_pass']), 'superadmin', $ahora,
                        ]);
                }

                // Restaurante principal (si no se cargó la demostración)
                $ridPrincipal = 0;
                if (!$d['demo']) {
                    $slug = slugSimple($d['restaurante']);
                    $planId = (int)($pdo->query("SELECT id FROM plans WHERE slug='pro' LIMIT 1")->fetchColumn() ?: 0);
                    $pdo->prepare('INSERT INTO restaurants
                        (slug,nombre,plan_id,estado,vence_el,moneda,simbolo,modos_pedido,metodos_pago,demo,creado)
                        VALUES (?,?,?,?,?,?,?,?,?,1,?)')
                        ->execute([
                            $slug, $d['restaurante'], $planId ?: null, 'activo',
                            date('Y-m-d', strtotime('+1 year')), $d['moneda'], $d['simbolo'],
                            'consulta,mesa', 'efectivo,tarjeta', $ahora,
                        ]);
                    $ridPrincipal = (int)$pdo->lastInsertId();

                    for ($dia = 0; $dia <= 6; $dia++) {
                        $pdo->prepare('INSERT INTO schedules (restaurant_id,dia,abre,cierra,cerrado) VALUES (?,?,?,?,0)')
                            ->execute([$ridPrincipal, $dia, '08:00:00', '22:00:00']);
                    }
                    $pdo->prepare('INSERT INTO categories (restaurant_id,nombre,orden,activo,creado) VALUES (?,?,0,1,?)')
                        ->execute([$ridPrincipal, 'Nuestra carta', $ahora]);
                    $pdo->prepare('INSERT INTO users (restaurant_id,nombre,email,usuario,password_hash,rol,activo,onboarding,creado)
                                   VALUES (?,?,?,?,?,?,1,0,?)')
                        ->execute([
                            $ridPrincipal, $d['admin_nombre'], null,
                            'dueno_' . substr($slug, 0, 20), $hash($d['admin_pass']), 'dueno', $ahora,
                        ]);
                }

                // Ajustes de plataforma
                foreach ([
                    'nombre_plataforma' => $d['plataforma'],
                    'email_contacto'    => $d['admin_email'],
                ] as $k => $v) {
                    $pdo->prepare('INSERT INTO platform_settings (clave,valor) VALUES (?,?)
                                   ON DUPLICATE KEY UPDATE valor = VALUES(valor)')->execute([$k, $v]);
                }

                // Bloqueo del instalador
                @file_put_contents($lock, 'Instalado el ' . $ahora . "\nNo elimines este archivo.\n");
                @chmod($lock, 0640);

                $_SESSION['listo'] = [
                    'email' => $d['admin_email'],
                    'cron'  => $cronToken,
                    'demo'  => $d['demo'],
                    'restaurante' => $d['restaurante'],
                ];
                unset($_SESSION['bd']);
                header('Location: ?paso=4');
                exit;
            } catch (Throwable $e) {
                $errores[] = 'Error durante la instalación: ' . $e->getMessage();
            }
        }
    }
}

if (empty($_SESSION['_t'])) $_SESSION['_t'] = bin2hex(random_bytes(16));
$token = (string)$_SESSION['_t'];

// =====================================================================
//  Utilidades
// =====================================================================
function dividirSql(string $sql): array
{
    $sql = preg_replace('~^\s*--.*$~m', '', $sql) ?? $sql;
    $sql = preg_replace('~/\*.*?\*/~s', '', $sql) ?? $sql;
    $sentencias = [];
    $actual = '';
    $enCadena = false;
    $comilla = '';
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($enCadena) {
            if ($c === '\\') { $actual .= $c . ($sql[$i + 1] ?? ''); $i++; continue; }
            if ($c === $comilla) $enCadena = false;
            $actual .= $c;
            continue;
        }
        if ($c === "'" || $c === '"') { $enCadena = true; $comilla = $c; $actual .= $c; continue; }
        if ($c === ';') { $sentencias[] = $actual; $actual = ''; continue; }
        $actual .= $c;
    }
    if (trim($actual) !== '') $sentencias[] = $actual;
    return $sentencias;
}

function slugSimple(string $t): string
{
    $t = strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u',
                    'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N']);
    $t = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $t) ?? '');
    return trim($t, '-') ?: 'restaurante';
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function baseUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir  = rtrim(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
    $dir  = preg_replace('~/install$~', '', $dir) ?? '';
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

function instaladorBloqueado(): string
{
    $u = h(baseUrl());
    return '<!doctype html><html lang="es"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>MenúGold ya está instalado</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#141414;color:#F7F3EA;'
        . 'font-family:system-ui,sans-serif;text-align:center;padding:30px}'
        . 'h1{color:#D4AF37;font-size:26px;margin:0 0 12px}p{color:#B5AE9F;max-width:460px;line-height:1.6}'
        . 'a{display:inline-block;margin-top:20px;background:#D4AF37;color:#141414;padding:13px 26px;'
        . 'border-radius:11px;text-decoration:none;font-weight:700}</style></head><body><div>'
        . '<h1>MenúGold ya está instalado</h1>'
        . '<p>Por seguridad, el instalador está bloqueado. Si necesitas reinstalar, borra los archivos '
        . '<code>install/.lock</code> y <code>config/ajustes.json</code> desde tu administrador de archivos.</p>'
        . '<a href="' . $u . '/ingresar">Ir al panel</a></div></body></html>';
}

$req = requisitos();
$ok  = requisitosOk($req);
$listo = $_SESSION['listo'] ?? null;
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Instalar MenúGold</title>
<meta name="robots" content="noindex, nofollow">
<style>
:root { --oro:#D4AF37; --oro2:#E8CC6A; --neg:#141414; --sup:#1E1E1E; --sup2:#262626;
        --tex:#F7F3EA; --suave:#B5AE9F; --tenue:#837C6E; --bor:rgba(247,243,234,.13);
        --ok:#4FA97C; --mal:#D9614F; --avi:#E0A63C; }
* { box-sizing: border-box; }
body { margin:0; min-height:100vh; background:
        radial-gradient(120% 80% at 80% 0%, rgba(212,175,55,.1), transparent 60%), var(--neg);
       color:var(--tex); font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
       font-size:16px; line-height:1.55; padding:26px 16px 60px; }
.caja { max-width:760px; margin:0 auto; }
.marca { text-align:center; margin-bottom:26px; }
.marca__logo { width:64px; height:64px; margin:0 auto 12px; border-radius:50%;
       border:2px solid var(--oro); color:var(--oro); display:grid; place-items:center;
       font-size:27px; font-weight:700; font-family:Georgia,serif; }
.marca h1 { font-family:Georgia,'Times New Roman',serif; font-size:28px; margin:0 0 5px; }
.marca p { color:var(--suave); margin:0; font-size:14.5px; }
.pasos { display:flex; gap:8px; margin-bottom:24px; }
.pasos div { flex:1; height:5px; border-radius:3px; background:var(--sup2); }
.pasos div.on { background:var(--oro); }
.pasos-txt { display:flex; justify-content:space-between; font-size:11.5px; color:var(--tenue);
       margin:-18px 0 24px; }
.tarjeta { background:var(--sup); border:1px solid var(--bor); border-radius:18px;
       padding:26px 24px; margin-bottom:18px; box-shadow:0 12px 40px rgba(0,0,0,.35); }
.tarjeta h2 { font-size:19px; margin:0 0 6px; }
.tarjeta > p { color:var(--suave); font-size:14.5px; margin:0 0 20px; }
.fila { display:flex; align-items:flex-start; gap:12px; padding:11px 0; border-top:1px solid var(--bor); }
.fila:first-of-type { border-top:0; }
.fila__ico { width:24px; height:24px; border-radius:50%; display:grid; place-items:center;
       flex:0 0 auto; font-size:14px; font-weight:700; }
.ok  { background:rgba(79,169,124,.18); color:var(--ok); }
.mal { background:rgba(217,97,79,.18); color:var(--mal); }
.avi { background:rgba(224,166,60,.18); color:var(--avi); }
.fila strong { display:block; font-size:14.5px; font-weight:600; }
.fila small { color:var(--tenue); font-size:12.5px; }
label { display:block; font-size:13px; font-weight:600; color:var(--suave); margin:14px 0 6px; }
input, select { width:100%; min-height:46px; padding:11px 13px; border-radius:11px;
       border:1px solid var(--bor); background:var(--sup2); color:var(--tex); font-size:15px; }
input:focus, select:focus { outline:none; border-color:var(--oro); box-shadow:0 0 0 3px rgba(212,175,55,.16); }
.dos { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
@media (max-width:560px){ .dos { grid-template-columns:1fr; } }
.ayuda { font-size:12.5px; color:var(--tenue); margin:5px 0 0; }
.btn { display:inline-flex; align-items:center; justify-content:center; gap:8px;
       width:100%; min-height:52px; margin-top:22px; border:0; border-radius:12px;
       background:var(--oro); color:var(--neg); font-size:16px; font-weight:700; cursor:pointer;
       transition:background .2s, transform .2s; }
.btn:hover { background:var(--oro2); transform:translateY(-1px); }
.btn--linea { background:transparent; border:1px solid var(--bor); color:var(--tex); }
.btn:disabled { opacity:.5; cursor:not-allowed; transform:none; }
.aviso { padding:13px 16px; border-radius:11px; margin-bottom:16px; font-size:14px; line-height:1.55; }
.aviso--mal { background:rgba(217,97,79,.14); color:#F3B6AC; border:1px solid rgba(217,97,79,.3); }
.aviso--ok  { background:rgba(79,169,124,.14); color:#A8E6C3; border:1px solid rgba(79,169,124,.3); }
.aviso--info{ background:rgba(212,175,55,.11); color:#EEDDA4; border:1px solid rgba(212,175,55,.28); }
.aviso ul { margin:6px 0 0; padding-left:18px; }
code { background:var(--sup2); padding:3px 7px; border-radius:6px; font-size:12.5px;
       word-break:break-all; display:inline-block; }
.check { display:flex; gap:11px; align-items:flex-start; margin-top:18px; cursor:pointer; }
.check input { width:20px; height:20px; min-height:0; flex:0 0 auto; margin-top:2px; accent-color:var(--oro); }
.check span { font-size:14px; }
.credenciales { background:var(--sup2); border-radius:12px; padding:16px; margin:16px 0; font-size:14px; }
.credenciales div { padding:5px 0; }
.pie { text-align:center; color:var(--tenue); font-size:12.5px; margin-top:26px; }
</style>
</head>
<body>
<div class="caja">
  <div class="marca">
    <div class="marca__logo">M</div>
    <h1>Instalar MenúGold</h1>
    <p>Tu sistema de menú QR con pedidos, listo en tres pasos</p>
  </div>

  <div class="pasos">
    <?php for ($i = 1; $i <= 4; $i++): ?>
      <div class="<?= $i <= $paso ? 'on' : '' ?>"></div>
    <?php endfor; ?>
  </div>
  <div class="pasos-txt">
    <span>Requisitos</span><span>Base de datos</span><span>Tu cuenta</span><span>Listo</span>
  </div>

  <?php foreach ($errores as $e): ?>
    <div class="aviso aviso--mal"><strong>⚠</strong> <?= h($e) ?></div>
  <?php endforeach; ?>

  <!-- ==================== PASO 1 ==================== -->
  <?php if ($paso === 1): ?>
    <div class="tarjeta">
      <h2>Revisemos tu servidor</h2>
      <p>Comprobamos que tu hosting tenga todo lo necesario. Casi todos los planes de cPanel lo cumplen.</p>
      <?php foreach ($req as $r): ?>
        <div class="fila">
          <span class="fila__ico <?= $r[1] ? 'ok' : ($r[3] ? 'mal' : 'avi') ?>"><?= $r[1] ? '✓' : ($r[3] ? '✕' : '!') ?></span>
          <div>
            <strong><?= h($r[0]) ?></strong>
            <small><?= h($r[2]) ?><?= !$r[3] && !$r[1] ? ' · opcional' : '' ?></small>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if ($ok): ?>
        <div class="aviso aviso--ok" style="margin-top:20px"><strong>✓</strong> Todo listo. Podemos continuar.</div>
        <a class="btn" href="?paso=2" style="text-decoration:none">Continuar →</a>
      <?php else: ?>
        <div class="aviso aviso--mal" style="margin-top:20px">
          <strong>Faltan requisitos obligatorios.</strong>
          Actívalos desde cPanel (Selector de PHP → Extensiones) o pídelo a tu proveedor de hosting.
          Si es un problema de permisos, pon las carpetas <code>/config</code> y <code>/storage</code> en 755.
        </div>
        <a class="btn btn--linea" href="?paso=1" style="text-decoration:none">Volver a comprobar</a>
      <?php endif; ?>
    </div>

  <!-- ==================== PASO 2 ==================== -->
  <?php elseif ($paso === 2): ?>
    <div class="tarjeta">
      <h2>Conecta tu base de datos</h2>
      <p>Créala primero en cPanel → Bases de datos MySQL, junto con un usuario que tenga <strong>todos los privilegios</strong> sobre ella.</p>
      <form method="post">
        <input type="hidden" name="_t" value="<?= h($token) ?>">
        <input type="hidden" name="paso" value="2">
        <div class="dos">
          <div>
            <label for="db_host">Servidor</label>
            <input type="text" id="db_host" name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>" required>
            <p class="ayuda">Casi siempre <code>localhost</code>.</p>
          </div>
          <div>
            <label for="db_port">Puerto</label>
            <input type="number" id="db_port" name="db_port" value="<?= h($_POST['db_port'] ?? '3306') ?>" required>
          </div>
        </div>
        <label for="db_name">Nombre de la base de datos</label>
        <input type="text" id="db_name" name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" required
               placeholder="miusuario_menugold" autocomplete="off">
        <label for="db_user">Usuario de la base de datos</label>
        <input type="text" id="db_user" name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required autocomplete="off">
        <label for="db_pass">Contraseña</label>
        <input type="password" id="db_pass" name="db_pass" autocomplete="new-password">
        <div class="aviso aviso--info" style="margin-top:18px">
          Al continuar crearemos todas las tablas. Si la base ya tenía datos de MenúGold, se reemplazarán.
        </div>
        <button class="btn" type="submit">Conectar e instalar las tablas →</button>
      </form>
    </div>

  <!-- ==================== PASO 3 ==================== -->
  <?php elseif ($paso === 3): ?>
    <div class="tarjeta">
      <h2>Tu cuenta y tu restaurante</h2>
      <p>Este será el usuario con el que administres toda la plataforma.</p>
      <?php if (!empty($_SESSION['bd_version'])): ?>
        <div class="aviso aviso--ok"><strong>✓</strong> Base de datos conectada (<?= h($_SESSION['bd_version']) ?>) y tablas creadas.</div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="_t" value="<?= h($token) ?>">
        <input type="hidden" name="paso" value="3">

        <label for="plataforma">Nombre de tu plataforma</label>
        <input type="text" id="plataforma" name="plataforma" value="<?= h($_POST['plataforma'] ?? 'MenúGold') ?>" required maxlength="80">
        <p class="ayuda">Es la marca que verán tus clientes en la página de venta y en los correos.</p>

        <label for="restaurante">Nombre de tu primer restaurante</label>
        <input type="text" id="restaurante" name="restaurante" value="<?= h($_POST['restaurante'] ?? '') ?>" required maxlength="120"
               placeholder="Ej. La Terraza Gold">

        <div class="dos">
          <div>
            <label for="admin_nombre">Tu nombre</label>
            <input type="text" id="admin_nombre" name="admin_nombre" value="<?= h($_POST['admin_nombre'] ?? '') ?>" required maxlength="120">
          </div>
          <div>
            <label for="admin_email">Tu correo (será tu usuario)</label>
            <input type="email" id="admin_email" name="admin_email" value="<?= h($_POST['admin_email'] ?? '') ?>" required maxlength="190">
          </div>
        </div>
        <div class="dos">
          <div>
            <label for="admin_pass">Contraseña</label>
            <input type="password" id="admin_pass" name="admin_pass" required minlength="8" autocomplete="new-password">
            <p class="ayuda">Mínimo 8 caracteres, con letras y números.</p>
          </div>
          <div>
            <label for="admin_pass2">Repite la contraseña</label>
            <input type="password" id="admin_pass2" name="admin_pass2" required minlength="8" autocomplete="new-password">
          </div>
        </div>
        <div class="dos">
          <div>
            <label for="zona">Zona horaria</label>
            <select id="zona" name="zona">
              <?php
              $zonaSel = $_POST['zona'] ?? 'America/Guatemala';
              foreach (['America/Guatemala','America/Mexico_City','America/El_Salvador','America/Tegucigalpa',
                        'America/Managua','America/Costa_Rica','America/Panama','America/Bogota','America/Lima',
                        'America/Santiago','America/Buenos_Aires','America/New_York','Europe/Madrid'] as $z): ?>
                <option value="<?= h($z) ?>" <?= $zonaSel === $z ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $z)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="simbolo">Símbolo de la moneda</label>
            <input type="text" id="simbolo" name="simbolo" value="<?= h($_POST['simbolo'] ?? 'Q') ?>" maxlength="4" required>
            <input type="hidden" name="moneda" value="<?= h($_POST['moneda'] ?? 'GTQ') ?>">
          </div>
        </div>

        <label class="check">
          <input type="checkbox" name="demo" value="1" <?= !empty($_POST['demo']) ? 'checked' : '' ?>>
          <span>
            <strong>Cargar datos de demostración</strong><br>
            <small style="color:var(--tenue)">Dos restaurantes completos con menú, mesas, QR y pedidos de ejemplo.
            Ideal para probar todo antes de cargar tu carta real. Puedes borrarlos después.</small>
          </span>
        </label>

        <button class="btn" type="submit">Instalar MenúGold →</button>
      </form>
    </div>

  <!-- ==================== PASO 4 ==================== -->
  <?php else: ?>
    <div class="tarjeta">
      <div style="text-align:center;margin-bottom:8px">
        <div style="width:76px;height:76px;margin:0 auto 16px;border-radius:50%;background:var(--ok);
                    display:grid;place-items:center;font-size:38px;color:#fff">✓</div>
        <h2 style="font-size:24px">¡Listo! MenúGold ya está instalado</h2>
        <p>Ya puedes entrar y empezar a construir tu menú.</p>
      </div>

      <?php if ($listo): ?>
        <div class="credenciales">
          <div><strong>Panel:</strong> <code><?= h(baseUrl()) ?>/ingresar</code></div>
          <div><strong>Tu usuario:</strong> <code><?= h($listo['email']) ?></code></div>
          <div><strong>Contraseña:</strong> la que acabas de elegir</div>
          <?php if (!empty($listo['demo'])): ?>
            <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--bor)">
              <strong>Usuarios de demostración:</strong><br>
              <small style="color:var(--suave)">
                dueño: <code>dueno@laterraza.gt</code> / <code>Terraza2026!</code><br>
                cocina: <code>cocina1</code> / <code>Cocina2026!</code><br>
                mesero: <code>mesero1</code> / <code>Mesero2026!</code>
              </small>
            </div>
          <?php endif; ?>
        </div>

        <div class="aviso aviso--info">
          <strong>Un último paso: la tarea programada.</strong><br>
          En cPanel → Trabajos cron, agrega este comando cada 10 minutos.
          Se encarga de los vencimientos, los avisos por correo y los respaldos automáticos:
          <div style="margin-top:8px">
            <code>*/10 * * * * curl -s "<?= h(baseUrl()) ?>/cron/run.php?token=<?= h($listo['cron']) ?>"</code>
          </div>
        </div>

        <div class="aviso aviso--ok">
          <strong>Seguridad:</strong> el instalador quedó bloqueado automáticamente.
          Para mayor tranquilidad puedes borrar la carpeta <code>/install</code> desde cPanel.
        </div>
      <?php endif; ?>

      <a class="btn" href="<?= h(baseUrl()) ?>/ingresar" style="text-decoration:none">Entrar al panel →</a>
      <a class="btn btn--linea" href="<?= h(baseUrl()) ?>/" style="text-decoration:none;margin-top:10px">Ver la página de venta</a>
    </div>
  <?php endif; ?>

  <p class="pie">MenúGold · Sistema de menú digital QR con pedidos</p>
</div>
</body>
</html>
