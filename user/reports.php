<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and is manager
if (!isLoggedIn() || !isManager()) {
      header('Location: ../login.php');
      exit();
}

$pdo = getDBConnection();

// Get dashboard statistics
$stats = getDashboardStats();
$sales_data = getSalesData('month');
$top_products = getTopProducts(5);
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Reports - POS System</title>

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
                                    <a class="nav-link" href="orders.php">
                                          <i class="fas fa-shopping-cart me-1"></i>Orders
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link active" href="reports.php">
                                          <i class="fas fa-chart-bar me-1"></i>Reports
                                    </a>
                              </li>
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
                        <h2><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</h2>
                        <p class="text-muted">View detailed reports and analytics for your business</p>
                  </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                  <div class="col-md-3">
                        <div class="card bg-primary text-white">
                              <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                          <div>
                                                <h4 class="card-title">Today's Sales</h4>
                                                <h2 class="mb-0">$<?php echo number_format($stats['today_sales'], 2); ?></h2>
                                          </div>
                                          <div class="align-self-center">
                                                <i class="fas fa-dollar-sign fa-2x"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
                  <div class="col-md-3">
                        <div class="card bg-success text-white">
                              <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                          <div>
                                                <h4 class="card-title">Today's Orders</h4>
                                                <h2 class="mb-0"><?php echo $stats['today_orders']; ?></h2>
                                          </div>
                                          <div class="align-self-center">
                                                <i class="fas fa-shopping-cart fa-2x"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
                  <div class="col-md-3">
                        <div class="card bg-info text-white">
                              <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                          <div>
                                                <h4 class="card-title">Total Products</h4>
                                                <h2 class="mb-0"><?php echo $stats['total_products']; ?></h2>
                                          </div>
                                          <div class="align-self-center">
                                                <i class="fas fa-box fa-2x"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
                  <div class="col-md-3">
                        <div class="card bg-warning text-white">
                              <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                          <div>
                                                <h4 class="card-title">Low Stock Items</h4>
                                                <h2 class="mb-0"><?php echo $stats['low_stock_items']; ?></h2>
                                          </div>
                                          <div class="align-self-center">
                                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>

            <!-- Charts and Tables -->
            <div class="row">
                  <div class="col-md-8">
                        <div class="card">
                              <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Sales Trend (Last 30 Days)</h5>
                              </div>
                              <div class="card-body">
                                    <div class="text-center py-4">
                                          <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                          <h5 class="text-muted">Sales Chart</h5>
                                          <p class="text-muted">Chart functionality would be implemented here with Chart.js or similar library</p>
                                    </div>
                              </div>
                        </div>
                  </div>
                  <div class="col-md-4">
                        <div class="card">
                              <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Products</h5>
                              </div>
                              <div class="card-body">
                                    <?php if (empty($top_products)): ?>
                                          <p class="text-muted text-center">No sales data available</p>
                                    <?php else: ?>
                                          <?php foreach ($top_products as $index => $product): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                      <div>
                                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo $product['total_sold']; ?> sold</small>
                                                      </div>
                                                      <span class="badge bg-primary">$<?php echo number_format($product['total_revenue'], 2); ?></span>
                                                </div>
                                          <?php endforeach; ?>
                                    <?php endif; ?>
                              </div>
                        </div>
                  </div>
            </div>

            <!-- Recent Orders -->
            <div class="row mt-4">
                  <div class="col-12">
                        <div class="card">
                              <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Orders</h5>
                              </div>
                              <div class="card-body">
                                    <?php if (empty($stats['recent_orders'])): ?>
                                          <p class="text-muted text-center">No recent orders</p>
                                    <?php else: ?>
                                          <div class="table-responsive">
                                                <table class="table table-hover">
                                                      <thead>
                                                            <tr>
                                                                  <th>Order ID</th>
                                                                  <th>Customer</th>
                                                                  <th>Items</th>
                                                                  <th>Total</th>
                                                                  <th>Status</th>
                                                                  <th>Date</th>
                                                            </tr>
                                                      </thead>
                                                      <tbody>
                                                            <?php foreach ($stats['recent_orders'] as $order): ?>
                                                                  <tr>
                                                                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                                                                        <td><?php echo htmlspecialchars($order['customer_name'] ?: 'Walk-in Customer'); ?></td>
                                                                        <td><span class="badge bg-info"><?php echo $order['product_count']; ?> items</span></td>
                                                                        <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                                                        <td>
                                                                              <span class="badge bg-<?php echo $order['status'] === 'completed' ? 'success' : ($order['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                                                    <?php echo ucfirst($order['status']); ?>
                                                                              </span>
                                                                        </td>
                                                                        <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                                                  </tr>
                                                            <?php endforeach; ?>
                                                      </tbody>
                                                </table>
                                          </div>
                                    <?php endif; ?>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>