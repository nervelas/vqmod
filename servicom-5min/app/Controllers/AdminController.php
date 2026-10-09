<?php
declare(strict_types=1);
namespace S5\Controllers;

use S5\Core\Auth;
use S5\Core\Config;
use S5\Core\Crypto;
use S5\Core\Csrf;
use S5\Core\Db;
use S5\Core\Http;
use S5\Core\Log;
use S5\Core\Router;
use S5\Core\Sanitize;
use S5\Core\Session;
use S5\Core\Settings;
use S5\Core\Totp;
use S5\Core\View;
use S5\Services\AiBudget;
use S5\Services\Brief;
use S5\Services\Cron;
use S5\Services\DemoData;
use S5\Services\Diagnostics;
use S5\Services\Files;
use S5\Services\Hosts;
use S5\Services\Lifecycle;
use S5\Services\Orders;
use S5\Services\Pipeline;
use S5\Services\Slugs;

/** Panel del dueño. Todas las acciones exigen sesión y CSRF. */
final class AdminController
{
    public static function routes(Router $r): void
    {
        $r->get('/admin', fn() => self::dashboard());
        $r->get('/admin/login', fn() => self::loginForm());
        $r->post('/admin/login', fn() => self::loginPost());
        $r->post('/admin/logout', fn() => self::logout());
        $r->get('/admin/pedidos', fn() => self::orders());
        $r->get('/admin/pedido/{id}', fn($p) => self::order((int) $p['id']));
        $r->post('/admin/pedido/{id}/accion', fn($p) => self::action((int) $p['id']));
        $r->get('/admin/demos', fn() => self::demos());
        $r->post('/admin/demos', fn() => self::demoCreate());
        $r->get('/admin/correos', fn() => self::emails());
        $r->get('/admin/renovaciones', fn() => self::renewals());
        $r->get('/admin/ia', fn() => self::ai());
        $r->get('/admin/ajustes', fn() => self::settings());
        $r->post('/admin/ajustes', fn() => self::settingsSave());
        $r->get('/admin/hostings', fn() => self::hosts());
        $r->post('/admin/hostings', fn() => self::hostSave());
        $r->get('/admin/bitacora', fn() => self::audit());
        $r->get('/admin/diagnostico', fn() => self::diagnostics());
        $r->post('/admin/diagnostico', fn() => self::diagnosticsAction());
        $r->get('/admin/cuenta', fn() => self::account());
        $r->post('/admin/cuenta', fn() => self::accountSave());
        $r->post('/admin/alerta/{id}', fn($p) => self::resolveAlert((int) $p['id']));
        $r->get('/admin/archivo/{id}', fn($p) => FileController::admin($p));
        $r->get('/admin/construir/{id}', fn($p) => self::buildTick((int) $p['id']));
    }

    // ---------------------------------------------------------------- utilidades
    private static function require(): array
    {
        $u = Auth::user();
        if (!$u) {
            Http::redirect('/admin/login');
        }
        return $u;
    }

    private static function post(): void
    {
        if (!Csrf::check()) {
            http_response_code(403);
            exit('Sesión expirada. Recargue la página.');
        }
    }

    private static function render(string $view, array $vars = []): void
    {
        $vars += ['user' => Auth::user(), 'flash' => self::takeFlash(), 'alertCount' => self::alertCount()];
        View::render('admin/' . $view, $vars, 'admin/layout');
    }

    private static function flash(string $msg, string $type = 'ok'): void
    {
        Session::start();
        $_SESSION['flash'] = [$type, $msg];
    }

    private static function takeFlash(): ?array
    {
        Session::start();
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }

    private static function alertCount(): int
    {
        try {
            return (int) Db::val('SELECT COUNT(*) FROM ' . Db::t('alerts') . ' WHERE resolved=0');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // ---------------------------------------------------------------- acceso
    private static function loginForm(): void
    {
        if (Auth::check()) {
            Http::redirect('/admin');
        }
        Session::start();
        View::render('admin/login', ['need2fa' => !empty($_SESSION['pending_uid']), 'error' => self::takeFlash()[1] ?? null], 'admin/layout_bare');
    }

    private static function loginPost(): void
    {
        self::post();
        Session::start();
        if (!empty($_SESSION['pending_uid']) && isset($_POST['code'])) {
            $r = Auth::verify2fa((string) $_POST['code']);
        } else {
            unset($_SESSION['pending_uid']);
            $r = Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''), '');
        }
        if ($r['ok']) {
            Http::redirect('/admin');
        }
        if (!empty($r['need2fa'])) {
            View::render('admin/login', ['need2fa' => true, 'error' => $r['error'] ?? null], 'admin/layout_bare');
            return;
        }
        self::flash((string) ($r['error'] ?? 'No se pudo iniciar sesión.'), 'err');
        Http::redirect('/admin/login');
    }

