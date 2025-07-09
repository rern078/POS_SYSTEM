<?php
require_once '../config/database.php';

try {
      $pdo = getDBConnection();

      // Check if status column already exists
      $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'status'");
      $stmt->execute();
      $columnExists = $stmt->fetch();

      if (!$columnExists) {
            // Add status column
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `status` ENUM('active', 'inactive') DEFAULT 'active' AFTER `role`");
            echo "Status column added successfully!\n";

            // Add index for better performance
            $pdo->exec("ALTER TABLE `users` ADD INDEX `idx_user_status` (`status`)");
            echo "Status index added successfully!\n";

            // Update existing users to be active by default
            $pdo->exec("UPDATE `users` SET `status` = 'active' WHERE `status` IS NULL");
            echo "Existing users updated to active status!\n";

            echo "\nDatabase migration completed successfully!\n";
      } else {
            echo "Status column already exists in the users table.\n";
      }
} catch (PDOException $e) {
      echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
      echo "Error: " . $e->getMessage() . "\n";
}
