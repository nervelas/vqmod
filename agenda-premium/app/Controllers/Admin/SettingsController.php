<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Upload;
use App\Core\Validator;
use App\Core\Auth;

/** Marca y ajustes generales del negocio. */
final class SettingsController extends A4Controller
{
    /** Fondo oscuro de referencia para el contraste del oro de marca. */
    private const DARK_BG = '#06080D';
    private const FILES = ['logo' => 'logo_file_id', 'favicon' => 'favicon_file_id', 'hero' => 'hero_file_id'];

    public function index(Request $req, array $p): Response
    {
        return $this->render([], []);
    }

    public function save(Request $req, array $p): Response
    {
        $errors = [];
        $vals = [];
        $warn = [];

        $text = function (string $key, int $max, bool $required = false, string $label = '') use ($req, &$errors, &$vals): void {
            $v = $req->str($key, $max);
            if (preg_match('/[<>]/', $v)) {
                $errors[$key] = 'No uses los signos < ni > en este campo.';
                return;
            }
            if ($required && $v === '') {
                $errors[$key] = 'Escribe ' . ($label ?: 'este dato') . '.';
                return;
            }
            $vals[$key] = $v;
        };
        $text('business_name', 120, true, 'el nombre del negocio');
        $text('tagline', 160);
        $about = trim((string) ($req->post['about'] ?? ''));
        $vals['about'] = mb_substr(Str::clean($about, 4000), 0, 2000);

        // Color de oro
        $gold = strtoupper($req->str('color_gold', 7));
        if (!Validator::color($gold)) {
            $errors['color_gold'] = 'Usa un color con formato #RRGGBB, por ejemplo #C9A050.';
        } else {
            $vals['color_gold'] = $gold;
            $ratio = self::contrast($gold, self::DARK_BG);
            if ($ratio < 4.5) {
                $warn[] = 'El color ' . $gold . ' tiene un contraste de ' . number_format($ratio, 1) . ':1 sobre el fondo oscuro; el mínimo recomendado (AA) es 4,5:1. Los textos en oro podrían costar trabajo de leer.';
            }
        }

        // Contacto
        $cc = preg_replace('/\D+/', '', $req->str('phone_cc', 6)) ?? '';
        if ($cc === '' || strlen($cc) > 4) {
            $errors['phone_cc'] = 'Escribe el prefijo del país con números, por ejemplo 502.';
        } else {
            $vals['phone_cc'] = $cc;
        }
        foreach (['whatsapp' => 'WhatsApp', 'phone' => 'teléfono'] as $k => $label) {
            $raw = $req->str($k, 30);
            if ($raw === '') {
                $vals[$k] = '';
                continue;
            }
            $ph = Str::phone($raw, $cc !== '' ? $cc : '502');
            if ($ph === null) {
                $errors[$k] = 'El número de ' . $label . ' no es válido. Usa 8 dígitos o el formato +502 5555 1234.';
            } else {
                $vals[$k] = $ph;
            }
        }
        $email = $req->str('email', 190);
        if ($email !== '' && !Validator::email($email)) {
            $errors['email'] = 'Ese correo no parece válido.';
        } else {
            $vals['email'] = $email;
        }
        $text('address', 255);
        $map = $req->str('map_url', 500);
        if ($map !== '' && !$this->isHttpsUrl($map)) {
            $errors['map_url'] = 'Pega un enlace que empiece con https:// (por ejemplo, el de “Compartir” en tu mapa).';
        } else {
            $vals['map_url'] = $map;
        }
        foreach (['website' => 'tu sitio web', 'social_facebook' => 'Facebook', 'social_instagram' => 'Instagram', 'social_tiktok' => 'TikTok', 'social_youtube' => 'YouTube'] as $k => $label) {
            $u = $req->str($k, 300);
            if ($u !== '' && !$this->isHttpsUrl($u)) {
                $errors[$k] = 'El enlace de ' . $label . ' debe empezar con https://';
            } else {
                $vals[$k] = $u;
            }
        }

        // Regionales
        $tz = $req->str('timezone', 64);
        if (!Tz::valid($tz)) {
            $errors['timezone'] = 'Elige una zona horaria de la lista.';
        } else {
            $vals['timezone'] = $tz;
        }
        $vals['time_format'] = $this->pick($req, 'time_format', ['12', '24'], '12');
        $cur = $req->str('currency_symbol', 6);
        if ($cur === '' || preg_match('/[<>]/', $cur)) {
            $errors['currency_symbol'] = 'Escribe el símbolo de moneda, por ejemplo Q.';
        } else {
            $vals['currency_symbol'] = $cur;
        }

        // Terminología
        $profs = $this->professionKeys();
        $prof = $req->str('profession', 40);
        if ($profs && !in_array($prof, $profs, true) && $prof !== (string) Settings::get('profession', 'otro')) {
            $errors['profession'] = 'Elige una profesión de la lista.';
        } else {
            $vals['profession'] = preg_match('/^[a-z_]{1,40}$/', $prof) ? $prof : 'otro';
        }
        foreach (['terms_label' => 'el nombre de la cita', 'terms_label_plural' => 'el plural de cita', 'host_label' => 'cómo llamas a quien atiende', 'client_label' => 'cómo llamas a quien reserva'] as $k => $label) {
            $text($k, 30, true, $label);
        }

        // Reservas
        $vals['public_home_enabled'] = $req->bool('public_home_enabled') ? '1' : '0';
        $int = function (string $key, int $min, int $max, string $msg) use ($req, &$errors, &$vals): void {
            $n = Validator::intRange($req->str($key, 8), $min, $max);
            if ($n === null) {
                $errors[$key] = $msg;
            } else {
                $vals[$key] = (string) $n;
            }
        };
        $int('pending_expire_hours', 1, 720, 'Indica entre 1 y 720 horas.');
        $int('waitlist_offer_minutes', 5, 1440, 'Indica entre 5 y 1440 minutos.');
        $int('noshow_block_after', 0, 20, 'Indica un número entre 0 y 20 (0 = nunca bloquear).');
        $int('noshow_deposit_after', 0, 20, 'Indica un número entre 0 y 20 (0 = nunca pedir anticipo).');
        $int('noshow_deposit_percent', 1, 100, 'Indica un porcentaje entre 1 y 100.');
        $video = strtolower($req->str('video_provider_domain', 120));
        if (!preg_match('/^(?=.{3,120}$)([a-z0-9]([a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,}$/', $video)) {
            $errors['video_provider_domain'] = 'Escribe solo el dominio, por ejemplo meet.jit.si (sin https://).';
        } else {
            $vals['video_provider_domain'] = $video;
        }
        $origins = $this->origins($req->str('embed_allowed_origins', 1000));
        if ($origins === null) {
            $errors['embed_allowed_origins'] = 'Escribe orígenes completos separados por espacios, por ejemplo https://mi-sitio.com, o * para permitir todos.';
        } else {
            $vals['embed_allowed_origins'] = $origins;
        }

        // Pagos
        $vals['bank_info'] = mb_substr(Str::clean((string) ($req->post['bank_info'] ?? ''), 1500), 0, 1000);
        $pl = $req->str('payment_link', 500);
        if ($pl !== '' && !$this->isHttpsUrl($pl)) {
            $errors['payment_link'] = 'El enlace de pago debe empezar con https://';
        } else {
            $vals['payment_link'] = $pl;
        }

        // Imágenes: validar antes de guardar nada
        $uploads = [];
        foreach (self::FILES as $slot => $key) {
            $f = $req->files[$slot] ?? null;
            if (is_array($f) && isset($f['error']) && !is_array($f['error']) && $f['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploads[$slot] = $f;
            }
        }

        if ($errors) {
            return $this->render($req->post, $errors, 422, $warn);
        }

        $stored = [];
        foreach ($uploads as $slot => $f) {
            try {
                $row = Upload::store($f, [
                    'images_only' => true,
                    'is_public' => 1,
                    'kind' => 'brand_' . $slot,
                    'owner_type' => 'settings',
                    'uploaded_by' => Auth::user()['id'] ?? null,
                    'max_bytes' => 4 * 1024 * 1024,
                    'max_side' => $slot === 'hero' ? 2400 : ($slot === 'favicon' ? 256 : 800),
                ]);
                $stored[$slot] = (int) $row['id'];
            } catch (\RuntimeException $e) {
                $labels = ['logo' => 'el logo', 'favicon' => 'el favicon', 'hero' => 'la imagen de portada'];
                $errors[$slot] = 'No pudimos usar ' . $labels[$slot] . ': ' . $e->getMessage();
            }
        }
        if ($errors) {
            foreach ($stored as $id) {
                Upload::delete($id);
            }
            return $this->render($req->post, $errors, 422, $warn);
        }

        $changed = [];
        foreach ($vals as $k => $v) {
            if ((string) Settings::get($k, '') !== $v) {
                $changed[] = $k;
            }
        }
        Settings::setMany($vals);

        foreach (self::FILES as $slot => $key) {
            $oldId = (int) Settings::get($key, 0);
            if (isset($stored[$slot])) {
                Settings::set($key, (string) $stored[$slot]);
                $changed[] = $key;
                $this->dropBrandFile($oldId);
            } elseif ($req->bool('remove_' . $slot) && $oldId > 0) {
                Settings::set($key, '');
                $changed[] = $key;
                $this->dropBrandFile($oldId);
            }
        }
        \App\Core\Cache::clearAll();
        $this->audit('settings.update', $changed ? 'Cambió: ' . implode(', ', $changed) : 'Sin cambios');
        $this->flash('success', 'Guardamos los ajustes de tu negocio.');
        foreach ($warn as $w) {
            $this->flash('warn', $w);
        }
        return $this->redirect('/admin/ajustes');
    }

    /** Borra el archivo anterior solo si es una imagen de marca (no toca otros archivos). */
    private function dropBrandFile(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        $kind = (string) Db::val('SELECT kind FROM files WHERE id = ?', [$id]);
        if (strpos($kind, 'brand_') === 0) {
            Upload::delete($id);
        }
    }

    private function render(array $old, array $errors, int $status = 200, array $warn = []): Response
    {
        $s = Settings::all();
        $form = [];
        foreach ($s as $k => $v) {
            $form[$k] = array_key_exists($k, $old) && !is_array($old[$k]) ? (string) $old[$k] : (string) $v;
        }
        $form['public_home_enabled'] = $old ? (isset($old['public_home_enabled']) ? '1' : '0') : $form['public_home_enabled'];
        $files = [];
        foreach (self::FILES as $slot => $key) {
            $files[$slot] = Upload::url((int) Settings::get($key, 0) ?: null);
        }
        $profs = [];
        if (class_exists('App\\Services\\ProfessionService')) {
            try {
                $profs = $this->professionOptions();
            } catch (\Throwable $e) {
                $profs = [];
            }
        }
        $res = $this->page('admin/settings/index', [
            'title' => 'Marca y ajustes',
            'f' => $form,
            'errors' => $errors,
            'warn' => $warn,
            'files' => $files,
            'zones' => Tz::list(),
            'professions' => $profs,
            'darkBg' => self::DARK_BG,
            'contrast' => Validator::color($form['color_gold']) ? self::contrast($form['color_gold'], self::DARK_BG) : 0.0,
            'styles' => [],
        ], ['js/admin-settings.js'], '/admin/ajustes');
        $res->status = $status;
        return $res;
    }

    /** @return array<string,string> clave => nombre */
    private function professionOptions(): array
    {
        $out = [];
        foreach ((array) \App\Services\ProfessionService::all() as $k => $item) {
            if (is_array($item)) {
                $key = (string) ($item['key'] ?? $item['id'] ?? $item['slug'] ?? (is_string($k) ? $k : ''));
                $name = (string) ($item['name'] ?? $item['label'] ?? $item['title'] ?? $key);
            } else {
                $key = is_string($k) ? $k : (string) $item;
                $name = (string) $item;
            }
            if ($key !== '') {
                $out[$key] = $name;
            }
        }
        return $out;
    }

    private function professionKeys(): array
    {
        if (!class_exists('App\\Services\\ProfessionService')) {
            return [];
        }
        try {
            return array_keys($this->professionOptions());
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Orígenes para frame-ancestors: solo * o https?://host[:puerto]; devuelve null si algo no es válido. */
    private function origins(string $raw): ?string
    {
        $raw = trim(str_replace([',', ';', "\n", "\r"], ' ', $raw));
        if ($raw === '') {
            return '*';
        }
        $out = [];
        foreach (preg_split('/\s+/', $raw) ?: [] as $o) {
            $o = strtolower($o);
            if ($o === '*' || preg_match('#^https?://(\*\.)?[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*(:\d{1,5})?$#', $o)) {
                $out[] = $o;
            } else {
                return null;
            }
        }
        $out = array_values(array_unique($out));
        return in_array('*', $out, true) ? '*' : implode(' ', $out);
    }

    /** Luminancia relativa WCAG de un color #RRGGBB. */
    public static function luminance(string $hex): float
    {
        $h = ltrim($hex, '#');
        $c = [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
        foreach ($c as &$v) {
            $v /= 255;
            $v = $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }
}
