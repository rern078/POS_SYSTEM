<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a customer
if (!isLoggedIn() || $_SESSION['role'] !== 'customer') {
      header('Location: ../login.php');
      exit();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Customer Settings - POS System</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="../assets/css/customer.css">
</head>

<body>
      <div class="customer-layout">
            <!-- Main Content -->
            <div class="customer-main">
                  <!-- Top Navigation -->
                  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
                        <div class="container-fluid">
                              <a href="customer_dashboard.php" class="btn btn-link">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                              </a>

                              <ul class="navbar-nav ms-auto">
                                    <li class="nav-item">
                                          <a class="nav-link" href="../index.php">
                                                <i class="fas fa-home me-1"></i>Home
                                          </a>
                                    </li>
                                    <li class="nav-item dropdown">
                                          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['username']; ?>
                                                <span class="badge bg-success ms-1">Customer</span>
                                          </a>
                                          <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="customer_dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                                <li><a class="dropdown-item" href="customer_profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
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
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h1 class="content-card-title mb-0">
                                          <i class="fas fa-key"></i> Change Password
                                    </h1>
                              </div>
                              <div class="content-card-body">
                                    <!-- Success/Error messages placeholder -->
                                    <?php if (isset($_SESSION['change_password_success'])): ?>
                                          <div class="alert alert-success">Password changed successfully.</div>
                                          <?php unset($_SESSION['change_password_success']); ?>
                                    <?php elseif (isset($_SESSION['change_password_error'])): ?>
                                          <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['change_password_error']); ?></div>
                                          <?php unset($_SESSION['change_password_error']); ?>
                                    <?php endif; ?>
                                    <form method="post" action="">
                                          <div class="mb-3">
                                                <label for="current_password" class="form-label">Current Password</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                          </div>
                                          <div class="mb-3">
                                                <label for="new_password" class="form-label">New Password</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                          </div>
                                          <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                          </div>
                                          <button type="submit" class="btn btn-modern btn-primary">
                                                <i class="fas fa-save me-1"></i>Change Password
                                          </button>
                                    </form>
                              </div>
                        </div>
                  </div>
            </div>
      </div>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>