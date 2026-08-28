<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controlador;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Subida;
use App\Models\Casa;
use App\Models\Comunicacion;

final class ComunicacionControlador extends Controlador
{
    // ---------------------------------------------------------------- AVISOS

    public function avisos(): void
    {
        $this->exigirRol('admin', 'junta');
        $this->mostrar('admin/comunicacion/avisos', [
            'tituloPagina' => 'Avisos',
            'subtitulo'    => 'Comunicados a los residentes',
            'avisos'       => Comunicacion::avisos([], 60),
            'totalCasas'   => Casa::contar(),
        ]);
    }

    public function nuevoAviso(int $id = 0): void
    {
        $this->exigirRol('admin', 'junta');
        $aviso = $id > 0 ? Comunicacion::aviso($id) : null;
        if ($id > 0 && $aviso === null) {
            $this->error('El aviso no existe.', '/admin/avisos');
        }

        if ($this->post()) {
            $this->verificarCsrf();
            $titulo = Peticion::texto('titulo');
            $cuerpo = Peticion::texto('cuerpo');
            if ($titulo === '' || mb_strlen($cuerpo) < 10) {
                $this->error('Escriba el título y el contenido del aviso.', $id > 0 ? '/admin/avisos/' . $id . '/editar' : '/admin/avisos/nuevo');
            }
            $datos = [
                'titulo'      => $titulo,
                'cuerpo'      => $cuerpo,
                'alcance'     => Peticion::texto('alcance', 'todos'),
                'destino_id'  => Peticion::entero('destino_id') ?: null,
                'prioridad'   => Peticion::texto('prioridad', 'normal'),
                'publicar_en' => Peticion::texto('publicar_en') !== ''
                                 ? date('Y-m-d H:i:s', (int) strtotime(Peticion::texto('publicar_en')))
                                 : date('Y-m-d H:i:s'),
                'vence_en'    => Peticion::texto('vence_en') !== ''
                                 ? date('Y-m-d H:i:s', (int) strtotime(Peticion::texto('vence_en')))
                                 : null,
                'confirmar'   => Peticion::bool('confirmar'),
            ];
            $imagen  = Subida::guardar('imagen', 'avisos', Subida::IMAGENES, 6);
            $archivo = Subida::guardar('archivo', 'avisos', array_merge(Subida::IMAGENES, Subida::DOCS), 8);
            if ($imagen !== null)  { $datos['imagen'] = $imagen; }
            if ($archivo !== null) { $datos['archivo'] = $archivo; }

            $nuevo = Comunicacion::guardarAviso($datos, $id);
            unset($nuevo);
            $this->exito($id > 0 ? 'Aviso actualizado.' : 'Aviso publicado y notificado a los residentes.', '/admin/avisos');
        }

        $this->mostrar('admin/comunicacion/aviso-editar', [
            'tituloPagina' => $id > 0 ? 'Editar aviso' : 'Nuevo aviso',
            'aviso'        => $aviso,
            'fases'        => Casa::fases(),
            'calles'       => Casa::calles(),
            'casas'        => Casa::opciones(),
        ]);
    }

