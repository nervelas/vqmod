<?php
namespace MenuGold\Controllers\Super;

use MenuGold\Core\Audit;
use MenuGold\Core\Auth;
use MenuGold\Core\Backup;
use MenuGold\Core\Controller;
use MenuGold\Core\DB;
use MenuGold\Core\Image;
use MenuGold\Core\Response;
use MenuGold\Core\Security;
use MenuGold\Core\Session;
use MenuGold\Core\Str;
use MenuGold\Core\Validator;
use MenuGold\Core\View;
use MenuGold\Models\Landing;
use MenuGold\Models\Report;
use MenuGold\Models\Restaurant;
use MenuGold\Models\TableModel;

/** Consola del superadministrador de la plataforma. */
class SuperController extends Controller
{
    private function guard()
    {
        $user = Auth::user();
        if (!$user) {
            Session::set('after_login', '/panel');
            return $this->redirect('/panel/entrar');
        }
        if (!Auth::isSuper()) {
            return $this->denied('Esta zona es solo para el administrador de la plataforma.');
        }
        View::share('auth_user', $user);
        View::share('nav_active', $this->request->path);
        View::share('flashes', Session::flashAll());
        View::share('restaurant', null);
        return null;
    }

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        return $this->view('super/dashboard', array(
            'stats'  => Report::platform(),
            'recent' => DB::all('SELECT r.*, p.name AS plan_name FROM restaurants r
                                 LEFT JOIN plans p ON p.id = r.plan_id
                                 ORDER BY r.created_at DESC LIMIT 8'),
            'orders' => DB::all("SELECT DATE(placed_at) AS d, COUNT(*) AS n FROM orders
                                 WHERE placed_at >= :d AND status <> 'cancelled'
                                 GROUP BY DATE(placed_at) ORDER BY d",
                                array('d' => date('Y-m-d', strtotime('-29 days')))),
        ));
    }

    public function restaurants()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $q = $this->request->str('q', '');
        $params = array();
        $where = '1 = 1';
        if ($q !== '') { $where = '(r.name LIKE :q OR r.slug LIKE :q2)'; $params['q'] = '%' . $q . '%'; $params['q2'] = '%' . $q . '%'; }

        return $this->view('super/restaurants', array(
            'rows' => DB::all(
                'SELECT r.*, p.name AS plan_name,
                        (SELECT COUNT(*) FROM products WHERE restaurant_id = r.id) AS products,
                        (SELECT COUNT(*) FROM orders WHERE restaurant_id = r.id AND placed_at >= DATE_FORMAT(NOW(), "%Y-%m-01")) AS orders_month
                 FROM restaurants r LEFT JOIN plans p ON p.id = r.plan_id
                 WHERE ' . $where . ' ORDER BY r.name', $params),
            'q' => $q,
        ));
    }

    public function restaurant(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        $id = $params['id'] === 'nuevo' ? 0 : (int)$params['id'];
        $restaurant = $id > 0 ? Restaurant::find($id) : null;
        if ($id > 0 && !$restaurant) { return $this->notFound('Ese restaurante no existe.'); }

        if (!$this->request->isPost()) {
            return $this->view('super/restaurant', array(
                'row'    => $restaurant,
                'plans'  => DB::all('SELECT * FROM plans ORDER BY sort, id'),
                'owners' => $restaurant ? DB::all("SELECT * FROM users WHERE restaurant_id = :r ORDER BY role", array('r' => $id)) : array(),
                'usage'  => $restaurant ? Restaurant::usage($id) : null,
            ));
        }

        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $v = new Validator($this->request->post);
        $v->required('name', 'El nombre')->max('name', 'El nombre', 120)->email('email', 'El correo');
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            return $this->back('/super/restaurantes');
        }

        $status = $this->request->str('status', 'trial');
        if (!in_array($status, array('active', 'trial', 'suspended'), true)) { $status = 'trial'; }

        $slug = $this->request->str('slug', '');
        $slug = $slug !== '' ? Str::slug($slug) : Str::slug($this->request->str('name'));
        $slug = Restaurant::uniqueSlug($slug, $id);

