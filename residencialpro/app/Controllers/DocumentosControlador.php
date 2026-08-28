<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controlador;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Models\Casa;
use App\Models\Documento;
use App\Models\Exportar;
use App\Models\Pago;
use App\Models\Visita;
use Vendor\Qr\QrCode;

final class DocumentosControlador extends Controlador
{
    public function recibo(int $id = 0): void
    {
        $pago = Pago::porId($id);
        if ($pago === null) {
            Respuesta::abortar(404, 'El recibo no existe.');
        }
        $this->exigirAcceso((int) $pago['casa_id']);
        Auditoria::registrar('descargar_recibo', 'pagos', $id);
        Respuesta::verEnLinea(Documento::recibo($id), 'Recibo-' . ($pago['recibo'] ?? $id) . '.pdf', 'application/pdf');
    }

    public function estadoCuenta(int $casa = 0): void
    {
        $this->exigirAcceso($casa);
        $c = Casa::porId($casa);
        Respuesta::verEnLinea(
            Documento::estadoCuenta($casa),
            'Estado-de-cuenta-' . ($c['codigo'] ?? $casa) . '.pdf',
            'application/pdf'
        );
    }

    public function solvencia(int $casa = 0): void
    {
        $this->exigirAcceso($casa);
        $c = Casa::porId($casa);
        Auditoria::registrar('emitir_solvencia', 'casas', $casa, (string) ($c['codigo'] ?? ''));
        Respuesta::verEnLinea(
            Documento::solvencia($casa),
            'Constancia-solvencia-' . ($c['codigo'] ?? $casa) . '.pdf',
            'application/pdf'
        );
    }

