<?php
/**
 * Controlador frontal de la aplicacion web.
 *
 * Las rutas van por parametro (?r=documentos) para que funcione en cualquier
 * hosting compartido de cPanel sin depender de mod_rewrite.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Fel\Certificador\Fabrica;
use Fel\Core\Config;
use Fel\Dte\Catalogos;
use Fel\Dte\Documento;
use Fel\Dte\Emisor;
use Fel\Dte\Frase;
use Fel\Dte\Item;
use Fel\Dte\Receptor;
use Fel\Dte\Referencia;
use Fel\Presentacion\RepresentacionGrafica;
use Fel\Repositorio\AnulacionRepositorio;
use Fel\Repositorio\BitacoraRepositorio;
use Fel\Repositorio\ClienteRepositorio;
use Fel\Repositorio\DocumentoRepositorio;
use Fel\Repositorio\ProductoRepositorio;
use Fel\Servicio\AnulacionService;
use Fel\Servicio\FacturacionService;
use Fel\Web\Flash;
use Fel\Web\Sesion;
use Fel\Web\Vista;

try {
    Config::cargar();
} catch (\RuntimeException $error) {
    http_response_code(500);
    echo '<h1>Configuración pendiente</h1><p>' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

date_default_timezone_set((string) Config::get('zona_horaria', 'America/Guatemala'));

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

Sesion::iniciar();

$ruta   = (string) ($_GET['r'] ?? 'panel');
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$publicas = ['ingresar', 'salir'];

if (!in_array($ruta, $publicas, true) && !Sesion::autenticado()) {
    redirigir('ingresar');
}

if ($metodo === 'POST' && !Sesion::csrfValido($_POST['csrf'] ?? null)) {
    http_response_code(419);
    exit('Token de seguridad vencido. Vuelva a cargar la página.');
}

function redirigir(string $ruta, array $parametros = []): never
{
    $consulta = http_build_query(array_merge(['r' => $ruta], $parametros));
    header('Location: index.php?' . $consulta);
    exit;
}

function entrada(string $clave, string $porDefecto = ''): string
{
    return trim((string) ($_POST[$clave] ?? $_GET[$clave] ?? $porDefecto));
}

$documentos  = new DocumentoRepositorio();
$clientes    = new ClienteRepositorio();
$productos   = new ProductoRepositorio();
$anulaciones = new AnulacionRepositorio();
$bitacora    = new BitacoraRepositorio();

switch ($ruta) {

    // ------------------------------------------------------------- acceso
    case 'ingresar':
        if (Sesion::autenticado()) {
            redirigir('panel');
        }

        if ($metodo === 'POST') {
            if (Sesion::intentarIngreso(entrada('usuario'), (string) ($_POST['clave'] ?? ''))) {
                redirigir('panel');
            }
            Flash::error('Usuario o contraseña incorrectos.');
        }

        echo Vista::parcial('login', ['csrf' => Sesion::tokenCsrf(), 'mensajes' => Flash::consumir()]);
        break;

    case 'salir':
        Sesion::cerrar();
        redirigir('ingresar');
        // no break

    // ------------------------------------------------------------- panel
    case 'panel':
        $desde = date('Y-m-01');
        $hasta = date('Y-m-d');

        echo Vista::render('panel', [
            'resumen'      => $documentos->resumen($desde, $hasta),
            'recientes'    => $documentos->listar([], 8),
            'pendientes'   => $documentos->pendientes(50),
            'certificador' => (string) Config::get('certificador.proveedor', 'simulador'),
            'ambiente'     => (string) Config::get('ambiente', 'pruebas'),
            'desde'        => $desde,
            'hasta'        => $hasta,
        ], 'Panel');
        break;

    // -------------------------------------------------------- documentos
    case 'documentos':
        $filtros = [
            'estado'   => entrada('estado'),
            'tipo'     => entrada('tipo'),
            'receptor' => entrada('receptor'),
            'desde'    => entrada('desde'),
            'hasta'    => entrada('hasta'),
        ];

        echo Vista::render('documentos', [
            'documentos' => $documentos->listar($filtros, 200),
            'filtros'    => $filtros,
            'tipos'      => Catalogos::tiposDte(),
        ], 'Documentos');
        break;

    case 'nuevo':
        echo Vista::render('documento_nuevo', [
            'csrf'      => Sesion::tokenCsrf(),
            'tipos'     => Catalogos::tiposDte(),
            'unidades'  => Catalogos::unidadesMedida(),
            'monedas'   => Catalogos::monedas(),
            'clientes'  => $clientes->listar(),
            'productos' => $productos->listar(),
            'frases'    => Catalogos::frases(),
            'emisor'    => (array) Config::get('emisor', []),
        ], 'Nuevo documento');
        break;

    case 'emitir':
        if ($metodo !== 'POST') {
            redirigir('nuevo');
        }

        try {
            $documento = construirDocumentoDesdeFormulario($_POST);
        } catch (\Throwable $error) {
            Flash::error('No se pudo preparar el documento: ' . $error->getMessage());
            redirigir('nuevo');
        }

        $servicio  = new FacturacionService();
        $resultado = $servicio->emitir(
            $documento,
            (string) (Sesion::usuario()['usuario'] ?? ''),
            ((int) entrada('cliente_id')) ?: null
        );

        if ($resultado->exito) {
            Flash::exito('Documento certificado. Número de autorización: ' . $resultado->uuid());
            redirigir('ver', ['id' => (int) $resultado->documentoId]);
        }

        foreach ($resultado->errores as $mensaje) {
            Flash::error($mensaje);
        }

        if ($resultado->documentoId !== null) {
            Flash::aviso('El documento quedó guardado en estado ' . $resultado->estado . '.');
            redirigir('ver', ['id' => (int) $resultado->documentoId]);
        }

        redirigir('nuevo');
        // no break

    case 'ver':
        $id  = (int) entrada('id');
        $fila = $documentos->buscar($id);

        if ($fila === null) {
            Flash::error('Documento no encontrado.');
            redirigir('documentos');
        }

        echo Vista::render('documento_ver', [
            'documento'   => $fila,
            'items'       => $documentos->items($id),
            'bitacora'    => $bitacora->porDocumento($id),
            'anulaciones' => $anulaciones->porDocumento($id),
            'csrf'        => Sesion::tokenCsrf(),
        ], 'Documento ' . $fila['serie'] . '-' . $fila['numero']);
        break;

    case 'imprimir':
        $id   = (int) entrada('id');
        $fila = $documentos->buscar($id);

        if ($fila === null) {
            http_response_code(404);
            exit('Documento no encontrado.');
        }

        echo (new RepresentacionGrafica())->html(
            $fila,
            $documentos->items($id),
            textosDeFrases((string) $fila['xml_enviado'])
        );
        break;

    case 'xml':
        $id   = (int) entrada('id');
        $fila = $documentos->buscar($id);

        if ($fila === null) {
            http_response_code(404);
            exit('Documento no encontrado.');
        }

        $xml = (string) ($fila['xml_certificado'] ?: $fila['xml_enviado']);
        $nombre = sprintf('%s-%s-%s.xml', $fila['tipo'], $fila['serie'] ?: 'SIN_SERIE', $fila['numero'] ?: $id);

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        echo $xml;
        break;

    case 'anular':
        if ($metodo !== 'POST') {
            redirigir('documentos');
        }

        $id     = (int) entrada('id');
        $motivo = entrada('motivo');

        $resultado = (new AnulacionService())->anular($id, $motivo, (string) (Sesion::usuario()['usuario'] ?? ''));

        if ($resultado['exito']) {
            Flash::exito($resultado['mensaje']);
        } else {
            Flash::error($resultado['mensaje']);
        }

        redirigir('ver', ['id' => $id]);
        // no break

    case 'reintentar':
        if ($metodo !== 'POST') {
            redirigir('panel');
        }

        $id        = (int) entrada('id');
        $resultado = (new FacturacionService())->reintentar($id);

        if ($resultado->exito) {
            Flash::exito('Documento certificado. Número de autorización: ' . $resultado->uuid());
        } else {
            Flash::error($resultado->mensaje());
        }

        redirigir('ver', ['id' => $id]);
        // no break

    // ---------------------------------------------------------- clientes
    case 'clientes':
        echo Vista::render('clientes', [
            'clientes'      => $clientes->listar(entrada('q')),
            'busqueda'      => entrada('q'),
            'csrf'          => Sesion::tokenCsrf(),
            'departamentos' => Catalogos::departamentos(),
            'edicion'       => ((int) entrada('editar')) ? $clientes->buscar((int) entrada('editar')) : null,
        ], 'Clientes');
        break;

    case 'cliente_guardar':
        if ($metodo !== 'POST') {
            redirigir('clientes');
        }

        $id = ((int) entrada('id')) ?: null;

        if (entrada('nombre') === '') {
            Flash::error('El nombre del cliente es obligatorio.');
            redirigir('clientes');
        }

        $clientes->guardar($_POST, $id);
        Flash::exito($id === null ? 'Cliente agregado.' : 'Cliente actualizado.');
        redirigir('clientes');
        // no break

    case 'cliente_eliminar':
        if ($metodo !== 'POST') {
            redirigir('clientes');
        }
        $clientes->desactivar((int) entrada('id'));
        Flash::exito('Cliente desactivado.');
        redirigir('clientes');
        // no break

    // --------------------------------------------------------- productos
    case 'productos':
        echo Vista::render('productos', [
            'productos' => $productos->listar(entrada('q')),
            'busqueda'  => entrada('q'),
            'csrf'      => Sesion::tokenCsrf(),
            'unidades'  => Catalogos::unidadesMedida(),
            'edicion'   => ((int) entrada('editar')) ? $productos->buscar((int) entrada('editar')) : null,
        ], 'Productos y servicios');
        break;

    case 'producto_guardar':
        if ($metodo !== 'POST') {
            redirigir('productos');
        }

        $id = ((int) entrada('id')) ?: null;

        if (entrada('descripcion') === '') {
            Flash::error('La descripción es obligatoria.');
            redirigir('productos');
        }

        $productos->guardar($_POST, $id);
        Flash::exito($id === null ? 'Producto agregado.' : 'Producto actualizado.');
        redirigir('productos');
        // no break

    case 'producto_eliminar':
        if ($metodo !== 'POST') {
            redirigir('productos');
        }
        $productos->desactivar((int) entrada('id'));
        Flash::exito('Producto desactivado.');
        redirigir('productos');
        // no break

    // ------------------------------------------------------------ ajustes
    case 'ajustes':
        echo Vista::render('ajustes', [
            'emisor'        => (array) Config::get('emisor', []),
            'certificador'  => (string) Config::get('certificador.proveedor', 'simulador'),
            'disponibles'   => Fabrica::disponibles(),
            'ambiente'      => (string) Config::get('ambiente', 'pruebas'),
            'reglas'        => (array) Config::get('reglas', []),
        ], 'Ajustes');
        break;

    default:
        http_response_code(404);
        echo Vista::render('error404', [], 'No encontrado');
}

/**
 * Arma el objeto Documento a partir del formulario de emision.
 *
 * @param array<string,mixed> $datos
 */
