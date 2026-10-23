-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: eri_install_build
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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

--
-- Table structure for table `approval_delegations`
--

DROP TABLE IF EXISTS `approval_delegations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_delegations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `from_user_id` int(10) unsigned NOT NULL,
  `to_user_id` int(10) unsigned NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_delegation` (`to_user_id`,`starts_on`,`ends_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approval_delegations`
--

LOCK TABLES `approval_delegations` WRITE;
/*!40000 ALTER TABLE `approval_delegations` DISABLE KEYS */;
/*!40000 ALTER TABLE `approval_delegations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `approval_policies`
--

DROP TABLE IF EXISTS `approval_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_policies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `policy_code` varchar(40) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(40) NOT NULL DEFAULT 'approve',
  `step_no` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `step_label` varchar(120) NOT NULL,
  `approver_role` varchar(50) NOT NULL,
  `applies_when` varchar(30) NOT NULL DEFAULT 'always' COMMENT 'always, cross_border',
  `currency` char(3) DEFAULT NULL COMMENT 'with min_amount: only records in this currency',
  `min_amount` decimal(14,2) DEFAULT NULL COMMENT 'only records of this amount or more',
  `sla_hours` smallint(5) unsigned NOT NULL DEFAULT 24,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_approval_policy_code` (`policy_code`),
  KEY `idx_approval_policy` (`module`,`action`,`is_active`,`step_no`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approval_policies`
--

LOCK TABLES `approval_policies` WRITE;
/*!40000 ALTER TABLE `approval_policies` DISABLE KEYS */;
INSERT INTO `approval_policies` VALUES (1,'REQ-1','requests','approve',1,'Operations approval','logistics_manager','always',NULL,NULL,8,1,'The route, the truck type and the price make sense.','2026-10-23 14:22:48'),(2,'REQ-2-USD','requests','approve',2,'Management approval','management','always','USD',10000.00,24,1,'Large jobs are signed by management.','2026-10-23 14:22:48'),(3,'REQ-2-RWF','requests','approve',2,'Management approval','management','always','RWF',12000000.00,24,1,NULL,'2026-10-23 14:22:48'),(4,'REQ-2-KES','requests','approve',2,'Management approval','management','always','KES',1200000.00,24,1,NULL,'2026-10-23 14:22:48'),(5,'REQ-2-TZS','requests','approve',2,'Management approval','management','always','TZS',25000000.00,24,1,NULL,'2026-10-23 14:22:48'),(6,'REQ-2-UGX','requests','approve',2,'Management approval','management','always','UGX',35000000.00,24,1,NULL,'2026-10-23 14:22:48'),(7,'TRP-1','trips','approve',1,'Clearance ready','clearance_officer','cross_border',NULL,NULL,12,0,'Every border on the route has its papers lodged.','2026-10-23 14:22:48'),(8,'TRP-2','trips','approve',2,'Release the truck','logistics_manager','always',NULL,NULL,4,0,'Operations releases the truck to the driver.','2026-10-23 14:22:48'),(9,'DOC-1','shipment_documents','verify',1,'Operations check','logistics_manager','always',NULL,NULL,8,1,'The paper matches the booking: names, weights, counts.','2026-10-23 14:22:48'),(10,'DOC-2','shipment_documents','verify',2,'Customs compliance','clearance_officer','cross_border',NULL,NULL,8,1,'The paper will be accepted at the border.','2026-10-23 14:22:48'),(11,'CDOC-1','clearance_documents','verify',1,'Clearance check','clearance_officer','always',NULL,NULL,8,1,NULL,'2026-10-23 14:22:48'),(12,'EXP-1','expenses','approve',1,'Operations check','logistics_manager','always',NULL,NULL,12,1,'The cost belongs to this trip.','2026-10-23 14:22:48'),(13,'EXP-2','expenses','approve',2,'Finance approval','finance','always',NULL,NULL,24,1,NULL,'2026-10-23 14:22:48'),(14,'EXP-3-USD','expenses','approve',3,'Management approval','management','always','USD',2000.00,24,1,NULL,'2026-10-23 14:22:48'),(15,'EXP-3-RWF','expenses','approve',3,'Management approval','management','always','RWF',2500000.00,24,1,NULL,'2026-10-23 14:22:48'),(16,'EXP-3-KES','expenses','approve',3,'Management approval','management','always','KES',250000.00,24,1,NULL,'2026-10-23 14:22:48'),(17,'EXP-3-TZS','expenses','approve',3,'Management approval','management','always','TZS',5000000.00,24,1,NULL,'2026-10-23 14:22:48'),(18,'EXP-3-UGX','expenses','approve',3,'Management approval','management','always','UGX',7000000.00,24,1,NULL,'2026-10-23 14:22:48'),(19,'PRC-1','procurement','approve',1,'Finance budget check','finance','always',NULL,NULL,24,1,NULL,'2026-10-23 14:22:48'),(20,'PRC-2-USD','procurement','approve',2,'Management approval','management','always','USD',5000.00,48,1,NULL,'2026-10-23 14:22:48'),(21,'PRC-2-RWF','procurement','approve',2,'Management approval','management','always','RWF',6000000.00,48,1,NULL,'2026-10-23 14:22:48'),(22,'PRC-2-KES','procurement','approve',2,'Management approval','management','always','KES',600000.00,48,1,NULL,'2026-10-23 14:22:48'),(23,'PRC-2-TZS','procurement','approve',2,'Management approval','management','always','TZS',12000000.00,48,1,NULL,'2026-10-23 14:22:48'),(24,'PRC-2-UGX','procurement','approve',2,'Management approval','management','always','UGX',18000000.00,48,1,NULL,'2026-10-23 14:22:48'),(25,'BIL-1','supplier_bills','approve',1,'Finance approval','finance','always',NULL,NULL,24,1,NULL,'2026-10-23 14:22:48'),(26,'BIL-2-USD','supplier_bills','approve',2,'Management approval','management','always','USD',5000.00,48,1,NULL,'2026-10-23 14:22:48'),(27,'BIL-2-RWF','supplier_bills','approve',2,'Management approval','management','always','RWF',6000000.00,48,1,NULL,'2026-10-23 14:22:48'),(28,'BIL-2-KES','supplier_bills','approve',2,'Management approval','management','always','KES',600000.00,48,1,NULL,'2026-10-23 14:22:48'),(29,'BIL-2-TZS','supplier_bills','approve',2,'Management approval','management','always','TZS',12000000.00,48,1,NULL,'2026-10-23 14:22:48'),(30,'BIL-2-UGX','supplier_bills','approve',2,'Management approval','management','always','UGX',18000000.00,48,1,NULL,'2026-10-23 14:22:48'),(31,'INV-1','invoices','approve',1,'Finance issues the invoice','finance','always',NULL,NULL,24,1,NULL,'2026-10-23 14:22:48');
/*!40000 ALTER TABLE `approval_policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `approval_step_settings`
--

DROP TABLE IF EXISTS `approval_step_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_step_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL COMMENT 'blank: every company',
  `step` enum('verify','authorize','pay','confirm') NOT NULL,
  `role_key` varchar(50) NOT NULL,
  `main_user_id` int(10) unsigned DEFAULT NULL COMMENT 'blank: anyone holding the role',
  `backup_user_id` int(10) unsigned DEFAULT NULL,
  `backup_after_hours` smallint(5) unsigned DEFAULT NULL COMMENT 'blank: no backup, the approver is reminded until they act',
  `reminder_hours` smallint(5) unsigned NOT NULL DEFAULT 4,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_step_setting` (`company_id`,`step`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approval_step_settings`
--

LOCK TABLES `approval_step_settings` WRITE;
/*!40000 ALTER TABLE `approval_step_settings` DISABLE KEYS */;
INSERT INTO `approval_step_settings` VALUES (1,NULL,'verify','finance',NULL,NULL,NULL,4,1,NULL,'2026-10-23 14:22:52'),(2,NULL,'authorize','management',NULL,NULL,NULL,4,1,NULL,'2026-10-23 14:22:52'),(3,NULL,'pay','cashier',NULL,NULL,NULL,4,1,NULL,'2026-10-23 14:22:52'),(4,NULL,'confirm','logistics_manager',NULL,NULL,NULL,4,1,NULL,'2026-10-23 14:22:52');
/*!40000 ALTER TABLE `approval_step_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `approval_steps`
--

DROP TABLE IF EXISTS `approval_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_steps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(40) NOT NULL DEFAULT 'approve',
  `record_id` int(10) unsigned NOT NULL,
  `record_code` varchar(64) DEFAULT NULL,
  `round_no` smallint(5) unsigned NOT NULL DEFAULT 1,
  `step_no` tinyint(3) unsigned NOT NULL,
  `step_label` varchar(120) NOT NULL,
  `approver_role` varchar(50) NOT NULL,
  `status` enum('waiting','pending','approved','rejected','cancelled') NOT NULL DEFAULT 'waiting',
  `amount` decimal(14,2) DEFAULT NULL,
  `currency` char(3) DEFAULT NULL,
  `requested_by` int(10) unsigned DEFAULT NULL,
  `decided_by` int(10) unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `sla_hours` smallint(5) unsigned NOT NULL DEFAULT 24,
  `activated_at` datetime DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `reminded_at` datetime DEFAULT NULL,
  `escalated_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_approval_record` (`module`,`record_id`,`action`,`round_no`),
  KEY `idx_approval_inbox` (`status`,`approver_role`),
  KEY `idx_approval_due` (`status`,`due_at`),
  KEY `idx_steps_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approval_steps`
--

LOCK TABLES `approval_steps` WRITE;
/*!40000 ALTER TABLE `approval_steps` DISABLE KEYS */;
/*!40000 ALTER TABLE `approval_steps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `role_key` varchar(50) DEFAULT NULL,
  `action_name` varchar(100) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` varchar(64) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `before_values` longtext DEFAULT NULL,
  `after_values` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,NULL,NULL,NULL,'gl.synced','journal',NULL,NULL,'{\"invoice\":0,\"payment\":0,\"expense\":0,\"fuel\":0,\"maintenance\":0,\"purchase\":0,\"supplier_bill\":0,\"supplier_payment\":0,\"disbursement\":0}',NULL,NULL,'console','console','2026-10-23 14:22:53');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The audit log cannot be changed.' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The audit log cannot be deleted.' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `bank_statement_lines`
--

DROP TABLE IF EXISTS `bank_statement_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_statement_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `statement_id` int(10) unsigned NOT NULL,
  `line_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference` varchar(80) DEFAULT NULL,
  `amount` decimal(16,2) NOT NULL COMMENT 'money in is positive, money out negative',
  `matched_entry_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_statement_line` (`statement_id`),
  KEY `idx_statement_line_entry` (`matched_entry_id`),
  CONSTRAINT `fk_statement_line_entry` FOREIGN KEY (`matched_entry_id`) REFERENCES `gl_journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_statement_line_statement` FOREIGN KEY (`statement_id`) REFERENCES `bank_statements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_statement_lines`
--

LOCK TABLES `bank_statement_lines` WRITE;
/*!40000 ALTER TABLE `bank_statement_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_statement_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_statements`
--

DROP TABLE IF EXISTS `bank_statements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_statements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `statement_code` varchar(32) NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `payment_method_id` int(10) unsigned DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `opening_balance` decimal(16,2) NOT NULL DEFAULT 0.00,
  `closing_balance` decimal(16,2) NOT NULL DEFAULT 0.00,
  `status` enum('open','reconciled') NOT NULL DEFAULT 'open',
  `statement_file` varchar(255) DEFAULT NULL,
  `reconciled_by` int(10) unsigned DEFAULT NULL,
  `reconciled_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_statement` (`statement_code`),
  KEY `idx_bank_statement_account` (`account_id`,`period_to`),
  KEY `idx_statements_company` (`company_id`),
  CONSTRAINT `fk_bank_statement_account` FOREIGN KEY (`account_id`) REFERENCES `gl_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_statements`
--

LOCK TABLES `bank_statements` WRITE;
/*!40000 ALTER TABLE `bank_statements` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_statements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `border_charges`
--

DROP TABLE IF EXISTS `border_charges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `border_charges` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `crossing_id` int(10) unsigned NOT NULL,
  `charge_type` varchar(60) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `receipt_no` varchar(60) DEFAULT NULL,
  `tax_rate` decimal(8,2) DEFAULT NULL,
  `taxable_amount` decimal(14,2) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `paid_for` enum('us','customer') NOT NULL DEFAULT 'us',
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `status` enum('pending','paid','waived') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_border_charge` (`crossing_id`),
  KEY `idx_border_charge_status` (`crossing_id`,`status`),
  CONSTRAINT `fk_border_charge` FOREIGN KEY (`crossing_id`) REFERENCES `border_crossings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `border_charges`
--

LOCK TABLES `border_charges` WRITE;
/*!40000 ALTER TABLE `border_charges` DISABLE KEYS */;
/*!40000 ALTER TABLE `border_charges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `border_crossings`
--

DROP TABLE IF EXISTS `border_crossings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `border_crossings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `reference` varchar(40) NOT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `shipment_id` int(10) unsigned DEFAULT NULL,
  `vehicle_id` int(10) unsigned DEFAULT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `border_post_id` int(10) unsigned NOT NULL,
  `customs_country` varchar(60) DEFAULT NULL,
  `direction` enum('export','import','transit') NOT NULL DEFAULT 'export',
  `clearing_agent_id` int(10) unsigned DEFAULT NULL,
  `declaration_no` varchar(60) DEFAULT NULL COMMENT 'the customs declaration or entry number',
  `transit_bond_no` varchar(60) DEFAULT NULL COMMENT 'T1 or regional bond reference',
  `seal_no` varchar(60) DEFAULT NULL,
  `weighbridge_kg` decimal(12,2) DEFAULT NULL COMMENT 'what the axle scale said',
  `arrived_at` datetime DEFAULT NULL,
  `lodged_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `cleared_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `departed_at` datetime DEFAULT NULL,
  `status` enum('draft','documents_pending','declaration_submitted','under_review','inspection','duties_pending','payment_pending','cleared','released','rejected') NOT NULL DEFAULT 'draft',
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
  KEY `idx_crossing_shipment` (`shipment_id`),
  KEY `idx_crossings_company` (`company_id`),
  CONSTRAINT `fk_crossing_post` FOREIGN KEY (`border_post_id`) REFERENCES `border_posts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `border_crossings`
--

LOCK TABLES `border_crossings` WRITE;
/*!40000 ALTER TABLE `border_crossings` DISABLE KEYS */;
/*!40000 ALTER TABLE `border_crossings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `border_documents`
--

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
  `status` enum('pending','valid','rejected','expired') NOT NULL DEFAULT 'pending',
  `document_file` varchar(255) DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_border_document` (`crossing_id`),
  KEY `idx_border_document_status` (`crossing_id`,`status`),
  KEY `fk_border_document_uploader` (`uploaded_by`),
  CONSTRAINT `fk_border_document` FOREIGN KEY (`crossing_id`) REFERENCES `border_crossings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_border_document_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `border_documents`
--

LOCK TABLES `border_documents` WRITE;
/*!40000 ALTER TABLE `border_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `border_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `border_posts`
--

DROP TABLE IF EXISTS `border_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `border_posts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `post_name` varchar(120) NOT NULL,
  `post_code` varchar(20) DEFAULT NULL COMMENT 'the customs office code, when there is one',
  `country_a` varchar(60) NOT NULL COMMENT 'the side this company is usually on',
  `country_b` varchar(60) NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `geofence_m` int(10) unsigned NOT NULL DEFAULT 1500,
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

--
-- Dumping data for table `border_posts`
--

LOCK TABLES `border_posts` WRITE;
/*!40000 ALTER TABLE `border_posts` DISABLE KEYS */;
INSERT INTO `border_posts` VALUES (1,'Gatuna / Katuna','GTN','Rwanda','Uganda',-1.4300000,30.0110000,3000,1,6.00,NULL,NULL,'Northern corridor to Kampala and Mombasa.',1,NULL,'2026-10-23 14:22:45'),(2,'Rusumo','RSM','Rwanda','Tanzania',-2.3828000,30.7837000,3000,1,8.00,NULL,NULL,'Central corridor to Dar es Salaam.',1,NULL,'2026-10-23 14:22:45'),(3,'Kagitumba / Mirama Hills','KGT','Rwanda','Uganda',-1.0570000,30.4560000,3000,1,5.00,NULL,NULL,NULL,1,NULL,'2026-10-23 14:22:45'),(4,'Rusizi I / Bukavu','RSZ','Rwanda','DR Congo',-2.4870000,28.9000000,3000,0,4.00,NULL,NULL,NULL,1,NULL,'2026-10-23 14:22:45'),(5,'La Corniche / Goma','GSN','Rwanda','DR Congo',-1.6850000,29.2420000,3000,0,4.00,NULL,NULL,NULL,1,NULL,'2026-10-23 14:22:45'),(6,'Nemba / Gasenyi','NMB','Rwanda','Burundi',NULL,NULL,1500,0,5.00,NULL,NULL,NULL,1,NULL,'2026-10-23 14:22:45'),(7,'Namanga','NMG','Kenya','Tanzania',-2.5440000,36.7880000,3000,1,7.00,NULL,NULL,'On the Nairobi to Arusha run.',1,NULL,'2026-10-23 14:22:45'),(8,'Malaba','MLB','Kenya','Uganda',0.6360000,34.2810000,3000,1,10.00,NULL,NULL,'The busiest post on the northern corridor.',1,NULL,'2026-10-23 14:22:45'),(9,'Busia','BSA','Kenya','Uganda',0.4640000,34.0920000,3000,1,8.00,NULL,NULL,NULL,1,NULL,'2026-10-23 14:22:45'),(10,'Holili / Taveta','HLL','Tanzania','Kenya',NULL,NULL,1500,1,6.00,NULL,NULL,NULL,1,NULL,'2026-10-23 14:22:45');
/*!40000 ALTER TABLE `border_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cargo_claims`
--

DROP TABLE IF EXISTS `cargo_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cargo_claims` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `claim_code` varchar(32) NOT NULL,
  `freight_job_id` int(10) unsigned DEFAULT NULL,
  `shipment_id` int(10) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `claim_type` enum('damage','loss','theft','shortage','delay','other') NOT NULL DEFAULT 'damage',
  `incident_date` date DEFAULT NULL,
  `incident_place` varchar(150) DEFAULT NULL,
  `description` text NOT NULL,
  `claimed_amount` decimal(14,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `insurer` varchar(150) DEFAULT NULL,
  `insurer_ref` varchar(60) DEFAULT NULL,
  `status` enum('open','submitted','settled','rejected') NOT NULL DEFAULT 'open',
  `settled_amount` decimal(14,2) DEFAULT NULL,
  `settled_at` date DEFAULT NULL,
  `document_file` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cargo_claim_code` (`claim_code`),
  KEY `idx_cargo_claim_status` (`status`),
  KEY `idx_cargo_claim_job` (`freight_job_id`),
  KEY `idx_claims_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cargo_claims`
--

LOCK TABLES `cargo_claims` WRITE;
/*!40000 ALTER TABLE `cargo_claims` DISABLE KEYS */;
/*!40000 ALTER TABLE `cargo_claims` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clearance_inspections`
--

DROP TABLE IF EXISTS `clearance_inspections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clearance_inspections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `crossing_id` int(10) unsigned NOT NULL,
  `inspection_type` varchar(100) NOT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `inspector` varchar(120) DEFAULT NULL,
  `result` enum('pending','passed','failed') NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_clearance_inspection` (`crossing_id`,`result`),
  CONSTRAINT `fk_clearance_inspection_crossing` FOREIGN KEY (`crossing_id`) REFERENCES `border_crossings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clearance_inspections`
--

LOCK TABLES `clearance_inspections` WRITE;
/*!40000 ALTER TABLE `clearance_inspections` DISABLE KEYS */;
/*!40000 ALTER TABLE `clearance_inspections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clearance_payments`
--

DROP TABLE IF EXISTS `clearance_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clearance_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `crossing_id` int(10) unsigned NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `payment_method` varchar(80) DEFAULT NULL,
  `reference` varchar(80) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `status` enum('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_clearance_payment` (`crossing_id`,`status`),
  CONSTRAINT `fk_clearance_payment_crossing` FOREIGN KEY (`crossing_id`) REFERENCES `border_crossings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clearance_payments`
--

LOCK TABLES `clearance_payments` WRITE;
/*!40000 ALTER TABLE `clearance_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `clearance_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clearance_releases`
--

DROP TABLE IF EXISTS `clearance_releases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clearance_releases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `crossing_id` int(10) unsigned NOT NULL,
  `release_number` varchar(80) NOT NULL,
  `release_date` date NOT NULL,
  `released_by` int(10) unsigned DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clearance_release_crossing` (`crossing_id`),
  UNIQUE KEY `uq_clearance_release_number` (`release_number`),
  KEY `fk_clearance_release_user` (`released_by`),
  CONSTRAINT `fk_clearance_release_crossing` FOREIGN KEY (`crossing_id`) REFERENCES `border_crossings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_clearance_release_user` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clearance_releases`
--

LOCK TABLES `clearance_releases` WRITE;
/*!40000 ALTER TABLE `clearance_releases` DISABLE KEYS */;
/*!40000 ALTER TABLE `clearance_releases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_code` varchar(20) NOT NULL,
  `company_name` varchar(150) NOT NULL COMMENT 'as printed on every form header',
  `short_name` varchar(60) DEFAULT NULL,
  `country` varchar(60) DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `website` varchar(190) DEFAULT NULL,
  `tin_number` varchar(40) DEFAULT NULL,
  `base_currency` char(3) NOT NULL DEFAULT 'RWF' COMMENT 'the currency its books are kept in',
  `logo_file` varchar(255) DEFAULT NULL,
  `stamp_file` varchar(255) DEFAULT NULL COMMENT 'printed when the MD authorizes',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 100,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_code` (`company_code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `companies`
--

LOCK TABLES `companies` WRITE;
/*!40000 ALTER TABLE `companies` DISABLE KEYS */;
INSERT INTO `companies` VALUES (1,'ERI-RW','ERI RWANDA LTD','ERI Rwanda','Rwanda','Kigali','KIGALI-RWANDA','+250 252 502 437','info@eri-rwanda.com',NULL,NULL,'RWF',NULL,NULL,1,10,NULL,NULL,'2026-10-23 14:22:53'),(2,'ERI-KE','ERI KENYA LTD','ERI Kenya','Kenya','Mombasa','New Cannon Tower, 9th floor, Moi Avenue, Mombasa','+254 720 051668','info@eri-kenya.com',NULL,NULL,'KES',NULL,NULL,1,20,NULL,NULL,'2026-10-23 14:22:53'),(3,'ERI-TZ','ERI TANZANIA LTD','ERI Tanzania','Tanzania','Dar es Salaam',NULL,NULL,NULL,NULL,NULL,'TZS',NULL,NULL,1,30,NULL,NULL,'2026-10-23 14:22:53');
/*!40000 ALTER TABLE `companies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `company_settings`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `company_settings`
--

LOCK TABLES `company_settings` WRITE;
/*!40000 ALTER TABLE `company_settings` DISABLE KEYS */;
INSERT INTO `company_settings` VALUES (1,'company_name','','Company name','Company','text','2026-10-23 14:22:44'),(2,'company_tin','','TIN number','Company','text','2026-10-23 14:22:44'),(3,'company_phone','','Phone','Company','text','2026-10-23 14:22:44'),(4,'company_email','','Email','Company','text','2026-10-23 14:22:44'),(5,'company_address','','Address','Company','text','2026-10-23 14:22:44'),(6,'currency_code','RWF','Currency code','Finance','text','2026-10-23 14:22:44'),(7,'currency_symbol','RWF','Currency symbol','Finance','text','2026-10-23 14:22:44'),(8,'tax_rate','18','Default VAT rate (%)','Finance','number','2026-10-23 14:22:44'),(9,'invoice_prefix','INV','Invoice number prefix','Finance','text','2026-10-23 14:22:44'),(10,'payment_terms_days','30','Default payment terms (days)','Finance','number','2026-10-23 14:22:44'),(11,'licence_alert_days','90','Driver licence alert window (days)','Operations','number','2026-10-23 14:22:44'),(12,'document_alert_days','30','Vehicle document alert window (days)','Operations','number','2026-10-23 14:22:44'),(13,'service_alert_days','15','Service due alert window (days)','Operations','number','2026-10-23 14:22:47'),(14,'on_time_grace_minutes','30','On-time delivery grace (minutes)','Operations','number','2026-10-23 14:22:44'),(15,'max_login_attempts','5','Failed logins before lockout','Security','number','2026-10-23 14:22:44'),(16,'lockout_minutes','15','Lockout duration (minutes)','Security','number','2026-10-23 14:22:44'),(17,'accounting_basis','Accrual Basis','Reporting basis','Accounting','text','2026-10-23 14:22:44'),(18,'fiscal_year_start','01-01','Financial year starts (MM-DD)','Accounting','text','2026-10-23 14:22:44'),(19,'gl_auto_post','1','Post operational documents to the ledger automatically','Accounting','number','2026-10-23 14:22:44'),(20,'email_enabled','1','Send email notifications','Email','number','2026-10-23 14:22:44'),(21,'email_signature','This message was sent by the LMS logistics system on behalf of the company named above. Please do not reply to it directly.','Signature at the foot of every email','Email','text','2026-10-23 14:22:44'),(22,'email_min_severity','info','Least important severity to email (info, success, warning, danger)','Email','text','2026-10-23 14:22:44'),(23,'email_alerts_to_roles','1','Email the operational alerts (expiring documents, low stock, overdue invoices)','Email','number','2026-10-23 14:22:44'),(25,'base_currency','RWF','The currency the books are kept in','Company','text','2026-10-23 14:22:45'),(26,'company_seal','','Company seal (stamp)','Company','image','2026-10-23 14:22:48'),(27,'clearance_before_dispatch','1','Clearance lodged before a cross-border truck leaves (1 = yes)','Operations','number','2026-10-23 14:22:48'),(28,'document_pack_always','1','Every shipment needs its document pack before dispatch (1 = yes)','Operations','number','2026-10-23 14:22:48'),(29,'clearance_alert_hours','24','Alert when a clearance has not moved for (hours)','Operations','number','2026-10-23 14:22:48'),(30,'gps_silence_hours','3','Alert when a truck on the road has not reported for (hours)','Operations','number','2026-10-23 14:22:48'),(31,'gps_api_token','','Tracker key (sent by the GPS server as X-Tracker-Key)','Operations','text','2026-10-23 14:22:48'),(32,'approval_separation','1','Nobody approves a record they raised (1 = yes)','Operations','number','2026-10-23 14:22:48'),(33,'public_requests','1','Customers may send transport requests from the website (1 = yes)','Operations','number','2026-10-23 14:22:48'),(34,'default_company_id','1','Company a record belongs to when none is chosen','Company','number','2026-10-23 14:22:51'),(35,'session_timeout_minutes','60','Sign out after this many idle minutes','Security','number','2026-10-23 14:22:51'),(36,'password_min_length','10','Shortest password allowed','Security','number','2026-10-23 14:22:51'),(37,'two_step_for_approvers','1','Approvers sign in with a code sent by email (1 = yes)','Security','number','2026-10-23 14:22:51'),(38,'service_interval_km','7000','Service every (km): oil and filters','Fleet','number','2026-10-23 14:22:51'),(39,'service_warning_km','500','Warn this many km before a service is due','Fleet','number','2026-10-23 14:22:51'),(40,'document_alert_days_list','30,14,3','Warn this many days before a document expires','Fleet','text','2026-10-23 14:22:51'),(41,'driver_unreachable_hours','24','Alert when a driver\'s phone has not been heard from for (hours)','Fleet','number','2026-10-23 14:22:51'),(42,'parked_without_handover_hours','12','Alert when a truck is parked with no handover for (hours)','Fleet','number','2026-10-23 14:22:51'),(43,'batch_number_format','BATCH-{YEAR}-{MONTH}-{SEQ}','Payment batch number','Payments','text','2026-10-23 14:22:52'),(44,'mtn_file_columns','msisdn,amount,name,reference','MTN bulk file columns, in order','Payments','text','2026-10-23 14:22:52'),(45,'mtn_number_format','250XXXXXXXXX','How MoMo numbers are written in the MTN file','Payments','text','2026-10-23 14:22:52'),(46,'approval_reminder_until_handled','1','Remind approvers until they act (1 = yes)','Approvals','number','2026-10-23 14:22:52'),(47,'signature_required_to_approve','1','Approvers must have a signature on file before signing (1 = yes)','Approvals','number','2026-10-23 14:22:52'),(48,'trip_number_format','TRP-{YEAR}-{SEQ4}','Trip number','Trips','text','2026-10-23 14:22:52'),(49,'fuel_variance_percent','3','Flag a trip when fuel used differs from normal by more than (%)','Trips','number','2026-10-23 14:22:52'),(50,'handover_number_format','HO-{YEAR}-{SEQ4}','Vehicle handover number','Trips','text','2026-10-23 14:22:52'),(51,'support_backup_after_minutes','60','An urgent support request not handled goes to a backup after (minutes)','Trips','number','2026-10-23 14:22:52'),(52,'support_backup_role','management','Role that backs up the OM on urgent support requests','Trips','text','2026-10-23 14:22:52'),(53,'whatsapp_enabled','0','Send WhatsApp messages (1 = yes)','WhatsApp','number','2026-10-23 14:22:52'),(54,'whatsapp_business_number','','Company WhatsApp Business number','WhatsApp','text','2026-10-23 14:22:52'),(55,'whatsapp_phone_number_id','','WhatsApp Cloud API: phone number ID','WhatsApp','text','2026-10-23 14:22:52'),(56,'whatsapp_access_token','','WhatsApp Cloud API: access token','WhatsApp','text','2026-10-23 14:22:52'),(57,'whatsapp_api_version','v19.0','WhatsApp Cloud API version','WhatsApp','text','2026-10-23 14:22:52'),(58,'whatsapp_template','','Approved message template (blank: plain text, only inside 24 h of the user writing)','WhatsApp','text','2026-10-23 14:22:52'),(59,'whatsapp_template_language','en','Template language code','WhatsApp','text','2026-10-23 14:22:52'),(60,'whatsapp_country_code','250','Country code added to numbers written without one','WhatsApp','text','2026-10-23 14:22:52'),(61,'journal_number_format','JV-{YEAR}-{SEQ4}','Manual journal number','Accounting','text','2026-10-23 14:22:52'),(62,'backup_keep_days','30','Keep daily database backups for (days)','System','number','2026-10-23 14:22:52'),(63,'backup_folder','','Backup folder (empty = storage/backups)','System','text','2026-10-23 14:22:52'),(64,'job_card_number_format','JC-{YEAR}-{SEQ4}','Job card number','Garage and store','text','2026-10-23 14:22:53'),(65,'goods_receipt_number_format','GRN-{YEAR}-{SEQ4}','Goods received note number','Garage and store','text','2026-10-23 14:22:53'),(66,'stock_count_number_format','SC-{YEAR}-{SEQ4}','Stock count number','Garage and store','text','2026-10-23 14:22:53'),(67,'part_repeat_days','30','Warn when the same part is fitted to the same truck again within (days)','Garage and store','number','2026-10-23 14:22:53'),(68,'service_request_auto','1','Raise a draft service request (F3) when a truck reaches its service (1 = yes, 0 = no)','Garage and store','number','2026-10-23 14:22:53'),(69,'invoice_vat_percent','0','VAT on customer invoices (%)','Invoicing','number','2026-10-23 14:22:53'),(70,'invoice_due_days','30','Invoice due after (days), when the customer has no terms','Invoicing','number','2026-10-23 14:22:53'),(71,'intercompany_invoice_format','ICI-{YEAR}-{SEQ4}','Inter-company invoice number','Accounting','text','2026-10-23 14:22:53'),(72,'intercompany_markup_percent','0','Markup a company adds on work done for a sister company (%)','Accounting','number','2026-10-23 14:22:53'),(73,'report_brand_color','AD7D00','Report colour (hex, no #)','Accounting','text','2026-10-23 14:22:53'),(74,'company_logo','','Company logo','Company','image','2026-10-23 14:22:53');
/*!40000 ALTER TABLE `company_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `containers`
--

DROP TABLE IF EXISTS `containers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `containers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `tracking_code` varchar(32) NOT NULL,
  `container_no` varchar(40) NOT NULL,
  `container_size` varchar(20) DEFAULT NULL,
  `shipping_line_id` int(10) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `shipment_id` int(10) unsigned DEFAULT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `bill_of_lading` varchar(60) DEFAULT NULL,
  `picked_up_at` datetime NOT NULL COMMENT 'gate-out full from the port or depot; the free days run from here',
  `picked_up_from` varchar(120) DEFAULT NULL,
  `free_days` int(10) unsigned NOT NULL DEFAULT 14,
  `detention_rate` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'per day after the free days',
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `unloaded_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL COMMENT 'gate-in empty at the return depot',
  `return_depot` varchar(120) DEFAULT NULL,
  `return_interchange_no` varchar(60) DEFAULT NULL,
  `deposit_amount` decimal(14,2) DEFAULT NULL,
  `deposit_refunded_at` date DEFAULT NULL,
  `status` enum('out_full','unloaded','returned') NOT NULL DEFAULT 'out_full',
  `disbursement_id` int(10) unsigned DEFAULT NULL,
  `interchange_file` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_container_tracking` (`tracking_code`),
  KEY `idx_container_no` (`container_no`),
  KEY `idx_container_status` (`status`,`picked_up_at`),
  KEY `fk_container_line` (`shipping_line_id`),
  KEY `fk_container_customer` (`customer_id`),
  KEY `fk_container_shipment` (`shipment_id`),
  KEY `fk_container_trip` (`trip_id`),
  KEY `idx_containers_company` (`company_id`),
  CONSTRAINT `fk_container_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_container_line` FOREIGN KEY (`shipping_line_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_container_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_container_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `containers`
--

LOCK TABLES `containers` WRITE;
/*!40000 ALTER TABLE `containers` DISABLE KEYS */;
/*!40000 ALTER TABLE `containers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crane_logs`
--

DROP TABLE IF EXISTS `crane_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crane_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `lift_date` date NOT NULL,
  `vehicle_id` int(10) unsigned DEFAULT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `container_no` varchar(20) NOT NULL,
  `operation` enum('loading','offloading') NOT NULL DEFAULT 'loading',
  `container_size` varchar(10) NOT NULL DEFAULT '20',
  `supplier_id` int(10) unsigned DEFAULT NULL COMMENT 'the crane operator',
  `fee` decimal(14,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `request_id` int(10) unsigned DEFAULT NULL COMMENT 'the F7 that paid the operator',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_crane_logs_date` (`lift_date`),
  KEY `idx_crane_logs_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crane_logs`
--

LOCK TABLES `crane_logs` WRITE;
/*!40000 ALTER TABLE `crane_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `crane_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `currencies`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `currencies`
--

LOCK TABLES `currencies` WRITE;
/*!40000 ALTER TABLE `currencies` DISABLE KEYS */;
INSERT INTO `currencies` VALUES (1,'RWF','Rwandan franc','FRw','Rwanda',0,1,1,10,NULL,'2026-10-23 14:22:45'),(2,'KES','Kenyan shilling','KSh','Kenya',2,0,1,20,NULL,'2026-10-23 14:22:45'),(3,'TZS','Tanzanian shilling','TSh','Tanzania',0,0,1,30,NULL,'2026-10-23 14:22:45'),(4,'UGX','Ugandan shilling','USh','Uganda',0,0,0,40,NULL,'2026-10-23 14:22:45'),(5,'USD','US dollar','$','International',2,0,1,50,NULL,'2026-10-23 14:22:45');
/*!40000 ALTER TABLE `currencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `currency_rates`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `currency_rates`
--

LOCK TABLES `currency_rates` WRITE;
/*!40000 ALTER TABLE `currency_rates` DISABLE KEYS */;
INSERT INTO `currency_rates` VALUES (1,'RWF','2000-01-01',1.00000000,'Base currency','The books are kept in this currency, so it never converts.',NULL,'2026-10-23 14:22:45');
/*!40000 ALTER TABLE `currency_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL COMMENT 'blank: a customer of the whole group',
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
  KEY `idx_customers_deleted` (`deleted_at`),
  KEY `idx_customers_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deliveries`
--

DROP TABLE IF EXISTS `deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deliveries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
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
  `pod_latitude` decimal(10,7) DEFAULT NULL,
  `pod_longitude` decimal(10,7) DEFAULT NULL,
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
  KEY `idx_deliveries_company` (`company_id`),
  CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_deliveries_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_delivery_warehouse` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deliveries`
--

LOCK TABLES `deliveries` WRITE;
/*!40000 ALTER TABLE `deliveries` DISABLE KEYS */;
/*!40000 ALTER TABLE `deliveries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `head_user_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_department` (`company_id`,`department_name`),
  CONSTRAINT `fk_department_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,2,'Operations',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(2,1,'Operations',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(3,3,'Operations',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(4,2,'Finance',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(5,1,'Finance',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(6,3,'Finance',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(7,2,'Management',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(8,1,'Management',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(9,3,'Management',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(10,2,'Cash office',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(11,1,'Cash office',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(12,3,'Cash office',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(13,2,'Store',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(14,1,'Store',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(15,3,'Store',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(16,2,'Garage / Workshop',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(17,1,'Garage / Workshop',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(18,3,'Garage / Workshop',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(19,2,'HR',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(20,1,'HR',NULL,1,NULL,NULL,'2026-10-23 14:22:53'),(21,3,'HR',NULL,1,NULL,NULL,'2026-10-23 14:22:53');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disbursements`
--

DROP TABLE IF EXISTS `disbursements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disbursements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `disbursement_code` varchar(32) NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `shipment_id` int(10) unsigned DEFAULT NULL,
  `crossing_id` int(10) unsigned DEFAULT NULL,
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `category` varchar(60) NOT NULL DEFAULT 'Customs duty',
  `description` varchar(255) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `method` varchar(40) NOT NULL DEFAULT 'bank_transfer',
  `reference` varchar(80) DEFAULT NULL,
  `paid_at` date NOT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','invoiced','recovered','written_off') NOT NULL DEFAULT 'pending',
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_disbursement` (`disbursement_code`),
  KEY `idx_disbursement_trip` (`trip_id`,`status`),
  KEY `idx_disbursement_customer` (`customer_id`,`status`),
  KEY `fk_disbursement_shipment` (`shipment_id`),
  KEY `fk_disbursement_supplier` (`supplier_id`),
  KEY `fk_disbursement_invoice` (`invoice_id`),
  KEY `idx_disbursement_crossing` (`crossing_id`),
  KEY `idx_disbursements_company` (`company_id`),
  CONSTRAINT `fk_disbursement_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_disbursement_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_disbursement_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_disbursement_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_disbursement_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disbursements`
--

LOCK TABLES `disbursements` WRITE;
/*!40000 ALTER TABLE `disbursements` DISABLE KEYS */;
/*!40000 ALTER TABLE `disbursements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_sequences`
--

DROP TABLE IF EXISTS `document_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_sequences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `sequence_key` varchar(40) NOT NULL,
  `period_key` varchar(10) NOT NULL,
  `last_no` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sequence` (`company_id`,`sequence_key`,`period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_sequences`
--

LOCK TABLES `document_sequences` WRITE;
/*!40000 ALTER TABLE `document_sequences` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_types`
--

DROP TABLE IF EXISTS `document_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL COMMENT 'blank: the whole group',
  `type_code` varchar(40) NOT NULL,
  `type_name` varchar(120) NOT NULL,
  `applies_to` enum('vehicle','trailer','driver') NOT NULL,
  `fleet` enum('all','tanker','container') NOT NULL DEFAULT 'all' COMMENT 'only trucks/drivers of this fleet need it',
  `required_for_trip` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'a trip cannot be planned without it in date',
  `cross_border_only` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'only needed when the route crosses a border',
  `alert_days` varchar(40) NOT NULL DEFAULT '30,14,3' COMMENT 'days before expiry people are warned',
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 100,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_document_type` (`applies_to`,`type_code`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_types`
--

LOCK TABLES `document_types` WRITE;
/*!40000 ALTER TABLE `document_types` DISABLE KEYS */;
INSERT INTO `document_types` VALUES (1,NULL,'registration','Registration certificate','vehicle','all',1,0,'30,14,3',10,1,NULL,NULL,'2026-10-23 14:22:51'),(2,NULL,'insurance','Insurance','vehicle','all',1,0,'30,14,3',20,1,NULL,NULL,'2026-10-23 14:22:51'),(3,NULL,'regional_insurance','Regional insurance (COMESA yellow card)','vehicle','all',1,1,'30,14,3',30,1,NULL,NULL,'2026-10-23 14:22:51'),(4,NULL,'inspection','Technical inspection','vehicle','all',1,0,'30,14,3',40,1,NULL,NULL,'2026-10-23 14:22:51'),(5,NULL,'transport_licence','Goods-transport licence','vehicle','all',1,0,'30,14,3',50,1,NULL,NULL,'2026-10-23 14:22:51'),(6,NULL,'transit_permit','Cross-border / transit permit','vehicle','all',1,1,'30,14,3',60,1,NULL,NULL,'2026-10-23 14:22:51'),(7,NULL,'tank_calibration','Tank calibration certificate','vehicle','tanker',1,0,'30,14,3',70,1,NULL,NULL,'2026-10-23 14:22:51'),(8,NULL,'registration','Registration certificate','trailer','all',1,0,'30,14,3',10,1,NULL,NULL,'2026-10-23 14:22:51'),(9,NULL,'insurance','Insurance','trailer','all',1,0,'30,14,3',20,1,NULL,NULL,'2026-10-23 14:22:51'),(10,NULL,'inspection','Technical inspection','trailer','all',1,0,'30,14,3',30,1,NULL,NULL,'2026-10-23 14:22:51'),(11,NULL,'tank_calibration','Tank calibration certificate','trailer','tanker',1,0,'30,14,3',40,1,NULL,NULL,'2026-10-23 14:22:51'),(12,NULL,'driving_licence','Driving licence (heavy-truck category)','driver','all',1,0,'30,14,3',10,1,NULL,NULL,'2026-10-23 14:22:51'),(13,NULL,'national_id','National ID','driver','all',0,0,'30,14,3',20,1,NULL,NULL,'2026-10-23 14:22:51'),(14,NULL,'passport','Passport','driver','all',1,1,'30,14,3',30,1,NULL,NULL,'2026-10-23 14:22:51'),(15,NULL,'hazmat_training','Hazardous-goods training','driver','tanker',1,0,'30,14,3',40,1,NULL,NULL,'2026-10-23 14:22:51');
/*!40000 ALTER TABLE `document_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `driver_deductions`
--

DROP TABLE IF EXISTS `driver_deductions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `driver_deductions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `driver_id` int(10) unsigned NOT NULL,
  `request_id` int(10) unsigned DEFAULT NULL COMMENT 'the F9',
  `trip_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` char(3) NOT NULL,
  `status` enum('pending','deducted','cancelled') NOT NULL DEFAULT 'pending',
  `payroll_run_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_driver_deductions_driver` (`driver_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `driver_deductions`
--

LOCK TABLES `driver_deductions` WRITE;
/*!40000 ALTER TABLE `driver_deductions` DISABLE KEYS */;
/*!40000 ALTER TABLE `driver_deductions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `driver_documents`
--

DROP TABLE IF EXISTS `driver_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `driver_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_code` varchar(32) DEFAULT NULL,
  `company_id` int(10) unsigned DEFAULT NULL,
  `driver_id` int(10) unsigned NOT NULL,
  `document_type` varchar(40) NOT NULL,
  `document_number` varchar(80) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('pending','valid','rejected','expired') NOT NULL DEFAULT 'pending',
  `document_file` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_driver_document_type` (`driver_id`,`document_type`),
  KEY `idx_ddocs_company` (`company_id`),
  CONSTRAINT `fk_driver_document_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `driver_documents`
--

LOCK TABLES `driver_documents` WRITE;
/*!40000 ALTER TABLE `driver_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `driver_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `driver_momo_changes`
--

DROP TABLE IF EXISTS `driver_momo_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `driver_momo_changes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `driver_id` int(10) unsigned NOT NULL,
  `old_number` varchar(40) DEFAULT NULL,
  `old_name` varchar(150) DEFAULT NULL,
  `new_number` varchar(40) NOT NULL,
  `new_name` varchar(150) NOT NULL,
  `new_phone` varchar(40) DEFAULT NULL,
  `requested_by` int(10) unsigned DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `decided_by` int(10) unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_momo_driver` (`driver_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `driver_momo_changes`
--

LOCK TABLES `driver_momo_changes` WRITE;
/*!40000 ALTER TABLE `driver_momo_changes` DISABLE KEYS */;
/*!40000 ALTER TABLE `driver_momo_changes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drivers`
--

DROP TABLE IF EXISTS `drivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `drivers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `nationality` varchar(60) DEFAULT NULL,
  `national_id` varchar(40) DEFAULT NULL,
  `passport_number` varchar(40) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `whatsapp_number` varchar(40) DEFAULT NULL,
  `photo_file` varchar(255) DEFAULT NULL,
  `fleet` enum('all','tanker','container') NOT NULL DEFAULT 'all',
  `payment_method` enum('momo','cash') NOT NULL DEFAULT 'momo' COMMENT 'cash: foreign drivers without MoMo',
  `momo_number` varchar(40) DEFAULT NULL,
  `momo_name` varchar(150) DEFAULT NULL COMMENT 'the name MTN has registered on the number',
  `cash_pin_hash` varchar(255) DEFAULT NULL,
  `default_vehicle_id` int(10) unsigned DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `license_number` varchar(80) DEFAULT NULL,
  `license_class` varchar(20) DEFAULT NULL,
  `license_expiry` date DEFAULT NULL,
  `emergency_contact` varchar(150) DEFAULT NULL,
  `emergency_phone` varchar(40) DEFAULT NULL,
  `hired_on` date DEFAULT NULL,
  `contract_type` enum('permanent','fixed_term','casual','probation') DEFAULT NULL,
  `salary` decimal(14,2) DEFAULT NULL COMMENT 'seen by HR only',
  `salary_currency` char(3) DEFAULT NULL,
  `left_on` date DEFAULT NULL,
  `left_reason` varchar(255) DEFAULT NULL,
  `last_seen_at` datetime DEFAULT NULL COMMENT 'the app was last reached from the phone',
  `status` enum('active','on_trip','on_leave','suspended','left') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `license_number` (`license_number`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `uq_drivers_user_id` (`user_id`),
  KEY `idx_drivers_status` (`status`),
  KEY `idx_drivers_deleted` (`deleted_at`),
  KEY `idx_drivers_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drivers`
--

LOCK TABLES `drivers` WRITE;
/*!40000 ALTER TABLE `drivers` DISABLE KEYS */;
/*!40000 ALTER TABLE `drivers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_outbox`
--

DROP TABLE IF EXISTS `email_outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_outbox` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `message_key` varchar(120) NOT NULL,
  `category` enum('notification','alert','password_reset','new_account','invoice','manual','test') NOT NULL DEFAULT 'notification',
  `to_email` varchar(190) NOT NULL,
  `to_name` varchar(150) DEFAULT NULL,
  `cc_emails` text DEFAULT NULL,
  `reply_to` varchar(190) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `body_html` mediumtext NOT NULL,
  `body_text` mediumtext DEFAULT NULL,
  `attachments` text DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_outbox`
--

LOCK TABLES `email_outbox` WRITE;
/*!40000 ALTER TABLE `email_outbox` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_outbox` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
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
  `payment_method` varchar(40) NOT NULL DEFAULT 'cash',
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
  KEY `idx_expenses_company` (`company_id`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_expenses_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_request_events`
--

DROP TABLE IF EXISTS `form_request_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_request_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int(10) unsigned NOT NULL,
  `version` smallint(5) unsigned NOT NULL DEFAULT 1,
  `step` enum('created','prepared','verified','authorized','paid','returned','resent','cancelled','received','forwarded','confirmed','note','issued','partly_issued','withdrawn','accepted','declined','goods_received') NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `on_behalf_of` int(10) unsigned DEFAULT NULL COMMENT 'when a delegate signs',
  `role_key` varchar(50) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `signature_file` varchar(255) DEFAULT NULL,
  `stamp_file` varchar(255) DEFAULT NULL,
  `snapshot` text DEFAULT NULL COMMENT 'JSON copy of the request and lines at this step',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_events_request` (`request_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_request_events`
--

LOCK TABLES `form_request_events` WRITE;
/*!40000 ALTER TABLE `form_request_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_request_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_request_lines`
--

DROP TABLE IF EXISTS `form_request_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_request_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int(10) unsigned NOT NULL,
  `line_no` smallint(5) unsigned NOT NULL DEFAULT 1,
  `group_no` smallint(5) unsigned DEFAULT NULL COMMENT 'lines of one truck on a multi-truck F1',
  `trip_id` int(10) unsigned DEFAULT NULL,
  `vehicle_id` int(10) unsigned DEFAULT NULL,
  `trailer_id` int(10) unsigned DEFAULT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `item_code` varchar(40) NOT NULL DEFAULT 'other' COMMENT 'fuel, road_toll, mileage, part, tyre, crane, labour, support...',
  `item_id` int(10) unsigned DEFAULT NULL COMMENT 'a stock item',
  `description` varchar(255) NOT NULL,
  `quantity` decimal(14,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(20) DEFAULT NULL,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `standard_quantity` decimal(14,2) DEFAULT NULL COMMENT 'what the rate card says, to spot a difference',
  `standard_amount` decimal(16,2) DEFAULT NULL,
  `payee_type` enum('driver','supplier','employee','company','other','none') NOT NULL DEFAULT 'none',
  `payee_id` int(10) unsigned DEFAULT NULL,
  `payee_name` varchar(150) DEFAULT NULL,
  `payment_method` enum('momo','cash','bank','none') NOT NULL DEFAULT 'none',
  `momo_number` varchar(40) DEFAULT NULL,
  `momo_name` varchar(150) DEFAULT NULL,
  `debit_account` varchar(20) DEFAULT NULL,
  `extra` text DEFAULT NULL COMMENT 'JSON: container no., size, tyre position, date...',
  `paid_amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `done_quantity` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'F4 issued / F5 received',
  `pay_status` enum('not_payable','unpaid','in_batch','paid','failed') NOT NULL DEFAULT 'not_payable',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lines_request` (`request_id`),
  KEY `idx_lines_trip` (`trip_id`),
  CONSTRAINT `fk_lines_request` FOREIGN KEY (`request_id`) REFERENCES `form_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_request_lines`
--

LOCK TABLES `form_request_lines` WRITE;
/*!40000 ALTER TABLE `form_request_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_request_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_requests`
--

DROP TABLE IF EXISTS `form_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `form_code` varchar(10) NOT NULL,
  `request_no` varchar(60) DEFAULT NULL COMMENT 'given when it is sent',
  `title` varchar(190) NOT NULL,
  `status` enum('draft','sent','verified','authorized','partly_paid','paid','returned','cancelled','closed') NOT NULL DEFAULT 'draft',
  `version` smallint(5) unsigned NOT NULL DEFAULT 1,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `total_amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `total_quantity` decimal(14,2) DEFAULT NULL COMMENT 'litres on a fuel order',
  `base_currency` char(3) DEFAULT NULL,
  `exchange_rate` decimal(18,8) DEFAULT NULL,
  `base_total` decimal(16,2) DEFAULT NULL,
  `route_id` int(10) unsigned DEFAULT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `vehicle_id` int(10) unsigned DEFAULT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `job_card_id` int(10) unsigned DEFAULT NULL,
  `parent_request_id` int(10) unsigned DEFAULT NULL COMMENT 'the PO behind an invoice, the F3 behind an F4',
  `to_company_id` int(10) unsigned DEFAULT NULL COMMENT 'a sister-company request goes to this company',
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `needed_on` date DEFAULT NULL,
  `non_standard_reason` varchar(255) DEFAULT NULL COMMENT 'why an amount differs from the rate card',
  `payload` text DEFAULT NULL COMMENT 'JSON: the fields particular to one form',
  `notes` text DEFAULT NULL,
  `prepared_by` int(10) unsigned DEFAULT NULL,
  `prepared_at` datetime DEFAULT NULL,
  `verified_by` int(10) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `authorized_by` int(10) unsigned DEFAULT NULL,
  `authorized_at` datetime DEFAULT NULL,
  `paid_by` int(10) unsigned DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `returned_reason` varchar(255) DEFAULT NULL,
  `waiting_since` datetime DEFAULT NULL COMMENT 'when the current step started, for reminders',
  `reminded_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_request_no` (`company_id`,`request_no`),
  KEY `idx_requests_company` (`company_id`,`status`),
  KEY `idx_requests_form` (`form_code`,`status`),
  KEY `idx_requests_trip` (`trip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_requests`
--

LOCK TABLES `form_requests` WRITE;
/*!40000 ALTER TABLE `form_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_types`
--

DROP TABLE IF EXISTS `form_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `form_code` varchar(10) NOT NULL,
  `form_name` varchar(120) NOT NULL,
  `title_on_pdf` varchar(120) NOT NULL COMMENT 'FUEL ORDER, TRIP MONEY REQUEST',
  `chain` enum('full','finance_md','om_confirm','store','supplier','none') NOT NULL DEFAULT 'full',
  `is_money` tinyint(1) NOT NULL DEFAULT 1,
  `goes_to_payment` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'appears on the Cashier''s To pay list once authorized',
  `posts_on_verify` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'the books post when Finance verifies (a PO only commits)',
  `number_format` varchar(80) NOT NULL DEFAULT '{SEQ}/{MONTH}/{YEAR}',
  `number_reset` enum('month','year','never') NOT NULL DEFAULT 'month',
  `debit_account` varchar(20) DEFAULT NULL COMMENT 'default expense or asset account',
  `credit_account` varchar(20) DEFAULT NULL COMMENT 'default payable',
  `raised_by_roles` varchar(255) DEFAULT NULL COMMENT 'comma-separated role keys',
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_form_code` (`form_code`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_types`
--

LOCK TABLES `form_types` WRITE;
/*!40000 ALTER TABLE `form_types` DISABLE KEYS */;
INSERT INTO `form_types` VALUES (1,'F1','Trip money: fuel, road toll, mileage','ROAD TOLL, FUEL AND MILEAGE REQUEST','full',1,1,1,'{SEQ}/{MONTHNAME}/{YEAR}','month',NULL,'2300','logistics_manager','Created with the trip; paid to the drivers before departure.',1,10,'2026-10-23 14:22:52'),(2,'F2','Fuel order','FUEL ORDER','full',1,0,0,'FUEL ORDER {SEQ}/{MONTHNAME}/{YEAR}','month','5000','2000','logistics_manager','Sent to the station or ERI Kenya; the fuel expense posts when their invoice is verified.',1,20,'2026-10-23 14:22:52'),(3,'F3','Service / repair request','SERVICE / REPAIR REQUEST','full',1,0,0,'SRV {SEQ}/{MONTHNAME}/{YEAR}','month','6000','2000','logistics_manager,workshop,driver','Opens a job card and a stores issue note.',1,30,'2026-10-23 14:22:52'),(4,'F4','Stores issue note','STORES ISSUE NOTE','store',0,0,0,'SIN {SEQ}/{YEAR}','month','6000','1200','logistics_manager,workshop,warehouse_manager','The storekeeper issues parts against it; stock goes down.',1,40,'2026-10-23 14:22:52'),(5,'F5','Purchase order','PURCHASE ORDER','full',1,0,0,'PO {SEQ}/{YEAR}','month','1200','2000','logistics_manager,warehouse_manager','A commitment: nothing posts until the goods are received.',1,50,'2026-10-23 14:22:52'),(6,'F6','Tyre change payment','CHANGING TYRES PAYMENT REQUEST','full',1,1,1,'TYR {SEQ}/{MONTHNAME}/{YEAR}','month','6050','2000','logistics_manager','From the tyre log.',1,60,'2026-10-23 14:22:52'),(7,'F7','Crane payment','CRANE PAYMENT REQUEST','full',1,1,1,'CRN {SEQ}/{MONTHNAME}/{YEAR}','month','5400','2000','logistics_manager','From the crane log.',1,70,'2026-10-23 14:22:52'),(8,'F8','Delivery note','DELIVERY NOTE','om_confirm',0,0,0,'DN {SEQ}/{YEAR}','month',NULL,NULL,'driver,logistics_manager','Filled by the driver at the customer.',1,80,'2026-10-23 14:22:52'),(9,'F9','Shortage deduction','SHORTAGE DEDUCTION','full',1,0,1,'SHD {SEQ}/{YEAR}','month','1160','5900','logistics_manager,finance','Goes on the driver\'s next payroll list.',1,90,'2026-10-23 14:22:52'),(10,'F10','Driver support','DRIVER SUPPORT REQUEST','full',1,1,1,'SUP {SEQ}/{MONTHNAME}/{YEAR}','month','5150','2300','driver,logistics_manager','Money follows the full chain and lands on the trip.',1,100,'2026-10-23 14:22:52'),(11,'F11','Sister-company request','SISTER-COMPANY REQUEST','om_confirm',0,0,0,'ICR {SEQ}/{YEAR}','month',NULL,NULL,'sister_supervisor','Creates the job card and issue note in the receiving company.',1,110,'2026-10-23 14:22:52'),(12,'F12','Supplier invoice','SUPPLIER INVOICE','full',1,1,1,'SINV {SEQ}/{YEAR}','month','6900','2000','supplier,finance,warehouse_manager','Matched to the PO and what was received.',1,120,'2026-10-23 14:22:52'),(13,'F13','Customer invoice','INVOICE','finance_md',1,0,1,'INV {SEQ}/{YEAR}','month','1100','4000','finance','Sent to the customer once authorized.',1,130,'2026-10-23 14:22:52'),(14,'F14','Vehicle handover','VEHICLE HANDOVER','om_confirm',0,0,0,'HO {SEQ}/{YEAR}','month',NULL,NULL,'driver','Ends the trip; the OM confirms.',1,140,'2026-10-23 14:22:52'),(15,'F15','Payment request (other)','PAYMENT REQUEST','full',1,1,1,'PR {SEQ}/{MONTHNAME}/{YEAR}','month','6900','2000','logistics_manager,finance,clearance_officer,shipping_clerk','Any other payment, including clearance charges and duties.',1,150,'2026-10-23 14:22:52');
/*!40000 ALTER TABLE `form_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `freight_jobs`
--

DROP TABLE IF EXISTS `freight_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `freight_jobs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `job_code` varchar(32) NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `request_id` int(10) unsigned DEFAULT NULL,
  `shipment_id` int(10) unsigned DEFAULT NULL,
  `direction` enum('import','export','transit') NOT NULL DEFAULT 'import',
  `mode` enum('sea','air','road') NOT NULL DEFAULT 'sea',
  `scope` enum('door_to_door','clearing_forwarding','clearing_only','freight_only','transport_only') NOT NULL DEFAULT 'clearing_forwarding',
  `load_type` enum('fcl','lcl','loose','units','out_of_gauge','project') NOT NULL DEFAULT 'fcl',
  `incoterm` varchar(10) DEFAULT NULL,
  `commodity` varchar(255) NOT NULL,
  `hs_code` varchar(20) DEFAULT NULL,
  `packages` int(10) unsigned DEFAULT NULL,
  `weight_kg` decimal(12,2) DEFAULT NULL,
  `volume_m3` decimal(12,3) DEFAULT NULL,
  `containers_count` smallint(5) unsigned DEFAULT NULL,
  `container_numbers` varchar(500) DEFAULT NULL,
  `consolidation_ref` varchar(60) DEFAULT NULL COMMENT 'the consolidation an LCL load travels in',
  `origin` varchar(150) DEFAULT NULL,
  `port_of_loading` varchar(120) DEFAULT NULL,
  `port_of_discharge` varchar(120) DEFAULT NULL,
  `final_destination` varchar(150) DEFAULT NULL,
  `carrier_id` int(10) unsigned DEFAULT NULL COMMENT 'shipping line or airline (suppliers)',
  `vessel_flight` varchar(120) DEFAULT NULL,
  `voyage_no` varchar(60) DEFAULT NULL,
  `booking_no` varchar(60) DEFAULT NULL,
  `master_bl` varchar(60) DEFAULT NULL COMMENT 'master bill of lading or air waybill',
  `house_bl` varchar(60) DEFAULT NULL,
  `idf_no` varchar(60) DEFAULT NULL COMMENT 'Kenya import declaration form',
  `entry_no` varchar(60) DEFAULT NULL COMMENT 'customs entry number',
  `kebs_coc_no` varchar(60) DEFAULT NULL,
  `etd` datetime DEFAULT NULL,
  `departed_at` datetime DEFAULT NULL,
  `eta` datetime DEFAULT NULL,
  `arrived_at` datetime DEFAULT NULL,
  `customs_at` datetime DEFAULT NULL,
  `cleared_at` datetime DEFAULT NULL,
  `inland_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `insured` tinyint(1) NOT NULL DEFAULT 0,
  `insurer` varchar(150) DEFAULT NULL,
  `insurance_cert_no` varchar(60) DEFAULT NULL,
  `insured_value` decimal(14,2) DEFAULT NULL,
  `premium` decimal(14,2) DEFAULT NULL,
  `insurance_currency` char(3) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `quoted_amount` decimal(14,2) DEFAULT NULL,
  `status` enum('booked','departed','arrived','customs','cleared','inland','delivered','closed','cancelled') NOT NULL DEFAULT 'booked',
  `notify_customer` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_freight_job_code` (`job_code`),
  KEY `idx_freight_job_status` (`status`),
  KEY `idx_freight_job_customer` (`customer_id`),
  KEY `idx_freight_job_eta` (`eta`),
  KEY `idx_freight_job_shipment` (`shipment_id`),
  KEY `idx_freight_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `freight_jobs`
--

LOCK TABLES `freight_jobs` WRITE;
/*!40000 ALTER TABLE `freight_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `freight_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fuel_orders`
--

DROP TABLE IF EXISTS `fuel_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fuel_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `order_code` varchar(60) DEFAULT NULL,
  `request_id` int(10) unsigned DEFAULT NULL COMMENT 'the F1 or F2 it came from',
  `trip_id` int(10) unsigned DEFAULT NULL,
  `vehicle_id` int(10) unsigned NOT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `fuel_point` varchar(120) DEFAULT NULL,
  `litres_ordered` decimal(10,2) NOT NULL,
  `standard_litres` decimal(10,2) DEFAULT NULL,
  `litres_delivered` decimal(10,2) DEFAULT NULL,
  `unit_price` decimal(12,4) DEFAULT NULL,
  `currency` char(3) DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `confirmed_by` int(10) unsigned DEFAULT NULL COMMENT 'the station login, or whoever recorded it',
  `status` enum('pending','ordered','delivered','invoiced','cancelled') NOT NULL DEFAULT 'pending',
  `supplier_invoice_id` int(10) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fuel_orders_trip` (`trip_id`),
  KEY `idx_fuel_orders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fuel_orders`
--

LOCK TABLES `fuel_orders` WRITE;
/*!40000 ALTER TABLE `fuel_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `fuel_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fuel_records`
--

DROP TABLE IF EXISTS `fuel_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fuel_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
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
  KEY `idx_fuel_company` (`company_id`),
  CONSTRAINT `fk_fuel_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fuel_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fuel_records_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fuel_records`
--

LOCK TABLES `fuel_records` WRITE;
/*!40000 ALTER TABLE `fuel_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `fuel_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gl_accounts`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gl_accounts`
--

LOCK TABLES `gl_accounts` WRITE;
/*!40000 ALTER TABLE `gl_accounts` DISABLE KEYS */;
INSERT INTO `gl_accounts` VALUES (1,'5500','Customs duty and levies','expense','debit',NULL,0,0,1,'Operating expenses',550,'operating',0,'Duty, VAT and levies paid at a border to release a load.',1,NULL,'2026-10-23 14:22:45'),(2,'5600','Clearing and forwarding fees','expense','debit',NULL,0,0,1,'Operating expenses',560,'operating',0,'What clearing agents charge to lodge and follow a declaration.',1,NULL,'2026-10-23 14:22:45'),(3,'5700','Border and transit charges','expense','debit',NULL,0,0,1,'Operating expenses',570,'operating',0,'Weighbridge, escort, parking and transit fees at a crossing.',1,NULL,'2026-10-23 14:22:45'),(4,'1150','Disbursements recoverable','asset','debit',NULL,0,0,0,'Other current assets',30,'operating',0,'Duty, port charges and fees paid on behalf of customers, waiting to be recharged.',1,NULL,'2026-10-23 14:22:47'),(5,'1160','Driver receivables (deductions)','asset','debit',NULL,0,0,0,'Current assets',20,'operating',0,NULL,1,NULL,'2026-10-23 14:22:52'),(6,'1170','Receivable from sister companies','asset','debit',NULL,0,0,0,'Current assets',21,'operating',0,NULL,1,NULL,'2026-10-23 14:22:52'),(7,'2300','Drivers payable','liability','credit',NULL,0,0,0,'Current liabilities',10,'operating',0,NULL,1,NULL,'2026-10-23 14:22:52'),(8,'2310','Payable to sister companies','liability','credit',NULL,0,0,0,'Current liabilities',11,'operating',0,NULL,1,NULL,'2026-10-23 14:22:52'),(9,'4200','Clearing and forwarding income','income','credit',NULL,0,0,0,'Revenue',20,'operating',0,NULL,1,NULL,'2026-10-23 14:22:52'),(10,'4300','Service income (sister companies)','income','credit',NULL,0,0,0,'Revenue',30,'operating',0,NULL,1,NULL,'2026-10-23 14:22:52'),(11,'5150','Driver support','cost_of_sales','debit',NULL,0,0,0,'Cost of sales',25,'operating',0,NULL,1,NULL,'2026-10-23 14:22:52'),(12,'5210','Road toll','cost_of_sales','debit',NULL,0,0,0,'Cost of sales',30,'operating',0,NULL,1,NULL,'2026-10-23 14:22:52'),(13,'5220','Mileage allowance','cost_of_sales','debit',NULL,0,0,0,'Cost of sales',31,'operating',0,NULL,1,NULL,'2026-10-23 14:22:52'),(14,'5900','Shortage loss','cost_of_sales','debit',NULL,0,0,0,'Cost of sales',60,'operating',0,NULL,1,NULL,'2026-10-23 14:22:52'),(15,'6050','Tyres','expense','debit',NULL,0,0,0,'Operating expenses',15,'operating',0,NULL,1,NULL,'2026-10-23 14:22:52'),(16,'6950','Exchange gains and losses','expense','debit',NULL,0,0,0,'Operating expenses',90,'operating',0,NULL,1,NULL,'2026-10-23 14:22:52'),(20,'3900','Opening balance equity','equity','credit',NULL,0,0,0,'Equity',90,'financing',0,NULL,1,NULL,'2026-10-23 14:22:52'),(21,'2050','Goods received not invoiced','liability','credit',NULL,0,0,0,'Accounts payable',50,'operating',0,'Stock received against a purchase order before the supplier invoice is verified.',1,NULL,'2026-10-23 14:22:53'),(22,'6960','Stock count differences','expense','debit',NULL,0,0,0,'Operating expenses',120,'operating',0,'Stock found short (or over) at a count, once the count is approved.',1,NULL,'2026-10-23 14:22:53'),(23,'1000','Cash and bank','asset','debit',NULL,1,0,0,'Cash and bank',10,'cash',0,'Every account the company can pay from.',1,NULL,'2026-10-23 14:22:53'),(24,'1010','Petty cash','asset','debit',23,0,0,1,'Cash and bank',10,'cash',1,'Cash held at the Kigali office.',1,NULL,'2026-10-23 14:22:53'),(25,'1020','Bank - main account','asset','debit',23,0,0,1,'Cash and bank',10,'cash',1,'The operating current account.',1,NULL,'2026-10-23 14:22:53'),(26,'1030','Mobile money','asset','debit',23,0,0,1,'Cash and bank',10,'cash',1,'Driver float and field payments.',1,NULL,'2026-10-23 14:22:53'),(27,'1100','Accounts receivable','asset','debit',NULL,0,0,0,'Accounts receivable',20,'operating',0,'Invoices issued to customers and not yet settled.',1,NULL,'2026-10-23 14:22:53'),(28,'1200','Inventory','asset','debit',NULL,0,0,0,'Other current assets',30,'operating',0,'Stock held in the warehouses.',1,NULL,'2026-10-23 14:22:53'),(29,'1250','Prepayments and deposits','asset','debit',NULL,0,0,0,'Other current assets',30,'operating',0,'Insurance and rent paid in advance.',1,NULL,'2026-10-23 14:22:53'),(30,'1500','Motor vehicles','asset','debit',NULL,0,0,0,'Fixed assets',40,'investing',0,'The fleet, at cost.',1,NULL,'2026-10-23 14:22:53'),(31,'1510','Equipment and fittings','asset','debit',NULL,0,0,0,'Fixed assets',40,'investing',0,'Warehouse and office equipment, at cost.',1,NULL,'2026-10-23 14:22:53'),(32,'1590','Accumulated depreciation','asset','credit',NULL,0,1,0,'Fixed assets',40,'investing',0,'Depreciation charged to date on the fleet and equipment.',1,NULL,'2026-10-23 14:22:53'),(33,'2000','Accounts payable','liability','credit',NULL,0,0,0,'Accounts payable',50,'operating',0,'Suppliers, workshops and fuel stations not yet paid.',1,NULL,'2026-10-23 14:22:53'),(34,'2100','VAT payable','liability','credit',NULL,0,0,0,'Other current liabilities',60,'operating',0,'VAT charged on invoices and owed to RRA.',1,NULL,'2026-10-23 14:22:53'),(35,'2150','Accrued expenses','liability','credit',NULL,0,0,0,'Other current liabilities',60,'operating',0,'Costs incurred but not yet invoiced by the supplier.',1,NULL,'2026-10-23 14:22:53'),(36,'2200','Payroll liabilities','liability','credit',NULL,0,0,0,'Other current liabilities',60,'operating',0,'Net pay, RSSB and PAYE owed.',1,NULL,'2026-10-23 14:22:53'),(37,'2500','Motor vehicle loans','liability','credit',NULL,0,0,0,'Long term liabilities',70,'financing',0,'Asset finance on the fleet.',1,NULL,'2026-10-23 14:22:53'),(38,'3000','Share capital','equity','credit',NULL,0,0,0,'Equity',80,'financing',0,'Capital introduced by the owners.',1,NULL,'2026-10-23 14:22:53'),(39,'3100','Retained earnings','equity','credit',NULL,0,0,0,'Equity',80,'financing',0,'Profit of earlier years kept in the business.',1,NULL,'2026-10-23 14:22:53'),(40,'3200','Drawings and dividends','equity','debit',NULL,0,0,0,'Equity',80,'financing',0,'Profit taken out by the owners.',1,NULL,'2026-10-23 14:22:53'),(41,'4000','Freight revenue','income','credit',NULL,0,0,0,'Income',100,'operating',0,'Transport invoiced to customers.',1,NULL,'2026-10-23 14:22:53'),(42,'4100','Warehousing and handling revenue','income','credit',NULL,0,0,0,'Income',100,'operating',0,'Storage, loading and handling charged out.',1,NULL,'2026-10-23 14:22:53'),(43,'4900','Other income','income','credit',NULL,0,0,0,'Other income',140,'operating',0,'Anything outside the main trade.',1,NULL,'2026-10-23 14:22:53'),(44,'5000','Fuel','cost_of_sales','debit',NULL,0,0,0,'Cost of sales',110,'operating',0,'Diesel and petrol drawn against trips.',1,NULL,'2026-10-23 14:22:53'),(45,'5100','Driver allowances','cost_of_sales','debit',NULL,0,0,0,'Cost of sales',110,'operating',0,'Night-out and per-diem paid to drivers.',1,NULL,'2026-10-23 14:22:53'),(46,'5200','Tolls, parking and permits','cost_of_sales','debit',NULL,0,0,0,'Cost of sales',110,'operating',0,'Road charges incurred on a trip.',1,NULL,'2026-10-23 14:22:53'),(47,'5300','Subcontracted transport','cost_of_sales','debit',NULL,0,0,0,'Cost of sales',110,'operating',0,'Work given to another carrier.',1,NULL,'2026-10-23 14:22:53'),(48,'5400','Loading and handling','cost_of_sales','debit',NULL,0,0,0,'Cost of sales',110,'operating',0,'Casual labour at the warehouse and at drop points.',1,NULL,'2026-10-23 14:22:53'),(49,'6000','Vehicle maintenance and repairs','expense','debit',NULL,0,0,0,'Operating expenses',120,'operating',0,'Servicing, parts and workshop labour.',1,NULL,'2026-10-23 14:22:53'),(50,'6100','Insurance','expense','debit',NULL,0,0,0,'Operating expenses',120,'operating',0,'Fleet and goods-in-transit cover.',1,NULL,'2026-10-23 14:22:53'),(51,'6200','Salaries and wages','expense','debit',NULL,0,0,0,'Operating expenses',120,'operating',0,'Staff pay other than driver allowances.',1,NULL,'2026-10-23 14:22:53'),(52,'6300','Office and administration','expense','debit',NULL,0,0,0,'Operating expenses',120,'operating',0,'Rent, communications, stationery and software.',1,NULL,'2026-10-23 14:22:53'),(53,'6400','Depreciation','expense','debit',NULL,0,0,0,'Operating expenses',120,'operating',0,'Wear charged against the fleet and equipment.',1,NULL,'2026-10-23 14:22:53'),(54,'6500','Bank charges','expense','debit',NULL,0,0,0,'Operating expenses',120,'operating',0,'Transfer fees and account charges.',1,NULL,'2026-10-23 14:22:53'),(55,'6900','Other expenses','expense','debit',NULL,0,0,0,'Other expenses',150,'operating',0,'Anything that does not belong above.',1,NULL,'2026-10-23 14:22:53');
/*!40000 ALTER TABLE `gl_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gl_budgets`
--

DROP TABLE IF EXISTS `gl_budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gl_budgets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
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
  KEY `idx_budgets_company` (`company_id`),
  CONSTRAINT `fk_budget_account` FOREIGN KEY (`account_id`) REFERENCES `gl_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gl_budgets`
--

LOCK TABLES `gl_budgets` WRITE;
/*!40000 ALTER TABLE `gl_budgets` DISABLE KEYS */;
/*!40000 ALTER TABLE `gl_budgets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gl_cheque_books`
--

DROP TABLE IF EXISTS `gl_cheque_books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gl_cheque_books` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
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
  KEY `idx_chequebooks_company` (`company_id`),
  CONSTRAINT `fk_cheque_book_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `gl_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gl_cheque_books`
--

LOCK TABLES `gl_cheque_books` WRITE;
/*!40000 ALTER TABLE `gl_cheque_books` DISABLE KEYS */;
/*!40000 ALTER TABLE `gl_cheque_books` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gl_cheque_lines`
--

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gl_cheque_lines`
--

LOCK TABLES `gl_cheque_lines` WRITE;
/*!40000 ALTER TABLE `gl_cheque_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `gl_cheque_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gl_cheques`
--

DROP TABLE IF EXISTS `gl_cheques`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gl_cheques` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
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
  KEY `idx_cheques_company` (`company_id`),
  CONSTRAINT `fk_cheque_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `gl_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gl_cheques`
--

LOCK TABLES `gl_cheques` WRITE;
/*!40000 ALTER TABLE `gl_cheques` DISABLE KEYS */;
/*!40000 ALTER TABLE `gl_cheques` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gl_fiscal_periods`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gl_fiscal_periods`
--

LOCK TABLES `gl_fiscal_periods` WRITE;
/*!40000 ALTER TABLE `gl_fiscal_periods` DISABLE KEYS */;
INSERT INTO `gl_fiscal_periods` VALUES (1,'2026-01','2026-01-01','2026-01-31','open',NULL,NULL),(2,'2026-02','2026-02-01','2026-02-28','open',NULL,NULL),(3,'2026-03','2026-03-01','2026-03-31','open',NULL,NULL),(4,'2026-04','2026-04-01','2026-04-30','open',NULL,NULL),(5,'2026-05','2026-05-01','2026-05-31','open',NULL,NULL),(6,'2026-06','2026-06-01','2026-06-30','open',NULL,NULL),(7,'2026-07','2026-07-01','2026-07-31','open',NULL,NULL),(8,'2026-08','2026-08-01','2026-08-31','open',NULL,NULL),(9,'2026-09','2026-09-01','2026-09-30','open',NULL,NULL),(10,'2026-10','2026-10-01','2026-10-31','open',NULL,NULL),(11,'2026-11','2026-11-01','2026-11-30','open',NULL,NULL),(12,'2026-12','2026-12-01','2026-12-31','open',NULL,NULL);
/*!40000 ALTER TABLE `gl_fiscal_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gl_journal_entries`
--

DROP TABLE IF EXISTS `gl_journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gl_journal_entries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `entry_no` varchar(32) NOT NULL,
  `entry_date` date NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `base_currency` char(3) DEFAULT NULL,
  `exchange_rate` decimal(18,8) DEFAULT NULL COMMENT 'one unit of the entry currency in the base currency; blank: no rate on file',
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
  KEY `idx_journal_company` (`company_id`),
  CONSTRAINT `gl_journal_entries_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gl_journal_entries`
--

LOCK TABLES `gl_journal_entries` WRITE;
/*!40000 ALTER TABLE `gl_journal_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `gl_journal_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gl_journal_lines`
--

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
  `base_debit` decimal(16,2) DEFAULT NULL,
  `base_credit` decimal(16,2) DEFAULT NULL,
  `vehicle_id` int(10) unsigned DEFAULT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `request_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gl_lines_account` (`account_id`),
  KEY `idx_gl_lines_entry` (`entry_id`,`line_no`),
  KEY `idx_lines_vehicle` (`vehicle_id`),
  KEY `idx_lines_trip` (`trip_id`),
  CONSTRAINT `gl_journal_lines_ibfk_1` FOREIGN KEY (`entry_id`) REFERENCES `gl_journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gl_journal_lines_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `gl_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gl_journal_lines`
--

LOCK TABLES `gl_journal_lines` WRITE;
/*!40000 ALTER TABLE `gl_journal_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `gl_journal_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `goods_receipt_lines`
--

DROP TABLE IF EXISTS `goods_receipt_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_receipt_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_id` int(10) unsigned NOT NULL,
  `request_line_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(14,2) NOT NULL,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `movement_code` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_goods_receipt_lines_receipt` (`receipt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `goods_receipt_lines`
--

LOCK TABLES `goods_receipt_lines` WRITE;
/*!40000 ALTER TABLE `goods_receipt_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `goods_receipt_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `goods_receipts`
--

DROP TABLE IF EXISTS `goods_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_receipts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `receipt_no` varchar(40) NOT NULL,
  `request_id` int(10) unsigned NOT NULL COMMENT 'the F5',
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `warehouse_id` int(10) unsigned DEFAULT NULL,
  `received_on` date NOT NULL,
  `delivery_ref` varchar(80) DEFAULT NULL COMMENT 'the supplier delivery note number',
  `currency` char(3) NOT NULL,
  `total_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `entry_id` int(10) unsigned DEFAULT NULL,
  `received_by` int(10) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_goods_receipts_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `goods_receipts`
--

LOCK TABLES `goods_receipts` WRITE;
/*!40000 ALTER TABLE `goods_receipts` DISABLE KEYS */;
/*!40000 ALTER TABLE `goods_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gps_providers`
--

DROP TABLE IF EXISTS `gps_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_providers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `provider_name` varchar(120) NOT NULL,
  `api_type` varchar(40) NOT NULL DEFAULT 'generic_json' COMMENT 'which adapter reads it',
  `base_url` varchar(255) DEFAULT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `api_username` varchar(120) DEFAULT NULL,
  `api_password` varchar(255) DEFAULT NULL,
  `poll_minutes` smallint(5) unsigned NOT NULL DEFAULT 5,
  `field_map` text DEFAULT NULL COMMENT 'JSON: where device, time, lat, lng, speed and odometer sit in the answer',
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `last_polled_at` datetime DEFAULT NULL,
  `last_error` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gps_providers`
--

LOCK TABLES `gps_providers` WRITE;
/*!40000 ALTER TABLE `gps_providers` DISABLE KEYS */;
/*!40000 ALTER TABLE `gps_providers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `intercompany_invoices`
--

DROP TABLE IF EXISTS `intercompany_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `intercompany_invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(40) NOT NULL,
  `from_company_id` int(10) unsigned NOT NULL COMMENT 'the company that did the work and is owed',
  `to_company_id` int(10) unsigned NOT NULL COMMENT 'the company that asked and owes',
  `request_id` int(10) unsigned DEFAULT NULL COMMENT 'the F11',
  `job_card_id` int(10) unsigned DEFAULT NULL,
  `vehicle_id` int(10) unsigned DEFAULT NULL COMMENT 'the sister truck',
  `currency` char(3) NOT NULL,
  `parts_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `labour_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('issued','paid','cancelled') NOT NULL DEFAULT 'issued',
  `from_entry_id` int(10) unsigned DEFAULT NULL COMMENT 'the receivable/income entry in the doing company',
  `to_entry_id` int(10) unsigned DEFAULT NULL COMMENT 'the expense/payable entry in the asking company',
  `to_bill_id` int(10) unsigned DEFAULT NULL COMMENT 'the supplier bill raised in the asking company',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ici_from` (`from_company_id`),
  KEY `idx_ici_to` (`to_company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `intercompany_invoices`
--

LOCK TABLES `intercompany_invoices` WRITE;
/*!40000 ALTER TABLE `intercompany_invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `intercompany_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_items`
--

DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
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
  KEY `idx_items_company` (`company_id`),
  CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_items`
--

LOCK TABLES `inventory_items` WRITE;
/*!40000 ALTER TABLE `inventory_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_lines`
--

DROP TABLE IF EXISTS `invoice_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `request_line_id` int(10) unsigned DEFAULT NULL,
  `shipment_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `line_kind` enum('service','disbursement') NOT NULL DEFAULT 'service',
  `disbursement_id` int(10) unsigned DEFAULT NULL,
  `line_total` decimal(14,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `shipment_id` (`shipment_id`),
  KEY `idx_invoice_lines_invoice` (`invoice_id`),
  CONSTRAINT `invoice_lines_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_lines_ibfk_2` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_lines`
--

LOCK TABLES `invoice_lines` WRITE;
/*!40000 ALTER TABLE `invoice_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `invoice_number` varchar(32) NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `journey_id` int(10) unsigned DEFAULT NULL,
  `request_id` int(10) unsigned DEFAULT NULL COMMENT 'the F13 it was made from',
  `lpo_number` varchar(60) DEFAULT NULL,
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
  `is_opening` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'an open balance brought over from Tally; already in the opening ledger',
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `customer_id` (`customer_id`),
  KEY `trip_id` (`trip_id`),
  KEY `issued_by` (`issued_by`),
  KEY `idx_invoices_status` (`status`),
  KEY `idx_invoices_issue` (`issue_date`),
  KEY `idx_invoices_deleted` (`deleted_at`),
  KEY `idx_invoices_company` (`company_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_drafts`
--

DROP TABLE IF EXISTS `journal_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_drafts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `draft_no` varchar(40) DEFAULT NULL,
  `entry_date` date NOT NULL,
  `memo` varchar(255) NOT NULL,
  `reference` varchar(60) DEFAULT NULL,
  `currency` char(3) NOT NULL,
  `lines_json` longtext NOT NULL COMMENT 'JSON lines: account, description, debit, credit',
  `total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('sent','verified','posted','refused','cancelled') NOT NULL DEFAULT 'sent',
  `prepared_by` int(10) unsigned DEFAULT NULL,
  `prepared_at` datetime NOT NULL DEFAULT current_timestamp(),
  `verified_by` int(10) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `authorized_by` int(10) unsigned DEFAULT NULL,
  `authorized_at` datetime DEFAULT NULL,
  `refused_by` int(10) unsigned DEFAULT NULL,
  `refused_at` datetime DEFAULT NULL,
  `refused_reason` varchar(255) DEFAULT NULL,
  `entry_id` int(10) unsigned DEFAULT NULL COMMENT 'the journal entry once posted',
  PRIMARY KEY (`id`),
  KEY `idx_journal_drafts_company` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_drafts`
--

LOCK TABLES `journal_drafts` WRITE;
/*!40000 ALTER TABLE `journal_drafts` DISABLE KEYS */;
/*!40000 ALTER TABLE `journal_drafts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journeys`
--

DROP TABLE IF EXISTS `journeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journeys` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `journey_code` varchar(32) NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `request_id` int(10) unsigned DEFAULT NULL,
  `origin` varchar(150) NOT NULL,
  `final_destination` varchar(150) NOT NULL,
  `route_via` varchar(255) DEFAULT NULL,
  `planned_start` date DEFAULT NULL,
  `planned_end` date DEFAULT NULL,
  `status` enum('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_journey` (`journey_code`),
  KEY `idx_journey_status` (`status`),
  KEY `fk_journey_customer` (`customer_id`),
  KEY `fk_journey_request` (`request_id`),
  KEY `idx_journeys_company` (`company_id`),
  CONSTRAINT `fk_journey_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_journey_request` FOREIGN KEY (`request_id`) REFERENCES `transport_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journeys`
--

LOCK TABLES `journeys` WRITE;
/*!40000 ALTER TABLE `journeys` DISABLE KEYS */;
/*!40000 ALTER TABLE `journeys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ledger_mappings`
--

DROP TABLE IF EXISTS `ledger_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ledger_mappings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL COMMENT 'blank: the whole group',
  `item_code` varchar(40) NOT NULL,
  `item_label` varchar(120) NOT NULL,
  `debit_account` varchar(20) NOT NULL,
  `credit_account` varchar(20) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mapping` (`company_id`,`item_code`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ledger_mappings`
--

LOCK TABLES `ledger_mappings` WRITE;
/*!40000 ALTER TABLE `ledger_mappings` DISABLE KEYS */;
INSERT INTO `ledger_mappings` VALUES (1,NULL,'road_toll','Road toll (F1)','5210','2300',NULL,'2026-10-23 14:22:52'),(2,NULL,'mileage','Mileage (F1)','5220','2300',NULL,'2026-10-23 14:22:52'),(3,NULL,'fuel','Fuel','5000','2000',NULL,'2026-10-23 14:22:52'),(4,NULL,'part','Spare parts issued','6000','1200',NULL,'2026-10-23 14:22:52'),(5,NULL,'tyre','Tyre change','6050','2000',NULL,'2026-10-23 14:22:52'),(6,NULL,'crane','Crane lift','5400','2000',NULL,'2026-10-23 14:22:52'),(7,NULL,'labour','Garage labour','6000','2000',NULL,'2026-10-23 14:22:52'),(8,NULL,'service','Service / repair','6000','2000',NULL,'2026-10-23 14:22:52'),(9,NULL,'support','Driver support','5150','2300',NULL,'2026-10-23 14:22:52'),(10,NULL,'shortage','Cargo shortage (driver)','1160','5900',NULL,'2026-10-23 14:22:52'),(11,NULL,'duty','Duties and taxes for a client','1150','2000',NULL,'2026-10-23 14:22:52'),(12,NULL,'shipping','Shipping line charges','1150','2000',NULL,'2026-10-23 14:22:52'),(13,NULL,'other','Anything else','6900','2000',NULL,'2026-10-23 14:22:52'),(14,NULL,'goods_received','Goods received into store (against F5)','1200','2050','Cleared by the supplier invoice (F12).','2026-10-23 14:22:53'),(15,NULL,'part_issued','Parts issued to a truck (F4)','6000','1200','Charged to the truck on the job card.','2026-10-23 14:22:53'),(16,NULL,'tyre_issued','Tyres issued to a truck (F4)','6050','1200','Charged to the truck on the job card.','2026-10-23 14:22:53'),(17,NULL,'stock_count','Stock count difference','6960','1200','A loss debits the first account; a gain reverses it.','2026-10-23 14:22:53'),(21,NULL,'transport','Transport invoiced (F13)','1100','4000','Customer receivable and freight revenue.','2026-10-23 14:22:53'),(22,NULL,'deduction','Shortage deducted by the customer (F13)','5900','1100','The customer pays less; recovered from the driver through F9.','2026-10-23 14:22:53'),(23,NULL,'vat','VAT on a customer invoice (F13)','1100','2100',NULL,'2026-10-23 14:22:53'),(24,NULL,'payroll_deduction','Deduction taken on the payroll','2200','1160','Clears the driver receivable when the payroll is closed.','2026-10-23 14:22:53');
/*!40000 ALTER TABLE `ledger_mappings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lookup_values`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=207 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lookup_values`
--

LOCK TABLES `lookup_values` WRITE;
/*!40000 ALTER TABLE `lookup_values` DISABLE KEYS */;
INSERT INTO `lookup_values` VALUES (1,'vehicle_type','Delivery truck','Delivery truck',10,1,NULL,NULL,'2026-10-23 14:22:44'),(2,'vehicle_type','Box truck','Box truck',20,1,NULL,NULL,'2026-10-23 14:22:44'),(3,'vehicle_type','Refrigerated truck','Refrigerated truck',30,1,NULL,NULL,'2026-10-23 14:22:44'),(4,'vehicle_type','Pickup','Pickup',40,1,NULL,NULL,'2026-10-23 14:22:44'),(5,'vehicle_type','Van','Van',50,1,NULL,NULL,'2026-10-23 14:22:44'),(6,'vehicle_type','Tanker','Tanker',60,1,NULL,NULL,'2026-10-23 14:22:44'),(7,'vehicle_type','Trailer','Trailer',70,1,NULL,NULL,'2026-10-23 14:22:44'),(8,'vehicle_type','Motorcycle','Motorcycle',80,1,NULL,NULL,'2026-10-23 14:22:44'),(9,'licence_class','A','A',10,1,NULL,NULL,'2026-10-23 14:22:44'),(10,'licence_class','B','B',20,1,NULL,NULL,'2026-10-23 14:22:44'),(11,'licence_class','C','C',30,1,NULL,NULL,'2026-10-23 14:22:44'),(12,'licence_class','D','D',40,1,NULL,NULL,'2026-10-23 14:22:44'),(13,'licence_class','E','E',50,1,NULL,NULL,'2026-10-23 14:22:44'),(14,'licence_class','B/C','B/C',60,1,NULL,NULL,'2026-10-23 14:22:44'),(15,'licence_class','C/D','C/D',70,1,NULL,NULL,'2026-10-23 14:22:44'),(16,'expense_category','Fuel','Fuel',10,1,NULL,NULL,'2026-10-23 14:22:44'),(17,'expense_category','Toll','Toll',20,1,NULL,NULL,'2026-10-23 14:22:44'),(18,'expense_category','Repair','Repair',30,1,NULL,NULL,'2026-10-23 14:22:44'),(19,'expense_category','Allowance','Allowance',40,1,NULL,NULL,'2026-10-23 14:22:44'),(20,'expense_category','Parking','Parking',50,1,NULL,NULL,'2026-10-23 14:22:44'),(21,'expense_category','Insurance','Insurance',60,1,NULL,NULL,'2026-10-23 14:22:44'),(22,'expense_category','Loading','Loading',70,1,NULL,NULL,'2026-10-23 14:22:44'),(23,'expense_category','Permit','Permit',80,1,NULL,NULL,'2026-10-23 14:22:44'),(24,'item_category','Food','Food',10,1,NULL,NULL,'2026-10-23 14:22:44'),(25,'item_category','Packaging','Packaging',20,1,NULL,NULL,'2026-10-23 14:22:44'),(26,'item_category','Spare parts','Spare parts',30,1,NULL,NULL,'2026-10-23 14:22:44'),(27,'item_category','Consumables','Consumables',40,1,NULL,NULL,'2026-10-23 14:22:44'),(28,'item_category','Cold chain','Cold chain',50,1,NULL,NULL,'2026-10-23 14:22:44'),(29,'item_category','Equipment','Equipment',60,1,NULL,NULL,'2026-10-23 14:22:44'),(30,'item_category','Stationery','Stationery',70,1,NULL,NULL,'2026-10-23 14:22:44'),(31,'unit_of_measure','Unit','Unit',10,1,NULL,NULL,'2026-10-23 14:22:44'),(32,'unit_of_measure','Kg','Kg',20,1,NULL,NULL,'2026-10-23 14:22:44'),(33,'unit_of_measure','Litre','Litre',30,1,NULL,NULL,'2026-10-23 14:22:44'),(34,'unit_of_measure','Box','Box',40,1,NULL,NULL,'2026-10-23 14:22:44'),(35,'unit_of_measure','Sack','Sack',50,1,NULL,NULL,'2026-10-23 14:22:44'),(36,'unit_of_measure','Crate','Crate',60,1,NULL,NULL,'2026-10-23 14:22:44'),(37,'unit_of_measure','Pallet','Pallet',70,1,NULL,NULL,'2026-10-23 14:22:44'),(38,'unit_of_measure','Carton','Carton',80,1,NULL,NULL,'2026-10-23 14:22:44'),(39,'supplier_category','Spare parts','Spare parts',10,1,NULL,NULL,'2026-10-23 14:22:44'),(40,'supplier_category','Fuel','Fuel',20,1,NULL,NULL,'2026-10-23 14:22:44'),(41,'supplier_category','Packaging','Packaging',30,1,NULL,NULL,'2026-10-23 14:22:44'),(42,'supplier_category','Cold chain','Cold chain',40,1,NULL,NULL,'2026-10-23 14:22:44'),(43,'supplier_category','Equipment','Equipment',50,1,NULL,NULL,'2026-10-23 14:22:44'),(44,'supplier_category','Services','Services',60,1,NULL,NULL,'2026-10-23 14:22:44'),(45,'supplier_category','Consumables','Consumables',70,1,NULL,NULL,'2026-10-23 14:22:44'),(46,'procurement_category','Spare parts','Spare parts',10,1,NULL,NULL,'2026-10-23 14:22:44'),(47,'procurement_category','Fuel','Fuel',20,1,NULL,NULL,'2026-10-23 14:22:44'),(48,'procurement_category','Packaging','Packaging',30,1,NULL,NULL,'2026-10-23 14:22:44'),(49,'procurement_category','Cold chain','Cold chain',40,1,NULL,NULL,'2026-10-23 14:22:44'),(50,'procurement_category','Equipment','Equipment',50,1,NULL,NULL,'2026-10-23 14:22:44'),(51,'procurement_category','Services','Services',60,1,NULL,NULL,'2026-10-23 14:22:44'),(52,'procurement_category','Food','Food',70,1,NULL,NULL,'2026-10-23 14:22:44'),(53,'department','Operations','Operations',10,1,NULL,NULL,'2026-10-23 14:22:44'),(54,'department','Fleet','Fleet',20,1,NULL,NULL,'2026-10-23 14:22:44'),(55,'department','Warehouse','Warehouse',30,1,NULL,NULL,'2026-10-23 14:22:44'),(56,'department','Finance','Finance',40,1,NULL,NULL,'2026-10-23 14:22:44'),(57,'department','Management','Management',50,1,NULL,NULL,'2026-10-23 14:22:44'),(58,'department','Administration','Administration',60,1,NULL,NULL,'2026-10-23 14:22:44'),(59,'report_period','Daily','Daily',10,1,NULL,NULL,'2026-10-23 14:22:44'),(60,'report_period','Weekly','Weekly',20,1,NULL,NULL,'2026-10-23 14:22:44'),(61,'report_period','Monthly','Monthly',30,1,NULL,NULL,'2026-10-23 14:22:44'),(62,'report_period','Quarterly','Quarterly',40,1,NULL,NULL,'2026-10-23 14:22:44'),(63,'report_period','Yearly','Yearly',50,1,NULL,NULL,'2026-10-23 14:22:44'),(64,'border_charge','Customs duty','Customs duty',10,1,NULL,NULL,'2026-10-23 14:22:45'),(65,'border_charge','Import VAT','Import VAT',20,1,NULL,NULL,'2026-10-23 14:22:45'),(66,'border_charge','Withholding tax','Withholding tax',30,1,NULL,NULL,'2026-10-23 14:22:45'),(67,'border_charge','Clearing agent fee','Clearing agent fee',40,1,NULL,NULL,'2026-10-23 14:22:45'),(68,'border_charge','Transit bond','Transit bond',50,1,NULL,NULL,'2026-10-23 14:22:45'),(69,'border_charge','Weighbridge','Weighbridge',60,1,NULL,NULL,'2026-10-23 14:22:45'),(70,'border_charge','Escort fee','Escort fee',70,1,NULL,NULL,'2026-10-23 14:22:45'),(71,'border_charge','Parking and storage','Parking and storage',80,1,NULL,NULL,'2026-10-23 14:22:45'),(72,'border_charge','Road toll','Road toll',90,1,NULL,NULL,'2026-10-23 14:22:45'),(73,'border_charge','Other border charge','Other border charge',100,1,NULL,NULL,'2026-10-23 14:22:45'),(74,'border_document','Customs declaration','Customs declaration',10,1,NULL,NULL,'2026-10-23 14:22:45'),(75,'border_document','T1 transit bond','T1 transit bond',20,1,NULL,NULL,'2026-10-23 14:22:45'),(76,'border_document','Commercial invoice','Commercial invoice',30,1,NULL,NULL,'2026-10-23 14:22:45'),(77,'border_document','Packing list','Packing list',40,1,NULL,NULL,'2026-10-23 14:22:45'),(78,'border_document','Certificate of origin','Certificate of origin',50,1,NULL,NULL,'2026-10-23 14:22:45'),(79,'border_document','Bill of lading','Bill of lading',60,1,NULL,NULL,'2026-10-23 14:22:45'),(80,'border_document','Weighbridge ticket','Weighbridge ticket',70,1,NULL,NULL,'2026-10-23 14:22:45'),(81,'border_document','COMESA yellow card','COMESA yellow card',80,1,NULL,NULL,'2026-10-23 14:22:45'),(82,'border_document','Road transit permit','Road transit permit',90,1,NULL,NULL,'2026-10-23 14:22:45'),(83,'border_document','Phytosanitary certificate','Phytosanitary certificate',100,1,NULL,NULL,'2026-10-23 14:22:45'),(84,'border_document','Driver passport','Driver passport',110,1,NULL,NULL,'2026-10-23 14:22:45'),(85,'border_document','Air waybill','Air waybill',115,1,NULL,NULL,'2026-10-23 14:22:47'),(86,'border_document','Import permit','Import permit',120,1,NULL,NULL,'2026-10-23 14:22:47'),(87,'border_document','Export permit','Export permit',125,1,NULL,NULL,'2026-10-23 14:22:47'),(88,'border_document','Insurance certificate','Insurance certificate',130,1,NULL,NULL,'2026-10-23 14:22:47'),(89,'border_document','Other','Other',999,1,NULL,NULL,'2026-10-23 14:22:47'),(90,'procurement_category','Clearing agent','Clearing agent',65,1,NULL,NULL,'2026-10-23 14:22:48'),(91,'procurement_category','Shipping line','Shipping line',66,1,NULL,NULL,'2026-10-23 14:22:48'),(92,'border_document','Export declaration','Export declaration',12,1,NULL,NULL,'2026-10-23 14:22:48'),(93,'border_document','Import declaration','Import declaration',14,1,NULL,NULL,'2026-10-23 14:22:48'),(94,'border_document','Regional transit guarantee (RCTG)','Regional transit guarantee (RCTG)',22,1,NULL,NULL,'2026-10-23 14:22:48'),(95,'border_document','Electronic cargo tracking seal','Electronic cargo tracking seal',24,1,NULL,NULL,'2026-10-23 14:22:48'),(96,'border_document','Delivery order','Delivery order',65,1,NULL,NULL,'2026-10-23 14:22:48'),(97,'border_document','Tax payment receipt','Tax payment receipt',120,1,NULL,NULL,'2026-10-23 14:22:48'),(98,'border_document','Customs release order','Customs release order',130,1,NULL,NULL,'2026-10-23 14:22:48'),(100,'vehicle_type','Prime mover','Prime mover',100,1,NULL,NULL,'2026-10-23 14:22:49'),(101,'vehicle_type','Skeletal trailer','Skeletal trailer',110,1,NULL,NULL,'2026-10-23 14:22:49'),(102,'vehicle_type','Flatbed trailer','Flatbed trailer',120,1,NULL,NULL,'2026-10-23 14:22:49'),(103,'vehicle_type','Low bed','Low bed',130,1,NULL,NULL,'2026-10-23 14:22:49'),(104,'vehicle_type','Car carrier','Car carrier',140,1,NULL,NULL,'2026-10-23 14:22:49'),(105,'supplier_category','Shipping line','Shipping line',100,1,NULL,NULL,'2026-10-23 14:22:49'),(106,'supplier_category','Airline','Airline',110,1,NULL,NULL,'2026-10-23 14:22:49'),(107,'supplier_category','Clearing agent','Clearing agent',120,1,NULL,NULL,'2026-10-23 14:22:49'),(108,'supplier_category','Insurer','Insurer',130,1,NULL,NULL,'2026-10-23 14:22:49'),(109,'supplier_category','Container freight station','Container freight station',140,1,NULL,NULL,'2026-10-23 14:22:49'),(110,'border_document','Import Declaration Form (IDF)','Import Declaration Form (IDF)',13,1,NULL,NULL,'2026-10-23 14:22:49'),(111,'border_document','KEBS certificate of conformity','KEBS certificate of conformity',96,1,NULL,NULL,'2026-10-23 14:22:49'),(112,'border_document','Port charges receipt (KPA)','Port charges receipt (KPA)',125,1,NULL,NULL,'2026-10-23 14:22:49'),(113,'border_charge','Port charges','Port charges',85,1,NULL,NULL,'2026-10-23 14:22:49'),(114,'border_charge','Shipping line charges','Shipping line charges',86,1,NULL,NULL,'2026-10-23 14:22:49'),(115,'border_charge','CFS charges','CFS charges',87,1,NULL,NULL,'2026-10-23 14:22:49'),(119,'vehicle_type','Truck','Truck',30,1,NULL,NULL,'2026-10-23 14:22:53'),(159,'supplier_category','Garage','Garage',30,1,NULL,NULL,'2026-10-23 14:22:53'),(160,'supplier_category','Tyres','Tyres',40,1,NULL,NULL,'2026-10-23 14:22:53'),(161,'supplier_category','Crane','Crane',50,1,NULL,NULL,'2026-10-23 14:22:53'),(162,'supplier_category','Sister company','Sister company',60,1,NULL,NULL,'2026-10-23 14:22:53');
/*!40000 ALTER TABLE `lookup_values` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maintenance_orders`
--

DROP TABLE IF EXISTS `maintenance_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maintenance_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `work_order_code` varchar(32) NOT NULL,
  `vehicle_id` int(10) unsigned NOT NULL,
  `request_id` int(10) unsigned DEFAULT NULL COMMENT 'the F3 (or F11) it came from',
  `trailer_id` int(10) unsigned DEFAULT NULL,
  `owner_company_id` int(10) unsigned DEFAULT NULL COMMENT 'the company that owns the truck, when it is a sister company',
  `flow` enum('legacy','eri') NOT NULL DEFAULT 'eri' COMMENT 'eri: parts post when issued, outside work through F12',
  `service_name` varchar(150) NOT NULL,
  `maintenance_type` enum('preventive','corrective','inspection','tyre','bodywork','emergency') NOT NULL DEFAULT 'preventive',
  `workshop` enum('in_house','external') NOT NULL DEFAULT 'external',
  `provider_name` varchar(150) DEFAULT NULL,
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `mechanic_id` int(10) unsigned DEFAULT NULL,
  `reported_by_driver_id` int(10) unsigned DEFAULT NULL,
  `complaint` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `work_done` text DEFAULT NULL,
  `labour_hours` decimal(8,2) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `estimated_cost` decimal(12,2) DEFAULT NULL,
  `actual_cost` decimal(12,2) DEFAULT NULL,
  `odometer_reading` int(10) unsigned DEFAULT NULL,
  `next_service_km` int(10) unsigned DEFAULT NULL,
  `next_service_date` date DEFAULT NULL,
  `status` enum('open','scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
  `started_at` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `closed_by` int(10) unsigned DEFAULT NULL,
  `stock_issued_at` datetime DEFAULT NULL,
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
  KEY `idx_maintenance_company` (`company_id`),
  CONSTRAINT `maintenance_orders_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance_orders`
--

LOCK TABLES `maintenance_orders` WRITE;
/*!40000 ALTER TABLE `maintenance_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `maintenance_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maintenance_parts`
--

DROP TABLE IF EXISTS `maintenance_parts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maintenance_parts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `maintenance_id` int(10) unsigned NOT NULL,
  `line_type` enum('part','labour','service','consumable') NOT NULL DEFAULT 'part',
  `item_id` int(10) unsigned DEFAULT NULL,
  `request_line_id` int(10) unsigned DEFAULT NULL COMMENT 'the F4 line it was issued against',
  `movement_code` varchar(40) DEFAULT NULL,
  `issued_at` datetime DEFAULT NULL,
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

--
-- Dumping data for table `maintenance_parts`
--

LOCK TABLES `maintenance_parts` WRITE;
/*!40000 ALTER TABLE `maintenance_parts` DISABLE KEYS */;
/*!40000 ALTER TABLE `maintenance_parts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(190) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'20260918_000001_extend_logistics_schema.sql','2026-10-23 14:22:42'),(2,'20260918_000002_add_notifications.sql','2026-10-23 14:22:42'),(3,'20260919_000003_link_drivers_to_users.sql','2026-10-23 14:22:42'),(4,'20260919_000004_add_user_privilege.sql','2026-10-23 14:22:42'),(5,'20260920_000005_core_depth.sql','2026-10-23 14:22:43'),(6,'20260920_000006_billing_access_security.sql','2026-10-23 14:22:44'),(7,'20260920_000007_role_permission_matrix.sql','2026-10-23 14:22:44'),(8,'20260920_000008_driver_can_close_delivery.sql','2026-10-23 14:22:44'),(9,'20260920_000009_accounting_books.sql','2026-10-23 14:22:44'),(10,'20260921_000010_email_delivery.sql','2026-10-23 14:22:44'),(11,'20260921_000011_lookup_lists.sql','2026-10-23 14:22:44'),(12,'20260921_000012_warehouses_permission.sql','2026-10-23 14:22:44'),(13,'20260921_000013_repair_orphan_assigned_requests.sql','2026-10-23 14:22:44'),(14,'20260921_000014_stock_opening_movements.sql','2026-10-23 14:22:44'),(15,'20260921_000015_payment_methods.sql','2026-10-23 14:22:44'),(16,'20260921_000016_payment_method_details.sql','2026-10-23 14:22:44'),(17,'20260921_000017_vendor_branding.sql','2026-10-23 14:22:44'),(18,'20260922_000018_cheques_and_budget.sql','2026-10-23 14:22:45'),(19,'20260922_000018_remove_vendor_setting.sql','2026-10-23 14:22:45'),(20,'20260922_000019_currencies.sql','2026-10-23 14:22:45'),(21,'20260922_000020_border_clearance.sql','2026-10-23 14:22:45'),(22,'20260922_000021_rate_by_weight.sql','2026-10-23 14:22:45'),(23,'20260922_000022_warehouse_routing.sql','2026-10-23 14:22:46'),(24,'20260922_000023_request_quotes.sql','2026-10-23 14:22:46'),(25,'20260922_000024_currency_everywhere.sql','2026-10-23 14:22:46'),(26,'20260922_000025_request_contact.sql','2026-10-23 14:22:46'),(27,'20260922_000026_cargo_custody.sql','2026-10-23 14:22:46'),(28,'20260922_000027_shipment_from_stock.sql','2026-10-23 14:22:46'),(29,'20260922_000028_books_per_currency.sql','2026-10-23 14:22:46'),(30,'20260923_000029_profile_photo.sql','2026-10-23 14:22:46'),(31,'20260925_000030_customs_clearance_workflow.sql','2026-10-23 14:22:47'),(32,'20260925_000031_pre_dispatch_document_pack.sql','2026-10-23 14:22:47'),(33,'20261001_000032_client_meeting_features.sql','2026-10-23 14:22:47'),(34,'20261001_000033_roles_for_cargo_flow.sql','2026-10-23 14:22:47'),(35,'20261002_000034_driver_papers.sql','2026-10-23 14:22:47'),(36,'20261002_000035_payables_disbursements_bank.sql','2026-10-23 14:22:47'),(37,'20261002_000036_storage_and_containers.sql','2026-10-23 14:22:48'),(38,'20261002_000037_workshop_and_journeys.sql','2026-10-23 14:22:48'),(39,'20261002_000038_payment_method_accounts.sql','2026-10-23 14:22:48'),(40,'20261003_000039_price_offers.sql','2026-10-23 14:22:48'),(41,'20261003_000040_email_attachments.sql','2026-10-23 14:22:48'),(42,'20261003_000041_driver_road.sql','2026-10-23 14:22:48'),(43,'20261005_000042_customer_border_charges.sql','2026-10-23 14:22:48'),(44,'20261005_000043_payment_method_opening.sql','2026-10-23 14:22:48'),(45,'20261015_000044_go_live_controls.sql','2026-10-23 14:22:48'),(46,'20261016_000045_freight_forwarding.sql','2026-10-23 14:22:49'),(47,'20261022_000046_eri_group_foundation.sql','2026-10-23 14:22:51'),(48,'20261022_000047_eri_fleet_and_drivers.sql','2026-10-23 14:22:51'),(49,'20261022_000048_eri_routes_requests_payments.sql','2026-10-23 14:22:52'),(50,'20261022_000049_eri_trips_fuel_handover.sql','2026-10-23 14:22:52'),(51,'20261022_000051_eri_whatsapp.sql','2026-10-23 14:22:52'),(52,'20261022_000052_eri_audit_opening.sql','2026-10-23 14:22:52'),(53,'20261023_000053_eri_journal_approval.sql','2026-10-23 14:22:52'),(54,'20261023_000054_eri_garage_store.sql','2026-10-23 14:22:53'),(55,'20261023_000055_eri_billing_shortage_payroll.sql','2026-10-23 14:22:53'),(56,'20261023_000056_eri_sister_requests.sql','2026-10-23 14:22:53'),(57,'20261023_000057_eri_roles_simplify.sql','2026-10-23 14:22:53'),(58,'99991231_000000_eri_permission_matrix_378db5ca.sql','2026-10-23 14:22:53');
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_reads`
--

DROP TABLE IF EXISTS `notification_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_reads` (
  `notification_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `read_at` datetime NOT NULL,
  PRIMARY KEY (`notification_id`,`user_id`),
  KEY `idx_notification_reads_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_reads`
--

LOCK TABLES `notification_reads` WRITE;
/*!40000 ALTER TABLE `notification_reads` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_reads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opening_balances`
--

DROP TABLE IF EXISTS `opening_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `opening_balances` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `as_of` date NOT NULL COMMENT 'the go-live date; balances are as at the day before',
  `kind` enum('ledger','customers','suppliers') NOT NULL DEFAULT 'ledger',
  `rows_json` longtext NOT NULL COMMENT 'JSON rows as loaded',
  `status` enum('draft','checked','signed') NOT NULL DEFAULT 'draft',
  `prepared_by` int(10) unsigned DEFAULT NULL,
  `checked_by` int(10) unsigned DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  `signed_by` int(10) unsigned DEFAULT NULL,
  `signed_at` datetime DEFAULT NULL,
  `entry_ids` varchar(255) DEFAULT NULL COMMENT 'the journal entries posted at sign-off',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_opening_company` (`company_id`,`kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opening_balances`
--

LOCK TABLES `opening_balances` WRITE;
/*!40000 ALTER TABLE `opening_balances` DISABLE KEYS */;
/*!40000 ALTER TABLE `opening_balances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

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

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_batch_lines`
--

DROP TABLE IF EXISTS `payment_batch_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_batch_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` int(10) unsigned NOT NULL,
  `request_id` int(10) unsigned NOT NULL,
  `payee_type` varchar(20) NOT NULL,
  `payee_id` int(10) unsigned DEFAULT NULL,
  `payee_name` varchar(150) NOT NULL,
  `momo_number` varchar(40) DEFAULT NULL,
  `momo_name` varchar(150) DEFAULT NULL,
  `amount` decimal(16,2) NOT NULL,
  `currency` char(3) NOT NULL,
  `status` enum('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `failure_reason` varchar(255) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `receipt_no` varchar(40) DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL COMMENT 'the payee said the money arrived',
  `confirmed_via` enum('app','pin','signature') DEFAULT NULL,
  `confirmation_signature` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_batch_lines` (`batch_id`),
  KEY `idx_batch_request` (`request_id`),
  CONSTRAINT `fk_batch_lines` FOREIGN KEY (`batch_id`) REFERENCES `payment_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_batch_lines`
--

LOCK TABLES `payment_batch_lines` WRITE;
/*!40000 ALTER TABLE `payment_batch_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_batch_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_batches`
--

DROP TABLE IF EXISTS `payment_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `batch_no` varchar(40) NOT NULL COMMENT 'BATCH-2026-10-031',
  `method` enum('momo','cash','bank') NOT NULL DEFAULT 'momo',
  `payment_method_id` int(10) unsigned DEFAULT NULL COMMENT 'the MoMo account, cash box or bank account it leaves',
  `currency` char(3) NOT NULL,
  `total_amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `line_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `status` enum('built','recorded','partly_failed','cancelled') NOT NULL DEFAULT 'built',
  `mtn_batch_no` varchar(80) DEFAULT NULL,
  `proof_file` varchar(255) DEFAULT NULL,
  `built_by` int(10) unsigned DEFAULT NULL,
  `built_at` datetime DEFAULT NULL,
  `recorded_by` int(10) unsigned DEFAULT NULL,
  `recorded_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batch_no` (`company_id`,`batch_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_batches`
--

LOCK TABLES `payment_batches` WRITE;
/*!40000 ALTER TABLE `payment_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_methods`
--

DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_methods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL COMMENT 'blank: used by every company',
  `method_key` varchar(40) NOT NULL,
  `method_name` varchar(80) NOT NULL,
  `gl_account_id` int(10) unsigned DEFAULT NULL,
  `currency` char(3) DEFAULT NULL,
  `opening_balance` decimal(16,2) DEFAULT NULL,
  `opening_date` date DEFAULT NULL,
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
  KEY `idx_methods_company` (`company_id`),
  CONSTRAINT `fk_payment_method_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_methods`
--

LOCK TABLES `payment_methods` WRITE;
/*!40000 ALTER TABLE `payment_methods` DISABLE KEYS */;
INSERT INTO `payment_methods` VALUES (1,NULL,'bank_transfer','Bank transfer',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Account name and number go here','both',1,1,10,NULL,'2026-10-23 14:22:44'),(2,NULL,'mobile_money','Mobile money',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'both',1,1,20,NULL,'2026-10-23 14:22:44'),(3,NULL,'cash','Cash',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'both',1,1,30,NULL,'2026-10-23 14:22:44'),(4,NULL,'cheque','Cheque',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'both',1,1,40,NULL,'2026-10-23 14:22:44'),(5,NULL,'card','Card',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'both',0,1,50,NULL,'2026-10-23 14:22:44'),(6,NULL,'fuel_card','Fuel card',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'out',0,1,60,NULL,'2026-10-23 14:22:44');
/*!40000 ALTER TABLE `payment_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `payment_code` varchar(32) NOT NULL,
  `invoice_id` int(10) unsigned NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `exchange_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `base_amount` decimal(14,2) DEFAULT NULL,
  `method` varchar(40) NOT NULL DEFAULT 'bank_transfer',
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
  KEY `idx_payments_company` (`company_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_runs`
--

DROP TABLE IF EXISTS `payroll_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_runs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `period` char(7) NOT NULL COMMENT 'YYYY-MM',
  `status` enum('draft','closed') NOT NULL DEFAULT 'draft',
  `rows_json` longtext DEFAULT NULL COMMENT 'the list as closed',
  `closed_by` int(10) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `entry_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_runs` (`company_id`,`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_runs`
--

LOCK TABLES `payroll_runs` WRITE;
/*!40000 ALTER TABLE `payroll_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `payroll_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'dashboard','Dashboard','Overview',10),(2,'vehicles','Vehicles','Fleet',20),(3,'drivers','Drivers','Fleet',30),(4,'maintenance','Maintenance','Fleet',40),(5,'vehicle_documents','Vehicle documents','Fleet',50),(6,'requests','Transport requests','Transport',60),(7,'trips','Trips','Transport',70),(8,'shipments','Shipments','Transport',80),(9,'deliveries','Deliveries','Transport',90),(10,'customers','Customers','Commercial',100),(11,'rates','Rate cards','Commercial',110),(12,'invoices','Invoices','Commercial',120),(13,'fuel','Fuel management','Finance',130),(14,'expenses','Logistics expenses','Finance',140),(15,'warehouse','Items and stock','Store',150),(16,'movements','Stock movements','Store',160),(17,'procurement','Procurement','Warehouse',170),(18,'suppliers','Suppliers','Warehouse',180),(19,'reports','Reports','Insights',190),(20,'users','Users and permissions','Administration',200),(21,'settings','Company settings','Administration',210),(22,'audit','Audit trail','Administration',220),(23,'accounts','Chart of accounts','Accounting',230),(24,'journal','Journal entries','Accounting',240),(25,'books','Accounting books','Accounting',250),(26,'email','Email outbox','Administration',225),(27,'lookups','Reference lists','Administration',215),(28,'warehouses','Stores','Store',405),(29,'payment_methods','Payment methods','Accounting',340),(30,'cheques','Cheques','Accounting',320),(31,'cheque_books','Cheque books','Accounting',330),(32,'budgets','Budget','Accounting',350),(33,'currencies','Currencies','Accounting',360),(34,'currency_rates','Exchange rates','Accounting',370),(35,'crossings','Border crossings','Transport',250),(36,'border_posts','Border posts','Transport',260),(37,'clearance_documents','Clearance documents','Transport',251),(38,'clearance_inspections','Clearance inspections','Transport',252),(39,'clearance_payments','Clearance payments','Transport',253),(40,'clearance_releases','Clearance releases','Transport',254),(41,'shipment_documents','Shipment documents','Transport',245),(42,'pre_dispatch_checks','Pre-dispatch checks','Transport',246),(43,'driver_documents','Driver papers','Fleet',35),(44,'report_emails','Report emails','Insights',195),(46,'supplier_bills','Supplier bills','Accounting',322),(47,'supplier_payments','Supplier payments','Accounting',324),(48,'disbursements','Disbursements','Commercial',126),(49,'bank_statements','Bank reconciliation','Accounting',326),(52,'containers','Containers','Transport',95),(53,'journeys','Journeys (multi-leg)','Transport',52),(54,'price_offers','Price offers','Commercial',115),(55,'support_requests','Driver support','Transport',95),(56,'approvals','Approvals inbox','Overview',12),(57,'approval_policies','Approval rules','Administration',212),(58,'tracking','Live tracking','Transport',72),(59,'freight_jobs','Freight jobs (sea and air)','Transport',55),(60,'cargo_claims','Cargo claims','Commercial',127),(61,'companies','Companies','Administration',200),(62,'departments','Departments','Administration',201),(63,'all_companies','See all companies','Administration',202),(64,'trailers','Trailers','Fleet',21),(65,'document_types','Document types','Administration',215),(66,'driver_momo_changes','MoMo number changes','Fleet',32),(67,'gps_providers','GPS providers','Administration',216),(68,'routes','Routes','Transport',60),(69,'route_rates','Route rate card','Transport',61),(70,'service_rates','Crane, tyre and labour rates','Transport',62),(71,'form_requests','Requests (F1-F15)','Requests',100),(72,'form_types','Request forms','Administration',217),(73,'approval_step_settings','Approvers per step','Administration',218),(74,'approval_delegations','Delegations','Administration',219),(75,'payment_batches','Payment batches','Payments',110),(76,'ledger_mappings','Ledger mapping','Accounting',160),(77,'trip_planner','Plan trips (New trip)','Transport',69),(78,'vehicle_handovers','Vehicle handovers','Transport',74),(79,'fuel_orders','Fuel orders','Transport',75),(80,'whatsapp_outbox','WhatsApp outbox','Administration',221),(81,'opening_balances','Opening balances (from Tally)','Accounting',165),(82,'garage','Garage: job cards','Fleet',75),(83,'store_issues','Store: issue notes','Warehouse',120),(84,'goods_receipts','Store: goods received','Warehouse',121),(85,'stock_counts','Store: stock counts','Warehouse',122),(86,'tyre_logs','Tyre log','Fleet',76),(87,'crane_logs','Crane log','Trips',77),(88,'shortage_rules','Shortage tolerance and cargo value','Commercial',141),(89,'billing','Customer invoicing (F13)','Commercial',142),(90,'payroll','Drivers payroll list','Fleet',78),(91,'portal','Customer and supplier portal','Portal',300),(92,'intercompany','Inter-company invoices','Accounting',168),(93,'payments','Payments received','Commercial',125);
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `price_offer_lines`
--

DROP TABLE IF EXISTS `price_offer_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `price_offer_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `offer_id` int(10) unsigned NOT NULL,
  `rate_card_id` int(10) unsigned NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_price_offer_line` (`offer_id`),
  KEY `fk_price_offer_line_rate` (`rate_card_id`),
  CONSTRAINT `fk_price_offer_line_offer` FOREIGN KEY (`offer_id`) REFERENCES `price_offers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_price_offer_line_rate` FOREIGN KEY (`rate_card_id`) REFERENCES `rate_cards` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `price_offer_lines`
--

LOCK TABLES `price_offer_lines` WRITE;
/*!40000 ALTER TABLE `price_offer_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `price_offer_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `price_offers`
--

DROP TABLE IF EXISTS `price_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `price_offers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `offer_code` varchar(32) NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `valid_until` date DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('draft','sent','accepted','declined') NOT NULL DEFAULT 'draft',
  `sent_at` datetime DEFAULT NULL,
  `accepted_by` varchar(150) DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `lpo_sent_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_offer` (`offer_code`),
  KEY `idx_price_offer_customer` (`customer_id`,`status`),
  KEY `idx_offers_company` (`company_id`),
  CONSTRAINT `fk_price_offer_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `price_offers`
--

LOCK TABLES `price_offers` WRITE;
/*!40000 ALTER TABLE `price_offers` DISABLE KEYS */;
/*!40000 ALTER TABLE `price_offers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_request_lines`
--

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_request_lines`
--

LOCK TABLES `purchase_request_lines` WRITE;
/*!40000 ALTER TABLE `purchase_request_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_request_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_requests`
--

DROP TABLE IF EXISTS `purchase_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
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
  KEY `idx_purchases_company` (`company_id`),
  CONSTRAINT `fk_purchase_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_purchase_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_requests`
--

LOCK TABLES `purchase_requests` WRITE;
/*!40000 ALTER TABLE `purchase_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_cards`
--

DROP TABLE IF EXISTS `rate_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_cards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `rate_code` varchar(32) NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `route_id` int(10) unsigned DEFAULT NULL,
  `cargo_type` varchar(20) DEFAULT NULL COMMENT 'litres, tonnes, containers; NULL = any',
  `service_type` enum('full_truck','shared') DEFAULT NULL,
  `origin_warehouse_id` int(10) unsigned DEFAULT NULL,
  `destination_warehouse_id` int(10) unsigned DEFAULT NULL,
  `origin` varchar(150) NOT NULL,
  `destination` varchar(150) NOT NULL,
  `vehicle_type` varchar(80) DEFAULT NULL,
  `full_load_kg` decimal(12,2) DEFAULT NULL,
  `full_load_price` decimal(14,2) DEFAULT NULL,
  `rate_type` enum('per_trip','per_kg','per_m3','per_package','per_day','per_ton','per_litre','per_container') NOT NULL DEFAULT 'per_trip',
  `rate_amount` decimal(14,4) NOT NULL,
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
  KEY `idx_rates_company` (`company_id`),
  CONSTRAINT `fk_rate_destination_wh` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rate_origin_wh` FOREIGN KEY (`origin_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rate_cards_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_cards`
--

LOCK TABLES `rate_cards` WRITE;
/*!40000 ALTER TABLE `rate_cards` DISABLE KEYS */;
/*!40000 ALTER TABLE `rate_cards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `report_subscriptions`
--

DROP TABLE IF EXISTS `report_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `report_key` varchar(60) NOT NULL,
  `frequency` enum('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
  `recipients` text DEFAULT NULL,
  `to_finance` tinyint(1) NOT NULL DEFAULT 0,
  `to_management` tinyint(1) NOT NULL DEFAULT 0,
  `to_fleet` tinyint(1) NOT NULL DEFAULT 0,
  `to_logistics` tinyint(1) NOT NULL DEFAULT 0,
  `to_warehouse` tinyint(1) NOT NULL DEFAULT 0,
  `to_admin` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_sent_at` datetime DEFAULT NULL,
  `last_status` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_report_sub_active` (`is_active`,`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `report_subscriptions`
--

LOCK TABLES `report_subscriptions` WRITE;
/*!40000 ALTER TABLE `report_subscriptions` DISABLE KEYS */;
INSERT INTO `report_subscriptions` VALUES (1,'trip_profitability','weekly',NULL,1,1,0,0,0,0,1,NULL,NULL,'Agreed at the client meeting, 30 Sep 2026.',NULL,'2026-10-23 14:22:47'),(2,'fuel_consumption','daily',NULL,0,0,1,0,0,0,1,NULL,NULL,'Agreed at the client meeting, 30 Sep 2026.',NULL,'2026-10-23 14:22:47'),(3,'expense_summary','monthly',NULL,1,1,0,0,0,0,1,NULL,NULL,'Agreed at the client meeting, 30 Sep 2026.',NULL,'2026-10-23 14:22:47');
/*!40000 ALTER TABLE `report_subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

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

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=833 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1,'audit',1,1,1,1,1),(2,1,'customers',1,1,1,1,1),(3,1,'dashboard',1,1,1,1,1),(4,1,'deliveries',1,1,1,1,1),(5,1,'drivers',1,1,1,1,1),(6,1,'expenses',1,1,1,1,1),(7,1,'fuel',1,1,1,1,1),(8,1,'invoices',1,1,1,1,1),(9,1,'maintenance',1,1,1,1,1),(10,1,'movements',1,1,1,1,1),(11,1,'procurement',1,1,1,1,1),(12,1,'rates',1,1,1,1,1),(13,1,'reports',1,1,1,1,1),(14,1,'requests',1,1,1,1,1),(15,1,'settings',1,1,1,1,1),(16,1,'shipments',1,1,1,1,1),(17,1,'suppliers',1,1,1,1,1),(18,1,'trips',1,1,1,1,1),(19,1,'users',1,1,1,1,1),(20,1,'vehicle_documents',1,1,1,1,1),(21,1,'vehicles',1,1,1,1,1),(22,1,'warehouse',1,1,1,1,1),(123,1,'accounts',1,1,1,1,1),(124,1,'books',1,1,1,1,1),(125,1,'journal',1,1,1,1,1),(132,1,'email',1,1,1,1,1),(134,1,'lookups',1,1,1,1,1),(142,1,'warehouses',1,1,1,1,1),(149,1,'payment_methods',1,1,1,1,1),(153,1,'cheques',1,1,1,1,1),(155,1,'cheque_books',1,1,1,1,1),(157,1,'budgets',1,1,1,1,1),(164,1,'currencies',1,1,1,1,1),(165,1,'currency_rates',1,1,1,1,1),(178,1,'crossings',1,1,1,1,1),(179,1,'border_posts',1,1,1,1,1),(193,1,'clearance_documents',1,1,1,1,1),(195,1,'clearance_inspections',1,1,1,1,1),(197,1,'clearance_payments',1,1,1,1,1),(199,1,'clearance_releases',1,1,1,1,1),(211,1,'shipment_documents',1,1,1,1,1),(213,1,'pre_dispatch_checks',1,1,1,1,1),(215,1,'driver_documents',1,1,1,1,1),(221,1,'report_emails',1,1,1,1,1),(235,1,'supplier_bills',1,1,1,1,1),(237,1,'supplier_payments',1,1,1,1,1),(239,1,'disbursements',1,1,1,1,1),(241,1,'bank_statements',1,1,1,1,1),(259,1,'containers',1,1,1,1,1),(280,1,'journeys',1,1,1,1,1),(293,1,'price_offers',1,1,1,1,1),(299,1,'support_requests',1,1,1,1,1),(312,1,'approvals',1,1,1,1,1),(321,1,'approval_policies',1,1,1,1,1),(322,1,'tracking',1,1,1,1,1),(353,1,'freight_jobs',1,1,1,1,1),(354,1,'cargo_claims',1,1,1,1,1),(369,1,'companies',1,1,1,1,1),(370,1,'departments',1,1,1,1,1),(371,1,'all_companies',1,1,1,1,1),(404,1,'trailers',1,1,1,1,1),(405,1,'document_types',1,1,1,1,1),(406,1,'driver_momo_changes',1,1,1,1,1),(413,1,'gps_providers',1,1,1,1,1),(414,1,'routes',1,1,1,1,1),(415,1,'route_rates',1,1,1,1,1),(416,1,'service_rates',1,1,1,1,1),(417,1,'form_requests',1,1,1,1,1),(418,1,'form_types',1,1,1,1,1),(419,1,'approval_step_settings',1,1,1,1,1),(420,1,'approval_delegations',1,1,1,1,1),(421,1,'payment_batches',1,1,1,1,1),(430,1,'ledger_mappings',1,1,1,1,1),(433,1,'trip_planner',1,1,1,1,1),(435,1,'vehicle_handovers',1,1,1,1,1),(437,1,'fuel_orders',1,1,1,1,1),(447,1,'whatsapp_outbox',1,1,1,1,1),(448,1,'billing',1,1,1,1,1),(449,1,'crane_logs',1,1,1,1,1),(450,1,'garage',1,1,1,1,1),(451,1,'goods_receipts',1,1,1,1,1),(452,1,'intercompany',1,1,1,1,1),(453,1,'opening_balances',1,1,1,1,1),(454,1,'payroll',1,1,1,1,1),(455,1,'portal',1,1,1,1,1),(456,1,'shortage_rules',1,1,1,1,1),(457,1,'stock_counts',1,1,1,1,1),(458,1,'store_issues',1,1,1,1,1),(459,1,'tyre_logs',1,1,1,1,1),(463,7,'dashboard',1,0,0,0,0),(464,7,'approvals',1,0,0,0,0),(465,7,'form_requests',1,0,0,0,0),(466,7,'payment_batches',1,0,0,0,0),(467,7,'reports',1,0,0,0,0),(468,7,'tracking',1,0,0,0,0),(469,7,'vehicles',1,0,0,0,0),(470,7,'trailers',1,0,0,0,0),(471,7,'vehicle_documents',1,0,0,0,0),(472,7,'drivers',1,0,0,0,0),(473,7,'driver_documents',1,0,0,0,0),(474,7,'driver_momo_changes',1,0,0,0,0),(475,7,'maintenance',1,0,0,0,0),(476,7,'routes',1,0,0,0,0),(477,7,'route_rates',1,0,0,0,0),(478,7,'service_rates',1,0,0,0,0),(479,7,'trip_planner',1,0,0,0,0),(480,7,'trips',1,0,0,0,0),(481,7,'fuel_orders',1,0,0,0,0),(482,7,'vehicle_handovers',1,0,0,0,0),(483,7,'support_requests',1,0,0,0,0),(484,7,'deliveries',1,0,0,0,0),(485,7,'customers',1,0,0,0,0),(486,7,'rates',1,0,0,0,0),(487,7,'invoices',1,0,0,0,0),(488,7,'payments',1,0,0,0,0),(489,7,'suppliers',1,0,0,0,0),(490,7,'supplier_bills',1,0,0,0,0),(491,7,'supplier_payments',1,0,0,0,0),(492,7,'warehouses',1,0,0,0,0),(493,7,'warehouse',1,0,0,0,0),(494,7,'movements',1,0,0,0,0),(495,7,'procurement',1,0,0,0,0),(496,7,'books',1,0,0,0,0),(497,7,'journal',1,0,0,0,0),(498,7,'accounts',1,0,0,0,0),(499,7,'ledger_mappings',1,0,0,0,0),(500,7,'budgets',1,0,0,0,0),(501,7,'bank_statements',1,0,0,0,0),(502,7,'currencies',1,0,0,0,0),(503,7,'payment_methods',1,0,0,0,0),(504,7,'cheques',1,0,0,0,0),(505,7,'cheque_books',1,0,0,0,0),(506,7,'expenses',1,0,0,0,0),(507,7,'fuel',1,0,0,0,0),(508,7,'disbursements',1,0,0,0,0),(509,7,'cargo_claims',1,0,0,0,0),(510,7,'crossings',1,0,0,0,0),(511,7,'clearance_documents',1,0,0,0,0),(512,7,'clearance_inspections',1,0,0,0,0),(513,7,'clearance_payments',1,0,0,0,0),(514,7,'clearance_releases',1,0,0,0,0),(515,7,'containers',1,0,0,0,0),(516,7,'border_posts',1,0,0,0,0),(517,7,'shipments',1,0,0,0,0),(518,7,'shipment_documents',1,0,0,0,0),(519,7,'freight_jobs',1,0,0,0,0),(520,7,'journeys',1,0,0,0,0),(521,7,'requests',1,0,0,0,0),(522,7,'price_offers',1,0,0,0,0),(523,7,'audit',1,0,0,0,0),(524,7,'garage',1,0,0,0,0),(525,7,'store_issues',1,0,0,0,0),(526,7,'goods_receipts',1,0,0,0,0),(527,7,'stock_counts',1,0,0,0,0),(528,7,'tyre_logs',1,0,0,0,0),(529,7,'crane_logs',1,0,0,0,0),(530,7,'billing',1,0,0,0,0),(531,7,'shortage_rules',1,0,0,0,0),(532,7,'payroll',1,0,0,0,0),(533,7,'intercompany',1,0,0,0,0),(534,7,'opening_balances',1,0,0,0,1),(535,7,'currency_rates',1,0,0,0,0),(590,2,'dashboard',1,0,0,0,0),(591,2,'approvals',1,0,0,0,0),(592,2,'reports',1,0,0,0,0),(593,2,'tracking',1,0,0,0,0),(594,2,'vehicles',1,1,1,0,0),(595,2,'trailers',1,1,1,0,0),(596,2,'vehicle_documents',1,1,1,1,0),(597,2,'drivers',1,1,1,0,0),(598,2,'driver_documents',1,1,1,1,0),(599,2,'document_types',1,0,0,0,0),(600,2,'driver_momo_changes',1,1,1,0,1),(601,2,'maintenance',1,0,0,0,0),(602,2,'garage',1,0,0,0,0),(603,2,'tyre_logs',1,0,0,0,0),(604,2,'crane_logs',1,1,1,0,0),(605,2,'store_issues',1,0,0,0,0),(606,2,'goods_receipts',1,0,0,0,0),(607,2,'stock_counts',1,0,0,0,0),(608,2,'warehouse',1,0,0,0,0),(609,2,'routes',1,0,0,0,0),(610,2,'route_rates',1,0,0,0,0),(611,2,'service_rates',1,0,0,0,0),(612,2,'payroll',1,0,0,0,0),(613,2,'trip_planner',1,1,1,0,1),(614,2,'trips',1,1,1,0,1),(615,2,'fuel_orders',1,1,1,0,1),(616,2,'vehicle_handovers',1,1,1,0,1),(617,2,'support_requests',1,1,1,0,1),(618,2,'deliveries',1,1,1,0,1),(619,2,'form_requests',1,1,1,0,0),(620,2,'customers',1,1,1,0,0),(621,2,'suppliers',1,1,0,0,0),(622,2,'procurement',1,1,1,0,0),(623,2,'containers',1,1,1,0,0),(624,2,'crossings',1,0,0,0,0),(625,2,'border_posts',1,0,0,0,0),(626,2,'fuel',1,0,0,0,0),(627,2,'shipments',1,1,1,0,0),(628,2,'requests',1,1,1,0,1),(629,2,'journeys',1,0,0,0,0),(630,2,'freight_jobs',1,0,0,0,0),(631,2,'price_offers',1,1,1,0,0),(632,2,'rates',1,0,0,0,0),(633,2,'payment_batches',1,0,0,0,0),(634,2,'shortage_rules',1,0,0,0,0),(653,6,'dashboard',1,0,0,0,0),(654,6,'approvals',1,0,0,0,0),(655,6,'reports',1,0,0,0,0),(656,6,'form_requests',1,1,1,0,0),(657,6,'payment_batches',1,0,0,0,0),(658,6,'books',1,0,0,0,0),(659,6,'journal',1,1,1,0,1),(660,6,'accounts',1,1,1,0,0),(661,6,'ledger_mappings',1,1,1,1,0),(662,6,'budgets',1,1,1,1,0),(663,6,'bank_statements',1,1,1,1,0),(664,6,'currencies',1,1,1,0,0),(665,6,'currency_rates',1,1,1,1,0),(666,6,'opening_balances',1,1,1,0,0),(667,6,'payment_methods',1,1,1,0,0),(668,6,'cheques',1,1,1,0,1),(669,6,'cheque_books',1,1,1,0,0),(670,6,'invoices',1,1,1,1,1),(671,6,'payments',1,1,1,1,0),(672,6,'customers',1,1,1,0,0),(673,6,'rates',1,1,1,0,0),(674,6,'suppliers',1,1,1,0,0),(675,6,'supplier_bills',1,1,1,0,1),(676,6,'supplier_payments',1,1,1,0,0),(677,6,'expenses',1,0,0,0,1),(678,6,'disbursements',1,1,1,0,0),(679,6,'cargo_claims',1,0,0,0,0),(680,6,'trips',1,0,0,0,0),(681,6,'vehicles',1,0,0,0,0),(682,6,'drivers',1,0,0,0,0),(683,6,'driver_documents',1,0,0,0,0),(684,6,'fuel',1,0,0,0,0),(685,6,'fuel_orders',1,0,0,0,0),(686,6,'route_rates',1,0,0,0,0),(687,6,'service_rates',1,0,0,0,0),(688,6,'routes',1,0,0,0,0),(689,6,'maintenance',1,0,0,0,0),(690,6,'deliveries',1,0,0,0,0),(691,6,'procurement',1,0,0,0,0),(692,6,'crossings',1,0,0,0,0),(693,6,'clearance_payments',1,0,0,0,0),(694,6,'support_requests',1,0,0,0,0),(695,6,'stock_counts',1,0,0,0,1),(696,6,'goods_receipts',1,0,0,0,0),(697,6,'tyre_logs',1,0,0,0,0),(698,6,'crane_logs',1,0,0,0,0),(699,6,'garage',1,0,0,0,0),(700,6,'warehouse',1,0,0,0,0),(701,6,'billing',1,1,1,0,0),(702,6,'shortage_rules',1,1,1,1,0),(703,6,'payroll',1,1,1,0,1),(704,6,'intercompany',1,0,0,0,0),(716,10,'dashboard',1,0,0,0,0),(717,10,'approvals',1,0,0,0,0),(718,10,'form_requests',1,0,0,0,0),(719,10,'payment_batches',1,1,1,0,1),(720,10,'books',1,0,0,0,0),(721,10,'journal',1,0,0,0,0),(722,10,'accounts',1,0,0,0,0),(723,10,'reports',1,0,0,0,0),(724,10,'invoices',1,0,0,0,0),(725,10,'supplier_bills',1,0,0,0,0),(726,10,'supplier_payments',1,0,0,0,0),(727,10,'payments',1,0,0,0,0),(728,10,'payment_methods',1,0,0,0,0),(729,10,'bank_statements',1,0,0,0,0),(730,10,'currency_rates',1,0,0,0,0),(731,10,'intercompany',1,0,0,0,0),(747,4,'dashboard',1,0,0,0,0),(748,4,'approvals',1,0,0,0,0),(749,4,'form_requests',1,1,0,0,0),(750,4,'warehouses',1,0,0,0,0),(751,4,'warehouse',1,1,1,0,0),(752,4,'movements',1,1,1,0,0),(753,4,'procurement',1,1,1,0,0),(754,4,'suppliers',1,0,0,0,0),(755,4,'reports',1,0,0,0,0),(756,4,'store_issues',1,1,1,0,0),(757,4,'goods_receipts',1,1,0,0,0),(758,4,'stock_counts',1,1,1,0,0),(759,4,'garage',1,1,1,0,0),(760,4,'maintenance',1,0,1,0,0),(761,4,'tyre_logs',1,1,1,0,0),(762,4,'vehicles',1,0,0,0,0),(763,4,'trailers',1,0,0,0,0),(764,4,'support_requests',1,0,0,0,0),(778,5,'dashboard',1,0,0,0,0),(779,5,'trips',1,0,0,0,0),(780,5,'deliveries',1,0,1,0,0),(781,5,'support_requests',1,1,0,0,0),(782,5,'driver_documents',1,0,0,0,0),(783,5,'vehicle_handovers',1,1,0,0,0),(784,5,'form_requests',1,1,0,0,0),(785,5,'vehicles',1,0,0,0,0),(793,9,'dashboard',1,0,0,0,0),(794,9,'approvals',1,0,0,0,0),(795,9,'reports',1,0,0,0,0),(796,9,'crossings',1,1,1,0,1),(797,9,'border_posts',1,0,0,0,0),(798,9,'clearance_documents',1,1,1,0,0),(799,9,'clearance_inspections',1,1,1,0,0),(800,9,'clearance_payments',1,1,1,0,0),(801,9,'clearance_releases',1,1,1,0,0),(802,9,'containers',1,1,1,0,0),(803,9,'shipments',1,1,1,0,0),(804,9,'shipment_documents',1,1,1,0,0),(805,9,'customers',1,1,0,0,0),(806,9,'trips',1,0,0,0,0),(807,9,'form_requests',1,1,0,0,0),(808,9,'documents',1,0,0,0,0),(824,13,'dashboard',1,0,0,0,0),(825,13,'portal',1,0,0,0,0),(827,14,'dashboard',1,0,0,0,0),(828,14,'portal',1,0,0,0,0),(830,1,'payments',1,1,1,1,1);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'super_admin','Admin'),(2,'logistics_manager','Operational Manager'),(4,'warehouse_manager','Store & garage keeper'),(5,'driver','Driver'),(6,'finance','Finance Manager'),(7,'management','Managing Director'),(9,'clearance_officer','Declaration Manager'),(10,'cashier','Cashier'),(13,'supplier','Supplier'),(14,'customer','Customer');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `route_legs`
--

DROP TABLE IF EXISTS `route_legs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `route_legs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `route_id` int(10) unsigned NOT NULL,
  `leg_no` tinyint(3) unsigned NOT NULL,
  `from_place` varchar(120) NOT NULL,
  `to_place` varchar(120) NOT NULL,
  `distance_km` int(10) unsigned DEFAULT NULL,
  `expected_hours` decimal(6,1) DEFAULT NULL,
  `border_post_id` int(10) unsigned DEFAULT NULL COMMENT 'the border crossed at the end of this leg',
  `is_turnaround` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'the leg arrives where the truck turns back',
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_leg_route` (`route_id`,`leg_no`),
  CONSTRAINT `fk_leg_route` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `route_legs`
--

LOCK TABLES `route_legs` WRITE;
/*!40000 ALTER TABLE `route_legs` DISABLE KEYS */;
/*!40000 ALTER TABLE `route_legs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `route_rates`
--

DROP TABLE IF EXISTS `route_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `route_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `route_id` int(10) unsigned NOT NULL,
  `rate_item` enum('fuel','road_toll','mileage','other') NOT NULL,
  `item_label` varchar(120) DEFAULT NULL COMMENT 'for "other"',
  `fleet` enum('all','tanker','container') NOT NULL DEFAULT 'all',
  `fuel_point` varchar(120) DEFAULT NULL COMMENT 'where the fuel is drawn: Kigali, Mombasa',
  `fuel_on` enum('departure','turnaround') DEFAULT NULL COMMENT 'ordered with F1, or raised as a top-up F2 at the turnaround',
  `supplier_id` int(10) unsigned DEFAULT NULL COMMENT 'the station or sister company that gives the fuel',
  `quantity` decimal(12,2) DEFAULT NULL COMMENT 'litres, for fuel',
  `amount` decimal(14,2) DEFAULT NULL COMMENT 'money paid to the driver (toll, mileage)',
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_route_rates` (`route_id`,`rate_item`,`valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `route_rates`
--

LOCK TABLES `route_rates` WRITE;
/*!40000 ALTER TABLE `route_rates` DISABLE KEYS */;
/*!40000 ALTER TABLE `route_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `routes`
--

DROP TABLE IF EXISTS `routes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `routes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL COMMENT 'blank: used by the whole group',
  `route_code` varchar(30) NOT NULL COMMENT 'KGL-MBA-KGL',
  `route_name` varchar(150) NOT NULL,
  `origin` varchar(120) NOT NULL,
  `destination` varchar(120) NOT NULL,
  `turnaround_point` varchar(120) DEFAULT NULL COMMENT 'where the truck loads and turns back (Mombasa)',
  `round_trip` tinyint(1) NOT NULL DEFAULT 1,
  `crosses_border` tinyint(1) NOT NULL DEFAULT 1,
  `total_km` int(10) unsigned DEFAULT NULL,
  `expected_days` decimal(5,1) DEFAULT NULL COMMENT 'the whole trip',
  `max_return_days` decimal(5,1) DEFAULT NULL COMMENT 'from the turnaround point back (3 days from Mombasa)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_route_code` (`route_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `routes`
--

LOCK TABLES `routes` WRITE;
/*!40000 ALTER TABLE `routes` DISABLE KEYS */;
/*!40000 ALTER TABLE `routes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_rates`
--

DROP TABLE IF EXISTS `service_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `rate_type` enum('crane','tyre','labour','other') NOT NULL,
  `description` varchar(150) NOT NULL COMMENT 'Crane lift, 20 ft, loading',
  `variant` varchar(60) DEFAULT NULL COMMENT '20ft, 40ft, loading, offloading',
  `unit` varchar(30) NOT NULL DEFAULT 'each',
  `amount` decimal(14,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_service_rates` (`rate_type`,`valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_rates`
--

LOCK TABLES `service_rates` WRITE;
/*!40000 ALTER TABLE `service_rates` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipment_documents`
--

DROP TABLE IF EXISTS `shipment_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shipment_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` int(10) unsigned NOT NULL,
  `document_type` varchar(80) NOT NULL,
  `document_number` varchar(80) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('pending','valid','rejected','expired') NOT NULL DEFAULT 'pending',
  `document_file` varchar(255) DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shipment_document_type` (`shipment_id`,`document_type`),
  KEY `idx_shipment_document_status` (`shipment_id`,`status`),
  KEY `fk_shipment_document_uploader` (`uploaded_by`),
  CONSTRAINT `fk_shipment_document_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shipment_document_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipment_documents`
--

LOCK TABLES `shipment_documents` WRITE;
/*!40000 ALTER TABLE `shipment_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `shipment_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipment_legs`
--

DROP TABLE IF EXISTS `shipment_legs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shipment_legs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` int(10) unsigned NOT NULL,
  `trip_id` int(10) unsigned NOT NULL,
  `leg_no` tinyint(3) unsigned DEFAULT NULL,
  `handed_over_at` datetime DEFAULT NULL COMMENT 'when it left this truck for the next one',
  `handover_point` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shipment_leg` (`shipment_id`,`trip_id`),
  KEY `fk_shipment_leg_trip` (`trip_id`),
  CONSTRAINT `fk_shipment_leg_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shipment_leg_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipment_legs`
--

LOCK TABLES `shipment_legs` WRITE;
/*!40000 ALTER TABLE `shipment_legs` DISABLE KEYS */;
/*!40000 ALTER TABLE `shipment_legs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipment_pre_dispatch_checks`
--

DROP TABLE IF EXISTS `shipment_pre_dispatch_checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shipment_pre_dispatch_checks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` int(10) unsigned NOT NULL,
  `cargo_loaded` tinyint(1) NOT NULL DEFAULT 0,
  `quantity_verified` tinyint(1) NOT NULL DEFAULT 0,
  `packaging_verified` tinyint(1) NOT NULL DEFAULT 0,
  `documents_verified` tinyint(1) NOT NULL DEFAULT 0,
  `vehicle_verified` tinyint(1) NOT NULL DEFAULT 0,
  `driver_verified` tinyint(1) NOT NULL DEFAULT 0,
  `route_verified` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','ready','not_ready') NOT NULL DEFAULT 'pending',
  `checked_by` int(10) unsigned DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pre_dispatch_shipment` (`shipment_id`),
  KEY `fk_pre_dispatch_user` (`checked_by`),
  CONSTRAINT `fk_pre_dispatch_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pre_dispatch_user` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipment_pre_dispatch_checks`
--

LOCK TABLES `shipment_pre_dispatch_checks` WRITE;
/*!40000 ALTER TABLE `shipment_pre_dispatch_checks` DISABLE KEYS */;
/*!40000 ALTER TABLE `shipment_pre_dispatch_checks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipments`
--

DROP TABLE IF EXISTS `shipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shipments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `shipment_code` varchar(32) NOT NULL,
  `request_id` int(10) unsigned DEFAULT NULL,
  `freight_job_id` int(10) unsigned DEFAULT NULL,
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
  `container_no` varchar(40) DEFAULT NULL,
  `container_size` varchar(20) DEFAULT NULL,
  `seal_no` varchar(60) DEFAULT NULL,
  `trailer_plate` varchar(32) DEFAULT NULL,
  `bill_of_lading` varchar(60) DEFAULT NULL,
  `customer_reference` varchar(60) DEFAULT NULL,
  `status` enum('draft','booked','loaded','cleared','released','in_transit','delivered','returned','cancelled') NOT NULL DEFAULT 'draft',
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
  KEY `idx_shipment_container` (`container_no`),
  KEY `idx_shipment_freight_job` (`freight_job_id`),
  KEY `idx_shipments_company` (`company_id`),
  CONSTRAINT `fk_shipment_destination_wh` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shipment_origin_wh` FOREIGN KEY (`origin_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shipment_rate_card` FOREIGN KEY (`rate_card_id`) REFERENCES `rate_cards` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_shipment_stock_item` FOREIGN KEY (`stock_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `transport_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_ibfk_2` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipments`
--

LOCK TABLES `shipments` WRITE;
/*!40000 ALTER TABLE `shipments` DISABLE KEYS */;
/*!40000 ALTER TABLE `shipments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shortage_rules`
--

DROP TABLE IF EXISTS `shortage_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shortage_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = every customer',
  `cargo_type` varchar(20) NOT NULL DEFAULT 'litres',
  `tolerance_type` enum('quantity','percent') NOT NULL DEFAULT 'percent',
  `tolerance_value` decimal(12,3) NOT NULL DEFAULT 0.000,
  `unit_value` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'the customer''s price of one unit of cargo',
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `charge` enum('excess','full') NOT NULL DEFAULT 'excess' COMMENT 'charge only what is above the tolerance, or the whole shortage',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shortage_rules_customer` (`customer_id`,`cargo_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shortage_rules`
--

LOCK TABLES `shortage_rules` WRITE;
/*!40000 ALTER TABLE `shortage_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `shortage_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_count_lines`
--

DROP TABLE IF EXISTS `stock_count_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_count_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `count_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `system_quantity` decimal(14,2) NOT NULL,
  `counted_quantity` decimal(14,2) DEFAULT NULL,
  `unit_cost` decimal(14,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(255) DEFAULT NULL COMMENT 'why the count differs',
  PRIMARY KEY (`id`),
  KEY `idx_stock_count_lines_count` (`count_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_count_lines`
--

LOCK TABLES `stock_count_lines` WRITE;
/*!40000 ALTER TABLE `stock_count_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_count_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_counts`
--

DROP TABLE IF EXISTS `stock_counts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_counts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `count_no` varchar(40) NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `counted_on` date NOT NULL,
  `status` enum('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `counted_by` int(10) unsigned DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `decided_by` int(10) unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decision_note` varchar(255) DEFAULT NULL,
  `entry_id` int(10) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stock_counts_company` (`company_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_counts`
--

LOCK TABLES `stock_counts` WRITE;
/*!40000 ALTER TABLE `stock_counts` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_counts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_movements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
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
  KEY `idx_movements_company` (`company_id`),
  CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_movements`
--

LOCK TABLES `stock_movements` WRITE;
/*!40000 ALTER TABLE `stock_movements` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_bill_lines`
--

DROP TABLE IF EXISTS `supplier_bill_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_bill_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bill_line` (`bill_id`),
  KEY `fk_bill_line_account` (`account_id`),
  CONSTRAINT `fk_bill_line_account` FOREIGN KEY (`account_id`) REFERENCES `gl_accounts` (`id`),
  CONSTRAINT `fk_bill_line_bill` FOREIGN KEY (`bill_id`) REFERENCES `supplier_bills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_bill_lines`
--

LOCK TABLES `supplier_bill_lines` WRITE;
/*!40000 ALTER TABLE `supplier_bill_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_bill_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_bills`
--

DROP TABLE IF EXISTS `supplier_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_bills` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `bill_no` varchar(32) NOT NULL,
  `supplier_id` int(10) unsigned NOT NULL,
  `supplier_invoice_no` varchar(60) DEFAULT NULL,
  `bill_date` date NOT NULL,
  `due_date` date NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','approved','partially_paid','paid','cancelled') NOT NULL DEFAULT 'draft',
  `trip_id` int(10) unsigned DEFAULT NULL,
  `purchase_request_id` int(10) unsigned DEFAULT NULL,
  `maintenance_id` int(10) unsigned DEFAULT NULL,
  `crossing_id` int(10) unsigned DEFAULT NULL,
  `bill_file` varchar(255) DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_opening` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'an open balance brought over from Tally; already in the opening ledger',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bill_no` (`bill_no`),
  KEY `idx_bill_supplier` (`supplier_id`,`status`),
  KEY `idx_bill_due` (`due_date`),
  KEY `fk_bill_trip` (`trip_id`),
  KEY `idx_bills_company` (`company_id`),
  CONSTRAINT `fk_bill_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_bill_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_bills`
--

LOCK TABLES `supplier_bills` WRITE;
/*!40000 ALTER TABLE `supplier_bills` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_bills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_payments`
--

DROP TABLE IF EXISTS `supplier_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `payment_code` varchar(32) NOT NULL,
  `bill_id` int(10) unsigned NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `method` varchar(40) NOT NULL DEFAULT 'bank_transfer',
  `reference` varchar(80) DEFAULT NULL,
  `paid_at` datetime NOT NULL,
  `recorded_by` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_supplier_payment` (`payment_code`),
  KEY `idx_supplier_payment_bill` (`bill_id`),
  KEY `idx_supplier_payments_company` (`company_id`),
  CONSTRAINT `fk_supplier_payment_bill` FOREIGN KEY (`bill_id`) REFERENCES `supplier_bills` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_payments`
--

LOCK TABLES `supplier_payments` WRITE;
/*!40000 ALTER TABLE `supplier_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL COMMENT 'blank: a supplier of the whole group',
  `supplier_code` varchar(32) DEFAULT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `category` varchar(80) DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `tin_number` varchar(40) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `payment_terms_days` int(10) unsigned NOT NULL DEFAULT 30,
  `payment_method` enum('momo','bank','cash') NOT NULL DEFAULT 'bank',
  `momo_number` varchar(20) DEFAULT NULL,
  `momo_name` varchar(120) DEFAULT NULL,
  `bank_name` varchar(120) DEFAULT NULL,
  `bank_account` varchar(60) DEFAULT NULL,
  `rating` tinyint(3) unsigned DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_suppliers_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_requests`
--

DROP TABLE IF EXISTS `support_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `request_code` varchar(32) NOT NULL,
  `trip_id` int(10) unsigned NOT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `requested_by` int(10) unsigned DEFAULT NULL,
  `assigned_to` int(10) unsigned DEFAULT NULL,
  `decided_by` int(10) unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `forwarded_role` varchar(50) DEFAULT NULL COMMENT 'Finance, garage, management, HR...',
  `request_id` int(10) unsigned DEFAULT NULL COMMENT 'the F10 when money is needed',
  `escalated_at` datetime DEFAULT NULL,
  `support_type` enum('money','breakdown','accident','police_border','documents','health','other') NOT NULL DEFAULT 'other',
  `urgency` enum('normal','urgent','emergency') NOT NULL DEFAULT 'normal',
  `title` varchar(150) NOT NULL,
  `details` text DEFAULT NULL,
  `location` varchar(190) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `amount_requested` decimal(14,2) DEFAULT NULL,
  `currency` char(3) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `photos` text DEFAULT NULL COMMENT 'JSON list of photo files',
  `status` enum('open','in_progress','resolved','rejected') NOT NULL DEFAULT 'open',
  `response` text DEFAULT NULL,
  `amount_given` decimal(14,2) DEFAULT NULL,
  `given_method` varchar(60) DEFAULT NULL,
  `given_reference` varchar(100) DEFAULT NULL,
  `responded_by` int(10) unsigned DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_request_code` (`request_code`),
  KEY `idx_support_trip` (`trip_id`),
  KEY `idx_support_assigned` (`assigned_to`,`status`),
  KEY `idx_support_company` (`company_id`),
  CONSTRAINT `fk_support_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_requests`
--

LOCK TABLES `support_requests` WRITE;
/*!40000 ALTER TABLE `support_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trailers`
--

DROP TABLE IF EXISTS `trailers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trailers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `trailer_number` varchar(32) NOT NULL COMMENT 'RL 9465 /BX',
  `trailer_type` enum('tanker','flatbed','skeleton','box','lowbed','other') NOT NULL DEFAULT 'flatbed',
  `make` varchar(80) DEFAULT NULL,
  `manufacture_year` smallint(5) unsigned DEFAULT NULL,
  `chassis_number` varchar(80) DEFAULT NULL,
  `max_load_t` decimal(8,2) DEFAULT NULL COMMENT 'payload in tonnes',
  `tank_capacity_l` decimal(10,0) DEFAULT NULL COMMENT 'tanker trailers: cargo tank in litres',
  `compartments` tinyint(3) unsigned DEFAULT NULL,
  `container_sizes` set('1x20','1x40','2x20') DEFAULT NULL,
  `vehicle_id` int(10) unsigned DEFAULT NULL COMMENT 'the truck it is usually coupled to',
  `status` enum('available','in_use','in_garage','off_road','inactive') NOT NULL DEFAULT 'available',
  `photo_file` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trailer_number` (`trailer_number`),
  KEY `idx_trailers_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trailers`
--

LOCK TABLES `trailers` WRITE;
/*!40000 ALTER TABLE `trailers` DISABLE KEYS */;
/*!40000 ALTER TABLE `trailers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transport_requests`
--

DROP TABLE IF EXISTS `transport_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transport_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `reference_code` varchar(32) NOT NULL,
  `source` enum('office','website') NOT NULL DEFAULT 'office',
  `service_type` varchar(30) DEFAULT NULL,
  `load_type` varchar(20) DEFAULT NULL,
  `requester_id` int(10) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `price_offer_id` int(10) unsigned DEFAULT NULL,
  `requested_by_contact` varchar(150) DEFAULT NULL,
  `contact_email` varchar(190) DEFAULT NULL,
  `contact_phone` varchar(40) DEFAULT NULL,
  `company_name` varchar(180) DEFAULT NULL,
  `lpo_number` varchar(60) DEFAULT NULL,
  `lpo_file` varchar(255) DEFAULT NULL,
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
  KEY `idx_requests_company` (`company_id`),
  CONSTRAINT `fk_request_destination_wh` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_request_origin_wh` FOREIGN KEY (`origin_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_request_rate_card` FOREIGN KEY (`rate_card_id`) REFERENCES `rate_cards` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_request_stock_item` FOREIGN KEY (`stock_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_requests_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_requests_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_requests_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transport_requests_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transport_requests`
--

LOCK TABLES `transport_requests` WRITE;
/*!40000 ALTER TABLE `transport_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `transport_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trip_changes`
--

DROP TABLE IF EXISTS `trip_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trip_changes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trip_id` int(10) unsigned NOT NULL,
  `change_type` enum('driver','vehicle','trailer') NOT NULL,
  `old_id` int(10) unsigned DEFAULT NULL,
  `new_id` int(10) unsigned NOT NULL,
  `reason` varchar(255) NOT NULL,
  `odometer` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_trip_changes` (`trip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trip_changes`
--

LOCK TABLES `trip_changes` WRITE;
/*!40000 ALTER TABLE `trip_changes` DISABLE KEYS */;
/*!40000 ALTER TABLE `trip_changes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trip_events`
--

DROP TABLE IF EXISTS `trip_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trip_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trip_id` int(10) unsigned NOT NULL,
  `event_type` varchar(40) NOT NULL COMMENT 'money_received, departed, checkpoint, at_turnaround, loading, loaded, offloading, delivered, arrived, delay, problem, closed...',
  `happened_at` datetime NOT NULL,
  `source` enum('driver','om','gps','system') NOT NULL DEFAULT 'driver',
  `place` varchar(150) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `odometer` int(10) unsigned DEFAULT NULL,
  `quantity` decimal(14,2) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `photo_file` varchar(255) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_trip_events` (`trip_id`,`happened_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trip_events`
--

LOCK TABLES `trip_events` WRITE;
/*!40000 ALTER TABLE `trip_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `trip_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trip_stops`
--

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
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `contact_phone` varchar(40) DEFAULT NULL,
  `planned_arrival_at` datetime DEFAULT NULL,
  `actual_arrival_at` datetime DEFAULT NULL,
  `departed_at` datetime DEFAULT NULL,
  `arrival_mileage` int(10) unsigned DEFAULT NULL,
  `arrival_condition` varchar(20) DEFAULT NULL,
  `arrival_source` varchar(20) DEFAULT NULL COMMENT 'driver, gps',
  `status` enum('pending','arrived','completed','skipped','failed') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_trip_stops_trip` (`trip_id`,`stop_sequence`),
  KEY `fk_stop_warehouse` (`warehouse_id`),
  CONSTRAINT `fk_stop_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trip_stops_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trip_stops`
--

LOCK TABLES `trip_stops` WRITE;
/*!40000 ALTER TABLE `trip_stops` DISABLE KEYS */;
/*!40000 ALTER TABLE `trip_stops` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trips`
--

DROP TABLE IF EXISTS `trips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trips` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `reference_code` varchar(32) NOT NULL,
  `trip_group` varchar(32) DEFAULT NULL COMMENT 'trucks planned together on one F1',
  `request_id` int(10) unsigned DEFAULT NULL,
  `journey_id` int(10) unsigned DEFAULT NULL,
  `leg_no` tinyint(3) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `route_id` int(10) unsigned DEFAULT NULL,
  `trip_type` enum('delivery','collection','transfer','return','shuttle') NOT NULL DEFAULT 'delivery',
  `vehicle_id` int(10) unsigned DEFAULT NULL,
  `trailer_id` int(10) unsigned DEFAULT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `pickup_location` varchar(150) NOT NULL,
  `destination` varchar(150) NOT NULL,
  `planned_departure_at` datetime DEFAULT NULL,
  `planned_arrival_at` datetime DEFAULT NULL,
  `expected_back_at` datetime DEFAULT NULL,
  `turnaround_at` datetime DEFAULT NULL COMMENT 'arrived where it loads and turns back',
  `return_due_at` datetime DEFAULT NULL COMMENT 'turnaround + the route''s maximum return days',
  `late_alerted_at` datetime DEFAULT NULL,
  `departure_at` datetime DEFAULT NULL,
  `start_mileage` int(10) unsigned DEFAULT NULL,
  `end_mileage` int(10) unsigned DEFAULT NULL,
  `km_driven` int(10) unsigned DEFAULT NULL,
  `fuel_given_l` decimal(10,2) DEFAULT NULL,
  `fuel_expected_l` decimal(10,2) DEFAULT NULL,
  `fuel_flag` varchar(255) DEFAULT NULL COMMENT 'the consumption check at close',
  `arrival_at` datetime DEFAULT NULL,
  `status` enum('planned','ready','on_road','at_turnaround','loading','offloading','returning','arrived','closed','cancelled','requested','approved','in_transit','delivered') NOT NULL DEFAULT 'planned',
  `cargo_summary` varchar(255) DEFAULT NULL,
  `cargo_type` enum('litres','tonnes','containers','other') DEFAULT NULL,
  `cargo_quantity` decimal(14,2) DEFAULT NULL,
  `container_numbers` varchar(255) DEFAULT NULL,
  `container_size` enum('1x20','1x40','2x20') DEFAULT NULL,
  `loaded_quantity` decimal(14,2) DEFAULT NULL,
  `delivered_quantity` decimal(14,2) DEFAULT NULL,
  `money_request_id` int(10) unsigned DEFAULT NULL COMMENT 'the F1 that pays this trip',
  `money_confirmed_at` datetime DEFAULT NULL COMMENT 'the driver confirmed the money arrived',
  `service_due_reason` varchar(255) DEFAULT NULL COMMENT 'leaves with a service due: why, and serviced on return',
  `dispatched_at` datetime DEFAULT NULL,
  `dispatched_by` int(10) unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
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
  KEY `idx_trip_journey` (`journey_id`,`leg_no`),
  KEY `idx_trips_company` (`company_id`),
  KEY `idx_trips_route` (`route_id`),
  KEY `idx_trips_group` (`trip_group`),
  CONSTRAINT `fk_trips_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trips_request` FOREIGN KEY (`request_id`) REFERENCES `transport_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trips_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trips`
--

LOCK TABLES `trips` WRITE;
/*!40000 ALTER TABLE `trips` DISABLE KEYS */;
/*!40000 ALTER TABLE `trips` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tyre_logs`
--

DROP TABLE IF EXISTS `tyre_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tyre_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `changed_on` date NOT NULL,
  `vehicle_id` int(10) unsigned DEFAULT NULL,
  `trailer_id` int(10) unsigned DEFAULT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `position` varchar(30) NOT NULL,
  `odometer_km` int(10) unsigned DEFAULT NULL,
  `tyre_serial` varchar(60) DEFAULT NULL,
  `brand` varchar(60) DEFAULT NULL,
  `reason` varchar(30) NOT NULL DEFAULT 'worn',
  `quantity` decimal(8,2) NOT NULL DEFAULT 1.00,
  `item_id` int(10) unsigned DEFAULT NULL COMMENT 'the tyre from the store, if any',
  `supplier_id` int(10) unsigned DEFAULT NULL COMMENT 'the tyre fitter',
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'fitting fee per tyre',
  `currency` char(3) NOT NULL DEFAULT 'RWF',
  `maintenance_id` int(10) unsigned DEFAULT NULL,
  `request_id` int(10) unsigned DEFAULT NULL COMMENT 'the F6 that paid the fitter',
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tyre_logs_vehicle` (`vehicle_id`,`changed_on`),
  KEY `idx_tyre_logs_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tyre_logs`
--

LOCK TABLES `tyre_logs` WRITE;
/*!40000 ALTER TABLE `tyre_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `tyre_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned DEFAULT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `supplier_id` int(10) unsigned DEFAULT NULL COMMENT 'a supplier login sees only its own orders',
  `customer_id` int(10) unsigned DEFAULT NULL COMMENT 'a customer login sees only its own cargo',
  `full_name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `whatsapp_number` varchar(40) DEFAULT NULL,
  `whatsapp_topics` varchar(255) DEFAULT NULL COMMENT 'comma-separated topics also sent on WhatsApp; blank: the defaults of the role',
  `department` varchar(80) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `signature_file` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','locked') NOT NULL DEFAULT 'active',
  `prvg` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `two_step_login` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'a code by email at every sign-in',
  `notify_by_email` tinyint(1) NOT NULL DEFAULT 1,
  `password_changed_at` datetime DEFAULT NULL,
  `failed_login_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  KEY `idx_users_deleted` (`deleted_at`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `chk_users_prvg` CHECK (`prvg` in (1,2))
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,1,8,NULL,NULL,'Admin User','admin@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 788 000 001',NULL,NULL,'Administration',NULL,NULL,NULL,'active',1,0,0,1,NULL,0,NULL,'2026-09-18 09:00:00',NULL,'2026-10-23 14:22:53',NULL),(2,2,1,2,NULL,NULL,'Aline Mukamana','aline@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 788 202 020',NULL,NULL,'Operations',NULL,NULL,NULL,'active',2,0,0,1,NULL,0,NULL,'2026-09-18 08:42:00',NULL,'2026-10-23 14:22:53',NULL),(3,5,1,2,NULL,NULL,'Samuel Niyonzima','samuel@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 788 632 119',NULL,NULL,'Fleet',NULL,NULL,NULL,'active',2,0,0,1,NULL,0,NULL,'2026-09-18 06:18:00',NULL,'2026-10-23 14:22:53',NULL),(4,4,1,14,NULL,NULL,'Nadine Tuyisenge','nadine@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 782 110 554',NULL,NULL,'Warehouse',NULL,NULL,NULL,'active',2,0,0,1,NULL,0,NULL,'2026-09-16 09:35:00',NULL,'2026-10-23 14:22:53',NULL),(5,4,1,NULL,NULL,NULL,'Patrick Habimana','patrick.workshop@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 785 330 912',NULL,NULL,'Store & garage',NULL,NULL,NULL,'active',2,0,0,1,NULL,0,NULL,'2026-09-16 08:10:00',NULL,'2026-10-23 14:22:53',NULL),(6,9,1,NULL,NULL,NULL,'Grace Uwase','grace.clearance@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 788 410 226',NULL,NULL,'Clearance',NULL,NULL,NULL,'active',2,0,0,1,NULL,0,NULL,'2026-09-16 08:20:00',NULL,'2026-10-23 14:22:53',NULL),(7,6,1,5,NULL,NULL,'Emmanuel Safari','emmanuel@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 788 705 881',NULL,NULL,'Finance',NULL,NULL,NULL,'active',2,0,0,1,NULL,0,NULL,'2026-09-15 11:05:00',NULL,'2026-10-23 14:22:53',NULL),(8,7,1,8,NULL,NULL,'Jean Pierre Habimana','jeanpierre@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 788 512 030',NULL,NULL,'Management',NULL,NULL,NULL,'active',2,0,0,1,NULL,0,NULL,'2026-09-14 14:10:00',NULL,'2026-10-23 14:22:53',NULL),(9,2,1,2,NULL,NULL,'Eric Murenzi','eric@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 783 210 084',NULL,NULL,'Operations',NULL,NULL,NULL,'active',2,0,0,1,NULL,0,NULL,NULL,NULL,'2026-10-23 14:22:53',NULL),(10,6,1,5,NULL,NULL,'Patrick Nkusi','patrick.finance@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 788 300 410',NULL,NULL,'Finance',NULL,NULL,NULL,'active',2,0,0,1,NULL,0,NULL,NULL,NULL,'2026-10-23 14:22:53',NULL),(11,10,1,11,NULL,NULL,'Diane Uwera','cashier@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+250 788 300 411',NULL,NULL,'Cash office',NULL,NULL,NULL,'active',2,0,0,1,NULL,0,NULL,NULL,NULL,'2026-10-23 14:22:53',NULL),(12,2,3,3,NULL,NULL,'Shomari Mwinyi','shomari@itec.rw','$2y$10$xT3CpwhBWfjZdDjM1qVKleYlvscj7OR0UTKfa2gCdgF4EqHt67ea6','+255 754 300 413',NULL,NULL,'Operations',NULL,NULL,NULL,'active',2,0,0,1,NULL,0,NULL,NULL,NULL,'2026-10-23 14:22:53',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_documents`
--

DROP TABLE IF EXISTS `vehicle_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `document_code` varchar(32) NOT NULL,
  `owner_type` enum('vehicle','trailer') NOT NULL DEFAULT 'vehicle',
  `vehicle_id` int(10) unsigned DEFAULT NULL,
  `trailer_id` int(10) unsigned DEFAULT NULL,
  `document_type` varchar(40) NOT NULL DEFAULT 'insurance',
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
  KEY `idx_vdocs_company` (`company_id`),
  CONSTRAINT `vehicle_documents_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_documents`
--

LOCK TABLES `vehicle_documents` WRITE;
/*!40000 ALTER TABLE `vehicle_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicle_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_handovers`
--

DROP TABLE IF EXISTS `vehicle_handovers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_handovers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `handover_code` varchar(32) DEFAULT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `vehicle_id` int(10) unsigned NOT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `parked_at` varchar(150) NOT NULL COMMENT 'where the truck is parked',
  `odometer` int(10) unsigned NOT NULL,
  `fuel_level` enum('empty','quarter','half','three_quarters','full') NOT NULL,
  `key_received_by` varchar(150) NOT NULL,
  `key_received_user_id` int(10) unsigned DEFAULT NULL,
  `photo_front` varchar(255) DEFAULT NULL,
  `photo_back` varchar(255) DEFAULT NULL,
  `photo_left` varchar(255) DEFAULT NULL,
  `photo_right` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `status` enum('submitted','confirmed','rejected') NOT NULL DEFAULT 'submitted',
  `confirmed_by` int(10) unsigned DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `submitted_by` int(10) unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_handover_trip` (`trip_id`),
  KEY `idx_handover_vehicle` (`vehicle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_handovers`
--

LOCK TABLES `vehicle_handovers` WRITE;
/*!40000 ALTER TABLE `vehicle_handovers` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicle_handovers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_positions`
--

DROP TABLE IF EXISTS `vehicle_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_positions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(10) unsigned NOT NULL,
  `trip_id` int(10) unsigned DEFAULT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `speed_kmh` decimal(6,1) DEFAULT NULL,
  `heading` smallint(6) DEFAULT NULL,
  `accuracy_m` decimal(8,1) DEFAULT NULL,
  `source` enum('device','phone') NOT NULL DEFAULT 'phone',
  `recorded_at` datetime NOT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_position_vehicle` (`vehicle_id`,`recorded_at`),
  KEY `idx_position_trip` (`trip_id`,`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_positions`
--

LOCK TABLES `vehicle_positions` WRITE;
/*!40000 ALTER TABLE `vehicle_positions` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicle_positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicles`
--

DROP TABLE IF EXISTS `vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `plate_number` varchar(32) NOT NULL,
  `vehicle_type` varchar(80) NOT NULL,
  `fleet` enum('tanker','container','other') NOT NULL DEFAULT 'container' COMMENT 'decides which OM manages it',
  `trailer_id` int(10) unsigned DEFAULT NULL,
  `make` varchar(80) DEFAULT NULL,
  `model` varchar(80) DEFAULT NULL,
  `manufacture_year` smallint(5) unsigned DEFAULT NULL,
  `chassis_number` varchar(80) DEFAULT NULL,
  `engine_number` varchar(80) DEFAULT NULL,
  `gps_device_id` varchar(64) DEFAULT NULL COMMENT 'the tracker IMEI or unique id',
  `gps_provider_id` int(10) unsigned DEFAULT NULL,
  `capacity_kg` decimal(12,2) DEFAULT NULL,
  `capacity_m3` decimal(12,3) DEFAULT NULL,
  `max_payload_t` decimal(8,2) DEFAULT NULL COMMENT 'payload in tonnes',
  `fuel_tank_l` decimal(8,0) DEFAULT NULL COMMENT 'own fuel tank, litres (1,200)',
  `cargo_tank_l` decimal(10,0) DEFAULT NULL COMMENT 'tankers: cargo tank in litres',
  `container_sizes` set('1x20','1x40','2x20') DEFAULT NULL COMMENT 'container trucks: what it may carry',
  `normal_l_per_100km` decimal(6,2) DEFAULT NULL COMMENT 'normal consumption, for the fuel check',
  `fuel_type` enum('diesel','petrol','electric','hybrid','cng') NOT NULL DEFAULT 'diesel',
  `ownership` enum('owned','leased','rented','subcontracted') NOT NULL DEFAULT 'owned',
  `acquired_on` date DEFAULT NULL,
  `has_cooling_unit` tinyint(1) NOT NULL DEFAULT 0,
  `assigned_driver_id` int(10) unsigned DEFAULT NULL,
  `mileage` int(10) unsigned NOT NULL DEFAULT 0,
  `odometer_source` enum('gps','manual') NOT NULL DEFAULT 'manual',
  `service_interval_km` int(10) unsigned DEFAULT NULL COMMENT 'blank: the company setting (7,000 km)',
  `last_service_km` int(10) unsigned DEFAULT NULL,
  `next_service_km` int(10) unsigned DEFAULT NULL,
  `service_first_on_return` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'left with a service due: first in line when back',
  `status` enum('available','assigned','loading','on_road','offloading','returning','in_garage','off_road','inactive') NOT NULL DEFAULT 'available',
  `off_road_reason` varchar(255) DEFAULT NULL,
  `next_service_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `photo_front` varchar(255) DEFAULT NULL,
  `photo_back` varchar(255) DEFAULT NULL,
  `photo_left` varchar(255) DEFAULT NULL,
  `photo_right` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plate_number` (`plate_number`),
  KEY `idx_vehicles_status` (`status`),
  KEY `idx_vehicles_deleted` (`deleted_at`),
  KEY `idx_vehicles_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicles`
--

LOCK TABLES `vehicles` WRITE;
/*!40000 ALTER TABLE `vehicles` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warehouses`
--

DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `warehouses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `warehouse_code` varchar(32) DEFAULT NULL,
  `warehouse_name` varchar(150) NOT NULL,
  `location` varchar(150) NOT NULL,
  `manager_id` int(10) unsigned DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `capacity_m3` decimal(12,2) DEFAULT NULL,
  `is_cold_chain` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_warehouses_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warehouses`
--

LOCK TABLES `warehouses` WRITE;
/*!40000 ALTER TABLE `warehouses` DISABLE KEYS */;
/*!40000 ALTER TABLE `warehouses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_outbox`
--

DROP TABLE IF EXISTS `whatsapp_outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `whatsapp_outbox` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned DEFAULT NULL,
  `message_key` varchar(120) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `to_number` varchar(40) NOT NULL,
  `topic` varchar(30) DEFAULT NULL,
  `body` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `status` enum('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
  `provider_id` varchar(120) DEFAULT NULL,
  `error` varchar(255) DEFAULT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_whatsapp_key` (`message_key`),
  KEY `idx_whatsapp_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_outbox`
--

LOCK TABLES `whatsapp_outbox` WRITE;
/*!40000 ALTER TABLE `whatsapp_outbox` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_outbox` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'eri_install_build'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-23 16:23:19
