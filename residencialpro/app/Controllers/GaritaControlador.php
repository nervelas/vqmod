<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controlador;
use App\Core\DB;
use App\Core\Notificar;
use App\Core\Peticion;
use App\Core\Subida;
use App\Models\Casa;
use App\Models\Comunicacion;
use App\Models\Visita;

final class GaritaControlador extends Controlador
{
    public function panel(): void
    {
        $this->exigirRol('garita', 'admin');
        $this->mostrar('garita/panel', [
            'tituloPagina' => 'Control de accesos',
            'adentro'      => Visita::adentro(),
            'vigentes'     => Visita::preregistrosVigentes(),
            'deHoy'        => Visita::deHoy(),
            'turno'        => $this->turnoActual(),
        ], 'garita');
    }

    public function ingreso(): void
    {
        $this->exigirRol('garita', 'admin');
        if ($this->post()) {
            $this->verificarCsrf();
            $visitante = Peticion::texto('visitante');
            if ($visitante === '') {
                $this->error('Escriba el nombre del visitante.', '/garita/ingreso');
            }
            $prereg = null;
            $codigo = Peticion::texto('codigo');
            if ($codigo !== '') {
                $r = Visita::validar($codigo);
                if ($r['ok']) {
                    $prereg = $r['prereg'];
                }
            }
            $foto = null;
            $dataUrl = Peticion::texto('foto_data');
            if ($dataUrl !== '') {
                $foto = Subida::guardarDataUrl($dataUrl, 'visitas');
            }

            $id = Visita::registrarEntrada([
                'casa_id'    => Peticion::entero('casa_id') ?: null,
                'tipo'       => Peticion::texto('tipo', 'visita'),
                'visitante'  => $visitante,
                'dpi'        => Peticion::texto('dpi') ?: null,
                'placa'      => Peticion::texto('placa') ?: null,
                'vehiculo'   => Peticion::texto('vehiculo') ?: null,
                'personas'   => Peticion::entero('personas', 1),
                'motivo'     => Peticion::texto('motivo') ?: null,
                'foto'       => $foto,
            ], $prereg);
            unset($id);
            $this->exito('Ingreso registrado. El residente fue notificado.', '/garita');
        }

        $codigo  = Peticion::texto('codigo');
        $prereg  = null;
        $casa    = null;
        $mensaje = '';
        if ($codigo !== '') {
            $r = Visita::validar($codigo);
            $mensaje = $r['mensaje'];
            if ($r['ok']) {
                $prereg = $r['prereg'];
                $casa   = $r['casa'];
            }
        }

        $this->mostrar('garita/ingreso', [
            'tituloPagina' => 'Registrar ingreso',
            'casas'        => Casa::opciones(),
            'prereg'       => $prereg,
            'casaPre'      => $casa,
            'mensaje'      => $mensaje,
            'codigo'       => $codigo,
        ], 'garita');
    }

    public function salida(int $id = 0): void
    {
        $this->exigirRol('garita', 'admin');
        $this->verificarCsrf();
        if (!Visita::registrarSalida($id)) {
            $this->error('La visita no existe o ya registró su salida.', '/garita/visitas');
        }
        $this->exito('Salida registrada.', '/garita/visitas');
    }

    public function visitas(): void
    {
        $this->exigirRol('garita', 'admin');
        $this->mostrar('garita/visitas', [
            'tituloPagina' => 'Dentro del residencial',
            'adentro'      => Visita::adentro(),
            'recientes'    => Visita::listar([], 40),
        ], 'garita');
    }

    public function bitacora(): void
    {
        $this->exigirRol('garita', 'admin');
        if ($this->post()) {
            $this->verificarCsrf();
            $texto = Peticion::texto('texto');
            if (mb_strlen($texto) < 4) {
                $this->error('Escriba la novedad con al menos 4 caracteres.', '/garita/bitacora');
            }
            $turno = $this->turnoActual();
            DB::insertar('bitacora_garita', [
                'turno_id'   => $turno !== null ? (int) $turno['id'] : null,
                'usuario_id' => Auth::id(),
                'tipo'       => Peticion::texto('tipo', 'novedad'),
                'texto'      => $texto,
            ]);
            Auditoria::registrar('bitacora_garita', null, null, recortar($texto, 80));
            if (Peticion::texto('tipo') === 'incidente') {
                Notificar::rol(['admin'], 'Novedad importante en garita', recortar($texto, 110), '/admin/bitacora', 'alerta');
            }
            $this->exito('Novedad registrada en la bitácora.', '/garita/bitacora');
        }

        $this->mostrar('garita/bitacora', [
            'tituloPagina' => 'Bitácora del turno',
            'registros'    => DB::todos(
                'SELECT b.*, u.nombre AS guardia FROM bitacora_garita b
                 LEFT JOIN usuarios u ON u.id = b.usuario_id
                 ORDER BY b.id DESC LIMIT 60'
            ),
            'turno'        => $this->turnoActual(),
        ], 'garita');
    }

