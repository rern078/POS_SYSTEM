<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || !isAdmin()) {
      header('Location: login.php');
      exit();
}

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle user operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                  case 'add':
                        $username = trim($_POST['username']);
                        $email = trim($_POST['email']);
                        $password = $_POST['password'];
                        $role = $_POST['role'];
                        $full_name = trim($_POST['full_name']);

                        if (empty($username) || empty($email) || empty($password)) {
                              $error = 'Please fill in all required fields.';
                        } else {
                              // Check if username or email already exists
                              $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                              $stmt->execute([$username, $email]);
                              if ($stmt->fetch()) {
                                    $error = 'Username or email already exists.';
                              } else {
                                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, full_name) VALUES (?, ?, ?, ?, ?)");
                                    if ($stmt->execute([$username, $email, $hashed_password, $role, $full_name])) {
                                          $message = 'User added successfully!';
                                    } else {
                                          $error = 'Failed to add user.';
                                    }
                              }
                        }
                        break;

                  case 'update':
                        $id = intval($_POST['id']);
                        $username = trim($_POST['username']);
                        $email = trim($_POST['email']);
                        $role = $_POST['role'];
                        $full_name = trim($_POST['full_name']);
                        $password = $_POST['password'];

                        if (empty($username) || empty($email)) {
                              $error = 'Please fill in all required fields.';
                        } else {
                              // Check if username or email already exists for other users
                              $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                              $stmt->execute([$username, $email, $id]);
                              if ($stmt->fetch()) {
                                    $error = 'Username or email already exists.';
                              } else {
                                    if (!empty($password)) {
                                          $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                          $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ?, full_name = ? WHERE id = ?");
                                          $params = [$username, $email, $hashed_password, $role, $full_name, $id];
                                    } else {
                                          $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, full_name = ? WHERE id = ?");
                                          $params = [$username, $email, $role, $full_name, $id];
                                    }

                                    if ($stmt->execute($params)) {
                                          $message = 'User updated successfully!';
                                    } else {
                                          $error = 'Failed to update user.';
                                    }
                              }
                        }
                        break;

                  case 'delete':
                        $id = intval($_POST['id']);
                        // Prevent deleting own account
                        if ($id == $_SESSION['user_id']) {
                              $error = 'You cannot delete your own account.';
                        } else {
                              $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                              if ($stmt->execute([$id])) {
                                    $message = 'User deleted successfully!';
                              } else {
                                    $error = 'Failed to delete user.';
                              }
                        }
                        break;
            }
      }
}

// Get users with search and filter
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$sort = $_GET['sort'] ?? 'id';
$order = $_GET['order'] ?? 'ASC';

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

$whereConditions = [];
$params = [];

