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
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                  case 'add_expense':
                        $expense_date = $_POST['expense_date'];
                        $vendor_name = trim($_POST['vendor_name']);
                        $vendor_email = trim($_POST['vendor_email']);
                        $vendor_phone = trim($_POST['vendor_phone']);
                        $description = trim($_POST['description']);
                        $amount = floatval($_POST['amount']);
                        $tax_amount = floatval($_POST['tax_amount']);
                        $total_amount = floatval($_POST['total_amount']);
                        $payment_method = $_POST['payment_method'];
                        $payment_status = $_POST['payment_status'];
                        $expense_category = trim($_POST['expense_category']);
                        $notes = trim($_POST['notes']);

                        // Handle receipt upload
                        $receipt_path = null;
                        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                              $upload_dir = '../uploads/receipts/';
                              if (!is_dir($upload_dir)) {
                                    mkdir($upload_dir, 0755, true);
                              }

                              $file_extension = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
                              $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

                              if (in_array($file_extension, $allowed_extensions)) {
                                    $filename = 'receipt_' . time() . '_' . uniqid() . '.' . $file_extension;
                                    $filepath = $upload_dir . $filename;

                                    if (move_uploaded_file($_FILES['receipt']['tmp_name'], $filepath)) {
                                          $receipt_path = 'uploads/receipts/' . $filename;
                                    }
                              }
                        }

                        // Generate expense number
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE DATE(expense_date) = ?");
                        $stmt->execute([$expense_date]);
                        $count = $stmt->fetchColumn() + 1;
                        $expense_number = 'EXP-' . date('Ymd', strtotime($expense_date)) . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

                        $stmt = $pdo->prepare("INSERT INTO expenses (expense_number, expense_date, vendor_name, vendor_email, vendor_phone, description, amount, tax_amount, total_amount, payment_method, payment_status, expense_category, receipt_path, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                        if ($stmt->execute([$expense_number, $expense_date, $vendor_name, $vendor_email, $vendor_phone, $description, $amount, $tax_amount, $total_amount, $payment_method, $payment_status, $expense_category, $receipt_path, $notes, $_SESSION['user_id']])) {
                              $message = 'Expense added successfully!';
                        } else {
                              $error = 'Failed to add expense.';
                        }
                        break;

                  case 'update_expense':
                        $expense_id = intval($_POST['expense_id']);
                        $expense_date = $_POST['expense_date'];
                        $vendor_name = trim($_POST['vendor_name']);
                        $vendor_email = trim($_POST['vendor_email']);
                        $vendor_phone = trim($_POST['vendor_phone']);
                        $description = trim($_POST['description']);
                        $amount = floatval($_POST['amount']);
                        $tax_amount = floatval($_POST['tax_amount']);
                        $total_amount = floatval($_POST['total_amount']);
                        $payment_method = $_POST['payment_method'];
                        $payment_status = $_POST['payment_status'];
                        $expense_category = trim($_POST['expense_category']);
                        $notes = trim($_POST['notes']);

                        // Handle receipt upload
                        $receipt_path = null;
                        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                              $upload_dir = '../uploads/receipts/';
                              if (!is_dir($upload_dir)) {
                                    mkdir($upload_dir, 0755, true);
                              }

                              $file_extension = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
                              $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

                              if (in_array($file_extension, $allowed_extensions)) {
                                    $filename = 'receipt_' . time() . '_' . uniqid() . '.' . $file_extension;
                                    $filepath = $upload_dir . $filename;

                                    if (move_uploaded_file($_FILES['receipt']['tmp_name'], $filepath)) {
                                          $receipt_path = 'uploads/receipts/' . $filename;
                                    }
                              }
                        }

                        if ($receipt_path) {
                              $stmt = $pdo->prepare("UPDATE expenses SET expense_date = ?, vendor_name = ?, vendor_email = ?, vendor_phone = ?, description = ?, amount = ?, tax_amount = ?, total_amount = ?, payment_method = ?, payment_status = ?, expense_category = ?, receipt_path = ?, notes = ? WHERE id = ?");
                              $params = [$expense_date, $vendor_name, $vendor_email, $vendor_phone, $description, $amount, $tax_amount, $total_amount, $payment_method, $payment_status, $expense_category, $receipt_path, $notes, $expense_id];
                        } else {
                              $stmt = $pdo->prepare("UPDATE expenses SET expense_date = ?, vendor_name = ?, vendor_email = ?, vendor_phone = ?, description = ?, amount = ?, tax_amount = ?, total_amount = ?, payment_method = ?, payment_status = ?, expense_category = ?, notes = ? WHERE id = ?");
                              $params = [$expense_date, $vendor_name, $vendor_email, $vendor_phone, $description, $amount, $tax_amount, $total_amount, $payment_method, $payment_status, $expense_category, $notes, $expense_id];
                        }

                        if ($stmt->execute($params)) {
                              $message = 'Expense updated successfully!';
                        } else {
                              $error = 'Failed to update expense.';
                        }
                        break;

                  case 'delete_expense':
                        $expense_id = intval($_POST['expense_id']);

                        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
                        if ($stmt->execute([$expense_id])) {
                              $message = 'Expense deleted successfully!';
                        } else {
                              $error = 'Failed to delete expense.';
                        }
                        break;
            }
      }
}

