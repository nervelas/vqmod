<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Controlador;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Models\Casa;
use App\Models\Comunicacion;
use App\Models\Cuota;
use App\Models\Egreso;
use App\Models\Pago;
use App\Models\Reporte;
use App\Models\Reserva;
use App\Models\Visita;

final class TableroControlador extends Controlador
{
    public function inicio(): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        Cuota::recalcularMora();

        $u = Auth::usuario();
        if ((int) ($u['onboarding'] ?? 0) === 0 && Auth::es('admin') && Casa::contar() === 0) {
            $this->redirigir('/admin/onboarding');
        }

        $this->mostrar('admin/tablero', [
            'tituloPagina' => 'Tablero',
            'subtitulo'    => 'Resumen de ' . periodoNombre(date('Y-m')),
            'kpi'          => Reporte::tablero(),
            'serie'        => Reporte::serieRecaudacion(8),
            'flujo'        => Egreso::flujo(6),
            'egresosCat'   => Egreso::porCategoria(date('Y-m-01'), date('Y-m-t')),
            'visitasDia'   => Reporte::visitasPorDia(14),
            'ultimosPagos' => Pago::listar(['estado' => 'aprobado'], 6),
            'morosos'      => array_slice(Cuota::morosidad(), 0, 6),
            'incidencias'  => Comunicacion::incidencias(['abiertas' => true], 5),
            'reservas'     => Reserva::listar(['desde' => date('Y-m-d')], 5),
            'adentro'      => Visita::adentro(),
            'cuentas'      => Egreso::saldosCuentas(),
        ]);
    }

    public function informes(): void
    {
        $this->exigirRol('admin', 'junta', 'contabilidad');
        $periodo = Peticion::texto('periodo', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            $periodo = date('Y-m');
        }
        $desde = $periodo . '-01';
        $hasta = date('Y-m-t', (int) strtotime($desde));
        $anio  = (int) substr($periodo, 0, 4);

        $this->mostrar('admin/informes', [
            'tituloPagina' => 'Informes',
            'subtitulo'    => periodoNombre($periodo),
            'periodo'      => $periodo,
            'ingresos'     => Pago::recaudado($desde, $hasta),
            'egresos'      => Egreso::total($desde, $hasta),
            'esperado'     => Cuota::esperadoPeriodo($periodo),
            'porCategoria' => Egreso::porCategoria($desde, $hasta),
            'porMetodo'    => Pago::porMetodo($desde, $hasta),
            'presupuesto'  => Egreso::presupuestoVsReal($anio),
            'flujo'        => Egreso::flujo(12),
            'cuentas'      => Egreso::saldosCuentas(),
            'morosidad'    => Cuota::resumenMorosidad(Cuota::morosidad()),
        ]);
    }

    public function auditoria(): void
    {
        $this->exigirRol('admin');
        $filtros = [
            'accion' => Peticion::texto('accion'),
            'desde'  => Peticion::texto('desde'),
            'buscar' => Peticion::texto('buscar'),
        ];
        $this->mostrar('admin/auditoria', [
            'tituloPagina' => 'Auditoría',
            'subtitulo'    => 'Bitácora de operaciones sensibles',
            'registros'    => Reporte::auditoria($filtros, 300),
            'filtros'      => $filtros,
            'acciones'     => DB::todos('SELECT DISTINCT accion FROM auditoria ORDER BY accion'),
        ]);
    }

    public function respaldos(): void
    {
        $this->exigirRol('admin');
        $dir = RUTA_BASE . '/storage/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if ($this->post()) {
            $this->verificarCsrf();
            $accion = Peticion::texto('accion');
            if ($accion === 'crear') {
                try {
                    $archivo = self::crearRespaldo();
                    Auditoria::registrar('respaldo', null, null, basename($archivo));
                    $this->exito('Respaldo generado: ' . basename($archivo), '/admin/respaldos');
                } catch (\Throwable $e) {
                    $this->error('No se pudo generar el respaldo: ' . $e->getMessage(), '/admin/respaldos');
                }
            }
            if ($accion === 'borrar') {
                $nombre = basename(Peticion::texto('archivo'));
                if (preg_match('/^respaldo-[\w\-]+\.sql(\.gz)?$/', $nombre) && is_file($dir . '/' . $nombre)) {
                    @unlink($dir . '/' . $nombre);
                    Auditoria::registrar('borrar_respaldo', null, null, $nombre);
                    $this->exito('Respaldo eliminado.', '/admin/respaldos');
                }
                $this->error('El archivo indicado no existe.', '/admin/respaldos');
            }
        }

        if (Peticion::texto('descargar') !== '') {
            $nombre = basename(Peticion::texto('descargar'));
            $ruta   = $dir . '/' . $nombre;
            if (preg_match('/^respaldo-[\w\-]+\.sql(\.gz)?$/', $nombre) && is_file($ruta)) {
                Auditoria::registrar('descargar_respaldo', null, null, $nombre);
                Respuesta::descargar((string) file_get_contents($ruta), $nombre, 'application/octet-stream');
            }
            $this->error('El archivo indicado no existe.', '/admin/respaldos');
        }

        $archivos = [];
        foreach (glob($dir . '/respaldo-*.sql*') ?: [] as $f) {
            $archivos[] = ['nombre' => basename($f), 'tamano' => filesize($f), 'fecha' => filemtime($f)];
        }
        usort($archivos, static fn($a, $b) => $b['fecha'] <=> $a['fecha']);

        $this->mostrar('admin/respaldos', [
            'tituloPagina' => 'Respaldos',
            'subtitulo'    => 'Copias de seguridad de la base de datos',
            'archivos'     => $archivos,
            'automatico'   => Ajustes::esVerdadero('respaldo_automatico', true),
            'cronToken'    => (string) Config::get('cron.token', ''),
        ]);
    }

    /** Onboarding en 4 pasos la primera vez. */
    public function onboarding(): void
    {
        $this->exigirRol('admin');
        $paso = max(1, min(4, Peticion::entero('paso', 1)));

        if ($this->post()) {
            $this->verificarCsrf();
            $accion = Peticion::texto('accion');

            if ($accion === 'condominio') {
                Ajustes::setVarios([
                    'nombre'    => Peticion::texto('nombre'),
                    'direccion' => Peticion::texto('direccion'),
                    'telefono'  => Peticion::texto('telefono'),
                    'correo'    => Peticion::texto('correo'),
                ]);
                $this->redirigir('/admin/onboarding?paso=2');
            }

            if ($accion === 'casas') {
                $fase    = Peticion::texto('fase', 'Fase única');
                $prefijo = strtoupper(Peticion::texto('prefijo', 'C'));
                $desde   = max(1, Peticion::entero('desde', 1));
                $hasta   = min($desde + 499, max($desde, Peticion::entero('hasta', 20)));
                $metros  = Peticion::decimal('metros', 0);

                $faseId = (int) DB::valor('SELECT id FROM fases WHERE nombre = :n', ['n' => $fase], 0);
                if ($faseId === 0) {
                    $faseId = DB::insertar('fases', ['nombre' => $fase, 'orden' => 1]);
                }
                $creadas = 0;
                $total   = $hasta - $desde + 1;
                $coef    = $total > 0 ? round(100 / $total, 5) : 0;
                for ($i = $desde; $i <= $hasta; $i++) {
                    $codigo = $prefijo . '-' . $i;
                    if (DB::valor('SELECT id FROM casas WHERE codigo = :c', ['c' => $codigo])) {
                        continue;
                    }
                    DB::insertar('casas', [
                        'fase_id'     => $faseId,
                        'codigo'      => $codigo,
                        'tipo'        => 'casa',
                        'metros'      => $metros,
                        'coeficiente' => $coef,
                        'estado'      => 'habitada',
                    ]);
                    $creadas++;
                }
                Auditoria::registrar('onboarding_casas', 'casas', null, $creadas . ' viviendas creadas');
                $this->redirigir('/admin/onboarding?paso=3');
            }

            if ($accion === 'cuota') {
                $monto = Peticion::decimal('monto', 0);
                if ($monto > 0) {
                    $existe = (int) DB::valor('SELECT id FROM conceptos WHERE nombre = :n', ['n' => 'Cuota de mantenimiento'], 0);
                    if ($existe === 0) {
                        DB::insertar('conceptos', [
                            'nombre'       => 'Cuota de mantenimiento',
                            'descripcion'  => 'Cuota ordinaria mensual de mantenimiento y seguridad.',
                            'calculo'      => 'fijo',
                            'monto'        => $monto,
                            'periodicidad' => 'mensual',
                            'dia_vence'    => max(1, min(28, Peticion::entero('dia_vence', 10))),
                            'mora_tipo'    => 'porcentaje',
                            'mora_valor'   => Peticion::decimal('mora', 2),
                            'automatico'   => 1,
                            'orden'        => 1,
                        ]);
                    } else {
                        DB::actualizar('conceptos', ['monto' => $monto], 'id = :id', ['id' => $existe]);
                    }
                    if (Peticion::bool('generar')) {
                        Cuota::generarPeriodo(date('Y-m'));
                    }
                }
                $this->redirigir('/admin/onboarding?paso=4');
            }

            if ($accion === 'aviso') {
                $titulo = Peticion::texto('titulo');
                if ($titulo !== '') {
                    Comunicacion::guardarAviso([
                        'titulo'      => $titulo,
                        'cuerpo'      => Peticion::texto('cuerpo'),
                        'alcance'     => 'todos',
                        'prioridad'   => 'importante',
                        'publicar_en' => date('Y-m-d H:i:s'),
                    ]);
                }
                DB::actualizar('usuarios', ['onboarding' => 1], 'id = :id', ['id' => Auth::id()]);
                $_SESSION['usuario']['onboarding'] = 1;
                $this->exito('¡Listo! Su residencial ya está configurado.', '/admin');
            }

            // El botón de omitir lleva nombre propio: repetir «accion» en el
            // mismo formulario es HTML inválido y depende del orden de envío.
            if ($accion === 'omitir' || Peticion::texto('omitir') !== '') {
                DB::actualizar('usuarios', ['onboarding' => 1], 'id = :id', ['id' => Auth::id()]);
                $_SESSION['usuario']['onboarding'] = 1;
                $this->redirigir('/admin');
            }
        }

        $this->mostrar('admin/onboarding', [
            'tituloPagina' => 'Configuración inicial',
            'paso'         => $paso,
            'totalCasas'   => Casa::contar(),
        ], 'limpio');
    }

    /** Genera un volcado SQL de todas las tablas. */
    public static function crearRespaldo(): string
    {
        $dir = RUTA_BASE . '/storage/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $nombre = 'respaldo-' . date('Y-m-d-His') . '.sql';
        $ruta   = $dir . '/' . $nombre;
        $fh     = fopen($ruta, 'w');
        if ($fh === false) {
            throw new \RuntimeException('No se pudo escribir en /storage/backups.');
        }
        fwrite($fh, "-- Respaldo de ResidencialPro\n-- " . date('d/m/Y H:i:s') . "\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        $tablas = DB::conexion()->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tablas as $tabla) {
            $crear = DB::uno('SHOW CREATE TABLE `' . $tabla . '`');
            fwrite($fh, "DROP TABLE IF EXISTS `{$tabla}`;\n" . ($crear['Create Table'] ?? '') . ";\n\n");
            $st = DB::conexion()->query('SELECT * FROM `' . $tabla . '`');
            $n = 0;
            while ($fila = $st->fetch(\PDO::FETCH_ASSOC)) {
                $vals = [];
                foreach ($fila as $v) {
                    $vals[] = $v === null ? 'NULL' : DB::conexion()->quote((string) $v);
                }
                fwrite($fh, "INSERT INTO `{$tabla}` VALUES (" . implode(',', $vals) . ");\n");
                $n++;
            }
            if ($n > 0) {
                fwrite($fh, "\n");
            }
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fh);

        if (function_exists('gzencode')) {
            $gz = gzencode((string) file_get_contents($ruta), 6);
            if ($gz !== false) {
                file_put_contents($ruta . '.gz', $gz);
                @unlink($ruta);
                $ruta .= '.gz';
            }
        }
        // Conservar únicamente los 12 respaldos más recientes.
        $todos = glob($dir . '/respaldo-*.sql*') ?: [];
        usort($todos, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($todos, 12) as $viejo) {
            @unlink($viejo);
        }
        return $ruta;
    }
}
