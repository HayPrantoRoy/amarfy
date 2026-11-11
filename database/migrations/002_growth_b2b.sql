-- v2: customer segments, B2B companies, marketing (campaigns, traffic, attribution)
CREATE TABLE IF NOT EXISTS `segments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `rule_key` VARCHAR(40) NULL,
  `rules_json` TEXT NULL,
  `match_all` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` VARCHAR(255) NOT NULL DEFAULT 'Amarfy',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `companies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `external_id` VARCHAR(120) NULL,
  `ordering_approved` TINYINT(1) NOT NULL DEFAULT 1,
  `main_contact_id` INT UNSIGNED NULL,
  `staff_id` INT UNSIGNED NULL,
  `note` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_contacts` (
  `company_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`company_id`, `customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_locations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `address` VARCHAR(255) NULL,
  `city` VARCHAR(120) NULL,
  `country` VARCHAR(120) NULL DEFAULT 'Bangladesh',
  `payment_terms` VARCHAR(64) NULL,
  `checkout_mode` ENUM('auto','review') NOT NULL DEFAULT 'auto',
  `ship_to_address` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `channel` VARCHAR(40) NOT NULL DEFAULT 'email',
  `status` ENUM('draft','active','paused','completed') NOT NULL DEFAULT 'draft',
  `goal` VARCHAR(255) NULL,
  `budget` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `utm_campaign` VARCHAR(120) NULL,
  `starts_on` DATE NULL,
  `ends_on` DATE NULL,
  `autopilot` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `traffic_daily` (
  `day` DATE NOT NULL,
  `source` VARCHAR(20) NOT NULL,
  `sessions` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`day`, `source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
