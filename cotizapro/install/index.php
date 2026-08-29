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
            $adminName  = mb_substr(Request::str('admin_name'), 0, 120) ?: 'Superadministrador';
            $adminEmail = Request::email('admin_email');
            $adminPass  = Request::raw('admin_pass');
            $siteUrl    = rtrim(Request::str('site_url'), '/');
            $withDemo   = Request::bool('demo');

            if ($adminEmail === '') {
                $errors[] = 'Escriba un correo válido para el superadministrador.';
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

                    // Datos base y demostración (antes del superadministrador).
                    seedBase($pdo);
                    if ($withDemo && is_file(BASE_PATH . '/database/database_demo.sql')) {
                        runSql($pdo, (string) file_get_contents(BASE_PATH . '/database/database_demo.sql'));
                    }

                    // Superadministrador
                    $hash = Security::hashPassword($adminPass);
                    $st = $pdo->prepare('INSERT INTO users (company_id,name,email,password,role,status,created_at) VALUES (NULL,?,?,?,"superadmin","activo",NOW())
                                         ON DUPLICATE KEY UPDATE password = VALUES(password), role = "superadmin", status = "activo"');
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

/** Planes y textos por defecto de la plataforma. */
function seedBase(PDO $pdo): void
{
    $n = (int) $pdo->query('SELECT COUNT(*) FROM plans')->fetchColumn();
    if ($n === 0) {
        $plans = [
            ['basico', 'Básico', 'Para empezar a cotizar en línea', 295, 2950, 200, 2, 150, 0, 1,
                ['Catálogo con 200 productos', 'Cotizador en línea', 'PDF con su marca', '2 usuarios', 'Seguimiento por enlace']],
            ['pro', 'Pro', 'El plan de la mayoría', 545, 5450, 1500, 6, 600, 1, 2,
                ['Catálogo con 1,500 productos', 'Kanban de cotizaciones', 'Listas de precios por cliente', '6 usuarios', 'Importador de WooCommerce', 'Reportes y exportación']],
            ['premium', 'Premium', 'Catálogo grande y varios vendedores', 895, 8950, 0, 0, 0, 0, 3,
                ['Productos ilimitados', 'Usuarios ilimitados', 'Dominio propio', 'Recordatorios automáticos', 'Respaldos y auditoría', 'Soporte prioritario']],
        ];
        $st = $pdo->prepare('INSERT INTO plans (code,name,tagline,price_month,price_year,max_products,max_users,max_quotes_month,highlight,sort,features,active,created_at)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,1,NOW())');
        foreach ($plans as $p) {
            $st->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9], json_encode($p[10], JSON_UNESCAPED_UNICODE)]);
        }
    }
    $sets = [
        'platform_name'    => 'CotizaPro B2B',
        'platform_tagline' => 'Catálogo, cotizador y seguimiento para empresas que venden por cotización.',
        'whatsapp_message' => 'Hola, me interesa el sistema de catálogo y cotizaciones. ¿Me comparten información?',
        'landing_hero_kicker' => 'Catálogo · Cotizador · Seguimiento',
        'landing_hero_title'  => 'Su catálogo|cotiza solo.|Usted cierra.',
        'landing_hero_sub'    => 'El sistema para empresas que no venden con tarjeta: catálogo técnico en línea, cotizador para el cliente y un tablero donde ninguna cotización se pierde.',
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
