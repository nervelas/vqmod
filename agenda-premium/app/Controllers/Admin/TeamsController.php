<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Validator;

/** Equipos de anfitriones (para asignarlos juntos a un evento). */
final class TeamsController extends A2Controller
{
    public function index(Request $req, array $p): Response
    {
        $teams = Db::all(
            'SELECT t.*, (SELECT COUNT(*) FROM team_hosts th WHERE th.team_id = t.id) AS hosts_count,
                    (SELECT COUNT(*) FROM event_types e WHERE e.team_id = t.id) AS events_count
               FROM teams t ORDER BY t.name'
        );
        foreach ($teams as &$t) {
            $t['members'] = Db::col('SELECT h.name FROM team_hosts th JOIN hosts h ON h.id = th.host_id WHERE th.team_id = ? ORDER BY h.sort_order, h.name', [$t['id']]);
        }
        unset($t);
        return $this->page('admin/teams/index', ['teams' => $teams], '/admin/equipos', 'Equipos');
    }

    public function create(Request $req, array $p): Response
    {
        return $this->form(['id' => null, 'name' => '', 'slug' => '', 'description' => '', 'active' => 1], [], null);
    }

    public function edit(Request $req, array $p): Response
    {
        $t = Db::one('SELECT * FROM teams WHERE id = ?', [(int) $p['id']]);
        if (!$t) {
            $this->abort(404);
        }
        $members = array_map('intval', Db::col('SELECT host_id FROM team_hosts WHERE team_id = ?', [$t['id']]));
        return $this->form($t, $members, null);
    }

    public function store(Request $req, array $p): Response
    {
        return $this->persist($req, null);
    }

    public function update(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        if (!Db::val('SELECT id FROM teams WHERE id = ?', [$id])) {
            $this->abort(404);
        }
        return $this->persist($req, $id);
    }

    public function delete(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $t = Db::one('SELECT id, name FROM teams WHERE id = ?', [$id]);
        if (!$t) {
            $this->abort(404);
        }
        Db::delete('teams', 'id = ?', [$id]);
        Auth::audit('teams.delete', 'team', $id, (string) $t['name']);
        return $this->done($req, 'success', 'Eliminamos el equipo «' . $t['name'] . '». Los anfitriones y eventos siguen igual.', '/admin/equipos');
    }

    private function persist(Request $req, ?int $id): Response
    {
        $name = $req->str('name', 120);
        $slug = strtolower($req->str('slug', 80));
        $members = self::idList($req->post['hosts'] ?? []);
        if ($members) {
            $members = array_map('intval', Db::col('SELECT id FROM hosts WHERE id IN (' . implode(',', array_fill(0, count($members), '?')) . ')', $members));
        }
        $row = [
            'name' => $name,
            'description' => trim((string) ($req->post['description'] ?? '')) === '' ? null : mb_substr((string) $req->post['description'], 0, 2000),
            'active' => $req->bool('active') ? 1 : 0,
        ];
        $error = null;
        if ($name === '') {
            $error = 'Escribe el nombre del equipo.';
        } elseif ($slug === '') {
            $slug = Str::uniqueSlug('teams', $name, $id);
        } elseif (!Validator::slug($slug)) {
            $error = 'El enlace solo puede llevar letras minúsculas, números y guiones.';
        } elseif ((int) Db::val('SELECT COUNT(*) FROM teams WHERE slug = ?' . ($id ? ' AND id <> ' . (int) $id : ''), [$slug]) > 0) {
            $error = 'Ese enlace ya lo usa otro equipo.';
        }
        $row['slug'] = $slug;
        if ($error === null) {
            try {
                $id = (int) Db::tx(function () use ($row, $id, $members): int {
                    if ($id === null) {
                        $id = Db::insert('teams', $row);
                    } else {
                        Db::update('teams', $row, 'id = ?', [$id]);
                    }
                    Db::delete('team_hosts', 'team_id = ?', [$id]);
                    foreach ($members as $hid) {
                        Db::insert('team_hosts', ['team_id' => $id, 'host_id' => $hid]);
                    }
                    return $id;
                });
                Auth::audit('teams.save', 'team', $id, $name);
                $this->flash('success', 'Guardamos el equipo «' . $name . '».');
                return $this->redirect('/admin/equipos');
            } catch (\Throwable $e) {
                Logger::error('No se pudo guardar el equipo', $e);
                $error = 'No pudimos guardar el equipo. Inténtalo de nuevo.';
            }
        }
        return $this->form(array_merge($row, ['id' => $id]), $members, $error, 422);
    }

    private function form(array $t, array $members, ?string $error, int $status = 200): Response
    {
        return $this->page('admin/teams/form', [
            't' => $t, 'id' => $t['id'] ?? null, 'members' => $members, 'error' => $error,
            'hosts' => Db::all('SELECT id, name, title, color, active FROM hosts ORDER BY sort_order, name'),
        ], '/admin/equipos', ($t['id'] ?? null) ? 'Editar equipo' : 'Nuevo equipo', [], $status);
    }
}
