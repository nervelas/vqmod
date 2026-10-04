<?php
declare(strict_types=1);

namespace Aurea\Controllers\Admin;

use Aurea\Core\Db;
use Aurea\Core\Response;
use Aurea\Core\Settings;
use Aurea\Core\Util;
use Aurea\Services\AvailabilityService;
use Aurea\Services\NotificationService;
use Aurea\Services\PresetService;
use Aurea\Services\ReportService;

final class DashboardController extends AdminController
{
    public function index(): Response
    {
        $sp = $this->scopePro();
        $w = $sp ? ' AND a.professional_id=' . (int)$sp : '';
        $base = 'SELECT a.*, c.name client_name, c.phone_cc, c.phone client_phone, s.name service_name, p.name prof_name, p.color prof_color FROM appointments a
            JOIN clients c ON c.id=a.client_id JOIN services s ON s.id=a.service_id JOIN professionals p ON p.id=a.professional_id ';
        $today = Db::all($base . "WHERE a.start_at BETWEEN ? AND ? AND a.status IN ('pending','confirmed','completed','no_show')" . $w . ' ORDER BY a.start_at', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')]);
        $pending = Db::all($base . "WHERE a.status='pending' AND a.start_at>=?" . $w . ' ORDER BY a.start_at LIMIT 8', [date('Y-m-d H:i:s')]);
        $from = date('Y-m-01'); $to = date('Y-m-t');
        $kpi = ReportService::summary($from, $to, $sp);
        // Próximos huecos libres por profesional
        $slots = [];
        $svc = Db::one('SELECT * FROM services WHERE active=1 ORDER BY sort,id LIMIT 1');
        if ($svc) {
            foreach (AvailabilityService::professionalsFor((int)$svc['id']) as $p) {
                if ($sp && (int)$p['id'] !== $sp) { continue; }
                $n = AvailabilityService::nextAvailable($svc, [$p], null);
                if ($n) { $slots[] = ['prof' => $p, 'start' => $n['start'], 'svc' => $svc['name']]; }
            }
        }
        $alerts = [];
        if (\Aurea\Core\Auth::role() === "admin") { $alerts = $this->alerts(); }
        $unclosed = (int)Db::val("SELECT COUNT(*) FROM appointments a WHERE a.status='confirmed' AND a.end_at<?" . $w, [date('Y-m-d H:i:s')]);
        if ($unclosed > 0) { $alerts[] = ['warn', $unclosed . ' cita(s) pasadas siguen "confirmadas": márcalas como completadas o no asistió.', '/admin/citas?estado=confirmed&hasta=' . date('Y-m-d')]; }
        return $this->render('dashboard', compact('today', 'pending', 'kpi', 'slots', 'alerts'), 'inicio', 'Inicio');
    }

    private function alerts(): array
    {
        $a = [];
        if (Settings::get('onboarding_done', '0') !== '1') { $a[] = ['info', 'Completa el asistente de inicio en 5 pasos para dejar todo listo.', '/admin/onboarding']; }
        if (is_dir(AUREA_ROOT . '/instalar')) { $a[] = ['err', 'La carpeta /instalar sigue en el servidor. Elimínala por seguridad.', '']; }
        if (Settings::get('smtp_host', '') === '') { $a[] = ['warn', 'El correo saliente usa mail() del hosting. Configura SMTP en Sistema para mejor entrega.', '/admin/sistema']; }
        $last = (int)Settings::get('cron_last_run', '0');
        if ($last === 0 || time() - $last > 3600) { $a[] = ['warn', 'El cron no se ha ejecutado en la última hora. Revisa la configuración en Sistema.', '/admin/sistema']; }
        $failed = (int)Db::val("SELECT COUNT(*) FROM notifications_queue WHERE status='failed'");
        if ($failed > 0) { $a[] = ['err', $failed . ' mensaje(s) no se pudieron enviar.', '/admin/mensajes']; }
        $rc = (int)Db::val("SELECT COUNT(*) FROM payments WHERE status='pending'");
        if ($rc > 0) { $a[] = ['warn', $rc . ' pago(s)/comprobante(s) por revisar.', '/admin/pagos?estado=pending']; }
        if (!Db::val('SELECT id FROM professionals WHERE active=1 LIMIT 1')) { $a[] = ['warn', 'Aún no hay profesionales activos: nadie puede recibir reservas.', '/admin/profesionales/nuevo']; }
        return $a;
    }

    public function onboarding(): Response
    {
        if (\Aurea\Core\Auth::role() !== 'admin') { $this->abort(403); }
        $step = max(1, min(5, $this->req->int('paso', 1)));
        $data = ['step' => $step, 'presets' => PresetService::labels(), 'current' => (string)Settings::get('profession', 'otro'),
            'services' => Db::all('SELECT id,name,duration_min,price FROM services ORDER BY sort,id'), 'sched' => json_decode((string)Settings::get('onb_schedule', ''), true) ?: ['days' => [1, 2, 3, 4, 5], 'am_s' => '08:00', 'am_e' => '12:00', 'pm_s' => '14:00', 'pm_e' => '18:00'],
            'link' => abs_url('/reservar'), 'scripts' => ['vendor/qrcode.js', 'js/qr.js']];
        return $this->render('onboarding', $data, 'inicio', 'Asistente de inicio');
    }

    public function onboardingSave(): Response
    {
        if (\Aurea\Core\Auth::role() !== 'admin') { $this->abort(403); }
        $step = $this->req->int('paso', 1);
        if ($step === 1) {
            $slug = $this->req->str('preset');
            if (!PresetService::apply($slug, true)) { $this->fail('Elige un tipo de profesión.'); return $this->redirect('/admin/onboarding?paso=1'); }
            $this->audit('preset_applied', 'settings', null, $slug);
            $this->ok('Perfil de profesión cargado: servicios, textos y formulario sugeridos.');
        } elseif ($step === 2) {
            $days = array_values(array_filter(array_map('intval', (array)($this->req->post['days'] ?? [])), static fn($d) => $d >= 0 && $d <= 6));
            $t = static fn(string $k, string $d) => Util::isTime($k) ? substr($k, 0, 5) : $d;
            $sched = ['days' => $days, 'am_s' => $t($this->req->str('am_s'), '08:00'), 'am_e' => $t($this->req->str('am_e'), '12:00'), 'pm_s' => $this->req->str('pm_s') !== '' ? $t($this->req->str('pm_s'), '14:00') : '', 'pm_e' => $this->req->str('pm_e') !== '' ? $t($this->req->str('pm_e'), '18:00') : ''];
            Settings::set('onb_schedule', json_encode($sched));
            foreach (Db::all('SELECT id FROM professionals') as $p) { $this->applySchedule((int)$p['id'], $sched); }
            $this->ok('Horario base guardado.');
        } elseif ($step === 3) {
            $name = Util::limit($this->req->str('name'), 150);
            if (mb_strlen($name) < 2) { $this->fail('Escribe el nombre del profesional.'); return $this->redirect('/admin/onboarding?paso=3'); }
            $pid = ProfessionalController::createBasic($name, Util::limit($this->req->str('title'), 150), $this->req->str('email'), $this->req->str('whatsapp'));
            $sched = json_decode((string)Settings::get('onb_schedule', ''), true);
            if ($sched) { $this->applySchedule($pid, $sched); } else { PresetService::defaultSchedule($pid); }
            $this->audit('professional_created', 'professional', $pid);
            $this->ok('Profesional creado y vinculado a todos los servicios.');
        } elseif ($step === 4) {
            foreach ((array)($this->req->post['svc'] ?? []) as $id => $v) {
                if (!is_array($v)) { continue; }
                $price = max(0, (float)str_replace(',', '.', (string)($v['price'] ?? 0)));
                $dur = max(5, min(600, (int)($v['duration'] ?? 30)));
                Db::exec('UPDATE services SET price=?, duration_min=? WHERE id=?', [$price, $dur, (int)$id]);
            }
            $this->ok('Servicios actualizados.');
        } elseif ($step === 5) {
            Settings::set('onboarding_done', '1');
            $this->ok('¡Todo listo! Tu sistema de citas está configurado.');
            return $this->redirect('/admin');
        }
        return $this->redirect('/admin/onboarding?paso=' . min(5, $step + 1));
    }

    private function applySchedule(int $profId, array $s): void
    {
        Db::exec('DELETE FROM schedules WHERE professional_id=?', [$profId]);
        foreach ($s['days'] as $wd) {
            if ($s['am_s'] && $s['am_e'] && $s['am_s'] < $s['am_e']) { Db::insert('schedules', ['professional_id' => $profId, 'location_id' => null, 'weekday' => (int)$wd, 'start_time' => $s['am_s'] . ':00', 'end_time' => $s['am_e'] . ':00']); }
            if (!empty($s['pm_s']) && !empty($s['pm_e']) && $s['pm_s'] < $s['pm_e']) { Db::insert('schedules', ['professional_id' => $profId, 'location_id' => null, 'weekday' => (int)$wd, 'start_time' => $s['pm_s'] . ':00', 'end_time' => $s['pm_e'] . ':00']); }
        }
    }
}