        $data = array(
            'name'            => $this->request->str('name'),
            'slug'            => $slug,
            'tagline'         => $this->request->str('tagline'),
            'email'           => $this->request->str('email'),
            'phone'           => $this->request->str('phone'),
            'whatsapp'        => $this->request->str('whatsapp'),
            'address'         => $this->request->str('address'),
            'city'            => $this->request->str('city'),
            'currency'        => mb_substr($this->request->str('currency', 'Q'), 0, 6),
            'plan_id'         => $this->request->int('plan_id', 0) ?: null,
            'plan_expires_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->request->str('plan_expires_at')) ? $this->request->str('plan_expires_at') : null,
            'status'          => $status,
            'notes'           => $this->request->str('notes'),
        );

        if ($restaurant) {
            DB::update('restaurants', $data, 'id = :id', array('id' => $id));
            Audit::log('restaurant_updated', 'restaurant', $id, array('slug' => $slug), $id);
            Session::flash('success', 'Restaurante actualizado.');
            return $this->redirect('/super/restaurante/' . $id);
        }

        // Alta: restaurante + dueño + horarios por omisión.
        $ownerUser  = preg_replace('/[^A-Za-z0-9._@\-]/', '', $this->request->str('owner_username', ''));
        $ownerPass  = (string)$this->request->input('owner_password', '');
        if ($ownerUser === '' || strlen($ownerPass) < 8) {
            Session::flash('error', 'Define el usuario del dueño y una contraseña de al menos 8 caracteres.');
            return $this->back('/super/restaurante/nuevo');
        }
        if (DB::value('SELECT id FROM users WHERE username = :u', array('u' => $ownerUser))) {
            Session::flash('error', 'Ese nombre de usuario ya existe en la plataforma.');
            return $this->back('/super/restaurante/nuevo');
        }

        DB::begin();
        try {
            $newId = DB::insert('restaurants', array_merge($data, array('created_at' => date('Y-m-d H:i:s'))));
            DB::insert('users', array(
                'restaurant_id' => $newId,
                'role'          => Auth::ROLE_OWNER,
                'name'          => $this->request->str('owner_name', 'Dueño'),
                'username'      => $ownerUser,
                'email'         => $this->request->str('email'),
                'password_hash' => Security::hashPassword($ownerPass),
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
            ));
            for ($d = 0; $d <= 6; $d++) {
                DB::insert('restaurant_hours', array(
                    'restaurant_id' => $newId, 'weekday' => $d,
                    'opens_at' => '11:00:00', 'closes_at' => '22:00:00', 'is_closed' => 0,
                ));
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            Session::flash('error', 'No se pudo crear el restaurante: ' . $e->getMessage());
            return $this->back('/super/restaurante/nuevo');
        }

        Audit::log('restaurant_created', 'restaurant', $newId, array('slug' => $slug), $newId);
        Session::flash('success', 'Restaurante creado. Su menú vive en /r/' . $slug);
        return $this->redirect('/super/restaurante/' . $newId);
    }

    public function toggleStatus(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $id = (int)$params['id'];
        $r = Restaurant::find($id);
        if (!$r) { return $this->notFound('Ese restaurante no existe.'); }
        $new = $r['status'] === 'suspended' ? 'active' : 'suspended';
        DB::update('restaurants', array('status' => $new), 'id = :id', array('id' => $id));
        Audit::log('restaurant_status', 'restaurant', $id, array('status' => $new), $id);
        Session::flash('success', $new === 'suspended' ? 'Restaurante suspendido.' : 'Restaurante reactivado.');
        return $this->back('/super/restaurantes');
    }

    public function impersonate(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $id = (int)$params['id'];
        if (!Restaurant::find($id)) { return $this->notFound('Ese restaurante no existe.'); }
        Session::set('impersonate_restaurant', $id);
        Audit::log('impersonate', 'restaurant', $id, array(), $id);
        return $this->redirect('/panel');
    }

