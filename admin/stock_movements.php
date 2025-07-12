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

// Handle stock movement operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                  case 'stock_in':
                        $product_id = intval($_POST['product_id']);
                        $quantity = intval($_POST['quantity']);
                        $unit_cost = floatval($_POST['unit_cost']);
                        $supplier = trim($_POST['supplier']);
                        $reference = trim($_POST['reference']);
                        $notes = trim($_POST['notes']);

                        if ($quantity <= 0) {
                              $error = 'Quantity must be greater than 0.';
                        } elseif ($unit_cost < 0) {
                              $error = 'Unit cost cannot be negative.';
                        } else {
                              try {
                                    $pdo->beginTransaction();

                                    // Get current stock
                                    $stmt = $pdo->prepare("SELECT stock_quantity, name FROM products WHERE id = ?");
                                    $stmt->execute([$product_id]);
                                    $product = $stmt->fetch(PDO::FETCH_ASSOC);

                                    if ($product) {
                                          $old_quantity = $product['stock_quantity'];
                                          $new_quantity = $old_quantity + $quantity;

                                          // Update product stock
                                          $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                                          if ($stmt->execute([$new_quantity, $product_id])) {
                                                // Log the stock in
                                                $stmt = $pdo->prepare("INSERT INTO inventory_adjustments (product_id, adjustment_type, old_quantity, new_quantity, notes, adjusted_by) VALUES (?, 'stock_in', ?, ?, ?, ?)");
                                                $stmt->execute([$product_id, $old_quantity, $new_quantity, "Stock In: $quantity units from $supplier. Reference: $reference. Notes: $notes", $_SESSION['user_id']]);

                                                // Log purchase details (if purchase_orders table exists)
                                                try {
                                                      $stmt = $pdo->prepare("INSERT INTO purchase_orders (product_id, quantity, unit_cost, supplier, reference, notes, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'received')");
                                                      $stmt->execute([$product_id, $quantity, $unit_cost, $supplier, $reference, $notes, $_SESSION['user_id']]);
                                                } catch (PDOException $e) {
                                                      // Purchase orders table might not exist, but that's okay
                                                }

                                                $pdo->commit();
                                                $message = "Stock In successful! Added $quantity units to " . $product['name'];
                                          } else {
                                                $pdo->rollBack();
                                                $error = 'Failed to update stock.';
                                          }
                                    } else {
                                          $pdo->rollBack();
                                          $error = 'Product not found.';
                                    }
                              } catch (Exception $e) {
                                    $pdo->rollBack();
                                    $error = 'Database error: ' . $e->getMessage();
                              }
                        }
                        break;

                  case 'stock_out':
                        $product_id = intval($_POST['product_id']);
                        $quantity = intval($_POST['quantity']);
                        $reason = $_POST['reason'];
                        $reference = trim($_POST['reference']);
                        $notes = trim($_POST['notes']);

                        if ($quantity <= 0) {
                              $error = 'Quantity must be greater than 0.';
                        } else {
                              try {
                                    $pdo->beginTransaction();

                                    // Get current stock
                                    $stmt = $pdo->prepare("SELECT stock_quantity, name FROM products WHERE id = ?");
                                    $stmt->execute([$product_id]);
                                    $product = $stmt->fetch(PDO::FETCH_ASSOC);

                                    if ($product) {
                                          $old_quantity = $product['stock_quantity'];

                                          if ($old_quantity < $quantity) {
                                                $pdo->rollBack();
                                                $error = 'Insufficient stock. Available: ' . $old_quantity . ', Requested: ' . $quantity;
                                          } else {
                                                $new_quantity = $old_quantity - $quantity;

                                                // Update product stock
                                                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                                                if ($stmt->execute([$new_quantity, $product_id])) {
                                                      // Log the stock out
                                                      $stmt = $pdo->prepare("INSERT INTO inventory_adjustments (product_id, adjustment_type, old_quantity, new_quantity, notes, adjusted_by) VALUES (?, 'stock_out', ?, ?, ?, ?)");
                                                      $stmt->execute([$product_id, $old_quantity, $new_quantity, "Stock Out: $quantity units. Reason: $reason. Reference: $reference. Notes: $notes", $_SESSION['user_id']]);

                                                      $pdo->commit();
                                                      $message = "Stock Out successful! Removed $quantity units from " . $product['name'];
                                                } else {
                                                      $pdo->rollBack();
                                                      $error = 'Failed to update stock.';
                                                }
                                          }
                                    } else {
                                          $pdo->rollBack();
                                          $error = 'Product not found.';
                                    }
                              } catch (Exception $e) {
                                    $pdo->rollBack();
                                    $error = 'Database error: ' . $e->getMessage();
                              }
                        }
                        break;
            }
      }
}

