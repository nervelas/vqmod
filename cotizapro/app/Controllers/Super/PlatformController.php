<?php
declare(strict_types=1);

namespace App\Controllers\Super;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Backup;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Uploader;
use App\Models\Plan;
use App\Models\Setting;

final class PlatformController extends Controller
{
    public function dashboard(array $params = []): void
    {
        $this->super();
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $from = date('Y-m-01', strtotime("-{$i} month"));
            $to   = date('Y-m-t', strtotime("-{$i} month"));
            $months[] = [
                'label'  => \App\Controllers\Panel\DashboardController::monthLabel($from),
                'quotes' => (int) DB::value('SELECT COUNT(*) FROM quotes WHERE created_at BETWEEN ? AND ?', [$from . ' 00:00:00', $to . ' 23:59:59'], 0),
                'empresas' => (int) DB::value('SELECT COUNT(*) FROM companies WHERE created_at <= ?', [$to . ' 23:59:59'], 0),
            ];
        }
        $this->view('super/dashboard', [
            'title'     => 'Plataforma',
            'companies' => DB::all(
                'SELECT c.*, p.name AS plan_name,
                        (SELECT COUNT(*) FROM quotes q WHERE q.company_id = c.id AND q.created_at >= DATE_FORMAT(NOW(),"%Y-%m-01")) AS quotes_month,
                        (SELECT COUNT(*) FROM products pr WHERE pr.company_id = c.id) AS n_products
                 FROM companies c LEFT JOIN plans p ON p.id = c.plan_id ORDER BY c.name'
            ),
            'totals' => [
                'empresas'  => (int) DB::value('SELECT COUNT(*) FROM companies', [], 0),
                'activas'   => (int) DB::value('SELECT COUNT(*) FROM companies WHERE status = "activa"', [], 0),
                'usuarios'  => (int) DB::value('SELECT COUNT(*) FROM users WHERE role <> "superadmin"', [], 0),
                'productos' => (int) DB::value('SELECT COUNT(*) FROM products', [], 0),
                'quotes'    => (int) DB::value('SELECT COUNT(*) FROM quotes', [], 0),
                'monto'     => (float) DB::value('SELECT COALESCE(SUM(won_amount),0) FROM quotes WHERE status = "aprobada"', [], 0),
            ],
            'months'   => $months,
            'expiring' => DB::all('SELECT * FROM companies WHERE expires_at IS NOT NULL AND expires_at <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY expires_at'),
            'lastCron' => DB::one('SELECT * FROM cron_runs ORDER BY id DESC LIMIT 1'),
        ], 'layout/super');
    }

    public function settings(array $params = []): void
    {
        $this->super();
        if (Request::isPost()) {
            Csrf::verify();
            $pairs = [];
            foreach ([
                'platform_name', 'platform_tagline', 'contact_email', 'whatsapp', 'whatsapp_message',
                'phone', 'address', 'demo_slug', 'seo_title', 'seo_description',
                'smtp_host', 'smtp_port', 'smtp_user', 'smtp_secure', 'smtp_from', 'smtp_from_name',
            ] as $k) {
                $pairs[$k] = mb_substr(Request::str($k), 0, 400);
            }
            $pass = Request::raw('smtp_pass');
            if ($pass !== '') {
                $pairs['smtp_pass'] = $pass;
            }
            $f = Uploader::files('hero_image');
            if ($f) {
                $res = Uploader::image($f[0], 0, 'plataforma', 2200, 1400);
                if ($res) {
                    $pairs['hero_image'] = $res['path_webp'] ?: $res['path'];
                }
            }
            Setting::setMany($pairs);
            Audit::log('plataforma.ajustes', 'settings', 0, [], null);
            Flash::ok('Ajustes de la plataforma guardados.');
            redirect('/super/ajustes');
        }
        $this->view('super/settings', ['title' => 'Ajustes de la plataforma', 's' => Setting::all(),
            'companies' => DB::all('SELECT slug, name FROM companies ORDER BY name'),
            'cronToken' => (string) \App\Core\Config::get('cron_token', ''),
        ], 'layout/super');
    }

    public function landing(array $params = []): void
    {
        $this->super();
        if (Request::isPost()) {
            Csrf::verify();
            if (Request::str('what') === 'texts') {
                $pairs = [];
                foreach (['hero_title', 'hero_sub', 'hero_kicker', 'problem_title', 'problem_body', 'steps_title', 'plans_title', 'plans_sub', 'cta_title', 'cta_body'] as $k) {
                    $pairs['landing_' . $k] = mb_substr(Request::str($k), 0, 1200);
                }
                Setting::setMany($pairs);
                Flash::ok('Textos de la landing guardados.');
                redirect('/super/landing');
            }
            $section = Request::str('section');
            if (!in_array($section, ['problema', 'paso', 'beneficio', 'testimonio', 'faq'], true)) {
                Flash::error('Sección no válida.');
                redirect('/super/landing');
            }
            $data = [
                'section'  => $section,
                'sort'     => Request::int('sort'),
                'title'    => mb_substr(Request::str('title'), 0, 190) ?: null,
                'subtitle' => mb_substr(Request::str('subtitle'), 0, 255) ?: null,
                'body'     => mb_substr(Request::str('body'), 0, 3000) ?: null,
                'icon'     => mb_substr(Request::str('icon'), 0, 40) ?: null,
                'active'   => Request::bool('active') ? 1 : 0,
            ];
            $f = Uploader::files('image');
            if ($f) {
                $res = Uploader::image($f[0], 0, 'plataforma', 1200, 900);
                if ($res) {
                    $data['image'] = $res['path_webp'] ?: $res['path'];
                }
            }
            $id = Request::int('id');
            if ($id && DB::one('SELECT id FROM landing_blocks WHERE id = ?', [$id])) {
                DB::update('landing_blocks', $data, 'id = :id', ['id' => $id]);
            } else {
                DB::insert('landing_blocks', $data);
            }
            Flash::ok('Bloque guardado.');
            redirect('/super/landing');
        }
        $blocks = [];
        foreach (DB::all('SELECT * FROM landing_blocks ORDER BY section, sort, id') as $b) {
            $blocks[$b['section']][] = $b;
        }
        $this->view('super/landing', [
            'title'  => 'Landing de venta',
            'blocks' => $blocks,
            's'      => Setting::all(),
            'edit'   => Request::int('editar') ? DB::one('SELECT * FROM landing_blocks WHERE id = ?', [Request::int('editar')]) : null,
        ], 'layout/super');
    }

    public function landingDelete(array $params): void
    {
        $this->super();
        $this->guardPost();
        DB::delete('landing_blocks', 'id = :id', ['id' => (int) $params['id']]);
        Flash::ok('Bloque eliminado.');
        redirect('/super/landing');
    }

    public function plans(array $params = []): void
    {
        $this->super();
        if (Request::isPost()) {
            Csrf::verify();
            $name = mb_substr(Request::str('name'), 0, 80);
            if ($name === '') {
                Flash::error('Escriba el nombre del plan.');
                redirect('/super/planes');
            }
            $features = array_values(array_filter(array_map('trim', explode("\n", Request::str('features')))));
            $data = [
                'code'         => mb_substr(preg_replace('/[^a-z0-9\-]/', '', mb_strtolower(Request::str('code') ?: slugify($name))) ?: 'plan', 0, 40),
                'name'         => $name,
                'tagline'      => mb_substr(Request::str('tagline'), 0, 160) ?: null,
                'price_month'  => max(0, Request::float('price_month')),
                'price_year'   => max(0, Request::float('price_year')),
                'max_products' => max(0, Request::int('max_products')),
                'max_users'    => max(0, Request::int('max_users')),
                'max_quotes_month' => max(0, Request::int('max_quotes_month')),
                'features'     => json_encode($features, JSON_UNESCAPED_UNICODE),
                'highlight'    => Request::bool('highlight') ? 1 : 0,
                'sort'         => Request::int('sort'),
                'active'       => Request::bool('active') ? 1 : 0,
            ];
            $id = Request::int('id');
            if ($id && Plan::find($id)) {
                DB::update('plans', $data, 'id = :id', ['id' => $id]);
            } else {
                if (DB::one('SELECT id FROM plans WHERE code = ?', [$data['code']])) {
                    $data['code'] .= '-' . random_int(10, 99);
                }
                $data['created_at'] = nowSql();
                DB::insert('plans', $data);
            }
            Audit::log('plan.guardar', 'plan', $id, ['nombre' => $name], null);
            Flash::ok('Plan guardado.');
            redirect('/super/planes');
        }
        $this->view('super/plans', [
            'title' => 'Planes',
            'plans' => Plan::all(),
            'edit'  => Request::int('editar') ? Plan::find(Request::int('editar')) : null,
            'counts' => array_column(DB::all('SELECT plan_id, COUNT(*) n FROM companies GROUP BY plan_id'), 'n', 'plan_id'),
        ], 'layout/super');
    }

    public function planDelete(array $params): void
    {
        $this->super();
        $this->guardPost();
        $id = (int) $params['id'];
        if ((int) DB::value('SELECT COUNT(*) FROM companies WHERE plan_id = ?', [$id], 0) > 0) {
            Flash::error('No puede eliminar un plan que tiene empresas asignadas.');
            redirect('/super/planes');
        }
        DB::delete('plans', 'id = :id', ['id' => $id]);
        Flash::ok('Plan eliminado.');
        redirect('/super/planes');
    }

    public function backups(array $params = []): void
    {
        $this->super();
        $this->view('super/backups', [
            'title'   => 'Respaldos',
            'rows'    => Backup::list(),
            'dirSize' => self::dirSize(Backup::dir()),
        ], 'layout/super');
    }

    public function backupCreate(array $params = []): void
    {
        $this->super();
        $this->guardPost();
        @set_time_limit(300);
        $res = Backup::create('manual');
        if (!$res) {
            Flash::error('No se pudo crear el respaldo. Revise permisos de /storage/backups.');
        } else {
            Audit::log('respaldo.crear', 'backup', 0, ['archivo' => $res['name']], null);
            Flash::ok('Respaldo creado: ' . $res['name'] . ' (' . self::human($res['size']) . ').');
        }
        redirect('/super/respaldos');
    }

    public function backupDownload(array $params): void
    {
        $this->super();
        $name = basename((string) $params['name']);
        $file = Backup::dir() . '/' . $name;
        if (!is_file($file) || !preg_match('/^cotizapro-[\d\-]+\.sql(\.gz)?$/', $name)) {
            ErrorHandler::render(404);
        }
        Audit::log('respaldo.descargar', 'backup', 0, ['archivo' => $name], null);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    public function backupDelete(array $params = []): void
    {
        $this->super();
        $this->guardPost();
        $name = basename(Request::str('name'));
        if (preg_match('/^cotizapro-[\d\-]+\.sql(\.gz)?$/', $name)) {
            $f = Backup::dir() . '/' . $name;
            if (is_file($f)) {
                @unlink($f);
            }
            DB::delete('backups', 'filename = :n', ['n' => $name]);
            Flash::ok('Respaldo eliminado.');
        }
        redirect('/super/respaldos');
    }

    public function audit(array $params = []): void
    {
        $this->super();
        [$page, $per, $offset] = Request::page(50);
        $total = (int) DB::value('SELECT COUNT(*) FROM audit_log', [], 0);
        $this->view('super/audit', [
            'title' => 'Bitácora global',
            'rows'  => DB::all('SELECT a.*, c.name AS company_name FROM audit_log a LEFT JOIN companies c ON c.id = a.company_id ORDER BY a.id DESC LIMIT ' . (int) $per . ' OFFSET ' . (int) $offset),
            'total' => $total,
            'page'  => $page,
            'pages' => (int) ceil($total / $per),
        ], 'layout/super');
    }

    public static function human(int $bytes): string
    {
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, $i > 1 ? 1 : 0) . ' ' . $u[$i];
    }

    private static function dirSize(string $dir): int
    {
        $s = 0;
        foreach (glob($dir . '/*') ?: [] as $f) {
            $s += is_file($f) ? (int) filesize($f) : 0;
        }
        return $s;
    }
}
