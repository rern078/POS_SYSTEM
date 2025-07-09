<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
      if ($_SESSION['role'] === 'admin') {
            header('Location: admin/index.php');
      } elseif ($_SESSION['role'] === 'customer') {
            // Customers should stay on the main page (root)
            header('Location: index.php');
      } else {
            // Staff members (cashier, manager) go to user dashboard
            header('Location: user/index.php');
      }
      exit();
}

$error = '';
$success = '';

// Handle customer registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $username = trim($_POST['username'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $password = $_POST['password'] ?? '';
      $confirm_password = $_POST['confirm_password'] ?? '';
      $full_name = trim($_POST['full_name'] ?? '');
      $phone = trim($_POST['phone'] ?? '');

      // Validation
      if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
            $error = 'All required fields must be filled.';
      } elseif (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters long.';
      } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
      } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
      } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
      } else {
            try {
                  $pdo = getDBConnection();

                  // Check if username already exists
                  $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                  $stmt->execute([$username]);
                  if ($stmt->fetch()) {
                        $error = 'Username already exists.';
                  } else {
                        // Check if email already exists
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        if ($stmt->fetch()) {
                              $error = 'Email already exists.';
                        } else {
                              // Hash password and insert customer
                              $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                              $role = 'customer';

                              $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone, role) VALUES (?, ?, ?, ?, ?, ?)");

                              if ($stmt->execute([$username, $email, $hashedPassword, $full_name, $phone, $role])) {
                                    $success = 'Registration successful! You can now login to access your account and order history.';
                                    // Clear form data
                                    $_POST = array();

                                    // If redirect parameter is set, redirect to checkout
                                    if (isset($_GET['redirect']) && $_GET['redirect'] === 'checkout') {
                                          header('Location: login.php?redirect=checkout');
                                          exit();
                                    } else {
                                          // Redirect customers to homepage after registration
                                          header('Location: index.php?registered=1');
                                          exit();
                                    }
                              } else {
                                    $error = 'Registration failed. Please try again.';
                              }
                        }
                  }
            } catch (PDOException $e) {
                  $error = 'Database error: ' . $e->getMessage();
            }
      }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Customer Registration - POS System</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="admin/assets/css/admin.css">
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
                                                                  <i class="fas fa-user-plus"></i>
                                                                  Customer Registration
                                                            </h1>
                                                            <p class="text-muted">Create your customer account</p>
                                                      </div>

                                                      <?php if ($error): ?>
                                                            <div class="alert alert-danger" role="alert">
                                                                  <i class="fas fa-exclamation-triangle me-2"></i>
                                                                  <?php echo htmlspecialchars($error); ?>
                                                            </div>
                                                      <?php endif; ?>

                                                      <?php if ($success): ?>
                                                            <div class="alert alert-success" role="alert">
                                                                  <i class="fas fa-check-circle me-2"></i>
                                                                  <?php echo htmlspecialchars($success); ?>
                                                            </div>
                                                      <?php endif; ?>

                                                      <form class="user" method="POST" action="">
                                                            <div class="form-group mb-4">
                                                                  <div class="input-group">
                                                                        <span class="input-group-text">
                                                                              <i class="fas fa-user"></i>
                                                                        </span>
                                                                        <input type="text" class="form-control form-control-user"
                                                                              name="full_name" placeholder="Enter Full Name..."
                                                                              value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                                                                              required>
                                                                  </div>
                                                            </div>

                                                            <div class="form-group mb-4">
                                                                  <div class="input-group">
                                                                        <span class="input-group-text">
                                                                              <i class="fas fa-at"></i>
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
                                                                              <i class="fas fa-envelope"></i>
                                                                        </span>
                                                                        <input type="email" class="form-control form-control-user"
                                                                              name="email" placeholder="Enter Email Address..."
                                                                              value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                                                              required>
                                                                  </div>
                                                            </div>

                                                            <div class="form-group mb-4">
                                                                  <div class="input-group">
                                                                        <span class="input-group-text">
                                                                              <i class="fas fa-phone"></i>
                                                                        </span>
                                                                        <input type="tel" class="form-control form-control-user"
                                                                              name="phone" placeholder="Enter Phone Number (Optional)..."
                                                                              value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
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
                                                                  <div class="input-group">
                                                                        <span class="input-group-text">
                                                                              <i class="fas fa-lock"></i>
                                                                        </span>
                                                                        <input type="password" class="form-control form-control-user"
                                                                              name="confirm_password" placeholder="Confirm Password" required>
                                                                  </div>
                                                            </div>

                                                            <button type="submit" class="btn btn-primary btn-user w-100">
                                                                  <i class="fas fa-user-plus me-2"></i>
                                                                  Create Customer Account
                                                            </button>
                                                      </form>

                                                      <hr class="my-4">

                                                      <div class="login-links">
                                                            <div class="text-center mb-2">
                                                                  <a href="login.php">Already have an account? Login!</a>
                                                            </div>

                                                            <div class="text-center mb-2">
                                                                  <a href="register.php">Register as Staff Member</a>
                                                            </div>

                                                            <div class="text-center">
                                                                  <a href="index.php">
                                                                        <i class="fas fa-arrow-left me-1"></i>
                                                                        Back to POS
                                                                  </a>
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
</body>

</html>