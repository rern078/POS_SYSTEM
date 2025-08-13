-- Add clothing-specific fields to products table
-- This script adds size and weight fields for clothing products

-- Add size field for clothing products
ALTER TABLE `products` 
ADD COLUMN `size` VARCHAR(20) DEFAULT NULL AFTER `type`;

-- Add weight field for clothing products (in grams)
ALTER TABLE `products` 
ADD COLUMN `weight` DECIMAL(8,2) DEFAULT NULL AFTER `size`;

-- Add color field for clothing products
ALTER TABLE `products` 
ADD COLUMN `color` VARCHAR(50) DEFAULT NULL AFTER `weight`;

-- Add material field for clothing products
ALTER TABLE `products` 
ADD COLUMN `material` VARCHAR(100) DEFAULT NULL AFTER `color`;

-- Add indexes for better performance when filtering by clothing attributes
ALTER TABLE `products` ADD INDEX `idx_product_size` (`size`);
ALTER TABLE `products` ADD INDEX `idx_product_color` (`color`);
ALTER TABLE `products` ADD INDEX `idx_product_material` (`material`);

-- Update existing clothing products with sample data (optional)
-- UPDATE `products` SET `size` = 'M', `weight` = 250.00, `color` = 'Blue', `material` = 'Cotton' WHERE `type` = 'Clothes' AND `id` = 1;
