<?php
declare(strict_types=1);
/**
 * Instalador en tres pasos. Se autobloquea creando install/.lock.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\App;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Request;
use App\Core\Security;

App::setBasePath(rtrim(str_replace('/install/index.php', '', str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/'));
App::startSession();

$lock = BASE_PATH . '/install/.lock';
$configFile = CONFIG_PATH . '/config.php';
$installed = is_file($lock) && is_file($configFile);

$step   = max(1, min(3, (int) ($_GET['paso'] ?? 1)));
$errors = [];
$ok     = [];

/* ------------------------------------------------------ verificaciones */
function checks(): array
{
    $writable = static fn (string $p): bool => is_dir($p) ? is_writable($p) : @mkdir($p, 0755, true);
    return [
        ['PHP 8.0 o superior', PHP_VERSION_ID >= 80000, PHP_VERSION],
        ['Extensión PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'activa' : 'falta'],
        ['Extensión mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'activa' : 'falta'],
        ['Extensión GD (imágenes)', extension_loaded('gd'), extension_loaded('gd') ? 'activa' : 'falta'],
        ['Soporte WebP', function_exists('imagewebp'), function_exists('imagewebp') ? 'sí' : 'no (se usará JPG)'],
        ['Extensión fileinfo', extension_loaded('fileinfo'), extension_loaded('fileinfo') ? 'activa' : 'falta'],
        ['Extensión zip (Excel)', class_exists('ZipArchive'), class_exists('ZipArchive') ? 'activa' : 'falta'],
        ['Extensión openssl', extension_loaded('openssl'), extension_loaded('openssl') ? 'activa' : 'falta'],
        ['Carpeta /config escribible', $writable(CONFIG_PATH), CONFIG_PATH],
        ['Carpeta /storage escribible', $writable(STORAGE_PATH . '/logs') && $writable(STORAGE_PATH . '/uploads') && $writable(STORAGE_PATH . '/backups'), STORAGE_PATH],
        ['Archivo database/database.sql', is_file(BASE_PATH . '/database/database.sql'), 'esquema'],
        ['Reescritura de URL (mod_rewrite)', function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules(), true) : true, 'verifique .htaccess'],
    ];
}

/* ------------------------------------------------------------ acciones */
if (Request::isPost() && !$installed) {
    if (!Csrf::check()) {
        $errors[] = 'La sesión expiró. Vuelva a intentarlo.';
    } elseif (Request::str('accion') === 'probar') {
        try {
            $pdo = DB::connect(Request::str('db_host', 'localhost'), Request::str('db_name'), Request::str('db_user'), Request::raw('db_pass'), Request::str('db_port', '3306'));
            $_SESSION['inst_db'] = [
                'host' => Request::str('db_host', 'localhost'),
                'name' => Request::str('db_name'),
                'user' => Request::str('db_user'),
                'pass' => Request::raw('db_pass'),
                'port' => Request::str('db_port', '3306'),
            ];
            $ok[] = 'Conexión correcta con ' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . '.';
            $step = 3;
        } catch (\Throwable $e) {
            $errors[] = 'No se pudo conectar: ' . $e->getMessage();
            $step = 2;
        }
    } elseif (Request::str('accion') === 'instalar') {
        $db = $_SESSION['inst_db'] ?? null;
        if (!$db) {
            $errors[] = 'Se perdieron los datos de conexión. Repita el paso 2.';
            $step = 2;
        } else {
            $companyName = mb_substr(Request::str('company_name'), 0, 140);
            $adminName  = mb_substr(Request::str('admin_name'), 0, 120) ?: 'Administrador';
            $adminEmail = Request::email('admin_email');
            $adminPass  = Request::raw('admin_pass');
            $siteUrl    = rtrim(Request::str('site_url'), '/');
            $withDemo   = Request::bool('demo');

            if ($companyName === '') {
                $errors[] = 'Escriba el nombre de su empresa.';
            }
            if ($adminEmail === '') {
                $errors[] = 'Escriba un correo válido para el administrador.';
            }
            if (!Security::passwordOk($adminPass)) {
                $errors[] = 'La contraseña debe tener 8+ caracteres, con mayúsculas, minúsculas y números.';
            }
            if (!$errors) {
                try {
                    $pdo = DB::connect($db['host'], $db['name'], $db['user'], $db['pass'], $db['port']);
                    $pdo->exec('SET NAMES utf8mb4');
                    runSql($pdo, (string) file_get_contents(BASE_PATH . '/database/database.sql'));

                    $appKey    = bin2hex(random_bytes(32));
                    $cronToken = bin2hex(random_bytes(24));
                    $config = "<?php\n"
                        . "// Generado por el instalador el " . date('Y-m-d H:i') . ". No compartir este archivo.\n"
                        . "return [\n"
                        . "    'db_host'    => " . var_export($db['host'], true) . ",\n"
                        . "    'db_port'    => " . var_export($db['port'], true) . ",\n"
                        . "    'db_name'    => " . var_export($db['name'], true) . ",\n"
                        . "    'db_user'    => " . var_export($db['user'], true) . ",\n"
                        . "    'db_pass'    => " . var_export($db['pass'], true) . ",\n"
                        . "    'app_key'    => " . var_export($appKey, true) . ",\n"
                        . "    'cron_token' => " . var_export($cronToken, true) . ",\n"
                        . "    'site_url'   => " . var_export($siteUrl, true) . ",\n"
                        . "    'installed'  => " . var_export(date('c'), true) . ",\n"
                        . "];\n";
                    if (@file_put_contents($configFile, $config) === false) {
                        throw new \RuntimeException('No se pudo escribir config/config.php. Dé permisos de escritura a la carpeta /config.');
                    }
                    @chmod($configFile, 0640);

                    // Datos de demostración (antes de la empresa y el administrador).
                    if ($withDemo && is_file(BASE_PATH . '/database/database_demo.sql')) {
                        runSql($pdo, (string) file_get_contents(BASE_PATH . '/database/database_demo.sql'));
                    }
                    seedBase($pdo, $companyName, $adminEmail);

                    // Administrador de la empresa
                    $hash = Security::hashPassword($adminPass);
                    $st = $pdo->prepare('INSERT INTO users (name,email,password,role,status,created_at) VALUES (?,?,?,"admin","activo",NOW())
                                         ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), role = "admin", status = "activo"');
                    $st->execute([$adminName, $adminEmail, $hash]);

                    @file_put_contents($lock, 'Instalado el ' . date('c') . "\n");
                    unset($_SESSION['inst_db']);
                    header('Location: ' . url('/install/?listo=1'), true, 302);
                    exit;
                } catch (\Throwable $e) {
                    $errors[] = 'Error al instalar: ' . $e->getMessage();
                    $step = 3;
                }
            } else {
                $step = 3;
            }
        }
    }
}

/** Ejecuta un archivo SQL sentencia por sentencia. */
function runSql(PDO $pdo, string $sql): void
{
    $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
    $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]/', $sql) ?: []));
    foreach ($statements as $s) {
        if ($s === '' || str_starts_with($s, '/*')) {
            continue;
        }
        $pdo->exec($s);
    }
}

