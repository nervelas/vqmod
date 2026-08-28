# 03 — Conectar su certificador

## Qué es y cuánto cuesta

Un **Certificador de Documentos Tributarios Electrónicos** es una empresa
autorizada por SAT para firmar, validar y certificar los DTE de otros
contribuyentes, y transmitirlos a SAT. Sin uno no hay facturación FEL propia.

Certificadores que operan en Guatemala (la lista oficial y vigente está
publicada por SAT en su portal — verifíquela antes de contratar):

- INFILE
- Digifact
- Guatefacturas
- Megaprint
- Certifika
- G4S Documentia

El costo se maneja por documento certificado o por paquetes mensuales, y suele
ser de pocos centavos por documento. Pida cotización a dos o tres: los precios
y la calidad del soporte varían bastante.

## Qué pedir al contratar

Cuando firme, exija estos cinco puntos. Sin ellos no se puede integrar:

1. **Manual técnico de integración** (el documento con endpoints, formatos y
   códigos de error).
2. **Credenciales de ambiente de pruebas** — para no gastar folios reales
   mientras ajusta.
3. **Credenciales de producción**.
4. **Llave y alias del servicio de firma** (la firma electrónica del emisor).
5. **Usuario y llave de API** para el servicio de certificación.

Pregunte también:

- ¿Cuál es su disponibilidad comprometida (SLA)?
- ¿Tienen límite de documentos por minuto?
- ¿Cómo se manejan las anulaciones: mismo endpoint u otro?
- ¿Qué versión de esquema de SAT soportan?

## Dónde se cargan las credenciales

Desde la interfaz: **Empresas → editar la empresa → sección Certificador**.
Se guardan **cifradas** (AES-256-GCM) en la base de datos con la clave de
`app.clave_aplicacion`.

Los campos del formulario son los mismos que se describen abajo. El bloque
*"Ajustes adicionales en JSON"* sirve para cualquier opción del adaptador
genérico que no tenga campo propio.

`config/config.php` solo se usa para sembrar la **primera** empresa durante la
instalación; después de eso, todo se administra desde la pantalla.

### Opción 1 — INFILE (adaptador incluido)

En la pantalla: proveedor **INFILE**, y llene las URL y credenciales. En
`config.php` (solo para la empresa inicial) equivale a:

```php
'certificador' => [
    'proveedor' => 'infile',

    'nombre_visible' => 'INFILE, S.A.',   // sale impreso en la factura
    'nit_visible'    => 'EL-NIT-DE-INFILE',

    'infile' => [
        'url_firma'         => 'https://...',   // del manual que le entreguen
        'url_certificacion' => 'https://...',
        'url_anulacion'     => 'https://...',

        'llave_firma'  => 'LLAVE-DEL-SERVICIO-DE-FIRMA',
        'alias_firma'  => '12345679',           // normalmente su NIT
        'codigo_firma' => 'DTE',

        'usuario_api' => 'SU-USUARIO-API',
        'llave_api'   => 'SU-LLAVE-API',

        'cabeceras' => [                        // ajústelas al manual
            'usuario'       => 'UsuarioApi',
            'llave'         => 'LlaveApi',
            'identificador' => 'Identificador',
        ],
    ],
],
```

> **Confirme las URL y los nombres de cabecera contra el manual de su
> certificador.** Las que trae `config.example.php` son de referencia y cambian
> entre planes y versiones de API. Si el certificador le entrega otras, use las
> del manual: el sistema las toma de la configuración, no del código.

### Opción 2 — Cualquier otro certificador (adaptador REST genérico)

Sirve para Digifact, Guatefacturas, Megaprint o cualquiera que exponga una API
REST. Se declara la forma de la petición y el sistema la arma. En la pantalla
de la empresa, elija el proveedor **Otro certificador** y pegue esta estructura
en *"Ajustes adicionales en JSON"*.

