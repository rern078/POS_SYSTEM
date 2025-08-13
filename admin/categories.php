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

// Handle category operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                  case 'add':
                        $name = trim($_POST['name']);
                        $type = trim($_POST['type']);
                        $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
                        $description = trim($_POST['description']);
                        $sort_order = intval($_POST['sort_order']);

                        if (empty($name) || empty($type)) {
                              $error = 'Please fill in all required fields.';
                        } else {
                              $stmt = $pdo->prepare("INSERT INTO categories (name, type, parent_id, description, sort_order) VALUES (?, ?, ?, ?, ?)");
                              if ($stmt->execute([$name, $type, $parent_id, $description, $sort_order])) {
                                    $message = 'Category added successfully!';
                              } else {
                                    $error = 'Failed to add category.';
                              }
                        }
                        break;

                  case 'update':
                        $id = intval($_POST['id']);
                        $name = trim($_POST['name']);
                        $type = trim($_POST['type']);
                        $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
                        $description = trim($_POST['description']);
                        $sort_order = intval($_POST['sort_order']);
                        $is_active = isset($_POST['is_active']) ? 1 : 0;

                        if (empty($name) || empty($type)) {
                              $error = 'Please fill in all required fields.';
                        } else {
                              // Prevent circular references
                              if ($parent_id == $id) {
                                    $error = 'A category cannot be its own parent.';
                              } else {
                                    $stmt = $pdo->prepare("UPDATE categories SET name = ?, type = ?, parent_id = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?");
                                    if ($stmt->execute([$name, $type, $parent_id, $description, $sort_order, $is_active, $id])) {
                                          $message = 'Category updated successfully!';
                                    } else {
                                          $error = 'Failed to update category.';
                                    }
                              }
                        }
                        break;

                  case 'delete':
                        $id = intval($_POST['id']);

                        // Check if category has subcategories
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
                        $checkStmt->execute([$id]);
                        $hasSubcategories = $checkStmt->fetchColumn() > 0;

                        // Check if category has products
                        $productStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                        $productStmt->execute([$id]);
                        $hasProducts = $productStmt->fetchColumn() > 0;

                        if ($hasSubcategories) {
                              $error = 'Cannot delete category that has subcategories. Please delete subcategories first.';
                        } elseif ($hasProducts) {
                              $error = 'Cannot delete category that has products. Please reassign or delete products first.';
                        } else {
                              $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                              if ($stmt->execute([$id])) {
                                    $message = 'Category deleted successfully!';
                              } else {
                                    $error = 'Failed to delete category.';
                              }
                        }
                        break;
            }
      }
}

// Get categories with hierarchical structure
$categories = [];
$stmt = $pdo->query("SELECT * FROM categories ORDER BY type, sort_order, name");
$allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize categories hierarchically
$mainCategories = [];
$subCategories = [];

foreach ($allCategories as $category) {
      if ($category['parent_id'] === null) {
            $mainCategories[] = $category;
      } else {
            $subCategories[] = $category;
      }
}

// Group subcategories by parent
$subCategoriesByParent = [];
foreach ($subCategories as $subCategory) {
      $parentId = $subCategory['parent_id'];
      if (!isset($subCategoriesByParent[$parentId])) {
            $subCategoriesByParent[$parentId] = [];
      }
      $subCategoriesByParent[$parentId][] = $subCategory;
}

