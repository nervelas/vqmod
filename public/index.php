<?php
/**
 * Controlador frontal.
 *
 * Las rutas van por parametro (?r=documentos) para que funcione en cualquier
 * hosting compartido de cPanel sin depender de mod_rewrite.
 *
 * Toda ruta de operacion trabaja sobre la empresa activa (Contexto): un
 * operador solo ve la suya, y el administrador de la plataforma elige sobre
 * cual trabaja.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Fel\Certificador\Fabrica;
use Fel\Core\Config;
use Fel\Dte\Catalogos;
use Fel\Dte\Documento;
use Fel\Dte\Frase;
use Fel\Dte\Item;
use Fel\Dte\Receptor;
use Fel\Dte\Referencia;
use Fel\Dte\XmlBuilder;
use Fel\Presentacion\RepresentacionGrafica;
use Fel\Repositorio\AnulacionRepositorio;
use Fel\Repositorio\BitacoraRepositorio;
use Fel\Repositorio\ClienteRepositorio;
use Fel\Repositorio\DocumentoRepositorio;
use Fel\Repositorio\EmpresaRepositorio;
use Fel\Repositorio\ProductoRepositorio;
use Fel\Repositorio\UsuarioRepositorio;
use Fel\Servicio\AnulacionService;
use Fel\Servicio\FacturacionService;
use Fel\Web\Contexto;
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

/** Rutas que no exigen sesion. */
const RUTAS_PUBLICAS = ['ingresar', 'salir'];

/** Rutas reservadas al administrador de la plataforma. */
const RUTAS_PLATAFORMA = [
    'empresas', 'empresa_nueva', 'empresa_editar', 'empresa_guardar',
    'empresa_estado', 'usar_empresa', 'usuarios', 'usuario_guardar', 'usuario_estado',
];

/** Rutas que funcionan sin empresa seleccionada. */
const RUTAS_SIN_EMPRESA = ['ingresar', 'salir', ...RUTAS_PLATAFORMA];

if (!in_array($ruta, RUTAS_PUBLICAS, true) && !Sesion::autenticado()) {
    redirigir('ingresar');
}

if ($metodo === 'POST' && !Sesion::csrfValido($_POST['csrf'] ?? null)) {
    http_response_code(419);
    exit('Token de seguridad vencido. Vuelva a cargar la página.');
}

if (in_array($ruta, RUTAS_PLATAFORMA, true) && !Sesion::esSuperadmin()) {
    http_response_code(403);
    echo Vista::render('error403', [], 'Sin permiso');
    exit;
}

if (!in_array($ruta, RUTAS_SIN_EMPRESA, true) && !Contexto::hayEmpresa()) {
    if (Sesion::esSuperadmin()) {
        Flash::aviso('Elija la empresa sobre la que va a trabajar.');
        redirigir('empresas');
    }

    Sesion::cerrar();
    redirigir('ingresar');
}

function redirigir(string $ruta, array $parametros = []): never
{
    header('Location: index.php?' . http_build_query(array_merge(['r' => $ruta], $parametros)));
    exit;
}

function entrada(string $clave, string $porDefecto = ''): string
{
    return trim((string) ($_POST[$clave] ?? $_GET[$clave] ?? $porDefecto));
}

function usuarioActual(): string
{
    return (string) (Sesion::usuario()['usuario'] ?? '');
}

$empresas = new EmpresaRepositorio();

