<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Audit;
use MenuGold\Core\DB;
use MenuGold\Core\Image;
use MenuGold\Core\Session;
use MenuGold\Core\Str;
use MenuGold\Models\Order;
use MenuGold\Models\Report;
use MenuGold\Models\Restaurant;

class DashboardController extends BaseController
{
    protected $ability = 'orders';

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $rid = $this->rid();

        $today = date('Y-m-d');
        $summary = Report::summary($rid, $today, $today);
        $month   = Report::summary($rid, date('Y-m-01'), $today);
        $byDay   = Report::byDay($rid, date('Y-m-d', strtotime('-13 days')), $today);
        $top     = Report::topProducts($rid, date('Y-m-01'), $today, 6);

        // Serie de 14 días sin huecos, para la gráfica.
        $series = array();
        for ($i = 13; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime('-' . $i . ' days'));
            $series[$d] = 0.0;
        }
        foreach ($byDay as $row) {
            if (isset($series[$row['d']])) { $series[$row['d']] = (float)$row['revenue']; }
        }

        return $this->view('admin/dashboard', array(
            'summary'   => $summary,
            'month'     => $month,
            'series'    => $series,
            'top'       => $top,
            'usage'     => Restaurant::usage($rid),
            'active'    => Order::recent($rid, 8),
            'calls'     => DB::all("SELECT sc.*, t.name AS table_name FROM service_calls sc
                                     LEFT JOIN tables t ON t.id = sc.table_id
                                     WHERE sc.restaurant_id = :r AND sc.status = 'open'
                                     ORDER BY sc.created_at", array('r' => $rid)),
            'setupDone' => $this->setupChecklist($rid),
            'sinFoto'   => \MenuGold\Models\PhotoJob::cuantosFaltan($rid),
        ));
    }

    private function setupChecklist($rid)
    {
        return array(
            'identity' => $this->restaurant['logo'] !== '' && $this->restaurant['cover'] !== '',
            'menu'     => (int)DB::value('SELECT COUNT(*) FROM products WHERE restaurant_id = :r', array('r' => $rid), 0) > 0,
            'tables'   => (int)DB::value('SELECT COUNT(*) FROM tables WHERE restaurant_id = :r', array('r' => $rid), 0) > 0,
            'hours'    => (int)DB::value('SELECT COUNT(*) FROM restaurant_hours WHERE restaurant_id = :r', array('r' => $rid), 0) > 0,
        );
    }

    /** Puesta en marcha en cuatro pasos. */
    public function onboarding()
    {
        $stop = $this->guard('settings');
        if ($stop) { return $stop; }

        $step = max(1, min(4, $this->request->int('paso', 1)));

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }
            $step = max(1, min(4, $this->request->int('step', 1)));

            if ($step === 1) {
                $data = array(
                    'name'    => $this->request->str('name', $this->restaurant['name']),
                    'tagline' => $this->request->str('tagline', ''),
                    'phone'   => $this->request->str('phone', ''),
                    'whatsapp'=> $this->request->str('whatsapp', ''),
                    'address' => $this->request->str('address', ''),
                );
                if ($data['name'] === '') {
                    Session::flash('error', 'El nombre del restaurante es obligatorio.');
                    return $this->redirect('/panel/inicio-guiado?paso=1');
                }
                DB::update('restaurants', $data, 'id = :id', array('id' => $this->rid()));
                $this->handleLogoUpload();
                return $this->redirect('/panel/inicio-guiado?paso=2');
            }

            if ($step === 2) {
                $names = $this->request->arr('categories');
                $sort = 0;
                foreach ($names as $n) {
                    $n = trim((string)$n);
                    if ($n === '') { continue; }
                    $exists = DB::value('SELECT id FROM categories WHERE restaurant_id = :r AND name = :n',
                        array('r' => $this->rid(), 'n' => $n));
                    if ($exists) { continue; }
                    DB::insert('categories', array(
                        'restaurant_id' => $this->rid(), 'name' => $n,
                        'roman' => mg_roman(++$sort), 'sort' => $sort, 'is_active' => 1,
                    ));
                }
                return $this->redirect('/panel/inicio-guiado?paso=3');
            }

            if ($step === 3) {
                $count = max(0, min(80, $this->request->int('tables', 0)));
                $prefix = $this->request->str('prefix', 'Mesa');
                $existing = (int)DB::value('SELECT COUNT(*) FROM tables WHERE restaurant_id = :r', array('r' => $this->rid()), 0);
                for ($i = 1; $i <= $count; $i++) {
                    DB::insert('tables', array(
                        'restaurant_id' => $this->rid(),
                        'name'     => trim($prefix . ' ' . ($existing + $i)),
                        'seats'    => 4,
                        'qr_token' => \MenuGold\Models\TableModel::newToken(),
                        'sort'     => $existing + $i,
                    ));
                }
                return $this->redirect('/panel/inicio-guiado?paso=4');
            }

            if ($step === 4) {
                Restaurant::setSetting($this->rid(), 'onboarding_done', '1');
                Audit::log('onboarding_done', 'restaurant', $this->rid());
                Session::flash('success', 'Todo listo. Tu menú ya está en línea.');
                return $this->redirect('/panel');
            }
        }

        return $this->view('admin/onboarding', array(
            'step'       => $step,
            'categories' => DB::all('SELECT * FROM categories WHERE restaurant_id = :r ORDER BY sort', array('r' => $this->rid())),
            'tables'     => (int)DB::value('SELECT COUNT(*) FROM tables WHERE restaurant_id = :r', array('r' => $this->rid()), 0),
            'products'   => (int)DB::value('SELECT COUNT(*) FROM products WHERE restaurant_id = :r', array('r' => $this->rid()), 0),
        ));
    }

    private function handleLogoUpload()
    {
        if (empty($this->request->files['logo']['name'])) { return; }
        try {
            $base = Image::store($this->request->files['logo'], $this->rid(), 'marca', 960);
            DB::update('restaurants', array('logo' => $base), 'id = :id', array('id' => $this->rid()));
            Image::generatePwaIcons($base, $this->rid(), '#0C0B09');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
    }
}
