-- MenúGold · esquema base
-- MySQL 8 / MariaDB 10.4+ · utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `plans` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(60)  NOT NULL,
  `slug`          VARCHAR(60)  NOT NULL,
  `price_month`   DECIMAL(10,2) NOT NULL DEFAULT 0,
  `max_products`  INT NOT NULL DEFAULT 50,
  `max_tables`    INT NOT NULL DEFAULT 10,
  `max_orders_month` INT NOT NULL DEFAULT 500,
  `max_users`     INT NOT NULL DEFAULT 3,
  `features`      JSON NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `sort`          INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plans_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `restaurants` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`          VARCHAR(64)  NOT NULL,
  `name`          VARCHAR(120) NOT NULL,
  `tagline`       VARCHAR(180) NOT NULL DEFAULT '',
  `description`   TEXT NULL,
  `logo`          VARCHAR(200) NOT NULL DEFAULT '',
  `cover`         VARCHAR(200) NOT NULL DEFAULT '',
  `phone`         VARCHAR(40)  NOT NULL DEFAULT '',
  `whatsapp`      VARCHAR(40)  NOT NULL DEFAULT '',
  `email`         VARCHAR(120) NOT NULL DEFAULT '',
  `address`       VARCHAR(220) NOT NULL DEFAULT '',
  `city`          VARCHAR(80)  NOT NULL DEFAULT '',
  `map_url`       VARCHAR(255) NOT NULL DEFAULT '',
  `review_url`    VARCHAR(255) NOT NULL DEFAULT '',
  `payment_url`   VARCHAR(255) NOT NULL DEFAULT '',
  `currency`      VARCHAR(6)   NOT NULL DEFAULT 'Q',
  `tax_rate`      DECIMAL(5,2) NOT NULL DEFAULT 0,
  `tax_included`  TINYINT(1)   NOT NULL DEFAULT 1,
  `tip_enabled`   TINYINT(1)   NOT NULL DEFAULT 1,
  `tip_options`   VARCHAR(60)  NOT NULL DEFAULT '10,15,20',
  `service_modes` VARCHAR(120) NOT NULL DEFAULT 'dine_in,takeaway,delivery',
  `order_mode`    ENUM('catalog','order','whatsapp') NOT NULL DEFAULT 'order',
  `theme`         VARCHAR(40)  NOT NULL DEFAULT 'brasa',
  `font_combo`    VARCHAR(40)  NOT NULL DEFAULT 'editorial',
  `primary_color` VARCHAR(9)   NOT NULL DEFAULT '#D8B26E',
  `accent_color`  VARCHAR(9)   NOT NULL DEFAULT '#C4502B',
  `lang_default`  VARCHAR(5)   NOT NULL DEFAULT 'es',
  `langs`         VARCHAR(40)  NOT NULL DEFAULT 'es,en',
  `timezone`      VARCHAR(60)  NOT NULL DEFAULT 'America/Guatemala',
  `bank_info`     TEXT NULL,
  `plan_id`       INT UNSIGNED NULL,
  `plan_expires_at` DATE NULL,
  `status`        ENUM('active','trial','suspended') NOT NULL DEFAULT 'trial',
  `notes`         TEXT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rest_slug` (`slug`),
  KEY `idx_rest_status` (`status`),
  CONSTRAINT `fk_rest_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `restaurant_hours` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `weekday`       TINYINT NOT NULL,          -- 0 = domingo
  `opens_at`      TIME NULL,
  `closes_at`     TIME NULL,
  `is_closed`     TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hours` (`restaurant_id`,`weekday`),
  CONSTRAINT `fk_hours_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `restaurant_settings` (
  `restaurant_id` INT UNSIGNED NOT NULL,
  `skey`          VARCHAR(80) NOT NULL,
  `svalue`        MEDIUMTEXT NULL,
  PRIMARY KEY (`restaurant_id`,`skey`),
  CONSTRAINT `fk_rset_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NULL,
  `role`          ENUM('superadmin','owner','manager','kitchen','waiter') NOT NULL DEFAULT 'waiter',
  `name`          VARCHAR(120) NOT NULL,
  `username`      VARCHAR(80)  NOT NULL,
  `email`         VARCHAR(160) NOT NULL DEFAULT '',
  `password_hash` VARCHAR(255) NOT NULL,
  `pin`           VARCHAR(255) NOT NULL DEFAULT '',
  `avatar`        VARCHAR(200) NOT NULL DEFAULT '',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `must_change`   TINYINT(1) NOT NULL DEFAULT 0,
  `last_login_at` DATETIME NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_rest` (`restaurant_id`,`role`),
  CONSTRAINT `fk_users_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `name`          VARCHAR(120) NOT NULL,
  `name_en`       VARCHAR(120) NOT NULL DEFAULT '',
  `description`   VARCHAR(255) NOT NULL DEFAULT '',
  `description_en` VARCHAR(255) NOT NULL DEFAULT '',
  `image`         VARCHAR(200) NOT NULL DEFAULT '',
  `roman`         VARCHAR(8) NOT NULL DEFAULT '',
  `sort`          INT NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `available_from` TIME NULL,
  `available_to`   TIME NULL,
  `days_mask`     TINYINT UNSIGNED NOT NULL DEFAULT 127,
  PRIMARY KEY (`id`),
  KEY `idx_cat_rest` (`restaurant_id`,`is_active`,`sort`),
  CONSTRAINT `fk_cat_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `category_id`   INT UNSIGNED NULL,
  `sku`           VARCHAR(40)  NOT NULL DEFAULT '',
  `name`          VARCHAR(160) NOT NULL,
  `name_en`       VARCHAR(160) NOT NULL DEFAULT '',
  `description`   TEXT NULL,
  `description_en` TEXT NULL,
  `price`         DECIMAL(10,2) NOT NULL DEFAULT 0,
  `compare_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `cost`          DECIMAL(10,2) NOT NULL DEFAULT 0,
  `image`         VARCHAR(200) NOT NULL DEFAULT '',
  `prep_minutes`  SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `calories`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `tags`          VARCHAR(160) NOT NULL DEFAULT '',
  `photo_query`   VARCHAR(120) NOT NULL DEFAULT '',
  `photo_tries`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `is_featured`   TINYINT(1) NOT NULL DEFAULT 0,
  `is_out_of_stock` TINYINT(1) NOT NULL DEFAULT 0,
  `available_from` TIME NULL,
  `available_to`   TIME NULL,
  `days_mask`     TINYINT UNSIGNED NOT NULL DEFAULT 127,
  `sort`          INT NOT NULL DEFAULT 0,
  `views`         INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prod_rest` (`restaurant_id`,`is_active`,`sort`),
  KEY `idx_prod_cat` (`category_id`,`is_active`,`sort`),
  CONSTRAINT `fk_prod_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prod_cat`  FOREIGN KEY (`category_id`)  REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `path`       VARCHAR(200) NOT NULL,
  `sort`       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_pimg_prod` (`product_id`,`sort`),
  CONSTRAINT `fk_pimg_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `variants` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `name`       VARCHAR(80) NOT NULL,
  `name_en`    VARCHAR(80) NOT NULL DEFAULT '',
  `price_delta` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `sort`       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_var_prod` (`product_id`,`sort`),
  CONSTRAINT `fk_var_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modifier_groups` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `name`          VARCHAR(120) NOT NULL,
  `name_en`       VARCHAR(120) NOT NULL DEFAULT '',
  `help`          VARCHAR(180) NOT NULL DEFAULT '',
  `type`          ENUM('single','multi') NOT NULL DEFAULT 'single',
  `min_select`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_select`    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `is_required`   TINYINT(1) NOT NULL DEFAULT 0,
  `sort`          INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_mg_rest` (`restaurant_id`,`sort`),
  CONSTRAINT `fk_mg_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modifier_options` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id`    INT UNSIGNED NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `name_en`     VARCHAR(120) NOT NULL DEFAULT '',
  `price_delta` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `is_default`  TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `sort`        INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_mo_group` (`group_id`,`sort`),
  CONSTRAINT `fk_mo_group` FOREIGN KEY (`group_id`) REFERENCES `modifier_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_modifier_groups` (
  `product_id` INT UNSIGNED NOT NULL,
  `group_id`   INT UNSIGNED NOT NULL,
  `sort`       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`product_id`,`group_id`),
  CONSTRAINT `fk_pmg_prod`  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pmg_group` FOREIGN KEY (`group_id`)   REFERENCES `modifier_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `combos` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `name`          VARCHAR(160) NOT NULL,
  `description`   TEXT NULL,
  `price`         DECIMAL(10,2) NOT NULL DEFAULT 0,
  `image`         VARCHAR(200) NOT NULL DEFAULT '',
  `items`         JSON NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `starts_at`     DATE NULL,
  `ends_at`       DATE NULL,
  `sort`          INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_combo_rest` (`restaurant_id`,`is_active`),
  CONSTRAINT `fk_combo_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `promotions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `name`          VARCHAR(160) NOT NULL,
  `type`          ENUM('percent','amount') NOT NULL DEFAULT 'percent',
  `value`         DECIMAL(10,2) NOT NULL DEFAULT 0,
  `scope`         ENUM('all','category','product') NOT NULL DEFAULT 'all',
  `scope_id`      INT UNSIGNED NULL,
  `starts_at`     DATE NULL,
  `ends_at`       DATE NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_promo_rest` (`restaurant_id`,`is_active`),
  CONSTRAINT `fk_promo_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tables` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `name`          VARCHAR(60) NOT NULL,
  `zone`          VARCHAR(60) NOT NULL DEFAULT '',
  `seats`         TINYINT UNSIGNED NOT NULL DEFAULT 4,
  `qr_token`      VARCHAR(64) NOT NULL,
  `status`        ENUM('free','occupied','bill') NOT NULL DEFAULT 'free',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `sort`          INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_table_token` (`qr_token`),
  KEY `idx_table_rest` (`restaurant_id`,`is_active`,`sort`),
  CONSTRAINT `fk_table_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_zones` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `name`          VARCHAR(120) NOT NULL,
  `fee`           DECIMAL(10,2) NOT NULL DEFAULT 0,
  `min_order`     DECIMAL(10,2) NOT NULL DEFAULT 0,
  `eta_minutes`   SMALLINT UNSIGNED NOT NULL DEFAULT 40,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `sort`          INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_zone_rest` (`restaurant_id`,`is_active`),
  CONSTRAINT `fk_zone_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `name`          VARCHAR(120) NOT NULL DEFAULT '',
  `phone`         VARCHAR(40) NOT NULL,
  `email`         VARCHAR(160) NOT NULL DEFAULT '',
  `address`       VARCHAR(255) NOT NULL DEFAULT '',
  `notes`         VARCHAR(255) NOT NULL DEFAULT '',
  `points`        INT NOT NULL DEFAULT 0,
  `orders_count`  INT NOT NULL DEFAULT 0,
  `total_spent`   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `last_order_at` DATETIME NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cust_phone` (`restaurant_id`,`phone`),
  CONSTRAINT `fk_cust_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupons` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `code`          VARCHAR(40) NOT NULL,
  `type`          ENUM('percent','amount','free_delivery') NOT NULL DEFAULT 'percent',
  `value`         DECIMAL(10,2) NOT NULL DEFAULT 0,
  `min_total`     DECIMAL(10,2) NOT NULL DEFAULT 0,
  `max_uses`      INT NOT NULL DEFAULT 0,
  `used`          INT NOT NULL DEFAULT 0,
  `starts_at`     DATE NULL,
  `ends_at`       DATE NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon` (`restaurant_id`,`code`),
  CONSTRAINT `fk_coupon_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `code`          VARCHAR(12) NOT NULL,
  `table_id`      INT UNSIGNED NULL,
  `mode`          ENUM('dine_in','takeaway','delivery') NOT NULL DEFAULT 'dine_in',
  `status`        ENUM('new','preparing','ready','delivered','paid','cancelled') NOT NULL DEFAULT 'new',
  `customer_id`   INT UNSIGNED NULL,
  `customer_name` VARCHAR(120) NOT NULL DEFAULT '',
  `customer_phone` VARCHAR(40) NOT NULL DEFAULT '',
  `address`       VARCHAR(255) NOT NULL DEFAULT '',
  `delivery_zone_id` INT UNSIGNED NULL,
  `delivery_fee`  DECIMAL(10,2) NOT NULL DEFAULT 0,
  `subtotal`      DECIMAL(10,2) NOT NULL DEFAULT 0,
  `discount`      DECIMAL(10,2) NOT NULL DEFAULT 0,
  `tip`           DECIMAL(10,2) NOT NULL DEFAULT 0,
  `tax`           DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total`         DECIMAL(10,2) NOT NULL DEFAULT 0,
  `coupon_code`   VARCHAR(40) NOT NULL DEFAULT '',
  `payment_method` ENUM('cash','card','transfer','link','pending') NOT NULL DEFAULT 'pending',
  `payment_status` ENUM('pending','paid','refunded') NOT NULL DEFAULT 'pending',
  `notes`         VARCHAR(500) NOT NULL DEFAULT '',
  `waiter_id`     INT UNSIGNED NULL,
  `cancel_reason` VARCHAR(255) NOT NULL DEFAULT '',
  `source`        ENUM('qr','web','whatsapp','panel') NOT NULL DEFAULT 'qr',
  `lang`          VARCHAR(5) NOT NULL DEFAULT 'es',
  `track_token`   VARCHAR(48) NOT NULL DEFAULT '',
  `placed_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `accepted_at`   DATETIME NULL,
  `ready_at`      DATETIME NULL,
  `delivered_at`  DATETIME NULL,
  `paid_at`       DATETIME NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_code` (`restaurant_id`,`code`),
  KEY `idx_order_status` (`restaurant_id`,`status`,`placed_at`),
  KEY `idx_order_table` (`table_id`,`status`),
  KEY `idx_order_placed` (`restaurant_id`,`placed_at`),
  KEY `idx_order_track` (`track_token`),
  CONSTRAINT `fk_order_rest`  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_table` FOREIGN KEY (`table_id`) REFERENCES `tables`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_cust`  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`      INT UNSIGNED NOT NULL,
  `product_id`    INT UNSIGNED NULL,
  `name_snapshot` VARCHAR(200) NOT NULL,
  `qty`           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price`    DECIMAL(10,2) NOT NULL DEFAULT 0,
  `modifiers`     JSON NULL,
  `modifiers_total` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `line_total`    DECIMAL(10,2) NOT NULL DEFAULT 0,
  `notes`         VARCHAR(255) NOT NULL DEFAULT '',
  `status`        ENUM('pending','preparing','ready','served','cancelled') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_oi_order` (`order_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oi_prod`  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_events` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`    INT UNSIGNED NOT NULL,
  `from_status` VARCHAR(20) NOT NULL DEFAULT '',
  `to_status`   VARCHAR(20) NOT NULL DEFAULT '',
  `user_id`     INT UNSIGNED NULL,
  `note`        VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_oe_order` (`order_id`,`created_at`),
  CONSTRAINT `fk_oe_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_calls` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `table_id`      INT UNSIGNED NULL,
  `type`          ENUM('waiter','bill') NOT NULL DEFAULT 'waiter',
  `status`        ENUM('open','done') NOT NULL DEFAULT 'open',
  `note`          VARCHAR(180) NOT NULL DEFAULT '',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at`   DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sc_rest` (`restaurant_id`,`status`,`created_at`),
  CONSTRAINT `fk_sc_rest`  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sc_table` FOREIGN KEY (`table_id`) REFERENCES `tables`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `landing_content` (
  `ckey`   VARCHAR(80) NOT NULL,
  `cvalue` MEDIUMTEXT NULL,
  PRIMARY KEY (`ckey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `landing_plans` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(60) NOT NULL,
  `price`      VARCHAR(30) NOT NULL DEFAULT '',
  `period`     VARCHAR(40) NOT NULL DEFAULT 'al mes',
  `pitch`      VARCHAR(180) NOT NULL DEFAULT '',
  `features`   TEXT NULL,
  `cta_text`   VARCHAR(60) NOT NULL DEFAULT 'Quiero este plan',
  `wa_message` VARCHAR(255) NOT NULL DEFAULT '',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `sort`       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `testimonials` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `role`       VARCHAR(120) NOT NULL DEFAULT '',
  `place`      VARCHAR(120) NOT NULL DEFAULT '',
  `quote`      TEXT NOT NULL,
  `avatar`     VARCHAR(200) NOT NULL DEFAULT '',
  `rating`     TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `sort`       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `skey`   VARCHAR(80) NOT NULL,
  `svalue` MEDIUMTEXT NULL,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `user_id`       INT UNSIGNED NOT NULL DEFAULT 0,
  `action`        VARCHAR(60) NOT NULL,
  `entity`        VARCHAR(60) NOT NULL DEFAULT '',
  `entity_id`     INT UNSIGNED NOT NULL DEFAULT 0,
  `details`       TEXT NULL,
  `ip`            VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent`    VARCHAR(200) NOT NULL DEFAULT '',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_rest` (`restaurant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `photo_credits` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `image_base`    VARCHAR(200) NOT NULL,
  `entity`        VARCHAR(40) NOT NULL DEFAULT 'product',
  `entity_id`     INT UNSIGNED NOT NULL DEFAULT 0,
  `title`         VARCHAR(255) NOT NULL DEFAULT '',
  `author`        VARCHAR(255) NOT NULL DEFAULT '',
  `license`       VARCHAR(120) NOT NULL DEFAULT '',
  `source`        VARCHAR(80)  NOT NULL DEFAULT '',
  `source_url`    VARCHAR(500) NOT NULL DEFAULT '',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_credit_rest` (`restaurant_id`),
  KEY `idx_credit_entity` (`entity`,`entity_id`),
  CONSTRAINT `fk_credit_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket_key`   VARCHAR(190) NOT NULL,
  `hits`         INT NOT NULL DEFAULT 0,
  `window_start` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bucket` (`bucket_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
