-- Add phone column to users table for customer contact information
-- This script will add the phone column and update existing users

-- Add phone column to users table
ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(20) NULL AFTER `full_name`;

-- Add index for phone number searches (optional)
ALTER TABLE `users` ADD INDEX `idx_users_phone` (`phone`);

-- Verify the changes
DESCRIBE `users`;

-- Show sample data with phone column
SELECT id, username, email, full_name, phone, role FROM users LIMIT 5; 