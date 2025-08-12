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
                                    <i class="fas fa-key"></i> Change Password
                              </h1>
                        </div>
                        <div class="content-card-body">
                              <!-- Success/Error messages placeholder -->
                              <?php if (isset($_SESSION['change_password_success'])): ?>
                                    <div class="alert alert-success">
                                          <i class="fas fa-check-circle me-2"></i>
                                          Password changed successfully.
                                    </div>
                                    <?php unset($_SESSION['change_password_success']); ?>
                              <?php elseif (isset($_SESSION['change_password_error'])): ?>
                                    <div class="alert alert-danger">
                                          <i class="fas fa-exclamation-circle me-2"></i>
                                          <?php echo htmlspecialchars($_SESSION['change_password_error']); ?>
                                    </div>
                                    <?php unset($_SESSION['change_password_error']); ?>
                              <?php endif; ?>

                              <form method="post" action="" class="needs-validation" novalidate>
                                    <div class="row">
                                          <div class="col-12">
                                                <div class="mb-4">
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
                                          <div class="col-12">
                                                <div class="mb-4">
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
                                          <div class="col-12">
                                                <div class="mb-4">
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
                                          <div class="col-12">
                                                <button type="submit" class="btn btn-modern btn-primary w-100">
                                                      <i class="fas fa-save me-2"></i>Change Password
                                                </button>
                                          </div>
                                    </div>
                              </form>
                        </div>
                  </div>
            </div>
      </div>

      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
      </script>
</body>

</html>