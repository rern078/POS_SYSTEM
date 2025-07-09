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
      <link rel="stylesheet" href="../assets/css/customer.css">
</head>

<body>
      <div class="customer-layout">
            <div class="customer-main">
                  <div class="content-card">
                        <div class="content-card-header d-flex justify-content-between align-items-center">
                              <h1 class="content-card-title mb-0">
                                    <i class="fas fa-user"></i> Profile
                              </h1>
                              <a href="#" class="btn btn-modern btn-outline-primary disabled">
                                    <i class="fas fa-edit me-1"></i>Edit Profile
                              </a>
                        </div>
                        <div class="content-card-body">
                              <dl class="row mb-0">
                                    <dt class="col-sm-3">Full Name</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($user['full_name']); ?></dd>
                                    <dt class="col-sm-3">Username</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($user['username']); ?></dd>
                                    <dt class="col-sm-3">Email</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($user['email']); ?></dd>
                                    <dt class="col-sm-3">Member Since</dt>
                                    <dd class="col-sm-9"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></dd>
                              </dl>
                        </div>
                  </div>
            </div>
      </div>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>