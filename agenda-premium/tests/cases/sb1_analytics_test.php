<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
T::boot('sb1_analytics', ['profession' => 'medico']);

use App\Core\Clock;
use App\Core\Db;
use App\Core\Settings;
use App\Services\AnalyticsService;
use App\Services\ReportService;

Clock::set(strtotime('2026-10-14 12:00:00 UTC'));
$UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36';
$UA_M = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

T::section('track(): sanitización, bots y dispositivos');
AnalyticsService::track('view', ['event_type_id' => 1, 'visit_id' => 'abcdef0123456789', 'utm_source' => "goo<script>gle\n", 'utm_medium' => str_repeat('m', 300), 'referrer_host' => 'https://Referente.Example.com:8443/ruta/secreta?token=abc', 'user_agent' => $UA]);
$row = Db::one('SELECT * FROM analytics_events ORDER BY id DESC LIMIT 1');
T::eq('gooscriptgle', $row['utm_source'], 'UTM sin etiquetas ni saltos de línea');
T::eq(100, strlen((string) $row['utm_medium']), 'UTM truncado a 100');
T::eq('referente.example.com', $row['referrer_host'], 'el referente se reduce al host (sin ruta, puerto ni parámetros)');
T::eq('desktop', $row['device'], 'dispositivo de escritorio');
$n = (int) Db::val('SELECT COUNT(*) FROM analytics_events');
AnalyticsService::track('view', ['event_type_id' => 1, 'visit_id' => 'abcdef0123456789', 'user_agent' => $UA]);
T::eq($n, (int) Db::val('SELECT COUNT(*) FROM analytics_events'), 'el mismo paso de la misma visita no se duplica');
foreach (['Googlebot/2.1 (+http://www.google.com/bot.html)', 'curl/8.0', 'Mozilla/5.0 HeadlessChrome/120', ''] as $bot) {
    AnalyticsService::track('view', ['event_type_id' => 1, 'visit_id' => bin2hex(random_bytes(8)), 'user_agent' => $bot]);
}
T::eq($n, (int) Db::val('SELECT COUNT(*) FROM analytics_events'), 'los bots y los agentes vacíos se descartan');
AnalyticsService::track('slot', ['event_type_id' => 1, 'visit_id' => 'abcdef0123456789', 'user_agent' => $UA_M, 'referrer_host' => 'no es host!!']);
$row = Db::one('SELECT * FROM analytics_events ORDER BY id DESC LIMIT 1');
T::ok($row['device'] === 'mobile' && $row['referrer_host'] === null && $row['step'] === 'slot', 'dispositivo móvil; referente inválido → null');
AnalyticsService::track('hack', ['visit_id' => 'abcdef0123456789', 'user_agent' => $UA]);
AnalyticsService::track('view', ['visit_id' => "x'; DROP TABLE users;--", 'user_agent' => $UA]);
T::ok(Db::val('SELECT COUNT(*) FROM users') > 0 && (int) Db::val("SELECT COUNT(*) FROM analytics_events WHERE step = 'hack'") === 0, 'paso desconocido ignorado; visit_id malicioso no rompe nada');
T::ok(strlen((string) Db::val('SELECT visit_id FROM analytics_events ORDER BY id DESC LIMIT 1')) === 16, 'visit_id inválido se reemplaza por uno aleatorio de 16');
Db::exec('DELETE FROM analytics_events');

