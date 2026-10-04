<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Settings;
use App\Core\Tz;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Generación (RFC 5545) y lectura de archivos .ics, y enlaces para agregar la cita a Google Calendar y Outlook. */
final class IcsService
{
    private const MAX_OCCURRENCES_PER_EVENT = 2000;
    private const MAX_INTERVALS = 20000;
    private const MAX_PERIODS = 30000;

    /** Nombres de zona de Windows (los usa Outlook) -> zona IANA. */
    private const WINDOWS_ZONES = [
        'Eastern Standard Time' => 'America/New_York', 'Central Standard Time' => 'America/Chicago',
        'Mountain Standard Time' => 'America/Denver', 'Pacific Standard Time' => 'America/Los_Angeles',
        'US Mountain Standard Time' => 'America/Phoenix', 'Alaskan Standard Time' => 'America/Anchorage',
        'Hawaiian Standard Time' => 'Pacific/Honolulu', 'Central America Standard Time' => 'America/Guatemala',
        'Central Standard Time (Mexico)' => 'America/Mexico_City', 'SA Pacific Standard Time' => 'America/Bogota',
        'SA Western Standard Time' => 'America/La_Paz', 'SA Eastern Standard Time' => 'America/Cayenne',
        'Argentina Standard Time' => 'America/Argentina/Buenos_Aires', 'E. South America Standard Time' => 'America/Sao_Paulo',
        'Pacific SA Standard Time' => 'America/Santiago', 'Venezuela Standard Time' => 'America/Caracas',
        'GMT Standard Time' => 'Europe/London', 'Greenwich Standard Time' => 'Atlantic/Reykjavik',
        'W. Europe Standard Time' => 'Europe/Berlin', 'Romance Standard Time' => 'Europe/Paris',
        'Central Europe Standard Time' => 'Europe/Budapest', 'Central European Standard Time' => 'Europe/Warsaw',
        'E. Europe Standard Time' => 'Europe/Chisinau', 'FLE Standard Time' => 'Europe/Kiev',
        'Russian Standard Time' => 'Europe/Moscow', 'Israel Standard Time' => 'Asia/Jerusalem',
        'India Standard Time' => 'Asia/Kolkata', 'China Standard Time' => 'Asia/Shanghai',
        'Tokyo Standard Time' => 'Asia/Tokyo', 'AUS Eastern Standard Time' => 'Australia/Sydney',
        'New Zealand Standard Time' => 'Pacific/Auckland', 'UTC' => 'UTC',
    ];

    // ======================================================== GENERACIÓN

    /** .ics de una cita (salida de BookingService::display). Una cancelada sale como METHOD:CANCEL. */
    public static function generate(array $bookingDisplay, array $opts = []): string
    {
        return self::calendar([$bookingDisplay], $opts);
    }

    public static function generateMany(array $list, array $opts = []): string
    {
        return self::calendar($list, $opts);
    }

    /** Calendario suscribible del anfitrión: citas pendientes/confirmadas desde hace 30 días hacia adelante. */
    public static function hostFeed(int $hostId): string
    {
        $host = Db::one('SELECT * FROM hosts WHERE id = ?', [$hostId]);
        if (!$host) {
            return self::calendar([], ['name' => (string) Settings::get('business_name', 'Agenda Premium')]);
        }
        $rows = Db::all(
            "SELECT b.* FROM bookings b WHERE b.status IN ('pending','confirmed') AND b.starts_at >= ?
               AND (b.host_id = ? OR EXISTS (SELECT 1 FROM booking_hosts bh WHERE bh.booking_id = b.id AND bh.host_id = ?))
             ORDER BY b.starts_at LIMIT 3000",
            [Clock::utc(Clock::now() - 30 * 86400), $hostId, $hostId]
        );
        $events = [];
        foreach (Db::all('SELECT id, name, location FROM event_types') as $ev) {
            $events[(int) $ev['id']] = $ev;
        }
        $list = [];
        foreach ($rows as $r) {
            $r['event'] = $events[(int) $r['event_type_id']] ?? [];
            $r['host'] = $host;
            $list[] = $r;
        }
        return self::calendar($list, [
            'audience' => 'host',
            'name' => (string) Settings::get('business_name', 'Agenda Premium') . ' · ' . $host['name'],
            'feed' => true,
        ]);
    }