switch ($ruta) {

    // ------------------------------------------------------------- acceso
    case 'ingresar':
        if (Sesion::autenticado()) {
            redirigir('panel');
        }

        if ($metodo === 'POST') {
            if (Sesion::intentarIngreso(entrada('usuario'), (string) ($_POST['clave'] ?? ''))) {
                redirigir(Sesion::esSuperadmin() ? 'empresas' : 'panel');
            }
            Flash::error('Usuario o contraseña incorrectos.');
        }

        echo Vista::parcial('login', ['csrf' => Sesion::tokenCsrf(), 'mensajes' => Flash::consumir()]);
        break;

    case 'salir':
        Sesion::cerrar();
        redirigir('ingresar');
        // no break

    // -------------------------------------------------- plataforma: empresas
    case 'empresas':
        $desde = date('Y-m-01');
        $hasta = date('Y-m-d');
        $lista = [];

        foreach ($empresas->listar() as $registro) {
            $lista[] = [
                'empresa' => $registro,
                'uso'     => $empresas->uso($registro->id(), $desde, $hasta),
            ];
        }

        echo Vista::render('empresas', [
            'lista' => $lista,
            'csrf'  => Sesion::tokenCsrf(),
            'desde' => $desde,
            'hasta' => $hasta,
        ], 'Empresas');
        break;

    case 'empresa_nueva':
    case 'empresa_editar':
        $id      = (int) entrada('id');
        $empresa = $id > 0 ? $empresas->buscar($id) : null;

        if ($ruta === 'empresa_editar' && $empresa === null) {
            Flash::error('Empresa no encontrada.');
            redirigir('empresas');
        }

        echo Vista::render('empresa_form', [
            'empresa'       => $empresa,
            'csrf'          => Sesion::tokenCsrf(),
            'departamentos' => Catalogos::departamentos(),
            'afiliaciones'  => Catalogos::afiliacionesIva(),
            'proveedores'   => Fabrica::proveedores(),
            'credenciales'  => $empresa?->configCertificador() ?? [],
            'usuarios'      => $empresa === null ? [] : (new UsuarioRepositorio())->porEmpresa($empresa->id()),
        ], $empresa === null ? 'Nueva empresa' : 'Editar empresa');
        break;

    case 'empresa_guardar':
        if ($metodo !== 'POST') {
            redirigir('empresas');
        }

        $id = ((int) entrada('id')) ?: null;

        if (entrada('nombre_interno') === '' || entrada('nit') === '' || entrada('nombre_emisor') === '') {
            Flash::error('El nombre interno, el NIT y la razón social son obligatorios.');
            redirigir($id === null ? 'empresa_nueva' : 'empresa_editar', $id === null ? [] : ['id' => $id]);
        }

        $credenciales = credencialesDesdeFormulario($_POST);

        try {
            $guardadaId = $empresas->guardar($_POST, $id, $credenciales);
        } catch (\Throwable $error) {
            Flash::error('No se pudo guardar: ' . $error->getMessage());
            redirigir('empresas');
        }

        // Alta opcional del primer usuario de la empresa
        if (entrada('nuevo_usuario') !== '' && strlen((string) ($_POST['nueva_clave'] ?? '')) >= 10) {
            $usuarios = new UsuarioRepositorio();

            if ($usuarios->existe(entrada('nuevo_usuario'))) {
                Flash::error('Ya existe un usuario con ese nombre; no se creó.');
            } else {
                $usuarios->crear(
                    entrada('nuevo_usuario'),
                    (string) $_POST['nueva_clave'],
                    entrada('nuevo_usuario_nombre'),
                    UsuarioRepositorio::ADMIN,
                    $guardadaId
                );
                Flash::exito('Usuario de la empresa creado.');
            }
        } elseif (entrada('nuevo_usuario') !== '') {
            Flash::error('La contraseña del usuario debe tener al menos 10 caracteres; no se creó.');
        }

        Flash::exito($id === null ? 'Empresa creada.' : 'Empresa actualizada.');
        redirigir('empresa_editar', ['id' => $guardadaId]);
        // no break

    case 'empresa_estado':
        if ($metodo !== 'POST') {
            redirigir('empresas');
        }
        $empresas->cambiarEstado((int) entrada('id'), entrada('activa') === '1');
        Flash::exito('Estado de la empresa actualizado.');
        redirigir('empresas');
        // no break

    case 'usar_empresa':
        $id = (int) entrada('id');

        if ($empresas->buscar($id) === null) {
            Flash::error('Empresa no encontrada.');
            redirigir('empresas');
        }

        Sesion::usarEmpresa($id);
        Contexto::reiniciar();
        redirigir('panel');
        // no break

    case 'usuarios':
        echo Vista::render('usuarios', [
            'usuarios' => (new UsuarioRepositorio())->porEmpresa(null),
            'empresas' => $empresas->listar(),
            'csrf'     => Sesion::tokenCsrf(),
        ], 'Usuarios');
        break;

    case 'usuario_guardar':
        if ($metodo !== 'POST') {
            redirigir('usuarios');
        }

        $usuarios = new UsuarioRepositorio();
        $nombre   = entrada('usuario');
        $clave    = (string) ($_POST['clave'] ?? '');

        if ($nombre === '' || strlen($clave) < 10) {
            Flash::error('Indique el usuario y una contraseña de al menos 10 caracteres.');
            redirigir('usuarios');
        }

        if ($usuarios->existe($nombre)) {
            $usuarios->cambiarClave($nombre, $clave);
            Flash::exito('Contraseña actualizada.');
            redirigir('usuarios');
        }

        $rol = entrada('rol', UsuarioRepositorio::OPERADOR);
        $usuarios->crear(
            $nombre,
            $clave,
            entrada('nombre'),
            $rol,
            ((int) entrada('empresa_id')) ?: null
        );

        Flash::exito('Usuario creado.');
        redirigir('usuarios');
        // no break

    case 'usuario_estado':
        if ($metodo !== 'POST') {
            redirigir('usuarios');
        }
        (new UsuarioRepositorio())->cambiarEstado((int) entrada('id'), entrada('activo') === '1');
        Flash::exito('Usuario actualizado.');
        redirigir('usuarios');
        // no break

    // --------------------------------------------------------------- panel
    case 'panel':
        $empresa    = Contexto::empresaRequerida();
        $documentos = new DocumentoRepositorio($empresa->id());
        $desde      = date('Y-m-01');
        $hasta      = date('Y-m-d');

        echo Vista::render('panel', [
            'empresa'    => $empresa,
            'resumen'    => $documentos->resumen($desde, $hasta),
            'recientes'  => $documentos->listar([], 8),
            'pendientes' => $documentos->pendientes(50),
            'problemas'  => $empresa->problemas(),
            'desde'      => $desde,
            'hasta'      => $hasta,
        ], 'Panel');
        break;

    // ---------------------------------------------------------- documentos
    case 'documentos':
        $documentos = new DocumentoRepositorio(Contexto::empresaId());
        $filtros    = [
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
        $empresa = Contexto::empresaRequerida();

        echo Vista::render('documento_nuevo', [
            'csrf'      => Sesion::tokenCsrf(),
            'tipos'     => Catalogos::tiposDte(),
            'unidades'  => Catalogos::unidadesMedida(),
            'monedas'   => Catalogos::monedas(),
            'clientes'  => (new ClienteRepositorio($empresa->id()))->listar(),
            'productos' => (new ProductoRepositorio($empresa->id()))->listar(),
            'frases'    => Catalogos::frases(),
            'empresa'   => $empresa,
        ], 'Nuevo documento');
        break;

    case 'emitir':
        if ($metodo !== 'POST') {
            redirigir('nuevo');
        }

        $empresa = Contexto::empresaRequerida();

        try {
            $documento = construirDocumentoDesdeFormulario($_POST, $empresa);
        } catch (\Throwable $error) {
            Flash::error('No se pudo preparar el documento: ' . $error->getMessage());
            redirigir('nuevo');
        }

        $resultado = (new FacturacionService($empresa))->emitir(
            $documento,
            usuarioActual(),
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
        $empresa    = Contexto::empresaRequerida();
        $documentos = new DocumentoRepositorio($empresa->id());
        $id         = (int) entrada('id');
        $fila       = $documentos->buscar($id);

        if ($fila === null) {
            Flash::error('Documento no encontrado.');
            redirigir('documentos');
        }

        echo Vista::render('documento_ver', [
            'empresa'     => $empresa,
            'documento'   => $fila,
            'items'       => $documentos->items($id),
            'bitacora'    => (new BitacoraRepositorio($empresa->id()))->porDocumento($id),
            'anulaciones' => (new AnulacionRepositorio($empresa->id()))->porDocumento($id),
            'csrf'        => Sesion::tokenCsrf(),
        ], 'Documento ' . $fila['serie'] . '-' . $fila['numero']);
        break;

    case 'imprimir':
        $empresa    = Contexto::empresaRequerida();
        $documentos = new DocumentoRepositorio($empresa->id());
        $id         = (int) entrada('id');
        $fila       = $documentos->buscar($id);

        if ($fila === null) {
            http_response_code(404);
            exit('Documento no encontrado.');
        }

        $formato = entrada('formato') === '' ? null : entrada('formato');

        echo (new RepresentacionGrafica())->html(
            $empresa,
            $fila,
            $documentos->items($id),
            textosDeFrases((string) $fila['xml_enviado']),
            $formato
        );
        break;

    case 'xml':
        $documentos = new DocumentoRepositorio(Contexto::empresaId());
        $id         = (int) entrada('id');
        $fila       = $documentos->buscar($id);

        if ($fila === null) {
            http_response_code(404);
            exit('Documento no encontrado.');
        }

        $nombre = sprintf('%s-%s-%s.xml', $fila['tipo'], $fila['serie'] ?: 'SIN_SERIE', $fila['numero'] ?: $id);

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        echo (string) ($fila['xml_certificado'] ?: $fila['xml_enviado']);
        break;

    case 'anular':
        if ($metodo !== 'POST') {
            redirigir('documentos');
        }

        $id        = (int) entrada('id');
        $resultado = (new AnulacionService(Contexto::empresaRequerida()))
            ->anular($id, entrada('motivo'), usuarioActual());

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
        $resultado = (new FacturacionService(Contexto::empresaRequerida()))->reintentar($id);

        if ($resultado->exito) {
            Flash::exito('Documento certificado. Número de autorización: ' . $resultado->uuid());
        } else {
            Flash::error($resultado->mensaje());
        }

        redirigir('ver', ['id' => $id]);
        // no break

    // ---------------------------------------------------------- clientes
    case 'clientes':
        $clientes = new ClienteRepositorio(Contexto::empresaId());

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

        if (entrada('nombre') === '') {
            Flash::error('El nombre del cliente es obligatorio.');
            redirigir('clientes');
        }

        $id = ((int) entrada('id')) ?: null;
        (new ClienteRepositorio(Contexto::empresaId()))->guardar($_POST, $id);
        Flash::exito($id === null ? 'Cliente agregado.' : 'Cliente actualizado.');
        redirigir('clientes');
        // no break

    case 'cliente_eliminar':
        if ($metodo !== 'POST') {
            redirigir('clientes');
        }
        (new ClienteRepositorio(Contexto::empresaId()))->desactivar((int) entrada('id'));
        Flash::exito('Cliente desactivado.');
        redirigir('clientes');
        // no break

    // --------------------------------------------------------- productos
    case 'productos':
        $productos = new ProductoRepositorio(Contexto::empresaId());

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

        if (entrada('descripcion') === '') {
            Flash::error('La descripción es obligatoria.');
            redirigir('productos');
        }

        $id = ((int) entrada('id')) ?: null;
        (new ProductoRepositorio(Contexto::empresaId()))->guardar($_POST, $id);
        Flash::exito($id === null ? 'Producto agregado.' : 'Producto actualizado.');
        redirigir('productos');
        // no break

    case 'producto_eliminar':
        if ($metodo !== 'POST') {
            redirigir('productos');
        }
        (new ProductoRepositorio(Contexto::empresaId()))->desactivar((int) entrada('id'));
        Flash::exito('Producto desactivado.');
        redirigir('productos');
        // no break

    // ------------------------------------------------------------ ajustes
    case 'ajustes':
        echo Vista::render('ajustes', [
            'empresa' => Contexto::empresaRequerida(),
        ], 'Ajustes');
        break;

    default:
        http_response_code(404);
        echo Vista::render('error404', [], 'No encontrado');
}

/**
 * Extrae las credenciales del certificador del formulario de empresa.
 *
 * Devuelve null cuando el operador dejo los campos vacios, para no borrar
 * por descuido las credenciales ya guardadas al editar otros datos.
 *
 * @param array<string,mixed> $datos
 * @return array<string,mixed>|null
 */
function credencialesDesdeFormulario(array $datos): ?array
{
    $campos = [
        'url_firma', 'url_certificacion', 'url_anulacion',
        'llave_firma', 'alias_firma', 'codigo_firma',
        'usuario_api', 'llave_api',
    ];

    $credenciales = [];
    foreach ($campos as $campo) {
        $valor = trim((string) ($datos['cert_' . $campo] ?? ''));
        if ($valor !== '') {
            $credenciales[$campo] = $valor;
        }
    }

    // JSON libre para el adaptador REST generico
    $json = trim((string) ($datos['cert_json'] ?? ''));
    if ($json !== '') {
        $decodificado = json_decode($json, true);
        if (is_array($decodificado)) {
            $credenciales = array_merge($credenciales, $decodificado);
        }
    }

    return $credenciales === [] ? null : $credenciales;
}

/**
 * Arma el objeto Documento a partir del formulario de emision.
 * El emisor NO se toma de aqui: lo impone el servicio desde la empresa activa.
 *
 * @param array<string,mixed> $datos
 */
function construirDocumentoDesdeFormulario(array $datos, \Fel\Plataforma\Empresa $empresa): Documento
{
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
        emisor: $empresa->emisor(),
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
    $xpath->registerNamespace('dte', XmlBuilder::NS_DTE);

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
