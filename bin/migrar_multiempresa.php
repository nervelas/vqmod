<?php
/**
 * Migracion de la version de un solo emisor a la version multiempresa.
 *
 *   1. Respalde la base de datos.
 *   2. Importe db/migracion-002-multiempresa.sql desde phpMyAdmin.
 *   3. Ejecute este script:  php bin/migrar_multiempresa.php
 *
 * Crea la empresa con los datos de config/config.php y sus credenciales de
 * certificador, de modo que todos los documentos, clientes y productos ya
 * existentes queden asignados a ella.
 *
 * Es idempotente: si la empresa ya existe, no la duplica.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Fel\Core\Config;
use Fel\Core\Db;
use Fel\Repositorio\EmpresaRepositorio;

if (PHP_SAPI !== 'cli') {
    exit('Este script solo se ejecuta desde la linea de comandos.');
}

Config::cargar();
date_default_timezone_set((string) Config::get('zona_horaria', 'America/Guatemala'));

echo "Migracion a multiempresa\n", str_repeat('=', 40), "\n\n";

$emisor = (array) Config::get('emisor', []);

if (($emisor['nit'] ?? '') === '' || ($emisor['nombre'] ?? '') === '') {
    exit("No hay datos de emisor en config/config.php. Nada que migrar.\n");
}

$empresas  = new EmpresaRepositorio();
$existente = $empresas->buscarPorNit(
    (string) $emisor['nit'],
    (string) ($emisor['codigo_establecimiento'] ?? '1')
);

if ($existente !== null) {
    echo "La empresa ya existe (id {$existente->id()}). No se hace nada.\n";
    exit(0);
}

$proveedor    = (string) Config::get('certificador.proveedor', 'simulador');
$credenciales = (array) Config::get('certificador.' . $proveedor, []);

$empresaId = $empresas->guardar([
    'nombre_interno'          => $emisor['nombre_comercial'] ?? $emisor['nombre'],
    'nit'                     => $emisor['nit'],
    'nombre_emisor'           => $emisor['nombre'],
    'nombre_comercial'        => $emisor['nombre_comercial'] ?? $emisor['nombre'],
    'afiliacion_iva'          => $emisor['afiliacion_iva'] ?? 'GEN',
    'codigo_establecimiento'  => $emisor['codigo_establecimiento'] ?? '1',
    'correo'                  => $emisor['correo'] ?? '',
    'telefono'                => $emisor['telefono'] ?? '',
    'direccion'               => $emisor['direccion'] ?? 'Ciudad',
    'codigo_postal'           => $emisor['codigo_postal'] ?? '01001',
    'municipio'               => $emisor['municipio'] ?? 'Guatemala',
    'departamento'            => $emisor['departamento'] ?? 'Guatemala',
    'pais'                    => $emisor['pais'] ?? 'GT',
    'ambiente'                => (string) Config::get('ambiente', 'pruebas'),
    'certificador_proveedor'  => $proveedor,
    'certificador_nombre'     => (string) Config::get('certificador.nombre_visible', ''),
    'certificador_nit'        => (string) Config::get('certificador.nit_visible', ''),
    'limite_consumidor_final' => (float) Config::get('reglas.limite_consumidor_final', 2500),
    'dias_maximos_anulacion'  => (int) Config::get('reglas.dias_maximos_anulacion', 30),
], null, $credenciales);

echo "Empresa creada con id {$empresaId}.\n";

// La migracion SQL dejo los registros con empresa_id = 1. Si la empresa recien
// creada tiene otro id, se reasignan.
if ($empresaId !== 1) {
    foreach (['fel_documentos', 'fel_clientes', 'fel_productos', 'fel_bitacora', 'fel_usuarios'] as $tabla) {
        $sentencia = Db::conexion()->prepare("UPDATE {$tabla} SET empresa_id = :nuevo WHERE empresa_id = 1");
        $sentencia->execute(['nuevo' => $empresaId]);
        echo "  {$tabla}: ", $sentencia->rowCount(), " registros reasignados.\n";
    }
}

$sentencia = Db::conexion()->prepare('SELECT COUNT(*) FROM fel_documentos WHERE empresa_id = :id');
$sentencia->execute(['id' => $empresaId]);

echo "\nDocumentos asignados a la empresa: ", (int) $sentencia->fetchColumn(), "\n";
echo "\nListo. Ingrese como administrador de la plataforma y revise la empresa\n";
echo "en la pantalla Empresas: confirme sus credenciales de certificador.\n";