    private static function logout(): void
    {
        self::post();
        Auth::logout();
        Http::redirect('/admin/login');
    }

    // ---------------------------------------------------------------- tablero
    private static function dashboard(): void
    {
        self::require();
        $counts = [];
        foreach (Db::all('SELECT status, COUNT(*) c FROM ' . Db::t('orders') . ' GROUP BY status') as $r) {
            $counts[$r['status']] = (int) $r['c'];
        }
        $pay = Db::all('SELECT id, business_name, fqdn, plan, created_at FROM ' . Db::t('orders') . ' WHERE status=? ORDER BY updated_at', [Orders::ST_PAGO]);
        $alerts = Db::all('SELECT * FROM ' . Db::t('alerts') . ' WHERE resolved=0 ORDER BY id DESC LIMIT 30');
        $soon = Db::all('SELECT id, business_name, fqdn, renewal_at FROM ' . Db::t('orders') . ' WHERE status IN (?,?) AND renewal_at IS NOT NULL AND renewal_at<=? ORDER BY renewal_at LIMIT 20', [Orders::ST_PUBLICADA, Orders::ST_VENCIDA, gmdate('Y-m-d', time() + 30 * 86400)]);
        $prep = Db::all('SELECT id, business_name, fqdn FROM ' . Db::t('orders') . ' WHERE status=? ORDER BY updated_at', [Orders::ST_PREPARANDO]);
        self::render('dashboard', compact('counts', 'pay', 'alerts', 'soon', 'prep') + ['ai' => AiBudget::resumen(), 'hostCounts' => Hosts::counts(), 'hosts' => Hosts::all()]);
    }

    private static function resolveAlert(int $id): void
    {
        self::require();
        self::post();
        Db::update('alerts', ['resolved' => 1], 'id=?', [$id]);
        Http::redirect($_SERVER['HTTP_REFERER'] ?? '/admin');
    }

