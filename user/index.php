<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

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
                                    <a class="nav-link active" href="index.php">
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
                                    <a class="nav-link" href="orders.php">
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
                        <div class="card border-left-primary shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                      Today's Sales
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      $<?php echo number_format($stats['today_sales'], 2); ?>
                                                </div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>

                  <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                      Today's Orders
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      <?php echo $stats['today_orders']; ?>
                                                </div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>

                  <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                      Total Products
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      <?php echo $stats['total_products']; ?>
                                                </div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-box fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>

                  <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                      Low Stock Items
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      <?php echo $stats['low_stock_items']; ?>
                                                </div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                  <div class="col-lg-8">
                        <div class="card shadow mb-4">
                              <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                              </div>
                              <div class="card-body">
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
                        <div class="card shadow mb-4">
                              <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                              </div>
                              <div class="card-body">
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

                        <div class="card shadow mb-4">
                              <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">System Status</h6>
                              </div>
                              <div class="card-body">
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

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="../admin/assets/js/admin.js"></script>
</body>

</html>