<?php
/**
 * Asistente de instalacion por navegador.
 *
 * Permite instalar el sistema sin usar la Terminal: comprueba los requisitos,
 * escribe config/config.php, crea las tablas y da de alta al administrador.
 *
 * Por seguridad se bloquea solo en cuanto la instalacion esta terminada: si ya
 * existe la configuracion y hay usuarios creados, deja de funcionar. Aun asi,
 * BORRE ESTE ARCHIVO cuando termine.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

const RAIZ         = __DIR__ . '/..';
const RUTA_CONFIG  = RAIZ . '/config/config.php';
const RUTA_EJEMPLO = RAIZ . '/config/config.example.php';

session_start();

/** @return list<array{ok:bool,titulo:string,detalle:string,critico:bool}> */
function requisitos(): array
{
    $lista = [];

    $version = PHP_VERSION;
    $lista[] = [
        'ok'      => version_compare($version, '8.0.0', '>='),
        'titulo'  => "PHP 8.0 o superior (tiene {$version})",
        'detalle' => 'cPanel → Select PHP Version → elija 8.0 o superior',
        'critico' => true,
    ];

    foreach (['dom' => 'genera el XML del DTE',
              'curl' => 'habla con el certificador',
              'pdo_mysql' => 'conecta con MySQL',
              'mbstring' => 'maneja acentos y ñ',
              'openssl' => 'cifra las credenciales'] as $ext => $para) {
        $lista[] = [
            'ok'      => extension_loaded($ext),
            'titulo'  => "Extensión {$ext} — {$para}",
            'detalle' => 'cPanel → Select PHP Version → Extensions → marque ' . $ext,
            'critico' => true,
        ];
    }

    $storage = RAIZ . '/storage';
    $lista[] = [
        'ok'      => is_dir($storage) && is_writable($storage),
        'titulo'  => 'La carpeta storage/ se puede escribir',
        'detalle' => 'Administrador de archivos → clic derecho en storage → Permisos → 770, '
                   . 'marcando "Aplicar recursivamente"',
        'critico' => true,
    ];

    $config = RAIZ . '/config';
    $lista[] = [
        'ok'      => is_writable($config) || is_writable(RUTA_CONFIG),
        'titulo'  => 'La carpeta config/ se puede escribir',
        'detalle' => 'Solo hace falta durante la instalación. Si no puede, el asistente le dará '
                   . 'el contenido para que lo pegue a mano.',
        'critico' => false,
    ];

    $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $lista[] = [
        'ok'      => $https,
        'titulo'  => 'El sitio abre por HTTPS',
        'detalle' => 'cPanel → SSL/TLS Status → Run AutoSSL. Puede instalar sin esto, pero '
                   . 'ACTÍVELO antes de facturar de verdad: se manejan datos fiscales.',
        'critico' => false,
    ];

    return $lista;
}

function yaInstalado(): bool
{
    if (!is_file(RUTA_CONFIG)) {
        return false;
    }

    try {
        require_once RAIZ . '/src/autoload.php';
        \Fel\Core\Config::cargar(RUTA_CONFIG);

        return (new \Fel\Repositorio\UsuarioRepositorio())->total() > 0;
    } catch (\Throwable) {
        return false;
    }
}

/** @param array<string,string> $datos */
function contenidoConfig(array $datos): string
{
    $plantilla = (string) file_get_contents(RUTA_EJEMPLO);
    $escapar   = static fn (string $v): string => str_replace(["\\", "'"], ["\\\\", "\\'"], $v);

    $reemplazos = [
        "'nombre'  => 'usuario_fel',"    => "'nombre'  => '" . $escapar($datos['db_nombre']) . "',",
        "'usuario' => 'usuario_fel',"    => "'usuario' => '" . $escapar($datos['db_usuario']) . "',",
        "'clave'   => 'CAMBIE_ESTA_CLAVE'," => "'clave'   => '" . $escapar($datos['db_clave']) . "',",
        "'host'    => 'localhost',"      => "'host'    => '" . $escapar($datos['db_host']) . "',",
        "'clave_aplicacion' => 'CAMBIE_ESTA_CLAVE_ALEATORIA',"
            => "'clave_aplicacion' => '" . $escapar($datos['clave_app']) . "',",
        "'nombre'         => 'Facturación FEL'," => "'nombre'         => '" . $escapar($datos['app_nombre']) . "',",
    ];

    return strtr($plantilla, $reemplazos);
}

