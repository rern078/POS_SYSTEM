<?php
session_start();
require_once 'config/database.php';

$pdo = getDBConnection();

// Get order details
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if (!$order_id) {
      die('Order ID is required');
}

// Get order information
$stmt = $pdo->prepare("
    SELECT * FROM orders WHERE id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
      die('Order not found');
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
      <div class="receipt-content">
            <!-- Receipt Header -->
            <div class="receipt-header text-center mb-4">
                  <h3 class="mb-2"><i class="fas fa-store text-primary me-2"></i>POS System</h3>
                  <p class="mb-1">123 Main Street, City, State 12345</p>
                  <p class="mb-1">Phone: (555) 123-4567</p>
                  <p class="mb-3">Email: info@possystem.com</p>
                  <hr>
                  <h5 class="mb-2">RECEIPT</h5>
                  <p class="mb-1">Order #<?php echo $order_id; ?></p>
                  <p class="mb-3">Date: <?php echo date('M d, Y H:i:s', strtotime($order['created_at'])); ?></p>
            </div>

            <!-- Customer Information -->
            <div class="customer-info mb-3">
                  <div class="row">
                        <div class="col-6">
                              <strong>Customer:</strong><br>
                              <?php echo htmlspecialchars($order['customer_name']); ?>
                        </div>
                        <div class="col-6 text-end">
                              <strong>Email:</strong><br>
                              <?php echo htmlspecialchars($order['customer_email']); ?>
                        </div>
                  </div>
            </div>

            <!-- Items Table -->
            <div class="table-responsive">
                  <table class="table table-sm">
                        <thead>
                              <tr>
                                    <th>Item</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Total</th>
                              </tr>
                        </thead>
                        <tbody>
                              <?php foreach ($order_items as $item): ?>
                                    <tr>
                                          <td>
                                                <?php echo htmlspecialchars($item['product_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($item['product_code']); ?></small>
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

      <!-- Receipt CSS -->
      <link rel="stylesheet" href="assets/css/receipt.css">
</head>

<body class="bg-light">
      <div class="receipt-container">
            <!-- Receipt Header -->
            <div class="receipt-header">
                  <h3 class="mb-2">
                        <i class="fas fa-store me-2"></i>POS System
                  </h3>
                  <p class="mb-1">123 Main Street, City, State 12345</p>
                  <p class="mb-1">Phone: (555) 123-4567</p>
                  <p class="mb-0">Email: info@possystem.com</p>
            </div>

            <!-- Receipt Body -->
            <div class="receipt-body">
                  <div class="text-center mb-4">
                        <h5 class="mb-2">RECEIPT</h5>
                        <div class="receipt-number">Order #<?php echo $order_id; ?></div>
                        <div class="receipt-date"><?php echo date('M d, Y H:i:s', strtotime($order['created_at'])); ?></div>
                  </div>

                  <!-- Customer Information -->
                  <div class="customer-info">
                        <div class="row">
                              <div class="col-6">
                                    <strong>Customer:</strong><br>
                                    <?php echo htmlspecialchars($order['customer_name']); ?>
                              </div>
                              <div class="col-6 text-end">
                                    <strong>Email:</strong><br>
                                    <?php echo htmlspecialchars($order['customer_email']); ?>
                              </div>
                        </div>
                  </div>

                  <!-- Items Table -->
                  <div class="table-responsive">
                        <table class="table table-sm">
                              <thead>
                                    <tr>
                                          <th>Item</th>
                                          <th class="text-center">Qty</th>
                                          <th class="text-end">Price</th>
                                          <th class="text-end">Total</th>
                                    </tr>
                              </thead>
                              <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                          <tr>
                                                <td>
                                                      <?php echo htmlspecialchars($item['product_name']); ?>
                                                      <br><small class="text-muted"><?php echo htmlspecialchars($item['product_code']); ?></small>
                                                </td>
                                                <td class="text-center"><?php echo $item['quantity']; ?></td>
                                                <td class="text-end">$<?php echo number_format($item['price'], 2); ?></td>
                                                <td class="text-end">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                          </tr>
                                    <?php endforeach; ?>
                              </tbody>
                              <tfoot>
                                    <tr class="table-active total-row">
                                          <th colspan="3">TOTAL</th>
                                          <th class="text-end">$<?php echo number_format($order['total_amount'], 2); ?></th>
                                    </tr>
                              </tfoot>
                        </table>
                  </div>

                  <!-- Payment Information -->
                  <div class="payment-info">
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
                  <p class="mb-3">
                        <small>
                              Receipt generated on <?php echo date('M d, Y H:i:s'); ?><br>
                              Transaction ID: <?php echo strtoupper(substr(md5($order_id . time()), 0, 8)); ?>
                        </small>
                  </p>

                  <!-- Action Buttons -->
                  <div class="no-print">
                        <button class="btn btn-print me-2" onclick="window.print()">
                              <i class="fas fa-print me-2"></i>Print Receipt
                        </button>
                        <a href="index.php" class="btn btn-home">
                              <i class="fas fa-home me-2"></i>Back to Home
                        </a>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>