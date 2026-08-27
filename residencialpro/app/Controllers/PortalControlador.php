<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controlador;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Sesion;
use App\Core\Subida;
use App\Models\Casa;
use App\Models\Comunicacion;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\Visita;

final class PortalControlador extends Controlador
{
    private array $casasUsuario = [];
    private int $casaId = 0;
    private ?array $casa = null;

    private function preparar(): void
    {
        $this->exigirRol('residente', 'admin', 'junta', 'contabilidad', 'garita');
        $ids = Auth::casas();
        if ($ids === []) {
            if (Auth::esStaff()) {
                $this->redirigir('/admin');
            }
            Sesion::flash('alerta', 'Su usuario todavía no está asociado a ninguna vivienda. Comuníquese con la administración.');
            $this->redirigir('/perfil');
        }
        $this->casaId = Auth::casaActual();
        $this->casa   = Casa::porId($this->casaId);
        foreach ($ids as $id) {
            $c = Casa::porId((int) $id);
            if ($c !== null) {
                $this->casasUsuario[] = $c;
            }
        }
        Cuota::recalcularMora($this->casaId);
    }

    /** Datos comunes de todas las vistas del portal. */
    private function base(array $datos): array
    {
        return array_merge([
            'casaActual' => $this->casa,
            'casas'      => $this->casasUsuario,
        ], $datos);
    }

    public function inicio(): void
    {
        $this->preparar();
        $saldo = Casa::saldo($this->casaId);
        $this->mostrar('portal/inicio', $this->base([
            'tituloPagina' => 'Mi residencial',
            'saldo'        => $saldo,
            'dias'         => Casa::diasMora($this->casaId),
            'aFavor'       => Pago::saldoAFavor($this->casaId),
            'cargos'       => Cuota::cargos($this->casaId, 'pendientes', 6),
            'avisos'       => Comunicacion::avisos(['vigentes' => true, 'casa' => $this->casaId], 4),
            'visitas'      => Visita::preregistrosDe($this->casaId, 4),
            'reservas'     => Reserva::listar(['casa' => $this->casaId, 'desde' => date('Y-m-d')], 4),
            'votaciones'   => Comunicacion::votaciones(['abiertas' => true]),
            'eventos'      => Comunicacion::eventos(true, 3),
            'sinLeer'      => Comunicacion::avisosNoLeidos(Auth::id(), $this->casaId),
        ]), 'portal');
    }

    public function estadoCuenta(): void
    {
        $this->preparar();
        $this->mostrar('portal/estado-cuenta', $this->base([
            'tituloPagina' => 'Mi estado de cuenta',
            'cargos'       => Cuota::cargos($this->casaId, 'vigentes', 120),
            'pagos'        => Pago::listar(['casa' => $this->casaId], 20),
            'saldo'        => Casa::saldo($this->casaId),
            'antiguedad'   => Cuota::antiguedad($this->casaId),
            'aFavor'       => Pago::saldoAFavor($this->casaId),
        ]), 'portal');
    }

