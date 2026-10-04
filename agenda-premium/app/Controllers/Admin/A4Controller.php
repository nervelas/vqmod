<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Tz;

/** Utilidades compartidas por las pantallas de Sistema (ajustes, legal, comunicaciones, respaldo…). */
abstract class A4Controller extends Controller
{
    protected const STYLES = [];

    protected function page(string $tpl, array $data, array $scripts = ['js/admin-settings.js'], string $active = ''): Response
    {
        $data['scripts'] = $data['scripts'] ?? $scripts;
        $data['active'] = $data['active'] ?? $active;
        return $this->view($tpl, $data, 'layouts/admin');
    }

    protected function bizTz(): string
    {
        return Settings::tz();
    }

    /** Registra en auditoría sin guardar nunca valores secretos. */
    protected function audit(string $action, string $detail = '', ?string $entity = 'settings', $id = null): void
    {
        Auth::audit($action, $entity, $id, $detail === '' ? null : $detail);
    }

    /** Fecha local del negocio de un UTC (d/m/Y H:i). */
    public static function local(?string $utc, string $fmt = 'd/m/Y H:i'): string
    {
        if ($utc === null || $utc === '') {
            return '—';
        }
        try {
            return Tz::format($utc, Settings::tz(), $fmt);
        } catch (\Throwable $e) {
            return (string) $utc;
        }
    }

    /** Tamaño legible: 12,4 MB */
    public static function bytes($n): string
    {
        $n = (float) $n;
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($n >= 1024 && $i < count($u) - 1) {
            $n /= 1024;
            $i++;
        }
        return ($i === 0 ? (string) (int) $n : number_format($n, 1, '.', ',')) . ' ' . $u[$i];
    }

    /** Fecha local AAAA-MM-DD -> inicio (00:00) en UTC; null si no es válida. */
    protected function dayStartUtc(string $date, bool $end = false): ?string
    {
        if (!\App\Core\Validator::date($date)) {
            return null;
        }
        $ts = Tz::localToTs($date . ' 00:00:00', $this->bizTz());
        return Tz::fromTs($end ? $ts + 86400 : $ts);
    }

    /** CSV con BOM (Excel) y protección contra inyección de fórmulas. */
    protected function csv(string $filename, array $header, array $rows): Response
    {
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $header, ',', '"', '\\');
        foreach ($rows as $r) {
            $out = [];
            foreach ($r as $cell) {
                $c = (string) $cell;
                if ($c !== '' && strpbrk($c[0], "=+-@\t\r") !== false) {
                    $c = "'" . $c;
                }
                $out[] = $c;
            }
            fputcsv($fh, $out, ',', '"', '\\');
        }
        rewind($fh);
        $body = (string) stream_get_contents($fh);
        fclose($fh);
        return Response::download($body, 'text/csv', $filename);
    }

    protected function stamp(): string
    {
        return Tz::formatTs(Clock::now(), $this->bizTz(), 'Ymd-His');
    }

    /** Devuelve el valor solo si pertenece a la lista permitida. */
    protected function pick(Request $req, string $key, array $allowed, string $default): string
    {
        $v = $req->str($key, 40);
        return in_array($v, $allowed, true) ? $v : $default;
    }

    protected function isHttpsUrl(string $v): bool
    {
        return $v !== '' && \App\Core\Validator::url($v, true) && (string) parse_url($v, PHP_URL_USER) === '';
    }
}
