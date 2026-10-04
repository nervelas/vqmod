<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Clock;
use App\Core\Config;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Migrator;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Validator;

/** Estado del sistema: requisitos, base de datos, cron, colas, almacenamiento y registro de errores. */
final class SystemStatusController extends A4Controller
{
    private const EXT_REQUIRED = ['pdo_mysql' => 'Base de datos', 'mbstring' => 'Textos en español', 'json' => 'Intercambio de datos', 'fileinfo' => 'Verificación de archivos subidos', 'openssl' => 'Cifrado de claves'];
    private const EXT_OPTIONAL = ['gd' => 'Optimización de imágenes (o Imagick)', 'curl' => 'Webhooks, calendarios externos y WhatsApp', 'zip' => 'Exportaciones comprimidas', 'intl' => 'Formatos regionales'];

    public function index(Request $req, array $p): Response
    {
        $log = array_reverse(Logger::tail(200));
        $filter = $req->str('log', 80);
        if ($filter !== '') {
            $log = array_values(array_filter($log, static fn (string $l): bool => stripos($l, $filter) !== false));
        }
        $lastRun = (int) Settings::get('cron_last_run', 0);
        $res = $this->page('admin/system/index', [
            'title' => 'Estado del sistema',
            'runtime' => $this->runtime(),
            'extensions' => $this->extensions(),
            'db' => $this->database(),
            'dirs' => $this->folders(),
            'cron' => $this->cronState($lastRun),
            'queues' => $this->queues(),
            'storage' => $this->storage(),
            'installDir' => is_dir(APP_ROOT . '/instalar'),
            'log' => $log,
            'logFilter' => $filter,
            'logSize' => is_file(Logger::path()) ? (int) filesize(Logger::path()) : 0,
            'adminEmail' => (string) (Auth::user()['email'] ?? ''),
            'cronReady' => class_exists('App\\Services\\CronService'),
            'scripts' => ['js/admin-system.js'],
        ], ['js/admin-system.js'], '/admin/sistema');
        return $res;
    }

    public function runCron(Request $req, array $p): Response
    {
        if (!class_exists('App\\Services\\CronService')) {
            return $this->fail($req, 'El servicio de tareas programadas todavía no está disponible.', '/admin/sistema');
        }
        if (!RateLimiter::hit('a4cron:' . (int) (Auth::user()['id'] ?? 0), 10, 300)) {
            return $this->fail($req, 'Ya ejecutaste el cron varias veces. Espera unos minutos.', '/admin/sistema');
        }
        try {
            $r = (array) \App\Services\CronService::run('url');
            Settings::flush();
            $this->audit('system.cron_run', 'Ejecución manual del cron', 'system');
            $detail = $this->summarize($r);
            $this->flash('success', 'Ejecutamos las tareas programadas.' . ($detail !== '' ? ' ' . $detail : ''));
        } catch (\Throwable $e) {
            Logger::error('Cron manual falló', $e);
            $this->flash('error', 'El cron terminó con un error. Revisa el registro de errores más abajo.');
        }
        return $this->redirect('/admin/sistema');
    }

    public function clearCache(Request $req, array $p): Response
    {
        $n = Cache::clearAll();
        Cache::bumpAvailability();
        $this->audit('system.cache_clear', $n . ' archivos', 'system');
        $this->flash('success', 'Vaciamos la caché (' . $n . ' archivo' . ($n === 1 ? '' : 's') . ').');
        return $this->redirect('/admin/sistema');
    }

