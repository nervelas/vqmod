<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Ajustes;
use Vendor\Xlsx\Xlsx;

/** Exportaciones a Excel. */
final class Exportar
{
    private static function cab(array $titulos): array
    {
        return array_map(static fn($t) => ['v' => $t, 'estilo' => 'cabecera'], $titulos);
    }

    public static function morosidad(array $filtros = []): string
    {
        $filas   = Cuota::morosidad($filtros);
        $resumen = Cuota::resumenMorosidad($filas);
        $x = new Xlsx();
        $x->titulo('Morosidad — ' . Ajustes::get('nombre', 'ResidencialPro'));

        $datos = [self::cab(['Casa', 'Fase', 'Calle', 'Residente', 'Teléfono', 'Correo',
                             'Vence más antiguo', 'Días', 'Por vencer', '1-30', '31-60', '61-90', '+90', 'Mora', 'Saldo total'])];
        foreach ($filas as $f) {
            $a = $f['antiguedad'];
            $datos[] = [
                $f['codigo'], $f['fase'], $f['calle'], $f['residente'], $f['telefono'], $f['correo'],
                ['v' => $f['vence'], 't' => Xlsx::TXT],
                ['v' => $f['dias'], 't' => Xlsx::NUM],
                ['v' => $a['corriente'], 't' => Xlsx::MONEY],
                ['v' => $a['d30'], 't' => Xlsx::MONEY],
                ['v' => $a['d60'], 't' => Xlsx::MONEY],
                ['v' => $a['d90'], 't' => Xlsx::MONEY],
                ['v' => $a['d120'], 't' => Xlsx::MONEY],
                ['v' => (float) $f['mora'], 't' => Xlsx::MONEY],
                ['v' => (float) $f['saldo'], 't' => Xlsx::MONEY],
            ];
        }
        $datos[] = [
            ['v' => 'TOTALES (' . $resumen['casas'] . ' viviendas)', 'estilo' => 'total'],
            ['v' => '', 'estilo' => 'total'], ['v' => '', 'estilo' => 'total'], ['v' => '', 'estilo' => 'total'],
            ['v' => '', 'estilo' => 'total'], ['v' => '', 'estilo' => 'total'], ['v' => '', 'estilo' => 'total'],
            ['v' => '', 'estilo' => 'total'],
            ['v' => $resumen['corriente'], 't' => Xlsx::MONEY, 'estilo' => 'total'],
            ['v' => $resumen['d30'], 't' => Xlsx::MONEY, 'estilo' => 'total'],
            ['v' => $resumen['d60'], 't' => Xlsx::MONEY, 'estilo' => 'total'],
            ['v' => $resumen['d90'], 't' => Xlsx::MONEY, 'estilo' => 'total'],
            ['v' => $resumen['d120'], 't' => Xlsx::MONEY, 'estilo' => 'total'],
            ['v' => '', 'estilo' => 'total'],
            ['v' => $resumen['total'], 't' => Xlsx::MONEY, 'estilo' => 'total'],
        ];
        $x->hoja('Morosidad', $datos, [12, 20, 20, 34, 16, 28, 18, 8, 14, 14, 14, 14, 14, 14, 16]);
        return $x->salida();
    }

    public static function pagos(array $filtros = []): string
    {
        $filas = Pago::listar($filtros, 5000);
        $x = new Xlsx();
        $x->titulo('Pagos');
        $datos = [self::cab(['Recibo', 'Fecha', 'Casa', 'Residente', 'Forma de pago', 'Referencia', 'Estado', 'Monto'])];
        $total = 0.0;
        foreach ($filas as $f) {
            if ($f['estado'] === 'aprobado') {
                $total += (float) $f['monto'];
            }
            $datos[] = [
                $f['recibo'], $f['fecha'], $f['casa'], $f['residente'], ucfirst((string) $f['metodo']),
                $f['referencia'], ucfirst((string) $f['estado']),
                ['v' => (float) $f['monto'], 't' => Xlsx::MONEY],
            ];
        }
        $datos[] = [
            ['v' => 'TOTAL APROBADO', 'estilo' => 'total'], ['v' => '', 'estilo' => 'total'], ['v' => '', 'estilo' => 'total'],
            ['v' => '', 'estilo' => 'total'], ['v' => '', 'estilo' => 'total'], ['v' => '', 'estilo' => 'total'],
            ['v' => '', 'estilo' => 'total'], ['v' => round($total, 2), 't' => Xlsx::MONEY, 'estilo' => 'total'],
        ];
        $x->hoja('Pagos', $datos, [14, 12, 12, 34, 18, 20, 14, 16]);
        return $x->salida();
    }

