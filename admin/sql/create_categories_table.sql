-- Create Categories Table for Subcategories
-- This table will store categories and subcategories for different product types

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('Food','Clothes') NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int(11) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `parent_id` (`parent_id`),
  KEY `is_active` (`is_active`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Insert sample categories for Food type
INSERT INTO `categories` (`name`, `type`, `parent_id`, `description`, `sort_order`) VALUES
('Beverages', 'Food', NULL, 'Drinks and beverages', 1),
('Snacks', 'Food', NULL, 'Snack foods and treats', 2),
('Groceries', 'Food', NULL, 'Basic grocery items', 3),
('Personal Care', 'Food', NULL, 'Personal care products', 4);

-- Insert sample categories for Clothes type
INSERT INTO `categories` (`name`, `type`, `parent_id`, `description`, `sort_order`) VALUES
('Men\'s Clothing', 'Clothes', NULL, 'Clothing for men', 1),
('Women\'s Clothing', 'Clothes', NULL, 'Clothing for women', 2),
('Kids Clothing', 'Clothes', NULL, 'Clothing for children', 3),
('Shoes', 'Clothes', NULL, 'Footwear for all ages', 4),
('Accessories', 'Clothes', NULL, 'Fashion accessories', 5);

-- Insert subcategories for Men's Clothing
INSERT INTO `categories` (`name`, `type`, `parent_id`, `description`, `sort_order`) VALUES
('T-Shirts', 'Clothes', 5, 'Men\'s t-shirts and casual tops', 1),
('Shirts', 'Clothes', 5, 'Men\'s formal and casual shirts', 2),
('Pants', 'Clothes', 5, 'Men\'s pants and trousers', 3),
('Jackets', 'Clothes', 5, 'Men\'s jackets and coats', 4),
('Shorts', 'Clothes', 5, 'Men\'s shorts', 5);

-- Insert subcategories for Women's Clothing
INSERT INTO `categories` (`name`, `type`, `parent_id`, `description`, `sort_order`) VALUES
('Dresses', 'Clothes', 6, 'Women\'s dresses', 1),
('Tops', 'Clothes', 6, 'Women\'s tops and blouses', 2),
('Jeans', 'Clothes', 6, 'Women\'s jeans and denim', 3),
('Skirts', 'Clothes', 6, 'Women\'s skirts', 4),
('Pants', 'Clothes', 6, 'Women\'s pants and trousers', 5);

-- Insert subcategories for Kids Clothing
INSERT INTO `categories` (`name`, `type`, `parent_id`, `description`, `sort_order`) VALUES
('Boys Clothing', 'Clothes', 7, 'Clothing for boys', 1),
('Girls Clothing', 'Clothes', 7, 'Clothing for girls', 2),
('School Uniforms', 'Clothes', 7, 'School uniforms for children', 3),
('Baby Clothing', 'Clothes', 7, 'Clothing for babies and toddlers', 4);

-- Insert subcategories for Shoes
INSERT INTO `categories` (`name`, `type`, `parent_id`, `description`, `sort_order`) VALUES
('Men\'s Shoes', 'Clothes', 8, 'Shoes for men', 1),
('Women\'s Shoes', 'Clothes', 8, 'Shoes for women', 2),
('Kids Shoes', 'Clothes', 8, 'Shoes for children', 3),
('Sports Shoes', 'Clothes', 8, 'Athletic and sports footwear', 4),
('Formal Shoes', 'Clothes', 8, 'Formal and dress shoes', 5);

-- Insert subcategories for Accessories
INSERT INTO `categories` (`name`, `type`, `parent_id`, `description`, `sort_order`) VALUES
('Bags', 'Clothes', 9, 'Handbags, backpacks, and luggage', 1),
('Jewelry', 'Clothes', 9, 'Necklaces, rings, earrings', 2),
('Watches', 'Clothes', 9, 'Wristwatches and timepieces', 3),
('Belts', 'Clothes', 9, 'Belts and buckles', 4),
('Hats', 'Clothes', 9, 'Caps, hats, and headwear', 5);

-- Add category_id column to products table if it doesn't exist
ALTER TABLE `products` ADD COLUMN `category_id` int(11) DEFAULT NULL AFTER `category`;
ALTER TABLE `products` ADD INDEX `idx_category_id` (`category_id`);

-- Update existing products to use category_id
-- This will be done in a separate migration script
