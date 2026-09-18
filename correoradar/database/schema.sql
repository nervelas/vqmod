-- ============================================================================
--  CorreoRadar - Esquema de base de datos (MySQL 5.7+ / MariaDB 10.2+)
--  El instalador (install.php) ejecuta este archivo automáticamente.
--  También puede importarse a mano desde phpMyAdmin si se prefiere.
-- ============================================================================

-- Ajustes del sitio (clave/valor). 100% administrable desde el panel.
CREATE TABLE IF NOT EXISTS `cr_ajustes` (
  `clave`       VARCHAR(64)  NOT NULL,
  `valor`       MEDIUMTEXT   NULL,
  `actualizado` DATETIME     NOT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarios: administradores y cuentas públicas.
CREATE TABLE IF NOT EXISTS `cr_usuarios` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario`       VARCHAR(64)  NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `nombre`        VARCHAR(120) NULL,
  `clave_hash`    VARCHAR(255) NOT NULL,
  `rol`           ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
  `activo`        TINYINT(1)   NOT NULL DEFAULT 1,
  `creado`        DATETIME     NOT NULL,
  `ultimo_acceso` DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario` (`usuario`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cabecera de cada extracción realizada.
CREATE TABLE IF NOT EXISTS `cr_escaneos` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token`           CHAR(32)     NOT NULL,
  `usuario_id`      INT UNSIGNED NULL,
  `url_origen`      VARCHAR(1000) NOT NULL,
  `host`            VARCHAR(190) NOT NULL,
  `profundo`        TINYINT(1)   NOT NULL DEFAULT 0,
  `max_paginas`     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `max_profundidad` TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `estado`          ENUM('en_cola','ejecutando','completado','error','cancelado') NOT NULL DEFAULT 'en_cola',
  `paginas_ok`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `paginas_error`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `correos`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `ip`              VARCHAR(45)  NULL,
  `agente`          VARCHAR(255) NULL,
  `mensaje`         VARCHAR(500) NULL,
  `telefonos_n`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `whatsapps`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `enlaces_wa`      TEXT         NULL,
  `redes`           TEXT         NULL,
  `inicio`          DATETIME     NOT NULL,
  `fin`             DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_host` (`host`),
  KEY `idx_inicio` (`inicio`),
  KEY `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cola de páginas/recursos por escaneo (motor de rastreo profundo).
CREATE TABLE IF NOT EXISTS `cr_cola` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `escaneo_id`   INT UNSIGNED NOT NULL,
  `url`          VARCHAR(1000) NOT NULL,
  `url_hash`     CHAR(40)     NOT NULL,
  `tipo`         ENUM('pagina','recurso','sitemap') NOT NULL DEFAULT 'pagina',
  `profundidad`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `prioridad`    TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `estado`       ENUM('pendiente','procesando','hecho','error') NOT NULL DEFAULT 'pendiente',
  `http_codigo`  SMALLINT NULL,
  `correos`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `creado`       DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cola` (`escaneo_id`,`url_hash`),
  KEY `idx_pendiente` (`escaneo_id`,`estado`,`prioridad`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Correos encontrados (únicos por escaneo).
CREATE TABLE IF NOT EXISTS `cr_correos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `escaneo_id` INT UNSIGNED NOT NULL,
  `correo`     VARCHAR(190) NOT NULL,
  `dominio`    VARCHAR(190) NOT NULL,
  `url_origen` VARCHAR(1000) NOT NULL,
  `metodo`     VARCHAR(190) NOT NULL,
  `tipo`       ENUM('generico','personal') NOT NULL DEFAULT 'personal',
  `confianza`  TINYINT UNSIGNED NOT NULL DEFAULT 60,
  `mx`         TINYINT(1)   NULL,
  `veces`      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `detectado`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_correo` (`escaneo_id`,`correo`),
  KEY `idx_dominio` (`dominio`),
  KEY `idx_escaneo` (`escaneo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teléfonos y números de WhatsApp encontrados (únicos por escaneo).
CREATE TABLE IF NOT EXISTS `cr_telefonos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `escaneo_id` INT UNSIGNED NOT NULL,
  `numero`     VARCHAR(20)  NOT NULL,          -- formato internacional E.164
  `formato`    VARCHAR(32)  NOT NULL,          -- versión legible: +502 2222 3333
  `pais`       VARCHAR(60)  NOT NULL,
  `iso`        CHAR(2)      NOT NULL DEFAULT '',
  `whatsapp`   TINYINT(1)   NOT NULL DEFAULT 0,
  `url_origen` VARCHAR(1000) NOT NULL,
  `metodo`     VARCHAR(190) NOT NULL,
  `confianza`  TINYINT UNSIGNED NOT NULL DEFAULT 60,
  `veces`      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `detectado`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_telefono` (`escaneo_id`,`numero`),
  KEY `idx_wa` (`whatsapp`),
  KEY `idx_escaneo_tel` (`escaneo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de peticiones para el límite por IP.
CREATE TABLE IF NOT EXISTS `cr_peticiones` (
  `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`     VARCHAR(45) NOT NULL,
  `accion` VARCHAR(32) NOT NULL DEFAULT 'escaneo',
  `creado` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip`,`accion`,`creado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intentos de acceso (anti fuerza bruta).
CREATE TABLE IF NOT EXISTS `cr_intentos` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`      VARCHAR(45) NOT NULL,
  `usuario` VARCHAR(64) NULL,
  `exito`   TINYINT(1) NOT NULL DEFAULT 0,
  `creado`  DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_fecha` (`ip`,`creado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache de comprobacion MX por dominio (evita consultas DNS repetidas).
CREATE TABLE IF NOT EXISTS `cr_dns` (
  `dominio`  VARCHAR(190) NOT NULL,
  `mx`       TINYINT(1) NOT NULL DEFAULT 0,
  `revisado` DATETIME NOT NULL,
  PRIMARY KEY (`dominio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
--  MÓDULO DE CAMPAÑAS DE CORREO
-- ============================================================================

-- Buzones de salida (cuentas SMTP del propio dominio).
CREATE TABLE IF NOT EXISTS `cr_remitentes` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`          VARCHAR(120) NOT NULL,           -- etiqueta interna
  `de_correo`       VARCHAR(190) NOT NULL,           -- info@midominio.com
  `de_nombre`       VARCHAR(120) NOT NULL DEFAULT '',
  `responder_a`     VARCHAR(190) NOT NULL DEFAULT '',
  `host`            VARCHAR(190) NOT NULL,
  `puerto`          SMALLINT UNSIGNED NOT NULL DEFAULT 587,
  `seguridad`       ENUM('tls','ssl','ninguna') NOT NULL DEFAULT 'tls',
  `usuario`         VARCHAR(190) NOT NULL,
  `clave`           VARBINARY(1024) NOT NULL,        -- cifrada con la clave de la app
  `limite_hora`     SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  `limite_dia`      SMALLINT UNSIGNED NOT NULL DEFAULT 300,
  `activo`          TINYINT(1)   NOT NULL DEFAULT 1,
  `ultimo_error`    VARCHAR(500) NULL,
  `probado_en`      DATETIME     NULL,
  `creado`          DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listas de contactos.
CREATE TABLE IF NOT EXISTS `cr_listas` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(160) NOT NULL,
  `descripcion` VARCHAR(500) NULL,
  `contactos`   MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  `creado`      DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contactos de cada lista.
CREATE TABLE IF NOT EXISTS `cr_contactos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lista_id`   INT UNSIGNED NOT NULL,
  `correo`     VARCHAR(190) NOT NULL,
  `nombre`     VARCHAR(160) NOT NULL DEFAULT '',
  `centro`     VARCHAR(190) NOT NULL DEFAULT '',     -- nombre del colegio o empresa
  `dominio`    VARCHAR(190) NOT NULL DEFAULT '',
  `telefono`   VARCHAR(24)  NOT NULL DEFAULT '',
  `origen`     VARCHAR(255) NOT NULL DEFAULT '',     -- URL de donde salió
  `estado`     ENUM('activo','baja','rebotado','suprimido') NOT NULL DEFAULT 'activo',
  `creado`     DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lista_correo` (`lista_id`,`correo`),
  KEY `idx_estado` (`lista_id`,`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lista de supresión global: nunca se escribe a estas direcciones.
CREATE TABLE IF NOT EXISTS `cr_supresion` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `correo`   VARCHAR(190) NOT NULL,
  `motivo`   ENUM('baja','rebote','queja','manual','importada') NOT NULL DEFAULT 'manual',
  `detalle`  VARCHAR(400) NULL,
  `creado`   DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_supresion` (`correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plantillas de mensaje.
CREATE TABLE IF NOT EXISTS `cr_plantillas` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(160) NOT NULL,
  `asunto`      VARCHAR(300) NOT NULL,
  `cuerpo`      MEDIUMTEXT   NOT NULL,
  `creado`      DATETIME NOT NULL,
  `actualizado` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campañas.
CREATE TABLE IF NOT EXISTS `cr_campanas` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(160) NOT NULL,
  `lista_id`      INT UNSIGNED NOT NULL,
  `plantilla_id`  INT UNSIGNED NOT NULL,
  `remitentes`    VARCHAR(190) NOT NULL DEFAULT '',  -- ids separados por coma
  `estado`        ENUM('borrador','preparada','enviando','pausada','completada','cancelada') NOT NULL DEFAULT 'borrador',
  `limite_hora`   SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  `pausa_min`     SMALLINT UNSIGNED NOT NULL DEFAULT 8,   -- segundos entre envíos
  `pausa_max`     SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `total`         MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  `enviados`      MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  `errores`       MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  `rebotes`       MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  `aperturas`     MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  `clics`         MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  `bajas`         MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
  `creado`        DATETIME NOT NULL,
  `iniciada`      DATETIME NULL,
  `finalizada`    DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estado_camp` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un registro por destinatario y campaña.
CREATE TABLE IF NOT EXISTS `cr_envios` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campana_id`   INT UNSIGNED NOT NULL,
  `contacto_id`  INT UNSIGNED NOT NULL,
  `correo`       VARCHAR(190) NOT NULL,
  `remitente_id` INT UNSIGNED NULL,
  `token`        CHAR(32)     NOT NULL,
  `estado`       ENUM('pendiente','enviado','error','rebotado','cancelado') NOT NULL DEFAULT 'pendiente',
  `intentos`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `error`        VARCHAR(400) NULL,
  `aperturas`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `clics`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `enviado_en`   DATETIME NULL,
  `abierto_en`   DATETIME NULL,
  `creado`       DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_envio` (`token`),
  UNIQUE KEY `uq_campana_contacto` (`campana_id`,`contacto_id`),
  KEY `idx_pendientes` (`campana_id`,`estado`,`id`),
  KEY `idx_enviados` (`remitente_id`,`enviado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
