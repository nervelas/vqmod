<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Controlador;
use App\Core\Correo;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Subida;
use App\Core\Url;
use App\Models\Casa;
use App\Models\Cuota;
use App\Models\Documento;
use App\Models\Egreso;
use App\Models\Pago;

final class PagosControlador extends Controlador
{
    public function index(): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        [$porPagina, $desde, $pagina] = $this->paginacion(30);
        $filtros = [
            'estado' => Peticion::texto('estado'),
            'casa'   => Peticion::entero('casa'),
            'desde'  => Peticion::texto('desde'),
            'hasta'  => Peticion::texto('hasta'),
            'metodo' => Peticion::texto('metodo'),
            'buscar' => Peticion::texto('buscar'),
        ];
        $rangoDesde = $filtros['desde'] !== '' ? $filtros['desde'] : date('Y-m-01');
        $rangoHasta = $filtros['hasta'] !== '' ? $filtros['hasta'] : date('Y-m-t');

        $this->mostrar('admin/pagos/index', [
            'tituloPagina' => 'Pagos',
            'subtitulo'    => 'Ingresos registrados',
            'pagos'        => Pago::listar($filtros, $porPagina, $desde),
            'total'        => Pago::contar($filtros),
            'pagina'       => $pagina,
            'porPagina'    => $porPagina,
            'filtros'      => $filtros,
            'casas'        => Casa::opciones(),
            'recaudado'    => Pago::recaudado($rangoDesde, $rangoHasta),
            'porMetodo'    => Pago::porMetodo($rangoDesde, $rangoHasta),
            'enRevision'   => Pago::pendientesRevision(),
        ]);
    }

    public function nuevo(): void
    {
        $this->exigirRol('admin', 'contabilidad');
        $casaId = Peticion::entero('casa');

        if ($this->post()) {
            $this->verificarCsrf();
            $casaId = Peticion::entero('casa_id');
            $casa   = Casa::porId($casaId);
            if ($casa === null) {
                $this->error('Seleccione una vivienda válida.', '/admin/pagos/nuevo');
            }
            $monto = Peticion::decimal('monto');
            if ($monto <= 0) {
                $this->error('El monto debe ser mayor que cero.', '/admin/pagos/nuevo?casa=' . $casaId);
            }

            $asignaciones = [];
            foreach (Peticion::arreglo('cargo') as $cargoId => $valor) {
                $v = (float) str_replace(',', '', (string) $valor);
                if ($v > 0) {
                    $asignaciones[(int) $cargoId] = $v;
                }
            }
            $comprobante = Subida::guardar('comprobante', 'comprobantes', array_merge(Subida::IMAGENES, Subida::DOCS), 8);

            try {
                $pagoId = Pago::registrar([
                    'casa_id'     => $casaId,
                    'fecha'       => Peticion::texto('fecha', date('Y-m-d')),
                    'monto'       => $monto,
                    'metodo'      => Peticion::texto('metodo', 'transferencia'),
                    'referencia'  => Peticion::texto('referencia') ?: null,
                    'banco'       => Peticion::texto('banco') ?: null,
                    'cuenta_id'   => Peticion::entero('cuenta_id') ?: null,
                    'comprobante' => $comprobante,
                    'notas'       => Peticion::texto('notas') ?: null,
                ], $asignaciones, true);

                if (Peticion::bool('enviar_recibo')) {
                    self::enviarRecibo($pagoId);
                }
                $this->exito('Pago registrado. Recibo ' . (Pago::porId($pagoId)['recibo'] ?? '') . '.', '/admin/pagos/' . $pagoId);
            } catch (\Throwable $e) {
                $this->error('No se pudo registrar el pago: ' . $e->getMessage(), '/admin/pagos/nuevo?casa=' . $casaId);
            }
        }

        if ($casaId > 0) {
            Cuota::recalcularMora($casaId);
        }
        $this->mostrar('admin/pagos/nuevo', [
            'tituloPagina' => 'Registrar pago',
            'subtitulo'    => 'Ingreso de un pago recibido',
            'casas'        => Casa::opciones(),
            'casaId'       => $casaId,
            'casa'         => $casaId > 0 ? Casa::porId($casaId) : null,
            'cargos'       => $casaId > 0 ? Cuota::cargos($casaId, 'pendientes') : [],
            'saldo'        => $casaId > 0 ? Casa::saldo($casaId) : 0.0,
            'cuentas'      => Egreso::cuentas(),
        ]);
    }

    public function detalle(int $id = 0): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        $pago = Pago::porId($id);
        if ($pago === null) {
            $this->error('El pago no existe.', '/admin/pagos');
        }
        $this->mostrar('admin/pagos/detalle', [
            'tituloPagina' => 'Recibo ' . ($pago['recibo'] ?? '—'),
            'subtitulo'    => 'Vivienda ' . $pago['casa'],
            'pago'         => $pago,
            'detalle'      => Pago::detalle($id),
            'residente'    => Casa::propietario((int) $pago['casa_id']),
            'saldo'        => Casa::saldo((int) $pago['casa_id']),
        ]);
    }

    public function anular(int $id = 0): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        $motivo = Peticion::texto('motivo');
        if (mb_strlen($motivo) < 5) {
            $this->error('Escriba el motivo de la anulación (mínimo 5 caracteres).', '/admin/pagos/' . $id);
        }
        if (!Pago::anular($id, $motivo)) {
            $this->error('Solo se pueden anular pagos aprobados.', '/admin/pagos/' . $id);
        }
        $this->exito('Pago anulado. Los cargos volvieron a quedar pendientes.', '/admin/pagos/' . $id);
    }

    // ------------------------------------------------------- COMPROBANTES

    public function comprobantes(): void
    {
        $this->exigirRol('admin', 'contabilidad');
        $this->mostrar('admin/pagos/comprobantes', [
            'tituloPagina' => 'Comprobantes por revisar',
            'subtitulo'    => 'Pagos reportados por los residentes',
            'pendientes'   => Pago::listar(['estado' => 'revision'], 60),
            'recientes'    => DB::todos(
                'SELECT p.*, c.codigo AS casa FROM pagos p LEFT JOIN casas c ON c.id = p.casa_id
                 WHERE p.estado IN ("aprobado","rechazado") AND p.aprobado_en IS NOT NULL
                 ORDER BY p.aprobado_en DESC LIMIT 12'
            ),
        ]);
    }

    public function aprobar(int $id = 0): void
    {
        $this->exigirRol('admin', 'contabilidad');
        $this->verificarCsrf();
        $asignaciones = [];
        foreach (Peticion::arreglo('cargo') as $cargoId => $valor) {
            $v = (float) str_replace(',', '', (string) $valor);
            if ($v > 0) {
                $asignaciones[(int) $cargoId] = $v;
            }
        }
        if (!Pago::aprobar($id, $asignaciones)) {
            $this->error('El comprobante ya fue procesado.', '/admin/comprobantes');
        }
        self::enviarRecibo($id);
        $this->exito('Comprobante aprobado. Se envió el recibo al residente.', '/admin/comprobantes');
    }

    public function rechazar(int $id = 0): void
    {
        $this->exigirRol('admin', 'contabilidad');
        $this->verificarCsrf();
        $motivo = Peticion::texto('motivo');
        if (mb_strlen($motivo) < 5) {
            $this->error('Escriba el motivo del rechazo para que el residente sepa qué corregir.', '/admin/comprobantes');
        }
        if (!Pago::rechazar($id, $motivo)) {
            $this->error('El comprobante ya fue procesado.', '/admin/comprobantes');
        }
        $pago = Pago::porId($id);
        $residente = $pago !== null ? Casa::propietario((int) $pago['casa_id']) : null;
        if ($residente !== null && !empty($residente['correo'])) {
            Correo::enviar(
                (string) $residente['correo'],
                (string) $residente['nombre'],
                'Su comprobante no pudo ser aprobado',
                Correo::plantillaHtml(
                    'Comprobante rechazado',
                    '<p>Estimado(a) ' . e((string) $residente['nombre']) . ',</p>'
                    . '<p>Revisamos el comprobante que envió para la vivienda <strong>'
                    . e((string) $pago['casa']) . '</strong> y no pudimos aprobarlo por lo siguiente:</p>'
                    . '<p style="background:#F8E4E4;padding:12px 14px;border-radius:10px;color:#8A2F2F">'
                    . e($motivo) . '</p>'
                    . '<p>Puede volver a enviarlo desde su portal. Si tiene dudas, con gusto le ayudamos.</p>',
                    'Ir a mi portal',
                    Url::absoluta('/portal/pagar')
                )
            );
        }
        $this->exito('Comprobante rechazado. Se notificó al residente.', '/admin/comprobantes');
    }

    /** Genera el recibo en PDF y lo envía por correo. */
    public static function enviarRecibo(int $pagoId): bool
    {
        $pago = Pago::porId($pagoId);
        if ($pago === null || $pago['estado'] !== 'aprobado') {
            return false;
        }
        $residente = Casa::propietario((int) $pago['casa_id']);
        if ($residente === null || empty($residente['correo'])) {
            return false;
        }
        try {
            $pdf = Documento::recibo($pagoId);
        } catch (\Throwable $e) {
            \App\Core\Log::error('Recibo PDF: ' . $e->getMessage());
            return false;
        }
        $ok = Correo::enviar(
            (string) $residente['correo'],
            (string) $residente['nombre'],
            'Recibo de pago ' . ($pago['recibo'] ?? '') . ' — ' . Ajustes::get('nombre', ''),
            Correo::plantillaHtml(
                'Recibimos su pago',
                '<p>Estimado(a) ' . e((string) $residente['nombre']) . ',</p>'
                . '<p>Confirmamos la recepción de su pago por <strong>' . e(q((float) $pago['monto']))
                . '</strong> correspondiente a la vivienda <strong>' . e((string) $pago['casa']) . '</strong>.</p>'
                . '<p>Adjuntamos su recibo oficial en formato PDF. Puede verificar su autenticidad '
                . 'escaneando el código QR que aparece en el documento.</p>',
                'Ver mi estado de cuenta',
                Url::absoluta('/portal/estado-cuenta')
            ),
            [['nombre' => 'Recibo-' . ($pago['recibo'] ?? $pagoId) . '.pdf', 'contenido' => $pdf, 'mime' => 'application/pdf']]
        );
        Auditoria::registrar('enviar_recibo', 'pagos', $pagoId, $ok ? 'Enviado a ' . $residente['correo'] : 'No se pudo enviar');
        return $ok;
    }
}
