-- Add Sample Clothing Products for Testing
-- This script adds various clothing products to test the clothing filter feature

-- First, ensure the type field exists
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `type` ENUM('Food', 'Clothes') DEFAULT 'Food' AFTER `category`;

-- Add clothing-specific fields if they don't exist
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `size` VARCHAR(20) DEFAULT NULL AFTER `type`;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `weight` DECIMAL(8,2) DEFAULT NULL AFTER `size`;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `color` VARCHAR(50) DEFAULT NULL AFTER `weight`;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `material` VARCHAR(100) DEFAULT NULL AFTER `color`;

-- Insert sample clothing products
INSERT INTO `products` (`product_code`, `barcode`, `qr_code`, `name`, `description`, `price`, `discount_price`, `stock_quantity`, `category`, `type`, `size`, `weight`, `color`, `material`, `image_path`) VALUES
-- Apparel Category
('CLO001', '1234567890123', 'CLO001_QR', 'Men\'s Cotton T-Shirt', 'Comfortable 100% cotton t-shirt for men, available in various sizes and colors', 25.00, 20.00, 50, 'Apparel', 'Clothes', 'M', 250.00, 'Blue', 'Cotton', 'images/placeholder.jpg'),
('CLO002', '1234567890124', 'CLO002_QR', 'Women\'s Denim Jeans', 'Classic blue denim jeans for women with perfect fit and durability', 45.00, NULL, 30, 'Apparel', 'Clothes', 'L', 400.00, 'Blue', 'Denim', 'images/placeholder.jpg'),
('CLO003', '1234567890125', 'CLO003_QR', 'Kids Winter Jacket', 'Warm and cozy winter jacket for children, waterproof and insulated', 35.00, 30.00, 25, 'Apparel', 'Clothes', 'S', 500.00, 'Red', 'Polyester', 'images/placeholder.jpg'),
('CLO004', '1234567890126', 'CLO004_QR', 'Unisex Hoodie', 'Comfortable hoodie suitable for both men and women, great for casual wear', 35.00, NULL, 45, 'Apparel', 'Clothes', 'L', 350.00, 'Gray', 'Cotton Blend', 'images/placeholder.jpg'),
('CLO005', '1234567890127', 'CLO005_QR', 'Men\'s Casual Pants', 'Comfortable casual pants for men, perfect for everyday wear', 32.00, 28.00, 40, 'Apparel', 'Clothes', '32', 300.00, 'Khaki', 'Cotton', 'images/placeholder.jpg'),

-- Clothing Category
('CLO006', '1234567890128', 'CLO006_QR', 'Men\'s Formal Shirt', 'Elegant formal shirt for men, suitable for office and special occasions', 40.00, NULL, 40, 'Clothing', 'Clothes', 'L', 280.00, 'White', 'Cotton', 'images/placeholder.jpg'),
('CLO007', '1234567890129', 'CLO007_QR', 'Women\'s Blouse', 'Elegant blouse for women, suitable for professional and casual settings', 28.00, NULL, 30, 'Clothing', 'Clothes', 'M', 200.00, 'Pink', 'Silk', 'images/placeholder.jpg'),
('CLO008', '1234567890130', 'CLO008_QR', 'Women\'s Summer Dress', 'Light and breezy summer dress perfect for warm weather', 30.00, 25.00, 35, 'Clothing', 'Clothes', 'S', 250.00, 'Yellow', 'Cotton', 'images/placeholder.jpg'),
('CLO009', '1234567890131', 'CLO009_QR', 'Men\'s Business Suit', 'Professional business suit for men, includes jacket and pants', 120.00, 100.00, 15, 'Clothing', 'Clothes', 'L', 800.00, 'Navy', 'Wool', 'images/placeholder.jpg'),
('CLO010', '1234567890132', 'CLO010_QR', 'Women\'s Cardigan', 'Soft and warm cardigan for women, perfect for layering', 35.00, NULL, 25, 'Clothing', 'Clothes', 'M', 300.00, 'Beige', 'Wool Blend', 'images/placeholder.jpg'),

