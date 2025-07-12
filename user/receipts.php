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

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = [];
$params = [];

// Access control - staff can see all orders, customers only their own
$user = getCurrentUser();
$is_staff = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager', 'cashier']);

if ($is_staff) {
      // Staff can view all orders
      $where_conditions[] = "1=1";
} else {
      // Customers can only view their own orders
      $where_conditions[] = "(user_id = ? OR (user_id IS NULL AND customer_email = ?))";
      $params[] = $user['id'];
      $params[] = $user['email'];
}

if (!empty($search)) {
      $where_conditions[] = "(customer_name LIKE ? OR customer_email LIKE ? OR id LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      $params[] = "%$search%";
}

if (!empty($status)) {
      $where_conditions[] = "status = ?";
      $params[] = $status;
}

if (!empty($date_from)) {
      $where_conditions[] = "DATE(created_at) >= ?";
      $params[] = $date_from;
}

if (!empty($date_to)) {
      $where_conditions[] = "DATE(created_at) <= ?";
      $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $where_clause");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Get orders
$stmt = $pdo->prepare("
    SELECT * FROM orders 
    WHERE $where_clause 
    ORDER BY created_at DESC 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order items for each order
foreach ($orders as &$order) {
      $stmt = $pdo->prepare("
          SELECT oi.*, p.name AS product_name, p.product_code
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          WHERE oi.order_id = ?
      ");
      $stmt->execute([$order['id']]);
      $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Receipts - POS System</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="../admin/assets/css/admin.css">
</head>

<body>
      <div class="admin-layout">
            <?php include 'side.php'; ?>
            <div class="admin-main">
                  <nav class="admin-topbar">
                        <div class="topbar-left">
                              <button class="btn btn-link sidebar-toggle-btn" id="sidebarToggleBtn">
                                    <i class="fas fa-bars"></i>
                              </button>
                              <div class="breadcrumb-container">
                                    <nav aria-label="breadcrumb">
                                          <ol class="breadcrumb">
                                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                                <li class="breadcrumb-item active" aria-current="page">Receipts</li>
                                          </ol>
                                    </nav>
                              </div>
                        </div>
                  </nav>
                  <div class="admin-content">
                        <div class="container-fluid mt-4">
                              <div class="row">
                                    <div class="col-12">
                                          <div class="card">
                                                <div class="card-header">
                                                      <div class="d-flex justify-content-between align-items-center">
                                                            <h5 class="mb-0">
                                                                  <i class="fas fa-receipt me-2"></i>Receipts
                                                            </h5>
                                                            <div class="btn-group">
                                                                  <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportReceipts()">
                                                                        <i class="fas fa-download me-1"></i>Export
                                                                  </button>
                                                                  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printSelected()">
                                                                        <i class="fas fa-print me-1"></i>Print Selected
                                                                  </button>
                                                            </div>
                                                      </div>
                                                </div>

                                                <div class="card-body">
                                                      <!-- Search and Filter Form -->
                                                      <form method="GET" class="row g-3 mb-4">
                                                            <div class="col-md-3">
                                                                  <label for="search" class="form-label">Search</label>
                                                                  <input type="text" class="form-control" id="search" name="search"
                                                                        value="<?php echo htmlspecialchars($search); ?>"
                                                                        placeholder="Customer name, email, or order ID">
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
                                                                  <label for="date_from" class="form-label">From Date</label>
                                                                  <input type="date" class="form-control" id="date_from" name="date_from"
                                                                        value="<?php echo htmlspecialchars($date_from); ?>">
                                                            </div>
                                                            <div class="col-md-2">
                                                                  <label for="date_to" class="form-label">To Date</label>
                                                                  <input type="date" class="form-control" id="date_to" name="date_to"
                                                                        value="<?php echo htmlspecialchars($date_to); ?>">
                                                            </div>
                                                            <div class="col-md-3 d-flex align-items-end">
                                                                  <button type="submit" class="btn btn-primary me-2">
                                                                        <i class="fas fa-search me-1"></i>Search
                                                                  </button>
                                                                  <a href="receipts.php" class="btn btn-outline-secondary">
                                                                        <i class="fas fa-times me-1"></i>Clear
                                                                  </a>
                                                            </div>
                                                      </form>

                                                      <!-- Results Summary -->
                                                      <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <div>
                                                                  <span class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> receipts</span>
                                                            </div>
                                                            <div class="form-check">
                                                                  <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                                                  <label class="form-check-label" for="selectAll">
                                                                        Select All
                                                                  </label>
                                                            </div>
                                                      </div>

                                                      <!-- Receipts Table -->
                                                      <?php if (empty($orders)): ?>
                                                            <div class="text-center py-5">
                                                                  <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                                                  <h5 class="text-muted">No receipts found</h5>
                                                                  <p class="text-muted">Try adjusting your search criteria</p>
                                                            </div>
                                                      <?php else: ?>
                                                            <div class="table-responsive">
                                                                  <table class="table table-hover">
                                                                        <thead class="table-light">
                                                                              <tr>
                                                                                    <th width="50">
                                                                                          <input type="checkbox" class="form-check-input" id="selectAllHeader" onchange="toggleSelectAll()">
                                                                                    </th>
                                                                                    <th>Order ID</th>
                                                                                    <th>Customer</th>
                                                                                    <th>Date & Time</th>
                                                                                    <th>Total Amount</th>
                                                                                    <th>Payment Method</th>
                                                                                    <th>Change</th>
                                                                                    <th>Status</th>
                                                                                    <th>Items</th>
                                                                                    <th width="150">Actions</th>
                                                                              </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                              <?php foreach ($orders as $order): ?>
                                                                                    <tr>
                                                                                          <td>
                                                                                                <input type="checkbox" class="form-check-input receipt-checkbox" value="<?php echo $order['id']; ?>">
                                                                                          </td>
                                                                                          <td>
                                                                                                <strong>#<?php echo $order['id']; ?></strong>
                                                                                          </td>
                                                                                          <td>
                                                                                                <div>
                                                                                                      <strong><?php echo htmlspecialchars($order['customer_name'] ?: 'Walk-in Customer'); ?></strong>
                                                                                                      <?php if ($order['customer_email']): ?>
                                                                                                            <br><small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                                                                                      <?php endif; ?>
                                                                                                </div>
                                                                                          </td>
                                                                                          <td>
                                                                                                <div>
                                                                                                      <strong><?php echo date('M d, Y', strtotime($order['created_at'])); ?></strong>
                                                                                                      <br><small class="text-muted"><?php echo date('H:i A', strtotime($order['created_at'])); ?></small>
                                                                                                </div>
                                                                                          </td>
                                                                                          <td>
                                                                                                <strong class="text-primary">$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                                                                          </td>
                                                                                          <td>
                                                                                                <span class="badge bg-info"><?php echo ucfirst($order['payment_method']); ?></span>
                                                                                          </td>
                                                                                          <td>
                                                                                                <?php if ($order['payment_method'] === 'cash' && $order['change_amount'] > 0): ?>
                                                                                                      <span class="badge bg-success">$<?php echo number_format($order['change_amount'], 2); ?></span>
                                                                                                <?php else: ?>
                                                                                                      <span class="text-muted">-</span>
                                                                                                <?php endif; ?>
                                                                                          </td>
                                                                                          <td>
                                                                                                <span class="badge bg-<?php echo $order['status'] === 'completed' ? 'success' : ($order['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                                                                      <?php echo ucfirst($order['status']); ?>
                                                                                                </span>
                                                                                          </td>
                                                                                          <td>
                                                                                                <small class="text-muted">
                                                                                                      <?php echo count($order['items']); ?> item<?php echo count($order['items']) !== 1 ? 's' : ''; ?>
                                                                                                </small>
                                                                                          </td>
                                                                                          <td>
                                                                                                <div class="btn-group btn-group-sm">
                                                                                                      <a href="receipt.php?order_id=<?php echo $order['id']; ?>"
                                                                                                            class="btn btn-outline-primary" title="View Receipt" target="_blank">
                                                                                                            <i class="fas fa-eye"></i>
                                                                                                      </a>
                                                                                                      <a href="receipt.php?order_id=<?php echo $order['id']; ?>"
                                                                                                            class="btn btn-outline-success" title="Print Receipt" target="_blank">
                                                                                                            <i class="fas fa-print"></i>
                                                                                                      </a>
                                                                                                      <button type="button" class="btn btn-outline-info"
                                                                                                            onclick="viewReceiptDetails(<?php echo $order['id']; ?>)" title="Quick View">
                                                                                                            <i class="fas fa-info-circle"></i>
                                                                                                      </button>
                                                                                                </div>
                                                                                          </td>
                                                                                    </tr>
                                                                              <?php endforeach; ?>
                                                                        </tbody>
                                                                  </table>
                                                            </div>

                                                            <!-- Pagination -->
                                                            <?php if ($total_pages > 1): ?>
                                                                  <nav aria-label="Receipts pagination">
                                                                        <ul class="pagination justify-content-center">
                                                                              <?php if ($page > 1): ?>
                                                                                    <li class="page-item">
                                                                                          <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">Previous</a>
                                                                                    </li>
                                                                              <?php endif; ?>

                                                                              <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                                                          <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"><?php echo $i; ?></a>
                                                                                    </li>
                                                                              <?php endfor; ?>

                                                                              <?php if ($page < $total_pages): ?>
                                                                                    <li class="page-item">
                                                                                          <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">Next</a>
                                                                                    </li>
                                                                              <?php endif; ?>
                                                                        </ul>
                                                                  </nav>
                                                            <?php endif; ?>
                                                      <?php endif; ?>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Receipt Details Modal -->
      <div class="modal fade" id="receiptDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Receipt Details</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="receiptDetailsContent">
                              <!-- Content will be loaded here -->
                        </div>
                        <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                              <button type="button" class="btn btn-primary" id="printReceiptBtn">
                                    <i class="fas fa-print me-2"></i>Print Receipt
                              </button>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

      <script>
            function toggleSelectAll() {
                  const selectAllCheckbox = document.getElementById('selectAll');
                  const selectAllHeaderCheckbox = document.getElementById('selectAllHeader');
                  const receiptCheckboxes = document.querySelectorAll('.receipt-checkbox');

                  const isChecked = selectAllCheckbox.checked || selectAllHeaderCheckbox.checked;

                  receiptCheckboxes.forEach(checkbox => {
                        checkbox.checked = isChecked;
                  });

                  selectAllCheckbox.checked = isChecked;
                  selectAllHeaderCheckbox.checked = isChecked;
            }

            function viewReceiptDetails(orderId) {
                  // Load receipt details via AJAX
                  fetch(`receipt.php?order_id=${orderId}&ajax=1`)
                        .then(response => response.text())
                        .then(html => {
                              document.getElementById('receiptDetailsContent').innerHTML = html;
                              new bootstrap.Modal(document.getElementById('receiptDetailsModal')).show();

                              // Set up print button
                              document.getElementById('printReceiptBtn').onclick = function() {
                                    window.open(`receipt.php?order_id=${orderId}`, '_blank');
                              };
                        });
            }

            function printSelected() {
                  const selectedCheckboxes = document.querySelectorAll('.receipt-checkbox:checked');
                  if (selectedCheckboxes.length === 0) {
                        alert('Please select at least one receipt to print');
                        return;
                  }

                  const orderIds = Array.from(selectedCheckboxes).map(cb => cb.value);

                  // Open each receipt in a new tab
                  orderIds.forEach(orderId => {
                        window.open(`receipt.php?order_id=${orderId}`, '_blank');
                  });
            }

            function exportReceipts() {
                  // Get current search parameters
                  const urlParams = new URLSearchParams(window.location.search);
                  const exportUrl = `export_receipts.php?${urlParams.toString()}`;

                  // Create a temporary link and click it
                  const link = document.createElement('a');
                  link.href = exportUrl;
                  link.download = 'receipts_export.csv';
                  document.body.appendChild(link);
                  link.click();
                  document.body.removeChild(link);
            }

            // Auto-submit form when date inputs change
            document.getElementById('date_from').addEventListener('change', function() {
                  if (this.value && document.getElementById('date_to').value) {
                        this.form.submit();
                  }
            });

            document.getElementById('date_to').addEventListener('change', function() {
                  if (this.value && document.getElementById('date_from').value) {
                        this.form.submit();
                  }
            });

            // Auto-submit form when status changes
            document.getElementById('status').addEventListener('change', function() {
                  this.form.submit();
            });
      </script>
</body>

</html>