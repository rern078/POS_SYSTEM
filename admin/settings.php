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

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $pdo = getDBConnection();

      if (isset($_POST['update_notifications'])) {
            // Handle notification settings
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $low_stock_alerts = isset($_POST['low_stock_alerts']) ? 1 : 0;
            $sales_reports = isset($_POST['sales_reports']) ? 1 : 0;

            // In a real application, you would store these in a settings table
            // For now, we'll just show a success message
            $message = 'Notification settings updated successfully!';
            $messageType = 'success';
      } elseif (isset($_POST['update_security'])) {
            // Handle security settings
            $two_factor_auth = isset($_POST['two_factor_auth']) ? 1 : 0;
            $session_timeout = $_POST['session_timeout'];

            // In a real application, you would store these in a settings table
            $message = 'Security settings updated successfully!';
            $messageType = 'success';
      } elseif (isset($_POST['update_display'])) {
            // Handle display settings
            $theme = $_POST['theme'];
            $language = $_POST['language'];
            $timezone = $_POST['timezone'];

            // In a real application, you would store these in a settings table
            $message = 'Display settings updated successfully!';
            $messageType = 'success';
      }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <?php include 'include/title.inc.php'; ?>
</head>

<body>
      <div class="admin-layout">
            <?php include 'side.php'; ?>

            <!-- Main Content Wrapper -->
            <div class="admin-main">
                  <!-- Top Navigation Bar -->
                  <nav class="admin-topbar">
                        <div class="topbar-left">
                              <button class="btn btn-link sidebar-toggle-btn" id="sidebarToggleBtn">
                                    <i class="fas fa-bars"></i>
                              </button>
                              <div class="breadcrumb-container">
                                    <nav aria-label="breadcrumb">
                                          <ol class="breadcrumb">
                                                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                                                <li class="breadcrumb-item active" aria-current="page">Settings</li>
                                          </ol>
                                    </nav>
                              </div>
                        </div>
                        <div class="topbar-right">
                              <div class="topbar-actions">
                                    <div class="dropdown">
                                          <button class="btn btn-link notification-btn" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-bell"></i>
                                                <span class="notification-badge">3</span>
                                          </button>
                                          <ul class="dropdown-menu dropdown-menu-end notification-menu" aria-labelledby="notificationDropdown">
                                                <li>
                                                      <h6 class="dropdown-header">Notifications</h6>
                                                </li>
                                                <li><a class="dropdown-item" href="#"><i class="fas fa-exclamation-circle text-warning me-2"></i>Low stock alert</a></li>
                                                <li><a class="dropdown-item" href="#"><i class="fas fa-check-circle text-success me-2"></i>Order completed</a></li>
                                                <li><a class="dropdown-item" href="#"><i class="fas fa-info-circle text-info me-2"></i>New user registered</a></li>
                                                <li>
                                                      <hr class="dropdown-divider">
                                                </li>
                                                <li><a class="dropdown-item text-center" href="#">View all notifications</a></li>
                                          </ul>
                                    </div>
                              </div>
                        </div>
                  </nav>

                  <!-- Page Content -->
                  <div class="admin-content">
                        <!-- Page Header -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h1 class="content-card-title">
                                          <i class="fas fa-cog"></i>
                                          Settings
                                    </h1>
                                    <p class="text-muted mb-0">Configure your account and system preferences</p>
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
                                    <!-- Notification Settings -->
                                    <div class="content-card mb-4">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-bell"></i>
                                                      Notification Settings
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <form method="POST" action="">
                                                      <div class="mb-3">
                                                            <div class="form-check form-switch">
                                                                  <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" checked>
                                                                  <label class="form-check-label" for="email_notifications">
                                                                        Email Notifications
                                                                  </label>
                                                            </div>
                                                            <small class="text-muted">Receive email notifications for important events</small>
                                                      </div>

                                                      <div class="mb-3">
                                                            <div class="form-check form-switch">
                                                                  <input class="form-check-input" type="checkbox" id="low_stock_alerts" name="low_stock_alerts" checked>
                                                                  <label class="form-check-label" for="low_stock_alerts">
                                                                        Low Stock Alerts
                                                                  </label>
                                                            </div>
                                                            <small class="text-muted">Get notified when products are running low on stock</small>
                                                      </div>

                                                      <div class="mb-3">
                                                            <div class="form-check form-switch">
                                                                  <input class="form-check-input" type="checkbox" id="sales_reports" name="sales_reports">
                                                                  <label class="form-check-label" for="sales_reports">
                                                                        Daily Sales Reports
                                                                  </label>
                                                            </div>
                                                            <small class="text-muted">Receive daily sales summary reports</small>
                                                      </div>

                                                      <button type="submit" name="update_notifications" class="btn btn-primary">
                                                            <i class="fas fa-save me-2"></i>Update Notifications
                                                      </button>
                                                </form>
                                          </div>
                                    </div>

                                    <!-- Security Settings -->
                                    <div class="content-card mb-4">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-shield-alt"></i>
                                                      Security Settings
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <form method="POST" action="">
                                                      <div class="mb-3">
                                                            <div class="form-check form-switch">
                                                                  <input class="form-check-input" type="checkbox" id="two_factor_auth" name="two_factor_auth">
                                                                  <label class="form-check-label" for="two_factor_auth">
                                                                        Two-Factor Authentication
                                                                  </label>
                                                            </div>
                                                            <small class="text-muted">Add an extra layer of security to your account</small>
                                                      </div>

                                                      <div class="mb-3">
                                                            <label for="session_timeout" class="form-label">Session Timeout</label>
                                                            <select class="form-select" id="session_timeout" name="session_timeout">
                                                                  <option value="15">15 minutes</option>
                                                                  <option value="30" selected>30 minutes</option>
                                                                  <option value="60">1 hour</option>
                                                                  <option value="120">2 hours</option>
                                                                  <option value="0">Never (until logout)</option>
                                                            </select>
                                                            <small class="text-muted">Automatically log out after inactivity</small>
                                                      </div>

                                                      <button type="submit" name="update_security" class="btn btn-primary">
                                                            <i class="fas fa-save me-2"></i>Update Security
                                                      </button>
                                                </form>
                                          </div>
                                    </div>

                                    <!-- Display Settings -->
                                    <div class="content-card mb-4">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-palette"></i>
                                                      Display Settings
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <form method="POST" action="">
                                                      <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                  <label for="theme" class="form-label">Theme</label>
                                                                  <select class="form-select" id="theme" name="theme">
                                                                        <option value="light" selected>Light</option>
                                                                        <option value="dark">Dark</option>
                                                                        <option value="auto">Auto (System)</option>
                                                                  </select>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                  <label for="language" class="form-label">Language</label>
                                                                  <select class="form-select" id="language" name="language">
                                                                        <option value="en" selected>English</option>
                                                                        <option value="es">Spanish</option>
                                                                        <option value="fr">French</option>
                                                                        <option value="de">German</option>
                                                                  </select>
                                                            </div>
                                                      </div>

                                                      <div class="mb-3">
                                                            <label for="timezone" class="form-label">Timezone</label>
                                                            <select class="form-select" id="timezone" name="timezone">
                                                                  <option value="UTC">UTC</option>
                                                                  <option value="America/New_York">Eastern Time</option>
                                                                  <option value="America/Chicago">Central Time</option>
                                                                  <option value="America/Denver">Mountain Time</option>
                                                                  <option value="America/Los_Angeles">Pacific Time</option>
                                                                  <option value="Europe/London">London</option>
                                                                  <option value="Europe/Paris">Paris</option>
                                                                  <option value="Asia/Tokyo">Tokyo</option>
                                                            </select>
                                                      </div>

                                                      <button type="submit" name="update_display" class="btn btn-primary">
                                                            <i class="fas fa-save me-2"></i>Update Display
                                                      </button>
                                                </form>
                                          </div>
                                    </div>
                              </div>

                              <div class="col-lg-4">
                                    <!-- Quick Actions -->
                                    <div class="content-card mb-4">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-bolt"></i>
                                                      Quick Actions
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="d-grid gap-2">
                                                      <a href="profile.php" class="btn btn-outline-primary">
                                                            <i class="fas fa-user me-2"></i>Edit Profile
                                                      </a>
                                                      <a href="users.php" class="btn btn-outline-info">
                                                            <i class="fas fa-users me-2"></i>Manage Users
                                                      </a>
                                                      <a href="reports.php" class="btn btn-outline-success">
                                                            <i class="fas fa-chart-bar me-2"></i>View Reports
                                                      </a>
                                                      <a href="index.php" class="btn btn-outline-secondary">
                                                            <i class="fas fa-tachometer-alt me-2"></i>Back to Dashboard
                                                      </a>
                                                </div>
                                          </div>
                                    </div>

                                    <!-- System Information -->
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-info-circle"></i>
                                                      System Information
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="mb-3">
                                                      <small class="text-muted">PHP Version</small>
                                                      <div class="fw-bold"><?php echo PHP_VERSION; ?></div>
                                                </div>
                                                <div class="mb-3">
                                                      <small class="text-muted">Server Time</small>
                                                      <div class="fw-bold"><?php echo date('Y-m-d H:i:s'); ?></div>
                                                </div>
                                                <div class="mb-3">
                                                      <small class="text-muted">Database</small>
                                                      <div class="fw-bold">MySQL</div>
                                                </div>
                                                <div class="mb-3">
                                                      <small class="text-muted">Last Login</small>
                                                      <div class="fw-bold"><?php echo date('M d, Y H:i'); ?></div>
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
      <script src="assets/js/admin.js"></script>
</body>

</html>