    public static function googleLink(array $b): string
    {
        $q = [
            'action' => 'TEMPLATE',
            'text' => self::title($b, 'guest'),
            'dates' => gmdate('Ymd\THis\Z', Tz::ts((string) $b['starts_at'])) . '/' . gmdate('Ymd\THis\Z', Tz::ts((string) $b['ends_at'])),
            'details' => self::description($b, 'guest'),
            'location' => self::location($b),
        ];
        return 'https://calendar.google.com/calendar/render?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }

    public static function outlookLink(array $b): string
    {
        $q = [
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
            'subject' => self::title($b, 'guest'),
            'startdt' => Tz::iso((string) $b['starts_at']),
            'enddt' => Tz::iso((string) $b['ends_at']),
            'body' => self::description($b, 'guest'),
            'location' => self::location($b),
        ];
        return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }

    private static function calendar(array $list, array $opts): string
    {
        $audience = (string) ($opts['audience'] ?? 'guest');
        $cancel = count($list) === 1 && ($list[0]['status'] ?? '') === 'cancelled';
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Agenda Premium//Citas//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:' . ($cancel ? 'CANCEL' : 'PUBLISH'),
        ];
        if (!empty($opts['name'])) {
            $lines[] = 'X-WR-CALNAME:' . self::esc((string) $opts['name']);
        }
        if (!empty($opts['feed'])) {
            $lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:PT1H';
            $lines[] = 'X-PUBLISHED-TTL:PT1H';
        }
        $seq = self::sequences($list);
        foreach ($list as $b) {
            $lines = array_merge($lines, self::vevent($b, $audience, $seq[(int) ($b['id'] ?? 0)] ?? 0));
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    /** @return string[] */
    private static function vevent(array $b, string $audience, int $sequence): array
    {
        $status = (string) ($b['status'] ?? 'confirmed');
        $icsStatus = match ($status) {
            'cancelled', 'rejected' => 'CANCELLED',
            'pending' => 'TENTATIVE',
            default => 'CONFIRMED',
        };
        $stamp = isset($b['updated_at']) ? Tz::ts((string) $b['updated_at']) : Clock::now();
        $host = (array) ($b['host'] ?? []);
        $l = [
            'BEGIN:VEVENT',
            'UID:' . self::uid($b),
            'DTSTAMP:' . gmdate('Ymd\THis\Z', $stamp),
            'SEQUENCE:' . (int) ($b['sequence'] ?? $sequence),
            'DTSTART:' . gmdate('Ymd\THis\Z', Tz::ts((string) $b['starts_at'])),
            'DTEND:' . gmdate('Ymd\THis\Z', Tz::ts((string) $b['ends_at'])),
            'SUMMARY:' . self::esc(self::title($b, $audience)),
            'STATUS:' . $icsStatus,
            'TRANSP:OPAQUE',
            'DESCRIPTION:' . self::esc(self::description($b, $audience)),
        ];
        $loc = self::location($b);
        if ($loc !== '') {
            $l[] = 'LOCATION:' . self::esc($loc);
        }
        $link = self::link($b);
        if ($link !== '') {
            $l[] = 'URL:' . $link;
        }
        $email = trim((string) ($host['email'] ?? '')) ?: trim((string) Settings::get('email', ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $l[] = 'ORGANIZER;CN=' . self::param((string) ($host['name'] ?? Settings::get('business_name', ''))) . ':mailto:' . $email;
        }
        if ($icsStatus !== 'CANCELLED') {
            $l[] = 'BEGIN:VALARM';
            $l[] = 'ACTION:DISPLAY';
            $l[] = 'DESCRIPTION:' . self::esc('Recordatorio: ' . self::title($b, 'guest'));
            $l[] = 'TRIGGER:-PT1H';
            $l[] = 'END:VALARM';
        }
        $l[] = 'END:VEVENT';
        return $l;
    }

    private static function uid(array $b): string
    {
        $host = (string) parse_url(Mailer::baseUrl(), PHP_URL_HOST);
        $key = (string) ($b['token'] ?? ('b' . ($b['id'] ?? '0')));
        return 'ap-' . $key . '@' . ($host !== '' ? $host : 'agenda.local');
    }

    /** SEQUENCE: crece con cada cambio registrado en el historial de la cita. */
    private static function sequences(array $list): array
    {
        $ids = [];
        foreach ($list as $b) {
            if (!isset($b['sequence']) && !empty($b['id'])) {
                $ids[] = (int) $b['id'];
            }
        }
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = Db::all(
                'SELECT booking_id, COUNT(*) AS n FROM booking_history WHERE booking_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ') GROUP BY booking_id',
                $chunk
            );
            foreach ($rows as $r) {
                $out[(int) $r['booking_id']] = (int) $r['n'];
            }
        }
        return $out;
    }

    private static function title(array $b, string $audience): string
    {
        $event = (string) ($b['event']['name'] ?? 'Cita');
        if ($audience === 'host') {
            return $event . ' · ' . (string) ($b['guest_name'] ?? '');
        }
        $host = (string) ($b['host']['name'] ?? '');
        return $host !== '' ? $event . ' con ' . $host : $event;
    }

    private static function description(array $b, string $audience): string
    {
        $parts = [];
        if ($audience === 'host') {
            $parts[] = 'Cliente: ' . (string) ($b['guest_name'] ?? '');
            foreach (['guest_email' => 'Correo', 'guest_phone' => 'Teléfono'] as $k => $label) {
                if (!empty($b[$k])) {
                    $parts[] = $label . ': ' . $b[$k];
                }
            }
            if (!empty($b['notes'])) {
                $parts[] = 'Notas: ' . $b['notes'];
            }
        } else {
            $parts[] = self::title($b, 'guest');
        }
        if (!empty($b['video_url'])) {
            $parts[] = 'Videollamada: ' . $b['video_url'];
        }
        $link = self::link($b);
        if ($link !== '') {
            $parts[] = ($audience === 'host' ? 'Ver la cita: ' : 'Gestiona tu cita: ') . $link;
        }
        return implode("\n", $parts);
    }

    private static function location(array $b): string
    {
        foreach ([$b['location'] ?? '', $b['video_url'] ?? '', $b['event']['location'] ?? ''] as $v) {
            if (trim((string) $v) !== '') {
                return trim((string) $v);
            }
        }
        return '';
    }

    private static function link(array $b): string
    {
        $pub = (string) ($b['public_url'] ?? '');
        if (preg_match('~^https?://~i', $pub)) {
            return $pub;
        }
        $base = Mailer::baseUrl();
        return !empty($b['token']) && $base !== '' ? $base . '/reserva/' . $b['token'] : '';
    }

    private static function esc(string $s): string
    {
        $s = mb_scrub($s);
        return str_replace(['\\', ';', ',', "\r\n", "\r", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $s);
    }

    /** Valor de parámetro entre comillas (sin comillas dentro). */
    private static function param(string $s): string
    {
        return '"' . str_replace(['"', "\r", "\n"], ["'", ' ', ' '], mb_scrub($s)) . '"';
    }

    /** Plegado a 75 octetos sin partir caracteres UTF-8. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $cur = '';
        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if (strlen($cur) + strlen($ch) > 75) {
                $out .= $cur . "\r\n";
                $cur = ' ';
            }
            $cur .= $ch;
        }
        return $out . $cur;
    }

    // ============================================================ LECTURA

    public static function isCalendar(string $ics): bool
    {
        return (bool) preg_match('/^\xEF\xBB\xBF?\s*BEGIN:VCALENDAR/i', substr($ics, 0, 512));
    }

    /** @return array<int,array{0:int,1:int}> intervalos ocupados [inicio, fin] (timestamps UTC) dentro de [$fromTs, $toTs]. */
    public static function parse(string $ics, int $fromTs, int $toTs, ?string $defaultTz = null): array
    {
        return array_map(static fn (array $e): array => [$e['start'], $e['end']], self::parseEvents($ics, $fromTs, $toTs, $defaultTz));
    }

    /**
     * Como parse() pero con el UID de cada evento.
     * Los eventos de todo el día bloquean el día completo en la zona $defaultTz (por defecto, la del negocio).
     * @return array<int,array{start:int,end:int,uid:?string}>
     */
    public static function parseEvents(string $ics, int $fromTs, int $toTs, ?string $defaultTz = null): array
    {
        $tz = Tz::safe($defaultTz, Settings::tz());
        try {
            [$events, $zones] = self::readComponents($ics);
        } catch (Throwable $e) {
            return [];
        }
        // Instancias modificadas de una serie: reemplazan la ocurrencia original
        $overridden = [];
        foreach ($events as $ev) {
            if (isset($ev['RECURRENCE-ID'])) {
                $rid = self::dateValue($ev['RECURRENCE-ID'][0]);
                if ($rid !== null) {
                    $overridden[self::text($ev, 'UID') . '|' . self::toTs($rid, $zones, $tz)] = true;
                }
            }
        }
        $out = [];
        foreach ($events as $ev) {
            if (strtoupper(self::text($ev, 'STATUS')) === 'CANCELLED'
                || strtoupper(self::text($ev, 'TRANSP')) === 'TRANSPARENT'
                || strtoupper(self::text($ev, 'X-MICROSOFT-CDO-BUSYSTATUS')) === 'FREE') {
                continue;
            }
            try {
                foreach (self::expand($ev, $zones, $tz, $fromTs, $toTs, $overridden) as $iv) {
                    $out[] = $iv;
                    if (count($out) >= self::MAX_INTERVALS) {
                        break 2;
                    }
                }
            } catch (Throwable $e) {
                continue; // un evento mal formado no invalida el resto
            }
        }
        usort($out, static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);
        return $out;
    }

    /** @return array{0:array<int,array<string,array>>,1:array<string,array>} [eventos con propiedades, definiciones VTIMEZONE] */
    private static function readComponents(string $ics): array
    {
        $ics = preg_replace('/^\xEF\xBB\xBF/', '', $ics) ?? $ics;
        $ics = preg_replace('/\r\n|\r/', "\n", $ics) ?? $ics;
        $ics = preg_replace('/\n[ \t]/', '', $ics) ?? $ics;
        $events = [];
        $zones = [];
        $stack = [];
        $curEvent = null;
        $curZone = null;
        $curSub = null;
        foreach (explode("\n", $ics) as $raw) {
            $line = rtrim($raw);
            if ($line === '') {
                continue;
            }
            $p = self::splitLine($line);
            if ($p === null) {
                continue;
            }
            [$name, $params, $value] = $p;
            if ($name === 'BEGIN') {
                $type = strtoupper($value);
                $stack[] = $type;
                if ($type === 'VEVENT') {
                    $curEvent = [];
                } elseif ($type === 'VTIMEZONE') {
                    $curZone = ['id' => '', 'loc' => '', 'subs' => []];
                } elseif (($type === 'STANDARD' || $type === 'DAYLIGHT') && $curZone !== null) {
                    $curSub = ['dtstart' => null, 'to' => 0, 'from' => 0, 'rrule' => null];
                }
                continue;
            }
            if ($name === 'END') {
                $type = strtoupper($value);
                array_pop($stack);
                if ($type === 'VEVENT' && $curEvent !== null) {
                    if (isset($curEvent['DTSTART'])) {
                        $events[] = $curEvent;
                    }
                    $curEvent = null;
                } elseif ($type === 'VTIMEZONE' && $curZone !== null) {
                    if ($curZone['id'] !== '') {
                        $zones[$curZone['id']] = $curZone;
                    }
                    $curZone = null;
                } elseif (($type === 'STANDARD' || $type === 'DAYLIGHT') && $curSub !== null && $curZone !== null) {
                    if ($curSub['dtstart'] !== null) {
                        $curZone['subs'][] = $curSub;
                    }
                    $curSub = null;
                }
                continue;
            }
            $top = end($stack);
            if ($top === 'VEVENT' && $curEvent !== null) {
                $curEvent[$name][] = ['params' => $params, 'value' => $value];
            } elseif ($curSub !== null && $curZone !== null && ($top === 'STANDARD' || $top === 'DAYLIGHT')) {
                if ($name === 'DTSTART') {
                    $curSub['dtstart'] = self::dateValue(['params' => $params, 'value' => $value]);
                } elseif ($name === 'TZOFFSETTO') {
                    $curSub['to'] = self::offsetSeconds($value);
                } elseif ($name === 'TZOFFSETFROM') {
                    $curSub['from'] = self::offsetSeconds($value);
                } elseif ($name === 'RRULE') {
                    $curSub['rrule'] = self::parseRule($value);
                }
            } elseif ($top === 'VTIMEZONE' && $curZone !== null) {
                if ($name === 'TZID') {
                    $curZone['id'] = $value;
                } elseif ($name === 'X-LIC-LOCATION') {
                    $curZone['loc'] = $value;
                }
            }
        }
        return [$events, $zones];
    }

    /** @return array{0:string,1:array<string,string>,2:string}|null [NOMBRE, parámetros, valor] */
    private static function splitLine(string $line): ?array
    {
        $inQuote = false;
        $colon = -1;
        $len = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            $c = $line[$i];
            if ($c === '"') {
                $inQuote = !$inQuote;
            } elseif ($c === ':' && !$inQuote) {
                $colon = $i;
                break;
            }
        }
        if ($colon < 1) {
            return null;
        }
        $head = substr($line, 0, $colon);
        $value = substr($line, $colon + 1);
        $parts = [];
        $cur = '';
        $inQuote = false;
        foreach (str_split($head) as $c) {
            if ($c === '"') {
                $inQuote = !$inQuote;
            }
            if ($c === ';' && !$inQuote) {
                $parts[] = $cur;
                $cur = '';
            } else {
                $cur .= $c;
            }
        }
        $parts[] = $cur;
        $name = strtoupper((string) array_shift($parts));
        $params = [];
        foreach ($parts as $pp) {
            $eq = strpos($pp, '=');
            if ($eq !== false) {
                $params[strtoupper(substr($pp, 0, $eq))] = trim(substr($pp, $eq + 1), '"');
            }
        }
        return [$name, $params, $value];
    }

    private static function text(array $ev, string $name): string
    {
        return isset($ev[$name][0]) ? trim((string) $ev[$name][0]['value']) : '';
    }

    /** @return array{allday:bool,utc:bool,local:string,tzid:?string}|null */
    private static function dateValue(array $prop): ?array
    {
        $v = trim((string) $prop['value']);
        $params = (array) $prop['params'];
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $v, $m)) {
            if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return null;
            }
            return ['allday' => true, 'utc' => false, 'local' => "$m[1]-$m[2]-$m[3] 00:00:00", 'tzid' => null];
        }
        if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})(Z?)$/i', $v, $m)) {
            if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || (int) $m[4] > 23 || (int) $m[5] > 59 || (int) $m[6] > 60) {
                return null;
            }
            return [
                'allday' => false,
                'utc' => strtoupper($m[7]) === 'Z',
                'local' => "$m[1]-$m[2]-$m[3] $m[4]:$m[5]:" . sprintf('%02d', min(59, (int) $m[6])),
                'tzid' => ($params['TZID'] ?? null) ?: null,
            ];
        }
        return null;
    }

    private static function offsetSeconds(string $v): int
    {
        if (!preg_match('/^([+-])(\d{2})(\d{2})(\d{2})?$/', trim($v), $m)) {
            return 0;
        }
        $s = (int) $m[2] * 3600 + (int) $m[3] * 60 + (int) ($m[4] ?? 0);
        return $m[1] === '-' ? -$s : $s;
    }

    /** Zona de un TZID: ['tz' => DateTimeZone] o ['custom' => definición VTIMEZONE]. */
    private static function zone(?string $tzid, array $zones, string $default): array
    {
        if ($tzid === null || $tzid === '') {
            return ['tz' => new DateTimeZone($default)];
        }
        if (Tz::valid($tzid)) {
            return ['tz' => new DateTimeZone($tzid)];
        }
        $def = $zones[$tzid] ?? null;
        if ($def !== null && Tz::valid($def['loc'])) {
            return ['tz' => new DateTimeZone($def['loc'])];
        }
        if (isset(self::WINDOWS_ZONES[$tzid])) {
            return ['tz' => new DateTimeZone(self::WINDOWS_ZONES[$tzid])];
        }
        if ($def !== null && $def['subs']) {
            return ['custom' => $def];
        }
        if (preg_match('~([A-Za-z_]+/[A-Za-z_\-]+(?:/[A-Za-z_\-]+)?)$~', $tzid, $m) && Tz::valid($m[1])) {
            return ['tz' => new DateTimeZone($m[1])];
        }
        return ['tz' => new DateTimeZone($default)];
    }

    /** Hora local 'Y-m-d H:i:s' en una zona -> timestamp UTC. */
    private static function localTs(string $local, array $zone): int
    {
        if (isset($zone['tz'])) {
            return (int) (new DateTimeImmutable($local, $zone['tz']))->format('U');
        }
        $naive = (int) (new DateTimeImmutable($local, new DateTimeZone('UTC')))->format('U');
        return $naive - self::customOffset($zone['custom'], $naive);
    }

    /** Desfase (segundos) de una definición VTIMEZONE propia en la hora local $naive (UTC "de pared"). */
    private static function customOffset(array $def, int $naive): int
    {
        $year = (int) gmdate('Y', $naive);
        $best = null;
        $bestTs = null;
        foreach ($def['subs'] as $sub) {
            $ds = (int) (new DateTimeImmutable($sub['dtstart']['local'], new DateTimeZone('UTC')))->format('U');
            $times = [];
            if ($sub['rrule'] !== null && strtoupper((string) ($sub['rrule']['FREQ'] ?? '')) === 'YEARLY' && !empty($sub['rrule']['BYMONTH'])) {
                foreach ([$year - 1, $year] as $y) {
                    if ($y < (int) gmdate('Y', $ds)) {
                        continue;
                    }
                    $day = self::nthWeekdayOfMonth($y, (int) $sub['rrule']['BYMONTH'][0], $sub['rrule']['BYDAY'][0] ?? ['n' => 1, 'd' => 0]);
                    if ($day !== null) {
                        $times[] = gmmktime((int) gmdate('G', $ds), (int) gmdate('i', $ds), (int) gmdate('s', $ds), (int) $sub['rrule']['BYMONTH'][0], $day, $y);
                    }
                }
            } else {
                $times[] = $ds;
            }
            foreach ($times as $t) {
                if ($t <= $naive && ($bestTs === null || $t > $bestTs)) {
                    $bestTs = $t;
                    $best = $sub['to'];
                }
            }
        }
        return $best ?? (int) $def['subs'][0]['from'];
    }

    /** Día del mes del enésimo (o último, n<0) día de la semana $d (0=domingo). */
    private static function nthWeekdayOfMonth(int $year, int $month, array $by): ?int
    {
        $n = (int) $by['n'];
        $dim = (int) gmdate('t', gmmktime(0, 0, 0, $month, 1, $year));
        $days = [];
        for ($d = 1; $d <= $dim; $d++) {
            if ((int) gmdate('w', gmmktime(0, 0, 0, $month, $d, $year)) === (int) $by['d']) {
                $days[] = $d;
            }
        }
        if ($n === 0) {
            return $days[0] ?? null;
        }
        return $n > 0 ? ($days[$n - 1] ?? null) : ($days[count($days) + $n] ?? null);
    }

    private static function toTs(array $dv, array $zones, string $default): int
    {
        if ($dv['utc']) {
            return Tz::ts($dv['local']);
        }
        return self::localTs($dv['local'], self::zone($dv['tzid'], $zones, $default));
    }

    private static function durationSeconds(string $v): ?int
    {
        if (!preg_match('/^([+-])?P(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/i', trim($v), $m)) {
            return null;
        }
        $s = (int) ($m[2] ?? 0) * 604800 + (int) ($m[3] ?? 0) * 86400 + (int) ($m[4] ?? 0) * 3600 + (int) ($m[5] ?? 0) * 60 + (int) ($m[6] ?? 0);
        return ($m[1] ?? '') === '-' ? -$s : $s;
    }

    /** @return array<string,mixed> */
    private static function parseRule(string $v): array
    {
        $r = [];
        foreach (explode(';', $v) as $part) {
            $eq = strpos($part, '=');
            if ($eq === false) {
                continue;
            }
            $k = strtoupper(substr($part, 0, $eq));
            $val = substr($part, $eq + 1);
            switch ($k) {
                case 'BYDAY':
                    $r[$k] = [];
                    foreach (explode(',', $val) as $d) {
                        if (preg_match('/^([+-]?\d{1,2})?(SU|MO|TU|WE|TH|FR|SA)$/i', trim($d), $m)) {
                            $r[$k][] = ['n' => (int) ($m[1] ?? 0), 'd' => array_search(strtoupper($m[2]), ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'], true)];
                        }
                    }
                    break;
                case 'BYMONTHDAY':
                case 'BYMONTH':
                case 'BYSETPOS':
                    $r[$k] = array_map('intval', array_filter(explode(',', $val), 'strlen'));
                    break;
                case 'INTERVAL':
                case 'COUNT':
                    $r[$k] = max(1, (int) $val);
                    break;
                default:
                    $r[$k] = $val;
            }
        }
        return $r;
    }

    /**
     * Expande un VEVENT a intervalos dentro de la ventana.
     * @return array<int,array{start:int,end:int,uid:?string}>
     */
    private static function expand(array $ev, array $zones, string $default, int $fromTs, int $toTs, array $overridden): array
    {
        $dtstart = self::dateValue($ev['DTSTART'][0]);
        if ($dtstart === null) {
            return [];
        }
        $zone = $dtstart['utc'] ? ['tz' => new DateTimeZone('UTC')] : self::zone($dtstart['tzid'], $zones, $default);
        $startTs = self::localTs($dtstart['local'], $zone);
        $uid = self::text($ev, 'UID') ?: null;

        // Duración: todo el día = días completos; con hora = segundos exactos
        $allday = $dtstart['allday'];
        $days = 1;
        $secs = 0;
        if (isset($ev['DTEND'])) {
            $de = self::dateValue($ev['DTEND'][0]);
            if ($de === null) {
                return [];
            }
            if ($allday) {
                $days = (int) round((Tz::ts(substr($de['local'], 0, 10) . ' 00:00:00') - Tz::ts(substr($dtstart['local'], 0, 10) . ' 00:00:00')) / 86400);
            } else {
                $secs = self::toTs($de, $zones, $default) - $startTs;
            }
        } elseif (isset($ev['DURATION'])) {
            $d = self::durationSeconds((string) $ev['DURATION'][0]['value']);
            if ($d === null) {
                return [];
            }
            $allday ? $days = (int) round($d / 86400) : $secs = $d;
        }
        if (($allday && $days < 1) || (!$allday && $secs <= 0)) {
            return [];
        }
        $endOf = static function (string $startLocal, int $startTsOcc) use ($allday, $days, $secs, $zone): int {
            if ($allday) {
                $d = new DateTimeImmutable(substr($startLocal, 0, 10) . ' 00:00:00', new DateTimeZone('UTC'));
                return self::localTs($d->modify('+' . $days . ' days')->format('Y-m-d H:i:s'), $zone);
            }
            return $startTsOcc + $secs;
        };

        $exclude = [];
        foreach (($ev['EXDATE'] ?? []) as $prop) {
            foreach (explode(',', (string) $prop['value']) as $one) {
                $dv = self::dateValue(['params' => $prop['params'], 'value' => $one]);
                if ($dv !== null) {
                    $exclude[$dv['allday'] ? self::localTs($dv['local'], $zone) : self::toTs($dv, $zones, $default)] = true;
                }
            }
        }

        $starts = [[$dtstart['local'], $startTs]];
        if (isset($ev['RRULE'])) {
            $starts = [];
            $rule = self::parseRule((string) $ev['RRULE'][0]['value']);
            foreach (self::recur($dtstart['local'], $rule, $zone, $fromTs - ($allday ? $days * 86400 : $secs) - 2 * 86400, $toTs + 2 * 86400) as $loc) {
                $starts[] = [$loc, self::localTs($loc, $zone)];
            }
            foreach (($ev['RDATE'] ?? []) as $prop) {
                foreach (explode(',', (string) $prop['value']) as $one) {
                    $dv = self::dateValue(['params' => $prop['params'], 'value' => $one]);
                    if ($dv !== null) {
                        $starts[] = [$dv['local'], $dv['allday'] ? self::localTs($dv['local'], $zone) : self::toTs($dv, $zones, $default)];
                    }
                }
            }
        }

        $out = [];
        foreach ($starts as [$loc, $ts]) {
            if (isset($exclude[$ts]) || ($uid !== null && isset($overridden[$uid . '|' . $ts]) && !isset($ev['RECURRENCE-ID']))) {
                continue;
            }
            $end = $endOf($loc, $ts);
            if ($end > $fromTs && $ts < $toTs) {
                $out[] = ['start' => $ts, 'end' => $end, 'uid' => $uid];
                if (count($out) >= self::MAX_OCCURRENCES_PER_EVENT) {
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * Genera inicios locales ("pared") de una regla de recurrencia dentro de la ventana.
     * La aritmética se hace con fechas de pared para conservar la hora local aunque cambie el horario de verano.
     * @return string[]
     */
    private static function recur(string $startLocal, array $rule, array $zone, int $fromTs, int $toTs): array
    {
        $freq = strtoupper((string) ($rule['FREQ'] ?? ''));
        if (!in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
            return [$startLocal];
        }
        $interval = (int) ($rule['INTERVAL'] ?? 1);
        $count = isset($rule['COUNT']) ? (int) $rule['COUNT'] : null;
        $until = null;
        if (!empty($rule['UNTIL'])) {
            $dv = self::dateValue(['params' => [], 'value' => (string) $rule['UNTIL']]);
            if ($dv !== null) {
                $until = $dv['allday'] ? self::localTs(substr($dv['local'], 0, 10) . ' 23:59:59', $zone) : ($dv['utc'] ? Tz::ts($dv['local']) : self::localTs($dv['local'], $zone));
            }
        }
        $wkst = (int) (['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6][strtoupper((string) ($rule['WKST'] ?? 'MO'))] ?? 1);
        $time = substr($startLocal, 11);
        $sd = (int) substr($startLocal, 8, 2);
        $sm = (int) substr($startLocal, 5, 2);
        $sy = (int) substr($startLocal, 0, 4);
        $startNaive = gmmktime(0, 0, 0, $sm, $sd, $sy);

        // Salto de períodos hasta cerca de la ventana (solo sin COUNT, que obliga a contar desde el inicio)
        $k0 = 0;
        if ($count === null && $fromTs > $startNaive) {
            $k0 = match ($freq) {
                'DAILY' => intdiv(intdiv($fromTs - $startNaive, 86400), $interval),
                'WEEKLY' => intdiv(intdiv($fromTs - $startNaive, 604800), $interval),
                'MONTHLY' => intdiv(((int) gmdate('Y', $fromTs) - $sy) * 12 + (int) gmdate('n', $fromTs) - $sm, $interval),
                default => intdiv((int) gmdate('Y', $fromTs) - $sy, $interval),
            };
            $k0 = max(0, $k0 - 1);
        }

        $byDay = $rule['BYDAY'] ?? [];
        $byMonthDay = $rule['BYMONTHDAY'] ?? [];
        $byMonth = $rule['BYMONTH'] ?? [];
        $bySetPos = $rule['BYSETPOS'] ?? [];
        $generated = 0;
        $out = [];

        for ($k = $k0; $k < self::MAX_PERIODS; $k++) {
            $cands = []; // fechas Y-m-d de este período
            switch ($freq) {
                case 'DAILY':
                    $dayTs = gmmktime(0, 0, 0, $sm, $sd + $k * $interval, $sy);
                    $dow = (int) gmdate('w', $dayTs);
                    $ok = (!$byDay || in_array($dow, array_column($byDay, 'd'), true))
                        && (!$byMonthDay || in_array((int) gmdate('j', $dayTs), $byMonthDay, true));
                    if ($ok) {
                        $cands[] = gmdate('Y-m-d', $dayTs);
                    }
                    $periodStart = $dayTs;
                    break;
                case 'WEEKLY':
                    $startDow = (int) gmdate('w', $startNaive);
                    $weekStart = gmmktime(0, 0, 0, $sm, $sd - (($startDow - $wkst + 7) % 7) + $k * $interval * 7, $sy);
                    $dows = $byDay ? array_unique(array_column($byDay, 'd')) : [$startDow];
                    foreach ($dows as $d) {
                        $cands[] = gmdate('Y-m-d', $weekStart + ((($d - $wkst + 7) % 7) * 86400));
                    }
                    sort($cands);
                    $periodStart = $weekStart;
                    break;
                case 'MONTHLY':
                    $mTs = gmmktime(0, 0, 0, $sm + $k * $interval, 1, $sy);
                    $cands = self::monthDays((int) gmdate('Y', $mTs), (int) gmdate('n', $mTs), $byDay, $byMonthDay, $sd);
                    $periodStart = $mTs;
                    break;
                default:
                    $y = $sy + $k * $interval;
                    foreach ($byMonth ?: [$sm] as $mo) {
                        $cands = array_merge($cands, self::monthDays($y, (int) $mo, $byDay, $byMonthDay, $sd));
                    }
                    sort($cands);
                    $periodStart = gmmktime(0, 0, 0, 1, 1, $y);
            }
            if ($byMonth && $freq !== 'YEARLY') {
                $cands = array_values(array_filter($cands, static fn (string $d): bool => in_array((int) substr($d, 5, 2), $byMonth, true)));
            }
            if ($bySetPos && $cands) {
                $sel = [];
                foreach ($bySetPos as $pos) {
                    $i = $pos > 0 ? $pos - 1 : count($cands) + $pos;
                    if (isset($cands[$i])) {
                        $sel[$cands[$i]] = true;
                    }
                }
                $cands = array_keys($sel);
                sort($cands);
            }
            foreach ($cands as $date) {
                $loc = $date . ' ' . $time;
                if ($loc < $startLocal) {
                    continue;
                }
                $ts = self::localTs($loc, $zone);
                if ($until !== null && $ts > $until) {
                    return $out;
                }
                $generated++;
                if ($count !== null && $generated > $count) {
                    return $out;
                }
                $out[] = $loc;
                if ($generated >= self::MAX_PERIODS) {
                    return $out;
                }
            }
            if ($periodStart > $toTs) {
                break;
            }
        }
        return $out;
    }

    /** Fechas Y-m-d de un mes que cumplen BYMONTHDAY / BYDAY (con ordinales) o el día original. */
    private static function monthDays(int $year, int $month, array $byDay, array $byMonthDay, int $startDay): array
    {
        $dim = (int) gmdate('t', gmmktime(0, 0, 0, $month, 1, $year));
        $days = [];
        if ($byMonthDay) {
            foreach ($byMonthDay as $d) {
                $d = $d > 0 ? $d : $dim + $d + 1;
                if ($d >= 1 && $d <= $dim) {
                    $days[$d] = true;
                }
            }
            if ($byDay) {
                $allowed = array_column($byDay, 'd');
                foreach (array_keys($days) as $d) {
                    if (!in_array((int) gmdate('w', gmmktime(0, 0, 0, $month, $d, $year)), $allowed, true)) {
                        unset($days[$d]);
                    }
                }
            }
        } elseif ($byDay) {
            foreach ($byDay as $by) {
                if ($by['n'] !== 0) {
                    $d = self::nthWeekdayOfMonth($year, $month, $by);
                    if ($d !== null) {
                        $days[$d] = true;
                    }
                } else {
                    for ($d = 1; $d <= $dim; $d++) {
                        if ((int) gmdate('w', gmmktime(0, 0, 0, $month, $d, $year)) === $by['d']) {
                            $days[$d] = true;
                        }
                    }
                }
            }
        } elseif ($startDay <= $dim) {
            $days[$startDay] = true;
        }
        ksort($days);
        return array_map(static fn (int $d): string => sprintf('%04d-%02d-%02d', $year, $month, $d), array_keys($days));
    }
}
