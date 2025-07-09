<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
      header('Location: ../login.php');
      exit();
}

$user = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Profile - POS System</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="../admin/assets/css/admin.css">
</head>

<body>
      <!-- Navigation -->
      <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                  <a class="navbar-brand" href="index.php">
                        <i class="fas fa-store me-2"></i>POS System
                  </a>

                  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                        <span class="navbar-toggler-icon"></span>
                  </button>

                  <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav me-auto">
                              <li class="nav-item">
                                    <a class="nav-link" href="index.php">
                                          <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="pos.php">
                                          <i class="fas fa-cash-register me-1"></i>POS
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="products.php">
                                          <i class="fas fa-box me-1"></i>Products
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="orders.php">
                                          <i class="fas fa-shopping-cart me-1"></i>Orders
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="receipts.php">
                                          <i class="fas fa-receipt me-1"></i>Receipts
                                    </a>
                              </li>
                              <?php if (isManager()): ?>
                                    <li class="nav-item">
                                          <a class="nav-link" href="reports.php">
                                                <i class="fas fa-chart-bar me-1"></i>Reports
                                          </a>
                                    </li>
                              <?php endif; ?>
                        </ul>

                        <ul class="navbar-nav">
                              <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                          <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['username']; ?>
                                          <span class="badge bg-light text-dark ms-1"><?php echo ucfirst($_SESSION['role']); ?></span>
                                    </a>
                                    <ul class="dropdown-menu">
                                          <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                          <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                          <li>
                                                <hr class="dropdown-divider">
                                          </li>
                                          <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                    </ul>
                              </li>
                        </ul>
                  </div>
            </div>
      </nav>

      <!-- Main Content -->
      <div class="container-fluid mt-4">
            <div class="row justify-content-center">
                  <div class="col-md-8">
                        <div class="card">
                              <div class="card-header">
                                    <h4 class="mb-0"><i class="fas fa-user me-2"></i>User Profile</h4>
                              </div>
                              <div class="card-body">
                                    <div class="row">
                                          <div class="col-md-4 text-center">
                                                <div class="mb-3">
                                                      <i class="fas fa-user-circle fa-5x text-primary"></i>
                                                </div>
                                                <h5><?php echo htmlspecialchars($user['username']); ?></h5>
                                                <span class="badge bg-primary"><?php echo ucfirst($user['role']); ?></span>
                                          </div>
                                          <div class="col-md-8">
                                                <div class="row mb-3">
                                                      <div class="col-sm-3">
                                                            <strong>Username:</strong>
                                                      </div>
                                                      <div class="col-sm-9">
                                                            <?php echo htmlspecialchars($user['username']); ?>
                                                      </div>
                                                </div>
                                                <div class="row mb-3">
                                                      <div class="col-sm-3">
                                                            <strong>Email:</strong>
                                                      </div>
                                                      <div class="col-sm-9">
                                                            <?php echo htmlspecialchars($user['email']); ?>
                                                      </div>
                                                </div>
                                                <div class="row mb-3">
                                                      <div class="col-sm-3">
                                                            <strong>Role:</strong>
                                                      </div>
                                                      <div class="col-sm-9">
                                                            <span class="badge bg-primary"><?php echo ucfirst($user['role']); ?></span>
                                                      </div>
                                                </div>
                                                <div class="row mb-3">
                                                      <div class="col-sm-3">
                                                            <strong>Member Since:</strong>
                                                      </div>
                                                      <div class="col-sm-9">
                                                            <?php echo date('F d, Y', strtotime($user['created_at'])); ?>
                                                      </div>
                                                </div>
                                                <div class="row mb-3">
                                                      <div class="col-sm-3">
                                                            <strong>Last Updated:</strong>
                                                      </div>
                                                      <div class="col-sm-9">
                                                            <?php echo date('F d, Y H:i A', strtotime($user['updated_at'])); ?>
                                                      </div>
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
</body>

</html>