-- Update inventory_adjustments table to include stock_in and stock_out types
-- This script should be run if the inventory_adjustments table already exists

-- First, check if the table exists
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                     WHERE table_schema = DATABASE() 
                     AND table_name = 'inventory_adjustments');

-- If table exists, update the enum
SET @sql = IF(@table_exists > 0,
    'ALTER TABLE inventory_adjustments 
     MODIFY COLUMN adjustment_type enum(\'manual\',\'restock\',\'damage\',\'correction\',\'sale\',\'return\',\'stock_in\',\'stock_out\') NOT NULL DEFAULT \'manual\'',
    'SELECT \'Table inventory_adjustments does not exist. Run add_inventory_adjustments_table.sql first.\' as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt; 