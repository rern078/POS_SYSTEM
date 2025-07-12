<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || $_SESSION['role'] !== 'customer') {
      header('Location: ../login.php');
      exit();
}

$pdo = getDBConnection();
$user = getCurrentUser();

// Get customer's order history - prioritize user_id, fallback to email for backward compatibility
$stmt = $pdo->prepare("
      SELECT o.*, 
             COUNT(oi.id) as total_items,
             GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, ')') SEPARATOR ', ') as items
      FROM orders o 
      LEFT JOIN order_items oi ON o.id = oi.order_id
      LEFT JOIN products p ON oi.product_id = p.id
      WHERE (o.user_id = ? OR (o.user_id IS NULL AND o.customer_email = ?))
      GROUP BY o.id
      ORDER BY o.created_at DESC
");
$stmt->execute([$user['id'], $user['email']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total spent - prioritize user_id, fallback to email for backward compatibility
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_spent FROM orders WHERE (user_id = ? OR (user_id IS NULL AND customer_email = ?)) AND status = 'completed'");
$stmt->execute([$user['id'], $user['email']]);
$total_spent = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Customer Dashboard - POS System</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Google Fonts -->
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <!-- Custom Customer CSS -->
      <link rel="stylesheet" href="../assets/css/customer.css">
</head>

<body>
      <div class="customer-layout">
            <!-- Main Content -->
            <div class="customer-main">
                  <!-- Top Navigation -->
                  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
                        <div class="container-fluid">
                              <button class="btn btn-link sidebar-toggle">
                                    <i class="fas fa-bars"></i>
                              </button>

                              <ul class="navbar-nav ms-auto">
                                    <li class="nav-item dropdown">
                                          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['username']; ?>
                                                <span class="badge bg-success ms-1">Customer</span>
                                          </a>
                                          <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="customer_profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                                <li><a class="dropdown-item" href="customer_settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                                <li>
                                                      <hr class="dropdown-divider">
                                                </li>
                                                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                          </ul>
                                    </li>
                              </ul>
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
                                                      <i class="fas fa-user"></i>
                                                      Customer Dashboard
                                                </h1>
                                                <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>!</p>
                                                <div class="mt-3 d-flex gap-2">
                                                      <a href="customer_profile.php" class="btn btn-modern btn-outline-primary">
                                                            <i class="fas fa-user me-1"></i>View Profile
                                                      </a>
                                                      <a href="customer_settings.php" class="btn btn-modern btn-outline-secondary">
                                                            <i class="fas fa-key me-1"></i>Change Password
                                                      </a>
                                                </div>
                                          </div>
                                          <a href="../index.php" class="btn btn-modern btn-primary">
                                                <i class="fas fa-shopping-cart me-2"></i>Continue Shopping
                                          </a>
                                    </div>
                              </div>
                        </div>

                        <!-- Stats Cards -->
                        <div class="row g-4 mb-4">
                              <div class="col-lg-4 col-md-6">
                                    <div class="content-card">
                                          <div class="content-card-body">
                                                <div class="d-flex align-items-center">
                                                      <div class="flex-shrink-0">
                                                            <div class="stats-icon bg-primary text-white">
                                                                  <i class="fas fa-shopping-bag"></i>
                                                            </div>
                                                      </div>
                                                      <div class="flex-grow-1 ms-3">
                                                            <h3 class="stats-number"><?php echo count($orders); ?></h3>
                                                            <p class="stats-label text-muted mb-0">Total Orders</p>
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                              </div>

                              <div class="col-lg-4 col-md-6">
                                    <div class="content-card">
                                          <div class="content-card-body">
                                                <div class="d-flex align-items-center">
                                                      <div class="flex-shrink-0">
                                                            <div class="stats-icon bg-success text-white">
                                                                  <i class="fas fa-dollar-sign"></i>
                                                            </div>
                                                      </div>
                                                      <div class="flex-grow-1 ms-3">
                                                            <h3 class="stats-number">$<?php echo number_format($total_spent, 2); ?></h3>
                                                            <p class="stats-label text-muted mb-0">Total Spent</p>
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                              </div>

                              <div class="col-lg-4 col-md-6">
                                    <div class="content-card">
                                          <div class="content-card-body">
                                                <div class="d-flex align-items-center">
                                                      <div class="flex-shrink-0">
                                                            <div class="stats-icon bg-info text-white">
                                                                  <i class="fas fa-calendar"></i>
                                                            </div>
                                                      </div>
                                                      <div class="flex-grow-1 ms-3">
                                                            <h3 class="stats-number"><?php echo date('M Y'); ?></h3>
                                                            <p class="stats-label text-muted mb-0">Member Since</p>
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Order History -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h2 class="content-card-title">
                                          <i class="fas fa-history me-2"></i>
                                          Order History
                                    </h2>
                              </div>
                              <div class="content-card-body">
                                    <?php if (empty($orders)): ?>
                                          <div class="text-center py-5">
                                                <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                                                <h4 class="text-muted">No Orders Yet</h4>
                                                <p class="text-muted">You haven't placed any orders yet.</p>
                                                <a href="../index.php" class="btn btn-primary">
                                                      <i class="fas fa-shopping-cart me-2"></i>Start Shopping
                                                </a>
                                          </div>
                                    <?php else: ?>
                                          <div class="table-responsive">
                                                <table class="table table-hover">
                                                      <thead>
                                                            <tr>
                                                                  <th>Order ID</th>
                                                                  <th>Date</th>
                                                                  <th>Items</th>
                                                                  <th>Total</th>
                                                                  <th>Status</th>
                                                                  <th>Payment</th>
                                                                  <th>Actions</th>
                                                            </tr>
                                                      </thead>
                                                      <tbody>
                                                            <?php foreach ($orders as $order): ?>
                                                                  <tr>
                                                                        <td>
                                                                              <strong>#<?php echo $order['id']; ?></strong>
                                                                        </td>
                                                                        <td>
                                                                              <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?>
                                                                        </td>
                                                                        <td>
                                                                              <small class="text-muted">
                                                                                    <?php echo htmlspecialchars($order['items'] ?? 'No items'); ?>
                                                                              </small>
                                                                        </td>
                                                                        <td>
                                                                              <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                                                        </td>
                                                                        <td>
                                                                              <span class="badge bg-<?php
                                                                                                      echo $order['status'] === 'completed' ? 'success' : ($order['status'] === 'pending' ? 'warning' : 'danger');
                                                                                                      ?>">
                                                                                    <i class="fas fa-<?php
                                                                                                      echo $order['status'] === 'completed' ? 'check-circle' : ($order['status'] === 'pending' ? 'clock' : 'times-circle');
                                                                                                      ?> me-1"></i>
                                                                                    <?php echo ucfirst($order['status']); ?>
                                                                              </span>
                                                                        </td>
                                                                        <td>
                                                                              <span class="badge bg-secondary">
                                                                                    <i class="fas fa-credit-card me-1"></i>
                                                                                    <?php echo ucfirst($order['payment_method']); ?>
                                                                              </span>
                                                                        </td>
                                                                        <td>
                                                                              <a href="../guest_receipt.php?order_id=<?php echo $order['id']; ?>"
                                                                                    class="btn btn-sm btn-outline-primary" target="_blank">
                                                                                    <i class="fas fa-print me-1"></i>Receipt
                                                                              </a>
                                                                        </td>
                                                                  </tr>
                                                            <?php endforeach; ?>
                                                      </tbody>
                                                </table>
                                          </div>
                                    <?php endif; ?>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="../admin/assets/js/admin.js"></script>
</body>

</html>