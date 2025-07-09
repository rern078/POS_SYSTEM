-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 07, 2025 at 09:06 AM
-- Server version: 5.7.36
-- PHP Version: 8.1.0

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
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) DEFAULT '0.00',
  `tax_amount` decimal(10,2) DEFAULT '0.00',
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `journal_entry_id` int(11) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_name`, `customer_email`, `total_amount`, `subtotal`, `tax_amount`, `discount_amount`, `journal_entry_id`, `status`, `payment_method`, `created_at`, `updated_at`) VALUES
(1, 'bb', 'chamrern@gmail.com', '4.00', '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '2025-07-04 08:03:38', '2025-07-04 08:03:38'),
(2, 'aa', 'tiengchamrern2@gmail.com', '8.00', '0.00', '0.00', '0.00', NULL, 'completed', 'card', '2025-07-04 08:04:10', '2025-07-04 08:04:10'),
(3, 'chamrern', 'chamrer@gmail.com', '20.50', '0.00', '0.00', '0.00', NULL, 'completed', 'cash', '2025-07-04 08:14:36', '2025-07-04 08:14:36'),
(4, 'ss', 'ss@gmail.com', '12.50', '0.00', '0.00', '0.00', NULL, 'completed', 'card', '2025-07-05 04:56:12', '2025-07-05 04:56:12');

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
  `cost_price` decimal(10,2) DEFAULT '0.00',
  `profit_margin` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `cost_price`, `profit_margin`) VALUES
(1, 1, 1, 2, '2.00', '0.00', '0.00'),
(2, 2, 16, 2, '4.00', '0.00', '0.00'),
(3, 3, 11, 2, '3.00', '0.00', '0.00'),
(4, 3, 2, 2, '2.50', '0.00', '0.00'),
(5, 3, 4, 2, '3.00', '0.00', '0.00'),
(6, 3, 3, 2, '1.75', '0.00', '0.00'),
(7, 4, 14, 1, '2.50', '0.00', '0.00'),
(8, 4, 16, 1, '4.00', '0.00', '0.00'),
(9, 4, 11, 2, '3.00', '0.00', '0.00');

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
(1, 'PROD001', 'BAR000001', 'QR000001', 'Coca Cola', 'Refreshing carbonated soft drink - 330ml can', '2.50', '2.00', 90, 'Beverages', 'images/coke.jpg', '2025-07-04 03:39:03', '2025-07-07 04:34:35'),
(2, 'PROD002', 'BAR000002', 'QR000002', 'Pepsi', 'Classic cola drink - 330ml can', '2.50', NULL, 83, 'Beverages', 'images/pepsi.jpg', '2025-07-04 03:39:03', '2025-07-07 04:34:35'),
(3, 'PROD003', 'BAR000003', 'QR000003', 'Sprite', 'Lemon-lime flavored soft drink - 330ml can', '2.00', '1.75', 58, 'Beverages', 'images/sprite.jpg', '2025-07-04 03:39:03', '2025-07-07 04:34:35'),
(4, 'PROD004', 'BAR000004', 'QR000004', 'Red Bull', 'Energy drink - 250ml can', '3.50', '3.00', 43, 'Beverages', 'images/redbull.jpg', '2025-07-04 03:39:03', '2025-07-07 04:34:35'),
(5, 'PROD005', 'BAR000005', 'QR000005', 'Mineral Water', 'Pure drinking water - 500ml bottle', '1.00', NULL, 200, 'Beverages', 'images/water.jpg', '2025-07-04 03:39:03', '2025-07-07 04:34:35'),
(6, 'PROD006', 'BAR000006', 'QR000006', 'Lays Classic', 'Original potato chips - 150g bag', '3.00', '2.50', 73, 'Snacks', 'images/lays.jpg', '2025-07-04 03:39:03', '2025-07-07 04:34:35'),
(7, 'PROD007', 'BAR000007', 'QR000007', 'Doritos Nacho', 'Cheese flavored tortilla chips - 180g bag', '4.50', '3.99', 49, 'Snacks', 'images/products/product_7_68678fa8ee106.jpg', '2025-07-04 03:39:03', '2025-07-07 04:34:35'),
(8, 'PROD008', 'BAR000008', 'QR000008', 'Pringles Original', 'Stackable potato chips - 110g can', '5.00', '4.50', 40, 'Snacks', NULL, '2025-07-04 03:39:03', '2025-07-07 04:34:35'),
(9, 'PROD009', 'BAR000009', 'QR000009', 'Oreo', 'Chocolate sandwich cookies', '3.50', '3.00', 1, 'Snacks', 'images/snickers.jpg', '2025-07-04 03:39:03', '2025-07-07 04:34:35'),
(10, 'PROD010', 'BAR000010', 'QR000010', 'KitKat', 'Chocolate wafer bar - 4 finger pack', '2.25', '2.00', 63, 'Snacks', 'images/kitkat.jpg', '2025-07-04 03:39:03', '2025-07-07 04:34:35'),
(11, 'PROD011', 'BAR000011', 'QR000011', 'Oreo Cookies', 'Chocolate sandwich cookies - 137g pack', '3.50', '3.00', 51, 'Snacks', 'images/oreo.jpg', '2025-07-04 03:47:30', '2025-07-07 04:34:35'),
(12, 'PROD012', 'BAR000012', 'QR000012', 'Twix', 'Chocolate bar with caramel and cookie - 50g', '2.50', '2.25', 70, 'Snacks', 'images/twix.jpg', '2025-07-04 03:47:30', '2025-07-07 04:34:35'),
(13, 'PROD013', 'BAR000013', 'QR000013', 'M&M\'s Peanut', 'Chocolate candies with peanuts - 100g bag', '3.00', '2.75', 44, 'Snacks', 'images/products/product_13_686790925ed75.jpg', '2025-07-04 03:47:30', '2025-07-07 04:34:35'),
(14, 'PROD014', 'BAR000014', 'QR000014', 'White Bread', 'Fresh white bread loaf - 500g', '2.50', NULL, 29, 'Groceries', NULL, '2025-07-04 03:47:30', '2025-07-07 04:34:35'),
(15, 'PROD015', 'BAR000015', 'QR000015', 'Milk', 'Fresh whole milk - 1 liter', '3.00', '2.75', 25, 'Groceries', NULL, '2025-07-04 03:47:30', '2025-07-07 04:34:35'),
(16, 'PROD016', 'BAR000016', 'QR000016', 'Eggs', 'Fresh farm eggs - 12 pieces', '4.50', '4.00', 37, 'Groceries', NULL, '2025-07-04 03:47:30', '2025-07-07 04:34:35'),
(17, 'PROD017', 'BAR000017', 'QR000017', 'Butter', 'Salted butter - 250g block', '3.75', '3.50', 34, 'Groceries', NULL, '2025-07-04 03:47:30', '2025-07-07 04:34:35'),
(18, 'PROD018', 'BAR000018', 'QR000018', 'Toothpaste', 'Fresh mint toothpaste - 100ml tube', '4.00', '3.50', 60, 'Personal Care', NULL, '2025-07-04 03:47:30', '2025-07-07 04:34:35'),
(19, 'PROD019', 'BAR000019', 'QR000019', 'Shampoo', 'Moisturizing shampoo - 400ml bottle', '8.50', '7.99', 30, 'Personal Care', NULL, '2025-07-04 03:47:30', '2025-07-07 04:34:35'),
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
  `role` enum('admin','users','cashier','manager','customer') DEFAULT 'cashier',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@gmail.com', '$2y$10$CS9vAQgLGXBEBMInau3wpO0xhvzLUB0TraGYJ08rGDfBbpq5vkDFe', 'admin', '2025-07-04 02:37:13', '2025-07-04 10:01:27'),
(2, 'chamrern', 'chamrern@gmail.com', '$2y$10$.iFJi/8wG305GsIfVeK.pe90nMZK9WZl9OKzTYp9EBEo.BECtB6jm', 'admin', '2025-07-04 02:48:03', '2025-07-04 02:48:03'),
(3, 'users', 'user@gmail.com', '$2y$10$ErUu0aA6Zx18A7I8Wl8lrOl9g.vwGW/JcfMYGRZ/9FqDxaTGIfGFO', 'cashier', '2025-07-04 02:48:36', '2025-07-04 02:48:36'),
(4, 'manager', 'manager@gmail.com', '$2y$10$b3FaHdMMEAnnRP9EGQ4LkeqrnVVnyecveJVnl6dOibAsX5St0J.KS', 'manager', '2025-07-04 02:49:03', '2025-07-04 09:58:53'),
(6, 'users1@gmail.com', 'user1@gmail.com', '$2y$10$71mhzh/KNaieAuYnMzIkVevl088Xzq1Zl9mdXjcqK0SczxDih5G.G', 'cashier', '2025-07-05 03:35:17', '2025-07-05 03:35:17');

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
