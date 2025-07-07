-- Add barcode and QR code fields to products table
ALTER TABLE products 
ADD COLUMN barcode VARCHAR(100) DEFAULT NULL AFTER product_code,
ADD COLUMN qr_code VARCHAR(255) DEFAULT NULL AFTER barcode,
ADD UNIQUE KEY `barcode` (`barcode`),
ADD UNIQUE KEY `qr_code` (`qr_code`);

-- Update existing products with barcode and QR code values
UPDATE products SET 
barcode = CONCAT('BAR', LPAD(id, 6, '0')),
qr_code = CONCAT('QR', LPAD(id, 6, '0'))
WHERE barcode IS NULL OR qr_code IS NULL; 