<?php
declare(strict_types=1);

// Utilidades mínimas de pruebas (sin dependencias). Cada archivo tests/cases/*_test.php es un script independiente.
final class T
{
    public static int $pass = 0;
    public static int $fail = 0;
    public static string $db = '';

    /** Crea una base limpia ap_t_<nombre> e instala el sistema base. */
    public static function boot(string $name, array $opts = []): void
    {
        $cfg = '/tmp/ap-t-' . $name . '.config.php';
        putenv('AP_CONFIG=' . $cfg);
        @unlink($cfg);
        @unlink('/tmp/installed.lock');
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['HTTP_HOST'] = '127.0.0.1:8190';
        require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
        self::$db = 'ap_t_' . $name;
        $db = ['host' => '127.0.0.1', 'port' => 3306, 'name' => self::$db, 'user' => 'ap', 'pass' => 'ap_test_pw'];
        $pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'ap', 'ap_test_pw', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('DROP DATABASE IF EXISTS `' . self::$db . '`');
        $pdo->exec('CREATE DATABASE `' . self::$db . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        \App\Services\InstallService::install([
            'db' => $db,
            'business' => ['name' => 'Negocio de Prueba', 'email' => 'negocio@example.test', 'phone' => '55551234', 'whatsapp' => '55551234', 'timezone' => 'America/Guatemala'],
            'admin' => ['name' => 'Admin Prueba', 'email' => 'admin@test.local', 'password' => 'Prueba#Segura2026'],
            'profession' => $opts['profession'] ?? 'otro',
            'demo' => !empty($opts['demo']),
            'base_url' => '',
        ]);
        \App\Core\Config::set('allow_private_http', !empty($opts['allow_private_http']));
    }

    public static function section(string $title): void
    {
        echo "\n## {$title}\n";
    }

    public static function ok($cond, string $msg): void
    {
        if ($cond) {
            self::$pass++;
            echo "  ok   {$msg}\n";
        } else {
            self::$fail++;
            echo "  FAIL {$msg}\n";
        }
    }

    public static function eq($expected, $actual, string $msg): void
    {
        $ok = $expected === $actual;
        self::ok($ok, $msg . ($ok ? '' : ' (esperado ' . json_encode($expected) . ', obtenido ' . json_encode($actual) . ')'));
    }

    /** Verifica que $fn lance una excepción (opcionalmente de cierta clase y/o con texto). */
    public static function throws(callable $fn, string $msg, ?string $class = null, ?string $contains = null): void
    {
        try {
            $fn();
            self::ok(false, $msg . ' (no lanzó excepción)');
        } catch (\Throwable $e) {
            $ok = ($class === null || $e instanceof $class) && ($contains === null || stripos($e->getMessage(), $contains) !== false);
            self::ok($ok, $msg . ($ok ? '' : ' (lanzó ' . get_class($e) . ': ' . $e->getMessage() . ')'));
        }
    }

    public static function done(): void
    {
        echo "\nResultado: " . self::$pass . ' correctas, ' . self::$fail . " fallidas\n";
        exit(self::$fail > 0 ? 1 : 0);
    }
}
