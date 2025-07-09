<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
      header('Location: ../login.php');
      exit();
}

$pdo = getDBConnection();

// Get order details
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if (!$order_id) {
      die('Order ID is required');
}

// Get order information - ensure customer can only view their own orders
$user = getCurrentUser();
$stmt = $pdo->prepare("
    SELECT * FROM orders WHERE id = ? AND (user_id = ? OR (user_id IS NULL AND customer_email = ?))
");
$stmt->execute([$order_id, $user['id'], $user['email']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
      die('Order not found or access denied');
}

// Get order items
$stmt = $pdo->prepare("
    SELECT oi.*, p.name AS product_name, p.product_code
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If AJAX request, return only the receipt content without full HTML
if ($is_ajax) {
?>
      <div class="receipt-container" style="max-width: 100%; border: none; box-shadow: none;">
            <div class="row">
                  <div class="col-md-6">
                        <h6>Order Information</h6>
                        <table class="table table-sm">
                              <tr>
                                    <td><strong>Order ID:</strong></td>
                                    <td>#<?php echo $order_id; ?></td>
                              </tr>
                              <tr>
                                    <td><strong>Date:</strong></td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                              </tr>
                              <tr>
                                    <td><strong>Time:</strong></td>
                                    <td><?php echo date('H:i A', strtotime($order['created_at'])); ?></td>
                              </tr>
                              <tr>
                                    <td><strong>Status:</strong></td>
                                    <td><span class="badge bg-<?php echo $order['status'] === 'completed' ? 'success' : ($order['status'] === 'pending' ? 'warning' : 'danger'); ?>"><?php echo ucfirst($order['status']); ?></span></td>
                              </tr>
                              <tr>
                                    <td><strong>Payment Method:</strong></td>
                                    <td><?php echo ucfirst($order['payment_method']); ?></td>
                              </tr>
                        </table>
                  </div>
                  <div class="col-md-6">
                        <h6>Customer Information</h6>
                        <table class="table table-sm">
                              <tr>
                                    <td><strong>Name:</strong></td>
                                    <td><?php echo htmlspecialchars($order['customer_name'] ?: 'Walk-in Customer'); ?></td>
                              </tr>
                              <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo htmlspecialchars($order['customer_email'] ?: 'N/A'); ?></td>
                              </tr>
                              <tr>
                                    <td><strong>Cashier:</strong></td>
                                    <td><?php echo htmlspecialchars($_SESSION['username']); ?></td>
                              </tr>
                        </table>
                  </div>
            </div>

            <h6 class="mt-4">Order Items</h6>
            <div class="table-responsive">
                  <table class="table table-sm">
                        <thead>
                              <tr>
                                    <th>Product</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Total</th>
                              </tr>
                        </thead>
                        <tbody>
                              <?php foreach ($order_items as $item): ?>
                                    <tr>
                                          <td>
                                                <div class="d-flex align-items-center">
                                                      <img src="../<?php echo $item['image_path'] ?: 'images/placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" class="me-2">
                                                      <div>
                                                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($item['product_code']); ?></small>
                                                      </div>
                                                </div>
                                          </td>
                                          <td class="text-center"><?php echo $item['quantity']; ?></td>
                                          <td class="text-end">$<?php echo number_format($item['price'], 2); ?></td>
                                          <td class="text-end">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                    </tr>
                              <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                              <tr class="table-active">
                                    <th colspan="3">TOTAL</th>
                                    <th class="text-end">$<?php echo number_format($order['total_amount'], 2); ?></th>
                              </tr>
                        </tfoot>
                  </table>
            </div>
      </div>
<?php
      exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Receipt - Order #<?php echo $order_id; ?></title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

      <!-- User Receipt CSS -->
      <link rel="stylesheet" href="../assets/css/user-receipt.css">
</head>

<body>
      <!-- Print Button -->
      <div class="no-print text-center mt-3">
            <button class="btn btn-primary" onclick="window.print()">
                  <i class="fas fa-print me-2"></i>Print Receipt
            </button>
            <a href="pos.php" class="btn btn-secondary ms-2">
                  <i class="fas fa-arrow-left me-2"></i>Back to POS
            </a>
      </div>

      <!-- Receipt -->
      <div class="receipt-container">
            <!-- Receipt Header -->
            <div class="receipt-header">
                  <h3 class="mb-1">
                        <i class="fas fa-store text-primary me-2"></i>POS System
                  </h3>
                  <p class="mb-1">Modern Point of Sale Solution</p>
                  <div class="store-info">
                        <p class="mb-1">123 Business Street</p>
                        <p class="mb-1">City, State 12345</p>
                        <p class="mb-1">Phone: (555) 123-4567</p>
                        <p class="mb-0">Email: info@possystem.com</p>
                  </div>
            </div>

            <!-- Receipt Body -->
            <div class="receipt-body">
                  <!-- Order Information -->
                  <div class="order-info">
                        <div class="row">
                              <div class="col-6">
                                    <strong>Order #:</strong> <?php echo $order_id; ?>
                              </div>
                              <div class="col-6 text-end">
                                    <strong>Date:</strong> <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                              </div>
                        </div>
                        <div class="row">
                              <div class="col-6">
                                    <strong>Time:</strong> <?php echo date('H:i A', strtotime($order['created_at'])); ?>
                              </div>
                              <div class="col-6 text-end">
                                    <strong>Cashier:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?>
                              </div>
                        </div>
                        <?php if ($order['customer_name']): ?>
                              <div class="row">
                                    <div class="col-12">
                                          <strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?>
                                    </div>
                              </div>
                        <?php endif; ?>
                        <?php if ($order['customer_email']): ?>
                              <div class="row">
                                    <div class="col-12">
                                          <strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?>
                                    </div>
                              </div>
                        <?php endif; ?>
                  </div>

                  <!-- Items Header -->
                  <div class="item-row" style="font-weight: bold; border-bottom: 2px solid #000;">
                        <div class="item-name">Item</div>
                        <div class="item-quantity">Qty</div>
                        <div class="item-price">Price</div>
                  </div>

                  <!-- Order Items -->
                  <?php foreach ($order_items as $item): ?>
                        <div class="item-row">
                              <div class="item-name">
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($item['product_code']); ?></small>
                              </div>
                              <div class="item-quantity">
                                    <?php echo $item['quantity']; ?>
                              </div>
                              <div class="item-price">
                                    $<?php echo number_format($item['price'], 2); ?>
                              </div>
                        </div>
                  <?php endforeach; ?>

                  <!-- Total -->
                  <div class="item-row total-row">
                        <div class="item-name">TOTAL</div>
                        <div class="item-quantity"></div>
                        <div class="item-price">$<?php echo number_format($order['total_amount'], 2); ?></div>
                  </div>

                  <!-- Payment Information -->
                  <div class="mt-3">
                        <div class="row">
                              <div class="col-6">
                                    <strong>Payment Method:</strong>
                              </div>
                              <div class="col-6 text-end">
                                    <?php echo ucfirst($order['payment_method']); ?>
                              </div>
                        </div>
                        <div class="row">
                              <div class="col-6">
                                    <strong>Status:</strong>
                              </div>
                              <div class="col-6 text-end">
                                    <span class="badge bg-<?php echo $order['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                          <?php echo ucfirst($order['status']); ?>
                                    </span>
                              </div>
                        </div>
                  </div>
            </div>

            <!-- Receipt Footer -->
            <div class="receipt-footer">
                  <p class="mb-2"><strong>Thank you for your purchase!</strong></p>
                  <p class="mb-2">Please keep this receipt for your records</p>
                  <p class="mb-2">For returns, please bring this receipt within 30 days</p>
                  <p class="mb-0">
                        <small>
                              Receipt generated on <?php echo date('M d, Y H:i:s'); ?><br>
                              Transaction ID: <?php echo strtoupper(substr(md5($order_id . time()), 0, 8)); ?>
                        </small>
                  </p>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

      <script>
            // Auto-print when page loads (optional)
            // window.onload = function() {
            //     window.print();
            // };

            // Add keyboard shortcut for printing
            document.addEventListener('keydown', function(e) {
                  if (e.ctrlKey && e.key === 'p') {
                        e.preventDefault();
                        window.print();
                  }
            });
      </script>
</body>

</html>