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
      <!-- Google Fonts -->
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="../assets/css/user.css">
</head>

<body>
      <div class="user-layout">
            <?php include 'side.php'; ?>
            <div class="user-main" id="userMain">
                  <nav class="user-topbar">
                        <div class="topbar-left">
                              <button class="sidebar-toggle-btn" id="sidebarToggleBtn">
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
                  <div class="user-content">
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h1 class="content-card-title">
                                          <i class="fas fa-cog"></i>
                                          Account Settings
                                    </h1>
                              </div>
                              <div class="content-card-body">
                                    <?php if ($message): ?>
                                          <div class="alert alert-success">
                                                <i class="fas fa-check-circle me-2"></i>
                                                <?php echo $message; ?>
                                          </div>
                                    <?php endif; ?>
                                    <?php if ($error): ?>
                                          <div class="alert alert-danger">
                                                <i class="fas fa-exclamation-circle me-2"></i>
                                                <?php echo $error; ?>
                                          </div>
                                    <?php endif; ?>

                                    <form method="POST" class="needs-validation" novalidate>
                                          <div class="row">
                                                <div class="col-md-6">
                                                      <div class="mb-3">
                                                            <label for="current_password" class="form-label">
                                                                  <i class="fas fa-lock me-2"></i>Current Password
                                                            </label>
                                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                                            <div class="invalid-feedback">
                                                                  Please enter your current password.
                                                            </div>
                                                      </div>
                                                </div>
                                          </div>

                                          <div class="row">
                                                <div class="col-md-6">
                                                      <div class="mb-3">
                                                            <label for="new_password" class="form-label">
                                                                  <i class="fas fa-key me-2"></i>New Password
                                                            </label>
                                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                            <div class="invalid-feedback">
                                                                  Please enter a new password.
                                                            </div>
                                                      </div>
                                                </div>
                                          </div>

                                          <div class="row">
                                                <div class="col-md-6">
                                                      <div class="mb-3">
                                                            <label for="confirm_password" class="form-label">
                                                                  <i class="fas fa-check-circle me-2"></i>Confirm New Password
                                                            </label>
                                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                            <div class="invalid-feedback">
                                                                  Please confirm your new password.
                                                            </div>
                                                      </div>
                                                </div>
                                          </div>

                                          <div class="row">
                                                <div class="col-md-6">
                                                      <button type="submit" class="btn btn-modern btn-primary">
                                                            <i class="fas fa-save me-2"></i>Update Password
                                                      </button>
                                                </div>
                                          </div>
                                    </form>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

      <!-- Custom JS -->
      <script>
            // Form validation
            (function() {
                  'use strict';
                  window.addEventListener('load', function() {
                        var forms = document.getElementsByClassName('needs-validation');
                        var validation = Array.prototype.filter.call(forms, function(form) {
                              form.addEventListener('submit', function(event) {
                                    if (form.checkValidity() === false) {
                                          event.preventDefault();
                                          event.stopPropagation();
                                    }
                                    form.classList.add('was-validated');
                              }, false);
                        });
                  }, false);
            })();

            // Sidebar toggle functionality
            document.getElementById('sidebarToggleBtn').addEventListener('click', function() {
                  const sidebar = document.getElementById('adminSidebar');
                  const main = document.getElementById('userMain');

                  sidebar.classList.toggle('collapsed');
                  main.classList.toggle('sidebar-collapsed');
            });

            // Mobile sidebar toggle
            if (window.innerWidth <= 768) {
                  document.getElementById('sidebarToggleBtn').addEventListener('click', function() {
                        const sidebar = document.getElementById('adminSidebar');
                        sidebar.classList.toggle('show');
                  });
            }
      </script>
</body>

</html>