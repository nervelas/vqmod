<?php
/**
 * Pruebas automatizadas sin dependencias externas.
 * Ejecucion:  php tests/run.php
 *
 * Usan SQLite y el certificador SIMULADO: no tocan la red ni gastan folios.
 */
declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';

use Fel\Certificador\Fabrica;
use Fel\Certificador\GenericoRestCertificador;
use Fel\Certificador\RespuestaJson;
use Fel\Certificador\SimuladorCertificador;
use Fel\Core\Config;
use Fel\Core\Db;
use Fel\Core\Esquema;
use Fel\Core\Money;
use Fel\Core\Validator;
use Fel\Dte\AnulacionXmlBuilder;
use Fel\Dte\Calculator;
use Fel\Dte\Catalogos;
use Fel\Dte\Documento;
use Fel\Dte\Emisor;
use Fel\Dte\Frase;
use Fel\Dte\Item;
use Fel\Dte\Receptor;
use Fel\Dte\Referencia;
use Fel\Dte\XmlBuilder;
use Fel\Core\Cifrado;
use Fel\Plataforma\Empresa;
use Fel\Presentacion\CodigoQr;
use Fel\Presentacion\RepresentacionGrafica;
use Fel\Repositorio\ClienteRepositorio;
use Fel\Repositorio\DocumentoRepositorio;
use Fel\Repositorio\EmpresaRepositorio;
use Fel\Repositorio\ProductoRepositorio;
use Fel\Repositorio\UsuarioRepositorio;
use Fel\Servicio\AnulacionService;
use Fel\Servicio\ContingenciaService;
use Fel\Servicio\FacturacionService;

// ---------------------------------------------------------------- utilidades
$pruebas = 0;
$fallos  = [];

function afirmar(string $descripcion, bool $condicion, string $detalle = ''): void
{
    global $pruebas, $fallos;
    $pruebas++;

    if ($condicion) {
        echo "  \033[32m✓\033[0m {$descripcion}\n";

        return;
    }

    $fallos[] = $descripcion . ($detalle === '' ? '' : ' -> ' . $detalle);
    echo "  \033[31m✗\033[0m {$descripcion}" . ($detalle === '' ? '' : "\n      {$detalle}") . "\n";
}

function iguales(string $descripcion, mixed $esperado, mixed $obtenido): void
{
    afirmar(
        $descripcion,
        $esperado === $obtenido,
        sprintf('esperado %s, obtenido %s', var_export($esperado, true), var_export($obtenido, true))
    );
}

function grupo(string $titulo): void
{
    echo "\n\033[1m{$titulo}\033[0m\n";
}

// ---------------------------------------------------------------- preparacion
$archivoDb = sys_get_temp_dir() . '/fel-pruebas-' . getmypid() . '.sqlite';
@unlink($archivoDb);

Config::establecer([
    'zona_horaria' => 'America/Guatemala',
    'db'           => ['driver' => 'sqlite', 'archivo' => $archivoDb],
    'certificador' => ['proveedor' => 'simulador'],
    'reglas'       => ['limite_consumidor_final' => 2500.0, 'maximo_intentos' => 5],
    'rutas'        => [
        'almacen' => sys_get_temp_dir() . '/fel-pruebas',
        'xml'     => sys_get_temp_dir() . '/fel-pruebas/xml',
        'logs'    => sys_get_temp_dir() . '/fel-pruebas/logs',
    ],
    'xml'          => ['formato_legible' => false],
    'emisor'       => ['nombre_comercial' => 'PRUEBAS'],
    'app'          => ['clave_aplicacion' => str_repeat('c1a2v3e4', 8)],
]);

$sql = (string) file_get_contents(__DIR__ . '/../db/schema.sqlite.sql');
foreach (array_filter(array_map('trim', explode(';', $sql))) as $sentencia) {
    Db::conexion()->exec($sentencia);
}

// Empresa emisora sobre la que corren las pruebas
$empresas  = new EmpresaRepositorio();
$empresaId = $empresas->guardar([
    'nombre_interno'   => 'Empresa de pruebas',
    'nit'              => '12345679',
    'nombre_emisor'    => 'MI EMPRESA, SOCIEDAD ANONIMA',
    'nombre_comercial' => 'MI EMPRESA',
    'afiliacion_iva'   => 'GEN',
    'correo'           => 'facturacion@miempresa.gt',
    'certificador_proveedor'  => 'simulador',
    'certificador_nombre'     => 'CERTIFICADOR SIMULADO',
    'certificador_nit'        => '12345679',
    'limite_consumidor_final' => 2500.00,
    'dias_maximos_anulacion'  => 30,
]);
$empresa = $empresas->buscar($empresaId);

