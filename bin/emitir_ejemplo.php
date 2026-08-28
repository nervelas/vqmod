<?php
/**
 * Emite un documento de ejemplo para comprobar que toda la cadena funciona:
 * validacion -> XML -> firma -> certificacion -> guardado.
 *
 *   php bin/emitir_ejemplo.php [empresa_id]
 *
 * Con el certificador 'simulador' no gasta folios ni toca la red.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Fel\Core\Config;
use Fel\Dte\Documento;
use Fel\Dte\Item;
use Fel\Dte\Receptor;
use Fel\Repositorio\DocumentoRepositorio;
use Fel\Repositorio\EmpresaRepositorio;
use Fel\Servicio\FacturacionService;

if (PHP_SAPI !== 'cli') {
    exit('Este script solo se ejecuta desde la linea de comandos.');
}

Config::cargar();
date_default_timezone_set((string) Config::get('zona_horaria', 'America/Guatemala'));

$empresas = new EmpresaRepositorio();
$listado  = $empresas->listar();

if ($listado === []) {
    exit("No hay empresas registradas. Cree una desde la pantalla Empresas.\n");
}

$empresaId = isset($argv[1]) ? (int) $argv[1] : $listado[0]->id();
$empresa   = $empresas->buscar($empresaId);

if ($empresa === null) {
    echo "No existe la empresa {$empresaId}. Empresas disponibles:\n";
    foreach ($listado as $registro) {
        printf("  %d  %s (NIT %s)\n", $registro->id(), $registro->nombreInterno(), $registro->nit());
    }
    exit(1);
}

printf("Empresa      : %s (NIT %s)\n", $empresa->nombreInterno(), $empresa->nit());
printf("Certificador : %s (%s)\n", $empresa->proveedorCertificador(), $empresa->ambiente());

if (!$empresa->esSimulador()) {
    echo "\nATENCION: va a emitir un documento REAL ante SAT.\n";
    echo "Escriba EMITIR para continuar: ";

    if (trim((string) fgets(STDIN)) !== 'EMITIR') {
        exit("Cancelado.\n");
    }
}

$documento = new Documento(
    tipo: 'FACT',
    emisor: $empresa->emisor(),
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

$resultado = (new FacturacionService($empresa))->emitir($documento, 'cli');

if (!$resultado->exito) {
    echo "\nNo se pudo emitir:\n";
    foreach ($resultado->errores as $error) {
        echo "  - {$error}\n";
    }
    echo "Estado: {$resultado->estado}\n";
    exit(1);
}

$fila = (new DocumentoRepositorio($empresa->id()))->buscar((int) $resultado->documentoId);

echo "\nDocumento emitido correctamente.\n";
printf("  Id interno         : %d\n", (int) $resultado->documentoId);
printf("  Numero autorizacion: %s\n", $resultado->uuid());
printf("  Serie / Numero     : %s / %s\n", $fila['serie'], $fila['numero']);
printf("  Gran total         : Q%s\n", number_format((float) $fila['gran_total'], 2));
printf("  Estado             : %s\n", $fila['estado']);
