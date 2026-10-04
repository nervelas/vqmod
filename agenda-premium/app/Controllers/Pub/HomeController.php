<?php
declare(strict_types=1);

namespace App\Controllers\Pub;

use App\Core\Controller;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Upload;

/** Inicio del negocio, equipo, perfiles de anfitrión y textos legales. */
final class HomeController extends Controller
{
    public function index(Request $req, array $p): Response
    {
        $biz = PubSupport::biz();
        $events = PubSupport::publicEvents();
        if (!Settings::bool('public_home_enabled')) {
            if ($events) {
                return Response::redirect(url('/e/' . $events[0]['slug']));
            }
            return Response::redirect(url('/admin/login'));
        }
        $hero = $biz['hero'] !== '' ? $biz['hero'] : PubSupport::sceneUrl();
        return $this->view('public/home', [
            'title' => '',
            'description' => $biz['tagline'] !== '' ? $biz['name'] . ': ' . $biz['tagline'] : 'Reserva tu ' . $biz['terms_label'] . ' en línea con ' . $biz['name'],
            'biz' => $biz,
            'events' => $events,
            'hosts' => PubSupport::publicHosts(),
            'reviews' => PubSupport::reviews(null, 6),
            'hero' => $hero,
            'heroIsUpload' => $biz['hero'] !== '',
            'nav' => 'home',
            'styles' => ['css/booking.css'],
            'scripts' => ['js/public.js'],
            'bodyClass' => 'pub-home',
        ], 'layouts/public');
    }

    public function team(Request $req, array $p): Response
    {
        $biz = PubSupport::biz();
        return $this->view('public/team', [
            'title' => 'Nuestro equipo',
            'description' => 'Conoce al equipo de ' . $biz['name'],
            'biz' => $biz,
            'hosts' => PubSupport::publicHosts(),
            'nav' => 'team',
            'scripts' => ['js/public.js'],
            'bodyClass' => 'pub-team',
        ], 'layouts/public');
    }

    public function host(Request $req, array $p): Response
    {
        $h = Db::one('SELECT id, name, slug, title, bio, photo_file_id, color FROM hosts WHERE slug = ? AND active = 1 AND public_profile = 1', [(string) $p['slug']]);
        if (!$h) {
            throw new HttpException(404);
        }
        $ids = array_map('intval', Db::col('SELECT event_type_id FROM event_hosts WHERE host_id = ?', [(int) $h['id']]));
        $biz = PubSupport::biz();
        return $this->view('public/host', [
            'title' => (string) $h['name'],
            'description' => Str::truncate(trim(strip_tags((string) ($h['bio'] ?? ''))), 160) ?: ('Agenda con ' . $h['name']),
            'biz' => $biz,
            'host' => $h,
            'photo' => Upload::url((int) $h['photo_file_id']),
            'events' => PubSupport::publicEvents($ids),
            'reviews' => PubSupport::reviews((int) $h['id'], 6),
            'nav' => 'team',
            'scripts' => ['js/public.js'],
            'bodyClass' => 'pub-host',
        ], 'layouts/public');
    }

    public function privacy(Request $req, array $p): Response
    {
        return $this->legal('privacy_text', 'privacy', 'Aviso de privacidad');
    }

    public function terms(Request $req, array $p): Response
    {
        return $this->legal('terms_text', 'terms', 'Términos del servicio');
    }

    private function legal(string $setting, string $docKey, string $title): Response
    {
        $text = trim((string) Settings::get($setting, ''));
        if ($text === '' && class_exists('App\\Services\\LegalService')) {
            try {
                $d = \App\Services\LegalService::defaults();
                $text = trim((string) ($d[$docKey] ?? ''));
            } catch (\Throwable $e) {
                $text = '';
            }
        }
        $biz = PubSupport::biz();
        return $this->view('public/legal', [
            'title' => $title,
            'biz' => $biz,
            'docTitle' => $title,
            'html' => $text !== '' ? Str::richText($text) : '',
            'version' => (string) Settings::get('legal_version', '1'),
            'nav' => '',
            'scripts' => ['js/public.js'],
            'bodyClass' => 'pub-legal',
        ], 'layouts/public');
    }
}