function emisorPrueba(string $afiliacion = 'GEN'): Emisor
{
    return new Emisor(
        nit: '12345679',
        nombre: 'MI EMPRESA, SOCIEDAD ANONIMA',
        nombreComercial: 'MI EMPRESA',
        afiliacionIva: $afiliacion,
        correo: 'facturacion@miempresa.gt',
    );
}

// ---------------------------------------------------------------- validadores
grupo('Validaciones de Guatemala');

afirmar('NIT valido con digito verificador correcto', Validator::nitValido('12345679'));
afirmar('NIT valido escrito con guion', Validator::nitValido('1234567-9'));
afirmar('NIT invalido con digito verificador incorrecto', !Validator::nitValido('12345678'));
afirmar('NIT con K final', Validator::nitValido('93174' . (static function (): string {
    $n = '93174';
    $l = strlen($n);
    $s = 0;
    for ($i = 0; $i < $l; $i++) {
        $s += ((int) $n[$i]) * ($l + 1 - $i);
    }
    $m = (11 - ($s % 11)) % 11;

    return $m === 10 ? 'K' : (string) $m;
})()));
afirmar('CF siempre es valido', Validator::nitValido('CF'));
afirmar('CUI invalido de 12 digitos', !Validator::cuiValido('199375178010'));
afirmar('Correo vacio se acepta (campo opcional)', Validator::correoValido(''));
afirmar('Correo invalido se rechaza', !Validator::correoValido('no-es-correo'));
iguales('Normalizacion de NIT', '12345679', Validator::normalizarNit('1234567-9'));
afirmar('Fecha en formato SAT', (bool) preg_match(
    '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}-06:00$/',
    Validator::fechaHoraSat()
));

// ---------------------------------------------------------------- calculo
grupo('Calculo de montos e IVA');

$documento = new Documento('FACT', emisorPrueba(), Receptor::consumidorFinal());
$documento->agregarItem(new Item('Servicio', 1, 1000.00, 'S', 'SER'));
$documento->agregarItem(new Item('Cable', 3, 50.00, 'B', 'MT', 10.00));
$documento->completarFrases();
Calculator::calcular($documento);

iguales('Precio de la linea 2 (3 x 50)', 150.00, $documento->items[1]->precio);
iguales('Total de la linea 2 con descuento', 140.00, $documento->items[1]->total);
iguales('Gravable linea 1 (1000 / 1.12)', 892.86, $documento->items[0]->impuestos[0]->montoGravable);
iguales('IVA linea 1', 107.14, $documento->items[0]->impuestos[0]->montoImpuesto);
iguales('Gran total', 1140.00, Calculator::granTotal($documento));
iguales('IVA total', 122.14, Calculator::totalImpuestos($documento)['IVA']);
afirmar(
    'Gravable + IVA = gran total',
    abs((Calculator::totalGravable($documento) + Calculator::totalImpuestos($documento)['IVA'])
        - Calculator::granTotal($documento)) < 0.005
);

$pequeno = new Documento('FPEQ', emisorPrueba('PEQ'), Receptor::consumidorFinal());
$pequeno->agregarItem(new Item('Venta', 1, 500.00));
$pequeno->completarFrases();
Calculator::calcular($pequeno);
iguales('Pequeño contribuyente no desglosa IVA', 0.0, $pequeno->items[0]->impuestos[0]->montoImpuesto);
iguales('Pequeño contribuyente: gravable = total', 500.00, $pequeno->items[0]->impuestos[0]->montoGravable);
iguales('Frase de pequeño contribuyente', 4, $pequeno->frases[0]->tipo);

iguales('Formato monetario', '1140.00', Money::formato(1140.0));
iguales('Formato de cantidad sin ceros sobrantes', '2.5', Money::cantidad(2.5));
iguales('Monto en letras', 'UN MIL CIENTO CUARENTA QUETZALES CON 00/100', Calculator::montoEnLetras(1140.00));

// ---------------------------------------------------------------- XML
grupo('Generacion del XML del DTE');

$xml = (new XmlBuilder())->construir($documento);
$dom = new DOMDocument();
afirmar('El XML generado es bien formado', @$dom->loadXML($xml));

$xpath = new DOMXPath($dom);
$xpath->registerNamespace('dte', XmlBuilder::NS_DTE);

