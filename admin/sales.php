<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || !isAdmin()) {
      header('Location: login.php');
      exit();
}

// Get sales data
$pdo = getDBConnection();

// Get sales statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_orders,
    SUM(total_amount) as total_sales,
    AVG(total_amount) as avg_order_value,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders
FROM orders");
$stmt->execute();
$sales_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent sales
$stmt = $pdo->prepare("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 20
");
$stmt->execute();
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sales by payment method
$stmt = $pdo->prepare("
    SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total
    FROM orders
    WHERE status = 'completed'
    GROUP BY payment_method
");
$stmt->execute();
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Sales Management - POS System</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="assets/css/admin.css">
      <!-- Chart.js -->
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
      <!-- Navigation -->
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
                              <li class="nav-item">
                                    <a class="nav-link" href="index.php">
                                          <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="products.php">
                                          <i class="fas fa-box me-1"></i>Products
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link active" href="sales.php">
                                          <i class="fas fa-chart-line me-1"></i>Sales
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="inventory.php">
                                          <i class="fas fa-warehouse me-1"></i>Inventory
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="users.php">
                                          <i class="fas fa-users me-1"></i>Users
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="reports.php">
                                          <i class="fas fa-file-alt me-1"></i>Reports
                                    </a>
                              </li>
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

      <!-- Main Content -->
      <div class="container-fluid mt-4">
            <!-- Page Header -->
            <div class="row mb-4">
                  <div class="col-12">
                        <h1 class="h3 mb-0 text-gray-800">Sales Management</h1>
                        <p class="text-muted">Manage and analyze your sales data</p>
                  </div>
            </div>

            <!-- Sales Statistics Cards -->
            <div class="row mb-4">
                  <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                      Total Sales
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      $<?php echo number_format($sales_stats['total_sales'], 2); ?>
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
                                                      Total Orders
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      <?php echo $sales_stats['total_orders']; ?>
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
                                                      Average Order Value
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      $<?php echo number_format($sales_stats['avg_order_value'], 2); ?>
                                                </div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
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
                                                      Completion Rate
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      <?php echo $sales_stats['total_orders'] > 0 ? round(($sales_stats['completed_orders'] / $sales_stats['total_orders']) * 100, 1) : 0; ?>%
                                                </div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                  <!-- Sales Chart -->
                  <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                              <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Sales Overview</h6>
                                    <div class="dropdown no-arrow">
                                          <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                          </a>
                                          <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                                <div class="dropdown-header">Chart Options:</div>
                                                <a class="dropdown-item" href="#" onclick="updateChart('week')">This Week</a>
                                                <a class="dropdown-item" href="#" onclick="updateChart('month')">This Month</a>
                                                <a class="dropdown-item" href="#" onclick="updateChart('year')">This Year</a>
                                          </div>
                                    </div>
                              </div>
                              <div class="card-body">
                                    <div class="chart-area">
                                          <canvas id="salesChart"></canvas>
                                    </div>
                              </div>
                        </div>
                  </div>

                  <!-- Payment Methods Chart -->
                  <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                              <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Payment Methods</h6>
                              </div>
                              <div class="card-body">
                                    <div class="chart-pie pt-4 pb-2">
                                          <canvas id="paymentChart"></canvas>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>

            <!-- Recent Sales Table -->
            <div class="row">
                  <div class="col-12">
                        <div class="card shadow mb-4">
                              <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Recent Sales</h6>
                              </div>
                              <div class="card-body">
                                    <div class="table-responsive">
                                          <table class="table table-bordered" id="salesTable" width="100%" cellspacing="0">
                                                <thead>
                                                      <tr>
                                                            <th>Order ID</th>
                                                            <th>Customer</th>
                                                            <th>Email</th>
                                                            <th>Items</th>
                                                            <th>Total</th>
                                                            <th>Payment Method</th>
                                                            <th>Status</th>
                                                            <th>Date</th>
                                                            <th>Actions</th>
                                                      </tr>
                                                </thead>
                                                <tbody>
                                                      <?php foreach ($recent_sales as $sale): ?>
                                                            <tr>
                                                                  <td>#<?php echo $sale['id']; ?></td>
                                                                  <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                                                  <td><?php echo htmlspecialchars($sale['customer_email']); ?></td>
                                                                  <td><?php echo $sale['item_count']; ?> items</td>
                                                                  <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                                                  <td>
                                                                        <span class="badge bg-info">
                                                                              <?php echo ucfirst($sale['payment_method']); ?>
                                                                        </span>
                                                                  </td>
                                                                  <td>
                                                                        <span class="badge bg-<?php
                                                                                                echo $sale['status'] == 'completed' ? 'success' : ($sale['status'] == 'pending' ? 'warning' : 'danger');
                                                                                                ?>">
                                                                              <?php echo ucfirst($sale['status']); ?>
                                                                        </span>
                                                                  </td>
                                                                  <td><?php echo date('M d, Y H:i', strtotime($sale['created_at'])); ?></td>
                                                                  <td>
                                                                        <a href="order-details.php?id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-primary">
                                                                              <i class="fas fa-eye"></i>
                                                                        </a>
                                                                        <a href="edit-order.php?id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-warning">
                                                                              <i class="fas fa-edit"></i>
                                                                        </a>
                                                                  </td>
                                                            </tr>
                                                      <?php endforeach; ?>
                                                </tbody>
                                          </table>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>

      <script>
            // Sales Chart
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            const salesChart = new Chart(salesCtx, {
                  type: 'line',
                  data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                              label: 'Sales',
                              data: [12000, 19000, 15000, 25000, 22000, 30000],
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

            // Payment Methods Chart
            const paymentCtx = document.getElementById('paymentChart').getContext('2d');
            const paymentChart = new Chart(paymentCtx, {
                  type: 'doughnut',
                  data: {
                        labels: <?php echo json_encode(array_column($payment_methods, 'payment_method')); ?>,
                        datasets: [{
                              data: <?php echo json_encode(array_column($payment_methods, 'count')); ?>,
                              backgroundColor: [
                                    '#FF6384',
                                    '#36A2EB',
                                    '#FFCE56',
                                    '#4BC0C0',
                                    '#9966FF'
                              ]
                        }]
                  },
                  options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                              legend: {
                                    position: 'bottom'
                              }
                        }
                  }
            });

            function updateChart(period) {
                  // This function would typically make an AJAX call to get new data
                  console.log('Updating chart for period:', period);
            }
      </script>
</body>

</html>