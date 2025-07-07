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

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today

// Sales summary with date range
$stmt = $pdo->prepare("SELECT COUNT(*) as total_orders, SUM(total_amount) as total_sales, AVG(total_amount) as avg_order_value FROM orders WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
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

// Top 5 selling products with date range
$stmt = $pdo->prepare("SELECT p.name, SUM(oi.quantity) as total_sold, SUM(oi.quantity * oi.price) as total_revenue FROM products p JOIN order_items oi ON p.id = oi.product_id JOIN orders o ON oi.order_id = o.id WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY p.id, p.name ORDER BY total_sold DESC LIMIT 5");
$stmt->execute([$start_date, $end_date]);
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Daily sales for the selected period
$stmt = $pdo->prepare("SELECT DATE(created_at) as date, SUM(total_amount) as daily_sales, COUNT(*) as daily_orders FROM orders WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY date");
$stmt->execute([$start_date, $end_date]);
$daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Category-wise sales
$stmt = $pdo->prepare("SELECT p.category, SUM(oi.quantity * oi.price) as category_sales, SUM(oi.quantity) as category_quantity FROM products p JOIN order_items oi ON p.id = oi.product_id JOIN orders o ON oi.order_id = o.id WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY p.category ORDER BY category_sales DESC");
$stmt->execute([$start_date, $end_date]);
$category_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$start_date, $end_date]);
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Reports - POS System</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Google Fonts -->
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="assets/css/admin.css">
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
      <style>
            .date-filter-card {
                  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                  color: white;
                  border-radius: 15px;
                  padding: 20px;
                  margin-bottom: 30px;
            }

            .report-section {
                  margin-bottom: 30px;
            }

            .report-card {
                  background: white;
                  border-radius: 15px;
                  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                  overflow: hidden;
                  transition: transform 0.3s ease;
            }

            .report-card:hover {
                  transform: translateY(-5px);
            }

            .report-card-header {
                  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                  color: white;
                  padding: 20px;
                  border-bottom: none;
            }

            .chart-container {
                  position: relative;
                  height: 300px;
                  margin: 20px 0;
            }

            .export-buttons {
                  display: flex;
                  gap: 10px;
                  flex-wrap: wrap;
            }

            .metric-highlight {
                  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                  color: white;
                  padding: 15px;
                  border-radius: 10px;
                  text-align: center;
                  margin-bottom: 20px;
            }
      </style>
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
                                                <li class="breadcrumb-item active" aria-current="page">Reports</li>
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
                                          <i class="fas fa-chart-bar"></i>
                                          Reports & Analytics
                                    </h1>
                                    <p class="text-muted mb-0">Comprehensive business insights and performance metrics</p>
                              </div>
                        </div>

                        <!-- Date Range Filter -->
                        <div class="date-filter-card">
                              <form method="GET" class="row align-items-end">
                                    <div class="col-md-3">
                                          <label for="start_date" class="form-label text-white">Start Date</label>
                                          <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                                    </div>
                                    <div class="col-md-3">
                                          <label for="end_date" class="form-label text-white">End Date</label>
                                          <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                                    </div>
                                    <div class="col-md-3">
                                          <button type="submit" class="btn btn-light w-100">
                                                <i class="fas fa-filter me-2"></i>Apply Filter
                                          </button>
                                    </div>
                                    <div class="col-md-3">
                                          <a href="reports.php" class="btn btn-outline-light w-100">
                                                <i class="fas fa-refresh me-2"></i>Reset
                                          </a>
                                    </div>
                              </form>
                        </div>

                        <!-- Key Metrics Summary -->
                        <div class="metric-highlight">
                              <h4><i class="fas fa-calendar-alt me-2"></i>Period Summary: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></h4>
                              <div class="row mt-3">
                                    <div class="col-md-3">
                                          <h5>Total Sales</h5>
                                          <h3>$<?php echo number_format($sales_summary['total_sales'] ?? 0, 2); ?></h3>
                                    </div>
                                    <div class="col-md-3">
                                          <h5>Total Orders</h5>
                                          <h3><?php echo $sales_summary['total_orders'] ?? 0; ?></h3>
                                    </div>
                                    <div class="col-md-3">
                                          <h5>Average Order</h5>
                                          <h3>$<?php echo number_format($sales_summary['avg_order_value'] ?? 0, 2); ?></h3>
                                    </div>
                                    <div class="col-md-3">
                                          <h5>Days in Period</h5>
                                          <h3><?php echo (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1; ?></h3>
                                    </div>
                              </div>
                        </div>

                        <!-- Sales and Revenue Charts -->
                        <div class="row mb-4">
                              <div class="col-xl-8 col-lg-7">
                                    <div class="report-card">
                                          <div class="report-card-header">
                                                <h6 class="content-card-title mb-0">
                                                      <i class="fas fa-chart-line"></i>
                                                      Sales Trend (Last 6 Months)
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="chart-container">
                                                      <canvas id="salesTrendChart"></canvas>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                              <div class="col-xl-4 col-lg-5">
                                    <div class="report-card">
                                          <div class="report-card-header">
                                                <h6 class="content-card-title mb-0">
                                                      <i class="fas fa-trophy"></i>
                                                      Top Products (Period)
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <?php if (!empty($top_products)): ?>
                                                      <div class="chart-container">
                                                            <canvas id="topProductsChart"></canvas>
                                                      </div>
                                                <?php else: ?>
                                                      <p class="text-muted text-center">No sales data for selected period</p>
                                                <?php endif; ?>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Daily Sales Chart -->
                        <div class="row mb-4">
                              <div class="col-12">
                                    <div class="report-card">
                                          <div class="report-card-header">
                                                <h6 class="content-card-title mb-0">
                                                      <i class="fas fa-calendar-day"></i>
                                                      Daily Sales Breakdown
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="chart-container">
                                                      <canvas id="dailySalesChart"></canvas>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Category Analysis and Recent Orders -->
                        <div class="row mb-4">
                              <div class="col-xl-6 col-lg-6">
                                    <div class="report-card">
                                          <div class="report-card-header">
                                                <h6 class="content-card-title mb-0">
                                                      <i class="fas fa-tags"></i>
                                                      Sales by Category
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <?php if (!empty($category_sales)): ?>
                                                      <div class="chart-container">
                                                            <canvas id="categoryChart"></canvas>
                                                      </div>
                                                <?php else: ?>
                                                      <p class="text-muted text-center">No category data for selected period</p>
                                                <?php endif; ?>
                                          </div>
                                    </div>
                              </div>
                              <div class="col-xl-6 col-lg-6">
                                    <div class="report-card">
                                          <div class="report-card-header">
                                                <h6 class="content-card-title mb-0">
                                                      <i class="fas fa-clock"></i>
                                                      Recent Orders
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="table-responsive">
                                                      <table class="table table-hover">
                                                            <thead>
                                                                  <tr>
                                                                        <th>Order ID</th>
                                                                        <th>Customer Name</th>
                                                                        <th>Customer Email</th>
                                                                        <th>Total Amount</th>
                                                                        <th>Subtotal</th>
                                                                        <th>Tax Amount</th>
                                                                        <th>Discount Amount</th>
                                                                        <th>Journal Entry ID</th>
                                                                        <th>Status</th>
                                                                        <th>Payment Method</th>
                                                                        <th>Created At</th>
                                                                        <th>Updated At</th>
                                                                  </tr>
                                                            </thead>
                                                            <tbody>
                                                                  <?php foreach ($recent_orders as $order): ?>
                                                                        <tr>
                                                                              <td>#<?php echo $order['id']; ?></td>
                                                                              <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Walk-in Customer'); ?></td>
                                                                              <td><?php echo htmlspecialchars($order['customer_email']); ?></td>
                                                                              <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                                                              <td>$<?php echo number_format($order['subtotal'], 2); ?></td>
                                                                              <td>$<?php echo number_format($order['tax_amount'], 2); ?></td>
                                                                              <td>$<?php echo number_format($order['discount_amount'], 2); ?></td>
                                                                              <td><?php echo htmlspecialchars($order['journal_entry_id']); ?></td>
                                                                              <td><span class="badge bg-<?php echo $order['status'] == 'completed' ? 'success' : ($order['status'] == 'pending' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                                                              <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                                                                              <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                                                              <td><?php echo date('M d, Y H:i', strtotime($order['updated_at'])); ?></td>
                                                                        </tr>
                                                                  <?php endforeach; ?>
                                                            </tbody>
                                                      </table>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Export and Print Options -->
                        <div class="row mb-4">
                              <div class="col-12">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-download"></i>
                                                      Export Reports
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="export-buttons">
                                                      <a href="export_report.php?type=sales&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-success">
                                                            <i class="fas fa-file-excel me-2"></i>Export Sales Report (Excel)
                                                      </a>
                                                      <a href="export_report.php?type=inventory&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-info">
                                                            <i class="fas fa-file-csv me-2"></i>Export Inventory Report (CSV)
                                                      </a>
                                                      <a href="export_report.php?type=products&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-warning">
                                                            <i class="fas fa-file-pdf me-2"></i>Export Product Report (PDF)
                                                      </a>
                                                      <button onclick="window.print()" class="btn btn-secondary">
                                                            <i class="fas fa-print me-2"></i>Print Report
                                                      </button>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <script src="assets/js/admin.js"></script>
      <script>
            // Sales Trend Chart
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

            // Top Products Chart
            <?php if (!empty($top_products)): ?>
                  const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
                  const topProductsChart = new Chart(topProductsCtx, {
                        type: 'doughnut',
                        data: {
                              labels: <?php echo json_encode(array_column($top_products, 'name')); ?>,
                              datasets: [{
                                    data: <?php echo json_encode(array_column($top_products, 'total_sold')); ?>,
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
            <?php endif; ?>

            // Daily Sales Chart
            const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
            const dailySalesChart = new Chart(dailySalesCtx, {
                  type: 'bar',
                  data: {
                        labels: <?php echo json_encode(array_column($daily_sales, 'date')); ?>,
                        datasets: [{
                              label: 'Daily Sales',
                              data: <?php echo json_encode(array_map('floatval', array_column($daily_sales, 'daily_sales'))); ?>,
                              backgroundColor: 'rgba(54, 162, 235, 0.8)',
                              borderColor: 'rgba(54, 162, 235, 1)',
                              borderWidth: 1
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

            // Category Chart
            <?php if (!empty($category_sales)): ?>
                  const categoryCtx = document.getElementById('categoryChart').getContext('2d');
                  const categoryChart = new Chart(categoryCtx, {
                        type: 'pie',
                        data: {
                              labels: <?php echo json_encode(array_column($category_sales, 'category')); ?>,
                              datasets: [{
                                    data: <?php echo json_encode(array_map('floatval', array_column($category_sales, 'category_sales'))); ?>,
                                    backgroundColor: [
                                          '#FF6384',
                                          '#36A2EB',
                                          '#FFCE56',
                                          '#4BC0C0',
                                          '#9966FF',
                                          '#FF9F40'
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
            <?php endif; ?>
      </script>
</body>

</html>