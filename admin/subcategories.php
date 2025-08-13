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

// Handle subcategory operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                  case 'add':
                        $name = trim($_POST['name']);
                        $category_id = intval($_POST['category_id']);
                        $type = trim($_POST['type']);
                        $description = trim($_POST['description']);
                        $sort_order = intval($_POST['sort_order']);

                        if (empty($name) || empty($category_id) || empty($type)) {
                              $error = 'Please fill in all required fields.';
                        } else {
                              $stmt = $pdo->prepare("INSERT INTO subcategories (name, category_id, type, description, sort_order) VALUES (?, ?, ?, ?, ?)");
                              if ($stmt->execute([$name, $category_id, $type, $description, $sort_order])) {
                                    $message = 'Subcategory added successfully!';
                              } else {
                                    $error = 'Failed to add subcategory.';
                              }
                        }
                        break;

                  case 'update':
                        $id = intval($_POST['id']);
                        $name = trim($_POST['name']);
                        $category_id = intval($_POST['category_id']);
                        $type = trim($_POST['type']);
                        $description = trim($_POST['description']);
                        $sort_order = intval($_POST['sort_order']);
                        $is_active = isset($_POST['is_active']) ? 1 : 0;

                        if (empty($name) || empty($category_id) || empty($type)) {
                              $error = 'Please fill in all required fields.';
                        } else {
                              $stmt = $pdo->prepare("UPDATE subcategories SET name = ?, category_id = ?, type = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?");
                              if ($stmt->execute([$name, $category_id, $type, $description, $sort_order, $is_active, $id])) {
                                    $message = 'Subcategory updated successfully!';
                              } else {
                                    $error = 'Failed to update subcategory.';
                              }
                        }
                        break;

                  case 'delete':
                        $id = intval($_POST['id']);

                        // Check if subcategory has products
                        $productStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE subcategory_id = ?");
                        $productStmt->execute([$id]);
                        $hasProducts = $productStmt->fetchColumn() > 0;

                        if ($hasProducts) {
                              $error = 'Cannot delete subcategory that has products. Please reassign or delete products first.';
                        } else {
                              $stmt = $pdo->prepare("DELETE FROM subcategories WHERE id = ?");
                              if ($stmt->execute([$id])) {
                                    $message = 'Subcategory deleted successfully!';
                              } else {
                                    $error = 'Failed to delete subcategory.';
                              }
                        }
                        break;
            }
      }
}

