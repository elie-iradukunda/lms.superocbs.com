-- LMS - Logistics Management System
-- One-file install for a new database.
--
-- Import this into an empty database and the system is ready to sign in to.
-- It contains:
--
--   every table the code expects, with its keys and foreign keys
--   the reference data a company cannot start without: the roles and what each
--   one may do, the chart of accounts, the currencies, the payment methods,
--   the drop-down lists, the border posts and the accounting periods
--   the sign-in accounts
--
-- It contains no business data: no vehicles, no customers, no trips, no
-- invoices. Those are yours to enter.
--
-- It also names no company. The trading name, address, tax number and logo are
-- Settings, not code, and they ship blank so that nothing you print or send can
-- go out carrying somebody else's details. Fill them in under
-- Administration > Settings before you issue anything.
--
-- IMPORTANT, and this is the one thing not to skip: every account below ships
-- with a password that is published in the source code. Each one is marked as
-- having to choose a new password the first time it signs in. Do not undo that.
--
-- How to import it in cPanel:
--   phpMyAdmin -> pick your database -> Import -> choose this file -> Go

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action_name` varchar(100) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` varchar(64) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=316 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `border_charges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `border_charges` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `crossing_id` int(10) unsigned NOT NULL,
  `charge_type` varchar(60) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `receipt_no` varchar(60) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_border_charge` (`crossing_id`),
  CONSTRAINT `fk_border_charge` FOREIGN KEY (`crossing_id`) REFERENCES `border_crossings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `border_crossings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `border_crossings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(40) NOT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `shipment_id` int(10) unsigned DEFAULT NULL,
  `vehicle_id` int(10) unsigned DEFAULT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `border_post_id` int(10) unsigned NOT NULL,
  `direction` enum('export','import','transit') NOT NULL DEFAULT 'export',
  `clearing_agent_id` int(10) unsigned DEFAULT NULL,
  `declaration_no` varchar(60) DEFAULT NULL COMMENT 'the customs declaration or entry number',
  `transit_bond_no` varchar(60) DEFAULT NULL COMMENT 'T1 or regional bond reference',
  `seal_no` varchar(60) DEFAULT NULL,
  `weighbridge_kg` decimal(12,2) DEFAULT NULL COMMENT 'what the axle scale said',
  `arrived_at` datetime DEFAULT NULL,
  `lodged_at` datetime DEFAULT NULL,
  `cleared_at` datetime DEFAULT NULL,
  `departed_at` datetime DEFAULT NULL,
  `status` enum('expected','at_border','lodged','held','cleared','departed') NOT NULL DEFAULT 'expected',
  `hold_reason` varchar(255) DEFAULT NULL COMMENT 'why it is not moving: papers, inspection, payment',
  `charges_total` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'added up from the charge lines',
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `exchange_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_amount` decimal(14,2) DEFAULT NULL COMMENT 'what the charges come to in the books',
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crossing_reference` (`reference`),
  KEY `idx_crossing_post` (`border_post_id`,`arrived_at`),
  KEY `idx_crossing_trip` (`trip_id`),
  KEY `idx_crossing_status` (`status`),
  KEY `idx_crossing_deleted` (`deleted_at`),
  CONSTRAINT `fk_crossing_post` FOREIGN KEY (`border_post_id`) REFERENCES `border_posts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `border_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `border_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `crossing_id` int(10) unsigned NOT NULL,
  `document_type` varchar(60) NOT NULL,
  `document_no` varchar(80) DEFAULT NULL,
  `issued_on` date DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `is_received` tinyint(1) NOT NULL DEFAULT 0,
  `document_file` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_border_document` (`crossing_id`),
  CONSTRAINT `fk_border_document` FOREIGN KEY (`crossing_id`) REFERENCES `border_crossings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `border_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `border_posts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `post_name` varchar(120) NOT NULL,
  `post_code` varchar(20) DEFAULT NULL COMMENT 'the customs office code, when there is one',
  `country_a` varchar(60) NOT NULL COMMENT 'the side this company is usually on',
  `country_b` varchar(60) NOT NULL,
  `is_one_stop` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'both authorities under one roof',
  `typical_hours` decimal(6,2) DEFAULT NULL COMMENT 'what a normal crossing takes here, for planning',
  `contact_name` varchar(120) DEFAULT NULL,
  `contact_phone` varchar(40) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_border_post` (`post_name`),
  KEY `idx_border_active` (`is_active`,`post_name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(60) NOT NULL,
  `setting_value` varchar(255) DEFAULT NULL,
  `setting_label` varchar(120) NOT NULL,
  `setting_group` varchar(60) NOT NULL DEFAULT 'Company',
  `input_type` varchar(20) NOT NULL DEFAULT 'text',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `currencies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` char(3) NOT NULL COMMENT 'ISO 4217: RWF, KES, TZS, USD',
  `currency_name` varchar(60) NOT NULL,
  `symbol` varchar(8) DEFAULT NULL,
  `country` varchar(60) DEFAULT NULL,
  `decimals` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'RWF and TZS are whole units; USD and KES take cents',
  `is_base` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 100,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_currency_code` (`code`),
  KEY `idx_currency_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currency_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `currency_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `currency_code` char(3) NOT NULL,
  `effective_from` date NOT NULL,
  `rate` decimal(18,8) NOT NULL,
  `source` varchar(80) DEFAULT NULL COMMENT 'where the rate came from: BNR, the bank, an agreement',
  `notes` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_day` (`currency_code`,`effective_from`),
  KEY `idx_rate_lookup` (`currency_code`,`effective_from`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(32) NOT NULL,
  `customer_name` varchar(180) NOT NULL,
  `customer_type` enum('corporate','government','ngo','individual') NOT NULL DEFAULT 'corporate',
  `contact_name` varchar(150) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `district` varchar(80) DEFAULT NULL,
  `tin_number` varchar(40) DEFAULT NULL,
  `payment_terms_days` int(10) unsigned NOT NULL DEFAULT 30,
  `credit_limit` decimal(14,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `status` enum('active','on_hold','inactive') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_code` (`customer_code`),
  KEY `idx_customers_status` (`status`),
  KEY `idx_customers_deleted` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deliveries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `delivery_code` varchar(32) NOT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `shipment_id` int(10) unsigned DEFAULT NULL,
  `recipient_name` varchar(150) NOT NULL,
  `recipient_phone` varchar(40) DEFAULT NULL,
  `destination` varchar(150) NOT NULL,
  `destination_warehouse_id` int(10) unsigned DEFAULT NULL,
  `planned_at` datetime DEFAULT NULL,
  `dropped_at` datetime DEFAULT NULL,
  `status` enum('loading','in_transit','at_destination','delivered','failed') NOT NULL DEFAULT 'loading',
  `attempt_number` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `failure_reason` enum('none','recipient_absent','address_wrong','goods_damaged','goods_refused','vehicle_breakdown','access_denied','weather','other') NOT NULL DEFAULT 'none',
  `failure_notes` varchar(255) DEFAULT NULL,
  `rescheduled_at` datetime DEFAULT NULL,
  `proof_file` varchar(255) DEFAULT NULL,
  `recipient_signature` varchar(255) DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `delivered_by` int(10) unsigned DEFAULT NULL,
  `collected_by` varchar(150) DEFAULT NULL,
  `collected_id_no` varchar(60) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_code` (`delivery_code`),
  KEY `trip_id` (`trip_id`),
  KEY `fk_deliveries_shipment` (`shipment_id`),
  KEY `idx_deliveries_status` (`status`),
  KEY `idx_deliveries_deleted` (`deleted_at`),
  KEY `fk_delivery_warehouse` (`destination_warehouse_id`),
  CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_deliveries_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_delivery_warehouse` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `drivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `drivers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `national_id` varchar(40) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `license_number` varchar(80) NOT NULL,
  `license_class` varchar(20) DEFAULT NULL,
  `license_expiry` date DEFAULT NULL,
  `emergency_contact` varchar(150) DEFAULT NULL,
  `emergency_phone` varchar(40) DEFAULT NULL,
  `hired_on` date DEFAULT NULL,
  `status` enum('available','on_trip','off_duty','inactive') NOT NULL DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `license_number` (`license_number`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `uq_drivers_user_id` (`user_id`),
  KEY `idx_drivers_status` (`status`),
  KEY `idx_drivers_deleted` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_outbox` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `message_key` varchar(120) NOT NULL,
  `category` enum('notification','alert','password_reset','new_account','invoice','manual','test') NOT NULL DEFAULT 'notification',
  `to_email` varchar(190) NOT NULL,
  `to_name` varchar(150) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `body_html` mediumtext NOT NULL,
  `body_text` mediumtext DEFAULT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` varchar(64) DEFAULT NULL,
  `status` enum('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
  `provider_id` varchar(120) DEFAULT NULL,
  `error` varchar(500) DEFAULT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_key` (`message_key`),
  KEY `user_id` (`user_id`),
  KEY `idx_outbox_status` (`status`,`created_at`),
  KEY `idx_outbox_recipient` (`to_email`),
  KEY `idx_outbox_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `email_outbox_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference_code` varchar(32) DEFAULT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `vehicle_id` int(10) unsigned DEFAULT NULL,
  `category` varchar(60) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `exchange_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_amount` decimal(14,2) DEFAULT NULL,
  `submitted_by` int(10) unsigned DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','mobile_money','fuel_card','cheque') NOT NULL DEFAULT 'cash',
  `receipt_file` varchar(255) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_code` (`reference_code`),
  KEY `trip_id` (`trip_id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `fk_expenses_approver` (`approved_by`),
  KEY `idx_expenses_date` (`expense_date`),
  KEY `idx_expenses_status` (`status`),
  KEY `idx_expenses_deleted` (`deleted_at`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_expenses_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fuel_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fuel_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference_code` varchar(32) DEFAULT NULL,
  `vehicle_id` int(10) unsigned NOT NULL,
  `station_name` varchar(150) NOT NULL,
  `fuel_type` enum('diesel','petrol','electric','cng') NOT NULL DEFAULT 'diesel',
  `litres` decimal(10,2) NOT NULL,
  `is_full_tank` tinyint(1) NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `exchange_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_amount` decimal(14,2) DEFAULT NULL,
  `mileage` int(10) unsigned DEFAULT NULL,
  `previous_mileage` int(10) unsigned DEFAULT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `purchased_at` datetime NOT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_code` (`reference_code`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `fk_fuel_driver` (`driver_id`),
  KEY `fk_fuel_trip` (`trip_id`),
  KEY `idx_fuel_purchased` (`purchased_at`),
  KEY `idx_fuel_deleted` (`deleted_at`),
  CONSTRAINT `fk_fuel_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fuel_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fuel_records_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gl_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gl_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_code` varchar(20) NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `account_type` enum('asset','liability','equity','income','cost_of_sales','expense') NOT NULL,
  `normal_balance` enum('debit','credit') NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `is_header` tinyint(1) NOT NULL DEFAULT 0,
  `is_contra` tinyint(1) NOT NULL DEFAULT 0,
  `depth` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `report_section` varchar(60) NOT NULL DEFAULT 'Other',
  `section_order` smallint(5) unsigned NOT NULL DEFAULT 999,
  `cash_flow_class` enum('operating','investing','financing','cash','none') NOT NULL DEFAULT 'none',
  `is_bank` tinyint(1) NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_code` (`account_code`),
  KEY `parent_id` (`parent_id`),
  KEY `idx_gl_accounts_type` (`account_type`),
  KEY `idx_gl_accounts_section` (`section_order`,`account_code`),
  KEY `idx_gl_accounts_deleted` (`deleted_at`),
  CONSTRAINT `gl_accounts_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `gl_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=634 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gl_budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gl_budgets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fiscal_year` smallint(5) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0 = the whole year, 1-12 = one month',
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budget_line` (`fiscal_year`,`account_id`,`month`),
  KEY `idx_budget_account` (`account_id`),
  CONSTRAINT `fk_budget_account` FOREIGN KEY (`account_id`) REFERENCES `gl_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gl_cheque_books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gl_cheque_books` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bank_account_id` int(10) unsigned NOT NULL COMMENT 'gl_accounts.id, an account marked is_bank',
  `prefix` varchar(20) DEFAULT NULL COMMENT 'when the leaves carry letters as well as digits',
  `first_no` bigint(20) unsigned NOT NULL,
  `last_no` bigint(20) unsigned NOT NULL,
  `next_no` bigint(20) unsigned NOT NULL,
  `status` enum('active','finished') NOT NULL DEFAULT 'active',
  `notes` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cheque_book_account` (`bank_account_id`,`status`),
  CONSTRAINT `fk_cheque_book_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `gl_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gl_cheque_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gl_cheque_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cheque_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL COMMENT 'gl_accounts.id, what the money was for',
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_cheque_line` (`cheque_id`),
  KEY `idx_cheque_line_account` (`account_id`),
  CONSTRAINT `fk_cheque_line_account` FOREIGN KEY (`account_id`) REFERENCES `gl_accounts` (`id`),
  CONSTRAINT `fk_cheque_line_cheque` FOREIGN KEY (`cheque_id`) REFERENCES `gl_cheques` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gl_cheques`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gl_cheques` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(40) NOT NULL COMMENT 'CHQ-2026-0001, issued by the system',
  `cheque_no` varchar(40) DEFAULT NULL COMMENT 'the number printed on the paper leaf',
  `bank_account_id` int(10) unsigned NOT NULL,
  `cheque_book_id` int(10) unsigned DEFAULT NULL,
  `payee_name` varchar(200) NOT NULL COMMENT 'exactly as written on the leaf',
  `supplier_id` int(10) unsigned DEFAULT NULL COMMENT 'when the payee is a supplier on file',
  `payee_address` varchar(255) DEFAULT NULL,
  `cheque_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `memo` varchar(255) DEFAULT NULL,
  `status` enum('draft','issued','presented','void') NOT NULL DEFAULT 'draft',
  `entry_id` int(10) unsigned DEFAULT NULL COMMENT 'gl_journal_entries, once posted',
  `prepared_by` int(10) unsigned DEFAULT NULL,
  `issued_by` int(10) unsigned DEFAULT NULL,
  `issued_at` datetime DEFAULT NULL,
  `printed_at` datetime DEFAULT NULL,
  `presented_on` date DEFAULT NULL,
  `void_by` int(10) unsigned DEFAULT NULL,
  `void_at` datetime DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cheque_reference` (`reference`),
  UNIQUE KEY `uq_cheque_no_account` (`bank_account_id`,`cheque_no`),
  KEY `idx_cheque_date` (`cheque_date`),
  KEY `idx_cheque_status` (`status`),
  KEY `idx_cheque_deleted` (`deleted_at`),
  CONSTRAINT `fk_cheque_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `gl_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gl_fiscal_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gl_fiscal_periods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `period_label` varchar(40) NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `closed_by` int(10) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `period_label` (`period_label`),
  KEY `closed_by` (`closed_by`),
  KEY `idx_gl_periods_range` (`starts_on`,`ends_on`),
  CONSTRAINT `gl_fiscal_periods_ibfk_1` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gl_journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gl_journal_entries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entry_no` varchar(32) NOT NULL,
  `entry_date` date NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `memo` varchar(255) NOT NULL,
  `reference` varchar(64) DEFAULT NULL,
  `source_type` varchar(40) DEFAULT NULL,
  `source_id` int(10) unsigned DEFAULT NULL,
  `source_code` varchar(64) DEFAULT NULL,
  `status` enum('draft','posted','reversed') NOT NULL DEFAULT 'posted',
  `reversed_by` int(10) unsigned DEFAULT NULL,
  `posted_by` int(10) unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `entry_no` (`entry_no`),
  UNIQUE KEY `uq_gl_source` (`source_type`,`source_id`),
  KEY `posted_by` (`posted_by`),
  KEY `idx_gl_entries_date` (`entry_date`),
  KEY `idx_gl_entries_status` (`status`),
  KEY `idx_gl_entries_deleted` (`deleted_at`),
  KEY `idx_journal_currency` (`currency`,`entry_date`),
  CONSTRAINT `gl_journal_entries_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gl_journal_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gl_journal_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(10) unsigned NOT NULL,
  `line_no` smallint(5) unsigned NOT NULL DEFAULT 1,
  `account_id` int(10) unsigned NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `debit` decimal(16,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(16,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_gl_lines_account` (`account_id`),
  KEY `idx_gl_lines_entry` (`entry_id`,`line_no`),
  CONSTRAINT `gl_journal_lines_ibfk_1` FOREIGN KEY (`entry_id`) REFERENCES `gl_journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gl_journal_lines_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `gl_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_id` int(10) unsigned NOT NULL,
  `sku` varchar(80) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `category` varchar(80) DEFAULT NULL,
  `unit_of_measure` varchar(20) NOT NULL DEFAULT 'Unit',
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `minimum_level` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reorder_quantity` decimal(12,2) DEFAULT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `batch_number` varchar(80) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `storage_temperature` varchar(40) DEFAULT NULL,
  `status` enum('in_stock','reorder','out_of_stock') NOT NULL DEFAULT 'in_stock',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `warehouse_id` (`warehouse_id`),
  KEY `idx_inventory_status` (`status`),
  KEY `idx_inventory_deleted` (`deleted_at`),
  CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `shipment_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `shipment_id` (`shipment_id`),
  KEY `idx_invoice_lines_invoice` (`invoice_id`),
  CONSTRAINT `invoice_lines_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_lines_ibfk_2` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(32) NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `tax_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `exchange_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_amount` decimal(14,2) DEFAULT NULL,
  `amount_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','issued','partially_paid','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  `issued_by` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `customer_id` (`customer_id`),
  KEY `trip_id` (`trip_id`),
  KEY `issued_by` (`issued_by`),
  KEY `idx_invoices_status` (`status`),
  KEY `idx_invoices_issue` (`issue_date`),
  KEY `idx_invoices_deleted` (`deleted_at`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `succeeded` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attempts_email` (`email`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lookup_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lookup_values` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `list_key` varchar(40) NOT NULL,
  `value` varchar(120) NOT NULL,
  `label` varchar(120) NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 100,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lookup_entry` (`list_key`,`value`),
  KEY `idx_lookup_list` (`list_key`,`sort_order`),
  KEY `idx_lookup_deleted` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1535 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `maintenance_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maintenance_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `work_order_code` varchar(32) NOT NULL,
  `vehicle_id` int(10) unsigned NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `maintenance_type` enum('preventive','corrective','inspection','tyre','bodywork','emergency') NOT NULL DEFAULT 'preventive',
  `provider_name` varchar(150) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `estimated_cost` decimal(12,2) DEFAULT NULL,
  `actual_cost` decimal(12,2) DEFAULT NULL,
  `odometer_reading` int(10) unsigned DEFAULT NULL,
  `status` enum('open','scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
  `started_at` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_order_code` (`work_order_code`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `idx_maintenance_status` (`status`),
  KEY `idx_maintenance_deleted` (`deleted_at`),
  CONSTRAINT `maintenance_orders_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `maintenance_parts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maintenance_parts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `maintenance_id` int(10) unsigned NOT NULL,
  `line_type` enum('part','labour','service','consumable') NOT NULL DEFAULT 'part',
  `part_name` varchar(180) NOT NULL,
  `part_number` varchar(80) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) GENERATED ALWAYS AS (`quantity` * `unit_cost`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_maintenance_parts_order` (`maintenance_id`),
  CONSTRAINT `maintenance_parts_ibfk_1` FOREIGN KEY (`maintenance_id`) REFERENCES `maintenance_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(190) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `notification_key` varchar(80) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `role_key` varchar(50) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `link_route` varchar(80) DEFAULT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` varchar(64) DEFAULT NULL,
  `severity` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_key` (`notification_key`),
  KEY `user_id` (`user_id`),
  KEY `role_key` (`role_key`),
  KEY `is_read` (`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`),
  KEY `idx_resets_expiry` (`expires_at`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_methods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `method_key` varchar(40) NOT NULL,
  `method_name` varchar(80) NOT NULL,
  `gl_account_id` int(10) unsigned DEFAULT NULL,
  `provider_name` varchar(120) DEFAULT NULL,
  `account_name` varchar(160) DEFAULT NULL,
  `account_number` varchar(80) DEFAULT NULL,
  `branch_name` varchar(120) DEFAULT NULL,
  `swift_code` varchar(20) DEFAULT NULL,
  `phone_number` varchar(40) DEFAULT NULL,
  `payment_details` varchar(255) DEFAULT NULL,
  `instructions` varchar(255) DEFAULT NULL,
  `direction` enum('in','out','both') NOT NULL DEFAULT 'both',
  `show_on_invoice` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 100,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_method` (`method_key`),
  KEY `idx_payment_method_order` (`sort_order`,`method_name`),
  KEY `fk_payment_method_account` (`gl_account_id`),
  CONSTRAINT `fk_payment_method_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payment_code` varchar(32) NOT NULL,
  `invoice_id` int(10) unsigned NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `exchange_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_amount` decimal(14,2) DEFAULT NULL,
  `method` enum('cash','bank_transfer','mobile_money','cheque','card') NOT NULL DEFAULT 'bank_transfer',
  `reference` varchar(80) DEFAULT NULL,
  `paid_at` datetime NOT NULL,
  `recorded_by` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_code` (`payment_code`),
  KEY `invoice_id` (`invoice_id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `idx_payments_date` (`paid_at`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `permission_key` varchar(50) NOT NULL,
  `permission_label` varchar(100) NOT NULL,
  `permission_group` varchar(60) NOT NULL DEFAULT 'General',
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_key` (`permission_key`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_request_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_request_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_request_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned DEFAULT NULL,
  `item_name` varchar(180) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_of_measure` varchar(20) NOT NULL DEFAULT 'Unit',
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `received_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `idx_pr_lines_request` (`purchase_request_id`),
  CONSTRAINT `purchase_request_lines_ibfk_1` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_request_lines_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `request_code` varchar(32) NOT NULL,
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `requested_by` int(10) unsigned DEFAULT NULL,
  `description` text NOT NULL,
  `category` varchar(80) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `exchange_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_amount` decimal(14,2) DEFAULT NULL,
  `expected_date` date DEFAULT NULL,
  `status` enum('draft','quotation','approved','received','rejected') NOT NULL DEFAULT 'draft',
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_code` (`request_code`),
  KEY `supplier_id` (`supplier_id`),
  KEY `requested_by` (`requested_by`),
  KEY `fk_purchase_warehouse` (`warehouse_id`),
  KEY `fk_purchase_approver` (`approved_by`),
  KEY `idx_purchase_status` (`status`),
  KEY `idx_purchase_deleted` (`deleted_at`),
  CONSTRAINT `fk_purchase_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_purchase_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rate_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_cards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rate_code` varchar(32) NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `origin_warehouse_id` int(10) unsigned DEFAULT NULL,
  `destination_warehouse_id` int(10) unsigned DEFAULT NULL,
  `origin` varchar(150) NOT NULL,
  `destination` varchar(150) NOT NULL,
  `vehicle_type` varchar(80) DEFAULT NULL,
  `full_load_kg` decimal(12,2) DEFAULT NULL,
  `full_load_price` decimal(14,2) DEFAULT NULL,
  `rate_type` enum('per_trip','per_kg','per_m3','per_package','per_day') NOT NULL DEFAULT 'per_trip',
  `rate_amount` decimal(14,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `minimum_charge` decimal(14,2) DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `status` enum('active','expired','draft') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rate_code` (`rate_code`),
  KEY `customer_id` (`customer_id`),
  KEY `idx_rates_status` (`status`),
  KEY `idx_rates_deleted` (`deleted_at`),
  KEY `fk_rate_origin_wh` (`origin_warehouse_id`),
  KEY `fk_rate_destination_wh` (`destination_warehouse_id`),
  CONSTRAINT `fk_rate_destination_wh` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rate_origin_wh` FOREIGN KEY (`origin_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rate_cards_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `report_key` varchar(60) DEFAULT NULL,
  `report_name` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `period_label` varchar(100) NOT NULL,
  `owner_name` varchar(100) NOT NULL,
  `last_generated_at` datetime DEFAULT NULL,
  `format_label` varchar(20) NOT NULL DEFAULT 'CSV',
  `action_label` varchar(40) NOT NULL DEFAULT 'View',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_name` (`report_name`),
  UNIQUE KEY `uq_reports_key` (`report_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `permission_key` varchar(50) NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT 1,
  `can_create` tinyint(1) NOT NULL DEFAULT 0,
  `can_edit` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete` tinyint(1) NOT NULL DEFAULT 0,
  `can_approve` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_permission` (`role_id`,`permission_key`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=265 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_key` varchar(50) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_key` (`role_key`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shipments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shipment_code` varchar(32) NOT NULL,
  `request_id` int(10) unsigned DEFAULT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `consignee_name` varchar(150) NOT NULL,
  `consignee_phone` varchar(40) DEFAULT NULL,
  `origin` varchar(150) NOT NULL,
  `origin_warehouse_id` int(10) unsigned DEFAULT NULL,
  `destination` varchar(150) NOT NULL,
  `destination_warehouse_id` int(10) unsigned DEFAULT NULL,
  `stock_item_id` int(10) unsigned DEFAULT NULL,
  `stock_quantity` decimal(12,2) DEFAULT NULL,
  `stock_issued_at` datetime DEFAULT NULL,
  `cargo_type` enum('general','cold_chain','fragile','hazardous','bulk','liquid','perishable') NOT NULL DEFAULT 'general',
  `cargo_description` varchar(255) NOT NULL,
  `packages_count` int(10) unsigned NOT NULL DEFAULT 1,
  `weight_kg` decimal(12,2) DEFAULT NULL,
  `volume_m3` decimal(12,3) DEFAULT NULL,
  `temperature_min_c` decimal(5,2) DEFAULT NULL,
  `temperature_max_c` decimal(5,2) DEFAULT NULL,
  `is_hazardous` tinyint(1) NOT NULL DEFAULT 0,
  `declared_value` decimal(14,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `rate_card_id` int(10) unsigned DEFAULT NULL,
  `quoted_amount` decimal(14,2) DEFAULT NULL,
  `quote_basis` varchar(160) DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `status` enum('draft','booked','loaded','in_transit','delivered','returned','cancelled') NOT NULL DEFAULT 'draft',
  `booked_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipment_code` (`shipment_code`),
  KEY `request_id` (`request_id`),
  KEY `trip_id` (`trip_id`),
  KEY `customer_id` (`customer_id`),
  KEY `idx_shipments_status` (`status`),
  KEY `idx_shipments_deleted` (`deleted_at`),
  KEY `fk_shipment_rate_card` (`rate_card_id`),
  KEY `fk_shipment_origin_wh` (`origin_warehouse_id`),
  KEY `fk_shipment_destination_wh` (`destination_warehouse_id`),
  KEY `fk_shipment_stock_item` (`stock_item_id`),
  CONSTRAINT `fk_shipment_destination_wh` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shipment_origin_wh` FOREIGN KEY (`origin_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shipment_rate_card` FOREIGN KEY (`rate_card_id`) REFERENCES `rate_cards` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shipment_stock_item` FOREIGN KEY (`stock_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `transport_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_ibfk_2` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_movements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `movement_code` varchar(32) NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `movement_type` enum('stock_in','stock_out','transfer_in','transfer_out','adjustment','damage','return') NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `balance_after` decimal(12,2) DEFAULT NULL,
  `reference_type` varchar(40) DEFAULT NULL,
  `reference_code` varchar(64) DEFAULT NULL,
  `performed_by` int(10) unsigned DEFAULT NULL,
  `moved_at` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `movement_code` (`movement_code`),
  KEY `warehouse_id` (`warehouse_id`),
  KEY `performed_by` (`performed_by`),
  KEY `idx_movements_item` (`item_id`,`moved_at`),
  KEY `idx_movements_date` (`moved_at`),
  CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_code` varchar(32) DEFAULT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `category` varchar(80) DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `tin_number` varchar(40) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `payment_terms_days` int(10) unsigned NOT NULL DEFAULT 30,
  `rating` tinyint(3) unsigned DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transport_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transport_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference_code` varchar(32) NOT NULL,
  `requester_id` int(10) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `requested_by_contact` varchar(150) DEFAULT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `pickup_location` varchar(150) NOT NULL,
  `origin_warehouse_id` int(10) unsigned DEFAULT NULL,
  `destination` varchar(150) NOT NULL,
  `destination_warehouse_id` int(10) unsigned DEFAULT NULL,
  `stock_item_id` int(10) unsigned DEFAULT NULL,
  `stock_quantity` decimal(12,2) DEFAULT NULL,
  `cargo_description` varchar(255) DEFAULT NULL,
  `weight_kg` decimal(12,2) DEFAULT NULL,
  `rate_card_id` int(10) unsigned DEFAULT NULL,
  `quoted_amount` decimal(14,2) DEFAULT NULL,
  `quote_basis` varchar(160) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `quoted_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `accepted_by` varchar(150) DEFAULT NULL,
  `packages_count` int(10) unsigned DEFAULT NULL,
  `required_date` date NOT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `status` enum('pending','quoted','approved','assigned','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_code` (`reference_code`),
  KEY `requester_id` (`requester_id`),
  KEY `fk_requests_customer` (`customer_id`),
  KEY `fk_requests_trip` (`trip_id`),
  KEY `fk_requests_approver` (`approved_by`),
  KEY `idx_requests_status` (`status`),
  KEY `idx_requests_deleted` (`deleted_at`),
  KEY `fk_request_origin_wh` (`origin_warehouse_id`),
  KEY `fk_request_destination_wh` (`destination_warehouse_id`),
  KEY `fk_request_rate_card` (`rate_card_id`),
  KEY `fk_request_stock_item` (`stock_item_id`),
  CONSTRAINT `fk_request_destination_wh` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_request_origin_wh` FOREIGN KEY (`origin_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_request_rate_card` FOREIGN KEY (`rate_card_id`) REFERENCES `rate_cards` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_request_stock_item` FOREIGN KEY (`stock_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_requests_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_requests_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_requests_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transport_requests_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trip_stops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trip_stops` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trip_id` int(10) unsigned NOT NULL,
  `stop_sequence` int(10) unsigned NOT NULL DEFAULT 1,
  `stop_type` enum('pickup','dropoff','waypoint','checkpoint') NOT NULL DEFAULT 'dropoff',
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `location_name` varchar(180) NOT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `contact_phone` varchar(40) DEFAULT NULL,
  `planned_arrival_at` datetime DEFAULT NULL,
  `actual_arrival_at` datetime DEFAULT NULL,
  `status` enum('pending','arrived','completed','skipped','failed') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_trip_stops_trip` (`trip_id`,`stop_sequence`),
  KEY `fk_stop_warehouse` (`warehouse_id`),
  CONSTRAINT `fk_stop_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trip_stops_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trips` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference_code` varchar(32) NOT NULL,
  `request_id` int(10) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `trip_type` enum('delivery','collection','transfer','return','shuttle') NOT NULL DEFAULT 'delivery',
  `vehicle_id` int(10) unsigned DEFAULT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `pickup_location` varchar(150) NOT NULL,
  `destination` varchar(150) NOT NULL,
  `planned_departure_at` datetime DEFAULT NULL,
  `planned_arrival_at` datetime DEFAULT NULL,
  `departure_at` datetime DEFAULT NULL,
  `arrival_at` datetime DEFAULT NULL,
  `status` enum('requested','approved','loading','in_transit','delivered','cancelled') NOT NULL DEFAULT 'requested',
  `cargo_summary` varchar(255) DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_code` (`reference_code`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `driver_id` (`driver_id`),
  KEY `fk_trips_customer` (`customer_id`),
  KEY `idx_trips_status` (`status`),
  KEY `idx_trips_departure` (`departure_at`),
  KEY `idx_trips_deleted` (`deleted_at`),
  KEY `idx_trips_request` (`request_id`),
  CONSTRAINT `fk_trips_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trips_request` FOREIGN KEY (`request_id`) REFERENCES `transport_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trips_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `department` varchar(80) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','locked') NOT NULL DEFAULT 'active',
  `prvg` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `notify_by_email` tinyint(1) NOT NULL DEFAULT 1,
  `password_changed_at` datetime DEFAULT NULL,
  `failed_login_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  KEY `idx_users_deleted` (`deleted_at`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `chk_users_prvg` CHECK (`prvg` in (1,2))
) ENGINE=InnoDB AUTO_INCREMENT=157 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicle_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_code` varchar(32) NOT NULL,
  `vehicle_id` int(10) unsigned NOT NULL,
  `document_type` enum('insurance','inspection','registration','road_license','permit','other') NOT NULL DEFAULT 'insurance',
  `document_number` varchar(80) DEFAULT NULL,
  `provider_name` varchar(150) DEFAULT NULL,
  `issued_on` date DEFAULT NULL,
  `expires_on` date NOT NULL,
  `cost` decimal(12,2) DEFAULT NULL,
  `document_file` varchar(255) DEFAULT NULL,
  `status` enum('valid','expiring','expired','cancelled') NOT NULL DEFAULT 'valid',
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_code` (`document_code`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `idx_vehicle_docs_expiry` (`expires_on`),
  KEY `idx_vehicle_docs_deleted` (`deleted_at`),
  CONSTRAINT `vehicle_documents_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plate_number` varchar(32) NOT NULL,
  `vehicle_type` varchar(80) NOT NULL,
  `make` varchar(80) DEFAULT NULL,
  `model` varchar(80) DEFAULT NULL,
  `manufacture_year` smallint(5) unsigned DEFAULT NULL,
  `chassis_number` varchar(80) DEFAULT NULL,
  `capacity_kg` decimal(12,2) DEFAULT NULL,
  `capacity_m3` decimal(12,3) DEFAULT NULL,
  `fuel_type` enum('diesel','petrol','electric','hybrid','cng') NOT NULL DEFAULT 'diesel',
  `ownership` enum('owned','leased','rented','subcontracted') NOT NULL DEFAULT 'owned',
  `acquired_on` date DEFAULT NULL,
  `has_cooling_unit` tinyint(1) NOT NULL DEFAULT 0,
  `assigned_driver_id` int(10) unsigned DEFAULT NULL,
  `mileage` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('available','on_trip','maintenance','inactive') NOT NULL DEFAULT 'available',
  `next_service_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plate_number` (`plate_number`),
  KEY `idx_vehicles_status` (`status`),
  KEY `idx_vehicles_deleted` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouse_cargo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `warehouse_cargo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `movement_code` varchar(40) NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `shipment_id` int(10) unsigned NOT NULL,
  `direction` enum('in','out') NOT NULL,
  `reason` enum('received','loaded','arrived','collected','returned') NOT NULL,
  `packages` int(10) unsigned DEFAULT NULL,
  `weight_kg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `delivery_id` int(10) unsigned DEFAULT NULL,
  `moved_at` datetime NOT NULL,
  `performed_by` int(10) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cargo_movement` (`movement_code`),
  UNIQUE KEY `uniq_cargo_step` (`shipment_id`,`warehouse_id`,`reason`),
  KEY `idx_cargo_warehouse` (`warehouse_id`,`moved_at`),
  KEY `idx_cargo_shipment` (`shipment_id`,`moved_at`),
  KEY `fk_cargo_trip` (`trip_id`),
  KEY `fk_cargo_delivery` (`delivery_id`),
  KEY `fk_cargo_user` (`performed_by`),
  CONSTRAINT `fk_cargo_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cargo_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cargo_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cargo_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cargo_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `warehouses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_code` varchar(32) DEFAULT NULL,
  `warehouse_name` varchar(150) NOT NULL,
  `location` varchar(150) NOT NULL,
  `manager_id` int(10) unsigned DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `capacity_m3` decimal(12,2) DEFAULT NULL,
  `is_cold_chain` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;



-- ---------------------------------------------------------- reference data


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` (`id`, `role_key`, `role_name`) VALUES (1,'super_admin','Super Admin'),(2,'logistics_manager','Logistics Manager'),(3,'fleet_manager','Fleet Manager'),(4,'warehouse_manager','Warehouse Manager'),(5,'driver','Driver'),(6,'finance','Finance'),(7,'management','Management');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;

/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` (`id`, `permission_key`, `permission_label`, `permission_group`, `sort_order`) VALUES (1,'dashboard','Dashboard','Overview',10),(2,'vehicles','Vehicles','Fleet',20),(3,'drivers','Drivers','Fleet',30),(4,'maintenance','Maintenance','Fleet',40),(5,'vehicle_documents','Vehicle documents','Fleet',50),(6,'requests','Transport requests','Transport',60),(7,'trips','Trips','Transport',70),(8,'shipments','Shipments','Transport',80),(9,'deliveries','Deliveries','Transport',90),(10,'customers','Customers','Commercial',100),(11,'rates','Rate cards','Commercial',110),(12,'invoices','Invoices','Commercial',120),(13,'fuel','Fuel management','Finance',130),(14,'expenses','Logistics expenses','Finance',140),(15,'warehouse','Warehouse and inventory','Warehouse',150),(16,'movements','Stock movements','Warehouse',160),(17,'procurement','Procurement','Warehouse',170),(18,'suppliers','Suppliers','Warehouse',180),(19,'reports','Reports','Insights',190),(20,'users','Users and permissions','Administration',200),(21,'settings','Company settings','Administration',210),(22,'audit','Audit trail','Administration',220),(23,'accounts','Chart of accounts','Accounting',230),(24,'journal','Journal entries','Accounting',240),(25,'books','Accounting books','Accounting',250),(26,'payments','Payments received','Commercial',125),(28,'email','Email outbox','Administration',225),(30,'lookups','Reference lists','Administration',215),(32,'warehouses','Warehouses','Warehouse',405),(36,'payment_methods','Payment methods','Accounting',340),(40,'cheques','Cheques','Accounting',320),(41,'cheque_books','Cheque books','Accounting',330),(42,'budgets','Budget','Accounting',350),(44,'currencies','Currencies','Accounting',360),(45,'currency_rates','Exchange rates','Accounting',370),(47,'crossings','Border crossings','Transport',250),(48,'border_posts','Border posts','Transport',260);
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;

/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_key`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_approve`) VALUES (1,1,'audit',1,1,1,1,1),(2,1,'customers',1,1,1,1,1),(3,1,'dashboard',1,1,1,1,1),(4,1,'deliveries',1,1,1,1,1),(5,1,'drivers',1,1,1,1,1),(6,1,'expenses',1,1,1,1,1),(7,1,'fuel',1,1,1,1,1),(8,1,'invoices',1,1,1,1,1),(9,1,'maintenance',1,1,1,1,1),(10,1,'movements',1,1,1,1,1),(11,1,'procurement',1,1,1,1,1),(12,1,'rates',1,1,1,1,1),(13,1,'reports',1,1,1,1,1),(14,1,'requests',1,1,1,1,1),(15,1,'settings',1,1,1,1,1),(16,1,'shipments',1,1,1,1,1),(17,1,'suppliers',1,1,1,1,1),(18,1,'trips',1,1,1,1,1),(19,1,'users',1,1,1,1,1),(20,1,'vehicle_documents',1,1,1,1,1),(21,1,'vehicles',1,1,1,1,1),(22,1,'warehouse',1,1,1,1,1),(32,2,'dashboard',1,0,0,0,0),(33,2,'vehicles',1,1,1,0,0),(34,2,'drivers',1,1,1,0,0),(35,2,'maintenance',1,1,1,0,1),(36,2,'vehicle_documents',1,1,1,0,0),(37,2,'requests',1,1,1,1,1),(38,2,'trips',1,1,1,1,1),(39,2,'shipments',1,1,1,1,0),(40,2,'deliveries',1,1,1,1,0),(41,2,'customers',1,1,1,0,0),(42,2,'rates',1,0,0,0,0),(43,2,'fuel',1,1,1,0,0),(44,2,'expenses',1,1,1,0,0),(45,2,'warehouse',1,1,1,0,0),(46,2,'movements',1,1,0,0,0),(47,2,'procurement',1,1,1,0,0),(48,2,'suppliers',1,1,1,0,0),(49,2,'reports',1,1,1,0,0),(50,2,'audit',1,0,0,0,0),(63,3,'dashboard',1,0,0,0,0),(64,3,'vehicles',1,1,1,1,0),(65,3,'drivers',1,1,1,1,0),(66,3,'maintenance',1,1,1,1,1),(67,3,'vehicle_documents',1,1,1,1,0),(68,3,'fuel',1,1,1,0,0),(69,3,'trips',1,0,0,0,0),(70,3,'reports',1,0,0,0,0),(78,4,'dashboard',1,0,0,0,0),(79,4,'warehouse',1,1,1,1,0),(80,4,'movements',1,1,0,0,0),(81,4,'procurement',1,1,1,1,0),(82,4,'suppliers',1,1,1,1,0),(83,4,'requests',1,1,1,0,0),(84,4,'shipments',1,1,1,0,0),(85,4,'reports',1,0,0,0,0),(93,5,'dashboard',1,0,0,0,0),(94,5,'trips',1,0,1,0,0),(95,5,'deliveries',1,0,1,0,1),(96,5,'shipments',1,0,0,0,0),(97,5,'fuel',1,1,0,0,0),(100,6,'dashboard',1,0,0,0,0),(101,6,'fuel',1,0,1,0,0),(102,6,'expenses',1,1,1,1,1),(103,6,'procurement',1,0,1,0,1),(104,6,'maintenance',1,0,0,0,1),(105,6,'customers',1,1,1,0,0),(106,6,'rates',1,1,1,1,0),(107,6,'invoices',1,1,1,1,1),(108,6,'suppliers',1,0,0,0,0),(109,6,'reports',1,1,1,0,0),(115,7,'dashboard',1,0,0,0,0),(116,7,'reports',1,0,0,0,0),(117,7,'customers',1,0,0,0,0),(118,7,'invoices',1,0,0,0,0),(119,7,'trips',1,0,0,0,0),(120,7,'audit',1,0,0,0,0),(123,1,'accounts',1,1,1,1,1),(124,1,'books',1,1,1,1,1),(125,1,'journal',1,1,1,1,1),(126,6,'accounts',1,1,1,0,0),(127,6,'journal',1,1,1,1,1),(128,6,'books',1,0,0,0,0),(129,7,'books',1,0,0,0,0),(130,7,'accounts',1,0,0,0,0),(132,1,'payments',1,1,1,1,1),(133,6,'payments',1,1,1,1,0),(134,7,'payments',1,0,0,0,0),(138,1,'email',1,1,1,1,1),(139,6,'email',1,0,1,0,0),(143,1,'lookups',1,1,1,1,1),(144,6,'lookups',1,1,1,0,0),(145,3,'lookups',1,1,1,0,0),(146,2,'lookups',1,1,1,0,0),(147,4,'lookups',1,1,1,0,0),(154,1,'warehouses',1,1,1,1,1),(155,4,'warehouses',1,1,1,1,0),(156,2,'warehouses',1,1,1,0,0),(157,6,'warehouses',1,0,0,0,0),(158,7,'warehouses',1,0,0,0,0),(169,6,'payment_methods',1,1,1,1,1),(170,1,'payment_methods',1,1,1,1,1),(172,7,'payment_methods',1,0,0,0,0),(182,6,'cheques',1,1,1,1,1),(183,1,'cheques',1,1,1,1,1),(184,6,'cheque_books',1,1,1,1,1),(185,1,'cheque_books',1,1,1,1,1),(186,6,'budgets',1,1,1,1,1),(187,1,'budgets',1,1,1,1,1),(189,7,'cheques',1,0,0,0,0),(190,7,'cheque_books',1,0,0,0,0),(191,7,'budgets',1,0,0,0,0),(195,6,'currencies',1,1,1,1,1),(196,6,'currency_rates',0,0,0,0,1),(197,1,'currencies',1,1,1,1,1),(198,1,'currency_rates',0,0,0,0,1),(202,5,'currencies',1,0,0,0,0),(203,3,'currencies',1,0,0,0,0),(204,2,'currencies',1,0,0,0,0),(205,7,'currencies',1,0,0,0,0),(206,4,'currencies',1,0,0,0,0),(212,2,'crossings',1,1,1,1,1),(213,2,'border_posts',1,1,1,1,1),(214,1,'crossings',1,1,1,1,1),(215,1,'border_posts',1,1,1,1,1),(219,6,'crossings',1,1,1,0,0),(220,6,'border_posts',1,0,0,0,0),(221,3,'crossings',1,0,0,0,0),(222,3,'border_posts',1,0,0,0,0),(223,7,'crossings',1,0,0,0,0),(224,7,'border_posts',1,0,0,0,0),(227,5,'crossings',1,0,1,0,0);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;

/*!40000 ALTER TABLE `gl_accounts` DISABLE KEYS */;
INSERT INTO `gl_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `is_header`, `is_contra`, `depth`, `report_section`, `section_order`, `cash_flow_class`, `is_bank`, `description`, `is_active`, `deleted_at`, `created_at`) VALUES (1,'1000','Cash and bank','asset','debit',NULL,1,0,0,'Cash and bank',10,'cash',0,'Every account the company can pay from.',1,NULL,'2026-09-20 22:03:41'),(2,'1010','Petty cash','asset','debit',1,0,0,1,'Cash and bank',10,'cash',1,'Cash held at the Kigali office.',1,NULL,'2026-09-20 22:03:41'),(3,'1020','Bank - main account','asset','debit',1,0,0,1,'Cash and bank',10,'cash',1,'The operating current account.',1,NULL,'2026-09-20 22:03:41'),(4,'1030','Mobile money','asset','debit',1,0,0,1,'Cash and bank',10,'cash',1,'Driver float and field payments.',1,NULL,'2026-09-20 22:03:41'),(5,'1100','Accounts receivable','asset','debit',NULL,0,0,0,'Accounts receivable',20,'operating',0,'Invoices issued to customers and not yet settled.',1,NULL,'2026-09-20 22:03:41'),(6,'1200','Inventory','asset','debit',NULL,0,0,0,'Other current assets',30,'operating',0,'Stock held in the warehouses.',1,NULL,'2026-09-20 22:03:41'),(7,'1250','Prepayments and deposits','asset','debit',NULL,0,0,0,'Other current assets',30,'operating',0,'Insurance and rent paid in advance.',1,NULL,'2026-09-20 22:03:41'),(8,'1500','Motor vehicles','asset','debit',NULL,0,0,0,'Fixed assets',40,'investing',0,'The fleet, at cost.',1,NULL,'2026-09-20 22:03:41'),(9,'1510','Equipment and fittings','asset','debit',NULL,0,0,0,'Fixed assets',40,'investing',0,'Warehouse and office equipment, at cost.',1,NULL,'2026-09-20 22:03:41'),(10,'1590','Accumulated depreciation','asset','credit',NULL,0,1,0,'Fixed assets',40,'investing',0,'Depreciation charged to date on the fleet and equipment.',1,NULL,'2026-09-20 22:03:41'),(11,'2000','Accounts payable','liability','credit',NULL,0,0,0,'Accounts payable',50,'operating',0,'Suppliers, workshops and fuel stations not yet paid.',1,NULL,'2026-09-20 22:03:41'),(12,'2100','VAT payable','liability','credit',NULL,0,0,0,'Other current liabilities',60,'operating',0,'VAT charged on invoices and owed to RRA.',1,NULL,'2026-09-20 22:03:41'),(13,'2150','Accrued expenses','liability','credit',NULL,0,0,0,'Other current liabilities',60,'operating',0,'Costs incurred but not yet invoiced by the supplier.',1,NULL,'2026-09-20 22:03:41'),(14,'2200','Payroll liabilities','liability','credit',NULL,0,0,0,'Other current liabilities',60,'operating',0,'Net pay, RSSB and PAYE owed.',1,NULL,'2026-09-20 22:03:41'),(15,'2500','Motor vehicle loans','liability','credit',NULL,0,0,0,'Long term liabilities',70,'financing',0,'Asset finance on the fleet.',1,NULL,'2026-09-20 22:03:41'),(16,'3000','Share capital','equity','credit',NULL,0,0,0,'Equity',80,'financing',0,'Capital introduced by the owners.',1,NULL,'2026-09-20 22:03:41'),(17,'3100','Retained earnings','equity','credit',NULL,0,0,0,'Equity',80,'financing',0,'Profit of earlier years kept in the business.',1,NULL,'2026-09-20 22:03:41'),(18,'3200','Drawings and dividends','equity','debit',NULL,0,0,0,'Equity',80,'financing',0,'Profit taken out by the owners.',1,NULL,'2026-09-20 22:03:41'),(19,'4000','Freight revenue','income','credit',NULL,0,0,0,'Income',100,'operating',0,'Transport invoiced to customers.',1,NULL,'2026-09-20 22:03:41'),(20,'4100','Warehousing and handling revenue','income','credit',NULL,0,0,0,'Income',100,'operating',0,'Storage, loading and handling charged out.',1,NULL,'2026-09-20 22:03:41'),(21,'4900','Other income','income','credit',NULL,0,0,0,'Other income',140,'operating',0,'Anything outside the main trade.',1,NULL,'2026-09-20 22:03:41'),(22,'5000','Fuel','cost_of_sales','debit',NULL,0,0,0,'Cost of sales',110,'operating',0,'Diesel and petrol drawn against trips.',1,NULL,'2026-09-20 22:03:41'),(23,'5100','Driver allowances','cost_of_sales','debit',NULL,0,0,0,'Cost of sales',110,'operating',0,'Night-out and per-diem paid to drivers.',1,NULL,'2026-09-20 22:03:41'),(24,'5200','Tolls, parking and permits','cost_of_sales','debit',NULL,0,0,0,'Cost of sales',110,'operating',0,'Road charges incurred on a trip.',1,NULL,'2026-09-20 22:03:41'),(25,'5300','Subcontracted transport','cost_of_sales','debit',NULL,0,0,0,'Cost of sales',110,'operating',0,'Work given to another carrier.',1,NULL,'2026-09-20 22:03:41'),(26,'5400','Loading and handling','cost_of_sales','debit',NULL,0,0,0,'Cost of sales',110,'operating',0,'Casual labour at the warehouse and at drop points.',1,NULL,'2026-09-20 22:03:41'),(27,'6000','Vehicle maintenance and repairs','expense','debit',NULL,0,0,0,'Operating expenses',120,'operating',0,'Servicing, parts and workshop labour.',1,NULL,'2026-09-20 22:03:41'),(28,'6100','Insurance','expense','debit',NULL,0,0,0,'Operating expenses',120,'operating',0,'Fleet and goods-in-transit cover.',1,NULL,'2026-09-20 22:03:41'),(29,'6200','Salaries and wages','expense','debit',NULL,0,0,0,'Operating expenses',120,'operating',0,'Staff pay other than driver allowances.',1,NULL,'2026-09-20 22:03:41'),(30,'6300','Office and administration','expense','debit',NULL,0,0,0,'Operating expenses',120,'operating',0,'Rent, communications, stationery and software.',1,NULL,'2026-09-20 22:03:41'),(31,'6400','Depreciation','expense','debit',NULL,0,0,0,'Operating expenses',120,'operating',0,'Wear charged against the fleet and equipment.',1,NULL,'2026-09-20 22:03:41'),(32,'6500','Bank charges','expense','debit',NULL,0,0,0,'Operating expenses',120,'operating',0,'Transfer fees and account charges.',1,NULL,'2026-09-20 22:03:41'),(33,'6900','Other expenses','expense','debit',NULL,0,0,0,'Other expenses',150,'operating',0,'Anything that does not belong above.',1,NULL,'2026-09-20 22:03:41'),(301,'5500','Customs duty and levies','expense','debit',NULL,0,0,1,'Operating expenses',550,'operating',0,'Duty, VAT and levies paid at a border to release a load.',1,NULL,'2026-09-22 13:44:36'),(302,'5600','Clearing and forwarding fees','expense','debit',NULL,0,0,1,'Operating expenses',560,'operating',0,'What clearing agents charge to lodge and follow a declaration.',1,NULL,'2026-09-22 13:44:36'),(303,'5700','Border and transit charges','expense','debit',NULL,0,0,1,'Operating expenses',570,'operating',0,'Weighbridge, escort, parking and transit fees at a crossing.',1,NULL,'2026-09-22 13:44:36');
/*!40000 ALTER TABLE `gl_accounts` ENABLE KEYS */;

/*!40000 ALTER TABLE `gl_fiscal_periods` DISABLE KEYS */;
INSERT INTO `gl_fiscal_periods` (`id`, `period_label`, `starts_on`, `ends_on`, `status`, `closed_by`, `closed_at`) VALUES (1,'2026-01','2026-01-01','2026-01-31','open',NULL,NULL),(2,'2026-02','2026-02-01','2026-02-28','open',NULL,NULL),(3,'2026-03','2026-03-01','2026-03-31','open',NULL,NULL),(4,'2026-04','2026-04-01','2026-04-30','open',NULL,NULL),(5,'2026-05','2026-05-01','2026-05-31','open',NULL,NULL),(6,'2026-06','2026-06-01','2026-06-30','open',NULL,NULL),(7,'2026-07','2026-07-01','2026-07-31','open',NULL,NULL),(8,'2026-08','2026-08-01','2026-08-31','open',NULL,NULL),(9,'2026-09','2026-09-01','2026-09-30','open',NULL,NULL),(10,'2026-10','2026-10-01','2026-10-31','open',NULL,NULL),(11,'2026-11','2026-11-01','2026-11-30','open',NULL,NULL),(12,'2026-12','2026-12-01','2026-12-31','open',NULL,NULL);
/*!40000 ALTER TABLE `gl_fiscal_periods` ENABLE KEYS */;

/*!40000 ALTER TABLE `currencies` DISABLE KEYS */;
INSERT INTO `currencies` (`id`, `code`, `currency_name`, `symbol`, `country`, `decimals`, `is_base`, `is_active`, `sort_order`, `deleted_at`, `created_at`) VALUES (1,'RWF','Rwandan franc','FRw','Rwanda',0,1,1,10,NULL,'2026-09-22 11:48:07'),(2,'KES','Kenyan shilling','KSh','Kenya',2,0,1,20,NULL,'2026-09-22 11:48:07'),(3,'TZS','Tanzanian shilling','TSh','Tanzania',0,0,1,30,NULL,'2026-09-22 11:48:07'),(4,'UGX','Ugandan shilling','USh','Uganda',0,0,1,40,NULL,'2026-09-22 11:48:07'),(5,'USD','US dollar','$','International',2,0,1,50,NULL,'2026-09-22 11:48:07');
/*!40000 ALTER TABLE `currencies` ENABLE KEYS */;

/*!40000 ALTER TABLE `payment_methods` DISABLE KEYS */;
INSERT INTO `payment_methods` (`id`, `method_key`, `method_name`, `gl_account_id`, `provider_name`, `account_name`, `account_number`, `branch_name`, `swift_code`, `phone_number`, `payment_details`, `instructions`, `direction`, `show_on_invoice`, `is_active`, `sort_order`, `deleted_at`, `created_at`) VALUES (1,'bank_transfer','Bank transfer',3,'Your bank',NULL,NULL,NULL,NULL,NULL,NULL,'Account name and number go here','both',1,1,10,NULL,'2026-09-21 21:24:36'),(2,'mobile_money','Mobile money',4,'Your mobile network',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'both',1,1,20,NULL,'2026-09-21 21:24:36'),(3,'cash','Cash',2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'both',1,1,30,NULL,'2026-09-21 21:24:36'),(4,'cheque','Cheque',3,'Your bank',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'both',1,1,40,NULL,'2026-09-21 21:24:36'),(5,'card','Card',3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'both',0,1,50,NULL,'2026-09-21 21:24:36'),(6,'fuel_card','Fuel card',11,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'out',0,1,60,NULL,'2026-09-21 21:24:36');
/*!40000 ALTER TABLE `payment_methods` ENABLE KEYS */;

/*!40000 ALTER TABLE `lookup_values` DISABLE KEYS */;
INSERT INTO `lookup_values` (`id`, `list_key`, `value`, `label`, `sort_order`, `is_active`, `notes`, `deleted_at`, `created_at`) VALUES (1,'vehicle_type','Delivery truck','Delivery truck',10,1,NULL,NULL,'2026-09-21 10:26:59'),(2,'vehicle_type','Box truck','Box truck',20,1,NULL,NULL,'2026-09-21 10:26:59'),(3,'vehicle_type','Refrigerated truck','Refrigerated truck',30,1,NULL,NULL,'2026-09-21 10:26:59'),(4,'vehicle_type','Pickup','Pickup',40,1,NULL,NULL,'2026-09-21 10:26:59'),(5,'vehicle_type','Van','Van',50,1,NULL,NULL,'2026-09-21 10:26:59'),(6,'vehicle_type','Tanker','Tanker',60,1,NULL,NULL,'2026-09-21 10:26:59'),(7,'vehicle_type','Trailer','Trailer',70,1,NULL,NULL,'2026-09-21 10:26:59'),(8,'vehicle_type','Motorcycle','Motorcycle',80,1,NULL,NULL,'2026-09-21 10:26:59'),(9,'licence_class','A','A',10,1,NULL,NULL,'2026-09-21 10:26:59'),(10,'licence_class','B','B',20,1,NULL,NULL,'2026-09-21 10:26:59'),(11,'licence_class','C','C',30,1,NULL,NULL,'2026-09-21 10:26:59'),(12,'licence_class','D','D',40,1,NULL,NULL,'2026-09-21 10:26:59'),(13,'licence_class','E','E',50,1,NULL,NULL,'2026-09-21 10:26:59'),(14,'licence_class','B/C','B/C',60,1,NULL,NULL,'2026-09-21 10:26:59'),(15,'licence_class','C/D','C/D',70,1,NULL,NULL,'2026-09-21 10:26:59'),(16,'expense_category','Fuel','Fuel',10,1,NULL,NULL,'2026-09-21 10:26:59'),(17,'expense_category','Toll','Toll',20,1,NULL,NULL,'2026-09-21 10:26:59'),(18,'expense_category','Repair','Repair',30,1,NULL,NULL,'2026-09-21 10:26:59'),(19,'expense_category','Allowance','Allowance',40,1,NULL,NULL,'2026-09-21 10:26:59'),(20,'expense_category','Parking','Parking',50,1,NULL,NULL,'2026-09-21 10:26:59'),(21,'expense_category','Insurance','Insurance',60,1,NULL,NULL,'2026-09-21 10:26:59'),(22,'expense_category','Loading','Loading',70,1,NULL,NULL,'2026-09-21 10:26:59'),(23,'expense_category','Permit','Permit',80,1,NULL,NULL,'2026-09-21 10:26:59'),(24,'item_category','Food','Food',10,1,NULL,NULL,'2026-09-21 10:26:59'),(25,'item_category','Packaging','Packaging',20,1,NULL,NULL,'2026-09-21 10:26:59'),(26,'item_category','Spare parts','Spare parts',30,1,NULL,NULL,'2026-09-21 10:26:59'),(27,'item_category','Consumables','Consumables',40,1,NULL,NULL,'2026-09-21 10:26:59'),(28,'item_category','Cold chain','Cold chain',50,1,NULL,NULL,'2026-09-21 10:26:59'),(29,'item_category','Equipment','Equipment',60,1,NULL,NULL,'2026-09-21 10:26:59'),(30,'item_category','Stationery','Stationery',70,1,NULL,NULL,'2026-09-21 10:26:59'),(31,'unit_of_measure','Unit','Unit',10,1,NULL,NULL,'2026-09-21 10:26:59'),(32,'unit_of_measure','Kg','Kg',20,1,NULL,NULL,'2026-09-21 10:26:59'),(33,'unit_of_measure','Litre','Litre',30,1,NULL,NULL,'2026-09-21 10:26:59'),(34,'unit_of_measure','Box','Box',40,1,NULL,NULL,'2026-09-21 10:26:59'),(35,'unit_of_measure','Sack','Sack',50,1,NULL,NULL,'2026-09-21 10:26:59'),(36,'unit_of_measure','Crate','Crate',60,1,NULL,NULL,'2026-09-21 10:26:59'),(37,'unit_of_measure','Pallet','Pallet',70,1,NULL,NULL,'2026-09-21 10:26:59'),(38,'unit_of_measure','Carton','Carton',80,1,NULL,NULL,'2026-09-21 10:26:59'),(39,'supplier_category','Spare parts','Spare parts',10,1,NULL,NULL,'2026-09-21 10:26:59'),(40,'supplier_category','Fuel','Fuel',20,1,NULL,NULL,'2026-09-21 10:26:59'),(41,'supplier_category','Packaging','Packaging',30,1,NULL,NULL,'2026-09-21 10:26:59'),(42,'supplier_category','Cold chain','Cold chain',40,1,NULL,NULL,'2026-09-21 10:26:59'),(43,'supplier_category','Equipment','Equipment',50,1,NULL,NULL,'2026-09-21 10:26:59'),(44,'supplier_category','Services','Services',60,1,NULL,NULL,'2026-09-21 10:26:59'),(45,'supplier_category','Consumables','Consumables',70,1,NULL,NULL,'2026-09-21 10:26:59'),(46,'procurement_category','Spare parts','Spare parts',10,1,NULL,NULL,'2026-09-21 10:26:59'),(47,'procurement_category','Fuel','Fuel',20,1,NULL,NULL,'2026-09-21 10:26:59'),(48,'procurement_category','Packaging','Packaging',30,1,NULL,NULL,'2026-09-21 10:26:59'),(49,'procurement_category','Cold chain','Cold chain',40,1,NULL,NULL,'2026-09-21 10:26:59'),(50,'procurement_category','Equipment','Equipment',50,1,NULL,NULL,'2026-09-21 10:26:59'),(51,'procurement_category','Services','Services',60,1,NULL,NULL,'2026-09-21 10:26:59'),(52,'procurement_category','Food','Food',70,1,NULL,NULL,'2026-09-21 10:26:59'),(53,'department','Operations','Operations',10,1,NULL,NULL,'2026-09-21 10:26:59'),(54,'department','Fleet','Fleet',20,1,NULL,NULL,'2026-09-21 10:26:59'),(55,'department','Warehouse','Warehouse',30,1,NULL,NULL,'2026-09-21 10:26:59'),(56,'department','Finance','Finance',40,1,NULL,NULL,'2026-09-21 10:26:59'),(57,'department','Management','Management',50,1,NULL,NULL,'2026-09-21 10:26:59'),(58,'department','Administration','Administration',60,1,NULL,NULL,'2026-09-21 10:26:59'),(59,'report_period','Daily','Daily',10,1,NULL,NULL,'2026-09-21 10:26:59'),(60,'report_period','Weekly','Weekly',20,1,NULL,NULL,'2026-09-21 10:26:59'),(61,'report_period','Monthly','Monthly',30,1,NULL,NULL,'2026-09-21 10:26:59'),(62,'report_period','Quarterly','Quarterly',40,1,NULL,NULL,'2026-09-21 10:26:59'),(63,'report_period','Yearly','Yearly',50,1,NULL,NULL,'2026-09-21 10:26:59'),(253,'vehicle_type','Cement bulker','Cement bulker',90,1,'Imodoka itwara sima mu buryo bwa bulk',NULL,'2026-09-21 10:42:04'),(695,'border_charge','Customs duty','Customs duty',10,1,NULL,NULL,'2026-09-22 13:44:36'),(696,'border_charge','Import VAT','Import VAT',20,1,NULL,NULL,'2026-09-22 13:44:36'),(697,'border_charge','Withholding tax','Withholding tax',30,1,NULL,NULL,'2026-09-22 13:44:36'),(698,'border_charge','Clearing agent fee','Clearing agent fee',40,1,NULL,NULL,'2026-09-22 13:44:36'),(699,'border_charge','Transit bond','Transit bond',50,1,NULL,NULL,'2026-09-22 13:44:36'),(700,'border_charge','Weighbridge','Weighbridge',60,1,NULL,NULL,'2026-09-22 13:44:36'),(701,'border_charge','Escort fee','Escort fee',70,1,NULL,NULL,'2026-09-22 13:44:36'),(702,'border_charge','Parking and storage','Parking and storage',80,1,NULL,NULL,'2026-09-22 13:44:36'),(703,'border_charge','Road toll','Road toll',90,1,NULL,NULL,'2026-09-22 13:44:36'),(704,'border_charge','Other border charge','Other border charge',100,1,NULL,NULL,'2026-09-22 13:44:36'),(705,'border_document','Customs declaration','Customs declaration',10,1,NULL,NULL,'2026-09-22 13:44:36'),(706,'border_document','T1 transit bond','T1 transit bond',20,1,NULL,NULL,'2026-09-22 13:44:36'),(707,'border_document','Commercial invoice','Commercial invoice',30,1,NULL,NULL,'2026-09-22 13:44:36'),(708,'border_document','Packing list','Packing list',40,1,NULL,NULL,'2026-09-22 13:44:36'),(709,'border_document','Certificate of origin','Certificate of origin',50,1,NULL,NULL,'2026-09-22 13:44:36'),(710,'border_document','Bill of lading','Bill of lading',60,1,NULL,NULL,'2026-09-22 13:44:36'),(711,'border_document','Weighbridge ticket','Weighbridge ticket',70,1,NULL,NULL,'2026-09-22 13:44:36'),(712,'border_document','COMESA yellow card','COMESA yellow card',80,1,NULL,NULL,'2026-09-22 13:44:36'),(713,'border_document','Road transit permit','Road transit permit',90,1,NULL,NULL,'2026-09-22 13:44:36'),(714,'border_document','Phytosanitary certificate','Phytosanitary certificate',100,1,NULL,NULL,'2026-09-22 13:44:36'),(715,'border_document','Driver passport','Driver passport',110,1,NULL,NULL,'2026-09-22 13:44:36');
/*!40000 ALTER TABLE `lookup_values` ENABLE KEYS */;

/*!40000 ALTER TABLE `border_posts` DISABLE KEYS */;
INSERT INTO `border_posts` (`id`, `post_name`, `post_code`, `country_a`, `country_b`, `is_one_stop`, `typical_hours`, `contact_name`, `contact_phone`, `notes`, `is_active`, `deleted_at`, `created_at`) VALUES (1,'Gatuna / Katuna','GTN','Rwanda','Uganda',1,6.00,NULL,NULL,'Northern corridor to Kampala and Mombasa.',1,NULL,'2026-09-22 13:44:36'),(2,'Rusumo','RSM','Rwanda','Tanzania',1,8.00,NULL,NULL,'Central corridor to Dar es Salaam.',1,NULL,'2026-09-22 13:44:36'),(3,'Kagitumba / Mirama Hills','KGT','Rwanda','Uganda',1,5.00,NULL,NULL,NULL,1,NULL,'2026-09-22 13:44:36'),(4,'Rusizi I / Bukavu','RSZ','Rwanda','DR Congo',0,4.00,NULL,NULL,NULL,1,NULL,'2026-09-22 13:44:36'),(5,'La Corniche / Goma','GSN','Rwanda','DR Congo',0,4.00,NULL,NULL,NULL,1,NULL,'2026-09-22 13:44:36'),(6,'Nemba / Gasenyi','NMB','Rwanda','Burundi',0,5.00,NULL,NULL,NULL,1,NULL,'2026-09-22 13:44:36'),(7,'Namanga','NMG','Kenya','Tanzania',1,7.00,NULL,NULL,'On the Nairobi to Arusha run.',1,NULL,'2026-09-22 13:44:36'),(8,'Malaba','MLB','Kenya','Uganda',1,10.00,NULL,NULL,'The busiest post on the northern corridor.',1,NULL,'2026-09-22 13:44:36'),(9,'Busia','BSA','Kenya','Uganda',1,8.00,NULL,NULL,NULL,1,NULL,'2026-09-22 13:44:36'),(10,'Holili / Taveta','HLL','Tanzania','Kenya',1,6.00,NULL,NULL,NULL,1,NULL,'2026-09-22 13:44:36');
/*!40000 ALTER TABLE `border_posts` ENABLE KEYS */;

/*!40000 ALTER TABLE `company_settings` DISABLE KEYS */;
INSERT INTO `company_settings` (`id`, `setting_key`, `setting_value`, `setting_label`, `setting_group`, `input_type`, `updated_at`) VALUES (1,'company_name','','Company name','Company','text','2026-09-22 22:39:16'),(2,'company_tin','','TIN number','Company','text','2026-09-20 22:21:32'),(3,'company_phone','','Phone','Company','text','2026-09-20 22:21:32'),(4,'company_email','','Email','Company','text','2026-09-20 22:21:32'),(5,'company_address','','Address','Company','text','2026-09-20 22:21:32'),(6,'currency_code','RWF','Currency code','Finance','text','2026-09-20 22:03:40'),(7,'currency_symbol','RWF','Currency symbol','Finance','text','2026-09-20 22:03:40'),(8,'tax_rate','18','Default VAT rate (%)','Finance','number','2026-09-20 22:03:40'),(9,'invoice_prefix','INV','Invoice number prefix','Finance','text','2026-09-20 22:03:40'),(10,'payment_terms_days','30','Default payment terms (days)','Finance','number','2026-09-20 22:03:40'),(11,'licence_alert_days','90','Driver licence alert window (days)','Operations','number','2026-09-20 22:03:40'),(12,'document_alert_days','30','Vehicle document alert window (days)','Operations','number','2026-09-20 22:03:40'),(13,'service_alert_days','14','Service due alert window (days)','Operations','number','2026-09-20 22:03:40'),(14,'on_time_grace_minutes','30','On-time delivery grace (minutes)','Operations','number','2026-09-20 22:03:40'),(15,'max_login_attempts','5','Failed logins before lockout','Security','number','2026-09-20 22:03:40'),(16,'lockout_minutes','15','Lockout duration (minutes)','Security','number','2026-09-20 22:03:40'),(17,'accounting_basis','Accrual Basis','Reporting basis','Accounting','text','2026-09-20 22:03:40'),(18,'fiscal_year_start','01-01','Financial year starts (MM-DD)','Accounting','text','2026-09-20 22:03:40'),(19,'gl_auto_post','1','Post operational documents to the ledger automatically','Accounting','number','2026-09-20 22:03:40'),(20,'report_brand_color','AD7D00','Report colour (hex, no #)','Accounting','text','2026-09-20 22:03:41'),(21,'company_logo','','Logo for exports (PNG or JPG under assets/img)','Accounting','text','2026-09-20 22:03:41'),(23,'email_enabled','1','Send email notifications','Email','number','2026-09-21 09:55:24'),(24,'email_signature','This message was sent by the LMS logistics system on behalf of the company named above. Please do not reply to it directly.','Signature at the foot of every email','Email','text','2026-09-21 21:59:32'),(25,'email_min_severity','info','Least important severity to email (info, success, warning, danger)','Email','text','2026-09-21 09:55:24'),(26,'email_alerts_to_roles','1','Email the operational alerts (expiring documents, low stock, overdue invoices)','Email','number','2026-09-21 09:55:24'),(44,'base_currency','RWF','The currency the books are kept in','Company','text','2026-09-22 11:48:55');
/*!40000 ALTER TABLE `company_settings` ENABLE KEYS */;

/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` (`id`, `migration`, `applied_at`) VALUES (1,'20260918_000001_extend_logistics_schema.sql','2026-09-20 22:03:39'),(2,'20260918_000002_add_notifications.sql','2026-09-20 22:03:39'),(3,'20260919_000003_link_drivers_to_users.sql','2026-09-20 22:03:39'),(4,'20260919_000004_add_user_privilege.sql','2026-09-20 22:03:39'),(5,'20260920_000005_core_depth.sql','2026-09-20 22:03:40'),(6,'20260920_000006_billing_access_security.sql','2026-09-20 22:03:40'),(7,'20260920_000007_role_permission_matrix.sql','2026-09-20 22:03:40'),(8,'20260920_000008_driver_can_close_delivery.sql','2026-09-20 22:03:40'),(9,'20260920_000009_accounting_books.sql','2026-09-20 22:03:40'),(10,'20260921_000010_email_delivery.sql','2026-09-21 09:55:24'),(11,'20260921_000011_lookup_lists.sql','2026-09-21 10:26:59'),(12,'20260921_000012_warehouses_permission.sql','2026-09-21 10:30:16'),(13,'20260921_000013_repair_orphan_assigned_requests.sql','2026-09-21 11:17:36'),(14,'20260921_000014_stock_opening_movements.sql','2026-09-21 20:20:58'),(15,'20260921_000015_payment_methods.sql','2026-09-21 21:24:36'),(16,'20260921_000016_payment_method_details.sql','2026-09-21 21:34:52'),(17,'20260921_000017_vendor_branding.sql','2026-09-21 21:59:32'),(18,'20260922_000018_cheques_and_budget.sql','2026-09-22 07:30:15'),(19,'20260922_000018_remove_vendor_setting.sql','2026-09-22 07:30:15'),(20,'20260922_000019_currencies.sql','2026-09-22 11:48:55'),(21,'20260922_000020_border_clearance.sql','2026-09-22 13:44:37'),(22,'20260922_000021_rate_by_weight.sql','2026-09-22 14:12:19'),(23,'20260922_000022_warehouse_routing.sql','2026-09-22 15:12:07'),(24,'20260922_000023_request_quotes.sql','2026-09-22 15:58:06'),(25,'20260922_000024_currency_everywhere.sql','2026-09-22 17:14:25'),(26,'20260922_000025_request_contact.sql','2026-09-22 18:39:54'),(27,'20260922_000026_cargo_custody.sql','2026-09-22 21:04:16'),(28,'20260922_000027_shipment_from_stock.sql','2026-09-22 21:13:43'),(29,'20260922_000028_books_per_currency.sql','2026-09-22 21:49:21'),(30,'20260923_000029_profile_photo.sql','2026-09-22 22:26:41');
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `role_id`, `full_name`, `email`, `password_hash`, `phone`, `department`, `job_title`, `avatar_path`, `status`, `prvg`, `must_change_password`, `notify_by_email`, `password_changed_at`, `failed_login_count`, `locked_until`, `last_login_at`, `created_at`, `deleted_at`) VALUES (1,1,'ITEC ADMIN CARGO','admin@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 788 000 001','Administration','System Administrator',NULL,'active',1,1,1,NULL,0,NULL,'2026-09-23 00:37:04','2026-09-20 22:03:41',NULL),(2,2,'Aline Mukamana','aline@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 788 202 020','Operations','Head of Operations',NULL,'active',2,1,1,NULL,0,NULL,'2026-09-23 00:30:42','2026-09-20 22:03:41',NULL),(3,5,'Samuel Niyonzima','samuel@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 788 632 119','Fleet','Long-haul Driver',NULL,'active',2,1,1,NULL,0,NULL,'2026-09-18 06:18:00','2026-09-20 22:03:41',NULL),(4,3,'Eric Murenzi','eric@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 783 210 084','Fleet','Fleet Supervisor',NULL,'active',2,1,1,NULL,0,NULL,'2026-09-17 16:20:00','2026-09-20 22:03:41',NULL),(5,4,'Nadine Tuyisenge','nadine@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 782 110 554','Warehouse','Warehouse Supervisor',NULL,'active',2,1,1,NULL,0,NULL,'2026-09-16 09:35:00','2026-09-20 22:03:41',NULL),(6,6,'Emmanuel Safari','emmanuel@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 788 705 881','Finance','Finance Officer',NULL,'active',2,1,1,NULL,0,NULL,'2026-09-15 11:05:00','2026-09-20 22:03:41',NULL),(7,7,'Jean Pierre Habimana','jeanpierre@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 788 512 030','Management','Managing Director',NULL,'active',2,1,1,NULL,0,NULL,'2026-09-14 14:10:00','2026-09-20 22:03:41',NULL),(44,5,'Claude Bizimana','elieiradukunda2050@gmail.com','$2y$10$oQYPWwlKXp/wzgRv3Rxtguk988YJ7OrvCpmKhWYiKCXschsZ76BNW','+250 788 401 003','Fleet','Umushoferi',NULL,'active',2,1,1,'2026-09-21 19:35:52',0,NULL,'2026-09-22 22:40:03','2026-09-21 17:29:18',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;



-- The rows above already carry these, but a file gets edited by hand, and none
-- of the three is worth getting wrong.
UPDATE company_settings SET setting_value = '' WHERE setting_key IN (
    'company_name', 'company_tin', 'company_phone', 'company_email', 'company_address', 'company_logo'
);
UPDATE users SET must_change_password = 1, avatar_path = NULL;

SET FOREIGN_KEY_CHECKS = 1;