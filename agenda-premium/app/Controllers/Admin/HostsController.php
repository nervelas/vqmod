<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Clock;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Upload;
use App\Core\Validator;

/** Anfitriones: ficha pública, zona horaria, horario, foto, vínculo con usuario y feed ICS propio. */
final class HostsController extends A2Controller
{

    public function index(Request $req, array $p): Response
    {
        $hosts = Db::all(
            'SELECT h.*, u.email AS user_email, s.name AS schedule_name,
                    (SELECT COUNT(*) FROM event_hosts eh WHERE eh.host_id = h.id) AS events_count
               FROM hosts h
               LEFT JOIN users u ON u.id = h.user_id
               LEFT JOIN schedules s ON s.id = h.schedule_id
              ORDER BY h.sort_order, h.name'
        );
        foreach ($hosts as &$h) {
            $h['photo'] = Upload::url($h['photo_file_id'] ? (int) $h['photo_file_id'] : null);
        }
        unset($h);
        return $this->page('admin/hosts/index', ['hosts' => $hosts], '/admin/anfitriones', 'Anfitriones');
    }

    public function order(Request $req, array $p): Response
    {
        $ids = self::idList($req->post['ids'] ?? ($req->json()['ids'] ?? []));
        if (!$ids) {
            return $this->json(['ok' => false, 'error' => 'No recibimos el nuevo orden.'], 422);
        }
        Db::tx(static function () use ($ids): void {
            foreach ($ids as $i => $id) {
                Db::update('hosts', ['sort_order' => ($i + 1) * 10], 'id = ?', [$id]);
            }
        });
        return $this->json(['ok' => true]);
    }

    public function create(Request $req, array $p): Response
    {
        $h = [
            'id' => null, 'name' => '', 'slug' => '', 'title' => '', 'bio' => '', 'timezone' => \App\Core\Settings::tz(), 'color' => '#C9A050',
            'email' => '', 'phone' => '', 'whatsapp' => '', 'schedule_id' => null, 'public_profile' => 1, 'active' => 1,
            'user_id' => null, 'sort_order' => 0, 'photo_file_id' => null, 'ics_token' => '',
        ];
        return $this->form($h, null);
    }

    public function edit(Request $req, array $p): Response
    {
        $h = Db::one('SELECT * FROM hosts WHERE id = ?', [(int) $p['id']]);
        if (!$h) {
            $this->abort(404);
        }
        return $this->form($h, null);
    }

    public function store(Request $req, array $p): Response
    {
        return $this->persist($req, null);
    }

    public function update(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        if (!Db::val('SELECT id FROM hosts WHERE id = ?', [$id])) {
            $this->abort(404);
        }
        return $this->persist($req, $id);
    }

    public function regenerateToken(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        if (!Db::val('SELECT id FROM hosts WHERE id = ?', [$id])) {
            $this->abort(404);
        }
        Db::update('hosts', ['ics_token' => Str::token(16)], 'id = ?', [$id]);
        Auth::audit('hosts.ics_token', 'host', $id, 'Enlace ICS regenerado');
        $this->flash('success', 'Generamos un enlace nuevo. El anterior ya no funciona: actualiza la suscripción en tu calendario.');
        return $this->redirect('/admin/anfitriones/' . $id . '/editar');
    }