iguales('Namespace y version del documento', '0.1', $dom->documentElement?->getAttribute('Version'));
iguales('Tipo de DTE en DatosGenerales', 'FACT', (string) $xpath->evaluate('string(//dte:DatosGenerales/@Tipo)'));
iguales('NIT del emisor', '12345679', (string) $xpath->evaluate('string(//dte:Emisor/@NITEmisor)'));
iguales('Receptor consumidor final', 'CF', (string) $xpath->evaluate('string(//dte:Receptor/@IDReceptor)'));
iguales('Cantidad de items', 2.0, $xpath->evaluate('count(//dte:Item)'));
iguales('GranTotal en el XML', '1140.00', (string) $xpath->evaluate('string(//dte:GranTotal)'));
iguales('TotalMontoImpuesto en el XML', '122.14', (string) $xpath->evaluate('string(//dte:TotalImpuesto/@TotalMontoImpuesto)'));
iguales('Numero de linea del segundo item', '2', (string) $xpath->evaluate('string(//dte:Item[2]/@NumeroLinea)'));
afirmar('Existe al menos una frase', $xpath->evaluate('count(//dte:Frase)') >= 1);

$conAdenda = new Documento('FACT', emisorPrueba(), Receptor::consumidorFinal());
$conAdenda->agregarItem(new Item('X', 1, 100.00));
$conAdenda->completarFrases();
$conAdenda->adenda = ['NumeroPedido' => 'PED-1'];
afirmar(
    'La adenda no usa el namespace dte',
    str_contains((new XmlBuilder())->construir($conAdenda), '<NumeroPedido>PED-1</NumeroPedido>')
);

$nota = new Documento('NCRE', emisorPrueba(), new Receptor('80000002', 'CLIENTE, S.A.'));
$nota->agregarItem(new Item('Devolucion', 1, 100.00));
$nota->completarFrases();
afirmar('Nota de credito sin referencia es invalida', $nota->validar() !== []);