/** Crea (o completa) la fila única de la empresa y los ajustes del sistema. */
function seedBase(PDO $pdo, string $companyName, string $adminEmail): void
{
    $exists = (int) $pdo->query('SELECT COUNT(*) FROM company')->fetchColumn() > 0;
    if ($exists) {
        // Los datos demo ya crearon la empresa: solo se renombra si el instalador trae otro nombre.
        $st = $pdo->prepare('UPDATE company SET name = ?, updated_at = NOW() WHERE id = 1');
        $st->execute([$companyName]);
    } else {
        $st = $pdo->prepare(
            'INSERT INTO company (id,name,legal_name,email,theme,color_accent,color_ink,color_paper,
                                  currency_symbol,tax_rate,tax_label,price_visibility,quote_prefix,quote_next,
                                  quote_year,quote_pad,validity_days,tagline,about,created_at,updated_at)
             VALUES (1,?,?,?,"acero","#E8590C","#1C1F22","#F5F6F4","Q",12.000,"IVA","oculto","COT",1,?,4,15,?,?,NOW(),NOW())'
        );
        $st->execute([
            $companyName,
            $companyName,
            $adminEmail,
            (int) date('Y'),
            'Catálogo técnico y cotizaciones en línea',
            'Escriba aquí la historia de ' . $companyName . ' desde Configuración → Identidad.',
        ]);
    }
    $pdo->exec('INSERT INTO price_lists (name, discount_pct, is_default)
                SELECT "General", 0, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM price_lists)');

    $sets = [
        'app_name' => 'CotizaPro B2B',
    ];
    $st = $pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value` = `value`');
    foreach ($sets as $k => $v) {
        $st->execute([$k, $v]);
    }
}

$checks   = checks();
$allOk    = !in_array(false, array_column($checks, 1), true);
$done     = isset($_GET['listo']);
$token    = Csrf::token();
$guessUrl = (App::isHttps() ? 'https://' : 'http://') . App::host() . App::basePath();
require __DIR__ . '/view.php';
