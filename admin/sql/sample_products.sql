-- Sample products with new fields (product_code and discount_price)
-- 20 diverse product items for POS system
INSERT INTO products (product_code, name, description, price, discount_price, stock_quantity, category, image_path) VALUES
-- Beverages
('PROD001', 'Coca Cola', 'Refreshing carbonated soft drink - 330ml can', 2.50, 2.00, 100, 'Beverages', 'images/coke.jpg'),
('PROD002', 'Pepsi', 'Classic cola drink - 330ml can', 2.50, NULL, 85, 'Beverages', 'images/pepsi.jpg'),
('PROD003', 'Sprite', 'Lemon-lime flavored soft drink - 330ml can', 2.00, 1.75, 60, 'Beverages', 'images/sprite.jpg'),
('PROD004', 'Red Bull', 'Energy drink - 250ml can', 3.50, 3.00, 45, 'Beverages', 'images/redbull.jpg'),
('PROD005', 'Mineral Water', 'Pure drinking water - 500ml bottle', 1.00, NULL, 200, 'Beverages', 'images/water.jpg'),

-- Snacks & Chips
('PROD006', 'Lays Classic', 'Original potato chips - 150g bag', 3.00, 2.50, 75, 'Snacks', 'images/lays.jpg'),
('PROD007', 'Doritos Nacho', 'Cheese flavored tortilla chips - 180g bag', 4.50, 3.99, 50, 'Snacks', 'images/doritos.jpg'),
('PROD008', 'Pringles Original', 'Stackable potato chips - 110g can', 5.00, 4.50, 40, 'Snacks', 'images/pringles.jpg'),

-- Chocolate & Candy
('PROD009', 'Snickers', 'Chocolate bar with caramel and peanuts - 50g', 2.00, 1.75, 90, 'Snacks', 'images/snickers.jpg'),
('PROD010', 'KitKat', 'Chocolate wafer bar - 4 finger pack', 2.25, 2.00, 65, 'Snacks', 'images/kitkat.jpg'),
('PROD011', 'Oreo Cookies', 'Chocolate sandwich cookies - 137g pack', 3.50, 3.00, 55, 'Snacks', 'images/oreo.jpg'),
('PROD012', 'Twix', 'Chocolate bar with caramel and cookie - 50g', 2.50, 2.25, 70, 'Snacks', 'images/twix.jpg'),
('PROD013', 'M&M\'s Peanut', 'Chocolate candies with peanuts - 100g bag', 3.00, 2.75, 45, 'Snacks', 'images/mms.jpg'),

-- Groceries & Essentials
('PROD014', 'White Bread', 'Fresh white bread loaf - 500g', 2.50, NULL, 30, 'Groceries', 'images/bread.jpg'),
('PROD015', 'Milk', 'Fresh whole milk - 1 liter', 3.00, 2.75, 25, 'Groceries', 'images/milk.jpg'),
('PROD016', 'Eggs', 'Fresh farm eggs - 12 pieces', 4.50, 4.00, 40, 'Groceries', 'images/eggs.jpg'),
('PROD017', 'Butter', 'Salted butter - 250g block', 3.75, 3.50, 35, 'Groceries', 'images/butter.jpg'),

-- Household & Personal Care
('PROD018', 'Toothpaste', 'Fresh mint toothpaste - 100ml tube', 4.00, 3.50, 60, 'Personal Care', 'images/toothpaste.jpg'),
('PROD019', 'Shampoo', 'Moisturizing shampoo - 400ml bottle', 8.50, 7.99, 30, 'Personal Care', 'images/shampoo.jpg'),
('PROD020', 'Soap Bar', 'Antibacterial soap bar - 100g', 2.00, 1.75, 80, 'Personal Care', 'images/soap.jpg')

ON DUPLICATE KEY UPDATE
name = VALUES(name),
description = VALUES(description),
price = VALUES(price),
discount_price = VALUES(discount_price),
stock_quantity = VALUES(stock_quantity),
category = VALUES(category),
image_path = VALUES(image_path); 