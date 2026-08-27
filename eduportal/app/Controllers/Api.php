<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Notificador;
use App\Core\Settings;
use App\Models\Alumno;

final class Api extends Controller
{
    public function notificaciones(): string
    {
        $this->requireAuth();
        $uid = (int)Auth::id();
        return $this->json([
            'ok'         => true,
            'pendientes' => Notificador::pendientes($uid),
            'mensajes'   => \App\Models\Comunicacion::mensajesNoLeidos($uid),
            'items'      => array_map(static fn($n) => [
                'id'     => (int)$n['id'],
                'titulo' => (string)$n['titulo'],
                'cuerpo' => (string)($n['cuerpo'] ?? ''),
                'url'    => $n['url'] ? url((string)$n['url']) : null,
                'fecha'  => fecha_hora((string)$n['creado_en']),
                'leido'  => $n['leido_en'] !== null,
            ], Notificador::ultimas($uid, 12)),
        ]);
    }

    public function marcarLeidas(): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        Notificador::marcarLeidas((int)Auth::id());
        return $this->json(['ok' => true]);
    }

    public function suscribirPush(): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $endpoint = (string)$this->req->input('endpoint', '');
        if (!filter_var($endpoint, FILTER_VALIDATE_URL) || !str_starts_with($endpoint, 'https://')) {
            return $this->json(['ok' => false, 'error' => 'Endpoint no valido.'], 422);
        }
        Notificador::guardarSuscripcion(
            (int)Auth::id(),
            $endpoint,
            mb_substr((string)$this->req->input('p256dh', ''), 0, 255),
            mb_substr((string)$this->req->input('auth', ''), 0, 255)
        );
        return $this->json(['ok' => true]);
    }

    public function clavePush(): string
    {
        $this->requireAuth();
        return $this->json(['ok' => true, 'clave' => (string)Settings::get('vapid_public', '')]);
    }

    /** Buscador de alumnos para autocompletado (respeta permisos). */
    public function buscarAlumnos(): string
    {
        $this->requirePermiso('alumnos.ver');
        $q = (string)$this->req->input('q', '');
        if (mb_strlen($q) < 2) {
            return $this->json(['ok' => true, 'items' => []]);
        }
        $items = array_map(static fn($a) => [
            'id'     => (int)$a['id'],
            'texto'  => trim($a['apellidos'] . ', ' . $a['nombres']),
            'codigo' => (string)$a['codigo'],
            'grupo'  => (string)($a['grupo'] ?? ''),
        ], Alumno::buscar(['q' => $q], 12, 0));
        return $this->json(['ok' => true, 'items' => $items]);
    }
}