$nota->referencia = new Referencia(
    numeroAutorizacionDocumentoOrigen: 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
    fechaEmisionDocumentoOrigen: '2026-08-01',
    motivoAjuste: 'Devolucion de mercaderia',
    numeroDocumentoOrigen: '1',
    serieDocumentoOrigen: 'ABC12345',
);
iguales('Nota de credito con referencia es valida', [], $nota->validar());
$xmlNota = (new XmlBuilder())->construir($nota);
afirmar('El XML de la nota incluye el complemento de referencia', str_contains($xmlNota, 'ReferenciasNota'));
afirmar('El complemento apunta al documento origen', str_contains($xmlNota, 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE'));

$cambiaria = new Documento('FCAM', emisorPrueba(), new Receptor('80000002', 'CLIENTE, S.A.'));
$cambiaria->agregarItem(new Item('Mercaderia', 1, 1120.00));
$cambiaria->completarFrases();
$cambiaria->abonos = [['numero' => 1, 'fecha' => '2026-09-30', 'monto' => 1120.00]];
afirmar(
    'Factura cambiaria incluye el complemento de abonos',
    str_contains((new XmlBuilder())->construir($cambiaria), 'AbonosFacturaCambiaria')
);

// ---------------------------------------------------------------- validaciones de negocio
grupo('Reglas antes de enviar a SAT');

$invalido = new Documento('FACT', emisorPrueba(), new Receptor('12345678', 'NIT MALO'));
$invalido->agregarItem(new Item('X', 1, 10.00));
$invalido->completarFrases();
afirmar('Se detecta un NIT de receptor invalido', $invalido->validar() !== []);

$sinItems = new Documento('FACT', emisorPrueba(), Receptor::consumidorFinal());
$sinItems->completarFrases();
afirmar('Se rechaza un documento sin lineas', $sinItems->validar() !== []);

$grande = new Documento('FACT', emisorPrueba(), Receptor::consumidorFinal());
$grande->agregarItem(new Item('Equipo', 1, 5000.00));
$grande->completarFrases();
afirmar(
    'Se advierte al superar el limite de consumidor final',
    (bool) array_filter($grande->validar(), static fn (string $e): bool => str_contains($e, 'consumidor final'))
);

$tipoMalo = new Documento('XXXX', emisorPrueba(), Receptor::consumidorFinal());
$tipoMalo->agregarItem(new Item('X', 1, 10.00));
$tipoMalo->completarFrases();
afirmar('Se rechaza un tipo de DTE inexistente', $tipoMalo->validar() !== []);

// ---------------------------------------------------------------- emision completa
grupo('Emision completa contra el certificador simulado');

$servicio = new FacturacionService($empresa, new SimuladorCertificador());

$factura = new Documento('FACT', emisorPrueba(), new Receptor('80000002', 'CLIENTE DE PRUEBA, S.A.', 'cliente@ejemplo.gt'));
$factura->agregarItem(new Item('Servicio de soporte mensual', 1, 1000.00, 'S', 'SER'));
$factura->agregarItem(new Item('Cable UTP cat6', 3, 50.00, 'B', 'MT', 10.00));
$factura->referenciaInterna = 'ORD-1001';

$emision = $servicio->emitir($factura, 'pruebas');

afirmar('La emision fue exitosa', $emision->exito, $emision->mensaje());
afirmar('Se obtuvo numero de autorizacion (UUID)', $emision->uuid() !== '');
iguales('Estado final del documento', 'CERTIFICADO', $emision->estado);

$repo = new DocumentoRepositorio($empresa->id());
$fila = $repo->buscar((int) $emision->documentoId);

afirmar('El documento quedo guardado', $fila !== null);
iguales('Gran total persistido', '1140.00', number_format((float) $fila['gran_total'], 2, '.', ''));
iguales('IVA persistido', '122.14', number_format((float) $fila['total_iva'], 2, '.', ''));
iguales('Se guardaron las dos lineas', 2, count($repo->items((int) $emision->documentoId)));
afirmar('Se guardo el XML certificado', str_contains((string) $fila['xml_certificado'], 'Certificacion'));
afirmar('El XML certificado trae el numero de autorizacion', str_contains((string) $fila['xml_certificado'], $emision->uuid()));

$resumen = $repo->resumen(date('Y-m-01'), date('Y-m-d'));
afirmar('El resumen del mes cuenta el documento', (int) $resumen['documentos'] >= 1);

// ---------------------------------------------------------------- representacion grafica
grupo('Representacion grafica');

$html = (new RepresentacionGrafica())->html(
    $empresa,
    $fila,
    $repo->items((int) $emision->documentoId),
    ['Sujeto a pagos trimestrales']
);
afirmar('Incluye el numero de autorizacion', str_contains($html, $emision->uuid()));
afirmar('Incluye el NIT del emisor', str_contains($html, '12345679'));
afirmar('Incluye el nombre del receptor', str_contains($html, 'CLIENTE DE PRUEBA'));
afirmar('Incluye el total', str_contains($html, '1,140.00'));
afirmar('Incluye el total en letras', str_contains($html, 'QUETZALES CON'));
afirmar('Incluye el codigo QR', str_contains($html, '<svg') && str_contains($html, 'Código QR'));
afirmar(
    'El contenido del QR apunta al verificador de SAT con el UUID',
    (static function () use ($fila, $emision): bool {
        $contenido = (new RepresentacionGrafica())->contenidoQr($fila);

        return str_contains($contenido, 'verificacionDte') && str_contains($contenido, $emision->uuid());
    })()
);

$ticket = (new RepresentacionGrafica())->html(
    $empresa,
    $fila,
    $repo->items((int) $emision->documentoId),
    ['Sujeto a pagos trimestrales'],
    'ticket'
);
afirmar('El formato ticket mide 80 mm', str_contains($ticket, 'size:80mm auto'));
afirmar('El ticket trae el numero de autorizacion', str_contains($ticket, $emision->uuid()));
afirmar('El ticket trae los datos del certificador', str_contains($ticket, 'Datos Del Certificador'));
afirmar('El ticket trae el QR', str_contains($ticket, '<svg'));
afirmar('El ticket trae el detalle', str_contains($ticket, 'Servicio de soporte mensual'));

// ---------------------------------------------------------------- anulacion
grupo('Anulacion');

$anulacion = new AnulacionService($empresa, new SimuladorCertificador());
$resultadoAnulacion = $anulacion->anular((int) $emision->documentoId, 'Error en el detalle', 'pruebas');

afirmar('La anulacion fue exitosa', $resultadoAnulacion['exito'], $resultadoAnulacion['mensaje']);
iguales('El documento quedo ANULADO', 'ANULADO', (string) $repo->buscar((int) $emision->documentoId)['estado']);

$reAnular = $anulacion->anular((int) $emision->documentoId, 'Otra vez');
afirmar('No se puede anular dos veces', !$reAnular['exito']);

$xmlAnulacion = (new AnulacionXmlBuilder())->construir(
    'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
    '12345679',
    'CF',
    '2026-08-01T10:00:00-06:00',
    'Prueba'
);
$domAnulacion = new DOMDocument();
afirmar('El XML de anulacion es bien formado', @$domAnulacion->loadXML($xmlAnulacion));
afirmar('Usa el namespace de anulacion', str_contains($xmlAnulacion, AnulacionXmlBuilder::NS_DTE));
afirmar('Incluye el documento a anular', str_contains($xmlAnulacion, 'NumeroDocumentoAAnular'));

// ---------------------------------------------------------------- contingencia
grupo('Contingencia y reintentos');

/** Certificador que siempre falla por red, para simular una caida. */
final class CertificadorCaido implements \Fel\Certificador\CertificadorInterface
{
    public function nombre(): string
    {
        return 'caido';
    }

    public function firmar(string $xml, bool $esAnulacion = false): string
    {
        return $xml;
    }

    public function certificar(string $xmlFirmado, string $identificadorInterno): \Fel\Certificador\Resultado
    {
        return \Fel\Certificador\Resultado::error('Sin conexion', 'SIN_CONEXION', '', reintentable: true);
    }

    public function anular(string $xmlAnulacionFirmado, string $identificadorInterno): \Fel\Certificador\Resultado
    {
        return \Fel\Certificador\Resultado::error('Sin conexion', 'SIN_CONEXION', '', reintentable: true);
    }
}

$offline = new FacturacionService($empresa, new CertificadorCaido());
$enContingencia = new Documento('FACT', emisorPrueba(), Receptor::consumidorFinal());
$enContingencia->agregarItem(new Item('Venta mostrador', 2, 60.00));
$resultadoOffline = $offline->emitir($enContingencia, 'pruebas');

afirmar('Con el certificador caido la emision no tiene exito', !$resultadoOffline->exito);
iguales('El documento queda PENDIENTE, no rechazado', 'PENDIENTE', $resultadoOffline->estado);
afirmar('El documento se guardo aunque no se certifico', $resultadoOffline->documentoId !== null);

$pendientesAntes = count($repo->pendientes());
afirmar('Hay documentos en contingencia', $pendientesAntes >= 1);

$reanudado = new ContingenciaService();
$procesado = $reanudado->procesarPendientes();

iguales('Se certificaron los pendientes al volver el servicio', $pendientesAntes, $procesado['certificados']);
iguales('Ya no quedan pendientes', 0, count($repo->pendientes()));

$documentoRecuperado = $repo->buscar((int) $resultadoOffline->documentoId);
iguales('El documento recuperado quedo CERTIFICADO', 'CERTIFICADO', (string) $documentoRecuperado['estado']);
afirmar('Conserva el mismo identificador interno (sin duplicar folio)', $documentoRecuperado['identificador'] !== '');

/** Certificador que rechaza el contenido: NO debe reintentarse. */
final class CertificadorQueRechaza implements \Fel\Certificador\CertificadorInterface
{
    public function nombre(): string
    {
        return 'rechaza';
    }

    public function firmar(string $xml, bool $esAnulacion = false): string
    {
        return $xml;
    }

    public function certificar(string $xmlFirmado, string $identificadorInterno): \Fel\Certificador\Resultado
    {
        return \Fel\Certificador\Resultado::error('El NIT del receptor no existe en SAT', 'VAL-001');
    }

    public function anular(string $xmlAnulacionFirmado, string $identificadorInterno): \Fel\Certificador\Resultado
    {
        return \Fel\Certificador\Resultado::error('No aplica', 'VAL-001');
    }
}

$rechazo = (new FacturacionService($empresa, new CertificadorQueRechaza()))->emitir(
    (static function (): Documento {
        $d = new Documento('FACT', emisorPrueba(), Receptor::consumidorFinal());
        $d->agregarItem(new Item('Producto', 1, 25.00));

        return $d;
    })()
);
iguales('Un rechazo de contenido queda RECHAZADO (no se reintenta)', 'RECHAZADO', $rechazo->estado);
afirmar('El mensaje del certificador se conserva', str_contains($rechazo->mensaje(), 'NIT del receptor'));

// ---------------------------------------------------------------- adaptadores
grupo('Adaptadores de certificador');

iguales('La fabrica devuelve el simulador por omision', 'simulador', Fabrica::crear()->nombre());

$respuesta = RespuestaJson::interpretar(
    [
        'resultado'       => true,
        'uuid'            => '11111111-2222-3333-4444-555555555555',
        'serie'           => 'A1B2C3D4',
        'numero'          => 77,
        'fecha'           => '2026-08-25T10:00:00-06:00',
        'xml_certificado' => base64_encode('<dte:GTDocumento/>'),
        'descripcion'     => 'Certificado correctamente',
    ],
    '{}',
    200
);
afirmar('Se interpreta una respuesta exitosa', $respuesta->exito);
iguales('Se lee el UUID', '11111111-2222-3333-4444-555555555555', $respuesta->uuid);
iguales('Se lee el numero', '77', $respuesta->numero);
afirmar('Se decodifica el XML en base64', str_contains($respuesta->xmlCertificado, 'GTDocumento'));

$rechazoJson = RespuestaJson::interpretar(
    ['resultado' => false, 'descripcion_errores' => [['mensaje_error' => 'Frase invalida']]],
    '{}',
    200
);
afirmar('Se interpreta un rechazo', !$rechazoJson->exito);
afirmar('Se extraen los mensajes anidados', str_contains($rechazoJson->mensaje(), 'Frase invalida'));

$generico = new GenericoRestCertificador(['firma' => ['habilitada' => false]], 'prueba');
iguales('El adaptador generico puede omitir la firma', '<xml/>', $generico->firmar('<xml/>'));

// ---------------------------------------------------------------- catalogos
grupo('Catalogos');

afirmar('FACT existe en el catalogo', Catalogos::tipoDteValido('FACT'));
afirmar('FPEQ no desglosa IVA', !Catalogos::tipoDteDesglosaIva('FPEQ'));
afirmar('NCRE es nota (requiere referencia)', Catalogos::tipoDteEsNota('NCRE'));
iguales('Tasa de IVA', 0.12, Catalogos::tasaIva());
iguales('Departamentos de Guatemala', 22, count(Catalogos::departamentos()));
iguales('Frase de agente de retencion de IVA', 2, Frase::porClave('IVA_AGENTE_RETENCION')->tipo);

// ---------------------------------------------------------------- repositorios
grupo('Clientes y productos');

$clientes  = new ClienteRepositorio($empresa->id());
$clienteId = $clientes->guardar([
    'identificador' => '80000002',
    'nombre'        => 'CLIENTE DE PRUEBA, S.A.',
    'correo'        => 'cliente@ejemplo.gt',
]);
afirmar('Se guarda un cliente', $clienteId > 0);
iguales('Se recupera por identificador', 'CLIENTE DE PRUEBA, S.A.', (string) $clientes->buscarPorIdentificador('80000002')['nombre']);
iguales('Busqueda por nombre', 1, count($clientes->listar('PRUEBA')));

$productos  = new ProductoRepositorio($empresa->id());
$productoId = $productos->guardar([
    'codigo'          => 'SOP-01',
    'descripcion'     => 'Soporte tecnico mensual',
    'tipo'            => 'S',
    'unidad_medida'   => 'SER',
    'precio_unitario' => 1000.00,
]);
afirmar('Se guarda un producto', $productoId > 0);
iguales('Se recupera el producto', 'Soporte tecnico mensual', (string) $productos->buscar($productoId)['descripcion']);
$productos->desactivar($productoId);
iguales('El producto desactivado no aparece en el listado', 0, count($productos->listar()));

// ---------------------------------------------------------------- codigo QR
grupo('Codigo QR');

$qr = new CodigoQr('https://felpub.c.sat.gob.gt/verificador-web/publico/vistas/verificacionDte.jsf?uuid=' . $emision->uuid());
iguales('Tamaño coherente con la version', 17 + ($qr->version() * 4), $qr->tamano());
afirmar('La version esta dentro del rango soportado', $qr->version() >= 1 && $qr->version() <= 15);
afirmar('Genera SVG', str_starts_with($qr->svg(), '<svg'));
afirmar('Genera data URI', str_starts_with($qr->dataUri(), 'data:image/svg+xml;base64,'));

$matriz = $qr->matriz();
afirmar('Los tres patrones de deteccion estan presentes', (static function (array $m): bool {
    $n = count($m);
    foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$x, $y]) {
        // esquina oscura, anillo claro, centro oscuro
        if ($m[$y][$x] !== 1 || $m[$y + 1][$x + 1] !== 0 || $m[$y + 3][$x + 3] !== 1) {
            return false;
        }
    }

    return true;
})($matriz));

