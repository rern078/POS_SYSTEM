-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 09, 2025 at 09:10 AM
-- Server version: 5.7.36
-- PHP Version: 8.0.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pos_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `chart_of_accounts`
--

DROP TABLE IF EXISTS `chart_of_accounts`;
CREATE TABLE IF NOT EXISTS `chart_of_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_code` varchar(20) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_type` enum('asset','liability','equity','revenue','expense') NOT NULL,
  `parent_account_id` int(11) DEFAULT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_code` (`account_code`),
  KEY `parent_account_id` (`parent_account_id`),
  KEY `account_type` (`account_type`)
) ENGINE=MyISAM AUTO_INCREMENT=26 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `chart_of_accounts`
--

INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_account_id`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '1000', 'Current Assets', 'asset', NULL, 'All current assets', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(2, '1100', 'Cash and Cash Equivalents', 'asset', 1, 'Cash, bank accounts, petty cash', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(3, '1200', 'Accounts Receivable', 'asset', 1, 'Money owed by customers', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(4, '1300', 'Inventory', 'asset', 1, 'Product inventory', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(5, '1400', 'Prepaid Expenses', 'asset', 1, 'Prepaid rent, insurance, etc.', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(6, '2000', 'Current Liabilities', 'liability', NULL, 'All current liabilities', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(7, '2100', 'Accounts Payable', 'liability', 6, 'Money owed to suppliers', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(8, '2200', 'Sales Tax Payable', 'liability', 6, 'Sales tax collected', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(9, '2300', 'Accrued Expenses', 'liability', 6, 'Accrued wages, utilities, etc.', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(10, '3000', 'Owner Equity', 'equity', NULL, 'Owner investments and retained earnings', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(11, '3100', 'Owner Investment', 'equity', 10, 'Initial and additional owner investments', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(12, '3200', 'Retained Earnings', 'equity', 10, 'Accumulated profits/losses', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(13, '4000', 'Revenue', 'revenue', NULL, 'All revenue accounts', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(14, '4100', 'Sales Revenue', 'revenue', 13, 'Product sales revenue', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(15, '4200', 'Discount Revenue', 'revenue', 13, 'Revenue from discounts given', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(16, '5000', 'Expenses', 'expense', NULL, 'All expense accounts', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(17, '5100', 'Cost of Goods Sold', 'expense', 16, 'Cost of products sold', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(18, '5200', 'Operating Expenses', 'expense', 16, 'General operating expenses', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(19, '5210', 'Rent Expense', 'expense', 18, 'Store rent', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(20, '5220', 'Utilities Expense', 'expense', 18, 'Electricity, water, internet', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(21, '5230', 'Salaries and Wages', 'expense', 18, 'Employee salaries and wages', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(22, '5240', 'Advertising Expense', 'expense', 18, 'Marketing and advertising costs', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(23, '5250', 'Office Supplies', 'expense', 18, 'Office and store supplies', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(24, '5260', 'Insurance Expense', 'expense', 18, 'Business insurance', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46'),
(25, '5270', 'Depreciation Expense', 'expense', 18, 'Equipment and furniture depreciation', 1, '2025-07-07 04:44:46', '2025-07-07 04:44:46');

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

DROP TABLE IF EXISTS `currencies`;
CREATE TABLE IF NOT EXISTS `currencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`id`, `code`, `name`, `symbol`, `is_default`, `is_active`, `created_at`) VALUES
(1, 'USD', 'US Dollar', '$', 1, 1, '2025-07-09 07:36:39'),
(2, 'EUR', 'Euro', '€', 0, 1, '2025-07-09 07:36:39'),
(3, 'GBP', 'British Pound', '£', 0, 1, '2025-07-09 07:36:39'),
(4, 'JPY', 'Japanese Yen', '¥', 0, 1, '2025-07-09 07:36:39'),
(5, 'CAD', 'Canadian Dollar', 'C$', 0, 1, '2025-07-09 07:36:39'),
(6, 'AUD', 'Australian Dollar', 'A$', 0, 1, '2025-07-09 07:36:39'),
(7, 'CHF', 'Swiss Franc', 'CHF', 0, 1, '2025-07-09 07:36:39'),
(8, 'CNY', 'Chinese Yuan', '¥', 0, 1, '2025-07-09 07:36:39'),
(9, 'INR', 'Indian Rupee', '₹', 0, 1, '2025-07-09 07:36:39'),
(10, 'KRW', 'South Korean Won', '₩', 0, 1, '2025-07-09 07:36:39'),
(11, 'SGD', 'Singapore Dollar', 'S$', 0, 1, '2025-07-09 07:36:39'),
(12, 'HKD', 'Hong Kong Dollar', 'HK$', 0, 1, '2025-07-09 07:36:39'),
(13, 'THB', 'Thai Baht', '฿', 0, 1, '2025-07-09 07:36:39'),
(14, 'PHP', 'Philippine Peso', '₱', 0, 1, '2025-07-09 07:36:39'),
(15, 'MYR', 'Malaysian Ringgit', 'RM', 0, 1, '2025-07-09 07:36:39'),
(16, 'IDR', 'Indonesian Rupiah', 'Rp', 0, 1, '2025-07-09 07:36:39'),
(17, 'VND', 'Vietnamese Dong', '₫', 0, 1, '2025-07-09 07:36:39'),
(18, 'KHR', 'Cambodian Riel', '៛', 0, 1, '2025-07-09 07:36:39');

