<?php
declare(strict_types=1);

require __DIR__ . '/../lib/T.php';
require __DIR__ . '/../lib/SaHelper.php';
T::boot('sa_ics');

use App\Core\Clock;
use App\Core\Db;
use App\Core\Settings;
use App\Services\BookingService;
use App\Services\IcsService;

$dir = SaHelper::workDir('ics');
Settings::set('site_base_url', 'http://agenda.test/app');
$py = static function (string $mode, string $ics, string ...$args) use ($dir): ?array {
    $f = $dir . '/x_' . md5($ics) . '.ics';
    file_put_contents($f, $ics);
    $o = shell_exec('python3 ' . escapeshellarg(__DIR__ . '/../lib/ics_check.py') . ' ' . $mode . ' ' . escapeshellarg($f) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1');
    return json_decode((string) $o, true) ?? ['error' => $o];
};

T::section('Generación: estructura RFC 5545 validada con la biblioteca icalendar');
$ev = SaHelper::makeEvent(['name' => 'Consulta, "inicial"; nutrición ñ', 'location' => 'Zona 10, Torre Ñ; Oficina 4']);
$bid = SaHelper::makeBooking($ev, ['starts_at' => '2026-10-12 16:00:00', 'ends_at' => '2026-10-12 16:45:00', 'notes' => "Primera vez\nllevar estudios", 'location' => 'Zona 10, Torre Ñ; Oficina 4', 'video_url' => 'https://meet.example.test/sala-ñ']);
$b = BookingService::display(BookingService::find($bid));
$ics = IcsService::generate($b);
T::ok(strpos($ics, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:") === 0 && substr($ics, -15) === "END:VCALENDAR\r\n", 'VCALENDAR con CRLF');
T::ok(strpos($ics, "\n") !== false && substr_count($ics, "\n") === substr_count($ics, "\r\n"), 'todas las líneas terminan en CRLF');
$bad = 0;
$fold = 0;
foreach (explode("\r\n", rtrim($ics, "\r\n")) as $line) {
    if (strlen($line) > 75) {
        $bad++;
    }
    if (isset($line[0]) && $line[0] === ' ') {
        $fold++;
    }
    if (!mb_check_encoding($line, 'UTF-8')) {
        $bad++;
    }
}
T::ok($bad === 0 && $fold > 0, 'plegado a 75 octetos sin partir caracteres UTF-8 (' . $fold . ' líneas de continuación)');
$v = $py('validate', $ics);
T::ok(isset($v['events'][0]), 'icalendar lee el archivo generado' . (isset($v['error']) ? ': ' . $v['error'] : ''));
$e = $v['events'][0] ?? [];
T::eq('Consulta, "inicial"; nutrición ñ con ' . $b['host']['name'], $e['summary'] ?? null, 'SUMMARY con comas, comillas y punto y coma (escapes correctos)');
T::eq(strtotime('2026-10-12 16:00:00 UTC'), $e['start'] ?? null, 'DTSTART en UTC correcto');
T::eq(strtotime('2026-10-12 16:45:00 UTC'), $e['end'] ?? null, 'DTEND en UTC correcto');
T::eq('Zona 10, Torre Ñ; Oficina 4', $e['location'] ?? null, 'LOCATION con escapes');
T::ok(strpos((string) ($e['description'] ?? ''), "\nGestiona tu cita: http://agenda.test/app/reserva/" . $b['token']) !== false, 'DESCRIPTION con saltos de línea y enlace de la cita');
T::eq('CONFIRMED', $e['status'] ?? null, 'STATUS CONFIRMED');
T::eq(1, $e['alarms'] ?? null, 'una alarma VALARM');
T::eq('http://agenda.test/app/reserva/' . $b['token'], $e['url'] ?? null, 'URL de la cita');
T::ok(strpos((string) ($e['organizer'] ?? ''), 'mailto:') === 0, 'ORGANIZER con mailto');
T::eq('PUBLISH', $v['method'] ?? null, 'METHOD:PUBLISH');
T::eq('ap-' . $b['token'] . '@agenda.test', $e['uid'] ?? null, 'UID estable basado en el token y el dominio');
T::eq($e['uid'] ?? 1, $py('validate', IcsService::generate($b))['events'][0]['uid'] ?? 2, 'el UID no cambia entre generaciones');

T::section('SEQUENCE y STATUS siguen la vida de la cita');
$seq0 = (int) ($e['sequence'] ?? -1);
BookingService::log($bid, 'reprogramada', 'cambio', 'Prueba');
$seq1 = $py('validate', IcsService::generate(BookingService::display(BookingService::find($bid))))['events'][0]['sequence'] ?? -1;
T::ok($seq1 > $seq0, "SEQUENCE aumenta tras un cambio ($seq0 -> $seq1)");
Db::update('bookings', ['status' => 'pending'], 'id = ?', [$bid]);
T::eq('TENTATIVE', $py('validate', IcsService::generate(BookingService::display(BookingService::find($bid))))['events'][0]['status'] ?? null, 'pendiente = TENTATIVE');
Db::update('bookings', ['status' => 'cancelled'], 'id = ?', [$bid]);
$c = $py('validate', IcsService::generate(BookingService::display(BookingService::find($bid))));
T::ok(($c['method'] ?? '') === 'CANCEL' && ($c['events'][0]['status'] ?? '') === 'CANCELLED' && ($c['events'][0]['alarms'] ?? 1) === 0, 'cancelada = METHOD:CANCEL, STATUS:CANCELLED y sin alarma');
Db::update('bookings', ['status' => 'confirmed'], 'id = ?', [$bid]);

T::section('generateMany y calendario del anfitrión');
$hostId = (int) Db::val('SELECT id FROM hosts ORDER BY id LIMIT 1');
$other = Db::insert('hosts', ['name' => 'Otro Anfitrión', 'slug' => 'otro', 'ics_token' => bin2hex(random_bytes(16)), 'created_at' => gmdate('Y-m-d H:i:s')]);
$soon = SaHelper::makeBooking($ev, ['starts_at' => gmdate('Y-m-d H:i:s', time() + 2 * 86400), 'guest_name' => 'Pedro Ñ']);
$pending = SaHelper::makeBooking($ev, ['starts_at' => gmdate('Y-m-d H:i:s', time() + 3 * 86400), 'status' => 'pending', 'guest_name' => 'Ana Pendiente']);
$cancelled = SaHelper::makeBooking($ev, ['starts_at' => gmdate('Y-m-d H:i:s', time() + 4 * 86400), 'status' => 'cancelled', 'guest_name' => 'Cancelada Carla']);
$old = SaHelper::makeBooking($ev, ['starts_at' => gmdate('Y-m-d H:i:s', time() - 40 * 86400), 'guest_name' => 'Muy Antigua']);
$recent = SaHelper::makeBooking($ev, ['starts_at' => gmdate('Y-m-d H:i:s', time() - 10 * 86400), 'guest_name' => 'Reciente Rosa']);
$foreign = SaHelper::makeBooking($ev, ['starts_at' => gmdate('Y-m-d H:i:s', time() + 5 * 86400), 'host_id' => $other, 'guest_name' => 'De Otro']);
$feed = IcsService::hostFeed($hostId);
foreach (['Pedro Ñ' => true, 'Ana Pendiente' => true, 'Reciente Rosa' => true, 'Cancelada Carla' => false, 'Muy Antigua' => false, 'De Otro' => false] as $name => $should) {
    T::ok((strpos($feed, $name) !== false) === $should, 'feed ' . ($should ? 'incluye' : 'excluye') . ' a ' . $name);
}
$fv = $py('validate', $feed);
T::ok(isset($fv['events']) && count($fv['events']) >= 3 && strpos($feed, 'REFRESH-INTERVAL') !== false, 'el feed es un calendario válido con intervalo de actualización');
T::ok(strpos($feed, 'Cliente: Pedro') !== false, 'el feed del anfitrión incluye datos del cliente');
$many = IcsService::generateMany([BookingService::display(BookingService::find($soon)), BookingService::display(BookingService::find($pending))]);
T::eq(2, count($py('validate', $many)['events'] ?? []), 'generateMany con dos citas');
T::eq(0, count($py('validate', IcsService::hostFeed(999999))['events'] ?? [1]), 'anfitrión inexistente: calendario vacío válido');

T::section('Enlaces para agregar a Google Calendar y Outlook');
$g = IcsService::googleLink($b);
parse_str((string) parse_url($g, PHP_URL_QUERY), $q);
T::ok(strpos($g, 'https://calendar.google.com/calendar/render?action=TEMPLATE') === 0 && $q['dates'] === '20261012T160000Z/20261012T164500Z' && strpos($q['text'], 'inicial') !== false && $q['location'] === 'Zona 10, Torre Ñ; Oficina 4', 'googleLink con fechas UTC y textos codificados');
$o = IcsService::outlookLink($b);
parse_str((string) parse_url($o, PHP_URL_QUERY), $q);
T::ok(strpos($o, 'https://outlook.live.com/calendar/0/deeplink/compose?') === 0 && $q['startdt'] === '2026-10-12T16:00:00Z' && $q['enddt'] === '2026-10-12T16:45:00Z', 'outlookLink con fechas ISO');

T::section('Lectura: zonas horarias, cambio de horario, todo el día, EXDATE');
$wrap = static fn (string $events, string $extra = ''): string => "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Prueba//ES\r\n" . $extra . $events . "END:VCALENDAR\r\n";
$vevent = static fn (string $body): string => "BEGIN:VEVENT\r\nUID:" . bin2hex(random_bytes(4)) . "@t\r\n" . $body . "END:VEVENT\r\n";
$fmt = static fn (array $pairs): array => array_map(static fn (array $p): string => gmdate('Y-m-d H:i', $p[0]) . '/' . gmdate('H:i', $p[1]), $pairs);
$span = static fn (string $a, string $b): array => [strtotime($a . ' UTC'), strtotime($b . ' UTC')];
[$f, $t] = $span('2026-03-01', '2026-03-31');

$ny = $wrap($vevent("DTSTART;TZID=America/New_York:20260302T100000\r\nDTEND;TZID=America/New_York:20260302T110000\r\nRRULE:FREQ=WEEKLY;COUNT=3\r\n"));
T::eq(['2026-03-02 15:00/16:00', '2026-03-09 14:00/15:00', '2026-03-16 14:00/15:00'], $fmt(IcsService::parse($ny, $f, $t)), 'America/New_York semanal conserva las 10:00 locales al cambiar a horario de verano');
$custom = $wrap($vevent("DTSTART;TZID=Zona Propia:20260302T100000\r\nDTEND;TZID=Zona Propia:20260302T110000\r\nRRULE:FREQ=WEEKLY;COUNT=3\r\n"),
    "BEGIN:VTIMEZONE\r\nTZID:Zona Propia\r\nBEGIN:STANDARD\r\nDTSTART:19701101T020000\r\nTZOFFSETFROM:-0400\r\nTZOFFSETTO:-0500\r\nRRULE:FREQ=YEARLY;BYMONTH=11;BYDAY=1SU\r\nEND:STANDARD\r\nBEGIN:DAYLIGHT\r\nDTSTART:19700308T020000\r\nTZOFFSETFROM:-0500\r\nTZOFFSETTO:-0400\r\nRRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU\r\nEND:DAYLIGHT\r\nEND:VTIMEZONE\r\n");
T::eq(['2026-03-02 15:00/16:00', '2026-03-09 14:00/15:00', '2026-03-16 14:00/15:00'], $fmt(IcsService::parse($custom, $f, $t)), 'VTIMEZONE propio con reglas de verano');
$win = str_replace('America/New_York', 'Eastern Standard Time', $ny);
T::eq($fmt(IcsService::parse($ny, $f, $t)), $fmt(IcsService::parse($win, $f, $t)), 'nombre de zona de Windows reconocido');
$mozilla = str_replace('TZID=America/New_York', 'TZID=/mozilla.org/20050126_1/America/New_York', $ny);
T::eq($fmt(IcsService::parse($ny, $f, $t)), $fmt(IcsService::parse($mozilla, $f, $t)), 'TZID con prefijo de herramienta reconocido');

[$f2, $t2] = $span('2026-10-01', '2026-10-31');
$allday = $wrap($vevent("DTSTART;VALUE=DATE:20261010\r\nDTEND;VALUE=DATE:20261012\r\nSUMMARY:Feriado\r\n"));
T::eq(['2026-10-10 06:00/06:00'], array_map(static fn (array $p): string => gmdate('Y-m-d H:i', $p[0]) . '/' . gmdate('H:i', $p[1]), array_slice(IcsService::parse($allday, $f2, $t2, 'America/Guatemala'), 0, 1)), 'todo el día: empieza a las 00:00 locales de Guatemala');
$p = IcsService::parse($allday, $f2, $t2, 'America/Guatemala');
T::eq(2 * 86400, $p[0][1] - $p[0][0], 'todo el día de 2 días dura 48 h');
$oneday = $wrap($vevent("DTSTART;VALUE=DATE:20261010\r\n"));
T::eq(86400, IcsService::parse($oneday, $f2, $t2, 'America/Guatemala')[0][1] - IcsService::parse($oneday, $f2, $t2, 'America/Guatemala')[0][0], 'todo el día sin DTEND dura un día');
$adRep = $wrap($vevent("DTSTART;VALUE=DATE:20261010\r\nRRULE:FREQ=WEEKLY;COUNT=3\r\nEXDATE;VALUE=DATE:20261017\r\n"));
T::eq(['2026-10-10 06:00', '2026-10-24 06:00'], array_map(static fn (array $p): string => gmdate('Y-m-d H:i', $p[0]), IcsService::parse($adRep, $f2, $t2, 'America/Guatemala')), 'todo el día repetido con EXDATE de fecha');

$utc = $wrap($vevent("DTSTART:20261102T090000Z\r\nDTEND:20261102T100000Z\r\nRRULE:FREQ=DAILY;COUNT=4\r\nEXDATE:20261104T090000Z\r\n"));
[$f3, $t3] = $span('2026-11-01', '2026-11-30');
T::eq(['2026-11-02 09:00/10:00', '2026-11-03 09:00/10:00', '2026-11-05 09:00/10:00'], $fmt(IcsService::parse($utc, $f3, $t3)), 'UTC con Z, DAILY COUNT y EXDATE');
$dur = $wrap($vevent("DTSTART:20261102T090000Z\r\nDURATION:PT1H30M\r\n"));
T::eq(['2026-11-02 09:00/10:30'], $fmt(IcsService::parse($dur, $f3, $t3)), 'DURATION');
$durD = $wrap($vevent("DTSTART:20261102T090000Z\r\nDURATION:P1DT2H\r\n"));
T::eq(26 * 3600, IcsService::parse($durD, $f3, $t3)[0][1] - IcsService::parse($durD, $f3, $t3)[0][0], 'DURATION con días y horas');
$float = $wrap($vevent("DTSTART:20261020T090000\r\nDTEND:20261020T100000\r\n"));
T::eq(['2026-10-20 15:00/16:00'], $fmt(IcsService::parse($float, $f2, $t2, 'America/Guatemala')), 'hora flotante = zona indicada (Guatemala)');
T::eq(['2026-10-20 13:00/14:00'], $fmt(IcsService::parse($float, $f2, $t2, 'America/New_York')), 'hora flotante con otra zona');

T::section('Lectura: reglas de repetición');
$rule = static fn (string $start, string $rrule, string $extra = ''): string => $wrap($vevent("DTSTART:$start\r\nDTEND:" . gmdate('Ymd\THis\Z', strtotime(str_replace(['T', 'Z'], [' ', ''], $start) . ' UTC') + 3600) . "\r\nRRULE:$rrule\r\n$extra"));
[$fa, $ta] = $span('2026-01-01', '2026-05-01');
$days = static fn (array $pairs): array => array_map(static fn (array $p): string => gmdate('Y-m-d', $p[0]), $pairs);
T::eq(['2026-01-13', '2026-02-10', '2026-03-10', '2026-04-14'], $days(IcsService::parse($rule('20260113T100000Z', 'FREQ=MONTHLY;BYDAY=2TU'), $fa, $ta)), 'MONTHLY BYDAY=2TU (segundo martes)');
T::eq(['2026-01-30', '2026-02-27', '2026-03-31', '2026-04-30'], $days(IcsService::parse($rule('20260130T100000Z', 'FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1'), $fa, $ta)), 'MONTHLY último día hábil con BYSETPOS=-1');
T::eq(['2026-01-15', '2026-02-15', '2026-03-15', '2026-04-15'], $days(IcsService::parse($rule('20250915T100000Z', 'FREQ=MONTHLY;BYMONTHDAY=15'), $fa, $ta)), 'MONTHLY BYMONTHDAY=15 desde hace meses');
T::eq(['2026-01-31', '2026-03-31'], $days(IcsService::parse($rule('20260131T100000Z', 'FREQ=MONTHLY;COUNT=3'), $fa, $ta)), 'MONTHLY el día 31 se salta los meses cortos');
T::eq(['2026-01-30', '2026-02-27'], $days(IcsService::parse($rule('20260130T100000Z', 'FREQ=MONTHLY;BYMONTHDAY=-1,-2;BYDAY=FR'), $fa, $ta)), 'BYMONTHDAY negativo combinado con BYDAY');
T::eq(['2026-11-02', '2026-11-04', '2026-11-16', '2026-11-18'], $days(IcsService::parse($rule('20261102T090000Z', 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;UNTIL=20261120T000000Z'), $f3, $t3)), 'WEEKLY INTERVAL=2 BYDAY y UNTIL');
T::eq(['2026-11-02', '2026-11-04', '2026-11-06'], $days(IcsService::parse($rule('20261102T090000Z', 'FREQ=DAILY;INTERVAL=2;COUNT=3'), $f3, $t3)), 'DAILY INTERVAL=2 COUNT=3');
T::eq(['2026-06-15'], $days(IcsService::parse($rule('20200615T100000Z', 'FREQ=YEARLY'), $span('2026-01-01', '2026-12-31')[0], $span('2026-01-01', '2026-12-31')[1])), 'YEARLY desde 2020');
T::eq([], IcsService::parse($rule('20200101T100000Z', 'FREQ=DAILY;COUNT=5'), $f3, $t3), 'serie con COUNT ya terminada no ocupa nada');
[$fr, $tr] = $span('2026-11-01', '2026-11-12');
T::eq(['2026-11-02', '2026-11-09', '2026-11-11'], $days(IcsService::parse($rule('20261102T090000Z', 'FREQ=WEEKLY', "RDATE:20261111T090000Z\r\n"), $fr, $tr)), 'RDATE agrega una ocurrencia extra');
$t0 = microtime(true);
$perf = IcsService::parse($rule('20000101T100000Z', 'FREQ=DAILY'), $span('2026-11-01', '2026-11-08')[0], $span('2026-11-01', '2026-11-08')[1]);
T::ok(count($perf) === 7 && microtime(true) - $t0 < 1.0, 'serie diaria sin fin desde el año 2000: salto rápido (' . count($perf) . ' ocurrencias, ' . round(microtime(true) - $t0, 3) . ' s)');
$t0 = microtime(true);
$perf2 = IcsService::parse($rule('20000101T100000Z', 'FREQ=DAILY'), 0, 4102444800);
T::ok(count($perf2) <= 2000 && microtime(true) - $t0 < 3.0, 'límite de expansión: ventana enorme limitada a 2000 ocurrencias por evento (' . count($perf2) . ', ' . round(microtime(true) - $t0, 2) . ' s)');
T::eq(1, count(IcsService::parse($rule('20261102T090000Z', 'FREQ=SECONDLY;COUNT=5'), $f3, $t3)), 'FREQ no soportada se trata como evento único');

T::section('Lectura: eventos que se ignoran, instancias modificadas y entradas torcidas');
$ign = $wrap($vevent("DTSTART:20261102T090000Z\r\nDTEND:20261102T100000Z\r\nSTATUS:CANCELLED\r\n") . $vevent("DTSTART:20261103T090000Z\r\nDTEND:20261103T100000Z\r\nTRANSP:TRANSPARENT\r\n") . $vevent("DTSTART:20261104T090000Z\r\nDTEND:20261104T100000Z\r\nTRANSP:OPAQUE\r\nSTATUS:TENTATIVE\r\n"));
T::eq(['2026-11-04 09:00/10:00'], $fmt(IcsService::parse($ign, $f3, $t3)), 'STATUS:CANCELLED y TRANSP:TRANSPARENT se ignoran; TENTATIVE ocupa');
$ov = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//P//ES\r\n"
    . "BEGIN:VEVENT\r\nUID:serie@t\r\nDTSTART:20261102T090000Z\r\nDTEND:20261102T100000Z\r\nRRULE:FREQ=DAILY;COUNT=4\r\nEND:VEVENT\r\n"
    . "BEGIN:VEVENT\r\nUID:serie@t\r\nRECURRENCE-ID:20261103T090000Z\r\nDTSTART:20261103T150000Z\r\nDTEND:20261103T160000Z\r\nEND:VEVENT\r\n"
    . "BEGIN:VEVENT\r\nUID:serie@t\r\nRECURRENCE-ID:20261104T090000Z\r\nDTSTART:20261104T090000Z\r\nDTEND:20261104T100000Z\r\nSTATUS:CANCELLED\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
T::eq(['2026-11-02 09:00/10:00', '2026-11-03 15:00/16:00', '2026-11-05 09:00/10:00'], $fmt(IcsService::parse($ov, $f3, $t3)), 'instancia movida y instancia cancelada de una serie');
T::eq([], IcsService::parse('esto no es un calendario', $f3, $t3), 'texto cualquiera devuelve vacío');
T::eq([], IcsService::parse('', $f3, $t3), 'cadena vacía devuelve vacío');
T::eq(false, IcsService::isCalendar('<html>hola</html>'), 'isCalendar rechaza HTML');
$lf = "\xEF\xBB\xBFBEGIN:VCALENDAR\nVERSION:2.0\nBEGIN:VEVENT\nUID:a@t\nDTSTART:20261102T090000Z\nDTEND:20261102T\n 100000Z\nRRULE:FREQ=DAILY;\n COUNT=2\nEND:VEVENT\nEND:VCALENDAR\n";
T::eq(['2026-11-02 09:00/10:00', '2026-11-03 09:00/10:00'], $fmt(IcsService::parse($lf, $f3, $t3)), 'BOM, saltos LF y líneas plegadas');
$badEv = $wrap($vevent("DTSTART:garbage\r\nDTEND:20261102T100000Z\r\n") . $vevent("DTSTART:20261105T090000Z\r\nDTEND:20261105T080000Z\r\n") . $vevent("DTSTART:20261106T090000Z\r\nDTEND:20261106T100000Z\r\n"));
T::eq(['2026-11-06 09:00/10:00'], $fmt(IcsService::parse($badEv, $f3, $t3)), 'eventos mal formados o con fin anterior al inicio se descartan sin afectar al resto');
$edge = $wrap($vevent("DTSTART:20261031T230000Z\r\nDTEND:20261101T010000Z\r\n"));
T::eq(1, count(IcsService::parse($edge, $f3, $t3)), 'un evento que empieza antes de la ventana y termina dentro se incluye');
T::ok(IcsService::parseEvents($utc, $f3, $t3)[0]['uid'] !== null, 'parseEvents devuelve el UID');

T::section('Comparación con una biblioteca independiente (recurring-ical-events)');
$corpus = [
    'semanal NY con cambio de horario' => $ny,
    'diaria con EXDATE' => $utc,
    'mensual 2º martes' => $rule('20260113T100000Z', 'FREQ=MONTHLY;BYDAY=2TU'),
    'mensual último día hábil' => $rule('20260130T100000Z', 'FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1'),
    'mensual día 15' => $rule('20250915T100000Z', 'FREQ=MONTHLY;BYMONTHDAY=15'),
    'semanal cada 2 semanas' => $rule('20261102T090000Z', 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;UNTIL=20261120T000000Z'),
    'instancias modificadas' => $ov,
    'anual' => $rule('20200615T100000Z', 'FREQ=YEARLY'),
    'diaria sin fin desde 2024' => $rule('20240301T100000Z', 'FREQ=DAILY;INTERVAL=3'),
    'semanal jueves en Madrid con verano' => $wrap($vevent("DTSTART;TZID=Europe/Madrid:20260319T180000\r\nDTEND;TZID=Europe/Madrid:20260319T190000\r\nRRULE:FREQ=WEEKLY;BYDAY=TH;COUNT=8\r\n")),
];
foreach ($corpus as $name => $text) {
    [$ff, $tt] = $span('2026-01-01', '2026-12-31');
    $mine = IcsService::parse($text, $ff, $tt);
    $ref = $py('expand', $text, (string) $ff, (string) $tt);
    T::eq($ref, $mine, 'coincide con la biblioteca: ' . $name);
}

T::done();
