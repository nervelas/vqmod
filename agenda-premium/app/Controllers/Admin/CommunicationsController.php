<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Db;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Validator;

/** Correo (SMTP), WhatsApp opcional, captcha, límites, API y cron. */
final class CommunicationsController extends A4Controller
{
    public function index(Request $req, array $p, array $old = [], array $errors = [], int $status = 200): Response
    {
        $f = [];
        foreach (Settings::all() as $k => $v) {
            if (in_array($k, Settings::SECRET_KEYS, true)) {
                continue;
            }
            $f[$k] = array_key_exists($k, $old) && !is_array($old[$k]) ? (string) $old[$k] : (string) $v;
        }
        if ($old) {
            $f['weekly_summary'] = isset($old['weekly_summary']) ? '1' : '0';
            $f['wa_api_enabled'] = isset($old['wa_api_enabled']) ? '1' : '0';
        }
        $token = (string) Settings::get('cron_token', '');
        $base = rtrim(abs_url('/'), '/');
        $waErrors = [];
        $mailErrors = [];
        try {
            $waErrors = Db::all("SELECT id, phone, last_error, created_at FROM message_queue WHERE channel = 'whatsapp_api' AND status = 'failed' ORDER BY id DESC LIMIT 8");
            $mailErrors = Db::all("SELECT id, to_email, subject, last_error, created_at FROM email_queue WHERE status = 'failed' ORDER BY id DESC LIMIT 8");
        } catch (\Throwable $e) {
            // tablas no disponibles: se omite el registro
        }
        $res = $this->page('admin/communications/index', [
            'title' => 'Correo y WhatsApp',
            'f' => $f,
            'errors' => $errors,
            'secrets' => [
                'smtp_pass' => Settings::hasSecret('smtp_pass'),
                'wa_api_token' => Settings::hasSecret('wa_api_token'),
                'captcha_secret' => Settings::hasSecret('captcha_secret'),
            ],
            'cronUrl' => $token !== '' ? $base . '/cron.php?token=' . $token : '',
            'cronCli' => 'php ' . APP_ROOT . '/cron.php',
            'cronToken' => $token,
            'cronLast' => (int) Settings::get('cron_last_run', 0),
            'waErrors' => $waErrors,
            'mailErrors' => $mailErrors,
            'adminEmail' => (string) (Auth::user()['email'] ?? ''),
            'mailerReady' => class_exists('App\\Services\\Mailer'),
            'waReady' => class_exists('App\\Services\\WhatsAppService'),
        ], ['js/admin-settings.js'], '/admin/comunicaciones');
        $res->status = $status;
        return $res;
    }

    public function save(Request $req, array $p): Response
    {
        $errors = [];
        $vals = [];
        $host = strtolower($req->str('smtp_host', 190));
        if ($host !== '' && !preg_match('/^(?=.{1,190}$)[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*$/', $host)) {
            $errors['smtp_host'] = 'Escribe solo el servidor, por ejemplo smtp.midominio.com (sin https:// ni espacios).';
        }
        $vals['smtp_host'] = $host;
        $port = Validator::intRange($req->str('smtp_port', 6), 1, 65535);
        if ($port === null) {
            $errors['smtp_port'] = 'El puerto debe ser un número entre 1 y 65535 (587 o 465 son los más comunes).';
        } else {
            $vals['smtp_port'] = (string) $port;
        }
        $vals['smtp_secure'] = $this->pick($req, 'smtp_secure', ['tls', 'ssl', 'none'], 'tls');
        $user = $req->str('smtp_user', 190);
        $vals['smtp_user'] = $user;
        $fromName = $req->str('mail_from_name', 120);
        if (preg_match('/[<>\r\n]/', $fromName)) {
            $errors['mail_from_name'] = 'El nombre del remitente no puede incluir < ni >.';
        }
        $vals['mail_from_name'] = $fromName;
        foreach (['mail_from_email' => 'del remitente', 'admin_notify_email' => 'de aviso'] as $k => $lbl) {
            $v = $req->str($k, 190);
            if ($v !== '' && !Validator::email($v)) {
                $errors[$k] = 'El correo ' . $lbl . ' no parece válido.';
            }
            $vals[$k] = $v;
        }
        $vals['weekly_summary'] = $req->bool('weekly_summary') ? '1' : '0';

        // WhatsApp Cloud API
        $vals['wa_api_enabled'] = $req->bool('wa_api_enabled') ? '1' : '0';
        $pid = $req->str('wa_api_phone_id', 40);
        if ($pid !== '' && !preg_match('/^\d{5,30}$/', $pid)) {
            $errors['wa_api_phone_id'] = 'El identificador del número tiene solo dígitos (lo encuentras en tu panel de WhatsApp Business).';
        }
        $vals['wa_api_phone_id'] = $pid;
        $tpl = $req->str('wa_api_template', 100);
        if ($tpl !== '' && !preg_match('/^[a-z0-9_]{1,100}$/', $tpl)) {
            $errors['wa_api_template'] = 'El nombre de la plantilla usa solo minúsculas, números y guion bajo.';
        }
        $vals['wa_api_template'] = $tpl;
        $lang = $req->str('wa_api_lang', 10);
        if (!preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $lang)) {
            $errors['wa_api_lang'] = 'Usa un código de idioma como es o es_MX.';
        }
        $vals['wa_api_lang'] = $lang;

