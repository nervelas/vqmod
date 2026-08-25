<?php
declare(strict_types=1);

namespace Fel\Dte;

use Fel\Core\Money;

/**
 * Calculo de montos del DTE.
 *
 * Reglas aplicadas:
 *  - En Guatemala el precio de lista incluye IVA, por eso el impuesto se
 *    desglosa hacia adentro:  gravable = total / (1 + tasa).
 *  - GranTotal = suma de los Total de cada linea (nunca se recalcula
 *    desde el gravable, para que no aparezcan diferencias de centavos).
 *  - Los regimenes sin credito fiscal (pequeño contribuyente, agropecuario)
 *    reportan MontoGravable = Total y MontoImpuesto = 0.00.
 */
final class Calculator
{
    /**
     * Recalcula precio, descuento, total e impuestos de cada linea.
     * Es idempotente: puede llamarse varias veces sobre el mismo documento.
     */
    public static function calcular(Documento $documento): void
    {
        $desglosa = $documento->desglosaIva();
        $tasa     = Catalogos::tasaIva();

        foreach ($documento->items as $item) {
            $item->precio    = Money::redondear($item->cantidad * $item->precioUnitario);
            $item->descuento = Money::redondear($item->descuento);
            $item->total     = Money::redondear($item->precio - $item->descuento);

            $item->impuestos = [];

            if ($desglosa && !$item->exento) {
                $gravable = Money::redondear($item->total / (1 + $tasa));
                $impuesto = Money::redondear($item->total - $gravable);
            } else {
                $gravable = $item->total;
                $impuesto = 0.0;
            }

            $item->impuestos[] = new Impuesto(
                nombreCorto:   'IVA',
                montoGravable: $gravable,
                montoImpuesto: $impuesto,
            );
        }
    }

    public static function granTotal(Documento $documento): float
    {
        $total = 0.0;

        foreach ($documento->items as $item) {
            $precio    = Money::redondear($item->cantidad * $item->precioUnitario);
            $total    += Money::redondear($precio - Money::redondear($item->descuento));
        }

        return Money::redondear($total);
    }

    /**
     * Suma de impuestos por nombre corto, en el orden en que aparecen.
     *
     * @return array<string,float>
     */
    public static function totalImpuestos(Documento $documento): array
    {
        $totales = [];

        foreach ($documento->items as $item) {
            foreach ($item->impuestos as $impuesto) {
                $totales[$impuesto->nombreCorto] = Money::redondear(
                    ($totales[$impuesto->nombreCorto] ?? 0.0) + $impuesto->montoImpuesto
                );
            }
        }

        return $totales;
    }

    /** Base gravable total (sin impuestos). */
    public static function totalGravable(Documento $documento): float
    {
        $total = 0.0;

        foreach ($documento->items as $item) {
            foreach ($item->impuestos as $impuesto) {
                $total += $impuesto->montoGravable;
            }
        }

        return Money::redondear($total);
    }

    public static function totalDescuentos(Documento $documento): float
    {
        $total = 0.0;

        foreach ($documento->items as $item) {
            $total += Money::redondear($item->descuento);
        }

        return Money::redondear($total);
    }

    /**
     * Convierte un monto a letras para la representacion grafica.
     * Ej.: 1234.56 -> "UN MIL DOSCIENTOS TREINTA Y CUATRO QUETZALES CON 56/100"
     */
    public static function montoEnLetras(float $monto, string $moneda = 'GTQ'): string
    {
        $entero    = (int) floor(abs($monto));
        $centavos  = (int) round((abs($monto) - $entero) * 100);

        if ($centavos === 100) {
            $entero++;
            $centavos = 0;
        }

        $nombreMoneda = match ($moneda) {
            'USD'   => $entero === 1 ? 'DOLAR' : 'DOLARES',
            'EUR'   => $entero === 1 ? 'EURO' : 'EUROS',
            default => $entero === 1 ? 'QUETZAL' : 'QUETZALES',
        };

        return sprintf(
            '%s %s CON %02d/100',
            strtoupper(self::numeroALetras($entero)),
            $nombreMoneda,
            $centavos
        );
    }

    private static function numeroALetras(int $numero): string
    {
        if ($numero === 0) {
            return 'cero';
        }
        if ($numero < 0) {
            return 'menos ' . self::numeroALetras(-$numero);
        }

        $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
            'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciseis', 'diecisiete',
            'dieciocho', 'diecinueve', 'veinte'];
        $decenas  = ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos',
            'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        if ($numero <= 20) {
            return $unidades[$numero];
        }

        if ($numero < 100) {
            $d = intdiv($numero, 10);
            $u = $numero % 10;
            if ($numero < 30) {
                return 'veinti' . $unidades[$u];
            }

            return $decenas[$d] . ($u > 0 ? ' y ' . $unidades[$u] : '');
        }

        if ($numero === 100) {
            return 'cien';
        }

        if ($numero < 1000) {
            $c = intdiv($numero, 100);
            $r = $numero % 100;

            return $centenas[$c] . ($r > 0 ? ' ' . self::numeroALetras($r) : '');
        }

        if ($numero < 1000000) {
            $miles = intdiv($numero, 1000);
            $resto = $numero % 1000;
            $texto = $miles === 1 ? 'un mil' : self::numeroALetras($miles) . ' mil';

            return $texto . ($resto > 0 ? ' ' . self::numeroALetras($resto) : '');
        }

        $millones = intdiv($numero, 1000000);
        $resto    = $numero % 1000000;
        $texto    = $millones === 1 ? 'un millon' : self::numeroALetras($millones) . ' millones';

        return $texto . ($resto > 0 ? ' ' . self::numeroALetras($resto) : '');
    }
}
