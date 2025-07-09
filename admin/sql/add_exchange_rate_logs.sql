-- Add exchange rate logs table for monitoring and tracking
-- This table tracks exchange rate API updates and system status

USE pos_db;

-- Create exchange_rate_logs table
CREATE TABLE IF NOT EXISTS exchange_rate_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL COMMENT 'API provider name (exchangerate_api, fixer_io, currency_api)',
    base_currency VARCHAR(3) NOT NULL COMMENT 'Base currency for the update',
    rates_updated INT DEFAULT 0 COMMENT 'Number of rates updated in this batch',
    status ENUM('success', 'error', 'warning') DEFAULT 'success' COMMENT 'Update status',
    error_message TEXT NULL COMMENT 'Error message if status is error',
    response_time DECIMAL(10,3) NULL COMMENT 'API response time in seconds',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_provider (provider),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Create exchange_rate_alerts table for monitoring
CREATE TABLE IF NOT EXISTS exchange_rate_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type ENUM('rate_change', 'api_failure', 'stale_rates', 'manual_update') NOT NULL,
    currency_pair VARCHAR(7) NOT NULL COMMENT 'Format: USD-EUR',
    threshold DECIMAL(10,6) NULL COMMENT 'Threshold value for rate change alerts',
    current_value DECIMAL(10,6) NULL COMMENT 'Current rate value',
    previous_value DECIMAL(10,6) NULL COMMENT 'Previous rate value',
    change_percentage DECIMAL(5,2) NULL COMMENT 'Percentage change',
    message TEXT NOT NULL COMMENT 'Alert message',
    is_read BOOLEAN DEFAULT FALSE COMMENT 'Whether alert has been read',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alert_type (alert_type),
    INDEX idx_currency_pair (currency_pair),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- Create exchange_rate_settings table for configuration
CREATE TABLE IF NOT EXISTS exchange_rate_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- Insert default settings
INSERT IGNORE INTO exchange_rate_settings (setting_key, setting_value, description) VALUES
('update_frequency_hours', '6', 'How often to update exchange rates (in hours)'),
('api_provider_priority', 'exchangerate_api,fixer_io,currency_api', 'Priority order of API providers'),
('alert_rate_change_threshold', '5.0', 'Alert when rate changes by this percentage'),
('max_retry_attempts', '3', 'Maximum retry attempts for API calls'),
('log_retention_days', '30', 'How long to keep exchange rate logs'),
('auto_update_enabled', '1', 'Whether automatic updates are enabled');

-- Add some sample alerts for testing
INSERT IGNORE INTO exchange_rate_alerts (alert_type, currency_pair, message) VALUES
('api_failure', 'USD-EUR', 'Failed to fetch rates from ExchangeRate-API'),
('manual_update', 'USD-EUR', 'Exchange rates updated manually by admin'),
('stale_rates', 'USD-EUR', 'Exchange rates are older than 24 hours');

-- Verify the tables
DESCRIBE exchange_rate_logs;
DESCRIBE exchange_rate_alerts;
DESCRIBE exchange_rate_settings; 