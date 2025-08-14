-- Add card payment fields to orders table
-- This script adds fields to store card payment information when orders are placed

USE pos_db;

-- Add card payment fields to orders table
ALTER TABLE orders ADD COLUMN card_type VARCHAR(20) DEFAULT NULL AFTER payment_method;
ALTER TABLE orders ADD COLUMN card_number VARCHAR(20) DEFAULT NULL AFTER card_type;
ALTER TABLE orders ADD COLUMN card_expiry VARCHAR(10) DEFAULT NULL AFTER card_number;
ALTER TABLE orders ADD COLUMN card_cvv VARCHAR(10) DEFAULT NULL AFTER card_expiry;
ALTER TABLE orders ADD COLUMN card_holder VARCHAR(100) DEFAULT NULL AFTER card_cvv;

-- Add indexes for better performance
ALTER TABLE orders ADD INDEX idx_card_type (card_type);
ALTER TABLE orders ADD INDEX idx_payment_method_card (payment_method, card_type);

-- Update payment_method enum to include more specific card types
-- Note: This is optional and depends on your current payment_method column type
-- If payment_method is VARCHAR(50), you can update it to include specific card types
-- ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cash', 'visa', 'mastercard', 'amex', 'discover', 'mobile', 'bank') DEFAULT 'cash';

-- Verify the changes
DESCRIBE orders;

-- Show sample data structure
SELECT 
    id,
    customer_name,
    payment_method,
    card_type,
    LEFT(card_number, 4) as card_last_4,
    card_expiry,
    card_holder,
    total_amount,
    created_at
FROM orders 
WHERE payment_method = 'card' 
ORDER BY created_at DESC 
LIMIT 5;
