-- Sample Clothes Products Data
-- This file contains 10 sample clothing products for the POS system

INSERT INTO `products` (`product_code`, `barcode`, `qr_code`, `name`, `description`, `price`, `discount_price`, `stock_quantity`, `category`, `type`, `image_path`) VALUES
('CLO001', '1234567890123', 'CLO001_QR', 'Men\'s Cotton T-Shirt', 'Comfortable 100% cotton t-shirt for men, available in various sizes and colors', 25.00, 20.00, 50, 'Apparel', 'Clothes', ''),
('CLO002', '1234567890124', 'CLO002_QR', 'Women\'s Denim Jeans', 'Classic blue denim jeans for women with perfect fit and durability', 45.00, NULL, 30, 'Clothing', 'Clothes', ''),
('CLO003', '1234567890125', 'CLO003_QR', 'Kids Winter Jacket', 'Warm and cozy winter jacket for children, waterproof and insulated', 35.00, 30.00, 25, 'Apparel', 'Clothes', ''),
('CLO004', '1234567890126', 'CLO004_QR', 'Men\'s Formal Shirt', 'Elegant formal shirt for men, suitable for office and special occasions', 40.00, NULL, 40, 'Clothing', 'Clothes', ''),
('CLO005', '1234567890127', 'CLO005_QR', 'Women\'s Summer Dress', 'Light and breezy summer dress perfect for warm weather', 30.00, 25.00, 35, 'Fashion', 'Clothes', ''),
('CLO006', '1234567890128', 'CLO006_QR', 'Unisex Hoodie', 'Comfortable hoodie suitable for both men and women, great for casual wear', 35.00, NULL, 45, 'Apparel', 'Clothes', ''),
('CLO007', '1234567890129', 'CLO007_QR', 'Men\'s Sports Shorts', 'Lightweight sports shorts for men, perfect for workouts and sports activities', 20.00, 18.00, 60, 'Sports Wear', 'Clothes', ''),
('CLO008', '1234567890130', 'CLO008_QR', 'Women\'s Blouse', 'Elegant blouse for women, suitable for professional and casual settings', 28.00, NULL, 30, 'Clothing', 'Clothes', ''),
('CLO009', '1234567890131', 'CLO009_QR', 'Kids School Uniform', 'Complete school uniform set for children, includes shirt and pants', 50.00, 45.00, 20, 'Uniforms', 'Clothes', ''),
('CLO010', '1234567890132', 'CLO010_QR', 'Men\'s Casual Pants', 'Comfortable casual pants for men, perfect for everyday wear', 32.00, 28.00, 40, 'Apparel', 'Clothes', '');

-- Note: The image_path field is left empty as it would need actual image files
-- You can update these later with actual image paths when images are uploaded
