<?php
declare(strict_types=1);

namespace Fel\Dte;

/**
 * Impuesto aplicado a una linea del DTE.
 */
final class Impuesto
{
    public function __construct(
        public string $nombreCorto,
        public float $montoGravable,
        public float $montoImpuesto,
        public int $codigoUnidadGravable = 1,
        public ?float $cantidadUnidadesGravables = null,
    ) {
    }
}
