<?php
declare(strict_types=1);

namespace App\Controllers\Pub;

use App\Core\Db;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Upload;
use App\Services\AnalyticsService;
use App\Services\EventRepository;

/** Utilidades compartidas por los controladores públicos (sin sesión ni cookies). */
final class PubSupport
{
    public const MODES = [
        'in_person' => ['Presencial', 'map-pin'],
        'video_auto' => ['Videollamada', 'video'],
        'video_custom' => ['Videollamada', 'video'],
        'phone' => ['Llamada telefónica', 'phone'],
        'home' => ['A domicilio', 'home'],
    ];

    /** Token de 32 caracteres hexadecimales o 404 (el enrutador no admite {32} anidado). */
    public static function token(array $p, string $key = 'token'): string
    {
        $t = (string) ($p[$key] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $t)) {
            throw new HttpException(404);
        }
        return $t;
    }

    public static function modeLabel(string $mode): string
    {
        return self::MODES[$mode][0] ?? 'Presencial';
    }

    public static function modeIcon(string $mode): string
    {
        return self::MODES[$mode][1] ?? 'map-pin';
    }

    /** "30,60,90" -> [30,60,90] válidas y ordenadas; siempre incluye la duración por defecto si la lista queda vacía. */
    public static function durations(array $event): array
    {
        $list = [];
        if (!empty($event['duration_list']) && is_array($event['duration_list'])) {
            $list = $event['duration_list'];
        } else {
            $list = explode(',', (string) ($event['duration_options'] ?? ''));
        }
        $out = [];
        foreach ($list as $d) {
            $d = (int) $d;
            if ($d >= 5 && $d <= 1440) {
                $out[$d] = $d;
            }
        }
        if (!$out) {
            $out[(int) ($event['default_duration'] ?? 30)] = (int) ($event['default_duration'] ?? 30);
        }
        sort($out);
        return array_values($out);
    }

    public static function defaultDuration(array $event, array $durations): int
    {
        $d = (int) ($event['default_duration'] ?? 0);
        return in_array($d, $durations, true) ? $d : $durations[0];
    }

    /** Opciones de una pregunta personalizada: JSON, o una por línea. */
    public static function options(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        if ($raw[0] === '[') {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                return array_values(array_filter(array_map(static fn($v) => Str::clean(is_array($v) ? (string) ($v['label'] ?? $v['value'] ?? '') : (string) $v, 160), $j), static fn($v) => $v !== ''));
            }
        }
        $lines = preg_split('/\R|,/', $raw) ?: []; // igual que BookingService: una por línea o separadas por coma
        return array_values(array_filter(array_map(static fn($v) => Str::clean($v, 160), $lines), static fn($v) => $v !== ''));
    }

    public static function priceText(array $event): string
    {
        $p = (float) ($event['price'] ?? 0);
        return $p > 0 ? \App\Core\Fmt::money($p) : 'Sin costo';
    }

    public static function safeUrl(?string $u): string
    {
        $u = trim((string) $u);
        if ($u === '' || strlen($u) > 500) {
            return '';
        }
        $s = strtolower((string) parse_url($u, PHP_URL_SCHEME));
        return in_array($s, ['http', 'https'], true) && filter_var($u, FILTER_VALIDATE_URL) ? $u : '';
    }

    public static function waLink(string $phone, string $text = ''): string
    {
        $d = Str::phone($phone, (string) Settings::get('phone_cc', '502'));
        if ($d === null) {
            return '';
        }
        if (class_exists('App\\Services\\WhatsAppService')) {
            try {
                return \App\Services\WhatsAppService::link($d, $text);
            } catch (\Throwable $e) {
                // se usa el enlace propio
            }
        }
        return 'https://wa.me/' . $d . ($text !== '' ? '?text=' . rawurlencode($text) : '');
    }

    /** Datos del negocio para encabezados, pie y contacto. */
    public static function biz(): array
    {
        $wa = trim((string) Settings::get('whatsapp', ''));
        $phone = trim((string) Settings::get('phone', ''));
        $social = [];
        foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'] as $k => $label) {
            $u = self::safeUrl((string) Settings::get('social_' . $k, ''));
            if ($u !== '') {
                $social[$k] = ['label' => $label, 'url' => $u];
            }
        }
        $cc = (string) Settings::get('phone_cc', '502');
        return [
            'name' => (string) Settings::get('business_name', 'Agenda Premium'),
            'tagline' => (string) Settings::get('tagline', ''),
            'about' => (string) Settings::get('about', ''),
            'logo' => Upload::url((int) Settings::get('logo_file_id', 0)),
            'hero' => Upload::url((int) Settings::get('hero_file_id', 0)),
            'whatsapp' => $wa,
            'wa_link' => $wa !== '' ? self::waLink($wa) : '',
            'wa_display' => $wa !== '' ? Str::phoneDisplay(Str::phone($wa, $cc)) : '',
            'phone' => $phone,
            'phone_display' => $phone !== '' ? Str::phoneDisplay(Str::phone($phone, $cc)) : '',
            'phone_tel' => $phone !== '' && Str::phone($phone, $cc) ? '+' . Str::phone($phone, $cc) : '',
            'email' => (string) Settings::get('email', ''),
            'address' => (string) Settings::get('address', ''),
            'map_url' => self::safeUrl((string) Settings::get('map_url', '')),
            'website' => self::safeUrl((string) Settings::get('website', '')),
            'social' => $social,
            'profession' => (string) Settings::get('profession', 'otro'),
            'terms_label' => (string) Settings::get('terms_label', 'cita'),
            'host_label' => (string) Settings::get('host_label', 'profesional'),
            'cookies_notice' => trim((string) Settings::get('cookies_notice', '')),
        ];
    }

    /** Escena SVG del tema (Diseño) cuando no hay imagen subida: assets/img/scenes/<profesión>.svg o la primera disponible. */
    public static function sceneUrl(): string
    {
        $dir = APP_ROOT . '/assets/img/scenes/';
        $map = [
            'medico' => 'consultorio-medico', 'nutricionista' => 'consultorio-medico', 'fisioterapeuta' => 'consultorio-medico',
            'veterinario' => 'consultorio-medico', 'dentista' => 'odontologia', 'psicologo' => 'psicologia',
            'abogado' => 'legal-notarial', 'notario' => 'legal-notarial', 'contador' => 'contabilidad',
            'arquitecto_ingeniero' => 'arquitectura-ingenieria', 'consultor_coach' => 'reunion-comercial',
            'ventas_reuniones' => 'reunion-comercial', 'estetica_spa' => 'spa-estetica', 'academia_tutor' => 'academia-tutoria',
        ];
        $prof = (string) Settings::get('profession', 'otro');
        foreach ([($map[$prof] ?? 'tiempo-y-oro'), 'tiempo-y-oro'] as $f) {
            if (is_file($dir . $f . '.svg')) {
                return asset('img/scenes/' . $f . '.svg');
            }
        }
        $any = glob($dir . '*.svg') ?: [];
        sort($any, SORT_STRING);
        return $any ? asset('img/scenes/' . basename($any[0])) : '';
    }

    /** Eventos públicos listos para mostrar como tarjetas. */
    public static function publicEvents(?array $onlyIds = null): array
    {
        $out = [];
        foreach (EventRepository::publicList() as $e) {
            if ($onlyIds !== null && !in_array((int) $e['id'], $onlyIds, true)) {
                continue;
            }
            $durs = self::durations($e);
            $e['_durations'] = $durs;
            $e['_mode_label'] = self::modeLabel((string) ($e['mode'] ?? 'in_person'));
            $e['_mode_icon'] = self::modeIcon((string) ($e['mode'] ?? 'in_person'));
            $e['_price_text'] = self::priceText($e);
            $e['_color'] = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#C9A050';
            $out[] = $e;
        }
        return $out;
    }

    /** Anfitriones activos con perfil público. */
    public static function publicHosts(): array
    {
        $rows = Db::all('SELECT id, name, slug, title, bio, photo_file_id, color FROM hosts WHERE active = 1 AND public_profile = 1 ORDER BY sort_order, name');
        foreach ($rows as &$h) {
            $h['_photo'] = Upload::url((int) $h['photo_file_id']);
            $h['_initial'] = mb_strtoupper(mb_substr((string) $h['name'], 0, 1));
        }
        return $rows;
    }

    /** Anfitriones de un evento (solo datos públicos). */
    public static function eventHosts(int $eventId): array
    {
        $rows = Db::all(
            'SELECT h.id, h.name, h.slug, h.title, h.photo_file_id FROM event_hosts eh JOIN hosts h ON h.id = eh.host_id WHERE eh.event_type_id = ? AND h.active = 1 ORDER BY h.sort_order, h.name',
            [$eventId]
        );
        foreach ($rows as &$h) {
            $h['_photo'] = Upload::url((int) $h['photo_file_id']);
            $h['_initial'] = mb_strtoupper(mb_substr((string) $h['name'], 0, 1));
        }
        return $rows;
    }

    /** Reseñas aprobadas (sin datos de contacto). */
    public static function reviews(?int $hostId = null, int $limit = 6): array
    {
        $sql = "SELECT client_name, rating, comment, submitted_at FROM reviews WHERE status = 'approved' AND rating IS NOT NULL";
        $params = [];
        if ($hostId !== null) {
            $sql .= ' AND host_id = ?';
            $params[] = $hostId;
        }
        $sql .= ' ORDER BY submitted_at DESC, id DESC LIMIT ' . max(1, min(50, $limit));
        $rows = Db::all($sql, $params);
        foreach ($rows as &$r) {
            $r['_name'] = self::firstNameInitial((string) $r['client_name']);
        }
        return $rows;
    }

    /** "María López" -> "María L." (privacidad en reseñas públicas). */
    public static function firstNameInitial(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = (string) ($parts[0] ?? '');
        $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) . '.' : '';
        return trim($first . ' ' . $last);
    }

    /** Zonas horarias agrupadas por región para el selector. */
    public static function tzGroups(): array
    {
        $g = [];
        foreach (Tz::list() as $id) {
            [$region, $city] = explode('/', $id, 2) + ['', ''];
            $g[$region][] = ['id' => $id, 'label' => str_replace('_', ' ', $city)];
        }
        ksort($g);
        return $g;
    }

    /** Registra un paso del embudo sin lanzar jamás. */
    public static function track(string $step, Request $req, array $ctx): void
    {
        if (!class_exists(AnalyticsService::class)) {
            return;
        }
        try {
            $vid = (string) ($ctx['visit_id'] ?? '');
            if (!preg_match('/^[a-f0-9]{16}$/', $vid)) {
                return;
            }
            $ref = (string) ($ctx['referrer_host'] ?? '');
            AnalyticsService::track($step, [
                'event_type_id' => isset($ctx['event_type_id']) ? (int) $ctx['event_type_id'] : null,
                'host_id' => !empty($ctx['host_id']) ? (int) $ctx['host_id'] : null,
                'visit_id' => $vid,
                'utm_source' => self::short($ctx['utm_source'] ?? null, 100),
                'utm_medium' => self::short($ctx['utm_medium'] ?? null, 100),
                'utm_campaign' => self::short($ctx['utm_campaign'] ?? null, 100),
                'referrer_host' => preg_match('/^[A-Za-z0-9.\-]{1,190}$/', $ref) ? $ref : null,
                'device' => $req->isMobile() ? 'mobile' : 'desktop',
            ]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Analítica pública falló', $e);
        }
    }

    public static function short($v, int $max): ?string
    {
        if (!is_scalar($v)) {
            return null;
        }
        $s = Str::clean((string) $v, $max);
        return $s === '' ? null : $s;
    }

    /** Cubeta de límite de frecuencia por IP (hash para no almacenar la IP completa). */
    public static function bucket(Request $req, string $name): string
    {
        return $name . ':' . substr(hash('sha256', $req->ip()), 0, 20);
    }

    /** Tokens CSRF públicos firmados para los dos alcances que usan las páginas sin sesión. */
    public static function csrfSet(): array
    {
        return ['book' => \App\Core\Csrf::publicToken('book'), 'manage' => \App\Core\Csrf::publicToken('manage')];
    }

    public static function hexColor(?string $c, string $fallback = ''): string
    {
        $c = (string) $c;
        if (preg_match('/^#?([0-9A-Fa-f]{6})$/', $c, $m)) {
            return '#' . strtoupper($m[1]);
        }
        return $fallback;
    }
}
