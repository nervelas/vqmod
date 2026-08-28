<?php
declare(strict_types=1);

namespace Fel\Repositorio;

use Fel\Core\Cifrado;
use Fel\Core\Db;
use Fel\Core\Validator;
use Fel\Plataforma\Empresa;

/**
 * Alta y consulta de las empresas emisoras.
 *
 * Las credenciales del certificador nunca se guardan en claro: se cifran
 * antes de tocar la base y solo se descifran al momento de certificar.
 */
final class EmpresaRepositorio
{
    private const CAMPOS = [
        'nombre_interno', 'nit', 'nombre_emisor', 'nombre_comercial', 'afiliacion_iva',
        'codigo_establecimiento', 'correo', 'telefono', 'direccion', 'codigo_postal',
        'municipio', 'departamento', 'pais', 'ambiente', 'certificador_proveedor',
        'certificador_nombre', 'certificador_nit', 'formato_impresion', 'color_marca',
        'logo', 'limite_consumidor_final', 'dias_maximos_anulacion', 'plan', 'notas',
    ];

    /**
     * Crea o actualiza una empresa.
     *
     * @param array<string,mixed> $datos
     * @param array<string,mixed>|null $credenciales Si es null, se conservan las que ya estaban.
     */
    public function guardar(array $datos, ?int $id = null, ?array $credenciales = null): int
    {
        $parametros = $this->normalizar($datos);
        $ahora      = date('Y-m-d H:i:s');

        if ($id !== null) {
            $asignaciones = [];
            foreach (self::CAMPOS as $campo) {
                $asignaciones[] = "{$campo} = :{$campo}";
            }

            if ($credenciales !== null) {
                $asignaciones[]                    = 'certificador_config = :certificador_config';
                $parametros['certificador_config'] = Cifrado::cifrarArreglo($credenciales);
            }

            $parametros['id']             = $id;
            $parametros['actualizado_en'] = $ahora;

            $sentencia = Db::conexion()->prepare(
                'UPDATE fel_empresas SET ' . implode(', ', $asignaciones)
                . ', actualizado_en = :actualizado_en WHERE id = :id'
            );
            $sentencia->execute($parametros);

            return $id;
        }

        $parametros['certificador_config'] = Cifrado::cifrarArreglo($credenciales ?? []);
        $parametros['creado_en']           = $ahora;
        $parametros['actualizado_en']      = $ahora;

        $columnas = array_merge(self::CAMPOS, ['certificador_config', 'creado_en', 'actualizado_en']);
        $marcas   = array_map(static fn (string $c): string => ':' . $c, $columnas);

        $sentencia = Db::conexion()->prepare(
            'INSERT INTO fel_empresas (' . implode(', ', $columnas) . ')
             VALUES (' . implode(', ', $marcas) . ')'
        );
        $sentencia->execute($parametros);

        return (int) Db::conexion()->lastInsertId();
    }

    /**
     * @param array<string,mixed> $datos
     * @return array<string,mixed>
     */
    private function normalizar(array $datos): array
    {
        $formato  = (string) ($datos['formato_impresion'] ?? 'carta');
        $ambiente = (string) ($datos['ambiente'] ?? 'pruebas');

        return [
            'nombre_interno'          => trim((string) ($datos['nombre_interno'] ?? '')),
            'nit'                     => Validator::normalizarNit((string) ($datos['nit'] ?? '')),
            'nombre_emisor'           => trim((string) ($datos['nombre_emisor'] ?? '')),
            'nombre_comercial'        => trim((string) ($datos['nombre_comercial'] ?? '')),
            'afiliacion_iva'          => strtoupper((string) ($datos['afiliacion_iva'] ?? 'GEN')),
            'codigo_establecimiento'  => trim((string) ($datos['codigo_establecimiento'] ?? '1')),
            'correo'                  => trim((string) ($datos['correo'] ?? '')),
            'telefono'                => trim((string) ($datos['telefono'] ?? '')),
            'direccion'               => trim((string) ($datos['direccion'] ?? 'Ciudad')),
            'codigo_postal'           => trim((string) ($datos['codigo_postal'] ?? '01001')),
            'municipio'               => trim((string) ($datos['municipio'] ?? 'Guatemala')),
            'departamento'            => trim((string) ($datos['departamento'] ?? 'Guatemala')),
            'pais'                    => strtoupper((string) ($datos['pais'] ?? 'GT')),
            'ambiente'                => in_array($ambiente, ['pruebas', 'produccion'], true) ? $ambiente : 'pruebas',
            'certificador_proveedor'  => strtolower(trim((string) ($datos['certificador_proveedor'] ?? 'simulador'))),
            'certificador_nombre'     => trim((string) ($datos['certificador_nombre'] ?? '')),
            'certificador_nit'        => trim((string) ($datos['certificador_nit'] ?? '')),
            'formato_impresion'       => in_array($formato, ['carta', 'ticket'], true) ? $formato : 'carta',
            'color_marca'             => trim((string) ($datos['color_marca'] ?? '#0f5f8a')),
            'logo'                    => (string) ($datos['logo'] ?? ''),
            'limite_consumidor_final' => (float) ($datos['limite_consumidor_final'] ?? 2500),
            'dias_maximos_anulacion'  => (int) ($datos['dias_maximos_anulacion'] ?? 30),
            'plan'                    => trim((string) ($datos['plan'] ?? '')),
            'notas'                   => trim((string) ($datos['notas'] ?? '')),
        ];
    }

