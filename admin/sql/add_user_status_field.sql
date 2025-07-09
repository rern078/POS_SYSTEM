-- Add status field to users table
ALTER TABLE `users` ADD COLUMN `status` ENUM('active', 'inactive') DEFAULT 'active' AFTER `role`;

-- Add index for better performance
ALTER TABLE `users` ADD INDEX `idx_user_status` (`status`);

-- Update existing users to be active by default
UPDATE `users` SET `status` = 'active' WHERE `status` IS NULL; 