// ---- datos sintéticos ----
$sched = Db::insert('schedules', ['name' => 'Prueba analítica', 'timezone' => 'America/Guatemala', 'is_default' => 0, 'created_at' => '2026-01-01 00:00:00']);
for ($d = 1; $d <= 5; $d++) {
    Db::insert('schedule_rules', ['schedule_id' => $sched, 'weekday' => $d, 'start_time' => '08:00:00', 'end_time' => '12:00:00']);
}
$host = Db::insert('hosts', ['name' => 'Dra. Analítica', 'slug' => 'dra-analitica', 'timezone' => 'America/Guatemala', 'ics_token' => bin2hex(random_bytes(16)), 'schedule_id' => $sched, 'active' => 1, 'created_at' => '2026-01-01 00:00:00']);
$host2 = Db::insert('hosts', ['name' => 'Otro Dr.', 'slug' => 'otro-dr', 'timezone' => 'America/Guatemala', 'ics_token' => bin2hex(random_bytes(16)), 'schedule_id' => $sched, 'active' => 1, 'created_at' => '2026-01-01 00:00:00']);
$mk = static function (string $startUtc, string $status, ?string $utm, ?string $ref, int $hid = 0, int $event = 1, int $dur = 30) use ($host): int {
    $s = strtotime($startUtc . ' UTC');
    return Db::insert('bookings', [
        'token' => bin2hex(random_bytes(16)), 'event_type_id' => $event, 'host_id' => $hid ?: $host, 'starts_at' => gmdate('Y-m-d H:i:s', $s), 'ends_at' => gmdate('Y-m-d H:i:s', $s + $dur * 60),
        'blocked_start' => gmdate('Y-m-d H:i:s', $s), 'blocked_end' => gmdate('Y-m-d H:i:s', $s + $dur * 60), 'duration' => $dur, 'status' => $status, 'guest_name' => '=cmd|\' /C calc\'!A0', 'guest_email' => 'g@example.test',
        'guest_phone' => '+50255551234', 'price' => '300.00', 'total' => '300.00', 'utm_source' => $utm, 'referrer_host' => $ref, 'created_at' => '2026-10-01 10:00:00', 'updated_at' => '2026-10-01 10:00:00',
    ]);
};
$b1 = $mk('2026-10-05 15:00:00', 'confirmed', 'google', null);       // lun 09:00 GT
$mk('2026-10-05 15:30:00', 'completed', 'google', null);             // lun 09:30 GT
$mk('2026-10-06 20:00:00', 'confirmed', null, 'facebook.com');       // mar 14:00 GT
$mk('2026-10-07 15:00:00', 'cancelled', null, null);                 // mié 09:00 GT (excluida de picos)
$mk('2026-10-08 21:00:00', 'no_show', null, null);                   // jue 15:00 GT
$mk('2026-10-12 03:00:00', 'confirmed', null, null);                 // dom 21:00 GT = lun 05:00 Madrid (fuera del periodo en Madrid)
$mk('2026-10-01 15:00:00', 'confirmed', 'google', null);             // periodo anterior
$mk('2026-10-02 15:00:00', 'cancelled', null, null);                 // periodo anterior
$mk('2026-10-06 16:00:00', 'confirmed', null, null, $host2, 2, 60);  // otro anfitrión y evento
$ev = static function (string $step, string $vid, string $when, ?string $utm, ?string $ref, int $event = 1, string $dev = 'desktop'): void {
    Db::insert('analytics_events', ['event_type_id' => $event, 'visit_id' => $vid, 'step' => $step, 'utm_source' => $utm, 'referrer_host' => $ref, 'device' => $dev, 'created_at' => $when]);
};
for ($i = 1; $i <= 10; $i++) {
    $vid = sprintf('v%015d', $i);
    $src = $i <= 6 ? 'google' : null;
    $ref = ($i === 7 || $i === 8) ? 'facebook.com' : null;
    $ev('view', $vid, '2026-10-06 10:00:00', $src, $ref, 1, $i % 2 ? 'mobile' : 'desktop');
    if ($i <= 4) {
        $ev('slot', $vid, '2026-10-06 10:01:00', $src, $ref);
    }
    if ($i <= 2) {
        $ev('booked', $vid, '2026-10-06 10:02:00', $src, $ref);
    }
}
for ($i = 1; $i <= 5; $i++) {
    $ev('view', sprintf('p%015d', $i), '2026-10-01 10:00:00', null, null);
}
$ev('booked', sprintf('p%015d', 1), '2026-10-01 10:05:00', null, null);
Db::insert('payments', ['booking_id' => $b1, 'amount' => '300.00', 'method' => 'cash', 'status' => 'verified', 'created_at' => '2026-10-06 18:00:00']);
Db::insert('payments', ['booking_id' => $b1, 'amount' => '50.00', 'method' => 'cash', 'status' => 'refunded', 'created_at' => '2026-10-07 18:00:00']);
Db::insert('payments', ['booking_id' => $b1, 'amount' => '999.00', 'method' => 'transfer', 'status' => 'pending', 'created_at' => '2026-10-07 18:00:00']);
Db::insert('payments', ['booking_id' => $b1, 'amount' => '100.00', 'method' => 'cash', 'status' => 'verified', 'created_at' => '2026-10-01 18:00:00']);