// Get expenses with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
      $where_conditions[] = "(e.expense_number LIKE ? OR e.vendor_name LIKE ? OR e.description LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      $params[] = "%$search%";
}

if (!empty($category)) {
      $where_conditions[] = "e.expense_category = ?";
      $params[] = $category;
}

if (!empty($status)) {
      $where_conditions[] = "e.payment_status = ?";
      $params[] = $status;
}

if (!empty($date_from)) {
      $where_conditions[] = "e.expense_date >= ?";
      $params[] = $date_from;
}

if (!empty($date_to)) {
      $where_conditions[] = "e.expense_date <= ?";
      $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) FROM expenses e $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_expenses = $stmt->fetchColumn();
$total_pages = ceil($total_expenses / $limit);

// Get expenses
$sql = "SELECT e.*, u.username as created_by_name 
        FROM expenses e 
        LEFT JOIN users u ON e.created_by = u.id 
        $where_clause 
        ORDER BY e.expense_date DESC, e.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get expense categories for filter
$stmt = $pdo->prepare("SELECT DISTINCT expense_category FROM expenses WHERE expense_category IS NOT NULL AND expense_category != '' ORDER BY expense_category");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get expense statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_expenses,
        SUM(total_amount) as total_amount,
        AVG(total_amount) as avg_amount,
        COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_expenses,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_expenses
    FROM expenses 
    WHERE MONTH(expense_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(expense_date) = YEAR(CURRENT_DATE())
");
$stmt->execute();
$expense_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Expenses Management - POS Admin</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Google Fonts -->
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="assets/css/admin.css">
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
                                                <li class="breadcrumb-item active" aria-current="page">Expenses</li>
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
                                          <i class="fas fa-receipt"></i>
                                          Expenses Management
                                    </h1>
                                    <p class="text-muted mb-0">Track and manage business expenses</p>
                              </div>
                        </div>

                        <!-- Action Button -->
                        <div class="row mb-4">
                              <div class="col-12">
                                    <div class="d-flex justify-content-end">
                                          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                                <i class="fas fa-plus me-2"></i>New Expense
                                          </button>
                                    </div>
                              </div>
                        </div>

                        <?php if ($message): ?>
                              <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                              <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <!-- Expense Statistics -->
                        <div class="row mb-4">
                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Total Expenses</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-receipt"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value">$<?php echo number_format($expense_stats['total_amount'] ?? 0, 2); ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> This Month
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card success">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Paid Expenses</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-check-circle"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo $expense_stats['paid_expenses'] ?? 0; ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> This Month
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card warning">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Pending Expenses</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-clock"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo $expense_stats['pending_expenses'] ?? 0; ?></h2>
                                          <div class="stat-card-change negative">
                                                <i class="fas fa-arrow-down"></i> This Month
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card info">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Average Expense</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-chart-line"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value">$<?php echo number_format($expense_stats['avg_amount'] ?? 0, 2); ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> This Month
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Search and Filter -->
                        <div class="row mb-4">
                              <div class="col-12">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-search"></i>
                                                      Search & Filter
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <form method="GET" class="row g-3">
                                                      <div class="col-md-3">
                                                            <label for="search" class="form-label">Search</label>
                                                            <input type="text" class="form-control" id="search" name="search"
                                                                  value="<?php echo htmlspecialchars($search); ?>"
                                                                  placeholder="Expense #, vendor, description...">
                                                      </div>
                                                      <div class="col-md-2">
                                                            <label for="category" class="form-label">Category</label>
                                                            <select class="form-select" id="category" name="category">
                                                                  <option value="">All Categories</option>
                                                                  <?php foreach ($categories as $cat): ?>
                                                                        <option value="<?php echo htmlspecialchars($cat); ?>"
                                                                              <?php echo $category === $cat ? 'selected' : ''; ?>>
                                                                              <?php echo htmlspecialchars($cat); ?>
                                                                        </option>
                                                                  <?php endforeach; ?>
                                                            </select>
                                                      </div>
                                                      <div class="col-md-2">
                                                            <label for="status" class="form-label">Status</label>
                                                            <select class="form-select" id="status" name="status">
                                                                  <option value="">All Status</option>
                                                                  <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                                  <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                  <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                            </select>
                                                      </div>
                                                      <div class="col-md-2">
                                                            <label for="date_from" class="form-label">From Date</label>
                                                            <input type="date" class="form-control" id="date_from" name="date_from"
                                                                  value="<?php echo htmlspecialchars($date_from); ?>">
                                                      </div>
                                                      <div class="col-md-2">
                                                            <label for="date_to" class="form-label">To Date</label>
                                                            <input type="date" class="form-control" id="date_to" name="date_to"
                                                                  value="<?php echo htmlspecialchars($date_to); ?>">
                                                      </div>
                                                      <div class="col-md-1">
                                                            <label class="form-label">&nbsp;</label>
                                                            <div class="d-flex gap-2">
                                                                  <button type="submit" class="btn btn-primary">
                                                                        <i class="fas fa-search"></i>
                                                                  </button>
                                                                  <a href="expenses.php" class="btn btn-outline-secondary">
                                                                        <i class="fas fa-times"></i>
                                                                  </a>
                                                            </div>
                                                      </div>
                                                </form>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Expenses Table -->
                        <div class="row mb-4">
                              <div class="col-12">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-list"></i>
                                                      Expenses List
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
                                                                        <th>Payment Method</th>
                                                                        <th>Status</th>
                                                                        <th>Created By</th>
                                                                        <th>Actions</th>
                                                                  </tr>
                                                            </thead>
                                                            <tbody>
                                                                  <?php foreach ($expenses as $expense): ?>
                                                                        <tr>
                                                                              <td><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></td>
                                                                              <td>
                                                                                    <strong><?php echo htmlspecialchars($expense['expense_number']); ?></strong>
                                                                              </td>
                                                                              <td>
                                                                                    <?php echo htmlspecialchars($expense['vendor_name'] ?? 'N/A'); ?>
                                                                                    <?php if ($expense['vendor_email']): ?>
                                                                                          <br><small class="text-muted"><?php echo htmlspecialchars($expense['vendor_email']); ?></small>
                                                                                    <?php endif; ?>
                                                                              </td>
                                                                              <td><?php echo htmlspecialchars(substr($expense['description'], 0, 50)) . (strlen($expense['description']) > 50 ? '...' : ''); ?></td>
                                                                              <td>
                                                                                    <span class="badge bg-info">
                                                                                          <?php echo htmlspecialchars($expense['expense_category'] ?? 'General'); ?>
                                                                                    </span>
                                                                              </td>
                                                                              <td class="text-end">
                                                                                    <strong>$<?php echo number_format($expense['total_amount'], 2); ?></strong>
                                                                                    <?php if ($expense['tax_amount'] > 0): ?>
                                                                                          <br><small class="text-muted">Tax: $<?php echo number_format($expense['tax_amount'], 2); ?></small>
                                                                                    <?php endif; ?>
                                                                              </td>
                                                                              <td>
                                                                                    <span class="badge bg-secondary">
                                                                                          <?php echo ucfirst($expense['payment_method']); ?>
                                                                                    </span>
                                                                              </td>
                                                                              <td>
                                                                                    <span class="badge bg-<?php
                                                                                                            echo $expense['payment_status'] == 'paid' ? 'success' : ($expense['payment_status'] == 'pending' ? 'warning' : 'danger');
                                                                                                            ?>">
                                                                                          <?php echo ucfirst($expense['payment_status']); ?>
                                                                                    </span>
                                                                              </td>
                                                                              <td><?php echo htmlspecialchars($expense['created_by_name']); ?></td>
                                                                              <td>
                                                                                    <div class="btn-group btn-group-sm">
                                                                                          <button class="btn btn-outline-primary" onclick="viewExpense(<?php echo $expense['id']; ?>)">
                                                                                                <i class="fas fa-eye"></i>
                                                                                          </button>
                                                                                          <button class="btn btn-outline-warning" onclick="editExpense(<?php echo $expense['id']; ?>)">
                                                                                                <i class="fas fa-edit"></i>
                                                                                          </button>
                                                                                          <button class="btn btn-outline-danger" onclick="deleteExpense(<?php echo $expense['id']; ?>)">
                                                                                                <i class="fas fa-trash"></i>
                                                                                          </button>
                                                                                    </div>
                                                                              </td>
                                                                        </tr>
                                                                  <?php endforeach; ?>
                                                            </tbody>
                                                      </table>
                                                </div>

                                                <!-- Pagination -->
                                                <?php if ($total_pages > 1): ?>
                                                      <div class="d-flex justify-content-between align-items-center mt-4">
                                                            <div class="text-muted">
                                                                  Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_expenses); ?> of <?php echo $total_expenses; ?> expenses
                                                            </div>
                                                            <nav aria-label="Expenses pagination">
                                                                  <ul class="pagination mb-0">
                                                                        <?php if ($page > 1): ?>
                                                                              <li class="page-item">
                                                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                                                              </li>
                                                                        <?php endif; ?>

                                                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                                              <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                                                              </li>
                                                                        <?php endfor; ?>

                                                                        <?php if ($page < $total_pages): ?>
                                                                              <li class="page-item">
                                                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                                                              </li>
                                                                        <?php endif; ?>
                                                                  </ul>
                                                            </nav>
                                                      </div>
                                                <?php endif; ?>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Add Expense Modal -->
      <div class="modal fade" id="addExpenseModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">
                                    <i class="fas fa-plus me-2"></i>New Expense
                              </h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                              <input type="hidden" name="action" value="add_expense">
                              <div class="modal-body">
                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="expense_date" class="form-label">Expense Date *</label>
                                                      <input type="date" class="form-control" id="expense_date" name="expense_date"
                                                            value="<?php echo date('Y-m-d'); ?>" required>
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="expense_category" class="form-label">Category</label>
                                                      <input type="text" class="form-control" id="expense_category" name="expense_category"
                                                            placeholder="e.g., Rent, Utilities, Supplies">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="vendor_name" class="form-label">Vendor Name</label>
                                                      <input type="text" class="form-control" id="vendor_name" name="vendor_name"
                                                            placeholder="Vendor or supplier name">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="vendor_email" class="form-label">Vendor Email</label>
                                                      <input type="email" class="form-control" id="vendor_email" name="vendor_email"
                                                            placeholder="vendor@example.com">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="vendor_phone" class="form-label">Vendor Phone</label>
                                                      <input type="text" class="form-control" id="vendor_phone" name="vendor_phone"
                                                            placeholder="Phone number">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="payment_method" class="form-label">Payment Method</label>
                                                      <select class="form-select" id="payment_method" name="payment_method">
                                                            <option value="cash">Cash</option>
                                                            <option value="check">Check</option>
                                                            <option value="bank_transfer">Bank Transfer</option>
                                                            <option value="credit_card">Credit Card</option>
                                                            <option value="other">Other</option>
                                                      </select>
                                                </div>
                                          </div>
                                    </div>

                                    <div class="mb-3">
                                          <label for="description" class="form-label">Description *</label>
                                          <textarea class="form-control" id="description" name="description" rows="3"
                                                placeholder="Detailed description of the expense" required></textarea>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-4">
                                                <div class="mb-3">
                                                      <label for="amount" class="form-label">Amount *</label>
                                                      <input type="number" class="form-control" id="amount" name="amount"
                                                            step="0.01" min="0" placeholder="0.00" required onchange="calculateTotal()">
                                                </div>
                                          </div>
                                          <div class="col-md-4">
                                                <div class="mb-3">
                                                      <label for="tax_amount" class="form-label">Tax Amount</label>
                                                      <input type="number" class="form-control" id="tax_amount" name="tax_amount"
                                                            step="0.01" min="0" placeholder="0.00" onchange="calculateTotal()">
                                                </div>
                                          </div>
                                          <div class="col-md-4">
                                                <div class="mb-3">
                                                      <label for="total_amount" class="form-label">Total Amount</label>
                                                      <input type="number" class="form-control" id="total_amount" name="total_amount"
                                                            step="0.01" min="0" placeholder="0.00" readonly>
                                                </div>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="payment_status" class="form-label">Payment Status</label>
                                                      <select class="form-select" id="payment_status" name="payment_status">
                                                            <option value="paid">Paid</option>
                                                            <option value="pending">Pending</option>
                                                            <option value="cancelled">Cancelled</option>
                                                      </select>
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="receipt" class="form-label">Receipt</label>
                                                      <input type="file" class="form-control" id="receipt" name="receipt"
                                                            accept="image/*,.pdf">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="mb-3">
                                          <label for="notes" class="form-label">Notes</label>
                                          <textarea class="form-control" id="notes" name="notes" rows="2"
                                                placeholder="Additional notes"></textarea>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                          <i class="fas fa-save me-2"></i>Save Expense
                                    </button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Delete Expense Form -->
      <form id="deleteExpenseForm" method="POST" style="display: none;">
            <input type="hidden" name="action" value="delete_expense">
            <input type="hidden" name="expense_id" id="delete_expense_id">
      </form>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>

      <script>
            function calculateTotal() {
                  const amount = parseFloat(document.getElementById('amount').value) || 0;
                  const taxAmount = parseFloat(document.getElementById('tax_amount').value) || 0;
                  const total = amount + taxAmount;
                  document.getElementById('total_amount').value = total.toFixed(2);
            }

            function viewExpense(expenseId) {
                  // Load expense details via AJAX
                  fetch(`get_expense_details.php?id=${expenseId}`)
                        .then(response => response.text())
                        .then(html => {
                              // Create modal for viewing expense details
                              const modal = document.createElement('div');
                              modal.className = 'modal fade';
                              modal.innerHTML = `
                                    <div class="modal-dialog modal-lg">
                                          <div class="modal-content">
                                                <div class="modal-header">
                                                      <h5 class="modal-title">Expense Details</h5>
                                                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">${html}</div>
                                          </div>
                                    </div>
                              `;
                              document.body.appendChild(modal);
                              new bootstrap.Modal(modal).show();
                              modal.addEventListener('hidden.bs.modal', () => modal.remove());
                        });
            }

            function editExpense(expenseId) {
                  // Load expense for editing via AJAX
                  fetch(`get_expense.php?id=${expenseId}`)
                        .then(response => response.json())
                        .then(data => {
                              // Populate the add expense modal with data
                              document.getElementById('expense_date').value = data.expense_date;
                              document.getElementById('expense_category').value = data.expense_category || '';
                              document.getElementById('vendor_name').value = data.vendor_name || '';
                              document.getElementById('vendor_email').value = data.vendor_email || '';
                              document.getElementById('vendor_phone').value = data.vendor_phone || '';
                              document.getElementById('payment_method').value = data.payment_method;
                              document.getElementById('description').value = data.description;
                              document.getElementById('amount').value = data.amount;
                              document.getElementById('tax_amount').value = data.tax_amount;
                              document.getElementById('total_amount').value = data.total_amount;
                              document.getElementById('payment_status').value = data.payment_status;
                              document.getElementById('notes').value = data.notes || '';

                              // Change form action
                              const form = document.querySelector('#addExpenseModal form');
                              form.querySelector('input[name="action"]').value = 'update_expense';
                              form.insertAdjacentHTML('beforeend', `<input type="hidden" name="expense_id" value="${data.id}">`);

                              // Change modal title
                              document.querySelector('#addExpenseModal .modal-title').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Expense';
                              document.querySelector('#addExpenseModal .btn-primary').innerHTML = '<i class="fas fa-save me-2"></i>Update Expense';

                              new bootstrap.Modal(document.getElementById('addExpenseModal')).show();
                        });
            }

            function deleteExpense(expenseId) {
                  if (confirm('Are you sure you want to delete this expense? This action cannot be undone.')) {
                        document.getElementById('delete_expense_id').value = expenseId;
                        document.getElementById('deleteExpenseForm').submit();
                  }
            }

            // Reset modal when closed
            document.getElementById('addExpenseModal').addEventListener('hidden.bs.modal', function() {
                  this.querySelector('form').reset();
                  this.querySelector('input[name="action"]').value = 'add_expense';
                  this.querySelector('.modal-title').innerHTML = '<i class="fas fa-plus me-2"></i>New Expense';
                  this.querySelector('.btn-primary').innerHTML = '<i class="fas fa-save me-2"></i>Save Expense';
                  const expenseIdInput = this.querySelector('input[name="expense_id"]');
                  if (expenseIdInput) expenseIdInput.remove();
            });
      </script>
</body>

</html>