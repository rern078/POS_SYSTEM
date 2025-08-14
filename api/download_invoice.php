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

      // Generate PDF content (simplified version - in production you'd use a proper PDF library)
      $pdf_content = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Invoice #{$order['id']}</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
            .header { border-bottom: 2px solid #667eea; padding-bottom: 20px; margin-bottom: 30px; }
            .company-info { float: left; }
            .invoice-info { float: right; text-align: right; }
            .clear { clear: both; }
            .customer-info { margin-bottom: 30px; }
            .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
            .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            .items-table th { background-color: #667eea; color: white; }
            .totals { float: right; width: 300px; }
            .totals table { width: 100%; border-collapse: collapse; }
            .totals td { padding: 5px; }
            .footer { border-top: 2px solid #667eea; padding-top: 20px; text-align: center; color: #666; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <div class='company-info'>
                <h2 style='color: #667eea; margin: 0;'>CH-FASHION</h2>
                <p style='margin: 5px 0; color: #666;'>Fashion Store</p>
                <p style='margin: 5px 0; color: #666;'>123 Fashion Street</p>
                <p style='margin: 5px 0; color: #666;'>Phnom Penh, Cambodia</p>
                <p style='margin: 5px 0; color: #666;'>Phone: +855 123 456 78</p>
                <p style='margin: 5px 0; color: #666;'>Email: info@ch-fashion.com</p>
            </div>
            <div class='invoice-info'>
                <h1 style='color: #667eea; margin: 0; font-size: 2.5rem;'>INVOICE</h1>
                <p style='margin: 10px 0; font-size: 1.2rem; color: #333;'>#{$order['id']}</p>
                <p style='margin: 5px 0; color: #666;'>Date: " . date('M d, Y', strtotime($order['created_at'])) . "</p>
                <p style='margin: 5px 0; color: #666;'>Due Date: " . date('M d, Y', strtotime($order['created_at'])) . "</p>
            </div>
            <div class='clear'></div>
        </div>
        
        <div class='customer-info'>
            <div style='float: left; width: 50%;'>
                <h4 style='color: #333; margin-bottom: 15px;'>Bill To:</h4>
                <p style='margin: 5px 0; font-weight: bold;'>" . htmlspecialchars($order['full_name'] ?? $order['customer_name']) . "</p>
                <p style='margin: 5px 0; color: #666;'>" . htmlspecialchars($order['email'] ?? $order['customer_email']) . "</p>
                <p style='margin: 5px 0; color: #666;'>Phone: " . htmlspecialchars($order['phone'] ?? 'N/A') . "</p>
            </div>
            <div style='float: right; width: 50%; text-align: right;'>
                <h4 style='color: #333; margin-bottom: 15px;'>Order Details:</h4>
                <p style='margin: 5px 0;'><strong>Order ID:</strong> #{$order['id']}</p>
                <p style='margin: 5px 0;'><strong>Status:</strong> " . ucfirst($order['status']) . "</p>
                <p style='margin: 5px 0;'><strong>Payment Method:</strong> " . ucfirst($order['payment_method'] ?? 'N/A') . "</p>
            </div>
            <div class='clear'></div>
        </div>
        
        <table class='items-table'>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Code</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>";

      $subtotal = 0;
      foreach ($items as $item) {
            $item_total = $item['quantity'] * $item['price'];
            $subtotal += $item_total;

            $pdf_content .= "
                <tr>
                    <td>" . htmlspecialchars($item['name']) . "</td>
                    <td>" . htmlspecialchars($item['product_code']) . "</td>
                    <td>{$item['quantity']}</td>
                    <td>$" . number_format($item['price'], 2) . "</td>
                    <td>$" . number_format($item_total, 2) . "</td>
                </tr>";
      }

      $pdf_content .= "
            </tbody>
        </table>
        
        <div class='totals'>
            <table>
                <tr>
                    <td style='text-align: right;'><strong>Subtotal:</strong></td>
                    <td style='text-align: right;'>$" . number_format($subtotal, 2) . "</td>
                </tr>
                <tr>
                    <td style='text-align: right;'><strong>Tax (0%):</strong></td>
                    <td style='text-align: right;'>$0.00</td>
                </tr>
                <tr style='border-top: 2px solid #667eea;'>
                    <td style='text-align: right; padding-top: 10px;'><strong>Total:</strong></td>
                    <td style='text-align: right; padding-top: 10px; font-size: 1.2rem; font-weight: bold; color: #667eea;'>$" . number_format($order['total_amount'], 2) . "</td>
                </tr>
            </table>
        </div>
        
        <div class='footer'>
            <p style='margin: 5px 0;'>Thank you for your business!</p>
            <p style='margin: 5px 0;'>For any questions, please contact us at info@ch-fashion.com</p>
            <p style='margin: 5px 0;'>This is a computer-generated invoice. No signature required.</p>
        </div>
    </body>
    </html>";

      // Set headers for PDF download
      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="invoice_' . $order_id . '.pdf"');
      header('Cache-Control: no-cache, no-store, must-revalidate');
      header('Pragma: no-cache');
      header('Expires: 0');

      // For now, we'll output HTML that can be converted to PDF by the browser
      // In production, you should use a proper PDF library like TCPDF, FPDF, or DOMPDF
      echo $pdf_content;
} catch (Exception $e) {
      http_response_code(500);
      echo json_encode(['error' => 'Server error']);
}
