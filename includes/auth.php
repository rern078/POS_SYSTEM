<?php
require_once '../config/database.php';

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

      // Today's sales
      $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as today_sales FROM orders WHERE DATE(created_at) = CURDATE() AND status = 'completed'");
      $stmt->execute();
      $stats['today_sales'] = $stmt->fetchColumn();

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

      // Recent orders
      $stmt = $pdo->prepare("
        SELECT o.*, COUNT(oi.id) as product_count 
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        GROUP BY o.id 
        ORDER BY o.created_at DESC 
        LIMIT 10
    ");
      $stmt->execute();
      $stats['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

      return $stats;
}

// Get sales data for charts
function getSalesData($period = 'month')
{
      $pdo = getDBConnection();

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
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get top products
function getTopProducts($limit = 5)
{
      $pdo = getDBConnection();
      $stmt = $pdo->prepare("
        SELECT p.name, SUM(oi.quantity) as total_sold, SUM(oi.quantity * oi.price) as total_revenue
        FROM products p
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status = 'completed'
        GROUP BY p.id, p.name
        ORDER BY total_sold DESC
        LIMIT ?
    ");
      $stmt->execute([$limit]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