-- --------------------------------------------------------

--
-- Table structure for table `exchange_rates`
--

DROP TABLE IF EXISTS `exchange_rates`;
CREATE TABLE IF NOT EXISTS `exchange_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `base_currency` varchar(3) NOT NULL DEFAULT 'USD',
  `target_currency` varchar(3) NOT NULL,
  `rate` decimal(10,6) NOT NULL,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_currency_pair` (`base_currency`,`target_currency`),
  KEY `idx_base_currency` (`base_currency`),
  KEY `idx_target_currency` (`target_currency`)
) ENGINE=MyISAM AUTO_INCREMENT=38 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `exchange_rates`
--

INSERT INTO `exchange_rates` (`id`, `base_currency`, `target_currency`, `rate`, `last_updated`, `is_active`) VALUES
(37, 'USD', 'KHR', '4100.000000', '2025-07-09 08:31:38', 1),
(36, 'USD', 'VND', '9999.999999', '2025-07-09 08:31:38', 1),
(35, 'USD', 'IDR', '9999.999999', '2025-07-09 08:31:38', 1),
(34, 'USD', 'MYR', '4.150000', '2025-07-09 08:31:38', 1),
(33, 'USD', 'PHP', '50.800000', '2025-07-09 08:31:38', 1),
(32, 'USD', 'THB', '33.500000', '2025-07-09 08:31:38', 1),
(31, 'USD', 'HKD', '7.780000', '2025-07-09 08:31:38', 1),
(30, 'USD', 'SGD', '1.350000', '2025-07-09 08:31:38', 1),
(29, 'USD', 'KRW', '1150.000000', '2025-07-09 08:31:38', 1),
(28, 'USD', 'INR', '74.500000', '2025-07-09 08:31:38', 1),
(27, 'USD', 'CNY', '6.450000', '2025-07-09 08:31:38', 1),
(26, 'USD', 'CHF', '0.920000', '2025-07-09 08:31:38', 1),
(25, 'USD', 'AUD', '1.350000', '2025-07-09 08:31:38', 1),
(24, 'USD', 'CAD', '1.250000', '2025-07-09 08:31:38', 1),
(23, 'USD', 'JPY', '110.000000', '2025-07-09 08:31:38', 1),
(22, 'USD', 'GBP', '0.730000', '2025-07-09 08:31:38', 1),
(21, 'USD', 'EUR', '0.850000', '2025-07-09 08:31:38', 1),
(20, 'USD', 'USD', '1.000000', '2025-07-09 08:31:38', 1);

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_number` varchar(20) NOT NULL,
  `expense_date` date NOT NULL,
  `vendor_name` varchar(100) DEFAULT NULL,
  `vendor_email` varchar(100) DEFAULT NULL,
  `vendor_phone` varchar(20) DEFAULT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','check','bank_transfer','credit_card','other') DEFAULT 'cash',
  `payment_status` enum('paid','pending','cancelled') DEFAULT 'pending',
  `expense_category` varchar(50) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expense_number` (`expense_number`),
  KEY `expense_date` (`expense_date`),
  KEY `payment_status` (`payment_status`),
  KEY `created_by` (`created_by`),
  KEY `idx_expenses_date` (`expense_date`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `financial_periods`
--

DROP TABLE IF EXISTS `financial_periods`;
CREATE TABLE IF NOT EXISTS `financial_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_closed` tinyint(1) DEFAULT '0',
  `closing_date` timestamp NULL DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `start_date` (`start_date`),
  KEY `end_date` (`end_date`),
  KEY `is_closed` (`is_closed`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `general_ledger`
--

DROP TABLE IF EXISTS `general_ledger`;
CREATE TABLE IF NOT EXISTS `general_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `opening_debit` decimal(12,2) DEFAULT '0.00',
  `opening_credit` decimal(12,2) DEFAULT '0.00',
  `period_debit` decimal(12,2) DEFAULT '0.00',
  `period_credit` decimal(12,2) DEFAULT '0.00',
  `closing_debit` decimal(12,2) DEFAULT '0.00',
  `closing_credit` decimal(12,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_period` (`account_id`,`period_id`),
  KEY `account_id` (`account_id`),
  KEY `period_id` (`period_id`),
  KEY `idx_general_ledger_period` (`period_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `journal_entries`
--

DROP TABLE IF EXISTS `journal_entries`;
CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_number` varchar(20) NOT NULL,
  `entry_date` date NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `description` text,
  `entry_type` enum('sale','purchase','expense','adjustment','transfer','opening_balance') NOT NULL,
  `total_debit` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_credit` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entry_number` (`entry_number`),
  KEY `entry_date` (`entry_date`),
  KEY `entry_type` (`entry_type`),
  KEY `created_by` (`created_by`),
  KEY `idx_journal_entries_date` (`entry_date`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `journal_entry_details`
--

DROP TABLE IF EXISTS `journal_entry_details`;
CREATE TABLE IF NOT EXISTS `journal_entry_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journal_entry_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `debit_amount` decimal(12,2) DEFAULT '0.00',
  `credit_amount` decimal(12,2) DEFAULT '0.00',
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `account_id` (`account_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `currency_code` varchar(3) DEFAULT 'USD',
  `exchange_rate` decimal(10,6) DEFAULT '1.000000',
  `original_amount` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT '0.00',
  `tax_amount` decimal(10,2) DEFAULT '0.00',
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `journal_entry_id` int(11) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `amount_tendered` decimal(10,2) DEFAULT '0.00',
  `change_amount` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orders_user_id` (`user_id`),
  KEY `idx_orders_customer_email` (`customer_email`),
  KEY `idx_payment_method` (`payment_method`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_currency_code` (`currency_code`)
) ENGINE=MyISAM AUTO_INCREMENT=32 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `customer_name`, `customer_email`, `total_amount`, `currency_code`, `exchange_rate`, `original_amount`, `subtotal`, `tax_amount`, `discount_amount`, `journal_entry_id`, `status`, `payment_method`, `amount_tendered`, `change_amount`, `created_at`, `updated_at`) VALUES
(1, NULL, 'bb', 'chamrern@gmail.com', '4.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '0.00', '0.00', '2025-07-04 08:03:38', '2025-07-04 08:03:38'),
(2, NULL, 'aa', 'tiengchamrern2@gmail.com', '8.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'card', '0.00', '0.00', '2025-07-04 08:04:10', '2025-07-04 08:04:10'),
(3, NULL, 'chamrern', 'chamrer@gmail.com', '20.50', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '0.00', '0.00', '2025-07-04 08:14:36', '2025-07-04 08:14:36'),
(4, NULL, 'ss', 'ss@gmail.com', '12.50', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'card', '0.00', '0.00', '2025-07-05 04:56:12', '2025-07-05 04:56:12'),
(5, NULL, 'test', 'test@gmail.com', '12.50', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'bank', '0.00', '0.00', '2025-07-08 07:29:54', '2025-07-08 07:29:54'),
(6, NULL, 'test2', 'test2@gmail.com', '11.97', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '0.00', '0.00', '2025-07-08 07:56:34', '2025-07-08 07:56:34'),
(7, 10, 'customers1', 'customers1@gmail.com', '2.50', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'cancelled', 'test', '0.00', '0.00', '2025-07-08 08:21:55', '2025-07-09 04:56:08'),
(8, 10, 'customer1', 'customer1@gmail.com', '12.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'card', '0.00', '0.00', '2025-07-09 02:18:27', '2025-07-09 02:18:27'),
(9, 10, 'customer1', 'customer1@gmail.com', '35.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'card', '0.00', '0.00', '2025-07-09 03:17:18', '2025-07-09 03:17:18'),
(10, 10, 'customers1', 'customers1@gmail.com', '3.50', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'card', '0.00', '0.00', '2025-07-09 03:23:08', '2025-07-09 03:23:08'),
(11, NULL, 'bb4535', 'chamrern@gmail.com', '9.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '0.00', '0.00', '2025-07-09 03:24:47', '2025-07-09 03:24:47'),
(12, NULL, 'fdf', '3423rn@gmail.com', '2.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'card', '0.00', '0.00', '2025-07-09 03:51:50', '2025-07-09 03:51:50'),
(13, NULL, '3435', 'fere@fdfdf', '4.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '100.00', '0.00', '2025-07-09 03:53:24', '2025-07-09 03:53:24'),
(14, NULL, '454', 'tiengchamrern2@gmail.com', '2.75', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '100.00', '0.00', '2025-07-09 04:08:46', '2025-07-09 04:08:46'),
(15, 10, 'customers1', 'customers1@gmail.com', '6.75', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '100.00', '93.25', '2025-07-09 04:10:00', '2025-07-09 04:10:00'),
(16, NULL, 'rerere', 'chamrern@gmail.com', '2.75', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '50.00', '0.00', '2025-07-09 04:10:58', '2025-07-09 04:10:58'),
(17, NULL, '435', 'chamrern1@gmail.com45', '2.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '20.00', '0.00', '2025-07-09 04:13:32', '2025-07-09 04:13:32'),
(18, NULL, '343', '4343@fefrer', '2.50', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '50.00', '0.00', '2025-07-09 04:18:41', '2025-07-09 04:18:41'),
(19, 10, 'customers1', 'customers1@gmail.com', '6.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '50.00', '44.00', '2025-07-09 04:19:38', '2025-07-09 04:19:38'),
(20, NULL, '423f', 'fsf@fe325325', '2.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '10.00', '8.00', '2025-07-09 04:23:12', '2025-07-09 04:25:22'),
(21, NULL, '535', 'tiengchamrern2@gmail.com', '2.50', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '100.00', '0.00', '2025-07-09 04:25:58', '2025-07-09 04:25:58'),
(22, NULL, 'aa', 'chamrern1@gmail.com', '2.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '100.00', '98.00', '2025-07-09 04:30:08', '2025-07-09 04:31:42'),
(23, NULL, 'bb', 'chamrern1@gmail.com', '6.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '100.00', '94.00', '2025-07-09 04:34:26', '2025-07-09 04:55:56'),
(24, NULL, 'aa', 'tiengchamrern2@gmail.com', '4.50', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '5.00', '0.50', '2025-07-09 04:35:05', '2025-07-09 04:55:52'),
(25, 10, 'customers1', 'customers1@gmail.com', '3.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '50.00', '47.00', '2025-07-09 07:30:24', '2025-07-09 07:30:24'),
(26, 10, 'customers1', 'customers1@gmail.com', '3.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'card', '50.00', '0.00', '2025-07-09 07:30:35', '2025-07-09 07:30:35'),
(27, 10, 'customers1', 'customers1@gmail.com', '3.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'card', '50.00', '0.00', '2025-07-09 07:30:57', '2025-07-09 07:30:57'),
(28, 10, 'customers1', 'customers1@gmail.com', '3.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'card', '50.00', '0.00', '2025-07-09 07:31:19', '2025-07-09 07:31:19'),
(29, 10, 'customers1', 'customers1@gmail.com', '3.00', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'card', '0.00', '0.00', '2025-07-09 07:32:43', '2025-07-09 07:32:43'),
(30, 10, 'customers1', 'customers1@gmail.com', '98277.00', 'KHR', '4100.000000', '23.97', '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '100000.00', '1723.00', '2025-07-09 07:59:45', '2025-07-09 07:59:45'),
(31, NULL, 'aa', 'chamrern1@gmail.com', '3.99', 'USD', '1.000000', NULL, '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '100.00', '96.01', '2025-07-09 08:04:18', '2025-07-09 08:04:18');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `currency_code` varchar(3) DEFAULT 'USD',
  `exchange_rate` decimal(10,6) DEFAULT '1.000000',
  `cost_price` decimal(10,2) DEFAULT '0.00',
  `profit_margin` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  KEY `idx_currency_code` (`currency_code`)
) ENGINE=MyISAM AUTO_INCREMENT=34 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `currency_code`, `exchange_rate`, `cost_price`, `profit_margin`) VALUES
(1, 1, 1, 2, '2.00', 'USD', '1.000000', '0.00', '0.00'),
(2, 2, 16, 2, '4.00', 'USD', '1.000000', '0.00', '0.00'),
(3, 3, 11, 2, '3.00', 'USD', '1.000000', '0.00', '0.00'),
(4, 3, 2, 2, '2.50', 'USD', '1.000000', '0.00', '0.00'),
(5, 3, 4, 2, '3.00', 'USD', '1.000000', '0.00', '0.00'),
(6, 3, 3, 2, '1.75', 'USD', '1.000000', '0.00', '0.00'),
(7, 4, 14, 1, '2.50', 'USD', '1.000000', '0.00', '0.00'),
(8, 4, 16, 1, '4.00', 'USD', '1.000000', '0.00', '0.00'),
(9, 4, 11, 2, '3.00', 'USD', '1.000000', '0.00', '0.00'),
(10, 5, 14, 5, '2.50', 'USD', '1.000000', '0.00', '0.00'),
(11, 6, 7, 3, '3.99', 'USD', '1.000000', '0.00', '0.00'),
(12, 7, 1, 1, '2.50', 'USD', '1.000000', '0.00', '0.00'),
(13, 8, 11, 4, '3.00', 'USD', '1.000000', '0.00', '0.00'),
(14, 9, 18, 10, '3.50', 'USD', '1.000000', '0.00', '0.00'),
(15, 10, 3, 2, '1.75', 'USD', '1.000000', '0.00', '0.00'),
(16, 11, 9, 3, '3.00', 'USD', '1.000000', '0.00', '0.00'),
(17, 12, 1, 1, '2.00', 'USD', '1.000000', '0.00', '0.00'),
(18, 13, 16, 1, '4.00', 'USD', '1.000000', '0.00', '0.00'),
(19, 14, 15, 1, '2.75', 'USD', '1.000000', '0.00', '0.00'),
(20, 15, 12, 3, '2.25', 'USD', '1.000000', '0.00', '0.00'),
(21, 16, 13, 1, '2.75', 'USD', '1.000000', '0.00', '0.00'),
(22, 17, 1, 1, '2.00', 'USD', '1.000000', '0.00', '0.00'),
(23, 18, 6, 1, '2.50', 'USD', '1.000000', '0.00', '0.00'),
(24, 19, 1, 3, '2.00', 'USD', '1.000000', '0.00', '0.00'),
(25, 20, 1, 1, '2.00', 'USD', '1.000000', '0.00', '0.00'),
(26, 21, 6, 1, '2.50', 'USD', '1.000000', '0.00', '0.00'),
(27, 22, 1, 1, '2.00', 'USD', '1.000000', '0.00', '0.00'),
(28, 23, 1, 3, '2.00', 'USD', '1.000000', '0.00', '0.00'),
(29, 24, 6, 1, '2.50', 'USD', '1.000000', '0.00', '0.00'),
(30, 24, 1, 1, '2.00', 'USD', '1.000000', '0.00', '0.00'),
(31, 29, 4, 1, '3.00', 'USD', '1.000000', '0.00', '0.00'),
(32, 30, 19, 3, '32759.00', 'KHR', '4100.000000', '0.00', '0.00'),
(33, 31, 7, 1, '3.99', 'USD', '1.000000', '0.00', '0.00');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_code` varchar(50) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT '0',
  `category` varchar(50) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_code` (`product_code`),
  UNIQUE KEY `barcode` (`barcode`),
  UNIQUE KEY `qr_code` (`qr_code`)
) ENGINE=MyISAM AUTO_INCREMENT=24 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_code`, `barcode`, `qr_code`, `name`, `description`, `price`, `discount_price`, `stock_quantity`, `category`, `image_path`, `created_at`, `updated_at`) VALUES
(1, 'PROD001', 'BAR000001', 'QR000001', 'Coca Cola', 'Refreshing carbonated soft drink - 330ml can', '2.50', '2.00', 79, 'Beverages', 'images/coke.jpg', '2025-07-04 03:39:03', '2025-07-09 04:35:05'),
(2, 'PROD002', 'BAR000002', 'QR000002', 'Pepsi', 'Classic cola drink - 330ml can', '2.50', NULL, 83, 'Beverages', 'images/pepsi.jpg', '2025-07-04 03:39:03', '2025-07-07 04:34:35'),
(3, 'PROD003', 'BAR000003', 'QR000003', 'Sprite', 'Lemon-lime flavored soft drink - 330ml can', '2.00', '1.75', 56, 'Beverages', 'images/sprite.jpg', '2025-07-04 03:39:03', '2025-07-09 03:23:08'),
(4, 'PROD004', 'BAR000004', 'QR000004', 'Red Bull', 'Energy drink - 250ml can', '3.50', '3.00', 42, 'Beverages', 'images/redbull.jpg', '2025-07-04 03:39:03', '2025-07-09 07:32:43'),
(5, 'PROD005', 'BAR000005', 'QR000005', 'Mineral Water', 'Pure drinking water - 500ml bottle', '1.00', NULL, 200, 'Beverages', 'images/water.jpg', '2025-07-04 03:39:03', '2025-07-07 04:34:35'),
(6, 'PROD006', 'BAR000006', 'QR000006', 'Lays Classic', 'Original potato chips - 150g bag', '3.00', '2.50', 70, 'Snacks', 'images/lays.jpg', '2025-07-04 03:39:03', '2025-07-09 04:35:05'),
(7, 'PROD007', 'BAR000007', 'QR000007', 'Doritos Nacho', 'Cheese flavored tortilla chips - 180g bag', '4.50', '3.99', 45, 'Snacks', 'images/products/product_7_68678fa8ee106.jpg', '2025-07-04 03:39:03', '2025-07-09 08:04:18'),
(8, 'PROD008', 'BAR000008', 'QR000008', 'Pringles Original', 'Stackable potato chips - 110g can', '5.00', '4.50', 40, 'Snacks', NULL, '2025-07-04 03:39:03', '2025-07-07 04:34:35'),
(9, 'PROD009', 'BAR000009', 'QR000009', 'Oreo', 'Chocolate sandwich cookies', '3.50', '3.00', -2, 'Snacks', 'images/snickers.jpg', '2025-07-04 03:39:03', '2025-07-09 03:24:47'),
(10, 'PROD010', 'BAR000010', 'QR000010', 'KitKat', 'Chocolate wafer bar - 4 finger pack', '2.25', '2.00', 63, 'Snacks', 'images/kitkat.jpg', '2025-07-04 03:39:03', '2025-07-07 04:34:35'),
(11, 'PROD011', 'BAR000011', 'QR000011', 'Oreo Cookies', 'Chocolate sandwich cookies - 137g pack', '3.50', '3.00', 47, 'Snacks', 'images/oreo.jpg', '2025-07-04 03:47:30', '2025-07-09 02:18:27'),
(12, 'PROD012', 'BAR000012', 'QR000012', 'Twix', 'Chocolate bar with caramel and cookie - 50g', '2.50', '2.25', 67, 'Snacks', 'images/twix.jpg', '2025-07-04 03:47:30', '2025-07-09 04:10:00'),
(13, 'PROD013', 'BAR000013', 'QR000013', 'M&M\'s Peanut', 'Chocolate candies with peanuts - 100g bag', '3.00', '2.75', 43, 'Snacks', 'images/products/product_13_686790925ed75.jpg', '2025-07-04 03:47:30', '2025-07-09 04:10:58'),
(14, 'PROD014', 'BAR000014', 'QR000014', 'White Bread', 'Fresh white bread loaf - 500g', '2.50', NULL, 24, 'Groceries', NULL, '2025-07-04 03:47:30', '2025-07-08 07:29:54'),
(15, 'PROD015', 'BAR000015', 'QR000015', 'Milk', 'Fresh whole milk - 1 liter', '3.00', '2.75', 24, 'Groceries', NULL, '2025-07-04 03:47:30', '2025-07-09 04:08:46'),
(16, 'PROD016', 'BAR000016', 'QR000016', 'Eggs', 'Fresh farm eggs - 12 pieces', '4.50', '4.00', 36, 'Groceries', NULL, '2025-07-04 03:47:30', '2025-07-09 03:53:24'),
(17, 'PROD017', 'BAR000017', 'QR000017', 'Butter', 'Salted butter - 250g block', '3.75', '3.50', 34, 'Groceries', NULL, '2025-07-04 03:47:30', '2025-07-07 04:34:35'),
(18, 'PROD018', 'BAR000018', 'QR000018', 'Toothpaste', 'Fresh mint toothpaste - 100ml tube', '4.00', '3.50', 50, 'Personal Care', NULL, '2025-07-04 03:47:30', '2025-07-09 03:17:18'),
(19, 'PROD019', 'BAR000019', 'QR000019', 'Shampoo', 'Moisturizing shampoo - 400ml bottle', '8.50', '7.99', 27, 'Personal Care', NULL, '2025-07-04 03:47:30', '2025-07-09 07:59:45'),
(20, 'PROD020', 'BAR000020', 'QR000020', 'Soap Bar', 'Antibacterial soap bar - 100g', '2.00', '1.75', 80, 'Personal Care', NULL, '2025-07-04 03:47:30', '2025-07-07 04:34:35'),
(21, 'PROD021', 'BAR000021', 'QR000021', 'Testing', 'Testing', '20.00', '10.00', 0, 'Testing', 'images/products/product_21_686753bc59b78.jpg', '2025-07-04 03:59:12', '2025-07-07 04:34:35'),
(22, 'PROD023', 'BAR000022', 'QR000022', 'Testing1', 'Testing1', '30.00', '15.00', 0, 'Beverages', 'images/products/product_22_686753b1796ed.jpg', '2025-07-04 04:00:48', '2025-07-07 04:34:35');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(20) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(10,2) DEFAULT '0.00',
  `shipping_amount` decimal(10,2) DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','sent','received','cancelled') DEFAULT 'draft',
  `payment_terms` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `vendor_id` (`vendor_id`),
  KEY `order_date` (`order_date`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  KEY `idx_purchase_orders_date` (`order_date`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

DROP TABLE IF EXISTS `purchase_order_items`;
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `received_quantity` int(11) DEFAULT '0',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `purchase_order_id` (`purchase_order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `tax_rates`
--

DROP TABLE IF EXISTS `tax_rates`;
CREATE TABLE IF NOT EXISTS `tax_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(50) NOT NULL,
  `tax_rate` decimal(5,4) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `tax_rates`
--

INSERT INTO `tax_rates` (`id`, `tax_name`, `tax_rate`, `is_active`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Sales Tax', '0.0800', 1, 'Standard sales tax rate', '2025-07-07 04:44:47', '2025-07-07 04:44:47'),
(2, 'GST', '0.0500', 1, 'Goods and Services Tax', '2025-07-07 04:44:47', '2025-07-07 04:44:47'),
(3, 'PST', '0.0700', 1, 'Provincial Sales Tax', '2025-07-07 04:44:47', '2025-07-07 04:44:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','users','cashier','manager','customer') DEFAULT 'cashier',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_phone` (`phone`),
  KEY `idx_user_status` (`status`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@gmail.com', '$2y$10$CS9vAQgLGXBEBMInau3wpO0xhvzLUB0TraGYJ08rGDfBbpq5vkDFe', 'admin', NULL, 'admin', 'active', '2025-07-04 02:37:13', '2025-07-08 07:48:23'),
(2, 'chamrern', 'chamrern@gmail.com', '$2y$10$.iFJi/8wG305GsIfVeK.pe90nMZK9WZl9OKzTYp9EBEo.BECtB6jm', 'chamrern', '0967797762', 'admin', 'active', '2025-07-04 02:48:03', '2025-07-09 03:32:54'),
(3, 'staff', 'staff@gmail.com', '$2y$10$ErUu0aA6Zx18A7I8Wl8lrOl9g.vwGW/JcfMYGRZ/9FqDxaTGIfGFO', 'staff', NULL, 'cashier', 'active', '2025-07-04 02:48:36', '2025-07-08 07:52:22'),
(4, 'manager', 'manager@gmail.com', '$2y$10$b3FaHdMMEAnnRP9EGQ4LkeqrnVVnyecveJVnl6dOibAsX5St0J.KS', 'manager', NULL, 'manager', 'active', '2025-07-04 02:49:03', '2025-07-08 07:48:23'),
(10, 'customers1', 'customers1@gmail.com', '$2y$10$nLs8LQHzGuDWRtdyHAZQCelVU.NFFb/zvg/eficyhmvpPw1eOAVLa', 'customers1', NULL, 'customer', 'active', '2025-07-08 08:06:24', '2025-07-08 08:06:24'),
(12, 'customers2', 'customers2@gmail.com', '$2y$10$Y5p8HabNf/xaXIgBxpd0IeuKMrDarv4wSVcr6PbushQdjrk6JPyq2', 'customers2', '05454545455', 'customer', 'inactive', '2025-07-09 03:31:18', '2025-07-09 03:43:32');

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

DROP TABLE IF EXISTS `vendors`;
CREATE TABLE IF NOT EXISTS `vendors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_code` varchar(20) NOT NULL,
  `vendor_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(50) DEFAULT 'USA',
  `tax_id` varchar(50) DEFAULT NULL,
  `payment_terms` varchar(50) DEFAULT NULL,
  `credit_limit` decimal(12,2) DEFAULT '0.00',
  `is_active` tinyint(1) DEFAULT '1',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_code` (`vendor_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