    public function testMail(Request $req, array $p): Response
    {
        $to = $req->str('to', 190);
        if (!Validator::email($to)) {
            return $this->fail($req, 'Escribe un correo válido para la prueba.', '/admin/sistema');
        }
        if (!class_exists('App\\Services\\Mailer')) {
            return $this->fail($req, 'El servicio de correo todavía no está disponible.', '/admin/sistema');
        }
        if (!RateLimiter::hit('a4test:mail:' . (int) (Auth::user()['id'] ?? 0), 12, 600)) {
            return $this->fail($req, 'Has hecho muchas pruebas seguidas. Espera unos minutos.', '/admin/sistema');
        }
        try {
            $html = \App\Services\Mailer::layout('Correo de prueba', '<p>Este es un correo de prueba desde el estado del sistema.</p>');
            $r = \App\Services\Mailer::sendNow($to, null, 'Correo de prueba del sistema', $html, 'Este es un correo de prueba desde el estado del sistema.');
        } catch (\Throwable $e) {
            Logger::error('Prueba de correo (sistema) falló', $e);
            $r = ['ok' => false, 'error' => 'No pudimos enviar el correo.'];
        }
        $this->audit('system.test_mail', (!empty($r['ok']) ? 'Enviada' : 'Fallida') . ' a ' . $to, 'system');
        if (!empty($r['ok'])) {
            $this->flash('success', 'Enviamos el correo de prueba a ' . $to . '.');
        } else {
            $this->flash('error', 'No se pudo enviar: ' . mb_substr(trim((string) ($r['error'] ?? 'revisa la configuración de correo.')), 0, 300));
        }
        return $this->redirect('/admin/sistema');
    }

    public function downloadLog(Request $req, array $p): Response
    {
        $path = Logger::path();
        if (!is_file($path) || filesize($path) === 0) {
            return $this->fail($req, 'El registro de errores está vacío.', '/admin/sistema');
        }
        $this->audit('system.log_download', '', 'system');
        $r = Response::file($path, 'text/plain; charset=utf-8', 'registro-errores-' . $this->stamp() . '.log', false);
        $r->header('Cache-Control', 'no-store');
        return $r;
    }

    public function clearLog(Request $req, array $p): Response
    {
        $path = Logger::path();
        if (is_file($path)) {
            @file_put_contents($path, '', LOCK_EX);
        }
        $this->audit('system.log_clear', '', 'system');
        $this->flash('success', 'Vaciamos el registro de errores.');
        return $this->redirect('/admin/sistema');
    }

    // ---- comprobaciones ----

    private function runtime(): array
    {
        $php = PHP_VERSION;
        $dbv = '';
        try {
            $dbv = (string) Db::val('SELECT VERSION()');
        } catch (\Throwable $e) {
            $dbv = '';
        }
        $isMaria = stripos($dbv, 'mariadb') !== false;
        $num = (string) preg_replace('/^(\d+\.\d+(\.\d+)?).*$/', '$1', $dbv);
        $dbOk = $dbv !== '' && ($isMaria ? version_compare($num, '10.3', '>=') : version_compare($num, '5.7', '>='));
        $memory = (string) ini_get('memory_limit');
        $upload = (string) ini_get('upload_max_filesize');
        $post = (string) ini_get('post_max_size');
        $req = $GLOBALS['__request'] ?? null;
        $https = $req instanceof Request ? $req->isHttps() : false;
        $rows = [
            ['label' => 'Versión de PHP', 'value' => $php, 'status' => version_compare($php, '8.0.0', '>=') ? 'ok' : 'err', 'help' => version_compare($php, '8.0.0', '>=') ? '' : 'Se requiere PHP 8.0 o superior. Pídele a tu hosting que lo actualice desde el panel de control.'],
            ['label' => 'Base de datos', 'value' => $dbv !== '' ? ($isMaria ? 'MariaDB ' : 'MySQL ') . $dbv : 'Sin respuesta', 'status' => $dbOk ? 'ok' : ($dbv === '' ? 'err' : 'warn'), 'help' => $dbOk ? '' : 'Se recomienda MySQL 5.7+ o MariaDB 10.3+.'],
            ['label' => 'Zona horaria del negocio', 'value' => Settings::tz() . ' · servidor: ' . date_default_timezone_get(), 'status' => 'ok', 'help' => ''],
            ['label' => 'Conexión segura (HTTPS)', 'value' => $https ? 'Activa' : 'No detectada', 'status' => $https ? 'ok' : 'warn', 'help' => $https ? '' : 'Activa el certificado SSL de tu dominio: protege las contraseñas y los datos de tus clientes.'],
            ['label' => 'Clave de la aplicación', 'value' => (string) Config::get('app_key', '') !== '' ? 'Configurada' : 'Falta', 'status' => (string) Config::get('app_key', '') !== '' ? 'ok' : 'err', 'help' => ''],
            ['label' => 'Límites de subida', 'value' => 'Archivo ' . $upload . ' · formulario ' . $post . ' · memoria ' . $memory, 'status' => $this->bytesOf($upload) >= 2 * 1048576 ? 'ok' : 'warn', 'help' => $this->bytesOf($upload) >= 2 * 1048576 ? '' : 'El límite de subida es bajo; podrías tener problemas para cargar imágenes.'],
            ['label' => 'Errores en pantalla', 'value' => in_array(strtolower((string) ini_get('display_errors')), ['', '0', 'off', 'false', 'stderr'], true) ? 'Ocultos' : 'Visibles', 'status' => in_array(strtolower((string) ini_get('display_errors')), ['', '0', 'off', 'false', 'stderr'], true) ? 'ok' : 'warn', 'help' => 'En producción conviene ocultarlos (display_errors = Off).'],
        ];
        return $rows;
    }