// Get all categories for dropdown (excluding current category when editing)
$allCategoriesForDropdown = $pdo->query("SELECT id, name, type, parent_id FROM categories WHERE is_active = 1 ORDER BY type, name")->fetchAll(PDO::FETCH_ASSOC);
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
                                                <li class="breadcrumb-item active" aria-current="page">Categories</li>
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
                                                      <i class="fas fa-tags"></i>
                                                      Category Management
                                                </h1>
                                                <p class="text-muted mb-0">Manage product categories and subcategories for better organization.</p>
                                          </div>
                                          <button class="btn btn-modern btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                                <i class="fas fa-plus me-2"></i>Add Category
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

                        <!-- Categories Display -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h6 class="content-card-title">
                                          <i class="fas fa-list"></i>
                                          Categories List
                                    </h6>
                              </div>
                              <div class="content-card-body">
                                    <div class="row">
                                          <!-- Food Categories -->
                                          <div class="col-md-6">
                                                <h5 class="text-warning mb-3">
                                                      <i class="fas fa-utensils me-2"></i>Food Categories
                                                </h5>
                                                <?php foreach ($mainCategories as $category): ?>
                                                      <?php if ($category['type'] === 'Food'): ?>
                                                            <div class="card mb-3">
                                                                  <div class="card-body">
                                                                        <div class="d-flex justify-content-between align-items-start">
                                                                              <div>
                                                                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($category['name']); ?></h6>
                                                                                    <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars($category['description']); ?></p>
                                                                                    <span class="badge bg-warning">Food</span>
                                                                                    <?php if (isset($subCategoriesByParent[$category['id']])): ?>
                                                                                          <div class="mt-2">
                                                                                                <small class="text-muted">Subcategories:</small>
                                                                                                <?php foreach ($subCategoriesByParent[$category['id']] as $subCategory): ?>
                                                                                                      <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($subCategory['name']); ?></span>
                                                                                                <?php endforeach; ?>
                                                                                          </div>
                                                                                    <?php endif; ?>
                                                                              </div>
                                                                              <div class="btn-group" role="group">
                                                                                    <button class="btn btn-sm btn-primary" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                                                                          <i class="fas fa-edit"></i>
                                                                                    </button>
                                                                                    <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
                                                                                          <i class="fas fa-trash"></i>
                                                                                    </button>
                                                                              </div>
                                                                        </div>
                                                                  </div>
                                                            </div>
                                                      <?php endif; ?>
                                                <?php endforeach; ?>
                                          </div>

                                          <!-- Clothes Categories -->
                                          <div class="col-md-6">
                                                <h5 class="text-info mb-3">
                                                      <i class="fas fa-tshirt me-2"></i>Clothes Categories
                                                </h5>
                                                <?php foreach ($mainCategories as $category): ?>
                                                      <?php if ($category['type'] === 'Clothes'): ?>
                                                            <div class="card mb-3">
                                                                  <div class="card-body">
                                                                        <div class="d-flex justify-content-between align-items-start">
                                                                              <div>
                                                                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($category['name']); ?></h6>
                                                                                    <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars($category['description']); ?></p>
                                                                                    <span class="badge bg-info">Clothes</span>
                                                                                    <?php if (isset($subCategoriesByParent[$category['id']])): ?>
                                                                                          <div class="mt-2">
                                                                                                <small class="text-muted">Subcategories:</small>
                                                                                                <?php foreach ($subCategoriesByParent[$category['id']] as $subCategory): ?>
                                                                                                      <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($subCategory['name']); ?></span>
                                                                                                <?php endforeach; ?>
                                                                                          </div>
                                                                                    <?php endif; ?>
                                                                              </div>
                                                                              <div class="btn-group" role="group">
                                                                                    <button class="btn btn-sm btn-primary" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                                                                          <i class="fas fa-edit"></i>
                                                                                    </button>
                                                                                    <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
                                                                                          <i class="fas fa-trash"></i>
                                                                                    </button>
                                                                              </div>
                                                                        </div>
                                                                  </div>
                                                            </div>
                                                      <?php endif; ?>
                                                <?php endforeach; ?>
                                          </div>
                                    </div>

                                    <!-- Subcategories Section -->
                                    <div class="mt-4">
                                          <h5 class="text-secondary mb-3">
                                                <i class="fas fa-sitemap me-2"></i>All Subcategories
                                          </h5>
                                          <div class="table-responsive">
                                                <table id="subcategoriesTable" class="table table-modern">
                                                      <thead>
                                                            <tr>
                                                                  <th>Name</th>
                                                                  <th>Parent Category</th>
                                                                  <th>Type</th>
                                                                  <th>Description</th>
                                                                  <th>Status</th>
                                                                  <th>Actions</th>
                                                            </tr>
                                                      </thead>
                                                      <tbody>
                                                            <?php foreach ($subCategories as $subCategory): ?>
                                                                  <tr>
                                                                        <td>
                                                                              <strong><?php echo htmlspecialchars($subCategory['name']); ?></strong>
                                                                        </td>
                                                                        <td>
                                                                              <?php
                                                                              $parentName = 'N/A';
                                                                              foreach ($allCategoriesForDropdown as $parent) {
                                                                                    if ($parent['id'] == $subCategory['parent_id']) {
                                                                                          $parentName = htmlspecialchars($parent['name']);
                                                                                          break;
                                                                                    }
                                                                              }
                                                                              echo $parentName;
                                                                              ?>
                                                                        </td>
                                                                        <td>
                                                                              <span class="badge bg-<?php echo $subCategory['type'] === 'Food' ? 'warning' : 'info'; ?>">
                                                                                    <?php echo htmlspecialchars($subCategory['type']); ?>
                                                                              </span>
                                                                        </td>
                                                                        <td><?php echo htmlspecialchars($subCategory['description']); ?></td>
                                                                        <td>
                                                                              <span class="badge bg-<?php echo $subCategory['is_active'] ? 'success' : 'danger'; ?>">
                                                                                    <?php echo $subCategory['is_active'] ? 'Active' : 'Inactive'; ?>
                                                                              </span>
                                                                        </td>
                                                                        <td>
                                                                              <div class="btn-group" role="group">
                                                                                    <button class="btn btn-sm btn-primary" onclick="editCategory(<?php echo htmlspecialchars(json_encode($subCategory)); ?>)">
                                                                                          <i class="fas fa-edit"></i>
                                                                                    </button>
                                                                                    <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $subCategory['id']; ?>, '<?php echo htmlspecialchars($subCategory['name']); ?>')">
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

      <!-- jQuery (required for DataTables) -->
      <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- DataTables CSS and JS -->
      <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
      <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
      <script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
      <script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
      <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
      <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>

      <!-- Add Category Modal -->
      <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" class="form-modern">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="add">

                                    <div class="mb-3">
                                          <label for="name" class="form-label">Category Name *</label>
                                          <input type="text" class="form-control" id="name" name="name" required placeholder="Enter category name">
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
                                          <label for="parent_id" class="form-label">Parent Category (Optional)</label>
                                          <select class="form-select" id="parent_id" name="parent_id">
                                                <option value="">No Parent (Main Category)</option>
                                          </select>
                                    </div>

                                    <div class="mb-3">
                                          <label for="description" class="form-label">Description</label>
                                          <textarea class="form-control" id="description" name="description" rows="3" placeholder="Enter category description"></textarea>
                                    </div>

                                    <div class="mb-3">
                                          <label for="sort_order" class="form-label">Sort Order</label>
                                          <input type="number" class="form-control" id="sort_order" name="sort_order" value="0" min="0">
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add Category</button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Edit Category Modal -->
      <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" class="form-modern">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" id="edit_id">

                                    <div class="mb-3">
                                          <label for="edit_name" class="form-label">Category Name *</label>
                                          <input type="text" class="form-control" id="edit_name" name="name" required placeholder="Enter category name">
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
                                          <label for="edit_parent_id" class="form-label">Parent Category (Optional)</label>
                                          <select class="form-select" id="edit_parent_id" name="parent_id">
                                                <option value="">No Parent (Main Category)</option>
                                          </select>
                                    </div>

                                    <div class="mb-3">
                                          <label for="edit_description" class="form-label">Description</label>
                                          <textarea class="form-control" id="edit_description" name="description" rows="3" placeholder="Enter category description"></textarea>
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
                                    <button type="submit" class="btn btn-primary">Update Category</button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <script>
            // Load parent categories for dropdowns
            const allCategories = <?php echo json_encode($allCategoriesForDropdown); ?>;

            function loadParentCategories(type, parentSelect, excludeId = null) {
                  parentSelect.innerHTML = '<option value="">No Parent (Main Category)</option>';

                  allCategories.forEach(category => {
                        if (category.type === type && category.id != excludeId) {
                              const option = document.createElement('option');
                              option.value = category.id;
                              option.textContent = category.name;
                              parentSelect.appendChild(option);
                        }
                  });
            }

            // Add Category Modal - Type change handler
            document.getElementById('type').addEventListener('change', function() {
                  loadParentCategories(this.value, document.getElementById('parent_id'));
            });

            // Edit Category Modal - Type change handler
            document.getElementById('edit_type').addEventListener('change', function() {
                  const excludeId = document.getElementById('edit_id').value;
                  loadParentCategories(this.value, document.getElementById('edit_parent_id'), excludeId);
            });

            function editCategory(category) {
                  document.getElementById('edit_id').value = category.id;
                  document.getElementById('edit_name').value = category.name;
                  document.getElementById('edit_type').value = category.type;
                  document.getElementById('edit_description').value = category.description || '';
                  document.getElementById('edit_sort_order').value = category.sort_order || 0;
                  document.getElementById('edit_is_active').checked = category.is_active == 1;

                  // Load parent categories and set selected value
                  loadParentCategories(category.type, document.getElementById('edit_parent_id'), category.id);
                  document.getElementById('edit_parent_id').value = category.parent_id || '';

                  const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
                  editModal.show();
            }

            function deleteCategory(id, name) {
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

            // Initialize DataTables
            $(document).ready(function() {
                  console.log('Document ready, initializing DataTable...');
                  console.log('Table element:', $('#subcategoriesTable'));

                  if ($('#subcategoriesTable').length > 0) {
                        $('#subcategoriesTable').DataTable({
                              responsive: true,
                              pageLength: 10,
                              lengthMenu: [
                                    [5, 10, 25, 50, -1],
                                    [5, 10, 25, 50, "All"]
                              ],
                              order: [
                                    [0, 'asc']
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
                              },
                              columnDefs: [{
                                    targets: -1, // Actions column
                                    orderable: false,
                                    searchable: false
                              }]
                        });
                        console.log('DataTable initialized successfully');
                  } else {
                        console.error('Table element not found');
                  }
            });
      </script>
</body>

</html>