// Get movement history with filters
$search = $_GET['search'] ?? '';
$movement_type = $_GET['movement_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$product_id = $_GET['product_id'] ?? '';

// Pagination settings
$items_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

$whereConditions = [];
$params = [];

if (!empty($search)) {
      $whereConditions[] = "(p.name LIKE ? OR p.product_code LIKE ? OR ia.notes LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      $params[] = "%$search%";
}

if (!empty($movement_type)) {
      $whereConditions[] = "ia.adjustment_type = ?";
      $params[] = $movement_type;
}

if (!empty($date_from)) {
      $whereConditions[] = "DATE(ia.adjusted_at) >= ?";
      $params[] = $date_from;
}

if (!empty($date_to)) {
      $whereConditions[] = "DATE(ia.adjusted_at) <= ?";
      $params[] = $date_to;
}

if (!empty($product_id)) {
      $whereConditions[] = "ia.product_id = ?";
      $params[] = $product_id;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM inventory_adjustments ia 
             LEFT JOIN products p ON ia.product_id = p.id 
             LEFT JOIN users u ON ia.adjusted_by = u.id 
             $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total_items = $countStmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

// Get paginated movements
$sql = "SELECT ia.*, p.name as product_name, p.product_code, p.barcode, u.username as adjusted_by_name
        FROM inventory_adjustments ia 
        LEFT JOIN products p ON ia.product_id = p.id 
        LEFT JOIN users u ON ia.adjusted_by = u.id 
        $whereClause 
        ORDER BY ia.adjusted_at DESC 
        LIMIT $items_per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products for filter dropdown
$products = [];
$prodStmt = $pdo->query("SELECT id, name, product_code FROM products ORDER BY name");
while ($row = $prodStmt->fetch(PDO::FETCH_ASSOC)) {
      $products[] = $row;
}

// Get movement statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_movements,
    COUNT(CASE WHEN adjustment_type = 'stock_in' THEN 1 END) as stock_ins,
    COUNT(CASE WHEN adjustment_type = 'stock_out' THEN 1 END) as stock_outs,
    SUM(CASE WHEN adjustment_type = 'stock_in' THEN quantity_change ELSE 0 END) as total_stock_in,
    SUM(CASE WHEN adjustment_type = 'stock_out' THEN ABS(quantity_change) ELSE 0 END) as total_stock_out
FROM inventory_adjustments 
WHERE DATE(adjusted_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Stock Movements - POS Admin</title>

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
                                                <li class="breadcrumb-item active" aria-current="page">Stock Movements</li>
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
                                    <div class="d-flex justify-content-between align-items-center">
                                          <div>
                                                <h1 class="content-card-title">
                                                      <i class="fas fa-exchange-alt"></i>
                                                      Stock Movements
                                                </h1>
                                                <p class="text-muted mb-0">Track stock in and stock out operations with detailed history.</p>
                                          </div>
                                          <div>
                                                <button class="btn btn-modern btn-success me-2" data-bs-toggle="modal" data-bs-target="#stockInModal">
                                                      <i class="fas fa-plus me-2"></i>Stock In
                                                </button>
                                                <button class="btn btn-modern btn-danger me-2" data-bs-toggle="modal" data-bs-target="#stockOutModal">
                                                      <i class="fas fa-minus me-2"></i>Stock Out
                                                </button>
                                                <button class="btn btn-modern btn-primary" onclick="exportMovements()">
                                                      <i class="fas fa-download me-2"></i>Export
                                                </button>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Messages -->
                        <?php if ($message): ?>
                              <div class="alert alert-modern alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                              </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                              <div class="alert alert-modern alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                              </div>
                        <?php endif; ?>

                        <!-- Movement Statistics -->
                        <div class="row mb-4">
                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Total Movements</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-exchange-alt"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo number_format($stats['total_movements']); ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> Last 30 days
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card success">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Stock Ins</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-plus-circle"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo number_format($stats['stock_ins']); ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> <?php echo number_format($stats['total_stock_in']); ?> units
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card warning">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Stock Outs</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-minus-circle"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo number_format($stats['stock_outs']); ?></h2>
                                          <div class="stat-card-change negative">
                                                <i class="fas fa-arrow-down"></i> <?php echo number_format($stats['total_stock_out']); ?> units
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card info">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Net Movement</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-chart-line"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo number_format($stats['total_stock_in'] - $stats['total_stock_out']); ?></h2>
                                          <div class="stat-card-change <?php echo ($stats['total_stock_in'] - $stats['total_stock_out']) >= 0 ? 'positive' : 'negative'; ?>">
                                                <i class="fas fa-<?php echo ($stats['total_stock_in'] - $stats['total_stock_out']) >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i> Net change
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Filters and Search -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h6 class="content-card-title">
                                          <i class="fas fa-filter"></i>
                                          Filters & Search
                                    </h6>
                              </div>
                              <div class="content-card-body">
                                    <form method="GET" class="row g-3 form-modern">
                                          <div class="col-md-3">
                                                <label for="search" class="form-label">Search</label>
                                                <input type="text" class="form-control" id="search" name="search"
                                                      placeholder="Search products, notes..."
                                                      value="<?php echo htmlspecialchars($search); ?>">
                                          </div>
                                          <div class="col-md-2">
                                                <label for="movement_type" class="form-label">Movement Type</label>
                                                <select class="form-select" id="movement_type" name="movement_type">
                                                      <option value="">All Types</option>
                                                      <option value="stock_in" <?php echo $movement_type === 'stock_in' ? 'selected' : ''; ?>>Stock In</option>
                                                      <option value="stock_out" <?php echo $movement_type === 'stock_out' ? 'selected' : ''; ?>>Stock Out</option>
                                                      <option value="sale" <?php echo $movement_type === 'sale' ? 'selected' : ''; ?>>Sale</option>
                                                      <option value="return" <?php echo $movement_type === 'return' ? 'selected' : ''; ?>>Return</option>
                                                      <option value="damage" <?php echo $movement_type === 'damage' ? 'selected' : ''; ?>>Damage</option>
                                                      <option value="manual" <?php echo $movement_type === 'manual' ? 'selected' : ''; ?>>Manual</option>
                                                </select>
                                          </div>
                                          <div class="col-md-2">
                                                <label for="product_id" class="form-label">Product</label>
                                                <select class="form-select" id="product_id" name="product_id">
                                                      <option value="">All Products</option>
                                                      <?php foreach ($products as $product): ?>
                                                            <option value="<?php echo $product['id']; ?>"
                                                                  <?php echo $product_id == $product['id'] ? 'selected' : ''; ?>>
                                                                  <?php echo htmlspecialchars($product['name']); ?>
                                                            </option>
                                                      <?php endforeach; ?>
                                                </select>
                                          </div>
                                          <div class="col-md-2">
                                                <label for="date_from" class="form-label">Date From</label>
                                                <input type="date" class="form-control" id="date_from" name="date_from"
                                                      value="<?php echo htmlspecialchars($date_from); ?>">
                                          </div>
                                          <div class="col-md-2">
                                                <label for="date_to" class="form-label">Date To</label>
                                                <input type="date" class="form-control" id="date_to" name="date_to"
                                                      value="<?php echo htmlspecialchars($date_to); ?>">
                                          </div>
                                          <div class="col-md-1">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="submit" class="btn btn-modern btn-primary w-100">
                                                      <i class="fas fa-search"></i>
                                                </button>
                                          </div>
                                    </form>
                              </div>
                        </div>

                        <!-- Movements Table -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                          <h6 class="content-card-title">
                                                <i class="fas fa-list"></i>
                                                Movement History
                                          </h6>
                                          <div>
                                                <button class="btn btn-modern btn-outline-secondary me-2" onclick="printPage()">
                                                      <i class="fas fa-print me-1"></i>Print
                                                </button>
                                                <button class="btn btn-modern btn-outline-secondary" onclick="exportTable('movementsTable', 'stock_movements')">
                                                      <i class="fas fa-download me-1"></i>Export Table
                                                </button>
                                          </div>
                                    </div>
                              </div>
                              <div class="content-card-body">
                                    <div class="table-responsive">
                                          <table class="table table-modern" id="movementsTable" width="100%" cellspacing="0">
                                                <thead>
                                                      <tr>
                                                            <th>Date & Time</th>
                                                            <th>Product</th>
                                                            <th>Type</th>
                                                            <th>Quantity Change</th>
                                                            <th>Old Stock</th>
                                                            <th>New Stock</th>
                                                            <th>Notes</th>
                                                            <th>User</th>
                                                      </tr>
                                                </thead>
                                                <tbody>
                                                      <?php foreach ($movements as $movement):
                                                            $type_class = $movement['adjustment_type'] === 'stock_in' ? 'success' : ($movement['adjustment_type'] === 'stock_out' ? 'danger' : ($movement['adjustment_type'] === 'sale' ? 'warning' : 'info'));
                                                            $type_icon = $movement['adjustment_type'] === 'stock_in' ? 'plus-circle' : ($movement['adjustment_type'] === 'stock_out' ? 'minus-circle' : ($movement['adjustment_type'] === 'sale' ? 'shopping-cart' : 'edit'));
                                                      ?>
                                                            <tr>
                                                                  <td>
                                                                        <small class="text-muted">
                                                                              <?php echo date('M d, Y H:i', strtotime($movement['adjusted_at'])); ?>
                                                                        </small>
                                                                  </td>
                                                                  <td>
                                                                        <div>
                                                                              <div class="fw-medium"><?php echo htmlspecialchars($movement['product_name']); ?></div>
                                                                              <small class="text-muted">
                                                                                    <?php echo htmlspecialchars($movement['product_code'] ?? 'N/A'); ?>
                                                                              </small>
                                                                        </div>
                                                                  </td>
                                                                  <td>
                                                                        <span class="badge bg-<?php echo $type_class; ?>">
                                                                              <i class="fas fa-<?php echo $type_icon; ?> me-1"></i>
                                                                              <?php echo ucfirst(str_replace('_', ' ', $movement['adjustment_type'])); ?>
                                                                        </span>
                                                                  </td>
                                                                  <td>
                                                                        <span class="fw-bold text-<?php echo $movement['quantity_change'] >= 0 ? 'success' : 'danger'; ?>">
                                                                              <?php echo ($movement['quantity_change'] >= 0 ? '+' : '') . number_format($movement['quantity_change']); ?>
                                                                        </span>
                                                                  </td>
                                                                  <td><?php echo number_format($movement['old_quantity']); ?></td>
                                                                  <td><?php echo number_format($movement['new_quantity']); ?></td>
                                                                  <td>
                                                                        <small class="text-muted">
                                                                              <?php echo htmlspecialchars(substr($movement['notes'], 0, 50)) . (strlen($movement['notes']) > 50 ? '...' : ''); ?>
                                                                        </small>
                                                                  </td>
                                                                  <td>
                                                                        <small class="text-muted">
                                                                              <?php echo htmlspecialchars($movement['adjusted_by_name']); ?>
                                                                        </small>
                                                                  </td>
                                                            </tr>
                                                      <?php endforeach; ?>
                                                </tbody>
                                          </table>
                                    </div>

                                    <!-- Pagination -->
                                    <?php if ($total_pages > 1): ?>
                                          <nav aria-label="Movements pagination" class="mt-4">
                                                <ul class="pagination justify-content-center">
                                                      <?php if ($current_page > 1): ?>
                                                            <li class="page-item">
                                                                  <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&movement_type=<?php echo urlencode($movement_type); ?>&product_id=<?php echo urlencode($product_id); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                                                        Previous
                                                                  </a>
                                                            </li>
                                                      <?php endif; ?>

                                                      <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                                                  <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&movement_type=<?php echo urlencode($movement_type); ?>&product_id=<?php echo urlencode($product_id); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                                                        <?php echo $i; ?>
                                                                  </a>
                                                            </li>
                                                      <?php endfor; ?>

                                                      <?php if ($current_page < $total_pages): ?>
                                                            <li class="page-item">
                                                                  <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&movement_type=<?php echo urlencode($movement_type); ?>&product_id=<?php echo urlencode($product_id); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                                                        Next
                                                                  </a>
                                                            </li>
                                                      <?php endif; ?>
                                                </ul>
                                          </nav>
                                    <?php endif; ?>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>

      <!-- Stock In Modal -->
      <div class="modal fade" id="stockInModal" tabindex="-1" aria-labelledby="stockInModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="stockInModalLabel">
                                    <i class="fas fa-plus-circle text-success me-2"></i>Stock In
                              </h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" class="form-modern">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="stock_in">

                                    <div class="mb-3">
                                          <label for="stock_in_product_id" class="form-label">Product *</label>
                                          <select class="form-select" id="stock_in_product_id" name="product_id" required>
                                                <option value="">Select Product</option>
                                                <?php foreach ($products as $product): ?>
                                                      <option value="<?php echo $product['id']; ?>">
                                                            <?php echo htmlspecialchars($product['name']); ?>
                                                            (<?php echo htmlspecialchars($product['product_code'] ?? 'N/A'); ?>)
                                                      </option>
                                                <?php endforeach; ?>
                                          </select>
                                    </div>

                                    <div class="mb-3">
                                          <label for="quantity" class="form-label">Quantity *</label>
                                          <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                                    </div>

                                    <div class="mb-3">
                                          <label for="unit_cost" class="form-label">Unit Cost</label>
                                          <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="unit_cost" name="unit_cost" min="0" step="0.01" placeholder="0.00">
                                          </div>
                                    </div>

                                    <div class="mb-3">
                                          <label for="supplier" class="form-label">Supplier</label>
                                          <input type="text" class="form-control" id="supplier" name="supplier" placeholder="Supplier name">
                                    </div>

                                    <div class="mb-3">
                                          <label for="reference" class="form-label">Reference</label>
                                          <input type="text" class="form-control" id="reference" name="reference" placeholder="Invoice, PO, etc.">
                                    </div>

                                    <div class="mb-3">
                                          <label for="stock_in_notes" class="form-label">Notes</label>
                                          <textarea class="form-control" id="stock_in_notes" name="notes" rows="3" placeholder="Additional notes about this stock in"></textarea>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">
                                          <i class="fas fa-plus-circle me-2"></i>Add Stock
                                    </button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Stock Out Modal -->
      <div class="modal fade" id="stockOutModal" tabindex="-1" aria-labelledby="stockOutModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="stockOutModalLabel">
                                    <i class="fas fa-minus-circle text-danger me-2"></i>Stock Out
                              </h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" class="form-modern">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="stock_out">

                                    <div class="mb-3">
                                          <label for="stock_out_product_id" class="form-label">Product *</label>
                                          <select class="form-select" id="stock_out_product_id" name="product_id" required>
                                                <option value="">Select Product</option>
                                                <?php foreach ($products as $product): ?>
                                                      <option value="<?php echo $product['id']; ?>">
                                                            <?php echo htmlspecialchars($product['name']); ?>
                                                            (<?php echo htmlspecialchars($product['product_code'] ?? 'N/A'); ?>)
                                                      </option>
                                                <?php endforeach; ?>
                                          </select>
                                    </div>

                                    <div class="mb-3">
                                          <label for="stock_out_quantity" class="form-label">Quantity *</label>
                                          <input type="number" class="form-control" id="stock_out_quantity" name="quantity" min="1" required>
                                    </div>

                                    <div class="mb-3">
                                          <label for="reason" class="form-label">Reason *</label>
                                          <select class="form-select" id="reason" name="reason" required>
                                                <option value="">Select Reason</option>
                                                <option value="damage">Damage/Loss</option>
                                                <option value="expiry">Expiry</option>
                                                <option value="theft">Theft</option>
                                                <option value="quality_control">Quality Control</option>
                                                <option value="transfer">Transfer</option>
                                                <option value="other">Other</option>
                                          </select>
                                    </div>

                                    <div class="mb-3">
                                          <label for="stock_out_reference" class="form-label">Reference</label>
                                          <input type="text" class="form-control" id="stock_out_reference" name="reference" placeholder="Document reference">
                                    </div>

                                    <div class="mb-3">
                                          <label for="stock_out_notes" class="form-label">Notes</label>
                                          <textarea class="form-control" id="stock_out_notes" name="notes" rows="3" placeholder="Additional notes about this stock out"></textarea>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">
                                          <i class="fas fa-minus-circle me-2"></i>Remove Stock
                                    </button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <script>
            function exportMovements() {
                  // Export all movement data
                  const table = document.getElementById('movementsTable');
                  const html = table.outerHTML;
                  const url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
                  const downloadLink = document.createElement("a");
                  document.body.appendChild(downloadLink);
                  downloadLink.href = url;
                  downloadLink.download = 'stock_movements_report.xls';
                  downloadLink.click();
                  document.body.removeChild(downloadLink);
            }

            function exportTable(tableId, filename) {
                  const table = document.getElementById(tableId);
                  const html = table.outerHTML;
                  const url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
                  const downloadLink = document.createElement("a");
                  document.body.appendChild(downloadLink);
                  downloadLink.href = url;
                  downloadLink.download = filename + '.xls';
                  downloadLink.click();
                  document.body.removeChild(downloadLink);
            }

            function printPage() {
                  window.print();
            }

            // Show current stock when product is selected in stock out modal
            document.getElementById('stock_out_product_id').addEventListener('change', function() {
                  const productId = this.value;
                  if (productId) {
                        // You could add an AJAX call here to get current stock and show it
                        // For now, we'll just enable the quantity field
                        document.getElementById('stock_out_quantity').disabled = false;
                  } else {
                        document.getElementById('stock_out_quantity').disabled = true;
                  }
            });
      </script>
</body>

</html>