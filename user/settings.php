<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
      header('Location: ../login.php');
      exit();
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $current_password = $_POST['current_password'];
      $new_password = $_POST['new_password'];
      $confirm_password = $_POST['confirm_password'];

      $pdo = getDBConnection();
      $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
      $stmt->execute([$_SESSION['user_id']]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect';
      } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
      } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long';
      } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                  $message = 'Password updated successfully';
            } else {
                  $error = 'Failed to update password';
            }
      }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Settings - POS System</title>

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
                                                <li class="breadcrumb-item active" aria-current="page">Settings</li>
                                          </ol>
                                    </nav>
                              </div>
                        </div>
                  </nav>
                  <div class="admin-content">
                        <div class="container-fluid mt-4">
                              <div class="row justify-content-center">
                                    <div class="col-md-6">
                                          <div class="card">
                                                <div class="card-header">
                                                      <h4 class="mb-0"><i class="fas fa-cog me-2"></i>Account Settings</h4>
                                                </div>
                                                <div class="card-body">
                                                      <?php if ($message): ?>
                                                            <div class="alert alert-success"><?php echo $message; ?></div>
                                                      <?php endif; ?>
                                                      <?php if ($error): ?>
                                                            <div class="alert alert-danger"><?php echo $error; ?></div>
                                                      <?php endif; ?>

                                                      <form method="POST">
                                                            <div class="mb-3">
                                                                  <label for="current_password" class="form-label">Current Password</label>
                                                                  <input type="password" class="form-control" id="current_password" name="current_password" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                  <label for="new_password" class="form-label">New Password</label>
                                                                  <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                                  <div class="form-text">Password must be at least 6 characters long</div>
                                                            </div>
                                                            <div class="mb-3">
                                                                  <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                                  <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                            </div>
                                                            <button type="submit" class="btn btn-primary">
                                                                  <i class="fas fa-save me-2"></i>Update Password
                                                            </button>
                                                      </form>
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