<?php
declare(strict_types=1);

namespace Fel\Plataforma;

use Fel\Core\Cifrado;
use Fel\Dte\Emisor;

/**
 * Una empresa emisora dentro del sistema.
 *
 * Cada empresa tiene sus propios datos ante SAT, sus credenciales de
 * certificador (guardadas cifradas) y sus documentos. Los usuarios, clientes,
 * productos y DTE siempre pertenecen a una empresa: es lo que permite atender
 * a varios contribuyentes desde una sola instalacion.
 */
final class Empresa
{
    /** @param array<string,mixed> $fila Registro de fel_empresas */
    public function __construct(private array $fila)
    {
    }

    public function id(): int
    {
        return (int) $this->fila['id'];
    }

    public function nombreInterno(): string
    {
        return (string) $this->fila['nombre_interno'];
    }

    public function nit(): string
    {
        return (string) $this->fila['nit'];
    }

    public function nombreComercial(): string
    {
        return (string) $this->fila['nombre_comercial'];
    }

    public function activa(): bool
    {
        return (bool) ($this->fila['activa'] ?? true);
    }

    public function ambiente(): string
    {
        return (string) ($this->fila['ambiente'] ?? 'pruebas');
    }

    public function proveedorCertificador(): string
    {
        return (string) ($this->fila['certificador_proveedor'] ?? 'simulador');
    }

    public function esSimulador(): bool
    {
        return $this->proveedorCertificador() === 'simulador';
    }

    public function formatoImpresion(): string
    {
        return (string) ($this->fila['formato_impresion'] ?? 'carta');
    }

    public function colorMarca(): string
    {
        $color = (string) ($this->fila['color_marca'] ?? '#0f5f8a');

        // Se valida para que no pueda inyectarse CSS a traves del color.
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) === 1 ? $color : '#0f5f8a';
    }

    public function logo(): string
    {
        $logo = (string) ($this->fila['logo'] ?? '');

        return str_starts_with($logo, 'data:image/') ? $logo : '';
    }

    public function limiteConsumidorFinal(): float
    {
        return (float) ($this->fila['limite_consumidor_final'] ?? 0);
    }

    public function diasMaximosAnulacion(): int
    {
        return (int) ($this->fila['dias_maximos_anulacion'] ?? 0);
    }

    public function certificadorNombre(): string
    {
        return (string) ($this->fila['certificador_nombre'] ?? '');
    }

    public function certificadorNit(): string
    {
        return (string) ($this->fila['certificador_nit'] ?? '');
    }

    /** Datos del emisor listos para armar el DTE. */
    public function emisor(): Emisor
    {
        return new Emisor(
            nit:                   $this->nit(),
            nombre:                (string) $this->fila['nombre_emisor'],
            nombreComercial:       (string) $this->fila['nombre_comercial'],
            afiliacionIva:         (string) ($this->fila['afiliacion_iva'] ?? 'GEN'),
            codigoEstablecimiento: (string) ($this->fila['codigo_establecimiento'] ?? '1'),
            correo:                (string) ($this->fila['correo'] ?? ''),
            direccion:             (string) ($this->fila['direccion'] ?? 'Ciudad'),
            codigoPostal:          (string) ($this->fila['codigo_postal'] ?? '01001'),
            municipio:             (string) ($this->fila['municipio'] ?? 'Guatemala'),
            departamento:          (string) ($this->fila['departamento'] ?? 'Guatemala'),
            pais:                  (string) ($this->fila['pais'] ?? 'GT'),
        );
    }

    /**
     * Credenciales del certificador, descifradas.
     *
     * @return array<string,mixed>
     */
    public function configCertificador(): array
    {
        return Cifrado::descifrarArreglo((string) ($this->fila['certificador_config'] ?? ''));
    }

    /**
     * Fila cruda, sin las credenciales. Sirve para pintar formularios sin
     * arrastrar secretos a la vista.
     *
     * @return array<string,mixed>
     */
    public function datos(): array
    {
        $fila = $this->fila;
        unset($fila['certificador_config']);

        return $fila;
    }

    public function valor(string $clave, mixed $porDefecto = null): mixed
    {
        return $this->fila[$clave] ?? $porDefecto;
    }

    /** @return list<string> Problemas que impiden emitir documentos validos. */
    public function problemas(): array
    {
        $problemas = $this->emisor()->validar();

        if (!$this->activa()) {
            $problemas[] = 'La empresa está desactivada.';
        }

        if ($this->ambiente() === 'produccion' && $this->esSimulador()) {
            $problemas[] = 'La empresa está en producción pero sigue apuntando al certificador simulado.';
        }

        if (!$this->esSimulador() && $this->configCertificador() === []) {
            $problemas[] = 'Faltan las credenciales del certificador.';
        }

        return $problemas;
    }
}
