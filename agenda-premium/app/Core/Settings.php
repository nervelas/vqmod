<?php
declare(strict_types=1);

namespace App\Core;

/** Ajustes administrables (tabla settings). Valores siempre como texto; usa los helpers tipados. */
final class Settings
{
    private static ?array $cache = null;

    public static function defaults(): array
    {
        return [
            // Negocio y marca
            'business_name' => 'Agenda Premium',
            'tagline' => 'Reserva tu cita en minutos',
            'about' => '',
            'logo_file_id' => '',
            'favicon_file_id' => '',
            'hero_file_id' => '',
            'color_gold' => '#C9A050',
            'color_bg' => '#06080D',
            'whatsapp' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'map_url' => '',
            'social_facebook' => '',
            'social_instagram' => '',
            'social_tiktok' => '',
            'social_youtube' => '',
            'website' => '',
            'profession' => 'otro',
            'terms_label' => 'cita',
            'terms_label_plural' => 'citas',
            'host_label' => 'profesional',
            'client_label' => 'cliente',
            // Regionales
            'timezone' => 'America/Guatemala',
            'currency_symbol' => 'Q',
            'phone_cc' => '502',
            'time_format' => '12',
            'holidays_enabled' => '1',
            // Reserva
            'booking_rate_limit' => '10',
            'booking_min_form_seconds' => '3',
            'bank_info' => '',
            'payment_link' => '',
            'noshow_block_after' => '0',
            'noshow_deposit_after' => '2',
            'noshow_deposit_percent' => '50',
            'embed_allowed_origins' => '*',
            'public_home_enabled' => '1',
            'captcha_provider' => 'none',
            'captcha_site_key' => '',
            'captcha_secret' => '',
            'video_provider_domain' => 'meet.jit.si',
            'waitlist_offer_minutes' => '15',
            'pending_expire_hours' => '48',
            // Legal
            'legal_version' => '1',
            'privacy_text' => '',
            'terms_text' => '',
            'cookies_notice' => '',
            'retention_months' => '0',
            // Correo
            'mail_from_name' => '',
            'mail_from_email' => '',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_secure' => 'tls',
            'smtp_user' => '',
            'smtp_pass' => '',
            'admin_notify_email' => '',
            'weekly_summary' => '1',
            // WhatsApp Cloud API (opcional)
            'wa_api_enabled' => '0',
            'wa_api_token' => '',
            'wa_api_phone_id' => '',
            'wa_api_template' => '',
            'wa_api_lang' => 'es',
            // Sistema
            'cron_token' => '',
            'cron_last_run' => '',
            'session_idle_minutes' => '120',
            'avail_version' => '1',
            'onboarding_done' => '0',
            'ics_cache_minutes' => '30',
            'api_rate_per_minute' => '60',
            'installed_version' => '1.0.0',
        ];
    }

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = self::defaults();
            try {
                foreach (Db::all('SELECT k, v FROM settings') as $r) {
                    self::$cache[$r['k']] = (string) $r['v'];
                }
            } catch (\Throwable $e) {
                // base de datos aún no disponible (instalador)
            }
        }
        return self::$cache;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::load();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function bool(string $key): bool
    {
        return in_array((string) self::get($key, '0'), ['1', 'true', 'on', 'yes'], true);
    }

    public static function all(): array
    {
        return self::load();
    }

    public static function set(string $key, string $value): void
    {
        Db::q('INSERT INTO settings (k, v, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = VALUES(updated_at)', [$key, $value, Clock::utc()]);
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $k => $v) {
            self::set((string) $k, (string) $v);
        }
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function tz(): string
    {
        return Tz::safe((string) self::get('timezone', 'America/Guatemala'));
    }
}
