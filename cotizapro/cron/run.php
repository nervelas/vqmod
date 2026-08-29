<?php
declare(strict_types=1);
/**
 * Tareas programadas de CotizaPro B2B.
 *
 *   * /15 * * * * curl -s "https://SUDOMINIO/cron/run.php?token=XXXX"
 *
 * Ejecuta: recordatorios al vendedor y al cliente, informe mensual,
 * respaldo semanal y limpieza de temporales.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Backup;
use App\Core\Config;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Mailer;
use App\Models\Company;
use App\Models\Notification;
use App\Models\Quote;
use App\Models\Setting;

Config::load();

$cli   = PHP_SAPI === 'cli';
$token = (string) Config::get('cron_token', '');
$given = (string) ($_GET['token'] ?? ($argv[1] ?? ''));

if (!Config::installed()) {
    http_response_code(503);
    exit("No instalado.\n");
}
if (!$cli && ($token === '' || !hash_equals($token, $given))) {
    http_response_code(403);
    exit("Token no válido.\n");
}

@set_time_limit(600);
header('Content-Type: text/plain; charset=utf-8');

$log = [];
$t0 = microtime(true);

/* ------------------------------------------- 1 · recordatorios al vendedor */
try {
    $n = 0;
    $company = Company::get();
    $rows = $company ? DB::all(
        'SELECT q.*, c.name AS company_name, c.email AS company_email, c.reminder_days_seller,
                u.email AS seller_email, u.name AS seller_name
         FROM quotes q
         JOIN company c ON c.id = 1
         LEFT JOIN users u ON u.id = q.user_id
         WHERE q.is_current = 1
           AND q.status IN ("nueva","elaboracion","enviada","negociacion")
           AND c.reminder_days_seller > 0
           AND DATEDIFF(NOW(), COALESCE(q.last_contact_at, q.created_at)) >= c.reminder_days_seller
           AND (q.reminder_sent_at IS NULL OR q.reminder_sent_at < DATE_SUB(NOW(), INTERVAL 3 DAY))
         LIMIT 120'
    ) : [];
    foreach ($rows as $q) {
        $days = daysSince((string) ($q['last_contact_at'] ?: $q['created_at']));
        $body = '<p>La cotización <strong>' . e($q['number']) . '</strong> de <strong>'
              . e($q['contact_company'] ?: $q['contact_name']) . '</strong> lleva <strong>' . $days
              . ' días</strong> sin seguimiento.</p><p>Monto: ' . e(money((float) $q['total'], (string) $q['currency_symbol'])) . '</p>';
        $to = (string) ($q['seller_email'] ?: $q['company_email']);
        if ($to !== '') {
            Mailer::send($to, 'Seguimiento pendiente · ' . $q['number'],
                Mailer::template('Cotización sin respuesta', $body, $company, 'Abrir la cotización', absUrl('/panel/cotizaciones/' . $q['id'])), $company);
        }
        if ($q['user_id']) {
            Notification::push((int) $q['user_id'], 'La ' . $q['number'] . ' lleva ' . $days . ' días sin respuesta',
                (string) ($q['contact_company'] ?: $q['contact_name']), '/panel/cotizaciones/' . $q['id'], 'recordatorio');
        }
        DB::update('quotes', ['reminder_sent_at' => nowSql()], 'id = :id', ['id' => (int) $q['id']]);
        $n++;
    }
    $log[] = "recordatorios_vendedor={$n}";
} catch (\Throwable $e) {
    ErrorHandler::log('CRON recordatorios vendedor: ' . $e->getMessage());
    $log[] = 'recordatorios_vendedor=ERROR';
}

/* --------------------------------------------- 2 · recordatorio al cliente */
try {
    $n = 0;
    $company = Company::get();
    $rows = $company ? DB::all(
        'SELECT q.*, c.reminder_days_client
         FROM quotes q
         JOIN company c ON c.id = 1
         WHERE q.is_current = 1 AND q.status = "enviada"
           AND c.reminder_days_client > 0
           AND q.sent_at IS NOT NULL
           AND DATEDIFF(NOW(), q.sent_at) >= c.reminder_days_client
           AND q.client_reminder_sent_at IS NULL
           AND q.contact_email <> ""
         LIMIT 80'
    ) : [];
    foreach ($rows as $q) {
        $body = '<p>Estimado(a) ' . e($q['contact_name']) . ', le recordamos que su cotización <strong>'
              . e($q['number']) . '</strong> sigue vigente'
              . ($q['valid_until'] ? ' hasta el ' . e(fechaLarga((string) $q['valid_until'])) : '')
              . '.</p><p>Puede aprobarla o pedirnos cambios desde el enlace de abajo.</p>';
        Mailer::send((string) $q['contact_email'], 'Su cotización ' . $q['number'] . ' sigue vigente',
            Mailer::template('Recordatorio de cotización', $body, $company, 'Ver mi cotización', Quote::trackUrl($q)), $company);
        DB::update('quotes', ['client_reminder_sent_at' => nowSql()], 'id = :id', ['id' => (int) $q['id']]);
        Quote::event((int) $q['id'], 'sistema', 'Recordatorio automático enviado al cliente');
        $n++;
    }
    $log[] = "recordatorios_cliente={$n}";
} catch (\Throwable $e) {
    ErrorHandler::log('CRON recordatorios cliente: ' . $e->getMessage());
    $log[] = 'recordatorios_cliente=ERROR';
}