    public function turno(): void
    {
        $this->exigirRol('garita', 'admin');
        $actual = $this->turnoActual();

        if ($this->post()) {
            $this->verificarCsrf();
            $accion = Peticion::texto('accion');
            if ($accion === 'iniciar' && $actual === null) {
                DB::insertar('turnos', ['usuario_id' => Auth::id(), 'inicio' => date('Y-m-d H:i:s')]);
                Auditoria::registrar('iniciar_turno', 'turnos', null, 'Inicio de turno de garita');
                $this->exito('Turno iniciado. Buen trabajo.', '/garita');
            }
            if ($accion === 'cerrar' && $actual !== null) {
                DB::actualizar('turnos', [
                    'fin'       => date('Y-m-d H:i:s'),
                    'novedades' => Peticion::texto('novedades') ?: null,
                ], 'id = :id', ['id' => (int) $actual['id']]);
                Auditoria::registrar('cerrar_turno', 'turnos', (int) $actual['id'], 'Cierre de turno');
                Notificar::rol(['admin'], 'Cambio de turno en garita',
                    (string) (Auth::usuario()['nombre'] ?? '') . ' cerró su turno.', '/admin/bitacora', 'reloj');
                $this->exito('Turno cerrado. Gracias.', '/garita');
            }
        }

        $this->mostrar('garita/turno', [
            'tituloPagina' => 'Cambio de turno',
            'turno'        => $actual,
            'anteriores'   => DB::todos(
                'SELECT t.*, u.nombre AS guardia FROM turnos t
                 LEFT JOIN usuarios u ON u.id = t.usuario_id
                 ORDER BY t.id DESC LIMIT 10'
            ),
            'novedades'    => $actual !== null ? DB::todos(
                'SELECT * FROM bitacora_garita WHERE turno_id = :t ORDER BY id DESC',
                ['t' => (int) $actual['id']]
            ) : [],
        ], 'garita');
    }

    public function panico(): void
    {
        $this->exigirRol('garita', 'admin', 'residente');
        $this->verificarCsrf();
        $detalle = Peticion::texto('detalle', 'Botón de emergencia activado desde la garita.');
        $id = DB::insertar('emergencias', [
            'usuario_id' => Auth::id(),
            'casa_id'    => Peticion::entero('casa_id') ?: null,
            'tipo'       => Peticion::texto('tipo', 'panico'),
            'detalle'    => $detalle,
        ]);
        Auditoria::registrar('emergencia', 'emergencias', $id, $detalle);
        Notificar::rol(
            ['admin', 'junta'],
            '🚨 EMERGENCIA en el residencial',
            (string) (Auth::usuario()['nombre'] ?? '') . ': ' . recortar($detalle, 90),
            '/admin/bitacora',
            'sirena'
        );
        if (Peticion::esAjax()) {
            $this->json(['ok' => true, 'mensaje' => 'Alerta enviada a la administración y a la junta directiva.']);
        }
        $this->exito('Alerta enviada a la administración y a la junta directiva.', '/garita');
    }

    public function directorio(): void
    {
        $this->exigirRol('garita', 'admin');
        $this->mostrar('garita/directorio', [
            'tituloPagina' => 'Directorio',
            'emergencia'   => Comunicacion::contactosEmergencia(),
            'casas'        => DB::todos(
                'SELECT c.id, c.codigo, f.nombre AS fase, c.restringida,
                        (SELECT r.nombre FROM residentes r WHERE r.casa_id = c.id AND r.activo = 1
                         ORDER BY (r.tipo="propietario") DESC, r.id LIMIT 1) AS residente,
                        (SELECT r.telefono FROM residentes r WHERE r.casa_id = c.id AND r.activo = 1
                         ORDER BY (r.tipo="propietario") DESC, r.id LIMIT 1) AS telefono
                 FROM casas c LEFT JOIN fases f ON f.id = c.fase_id
                 ORDER BY f.orden, LENGTH(c.codigo), c.codigo'
            ),
        ], 'garita');
    }

    // --------------------------------------------------------- Vista admin

    public function reporteVisitas(): void
    {
        $this->exigirRol('admin', 'junta');
        [$porPagina, $desde, $pagina] = $this->paginacion(40);
        $filtros = [
            'casa'   => Peticion::entero('casa'),
            'desde'  => Peticion::texto('desde'),
            'hasta'  => Peticion::texto('hasta'),
            'placa'  => Peticion::texto('placa'),
            'tipo'   => Peticion::texto('tipo'),
            'buscar' => Peticion::texto('buscar'),
        ];
        $this->mostrar('admin/visitas', [
            'tituloPagina' => 'Visitas y accesos',
            'subtitulo'    => 'Registro histórico de la garita',
            'visitas'      => Visita::listar($filtros, $porPagina, $desde),
            'total'        => Visita::contar($filtros),
            'pagina'       => $pagina,
            'porPagina'    => $porPagina,
            'filtros'      => $filtros,
            'casas'        => Casa::opciones(),
            'adentro'      => count(Visita::adentro()),
            'hoy'          => Visita::deHoy(),
            'serie'        => \App\Models\Reporte::visitasPorDia(14),
        ]);
    }

    public function bitacoraAdmin(): void
    {
        $this->exigirRol('admin', 'junta');
        $this->mostrar('admin/bitacora', [
            'tituloPagina' => 'Bitácora de garita',
            'subtitulo'    => 'Novedades, turnos y emergencias',
            'registros'    => DB::todos(
                'SELECT b.*, u.nombre AS guardia FROM bitacora_garita b
                 LEFT JOIN usuarios u ON u.id = b.usuario_id ORDER BY b.id DESC LIMIT 120'
            ),
            'turnos'       => DB::todos(
                'SELECT t.*, u.nombre AS guardia FROM turnos t
                 LEFT JOIN usuarios u ON u.id = t.usuario_id ORDER BY t.id DESC LIMIT 20'
            ),
            'emergencias'  => DB::todos(
                'SELECT e.*, u.nombre AS usuario, c.codigo AS casa FROM emergencias e
                 LEFT JOIN usuarios u ON u.id = e.usuario_id
                 LEFT JOIN casas c ON c.id = e.casa_id
                 ORDER BY e.id DESC LIMIT 20'
            ),
        ]);
    }

    private function turnoActual(): ?array
    {
        return DB::uno(
            'SELECT * FROM turnos WHERE fin IS NULL ORDER BY id DESC LIMIT 1'
        );
    }
}
