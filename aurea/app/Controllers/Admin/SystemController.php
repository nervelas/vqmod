<?php
declare(strict_types=1);

namespace Aurea\Controllers\Admin;

use Aurea\Core\Db;
use Aurea\Core\Mailer;
use Aurea\Core\Response;
use Aurea\Core\Settings;
use Aurea\Core\Util;
use Aurea\Services\BackupService;
use Aurea\Services\CronService;
use Aurea\Services\Migrator;

final class SystemController extends AdminController
{
    public function index(): Response
    {
        $this->need('admin');
        $exts = ['pdo_mysql', 'mbstring', 'json', 'fileinfo', 'openssl', 'gd', 'curl', 'sodium'];
        $checks = [];
        foreach ($exts as $x) { $checks[] = [$x, extension_loaded($x)]; }
        $dirs = [];
        foreach (['config', 'storage/logs', 'storage/cache', 'storage/private', 'storage/sessions', 'storage/backups', 'uploads'] as $d) { $dirs[] = [$d, is_writable(AUREA_ROOT . '/' . $d)]; }
        $log = '';
        $lf = AUREA_ROOT . '/storage/logs/error.log';
        if (is_file($lf)) { $lines = array_slice(file($lf, FILE_IGNORE_NEW_LINES) ?: [], -40); $log = implode("\n", $lines); }
        $q = Db::all('SELECT status, COUNT(*) n FROM notifications_queue GROUP BY status');
        $last = (int)Settings::get('cron_last_run', '0');
        $cronUrl = abs_url('/cron.php?token=' . Settings::get('cron_token', ''));
        $dbv = (string)Db::val('SELECT VERSION()');
        return $this->render('system', ['php' => PHP_VERSION, 'checks' => $checks, 'dirs' => $dirs, 'log' => $log, 'queue' => $q, 'last' => $last, 'lastOk' => (string)Settings::get('cron_last_ok', ''),
            'cronUrl' => $cronUrl, 'cronCli' => 'php ' . AUREA_ROOT . '/cron.php', 'dbv' => $dbv, 'pending' => count(Migrator::pending()), 'version' => AUREA_VERSION, 'tz' => date_default_timezone_get(),
            'smtp' => Settings::get('smtp_host', '') !== '', 'argon' => defined('PASSWORD_ARGON2ID'), 'https' => $this->req->isHttps(), 'installer' => is_dir(AUREA_ROOT . '/instalar')], 'sistema', 'Sistema');
    }

    public function testMail(): Response
    {
        $this->need('admin');
        $to = $this->req->str('to');
        if (!Util::isEmail($to)) { $this->fail('Escribe un correo válido para la prueba.'); return $this->redirect('/admin/sistema'); }
        [$ok, $err] = Mailer::send($to, 'Correo de prueba · ' . Settings::get('business_name', 'AUREA'), \Aurea\Services\NotificationService::htmlWrap('Prueba', "¡Funciona!\n\nEste es un correo de prueba enviado desde AUREA. Si lo lees, tu configuración de correo es correcta."));
        $this->audit('mail_test', 'settings', null, $ok ? 'ok' : $err);
        if ($ok) { $this->ok('Correo de prueba enviado a ' . $to . '. Revisa tu bandeja (y spam).'); } else { $this->fail('No se pudo enviar: ' . $err); }
        return $this->redirect('/admin/sistema');
    }

    public function runCron(): Response
    {
        $this->need('admin');
        $r = CronService::run(true);
        $this->audit('cron_manual', 'settings');
        $this->ok('Tareas ejecutadas: ' . json_encode($r, JSON_UNESCAPED_UNICODE));
        return $this->redirect('/admin/sistema');
    }

    public function backup(): Response
    {
        $this->need('admin');
        $sql = BackupService::dump();
        $this->audit('backup_downloaded', 'database', null, strlen($sql) . ' bytes');
        return Response::download($sql, 'respaldo-aurea-' . date('Ymd-His') . '.sql', 'application/sql');
    }

    public function migrate(): Response
    {
        $this->need('admin');
        $done = Migrator::run();
        $files = glob(AUREA_ROOT . '/database/migrations/*.sql') ?: [];
        sort($files);
        if ($files) { Settings::set('schema_version', basename((string)end($files))); }
        $this->audit('migrations_run', 'database', null, implode(',', $done));
        $this->ok($done ? 'Actualizaciones aplicadas: ' . implode(', ', $done) : 'No hay actualizaciones pendientes.');
        return $this->redirect('/admin/sistema');
    }

    public function auditLog(): Response
    {
        $this->need('admin');
        $total = (int)Db::val('SELECT COUNT(*) FROM audit_log');
        $pg = $this->paginate($total, 50);
        $rows = Db::all('SELECT * FROM audit_log ORDER BY id DESC LIMIT ' . (int)$pg['per'] . ' OFFSET ' . (int)$pg['offset']);
        return $this->render('audit', compact('rows', 'pg'), 'auditoria', 'Registro de auditoría');
    }
}
