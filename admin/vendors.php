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
                  case 'add_vendor':
                        $vendor_code = trim($_POST['vendor_code']);
                        $vendor_name = trim($_POST['vendor_name']);
                        $contact_person = trim($_POST['contact_person']);
                        $email = trim($_POST['email']);
                        $phone = trim($_POST['phone']);
                        $address = trim($_POST['address']);
                        $city = trim($_POST['city']);
                        $state = trim($_POST['state']);
                        $postal_code = trim($_POST['postal_code']);
                        $country = trim($_POST['country']);
                        $tax_id = trim($_POST['tax_id']);
                        $payment_terms = trim($_POST['payment_terms']);
                        $credit_limit = floatval($_POST['credit_limit']);
                        $notes = trim($_POST['notes']);

                        // Check if vendor code already exists
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vendors WHERE vendor_code = ?");
                        $stmt->execute([$vendor_code]);
                        if ($stmt->fetchColumn() > 0) {
                              $error = 'Vendor code already exists.';
                        } else {
                              $stmt = $pdo->prepare("INSERT INTO vendors (vendor_code, vendor_name, contact_person, email, phone, address, city, state, postal_code, country, tax_id, payment_terms, credit_limit, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                              if ($stmt->execute([$vendor_code, $vendor_name, $contact_person, $email, $phone, $address, $city, $state, $postal_code, $country, $tax_id, $payment_terms, $credit_limit, $notes])) {
                                    $message = 'Vendor added successfully!';
                              } else {
                                    $error = 'Failed to add vendor.';
                              }
                        }
                        break;

                  case 'update_vendor':
                        $vendor_id = intval($_POST['vendor_id']);
                        $vendor_code = trim($_POST['vendor_code']);
                        $vendor_name = trim($_POST['vendor_name']);
                        $contact_person = trim($_POST['contact_person']);
                        $email = trim($_POST['email']);
                        $phone = trim($_POST['phone']);
                        $address = trim($_POST['address']);
                        $city = trim($_POST['city']);
                        $state = trim($_POST['state']);
                        $postal_code = trim($_POST['postal_code']);
                        $country = trim($_POST['country']);
                        $tax_id = trim($_POST['tax_id']);
                        $payment_terms = trim($_POST['payment_terms']);
                        $credit_limit = floatval($_POST['credit_limit']);
                        $notes = trim($_POST['notes']);

                        // Check if vendor code already exists (excluding current vendor)
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vendors WHERE vendor_code = ? AND id != ?");
                        $stmt->execute([$vendor_code, $vendor_id]);
                        if ($stmt->fetchColumn() > 0) {
                              $error = 'Vendor code already exists.';
                        } else {
                              $stmt = $pdo->prepare("UPDATE vendors SET vendor_code = ?, vendor_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, postal_code = ?, country = ?, tax_id = ?, payment_terms = ?, credit_limit = ?, notes = ? WHERE id = ?");

                              if ($stmt->execute([$vendor_code, $vendor_name, $contact_person, $email, $phone, $address, $city, $state, $postal_code, $country, $tax_id, $payment_terms, $credit_limit, $notes, $vendor_id])) {
                                    $message = 'Vendor updated successfully!';
                              } else {
                                    $error = 'Failed to update vendor.';
                              }
                        }
                        break;

                  case 'delete_vendor':
                        $vendor_id = intval($_POST['vendor_id']);

                        // Check if vendor has associated purchase orders
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = ?");
                        $stmt->execute([$vendor_id]);
                        if ($stmt->fetchColumn() > 0) {
                              $error = 'Cannot delete vendor with associated purchase orders.';
                        } else {
                              $stmt = $pdo->prepare("DELETE FROM vendors WHERE id = ?");
                              if ($stmt->execute([$vendor_id])) {
                                    $message = 'Vendor deleted successfully!';
                              } else {
                                    $error = 'Failed to delete vendor.';
                              }
                        }
                        break;

                  case 'toggle_status':
                        $vendor_id = intval($_POST['vendor_id']);
                        $current_status = $_POST['current_status'];
                        $new_status = $current_status ? 0 : 1;

                        $stmt = $pdo->prepare("UPDATE vendors SET is_active = ? WHERE id = ?");
                        if ($stmt->execute([$new_status, $vendor_id])) {
                              $message = 'Vendor status updated successfully!';
                        } else {
                              $error = 'Failed to update vendor status.';
                        }
                        break;
            }
      }
}

