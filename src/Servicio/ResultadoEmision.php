<?php
declare(strict_types=1);

namespace Fel\Servicio;

use Fel\Certificador\Resultado;

/**
 * Salida de una emision: que paso, con que documento y por que.
 */
final class ResultadoEmision
{
    /** @param list<string> $errores */
    public function __construct(
        public bool $exito,
        public ?int $documentoId = null,
        public ?Resultado $resultado = null,
        public array $errores = [],
        public string $estado = '',
    ) {
    }

    public function uuid(): string
    {
        return $this->resultado?->uuid ?? '';
    }

    public function mensaje(): string
    {
        if ($this->errores !== []) {
            return implode(' ', $this->errores);
        }

        return $this->resultado?->mensaje() ?? '';
    }
}
