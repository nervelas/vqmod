<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;

/** Recursos y salas que ocupan las citas. */
final class ResourcesController extends A2Controller
{
    private const SCRIPTS = ['js/admin-schedules.js'];

    public function index(Request $req, array $p): Response
    {
        $rows = Db::all(
            'SELECT r.*, (SELECT COUNT(*) FROM event_resources er WHERE er.resource_id = r.id) AS events_count,
                    (SELECT COUNT(*) FROM bookings b WHERE b.resource_id = r.id) AS bookings_count
               FROM resources r ORDER BY r.active DESC, r.name'
        );
        return $this->page('admin/resources/index', ['rows' => $rows], '/admin/recursos', 'Recursos y salas', self::SCRIPTS);
    }

    public function save(Request $req, array $p): Response
    {
        $id = self::intOrNull($req, 'id');
        $name = $req->str('name', 120);
        if ($name === '') {
            return $this->done($req, 'error', 'Escribe el nombre de la sala o recurso.', '/admin/recursos');
        }
        $row = [
            'name' => $name,
            'description' => $req->str('description', 255) ?: null,
            'capacity' => self::intIn($req, 'capacity', 1, 1, 1000),
            'active' => $req->bool('active') ? 1 : 0,
        ];
        if ($id) {
            if (!Db::val('SELECT id FROM resources WHERE id = ?', [$id])) {
                $this->abort(404);
            }
            Db::update('resources', $row, 'id = ?', [$id]);
        } else {
            $id = Db::insert('resources', $row);
        }
        Cache::bumpAvailability();
        Auth::audit('resources.save', 'resource', $id, $name);
        return $this->done($req, 'success', 'Guardamos «' . $name . '».', '/admin/recursos');
    }

    public function delete(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $r = Db::one('SELECT id, name FROM resources WHERE id = ?', [$id]);
        if (!$r) {
            $this->abort(404);
        }
        if ((int) Db::val('SELECT COUNT(*) FROM bookings WHERE resource_id = ?', [$id]) > 0) {
            return $this->done($req, 'error', 'Esta sala ya tiene citas registradas, así que no se puede eliminar. Márcala como inactiva.', '/admin/recursos');
        }
        Db::delete('resources', 'id = ?', [$id]);
        Cache::bumpAvailability();
        Auth::audit('resources.delete', 'resource', $id, (string) $r['name']);
        return $this->done($req, 'success', 'Eliminamos «' . $r['name'] . '».', '/admin/recursos');
    }
}
