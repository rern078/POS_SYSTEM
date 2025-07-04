<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Function to handle image upload
function handleImageUpload($file, $product_id = null)
{
      if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
      }

      $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
      $max_size = 5 * 1024 * 1024; // 5MB

      // Check file type
      if (!in_array($file['type'], $allowed_types)) {
            return false;
      }

      // Check file size
      if ($file['size'] > $max_size) {
            return false;
      }

      // Create upload directory if it doesn't exist
      $upload_dir = '../images/products/';
      if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
      }

      // Generate unique filename
      $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
      $filename = 'product_' . ($product_id ?? time()) . '_' . uniqid() . '.' . $extension;
      $filepath = $upload_dir . $filename;

      // Move uploaded file
      if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return 'images/products/' . $filename;
      }

      return false;
}

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || !isAdmin()) {
      header('Location: login.php');
      exit();
}

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle product operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                  case 'add':
                        $product_code = trim($_POST['product_code']);
                        $name = trim($_POST['name']);
                        $description = trim($_POST['description']);
                        $price = floatval($_POST['price']);
                        $discount_price = !empty($_POST['discount_price']) ? floatval($_POST['discount_price']) : null;
                        $stock = intval($_POST['stock_quantity']);

                        // Handle category - use new category if provided, otherwise use dropdown selection
                        $category = '';
                        if (!empty($_POST['new_category'])) {
                              $category = trim($_POST['new_category']);
                        } elseif (!empty($_POST['category'])) {
                              $category = trim($_POST['category']);
                        }

                        if (empty($name) || $price <= 0) {
                              $error = 'Please fill in all required fields correctly.';
                        } else {
                              // Handle image upload
                              $image_path = null;
                              if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                                    $image_result = handleImageUpload($_FILES['image']);
                                    if ($image_result === false) {
                                          $error = 'Invalid image file. Please upload a valid image (JPEG, PNG, GIF, WebP) under 5MB.';
                                          break;
                                    }
                                    $image_path = $image_result;
                              }

                              $stmt = $pdo->prepare("INSERT INTO products (product_code, name, description, price, discount_price, stock_quantity, category, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                              if ($stmt->execute([$product_code, $name, $description, $price, $discount_price, $stock, $category, $image_path])) {
                                    $message = 'Product added successfully!';
                              } else {
                                    $error = 'Failed to add product.';
                              }
                        }
                        break;

                  case 'update':
                        $id = intval($_POST['id']);
                        $product_code = trim($_POST['product_code']);
                        $name = trim($_POST['name']);
                        $description = trim($_POST['description']);
                        $price = floatval($_POST['price']);
                        $discount_price = !empty($_POST['discount_price']) ? floatval($_POST['discount_price']) : null;
                        $stock = intval($_POST['stock_quantity']);

                        // Handle category - use new category if provided, otherwise use dropdown selection
                        $category = '';
                        if (!empty($_POST['new_category'])) {
                              $category = trim($_POST['new_category']);
                        } elseif (!empty($_POST['category'])) {
                              $category = trim($_POST['category']);
                        }

                        if (empty($name) || $price <= 0) {
                              $error = 'Please fill in all required fields correctly.';
                        } else {
                              // Handle image upload
                              $image_path = null;
                              if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                                    $image_result = handleImageUpload($_FILES['image'], $id);
                                    if ($image_result === false) {
                                          $error = 'Invalid image file. Please upload a valid image (JPEG, PNG, GIF, WebP) under 5MB.';
                                          break;
                                    }
                                    $image_path = $image_result;
                              }

                              // Build update query based on whether image is being updated
                              if ($image_path) {
                                    $stmt = $pdo->prepare("UPDATE products SET product_code = ?, name = ?, description = ?, price = ?, discount_price = ?, stock_quantity = ?, category = ?, image_path = ? WHERE id = ?");
                                    $params = [$product_code, $name, $description, $price, $discount_price, $stock, $category, $image_path, $id];
                              } else {
                                    $stmt = $pdo->prepare("UPDATE products SET product_code = ?, name = ?, description = ?, price = ?, discount_price = ?, stock_quantity = ?, category = ? WHERE id = ?");
                                    $params = [$product_code, $name, $description, $price, $discount_price, $stock, $category, $id];
                              }

                              if ($stmt->execute($params)) {
                                    $message = 'Product updated successfully!';
                              } else {
                                    $error = 'Failed to update product.';
                              }
                        }
                        break;

                  case 'delete':
                        $id = intval($_POST['id']);
                        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                        if ($stmt->execute([$id])) {
                              $message = 'Product deleted successfully!';
                        } else {
                              $error = 'Failed to delete product.';
                        }
                        break;
            }
      }
}

