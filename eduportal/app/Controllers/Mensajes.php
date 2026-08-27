<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Notificador;
use App\Core\Validator;
use App\Models\Comunicacion;

final class Mensajes extends Controller
{
    public function index(): string
    {
        $this->requirePermiso('mensajes.ver');
        $uid = (int)Auth::id();
        return $this->view('admin/mensajes', [
            'titulo'    => 'Mensajes',
            'hilos'     => Comunicacion::hilos($uid),
            'contactos' => Comunicacion::contactos($uid, (string)Auth::rol()),
            'actual'    => null,
            'mensajes'  => [],
        ]);
    }

    public function conversacion(string $usuario): string
    {
        $this->requirePermiso('mensajes.ver');
        $uid = (int)Auth::id();
        $otroId = (int)$usuario;
        if (!Comunicacion::puedeEscribirA($uid, (string)Auth::rol(), $otroId)) {
            throw new HttpException(403, 'No puede conversar con este usuario.');
        }
        Comunicacion::marcarConversacionLeida($uid, $otroId);
        return $this->view('admin/mensajes', [
            'titulo'    => 'Mensajes',
            'hilos'     => Comunicacion::hilos($uid),
            'contactos' => Comunicacion::contactos($uid, (string)Auth::rol()),
            'actual'    => Database::one('SELECT id, nombre, rol FROM users WHERE id = :id', ['id' => $otroId]),
            'mensajes'  => Comunicacion::conversacion($uid, $otroId),
        ]);
    }

    public function enviar(string $usuario): string
    {
        $this->requirePermiso('mensajes.ver');
        $this->requireCsrf();
        $uid = (int)Auth::id();
        $otroId = (int)$usuario;
        if (!Comunicacion::puedeEscribirA($uid, (string)Auth::rol(), $otroId)) {
            throw new HttpException(403, 'No puede escribir a este usuario.');
        }
        $v = Validator::make($this->req->all(), ['cuerpo' => 'required|len:1,4000'], ['cuerpo' => 'mensaje']);
        if ($v->fails()) {
            $this->error($v->firstError());
            return $this->redirect('mensajes/' . $otroId);
        }
        Database::run(
            'INSERT INTO mensajes (de_id, para_id, cuerpo) VALUES (:d, :p, :c)',
            ['d' => $uid, 'p' => $otroId, 'c' => (string)$v->get('cuerpo')]
        );
        Notificador::crear($otroId, 'Nuevo mensaje', 'De ' . Auth::nombre(), 'mensajes/' . $uid);
        if ($this->req->wantsJson()) {
            return $this->json(['ok' => true]);
        }
        return $this->redirect('mensajes/' . $otroId);
    }
}
