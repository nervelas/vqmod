<?php
declare(strict_types=1);

namespace Aurea\Controllers\Admin;

use Aurea\Core\Auth;
use Aurea\Core\Db;
use Aurea\Core\Response;
use Aurea\Core\Upload;
use Aurea\Core\Util;

final class ProfessionalController extends AdminController
{
    public static function uniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = Util::slug($name);
        $slug = $base; $i = 2;
        while (Db::val('SELECT id FROM professionals WHERE slug=?' . ($exceptId ? ' AND id<>' . (int)$exceptId : ''), [$slug])) { $slug = $base . '-' . $i++; }
        return $slug;
    }

    /** Alta mínima (asistente y pruebas): vincula a todos los servicios activos. */
    public static function createBasic(string $name, string $title = '', string $email = '', string $whatsapp = ''): int
    {
        $colors = ['#B8924A', '#6B8F8A', '#8C5A6B', '#5C6F9A', '#9A7B4F', '#6A8F5C'];
        $n = (int)Db::val('SELECT COUNT(*) FROM professionals');
        $id = Db::insert('professionals', ['name' => $name, 'title' => $title, 'slug' => self::uniqueSlug($name), 'bio' => '', 'specialties' => '', 'photo' => '',
            'color' => $colors[$n % count($colors)], 'email' => Util::isEmail($email) ? $email : '', 'phone' => '', 'whatsapp' => Util::limit($whatsapp, 30), 'ics_token' => Util::token(16),
            'active' => 1, 'sort' => $n, 'created_at' => date('Y-m-d H:i:s')]);
        foreach (Db::all('SELECT id FROM services') as $s) { Db::exec('INSERT IGNORE INTO professional_services (professional_id,service_id) VALUES (?,?)', [$id, $s['id']]); }
        foreach (Db::all('SELECT id FROM locations') as $l) { Db::exec('INSERT IGNORE INTO professional_locations (professional_id,location_id) VALUES (?,?)', [$id, $l['id']]); }
        return $id;
    }

    public function index(): Response
    {
        $this->need('schedule_own');
        if ($this->scopePro()) { return $this->redirect('/admin/profesionales/' . $this->scopePro()); }
        $rows = Db::all('SELECT p.*, (SELECT COUNT(*) FROM appointments a WHERE a.professional_id=p.id AND a.status IN (\'pending\',\'confirmed\') AND a.start_at>=?) upcoming FROM professionals p ORDER BY p.sort,p.name', [date('Y-m-d H:i:s')]);
        return $this->render('professionals', compact('rows'), 'profesionales', term('professionals'));
    }

    public function form(): Response
    {
        $this->need('schedule_own');
        $id = $this->id();
        $own = $this->scopePro();
        if ($own !== null && $id !== $own) { $this->abort(404); }
        $p = $id ? Db::one('SELECT * FROM professionals WHERE id=?', [$id]) : ['id' => 0, 'name' => '', 'title' => '', 'bio' => '', 'specialties' => '', 'photo' => '', 'color' => '#B8924A', 'email' => '', 'phone' => '', 'whatsapp' => '', 'active' => 1, 'sort' => 0, 'slug' => '', 'ics_token' => ''];
        if (!$p) { $this->abort(404); }
        if (!$id && Auth::role() !== 'admin') { $this->abort(403); }
        $services = Db::all('SELECT s.id, s.name, s.price, ps.professional_id linked, ps.price_override FROM services s LEFT JOIN professional_services ps ON ps.service_id=s.id AND ps.professional_id=? ORDER BY s.sort,s.name', [$id]);
        $locs = Db::all('SELECT l.id, l.name, pl.professional_id linked FROM locations l LEFT JOIN professional_locations pl ON pl.location_id=l.id AND pl.professional_id=? ORDER BY l.sort,l.name', [$id]);
        $sched = [];
        foreach (Db::all('SELECT * FROM schedules WHERE professional_id=? ORDER BY weekday,start_time', [$id]) as $r) { $sched[(int)$r['weekday']][] = $r; }
        return $this->render('professional_form', ['p' => $p, 'services' => $services, 'locs' => $locs, 'sched' => $sched, 'full' => Auth::role() === 'admin',
            'allLocs' => Db::all('SELECT id,name FROM locations WHERE active=1 ORDER BY name')], 'profesionales', $id ? $p['name'] : __('Nuevo %s', mb_strtolower(term('professional'))));
    }

    public function save(): Response
    {
        $this->need('schedule_own');
        $id = $this->req->int('id');
        $own = $this->scopePro();
        $full = Auth::role() === 'admin';
        if ($own !== null && $id !== $own) { $this->abort(404); }
        if (!$id && !$full) { $this->abort(403); }
        $name = Util::limit($this->req->str('name'), 150);
        $email = $this->req->str('email');
        if (mb_strlen($name) < 2) { $this->fail('Escribe el nombre.'); return $this->redirect($id ? '/admin/profesionales/' . $id : '/admin/profesionales/nuevo'); }
        if ($email !== '' && !Util::isEmail($email)) { $this->fail('El correo no es válido.'); return $this->redirect($id ? '/admin/profesionales/' . $id : '/admin/profesionales/nuevo'); }
        $color = Util::isColor($this->req->str('color')) ? $this->req->str('color') : '#B8924A';
        $d = ['title' => Util::limit($this->req->str('title'), 150), 'bio' => Util::limit($this->req->str('bio'), 3000), 'specialties' => Util::limit($this->req->str('specialties'), 500),
            'email' => $email, 'phone' => Util::limit($this->req->str('phone'), 30), 'whatsapp' => Util::limit($this->req->str('whatsapp'), 30)];
        if ($full) { $d += ['name' => $name, 'color' => $color, 'active' => $this->req->int('active') ? 1 : 0, 'sort' => $this->req->int('sort')]; }
        try {
            if (!empty($_FILES['photo']['name'])) {
                $old = $id ? (string)Db::val('SELECT photo FROM professionals WHERE id=?', [$id]) : '';
                $d['photo'] = Upload::publicImage($_FILES['photo'], 'prof', 900);
                if ($old !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $old)) { @unlink(AUREA_ROOT . '/uploads/' . $old); }
            }
        } catch (\RuntimeException $e) { $this->fail($e->getMessage()); }
        if ($id) {
            Db::update('professionals', $id, $d);
            $this->audit('professional_updated', 'professional', $id);
        } else {
            $d += ['name' => $name, 'slug' => self::uniqueSlug($name), 'ics_token' => Util::token(16), 'created_at' => date('Y-m-d H:i:s'), 'photo' => $d['photo'] ?? ''];
            $id = Db::insert('professionals', $d);
            \Aurea\Services\PresetService::defaultSchedule($id);
            $this->audit('professional_created', 'professional', $id);
        }
        if ($full) {
            Db::exec('DELETE FROM professional_services WHERE professional_id=?', [$id]);
            $ov = (array)($this->req->post['price_ov'] ?? []);
            foreach ((array)($this->req->post['svc'] ?? []) as $sid => $v) {
                $sid = (int)$sid;
                if (!Db::val('SELECT id FROM services WHERE id=?', [$sid])) { continue; }
                $o = isset($ov[$sid]) && $ov[$sid] !== '' ? max(0, (float)str_replace(',', '.', (string)$ov[$sid])) : null;
                Db::exec('INSERT INTO professional_services (professional_id,service_id,price_override) VALUES (?,?,?)', [$id, $sid, $o]);
            }
            Db::exec('DELETE FROM professional_locations WHERE professional_id=?', [$id]);
            foreach ((array)($this->req->post['loc'] ?? []) as $lid => $v) {
                if (Db::val('SELECT id FROM locations WHERE id=?', [(int)$lid])) { Db::exec('INSERT INTO professional_locations (professional_id,location_id) VALUES (?,?)', [$id, (int)$lid]); }
            }
        }
        $this->ok('Guardado.');
        return $this->redirect('/admin/profesionales/' . $id);
    }

    public function saveSchedule(): Response
    {
        $this->need('schedule_own');
        $id = $this->id();
        $own = $this->scopePro();
        if ($own !== null && $id !== $own) { $this->abort(404); }
        if (!Db::val('SELECT id FROM professionals WHERE id=?', [$id])) { $this->abort(404); }
        $rows = []; $errors = [];
        foreach ((array)($this->req->post['sch'] ?? []) as $wd => $blocks) {
            $wd = (int)$wd;
            if ($wd < 0 || $wd > 6 || !is_array($blocks)) { continue; }
            $day = [];
            foreach (array_slice($blocks, 0, 8) as $b) {
                $s = (string)($b['start'] ?? ''); $e = (string)($b['end'] ?? '');
                if ($s === '' && $e === '') { continue; }
                if (!Util::isTime($s) || !Util::isTime($e) || Util::minutes($s) >= Util::minutes($e)) { $errors[] = 'Hay un bloque con horas inválidas (la hora de fin debe ser posterior a la de inicio).'; continue; }
                $loc = !empty($b['loc']) ? (int)$b['loc'] : null;
                if ($loc !== null && !Db::val('SELECT id FROM locations WHERE id=?', [$loc])) { $loc = null; }
                $day[] = [Util::minutes($s), Util::minutes($e), $loc];
            }
            usort($day, static fn($a, $b) => $a[0] <=> $b[0]);
            for ($i = 1; $i < count($day); $i++) { if ($day[$i][0] < $day[$i - 1][1]) { $errors[] = 'Hay bloques que se traslapan el mismo día.'; break; } }
            foreach ($day as [$s, $e, $loc]) { $rows[] = [$wd, sprintf('%02d:%02d:00', intdiv($s, 60), $s % 60), sprintf('%02d:%02d:00', intdiv($e, 60), $e % 60), $loc]; }
        }
        if ($errors) { $this->fail(array_values(array_unique($errors))[0]); return $this->redirect('/admin/profesionales/' . $id . '#horario'); }
        Db::begin();
        Db::exec('DELETE FROM schedules WHERE professional_id=?', [$id]);
        foreach ($rows as [$wd, $s, $e, $loc]) { Db::insert('schedules', ['professional_id' => $id, 'location_id' => $loc, 'weekday' => $wd, 'start_time' => $s, 'end_time' => $e]); }
        Db::commit();
        $this->audit('schedule_updated', 'professional', $id);
        $this->ok('Horario semanal guardado.');
        return $this->redirect('/admin/profesionales/' . $id . '#horario');
    }

    public function delete(): Response
    {
        $this->need('admin');
        $id = $this->id();
        try {
            $photo = (string)Db::val('SELECT photo FROM professionals WHERE id=?', [$id]);
            Db::delete('professionals', $id);
            if ($photo !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $photo)) { @unlink(AUREA_ROOT . '/uploads/' . $photo); }
            $this->audit('professional_deleted', 'professional', $id);
            $this->ok('Eliminado.');
        } catch (\PDOException $e) {
            $this->fail('No se puede eliminar porque tiene citas registradas. Desactívalo para ocultarlo de las reservas.');
        }
        return $this->redirect('/admin/profesionales');
    }
}