    // ---------------------------------------------------------------- pedidos
    private static function orders(): void
    {
        self::require();
        $status = (string) ($_GET['estado'] ?? '');
        $q = Sanitize::text($_GET['q'] ?? '', 80);
        $plan = (string) ($_GET['plan'] ?? '');
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $per = 30;
        $where = ['status<>?'];
        $params = [Orders::ST_ELIMINADA];
        if ($status === 'todos') {
            $where = ['1=1'];
            $params = [];
        } elseif ($status !== '' && isset(Orders::LABELS[$status])) {
            $where = ['status=?'];
            $params = [$status];
        }
        if (in_array($plan, ['info', 'tienda'], true)) {
            $where[] = 'plan=?';
            $params[] = $plan;
        }
        if ($q !== '') {
            $where[] = '(business_name LIKE ? OR slug LIKE ? OR fqdn LIKE ? OR client_email LIKE ? OR client_phone LIKE ? OR domain_assigned LIKE ?)';
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $total = (int) Db::val('SELECT COUNT(*) FROM ' . Db::t('orders') . ' WHERE ' . $w, $params);
        $rows = Db::all('SELECT id,status,plan,business_name,slug,fqdn,host_id,client_email,client_phone,is_demo,renewal_at,created_at,updated_at FROM ' . Db::t('orders') . ' WHERE ' . $w . ' ORDER BY updated_at DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $params);
        self::render('orders', ['rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per, 'status' => $status, 'q' => $q, 'plan' => $plan, 'hostNames' => array_column(Hosts::all(), 'name', 'id')]);
    }

    private static function order(int $id): void
    {
        self::require();
        $o = Orders::byId($id);
        if (!$o) {
            http_response_code(404);
            self::render('notfound');
            return;
        }
        $b = Orders::data($o);
        $analysis = $o['analysis'] ? json_decode((string) $o['analysis'], true) : null;
        $proof = $o['pay_file_id'] ? Db::one('SELECT * FROM ' . Db::t('files') . ' WHERE id=?', [$o['pay_file_id']]) : null;
        $steps = Db::all('SELECT * FROM ' . Db::t('build_steps') . ' WHERE order_id=? ORDER BY id', [$id]);
        $log = Db::all('SELECT * FROM ' . Db::t('audit_log') . ' WHERE order_id=? ORDER BY id DESC LIMIT 40', [$id]);
        $ai = Db::all('SELECT kind, model, tokens_in, tokens_out, cost_usd, ok, created_at FROM ' . Db::t('ai_usage') . ' WHERE order_id=? ORDER BY id DESC LIMIT 20', [$id]);
        self::render('order', [
            'o' => $o, 'b' => $b, 'analysis' => $analysis, 'proof' => $proof, 'steps' => $steps, 'log' => $log, 'ai' => $ai,
            'hosts' => Hosts::all(true), 'previewUrl' => Orders::previewUrl($o, true), 'siteUrl' => Orders::previewUrl($o, false),
            'total' => Orders::total($o), 'qa' => $o['qa_result'] ? json_decode((string) $o['qa_result'], true) : null,
            'texts' => $o['texts'] ? json_decode((string) $o['texts'], true) : null,
        ]);
    }

    private static function action(int $id): void
    {
        self::require();
        self::post();
        $o = Orders::byId($id);
        if (!$o) {
            Http::redirect('/admin/pedidos');
        }
        $a = (string) ($_POST['accion'] ?? '');
        try {
            switch ($a) {
                case 'aprobar':
                    $r = Lifecycle::approve($id);
                    self::flash('Pago aprobado y web publicada.' . ($r['email_sent'] ? ' Se envió el correo al cliente.' : ' El cliente no dejó correo: entregue este enlace para definir su contraseña: ' . $r['reset_url']));
                    break;
                case 'rechazar':
                    Lifecycle::reject($id, (string) ($_POST['motivo'] ?? ''));
                    self::flash('Pago rechazado. Se avisó al cliente.');
                    break;
                case 'regenerar':
                    if (!$o['fqdn']) { throw new \RuntimeException('Aún no tiene sitio construido.'); }
                    Pipeline::regenerate($id);
                    Orders::set($id, ['regen_count' => max(0, (int) $o['regen_count'])]);
                    Pipeline::tick($id, 5);
                    self::flash('Regeneración iniciada.');
                    Http::redirect('/admin/pedido/' . $id);
                    break;
                case 'reanudar':
                    Pipeline::resume($id);
                    Pipeline::tick($id, 8);
                    self::flash('Construcción reanudada.');
                    break;
                case 'iniciar':
                    $hid = (int) ($_POST['host_id'] ?? 0);
                    if ($hid && Hosts::get($hid)) { Orders::set($id, ['host_id' => $hid]); }
                    Pipeline::start($id);
                    Pipeline::tick($id, 8);
                    self::flash('Construcción iniciada.');
                    break;
                case 'host':
                    if ($o['fqdn']) { throw new \RuntimeException('El hosting solo se puede cambiar antes de construir.'); }
                    $hid = (int) ($_POST['host_id'] ?? 0);
                    if (!$hid || !Hosts::get($hid)) { throw new \RuntimeException('Hosting no válido.'); }
                    Orders::set($id, ['host_id' => $hid]);
                    self::flash('Hosting asignado.');
                    break;
                case 'borrar':
                    if (!Lifecycle::deletePreview($id, 'manual por el dueño')) { throw new \RuntimeException('No se puede borrar: ' . (Lifecycle::deletable($o) ?? 'verifique el estado') . '.'); }
                    self::flash('Vista previa eliminada con todos sus archivos.');
                    Http::redirect('/admin/pedidos');
                    break;
                case 'dominio':
                    $res = Lifecycle::assignDomain($id, (string) ($_POST['dominio'] ?? ''));
                    self::flash('Dominio asignado. ' . json_encode($res, JSON_UNESCAPED_UNICODE));
                    break;
                case 'renovado':
                    Lifecycle::renewed($id);
                    self::flash('Marcado como renovado (+1 año).');
                    break;
                case 'suspender':
                    Lifecycle::suspend($id);
                    self::flash('Sitio suspendido.');
                    break;
                case 'reactivar':
                    Lifecycle::reactivate($id);
                    self::flash('Sitio reactivado.');
                    break;
                case 'tarjeta':
                    Orders::set($id, ['card_extra' => !empty($_POST['valor']) ? 1 : 0]);
                    self::flash('Extra de tarjeta actualizado.');
                    break;
                default:
                    throw new \RuntimeException('Acción desconocida.');
            }
            Log::audit('admin_' . $a, '', $id);
        } catch (\Throwable $e) {
            self::flash($e->getMessage(), 'err');
        }
        Http::redirect('/admin/pedido/' . $id);
    }

    /** Avance manual/AJAX de una construcción desde el panel. */
    private static function buildTick(int $id): void
    {
        self::require();
        Http::json(['ok' => true] + Pipeline::tick($id, 20));
    }

    // ---------------------------------------------------------------- demos
    private static function demos(): void
    {
        self::require();
        $rows = Db::all('SELECT id,business_name,fqdn,status,plan,data FROM ' . Db::t('orders') . ' WHERE is_demo=1 AND status<>? ORDER BY id DESC', [Orders::ST_ELIMINADA]);
        self::render('demos', ['rows' => $rows, 'hosts' => Hosts::all(true)]);
    }

    private static function demoCreate(): void
    {
        self::require();
        self::post();
        $plan = ($_POST['plan'] ?? '') === 'tienda' ? 'tienda' : 'info';
        $rubro = in_array($_POST['rubro'] ?? '', Brief::RUBROS, true) ? $_POST['rubro'] : 'otro';
        $style = Sanitize::intRange($_POST['estilo'] ?? 1, 1, 5);
        $hid = (int) ($_POST['host_id'] ?? 0) ?: Hosts::defaultId();
        if (!$hid) {
            self::flash('Configure primero un hosting.', 'err');
            Http::redirect('/admin/demos');
        }
        $o = Orders::create($plan);
        $b = DemoData::brief($plan, $rubro, $style);
        Orders::saveData((int) $o['id'], $b);
        Orders::set((int) $o['id'], ['is_demo' => 1, 'host_id' => $hid, 'expires_at' => null]);
        Pipeline::start((int) $o['id'], ['demo' => true]);
        Pipeline::tick((int) $o['id'], 8);
        Log::audit('demo_creada', "$plan/$rubro/$style", (int) $o['id']);
        Http::redirect('/admin/pedido/' . $o['id']);
    }

    // ---------------------------------------------------------------- listados
    private static function emails(): void
    {
        self::require();
        $rows = Db::all('SELECT id,business_name,fqdn,client_email,data,domain_requested,emails_requested,published_at,status FROM ' . Db::t('orders') . ' WHERE status IN (?,?,?) ORDER BY published_at DESC', [Orders::ST_PUBLICADA, Orders::ST_VENCIDA, Orders::ST_SUSPENDIDA]);
        self::render('emails', ['rows' => $rows]);
    }

    private static function renewals(): void
    {
        self::require();
        $rows = Db::all('SELECT id,business_name,fqdn,status,renewal_at,client_email,client_phone,plan FROM ' . Db::t('orders') . ' WHERE renewal_at IS NOT NULL AND status IN (?,?,?) ORDER BY renewal_at', [Orders::ST_PUBLICADA, Orders::ST_VENCIDA, Orders::ST_SUSPENDIDA]);
        self::render('renewals', ['rows' => $rows]);
    }

    private static function ai(): void
    {
        self::require();
        $recent = Db::all('SELECT * FROM ' . Db::t('ai_usage') . ' ORDER BY id DESC LIMIT 60');
        self::render('ai', ['sum' => AiBudget::resumen(), 'recent' => $recent, 'capDay' => Settings::float('ai_cap_day', 1.0), 'capTotal' => Settings::float('ai_cap_total', 10.0)]);
    }

    private static function audit(): void
    {
        self::require();
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $rows = Db::all('SELECT * FROM ' . Db::t('audit_log') . ' ORDER BY id DESC LIMIT 100 OFFSET ' . (($page - 1) * 100));
        self::render('audit', ['rows' => $rows, 'page' => $page]);
    }

    // ---------------------------------------------------------------- ajustes
    private const FIELDS = [
        // clave => [tipo, secreto]
        'precio_info' => ['num', 0], 'precio_tienda' => ['num', 0], 'precio_tarjeta' => ['num', 0],
        'banco_nombre' => ['text', 0], 'banco_cuenta' => ['text', 0], 'banco_titular' => ['text', 0], 'banco_tipo' => ['text', 0],
        'wa_servicom' => ['phone', 0], 'owner_email' => ['email', 0], 'footer_credit' => ['text', 0],
        'dominio_base' => ['domain', 0], 'portal_sub' => ['sub', 0], 'reservados' => ['csv', 0], 'webs_path' => ['path', 0], 'force_scheme' => ['scheme', 0],
        'ai_key' => ['secret', 1], 'ai_model_main' => ['model', 0], 'ai_model_fallback' => ['model', 0], 'ai_model_extract' => ['model', 0],
        'ai_cap_day' => ['num', 0], 'ai_cap_total' => ['num', 0], 'pres_max_mb' => ['int', 0],
        'ai_price_in_claude-sonnet-5-5' => ['num', 0], 'ai_price_out_claude-sonnet-5-5' => ['num', 0], 'ai_price_in_claude-haiku-5-5' => ['num', 0], 'ai_price_out_claude-haiku-5-5' => ['num', 0],
        'smtp_host' => ['text', 0], 'smtp_port' => ['int', 0], 'smtp_user' => ['text', 0], 'smtp_pass' => ['secret', 1], 'smtp_secure' => ['smtp', 0], 'smtp_from' => ['email', 0], 'smtp_from_name' => ['text', 0],
        'preview_days' => ['int', 0], 'max_regen' => ['int', 0], 'max_drafts_ip_day' => ['int', 0], 'max_analysis_ip_day' => ['int', 0], 'min_fill_seconds' => ['int', 0],
        'renewal_notice_days' => ['int', 0], 'notify_client_renewal' => ['bool', 0], 'ssl_wait_min' => ['int', 0], 'trust_proxy' => ['bool', 0],
    ];

    private static function settings(): void
    {
        self::require();
        $vals = [];
        foreach (self::FIELDS as $k => [$t, $sec]) {
            $vals[$k] = $sec ? (Settings::has($k) ? '__guardado__' : '') : (string) Settings::get($k, '');
        }
        self::render('settings', ['vals' => $vals, 'defaults' => Settings::defaults()]);
    }

    private static function settingsSave(): void
    {
        self::require();
        self::post();
        $errors = [];
        foreach (self::FIELDS as $k => [$t, $sec]) {
            $fk = str_replace(['.', '-'], '_', $k);
            if (!array_key_exists($fk, $_POST)) {
                if ($t === 'bool') { Settings::set($k, '0'); }
                continue;
            }
            $v = trim((string) $_POST[$fk]);
            switch ($t) {
                case 'num': $v = is_numeric($v) ? (string) max(0, (float) $v) : ''; break;
                case 'int': $v = ctype_digit($v) ? $v : ''; break;
                case 'bool': $v = $v === '1' || $v === 'on' ? '1' : '0'; break;
                case 'text': $v = Sanitize::text($v, 190); break;
                case 'email': $v = $v === '' ? '' : Sanitize::email($v); break;
                case 'phone': $v = Sanitize::phoneDigits($v, 15); break;
                case 'domain': $v = strtolower($v); if (!preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,24}$/', $v)) { $errors[] = 'Dominio base no válido.'; continue 2; } break;
                case 'sub': $v = strtolower($v); if (!preg_match('/^[a-z0-9-]{1,40}$/', $v)) { $errors[] = 'Subdominio del portal no válido.'; continue 2; } break;
                case 'csv': $v = implode(',', array_filter(array_map(fn($x) => Sanitize::slug($x, 40), explode(',', $v)))); break;
                case 'path': $v = rtrim($v, '/'); if ($v !== '' && (!str_starts_with($v, '/') || str_contains($v, '..'))) { $errors[] = 'La ruta de las webs debe ser absoluta.'; continue 2; } break;
                case 'scheme': $v = in_array($v, ['http', 'https'], true) ? $v : ''; break;
                case 'model': $v = preg_match('/^[a-z0-9._-]{3,60}$/i', $v) ? $v : ''; break;
                case 'smtp': $v = in_array($v, ['tls', 'ssl', 'none'], true) ? $v : 'tls'; break;
                case 'secret':
                    if ($v === '' || $v === '__guardado__') { continue 2; }
                    break;
            }
            if ($t === 'num' && $v === '' && in_array($k, ['precio_info', 'precio_tienda', 'precio_tarjeta'], true)) { $errors[] = 'Precio no válido.'; continue; }
            Settings::set($k, $v, (bool) $sec);
        }
        // protección: el dominio base nunca puede quedar vacío ni la lista de reservados sin lo esencial
        $res = array_filter(explode(',', (string) Settings::get('reservados', '')));
        foreach (['www', 'mail', 'webmail', 'cpanel', 'whm', 'ftp', 'smtp', 'imap', 'pop', 'ns1', 'ns2', 'autodiscover', 'autoconfig', 'admin', 'panel', 'portal', 'api', 'crear', 'cpw', 'ctv', 'demo', 'test', 'dev', 'staging', 'blog', 'tienda', 'soporte'] as $must) {
            if (!in_array($must, $res, true)) { $res[] = $must; }
        }
        Settings::set('reservados', implode(',', array_unique($res)));
        Log::audit('ajustes_guardados');
        self::flash($errors ? implode(' ', $errors) : 'Ajustes guardados.', $errors ? 'err' : 'ok');
        Http::redirect('/admin/ajustes');
    }

    // ---------------------------------------------------------------- hostings
    private static function hosts(): void
    {
        self::require();
        $rows = Hosts::all();
        foreach ($rows as &$h) {
            unset($h['cfg']['token'], $h['cfg']['agent_secret']);
        }
        unset($h);
        self::render('hosts', ['rows' => $rows, 'counts' => Hosts::counts(), 'defaultId' => Hosts::defaultId(), 'webs' => Hosts::defaultWebsPath()]);
    }

    private static function hostSave(): void
    {
        self::require();
        self::post();
        $act = (string) ($_POST['accion'] ?? 'guardar');
        try {
            if ($act === 'predeterminado') {
                Settings::set('default_host', (string) (int) $_POST['id']);
                self::flash('Hosting predeterminado actualizado.');
                Http::redirect('/admin/hostings');
            }
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            $old = $id ? Hosts::get($id) : null;
            if ($act === 'activar' && $old) {
                Hosts::save($id, $old['name'], $old['kind'], $old['cfg'], empty($_POST['valor']) ? false : true);
                self::flash('Hosting actualizado.');
                Http::redirect('/admin/hostings');
            }
            $kind = in_array($_POST['kind'] ?? '', ['cpanel', 'agent'], true) ? $_POST['kind'] : 'cpanel';
            $name = Sanitize::text($_POST['name'] ?? '', 120);
            if ($name === '') { throw new \RuntimeException('Escriba un nombre para el hosting.'); }
            $cfg = $old['cfg'] ?? [];
            $keep = fn(string $k, string $v) => ($v === '' || $v === '__guardado__') ? ($cfg[$k] ?? '') : $v;
            $cfg['domain_root'] = strtolower(Sanitize::text($_POST['domain_root'] ?? Settings::baseDomain(), 120)) ?: Settings::baseDomain();
            $webs = rtrim(Sanitize::text($_POST['webs_path'] ?? '', 300), '/');
            if ($webs !== '' && (!str_starts_with($webs, '/') || str_contains($webs, '..'))) { throw new \RuntimeException('La ruta debe ser absoluta.'); }
            $cfg['webs_path'] = $webs;
            $cfg['verify_ssl'] = !empty($_POST['verify_ssl']);
            if ($kind === 'cpanel') {
                $cfg['host'] = Sanitize::text($_POST['host'] ?? 'localhost', 190) ?: 'localhost';
                $cfg['port'] = (int) ($_POST['port'] ?? 2083) ?: 2083;
                $cfg['user'] = Sanitize::text($_POST['user'] ?? '', 60);
                $cfg['token'] = $keep('token', trim((string) ($_POST['token'] ?? '')));
                $cfg['home'] = rtrim(Sanitize::text($_POST['home'] ?? '', 200), '/');
            } else {
                $cfg['agent_url'] = Sanitize::url($_POST['agent_url'] ?? '');
                $cfg['agent_secret'] = $keep('agent_secret', trim((string) ($_POST['agent_secret'] ?? '')));
                if ($cfg['agent_url'] === '' || strlen((string) $cfg['agent_secret']) < 32) { throw new \RuntimeException('Indique la URL del agente y un secreto de al menos 32 caracteres.'); }
            }
            $hid = Hosts::save($id, $name, $kind, $cfg, true);
            if (!Settings::has('default_host')) { Settings::set('default_host', (string) $hid); }
            Log::audit('hosting_guardado', $name);
            self::flash('Hosting guardado.');
        } catch (\Throwable $e) {
            self::flash($e->getMessage(), 'err');
        }
        Http::redirect('/admin/hostings');
    }

    // ---------------------------------------------------------------- diagnóstico
    private static function diagnostics(): void
    {
        self::require();
        self::render('diagnostics', ['d' => Diagnostics::run(), 'test' => null]);
    }

    private static function diagnosticsAction(): void
    {
        self::require();
        self::post();
        $r = null;
        if (($_POST['accion'] ?? '') === 'ia') {
            $r = Diagnostics::aiTest();
        } elseif (($_POST['accion'] ?? '') === 'cron') {
            $r = ['ok' => true, 'msg' => json_encode(Cron::run('manual'), JSON_UNESCAPED_UNICODE)];
        }
        self::render('diagnostics', ['d' => Diagnostics::run(), 'test' => $r]);
    }

    // ---------------------------------------------------------------- cuenta
    private static function account(): void
    {
        $u = self::require();
        Session::start();
        $secret = $_SESSION['totp_new'] ?? null;
        self::render('account', ['secret' => $secret, 'uri' => $secret ? Totp::uri($secret, $u['email']) : '']);
    }

    private static function accountSave(): void
    {
        $u = self::require();
        self::post();
        Session::start();
        $a = (string) ($_POST['accion'] ?? '');
        try {
            if ($a === 'password') {
                $row = Db::one('SELECT pass_hash FROM ' . Db::t('users') . ' WHERE id=?', [$u['id']]);
                if (!password_verify((string) ($_POST['actual'] ?? ''), (string) $row['pass_hash'])) { throw new \RuntimeException('La contraseña actual no es correcta.'); }
                $n = (string) ($_POST['nueva'] ?? '');
                if (strlen($n) < 12) { throw new \RuntimeException('La nueva contraseña debe tener al menos 12 caracteres.'); }
                Db::update('users', ['pass_hash' => Auth::hash($n)], 'id=?', [$u['id']]);
                Log::audit('password_cambiada');
                self::flash('Contraseña actualizada.');
            } elseif ($a === 'totp_iniciar') {
                $_SESSION['totp_new'] = Totp::newSecret();
            } elseif ($a === 'totp_activar') {
                $s = (string) ($_SESSION['totp_new'] ?? '');
                if ($s === '' || !Totp::verify($s, (string) ($_POST['code'] ?? ''))) { throw new \RuntimeException('El código no coincide. Inténtelo de nuevo.'); }
                Db::update('users', ['totp_secret' => Crypto::encrypt($s), 'totp_enabled' => 1], 'id=?', [$u['id']]);
                unset($_SESSION['totp_new']);
                Log::audit('2fa_activado');
                self::flash('Verificación en dos pasos activada.');
            } elseif ($a === 'totp_quitar') {
                $row = Db::one('SELECT pass_hash FROM ' . Db::t('users') . ' WHERE id=?', [$u['id']]);
                if (!password_verify((string) ($_POST['actual'] ?? ''), (string) $row['pass_hash'])) { throw new \RuntimeException('Contraseña incorrecta.'); }
                Db::update('users', ['totp_secret' => null, 'totp_enabled' => 0], 'id=?', [$u['id']]);
                Log::audit('2fa_desactivado');
                self::flash('Verificación en dos pasos desactivada.');
            }
        } catch (\Throwable $e) {
            self::flash($e->getMessage(), 'err');
        }
        Http::redirect('/admin/cuenta');
    }
}
