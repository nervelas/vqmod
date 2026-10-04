<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminNav;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Db;
use App\Core\Fmt;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;

/** Búsqueda global (JSON) para la paleta de comandos y los selectores de cliente. */
final class SearchController extends Controller
{
    private const LIMIT = 6;

    public function search(Request $req, array $p): Response
    {
        $q = $req->str('q', 60);
        $scope = $req->str('scope', 10);
        if ($scope === 'clients') {
            return $this->json(['ok' => true, 'results' => $q === '' ? [] : $this->clients($q, 8)]);
        }
        if ($q === '') {
            return $this->json(['ok' => true, 'results' => []]);
        }
        $out = array_merge($this->pages($q), $this->actions($q));
        if (mb_strlen($q) >= 2) {
            if (Auth::can('clients')) {
                $out = array_merge($out, $this->clients($q, self::LIMIT));
            }
            if (Auth::can('bookings')) {
                $out = array_merge($out, $this->bookings($q));
            }
            if (Auth::can('events')) {
                $out = array_merge($out, $this->events($q));
            }
            if (Auth::can('team')) {
                $out = array_merge($out, $this->hosts($q));
            }
        }
        return $this->json(['ok' => true, 'results' => $out]);
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower($s);
        return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    private function pages(string $q): array
    {
        $n = $this->norm($q);
        $out = [];
        foreach (AdminNav::visible() as $sec) {
            foreach ($sec['items'] as $it) {
                if (str_contains($this->norm($it['label'] . ' ' . ($it['keywords'] ?? '')), $n)) {
                    $out[] = ['group' => 'Páginas', 'type' => 'page', 'title' => $it['label'], 'sub' => $sec['title'], 'icon' => $it['icon'], 'url' => url($it['path'])];
                }
            }
        }
        return array_slice($out, 0, 6);
    }

    private function actions(string $q): array
    {
        $n = $this->norm($q);
        $all = [];
        if (Auth::can('bookings')) {
            $all[] = ['Nueva cita', 'plus', url('/admin/citas/nueva'), 'agendar crear cita manual'];
        }
        if (Auth::can('clients') && Auth::scopedHostId() === null) {
            $all[] = ['Nuevo cliente', 'user', url('/admin/clientes/nuevo'), 'agregar crear cliente ficha'];
        }
        if (Auth::can('events')) {
            $all[] = ['Nuevo evento', 'layers', url('/admin/eventos/nuevo'), 'crear tipo de evento servicio'];
        }
        $out = [];
        foreach ($all as [$t, $ic, $u, $kw]) {
            if (str_contains($this->norm($t . ' ' . $kw), $n)) {
                $out[] = ['group' => 'Acciones', 'type' => 'action', 'title' => $t, 'sub' => 'Acción rápida', 'icon' => $ic, 'url' => $u];
            }
        }
        if (str_contains($this->norm('Cambiar tema claro oscuro modo'), $n)) {
            $out[] = ['group' => 'Acciones', 'type' => 'theme', 'title' => 'Cambiar tema', 'sub' => 'Claro u oscuro', 'icon' => 'moon', 'url' => ''];
        }
        return $out;
    }

    private function clients(string $q, int $limit): array
    {
        [$sql, $params] = A1Support::clientScope('c');
        $like = A1Support::like($q);
        $sql .= " AND c.anonymized_at IS NULL AND (c.name LIKE ? ESCAPE '|' OR c.email LIKE ? ESCAPE '|' OR c.phone LIKE ? ESCAPE '|'";
        array_push($params, $like, $like, $like);
        $digits = preg_replace('/\D+/', '', $q) ?? '';
        if (strlen($digits) >= 3) {
            $sql .= " OR c.phone LIKE ? ESCAPE '|'";
            $params[] = A1Support::like($digits);
        }
        $rows = Db::all('SELECT c.id, c.name, c.email, c.phone, c.nit FROM clients c WHERE ' . $sql . ') ORDER BY c.name LIMIT ' . $limit, $params);
        return array_map(static fn (array $c): array => [
            'group' => 'Clientes', 'type' => 'client', 'id' => (int) $c['id'], 'title' => $c['name'],
            'sub' => trim(($c['email'] ?? '') . ' ' . ($c['phone'] ? Str::phoneDisplay($c['phone']) : '')), 'icon' => 'user', 'url' => url('/admin/clientes/' . $c['id']),
            'email' => (string) $c['email'], 'phone' => (string) $c['phone'], 'nit' => (string) $c['nit'],
        ], $rows);
    }

    private function bookings(string $q): array
    {
        [$sql, $params] = A1Support::bookingScope('b');
        $like = A1Support::like($q);
        $sql .= " AND (b.guest_name LIKE ? ESCAPE '|' OR b.guest_email LIKE ? ESCAPE '|' OR b.guest_phone LIKE ? ESCAPE '|'";
        array_push($params, $like, $like, $like);
        if (preg_match('/^#?(\d{1,9})$/', $q, $m)) {
            $sql .= ' OR b.id = ?';
            $params[] = (int) $m[1];
        }
        $rows = Db::all('SELECT b.id, b.guest_name, b.starts_at, b.status, e.name AS event_name FROM bookings b JOIN event_types e ON e.id = b.event_type_id WHERE ' . $sql . ') ORDER BY b.starts_at DESC LIMIT ' . self::LIMIT, $params);
        $tz = Settings::tz();
        return array_map(static fn (array $b): array => [
            'group' => 'Citas', 'type' => 'booking', 'id' => (int) $b['id'], 'title' => $b['guest_name'] . ' · #' . $b['id'],
            'sub' => $b['event_name'] . ' · ' . Fmt::dateShort($b['starts_at'], $tz) . ' ' . Fmt::time($b['starts_at'], $tz) . ' · ' . A1Support::statusLabel((string) $b['status']),
            'icon' => 'calendar', 'url' => url('/admin/citas/' . $b['id']),
        ], $rows);
    }

    private function events(string $q): array
    {
        $rows = Db::all("SELECT id, name, kind FROM event_types WHERE name LIKE ? ESCAPE '|' ORDER BY name LIMIT " . self::LIMIT, [A1Support::like($q)]);
        return array_map(static fn (array $e): array => ['group' => 'Eventos', 'type' => 'event', 'id' => (int) $e['id'], 'title' => $e['name'], 'sub' => 'Tipo de evento', 'icon' => 'layers', 'url' => url('/admin/eventos/' . $e['id'])], $rows);
    }

    private function hosts(string $q): array
    {
        $rows = Db::all("SELECT id, name, title FROM hosts WHERE name LIKE ? ESCAPE '|' ORDER BY name LIMIT " . self::LIMIT, [A1Support::like($q)]);
        return array_map(static fn (array $h): array => ['group' => 'Anfitriones', 'type' => 'host', 'id' => (int) $h['id'], 'title' => $h['name'], 'sub' => (string) $h['title'], 'icon' => 'user', 'url' => url('/admin/anfitriones/' . $h['id'])], $rows);
    }
}
