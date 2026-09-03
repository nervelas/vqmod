-- =============================================================================
-- Servicom — Estructura de base de datos (MySQL 5.7+ / MariaDB 10.3+)
-- Codificacion: utf8mb4
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------- Usuarios del panel ------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `username`   VARCHAR(60)  NOT NULL,
  `email`      VARCHAR(160) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       VARCHAR(20)  NOT NULL DEFAULT 'admin',
  `status`     TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login` DATETIME     NULL,
  `created_at` DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ------------------------------------------------- Ajustes generales ---------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`        VARCHAR(80)  NOT NULL,
  `value`      TEXT         NULL,
  `group_name` VARCHAR(40)  NOT NULL DEFAULT 'general',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ---------------------------------------------------------- Temas ------------
DROP TABLE IF EXISTS `themes`;
CREATE TABLE `themes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `theme_key`   VARCHAR(40)  NOT NULL,
  `name`        VARCHAR(80)  NOT NULL,
  `mode`        VARCHAR(10)  NOT NULL DEFAULT 'dark',
  `description` VARCHAR(255) NULL,
  `palette`     TEXT         NULL,
  `fonts`       TEXT         NULL,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_themes_key` (`theme_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------- Paginas -----------
DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`            VARCHAR(191) NOT NULL,
  `title`           VARCHAR(200) NOT NULL,
  `subtitle`        VARCHAR(255) NULL,
  `template`        VARCHAR(40)  NOT NULL DEFAULT 'page',
  `body`            MEDIUMTEXT   NULL,
  `image`           VARCHAR(255) NULL,
  `meta_title`      VARCHAR(200) NULL,
  `meta_description` VARCHAR(320) NULL,
  `meta_keywords`   VARCHAR(320) NULL,
  `og_image`        VARCHAR(255) NULL,
  `robots`          VARCHAR(120) NULL,
  `is_system`       TINYINT(1)   NOT NULL DEFAULT 0,
  `show_in_sitemap` TINYINT(1)   NOT NULL DEFAULT 1,
  `priority`        VARCHAR(4)   NOT NULL DEFAULT '0.8',
  `status`          TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`      INT          NOT NULL DEFAULT 0,
  `updated_at`      DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ------------------------------------------ Bloques de contenido -------------
DROP TABLE IF EXISTS `blocks`;
CREATE TABLE `blocks` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`        VARCHAR(60)  NOT NULL,
  `area`       VARCHAR(60)  NOT NULL DEFAULT 'inicio',
  `label`      VARCHAR(120) NOT NULL,
  `eyebrow`    VARCHAR(160) NULL,
  `title`      VARCHAR(255) NULL,
  `subtitle`   VARCHAR(500) NULL,
  `body`       MEDIUMTEXT   NULL,
  `image`      VARCHAR(255) NULL,
  `icon`       VARCHAR(40)  NULL,
  `btn_text`   VARCHAR(80)  NULL,
  `btn_url`    VARCHAR(255) NULL,
  `btn2_text`  VARCHAR(80)  NULL,
  `btn2_url`   VARCHAR(255) NULL,
  `extra`      TEXT         NULL,
  `status`     TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blocks_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------------------------- Slider principal ------------
DROP TABLE IF EXISTS `slides`;
CREATE TABLE `slides` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `eyebrow`    VARCHAR(160) NULL,
  `title`      VARCHAR(255) NOT NULL,
  `highlight`  VARCHAR(160) NULL,
  `subtitle`   VARCHAR(500) NULL,
  `image`      VARCHAR(255) NULL,
  `image_alt`  VARCHAR(200) NULL,
  `align`      VARCHAR(20)  NOT NULL DEFAULT 'left',
  `btn1_text`  VARCHAR(80)  NULL,
  `btn1_url`   VARCHAR(255) NULL,
  `btn1_icon`  VARCHAR(40)  NULL,
  `btn2_text`  VARCHAR(80)  NULL,
  `btn2_url`   VARCHAR(255) NULL,
  `btn2_icon`  VARCHAR(40)  NULL,
  `status`     TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ---------------------------------------------------------- Servicios --------
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(191) NOT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `short_title` VARCHAR(80)  NULL,
  `icon`        VARCHAR(40)  NOT NULL DEFAULT 'web',
  `excerpt`     VARCHAR(500) NULL,
  `body`        MEDIUMTEXT   NULL,
  `features`    TEXT         NULL,
  `image`       VARCHAR(255) NULL,
  `image_alt`   VARCHAR(200) NULL,
  `price_text`  VARCHAR(120) NULL,
  `btn_text`    VARCHAR(80)  NULL,
  `meta_title`  VARCHAR(200) NULL,
  `meta_description` VARCHAR(320) NULL,
  `meta_keywords`    VARCHAR(320) NULL,
  `featured`    TINYINT(1)   NOT NULL DEFAULT 1,
  `status`      TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_services_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ------------------------------------------------- Menu de navegacion --------
DROP TABLE IF EXISTS `menu_items`;
CREATE TABLE `menu_items` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label`      VARCHAR(80)  NOT NULL,
  `url`        VARCHAR(255) NOT NULL,
  `icon`       VARCHAR(40)  NOT NULL DEFAULT 'inicio',
  `location`   VARCHAR(20)  NOT NULL DEFAULT 'header',
  `target`     VARCHAR(10)  NOT NULL DEFAULT '_self',
  `is_button`  TINYINT(1)   NOT NULL DEFAULT 0,
  `status`     TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------------------------------- Indicadores -----------
DROP TABLE IF EXISTS `stats`;
CREATE TABLE `stats` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `value`      VARCHAR(20)  NOT NULL,
  `prefix`     VARCHAR(10)  NULL,
  `suffix`     VARCHAR(10)  NULL,
  `label`      VARCHAR(120) NOT NULL,
  `icon`       VARCHAR(40)  NOT NULL DEFAULT 'chispa',
  `status`     TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- -------------------------------------------------- Proceso de trabajo -------
DROP TABLE IF EXISTS `process_steps`;
CREATE TABLE `process_steps` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(160) NOT NULL,
  `body`       TEXT         NULL,
  `icon`       VARCHAR(40)  NOT NULL DEFAULT 'chispa',
  `status`     TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ------------------------------------------------------- Portafolio ----------
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(200) NOT NULL,
  `category`   VARCHAR(80)  NULL,
  `description` VARCHAR(500) NULL,
  `image`      VARCHAR(255) NULL,
  `image_alt`  VARCHAR(200) NULL,
  `url`        VARCHAR(255) NULL,
  `status`     TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------------------------------- Testimonios -----------
DROP TABLE IF EXISTS `testimonials`;
CREATE TABLE `testimonials` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `role`       VARCHAR(160) NULL,
  `body`       TEXT         NOT NULL,
  `rating`     TINYINT      NOT NULL DEFAULT 5,
  `avatar`     VARCHAR(255) NULL,
  `status`     TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ------------------------------------------------ Preguntas frecuentes -------
DROP TABLE IF EXISTS `faqs`;
CREATE TABLE `faqs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question`   VARCHAR(255) NOT NULL,
  `answer`     TEXT         NOT NULL,
  `status`     TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ------------------------------------------------------------ Planes ---------
DROP TABLE IF EXISTS `plans`;
CREATE TABLE `plans` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `tagline`    VARCHAR(200) NULL,
  `price_text` VARCHAR(120) NULL,
  `features`   TEXT         NULL,
  `btn_text`   VARCHAR(80)  NULL,
  `btn_url`    VARCHAR(255) NULL,
  `featured`   TINYINT(1)   NOT NULL DEFAULT 0,
  `icon`       VARCHAR(40)  NOT NULL DEFAULT 'planes',
  `status`     TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------------------------------------- Blog ------------
DROP TABLE IF EXISTS `posts`;
CREATE TABLE `posts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`         VARCHAR(191) NOT NULL,
  `title`        VARCHAR(220) NOT NULL,
  `excerpt`      VARCHAR(500) NULL,
  `body`         MEDIUMTEXT   NULL,
  `image`        VARCHAR(255) NULL,
  `image_alt`    VARCHAR(200) NULL,
  `author`       VARCHAR(120) NULL,
  `meta_title`   VARCHAR(200) NULL,
  `meta_description` VARCHAR(320) NULL,
  `meta_keywords`    VARCHAR(320) NULL,
  `published_at` DATETIME     NULL,
  `status`       TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_posts_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ---------------------------------------------------- Biblioteca media -------
DROP TABLE IF EXISTS `media`;
CREATE TABLE `media` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename`   VARCHAR(200) NOT NULL,
  `path`       VARCHAR(255) NOT NULL,
  `mime`       VARCHAR(80)  NULL,
  `size`       INT          NOT NULL DEFAULT 0,
  `width`      INT          NOT NULL DEFAULT 0,
  `height`     INT          NOT NULL DEFAULT 0,
  `alt`        VARCHAR(200) NULL,
  `created_at` DATETIME     NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ------------------------------------------------- Mensajes recibidos --------
DROP TABLE IF EXISTS `submissions`;
CREATE TABLE `submissions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(160) NOT NULL,
  `email`      VARCHAR(190) NULL,
  `phone`      VARCHAR(60)  NULL,
  `service`    VARCHAR(160) NULL,
  `subject`    VARCHAR(200) NULL,
  `message`    TEXT         NULL,
  `page`       VARCHAR(200) NULL,
  `ip`         VARCHAR(60)  NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

SET FOREIGN_KEY_CHECKS = 1;
