<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Xlsx;
use App\Models\Customer;

final class CustomerController extends Controller
{
    public function index(array $params = []): void
    {
        [$u, $c] = $this->panel();
        [$page, $per, $offset] = Request::page(25);
        [$rows, $total] = Customer::search([
            'q'       => Request::str('q'),
            'user_id' => Auth::ownerFilter() ?: Request::int('vendedor'),
            'limit'   => $per,
            'offset'  => $offset,
        ]);
        $this->view('panel/customers', [
            'title'   => 'Clientes',
            'rows'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'pages'   => (int) ceil($total / $per),
            'sellers' => DB::all('SELECT id, name FROM users WHERE role IN ("admin","vendedor") ORDER BY name'),
        ], 'layout/panel');
    }

    public function form(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $id  = (int) ($params['id'] ?? 0);
        $cu  = $id ? Customer::find($id) : null;
        if ($id && !$cu) {
            ErrorHandler::render(404);
        }
        if (Auth::isSeller() && $cu && $cu['assigned_user_id'] && (int) $cu['assigned_user_id'] !== (int) $u['id']) {
            ErrorHandler::render(403);
        }

        if (Request::isPost()) {
            Csrf::verify();
            $name = mb_substr(Request::str('name'), 0, 160);
            if ($name === '') {
                Flash::error('El nombre del cliente es obligatorio.');
                Flash::keep($_POST);
                redirect($id ? '/panel/clientes/' . $id : '/panel/clientes/nuevo');
            }
            $assigned = Request::int('assigned_user_id') ?: null;
            if (Auth::isSeller()) {
                $assigned = (int) $u['id'];
            }
            if ($assigned && !DB::one('SELECT id FROM users WHERE id = ?', [$assigned])) {
                $assigned = null;
            }
            $pl = Request::int('price_list_id') ?: null;
            if ($pl && !DB::one('SELECT id FROM price_lists WHERE id = ?', [$pl])) {
                $pl = null;
            }
            $next = Request::str('next_followup');
            $data = [
                'name'             => $name,
                'legal_name'       => mb_substr(Request::str('legal_name'), 0, 200) ?: null,
                'nit'              => mb_substr(Request::str('nit'), 0, 30) ?: null,
                'email'            => Request::email('email') ?: null,
                'phone'            => mb_substr(Request::str('phone'), 0, 40) ?: null,
                'whatsapp'         => mb_substr(Request::str('whatsapp'), 0, 30) ?: null,
                'address'          => mb_substr(Request::str('address'), 0, 220) ?: null,
                'city'             => mb_substr(Request::str('city'), 0, 90) ?: null,
                'sector'           => mb_substr(Request::str('sector'), 0, 90) ?: null,
                'price_list_id'    => $pl,
                'assigned_user_id' => $assigned,
                'notes'            => mb_substr(Request::str('notes'), 0, 4000) ?: null,
                'next_followup'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', $next) ? $next : null,
                'updated_at'       => nowSql(),
            ];
            if ($id) {
                DB::update('customers', $data, 'id = :id', ['id' => $id]);
                Audit::log('cliente.editar', 'customer', $id, ['nombre' => $name]);
            } else {
                $data['created_at'] = nowSql();
                $id = DB::insert('customers', $data);
                Audit::log('cliente.crear', 'customer', $id, ['nombre' => $name]);
            }
            Flash::ok('Cliente guardado.');
            redirect('/panel/clientes/' . $id);
        }

        $this->view('panel/customer-form', [
            'title'      => $cu ? $cu['name'] : 'Nuevo cliente',
            'cu'         => $cu,
            'contacts'   => $cu ? Customer::contacts((int) $cu['id']) : [],
            'quotes'     => $cu ? Customer::quotes((int) $cu['id']) : [],
            'sellers'    => DB::all('SELECT id, name FROM users WHERE role IN ("admin","vendedor") AND status = "activo" ORDER BY name'),
            'priceLists' => DB::all('SELECT * FROM price_lists ORDER BY name'),
        ], 'layout/panel');
    }

    public function contact(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        $cu = Customer::find((int) $params['id']);
        if (!$cu) {
            ErrorHandler::render(404);
        }
        $name = mb_substr(Request::str('name'), 0, 120);
        if ($name === '') {
            Flash::error('Escriba el nombre del contacto.');
            redirect('/panel/clientes/' . $cu['id']);
        }
        if (Request::bool('is_primary')) {
            DB::run('UPDATE customer_contacts SET is_primary = 0 WHERE customer_id = ?', [(int) $cu['id']]);
        }
        DB::insert('customer_contacts', [
            'customer_id' => (int) $cu['id'],
            'name'        => $name,
            'position'    => mb_substr(Request::str('position'), 0, 90) ?: null,
            'email'       => Request::email('email') ?: null,
            'phone'       => mb_substr(Request::str('phone'), 0, 40) ?: null,
            'is_primary'  => Request::bool('is_primary') ? 1 : 0,
        ]);
        Flash::ok('Contacto agregado.');
        redirect('/panel/clientes/' . $cu['id']);
    }

    public function contactDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        DB::delete('customer_contacts', 'id = :id', ['id' => (int) $params['id']]);
        $this->back('/panel/clientes');
    }

    public function destroy(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        $id = (int) $params['id'];
        DB::run('UPDATE quotes SET customer_id = NULL WHERE customer_id = ?', [$id]);
        DB::delete('customer_contacts', 'customer_id = :cu', ['cu' => $id]);
        DB::delete('customers', 'id = :id', ['id' => $id]);
        Audit::log('cliente.eliminar', 'customer', $id, []);
        Flash::ok('Cliente eliminado.');
        redirect('/panel/clientes');
    }

    public function export(array $params = []): void
    {
        [$u, $c] = $this->panel();
        [$rows] = Customer::search(['limit' => 5000, 'user_id' => Auth::ownerFilter()]);
        $data = [['Nombre', 'Razón social', 'NIT', 'Correo', 'Teléfono', 'Ciudad', 'Sector', 'Lista de precios', 'Vendedor', 'Cotizaciones', 'Monto ganado']];
        foreach ($rows as $r) {
            $data[] = [
                $r['name'], $r['legal_name'], $r['nit'], $r['email'], $r['phone'], $r['city'], $r['sector'],
                $r['price_list_name'], $r['seller_name'], (int) $r['quote_count'], (float) $r['won_total'],
            ];
        }
        (new Xlsx())->addSheet('Clientes', $data, [30, 32, 14, 28, 16, 16, 18, 18, 20, 12, 14])
            ->download('clientes-' . date('Y-m-d') . '.xlsx');
    }
}
