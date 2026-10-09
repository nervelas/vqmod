<?php
declare(strict_types=1);
namespace S5\Core;

/** Definición de tablas del portal. Se crea desde el instalador y se verifica en diagnóstico. */
final class Schema
{
    public static function statements(string $p): array
    {
        $e = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS `{$p}users` (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              email VARCHAR(190) NOT NULL UNIQUE,
              pass_hash VARCHAR(255) NOT NULL,
              totp_secret TEXT NULL,
              totp_enabled TINYINT NOT NULL DEFAULT 0,
              created_at DATETIME NOT NULL
            ) $e",
            "CREATE TABLE IF NOT EXISTS `{$p}settings` (
              k VARCHAR(80) NOT NULL PRIMARY KEY,
              v MEDIUMTEXT NULL,
              is_secret TINYINT NOT NULL DEFAULT 0
            ) $e",
            "CREATE TABLE IF NOT EXISTS `{$p}hosts` (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(120) NOT NULL,
              kind VARCHAR(20) NOT NULL DEFAULT 'cpanel',
              config TEXT NULL,
              active TINYINT NOT NULL DEFAULT 1,
              created_at DATETIME NOT NULL
            ) $e",
            "CREATE TABLE IF NOT EXISTS `{$p}orders` (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              token CHAR(64) NOT NULL UNIQUE,
              preview_key CHAR(32) NOT NULL,
              status VARCHAR(30) NOT NULL DEFAULT 'borrador',
              plan VARCHAR(10) NOT NULL DEFAULT 'info',
              step VARCHAR(30) NULL,
              slug VARCHAR(60) NULL,
              fqdn VARCHAR(190) NULL,
              host_id INT UNSIGNED NULL,
              business_name VARCHAR(190) NULL,
              client_email VARCHAR(190) NULL,
              client_phone VARCHAR(40) NULL,
              data LONGTEXT NULL,
              analysis LONGTEXT NULL,
              analysis_state VARCHAR(20) NOT NULL DEFAULT 'ninguno',
              analysis_msg VARCHAR(255) NULL,
              analysis_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
              analysis_lock DATETIME NULL,
              texts LONGTEXT NULL,
              texts_source VARCHAR(10) NULL,
              regen_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
              site_path VARCHAR(500) NULL,
              db_name VARCHAR(80) NULL,
              db_user VARCHAR(80) NULL,
              db_pass TEXT NULL,
              wp_user VARCHAR(80) NULL,
              wp_pass TEXT NULL,
              wp_prefix VARCHAR(20) NULL,
              build_state LONGTEXT NULL,
              build_msg VARCHAR(500) NULL,
              build_lock DATETIME NULL,
              build_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
              qa_result LONGTEXT NULL,
              pay_name VARCHAR(190) NULL,
              pay_file_id INT UNSIGNED NULL,
              pay_reject_reason VARCHAR(500) NULL,
              paid_at DATETIME NULL,
              published_at DATETIME NULL,
              renewal_at DATE NULL,
              renewal_notified TINYINT NOT NULL DEFAULT 0,
              domain_requested VARCHAR(190) NULL,
              domain_assigned VARCHAR(190) NULL,
              emails_requested TEXT NULL,
              card_extra TINYINT NOT NULL DEFAULT 0,
              is_demo TINYINT NOT NULL DEFAULT 0,
              ip VARCHAR(45) NULL,
              user_agent VARCHAR(255) NULL,
              started_at DATETIME NULL,
              expires_at DATETIME NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              INDEX (status), INDEX (slug), INDEX (preview_key), INDEX (renewal_at), INDEX (updated_at)
            ) $e",
            "CREATE TABLE IF NOT EXISTS `{$p}build_steps` (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              order_id INT UNSIGNED NOT NULL,
              step_key VARCHAR(40) NOT NULL,
              status VARCHAR(12) NOT NULL DEFAULT 'pending',
              attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
              message VARCHAR(500) NULL,
              state TEXT NULL,
              started_at DATETIME NULL,
              finished_at DATETIME NULL,
              UNIQUE KEY ord_step (order_id, step_key)
            ) $e",
            "CREATE TABLE IF NOT EXISTS `{$p}files` (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              order_id INT UNSIGNED NOT NULL,
              kind VARCHAR(20) NOT NULL,
              orig_name VARCHAR(255) NULL,
              stored VARCHAR(80) NOT NULL,
              mime VARCHAR(80) NOT NULL,
              size INT UNSIGNED NOT NULL DEFAULT 0,
              w INT UNSIGNED NULL,
              h INT UNSIGNED NULL,
              source VARCHAR(20) NOT NULL DEFAULT 'cliente',
              created_at DATETIME NOT NULL,
              INDEX (order_id), INDEX (kind)
            ) $e",
            "CREATE TABLE IF NOT EXISTS `{$p}ai_usage` (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              kind VARCHAR(12) NOT NULL,
              model VARCHAR(60) NOT NULL,
              tokens_in INT UNSIGNED NOT NULL DEFAULT 0,
              tokens_out INT UNSIGNED NOT NULL DEFAULT 0,
              cost_usd DECIMAL(10,6) NOT NULL DEFAULT 0,
              order_id INT UNSIGNED NULL,
              ok TINYINT NOT NULL DEFAULT 1,
              created_at DATETIME NOT NULL,
              INDEX (created_at), INDEX (kind)
            ) $e",
            "CREATE TABLE IF NOT EXISTS `{$p}rate_limits` (
              k CHAR(40) NOT NULL PRIMARY KEY,
              cnt INT UNSIGNED NOT NULL DEFAULT 0,
              win_start INT UNSIGNED NOT NULL
            ) $e",
            "CREATE TABLE IF NOT EXISTS `{$p}audit_log` (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              created_at DATETIME NOT NULL,
              actor VARCHAR(60) NULL,
              action VARCHAR(80) NOT NULL,
              detail TEXT NULL,
              order_id INT UNSIGNED NULL,
              ip VARCHAR(45) NULL,
              INDEX (created_at), INDEX (order_id)
            ) $e",
            "CREATE TABLE IF NOT EXISTS `{$p}meta` (
              k VARCHAR(60) NOT NULL PRIMARY KEY,
              v TEXT NULL
            ) $e",
            "CREATE TABLE IF NOT EXISTS `{$p}alerts` (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              level VARCHAR(10) NOT NULL DEFAULT 'info',
              message VARCHAR(500) NOT NULL,
              order_id INT UNSIGNED NULL,
              resolved TINYINT NOT NULL DEFAULT 0,
              created_at DATETIME NOT NULL,
              INDEX (resolved)
            ) $e",
        ];
    }

    public static function install(\PDO $pdo, string $prefix): void
    {
        foreach (self::statements($prefix) as $sql) {
            $pdo->exec($sql);
        }
    }
}
