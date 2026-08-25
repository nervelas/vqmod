<?php
/**
 * ---------------------------------------------------------------------------
 * Configuracion del sistema de facturacion FEL (Guatemala)
 * ---------------------------------------------------------------------------
 * Copie este archivo como config/config.php y complete sus datos reales.
 * NUNCA suba config.php a un repositorio: contiene llaves de firma y de API.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

return [

    'zona_horaria' => 'America/Guatemala',

    // -----------------------------------------------------------------------
    // Ambiente: 'pruebas' o 'produccion'.
    // En 'pruebas' se usa el certificador simulado salvo que indique otro.
    // -----------------------------------------------------------------------
    'ambiente' => 'pruebas',

    // -----------------------------------------------------------------------
    // DATOS DEL EMISOR
    // Deben coincidir EXACTAMENTE con lo registrado en la Agencia Virtual
    // de SAT (RTU). Cualquier diferencia hace que el certificador rechace
    // el documento.
    // -----------------------------------------------------------------------
    'emisor' => [
        'nit'                    => '12345679',           // sin guion
        'nombre'                 => 'MI EMPRESA, SOCIEDAD ANONIMA',
        'nombre_comercial'       => 'MI EMPRESA',
        // GEN = regimen general | PEQ = pequeño contribuyente
        // PEE = pequeño contribuyente electronico | AGR/AGE = agropecuario
        // EXE = exento
        'afiliacion_iva'         => 'GEN',
        // Codigo que SAT asigna a cada establecimiento habilitado.
        'codigo_establecimiento' => '1',
        'correo'                 => 'facturacion@miempresa.gt',
        'telefono'               => '+502 0000-0000',
        'direccion'              => '5a. Avenida 10-50 zona 1',
        'codigo_postal'          => '01001',
        'municipio'              => 'Guatemala',
        'departamento'           => 'Guatemala',
        'pais'                   => 'GT',
    ],

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
    // CERTIFICADOR
    //
    // En Guatemala NO se transmite directamente a SAT. Hay que contratar a
    // un CERTIFICADOR autorizado (INFILE, Digifact, Guatefacturas, Megaprint,
    // Certifika, entre otros). El certificador entrega:
    //   - usuario y llave de API
    //   - llave / alias para el servicio de firma
    //   - las URL de sus ambientes de pruebas y de produccion
    //
    // Las URL de abajo son de referencia: CONFIRMELAS contra el manual
    // tecnico que le entregue su certificador, porque cambian por plan y
    // por version de API.
    // -----------------------------------------------------------------------
    'certificador' => [

        // 'simulador' | 'infile' | cualquier clave definida abajo (adaptador REST generico)
        'proveedor' => 'simulador',

        // Datos que se imprimen en la representacion grafica
        'nombre_visible' => 'CERTIFICADOR SIMULADO (SIN VALIDEZ FISCAL)',
        'nit_visible'    => '12345679',

        'infile' => [
            'url_firma'         => 'https://signer-emisor.feel.com.gt/sign_solicitud',
            'url_certificacion' => 'https://certificador.feel.com.gt/fel/certificacion/v2/dte',
            'url_anulacion'     => 'https://certificador.feel.com.gt/fel/anulacion/v2/dte',

            // Servicio de firma
            'llave_firma'  => 'LLAVE_DE_FIRMA_QUE_LE_ENTREGO_EL_CERTIFICADOR',
            'alias_firma'  => '12345679',   // normalmente el NIT del emisor
            'codigo_firma' => 'DTE',

            // API de certificacion
            'usuario_api' => 'USUARIO_API',
            'llave_api'   => 'LLAVE_API',

            // Nombres de cabecera. Ajustelos si su manual usa otros.
            'cabeceras' => [
                'usuario'       => 'UsuarioApi',
                'llave'         => 'LlaveApi',
                'identificador' => 'Identificador',
            ],
        ],

        // ------------------------------------------------------------------
        // Adaptador REST generico: sirve para CUALQUIER certificador.
        // Marcadores disponibles: {XML} {XML_BASE64} {IDENTIFICADOR}
        // Copie este bloque, renombrelo y ponga su nombre en 'proveedor'.
        // ------------------------------------------------------------------
        'otro_certificador' => [
            'firma' => [
                'habilitada'          => true,
                'url'                 => 'https://ejemplo-certificador.gt/firma',
                'formato'             => 'json',
                'cabeceras'           => ['Content-Type' => 'application/json'],
                'plantilla'           => [
                    'llave'   => 'SU_LLAVE_DE_FIRMA',
                    'alias'   => '12345679',
                    'archivo' => '{XML_BASE64}',
                ],
                'campo_respuesta_xml' => 'archivo',
            ],
            'certificacion' => [
                'url'       => 'https://ejemplo-certificador.gt/certificar',
                'formato'   => 'xml',        // 'xml' | 'json' | 'base64'
                'cabeceras' => [
                    'Usuario'       => 'SU_USUARIO',
                    'Llave'         => 'SU_LLAVE_API',
                    'Identificador' => '{IDENTIFICADOR}',
                ],
            ],
            'anulacion' => [
                'url'       => 'https://ejemplo-certificador.gt/anular',
                'formato'   => 'xml',
                'cabeceras' => [
                    'Usuario'       => 'SU_USUARIO',
                    'Llave'         => 'SU_LLAVE_API',
                    'Identificador' => '{IDENTIFICADOR}',
                ],
            ],
        ],
    ],

    // -----------------------------------------------------------------------
    // REGLAS DE NEGOCIO
    // -----------------------------------------------------------------------
    'reglas' => [
        // Monto a partir del cual SAT espera que se identifique al comprador
        // en lugar de facturar a Consumidor Final. Verifique el monto vigente
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
        'timeout'           => 60,
        'timeout_conexion'  => 15,
        'reintentos'        => 3,
        'espera_reintento'  => 2,
        // No lo cambie: la conexion transporta datos fiscales.
        'verificar_tls'     => true,
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
        'url_verificador' => 'https://felpub.c.sat.gob.gt/verificador-web/publico/vistas/verificacionDte.jsf',
    ],

    // -----------------------------------------------------------------------
    // APLICACION WEB
    // -----------------------------------------------------------------------
    'app' => [
        'nombre'          => 'Facturación FEL',
        'sesion_minutos'  => 120,
        // Genere uno nuevo con: php -r "echo bin2hex(random_bytes(32));"
        'clave_aplicacion' => 'CAMBIE_ESTA_CLAVE_ALEATORIA',
    ],
];