    public function carta(int $casa = 0): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        $c = Casa::porId($casa);
        if ($c === null) {
            Respuesta::abortar(404, 'La vivienda no existe.');
        }
        DB::insertar('cobranza_log', [
            'casa_id' => $casa,
            'tipo'    => 'carta',
            'canal'   => 'pdf',
            'detalle' => 'Carta de cobro generada desde el panel',
            'saldo'   => Casa::saldo($casa),
        ]);
        Auditoria::registrar('carta_cobro', 'casas', $casa, (string) $c['codigo']);
        Respuesta::verEnLinea(Documento::cartaCobro($casa), 'Carta-de-cobro-' . $c['codigo'] . '.pdf', 'application/pdf');
    }

    public function morosidad(): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        $filtros = ['fase' => Peticion::entero('fase'), 'calle' => Peticion::entero('calle')];
        Respuesta::verEnLinea(Documento::morosidad($filtros), 'Morosidad-' . date('Y-m-d') . '.pdf', 'application/pdf');
    }

    public function informe(string $periodo = ''): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            $periodo = date('Y-m');
        }
        Respuesta::verEnLinea(Documento::informeMensual($periodo), 'Informe-' . $periodo . '.pdf', 'application/pdf');
    }

    public function acta(int $id = 0): void
    {
        $this->exigirRol('admin', 'junta');
        Respuesta::verEnLinea(Documento::actaVotacion($id), 'Acta-votacion-' . $id . '.pdf', 'application/pdf');
    }

    public function pase(int $id = 0): void
    {
        $p = DB::uno('SELECT * FROM preregistros WHERE id = :id', ['id' => $id]);
        if ($p === null) {
            Respuesta::abortar(404, 'El pase no existe.');
        }
        $this->exigirAcceso((int) $p['casa_id']);
        Respuesta::verEnLinea(Documento::pasePreregistro($id), 'Pase-' . $p['codigo'] . '.pdf', 'application/pdf');
    }

    /** Imagen PNG del código QR de un pre-registro. */
    public function qrPase(int $id = 0): void
    {
        $p = DB::uno('SELECT * FROM preregistros WHERE id = :id', ['id' => $id]);
        if ($p === null) {
            Respuesta::abortar(404, 'El pase no existe.');
        }
        $this->exigirAcceso((int) $p['casa_id']);
        $png = QrCode::png(Visita::tokenQr($p), 8, 3, 'M');
        if ($png === '') {
            Respuesta::abortar(500, 'No se pudo generar el código QR: falta la extensión GD.');
        }
        header('Cache-Control: private, max-age=300');
        Respuesta::verEnLinea($png, 'qr-' . $p['codigo'] . '.png', 'image/png');
    }

    // ---------------------------------------------------------------- EXCEL

    public function excel(string $tipo = ''): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        $filtros = [
            'fase'      => Peticion::entero('fase'),
            'calle'     => Peticion::entero('calle'),
            'desde'     => Peticion::texto('desde'),
            'hasta'     => Peticion::texto('hasta'),
            'estado'    => Peticion::texto('estado'),
            'casa'      => Peticion::entero('casa'),
            'metodo'    => Peticion::texto('metodo'),
            'categoria' => Peticion::entero('categoria'),
            'buscar'    => Peticion::texto('buscar'),
        ];
        $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        [$bin, $nombre] = match ($tipo) {
            'morosidad' => [Exportar::morosidad($filtros), 'Morosidad-' . date('Y-m-d') . '.xlsx'],
            'pagos'     => [Exportar::pagos($filtros),     'Pagos-' . date('Y-m-d') . '.xlsx'],
            'egresos'   => [Exportar::egresos($filtros),   'Egresos-' . date('Y-m-d') . '.xlsx'],
            'visitas'   => [Exportar::visitas($filtros),   'Visitas-' . date('Y-m-d') . '.xlsx'],
            'casas'     => [Exportar::casas(),             'Viviendas-' . date('Y-m-d') . '.xlsx'],
            default     => [null, ''],
        };
        if ($bin === null) {
            Respuesta::abortar(404, 'El reporte solicitado no existe.');
        }
        Auditoria::registrar('exportar_excel', null, null, $tipo);
        Respuesta::descargar($bin, $nombre, $mime);
    }

    public function excelEstadoCuenta(int $casa = 0): void
    {
        $this->exigirAcceso($casa);
        $c = Casa::porId($casa);
        Respuesta::descargar(
            Exportar::estadoCuenta($casa),
            'Estado-de-cuenta-' . ($c['codigo'] ?? $casa) . '.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    // --------------------------------------------------------- ARCHIVOS

    /** Entrega archivos subidos verificando permisos (nunca se ejecutan). */
    public function archivo(string $carpeta = '', string $nombre = ''): void
    {
        $carpetasPrivadas = ['comprobantes', 'facturas', 'visitas', 'documentos', 'incidencias'];
        $carpeta = preg_replace('/[^a-z0-9_\-]/i', '', $carpeta) ?? '';
        $nombre  = basename($nombre);
        if ($carpeta === '' || $nombre === '' || !preg_match('/^[\w\-\.]+$/', $nombre)) {
            Respuesta::abortar(404, 'Archivo no encontrado.');
        }
        if (in_array($carpeta, $carpetasPrivadas, true) && Auth::invitado()) {
            Respuesta::abortar(403, 'Debe iniciar sesión para ver este archivo.');
        }
        if ($carpeta === 'comprobantes' && !Auth::esStaff()) {
            $delResidente = DB::valor(
                'SELECT id FROM pagos WHERE comprobante = :a AND casa_id IN (' .
                (Auth::casas() !== [] ? implode(',', array_map('intval', Auth::casas())) : '0') . ')',
                ['a' => $nombre]
            );
            if (!$delResidente) {
                Respuesta::abortar(403, 'No tiene permiso para ver este comprobante.');
            }
        }
        $ruta = RUTA_BASE . '/uploads/' . $carpeta . '/' . $nombre;
        if (!is_file($ruta)) {
            Respuesta::abortar(404, 'Archivo no encontrado.');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($ruta);
        $permitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf', 'text/plain'];
        if (!in_array($mime, $permitidos, true)) {
            $mime = 'application/octet-stream';
        }
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=600');
        Respuesta::verEnLinea((string) file_get_contents($ruta), $nombre, $mime);
    }

    /** El residente solo puede ver documentos de su vivienda. */
    private function exigirAcceso(int $casaId): void
    {
        if (Auth::invitado()) {
            \App\Core\Sesion::set('_destino', Peticion::uri());
            Respuesta::redirigir('/acceso');
        }
        Auth::exigirCasa($casaId);
    }
}
