<?php
declare(strict_types=1);

namespace App\Controllers\Super;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Flash;
use App\Core\Img;
use App\Core\Request;
use App\Core\Security;
use App\Core\Uploader;
use App\Models\Company;
use App\Models\Plan;

final class CompanyAdminController extends Controller
{
    public function index(array $params = []): void
    {
        $this->super();
        $rows = DB::all(
            'SELECT c.*, p.name AS plan_name,
                    (SELECT COUNT(*) FROM products pr WHERE pr.company_id = c.id) AS n_products,
                    (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id) AS n_users,
                    (SELECT COUNT(*) FROM quotes q WHERE q.company_id = c.id) AS n_quotes
             FROM companies c LEFT JOIN plans p ON p.id = c.plan_id ORDER BY c.name'
        );
        $this->view('super/companies', ['title' => 'Empresas', 'rows' => $rows], 'layout/super');
    }

    public function form(array $params = []): void
    {
        $su = $this->super();
        $id = (int) ($params['id'] ?? 0);
        $c  = $id ? Company::find($id) : null;
        if ($id && !$c) {
            ErrorHandler::render(404);
        }

        if (Request::isPost()) {
            Csrf::verify();
            $name = mb_substr(Request::str('name'), 0, 140);
            if ($name === '') {
                Flash::error('Escriba el nombre de la empresa.');
                Flash::keep($_POST);
                redirect($id ? '/super/empresas/' . $id : '/super/empresas/nueva');
            }
            $theme = Request::str('theme');
            if (!isset(Company::THEMES[$theme])) {
                $theme = 'acero';
            }
            $base = Company::THEMES[$theme];
            $status = Request::str('status');
            if (!in_array($status, ['activa', 'prueba', 'suspendida', 'cancelada'], true)) {
                $status = 'prueba';
            }
            $domain = mb_strtolower(preg_replace('/[^a-z0-9\.\-]/i', '', Request::str('domain')) ?: '');
            if ($domain !== '') {
                $dupe = DB::one('SELECT id FROM companies WHERE domain = ?' . ($id ? ' AND id <> ?' : '') . ' LIMIT 1', $id ? [$domain, $id] : [$domain]);
                if ($dupe) {
                    Flash::error('Ese dominio ya está asignado a otra empresa.');
                    redirect($id ? '/super/empresas/' . $id : '/super/empresas/nueva');
                }
            }
            $expires = Request::str('expires_at');
            $data = [
                'slug'        => Company::uniqueSlug(Request::str('slug') ?: $name, $id ?: null),
                'name'        => $name,
                'legal_name'  => mb_substr(Request::str('legal_name'), 0, 180) ?: null,
                'nit'         => mb_substr(Request::str('nit'), 0, 30) ?: null,
                'plan_id'     => Request::int('plan_id') ?: null,
                'status'      => $status,
                'expires_at'  => preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires) ? $expires : null,
                'domain'      => $domain ?: null,
                'theme'       => $theme,
                'color_accent' => Company::hex(Request::str('color_accent'), $base['accent']),
                'color_ink'    => Company::hex(Request::str('color_ink'), $base['ink']),
                'color_paper'  => Company::hex(Request::str('color_paper'), $base['paper']),
                'tagline'     => mb_substr(Request::str('tagline'), 0, 190) ?: null,
                'email'       => Request::email('email') ?: null,
                'phone'       => mb_substr(Request::str('phone'), 0, 40) ?: null,
                'whatsapp'    => preg_replace('/[^0-9]/', '', Request::str('whatsapp')) ?: null,
                'address'     => mb_substr(Request::str('address'), 0, 220) ?: null,
                'city'        => mb_substr(Request::str('city'), 0, 90) ?: null,
                'years_experience' => max(0, min(200, Request::int('years_experience'))),
                'updated_at'  => nowSql(),
            ];
            $f = Uploader::files('logo');
            if ($f && $id) {
                $res = Uploader::image($f[0], $id, 'marca', 600, 400);
                if ($res) {
                    $data['logo'] = $res['path'];
                }
            }

            if ($id) {
                DB::update('companies', $data, 'id = :id', ['id' => $id]);
                Audit::log('empresa.editar', 'company', $id, ['nombre' => $name, 'estado' => $status], null);
                if (isset($data['logo'])) {
                    Img::pwaIcons(STORAGE_PATH . '/uploads/' . $data['logo'], STORAGE_PATH . '/uploads/e' . $id . '/iconos', [72, 96, 128, 144, 152, 192, 384, 512], (string) $data['color_ink']);
                }
                Flash::ok('Empresa actualizada.');
                redirect('/super/empresas/' . $id);
            }

            // Alta: se crea junto con su administrador y datos base.
            $adminEmail = Request::email('admin_email');
            $adminPass  = Request::raw('admin_password');
            $adminName  = mb_substr(Request::str('admin_name'), 0, 120) ?: 'Administrador';
            if ($adminEmail === '' || !Security::passwordOk($adminPass)) {
                Flash::error('Indique el correo del administrador y una contraseña de 8+ caracteres con mayúsculas, minúsculas y números.');
                Flash::keep($_POST);
                redirect('/super/empresas/nueva');
            }
            if (DB::one('SELECT id FROM users WHERE email = ? LIMIT 1', [$adminEmail])) {
                Flash::error('Ya existe un usuario con ese correo.');
                redirect('/super/empresas/nueva');
            }
            $data['quote_year'] = (int) date('Y');
            $data['created_at'] = nowSql();
            DB::begin();
            try {
                $newId = DB::insert('companies', $data);
                DB::insert('users', [
                    'company_id' => $newId,
                    'name'       => $adminName,
                    'email'      => $adminEmail,
                    'password'   => Security::hashPassword($adminPass),
                    'role'       => Auth::ROLE_ADMIN,
                    'status'     => 'activo',
                    'created_at' => nowSql(),
                ]);
                DB::insert('price_lists', ['company_id' => $newId, 'name' => 'Precio de lista', 'discount_pct' => 0, 'is_default' => 1]);
                DB::insert('price_lists', ['company_id' => $newId, 'name' => 'Mayorista', 'discount_pct' => 12, 'is_default' => 0]);
                foreach ([['material', 'Material'], ['medida', 'Medida'], ['marca_tec', 'Norma / Serie'], ['aplicacion', 'Aplicación']] as $i => [$code, $label]) {
                    DB::insert('attribute_defs', ['company_id' => $newId, 'code' => $code, 'label' => $label, 'type' => 'texto', 'filterable' => 1, 'sort' => $i]);
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollback();
                throw $e;
            }
            Audit::log('empresa.crear', 'company', $newId, ['nombre' => $name], null);
            Flash::ok('Empresa creada. Su sitio público está en /e/' . $data['slug']);
            redirect('/super/empresas/' . $newId);
        }

        $this->view('super/company-form', [
            'title'  => $c ? $c['name'] : 'Nueva empresa',
            'c'      => $c,
            'plans'  => Plan::all(),
            'themes' => Company::THEMES,
            'users'  => $c ? DB::all('SELECT * FROM users WHERE company_id = ? ORDER BY role, name', [(int) $c['id']]) : [],
            'usage'  => $c ? Company::usage((int) $c['id']) : [],
            'limits' => $c ? Company::limits((int) $c['id']) : [],
        ], 'layout/super');
    }

