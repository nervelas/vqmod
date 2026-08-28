-- ---------------------------------------------------------------------------
-- Migracion 002: de un solo emisor a multiples empresas
--
-- Aplique este archivo SOLO si ya tenia el sistema funcionando con la version
-- anterior (un solo emisor definido en config/config.php) y quiere conservar
-- los documentos ya emitidos. En una instalacion nueva use db/schema.sql.
--
-- Antes de ejecutar: RESPALDE la base de datos.
--   cPanel > phpMyAdmin > su base > Exportar
--
-- Despues de ejecutar, corra:  php bin/migrar_multiempresa.php
-- que crea la empresa a partir de config.php y le asigna los datos existentes.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

-- 1. Tabla de empresas
CREATE TABLE IF NOT EXISTS fel_empresas (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_interno          VARCHAR(120) NOT NULL,
    nit                     VARCHAR(25)  NOT NULL,
    nombre_emisor           VARCHAR(255) NOT NULL,
    nombre_comercial        VARCHAR(255) NOT NULL,
    afiliacion_iva          VARCHAR(5)   NOT NULL DEFAULT 'GEN',
    codigo_establecimiento  VARCHAR(10)  NOT NULL DEFAULT '1',
    correo                  VARCHAR(255) NOT NULL DEFAULT '',
    telefono                VARCHAR(50)  NOT NULL DEFAULT '',
    direccion               VARCHAR(255) NOT NULL DEFAULT 'Ciudad',
    codigo_postal           VARCHAR(10)  NOT NULL DEFAULT '01001',
    municipio               VARCHAR(100) NOT NULL DEFAULT 'Guatemala',
    departamento            VARCHAR(100) NOT NULL DEFAULT 'Guatemala',
    pais                    VARCHAR(3)   NOT NULL DEFAULT 'GT',
    ambiente                VARCHAR(15)  NOT NULL DEFAULT 'pruebas',
    certificador_proveedor  VARCHAR(50)  NOT NULL DEFAULT 'simulador',
    certificador_config     TEXT         NULL,
    certificador_nombre     VARCHAR(255) NOT NULL DEFAULT '',
    certificador_nit        VARCHAR(25)  NOT NULL DEFAULT '',
    formato_impresion       VARCHAR(10)  NOT NULL DEFAULT 'carta',
    color_marca             VARCHAR(9)   NOT NULL DEFAULT '#0f5f8a',
    logo                    MEDIUMTEXT   NULL,
    limite_consumidor_final DECIMAL(18,2) NOT NULL DEFAULT 2500.00,
    dias_maximos_anulacion  INT UNSIGNED NOT NULL DEFAULT 30,
    plan                    VARCHAR(40)  NOT NULL DEFAULT '',
    notas                   VARCHAR(500) NOT NULL DEFAULT '',
    activa                  TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en               DATETIME     NOT NULL,
    actualizado_en          DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_empresas_nit_establecimiento (nit, codigo_establecimiento),
    KEY idx_empresas_nombre (nombre_interno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Columna empresa_id en las tablas existentes.
--    Se agrega con valor 1 por omision: bin/migrar_multiempresa.php crea la
--    empresa a partir de config.php, de modo que todo lo ya emitido queda
--    correctamente asignado a ella.
ALTER TABLE fel_documentos ADD COLUMN empresa_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE fel_clientes   ADD COLUMN empresa_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE fel_productos  ADD COLUMN empresa_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE fel_bitacora   ADD COLUMN empresa_id INT UNSIGNED NULL DEFAULT 1 AFTER id;
ALTER TABLE fel_usuarios   ADD COLUMN empresa_id INT UNSIGNED NULL AFTER id;

-- 3. Indices por empresa
ALTER TABLE fel_documentos ADD KEY idx_documentos_empresa (empresa_id);
ALTER TABLE fel_clientes   ADD KEY idx_clientes_empresa (empresa_id);
ALTER TABLE fel_productos  ADD KEY idx_productos_empresa (empresa_id);
ALTER TABLE fel_bitacora   ADD KEY idx_bitacora_empresa (empresa_id);
ALTER TABLE fel_usuarios   ADD KEY idx_usuarios_empresa (empresa_id);

-- 4. El usuario administrador existente pasa a ser administrador de la
--    plataforma (ve todas las empresas).
UPDATE fel_usuarios SET rol = 'superadmin', empresa_id = NULL WHERE rol = 'admin';

-- 5. Los demas usuarios quedan atados a la empresa 1.
UPDATE fel_usuarios SET empresa_id = 1 WHERE rol <> 'superadmin' AND empresa_id IS NULL;
