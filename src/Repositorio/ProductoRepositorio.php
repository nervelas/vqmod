<?php
declare(strict_types=1);

namespace Fel\Repositorio;

use Fel\Core\Db;

final class ProductoRepositorio
{
    public function __construct(private int $empresaId)
    {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('El repositorio de productos requiere una empresa valida.');
        }
    }

    /** @param array<string,mixed> $datos */
    public function guardar(array $datos, ?int $id = null): int
    {
        $parametros = [
            'codigo'          => (string) ($datos['codigo'] ?? ''),
            'descripcion'     => (string) ($datos['descripcion'] ?? ''),
            'tipo'            => strtoupper((string) ($datos['tipo'] ?? 'B')),
            'unidad_medida'   => (string) ($datos['unidad_medida'] ?? 'UNI'),
            'precio_unitario' => (float) ($datos['precio_unitario'] ?? 0),
            'exento'          => !empty($datos['exento']) ? 1 : 0,
        ];

        if ($id !== null) {
            $parametros['id']      = $id;
            $parametros['empresa'] = $this->empresaId;
            $sentencia = Db::conexion()->prepare(
                'UPDATE fel_productos SET
                    codigo = :codigo, descripcion = :descripcion, tipo = :tipo,
                    unidad_medida = :unidad_medida, precio_unitario = :precio_unitario, exento = :exento
                 WHERE id = :id AND empresa_id = :empresa'
            );
            $sentencia->execute($parametros);

            return $id;
        }

        $parametros['creado_en']  = date('Y-m-d H:i:s');
        $parametros['empresa_id']  = $this->empresaId;
        $sentencia = Db::conexion()->prepare(
            'INSERT INTO fel_productos
                (empresa_id, codigo, descripcion, tipo, unidad_medida, precio_unitario, exento, creado_en)
             VALUES
                (:empresa_id, :codigo, :descripcion, :tipo, :unidad_medida, :precio_unitario, :exento, :creado_en)'
        );
        $sentencia->execute($parametros);

        return (int) Db::conexion()->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function buscar(int $id): ?array
    {
        $sentencia = Db::conexion()->prepare(
            'SELECT * FROM fel_productos WHERE id = :id AND empresa_id = :empresa'
        );
        $sentencia->execute(['id' => $id, 'empresa' => $this->empresaId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    /** @return list<array<string,mixed>> */
    public function listar(string $busqueda = '', int $limite = 200): array
    {
        if ($busqueda === '') {
            $sentencia = Db::conexion()->prepare(
                'SELECT * FROM fel_productos WHERE empresa_id = :empresa AND activo = 1
                 ORDER BY descripcion LIMIT ' . max(1, $limite)
            );
            $sentencia->execute(['empresa' => $this->empresaId]);

            return $sentencia->fetchAll();
        }

        $sentencia = Db::conexion()->prepare(
            'SELECT * FROM fel_productos
             WHERE empresa_id = :empresa AND activo = 1
               AND (descripcion LIKE :b OR codigo LIKE :b)
             ORDER BY descripcion LIMIT ' . max(1, $limite)
        );
        $sentencia->execute(['empresa' => $this->empresaId, 'b' => '%' . $busqueda . '%']);

        return $sentencia->fetchAll();
    }

    public function desactivar(int $id): void
    {
        $sentencia = Db::conexion()->prepare(
            'UPDATE fel_productos SET activo = 0 WHERE id = :id AND empresa_id = :empresa'
        );
        $sentencia->execute(['id' => $id, 'empresa' => $this->empresaId]);
    }
}