afirmar('El patron de sincronizacion alterna', (static function (array $m): bool {
    for ($i = 8; $i < count($m) - 8; $i++) {
        if ($m[6][$i] !== ($i % 2 === 0 ? 1 : 0)) {
            return false;
        }
    }

    return true;
})($matriz));

$mezcla = array_sum(array_map('array_sum', $matriz)) / (count($matriz) ** 2);
afirmar('La proporcion de modulos oscuros es razonable', $mezcla > 0.35 && $mezcla < 0.65);

$corto = new CodigoQr('a', CodigoQr::NIVEL_H);
iguales('Un contenido corto cabe en la version 1', 1, $corto->version());

$distintos = (new CodigoQr('AAA'))->matriz() !== (new CodigoQr('BBB'))->matriz();
afirmar('Contenidos distintos generan matrices distintas', $distintos);

$excepcion = false;
try {
    new CodigoQr(str_repeat('x', 5000));
} catch (\InvalidArgumentException) {
    $excepcion = true;
}
afirmar('Un contenido excesivo se rechaza con un mensaje claro', $excepcion);

// ------------------------------------------------------- cifrado de credenciales
grupo('Cifrado de credenciales');

$secreto = 'LLAVE-DE-FIRMA-DEL-CERTIFICADOR';
$cifrado = Cifrado::cifrar($secreto);
afirmar('El texto cifrado no contiene el original', !str_contains($cifrado, $secreto));
iguales('Se recupera el original', $secreto, Cifrado::descifrar($cifrado));
afirmar('Dos cifrados del mismo dato son distintos', Cifrado::cifrar($secreto) !== $cifrado);
iguales('Cadena vacia se conserva', '', Cifrado::cifrar(''));
iguales(
    'Arreglos completos van y vuelven',
    ['usuario_api' => 'u', 'llave_api' => 'k'],
    Cifrado::descifrarArreglo(Cifrado::cifrarArreglo(['usuario_api' => 'u', 'llave_api' => 'k']))
);

