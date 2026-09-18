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