    public function destroy(array $params): void
    {
        $this->super();
        $this->guardPost();
        $id = (int) $params['id'];
        $c = Company::find($id);
        if (!$c) {
            ErrorHandler::render(404);
        }
        if (Request::str('confirm') !== (string) $c['slug']) {
            Flash::error('Para eliminar debe escribir el identificador exacto: ' . $c['slug']);
            redirect('/super/empresas/' . $id);
        }
        DB::begin();
        try {
            foreach (['quote_items', 'quote_events', 'quotes', 'product_attributes', 'product_images', 'product_documents',
                      'product_prices', 'products', 'categories', 'attribute_defs', 'brands', 'price_lists',
                      'customer_contacts', 'customers', 'imports', 'notifications', 'email_log', 'audit_log', 'users'] as $t) {
                DB::delete($t, 'company_id = :c', ['c' => $id]);
            }
            DB::delete('companies', 'id = :id', ['id' => $id]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }
        $dir = STORAGE_PATH . '/uploads/e' . $id;
        if (is_dir($dir)) {
            self::rrmdir($dir);
        }
        Audit::log('empresa.eliminar', 'company', $id, ['nombre' => $c['name']], null);
        Flash::ok('Empresa eliminada junto con todos sus datos.');
        redirect('/super/empresas');
    }

    /** Entra al panel de una empresa como su administrador (soporte). */
    public function impersonate(array $params): void
    {
        $su = $this->super();
        $id = (int) $params['id'];
        $c = Company::find($id);
        if (!$c) {
            ErrorHandler::render(404);
        }
        $admin = DB::one('SELECT * FROM users WHERE company_id = ? AND role = "admin" AND status = "activo" ORDER BY id LIMIT 1', [$id]);
        if (!$admin) {
            Flash::error('Esta empresa no tiene un administrador activo.');
            redirect('/super/empresas/' . $id);
        }
        Audit::log('empresa.impersonar', 'company', $id, ['por' => $su['email']], $id);
        Auth::login($admin);
        Flash::warn('Está trabajando dentro de ' . $c['name'] . ' como administrador. Cierre sesión para volver a su cuenta.');
        redirect('/panel');
    }

    private static function rrmdir(string $dir): void
    {
        $root = realpath(STORAGE_PATH . '/uploads');
        $real = realpath($dir);
        if (!$root || !$real || !str_starts_with($real, $root)) {
            return;
        }
        foreach (scandir($real) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $real . '/' . $f;
            is_dir($p) ? self::rrmdir($p) : @unlink($p);
        }
        @rmdir($real);
    }
}