    public function pagar(): void
    {
        $this->preparar();
        if ($this->post()) {
            $this->verificarCsrf();
            $monto = Peticion::decimal('monto');
            if ($monto <= 0) {
                $this->error('Indique el monto que depositó o transfirió.', '/portal/pagar');
            }
            $comprobante = Subida::guardar('comprobante', 'comprobantes', array_merge(Subida::IMAGENES, Subida::DOCS), 8);
            if ($comprobante === null) {
                $this->error(Subida::$ultimoError !== '' ? Subida::$ultimoError
                    : 'Adjunte la fotografía o el PDF de su comprobante.', '/portal/pagar');
            }
            $pagoId = Pago::registrar([
                'casa_id'     => $this->casaId,
                'fecha'       => Peticion::texto('fecha', date('Y-m-d')),
                'monto'       => $monto,
                'metodo'      => Peticion::texto('metodo', 'deposito'),
                'referencia'  => Peticion::texto('referencia') ?: null,
                'banco'       => Peticion::texto('banco') ?: null,
                'comprobante' => $comprobante,
                'notas'       => Peticion::texto('notas') ?: null,
            ], [], false);
            \App\Core\Notificar::rol(
                ['admin', 'contabilidad'],
                'Nuevo comprobante por revisar',
                'Casa ' . ($this->casa['codigo'] ?? '') . ' — ' . q($monto),
                '/admin/comprobantes',
                'archivo'
            );
            Auditoria::registrar('subir_comprobante', 'pagos', $pagoId, 'Casa ' . ($this->casa['codigo'] ?? ''));
            $this->exito('¡Gracias! Su comprobante quedó en revisión. Le avisaremos en cuanto sea aprobado.', '/portal/estado-cuenta');
        }

        $this->mostrar('portal/pagar', $this->base([
            'tituloPagina' => 'Reportar un pago',
            'saldo'        => Casa::saldo($this->casaId),
            'cargos'       => Cuota::cargos($this->casaId, 'pendientes'),
            'enRevision'   => Pago::listar(['casa' => $this->casaId, 'estado' => 'revision'], 5),
            'enlacePago'   => Ajustes::get('enlace_pago', ''),
            'cuenta'       => Ajustes::get('cuenta_deposito', ''),
        ]), 'portal');
    }

    // -------------------------------------------------------------- VISITAS

    public function visitas(): void
    {
        $this->preparar();
        $this->mostrar('portal/visitas', $this->base([
            'tituloPagina' => 'Mis visitas',
            'preregistros' => Visita::preregistrosDe($this->casaId, 30),
            'historial'    => Visita::listar(['casa' => $this->casaId], 20),
        ]), 'portal');
    }

    public function nuevaVisita(): void
    {
        $this->preparar();
        if ($this->post()) {
            $this->verificarCsrf();
            $visitante = Peticion::texto('visitante');
            if ($visitante === '') {
                $this->error('Escriba el nombre de su visita.', '/portal/visitas/nueva');
            }
            $recurrente = Peticion::bool('recurrente');
            $desde = Peticion::texto('valido_desde', date('Y-m-d\TH:i'));
            $hasta = Peticion::texto('valido_hasta', date('Y-m-d\TH:i', time() + 86400));
            $desde = date('Y-m-d H:i:s', (int) (strtotime($desde) ?: time()));
            $hasta = date('Y-m-d H:i:s', (int) (strtotime($hasta) ?: time() + 86400));
            if (strtotime($hasta) <= strtotime($desde)) {
                $this->error('La fecha final debe ser posterior a la inicial.', '/portal/visitas/nueva');
            }
            if (strtotime($hasta) > time() + 86400 * 400) {
                $this->error('La vigencia máxima es de un año.', '/portal/visitas/nueva');
            }
            $dias = Peticion::arreglo('dias');

            $prereg = Visita::preRegistrar([
                'casa_id'      => $this->casaId,
                'visitante'    => $visitante,
                'dpi'          => Peticion::texto('dpi') ?: null,
                'placa'        => Peticion::texto('placa') ?: null,
                'motivo'       => Peticion::texto('motivo') ?: null,
                'recurrente'   => $recurrente,
                'dias'         => $recurrente && $dias !== [] ? implode(',', array_map('intval', $dias)) : null,
                'hora_desde'   => $recurrente ? Peticion::texto('hora_desde', '07:00') : null,
                'hora_hasta'   => $recurrente ? Peticion::texto('hora_hasta', '18:00') : null,
                'valido_desde' => $desde,
                'valido_hasta' => $hasta,
                'max_usos'     => $recurrente ? 999 : max(1, Peticion::entero('max_usos', 1)),
            ]);
            $this->exito('Autorización creada. Comparta el código con su visita.', '/portal/visitas?nuevo=' . (int) $prereg['id']);
        }

        $this->mostrar('portal/nueva-visita', $this->base([
            'tituloPagina' => 'Autorizar una visita',
        ]), 'portal');
    }

