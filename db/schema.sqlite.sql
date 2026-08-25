-- ---------------------------------------------------------------------------
-- Mismo esquema que db/schema.sql, en dialecto SQLite.
-- Solo se usa para las pruebas automatizadas (tests/run.php) y para probar
-- el sistema en una maquina sin MySQL. En produccion use db/schema.sql.
-- ---------------------------------------------------------------------------

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS fel_clientes (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
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
    documento_id INTEGER NULL,
    operacion    TEXT    NOT NULL,
    exito        INTEGER NOT NULL DEFAULT 0,
    mensaje      TEXT    NULL,
    respuesta    TEXT    NULL,
    creado_en    TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS fel_usuarios (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario    TEXT    NOT NULL UNIQUE,
    clave_hash TEXT    NOT NULL,
    nombre     TEXT    NOT NULL,
    rol        TEXT    NOT NULL DEFAULT 'operador',
    activo     INTEGER NOT NULL DEFAULT 1,
    creado_en  TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_clientes_identificador  ON fel_clientes (identificador);
CREATE INDEX IF NOT EXISTS idx_clientes_nombre         ON fel_clientes (nombre);
CREATE INDEX IF NOT EXISTS idx_productos_codigo        ON fel_productos (codigo);
CREATE INDEX IF NOT EXISTS idx_productos_descripcion   ON fel_productos (descripcion);
CREATE INDEX IF NOT EXISTS idx_documentos_uuid         ON fel_documentos (uuid);
CREATE INDEX IF NOT EXISTS idx_documentos_estado       ON fel_documentos (estado);
CREATE INDEX IF NOT EXISTS idx_documentos_fecha        ON fel_documentos (fecha_emision);
CREATE INDEX IF NOT EXISTS idx_documentos_receptor     ON fel_documentos (receptor_id);
CREATE INDEX IF NOT EXISTS idx_items_documento         ON fel_documento_items (documento_id);
CREATE INDEX IF NOT EXISTS idx_anulaciones_documento   ON fel_anulaciones (documento_id);
CREATE INDEX IF NOT EXISTS idx_bitacora_documento      ON fel_bitacora (documento_id);
CREATE INDEX IF NOT EXISTS idx_bitacora_fecha          ON fel_bitacora (creado_en);