if (!empty($search)) {
      $whereConditions[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      $params[] = "%$search%";
}

if (!empty($role_filter)) {
      $whereConditions[] = "role = ?";
      $params[] = $role_filter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM users $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total_items = $countStmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

// Get paginated users
$sql = "SELECT * FROM users $whereClause ORDER BY $sort $order LIMIT $items_per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get roles for filter dropdown
$roles = ['admin', 'manager', 'cashier', 'user'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Users Management - POS Admin</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Google Fonts -->
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="assets/css/admin.css">
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
                                                <li class="breadcrumb-item active" aria-current="page">Users</li>
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
                                    <div class="d-flex justify-content-between align-items-center">
                                          <div>
                                                <h1 class="content-card-title">
                                                      <i class="fas fa-users"></i>
                                                      Users Management
                                                </h1>
                                                <p class="text-muted mb-0">Manage system users, roles, and permissions.</p>
                                          </div>
                                          <button class="btn btn-modern btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                                <i class="fas fa-plus me-2"></i>Add User
                                          </button>
                                    </div>
                              </div>
                        </div>

                        <!-- Messages -->
                        <?php if ($message): ?>
                              <div class="alert alert-modern alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                              </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                              <div class="alert alert-modern alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                              </div>
                        <?php endif; ?>

                        <!-- Filters and Search -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h6 class="content-card-title">
                                          <i class="fas fa-filter"></i>
                                          Filters & Search
                                    </h6>
                              </div>
                              <div class="content-card-body">
                                    <form method="GET" class="row g-3 form-modern">
                                          <div class="col-md-4">
                                                <label for="search" class="form-label">Search Users</label>
                                                <input type="text" class="form-control" id="search" name="search"
                                                      placeholder="Search by username, email, or name..."
                                                      value="<?php echo htmlspecialchars($search); ?>">
                                          </div>
                                          <div class="col-md-3">
                                                <label for="role" class="form-label">Role</label>
                                                <select class="form-select" id="role" name="role">
                                                      <option value="">All Roles</option>
                                                      <?php foreach ($roles as $role): ?>
                                                            <option value="<?php echo htmlspecialchars($role); ?>"
                                                                  <?php echo $role_filter === $role ? 'selected' : ''; ?>>
                                                                  <?php echo ucfirst(htmlspecialchars($role)); ?>
                                                            </option>
                                                      <?php endforeach; ?>
                                                </select>
                                          </div>
                                          <div class="col-md-2">
                                                <label for="sort" class="form-label">Sort By</label>
                                                <select class="form-select" id="sort" name="sort">
                                                      <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>ID</option>
                                                      <option value="username" <?php echo $sort === 'username' ? 'selected' : ''; ?>>Username</option>
                                                      <option value="email" <?php echo $sort === 'email' ? 'selected' : ''; ?>>Email</option>
                                                      <option value="role" <?php echo $sort === 'role' ? 'selected' : ''; ?>>Role</option>
                                                      <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                                                </select>
                                          </div>
                                          <div class="col-md-2">
                                                <label for="order" class="form-label">Order</label>
                                                <select class="form-select" id="order" name="order">
                                                      <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                                      <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                                </select>
                                          </div>
                                          <div class="col-md-1">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="submit" class="btn btn-modern btn-primary w-100">
                                                      <i class="fas fa-search"></i>
                                                </button>
                                          </div>
                                    </form>
                              </div>
                        </div>

                        <!-- Users Table -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                          <h6 class="content-card-title">
                                                <i class="fas fa-list"></i>
                                                Users List
                                          </h6>
                                          <div>
                                                <button class="btn btn-modern btn-outline-secondary me-2" onclick="exportTable('usersTable', 'users')">
                                                      <i class="fas fa-download me-1"></i>Export
                                                </button>
                                                <button class="btn btn-modern btn-outline-secondary" onclick="printPage()">
                                                      <i class="fas fa-print me-1"></i>Print
                                                </button>
                                          </div>
                                    </div>
                              </div>
                              <div class="content-card-body">
                                    <div class="table-responsive">
                                          <table class="table table-modern" id="usersTable" width="100%" cellspacing="0">
                                                <thead>
                                                      <tr>
                                                            <th>ID</th>
                                                            <th>Avatar</th>
                                                            <th>Username</th>
                                                            <th>Full Name</th>
                                                            <th>Email</th>
                                                            <th>Role</th>
                                                            <th>Status</th>
                                                            <th>Created</th>
                                                            <th>Actions</th>
                                                      </tr>
                                                </thead>
                                                <tbody>
                                                      <?php foreach ($users as $user): ?>
                                                            <tr>
                                                                  <td><?php echo $user['id']; ?></td>
                                                                  <td>
                                                                        <div class="avatar-sm">
                                                                              <i class="fas fa-user-circle fa-lg text-secondary"></i>
                                                                        </div>
                                                                  </td>
                                                                  <td>
                                                                        <div class="fw-medium"><?php echo htmlspecialchars($user['username']); ?></div>
                                                                  </td>
                                                                  <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                                                  <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                                  <td>
                                                                        <span class="badge bg-<?php
                                                                                                echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'manager' ? 'warning' : ($user['role'] === 'cashier' ? 'info' : 'secondary'));
                                                                                                ?>">
                                                                              <i class="fas fa-<?php
                                                                                                echo $user['role'] === 'admin' ? 'crown' : ($user['role'] === 'manager' ? 'user-tie' : ($user['role'] === 'cashier' ? 'cash-register' : 'user'));
                                                                                                ?> me-1"></i>
                                                                              <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                                                        </span>
                                                                  </td>
                                                                  <td>
                                                                        <span class="badge bg-success">
                                                                              <i class="fas fa-check-circle me-1"></i>Active
                                                                        </span>
                                                                  </td>
                                                                  <td>
                                                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></small>
                                                                  </td>
                                                                  <td>
                                                                        <div class="btn-group" role="group">
                                                                              <button class="btn btn-sm btn-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                                                    <i class="fas fa-edit"></i>
                                                                              </button>
                                                                              <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                                                          <i class="fas fa-trash"></i>
                                                                                    </button>
                                                                              <?php endif; ?>
                                                                        </div>
                                                                  </td>
                                                            </tr>
                                                      <?php endforeach; ?>
                                                </tbody>
                                          </table>
                                    </div>

                                    <!-- Pagination -->
                                    <?php if ($total_pages > 1): ?>
                                          <nav aria-label="Users pagination" class="mt-4">
                                                <ul class="pagination justify-content-center">
                                                      <?php if ($current_page > 1): ?>
                                                            <li class="page-item">
                                                                  <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">
                                                                        Previous
                                                                  </a>
                                                            </li>
                                                      <?php endif; ?>

                                                      <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                                                  <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">
                                                                        <?php echo $i; ?>
                                                                  </a>
                                                            </li>
                                                      <?php endfor; ?>

                                                      <?php if ($current_page < $total_pages): ?>
                                                            <li class="page-item">
                                                                  <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">
                                                                        Next
                                                                  </a>
                                                            </li>
                                                      <?php endif; ?>
                                                </ul>
                                          </nav>
                                    <?php endif; ?>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>

      <!-- Add User Modal -->
      <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" class="form-modern">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="add">

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="username" class="form-label">Username *</label>
                                                      <input type="text" class="form-control" id="username" name="username" required placeholder="Enter username">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="email" class="form-label">Email *</label>
                                                      <input type="email" class="form-control" id="email" name="email" required placeholder="Enter email">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="full_name" class="form-label">Full Name</label>
                                                      <input type="text" class="form-control" id="full_name" name="full_name" placeholder="Enter full name">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="role" class="form-label">Role *</label>
                                                      <select class="form-select" id="role" name="role" required>
                                                            <option value="">Select Role</option>
                                                            <?php foreach ($roles as $role): ?>
                                                                  <option value="<?php echo htmlspecialchars($role); ?>"><?php echo ucfirst(htmlspecialchars($role)); ?></option>
                                                            <?php endforeach; ?>
                                                      </select>
                                                </div>
                                          </div>
                                    </div>

                                    <div class="mb-3">
                                          <label for="password" class="form-label">Password *</label>
                                          <input type="password" class="form-control" id="password" name="password" required placeholder="Enter password">
                                          <div class="form-text">Password must be at least 6 characters long.</div>
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
      <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" class="form-modern">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" id="edit_id">

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_username" class="form-label">Username *</label>
                                                      <input type="text" class="form-control" id="edit_username" name="username" required placeholder="Enter username">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_email" class="form-label">Email *</label>
                                                      <input type="email" class="form-control" id="edit_email" name="email" required placeholder="Enter email">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_full_name" class="form-label">Full Name</label>
                                                      <input type="text" class="form-control" id="edit_full_name" name="full_name" placeholder="Enter full name">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_role" class="form-label">Role *</label>
                                                      <select class="form-select" id="edit_role" name="role" required>
                                                            <option value="">Select Role</option>
                                                            <?php foreach ($roles as $role): ?>
                                                                  <option value="<?php echo htmlspecialchars($role); ?>"><?php echo ucfirst(htmlspecialchars($role)); ?></option>
                                                            <?php endforeach; ?>
                                                      </select>
                                                </div>
                                          </div>
                                    </div>

                                    <div class="mb-3">
                                          <label for="edit_password" class="form-label">Password</label>
                                          <input type="password" class="form-control" id="edit_password" name="password" placeholder="Leave empty to keep current password">
                                          <div class="form-text">Leave empty to keep the current password, or enter a new password (minimum 6 characters).</div>
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

      <script>
            function editUser(user) {
                  document.getElementById('edit_id').value = user.id;
                  document.getElementById('edit_username').value = user.username;
                  document.getElementById('edit_email').value = user.email;
                  document.getElementById('edit_full_name').value = user.full_name || '';
                  document.getElementById('edit_role').value = user.role;
                  document.getElementById('edit_password').value = '';

                  const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
                  editModal.show();
            }

            function deleteUser(id, username) {
                  if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="id" value="${id}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                  }
            }

            function exportTable(tableId, filename) {
                  const table = document.getElementById(tableId);
                  const html = table.outerHTML;
                  const url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
                  const downloadLink = document.createElement("a");
                  document.body.appendChild(downloadLink);
                  downloadLink.href = url;
                  downloadLink.download = filename + '.xls';
                  downloadLink.click();
                  document.body.removeChild(downloadLink);
            }

            function printPage() {
                  window.print();
            }
      </script>
</body>

</html>