<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Backup;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Flash;
use App\Core\Img;
use App\Core\Request;
use App\Core\Uploader;
use App\Models\Company;
use App\Models\Setting;

final class SettingsController extends Controller
{
    public function index(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        if (Request::isPost()) {
            Csrf::verify();
            $theme = Request::str('theme');
            if (!isset(Company::THEMES[$theme]) && $theme !== 'personalizado') {
                $theme = 'acero';
            }
            $base = Company::THEMES[$theme] ?? Company::THEMES['acero'];
            $data = [
                'name'            => mb_substr(Request::str('name'), 0, 140) ?: (string) $c['name'],
                'legal_name'      => mb_substr(Request::str('legal_name'), 0, 180) ?: null,
                'nit'             => mb_substr(Request::str('nit'), 0, 30) ?: null,
                'tagline'         => mb_substr(Request::str('tagline'), 0, 190) ?: null,
                'about'           => mb_substr(Request::str('about'), 0, 6000) ?: null,
                'years_experience' => max(0, min(200, Request::int('years_experience'))),
                'email'           => Request::email('email') ?: null,
                'phone'           => mb_substr(Request::str('phone'), 0, 40) ?: null,
                'whatsapp'        => preg_replace('/[^0-9]/', '', Request::str('whatsapp')) ?: null,
                'address'         => mb_substr(Request::str('address'), 0, 220) ?: null,
                'city'            => mb_substr(Request::str('city'), 0, 90) ?: null,
                'maps_url'        => filter_var(Request::str('maps_url'), FILTER_VALIDATE_URL) ? mb_substr(Request::str('maps_url'), 0, 255) : null,
                'theme'           => $theme === 'personalizado' ? 'acero' : $theme,
                'color_accent'    => Company::hex(Request::str('color_accent'), $base['accent']),
                'color_ink'       => Company::hex(Request::str('color_ink'), $base['ink']),
                'color_paper'     => Company::hex(Request::str('color_paper'), $base['paper']),
                'currency_symbol' => mb_substr(Request::str('currency_symbol') ?: 'Q', 0, 6),
                'tax_rate'        => max(0, min(100, Request::float('tax_rate'))),
                'tax_label'       => mb_substr(Request::str('tax_label') ?: 'IVA', 0, 20),
                'price_visibility' => in_array(Request::str('price_visibility'), ['publico', 'clientes', 'oculto'], true) ? Request::str('price_visibility') : 'oculto',
                'quote_prefix'    => mb_substr(preg_replace('/[^A-Za-z0-9\-]/', '', Request::str('quote_prefix')) ?: 'COT', 0, 16),
                'quote_pad'       => max(3, min(8, Request::int('quote_pad', 4))),
                'validity_days'   => max(1, min(365, Request::int('validity_days', 15))),
                'delivery_terms'  => mb_substr(Request::str('delivery_terms'), 0, 190) ?: null,
                'payment_terms'   => mb_substr(Request::str('payment_terms'), 0, 190) ?: null,
                'pdf_terms'       => mb_substr(Request::str('pdf_terms'), 0, 4000) ?: null,
                'pdf_footer'      => mb_substr(Request::str('pdf_footer'), 0, 255) ?: null,
                'smtp_host'       => mb_substr(Request::str('smtp_host'), 0, 150) ?: null,
                'smtp_port'       => Request::int('smtp_port') ?: null,
                'smtp_user'       => mb_substr(Request::str('smtp_user'), 0, 150) ?: null,
                'smtp_secure'     => in_array(Request::str('smtp_secure'), ['tls', 'ssl', 'ninguna'], true) ? Request::str('smtp_secure') : 'tls',
                'smtp_from'       => Request::email('smtp_from') ?: null,
                'smtp_from_name'  => mb_substr(Request::str('smtp_from_name'), 0, 150) ?: null,
                'reminder_days_seller' => max(0, min(60, Request::int('reminder_days_seller'))),
                'reminder_days_client' => max(0, min(60, Request::int('reminder_days_client'))),
                'assign_mode'     => Request::str('assign_mode') === 'manual' ? 'manual' : 'rotativo',
                'seo_title'       => mb_substr(Request::str('seo_title'), 0, 190) ?: null,
                'seo_description' => mb_substr(Request::str('seo_description'), 0, 300) ?: null,
            ];
            $smtpPass = Request::raw('smtp_pass');
            if ($smtpPass !== '') {
                $data['smtp_pass'] = $smtpPass;
            }
            foreach ([['logo', 'marca', 600, 400], ['hero_image', 'marca', 2200, 1400], ['og_image', 'marca', 1200, 630]] as [$field, $kind, $mw, $mh]) {
                $f = Uploader::files($field);
                if (!$f) {
                    continue;
                }
                $res = Uploader::image($f[0], $kind, $mw, $mh);
                if ($res) {
                    $data[$field] = $field === 'logo' ? $res['path'] : ($res['path_webp'] ?: $res['path']);
                }
            }
            Company::save($data);
            Setting::set('app_name', mb_substr(Request::str('app_name') ?: (string) $data['name'], 0, 80));

            // Regenera los iconos PWA cuando cambia el logo.
            if (isset($data['logo'])) {
                $src = STORAGE_PATH . '/uploads/' . $data['logo'];
                if (is_file($src)) {
                    Img::pwaIcons($src, BASE_PATH . '/assets/img/icons', [72, 96, 128, 144, 152, 192, 384, 512], (string) $data['color_ink']);
                }
            }
            Audit::log('empresa.ajustes', 'company', Company::ID, ['tema' => $data['theme']]);
            Flash::ok('Ajustes guardados.');
            redirect('/panel/ajustes');
        }

        $this->view('panel/settings', [
            'title'  => 'Configuración de la empresa',
            'c'      => Company::get(),
            'themes' => Company::THEMES,
            'appName' => Setting::get('app_name', 'CotizaPro B2B'),
            'stats'  => [
                'products' => (int) DB::value('SELECT COUNT(*) FROM products', [], 0),
                'users'    => (int) DB::value('SELECT COUNT(*) FROM users', [], 0),
                'quotes'   => (int) DB::value('SELECT COUNT(*) FROM quotes WHERE created_at >= DATE_FORMAT(NOW(),"%Y-%m-01")', [], 0),
            ],
        ], 'layout/panel');
    }