// ----------------------------------------------------------------- acciones

$paso     = (int) ($_GET['paso'] ?? 1);
$errores  = [];
$avisos   = [];
$configEscrita = null;

if ($paso === 9 || yaInstalado()) {
    $paso = yaInstalado() ? 9 : $paso;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $paso !== 9) {
    $datos = [
        'db_host'    => trim((string) ($_POST['db_host'] ?? 'localhost')),
        'db_nombre'  => trim((string) ($_POST['db_nombre'] ?? '')),
        'db_usuario' => trim((string) ($_POST['db_usuario'] ?? '')),
        'db_clave'   => (string) ($_POST['db_clave'] ?? ''),
        'app_nombre' => trim((string) ($_POST['app_nombre'] ?? 'Facturación FEL')),
        'clave_app'  => bin2hex(random_bytes(32)),
    ];

    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $nombre  = trim((string) ($_POST['nombre'] ?? ''));
    $clave   = (string) ($_POST['clave'] ?? '');

    if ($datos['db_nombre'] === '' || $datos['db_usuario'] === '') {
        $errores[] = 'Indique el nombre de la base de datos y el usuario.';
    }
    if ($usuario === '') {
        $errores[] = 'Indique el usuario administrador.';
    }
    if (strlen($clave) < 10) {
        $errores[] = 'La contraseña del administrador debe tener al menos 10 caracteres.';
    }

    // 1. Probar la conexión antes de escribir nada.
    if ($errores === []) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $datos['db_host'], $datos['db_nombre']);
            $pdo = new PDO($dsn, $datos['db_usuario'], $datos['db_clave'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\PDOException $error) {
            $errores[] = 'No se pudo conectar a la base de datos: ' . $error->getMessage();
            $errores[] = 'Revise que el usuario esté agregado a la base con TODOS LOS PRIVILEGIOS '
                       . 'y que el nombre lleve el prefijo de su cuenta de cPanel.';
        }
    }

    // 2. Escribir config.php
    if ($errores === []) {
        $contenido = contenidoConfig($datos);

        if (@file_put_contents(RUTA_CONFIG, $contenido) === false) {
            $configEscrita = $contenido;
            $errores[] = 'No se pudo escribir config/config.php. Créelo a mano con el contenido '
                       . 'que aparece abajo y vuelva a enviar el formulario.';
        }
    }

    // 3. Crear tablas y el administrador
    if ($errores === []) {
        try {
            require_once RAIZ . '/src/autoload.php';
            \Fel\Core\Config::cargar(RUTA_CONFIG);
            date_default_timezone_set((string) \Fel\Core\Config::get('zona_horaria', 'America/Guatemala'));

            \Fel\Core\Esquema::aplicar($pdo, RAIZ . '/db/schema.sql');

            foreach (['storage', 'storage/xml', 'storage/logs'] as $carpeta) {
                $ruta = RAIZ . '/' . $carpeta;
                if (!is_dir($ruta)) {
                    @mkdir($ruta, 0770, true);
                }
            }

            $usuarios = new \Fel\Repositorio\UsuarioRepositorio();
            if ($usuarios->total() === 0) {
                $usuarios->crear($usuario, $clave, $nombre !== '' ? $nombre : $usuario,
                                 \Fel\Repositorio\UsuarioRepositorio::SUPERADMIN);
            } else {
                $avisos[] = 'Ya existían usuarios: no se creó uno nuevo.';
            }

            $_SESSION['instalado'] = true;
            header('Location: instalar.php?paso=9');
            exit;
        } catch (\Throwable $error) {
            $errores[] = 'Error al crear las tablas: ' . $error->getMessage();
        }
    }
}

