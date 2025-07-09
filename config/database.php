<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'pos_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create database connection
function getDBConnection()
{
      try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
      } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
      }
}

// Initialize database tables if they don't exist
function initializeDatabase()
{
      $pdo = getDBConnection();

      // Create users table
      $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100),
        phone VARCHAR(20),
        role ENUM('admin', 'cashier', 'manager', 'customer') DEFAULT 'cashier',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

      // Create products table
      $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_code VARCHAR(50) UNIQUE,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        discount_price DECIMAL(10,2) DEFAULT NULL,
        stock_quantity INT DEFAULT 0,
        category VARCHAR(50),
        image_path VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

      // Create orders table
      $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        customer_name VARCHAR(100),
        customer_email VARCHAR(100),
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
        payment_method VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_orders_user_id (user_id),
        INDEX idx_orders_customer_email (customer_email)
    )");

      // Create order_items table
      $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT,
        product_id INT,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");

      // Create exchange_rates table
      $pdo->exec("CREATE TABLE IF NOT EXISTS exchange_rates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        base_currency VARCHAR(3) NOT NULL DEFAULT 'USD',
        target_currency VARCHAR(3) NOT NULL,
        rate DECIMAL(10,6) NOT NULL,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        UNIQUE KEY unique_currency_pair (base_currency, target_currency)
    )");

      // Create currencies table
      $pdo->exec("CREATE TABLE IF NOT EXISTS currencies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(3) NOT NULL UNIQUE,
        name VARCHAR(50) NOT NULL,
        symbol VARCHAR(5) NOT NULL,
        is_default BOOLEAN DEFAULT FALSE,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

      // Add currency fields to orders table if they don't exist
      try {
            $pdo->exec("ALTER TABLE orders ADD COLUMN currency_code VARCHAR(3) DEFAULT 'USD' AFTER total_amount");
      } catch (PDOException $e) {
            // Column might already exist
      }

      try {
            $pdo->exec("ALTER TABLE orders ADD COLUMN exchange_rate DECIMAL(10,6) DEFAULT 1.000000 AFTER currency_code");
      } catch (PDOException $e) {
            // Column might already exist
      }

      // Insert default currencies
      $defaultCurrencies = [
            ['USD', 'US Dollar', '$', 1],
            ['EUR', 'Euro', '€', 0],
            ['GBP', 'British Pound', '£', 0],
            ['JPY', 'Japanese Yen', '¥', 0],
            ['CAD', 'Canadian Dollar', 'C$', 0],
            ['AUD', 'Australian Dollar', 'A$', 0],
            ['CHF', 'Swiss Franc', 'CHF', 0],
            ['CNY', 'Chinese Yuan', '¥', 0],
            ['INR', 'Indian Rupee', '₹', 0],
            ['KRW', 'South Korean Won', '₩', 0],
            ['SGD', 'Singapore Dollar', 'S$', 0],
            ['HKD', 'Hong Kong Dollar', 'HK$', 0],
            ['THB', 'Thai Baht', '฿', 0],
            ['PHP', 'Philippine Peso', '₱', 0],
            ['MYR', 'Malaysian Ringgit', 'RM', 0],
            ['IDR', 'Indonesian Rupiah', 'Rp', 0],
            ['VND', 'Vietnamese Dong', '₫', 0],
            ['KHR', 'Cambodian Riel', '៛', 0]
      ];

      $stmt = $pdo->prepare("INSERT IGNORE INTO currencies (code, name, symbol, is_default) VALUES (?, ?, ?, ?)");
      foreach ($defaultCurrencies as $currency) {
            $stmt->execute($currency);
      }

      // Insert default exchange rates
      $defaultRates = [
            ['USD', 'USD', 1.000000],
            ['USD', 'EUR', 0.850000],
            ['USD', 'GBP', 0.730000],
            ['USD', 'JPY', 110.000000],
            ['USD', 'CAD', 1.250000],
            ['USD', 'AUD', 1.350000],
            ['USD', 'CHF', 0.920000],
            ['USD', 'CNY', 6.450000],
            ['USD', 'INR', 74.500000],
            ['USD', 'KRW', 1150.000000],
            ['USD', 'SGD', 1.350000],
            ['USD', 'HKD', 7.780000],
            ['USD', 'THB', 33.500000],
            ['USD', 'PHP', 50.800000],
            ['USD', 'MYR', 4.150000],
            ['USD', 'IDR', 14250.000000],
            ['USD', 'VND', 23000.000000],
            ['USD', 'KHR', 4100.000000]
      ];

      $stmt = $pdo->prepare("INSERT IGNORE INTO exchange_rates (base_currency, target_currency, rate) VALUES (?, ?, ?)");
      foreach ($defaultRates as $rate) {
            $stmt->execute($rate);
      }

      // Insert default admin user if not exists
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
      $stmt->execute();
      if ($stmt->fetchColumn() == 0) {
            $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute(['admin', 'admin@pos.com', $hashedPassword, 'admin']);
      }
}

// Initialize database on first run
initializeDatabase();