    public function buscar(int $id): ?Empresa
    {
        $sentencia = Db::conexion()->prepare('SELECT * FROM fel_empresas WHERE id = :id');
        $sentencia->execute(['id' => $id]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : new Empresa($fila);
    }

    public function buscarPorNit(string $nit, string $establecimiento = '1'): ?Empresa
    {
        $sentencia = Db::conexion()->prepare(
            'SELECT * FROM fel_empresas WHERE nit = :nit AND codigo_establecimiento = :est LIMIT 1'
        );
        $sentencia->execute(['nit' => Validator::normalizarNit($nit), 'est' => $establecimiento]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : new Empresa($fila);
    }

    /** @return list<Empresa> */
    public function listar(bool $soloActivas = false): array
    {
        $sql = 'SELECT * FROM fel_empresas' . ($soloActivas ? ' WHERE activa = 1' : '') . ' ORDER BY nombre_interno';
        $sentencia = Db::conexion()->query($sql);

        return array_map(
            static fn (array $fila): Empresa => new Empresa($fila),
            $sentencia === false ? [] : $sentencia->fetchAll()
        );
    }

    public function cambiarEstado(int $id, bool $activa): void
    {
        $sentencia = Db::conexion()->prepare(
            'UPDATE fel_empresas SET activa = :activa, actualizado_en = :ahora WHERE id = :id'
        );
        $sentencia->execute(['activa' => $activa ? 1 : 0, 'ahora' => date('Y-m-d H:i:s'), 'id' => $id]);
    }

    /**
     * Resumen de uso por empresa, para facturar el servicio y ver quien lo usa.
     *
     * @return array{documentos:int,certificados:int,pendientes:int,total:float,ultimo:string}
     */
    public function uso(int $empresaId, string $desde, string $hasta): array
    {
        $sentencia = Db::conexion()->prepare(
            'SELECT COUNT(*) AS documentos,
                    SUM(CASE WHEN estado = :cert THEN 1 ELSE 0 END) AS certificados,
                    SUM(CASE WHEN estado = :pend THEN 1 ELSE 0 END) AS pendientes,
                    COALESCE(SUM(CASE WHEN estado = :cert2 THEN gran_total ELSE 0 END), 0) AS total,
                    COALESCE(MAX(fecha_emision), :vacio) AS ultimo
             FROM fel_documentos
             WHERE empresa_id = :empresa AND fecha_emision >= :desde AND fecha_emision <= :hasta'
        );

        $sentencia->execute([
            'cert'    => DocumentoRepositorio::CERTIFICADO,
            'cert2'   => DocumentoRepositorio::CERTIFICADO,
            'pend'    => DocumentoRepositorio::PENDIENTE,
            'vacio'   => '',
            'empresa' => $empresaId,
            'desde'   => $desde,
            'hasta'   => $hasta . 'T23:59:59-06:00',
        ]);

        $fila = $sentencia->fetch();

        return [
            'documentos'   => (int) ($fila['documentos'] ?? 0),
            'certificados' => (int) ($fila['certificados'] ?? 0),
            'pendientes'   => (int) ($fila['pendientes'] ?? 0),
            'total'        => (float) ($fila['total'] ?? 0),
            'ultimo'       => (string) ($fila['ultimo'] ?? ''),
        ];
    }
}
