-- ---------------------------------------------------------------------------
-- Mismo esquema que db/schema.sql, en dialecto SQLite.
-- Solo se usa para las pruebas automatizadas (tests/run.php) y para probar
-- el sistema en una maquina sin MySQL. En produccion use db/schema.sql.
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS fel_empresas (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_interno          TEXT    NOT NULL,
    nit                     TEXT    NOT NULL,
    nombre_emisor           TEXT    NOT NULL,
    nombre_comercial        TEXT    NOT NULL,
    afiliacion_iva          TEXT    NOT NULL DEFAULT 'GEN',
    codigo_establecimiento  TEXT    NOT NULL DEFAULT '1',
    correo                  TEXT    NOT NULL DEFAULT '',
    telefono                TEXT    NOT NULL DEFAULT '',
    direccion               TEXT    NOT NULL DEFAULT 'Ciudad',
    codigo_postal           TEXT    NOT NULL DEFAULT '01001',
    municipio               TEXT    NOT NULL DEFAULT 'Guatemala',
    departamento            TEXT    NOT NULL DEFAULT 'Guatemala',
    pais                    TEXT    NOT NULL DEFAULT 'GT',
    ambiente                TEXT    NOT NULL DEFAULT 'pruebas',
    certificador_proveedor  TEXT    NOT NULL DEFAULT 'simulador',
    certificador_config     TEXT    NULL,
    certificador_nombre     TEXT    NOT NULL DEFAULT '',
    certificador_nit        TEXT    NOT NULL DEFAULT '',
    formato_impresion       TEXT    NOT NULL DEFAULT 'carta',
    color_marca             TEXT    NOT NULL DEFAULT '#0f5f8a',
    logo                    TEXT    NULL,
    limite_consumidor_final REAL    NOT NULL DEFAULT 2500.00,
    dias_maximos_anulacion  INTEGER NOT NULL DEFAULT 30,
    plan                    TEXT    NOT NULL DEFAULT '',
    notas                   TEXT    NOT NULL DEFAULT '',
    activa                  INTEGER NOT NULL DEFAULT 1,
    creado_en               TEXT    NOT NULL,
    actualizado_en          TEXT    NOT NULL,
    UNIQUE (nit, codigo_establecimiento)
);

