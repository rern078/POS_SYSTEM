-- Add customer role to users table
-- This script updates the existing users table to include the 'customer' role and full_name column

-- Update the role ENUM to include 'customer'
ALTER TABLE `users` MODIFY COLUMN `role` enum('admin','users','cashier','manager','customer') DEFAULT 'cashier';

-- Add full_name column if it doesn't exist
ALTER TABLE `users` ADD COLUMN `full_name` VARCHAR(100) AFTER `password`;

-- Optional: Create a sample customer account for testing
-- INSERT INTO users (username, email, password, full_name, role) VALUES 
-- ('customer1', 'customer1@example.com', '$2y$10$CS9vAQgLGXBEBMInau3wpO0xhvzLUB0TraGYJ08rGDfBbpq5vkDFe', 'John Customer', 'customer');

-- Note: The password hash above is for 'password123' - change as needed 