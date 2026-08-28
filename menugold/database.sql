-- =====================================================================
--  MenuGold - Estructura de base de datos
--  Sistema de Menu Digital QR con Pedidos para Restaurantes (multi-restaurante)
--  MySQL 8 / MariaDB 10.4+   ·   utf8mb4_unicode_ci
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  PLATAFORMA
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `plans` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`          VARCHAR(60)  NOT NULL,
  `slug`            VARCHAR(60)  NOT NULL,
  `descripcion`     VARCHAR(255) NOT NULL DEFAULT '',
  `precio_mensual`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `precio_anual`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_productos`   INT NOT NULL DEFAULT 50,     -- 0 = ilimitado
  `max_mesas`       INT NOT NULL DEFAULT 10,
  `max_sucursales`  INT NOT NULL DEFAULT 1,
  `max_usuarios`    INT NOT NULL DEFAULT 3,
  `caracteristicas` TEXT NULL,                   -- JSON: lista de textos
  `destacado`       TINYINT(1) NOT NULL DEFAULT 0,
  `orden`           INT NOT NULL DEFAULT 0,
  `activo`          TINYINT(1) NOT NULL DEFAULT 1,
  `creado`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `platform_settings` (
  `clave` VARCHAR(60) NOT NULL,
  `valor` MEDIUMTEXT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `restaurants` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`            VARCHAR(60)  NOT NULL,
  `nombre`          VARCHAR(120) NOT NULL,
  `eslogan`         VARCHAR(180) NOT NULL DEFAULT '',
  `descripcion`     TEXT NULL,
  `plan_id`         INT UNSIGNED NULL,
  `estado`          ENUM('activo','suspendido','prueba') NOT NULL DEFAULT 'prueba',
  `vence_el`        DATE NULL,
  `dominio`         VARCHAR(190) NULL,           -- dominio o subdominio propio
  `logo`            VARCHAR(190) NOT NULL DEFAULT '',
  `portada`         VARCHAR(190) NOT NULL DEFAULT '',
  `tema`            VARCHAR(30)  NOT NULL DEFAULT 'negro-oro',
  `color_primario`  VARCHAR(9)   NOT NULL DEFAULT '#D4AF37',
  `color_fondo`     VARCHAR(9)   NOT NULL DEFAULT '#141414',
  `tipografia`      ENUM('clasica','moderna','editorial') NOT NULL DEFAULT 'clasica',
  `moneda`          VARCHAR(6)   NOT NULL DEFAULT 'GTQ',
  `simbolo`         VARCHAR(4)   NOT NULL DEFAULT 'Q',
  `impuesto_pct`    DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `impuesto_incluido` TINYINT(1) NOT NULL DEFAULT 1,
  `propina_sugerida` VARCHAR(60) NOT NULL DEFAULT '[0,10,15]',
  `telefono`        VARCHAR(30)  NOT NULL DEFAULT '',
  `whatsapp`        VARCHAR(30)  NOT NULL DEFAULT '',
  `email`           VARCHAR(190) NOT NULL DEFAULT '',
  `direccion`       VARCHAR(255) NOT NULL DEFAULT '',
  `mapa_lat`        DECIMAL(10,7) NULL,
  `mapa_lng`        DECIMAL(10,7) NULL,
  `facebook`        VARCHAR(190) NOT NULL DEFAULT '',
  `instagram`       VARCHAR(190) NOT NULL DEFAULT '',
  `tiktok`          VARCHAR(190) NOT NULL DEFAULT '',
  `google_reviews`  VARCHAR(255) NOT NULL DEFAULT '',
  `link_pago`       VARCHAR(255) NOT NULL DEFAULT '',
  `datos_bancarios` TEXT NULL,
  `modos_pedido`    VARCHAR(120) NOT NULL DEFAULT 'consulta,mesa',  -- consulta,mesa,llevar,delivery,whatsapp
  `metodos_pago`    VARCHAR(120) NOT NULL DEFAULT 'efectivo,tarjeta',
  `idioma`          VARCHAR(5)   NOT NULL DEFAULT 'es',
  `idiomas`         VARCHAR(30)  NOT NULL DEFAULT 'es',
  `abierto_modo`    ENUM('auto','abierto','cerrado') NOT NULL DEFAULT 'auto',
  `mensaje_bienvenida` VARCHAR(255) NOT NULL DEFAULT '',
  `mensaje_pie`     VARCHAR(255) NOT NULL DEFAULT '',
  `seo_title`       VARCHAR(190) NOT NULL DEFAULT '',
  `seo_desc`        VARCHAR(255) NOT NULL DEFAULT '',
  `og_image`        VARCHAR(190) NOT NULL DEFAULT '',
  `tiempo_prep_min` INT NOT NULL DEFAULT 20,
  `pedido_minimo`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notas_activas`   TINYINT(1) NOT NULL DEFAULT 1,
  `demo`            TINYINT(1) NOT NULL DEFAULT 0,
  `creado`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado`     DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rest_slug` (`slug`),
  UNIQUE KEY `uq_rest_dominio` (`dominio`),
  KEY `ix_rest_estado` (`estado`),
  KEY `ix_rest_plan` (`plan_id`),
  CONSTRAINT `fk_rest_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `restaurant_settings` (
  `restaurant_id` INT UNSIGNED NOT NULL,
  `clave`         VARCHAR(60) NOT NULL,
  `valor`         MEDIUMTEXT NULL,
  PRIMARY KEY (`restaurant_id`, `clave`),
  CONSTRAINT `fk_rs_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schedules` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `dia`           TINYINT NOT NULL,          -- 0=domingo .. 6=sabado
  `abre`          TIME NOT NULL DEFAULT '08:00:00',
  `cierra`        TIME NOT NULL DEFAULT '22:00:00',
  `cerrado`       TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_horario` (`restaurant_id`, `dia`),
  CONSTRAINT `fk_hor_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  USUARIOS Y SEGURIDAD
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NULL,             -- NULL solo para superadmin
  `nombre`        VARCHAR(120) NOT NULL,
  `email`         VARCHAR(190) NULL,
  `usuario`       VARCHAR(60)  NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `rol`           ENUM('superadmin','dueno','admin','cocina','mesero') NOT NULL DEFAULT 'mesero',
  `telefono`      VARCHAR(30) NOT NULL DEFAULT '',
  `avatar`        VARCHAR(190) NOT NULL DEFAULT '',
  `tema_panel`    ENUM('claro','oscuro','auto') NOT NULL DEFAULT 'auto',
  `activo`        TINYINT(1) NOT NULL DEFAULT 1,
  `onboarding`    TINYINT(1) NOT NULL DEFAULT 0,
  `ultimo_acceso` DATETIME NULL,
  `ultima_ip`     VARCHAR(45) NOT NULL DEFAULT '',
  `creado`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_email` (`email`),
  UNIQUE KEY `uq_user_usuario` (`usuario`),
  KEY `ix_user_rest` (`restaurant_id`),
  CONSTRAINT `fk_user_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expira`     DATETIME NOT NULL,
  `usado`      TINYINT(1) NOT NULL DEFAULT 0,
  `ip`         VARCHAR(45) NOT NULL DEFAULT '',
  `creado`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_pr_token` (`token_hash`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expira`     DATETIME NOT NULL,
  `creado`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_rt_user` (`user_id`, `token_hash`),
  CONSTRAINT `fk_rt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `clave`           VARCHAR(40) NOT NULL,
  `contador`        INT NOT NULL DEFAULT 0,
  `ventana_inicio`  DATETIME NOT NULL,
  `bloqueado_hasta` DATETIME NULL,
  PRIMARY KEY (`clave`),
  KEY `ix_rl_ventana` (`ventana_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NULL,
  `user_id`       INT UNSIGNED NULL,
  `usuario`       VARCHAR(120) NOT NULL DEFAULT '',
  `accion`        VARCHAR(60)  NOT NULL,
  `entidad`       VARCHAR(60)  NOT NULL DEFAULT '',
  `entidad_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `antes`         TEXT NULL,
  `despues`       TEXT NULL,
  `ip`            VARCHAR(45)  NOT NULL DEFAULT '',
  `agente`        VARCHAR(255) NOT NULL DEFAULT '',
  `creado`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_audit_rest` (`restaurant_id`, `creado`),
  KEY `ix_audit_accion` (`accion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  MENU
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `categories` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `nombre`        VARCHAR(120) NOT NULL,
  `nombre_en`     VARCHAR(120) NOT NULL DEFAULT '',
  `descripcion`   VARCHAR(255) NOT NULL DEFAULT '',
  `descripcion_en` VARCHAR(255) NOT NULL DEFAULT '',
  `imagen`        VARCHAR(190) NOT NULL DEFAULT '',
  `icono`         VARCHAR(30)  NOT NULL DEFAULT 'utensils',
  `orden`         INT NOT NULL DEFAULT 0,
  `activo`        TINYINT(1) NOT NULL DEFAULT 1,
  `hora_inicio`   TIME NULL,                     -- disponibilidad por horario
  `hora_fin`      TIME NULL,
  `dias`          VARCHAR(30) NOT NULL DEFAULT '',  -- '0,1,2,...' vacio = todos
  `creado`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado`   DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_cat_rest` (`restaurant_id`, `activo`, `orden`),
  CONSTRAINT `fk_cat_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `category_id`   INT UNSIGNED NULL,
  `nombre`        VARCHAR(160) NOT NULL,
  `nombre_en`     VARCHAR(160) NOT NULL DEFAULT '',
  `descripcion`   TEXT NULL,
  `descripcion_en` TEXT NULL,
  `precio`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `precio_promo`  DECIMAL(10,2) NULL,
  `costo`         DECIMAL(10,2) NULL,
  `imagen`        VARCHAR(190) NOT NULL DEFAULT '',
  `imagenes`      TEXT NULL,                     -- JSON con fotos adicionales
  `sku`           VARCHAR(40) NOT NULL DEFAULT '',
  `orden`         INT NOT NULL DEFAULT 0,
  `activo`        TINYINT(1) NOT NULL DEFAULT 1,
  `agotado`       TINYINT(1) NOT NULL DEFAULT 0,
  `destacado`     TINYINT(1) NOT NULL DEFAULT 0,
  `tiempo_prep`   INT NOT NULL DEFAULT 15,
  `calorias`      INT NULL,
  `etiquetas`     VARCHAR(190) NOT NULL DEFAULT '',   -- nuevo,popular,picante,vegano,vegetariano,sin_gluten
  `alergenos`     VARCHAR(255) NOT NULL DEFAULT '',
  `estacion`      ENUM('cocina','bar','postres') NOT NULL DEFAULT 'cocina',
  `hora_inicio`   TIME NULL,
  `hora_fin`      TIME NULL,
  `dias`          VARCHAR(30) NOT NULL DEFAULT '',
  `es_combo`      TINYINT(1) NOT NULL DEFAULT 0,
  `combo_items`   TEXT NULL,                     -- JSON [{product_id, cantidad}]
  `vendidos`      INT UNSIGNED NOT NULL DEFAULT 0,
  `creado`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado`   DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_prod_rest` (`restaurant_id`, `activo`, `orden`),
  KEY `ix_prod_cat` (`category_id`),
  KEY `ix_prod_destacado` (`restaurant_id`, `destacado`),
  CONSTRAINT `fk_prod_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modifier_groups` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `nombre`        VARCHAR(120) NOT NULL,
  `nombre_en`     VARCHAR(120) NOT NULL DEFAULT '',
  `tipo`          ENUM('unico','multiple') NOT NULL DEFAULT 'unico',
  `obligatorio`   TINYINT(1) NOT NULL DEFAULT 0,
  `min_sel`       INT NOT NULL DEFAULT 0,
  `max_sel`       INT NOT NULL DEFAULT 1,
  `orden`         INT NOT NULL DEFAULT 0,
  `activo`        TINYINT(1) NOT NULL DEFAULT 1,
  `creado`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_mg_rest` (`restaurant_id`, `orden`),
  CONSTRAINT `fk_mg_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modifier_options` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id`      INT UNSIGNED NOT NULL,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `nombre`        VARCHAR(120) NOT NULL,
  `nombre_en`     VARCHAR(120) NOT NULL DEFAULT '',
  `precio_extra`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `orden`         INT NOT NULL DEFAULT 0,
  `activo`        TINYINT(1) NOT NULL DEFAULT 1,
  `agotado`       TINYINT(1) NOT NULL DEFAULT 0,
  `predeterminado` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_mo_group` (`group_id`, `orden`),
  KEY `ix_mo_rest` (`restaurant_id`),
  CONSTRAINT `fk_mo_group` FOREIGN KEY (`group_id`) REFERENCES `modifier_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_modifiers` (
  `product_id` INT UNSIGNED NOT NULL,
  `group_id`   INT UNSIGNED NOT NULL,
  `orden`      INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`product_id`, `group_id`),
  KEY `ix_pm_group` (`group_id`),
  CONSTRAINT `fk_pm_prod`  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pm_group` FOREIGN KEY (`group_id`)   REFERENCES `modifier_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `promotions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `nombre`        VARCHAR(120) NOT NULL,
  `descripcion`   VARCHAR(255) NOT NULL DEFAULT '',
  `tipo`          ENUM('descuento','2x1','combo','precio_fijo') NOT NULL DEFAULT 'descuento',
  `valor`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `product_ids`   VARCHAR(500) NOT NULL DEFAULT '',
  `category_ids`  VARCHAR(500) NOT NULL DEFAULT '',
  `imagen`        VARCHAR(190) NOT NULL DEFAULT '',
  `desde`         DATE NULL,
  `hasta`         DATE NULL,
  `dias`          VARCHAR(30) NOT NULL DEFAULT '',
  `activo`        TINYINT(1) NOT NULL DEFAULT 1,
  `orden`         INT NOT NULL DEFAULT 0,
  `creado`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_promo_rest` (`restaurant_id`, `activo`),
  CONSTRAINT `fk_promo_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  MESAS Y ZONAS
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `zones` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `nombre`        VARCHAR(80) NOT NULL,
  `orden`         INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_zone_rest` (`restaurant_id`, `orden`),
  CONSTRAINT `fk_zone_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tables` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `zone_id`       INT UNSIGNED NULL,
  `nombre`        VARCHAR(40) NOT NULL,
  `capacidad`     INT NOT NULL DEFAULT 4,
  `estado`        ENUM('libre','ocupada','cuenta','llamada') NOT NULL DEFAULT 'libre',
  `orden`         INT NOT NULL DEFAULT 0,
  `activo`        TINYINT(1) NOT NULL DEFAULT 1,
  `abierta_desde` DATETIME NULL,
  `mesero_id`     INT UNSIGNED NULL,
  `creado`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_tbl_rest` (`restaurant_id`, `activo`, `orden`),
  CONSTRAINT `fk_tbl_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tbl_zone` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_zones` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `nombre`        VARCHAR(120) NOT NULL,
  `costo`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `minimo`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tiempo_min`    INT NOT NULL DEFAULT 30,
  `activo`        TINYINT(1) NOT NULL DEFAULT 1,
  `orden`         INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_dz_rest` (`restaurant_id`, `activo`),
  CONSTRAINT `fk_dz_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  CLIENTES, CUPONES Y FIDELIDAD
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `customers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `nombre`        VARCHAR(120) NOT NULL,
  `telefono`      VARCHAR(30) NOT NULL,
  `email`         VARCHAR(190) NOT NULL DEFAULT '',
  `direccion`     VARCHAR(255) NOT NULL DEFAULT '',
  `referencia`    VARCHAR(255) NOT NULL DEFAULT '',
  `zone_id`       INT UNSIGNED NULL,
  `puntos`        INT NOT NULL DEFAULT 0,
  `pedidos`       INT NOT NULL DEFAULT 0,
  `total_gastado` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notas`         VARCHAR(255) NOT NULL DEFAULT '',
  `creado`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_pedido` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cli_tel` (`restaurant_id`, `telefono`),
  KEY `ix_cli_rest` (`restaurant_id`),
  CONSTRAINT `fk_cli_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupons` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `codigo`        VARCHAR(40) NOT NULL,
  `descripcion`   VARCHAR(190) NOT NULL DEFAULT '',
  `tipo`          ENUM('porcentaje','monto','envio_gratis') NOT NULL DEFAULT 'porcentaje',
  `valor`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `min_compra`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `usos_max`      INT NOT NULL DEFAULT 0,        -- 0 = ilimitado
  `usos`          INT NOT NULL DEFAULT 0,
  `desde`         DATE NULL,
  `hasta`         DATE NULL,
  `activo`        TINYINT(1) NOT NULL DEFAULT 1,
  `creado`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cup_codigo` (`restaurant_id`, `codigo`),
  CONSTRAINT `fk_cup_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  PEDIDOS
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `orders` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id`    INT UNSIGNED NOT NULL,
  `codigo`           VARCHAR(20) NOT NULL,
  `table_id`         INT UNSIGNED NULL,
  `mesa_nombre`      VARCHAR(40) NOT NULL DEFAULT '',
  `modo`             ENUM('mesa','llevar','delivery','whatsapp') NOT NULL DEFAULT 'mesa',
  `estado`           ENUM('nuevo','preparando','listo','entregado','pagado','anulado') NOT NULL DEFAULT 'nuevo',
  `customer_id`      INT UNSIGNED NULL,
  `cliente_nombre`   VARCHAR(120) NOT NULL DEFAULT '',
  `cliente_telefono` VARCHAR(30)  NOT NULL DEFAULT '',
  `cliente_direccion` VARCHAR(255) NOT NULL DEFAULT '',
  `cliente_referencia` VARCHAR(255) NOT NULL DEFAULT '',
  `delivery_zone_id` INT UNSIGNED NULL,
  `costo_envio`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `subtotal`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `descuento`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cupon_codigo`     VARCHAR(40) NOT NULL DEFAULT '',
  `impuesto`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `propina`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `pagado_con`       DECIMAL(12,2) NULL,
  `metodo_pago`      VARCHAR(30) NOT NULL DEFAULT '',
  `notas`            VARCHAR(500) NOT NULL DEFAULT '',
  `motivo_anulacion` VARCHAR(255) NOT NULL DEFAULT '',
  `user_id`          INT UNSIGNED NULL,          -- mesero que atiende / cobra
  `creado_por`       ENUM('cliente','mesero','admin') NOT NULL DEFAULT 'cliente',
  `session_token`    CHAR(32) NOT NULL DEFAULT '',
  `ip`               VARCHAR(45) NOT NULL DEFAULT '',
  `minutos_prep`     INT NULL,
  `calificacion`     TINYINT NULL,
  `comentario`       VARCHAR(500) NOT NULL DEFAULT '',
  `creado`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado`      DATETIME NULL,
  `listo_en`         DATETIME NULL,
  `entregado_en`     DATETIME NULL,
  `pagado_en`        DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ord_codigo` (`restaurant_id`, `codigo`),
  KEY `ix_ord_rest_estado` (`restaurant_id`, `estado`, `creado`),
  KEY `ix_ord_mesa` (`table_id`, `estado`),
  KEY `ix_ord_creado` (`restaurant_id`, `creado`),
  KEY `ix_ord_token` (`session_token`),
  CONSTRAINT `fk_ord_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ord_tbl`  FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ord_cli`  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`      INT UNSIGNED NOT NULL,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `product_id`    INT UNSIGNED NULL,
  `nombre`        VARCHAR(180) NOT NULL,
  `precio_unit`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `extra_unit`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cantidad`      INT NOT NULL DEFAULT 1,
  `modificadores` TEXT NULL,                     -- JSON [{grupo, opcion, precio}]
  `notas`         VARCHAR(255) NOT NULL DEFAULT '',
  `subtotal`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `estacion`      ENUM('cocina','bar','postres') NOT NULL DEFAULT 'cocina',
  `estado`        ENUM('pendiente','preparando','listo','entregado','anulado') NOT NULL DEFAULT 'pendiente',
  `creado`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_oi_order` (`order_id`),
  KEY `ix_oi_prod` (`product_id`),
  KEY `ix_oi_rest` (`restaurant_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_events` (
  `id`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `estado`   VARCHAR(20) NOT NULL,
  `user_id`  INT UNSIGNED NULL,
  `usuario`  VARCHAR(120) NOT NULL DEFAULT '',
  `nota`     VARCHAR(255) NOT NULL DEFAULT '',
  `creado`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_oe_order` (`order_id`, `creado`),
  CONSTRAINT `fk_oe_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `waiter_calls` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `table_id`      INT UNSIGNED NULL,
  `mesa_nombre`   VARCHAR(40) NOT NULL DEFAULT '',
  `tipo`          ENUM('mesero','cuenta') NOT NULL DEFAULT 'mesero',
  `estado`        ENUM('pendiente','atendida') NOT NULL DEFAULT 'pendiente',
  `nota`          VARCHAR(190) NOT NULL DEFAULT '',
  `user_id`       INT UNSIGNED NULL,
  `creado`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atendida_en`   DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_wc_rest` (`restaurant_id`, `estado`, `creado`),
  CONSTRAINT `fk_wc_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(120) NOT NULL,
  `email`       VARCHAR(190) NOT NULL,
  `telefono`    VARCHAR(30) NOT NULL DEFAULT '',
  `restaurante` VARCHAR(120) NOT NULL DEFAULT '',
  `plan`        VARCHAR(60) NOT NULL DEFAULT '',
  `mensaje`     TEXT NOT NULL,
  `ip`          VARCHAR(45) NOT NULL DEFAULT '',
  `leido`       TINYINT(1) NOT NULL DEFAULT 0,
  `creado`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_cm_leido` (`leido`, `creado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
--  DATOS BASE (planes y textos de la plataforma)
-- ---------------------------------------------------------------------

INSERT INTO `plans` (`nombre`,`slug`,`descripcion`,`precio_mensual`,`precio_anual`,`max_productos`,`max_mesas`,`max_sucursales`,`max_usuarios`,`caracteristicas`,`destacado`,`orden`) VALUES
('Básico','basico','Ideal para cafeterías y comedores que quieren su menú digital.',149.00,1490.00,60,12,1,3,
 '["Menú digital ilimitado en visitas","QR general y por mesa","Fotos y descripciones","Pedidos por WhatsApp","1 sucursal","Soporte por correo"]',0,1),
('Pro','pro','El favorito: pedidos en mesa que llegan directo a la cocina.',299.00,2990.00,250,40,1,10,
 '["Todo lo del plan Básico","Pedidos en mesa en tiempo real","Pantalla de cocina (KDS)","Panel de mesero y caja","Reportes y gráficas","Cupones y promociones","Pedidos para llevar"]',1,2),
('Premium','premium','Para restaurantes con varias sucursales y operación completa.',549.00,5490.00,0,0,5,0,
 '["Todo lo del plan Pro","Productos y mesas ilimitados","Hasta 5 sucursales","Delivery con zonas y costos","Programa de puntos","Dominio propio","Respaldos automáticos","Soporte prioritario"]',0,3);

INSERT INTO `platform_settings` (`clave`,`valor`) VALUES
('nombre_plataforma','MenúGold'),
('eslogan','Menús QR de lujo con pedidos en tiempo real'),
('descripcion','El menú digital que hace que tu restaurante se vea como lo que es: una experiencia de alta cocina. Tus clientes escanean, piden y tú lo ves al instante.'),
('email_contacto','hola@menugold.gt'),
('whatsapp',''),
('telefono',''),
('direccion','Ciudad de Guatemala'),
('hero_titulo','Tu carta, convertida en experiencia'),
('hero_subtitulo','Menú QR elegante, pedidos que llegan solos a la cocina y control total desde tu celular.'),
('cta_texto','Quiero mi menú digital'),
('demo_slug','la-terraza-gold'),
('backup_semanal','1'),
('aviso_vencimiento_dias','7');