T::section('Informe: KPIs y comparación');
$rep = AnalyticsService::report('2026-10-05', '2026-10-11');
$k = $rep['kpis'];
T::eq(['2026-09-28', '2026-10-04', 7], [$rep['period']['previous_from'], $rep['period']['previous_to'], $rep['period']['days']], 'periodo anterior de igual duración');
T::ok($k['bookings']['value'] === 7 && $k['bookings']['previous'] === 2 && $k['bookings']['delta_pct'] === 250.0, 'citas 7 vs 2 (+250 %)');
T::ok($k['cancelled']['value'] === 1 && $k['no_show']['value'] === 1 && $k['confirmed']['value'] === 5, 'cancelaciones, no-shows y confirmadas');
T::ok($k['no_show_pct']['value'] === 14.3 && $k['cancel_pct']['value'] === 14.3, 'porcentajes de inasistencia y cancelación');
T::ok($k['views']['value'] === 10 && $k['views']['previous'] === 5 && $k['views']['delta_pct'] === 100.0, 'visitas 10 vs 5');
T::ok($k['conversion_pct']['value'] === 20.0 && $k['conversion_pct']['previous'] === 20.0 && $k['conversion_pct']['delta_pct'] === 0.0, 'conversión 20 % vs 20 %');
T::ok($k['revenue']['value'] === 250.0 && $k['revenue']['previous'] === 100.0 && $k['revenue']['delta_pct'] === 150.0, 'ingresos = verificados − reembolsos; los pendientes no cuentan');
T::eq(['view' => 10, 'slot' => 4, 'booked' => 2], array_column($rep['funnel'], 'count', 'step'), 'embudo por visit_id: vistas → horario → reservas');
T::eq([100.0, 40.0, 20.0], array_column($rep['funnel'], 'pct'), 'porcentajes del embudo');
T::eq(['mobile' => 5, 'desktop' => 5], $rep['devices'], 'dispositivos');

T::section('Por evento, por fuente y por periodo');
$byEv = array_column($rep['by_event'], null, 'event_type_id');
T::ok($byEv[1]['views'] === 10 && $byEv[1]['booked_visits'] === 2 && $byEv[1]['bookings'] === 6 && $byEv[1]['conversion_pct'] === 20.0 && $byEv[2]['bookings'] === 1, 'conversión por evento');
$bySrc = array_column($rep['by_source'], null, 'source');
T::ok($bySrc['google']['views'] === 6 && $bySrc['google']['booked_visits'] === 2 && $bySrc['google']['bookings'] === 2 && $bySrc['google']['conversion_pct'] === 33.3, 'fuente utm_source=google');
T::ok($bySrc['facebook.com']['views'] === 2 && $bySrc['facebook.com']['bookings'] === 1, 'fuente por referente cuando no hay UTM');
T::ok($bySrc['directo']['views'] === 2 && $bySrc['directo']['bookings'] === 4, 'tráfico directo');
$day = array_column($rep['by_period']['rows'], null, 'period');
T::ok(count($day) === 7 && $day['2026-10-05']['bookings'] === 2 && $day['2026-10-07']['cancelled'] === 1 && $day['2026-10-08']['no_show'] === 1 && $day['2026-10-11']['bookings'] === 1 && $day['2026-10-09']['bookings'] === 0, 'reservas por día (incluye días vacíos y la cita del domingo 21:00 hora local)');
$wk = AnalyticsService::report('2026-10-05', '2026-10-11', ['group' => 'week'])['by_period'];
T::ok($wk['group'] === 'week' && count($wk['rows']) === 1 && $wk['rows'][0]['period'] === '2026-10-05' && $wk['rows'][0]['bookings'] === 7, 'agrupado por semana');
$mo = AnalyticsService::report('2026-09-20', '2026-10-11', ['group' => 'month'])['by_period'];
T::eq(['2026-09', '2026-10'], array_column($mo['rows'], 'period'), 'agrupado por mes');

