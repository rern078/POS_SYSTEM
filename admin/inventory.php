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

// Handle inventory operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                  case 'update_stock':
                        $product_id = intval($_POST['product_id']);
                        $new_quantity = intval($_POST['new_quantity']);
                        $adjustment_type = $_POST['adjustment_type'];
                        $notes = trim($_POST['notes']);

                        if ($new_quantity < 0) {
                              $error = 'Stock quantity cannot be negative.';
                        } else {
                              // Get current stock
                              $stmt = $pdo->prepare("SELECT stock_quantity, name FROM products WHERE id = ?");
                              $stmt->execute([$product_id]);
                              $product = $stmt->fetch(PDO::FETCH_ASSOC);

                              if ($product) {
                                    // Update stock
                                    $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                                    if ($stmt->execute([$new_quantity, $product_id])) {
                                          // Log the adjustment (try to insert, but don't fail if table doesn't exist)
                                          try {
                                                $stmt = $pdo->prepare("INSERT INTO inventory_adjustments (product_id, adjustment_type, old_quantity, new_quantity, notes, adjusted_by) VALUES (?, ?, ?, ?, ?, ?)");
                                                $stmt->execute([$product_id, $adjustment_type, $product['stock_quantity'], $new_quantity, $notes, $_SESSION['user_id']]);
                                          } catch (PDOException $e) {
                                                // Table might not exist yet, but stock update was successful
                                                // This is not a critical error
                                          }

                                          $message = 'Stock updated successfully!';
                                    } else {
                                          $error = 'Failed to update stock.';
                                    }
                              } else {
                                    $error = 'Product not found.';
                              }
                        }
                        break;

                  case 'bulk_update':
                        $product_ids = $_POST['product_ids'] ?? [];
                        $adjustment_type = $_POST['bulk_adjustment_type'];
                        $adjustment_value = intval($_POST['bulk_adjustment_value']);
                        $bulk_notes = trim($_POST['bulk_notes']);

                        if (empty($product_ids)) {
                              $error = 'Please select at least one product.';
                        } else {
                              $success_count = 0;
                              foreach ($product_ids as $product_id) {
                                    $product_id = intval($product_id);

                                    // Get current stock
                                    $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                                    $stmt->execute([$product_id]);
                                    $product = $stmt->fetch(PDO::FETCH_ASSOC);

                                    if ($product) {
                                          $old_quantity = $product['stock_quantity'];
                                          $new_quantity = $adjustment_type === 'add' ? $old_quantity + $adjustment_value : $old_quantity - $adjustment_value;

                                          if ($new_quantity >= 0) {
                                                // Update stock
                                                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                                                if ($stmt->execute([$new_quantity, $product_id])) {
                                                      // Log the adjustment (try to insert, but don't fail if table doesn't exist)
                                                      try {
                                                            $stmt = $pdo->prepare("INSERT INTO inventory_adjustments (product_id, adjustment_type, old_quantity, new_quantity, notes, adjusted_by) VALUES (?, ?, ?, ?, ?, ?)");
                                                            $stmt->execute([$product_id, $adjustment_type, $old_quantity, $new_quantity, $bulk_notes, $_SESSION['user_id']]);
                                                      } catch (PDOException $e) {
                                                            // Table might not exist yet, but stock update was successful
                                                            // This is not a critical error
                                                      }

                                                      $success_count++;
                                                }
                                          }
                                    }
                              }

                              if ($success_count > 0) {
                                    $message = "Successfully updated stock for $success_count product(s)!";
                              } else {
                                    $error = 'Failed to update any products.';
                              }
                        }
                        break;

                  case 'stock_in':
                        $product_id = intval($_POST['product_id']);
                        $quantity = intval($_POST['quantity']);
                        $supplier = trim($_POST['supplier']);
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
                                          $new_quantity = $old_quantity + $quantity;

                                          // Update product stock
                                          $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                                          if ($stmt->execute([$new_quantity, $product_id])) {
                                                // Log the stock in
                                                $log_notes = "Stock In: $quantity units";
                                                if ($supplier) $log_notes .= " from $supplier";
                                                if ($reference) $log_notes .= ". Reference: $reference";
                                                if ($notes) $log_notes .= ". Notes: $notes";

                                                $stmt = $pdo->prepare("INSERT INTO inventory_adjustments (product_id, adjustment_type, old_quantity, new_quantity, notes, adjusted_by) VALUES (?, 'stock_in', ?, ?, ?, ?)");
                                                $stmt->execute([$product_id, $old_quantity, $new_quantity, $log_notes, $_SESSION['user_id']]);

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
                                                      $log_notes = "Stock Out: $quantity units. Reason: $reason";
                                                      if ($reference) $log_notes .= ". Reference: $reference";
                                                      if ($notes) $log_notes .= ". Notes: $notes";

                                                      $stmt = $pdo->prepare("INSERT INTO inventory_adjustments (product_id, adjustment_type, old_quantity, new_quantity, notes, adjusted_by) VALUES (?, 'stock_out', ?, ?, ?, ?)");
                                                      $stmt->execute([$product_id, $old_quantity, $new_quantity, $log_notes, $_SESSION['user_id']]);

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

// Get inventory data with search and filter
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$type_filter = $_GET['type_filter'] ?? '';
$stock_status = $_GET['stock_status'] ?? '';
$sort = $_GET['sort'] ?? 'name';
$order = $_GET['order'] ?? 'ASC';

// Pagination settings
$items_per_page = 15;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

$whereConditions = [];
$params = [];

if (!empty($search)) {
      $whereConditions[] = "(p.name LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      $params[] = "%$search%";
}

if (!empty($category)) {
      $whereConditions[] = "p.category = ?";
      $params[] = $category;
}

if (!empty($type_filter)) {
      $whereConditions[] = "p.type = ?";
      $params[] = $type_filter;
}

if (!empty($stock_status)) {
      switch ($stock_status) {
            case 'in_stock':
                  $whereConditions[] = "p.stock_quantity > 0";
                  break;
            case 'out_of_stock':
                  $whereConditions[] = "p.stock_quantity = 0";
                  break;
            case 'low_stock':
                  $whereConditions[] = "p.stock_quantity BETWEEN 1 AND 10";
                  break;
            case 'critical':
                  $whereConditions[] = "p.stock_quantity <= 5";
                  break;
      }
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM products p $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total_items = $countStmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

// Get paginated inventory
$sql = "SELECT p.*, 
              COALESCE(SUM(oi.quantity), 0) as total_sold,
              p.created_at as last_updated
        FROM products p 
        LEFT JOIN order_items oi ON p.id = oi.product_id 
        $whereClause 
        GROUP BY p.id 
        ORDER BY $sort $order 
        LIMIT $items_per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter dropdown
$categories = [];
$catStmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
while ($row = $catStmt->fetch(PDO::FETCH_ASSOC)) {
      $categories[] = $row['category'];
}

// Get inventory statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_products,
    SUM(stock_quantity) as total_stock,
    COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
    COUNT(CASE WHEN stock_quantity BETWEEN 1 AND 10 THEN 1 END) as low_stock,
    COUNT(CASE WHEN stock_quantity <= 5 THEN 1 END) as critical_stock,
    AVG(stock_quantity) as avg_stock
FROM products");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <?php include 'include/title.inc.php'; ?>
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
                                                <li class="breadcrumb-item active" aria-current="page">Inventory</li>
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
                                                      <i class="fas fa-warehouse"></i>
                                                      Inventory Management
                                                </h1>
                                                <p class="text-muted mb-0">Monitor and manage your product inventory levels.</p>
                                          </div>
                                          <div>
                                                <button class="btn btn-modern btn-success me-2" data-bs-toggle="modal" data-bs-target="#quickStockInModal">
                                                      <i class="fas fa-plus me-2"></i>Quick Stock In
                                                </button>
                                                <button class="btn btn-modern btn-danger me-2" data-bs-toggle="modal" data-bs-target="#quickStockOutModal">
                                                      <i class="fas fa-minus me-2"></i>Quick Stock Out
                                                </button>
                                                <button class="btn btn-modern btn-warning me-2" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                                                      <i class="fas fa-edit me-2"></i>Bulk Update
                                                </button>
                                                <button class="btn btn-modern btn-primary" onclick="exportInventory()">
                                                      <i class="fas fa-download me-2"></i>Export
                                                </button>
                                                <a href="stock_movements.php" class="btn btn-modern btn-info">
                                                      <i class="fas fa-exchange-alt me-2"></i>View Movements
                                                </a>
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

                        <!-- Inventory Statistics -->
                        <div class="row mb-4">
                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Total Products</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-box"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo number_format($stats['total_products']); ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> Active inventory
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card success">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Total Stock</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-cubes"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo number_format($stats['total_stock']); ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> Units available
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card warning">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Low Stock Items</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-exclamation-triangle"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo number_format($stats['low_stock']); ?></h2>
                                          <div class="stat-card-change negative">
                                                <i class="fas fa-arrow-down"></i> Need attention
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card danger">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Out of Stock</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-times-circle"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo number_format($stats['out_of_stock']); ?></h2>
                                          <div class="stat-card-change negative">
                                                <i class="fas fa-arrow-down"></i> Need restocking
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
                                                <label for="search" class="form-label">Search Products</label>
                                                <input type="text" class="form-control" id="search" name="search"
                                                      placeholder="Search by name, code, or barcode..."
                                                      value="<?php echo htmlspecialchars($search); ?>">
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
                                                <label for="type_filter" class="form-label">Type</label>
                                                <select class="form-select" id="type_filter" name="type_filter">
                                                      <option value="">All Types</option>
                                                      <option value="Food" <?php echo (isset($_GET['type_filter']) && $_GET['type_filter'] === 'Food') ? 'selected' : ''; ?>>Food</option>
                                                      <option value="Clothes" <?php echo (isset($_GET['type_filter']) && $_GET['type_filter'] === 'Clothes') ? 'selected' : ''; ?>>Clothes</option>
                                                </select>
                                          </div>
                                          <div class="col-md-2">
                                                <label for="stock_status" class="form-label">Stock Status</label>
                                                <select class="form-select" id="stock_status" name="stock_status">
                                                      <option value="">All Status</option>
                                                      <option value="in_stock" <?php echo $stock_status === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                                                      <option value="low_stock" <?php echo $stock_status === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                                                      <option value="critical" <?php echo $stock_status === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                                      <option value="out_of_stock" <?php echo $stock_status === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                                </select>
                                          </div>
                                          <div class="col-md-2">
                                                <label for="sort" class="form-label">Sort By</label>
                                                <select class="form-select" id="sort" name="sort">
                                                      <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name</option>
                                                      <option value="stock_quantity" <?php echo $sort === 'stock_quantity' ? 'selected' : ''; ?>>Stock</option>
                                                      <option value="category" <?php echo $sort === 'category' ? 'selected' : ''; ?>>Category</option>
                                                      <option value="last_updated" <?php echo $sort === 'last_updated' ? 'selected' : ''; ?>>Last Updated</option>
                                                </select>
                                          </div>
                                          <div class="col-md-2">
                                                <label for="order" class="form-label">Order</label>
                                                <select class="form-select" id="order" name="order">
                                                      <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                                      <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                                </select>
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

                        <!-- Inventory Table -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                          <h6 class="content-card-title">
                                                <i class="fas fa-list"></i>
                                                Inventory List
                                          </h6>
                                          <div>
                                                <button class="btn btn-modern btn-outline-secondary me-2" onclick="printPage()">
                                                      <i class="fas fa-print me-1"></i>Print
                                                </button>
                                                <button class="btn btn-modern btn-outline-secondary" onclick="exportTable('inventoryTable', 'inventory')">
                                                      <i class="fas fa-download me-1"></i>Export Table
                                                </button>
                                          </div>
                                    </div>
                              </div>
                              <div class="content-card-body">
                                    <div class="table-responsive">
                                          <table class="table table-modern" id="inventoryTable" width="100%" cellspacing="0">
                                                <thead>
                                                      <tr>
                                                            <th>
                                                                  <input type="checkbox" id="selectAll" class="form-check-input">
                                                            </th>
                                                            <th>Product</th>
                                                            <th>Code</th>
                                                            <th>Category</th>
                                                            <th>Type</th>
                                                            <th>Current Stock</th>
                                                            <th>Status</th>
                                                            <th>Total Sold</th>
                                                            <th>Last Updated</th>
                                                            <th>Actions</th>
                                                      </tr>
                                                </thead>
                                                <tbody>
                                                      <?php foreach ($inventory as $item):
                                                            $img_path = !empty($item['image_path']) ? '../' . htmlspecialchars($item['image_path']) : '../images/placeholder.jpg';
                                                            $stock_status_class = $item['stock_quantity'] == 0 ? 'danger' : ($item['stock_quantity'] <= 5 ? 'warning' : ($item['stock_quantity'] <= 10 ? 'info' : 'success'));
                                                            $stock_status_text = $item['stock_quantity'] == 0 ? 'Out of Stock' : ($item['stock_quantity'] <= 5 ? 'Critical' : ($item['stock_quantity'] <= 10 ? 'Low Stock' : 'In Stock'));
                                                      ?>
                                                            <tr>
                                                                  <td>
                                                                        <input type="checkbox" name="product_ids[]" value="<?php echo $item['id']; ?>" class="form-check-input product-checkbox">
                                                                  </td>
                                                                  <td>
                                                                        <div class="d-flex align-items-center">
                                                                              <img src="<?php echo $img_path; ?>"
                                                                                    alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                                                    class="img-thumbnail me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                                              <div>
                                                                                    <div class="fw-medium"><?php echo htmlspecialchars($item['name']); ?></div>
                                                                                    <small class="text-muted">ID: <?php echo $item['id']; ?></small>
                                                                              </div>
                                                                        </div>
                                                                  </td>
                                                                  <td>
                                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($item['product_code'] ?? 'N/A'); ?></span>
                                                                  </td>
                                                                  <td><?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?></td>
                                                                  <td>
                                                                        <span class="badge bg-<?php echo ($item['type'] ?? 'Food') === 'Food' ? 'warning' : 'info'; ?>">
                                                                              <?php echo htmlspecialchars($item['type'] ?? 'Food'); ?>
                                                                        </span>
                                                                  </td>
                                                                  <td>
                                                                        <span class="fw-bold text-<?php echo $stock_status_class; ?>">
                                                                              <?php echo number_format($item['stock_quantity']); ?>
                                                                        </span>
                                                                  </td>
                                                                  <td>
                                                                        <span class="badge bg-<?php echo $stock_status_class; ?>">
                                                                              <i class="fas fa-<?php
                                                                                                echo $item['stock_quantity'] == 0 ? 'times-circle' : ($item['stock_quantity'] <= 5 ? 'exclamation-triangle' : ($item['stock_quantity'] <= 10 ? 'info-circle' : 'check-circle'));
                                                                                                ?> me-1"></i>
                                                                              <?php echo $stock_status_text; ?>
                                                                        </span>
                                                                  </td>
                                                                  <td>
                                                                        <span class="badge bg-info"><?php echo number_format($item['total_sold']); ?></span>
                                                                  </td>
                                                                  <td>
                                                                        <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($item['last_updated'])); ?></small>
                                                                  </td>
                                                                  <td>
                                                                        <div class="btn-group" role="group">
                                                                              <button class="btn btn-sm btn-primary" onclick="updateStock(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                                                                    <i class="fas fa-edit"></i>
                                                                              </button>
                                                                              <button class="btn btn-sm btn-info" onclick="viewHistory(<?php echo $item['id']; ?>)">
                                                                                    <i class="fas fa-history"></i>
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
                                          <nav aria-label="Inventory pagination" class="mt-4">
                                                <ul class="pagination justify-content-center">
                                                      <?php if ($current_page > 1): ?>
                                                            <li class="page-item">
                                                                  <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&type_filter=<?php echo urlencode($type_filter); ?>&stock_status=<?php echo urlencode($stock_status); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">
                                                                        Previous
                                                                  </a>
                                                            </li>
                                                      <?php endif; ?>

                                                      <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                                                  <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&type_filter=<?php echo urlencode($type_filter); ?>&stock_status=<?php echo urlencode($stock_status); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">
                                                                        <?php echo $i; ?>
                                                                  </a>
                                                            </li>
                                                      <?php endfor; ?>

                                                      <?php if ($current_page < $total_pages): ?>
                                                            <li class="page-item">
                                                                  <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&type_filter=<?php echo urlencode($type_filter); ?>&stock_status=<?php echo urlencode($stock_status); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">
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

      <!-- Update Stock Modal -->
      <div class="modal fade" id="updateStockModal" tabindex="-1" aria-labelledby="updateStockModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="updateStockModalLabel">Update Stock</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" class="form-modern">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="update_stock">
                                    <input type="hidden" name="product_id" id="update_product_id">

                                    <div class="mb-3">
                                          <label class="form-label">Product</label>
                                          <div class="form-control-plaintext" id="update_product_name"></div>
                                    </div>

                                    <div class="mb-3">
                                          <label for="new_quantity" class="form-label">New Stock Quantity *</label>
                                          <input type="number" class="form-control" id="new_quantity" name="new_quantity" min="0" required>
                                    </div>

                                    <div class="mb-3">
                                          <label for="adjustment_type" class="form-label">Adjustment Type</label>
                                          <select class="form-select" id="adjustment_type" name="adjustment_type" required>
                                                <option value="manual">Manual Adjustment</option>
                                                <option value="restock">Restock</option>
                                                <option value="damage">Damage/Loss</option>
                                                <option value="correction">Correction</option>
                                          </select>
                                    </div>

                                    <div class="mb-3">
                                          <label for="notes" class="form-label">Notes</label>
                                          <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Optional notes about this adjustment"></textarea>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Stock</button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Quick Stock In Modal -->
      <div class="modal fade" id="quickStockInModal" tabindex="-1" aria-labelledby="quickStockInModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="quickStockInModalLabel">
                                    <i class="fas fa-plus-circle text-success me-2"></i>Quick Stock In
                              </h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" class="form-modern">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="stock_in">

                                    <div class="mb-3">
                                          <label for="quick_stock_in_product_id" class="form-label">Product *</label>
                                          <select class="form-select" id="quick_stock_in_product_id" name="product_id" required>
                                                <option value="">Select Product</option>
                                                <?php foreach ($inventory as $item): ?>
                                                      <option value="<?php echo $item['id']; ?>">
                                                            <?php echo htmlspecialchars($item['name']); ?>
                                                            (Current: <?php echo $item['stock_quantity']; ?>)
                                                      </option>
                                                <?php endforeach; ?>
                                          </select>
                                    </div>

                                    <div class="mb-3">
                                          <label for="quick_quantity" class="form-label">Quantity to Add *</label>
                                          <input type="number" class="form-control" id="quick_quantity" name="quantity" min="1" required>
                                    </div>

                                    <div class="mb-3">
                                          <label for="quick_supplier" class="form-label">Supplier</label>
                                          <input type="text" class="form-control" id="quick_supplier" name="supplier" placeholder="Supplier name">
                                    </div>

                                    <div class="mb-3">
                                          <label for="quick_reference" class="form-label">Reference</label>
                                          <input type="text" class="form-control" id="quick_reference" name="reference" placeholder="Invoice, PO, etc.">
                                    </div>

                                    <div class="mb-3">
                                          <label for="quick_stock_in_notes" class="form-label">Notes</label>
                                          <textarea class="form-control" id="quick_stock_in_notes" name="notes" rows="2" placeholder="Optional notes"></textarea>
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

      <!-- Quick Stock Out Modal -->
      <div class="modal fade" id="quickStockOutModal" tabindex="-1" aria-labelledby="quickStockOutModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="quickStockOutModalLabel">
                                    <i class="fas fa-minus-circle text-danger me-2"></i>Quick Stock Out
                              </h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" class="form-modern">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="stock_out">

                                    <div class="mb-3">
                                          <label for="quick_stock_out_product_id" class="form-label">Product *</label>
                                          <select class="form-select" id="quick_stock_out_product_id" name="product_id" required>
                                                <option value="">Select Product</option>
                                                <?php foreach ($inventory as $item): ?>
                                                      <option value="<?php echo $item['id']; ?>" data-stock="<?php echo $item['stock_quantity']; ?>">
                                                            <?php echo htmlspecialchars($item['name']); ?>
                                                            (Current: <?php echo $item['stock_quantity']; ?>)
                                                      </option>
                                                <?php endforeach; ?>
                                          </select>
                                    </div>

                                    <div class="mb-3">
                                          <label for="quick_stock_out_quantity" class="form-label">Quantity to Remove *</label>
                                          <input type="number" class="form-control" id="quick_stock_out_quantity" name="quantity" min="1" required>
                                          <div class="form-text">Available stock: <span id="available_stock">-</span></div>
                                    </div>

                                    <div class="mb-3">
                                          <label for="quick_reason" class="form-label">Reason *</label>
                                          <select class="form-select" id="quick_reason" name="reason" required>
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
                                          <label for="quick_stock_out_reference" class="form-label">Reference</label>
                                          <input type="text" class="form-control" id="quick_stock_out_reference" name="reference" placeholder="Document reference">
                                    </div>

                                    <div class="mb-3">
                                          <label for="quick_stock_out_notes" class="form-label">Notes</label>
                                          <textarea class="form-control" id="quick_stock_out_notes" name="notes" rows="2" placeholder="Optional notes"></textarea>
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

      <!-- Bulk Update Modal -->
      <div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-labelledby="bulkUpdateModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="bulkUpdateModalLabel">Bulk Stock Update</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" class="form-modern">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="bulk_update">

                                    <div class="alert alert-info">
                                          <i class="fas fa-info-circle me-2"></i>
                                          Select products from the table above, then choose how to adjust their stock levels.
                                    </div>

                                    <div class="mb-3">
                                          <label for="bulk_adjustment_type" class="form-label">Adjustment Type *</label>
                                          <select class="form-select" id="bulk_adjustment_type" name="bulk_adjustment_type" required>
                                                <option value="">Select Type</option>
                                                <option value="add">Add Stock</option>
                                                <option value="subtract">Subtract Stock</option>
                                          </select>
                                    </div>

                                    <div class="mb-3">
                                          <label for="bulk_adjustment_value" class="form-label">Adjustment Value *</label>
                                          <input type="number" class="form-control" id="bulk_adjustment_value" name="bulk_adjustment_value" min="1" required placeholder="Enter quantity">
                                    </div>

                                    <div class="mb-3">
                                          <label for="bulk_notes" class="form-label">Notes</label>
                                          <textarea class="form-control" id="bulk_notes" name="bulk_notes" rows="3" placeholder="Notes for this bulk adjustment"></textarea>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-warning">Bulk Update</button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <script>
            // Select all functionality
            document.getElementById('selectAll').addEventListener('change', function() {
                  const checkboxes = document.querySelectorAll('.product-checkbox');
                  checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                  });
            });

            function updateStock(item) {
                  document.getElementById('update_product_id').value = item.id;
                  document.getElementById('update_product_name').textContent = item.name;
                  document.getElementById('new_quantity').value = item.stock_quantity;
                  document.getElementById('adjustment_type').value = 'manual';
                  document.getElementById('notes').value = '';

                  const updateModal = new bootstrap.Modal(document.getElementById('updateStockModal'));
                  updateModal.show();
            }

            function viewHistory(productId) {
                  // This would typically open a modal with the product's inventory history
                  alert('Inventory history for product ID: ' + productId + ' would be displayed here.');
            }

            function exportInventory() {
                  // Export all inventory data
                  const table = document.getElementById('inventoryTable');
                  const html = table.outerHTML;
                  const url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
                  const downloadLink = document.createElement("a");
                  document.body.appendChild(downloadLink);
                  downloadLink.href = url;
                  downloadLink.download = 'inventory_report.xls';
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

            // Show available stock when product is selected in quick stock out modal
            document.getElementById('quick_stock_out_product_id').addEventListener('change', function() {
                  const selectedOption = this.options[this.selectedIndex];
                  const availableStock = selectedOption.getAttribute('data-stock');
                  document.getElementById('available_stock').textContent = availableStock || '-';

                  // Set max value for quantity input
                  const quantityInput = document.getElementById('quick_stock_out_quantity');
                  quantityInput.max = availableStock;
            });
      </script>
</body>

</html>