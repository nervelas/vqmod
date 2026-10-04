<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Config;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\Migrator;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use PDO;

/** Lógica del instalador (web y CLI). */
final class InstallService
{
    public static function configPath(): string
    {
        return (string) (getenv('AP_CONFIG') ?: APP_ROOT . '/config/config.php');
    }

    public static function lockPath(): string
    {
        return dirname(self::configPath()) . '/installed.lock';
    }

    public static function isLocked(): bool
    {
        return is_file(self::lockPath()) && Config::installed();
    }

    /** @return array<int,array{name:string,ok:bool,required:bool,detail:string}> */
    public static function requirements(): array
    {
        $dirs = ['config', 'storage', 'storage/uploads', 'storage/logs', 'storage/cache', 'storage/backups', 'storage/sessions'];
        $out = [];
        $out[] = ['name' => 'PHP 8.0 o superior', 'ok' => PHP_VERSION_ID >= 80000, 'required' => true, 'detail' => 'Versión actual: ' . PHP_VERSION];
        foreach (['pdo_mysql', 'mbstring', 'json', 'fileinfo', 'openssl', 'ctype'] as $ext) {
            $out[] = ['name' => 'Extensión ' . $ext, 'ok' => extension_loaded($ext), 'required' => true, 'detail' => extension_loaded($ext) ? 'Disponible' : 'Falta; pídela a tu hosting'];
        }
        $img = extension_loaded('gd') || extension_loaded('imagick');
        $out[] = ['name' => 'Extensión gd o imagick', 'ok' => $img, 'required' => false, 'detail' => $img ? 'Disponible (se optimizarán las imágenes)' : 'Recomendada para optimizar imágenes'];
        $curl = extension_loaded('curl');
        $out[] = ['name' => 'Extensión curl', 'ok' => $curl, 'required' => false, 'detail' => $curl ? 'Disponible' : 'Recomendada para calendarios externos, webhooks y WhatsApp'];
        foreach ($dirs as $d) {
            $p = APP_ROOT . '/' . $d;
            if (!is_dir($p)) {
                @mkdir($p, 0750, true);
            }
            $ok = is_dir($p) && is_writable($p);
            $out[] = ['name' => 'Permiso de escritura en /' . $d, 'ok' => $ok, 'required' => true, 'detail' => $ok ? 'Correcto' : 'Da permisos de escritura (755 o 775) a esta carpeta'];
        }
        return $out;
    }

