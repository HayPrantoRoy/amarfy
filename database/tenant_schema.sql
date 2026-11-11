-- Amarfy tenant database schema (one database per store: amarfy_100001, ...)
CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(64) NOT NULL PRIMARY KEY,
  `v` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `staff` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `master_user_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `role` VARCHAR(32) NOT NULL DEFAULT 'owner',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `locations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `address` VARCHAR(255) NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(120) NOT NULL DEFAULT '',
  `last_name` VARCHAR(120) NOT NULL DEFAULT '',
  `email` VARCHAR(255) NULL,
  `phone` VARCHAR(40) NULL,
  `company` VARCHAR(255) NULL,
  `address1` VARCHAR(255) NULL,
  `address2` VARCHAR(255) NULL,
  `city` VARCHAR(120) NULL,
  `zip` VARCHAR(40) NULL,
  `country` VARCHAR(120) NULL DEFAULT 'Bangladesh',
  `tags` VARCHAR(500) NULL,
  `note` TEXT NULL,
  `accepts_marketing` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `path` VARCHAR(500) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `mime` VARCHAR(100) NULL,
  `size` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `handle` VARCHAR(255) NULL,
  `description_html` MEDIUMTEXT NULL,
  `status` ENUM('active','draft','archived') NOT NULL DEFAULT 'active',
  `category` VARCHAR(255) NULL,
  `product_type` VARCHAR(255) NULL,
  `vendor` VARCHAR(255) NULL,
  `theme_template` VARCHAR(100) NOT NULL DEFAULT 'Default product',
  `tags` VARCHAR(1000) NULL,
  `options_json` TEXT NULL,
  `is_gift_card` TINYINT(1) NOT NULL DEFAULT 0,
  `charge_tax` TINYINT(1) NOT NULL DEFAULT 1,
  `track_inventory` TINYINT(1) NOT NULL DEFAULT 1,
  `requires_shipping` TINYINT(1) NOT NULL DEFAULT 1,
  `weight` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `weight_unit` VARCHAR(8) NOT NULL DEFAULT 'kg',
  `length` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `width` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `height` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `dim_unit` VARCHAR(8) NOT NULL DEFAULT 'cm',
  `package` VARCHAR(255) NULL,
  `origin_country` VARCHAR(120) NULL,
  `hs_code` VARCHAR(40) NULL,
  `metafields_json` TEXT NULL,
  `purchase_options_json` TEXT NULL,
  `seo_title` VARCHAR(255) NULL,
  `seo_description` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_media` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `file_id` INT UNSIGNED NULL,
  `path` VARCHAR(500) NOT NULL,
  `position` INT NOT NULL DEFAULT 0,
  KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `variants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL DEFAULT 'Default Title',
  `option1` VARCHAR(255) NULL,
  `option2` VARCHAR(255) NULL,
  `option3` VARCHAR(255) NULL,
  `price` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `compare_at_price` DECIMAL(14,2) NULL,
  `cost` DECIMAL(14,2) NULL,
  `unit_price` DECIMAL(14,2) NULL,
  `sku` VARCHAR(120) NULL,
  `barcode` VARCHAR(120) NULL,
  `sell_out_of_stock` TINYINT(1) NOT NULL DEFAULT 0,
  `position` INT NOT NULL DEFAULT 0,
  KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_levels` (
  `variant_id` INT UNSIGNED NOT NULL,
  `location_id` INT UNSIGNED NOT NULL,
  `available` INT NOT NULL DEFAULT 0,
  `committed` INT NOT NULL DEFAULT 0,
  `unavailable` INT NOT NULL DEFAULT 0,
  `incoming` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`variant_id`, `location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `collections` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `handle` VARCHAR(255) NULL,
  `description_html` MEDIUMTEXT NULL,
  `image_path` VARCHAR(500) NULL,
  `type` ENUM('manual','smart') NOT NULL DEFAULT 'manual',
  `match_all` TINYINT(1) NOT NULL DEFAULT 1,
  `conditions_json` TEXT NULL,
  `excluded_json` TEXT NULL,
  `theme_template` VARCHAR(100) NOT NULL DEFAULT 'Default collection',
  `channels` INT NOT NULL DEFAULT 3,
  `seo_title` VARCHAR(255) NULL,
  `seo_description` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `collection_products` (
  `collection_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `position` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`collection_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `draft_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `number` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NULL,
  `status` ENUM('open','invoice_sent','completed') NOT NULL DEFAULT 'open',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'BDT',
  `po_number` VARCHAR(120) NULL,
  `tags` VARCHAR(1000) NULL,
  `note` TEXT NULL,
  `discount_type` VARCHAR(16) NULL,
  `discount_value` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `discount_reason` VARCHAR(255) NULL,
  `shipping_title` VARCHAR(255) NULL,
  `shipping_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `payment_due_later` TINYINT(1) NOT NULL DEFAULT 0,
  `payment_terms` VARCHAR(64) NULL,
  `shipping_address_json` TEXT NULL,
  `order_id` INT UNSIGNED NULL,
  `updated_by` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `draft_order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `draft_order_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NULL,
  `product_id` INT UNSIGNED NULL,
  `title` VARCHAR(255) NOT NULL,
  `variant_title` VARCHAR(255) NULL,
  `image` VARCHAR(500) NULL,
  `price` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `quantity` INT NOT NULL DEFAULT 1,
  `taxable` TINYINT(1) NOT NULL DEFAULT 1,
  `requires_shipping` TINYINT(1) NOT NULL DEFAULT 1,
  KEY (`draft_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `number` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NULL,
  `draft_order_id` INT UNSIGNED NULL,
  `location_id` INT UNSIGNED NULL,
  `channel` VARCHAR(64) NOT NULL DEFAULT 'Online Store',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'BDT',
  `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `discount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `shipping` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `tax` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `payment_status` ENUM('pending','paid','refunded','voided') NOT NULL DEFAULT 'pending',
  `fulfillment_status` ENUM('unfulfilled','fulfilled') NOT NULL DEFAULT 'unfulfilled',
  `delivery_status` VARCHAR(64) NULL,
  `delivery_method` VARCHAR(255) NULL,
  `payment_terms` VARCHAR(64) NULL,
  `fulfill_by` DATE NULL,
  `tags` VARCHAR(1000) NULL,
  `note` TEXT NULL,
  `shipping_address_json` TEXT NULL,
  `archived` TINYINT(1) NOT NULL DEFAULT 0,
  `cancelled_at` DATETIME NULL,
  `fulfilled_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NULL,
  `product_id` INT UNSIGNED NULL,
  `title` VARCHAR(255) NOT NULL,
  `variant_title` VARCHAR(255) NULL,
  `image` VARCHAR(500) NULL,
  `price` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `quantity` INT NOT NULL DEFAULT 1,
  KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `timeline` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `subject_type` VARCHAR(32) NOT NULL,
  `subject_id` INT UNSIGNED NOT NULL,
  `kind` ENUM('event','comment') NOT NULL DEFAULT 'event',
  `message` TEXT NOT NULL,
  `author` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (`subject_type`, `subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `checkouts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NULL,
  `email` VARCHAR(255) NULL,
  `total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `recovery_status` ENUM('not_recovered','recovered') NOT NULL DEFAULT 'not_recovered',
  `email_status` VARCHAR(32) NOT NULL DEFAULT 'Not sent',
  `completed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `contact_name` VARCHAR(255) NULL,
  `email` VARCHAR(255) NULL,
  `phone` VARCHAR(40) NULL,
  `address` VARCHAR(255) NULL,
  `country` VARCHAR(120) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `number` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NULL,
  `destination_id` INT UNSIGNED NULL,
  `status` ENUM('draft','ordered','partial','received','closed') NOT NULL DEFAULT 'draft',
  `reference` VARCHAR(255) NULL,
  `note` TEXT NULL,
  `terms` VARCHAR(64) NULL,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'BDT',
  `expected_arrival` DATE NULL,
  `carrier` VARCHAR(120) NULL,
  `tracking` VARCHAR(120) NULL,
  `shipping` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `tags` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NOT NULL,
  `supplier_sku` VARCHAR(120) NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `received` INT NOT NULL DEFAULT 0,
  `cost` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `tax_rate` DECIMAL(6,2) NOT NULL DEFAULT 0,
  KEY (`purchase_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transfers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `number` INT UNSIGNED NOT NULL,
  `origin_id` INT UNSIGNED NULL,
  `destination_id` INT UNSIGNED NULL,
  `status` ENUM('draft','in_transit','received') NOT NULL DEFAULT 'draft',
  `transfer_date` DATE NULL,
  `reference` VARCHAR(255) NULL,
  `note` TEXT NULL,
  `tags` VARCHAR(500) NULL,
  `purchase_order_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transfer_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `transfer_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `received` INT NOT NULL DEFAULT 0,
  KEY (`transfer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gift_cards` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(64) NOT NULL,
  `initial_value` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `balance` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'BDT',
  `expires_on` DATE NULL,
  `customer_id` INT UNSIGNED NULL,
  `recipient` VARCHAR(255) NULL,
  `note` TEXT NULL,
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
