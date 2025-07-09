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

// Get current user
$user = getCurrentUser();

// Get orders with pagination - only show customer's own orders
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

$where_conditions = ["(o.user_id = ? OR (o.user_id IS NULL AND o.customer_email = ?))"];
$params = [$user['id'], $user['email']];

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

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) FROM orders o $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_orders = $stmt->fetchColumn();
$total_pages = ceil($total_orders / $limit);

// Get orders
$sql = "SELECT o.*, COUNT(oi.id) as item_count 
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        $where_clause 
        GROUP BY o.id 
        ORDER BY o.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Orders - POS System</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="../admin/assets/css/admin.css">
</head>

<body>
      <!-- Navigation -->
      <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                  <a class="navbar-brand" href="index.php">
                        <i class="fas fa-store me-2"></i>POS System
                  </a>

                  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                        <span class="navbar-toggler-icon"></span>
                  </button>

                  <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav me-auto">
                              <li class="nav-item">
                                    <a class="nav-link" href="index.php">
                                          <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="pos.php">
                                          <i class="fas fa-cash-register me-1"></i>POS
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="products.php">
                                          <i class="fas fa-box me-1"></i>Products
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link active" href="orders.php">
                                          <i class="fas fa-shopping-cart me-1"></i>Orders
                                    </a>
                              </li>
                              <?php if (isManager()): ?>
                                    <li class="nav-item">
                                          <a class="nav-link" href="reports.php">
                                                <i class="fas fa-chart-bar me-1"></i>Reports
                                          </a>
                                    </li>
                              <?php endif; ?>
                        </ul>

                        <ul class="navbar-nav">
                              <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                          <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['username']; ?>
                                          <span class="badge bg-light text-dark ms-1"><?php echo ucfirst($_SESSION['role']); ?></span>
                                    </a>
                                    <ul class="dropdown-menu">
                                          <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                          <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                          <li>
                                                <hr class="dropdown-divider">
                                          </li>
                                          <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                    </ul>
                              </li>
                        </ul>
                  </div>
            </div>
      </nav>

      <!-- Main Content -->
      <div class="container-fluid mt-4">
            <div class="row">
                  <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                              <h2><i class="fas fa-shopping-cart me-2"></i>Orders</h2>
                              <a href="pos.php" class="btn btn-primary">
                                    <i class="fas fa-cash-register me-2"></i>New Order
                              </a>
                        </div>

                        <!-- Search and Filter -->
                        <div class="card mb-4">
                              <div class="card-body">
                                    <form method="GET" class="row g-3">
                                          <div class="col-md-4">
                                                <input type="text" name="search" class="form-control" placeholder="Search orders..." value="<?php echo htmlspecialchars($search); ?>">
                                          </div>
                                          <div class="col-md-3">
                                                <select name="status" class="form-select">
                                                      <option value="">All Status</option>
                                                      <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                      <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                      <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                          </div>
                                          <div class="col-md-2">
                                                <button type="submit" class="btn btn-primary w-100">
                                                      <i class="fas fa-search me-2"></i>Search
                                                </button>
                                          </div>
                                          <div class="col-md-2">
                                                <a href="orders.php" class="btn btn-outline-secondary w-100">
                                                      <i class="fas fa-times me-2"></i>Clear
                                                </a>
                                          </div>
                                    </form>
                              </div>
                        </div>

                        <!-- Orders Table -->
                        <div class="card">
                              <div class="card-body">
                                    <?php if (empty($orders)): ?>
                                          <div class="text-center py-5">
                                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                                <h4 class="text-muted">No orders found</h4>
                                                <p class="text-muted">Try adjusting your search criteria</p>
                                          </div>
                                    <?php else: ?>
                                          <div class="table-responsive">
                                                <table class="table table-hover">
                                                      <thead class="table-light">
                                                            <tr>
                                                                  <th>Order ID</th>
                                                                  <th>Customer</th>
                                                                  <th>Items</th>
                                                                  <th>Total</th>
                                                                  <th>Payment</th>
                                                                  <th>Status</th>
                                                                  <th>Date</th>
                                                                  <th>Actions</th>
                                                            </tr>
                                                      </thead>
                                                      <tbody>
                                                            <?php foreach ($orders as $order): ?>
                                                                  <tr>
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
                                                                              <span class="badge bg-info"><?php echo $order['item_count']; ?> items</span>
                                                                        </td>
                                                                        <td>
                                                                              <strong class="text-primary">$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                                                        </td>
                                                                        <td>
                                                                              <span class="badge bg-secondary"><?php echo ucfirst($order['payment_method']); ?></span>
                                                                        </td>
                                                                        <td>
                                                                              <span class="badge bg-<?php
                                                                                                      echo $order['status'] === 'completed' ? 'success' : ($order['status'] === 'pending' ? 'warning' : 'danger');
                                                                                                      ?>">
                                                                                    <?php echo ucfirst($order['status']); ?>
                                                                              </span>
                                                                        </td>
                                                                        <td>
                                                                              <small>
                                                                                    <?php echo date('M d, Y', strtotime($order['created_at'])); ?><br>
                                                                                    <?php echo date('H:i A', strtotime($order['created_at'])); ?>
                                                                              </small>
                                                                        </td>
                                                                        <td>
                                                                              <div class="btn-group btn-group-sm">
                                                                                    <a href="receipt.php?order_id=<?php echo $order['id']; ?>" class="btn btn-outline-primary" title="View Receipt">
                                                                                          <i class="fas fa-print"></i>
                                                                                    </a>
                                                                                    <button type="button" class="btn btn-outline-info" onclick="viewOrderDetails(<?php echo $order['id']; ?>)" title="View Details">
                                                                                          <i class="fas fa-eye"></i>
                                                                                    </button>
                                                                              </div>
                                                                        </td>
                                                                  </tr>
                                                            <?php endforeach; ?>
                                                      </tbody>
                                                </table>
                                          </div>
                                    <?php endif; ?>
                              </div>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                              <nav aria-label="Orders pagination" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                          <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                      <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>">Previous</a>
                                                </li>
                                          <?php endif; ?>

                                          <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                      <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>"><?php echo $i; ?></a>
                                                </li>
                                          <?php endfor; ?>

                                          <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                      <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>">Next</a>
                                                </li>
                                          <?php endif; ?>
                                    </ul>
                              </nav>
                        <?php endif; ?>
                  </div>
            </div>
      </div>

      <!-- Order Details Modal -->
      <div class="modal fade" id="orderDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Order Details</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="orderDetailsContent">
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
            function viewOrderDetails(orderId) {
                  // Load order details via AJAX
                  fetch(`receipt.php?order_id=${orderId}&ajax=1`)
                        .then(response => response.text())
                        .then(html => {
                              document.getElementById('orderDetailsContent').innerHTML = html;
                              new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();

                              // Set up print button
                              document.getElementById('printReceiptBtn').onclick = function() {
                                    window.open(`receipt.php?order_id=${orderId}`, '_blank');
                              };
                        });
            }