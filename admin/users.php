<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || !isAdmin()) {
      header('Location: login.php');
      exit();
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $pdo = getDBConnection();

      if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                  case 'add_user':
                        $username = trim($_POST['username']);
                        $email = trim($_POST['email']);
                        $password = $_POST['password'];
                        $role = $_POST['role'];

                        // Check if username or email already exists
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                        $stmt->execute([$username, $email]);
                        if ($stmt->fetch()) {
                              $error = "Username or email already exists!";
                        } else {
                              $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                              $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                              if ($stmt->execute([$username, $email, $hashed_password, $role])) {
                                    $success = "User added successfully!";
                              } else {
                                    $error = "Error adding user!";
                              }
                        }
                        break;

                  case 'update_user':
                        $user_id = $_POST['user_id'];
                        $email = trim($_POST['email']);
                        $role = $_POST['role'];

                        $stmt = $pdo->prepare("UPDATE users SET email = ?, role = ? WHERE id = ?");
                        if ($stmt->execute([$email, $role, $user_id])) {
                              $success = "User updated successfully!";
                        } else {
                              $error = "Error updating user!";
                        }
                        break;

                  case 'delete_user':
                        $user_id = $_POST['user_id'];

                        // Don't allow admin to delete themselves
                        if ($user_id == $_SESSION['user_id']) {
                              $error = "You cannot delete your own account!";
                        } else {
                              $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                              if ($stmt->execute([$user_id])) {
                                    $success = "User deleted successfully!";
                              } else {
                                    $error = "Error deleting user!";
                              }
                        }
                        break;
            }
      }
}

// Get users data
$pdo = getDBConnection();

// Get users statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_users,
    COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count,
    COUNT(CASE WHEN role = 'manager' THEN 1 END) as manager_count,
    COUNT(CASE WHEN role = 'cashier' THEN 1 END) as cashier_count