        // Captcha
        $prov = $this->pick($req, 'captcha_provider', ['none', 'turnstile', 'hcaptcha'], 'none');
        $vals['captcha_provider'] = $prov;
        $site = $req->str('captcha_site_key', 200);
        if ($site !== '' && !preg_match('/^[A-Za-z0-9_\-]{6,200}$/', $site)) {
            $errors['captcha_site_key'] = 'La clave del sitio solo lleva letras, números, guiones y guion bajo.';
        }
        $vals['captcha_site_key'] = $site;

        // Secretos: solo se reemplazan si escriben uno nuevo
        $newSecrets = [];
        foreach (['smtp_pass' => 'smtp_pass', 'wa_api_token' => 'wa_api_token', 'captcha_secret' => 'captcha_secret'] as $field => $key) {
            $raw = (string) ($req->post[$field] ?? '');
            if ($raw !== '') {
                if (strlen($raw) > 1000 || preg_match('/[\x00-\x1F]/', $raw)) {
                    $errors[$field] = 'Ese valor no es válido (contiene caracteres de control o es demasiado largo).';
                } else {
                    $newSecrets[$key] = $raw;
                }
            } elseif ($req->bool('clear_' . $field)) {
                $newSecrets[$key] = '';
            }
        }
        if ($prov !== 'none') {
            if ($site === '') {
                $errors['captcha_site_key'] = 'Para activar el captcha necesitas la clave del sitio.';
            }
            if (!isset($newSecrets['captcha_secret']) && !Settings::hasSecret('captcha_secret')) {
                $errors['captcha_secret'] = 'Para activar el captcha necesitas la clave secreta.';
            } elseif (isset($newSecrets['captcha_secret']) && $newSecrets['captcha_secret'] === '') {
                $errors['captcha_secret'] = 'No puedes quitar la clave secreta con el captcha activo. Desactiva el captcha primero.';
            }
        }
        if ($vals['wa_api_enabled'] === '1') {
            $hasTok = isset($newSecrets['wa_api_token']) ? $newSecrets['wa_api_token'] !== '' : Settings::hasSecret('wa_api_token');
            if (!$hasTok || $pid === '') {
                $errors['wa_api_enabled'] = 'Para activar la API de WhatsApp necesitas el token y el identificador del número.';
            }
        }

        // Límites
        $limits = [
            'booking_rate_limit' => [1, 200, 'Indica entre 1 y 200 reservas por hora desde una misma dirección.'],
            'booking_min_form_seconds' => [0, 60, 'Indica entre 0 y 60 segundos.'],
            'api_rate_per_minute' => [10, 600, 'Indica entre 10 y 600 solicitudes por minuto.'],
            'session_idle_minutes' => [15, 1440, 'Indica entre 15 y 1440 minutos.'],
        ];
        foreach ($limits as $k => [$min, $max, $msg]) {
            $n = Validator::intRange($req->str($k, 6), $min, $max);
            if ($n === null) {
                $errors[$k] = $msg;
            } else {
                $vals[$k] = (string) $n;
            }
        }

        if ($errors) {
            return $this->index($req, [], $req->post, $errors, 422);
        }

