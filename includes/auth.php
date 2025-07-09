<?php
require_once '../config/database.php';
require_once 'exchange_rate.php';

// Check if user is logged in
function isLoggedIn()
{
      return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin()
{
      return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user is manager
function isManager()
{
      return isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager');
}

// Get current user data
function getCurrentUser()
{
      if (!isLoggedIn()) {
            return null;
      }

      $pdo = getDBConnection();
      $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
      $stmt->execute([$_SESSION['user_id']]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Login function
function login($username, $password)
{
      $pdo = getDBConnection();
      $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
      $stmt->execute([$username]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($user && password_verify($password, $user['password'])) {
            // Check if user is active
            if ($user['status'] !== 'active') {
                  return false; // User is inactive
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            return true;
      }
      return false;
}

// Logout function
function logout()
{
      session_destroy();
      session_start();
}

// Get dashboard statistics
function getDashboardStats()
{
      $pdo = getDBConnection();
      $stats = [];

      // Get default currency
      $exchangeRate = new ExchangeRate();
      $defaultCurrency = $exchangeRate->getDefaultCurrency();
      $defaultCurrencyCode = $defaultCurrency['code'] ?? 'USD';

      // Today's sales - convert all amounts to default currency
      // Check if currency columns exist first
      $stmt = $pdo->prepare("SHOW COLUMNS FROM orders LIKE 'currency_code'");
      $stmt->execute();
      $currencyColumnExists = $stmt->fetch();

      if ($currencyColumnExists) {
            // Currency columns exist - use full functionality
            try {
                  $stmt = $pdo->prepare("
                        SELECT 
                              o.currency_code,
                              o.total_amount,
                              o.exchange_rate
                        FROM orders 
                        WHERE DATE(created_at) = CURDATE() AND status = 'completed'
                  ");
                  $stmt->execute();
                  $todayOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                  $todaySales = 0;
                  foreach ($todayOrders as $order) {
                        if ($order['currency_code'] === $defaultCurrencyCode) {
                              $todaySales += $order['total_amount'];
                        } else {
                              // Convert to default currency
                              $convertedAmount = $exchangeRate->convertCurrency(
                                    $order['total_amount'],
                                    $order['currency_code'],
                                    $defaultCurrencyCode
                              );
                              $todaySales += $convertedAmount;
                        }
                  }
            } catch (PDOException $e) {
                  // If there's an error, fall back to simple sum
                  $stmt = $pdo->prepare("
                        SELECT SUM(total_amount) as total_sales
                        FROM orders 
                        WHERE DATE(created_at) = CURDATE() AND status = 'completed'
                  ");
                  $stmt->execute();
                  $todaySales = $stmt->fetchColumn() ?: 0;
            }
      } else {
            // Currency columns don't exist - use simple sum
            $stmt = $pdo->prepare("
                  SELECT SUM(total_amount) as total_sales
                  FROM orders 
                  WHERE DATE(created_at) = CURDATE() AND status = 'completed'
            ");
            $stmt->execute();
            $todaySales = $stmt->fetchColumn() ?: 0;
      }
      $stats['today_sales'] = $todaySales;

      // Today's orders
      $stmt = $pdo->prepare("SELECT COUNT(*) as today_orders FROM orders WHERE DATE(created_at) = CURDATE()");
      $stmt->execute();
      $stats['today_orders'] = $stmt->fetchColumn();

      // Total products
      $stmt = $pdo->prepare("SELECT COUNT(*) as total_products FROM products");
      $stmt->execute();
      $stats['total_products'] = $stmt->fetchColumn();

      // Low stock items (less than 10)
      $stmt = $pdo->prepare("SELECT COUNT(*) as low_stock_items FROM products WHERE stock_quantity < 10");
      $stmt->execute();
      $stats['low_stock_items'] = $stmt->fetchColumn();

      // Recent orders with converted amounts
      if ($currencyColumnExists) {
            // Currency columns exist - use full functionality
            try {
                  $stmt = $pdo->prepare("
                        SELECT o.*, COUNT(oi.id) as product_count 
                        FROM orders o 
                        LEFT JOIN order_items oi ON o.id = oi.order_id 
                        GROUP BY o.id 
                        ORDER BY o.created_at DESC 
                        LIMIT 10
                  ");
                  $stmt->execute();
                  $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                  // Convert amounts in recent orders to default currency
                  foreach ($recentOrders as &$order) {
                        if ($order['currency_code'] !== $defaultCurrencyCode) {
                              $order['total_amount'] = $exchangeRate->convertCurrency(
                                    $order['total_amount'],
                                    $order['currency_code'],
                                    $defaultCurrencyCode
                              );
                        }
                  }
            } catch (PDOException $e) {
                  // If there's an error, fall back to simple query
                  $stmt = $pdo->prepare("
                        SELECT o.*, COUNT(oi.id) as product_count 
                        FROM orders o 
                        LEFT JOIN order_items oi ON o.id = oi.order_id 
                        GROUP BY o.id 
                        ORDER BY o.created_at DESC 
                        LIMIT 10
                  ");
                  $stmt->execute();
                  $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
      } else {
            // Currency columns don't exist - use simple query
            $stmt = $pdo->prepare("
                  SELECT o.*, COUNT(oi.id) as product_count 
                  FROM orders o 
                  LEFT JOIN order_items oi ON o.id = oi.order_id 
                  GROUP BY o.id 
                  ORDER BY o.created_at DESC 
                  LIMIT 10
            ");
            $stmt->execute();
            $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
      }
      $stats['recent_orders'] = $recentOrders;

      return $stats;
}

// Get sales data for charts
function getSalesData($period = 'month')
{
      $pdo = getDBConnection();

      // Check if currency columns exist first
      $stmt = $pdo->prepare("SHOW COLUMNS FROM orders LIKE 'currency_code'");
      $stmt->execute();
      $currencyColumnExists = $stmt->fetch();

      if ($currencyColumnExists) {
            // Currency columns exist - use full functionality
            try {
                  // Get default currency
                  $exchangeRate = new ExchangeRate();
                  $defaultCurrency = $exchangeRate->getDefaultCurrency();
                  $defaultCurrencyCode = $defaultCurrency['code'] ?? 'USD';

                  switch ($period) {
                        case 'week':
                              $sql = "SELECT DATE(created_at) as date, currency_code, SUM(total_amount) as sales 
                                FROM orders 
                                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                                AND status = 'completed'
                                GROUP BY DATE(created_at), currency_code 
                                ORDER BY date";
                              break;
                        case 'month':
                              $sql = "SELECT DATE(created_at) as date, currency_code, SUM(total_amount) as sales 
                                FROM orders 
                                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                                AND status = 'completed'
                                GROUP BY DATE(created_at), currency_code 
                                ORDER BY date";
                              break;
                        case 'year':
                              $sql = "SELECT MONTH(created_at) as month, currency_code, SUM(total_amount) as sales 
                                FROM orders 
                                WHERE YEAR(created_at) = YEAR(NOW()) 
                                AND status = 'completed'
                                GROUP BY MONTH(created_at), currency_code 
                                ORDER BY month";
                              break;
                        default:
                              $sql = "SELECT DATE(created_at) as date, currency_code, SUM(total_amount) as sales 
                                FROM orders 
                                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                                AND status = 'completed'
                                GROUP BY DATE(created_at), currency_code 
                                ORDER BY date";
                  }

                  $stmt = $pdo->prepare($sql);
                  $stmt->execute();
                  $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

                  // Convert and aggregate by date/month
                  $convertedData = [];
                  foreach ($rawData as $row) {
                        $dateKey = isset($row['month']) ? $row['month'] : $row['date'];

                        if (!isset($convertedData[$dateKey])) {
                              $convertedData[$dateKey] = 0;
                        }

                        if ($row['currency_code'] === $defaultCurrencyCode) {
                              $convertedData[$dateKey] += $row['sales'];
                        } else {
                              // Convert to default currency
                              $convertedAmount = $exchangeRate->convertCurrency(
                                    $row['sales'],
                                    $row['currency_code'],
                                    $defaultCurrencyCode
                              );
                              $convertedData[$dateKey] += $convertedAmount;
                        }
                  }

                  // Convert back to array format
                  $result = [];
                  foreach ($convertedData as $key => $value) {
                        $result[] = [
                              isset($rawData[0]['month']) ? 'month' : 'date' => $key,
                              'sales' => $value
                        ];
                  }

                  return $result;
            } catch (PDOException $e) {
                  // If there's an error, fall back to simple aggregation
                  // This will be handled by the else block below
            }
      } else {
            // Currency columns don't exist - use simple aggregation
            switch ($period) {
                  case 'week':
                        $sql = "SELECT DATE(created_at) as date, SUM(total_amount) as sales 
                          FROM orders 
                          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                          AND status = 'completed'
                          GROUP BY DATE(created_at) 
                          ORDER BY date";
                        break;
                  case 'month':
                        $sql = "SELECT DATE(created_at) as date, SUM(total_amount) as sales 
                          FROM orders 
                          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                          AND status = 'completed'
                          GROUP BY DATE(created_at) 
                          ORDER BY date";
                        break;
                  case 'year':
                        $sql = "SELECT MONTH(created_at) as month, SUM(total_amount) as sales 
                          FROM orders 
                          WHERE YEAR(created_at) = YEAR(NOW()) 
                          AND status = 'completed'
                          GROUP BY MONTH(created_at) 
                          ORDER BY month";
                        break;
                  default:
                        $sql = "SELECT DATE(created_at) as date, SUM(total_amount) as sales 
                          FROM orders 
                          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                          AND status = 'completed'
                          GROUP BY DATE(created_at) 
                          ORDER BY date";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $result;
      }
}

// Get top products
function getTopProducts($limit = 5)
{
      $pdo = getDBConnection();
      $limit = (int)$limit; // Ensure it's an integer

      // Check if currency columns exist first
      $stmt = $pdo->prepare("SHOW COLUMNS FROM order_items LIKE 'currency_code'");
      $stmt->execute();
      $currencyColumnExists = $stmt->fetch();

      if ($currencyColumnExists) {
            // Currency columns exist - use full functionality
            try {
                  // Get default currency
                  $exchangeRate = new ExchangeRate();
                  $defaultCurrency = $exchangeRate->getDefaultCurrency();
                  $defaultCurrencyCode = $defaultCurrency['code'] ?? 'USD';

                  $stmt = $pdo->prepare("
                    SELECT p.name, SUM(oi.quantity) as total_sold, 
                           oi.currency_code, SUM(oi.quantity * oi.price) as total_revenue
                    FROM products p
                    JOIN order_items oi ON p.id = oi.product_id
                    JOIN orders o ON oi.order_id = o.id
                    WHERE o.status = 'completed'
                    GROUP BY p.id, p.name, oi.currency_code
                    ORDER BY total_sold DESC
                    LIMIT $limit
                ");
                  $stmt->execute();
                  $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

                  // Convert and aggregate by product
                  $convertedData = [];
                  foreach ($rawData as $row) {
                        $productName = $row['name'];

                        if (!isset($convertedData[$productName])) {
                              $convertedData[$productName] = [
                                    'name' => $productName,
                                    'total_sold' => 0,
                                    'total_revenue' => 0
                              ];
                        }

                        $convertedData[$productName]['total_sold'] += $row['total_sold'];

                        if ($row['currency_code'] === $defaultCurrencyCode) {
                              $convertedData[$productName]['total_revenue'] += $row['total_revenue'];
                        } else {
                              // Convert to default currency
                              $convertedAmount = $exchangeRate->convertCurrency(
                                    $row['total_revenue'],
                                    $row['currency_code'],
                                    $defaultCurrencyCode
                              );
                              $convertedData[$productName]['total_revenue'] += $convertedAmount;
                        }
                  }

                  // Convert back to array format and sort by total_sold
                  $result = array_values($convertedData);
                  usort($result, function ($a, $b) {
                        return $b['total_sold'] - $a['total_sold'];
                  });

                  return array_slice($result, 0, $limit);
            } catch (PDOException $e) {
                  // If there's an error, fall back to simple aggregation
                  // This will be handled by the else block below
            }
      } else {
            // Currency columns don't exist - use simple aggregation
            $stmt = $pdo->prepare("
              SELECT p.name, SUM(oi.quantity) as total_sold, 
                     SUM(oi.quantity * oi.price) as total_revenue
              FROM products p
              JOIN order_items oi ON p.id = oi.product_id
              JOIN orders o ON oi.order_id = o.id
              WHERE o.status = 'completed'
              GROUP BY p.id, p.name
              ORDER BY total_sold DESC
              LIMIT $limit
          ");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $result;
      }
}
