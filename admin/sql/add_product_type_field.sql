-- Add product type field to products table
-- This script adds a 'type' field to the products table with options for 'Food' and 'Clothes'

ALTER TABLE `products` 
ADD COLUMN `type` ENUM('Food', 'Clothes') DEFAULT 'Food' AFTER `category`;

-- Update existing products to have appropriate types based on their categories
UPDATE `products` SET `type` = 'Food' WHERE `category` IN ('Beverages', 'Snacks', 'Groceries');
UPDATE `products` SET `type` = 'Clothes' WHERE `category` IN ('Clothing', 'Apparel', 'Fashion');

-- For products with other categories, default to 'Food'
UPDATE `products` SET `type` = 'Food' WHERE `type` IS NULL OR `type` = '';

-- Add index for better performance when filtering by type
ALTER TABLE `products` ADD INDEX `idx_product_type` (`type`);
