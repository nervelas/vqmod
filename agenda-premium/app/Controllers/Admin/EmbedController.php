<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;

/** Insertar y compartir: generador de enlace, incrustación, botón flotante, WordPress y código QR. */
final class EmbedController extends A2Controller
{
    public function index(Request $req, array $p): Response
    {
        $events = Db::all("SELECT id, slug, name, color, active, visibility FROM event_types WHERE single_use = 0 ORDER BY sort_order, id");
        $hosts = Db::all('SELECT id, slug, name FROM hosts WHERE active = 1 ORDER BY sort_order, name');
        $boot = [
            'base' => rtrim(abs_url('/'), '/'),
            'events' => array_map(static fn (array $e): array => ['slug' => (string) $e['slug'], 'name' => (string) $e['name'], 'color' => (string) $e['color'], 'active' => (int) $e['active'] === 1, 'secret' => $e['visibility'] === 'secret'], $events),
            'hosts' => array_map(static fn (array $h): array => ['slug' => (string) $h['slug'], 'name' => (string) $h['name']], $hosts),
            'color' => (string) Settings::get('color_gold', '#C9A050'),
            'business' => (string) Settings::get('business_name', 'Agenda Premium'),
        ];
        return $this->page('admin/embed/index', ['events' => $events, 'hosts' => $hosts, 'boot' => $boot], '/admin/insertar', 'Insertar y compartir', ['vendor/qrcode-generator/qrcode.js', 'js/admin-embed.js']);
    }
}
