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

?>
<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Customer Profile - POS System</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="../assets/css/user.css">
</head>

<body>
      <div class="customer-layout">
            <!-- Main Content -->
            <div class="customer-main">
                  <!-- Back Button -->
                  <div class="back-button-container">
                        <a href="customer_dashboard.php" class="back-btn">
                              <i class="fas fa-arrow-left"></i>
                              <span>Back to Dashboard</span>
                        </a>
                  </div>

                  <!-- Page Content -->
                  <div class="content-card">
                        <div class="content-card-header">
                              <h1 class="content-card-title">
                                    <i class="fas fa-user"></i> Customer Profile
                              </h1>
                        </div>
                        <div class="content-card-body">
                              <div class="row">
                                    <div class="col-md-4 text-center">
                                          <div class="stats-card">
                                                <div class="stats-icon" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                                                      <i class="fas fa-user-circle"></i>
                                                </div>
                                                <h5 class="mt-3"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h5>
                                                <span class="badge bg-success">Customer</span>
                                          </div>
                                    </div>
                                    <div class="col-md-8">
                                          <div class="row mb-3">
                                                <div class="col-sm-3">
                                                      <strong class="text-muted">Full Name:</strong>
                                                </div>
                                                <div class="col-sm-9">
                                                      <?php echo htmlspecialchars($user['full_name'] ?: 'Not provided'); ?>
                                                </div>
                                          </div>
                                          <div class="row mb-3">
                                                <div class="col-sm-3">
                                                      <strong class="text-muted">Username:</strong>
                                                </div>
                                                <div class="col-sm-9">
                                                      <?php echo htmlspecialchars($user['username']); ?>
                                                </div>
                                          </div>
                                          <div class="row mb-3">
                                                <div class="col-sm-3">
                                                      <strong class="text-muted">Email:</strong>
                                                </div>
                                                <div class="col-sm-9">
                                                      <?php echo htmlspecialchars($user['email']); ?>
                                                </div>
                                          </div>
                                          <div class="row mb-3">
                                                <div class="col-sm-3">
                                                      <strong class="text-muted">Member Since:</strong>
                                                </div>
                                                <div class="col-sm-9">
                                                      <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                                </div>
                                          </div>
                                          <?php if (!empty($user['phone'])): ?>
                                                <div class="row mb-3">
                                                      <div class="col-sm-3">
                                                            <strong class="text-muted">Phone:</strong>
                                                      </div>
                                                      <div class="col-sm-9">
                                                            <?php echo htmlspecialchars($user['phone']); ?>
                                                      </div>
                                                </div>
                                          <?php endif; ?>

                                          <div class="mt-4">
                                                <a href="customer_settings.php" class="btn btn-modern btn-primary">
                                                      <i class="fas fa-cog me-2"></i>Account Settings
                                                </a>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>