    public function cancelarVisita(int $id = 0): void
    {
        $this->preparar();
        $this->verificarCsrf();
        $p = DB::uno('SELECT * FROM preregistros WHERE id = :id', ['id' => $id]);
        if ($p === null || (int) $p['casa_id'] !== $this->casaId) {
            $this->error('La autorización no existe.', '/portal/visitas');
        }
        DB::actualizar('preregistros', ['estado' => 'cancelado'], 'id = :id', ['id' => $id]);
        Auditoria::registrar('cancelar_preregistro', 'preregistros', $id, (string) $p['visitante']);
        $this->exito('Autorización cancelada. El código ya no permite el ingreso.', '/portal/visitas');
    }

    // ------------------------------------------------------------- RESERVAS

    public function reservas(): void
    {
        $this->preparar();
        if ($this->post()) {
            $this->verificarCsrf();
            $r = Reserva::solicitar(
                Peticion::entero('area_id'),
                $this->casaId,
                Peticion::texto('fecha'),
                Peticion::texto('hora_desde'),
                Peticion::texto('hora_hasta'),
                Peticion::entero('personas', 1),
                Peticion::texto('motivo')
            );
            if ($r['ok']) {
                $this->exito($r['mensaje'], '/portal/reservas');
            }
            $this->error($r['mensaje'], '/portal/reservas');
        }

        $areaId = Peticion::entero('area');
        $areas  = Reserva::areas();
        if ($areaId <= 0 && $areas !== []) {
            $areaId = (int) $areas[0]['id'];
        }
        $mes = Peticion::texto('mes', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes = date('Y-m');
        }

        $this->mostrar('portal/reservas', $this->base([
            'tituloPagina' => 'Áreas comunes',
            'areas'        => $areas,
            'areaId'       => $areaId,
            'area'         => $areaId > 0 ? Reserva::area($areaId) : null,
            'mes'          => $mes,
            'ocupadas'     => $areaId > 0 ? Reserva::delMes($areaId, $mes) : [],
            'misReservas'  => Reserva::listar(['casa' => $this->casaId], 20),
            'solvente'     => Casa::solvente($this->casaId),
        ]), 'portal');
    }

    public function cancelarReserva(int $id = 0): void
    {
        $this->preparar();
        $this->verificarCsrf();
        $r = Reserva::porId($id);
        if ($r === null || (int) $r['casa_id'] !== $this->casaId) {
            $this->error('La reserva no existe.', '/portal/reservas');
        }
        Reserva::cancelar($id);
        $this->exito('Reserva cancelada.', '/portal/reservas');
    }

    // -------------------------------------------------------------- AVISOS

    public function avisos(): void
    {
        $this->preparar();
        $avisos = Comunicacion::avisos(['vigentes' => true, 'casa' => $this->casaId], 40);
        $leidos = array_map(
            static fn($f) => (int) $f['aviso_id'],
            DB::todos('SELECT aviso_id FROM avisos_lecturas WHERE usuario_id = :u', ['u' => Auth::id()])
        );
        $this->mostrar('portal/avisos', $this->base([
            'tituloPagina' => 'Avisos del residencial',
            'avisos'       => $avisos,
            'leidos'       => $leidos,
            'eventos'      => Comunicacion::eventos(true, 8),
        ]), 'portal');
    }

    public function verAviso(int $id = 0): void
    {
        $this->preparar();
        $aviso = Comunicacion::aviso($id);
        if ($aviso === null) {
            $this->error('El aviso no existe.', '/portal/avisos');
        }
        Comunicacion::marcarLeido($id, Auth::id(), $this->casaId);
        $this->mostrar('portal/aviso', $this->base([
            'tituloPagina' => recortar((string) $aviso['titulo'], 40),
            'aviso'        => $aviso,
        ]), 'portal');
    }

    // --------------------------------------------------------- INCIDENCIAS

