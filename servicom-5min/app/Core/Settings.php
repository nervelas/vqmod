<?php
declare(strict_types=1);
namespace S5\Core;

/** Ajustes editables desde el panel. Los secretos se guardan cifrados. */
final class Settings
{
    private static ?array $cache = null;

    public static function defaults(): array
    {
        return [
            'dominio_base' => 'servicom.gt',
            'portal_sub' => 'crear',
            'precio_info' => '1250',
            'precio_tienda' => '1750',
            'precio_tarjeta' => '750',
            'wa_servicom' => '',
            'owner_email' => '',
            'ai_model_main' => 'claude-sonnet-5-5',
            'ai_model_fallback' => 'claude-haiku-5-5',
            'ai_model_extract' => 'claude-haiku-5-5',
            'ai_cap_day' => '1.00',
            'ai_cap_total' => '10.00',
            'pres_max_mb' => '10',
            'webs_path' => '',
            'reservados' => 'www,mail,webmail,cpanel,whm,ftp,smtp,imap,pop,ns1,ns2,autodiscover,autoconfig,admin,panel,portal,api,crear,cpw,ctv,demo,test,dev,staging,blog,tienda,soporte',
            'banco_nombre' => '',
            'banco_cuenta' => '',
            'banco_titular' => '',
            'banco_tipo' => '',
            'preview_days' => '15',
            'max_regen' => '3',
            'max_drafts_ip_day' => '5',
            'max_analysis_ip_day' => '6',
            'min_fill_seconds' => '25',
            'renewal_notice_days' => '30',
            'notify_client_renewal' => '0',
            'smtp_host' => '', 'smtp_port' => '587', 'smtp_user' => '', 'smtp_pass' => '', 'smtp_secure' => 'tls', 'smtp_from' => '', 'smtp_from_name' => 'Servicom',
            'default_host' => '1',
            'footer_credit' => 'Sitio creado por Servicom',
            'force_scheme' => '',
        ];
    }

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Db::all('SELECT k, v, is_secret FROM ' . Db::t('settings')) as $r) {
                    self::$cache[$r['k']] = [(string) $r['v'], (int) $r['is_secret']];
                }
            } catch (\Throwable $e) {
                // tabla aún no existe (instalador)
            }
        }
        return self::$cache;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function get(string $k, $default = null)
    {
        $all = self::load();
        if (isset($all[$k])) {
            [$v, $secret] = $all[$k];
            if ($secret) {
                return Crypto::decrypt($v);
            }
            return $v;
        }
        $d = self::defaults();
        if (array_key_exists($k, $d)) {
            return $d[$k];
        }
        return $default;
    }

    public static function has(string $k): bool
    {
        $all = self::load();
        return isset($all[$k]) && $all[$k][0] !== '';
    }

    public static function int(string $k, int $default = 0): int
    {
        $v = self::get($k);
        return $v === null || $v === '' ? $default : (int) $v;
    }

    public static function float(string $k, float $default = 0.0): float
    {
        $v = self::get($k);
        return $v === null || $v === '' ? $default : (float) $v;
    }

    public static function bool(string $k, bool $default = false): bool
    {
        $v = self::get($k);
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array((string) $v, ['1', 'true', 'on', 'si', 'sí'], true);
    }

    public static function set(string $k, $v, bool $secret = false): void
    {
        $v = (string) $v;
        $store = $secret && $v !== '' ? Crypto::encrypt($v) : $v;
        Db::q('INSERT INTO ' . Db::t('settings') . ' (k, v, is_secret) VALUES (?,?,?) ON DUPLICATE KEY UPDATE v=VALUES(v), is_secret=VALUES(is_secret)', [$k, $store, $secret ? 1 : 0]);
        self::$cache = null;
    }

    /** Dominio base normalizado (sin esquema). */
    public static function baseDomain(): string
    {
        return strtolower(trim((string) self::get('dominio_base', 'servicom.gt')));
    }

    public static function portalHost(): string
    {
        return strtolower(trim((string) self::get('portal_sub', 'crear'))) . '.' . self::baseDomain();
    }

    public static function scheme(): string
    {
        $f = (string) self::get('force_scheme', '');
        if ($f === 'http' || $f === 'https') {
            return $f;
        }
        return 'https';
    }

    public static function portalUrl(): string
    {
        $o = getenv('S5_PORTAL_URL');
        if ($o) {
            return rtrim($o, '/');
        }
        return self::scheme() . '://' . self::portalHost();
    }
}