$alterado = false;
try {
    Cifrado::descifrar('fel1:' . base64_encode(random_bytes(48)));
} catch (\RuntimeException) {
    $alterado = true;
}
afirmar('Un dato alterado se detecta y se rechaza', $alterado);

// --------------------------------------------------- aislamiento entre empresas
grupo('Aislamiento entre empresas');

$otraId = $empresas->guardar([
    'nombre_interno'         => 'Otra empresa',
    'nit'                    => '80000002',
    'nombre_emisor'          => 'OTRA EMPRESA, S.A.',
    'nombre_comercial'       => 'OTRA',
    'certificador_proveedor' => 'simulador',
], null, ['usuario_api' => 'secreto-de-la-otra']);
$otra = $empresas->buscar($otraId);

afirmar('Se dio de alta la segunda empresa', $otra !== null);
iguales('Las credenciales se guardan cifradas y se leen bien', 'secreto-de-la-otra', (string) $otra->configCertificador()['usuario_api']);

$filaCruda = Db::conexion()->query('SELECT certificador_config FROM fel_empresas WHERE id = ' . $otraId)->fetchColumn();
afirmar('En la base no queda el secreto en claro', !str_contains((string) $filaCruda, 'secreto-de-la-otra'));

$servicioOtra = new FacturacionService($otra, new SimuladorCertificador());
$docOtra = new Documento('FACT', emisorPrueba(), Receptor::consumidorFinal());
$docOtra->agregarItem(new Item('Venta de la otra empresa', 1, 200.00));
$emisionOtra = $servicioOtra->emitir($docOtra, 'pruebas');
afirmar('La segunda empresa puede emitir', $emisionOtra->exito, $emisionOtra->mensaje());

