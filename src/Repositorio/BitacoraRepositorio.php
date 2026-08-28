<?php
declare(strict_types=1);

namespace Fel\Repositorio;

use Fel\Core\Db;

/**
 * Registro de cada intercambio con el certificador.
 * Es el respaldo que se presenta si SAT o el contador piden trazabilidad.
 */
final class BitacoraRepositorio
{
    public function __construct(private int $empresaId)
    {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('La bitacora requiere una empresa valida.');
        }
    }

    public function registrar(
        ?int $documentoId,
        string $operacion,
        bool $exito,
        string $mensaje,
        string $respuesta = '',
    ): void {
        $sentencia = Db::conexion()->prepare(
            'INSERT INTO fel_bitacora (empresa_id, documento_id, operacion, exito, mensaje, respuesta, creado_en)
             VALUES (:empresa_id, :documento_id, :operacion, :exito, :mensaje, :respuesta, :creado_en)'
        );

        $sentencia->execute([
            'empresa_id'   => $this->empresaId,
            'documento_id' => $documentoId,
            'operacion'    => $operacion,
            'exito'        => $exito ? 1 : 0,
            'mensaje'      => mb_substr($mensaje, 0, 4000),
            'respuesta'    => mb_substr($respuesta, 0, 60000),
            'creado_en'    => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function porDocumento(int $documentoId): array
    {
        $sentencia = Db::conexion()->prepare(
            'SELECT * FROM fel_bitacora
             WHERE documento_id = :id AND empresa_id = :empresa
             ORDER BY id DESC'
        );
        $sentencia->execute(['id' => $documentoId, 'empresa' => $this->empresaId]);

        return $sentencia->fetchAll();
    }
}