    private function bytesOf(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return PHP_INT_MAX;
        }
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824,
            'm' => $n * 1048576,
            'k' => $n * 1024,
            default => $n,
        };
    }

    private function extensions(): array
    {
        $out = [];
        foreach (self::EXT_REQUIRED as $ext => $why) {
            $ok = extension_loaded($ext);
            $out[] = ['name' => $ext, 'why' => $why, 'required' => true, 'ok' => $ok];
        }
        $img = extension_loaded('gd') || extension_loaded('imagick');
        foreach (self::EXT_OPTIONAL as $ext => $why) {
            $ok = $ext === 'gd' ? $img : extension_loaded($ext);
            $out[] = ['name' => $ext === 'gd' ? 'gd / imagick' : $ext, 'why' => $why, 'required' => $ext === 'gd', 'ok' => $ok];
        }
        return $out;
    }

    private function database(): array
    {
        $d = ['tables' => 0, 'size' => 0, 'applied' => 0, 'pending' => [], 'error' => ''];
        try {
            $r = Db::one('SELECT COUNT(*) AS n, COALESCE(SUM(data_length + index_length), 0) AS s FROM information_schema.TABLES WHERE table_schema = DATABASE()');
            $d['tables'] = (int) ($r['n'] ?? 0);
            $d['size'] = (int) ($r['s'] ?? 0);
            $d['applied'] = count(Migrator::applied());
            $d['pending'] = array_map('basename', Migrator::pending());
        } catch (\Throwable $e) {
            Logger::error('Estado del sistema: base de datos', $e);
            $d['error'] = 'No pudimos leer el estado de la base de datos.';
        }
        return $d;
    }

    private function folders(): array
    {
        $out = [];
        foreach (['config' => 'Configuración', 'storage' => 'Almacenamiento', 'storage/uploads' => 'Archivos subidos', 'storage/logs' => 'Registros', 'storage/cache' => 'Caché', 'storage/backups' => 'Respaldos', 'storage/sessions' => 'Sesiones'] as $rel => $label) {
            $path = APP_ROOT . '/' . $rel;
            $exists = is_dir($path);
            $out[] = ['path' => $rel, 'label' => $label, 'exists' => $exists, 'writable' => $exists && is_writable($path)];
        }
        return $out;
    }

    private function cronState(int $last): array
    {
        $age = $last > 0 ? Clock::now() - $last : null;
        if ($age === null) {
            $state = 'err';
            $text = 'Nunca se ha ejecutado';
        } elseif ($age <= 900) {
            $state = 'ok';
            $text = 'Funcionando';
        } elseif ($age <= 3600) {
            $state = 'warn';
            $text = 'Con retraso';
        } else {
            $state = 'err';
            $text = 'Detenido';
        }
        return [
            'state' => $state,
            'text' => $text,
            'last' => $last,
            'ago' => $age === null ? '' : $this->ago($age),
            'help' => $state === 'ok' ? '' : 'Los recordatorios, la liberación de citas pendientes y los respaldos dependen del cron. Programa la URL o el comando que aparece en “Correo y WhatsApp” (cada 5 minutos) y pulsa “Ejecutar cron ahora” para comprobarlo.',
        ];
    }

    private function ago(int $s): string
    {
        if ($s < 90) {
            return 'hace ' . max(1, $s) . ' s';
        }
        if ($s < 5400) {
            return 'hace ' . (int) round($s / 60) . ' min';
        }
        if ($s < 129600) {
            return 'hace ' . (int) round($s / 3600) . ' h';
        }
        return 'hace ' . (int) round($s / 86400) . ' días';
    }

    private function queues(): array
    {
        $q = static function (string $sql): int {
            try {
                return (int) Db::val($sql);
            } catch (\Throwable $e) {
                return -1;
            }
        };
        return [
            ['label' => 'Correos pendientes', 'n' => $q("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'"), 'bad' => false, 'link' => '/admin/comunicaciones'],
            ['label' => 'Correos fallidos', 'n' => $q("SELECT COUNT(*) FROM email_queue WHERE status = 'failed'"), 'bad' => true, 'link' => '/admin/comunicaciones'],
            ['label' => 'WhatsApp por enviar', 'n' => $q("SELECT COUNT(*) FROM message_queue WHERE status = 'pending'"), 'bad' => false, 'link' => '/admin/mensajes'],
            ['label' => 'WhatsApp fallidos (API)', 'n' => $q("SELECT COUNT(*) FROM message_queue WHERE status = 'failed'"), 'bad' => true, 'link' => '/admin/comunicaciones'],
            ['label' => 'Entregas de webhook pendientes', 'n' => $q("SELECT COUNT(*) FROM webhook_deliveries WHERE status = 'pending'"), 'bad' => false, 'link' => '/admin/webhooks'],
            ['label' => 'Entregas de webhook fallidas', 'n' => $q("SELECT COUNT(*) FROM webhook_deliveries WHERE status = 'failed'"), 'bad' => true, 'link' => '/admin/webhooks'],
            ['label' => 'Flujos fallidos', 'n' => $q("SELECT COUNT(*) FROM workflow_runs WHERE status = 'failed'"), 'bad' => true, 'link' => '/admin/flujos'],
            ['label' => 'Calendarios externos con error', 'n' => $q("SELECT COUNT(*) FROM external_calendars WHERE active = 1 AND (fail_count > 0 OR last_status = 'error')"), 'bad' => true, 'link' => '/admin/calendarios'],
        ];
    }

    private function storage(): array
    {
        $files = 0;
        $bytes = 0;
        try {
            $r = Db::one('SELECT COUNT(*) AS n, COALESCE(SUM(size), 0) AS s FROM files');
            $files = (int) ($r['n'] ?? 0);
            $bytes = (int) ($r['s'] ?? 0);
        } catch (\Throwable $e) {
            // sin tabla de archivos
        }
        $root = APP_ROOT . '/storage';
        $free = @disk_free_space($root);
        $total = @disk_total_space($root);
        return [
            'files' => $files,
            'uploads' => $bytes,
            'backups' => $this->dirSize(APP_ROOT . '/storage/backups'),
            'logs' => $this->dirSize(APP_ROOT . '/storage/logs'),
            'cache' => $this->dirSize(APP_ROOT . '/storage/cache'),
            'free' => $free === false ? null : (int) $free,
            'total' => $total === false ? null : (int) $total,
        ];
    }

    private function dirSize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $size = 0;
        $n = 0;
        try {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                if ($f->isFile()) {
                    $size += (int) $f->getSize();
                }
                if (++$n > 20000) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            return $size;
        }
        return $size;
    }

    /** Resumen legible del resultado de CronService::run (cualquier forma de arreglo simple). */
    private function summarize(array $r): string
    {
        $parts = [];
        foreach ($r as $k => $v) {
            if (is_int($v) && $v > 0 && is_string($k)) {
                $parts[] = $k . ': ' . $v;
            }
        }
        return $parts ? '(' . implode(', ', array_slice($parts, 0, 6)) . ')' : '';
    }
}
