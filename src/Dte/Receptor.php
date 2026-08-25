<?php
declare(strict_types=1);

namespace Fel\Dte;

use Fel\Core\Validator;

/**
 * Receptor del DTE. Si es consumidor final, el ID es "CF" y el nombre
 * puede ser "Consumidor Final".
 */
final class Receptor
{
    public function __construct(
        public string $id,
        public string $nombre,
        public string $correo = '',
        public string $tipoEspecial = '',
        public string $direccion = 'Ciudad',
        public string $codigoPostal = '01001',
        public string $municipio = 'Guatemala',
        public string $departamento = 'Guatemala',
        public string $pais = 'GT',
    ) {
        $this->id = $this->tipoEspecial === '' ? Validator::normalizarNit($id) : trim($id);
    }

    public static function consumidorFinal(string $nombre = 'Consumidor Final', string $correo = ''): self
    {
        return new self(id: 'CF', nombre: $nombre, correo: $correo);
    }

    /** @param array<string,mixed> $datos */
    public static function desdeArray(array $datos): self
    {
        return new self(
            id:           (string) ($datos['id'] ?? $datos['nit'] ?? 'CF'),
            nombre:       (string) ($datos['nombre'] ?? 'Consumidor Final'),
            correo:       (string) ($datos['correo'] ?? ''),
            tipoEspecial: (string) ($datos['tipo_especial'] ?? ''),
            direccion:    (string) ($datos['direccion'] ?? 'Ciudad'),
            codigoPostal: (string) ($datos['codigo_postal'] ?? '01001'),
            municipio:    (string) ($datos['municipio'] ?? 'Guatemala'),
            departamento: (string) ($datos['departamento'] ?? 'Guatemala'),
            pais:         (string) ($datos['pais'] ?? 'GT'),
        );
    }

    public function esConsumidorFinal(): bool
    {
        return strtoupper($this->id) === 'CF';
    }

    /** @return list<string> */
    public function validar(): array
    {
        $errores = [];

        if (!Validator::identificadorReceptorValido($this->id, $this->tipoEspecial)) {
            $errores[] = 'El identificador del receptor no es valido: ' . $this->id
                . ' (use un NIT con digito verificador correcto, CF para consumidor final,'
                . ' o indique tipo_especial CUI / EXT).';
        }
        if (trim($this->nombre) === '') {
            $errores[] = 'El nombre del receptor es obligatorio.';
        }
        if (!Validator::correoValido($this->correo)) {
            $errores[] = 'El correo del receptor no es valido.';
        }

        return $errores;
    }
}
