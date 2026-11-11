-- Amarfy master database: one row per registered store owner.
-- Each user gets their own tenant database (amarfy_100001, amarfy_100002, ...).
CREATE TABLE IF NOT EXISTS `users` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(255) NOT NULL,
  `store_name`    VARCHAR(255) NOT NULL,
  `email`         VARCHAR(255) NOT NULL,
  `mobile_number` VARCHAR(32)  NOT NULL,
  `password`      VARCHAR(255) NOT NULL,
  `db_name`       VARCHAR(128) NULL,
  `tenant_number` INT UNSIGNED NULL,
  `upload_folder` VARCHAR(128) NULL,
  `status`        TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_mobile_unique` (`mobile_number`),
  UNIQUE KEY `users_db_unique` (`db_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_logs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NULL,
  `email`      VARCHAR(255) NOT NULL,
  `success`    TINYINT(1) NOT NULL DEFAULT 0,
  `ip`         VARCHAR(64) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `login_logs_email_idx` (`email`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