    /** Prueba la conexión y devuelve null si todo bien, o un mensaje amable. */
    public static function testDb(array $db): ?string
    {
        try {
            $dsn = 'mysql:host=' . $db['host'] . (!empty($db['port']) ? ';port=' . (int) $db['port'] : '') . ';charset=utf8mb4';
            $pdo = new PDO($dsn, (string) $db['user'], (string) $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 6]);
            $ver = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            if (stripos($ver, 'mariadb') === false && version_compare($ver, '5.7.0', '<')) {
                return 'Tu MySQL es ' . $ver . '; se necesita 5.7 o superior.';
            }
            try {
                $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', (string) $db['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            } catch (\Throwable $e) {
                // sin permiso para crear: la base ya debe existir
            }
            new PDO($dsn . ';dbname=' . $db['name'], (string) $db['user'], (string) $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 6]);
            return null;
        } catch (\Throwable $e) {
            $m = $e->getMessage();
            if (stripos($m, 'Access denied') !== false) {
                return 'Usuario o contraseña de la base de datos incorrectos.';
            }
            if (stripos($m, 'Unknown database') !== false) {
                return 'La base de datos no existe y no tenemos permiso para crearla. Créala desde tu hosting e inténtalo otra vez.';
            }
            if (stripos($m, 'getaddrinfo') !== false || stripos($m, 'Connection refused') !== false || stripos($m, 'php_network') !== false) {
                return 'No se pudo conectar con el servidor de base de datos. Revisa el servidor (host) y el puerto.';
            }
            return 'No se pudo conectar con la base de datos. Revisa los datos e inténtalo de nuevo.';
        }
    }

    /**
     * Instalación completa.
     * $in: db{host,port,name,user,pass}, business{name,email,phone,whatsapp,timezone}, admin{name,email,password}, profession, demo, base_url
     * @return array{admin_id:int,host_id:int,cron_token:string}
     */
    public static function install(array $in): array
    {
        $db = $in['db'];
        $err = self::testDb($db);
        if ($err !== null) {
            throw new \RuntimeException($err);
        }
        $pwErr = Crypto::passwordStrongEnough((string) $in['admin']['password']);
        if ($pwErr !== null) {
            throw new \RuntimeException($pwErr);
        }
        if (!filter_var($in['admin']['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('El correo del administrador no es válido.');
        }
        $tz = Tz::safe((string) ($in['business']['timezone'] ?? 'America/Guatemala'));

        Config::set('db', $db);
        Db::reset();
        Db::connect($db);
        $appKey = base64_encode(random_bytes(32));
        Config::set('app_key', $appKey);
        Migrator::run();
        Settings::flush();

        $cronToken = Str::token(24);
        $biz = $in['business'];
        Settings::setMany([
            'business_name' => Str::clean((string) $biz['name'], 120),
            'email' => Str::clean((string) ($biz['email'] ?? ''), 190),
            'phone' => (string) Str::phone((string) ($biz['phone'] ?? '')),
            'whatsapp' => (string) Str::phone((string) ($biz['whatsapp'] ?? '')),
            'timezone' => $tz,
            'cron_token' => $cronToken,
            'mail_from_name' => Str::clean((string) $biz['name'], 120),
            'mail_from_email' => Str::clean((string) ($biz['email'] ?? ''), 190),
            'admin_notify_email' => Str::clean((string) $in['admin']['email'], 190),
            'installed_version' => AP_VERSION,
        ]);

        $now = Clock::utc();
        $adminId = Db::insert('users', [
            'name' => Str::clean((string) $in['admin']['name'], 120),
            'email' => strtolower(trim((string) $in['admin']['email'])),
            'password_hash' => Crypto::hashPassword((string) $in['admin']['password']),
            'role' => 'admin',
            'active' => 1,
            'theme' => 'dark',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $scheduleId = Db::insert('schedules', ['name' => 'Horario laboral', 'timezone' => $tz, 'is_default' => 1, 'created_at' => $now]);
        foreach ([1, 2, 3, 4, 5] as $wd) {
            Db::insert('schedule_rules', ['schedule_id' => $scheduleId, 'weekday' => $wd, 'start_time' => '09:00:00', 'end_time' => '13:00:00']);
            Db::insert('schedule_rules', ['schedule_id' => $scheduleId, 'weekday' => $wd, 'start_time' => '14:00:00', 'end_time' => '18:00:00']);
        }
        Db::insert('schedule_rules', ['schedule_id' => $scheduleId, 'weekday' => 6, 'start_time' => '09:00:00', 'end_time' => '12:00:00']);

        $hostId = Db::insert('hosts', [
            'user_id' => $adminId,
            'name' => Str::clean((string) $in['admin']['name'], 120),
            'slug' => Str::uniqueSlug('hosts', (string) $in['admin']['name']),
            'timezone' => $tz,
            'email' => strtolower(trim((string) $in['admin']['email'])),
            'phone' => (string) Str::phone((string) ($biz['phone'] ?? '')),
            'whatsapp' => (string) Str::phone((string) ($biz['whatsapp'] ?? '')),
            'ics_token' => Str::token(16),
            'schedule_id' => $scheduleId,
            'created_at' => $now,
        ]);

        $year = (int) gmdate('Y');
        HolidayService::ensureYear($year);
        HolidayService::ensureYear($year + 1);

        if (class_exists('App\\Services\\LegalService')) {
            \App\Services\LegalService::save(\App\Services\LegalService::defaults());
        }
        if (class_exists('App\\Services\\ProfessionService')) {
            \App\Services\ProfessionService::apply((string) ($in['profession'] ?? 'otro'), !empty($in['demo']));
        }

        $cfg = ['db' => $db, 'app_key' => $appKey, 'base_url' => (string) ($in['base_url'] ?? ''), 'trust_proxy' => false, 'allow_private_http' => false, 'installed_at' => $now];
        self::writeConfig($cfg);
        @file_put_contents(self::lockPath(), "Instalado el {$now} UTC. Borra la carpeta /instalar.\n");
        @chmod(self::lockPath(), 0640);
        return ['admin_id' => $adminId, 'host_id' => $hostId, 'cron_token' => $cronToken];
    }

    public static function writeConfig(array $cfg): void
    {
        $path = self::configPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $php = "<?php\n// Generado por el instalador. No lo compartas: contiene las claves del sistema.\nreturn " . var_export($cfg, true) . ";\n";
        if (@file_put_contents($path, $php, LOCK_EX) === false) {
            throw new \RuntimeException('No se pudo escribir la configuración. Da permisos de escritura a la carpeta config.');
        }
        @chmod($path, 0640);
        Config::load($path);
    }
}
