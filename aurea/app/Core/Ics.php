<?php
declare(strict_types=1);

namespace Aurea\Core;

/** Generador de iCalendar (RFC 5545). Horas locales con TZID del negocio. */
final class Ics
{
    public static function esc(string $s): string
    {
        return str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\;", "\\,", "\\n", "\\n", "\\n"], $s);
    }

    private static function fold(string $line): string
    {
        $out = '';
        while (strlen($line) > 74) {
            // Corta sin partir caracteres UTF-8
            $cut = 74;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) { $cut--; }
            $out .= substr($line, 0, $cut) . "\r\n ";
            $line = substr($line, $cut);
        }
        return $out . $line;
    }

    private static function dt(string $mysql): string
    {
        return date('Ymd\THis', strtotime($mysql));
    }

    /** @param array[] $events cada uno: uid, start, end, summary, description, location, status, url */
    public static function calendar(array $events, string $name = 'Citas'): string
    {
        $tz = (string)Settings::get('timezone', 'America/Guatemala');
        $L = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//AUREA//Agenda//ES', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::esc($name), 'X-WR-TIMEZONE:' . $tz];
        // Guatemala no tiene horario de verano: bloque VTIMEZONE fijo
        if ($tz === 'America/Guatemala') {
            array_push($L, 'BEGIN:VTIMEZONE', 'TZID:America/Guatemala', 'BEGIN:STANDARD', 'DTSTART:19700101T000000', 'TZOFFSETFROM:-0600', 'TZOFFSETTO:-0600', 'TZNAME:CST', 'END:STANDARD', 'END:VTIMEZONE');
        }
        $stamp = gmdate('Ymd\THis\Z');
        foreach ($events as $ev) {
            $L[] = 'BEGIN:VEVENT';
            $L[] = 'UID:' . $ev['uid'];
            $L[] = 'DTSTAMP:' . $stamp;
            $L[] = 'DTSTART;TZID=' . $tz . ':' . self::dt($ev['start']);
            $L[] = 'DTEND;TZID=' . $tz . ':' . self::dt($ev['end']);
            $L[] = 'SUMMARY:' . self::esc($ev['summary']);
            if (!empty($ev['description'])) { $L[] = 'DESCRIPTION:' . self::esc($ev['description']); }
            if (!empty($ev['location'])) { $L[] = 'LOCATION:' . self::esc($ev['location']); }
            if (!empty($ev['url'])) { $L[] = 'URL:' . $ev['url']; }
            $L[] = 'STATUS:' . (($ev['status'] ?? '') === 'pending' ? 'TENTATIVE' : (($ev['status'] ?? '') === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'));
            $L[] = 'END:VEVENT';
        }
        $L[] = 'END:VCALENDAR';
        return implode("\r\n", array_map([self::class, 'fold'], $L)) . "\r\n";
    }

    public static function googleLink(array $ev): string
    {
        $tz = (string)Settings::get('timezone', 'America/Guatemala');
        return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode($ev['summary'])
            . '&dates=' . self::dt($ev['start']) . '/' . self::dt($ev['end']) . '&ctz=' . rawurlencode($tz)
            . '&details=' . rawurlencode($ev['description'] ?? '') . '&location=' . rawurlencode($ev['location'] ?? '');
    }

    public static function outlookLink(array $ev): string
    {
        $tz = new \DateTimeZone((string)Settings::get('timezone', 'America/Guatemala'));
        $s = (new \DateTime($ev['start'], $tz))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        $e = (new \DateTime($ev['end'], $tz))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        return 'https://outlook.live.com/calendar/0/deeplink/compose?path=%2Fcalendar%2Faction%2Fcompose&rru=addevent&subject='
            . rawurlencode($ev['summary']) . '&startdt=' . rawurlencode($s) . '&enddt=' . rawurlencode($e)
            . '&body=' . rawurlencode($ev['description'] ?? '') . '&location=' . rawurlencode($ev['location'] ?? '');
    }
}
