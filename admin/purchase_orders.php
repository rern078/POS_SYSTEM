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
                  case 'add_po':
                        $po_number = trim($_POST['po_number']);
                        $vendor_id = intval($_POST['vendor_id']);
                        $order_date = $_POST['order_date'];
                        $expected_delivery_date = $_POST['expected_delivery_date'];
                        $subtotal = floatval($_POST['subtotal']);
                        $tax_amount = floatval($_POST['tax_amount']);
                        $shipping_amount = floatval($_POST['shipping_amount']);
                        $total_amount = floatval($_POST['total_amount']);
                        $payment_terms = trim($_POST['payment_terms']);
                        $notes = trim($_POST['notes']);
                        $items = $_POST['items'];

                        // Check if PO number already exists
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE po_number = ?");
                        $stmt->execute([$po_number]);
                        if ($stmt->fetchColumn() > 0) {
                              $error = 'Purchase order number already exists.';
                        } else {
                              try {
                                    $pdo->beginTransaction();

                                    // Insert purchase order
                                    $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_number, vendor_id, order_date, expected_delivery_date, subtotal, tax_amount, shipping_amount, total_amount, payment_terms, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                    $stmt->execute([$po_number, $vendor_id, $order_date, $expected_delivery_date, $subtotal, $tax_amount, $shipping_amount, $total_amount, $payment_terms, $notes, $_SESSION['user_id']]);
                                    $po_id = $pdo->lastInsertId();

                                    // Insert purchase order items
                                    $stmt = $pdo->prepare("INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");

                                    foreach ($items as $item) {
                                          if (!empty($item['product_id']) && $item['quantity'] > 0) {
                                                $total_price = floatval($item['quantity']) * floatval($item['unit_price']);
                                                $stmt->execute([
                                                      $po_id,
                                                      $item['product_id'],
                                                      $item['quantity'],
                                                      $item['unit_price'],
                                                      $total_price
                                                ]);
                                          }
                                    }

                                    $pdo->commit();
                                    $message = 'Purchase order created successfully!';
                              } catch (Exception $e) {
                                    $pdo->rollBack();
                                    $error = 'Error creating purchase order: ' . $e->getMessage();
                              }
                        }
                        break;

                  case 'update_po':
                        $po_id = intval($_POST['po_id']);
                        $vendor_id = intval($_POST['vendor_id']);
                        $order_date = $_POST['order_date'];
                        $expected_delivery_date = $_POST['expected_delivery_date'];
                        $subtotal = floatval($_POST['subtotal']);
                        $tax_amount = floatval($_POST['tax_amount']);
                        $shipping_amount = floatval($_POST['shipping_amount']);
                        $total_amount = floatval($_POST['total_amount']);
                        $payment_terms = trim($_POST['payment_terms']);
                        $notes = trim($_POST['notes']);
                        $items = $_POST['items'];

                        try {
                              $pdo->beginTransaction();

                              // Update purchase order
                              $stmt = $pdo->prepare("UPDATE purchase_orders SET vendor_id = ?, order_date = ?, expected_delivery_date = ?, subtotal = ?, tax_amount = ?, shipping_amount = ?, total_amount = ?, payment_terms = ?, notes = ? WHERE id = ?");
                              $stmt->execute([$vendor_id, $order_date, $expected_delivery_date, $subtotal, $tax_amount, $shipping_amount, $total_amount, $payment_terms, $notes, $po_id]);

                              // Delete existing items
                              $stmt = $pdo->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = ?");
                              $stmt->execute([$po_id]);

                              // Insert new items
                              $stmt = $pdo->prepare("INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");

                              foreach ($items as $item) {
                                    if (!empty($item['product_id']) && $item['quantity'] > 0) {
                                          $total_price = floatval($item['quantity']) * floatval($item['unit_price']);
                                          $stmt->execute([
                                                $po_id,
                                                $item['product_id'],
                                                $item['quantity'],
                                                $item['unit_price'],
                                                $total_price
                                          ]);
                                    }
                              }

                              $pdo->commit();
                              $message = 'Purchase order updated successfully!';
                        } catch (Exception $e) {
                              $pdo->rollBack();
                              $error = 'Error updating purchase order: ' . $e->getMessage();
                        }
                        break;

                  case 'update_status':
                        $po_id = intval($_POST['po_id']);
                        $status = $_POST['status'];

                        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
                        if ($stmt->execute([$status, $po_id])) {
                              $message = 'Purchase order status updated successfully!';
                        } else {
                              $error = 'Failed to update purchase order status.';
                        }
                        break;

                  case 'delete_po':
                        $po_id = intval($_POST['po_id']);

                        try {
                              $pdo->beginTransaction();

                              // Delete purchase order items first
                              $stmt = $pdo->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = ?");
                              $stmt->execute([$po_id]);

                              // Delete purchase order
                              $stmt = $pdo->prepare("DELETE FROM purchase_orders WHERE id = ?");
                              $stmt->execute([$po_id]);

                              $pdo->commit();
                              $message = 'Purchase order deleted successfully!';
                        } catch (Exception $e) {
                              $pdo->rollBack();
                              $error = 'Error deleting purchase order: ' . $e->getMessage();
                        }
                        break;
            }
      }
}

