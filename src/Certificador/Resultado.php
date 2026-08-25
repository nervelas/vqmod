<?php
declare(strict_types=1);

namespace Fel\Certificador;

/**
 * Resultado de una operacion contra el certificador.
 */
final class Resultado
{
    /**
     * @param list<string> $mensajes
     */
    public function __construct(
        public bool $exito,
        public string $uuid = '',
        public string $serie = '',
        public string $numero = '',
        public string $fechaCertificacion = '',
        public string $xmlCertificado = '',
        public array $mensajes = [],
        public string $codigoError = '',
        public string $respuestaCruda = '',
        public bool $reintentable = false,
    ) {
    }

    public static function error(string $mensaje, string $codigo = '', string $crudo = '', bool $reintentable = false): self
    {
        return new self(
            exito: false,
            mensajes: [$mensaje],
            codigoError: $codigo,
            respuestaCruda: $crudo,
            reintentable: $reintentable,
        );
    }

    public function mensaje(): string
    {
        return implode(' | ', $this->mensajes);
    }
}
