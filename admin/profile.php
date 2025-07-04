<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || !isAdmin()) {
      header('Location: login.php');
      exit();
}

$user = getCurrentUser();
$message = '';
$messageType = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $username = trim($_POST['username']);
      $email = trim($_POST['email']);
      $current_password = $_POST['current_password'];
      $new_password = $_POST['new_password'];
      $confirm_password = $_POST['confirm_password'];

      $pdo = getDBConnection();

      // Validate current password
      if (!empty($current_password)) {
            if (!password_verify($current_password, $user['password'])) {
                  $message = 'Current password is incorrect.';
                  $messageType = 'danger';
            } else {
                  // Check if username is already taken by another user
                  $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                  $stmt->execute([$username, $user['id']]);
                  if ($stmt->fetch()) {
                        $message = 'Username is already taken.';
                        $messageType = 'danger';
                  } else {
                        // Check if email is already taken by another user
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $stmt->execute([$email, $user['id']]);
                        if ($stmt->fetch()) {
                              $message = 'Email is already taken.';
                              $messageType = 'danger';
                        } else {
                              // Update profile
                              if (!empty($new_password)) {
                                    if ($new_password !== $confirm_password) {
                                          $message = 'New passwords do not match.';
                                          $messageType = 'danger';
                                    } else {
                                          $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                          $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, updated_at = NOW() WHERE id = ?");
                                          $stmt->execute([$username, $email, $hashed_password, $user['id']]);

                                          // Update session
                                          $_SESSION['username'] = $username;
                                          $_SESSION['email'] = $email;

                                          $message = 'Profile updated successfully!';
                                          $messageType = 'success';

                                          // Refresh user data
                                          $user = getCurrentUser();
                                    }
                              } else {
                                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, updated_at = NOW() WHERE id = ?");
                                    $stmt->execute([$username, $email, $user['id']]);

                                    // Update session
                                    $_SESSION['username'] = $username;
                                    $_SESSION['email'] = $email;

                                    $message = 'Profile updated successfully!';
                                    $messageType = 'success';

                                    // Refresh user data
                                    $user = getCurrentUser();
                              }
                        }
                  }
            }
      } else {
            $message = 'Please enter your current password to make changes.';
            $messageType = 'danger';
      }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Profile - Admin Dashboard</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="assets/css/admin.css">
</head>

<body>
      <!-- Navigation -->
      <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                  <a class="navbar-brand" href="index.php">
                        <i class="fas fa-store me-2"></i>POS Admin
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
                                    <a class="nav-link" href="products.php">
                                          <i class="fas fa-box me-1"></i>Products
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="sales.php">
                                          <i class="fas fa-chart-line me-1"></i>Sales
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="inventory.php">
                                          <i class="fas fa-warehouse me-1"></i>Inventory
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="users.php">
                                          <i class="fas fa-users me-1"></i>Users
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="reports.php">
                                          <i class="fas fa-file-alt me-1"></i>Reports
                                    </a>
                              </li>
                        </ul>

                        <ul class="navbar-nav">
                              <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                          <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['username']; ?>
                                    </a>
                                    <ul class="dropdown-menu">
                                          <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                          <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                          <li>
                                                <hr class="dropdown-divider">
                                          </li>
                                          <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                    </ul>
                              </li>
                        </ul>
                  </div>
            </div>
      </nav>

      <!-- Main Content -->
      <div class="container-fluid mt-4">
            <!-- Page Header -->
            <div class="row mb-4">
                  <div class="col-12">
                        <h1 class="h3 mb-0 text-gray-800">Profile</h1>
                        <p class="text-muted">Manage your account information</p>
                  </div>
            </div>

            <?php if (!empty($message)): ?>
                  <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>
            <?php endif; ?>

            <div class="row">
                  <div class="col-lg-8">
                        <div class="card shadow">
                              <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Profile Information</h6>
                              </div>
                              <div class="card-body">
                                    <form method="POST" action="">
                                          <div class="row">
                                                <div class="col-md-6 mb-3">
                                                      <label for="username" class="form-label">Username</label>
                                                      <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                      <label for="email" class="form-label">Email</label>
                                                      <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                </div>
                                          </div>

                                          <div class="row">
                                                <div class="col-md-6 mb-3">
                                                      <label for="role" class="form-label">Role</label>
                                                      <input type="text" class="form-control" id="role" value="<?php echo ucfirst($user['role']); ?>" readonly>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                      <label for="created_at" class="form-label">Member Since</label>
                                                      <input type="text" class="form-control" id="created_at" value="<?php echo date('M d, Y', strtotime($user['created_at'])); ?>" readonly>
                                                </div>
                                          </div>

                                          <hr class="my-4">

                                          <h6 class="font-weight-bold text-primary mb-3">Change Password</h6>
                                          <div class="row">
                                                <div class="col-md-12 mb-3">
                                                      <label for="current_password" class="form-label">Current Password</label>
                                                      <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Enter current password">
                                                </div>
                                          </div>

                                          <div class="row">
                                                <div class="col-md-6 mb-3">
                                                      <label for="new_password" class="form-label">New Password</label>
                                                      <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter new password">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                      <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                      <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                                                </div>
                                          </div>

                                          <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-primary">
                                                      <i class="fas fa-save me-2"></i>Update Profile
                                                </button>
                                          </div>
                                    </form>
                              </div>
                        </div>
                  </div>

                  <div class="col-lg-4">
                        <div class="card shadow">
                              <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Account Summary</h6>
                              </div>
                              <div class="card-body">
                                    <div class="text-center mb-4">
                                          <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                                <i class="fas fa-user fa-3x text-white"></i>
                                          </div>
                                          <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($user['username']); ?></h5>
                                          <p class="text-muted"><?php echo ucfirst($user['role']); ?></p>
                                    </div>

                                    <div class="row text-center">
                                          <div class="col-6">
                                                <div class="border-end">
                                                      <h6 class="text-primary mb-1"><?php echo date('M Y', strtotime($user['created_at'])); ?></h6>
                                                      <small class="text-muted">Joined</small>
                                                </div>
                                          </div>
                                          <div class="col-6">
                                                <h6 class="text-success mb-1">Active</h6>
                                                <small class="text-muted">Status</small>
                                          </div>
                                    </div>

                                    <hr class="my-4">

                                    <div class="d-grid">
                                          <a href="settings.php" class="btn btn-outline-primary">
                                                <i class="fas fa-cog me-2"></i>Account Settings
                                          </a>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>
</body>

</html>