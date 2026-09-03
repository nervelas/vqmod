<?php
/**
 * Instalador web de Servicom.
 * Crea config/config.php, importa la base de datos y define el administrador.
 * Elimine esta carpeta cuando termine.
 */
declare(strict_types=1);

if (PHP_VERSION_ID < 80000) {
    exit('Se requiere PHP 8.0 o superior. Versión detectada: ' . PHP_VERSION);
}

$root       = dirname(__DIR__);
$configFile = $root . '/config/config.php';
$installed  = is_file($configFile);
$errors     = [];
$done       = false;

// Comprobaciones del servidor
$checks = [
    ['PHP 8.0 o superior', PHP_VERSION_ID >= 80000, PHP_VERSION],
    ['Extensión pdo_mysql', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'disponible' : 'falta'],
    ['Extensión mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'disponible' : 'falta'],
    ['Extensión fileinfo', extension_loaded('fileinfo'), extension_loaded('fileinfo') ? 'disponible' : 'falta'],
    ['Carpeta config/ escribible', is_writable($root . '/config'), is_writable($root . '/config') ? 'ok' : 'sin permisos'],
    ['Carpeta uploads/media escribible', is_writable($root . '/uploads/media'), is_writable($root . '/uploads/media') ? 'ok' : 'sin permisos'],
    ['Carpeta storage/ escribible', is_writable($root . '/storage'), is_writable($root . '/storage') ? 'ok' : 'sin permisos'],
];
$ready = array_reduce($checks, static fn($c, $x) => $c && $x[1], true);


/** Separa un archivo .sql en sentencias, respetando las comillas simples. */
function sql_statements(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $out = [];
    $cur = '';
    $inStr = false;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($c === "'") {
            if ($inStr && ($sql[$i + 1] ?? '') === "'") { $cur .= "''"; $i++; continue; }
            $inStr = !$inStr;
        }
        if ($c === ';' && !$inStr) {
            if (trim($cur) !== '') { $out[] = trim($cur); }
            $cur = '';
            continue;
        }
        $cur .= $c;
    }
    if (trim($cur) !== '') { $out[] = trim($cur); }
    return $out;
}