// Get vendors with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$country = isset($_GET['country']) ? $_GET['country'] : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
      $where_conditions[] = "(v.vendor_code LIKE ? OR v.vendor_name LIKE ? OR v.contact_person LIKE ? OR v.email LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      $params[] = "%$search%";
      $params[] = "%$search%";
}

if ($status !== '') {
      $where_conditions[] = "v.is_active = ?";
      $params[] = $status;
}

if (!empty($country)) {
      $where_conditions[] = "v.country = ?";
      $params[] = $country;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) FROM vendors v $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_vendors = $stmt->fetchColumn();
$total_pages = ceil($total_vendors / $limit);

// Get vendors
$sql = "SELECT v.*, 
        COUNT(po.id) as purchase_order_count,
        SUM(CASE WHEN po.status = 'received' THEN po.total_amount ELSE 0 END) as total_purchases
        FROM vendors v 
        LEFT JOIN purchase_orders po ON v.id = po.vendor_id 
        $where_clause 
        GROUP BY v.id 
        ORDER BY v.vendor_name 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get countries for filter
$stmt = $pdo->prepare("SELECT DISTINCT country FROM vendors WHERE country IS NOT NULL AND country != '' ORDER BY country");
$stmt->execute();
$countries = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get vendor statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_vendors,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_vendors,
        COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_vendors,
        AVG(credit_limit) as avg_credit_limit
    FROM vendors
