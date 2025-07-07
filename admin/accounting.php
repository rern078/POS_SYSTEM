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

// Get accounting statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_entries,
        SUM(total_debit) as total_debits,
        SUM(total_credit) as total_credits
    FROM journal_entries 
    WHERE MONTH(entry_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(entry_date) = YEAR(CURRENT_DATE())
");
$stmt->execute();
$journal_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get expense statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_expenses,
        SUM(total_amount) as total_expense_amount,
        AVG(total_amount) as avg_expense
    FROM expenses 
    WHERE MONTH(expense_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(expense_date) = YEAR(CURRENT_DATE())
    AND payment_status = 'paid'
");
$stmt->execute();
$expense_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get revenue statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sales,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_sale
    FROM orders 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
    AND status = 'completed'
");
$stmt->execute();
$revenue_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get account balances
$stmt = $pdo->prepare("
    SELECT 
        coa.account_code,
        coa.account_name,
        coa.account_type,
        COALESCE(SUM(CASE WHEN jed.debit_amount > 0 THEN jed.debit_amount ELSE 0 END), 0) as total_debits,
        COALESCE(SUM(CASE WHEN jed.credit_amount > 0 THEN jed.credit_amount ELSE 0 END), 0) as total_credits
    FROM chart_of_accounts coa
    LEFT JOIN journal_entry_details jed ON coa.id = jed.account_id
    LEFT JOIN journal_entries je ON jed.journal_entry_id = je.id
    WHERE coa.is_active = 1
    GROUP BY coa.id, coa.account_code, coa.account_name, coa.account_type
    ORDER BY coa.account_code
");
$stmt->execute();
$account_balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent journal entries
$stmt = $pdo->prepare("
    SELECT je.*, u.username as created_by_name
    FROM journal_entries je
    LEFT JOIN users u ON je.created_by = u.id
    ORDER BY je.entry_date DESC, je.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent expenses
$stmt = $pdo->prepare("
    SELECT e.*, u.username as created_by_name
    FROM expenses e
    LEFT JOIN users u ON e.created_by = u.id
    ORDER BY e.expense_date DESC, e.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate profit/loss
$net_income = ($revenue_stats['total_revenue'] ?? 0) - ($expense_stats['total_expense_amount'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Accounting Dashboard - POS Admin</title>

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
                                                <li class="breadcrumb-item active" aria-current="page">Accounting</li>
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
                                          <i class="fas fa-calculator"></i>
                                          Accounting Dashboard
                                    </h1>
                                    <p class="text-muted mb-0">Financial overview and accounting management</p>
                              </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h6 class="content-card-title">
                                          <i class="fas fa-bolt"></i>
                                          Quick Actions
                                    </h6>
                              </div>
                              <div class="content-card-body">
                                    <div class="row">
                                          <div class="col-md-3 mb-2">
                                                <a href="journal_entries.php" class="btn btn-primary w-100">
                                                      <i class="fas fa-book me-2"></i>Journal Entries
                                                </a>
                                          </div>
                                          <div class="col-md-3 mb-2">
                                                <a href="expenses.php" class="btn btn-warning w-100">
                                                      <i class="fas fa-receipt me-2"></i>Expenses
                                                </a>
                                          </div>
                                          <div class="col-md-3 mb-2">
                                                <a href="vendors.php" class="btn btn-info w-100">
                                                      <i class="fas fa-truck me-2"></i>Vendors
                                                </a>
                                          </div>
                                          <div class="col-md-3 mb-2">
                                                <a href="purchase_orders.php" class="btn btn-success w-100">
                                                      <i class="fas fa-shopping-cart me-2"></i>Purchase Orders
                                                </a>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Financial Summary Cards -->
                        <div class="row mb-4">
                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Total Revenue</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-dollar-sign"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value">$<?php echo number_format($revenue_stats['total_revenue'] ?? 0, 2); ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> This Month
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card warning">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Total Expenses</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-receipt"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value">$<?php echo number_format($expense_stats['total_expense_amount'] ?? 0, 2); ?></h2>
                                          <div class="stat-card-change negative">
                                                <i class="fas fa-arrow-down"></i> This Month
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card <?php echo $net_income >= 0 ? 'success' : 'danger'; ?>">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Net Income</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-chart-line"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value">$<?php echo number_format($net_income, 2); ?></h2>
                                          <div class="stat-card-change <?php echo $net_income >= 0 ? 'positive' : 'negative'; ?>">
                                                <i class="fas fa-arrow-<?php echo $net_income >= 0 ? 'up' : 'down'; ?>"></i> This Month
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card info">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Journal Entries</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-book"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo $journal_stats['total_entries'] ?? 0; ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> This Month
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Charts Row -->
                        <div class="row mb-4">
                              <div class="col-xl-8 col-lg-7">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-chart-line"></i>
                                                      Revenue vs Expenses (Last 6 Months)
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="chart-container">
                                                      <canvas id="revenueExpenseChart"></canvas>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                              <div class="col-xl-4 col-lg-5">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-chart-pie"></i>
                                                      Account Types Distribution
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="chart-container small">
                                                      <canvas id="accountTypeChart"></canvas>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Account Balances and Recent Activity -->
                        <div class="row mb-4">
                              <div class="col-xl-6 col-lg-6">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-balance-scale"></i>
                                                      Account Balances
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="table-responsive">
                                                      <table class="table table-sm">
                                                            <thead>
                                                                  <tr>
                                                                        <th>Account</th>
                                                                        <th>Type</th>
                                                                        <th class="text-end">Balance</th>
                                                                  </tr>
                                                            </thead>
                                                            <tbody>
                                                                  <?php foreach ($account_balances as $account): ?>
                                                                        <?php
                                                                        $balance = 0;
                                                                        if (in_array($account['account_type'], ['asset', 'expense'])) {
                                                                              $balance = $account['total_debits'] - $account['total_credits'];
                                                                        } else {
                                                                              $balance = $account['total_credits'] - $account['total_debits'];
                                                                        }
                                                                        ?>
                                                                        <tr>
                                                                              <td>
                                                                                    <strong><?php echo htmlspecialchars($account['account_code']); ?></strong><br>
                                                                                    <small class="text-muted"><?php echo htmlspecialchars($account['account_name']); ?></small>
                                                                              </td>
                                                                              <td>
                                                                                    <span class="badge bg-<?php
                                                                                                            echo $account['account_type'] == 'asset' ? 'primary' : ($account['account_type'] == 'liability' ? 'warning' : ($account['account_type'] == 'equity' ? 'success' : ($account['account_type'] == 'revenue' ? 'info' : 'danger')));
                                                                                                            ?>">
                                                                                          <?php echo ucfirst($account['account_type']); ?>
                                                                                    </span>
                                                                              </td>
                                                                              <td class="text-end">
                                                                                    <strong class="<?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                                                          $<?php echo number_format(abs($balance), 2); ?>
                                                                                    </strong>
                                                                              </td>
                                                                        </tr>
                                                                  <?php endforeach; ?>
                                                            </tbody>
                                                      </table>
                                                </div>
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-6 col-lg-6">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-book"></i>
                                                      Recent Journal Entries
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="table-responsive">
                                                      <table class="table table-sm">
                                                            <thead>
                                                                  <tr>
                                                                        <th>Date</th>
                                                                        <th>Reference</th>
                                                                        <th>Type</th>
                                                                        <th class="text-end">Amount</th>
                                                                  </tr>
                                                            </thead>
                                                            <tbody>
                                                                  <?php foreach ($recent_entries as $entry): ?>
                                                                        <tr>
                                                                              <td><?php echo date('M d', strtotime($entry['entry_date'])); ?></td>
                                                                              <td>
                                                                                    <strong><?php echo htmlspecialchars($entry['entry_number']); ?></strong><br>
                                                                                    <small class="text-muted"><?php echo htmlspecialchars($entry['reference'] ?? ''); ?></small>
                                                                              </td>
                                                                              <td>
                                                                                    <span class="badge bg-secondary">
                                                                                          <?php echo ucfirst($entry['entry_type']); ?>
                                                                                    </span>
                                                                              </td>
                                                                              <td class="text-end">
                                                                                    <strong>$<?php echo number_format($entry['total_debit'], 2); ?></strong>
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

                        <!-- Recent Expenses -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h6 class="content-card-title">
                                          <i class="fas fa-receipt"></i>
                                          Recent Expenses
                                    </h6>
                              </div>
                              <div class="content-card-body">
                                    <div class="table-responsive">
                                          <table class="table table-bordered" id="expensesTable" width="100%" cellspacing="0">
                                                <thead>
                                                      <tr>
                                                            <th>Date</th>
                                                            <th>Expense #</th>
                                                            <th>Vendor</th>
                                                            <th>Description</th>
                                                            <th>Category</th>
                                                            <th>Amount</th>
                                                            <th>Status</th>
                                                            <th>Actions</th>
                                                      </tr>
                                                </thead>
                                                <tbody>
                                                      <?php foreach ($recent_expenses as $expense): ?>
                                                            <tr>
                                                                  <td><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></td>
                                                                  <td><?php echo htmlspecialchars($expense['expense_number']); ?></td>
                                                                  <td><?php echo htmlspecialchars($expense['vendor_name'] ?? 'N/A'); ?></td>
                                                                  <td><?php echo htmlspecialchars(substr($expense['description'], 0, 50)) . (strlen($expense['description']) > 50 ? '...' : ''); ?></td>
                                                                  <td>
                                                                        <span class="badge bg-info">
                                                                              <?php echo htmlspecialchars($expense['expense_category'] ?? 'General'); ?>
                                                                        </span>
                                                                  </td>
                                                                  <td>$<?php echo number_format($expense['total_amount'], 2); ?></td>
                                                                  <td>
                                                                        <span class="badge bg-<?php
                                                                                                echo $expense['payment_status'] == 'paid' ? 'success' : ($expense['payment_status'] == 'pending' ? 'warning' : 'danger');
                                                                                                ?>">
                                                                              <?php echo ucfirst($expense['payment_status']); ?>
                                                                        </span>
                                                                  </td>
                                                                  <td>
                                                                        <a href="edit_expense.php?id=<?php echo $expense['id']; ?>" class="btn btn-sm btn-primary">
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
            // Revenue vs Expenses Chart
            const revenueExpenseCtx = document.getElementById('revenueExpenseChart').getContext('2d');
            const revenueExpenseChart = new Chart(revenueExpenseCtx, {
                  type: 'line',
                  data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                              label: 'Revenue',
                              data: [12000, 19000, 15000, 25000, 22000, <?php echo $revenue_stats['total_revenue'] ?? 0; ?>],
                              borderColor: 'rgb(75, 192, 192)',
                              backgroundColor: 'rgba(75, 192, 192, 0.1)',
                              tension: 0.1,
                              fill: true
                        }, {
                              label: 'Expenses',
                              data: [8000, 12000, 10000, 18000, 15000, <?php echo $expense_stats['total_expense_amount'] ?? 0; ?>],
                              borderColor: 'rgb(255, 99, 132)',
                              backgroundColor: 'rgba(255, 99, 132, 0.1)',
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

            // Account Types Distribution Chart
            const accountTypeCtx = document.getElementById('accountTypeChart').getContext('2d');
            const accountTypeChart = new Chart(accountTypeCtx, {
                  type: 'doughnut',
                  data: {
                        labels: ['Assets', 'Liabilities', 'Equity', 'Revenue', 'Expenses'],
                        datasets: [{
                              data: [30, 20, 15, 25, 10],
                              backgroundColor: [
                                    'rgba(75, 192, 192, 0.8)',
                                    'rgba(255, 206, 86, 0.8)',
                                    'rgba(54, 162, 235, 0.8)',
                                    'rgba(255, 99, 132, 0.8)',
                                    'rgba(153, 102, 255, 0.8)'
                              ],
                              borderWidth: 2,
                              borderColor: '#fff'
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
      </script>
</body>

</html>