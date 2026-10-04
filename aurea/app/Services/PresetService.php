<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Db;
use Aurea\Core\Settings;
use Aurea\Core\Util;

/** Perfiles de profesión: terminología, servicios, formulario y plantillas sugeridas (todo editable luego). */
final class PresetService
{
    public static function all(): array
    {
        return require AUREA_ROOT . '/app/Data/presets.php';
    }

    public static function labels(): array
    {
        $o = [];
        foreach (self::all() as $k => $p) { $o[$k] = $p['label']; }
        return $o;
    }

    /** Aplica un perfil. $replace=true elimina servicios sin citas, categorías y formulario global previos. */
    public static function apply(string $slug, bool $replace = false): bool
    {
        $all = self::all();
        if (!isset($all[$slug])) { return false; }
        $p = $all[$slug];
        $kv = ['profession' => $slug, 'hero_headline' => $p['headline'], 'hero_subtitle' => $p['subtitle']];
        foreach ($p['terms'] as $k => $v) { $kv['term_' . $k] = $v; }
        Settings::setMany($kv);
        if ($replace) {
            Db::exec('DELETE FROM form_fields WHERE service_id IS NULL');
            Db::exec('DELETE FROM services WHERE id NOT IN (SELECT DISTINCT service_id FROM appointments)');
            Db::exec('DELETE FROM categories WHERE id NOT IN (SELECT DISTINCT category_id FROM services WHERE category_id IS NOT NULL)');
        }
        $catIds = [];
        foreach ($p['categories'] as $i => $name) {
            $catIds[$i] = Db::insert('categories', ['name' => $name, 'sort' => $i, 'active' => 1]);
        }
        $profs = array_column(Db::all('SELECT id FROM professionals'), 'id');
        foreach ($p['services'] as $i => $s) {
            $sid = Db::insert('services', [
                'category_id' => $catIds[$s['cat']] ?? null, 'name' => $s['name'], 'description' => $s['desc'], 'duration_min' => (int)$s['duration'],
                'buffer_before' => 0, 'buffer_after' => (int)$s['buffer_after'], 'min_notice_hours' => 2, 'max_advance_days' => 60,
                'slot_interval' => ((int)$s['duration'] % 20 === 0 && (int)$s['duration'] % 30 !== 0) ? 20 : 30, 'capacity' => (int)$s['capacity'],
                'price' => (float)$s['price'], 'deposit_type' => $s['deposit_type'], 'deposit_value' => (float)$s['deposit_value'],
                'modality' => $s['modality'], 'auto_confirm' => 1, 'active' => 1, 'sort' => $i, 'created_at' => date('Y-m-d H:i:s'),
            ]);
            foreach ($profs as $pid) { Db::exec('INSERT IGNORE INTO professional_services (professional_id,service_id) VALUES (?,?)', [$pid, $sid]); }
        }
        foreach ($p['form'] as $i => $f) {
            Db::insert('form_fields', ['service_id' => null, 'label' => $f['label'], 'ftype' => $f['type'], 'options' => $f['options'] ?? '', 'required' => (int)$f['required'], 'sort' => $i, 'active' => 1]);
        }
        TemplateService::seed(mb_strtolower($p['terms']['appt']), true);
        return true;
    }

    /** Datos de demostración (profesionales, horarios, clientes y citas de ejemplo). Solo si se solicita. */
    public static function demo(): void
    {
        $now = date('Y-m-d H:i:s');
        $loc = Db::insert('locations', ['name' => 'Consultorio principal', 'address' => '6a Avenida 12-34, Zona 10', 'city' => 'Ciudad de Guatemala', 'phone' => '22345678', 'map_url' => '', 'active' => 1, 'sort' => 0, 'created_at' => $now]);
        $people = [
            ['Dra. Lucía Fernández', 'Especialista senior', 'Más de doce años acompañando a sus pacientes con un trato cercano y diagnósticos claros.', '#B8924A'],
            ['Dr. Andrés Castillo', 'Especialista', 'Enfoque preventivo y atención personalizada, con horarios pensados para quienes tienen poco tiempo.', '#6B8F8A'],
        ];
        $profIds = [];
        foreach ($people as $i => [$name, $title, $bio, $color]) {
            $pid = Db::insert('professionals', ['name' => $name, 'title' => $title, 'slug' => Util::slug($name), 'bio' => $bio, 'specialties' => '', 'photo' => '', 'color' => $color,
                'email' => '', 'phone' => '', 'whatsapp' => '', 'ics_token' => Util::token(16), 'active' => 1, 'sort' => $i, 'created_at' => $now]);
            $profIds[] = $pid;
            Db::exec('INSERT IGNORE INTO professional_locations (professional_id,location_id) VALUES (?,?)', [$pid, $loc]);
            self::defaultSchedule($pid);
            foreach (Db::all('SELECT id FROM services') as $s) { Db::exec('INSERT IGNORE INTO professional_services (professional_id,service_id) VALUES (?,?)', [$pid, $s['id']]); }
        }
        $svc = Db::all('SELECT id FROM services WHERE active=1 ORDER BY sort LIMIT 3');
        $clients = [['María José Estrada', '55500101'], ['Carlos Mendoza', '55500102'], ['Sofía Orellana', '55500103'], ['Luis Pablo Ramírez', '55500104']];
        $cids = [];
        foreach ($clients as [$n, $ph]) { $cids[] = Db::insert('clients', ['name' => $n, 'phone_cc' => '502', 'phone' => $ph, 'email' => '', 'consent_at' => $now, 'created_at' => $now]); }
        if ($svc) {
            $day = 1;
            foreach ($cids as $i => $cid) {
                $date = date('Y-m-d', strtotime('+' . $day . ' weekday'));
                $day++;
                BookingService::create(['service_id' => $svc[$i % count($svc)]['id'], 'professional_id' => $profIds[$i % 2], 'start' => $date . ' ' . sprintf('%02d:00', 9 + $i),
                    'client' => ['name' => $clients[$i][0], 'phone' => $clients[$i][1], 'email' => '', 'consent' => true]], ['staff' => true, 'source' => 'manual', 'ignore_schedule' => true]);
            }
        }
        Settings::set('demo_loaded', '1');
    }

    /** Lunes a viernes 8:00-12:00 y 14:00-18:00; sábado 8:00-12:00. */
    public static function defaultSchedule(int $profId): void
    {
        Db::exec('DELETE FROM schedules WHERE professional_id=?', [$profId]);
        foreach ([1, 2, 3, 4, 5] as $wd) {
            Db::insert('schedules', ['professional_id' => $profId, 'location_id' => null, 'weekday' => $wd, 'start_time' => '08:00:00', 'end_time' => '12:00:00']);
            Db::insert('schedules', ['professional_id' => $profId, 'location_id' => null, 'weekday' => $wd, 'start_time' => '14:00:00', 'end_time' => '18:00:00']);
        }
        Db::insert('schedules', ['professional_id' => $profId, 'location_id' => null, 'weekday' => 6, 'start_time' => '08:00:00', 'end_time' => '12:00:00']);
    }
}
