<?php
declare(strict_types=1);

namespace Fel\Dte;

use Fel\Core\Money;

/**
 * Linea de detalle del DTE.
 *
 * En Guatemala el precio de venta al publico YA INCLUYE el IVA. Por eso
 * precioUnitario se captura con IVA incluido y el desglose lo hace el
 * calculador (MontoGravable = Total / 1.12, MontoImpuesto = Total - MontoGravable).
 */
final class Item
{
    /** @var list<Impuesto> */
    public array $impuestos = [];

    public float $precio = 0.0;
    public float $total  = 0.0;

    public function __construct(
        public string $descripcion,
        public float $cantidad,
        public float $precioUnitario,
        public string $tipo = 'B',
        public string $unidadMedida = 'UNI',
        public float $descuento = 0.0,
        public bool $exento = false,
        public string $codigoInterno = '',
    ) {
    }

    /** @param array<string,mixed> $datos */
    public static function desdeArray(array $datos): self
    {
        return new self(
            descripcion:    (string) ($datos['descripcion'] ?? ''),
            cantidad:       (float) ($datos['cantidad'] ?? 1),
            precioUnitario: (float) ($datos['precio_unitario'] ?? 0),
            tipo:           strtoupper((string) ($datos['tipo'] ?? 'B')),
            unidadMedida:   (string) ($datos['unidad_medida'] ?? 'UNI'),
            descuento:      (float) ($datos['descuento'] ?? 0),
            exento:         (bool) ($datos['exento'] ?? false),
            codigoInterno:  (string) ($datos['codigo_interno'] ?? ''),
        );
    }

    /** Subtotal de la linea antes de descuento. */
    public function precioBruto(): float
    {
        return Money::redondear($this->cantidad * $this->precioUnitario);
    }

    /** @return list<string> */
    public function validar(int $numeroLinea): array
    {
        $errores = [];
        $prefijo = "Linea {$numeroLinea}: ";

        if (trim($this->descripcion) === '') {
            $errores[] = $prefijo . 'la descripcion es obligatoria.';
        }
        if ($this->cantidad <= 0) {
            $errores[] = $prefijo . 'la cantidad debe ser mayor que cero.';
        }
        if ($this->precioUnitario < 0) {
            $errores[] = $prefijo . 'el precio unitario no puede ser negativo.';
        }
        if ($this->descuento < 0) {
            $errores[] = $prefijo . 'el descuento no puede ser negativo.';
        }
        if ($this->descuento > $this->precioBruto() + 0.0001) {
            $errores[] = $prefijo . 'el descuento no puede superar el precio de la linea.';
        }
        if (!array_key_exists($this->tipo, Catalogos::tiposItem())) {
            $errores[] = $prefijo . 'el tipo debe ser B (bien) o S (servicio).';
        }
        if (!array_key_exists($this->unidadMedida, Catalogos::unidadesMedida())) {
            $errores[] = $prefijo . 'unidad de medida no reconocida: ' . $this->unidadMedida;
        }

        return $errores;
    }
}