    public static function egresos(array $filtros = []): string
    {
        $filas = Egreso::listar($filtros, 5000);
        $x = new Xlsx();
        $x->titulo('Egresos');
        $datos = [self::cab(['Fecha', 'Categoría', 'Proveedor', 'Descripción', 'Documento', 'Forma de pago', 'Cuenta', 'Monto'])];
        $total = 0.0;
        foreach ($filas as $f) {
            $total += (float) $f['monto'];
            $datos[] = [
                $f['fecha'], $f['categoria'], $f['proveedor'], $f['descripcion'], $f['documento'],
                ucfirst((string) $f['metodo']), $f['cuenta'],
                ['v' => (float) $f['monto'], 't' => Xlsx::MONEY],
            ];
        }
        $datos[] = [
            ['v' => 'TOTAL', 'estilo' => 'total'], ['v' => '', 'estilo' => 'total'], ['v' => '', 'estilo' => 'total'],
            ['v' => '', 'estilo' => 'total'], ['v' => '', 'estilo' => 'total'], ['v' => '', 'estilo' => 'total'],
            ['v' => '', 'estilo' => 'total'], ['v' => round($total, 2), 't' => Xlsx::MONEY, 'estilo' => 'total'],
        ];
        $x->hoja('Egresos', $datos, [12, 22, 26, 40, 16, 16, 20, 16]);
        return $x->salida();
    }

    public static function visitas(array $filtros = []): string
    {
        $filas = Visita::listar($filtros, 5000);
        $x = new Xlsx();
        $x->titulo('Visitas');
        $datos = [self::cab(['Entrada', 'Salida', 'Tipo', 'Visitante', 'DPI', 'Placa', 'Casa', 'Motivo', 'Guardia'])];
        foreach ($filas as $f) {
            $datos[] = [
                $f['entrada'], $f['salida'], ucfirst((string) $f['tipo']), $f['visitante'], $f['dpi'],
                $f['placa'], $f['casa'], $f['motivo'], $f['guardia'],
            ];
        }
        $x->hoja('Visitas', $datos, [18, 18, 12, 30, 16, 12, 12, 30, 24]);
        return $x->salida();
    }

    public static function casas(): string
    {
        $filas = Casa::listar([], 5000);
        $x = new Xlsx();
        $x->titulo('Padrón de viviendas');
        $datos = [self::cab(['Código', 'Fase', 'Calle', 'Tipo', 'Metros', 'Coeficiente %', 'Parqueos',
                             'Estado', 'Residente', 'Teléfono', 'Saldo'])];
        foreach ($filas as $f) {
            $datos[] = [
                $f['codigo'], $f['fase'], $f['calle'], ucfirst((string) $f['tipo']),
                ['v' => (float) $f['metros'], 't' => Xlsx::NUM],
                ['v' => (float) $f['coeficiente'], 't' => Xlsx::NUM],
                ['v' => (int) $f['parqueos'], 't' => Xlsx::NUM],
                ucfirst((string) $f['estado']), $f['residente'], $f['telefono'],
                ['v' => (float) $f['saldo'], 't' => Xlsx::MONEY],
            ];
        }
        $x->hoja('Viviendas', $datos, [12, 20, 20, 14, 10, 14, 10, 14, 34, 16, 16]);
        return $x->salida();
    }

    public static function estadoCuenta(int $casaId): string
    {
        $casa   = Casa::porId($casaId) ?? [];
        $cargos = Cuota::cargos($casaId, 'vigentes');
        $x = new Xlsx();
        $x->titulo('Estado de cuenta ' . ($casa['codigo'] ?? ''));
        $datos = [self::cab(['Concepto', 'Período', 'Emisión', 'Vencimiento', 'Cargo', 'Mora', 'Abonado', 'Saldo', 'Estado'])];
        foreach ($cargos as $c) {
            $datos[] = [
                $c['descripcion'], $c['periodo'], $c['fecha_emision'], $c['fecha_vence'],
                ['v' => (float) $c['monto'], 't' => Xlsx::MONEY],
                ['v' => (float) $c['mora'], 't' => Xlsx::MONEY],
                ['v' => (float) $c['pagado'], 't' => Xlsx::MONEY],
                ['v' => Cuota::saldoCargo($c), 't' => Xlsx::MONEY],
                ucfirst((string) $c['estado']),
            ];
        }
        $x->hoja('Estado de cuenta', $datos, [42, 12, 12, 14, 14, 12, 14, 14, 12]);
        return $x->salida();
    }
}
