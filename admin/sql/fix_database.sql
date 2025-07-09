-- Fix database structure for customer role implementation
-- Run this script to add missing columns and update the users table

-- Add full_name column if it doesn't exist
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `full_name` VARCHAR(100) AFTER `password`;

-- Update the role ENUM to include 'customer' if not already present
ALTER TABLE `users` MODIFY COLUMN `role` enum('admin','users','cashier','manager','customer') DEFAULT 'cashier';

-- Update existing users to have a default full_name if it's NULL
UPDATE `users` SET `full_name` = `username` WHERE `full_name` IS NULL OR `full_name` = '';

-- Verify the changes
DESCRIBE `users`; 