$comprobaciones = requisitos();
$bloqueantes    = array_filter($comprobaciones, static fn (array $c): bool => $c['critico'] && !$c['ok']);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalación — Sistema de Facturación FEL</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#f2f4f7;color:#16202c;
     font:15px/1.55 "Segoe UI",Roboto,Helvetica,Arial,sans-serif;padding:24px 16px}
.caja{max-width:720px;margin:0 auto;background:#fff;border:1px solid #dfe4ea;
      border-radius:8px;padding:28px 32px;box-shadow:0 1px 6px rgba(0,0,0,.06)}
h1{font-size:22px;margin:0 0 4px;color:#0f5f8a}
.sub{color:#5b6875;margin:0 0 22px;font-size:14px}
h2{font-size:16px;margin:26px 0 12px;padding-bottom:6px;border-bottom:2px solid #0f5f8a;color:#0f5f8a}
ul.chequeo{list-style:none;padding:0;margin:0 0 18px}
ul.chequeo li{padding:9px 0 9px 30px;border-bottom:1px solid #eef1f4;position:relative;font-size:14px}
ul.chequeo li::before{position:absolute;left:0;top:9px;font-weight:700;font-size:15px}
li.si::before{content:"\2713";color:#1a7f4b}
li.no::before{content:"\2715";color:#b32431}
li.op::before{content:"!";color:#8a6100;left:6px}
li small{display:block;color:#5b6875;font-size:12.5px;margin-top:2px}
label{display:block;font-size:12px;text-transform:uppercase;letter-spacing:.05em;
      color:#5b6875;margin:0 0 4px}
input{width:100%;padding:9px 11px;border:1px solid #c9d2db;border-radius:4px;font:inherit}
input:focus{outline:2px solid #e6f1f7;border-color:#0f5f8a}
.campo{margin-bottom:14px}
.fila{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.boton{display:inline-block;background:#0f5f8a;color:#fff;border:0;border-radius:4px;
       padding:12px 22px;font:inherit;font-weight:600;cursor:pointer;text-decoration:none}
.boton:hover{background:#0c4d70}
.boton[disabled]{background:#9fb0bf;cursor:not-allowed}
.msg{padding:11px 15px;border-radius:5px;margin-bottom:12px;font-size:14px;border:1px solid}
.err{background:#fdeaec;border-color:#f3c3c8;color:#8f1c27}
.avi{background:#fdf3dc;border-color:#ecd79a;color:#7a5a00}
.oki{background:#e4f5eb;border-color:#bde3cd;color:#1a7f4b}
.ayuda{font-size:12.5px;color:#5b6875;margin:-8px 0 14px}
pre{background:#16202c;color:#e8eef4;padding:12px;border-radius:5px;font-size:11px;
    overflow:auto;max-height:260px;white-space:pre-wrap;word-break:break-all}
.paso{display:flex;gap:10px;align-items:center;margin-bottom:20px;font-size:13px;color:#5b6875}
.paso span{width:26px;height:26px;border-radius:50%;background:#dfe4ea;color:#5b6875;
           display:flex;align-items:center;justify-content:center;font-weight:700}
.paso span.on{background:#0f5f8a;color:#fff}
</style>
</head>
<body>
<div class="caja">

<?php if ($paso === 9): ?>

  <h1>Instalación terminada</h1>
  <p class="sub">El sistema ya está listo para usarse.</p>

  <div class="msg oki">
    Las tablas se crearon y el usuario administrador quedó activo.
  </div>

  <div class="msg avi">
    <strong>Ahora borre este archivo:</strong> <code>public/instalar.php</code><br>
    Desde el Administrador de archivos de cPanel, clic derecho → Eliminar.
  </div>

  <h2>Siguientes pasos</h2>
  <ol style="padding-left:20px;font-size:14px">
    <li>Entre al sistema e ingrese con su usuario administrador.</li>
    <li>Vaya a <strong>Empresas → Agregar empresa</strong> y cargue los datos del emisor,
        copiados <strong>exactamente</strong> del RTU.</li>
    <li>Deje el certificador en <strong>simulador</strong> y emita facturas de prueba:
        no gasta folios ni toca la red.</li>
    <li>Programe el cron de contingencia (capítulo 11 del manual).</li>
    <li>Active HTTPS antes de facturar de verdad.</li>
  </ol>

  <p style="margin-top:22px"><a class="boton" href="index.php">Entrar al sistema</a></p>

<?php else: ?>

  <h1>Instalación del sistema</h1>
  <p class="sub">Sistema de Facturación FEL — Guatemala. No necesita la Terminal.</p>

  <?php foreach ($errores as $e): ?>
    <div class="msg err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endforeach; ?>
  <?php foreach ($avisos as $a): ?>
    <div class="msg avi"><?= htmlspecialchars($a, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endforeach; ?>

  <?php if ($configEscrita !== null): ?>
    <p class="ayuda">Cree el archivo <code>config/config.php</code> con este contenido:</p>
    <pre><?= htmlspecialchars($configEscrita, ENT_QUOTES, 'UTF-8') ?></pre>
  <?php endif; ?>

  <h2>1. Requisitos del servidor</h2>
  <ul class="chequeo">
    <?php foreach ($comprobaciones as $c): ?>
      <li class="<?= $c['ok'] ? 'si' : ($c['critico'] ? 'no' : 'op') ?>">
        <?= htmlspecialchars($c['titulo'], ENT_QUOTES, 'UTF-8') ?>
        <?php if (!$c['ok']): ?>
          <small><?= htmlspecialchars($c['detalle'], ENT_QUOTES, 'UTF-8') ?></small>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($bloqueantes !== []): ?>
    <div class="msg err">
      Corrija los puntos marcados con <strong>✕</strong> y recargue esta página.
      Los marcados con <strong>!</strong> no impiden instalar.
    </div>
  <?php endif; ?>

  <form method="post" action="instalar.php">
    <h2>2. Base de datos</h2>
    <p class="ayuda">
      Los creó en cPanel → Bases de datos MySQL. El nombre y el usuario llevan el prefijo
      de su cuenta, por ejemplo <code>micuenta_fel</code>.
    </p>
    <div class="fila">
      <div class="campo">
        <label for="db_host">Servidor</label>
        <input id="db_host" name="db_host" value="<?= htmlspecialchars((string) ($_POST['db_host'] ?? 'localhost'), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="campo">
        <label for="db_nombre">Nombre de la base</label>
        <input id="db_nombre" name="db_nombre" required
               value="<?= htmlspecialchars((string) ($_POST['db_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
               placeholder="micuenta_fel">
      </div>
    </div>
    <div class="fila">
      <div class="campo">
        <label for="db_usuario">Usuario de la base</label>
        <input id="db_usuario" name="db_usuario" required
               value="<?= htmlspecialchars((string) ($_POST['db_usuario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
               placeholder="micuenta_felapp">
      </div>
      <div class="campo">
        <label for="db_clave">Contraseña de la base</label>
        <input id="db_clave" name="db_clave" type="password" autocomplete="off">
      </div>
    </div>

    <h2>3. Su usuario administrador</h2>
    <p class="ayuda">Con este usuario entra usted a dar de alta las empresas.</p>
    <div class="fila">
      <div class="campo">
        <label for="usuario">Usuario</label>
        <input id="usuario" name="usuario" required autocomplete="off"
               value="<?= htmlspecialchars((string) ($_POST['usuario'] ?? 'admin'), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="campo">
        <label for="nombre">Su nombre</label>
        <input id="nombre" name="nombre"
               value="<?= htmlspecialchars((string) ($_POST['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
    </div>
    <div class="campo">
      <label for="clave">Contraseña (mínimo 10 caracteres)</label>
      <input id="clave" name="clave" type="password" required minlength="10" autocomplete="new-password">
    </div>

    <div class="campo">
      <label for="app_nombre">Nombre que se verá en el sistema</label>
      <input id="app_nombre" name="app_nombre"
             value="<?= htmlspecialchars((string) ($_POST['app_nombre'] ?? 'Facturación FEL'), ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <p style="margin-top:22px">
      <button class="boton" type="submit" <?= $bloqueantes !== [] ? 'disabled' : '' ?>>
        Instalar el sistema
      </button>
    </p>
  </form>

<?php endif; ?>

</div>
</body>
</html>
