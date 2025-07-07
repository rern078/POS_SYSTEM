<?php
// Simple script to create the inventory_adjustments table
require_once '../config/database.php';

try {
      $pdo = getDBConnection();

      // Read the SQL file
      $sql = file_get_contents('sql/add_inventory_adjustments_table.sql');

      // Execute the SQL
      $pdo->exec($sql);

      echo "✅ Inventory adjustments table created successfully!\n";
      echo "You can now use the inventory management features.\n";
} catch (PDOException $e) {
      echo "❌ Error creating table: " . $e->getMessage() . "\n";
}
