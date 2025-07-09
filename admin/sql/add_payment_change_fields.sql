-- Add payment change calculation fields to orders table
-- This script adds amount_tendered and change_amount columns for cash payment processing

USE pos_db;

-- Add amount_tendered column
ALTER TABLE orders ADD COLUMN amount_tendered DECIMAL(10,2) DEFAULT 0.00 AFTER payment_method;

-- Add change_amount column
ALTER TABLE orders ADD COLUMN change_amount DECIMAL(10,2) DEFAULT 0.00 AFTER amount_tendered;

-- Add user_id column if it doesn't exist (for logged-in customers)
ALTER TABLE orders ADD COLUMN user_id INT(11) DEFAULT NULL AFTER id;

-- Add index for better performance
ALTER TABLE orders ADD INDEX idx_payment_method (payment_method);
ALTER TABLE orders ADD INDEX idx_user_id (user_id);

-- Verify the changes
DESCRIBE orders; 