T::section('Horas y días pico con zonas');
T::eq(9, $rep['peak']['peak_hour'], 'hora pico en Guatemala = 9');
T::eq(1, $rep['peak']['peak_weekday'], 'día pico = lunes');
T::eq(2, $rep['peak']['by_hour'][9], 'dos citas a las 9 (la cancelada no cuenta)');
Settings::set('timezone', 'Europe/Madrid');
$madrid = AnalyticsService::report('2026-10-05', '2026-10-11');
T::eq(17, $madrid['peak']['peak_hour'], 'misma agenda en Madrid (UTC+2): hora pico = 17');
T::eq(6, $madrid['kpis']['bookings']['value'], 'en Madrid la cita del domingo 21:00 GT cae fuera del periodo');
Settings::set('timezone', 'America/Guatemala');

T::section('Ocupación por anfitrión');
$occ = array_column($rep['occupancy'], null, 'host_id');
T::ok($occ[$host]['booked_minutes'] === 150 && $occ[$host]['capacity_minutes'] === 1200 && $occ[$host]['pct'] === 12.5, 'minutos reservados / laborables: 150 / 1200 = 12.5 %');
T::ok($occ[$host2]['booked_minutes'] === 60, 'el otro anfitrión tiene 60 min');
$f = AnalyticsService::report('2026-10-05', '2026-10-11', ['host_id' => $host]);
T::ok($f['kpis']['bookings']['value'] === 6 && count($f['occupancy']) === 1, 'filtro por anfitrión');
$f = AnalyticsService::report('2026-10-05', '2026-10-11', ['event_type_id' => 2]);
T::ok($f['kpis']['bookings']['value'] === 1 && $f['kpis']['views']['value'] === 0, 'filtro por evento');
Db::insert('schedule_overrides', ['schedule_id' => $sched, 'date' => '2026-10-07', 'is_open' => 0]);
T::eq(960, array_column(AnalyticsService::report('2026-10-05', '2026-10-11')['occupancy'], null, 'host_id')[$host]['capacity_minutes'], 'una excepción de día cerrado reduce la capacidad (4 días × 240)');
Db::insert('holidays', ['date' => '2026-10-08', 'name' => 'Feriado de prueba', 'kind' => 'half', 'half_day_end' => '10:00:00', 'scope' => 'national', 'active' => 1, 'source' => 'manual']);
T::eq(840, array_column(AnalyticsService::report('2026-10-05', '2026-10-11')['occupancy'], null, 'host_id')[$host]['capacity_minutes'], 'medio día feriado recorta el horario (08:00–10:00)');

T::section('Periodos vacíos y fechas inválidas');
$e = AnalyticsService::report('2030-01-01', '2030-01-07');
T::ok($e['kpis']['bookings']['value'] === 0 && $e['kpis']['bookings']['delta_pct'] === 0.0 && $e['kpis']['conversion_pct']['value'] === 0.0 && $e['by_event'] === [] && $e['peak']['peak_hour'] === null && $e['funnel'][0]['pct'] === 0.0, 'periodo vacío sin errores ni divisiones por cero');
T::ok(count($e['by_period']['rows']) === 7, 'el periodo vacío igual trae sus 7 días');
$bad = AnalyticsService::report('basura', '2026-13-45');
T::eq(30, $bad['period']['days'], 'fechas inválidas → últimos 30 días');
$rev = AnalyticsService::report('2026-10-11', '2026-10-05');
T::eq(30, $rev['period']['days'], 'rango invertido → últimos 30 días');

