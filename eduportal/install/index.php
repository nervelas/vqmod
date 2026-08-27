<?php
/**
 * EduPortal · Asistente de instalación en 3 pasos.
 * 1) Verificación de requisitos  2) Datos de la base  3) Cuenta de administración
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('INSTALADOR', true);

if (is_file(__DIR__ . '/.lock')) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>Instalación completada</title>'
       . '<div style="font-family:system-ui;max-width:620px;margin:14vh auto;padding:2rem;border:1px solid #ddd;border-radius:16px">'
       . '<h1 style="margin-top:0">La instalación ya fue completada</h1>'
       . '<p>Por seguridad el instalador está bloqueado. Si necesita reinstalar, elimine el archivo '
       . '<code>install/.lock</code> desde el administrador de archivos de cPanel.</p>'
       . '<p><a href="../">Ir al sitio</a></p></div>';
    exit;
}

session_start();
mb_internal_encoding('UTF-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('America/Guatemala');

/** Escapado seguro para las vistas del instalador. */
function h(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function token(): string
{
    if (empty($_SESSION['_inst_csrf'])) {
        $_SESSION['_inst_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_inst_csrf'];
}

function verificarToken(): bool
{
    return isset($_POST['_csrf']) && is_string($_POST['_csrf'])
        && !empty($_SESSION['_inst_csrf'])
        && hash_equals($_SESSION['_inst_csrf'], $_POST['_csrf']);
}

function baseUrl(): string
{
    $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'))), '/');
    $raiz = preg_replace('#/install$#', '', $dir) ?? '';
    return ($raiz === '' ? '' : $raiz) . '/';
}

/** @return array<int,array{nombre:string,ok:bool,detalle:string,critico:bool}> */
function requisitos(): array
{
    $r = [];
    $r[] = [
        'nombre'  => 'PHP 8.0 o superior',
        'ok'      => version_compare(PHP_VERSION, '8.0.0', '>='),
        'detalle' => 'Versión detectada: ' . PHP_VERSION,
        'critico' => true,
    ];
    foreach ([
        'pdo_mysql' => 'Conexión con MySQL/MariaDB',
        'mbstring'  => 'Manejo de texto UTF-8',
        'openssl'   => 'Cifrado y correo seguro',
        'fileinfo'  => 'Validación real de archivos subidos',
        'json'      => 'Intercambio de datos',
        'zlib'      => 'Compresión de PDF y respaldos',
    ] as $ext => $para) {
        $r[] = [
            'nombre'  => 'Extensión ' . $ext,
            'ok'      => extension_loaded($ext),
            'detalle' => $para,
            'critico' => true,
        ];
    }
    $r[] = [
        'nombre'  => 'Extensión gd',
        'ok'      => extension_loaded('gd'),
        'detalle' => 'Iconos de la app móvil, carné con QR y miniaturas',
        'critico' => false,
    ];
    foreach (['config', 'storage', 'storage/uploads', 'storage/logs', 'storage/backups', 'storage/cache', 'storage/sessions'] as $dir) {
        $ruta = BASE_PATH . '/' . $dir;
        if (!is_dir($ruta)) {
            @mkdir($ruta, 0755, true);
        }
        $r[] = [
            'nombre'  => 'Carpeta /' . $dir . ' con permiso de escritura',
            'ok'      => is_dir($ruta) && is_writable($ruta),
            'detalle' => is_dir($ruta) ? 'Permisos: ' . substr(sprintf('%o', fileperms($ruta)), -4) : 'No existe',
            'critico' => true,
        ];
    }
    $r[] = [
        'nombre'  => 'Reescritura de URL (mod_rewrite)',
        'ok'      => !function_exists('apache_get_modules') || in_array('mod_rewrite', apache_get_modules(), true),
        'detalle' => 'Necesaria para las direcciones amigables',
        'critico' => false,
    ];
    return $r;
}

/** Genera un par de claves VAPID para las notificaciones push. */
function generarVapid(): array
{
    if (!function_exists('openssl_pkey_new')) {
        return ['', ''];
    }
    $clave = @openssl_pkey_new([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if ($clave === false) {
        return ['', ''];
    }
    $detalles = openssl_pkey_get_details($clave);
    if (!$detalles || !isset($detalles['ec']['x'], $detalles['ec']['y'], $detalles['ec']['d'])) {
        return ['', ''];
    }
    $b64 = static fn(string $b): string => rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
    $publica = "\x04" . str_pad($detalles['ec']['x'], 32, "\0", STR_PAD_LEFT)
                      . str_pad($detalles['ec']['y'], 32, "\0", STR_PAD_LEFT);
    return [$b64($publica), $b64(str_pad($detalles['ec']['d'], 32, "\0", STR_PAD_LEFT))];
}

/**
 * Divide un script SQL en sentencias respetando comillas, comentarios y escapes.
 * @return array<int,string>
 */
function dividirSql(string $sql): array
{
    $sentencias = [];
    $actual = '';
    $comilla = null;
    $largo = strlen($sql);
    for ($i = 0; $i < $largo; $i++) {
        $c = $sql[$i];

        if ($comilla === null) {
            // Comentario de linea
            if (($c === '-' && substr($sql, $i, 2) === '--') || $c === '#') {
                $fin = strpos($sql, "\n", $i);
                $i = $fin === false ? $largo : $fin;
                continue;
            }
            // Comentario de bloque
            if ($c === '/' && substr($sql, $i, 2) === '/*') {
                $fin = strpos($sql, '*/', $i + 2);
                $i = $fin === false ? $largo : $fin + 1;
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $comilla = $c;
                $actual .= $c;
                continue;
            }
            if ($c === ';') {
                $s = trim($actual);
                if ($s !== '') {
                    $sentencias[] = $s;
                }
                $actual = '';
                continue;
            }
            $actual .= $c;
            continue;
        }

        // Dentro de una cadena
        $actual .= $c;
        if ($c === '\\' && $comilla !== '`') {
            if ($i + 1 < $largo) {
                $actual .= $sql[++$i];
            }
            continue;
        }
        if ($c === $comilla) {
            // Comilla duplicada = comilla escapada
            if ($i + 1 < $largo && $sql[$i + 1] === $comilla) {
                $actual .= $sql[++$i];
                continue;
            }
            $comilla = null;
        }
    }
    $s = trim($actual);
    if ($s !== '') {
        $sentencias[] = $s;
    }
    return $sentencias;
}

/**
 * Traduce los errores más comunes de MySQL a una explicación accionable.
 * @return array{0:string,1:array<int,string>} mensaje y lista de comprobaciones
 */
function explicarErrorBd(Throwable $e, array $datos): array
{
    $texto = $e->getMessage();
    $codigo = 0;
    if (preg_match('/\[(\d{4})\]/', $texto, $m)) {
        $codigo = (int)$m[1];
    }

    switch ($codigo) {
        case 1045: // ER_ACCESS_DENIED_ERROR
            return [
                'El servidor MySQL rechazó el usuario o la contraseña. No es un problema de permisos: '
                . 'todavía no llegamos a la base de datos.',
                [
                    'En cPanel abra <strong>Bases de datos MySQL &rarr; Usuarios actuales</strong> y copie el nombre '
                    . 'tal como aparece ahí. MySQL distingue mayúsculas de minúsculas y cPanel suele guardarlo '
                    . 'en minúsculas.',
                    'Si el nombre coincide, pulse <strong>Cambiar contraseña</strong> junto a ese usuario y ponga una '
                    . 'nueva solo con letras y números. Los símbolos se pierden al copiar y pegar.',
                    'Al pegar la contraseña, cuide que no quede un espacio al final.',
                    'Usuario que se intentó: <code>' . h($datos['user']) . '</code>',
                ],
            ];

        case 1044: // ER_DBACCESS_DENIED_ERROR
            return [
                'El usuario y la contraseña son correctos, pero ese usuario no tiene permisos sobre la base «'
                . $datos['database'] . '».',
                [
                    'En cPanel, sección <strong>Agregar usuario a la base de datos</strong>, seleccione el usuario '
                    . 'y la base, y márquele <strong>TODOS LOS PRIVILEGIOS</strong>.',
                ],
            ];

        case 1049: // ER_BAD_DB_ERROR
            return [
                'El usuario existe, pero no hay ninguna base de datos llamada «'
                . $datos['database'] . '».',
                [
                    'Copie el nombre completo desde <strong>Bases de datos actuales</strong> en cPanel: '
                    . 'lleva el prefijo de su cuenta, por ejemplo <code>micuenta_eduportal</code>.',
                ],
            ];

        case 2002:
        case 2003:
        case 2005:
            return [
                'No se encontró el servidor MySQL en ' . $datos['host'] . ':' . (int)$datos['puerto'] . '.',
                [
                    'En un hosting cPanel el servidor casi siempre es <code>localhost</code> y el puerto <code>3306</code>.',
                    'Si su proveedor usa un servidor de base de datos aparte, pídale la dirección exacta.',
                ],
            ];
    }

    return ['No se pudo conectar con la base de datos.', ['Detalle técnico: <code>' . h($texto) . '</code>']];
}

/** Ejecuta un archivo .sql sentencia por sentencia. */
function ejecutarSql(PDO $pdo, string $archivo): void
{
    $sql = @file_get_contents($archivo);
    if ($sql === false) {
        throw new RuntimeException('No se pudo leer ' . basename($archivo));
    }
    foreach (dividirSql($sql) as $sentencia) {
        $pdo->exec($sentencia);
    }
}

$paso = max(1, min(3, (int)($_GET['paso'] ?? 1)));
$errores = [];
$ayuda = [];
$aviso = null;

// ---------------- Paso 2: conexión a la base ----------------
if ($paso === 2 && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verificarToken()) {
        $errores[] = 'La sesión expiró. Vuelva a intentarlo.';
    } else {
        $datos = [
            'host'     => trim((string)($_POST['host'] ?? 'localhost')),
            'puerto'   => (int)($_POST['puerto'] ?? 3306),
            'database' => trim((string)($_POST['database'] ?? '')),
            'user'     => trim(str_replace(["\r", "\n", "\t", "\0"], '', (string)($_POST['user'] ?? ''))),
            // La contraseña no se recorta (puede llevar espacios), pero los saltos de línea
            // que se cuelan al pegar nunca son parte de ella.
            'password' => str_replace(["\r", "\n", "\t", "\0"], '', (string)($_POST['password'] ?? '')),
        ];
        if ($datos['database'] === '' || $datos['user'] === '') {
            $errores[] = 'Indique el nombre de la base de datos y el usuario.';
        } elseif ($datos['puerto'] < 1 || $datos['puerto'] > 65535) {
            $errores[] = 'El puerto no es válido.';
        } else {
            try {
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $datos['host'], $datos['puerto'], $datos['database']);
                $pdo = new PDO($dsn, $datos['user'], $datos['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 8,
                ]);
                $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
                $_SESSION['_inst_db'] = $datos;
                $_SESSION['_inst_version'] = $version;
                header('Location: ?paso=3');
                exit;
            } catch (Throwable $e) {
                [$mensaje, $comprobaciones] = explicarErrorBd($e, $datos);
                $errores[] = $mensaje;
                $ayuda = $comprobaciones;
            }
        }
    }
}

// ---------------- Paso 3: crear administrador e instalar ----------------
if ($paso === 3 && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verificarToken()) {
        $errores[] = 'La sesión expiró. Vuelva a intentarlo.';
    } elseif (empty($_SESSION['_inst_db'])) {
        $errores[] = 'Faltan los datos de la base. Regrese al paso 2.';
    } else {
        $colegio  = trim((string)($_POST['colegio'] ?? ''));
        $nombre   = trim((string)($_POST['nombre'] ?? ''));
        $email    = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $conf     = (string)($_POST['password_confirmacion'] ?? '');
        $demo     = isset($_POST['demo']);

        if ($colegio === '' || mb_strlen($colegio) > 120) {
            $errores[] = 'Escriba el nombre del colegio.';
        }
        if ($nombre === '' || mb_strlen($nombre) < 3) {
            $errores[] = 'Escriba su nombre completo.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no es válido.';
        }
        if (mb_strlen($password) < 10) {
            $errores[] = 'La contraseña debe tener al menos 10 caracteres.';
        }
        if ($password !== $conf) {
            $errores[] = 'La confirmación de la contraseña no coincide.';
        }

        if ($errores === []) {
            $db = $_SESSION['_inst_db'];
            try {
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['puerto'], $db['database']);
                $pdo = new PDO($dsn, $db['user'], $db['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");

                ejecutarSql($pdo, __DIR__ . '/database.sql');
                if ($demo && is_file(__DIR__ . '/database_demo.sql')) {
                    ejecutarSql($pdo, __DIR__ . '/database_demo.sql');
                }

                // Administrador
                $algo = defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)
                    ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
                $opts = $algo === PASSWORD_BCRYPT
                    ? ['cost' => 12]
                    : ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2];
                $hash = password_hash($password, $algo, $opts);

                $st = $pdo->prepare('SELECT id FROM users WHERE email = :e');
                $st->execute(['e' => $email]);
                $existe = $st->fetchColumn();
                if ($existe) {
                    $pdo->prepare('UPDATE users SET nombre = :n, password_hash = :p, rol = \'superadmin\', activo = 1 WHERE id = :id')
                        ->execute(['n' => $nombre, 'p' => $hash, 'id' => (int)$existe]);
                } else {
                    $pdo->prepare('INSERT INTO users (nombre, email, password_hash, rol, activo) VALUES (:n, :e, :p, \'superadmin\', 1)')
                        ->execute(['n' => $nombre, 'e' => $email, 'p' => $hash]);
                }

                // Ajustes iniciales
                [$vapidPub, $vapidPriv] = generarVapid();
                $cronToken = bin2hex(random_bytes(24));
                $ajustes = [
                    'colegio_nombre' => $colegio,
                    'seo_title'      => $colegio,
                    'smtp_nombre'    => $colegio,
                    'colegio_email'  => $email,
                    'cron_token'     => $cronToken,
                    'vapid_public'   => $vapidPub,
                    'vapid_private'  => $vapidPriv,
                ];
                $up = $pdo->prepare('INSERT INTO settings (clave, valor, grupo) VALUES (:c, :v, \'general\')
                                     ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
                foreach ($ajustes as $c => $v) {
                    $up->execute(['c' => $c, 'v' => (string)$v]);
                }

                // Ciclo inicial si no existe
                $hayCiclo = (int)$pdo->query('SELECT COUNT(*) FROM ciclos')->fetchColumn();
                if ($hayCiclo === 0) {
                    $anio = date('Y');
                    $pdo->prepare('INSERT INTO ciclos (nombre, fecha_inicio, fecha_fin, activo) VALUES (:n, :i, :f, 1)')
                        ->execute(['n' => $anio, 'i' => $anio . '-01-01', 'f' => $anio . '-12-31']);
                }

                // Archivo de configuración
                $plantilla = (string)file_get_contents(BASE_PATH . '/config/config.example.php');
                $config = strtr($plantilla, [
                    "'host'     => 'localhost'"        => "'host'     => " . var_export($db['host'], true),
                    "'port'     => 3306"               => "'port'     => " . (int)$db['puerto'],
                    "'database' => 'NOMBRE_DE_LA_BASE'" => "'database' => " . var_export($db['database'], true),
                    "'user'     => 'USUARIO'"          => "'user'     => " . var_export($db['user'], true),
                    "'password' => 'CONTRASENA'"       => "'password' => " . var_export($db['password'], true),
                    "'app_key' => ''"                  => "'app_key' => " . var_export(bin2hex(random_bytes(32)), true),
                ]);
                if (@file_put_contents(BASE_PATH . '/config/config.php', $config) === false) {
                    throw new RuntimeException('No se pudo escribir config/config.php. Revise los permisos de la carpeta /config.');
                }
                @chmod(BASE_PATH . '/config/config.php', 0640);

                // Bloqueo del instalador
                @file_put_contents(__DIR__ . '/.lock', 'Instalado el ' . date('Y-m-d H:i:s') . "\n");

                $_SESSION['_inst_ok'] = [
                    'email' => $email,
                    'cron'  => $cronToken,
                    'demo'  => $demo,
                    'push'  => $vapidPub !== '',
                ];
                unset($_SESSION['_inst_db']);
                header('Location: ?paso=3&listo=1');
                exit;
            } catch (Throwable $e) {
                $errores[] = 'Error durante la instalación: ' . $e->getMessage();
            }
        }
    }
}

$listo = isset($_GET['listo']) && !empty($_SESSION['_inst_ok']);
$resumen = $_SESSION['_inst_ok'] ?? null;
$reqs = $paso === 1 ? requisitos() : [];
$criticosOk = true;
foreach ($reqs as $r) {
    if ($r['critico'] && !$r['ok']) {
        $criticosOk = false;
    }
}
$base = baseUrl();
?>
<!doctype html>
<html lang="es-GT" data-tema="default" data-oscuro="0">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalación de EduPortal</title>
<link rel="icon" href="<?= h($base) ?>assets/icons/icon-96.png">
<link rel="stylesheet" href="<?= h($base) ?>assets/css/app.css">
<link rel="stylesheet" href="<?= h($base) ?>assets/css/paginas.css">
</head>
<body>
<main class="seccion" style="min-height:100vh;background:var(--fondo)">
  <div class="seccion__int" style="max-width:820px">

    <header class="cen mb-5">
      <img src="<?= h($base) ?>assets/icons/icon-96.png" alt="" style="width:64px;margin:0 auto 14px;border-radius:16px">
      <h1 style="margin-bottom:6px">Instalación de EduPortal</h1>
      <p class="txt-2">Sistema integral de gestión para colegios</p>
    </header>

    <ol class="flex" style="list-style:none;padding:0;gap:0;margin-bottom:32px">
      <?php foreach ([1 => 'Requisitos', 2 => 'Base de datos', 3 => 'Administrador'] as $n => $etq): ?>
        <li style="flex:1;text-align:center;position:relative">
          <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;
                       border-radius:999px;font-weight:700;
                       background:<?= $paso >= $n ? 'var(--primario)' : 'var(--mute-bg)' ?>;
                       color:<?= $paso >= $n ? '#fff' : 'var(--texto-3)' ?>"><?= $n ?></span>
          <div class="xs <?= $paso >= $n ? 'negrita' : 'txt-3' ?>" style="margin-top:6px"><?= h($etq) ?></div>
        </li>
      <?php endforeach; ?>
    </ol>

    <?php foreach ($errores as $err): ?>
      <div class="aviso aviso--bad"><span><?= h($err) ?></span></div>
    <?php endforeach; ?>

    <?php if ($ayuda !== []): ?>
      <div class="tarjeta mb-4">
        <h3 style="margin-top:0">Cómo resolverlo</h3>
        <ol class="sm txt-2" style="margin:0;padding-left:1.2rem;line-height:1.7">
          <?php foreach ($ayuda as $linea): ?>
            <li><?= $linea ?></li>
          <?php endforeach; ?>
        </ol>
      </div>
    <?php endif; ?>

    <?php if ($listo && $resumen): ?>
      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>¡Instalación completada!</h2></div>
        <div class="aviso aviso--ok"><span>EduPortal está listo para usarse. El instalador quedó bloqueado automáticamente.</span></div>
        <p>Ingrese al panel con el correo <strong><?= h($resumen['email']) ?></strong> y la contraseña que acaba de definir.</p>
        <?php if (!empty($resumen['demo'])): ?>
          <p class="sm txt-2">Se cargaron los datos de demostración. Puede eliminarlos más adelante desde el panel.</p>
        <?php endif; ?>
        <h3 class="mt-4">Configure la tarea programada en cPanel</h3>
        <p class="sm txt-2">Cada 15 minutos, con este comando:</p>
        <pre style="background:var(--superficie-2);border:1px solid var(--borde);border-radius:10px;
                    padding:12px;overflow-x:auto;font-size:.8rem"><code>*/15 * * * * curl -s "https://<?= h($_SERVER['HTTP_HOST'] ?? 'TUDOMINIO') ?><?= h($base) ?>cron/run.php?token=<?= h($resumen['cron']) ?>"</code></pre>
        <p class="sm txt-3">También encontrará este comando en <strong>Configuración → Respaldo</strong>.</p>
        <?php if (empty($resumen['push'])): ?>
          <div class="aviso aviso--warn"><span>No se pudieron generar las claves de notificaciones push
            (la extensión OpenSSL no soporta curvas EC). El resto del sistema funciona con normalidad.</span></div>
        <?php endif; ?>
        <div class="acciones mt-4">
          <a href="<?= h($base) ?>ingresar" class="btn">Ingresar al panel</a>
          <a href="<?= h($base) ?>" class="btn btn--linea">Ver el sitio público</a>
        </div>
        <div class="aviso aviso--info mt-4"><span>Por seguridad, elimine la carpeta <code>/install</code>
          desde el administrador de archivos de cPanel cuando termine.</span></div>
      </div>

    <?php elseif ($paso === 1): ?>
      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Paso 1 · Requisitos del servidor</h2></div>
        <div class="tabla-env">
          <table class="tabla" style="min-width:auto">
            <thead><tr><th>Requisito</th><th>Detalle</th><th class="cen">Estado</th></tr></thead>
            <tbody>
            <?php foreach ($reqs as $r): ?>
              <tr>
                <td><?= h($r['nombre']) ?><?= $r['critico'] ? '' : ' <span class="badge badge--mute">Opcional</span>' ?></td>
                <td class="sm txt-2"><?= h($r['detalle']) ?></td>
                <td class="cen">
                  <span class="badge badge--<?= $r['ok'] ? 'ok' : ($r['critico'] ? 'bad' : 'warn') ?>">
                    <?= $r['ok'] ? 'Correcto' : ($r['critico'] ? 'Falta' : 'Sugerido') ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($criticosOk): ?>
          <div class="aviso aviso--ok mt-4"><span>Su servidor cumple con todos los requisitos.</span></div>
          <a href="?paso=2" class="btn mt-2">Continuar al paso 2</a>
        <?php else: ?>
          <div class="aviso aviso--bad mt-4"><span>Corrija los requisitos marcados antes de continuar.
            Para los permisos de carpetas, asigne 755 desde el administrador de archivos de cPanel.</span></div>
          <a href="?paso=1" class="btn btn--linea mt-2">Volver a verificar</a>
        <?php endif; ?>
      </div>

    <?php elseif ($paso === 2): ?>
      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Paso 2 · Base de datos</h2></div>
        <p class="sm txt-2">Cree la base de datos y el usuario desde <strong>cPanel → Bases de datos MySQL</strong>,
           asigne todos los privilegios y escriba los datos aquí.</p>
        <form method="post" action="?paso=2">
          <input type="hidden" name="_csrf" value="<?= h(token()) ?>">
          <div class="fila">
            <div class="campo">
              <label for="host">Servidor</label>
              <input type="text" id="host" name="host" required value="<?= h($_POST['host'] ?? 'localhost') ?>">
            </div>
            <div class="campo">
              <label for="puerto">Puerto</label>
              <input type="number" id="puerto" name="puerto" required min="1" max="65535" value="<?= h($_POST['puerto'] ?? '3306') ?>">
            </div>
          </div>
          <div class="campo">
            <label for="database">Nombre de la base de datos</label>
            <input type="text" id="database" name="database" required value="<?= h($_POST['database'] ?? '') ?>" placeholder="cuenta_eduportal">
          </div>
          <div class="fila">
            <div class="campo">
              <label for="user">Usuario</label>
              <input type="text" id="user" name="user" required value="<?= h($_POST['user'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="campo">
              <label for="password">Contraseña</label>
              <input type="password" id="password" name="password" autocomplete="new-password">
            </div>
          </div>
          <div class="flex" style="gap:8px">
            <a href="?paso=1" class="btn btn--linea">Atrás</a>
            <button type="submit" class="btn">Probar conexión y continuar</button>
          </div>
        </form>
      </div>

    <?php else: ?>
      <div class="tarjeta">
        <div class="tarjeta__cab"><h2>Paso 3 · Colegio y administrador</h2></div>
        <?php if (!empty($_SESSION['_inst_version'])): ?>
          <div class="aviso aviso--ok"><span>Conexión establecida con
            <?= h($_SESSION['_inst_version']) ?>. Ya puede crear la cuenta principal.</span></div>
        <?php endif; ?>
        <form method="post" action="?paso=3">
          <input type="hidden" name="_csrf" value="<?= h(token()) ?>">
          <div class="campo">
            <label for="colegio">Nombre del colegio</label>
            <input type="text" id="colegio" name="colegio" required maxlength="120"
                   value="<?= h($_POST['colegio'] ?? '') ?>" placeholder="Colegio San Francisco">
          </div>
          <div class="campo">
            <label for="nombre">Su nombre completo</label>
            <input type="text" id="nombre" name="nombre" required maxlength="120" value="<?= h($_POST['nombre'] ?? '') ?>">
          </div>
          <div class="campo">
            <label for="email">Correo electrónico (será su usuario)</label>
            <input type="email" id="email" name="email" required maxlength="160" value="<?= h($_POST['email'] ?? '') ?>">
          </div>
          <div class="fila">
            <div class="campo">
              <label for="pwd">Contraseña (mínimo 10 caracteres)</label>
              <input type="password" id="pwd" name="password" required minlength="10" autocomplete="new-password">
            </div>
            <div class="campo">
              <label for="pwd2">Confirme la contraseña</label>
              <input type="password" id="pwd2" name="password_confirmacion" required minlength="10" autocomplete="new-password">
            </div>
          </div>
          <label class="check"><input type="checkbox" name="demo" value="1">
            Cargar datos de demostración (colegio de ejemplo con alumnos, cargos y notas)</label>
          <div class="flex" style="gap:8px">
            <a href="?paso=2" class="btn btn--linea">Atrás</a>
            <button type="submit" class="btn">Instalar EduPortal</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <p class="cen sm txt-3 mt-5">EduPortal &copy; <?= date('Y') ?> · Instalación para hosting compartido cPanel</p>
  </div>
</main>
</body>
</html>
