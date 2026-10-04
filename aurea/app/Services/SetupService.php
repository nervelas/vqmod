<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Auth;
use Aurea\Core\Db;
use Aurea\Core\Settings;
use Aurea\Core\Util;

/** Lógica de instalación (usada por /instalar y por las pruebas automatizadas). */
final class SetupService
{
    public const PRIVACY_DEFAULT = "Responsable del tratamiento: {negocio}.\n\nRecopilamos su nombre, teléfono, correo electrónico y la información que usted nos proporciona al agendar, con la única finalidad de gestionar su cita, enviarle confirmaciones y recordatorios y brindarle el servicio solicitado.\n\nNo vendemos ni compartimos sus datos con terceros, salvo obligación legal. Usted puede solicitar en cualquier momento el acceso, la corrección o la eliminación de sus datos escribiéndonos por los medios de contacto publicados en este sitio.\n\nConservamos sus datos mientras exista una relación de servicio o mientras sea necesario para cumplir obligaciones legales.";
    public const TERMS_DEFAULT = "Al agendar usted acepta estas condiciones:\n\n1. Puntualidad: le pedimos llegar con unos minutos de anticipación. Pasados 15 minutos del horario, la cita podría reprogramarse.\n2. Cancelaciones y cambios: puede cancelar o reprogramar desde el enlace de su cita respetando el tiempo mínimo indicado en la política de cancelación.\n3. Inasistencias: las citas a las que no asista sin avisar pueden limitar futuras reservas en línea.\n4. Anticipos: cuando el servicio requiere anticipo, la cita se confirma al recibir el comprobante de pago.";

    public static function defaults(array $biz): array
    {
        $tz = $biz['timezone'] ?? 'America/Guatemala';
        return [
            'business_name' => $biz['name'], 'business_tagline' => $biz['tagline'] ?? '', 'business_phone' => $biz['phone'] ?? '', 'business_whatsapp' => $biz['whatsapp'] ?? '',
            'business_email' => $biz['email'] ?? '', 'business_address' => $biz['address'] ?? '', 'business_map_url' => '', 'timezone' => $tz, 'currency' => 'GTQ',
            'logo' => '', 'favicon' => '', 'hero_image' => '', 'social_instagram' => '', 'social_facebook' => '', 'social_tiktok' => '', 'social_linkedin' => '', 'social_youtube' => '',
            'privacy_text' => self::PRIVACY_DEFAULT, 'terms_text' => self::TERMS_DEFAULT, 'privacy_version' => date('Ymd'), 'privacy_required' => '1',
            'seo_title' => '', 'seo_description' => '', 'cancel_min_hours' => '12', 'pending_expire_hours' => '48', 'email_required' => '0',
            'reminder_24h' => '1', 'reminder_2h' => '1', 'cron_fallback' => '1', 'waitlist_enabled' => '1', 'waitlist_offer_hours' => '2',
            'review_requests' => '1', 'review_delay_hours' => '2', 'reviews_public' => '1', 'followup_enabled' => '0', 'session_idle_minutes' => '120', 'upload_max_mb' => '5',
            'max_active_per_client' => '5', 'noshow_block_after' => '0', 'show_prices' => '1', 'bank_name' => '', 'bank_account' => '', 'bank_holder' => '', 'bank_type' => '',
            'bank_notes' => 'Envía tu comprobante desde el enlace de tu cita o por WhatsApp.', 'payment_link_url' => '', 'payment_link_label' => 'Pagar en línea',
            'captcha_provider' => 'none', 'captcha_site_key' => '', 'captcha_secret' => '', 'wa_api_enabled' => '0', 'wa_phone_id' => '', 'wa_token' => '', 'wa_template_lang' => 'es',
            'smtp_host' => '', 'smtp_port' => '587', 'smtp_secure' => 'tls', 'smtp_user' => '', 'smtp_pass' => '', 'mail_from_email' => $biz['email'] ?? '', 'mail_from_name' => $biz['name'],
            'booking_embed_origins' => '*', 'onboarding_done' => '0', 'cron_token' => Util::token(16), 'base_url' => $biz['base_url'] ?? '', 'profession' => 'otro',
            'term_client' => 'Cliente', 'term_clients' => 'Clientes', 'term_appt' => 'Cita', 'term_appts' => 'Citas', 'term_professional' => 'Profesional', 'term_professionals' => 'Profesionales',
        ];
    }

    /**
     * Crea todo sobre una BD ya conectada. $admin: name,email,password. $biz: name,... $preset: slug.
     * @return array{ok:bool,error?:string}
     */
    public static function install(array $biz, array $admin, string $preset, bool $demo): array
    {
        if ($e = Auth::strongPassword((string)$admin['password'])) { return ['ok' => false, 'error' => $e]; }
        if (!Util::isEmail((string)$admin['email'])) { return ['ok' => false, 'error' => 'Correo del administrador inválido.']; }
        if (trim((string)$biz['name']) === '') { return ['ok' => false, 'error' => 'Escribe el nombre del negocio.']; }
        Migrator::run();
        $now = date('Y-m-d H:i:s');
        foreach (self::defaults($biz) as $k => $v) {
            Db::exec('INSERT INTO settings (skey,svalue,updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)', [$k, (string)$v, $now]);
        }
        Settings::flush();
        Db::insert('users', ['name' => mb_substr(trim((string)$admin['name']), 0, 150) ?: 'Administrador', 'email' => mb_strtolower(trim((string)$admin['email'])),
            'password_hash' => Auth::hash((string)$admin['password']), 'role' => 'admin', 'active' => 1, 'created_at' => $now]);
        $y = (int)date('Y');
        foreach ([$y, $y + 1] as $yr) { HolidayService::seedYear($yr); }
        PresetService::apply($preset);
        if ($demo) { PresetService::demo(); }
        Settings::flush();
        return ['ok' => true];
    }
}