    private function persist(Request $req, ?int $id): Response
    {
        $old = $id ? Db::one('SELECT * FROM hosts WHERE id = ?', [$id]) : null;
        $name = $req->str('name', 120);
        $color = $req->str('color', 7);
        $row = [
            'name' => $name,
            'title' => $req->str('title', 160) ?: null,
            'bio' => trim((string) ($req->post['bio'] ?? '')) === '' ? null : mb_substr((string) $req->post['bio'], 0, 3000),
            'timezone' => $req->str('timezone', 64),
            'color' => Validator::color($color) ? strtoupper($color) : '#C9A050',
            'email' => strtolower($req->str('email', 190)) ?: null,
            'phone' => $req->str('phone', 30) ?: null,
            'whatsapp' => $req->str('whatsapp', 30) ?: null,
            'schedule_id' => self::intOrNull($req, 'schedule_id'),
            'public_profile' => $req->bool('public_profile') ? 1 : 0,
            'active' => $req->bool('active') ? 1 : 0,
            'user_id' => self::intOrNull($req, 'user_id'),
            'sort_order' => self::intIn($req, 'sort_order', (int) ($old['sort_order'] ?? 0), 0, 100000),
        ];
        $slug = strtolower($req->str('slug', 80));
        $error = null;

        if ($name === '') {
            $error = 'Escribe el nombre del anfitrión.';
        } elseif (!Tz::valid($row['timezone'])) {
            $error = 'Elige una zona horaria válida.';
        } elseif ($row['email'] !== null && !Validator::email($row['email'])) {
            $error = 'El correo no tiene un formato válido.';
        }
        foreach (['phone' => 'teléfono', 'whatsapp' => 'WhatsApp'] as $k => $label) {
            if ($error === null && $row[$k] !== null) {
                $norm = Str::phone($row[$k]);
                if ($norm === null) {
                    $error = 'El número de ' . $label . ' no es válido. Escribe 8 dígitos de Guatemala o el número con código de país (+502…).';
                } else {
                    $row[$k] = $norm;
                }
            }
        }
        if ($error === null) {
            if ($slug === '') {
                $slug = Str::uniqueSlug('hosts', $name, $id);
            } elseif (!Validator::slug($slug)) {
                $error = 'El enlace solo puede llevar letras minúsculas, números y guiones.';
            } elseif ((int) Db::val('SELECT COUNT(*) FROM hosts WHERE slug = ?' . ($id ? ' AND id <> ' . (int) $id : ''), [$slug]) > 0) {
                $error = 'Ese enlace ya lo usa otro anfitrión.';
            }
        }
        if ($error === null && $row['schedule_id'] !== null && !Db::val('SELECT id FROM schedules WHERE id = ?', [$row['schedule_id']])) {
            $error = 'El horario elegido no existe.';
        }
        if ($error === null && $row['user_id'] !== null) {
            if (!Db::val('SELECT id FROM users WHERE id = ?', [$row['user_id']])) {
                $error = 'El usuario elegido no existe.';
            } elseif (Db::val('SELECT id FROM hosts WHERE user_id = ?' . ($id ? ' AND id <> ' . (int) $id : ''), [$row['user_id']])) {
                $error = 'Ese usuario ya está vinculado a otro anfitrión.';
            }
        }
        $row['slug'] = $slug;

        $photo = null;
        $file = $req->files['photo'] ?? null;
        if ($error === null && is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $photo = Upload::store($file, [
                    'images_only' => true, 'is_public' => 1, 'kind' => 'host', 'owner_type' => 'host',
                    'owner_id' => $id, 'uploaded_by' => Auth::user()['id'] ?? null, 'max_bytes' => 4 * 1024 * 1024,
                ]);
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
            } catch (\Throwable $e) {
                Logger::error('Falló la subida de foto del anfitrión', $e);
                $error = 'No pudimos guardar la foto. Inténtalo con otra imagen.';
            }
        }

        if ($error !== null) {
            $view = array_merge($old ?? [], $row, ['id' => $id, 'ics_token' => $old['ics_token'] ?? '', 'photo_file_id' => $old['photo_file_id'] ?? null]);
            return $this->form($view, $error, 422);
        }

        if ($photo !== null) {
            $row['photo_file_id'] = (int) $photo['id'];
        } elseif ($old && $req->bool('remove_photo')) {
            $row['photo_file_id'] = null;
        }
        try {
            if ($id === null) {
                $row['ics_token'] = Str::token(16);
                $row['created_at'] = Clock::utc();
                $id = Db::insert('hosts', $row);
            } else {
                Db::update('hosts', $row, 'id = ?', [$id]);
            }
        } catch (\Throwable $e) {
            Logger::error('No se pudo guardar el anfitrión', $e);
            return $this->form(array_merge($old ?? [], $row, ['id' => $id]), 'No pudimos guardar al anfitrión. Revisa los datos e inténtalo de nuevo.', 422);
        }
        if ($photo !== null) {
            Db::update('files', ['owner_id' => $id], 'id = ?', [(int) $photo['id']]);
        }
        if ($old && !empty($old['photo_file_id']) && (int) ($row['photo_file_id'] ?? 0) !== (int) $old['photo_file_id']) {
            Upload::delete((int) $old['photo_file_id']);
        }
        Cache::bumpAvailability();
        Auth::audit($old ? 'hosts.update' : 'hosts.create', 'host', $id, $name);
        $this->flash('success', $old ? 'Guardamos los cambios de ' . $name . '.' : 'Agregamos a ' . $name . ' al equipo.');
        return $this->redirect('/admin/anfitriones/' . $id . '/editar');
    }

    private function form(array $h, ?string $error, int $status = 200): Response
    {
        $id = $h['id'] ?? null;
        $users = Db::all(
            'SELECT u.id, u.name, u.email, u.role FROM users u
              WHERE u.active = 1 AND (NOT EXISTS (SELECT 1 FROM hosts x WHERE x.user_id = u.id)' . ($id ? ' OR u.id = (SELECT user_id FROM hosts WHERE id = ' . (int) $id . ')' : '') . ')
              ORDER BY u.name'
        );
        $data = [
            'h' => $h,
            'id' => $id,
            'error' => $error,
            'users' => $users,
            'schedules' => Db::all('SELECT id, name, timezone, is_default FROM schedules ORDER BY is_default DESC, name'),
            'photo' => Upload::url(!empty($h['photo_file_id']) ? (int) $h['photo_file_id'] : null),
            'ics_url' => !empty($h['ics_token']) ? abs_url('/ics/' . $h['ics_token'] . '.ics') : '',
        ];
        return $this->page('admin/hosts/form', $data, '/admin/anfitriones', $id ? 'Editar anfitrión' : 'Nuevo anfitrión', [], $status);
    }
}
