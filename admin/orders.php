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

// Handle AJAX requests for order operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
      header('Content-Type: application/json');

      switch ($_POST['action']) {
            case 'update_status':
                  $order_id = (int)$_POST['order_id'];
                  $status = $_POST['status'];

                  try {
                        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                        $stmt->execute([$status, $order_id]);
                        echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
                  } catch (Exception $e) {
                        echo json_encode(['success' => false, 'message' => 'Error updating order status: ' . $e->getMessage()]);
                  }
                  exit;

            case 'get_order_details':
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
      }
}

// Get orders with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
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

// Get order statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders
FROM orders");
$stmt->execute();
$order_stats = $stmt->fetch(PDO::FETCH_ASSOC);
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
                                                <li class="breadcrumb-item active" aria-current="page">Orders</li>
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
                                          <i class="fas fa-shopping-cart"></i>
                                          Orders Management
                                    </h1>
                                    <p class="text-muted mb-0">Manage and track all customer orders</p>
                              </div>
                        </div>

                        <!-- Order Statistics -->
                        <div class="row mb-4">
                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Total Orders</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-shopping-cart"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo $order_stats['total_orders']; ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> All time orders
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card success">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Total Revenue</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-dollar-sign"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value">$<?php echo number_format($order_stats['total_revenue'], 2); ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> Total earnings
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card info">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Completed Orders</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-check-circle"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo $order_stats['completed_orders']; ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> Successfully delivered
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card warning">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Pending Orders</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-clock"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo $order_stats['pending_orders']; ?></h2>
                                          <div class="stat-card-change neutral">
                                                <i class="fas fa-minus"></i> Awaiting processing
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Search and Filter -->
                        <div class="content-card mb-4">
                              <div class="content-card-body">
                                    <form method="GET" class="row g-3">
                                          <div class="col-md-3">
                                                <input type="text" name="search" class="form-control" placeholder="Search orders..." value="<?php echo htmlspecialchars($search); ?>">
                                          </div>
                                          <div class="col-md-2">
                                                <select name="status" class="form-select">
                                                      <option value="">All Status</option>
                                                      <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                      <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                      <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                          </div>
                                          <div class="col-md-2">
                                                <input type="date" name="date_from" class="form-control" placeholder="From Date" value="<?php echo htmlspecialchars($date_from); ?>">
                                          </div>
                                          <div class="col-md-2">
                                                <input type="date" name="date_to" class="form-control" placeholder="To Date" value="<?php echo htmlspecialchars($date_to); ?>">
                                          </div>
                                          <div class="col-md-2">
                                                <button type="submit" class="btn btn-primary w-100">
                                                      <i class="fas fa-search me-2"></i>Search
                                                </button>
                                          </div>
                                          <div class="col-md-1">
                                                <a href="orders.php" class="btn btn-outline-secondary w-100">
                                                      <i class="fas fa-times"></i>
                                                </a>
                                          </div>
                                    </form>
                              </div>
                        </div>

                        <!-- Orders Table -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h6 class="content-card-title">
                                          <i class="fas fa-list"></i>
                                          Orders List
                                    </h6>
                                    <div class="content-card-actions">
                                          <button class="btn btn-modern btn-success btn-sm" onclick="exportOrders()">
                                                <i class="fas fa-download me-1"></i>Export
                                          </button>
                                    </div>
                              </div>
                              <div class="content-card-body">
                                    <?php if (empty($orders)): ?>
                                          <div class="text-center py-5">
                                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                                <h4 class="text-muted">No orders found</h4>
                                                <p class="text-muted">Try adjusting your search criteria</p>
                                          </div>
                                    <?php else: ?>
                                          <div class="table-responsive">
                                                <table class="table table-modern">
                                                      <thead>
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
                                                                              <strong class="text-primary">#<?php echo $order['id']; ?></strong>
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
                                                                              <strong class="text-success">$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                                                        </td>
                                                                        <td>
                                                                              <span class="badge bg-secondary"><?php echo ucfirst($order['payment_method']); ?></span>
                                                                        </td>
                                                                        <td>
                                                                              <span class="badge bg-<?php
                                                                                                      echo $order['status'] === 'completed' ? 'success' : ($order['status'] === 'pending' ? 'warning' : 'danger');
                                                                                                      ?>">
                                                                                    <i class="fas fa-<?php
                                                                                                      echo $order['status'] === 'completed' ? 'check' : ($order['status'] === 'pending' ? 'clock' : 'times');
                                                                                                      ?> me-1"></i>
                                                                                    <?php echo ucfirst($order['status']); ?>
                                                                              </span>
                                                                        </td>
                                                                        <td>
                                                                              <small class="text-muted">
                                                                                    <?php echo date('M d, Y', strtotime($order['created_at'])); ?><br>
                                                                                    <?php echo date('H:i A', strtotime($order['created_at'])); ?>
                                                                              </small>
                                                                        </td>
                                                                        <td>
                                                                              <div class="btn-group btn-group-sm">
                                                                                    <button type="button" class="btn btn-outline-primary" onclick="viewOrderDetails(<?php echo $order['id']; ?>)" title="View Details">
                                                                                          <i class="fas fa-eye"></i>
                                                                                    </button>
                                                                                    <button type="button" class="btn btn-outline-success" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'completed')" title="Mark Complete">
                                                                                          <i class="fas fa-check"></i>
                                                                                    </button>
                                                                                    <button type="button" class="btn btn-outline-warning" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'pending')" title="Mark Pending">
                                                                                          <i class="fas fa-clock"></i>
                                                                                    </button>
                                                                                    <button type="button" class="btn btn-outline-danger" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'cancelled')" title="Cancel Order">
                                                                                          <i class="fas fa-times"></i>
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
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>

      <script>
            function viewOrderDetails(orderId) {
                  // Show loading
                  document.getElementById('orderDetailsContent').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Loading order details...</p></div>';

                  // Load order details via AJAX
                  fetch('orders.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                              },
                              body: `action=get_order_details&order_id=${orderId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    const order = data.order;
                                    const items = data.items;

                                    let html = `
                                    <div class="row">
                                          <div class="col-md-6">
                                                <h6>Order Information</h6>
                                                <table class="table table-sm">
                                                      <tr><td><strong>Order ID:</strong></td><td>#${order.id}</td></tr>
                                                      <tr><td><strong>Date:</strong></td><td>${new Date(order.created_at).toLocaleDateString()}</td></tr>
                                                      <tr><td><strong>Time:</strong></td><td>${new Date(order.created_at).toLocaleTimeString()}</td></tr>
                                                      <tr><td><strong>Status:</strong></td><td><span class="badge bg-${order.status === 'completed' ? 'success' : (order.status === 'pending' ? 'warning' : 'danger')}">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span></td></tr>
                                                      <tr><td><strong>Payment Method:</strong></td><td>${order.payment_method.charAt(0).toUpperCase() + order.payment_method.slice(1)}</td></tr>
                                                      ${order.payment_method === 'card' && order.card_type ? `
                                                      <tr><td><strong>Card Type:</strong></td><td>${order.card_type.charAt(0).toUpperCase() + order.card_type.slice(1)}</td></tr>
                                                      <tr><td><strong>Card Number:</strong></td><td>**** **** **** ${order.card_number ? order.card_number.slice(-4) : '****'}</td></tr>
                                                      <tr><td><strong>Card Expiry:</strong></td><td>${order.card_expiry || 'N/A'}</td></tr>
                                                      <tr><td><strong>Cardholder:</strong></td><td>${order.card_holder || 'N/A'}</td></tr>
                                                      ` : ''}
                                                </table>
                                          </div>
                                          <div class="col-md-6">
                                                <h6>Customer Information</h6>
                                                <table class="table table-sm">
                                                      <tr><td><strong>Name:</strong></td><td>${order.customer_name || 'Walk-in Customer'}</td></tr>
                                                      <tr><td><strong>Email:</strong></td><td>${order.customer_email || 'N/A'}</td></tr>
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
                                                <tbody>`;

                                    items.forEach(item => {
                                          html += `
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
                                          </tr>`;
                                    });

                                    html += `
                                                </tbody>
                                                <tfoot>
                                                      <tr class="table-active">
                                                            <th colspan="3">TOTAL</th>
                                                            <th class="text-end">$${parseFloat(order.total_amount).toFixed(2)}</th>
                                                      </tr>
                                                </tfoot>
                                          </table>
                                    </div>`;

                                    document.getElementById('orderDetailsContent').innerHTML = html;

                                    // Set up print button
                                    document.getElementById('printReceiptBtn').onclick = function() {
                                          window.open(`../user/receipt.php?order_id=${orderId}`, '_blank');
                                    };

                                    new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();
                              } else {
                                    document.getElementById('orderDetailsContent').innerHTML = '<div class="alert alert-danger">Error loading order details: ' + data.message + '</div>';
                                    new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();
                              }
                        })
                        .catch(error => {
                              document.getElementById('orderDetailsContent').innerHTML = '<div class="alert alert-danger">Error loading order details</div>';
                              new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();
                        });
            }

            function updateOrderStatus(orderId, status) {
                  if (!confirm(`Are you sure you want to mark this order as ${status}?`)) {
                        return;
                  }

                  fetch('orders.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                              },
                              body: `action=update_status&order_id=${orderId}&status=${status}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    showNotification('Order status updated successfully', 'success');
                                    setTimeout(() => {
                                          location.reload();
                                    }, 1000);
                              } else {
                                    showNotification('Error updating order status: ' + data.message, 'error');
                              }
                        })
                        .catch(error => {
                              showNotification('Error updating order status', 'error');
                        });
            }

            function exportOrders() {
                  // Create export URL with current filters
                  const urlParams = new URLSearchParams(window.location.search);
                  const exportUrl = `export_report.php?type=orders&${urlParams.toString()}`;
                  window.open(exportUrl, '_blank');
            }

            function showNotification(message, type) {
                  // Create notification element
                  const notification = document.createElement('div');
                  notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
                  notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                  notification.innerHTML = `
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                        ${message}
                        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
                  `;

                  document.body.appendChild(notification);

                  // Auto remove after 3 seconds
                  setTimeout(() => {
                        if (notification.parentElement) {
                              notification.remove();
                        }
                  }, 3000);
            }
      </script>
</body>

</html>