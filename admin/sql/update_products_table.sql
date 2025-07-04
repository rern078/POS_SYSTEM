-- Update products table to add missing fields
-- Run this script if you have existing data

-- Add product_code column
ALTER TABLE products ADD COLUMN product_code VARCHAR(50) UNIQUE AFTER id;

-- Add discount_price column
ALTER TABLE products ADD COLUMN discount_price DECIMAL(10,2) DEFAULT NULL AFTER price;

-- Add image_path column
ALTER TABLE products ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER category;

-- Update existing products with default product codes if they don't have one
UPDATE products SET product_code = CONCAT('PROD', LPAD(id, 3, '0')) WHERE product_code IS NULL OR product_code = '';

-- Add some sample discount prices for demonstration
UPDATE products SET discount_price = price * 0.9 WHERE id IN (1, 3, 5); -- 10% discount on some products 