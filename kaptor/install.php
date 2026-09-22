<?php
/**
 * Kaptor - Instalador web.
 *
 * Crea config/config.php, la base de datos (si el hosting lo permite), todas
 * las tablas, los ajustes por defecto y la cuenta de administrador.
 * Cuando termina se bloquea solo: para volver a usarlo hay que borrar
 * storage/instalado.lock.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
@set_time_limit(180);

define('CR_RAIZ', __DIR__);
define('CR_VERSION', '6.0.1');
const PHP_MINIMO = '8.0.0';

$rutaConfig = CR_RAIZ . '/config/config.php';
$rutaLock   = CR_RAIZ . '/storage/instalado.lock';
$rutaSchema = CR_RAIZ . '/database/schema.sql';

session_start();

// ---------------------------------------------------------------------------
//  BLOQUEO: si ya está instalado no se puede volver a ejecutar
// ---------------------------------------------------------------------------
$yaInstalado = is_file($rutaLock) && is_file($rutaConfig);

// ---------------------------------------------------------------------------
//  COMPROBACIÓN DE REQUISITOS
// ---------------------------------------------------------------------------
/** @return array<int,array{nombre:string,ok:bool,detalle:string,critico:bool}> */
function cr_requisitos(): array
{
    $r = [];
    $r[] = [
        'nombre'  => 'PHP ' . PHP_MINIMO . ' o superior',
        'ok'      => version_compare(PHP_VERSION, PHP_MINIMO, '>='),
        'detalle' => 'Versión actual: ' . PHP_VERSION,
        'critico' => true,
    ];
    foreach ([
        'pdo_mysql' => 'Conexión con MySQL / MariaDB',
        'curl'      => 'Descarga de páginas web',
        'mbstring'  => 'Textos en UTF-8 y acentos',
        'json'      => 'Comunicación con el navegador',
    ] as $ext => $para) {
        $r[] = [
            'nombre'  => 'Extensión ' . $ext,
            'ok'      => extension_loaded($ext),
            'detalle' => $para,
            'critico' => true,
        ];
    }
    foreach (['zip' => 'Exportación a Excel (.xlsx)', 'openssl' => 'Descarga de webs con HTTPS', 'dom' => 'Análisis de HTML y auditor web'] as $ext => $para) {
        $r[] = [
            'nombre'  => 'Extensión ' . $ext,
            'ok'      => extension_loaded($ext),
            'detalle' => $para . ' (recomendada)',
            'critico' => false,
        ];
    }
    foreach (['config' => 'Guardar la configuración', 'storage' => 'Sesiones, registros y archivos'] as $dir => $para) {
        $ruta = CR_RAIZ . '/' . $dir;
        if (!is_dir($ruta)) { @mkdir($ruta, 0755, true); }
        $r[] = [
            'nombre'  => 'Carpeta /' . $dir . ' con permiso de escritura',
            'ok'      => is_dir($ruta) && is_writable($ruta),
            'detalle' => $para . ' (permisos 755 o 775)',
            'critico' => true,
        ];
    }
    $r[] = [
        'nombre'  => 'Archivo database/schema.sql',
        'ok'      => is_file(CR_RAIZ . '/database/schema.sql'),
        'detalle' => 'Definición de las tablas',
        'critico' => true,
    ];
    return $r;
}

$requisitos = cr_requisitos();
$faltaAlgo  = false;
foreach ($requisitos as $req) {
    if ($req['critico'] && !$req['ok']) { $faltaAlgo = true; }
}