    public function eliminarAviso(int $id = 0): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        DB::eliminar('avisos_lecturas', 'aviso_id = :a', ['a' => $id]);
        DB::eliminar('avisos', 'id = :id', ['id' => $id]);
        Auditoria::registrar('eliminar_aviso', 'avisos', $id);
        $this->exito('Aviso eliminado.', '/admin/avisos');
    }

    // --------------------------------------------------------------- EVENTOS

    public function eventos(): void
    {
        $this->exigirRol('admin', 'junta');
        if ($this->post()) {
            $this->verificarCsrf();
            $titulo = Peticion::texto('titulo');
            $inicio = Peticion::texto('inicio');
            if ($titulo === '' || $inicio === '') {
                $this->error('Indique el título y la fecha del evento.', '/admin/eventos');
            }
            $datos = [
                'titulo'  => $titulo,
                'detalle' => Peticion::texto('detalle') ?: null,
                'tipo'    => Peticion::texto('tipo', 'otro'),
                'inicio'  => date('Y-m-d H:i:s', (int) strtotime($inicio)),
                'fin'     => Peticion::texto('fin') !== '' ? date('Y-m-d H:i:s', (int) strtotime(Peticion::texto('fin'))) : null,
                'lugar'   => Peticion::texto('lugar') ?: null,
                'publico' => Peticion::bool('publico') ? 1 : 0,
            ];
            $id = Peticion::entero('id');
            if ($id > 0) {
                DB::actualizar('eventos', $datos, 'id = :id', ['id' => $id]);
            } else {
                DB::insertar('eventos', $datos);
            }
            Auditoria::registrar('guardar_evento', 'eventos', $id ?: null, $titulo);
            $this->exito('Evento guardado en el calendario.', '/admin/eventos');
        }

        $this->mostrar('admin/comunicacion/eventos', [
            'tituloPagina' => 'Calendario',
            'subtitulo'    => 'Asambleas, mantenimientos y actividades',
            'eventos'      => Comunicacion::eventos(false, 60),
        ]);
    }

    // ------------------------------------------------------------ VOTACIONES

    public function votaciones(): void
    {
        $this->exigirRol('admin', 'junta');
        $items = [];
        foreach (Comunicacion::votaciones([]) as $v) {
            $items[] = ['votacion' => $v, 'resultados' => Comunicacion::resultados((int) $v['id'])];
        }
        $this->mostrar('admin/comunicacion/votaciones', [
            'tituloPagina' => 'Votaciones',
            'subtitulo'    => 'Consultas y asambleas en línea',
            'items'        => $items,
        ]);
    }

    public function nuevaVotacion(): void
    {
        $this->exigirRol('admin', 'junta');
        if ($this->post()) {
            $this->verificarCsrf();
            $titulo = Peticion::texto('titulo');
            $opciones = array_values(array_filter(array_map('trim', Peticion::arreglo('opciones')), static fn($o) => $o !== ''));
            if ($titulo === '' || count($opciones) < 2) {
                $this->error('Escriba el título y al menos dos opciones de respuesta.', '/admin/votaciones/nueva');
            }
            $id = DB::insertar('votaciones', [
                'titulo'  => $titulo,
                'detalle' => Peticion::texto('detalle') ?: null,
                'modo'    => Peticion::texto('modo') === 'coeficiente' ? 'coeficiente' : 'casa',
                'inicio'  => date('Y-m-d H:i:s', (int) strtotime(Peticion::texto('inicio', 'now'))),
                'fin'     => date('Y-m-d H:i:s', (int) strtotime(Peticion::texto('fin', '+7 days'))),
                'quorum'  => Peticion::decimal('quorum', 50),
                'estado'  => Peticion::bool('abrir') ? 'abierta' : 'borrador',
            ]);
            foreach ($opciones as $i => $texto) {
                DB::insertar('votacion_opciones', [
                    'votacion_id' => $id,
                    'texto'       => mb_substr($texto, 0, 190),
                    'orden'       => $i + 1,
                ]);
            }
            Auditoria::registrar('crear_votacion', 'votaciones', $id, $titulo);
            if (Peticion::bool('abrir')) {
                \App\Core\Notificar::rol(['residente'], 'Nueva votación abierta', recortar($titulo, 100), '/portal/votaciones', 'voto');
            }
            $this->exito('Votación creada.', '/admin/votaciones/' . $id);
        }

        $this->mostrar('admin/comunicacion/votacion-nueva', ['tituloPagina' => 'Nueva votación']);
    }

    public function verVotacion(int $id = 0): void
    {
        $this->exigirRol('admin', 'junta');
        $v = Comunicacion::votacion($id);
        if ($v === null) {
            $this->error('La votación no existe.', '/admin/votaciones');
        }
        $this->mostrar('admin/comunicacion/votacion', [
            'tituloPagina' => recortar((string) $v['titulo'], 42),
            'votacion'     => $v,
            'resultados'   => Comunicacion::resultados($id),
            'votos'        => DB::todos(
                'SELECT v.*, c.codigo AS casa, o.texto AS opcion FROM votos v
                 LEFT JOIN casas c ON c.id = v.casa_id
                 LEFT JOIN votacion_opciones o ON o.id = v.opcion_id
                 WHERE v.votacion_id = :v ORDER BY v.id DESC',
                ['v' => $id]
            ),
        ]);
    }

    public function estadoVotacion(int $id = 0): void
    {
        $this->exigirRol('admin', 'junta');
        $this->verificarCsrf();
        $estado = Peticion::texto('estado');
        if (!in_array($estado, ['borrador', 'abierta', 'cerrada'], true)) {
            $this->error('Estado no válido.', '/admin/votaciones');
        }
        DB::actualizar('votaciones', ['estado' => $estado], 'id = :id', ['id' => $id]);
        Auditoria::registrar('estado_votacion', 'votaciones', $id, $estado);
        if ($estado === 'abierta') {
            $v = Comunicacion::votacion($id);
            \App\Core\Notificar::rol(['residente'], 'Nueva votación abierta',
                recortar((string) ($v['titulo'] ?? ''), 100), '/portal/votaciones', 'voto');
        }
        $this->exito('Votación ' . ($estado === 'abierta' ? 'abierta' : ($estado === 'cerrada' ? 'cerrada' : 'guardada como borrador')) . '.', '/admin/votaciones/' . $id);
    }

    // ----------------------------------------------------------- INCIDENCIAS

    public function incidencias(): void
    {
        $this->exigirRol('admin', 'junta');
        $estado = Peticion::texto('estado');
        $this->mostrar('admin/comunicacion/incidencias', [
            'tituloPagina' => 'Incidencias',
            'subtitulo'    => 'Reportes de los residentes',
            'incidencias'  => Comunicacion::incidencias($estado !== '' ? ['estado' => $estado] : [], 120),
            'estado'       => $estado,
            'abiertas'     => Comunicacion::abiertas(),
        ]);
    }

    public function verIncidencia(int $id = 0): void
    {
        $this->exigirRol('admin', 'junta');
        $i = Comunicacion::incidencia($id);
        if ($i === null) {
            $this->error('La incidencia no existe.', '/admin/incidencias');
        }
        if ($this->post()) {
            $this->verificarCsrf();
            $estado = Peticion::texto('estado', (string) $i['estado']);
            if (!in_array($estado, ['recibida', 'proceso', 'resuelta', 'cerrada'], true)) {
                $estado = (string) $i['estado'];
            }
            Comunicacion::actualizarIncidencia($id, $estado, Peticion::texto('comentario'));
            $this->exito('Incidencia actualizada. El residente fue notificado.', '/admin/incidencias/' . $id);
        }
        $this->mostrar('admin/comunicacion/incidencia', [
            'tituloPagina' => recortar((string) $i['titulo'], 42),
            'incidencia'   => $i,
            'seguimiento'  => Comunicacion::seguimiento($id),
        ]);
    }

    // -------------------------------------------------------------- MENSAJES

    public function mensajes(): void
    {
        $this->exigirRol('admin', 'junta');
        if ($this->post()) {
            $this->verificarCsrf();
            $cuerpo = Peticion::texto('cuerpo');
            $para   = Peticion::entero('para_usuario');
            if (mb_strlen($cuerpo) < 3) {
                $this->error('Escriba el mensaje.', '/admin/mensajes');
            }
            Comunicacion::enviarMensaje([
                'casa_id'      => Peticion::entero('casa_id') ?: null,
                'para_usuario' => $para ?: null,
                'asunto'       => Peticion::texto('asunto', 'Mensaje de la administración'),
                'cuerpo'       => $cuerpo,
            ]);
            $this->exito('Mensaje enviado.', '/admin/mensajes');
        }

        $this->mostrar('admin/comunicacion/mensajes', [
            'tituloPagina' => 'Mensajes',
            'subtitulo'    => 'Conversaciones con los residentes',
            'mensajes'     => Comunicacion::mensajes(Auth::id(), Auth::rol(), 80),
            'residentes'   => DB::todos(
                'SELECT u.id, u.nombre, c.codigo AS casa FROM usuarios u
                 INNER JOIN residentes r ON r.usuario_id = u.id AND r.activo = 1
                 INNER JOIN casas c ON c.id = r.casa_id
                 WHERE u.activo = 1 GROUP BY u.id, u.nombre, c.codigo ORDER BY c.codigo'
            ),
        ]);
    }

    public function emergencia(): void
    {
        $this->exigirRol('admin');
        if ($this->post()) {
            $this->verificarCsrf();
            $nombre = Peticion::texto('nombre');
            $telefono = Peticion::texto('telefono');
            if ($nombre === '' || $telefono === '') {
                $this->error('Indique el nombre y el teléfono.', '/admin/emergencia');
            }
            $id = Peticion::entero('id');
            $datos = [
                'nombre'   => $nombre,
                'telefono' => $telefono,
                'tipo'     => Peticion::texto('tipo') ?: null,
                'orden'    => Peticion::entero('orden'),
            ];
            if ($id > 0) {
                DB::actualizar('contactos_emergencia', $datos, 'id = :id', ['id' => $id]);
            } else {
                DB::insertar('contactos_emergencia', $datos);
            }
            $this->exito('Contacto guardado.', '/admin/emergencia');
        }
        $this->mostrar('admin/comunicacion/emergencia', [
            'tituloPagina' => 'Números de emergencia',
            'contactos'    => Comunicacion::contactosEmergencia(),
        ]);
    }
}
