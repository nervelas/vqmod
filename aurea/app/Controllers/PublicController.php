<?php
declare(strict_types=1);

namespace Aurea\Controllers;

use Aurea\Core\App;
use Aurea\Core\Brand;
use Aurea\Core\Captcha;
use Aurea\Core\Controller;
use Aurea\Core\Csrf;
use Aurea\Core\Db;
use Aurea\Core\Ics;
use Aurea\Core\Response;
use Aurea\Core\Settings;
use Aurea\Core\Util;
use Aurea\Core\View;
use Aurea\Services\AvailabilityService;
use Aurea\Services\FormService;

final class PublicController extends Controller
{
    public static function services(): array
    {
        $rows = Db::all('SELECT s.*, c.name cat_name, c.sort cat_sort FROM services s LEFT JOIN categories c ON c.id=s.category_id
            WHERE s.active=1 AND (c.id IS NULL OR c.active=1)
            AND EXISTS (SELECT 1 FROM professional_services ps JOIN professionals p ON p.id=ps.professional_id AND p.active=1 WHERE ps.service_id=s.id)
            ORDER BY COALESCE(c.sort,999), c.id, s.sort, s.name');
        return $rows;
    }

    public static function professionals(): array
    {
        return Db::all('SELECT * FROM professionals WHERE active=1 ORDER BY sort,name');
    }

    public static function reviewStats(): array
    {
        $r = Db::one("SELECT COUNT(*) n, AVG(rating) avg FROM reviews WHERE status='approved'");
        return ['n' => (int)($r['n'] ?? 0), 'avg' => round((float)($r['avg'] ?? 0), 1)];
    }

    public static function hoursSummary(): array
    {
        $days = [1, 2, 3, 4, 5, 6, 0];
        $out = [];
        foreach ($days as $wd) {
            $rows = Db::all('SELECT s.start_time, s.end_time FROM schedules s JOIN professionals p ON p.id=s.professional_id AND p.active=1 WHERE s.weekday=? ORDER BY s.start_time', [$wd]);
            $blocks = [];
            foreach ($rows as $r) {
                $s = Util::minutes($r['start_time']); $e = Util::minutes($r['end_time']);
                if ($blocks && $s <= $blocks[count($blocks) - 1][1]) { $blocks[count($blocks) - 1][1] = max($blocks[count($blocks) - 1][1], $e); } else { $blocks[] = [$s, $e]; }
            }
            $out[$wd] = $blocks;
        }
        return $out;
    }

    public function home(): Response
    {
        $services = self::services();
        View::$title = '';
        $stats = self::reviewStats();
        $reviews = Settings::bool('reviews_public', true) ? Db::all("SELECT r.rating, r.comment, r.created_at, c.name client_name, p.name prof_name FROM reviews r JOIN clients c ON c.id=r.client_id JOIN professionals p ON p.id=r.professional_id
            WHERE r.status='approved' AND r.comment<>'' ORDER BY r.created_at DESC LIMIT 6") : [];
        $profs = self::professionals();
        $locs = Db::all('SELECT * FROM locations WHERE active=1 ORDER BY sort,name');
        View::$head[] = '<script type="application/ld+json">' . json_encode($this->schema($profs, $stats, $locs), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
        return $this->html('public', 'public/home', compact('services', 'profs', 'reviews', 'stats', 'locs') + ['hours' => self::hoursSummary()]);
    }

    private function schema(array $profs, array $stats, array $locs): array
    {
        $s = ['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => (string)Settings::get('business_name', ''),
            'url' => abs_url('/'), 'description' => (string)Settings::get('seo_description', Settings::get('hero_subtitle', ''))];
        if (Settings::get('business_phone', '') !== '') { $s['telephone'] = (string)Settings::get('business_phone'); }
        if (Settings::get('business_email', '') !== '') { $s['email'] = (string)Settings::get('business_email'); }
        $addr = (string)Settings::get('business_address', '');
        if ($addr !== '') { $s['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $addr, 'addressCountry' => 'GT']; }
        if (Brand::logoUrl() !== '') { $s['image'] = abs_url('uploads/' . rawurlencode((string)Settings::get('logo'))); }
        $same = [];
        foreach (['instagram', 'facebook', 'tiktok', 'linkedin', 'youtube'] as $k) { $u = Util::safeUrl((string)Settings::get('social_' . $k, '')); if ($u !== '') { $same[] = $u; } }
        if ($same) { $s['sameAs'] = $same; }
        $names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $spec = [];
        foreach (self::hoursSummary() as $wd => $blocks) {
            foreach ($blocks as [$a, $b]) { $spec[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $names[$wd], 'opens' => sprintf('%02d:%02d', intdiv($a, 60), $a % 60), 'closes' => sprintf('%02d:%02d', intdiv($b, 60), $b % 60)]; }
        }
        if ($spec) { $s['openingHoursSpecification'] = $spec; }
        if ($stats['n'] > 0) { $s['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $stats['avg'], 'reviewCount' => $stats['n']]; }
        $s['potentialAction'] = ['@type' => 'ReserveAction', 'target' => abs_url('/reservar'), 'result' => ['@type' => 'Reservation', 'name' => term('appt')]];
        return $s;
    }

    public function booking(): Response
    {
        $services = self::services();
        $profs = self::professionals();
        $svcIds = array_column($services, 'id');
        $ps = $svcIds ? Db::all('SELECT professional_id, service_id, price_override FROM professional_services') : [];
        $pl = Db::all('SELECT professional_id, location_id FROM professional_locations');
        $fieldsBySvc = [];
        foreach ($services as $s) {
            $fieldsBySvc[(int)$s['id']] = array_map(static fn($f) => [
                'id' => (int)$f['id'], 'label' => $f['label'], 'type' => $f['ftype'], 'options' => FormService::options($f), 'required' => (bool)$f['required'],
                'help' => $f['help'], 'cond_field' => $f['cond_field_id'] ? (int)$f['cond_field_id'] : null, 'cond_value' => $f['cond_value'],
            ], FormService::fieldsFor((int)$s['id']));
        }
        $locs = Db::all('SELECT id,name,address,city FROM locations WHERE active=1 ORDER BY sort,name');
        $tz = (string)Settings::get('timezone', 'America/Guatemala');
        $cap = Captcha::provider();
        $boot = [
            'base' => base_path(),
            'today' => date('Y-m-d'),
            'csrf' => Csrf::token('public'),
            'embed' => App::$embed,
            'currency' => Settings::get('currency', 'GTQ') === 'USD' ? 'US$' : 'Q',
            'showPrices' => Settings::get('show_prices', '1') === '1',
            'emailRequired' => Settings::get('email_required', '0') === '1',
            'privacyRequired' => Settings::get('privacy_required', '1') === '1',
            'tzLabel' => 'hora de ' . str_replace('_', ' ', (string)(explode('/', $tz)[1] ?? $tz)) . ' (UTC' . date('P') . ')',
            'terms' => ['appt' => term('appt'), 'professional' => term('professional'), 'client' => term('client')],
            'waitlist' => Settings::get('waitlist_enabled', '1') === '1',
            'captcha' => ['provider' => $cap, 'siteKey' => $cap === 'none' ? '' : (string)Settings::get('captcha_site_key', '')],
            'whatsapp' => Brand::waUrl(),
            'pre' => ['service' => $this->req->int('servicio'), 'professional' => $this->req->int('profesional')],
            'services' => array_map(static fn($s) => [
                'id' => (int)$s['id'], 'name' => $s['name'], 'desc' => (string)$s['description'], 'duration' => (int)$s['duration_min'], 'price' => (float)$s['price'],
                'modality' => $s['modality'], 'deposit_type' => $s['deposit_type'], 'deposit_value' => (float)$s['deposit_value'], 'capacity' => (int)$s['capacity'],
                'category' => $s['category_id'] ? (int)$s['category_id'] : 0, 'category_name' => (string)($s['cat_name'] ?? ''), 'auto_confirm' => (bool)$s['auto_confirm'],
            ], $services),
            'professionals' => array_map(static function ($p) use ($ps, $pl) {
                $sv = [];
                foreach ($ps as $r) { if ((int)$r['professional_id'] === (int)$p['id']) { $sv[(string)$r['service_id']] = $r['price_override'] !== null ? (float)$r['price_override'] : null; } }
                $loc = [];
                foreach ($pl as $r) { if ((int)$r['professional_id'] === (int)$p['id']) { $loc[] = (int)$r['location_id']; } }
                return ['id' => (int)$p['id'], 'name' => $p['name'], 'title' => $p['title'], 'photo' => $p['photo'] !== '' ? url('uploads/' . rawurlencode($p['photo'])) : '', 'services' => (object)$sv, 'locations' => $loc];
            }, $profs),
            'locations' => $locs,
            'fields' => (object)$fieldsBySvc,
        ];
        View::$title = __('Reservar %s', mb_strtolower(term('appt')));
        View::$robots = App::$embed ? 'noindex' : '';
        return $this->html('public', 'public/booking', ['boot' => $boot, 'scripts' => ['js/booking.js'], 'noFab' => true]);
    }

    public function team(): Response
    {
        View::$title = term('professionals');
        $profs = self::professionals();
        return $this->html('public', 'public/team', compact('profs'));
    }

    public function professional(): Response
    {
        $p = Db::one('SELECT * FROM professionals WHERE slug=? AND active=1', [$this->req->params['slug'] ?? '']);
        if (!$p) { return $this->notFound(); }
        $svc = Db::all('SELECT s.*, ps.price_override FROM services s JOIN professional_services ps ON ps.service_id=s.id AND ps.professional_id=? WHERE s.active=1 ORDER BY s.sort,s.name', [$p['id']]);
        $reviews = Settings::bool('reviews_public', true) ? Db::all("SELECT r.rating, r.comment, r.created_at, c.name client_name FROM reviews r JOIN clients c ON c.id=r.client_id WHERE r.professional_id=? AND r.status='approved' ORDER BY r.created_at DESC LIMIT 10", [$p['id']]) : [];
        View::$title = $p['name'];
        View::$description = Util::limit(strip_tags((string)$p['bio']), 150);
        return $this->html('public', 'public/professional', compact('p', 'svc', 'reviews'));
    }

    public function privacy(): Response
    {
        View::$title = __('Aviso de privacidad');
        return $this->html('public', 'public/page', ['heading' => __('Aviso de privacidad'), 'text' => str_replace('{negocio}', (string)Settings::get('business_name', ''), (string)Settings::get('privacy_text', ''))]);
    }

    public function terms(): Response
    {
        View::$title = __('Términos y condiciones');
        return $this->html('public', 'public/page', ['heading' => __('Términos y condiciones'), 'text' => (string)Settings::get('terms_text', '')]);
    }

    public function sitemap(): Response
    {
        $u = ['/', '/reservar', '/equipo', '/privacidad', '/terminos'];
        foreach (self::professionals() as $p) { $u[] = '/profesional/' . $p['slug']; }
        $x = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($u as $path) { $x .= '<url><loc>' . e(abs_url($path)) . '</loc></url>'; }
        return Response::text($x . '</urlset>', 'application/xml');
    }

    public function robots(): Response
    {
        return Response::text("User-agent: *\nDisallow: /admin\nDisallow: /cita/\nDisallow: /embed\nDisallow: /api/\nSitemap: " . abs_url('/sitemap.xml') . "\n");
    }

    /** Feed ICS privado por profesional (se suscribe desde Google/Apple Calendar). */
    public function icsFeed(): Response
    {
        $p = Db::one('SELECT * FROM professionals WHERE ics_token=? AND active=1', [$this->req->params['token'] ?? '']);
        if (!$p) { return $this->notFound(); }
        $rows = Db::all("SELECT a.*, c.name client_name, c.phone_cc, c.phone client_phone, s.name service_name, l.address loc_address
            FROM appointments a JOIN clients c ON c.id=a.client_id JOIN services s ON s.id=a.service_id LEFT JOIN locations l ON l.id=a.location_id
            WHERE a.professional_id=? AND a.status IN ('pending','confirmed','completed') AND a.start_at>=? ORDER BY a.start_at LIMIT 1000",
            [$p['id'], date('Y-m-d H:i:s', time() - 30 * 86400)]);
        $ev = [];
        foreach ($rows as $a) {
            $ev[] = ['uid' => 'appt-' . $a['id'] . '@' . preg_replace('/[^a-z0-9.\-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'aurea')), 'start' => $a['start_at'], 'end' => $a['end_at'],
                'summary' => $a['service_name'] . ' · ' . $a['client_name'], 'status' => $a['status'],
                'description' => 'Cliente: ' . $a['client_name'] . ' (+' . $a['phone_cc'] . ' ' . $a['client_phone'] . ')' . "\nEstado: " . \Aurea\Services\BookingService::STATUSES[$a['status']],
                'location' => (string)$a['loc_address'], 'url' => abs_url('/admin/citas/' . $a['id'])];
        }
        $r = Response::text(Ics::calendar($ev, (string)Settings::get('business_name', 'Citas') . ' · ' . $p['name']), 'text/calendar');
        $r->headers['Cache-Control'] = 'private, max-age=300';
        return $r;
    }
}