$repoOtra = new DocumentoRepositorio($otraId);
afirmar('Cada empresa ve solo sus documentos',
    count($repo->listar()) >= 1 && count($repoOtra->listar()) === 1);
afirmar('Una empresa NO puede leer el documento de otra',
    $repoOtra->buscar((int) $emision->documentoId) === null);
afirmar('La empresa original NO ve el documento de la otra',
    $repo->buscar((int) $emisionOtra->documentoId) === null);
afirmar('Tampoco puede leer sus lineas de detalle',
    $repoOtra->items((int) $emision->documentoId) === []);

$clientesOtra = new ClienteRepositorio($otraId);
$clientesOtra->guardar(['identificador' => '80000002', 'nombre' => 'CLIENTE DE LA OTRA']);
iguales('Los clientes tampoco se mezclan', 1, count($clientesOtra->listar()));
afirmar('No se puede leer el cliente de otra empresa',
    $clientesOtra->buscar($clienteId) === null);

$anulacionCruzada = (new AnulacionService($otra, new SimuladorCertificador()))
    ->anular((int) $emisionOtra->documentoId, 'prueba');
afirmar('Se anula el documento propio', $anulacionCruzada['exito'], $anulacionCruzada['mensaje']);

$emisorForzado = new Documento('FACT', new Emisor('80000002', 'NIT AJENO', 'AJENO'), Receptor::consumidorFinal());
$emisorForzado->agregarItem(new Item('Intento de suplantacion', 1, 50.00));
$resultadoForzado = $servicio->emitir($emisorForzado, 'pruebas');
afirmar('El emisor del DTE siempre es el de la empresa activa, no el del formulario',
    $resultadoForzado->exito
    && (string) $repo->buscar((int) $resultadoForzado->documentoId)['emisor_nit'] === '12345679');

$repoInvalido = false;
try {
    new DocumentoRepositorio(0);
} catch (\InvalidArgumentException) {
    $repoInvalido = true;
}
afirmar('No se puede construir un repositorio sin empresa', $repoInvalido);

