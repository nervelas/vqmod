<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Audit;
use MenuGold\Core\Auth;
use MenuGold\Core\DB;
use MenuGold\Core\Image;
use MenuGold\Core\Mailer;
use MenuGold\Core\Money;
use MenuGold\Core\Security;
use MenuGold\Core\Session;
use MenuGold\Core\Validator;
use MenuGold\Models\Restaurant;

class SettingsController extends BaseController
{
    protected $ability = 'settings';

    /** Ocho temas de lujo listos para usar. */
    public static $themes = array(
        'brasa'    => array('label' => 'Brasa (predeterminado)', 'primary' => '#D8B26E', 'accent' => '#C4502B'),
        'olivo'    => array('label' => 'Olivo',                  'primary' => '#B7C49A', 'accent' => '#6E7F4E'),
        'vino'     => array('label' => 'Vino',                   'primary' => '#D9A5A5', 'accent' => '#7B2B36'),
        'cobre'    => array('label' => 'Cobre',                  'primary' => '#E0A472', 'accent' => '#9C4F1F'),
        'marfil'   => array('label' => 'Marfil',                 'primary' => '#EFE3CD', 'accent' => '#A08C63'),
        'esmeralda'=> array('label' => 'Esmeralda',              'primary' => '#8ED0AE', 'accent' => '#1F6B4C'),
        'indigo'   => array('label' => 'Índigo',                 'primary' => '#A9B6E8', 'accent' => '#3B4A8C'),
        'obsidiana'=> array('label' => 'Obsidiana',              'primary' => '#C9C9C9', 'accent' => '#5E5E5E'),
    );

    public static $fontCombos = array(
        'editorial' => array('label' => 'Editorial · Fraunces + Inter', 'display' => 'Fraunces', 'ui' => 'Inter'),
        'clasica'   => array('label' => 'Clásica · Fraunces en todo',   'display' => 'Fraunces', 'ui' => 'Fraunces'),
        'moderna'   => array('label' => 'Moderna · Inter en todo',      'display' => 'Inter',    'ui' => 'Inter'),
    );

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            $v = new Validator($this->request->post);
            $v->required('name', 'El nombre')->max('name', 'El nombre', 120)
              ->email('email', 'El correo')
              ->numeric('tax_rate', 'El impuesto', 0, 100);
            if ($v->fails()) {
                Session::flash('error', $v->firstError());
                return $this->redirect('/panel/ajustes');
            }

            $modes = array();
            foreach ($this->request->arr('service_modes') as $m) {
                if (in_array($m, array('dine_in', 'takeaway', 'delivery'), true)) { $modes[] = $m; }
            }
            if (!$modes) { $modes = array('dine_in'); }

            $langs = array();
            foreach ($this->request->arr('langs') as $l) {
                if (in_array($l, array('es', 'en'), true)) { $langs[] = $l; }
            }
            if (!$langs) { $langs = array('es'); }

            $orderMode = $this->request->str('order_mode', 'order');
            if (!in_array($orderMode, array('catalog', 'order', 'whatsapp'), true)) { $orderMode = 'order'; }