```php
'certificador' => [
    'proveedor' => 'mi_certificador',

    'mi_certificador' => [

        // Paso 1: firma (si su certificador la ofrece como servicio)
        'firma' => [
            'habilitada'          => true,
            'url'                 => 'https://api.certificador.gt/firma',
            'formato'             => 'json',
            'cabeceras'           => ['Content-Type' => 'application/json'],
            'plantilla'           => [
                'llave'   => 'SU-LLAVE-DE-FIRMA',
                'alias'   => '12345679',
                'archivo' => '{XML_BASE64}',
            ],
            'campo_respuesta_xml' => 'archivo',
        ],

        // Paso 2: certificación
        'certificacion' => [
            'url'       => 'https://api.certificador.gt/certificar',
            'formato'   => 'xml',          // 'xml' | 'json' | 'base64'
            'cabeceras' => [
                'Usuario'       => 'SU-USUARIO',
                'Llave'         => 'SU-LLAVE-API',
                'Identificador' => '{IDENTIFICADOR}',
            ],
        ],

        // Paso 3: anulación (si usa otro endpoint)
        'anulacion' => [
            'url'       => 'https://api.certificador.gt/anular',
            'formato'   => 'xml',
            'cabeceras' => [
                'Usuario'       => 'SU-USUARIO',
                'Llave'         => 'SU-LLAVE-API',
                'Identificador' => '{IDENTIFICADOR}',
            ],
        ],
    ],
],
```

**Marcadores disponibles** en cualquier cabecera o campo de la plantilla:

| Marcador | Se reemplaza por |
|---|---|
| `{XML}` | El XML tal cual |
| `{XML_BASE64}` | El XML codificado en base64 |
| `{IDENTIFICADOR}` | El identificador interno del documento |

**Si usted firma por su cuenta** (tiene su propia llave y no usa el servicio de
firma del certificador), ponga `'firma' => ['habilitada' => false]`. El sistema
enviará el XML sin pasar por el paso de firma.

### Lectura de la respuesta

El sistema reconoce los nombres de campo más usados en Guatemala, así que en
general no hay que configurar nada:

- Éxito: `resultado`, `exito`, `success`
- Autorización: `uuid`, `numero_autorizacion`, `autorizacion`
- Serie y número: `serie`, `numero`
- XML certificado: `xml_certificado`, `xml_dte`, `archivo` (acepta base64)
- Errores: `descripcion`, `mensaje`, `descripcion_errores`, `errores`
  (incluyendo listas anidadas)

Si su certificador usa otros nombres, el lugar para agregarlos es
`src/Certificador/RespuestaJson.php`.

## Probar sin gastar folios

**Primero con el simulador.** Deje `'proveedor' => 'simulador'` y emita varios
documentos: verifique que los totales cuadran, que la representación gráfica
sale bien y que su gente sabe usar el sistema. El simulador no toca la red.

**Después con el ambiente de pruebas del certificador.** Cambie el proveedor y
apunte a las URL de pruebas. Emita al menos uno de cada tipo que vaya a usar:
factura, nota de crédito, anulación. Revise que el UUID que devuelven aparezca
en el sistema.

**Al final, producción.** Cambie las URL y credenciales, ponga
`'ambiente' => 'produccion'`, y emita **una** factura real de monto bajo a su
propio NIT. Búsquela en el verificador público de SAT:

```
https://felpub.c.sat.gob.gt/verificador-web/publico/vistas/verificacionDte.jsf
```

Si aparece ahí, la cadena completa está funcionando.

## El código QR de la factura

El QR que se imprime apunta, por omisión, al verificador público de SAT con el
número de autorización. Cada certificador publica su propia URL de consulta:
si el suyo le indica otra, cámbiela en `config/config.php`:

```php
'sat' => [
    'plantilla_qr' => 'https://la-url-de-su-certificador/consulta?uuid={UUID}',
],
```

Marcadores disponibles: `{UUID}`, `{NIT_EMISOR}`, `{NIT_RECEPTOR}`, `{MONTO}`,
`{FECHA}`, `{SERIE}`, `{NUMERO}`.

## Cambiar de certificador

Es solo cambiar el proveedor y las credenciales de la empresa desde la
pantalla. Los documentos ya emitidos conservan el nombre del certificador que los
certificó, que es lo correcto: cada DTE queda ligado a quien lo certificó.

## Diagnóstico

Cada intercambio con el certificador queda en dos lugares:

- **En la base**, tabla `fel_bitacora` — visible en el detalle de cada
  documento, en la aplicación.
- **En archivo**, `storage/logs/fel-AAAA-MM.log` — con las llaves enmascaradas.

Cuando el certificador rechaza algo, el mensaje exacto que devolvió queda en el
campo `error_mensaje` del documento y se muestra en pantalla. Ese es el texto
que hay que mandarle a su soporte técnico.
