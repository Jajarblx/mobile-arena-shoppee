CREATE DATABASE IF NOT EXISTS mobile_arena_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE mobile_arena_db;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch` INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `migrations` (`migration`,`batch`) VALUES ('2026_09_22_000100_create_storefront_tables',1);

DROP TABLE IF EXISTS `order_items`;

DROP TABLE IF EXISTS `orders`;

DROP TABLE IF EXISTS `repair_bookings`;

DROP TABLE IF EXISTS `products`;

DROP TABLE IF EXISTS `categories`;

CREATE TABLE `categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `icon` VARCHAR(255) NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `brand` VARCHAR(255) NOT NULL,
  `condition` ENUM('brand_new','pre_owned','refurbished') NOT NULL,
  `grade` VARCHAR(255) NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `compare_price` DECIMAL(12,2) NULL,
  `stock` INT UNSIGNED NOT NULL DEFAULT 0,
  `sku` VARCHAR(255) NOT NULL UNIQUE,
  `short_description` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `specifications` JSON NULL,
  `image` VARCHAR(255) NULL,
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `warranty_note` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `products_condition_active_index` (`condition`,`active`),
  KEY `products_brand_active_index` (`brand`,`active`),
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(255) NOT NULL UNIQUE,
  `customer_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NULL,
  `phone` VARCHAR(255) NOT NULL,
  `fulfillment` ENUM('pickup','delivery_request') NOT NULL DEFAULT 'pickup',
  `address` TEXT NULL,
  `payment_method` VARCHAR(255) NOT NULL DEFAULT 'pay_at_store',
  `subtotal` DECIMAL(12,2) NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'pending',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `unit_price` DECIMAL(12,2) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `condition` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `repair_bookings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(255) NOT NULL UNIQUE,
  `customer_name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NULL,
  `device_type` VARCHAR(255) NOT NULL,
  `brand_model` VARCHAR(255) NOT NULL,
  `issue` TEXT NOT NULL,
  `preferred_date` DATE NULL,
  `service_type` VARCHAR(255) NOT NULL DEFAULT 'diagnostic',
  `status` VARCHAR(255) NOT NULL DEFAULT 'requested',
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `sort_order`, `created_at`, `updated_at`) VALUES (1, 'Smartphones', 'smartphones', 'phone', 1, '2026-09-22 00:53:53', '2026-09-22 00:53:53');

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `sort_order`, `created_at`, `updated_at`) VALUES (2, 'Tablets', 'tablets', 'tablet', 2, '2026-09-22 00:53:53', '2026-09-22 00:53:53');

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `sort_order`, `created_at`, `updated_at`) VALUES (3, 'Accessories', 'accessories', 'cable', 3, '2026-09-22 00:53:53', '2026-09-22 00:53:53');

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `sort_order`, `created_at`, `updated_at`) VALUES (4, 'Wearables', 'wearables', 'watch', 4, '2026-09-22 00:53:53', '2026-09-22 00:53:53');

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `brand`, `condition`, `grade`, `price`, `compare_price`, `stock`, `sku`, `short_description`, `description`, `specifications`, `image`, `featured`, `active`, `warranty_note`, `created_at`, `updated_at`) VALUES (1, 1, 'iPhone 13 128GB', 'iphone-13-128gb-brand-new', 'Apple', 'brand_new', NULL, 29990, 32990, 5, 'MA-IP13-128-BN', 'Demo listing for a sealed, brand-new smartphone unit.', 'A prototype product listing showing how Mobile Arena can present brand-new stock with clear warranty, condition, stock and specification information.', '{"Storage": "128GB", "Display": "6.1-inch", "Network": "5G", "Color": "Blue"}', 'https://commons.wikimedia.org/wiki/Special:Redirect/file/IPhone%2013.jpg?width=900', 1, 1, 'Prototype warranty text — replace with Mobile Arena’s actual warranty policy.', '2026-09-22 00:53:53', '2026-09-22 00:53:53');

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `brand`, `condition`, `grade`, `price`, `compare_price`, `stock`, `sku`, `short_description`, `description`, `specifications`, `image`, `featured`, `active`, `warranty_note`, `created_at`, `updated_at`) VALUES (2, 1, 'iPhone 12 128GB', 'iphone-12-128gb-pre-owned', 'Apple', 'pre_owned', 'Grade A', 18990, 21990, 2, 'MA-IP12-128-PO', 'Pre-owned demo unit with a condition grade and inspection notes.', 'This prototype demonstrates transparent second-hand listings. Actual battery health, cosmetic condition, inclusions and warranty should be entered per physical unit before publishing.', '{"Storage": "128GB", "Condition": "Grade A", "Inspection": "Demo: tested basic functions", "Inclusions": "Demo: unit + cable"}', 'https://commons.wikimedia.org/wiki/Special:Redirect/file/IPhone%2012%20Black%20256g.jpg?width=900', 1, 1, 'Prototype: unit-specific warranty and inspection details must be confirmed by store staff.', '2026-09-22 00:53:53', '2026-09-22 00:53:53');

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `brand`, `condition`, `grade`, `price`, `compare_price`, `stock`, `sku`, `short_description`, `description`, `specifications`, `image`, `featured`, `active`, `warranty_note`, `created_at`, `updated_at`) VALUES (3, 1, 'Galaxy A56 5G', 'galaxy-a56-5g-brand-new', 'Samsung', 'brand_new', NULL, 23990, 25990, 6, 'MA-SA56-BN', 'Brand-new Android phone demo listing with stock visibility.', 'Sample catalog content for a modern Android phone. Replace demo pricing and availability with Mobile Arena’s actual inventory before public launch.', '{"Network": "5G", "Storage": "128GB", "SIM": "Dual SIM", "Condition": "Brand New"}', 'https://commons.wikimedia.org/wiki/Special:Redirect/file/Samsung%20Galaxy%20A56%205G%202025%20%282%29.jpg?width=900', 1, 1, 'Prototype warranty text — verify actual store/manufacturer coverage.', '2026-09-22 00:53:53', '2026-09-22 00:53:53');

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `brand`, `condition`, `grade`, `price`, `compare_price`, `stock`, `sku`, `short_description`, `description`, `specifications`, `image`, `featured`, `active`, `warranty_note`, `created_at`, `updated_at`) VALUES (4, 1, 'Galaxy S23 256GB', 'galaxy-s23-256gb-refurbished', 'Samsung', 'refurbished', 'Refurbished A', 24990, 28990, 3, 'MA-S23-RF', 'Refurbished demo unit with condition grading and service history fields.', 'Prototype refurbished listing designed to disclose refurbishment status, inspection results, inclusions and warranty notes clearly.', '{"Storage": "256GB", "Condition": "Refurbished A", "Inspection": "Demo multi-point check", "Network": "5G"}', 'https://commons.wikimedia.org/wiki/Special:Redirect/file/Back%20of%20the%20Samsung%20Galaxy%20S23.jpg?width=900', 1, 1, 'Prototype: replace with the actual refurbishment warranty and work performed.', '2026-09-22 00:53:53', '2026-09-22 00:53:53');

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `brand`, `condition`, `grade`, `price`, `compare_price`, `stock`, `sku`, `short_description`, `description`, `specifications`, `image`, `featured`, `active`, `warranty_note`, `created_at`, `updated_at`) VALUES (5, 1, 'Redmi Note Series 256GB', 'redmi-note-series-256gb', 'Xiaomi', 'brand_new', NULL, 12990, 13990, 8, 'MA-RN-256-BN', 'Value-focused brand-new Android demo listing.', 'Sample inventory entry for the prototype. Exact model, memory variant, price and stock should be imported from Mobile Arena’s real inventory.', '{"Storage": "256GB", "Network": "4G/5G varies by model", "Condition": "Brand New"}', 'https://commons.wikimedia.org/wiki/Special:Redirect/file/Redmi%20Note%2014%20-%202024-09-27%2001.jpg?width=900', 1, 1, 'Prototype warranty text — verify before launch.', '2026-09-22 00:53:53', '2026-09-22 00:53:53');

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `brand`, `condition`, `grade`, `price`, `compare_price`, `stock`, `sku`, `short_description`, `description`, `specifications`, `image`, `featured`, `active`, `warranty_note`, `created_at`, `updated_at`) VALUES (6, 1, 'Vivo V Series 256GB', 'vivo-v-series-256gb', 'Vivo', 'brand_new', NULL, 17999, 19999, 4, 'MA-VIVO-V-BN', 'Brand-new Vivo demo listing for the Mobile Arena catalog.', 'Vivo is included as a sample brand in the prototype. Public merchant listings show Mobile Arena Cellphone and Accessories at Xentro Mall Calapan, but this exact stock item is not claimed as current store inventory.', '{"Storage": "256GB", "Condition": "Brand New", "Inventory": "Demo only"}', 'https://asia-exstatic-vivofs.vivo.com/PSee2l50xoirPK7y/1740648602489/8407ffaf300f069cdccc58238682afc0.png', 1, 1, 'Prototype warranty text — verify before launch.', '2026-09-22 00:53:53', '2026-09-22 00:53:53');

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `brand`, `condition`, `grade`, `price`, `compare_price`, `stock`, `sku`, `short_description`, `description`, `specifications`, `image`, `featured`, `active`, `warranty_note`, `created_at`, `updated_at`) VALUES (7, 2, 'iPad 10th Gen 64GB', 'ipad-10th-gen-pre-owned', 'Apple', 'pre_owned', 'Grade B+', 19990, 22990, 1, 'MA-IPAD10-PO', 'Pre-owned tablet demo listing with transparent condition grading.', 'A sample second-hand tablet listing intended to demonstrate per-unit condition and inspection disclosure.', '{"Storage": "64GB", "Condition": "Grade B+", "Inventory": "Demo only"}', 'https://commons.wikimedia.org/wiki/Special:Redirect/file/Ipadtenthgen.jpg?width=900', 0, 1, 'Prototype: warranty and inclusions must be confirmed per unit.', '2026-09-22 00:53:53', '2026-09-22 00:53:53');

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `brand`, `condition`, `grade`, `price`, `compare_price`, `stock`, `sku`, `short_description`, `description`, `specifications`, `image`, `featured`, `active`, `warranty_note`, `created_at`, `updated_at`) VALUES (8, 3, '20W USB-C Fast Charger', '20w-usb-c-fast-charger', 'Mobile Arena', 'brand_new', NULL, 699, 899, 15, 'MA-ACC-20W', 'Demo accessory listing for chargers and everyday essentials.', 'Prototype accessory item. Replace branding, compatibility claims and pricing with actual store stock.', '{"Output": "20W demo", "Connector": "USB-C", "Inventory": "Demo only"}', 'https://commons.wikimedia.org/wiki/Special:Redirect/file/SAMSUNG%20EP-TA800%2025W%20POWER%20ADAPER%20WHITE%20%284%29.jpg?width=900', 1, 1, 'Prototype warranty text.', '2026-09-22 00:53:53', '2026-09-22 00:53:53');

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `brand`, `condition`, `grade`, `price`, `compare_price`, `stock`, `sku`, `short_description`, `description`, `specifications`, `image`, `featured`, `active`, `warranty_note`, `created_at`, `updated_at`) VALUES (9, 3, '10,000mAh Power Bank', '10000mah-power-bank', 'Mobile Arena', 'brand_new', NULL, 999, 1299, 12, 'MA-ACC-PB10', 'Portable power demo listing for the accessories catalog.', 'Prototype accessory listing. Actual capacity, certifications and brand should be verified before publishing.', '{"Capacity": "10,000mAh demo", "Inventory": "Demo only"}', 'https://commons.wikimedia.org/wiki/Special:Redirect/file/Powerbank%20Xiaomi%2C%201.jpg?width=900', 1, 1, 'Prototype warranty text.', '2026-09-22 00:53:53', '2026-09-22 00:53:53');

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `brand`, `condition`, `grade`, `price`, `compare_price`, `stock`, `sku`, `short_description`, `description`, `specifications`, `image`, `featured`, `active`, `warranty_note`, `created_at`, `updated_at`) VALUES (10, 4, 'Smart Watch Series', 'smart-watch-series', 'Mobile Arena', 'brand_new', NULL, 1499, 1799, 7, 'MA-WEAR-SW', 'Wearable demo listing with simple product specification support.', 'Prototype wearable listing; replace with actual brand/model and verified technical specifications.', '{"Compatibility": "Demo: Android/iOS", "Inventory": "Demo only"}', 'https://commons.wikimedia.org/wiki/Special:Redirect/file/Mi%20Watch.jpg?width=900', 0, 1, 'Prototype warranty text.', '2026-09-22 00:53:53', '2026-09-22 00:53:53');

SET FOREIGN_KEY_CHECKS=1;
