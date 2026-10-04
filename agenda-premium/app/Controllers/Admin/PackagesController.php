<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;

/** Paquetes de sesiones: catálogo, venta a clientes y saldos. */
final class PackagesController extends A3Controller
{
    public function index(Request $req, array $p): Response
    {
        $packages = Db::all('SELECT p.*, e.name AS event_name, (SELECT COUNT(*) FROM client_packages cp WHERE cp.package_id = p.id) AS sold FROM packages p LEFT JOIN event_types e ON e.id = p.event_type_id ORDER BY p.active DESC, p.name');
        $filter = $req->str('ver', 10);
        $filter = in_array($filter, ['activos', 'vencidos', 'agotados', 'todos'], true) ? $filter : 'activos';
        $now = Clock::utc();
        $where = match ($filter) {
            'activos' => 'cp.remaining > 0 AND (cp.expires_at IS NULL OR cp.expires_at >= ?)',
            'vencidos' => 'cp.expires_at IS NOT NULL AND cp.expires_at < ? AND cp.remaining > 0',
            'agotados' => 'cp.remaining = 0 AND ? IS NOT NULL',
            default => '? IS NOT NULL',
        };
        $sold = Db::all(
            'SELECT cp.*, c.name AS client_name, c.phone AS client_phone, p.name AS package_name, p.sessions
             FROM client_packages cp JOIN clients c ON c.id = cp.client_id JOIN packages p ON p.id = cp.package_id
             WHERE ' . $where . ' ORDER BY cp.purchased_at DESC LIMIT 200',
            [$now]
        );
        $clients = Db::all('SELECT id, name, phone FROM clients WHERE anonymized_at IS NULL ORDER BY name LIMIT 600');
        $edit = null;
        if ($req->int('editar') > 0) {
            $edit = Db::one('SELECT * FROM packages WHERE id = ?', [$req->int('editar')]);
        }
        return $this->page('admin/packages/index', [
            'packages' => $packages, 'sold' => $sold, 'filter' => $filter, 'clients' => $clients,
            'events' => $this->events(), 'edit' => $edit, 'now' => $now,
        ], '/admin/paquetes', 'Paquetes de sesiones');
    }

    public function save(Request $req, array $p): Response
    {
        $id = $req->int('id');
        $name = $req->str('name', 160);
        $sessions = \App\Core\Validator::intRange($req->str('sessions', 5), 1, 999);
        $price = $this->money($req, 'price');
        $validity = \App\Core\Validator::intRange($req->str('validity_days', 5), 1, 3650);
        $eventId = $req->int('event_type_id');
        $back = '/admin/paquetes' . ($id ? '?editar=' . $id : '');
        if ($name === '' || $sessions === null || $price === null || $validity === null) {
            return $this->fail($req, 'Revisa el nombre, el número de sesiones, el precio y la vigencia en días.', $back);
        }
        if ($eventId > 0 && !Db::val('SELECT id FROM event_types WHERE id = ?', [$eventId])) {
            return $this->fail($req, 'El tipo de evento elegido ya no existe.', $back);
        }
        $data = [
            'name' => $name, 'description' => $req->str('description', 255) ?: null, 'sessions' => $sessions,
            'price' => $price, 'validity_days' => $validity, 'event_type_id' => $eventId > 0 ? $eventId : null,
            'active' => $req->bool('active') ? 1 : 0,
        ];
        if ($id > 0) {
            if (!Db::val('SELECT id FROM packages WHERE id = ?', [$id])) {
                throw new HttpException(404);
            }
            Db::update('packages', $data, 'id = ?', [$id]);
        } else {
            $id = Db::insert('packages', $data);
        }
        $this->audit('package.save', 'package', $id, $name);
        $this->flash('success', 'El paquete quedó guardado.');
        return $this->redirect('/admin/paquetes');
    }

    public function toggle(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $row = Db::one('SELECT active FROM packages WHERE id = ?', [$id]);
        if (!$row) {
            throw new HttpException(404);
        }
        Db::update('packages', ['active' => (int) $row['active'] ? 0 : 1], 'id = ?', [$id]);
        $this->flash('success', (int) $row['active'] ? 'El paquete se pausó y ya no se puede vender.' : 'El paquete está activo de nuevo.');
        return $this->redirect('/admin/paquetes');
    }

    public function delete(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        if (!Db::val('SELECT id FROM packages WHERE id = ?', [$id])) {
            throw new HttpException(404);
        }
        if ((int) Db::val('SELECT COUNT(*) FROM client_packages WHERE package_id = ?', [$id]) > 0) {
            $this->flash('warn', 'Este paquete ya se vendió, por eso no se puede eliminar. Puedes pausarlo para dejar de ofrecerlo.');
            return $this->redirect('/admin/paquetes');
        }
        Db::delete('packages', 'id = ?', [$id]);
        $this->audit('package.delete', 'package', $id);
        $this->flash('success', 'El paquete se eliminó.');
        return $this->redirect('/admin/paquetes');
    }

    public function sell(Request $req, array $p): Response
    {
        $clientId = $req->int('client_id');
        $packageId = $req->int('package_id');
        $pkg = Db::one('SELECT * FROM packages WHERE id = ? AND active = 1', [$packageId]);
        if (!$pkg || !Db::val('SELECT id FROM clients WHERE id = ?', [$clientId])) {
            return $this->fail($req, 'Elige un cliente y un paquete activo.', '/admin/paquetes');
        }
        $method = $req->str('method', 12);
        $method = in_array($method, ['cash', 'transfer', 'card_onsite', 'link', 'other'], true) ? $method : 'cash';
        if (!$this->svc('PackageService')) {
            return $this->fail($req, 'El servicio de paquetes aún no está disponible.', '/admin/paquetes', 503);
        }
        try {
            $cp = \App\Services\PackageService::sell($clientId, $packageId, $this->userId(), $req->bool('paid'), $method);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Venta de paquete', $e);
            return $this->fail($req, $e instanceof \InvalidArgumentException ? $e->getMessage() : 'No pudimos registrar la venta. Inténtalo de nuevo.', '/admin/paquetes');
        }
        $this->audit('package.sell', 'client_package', $cp, Str::truncate((string) $pkg['name'], 80));
        $this->flash('success', 'Paquete vendido: ya aparece en el saldo del cliente.');
        return $this->redirect('/admin/paquetes');
    }
}