// Get vendors for dropdown
$stmt = $pdo->prepare("SELECT id, vendor_code, vendor_name FROM vendors WHERE is_active = 1 ORDER BY vendor_name");
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products for dropdown
$stmt = $pdo->prepare("SELECT id, product_code, name, price FROM products WHERE stock_quantity >= 0 ORDER BY name");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get purchase orders with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$vendor_id = isset($_GET['vendor_id']) ? $_GET['vendor_id'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
      $where_conditions[] = "(po.po_number LIKE ? OR po.notes LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
}

if (!empty($status)) {
      $where_conditions[] = "po.status = ?";
      $params[] = $status;
}

if (!empty($vendor_id)) {
      $where_conditions[] = "po.vendor_id = ?";
      $params[] = $vendor_id;
}

if (!empty($date_from)) {
      $where_conditions[] = "po.order_date >= ?";
      $params[] = $date_from;
}

if (!empty($date_to)) {
      $where_conditions[] = "po.order_date <= ?";
      $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) FROM purchase_orders po $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_pos = $stmt->fetchColumn();
$total_pages = ceil($total_pos / $limit);

// Get purchase orders
$sql = "SELECT po.*, v.vendor_name, v.vendor_code, u.username as created_by_name,
        COUNT(poi.id) as item_count
        FROM purchase_orders po 
        LEFT JOIN vendors v ON po.vendor_id = v.id
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
        $where_clause 
        GROUP BY po.id 
        ORDER BY po.order_date DESC, po.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$purchase_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get PO statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_pos,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_pos,
        COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_pos,
        COUNT(CASE WHEN status = 'received' THEN 1 END) as received_pos,
        SUM(total_amount) as total_value
    FROM purchase_orders 
    WHERE MONTH(order_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(order_date) = YEAR(CURRENT_DATE())
");
$stmt->execute();
$po_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Purchase Orders - POS Admin</title>

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
                                                <li class="breadcrumb-item active" aria-current="page">Purchase Orders</li>
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
                                          <i class="fas fa-shopping-cart"></i>
                                          Purchase Orders
                                    </h1>
                                    <p class="text-muted mb-0">Manage purchase orders and inventory procurement</p>
                              </div>
                        </div>

                        <!-- Action Button -->
                        <div class="row mb-4">
                              <div class="col-12">
                                    <div class="d-flex justify-content-end">
                                          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPOModal">
                                                <i class="fas fa-plus me-2"></i>New Purchase Order
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

                        <!-- PO Statistics -->
                        <div class="row mb-4">
                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Total POs</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-shopping-cart"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo $po_stats['total_pos'] ?? 0; ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> This Month
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card info">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Draft POs</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-edit"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo $po_stats['draft_pos'] ?? 0; ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> This Month
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card warning">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Sent POs</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-paper-plane"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo $po_stats['sent_pos'] ?? 0; ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> This Month
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card success">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Total Value</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-dollar-sign"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value">$<?php echo number_format($po_stats['total_value'] ?? 0, 2); ?></h2>
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
                                                                  placeholder="PO number, notes...">
                                                      </div>
                                                      <div class="col-md-2">
                                                            <label for="status" class="form-label">Status</label>
                                                            <select class="form-select" id="status" name="status">
                                                                  <option value="">All Status</option>
                                                                  <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                                  <option value="sent" <?php echo $status === 'sent' ? 'selected' : ''; ?>>Sent</option>
                                                                  <option value="received" <?php echo $status === 'received' ? 'selected' : ''; ?>>Received</option>
                                                                  <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                            </select>
                                                      </div>
                                                      <div class="col-md-2">
                                                            <label for="vendor_id" class="form-label">Vendor</label>
                                                            <select class="form-select" id="vendor_id" name="vendor_id">
                                                                  <option value="">All Vendors</option>
                                                                  <?php foreach ($vendors as $vendor): ?>
                                                                        <option value="<?php echo $vendor['id']; ?>"
                                                                              <?php echo $vendor_id == $vendor['id'] ? 'selected' : ''; ?>>
                                                                              <?php echo htmlspecialchars($vendor['vendor_name']); ?>
                                                                        </option>
                                                                  <?php endforeach; ?>
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
                                                                  <a href="purchase_orders.php" class="btn btn-outline-secondary">
                                                                        <i class="fas fa-times"></i>
                                                                  </a>
                                                            </div>
                                                      </div>
                                                </form>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Purchase Orders Table -->
                        <div class="row mb-4">
                              <div class="col-12">
                                    <div class="card shadow">
                                          <div class="card-header py-3">
                                                <h6 class="m-0 font-weight-bold text-primary">Purchase Orders</h6>
                                          </div>
                                          <div class="card-body">
                                                <div class="table-responsive">
                                                      <table class="table table-bordered" id="purchaseOrdersTable" width="100%" cellspacing="0">
                                                            <thead>
                                                                  <tr>
                                                                        <th>PO Number</th>
                                                                        <th>Vendor</th>
                                                                        <th>Order Date</th>
                                                                        <th>Expected Delivery</th>
                                                                        <th>Items</th>
                                                                        <th>Total Amount</th>
                                                                        <th>Status</th>
                                                                        <th>Created By</th>
                                                                        <th>Actions</th>
                                                                  </tr>
                                                            </thead>
                                                            <tbody>
                                                                  <?php foreach ($purchase_orders as $po): ?>
                                                                        <tr>
                                                                              <td>
                                                                                    <strong><?php echo htmlspecialchars($po['po_number']); ?></strong>
                                                                              </td>
                                                                              <td>
                                                                                    <?php echo htmlspecialchars($po['vendor_name']); ?>
                                                                                    <br><small class="text-muted"><?php echo htmlspecialchars($po['vendor_code']); ?></small>
                                                                              </td>
                                                                              <td><?php echo date('M d, Y', strtotime($po['order_date'])); ?></td>
                                                                              <td>
                                                                                    <?php echo $po['expected_delivery_date'] ? date('M d, Y', strtotime($po['expected_delivery_date'])) : 'N/A'; ?>
                                                                              </td>
                                                                              <td class="text-center">
                                                                                    <span class="badge bg-info"><?php echo $po['item_count']; ?> items</span>
                                                                              </td>
                                                                              <td class="text-end">
                                                                                    <strong>$<?php echo number_format($po['total_amount'], 2); ?></strong>
                                                                                    <?php if ($po['tax_amount'] > 0 || $po['shipping_amount'] > 0): ?>
                                                                                          <br><small class="text-muted">
                                                                                                Sub: $<?php echo number_format($po['subtotal'], 2); ?>
                                                                                                <?php if ($po['tax_amount'] > 0): ?> | Tax: $<?php echo number_format($po['tax_amount'], 2); ?><?php endif; ?>
                                                                                                      <?php if ($po['shipping_amount'] > 0): ?> | Ship: $<?php echo number_format($po['shipping_amount'], 2); ?><?php endif; ?>
                                                                                          </small>
                                                                                    <?php endif; ?>
                                                                              </td>
                                                                              <td>
                                                                                    <span class="badge bg-<?php
                                                                                                            echo $po['status'] == 'received' ? 'success' : ($po['status'] == 'sent' ? 'warning' : ($po['status'] == 'draft' ? 'secondary' : 'danger'));
                                                                                                            ?>">
                                                                                          <?php echo ucfirst($po['status']); ?>
                                                                                    </span>
                                                                              </td>
                                                                              <td><?php echo htmlspecialchars($po['created_by_name']); ?></td>
                                                                              <td>
                                                                                    <div class="btn-group btn-group-sm">
                                                                                          <button class="btn btn-outline-primary" onclick="viewPO(<?php echo $po['id']; ?>)">
                                                                                                <i class="fas fa-eye"></i>
                                                                                          </button>
                                                                                          <button class="btn btn-outline-warning" onclick="editPO(<?php echo $po['id']; ?>)">
                                                                                                <i class="fas fa-edit"></i>
                                                                                          </button>
                                                                                          <button class="btn btn-outline-info" onclick="updateStatus(<?php echo $po['id']; ?>, '<?php echo $po['status']; ?>')">
                                                                                                <i class="fas fa-cog"></i>
                                                                                          </button>
                                                                                          <button class="btn btn-outline-danger" onclick="deletePO(<?php echo $po['id']; ?>)">
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
                                                                  Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_pos); ?> of <?php echo $total_pos; ?> purchase orders
                                                            </div>
                                                            <nav aria-label="Purchase orders pagination">
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
      </div>

      <!-- Add Purchase Order Modal -->
      <div class="modal fade" id="addPOModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">
                                    <i class="fas fa-plus me-2"></i>New Purchase Order
                              </h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                              <input type="hidden" name="action" value="add_po">
                              <div class="modal-body">
                                    <div class="row">
                                          <div class="col-md-3">
                                                <div class="mb-3">
                                                      <label for="po_number" class="form-label">PO Number *</label>
                                                      <input type="text" class="form-control" id="po_number" name="po_number"
                                                            placeholder="e.g., PO-2024-001" required>
                                                </div>
                                          </div>
                                          <div class="col-md-3">
                                                <div class="mb-3">
                                                      <label for="vendor_id" class="form-label">Vendor *</label>
                                                      <select class="form-select" id="vendor_id" name="vendor_id" required>
                                                            <option value="">Select Vendor</option>
                                                            <?php foreach ($vendors as $vendor): ?>
                                                                  <option value="<?php echo $vendor['id']; ?>">
                                                                        <?php echo htmlspecialchars($vendor['vendor_name']); ?>
                                                                  </option>
                                                            <?php endforeach; ?>
                                                      </select>
                                                </div>
                                          </div>
                                          <div class="col-md-3">
                                                <div class="mb-3">
                                                      <label for="order_date" class="form-label">Order Date *</label>
                                                      <input type="date" class="form-control" id="order_date" name="order_date"
                                                            value="<?php echo date('Y-m-d'); ?>" required>
                                                </div>
                                          </div>
                                          <div class="col-md-3">
                                                <div class="mb-3">
                                                      <label for="expected_delivery_date" class="form-label">Expected Delivery</label>
                                                      <input type="date" class="form-control" id="expected_delivery_date" name="expected_delivery_date">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="payment_terms" class="form-label">Payment Terms</label>
                                                      <input type="text" class="form-control" id="payment_terms" name="payment_terms"
                                                            placeholder="e.g., Net 30">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="notes" class="form-label">Notes</label>
                                                      <textarea class="form-control" id="notes" name="notes" rows="2"
                                                            placeholder="Additional notes"></textarea>
                                                </div>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-12">
                                                <h6>Purchase Order Items</h6>
                                                <div id="po-items">
                                                      <div class="row po-item mb-2">
                                                            <div class="col-md-4">
                                                                  <select class="form-select" name="items[0][product_id]" required>
                                                                        <option value="">Select Product</option>
                                                                        <?php foreach ($products as $product): ?>
                                                                              <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>">
                                                                                    <?php echo $product['product_code'] . ' - ' . $product['name']; ?>
                                                                              </option>
                                                                        <?php endforeach; ?>
                                                                  </select>
                                                            </div>
                                                            <div class="col-md-2">
                                                                  <input type="number" class="form-control" name="items[0][quantity]"
                                                                        placeholder="Qty" min="1" value="1" required onchange="calculateItemTotal(this)">
                                                            </div>
                                                            <div class="col-md-2">
                                                                  <input type="number" class="form-control" name="items[0][unit_price]"
                                                                        placeholder="Price" step="0.01" min="0" required onchange="calculateItemTotal(this)">
                                                            </div>
                                                            <div class="col-md-2">
                                                                  <input type="number" class="form-control item-total"
                                                                        placeholder="Total" step="0.01" min="0" readonly>
                                                            </div>
                                                            <div class="col-md-2">
                                                                  <button type="button" class="btn btn-outline-danger btn-sm" onclick="removePOItem(this)">
                                                                        <i class="fas fa-trash"></i>
                                                                  </button>
                                                            </div>
                                                      </div>
                                                </div>
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addPOItem()">
                                                      <i class="fas fa-plus me-2"></i>Add Item
                                                </button>
                                          </div>
                                    </div>

                                    <div class="row mt-3">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="subtotal" class="form-label">Subtotal</label>
                                                      <input type="number" class="form-control" id="subtotal" name="subtotal"
                                                            step="0.01" min="0" placeholder="0.00" readonly>
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="tax_amount" class="form-label">Tax Amount</label>
                                                      <input type="number" class="form-control" id="tax_amount" name="tax_amount"
                                                            step="0.01" min="0" placeholder="0.00" onchange="calculateTotal()">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="shipping_amount" class="form-label">Shipping Amount</label>
                                                      <input type="number" class="form-control" id="shipping_amount" name="shipping_amount"
                                                            step="0.01" min="0" placeholder="0.00" onchange="calculateTotal()">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="total_amount" class="form-label">Total Amount</label>
                                                      <input type="number" class="form-control" id="total_amount" name="total_amount"
                                                            step="0.01" min="0" placeholder="0.00" readonly>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                          <i class="fas fa-save me-2"></i>Save Purchase Order
                                    </button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Update Status Modal -->
      <div class="modal fade" id="updateStatusModal" tabindex="-1">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Update Purchase Order Status</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                              <input type="hidden" name="action" value="update_status">
                              <input type="hidden" name="po_id" id="update_po_id">
                              <div class="modal-body">
                                    <div class="mb-3">
                                          <label for="status" class="form-label">Status</label>
                                          <select class="form-select" id="status" name="status" required>
                                                <option value="draft">Draft</option>
                                                <option value="sent">Sent</option>
                                                <option value="received">Received</option>
                                                <option value="cancelled">Cancelled</option>
                                          </select>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Status</button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Delete PO Form -->
      <form id="deletePOForm" method="POST" style="display: none;">
            <input type="hidden" name="action" value="delete_po">
            <input type="hidden" name="po_id" id="delete_po_id">
      </form>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>

      <script>
            let itemIndex = 1;

            function addPOItem() {
                  const container = document.getElementById('po-items');
                  const newItem = document.createElement('div');
                  newItem.className = 'row po-item mb-2';
                  newItem.innerHTML = `
                        <div class="col-md-4">
                              <select class="form-select" name="items[${itemIndex}][product_id]" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                          <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>">
                                                <?php echo $product['product_code'] . ' - ' . $product['name']; ?>
                                          </option>
                                    <?php endforeach; ?>
                              </select>
                        </div>
                        <div class="col-md-2">
                              <input type="number" class="form-control" name="items[${itemIndex}][quantity]" 
                                     placeholder="Qty" min="1" value="1" required onchange="calculateItemTotal(this)">
                        </div>
                        <div class="col-md-2">
                              <input type="number" class="form-control" name="items[${itemIndex}][unit_price]" 
                                     placeholder="Price" step="0.01" min="0" required onchange="calculateItemTotal(this)">
                        </div>
                        <div class="col-md-2">
                              <input type="number" class="form-control item-total" 
                                     placeholder="Total" step="0.01" min="0" readonly>
                        </div>
                        <div class="col-md-2">
                              <button type="button" class="btn btn-outline-danger btn-sm" onclick="removePOItem(this)">
                                    <i class="fas fa-trash"></i>
                              </button>
                        </div>
                  `;
                  container.appendChild(newItem);
                  itemIndex++;
            }

            function removePOItem(button) {
                  button.closest('.po-item').remove();
                  calculateSubtotal();
            }

            function calculateItemTotal(input) {
                  const row = input.closest('.po-item');
                  const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
                  const price = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
                  const total = quantity * price;
                  row.querySelector('.item-total').value = total.toFixed(2);
                  calculateSubtotal();
            }

            function calculateSubtotal() {
                  let subtotal = 0;
                  document.querySelectorAll('.item-total').forEach(input => {
                        subtotal += parseFloat(input.value) || 0;
                  });
                  document.getElementById('subtotal').value = subtotal.toFixed(2);
                  calculateTotal();
            }

            function calculateTotal() {
                  const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
                  const taxAmount = parseFloat(document.getElementById('tax_amount').value) || 0;
                  const shippingAmount = parseFloat(document.getElementById('shipping_amount').value) || 0;
                  const total = subtotal + taxAmount + shippingAmount;
                  document.getElementById('total_amount').value = total.toFixed(2);
            }

            function viewPO(poId) {
                  // Load PO details via AJAX
                  fetch(`get_po_details.php?id=${poId}`)
                        .then(response => response.text())
                        .then(html => {
                              // Create modal for viewing PO details
                              const modal = document.createElement('div');
                              modal.className = 'modal fade';
                              modal.innerHTML = `
                                    <div class="modal-dialog modal-xl">
                                          <div class="modal-content">
                                                <div class="modal-header">
                                                      <h5 class="modal-title">Purchase Order Details</h5>
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

            function editPO(poId) {
                  // Load PO for editing via AJAX
                  fetch(`get_po.php?id=${poId}`)
                        .then(response => response.json())
                        .then(data => {
                              // Populate the add PO modal with data
                              document.getElementById('po_number').value = data.po_number;
                              document.getElementById('vendor_id').value = data.vendor_id;
                              document.getElementById('order_date').value = data.order_date;
                              document.getElementById('expected_delivery_date').value = data.expected_delivery_date || '';
                              document.getElementById('payment_terms').value = data.payment_terms || '';
                              document.getElementById('notes').value = data.notes || '';
                              document.getElementById('subtotal').value = data.subtotal;
                              document.getElementById('tax_amount').value = data.tax_amount;
                              document.getElementById('shipping_amount').value = data.shipping_amount;
                              document.getElementById('total_amount').value = data.total_amount;

                              // Change form action
                              const form = document.querySelector('#addPOModal form');
                              form.querySelector('input[name="action"]').value = 'update_po';
                              form.insertAdjacentHTML('beforeend', `<input type="hidden" name="po_id" value="${data.id}">`);

                              // Change modal title
                              document.querySelector('#addPOModal .modal-title').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Purchase Order';
                              document.querySelector('#addPOModal .btn-primary').innerHTML = '<i class="fas fa-save me-2"></i>Update Purchase Order';

                              new bootstrap.Modal(document.getElementById('addPOModal')).show();
                        });
            }

            function updateStatus(poId, currentStatus) {
                  document.getElementById('update_po_id').value = poId;
                  document.getElementById('status').value = currentStatus;
                  new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
            }

            function deletePO(poId) {
                  if (confirm('Are you sure you want to delete this purchase order? This action cannot be undone.')) {
                        document.getElementById('delete_po_id').value = poId;
                        document.getElementById('deletePOForm').submit();
                  }
            }

            // Reset modal when closed
            document.getElementById('addPOModal').addEventListener('hidden.bs.modal', function() {
                  this.querySelector('form').reset();
                  this.querySelector('input[name="action"]').value = 'add_po';
                  this.querySelector('.modal-title').innerHTML = '<i class="fas fa-plus me-2"></i>New Purchase Order';
                  this.querySelector('.btn-primary').innerHTML = '<i class="fas fa-save me-2"></i>Save Purchase Order';
                  const poIdInput = this.querySelector('input[name="po_id"]');
                  if (poIdInput) poIdInput.remove();

                  // Clear items
                  const itemsContainer = document.getElementById('po-items');
                  itemsContainer.innerHTML = itemsContainer.children[0].outerHTML;
                  itemIndex = 1;
            });
      </script>
</body>

</html>