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
      <link rel="stylesheet" href="../assets/css/user.css">
</head>

<body>
      <div class="user-layout">
            <?php include 'side.php'; ?>

            <!-- Main Content Wrapper -->
            <div class="user-main" id="userMain">
                  <!-- Top Navigation Bar -->
                  <nav class="user-topbar">
                        <div class="topbar-left">
                              <button class="sidebar-toggle-btn" id="sidebarToggleBtn">
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
                  <div class="user-content">
                        <!-- Welcome Section -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h1 class="content-card-title">
                                          <i class="fas fa-tachometer-alt"></i>
                                          Welcome to Your Dashboard
                                    </h1>
                              </div>
                              <div class="content-card-body">
                                    <div class="row">
                                          <div class="col-md-8">
                                                <h4 class="mb-3">Hello, <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>!</h4>
                                                <p class="text-muted mb-4">Welcome back to your POS system dashboard. Here's an overview of your business today.</p>

                                                <div class="row g-3">
                                                      <div class="col-md-6">
                                                            <a href="pos.php" class="btn btn-modern btn-primary w-100">
                                                                  <i class="fas fa-cash-register"></i>
                                                                  Start New Sale
                                                            </a>
                                                      </div>
                                                      <div class="col-md-6">
                                                            <a href="products.php" class="btn btn-modern btn-outline-primary w-100">
                                                                  <i class="fas fa-box"></i>
                                                                  Manage Products
                                                            </a>
                                                      </div>
                                                </div>
                                          </div>
                                          <div class="col-md-4 text-center">
                                                <div class="stats-card">
                                                      <div class="stats-icon" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                                                            <i class="fas fa-user"></i>
                                                      </div>
                                                      <div class="stats-number"><?php echo ucfirst($user['role']); ?></div>
                                                      <div class="stats-label">User Role</div>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Stats Cards -->
                        <div class="row g-4 mb-4">
                              <div class="col-xl-3 col-md-6">
                                    <div class="stats-card">
                                          <div class="stats-icon" style="background: linear-gradient(135deg, var(--success-color), #28a745);">
                                                <i class="fas fa-box"></i>
                                          </div>
                                          <div class="stats-number"><?php echo number_format($stats['total_products']); ?></div>
                                          <div class="stats-label">Total Products</div>
                                    </div>
                              </div>
                              <div class="col-xl-3 col-md-6">
                                    <div class="stats-card">
                                          <div class="stats-icon" style="background: linear-gradient(135deg, var(--warning-color), #fd7e14);">
                                                <i class="fas fa-exclamation-triangle"></i>
                                          </div>
                                          <div class="stats-number"><?php echo number_format($stats['low_stock_items']); ?></div>
                                          <div class="stats-label">Low Stock Items</div>
                                    </div>
                              </div>
                              <div class="col-xl-3 col-md-6">
                                    <div class="stats-card">
                                          <div class="stats-icon" style="background: linear-gradient(135deg, var(--info-color), #17a2b8);">
                                                <i class="fas fa-shopping-cart"></i>
                                          </div>
                                          <div class="stats-number"><?php echo number_format($stats['today_orders']); ?></div>
                                          <div class="stats-label">Today's Orders</div>
                                    </div>
                              </div>
                              <div class="col-xl-3 col-md-6">
                                    <div class="stats-card">
                                          <div class="stats-icon" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                                                <i class="fas fa-dollar-sign"></i>
                                          </div>
                                          <div class="stats-number">$<?php echo number_format($stats['today_sales'], 2); ?></div>
                                          <div class="stats-label">Today's Sales</div>
                                    </div>
                              </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h2 class="content-card-title">
                                          <i class="fas fa-bolt"></i>
                                          Quick Actions
                                    </h2>
                              </div>
                              <div class="content-card-body">
                                    <div class="row g-3">
                                          <div class="col-md-4">
                                                <a href="pos.php" class="btn btn-modern btn-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                                      <i class="fas fa-cash-register fa-2x mb-2"></i>
                                                      <span>New Sale</span>
                                                </a>
                                          </div>
                                          <div class="col-md-4">
                                                <a href="products.php" class="btn btn-modern btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                                      <i class="fas fa-box fa-2x mb-2"></i>
                                                      <span>Products</span>
                                                </a>
                                          </div>
                                          <div class="col-md-4">
                                                <a href="orders.php" class="btn btn-modern btn-outline-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                                      <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                                      <span>Orders</span>
                                                </a>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

      <!-- Custom JS -->
      <script>
            // Sidebar toggle functionality
            document.getElementById('sidebarToggleBtn').addEventListener('click', function() {
                  const sidebar = document.getElementById('adminSidebar');
                  const main = document.getElementById('userMain');

                  sidebar.classList.toggle('collapsed');
                  main.classList.toggle('sidebar-collapsed');
            });

            // Mobile sidebar toggle
            if (window.innerWidth <= 768) {
                  document.getElementById('sidebarToggleBtn').addEventListener('click', function() {
                        const sidebar = document.getElementById('adminSidebar');
                        sidebar.classList.toggle('show');
                  });
            }
      </script>
</body>

</html>