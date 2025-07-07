<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || !isAdmin()) {
      header('Location: login.php');
      exit();
}

$pdo = getDBConnection();

// Get parameters
$type = $_GET['type'] ?? 'sales';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Set headers based on export type
switch ($type) {
      case 'sales':
            $filename = "sales_report_" . date('Y-m-d') . ".csv";
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            break;
      case 'inventory':
            $filename = "inventory_report_" . date('Y-m-d') . ".csv";
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            break;
      case 'products':
            $filename = "products_report_" . date('Y-m-d') . ".csv";
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            break;
      default:
            header('Location: reports.php');
            exit();
}

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

switch ($type) {
      case 'sales':
            // Sales Report
            fputcsv($output, ['Sales Report', 'Period: ' . $start_date . ' to ' . $end_date]);
            fputcsv($output, ['']);

            // Sales Summary
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders, SUM(total_amount) as total_sales, AVG(total_amount) as avg_order_value FROM orders WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ?");
            $stmt->execute([$start_date, $end_date]);
            $sales_summary = $stmt->fetch(PDO::FETCH_ASSOC);

            fputcsv($output, ['Sales Summary']);
            fputcsv($output, ['Total Orders', $sales_summary['total_orders']]);
            fputcsv($output, ['Total Sales', '$' . number_format($sales_summary['total_sales'], 2)]);
            fputcsv($output, ['Average Order Value', '$' . number_format($sales_summary['avg_order_value'], 2)]);
            fputcsv($output, ['']);

            // Daily Sales
            fputcsv($output, ['Daily Sales Breakdown']);
            fputcsv($output, ['Date', 'Orders', 'Sales Amount']);

            $stmt = $pdo->prepare("SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as sales FROM orders WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY date");
            $stmt->execute([$start_date, $end_date]);
            $daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($daily_sales as $sale) {
                  fputcsv($output, [$sale['date'], $sale['orders'], '$' . number_format($sale['sales'], 2)]);
            }
            fputcsv($output, ['']);

            // Top Products
            fputcsv($output, ['Top Selling Products']);
            fputcsv($output, ['Product Name', 'Quantity Sold', 'Revenue']);

            $stmt = $pdo->prepare("SELECT p.name, SUM(oi.quantity) as total_sold, SUM(oi.quantity * oi.price) as total_revenue FROM products p JOIN order_items oi ON p.id = oi.product_id JOIN orders o ON oi.order_id = o.id WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY p.id, p.name ORDER BY total_sold DESC LIMIT 10");
            $stmt->execute([$start_date, $end_date]);
            $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($top_products as $product) {
                  fputcsv($output, [$product['name'], $product['total_sold'], '$' . number_format($product['total_revenue'], 2)]);
            }
            break;

      case 'inventory':
            // Inventory Report
            fputcsv($output, ['Inventory Report', 'Generated: ' . date('Y-m-d H:i:s')]);
            fputcsv($output, ['']);

            // Inventory Summary
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_products, SUM(stock_quantity) as total_stock, COUNT(CASE WHEN stock_quantity < 10 THEN 1 END) as low_stock, COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock FROM products");
            $stmt->execute();
            $inventory_summary = $stmt->fetch(PDO::FETCH_ASSOC);

            fputcsv($output, ['Inventory Summary']);
            fputcsv($output, ['Total Products', $inventory_summary['total_products']]);
            fputcsv($output, ['Total Stock', $inventory_summary['total_stock']]);
            fputcsv($output, ['Low Stock Items (< 10)', $inventory_summary['low_stock']]);
            fputcsv($output, ['Out of Stock Items', $inventory_summary['out_of_stock']]);
            fputcsv($output, ['']);

            // Product Details
            fputcsv($output, ['Product Details']);
            fputcsv($output, ['ID', 'Name', 'Category', 'Stock Quantity', 'Unit Price', 'Status']);

            $stmt = $pdo->prepare("SELECT id, name, category, stock_quantity, unit_price FROM products ORDER BY name");
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($products as $product) {
                  $status = $product['stock_quantity'] == 0 ? 'Out of Stock' : ($product['stock_quantity'] < 10 ? 'Low Stock' : 'In Stock');
                  fputcsv($output, [$product['id'], $product['name'], $product['category'], $product['stock_quantity'], '$' . number_format($product['unit_price'], 2), $status]);
            }
            break;

      case 'products':
            // Products Report
            fputcsv($output, ['Products Performance Report', 'Period: ' . $start_date . ' to ' . $end_date]);
            fputcsv($output, ['']);

            // Category Analysis
            fputcsv($output, ['Sales by Category']);
            fputcsv($output, ['Category', 'Sales Amount', 'Quantity Sold', 'Average Price']);

            $stmt = $pdo->prepare("SELECT p.category, SUM(oi.quantity * oi.price) as category_sales, SUM(oi.quantity) as category_quantity, AVG(oi.price) as avg_price FROM products p JOIN order_items oi ON p.id = oi.product_id JOIN orders o ON oi.order_id = o.id WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY p.category ORDER BY category_sales DESC");
            $stmt->execute([$start_date, $end_date]);
            $category_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($category_sales as $category) {
                  fputcsv($output, [$category['category'], '$' . number_format($category['category_sales'], 2), $category['category_quantity'], '$' . number_format($category['avg_price'], 2)]);
            }
            fputcsv($output, ['']);

                        // Product Performance
            fputcsv($output, ['Product Performance']);
            fputcsv($output, ['Product Name', 'Category', 'Quantity Sold', 'Revenue', 'Stock Level', 'Performance Rating']);
            
            $stmt = $pdo->prepare("SELECT p.name, p.category, p.stock_quantity, SUM(oi.quantity) as sold_quantity, SUM(oi.quantity * oi.price) as revenue FROM products p LEFT JOIN order_items oi ON p.id = oi.product_id LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY p.id, p.name, p.category, p.stock_quantity ORDER BY revenue DESC");
            $stmt->execute([$start_date, $end_date]);
            $product_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($product_performance as $product) {
                  $sold_qty = $product['sold_quantity'] ?? 0;
                  $revenue = $product['revenue'] ?? 0;

                  // Performance rating based on sales
                  if ($revenue > 1000) {
                        $rating = 'Excellent';
                  } elseif ($revenue > 500) {
                        $rating = 'Good';
                  } elseif ($revenue > 100) {
                        $rating = 'Average';
                  } else {
                        $rating = 'Poor';
                  }

                  fputcsv($output, [$product['name'], $product['category'], $sold_qty, '$' . number_format($revenue, 2), $product['stock_quantity'], $rating]);
            }
            break;
}

fclose($output);
exit();