// Get all subcategories with category information
$stmt = $pdo->query("
    SELECT s.*, c.name as category_name 
    FROM subcategories s 
    LEFT JOIN categories c ON s.category_id = c.id 
    ORDER BY s.type, c.name, s.sort_order, s.name
");
$subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for dropdown
$categories = $pdo->query("SELECT id, name, type FROM categories WHERE is_active = 1 ORDER BY type, name")->fetchAll(PDO::FETCH_ASSOC);

// Group subcategories by type for display
$foodSubcategories = [];
$clothesSubcategories = [];

foreach ($subcategories as $subcategory) {
      if ($subcategory['type'] === 'Food') {
            $foodSubcategories[] = $subcategory;
      } else {
            $clothesSubcategories[] = $subcategory;
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
                                                <li class="breadcrumb-item"><a href="categories.php">Categories</a></li>
                                                <li class="breadcrumb-item active" aria-current="page">Subcategories</li>
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
                                                      <i class="fas fa-sitemap"></i>
                                                      Subcategory Management
                                                </h1>
                                                <p class="text-muted mb-0">Manage product subcategories and organize them under main categories.</p>
                                          </div>
                                          <button class="btn btn-modern btn-primary" data-bs-toggle="modal" data-bs-target="#addSubcategoryModal">
                                                <i class="fas fa-plus me-2"></i>Add Subcategory
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

                        <!-- Subcategories Display -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h6 class="content-card-title">
                                          <i class="fas fa-list"></i>
                                          Subcategories List
                                    </h6>
                              </div>
                              <div class="content-card-body">
                                    <div class="row">
                                          <!-- Food Subcategories -->
                                          <div class="col-md-6">
                                                <h5 class="text-warning mb-3">
                                                      <i class="fas fa-utensils me-2"></i>Food Subcategories
                                                </h5>
                                                <?php foreach ($foodSubcategories as $subcategory): ?>
                                                      <div class="card mb-3">
                                                            <div class="card-body">
                                                                  <div class="d-flex justify-content-between align-items-start">
                                                                        <div>
                                                                              <h6 class="card-title mb-1"><?php echo htmlspecialchars($subcategory['name']); ?></h6>
                                                                              <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars($subcategory['description']); ?></p>
                                                                              <div class="mb-2">
                                                                                    <span class="badge bg-warning me-2">Food</span>
                                                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($subcategory['category_name']); ?></span>
                                                                              </div>
                                                                              <small class="text-muted">Sort Order: <?php echo $subcategory['sort_order']; ?></small>
                                                                        </div>
                                                                        <div class="btn-group" role="group">
                                                                              <button class="btn btn-sm btn-primary" onclick="editSubcategory(<?php echo htmlspecialchars(json_encode($subcategory)); ?>)">
                                                                                    <i class="fas fa-edit"></i>
                                                                              </button>
                                                                              <button class="btn btn-sm btn-danger" onclick="deleteSubcategory(<?php echo $subcategory['id']; ?>, '<?php echo htmlspecialchars($subcategory['name']); ?>')">
                                                                                    <i class="fas fa-trash"></i>
                                                                              </button>
                                                                        </div>
                                                                  </div>
                                                            </div>
                                                      </div>
                                                <?php endforeach; ?>
                                          </div>

                                          <!-- Clothes Subcategories -->
                                          <div class="col-md-6">
                                                <h5 class="text-info mb-3">
                                                      <i class="fas fa-tshirt me-2"></i>Clothes Subcategories
                                                </h5>
                                                <?php foreach ($clothesSubcategories as $subcategory): ?>
                                                      <div class="card mb-3">
                                                            <div class="card-body">
                                                                  <div class="d-flex justify-content-between align-items-start">
                                                                        <div>
                                                                              <h6 class="card-title mb-1"><?php echo htmlspecialchars($subcategory['name']); ?></h6>
                                                                              <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars($subcategory['description']); ?></p>
                                                                              <div class="mb-2">
                                                                                    <span class="badge bg-info me-2">Clothes</span>
                                                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($subcategory['category_name']); ?></span>
                                                                              </div>
                                                                              <small class="text-muted">Sort Order: <?php echo $subcategory['sort_order']; ?></small>
                                                                        </div>
                                                                        <div class="btn-group" role="group">
                                                                              <button class="btn btn-sm btn-primary" onclick="editSubcategory(<?php echo htmlspecialchars(json_encode($subcategory)); ?>)">
                                                                                    <i class="fas fa-edit"></i>
                                                                              </button>
                                                                              <button class="btn btn-sm btn-danger" onclick="deleteSubcategory(<?php echo $subcategory['id']; ?>, '<?php echo htmlspecialchars($subcategory['name']); ?>')">
                                                                                    <i class="fas fa-trash"></i>
                                                                              </button>
                                                                        </div>
                                                                  </div>
                                                            </div>
                                                      </div>
                                                <?php endforeach; ?>
                                          </div>
                                    </div>

                                    <!-- All Subcategories Table -->
                                    <div class="mt-4">
                                          <h5 class="text-secondary mb-3">
                                                <i class="fas fa-table me-2"></i>All Subcategories Table
                                          </h5>

                                          <!-- Quick Filter Buttons -->
                                          <div class="mb-3">
                                                <div class="btn-group" role="group" aria-label="Quick filters">
                                                      <button type="button" class="btn btn-outline-primary btn-sm" onclick="filterByType('all')">
                                                            <i class="fas fa-list me-1"></i>All
                                                      </button>
                                                      <button type="button" class="btn btn-outline-warning btn-sm" onclick="filterByType('Food')">
                                                            <i class="fas fa-utensils me-1"></i>Food
                                                      </button>
                                                      <button type="button" class="btn btn-outline-info btn-sm" onclick="filterByType('Clothes')">
                                                            <i class="fas fa-tshirt me-1"></i>Clothes
                                                      </button>
                                                      <button type="button" class="btn btn-outline-success btn-sm" onclick="filterByStatus('Active')">
                                                            <i class="fas fa-check-circle me-1"></i>Active
                                                      </button>
                                                      <button type="button" class="btn btn-outline-danger btn-sm" onclick="filterByStatus('Inactive')">
                                                            <i class="fas fa-times-circle me-1"></i>Inactive
                                                      </button>
                                                </div>
                                          </div>

                                          <div class="table-responsive">
                                                <table id="subcategoriesTable" class="table table-modern">
                                                      <thead>
                                                            <tr>
                                                                  <th>Name</th>
                                                                  <th>Parent Category</th>
                                                                  <th>Type</th>
                                                                  <th>Description</th>
                                                                  <th>Sort Order</th>
                                                                  <th>Status</th>
                                                                  <th>Actions</th>
                                                            </tr>
                                                      </thead>
                                                      <tbody>
                                                            <?php foreach ($subcategories as $subcategory): ?>
                                                                  <tr>
                                                                        <td>
                                                                              <strong><?php echo htmlspecialchars($subcategory['name']); ?></strong>
                                                                        </td>
                                                                        <td>
                                                                              <span class="badge bg-secondary"><?php echo htmlspecialchars($subcategory['category_name']); ?></span>
                                                                        </td>
                                                                        <td>
                                                                              <span class="badge bg-<?php echo $subcategory['type'] === 'Food' ? 'warning' : 'info'; ?>">
                                                                                    <?php echo htmlspecialchars($subcategory['type']); ?>
                                                                              </span>
                                                                        </td>
                                                                        <td><?php echo htmlspecialchars($subcategory['description']); ?></td>
                                                                        <td><?php echo $subcategory['sort_order']; ?></td>
                                                                        <td>
                                                                              <span class="badge bg-<?php echo $subcategory['is_active'] ? 'success' : 'danger'; ?>">
                                                                                    <?php echo $subcategory['is_active'] ? 'Active' : 'Inactive'; ?>
                                                                              </span>
                                                                        </td>
                                                                        <td>
                                                                              <div class="btn-group" role="group">
                                                                                    <button class="btn btn-sm btn-primary" onclick="editSubcategory(<?php echo htmlspecialchars(json_encode($subcategory)); ?>)">
                                                                                          <i class="fas fa-edit"></i>
                                                                                    </button>
                                                                                    <button class="btn btn-sm btn-danger" onclick="deleteSubcategory(<?php echo $subcategory['id']; ?>, '<?php echo htmlspecialchars($subcategory['name']); ?>')">
                                                                                          <i class="fas fa-trash"></i>
                                                                                    </button>
                                                                              </div>
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
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- jQuery (required for DataTables) -->
      <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
      <!-- DataTables CSS -->
      <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
      <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
      <!-- Custom DataTables CSS -->
      <style>
            .dataTables_wrapper .dataTables_filter {
                  margin-bottom: 15px;
            }

            .dataTables_wrapper .dataTables_length {
                  margin-bottom: 15px;
            }

            .dataTables_wrapper .dataTables_buttons {
                  margin-bottom: 15px;
            }

            .dataTables_wrapper .dataTables_buttons .btn {
                  margin-right: 5px;
                  margin-bottom: 5px;
            }

            .btn-group .btn.active {
                  background-color: #007bff;
                  border-color: #007bff;
                  color: white;
            }

            .btn-group .btn.active.btn-outline-warning {
                  background-color: #ffc107;
                  border-color: #ffc107;
                  color: #212529;
            }

            .btn-group .btn.active.btn-outline-info {
                  background-color: #17a2b8;
                  border-color: #17a2b8;
                  color: white;
            }

            .btn-group .btn.active.btn-outline-success {
                  background-color: #28a745;
                  border-color: #28a745;
                  color: white;
            }

            .btn-group .btn.active.btn-outline-danger {
                  background-color: #dc3545;
                  border-color: #dc3545;
                  color: white;
            }

            .dataTables_wrapper .dataTables_info {
                  margin-top: 15px;
            }

            .dataTables_wrapper .dataTables_paginate {
                  margin-top: 15px;
            }
      </style>
      <!-- DataTables JS -->
      <script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
      <script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
      <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
      <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
      <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
      <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
      <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
      <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
      <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>

      <!-- Add Subcategory Modal -->
      <div class="modal fade" id="addSubcategoryModal" tabindex="-1" aria-labelledby="addSubcategoryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="addSubcategoryModalLabel">Add New Subcategory</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" class="form-modern">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="add">

                                    <div class="mb-3">
                                          <label for="name" class="form-label">Subcategory Name *</label>
                                          <input type="text" class="form-control" id="name" name="name" required placeholder="Enter subcategory name">
                                    </div>

                                    <div class="mb-3">
                                          <label for="type" class="form-label">Type *</label>
                                          <select class="form-select" id="type" name="type" required>
                                                <option value="">Select Type</option>
                                                <option value="Food">Food</option>
                                                <option value="Clothes">Clothes</option>
                                          </select>
                                    </div>

                                    <div class="mb-3">
                                          <label for="category_id" class="form-label">Parent Category *</label>
                                          <select class="form-select" id="category_id" name="category_id" required>
                                                <option value="">Select Parent Category</option>
                                          </select>
                                    </div>

                                    <div class="mb-3">
                                          <label for="description" class="form-label">Description</label>
                                          <textarea class="form-control" id="description" name="description" rows="3" placeholder="Enter subcategory description"></textarea>
                                    </div>

                                    <div class="mb-3">
                                          <label for="sort_order" class="form-label">Sort Order</label>
                                          <input type="number" class="form-control" id="sort_order" name="sort_order" value="0" min="0">
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add Subcategory</button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Edit Subcategory Modal -->
      <div class="modal fade" id="editSubcategoryModal" tabindex="-1" aria-labelledby="editSubcategoryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="editSubcategoryModalLabel">Edit Subcategory</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" class="form-modern">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" id="edit_id">

                                    <div class="mb-3">
                                          <label for="edit_name" class="form-label">Subcategory Name *</label>
                                          <input type="text" class="form-control" id="edit_name" name="name" required placeholder="Enter subcategory name">
                                    </div>

                                    <div class="mb-3">
                                          <label for="edit_type" class="form-label">Type *</label>
                                          <select class="form-select" id="edit_type" name="type" required>
                                                <option value="">Select Type</option>
                                                <option value="Food">Food</option>
                                                <option value="Clothes">Clothes</option>
                                          </select>
                                    </div>

                                    <div class="mb-3">
                                          <label for="edit_category_id" class="form-label">Parent Category *</label>
                                          <select class="form-select" id="edit_category_id" name="category_id" required>
                                                <option value="">Select Parent Category</option>
                                          </select>
                                    </div>

                                    <div class="mb-3">
                                          <label for="edit_description" class="form-label">Description</label>
                                          <textarea class="form-control" id="edit_description" name="description" rows="3" placeholder="Enter subcategory description"></textarea>
                                    </div>

                                    <div class="mb-3">
                                          <label for="edit_sort_order" class="form-label">Sort Order</label>
                                          <input type="number" class="form-control" id="edit_sort_order" name="sort_order" value="0" min="0">
                                    </div>

                                    <div class="mb-3">
                                          <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" checked>
                                                <label class="form-check-label" for="edit_is_active">
                                                      Active
                                                </label>
                                          </div>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Subcategory</button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <script>
            // Load categories for dropdowns
            const categories = <?php echo json_encode($categories); ?>;

            function loadCategories(type, categorySelect) {
                  categorySelect.innerHTML = '<option value="">Select Parent Category</option>';

                  categories.forEach(category => {
                        if (category.type === type) {
                              const option = document.createElement('option');
                              option.value = category.id;
                              option.textContent = category.name;
                              categorySelect.appendChild(option);
                        }
                  });
            }

            // Add Subcategory Modal - Type change handler
            document.getElementById('type').addEventListener('change', function() {
                  loadCategories(this.value, document.getElementById('category_id'));
            });

            // Edit Subcategory Modal - Type change handler
            document.getElementById('edit_type').addEventListener('change', function() {
                  loadCategories(this.value, document.getElementById('edit_category_id'));
            });

            function editSubcategory(subcategory) {
                  document.getElementById('edit_id').value = subcategory.id;
                  document.getElementById('edit_name').value = subcategory.name;
                  document.getElementById('edit_type').value = subcategory.type;
                  document.getElementById('edit_description').value = subcategory.description || '';
                  document.getElementById('edit_sort_order').value = subcategory.sort_order || 0;
                  document.getElementById('edit_is_active').checked = subcategory.is_active == 1;

                  // Load categories and set selected value
                  loadCategories(subcategory.type, document.getElementById('edit_category_id'));
                  document.getElementById('edit_category_id').value = subcategory.category_id;

                  const editModal = new bootstrap.Modal(document.getElementById('editSubcategoryModal'));
                  editModal.show();
            }

            function deleteSubcategory(id, name) {
                  if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
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

            // Initialize DataTable
            let subcategoriesTable;

            $(document).ready(function() {
                  subcategoriesTable = $('#subcategoriesTable').DataTable({
                        responsive: true,
                        pageLength: 25,
                        lengthMenu: [
                              [10, 25, 50, 100, -1],
                              [10, 25, 50, 100, "All"]
                        ],
                        order: [
                              [0, 'asc']
                        ], // Sort by name by default
                        columnDefs: [{
                                    targets: -1, // Actions column
                                    orderable: false,
                                    searchable: false
                              },
                              {
                                    targets: [3, 4], // Description and Sort Order columns
                                    orderable: true,
                                    searchable: true
                              }
                        ],
                        dom: 'Bfrtip',
                        buttons: [{
                                    extend: 'copy',
                                    text: '<i class="fas fa-copy"></i> Copy',
                                    className: 'btn btn-sm btn-outline-secondary text-white'
                              },
                              {
                                    extend: 'csv',
                                    text: '<i class="fas fa-file-csv"></i> CSV',
                                    className: 'btn btn-sm btn-outline-secondary text-white'
                              },
                              {
                                    extend: 'excel',
                                    text: '<i class="fas fa-file-excel"></i> Excel',
                                    className: 'btn btn-sm btn-outline-secondary text-white'
                              },
                              {
                                    extend: 'pdf',
                                    text: '<i class="fas fa-file-pdf"></i> PDF',
                                    className: 'btn btn-sm btn-outline-secondary text-white'
                              },
                              {
                                    extend: 'print',
                                    text: '<i class="fas fa-print"></i> Print',
                                    className: 'btn btn-sm btn-outline-secondary text-white'
                              }
                        ],
                        language: {
                              search: "Search subcategories:",
                              lengthMenu: "Show _MENU_ subcategories per page",
                              info: "Showing _START_ to _END_ of _TOTAL_ subcategories",
                              infoEmpty: "Showing 0 to 0 of 0 subcategories",
                              infoFiltered: "(filtered from _MAX_ total subcategories)",
                              zeroRecords: "No subcategories found matching your search",
                              paginate: {
                                    first: "First",
                                    last: "Last",
                                    next: "Next",
                                    previous: "Previous"
                              }
                        }
                  });
            });

            // Filter functions for quick filter buttons
            function filterByType(type) {
                  if (type === 'all') {
                        subcategoriesTable.column(2).search('').draw();
                  } else {
                        subcategoriesTable.column(2).search(type).draw();
                  }

                  // Update button states
                  $('.btn-group .btn').removeClass('active');
                  if (type === 'all') {
                        $('.btn-group .btn:first').addClass('active');
                  } else if (type === 'Food') {
                        $('.btn-group .btn:nth-child(2)').addClass('active');
                  } else if (type === 'Clothes') {
                        $('.btn-group .btn:nth-child(3)').addClass('active');
                  }
            }

            function filterByStatus(status) {
                  subcategoriesTable.column(5).search(status).draw();

                  // Update button states
                  $('.btn-group .btn').removeClass('active');
                  if (status === 'Active') {
                        $('.btn-group .btn:nth-child(4)').addClass('active');
                  } else if (status === 'Inactive') {
                        $('.btn-group .btn:nth-child(5)').addClass('active');
                  }
            }

            // Add click handlers for filter buttons
            $(document).ready(function() {
                  $('.btn-group .btn').click(function() {
                        $('.btn-group .btn').removeClass('active');
                        $(this).addClass('active');
                  });
            });
      </script>
</body>

</html>