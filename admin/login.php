<?php
session_start();
require_once '../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
      header('Location: index.php');
      exit();
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $username = trim($_POST['username'] ?? '');
      $password = $_POST['password'] ?? '';

      if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
      } else {
            if (login($username, $password)) {
                  header('Location: index.php');
                  exit();
            } else {
                  $error = 'Invalid username or password.';
            }
      }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Admin Login - POS System</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="assets/css/admin.css">
</head>

<body class="bg-gradient-primary">
      <div class="container">
            <div class="row justify-content-center">
                  <div class="col-xl-10 col-lg-12 col-md-9">
                        <div class="card login-card">
                              <div class="card-body p-0">
                                    <div class="row g-0">
                                          <div class="col-lg-6 d-none d-lg-block bg-login-image"></div>
                                          <div class="col-lg-6">
                                                <div class="login-form-container">
                                                      <div class="text-center">
                                                            <h1 class="login-title">
                                                                  <i class="fas fa-shield-alt"></i>
                                                                  Admin Access
                                                            </h1>
                                                      </div>

                                                      <?php if ($error): ?>
                                                            <div class="alert alert-danger" role="alert">
                                                                  <i class="fas fa-exclamation-triangle me-2"></i>
                                                                  <?php echo htmlspecialchars($error); ?>
                                                            </div>
                                                      <?php endif; ?>

                                                      <form class="user" method="POST" action="">
                                                            <div class="form-group mb-4">
                                                                  <div class="input-group">
                                                                        <span class="input-group-text">
                                                                              <i class="fas fa-user"></i>
                                                                        </span>
                                                                        <input type="text" class="form-control form-control-user"
                                                                              name="username" placeholder="Enter Username..."
                                                                              value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                                                              required>
                                                                  </div>
                                                            </div>

                                                            <div class="form-group mb-4">
                                                                  <div class="input-group">
                                                                        <span class="input-group-text">
                                                                              <i class="fas fa-lock"></i>
                                                                        </span>
                                                                        <input type="password" class="form-control form-control-user"
                                                                              name="password" placeholder="Password" required>
                                                                  </div>
                                                            </div>

                                                            <div class="form-group mb-4">
                                                                  <div class="form-check">
                                                                        <input type="checkbox" class="form-check-input" id="customCheck">
                                                                        <label class="form-check-label" for="customCheck">
                                                                              Remember Me
                                                                        </label>
                                                                  </div>
                                                            </div>

                                                            <button type="submit" class="btn btn-primary btn-user w-100">
                                                                  <i class="fas fa-sign-in-alt me-2"></i>
                                                                  Access Admin Panel
                                                            </button>
                                                      </form>

                                                      <hr class="my-4">

                                                      <div class="login-links">
                                                            <div class="text-center mb-2">
                                                                  <a href="forgot-password.php">Forgot Password?</a>
                                                            </div>

                                                            <div class="text-center">
                                                                  <a href="../index.php">
                                                                        <i class="fas fa-arrow-left me-1"></i>
                                                                        Back to POS
                                                                  </a>
                                                            </div>
                                                      </div>

                                                      <div class="login-credentials">
                                                            <small>
                                                                  <strong>Default Admin Credentials:</strong><br>
                                                                  Username: admin<br>
                                                                  Password: admin123
                                                            </small>
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