<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Controlador;
use App\Core\Correo;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Url;
use App\Core\Validador;
use App\Models\Casa;
use App\Models\Cuota;

final class CuotasControlador extends Controlador
{
    // ------------------------------------------------------------ CONCEPTOS

    public function index(): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        $conceptos = Cuota::conceptos(false);
        foreach ($conceptos as &$c) {
            $c['emitido'] = (float) DB::valor(
                'SELECT COALESCE(SUM(monto),0) FROM cargos WHERE concepto_id = :k AND estado <> "anulado"',
                ['k' => (int) $c['id']], 0
            );
            $c['pendiente'] = (float) DB::valor(
                'SELECT COALESCE(SUM(monto + mora - descuento - pagado),0) FROM cargos
                 WHERE concepto_id = :k AND estado IN ("pendiente","parcial")',
                ['k' => (int) $c['id']], 0
            );
        }
        unset($c);

        $this->mostrar('admin/cuotas/index', [
            'tituloPagina' => 'Cuotas y conceptos',
            'subtitulo'    => 'Definición de los cobros del residencial',
            'conceptos'    => $conceptos,
            'totalCasas'   => Casa::contar(),
            'ultimoPeriodo' => (string) DB::valor('SELECT MAX(periodo) FROM cargos', [], ''),
        ]);
    }

    public function concepto(int $id = 0): void
    {
        $this->exigirRol('admin');
        $concepto = $id > 0 ? Cuota::concepto($id) : null;
        if ($id > 0 && $concepto === null) {
            $this->error('El concepto no existe.', '/admin/cuotas');
        }

        if ($this->post()) {
            $this->verificarCsrf();
            $v = new Validador();
            $nombre = Peticion::texto('nombre');
            $v->requerido('nombre', $nombre, 'El nombre del concepto')
              ->largoMax('nombre', $nombre, 120, 'El nombre')
              ->numero('monto', Peticion::decimal('monto'), 'El monto', 0)
              ->en('calculo', Peticion::texto('calculo'), ['fijo', 'coeficiente', 'metros'], 'La forma de cálculo')
              ->en('periodicidad', Peticion::texto('periodicidad'), ['mensual', 'bimestral', 'trimestral', 'anual', 'unico'], 'La periodicidad')
              ->numero('dia_vence', Peticion::entero('dia_vence'), 'El día de vencimiento', 1, 28)
              ->en('mora_tipo', Peticion::texto('mora_tipo'), ['ninguna', 'fijo', 'porcentaje'], 'El tipo de mora');

            if ($v->ok()) {
                $datos = [
                    'nombre'       => $nombre,
                    'descripcion'  => Peticion::texto('descripcion') ?: null,
                    'calculo'      => Peticion::texto('calculo'),
                    'monto'        => Peticion::decimal('monto'),
                    'periodicidad' => Peticion::texto('periodicidad'),
                    'dia_vence'    => Peticion::entero('dia_vence', 10),
                    'mora_tipo'    => Peticion::texto('mora_tipo'),
                    'mora_valor'   => Peticion::decimal('mora_valor'),
                    'pronto_pago'  => Peticion::decimal('pronto_pago'),
                    'pronto_dias'  => Peticion::entero('pronto_dias'),
                    'automatico'   => Peticion::bool('automatico') ? 1 : 0,
                    'activo'       => Peticion::bool('activo') ? 1 : 0,
                    'orden'        => Peticion::entero('orden'),
                ];
                if ($id > 0) {
                    DB::actualizar('conceptos', $datos, 'id = :id', ['id' => $id]);
                    Auditoria::registrar('editar_concepto', 'conceptos', $id, $nombre);
                    $this->exito('Concepto actualizado. Los cargos ya emitidos no cambian.', '/admin/cuotas');
                }
                $nuevo = DB::insertar('conceptos', $datos);
                Auditoria::registrar('crear_concepto', 'conceptos', $nuevo, $nombre);
                $this->exito('Concepto creado.', '/admin/cuotas');
            }
            $this->error($v->primerError(), $id > 0 ? '/admin/cuotas/concepto/' . $id : '/admin/cuotas/concepto');
        }

        $this->mostrar('admin/cuotas/concepto', [
            'tituloPagina' => $id > 0 ? 'Editar concepto' : 'Nuevo concepto de cobro',
            'concepto'     => $concepto,
            'totalCasas'   => Casa::contar(),
            'totalMetros'  => (float) DB::valor('SELECT COALESCE(SUM(metros),0) FROM casas', [], 0),
        ]);
    }

    // -------------------------------------------------------------- CARGOS

    public function generar(): void
    {
        $this->exigirRol('admin');
        $resultado = null;
        $periodo   = Peticion::texto('periodo', date('Y-m'));

        if ($this->post()) {
            $this->verificarCsrf();
            if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
                $this->error('Seleccione un período válido.', '/admin/cuotas/generar');
            }
            try {
                $resultado = Cuota::generarPeriodo($periodo, Peticion::entero('concepto_id') ?: null);
                Cuota::recalcularMora();
                $mensaje = $resultado['creados'] > 0
                    ? 'Se generaron ' . $resultado['creados'] . ' cargos por ' . q($resultado['monto']) . '.'
                    : 'No se generó ningún cargo nuevo: ya existían para ese período.';
                $this->exito($mensaje, '/admin/cargos?periodo=' . $periodo);
            } catch (\Throwable $e) {
                $this->error('No se pudieron generar los cargos: ' . $e->getMessage(), '/admin/cuotas/generar');
            }
        }

        $existentes = DB::todos(
            'SELECT periodo, COUNT(*) AS n, COALESCE(SUM(monto),0) AS total
             FROM cargos WHERE periodo IS NOT NULL AND estado <> "anulado"
             GROUP BY periodo ORDER BY periodo DESC LIMIT 12'
        );

        $this->mostrar('admin/cuotas/generar', [
            'tituloPagina' => 'Generar cargos del período',
            'subtitulo'    => 'Emisión automática para todas las viviendas',
            'conceptos'    => Cuota::conceptos(),
            'periodo'      => $periodo,
            'existentes'   => $existentes,
            'totalCasas'   => Casa::contar(),
            'resultado'    => $resultado,
        ]);
    }

    public function cargos(): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        [$porPagina, $desde, $pagina] = $this->paginacion(40);
        Cuota::recalcularMora();

        $where  = ['1=1'];
        $params = [];
        $periodo = Peticion::texto('periodo');
        $estado  = Peticion::texto('estado');
        $concepto = Peticion::entero('concepto');
        $buscar  = Peticion::texto('buscar');
        if ($periodo !== '')  { $where[] = 'g.periodo = :p';     $params['p'] = $periodo; }
        if ($estado !== '')   { $where[] = 'g.estado = :e';      $params['e'] = $estado; }
        if ($concepto > 0)    { $where[] = 'g.concepto_id = :k'; $params['k'] = $concepto; }
        if ($buscar !== '')   { $where[] = '(c.codigo LIKE :b OR g.descripcion LIKE :b)'; $params['b'] = '%' . $buscar . '%'; }
        $sqlWhere = implode(' AND ', $where);

        $total = (int) DB::valor(
            'SELECT COUNT(*) FROM cargos g LEFT JOIN casas c ON c.id = g.casa_id WHERE ' . $sqlWhere, $params, 0
        );
        $cargos = DB::todos(
            'SELECT g.*, c.codigo AS casa, k.nombre AS concepto
             FROM cargos g
             LEFT JOIN casas c ON c.id = g.casa_id
             LEFT JOIN conceptos k ON k.id = g.concepto_id
             WHERE ' . $sqlWhere . '
             ORDER BY g.fecha_vence DESC, c.codigo
             LIMIT ' . $porPagina . ' OFFSET ' . $desde,
            $params
        );
        $resumen = DB::uno(
            'SELECT COALESCE(SUM(g.monto),0) AS emitido,
                    COALESCE(SUM(g.mora),0) AS mora,
                    COALESCE(SUM(g.pagado),0) AS pagado,
                    COALESCE(SUM(CASE WHEN g.estado IN ("pendiente","parcial")
                         THEN g.monto + g.mora - g.descuento - g.pagado ELSE 0 END),0) AS saldo
             FROM cargos g LEFT JOIN casas c ON c.id = g.casa_id WHERE ' . $sqlWhere,
            $params
        ) ?? [];

        $this->mostrar('admin/cuotas/cargos', [
            'tituloPagina' => 'Cargos emitidos',
            'subtitulo'    => number_format($total) . ' cargo(s)',
            'cargos'       => $cargos,
            'total'        => $total,
            'pagina'       => $pagina,
            'porPagina'    => $porPagina,
            'resumen'      => $resumen,
            'conceptos'    => Cuota::conceptos(false),
            'periodos'     => DB::todos('SELECT DISTINCT periodo FROM cargos WHERE periodo IS NOT NULL ORDER BY periodo DESC LIMIT 24'),
            'filtros'      => ['periodo' => $periodo, 'estado' => $estado, 'concepto' => $concepto, 'buscar' => $buscar],
        ]);
    }

    public function nuevoCargo(): void
    {
        $this->exigirRol('admin');
        if ($this->post()) {
            $this->verificarCsrf();
            $casas = Peticion::arreglo('casas');
            if (Peticion::texto('destino') === 'todas') {
                $casas = array_map(static fn($c) => (int) $c['id'], Casa::opciones());
            }
            if (Peticion::texto('destino') === 'fase') {
                $casas = array_map(
                    static fn($c) => (int) $c['id'],
                    DB::todos('SELECT id FROM casas WHERE fase_id = :f', ['f' => Peticion::entero('fase_id')])
                );
            }
            $descripcion = Peticion::texto('descripcion');
            $monto       = Peticion::decimal('monto');
            $vence       = Peticion::texto('vence', date('Y-m-d', strtotime('+15 days')));

            if ($descripcion === '' || $monto <= 0 || $casas === []) {
                $this->error('Indique la descripción, el monto y al menos una vivienda.', '/admin/cargos/nuevo');
            }
            $n = 0;
            foreach ($casas as $casaId) {
                Cuota::crearCargo((int) $casaId, $descripcion, $monto, $vence, Peticion::entero('concepto_id') ?: null);
                $n++;
            }
            $this->exito('Se crearon ' . $n . ' cargo(s) por ' . q($monto * $n) . '.', '/admin/cargos');
        }

        $this->mostrar('admin/cuotas/nuevo-cargo', [
            'tituloPagina' => 'Nuevo cargo manual',
            'subtitulo'    => 'Multas, cuotas extraordinarias y cobros puntuales',
            'casas'        => Casa::opciones(),
            'fases'        => Casa::fases(),
            'conceptos'    => Cuota::conceptos(),
            'casaPre'      => Peticion::entero('casa'),
        ]);
    }

    public function anularCargo(int $id = 0): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        $cargo = Cuota::cargo($id);
        if ($cargo === null) {
            $this->error('El cargo no existe.', '/admin/cargos');
        }
        if (!Cuota::anularCargo($id, Peticion::texto('motivo', 'Anulado desde el panel'))) {
            $this->error('No se puede anular un cargo que ya tiene pagos aplicados. Anule primero el pago.', '/admin/casas/' . (int) $cargo['casa_id']);
        }
        $this->exito('Cargo anulado.', '/admin/casas/' . (int) $cargo['casa_id']);
    }

    // ----------------------------------------------------------- MOROSIDAD

    public function morosidad(): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        Cuota::recalcularMora();
        $filtros = ['fase' => Peticion::entero('fase'), 'calle' => Peticion::entero('calle')];
        $filas   = Cuota::morosidad($filtros);
        $tramo   = Peticion::texto('tramo');
        if ($tramo !== '') {
            $filas = array_values(array_filter($filas, static function ($f) use ($tramo): bool {
                $d = (int) $f['dias'];
                return match ($tramo) {
                    'd30'  => $d >= 1 && $d <= 30,
                    'd60'  => $d > 30 && $d <= 60,
                    'd90'  => $d > 60 && $d <= 90,
                    'd120' => $d > 90,
                    default => true,
                };
            }));
        }

        $this->mostrar('admin/cuotas/morosidad', [
            'tituloPagina' => 'Morosidad',
            'subtitulo'    => 'Cartera vencida por antigüedad',
            'filas'        => $filas,
            'resumen'      => Cuota::resumenMorosidad($filas),
            'fases'        => Casa::fases(),
            'filtros'      => $filtros,
            'tramo'        => $tramo,
            'diasCarta'    => (int) Ajustes::num('carta_dias', 60),
            'diasCorte'    => (int) Ajustes::num('corte_dias', 90),
        ]);
    }

    /** Envía recordatorios de cobro por correo a las casas seleccionadas. */
    public function recordatorios(): void
    {
        $this->exigirRol('admin');
        $this->verificarCsrf();
        $casas = Peticion::arreglo('casas');
        if ($casas === []) {
            $this->error('Seleccione al menos una vivienda.', '/admin/morosidad');
        }
        $enviados = 0;
        foreach ($casas as $casaId) {
            if (self::enviarRecordatorio((int) $casaId)) {
                $enviados++;
            }
        }
        Auditoria::registrar('recordatorios', null, null, $enviados . ' recordatorio(s) enviados');
        $this->exito('Se enviaron ' . $enviados . ' recordatorio(s) por correo.', '/admin/morosidad');
    }

    /** Recordatorio de cobro reutilizado por el panel y por el cron. */
    public static function enviarRecordatorio(int $casaId, string $tipo = 'recordatorio'): bool
    {
        $casa = Casa::porId($casaId);
        if ($casa === null) {
            return false;
        }
        $residente = Casa::propietario($casaId);
        if ($residente === null || empty($residente['correo'])) {
            return false;
        }
        $saldo = Casa::saldo($casaId);
        if ($saldo <= 0.009) {
            return false;
        }
        $cargos = Cuota::cargos($casaId, 'pendientes', 20);
        $filas = '';
        foreach ($cargos as $c) {
            $filas .= '<tr><td style="padding:7px 0;border-bottom:1px solid #EDE8DC">' . e((string) $c['descripcion'])
                . '<br><small style="color:#6E6A61">Vence ' . e(fecha((string) $c['fecha_vence'])) . '</small></td>'
                . '<td style="padding:7px 0;border-bottom:1px solid #EDE8DC;text-align:right;white-space:nowrap">'
                . e(q(Cuota::saldoCargo($c))) . '</td></tr>';
        }
        $titulo = $tipo === 'vencimiento'
            ? 'Su cuota vence hoy'
            : ($tipo === 'previo' ? 'Su cuota vence pronto' : 'Recordatorio de saldo pendiente');

        $enlacePago = Ajustes::get('enlace_pago', '');
        $ok = Correo::enviar(
            (string) $residente['correo'],
            (string) $residente['nombre'],
            $titulo . ' — ' . $casa['codigo'],
            Correo::plantillaHtml(
                $titulo,
                '<p>Estimado(a) ' . e((string) $residente['nombre']) . ',</p>'
                . '<p>Le compartimos el detalle del saldo de la vivienda <strong>' . e((string) $casa['codigo']) . '</strong>:</p>'
                . '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $filas
                . '<tr><td style="padding:11px 0;font-weight:700">Total a pagar</td>'
                . '<td style="padding:11px 0;text-align:right;font-weight:700;font-size:17px;color:#0E4C5A">' . e(q($saldo)) . '</td></tr>'
                . '</table>'
                . (Ajustes::get('cuenta_deposito', '') !== ''
                    ? '<p style="font-size:13px;color:#5B6259">Puede depositar o transferir a: ' . e(Ajustes::get('cuenta_deposito')) . '</p>'
                    : '')
                . '<p>Si ya realizó su pago, ignore este mensaje o adjunte su comprobante desde el portal para que lo registremos.</p>',
                $enlacePago !== '' ? 'Pagar en línea' : 'Ver mi estado de cuenta',
                $enlacePago !== '' ? $enlacePago : Url::absoluta('/portal/estado-cuenta')
            )
        );
        DB::insertar('cobranza_log', [
            'casa_id' => $casaId,
            'tipo'    => $tipo,
            'canal'   => 'correo',
            'detalle' => $ok ? 'Enviado a ' . $residente['correo'] : 'No se pudo enviar',
            'saldo'   => $saldo,
        ]);
        return $ok;
    }
}