-- Fashion Category
('CLO011', '1234567890133', 'CLO011_QR', 'Designer Handbag', 'Stylish designer handbag for women, perfect for any occasion', 80.00, 65.00, 20, 'Fashion', 'Clothes', NULL, 500.00, 'Black', 'Leather', 'images/placeholder.jpg'),
('CLO012', '1234567890134', 'CLO012_QR', 'Fashion Sunglasses', 'Trendy sunglasses with UV protection', 45.00, NULL, 30, 'Fashion', 'Clothes', NULL, 50.00, 'Brown', 'Plastic', 'images/placeholder.jpg'),
('CLO013', '1234567890135', 'CLO013_QR', 'Statement Necklace', 'Elegant statement necklace for women', 25.00, 20.00, 40, 'Fashion', 'Clothes', NULL, 30.00, 'Gold', 'Metal', 'images/placeholder.jpg'),
('CLO014', '1234567890136', 'CLO014_QR', 'Designer Watch', 'Luxury designer watch for men and women', 150.00, 120.00, 10, 'Fashion', 'Clothes', NULL, 80.00, 'Silver', 'Stainless Steel', 'images/placeholder.jpg'),
('CLO015', '1234567890137', 'CLO015_QR', 'Fashion Belt', 'Stylish leather belt for men and women', 30.00, NULL, 35, 'Fashion', 'Clothes', 'L', 100.00, 'Brown', 'Leather', 'images/placeholder.jpg'),

-- Sports Wear Category
('CLO016', '1234567890138', 'CLO016_QR', 'Men\'s Sports Shorts', 'Lightweight sports shorts for men, perfect for workouts and sports activities', 20.00, 18.00, 60, 'Sports Wear', 'Clothes', 'M', 150.00, 'Black', 'Polyester', 'images/placeholder.jpg'),
('CLO017', '1234567890139', 'CLO017_QR', 'Women\'s Yoga Pants', 'Comfortable yoga pants for women, perfect for exercise and casual wear', 25.00, NULL, 45, 'Sports Wear', 'Clothes', 'M', 200.00, 'Black', 'Spandex Blend', 'images/placeholder.jpg'),
('CLO018', '1234567890140', 'CLO018_QR', 'Running Shoes', 'Professional running shoes for men and women', 80.00, 65.00, 30, 'Sports Wear', 'Clothes', '42', 300.00, 'White', 'Mesh', 'images/placeholder.jpg'),
('CLO019', '1234567890141', 'CLO019_QR', 'Sports Jersey', 'Team sports jersey for men and women', 35.00, 30.00, 25, 'Sports Wear', 'Clothes', 'L', 250.00, 'Blue', 'Polyester', 'images/placeholder.jpg'),
('CLO020', '1234567890142', 'CLO020_QR', 'Gym Bag', 'Durable gym bag for carrying sports equipment', 40.00, NULL, 20, 'Sports Wear', 'Clothes', NULL, 500.00, 'Black', 'Nylon', 'images/placeholder.jpg'),

-- Uniforms Category
('CLO021', '1234567890143', 'CLO021_QR', 'Kids School Uniform', 'Complete school uniform set for children, includes shirt and pants', 50.00, 45.00, 20, 'Uniforms', 'Clothes', 'S', 400.00, 'Navy', 'Polyester', 'images/placeholder.jpg'),
('CLO022', '1234567890144', 'CLO022_QR', 'Security Guard Uniform', 'Professional security guard uniform', 60.00, NULL, 15, 'Uniforms', 'Clothes', 'L', 600.00, 'Black', 'Polyester', 'images/placeholder.jpg'),
('CLO023', '1234567890145', 'CLO023_QR', 'Chef\'s Uniform', 'Complete chef uniform including hat and apron', 45.00, 40.00, 25, 'Uniforms', 'Clothes', 'M', 350.00, 'White', 'Cotton', 'images/placeholder.jpg'),
('CLO024', '1234567890146', 'CLO024_QR', 'Medical Scrubs', 'Comfortable medical scrubs for healthcare workers', 35.00, NULL, 30, 'Uniforms', 'Clothes', 'M', 300.00, 'Blue', 'Cotton Blend', 'images/placeholder.jpg'),
('CLO025', '1234567890147', 'CLO025_QR', 'Police Uniform', 'Professional police uniform set', 80.00, 70.00, 10, 'Uniforms', 'Clothes', 'L', 800.00, 'Navy', 'Polyester', 'images/placeholder.jpg');

-- Update existing products to have appropriate types if not already set
UPDATE `products` SET `type` = 'Food' WHERE `type` IS NULL OR `type` = '';

-- Add indexes for better performance
ALTER TABLE `products` ADD INDEX IF NOT EXISTS `idx_product_type` (`type`);
ALTER TABLE `products` ADD INDEX IF NOT EXISTS `idx_product_category` (`category`);
ALTER TABLE `products` ADD INDEX IF NOT EXISTS `idx_product_size` (`size`);
ALTER TABLE `products` ADD INDEX IF NOT EXISTS `idx_product_color` (`color`);
ALTER TABLE `products` ADD INDEX IF NOT EXISTS `idx_product_material` (`material`);
