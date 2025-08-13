-- Create Subcategories Table
-- This table will store subcategories with references to main categories

CREATE TABLE IF NOT EXISTS `subcategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `type` enum('Food','Clothes') NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int(11) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `type` (`type`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `fk_subcategory_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Insert sample subcategories for existing categories
-- First, let's get the category IDs and insert subcategories

-- Food Subcategories
INSERT INTO `subcategories` (`name`, `category_id`, `type`, `description`, `sort_order`) VALUES
-- Beverages subcategories
('Soft Drinks', 1, 'Food', 'Carbonated soft drinks and sodas', 1),
('Hot Beverages', 1, 'Food', 'Coffee, tea, and hot drinks', 2),
('Juices', 1, 'Food', 'Fresh and packaged fruit juices', 3),
('Water', 1, 'Food', 'Bottled and filtered water', 4),

-- Snacks subcategories
('Chips & Crisps', 2, 'Food', 'Potato chips and similar snacks', 1),
('Candy & Chocolate', 2, 'Food', 'Sweets and chocolate products', 2),
('Nuts & Seeds', 2, 'Food', 'Dried nuts and seeds', 3),
('Crackers', 2, 'Food', 'Biscuits and crackers', 4),

-- Groceries subcategories
('Rice & Grains', 3, 'Food', 'Rice, pasta, and grain products', 1),
('Canned Foods', 3, 'Food', 'Canned vegetables and fruits', 2),
('Dairy Products', 3, 'Food', 'Milk, cheese, and dairy items', 3),
('Baking Supplies', 3, 'Food', 'Flour, sugar, and baking ingredients', 4),

-- Personal Care subcategories
('Hair Care', 4, 'Food', 'Shampoo, conditioner, and hair products', 1),
('Skin Care', 4, 'Food', 'Soap, lotion, and skin products', 2),
('Oral Care', 4, 'Food', 'Toothpaste, toothbrushes, and dental care', 3),
('Feminine Care', 4, 'Food', 'Feminine hygiene products', 4);

-- Clothes Subcategories
INSERT INTO `subcategories` (`name`, `category_id`, `type`, `description`, `sort_order`) VALUES
-- Men's Clothing subcategories
('T-Shirts', 5, 'Clothes', 'Men\'s t-shirts and casual tops', 1),
('Shirts', 5, 'Clothes', 'Men\'s formal and casual shirts', 2),
('Pants', 5, 'Clothes', 'Men\'s pants and trousers', 3),
('Jackets', 5, 'Clothes', 'Men\'s jackets and coats', 4),
('Shorts', 5, 'Clothes', 'Men\'s shorts', 5),

-- Women's Clothing subcategories
('Dresses', 6, 'Clothes', 'Women\'s dresses', 1),
('Tops', 6, 'Clothes', 'Women\'s tops and blouses', 2),
('Jeans', 6, 'Clothes', 'Women\'s jeans and denim', 3),
('Skirts', 6, 'Clothes', 'Women\'s skirts', 4),
('Pants', 6, 'Clothes', 'Women\'s pants and trousers', 5),

-- Kids Clothing subcategories
('Boys Clothing', 7, 'Clothes', 'Clothing for boys', 1),
('Girls Clothing', 7, 'Clothes', 'Clothing for girls', 2),
('School Uniforms', 7, 'Clothes', 'School uniforms for children', 3),
('Baby Clothing', 7, 'Clothes', 'Clothing for babies and toddlers', 4),

-- Shoes subcategories
('Men\'s Shoes', 8, 'Clothes', 'Shoes for men', 1),
('Women\'s Shoes', 8, 'Clothes', 'Shoes for women', 2),
('Kids Shoes', 8, 'Clothes', 'Shoes for children', 3),
('Sports Shoes', 8, 'Clothes', 'Athletic and sports footwear', 4),
('Formal Shoes', 8, 'Clothes', 'Formal and dress shoes', 5),

-- Accessories subcategories
('Bags', 9, 'Clothes', 'Handbags, backpacks, and luggage', 1),
('Jewelry', 9, 'Clothes', 'Necklaces, rings, earrings', 2),
('Watches', 9, 'Clothes', 'Wristwatches and timepieces', 3),
('Belts', 9, 'Clothes', 'Belts and buckles', 4),
('Hats', 9, 'Clothes', 'Caps, hats, and headwear', 5);

-- Add subcategory_id column to products table
ALTER TABLE `products` ADD COLUMN `subcategory_id` int(11) DEFAULT NULL AFTER `category_id`;
ALTER TABLE `products` ADD INDEX `idx_subcategory_id` (`subcategory_id`);

-- Update existing products to use subcategory_id where possible
-- This will be done in a separate migration script if needed
