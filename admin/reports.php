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

// Sales summary
$stmt = $pdo->prepare("SELECT COUNT(*) as total_orders, SUM(total_amount) as total_sales, AVG(total_amount) as avg_order_value FROM orders WHERE status = 'completed'");
$stmt->execute();
$sales_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Inventory summary
$stmt = $pdo->prepare("SELECT COUNT(*) as total_products, SUM(stock_quantity) as total_stock, COUNT(CASE WHEN stock_quantity < 10 THEN 1 END) as low_stock, COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock FROM products");
$stmt->execute();
$inventory_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// User summary
$stmt = $pdo->prepare("SELECT COUNT(*) as total_users, COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count, COUNT(CASE WHEN role = 'manager' THEN 1 END) as manager_count, COUNT(CASE WHEN role = 'cashier' THEN 1 END) as cashier_count FROM users");
$stmt->execute();
$user_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Sales by month for the last 6 months
$stmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as sales FROM orders WHERE status = 'completed' GROUP BY month ORDER BY month DESC LIMIT 6");
$stmt->execute();
$sales_by_month = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

// Top 5 selling products
$stmt = $pdo->prepare("SELECT p.name, SUM(oi.quantity) as total_sold FROM products p JOIN order_items oi ON p.id = oi.product_id JOIN orders o ON oi.order_id = o.id WHERE o.status = 'completed' GROUP BY p.id, p.name ORDER BY total_sold DESC LIMIT 5");
$stmt->execute();
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Reports - POS System</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <link rel="stylesheet" href="assets/css/admin.css">
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
      <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                  <a class="navbar-brand" href="index.php">
                        <i class="fas fa-store me-2"></i>POS Admin
                  </a>
                  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                        <span class="navbar-toggler-icon"></span>
                  </button>
                  <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav me-auto">
                              <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a></li>
                              <li class="nav-item"><a class="nav-link" href="products.php"><i class="fas fa-box me-1"></i>Products</a></li>
                              <li class="nav-item"><a class="nav-link" href="sales.php"><i class="fas fa-chart-line me-1"></i>Sales</a></li>
                              <li class="nav-item"><a class="nav-link" href="inventory.php"><i class="fas fa-warehouse me-1"></i>Inventory</a></li>
                              <li class="nav-item"><a class="nav-link" href="users.php"><i class="fas fa-users me-1"></i>Users</a></li>
                              <li class="nav-item"><a class="nav-link active" href="reports.php"><i class="fas fa-file-alt me-1"></i>Reports</a></li>
                        </ul>
                        <ul class="navbar-nav">
                              <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                          <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['username']; ?>
                                    </a>
                                    <ul class="dropdown-menu">
                                          <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                          <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                          <li>
                                                <hr class="dropdown-divider">
                                          </li>
                                          <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                    </ul>
                              </li>
                        </ul>
                  </div>
            </div>
      </nav>
      <div class="container-fluid mt-4">
            <div class="row mb-4">
                  <div class="col-12">
                        <h1 class="h3 mb-0 text-gray-800">Reports</h1>
                        <p class="text-muted">Business summary, trends, and analytics</p>
                  </div>
            </div>
            <div class="row mb-4">
                  <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Sales</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($sales_summary['total_sales'], 2); ?></div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
                  <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Orders</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $sales_summary['total_orders']; ?></div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
                  <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Average Order Value</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($sales_summary['avg_order_value'], 2); ?></div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
            <div class="row mb-4">
                  <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Products</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $inventory_summary['total_products']; ?></div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-box fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
                  <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Low Stock</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $inventory_summary['low_stock']; ?></div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
                  <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-secondary shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Out of Stock</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $inventory_summary['out_of_stock']; ?></div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
            <div class="row mb-4">
                  <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $user_summary['total_users']; ?></div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-users fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
                  <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Admins</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $user_summary['admin_count']; ?></div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
                  <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Managers</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $user_summary['manager_count']; ?></div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
            <div class="row mb-4">
                  <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                              <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Sales Trend (Last 6 Months)</h6>
                              </div>
                              <div class="card-body">
                                    <div class="chart-area">
                                          <canvas id="salesTrendChart"></canvas>
                                    </div>
                              </div>
                        </div>
                  </div>
                  <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                              <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Top 5 Products</h6>
                              </div>
                              <div class="card-body">
                                    <ul class="list-group">
                                          <?php foreach ($top_products as $product): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                      <?php echo htmlspecialchars($product['name']); ?>
                                                      <span class="badge bg-primary rounded-pill"><?php echo $product['total_sold']; ?> sold</span>
                                                </li>
                                          <?php endforeach; ?>
                                    </ul>
                              </div>
                        </div>
                  </div>
            </div>
            <div class="row mb-4">
                  <div class="col-12">
                        <a href="#" class="btn btn-success"><i class="fas fa-file-export me-2"></i>Export Report (CSV)</a>
                        <a href="#" class="btn btn-secondary"><i class="fas fa-print me-2"></i>Print Report</a>
                  </div>
            </div>
      </div>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <script src="assets/js/admin.js"></script>
      <script>
            const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
            const salesTrendChart = new Chart(salesTrendCtx, {
                  type: 'line',
                  data: {
                        labels: <?php echo json_encode(array_column($sales_by_month, 'month')); ?>,
                        datasets: [{
                              label: 'Sales',
                              data: <?php echo json_encode(array_map('floatval', array_column($sales_by_month, 'sales'))); ?>,
                              borderColor: 'rgb(75, 192, 192)',
                              backgroundColor: 'rgba(75, 192, 192, 0.1)',
                              tension: 0.1,
                              fill: true
                        }]
                  },
                  options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                              y: {
                                    beginAtZero: true,
                                    ticks: {
                                          callback: function(value) {
                                                return '$' + value.toLocaleString();
                                          }
                                    }
                              }
                        }
                  }
            });
      </script>
</body>

</html>