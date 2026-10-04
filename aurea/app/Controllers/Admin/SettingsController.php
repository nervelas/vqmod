<?php
declare(strict_types=1);

namespace Aurea\Controllers\Admin;

use Aurea\Core\Db;
use Aurea\Core\Response;
use Aurea\Core\Secret;
use Aurea\Core\Settings;
use Aurea\Core\Upload;
use Aurea\Core\Util;
use Aurea\Services\PresetService;

final class SettingsController extends AdminController
{
    private const TABS = ['negocio' => 'Negocio', 'marca' => 'Marca y sitio', 'reservas' => 'Reservas y políticas', 'pagos' => 'Pagos', 'comunicacion' => 'Comunicación', 'seguridad' => 'Seguridad', 'legal' => 'Legal', 'terminologia' => 'Terminología'];

    /** clave => [tipo, opciones]. Tipos: text, textarea, int, bool, email, url, color, select, secret */
    private function schema(): array
    {
        $tz = ['America/Guatemala', 'America/Mexico_City', 'America/El_Salvador', 'America/Tegucigalpa', 'America/Costa_Rica', 'America/Panama', 'America/Bogota', 'America/Lima', 'America/Santiago', 'America/Argentina/Buenos_Aires', 'Europe/Madrid', 'America/New_York', 'America/Chicago', 'America/Los_Angeles'];
        return [
            'negocio' => ['business_name' => ['text', ['max' => 150, 'required' => 1, 'label' => 'Nombre del negocio']], 'business_tagline' => ['text', ['max' => 190, 'label' => 'Lema']], 'business_phone' => ['text', ['max' => 30, 'label' => 'Teléfono']],
                'business_whatsapp' => ['text', ['max' => 30, 'label' => 'WhatsApp del negocio (8 dígitos o con código)']], 'business_email' => ['email', ['label' => 'Correo del negocio (recibe avisos de nuevas citas)']], 'business_address' => ['text', ['max' => 255, 'label' => 'Dirección']],
                'business_map_url' => ['url', ['label' => 'Enlace de mapa']], 'timezone' => ['select', ['options' => array_combine($tz, $tz), 'label' => 'Zona horaria']], 'currency' => ['select', ['options' => ['GTQ' => 'Quetzales (Q)', 'USD' => 'Dólares (US$)'], 'label' => 'Moneda']],
                'social_instagram' => ['url', ['label' => 'Instagram (URL)']], 'social_facebook' => ['url', ['label' => 'Facebook (URL)']], 'social_tiktok' => ['url', ['label' => 'TikTok (URL)']], 'social_linkedin' => ['url', ['label' => 'LinkedIn (URL)']], 'social_youtube' => ['url', ['label' => 'YouTube (URL)']]],
            'marca' => ['hero_headline' => ['text', ['max' => 120, 'label' => 'Titular de la portada']], 'hero_subtitle' => ['textarea', ['max' => 300, 'label' => 'Subtítulo de la portada']], 'show_prices' => ['bool', ['label' => 'Mostrar precios al público']],
                'color_gold' => ['color', ['label' => 'Color dorado de acento (vacío = predeterminado)']], 'color_ink' => ['color', ['label' => 'Color negro tinta']], 'color_ivory' => ['color', ['label' => 'Color marfil de fondo']],
                'seo_title' => ['text', ['max' => 70, 'label' => 'Título SEO']], 'seo_description' => ['textarea', ['max' => 170, 'label' => 'Descripción SEO']]],
            'reservas' => ['cancel_min_hours' => ['int', ['min' => 0, 'max' => 720, 'label' => 'Horas mínimas para cancelar o reprogramar en línea']], 'pending_expire_hours' => ['int', ['min' => 0, 'max' => 720, 'label' => 'Las citas pendientes vencen a las (horas; 0 = nunca)']],
                'email_required' => ['bool', ['label' => 'Correo obligatorio al reservar']], 'privacy_required' => ['bool', ['label' => 'Exigir aceptación del aviso de privacidad']], 'max_active_per_client' => ['int', ['min' => 0, 'max' => 50, 'label' => 'Máximo de citas próximas por cliente en línea (0 = sin límite)']],
                'noshow_block_after' => ['int', ['min' => 0, 'max' => 20, 'label' => 'Bloquear reservas en línea tras N inasistencias (0 = no bloquear)']], 'reminder_24h' => ['bool', ['label' => 'Enviar recordatorio 24 horas antes']], 'reminder_2h' => ['bool', ['label' => 'Enviar recordatorio 2 horas antes']],
                'waitlist_enabled' => ['bool', ['label' => 'Activar lista de espera']], 'waitlist_offer_hours' => ['int', ['min' => 1, 'max' => 72, 'label' => 'Horas que dura una oferta de la lista de espera']],
                'review_requests' => ['bool', ['label' => 'Solicitar reseña tras la cita']], 'review_delay_hours' => ['int', ['min' => 0, 'max' => 168, 'label' => 'Horas después de la cita para solicitar reseña']], 'reviews_public' => ['bool', ['label' => 'Mostrar reseñas aprobadas en el sitio']],
                'followup_enabled' => ['bool', ['label' => 'Enviar mensaje de seguimiento al día siguiente']], 'upload_max_mb' => ['int', ['min' => 1, 'max' => 25, 'label' => 'Tamaño máximo de archivos subidos (MB)']]],
            'pagos' => ['bank_name' => ['text', ['max' => 100, 'label' => 'Banco']], 'bank_type' => ['text', ['max' => 60, 'label' => 'Tipo de cuenta (monetaria, ahorro…)']], 'bank_account' => ['text', ['max' => 60, 'label' => 'Número de cuenta']], 'bank_holder' => ['text', ['max' => 150, 'label' => 'Nombre del titular']],
                'bank_notes' => ['textarea', ['max' => 400, 'label' => 'Instrucciones para el cliente']], 'payment_link_url' => ['url', ['label' => 'Enlace de pago externo (Recurrente, Pagalo, Qpaypro, NeoLink u otro)']], 'payment_link_label' => ['text', ['max' => 60, 'label' => 'Texto del botón de pago']]],
            'comunicacion' => ['mail_from_email' => ['email', ['label' => 'Correo remitente']], 'mail_from_name' => ['text', ['max' => 100, 'label' => 'Nombre remitente']], 'smtp_host' => ['text', ['max' => 190, 'label' => 'Servidor SMTP (vacío = usar mail() del hosting)']],
                'smtp_port' => ['int', ['min' => 1, 'max' => 65535, 'label' => 'Puerto']], 'smtp_secure' => ['select', ['options' => ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => 'Sin cifrado'], 'label' => 'Seguridad']],
                'smtp_user' => ['text', ['max' => 190, 'label' => 'Usuario SMTP']], 'smtp_pass' => ['secret', ['label' => 'Contraseña SMTP']], 'smtp_verify_cert' => ['bool', ['label' => 'Verificar certificado del servidor']],
                'cron_fallback' => ['bool', ['label' => 'Modo de respaldo: ejecutar tareas con las visitas si no hay cron']],
                'wa_api_enabled' => ['bool', ['label' => 'Activar WhatsApp Business Cloud API (opcional; requiere cuenta Meta propia)']], 'wa_phone_id' => ['text', ['max' => 40, 'label' => 'ID del número de teléfono (Meta)']], 'wa_token' => ['secret', ['label' => 'Token de acceso permanente (Meta)']],
                'wa_template_lang' => ['text', ['max' => 10, 'label' => 'Idioma de plantillas (es, es_MX…)']]],
            'seguridad' => ['captcha_provider' => ['select', ['options' => ['none' => 'Ninguno', 'turnstile' => 'Cloudflare Turnstile', 'hcaptcha' => 'hCaptcha'], 'label' => 'Verificación anti-bots en reservas']], 'captcha_site_key' => ['text', ['max' => 200, 'label' => 'Clave del sitio']], 'captcha_secret' => ['secret', ['label' => 'Clave secreta']],
                'session_idle_minutes' => ['int', ['min' => 5, 'max' => 1440, 'label' => 'Cerrar sesión del panel tras N minutos de inactividad']], 'booking_embed_origins' => ['text', ['max' => 500, 'label' => 'Sitios que pueden incrustar el widget (* = todos; o lista de https://dominio separados por espacio)']],
                'base_url' => ['url', ['label' => 'URL pública del sistema (se usa en enlaces de correos y WhatsApp)']]],
            'legal' => ['privacy_text' => ['textarea', ['max' => 10000, 'rows' => 14, 'label' => 'Aviso de privacidad (usa {negocio})']], 'terms_text' => ['textarea', ['max' => 10000, 'rows' => 14, 'label' => 'Términos y condiciones']]],
            'terminologia' => ['term_client' => ['text', ['max' => 40, 'label' => 'Cliente (singular, p. ej. Paciente)']], 'term_clients' => ['text', ['max' => 40, 'label' => 'Clientes (plural)']], 'term_appt' => ['text', ['max' => 40, 'label' => 'Cita (singular femenino, p. ej. Consulta)']],
                'term_appts' => ['text', ['max' => 40, 'label' => 'Citas (plural)']], 'term_professional' => ['text', ['max' => 40, 'label' => 'Profesional (singular)']], 'term_professionals' => ['text', ['max' => 40, 'label' => 'Profesionales (plural)']]],
        ];
    }

    public function index(): Response
    {
        $this->need('admin');
        $tab = array_key_exists($this->req->str('tab'), self::TABS) ? $this->req->str('tab') : 'negocio';
        $schema = $this->schema()[$tab];
        $vals = [];
        foreach ($schema as $k => [$type]) { $vals[$k] = $type === 'secret' ? (Settings::get($k, '') !== '' ? '********' : '') : Settings::get($k, ''); }
        return $this->render('settings', ['tab' => $tab, 'tabs' => self::TABS, 'schema' => $schema, 'vals' => $vals, 'presets' => PresetService::labels(), 'current' => (string)Settings::get('profession', 'otro')], 'ajustes', 'Marca y ajustes');
    }

    public function save(): Response
    {
        $this->need('admin');
        $tab = array_key_exists($this->req->str('tab'), self::TABS) ? $this->req->str('tab') : 'negocio';
        $upd = []; $errors = [];
        foreach ($this->schema()[$tab] as $k => [$type, $o]) {
            $raw = $this->req->post[$k] ?? '';
            $raw = is_scalar($raw) ? trim((string)$raw) : '';
            $label = $o['label'] ?? $k;
            if ($type === 'bool') { $upd[$k] = $this->req->int($k) ? '1' : '0'; continue; }
            if ($type === 'secret') { if ($raw !== '' && $raw !== '********') { $upd[$k] = Secret::encrypt($raw); } elseif ($this->req->int($k . '_clear')) { $upd[$k] = ''; } continue; }
            if ($type === 'color' && $this->req->int($k . '_default')) { $upd[$k] = ''; continue; }
            if ($raw === '') { if (!empty($o['required'])) { $errors[] = "«$label» es obligatorio."; } else { $upd[$k] = ''; } continue; }
            switch ($type) {
                case 'int': if (!ctype_digit($raw) || (int)$raw < ($o['min'] ?? 0) || (int)$raw > ($o['max'] ?? PHP_INT_MAX)) { $errors[] = "«$label»: valor fuera de rango."; } else { $upd[$k] = (string)(int)$raw; } break;
                case 'email': if (!Util::isEmail($raw)) { $errors[] = "«$label»: correo inválido."; } else { $upd[$k] = $raw; } break;
                case 'url': $u = Util::safeUrl($raw); if ($u === '') { $errors[] = "«$label»: usa una dirección http(s) válida."; } else { $upd[$k] = $u; } break;
                case 'color': if (!Util::isColor($raw)) { $errors[] = "«$label»: color inválido."; } else { $upd[$k] = $raw; } break;
                case 'select': if (!array_key_exists($raw, $o['options'])) { $errors[] = "«$label»: opción inválida."; } else { $upd[$k] = $raw; } break;
                default: if (mb_strlen($raw) > ($o['max'] ?? 255)) { $errors[] = "«$label» excede " . ($o['max'] ?? 255) . ' caracteres.'; } else { $upd[$k] = $raw; }
            }
        }
        if ($tab === 'seguridad' && isset($upd['booking_embed_origins']) && $upd['booking_embed_origins'] !== '*' && !preg_match('#^(https?://[A-Za-z0-9.\-:*]+)(\s+https?://[A-Za-z0-9.\-:*]+)*$#', $upd['booking_embed_origins'])) { $errors[] = 'Los sitios permitidos deben ser * o direcciones como https://midominio.com.'; }
        if ($tab === 'legal' && isset($upd['privacy_text']) && $upd['privacy_text'] !== (string)Settings::get('privacy_text', '')) { $upd['privacy_version'] = date('YmdHis'); }
        if ($tab === 'marca') {
            foreach (['logo' => ['logo', 600], 'favicon' => ['favicon', 128], 'hero_image' => ['hero', 1800]] as $field => [$prefix, $dim]) {
                if (!empty($_FILES[$field]['name'])) {
                    try {
                        $name = Upload::publicImage($_FILES[$field], $prefix, $dim);
                        $oldF = (string)Settings::get($field, '');
                        if ($oldF !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $oldF)) { @unlink(AUREA_ROOT . '/uploads/' . $oldF); }
                        $upd[$field] = $name;
                    } catch (\RuntimeException $e) { $errors[] = $e->getMessage(); }
                } elseif ($this->req->int($field . '_clear')) { $oldF = (string)Settings::get($field, ''); if ($oldF !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $oldF)) { @unlink(AUREA_ROOT . '/uploads/' . $oldF); } $upd[$field] = ''; }
            }
        }
        if ($errors) { $this->fail($errors[0]); return $this->redirect('/admin/ajustes?tab=' . $tab); }
        Settings::setMany($upd);
        $this->audit('settings_saved', 'settings', null, $tab);
        $this->ok('Ajustes guardados.');
        return $this->redirect('/admin/ajustes?tab=' . $tab);
    }

    public function applyPreset(): Response
    {
        $this->need('admin');
        $slug = $this->req->str('preset');
        $replace = $this->req->int('replace') === 1;
        if (!PresetService::apply($slug, $replace)) { $this->fail('Perfil inválido.'); }
        else { $this->audit('preset_applied', 'settings', null, $slug . ($replace ? ' (reemplazo)' : '')); $this->ok('Perfil de profesión aplicado.'); }
        return $this->redirect('/admin/ajustes?tab=terminologia');
    }

    public function share(): Response
    {
        $this->need('admin');
        $profs = Db::all('SELECT id,name,slug,ics_token FROM professionals WHERE active=1 ORDER BY sort,name');
        return $this->render('share', ['link' => abs_url('/reservar'), 'embed' => abs_url('/embed'), 'script' => abs_url('/assets/js/widget.js'), 'profs' => $profs, 'scripts' => ['vendor/qrcode.js', 'js/qr.js']], 'compartir', 'Compartir y widget');
    }
}
