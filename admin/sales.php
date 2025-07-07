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

// Get sales statistics
$stmt = $pdo->prepare("SELECT 
    COALESCE(SUM(total_amount), 0) as total_sales,
    COUNT(*) as total_orders,
    COALESCE(AVG(total_amount), 0) as avg_order_value,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders
FROM orders");
$stmt->execute();
$sales_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent sales
$stmt = $pdo->prepare("SELECT o.*, COUNT(oi.id) as item_count 
FROM orders o 
LEFT JOIN order_items oi ON o.id = oi.order_id 
GROUP BY o.id 
ORDER BY o.created_at DESC 
LIMIT 10");
$stmt->execute();
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment methods
$stmt = $pdo->prepare("SELECT payment_method, COUNT(*) as count 
FROM orders 
WHERE payment_method IS NOT NULL 
GROUP BY payment_method 
ORDER BY count DESC");
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
      <!-- Google Fonts -->
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="assets/css/admin.css">
      <!-- Chart.js -->
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                                                <li class="breadcrumb-item active" aria-current="page">Sales</li>
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
                                          <i class="fas fa-chart-line"></i>
                                          Sales Management
                                    </h1>
                                    <p class="text-muted mb-0">Manage and analyze your sales data</p>
                              </div>
                        </div>

                        <!-- Sales Statistics Cards -->
                        <div class="row mb-4">
                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Total Sales</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-dollar-sign"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value">$<?php echo number_format($sales_stats['total_sales'], 2); ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> +15.3% from last month
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card success">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Total Orders</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-shopping-cart"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo $sales_stats['total_orders']; ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> +8.7% from last month
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card info">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Average Order Value</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-chart-line"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value">$<?php echo number_format($sales_stats['avg_order_value'], 2); ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> +6.2% from last month
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card warning">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Completion Rate</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-percentage"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo $sales_stats['total_orders'] > 0 ? round(($sales_stats['completed_orders'] / $sales_stats['total_orders']) * 100, 1) : 0; ?>%</h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> +2.1% from last month
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Charts Row -->
                        <div class="row mb-4">
                              <!-- Sales Chart -->
                              <div class="col-xl-8 col-lg-7">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-chart-line"></i>
                                                      Sales Overview
                                                </h6>
                                                <div class="dropdown">
                                                      <button class="btn btn-link dropdown-toggle" type="button" id="chartDropdown" data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                      </button>
                                                      <ul class="dropdown-menu dropdown-menu-end">
                                                            <li><a class="dropdown-item" href="#" onclick="updateChart('week')"><i class="fas fa-calendar-week me-2"></i>This Week</a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="updateChart('month')"><i class="fas fa-calendar-alt me-2"></i>This Month</a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="updateChart('year')"><i class="fas fa-calendar me-2"></i>This Year</a></li>
                                                      </ul>
                                                </div>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="chart-container">
                                                      <canvas id="salesChart"></canvas>
                                                </div>
                                          </div>
                                    </div>
                              </div>

                              <!-- Payment Methods Chart -->
                              <div class="col-xl-4 col-lg-5">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-chart-pie"></i>
                                                      Payment Methods
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="chart-container small">
                                                      <canvas id="paymentChart"></canvas>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Recent Sales Table -->
                        <div class="row">
                              <div class="col-12">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-list"></i>
                                                      Recent Sales
                                                </h6>
                                                <a href="#" class="btn btn-modern btn-primary btn-sm">
                                                      <i class="fas fa-eye me-1"></i>View All
                                                </a>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="table-responsive">
                                                      <table class="table table-modern" id="salesTable" width="100%" cellspacing="0">
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
                                                                              <td>
                                                                                    <span class="fw-bold text-primary">#<?php echo $sale['id']; ?></span>
                                                                              </td>
                                                                              <td>
                                                                                    <div class="d-flex align-items-center">
                                                                                          <div class="avatar-sm me-2">
                                                                                                <i class="fas fa-user-circle fa-lg text-secondary"></i>
                                                                                          </div>
                                                                                          <div>
                                                                                                <div class="fw-medium"><?php echo htmlspecialchars($sale['customer_name']); ?></div>
                                                                                                <small class="text-muted">Customer</small>
                                                                                          </div>
                                                                                    </div>
                                                                              </td>
                                                                              <td><?php echo htmlspecialchars($sale['customer_email']); ?></td>
                                                                              <td>
                                                                                    <span class="badge bg-light text-dark"><?php echo $sale['item_count']; ?> items</span>
                                                                              </td>
                                                                              <td>
                                                                                    <span class="fw-bold text-success">$<?php echo number_format($sale['total_amount'], 2); ?></span>
                                                                              </td>
                                                                              <td>
                                                                                    <span class="badge bg-info">
                                                                                          <?php echo ucfirst($sale['payment_method']); ?>
                                                                                    </span>
                                                                              </td>
                                                                              <td>
                                                                                    <span class="badge bg-<?php
                                                                                                            echo $sale['status'] == 'completed' ? 'success' : ($sale['status'] == 'pending' ? 'warning' : 'danger');
                                                                                                            ?>">
                                                                                          <i class="fas fa-<?php
                                                                                                            echo $sale['status'] == 'completed' ? 'check' : ($sale['status'] == 'pending' ? 'clock' : 'times');
                                                                                                            ?> me-1"></i>
                                                                                          <?php echo ucfirst($sale['status']); ?>
                                                                                    </span>
                                                                              </td>
                                                                              <td>
                                                                                    <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($sale['created_at'])); ?></small>
                                                                              </td>
                                                                              <td>
                                                                                    <div class="btn-group btn-group-sm">
                                                                                          <button class="btn btn-outline-primary" title="View Details">
                                                                                                <i class="fas fa-eye"></i>
                                                                                          </button>
                                                                                          <button class="btn btn-outline-success" title="Mark Complete">
                                                                                                <i class="fas fa-check"></i>
                                                                                          </button>
                                                                                    </div>
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
                              borderColor: '#4e73df',
                              backgroundColor: 'rgba(78, 115, 223, 0.1)',
                              borderWidth: 2,
                              fill: true,
                              tension: 0.4
                        }]
                  },
                  options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                              legend: {
                                    display: false
                              }
                        },
                        scales: {
                              y: {
                                    beginAtZero: true,
                                    grid: {
                                          color: 'rgba(0, 0, 0, 0.05)'
                                    }
                              },
                              x: {
                                    grid: {
                                          display: false
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
                        labels: ['Cash', 'Credit Card', 'Debit Card', 'Mobile Payment'],
                        datasets: [{
                              data: [40, 25, 20, 15],
                              backgroundColor: [
                                    '#4e73df',
                                    '#1cc88a',
                                    '#f6c23e',
                                    '#e74a3b'
                              ],
                              borderWidth: 0
                        }]
                  },
                  options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                              legend: {
                                    position: 'bottom',
                                    labels: {
                                          padding: 20,
                                          usePointStyle: true
                                    }
                              }
                        }
                  }
            });

            function updateChart(period) {
                  console.log('Updating chart for period:', period);
                  // Here you would typically make an AJAX call to get new data
                  // For now, we'll just show a message
                  alert('Chart would update for ' + period + ' period');
            }
      </script>
</body>

</html>