function construirDocumentoDesdeFormulario(array $datos): Documento
{
    $emisor = Emisor::desdeArray((array) Config::get('emisor', []));

    $receptor = new Receptor(
        id:           (string) ($datos['receptor_id'] ?? 'CF'),
        nombre:       (string) ($datos['receptor_nombre'] ?? 'Consumidor Final'),
        correo:       (string) ($datos['receptor_correo'] ?? ''),
        tipoEspecial: (string) ($datos['receptor_tipo_especial'] ?? ''),
        direccion:    (string) ($datos['receptor_direccion'] ?? 'Ciudad'),
        municipio:    (string) ($datos['receptor_municipio'] ?? 'Guatemala'),
        departamento: (string) ($datos['receptor_departamento'] ?? 'Guatemala'),
    );

    $documento = new Documento(
        tipo: (string) ($datos['tipo'] ?? 'FACT'),
        emisor: $emisor,
        receptor: $receptor,
        moneda: (string) ($datos['moneda'] ?? 'GTQ'),
        tipoCambio: (float) ($datos['tipo_cambio'] ?? 1),
        observaciones: (string) ($datos['observaciones'] ?? ''),
        referenciaInterna: (string) ($datos['referencia_interna'] ?? ''),
    );

    /** @var array<int,array<string,string>> $lineas */
    $lineas = (array) ($datos['items'] ?? []);

    foreach ($lineas as $linea) {
        if (trim((string) ($linea['descripcion'] ?? '')) === '') {
            continue;
        }

        $documento->agregarItem(new Item(
            descripcion:    (string) $linea['descripcion'],
            cantidad:       (float) ($linea['cantidad'] ?? 1),
            precioUnitario: (float) ($linea['precio_unitario'] ?? 0),
            tipo:           strtoupper((string) ($linea['tipo'] ?? 'B')),
            unidadMedida:   (string) ($linea['unidad_medida'] ?? 'UNI'),
            descuento:      (float) ($linea['descuento'] ?? 0),
            exento:         !empty($linea['exento']),
        ));
    }

    foreach ((array) ($datos['frases'] ?? []) as $clave) {
        $documento->agregarFrase(Frase::porClave((string) $clave));
    }

    $uuidOrigen = trim((string) ($datos['ref_uuid'] ?? ''));
    if ($uuidOrigen !== '') {
        $documento->referencia = new Referencia(
            numeroAutorizacionDocumentoOrigen: $uuidOrigen,
            fechaEmisionDocumentoOrigen: (string) ($datos['ref_fecha'] ?? date('Y-m-d')),
            motivoAjuste: (string) ($datos['ref_motivo'] ?? ''),
            numeroDocumentoOrigen: (string) ($datos['ref_numero'] ?? ''),
            serieDocumentoOrigen: (string) ($datos['ref_serie'] ?? ''),
        );
    }

    return $documento;
}

/**
 * Extrae las frases del XML para mostrarlas en la representacion grafica.
 *
 * @return list<string>
 */
function textosDeFrases(string $xml): array
{
    if (trim($xml) === '') {
        return [];
    }

    $doc = new DOMDocument();
    if (!@$doc->loadXML($xml)) {
        return [];
    }

    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('dte', \Fel\Dte\XmlBuilder::NS_DTE);

    $catalogo = Catalogos::frases();
    $textos   = [];

    /** @var DOMNodeList<DOMElement> $nodos */
    $nodos = $xpath->query('//dte:Frase');

    foreach ($nodos as $nodo) {
        $tipo      = (int) $nodo->getAttribute('TipoFrase');
        $escenario = (int) $nodo->getAttribute('CodigoEscenario');

        foreach ($catalogo as $frase) {
            if ($frase['tipo'] === $tipo && $frase['escenario'] === $escenario) {
                $textos[] = $frase['texto'];
                continue 2;
            }
        }

        $textos[] = sprintf('Frase tipo %d, escenario %d', $tipo, $escenario);
    }

    return $textos;
}
