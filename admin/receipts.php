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

// Handle AJAX requests for receipt operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
      header('Content-Type: application/json');

      switch ($_POST['action']) {
            case 'get_receipt_details':
                  $order_id = (int)$_POST['order_id'];

                  // Get order information
                  $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                  $stmt->execute([$order_id]);
                  $order = $stmt->fetch(PDO::FETCH_ASSOC);

                  if (!$order) {
                        echo json_encode(['success' => false, 'message' => 'Order not found']);
                        exit;
                  }

                  // Get order items
                  $stmt = $pdo->prepare("
                        SELECT oi.*, p.name AS product_name, p.product_code, p.image_path
                        FROM order_items oi
                        JOIN products p ON oi.product_id = p.id
                        WHERE oi.order_id = ?
                  ");
                  $stmt->execute([$order_id]);
                  $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                  echo json_encode([
                        'success' => true,
                        'order' => $order,
                        'items' => $order_items
                  ]);
                  exit;

            case 'print_receipt':
                  $order_id = (int)$_POST['order_id'];

                  // Get order information
                  $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                  $stmt->execute([$order_id]);
                  $order = $stmt->fetch(PDO::FETCH_ASSOC);

                  if (!$order) {
                        echo json_encode(['success' => false, 'message' => 'Order not found']);
                        exit;
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

                  // Generate receipt HTML
                  $receipt_html = generateReceiptHTML($order, $order_items);

                  echo json_encode([
                        'success' => true,
                        'receipt_html' => $receipt_html
                  ]);
                  exit;
      }
}

// Get receipts with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

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

// Get total count
$count_sql = "SELECT COUNT(*) FROM orders o $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_receipts = $stmt->fetchColumn();
$total_pages = ceil($total_receipts / $limit);

// Get receipts
$sql = "SELECT o.*, COUNT(oi.id) as item_count 
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        $where_clause 
        GROUP BY o.id 
        ORDER BY o.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get receipt statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_receipts,
    SUM(total_amount) as total_revenue,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_receipts,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_receipts,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_receipts,
    COUNT(CASE WHEN payment_method = 'cash' THEN 1 END) as cash_payments,
    COUNT(CASE WHEN payment_method = 'card' THEN 1 END) as card_payments
FROM orders");
$stmt->execute();
$receipt_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Function to generate receipt HTML
function generateReceiptHTML($order, $order_items)
{
      $html = '
      <div class="receipt-print" style="font-family: monospace; max-width: 300px; margin: 0 auto; padding: 20px;">
            <div style="text-align: center; border-bottom: 1px dashed #ccc; padding-bottom: 10px; margin-bottom: 15px;">
                  <h3 style="margin: 0; font-size: 18px;">POS SYSTEM</h3>
                  <p style="margin: 5px 0; font-size: 12px;">Receipt #' . $order['id'] . '</p>
                  <p style="margin: 5px 0; font-size: 12px;">' . date('M d, Y H:i A', strtotime($order['created_at'])) . '</p>
            </div>
            
            <div style="margin-bottom: 15px;">
                  <p style="margin: 5px 0; font-size: 12px;"><strong>Customer:</strong> ' . htmlspecialchars($order['customer_name'] ?: 'Walk-in Customer') . '</p>
                  <p style="margin: 5px 0; font-size: 12px;"><strong>Email:</strong> ' . htmlspecialchars($order['customer_email'] ?: 'N/A') . '</p>
            </div>
            
            <div style="border-bottom: 1px dashed #ccc; padding-bottom: 10px; margin-bottom: 15px;">
                  <table style="width: 100%; font-size: 12px;">
                        <thead>
                              <tr style="border-bottom: 1px solid #ccc;">
                                    <th style="text-align: left; padding: 5px 0;">Item</th>
                                    <th style="text-align: center; padding: 5px 0;">Qty</th>
                                    <th style="text-align: right; padding: 5px 0;">Price</th>
                                    <th style="text-align: right; padding: 5px 0;">Total</th>
                              </tr>
                        </thead>
                        <tbody>';

      foreach ($order_items as $item) {
            $html .= '
                              <tr>
                                    <td style="padding: 3px 0; font-size: 11px;">' . htmlspecialchars($item['product_name']) . '</td>
                                    <td style="text-align: center; padding: 3px 0; font-size: 11px;">' . $item['quantity'] . '</td>
                                    <td style="text-align: right; padding: 3px 0; font-size: 11px;">$' . number_format($item['price'], 2) . '</td>
                                    <td style="text-align: right; padding: 3px 0; font-size: 11px;">$' . number_format($item['price'] * $item['quantity'], 2) . '</td>
                              </tr>';
      }

      $html .= '
                        </tbody>
                  </table>
            </div>
            
            <div style="border-top: 1px dashed #ccc; padding-top: 10px;">
                  <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px;">
                        <span><strong>Total:</strong></span>
                        <span><strong>$' . number_format($order['total_amount'], 2) . '</strong></span>
                  </div>
                  <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px;">
                        <span>Payment Method:</span>
                        <span>' . ucfirst($order['payment_method']) . '</span>
                  </div>
                  <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px;">
                        <span>Status:</span>
                        <span>' . ucfirst($order['status']) . '</span>
                  </div>
            </div>
            
            <div style="text-align: center; margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 10px;">
                  <p style="margin: 5px 0; font-size: 10px;">Thank you for your purchase!</p>
                  <p style="margin: 5px 0; font-size: 10px;">Please keep this receipt for your records</p>
            </div>
      </div>';

      return $html;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <?php include 'include/title.inc.php'; ?>
</head>

<body>
      <div class="admin-layout">
            <?php include 'side.php'; ?>

            <!-- Main Content Wrapper -->
            <div class="admin-main">
                  <!-- Top Navigation Bar -->
                  <nav class="admin-topbar">
                        <div class="topbar-left">
                              <button class="btn btn-link sidebar-toggle-btn" id="sidebarToggleBtn">
                                    <i class="fas fa-bars"></i>
                              </button>
                              <div class="breadcrumb-container">
                                    <nav aria-label="breadcrumb">
                                          <ol class="breadcrumb">
                                                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                                                <li class="breadcrumb-item active" aria-current="page">Receipts</li>
                                          </ol>
                                    </nav>
                              </div>
                        </div>
                        <div class="topbar-right">
                              <div class="topbar-actions">
                                    <div class="dropdown">
                                          <button class="btn btn-link notification-btn" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-bell"></i>
                                                <span class="notification-badge">3</span>
                                          </button>
                                          <ul class="dropdown-menu dropdown-menu-end notification-menu" aria-labelledby="notificationDropdown">
                                                <li>
                                                      <h6 class="dropdown-header">Notifications</h6>
                                                </li>
                                                <li><a class="dropdown-item" href="#"><i class="fas fa-exclamation-circle text-warning me-2"></i>Low stock alert</a></li>
                                                <li><a class="dropdown-item" href="#"><i class="fas fa-check-circle text-success me-2"></i>Order completed</a></li>
                                                <li><a class="dropdown-item" href="#"><i class="fas fa-info-circle text-info me-2"></i>New user registered</a></li>
                                                <li>
                                                      <hr class="dropdown-divider">
                                                </li>
                                                <li><a class="dropdown-item text-center" href="#">View all notifications</a></li>
                                          </ul>
                                    </div>
                              </div>
                        </div>
                  </nav>

                  <!-- Page Content -->
                  <div class="admin-content">
                        <!-- Page Header -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h1 class="content-card-title">
                                          <i class="fas fa-receipt me-2"></i>Receipts Management
                                    </h1>
                                    <div class="content-card-actions">
                                          <button class="btn btn-primary" onclick="exportReceipts()">
                                                <i class="fas fa-download me-2"></i>Export
                                          </button>
                                          <button class="btn btn-secondary" onclick="printSelectedReceipts()">
                                                <i class="fas fa-print me-2"></i>Print Selected
                                          </button>
                                    </div>
                              </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card">
                                          <div class="stat-card-icon bg-primary">
                                                <i class="fas fa-receipt"></i>
                                          </div>
                                          <div class="stat-card-content">
                                                <h3 class="stat-card-number"><?php echo number_format($receipt_stats['total_receipts']); ?></h3>
                                                <p class="stat-card-label">Total Receipts</p>
                                          </div>
                                    </div>
                              </div>
                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card">
                                          <div class="stat-card-icon bg-success">
                                                <i class="fas fa-dollar-sign"></i>
                                          </div>
                                          <div class="stat-card-content">
                                                <h3 class="stat-card-number">$<?php echo number_format($receipt_stats['total_revenue'], 2); ?></h3>
                                                <p class="stat-card-label">Total Revenue</p>
                                          </div>
                                    </div>
                              </div>
                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card">
                                          <div class="stat-card-icon bg-info">
                                                <i class="fas fa-credit-card"></i>
                                          </div>
                                          <div class="stat-card-content">
                                                <h3 class="stat-card-number"><?php echo number_format($receipt_stats['card_payments']); ?></h3>
                                                <p class="stat-card-label">Card Payments</p>
                                          </div>
                                    </div>
                              </div>
                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card">
                                          <div class="stat-card-icon bg-warning">
                                                <i class="fas fa-money-bill-wave"></i>
                                          </div>
                                          <div class="stat-card-content">
                                                <h3 class="stat-card-number"><?php echo number_format($receipt_stats['cash_payments']); ?></h3>
                                                <p class="stat-card-label">Cash Payments</p>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Search and Filter Card -->
                        <div class="content-card mb-4">
                              <div class="content-card-body">
                                    <form method="GET" class="row g-3">
                                          <div class="col-md-3">
                                                <label for="search" class="form-label">Search</label>
                                                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Customer name, email, or receipt ID">
                                          </div>
                                          <div class="col-md-2">
                                                <label for="status" class="form-label">Status</label>
                                                <select class="form-select" id="status" name="status">
                                                      <option value="">All Status</option>
                                                      <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                      <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                      <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                          </div>
                                          <div class="col-md-2">
                                                <label for="payment_method" class="form-label">Payment Method</label>
                                                <select class="form-select" id="payment_method" name="payment_method">
                                                      <option value="">All Methods</option>
                                                      <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                                      <option value="card" <?php echo $payment_method === 'card' ? 'selected' : ''; ?>>Card</option>
                                                </select>
                                          </div>
                                          <div class="col-md-2">
                                                <label for="date_from" class="form-label">Date From</label>
                                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                                          </div>
                                          <div class="col-md-2">
                                                <label for="date_to" class="form-label">Date To</label>
                                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                                          </div>
                                          <div class="col-md-1">
                                                <label class="form-label">&nbsp;</label>
                                                <div class="d-grid">
                                                      <button type="submit" class="btn btn-primary">
                                                            <i class="fas fa-search"></i>
                                                      </button>
                                                </div>
                                          </div>
                                    </form>
                              </div>
                        </div>

                        <!-- Receipts Table Card -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h5 class="content-card-title">Receipts List</h5>
                                    <div class="content-card-actions">
                                          <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                                <label class="form-check-label" for="selectAll">
                                                      Select All
                                                </label>
                                          </div>
                                    </div>
                              </div>
                              <div class="content-card-body">
                                    <div class="table-responsive">
                                          <table class="table table-hover">
                                                <thead>
                                                      <tr>
                                                            <th width="50">
                                                                  <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                                                            </th>
                                                            <th>Receipt ID</th>
                                                            <th>Customer</th>
                                                            <th>Items</th>
                                                            <th>Total Amount</th>
                                                            <th>Payment Method</th>
                                                            <th>Status</th>
                                                            <th>Date</th>
                                                            <th width="150">Actions</th>
                                                      </tr>
                                                </thead>
                                                <tbody>
                                                      <?php if (empty($receipts)): ?>
                                                            <tr>
                                                                  <td colspan="9" class="text-center py-4">
                                                                        <div class="empty-state">
                                                                              <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                                                              <h5>No receipts found</h5>
                                                                              <p class="text-muted">No receipts match your current search criteria.</p>
                                                                        </div>
                                                                  </td>
                                                            </tr>
                                                      <?php else: ?>
                                                            <?php foreach ($receipts as $receipt): ?>
                                                                  <tr>
                                                                        <td>
                                                                              <input type="checkbox" class="form-check-input receipt-checkbox" value="<?php echo $receipt['id']; ?>">
                                                                        </td>
                                                                        <td>
                                                                              <strong>#<?php echo $receipt['id']; ?></strong>
                                                                        </td>
                                                                        <td>
                                                                              <div>
                                                                                    <strong><?php echo htmlspecialchars($receipt['customer_name'] ?: 'Walk-in Customer'); ?></strong>
                                                                                    <?php if ($receipt['customer_email']): ?>
                                                                                          <br><small class="text-muted"><?php echo htmlspecialchars($receipt['customer_email']); ?></small>
                                                                                    <?php endif; ?>
                                                                              </div>
                                                                        </td>
                                                                        <td>
                                                                              <span class="badge bg-secondary"><?php echo $receipt['item_count']; ?> items</span>
                                                                        </td>
                                                                        <td>
                                                                              <strong>$<?php echo number_format($receipt['total_amount'], 2); ?></strong>
                                                                        </td>
                                                                        <td>
                                                                              <span class="badge bg-<?php echo $receipt['payment_method'] === 'cash' ? 'success' : 'info'; ?>">
                                                                                    <?php echo ucfirst($receipt['payment_method']); ?>
                                                                              </span>
                                                                        </td>
                                                                        <td>
                                                                              <span class="badge bg-<?php echo $receipt['status'] === 'completed' ? 'success' : ($receipt['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                                                    <?php echo ucfirst($receipt['status']); ?>
                                                                              </span>
                                                                        </td>
                                                                        <td>
                                                                              <div>
                                                                                    <div><?php echo date('M d, Y', strtotime($receipt['created_at'])); ?></div>
                                                                                    <small class="text-muted"><?php echo date('H:i A', strtotime($receipt['created_at'])); ?></small>
                                                                              </div>
                                                                        </td>
                                                                        <td>
                                                                              <div class="btn-group btn-group-sm">
                                                                                    <button type="button" class="btn btn-outline-primary" onclick="viewReceipt(<?php echo $receipt['id']; ?>)" title="View Receipt">
                                                                                          <i class="fas fa-eye"></i>
                                                                                    </button>
                                                                                    <button type="button" class="btn btn-outline-secondary" onclick="printReceipt(<?php echo $receipt['id']; ?>)" title="Print Receipt">
                                                                                          <i class="fas fa-print"></i>
                                                                                    </button>
                                                                                    <button type="button" class="btn btn-outline-info" onclick="downloadReceipt(<?php echo $receipt['id']; ?>)" title="Download PDF">
                                                                                          <i class="fas fa-download"></i>
                                                                                    </button>
                                                                              </div>
                                                                        </td>
                                                                  </tr>
                                                            <?php endforeach; ?>
                                                      <?php endif; ?>
                                                </tbody>
                                          </table>
                                    </div>

                                    <!-- Pagination -->
                                    <?php if ($total_pages > 1): ?>
                                          <nav aria-label="Receipts pagination" class="mt-4">
                                                <ul class="pagination justify-content-center">
                                                      <?php if ($page > 1): ?>
                                                            <li class="page-item">
                                                                  <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&payment_method=<?php echo urlencode($payment_method); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                                                        Previous
                                                                  </a>
                                                            </li>
                                                      <?php endif; ?>

                                                      <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                                  <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&payment_method=<?php echo urlencode($payment_method); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                                                        <?php echo $i; ?>
                                                                  </a>
                                                            </li>
                                                      <?php endfor; ?>

                                                      <?php if ($page < $total_pages): ?>
                                                            <li class="page-item">
                                                                  <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&payment_method=<?php echo urlencode($payment_method); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                                                        Next
                                                                  </a>
                                                            </li>
                                                      <?php endif; ?>
                                                </ul>
                                          </nav>
                                    <?php endif; ?>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Receipt Modal -->
      <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="receiptModalLabel">Receipt Details</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="receiptModalBody">
                              <!-- Receipt content will be loaded here -->
                        </div>
                        <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                              <button type="button" class="btn btn-primary" onclick="printCurrentReceipt()">
                                    <i class="fas fa-print me-2"></i>Print
                              </button>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Print Modal -->
      <div class="modal fade" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="printModalLabel">Print Receipt</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="printModalBody">
                              <!-- Print content will be loaded here -->
                        </div>
                        <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                              <button type="button" class="btn btn-primary" onclick="executePrint()">
                                    <i class="fas fa-print me-2"></i>Print
                              </button>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- jQuery -->
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>

      <script>
            let currentReceiptId = null;

            // Select all functionality
            document.getElementById('selectAllCheckbox').addEventListener('change', function() {
                  const checkboxes = document.querySelectorAll('.receipt-checkbox');
                  checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                  });
            });

            // View receipt details
            function viewReceipt(receiptId) {
                  $.post('receipts.php', {
                        action: 'get_receipt_details',
                        order_id: receiptId
                  }, function(response) {
                        if (response.success) {
                              const order = response.order;
                              const items = response.items;

                              let itemsHtml = '';
                              items.forEach(item => {
                                    itemsHtml += `
                                          <tr>
                                                <td>
                                                      <div class="d-flex align-items-center">
                                                            <img src="../${item.image_path || 'images/placeholder.jpg'}" alt="${item.product_name}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" class="me-2">
                                                            <div>
                                                                  <strong>${item.product_name}</strong><br>
                                                                  <small class="text-muted">${item.product_code}</small>
                                                            </div>
                                                      </div>
                                                </td>
                                                <td class="text-center">${item.quantity}</td>
                                                <td class="text-end">$${parseFloat(item.price).toFixed(2)}</td>
                                                <td class="text-end">$${(item.price * item.quantity).toFixed(2)}</td>
                                          </tr>
                                    `;
                              });

                              const modalBody = `
                                    <div class="row">
                                          <div class="col-md-6">
                                                <h6>Order Information</h6>
                                                <table class="table table-sm">
                                                      <tr>
                                                            <td><strong>Receipt ID:</strong></td>
                                                            <td>#${order.id}</td>
                                                      </tr>
                                                      <tr>
                                                            <td><strong>Date:</strong></td>
                                                            <td>${new Date(order.created_at).toLocaleDateString()}</td>
                                                      </tr>
                                                      <tr>
                                                            <td><strong>Time:</strong></td>
                                                            <td>${new Date(order.created_at).toLocaleTimeString()}</td>
                                                      </tr>
                                                      <tr>
                                                            <td><strong>Status:</strong></td>
                                                            <td><span class="badge bg-${order.status === 'completed' ? 'success' : (order.status === 'pending' ? 'warning' : 'danger')}">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span></td>
                                                      </tr>
                                                      <tr>
                                                            <td><strong>Payment Method:</strong></td>
                                                            <td>${order.payment_method.charAt(0).toUpperCase() + order.payment_method.slice(1)}</td>
                                                      </tr>
                                                      ${order.payment_method === 'card' && order.card_type ? `
                                                      <tr>
                                                            <td><strong>Card Type:</strong></td>
                                                            <td>${order.card_type.charAt(0).toUpperCase() + order.card_type.slice(1)}</td>
                                                      </tr>
                                                      <tr>
                                                            <td><strong>Card Number:</strong></td>
                                                            <td>**** **** **** ${order.card_number ? order.card_number.slice(-4) : '****'}</td>
                                                      </tr>
                                                      <tr>
                                                            <td><strong>Cardholder:</strong></td>
                                                            <td>${order.card_holder || 'N/A'}</td>
                                                      </tr>
                                                      ` : ''}
                                                </table>
                                          </div>
                                          <div class="col-md-6">
                                                <h6>Customer Information</h6>
                                                <table class="table table-sm">
                                                      <tr>
                                                            <td><strong>Name:</strong></td>
                                                            <td>${order.customer_name || 'Walk-in Customer'}</td>
                                                      </tr>
                                                      <tr>
                                                            <td><strong>Email:</strong></td>
                                                            <td>${order.customer_email || 'N/A'}</td>
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
                                                      ${itemsHtml}
                                                </tbody>
                                                <tfoot>
                                                      <tr class="table-active">
                                                            <th colspan="3">TOTAL</th>
                                                            <th class="text-end">$${parseFloat(order.total_amount).toFixed(2)}</th>
                                                      </tr>
                                                </tfoot>
                                          </table>
                                    </div>
                              `;

                              document.getElementById('receiptModalBody').innerHTML = modalBody;
                              currentReceiptId = receiptId;
                              new bootstrap.Modal(document.getElementById('receiptModal')).show();
                        } else {
                              alert('Error loading receipt details: ' + response.message);
                        }
                  }, 'json');
            }

            // Print receipt
            function printReceipt(receiptId) {
                  $.post('receipts.php', {
                        action: 'print_receipt',
                        order_id: receiptId
                  }, function(response) {
                        if (response.success) {
                              document.getElementById('printModalBody').innerHTML = response.receipt_html;
                              new bootstrap.Modal(document.getElementById('printModal')).show();
                        } else {
                              alert('Error loading receipt: ' + response.message);
                        }
                  }, 'json');
            }

            // Execute print
            function executePrint() {
                  const printContent = document.getElementById('printModalBody').innerHTML;
                  const printWindow = window.open('', '_blank');
                  printWindow.document.write(`
                        <html>
                              <head>
                                    <title>Receipt</title>
                                    <style>
                                          body { font-family: monospace; margin: 0; padding: 20px; }
                                          .receipt-print { max-width: 300px; margin: 0 auto; }
                                    </style>
                              </head>
                              <body>
                                    ${printContent}
                              </body>
                        </html>
                  `);
                  printWindow.document.close();
                  printWindow.print();
            }

            // Print current receipt from modal
            function printCurrentReceipt() {
                  if (currentReceiptId) {
                        printReceipt(currentReceiptId);
                  }
            }

            // Download receipt as PDF (placeholder)
            function downloadReceipt(receiptId) {
                  alert('PDF download functionality will be implemented in the next version.');
            }

            // Export receipts
            function exportReceipts() {
                  const searchParams = new URLSearchParams(window.location.search);
                  const exportUrl = `export_receipts.php?${searchParams.toString()}`;
                  window.open(exportUrl, '_blank');
            }

            // Print selected receipts
            function printSelectedReceipts() {
                  const selectedReceipts = Array.from(document.querySelectorAll('.receipt-checkbox:checked')).map(cb => cb.value);

                  if (selectedReceipts.length === 0) {
                        alert('Please select at least one receipt to print.');
                        return;
                  }

                  // For now, just print the first selected receipt
                  // In a full implementation, you might want to combine multiple receipts
                  printReceipt(selectedReceipts[0]);
            }
      </script>
</body>

</html>