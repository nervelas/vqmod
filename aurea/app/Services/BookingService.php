<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Db;
use Aurea\Core\Logger;
use Aurea\Core\Settings;
use Aurea\Core\Upload;
use Aurea\Core\Util;

/** Creación, cambio de estado y reprogramación de citas, con prevención de doble reserva. */
final class BookingService
{
    public const STATUSES = [
        'pending' => 'Pendiente', 'confirmed' => 'Confirmada', 'completed' => 'Completada',
        'cancelled' => 'Cancelada', 'no_show' => 'No asistió', 'rescheduled' => 'Reprogramada',
    ];

    public static function history(int $apptId, string $action, string $detail = '', ?int $userId = null, string $actor = ''): void
    {
        if ($actor === '') { $actor = $userId ? (string)(Db::val('SELECT name FROM users WHERE id=?', [$userId]) ?? 'Equipo') : 'Cliente / sistema'; }
        Db::insert('appointment_history', ['appointment_id' => $apptId, 'user_id' => $userId, 'actor' => mb_substr($actor, 0, 40), 'action' => $action,
            'detail' => mb_substr($detail, 0, 500), 'created_at' => date('Y-m-d H:i:s')]);
    }

    /** Encuentra o crea al cliente por teléfono. Devuelve el id o lanza RuntimeException si está bloqueado. */
    public static function findOrCreateClient(array $c, bool $staff, string $ip = ''): int
    {
        $row = Db::one('SELECT * FROM clients WHERE phone_cc=? AND phone=? ORDER BY id LIMIT 1', [$c['cc'], $c['phone']]);
        if ($row) {
            if ((int)$row['blocked'] && !$staff) {
                throw new \RuntimeException('No fue posible completar la reserva en línea. Por favor comunícate con nosotros.');
            }
            $upd = [];
            if ($row['email'] === '' && $c['email'] !== '') { $upd['email'] = $c['email']; }
            if ($row['nit'] === '' && ($c['nit'] ?? '') !== '') { $upd['nit'] = $c['nit']; }
            if (!empty($c['consent']) && !$row['consent_at']) {
                $upd += ['consent_at' => date('Y-m-d H:i:s'), 'consent_version' => (string)Settings::get('privacy_version', '1'), 'consent_ip' => $ip];
            }
            if ($upd) { Db::update('clients', (int)$row['id'], $upd); }
            return (int)$row['id'];
        }
        return Db::insert('clients', [
            'name' => $c['name'], 'phone_cc' => $c['cc'], 'phone' => $c['phone'], 'email' => $c['email'], 'nit' => $c['nit'] ?? '',
            'consent_at' => !empty($c['consent']) ? date('Y-m-d H:i:s') : null,
            'consent_version' => !empty($c['consent']) ? (string)Settings::get('privacy_version', '1') : '',
            'consent_ip' => !empty($c['consent']) ? $ip : '', 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function depositFor(array $svc, float $total): float
    {
        if ($total <= 0 || $svc['deposit_type'] === 'none') { return 0.0; }
        $d = $svc['deposit_type'] === 'percent' ? $total * min(100, (float)$svc['deposit_value']) / 100 : (float)$svc['deposit_value'];
        return round(min($total, max(0, $d)), 2);
    }

    /**
     * Crea una cita.
     * $in: service_id, professional_id ('any'|int), location_id, start 'Y-m-d H:i', client[name,phone,email,nit,consent],
     *      answers[field_id=>valor], files[field_id=>$_FILES], coupon, client_note, home_address
     * $opts: staff(bool), user_id, source, ip, status, client_package_id, ignore_schedule
     * @return array{ok:bool,id?:int,token?:string,error?:string,code?:string,fields?:array}
     */
    public static function create(array $in, array $opts = []): array
    {
        $staff = !empty($opts['staff']);
        $svc = Db::one('SELECT * FROM services WHERE id=? AND active=1', [(int)($in['service_id'] ?? 0)]);
        if (!$svc) { return ['ok' => false, 'code' => 'validation', 'error' => 'Servicio no disponible.']; }

        $errors = [];
        $name = Util::limit((string)($in['client']['name'] ?? ''), 150);
        if (mb_strlen($name) < 2) { $errors['name'] = 'Escribe tu nombre completo.'; }
        $ph = Util::phone((string)($in['client']['phone'] ?? ''), (string)($in['client']['cc'] ?? '502'));
        if (!$ph) { $errors['phone'] = 'Ingresa un teléfono válido (8 dígitos para Guatemala).'; }
        $email = trim((string)($in['client']['email'] ?? ''));
        if ($email !== '' && !Util::isEmail($email)) { $errors['email'] = 'Correo inválido.'; }
        if ($email === '' && Settings::get('email_required', '0') === '1' && !$staff) { $errors['email'] = 'El correo es obligatorio.'; }
        $nit = strtoupper(preg_replace('/[^0-9Kk\-]/', '', (string)($in['client']['nit'] ?? '')) ?? '');
        if (strlen($nit) > 20) { $errors['nit'] = 'NIT inválido.'; }
        if (!$staff && empty($in['client']['consent']) && Settings::get('privacy_required', '1') === '1') { $errors['consent'] = 'Debes aceptar el aviso de privacidad.'; }

        $startRaw = (string)($in['start'] ?? '');
        $ts = preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $startRaw) ? strtotime(str_replace('T', ' ', $startRaw)) : false;
        if ($ts === false) { return ['ok' => false, 'code' => 'validation', 'error' => 'Selecciona fecha y hora.']; }
        $start = date('Y-m-d H:i:s', $ts);

        $locId = !empty($in['location_id']) ? (int)$in['location_id'] : null;
        if ($locId !== null && !Db::val('SELECT id FROM locations WHERE id=? AND active=1', [$locId])) { $locId = null; }
        $home = Util::limit((string)($in['home_address'] ?? ''), 255);
        if ($svc['modality'] === 'domicilio' && mb_strlen($home) < 6) { $errors['home_address'] = 'Indica la dirección del servicio a domicilio.'; }
        if ($svc['modality'] !== 'domicilio') { $home = ''; }

        $fields = FormService::fieldsFor((int)$svc['id']);
        if ($staff || !empty($opts['skip_required'])) { $fields = array_map(static fn($f) => ['required' => 0] + $f, $fields); }
        [$answers, $toStore, $ferrs] = FormService::validate($fields, (array)($in['answers'] ?? []), (array)($in['files'] ?? []));
        foreach ($ferrs as $fid => $m) { $errors['f' . $fid] = $m; }
        if ($errors) { return ['ok' => false, 'code' => 'validation', 'error' => 'Revisa los datos marcados.', 'fields' => $errors]; }

        // Candidatos de profesional
        $pidIn = $in['professional_id'] ?? 'any';
        $profs = AvailabilityService::professionalsFor((int)$svc['id'], $locId, ($pidIn === 'any' || $pidIn === '' || $pidIn === null) ? null : (int)$pidIn);
        if (!$profs) { return ['ok' => false, 'code' => 'validation', 'error' => 'El profesional seleccionado no ofrece este servicio.']; }
        $ids = AvailabilityService::fairOrder(array_map(static fn($p) => (int)$p['id'], $profs), $start);
        $priceOverrides = [];
        foreach ($profs as $p) { $priceOverrides[(int)$p['id']] = $p['price_override']; }

        try {
            $clientId = self::findOrCreateClient(['name' => $name, 'cc' => $ph[0], 'phone' => $ph[1], 'email' => $email, 'nit' => $nit, 'consent' => !empty($in['client']['consent']) || $staff], $staff, (string)($opts['ip'] ?? ''));
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'code' => 'blocked', 'error' => $e->getMessage()];
        }
        if (!$staff) {
            $max = Settings::int('max_active_per_client', 5);
            if ($max > 0 && (int)Db::val("SELECT COUNT(*) FROM appointments WHERE client_id=? AND status IN ('pending','confirmed') AND start_at>?", [$clientId, date('Y-m-d H:i:s')]) >= $max) {
                return ['ok' => false, 'code' => 'limit', 'error' => 'Ya tienes varias citas próximas. Comunícate con nosotros para agendar más.'];
            }
        }

        $vopts = ['ignore_notice' => $staff, 'ignore_schedule' => !empty($opts['ignore_schedule']), 'allow_past' => $staff && !empty($opts['allow_past'])];
        $apptId = 0; $token = '';
        foreach ($ids as $pid) {
          for ($try = 0; $try < 3; $try++) {
            Db::begin();
            try {
                // Bloqueo por profesional: serializa reservas simultáneas sobre la misma agenda.
                // Debe ser la PRIMERA lectura de la transacción: así la lectura de solapamientos que sigue
                // ve todo lo confirmado por quien tenía el bloqueo antes (sin bloqueos de rango que causen interbloqueos).
                Db::q('SELECT id FROM professionals WHERE id=? FOR UPDATE', [$pid]);
                if (!AvailabilityService::validate($svc, $pid, $start, $locId, $vopts)) { Db::rollback(); continue 2; }

                $price = $priceOverrides[$pid] !== null ? (float)$priceOverrides[$pid] : (float)$svc['price'];
                $discount = 0.0; $coupon = null; $cpkg = null;
                if (!empty($opts['client_package_id'])) {
                    $cpkg = Db::one('SELECT * FROM client_packages WHERE id=? AND client_id=? FOR UPDATE', [(int)$opts['client_package_id'], $clientId]);
                    if (!$cpkg || (int)$cpkg['sessions_used'] >= (int)$cpkg['sessions_total'] || ($cpkg['expires_at'] && $cpkg['expires_at'] < date('Y-m-d'))
                        || ($cpkg['service_id'] && (int)$cpkg['service_id'] !== (int)$svc['id'])) {
                        Db::rollback();
                        return ['ok' => false, 'code' => 'package', 'error' => 'El paquete no tiene sesiones disponibles para este servicio.'];
                    }
                    $discount = $price;
                } elseif (!empty($in['coupon'])) {
                    [$coupon, $discount, $cerr] = CouponService::evaluate((string)$in['coupon'], (int)$svc['id'], $price, true);
                    if ($cerr !== '') { Db::rollback(); return ['ok' => false, 'code' => 'coupon', 'error' => $cerr, 'fields' => ['coupon' => $cerr]]; }
                }
                $total = round(max(0, $price - $discount), 2);
                $deposit = self::depositFor($svc, $total);
                $status = $opts['status'] ?? ($staff ? 'confirmed' : ((int)$svc['auto_confirm'] && $deposit <= 0 ? 'confirmed' : 'pending'));
                $now = date('Y-m-d H:i:s');
                $expires = null;
                $holdH = Settings::int('pending_expire_hours', 48);
                if ($status === 'pending' && $holdH > 0 && !$staff) { $expires = date('Y-m-d H:i:s', min(time() + $holdH * 3600, $ts)); }
                $token = Util::token(16);
                $apptId = Db::insert('appointments', [
                    'token' => $token, 'client_id' => $clientId, 'professional_id' => $pid, 'service_id' => (int)$svc['id'], 'location_id' => $locId,
                    'start_at' => $start, 'end_at' => date('Y-m-d H:i:s', $ts + (int)$svc['duration_min'] * 60),
                    'block_start' => date('Y-m-d H:i:s', $ts - (int)$svc['buffer_before'] * 60),
                    'block_end' => date('Y-m-d H:i:s', $ts + ((int)$svc['duration_min'] + (int)$svc['buffer_after']) * 60),
                    'duration_min' => (int)$svc['duration_min'], 'status' => $status, 'source' => (string)($opts['source'] ?? 'web'),
                    'modality' => $svc['modality'], 'meeting_url' => $svc['modality'] === 'virtual' ? (string)$svc['meeting_url'] : '', 'home_address' => $home,
                    'price' => $price, 'discount' => $discount, 'total' => $total, 'deposit_required' => $deposit,
                    'payment_status' => $total <= 0 ? 'paid' : 'unpaid', 'coupon_id' => $coupon['id'] ?? null, 'client_package_id' => $cpkg['id'] ?? null,
                    'client_note' => Util::limit((string)($in['client_note'] ?? ''), 1000), 'internal_note' => Util::limit((string)($in['internal_note'] ?? ''), 2000),
                    'pending_expires_at' => $expires, 'created_by' => $opts['user_id'] ?? null, 'created_at' => $now, 'updated_at' => $now,
                ]);
                if ($coupon) { CouponService::consume($coupon, $discount); }
                if ($cpkg) { Db::exec('UPDATE client_packages SET sessions_used=sessions_used+1 WHERE id=?', [$cpkg['id']]); }
                foreach ($answers as [$fid, $label, $val]) {
                    Db::insert('appointment_answers', ['appointment_id' => $apptId, 'field_id' => $fid, 'label' => $label, 'value' => $val]);
                }
                self::history($apptId, 'created', 'Cita creada (' . ($opts['source'] ?? 'web') . ')', $opts['user_id'] ?? null);
                Db::commit();
                break 2;
            } catch (\Throwable $e) {
                Db::rollback();
                // Interbloqueo/espera de InnoDB (SQLSTATE 40001): se reintenta la misma agenda
                if ($e instanceof \PDOException && ($e->errorInfo[0] ?? '') === '40001' && $try < 2) { usleep(random_int(20000, 80000)); continue; }
                Logger::exception($e);
                return ['ok' => false, 'code' => 'error', 'error' => 'No pudimos completar la reserva. Intenta de nuevo en unos minutos.'];
            }
          }
        }
        if (!$apptId) {
            return ['ok' => false, 'code' => 'slot_taken', 'error' => 'Ese horario acaba de ser reservado por otra persona. Elige otro horario.'];
        }
        // Archivos del formulario (fuera de la transacción)
        foreach ($toStore as $fid => $file) {
            try { Upload::privateDoc($file, 'appointment', $apptId, $opts['user_id'] ?? null); } catch (\RuntimeException $e) { Logger::error('Archivo de formulario: ' . $e->getMessage()); }
        }
        try { NotificationService::onBooked($apptId); } catch (\Throwable $e) { Logger::exception($e); }
        return ['ok' => true, 'id' => $apptId, 'token' => $token];
    }

    /**
     * Cambia el estado con todos los efectos (cupón/paquete, no-show, lista de espera, mensajes).
     * @return array{0:bool,1:string}
     */
    public static function setStatus(int $id, string $to, string $reason = '', ?int $userId = null): array
    {
        if (!isset(self::STATUSES[$to]) || $to === 'rescheduled') { return [false, 'Estado inválido.']; }
        $pre = Db::one('SELECT professional_id FROM appointments WHERE id=?', [$id]);
        if (!$pre) { return [false, 'Cita no encontrada.']; }
        Db::begin();
        try {
            Db::q('SELECT id FROM professionals WHERE id=? FOR UPDATE', [$pre['professional_id']]);
            $a = Db::one('SELECT * FROM appointments WHERE id=? FOR UPDATE', [$id]);
            if (!$a) { Db::rollback(); return [false, 'Cita no encontrada.']; }
            $from = $a['status'];
            if ($from === $to) { Db::rollback(); return [true, '']; }
            if ($from === 'rescheduled') { Db::rollback(); return [false, 'Esta cita fue reprogramada; modifica la nueva cita.']; }
            $wasActive = in_array($from, AvailabilityService::ACTIVE, true);
            $willBeActive = in_array($to, AvailabilityService::ACTIVE, true);
            if (!$wasActive && $willBeActive) {
                // Reactivar: el horario debe seguir libre
                $svc = Db::one('SELECT * FROM services WHERE id=?', [$a['service_id']]);
                if (!AvailabilityService::validate($svc, (int)$a['professional_id'], $a['start_at'], $a['location_id'] ? (int)$a['location_id'] : null, ['ignore_notice' => true, 'ignore_schedule' => true, 'allow_past' => true, 'exclude' => $id])) {
                    Db::rollback();
                    return [false, 'Ese horario ya fue ocupado; no se puede reactivar la cita.'];
                }
                if ($a['coupon_id']) { $c = Db::one('SELECT * FROM coupons WHERE id=?', [$a['coupon_id']]); if ($c) { CouponService::consume($c, (float)$a['discount']); } }
                if ($a['client_package_id']) { Db::exec('UPDATE client_packages SET sessions_used=LEAST(sessions_total,sessions_used+1) WHERE id=?', [$a['client_package_id']]); }
            }
            if ($wasActive && !$willBeActive) {
                if ($a['coupon_id']) { CouponService::restore((int)$a['coupon_id'], (float)$a['discount']); }
                if ($a['client_package_id']) { Db::exec('UPDATE client_packages SET sessions_used=GREATEST(0,sessions_used-1) WHERE id=?', [$a['client_package_id']]); }
            }
            $upd = ['status' => $to, 'updated_at' => date('Y-m-d H:i:s'), 'pending_expires_at' => null];
            if ($to === 'cancelled') { $upd['cancelled_at'] = date('Y-m-d H:i:s'); $upd['cancel_reason'] = Util::limit($reason, 255); }
            Db::update('appointments', $id, $upd);
            if ($to === 'no_show') { Db::exec('UPDATE clients SET noshow_count=noshow_count+1 WHERE id=?', [$a['client_id']]); }
            if ($from === 'no_show') { Db::exec('UPDATE clients SET noshow_count=GREATEST(0,noshow_count-1) WHERE id=?', [$a['client_id']]); }
            self::history($id, 'status', (self::STATUSES[$from] ?? $from) . ' → ' . self::STATUSES[$to] . ($reason !== '' ? ' · ' . $reason : ''), $userId);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            Logger::exception($e);
            return [false, 'No se pudo actualizar la cita.'];
        }
        try {
            if ($to === 'no_show') {
                $limit = Settings::int('noshow_block_after', 0);
                if ($limit > 0) { Db::exec('UPDATE clients SET blocked=1 WHERE id=? AND noshow_count>=?', [$a['client_id'], $limit]); }
            }
            if ($to === 'confirmed') { NotificationService::onConfirmed($id); }
            if ($to === 'cancelled') {
                NotificationService::onCancelled($id);
                WaitlistService::offerFreedSlot($a);
            }
            if ($to === 'completed') { NotificationService::onCompleted($id); }
        } catch (\Throwable $e) { Logger::exception($e); }
        return [true, ''];
    }

    /**
     * Reprograma: la cita original pasa a "reprogramada" y se crea una nueva con los mismos datos.
     * @return array{ok:bool,id?:int,token?:string,error?:string}
     */
    public static function reschedule(int $id, string $newStart, ?int $newProfessionalId = null, array $opts = []): array
    {
        $staff = !empty($opts['staff']);
        $ts = preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $newStart) ? strtotime(str_replace('T', ' ', $newStart)) : false;
        if ($ts === false) { return ['ok' => false, 'error' => 'Fecha u hora inválida.']; }
        $start = date('Y-m-d H:i:s', $ts);
        $oldPid = (int)Db::val('SELECT professional_id FROM appointments WHERE id=?', [$id]);
        if (!$oldPid) { return ['ok' => false, 'error' => 'Cita no encontrada.']; }
        $lockIds = array_unique([$oldPid, $newProfessionalId ?: $oldPid]);
        sort($lockIds);
        Db::begin();
        try {
            // Mismo orden de bloqueo que create(): primero profesionales, luego la cita
            foreach ($lockIds as $lid) { Db::q('SELECT id FROM professionals WHERE id=? FOR UPDATE', [$lid]); }
            $a = Db::one('SELECT * FROM appointments WHERE id=? FOR UPDATE', [$id]);
            if (!$a || !in_array($a['status'], ['pending', 'confirmed'], true)) { Db::rollback(); return ['ok' => false, 'error' => 'Esta cita ya no se puede reprogramar.']; }
            $pid = $newProfessionalId ?: (int)$a['professional_id'];
            $svc = Db::one('SELECT * FROM services WHERE id=?', [$a['service_id']]);
            if (!AvailabilityService::professionalsFor((int)$svc['id'], null, $pid)) { Db::rollback(); return ['ok' => false, 'error' => 'El profesional no ofrece este servicio.']; }
            $locId = $a['location_id'] ? (int)$a['location_id'] : null;
            if (!AvailabilityService::validate($svc, $pid, $start, $locId, ['ignore_notice' => $staff, 'ignore_schedule' => !empty($opts['ignore_schedule']), 'exclude' => $id])) {
                Db::rollback();
                return ['ok' => false, 'error' => 'Ese horario ya no está disponible. Elige otro.'];
            }
            $now = date('Y-m-d H:i:s');
            $token = Util::token(16);
            $newId = Db::insert('appointments', [
                'token' => $token, 'client_id' => $a['client_id'], 'professional_id' => $pid, 'service_id' => $a['service_id'], 'location_id' => $a['location_id'],
                'start_at' => $start, 'end_at' => date('Y-m-d H:i:s', $ts + (int)$svc['duration_min'] * 60),
                'block_start' => date('Y-m-d H:i:s', $ts - (int)$svc['buffer_before'] * 60),
                'block_end' => date('Y-m-d H:i:s', $ts + ((int)$svc['duration_min'] + (int)$svc['buffer_after']) * 60),
                'duration_min' => $svc['duration_min'], 'status' => $a['status'], 'source' => $a['source'], 'modality' => $a['modality'], 'meeting_url' => $a['meeting_url'],
                'home_address' => $a['home_address'], 'price' => $a['price'], 'discount' => $a['discount'], 'total' => $a['total'],
                'deposit_required' => $a['deposit_required'], 'payment_status' => $a['payment_status'], 'coupon_id' => $a['coupon_id'], 'client_package_id' => $a['client_package_id'],
                'client_note' => $a['client_note'], 'internal_note' => $a['internal_note'], 'rescheduled_from' => $id,
                'pending_expires_at' => $a['pending_expires_at'], 'created_by' => $opts['user_id'] ?? $a['created_by'], 'created_at' => $now, 'updated_at' => $now,
            ]);
            Db::exec("UPDATE appointments SET status='rescheduled', coupon_id=NULL, client_package_id=NULL, updated_at=?, pending_expires_at=NULL WHERE id=?", [$now, $id]);
            Db::exec('UPDATE payments SET appointment_id=? WHERE appointment_id=?', [$newId, $id]);
            Db::exec('INSERT INTO appointment_answers (appointment_id,field_id,label,value) SELECT ?,field_id,label,value FROM appointment_answers WHERE appointment_id=?', [$newId, $id]);
            Db::exec("UPDATE files SET owner_id=? WHERE owner_type='appointment' AND owner_id=?", [$newId, $id]);
            self::history($id, 'rescheduled', 'Reprogramada a ' . fdatetime($start), $opts['user_id'] ?? null);
            self::history($newId, 'created', 'Reprogramada desde cita #' . $id . ' (antes ' . fdatetime($a['start_at']) . ')', $opts['user_id'] ?? null);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            Logger::exception($e);
            return ['ok' => false, 'error' => 'No se pudo reprogramar. Intenta de nuevo.'];
        }
        try {
            NotificationService::onRescheduled($id, $newId);
            WaitlistService::offerFreedSlot($a);
        } catch (\Throwable $e) { Logger::exception($e); }
        return ['ok' => true, 'id' => $newId, 'token' => $token];
    }

    /** Recalcula el estado de pago de la cita a partir de los pagos confirmados. */
    public static function refreshPayment(int $id): void
    {
        $a = Db::one('SELECT id,total,deposit_required,status,payment_status,professional_id,service_id FROM appointments WHERE id=?', [$id]);
        if (!$a) { return; }
        $paid = (float)Db::val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE appointment_id=? AND status='confirmed'", [$id]);
        $st = ((float)$a['total'] <= 0 || $paid + 0.001 >= (float)$a['total']) ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
        if ($st !== $a['payment_status']) { Db::update('appointments', $id, ['payment_status' => $st]); }
        // Si exigía anticipo y ya se cubrió, una cita pendiente por anticipo se confirma sola
        if ($a['status'] === 'pending' && (float)$a['deposit_required'] > 0 && $paid + 0.001 >= (float)$a['deposit_required']
            && (int)Db::val('SELECT auto_confirm FROM services WHERE id=?', [$a['service_id']])) {
            self::setStatus($id, 'confirmed', 'Anticipo recibido');
        }
    }
}