    public function audit(array $params = []): void
    {
        $this->panel(Auth::ROLE_ADMIN);
        [$page, $per, $offset] = Request::page(40);
        $total = (int) DB::value('SELECT COUNT(*) FROM audit_log', [], 0);
        $this->view('panel/audit', [
            'title' => 'Bitácora de auditoría',
            'rows'  => DB::all('SELECT * FROM audit_log ORDER BY id DESC LIMIT ' . (int) $per . ' OFFSET ' . (int) $offset),
            'total' => $total,
            'page'  => $page,
            'pages' => (int) ceil($total / $per),
        ], 'layout/panel');
    }

    // ------------------------------------------------------------- respaldos
    public function backups(array $params = []): void
    {
        $this->panel(Auth::ROLE_ADMIN);
        $this->view('panel/backups', [
            'title'   => 'Respaldos de la base de datos',
            'rows'    => Backup::list(),
            'dirSize' => self::dirSize(Backup::dir()),
        ], 'layout/panel');
    }

    public function backupCreate(array $params = []): void
    {
        $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        @set_time_limit(300);
        $res = Backup::create('manual');
        if (!$res) {
            Flash::error('No se pudo crear el respaldo. Revise permisos de /storage/backups.');
        } else {
            Audit::log('respaldo.crear', 'backup', 0, ['archivo' => $res['name']]);
            Flash::ok('Respaldo creado: ' . $res['name'] . ' (' . self::human($res['size']) . ').');
        }
        redirect('/panel/respaldos');
    }

    public function backupDownload(array $params): void
    {
        $this->panel(Auth::ROLE_ADMIN);
        $name = basename((string) $params['name']);
        $file = Backup::dir() . '/' . $name;
        if (!is_file($file) || !preg_match('/^cotizapro-[\d\-]+\.sql(\.gz)?$/', $name)) {
            ErrorHandler::render(404);
        }
        Audit::log('respaldo.descargar', 'backup', 0, ['archivo' => $name]);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    public function backupDelete(array $params = []): void
    {
        $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        $name = basename(Request::str('name'));
        if (preg_match('/^cotizapro-[\d\-]+\.sql(\.gz)?$/', $name)) {
            $f = Backup::dir() . '/' . $name;
            if (is_file($f)) {
                @unlink($f);
            }
            DB::delete('backups', 'filename = :n', ['n' => $name]);
            Audit::log('respaldo.eliminar', 'backup', 0, ['archivo' => $name]);
            Flash::ok('Respaldo eliminado.');
        }
        redirect('/panel/respaldos');
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
