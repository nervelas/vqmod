<?php
/**
 * ---------------------------------------------------------------------------
 * Configuracion del sistema de facturacion FEL (Guatemala)
 * ---------------------------------------------------------------------------
 * Copie este archivo como config/config.php y complete sus datos.
 * NUNCA suba config.php a un repositorio.
 *
 * IMPORTANTE: los datos de cada empresa emisora (NIT, razon social,
 * credenciales del certificador) NO van aqui: se cargan desde la pantalla
 * "Empresas" y se guardan CIFRADOS en la base de datos. Este archivo solo
 * tiene la configuracion de la instalacion.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

return [

    'zona_horaria' => 'America/Guatemala',

    // -----------------------------------------------------------------------
    // BASE DE DATOS
    // En cPanel: cree la base y el usuario en "Bases de datos MySQL" y
    // asigne todos los privilegios. El nombre suele llevar el prefijo de
    // su cuenta, por ejemplo: usuario_fel
    // -----------------------------------------------------------------------
    'db' => [
        'driver'  => 'mysql',        // 'mysql' o 'sqlite'
        'host'    => 'localhost',
        'puerto'  => 3306,
        'nombre'  => 'usuario_fel',
        'usuario' => 'usuario_fel',
        'clave'   => 'CAMBIE_ESTA_CLAVE',
        // Solo para driver sqlite (pruebas locales):
        'archivo' => __DIR__ . '/../storage/fel.sqlite',
    ],

    // -----------------------------------------------------------------------
    // APLICACION
    // -----------------------------------------------------------------------
    'app' => [
        'nombre'         => 'Facturación FEL',
        'sesion_minutos' => 120,

        // CLAVE MAESTRA. Con ella se cifran las credenciales de certificador
        // de cada empresa. Genere una con:
        //     php -r "echo bin2hex(random_bytes(32));"
        //
        // Si la pierde, las credenciales guardadas no se pueden recuperar y
        // hay que volver a capturarlas. Respaldela junto con la base de datos.
        'clave_aplicacion' => 'CAMBIE_ESTA_CLAVE_ALEATORIA',
    ],

    // -----------------------------------------------------------------------
    // EMPRESA INICIAL (opcional)
    //
    // Solo se usa la PRIMERA VEZ, al correr bin/instalar.php o
    // bin/migrar_multiempresa.php: sirve para dar de alta la primera empresa
    // sin pasar por la pantalla. Despues de eso, todo se administra desde
    // "Empresas" y estos valores se ignoran.
    //
    // Si va a dar de alta las empresas desde la pantalla, deje esto vacio.
    // -----------------------------------------------------------------------
    'emisor' => [
        'nit'                    => '',           // sin guion
        'nombre'                 => '',           // razon social, igual al RTU
        'nombre_comercial'       => '',
        // GEN = regimen general | PEQ = pequeño contribuyente
        // PEE = pequeño contribuyente electronico | AGR/AGE = agropecuario
        // EXE = exento
        'afiliacion_iva'         => 'GEN',
        'codigo_establecimiento' => '1',          // el que asigno SAT
        'correo'                 => '',
        'telefono'               => '',
        'direccion'              => 'Ciudad',
        'codigo_postal'          => '01001',
        'municipio'              => 'Guatemala',
        'departamento'           => 'Guatemala',
        'pais'                   => 'GT',
    ],

    'ambiente' => 'pruebas',   // ambiente de la empresa inicial

    // -----------------------------------------------------------------------
    // CERTIFICADOR DE LA EMPRESA INICIAL (opcional, igual que 'emisor')
    //
    // En Guatemala NO se transmite directamente a SAT: hay que contratar a un
    // CERTIFICADOR autorizado (INFILE, Digifact, Guatefacturas, Megaprint,
    // Certifika, entre otros), que entrega usuario y llave de API, llave y
    // alias de firma, y las URL de sus ambientes.
    //
    // Las URL de abajo son de referencia: CONFIRMELAS contra el manual tecnico
    // de su certificador. Se pueden cargar tambien desde la pantalla Empresas.
    // -----------------------------------------------------------------------
    'certificador' => [
        'proveedor'      => 'simulador',   // 'simulador' | 'infile' | otro
        'nombre_visible' => '',
        'nit_visible'    => '',

        'infile' => [
            'url_firma'         => 'https://signer-emisor.feel.com.gt/sign_solicitud',
            'url_certificacion' => 'https://certificador.feel.com.gt/fel/certificacion/v2/dte',
            'url_anulacion'     => 'https://certificador.feel.com.gt/fel/anulacion/v2/dte',
            'llave_firma'       => '',
            'alias_firma'       => '',
            'codigo_firma'      => 'DTE',
            'usuario_api'       => '',
            'llave_api'         => '',
            'cabeceras'         => [
                'usuario'       => 'UsuarioApi',
                'llave'         => 'LlaveApi',
                'identificador' => 'Identificador',
            ],
        ],
    ],

    // -----------------------------------------------------------------------
    // REGLAS POR OMISION
    // Cada empresa puede tener las suyas; estas se usan como valor inicial.
    // -----------------------------------------------------------------------
    'reglas' => [
        // Monto desde el que SAT espera que se identifique al comprador en
        // lugar de facturar a Consumidor Final. Verifique el monto vigente
        // con su contador; 0 desactiva la advertencia.
        'limite_consumidor_final' => 2500.00,

        // Aviso (no bloqueo) cuando una anulacion sale de plazo. 0 lo desactiva.
        'dias_maximos_anulacion'  => 30,

        // Reintentos maximos de un documento en contingencia.
        'maximo_intentos'         => 10,
    ],

    // -----------------------------------------------------------------------
    // COMUNICACION HTTP
    // -----------------------------------------------------------------------
    'http' => [
        'timeout'          => 60,
        'timeout_conexion' => 15,
        'reintentos'       => 3,
        'espera_reintento' => 2,
        // No lo cambie: la conexion transporta datos fiscales.
        'verificar_tls'    => true,
    ],

    // -----------------------------------------------------------------------
    // XML
    // -----------------------------------------------------------------------
    'xml' => [
        'formato_legible'          => false,  // true solo para depurar
        'version_documento'        => '0.1',
        'version_anulacion'        => '0.1',
        'version_cambiaria'        => '1',
        'version_referencias_nota' => '0.0',
    ],

    // -----------------------------------------------------------------------
    // RUTAS DE ALMACENAMIENTO
    // Deben quedar FUERA de public_html o protegidas con .htaccess.
    // -----------------------------------------------------------------------
    'rutas' => [
        'almacen' => __DIR__ . '/../storage',
        'xml'     => __DIR__ . '/../storage/xml',
        'logs'    => __DIR__ . '/../storage/logs',
    ],

    // -----------------------------------------------------------------------
    // SAT
    // -----------------------------------------------------------------------
    'sat' => [
        // Contenido del codigo QR que se imprime en la representacion grafica.
        // Marcadores: {UUID} {NIT_EMISOR} {NIT_RECEPTOR} {MONTO} {FECHA}
        //             {SERIE} {NUMERO}
        //
        // Cada certificador publica su propia URL de consulta: si el suyo le
        // indica otra, pongala aqui.
        'plantilla_qr' => 'https://felpub.c.sat.gob.gt/verificador-web/publico/vistas/verificacionDte.jsf'
            . '?tipo=autorizacion&numero={UUID}&emisor={NIT_EMISOR}&receptor={NIT_RECEPTOR}&monto={MONTO}',
    ],
];
