<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Upload;

/** Entrega protegida de archivos, manifiesto PWA y feed ICS por anfitrión. */
final class SystemController extends Controller
{
    /** /f/{token}: públicos (fotos, logo) para todos; privados solo con sesión y permiso. */
    public function file(Request $req, array $p): Response
    {
        $row = Db::one('SELECT * FROM files WHERE token = ?', [$p['token']]);
        if (!$row) {
            throw new HttpException(404);
        }
        $path = Upload::path($row);
        if (!is_file($path)) {
            throw new HttpException(404);
        }
        if ((int) $row['is_public'] !== 1) {
            Session::start($req);
            if (!Auth::check()) {
                throw new HttpException(403);
            }
            $scope = Auth::scopedHostId();
            if ($scope !== null) {
                $ok = false;
                if ($row['owner_type'] === 'booking' && $row['owner_id']) {
                    $hid = Db::val('SELECT host_id FROM bookings WHERE id = ?', [$row['owner_id']]);
                    $ok = $hid !== null && (int) $hid === $scope;
                }
                if (!$ok) {
                    throw new HttpException(403);
                }
            }
        }
        $inlineOk = in_array($row['mime'], Upload::IMAGE_MIMES, true) || $row['mime'] === 'application/pdf';
        $r = Response::file($path, (string) $row['mime'], (string) $row['original_name'], $inlineOk);
        $r->header('Cache-Control', (int) $row['is_public'] === 1 ? 'public, max-age=86400' : 'private, no-store');
        $r->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
        $r->noSecurityHeaders = true;
        $r->header('X-Content-Type-Options', 'nosniff');
        return $r;
    }

    public function manifest(Request $req, array $p): Response
    {
        $name = (string) Settings::get('business_name', 'Agenda Premium');
        $base = BASE_PATH === '' ? '/' : BASE_PATH . '/';
        $data = [
            'name' => $name . ' · Panel',
            'short_name' => mb_substr($name, 0, 12),
            'description' => 'Panel de agenda y citas de ' . $name,
            'lang' => 'es',
            'dir' => 'ltr',
            'start_url' => $base . 'admin/',
            'scope' => $base,
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#06080D',
            'theme_color' => '#06080D',
            'icons' => [
                ['src' => asset('img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('img/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];
        $r = Response::text((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, 'application/manifest+json');
        $r->header('Cache-Control', 'public, max-age=3600');
        return $r;
    }

    /** Feed ICS privado del anfitrión (suscripción desde Google/Apple/Outlook). */
    public function hostFeed(Request $req, array $p): Response
    {
        $host = Db::one('SELECT id FROM hosts WHERE ics_token = ? AND active = 1', [$p['token']]);
        if (!$host) {
            throw new HttpException(404);
        }
        $ics = \App\Services\IcsService::hostFeed((int) $host['id']);
        $r = Response::text($ics, 200, 'text/calendar');
        $r->header('Cache-Control', 'private, max-age=300');
        $r->header('Content-Disposition', 'inline; filename="agenda.ics"');
        return $r;
    }
}
