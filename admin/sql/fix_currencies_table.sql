-- Fix Currencies Table Script
-- This script will fix any issues with the currencies table

USE pos_db;

-- Step 1: Create currencies table if it doesn't exist
CREATE TABLE IF NOT EXISTS currencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(3) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    symbol VARCHAR(5) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Step 2: Add symbol column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'pos_db' 
     AND TABLE_NAME = 'currencies' 
     AND COLUMN_NAME = 'symbol') = 0,
    'ALTER TABLE currencies ADD COLUMN symbol VARCHAR(5) NOT NULL DEFAULT "$" AFTER name',
    'SELECT "symbol column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Insert default currencies if table is empty
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

-- Step 4: Fix any NULL or empty symbol values
UPDATE currencies SET symbol = '$' WHERE code = 'USD' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = '€' WHERE code = 'EUR' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = '£' WHERE code = 'GBP' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = '¥' WHERE code = 'JPY' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = 'C$' WHERE code = 'CAD' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = 'A$' WHERE code = 'AUD' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = 'CHF' WHERE code = 'CHF' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = '¥' WHERE code = 'CNY' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = '₹' WHERE code = 'INR' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = '₩' WHERE code = 'KRW' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = 'S$' WHERE code = 'SGD' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = 'HK$' WHERE code = 'HKD' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = '฿' WHERE code = 'THB' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = '₱' WHERE code = 'PHP' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = 'RM' WHERE code = 'MYR' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = 'Rp' WHERE code = 'IDR' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = '₫' WHERE code = 'VND' AND (symbol IS NULL OR symbol = '');
UPDATE currencies SET symbol = '៛' WHERE code = 'KHR' AND (symbol IS NULL OR symbol = '');

-- Step 5: Set default symbol for any remaining NULL values
UPDATE currencies SET symbol = '$' WHERE symbol IS NULL OR symbol = '';

-- Step 6: Verify the fix
SELECT 'Currencies table status:' as message;
SELECT COUNT(*) as total_currencies FROM currencies;
SELECT COUNT(*) as null_symbols FROM currencies WHERE symbol IS NULL OR symbol = '';

-- Step 7: Show sample data
SELECT 'Sample currencies:' as message;
SELECT id, code, name, symbol, is_default FROM currencies LIMIT 5;

-- Step 8: Ensure only one default currency
UPDATE currencies SET is_default = FALSE WHERE code != 'USD';
UPDATE currencies SET is_default = TRUE WHERE code = 'USD'; 