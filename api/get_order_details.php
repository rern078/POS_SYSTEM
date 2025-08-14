<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
      http_response_code(401);
      echo json_encode(['error' => 'Unauthorized']);
      exit();
}

$order_id = $_GET['order_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$order_id) {
      http_response_code(400);
      echo json_encode(['error' => 'Order ID required']);
      exit();
}

try {
      $pdo = getDBConnection();

      // Get order details
      $stmt = $pdo->prepare("
        SELECT o.*, u.full_name, u.email, u.phone
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ? AND o.user_id = ?
    ");
      $stmt->execute([$order_id, $user_id]);
      $order = $stmt->fetch();

      if (!$order) {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found']);
            exit();
      }

      // Get order items
      $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.product_code, p.image_path
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
      $stmt->execute([$order_id]);
      $items = $stmt->fetchAll();

      // Generate HTML
      $html = '
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-muted mb-2">Order Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Order ID:</strong></td><td>#' . $order['id'] . '</td></tr>
                <tr><td><strong>Date:</strong></td><td>' . date('M d, Y H:i', strtotime($order['created_at'])) . '</td></tr>
                <tr><td><strong>Status:</strong></td><td><span class="order-status status-' . $order['status'] . '">' . ucfirst($order['status']) . '</span></td></tr>
                <tr><td><strong>Payment Method:</strong></td><td>' . ucfirst($order['payment_method'] ?? 'N/A') . '</td></tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6 class="text-muted mb-2">Customer Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Name:</strong></td><td>' . htmlspecialchars($order['full_name'] ?? $order['customer_name']) . '</td></tr>
                <tr><td><strong>Email:</strong></td><td>' . htmlspecialchars($order['email'] ?? $order['customer_email']) . '</td></tr>
                <tr><td><strong>Phone:</strong></td><td>' . htmlspecialchars($order['phone'] ?? 'N/A') . '</td></tr>
            </table>
        </div>
    </div>
    
    <hr>
    
    <h6 class="text-muted mb-3">Order Items</h6>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Code</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';

      $subtotal = 0;
      foreach ($items as $item) {
            $item_total = $item['quantity'] * $item['price'];
            $subtotal += $item_total;

            $html .= '
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="' . ($item['image_path'] ? '../' . $item['image_path'] : '../images/placeholder.jpg') . '" 
                                 alt="' . htmlspecialchars($item['name']) . '" 
                                 class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
                            <span>' . htmlspecialchars($item['name']) . '</span>
                        </div>
                    </td>
                    <td>' . htmlspecialchars($item['product_code']) . '</td>
                    <td>' . $item['quantity'] . '</td>
                    <td>$' . number_format($item['price'], 2) . '</td>
                    <td>$' . number_format($item_total, 2) . '</td>
                </tr>';
      }

      $html .= '
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end"><strong>Total:</strong></td>
                    <td><strong>$' . number_format($order['total_amount'], 2) . '</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>';

      echo json_encode(['html' => $html]);
} catch (Exception $e) {
      http_response_code(500);
      echo json_encode(['error' => 'Server error']);
}
