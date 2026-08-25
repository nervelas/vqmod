<?php
/**
 * Emite un documento de ejemplo para comprobar que toda la cadena funciona:
 * validacion -> XML -> firma -> certificacion -> guardado.
 *
 *   php bin/emitir_ejemplo.php
 *
 * Con el certificador 'simulador' no gasta folios ni toca la red.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Fel\Core\Config;
use Fel\Dte\Documento;
use Fel\Dte\Emisor;
use Fel\Dte\Item;
use Fel\Dte\Receptor;
use Fel\Repositorio\DocumentoRepositorio;
use Fel\Servicio\FacturacionService;

if (PHP_SAPI !== 'cli') {
    exit('Este script solo se ejecuta desde la linea de comandos.');
}

Config::cargar();
date_default_timezone_set((string) Config::get('zona_horaria', 'America/Guatemala'));

$proveedor = (string) Config::get('certificador.proveedor', 'simulador');

echo "Certificador: {$proveedor}\n";

if ($proveedor !== 'simulador') {
    echo "\nATENCION: va a emitir un documento REAL ante SAT con el certificador '{$proveedor}'.\n";
    echo "Escriba EMITIR para continuar: ";

    if (trim((string) fgets(STDIN)) !== 'EMITIR') {
        exit("Cancelado.\n");
    }
}

$documento = new Documento(
    tipo: 'FACT',
    emisor: Emisor::desdeArray((array) Config::get('emisor', [])),
    receptor: Receptor::consumidorFinal('Consumidor Final'),
);

$documento->agregarItem(new Item(
    descripcion: 'Servicio de ejemplo',
    cantidad: 1,
    precioUnitario: 112.00,
    tipo: 'S',
    unidadMedida: 'SER',
));

$documento->referenciaInterna = 'EJEMPLO-' . date('YmdHis');

$resultado = (new FacturacionService())->emitir($documento, 'cli');

if (!$resultado->exito) {
    echo "\nNo se pudo emitir:\n";
    foreach ($resultado->errores as $error) {
        echo "  - {$error}\n";
    }
    echo "Estado: {$resultado->estado}\n";
    exit(1);
}

$fila = (new DocumentoRepositorio())->buscar((int) $resultado->documentoId);

echo "\nDocumento emitido correctamente.\n";
printf("  Id interno        : %d\n", (int) $resultado->documentoId);
printf("  Numero autorizacion: %s\n", $resultado->uuid());
printf("  Serie / Numero    : %s / %s\n", $fila['serie'], $fila['numero']);
printf("  Gran total        : Q%s\n", number_format((float) $fila['gran_total'], 2));
printf("  Estado            : %s\n", $fila['estado']);
