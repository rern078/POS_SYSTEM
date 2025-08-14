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

      // Generate invoice HTML
      $html = '
    <div class="invoice-container" style="max-width: 800px; margin: 0 auto; font-family: Arial, sans-serif;">
        <!-- Invoice Header -->
        <div class="invoice-header" style="border-bottom: 2px solid #667eea; padding-bottom: 20px; margin-bottom: 30px;">
            <div class="row">
                <div class="col-6">
                    <h2 style="color: #667eea; margin: 0;">CH-FASHION</h2>
                    <p style="margin: 5px 0; color: #666;">Fashion Store</p>
                    <p style="margin: 5px 0; color: #666;">123 Fashion Street</p>
                    <p style="margin: 5px 0; color: #666;">Phnom Penh, Cambodia</p>
                    <p style="margin: 5px 0; color: #666;">Phone: +855 123 456 78</p>
                    <p style="margin: 5px 0; color: #666;">Email: info@ch-fashion.com</p>
                </div>
                <div class="col-6 text-end">
                    <h1 style="color: #667eea; margin: 0; font-size: 2.5rem;">INVOICE</h1>
                    <p style="margin: 10px 0; font-size: 1.2rem; color: #333;">#' . $order['id'] . '</p>
                    <p style="margin: 5px 0; color: #666;">Date: ' . date('M d, Y', strtotime($order['created_at'])) . '</p>
                    <p style="margin: 5px 0; color: #666;">Due Date: ' . date('M d, Y', strtotime($order['created_at'])) . '</p>
                </div>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div class="customer-info" style="margin-bottom: 30px;">
            <div class="row">
                <div class="col-6">
                    <h4 style="color: #333; margin-bottom: 15px;">Bill To:</h4>
                    <p style="margin: 5px 0; font-weight: bold;">' . htmlspecialchars($order['full_name'] ?? $order['customer_name']) . '</p>
                    <p style="margin: 5px 0; color: #666;">' . htmlspecialchars($order['email'] ?? $order['customer_email']) . '</p>
                    <p style="margin: 5px 0; color: #666;">Phone: ' . htmlspecialchars($order['phone'] ?? 'N/A') . '</p>
                </div>
                <div class="col-6 text-end">
                    <h4 style="color: #333; margin-bottom: 15px;">Order Details:</h4>
                    <p style="margin: 5px 0;"><strong>Order ID:</strong> #' . $order['id'] . '</p>
                    <p style="margin: 5px 0;"><strong>Status:</strong> <span style="color: ' . ($order['status'] === 'completed' ? '#28a745' : ($order['status'] === 'pending' ? '#ffc107' : '#dc3545')) . ';">' . ucfirst($order['status']) . '</span></p>
                    <p style="margin: 5px 0;"><strong>Payment Method:</strong> ' . ucfirst($order['payment_method'] ?? 'N/A') . '</p>
                </div>
            </div>
        </div>
        
        <!-- Items Table -->
        <div class="items-table" style="margin-bottom: 30px;">
            <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">
                <thead>
                    <tr style="background-color: #667eea; color: white;">
                        <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Item</th>
                        <th style="padding: 12px; text-align: center; border: 1px solid #ddd;">Code</th>
                        <th style="padding: 12px; text-align: center; border: 1px solid #ddd;">Quantity</th>
                        <th style="padding: 12px; text-align: right; border: 1px solid #ddd;">Unit Price</th>
                        <th style="padding: 12px; text-align: right; border: 1px solid #ddd;">Total</th>
                    </tr>
                </thead>
                <tbody>';

      $subtotal = 0;
      foreach ($items as $item) {
            $item_total = $item['quantity'] * $item['price'];
            $subtotal += $item_total;

            $html .= '
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 12px; border: 1px solid #ddd;">
                            <div style="display: flex; align-items: center;">
                                <img src="' . ($item['image_path'] ? '../' . $item['image_path'] : '../images/placeholder.jpg') . '" 
                                     alt="' . htmlspecialchars($item['name']) . '" 
                                     style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 10px;">
                                <span>' . htmlspecialchars($item['name']) . '</span>
                            </div>
                        </td>
                        <td style="padding: 12px; text-align: center; border: 1px solid #ddd;">' . htmlspecialchars($item['product_code']) . '</td>
                        <td style="padding: 12px; text-align: center; border: 1px solid #ddd;">' . $item['quantity'] . '</td>
                        <td style="padding: 12px; text-align: right; border: 1px solid #ddd;">$' . number_format($item['price'], 2) . '</td>
                        <td style="padding: 12px; text-align: right; border: 1px solid #ddd;">$' . number_format($item_total, 2) . '</td>
                    </tr>';
      }

      $html .= '
                </tbody>
            </table>
        </div>
        
        <!-- Totals -->
        <div class="totals" style="margin-bottom: 30px;">
            <div class="row">
                <div class="col-6"></div>
                <div class="col-6">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px; text-align: right;"><strong>Subtotal:</strong></td>
                            <td style="padding: 8px; text-align: right;">$' . number_format($subtotal, 2) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; text-align: right;"><strong>Tax (0%):</strong></td>
                            <td style="padding: 8px; text-align: right;">$0.00</td>
                        </tr>
                        <tr style="border-top: 2px solid #667eea;">
                            <td style="padding: 12px; text-align: right;"><strong>Total:</strong></td>
                            <td style="padding: 12px; text-align: right; font-size: 1.2rem; font-weight: bold; color: #667eea;">$' . number_format($order['total_amount'], 2) . '</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="invoice-footer" style="border-top: 2px solid #667eea; padding-top: 20px; text-align: center; color: #666;">
            <p style="margin: 5px 0;">Thank you for your business!</p>
            <p style="margin: 5px 0;">For any questions, please contact us at info@ch-fashion.com</p>
            <p style="margin: 5px 0;">This is a computer-generated invoice. No signature required.</p>
        </div>
    </div>';

      echo json_encode(['html' => $html]);
} catch (Exception $e) {
      http_response_code(500);
      echo json_encode(['error' => 'Server error']);
}
