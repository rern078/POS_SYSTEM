<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
      die('User not logged in');
}

$pdo = getDBConnection();

echo "<h2>Testing Receipt Change Amount Display</h2>";

// Test scenario: $10 total, $100 tendered, should show $90 change
$test_data = [
      'customer_name' => 'Test Customer',
      'customer_email' => 'test@example.com',
      'payment_method' => 'cash',
      'total_amount' => 10.00,
      'amount_tendered' => 100.00,
      'change_amount' => 90.00
];

try {
      $pdo->beginTransaction();

      // Create test order with change
      $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_email, total_amount, payment_method, amount_tendered, change_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')");
      $stmt->execute([$_SESSION['user_id'], $test_data['customer_name'], $test_data['customer_email'], $test_data['total_amount'], $test_data['payment_method'], $test_data['amount_tendered'], $test_data['change_amount']]);
      $order_id = $pdo->lastInsertId();

      // Add test order items
      $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
      $stmt->execute([$order_id, 1, 1, 10.00]); // $10 item

      $pdo->commit();

      echo "<p style='color: green;'>✓ Test order created successfully with ID: $order_id</p>";
      echo "<h3>Test Data:</h3>";
      echo "<ul>";
      echo "<li>Total Amount: $" . number_format($test_data['total_amount'], 2) . "</li>";
      echo "<li>Amount Tendered: $" . number_format($test_data['amount_tendered'], 2) . "</li>";
      echo "<li>Change Amount: $" . number_format($test_data['change_amount'], 2) . "</li>";
      echo "<li>Payment Method: " . ucfirst($test_data['payment_method']) . "</li>";
      echo "</ul>";

      // Test retrieving the order
      $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
      $stmt->execute([$order_id]);
      $order = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($order) {
            echo "<p style='color: green;'>✓ Order retrieved successfully</p>";

            // Verify the data is stored correctly
            echo "<h3>Stored Order Data:</h3>";
            echo "<ul>";
            echo "<li>Total Amount: $" . number_format($order['total_amount'], 2) . "</li>";
            echo "<li>Amount Tendered: $" . number_format($order['amount_tendered'], 2) . "</li>";
            echo "<li>Change Amount: $" . number_format($order['change_amount'], 2) . "</li>";
            echo "<li>Payment Method: " . ucfirst($order['payment_method']) . "</li>";
            echo "</ul>";

            // Test receipt URL
            echo "<h3>Receipt Test:</h3>";
            $receipt_url = "user/receipt.php?order_id=$order_id";
            echo "<p>Receipt URL: <a href='$receipt_url' target='_blank'>View Receipt</a></p>";

            // Simulate the receipt logic to check if change will be displayed
            echo "<h3>Receipt Display Logic Test:</h3>";
            $will_show_change = ($order['payment_method'] === 'cash' && $order['amount_tendered'] > 0);
            $will_show_change_amount = ($order['change_amount'] > 0);

            echo "<p>Will show cash fields: " . ($will_show_change ? 'Yes' : 'No') . "</p>";
            echo "<p>Will show change amount: " . ($will_show_change_amount ? 'Yes' : 'No') . "</p>";

            if ($will_show_change && $will_show_change_amount) {
                  echo "<p style='color: green;'>✓ Receipt should display change amount correctly</p>";
            } else {
                  echo "<p style='color: red;'>✗ Receipt may not display change amount</p>";
            }
      } else {
            echo "<p style='color: red;'>✗ Failed to retrieve order</p>";
      }
} catch (Exception $e) {
      $pdo->rollBack();
      echo "<p style='color: red;'>✗ Error creating test order: " . $e->getMessage() . "</p>";
}

// Show recent orders with change amounts
echo "<h3>Recent Orders with Change Amounts:</h3>";
$stmt = $pdo->prepare("SELECT id, customer_name, total_amount, payment_method, amount_tendered, change_amount, created_at FROM orders WHERE payment_method = 'cash' AND change_amount > 0 ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($recent_orders) {
      echo "<table border='1' style='border-collapse: collapse;'>";
      echo "<tr><th>ID</th><th>Customer</th><th>Total</th><th>Tendered</th><th>Change</th><th>Created</th><th>Receipt</th></tr>";
      foreach ($recent_orders as $order) {
            echo "<tr>";
            echo "<td>" . $order['id'] . "</td>";
            echo "<td>" . htmlspecialchars($order['customer_name']) . "</td>";
            echo "<td>$" . number_format($order['total_amount'], 2) . "</td>";
            echo "<td>$" . number_format($order['amount_tendered'], 2) . "</td>";
            echo "<td>$" . number_format($order['change_amount'], 2) . "</td>";
            echo "<td>" . $order['created_at'] . "</td>";
            echo "<td><a href='user/receipt.php?order_id=" . $order['id'] . "' target='_blank'>View</a></td>";
            echo "</tr>";
      }
      echo "</table>";
} else {
      echo "<p>No orders with change amounts found.</p>";
}

// Test different scenarios
echo "<h3>Testing Different Change Scenarios:</h3>";

$scenarios = [
      ['total' => 10.00, 'tendered' => 100.00, 'change' => 90.00],
      ['total' => 25.50, 'tendered' => 50.00, 'change' => 24.50],
      ['total' => 99.99, 'tendered' => 100.00, 'change' => 0.01],
      ['total' => 15.00, 'tendered' => 15.00, 'change' => 0.00]
];

foreach ($scenarios as $index => $scenario) {
      echo "<h4>Scenario " . ($index + 1) . ":</h4>";
      echo "<p>Total: $" . number_format($scenario['total'], 2) . "</p>";
      echo "<p>Amount Tendered: $" . number_format($scenario['tendered'], 2) . "</p>";
      echo "<p>Change: $" . number_format($scenario['change'], 2) . "</p>";

      // Check if change should be displayed
      $should_show_change = ($scenario['change'] > 0);
      echo "<p>Should show change: " . ($should_show_change ? 'Yes' : 'No') . "</p>";
      echo "<hr>";
}
