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

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
      $where_conditions[] = "(o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.id LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      $params[] = "%$search%";
}

if (!empty($status)) {
      $where_conditions[] = "o.status = ?";
      $params[] = $status;
}

if (!empty($payment_method)) {
      $where_conditions[] = "o.payment_method = ?";
      $params[] = $payment_method;
}

if (!empty($date_from)) {
      $where_conditions[] = "DATE(o.created_at) >= ?";
      $params[] = $date_from;
}

if (!empty($date_to)) {
      $where_conditions[] = "DATE(o.created_at) <= ?";
      $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get receipts with items
$sql = "SELECT o.*, 
        GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, 'x $', oi.price, ')') SEPARATOR '; ') as items
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        LEFT JOIN products p ON oi.product_id = p.id
        $where_clause 
        GROUP BY o.id 
        ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
$filename = 'receipts_export_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Headers
$headers = [
      'Receipt ID',
      'Customer Name',
      'Customer Email',
      'Items',
      'Subtotal',
      'Tax Amount',
      'Discount Amount',
      'Total Amount',
      'Payment Method',
      'Status',
      'Date Created',
      'Time Created'
];

fputcsv($output, $headers);

// Add data rows
foreach ($receipts as $receipt) {
      $row = [
            $receipt['id'],
            $receipt['customer_name'] ?: 'Walk-in Customer',
            $receipt['customer_email'] ?: 'N/A',
            $receipt['items'] ?: 'No items',
            number_format($receipt['subtotal'], 2),
            number_format($receipt['tax_amount'], 2),
            number_format($receipt['discount_amount'], 2),
            number_format($receipt['total_amount'], 2),
            ucfirst($receipt['payment_method']),
            ucfirst($receipt['status']),
            date('Y-m-d', strtotime($receipt['created_at'])),
            date('H:i:s', strtotime($receipt['created_at']))
      ];
      
      fputcsv($output, $row);
}

fclose($output);
exit;
?> 