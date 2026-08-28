<?php
declare(strict_types=1);

namespace Fel\Repositorio;

use Fel\Core\Db;

final class AnulacionRepositorio
{
    public function __construct(private int $empresaId)
    {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('El repositorio de anulaciones requiere una empresa valida.');
        }
    }

    public function crear(int $documentoId, string $motivo, string $xmlEnviado, string $usuario = ''): int
    {
        $sentencia = Db::conexion()->prepare(
            'INSERT INTO fel_anulaciones (documento_id, motivo, estado, xml_enviado, creado_por, creado_en)
             VALUES (:documento_id, :motivo, :estado, :xml, :usuario, :creado_en)'
        );

        $sentencia->execute([
            'documento_id' => $documentoId,
            'motivo'       => $motivo,
            'estado'       => 'PENDIENTE',
            'xml'          => $xmlEnviado,
            'usuario'      => $usuario,
            'creado_en'    => date('Y-m-d H:i:s'),
        ]);

        return (int) Db::conexion()->lastInsertId();
    }

    public function completar(int $id, string $uuid, string $fecha, string $xmlRespuesta): void
    {
        $sentencia = Db::conexion()->prepare(
            'UPDATE fel_anulaciones SET estado = :estado, uuid_anulacion = :uuid,
                    fecha_anulacion = :fecha, xml_respuesta = :xml, error_mensaje = NULL
             WHERE id = :id'
        );

        $sentencia->execute([
            'estado' => 'ANULADO',
            'uuid'   => $uuid,
            'fecha'  => $fecha,
            'xml'    => $xmlRespuesta,
            'id'     => $id,
        ]);
    }

    public function fallar(int $id, string $mensaje): void
    {
        $sentencia = Db::conexion()->prepare(
            'UPDATE fel_anulaciones SET estado = :estado, error_mensaje = :mensaje WHERE id = :id'
        );
        $sentencia->execute(['estado' => 'RECHAZADO', 'mensaje' => $mensaje, 'id' => $id]);
    }

    /** @return list<array<string,mixed>> */
    public function porDocumento(int $documentoId): array
    {
        $sentencia = Db::conexion()->prepare(
            'SELECT a.* FROM fel_anulaciones a
             INNER JOIN fel_documentos d ON d.id = a.documento_id
             WHERE a.documento_id = :id AND d.empresa_id = :empresa
             ORDER BY a.id DESC'
        );
        $sentencia->execute(['id' => $documentoId, 'empresa' => $this->empresaId]);

        return $sentencia->fetchAll();
    }
}
