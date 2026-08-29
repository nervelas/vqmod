-- MenúGold · esquema de un solo restaurante.
-- Todas las tablas llevan el prefijo mg_ para no chocar con nada que ya
-- exista en la misma base de datos.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `mg_settings` (
  `key`        VARCHAR(64) NOT NULL,
  `value`      MEDIUMTEXT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_hours` (
  `weekday`    TINYINT UNSIGNED NOT NULL,
  `opens_at`   TIME NOT NULL DEFAULT '12:00:00',
  `closes_at`  TIME NOT NULL DEFAULT '22:00:00',
  `is_closed`  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`weekday`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role`          ENUM('owner','manager','kitchen','waiter') NOT NULL DEFAULT 'waiter',
  `name`          VARCHAR(120) NOT NULL,
  `username`      VARCHAR(120) NOT NULL,
  `email`         VARCHAR(190) NOT NULL DEFAULT '',
  `password_hash` VARCHAR(255) NOT NULL,
  `pin`           VARCHAR(255) NOT NULL DEFAULT '',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_categories` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(120) NOT NULL,
  `name_en`        VARCHAR(120) NOT NULL DEFAULT '',
  `description`    VARCHAR(255) NOT NULL DEFAULT '',
  `description_en` VARCHAR(255) NOT NULL DEFAULT '',
  `image`          VARCHAR(200) NOT NULL DEFAULT '',
  `roman`          VARCHAR(8) NOT NULL DEFAULT '',
  `sort`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `days_mask`      TINYINT UNSIGNED NOT NULL DEFAULT 127,
  PRIMARY KEY (`id`),
  KEY `idx_cat_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_products` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`    INT UNSIGNED NOT NULL,
  `name`           VARCHAR(160) NOT NULL,
  `name_en`        VARCHAR(160) NOT NULL DEFAULT '',
  `description`    TEXT NOT NULL,
  `description_en` TEXT NOT NULL,
  `price`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `image`          VARCHAR(200) NOT NULL DEFAULT '',
  `prep_minutes`   SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `tags`           VARCHAR(190) NOT NULL DEFAULT '',
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `is_featured`    TINYINT(1) NOT NULL DEFAULT 0,
  `is_sold_out`    TINYINT(1) NOT NULL DEFAULT 0,
  `available_from` TIME NULL,
  `available_to`   TIME NULL,
  `days_mask`      TINYINT UNSIGNED NOT NULL DEFAULT 127,
  `sort`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prod_cat` (`category_id`,`sort`),
  KEY `idx_prod_feat` (`is_featured`),
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`category_id`) REFERENCES `mg_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_product_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `image`      VARCHAR(200) NOT NULL,
  `sort`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_pimg` (`product_id`,`sort`),
  CONSTRAINT `fk_pimg_prod` FOREIGN KEY (`product_id`) REFERENCES `mg_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_variants` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`  INT UNSIGNED NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `price_delta` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_default`  TINYINT(1) NOT NULL DEFAULT 0,
  `sort`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_var_prod` (`product_id`,`sort`),
  CONSTRAINT `fk_var_prod` FOREIGN KEY (`product_id`) REFERENCES `mg_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_modifier_groups` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120) NOT NULL,
  `name_en`     VARCHAR(120) NOT NULL DEFAULT '',
  `help`        VARCHAR(190) NOT NULL DEFAULT '',
  `type`        ENUM('single','multi') NOT NULL DEFAULT 'single',
  `min_select`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_select`  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `sort`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_modifier_options` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id`    INT UNSIGNED NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `price_delta` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_default`  TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `sort`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_opt_group` (`group_id`,`sort`),
  CONSTRAINT `fk_opt_group` FOREIGN KEY (`group_id`) REFERENCES `mg_modifier_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_product_modifier_groups` (
  `product_id` INT UNSIGNED NOT NULL,
  `group_id`   INT UNSIGNED NOT NULL,
  `sort`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`product_id`,`group_id`),
  KEY `idx_pmg_group` (`group_id`),
  CONSTRAINT `fk_pmg_prod`  FOREIGN KEY (`product_id`) REFERENCES `mg_products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pmg_group` FOREIGN KEY (`group_id`)   REFERENCES `mg_modifier_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_combos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(160) NOT NULL,
  `description` TEXT NOT NULL,
  `price`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `image`       VARCHAR(200) NOT NULL DEFAULT '',
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `sort`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_combo_items` (
  `combo_id`   INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `qty`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`combo_id`,`product_id`),
  KEY `idx_ci_prod` (`product_id`),
  CONSTRAINT `fk_ci_combo` FOREIGN KEY (`combo_id`)   REFERENCES `mg_combos`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_ci_prod`  FOREIGN KEY (`product_id`) REFERENCES `mg_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_promotions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(160) NOT NULL,
  `type`       ENUM('percent','amount') NOT NULL DEFAULT 'percent',
  `value`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `scope`      ENUM('all','category','product') NOT NULL DEFAULT 'all',
  `target_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `starts_at`  DATE NULL,
  `ends_at`    DATE NULL,
  `days_mask`  TINYINT UNSIGNED NOT NULL DEFAULT 127,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_coupons` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(40) NOT NULL,
  `type`       ENUM('percent','amount') NOT NULL DEFAULT 'percent',
  `value`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `min_total`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_uses`   INT UNSIGNED NOT NULL DEFAULT 0,
  `used_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `starts_at`  DATE NULL,
  `ends_at`    DATE NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_tables` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`      VARCHAR(80) NOT NULL,
  `zone`      VARCHAR(80) NOT NULL DEFAULT '',
  `seats`     TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `qr_token`  VARCHAR(64) NOT NULL,
  `status`    ENUM('free','busy','bill') NOT NULL DEFAULT 'free',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_table_token` (`qr_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_delivery_zones` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`      VARCHAR(120) NOT NULL,
  `fee`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `min_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `minutes`   SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_customers` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(160) NOT NULL DEFAULT '',
  `phone`       VARCHAR(40) NOT NULL DEFAULT '',
  `email`       VARCHAR(190) NOT NULL DEFAULT '',
  `address`     VARCHAR(255) NOT NULL DEFAULT '',
  `notes`       VARCHAR(255) NOT NULL DEFAULT '',
  `orders_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_spent` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `points`      INT UNSIGNED NOT NULL DEFAULT 0,
  `last_order_at` DATETIME NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cust_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_orders` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`           VARCHAR(12) NOT NULL,
  `public_token`   VARCHAR(64) NOT NULL,
  `mode`           ENUM('dine_in','takeaway','delivery') NOT NULL DEFAULT 'dine_in',
  `table_id`       INT UNSIGNED NULL,
  `customer_id`    INT UNSIGNED NULL,
  `customer_name`  VARCHAR(160) NOT NULL DEFAULT '',
  `customer_phone` VARCHAR(40) NOT NULL DEFAULT '',
  `address`        VARCHAR(255) NOT NULL DEFAULT '',
  `zone_id`        INT UNSIGNED NULL,
  `status`         ENUM('new','cooking','ready','served','closed','cancelled') NOT NULL DEFAULT 'new',
  `subtotal`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `delivery_fee`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tip`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `coupon_code`    VARCHAR(40) NOT NULL DEFAULT '',
  `payment_method` VARCHAR(40) NOT NULL DEFAULT '',
  `payment_status` ENUM('pending','paid') NOT NULL DEFAULT 'pending',
  `notes`          VARCHAR(500) NOT NULL DEFAULT '',
  `waiter_id`      INT UNSIGNED NULL,
  `cancel_reason`  VARCHAR(255) NOT NULL DEFAULT '',
  `placed_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ready_at`       DATETIME NULL,
  `closed_at`      DATETIME NULL,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_token` (`public_token`),
  KEY `idx_order_status` (`status`,`placed_at`),
  KEY `idx_order_code` (`code`),
  KEY `idx_order_table` (`table_id`),
  CONSTRAINT `fk_order_table` FOREIGN KEY (`table_id`) REFERENCES `mg_tables`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_cust`  FOREIGN KEY (`customer_id`) REFERENCES `mg_customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_order_items` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`     INT UNSIGNED NOT NULL,
  `product_id`   INT UNSIGNED NULL,
  `name`         VARCHAR(200) NOT NULL,
  `variant_name` VARCHAR(120) NOT NULL DEFAULT '',
  `qty`          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `line_total`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notes`        VARCHAR(255) NOT NULL DEFAULT '',
  `status`       ENUM('pending','done') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_item_order` (`order_id`),
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `mg_orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_prod`  FOREIGN KEY (`product_id`) REFERENCES `mg_products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_order_item_modifiers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_item_id` INT UNSIGNED NOT NULL,
  `name`          VARCHAR(160) NOT NULL,
  `price_delta`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_oim_item` (`order_item_id`),
  CONSTRAINT `fk_oim_item` FOREIGN KEY (`order_item_id`) REFERENCES `mg_order_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_service_calls` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_id`    INT UNSIGNED NULL,
  `type`        ENUM('waiter','bill') NOT NULL DEFAULT 'waiter',
  `status`      ENUM('open','done') NOT NULL DEFAULT 'open',
  `note`        VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_call_status` (`status`,`created_at`),
  CONSTRAINT `fk_call_table` FOREIGN KEY (`table_id`) REFERENCES `mg_tables`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_audit_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NULL,
  `action`     VARCHAR(80) NOT NULL,
  `entity`     VARCHAR(40) NOT NULL DEFAULT '',
  `entity_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `meta`       TEXT NOT NULL,
  `ip`         VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mg_rate_limits` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket_key`   VARCHAR(190) NOT NULL,
  `hits`         INT UNSIGNED NOT NULL DEFAULT 0,
  `window_start` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bucket` (`bucket_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
