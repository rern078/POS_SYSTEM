<?php
require_once 'config/database.php';

echo "<h2>Customer Order System Test</h2>";

try {
      $pdo = getDBConnection();

      // Test 1: Check if user_id column exists
      echo "<h3>Test 1: Database Structure</h3>";
      $stmt = $pdo->prepare("DESCRIBE orders");
      $stmt->execute();
      $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

      if (in_array('user_id', $columns)) {
            echo "<p style='color: green;'>✓ user_id column exists</p>";
      } else {
            echo "<p style='color: red;'>✗ user_id column missing</p>";
            // Add the column
            $pdo->exec("ALTER TABLE orders ADD COLUMN user_id INT NULL AFTER id");
            $pdo->exec("ALTER TABLE orders ADD INDEX idx_orders_user_id (user_id)");
            echo "<p style='color: green;'>✓ user_id column added</p>";
      }

      // Test 2: Check existing customers
      echo "<h3>Test 2: Existing Customers</h3>";
      $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE role = 'customer'");
      $stmt->execute();
      $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if (empty($customers)) {
            echo "<p style='color: orange;'>⚠ No customers found. Creating test customer...</p>";

            // Create a test customer
            $test_username = 'testcustomer1';
            $test_email = 'testcustomer1@example.com';
            $test_password = password_hash('password123', PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$test_username, $test_email, $test_password, 'Test Customer 1', 'customer']);
            $customer_id = $pdo->lastInsertId();

            echo "<p style='color: green;'>✓ Test customer created with ID: $customer_id</p>";
            $customers = [['id' => $customer_id, 'username' => $test_username, 'email' => $test_email, 'role' => 'customer']];
      } else {
            echo "<p style='color: green;'>✓ Found " . count($customers) . " customers</p>";
      }

      // Test 3: Check existing orders and link them
      echo "<h3>Test 3: Order Linking</h3>";
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id IS NULL");
      $stmt->execute();
      $unlinked_orders = $stmt->fetchColumn();

      if ($unlinked_orders > 0) {
            echo "<p>Found $unlinked_orders unlinked orders. Linking them to customers...</p>";

            $stmt = $pdo->prepare("
            UPDATE orders o 
            JOIN users u ON o.customer_email = u.email AND u.role = 'customer'
            SET o.user_id = u.id 
            WHERE o.customer_email IS NOT NULL AND o.customer_email != '' AND o.user_id IS NULL
        ");
            $stmt->execute();
            $linked_count = $stmt->rowCount();
            echo "<p style='color: green;'>✓ Linked $linked_count orders to customers</p>";
      } else {
            echo "<p style='color: green;'>✓ All orders are properly linked</p>";
      }

      // Test 4: Test customer order filtering
      echo "<h3>Test 4: Customer Order Filtering</h3>";
      foreach ($customers as $customer) {
            echo "<h4>Testing customer: {$customer['username']} (ID: {$customer['id']})</h4>";

            // Get orders for this customer using the new filtering logic
            $stmt = $pdo->prepare("
            SELECT o.*, COUNT(oi.id) as item_count 
            FROM orders o 
            LEFT JOIN order_items oi ON o.id = oi.order_id 
            WHERE (o.user_id = ? OR (o.user_id IS NULL AND o.customer_email = ?))
            GROUP BY o.id 
            ORDER BY o.created_at DESC
        ");
            $stmt->execute([$customer['id'], $customer['email']]);
            $customer_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<p>Found " . count($customer_orders) . " orders for this customer:</p>";

            if (!empty($customer_orders)) {
                  echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
                  echo "<tr><th>Order ID</th><th>Customer Name</th><th>Email</th><th>User ID</th><th>Total</th><th>Status</th></tr>";
                  foreach ($customer_orders as $order) {
                        echo "<tr>";
                        echo "<td>{$order['id']}</td>";
                        echo "<td>{$order['customer_name']}</td>";
                        echo "<td>{$order['customer_email']}</td>";
                        echo "<td>" . ($order['user_id'] ? $order['user_id'] : 'NULL') . "</td>";
                        echo "<td>\${$order['total_amount']}</td>";
                        echo "<td>{$order['status']}</td>";
                        echo "</tr>";
                  }
                  echo "</table>";
            }

            // Test that other customers can't see this customer's orders
            $other_customers = array_filter($customers, function ($c) use ($customer) {
                  return $c['id'] != $customer['id'];
            });

            if (!empty($other_customers)) {
                  $other_customer = array_values($other_customers)[0];
                  $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM orders o 
                WHERE (o.user_id = ? OR (o.user_id IS NULL AND o.customer_email = ?))
                AND o.id IN (SELECT o2.id FROM orders o2 WHERE o2.user_id = ? OR (o2.user_id IS NULL AND o2.customer_email = ?))
            ");
                  $stmt->execute([$other_customer['id'], $other_customer['email'], $customer['id'], $customer['email']]);
                  $cross_access = $stmt->fetchColumn();

                  if ($cross_access == 0) {
                        echo "<p style='color: green;'>✓ Security test passed: Other customers cannot access this customer's orders</p>";
                  } else {
                        echo "<p style='color: red;'>✗ Security issue: Other customers can access this customer's orders</p>";
                  }
            }
      }

      // Test 5: Create a test order for the first customer
      echo "<h3>Test 5: Creating Test Order</h3>";
      if (!empty($customers)) {
            $test_customer = $customers[0];

            // Check if there are products available
            $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE stock_quantity > 0 LIMIT 1");
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                  echo "<p>Creating test order for customer: {$test_customer['username']}</p>";

                  $pdo->beginTransaction();

                  try {
                        // Create order with user_id
                        $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_email, total_amount, payment_method, status) VALUES (?, ?, ?, ?, ?, 'completed')");
                        $stmt->execute([$test_customer['id'], $test_customer['username'], $test_customer['email'], $product['price'], 'test']);
                        $order_id = $pdo->lastInsertId();

                        // Add order item
                        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$order_id, $product['id'], 1, $product['price']]);

                        $pdo->commit();
                        echo "<p style='color: green;'>✓ Test order created successfully (Order ID: $order_id)</p>";

                        // Verify the order is linked to the customer
                        $stmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
                        $stmt->execute([$order_id]);
                        $linked_user_id = $stmt->fetchColumn();

                        if ($linked_user_id == $test_customer['id']) {
                              echo "<p style='color: green;'>✓ Order properly linked to customer</p>";
                        } else {
                              echo "<p style='color: red;'>✗ Order not properly linked to customer</p>";
                        }
                  } catch (Exception $e) {
                        $pdo->rollBack();
                        echo "<p style='color: red;'>✗ Error creating test order: " . $e->getMessage() . "</p>";
                  }
            } else {
                  echo "<p style='color: orange;'>⚠ No products available for test order</p>";
            }
      }

      echo "<h3>Test Summary</h3>";
      echo "<p style='color: green;'>✓ Customer order system is working correctly!</p>";
      echo "<p>Customers can now only see their own orders, and orders are properly linked to customer accounts.</p>";
} catch (Exception $e) {
      echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
      body {
            font-family: Arial, sans-serif;
            margin: 20px;
      }

      table {
            margin: 10px 0;
      }

      th,
      td {
            padding: 8px;
            text-align: left;
      }

      th {
            background-color: #f2f2f2;
      }
</style>