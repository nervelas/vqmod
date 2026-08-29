<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Audit;
use MenuGold\Core\DB;
use MenuGold\Core\Image;
use MenuGold\Core\Money;
use MenuGold\Core\Session;
use MenuGold\Core\Validator;
use MenuGold\Models\Settings;

class SettingsController extends BaseController
{
    protected $ability = 'settings';

    /** Ocho temas listos para usar. */
    public static $themes = array(
        'brasa'     => array('label' => 'Brasa (predeterminado)', 'primary' => '#D8B26E', 'accent' => '#C4502B'),
        'olivo'     => array('label' => 'Olivo',                  'primary' => '#B7C49A', 'accent' => '#6E7F4E'),
        'vino'      => array('label' => 'Vino',                   'primary' => '#D9A5A5', 'accent' => '#7B2B36'),
        'cobre'     => array('label' => 'Cobre',                  'primary' => '#E0A472', 'accent' => '#9C4F1F'),
        'marfil'    => array('label' => 'Marfil',                 'primary' => '#EFE3CD', 'accent' => '#A08C63'),
        'esmeralda' => array('label' => 'Esmeralda',              'primary' => '#8ED0AE', 'accent' => '#1F6B4C'),
        'indigo'    => array('label' => 'Índigo',                 'primary' => '#A9B6E8', 'accent' => '#3B4A8C'),
        'obsidiana' => array('label' => 'Obsidiana',              'primary' => '#C9C9C9', 'accent' => '#5E5E5E'),
    );

    public static $fontCombos = array(
        'editorial' => array('label' => 'Editorial · Fraunces + Inter', 'display' => 'Fraunces', 'ui' => 'Inter'),
        'clasica'   => array('label' => 'Clásica · Fraunces en todo',   'display' => 'Fraunces', 'ui' => 'Fraunces'),
        'moderna'   => array('label' => 'Moderna · Inter en todo',      'display' => 'Inter',    'ui' => 'Inter'),
    );

    /* ---------------- Identidad y servicio ---------------- */

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

