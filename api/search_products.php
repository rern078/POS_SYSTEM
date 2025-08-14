<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
      http_response_code(401);
      echo json_encode(['error' => 'Unauthorized']);
      exit();
}

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

try {
      $pdo = getDBConnection();

      // Build query
      $where_conditions = ['stock_quantity > 0'];
      $params = [];

      if (!empty($search)) {
            $where_conditions[] = '(name LIKE ? OR product_code LIKE ? OR description LIKE ?)';
            $search_param = '%' . $search . '%';
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
      }

      if (!empty($category)) {
            $where_conditions[] = 'category = ?';
            $params[] = $category;
      }

      $where_clause = implode(' AND ', $where_conditions);

      $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE $where_clause 
        ORDER BY name ASC 
        LIMIT 20
    ");
      $stmt->execute($params);
      $products = $stmt->fetchAll();

      // Generate HTML
      if (empty($products)) {
            $html = '
        <div class="text-center py-5">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No products found</h5>
            <p class="text-muted">Try adjusting your search terms or category filter.</p>
        </div>';
      } else {
            $html = '<div class="row">';
            foreach ($products as $product) {
                  $html .= '
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="price-check-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            ' . ($product['image_path'] ?
                        '<img src="../' . htmlspecialchars($product['image_path']) . '" 
                                      alt="' . htmlspecialchars($product['name']) . '" 
                                      class="rounded" style="width: 60px; height: 60px; object-fit: cover;">' :
                        '<div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                      style="width: 60px; height: 60px;">
                                     <i class="fas fa-image text-muted"></i>
                                 </div>'
                  ) . '
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-1">' . htmlspecialchars($product['name']) . '</h6>
                            <p class="mb-1 small">Code: ' . htmlspecialchars($product['product_code']) . '</p>
                            ' . ($product['category'] ? '<p class="mb-1 small text-muted">Category: ' . ucfirst(htmlspecialchars($product['category'])) . '</p>' : '') . '
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">$' . number_format($product['price'], 2) . '</span>
                                <span class="badge bg-success">In Stock: ' . $product['stock_quantity'] . '</span>
                            </div>';

                  if ($product['discount_price']) {
                        $html .= '
                            <small class="text-warning">
                                <i class="fas fa-tag me-1"></i>Discounted: $' . number_format($product['discount_price'], 2) . '
                            </small>';
                  }

                  $html .= '
                        </div>
                    </div>
                </div>
            </div>';
            }
            $html .= '</div>';
      }

      echo json_encode(['html' => $html]);
} catch (Exception $e) {
      http_response_code(500);
      echo json_encode(['error' => 'Server error']);
}
