<?php
declare(strict_types=1);

namespace Fel\Repositorio;

use Fel\Core\Db;
use Fel\Core\Validator;

final class ClienteRepositorio
{
    public function __construct(private int $empresaId)
    {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('El repositorio de clientes requiere una empresa valida.');
        }
    }

    /** @param array<string,mixed> $datos */
    public function guardar(array $datos, ?int $id = null): int
    {
        $parametros = [
            'identificador' => Validator::normalizarNit((string) ($datos['identificador'] ?? 'CF')),
            'tipo_especial' => (string) ($datos['tipo_especial'] ?? ''),
            'nombre'        => (string) ($datos['nombre'] ?? ''),
            'correo'        => (string) ($datos['correo'] ?? ''),
            'telefono'      => (string) ($datos['telefono'] ?? ''),
            'direccion'     => (string) ($datos['direccion'] ?? 'Ciudad'),
            'codigo_postal' => (string) ($datos['codigo_postal'] ?? '01001'),
            'municipio'     => (string) ($datos['municipio'] ?? 'Guatemala'),
            'departamento'  => (string) ($datos['departamento'] ?? 'Guatemala'),
            'pais'          => (string) ($datos['pais'] ?? 'GT'),
        ];

        if ($parametros['tipo_especial'] !== '') {
            // CUI y pasaporte no se normalizan como NIT.
            $parametros['identificador'] = trim((string) ($datos['identificador'] ?? ''));
        }

        if ($id !== null) {
            $parametros['id']      = $id;
            $parametros['empresa'] = $this->empresaId;
            $sentencia = Db::conexion()->prepare(
                'UPDATE fel_clientes SET
                    identificador = :identificador, tipo_especial = :tipo_especial, nombre = :nombre,
                    correo = :correo, telefono = :telefono, direccion = :direccion,
                    codigo_postal = :codigo_postal, municipio = :municipio,
                    departamento = :departamento, pais = :pais
                 WHERE id = :id AND empresa_id = :empresa'
            );
            $sentencia->execute($parametros);

            return $id;
        }

        $parametros['creado_en']  = date('Y-m-d H:i:s');
        $parametros['empresa_id']  = $this->empresaId;
        $sentencia = Db::conexion()->prepare(
            'INSERT INTO fel_clientes
                (empresa_id, identificador, tipo_especial, nombre, correo, telefono, direccion,
                 codigo_postal, municipio, departamento, pais, creado_en)
             VALUES
                (:empresa_id, :identificador, :tipo_especial, :nombre, :correo, :telefono, :direccion,
                 :codigo_postal, :municipio, :departamento, :pais, :creado_en)'
        );
        $sentencia->execute($parametros);

        return (int) Db::conexion()->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function buscar(int $id): ?array
    {
        $sentencia = Db::conexion()->prepare(
            'SELECT * FROM fel_clientes WHERE id = :id AND empresa_id = :empresa'
        );
        $sentencia->execute(['id' => $id, 'empresa' => $this->empresaId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    /** @return array<string,mixed>|null */
    public function buscarPorIdentificador(string $identificador): ?array
    {
        $sentencia = Db::conexion()->prepare(
            'SELECT * FROM fel_clientes
             WHERE empresa_id = :empresa AND identificador = :identificador
             ORDER BY id LIMIT 1'
        );
        $sentencia->execute([
            'empresa'       => $this->empresaId,
            'identificador' => Validator::normalizarNit($identificador),
        ]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    /** @return list<array<string,mixed>> */
    public function listar(string $busqueda = '', int $limite = 100): array
    {
        if ($busqueda === '') {
            $sentencia = Db::conexion()->prepare(
                'SELECT * FROM fel_clientes WHERE empresa_id = :empresa AND activo = 1
                 ORDER BY nombre LIMIT ' . max(1, $limite)
            );
            $sentencia->execute(['empresa' => $this->empresaId]);

            return $sentencia->fetchAll();
        }

        $sentencia = Db::conexion()->prepare(
            'SELECT * FROM fel_clientes
             WHERE empresa_id = :empresa AND activo = 1
               AND (nombre LIKE :b OR identificador LIKE :b)
             ORDER BY nombre LIMIT ' . max(1, $limite)
        );
        $sentencia->execute(['empresa' => $this->empresaId, 'b' => '%' . $busqueda . '%']);

        return $sentencia->fetchAll();
    }

    public function desactivar(int $id): void
    {
        $sentencia = Db::conexion()->prepare(
            'UPDATE fel_clientes SET activo = 0 WHERE id = :id AND empresa_id = :empresa'
        );
        $sentencia->execute(['id' => $id, 'empresa' => $this->empresaId]);
    }
}