    public function incidencias(): void
    {
        $this->preparar();
        if ($this->post()) {
            $this->verificarCsrf();
            $titulo = Peticion::texto('titulo');
            $descripcion = Peticion::texto('descripcion');
            if ($titulo === '' || mb_strlen($descripcion) < 10) {
                $this->error('Escriba un título y describa el problema con al menos 10 caracteres.', '/portal/incidencias');
            }
            $foto = Subida::guardar('foto', 'incidencias', Subida::IMAGENES, 6);
            $id = Comunicacion::crearIncidencia([
                'casa_id'     => $this->casaId,
                'categoria'   => Peticion::texto('categoria', 'general'),
                'titulo'      => $titulo,
                'descripcion' => $descripcion,
                'ubicacion'   => Peticion::texto('ubicacion') ?: null,
                'foto'        => $foto,
                'prioridad'   => Peticion::texto('prioridad', 'media'),
            ]);
            unset($id);
            $this->exito('Reporte enviado. La administración le dará seguimiento.', '/portal/incidencias');
        }

        $this->mostrar('portal/incidencias', $this->base([
            'tituloPagina' => 'Mis reportes',
            'incidencias'  => Comunicacion::incidencias(['casa' => $this->casaId], 30),
        ]), 'portal');
    }

    // ---------------------------------------------------------- VOTACIONES

    public function votaciones(): void
    {
        $this->preparar();
        if ($this->post()) {
            $this->verificarCsrf();
            $r = Comunicacion::votar(Peticion::entero('votacion_id'), Peticion::entero('opcion_id'), $this->casaId);
            if ($r['ok']) {
                $this->exito($r['mensaje'], '/portal/votaciones');
            }
            $this->error($r['mensaje'], '/portal/votaciones');
        }

        $votaciones = Comunicacion::votaciones([]);
        $datos = [];
        foreach ($votaciones as $v) {
            $datos[] = [
                'votacion'   => $v,
                'opciones'   => Comunicacion::opciones((int) $v['id']),
                'yaVoto'     => Comunicacion::yaVoto((int) $v['id'], $this->casaId),
                'resultados' => Comunicacion::resultados((int) $v['id']),
            ];
        }
        $this->mostrar('portal/votaciones', $this->base([
            'tituloPagina' => 'Votaciones',
            'items'        => $datos,
        ]), 'portal');
    }

    // ------------------------------------------------------------ MENSAJES

    public function mensajes(): void
    {
        $this->preparar();
        if ($this->post()) {
            $this->verificarCsrf();
            $cuerpo = Peticion::texto('cuerpo');
            if (mb_strlen($cuerpo) < 5) {
                $this->error('Escriba su mensaje.', '/portal/mensajes');
            }
            Comunicacion::enviarMensaje([
                'casa_id'  => $this->casaId,
                'para_rol' => 'admin',
                'asunto'   => Peticion::texto('asunto', 'Consulta del residente'),
                'cuerpo'   => $cuerpo,
            ]);
            $this->exito('Mensaje enviado a la administración.', '/portal/mensajes');
        }

        $this->mostrar('portal/mensajes', $this->base([
            'tituloPagina' => 'Mensajes',
            'mensajes'     => Comunicacion::mensajes(Auth::id(), 'residente', 40),
            'contactos'    => Comunicacion::contactosEmergencia(),
        ]), 'portal');
    }

    public function documentos(): void
    {
        $this->preparar();
        $this->mostrar('portal/documentos', $this->base([
            'tituloPagina' => 'Documentos',
            'documentos'   => DB::todos(
                'SELECT * FROM documentos WHERE publico = 1 OR casa_id = :c ORDER BY id DESC LIMIT 60',
                ['c' => $this->casaId]
            ),
            'reglamento'   => Ajustes::get('reglamento', ''),
        ]), 'portal');
    }

    public function cambiarCasa(int $id = 0): void
    {
        $this->exigirRol('residente', 'admin', 'junta', 'contabilidad');
        $this->verificarCsrf();
        if (in_array($id, Auth::casas(), true)) {
            Sesion::set('casa_actual', $id);
        }
        $this->redirigir('/portal');
    }
}