// ------------------------------------------------------------------- usuarios
grupo('Usuarios y roles');

$usuarios = new UsuarioRepositorio();
$usuarios->crear('super', 'claveLarguisima1', 'Superadministrador', UsuarioRepositorio::SUPERADMIN);
$usuarios->crear('opera', 'claveLarguisima2', 'Operador', UsuarioRepositorio::OPERADOR, $empresa->id());

afirmar('El superadministrador no queda atado a una empresa',
    (new UsuarioRepositorio())->porEmpresa(null)[0]['empresa_id'] === null
    || array_filter($usuarios->porEmpresa(null), static fn (array $u): bool =>
        $u['usuario'] === 'super' && $u['empresa_id'] === null) !== []);
iguales('El operador pertenece a su empresa', 1, count($usuarios->porEmpresa($empresa->id())));
afirmar('Se detecta un usuario existente', $usuarios->existe('opera'));
afirmar('Se puede cambiar la contrasena', $usuarios->cambiarClave('opera', 'otraClaveLarga3'));
afirmar('No se cambia la de un usuario inexistente', !$usuarios->cambiarClave('nadie', 'claveLarguisima9'));

// ------------------------------------------------------- uso por empresa
grupo('Uso por empresa (para facturar el servicio)');

$uso = $empresas->uso($empresa->id(), date('Y-m-01'), date('Y-m-d'));
afirmar('Se cuentan los documentos de la empresa', $uso['documentos'] >= 1);
afirmar('Se cuentan los certificados', $uso['certificados'] >= 1);
$usoOtra = $empresas->uso($otraId, date('Y-m-01'), date('Y-m-d'));
iguales('El uso de cada empresa es independiente', 1, $usoOtra['documentos']);

// ------------------------------------------------- lectura del esquema SQL
grupo('Lectura de los archivos de esquema');

// Este bloque existe por un error real: al dividir el .sql por ';' y descartar
// los fragmentos que empiezan con '--', se descartaba tambien la sentencia que
// venia detras del comentario. En MySQL no se creaba NINGUNA tabla.
foreach (['schema.sql' => 'MySQL', 'schema.sqlite.sql' => 'SQLite'] as $archivo => $motor) {
    $sql        = (string) file_get_contents(__DIR__ . '/../db/' . $archivo);
    $sentencias = Esquema::sentencias($sql);
    $tablas     = array_filter($sentencias, static fn (string $s): bool => stripos($s, 'CREATE TABLE') !== false);

    iguales("{$motor}: se leen las 8 tablas del esquema", 8, count($tablas));
    afirmar("{$motor}: ninguna sentencia queda vacia",
        array_filter($sentencias, static fn (string $s): bool => trim($s) === '') === []);
    afirmar("{$motor}: no quedan comentarios sueltos al inicio de una sentencia",
        array_filter($sentencias, static fn (string $s): bool => str_starts_with(trim($s), '--')) === []);

    foreach (['fel_empresas', 'fel_documentos', 'fel_usuarios'] as $tabla) {
        afirmar("{$motor}: se crea {$tabla}",
            array_filter($tablas, static fn (string $s): bool => str_contains($s, $tabla)) !== []);
    }
}

afirmar('Los comentarios de linea se eliminan, no la sentencia que los sigue',
    Esquema::sentencias("-- comentario\nCREATE TABLE x (id INT);") === ['CREATE TABLE x (id INT)']);
iguales('Un archivo solo de comentarios no produce sentencias',
    [], Esquema::sentencias("-- uno\n-- dos\n"));

// El esquema aplicado de verdad debe dejar las tablas utilizables.
$pdoPrueba = new PDO('sqlite::memory:');
$pdoPrueba->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$aplicadas = Esquema::aplicar($pdoPrueba, __DIR__ . '/../db/schema.sqlite.sql');
afirmar('Se aplican todas las sentencias del esquema', $aplicadas >= 9);
$tablasCreadas = $pdoPrueba->query(
    "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name LIKE 'fel_%'"
)->fetchColumn();
iguales('Quedan creadas las 8 tablas', 8, (int) $tablasCreadas);

// ---------------------------------------------------------------- cierre
@unlink($archivoDb);

echo "\n" . str_repeat('-', 60) . "\n";

if ($fallos === []) {
    echo "\033[32mTodas las pruebas pasaron ({$pruebas}).\033[0m\n";
    exit(0);
}

echo "\033[31m" . count($fallos) . " de {$pruebas} pruebas fallaron:\033[0m\n";
foreach ($fallos as $fallo) {
    echo "  - {$fallo}\n";
}
exit(1);
