-- ---------------------------------------------------------------------------
-- Sistema de facturacion FEL - Guatemala
-- Esquema para MySQL / MariaDB (compatible con cPanel y phpMyAdmin)
--
-- Importacion: cPanel > phpMyAdmin > seleccione su base de datos > Importar
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

-- Clientes (receptores del DTE)
CREATE TABLE IF NOT EXISTS fel_clientes (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    identificador  VARCHAR(25)  NOT NULL DEFAULT 'CF' COMMENT 'NIT, CF, CUI o pasaporte',
    tipo_especial  VARCHAR(10)  NOT NULL DEFAULT ''   COMMENT 'Vacio=NIT, CUI, EXT',
    nombre         VARCHAR(255) NOT NULL,
    correo         VARCHAR(255) NOT NULL DEFAULT '',
    telefono       VARCHAR(50)  NOT NULL DEFAULT '',
    direccion      VARCHAR(255) NOT NULL DEFAULT 'Ciudad',
    codigo_postal  VARCHAR(10)  NOT NULL DEFAULT '01001',
    municipio      VARCHAR(100) NOT NULL DEFAULT 'Guatemala',
    departamento   VARCHAR(100) NOT NULL DEFAULT 'Guatemala',
    pais           VARCHAR(3)   NOT NULL DEFAULT 'GT',
    activo         TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_clientes_identificador (identificador),
    KEY idx_clientes_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogo de productos y servicios
CREATE TABLE IF NOT EXISTS fel_productos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo          VARCHAR(50)  NOT NULL DEFAULT '',
    descripcion     VARCHAR(255) NOT NULL,
    tipo            CHAR(1)      NOT NULL DEFAULT 'B' COMMENT 'B=Bien, S=Servicio',
    unidad_medida   VARCHAR(10)  NOT NULL DEFAULT 'UNI',
    precio_unitario DECIMAL(18,6) NOT NULL DEFAULT 0 COMMENT 'Precio de venta CON IVA incluido',
    exento          TINYINT(1)   NOT NULL DEFAULT 0,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en       DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_productos_codigo (codigo),
    KEY idx_productos_descripcion (descripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documentos tributarios electronicos
CREATE TABLE IF NOT EXISTS fel_documentos (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    identificador       CHAR(36)     NOT NULL COMMENT 'Identificador interno enviado al certificador (idempotencia)',
    tipo                VARCHAR(6)   NOT NULL COMMENT 'FACT, FPEQ, NCRE, ...',
    estado              VARCHAR(20)  NOT NULL DEFAULT 'BORRADOR'
                        COMMENT 'BORRADOR, PENDIENTE, CERTIFICADO, RECHAZADO, ANULADO',
    moneda              VARCHAR(3)   NOT NULL DEFAULT 'GTQ',
    tipo_cambio         DECIMAL(18,6) NOT NULL DEFAULT 1,
    fecha_emision       VARCHAR(30)  NOT NULL COMMENT 'ISO-8601 con offset, tal como viaja en el XML',

    emisor_nit          VARCHAR(25)  NOT NULL,
    emisor_nombre       VARCHAR(255) NOT NULL,
    establecimiento     VARCHAR(10)  NOT NULL DEFAULT '1',

    receptor_id         VARCHAR(25)  NOT NULL DEFAULT 'CF',
    receptor_nombre     VARCHAR(255) NOT NULL DEFAULT 'Consumidor Final',
    receptor_correo     VARCHAR(255) NOT NULL DEFAULT '',
    cliente_id          INT UNSIGNED NULL,

    total_gravable      DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_descuentos    DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_iva           DECIMAL(18,2) NOT NULL DEFAULT 0,
    gran_total          DECIMAL(18,2) NOT NULL DEFAULT 0,

    uuid                VARCHAR(50)  NOT NULL DEFAULT '' COMMENT 'Numero de autorizacion asignado por SAT',
    serie               VARCHAR(30)  NOT NULL DEFAULT '',
    numero              VARCHAR(30)  NOT NULL DEFAULT '',
    fecha_certificacion VARCHAR(30)  NOT NULL DEFAULT '',
    certificador        VARCHAR(50)  NOT NULL DEFAULT '',

    xml_enviado         MEDIUMTEXT   NULL,
    xml_certificado     MEDIUMTEXT   NULL,
    error_mensaje       TEXT         NULL,
    intentos            INT UNSIGNED NOT NULL DEFAULT 0,

    referencia_interna  VARCHAR(100) NOT NULL DEFAULT '',
    observaciones       VARCHAR(500) NOT NULL DEFAULT '',
    creado_por          VARCHAR(100) NOT NULL DEFAULT '',
    creado_en           DATETIME     NOT NULL,
    actualizado_en      DATETIME     NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_documentos_identificador (identificador),
    KEY idx_documentos_uuid (uuid),
    KEY idx_documentos_estado (estado),
    KEY idx_documentos_fecha (fecha_emision),
    KEY idx_documentos_receptor (receptor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalle de cada documento
CREATE TABLE IF NOT EXISTS fel_documento_items (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    documento_id    INT UNSIGNED NOT NULL,
    numero_linea    INT UNSIGNED NOT NULL,
    tipo            CHAR(1)      NOT NULL DEFAULT 'B',
    descripcion     VARCHAR(255) NOT NULL,
    cantidad        DECIMAL(18,6) NOT NULL DEFAULT 1,
    unidad_medida   VARCHAR(10)  NOT NULL DEFAULT 'UNI',
    precio_unitario DECIMAL(18,6) NOT NULL DEFAULT 0,
    precio          DECIMAL(18,2) NOT NULL DEFAULT 0,
    descuento       DECIMAL(18,2) NOT NULL DEFAULT 0,
    total           DECIMAL(18,2) NOT NULL DEFAULT 0,
    monto_gravable  DECIMAL(18,2) NOT NULL DEFAULT 0,
    monto_impuesto  DECIMAL(18,2) NOT NULL DEFAULT 0,
    exento          TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_items_documento (documento_id),
    CONSTRAINT fk_items_documento FOREIGN KEY (documento_id)
        REFERENCES fel_documentos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anulaciones enviadas a SAT
CREATE TABLE IF NOT EXISTS fel_anulaciones (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    documento_id   INT UNSIGNED NOT NULL,
    motivo         VARCHAR(255) NOT NULL,
    estado         VARCHAR(20)  NOT NULL DEFAULT 'PENDIENTE',
    uuid_anulacion VARCHAR(50)  NOT NULL DEFAULT '',
    fecha_anulacion VARCHAR(30) NOT NULL DEFAULT '',
    xml_enviado    MEDIUMTEXT   NULL,
    xml_respuesta  MEDIUMTEXT   NULL,
    error_mensaje  TEXT         NULL,
    creado_por     VARCHAR(100) NOT NULL DEFAULT '',
    creado_en      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_anulaciones_documento (documento_id),
    CONSTRAINT fk_anulaciones_documento FOREIGN KEY (documento_id)
        REFERENCES fel_documentos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bitacora de comunicacion con el certificador (respaldo ante una revision de SAT)
CREATE TABLE IF NOT EXISTS fel_bitacora (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    documento_id  INT UNSIGNED NULL,
    operacion     VARCHAR(30)  NOT NULL COMMENT 'FIRMA, CERTIFICACION, ANULACION',
    exito         TINYINT(1)   NOT NULL DEFAULT 0,
    mensaje       TEXT         NULL,
    respuesta     MEDIUMTEXT   NULL,
    creado_en     DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_bitacora_documento (documento_id),
    KEY idx_bitacora_fecha (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarios del sistema
CREATE TABLE IF NOT EXISTS fel_usuarios (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario    VARCHAR(60)  NOT NULL,
    clave_hash VARCHAR(255) NOT NULL,
    nombre     VARCHAR(120) NOT NULL,
    rol        VARCHAR(20)  NOT NULL DEFAULT 'operador' COMMENT 'admin, operador',
    activo     TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
