<?php
declare(strict_types=1);

/**
 * ResidencialPro — Instalador web en 3 pasos.
 * 1) Requisitos del servidor   2) Base de datos   3) Condominio y administrador
 */

define('RPRO_INSTALADOR', true);
define('RUTA_BASE', dirname(__DIR__));
define('RPRO_VERSION', '1.0.0');

error_reporting(E_ALL);
ini_set('display_errors', '0');
@ini_set('max_execution_time', '300');

session_name('rpro_install');
session_start();

$rutaConfig = RUTA_BASE . '/config/config.php';
$rutaLock   = __DIR__ . '/.lock';

$base = rtrim(str_replace('\\', '/', dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')))), '/');
if ($base === '.' || $base === '/') { $base = ''; }

// El resumen final se muestra una sola vez, incluso con el bloqueo ya escrito:
// es la única pantalla donde aparecen la línea del cron y las llaves push.
if ((int) ($_GET['paso'] ?? 0) === 4 && !empty($_SESSION['listo'])) {
    pantallaFinal($_SESSION['listo'], $base);
    exit;
}

if (is_file($rutaLock)) {
    pantallaBloqueada();
    exit;
}

$paso    = max(1, min(3, (int) ($_GET['paso'] ?? 1)));
$errores = [];
$avisos  = [];

/* ----------------------------------------------------------- Utilidades */
function esc(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function escribible(string $ruta): bool
{
    if (!is_dir($ruta)) {
        @mkdir($ruta, 0755, true);
    }
    return is_dir($ruta) && is_writable($ruta);
}

function requisitos(): array
{
    $r = [];
    $r[] = ['PHP 8.0 o superior', PHP_VERSION, version_compare(PHP_VERSION, '8.0.0', '>='), true];
    foreach (['pdo_mysql' => 'Conexión con MySQL/MariaDB', 'mbstring' => 'Texto en español (acentos y ñ)',
              'openssl'   => 'Cifrado y notificaciones', 'json' => 'Intercambio de datos',
              'fileinfo'  => 'Validación real de archivos subidos'] as $ext => $para) {
        $r[] = [$para . ' (' . $ext . ')', extension_loaded($ext) ? 'activa' : 'ausente', extension_loaded($ext), true];
    }
    $r[] = ['Imágenes y iconos (gd)', extension_loaded('gd') ? 'activa' : 'ausente', extension_loaded('gd'), false];
    $r[] = ['Exportación a Excel (zip)', extension_loaded('zip') ? 'activa' : 'ausente', extension_loaded('zip'), false];
    $r[] = ['Notificaciones push (curl)', extension_loaded('curl') ? 'activa' : 'ausente', extension_loaded('curl'), false];

    foreach (['/config' => 'Carpeta config', '/storage' => 'Carpeta storage', '/uploads' => 'Carpeta uploads'] as $d => $et) {
        $ok = escribible(RUTA_BASE . $d);
        $r[] = [$et . ' con permiso de escritura', $ok ? 'correcta' : 'sin permiso', $ok, true];
    }
    return $r;
}

function conectar(array $d): PDO
{
    $dsn = "mysql:host={$d['host']};port={$d['puerto']};dbname={$d['nombre']};charset=utf8mb4";
    return new PDO($dsn, $d['usuario'], $d['clave'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/**
 * Ejecuta un archivo .sql sentencia por sentencia.
 * Separa por punto y coma respetando comillas, comentarios y escapes.
 */
function importarSql(PDO $pdo, string $archivo): int
{
    $fh = fopen($archivo, 'r');
    if ($fh === false) {
        throw new RuntimeException('No se pudo leer ' . basename($archivo));
    }
    $sentencia = '';
    $comilla   = '';
    $n = 0;
    while (($linea = fgets($fh)) !== false) {
        if ($comilla === '') {
            $recortada = ltrim($linea);
            if ($recortada === '' || str_starts_with($recortada, '--') || str_starts_with($recortada, '#')) {
                continue;
            }
        }
        $largo = strlen($linea);
        for ($i = 0; $i < $largo; $i++) {
            $ch = $linea[$i];
            if ($comilla !== '') {
                $sentencia .= $ch;
                if ($ch === '\\' && $i + 1 < $largo) {
                    $sentencia .= $linea[++$i];
                } elseif ($ch === $comilla) {
                    $comilla = '';
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $comilla = $ch;
                $sentencia .= $ch;
                continue;
            }
            if ($ch === ';') {
                $limpia = trim($sentencia);
                if ($limpia !== '') {
                    $pdo->exec($limpia);
                    $n++;
                }
                $sentencia = '';
                continue;
            }
            $sentencia .= $ch;
        }
    }
    fclose($fh);
    $limpia = trim($sentencia);
    if ($limpia !== '') {
        $pdo->exec($limpia);
        $n++;
    }
    return $n;
}

function generarVapid(): array
{
    if (!function_exists('openssl_pkey_new')) {
        return ['', ''];
    }
    require_once RUTA_BASE . '/vendor/push/WebPush.php';
    try {
        $c = \Vendor\Push\WebPush::generarClaves();
        return [$c['publica'], $c['privada']];
    } catch (Throwable) {
        return ['', ''];
    }
}

/* ------------------------------------------------------------- Proceso */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'bd') {
        $d = [
            'host'    => trim((string) ($_POST['host'] ?? 'localhost')),
            'puerto'  => trim((string) ($_POST['puerto'] ?? '3306')),
            'nombre'  => trim((string) ($_POST['nombre'] ?? '')),
            'usuario' => trim((string) ($_POST['usuario'] ?? '')),
            'clave'   => (string) ($_POST['clave'] ?? ''),
        ];
        $demo = !empty($_POST['demo']);
        if ($d['nombre'] === '' || $d['usuario'] === '') {
            $errores[] = 'Indique el nombre de la base de datos y el usuario.';
        } else {
            try {
                $pdo = conectar($d);
                $existentes = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables
                    WHERE table_schema = DATABASE() AND table_name = 'usuarios'")->fetchColumn();
                if ($existentes > 0 && empty($_POST['sobrescribir'])) {
                    $errores[] = 'La base de datos ya contiene tablas de ResidencialPro. '
                        . 'Marque "reinstalar" si desea borrar los datos existentes y empezar de nuevo.';
                } else {
                    if ($existentes > 0) {
                        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
                            $pdo->exec('DROP TABLE IF EXISTS `' . $t . '`');
                        }
                        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                    }
                    importarSql($pdo, RUTA_BASE . '/database/database.sql');
                    if ($demo && is_file(RUTA_BASE . '/database/database_demo.sql')) {
                        importarSql($pdo, RUTA_BASE . '/database/database_demo.sql');
                        $_SESSION['demo'] = true;
                    }
                    $_SESSION['bd'] = $d;
                    header('Location: ?paso=3');
                    exit;
                }
            } catch (Throwable $e) {
                $errores[] = 'No se pudo conectar o importar: ' . $e->getMessage();
            }
        }
        $paso = 2;
    }

    if ($accion === 'final') {
        $d = $_SESSION['bd'] ?? null;
        if (!is_array($d)) {
            $errores[] = 'La sesión del instalador expiró. Vuelva al paso 2.';
            $paso = 2;
        } else {
            $condominio = trim((string) ($_POST['condominio'] ?? ''));
            $adminNom   = trim((string) ($_POST['admin_nombre'] ?? ''));
            $adminCor   = trim((string) ($_POST['admin_correo'] ?? ''));
            $adminUsu   = trim((string) ($_POST['admin_usuario'] ?? ''));
            $adminCla   = (string) ($_POST['admin_clave'] ?? '');

            if ($condominio === '') { $errores[] = 'Escriba el nombre del condominio.'; }
            if ($adminNom === '')   { $errores[] = 'Escriba el nombre del administrador.'; }
            if (!filter_var($adminCor, FILTER_VALIDATE_EMAIL)) { $errores[] = 'El correo del administrador no es válido.'; }
            if (!preg_match('/^[a-zA-Z0-9._\-]{4,60}$/', $adminUsu)) { $errores[] = 'El usuario debe tener de 4 a 60 caracteres (letras, números, punto o guion).'; }
            if (mb_strlen($adminCla) < 10) { $errores[] = 'La contraseña debe tener al menos 10 caracteres.'; }

            if ($errores === []) {
                try {
                    $pdo = conectar($d);
                    [$vapidPub, $vapidPriv] = generarVapid();
                    $llaveApp   = bin2hex(random_bytes(32));
                    $tokenCron  = bin2hex(random_bytes(24));

                    // Configuración
                    $config = "<?php\n"
                        . "/**\n * ResidencialPro — configuración generada por el instalador.\n"
                        . " * Fecha: " . date('d/m/Y H:i') . "\n * No comparta este archivo.\n */\n\n"
                        . "return [\n"
                        . "    'app' => [\n"
                        . "        'nombre'  => " . var_export($condominio, true) . ",\n"
                        . "        'llave'   => " . var_export($llaveApp, true) . ",\n"
                        . "        'zona'    => 'America/Guatemala',\n"
                        . "        'depurar' => false,\n"
                        . "    ],\n"
                        . "    'db' => [\n"
                        . "        'host'    => " . var_export($d['host'], true) . ",\n"
                        . "        'puerto'  => " . var_export($d['puerto'], true) . ",\n"
                        . "        'nombre'  => " . var_export($d['nombre'], true) . ",\n"
                        . "        'usuario' => " . var_export($d['usuario'], true) . ",\n"
                        . "        'clave'   => " . var_export($d['clave'], true) . ",\n"
                        . "        'socket'  => '',\n"
                        . "    ],\n"
                        . "    'sesion' => ['minutos' => 30],\n"
                        . "    'cron'   => ['token' => " . var_export($tokenCron, true) . "],\n"
                        . "];\n";
                    if (@file_put_contents($rutaConfig, $config) === false) {
                        throw new RuntimeException('No se pudo escribir /config/config.php. Revise los permisos.');
                    }
                    @chmod($rutaConfig, 0640);

                    // Usuario administrador
                    $hash = defined('PASSWORD_ARGON2ID')
                        ? password_hash($adminCla, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2])
                        : password_hash($adminCla, PASSWORD_DEFAULT);
                    $st = $pdo->prepare('INSERT INTO usuarios (rol, nombre, usuario, correo, password_hash, activo, onboarding)
                                         VALUES ("admin", :n, :u, :c, :h, 1, 0)
                                         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), password_hash = VALUES(password_hash), rol = "admin"');
                    $st->execute(['n' => $adminNom, 'u' => $adminUsu, 'c' => $adminCor, 'h' => $hash]);

                    // Ajustes iniciales
                    $ajustes = [
                        'nombre'          => $condominio,
                        'lema'            => 'Residencial privado',
                        'descripcion'     => 'Administración integral del residencial: cuotas, visitas, áreas comunes y comunicación con los residentes.',
                        'correo'          => $adminCor,
                        'telefono'        => trim((string) ($_POST['telefono'] ?? '')),
                        'direccion'       => trim((string) ($_POST['direccion'] ?? '')),
                        'nit'             => trim((string) ($_POST['nit'] ?? '')),
                        'ciudad'          => trim((string) ($_POST['ciudad'] ?? 'Ciudad de Guatemala')),
                        'moneda_simbolo'  => 'Q',
                        'pais_codigo'     => '502',
                        'tema'            => (string) ($_POST['tema'] ?? 'verde-oro'),
                        'color_primario'  => '#0F2E24',
                        'color_acento'    => '#C9A961',
                        'mora_tipo'       => 'porcentaje',
                        'mora_valor'      => '2',
                        'mora_dias_gracia' => '0',
                        'mora_tope_porcentaje' => '100',
                        'corte_dias'      => '90',
                        'carta_dias'      => '60',
                        'carta_plazo_dias' => '15',
                        'recordatorio_previo_dias' => '5',
                        'recordatorio_cada_dias'   => '7',
                        'avisar_visita'   => '1',
                        'correo_activo'   => '1',
                        'smtp_seguridad'  => 'tls',
                        'smtp_puerto'     => '587',
                        'smtp_de_nombre'  => $condominio,
                        'firma_nombre'    => $adminNom,
                        'firma_cargo'     => 'Administración del residencial',
                        'vapid_publica'   => $vapidPub,
                        'vapid_privada'   => $vapidPriv,
                        'cron_token'      => $tokenCron,
                        'wa_recordatorio' => 'Estimado(a) {residente}, le saludamos de {condominio}. Le recordamos que la casa {casa} presenta un saldo de {saldo} con vencimiento {vence}. Puede pagar aquí: {enlace}. ¡Gracias!',
                        'wa_recibo'       => 'Estimado(a) {residente}, recibimos su pago de {monto} para la casa {casa}. Su recibo {recibo} está disponible en: {enlace}',
                        'wa_visita'       => 'Su visita {visitante} llegó a la garita de {condominio}.',
                        'correo_pie'      => 'Este es un mensaje automático de la administración. Por favor no responda a este correo.',
                        'instalado_en'    => date('Y-m-d H:i:s'),
                        'version'         => RPRO_VERSION,
                    ];
                    $st = $pdo->prepare('INSERT INTO ajustes (clave, valor, grupo) VALUES (:c, :v, "general")
                                         ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
                    foreach ($ajustes as $k => $v) {
                        $st->execute(['c' => $k, 'v' => (string) $v]);
                    }

                    // Iconos de la aplicación
                    if (extension_loaded('gd') && is_file(__DIR__ . '/generar-iconos.php')) {
                        try { include __DIR__ . '/generar-iconos.php'; } catch (Throwable) {}
                    }

                    // Bloqueo del instalador
                    @file_put_contents($rutaLock, 'Instalado el ' . date('d/m/Y H:i:s') . "\n");

                    $_SESSION['listo'] = [
                        'usuario' => $adminUsu,
                        'correo'  => $adminCor,
                        'cron'    => $tokenCron,
                        'demo'    => !empty($_SESSION['demo']),
                        'push'    => $vapidPub !== '',
                    ];
                    header('Location: ?paso=4');
                    exit;
                } catch (Throwable $e) {
                    $errores[] = 'Error al finalizar la instalación: ' . $e->getMessage();
                }
            }
            $paso = 3;
        }
    }
}

if ($paso === 3 && empty($_SESSION['bd'])) {
    $paso = 2;
    $avisos[] = 'Primero configure la conexión con la base de datos.';
}

$reqs = requisitos();
$faltaObligatorio = false;
foreach ($reqs as $r) {
    if ($r[3] && !$r[2]) { $faltaObligatorio = true; }
}

/* --------------------------------------------------------------- Vistas */
function cabecera(string $titulo, string $base): void
{
    ?><!DOCTYPE html>
<html lang="es" data-tema="verde-oro" data-modo="claro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($titulo) ?> · Instalación de ResidencialPro</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" href="<?= esc($base) ?>/assets/img/favicon.png">
<link rel="stylesheet" href="<?= esc($base) ?>/assets/css/fuentes-locales.css">
<link rel="stylesheet" href="<?= esc($base) ?>/assets/css/app.css">
<style>
  body { background: linear-gradient(160deg,#0A2019,#0F2E24 60%,#164032); min-height:100dvh; }
  .inst { width:min(880px, calc(100% - 32px)); margin:0 auto; padding:40px 0 60px; }
  .inst-marca { text-align:center; color:#E9EEE9; margin-bottom:26px; }
  .inst-marca b { font-family:var(--f-titulo); font-size:2rem; color:var(--acento-2); display:block; }
  .inst-marca span { font-size:.78rem; letter-spacing:.2em; text-transform:uppercase; color:rgba(233,238,233,.6); }
  .pasos { display:flex; gap:8px; justify-content:center; margin-bottom:26px; flex-wrap:wrap; }
  .paso-p { display:flex; align-items:center; gap:9px; padding:9px 16px; border-radius:var(--r-full);
            background:rgba(255,255,255,.07); color:rgba(233,238,233,.72); font-size:.83rem; font-weight:600; }
  .paso-p.activo { background:var(--acento); color:#1F1B10; }
  .paso-p.hecho { background:rgba(201,169,97,.22); color:var(--acento-2); }
  .paso-p i { width:22px;height:22px;border-radius:50%;display:grid;place-items:center;background:rgba(0,0,0,.18);font-style:normal;font-size:.75rem; }
  .req { display:flex; align-items:center; gap:12px; padding:11px 0; border-bottom:1px solid var(--borde); font-size:.9rem; }
  .req:last-child { border-bottom:0; }
  .req .est { margin-left:auto; }
</style>
</head>
<body>
<div class="inst">
  <div class="inst-marca">
    <b>ResidencialPro</b>
    <span>Instalación del sistema</span>
  </div>
<?php }

function pie(): void { ?>
</div>
</body></html>
<?php }

function pasos(int $actual): void
{
    $etiquetas = [1 => 'Requisitos', 2 => 'Base de datos', 3 => 'Condominio y administrador'];
    echo '<div class="pasos">';
    foreach ($etiquetas as $n => $t) {
        $clase = $n === $actual ? 'activo' : ($n < $actual ? 'hecho' : '');
        echo '<div class="paso-p ' . $clase . '"><i>' . ($n < $actual ? '✓' : $n) . '</i>' . esc($t) . '</div>';
    }
    echo '</div>';
}

function pantallaBloqueada(): void
{
    $base = rtrim(str_replace('\\', '/', dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')))), '/');
    if ($base === '.' || $base === '/') { $base = ''; }
    cabecera('Instalación bloqueada', $base);
    ?>
    <div class="tarjeta">
      <div class="tarjeta-cuerpo">
        <div class="aviso-caja ok"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9"/><path d="m8 12.2 2.6 2.6L16 9.4"/></svg>
          <div><strong>El sistema ya está instalado</strong>
            Por seguridad, el instalador quedó bloqueado. Si necesita reinstalar, borre el archivo
            <code>/install/.lock</code> y el archivo <code>/config/config.php</code> desde el administrador de archivos de cPanel.</div>
        </div>
        <p class="mt-3"><a class="btn btn-oro" href="<?= esc($base) ?>/">Ir al sistema</a></p>
      </div>
    </div>
    <?php
    pie();
}

function pantallaFinal(array $datos, string $base): void
{
    $dominio = ($_SERVER['HTTPS'] ?? '') ? 'https://' : 'http://';
    $dominio .= (string) ($_SERVER['HTTP_HOST'] ?? 'sudominio.com');
    cabecera('Instalación completa', $base);
    ?>
    <div class="tarjeta">
      <div class="tarjeta-cuerpo">
        <div class="aviso-caja ok mb-3">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9"/><path d="m8 12.2 2.6 2.6L16 9.4"/></svg>
          <div><strong>¡Listo! ResidencialPro quedó instalado</strong>
            El instalador se bloqueó automáticamente. Ya puede ingresar al panel de administración.</div>
        </div>

        <h3>Sus datos de acceso</h3>
        <table class="tabla mb-3">
          <tbody>
            <tr><td>Usuario</td><td class="fuerte"><?= esc($datos['usuario']) ?></td></tr>
            <tr><td>Correo</td><td class="fuerte"><?= esc($datos['correo']) ?></td></tr>
            <tr><td>Contraseña</td><td>La que definió en el paso 3</td></tr>
          </tbody>
        </table>

        <h3>Tarea programada (cron)</h3>
        <p class="texto-2" style="font-size:.9rem">En cPanel &rarr; <em>Trabajos cron</em>, agregue esta línea cada 15 minutos.
        Es lo que envía los recordatorios de cobro, aplica la mora y crea los respaldos.</p>
        <pre style="background:var(--fondo-2);padding:14px;border-radius:var(--r-sm);overflow:auto;font-size:.8rem">*/15 * * * * curl -s "<?= esc($dominio . $base) ?>/cron/run.php?token=<?= esc($datos['cron']) ?>" &gt;/dev/null 2&gt;&amp;1</pre>

        <?php if ($datos['push']): ?>
          <div class="aviso-caja info mt-2"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
            <div>Las claves de notificaciones push se generaron automáticamente. Los residentes podrán activarlas desde su portal.</div>
          </div>
        <?php endif; ?>

        <?php if (!empty($datos['demo'])): ?>
          <div class="aviso-caja alerta mt-2"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M12 9.5v4M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
            <div><strong>Datos de demostración cargados</strong>
              Antes de usar el sistema con datos reales, elimine las viviendas, residentes y movimientos de ejemplo.</div>
          </div>
        <?php endif; ?>

        <div class="fila-fin mt-3">
          <a class="btn btn-claro" href="<?= esc($base) ?>/">Ver el sitio público</a>
          <a class="btn btn-oro btn-lg" href="<?= esc($base) ?>/acceso">Ingresar al panel</a>
        </div>
      </div>
    </div>
    <?php
    // Ya se mostró el resumen: se cierra el instalador también a nivel de Apache.
    @file_put_contents(__DIR__ . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    session_destroy();
    pie();
}

cabecera('Instalación', $base);
pasos($paso);

foreach ($errores as $er) {
    echo '<div class="aviso-caja error mb-2"><div>' . esc($er) . '</div></div>';
}
foreach ($avisos as $av) {
    echo '<div class="aviso-caja alerta mb-2"><div>' . esc($av) . '</div></div>';
}
?>

<?php if ($paso === 1): ?>
  <div class="tarjeta">
    <div class="tarjeta-cab"><h3>Paso 1 · Requisitos del servidor</h3></div>
    <div class="tarjeta-cuerpo">
      <p class="texto-2" style="font-size:.92rem">Verificamos que su hosting cumpla con lo necesario. Los elementos
        marcados como opcionales no impiden la instalación, pero limitan algunas funciones.</p>
      <?php foreach ($reqs as [$et, $valor, $ok, $obligatorio]): ?>
        <div class="req">
          <span class="chip <?= $ok ? 'ok' : ($obligatorio ? 'grave' : 'aviso') ?>"><?= $ok ? '✓' : '✕' ?></span>
          <div class="crecer">
            <?= esc($et) ?>
            <?php if (!$obligatorio): ?><small class="texto-3"> · opcional</small><?php endif; ?>
          </div>
          <span class="est texto-3"><?= esc($valor) ?></span>
        </div>
      <?php endforeach; ?>

      <?php if ($faltaObligatorio): ?>
        <div class="aviso-caja error mt-3"><div>
          Corrija los puntos marcados en rojo antes de continuar. Si se trata de permisos de carpeta,
          asigne <strong>755</strong> a <code>/config</code>, <code>/storage</code> y <code>/uploads</code>
          desde el administrador de archivos de cPanel.
        </div></div>
      <?php else: ?>
        <div class="fila-fin mt-3">
          <a class="btn btn-oro btn-lg" href="?paso=2">Continuar</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($paso === 2): ?>
  <div class="tarjeta">
    <div class="tarjeta-cab"><h3>Paso 2 · Base de datos</h3></div>
    <div class="tarjeta-cuerpo">
      <p class="texto-2" style="font-size:.92rem">Cree la base de datos y el usuario en cPanel &rarr;
        <em>Bases de datos MySQL</em>, y escriba aquí esos datos. Nosotros creamos las tablas.</p>
      <form method="post">
        <input type="hidden" name="accion" value="bd">
        <div class="campos">
          <div class="campo">
            <label for="host">Servidor</label>
            <input type="text" id="host" name="host" value="<?= esc($_POST['host'] ?? 'localhost') ?>" required>
            <span class="ayuda">En la mayoría de hostings es <code>localhost</code>.</span>
          </div>
          <div class="campo">
            <label for="puerto">Puerto</label>
            <input type="text" id="puerto" name="puerto" value="<?= esc($_POST['puerto'] ?? '3306') ?>">
          </div>
          <div class="campo">
            <label for="nombre">Nombre de la base de datos</label>
            <input type="text" id="nombre" name="nombre" value="<?= esc($_POST['nombre'] ?? '') ?>" required placeholder="cuenta_residencial">
          </div>
          <div class="campo">
            <label for="usuario">Usuario de la base de datos</label>
            <input type="text" id="usuario" name="usuario" value="<?= esc($_POST['usuario'] ?? '') ?>" required placeholder="cuenta_admin">
          </div>
          <div class="campo campo-ancho">
            <label for="clave">Contraseña de la base de datos</label>
            <input type="password" id="clave" name="clave" value="">
          </div>
        </div>
        <label class="marca-check mb-2">
          <input type="checkbox" name="demo" value="1" checked>
          <span>Cargar datos de demostración (Residencial Los Cipreses: 3 fases, 60 casas, cuotas, visitas y reservas).
            Sirven para conocer el sistema y se pueden borrar después.</span>
        </label>
        <label class="marca-check mb-3">
          <input type="checkbox" name="sobrescribir" value="1">
          <span>Reinstalar: si la base ya tiene tablas de ResidencialPro, bórrelas y empiece de cero.
            <strong>Esta acción elimina los datos existentes.</strong></span>
        </label>
        <div class="fila-fin">
          <a class="btn btn-claro" href="?paso=1">Atrás</a>
          <button class="btn btn-oro btn-lg" type="submit">Crear las tablas</button>
        </div>
      </form>
    </div>
  </div>

<?php else: ?>
  <div class="tarjeta">
    <div class="tarjeta-cab"><h3>Paso 3 · Condominio y administrador</h3></div>
    <div class="tarjeta-cuerpo">
      <form method="post">
        <input type="hidden" name="accion" value="final">
        <fieldset>
          <legend>Datos del residencial</legend>
          <div class="campos">
            <div class="campo campo-ancho">
              <label for="condominio">Nombre del condominio o residencial</label>
              <input type="text" id="condominio" name="condominio" required maxlength="120"
                     value="<?= esc($_POST['condominio'] ?? ($_SESSION['demo'] ?? false ? 'Residencial Los Cipreses' : '')) ?>"
                     placeholder="Residencial Los Cipreses">
            </div>
            <div class="campo">
              <label for="telefono">Teléfono</label>
              <input type="tel" id="telefono" name="telefono" value="<?= esc($_POST['telefono'] ?? '') ?>" placeholder="2222-3333">
            </div>
            <div class="campo">
              <label for="nit">NIT</label>
              <input type="text" id="nit" name="nit" value="<?= esc($_POST['nit'] ?? '') ?>" placeholder="1234567-8">
            </div>
            <div class="campo campo-ancho">
              <label for="direccion">Dirección</label>
              <input type="text" id="direccion" name="direccion" value="<?= esc($_POST['direccion'] ?? '') ?>"
                     placeholder="Km 15.5 Carretera a El Salvador, Santa Catarina Pinula">
            </div>
            <div class="campo">
              <label for="ciudad">Ciudad</label>
              <input type="text" id="ciudad" name="ciudad" value="<?= esc($_POST['ciudad'] ?? 'Ciudad de Guatemala') ?>">
            </div>
            <div class="campo">
              <label for="tema">Tema visual</label>
              <select id="tema" name="tema">
                <option value="verde-oro">Verde &amp; Oro</option>
                <option value="negro-oro">Negro &amp; Oro</option>
                <option value="azul-marino">Azul Marino</option>
                <option value="grafito">Grafito</option>
                <option value="borgona">Borgoña</option>
                <option value="azul-real">Azul Real</option>
                <option value="terracota">Terracota</option>
                <option value="purpura">Púrpura</option>
              </select>
              <span class="ayuda">Puede cambiarlo cuando quiera desde los ajustes.</span>
            </div>
          </div>
        </fieldset>

        <fieldset>
          <legend>Cuenta del administrador</legend>
          <div class="campos">
            <div class="campo">
              <label for="admin_nombre">Nombre completo</label>
              <input type="text" id="admin_nombre" name="admin_nombre" required value="<?= esc($_POST['admin_nombre'] ?? '') ?>">
            </div>
            <div class="campo">
              <label for="admin_correo">Correo electrónico</label>
              <input type="email" id="admin_correo" name="admin_correo" required value="<?= esc($_POST['admin_correo'] ?? '') ?>">
            </div>
            <div class="campo">
              <label for="admin_usuario">Usuario para ingresar</label>
              <input type="text" id="admin_usuario" name="admin_usuario" required pattern="[a-zA-Z0-9._\-]{4,60}"
                     value="<?= esc($_POST['admin_usuario'] ?? 'admin') ?>">
            </div>
            <div class="campo">
              <label for="admin_clave">Contraseña</label>
              <input type="password" id="admin_clave" name="admin_clave" required minlength="10">
              <span class="ayuda">Mínimo 10 caracteres. Guárdela en un lugar seguro.</span>
            </div>
          </div>
        </fieldset>

        <div class="fila-fin">
          <a class="btn btn-claro" href="?paso=2">Atrás</a>
          <button class="btn btn-oro btn-lg" type="submit">Finalizar instalación</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php pie(); ?>