/* ------------------------------------------------- 3 · informe mensual */
try {
    $n = 0;
    $lastReport = Setting::get('last_monthly_report', '');
    $thisMonth  = date('Y-m');
    if ((int) date('j') <= 3 && $lastReport !== $thisMonth) {
        $from = date('Y-m-01', strtotime('-1 month'));
        $to   = date('Y-m-t', strtotime('-1 month'));
        $c = Company::get();
        if ($c) {
            $stat = DB::one(
                'SELECT COUNT(*) n, COALESCE(SUM(total),0) cotizado,
                        COALESCE(SUM(CASE WHEN status="aprobada" THEN won_amount ELSE 0 END),0) ganado,
                        SUM(status="aprobada") ganadas, SUM(status="perdida") perdidas
                 FROM quotes WHERE is_current = 1 AND created_at BETWEEN ? AND ?',
                [$from . ' 00:00:00', $to . ' 23:59:59']
            ) ?: [];
            $cerradas = (int) ($stat['ganadas'] ?? 0) + (int) ($stat['perdidas'] ?? 0);
            $conv = $cerradas > 0 ? round((int) $stat['ganadas'] / $cerradas * 100, 1) : 0;
            $sym = (string) $c['currency_symbol'];
            $body = '<p>Resumen del <strong>' . e(fechaCorta($from)) . '</strong> al <strong>' . e(fechaCorta($to)) . '</strong>:</p>'
                . '<table role="presentation" width="100%" style="font:14px Helvetica,Arial,sans-serif">'
                . '<tr><td style="padding:6px 0;border-bottom:1px solid #E7E9E4">Cotizaciones emitidas</td><td style="text-align:right;font-weight:600">' . (int) ($stat['n'] ?? 0) . '</td></tr>'
                . '<tr><td style="padding:6px 0;border-bottom:1px solid #E7E9E4">Monto cotizado</td><td style="text-align:right;font-weight:600">' . e(money((float) ($stat['cotizado'] ?? 0), $sym)) . '</td></tr>'
                . '<tr><td style="padding:6px 0;border-bottom:1px solid #E7E9E4">Monto ganado</td><td style="text-align:right;font-weight:600">' . e(money((float) ($stat['ganado'] ?? 0), $sym)) . '</td></tr>'
                . '<tr><td style="padding:6px 0">Tasa de conversión</td><td style="text-align:right;font-weight:600">' . $conv . ' %</td></tr>'
                . '</table>';
            foreach (DB::all('SELECT email, name FROM users WHERE role = "admin" AND status = "activo"') as $adm) {
                Mailer::send((string) $adm['email'], 'Informe mensual · ' . $c['name'],
                    Mailer::template('Informe de ' . fechaCorta($from), $body, $c, 'Ver los reportes', absUrl('/panel/reportes')), $c);
                $n++;
            }
        }
        Setting::set('last_monthly_report', $thisMonth);
    }
    $log[] = "informes={$n}";
} catch (\Throwable $e) {
    ErrorHandler::log('CRON informe mensual: ' . $e->getMessage());
    $log[] = 'informes=ERROR';
}

/* ------------------------------------------------- 4 · respaldo semanal */
try {
    $done = 'no';
    $last = Setting::get('last_weekly_backup', '');
    $week = date('o-W');
    if ((int) date('N') === 7 && $last !== $week) {
        $res = Backup::create('automatico');
        Setting::set('last_weekly_backup', $week);
        $done = $res ? $res['name'] : 'fallo';
    }
    $log[] = "respaldo={$done}";
} catch (\Throwable $e) {
    ErrorHandler::log('CRON respaldo: ' . $e->getMessage());
    $log[] = 'respaldo=ERROR';
}

/* ------------------------------------------------------- 5 · limpieza */
try {
    DB::run('DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 2 DAY) AND (blocked_until IS NULL OR blocked_until < NOW())');
    DB::run('DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
    DB::run('DELETE FROM two_factor_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 2 DAY)');
    DB::run('DELETE FROM notifications WHERE read_at IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)');
    DB::run('DELETE FROM email_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)');
    DB::run('DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)');
    DB::run('DELETE FROM cron_runs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    $removed = 0;
    foreach (glob(STORAGE_PATH . '/tmp/*') ?: [] as $f) {
        if (is_file($f) && filemtime($f) < time() - 86400) {
            @unlink($f);
            $removed++;
        }
    }
    // Rotación de registros: se conservan tres meses.
    foreach (glob(STORAGE_PATH . '/logs/app-*.log') ?: [] as $f) {
        if (filemtime($f) < time() - 7776000) {
            @unlink($f);
        }
    }
    $log[] = "limpieza_tmp={$removed}";
} catch (\Throwable $e) {
    ErrorHandler::log('CRON limpieza: ' . $e->getMessage());
    $log[] = 'limpieza=ERROR';
}

$result = implode(' · ', $log) . sprintf(' · %.2fs', microtime(true) - $t0);
try {
    DB::insert('cron_runs', ['task' => 'general', 'result' => mb_substr($result, 0, 400), 'created_at' => nowSql()]);
} catch (\Throwable) {
}
echo "CotizaPro cron OK\n{$result}\n";