        $changed = [];
        foreach ($vals as $k => $v) {
            if ((string) Settings::get($k, '') !== $v) {
                $changed[] = $k;
            }
        }
        Settings::setMany($vals);
        foreach ($newSecrets as $k => $plain) {
            Settings::setSecret($k, $plain);
            $changed[] = $k . ($plain === '' ? ' (borrada)' : ' (reemplazada)');
        }
        $this->audit('communications.update', $changed ? 'Cambió: ' . implode(', ', $changed) : 'Sin cambios');
        $this->flash('success', 'Guardamos la configuración de comunicaciones.');
        if ($prov !== 'none') {
            $this->flash('info', 'El captcha está activo: las páginas de reserva cargarán el script de ' . ($prov === 'turnstile' ? 'Cloudflare Turnstile' : 'hCaptcha') . '.');
        }
        return $this->redirect('/admin/comunicaciones');
    }

    public function testMail(Request $req, array $p): Response
    {
        if (!$this->throttle('mail')) {
            return $this->fail($req, 'Has hecho muchas pruebas seguidas. Espera unos minutos.', '/admin/comunicaciones');
        }
        $to = $req->str('to', 190);
        if (!Validator::email($to)) {
            return $this->fail($req, 'Escribe un correo válido para enviar la prueba.', '/admin/comunicaciones');
        }
        if (!class_exists('App\\Services\\Mailer')) {
            return $this->fail($req, 'El servicio de correo aún no está disponible en esta instalación.', '/admin/comunicaciones');
        }
        try {
            $html = \App\Services\Mailer::layout('Correo de prueba', '<p>¡Hola! Este es un correo de prueba de ' . e((string) Settings::get('business_name', 'Agenda Premium')) . '.</p><p>Si lo estás leyendo, tu correo está configurado correctamente.</p>');
            $r = \App\Services\Mailer::sendNow($to, null, 'Correo de prueba de ' . (string) Settings::get('business_name', 'Agenda Premium'), $html, 'Este es un correo de prueba. Si lo estás leyendo, tu correo está configurado correctamente.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Prueba de correo falló', $e);
            $r = ['ok' => false, 'error' => 'No pudimos enviar el correo. Revisa los datos del servidor.'];
        }
        $this->audit('communications.test_mail', ($r['ok'] ? 'Prueba enviada' : 'Prueba fallida') . ' a ' . $to);
        if (!empty($r['ok'])) {
            $this->flash('success', 'Enviamos el correo de prueba a ' . $to . '. Revisa también la carpeta de correo no deseado.');
        } else {
            $this->flash('error', 'No se pudo enviar: ' . $this->readable($r['error'] ?? null));
        }
        return $this->redirect('/admin/comunicaciones');
    }

    public function testConnection(Request $req, array $p): Response
    {
        if (!$this->throttle('conn')) {
            return $this->fail($req, 'Has hecho muchas pruebas seguidas. Espera unos minutos.', '/admin/comunicaciones');
        }
        if (!class_exists('App\\Services\\Mailer')) {
            return $this->fail($req, 'El servicio de correo aún no está disponible en esta instalación.', '/admin/comunicaciones');
        }
        try {
            $r = (array) \App\Services\Mailer::testConnection();
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Prueba de conexión SMTP falló', $e);
            $r = ['ok' => false, 'error' => 'No pudimos conectar con el servidor de correo.'];
        }
        $this->audit('communications.test_connection', !empty($r['ok']) ? 'Conexión correcta' : 'Conexión fallida');
        if (!empty($r['ok'])) {
            $this->flash('success', 'Conexión correcta con el servidor de correo.');
        } else {
            $this->flash('error', 'No pudimos conectar: ' . $this->readable($r['error'] ?? ($r['message'] ?? null)));
        }
        return $this->redirect('/admin/comunicaciones');
    }

    public function testWhatsapp(Request $req, array $p): Response
    {
        if (!$this->throttle('wa')) {
            return $this->fail($req, 'Has hecho muchas pruebas seguidas. Espera unos minutos.', '/admin/comunicaciones');
        }
        $phone = Str::phone($req->str('phone', 30), (string) Settings::get('phone_cc', '502'));
        if ($phone === null) {
            return $this->fail($req, 'Escribe un número válido (8 dígitos o formato +502 5555 1234).', '/admin/comunicaciones');
        }
        if (!Settings::bool('wa_api_enabled')) {
            return $this->fail($req, 'La API de WhatsApp está desactivada. Actívala y guarda antes de probar.', '/admin/comunicaciones');
        }
        if (!class_exists('App\\Services\\WhatsAppService')) {
            return $this->fail($req, 'El servicio de WhatsApp aún no está disponible en esta instalación.', '/admin/comunicaciones');
        }
        try {
            $r = (array) \App\Services\WhatsAppService::send($phone, 'Mensaje de prueba de ' . (string) Settings::get('business_name', 'Agenda Premium') . '.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Prueba de WhatsApp falló', $e);
            $r = ['ok' => false, 'error' => 'No pudimos enviar el mensaje.'];
        }
        $this->audit('communications.test_whatsapp', !empty($r['ok']) ? 'Prueba enviada' : 'Prueba fallida');
        if (!empty($r['ok'])) {
            $this->flash('success', 'Enviamos el mensaje de prueba por la API de WhatsApp.');
        } else {
            $this->flash('error', 'No se pudo enviar: ' . $this->readable($r['error'] ?? null));
        }
        return $this->redirect('/admin/comunicaciones');
    }

    public function regenerateCron(Request $req, array $p): Response
    {
        Settings::set('cron_token', Str::token(20));
        $this->audit('system.cron_token', 'Se regeneró el token del cron');
        $this->flash('success', 'Generamos un token nuevo. Actualiza la URL en tu servicio de tareas programadas; la anterior ya no funciona.');
        return $this->redirect('/admin/comunicaciones');
    }

    private function throttle(string $what): bool
    {
        try {
            return RateLimiter::hit('a4test:' . $what . ':' . (int) (Auth::user()['id'] ?? 0), 12, 600);
        } catch (\Throwable $e) {
            return true;
        }
    }

    /** Mensaje de error corto y sin datos sensibles. */
    private function readable($err): string
    {
        $s = trim((string) $err);
        if ($s === '') {
            return 'revisa el servidor, el puerto y las credenciales.';
        }
        return mb_substr(preg_replace('/\s+/', ' ', $s) ?? $s, 0, 300);
    }
}