            Settings::setMany(array(
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
                'instagram'     => $this->safeUrl('instagram'),
                'facebook'      => $this->safeUrl('facebook'),
                'currency'      => mb_substr($this->request->str('currency', 'Q'), 0, 6),
                'tax_rate'      => (string)min(100, max(0, $this->request->float('tax_rate'))),
                'tax_included'  => $this->request->bool('tax_included') ? '1' : '0',
                'service_modes' => implode(',', array_unique($modes)),
                'order_mode'    => $orderMode,
                'langs'         => implode(',', array_unique($langs)),
                'lang_default'  => in_array($this->request->str('lang_default', 'es'), $langs, true) ? $this->request->str('lang_default') : $langs[0],
                'timezone'      => $this->safeTimezone($this->request->str('timezone', 'America/Guatemala')),
            ));
            Audit::log('settings_updated', 'settings', 0);
            Session::flash('success', 'Ajustes guardados.');
            return $this->redirect('/panel/ajustes');
        }

        return $this->view('admin/settings/general', array('timezones' => $this->timezones()));
    }

    /* ---------------- Apariencia ---------------- */

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
            Settings::setMany(array(
                'theme' => $theme, 'font_combo' => $combo,
                'primary_color' => $primary, 'accent_color' => $accent,
            ));

            foreach (array('logo', 'cover') as $field) {
                if (empty($this->request->files[$field]['name'])) { continue; }
                try {
                    $anterior = Settings::get($field);
                    $base = Image::store($this->request->files[$field], 'marca', 1600);
                    if ($anterior !== '') { Image::remove($anterior); }
                    Settings::set($field, $base);
                    // Los iconos de la app se regeneran con el logo nuevo.
                    if ($field === 'logo') { Image::generatePwaIcons($base, '#0C0B09'); }
                } catch (\Throwable $e) {
                    Session::flash('error', $e->getMessage());
                }
            }

            Audit::log('appearance_updated', 'settings', 0, array('theme' => $theme));
            Session::flash('success', 'Apariencia actualizada.');
            return $this->redirect('/panel/ajustes/apariencia');
        }

        return $this->view('admin/settings/appearance', array(
            'themes' => self::$themes,
            'combos' => self::$fontCombos,
        ));
    }

    /* ---------------- Horario ---------------- */

    public function hours()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            $closed = array_map('strval', $this->request->arr('closed'));
            $opens  = $this->request->arr('opens_at');
            $closes = $this->request->arr('closes_at');
            for ($d = 0; $d <= 6; $d++) {
                $isClosed = in_array((string)$d, $closed, true) ? 1 : 0;
                $o = isset($opens[$d]) && preg_match('/^\d{1,2}:\d{2}$/', $opens[$d]) ? $opens[$d] . ':00' : '12:00:00';
                $c = isset($closes[$d]) && preg_match('/^\d{1,2}:\d{2}$/', $closes[$d]) ? $closes[$d] . ':00' : '22:00:00';
                DB::run(
                    'INSERT INTO mg_hours (weekday, opens_at, closes_at, is_closed) VALUES (:w, :o, :c, :x)
                     ON DUPLICATE KEY UPDATE opens_at = :o2, closes_at = :c2, is_closed = :x2',
                    array('w' => $d, 'o' => $o, 'c' => $c, 'x' => $isClosed, 'o2' => $o, 'c2' => $c, 'x2' => $isClosed)
                );
            }
            Audit::log('hours_updated', 'settings', 0);
            Session::flash('success', 'Horario guardado.');
            return $this->redirect('/panel/ajustes/horario');
        }

        return $this->view('admin/settings/hours', array('hours' => Settings::hours()));
    }

    /* ---------------- Zonas de entrega ---------------- */

    public function delivery()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }

            if ($this->request->str('action') === 'delete') {
                DB::delete('mg_delivery_zones', 'id = :id', array('id' => $this->request->int('id')));
                Session::flash('success', 'Zona eliminada.');
                return $this->redirect('/panel/ajustes/entrega');
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
                    'name'      => mb_substr($name, 0, 120),
                    'fee'       => Money::round(isset($fees[$i]) ? (float)$fees[$i] : 0),
                    'min_total' => Money::round(isset($mins[$i]) ? (float)$mins[$i] : 0),
                    'minutes'   => max(5, min(240, isset($etas[$i]) ? (int)$etas[$i] : 40)),
                    'is_active' => 1,
                    'sort'      => (int)$i,
                );
                $existing = isset($ids[$i]) ? (int)$ids[$i] : 0;
                if ($existing > 0 && $this->row('mg_delivery_zones', $existing)) {
                    DB::update('mg_delivery_zones', $row, 'id = :id', array('id' => $existing));
                } else {
                    DB::insert('mg_delivery_zones', $row);
                }
            }
            Audit::log('delivery_updated', 'settings', 0);
            Session::flash('success', 'Zonas de entrega guardadas.');
            return $this->redirect('/panel/ajustes/entrega');
        }

        return $this->view('admin/settings/delivery', array(
            'zones' => DB::all('SELECT * FROM mg_delivery_zones ORDER BY sort, id'),
        ));
    }

    /* ---------------- Cobros, propina e impresión ---------------- */

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

            $metodos = array();
            foreach ($this->request->arr('payment_methods') as $m) {
                $m = trim((string)$m);
                if ($m !== '' && mb_strlen($m) <= 30) { $metodos[] = $m; }
            }
            if (!$metodos) { $metodos = array('efectivo'); }

            Settings::setMany(array(
                'tip_enabled'     => $this->request->bool('tip_enabled') ? '1' : '0',
                'tip_options'     => implode(',', $tips),
                'payment_methods' => implode(',', array_unique($metodos)),
                'bank_info'       => mb_substr($this->request->str('bank_info'), 0, 2000),
                'payment_link'    => $this->safeUrl('payment_link'),
                'printer_width'   => $this->request->str('printer_width', '80') === '58' ? '58' : '80',
                'kds_sound'       => $this->request->bool('kds_sound') ? '1' : '0',
                'kds_late_min'    => (string)max(3, min(90, $this->request->int('kds_late_min', 18))),
                'loyalty_points_per_100' => (string)max(0, min(100, $this->request->int('loyalty_points_per_100', 0))),
            ));

            Audit::log('payments_updated', 'settings', 0);
            Session::flash('success', 'Cobros y propinas actualizados.');
            return $this->redirect('/panel/ajustes/pagos');
        }

        return $this->view('admin/settings/payments', array());
    }

    /* ---------------- Auxiliares ---------------- */

    private function hexOr($value, $default)
    {
        $v = trim((string)$value);
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $v) ? $v : $default;
    }

    private function safeUrl($field)
    {
        $v = trim((string)$this->request->str($field, ''));
        if ($v === '') { return ''; }
        if (!preg_match('#^https?://#i', $v)) { $v = 'https://' . $v; }
        return filter_var($v, FILTER_VALIDATE_URL) ? mb_substr($v, 0, 500) : '';
    }

    private function safeTimezone($tz)
    {
        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'America/Guatemala';
    }

    private function timezones()
    {
        $preferidas = array(
            'America/Guatemala', 'America/Mexico_City', 'America/El_Salvador', 'America/Tegucigalpa',
            'America/Managua', 'America/Costa_Rica', 'America/Panama', 'America/Bogota', 'America/Lima',
            'America/Santiago', 'America/Argentina/Buenos_Aires', 'America/New_York', 'America/Los_Angeles',
            'Europe/Madrid',
        );
        $resto = array_diff(timezone_identifiers_list(), $preferidas);
        return array_merge($preferidas, array_values($resto));
    }
}
