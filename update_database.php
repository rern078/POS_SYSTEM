<?php
require_once 'config/database.php';

echo "<h2>Database Update for Customer Order Security</h2>";

try {
      $pdo = getDBConnection();

      // Step 1: Check current orders table structure
      echo "<h3>Step 1: Checking current database structure...</h3>";
      $stmt = $pdo->prepare("DESCRIBE orders");
      $stmt->execute();
      $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

      if (in_array('user_id', $columns)) {
            echo "<p style='color: green;'>✓ user_id column already exists</p>";
      } else {
            echo "<p style='color: orange;'>⚠ user_id column missing. Adding it now...</p>";

            // Add user_id column
            $pdo->exec("ALTER TABLE orders ADD COLUMN user_id INT NULL AFTER id");
            echo "<p style='color: green;'>✓ user_id column added successfully</p>";

            // Add indexes for better performance
            $pdo->exec("ALTER TABLE orders ADD INDEX idx_orders_user_id (user_id)");
            $pdo->exec("ALTER TABLE orders ADD INDEX idx_orders_customer_email (customer_email)");
            echo "<p style='color: green;'>✓ Indexes added successfully</p>";
      }

      // Step 2: Check existing customers
      echo "<h3>Step 2: Checking existing customers...</h3>";
      $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE role = 'customer'");
      $stmt->execute();
      $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if (empty($customers)) {
            echo "<p style='color: orange;'>⚠ No customers found. Please register a customer first.</p>";
            echo "<p><a href='customer_register.php' class='btn btn-primary'>Register Customer</a></p>";
      } else {
            echo "<p style='color: green;'>✓ Found " . count($customers) . " customers</p>";

            // Display customers
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
            foreach ($customers as $customer) {
                  echo "<tr>";
                  echo "<td>{$customer['id']}</td>";
                  echo "<td>{$customer['username']}</td>";
                  echo "<td>{$customer['email']}</td>";
                  echo "<td>{$customer['role']}</td>";
                  echo "</tr>";
            }
            echo "</table>";
      }

      // Step 3: Check existing orders
      echo "<h3>Step 3: Checking existing orders...</h3>";
      $stmt = $pdo->prepare("SELECT id, customer_name, customer_email, user_id, total_amount FROM orders ORDER BY id DESC LIMIT 10");
      $stmt->execute();
      $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if (empty($orders)) {
            echo "<p style='color: orange;'>⚠ No orders found.</p>";
      } else {
            echo "<p style='color: green;'>✓ Found " . count($orders) . " recent orders</p>";

            // Display orders
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>Order ID</th><th>Customer Name</th><th>Email</th><th>User ID</th><th>Total</th></tr>";
            foreach ($orders as $order) {
                  echo "<tr>";
                  echo "<td>{$order['id']}</td>";
                  echo "<td>{$order['customer_name']}</td>";
                  echo "<td>{$order['customer_email']}</td>";
                  echo "<td>" . ($order['user_id'] ? $order['user_id'] : 'NULL') . "</td>";
                  echo "<td>\${$order['total_amount']}</td>";
                  echo "</tr>";
            }
            echo "</table>";
      }

      // Step 4: Link orders to customers
      if (!empty($customers) && !empty($orders)) {
            echo "<h3>Step 4: Linking orders to customers...</h3>";

            // Count unlinked orders
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id IS NULL");
            $stmt->execute();
            $unlinked_count = $stmt->fetchColumn();

            if ($unlinked_count > 0) {
                  echo "<p>Found $unlinked_count orders without user_id. Linking them now...</p>";

                  // Link orders to customers based on email matching
                  $stmt = $pdo->prepare("
                UPDATE orders o 
                JOIN users u ON o.customer_email = u.email AND u.role = 'customer'
                SET o.user_id = u.id 
                WHERE o.customer_email IS NOT NULL AND o.customer_email != '' AND o.user_id IS NULL
            ");
                  $stmt->execute();
                  $linked_count = $stmt->rowCount();

                  echo "<p style='color: green;'>✓ Successfully linked $linked_count orders to customers</p>";
            } else {
                  echo "<p style='color: green;'>✓ All orders are already linked to customers</p>";
            }
      }

      // Step 5: Show final result
      echo "<h3>Step 5: Final result...</h3>";
      $stmt = $pdo->prepare("
        SELECT o.id, o.customer_name, o.customer_email, o.user_id, u.username 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.id DESC 
        LIMIT 10
    ");
      $stmt->execute();
      $final_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

      echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
      echo "<tr><th>Order ID</th><th>Customer Name</th><th>Email</th><th>User ID</th><th>Username</th></tr>";
      foreach ($final_orders as $order) {
            $user_id_display = $order['user_id'] ? $order['user_id'] : 'NULL';
            $username_display = $order['username'] ? $order['username'] : 'Guest';
            $row_color = $order['user_id'] ? 'background-color: #d4edda;' : 'background-color: #f8d7da;';

            echo "<tr style='$row_color'>";
            echo "<td>{$order['id']}</td>";
            echo "<td>{$order['customer_name']}</td>";
            echo "<td>{$order['customer_email']}</td>";
            echo "<td>$user_id_display</td>";
            echo "<td>$username_display</td>";
            echo "</tr>";
      }
      echo "</table>";

      echo "<h3>Summary</h3>";
      echo "<p style='color: green;'>✓ Database update completed successfully!</p>";
      echo "<p>Orders with a User ID are linked to customer accounts.</p>";
      echo "<p>Orders with NULL User ID are guest orders (not linked to any customer account).</p>";

      echo "<h3>Next Steps</h3>";
      echo "<p><a href='index.php' class='btn btn-primary'>Go to Homepage</a></p>";
      echo "<p><a href='customer_register.php' class='btn btn-success'>Register New Customer</a></p>";
      echo "<p><a href='login.php' class='btn btn-info'>Login as Customer</a></p>";
} catch (Exception $e) {
      echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
      body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f8f9fa;
      }

      .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      }

      table {
            margin: 10px 0;
            width: 100%;
            border-collapse: collapse;
      }

      th,
      td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
      }

      th {
            background-color: #f2f2f2;
            font-weight: bold;
      }

      .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            text-decoration: none;
            border-radius: 4px;
            color: white;
            font-weight: bold;
      }

      .btn-primary {
            background-color: #007bff;
      }

      .btn-success {
            background-color: #28a745;
      }

      .btn-info {
            background-color: #17a2b8;
      }
</style>