            $data = array(
                'name'          => $this->request->str('name'),
                'tagline'       => $this->request->str('tagline'),
                'description'   => $this->request->str('description'),
                'phone'         => $this->request->str('phone'),
                'whatsapp'      => $this->request->str('whatsapp'),
                'email'         => $this->request->str('email'),
                'address'       => $this->request->str('address'),
                'city'          => $this->request->str('city'),
                'map_url'       => $this->safeUrl('map_url'),
                'review_url'    => $this->safeUrl('review_url'),
                'currency'      => mb_substr($this->request->str('currency', 'Q'), 0, 6),
                'tax_rate'      => min(100, max(0, $this->request->float('tax_rate'))),
                'tax_included'  => $this->request->bool('tax_included') ? 1 : 0,
                'service_modes' => implode(',', array_unique($modes)),
                'order_mode'    => $orderMode,
                'langs'         => implode(',', array_unique($langs)),
                'lang_default'  => in_array($this->request->str('lang_default', 'es'), $langs, true) ? $this->request->str('lang_default') : $langs[0],
                'timezone'      => $this->safeTimezone($this->request->str('timezone', 'America/Guatemala')),
            );
            DB::update('restaurants', $data, 'id = :id', array('id' => $this->rid()));
            Audit::log('settings_updated', 'restaurant', $this->rid(), array_keys($data));
            Session::flash('success', 'Ajustes guardados.');
            return $this->redirect('/panel/ajustes');
        }

        return $this->view('admin/settings/general', array('timezones' => $this->timezones()));
    }

    public function appearance()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            $theme = $this->request->str('theme', 'brasa');
            if (!isset(self::$themes[$theme]) && $theme !== 'custom') { $theme = 'brasa'; }
            $combo = $this->request->str('font_combo', 'editorial');
            if (!isset(self::$fontCombos[$combo])) { $combo = 'editorial'; }

            $primary = $this->hexOr($this->request->str('primary_color'), '#D8B26E');
            $accent  = $this->hexOr($this->request->str('accent_color'), '#C4502B');
            if ($theme !== 'custom') {
                $primary = self::$themes[$theme]['primary'];
                $accent  = self::$themes[$theme]['accent'];
            }

            DB::update('restaurants', array(
                'theme' => $theme, 'font_combo' => $combo,
                'primary_color' => $primary, 'accent_color' => $accent,
            ), 'id = :id', array('id' => $this->rid()));

            foreach (array('logo', 'cover') as $field) {
                if (empty($this->request->files[$field]['name'])) { continue; }
                try {
                    $base = Image::store($this->request->files[$field], $this->rid(), 'marca', 1600);
                    if ($this->restaurant[$field] !== '') { Image::remove($this->restaurant[$field]); }
                    DB::update('restaurants', array($field => $base), 'id = :id', array('id' => $this->rid()));
                    if ($field === 'logo') {
                        // Los iconos de la app se regeneran con el logo nuevo.
                        Image::generatePwaIcons($base, $this->rid(), '#0C0B09');
                    }
                } catch (\Throwable $e) {
                    Session::flash('error', $e->getMessage());
                }
            }

            Audit::log('appearance_updated', 'restaurant', $this->rid(), array('theme' => $theme));
            Session::flash('success', 'Apariencia actualizada.');
            return $this->redirect('/panel/ajustes/apariencia');
        }

        return $this->view('admin/settings/appearance', array(
            'themes' => self::$themes,
            'combos' => self::$fontCombos,
        ));
    }

    public function hours()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            $closed = $this->request->arr('closed');
            $opens  = $this->request->arr('opens_at');
            $closes = $this->request->arr('closes_at');
            for ($d = 0; $d <= 6; $d++) {
                $isClosed = in_array((string)$d, array_map('strval', $closed), true) ? 1 : 0;
                $o = isset($opens[$d]) && preg_match('/^\d{1,2}:\d{2}$/', $opens[$d]) ? $opens[$d] . ':00' : null;
                $c = isset($closes[$d]) && preg_match('/^\d{1,2}:\d{2}$/', $closes[$d]) ? $closes[$d] . ':00' : null;
                DB::run(
                    'INSERT INTO restaurant_hours (restaurant_id, weekday, opens_at, closes_at, is_closed)
                     VALUES (:r, :w, :o, :c, :x)
                     ON DUPLICATE KEY UPDATE opens_at = :o2, closes_at = :c2, is_closed = :x2',
                    array('r' => $this->rid(), 'w' => $d, 'o' => $o, 'c' => $c, 'x' => $isClosed,
                          'o2' => $o, 'c2' => $c, 'x2' => $isClosed)
                );
            }
            Audit::log('hours_updated', 'restaurant', $this->rid());
            Session::flash('success', 'Horarios guardados.');
            return $this->redirect('/panel/ajustes/horarios');
        }

        return $this->view('admin/settings/hours', array('hours' => Restaurant::hours($this->rid())));
    }

    public function delivery()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            if ($this->request->str('action') === 'delete') {
                DB::delete('delivery_zones', 'id = :id AND restaurant_id = :r',
                    array('id' => $this->request->int('id'), 'r' => $this->rid()));
                Session::flash('success', 'Zona eliminada.');
                return $this->redirect('/panel/ajustes/entregas');
            }

            $names = $this->request->arr('zone_name');
            $fees  = $this->request->arr('zone_fee');
            $mins  = $this->request->arr('zone_min');
            $etas  = $this->request->arr('zone_eta');
            $ids   = $this->request->arr('zone_id');
            foreach ($names as $i => $name) {
                $name = trim((string)$name);
                if ($name === '') { continue; }
                $row = array(
                    'name'        => mb_substr($name, 0, 120),
                    'fee'         => Money::round(isset($fees[$i]) ? (float)$fees[$i] : 0),
                    'min_order'   => Money::round(isset($mins[$i]) ? (float)$mins[$i] : 0),
                    'eta_minutes' => max(5, min(240, isset($etas[$i]) ? (int)$etas[$i] : 40)),
                    'is_active'   => 1,
                    'sort'        => $i,
                );
                $existing = isset($ids[$i]) ? (int)$ids[$i] : 0;
                if ($existing > 0 && $this->own('delivery_zones', $existing)) {
                    DB::update('delivery_zones', $row, 'id = :id AND restaurant_id = :r', array('id' => $existing, 'r' => $this->rid()));
                } else {
                    $row['restaurant_id'] = $this->rid();
                    DB::insert('delivery_zones', $row);
                }
            }
            Audit::log('delivery_updated', 'restaurant', $this->rid());
            Session::flash('success', 'Zonas de entrega guardadas.');
            return $this->redirect('/panel/ajustes/entregas');
        }

        return $this->view('admin/settings/delivery', array(
            'zones' => DB::all('SELECT * FROM delivery_zones WHERE restaurant_id = :r ORDER BY sort, id', array('r' => $this->rid())),
        ));
    }

    public function payments()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            $tips = array();
            foreach (explode(',', $this->request->str('tip_options', '10,15,20')) as $t) {
                $t = (int)trim($t);
                if ($t > 0 && $t <= 50) { $tips[] = $t; }
            }
            if (!$tips) { $tips = array(10, 15, 20); }

            DB::update('restaurants', array(
                'tip_enabled' => $this->request->bool('tip_enabled') ? 1 : 0,
                'tip_options' => implode(',', $tips),
                'bank_info'   => mb_substr($this->request->str('bank_info'), 0, 2000),
                'payment_url' => $this->safeUrl('payment_url'),
            ), 'id = :id', array('id' => $this->rid()));

            Restaurant::setSetting($this->rid(), 'loyalty_points_per_100', (string)max(0, min(100, $this->request->int('loyalty_points_per_100', 0))));
            Restaurant::setSetting($this->rid(), 'printer_width', $this->request->str('printer_width', '80') === '58' ? '58' : '80');
            Restaurant::setSetting($this->rid(), 'review_prompt', $this->request->bool('review_prompt') ? '1' : '0');

            Audit::log('payments_updated', 'restaurant', $this->rid());
            Session::flash('success', 'Cobros y propinas actualizados.');
            return $this->redirect('/panel/ajustes/pagos');
        }

        return $this->view('admin/settings/payments', array('settings' => Restaurant::settings($this->rid())));
    }

    public function mail()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            if ($this->request->str('action') === 'test') {
                $to = $this->request->str('test_email', $this->restaurant['email']);
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    Session::flash('error', 'Escribe un correo válido para la prueba.');
                    return $this->redirect('/panel/ajustes/correo');
                }
                $ok = Mailer::send($to, 'Prueba de correo · ' . $this->restaurant['name'],
                    Mailer::template('Tu correo está configurado',
                        '<p>Si estás leyendo esto, MenúGold puede enviar correos desde tu servidor.</p>'));
                Session::flash($ok ? 'success' : 'error',
                    $ok ? 'Correo de prueba enviado a ' . $to : 'No se pudo enviar. Revisa los datos SMTP y el registro de errores.');
                return $this->redirect('/panel/ajustes/correo');
            }

            foreach (array('smtp_host', 'smtp_port', 'smtp_user', 'smtp_secure', 'smtp_from', 'smtp_from_name') as $k) {
                Restaurant::setSetting($this->rid(), $k, $this->request->str($k, ''));
            }
            $pass = (string)$this->request->input('smtp_pass', '');
            if ($pass !== '') {
                Restaurant::setSetting($this->rid(), 'smtp_pass', $pass);
            }
            Audit::log('mail_updated', 'restaurant', $this->rid());
            Session::flash('success', 'Datos de correo guardados.');
            return $this->redirect('/panel/ajustes/correo');
        }

        return $this->view('admin/settings/mail', array('settings' => Restaurant::settings($this->rid())));
    }

    /* ---------------- Usuarios ---------------- */

    public function users()
    {
        $stop = $this->guard('users');
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            $v = new Validator($this->request->post);
            $v->required('name', 'El nombre')->required('username', 'El usuario')->email('email', 'El correo');
            if ($v->fails()) {
                Session::flash('error', $v->firstError());
                return $this->redirect('/panel/usuarios');
            }

            $role = $this->request->str('role', 'waiter');
            $allowed = array(Auth::ROLE_OWNER, Auth::ROLE_MANAGER, Auth::ROLE_KITCHEN, Auth::ROLE_WAITER);
            if (!in_array($role, $allowed, true)) { $role = Auth::ROLE_WAITER; }

            $id = $this->request->int('id', 0);
            $existing = $id > 0 ? DB::first('SELECT * FROM users WHERE id = :id AND restaurant_id = :r',
                array('id' => $id, 'r' => $this->rid())) : null;

            $username = preg_replace('/[^A-Za-z0-9._@\-]/', '', $this->request->str('username'));
            if ($username === '') {
                Session::flash('error', 'El usuario solo admite letras, números, punto, guion y arroba.');
                return $this->redirect('/panel/usuarios');
            }
            $clash = DB::value('SELECT id FROM users WHERE username = :u AND id <> :i', array('u' => $username, 'i' => $id));
            if ($clash) {
                Session::flash('error', 'Ese nombre de usuario ya está ocupado.');
                return $this->redirect('/panel/usuarios');
            }

            $data = array(
                'name'      => $this->request->str('name'),
                'username'  => $username,
                'email'     => $this->request->str('email'),
                'role'      => $role,
                'is_active' => $this->request->bool('is_active') ? 1 : 0,
            );

            $password = (string)$this->request->input('password', '');
            if ($password !== '') {
                if (strlen($password) < 8) {
                    Session::flash('error', 'La contraseña debe tener al menos 8 caracteres.');
                    return $this->redirect('/panel/usuarios');
                }
                $data['password_hash'] = Security::hashPassword($password);
            }
            $pin = preg_replace('/\D+/', '', (string)$this->request->input('pin', ''));
            if ($pin !== '') {
                if (strlen($pin) < 4) {
                    Session::flash('error', 'El PIN debe tener al menos 4 dígitos.');
                    return $this->redirect('/panel/usuarios');
                }
                $data['pin'] = Security::hashPassword($pin);
            }

            if ($existing) {
                DB::update('users', $data, 'id = :id AND restaurant_id = :r', array('id' => $id, 'r' => $this->rid()));
                Audit::log('user_updated', 'user', $id, array('role' => $role));
            } else {
                if (!isset($data['password_hash'])) {
                    Session::flash('error', 'Define una contraseña para el usuario nuevo.');
                    return $this->redirect('/panel/usuarios');
                }
                if (Restaurant::limitReached($this->rid(), 'users')) {
                    Session::flash('error', 'Alcanzaste el límite de usuarios de tu plan.');
                    return $this->redirect('/panel/usuarios');
                }
                $data['restaurant_id'] = $this->rid();
                $newId = DB::insert('users', $data);
                Audit::log('user_created', 'user', $newId, array('role' => $role));
            }
            Session::flash('success', 'Usuario guardado.');
            return $this->redirect('/panel/usuarios');
        }

        return $this->view('admin/settings/users', array(
            'users' => DB::all('SELECT * FROM users WHERE restaurant_id = :r ORDER BY role, name', array('r' => $this->rid())),
        ));
    }

    public function deleteUser(array $params)
    {
        $stop = $this->guard('users');
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $id = (int)$params['id'];
        if ($id === Auth::id()) {
            Session::flash('error', 'No puedes eliminar tu propio usuario.');
            return $this->redirect('/panel/usuarios');
        }
        DB::delete('users', 'id = :id AND restaurant_id = :r', array('id' => $id, 'r' => $this->rid()));
        Audit::log('user_deleted', 'user', $id);
        Session::flash('success', 'Usuario eliminado.');
        return $this->redirect('/panel/usuarios');
    }

    public function audit()
    {
        $stop = $this->guard('settings');
        if ($stop) { return $stop; }
        return $this->view('admin/audit', array(
            'entries' => DB::all(
                'SELECT a.*, u.name AS user_name FROM audit_log a
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.restaurant_id = :r ORDER BY a.id DESC LIMIT 200',
                array('r' => $this->rid())),
        ));
    }

    /* ---------------- Auxiliares ---------------- */

    private function hexOr($value, $default)
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', (string)$value) ? $value : $default;
    }

    private function safeUrl($field)
    {
        $v = $this->request->str($field, '');
        if ($v === '') { return ''; }
        if (!preg_match('#^https?://#i', $v)) { $v = 'https://' . $v; }
        return filter_var($v, FILTER_VALIDATE_URL) ? mb_substr($v, 0, 255) : '';
    }

    private function safeTimezone($tz)
    {
        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'America/Guatemala';
    }

    private function timezones()
    {
        return array(
            'America/Guatemala', 'America/El_Salvador', 'America/Tegucigalpa', 'America/Managua',
            'America/Costa_Rica', 'America/Panama', 'America/Mexico_City', 'America/Bogota',
            'America/Lima', 'America/Santiago', 'America/Argentina/Buenos_Aires', 'America/New_York',
            'America/Chicago', 'America/Los_Angeles', 'Europe/Madrid',
        );
    }
}
