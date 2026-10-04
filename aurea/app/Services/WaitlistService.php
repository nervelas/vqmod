<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Db;
use Aurea\Core\Settings;
use Aurea\Core\Util;

/** Lista de espera: al liberarse un horario se ofrece automáticamente a la siguiente persona. */
final class WaitlistService
{
    /** Ofrece el horario liberado por $freed (fila de appointments) al primero de la fila. Devuelve true si hubo oferta. */
    public static function offerFreedSlot(array $freed): bool
    {
        if (!Settings::bool('waitlist_enabled', true)) { return false; }
        $start = (string)$freed['start_at'];
        if (strtotime($start) <= time()) { return false; }
        $svc = Db::one('SELECT * FROM services WHERE id=? AND active=1', [$freed['service_id']]);
        if (!$svc) { return false; }
        // ¿El horario realmente quedó libre y es reservable?
        $date = substr($start, 0, 10);
        $ctx = AvailabilityService::context((int)$freed['professional_id'], $date, $date);
        $ok = false;
        foreach (AvailabilityService::slots($svc, $ctx, $date, null) as $s) { if ($s['start'] === $start) { $ok = true; break; } }
        if (!$ok) { return false; }
        $w = Db::one("SELECT * FROM waitlist WHERE status='waiting' AND service_id=? AND (professional_id IS NULL OR professional_id=?) AND date_from<=? AND date_to>=? ORDER BY id LIMIT 1",
            [$freed['service_id'], $freed['professional_id'], $date, $date]);
        if (!$w) { return false; }
        $token = Util::token(16);
        $hours = max(1, Settings::int('waitlist_offer_hours', 2));
        $exp = date('Y-m-d H:i:s', time() + $hours * 3600);
        Db::update('waitlist', (int)$w['id'], ['status' => 'offered', 'offer_token' => $token, 'offer_start' => $start,
            'offer_professional_id' => (int)$freed['professional_id'], 'offer_expires' => $exp]);
        $prof = Db::val('SELECT name FROM professionals WHERE id=?', [$freed['professional_id']]);
        NotificationService::enqueueRaw('waitlist_offer', [
            'nombre' => $w['name'], 'servicio' => $svc['name'], 'fecha' => Util::dateLong($start), 'hora' => date('H:i', strtotime($start)),
            'profesional' => (string)$prof, 'direccion' => (string)Settings::get('business_address', ''), 'enlace' => abs_url('/espera/' . $token),
            'negocio' => (string)Settings::get('business_name', ''), 'telefono' => '', 'precio' => money($svc['price']),
        ], (string)$w['email'], $w['phone_cc'] . $w['phone'], 'wl:' . $w['id'] . ':' . $token);
        return true;
    }

    public static function join(array $d): int
    {
        return Db::insert('waitlist', [
            'name' => $d['name'], 'phone_cc' => $d['cc'], 'phone' => $d['phone'], 'email' => $d['email'], 'service_id' => $d['service_id'],
            'professional_id' => $d['professional_id'] ?: null, 'date_from' => $d['date_from'], 'date_to' => $d['date_to'],
            'note' => Util::limit((string)($d['note'] ?? ''), 255), 'status' => 'waiting', 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Ofertas vencidas -> 'expired' y se ofrece el mismo horario a la siguiente persona. */
    public static function expireOffers(): int
    {
        $n = 0;
        foreach (Db::all("SELECT * FROM waitlist WHERE status='offered' AND offer_expires<?", [date('Y-m-d H:i:s')]) as $w) {
            Db::update('waitlist', (int)$w['id'], ['status' => 'expired']);
            $n++;
            if ($w['offer_start'] && $w['offer_professional_id']) {
                self::offerFreedSlot(['service_id' => $w['service_id'], 'professional_id' => $w['offer_professional_id'], 'start_at' => $w['offer_start']]);
            }
        }
        // Entradas cuyo rango de fechas ya pasó
        Db::exec("UPDATE waitlist SET status='expired' WHERE status='waiting' AND date_to<?", [date('Y-m-d')]);
        return $n;
    }
}
