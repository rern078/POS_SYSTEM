-- Add user_id column to orders table for proper customer order linking
-- This script will add the user_id column and update existing orders

-- Add user_id column to orders table
ALTER TABLE `orders` ADD COLUMN `user_id` INT NULL AFTER `id`;

-- Add foreign key constraint (optional - uncomment if you want referential integrity)
-- ALTER TABLE `orders` ADD CONSTRAINT `fk_orders_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Update existing orders to link them to customers based on email matching
UPDATE `orders` o 
JOIN `users` u ON o.customer_email = u.email AND u.role = 'customer'
SET o.user_id = u.id 
WHERE o.customer_email IS NOT NULL AND o.customer_email != '';

-- Add index for better performance
ALTER TABLE `orders` ADD INDEX `idx_orders_user_id` (`user_id`);
ALTER TABLE `orders` ADD INDEX `idx_orders_customer_email` (`customer_email`);

-- Verify the changes
SELECT 
    o.id as order_id,
    o.customer_name,
    o.customer_email,
    o.user_id,
    u.username,
    u.role
FROM orders o 
LEFT JOIN users u ON o.user_id = u.id 
ORDER BY o.id DESC 
LIMIT 10; 