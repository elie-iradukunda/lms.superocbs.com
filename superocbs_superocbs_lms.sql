-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 30, 2026 at 06:34 PM
-- Server version: 10.11.19-MariaDB-cll-lve
-- PHP Version: 8.4.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `superocbs_superocbs_lms`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action_name` varchar(100) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` varchar(64) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action_name`, `entity_type`, `entity_id`, `reason`, `metadata`, `created_at`) VALUES
(316, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-23 06:51:18'),
(317, 1, 'account.password_changed', 'users', '1', NULL, NULL, '2026-09-23 06:51:45'),
(318, 1, 'account.profile_updated', 'users', '1', NULL, NULL, '2026-09-23 06:54:53'),
(319, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-23 07:01:19'),
(320, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-23 07:47:24'),
(321, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-23 08:09:50'),
(322, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-23 09:01:15'),
(323, 1, 'settings.updated', 'settings', NULL, NULL, '{\"changed\":26}', '2026-09-23 09:03:28'),
(324, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-23 09:13:05'),
(325, 1, 'auth.logout', 'user', '1', NULL, NULL, '2026-09-23 09:16:15'),
(326, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-23 10:34:27'),
(327, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-23 11:30:53'),
(328, 1, 'settings.updated', 'settings', NULL, NULL, '{\"changed\":26}', '2026-09-23 11:31:33'),
(329, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-23 14:01:25'),
(330, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-23 15:24:31'),
(331, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-23 18:37:44'),
(332, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-23 20:04:52'),
(333, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-24 10:38:46'),
(334, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-24 12:05:37'),
(335, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-24 17:41:54'),
(336, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-26 16:34:40'),
(337, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-26 16:34:50'),
(338, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-26 16:37:21'),
(339, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-26 16:41:00'),
(340, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-26 16:41:47'),
(341, NULL, 'auth.reset_requested', 'user', '1', NULL, '{\"email\":\"nzizaoscar25@gmail.com\"}', '2026-09-26 16:42:38'),
(342, NULL, 'auth.password_reset', 'user', '1', NULL, NULL, '2026-09-26 16:43:31'),
(343, NULL, 'auth.login_locked', 'user', '1', 'Login attempted on a locked account.', '{\"email\":\"nzizaoscar25@gmail.com\"}', '2026-09-26 16:43:43'),
(344, NULL, 'auth.login_locked', 'user', '1', 'Login attempted on a locked account.', '{\"email\":\"nzizaoscar25@gmail.com\"}', '2026-09-26 16:44:23'),
(345, NULL, 'auth.login_locked', 'user', '1', 'Login attempted on a locked account.', '{\"email\":\"nzizaoscar25@gmail.com\"}', '2026-09-26 16:45:26'),
(346, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-26 16:46:18'),
(347, 1, 'auth.logout', 'user', '1', NULL, NULL, '2026-09-26 17:06:47'),
(348, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-26 17:06:54'),
(349, 1, 'auth.logout', 'user', '1', NULL, NULL, '2026-09-26 17:07:02'),
(350, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-27 07:32:34'),
(351, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-27 07:32:56'),
(352, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-27 07:33:36'),
(353, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-28 07:25:16'),
(354, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-29 07:00:31'),
(355, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-29 07:00:40'),
(356, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-29 07:00:51'),
(357, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-29 07:02:14'),
(358, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"nzizaoscar25@gmail.com\"}', '2026-09-29 07:02:42'),
(359, 2, 'auth.login', 'user', '2', NULL, NULL, '2026-09-29 07:03:30'),
(360, 2, 'auth.logout', 'user', '2', NULL, NULL, '2026-09-29 07:05:36'),
(361, 157, 'auth.login', 'user', '157', NULL, NULL, '2026-09-29 07:05:51'),
(362, 157, 'account.password_changed', 'users', '157', NULL, NULL, '2026-09-29 07:06:07'),
(363, 157, 'account.profile_updated', 'users', '157', NULL, NULL, '2026-09-29 07:06:31'),
(364, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-29 08:53:48'),
(365, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-29 19:23:12'),
(366, 1, 'auth.login', 'user', '1', NULL, NULL, '2026-09-29 19:28:56'),
(367, 157, 'auth.login', 'user', '157', NULL, NULL, '2026-09-30 16:28:57'),
(368, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-30 16:32:22'),
(369, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-30 16:34:00'),
(370, NULL, 'auth.login_failed', 'user', NULL, 'Invalid login attempt.', '{\"email\":\"admin@itec.rw\"}', '2026-09-30 16:34:21');

-- --------------------------------------------------------

--
-- Table structure for table `border_charges`
--

CREATE TABLE `border_charges` (
  `id` int(10) UNSIGNED NOT NULL,
  `crossing_id` int(10) UNSIGNED NOT NULL,
  `charge_type` varchar(60) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `receipt_no` varchar(60) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `border_charges`
--

INSERT INTO `border_charges` (`id`, `crossing_id`, `charge_type`, `description`, `receipt_no`, `amount`) VALUES
(1, 1, 'Clearing agent fee', 'Lodging export declaration for 300 bags coffee at Rusumo', 'GLCF-RSM-2026-0412', 150000.00),
(2, 2, 'Escort fee', 'Customs escort from Rubavu to Goma depot', 'DGDA-GOM-2026-7781', 120.00);

-- --------------------------------------------------------

--
-- Table structure for table `border_crossings`
--

CREATE TABLE `border_crossings` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference` varchar(40) NOT NULL,
  `trip_id` int(10) UNSIGNED DEFAULT NULL,
  `shipment_id` int(10) UNSIGNED DEFAULT NULL,
  `vehicle_id` int(10) UNSIGNED DEFAULT NULL,
  `driver_id` int(10) UNSIGNED DEFAULT NULL,
  `border_post_id` int(10) UNSIGNED NOT NULL,
  `direction` enum('export','import','transit') NOT NULL DEFAULT 'export',
  `clearing_agent_id` int(10) UNSIGNED DEFAULT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `border_crossings`
--

INSERT INTO `border_crossings` (`id`, `reference`, `trip_id`, `shipment_id`, `vehicle_id`, `driver_id`, `border_post_id`, `direction`, `clearing_agent_id`, `declaration_no`, `transit_bond_no`, `seal_no`, `weighbridge_kg`, `arrived_at`, `lodged_at`, `cleared_at`, `departed_at`, `status`, `hold_reason`, `charges_total`, `currency`, `exchange_rate`, `base_amount`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'BDR-2026-0001', 1, 1, 1, 1, 2, 'export', 2, 'C 2026 RW 145872', 'T1-RSM-2026-08812', 'RWS-0045521', 33150.00, '2026-09-10 12:50:00', '2026-09-10 13:20:00', '2026-09-10 16:45:00', '2026-09-10 17:10:00', 'departed', NULL, 150000.00, 'RWF', 1.00000000, 150000.00, 'Coffee export for Akagera Coffee Exporters. Cleared same day.', NULL, '2026-09-10 12:50:00'),
(2, 'BDR-2026-0002', 2, 2, 2, 2, 5, 'export', 2, 'C 2026 RW 151230', NULL, 'RWS-0046108', 37200.00, '2026-09-29 15:30:00', '2026-09-29 16:10:00', NULL, NULL, 'lodged', NULL, 120.00, 'USD', 1450.00000000, 174000.00, 'Cement for Virunga Cement Distributors. Waiting for DGDA release.', NULL, '2026-09-29 15:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `border_documents`
--

CREATE TABLE `border_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `crossing_id` int(10) UNSIGNED NOT NULL,
  `document_type` varchar(60) NOT NULL,
  `document_no` varchar(80) DEFAULT NULL,
  `issued_on` date DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `is_received` tinyint(1) NOT NULL DEFAULT 0,
  `document_file` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `border_documents`
--

INSERT INTO `border_documents` (`id`, `crossing_id`, `document_type`, `document_no`, `issued_on`, `expires_on`, `is_received`, `document_file`, `notes`) VALUES
(1, 1, 'Customs declaration', 'C 2026 RW 145872', '2026-09-10', NULL, 1, NULL, 'Stamped by RRA at Rusumo'),
(2, 2, 'Commercial invoice', 'VCD-EXP-2026-0093', '2026-09-27', NULL, 0, NULL, 'Original awaited from Virunga Cement Distributors');

-- --------------------------------------------------------

--
-- Table structure for table `border_posts`
--

CREATE TABLE `border_posts` (
  `id` int(10) UNSIGNED NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `border_posts`
--

INSERT INTO `border_posts` (`id`, `post_name`, `post_code`, `country_a`, `country_b`, `is_one_stop`, `typical_hours`, `contact_name`, `contact_phone`, `notes`, `is_active`, `deleted_at`, `created_at`) VALUES
(1, 'Gatuna / Katuna', 'GTN', 'Rwanda', 'Uganda', 1, 6.00, NULL, NULL, 'Northern corridor to Kampala and Mombasa.', 1, NULL, '2026-09-22 13:44:36'),
(2, 'Rusumo', 'RSM', 'Rwanda', 'Tanzania', 1, 8.00, NULL, NULL, 'Central corridor to Dar es Salaam.', 1, NULL, '2026-09-22 13:44:36'),
(3, 'Kagitumba / Mirama Hills', 'KGT', 'Rwanda', 'Uganda', 1, 5.00, NULL, NULL, NULL, 1, NULL, '2026-09-22 13:44:36'),
(4, 'Rusizi I / Bukavu', 'RSZ', 'Rwanda', 'DR Congo', 0, 4.00, NULL, NULL, NULL, 1, NULL, '2026-09-22 13:44:36'),
(5, 'La Corniche / Goma', 'GSN', 'Rwanda', 'DR Congo', 0, 4.00, NULL, NULL, NULL, 1, NULL, '2026-09-22 13:44:36'),
(6, 'Nemba / Gasenyi', 'NMB', 'Rwanda', 'Burundi', 0, 5.00, NULL, NULL, NULL, 1, NULL, '2026-09-22 13:44:36'),
(7, 'Namanga', 'NMG', 'Kenya', 'Tanzania', 1, 7.00, NULL, NULL, 'On the Nairobi to Arusha run.', 1, NULL, '2026-09-22 13:44:36'),
(8, 'Malaba', 'MLB', 'Kenya', 'Uganda', 1, 10.00, NULL, NULL, 'The busiest post on the northern corridor.', 1, NULL, '2026-09-22 13:44:36'),
(9, 'Busia', 'BSA', 'Kenya', 'Uganda', 1, 8.00, NULL, NULL, NULL, 1, NULL, '2026-09-22 13:44:36'),
(10, 'Holili / Taveta', 'HLL', 'Tanzania', 'Kenya', 1, 6.00, NULL, NULL, NULL, 1, NULL, '2026-09-22 13:44:36');

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(60) NOT NULL,
  `setting_value` varchar(255) DEFAULT NULL,
  `setting_label` varchar(120) NOT NULL,
  `setting_group` varchar(60) NOT NULL DEFAULT 'Company',
  `input_type` varchar(20) NOT NULL DEFAULT 'text',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `company_settings`
--

INSERT INTO `company_settings` (`id`, `setting_key`, `setting_value`, `setting_label`, `setting_group`, `input_type`, `updated_at`) VALUES
(1, 'company_name', 'ITEC Cargo ltd', 'Company name', 'Company', 'text', '2026-09-23 09:03:28'),
(2, 'company_tin', '', 'TIN number', 'Company', 'text', '2026-09-20 22:21:32'),
(3, 'company_phone', '', 'Phone', 'Company', 'text', '2026-09-20 22:21:32'),
(4, 'company_email', '', 'Email', 'Company', 'text', '2026-09-20 22:21:32'),
(5, 'company_address', '', 'Address', 'Company', 'text', '2026-09-20 22:21:32'),
(6, 'currency_code', 'RWF', 'Currency code', 'Finance', 'text', '2026-09-20 22:03:40'),
(7, 'currency_symbol', 'RWF', 'Currency symbol', 'Finance', 'text', '2026-09-20 22:03:40'),
(8, 'tax_rate', '18', 'Default VAT rate (%)', 'Finance', 'number', '2026-09-20 22:03:40'),
(9, 'invoice_prefix', 'INV', 'Invoice number prefix', 'Finance', 'text', '2026-09-20 22:03:40'),
(10, 'payment_terms_days', '30', 'Default payment terms (days)', 'Finance', 'number', '2026-09-20 22:03:40'),
(11, 'licence_alert_days', '90', 'Driver licence alert window (days)', 'Operations', 'number', '2026-09-20 22:03:40'),
(12, 'document_alert_days', '30', 'Vehicle document alert window (days)', 'Operations', 'number', '2026-09-20 22:03:40'),
(13, 'service_alert_days', '14', 'Service due alert window (days)', 'Operations', 'number', '2026-09-20 22:03:40'),
(14, 'on_time_grace_minutes', '30', 'On-time delivery grace (minutes)', 'Operations', 'number', '2026-09-20 22:03:40'),
(15, 'max_login_attempts', '5', 'Failed logins before lockout', 'Security', 'number', '2026-09-20 22:03:40'),
(16, 'lockout_minutes', '15', 'Lockout duration (minutes)', 'Security', 'number', '2026-09-20 22:03:40'),
(17, 'accounting_basis', 'Accrual Basis', 'Reporting basis', 'Accounting', 'text', '2026-09-20 22:03:40'),
(18, 'fiscal_year_start', '01-01', 'Financial year starts (MM-DD)', 'Accounting', 'text', '2026-09-20 22:03:40'),
(19, 'gl_auto_post', '1', 'Post operational documents to the ledger automatically', 'Accounting', 'number', '2026-09-20 22:03:40'),
(20, 'report_brand_color', 'AD7D00', 'Report colour (hex, no #)', 'Accounting', 'text', '2026-09-20 22:03:41'),
(21, 'company_logo', '', 'Logo for exports (PNG or JPG under assets/img)', 'Accounting', 'text', '2026-09-20 22:03:41'),
(23, 'email_enabled', '1', 'Send email notifications', 'Email', 'number', '2026-09-21 09:55:24'),
(24, 'email_signature', 'This message was sent by the LMS logistics system on behalf of the company named above. Please do not reply to it directly.', 'Signature at the foot of every email', 'Email', 'text', '2026-09-21 21:59:32'),
(25, 'email_min_severity', 'info', 'Least important severity to email (info, success, warning, danger)', 'Email', 'text', '2026-09-21 09:55:24'),
(26, 'email_alerts_to_roles', '1', 'Email the operational alerts (expiring documents, low stock, overdue invoices)', 'Email', 'number', '2026-09-21 09:55:24'),
(44, 'base_currency', 'Frw', 'The currency the books are kept in', 'Company', 'text', '2026-09-23 11:31:33');

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` char(3) NOT NULL COMMENT 'ISO 4217: RWF, KES, TZS, USD',
  `currency_name` varchar(60) NOT NULL,
  `symbol` varchar(8) DEFAULT NULL,
  `country` varchar(60) DEFAULT NULL,
  `decimals` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'RWF and TZS are whole units; USD and KES take cents',
  `is_base` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 100,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`id`, `code`, `currency_name`, `symbol`, `country`, `decimals`, `is_base`, `is_active`, `sort_order`, `deleted_at`, `created_at`) VALUES
(1, 'RWF', 'Rwandan franc', 'FRw', 'Rwanda', 0, 1, 1, 10, NULL, '2026-09-22 11:48:07'),
(2, 'KES', 'Kenyan shilling', 'KSh', 'Kenya', 2, 0, 1, 20, NULL, '2026-09-22 11:48:07'),
(3, 'TZS', 'Tanzanian shilling', 'TSh', 'Tanzania', 0, 0, 1, 30, NULL, '2026-09-22 11:48:07'),
(4, 'UGX', 'Ugandan shilling', 'USh', 'Uganda', 0, 0, 1, 40, NULL, '2026-09-22 11:48:07'),
(5, 'USD', 'US dollar', '$', 'International', 2, 0, 1, 50, NULL, '2026-09-22 11:48:07');

-- --------------------------------------------------------

--
-- Table structure for table `currency_rates`
--

