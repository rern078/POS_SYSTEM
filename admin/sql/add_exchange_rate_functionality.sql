-- Add exchange rate functionality to POS system
-- This script adds currency support and exchange rate tracking

USE pos_db;

-- Create exchange_rates table
CREATE TABLE IF NOT EXISTS exchange_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    base_currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    target_currency VARCHAR(3) NOT NULL,
    rate DECIMAL(10,6) NOT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    UNIQUE KEY unique_currency_pair (base_currency, target_currency)
);

-- Create currencies table for supported currencies
CREATE TABLE IF NOT EXISTS currencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(3) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    symbol VARCHAR(5) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add currency fields to orders table
ALTER TABLE orders ADD COLUMN currency_code VARCHAR(3) DEFAULT 'USD' AFTER total_amount;
ALTER TABLE orders ADD COLUMN exchange_rate DECIMAL(10,6) DEFAULT 1.000000 AFTER currency_code;
ALTER TABLE orders ADD COLUMN original_amount DECIMAL(10,2) DEFAULT NULL AFTER exchange_rate;

-- Add currency fields to order_items table
ALTER TABLE order_items ADD COLUMN currency_code VARCHAR(3) DEFAULT 'USD' AFTER price;
ALTER TABLE order_items ADD COLUMN exchange_rate DECIMAL(10,6) DEFAULT 1.000000 AFTER currency_code;

-- Insert default currencies
INSERT IGNORE INTO currencies (code, name, symbol, is_default) VALUES
('USD', 'US Dollar', '$', TRUE),
('EUR', 'Euro', '€', FALSE),
('GBP', 'British Pound', '£', FALSE),
('JPY', 'Japanese Yen', '¥', FALSE),
('CAD', 'Canadian Dollar', 'C$', FALSE),
('AUD', 'Australian Dollar', 'A$', FALSE),
('CHF', 'Swiss Franc', 'CHF', FALSE),
('CNY', 'Chinese Yuan', '¥', FALSE),
('INR', 'Indian Rupee', '₹', FALSE),
('KRW', 'South Korean Won', '₩', FALSE),
('SGD', 'Singapore Dollar', 'S$', FALSE),
('HKD', 'Hong Kong Dollar', 'HK$', FALSE),
('THB', 'Thai Baht', '฿', FALSE),
('PHP', 'Philippine Peso', '₱', FALSE),
('MYR', 'Malaysian Ringgit', 'RM', FALSE),
('IDR', 'Indonesian Rupiah', 'Rp', FALSE),
('VND', 'Vietnamese Dong', '₫', FALSE),
('KHR', 'Cambodian Riel', '៛', FALSE);

-- Insert default exchange rates (example rates - should be updated with real-time data)
INSERT IGNORE INTO exchange_rates (base_currency, target_currency, rate) VALUES
('USD', 'USD', 1.000000),
('USD', 'EUR', 0.850000),
('USD', 'GBP', 0.730000),
('USD', 'JPY', 110.000000),
('USD', 'CAD', 1.250000),
('USD', 'AUD', 1.350000),
('USD', 'CHF', 0.920000),
('USD', 'CNY', 6.450000),
('USD', 'INR', 74.500000),
('USD', 'KRW', 1150.000000),
('USD', 'SGD', 1.350000),
('USD', 'HKD', 7.780000),
('USD', 'THB', 33.500000),
('USD', 'PHP', 50.800000),
('USD', 'MYR', 4.150000),
('USD', 'IDR', 14250.000000),
('USD', 'VND', 23000.000000),
('USD', 'KHR', 4100.000000);

-- Add indexes for better performance
ALTER TABLE orders ADD INDEX idx_currency_code (currency_code);
ALTER TABLE order_items ADD INDEX idx_currency_code (currency_code);
ALTER TABLE exchange_rates ADD INDEX idx_base_currency (base_currency);
ALTER TABLE exchange_rates ADD INDEX idx_target_currency (target_currency);

-- Verify the changes
DESCRIBE exchange_rates;
DESCRIBE currencies;
DESCRIBE orders;
DESCRIBE order_items; 