<?php
declare(strict_types=1);

namespace Fel\Dte;

use Fel\Core\Validator;

/**
 * Datos del emisor tal como estan registrados en la Agencia Virtual de SAT.
 * Deben coincidir EXACTAMENTE con el registro tributario unificado (RTU),
 * de lo contrario el certificador rechaza el DTE.
 */
final class Emisor
{
    public function __construct(
        public string $nit,
        public string $nombre,
        public string $nombreComercial,
        public string $afiliacionIva = 'GEN',
        public string $codigoEstablecimiento = '1',
        public string $correo = '',
        public string $direccion = 'Ciudad',
        public string $codigoPostal = '01001',
        public string $municipio = 'Guatemala',
        public string $departamento = 'Guatemala',
        public string $pais = 'GT',
    ) {
        $this->nit = Validator::normalizarNit($nit);
    }

    /** @param array<string,mixed> $datos */
    public static function desdeArray(array $datos): self
    {
        return new self(
            nit:                   (string) ($datos['nit'] ?? ''),
            nombre:                (string) ($datos['nombre'] ?? ''),
            nombreComercial:       (string) ($datos['nombre_comercial'] ?? $datos['nombre'] ?? ''),
            afiliacionIva:         (string) ($datos['afiliacion_iva'] ?? 'GEN'),
            codigoEstablecimiento: (string) ($datos['codigo_establecimiento'] ?? '1'),
            correo:                (string) ($datos['correo'] ?? ''),
            direccion:             (string) ($datos['direccion'] ?? 'Ciudad'),
            codigoPostal:          (string) ($datos['codigo_postal'] ?? '01001'),
            municipio:             (string) ($datos['municipio'] ?? 'Guatemala'),
            departamento:          (string) ($datos['departamento'] ?? 'Guatemala'),
            pais:                  (string) ($datos['pais'] ?? 'GT'),
        );
    }

    /** @return list<string> Lista de errores; vacia si el emisor es valido. */
    public function validar(): array
    {
        $errores = [];

        if (!Validator::nitValido($this->nit) || $this->nit === 'CF') {
            $errores[] = 'El NIT del emisor no es valido: ' . $this->nit;
        }
        if (trim($this->nombre) === '') {
            $errores[] = 'El nombre del emisor es obligatorio.';
        }
        if (trim($this->nombreComercial) === '') {
            $errores[] = 'El nombre comercial del emisor es obligatorio.';
        }
        if (!array_key_exists($this->afiliacionIva, Catalogos::afiliacionesIva())) {
            $errores[] = 'Afiliacion IVA no reconocida: ' . $this->afiliacionIva;
        }
        if (!Validator::correoValido($this->correo)) {
            $errores[] = 'El correo del emisor no es valido.';
        }
        if (trim($this->codigoEstablecimiento) === '') {
            $errores[] = 'El codigo de establecimiento es obligatorio (lo asigna SAT al habilitar el establecimiento).';
        }

        return $errores;
    }
}
