<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
      header('Location: login.php');
      exit();
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get the section type from URL parameter
$section_type = $_GET['type'] ?? 'dashboard';
$valid_sections = ['dashboard', 'orders', 'invoices', 'price-check', 'profile'];

// Validate the section type
if (!in_array($section_type, $valid_sections)) {
      $section_type = 'dashboard';
}

// Handle password change
if ($_POST['action'] ?? '' === 'change_password') {
      $current_password = $_POST['current_password'] ?? '';
      $new_password = $_POST['new_password'] ?? '';
      $confirm_password = $_POST['confirm_password'] ?? '';

      if ($new_password === $confirm_password) {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (password_verify($current_password, $user['password'])) {
                  $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                  $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                  $stmt->execute([$hashed_password, $user_id]);
                  $password_success = "Password changed successfully!";
            } else {
                  $password_error = "Current password is incorrect!";
            }
      } else {
            $password_error = "New passwords do not match!";
      }
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get user orders
$stmt = $pdo->prepare("
    SELECT o.*, COUNT(oi.id) as item_count 
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.user_id = ? 
    GROUP BY o.id 
    ORDER BY o.created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

// Get total spent
$stmt = $pdo->prepare("SELECT SUM(total_amount) as total_spent FROM orders WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$total_spent = $stmt->fetch()['total_spent'] ?? 0;

// Get order count
$stmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?");
$stmt->execute([$user_id]);
$order_count = $stmt->fetch()['order_count'] ?? 0;

// Get recent products for price checking
$stmt = $pdo->prepare("SELECT * FROM products WHERE stock_quantity > 0 ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$recent_products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Customer Dashboard - CH-FASHION</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
      <link href="assets/css/user.css?v=<?php echo time(); ?>" rel="stylesheet">

</head>

<body>
      <!-- Mobile Sidebar Overlay -->
      <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

      <div class="user-layout">
            <!-- Sidebar -->
            <div class="user-sidebar" id="sidebar">
                  <div class="sidebar-header">
                        <a href="/" class="sidebar-brand">
                              <div class="brand-icon">
                                    <i class="fas fa-store"></i>
                              </div>
                              <span class="brand-text">CH-FASHION</span>
                        </a>
                        <button class="btn btn-link sidebar-toggle" onclick="toggleSidebar()">
                              <i class="fas fa-bars"></i>
                        </button>
                  </div>

                  <div class="sidebar-content">
                        <div class="user-profile">
                              <div class="user-avatar">
                                    <i class="fas fa-user"></i>
                              </div>
                              <div class="user-info">
                                    <h6 class="user-name"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h6>
                                    <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                              </div>
                        </div>

                        <nav class="sidebar-nav">
                              <a href="user_dashboard.php?type=dashboard" class="nav-link <?php echo $section_type === 'dashboard' ? 'active' : ''; ?>" data-section="dashboard">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <span>Dashboard</span>
                              </a>
                              <a href="user_dashboard.php?type=orders" class="nav-link <?php echo $section_type === 'orders' ? 'active' : ''; ?>" data-section="orders">
                                    <i class="fas fa-shopping-bag"></i>
                                    <span>My Orders</span>
                              </a>
                              <a href="user_dashboard.php?type=invoices" class="nav-link <?php echo $section_type === 'invoices' ? 'active' : ''; ?>" data-section="invoices">
                                    <i class="fas fa-file-invoice"></i>
                                    <span>Invoices</span>
                              </a>
                              <a href="user_dashboard.php?type=price-check" class="nav-link <?php echo $section_type === 'price-check' ? 'active' : ''; ?>" data-section="price-check">
                                    <i class="fas fa-search-dollar"></i>
                                    <span>Price Check</span>
                              </a>
                              <a href="user_dashboard.php?type=profile" class="nav-link <?php echo $section_type === 'profile' ? 'active' : ''; ?>" data-section="profile">
                                    <i class="fas fa-user-cog"></i>
                                    <span>Profile</span>
                              </a>
                              <a href="logout.php" class="nav-link">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Logout</span>
                              </a>
                        </nav>
                  </div>
            </div>

            <!-- Main Content -->
            <div class="user-main" id="main">
                  <div class="container-fluid p-4">
                        <!-- Dashboard Section -->
                        <div id="dashboard" class="content-section <?php echo $section_type === 'dashboard' ? 'active' : ''; ?>">
                              <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div>
                                          <h2 class="mb-0">Welcome back, <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>!</h2>
                                          <nav aria-label="breadcrumb">
                                                <ol class="breadcrumb mb-0">
                                                      <li class="breadcrumb-item"><a href="user_dashboard.php?type=dashboard">Dashboard</a></li>
                                                      <li class="breadcrumb-item active" aria-current="page">Overview</li>
                                                </ol>
                                          </nav>
                                    </div>
                                    <div class="current-time" id="currentTime"></div>
                              </div>

                              <!-- Stats Cards -->
                              <div class="row mb-4">
                                    <div class="col-md-4 mb-3">
                                          <div class="stat-card">
                                                <i class="fas fa-shopping-bag fa-2x mb-2"></i>
                                                <h3><?php echo $order_count; ?></h3>
                                                <p class="mb-0">Total Orders</p>
                                          </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                          <div class="stat-card">
                                                <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                                                <h3>$<?php echo number_format($total_spent, 2); ?></h3>
                                                <p class="mb-0">Total Spent</p>
                                          </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                          <div class="stat-card">
                                                <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                                <h3><?php echo date('M Y'); ?></h3>
                                                <p class="mb-0">Current Month</p>
                                          </div>
                                    </div>
                              </div>

                              <!-- Recent Orders -->
                              <div class="dashboard-card p-4">
                                    <h4 class="mb-3">Recent Orders</h4>
                                    <?php if (empty($orders)): ?>
                                          <div class="text-center py-4">
                                                <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No orders yet. Start shopping!</p>
                                                <a href="/" class="btn btn-primary">Browse Products</a>
                                          </div>
                                    <?php else: ?>
                                          <div class="table-responsive">
                                                <table class="table table-hover">
                                                      <thead>
                                                            <tr>
                                                                  <th>Order ID</th>
                                                                  <th>Date</th>
                                                                  <th>Amount</th>
                                                                  <th>Status</th>
                                                                  <th>Items</th>
                                                                  <th>Action</th>
                                                            </tr>
                                                      </thead>
                                                      <tbody>
                                                            <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                                                                  <tr>
                                                                        <td>#<?php echo $order['id']; ?></td>
                                                                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                                                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                                                        <td>
                                                                              <span class="order-status status-<?php echo $order['status']; ?>">
                                                                                    <?php echo ucfirst($order['status']); ?>
                                                                              </span>
                                                                        </td>
                                                                        <td><?php echo $order['item_count']; ?> items</td>
                                                                        <td>
                                                                              <button class="btn btn-sm btn-outline-primary" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                                                                    View Details
                                                                              </button>
                                                                        </td>
                                                                  </tr>
                                                            <?php endforeach; ?>
                                                      </tbody>
                                                </table>
                                          </div>
                                    <?php endif; ?>
                              </div>
                        </div>

                        <!-- Orders Section -->
                        <div id="orders" class="content-section <?php echo $section_type === 'orders' ? 'active' : ''; ?>">
                              <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div>
                                          <h2 class="mb-0">My Orders</h2>
                                          <nav aria-label="breadcrumb">
                                                <ol class="breadcrumb mb-0">
                                                      <li class="breadcrumb-item"><a href="user_dashboard.php?type=dashboard">Dashboard</a></li>
                                                      <li class="breadcrumb-item active" aria-current="page">Orders</li>
                                                </ol>
                                          </nav>
                                    </div>
                                    <a href="/" class="btn btn-primary">
                                          <i class="fas fa-plus me-2"></i>New Order
                                    </a>
                              </div>

                              <div class="dashboard-card p-4">
                                    <?php if (empty($orders)): ?>
                                          <div class="text-center py-5">
                                                <i class="fas fa-shopping-bag fa-4x text-muted mb-3"></i>
                                                <h4 class="text-muted">No orders found</h4>
                                                <p class="text-muted">You haven't placed any orders yet.</p>
                                                <a href="/" class="btn btn-primary btn-lg">Start Shopping</a>
                                          </div>
                                    <?php else: ?>
                                          <div class="table-responsive">
                                                <table class="table table-hover">
                                                      <thead>
                                                            <tr>
                                                                  <th>Order ID</th>
                                                                  <th>Date</th>
                                                                  <th>Amount</th>
                                                                  <th>Status</th>
                                                                  <th>Payment Method</th>
                                                                  <th>Items</th>
                                                                  <th>Actions</th>
                                                            </tr>
                                                      </thead>
                                                      <tbody>
                                                            <?php foreach ($orders as $order): ?>
                                                                  <tr>
                                                                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                                                                        <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                                                        <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                                                        <td>
                                                                              <span class="order-status status-<?php echo $order['status']; ?>">
                                                                                    <?php echo ucfirst($order['status']); ?>
                                                                              </span>
                                                                        </td>
                                                                        <td><?php echo ucfirst($order['payment_method'] ?? 'N/A'); ?></td>
                                                                        <td><?php echo $order['item_count']; ?> items</td>
                                                                        <td>
                                                                              <button class="btn btn-sm btn-outline-primary me-2" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                                                                    <i class="fas fa-eye me-1"></i>View
                                                                              </button>
                                                                              <button class="btn btn-sm btn-outline-success" onclick="downloadInvoice(<?php echo $order['id']; ?>)">
                                                                                    <i class="fas fa-download me-1"></i>Invoice
                                                                              </button>
                                                                        </td>
                                                                  </tr>
                                                            <?php endforeach; ?>
                                                      </tbody>
                                                </table>
                                          </div>
                                    <?php endif; ?>
                              </div>
                        </div>

                        <!-- Invoices Section -->
                        <div id="invoices" class="content-section <?php echo $section_type === 'invoices' ? 'active' : ''; ?>">
                              <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div>
                                          <h2 class="mb-0">Invoices</h2>
                                          <nav aria-label="breadcrumb">
                                                <ol class="breadcrumb mb-0">
                                                      <li class="breadcrumb-item"><a href="user_dashboard.php?type=dashboard">Dashboard</a></li>
                                                      <li class="breadcrumb-item active" aria-current="page">Invoices</li>
                                                </ol>
                                          </nav>
                                    </div>
                                    <button class="btn btn-primary" onclick="downloadAllInvoices()">
                                          <i class="fas fa-download me-2"></i>Download All
                                    </button>
                              </div>

                              <div class="dashboard-card p-4">
                                    <?php if (empty($orders)): ?>
                                          <div class="text-center py-5">
                                                <i class="fas fa-file-invoice fa-4x text-muted mb-3"></i>
                                                <h4 class="text-muted">No invoices available</h4>
                                                <p class="text-muted">Invoices will appear here after you place orders.</p>
                                          </div>
                                    <?php else: ?>
                                          <div class="row">
                                                <?php foreach ($orders as $order): ?>
                                                      <div class="col-md-6 col-lg-4 mb-3">
                                                            <div class="card h-100">
                                                                  <div class="card-body">
                                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                                              <h6 class="card-title mb-0">Invoice #<?php echo $order['id']; ?></h6>
                                                                              <span class="order-status status-<?php echo $order['status']; ?>">
                                                                                    <?php echo ucfirst($order['status']); ?>
                                                                              </span>
                                                                        </div>
                                                                        <p class="card-text text-muted">
                                                                              <small>
                                                                                    <i class="fas fa-calendar me-1"></i>
                                                                                    <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                                                              </small>
                                                                        </p>
                                                                        <h5 class="text-primary mb-3">$<?php echo number_format($order['total_amount'], 2); ?></h5>
                                                                        <div class="d-grid gap-2">
                                                                              <button class="btn btn-outline-primary btn-sm" onclick="viewInvoice(<?php echo $order['id']; ?>)">
                                                                                    <i class="fas fa-eye me-1"></i>View Invoice
                                                                              </button>
                                                                              <button class="btn btn-outline-success btn-sm" onclick="downloadInvoice(<?php echo $order['id']; ?>)">
                                                                                    <i class="fas fa-download me-1"></i>Download PDF
                                                                              </button>
                                                                        </div>
                                                                  </div>
                                                            </div>
                                                      </div>
                                                <?php endforeach; ?>
                                          </div>
                                    <?php endif; ?>
                              </div>
                        </div>

                        <!-- Price Check Section -->
                        <div id="price-check" class="content-section <?php echo $section_type === 'price-check' ? 'active' : ''; ?>">
                              <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div>
                                          <h2 class="mb-0">Price Check</h2>
                                          <nav aria-label="breadcrumb">
                                                <ol class="breadcrumb mb-0">
                                                      <li class="breadcrumb-item"><a href="user_dashboard.php?type=dashboard">Dashboard</a></li>
                                                      <li class="breadcrumb-item active" aria-current="page">Price Check</li>
                                                </ol>
                                          </nav>
                                    </div>
                                    <button class="btn btn-primary" onclick="refreshPrices()">
                                          <i class="fas fa-sync-alt me-2"></i>Refresh Prices
                                    </button>
                              </div>

                              <div class="dashboard-card p-4">
                                    <div class="row mb-4">
                                          <div class="col-md-6">
                                                <div class="input-group">
                                                      <input type="text" class="form-control" id="searchProduct" placeholder="Search products by name or code...">
                                                      <button class="btn btn-outline-primary" type="button" onclick="searchProducts()">
                                                            <i class="fas fa-search"></i>
                                                      </button>
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <select class="form-select" id="categoryFilter">
                                                      <option value="">All Categories</option>
                                                      <option value="clothing">Clothing</option>
                                                      <option value="accessories">Accessories</option>
                                                      <option value="shoes">Shoes</option>
                                                      <option value="electronics">Electronics</option>
                                                </select>
                                          </div>
                                    </div>

                                    <div id="priceResults">
                                          <div class="row">
                                                <?php foreach ($recent_products as $product): ?>
                                                      <div class="col-md-6 col-lg-4 mb-3">
                                                            <div class="price-check-card">
                                                                  <div class="d-flex align-items-center">
                                                                        <div class="flex-shrink-0">
                                                                              <?php if ($product['image_path']): ?>
                                                                                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>"
                                                                                          alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                                                          class="rounded" style="width: 60px; height: 60px; object-fit: cover;">
                                                                              <?php else: ?>
                                                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                                                                          style="width: 60px; height: 60px;">
                                                                                          <i class="fas fa-image text-muted"></i>
                                                                                    </div>
                                                                              <?php endif; ?>
                                                                        </div>
                                                                        <div class="flex-grow-1 ms-3">
                                                                              <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                                              <p class="mb-1 small">Code: <?php echo htmlspecialchars($product['product_code']); ?></p>
                                                                              <div class="d-flex justify-content-between align-items-center">
                                                                                    <span class="fw-bold">$<?php echo number_format($product['price'], 2); ?></span>
                                                                                    <span class="badge bg-success">In Stock: <?php echo $product['stock_quantity']; ?></span>
                                                                              </div>
                                                                              <?php if ($product['discount_price']): ?>
                                                                                    <small class="text-warning">
                                                                                          <i class="fas fa-tag me-1"></i>Discounted: $<?php echo number_format($product['discount_price'], 2); ?>
                                                                                    </small>
                                                                              <?php endif; ?>
                                                                        </div>
                                                                  </div>
                                                            </div>
                                                      </div>
                                                <?php endforeach; ?>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Profile Section -->
                        <div id="profile" class="content-section <?php echo $section_type === 'profile' ? 'active' : ''; ?>">
                              <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div>
                                          <h2 class="mb-0">Profile Settings</h2>
                                          <nav aria-label="breadcrumb">
                                                <ol class="breadcrumb mb-0">
                                                      <li class="breadcrumb-item"><a href="user_dashboard.php?type=dashboard">Dashboard</a></li>
                                                      <li class="breadcrumb-item active" aria-current="page">Profile</li>
                                                </ol>
                                          </nav>
                                    </div>
                                    <button class="btn btn-primary" onclick="editProfile()">
                                          <i class="fas fa-edit me-2"></i>Edit Profile
                                    </button>
                              </div>

                              <div class="row">
                                    <div class="col-md-6 mb-4">
                                          <div class="dashboard-card p-4">
                                                <h4 class="mb-3">Personal Information</h4>
                                                <div class="mb-3">
                                                      <label class="form-label">Full Name</label>
                                                      <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                      <label class="form-label">Email</label>
                                                      <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                      <label class="form-label">Phone</label>
                                                      <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                      <label class="form-label">Member Since</label>
                                                      <input type="text" class="form-control" value="<?php echo date('M d, Y', strtotime($user['created_at'])); ?>" readonly>
                                                </div>
                                          </div>
                                    </div>

                                    <div class="col-md-6 mb-4">
                                          <div class="dashboard-card p-4">
                                                <h4 class="mb-3">Change Password</h4>
                                                <?php if (isset($password_success)): ?>
                                                      <div class="alert alert-success"><?php echo $password_success; ?></div>
                                                <?php endif; ?>
                                                <?php if (isset($password_error)): ?>
                                                      <div class="alert alert-danger"><?php echo $password_error; ?></div>
                                                <?php endif; ?>

                                                <form method="POST">
                                                      <input type="hidden" name="action" value="change_password">
                                                      <div class="mb-3">
                                                            <label class="form-label">Current Password</label>
                                                            <div class="input-group">
                                                                  <input type="password" class="form-control" name="current_password" id="current_password" required>
                                                                  <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                                                        <i class="fas fa-eye" id="current_password_icon"></i>
                                                                  </button>
                                                            </div>
                                                      </div>
                                                      <div class="mb-3">
                                                            <label class="form-label">New Password</label>
                                                            <div class="input-group">
                                                                  <input type="password" class="form-control" name="new_password" id="new_password" required>
                                                                  <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                                                        <i class="fas fa-eye" id="new_password_icon"></i>
                                                                  </button>
                                                            </div>
                                                      </div>
                                                      <div class="mb-3">
                                                            <label class="form-label">Confirm New Password</label>
                                                            <div class="input-group">
                                                                  <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                                                                  <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                                        <i class="fas fa-eye" id="confirm_password_icon"></i>
                                                                  </button>
                                                            </div>
                                                      </div>
                                                      <button type="submit" class="btn btn-primary">
                                                            <i class="fas fa-key me-2"></i>Change Password
                                                      </button>
                                                </form>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Order Details Modal -->
      <div class="modal fade" id="orderModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Order Details</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="orderModalBody">
                              <!-- Order details will be loaded here -->
                        </div>
                        <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                              <button type="button" class="btn btn-primary" onclick="downloadInvoice(currentOrderId)">Download Invoice</button>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Invoice Modal -->
      <div class="modal fade" id="invoiceModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Invoice</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="invoiceModalBody">
                              <!-- Invoice will be loaded here -->
                        </div>
                        <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                              <button type="button" class="btn btn-primary" onclick="downloadInvoice(currentInvoiceId)">Download PDF</button>
                        </div>
                  </div>
            </div>
      </div>

      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <script>
            let currentOrderId = null;
            let currentInvoiceId = null;

            // Navigation
            document.querySelectorAll('.nav-link[data-section]').forEach(link => {
                  link.addEventListener('click', function(e) {
                        // Let the link work normally for URL-based navigation
                        // The page will reload with the new section
                  });
            });

            function showSection(sectionId) {
                  document.querySelectorAll('.content-section').forEach(section => {
                        section.classList.remove('active');
                  });
                  document.getElementById(sectionId).classList.add('active');
            }

            // Update browser history when navigating
            function updateURL(section) {
                  const url = new URL(window.location);
                  url.searchParams.set('type', section);
                  window.history.pushState({}, '', url);
            }

            // Handle browser back/forward buttons
            window.addEventListener('popstate', function() {
                  const urlParams = new URLSearchParams(window.location.search);
                  const section = urlParams.get('type') || 'dashboard';
                  showSection(section);

                  // Update active nav link
                  document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                  document.querySelector(`[data-section="${section}"]`).classList.add('active');
            });

            // Initialize section based on URL parameter
            document.addEventListener('DOMContentLoaded', function() {
                  const urlParams = new URLSearchParams(window.location.search);
                  const section = urlParams.get('type') || 'dashboard';

                  // Show the correct section
                  showSection(section);

                  // Update active nav link
                  document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                  const activeLink = document.querySelector(`[data-section="${section}"]`);
                  if (activeLink) {
                        activeLink.classList.add('active');
                  }
            });

            function toggleSidebar() {
                  const sidebar = document.getElementById('sidebar');
                  const main = document.getElementById('main');
                  const overlay = document.getElementById('sidebarOverlay');
                  const isMobile = window.innerWidth <= 768;

                  if (isMobile) {
                        // Mobile behavior - slide in/out with overlay
                        sidebar.classList.toggle('mobile-open');
                        overlay.classList.toggle('active');
                  } else {
                        // Desktop behavior - collapse/expand
                        sidebar.classList.toggle('collapsed');
                        main.classList.toggle('sidebar-collapsed');
                  }
            }

            function closeSidebar() {
                  const sidebar = document.getElementById('sidebar');
                  const overlay = document.getElementById('sidebarOverlay');
                  const isMobile = window.innerWidth <= 768;

                  if (isMobile) {
                        sidebar.classList.remove('mobile-open');
                        overlay.classList.remove('active');
                  }
            }

            // Handle window resize
            window.addEventListener('resize', function() {
                  const sidebar = document.getElementById('sidebar');
                  const main = document.getElementById('main');
                  const overlay = document.getElementById('sidebarOverlay');
                  const isMobile = window.innerWidth <= 768;

                  if (isMobile) {
                        // Reset to mobile state
                        sidebar.classList.remove('collapsed');
                        main.classList.remove('sidebar-collapsed');
                        sidebar.classList.remove('mobile-open');
                        overlay.classList.remove('active');
                  } else {
                        // Reset to desktop state
                        sidebar.classList.remove('mobile-open');
                        overlay.classList.remove('active');
                  }
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                  const sidebar = document.getElementById('sidebar');
                  const toggleBtn = document.querySelector('.sidebar-toggle');
                  const overlay = document.getElementById('sidebarOverlay');
                  const isMobile = window.innerWidth <= 768;

                  if (isMobile && sidebar.classList.contains('mobile-open')) {
                        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                              sidebar.classList.remove('mobile-open');
                              overlay.classList.remove('active');
                        }
                  }
            });

            // Update current time
            function updateTime() {
                  const now = new Date();
                  document.getElementById('currentTime').textContent = now.toLocaleString();
            }
            setInterval(updateTime, 1000);
            updateTime();

            // Order functions
            function viewOrder(orderId) {
                  currentOrderId = orderId;
                  fetch(`api/get_order_details.php?order_id=${orderId}`)
                        .then(response => response.json())
                        .then(data => {
                              document.getElementById('orderModalBody').innerHTML = data.html;
                              new bootstrap.Modal(document.getElementById('orderModal')).show();
                        })
                        .catch(error => {
                              console.error('Error:', error);
                              alert('Error loading order details');
                        });
            }

            function viewInvoice(orderId) {
                  currentInvoiceId = orderId;
                  fetch(`api/get_invoice.php?order_id=${orderId}`)
                        .then(response => response.json())
                        .then(data => {
                              document.getElementById('invoiceModalBody').innerHTML = data.html;
                              new bootstrap.Modal(document.getElementById('invoiceModal')).show();
                        })
                        .catch(error => {
                              console.error('Error:', error);
                              alert('Error loading invoice');
                        });
            }

            function downloadInvoice(orderId) {
                  window.open(`api/download_invoice.php?order_id=${orderId}`, '_blank');
            }

            function downloadAllInvoices() {
                  window.open('api/download_all_invoices.php', '_blank');
            }

            // Price check functions
            function searchProducts() {
                  const searchTerm = document.getElementById('searchProduct').value;
                  const category = document.getElementById('categoryFilter').value;

                  fetch(`api/search_products.php?search=${encodeURIComponent(searchTerm)}&category=${category}`)
                        .then(response => response.json())
                        .then(data => {
                              document.getElementById('priceResults').innerHTML = data.html;
                        })
                        .catch(error => {
                              console.error('Error:', error);
                              alert('Error searching products');
                        });
            }

            function refreshPrices() {
                  location.reload();
            }

            // Profile functions
            function editProfile() {
                  // Implement profile editing functionality
                  alert('Profile editing feature coming soon!');
            }

            // Password toggle function
            function togglePassword(fieldId) {
                  const passwordField = document.getElementById(fieldId);
                  const icon = document.getElementById(fieldId + '_icon');

                  if (passwordField.type === 'password') {
                        passwordField.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                  } else {
                        passwordField.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                  }
            }

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                  return new bootstrap.Tooltip(tooltipTriggerEl);
            });
      </script>
</body>

</html>