CREATE TABLE IF NOT EXISTS fel_clientes (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    empresa_id     INTEGER NOT NULL REFERENCES fel_empresas (id) ON DELETE CASCADE,
    identificador  TEXT    NOT NULL DEFAULT 'CF',
    tipo_especial  TEXT    NOT NULL DEFAULT '',
    nombre         TEXT    NOT NULL,
    correo         TEXT    NOT NULL DEFAULT '',
    telefono       TEXT    NOT NULL DEFAULT '',
    direccion      TEXT    NOT NULL DEFAULT 'Ciudad',
    codigo_postal  TEXT    NOT NULL DEFAULT '01001',
    municipio      TEXT    NOT NULL DEFAULT 'Guatemala',
    departamento   TEXT    NOT NULL DEFAULT 'Guatemala',
    pais           TEXT    NOT NULL DEFAULT 'GT',
    activo         INTEGER NOT NULL DEFAULT 1,
    creado_en      TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS fel_productos (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    empresa_id      INTEGER NOT NULL REFERENCES fel_empresas (id) ON DELETE CASCADE,
    codigo          TEXT    NOT NULL DEFAULT '',
    descripcion     TEXT    NOT NULL,
    tipo            TEXT    NOT NULL DEFAULT 'B',
    unidad_medida   TEXT    NOT NULL DEFAULT 'UNI',
    precio_unitario REAL    NOT NULL DEFAULT 0,
    exento          INTEGER NOT NULL DEFAULT 0,
    activo          INTEGER NOT NULL DEFAULT 1,
    creado_en       TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS fel_documentos (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    empresa_id          INTEGER NOT NULL REFERENCES fel_empresas (id) ON DELETE CASCADE,
    identificador       TEXT    NOT NULL UNIQUE,
    tipo                TEXT    NOT NULL,
    estado              TEXT    NOT NULL DEFAULT 'BORRADOR',
    moneda              TEXT    NOT NULL DEFAULT 'GTQ',
    tipo_cambio         REAL    NOT NULL DEFAULT 1,
    fecha_emision       TEXT    NOT NULL,

    emisor_nit          TEXT    NOT NULL,
    emisor_nombre       TEXT    NOT NULL,
    establecimiento     TEXT    NOT NULL DEFAULT '1',

    receptor_id         TEXT    NOT NULL DEFAULT 'CF',
    receptor_nombre     TEXT    NOT NULL DEFAULT 'Consumidor Final',
    receptor_correo     TEXT    NOT NULL DEFAULT '',
    cliente_id          INTEGER NULL,

    total_gravable      REAL    NOT NULL DEFAULT 0,
    total_descuentos    REAL    NOT NULL DEFAULT 0,
    total_iva           REAL    NOT NULL DEFAULT 0,
    gran_total          REAL    NOT NULL DEFAULT 0,

    uuid                TEXT    NOT NULL DEFAULT '',
    serie               TEXT    NOT NULL DEFAULT '',
    numero              TEXT    NOT NULL DEFAULT '',
    fecha_certificacion TEXT    NOT NULL DEFAULT '',
    certificador        TEXT    NOT NULL DEFAULT '',

    xml_enviado         TEXT    NULL,
    xml_certificado     TEXT    NULL,
    error_mensaje       TEXT    NULL,
    intentos            INTEGER NOT NULL DEFAULT 0,

    referencia_interna  TEXT    NOT NULL DEFAULT '',
    observaciones       TEXT    NOT NULL DEFAULT '',
    creado_por          TEXT    NOT NULL DEFAULT '',
    creado_en           TEXT    NOT NULL,
    actualizado_en      TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS fel_documento_items (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    documento_id    INTEGER NOT NULL REFERENCES fel_documentos (id) ON DELETE CASCADE,
    numero_linea    INTEGER NOT NULL,
    tipo            TEXT    NOT NULL DEFAULT 'B',
    descripcion     TEXT    NOT NULL,
    cantidad        REAL    NOT NULL DEFAULT 1,
    unidad_medida   TEXT    NOT NULL DEFAULT 'UNI',
    precio_unitario REAL    NOT NULL DEFAULT 0,
    precio          REAL    NOT NULL DEFAULT 0,
    descuento       REAL    NOT NULL DEFAULT 0,
    total           REAL    NOT NULL DEFAULT 0,
    monto_gravable  REAL    NOT NULL DEFAULT 0,
    monto_impuesto  REAL    NOT NULL DEFAULT 0,
    exento          INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS fel_anulaciones (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    documento_id    INTEGER NOT NULL REFERENCES fel_documentos (id) ON DELETE CASCADE,
    motivo          TEXT    NOT NULL,
    estado          TEXT    NOT NULL DEFAULT 'PENDIENTE',
    uuid_anulacion  TEXT    NOT NULL DEFAULT '',
    fecha_anulacion TEXT    NOT NULL DEFAULT '',
    xml_enviado     TEXT    NULL,
    xml_respuesta   TEXT    NULL,
    error_mensaje   TEXT    NULL,
    creado_por      TEXT    NOT NULL DEFAULT '',
    creado_en       TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS fel_bitacora (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    empresa_id   INTEGER NULL,
    documento_id INTEGER NULL,
    operacion    TEXT    NOT NULL,
    exito        INTEGER NOT NULL DEFAULT 0,
    mensaje      TEXT    NULL,
    respuesta    TEXT    NULL,
    creado_en    TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS fel_usuarios (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    empresa_id INTEGER NULL REFERENCES fel_empresas (id) ON DELETE CASCADE,
    usuario    TEXT    NOT NULL UNIQUE,
    clave_hash TEXT    NOT NULL,
    nombre     TEXT    NOT NULL,
    rol        TEXT    NOT NULL DEFAULT 'operador',
    activo     INTEGER NOT NULL DEFAULT 1,
    creado_en  TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_clientes_empresa       ON fel_clientes (empresa_id);
CREATE INDEX IF NOT EXISTS idx_clientes_identificador  ON fel_clientes (empresa_id, identificador);
CREATE INDEX IF NOT EXISTS idx_clientes_nombre         ON fel_clientes (nombre);
CREATE INDEX IF NOT EXISTS idx_productos_empresa      ON fel_productos (empresa_id);
CREATE INDEX IF NOT EXISTS idx_productos_codigo        ON fel_productos (empresa_id, codigo);
CREATE INDEX IF NOT EXISTS idx_productos_descripcion   ON fel_productos (descripcion);
CREATE INDEX IF NOT EXISTS idx_documentos_uuid         ON fel_documentos (uuid);
CREATE INDEX IF NOT EXISTS idx_documentos_empresa     ON fel_documentos (empresa_id);
CREATE INDEX IF NOT EXISTS idx_documentos_estado       ON fel_documentos (empresa_id, estado);
CREATE INDEX IF NOT EXISTS idx_documentos_fecha        ON fel_documentos (empresa_id, fecha_emision);
CREATE INDEX IF NOT EXISTS idx_documentos_receptor     ON fel_documentos (receptor_id);
CREATE INDEX IF NOT EXISTS idx_items_documento         ON fel_documento_items (documento_id);
CREATE INDEX IF NOT EXISTS idx_anulaciones_documento   ON fel_anulaciones (documento_id);
CREATE INDEX IF NOT EXISTS idx_bitacora_documento      ON fel_bitacora (documento_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_empresa        ON fel_usuarios (empresa_id);
CREATE INDEX IF NOT EXISTS idx_bitacora_fecha          ON fel_bitacora (creado_en);