// Get products with search and filter
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'id';
$order = $_GET['order'] ?? 'ASC';

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

$whereConditions = [];
$params = [];

if (!empty($search)) {
      $whereConditions[] = "(name LIKE ? OR description LIKE ? OR product_code LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      $params[] = "%$search%";
}

if (!empty($category)) {
      $whereConditions[] = "category = ?";
      $params[] = $category;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM products $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total_items = $countStmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

// Get paginated products
$sql = "SELECT * FROM products $whereClause ORDER BY $sort $order LIMIT $items_per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Products Management - POS Admin</title>

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
                                    <a class="nav-link active" href="products.php">
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
                  <div class="col-12 d-flex justify-content-between align-items-center">
                        <div>
                              <h1 class="h3 mb-0 text-gray-800">Products Management</h1>
                              <p class="text-muted">Manage your product inventory</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                              <i class="fas fa-plus me-2"></i>Add Product
                        </button>
                  </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                  <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>
            <?php endif; ?>

            <?php if ($error): ?>
                  <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>
            <?php endif; ?>

            <!-- Filters and Search -->
            <div class="card shadow mb-4">
                  <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Filters & Search</h6>
                  </div>
                  <div class="card-body">
                        <form method="GET" class="row g-3">
                              <div class="col-md-4">
                                    <label for="search" class="form-label">Search Products</label>
                                    <input type="text" class="form-control" id="search" name="search"
                                          placeholder="Search by name or description..."
                                          value="<?php echo htmlspecialchars($search); ?>">
                              </div>
                              <div class="col-md-3">
                                    <label for="category" class="form-label">Category</label>
                                    <select class="form-select" id="category" name="category">
                                          <option value="">All Categories</option>
                                          <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo htmlspecialchars($cat); ?>"
                                                      <?php echo $category === $cat ? 'selected' : ''; ?>>
                                                      <?php echo htmlspecialchars($cat); ?>
                                                </option>
                                          <?php endforeach; ?>
                                    </select>
                              </div>
                              <div class="col-md-2">
                                    <label for="sort" class="form-label">Sort By</label>
                                    <select class="form-select" id="sort" name="sort">
                                          <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>ID</option>
                                          <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name</option>
                                          <option value="price" <?php echo $sort === 'price' ? 'selected' : ''; ?>>Price</option>
                                          <option value="stock_quantity" <?php echo $sort === 'stock_quantity' ? 'selected' : ''; ?>>Stock</option>
                                          <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Date Added</option>
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
                                    <button type="submit" class="btn btn-primary w-100">
                                          <i class="fas fa-search"></i>
                                    </button>
                              </div>
                        </form>
                  </div>
            </div>

            <!-- Products Table -->
            <div class="card shadow mb-4">
                  <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Products List</h6>
                        <div>
                              <button class="btn btn-sm btn-outline-secondary" onclick="exportTable('productsTable', 'products')">
                                    <i class="fas fa-download me-1"></i>Export
                              </button>
                              <button class="btn btn-sm btn-outline-secondary" onclick="printPage()">
                                    <i class="fas fa-print me-1"></i>Print
                              </button>
                        </div>
                  </div>
                  <div class="card-body">
                        <div class="table-responsive">
                              <table class="table table-bordered" id="productsTable" width="100%" cellspacing="0">
                                    <thead>
                                          <tr>
                                                <th>ID</th>
                                                <th>Image</th>
                                                <th>Code</th>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Price</th>
                                                <th>Discount</th>
                                                <th>Stock</th>
                                                <th>Category</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                          </tr>
                                    </thead>
                                    <tbody>
                                          <?php foreach ($products as $product): ?>
                                                <tr>
                                                      <td><?php echo $product['id']; ?></td>
                                                      <td>
                                                            <?php if ($product['image_path']): ?>
                                                                  <img src="../<?php echo htmlspecialchars($product['image_path']); ?>"
                                                                        alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                                        class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                                            <?php else: ?>
                                                                  <div class="bg-light d-flex align-items-center justify-content-center"
                                                                        style="width: 50px; height: 50px;">
                                                                        <i class="fas fa-image text-muted"></i>
                                                                  </div>
                                                            <?php endif; ?>
                                                      </td>
                                                      <td>
                                                            <span class="badge bg-info"><?php echo htmlspecialchars($product['product_code'] ?? 'N/A'); ?></span>
                                                      </td>
                                                      <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                      <td><?php echo htmlspecialchars(substr($product['description'], 0, 50)) . (strlen($product['description']) > 50 ? '...' : ''); ?></td>
                                                      <td>$<?php echo number_format($product['price'], 2); ?></td>
                                                      <td>
                                                            <?php if ($product['discount_price'] && $product['discount_price'] > 0): ?>
                                                                  <span class="badge bg-success">$<?php echo number_format($product['discount_price'], 2); ?></span>
                                                            <?php else: ?>
                                                                  <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                      </td>
                                                      <td>
                                                            <span class="badge bg-<?php echo $product['stock_quantity'] < 10 ? 'danger' : ($product['stock_quantity'] < 50 ? 'warning' : 'success'); ?>">
                                                                  <?php echo $product['stock_quantity']; ?>
                                                            </span>
                                                      </td>
                                                      <td><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></td>
                                                      <td>
                                                            <span class="badge bg-<?php echo $product['stock_quantity'] > 0 ? 'success' : 'danger'; ?>">
                                                                  <?php echo $product['stock_quantity'] > 0 ? 'In Stock' : 'Out of Stock'; ?>
                                                            </span>
                                                      </td>
                                                      <td>
                                                            <div class="btn-group" role="group">
                                                                  <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                                                        <i class="fas fa-edit"></i>
                                                                  </button>
                                                                  <button class="btn btn-sm btn-danger btn-delete" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                                                        <i class="fas fa-trash"></i>
                                                                  </button>
                                                            </div>
                                                      </td>
                                                </tr>
                                          <?php endforeach; ?>
                                    </tbody>
                              </table>
                        </div>

                        <?php if (empty($products)): ?>
                              <div class="text-center py-4">
                                    <i class="fas fa-box fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No products found</h5>
                                    <p class="text-muted">Try adjusting your search criteria or add a new product.</p>
                              </div>
                        <?php endif; ?>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                              <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="text-muted">
                                          Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $items_per_page, $total_items); ?> of <?php echo $total_items; ?> products
                                    </div>
                                    <nav aria-label="Products pagination">
                                          <ul class="pagination mb-0">
                                                <!-- Previous page -->
                                                <?php if ($current_page > 1): ?>
                                                      <li class="page-item">
                                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>">
                                                                  <i class="fas fa-chevron-left"></i>
                                                            </a>
                                                      </li>
                                                <?php else: ?>
                                                      <li class="page-item disabled">
                                                            <span class="page-link">
                                                                  <i class="fas fa-chevron-left"></i>
                                                            </span>
                                                      </li>
                                                <?php endif; ?>

                                                <!-- Page numbers -->
                                                <?php
                                                $start_page = max(1, $current_page - 2);
                                                $end_page = min($total_pages, $current_page + 2);

                                                if ($start_page > 1): ?>
                                                      <li class="page-item">
                                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                                                      </li>
                                                      <?php if ($start_page > 2): ?>
                                                            <li class="page-item disabled">
                                                                  <span class="page-link">...</span>
                                                            </li>
                                                      <?php endif; ?>
                                                <?php endif; ?>

                                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                      <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                                      </li>
                                                <?php endfor; ?>

                                                <?php if ($end_page < $total_pages): ?>
                                                      <?php if ($end_page < $total_pages - 1): ?>
                                                            <li class="page-item disabled">
                                                                  <span class="page-link">...</span>
                                                            </li>
                                                      <?php endif; ?>
                                                      <li class="page-item">
                                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                                                      </li>
                                                <?php endif; ?>

                                                <!-- Next page -->
                                                <?php if ($current_page < $total_pages): ?>
                                                      <li class="page-item">
                                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>">
                                                                  <i class="fas fa-chevron-right"></i>
                                                            </a>
                                                      </li>
                                                <?php else: ?>
                                                      <li class="page-item disabled">
                                                            <span class="page-link">
                                                                  <i class="fas fa-chevron-right"></i>
                                                            </span>
                                                      </li>
                                                <?php endif; ?>
                                          </ul>
                                    </nav>
                              </div>
                        <?php endif; ?>
                  </div>
            </div>
      </div>

      <!-- Add Product Modal -->
      <div class="modal fade" id="addProductModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">
                                    <i class="fas fa-plus me-2"></i>Add New Product
                              </h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                              <input type="hidden" name="action" value="add">
                              <div class="modal-body">
                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="product_code" class="form-label">Product Code</label>
                                                      <input type="text" class="form-control" id="product_code" name="product_code"
                                                            placeholder="e.g., PROD001">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="name" class="form-label">Product Name *</label>
                                                      <input type="text" class="form-control" id="name" name="name" required>
                                                </div>
                                          </div>
                                    </div>
                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="category" class="form-label">Category</label>
                                                      <select class="form-select" id="category" name="category">
                                                            <option value="">Select Category</option>
                                                            <?php foreach ($categories as $cat): ?>
                                                                  <option value="<?php echo htmlspecialchars($cat); ?>">
                                                                        <?php echo htmlspecialchars($cat); ?>
                                                                  </option>
                                                            <?php endforeach; ?>
                                                      </select>
                                                      <div class="form-text">Or type a new category below</div>
                                                      <input type="text" class="form-control mt-1" id="new_category" name="new_category"
                                                            placeholder="Type new category name...">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="stock_quantity" class="form-label">Stock Quantity</label>
                                                      <input type="number" class="form-control" id="stock_quantity" name="stock_quantity"
                                                            min="0" value="0">
                                                </div>
                                          </div>
                                    </div>
                                    <div class="mb-3">
                                          <label for="description" class="form-label">Description</label>
                                          <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                    </div>
                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="price" class="form-label">Price *</label>
                                                      <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control" id="price" name="price"
                                                                  step="0.01" min="0" required>
                                                      </div>
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="discount_price" class="form-label">Discount Price</label>
                                                      <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control" id="discount_price" name="discount_price"
                                                                  step="0.01" min="0" placeholder="Leave empty for no discount">
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                                    <div class="mb-3">
                                          <label for="image" class="form-label">Product Image</label>
                                          <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                          <i class="fas fa-save me-2"></i>Save Product
                                    </button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Edit Product Modal -->
      <div class="modal fade" id="editProductModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">
                                    <i class="fas fa-edit me-2"></i>Edit Product
                              </h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                              <input type="hidden" name="action" value="update">
                              <input type="hidden" name="id" id="edit_id">
                              <div class="modal-body">
                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_product_code" class="form-label">Product Code</label>
                                                      <input type="text" class="form-control" id="edit_product_code" name="product_code">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_name" class="form-label">Product Name *</label>
                                                      <input type="text" class="form-control" id="edit_name" name="name" required>
                                                </div>
                                          </div>
                                    </div>
                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_category" class="form-label">Category</label>
                                                      <select class="form-select" id="edit_category" name="category">
                                                            <option value="">Select Category</option>
                                                            <?php foreach ($categories as $cat): ?>
                                                                  <option value="<?php echo htmlspecialchars($cat); ?>">
                                                                        <?php echo htmlspecialchars($cat); ?>
                                                                  </option>
                                                            <?php endforeach; ?>
                                                      </select>
                                                      <div class="form-text">Or type a new category below</div>
                                                      <input type="text" class="form-control mt-1" id="edit_new_category" name="new_category"
                                                            placeholder="Type new category name...">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_stock_quantity" class="form-label">Stock Quantity</label>
                                                      <input type="number" class="form-control" id="edit_stock_quantity" name="stock_quantity"
                                                            min="0">
                                                </div>
                                          </div>
                                    </div>
                                    <div class="mb-3">
                                          <label for="edit_description" class="form-label">Description</label>
                                          <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                                    </div>
                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_price" class="form-label">Price *</label>
                                                      <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control" id="edit_price" name="price"
                                                                  step="0.01" min="0" required>
                                                      </div>
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_discount_price" class="form-label">Discount Price</label>
                                                      <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control" id="edit_discount_price" name="discount_price"
                                                                  step="0.01" min="0" placeholder="Leave empty for no discount">
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                                    <div class="mb-3">
                                          <label for="edit_image" class="form-label">Product Image</label>
                                          <div id="current_image_preview" class="mb-2" style="display: none;">
                                                <img id="current_image" src="" alt="Current product image" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                                                <div class="form-text">Current image</div>
                                          </div>
                                          <input type="file" class="form-control" id="edit_image" name="image" accept="image/*">
                                          <div class="form-text">Leave empty to keep current image</div>
                                          <div id="new_image_preview" class="mt-2" style="display: none;">
                                                <img id="preview_image" src="" alt="New image preview" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                                                <div class="form-text">New image preview</div>
                                          </div>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                          <i class="fas fa-save me-2"></i>Update Product
                                    </button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Delete Product Form -->
      <form id="deleteProductForm" method="POST" style="display: none;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete_id">
      </form>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>

      <script>
            function editProduct(product) {
                  document.getElementById('edit_id').value = product.id;
                  document.getElementById('edit_product_code').value = product.product_code || '';
                  document.getElementById('edit_name').value = product.name;
                  document.getElementById('edit_description').value = product.description;
                  document.getElementById('edit_price').value = product.price;
                  document.getElementById('edit_discount_price').value = product.discount_price || '';
                  document.getElementById('edit_stock_quantity').value = product.stock_quantity;

                  // Handle category dropdown
                  const categorySelect = document.getElementById('edit_category');
                  const newCategoryInput = document.getElementById('edit_new_category');

                  if (product.category && product.category.trim() !== '') {
                        // Check if category exists in dropdown
                        let categoryExists = false;
                        for (let option of categorySelect.options) {
                              if (option.value === product.category) {
                                    categorySelect.value = product.category;
                                    categoryExists = true;
                                    break;
                              }
                        }

                        // If category doesn't exist in dropdown, put it in new category input
                        if (!categoryExists) {
                              categorySelect.value = '';
                              newCategoryInput.value = product.category;
                        } else {
                              newCategoryInput.value = '';
                        }
                  } else {
                        categorySelect.value = '';
                        newCategoryInput.value = '';
                  }

                  // Handle image preview
                  const currentImagePreview = document.getElementById('current_image_preview');
                  const currentImage = document.getElementById('current_image');
                  const newImagePreview = document.getElementById('new_image_preview');
                  const previewImage = document.getElementById('preview_image');

                  if (product.image_path && product.image_path.trim() !== '') {
                        currentImage.src = '../' + product.image_path;
                        currentImagePreview.style.display = 'block';
                  } else {
                        currentImagePreview.style.display = 'none';
                  }

                  newImagePreview.style.display = 'none';

                  new bootstrap.Modal(document.getElementById('editProductModal')).show();
            }

            function deleteProduct(id) {
                  if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                        document.getElementById('delete_id').value = id;
                        document.getElementById('deleteProductForm').submit();
                  }
            }

            // Handle category selection logic
            document.addEventListener('DOMContentLoaded', function() {
                  // Add Product Modal
                  const categorySelect = document.getElementById('category');
                  const newCategoryInput = document.getElementById('new_category');

                  if (categorySelect && newCategoryInput) {
                        categorySelect.addEventListener('change', function() {
                              if (this.value) {
                                    newCategoryInput.value = '';
                              }
                        });

                        newCategoryInput.addEventListener('input', function() {
                              if (this.value) {
                                    categorySelect.value = '';
                              }
                        });
                  }

                  // Edit Product Modal
                  const editCategorySelect = document.getElementById('edit_category');
                  const editNewCategoryInput = document.getElementById('edit_new_category');

                  if (editCategorySelect && editNewCategoryInput) {
                        editCategorySelect.addEventListener('change', function() {
                              if (this.value) {
                                    editNewCategoryInput.value = '';
                              }
                        });

                        editNewCategoryInput.addEventListener('input', function() {
                              if (this.value) {
                                    editCategorySelect.value = '';
                              }
                        });
                  }

                  // Handle image preview for edit modal
                  const editImageInput = document.getElementById('edit_image');
                  if (editImageInput) {
                        editImageInput.addEventListener('change', function() {
                              const file = this.files[0];
                              const newImagePreview = document.getElementById('new_image_preview');
                              const previewImage = document.getElementById('preview_image');

                              if (file) {
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                          previewImage.src = e.target.result;
                                          newImagePreview.style.display = 'block';
                                    };
                                    reader.readAsDataURL(file);
                              } else {
                                    newImagePreview.style.display = 'none';
                              }
                        });
                  }

                  // Handle image preview for add modal
                  const addImageInput = document.getElementById('image');
                  if (addImageInput) {
                        addImageInput.addEventListener('change', function() {
                              const file = this.files[0];
                              if (file) {
                                    // Create preview element if it doesn't exist
                                    let previewDiv = document.getElementById('add_image_preview');
                                    if (!previewDiv) {
                                          previewDiv = document.createElement('div');
                                          previewDiv.id = 'add_image_preview';
                                          previewDiv.className = 'mt-2';
                                          previewDiv.innerHTML = '<img id="add_preview_image" src="" alt="Image preview" class="img-thumbnail" style="max-width: 150px; max-height: 150px;"><div class="form-text">Image preview</div>';
                                          addImageInput.parentNode.appendChild(previewDiv);
                                    }

                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                          document.getElementById('add_preview_image').src = e.target.result;
                                          previewDiv.style.display = 'block';
                                    };
                                    reader.readAsDataURL(file);
                              } else {
                                    const previewDiv = document.getElementById('add_image_preview');
                                    if (previewDiv) {
                                          previewDiv.style.display = 'none';
                                    }
                              }
                        });
                  }
            });
      </script>
</body>

</html>