-- =====================================================================
--  CotizaPro B2B — Estructura de base de datos
--  Instalación de una sola empresa
--  MySQL 8.0 / MariaDB 10.4+   ·   utf8mb4_unicode_ci
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------ empresa (fila única)
CREATE TABLE IF NOT EXISTS `company` (
  `id`                INT UNSIGNED NOT NULL DEFAULT 1,
  `name`              VARCHAR(140) NOT NULL,
  `legal_name`        VARCHAR(180) NULL,
  `nit`               VARCHAR(30)  NULL,
  `logo`              VARCHAR(190) NULL,
  `logo_dark`         VARCHAR(190) NULL,
  `hero_image`        VARCHAR(190) NULL,
  `og_image`          VARCHAR(190) NULL,
  `theme`             VARCHAR(30)  NOT NULL DEFAULT 'acero',
  `color_accent`      VARCHAR(9)   NOT NULL DEFAULT '#E8590C',
  `color_ink`         VARCHAR(9)   NOT NULL DEFAULT '#1C1F22',
  `color_paper`       VARCHAR(9)   NOT NULL DEFAULT '#F5F6F4',
  `tagline`           VARCHAR(190) NULL,
  `about`             TEXT NULL,
  `years_experience`  INT NOT NULL DEFAULT 0,
  `email`             VARCHAR(150) NULL,
  `phone`             VARCHAR(40)  NULL,
  `whatsapp`          VARCHAR(30)  NULL,
  `address`           VARCHAR(220) NULL,
  `city`              VARCHAR(90)  NULL,
  `country`           VARCHAR(90)  NOT NULL DEFAULT 'Guatemala',
  `maps_url`          VARCHAR(255) NULL,
  `currency_symbol`   VARCHAR(6)   NOT NULL DEFAULT 'Q',
  `tax_rate`          DECIMAL(6,3) NOT NULL DEFAULT 12.000,
  `tax_label`         VARCHAR(20)  NOT NULL DEFAULT 'IVA',
  `tax_included`      TINYINT(1)   NOT NULL DEFAULT 0,
  `price_visibility`  ENUM('publico','clientes','oculto') NOT NULL DEFAULT 'oculto',
  `quote_prefix`      VARCHAR(16)  NOT NULL DEFAULT 'COT',
  `quote_next`        INT NOT NULL DEFAULT 1,
  `quote_year`        SMALLINT NOT NULL DEFAULT 2026,
  `quote_pad`         TINYINT NOT NULL DEFAULT 4,
  `pdf_terms`         TEXT NULL,
  `pdf_footer`        VARCHAR(255) NULL,
  `validity_days`     INT NOT NULL DEFAULT 15,
  `delivery_terms`    VARCHAR(190) NULL,
  `payment_terms`     VARCHAR(190) NULL,
  `smtp_host`         VARCHAR(150) NULL,
  `smtp_port`         INT NULL,
  `smtp_user`         VARCHAR(150) NULL,
  `smtp_pass`         VARCHAR(255) NULL,
  `smtp_secure`       VARCHAR(10)  NULL,
  `smtp_from`         VARCHAR(150) NULL,
  `smtp_from_name`    VARCHAR(150) NULL,
  `reminder_days_seller` INT NOT NULL DEFAULT 3,
  `reminder_days_client` INT NOT NULL DEFAULT 0,
  `assign_mode`       ENUM('manual','rotativo') NOT NULL DEFAULT 'rotativo',
  `assign_pointer`    INT UNSIGNED NOT NULL DEFAULT 0,
  `seo_title`         VARCHAR(190) NULL,
  `seo_description`   VARCHAR(300) NULL,
  `created_at`        DATETIME NULL,
  `updated_at`        DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------- usuarios
CREATE TABLE IF NOT EXISTS `users` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(120) NOT NULL,
  `email`          VARCHAR(150) NOT NULL,
  `username`       VARCHAR(60)  NULL,
  `password`       VARCHAR(255) NOT NULL,
  `role`           ENUM('admin','vendedor','visor') NOT NULL DEFAULT 'vendedor',
  `phone`          VARCHAR(40)  NULL,
  `whatsapp`       VARCHAR(30)  NULL,
  `position`       VARCHAR(90)  NULL,
  `avatar`         VARCHAR(190) NULL,
  `signature`      VARCHAR(190) NULL,
  `status`         ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `twofa_enabled`  TINYINT(1) NOT NULL DEFAULT 0,
  `receives_leads` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at`  DATETIME NULL,
  `last_login_ip`  VARCHAR(45) NULL,
  `created_at`     DATETIME NULL,
  `updated_at`     DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_email` (`email`),
  UNIQUE KEY `uq_user_username` (`username`),
  KEY `ix_user_role` (`role`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at`    DATETIME NULL,
  `ip`         VARCHAR(45) NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reset_token` (`token_hash`),
  KEY `ix_reset_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `two_factor_codes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `code_hash`  CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at`    DATETIME NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_2fa_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket`        VARCHAR(64) NOT NULL,
  `hits`          INT NOT NULL DEFAULT 0,
  `window_start`  DATETIME NOT NULL,
  `blocked_until` DATETIME NULL,
  `created_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bucket` (`bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------- catálogo
CREATE TABLE IF NOT EXISTS `brands` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `slug`       VARCHAR(140) NOT NULL,
  `logo`       VARCHAR(190) NULL,
  `website`    VARCHAR(190) NULL,
  `sort`       INT NOT NULL DEFAULT 0,
  `active`     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_brand` (`slug`),
  KEY `ix_brand_active` (`active`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`   INT UNSIGNED NULL,
  `name`        VARCHAR(140) NOT NULL,
  `slug`        VARCHAR(160) NOT NULL,
  `code`        VARCHAR(20)  NULL,
  `description` TEXT NULL,
  `image`       VARCHAR(190) NULL,
  `sort`        INT NOT NULL DEFAULT 0,
  `active`      TINYINT(1) NOT NULL DEFAULT 1,
  `seo_title`       VARCHAR(190) NULL,
  `seo_description` VARCHAR(300) NULL,
  `created_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_category` (`slug`),
  KEY `ix_cat_tree` (`parent_id`,`active`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attribute_defs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NULL,
  `code`        VARCHAR(50)  NOT NULL,
  `label`       VARCHAR(90)  NOT NULL,
  `type`        ENUM('texto','numero','lista','booleano') NOT NULL DEFAULT 'texto',
  `unit`        VARCHAR(20)  NULL,
  `options`     TEXT NULL,
  `filterable`  TINYINT(1) NOT NULL DEFAULT 1,
  `sort`        INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_attr_cat` (`category_id`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`      INT UNSIGNED NULL,
  `brand_id`         INT UNSIGNED NULL,
  `code`             VARCHAR(60)  NOT NULL,
  `name`             VARCHAR(200) NOT NULL,
  `slug`             VARCHAR(220) NOT NULL,
  `short_desc`       VARCHAR(300) NULL,
  `description`      TEXT NULL,
  `application`      VARCHAR(255) NULL,
  `unit`             VARCHAR(20)  NOT NULL DEFAULT 'unidad',
  `price`            DECIMAL(12,2) NOT NULL DEFAULT 0,
  `cost`             DECIMAL(12,2) NOT NULL DEFAULT 0,
  `price_visibility` ENUM('heredar','publico','clientes','oculto') NOT NULL DEFAULT 'heredar',
  `min_qty`          DECIMAL(10,2) NOT NULL DEFAULT 1,
  `lead_time`        VARCHAR(60)  NULL,
  `stock_note`       VARCHAR(60)  NULL,
  `featured`         TINYINT(1) NOT NULL DEFAULT 0,
  `active`           TINYINT(1) NOT NULL DEFAULT 1,
  `views`            INT UNSIGNED NOT NULL DEFAULT 0,
  `quote_count`      INT UNSIGNED NOT NULL DEFAULT 0,
  `seo_title`        VARCHAR(190) NULL,
  `seo_description`  VARCHAR(300) NULL,
  `created_at`       DATETIME NULL,
  `updated_at`       DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_slug` (`slug`),
  UNIQUE KEY `uq_product_code` (`code`),
  KEY `ix_prod_active` (`active`,`category_id`),
  KEY `ix_prod_featured` (`featured`,`active`),
  FULLTEXT KEY `ft_prod` (`code`,`name`,`short_desc`,`description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `path`       VARCHAR(190) NOT NULL,
  `path_webp`  VARCHAR(190) NULL,
  `path_thumb` VARCHAR(190) NULL,
  `width`      INT NULL,
  `height`     INT NULL,
  `blur`       VARCHAR(1500) NULL,
  `alt`        VARCHAR(190) NULL,
  `sort`       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_img_product` (`product_id`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_attributes` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`   INT UNSIGNED NOT NULL,
  `attribute_id` INT UNSIGNED NOT NULL,
  `value`        VARCHAR(190) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prod_attr` (`product_id`,`attribute_id`),
  KEY `ix_pa_filter` (`attribute_id`,`value`(60))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_documents` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `name`       VARCHAR(160) NOT NULL,
  `path`       VARCHAR(190) NOT NULL,
  `size`       INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_doc_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `price_lists` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(90) NOT NULL,
  `discount_pct` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `is_default`   TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_prices` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`    INT UNSIGNED NOT NULL,
  `price_list_id` INT UNSIGNED NOT NULL,
  `price`         DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pp` (`product_id`,`price_list_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ clientes
CREATE TABLE IF NOT EXISTS `customers` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(160) NOT NULL,
  `legal_name`       VARCHAR(200) NULL,
  `nit`              VARCHAR(30)  NULL,
  `email`            VARCHAR(150) NULL,
  `phone`            VARCHAR(40)  NULL,
  `whatsapp`         VARCHAR(30)  NULL,
  `address`          VARCHAR(220) NULL,
  `city`             VARCHAR(90)  NULL,
  `sector`           VARCHAR(90)  NULL,
  `price_list_id`    INT UNSIGNED NULL,
  `assigned_user_id` INT UNSIGNED NULL,
  `notes`            TEXT NULL,
  `next_followup`    DATE NULL,
  `created_at`       DATETIME NULL,
  `updated_at`       DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_cus_name` (`name`),
  KEY `ix_cus_nit` (`nit`),
  KEY `ix_cus_user` (`assigned_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_contacts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `position`    VARCHAR(90)  NULL,
  `email`       VARCHAR(150) NULL,
  `phone`       VARCHAR(40)  NULL,
  `is_primary`  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_cc_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------- cotizaciones
CREATE TABLE IF NOT EXISTS `quotes` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number`          VARCHAR(40)  NOT NULL,
  `folio_seq`       INT UNSIGNED NOT NULL DEFAULT 0,
  `folio_year`      SMALLINT NOT NULL DEFAULT 2026,
  `version`         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `parent_id`       INT UNSIGNED NULL,
  `is_current`      TINYINT(1) NOT NULL DEFAULT 1,
  `customer_id`     INT UNSIGNED NULL,
  `user_id`         INT UNSIGNED NULL,
  `contact_name`    VARCHAR(140) NOT NULL,
  `contact_company` VARCHAR(180) NULL,
  `contact_nit`     VARCHAR(30)  NULL,
  `contact_phone`   VARCHAR(40)  NULL,
  `contact_email`   VARCHAR(150) NULL,
  `status`          ENUM('nueva','elaboracion','enviada','negociacion','aprobada','perdida') NOT NULL DEFAULT 'nueva',
  `source`          ENUM('web','panel','importada') NOT NULL DEFAULT 'panel',
  `currency_symbol` VARCHAR(6) NOT NULL DEFAULT 'Q',
  `subtotal`        DECIMAL(14,2) NOT NULL DEFAULT 0,
  `discount_type`   ENUM('ninguno','porcentaje','monto') NOT NULL DEFAULT 'ninguno',
  `discount_value`  DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `taxable_base`    DECIMAL(14,2) NOT NULL DEFAULT 0,
  `tax_rate`        DECIMAL(6,3) NOT NULL DEFAULT 12.000,
  `tax_amount`      DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total`           DECIMAL(14,2) NOT NULL DEFAULT 0,
  `won_amount`      DECIMAL(14,2) NOT NULL DEFAULT 0,
  `validity_days`   INT NOT NULL DEFAULT 15,
  `valid_until`     DATE NULL,
  `delivery_time`   VARCHAR(160) NULL,
  `payment_terms`   VARCHAR(190) NULL,
  `notes`           TEXT NULL,
  `internal_notes`  TEXT NULL,
  `client_message`  TEXT NULL,
  `lost_reason`     VARCHAR(60) NULL,
  `lost_detail`     VARCHAR(255) NULL,
  `track_token`     VARCHAR(90) NOT NULL,
  `token_revoked`   TINYINT(1) NOT NULL DEFAULT 0,
  `pdf_path`        VARCHAR(190) NULL,
  `sent_at`         DATETIME NULL,
  `viewed_at`       DATETIME NULL,
  `approved_at`     DATETIME NULL,
  `lost_at`         DATETIME NULL,
  `last_contact_at` DATETIME NULL,
  `next_followup_at` DATE NULL,
  `reminder_sent_at` DATETIME NULL,
  `client_reminder_sent_at` DATETIME NULL,
  `board_sort`      INT NOT NULL DEFAULT 0,
  `created_at`      DATETIME NULL,
  `updated_at`      DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quote_token` (`track_token`),
  UNIQUE KEY `uq_quote_number` (`number`),
  KEY `ix_q_status` (`status`,`board_sort`),
  KEY `ix_q_user` (`user_id`,`status`),
  KEY `ix_q_customer` (`customer_id`),
  KEY `ix_q_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quote_items` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quote_id`     INT UNSIGNED NOT NULL,
  `product_id`   INT UNSIGNED NULL,
  `code`         VARCHAR(60)  NULL,
  `name`         VARCHAR(220) NOT NULL,
  `specs`        VARCHAR(500) NULL,
  `notes`        VARCHAR(300) NULL,
  `qty`          DECIMAL(12,2) NOT NULL DEFAULT 1,
  `unit`         VARCHAR(20) NOT NULL DEFAULT 'unidad',
  `unit_price`   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount_pct` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `line_total`   DECIMAL(14,2) NOT NULL DEFAULT 0,
  `sort`         INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_qi_quote` (`quote_id`,`sort`),
  KEY `ix_qi_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quote_events` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quote_id`   INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NULL,
  `actor`      VARCHAR(120) NULL,
  `type`       ENUM('estado','nota','llamada','correo','whatsapp','cliente','sistema','pdf') NOT NULL DEFAULT 'nota',
  `title`      VARCHAR(190) NOT NULL,
  `body`       TEXT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_qe_quote` (`quote_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ sistema
CREATE TABLE IF NOT EXISTS `settings` (
  `key`   VARCHAR(80) NOT NULL,
  `value` MEDIUMTEXT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NULL,
  `user_name`  VARCHAR(120) NULL,
  `action`     VARCHAR(80)  NOT NULL,
  `entity`     VARCHAR(60)  NULL,
  `entity_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `details`    TEXT NULL,
  `ip`         VARCHAR(45)  NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_audit_date` (`created_at`),
  KEY `ix_audit_entity` (`entity`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       VARCHAR(40) NOT NULL DEFAULT 'info',
  `title`      VARCHAR(190) NOT NULL,
  `body`       VARCHAR(400) NULL,
  `link`       VARCHAR(255) NULL,
  `read_at`    DATETIME NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_notif_user` (`user_id`,`read_at`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `to_email`   VARCHAR(190) NOT NULL,
  `subject`    VARCHAR(220) NOT NULL,
  `status`     ENUM('enviado','error') NOT NULL DEFAULT 'enviado',
  `error`      VARCHAR(400) NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_mail_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `imports` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NULL,
  `type`       VARCHAR(30) NOT NULL,
  `filename`   VARCHAR(190) NOT NULL,
  `rows_total` INT NOT NULL DEFAULT 0,
  `rows_ok`    INT NOT NULL DEFAULT 0,
  `rows_error` INT NOT NULL DEFAULT 0,
  `report`     MEDIUMTEXT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_imp_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `backups` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename`   VARCHAR(190) NOT NULL,
  `size`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `kind`       ENUM('manual','automatico') NOT NULL DEFAULT 'manual',
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cron_runs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task`       VARCHAR(50) NOT NULL,
  `result`     VARCHAR(400) NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_cron_task` (`task`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