");
$stmt->execute();
$vendor_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Vendors Management - POS Admin</title>

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
                                                <li class="breadcrumb-item active" aria-current="page">Vendors</li>
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
                                          <i class="fas fa-truck"></i>
                                          Vendors Management
                                    </h1>
                                    <p class="text-muted mb-0">Manage suppliers and vendors</p>
                              </div>
                        </div>

                        <!-- Action Button -->
                        <div class="row mb-4">
                              <div class="col-12">
                                    <div class="d-flex justify-content-end">
                                          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVendorModal">
                                                <i class="fas fa-plus me-2"></i>New Vendor
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

                        <!-- Vendor Statistics -->
                        <div class="row mb-4">
                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Total Vendors</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-truck"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo $vendor_stats['total_vendors'] ?? 0; ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> All Time
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card success">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Active Vendors</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-check-circle"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo $vendor_stats['active_vendors'] ?? 0; ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> Currently Active
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card warning">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Inactive Vendors</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-times-circle"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value"><?php echo $vendor_stats['inactive_vendors'] ?? 0; ?></h2>
                                          <div class="stat-card-change negative">
                                                <i class="fas fa-arrow-down"></i> Currently Inactive
                                          </div>
                                    </div>
                              </div>

                              <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="stat-card info">
                                          <div class="stat-card-header">
                                                <h6 class="stat-card-title">Avg Credit Limit</h6>
                                                <div class="stat-card-icon">
                                                      <i class="fas fa-credit-card"></i>
                                                </div>
                                          </div>
                                          <h2 class="stat-card-value">$<?php echo number_format($vendor_stats['avg_credit_limit'] ?? 0, 2); ?></h2>
                                          <div class="stat-card-change positive">
                                                <i class="fas fa-arrow-up"></i> Average
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
                                                      <div class="col-md-4">
                                                            <label for="search" class="form-label">Search</label>
                                                            <input type="text" class="form-control" id="search" name="search"
                                                                  value="<?php echo htmlspecialchars($search); ?>"
                                                                  placeholder="Vendor code, name, contact person, email...">
                                                      </div>
                                                      <div class="col-md-3">
                                                            <label for="status" class="form-label">Status</label>
                                                            <select class="form-select" id="status" name="status">
                                                                  <option value="">All Status</option>
                                                                  <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Active</option>
                                                                  <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Inactive</option>
                                                            </select>
                                                      </div>
                                                      <div class="col-md-3">
                                                            <label for="country" class="form-label">Country</label>
                                                            <select class="form-select" id="country" name="country">
                                                                  <option value="">All Countries</option>
                                                                  <?php foreach ($countries as $c): ?>
                                                                        <option value="<?php echo htmlspecialchars($c); ?>"
                                                                              <?php echo $country === $c ? 'selected' : ''; ?>>
                                                                              <?php echo htmlspecialchars($c); ?>
                                                                        </option>
                                                                  <?php endforeach; ?>
                                                            </select>
                                                      </div>
                                                      <div class="col-md-2">
                                                            <label class="form-label">&nbsp;</label>
                                                            <div class="d-flex gap-2">
                                                                  <button type="submit" class="btn btn-primary">
                                                                        <i class="fas fa-search me-2"></i>Search
                                                                  </button>
                                                                  <a href="vendors.php" class="btn btn-outline-secondary">
                                                                        <i class="fas fa-times me-2"></i>Clear
                                                                  </a>
                                                            </div>
                                                      </div>
                                                </form>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Vendors Table -->
                        <div class="row mb-4">
                              <div class="col-12">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-list"></i>
                                                      Vendors List
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="table-responsive">
                                                      <table class="table table-bordered" id="vendorsTable" width="100%" cellspacing="0">
                                                            <thead>
                                                                  <tr>
                                                                        <th>Vendor Code</th>
                                                                        <th>Vendor Name</th>
                                                                        <th>Contact Person</th>
                                                                        <th>Contact Info</th>
                                                                        <th>Location</th>
                                                                        <th>Credit Limit</th>
                                                                        <th>Purchase Orders</th>
                                                                        <th>Status</th>
                                                                        <th>Actions</th>
                                                                  </tr>
                                                            </thead>
                                                            <tbody>
                                                                  <?php foreach ($vendors as $vendor): ?>
                                                                        <tr>
                                                                              <td>
                                                                                    <strong><?php echo htmlspecialchars($vendor['vendor_code']); ?></strong>
                                                                              </td>
                                                                              <td>
                                                                                    <?php echo htmlspecialchars($vendor['vendor_name']); ?>
                                                                                    <?php if ($vendor['tax_id']): ?>
                                                                                          <br><small class="text-muted">Tax ID: <?php echo htmlspecialchars($vendor['tax_id']); ?></small>
                                                                                    <?php endif; ?>
                                                                              </td>
                                                                              <td>
                                                                                    <?php echo htmlspecialchars($vendor['contact_person'] ?? 'N/A'); ?>
                                                                              </td>
                                                                              <td>
                                                                                    <?php if ($vendor['email']): ?>
                                                                                          <div><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($vendor['email']); ?></div>
                                                                                    <?php endif; ?>
                                                                                    <?php if ($vendor['phone']): ?>
                                                                                          <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($vendor['phone']); ?></div>
                                                                                    <?php endif; ?>
                                                                              </td>
                                                                              <td>
                                                                                    <?php
                                                                                    $location = [];
                                                                                    if ($vendor['city']) $location[] = $vendor['city'];
                                                                                    if ($vendor['state']) $location[] = $vendor['state'];
                                                                                    if ($vendor['country']) $location[] = $vendor['country'];
                                                                                    echo htmlspecialchars(implode(', ', $location));
                                                                                    ?>
                                                                              </td>
                                                                              <td class="text-end">
                                                                                    <strong>$<?php echo number_format($vendor['credit_limit'], 2); ?></strong>
                                                                                    <?php if ($vendor['payment_terms']): ?>
                                                                                          <br><small class="text-muted"><?php echo htmlspecialchars($vendor['payment_terms']); ?></small>
                                                                                    <?php endif; ?>
                                                                              </td>
                                                                              <td class="text-center">
                                                                                    <span class="badge bg-info"><?php echo $vendor['purchase_order_count']; ?> orders</span>
                                                                                    <?php if ($vendor['total_purchases'] > 0): ?>
                                                                                          <br><small class="text-muted">$<?php echo number_format($vendor['total_purchases'], 2); ?></small>
                                                                                    <?php endif; ?>
                                                                              </td>
                                                                              <td>
                                                                                    <span class="badge bg-<?php echo $vendor['is_active'] ? 'success' : 'danger'; ?>">
                                                                                          <?php echo $vendor['is_active'] ? 'Active' : 'Inactive'; ?>
                                                                                    </span>
                                                                              </td>
                                                                              <td>
                                                                                    <div class="btn-group btn-group-sm">
                                                                                          <button class="btn btn-outline-primary" onclick="viewVendor(<?php echo $vendor['id']; ?>)">
                                                                                                <i class="fas fa-eye"></i>
                                                                                          </button>
                                                                                          <button class="btn btn-outline-warning" onclick="editVendor(<?php echo $vendor['id']; ?>)">
                                                                                                <i class="fas fa-edit"></i>
                                                                                          </button>
                                                                                          <button class="btn btn-outline-<?php echo $vendor['is_active'] ? 'warning' : 'success'; ?>"
                                                                                                onclick="toggleVendorStatus(<?php echo $vendor['id']; ?>, <?php echo $vendor['is_active']; ?>)">
                                                                                                <i class="fas fa-<?php echo $vendor['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                                                          </button>
                                                                                          <button class="btn btn-outline-danger" onclick="deleteVendor(<?php echo $vendor['id']; ?>)">
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
                                                                  Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_vendors); ?> of <?php echo $total_vendors; ?> vendors
                                                            </div>
                                                            <nav aria-label="Vendors pagination">
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

      <!-- Add Vendor Modal -->
      <div class="modal fade" id="addVendorModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">
                                    <i class="fas fa-plus me-2"></i>New Vendor
                              </h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                              <input type="hidden" name="action" value="add_vendor">
                              <div class="modal-body">
                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="vendor_code" class="form-label">Vendor Code *</label>
                                                      <input type="text" class="form-control" id="vendor_code" name="vendor_code"
                                                            placeholder="e.g., VEN001" required>
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="vendor_name" class="form-label">Vendor Name *</label>
                                                      <input type="text" class="form-control" id="vendor_name" name="vendor_name"
                                                            placeholder="Company or vendor name" required>
                                                </div>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="contact_person" class="form-label">Contact Person</label>
                                                      <input type="text" class="form-control" id="contact_person" name="contact_person"
                                                            placeholder="Primary contact person">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="email" class="form-label">Email</label>
                                                      <input type="email" class="form-control" id="email" name="email"
                                                            placeholder="contact@vendor.com">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="phone" class="form-label">Phone</label>
                                                      <input type="text" class="form-control" id="phone" name="phone"
                                                            placeholder="Phone number">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="tax_id" class="form-label">Tax ID</label>
                                                      <input type="text" class="form-control" id="tax_id" name="tax_id"
                                                            placeholder="Tax identification number">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="mb-3">
                                          <label for="address" class="form-label">Address</label>
                                          <textarea class="form-control" id="address" name="address" rows="2"
                                                placeholder="Street address"></textarea>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-4">
                                                <div class="mb-3">
                                                      <label for="city" class="form-label">City</label>
                                                      <input type="text" class="form-control" id="city" name="city"
                                                            placeholder="City">
                                                </div>
                                          </div>
                                          <div class="col-md-4">
                                                <div class="mb-3">
                                                      <label for="state" class="form-label">State/Province</label>
                                                      <input type="text" class="form-control" id="state" name="state"
                                                            placeholder="State or province">
                                                </div>
                                          </div>
                                          <div class="col-md-4">
                                                <div class="mb-3">
                                                      <label for="postal_code" class="form-label">Postal Code</label>
                                                      <input type="text" class="form-control" id="postal_code" name="postal_code"
                                                            placeholder="Postal code">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="country" class="form-label">Country</label>
                                                      <input type="text" class="form-control" id="country" name="country"
                                                            placeholder="Country" value="USA">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="credit_limit" class="form-label">Credit Limit</label>
                                                      <input type="number" class="form-control" id="credit_limit" name="credit_limit"
                                                            step="0.01" min="0" placeholder="0.00" value="0">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="payment_terms" class="form-label">Payment Terms</label>
                                                      <input type="text" class="form-control" id="payment_terms" name="payment_terms"
                                                            placeholder="e.g., Net 30, Net 60">
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
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                          <i class="fas fa-save me-2"></i>Save Vendor
                                    </button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Delete Vendor Form -->
      <form id="deleteVendorForm" method="POST" style="display: none;">
            <input type="hidden" name="action" value="delete_vendor">
            <input type="hidden" name="vendor_id" id="delete_vendor_id">
      </form>

      <!-- Toggle Status Form -->
      <form id="toggleStatusForm" method="POST" style="display: none;">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="vendor_id" id="toggle_vendor_id">
            <input type="hidden" name="current_status" id="toggle_current_status">
      </form>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>

      <script>
            function viewVendor(vendorId) {
                  // Load vendor details via AJAX
                  fetch(`get_vendor_details.php?id=${vendorId}`)
                        .then(response => response.text())
                        .then(html => {
                              // Create modal for viewing vendor details
                              const modal = document.createElement('div');
                              modal.className = 'modal fade';
                              modal.innerHTML = `
                              <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                          <div class="modal-header">
                                                <h5 class="modal-title">Vendor Details</h5>
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

            function editVendor(vendorId) {
                  // Load vendor for editing via AJAX
                  fetch(`get_vendor.php?id=${vendorId}`)
                        .then(response => response.json())
                        .then(data => {
                              // Populate the add vendor modal with data
                              document.getElementById('vendor_code').value = data.vendor_code;
                              document.getElementById('vendor_name').value = data.vendor_name;
                              document.getElementById('contact_person').value = data.contact_person || '';
                              document.getElementById('email').value = data.email || '';
                              document.getElementById('phone').value = data.phone || '';
                              document.getElementById('address').value = data.address || '';
                              document.getElementById('city').value = data.city || '';
                              document.getElementById('state').value = data.state || '';
                              document.getElementById('postal_code').value = data.postal_code || '';
                              document.getElementById('country').value = data.country || 'USA';
                              document.getElementById('tax_id').value = data.tax_id || '';
                              document.getElementById('payment_terms').value = data.payment_terms || '';
                              document.getElementById('credit_limit').value = data.credit_limit;
                              document.getElementById('notes').value = data.notes || '';

                              // Change form action
                              const form = document.querySelector('#addVendorModal form');
                              form.querySelector('input[name="action"]').value = 'update_vendor';
                              form.insertAdjacentHTML('beforeend', `<input type="hidden" name="vendor_id" value="${data.id}">`);

                              // Change modal title
                              document.querySelector('#addVendorModal .modal-title').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Vendor';
                              document.querySelector('#addVendorModal .btn-primary').innerHTML = '<i class="fas fa-save me-2"></i>Update Vendor';

                              new bootstrap.Modal(document.getElementById('addVendorModal')).show();
                        });
            }

            function deleteVendor(vendorId) {
                  if (confirm('Are you sure you want to delete this vendor? This action cannot be undone.')) {
                        document.getElementById('delete_vendor_id').value = vendorId;
                        document.getElementById('deleteVendorForm').submit();
                  }
            }

            function toggleVendorStatus(vendorId, currentStatus) {
                  const action = currentStatus ? 'deactivate' : 'activate';
                  if (confirm(`Are you sure you want to ${action} this vendor?`)) {
                        document.getElementById('toggle_vendor_id').value = vendorId;
                        document.getElementById('toggle_current_status').value = currentStatus;
                        document.getElementById('toggleStatusForm').submit();
                  }
            }

            // Reset modal when closed
            document.getElementById('addVendorModal').addEventListener('hidden.bs.modal', function() {
                  this.querySelector('form').reset();
                  this.querySelector('input[name="action"]').value = 'add_vendor';
                  this.querySelector('.modal-title').innerHTML = '<i class="fas fa-plus me-2"></i>New Vendor';
                  this.querySelector('.btn-primary').innerHTML = '<i class="fas fa-save me-2"></i>Save Vendor';
                  const vendorIdInput = this.querySelector('input[name="vendor_id"]');
                  if (vendorIdInput) vendorIdInput.remove();
            });
      </script>
      </div>
</body>

</html>