CREATE TABLE `currency_rates` (
  `id` int(10) UNSIGNED NOT NULL,
  `currency_code` char(3) NOT NULL,
  `effective_from` date NOT NULL,
  `rate` decimal(18,8) NOT NULL,
  `source` varchar(80) DEFAULT NULL COMMENT 'where the rate came from: BNR, the bank, an agreement',
  `notes` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `currency_rates`
--

INSERT INTO `currency_rates` (`id`, `currency_code`, `effective_from`, `rate`, `source`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'USD', '2026-09-01', 1450.00000000, 'BNR', 'BNR reference rate, September 2026', NULL, '2026-09-01 08:00:00'),
(2, 'TZS', '2026-09-01', 0.53700000, 'BNR', 'Used for fuel and charges paid in Tanzania', NULL, '2026-09-01 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL,
  `customer_code` varchar(32) NOT NULL,
  `customer_name` varchar(180) NOT NULL,
  `customer_type` enum('corporate','government','ngo','individual') NOT NULL DEFAULT 'corporate',
  `contact_name` varchar(150) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `district` varchar(80) DEFAULT NULL,
  `tin_number` varchar(40) DEFAULT NULL,
  `payment_terms_days` int(10) UNSIGNED NOT NULL DEFAULT 30,
  `credit_limit` decimal(14,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `status` enum('active','on_hold','inactive') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_code`, `customer_name`, `customer_type`, `contact_name`, `phone`, `email`, `address`, `district`, `tin_number`, `payment_terms_days`, `credit_limit`, `currency`, `status`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'CUS-2026-0001', 'Akagera Coffee Exporters Ltd', 'corporate', 'Diane Uwase', '+250 788 314 552', 'logistics@akageracoffee.rw', 'KG 11 Ave, Kimihurura', 'Gasabo', '102345678', 30, 25000000.00, 'RWF', 'active', 'Green coffee exporter. Ships to Dar es Salaam port.', NULL, '2026-09-05 09:10:00'),
(2, 'CUS-2026-0002', 'Virunga Cement Distributors Ltd', 'corporate', 'Patrick Nshimiyimana', '+250 783 220 918', 'orders@virungacement.rw', 'Gikondo Industrial Zone', 'Kicukiro', '108765432', 30, 20000.00, 'USD', 'active', 'Cement distributor supplying Goma, DR Congo. Billed in USD.', NULL, '2026-09-20 10:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `id` int(10) UNSIGNED NOT NULL,
  `delivery_code` varchar(32) NOT NULL,
  `trip_id` int(10) UNSIGNED DEFAULT NULL,
  `shipment_id` int(10) UNSIGNED DEFAULT NULL,
  `recipient_name` varchar(150) NOT NULL,
  `recipient_phone` varchar(40) DEFAULT NULL,
  `destination` varchar(150) NOT NULL,
  `destination_warehouse_id` int(10) UNSIGNED DEFAULT NULL,
  `planned_at` datetime DEFAULT NULL,
  `dropped_at` datetime DEFAULT NULL,
  `status` enum('loading','in_transit','at_destination','delivered','failed') NOT NULL DEFAULT 'loading',
  `attempt_number` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `failure_reason` enum('none','recipient_absent','address_wrong','goods_damaged','goods_refused','vehicle_breakdown','access_denied','weather','other') NOT NULL DEFAULT 'none',
  `failure_notes` varchar(255) DEFAULT NULL,
  `rescheduled_at` datetime DEFAULT NULL,
  `proof_file` varchar(255) DEFAULT NULL,
  `recipient_signature` varchar(255) DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `delivered_by` int(10) UNSIGNED DEFAULT NULL,
  `collected_by` varchar(150) DEFAULT NULL,
  `collected_id_no` varchar(60) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deliveries`
--

INSERT INTO `deliveries` (`id`, `delivery_code`, `trip_id`, `shipment_id`, `recipient_name`, `recipient_phone`, `destination`, `destination_warehouse_id`, `planned_at`, `dropped_at`, `status`, `attempt_number`, `failure_reason`, `failure_notes`, `rescheduled_at`, `proof_file`, `recipient_signature`, `delivered_at`, `delivered_by`, `collected_by`, `collected_id_no`, `deleted_at`, `created_at`) VALUES
(1, 'DEL-2026-0001', 1, 1, 'Bahari Freight Agents Ltd', '+255 754 210 330', 'Dar es Salaam Port, Tanzania', NULL, '2026-09-13 18:00:00', '2026-09-13 16:45:00', 'delivered', 1, 'none', NULL, NULL, NULL, NULL, '2026-09-13 17:05:00', 3, 'Hamisi Mwinyi', 'TZ-NIDA-19850612-00123', NULL, '2026-09-09 09:00:00'),
(2, 'DEL-2026-0002', 2, 2, 'Virunga Cement Distributors - Goma depot', '+243 997 120 455', 'Goma, DR Congo', NULL, '2026-09-30 14:00:00', NULL, 'in_transit', 1, 'none', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-28 11:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
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
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `user_id`, `full_name`, `national_id`, `date_of_birth`, `phone`, `address`, `license_number`, `license_class`, `license_expiry`, `emergency_contact`, `emergency_phone`, `hired_on`, `status`, `notes`, `created_at`, `deleted_at`) VALUES
(1, 3, 'Samuel Niyonzima', '1198780012345678', '1987-04-12', '+250 788 632 119', 'Kicukiro, Kigali', 'RW-DL-0456789', 'C/D', '2028-03-31', 'Grace Uwimana', '+250 788 441 220', '2019-02-01', 'available', 'Long-haul driver on the central corridor (Dar es Salaam).', '2026-09-05 08:00:00', NULL),
(2, 44, 'Claude Bizimana', '1199080023456789', '1990-08-23', '+250 788 401 003', 'Rubavu, Western Province', 'RW-DL-0561234', 'C/D', '2026-12-15', 'Alice Mukeshimana', '+250 782 330 114', '2021-05-10', 'on_trip', 'Covers the Rubavu / Goma run. Licence due for renewal in December.', '2026-09-05 08:05:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `email_outbox`
--

CREATE TABLE `email_outbox` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `message_key` varchar(120) NOT NULL,
  `category` enum('notification','alert','password_reset','new_account','invoice','manual','test') NOT NULL DEFAULT 'notification',
  `to_email` varchar(190) NOT NULL,
  `to_name` varchar(150) DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `body_html` mediumtext NOT NULL,
  `body_text` mediumtext DEFAULT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` varchar(64) DEFAULT NULL,
  `status` enum('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
  `provider_id` varchar(120) DEFAULT NULL,
  `error` varchar(500) DEFAULT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_outbox`
--

INSERT INTO `email_outbox` (`id`, `message_key`, `category`, `to_email`, `to_name`, `user_id`, `subject`, `body_html`, `body_text`, `entity_type`, `entity_id`, `status`, `provider_id`, `error`, `attempts`, `sent_at`, `created_at`) VALUES
(52, 'reset-27b17d7f600b1d5da2f3725e5bd21305002d77c8', 'password_reset', 'nzizaoscar25@gmail.com', 'ITEC ADMIN CARGO', 1, 'Reset your LMS password', '<!doctype html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>Reset your LMS password</title></head><body style=\"margin:0;padding:0;background:#f1f4f7;font-family:Segoe UI,Helvetica,Arial,sans-serif\"><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" style=\"width:100%;background:#f1f4f7;padding:24px 12px\"><tr><td align=\"center\"><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" style=\"width:100%;max-width:580px;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(20,30,40,.07)\"><tr><td style=\"background:#AD7D00;padding:18px 26px\"><span style=\"color:#ffffff;font-size:16px;font-weight:700;letter-spacing:.4px\">ITEC Cargo ltd</span><span style=\"display:block;color:rgba(255,255,255,.82);font-size:11px;letter-spacing:1.2px;text-transform:uppercase;margin-top:2px\">Logistics Management System</span></td></tr><tr><td style=\"padding:26px\"><h1 style=\"margin:0 0 16px;font-size:19px;line-height:1.35;color:#18222c;font-weight:700\">Reset your password</h1><p style=\"margin:0 0 14px;font-size:15px;line-height:1.6;color:#33404c\">Hello ITEC ADMIN CARGO,</p><p style=\"margin:0 0 14px;font-size:15px;line-height:1.6;color:#33404c\">Someone asked to reset the password on your LMS account. Use the button below to choose a new one.</p><p style=\"margin:0 0 14px;font-size:15px;line-height:1.6;color:#33404c\">The link works once and expires in one hour. If you did not ask for this, ignore this message and your password stays as it is.</p><p style=\"margin:24px 0 8px\"><a href=\"https://lms.superocbs.com/reset-password/ba50443d77eeeda6cb3621f7c6df70f744cfb6c1b137306d40f3a2efd79a6ac7\" style=\"display:inline-block;padding:11px 22px;background:#AD7D00;color:#ffffff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:600\">Choose a new password</a></p><p style=\"margin:0 0 6px;font-size:12px;color:#8a949e\">If the button does not work, open this address:<br><span style=\"color:#5b6670;word-break:break-all\">https://lms.superocbs.com/reset-password/ba50443d77eeeda6cb3621f7c6df70f744cfb6c1b137306d40f3a2efd79a6ac7</span></p></td></tr><tr><td style=\"padding:16px 26px;background:#f7f9fb;border-top:1px solid #e9edf1\"><p style=\"margin:0;font-size:11px;line-height:1.6;color:#8a949e\">This message was sent by the LMS logistics system on behalf of the company named above. Please do not reply to it directly.</p><p style=\"margin:6px 0 0;font-size:11px;color:#9aa4ad\">Powered by ITEC LTD &copy; 2026</p></td></tr></table></td></tr></table></body></html>', 'Reset your password\n\nHello ITEC ADMIN CARGO,\n\nSomeone asked to reset the password on your LMS account. Use the button below to choose a new one.\n\nThe link works once and expires in one hour. If you did not ask for this, ignore this message and your password stays as it is.\n\n\nChoose a new password: https://lms.superocbs.com/reset-password/ba50443d77eeeda6cb3621f7c6df70f744cfb6c1b137306d40f3a2efd79a6ac7\n\nThis message was sent by the LMS logistics system on behalf of the company named above. Please do not reply to it directly.\nPowered by ITEC LTD (c) 2026', 'users', '1', 'skipped', NULL, 'Email is switched off or no API key is configured.', 1, NULL, '2026-09-26 16:42:38');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference_code` varchar(32) DEFAULT NULL,
  `trip_id` int(10) UNSIGNED DEFAULT NULL,
  `vehicle_id` int(10) UNSIGNED DEFAULT NULL,
  `category` varchar(60) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `exchange_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_amount` decimal(14,2) DEFAULT NULL,
  `submitted_by` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','mobile_money','fuel_card','cheque') NOT NULL DEFAULT 'cash',
  `receipt_file` varchar(255) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `reference_code`, `trip_id`, `vehicle_id`, `category`, `amount`, `currency`, `exchange_rate`, `base_amount`, `submitted_by`, `status`, `approved_by`, `approved_at`, `rejection_reason`, `payment_method`, `receipt_file`, `expense_date`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'EXP-2026-0001', 1, 1, 'Allowance', 180000.00, 'RWF', 1.00000000, 180000.00, 3, 'approved', 6, '2026-09-14 10:00:00', NULL, 'mobile_money', NULL, '2026-09-14', 'Night-out allowance, 4 nights x 45,000 on the Dar es Salaam run.', NULL, '2026-09-14 08:30:00'),
(2, 'EXP-2026-0002', 2, 2, 'Permit', 60.00, 'USD', 1450.00000000, 87000.00, 44, 'pending', NULL, NULL, NULL, 'cash', NULL, '2026-09-29', 'DR Congo road transit permit paid at La Corniche.', NULL, '2026-09-29 16:40:00');

-- --------------------------------------------------------

--
-- Table structure for table `fuel_records`
--

CREATE TABLE `fuel_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference_code` varchar(32) DEFAULT NULL,
  `vehicle_id` int(10) UNSIGNED NOT NULL,
  `station_name` varchar(150) NOT NULL,
  `fuel_type` enum('diesel','petrol','electric','cng') NOT NULL DEFAULT 'diesel',
  `litres` decimal(10,2) NOT NULL,
  `is_full_tank` tinyint(1) NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `exchange_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_amount` decimal(14,2) DEFAULT NULL,
  `mileage` int(10) UNSIGNED DEFAULT NULL,
  `previous_mileage` int(10) UNSIGNED DEFAULT NULL,
  `driver_id` int(10) UNSIGNED DEFAULT NULL,
  `trip_id` int(10) UNSIGNED DEFAULT NULL,
  `purchased_at` datetime NOT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fuel_records`
--

INSERT INTO `fuel_records` (`id`, `reference_code`, `vehicle_id`, `station_name`, `fuel_type`, `litres`, `is_full_tank`, `unit_price`, `currency`, `exchange_rate`, `base_amount`, `mileage`, `previous_mileage`, `driver_id`, `trip_id`, `purchased_at`, `receipt_file`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'FUE-2026-0001', 1, 'Puma Energy Nyakanazi, Tanzania', 'diesel', 400.00, 1, 2950.00, 'TZS', 0.53700000, 633660.00, 183110, 182690, 1, 1, '2026-09-11 09:20:00', NULL, 'Refuelled in Tanzania on the way to Dar es Salaam.', NULL, '2026-09-11 09:30:00'),
(2, 'FUE-2026-0002', 2, 'SP Gisozi, Kigali', 'diesel', 300.00, 1, 1560.00, 'RWF', 1.00000000, 468000.00, 96180, 95540, 2, 2, '2026-09-29 06:50:00', NULL, 'Full tank before the Goma trip.', NULL, '2026-09-29 07:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `gl_accounts`
--

CREATE TABLE `gl_accounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_code` varchar(20) NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `account_type` enum('asset','liability','equity','income','cost_of_sales','expense') NOT NULL,
  `normal_balance` enum('debit','credit') NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `is_header` tinyint(1) NOT NULL DEFAULT 0,
  `is_contra` tinyint(1) NOT NULL DEFAULT 0,
  `depth` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `report_section` varchar(60) NOT NULL DEFAULT 'Other',
  `section_order` smallint(5) UNSIGNED NOT NULL DEFAULT 999,
  `cash_flow_class` enum('operating','investing','financing','cash','none') NOT NULL DEFAULT 'none',
  `is_bank` tinyint(1) NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gl_accounts`
--

INSERT INTO `gl_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `is_header`, `is_contra`, `depth`, `report_section`, `section_order`, `cash_flow_class`, `is_bank`, `description`, `is_active`, `deleted_at`, `created_at`) VALUES
(1, '1000', 'Cash and bank', 'asset', 'debit', NULL, 1, 0, 0, 'Cash and bank', 10, 'cash', 0, 'Every account the company can pay from.', 1, NULL, '2026-09-20 22:03:41'),
(2, '1010', 'Petty cash', 'asset', 'debit', 1, 0, 0, 1, 'Cash and bank', 10, 'cash', 1, 'Cash held at the Kigali office.', 1, NULL, '2026-09-20 22:03:41'),
(3, '1020', 'Bank - main account', 'asset', 'debit', 1, 0, 0, 1, 'Cash and bank', 10, 'cash', 1, 'The operating current account.', 1, NULL, '2026-09-20 22:03:41'),
(4, '1030', 'Mobile money', 'asset', 'debit', 1, 0, 0, 1, 'Cash and bank', 10, 'cash', 1, 'Driver float and field payments.', 1, NULL, '2026-09-20 22:03:41'),
(5, '1100', 'Accounts receivable', 'asset', 'debit', NULL, 0, 0, 0, 'Accounts receivable', 20, 'operating', 0, 'Invoices issued to customers and not yet settled.', 1, NULL, '2026-09-20 22:03:41'),
(6, '1200', 'Inventory', 'asset', 'debit', NULL, 0, 0, 0, 'Other current assets', 30, 'operating', 0, 'Stock held in the warehouses.', 1, NULL, '2026-09-20 22:03:41'),
(7, '1250', 'Prepayments and deposits', 'asset', 'debit', NULL, 0, 0, 0, 'Other current assets', 30, 'operating', 0, 'Insurance and rent paid in advance.', 1, NULL, '2026-09-20 22:03:41'),
(8, '1500', 'Motor vehicles', 'asset', 'debit', NULL, 0, 0, 0, 'Fixed assets', 40, 'investing', 0, 'The fleet, at cost.', 1, NULL, '2026-09-20 22:03:41'),
(9, '1510', 'Equipment and fittings', 'asset', 'debit', NULL, 0, 0, 0, 'Fixed assets', 40, 'investing', 0, 'Warehouse and office equipment, at cost.', 1, NULL, '2026-09-20 22:03:41'),
(10, '1590', 'Accumulated depreciation', 'asset', 'credit', NULL, 0, 1, 0, 'Fixed assets', 40, 'investing', 0, 'Depreciation charged to date on the fleet and equipment.', 1, NULL, '2026-09-20 22:03:41'),
(11, '2000', 'Accounts payable', 'liability', 'credit', NULL, 0, 0, 0, 'Accounts payable', 50, 'operating', 0, 'Suppliers, workshops and fuel stations not yet paid.', 1, NULL, '2026-09-20 22:03:41'),
(12, '2100', 'VAT payable', 'liability', 'credit', NULL, 0, 0, 0, 'Other current liabilities', 60, 'operating', 0, 'VAT charged on invoices and owed to RRA.', 1, NULL, '2026-09-20 22:03:41'),
(13, '2150', 'Accrued expenses', 'liability', 'credit', NULL, 0, 0, 0, 'Other current liabilities', 60, 'operating', 0, 'Costs incurred but not yet invoiced by the supplier.', 1, NULL, '2026-09-20 22:03:41'),
(14, '2200', 'Payroll liabilities', 'liability', 'credit', NULL, 0, 0, 0, 'Other current liabilities', 60, 'operating', 0, 'Net pay, RSSB and PAYE owed.', 1, NULL, '2026-09-20 22:03:41'),
(15, '2500', 'Motor vehicle loans', 'liability', 'credit', NULL, 0, 0, 0, 'Long term liabilities', 70, 'financing', 0, 'Asset finance on the fleet.', 1, NULL, '2026-09-20 22:03:41'),
(16, '3000', 'Share capital', 'equity', 'credit', NULL, 0, 0, 0, 'Equity', 80, 'financing', 0, 'Capital introduced by the owners.', 1, NULL, '2026-09-20 22:03:41'),
(17, '3100', 'Retained earnings', 'equity', 'credit', NULL, 0, 0, 0, 'Equity', 80, 'financing', 0, 'Profit of earlier years kept in the business.', 1, NULL, '2026-09-20 22:03:41'),
(18, '3200', 'Drawings and dividends', 'equity', 'debit', NULL, 0, 0, 0, 'Equity', 80, 'financing', 0, 'Profit taken out by the owners.', 1, NULL, '2026-09-20 22:03:41'),
(19, '4000', 'Freight revenue', 'income', 'credit', NULL, 0, 0, 0, 'Income', 100, 'operating', 0, 'Transport invoiced to customers.', 1, NULL, '2026-09-20 22:03:41'),
(20, '4100', 'Warehousing and handling revenue', 'income', 'credit', NULL, 0, 0, 0, 'Income', 100, 'operating', 0, 'Storage, loading and handling charged out.', 1, NULL, '2026-09-20 22:03:41'),
(21, '4900', 'Other income', 'income', 'credit', NULL, 0, 0, 0, 'Other income', 140, 'operating', 0, 'Anything outside the main trade.', 1, NULL, '2026-09-20 22:03:41'),
(22, '5000', 'Fuel', 'cost_of_sales', 'debit', NULL, 0, 0, 0, 'Cost of sales', 110, 'operating', 0, 'Diesel and petrol drawn against trips.', 1, NULL, '2026-09-20 22:03:41'),
(23, '5100', 'Driver allowances', 'cost_of_sales', 'debit', NULL, 0, 0, 0, 'Cost of sales', 110, 'operating', 0, 'Night-out and per-diem paid to drivers.', 1, NULL, '2026-09-20 22:03:41'),
(24, '5200', 'Tolls, parking and permits', 'cost_of_sales', 'debit', NULL, 0, 0, 0, 'Cost of sales', 110, 'operating', 0, 'Road charges incurred on a trip.', 1, NULL, '2026-09-20 22:03:41'),
(25, '5300', 'Subcontracted transport', 'cost_of_sales', 'debit', NULL, 0, 0, 0, 'Cost of sales', 110, 'operating', 0, 'Work given to another carrier.', 1, NULL, '2026-09-20 22:03:41'),
(26, '5400', 'Loading and handling', 'cost_of_sales', 'debit', NULL, 0, 0, 0, 'Cost of sales', 110, 'operating', 0, 'Casual labour at the warehouse and at drop points.', 1, NULL, '2026-09-20 22:03:41'),
(27, '6000', 'Vehicle maintenance and repairs', 'expense', 'debit', NULL, 0, 0, 0, 'Operating expenses', 120, 'operating', 0, 'Servicing, parts and workshop labour.', 1, NULL, '2026-09-20 22:03:41'),
(28, '6100', 'Insurance', 'expense', 'debit', NULL, 0, 0, 0, 'Operating expenses', 120, 'operating', 0, 'Fleet and goods-in-transit cover.', 1, NULL, '2026-09-20 22:03:41'),
(29, '6200', 'Salaries and wages', 'expense', 'debit', NULL, 0, 0, 0, 'Operating expenses', 120, 'operating', 0, 'Staff pay other than driver allowances.', 1, NULL, '2026-09-20 22:03:41'),
(30, '6300', 'Office and administration', 'expense', 'debit', NULL, 0, 0, 0, 'Operating expenses', 120, 'operating', 0, 'Rent, communications, stationery and software.', 1, NULL, '2026-09-20 22:03:41'),
(31, '6400', 'Depreciation', 'expense', 'debit', NULL, 0, 0, 0, 'Operating expenses', 120, 'operating', 0, 'Wear charged against the fleet and equipment.', 1, NULL, '2026-09-20 22:03:41'),
(32, '6500', 'Bank charges', 'expense', 'debit', NULL, 0, 0, 0, 'Operating expenses', 120, 'operating', 0, 'Transfer fees and account charges.', 1, NULL, '2026-09-20 22:03:41'),
(33, '6900', 'Other expenses', 'expense', 'debit', NULL, 0, 0, 0, 'Other expenses', 150, 'operating', 0, 'Anything that does not belong above.', 1, NULL, '2026-09-20 22:03:41'),
(301, '5500', 'Customs duty and levies', 'expense', 'debit', NULL, 0, 0, 1, 'Operating expenses', 550, 'operating', 0, 'Duty, VAT and levies paid at a border to release a load.', 1, NULL, '2026-09-22 13:44:36'),
(302, '5600', 'Clearing and forwarding fees', 'expense', 'debit', NULL, 0, 0, 1, 'Operating expenses', 560, 'operating', 0, 'What clearing agents charge to lodge and follow a declaration.', 1, NULL, '2026-09-22 13:44:36'),
(303, '5700', 'Border and transit charges', 'expense', 'debit', NULL, 0, 0, 1, 'Operating expenses', 570, 'operating', 0, 'Weighbridge, escort, parking and transit fees at a crossing.', 1, NULL, '2026-09-22 13:44:36');

-- --------------------------------------------------------

--
-- Table structure for table `gl_budgets`
--

CREATE TABLE `gl_budgets` (
  `id` int(10) UNSIGNED NOT NULL,
  `fiscal_year` smallint(5) UNSIGNED NOT NULL,
  `account_id` int(10) UNSIGNED NOT NULL,
  `month` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = the whole year, 1-12 = one month',
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gl_budgets`
--

INSERT INTO `gl_budgets` (`id`, `fiscal_year`, `account_id`, `month`, `amount`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 2026, 19, 0, 180000000.00, 'Freight revenue target for 2026', NULL, '2026-09-01 09:00:00'),
(2, 2026, 22, 0, 36000000.00, 'Annual fuel budget for the fleet', NULL, '2026-09-01 09:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `gl_cheques`
--

CREATE TABLE `gl_cheques` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference` varchar(40) NOT NULL COMMENT 'CHQ-2026-0001, issued by the system',
  `cheque_no` varchar(40) DEFAULT NULL COMMENT 'the number printed on the paper leaf',
  `bank_account_id` int(10) UNSIGNED NOT NULL,
  `cheque_book_id` int(10) UNSIGNED DEFAULT NULL,
  `payee_name` varchar(200) NOT NULL COMMENT 'exactly as written on the leaf',
  `supplier_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'when the payee is a supplier on file',
  `payee_address` varchar(255) DEFAULT NULL,
  `cheque_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `memo` varchar(255) DEFAULT NULL,
  `status` enum('draft','issued','presented','void') NOT NULL DEFAULT 'draft',
  `entry_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'gl_journal_entries, once posted',
  `prepared_by` int(10) UNSIGNED DEFAULT NULL,
  `issued_by` int(10) UNSIGNED DEFAULT NULL,
  `issued_at` datetime DEFAULT NULL,
  `printed_at` datetime DEFAULT NULL,
  `presented_on` date DEFAULT NULL,
  `void_by` int(10) UNSIGNED DEFAULT NULL,
  `void_at` datetime DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gl_cheques`
--

INSERT INTO `gl_cheques` (`id`, `reference`, `cheque_no`, `bank_account_id`, `cheque_book_id`, `payee_name`, `supplier_id`, `payee_address`, `cheque_date`, `amount`, `memo`, `status`, `entry_id`, `prepared_by`, `issued_by`, `issued_at`, `printed_at`, `presented_on`, `void_by`, `void_at`, `void_reason`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'CHQ-2026-0001', '100026', 3, 2, 'Kigali Truck Parts & Service Ltd', 1, 'KK 15 Rd, Gikondo, Kigali', '2026-09-17', 1245000.00, 'Service of RAD 482 K - MNT-2026-0001', 'presented', NULL, 6, 6, '2026-09-17 11:00:00', '2026-09-17 11:05:00', '2026-09-22', NULL, NULL, NULL, NULL, NULL, '2026-09-17 10:45:00'),
(2, 'CHQ-2026-0002', '100027', 3, 2, 'Great Lakes Clearing & Forwarding Ltd', 2, 'Rusumo Border, Kirehe', '2026-09-26', 150000.00, 'Clearing fee BDR-2026-0001 (Akagera coffee)', 'issued', NULL, 6, 6, '2026-09-26 14:00:00', '2026-09-26 14:02:00', NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-26 13:50:00');

-- --------------------------------------------------------

--
-- Table structure for table `gl_cheque_books`
--

CREATE TABLE `gl_cheque_books` (
  `id` int(10) UNSIGNED NOT NULL,
  `bank_account_id` int(10) UNSIGNED NOT NULL COMMENT 'gl_accounts.id, an account marked is_bank',
  `prefix` varchar(20) DEFAULT NULL COMMENT 'when the leaves carry letters as well as digits',
  `first_no` bigint(20) UNSIGNED NOT NULL,
  `last_no` bigint(20) UNSIGNED NOT NULL,
  `next_no` bigint(20) UNSIGNED NOT NULL,
  `status` enum('active','finished') NOT NULL DEFAULT 'active',
  `notes` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gl_cheque_books`
--

INSERT INTO `gl_cheque_books` (`id`, `bank_account_id`, `prefix`, `first_no`, `last_no`, `next_no`, `status`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 3, NULL, 100001, 100025, 100026, 'finished', 'First cheque book on the main account, all leaves used', NULL, '2026-01-05 09:00:00'),
(2, 3, NULL, 100026, 100075, 100028, 'active', 'Current cheque book on the main account', NULL, '2026-09-01 09:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `gl_cheque_lines`
--

CREATE TABLE `gl_cheque_lines` (
  `id` int(10) UNSIGNED NOT NULL,
  `cheque_id` int(10) UNSIGNED NOT NULL,
  `account_id` int(10) UNSIGNED NOT NULL COMMENT 'gl_accounts.id, what the money was for',
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gl_cheque_lines`
--

INSERT INTO `gl_cheque_lines` (`id`, `cheque_id`, `account_id`, `description`, `amount`) VALUES
(1, 1, 27, 'Full service of RAD 482 K after Dar es Salaam run', 1245000.00),
(2, 2, 302, 'Clearing and forwarding at Rusumo, BDR-2026-0001', 150000.00);

-- --------------------------------------------------------

--
-- Table structure for table `gl_fiscal_periods`
--

CREATE TABLE `gl_fiscal_periods` (
  `id` int(10) UNSIGNED NOT NULL,
  `period_label` varchar(40) NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `closed_by` int(10) UNSIGNED DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gl_fiscal_periods`
--

INSERT INTO `gl_fiscal_periods` (`id`, `period_label`, `starts_on`, `ends_on`, `status`, `closed_by`, `closed_at`) VALUES
(1, '2026-01', '2026-01-01', '2026-01-31', 'open', NULL, NULL),
(2, '2026-02', '2026-02-01', '2026-02-28', 'open', NULL, NULL),
(3, '2026-03', '2026-03-01', '2026-03-31', 'open', NULL, NULL),
(4, '2026-04', '2026-04-01', '2026-04-30', 'open', NULL, NULL),
(5, '2026-05', '2026-05-01', '2026-05-31', 'open', NULL, NULL),
(6, '2026-06', '2026-06-01', '2026-06-30', 'open', NULL, NULL),
(7, '2026-07', '2026-07-01', '2026-07-31', 'open', NULL, NULL),
(8, '2026-08', '2026-08-01', '2026-08-31', 'open', NULL, NULL),
(9, '2026-09', '2026-09-01', '2026-09-30', 'open', NULL, NULL),
(10, '2026-10', '2026-10-01', '2026-10-31', 'open', NULL, NULL),
(11, '2026-11', '2026-11-01', '2026-11-30', 'open', NULL, NULL),
(12, '2026-12', '2026-12-01', '2026-12-31', 'open', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `gl_journal_entries`
--

CREATE TABLE `gl_journal_entries` (
  `id` int(10) UNSIGNED NOT NULL,
  `entry_no` varchar(32) NOT NULL,
  `entry_date` date NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `memo` varchar(255) NOT NULL,
  `reference` varchar(64) DEFAULT NULL,
  `source_type` varchar(40) DEFAULT NULL,
  `source_id` int(10) UNSIGNED DEFAULT NULL,
  `source_code` varchar(64) DEFAULT NULL,
  `status` enum('draft','posted','reversed') NOT NULL DEFAULT 'posted',
  `reversed_by` int(10) UNSIGNED DEFAULT NULL,
  `posted_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gl_journal_entries`
--

INSERT INTO `gl_journal_entries` (`id`, `entry_no`, `entry_date`, `currency`, `memo`, `reference`, `source_type`, `source_id`, `source_code`, `status`, `reversed_by`, `posted_by`, `deleted_at`, `created_at`) VALUES
(1, 'JRN-2026-0001', '2026-09-14', 'RWF', 'Invoice INV-2026-0001 to Akagera Coffee Exporters Ltd', 'TRP-2026-0001', 'invoice', 1, 'INV-2026-0001', 'posted', NULL, 6, NULL, '2026-09-14 11:00:00'),
(2, 'JRN-2026-0002', '2026-09-25', 'RWF', 'Payment PAY-2026-0001 from Akagera Coffee Exporters Ltd', 'INV-2026-0001', 'payment', 1, 'PAY-2026-0001', 'posted', NULL, 6, NULL, '2026-09-25 11:35:00');

-- --------------------------------------------------------

--
-- Table structure for table `gl_journal_lines`
--

CREATE TABLE `gl_journal_lines` (
  `id` int(10) UNSIGNED NOT NULL,
  `entry_id` int(10) UNSIGNED NOT NULL,
  `line_no` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `account_id` int(10) UNSIGNED NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `debit` decimal(16,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(16,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gl_journal_lines`
--

INSERT INTO `gl_journal_lines` (`id`, `entry_id`, `line_no`, `account_id`, `description`, `debit`, `credit`) VALUES
(1, 1, 1, 5, 'Akagera Coffee Exporters Ltd', 6372000.00, 0.00),
(2, 1, 2, 19, 'Freight invoiced to Akagera Coffee Exporters Ltd', 0.00, 5400000.00),
(3, 1, 3, 12, 'VAT on INV-2026-0001', 0.00, 972000.00),
(4, 2, 1, 3, 'Received against INV-2026-0001', 6372000.00, 0.00),
(5, 2, 2, 5, 'Akagera Coffee Exporters Ltd', 0.00, 6372000.00);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `warehouse_id` int(10) UNSIGNED NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `warehouse_id`, `sku`, `item_name`, `category`, `unit_of_measure`, `quantity`, `minimum_level`, `reorder_quantity`, `unit_cost`, `batch_number`, `expiry_date`, `storage_temperature`, `status`, `deleted_at`, `created_at`) VALUES
(1, 1, 'SKU-2026-0001', 'Engine oil 15W-40 (20 L drum)', 'Spare parts', 'Unit', 16.00, 6.00, 12.00, 95000.00, 'SH-2609-15W40', '2029-06-30', NULL, 'in_stock', NULL, '2026-09-02 09:00:00'),
(2, 1, 'SKU-2026-0002', 'Tarpaulin cover 8 m x 10 m', 'Equipment', 'Unit', 3.00, 5.00, 10.00, 85000.00, NULL, NULL, NULL, 'reorder', NULL, '2026-09-02 09:05:00');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(10) UNSIGNED NOT NULL,
  `invoice_number` varchar(32) NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `trip_id` int(10) UNSIGNED DEFAULT NULL,
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
  `issued_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `customer_id`, `trip_id`, `issue_date`, `due_date`, `subtotal`, `tax_rate`, `tax_amount`, `total_amount`, `currency`, `exchange_rate`, `base_amount`, `amount_paid`, `status`, `issued_by`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'INV-2026-0001', 1, 1, '2026-09-14', '2026-10-14', 5400000.00, 18.00, 972000.00, 6372000.00, 'RWF', 1.00000000, 6372000.00, 6372000.00, 'paid', 6, 'Freight Kigali - Dar es Salaam, 18,000 kg green coffee.', NULL, '2026-09-14 11:00:00'),
(2, 'INV-2026-0002', 2, 2, '2026-09-29', '2026-10-29', 2250.00, 18.00, 405.00, 2655.00, 'USD', 1450.00000000, 3849750.00, 1000.00, 'partially_paid', 6, 'Freight Kigali - Goma, 25,000 kg cement. Balance due on delivery.', NULL, '2026-09-29 09:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_lines`
--

CREATE TABLE `invoice_lines` (
  `id` int(10) UNSIGNED NOT NULL,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `shipment_id` int(10) UNSIGNED DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoice_lines`
--

INSERT INTO `invoice_lines` (`id`, `invoice_id`, `shipment_id`, `description`, `quantity`, `unit_price`, `created_at`) VALUES
(1, 1, 1, 'Freight Kigali - Dar es Salaam, green coffee (per kg)', 18000.00, 300.00, '2026-09-14 11:00:00'),
(2, 2, 2, 'Freight Kigali - Goma, cement (per kg)', 25000.00, 0.09, '2026-09-29 09:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(190) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `succeeded` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `succeeded`, `attempted_at`) VALUES
(48, 'admin@itec.rw', '41.186.132.42', 1, '2026-09-23 06:51:18'),
(49, 'admin@itec.rw', '197.242.180.20', 0, '2026-09-23 07:01:19'),
(50, 'admin@itec.rw', '41.186.132.42', 1, '2026-09-23 07:47:24'),
(51, 'admin@itec.rw', '197.157.165.88', 1, '2026-09-23 08:09:50'),
(52, 'admin@itec.rw', '41.186.134.81', 1, '2026-09-23 09:01:15'),
(53, 'admin@itec.rw', '197.157.155.98', 1, '2026-09-23 09:13:05'),
(54, 'admin@itec.rw', '196.12.149.54', 1, '2026-09-23 10:34:27'),
(55, 'admin@itec.rw', '197.157.145.21', 1, '2026-09-23 11:30:53'),
(56, 'admin@itec.rw', '196.12.149.54', 1, '2026-09-23 14:01:25'),
(57, 'admin@itec.rw', '129.222.149.109', 1, '2026-09-23 15:24:31'),
(58, 'admin@itec.rw', '197.157.185.192', 1, '2026-09-23 18:37:44'),
(59, 'admin@itec.rw', '197.157.185.192', 1, '2026-09-23 20:04:52'),
(60, 'admin@itec.rw', '41.78.121.116', 1, '2026-09-24 10:38:46'),
(61, 'admin@itec.rw', '41.186.139.38', 1, '2026-09-24 12:05:37'),
(62, 'admin@itec.rw', '197.157.186.81', 1, '2026-09-24 17:41:54'),
(63, 'admin@itec.rw', '197.157.185.85', 0, '2026-09-26 16:34:40'),
(64, 'admin@itec.rw', '197.157.185.85', 0, '2026-09-26 16:34:50'),
(65, 'admin@itec.rw', '197.157.185.85', 0, '2026-09-26 16:37:21'),
(66, 'admin@itec.rw', '197.157.185.85', 0, '2026-09-26 16:41:00'),
(67, 'admin@itec.rw', '197.157.185.85', 0, '2026-09-26 16:41:47'),
(68, 'nzizaoscar25@gmail.com', '197.157.185.85', 0, '2026-09-26 16:43:43'),
(69, 'nzizaoscar25@gmail.com', '197.157.185.85', 0, '2026-09-26 16:44:23'),
(70, 'nzizaoscar25@gmail.com', '197.157.185.85', 0, '2026-09-26 16:45:26'),
(71, 'nzizaoscar25@gmail.com', '197.157.185.85', 1, '2026-09-26 16:46:18'),
(72, 'nzizaoscar25@gmail.com', '197.157.185.85', 1, '2026-09-26 17:06:54'),
(73, 'admin@itec.rw', '41.186.138.185', 0, '2026-09-27 07:32:34'),
(74, 'admin@itec.rw', '41.186.138.185', 0, '2026-09-27 07:32:56'),
(75, 'admin@itec.rw', '41.186.138.185', 0, '2026-09-27 07:33:36'),
(76, 'nzizaoscar25@gmail.com', '197.157.185.230', 1, '2026-09-28 07:25:16'),
(77, 'admin@itec.rw', '41.186.139.132', 0, '2026-09-29 07:00:31'),
(78, 'admin@itec.rw', '41.186.139.132', 0, '2026-09-29 07:00:40'),
(79, 'admin@itec.rw', '41.186.139.132', 0, '2026-09-29 07:00:51'),
(80, 'admin@itec.rw', '41.186.139.132', 0, '2026-09-29 07:02:14'),
(81, 'nzizaoscar25@gmail.com', '41.186.139.132', 0, '2026-09-29 07:02:42'),
(82, 'aline@itec.rw', '41.186.139.132', 1, '2026-09-29 07:03:30'),
(83, 'iradukundaelie71@gmail.com', '41.186.139.132', 1, '2026-09-29 07:05:51'),
(84, 'nzizaoscar25@gmail.com', '197.157.187.196', 1, '2026-09-29 08:53:48'),
(85, 'nzizaoscar25@gmail.com', '197.157.184.52', 1, '2026-09-29 19:23:12'),
(86, 'nzizaoscar25@gmail.com', '102.22.170.170', 1, '2026-09-29 19:28:56'),
(87, 'iradukundaelie71@gmail.com', '41.186.133.80', 1, '2026-09-30 16:28:57'),
(88, 'admin@itec.rw', '169.239.97.14', 0, '2026-09-30 16:32:22'),
(89, 'admin@itec.rw', '169.239.97.14', 0, '2026-09-30 16:34:00'),
(90, 'admin@itec.rw', '169.239.97.14', 0, '2026-09-30 16:34:21');

-- --------------------------------------------------------

--
-- Table structure for table `lookup_values`
--

CREATE TABLE `lookup_values` (
  `id` int(10) UNSIGNED NOT NULL,
  `list_key` varchar(40) NOT NULL,
  `value` varchar(120) NOT NULL,
  `label` varchar(120) NOT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 100,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lookup_values`
--

INSERT INTO `lookup_values` (`id`, `list_key`, `value`, `label`, `sort_order`, `is_active`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'vehicle_type', 'Delivery truck', 'Delivery truck', 10, 1, NULL, NULL, '2026-09-21 10:26:59'),
(2, 'vehicle_type', 'Box truck', 'Box truck', 20, 1, NULL, NULL, '2026-09-21 10:26:59'),
(3, 'vehicle_type', 'Refrigerated truck', 'Refrigerated truck', 30, 1, NULL, NULL, '2026-09-21 10:26:59'),
(4, 'vehicle_type', 'Pickup', 'Pickup', 40, 1, NULL, NULL, '2026-09-21 10:26:59'),
(5, 'vehicle_type', 'Van', 'Van', 50, 1, NULL, NULL, '2026-09-21 10:26:59'),
(6, 'vehicle_type', 'Tanker', 'Tanker', 60, 1, NULL, NULL, '2026-09-21 10:26:59'),
(7, 'vehicle_type', 'Trailer', 'Trailer', 70, 1, NULL, NULL, '2026-09-21 10:26:59'),
(8, 'vehicle_type', 'Motorcycle', 'Motorcycle', 80, 1, NULL, NULL, '2026-09-21 10:26:59'),
(9, 'licence_class', 'A', 'A', 10, 1, NULL, NULL, '2026-09-21 10:26:59'),
(10, 'licence_class', 'B', 'B', 20, 1, NULL, NULL, '2026-09-21 10:26:59'),
(11, 'licence_class', 'C', 'C', 30, 1, NULL, NULL, '2026-09-21 10:26:59'),
(12, 'licence_class', 'D', 'D', 40, 1, NULL, NULL, '2026-09-21 10:26:59'),
(13, 'licence_class', 'E', 'E', 50, 1, NULL, NULL, '2026-09-21 10:26:59'),
(14, 'licence_class', 'B/C', 'B/C', 60, 1, NULL, NULL, '2026-09-21 10:26:59'),
(15, 'licence_class', 'C/D', 'C/D', 70, 1, NULL, NULL, '2026-09-21 10:26:59'),
(16, 'expense_category', 'Fuel', 'Fuel', 10, 1, NULL, NULL, '2026-09-21 10:26:59'),
(17, 'expense_category', 'Toll', 'Toll', 20, 1, NULL, NULL, '2026-09-21 10:26:59'),
(18, 'expense_category', 'Repair', 'Repair', 30, 1, NULL, NULL, '2026-09-21 10:26:59'),
(19, 'expense_category', 'Allowance', 'Allowance', 40, 1, NULL, NULL, '2026-09-21 10:26:59'),
(20, 'expense_category', 'Parking', 'Parking', 50, 1, NULL, NULL, '2026-09-21 10:26:59'),
(21, 'expense_category', 'Insurance', 'Insurance', 60, 1, NULL, NULL, '2026-09-21 10:26:59'),
(22, 'expense_category', 'Loading', 'Loading', 70, 1, NULL, NULL, '2026-09-21 10:26:59'),
(23, 'expense_category', 'Permit', 'Permit', 80, 1, NULL, NULL, '2026-09-21 10:26:59'),
(24, 'item_category', 'Food', 'Food', 10, 1, NULL, NULL, '2026-09-21 10:26:59'),
(25, 'item_category', 'Packaging', 'Packaging', 20, 1, NULL, NULL, '2026-09-21 10:26:59'),
(26, 'item_category', 'Spare parts', 'Spare parts', 30, 1, NULL, NULL, '2026-09-21 10:26:59'),
(27, 'item_category', 'Consumables', 'Consumables', 40, 1, NULL, NULL, '2026-09-21 10:26:59'),
(28, 'item_category', 'Cold chain', 'Cold chain', 50, 1, NULL, NULL, '2026-09-21 10:26:59'),
(29, 'item_category', 'Equipment', 'Equipment', 60, 1, NULL, NULL, '2026-09-21 10:26:59'),
(30, 'item_category', 'Stationery', 'Stationery', 70, 1, NULL, NULL, '2026-09-21 10:26:59'),
(31, 'unit_of_measure', 'Unit', 'Unit', 10, 1, NULL, NULL, '2026-09-21 10:26:59'),
(32, 'unit_of_measure', 'Kg', 'Kg', 20, 1, NULL, NULL, '2026-09-21 10:26:59'),
(33, 'unit_of_measure', 'Litre', 'Litre', 30, 1, NULL, NULL, '2026-09-21 10:26:59'),
(34, 'unit_of_measure', 'Box', 'Box', 40, 1, NULL, NULL, '2026-09-21 10:26:59'),
(35, 'unit_of_measure', 'Sack', 'Sack', 50, 1, NULL, NULL, '2026-09-21 10:26:59'),
(36, 'unit_of_measure', 'Crate', 'Crate', 60, 1, NULL, NULL, '2026-09-21 10:26:59'),
(37, 'unit_of_measure', 'Pallet', 'Pallet', 70, 1, NULL, NULL, '2026-09-21 10:26:59'),
(38, 'unit_of_measure', 'Carton', 'Carton', 80, 1, NULL, NULL, '2026-09-21 10:26:59'),
(39, 'supplier_category', 'Spare parts', 'Spare parts', 10, 1, NULL, NULL, '2026-09-21 10:26:59'),
(40, 'supplier_category', 'Fuel', 'Fuel', 20, 1, NULL, NULL, '2026-09-21 10:26:59'),
(41, 'supplier_category', 'Packaging', 'Packaging', 30, 1, NULL, NULL, '2026-09-21 10:26:59'),
(42, 'supplier_category', 'Cold chain', 'Cold chain', 40, 1, NULL, NULL, '2026-09-21 10:26:59'),
(43, 'supplier_category', 'Equipment', 'Equipment', 50, 1, NULL, NULL, '2026-09-21 10:26:59'),
(44, 'supplier_category', 'Services', 'Services', 60, 1, NULL, NULL, '2026-09-21 10:26:59'),
(45, 'supplier_category', 'Consumables', 'Consumables', 70, 1, NULL, NULL, '2026-09-21 10:26:59'),
(46, 'procurement_category', 'Spare parts', 'Spare parts', 10, 1, NULL, NULL, '2026-09-21 10:26:59'),
(47, 'procurement_category', 'Fuel', 'Fuel', 20, 1, NULL, NULL, '2026-09-21 10:26:59'),
(48, 'procurement_category', 'Packaging', 'Packaging', 30, 1, NULL, NULL, '2026-09-21 10:26:59'),
(49, 'procurement_category', 'Cold chain', 'Cold chain', 40, 1, NULL, NULL, '2026-09-21 10:26:59'),
(50, 'procurement_category', 'Equipment', 'Equipment', 50, 1, NULL, NULL, '2026-09-21 10:26:59'),
(51, 'procurement_category', 'Services', 'Services', 60, 1, NULL, NULL, '2026-09-21 10:26:59'),
(52, 'procurement_category', 'Food', 'Food', 70, 1, NULL, NULL, '2026-09-21 10:26:59'),
(53, 'department', 'Operations', 'Operations', 10, 1, NULL, NULL, '2026-09-21 10:26:59'),
(54, 'department', 'Fleet', 'Fleet', 20, 1, NULL, NULL, '2026-09-21 10:26:59'),
(55, 'department', 'Warehouse', 'Warehouse', 30, 1, NULL, NULL, '2026-09-21 10:26:59'),
(56, 'department', 'Finance', 'Finance', 40, 1, NULL, NULL, '2026-09-21 10:26:59'),
(57, 'department', 'Management', 'Management', 50, 1, NULL, NULL, '2026-09-21 10:26:59'),
(58, 'department', 'Administration', 'Administration', 60, 1, NULL, NULL, '2026-09-21 10:26:59'),
(59, 'report_period', 'Daily', 'Daily', 10, 1, NULL, NULL, '2026-09-21 10:26:59'),
(60, 'report_period', 'Weekly', 'Weekly', 20, 1, NULL, NULL, '2026-09-21 10:26:59'),
(61, 'report_period', 'Monthly', 'Monthly', 30, 1, NULL, NULL, '2026-09-21 10:26:59'),
(62, 'report_period', 'Quarterly', 'Quarterly', 40, 1, NULL, NULL, '2026-09-21 10:26:59'),
(63, 'report_period', 'Yearly', 'Yearly', 50, 1, NULL, NULL, '2026-09-21 10:26:59'),
(253, 'vehicle_type', 'Cement bulker', 'Cement bulker', 90, 1, 'Imodoka itwara sima mu buryo bwa bulk', NULL, '2026-09-21 10:42:04'),
(695, 'border_charge', 'Customs duty', 'Customs duty', 10, 1, NULL, NULL, '2026-09-22 13:44:36'),
(696, 'border_charge', 'Import VAT', 'Import VAT', 20, 1, NULL, NULL, '2026-09-22 13:44:36'),
(697, 'border_charge', 'Withholding tax', 'Withholding tax', 30, 1, NULL, NULL, '2026-09-22 13:44:36'),
(698, 'border_charge', 'Clearing agent fee', 'Clearing agent fee', 40, 1, NULL, NULL, '2026-09-22 13:44:36'),
(699, 'border_charge', 'Transit bond', 'Transit bond', 50, 1, NULL, NULL, '2026-09-22 13:44:36'),
(700, 'border_charge', 'Weighbridge', 'Weighbridge', 60, 1, NULL, NULL, '2026-09-22 13:44:36'),
(701, 'border_charge', 'Escort fee', 'Escort fee', 70, 1, NULL, NULL, '2026-09-22 13:44:36'),
(702, 'border_charge', 'Parking and storage', 'Parking and storage', 80, 1, NULL, NULL, '2026-09-22 13:44:36'),
(703, 'border_charge', 'Road toll', 'Road toll', 90, 1, NULL, NULL, '2026-09-22 13:44:36'),
(704, 'border_charge', 'Other border charge', 'Other border charge', 100, 1, NULL, NULL, '2026-09-22 13:44:36'),
(705, 'border_document', 'Customs declaration', 'Customs declaration', 10, 1, NULL, NULL, '2026-09-22 13:44:36'),
(706, 'border_document', 'T1 transit bond', 'T1 transit bond', 20, 1, NULL, NULL, '2026-09-22 13:44:36'),
(707, 'border_document', 'Commercial invoice', 'Commercial invoice', 30, 1, NULL, NULL, '2026-09-22 13:44:36'),
(708, 'border_document', 'Packing list', 'Packing list', 40, 1, NULL, NULL, '2026-09-22 13:44:36'),
(709, 'border_document', 'Certificate of origin', 'Certificate of origin', 50, 1, NULL, NULL, '2026-09-22 13:44:36'),
(710, 'border_document', 'Bill of lading', 'Bill of lading', 60, 1, NULL, NULL, '2026-09-22 13:44:36'),
(711, 'border_document', 'Weighbridge ticket', 'Weighbridge ticket', 70, 1, NULL, NULL, '2026-09-22 13:44:36'),
(712, 'border_document', 'COMESA yellow card', 'COMESA yellow card', 80, 1, NULL, NULL, '2026-09-22 13:44:36'),
(713, 'border_document', 'Road transit permit', 'Road transit permit', 90, 1, NULL, NULL, '2026-09-22 13:44:36'),
(714, 'border_document', 'Phytosanitary certificate', 'Phytosanitary certificate', 100, 1, NULL, NULL, '2026-09-22 13:44:36'),
(715, 'border_document', 'Driver passport', 'Driver passport', 110, 1, NULL, NULL, '2026-09-22 13:44:36');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_orders`
--

CREATE TABLE `maintenance_orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `work_order_code` varchar(32) NOT NULL,
  `vehicle_id` int(10) UNSIGNED NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `maintenance_type` enum('preventive','corrective','inspection','tyre','bodywork','emergency') NOT NULL DEFAULT 'preventive',
  `provider_name` varchar(150) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `estimated_cost` decimal(12,2) DEFAULT NULL,
  `actual_cost` decimal(12,2) DEFAULT NULL,
  `odometer_reading` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('open','scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
  `started_at` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance_orders`
--

INSERT INTO `maintenance_orders` (`id`, `work_order_code`, `vehicle_id`, `service_name`, `maintenance_type`, `provider_name`, `priority`, `estimated_cost`, `actual_cost`, `odometer_reading`, `status`, `started_at`, `due_date`, `completed_at`, `approved_by`, `approved_at`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'MNT-2026-0001', 1, 'Full service after Dar es Salaam run (oil, filters, brakes)', 'preventive', 'Kigali Truck Parts & Service Ltd', 'normal', 1300000.00, 1245000.00, 184250, 'completed', '2026-09-15 08:00:00', '2026-09-16', '2026-09-16 15:30:00', 4, '2026-09-15 07:45:00', 'Paid by cheque CHQ-2026-0001.', NULL, '2026-09-14 16:00:00'),
(2, 'MNT-2026-0002', 2, 'Replace two front tyres', 'tyre', 'Kigali Truck Parts & Service Ltd', 'high', 1100000.00, NULL, NULL, 'scheduled', NULL, '2026-10-03', NULL, 4, '2026-09-28 15:00:00', 'Tread worn close to limit. Book in when truck returns from Goma.', NULL, '2026-09-28 14:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_parts`
--

CREATE TABLE `maintenance_parts` (
  `id` int(10) UNSIGNED NOT NULL,
  `maintenance_id` int(10) UNSIGNED NOT NULL,
  `line_type` enum('part','labour','service','consumable') NOT NULL DEFAULT 'part',
  `part_name` varchar(180) NOT NULL,
  `part_number` varchar(80) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) GENERATED ALWAYS AS (`quantity` * `unit_cost`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance_parts`
--

INSERT INTO `maintenance_parts` (`id`, `maintenance_id`, `line_type`, `part_name`, `part_number`, `quantity`, `unit_cost`, `created_at`) VALUES
(1, 1, 'service', 'Full service package (labour, oil, filters, brake adjustment)', 'KTP-SVC-FULL', 1.00, 1245000.00, '2026-09-16 15:30:00'),
(2, 2, 'part', 'Tyre 315/80 R22.5 steer axle', 'MIC-XMZ-31580', 2.00, 550000.00, '2026-09-28 14:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(190) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `applied_at`) VALUES
(1, '20260918_000001_extend_logistics_schema.sql', '2026-09-20 22:03:39'),
(2, '20260918_000002_add_notifications.sql', '2026-09-20 22:03:39'),
(3, '20260919_000003_link_drivers_to_users.sql', '2026-09-20 22:03:39'),
(4, '20260919_000004_add_user_privilege.sql', '2026-09-20 22:03:39'),
(5, '20260920_000005_core_depth.sql', '2026-09-20 22:03:40'),
(6, '20260920_000006_billing_access_security.sql', '2026-09-20 22:03:40'),
(7, '20260920_000007_role_permission_matrix.sql', '2026-09-20 22:03:40'),
(8, '20260920_000008_driver_can_close_delivery.sql', '2026-09-20 22:03:40'),
(9, '20260920_000009_accounting_books.sql', '2026-09-20 22:03:40'),
(10, '20260921_000010_email_delivery.sql', '2026-09-21 09:55:24'),
(11, '20260921_000011_lookup_lists.sql', '2026-09-21 10:26:59'),
(12, '20260921_000012_warehouses_permission.sql', '2026-09-21 10:30:16'),
(13, '20260921_000013_repair_orphan_assigned_requests.sql', '2026-09-21 11:17:36'),
(14, '20260921_000014_stock_opening_movements.sql', '2026-09-21 20:20:58'),
(15, '20260921_000015_payment_methods.sql', '2026-09-21 21:24:36'),
(16, '20260921_000016_payment_method_details.sql', '2026-09-21 21:34:52'),
(17, '20260921_000017_vendor_branding.sql', '2026-09-21 21:59:32'),
(18, '20260922_000018_cheques_and_budget.sql', '2026-09-22 07:30:15'),
(19, '20260922_000018_remove_vendor_setting.sql', '2026-09-22 07:30:15'),
(20, '20260922_000019_currencies.sql', '2026-09-22 11:48:55'),
(21, '20260922_000020_border_clearance.sql', '2026-09-22 13:44:37'),
(22, '20260922_000021_rate_by_weight.sql', '2026-09-22 14:12:19'),
(23, '20260922_000022_warehouse_routing.sql', '2026-09-22 15:12:07'),
(24, '20260922_000023_request_quotes.sql', '2026-09-22 15:58:06'),
(25, '20260922_000024_currency_everywhere.sql', '2026-09-22 17:14:25'),
(26, '20260922_000025_request_contact.sql', '2026-09-22 18:39:54'),
(27, '20260922_000026_cargo_custody.sql', '2026-09-22 21:04:16'),
(28, '20260922_000027_shipment_from_stock.sql', '2026-09-22 21:13:43'),
(29, '20260922_000028_books_per_currency.sql', '2026-09-22 21:49:21'),
(30, '20260923_000029_profile_photo.sql', '2026-09-22 22:26:41');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `notification_key` varchar(80) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `role_key` varchar(50) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `link_route` varchar(80) DEFAULT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` varchar(64) DEFAULT NULL,
  `severity` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `notification_key`, `user_id`, `role_key`, `title`, `message`, `link_route`, `entity_type`, `entity_id`, `severity`, `is_read`, `read_at`, `created_at`) VALUES
(54, 'payment-received-PAY-2026-0001', NULL, 'finance', 'Payment received', 'PAY-2026-0001: RWF 6,372,000 received from Akagera Coffee Exporters Ltd against INV-2026-0001.', 'payments', 'payments', '1', 'success', 1, '2026-09-25 12:00:00', '2026-09-25 11:35:00'),
(55, 'crossing-lodged-BDR-2026-0002', NULL, 'logistics_manager', 'Crossing lodged at La Corniche', 'BDR-2026-0002 (RAE 219 C, Virunga cement) is lodged at La Corniche / Goma and waiting for release.', 'crossings', 'border_crossings', '2', 'info', 0, NULL, '2026-09-29 16:10:00');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 1, '27b17d7f600b1d5da2f3725e5bd21305002d77c82c525605d723a80295c02bd1', '2026-09-26 19:42:38', '2026-09-26 18:43:31', '2026-09-26 16:42:38');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `payment_code` varchar(32) NOT NULL,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `exchange_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_amount` decimal(14,2) DEFAULT NULL,
  `method` enum('cash','bank_transfer','mobile_money','cheque','card') NOT NULL DEFAULT 'bank_transfer',
  `reference` varchar(80) DEFAULT NULL,
  `paid_at` datetime NOT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `payment_code`, `invoice_id`, `amount`, `currency`, `exchange_rate`, `base_amount`, `method`, `reference`, `paid_at`, `recorded_by`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'PAY-2026-0001', 1, 6372000.00, 'RWF', 1.00000000, 6372000.00, 'bank_transfer', 'BK-TRF-20260925-44810', '2026-09-25 11:30:00', 6, 'Full settlement of INV-2026-0001.', NULL, '2026-09-25 11:35:00'),
(2, 'PAY-2026-0002', 2, 1000.00, 'USD', 1450.00000000, 1450000.00, 'bank_transfer', 'EQB-TT-20260930-5521', '2026-09-30 10:15:00', 6, 'Advance payment. Balance of USD 1,655 due on delivery.', NULL, '2026-09-30 10:20:00');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(10) UNSIGNED NOT NULL,
  `method_key` varchar(40) NOT NULL,
  `method_name` varchar(80) NOT NULL,
  `gl_account_id` int(10) UNSIGNED DEFAULT NULL,
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
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 100,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `method_key`, `method_name`, `gl_account_id`, `provider_name`, `account_name`, `account_number`, `branch_name`, `swift_code`, `phone_number`, `payment_details`, `instructions`, `direction`, `show_on_invoice`, `is_active`, `sort_order`, `deleted_at`, `created_at`) VALUES
(1, 'bank_transfer', 'Bank transfer', 3, 'Your bank', NULL, NULL, NULL, NULL, NULL, NULL, 'Account name and number go here', 'both', 1, 1, 10, NULL, '2026-09-21 21:24:36'),
(2, 'mobile_money', 'Mobile money', 4, 'Your mobile network', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'both', 1, 1, 20, NULL, '2026-09-21 21:24:36'),
(3, 'cash', 'Cash', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'both', 1, 1, 30, NULL, '2026-09-21 21:24:36'),
(4, 'cheque', 'Cheque', 3, 'Your bank', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'both', 1, 1, 40, NULL, '2026-09-21 21:24:36'),
(5, 'card', 'Card', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'both', 0, 1, 50, NULL, '2026-09-21 21:24:36'),
(6, 'fuel_card', 'Fuel card', 11, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'out', 0, 1, 60, NULL, '2026-09-21 21:24:36');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `permission_key` varchar(50) NOT NULL,
  `permission_label` varchar(100) NOT NULL,
  `permission_group` varchar(60) NOT NULL DEFAULT 'General',
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `permission_key`, `permission_label`, `permission_group`, `sort_order`) VALUES
(1, 'dashboard', 'Dashboard', 'Overview', 10),
(2, 'vehicles', 'Vehicles', 'Fleet', 20),
(3, 'drivers', 'Drivers', 'Fleet', 30),
(4, 'maintenance', 'Maintenance', 'Fleet', 40),
(5, 'vehicle_documents', 'Vehicle documents', 'Fleet', 50),
(6, 'requests', 'Transport requests', 'Transport', 60),
(7, 'trips', 'Trips', 'Transport', 70),
(8, 'shipments', 'Shipments', 'Transport', 80),
(9, 'deliveries', 'Deliveries', 'Transport', 90),
(10, 'customers', 'Customers', 'Commercial', 100),
(11, 'rates', 'Rate cards', 'Commercial', 110),
(12, 'invoices', 'Invoices', 'Commercial', 120),
(13, 'fuel', 'Fuel management', 'Finance', 130),
(14, 'expenses', 'Logistics expenses', 'Finance', 140),
(15, 'warehouse', 'Warehouse and inventory', 'Warehouse', 150),
(16, 'movements', 'Stock movements', 'Warehouse', 160),
(17, 'procurement', 'Procurement', 'Warehouse', 170),
(18, 'suppliers', 'Suppliers', 'Warehouse', 180),
(19, 'reports', 'Reports', 'Insights', 190),
(20, 'users', 'Users and permissions', 'Administration', 200),
(21, 'settings', 'Company settings', 'Administration', 210),
(22, 'audit', 'Audit trail', 'Administration', 220),
(23, 'accounts', 'Chart of accounts', 'Accounting', 230),
(24, 'journal', 'Journal entries', 'Accounting', 240),
(25, 'books', 'Accounting books', 'Accounting', 250),
(26, 'payments', 'Payments received', 'Commercial', 125),
(28, 'email', 'Email outbox', 'Administration', 225),
(30, 'lookups', 'Reference lists', 'Administration', 215),
(32, 'warehouses', 'Warehouses', 'Warehouse', 405),
(36, 'payment_methods', 'Payment methods', 'Accounting', 340),
(40, 'cheques', 'Cheques', 'Accounting', 320),
(41, 'cheque_books', 'Cheque books', 'Accounting', 330),
(42, 'budgets', 'Budget', 'Accounting', 350),
(44, 'currencies', 'Currencies', 'Accounting', 360),
(45, 'currency_rates', 'Exchange rates', 'Accounting', 370),
(47, 'crossings', 'Border crossings', 'Transport', 250),
(48, 'border_posts', 'Border posts', 'Transport', 260);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_code` varchar(32) NOT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `warehouse_id` int(10) UNSIGNED DEFAULT NULL,
  `requested_by` int(10) UNSIGNED DEFAULT NULL,
  `description` text NOT NULL,
  `category` varchar(80) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `exchange_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_amount` decimal(14,2) DEFAULT NULL,
  `expected_date` date DEFAULT NULL,
  `status` enum('draft','quotation','approved','received','rejected') NOT NULL DEFAULT 'draft',
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_requests`
--

INSERT INTO `purchase_requests` (`id`, `request_code`, `supplier_id`, `warehouse_id`, `requested_by`, `description`, `category`, `amount`, `currency`, `exchange_rate`, `base_amount`, `expected_date`, `status`, `approved_by`, `approved_at`, `rejection_reason`, `received_at`, `notes`, `created_at`, `deleted_at`) VALUES
(1, 'PR-2026-0001', 1, 1, 5, 'Engine oil for fleet servicing', 'Spare parts', 1520000.00, 'RWF', 1.00000000, 1520000.00, '2026-09-05', 'received', 6, '2026-09-02 10:00:00', NULL, '2026-09-05 14:00:00', '16 drums received in full.', '2026-09-02 09:15:00', NULL),
(2, 'PR-2026-0002', 1, 1, 5, 'Tarpaulin covers to restock below minimum level', 'Equipment', 850000.00, 'RWF', 1.00000000, 850000.00, '2026-10-06', 'approved', 6, '2026-09-29 11:00:00', NULL, NULL, 'Needed for open trailers on the coffee and cement runs.', '2026-09-29 09:40:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_request_lines`
--

CREATE TABLE `purchase_request_lines` (
  `id` int(10) UNSIGNED NOT NULL,
  `purchase_request_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED DEFAULT NULL,
  `item_name` varchar(180) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_of_measure` varchar(20) NOT NULL DEFAULT 'Unit',
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `received_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_request_lines`
--

INSERT INTO `purchase_request_lines` (`id`, `purchase_request_id`, `item_id`, `item_name`, `quantity`, `unit_of_measure`, `unit_price`, `received_quantity`, `created_at`) VALUES
(1, 1, 1, 'Engine oil 15W-40 (20 L drum)', 16.00, 'Unit', 95000.00, 16.00, '2026-09-02 09:15:00'),
(2, 2, 2, 'Tarpaulin cover 8 m x 10 m', 10.00, 'Unit', 85000.00, 0.00, '2026-09-29 09:40:00');

-- --------------------------------------------------------

--
-- Table structure for table `rate_cards`
--

CREATE TABLE `rate_cards` (
  `id` int(10) UNSIGNED NOT NULL,
  `rate_code` varchar(32) NOT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `origin_warehouse_id` int(10) UNSIGNED DEFAULT NULL,
  `destination_warehouse_id` int(10) UNSIGNED DEFAULT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rate_cards`
--

INSERT INTO `rate_cards` (`id`, `rate_code`, `customer_id`, `origin_warehouse_id`, `destination_warehouse_id`, `origin`, `destination`, `vehicle_type`, `full_load_kg`, `full_load_price`, `rate_type`, `rate_amount`, `currency`, `minimum_charge`, `effective_from`, `effective_to`, `status`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'RATE-2026-0001', 1, 1, NULL, 'Kigali', 'Dar es Salaam', 'Trailer', 30000.00, 8500000.00, 'per_kg', 300.00, 'RWF', 3000000.00, '2026-01-01', '2026-12-31', 'active', 'Contract rate for Akagera Coffee Exporters, central corridor.', NULL, '2026-09-05 09:30:00'),
(2, 'RATE-2026-0002', 2, NULL, NULL, 'Kigali', 'Goma', 'Delivery truck', 28000.00, 2400.00, 'per_kg', 0.09, 'USD', 800.00, '2026-09-01', '2027-08-31', 'active', 'Cross-border rate for Virunga Cement Distributors, billed in USD.', NULL, '2026-09-20 10:45:00');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(10) UNSIGNED NOT NULL,
  `report_key` varchar(60) DEFAULT NULL,
  `report_name` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `period_label` varchar(100) NOT NULL,
  `owner_name` varchar(100) NOT NULL,
  `last_generated_at` datetime DEFAULT NULL,
  `format_label` varchar(20) NOT NULL DEFAULT 'CSV',
  `action_label` varchar(40) NOT NULL DEFAULT 'View',
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `report_key`, `report_name`, `description`, `period_label`, `owner_name`, `last_generated_at`, `format_label`, `action_label`, `deleted_at`) VALUES
(1, 'trip_profitability', 'Trip profitability', 'Invoiced revenue, net of VAT, against trip cost (expenses plus fuel) for each trip, and the resulting margin.', 'Monthly', 'Emmanuel Safari', '2026-09-30 08:00:00', 'CSV', 'View', NULL),
(2, 'fuel_consumption', 'Fuel consumption', 'Litres and money spent per vehicle, with the number of fill-ups and the average price paid.', 'Monthly', 'Eric Murenzi', '2026-09-30 08:05:00', 'CSV', 'View', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_key` varchar(50) NOT NULL,
  `role_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_key`, `role_name`) VALUES
(1, 'super_admin', 'Super Admin'),
(2, 'logistics_manager', 'Logistics Manager'),
(3, 'fleet_manager', 'Fleet Manager'),
(4, 'warehouse_manager', 'Warehouse Manager'),
(5, 'driver', 'Driver'),
(6, 'finance', 'Finance'),
(7, 'management', 'Management');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `permission_key` varchar(50) NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT 1,
  `can_create` tinyint(1) NOT NULL DEFAULT 0,
  `can_edit` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete` tinyint(1) NOT NULL DEFAULT 0,
  `can_approve` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_key`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_approve`) VALUES
(1, 1, 'audit', 1, 1, 1, 1, 1),
(2, 1, 'customers', 1, 1, 1, 1, 1),
(3, 1, 'dashboard', 1, 1, 1, 1, 1),
(4, 1, 'deliveries', 1, 1, 1, 1, 1),
(5, 1, 'drivers', 1, 1, 1, 1, 1),
(6, 1, 'expenses', 1, 1, 1, 1, 1),
(7, 1, 'fuel', 1, 1, 1, 1, 1),
(8, 1, 'invoices', 1, 1, 1, 1, 1),
(9, 1, 'maintenance', 1, 1, 1, 1, 1),
(10, 1, 'movements', 1, 1, 1, 1, 1),
(11, 1, 'procurement', 1, 1, 1, 1, 1),
(12, 1, 'rates', 1, 1, 1, 1, 1),
(13, 1, 'reports', 1, 1, 1, 1, 1),
(14, 1, 'requests', 1, 1, 1, 1, 1),
(15, 1, 'settings', 1, 1, 1, 1, 1),
(16, 1, 'shipments', 1, 1, 1, 1, 1),
(17, 1, 'suppliers', 1, 1, 1, 1, 1),
(18, 1, 'trips', 1, 1, 1, 1, 1),
(19, 1, 'users', 1, 1, 1, 1, 1),
(20, 1, 'vehicle_documents', 1, 1, 1, 1, 1),
(21, 1, 'vehicles', 1, 1, 1, 1, 1),
(22, 1, 'warehouse', 1, 1, 1, 1, 1),
(32, 2, 'dashboard', 1, 0, 0, 0, 0),
(33, 2, 'vehicles', 1, 1, 1, 0, 0),
(34, 2, 'drivers', 1, 1, 1, 0, 0),
(35, 2, 'maintenance', 1, 1, 1, 0, 1),
(36, 2, 'vehicle_documents', 1, 1, 1, 0, 0),
(37, 2, 'requests', 1, 1, 1, 1, 1),
(38, 2, 'trips', 1, 1, 1, 1, 1),
(39, 2, 'shipments', 1, 1, 1, 1, 0),
(40, 2, 'deliveries', 1, 1, 1, 1, 0),
(41, 2, 'customers', 1, 1, 1, 0, 0),
(42, 2, 'rates', 1, 0, 0, 0, 0),
(43, 2, 'fuel', 1, 1, 1, 0, 0),
(44, 2, 'expenses', 1, 1, 1, 0, 0),
(45, 2, 'warehouse', 1, 1, 1, 0, 0),
(46, 2, 'movements', 1, 1, 0, 0, 0),
(47, 2, 'procurement', 1, 1, 1, 0, 0),
(48, 2, 'suppliers', 1, 1, 1, 0, 0),
(49, 2, 'reports', 1, 1, 1, 0, 0),
(50, 2, 'audit', 1, 0, 0, 0, 0),
(63, 3, 'dashboard', 1, 0, 0, 0, 0),
(64, 3, 'vehicles', 1, 1, 1, 1, 0),
(65, 3, 'drivers', 1, 1, 1, 1, 0),
(66, 3, 'maintenance', 1, 1, 1, 1, 1),
(67, 3, 'vehicle_documents', 1, 1, 1, 1, 0),
(68, 3, 'fuel', 1, 1, 1, 0, 0),
(69, 3, 'trips', 1, 0, 0, 0, 0),
(70, 3, 'reports', 1, 0, 0, 0, 0),
(78, 4, 'dashboard', 1, 0, 0, 0, 0),
(79, 4, 'warehouse', 1, 1, 1, 1, 0),
(80, 4, 'movements', 1, 1, 0, 0, 0),
(81, 4, 'procurement', 1, 1, 1, 1, 0),
(82, 4, 'suppliers', 1, 1, 1, 1, 0),
(83, 4, 'requests', 1, 1, 1, 0, 0),
(84, 4, 'shipments', 1, 1, 1, 0, 0),
(85, 4, 'reports', 1, 0, 0, 0, 0),
(93, 5, 'dashboard', 1, 0, 0, 0, 0),
(94, 5, 'trips', 1, 0, 1, 0, 0),
(95, 5, 'deliveries', 1, 0, 1, 0, 1),
(96, 5, 'shipments', 1, 0, 0, 0, 0),
(97, 5, 'fuel', 1, 1, 0, 0, 0),
(100, 6, 'dashboard', 1, 0, 0, 0, 0),
(101, 6, 'fuel', 1, 0, 1, 0, 0),
(102, 6, 'expenses', 1, 1, 1, 1, 1),
(103, 6, 'procurement', 1, 0, 1, 0, 1),
(104, 6, 'maintenance', 1, 0, 0, 0, 1),
(105, 6, 'customers', 1, 1, 1, 0, 0),
(106, 6, 'rates', 1, 1, 1, 1, 0),
(107, 6, 'invoices', 1, 1, 1, 1, 1),
(108, 6, 'suppliers', 1, 0, 0, 0, 0),
(109, 6, 'reports', 1, 1, 1, 0, 0),
(115, 7, 'dashboard', 1, 0, 0, 0, 0),
(116, 7, 'reports', 1, 0, 0, 0, 0),
(117, 7, 'customers', 1, 0, 0, 0, 0),
(118, 7, 'invoices', 1, 0, 0, 0, 0),
(119, 7, 'trips', 1, 0, 0, 0, 0),
(120, 7, 'audit', 1, 0, 0, 0, 0),
(123, 1, 'accounts', 1, 1, 1, 1, 1),
(124, 1, 'books', 1, 1, 1, 1, 1),
(125, 1, 'journal', 1, 1, 1, 1, 1),
(126, 6, 'accounts', 1, 1, 1, 0, 0),
(127, 6, 'journal', 1, 1, 1, 1, 1),
(128, 6, 'books', 1, 0, 0, 0, 0),
(129, 7, 'books', 1, 0, 0, 0, 0),
(130, 7, 'accounts', 1, 0, 0, 0, 0),
(132, 1, 'payments', 1, 1, 1, 1, 1),
(133, 6, 'payments', 1, 1, 1, 1, 0),
(134, 7, 'payments', 1, 0, 0, 0, 0),
(138, 1, 'email', 1, 1, 1, 1, 1),
(139, 6, 'email', 1, 0, 1, 0, 0),
(143, 1, 'lookups', 1, 1, 1, 1, 1),
(144, 6, 'lookups', 1, 1, 1, 0, 0),
(145, 3, 'lookups', 1, 1, 1, 0, 0),
(146, 2, 'lookups', 1, 1, 1, 0, 0),
(147, 4, 'lookups', 1, 1, 1, 0, 0),
(154, 1, 'warehouses', 1, 1, 1, 1, 1),
(155, 4, 'warehouses', 1, 1, 1, 1, 0),
(156, 2, 'warehouses', 1, 1, 1, 0, 0),
(157, 6, 'warehouses', 1, 0, 0, 0, 0),
(158, 7, 'warehouses', 1, 0, 0, 0, 0),
(169, 6, 'payment_methods', 1, 1, 1, 1, 1),
(170, 1, 'payment_methods', 1, 1, 1, 1, 1),
(172, 7, 'payment_methods', 1, 0, 0, 0, 0),
(182, 6, 'cheques', 1, 1, 1, 1, 1),
(183, 1, 'cheques', 1, 1, 1, 1, 1),
(184, 6, 'cheque_books', 1, 1, 1, 1, 1),
(185, 1, 'cheque_books', 1, 1, 1, 1, 1),
(186, 6, 'budgets', 1, 1, 1, 1, 1),
(187, 1, 'budgets', 1, 1, 1, 1, 1),
(189, 7, 'cheques', 1, 0, 0, 0, 0),
(190, 7, 'cheque_books', 1, 0, 0, 0, 0),
(191, 7, 'budgets', 1, 0, 0, 0, 0),
(195, 6, 'currencies', 1, 1, 1, 1, 1),
(196, 6, 'currency_rates', 0, 0, 0, 0, 1),
(197, 1, 'currencies', 1, 1, 1, 1, 1),
(198, 1, 'currency_rates', 0, 0, 0, 0, 1),
(202, 5, 'currencies', 1, 0, 0, 0, 0),
(203, 3, 'currencies', 1, 0, 0, 0, 0),
(204, 2, 'currencies', 1, 0, 0, 0, 0),
(205, 7, 'currencies', 1, 0, 0, 0, 0),
(206, 4, 'currencies', 1, 0, 0, 0, 0),
(212, 2, 'crossings', 1, 1, 1, 1, 1),
(213, 2, 'border_posts', 1, 1, 1, 1, 1),
(214, 1, 'crossings', 1, 1, 1, 1, 1),
(215, 1, 'border_posts', 1, 1, 1, 1, 1),
(219, 6, 'crossings', 1, 1, 1, 0, 0),
(220, 6, 'border_posts', 1, 0, 0, 0, 0),
(221, 3, 'crossings', 1, 0, 0, 0, 0),
(222, 3, 'border_posts', 1, 0, 0, 0, 0),
(223, 7, 'crossings', 1, 0, 0, 0, 0),
(224, 7, 'border_posts', 1, 0, 0, 0, 0),
(227, 5, 'crossings', 1, 0, 1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `shipments`
--

CREATE TABLE `shipments` (
  `id` int(10) UNSIGNED NOT NULL,
  `shipment_code` varchar(32) NOT NULL,
  `request_id` int(10) UNSIGNED DEFAULT NULL,
  `trip_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `consignee_name` varchar(150) NOT NULL,
  `consignee_phone` varchar(40) DEFAULT NULL,
  `origin` varchar(150) NOT NULL,
  `origin_warehouse_id` int(10) UNSIGNED DEFAULT NULL,
  `destination` varchar(150) NOT NULL,
  `destination_warehouse_id` int(10) UNSIGNED DEFAULT NULL,
  `stock_item_id` int(10) UNSIGNED DEFAULT NULL,
  `stock_quantity` decimal(12,2) DEFAULT NULL,
  `stock_issued_at` datetime DEFAULT NULL,
  `cargo_type` enum('general','cold_chain','fragile','hazardous','bulk','liquid','perishable') NOT NULL DEFAULT 'general',
  `cargo_description` varchar(255) NOT NULL,
  `packages_count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `weight_kg` decimal(12,2) DEFAULT NULL,
  `volume_m3` decimal(12,3) DEFAULT NULL,
  `temperature_min_c` decimal(5,2) DEFAULT NULL,
  `temperature_max_c` decimal(5,2) DEFAULT NULL,
  `is_hazardous` tinyint(1) NOT NULL DEFAULT 0,
  `declared_value` decimal(14,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `rate_card_id` int(10) UNSIGNED DEFAULT NULL,
  `quoted_amount` decimal(14,2) DEFAULT NULL,
  `quote_basis` varchar(160) DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `status` enum('draft','booked','loaded','in_transit','delivered','returned','cancelled') NOT NULL DEFAULT 'draft',
  `booked_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shipments`
--

INSERT INTO `shipments` (`id`, `shipment_code`, `request_id`, `trip_id`, `customer_id`, `consignee_name`, `consignee_phone`, `origin`, `origin_warehouse_id`, `destination`, `destination_warehouse_id`, `stock_item_id`, `stock_quantity`, `stock_issued_at`, `cargo_type`, `cargo_description`, `packages_count`, `weight_kg`, `volume_m3`, `temperature_min_c`, `temperature_max_c`, `is_hazardous`, `declared_value`, `currency`, `rate_card_id`, `quoted_amount`, `quote_basis`, `special_instructions`, `status`, `booked_at`, `deleted_at`, `created_at`) VALUES
(1, 'SHP-2026-0001', 1, 1, 1, 'Bahari Freight Agents Ltd', '+255 754 210 330', 'Kigali', 1, 'Dar es Salaam Port, Tanzania', NULL, NULL, NULL, NULL, 'general', 'Green Arabica coffee, fully washed, grade A1 - 300 jute bags x 60 kg', 300, 18000.00, 32.400, NULL, NULL, 0, 156600000.00, 'RWF', 1, 5400000.00, '18,000 kg x RWF 300/kg (RATE-2026-0001)', 'Keep dry. Cover with tarpaulin. Do not stack other cargo on top.', 'delivered', '2026-09-09 09:00:00', NULL, '2026-09-09 09:00:00'),
(2, 'SHP-2026-0002', 2, 2, 2, 'Virunga Cement Distributors - Goma depot', '+243 997 120 455', 'Gikondo, Kigali', NULL, 'Goma, DR Congo', NULL, NULL, NULL, NULL, 'general', 'Portland cement CEM II 42.5N - 500 bags x 50 kg', 500, 25000.00, 20.000, NULL, NULL, 0, 5750.00, 'USD', 2, 2250.00, '25,000 kg x USD 0.09/kg (RATE-2026-0002)', 'Keep away from rain. Count bags at loading and at drop.', 'in_transit', '2026-09-28 11:00:00', NULL, '2026-09-28 11:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(10) UNSIGNED NOT NULL,
  `movement_code` varchar(32) NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `warehouse_id` int(10) UNSIGNED NOT NULL,
  `movement_type` enum('stock_in','stock_out','transfer_in','transfer_out','adjustment','damage','return') NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `balance_after` decimal(12,2) DEFAULT NULL,
  `reference_type` varchar(40) DEFAULT NULL,
  `reference_code` varchar(64) DEFAULT NULL,
  `performed_by` int(10) UNSIGNED DEFAULT NULL,
  `moved_at` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `movement_code`, `item_id`, `warehouse_id`, `movement_type`, `quantity`, `unit_cost`, `balance_after`, `reference_type`, `reference_code`, `performed_by`, `moved_at`, `notes`, `created_at`) VALUES
(1, 'MOV-2026-0001', 1, 1, 'stock_in', 16.00, 95000.00, 16.00, 'purchase_request', 'PR-2026-0001', 5, '2026-09-05 14:00:00', 'Received from Kigali Truck Parts & Service Ltd.', '2026-09-05 14:00:00'),
(2, 'MOV-2026-0002', 2, 1, 'stock_in', 3.00, 85000.00, 3.00, 'opening', NULL, 5, '2026-09-02 09:05:00', 'Opening balance counted at stock take.', '2026-09-02 09:05:00');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(10) UNSIGNED NOT NULL,
  `supplier_code` varchar(32) DEFAULT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `category` varchar(80) DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `tin_number` varchar(40) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `payment_terms_days` int(10) UNSIGNED NOT NULL DEFAULT 30,
  `rating` tinyint(3) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_code`, `supplier_name`, `category`, `contact_name`, `phone`, `email`, `tin_number`, `address`, `payment_terms_days`, `rating`, `status`, `deleted_at`, `created_at`) VALUES
(1, 'SUP-2026-0001', 'Kigali Truck Parts & Service Ltd', 'Spare parts', 'Olivier Habineza', '+250 788 552 671', 'sales@kigalitruckparts.rw', '103998877', 'KK 15 Rd, Gikondo, Kigali', 30, 4, 'active', NULL, '2026-09-01 10:00:00'),
(2, 'SUP-2026-0002', 'Great Lakes Clearing & Forwarding Ltd', 'Services', 'Joseph Kamanzi', '+250 785 118 204', 'ops@greatlakescf.rw', '104556612', 'Rusumo Border, Kirehe', 15, 5, 'active', NULL, '2026-09-01 10:10:00');

-- --------------------------------------------------------

--
-- Table structure for table `transport_requests`
--

CREATE TABLE `transport_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference_code` varchar(32) NOT NULL,
  `requester_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `requested_by_contact` varchar(150) DEFAULT NULL,
  `trip_id` int(10) UNSIGNED DEFAULT NULL,
  `pickup_location` varchar(150) NOT NULL,
  `origin_warehouse_id` int(10) UNSIGNED DEFAULT NULL,
  `destination` varchar(150) NOT NULL,
  `destination_warehouse_id` int(10) UNSIGNED DEFAULT NULL,
  `stock_item_id` int(10) UNSIGNED DEFAULT NULL,
  `stock_quantity` decimal(12,2) DEFAULT NULL,
  `cargo_description` varchar(255) DEFAULT NULL,
  `weight_kg` decimal(12,2) DEFAULT NULL,
  `rate_card_id` int(10) UNSIGNED DEFAULT NULL,
  `quoted_amount` decimal(14,2) DEFAULT NULL,
  `quote_basis` varchar(160) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `quoted_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `accepted_by` varchar(150) DEFAULT NULL,
  `packages_count` int(10) UNSIGNED DEFAULT NULL,
  `required_date` date NOT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `status` enum('pending','quoted','approved','assigned','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transport_requests`
--

INSERT INTO `transport_requests` (`id`, `reference_code`, `requester_id`, `customer_id`, `requested_by_contact`, `trip_id`, `pickup_location`, `origin_warehouse_id`, `destination`, `destination_warehouse_id`, `stock_item_id`, `stock_quantity`, `cargo_description`, `weight_kg`, `rate_card_id`, `quoted_amount`, `quote_basis`, `currency`, `quoted_at`, `accepted_at`, `accepted_by`, `packages_count`, `required_date`, `priority`, `status`, `approved_by`, `approved_at`, `rejection_reason`, `notes`, `created_at`, `deleted_at`) VALUES
(1, 'REQ-2026-0001', 2, 1, 'Diane Uwase', 1, 'ITEC Kigali Main Warehouse, Masaka', 1, 'Dar es Salaam Port, Tanzania', NULL, NULL, NULL, '300 bags green coffee (60 kg each)', 18000.00, 1, 5400000.00, '18,000 kg x RWF 300/kg (RATE-2026-0001)', 'RWF', '2026-09-08 10:15:00', '2026-09-08 15:40:00', 'Diane Uwase', 300, '2026-09-15', 'high', 'assigned', 2, '2026-09-09 08:30:00', NULL, 'Vessel cut-off at Dar es Salaam on 15 September.', '2026-09-08 09:30:00', NULL),
(2, 'REQ-2026-0002', 157, 2, 'Patrick Nshimiyimana', 2, 'Virunga Cement yard, Gikondo, Kigali', NULL, 'Goma, DR Congo', NULL, NULL, NULL, '500 bags cement CEM II 42.5N (50 kg each)', 25000.00, 2, 2250.00, '25,000 kg x USD 0.09/kg (RATE-2026-0002)', 'USD', '2026-09-26 11:00:00', '2026-09-26 16:20:00', 'Patrick Nshimiyimana', 500, '2026-09-30', 'normal', 'assigned', 157, '2026-09-27 09:00:00', NULL, 'Customer pays USD 1,000 in advance, balance on delivery.', '2026-09-26 10:20:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `trips`
--

CREATE TABLE `trips` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference_code` varchar(32) NOT NULL,
  `request_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `trip_type` enum('delivery','collection','transfer','return','shuttle') NOT NULL DEFAULT 'delivery',
  `vehicle_id` int(10) UNSIGNED DEFAULT NULL,
  `driver_id` int(10) UNSIGNED DEFAULT NULL,
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
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trips`
--

INSERT INTO `trips` (`id`, `reference_code`, `request_id`, `customer_id`, `trip_type`, `vehicle_id`, `driver_id`, `pickup_location`, `destination`, `planned_departure_at`, `planned_arrival_at`, `departure_at`, `arrival_at`, `status`, `cargo_summary`, `dispatched_at`, `completed_at`, `created_by`, `notes`, `created_at`, `deleted_at`) VALUES
(1, 'TRP-2026-0001', 1, 1, 'delivery', 1, 1, 'ITEC Kigali Main Warehouse, Masaka', 'Dar es Salaam Port, Tanzania', '2026-09-10 06:00:00', '2026-09-13 18:00:00', '2026-09-10 06:40:00', '2026-09-13 16:20:00', 'delivered', '300 bags green coffee, 18,000 kg', '2026-09-10 06:40:00', '2026-09-13 17:05:00', 2, 'Via Rusumo. Delivered ahead of vessel cut-off.', '2026-09-09 09:00:00', NULL),
(2, 'TRP-2026-0002', 2, 2, 'delivery', 2, 2, 'Virunga Cement yard, Gikondo, Kigali', 'Goma, DR Congo', '2026-09-29 07:00:00', '2026-09-30 14:00:00', '2026-09-29 07:25:00', NULL, 'in_transit', '500 bags cement, 25,000 kg', '2026-09-29 07:25:00', NULL, 157, 'Via Rubavu depot and La Corniche border.', '2026-09-28 11:00:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `trip_stops`
--

CREATE TABLE `trip_stops` (
  `id` int(10) UNSIGNED NOT NULL,
  `trip_id` int(10) UNSIGNED NOT NULL,
  `stop_sequence` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `stop_type` enum('pickup','dropoff','waypoint','checkpoint') NOT NULL DEFAULT 'dropoff',
  `warehouse_id` int(10) UNSIGNED DEFAULT NULL,
  `location_name` varchar(180) NOT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `contact_phone` varchar(40) DEFAULT NULL,
  `planned_arrival_at` datetime DEFAULT NULL,
  `actual_arrival_at` datetime DEFAULT NULL,
  `status` enum('pending','arrived','completed','skipped','failed') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trip_stops`
--

INSERT INTO `trip_stops` (`id`, `trip_id`, `stop_sequence`, `stop_type`, `warehouse_id`, `location_name`, `contact_name`, `contact_phone`, `planned_arrival_at`, `actual_arrival_at`, `status`, `notes`, `created_at`) VALUES
(1, 1, 1, 'checkpoint', NULL, 'Rusumo One Stop Border Post', 'Joseph Kamanzi', '+250 785 118 204', '2026-09-10 12:00:00', '2026-09-10 12:50:00', 'completed', 'Export declaration lodged and cleared the same day.', '2026-09-09 09:00:00'),
(2, 2, 1, 'waypoint', 2, 'ITEC Rubavu Cross-border Depot', 'Nadine Tuyisenge', '+250 782 110 554', '2026-09-29 12:00:00', '2026-09-29 12:40:00', 'completed', 'Driver rest and document check before the border.', '2026-09-28 11:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `department` varchar(80) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','locked') NOT NULL DEFAULT 'active',
  `prvg` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `notify_by_email` tinyint(1) NOT NULL DEFAULT 1,
  `password_changed_at` datetime DEFAULT NULL,
  `failed_login_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `full_name`, `email`, `password_hash`, `phone`, `department`, `job_title`, `avatar_path`, `status`, `prvg`, `must_change_password`, `notify_by_email`, `password_changed_at`, `failed_login_count`, `locked_until`, `last_login_at`, `created_at`, `deleted_at`) VALUES
(1, 1, 'ITEC ADMIN CARGO', 'nzizaoscar25@gmail.com', '$2y$10$.uwKoQ0B73atMUxnxuORK.QEaX.3m30.j.1o1icuoIHI2u.ZS9Ggu', '+250 788 000 001', 'Administration', 'System Administrator', 'avatars/20260923-065453-2e999431ad63.jpeg', 'active', 1, 0, 1, NULL, 0, NULL, '2026-09-29 21:28:56', '2026-09-20 22:03:41', NULL),
(2, 2, 'Aline Mukamana', 'aline@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 788 202 020', 'Operations', 'Head of Operations', NULL, 'active', 2, 1, 1, NULL, 0, NULL, '2026-09-29 09:03:30', '2026-09-20 22:03:41', NULL),
(3, 5, 'Samuel Niyonzima', 'samuel@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 788 632 119', 'Fleet', 'Long-haul Driver', NULL, 'active', 2, 1, 1, NULL, 0, NULL, '2026-09-18 06:18:00', '2026-09-20 22:03:41', NULL),
(4, 3, 'Eric Murenzi', 'eric@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 783 210 084', 'Fleet', 'Fleet Supervisor', NULL, 'active', 2, 1, 1, NULL, 0, NULL, '2026-09-17 16:20:00', '2026-09-20 22:03:41', NULL),
(5, 4, 'Nadine Tuyisenge', 'nadine@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 782 110 554', 'Warehouse', 'Warehouse Supervisor', NULL, 'active', 2, 1, 1, NULL, 0, NULL, '2026-09-16 09:35:00', '2026-09-20 22:03:41', NULL),
(6, 6, 'Emmanuel Safari', 'emmanuel@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 788 705 881', 'Finance', 'Finance Officer', NULL, 'active', 2, 1, 1, NULL, 0, NULL, '2026-09-15 11:05:00', '2026-09-20 22:03:41', NULL),
(7, 7, 'Jean Pierre Habimana', 'jeanpierre@itec.rw', '$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6', '+250 788 512 030', 'Management', 'Managing Director', NULL, 'active', 2, 1, 1, NULL, 0, NULL, '2026-09-14 14:10:00', '2026-09-20 22:03:41', NULL),
(44, 5, 'Claude Bizimana', 'elieiradukunda2050@gmail.com', '$2y$10$oQYPWwlKXp/wzgRv3Rxtguk988YJ7OrvCpmKhWYiKCXschsZ76BNW', '+250 788 401 003', 'Fleet', 'Umushoferi', NULL, 'active', 2, 1, 1, '2026-09-21 19:35:52', 0, NULL, '2026-09-22 22:40:03', '2026-09-21 17:29:18', NULL),
(157, 2, 'IRADUKUNDA Elie', 'iradukundaelie71@gmail.com', '$2y$10$IFMhn6QElh4hHGQN1.HQ7Okdau/SbIGK.aOoolokw6z66UB57hgW2', '+250 788 202 020', 'Operations', 'Head of Operations', 'avatars/20260929-070631-f9b732c28973.jpeg', 'active', 2, 0, 1, '2026-09-29 09:06:07', 0, NULL, '2026-09-30 18:28:57', '2026-09-20 22:03:41', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(10) UNSIGNED NOT NULL,
  `plate_number` varchar(32) NOT NULL,
  `vehicle_type` varchar(80) NOT NULL,
  `make` varchar(80) DEFAULT NULL,
  `model` varchar(80) DEFAULT NULL,
  `manufacture_year` smallint(5) UNSIGNED DEFAULT NULL,
  `chassis_number` varchar(80) DEFAULT NULL,
  `capacity_kg` decimal(12,2) DEFAULT NULL,
  `capacity_m3` decimal(12,3) DEFAULT NULL,
  `fuel_type` enum('diesel','petrol','electric','hybrid','cng') NOT NULL DEFAULT 'diesel',
  `ownership` enum('owned','leased','rented','subcontracted') NOT NULL DEFAULT 'owned',
  `acquired_on` date DEFAULT NULL,
  `has_cooling_unit` tinyint(1) NOT NULL DEFAULT 0,
  `assigned_driver_id` int(10) UNSIGNED DEFAULT NULL,
  `mileage` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('available','on_trip','maintenance','inactive') NOT NULL DEFAULT 'available',
  `next_service_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `plate_number`, `vehicle_type`, `make`, `model`, `manufacture_year`, `chassis_number`, `capacity_kg`, `capacity_m3`, `fuel_type`, `ownership`, `acquired_on`, `has_cooling_unit`, `assigned_driver_id`, `mileage`, `status`, `next_service_date`, `notes`, `created_at`, `deleted_at`) VALUES
(1, 'RAD 482 K', 'Trailer', 'Scania', 'R450', 2021, 'YS2R4X20005123456', 30000.00, 90.000, 'diesel', 'owned', '2021-06-15', 0, 1, 184250, 'available', '2026-12-15', 'Tractor and flatbed trailer for the central corridor.', '2026-09-05 08:00:00', NULL),
(2, 'RAE 219 C', 'Delivery truck', 'Mercedes-Benz', 'Actros 2645', 2022, 'WDB9634031L987654', 28000.00, 70.000, 'diesel', 'leased', '2022-03-01', 0, 2, 96410, 'on_trip', '2026-11-05', 'Rigid truck used on the Rubavu / Goma run.', '2026-09-05 08:05:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_documents`
--

CREATE TABLE `vehicle_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_code` varchar(32) NOT NULL,
  `vehicle_id` int(10) UNSIGNED NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicle_documents`
--

INSERT INTO `vehicle_documents` (`id`, `document_code`, `vehicle_id`, `document_type`, `document_number`, `provider_name`, `issued_on`, `expires_on`, `cost`, `document_file`, `status`, `notes`, `deleted_at`, `created_at`) VALUES
(1, 'DOC-2026-0001', 1, 'insurance', 'SNR-MV-2026-118420', 'SONARWA General Insurance', '2026-01-10', '2027-01-09', 2850000.00, NULL, 'valid', 'Comprehensive cover including COMESA yellow card.', NULL, '2026-09-05 08:10:00'),
(2, 'DOC-2026-0002', 2, 'inspection', 'CT-2026-RAE219C', 'Motor Vehicle Inspection Centre, Kigali', '2026-04-20', '2026-10-20', 50000.00, NULL, 'expiring', 'Book the next inspection before 20 October.', NULL, '2026-09-05 08:15:00');

-- --------------------------------------------------------

--
-- Table structure for table `warehouses`
--

CREATE TABLE `warehouses` (
  `id` int(10) UNSIGNED NOT NULL,
  `warehouse_code` varchar(32) DEFAULT NULL,
  `warehouse_name` varchar(150) NOT NULL,
  `location` varchar(150) NOT NULL,
  `manager_id` int(10) UNSIGNED DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `capacity_m3` decimal(12,2) DEFAULT NULL,
  `is_cold_chain` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouses`
--

INSERT INTO `warehouses` (`id`, `warehouse_code`, `warehouse_name`, `location`, `manager_id`, `phone`, `capacity_m3`, `is_cold_chain`, `status`, `deleted_at`) VALUES
(1, 'WH-2026-0001', 'ITEC Kigali Main Warehouse', 'Masaka, Kicukiro, Kigali', 5, '+250 782 110 554', 4500.00, 0, 'active', NULL),
(2, 'WH-2026-0002', 'ITEC Rubavu Cross-border Depot', 'Rubavu, Western Province', 5, '+250 782 110 555', 1200.00, 0, 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_cargo`
--

CREATE TABLE `warehouse_cargo` (
  `id` int(10) UNSIGNED NOT NULL,
  `movement_code` varchar(40) NOT NULL,
  `warehouse_id` int(10) UNSIGNED NOT NULL,
  `shipment_id` int(10) UNSIGNED NOT NULL,
  `direction` enum('in','out') NOT NULL,
  `reason` enum('received','loaded','arrived','collected','returned') NOT NULL,
  `packages` int(10) UNSIGNED DEFAULT NULL,
  `weight_kg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `trip_id` int(10) UNSIGNED DEFAULT NULL,
  `delivery_id` int(10) UNSIGNED DEFAULT NULL,
  `moved_at` datetime NOT NULL,
  `performed_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouse_cargo`
--

INSERT INTO `warehouse_cargo` (`id`, `movement_code`, `warehouse_id`, `shipment_id`, `direction`, `reason`, `packages`, `weight_kg`, `trip_id`, `delivery_id`, `moved_at`, `performed_by`, `notes`, `created_at`) VALUES
(1, 'CGO-2026-0001', 1, 1, 'in', 'received', 300, 18000.00, NULL, NULL, '2026-09-09 15:00:00', 5, 'Coffee received from Akagera washing station, 300 bags counted.', '2026-09-09 15:00:00'),
(2, 'CGO-2026-0002', 1, 1, 'out', 'loaded', 300, 18000.00, 1, 1, '2026-09-10 05:50:00', 5, 'Loaded on RAD 482 K for TRP-2026-0001.', '2026-09-10 05:50:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `border_charges`
--
ALTER TABLE `border_charges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_border_charge` (`crossing_id`);

--
-- Indexes for table `border_crossings`
--
ALTER TABLE `border_crossings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_crossing_reference` (`reference`),
  ADD KEY `idx_crossing_post` (`border_post_id`,`arrived_at`),
  ADD KEY `idx_crossing_trip` (`trip_id`),
  ADD KEY `idx_crossing_status` (`status`),
  ADD KEY `idx_crossing_deleted` (`deleted_at`);

--
-- Indexes for table `border_documents`
--
ALTER TABLE `border_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_border_document` (`crossing_id`);

--
-- Indexes for table `border_posts`
--
ALTER TABLE `border_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_border_post` (`post_name`),
  ADD KEY `idx_border_active` (`is_active`,`post_name`);

--
-- Indexes for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_currency_code` (`code`),
  ADD KEY `idx_currency_active` (`is_active`,`sort_order`);

--
-- Indexes for table `currency_rates`
--
ALTER TABLE `currency_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rate_day` (`currency_code`,`effective_from`),
  ADD KEY `idx_rate_lookup` (`currency_code`,`effective_from`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`),
  ADD KEY `idx_customers_status` (`status`),
  ADD KEY `idx_customers_deleted` (`deleted_at`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `delivery_code` (`delivery_code`),
  ADD KEY `trip_id` (`trip_id`),
  ADD KEY `fk_deliveries_shipment` (`shipment_id`),
  ADD KEY `idx_deliveries_status` (`status`),
  ADD KEY `idx_deliveries_deleted` (`deleted_at`),
  ADD KEY `fk_delivery_warehouse` (`destination_warehouse_id`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `uq_drivers_user_id` (`user_id`),
  ADD KEY `idx_drivers_status` (`status`),
  ADD KEY `idx_drivers_deleted` (`deleted_at`);

--
-- Indexes for table `email_outbox`
--
ALTER TABLE `email_outbox`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `message_key` (`message_key`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_outbox_status` (`status`,`created_at`),
  ADD KEY `idx_outbox_recipient` (`to_email`),
  ADD KEY `idx_outbox_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_code` (`reference_code`),
  ADD KEY `trip_id` (`trip_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `fk_expenses_approver` (`approved_by`),
  ADD KEY `idx_expenses_date` (`expense_date`),
  ADD KEY `idx_expenses_status` (`status`),
  ADD KEY `idx_expenses_deleted` (`deleted_at`);

--
-- Indexes for table `fuel_records`
--
ALTER TABLE `fuel_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_code` (`reference_code`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `fk_fuel_driver` (`driver_id`),
  ADD KEY `fk_fuel_trip` (`trip_id`),
  ADD KEY `idx_fuel_purchased` (`purchased_at`),
  ADD KEY `idx_fuel_deleted` (`deleted_at`);

--
-- Indexes for table `gl_accounts`
--
ALTER TABLE `gl_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_code` (`account_code`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `idx_gl_accounts_type` (`account_type`),
  ADD KEY `idx_gl_accounts_section` (`section_order`,`account_code`),
  ADD KEY `idx_gl_accounts_deleted` (`deleted_at`);

--
-- Indexes for table `gl_budgets`
--
ALTER TABLE `gl_budgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_budget_line` (`fiscal_year`,`account_id`,`month`),
  ADD KEY `idx_budget_account` (`account_id`);

--
-- Indexes for table `gl_cheques`
--
ALTER TABLE `gl_cheques`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cheque_reference` (`reference`),
  ADD UNIQUE KEY `uq_cheque_no_account` (`bank_account_id`,`cheque_no`),
  ADD KEY `idx_cheque_date` (`cheque_date`),
  ADD KEY `idx_cheque_status` (`status`),
  ADD KEY `idx_cheque_deleted` (`deleted_at`);

--
-- Indexes for table `gl_cheque_books`
--
ALTER TABLE `gl_cheque_books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cheque_book_account` (`bank_account_id`,`status`);

--
-- Indexes for table `gl_cheque_lines`
--
ALTER TABLE `gl_cheque_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cheque_line` (`cheque_id`),
  ADD KEY `idx_cheque_line_account` (`account_id`);

--
-- Indexes for table `gl_fiscal_periods`
--
ALTER TABLE `gl_fiscal_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `period_label` (`period_label`),
  ADD KEY `closed_by` (`closed_by`),
  ADD KEY `idx_gl_periods_range` (`starts_on`,`ends_on`);

--
-- Indexes for table `gl_journal_entries`
--
ALTER TABLE `gl_journal_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `entry_no` (`entry_no`),
  ADD UNIQUE KEY `uq_gl_source` (`source_type`,`source_id`),
  ADD KEY `posted_by` (`posted_by`),
  ADD KEY `idx_gl_entries_date` (`entry_date`),
  ADD KEY `idx_gl_entries_status` (`status`),
  ADD KEY `idx_gl_entries_deleted` (`deleted_at`),
  ADD KEY `idx_journal_currency` (`currency`,`entry_date`);

--
-- Indexes for table `gl_journal_lines`
--
ALTER TABLE `gl_journal_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gl_lines_account` (`account_id`),
  ADD KEY `idx_gl_lines_entry` (`entry_id`,`line_no`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `idx_inventory_status` (`status`),
  ADD KEY `idx_inventory_deleted` (`deleted_at`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `trip_id` (`trip_id`),
  ADD KEY `issued_by` (`issued_by`),
  ADD KEY `idx_invoices_status` (`status`),
  ADD KEY `idx_invoices_issue` (`issue_date`),
  ADD KEY `idx_invoices_deleted` (`deleted_at`);

--
-- Indexes for table `invoice_lines`
--
ALTER TABLE `invoice_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shipment_id` (`shipment_id`),
  ADD KEY `idx_invoice_lines_invoice` (`invoice_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempts_email` (`email`,`attempted_at`);

--
-- Indexes for table `lookup_values`
--
ALTER TABLE `lookup_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_lookup_entry` (`list_key`,`value`),
  ADD KEY `idx_lookup_list` (`list_key`,`sort_order`),
  ADD KEY `idx_lookup_deleted` (`deleted_at`);

--
-- Indexes for table `maintenance_orders`
--
ALTER TABLE `maintenance_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `work_order_code` (`work_order_code`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `idx_maintenance_status` (`status`),
  ADD KEY `idx_maintenance_deleted` (`deleted_at`);

--
-- Indexes for table `maintenance_parts`
--
ALTER TABLE `maintenance_parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_maintenance_parts_order` (`maintenance_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `migration` (`migration`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `notification_key` (`notification_key`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `role_key` (`role_key`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_resets_expiry` (`expires_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_code` (`payment_code`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `idx_payments_date` (`paid_at`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payment_method` (`method_key`),
  ADD KEY `idx_payment_method_order` (`sort_order`,`method_name`),
  ADD KEY `fk_payment_method_account` (`gl_account_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_key` (`permission_key`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_code` (`request_code`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `fk_purchase_warehouse` (`warehouse_id`),
  ADD KEY `fk_purchase_approver` (`approved_by`),
  ADD KEY `idx_purchase_status` (`status`),
  ADD KEY `idx_purchase_deleted` (`deleted_at`);

--
-- Indexes for table `purchase_request_lines`
--
ALTER TABLE `purchase_request_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `idx_pr_lines_request` (`purchase_request_id`);

--
-- Indexes for table `rate_cards`
--
ALTER TABLE `rate_cards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rate_code` (`rate_code`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_rates_status` (`status`),
  ADD KEY `idx_rates_deleted` (`deleted_at`),
  ADD KEY `fk_rate_origin_wh` (`origin_warehouse_id`),
  ADD KEY `fk_rate_destination_wh` (`destination_warehouse_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `report_name` (`report_name`),
  ADD UNIQUE KEY `uq_reports_key` (`report_key`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_key` (`role_key`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_role_permission` (`role_id`,`permission_key`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shipment_code` (`shipment_code`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `trip_id` (`trip_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_shipments_status` (`status`),
  ADD KEY `idx_shipments_deleted` (`deleted_at`),
  ADD KEY `fk_shipment_rate_card` (`rate_card_id`),
  ADD KEY `fk_shipment_origin_wh` (`origin_warehouse_id`),
  ADD KEY `fk_shipment_destination_wh` (`destination_warehouse_id`),
  ADD KEY `fk_shipment_stock_item` (`stock_item_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `movement_code` (`movement_code`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `idx_movements_item` (`item_id`,`moved_at`),
  ADD KEY `idx_movements_date` (`moved_at`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transport_requests`
--
ALTER TABLE `transport_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_code` (`reference_code`),
  ADD KEY `requester_id` (`requester_id`),
  ADD KEY `fk_requests_customer` (`customer_id`),
  ADD KEY `fk_requests_trip` (`trip_id`),
  ADD KEY `fk_requests_approver` (`approved_by`),
  ADD KEY `idx_requests_status` (`status`),
  ADD KEY `idx_requests_deleted` (`deleted_at`),
  ADD KEY `fk_request_origin_wh` (`origin_warehouse_id`),
  ADD KEY `fk_request_destination_wh` (`destination_warehouse_id`),
  ADD KEY `fk_request_rate_card` (`rate_card_id`),
  ADD KEY `fk_request_stock_item` (`stock_item_id`);

--
-- Indexes for table `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_code` (`reference_code`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `fk_trips_customer` (`customer_id`),
  ADD KEY `idx_trips_status` (`status`),
  ADD KEY `idx_trips_departure` (`departure_at`),
  ADD KEY `idx_trips_deleted` (`deleted_at`),
  ADD KEY `idx_trips_request` (`request_id`);

--
-- Indexes for table `trip_stops`
--
ALTER TABLE `trip_stops`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_trip_stops_trip` (`trip_id`,`stop_sequence`),
  ADD KEY `fk_stop_warehouse` (`warehouse_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `idx_users_deleted` (`deleted_at`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`),
  ADD KEY `idx_vehicles_status` (`status`),
  ADD KEY `idx_vehicles_deleted` (`deleted_at`);

--
-- Indexes for table `vehicle_documents`
--
ALTER TABLE `vehicle_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_code` (`document_code`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `idx_vehicle_docs_expiry` (`expires_on`),
  ADD KEY `idx_vehicle_docs_deleted` (`deleted_at`);

--
-- Indexes for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `warehouse_cargo`
--
ALTER TABLE `warehouse_cargo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_cargo_movement` (`movement_code`),
  ADD UNIQUE KEY `uniq_cargo_step` (`shipment_id`,`warehouse_id`,`reason`),
  ADD KEY `idx_cargo_warehouse` (`warehouse_id`,`moved_at`),
  ADD KEY `idx_cargo_shipment` (`shipment_id`,`moved_at`),
  ADD KEY `fk_cargo_trip` (`trip_id`),
  ADD KEY `fk_cargo_delivery` (`delivery_id`),
  ADD KEY `fk_cargo_user` (`performed_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=371;

--
-- AUTO_INCREMENT for table `border_charges`
--
ALTER TABLE `border_charges`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `border_crossings`
--
ALTER TABLE `border_crossings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `border_documents`
--
ALTER TABLE `border_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `border_posts`
--
ALTER TABLE `border_posts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `company_settings`
--
ALTER TABLE `company_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `currency_rates`
--
ALTER TABLE `currency_rates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `email_outbox`
--
ALTER TABLE `email_outbox`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `fuel_records`
--
ALTER TABLE `fuel_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `gl_accounts`
--
ALTER TABLE `gl_accounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=634;

--
-- AUTO_INCREMENT for table `gl_budgets`
--
ALTER TABLE `gl_budgets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gl_cheques`
--
ALTER TABLE `gl_cheques`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `gl_cheque_books`
--
ALTER TABLE `gl_cheque_books`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `gl_cheque_lines`
--
ALTER TABLE `gl_cheque_lines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `gl_fiscal_periods`
--
ALTER TABLE `gl_fiscal_periods`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `gl_journal_entries`
--
ALTER TABLE `gl_journal_entries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `gl_journal_lines`
--
ALTER TABLE `gl_journal_lines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_lines`
--
ALTER TABLE `invoice_lines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `lookup_values`
--
ALTER TABLE `lookup_values`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1535;

--
-- AUTO_INCREMENT for table `maintenance_orders`
--
ALTER TABLE `maintenance_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_parts`
--
ALTER TABLE `maintenance_parts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_request_lines`
--
ALTER TABLE `purchase_request_lines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `rate_cards`
--
ALTER TABLE `rate_cards`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=144;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=265;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `transport_requests`
--
ALTER TABLE `transport_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `trips`
--
ALTER TABLE `trips`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `trip_stops`
--
ALTER TABLE `trip_stops`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vehicle_documents`
--
ALTER TABLE `vehicle_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouses`
--
ALTER TABLE `warehouses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `warehouse_cargo`
--
ALTER TABLE `warehouse_cargo`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `border_charges`
--
ALTER TABLE `border_charges`
  ADD CONSTRAINT `fk_border_charge` FOREIGN KEY (`crossing_id`) REFERENCES `border_crossings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `border_crossings`
--
ALTER TABLE `border_crossings`
  ADD CONSTRAINT `fk_crossing_post` FOREIGN KEY (`border_post_id`) REFERENCES `border_posts` (`id`);

--
-- Constraints for table `border_documents`
--
ALTER TABLE `border_documents`
  ADD CONSTRAINT `fk_border_document` FOREIGN KEY (`crossing_id`) REFERENCES `border_crossings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_deliveries_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_delivery_warehouse` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `email_outbox`
--
ALTER TABLE `email_outbox`
  ADD CONSTRAINT `email_outbox_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_expenses_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `fuel_records`
--
ALTER TABLE `fuel_records`
  ADD CONSTRAINT `fk_fuel_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fuel_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fuel_records_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`);

--
-- Constraints for table `gl_accounts`
--
ALTER TABLE `gl_accounts`
  ADD CONSTRAINT `gl_accounts_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `gl_accounts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `gl_budgets`
--
ALTER TABLE `gl_budgets`
  ADD CONSTRAINT `fk_budget_account` FOREIGN KEY (`account_id`) REFERENCES `gl_accounts` (`id`);

--
-- Constraints for table `gl_cheques`
--
ALTER TABLE `gl_cheques`
  ADD CONSTRAINT `fk_cheque_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `gl_accounts` (`id`);

--
-- Constraints for table `gl_cheque_books`
--
ALTER TABLE `gl_cheque_books`
  ADD CONSTRAINT `fk_cheque_book_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `gl_accounts` (`id`);

--
-- Constraints for table `gl_cheque_lines`
--
ALTER TABLE `gl_cheque_lines`
  ADD CONSTRAINT `fk_cheque_line_account` FOREIGN KEY (`account_id`) REFERENCES `gl_accounts` (`id`),
  ADD CONSTRAINT `fk_cheque_line_cheque` FOREIGN KEY (`cheque_id`) REFERENCES `gl_cheques` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gl_fiscal_periods`
--
ALTER TABLE `gl_fiscal_periods`
  ADD CONSTRAINT `gl_fiscal_periods_ibfk_1` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `gl_journal_entries`
--
ALTER TABLE `gl_journal_entries`
  ADD CONSTRAINT `gl_journal_entries_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `gl_journal_lines`
--
ALTER TABLE `gl_journal_lines`
  ADD CONSTRAINT `gl_journal_lines_ibfk_1` FOREIGN KEY (`entry_id`) REFERENCES `gl_journal_entries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gl_journal_lines_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `gl_accounts` (`id`);

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`);

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoice_lines`
--
ALTER TABLE `invoice_lines`
  ADD CONSTRAINT `invoice_lines_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_lines_ibfk_2` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `maintenance_orders`
--
ALTER TABLE `maintenance_orders`
  ADD CONSTRAINT `maintenance_orders_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`);

--
-- Constraints for table `maintenance_parts`
--
ALTER TABLE `maintenance_parts`
  ADD CONSTRAINT `maintenance_parts_ibfk_1` FOREIGN KEY (`maintenance_id`) REFERENCES `maintenance_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD CONSTRAINT `fk_payment_method_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD CONSTRAINT `fk_purchase_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_purchase_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_requests_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_request_lines`
--
ALTER TABLE `purchase_request_lines`
  ADD CONSTRAINT `purchase_request_lines_ibfk_1` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_request_lines_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `rate_cards`
--
ALTER TABLE `rate_cards`
  ADD CONSTRAINT `fk_rate_destination_wh` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rate_origin_wh` FOREIGN KEY (`origin_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rate_cards_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shipments`
--
ALTER TABLE `shipments`
  ADD CONSTRAINT `fk_shipment_destination_wh` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_shipment_origin_wh` FOREIGN KEY (`origin_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_shipment_rate_card` FOREIGN KEY (`rate_card_id`) REFERENCES `rate_cards` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_shipment_stock_item` FOREIGN KEY (`stock_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `shipments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `transport_requests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `shipments_ibfk_2` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `shipments_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transport_requests`
--
ALTER TABLE `transport_requests`
  ADD CONSTRAINT `fk_request_destination_wh` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_request_origin_wh` FOREIGN KEY (`origin_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_request_rate_card` FOREIGN KEY (`rate_card_id`) REFERENCES `rate_cards` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_request_stock_item` FOREIGN KEY (`stock_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_requests_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_requests_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_requests_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transport_requests_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `trips`
--
ALTER TABLE `trips`
  ADD CONSTRAINT `fk_trips_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_trips_request` FOREIGN KEY (`request_id`) REFERENCES `transport_requests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `trips_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `trip_stops`
--
ALTER TABLE `trip_stops`
  ADD CONSTRAINT `fk_stop_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `trip_stops_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `vehicle_documents`
--
ALTER TABLE `vehicle_documents`
  ADD CONSTRAINT `vehicle_documents_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warehouse_cargo`
--
ALTER TABLE `warehouse_cargo`
  ADD CONSTRAINT `fk_cargo_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cargo_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cargo_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cargo_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cargo_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
