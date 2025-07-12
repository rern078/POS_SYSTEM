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
      <div class="admin-layout">
            <?php include 'side.php'; ?>
            <div class="admin-main">
                  <nav class="admin-topbar">
                        <div class="topbar-left">
                              <button class="btn btn-link sidebar-toggle-btn" id="sidebarToggleBtn">
                                    <i class="fas fa-bars"></i>
                              </button>
                              <div class="breadcrumb-container">
                                    <nav aria-label="breadcrumb">
                                          <ol class="breadcrumb">
                                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                                <li class="breadcrumb-item active" aria-current="page">Profile</li>
                                          </ol>
                                    </nav>
                              </div>
                        </div>
                  </nav>
                  <div class="admin-content">
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
                  </div>
            </div>
      </div>
      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="../admin/assets/js/admin.js"></script>
</body>

</html>