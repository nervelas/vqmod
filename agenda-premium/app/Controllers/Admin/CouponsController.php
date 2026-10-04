<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tz;
use App\Core\Validator;

/** Cupones de descuento. */
final class CouponsController extends A3Controller
{
    public function index(Request $req, array $p): Response
    {
        $coupons = Db::all('SELECT c.*, e.name AS event_name FROM coupons c LEFT JOIN event_types e ON e.id = c.event_type_id ORDER BY c.active DESC, c.id DESC');
        $edit = $req->int('editar') > 0 ? Db::one('SELECT * FROM coupons WHERE id = ?', [$req->int('editar')]) : null;
        return $this->page('admin/coupons/index', ['coupons' => $coupons, 'events' => $this->events(), 'edit' => $edit, 'now' => Clock::utc()], '/admin/cupones', 'Cupones');
    }

    public function save(Request $req, array $p): Response
    {
        $id = $req->int('id');
        $back = '/admin/cupones' . ($id ? '?editar=' . $id : '');
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_\-]/', '', $req->str('code', 40)) ?? '');
        $type = $req->str('type', 8) === 'fixed' ? 'fixed' : 'percent';
        $value = $this->money($req, 'value');
        $max = $req->str('max_uses', 7);
        $maxUses = $max === '' ? null : Validator::intRange($max, 1, 1000000);
        $eventId = $req->int('event_type_id');
        if ($code === '' || strlen($code) < 3) {
            return $this->fail($req, 'El código debe tener al menos 3 letras o números (sin espacios).', $back);
        }
        if ($value === null || (float) $value <= 0 || ($type === 'percent' && (float) $value > 100)) {
            return $this->fail($req, $type === 'percent' ? 'El porcentaje debe estar entre 1 y 100.' : 'Escribe un monto de descuento mayor que cero.', $back);
        }
        if ($max !== '' && $maxUses === null) {
            return $this->fail($req, 'El límite de usos debe ser un número entero o dejarse vacío.', $back);
        }
        $minRaw = $req->str('min_amount', 20);
        $min = $minRaw === '' ? '0.00' : $this->money($req, 'min_amount');
        if ($min === null) {
            return $this->fail($req, 'La compra mínima debe ser un monto válido o dejarse vacía.', $back);
        }
        $tz = $this->bizTz();
        $from = $req->str('valid_from', 10);
        $to = $req->str('valid_to', 10);
        if (($from !== '' && !Validator::date($from)) || ($to !== '' && !Validator::date($to))) {
            return $this->fail($req, 'Revisa las fechas de vigencia.', $back);
        }
        if ($from !== '' && $to !== '' && $to < $from) {
            return $this->fail($req, 'La fecha final no puede ser anterior a la inicial.', $back);
        }
        if ($eventId > 0 && !Db::val('SELECT id FROM event_types WHERE id = ?', [$eventId])) {
            return $this->fail($req, 'El tipo de evento elegido ya no existe.', $back);
        }
        $dup = Db::val('SELECT id FROM coupons WHERE code = ? AND id <> ?', [$code, $id]);
        if ($dup) {
            return $this->fail($req, 'Ya existe un cupón con ese código.', $back);
        }
        $data = [
            'code' => $code, 'type' => $type, 'value' => $value, 'max_uses' => $maxUses,
            'valid_from' => $from !== '' ? Tz::localToUtc($from . ' 00:00:00', $tz) : null,
            'valid_to' => $to !== '' ? Tz::localToUtc($to . ' 23:59:59', $tz) : null,
            'event_type_id' => $eventId > 0 ? $eventId : null,
            'active' => $req->bool('active') ? 1 : 0,
        ];
        $data['min_amount'] = $min;
        if ($id > 0) {
            if (!Db::val('SELECT id FROM coupons WHERE id = ?', [$id])) {
                throw new HttpException(404);
            }
            Db::update('coupons', $data, 'id = ?', [$id]);
        } else {
            $id = Db::insert('coupons', $data);
        }
        $this->audit('coupon.save', 'coupon', $id, $code);
        $this->flash('success', 'El cupón quedó guardado.');
        return $this->redirect('/admin/cupones');
    }

    public function toggle(Request $req, array $p): Response
    {
        $row = Db::one('SELECT id, active FROM coupons WHERE id = ?', [(int) $p['id']]);
        if (!$row) {
            throw new HttpException(404);
        }
        Db::update('coupons', ['active' => (int) $row['active'] ? 0 : 1], 'id = ?', [$row['id']]);
        $this->flash('success', (int) $row['active'] ? 'El cupón se pausó.' : 'El cupón está activo.');
        return $this->redirect('/admin/cupones');
    }

    public function delete(Request $req, array $p): Response
    {
        $row = Db::one('SELECT id, used FROM coupons WHERE id = ?', [(int) $p['id']]);
        if (!$row) {
            throw new HttpException(404);
        }
        if ((int) $row['used'] > 0 || (int) Db::val('SELECT COUNT(*) FROM bookings WHERE coupon_id = ?', [$row['id']]) > 0) {
            $this->flash('warn', 'Este cupón ya se usó en citas, así que no se puede eliminar. Puedes pausarlo.');
            return $this->redirect('/admin/cupones');
        }
        Db::delete('coupons', 'id = ?', [$row['id']]);
        $this->audit('coupon.delete', 'coupon', $row['id']);
        $this->flash('success', 'El cupón se eliminó.');
        return $this->redirect('/admin/cupones');
    }
}