    public function stopImpersonating()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        Session::forget('impersonate_restaurant');
        return $this->redirect('/super/restaurantes');
    }

    /* ---------------- Planes ---------------- */

    public function plans()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            $name = $this->request->str('name', '');
            if ($name === '') {
                Session::flash('error', 'El plan necesita un nombre.');
                return $this->redirect('/super/planes');
            }
            $id = $this->request->int('id', 0);
            $data = array(
                'name'             => $name,
                'slug'             => Str::slug($name),
                'price_month'      => max(0, $this->request->float('price_month')),
                'max_products'     => max(0, $this->request->int('max_products', 50)),
                'max_tables'       => max(0, $this->request->int('max_tables', 10)),
                'max_orders_month' => max(0, $this->request->int('max_orders_month', 500)),
                'max_users'        => max(0, $this->request->int('max_users', 3)),
                'features'         => json_encode(array_values(array_filter(array_map('trim',
                                        explode("\n", $this->request->str('features'))))), JSON_UNESCAPED_UNICODE),
                'is_active'        => $this->request->bool('is_active') ? 1 : 0,
                'sort'             => $this->request->int('sort', 0),
            );
            $clash = DB::value('SELECT id FROM plans WHERE slug = :s AND id <> :i', array('s' => $data['slug'], 'i' => $id));
            if ($clash) { $data['slug'] .= '-' . random_int(10, 99); }

            if ($id > 0 && DB::value('SELECT id FROM plans WHERE id = :id', array('id' => $id))) {
                DB::update('plans', $data, 'id = :id', array('id' => $id));
            } else {
                $id = DB::insert('plans', $data);
            }
            Audit::log('plan_saved', 'plan', $id, array('name' => $name));
            Session::flash('success', 'Plan guardado.');
            return $this->redirect('/super/planes');
        }

        $plans = DB::all('SELECT * FROM plans ORDER BY sort, id');
        foreach ($plans as $i => $p) {
            $plans[$i]['features_list'] = Str::json($p['features']);
            $plans[$i]['used_by'] = (int)DB::value('SELECT COUNT(*) FROM restaurants WHERE plan_id = :p', array('p' => (int)$p['id']), 0);
        }
        return $this->view('super/plans', array('plans' => $plans));
    }

    public function deletePlan(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }
        DB::delete('plans', 'id = :id', array('id' => (int)$params['id']));
        Session::flash('success', 'Plan eliminado. Los restaurantes que lo usaban quedaron sin límites de plan.');
        return $this->redirect('/super/planes');
    }

    /* ---------------- Sitio de venta ---------------- */

    public function landing()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            foreach (array_keys(Landing::defaults()) as $key) {
                if (!array_key_exists($key, $this->request->post)) { continue; }
                $value = (string)$this->request->post[$key];
                Landing::put($key, mb_substr($value, 0, 4000));
            }
            if (!empty($this->request->files['seo_og_image']['name'])) {
                try {
                    $base = Image::store($this->request->files['seo_og_image'], 0, 'landing', 1600);
                    Landing::put('seo_og_image', $base);
                } catch (\Throwable $e) { Session::flash('error', $e->getMessage()); }
            }
            Audit::log('landing_updated', 'landing', 0);
            Session::flash('success', 'Sitio de venta actualizado.');
            return $this->redirect('/super/landing');
        }

        return $this->view('super/landing', array(
            'values'   => array_merge(Landing::defaults(), Landing::all()),
            'defaults' => Landing::defaults(),
            'places'   => Restaurant::allActive(),
        ));
    }

    public function landingPlans()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            if ($this->request->str('action') === 'delete') {
                DB::delete('landing_plans', 'id = :id', array('id' => $this->request->int('id')));
                Session::flash('success', 'Plan del sitio eliminado.');
                return $this->redirect('/super/landing/planes');
            }
            $data = array(
                'name'        => $this->request->str('name'),
                'price'       => $this->request->str('price'),
                'period'      => $this->request->str('period', 'al mes'),
                'pitch'       => $this->request->str('pitch'),
                'features'    => $this->request->str('features'),
                'cta_text'    => $this->request->str('cta_text', 'Quiero este plan'),
                'wa_message'  => $this->request->str('wa_message'),
                'is_featured' => $this->request->bool('is_featured') ? 1 : 0,
                'is_active'   => $this->request->bool('is_active') ? 1 : 0,
                'sort'        => $this->request->int('sort', 0),
            );
            if ($data['name'] === '') {
                Session::flash('error', 'El plan necesita un nombre.');
                return $this->redirect('/super/landing/planes');
            }
            $id = $this->request->int('id', 0);
            if ($id > 0 && DB::value('SELECT id FROM landing_plans WHERE id = :id', array('id' => $id))) {
                DB::update('landing_plans', $data, 'id = :id', array('id' => $id));
            } else {
                DB::insert('landing_plans', $data);
            }
            Session::flash('success', 'Plan del sitio guardado.');
            return $this->redirect('/super/landing/planes');
        }

        return $this->view('super/landing-plans', array(
            'rows' => DB::all('SELECT * FROM landing_plans ORDER BY sort, id'),
        ));
    }

    public function landingTestimonials()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            if ($this->request->str('action') === 'delete') {
                DB::delete('testimonials', 'id = :id', array('id' => $this->request->int('id')));
                Session::flash('success', 'Testimonio eliminado.');
                return $this->redirect('/super/landing/testimonios');
            }
            $data = array(
                'name'      => $this->request->str('name'),
                'role'      => $this->request->str('role'),
                'place'     => $this->request->str('place'),
                'quote'     => $this->request->str('quote'),
                'rating'    => max(1, min(5, $this->request->int('rating', 5))),
                'is_active' => $this->request->bool('is_active') ? 1 : 0,
                'sort'      => $this->request->int('sort', 0),
            );
            if ($data['name'] === '' || $data['quote'] === '') {
                Session::flash('error', 'El testimonio necesita nombre y texto.');
                return $this->redirect('/super/landing/testimonios');
            }
            $id = $this->request->int('id', 0);
            if ($id > 0 && DB::value('SELECT id FROM testimonials WHERE id = :id', array('id' => $id))) {
                DB::update('testimonials', $data, 'id = :id', array('id' => $id));
            } else {
                DB::insert('testimonials', $data);
            }
            Session::flash('success', 'Testimonio guardado.');
            return $this->redirect('/super/landing/testimonios');
        }

        return $this->view('super/landing-testimonials', array(
            'rows' => DB::all('SELECT * FROM testimonials ORDER BY sort, id'),
        ));
    }

    /* ---------------- Respaldos ---------------- */

    public function backups()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        return $this->view('super/backups', array('files' => Backup::listFiles()));
    }

    public function createBackup()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        try {
            $file = Backup::create();
            Audit::log('backup_created', 'system', 0, array('file' => basename($file)));
            Session::flash('success', 'Respaldo creado: ' . basename($file));
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo crear el respaldo: ' . $e->getMessage());
        }
        return $this->redirect('/super/respaldo');
    }

    public function downloadBackup()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        $name = basename($this->request->str('archivo', ''));
        $path = MG_STORAGE . '/backups/' . $name;
        if ($name === '' || !preg_match('/^menugold-[\w\-]+\.sql(\.gz)?$/', $name) || !is_file($path)) {
            return $this->notFound('Ese respaldo no existe.');
        }
        Audit::log('backup_downloaded', 'system', 0, array('file' => $name));
        return Response::make((string)file_get_contents($path), 200, array(
            'Content-Type'        => substr($name, -3) === '.gz' ? 'application/gzip' : 'application/sql',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Content-Length'      => (string)filesize($path),
        ));
    }

    public function audit()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        return $this->view('super/audit', array(
            'entries' => DB::all(
                'SELECT a.*, u.name AS user_name, r.name AS restaurant_name
                 FROM audit_log a
                 LEFT JOIN users u ON u.id = a.user_id
                 LEFT JOIN restaurants r ON r.id = a.restaurant_id
                 ORDER BY a.id DESC LIMIT 300'),
        ));
    }
}