$v = static fn(string $k, string $d = ''): string => isset($_POST[$k]) && is_string($_POST[$k]) ? trim($_POST[$k]) : $d;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    $host = $v('db_host', 'localhost');
    $port = $v('db_port', '3306');
    $name = $v('db_name');
    $user = $v('db_user');
    $pass = (string) ($_POST['db_pass'] ?? '');
    $site = rtrim($v('site_url'), '/');
    $aName = $v('admin_name');
    $aUser = $v('admin_user');
    $aMail = $v('admin_email');
    $aPass = (string) ($_POST['admin_pass'] ?? '');

    if ($name === '' || $user === '') { $errors[] = 'Complete los datos de la base de datos.'; }
    if ($site === '' || !filter_var($site, FILTER_VALIDATE_URL)) { $errors[] = 'Escriba la dirección completa del sitio, incluyendo https://'; }
    if ($aName === '' || $aUser === '') { $errors[] = 'Complete el nombre y el usuario del administrador.'; }
    if (!filter_var($aMail, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Escriba un correo electrónico válido para el administrador.'; }
    if (strlen($aPass) < 8) { $errors[] = 'La contraseña del administrador debe tener al menos 8 caracteres.'; }

    $pdo = null;
    if ($errors === []) {
        try {
            $pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (Throwable $ex) {
            $errors[] = 'No se pudo conectar a la base de datos: ' . $ex->getMessage();
        }
    }

    if ($errors === [] && $pdo instanceof PDO) {
        try {
            foreach (['schema.sql', 'seed.sql'] as $file) {
                $sql = file_get_contents($root . '/database/' . $file);
                if ($sql === false) { throw new RuntimeException('No se encontró database/' . $file); }
                foreach (sql_statements($sql) as $stmt) {
                    try {
                        $pdo->exec($stmt);
                    } catch (Throwable $e) {
                        throw new RuntimeException('Al importar ' . $file . ': ' . $e->getMessage()
                            . ' — sentencia: ' . substr(preg_replace('/\s+/', ' ', $stmt), 0, 140));
                    }
                }
            }

            // Administrador definido en el formulario
            $pdo->exec('DELETE FROM `users`');
            $stmt = $pdo->prepare('INSERT INTO `users` (`name`,`username`,`email`,`password`,`role`,`status`,`created_at`) VALUES (?,?,?,?,\'admin\',1,?)');
            $stmt->execute([$aName, $aUser, $aMail, password_hash($aPass, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);

            // Correo de contacto = correo del administrador si no se cambió
            $stmt = $pdo->prepare('UPDATE `settings` SET `value` = ? WHERE `key` = \'email\'');
            $stmt->execute([$aMail]);

            $basePath = rtrim((string) parse_url($site, PHP_URL_PATH), '/');

            $config = "<?php\ndeclare(strict_types=1);\n\n"
                . "// Generado por el instalador de Servicom el " . date('d/m/Y H:i') . "\n\n"
                . "define('DB_DRIVER', 'mysql');\n"
                . "define('DB_HOST',   " . var_export($host, true) . ");\n"
                . "define('DB_PORT',   " . var_export($port, true) . ");\n"
                . "define('DB_NAME',   " . var_export($name, true) . ");\n"
                . "define('DB_USER',   " . var_export($user, true) . ");\n"
                . "define('DB_PASS',   " . var_export($pass, true) . ");\n"
                . "define('DB_CHARSET','utf8mb4');\n"
                . "define('DB_FILE',   '');\n\n"
                . "define('SITE_URL',  " . var_export($site, true) . ");\n"
                . "define('BASE_PATH', " . var_export($basePath, true) . ");\n\n"
                . "define('APP_KEY',   " . var_export(bin2hex(random_bytes(24)), true) . ");\n"
                . "define('APP_DEBUG', false);\n"
                . "define('SESSION_NAME', 'servicom_sess');\n\n"
                . "define('MAIL_TO',   " . var_export($aMail, true) . ");\n"
                . "define('MAIL_FROM', " . var_export('no-reply@' . (parse_url($site, PHP_URL_HOST) ?: 'localhost'), true) . ");\n";

            if (file_put_contents($configFile, $config) === false) {
                throw new RuntimeException('No se pudo escribir config/config.php. Revise los permisos de la carpeta config/.');
            }
            @chmod($configFile, 0640);
            $done = true;
        } catch (Throwable $ex) {
            $errors[] = 'Error durante la instalación: ' . $ex->getMessage();
        }
    }
}

$esc = static fn(mixed $x): string => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Instalación de Servicom</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#f4f6fa;color:#0f1729;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;line-height:1.65;padding:2rem 1rem}
.box{max-width:760px;margin:0 auto;background:#fff;border:1px solid #e2e8f2;border-radius:16px;padding:2rem;box-shadow:0 20px 50px -30px rgba(15,23,41,.4)}
h1{margin:0 0 .3rem;font-size:1.5rem;letter-spacing:-.02em}
h2{font-size:1.05rem;margin:1.8rem 0 .8rem;padding-bottom:.5rem;border-bottom:1px solid #e2e8f2}
p.sub{color:#5d6b85;margin:0 0 1.5rem}
.grid{display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr))}
label{display:block;font-size:.85rem;font-weight:600;margin-bottom:.3rem}
input{width:100%;padding:.6rem .75rem;border:1px solid #e2e8f2;border-radius:9px;font:inherit}
input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
.hint{font-size:.79rem;color:#5d6b85;margin-top:.25rem}
.btn{display:inline-flex;align-items:center;gap:.5rem;background:#2563eb;color:#fff;border:0;padding:.75rem 1.4rem;border-radius:9px;font-weight:600;cursor:pointer;font-size:.95rem;margin-top:1.5rem}
.btn:hover{filter:brightness(1.08)}
.btn[disabled]{opacity:.5;cursor:not-allowed}
ul.checks{list-style:none;padding:0;margin:0;display:grid;gap:.4rem;font-size:.9rem}
ul.checks li{display:flex;gap:.6rem;align-items:center}
.ok{color:#16a34a;font-weight:700}.bad{color:#dc2626;font-weight:700}
.alert{padding:.9rem 1.1rem;border-radius:10px;margin-bottom:1.2rem;font-size:.92rem}
.alert--error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.alert--ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
code{background:#f1f5f9;padding:.1rem .35rem;border-radius:4px;font-size:.86rem}
</style>
</head>
<body>
<div class="box">
<?php if ($done): ?>
  <h1>¡Instalación completada!</h1>
  <div class="alert alert--ok">
    El sitio quedó listo. <strong>Por seguridad, elimine ahora la carpeta <code>install/</code> del servidor.</strong>
  </div>
  <p>Ya puede:</p>
  <ul>
    <li><a href="<?= $esc(rtrim($v('site_url'), '/')) ?>/">Ver el sitio publicado</a></li>
    <li><a href="<?= $esc(rtrim($v('site_url'), '/')) ?>/admin/">Entrar al panel de administración</a> con el usuario <code><?= $esc($v('admin_user')) ?></code></li>
  </ul>
  <h2>Siguientes pasos recomendados</h2>
  <ol>
    <li>Suba su logotipo real en <em>Datos del sitio → Identidad</em>.</li>
    <li>Revise teléfonos, correo y redes sociales.</li>
    <li>Elija el tema visual en <em>Temas visuales</em>.</li>
    <li>Reemplace las tarjetas del portafolio por proyectos reales.</li>
    <li>Publique testimonios reales de sus clientes.</li>
    <li>Envíe <code>/sitemap.xml</code> a Google Search Console.</li>
  </ol>

<?php elseif ($installed): ?>
  <h1>El sitio ya está instalado</h1>
  <div class="alert alert--ok">Existe el archivo <code>config/config.php</code>. <strong>Elimine la carpeta <code>install/</code> del servidor.</strong></div>
  <p>Si necesita reinstalar, borre <code>config/config.php</code> y vuelva a cargar esta página.</p>
  <p><a href="../admin/">Ir al panel de administración →</a></p>

<?php else: ?>
  <h1>Instalación de Servicom</h1>
  <p class="sub">Complete los datos y el instalador creará la base de datos y su usuario administrador.</p>

  <?php if ($errors !== []): ?>
    <div class="alert alert--error">
      <?php foreach ($errors as $er): ?><div><?= $esc($er) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h2>Requisitos del servidor</h2>
  <ul class="checks">
    <?php foreach ($checks as [$label, $pass, $detail]): ?>
      <li><span class="<?= $pass ? 'ok' : 'bad' ?>"><?= $pass ? '✓' : '✕' ?></span> <?= $esc($label) ?> <span class="hint">(<?= $esc($detail) ?>)</span></li>
    <?php endforeach; ?>
  </ul>

  <form method="post">
    <h2>Base de datos MySQL</h2>
    <div class="grid">
      <div><label for="db_host">Servidor</label><input id="db_host" name="db_host" value="<?= $esc($v('db_host', 'localhost')) ?>" required></div>
      <div><label for="db_port">Puerto</label><input id="db_port" name="db_port" value="<?= $esc($v('db_port', '3306')) ?>"></div>
      <div><label for="db_name">Nombre de la base</label><input id="db_name" name="db_name" value="<?= $esc($v('db_name')) ?>" required></div>
      <div><label for="db_user">Usuario</label><input id="db_user" name="db_user" value="<?= $esc($v('db_user')) ?>" required></div>
      <div><label for="db_pass">Contraseña</label><input id="db_pass" name="db_pass" type="password"></div>
    </div>
    <p class="hint">Cree antes la base de datos y el usuario desde cPanel, y asigne todos los privilegios.</p>

    <h2>Dirección del sitio</h2>
    <div><label for="site_url">URL completa</label>
      <input id="site_url" name="site_url" value="<?= $esc($v('site_url', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'servicom.gt'))) ?>" required>
      <p class="hint">Sin barra final. Ejemplo: <code>https://servicom.gt</code></p>
    </div>

    <h2>Usuario administrador</h2>
    <div class="grid">
      <div><label for="admin_name">Nombre completo</label><input id="admin_name" name="admin_name" value="<?= $esc($v('admin_name')) ?>" required></div>
      <div><label for="admin_user">Usuario</label><input id="admin_user" name="admin_user" value="<?= $esc($v('admin_user', 'admin')) ?>" required></div>
      <div><label for="admin_email">Correo electrónico</label><input id="admin_email" name="admin_email" type="email" value="<?= $esc($v('admin_email')) ?>" required></div>
      <div><label for="admin_pass">Contraseña</label><input id="admin_pass" name="admin_pass" type="password" minlength="8" required>
        <p class="hint">Mínimo 8 caracteres. Use mayúsculas, números y símbolos.</p></div>
    </div>

    <button class="btn" type="submit" <?= $ready ? '' : 'disabled' ?>>Instalar ahora</button>
    <?php if (!$ready): ?><p class="hint">Corrija los requisitos marcados en rojo antes de continuar.</p><?php endif; ?>
  </form>
<?php endif; ?>
</div>
</body>
</html>
