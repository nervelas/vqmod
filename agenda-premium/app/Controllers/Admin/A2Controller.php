<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/** Base común de las pantallas de configuración de agenda (eventos, equipo, horarios, calendarios). */
abstract class A2Controller extends Controller
{
    public const KINDS = [
        'individual' => 'Individual',
        'group' => 'Grupal (con cupos)',
        'round_robin' => 'Rotativo entre anfitriones',
        'collective' => 'Colectivo (todos a la vez)',
    ];
    public const MODES = [
        'in_person' => 'Presencial',
        'video_auto' => 'Videollamada automática',
        'video_custom' => 'Videollamada con tu enlace',
        'phone' => 'Llamada telefónica',
        'home' => 'A domicilio',
    ];

    /** Página del panel dentro del layout de administración. */
    protected function page(string $tpl, array $data, string $active, string $title, array $scripts = [], int $status = 200): Response
    {
        $data['active'] = $active;
        $data['title'] = $title;
        $data['scripts'] = $scripts;
        return Response::html(View::render($tpl, $data, 'layouts/admin'), $status);
    }

    /** ¿La petición viene de fetch/AJAX? */
    protected function ajax(Request $req): bool
    {
        return $req->wantsJson();
    }

    /** Respuesta de acción: JSON si es AJAX; si no, mensaje y redirección. */
    protected function done(Request $req, string $type, string $msg, string $path, array $extra = []): Response
    {
        if ($this->ajax($req)) {
            return $this->json(array_merge(['ok' => $type !== 'error', $type === 'error' ? 'error' : 'message' => $msg], $extra), $type === 'error' ? 422 : 200);
        }
        $this->flash($type, $msg);
        return $this->redirect($path);
    }

    /** Anfitrión al que está limitado el usuario (null = sin límite). */
    protected function scope(): ?int
    {
        return Auth::scopedHostId();
    }

    protected function isAdmin(): bool
    {
        return Auth::role() === 'admin';
    }

    /** Entero positivo opcional: '' -> null. */
    protected static function intOrNull(Request $req, string $key, int $min = 0, int $max = 1000000): ?int
    {
        $v = trim((string) ($req->post[$key] ?? ''));
        if ($v === '' || !is_numeric($v)) {
            return null;
        }
        return max($min, min($max, (int) $v));
    }

    protected static function intIn(Request $req, string $key, int $default, int $min, int $max): int
    {
        $v = $req->post[$key] ?? null;
        if (!is_scalar($v) || !is_numeric($v)) {
            return $default;
        }
        return max($min, min($max, (int) $v));
    }

    /** Lista de ids enteros positivos desde un campo de arreglo. */
    protected static function idList($raw): array
    {
        $out = [];
        foreach ((array) $raw as $v) {
            if (is_scalar($v) && ctype_digit((string) $v) && (int) $v > 0) {
                $out[(int) $v] = (int) $v;
            }
        }
        return array_values($out);
    }
}
