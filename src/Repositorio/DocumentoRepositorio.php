<?php
declare(strict_types=1);

namespace Fel\Repositorio;

use Fel\Core\Db;
use Fel\Certificador\Resultado;
use Fel\Dte\Calculator;
use Fel\Dte\Documento;

/**
 * Persistencia de documentos y su detalle.
 */
final class DocumentoRepositorio
{
    /**
     * El identificador de la empresa se fija al construir el repositorio y se
     * aplica a TODAS las consultas. Asi ninguna consulta puede olvidarse del
     * filtro y ver documentos de otro contribuyente.
     */
    public function __construct(private int $empresaId)
    {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('El repositorio de documentos requiere una empresa valida.');
        }
    }

    public const BORRADOR    = 'BORRADOR';
    public const PENDIENTE   = 'PENDIENTE';
    public const CERTIFICADO = 'CERTIFICADO';
    public const RECHAZADO   = 'RECHAZADO';
    public const ANULADO     = 'ANULADO';

    /**
     * Inserta el documento y su detalle. Devuelve el id generado.
     */
    public function crear(
        Documento $documento,
        string $identificador,
        string $xmlEnviado,
        string $estado = self::PENDIENTE,
        string $usuario = '',
        ?int $clienteId = null,
    ): int {
        $pdo   = Db::conexion();
        $ahora = date('Y-m-d H:i:s');

        $pdo->beginTransaction();

        try {
            $sql = 'INSERT INTO fel_documentos (
                        empresa_id, identificador, tipo, estado, moneda, tipo_cambio, fecha_emision,
                        emisor_nit, emisor_nombre, establecimiento,
                        receptor_id, receptor_nombre, receptor_correo, cliente_id,
                        total_gravable, total_descuentos, total_iva, gran_total,
                        xml_enviado, referencia_interna, observaciones,
                        creado_por, creado_en, actualizado_en
                    ) VALUES (
                        :empresa_id, :identificador, :tipo, :estado, :moneda, :tipo_cambio, :fecha_emision,
                        :emisor_nit, :emisor_nombre, :establecimiento,
                        :receptor_id, :receptor_nombre, :receptor_correo, :cliente_id,
                        :total_gravable, :total_descuentos, :total_iva, :gran_total,
                        :xml_enviado, :referencia_interna, :observaciones,
                        :creado_por, :creado_en, :actualizado_en
                    )';

            $impuestos = Calculator::totalImpuestos($documento);

            $sentencia = $pdo->prepare($sql);
            $sentencia->execute([
                'empresa_id'         => $this->empresaId,
                'identificador'      => $identificador,
                'tipo'               => $documento->tipo,
                'estado'             => $estado,
                'moneda'             => $documento->moneda,
                'tipo_cambio'        => $documento->tipoCambio,
                'fecha_emision'      => $documento->fechaEmision,
                'emisor_nit'         => $documento->emisor->nit,
                'emisor_nombre'      => $documento->emisor->nombre,
                'establecimiento'    => $documento->emisor->codigoEstablecimiento,
                'receptor_id'        => $documento->receptor->id,
                'receptor_nombre'    => $documento->receptor->nombre,
                'receptor_correo'    => $documento->receptor->correo,
                'cliente_id'         => $clienteId,
                'total_gravable'     => Calculator::totalGravable($documento),
                'total_descuentos'   => Calculator::totalDescuentos($documento),
                'total_iva'          => $impuestos['IVA'] ?? 0.0,
                'gran_total'         => Calculator::granTotal($documento),
                'xml_enviado'        => $xmlEnviado,
                'referencia_interna' => $documento->referenciaInterna,
                'observaciones'      => $documento->observaciones,
                'creado_por'         => $usuario,
                'creado_en'          => $ahora,
                'actualizado_en'     => $ahora,
            ]);

            $documentoId = (int) $pdo->lastInsertId();

            $sqlItem = 'INSERT INTO fel_documento_items (
                            documento_id, numero_linea, tipo, descripcion, cantidad, unidad_medida,
                            precio_unitario, precio, descuento, total, monto_gravable, monto_impuesto, exento
                        ) VALUES (
                            :documento_id, :numero_linea, :tipo, :descripcion, :cantidad, :unidad_medida,
                            :precio_unitario, :precio, :descuento, :total, :monto_gravable, :monto_impuesto, :exento
                        )';
            $sentenciaItem = $pdo->prepare($sqlItem);

            foreach ($documento->items as $indice => $item) {
                $impuesto = $item->impuestos[0] ?? null;
                $sentenciaItem->execute([
                    'documento_id'    => $documentoId,
                    'numero_linea'    => $indice + 1,
                    'tipo'            => $item->tipo,
                    'descripcion'     => $item->descripcion,
                    'cantidad'        => $item->cantidad,
                    'unidad_medida'   => $item->unidadMedida,
                    'precio_unitario' => $item->precioUnitario,
                    'precio'          => $item->precio,
                    'descuento'       => $item->descuento,
                    'total'           => $item->total,
                    'monto_gravable'  => $impuesto?->montoGravable ?? $item->total,
                    'monto_impuesto'  => $impuesto?->montoImpuesto ?? 0.0,
                    'exento'          => $item->exento ? 1 : 0,
                ]);
            }

            $pdo->commit();

            return $documentoId;
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    public function marcarCertificado(int $documentoId, Resultado $resultado, string $certificador): void
    {
        $sentencia = Db::conexion()->prepare(
            'UPDATE fel_documentos SET
                estado = :estado, uuid = :uuid, serie = :serie, numero = :numero,
                fecha_certificacion = :fecha, certificador = :certificador,
                xml_certificado = :xml, error_mensaje = NULL,
                intentos = intentos + 1, actualizado_en = :ahora
             WHERE id = :id AND empresa_id = :empresa'
        );

        $sentencia->execute([
            'empresa'      => $this->empresaId,
            'estado'       => self::CERTIFICADO,
            'uuid'         => $resultado->uuid,
            'serie'        => $resultado->serie,
            'numero'       => $resultado->numero,
            'fecha'        => $resultado->fechaCertificacion,
            'certificador' => $certificador,
            'xml'          => $resultado->xmlCertificado,
            'ahora'        => date('Y-m-d H:i:s'),
            'id'           => $documentoId,
        ]);
    }

    public function marcarFallido(int $documentoId, string $mensaje, bool $reintentable): void
    {
        $sentencia = Db::conexion()->prepare(
            'UPDATE fel_documentos SET
                estado = :estado, error_mensaje = :mensaje,
                intentos = intentos + 1, actualizado_en = :ahora
             WHERE id = :id AND empresa_id = :empresa'
        );

        $sentencia->execute([
            'empresa' => $this->empresaId,
            'estado'  => $reintentable ? self::PENDIENTE : self::RECHAZADO,
            'mensaje' => $mensaje,
            'ahora'   => date('Y-m-d H:i:s'),
            'id'      => $documentoId,
        ]);
    }

    public function marcarAnulado(int $documentoId): void
    {
        $sentencia = Db::conexion()->prepare(
            'UPDATE fel_documentos SET estado = :estado, actualizado_en = :ahora
             WHERE id = :id AND empresa_id = :empresa'
        );
        $sentencia->execute([
            'empresa' => $this->empresaId,
            'estado' => self::ANULADO,
            'ahora'  => date('Y-m-d H:i:s'),
            'id'     => $documentoId,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function buscar(int $documentoId): ?array
    {
        $sentencia = Db::conexion()->prepare(
            'SELECT * FROM fel_documentos WHERE id = :id AND empresa_id = :empresa'
        );
        $sentencia->execute(['id' => $documentoId, 'empresa' => $this->empresaId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    /** @return array<string,mixed>|null */
    public function buscarPorUuid(string $uuid): ?array
    {
        $sentencia = Db::conexion()->prepare(
            'SELECT * FROM fel_documentos WHERE uuid = :uuid AND empresa_id = :empresa'
        );
        $sentencia->execute(['uuid' => $uuid, 'empresa' => $this->empresaId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    /** @return list<array<string,mixed>> */
    public function items(int $documentoId): array
    {
        $sentencia = Db::conexion()->prepare(
            'SELECT i.* FROM fel_documento_items i
             INNER JOIN fel_documentos d ON d.id = i.documento_id
             WHERE i.documento_id = :id AND d.empresa_id = :empresa
             ORDER BY i.numero_linea'
        );
        $sentencia->execute(['id' => $documentoId, 'empresa' => $this->empresaId]);

        return $sentencia->fetchAll();
    }

    /**
     * @param array<string,string> $filtros  estado, desde, hasta, receptor, tipo
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtros = [], int $limite = 100, int $desplazamiento = 0): array
    {
        $condiciones = ['empresa_id = :empresa'];
        $parametros  = ['empresa' => $this->empresaId];

        if (($filtros['estado'] ?? '') !== '') {
            $condiciones[]        = 'estado = :estado';
            $parametros['estado'] = $filtros['estado'];
        }
        if (($filtros['tipo'] ?? '') !== '') {
            $condiciones[]      = 'tipo = :tipo';
            $parametros['tipo'] = $filtros['tipo'];
        }
        if (($filtros['receptor'] ?? '') !== '') {
            $condiciones[]          = '(receptor_id LIKE :receptor OR receptor_nombre LIKE :receptor)';
            $parametros['receptor'] = '%' . $filtros['receptor'] . '%';
        }
        if (($filtros['desde'] ?? '') !== '') {
            $condiciones[]       = 'fecha_emision >= :desde';
            $parametros['desde'] = $filtros['desde'];
        }
        if (($filtros['hasta'] ?? '') !== '') {
            $condiciones[]       = 'fecha_emision <= :hasta';
            $parametros['hasta'] = $filtros['hasta'] . 'T23:59:59-06:00';
        }

        $sql = 'SELECT * FROM fel_documentos WHERE ' . implode(' AND ', $condiciones);
        $sql .= ' ORDER BY id DESC LIMIT ' . max(1, $limite) . ' OFFSET ' . max(0, $desplazamiento);

        $sentencia = Db::conexion()->prepare($sql);
        $sentencia->execute($parametros);

        return $sentencia->fetchAll();
    }

    /**
     * Documentos que quedaron en contingencia y deben reintentarse.
     *
     * @return list<array<string,mixed>>
     */
    public function pendientes(int $limite = 50, int $maximoIntentos = 10): array
    {
        $sentencia = Db::conexion()->prepare(
            'SELECT * FROM fel_documentos
             WHERE empresa_id = :empresa AND estado = :estado AND intentos < :maximo
             ORDER BY id ASC LIMIT ' . max(1, $limite)
        );
        $sentencia->execute([
            'empresa' => $this->empresaId,
            'estado'  => self::PENDIENTE,
            'maximo'  => $maximoIntentos,
        ]);

        return $sentencia->fetchAll();
    }

    /**
     * Pendientes de TODAS las empresas. Solo para el proceso de contingencia,
     * que corre desde el cron y no pertenece a ninguna empresa en particular.
     *
     * @return list<array<string,mixed>>
     */
    public static function pendientesGlobales(int $limite = 100, int $maximoIntentos = 10): array
    {
        $sentencia = Db::conexion()->prepare(
            'SELECT d.* FROM fel_documentos d
             INNER JOIN fel_empresas e ON e.id = d.empresa_id
             WHERE d.estado = :estado AND d.intentos < :maximo AND e.activa = 1
             ORDER BY d.id ASC LIMIT ' . max(1, $limite)
        );
        $sentencia->execute(['estado' => self::PENDIENTE, 'maximo' => $maximoIntentos]);

        return $sentencia->fetchAll();
    }

    /**
     * Totales por periodo, para conciliar con la declaracion mensual de IVA.
     *
     * @return array<string,mixed>
     */
    public function resumen(string $desde, string $hasta): array
    {
        $sentencia = Db::conexion()->prepare(
            'SELECT COUNT(*) AS documentos,
                    COALESCE(SUM(total_gravable), 0) AS gravable,
                    COALESCE(SUM(total_iva), 0)      AS iva,
                    COALESCE(SUM(gran_total), 0)     AS total
             FROM fel_documentos
             WHERE empresa_id = :empresa AND estado = :estado
               AND fecha_emision >= :desde AND fecha_emision <= :hasta'
        );
        $sentencia->execute([
            'empresa' => $this->empresaId,
            'estado' => self::CERTIFICADO,
            'desde'  => $desde,
            'hasta'  => $hasta . 'T23:59:59-06:00',
        ]);

        $fila = $sentencia->fetch();

        return $fila === false ? ['documentos' => 0, 'gravable' => 0, 'iva' => 0, 'total' => 0] : $fila;
    }
}