// ---------------------------------------------------------------------------
//  PROCESO DE INSTALACIÓN
// ---------------------------------------------------------------------------
$errores = [];
$hecho   = false;
$datos   = [
    'db_host'   => $_POST['db_host']   ?? 'localhost',
    'db_puerto' => $_POST['db_puerto'] ?? '3306',
    'db_nombre' => $_POST['db_nombre'] ?? '',
    'db_usuario'=> $_POST['db_usuario']?? '',
    'db_clave'  => $_POST['db_clave']  ?? '',
    'crear_db'  => isset($_POST['crear_db']),
    'admin_usuario' => $_POST['admin_usuario'] ?? 'admin',
    'admin_email'   => $_POST['admin_email']   ?? '',
    'admin_clave'   => $_POST['admin_clave']   ?? '',
    'sitio_nombre'  => $_POST['sitio_nombre']  ?? 'Kaptor',
    'zona'          => $_POST['zona']          ?? 'America/Guatemala',
];

if (!$yaInstalado && !$faltaAlgo && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['instalar'])) {

    // --- Validación básica del formulario ---------------------------------
    if (!hash_equals((string) ($_SESSION['inst_csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        $errores[] = 'La sesión caducó. Recarga la página e inténtalo de nuevo.';
    }
    if (trim($datos['db_nombre']) === '')  { $errores[] = 'Escribe el nombre de la base de datos.'; }
    if (trim($datos['db_usuario']) === '') { $errores[] = 'Escribe el usuario de la base de datos.'; }
    if (!preg_match('/^[a-zA-Z0-9._\-]{3,64}$/', $datos['admin_usuario'])) {
        $errores[] = 'El usuario administrador debe tener entre 3 y 64 caracteres (letras, números, punto, guion o guion bajo).';
    }
    if (!filter_var($datos['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo del administrador no es válido.';
    }
    if (strlen($datos['admin_clave']) < 8) {
        $errores[] = 'La contraseña del administrador debe tener al menos 8 caracteres.';
    }
    if (!in_array($datos['zona'], timezone_identifiers_list(), true)) {
        $datos['zona'] = 'UTC';
    }

    // --- Conexión y creación de la base de datos ---------------------------
    $pdo = null;
    if (!$errores) {
        $host   = trim($datos['db_host']);
        $puerto = trim($datos['db_puerto']) !== '' ? trim($datos['db_puerto']) : '3306';
        $nombre = trim($datos['db_nombre']);

        try {
            $pdo = new PDO(
                "mysql:host=$host;port=$puerto;charset=utf8mb4",
                $datos['db_usuario'],
                $datos['db_clave'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );

            if ($datos['crear_db']) {
                // Muchos hosting compartidos no lo permiten: si falla, seguimos
                // e intentamos usar la base de datos creada desde el panel.
                try {
                    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $nombre)
                        . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                } catch (PDOException $e) {
                    // se ignora a propósito
                }
            }
            $pdo->exec('USE `' . str_replace('`', '', $nombre) . '`');
        } catch (PDOException $e) {
            $errores[] = 'No se pudo conectar con la base de datos: ' . $e->getMessage();
            $pdo = null;
        }
    }

    // --- Crear tablas, ajustes y administrador ------------------------------
    if (!$errores && $pdo instanceof PDO) {
        try {
            // 1) Tablas
            $sql = (string) file_get_contents($rutaSchema);
            $sql = preg_replace('~^\s*--.*$~m', '', $sql) ?? $sql;
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $sentencia) {
                if ($sentencia !== '') { $pdo->exec($sentencia); }
            }

            // 2) Ajustes por defecto
            require_once CR_RAIZ . '/includes/Ajustes.php';
            $defectos = Ajustes::porDefecto();
            $defectos['sitio_nombre'] = trim($datos['sitio_nombre']) !== '' ? trim($datos['sitio_nombre']) : 'Kaptor';
            $defectos['cron_clave']   = bin2hex(random_bytes(16));   // para automatizar el envío
            $defectos['pie_texto']    = '© ' . date('Y') . ' ' . $defectos['sitio_nombre']
                . '. Uso responsable: extrae solo datos públicos y respeta la legislación de protección de datos.';
            $ins = $pdo->prepare('INSERT INTO `cr_ajustes` (`clave`,`valor`,`actualizado`) VALUES (?,?,NOW())
                                  ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`)');
            foreach ($defectos as $clave => $valor) { $ins->execute([$clave, (string) $valor]); }

            // 3) Cuenta de administrador
            $existe = $pdo->prepare('SELECT COUNT(*) FROM `cr_usuarios` WHERE `usuario` = ? OR `email` = ?');
            $existe->execute([$datos['admin_usuario'], strtolower($datos['admin_email'])]);
            if ((int) $existe->fetchColumn() === 0) {
                $pdo->prepare('INSERT INTO `cr_usuarios` (`usuario`,`email`,`nombre`,`clave_hash`,`rol`,`activo`,`creado`)
                               VALUES (?,?,?,?,\'admin\',1,NOW())')
                    ->execute([
                        $datos['admin_usuario'],
                        strtolower($datos['admin_email']),
                        'Administrador',
                        password_hash($datos['admin_clave'], PASSWORD_DEFAULT),
                    ]);
            }

            // 4) config/config.php
            $plantilla = (string) file_get_contents(CR_RAIZ . '/config/config.sample.php');
            $config = str_replace(
                ['{{DB_HOST}}', '{{DB_NOMBRE}}', '{{DB_USUARIO}}', '{{DB_CLAVE}}', '{{DB_PUERTO}}',
                 '{{CLAVE_APP}}', '{{URL_BASE}}', '{{ZONA_HORARIA}}'],
                [
                    addslashes(trim($datos['db_host'])),
                    addslashes(trim($datos['db_nombre'])),
                    addslashes(trim($datos['db_usuario'])),
                    addslashes((string) $datos['db_clave']),
                    addslashes(trim($datos['db_puerto']) !== '' ? trim($datos['db_puerto']) : '3306'),
                    bin2hex(random_bytes(24)),
                    '',
                    addslashes($datos['zona']),
                ],
                $plantilla
            );
            if (@file_put_contents($rutaConfig, $config) === false) {
                throw new RuntimeException('No se pudo escribir config/config.php. Revisa los permisos de la carpeta config/.');
            }
            @chmod($rutaConfig, 0644);

            // 5) Carpetas de trabajo y bloqueo del instalador
            foreach (['storage/logs', 'storage/cache', 'storage/uploads', 'storage/sessions'] as $dir) {
                if (!is_dir(CR_RAIZ . '/' . $dir)) { @mkdir(CR_RAIZ . '/' . $dir, 0755, true); }
            }
            @file_put_contents(CR_RAIZ . '/storage/.htaccess', "Require all denied\nDeny from all\n");
            @file_put_contents($rutaLock, 'Instalado el ' . date('d/m/Y H:i:s') . ' - Kaptor ' . CR_VERSION . "\n");

            $hecho = true;
            $yaInstalado = true;
        } catch (Throwable $e) {
            $errores[] = 'Error durante la instalación: ' . $e->getMessage();
        }
    }
}

if (empty($_SESSION['inst_csrf'])) { $_SESSION['inst_csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['inst_csrf'];

/** Escape corto para esta página. */
function h(?string $t): string { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalación de Kaptor</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/fonts/fonts.css">
<style>
:root{
  --obsidiana:#07080A; --carbon:#0E1014; --panel:#14171D; --borde:#232831;
  --oro:#D8B36A; --oro-claro:#F0DCAE; --neon:#6EF3A5; --texto:#EDEAE3; --suave:#9AA0AC;
  --error:#FF6B6B;
}
*{box-sizing:border-box}
body{margin:0;background:var(--obsidiana);color:var(--texto);font-family:'Manrope',system-ui,-apple-system,sans-serif;
     font-size:15px;line-height:1.6;
     background-image:radial-gradient(900px 500px at 50% -10%, rgba(216,179,106,.10), transparent 60%),
                      radial-gradient(700px 400px at 90% 100%, rgba(110,243,165,.06), transparent 60%);}
.caja{max-width:820px;margin:0 auto;padding:40px 20px 80px}
.marca{display:flex;align-items:center;gap:14px;margin-bottom:8px}
.marca svg{width:46px;height:46px;flex:none}
h1{font-family:'Cormorant Garamond',Georgia,serif;font-size:clamp(28px,5vw,40px);margin:0;font-weight:700;letter-spacing:-.02em}
h1 span{color:var(--oro)}
.lema{color:var(--suave);margin:0 0 28px}
.panel{background:linear-gradient(180deg,var(--panel),var(--carbon));border:1px solid var(--borde);
       border-radius:18px;padding:26px;margin-bottom:22px;box-shadow:0 22px 60px rgba(0,0,0,.45)}
h2{font-family:'Cormorant Garamond',Georgia,serif;font-size:20px;margin:0 0 4px;font-weight:600}
h2 small{display:block;font-family:'Manrope',sans-serif;font-size:13px;color:var(--suave);font-weight:400;margin-top:4px}
.req{display:flex;justify-content:space-between;gap:14px;padding:10px 0;border-bottom:1px dashed var(--borde);font-size:14px}
.req:last-child{border-bottom:0}
.req b{font-weight:500}
.req em{color:var(--suave);font-style:normal;font-size:13px;display:block}
.si{color:var(--neon);font-weight:600;white-space:nowrap}
.no{color:var(--error);font-weight:600;white-space:nowrap}
.op{color:var(--oro);font-weight:600;white-space:nowrap}
label{display:block;font-size:13px;color:var(--suave);margin:14px 0 6px;font-weight:500}
input[type=text],input[type=password],input[type=email],select{
  width:100%;padding:12px 14px;background:#0B0D11;border:1px solid var(--borde);border-radius:11px;
  color:var(--texto);font-family:inherit;font-size:15px;transition:border-color .2s, box-shadow .2s}
input:focus,select:focus{outline:none;border-color:var(--oro);box-shadow:0 0 0 3px rgba(216,179,106,.15)}
.fila{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:620px){.fila{grid-template-columns:1fr}}
.check{display:flex;gap:10px;align-items:flex-start;margin-top:16px;font-size:14px;color:var(--suave)}
.check input{margin-top:3px}
button{margin-top:26px;width:100%;padding:15px;border:0;border-radius:12px;cursor:pointer;
  background:linear-gradient(135deg,var(--oro),var(--oro-claro));color:#1A1206;font-weight:700;font-size:16px;
  font-family:inherit;letter-spacing:.01em;transition:transform .15s, box-shadow .2s}
button:hover{transform:translateY(-2px);box-shadow:0 14px 34px rgba(216,179,106,.32)}
.aviso{padding:14px 16px;border-radius:12px;margin-bottom:16px;font-size:14px}
.aviso.mal{background:rgba(255,107,107,.10);border:1px solid rgba(255,107,107,.35);color:#FFB3B3}
.aviso.bien{background:rgba(110,243,165,.08);border:1px solid rgba(110,243,165,.32);color:var(--neon)}
.aviso ul{margin:8px 0 0 18px;padding:0}
code{background:#0B0D11;border:1px solid var(--borde);padding:2px 7px;border-radius:6px;
     font-family:'IBM Plex Mono',monospace;font-size:13px;color:var(--oro-claro)}
.pasos{counter-reset:p;list-style:none;padding:0;margin:18px 0 0}
.pasos li{counter-increment:p;position:relative;padding:10px 0 10px 40px;font-size:14.5px}
.pasos li::before{content:counter(p);position:absolute;left:0;top:9px;width:26px;height:26px;border-radius:50%;
  background:rgba(216,179,106,.14);border:1px solid rgba(216,179,106,.4);color:var(--oro);
  display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700}
.btn-enlace{display:inline-block;margin-top:18px;padding:13px 26px;border-radius:12px;text-decoration:none;
  background:linear-gradient(135deg,var(--oro),var(--oro-claro));color:#1A1206;font-weight:700}
.pie{text-align:center;color:var(--suave);font-size:13px;margin-top:30px}
</style>
</head>
<body>
<div class="caja">

  <div class="marca">
    <svg viewBox="0 0 64 64" fill="none" aria-hidden="true">
      <circle cx="32" cy="32" r="29" stroke="#D8B36A" stroke-width="1.6" opacity=".55"/>
      <circle cx="32" cy="32" r="19" stroke="#D8B36A" stroke-width="1.2" opacity=".35"/>
      <circle cx="32" cy="32" r="9"  stroke="#D8B36A" stroke-width="1" opacity=".25"/>
      <path d="M32 32L32 3A29 29 0 0 1 58 19Z" fill="#6EF3A5" opacity=".18"/>
      <path d="M32 32 58 19" stroke="#6EF3A5" stroke-width="1.6"/>
      <circle cx="46" cy="24" r="3.2" fill="#6EF3A5"/>
    </svg>
    <div>
      <h1>Kap<span>tor</span></h1>
      <p class="lema">Instalación guiada · versión <?= h(CR_VERSION) ?></p>
    </div>
  </div>

<?php if ($yaInstalado && !$hecho): ?>

  <div class="panel">
    <h2>Kaptor ya está instalado</h2>
    <div class="aviso bien" style="margin-top:14px">
      Por seguridad el instalador está bloqueado.
    </div>
    <p>Si necesitas reinstalar, borra estos dos archivos por FTP y vuelve a cargar esta página:</p>
    <p><code>storage/instalado.lock</code> &nbsp; <code>config/config.php</code></p>
    <p style="color:var(--suave);font-size:14px;margin-top:18px">
      Recuerda: cuando termines la instalación, <b>elimina install.php</b> del servidor.
    </p>
    <a class="btn-enlace" href="index.php">Ir a Kaptor</a>
    <a class="btn-enlace" style="background:transparent;border:1px solid var(--borde);color:var(--texto)" href="admin/login.php">Panel de administración</a>
  </div>

<?php elseif ($hecho): ?>

  <div class="panel">
    <h2>Instalación completada</h2>
    <div class="aviso bien" style="margin-top:14px">
      Todo listo. La base de datos, las tablas y tu cuenta de administrador se han creado correctamente.
    </div>
    <ol class="pasos">
      <li><b>Borra el archivo <code>install.php</code></b> del servidor (es el último paso de seguridad).</li>
      <li>Entra al panel con el usuario <code><?= h($datos['admin_usuario']) ?></code> y la contraseña que acabas de elegir.</li>
      <li>Personaliza el logo, los colores y los textos en <b>Ajustes</b>.</li>
      <li>Empieza a extraer correos desde la portada.</li>
    </ol>
    <a class="btn-enlace" href="index.php">Abrir Kaptor</a>
    <a class="btn-enlace" style="background:transparent;border:1px solid var(--borde);color:var(--texto)" href="admin/login.php">Entrar al panel</a>
  </div>

<?php else: ?>

  <div class="panel">
    <h2>1. Requisitos del servidor
      <small>Comprobación automática de tu hosting</small>
    </h2>
    <?php foreach ($requisitos as $req): ?>
      <div class="req">
        <span><b><?= h($req['nombre']) ?></b><em><?= h($req['detalle']) ?></em></span>
        <span class="<?= $req['ok'] ? 'si' : ($req['critico'] ? 'no' : 'op') ?>">
          <?= $req['ok'] ? '✓ Correcto' : ($req['critico'] ? '✕ Falta' : '○ Opcional') ?>
        </span>
      </div>
    <?php endforeach; ?>
    <?php if ($faltaAlgo): ?>
      <div class="aviso mal" style="margin-top:18px">
        Hay requisitos obligatorios sin cumplir. Corrigelos (o pideselo a tu proveedor de hosting) y recarga esta página.
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$faltaAlgo): ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

    <?php if ($errores): ?>
      <div class="aviso mal">
        <b>No se pudo completar la instalación:</b>
        <ul><?php foreach ($errores as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <div class="panel">
      <h2>2. Base de datos
        <small>Los datos que te dio tu hosting (cPanel &rarr; Bases de datos MySQL)</small>
      </h2>
      <div class="fila">
        <div>
          <label for="db_host">Servidor</label>
          <input type="text" id="db_host" name="db_host" value="<?= h($datos['db_host']) ?>" required>
        </div>
        <div>
          <label for="db_puerto">Puerto</label>
          <input type="text" id="db_puerto" name="db_puerto" value="<?= h($datos['db_puerto']) ?>">
        </div>
      </div>
      <label for="db_nombre">Nombre de la base de datos</label>
      <input type="text" id="db_nombre" name="db_nombre" value="<?= h($datos['db_nombre']) ?>" required placeholder="micuenta_kaptor">
      <div class="fila">
        <div>
          <label for="db_usuario">Usuario</label>
          <input type="text" id="db_usuario" name="db_usuario" value="<?= h($datos['db_usuario']) ?>" required>
        </div>
        <div>
          <label for="db_clave">Contraseña</label>
          <input type="password" id="db_clave" name="db_clave" value="<?= h((string) $datos['db_clave']) ?>">
        </div>
      </div>
      <label class="check">
        <input type="checkbox" name="crear_db" <?= $datos['crear_db'] ? 'checked' : '' ?>>
        <span>Crear la base de datos si no existe. Actívalo solo si tu usuario tiene permiso; en la mayoría de hosting compartidos hay que crearla antes desde el panel.</span>
      </label>
    </div>

    <div class="panel">
      <h2>3. Cuenta de administrador
        <small>Con ella entraras al panel para configurarlo todo</small>
      </h2>
      <div class="fila">
        <div>
          <label for="admin_usuario">Usuario</label>
          <input type="text" id="admin_usuario" name="admin_usuario" value="<?= h($datos['admin_usuario']) ?>" required>
        </div>
        <div>
          <label for="admin_email">Correo electrónico</label>
          <input type="email" id="admin_email" name="admin_email" value="<?= h($datos['admin_email']) ?>" required>
        </div>
      </div>
      <label for="admin_clave">Contraseña (mínimo 8 caracteres)</label>
      <input type="password" id="admin_clave" name="admin_clave" required minlength="8">
    </div>

    <div class="panel">
      <h2>4. Tu sitio
        <small>Se puede cambiar después desde el panel</small>
      </h2>
      <div class="fila">
        <div>
          <label for="sitio_nombre">Nombre del sitio</label>
          <input type="text" id="sitio_nombre" name="sitio_nombre" value="<?= h($datos['sitio_nombre']) ?>">
        </div>
        <div>
          <label for="zona">Zona horaria</label>
          <select id="zona" name="zona">
            <?php
            $zonas = timezone_identifiers_list();
            $preferidas = ['America/Guatemala','America/Mexico_City','America/Bogota','America/Lima','America/Santiago',
                           'America/Argentina/Buenos_Aires','Europe/Madrid','America/New_York','UTC'];
            foreach (array_merge($preferidas, array_diff($zonas, $preferidas)) as $z): ?>
              <option value="<?= h($z) ?>" <?= $z === $datos['zona'] ? 'selected' : '' ?>><?= h($z) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <button type="submit" name="instalar" value="1">Instalar Kaptor</button>
    </div>
  </form>
  <?php endif; ?>

<?php endif; ?>

  <p class="pie">Kaptor <?= h(CR_VERSION) ?> · Extractor de correos y WhatsApp</p>
</div>
</body>
</html>
