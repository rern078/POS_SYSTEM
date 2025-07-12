<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/exchange_rate.php';

// Check if user is logged in
if (!isLoggedIn()) {
      header('Location: ../login.php');
      exit();
}

// Redirect customers to their dashboard
if ($_SESSION['role'] === 'customer') {
      header('Location: customer_dashboard.php');
      exit();
}

// Get user data
$user = getCurrentUser();
$pdo = getDBConnection();

// Get basic stats for user dashboard
$stats = [];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM products");
$stmt->execute();
$stats['total_products'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE stock_quantity < 10");
$stmt->execute();
$stats['low_stock_items'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$stats['today_orders'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = CURDATE() AND status = 'completed'");
$stmt->execute();
$stats['today_sales'] = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>User Dashboard - POS System</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Google Fonts -->
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="../admin/assets/css/admin.css">
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
                                                <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                                          </ol>
                                    </nav>
                              </div>
                        </div>

                        <div class="topbar-right">
                              <div class="dropdown">
                                    <button class="btn btn-link notification-btn" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                          <i class="fas fa-bell"></i>
                                          <span class="notification-badge">3</span>
                                    </button>
                                    <ul class="dropdown-menu notification-menu" aria-labelledby="notificationDropdown">
                                          <li>
                                                <h6 class="dropdown-header">Notifications</h6>
                                          </li>
                                          <li><a class="dropdown-item" href="#"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Low stock alert</a></li>
                                          <li><a class="dropdown-item" href="#"><i class="fas fa-info-circle text-info me-2"></i>New order received</a></li>
                                          <li><a class="dropdown-item" href="#"><i class="fas fa-check-circle text-success me-2"></i>Payment completed</a></li>
                                          <li>
                                                <hr class="dropdown-divider">
                                          </li>
                                          <li><a class="dropdown-item text-center" href="#">View all notifications</a></li>
                                    </ul>
                              </div>
                        </div>
                  </nav>

                  <!-- Main Content Area -->
                  <div class="admin-content">
                        <!-- Page Header -->
                        <div class="row mb-4">
                              <div class="col-12">
                                    <h1 class="h3 mb-0 text-gray-800">Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h1>
                                    <p class="text-muted">Role: <?php echo ucfirst($user['role']); ?> | Last login: <?php echo date('M d, Y H:i'); ?></p>
                              </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card success">
                                          <div class="stat-card-header">
                                                <div class="stat-card-title">Today's Sales</div>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-dollar-sign"></i>
                                                </div>
                                          </div>
                                          <div class="stat-card-value">
                                                <?php
                                                $exchangeRate = new ExchangeRate();
                                                $defaultCurrency = $exchangeRate->getDefaultCurrency();
                                                $symbol = $defaultCurrency['symbol'] ?? '$';
                                                echo $symbol . number_format($stats['today_sales'], 2);
                                                ?>
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card info">
                                          <div class="stat-card-header">
                                                <div class="stat-card-title">Today's Orders</div>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-shopping-cart"></i>
                                                </div>
                                          </div>
                                          <div class="stat-card-value"><?php echo $stats['today_orders']; ?></div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card warning">
                                          <div class="stat-card-header">
                                                <div class="stat-card-title">Total Products</div>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-box"></i>
                                                </div>
                                          </div>
                                          <div class="stat-card-value"><?php echo $stats['total_products']; ?></div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card danger">
                                          <div class="stat-card-header">
                                                <div class="stat-card-title">Low Stock Items</div>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-exclamation-triangle"></i>
                                                </div>
                                          </div>
                                          <div class="stat-card-value"><?php echo $stats['low_stock_items']; ?></div>
                                    </div>
                              </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="row">
                              <div class="col-lg-8">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h5 class="content-card-title">Quick Actions</h5>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="row">
                                                      <div class="col-md-6 mb-3">
                                                            <a href="pos.php" class="btn btn-primary btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                                                  <i class="fas fa-cash-register fa-3x mb-3"></i>
                                                                  <span>Start New Sale</span>
                                                            </a>
                                                      </div>
                                                      <div class="col-md-6 mb-3">
                                                            <a href="products.php" class="btn btn-info btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                                                  <i class="fas fa-box fa-3x mb-3"></i>
                                                                  <span>View Products</span>
                                                            </a>
                                                      </div>
                                                      <div class="col-md-6 mb-3">
                                                            <a href="orders.php" class="btn btn-success btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                                                  <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                                                  <span>View Orders</span>
                                                            </a>
                                                      </div>
                                                      <?php if (isManager()): ?>
                                                            <div class="col-md-6 mb-3">
                                                                  <a href="reports.php" class="btn btn-warning btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                                                        <i class="fas fa-chart-bar fa-3x mb-3"></i>
                                                                        <span>View Reports</span>
                                                                  </a>
                                                            </div>
                                                      <?php else: ?>
                                                            <div class="col-md-6 mb-3">
                                                                  <a href="profile.php" class="btn btn-secondary btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                                                        <i class="fas fa-user fa-3x mb-3"></i>
                                                                        <span>My Profile</span>
                                                                  </a>
                                                            </div>
                                                      <?php endif; ?>
                                                </div>
                                          </div>
                                    </div>
                              </div>

                              <div class="col-lg-4">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h5 class="content-card-title">Recent Activity</h5>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="list-group list-group-flush">
                                                      <div class="list-group-item d-flex justify-content-between align-items-center">
                                                            <div>
                                                                  <i class="fas fa-sign-in-alt text-success me-2"></i>
                                                                  <small>Logged in</small>
                                                            </div>
                                                            <small class="text-muted"><?php echo date('H:i'); ?></small>
                                                      </div>
                                                      <div class="list-group-item d-flex justify-content-between align-items-center">
                                                            <div>
                                                                  <i class="fas fa-user text-info me-2"></i>
                                                                  <small>Profile accessed</small>
                                                            </div>
                                                            <small class="text-muted">Today</small>
                                                      </div>
                                                </div>
                                          </div>
                                    </div>

                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h5 class="content-card-title">System Status</h5>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                      <span>Database</span>
                                                      <span class="badge bg-success">Online</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                      <span>Session</span>
                                                      <span class="badge bg-success">Active</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                      <span>Role</span>
                                                      <span class="badge bg-primary"><?php echo ucfirst($_SESSION['role']); ?></span>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="../admin/assets/js/admin.js"></script>
</body>

</html>