<?php
declare(strict_types=1);
namespace S5\Controllers;

use S5\Core\Db;
use S5\Core\Http;
use S5\Core\Settings;
use S5\Core\View;
use S5\Services\Orders;

/** Páginas públicas del portal. */
final class PortalController
{
    public static function cfg(): array
    {
        $demos = [];
        foreach (Db::all('SELECT business_name, fqdn, data, plan FROM ' . Db::t('orders') . ' WHERE is_demo=1 AND status=? ORDER BY id DESC LIMIT 12', [Orders::ST_PUBLICADA]) as $d) {
            $b = json_decode((string) $d['data'], true) ?: [];
            $demos[] = ['nombre' => $d['business_name'], 'url' => Settings::scheme() . '://' . $d['fqdn'] . Orders::portSuffix(), 'rubro' => $b['negocio']['rubro'] ?? '', 'plan' => $d['plan']];
        }
        return [
            'wa_servicom' => preg_replace('/\D+/', '', (string) Settings::get('wa_servicom', '')),
            'precio_info' => Settings::float('precio_info', 1250),
            'precio_tienda' => Settings::float('precio_tienda', 1750),
            'precio_tarjeta' => Settings::float('precio_tarjeta', 750),
            'dominio_base' => Settings::baseDomain(),
            'portal_sub' => (string) Settings::get('portal_sub', 'crear'),
            'pres_max_mb' => Settings::int('pres_max_mb', 10),
            'banco' => [
                'banco' => (string) Settings::get('banco_nombre', ''), 'cuenta' => (string) Settings::get('banco_cuenta', ''),
                'titular' => (string) Settings::get('banco_titular', ''), 'tipo' => (string) Settings::get('banco_tipo', ''),
            ],
            'demos' => $demos,
        ];
    }

    private static function page(string $view, array $vars, int $status = 200): void
    {
        http_response_code($status);
        $vars += ['cfg' => self::cfg()];
        View::render($view, $vars);
    }

    public static function home(): void
    {
        self::page('portal/home', ['title' => 'Tu web en 5 minutos · Servicom', 'page' => 'home']);
    }

    public static function wizard(array $p = []): void
    {
        $token = (string) ($p['token'] ?? '');
        $o = $token !== '' ? Orders::byToken($token) : null;
        if ($token !== '' && !$o) {
            self::gone();
            return;
        }
        $draft = $o ? ApiController::view($o) : null;
        self::page('portal/wizard', ['title' => 'Crea tu web · Servicom', 'page' => 'wizard', 'token' => $token, 'plan' => $o['plan'] ?? (in_array($_GET['plan'] ?? '', ['info', 'tienda'], true) ? $_GET['plan'] : ''), 'draft' => $draft]);
    }

    public static function status(array $p): void
    {
        $o = Orders::byToken((string) ($p['token'] ?? ''));
        if (!$o) { self::gone(); return; }
        self::page('portal/status', ['title' => 'Tu vista previa · Servicom', 'page' => 'status', 'token' => $o['token'], 'order' => $o, 'estado' => ApiController::publicState((string) $o['status']), 'url' => in_array($o['status'], [Orders::ST_LISTA, Orders::ST_PAGO], true) ? Orders::previewUrl($o, true) : '']);
    }

    public static function pay(array $p): void
    {
        $o = Orders::byToken((string) ($p['token'] ?? ''));
        if (!$o) { self::gone(); return; }
        self::page('portal/pay', [
            'title' => 'Pago · Servicom', 'page' => 'pay', 'token' => $o['token'], 'order' => $o, 'plan' => $o['plan'],
            'tarjeta' => !empty($o['card_extra']), 'total' => Orders::total($o),
        ]);
    }

    /** Enlaces de la barra de la vista previa (clave de vista previa, no el token del borrador). */
    public static function previewLink(array $p): void
    {
        $o = Orders::byPreviewKey((string) ($p['key'] ?? ''));
        if (!$o) { self::gone(); return; }
        $dest = ($p['action'] ?? '') === 'pagar' ? '/pago/' : '/continuar/';
        Http::redirect($dest . $o['token']);
    }

    public static function notFound(): void
    {
        self::page('portal/error', ['title' => 'No encontrado', 'page' => 'error', 'code' => 404], 404);
    }

    public static function gone(): void
    {
        self::page('portal/gone', ['title' => 'Enlace no disponible', 'page' => 'gone'], 410);
    }
}