T::section('CSV');
$csv = ReportService::csv('bookings', '2026-10-05', '2026-10-11');
T::ok(str_starts_with($csv, "\xEF\xBB\xBF"), 'empieza con BOM UTF-8');
T::ok(str_contains($csv, "\r\n") && !str_contains($csv, "\n\n"), 'saltos CRLF');
$h = fopen('php://memory', 'w+');
fwrite($h, substr($csv, 3));
rewind($h);
$rows = [];
while (($r = fgetcsv($h, 0, ',', '"', '')) !== false) {
    $rows[] = $r;
}
T::eq('ID', $rows[0][0], 'encabezado en español');
T::eq(8, count($rows), '7 citas del periodo + encabezado');
$bad = 0;
foreach (array_slice($rows, 1) as $r) {
    foreach ($r as $cell) {
        if ($cell !== '' && strpbrk($cell[0], '=+-@') !== false) {
            $bad++;
        }
    }
}
T::eq(0, $bad, 'ninguna celda empieza con = + - @ (fórmulas neutralizadas)');
T::eq("'=cmd|' /C calc'!A0", $rows[1][6], 'nombre con fórmula lleva prefijo apóstrofo');
T::eq("'+50255551234", $rows[1][8], 'teléfono con + lleva prefijo (se conserva como texto)');
T::eq('Confirmada', $rows[1][9], 'estado traducido');
Db::exec("UPDATE bookings SET guest_name = 'Pérez, \"Juan\"\nsegunda línea' WHERE id = ?", [$b1]);
$csv2 = ReportService::csv('bookings', '2026-10-05', '2026-10-05');
T::ok(str_contains($csv2, "\"Pérez, \"\"Juan\"\"\nsegunda línea\""), 'comas, comillas y saltos de línea se escapan correctamente');
$p = ReportService::csv('payments', '2026-10-01', '2026-10-31');
T::ok(str_contains($p, 'Reembolso') && str_contains($p, '300.00') && substr_count($p, "\r\n") === 5, 'CSV de pagos (4 pagos + encabezado)');
Db::insert('clients', ['name' => '@SUMA(A1)', 'email' => 'c@example.test', 'created_at' => '2026-10-06 10:00:00', 'updated_at' => '2026-10-06 10:00:00']);
T::ok(str_contains(ReportService::csv('clients', '2026-10-05', '2026-10-11'), "'@SUMA(A1)"), 'CSV de clientes neutraliza fórmulas');
$a = ReportService::csv('analytics', '2026-10-05', '2026-10-11');
T::ok(str_contains($a, '2026-10-05,2,0,0') && str_contains($a, 'google,6,2,2,33.3'), 'CSV de analítica (por día y por fuente)');
T::throws(fn () => ReportService::csv('usuarios', '2026-10-05', '2026-10-11'), 'tipo desconocido', \InvalidArgumentException::class);
T::throws(fn () => ReportService::csv('bookings', 'x', '2026-10-11'), 'fecha inválida', \InvalidArgumentException::class);

T::section('Resumen semanal');
Settings::set('admin_notify_email', 'dueno@example.test');
Settings::set('weekly_summary', '1');
Db::exec("UPDATE bookings SET guest_name = 'Cliente'");
ReportService::weeklySummary();
ReportService::weeklySummary();
$mails = Db::all("SELECT * FROM email_queue WHERE to_email = 'dueno@example.test'");
T::eq(1, count($mails), 'se encola una sola vez por semana aunque se llame varias veces');
T::ok(str_contains($mails[0]['subject'], 'Resumen semanal') && str_contains($mails[0]['body_html'], '05/10/2026 al 11/10/2026') && str_contains($mails[0]['body_html'], 'Q250.00') && str_contains($mails[0]['body_html'], '+250 %'), 'contenido: semana, ingresos y comparación');
Clock::set(Clock::now() + 7 * 86400);
ReportService::weeklySummary();
T::eq(2, (int) Db::val("SELECT COUNT(*) FROM email_queue WHERE to_email = 'dueno@example.test'"), 'la semana siguiente envía otro');
Settings::set('weekly_summary', '0');
Clock::set(Clock::now() + 7 * 86400);
ReportService::weeklySummary();
T::eq(2, (int) Db::val("SELECT COUNT(*) FROM email_queue WHERE to_email = 'dueno@example.test'"), 'con weekly_summary=0 no envía');
Settings::set('weekly_summary', '1');
Settings::set('admin_notify_email', '');
ReportService::weeklySummary();
T::eq(2, (int) Db::val("SELECT COUNT(*) FROM email_queue WHERE to_email = 'dueno@example.test'"), 'sin correo del administrador no envía');
Clock::set(null);
T::done();
