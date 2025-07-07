-- Add comprehensive accounting tables to POS system

-- Chart of Accounts table
CREATE TABLE IF NOT EXISTS `chart_of_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_code` varchar(20) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_type` enum('asset','liability','equity','revenue','expense') NOT NULL,
  `parent_account_id` int(11) DEFAULT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_code` (`account_code`),
  KEY `parent_account_id` (`parent_account_id`),
  KEY `account_type` (`account_type`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Insert default chart of accounts
INSERT INTO `chart_of_accounts` (`account_code`, `account_name`, `account_type`, `parent_account_id`, `description`) VALUES
-- Assets
('1000', 'Current Assets', 'asset', NULL, 'All current assets'),
('1100', 'Cash and Cash Equivalents', 'asset', 1, 'Cash, bank accounts, petty cash'),
('1200', 'Accounts Receivable', 'asset', 1, 'Money owed by customers'),
('1300', 'Inventory', 'asset', 1, 'Product inventory'),
('1400', 'Prepaid Expenses', 'asset', 1, 'Prepaid rent, insurance, etc.'),

-- Liabilities
('2000', 'Current Liabilities', 'liability', NULL, 'All current liabilities'),
('2100', 'Accounts Payable', 'liability', 6, 'Money owed to suppliers'),
('2200', 'Sales Tax Payable', 'liability', 6, 'Sales tax collected'),
('2300', 'Accrued Expenses', 'liability', 6, 'Accrued wages, utilities, etc.'),

-- Equity
('3000', 'Owner Equity', 'equity', NULL, 'Owner investments and retained earnings'),
('3100', 'Owner Investment', 'equity', 10, 'Initial and additional owner investments'),
('3200', 'Retained Earnings', 'equity', 10, 'Accumulated profits/losses'),

-- Revenue
('4000', 'Revenue', 'revenue', NULL, 'All revenue accounts'),
('4100', 'Sales Revenue', 'revenue', 13, 'Product sales revenue'),
('4200', 'Discount Revenue', 'revenue', 13, 'Revenue from discounts given'),

-- Expenses
('5000', 'Expenses', 'expense', NULL, 'All expense accounts'),
('5100', 'Cost of Goods Sold', 'expense', 16, 'Cost of products sold'),
('5200', 'Operating Expenses', 'expense', 16, 'General operating expenses'),
('5210', 'Rent Expense', 'expense', 18, 'Store rent'),
('5220', 'Utilities Expense', 'expense', 18, 'Electricity, water, internet'),
('5230', 'Salaries and Wages', 'expense', 18, 'Employee salaries and wages'),
('5240', 'Advertising Expense', 'expense', 18, 'Marketing and advertising costs'),
('5250', 'Office Supplies', 'expense', 18, 'Office and store supplies'),
('5260', 'Insurance Expense', 'expense', 18, 'Business insurance'),
('5270', 'Depreciation Expense', 'expense', 18, 'Equipment and furniture depreciation');

-- Journal Entries table
CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_number` varchar(20) NOT NULL,
  `entry_date` date NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `description` text,
  `entry_type` enum('sale','purchase','expense','adjustment','transfer','opening_balance') NOT NULL,
  `total_debit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entry_number` (`entry_number`),
  KEY `entry_date` (`entry_date`),
  KEY `entry_type` (`entry_type`),
  KEY `created_by` (`created_by`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Journal Entry Details table
CREATE TABLE IF NOT EXISTS `journal_entry_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journal_entry_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `debit_amount` decimal(12,2) DEFAULT 0.00,
  `credit_amount` decimal(12,2) DEFAULT 0.00,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `account_id` (`account_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Expenses table
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_number` varchar(20) NOT NULL,
  `expense_date` date NOT NULL,
  `vendor_name` varchar(100) DEFAULT NULL,
  `vendor_email` varchar(100) DEFAULT NULL,
  `vendor_phone` varchar(20) DEFAULT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
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
  KEY `created_by` (`created_by`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Vendors table
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
  `credit_limit` decimal(12,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_code` (`vendor_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Purchase Orders table
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(20) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `shipping_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
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
  KEY `created_by` (`created_by`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Purchase Order Items table
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `received_quantity` int(11) DEFAULT 0,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `purchase_order_id` (`purchase_order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Financial Periods table
CREATE TABLE IF NOT EXISTS `financial_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_closed` tinyint(1) DEFAULT 0,
  `closing_date` timestamp NULL DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `start_date` (`start_date`),
  KEY `end_date` (`end_date`),
  KEY `is_closed` (`is_closed`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- General Ledger table (for account balances)
CREATE TABLE IF NOT EXISTS `general_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `opening_debit` decimal(12,2) DEFAULT 0.00,
  `opening_credit` decimal(12,2) DEFAULT 0.00,
  `period_debit` decimal(12,2) DEFAULT 0.00,
  `period_credit` decimal(12,2) DEFAULT 0.00,
  `closing_debit` decimal(12,2) DEFAULT 0.00,
  `closing_credit` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_period` (`account_id`, `period_id`),
  KEY `account_id` (`account_id`),
  KEY `period_id` (`period_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Tax Rates table
CREATE TABLE IF NOT EXISTS `tax_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(50) NOT NULL,
  `tax_rate` decimal(5,4) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Insert default tax rates
INSERT INTO `tax_rates` (`tax_name`, `tax_rate`, `description`) VALUES
('Sales Tax', 0.0800, 'Standard sales tax rate'),
('GST', 0.0500, 'Goods and Services Tax'),
('PST', 0.0700, 'Provincial Sales Tax');

-- Update existing orders table to include accounting fields
ALTER TABLE `orders` 
ADD COLUMN `subtotal` decimal(10,2) DEFAULT 0.00 AFTER `total_amount`,
ADD COLUMN `tax_amount` decimal(10,2) DEFAULT 0.00 AFTER `subtotal`,
ADD COLUMN `discount_amount` decimal(10,2) DEFAULT 0.00 AFTER `tax_amount`,
ADD COLUMN `journal_entry_id` int(11) DEFAULT NULL AFTER `discount_amount`;

-- Update existing order_items table to include cost tracking
ALTER TABLE `order_items` 
ADD COLUMN `cost_price` decimal(10,2) DEFAULT 0.00 AFTER `price`,
ADD COLUMN `profit_margin` decimal(10,2) DEFAULT 0.00 AFTER `cost_price`;

-- Create indexes for better performance
CREATE INDEX idx_journal_entries_date ON journal_entries(entry_date);
CREATE INDEX idx_expenses_date ON expenses(expense_date);
CREATE INDEX idx_purchase_orders_date ON purchase_orders(order_date);
CREATE INDEX idx_general_ledger_period ON general_ledger(period_id); 