FROM users");
$stmt->execute();
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all users
$stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent user activity (placeholder - would need activity tracking table)
$recent_activity = [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>User Management - POS System</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="assets/css/admin.css">
      <!-- Chart.js -->
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                                    <a class="nav-link active" href="users.php">
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
                                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                          <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['username']; ?>
                                    </a>
                                    <ul class="dropdown-menu">
                                          <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
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
                        <h1 class="h3 mb-0 text-gray-800">User Management</h1>
                        <p class="text-muted">Manage system users and their permissions</p>
                  </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($error)): ?>
                  <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                  <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>
            <?php endif; ?>

            <!-- User Statistics Cards -->
            <div class="row mb-4">
                  <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                      Total Users
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      <?php echo $user_stats['total_users']; ?>
                                                </div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-users fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>

                  <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                      Administrators
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      <?php echo $user_stats['admin_count']; ?>
                                                </div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>

                  <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                      Managers
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      <?php echo $user_stats['manager_count']; ?>
                                                </div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>

                  <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                      Cashiers
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      <?php echo $user_stats['cashier_count']; ?>
                                                </div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-user fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                  <!-- User Roles Chart -->
                  <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                              <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">User Roles Distribution</h6>
                              </div>
                              <div class="card-body">
                                    <div class="chart-area">
                                          <canvas id="userRolesChart"></canvas>
                                    </div>
                              </div>
                        </div>
                  </div>

                  <!-- User Activity Chart -->
                  <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                              <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Recent User Activity</h6>
                              </div>
                              <div class="card-body">
                                    <div class="small text-muted mb-2">User registration timeline</div>
                                    <div class="chart-pie pt-4 pb-2">
                                          <canvas id="userActivityChart"></canvas>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>

            <!-- Users Table -->
            <div class="row">
                  <div class="col-12">
                        <div class="card shadow mb-4">
                              <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold text-primary">All Users</h6>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                          <i class="fas fa-plus me-1"></i>Add User
                                    </button>
                              </div>
                              <div class="card-body">
                                    <div class="table-responsive">
                                          <table class="table table-bordered" id="usersTable" width="100%" cellspacing="0">
                                                <thead>
                                                      <tr>
                                                            <th>Username</th>
                                                            <th>Email</th>
                                                            <th>Role</th>
                                                            <th>Status</th>
                                                            <th>Created</th>
                                                            <th>Last Updated</th>
                                                            <th>Actions</th>
                                                      </tr>
                                                </thead>
                                                <tbody>
                                                      <?php foreach ($users as $user): ?>
                                                            <tr>
                                                                  <td>
                                                                        <div class="d-flex align-items-center">
                                                                              <div class="avatar-sm me-2">
                                                                                    <i class="fas fa-user-circle fa-2x text-primary"></i>
                                                                              </div>
                                                                              <div>
                                                                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                                                          <span class="badge bg-info ms-1">You</span>
                                                                                    <?php endif; ?>
                                                                              </div>
                                                                        </div>
                                                                  </td>
                                                                  <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                                  <td>
                                                                        <?php
                                                                        $role_badges = [
                                                                              'admin' => 'danger',
                                                                              'manager' => 'warning',
                                                                              'cashier' => 'info'
                                                                        ];
                                                                        $role_names = [
                                                                              'admin' => 'Administrator',
                                                                              'manager' => 'Manager',
                                                                              'cashier' => 'Cashier'
                                                                        ];
                                                                        ?>
                                                                        <span class="badge bg-<?php echo $role_badges[$user['role']]; ?>">
                                                                              <?php echo $role_names[$user['role']]; ?>
                                                                        </span>
                                                                  </td>
                                                                  <td>
                                                                        <span class="badge bg-success">Active</span>
                                                                  </td>
                                                                  <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                                  <td><?php echo date('M d, Y H:i', strtotime($user['updated_at'])); ?></td>
                                                                  <td>
                                                                        <button class="btn btn-sm btn-primary" onclick="editUser(
                                                                            <?php echo $user['id']; ?>, 
                                                                            '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>', 
                                                                            '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>', 
                                                                            '<?php echo $user['role']; ?>'
                                                                        )">
                                                                              <i class="fas fa-edit"></i>
                                                                        </button>
                                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                                              <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                                                    <i class="fas fa-trash"></i>
                                                                              </button>
                                                                        <?php endif; ?>
                                                                  </td>
                                                            </tr>
                                                      <?php endforeach; ?>
                                                </tbody>
                                          </table>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Add User Modal -->
      <div class="modal fade" id="addUserModal" tabindex="-1">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Add New User</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="add_user">

                                    <div class="mb-3">
                                          <label for="username" class="form-label">Username</label>
                                          <input type="text" class="form-control" id="username" name="username" required>
                                    </div>

                                    <div class="mb-3">
                                          <label for="email" class="form-label">Email</label>
                                          <input type="email" class="form-control" id="email" name="email" required>
                                    </div>

                                    <div class="mb-3">
                                          <label for="password" class="form-label">Password</label>
                                          <input type="password" class="form-control" id="password" name="password" required>
                                    </div>

                                    <div class="mb-3">
                                          <label for="role" class="form-label">Role</label>
                                          <select class="form-select" id="role" name="role" required>
                                                <option value="">Select Role</option>
                                                <option value="admin">Administrator</option>
                                                <option value="manager">Manager</option>
                                                <option value="cashier">Cashier</option>
                                          </select>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add User</button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Edit User Modal -->
      <div class="modal fade" id="editUserModal" tabindex="-1">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Edit User</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="update_user">
                                    <input type="hidden" name="user_id" id="edit_user_id">

                                    <div class="mb-3">
                                          <label for="edit_username" class="form-label">Username</label>
                                          <input type="text" class="form-control" id="edit_username" readonly>
                                    </div>

                                    <div class="mb-3">
                                          <label for="edit_email" class="form-label">Email</label>
                                          <input type="email" class="form-control" id="edit_email" name="email" required>
                                    </div>

                                    <div class="mb-3">
                                          <label for="edit_role" class="form-label">Role</label>
                                          <select class="form-select" id="edit_role" name="role" required>
                                                <option value="admin">Administrator</option>
                                                <option value="manager">Manager</option>
                                                <option value="cashier">Cashier</option>
                                          </select>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update User</button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Delete User Modal -->
      <div class="modal fade" id="deleteUserModal" tabindex="-1">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Delete User</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                              <p>Are you sure you want to delete user <strong id="delete_username"></strong>?</p>
                              <p class="text-danger">This action cannot be undone.</p>
                        </div>
                        <form method="POST">
                              <input type="hidden" name="action" value="delete_user">
                              <input type="hidden" name="user_id" id="delete_user_id">
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Delete User</button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>

      <script>
            // User Roles Chart
            const userRolesCtx = document.getElementById('userRolesChart').getContext('2d');
            const userRolesChart = new Chart(userRolesCtx, {
                  type: 'bar',
                  data: {
                        labels: ['Administrators', 'Managers', 'Cashiers'],
                        datasets: [{
                              label: 'Number of Users',
                              data: [
                                    <?php echo $user_stats['admin_count']; ?>,
                                    <?php echo $user_stats['manager_count']; ?>,
                                    <?php echo $user_stats['cashier_count']; ?>
                              ],
                              backgroundColor: [
                                    'rgba(220, 53, 69, 0.6)',
                                    'rgba(255, 193, 7, 0.6)',
                                    'rgba(23, 162, 184, 0.6)'
                              ],
                              borderColor: [
                                    'rgb(220, 53, 69)',
                                    'rgb(255, 193, 7)',
                                    'rgb(23, 162, 184)'
                              ],
                              borderWidth: 1
                        }]
                  },
                  options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                              y: {
                                    beginAtZero: true,
                                    ticks: {
                                          stepSize: 1
                                    }
                              }
                        }
                  }
            });

            // User Activity Chart (placeholder)
            const userActivityCtx = document.getElementById('userActivityChart').getContext('2d');
            const userActivityChart = new Chart(userActivityCtx, {
                  type: 'doughnut',
                  data: {
                        labels: ['This Week', 'This Month', 'Older'],
                        datasets: [{
                              data: [2, 3, <?php echo $user_stats['total_users'] - 5; ?>],
                              backgroundColor: [
                                    '#28a745',
                                    '#17a2b8',
                                    '#6c757d'
                              ]
                        }]
                  },
                  options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                              legend: {
                                    position: 'bottom'
                              }
                        }
                  }
            });

            // User management functions
            function editUser(userId, username, email, role) {
                  document.getElementById('edit_user_id').value = userId;
                  document.getElementById('edit_username').value = username;
                  document.getElementById('edit_email').value = email;
                  document.getElementById('edit_role').value = role;
                  new bootstrap.Modal(document.getElementById('editUserModal')).show();
            }

            function deleteUser(userId, username) {
                  document.getElementById('delete_user_id').value = userId;
                  document.getElementById('delete_username').textContent = username;
                  new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
            }
      </script>
</body>

</html>