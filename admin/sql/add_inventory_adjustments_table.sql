-- Create inventory_adjustments table for tracking stock changes
CREATE TABLE IF NOT EXISTS `inventory_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `adjustment_type` enum('manual','restock','damage','correction','sale','return') NOT NULL DEFAULT 'manual',
  `old_quantity` int(11) NOT NULL DEFAULT 0,
  `new_quantity` int(11) NOT NULL DEFAULT 0,
  `quantity_change` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `adjusted_by` int(11) NOT NULL,
  `adjusted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `adjusted_by` (`adjusted_by`),
  KEY `adjusted_at` (`adjusted_at`),
  CONSTRAINT `inventory_adjustments_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_adjustments_ibfk_2` FOREIGN KEY (`adjusted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add trigger to automatically calculate quantity_change
DELIMITER $$
CREATE TRIGGER `calculate_quantity_change` 
BEFORE INSERT ON `inventory_adjustments` 
FOR EACH ROW 
BEGIN
    SET NEW.quantity_change = NEW.new_quantity - NEW.old_quantity;
END$$
DELIMITER ; 