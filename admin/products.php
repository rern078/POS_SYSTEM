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
                        $barcode = trim($_POST['barcode']);
                        $qr_code = trim($_POST['qr_code']);
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

                              $stmt = $pdo->prepare("INSERT INTO products (product_code, barcode, qr_code, name, description, price, discount_price, stock_quantity, category, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                              if ($stmt->execute([$product_code, $barcode, $qr_code, $name, $description, $price, $discount_price, $stock, $category, $image_path])) {
                                    $message = 'Product added successfully!';
                              } else {
                                    $error = 'Failed to add product.';
                              }
                        }
                        break;

                  case 'update':
                        $id = intval($_POST['id']);
                        $product_code = trim($_POST['product_code']);
                        $barcode = trim($_POST['barcode']);
                        $qr_code = trim($_POST['qr_code']);
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
                                    $stmt = $pdo->prepare("UPDATE products SET product_code = ?, barcode = ?, qr_code = ?, name = ?, description = ?, price = ?, discount_price = ?, stock_quantity = ?, category = ?, image_path = ? WHERE id = ?");
                                    $params = [$product_code, $barcode, $qr_code, $name, $description, $price, $discount_price, $stock, $category, $image_path, $id];
                              } else {
                                    $stmt = $pdo->prepare("UPDATE products SET product_code = ?, barcode = ?, qr_code = ?, name = ?, description = ?, price = ?, discount_price = ?, stock_quantity = ?, category = ? WHERE id = ?");
                                    $params = [$product_code, $barcode, $qr_code, $name, $description, $price, $discount_price, $stock, $category, $id];
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
      $whereConditions[] = "(name LIKE ? OR description LIKE ? OR product_code LIKE ? OR barcode LIKE ? OR qr_code LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
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

// Get categories for filter dropdown
$categories = [];
$catStmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
while ($row = $catStmt->fetch(PDO::FETCH_ASSOC)) {
      $categories[] = $row['category'];
}
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
                                                <li class="breadcrumb-item active" aria-current="page">Products</li>
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
                                                      <i class="fas fa-box"></i>
                                                      Products Management
                                                </h1>
                                                <p class="text-muted mb-0">Manage your product inventory, add new products, and track stock levels.</p>
                                          </div>
                                          <button class="btn btn-modern btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                                <i class="fas fa-plus me-2"></i>Add Product
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
                                                <button type="submit" class="btn btn-modern btn-primary w-100">
                                                      <i class="fas fa-search"></i>
                                                </button>
                                          </div>
                                    </form>
                              </div>
                        </div>

                        <!-- Products Table -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                          <h6 class="content-card-title">
                                                <i class="fas fa-list"></i>
                                                Products List
                                          </h6>
                                          <div>
                                                <a href="generate_qr.php" class="btn btn-modern btn-success me-2">
                                                      <i class="fas fa-qrcode me-1"></i>Generate QR Codes
                                                </a>
                                                <button class="btn btn-modern btn-outline-secondary me-2" onclick="exportTable('productsTable', 'products')">
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
                                          <table class="table table-modern" id="productsTable" width="100%" cellspacing="0">
                                                <thead>
                                                      <tr>
                                                            <th>ID</th>
                                                            <th>Image</th>
                                                            <th>Code</th>
                                                            <th>Barcode</th>
                                                            <th>QR Code</th>
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
                                                      <?php foreach ($products as $product):
                                                            $img_path = !empty($product['image_path']) ? '../' . htmlspecialchars($product['image_path']) : '../images/placeholder.jpg';
                                                      ?>
                                                            <tr>
                                                                  <td><?php echo $product['id']; ?></td>
                                                                  <td>
                                                                        <img src="<?php echo $img_path; ?>"
                                                                              alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                                              class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                                                  </td>
                                                                  <td>
                                                                        <span class="badge bg-info"><?php echo htmlspecialchars($product['product_code'] ?? 'N/A'); ?></span>
                                                                  </td>
                                                                  <td>
                                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($product['barcode'] ?? 'N/A'); ?></span>
                                                                  </td>
                                                                  <td>
                                                                        <span class="badge bg-dark"><?php echo htmlspecialchars($product['qr_code'] ?? 'N/A'); ?></span>
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
                                                                              <button class="btn btn-sm btn-danger" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                                                                    <i class="fas fa-trash"></i>
                                                                              </button>
                                                                        </div>
                                                                  </td>
                                                            </tr>
                                                      <?php endforeach; ?>
                                                </tbody>
                                          </table>
                                    </div>

                                    <!-- Pagination -->
                                    <?php if ($total_pages > 1): ?>
                                          <nav aria-label="Products pagination" class="mt-4">
                                                <ul class="pagination justify-content-center">
                                                      <?php if ($current_page > 1): ?>
                                                            <li class="page-item">
                                                                  <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">
                                                                        Previous
                                                                  </a>
                                                            </li>
                                                      <?php endif; ?>

                                                      <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                                                  <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">
                                                                        <?php echo $i; ?>
                                                                  </a>
                                                            </li>
                                                      <?php endfor; ?>

                                                      <?php if ($current_page < $total_pages): ?>
                                                            <li class="page-item">
                                                                  <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">
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

      <!-- Add Product Modal -->
      <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data" class="form-modern">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="add">

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="product_code" class="form-label">Product Code</label>
                                                      <input type="text" class="form-control" id="product_code" name="product_code" placeholder="Enter product code">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="name" class="form-label">Product Name *</label>
                                                      <input type="text" class="form-control" id="name" name="name" required placeholder="Enter product name">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="barcode" class="form-label">Barcode</label>
                                                      <input type="text" class="form-control" id="barcode" name="barcode" placeholder="Enter barcode">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="qr_code" class="form-label">QR Code</label>
                                                      <input type="text" class="form-control" id="qr_code" name="qr_code" placeholder="Enter QR code">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="mb-3">
                                          <label for="description" class="form-label">Description</label>
                                          <textarea class="form-control" id="description" name="description" rows="3" placeholder="Enter product description"></textarea>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-4">
                                                <div class="mb-3">
                                                      <label for="price" class="form-label">Price *</label>
                                                      <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required placeholder="0.00">
                                                      </div>
                                                </div>
                                          </div>
                                          <div class="col-md-4">
                                                <div class="mb-3">
                                                      <label for="discount_price" class="form-label">Discount Price</label>
                                                      <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control" id="discount_price" name="discount_price" step="0.01" min="0" placeholder="0.00">
                                                      </div>
                                                </div>
                                          </div>
                                          <div class="col-md-4">
                                                <div class="mb-3">
                                                      <label for="stock_quantity" class="form-label">Stock Quantity</label>
                                                      <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" min="0" value="0">
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
                                                                  <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                                            <?php endforeach; ?>
                                                      </select>
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="new_category" class="form-label">Or New Category</label>
                                                      <input type="text" class="form-control" id="new_category" name="new_category" placeholder="Enter new category">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="mb-3">
                                          <label for="image" class="form-label">Product Image</label>
                                          <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                          <div class="form-text">Upload an image (JPEG, PNG, GIF, WebP) under 5MB.</div>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add Product</button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Edit Product Modal -->
      <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="editProductModalLabel">Edit Product</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data" class="form-modern">
                              <div class="modal-body">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" id="edit_id">

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_product_code" class="form-label">Product Code</label>
                                                      <input type="text" class="form-control" id="edit_product_code" name="product_code" placeholder="Enter product code">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_name" class="form-label">Product Name *</label>
                                                      <input type="text" class="form-control" id="edit_name" name="name" required placeholder="Enter product name">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_barcode" class="form-label">Barcode</label>
                                                      <input type="text" class="form-control" id="edit_barcode" name="barcode" placeholder="Enter barcode">
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_qr_code" class="form-label">QR Code</label>
                                                      <input type="text" class="form-control" id="edit_qr_code" name="qr_code" placeholder="Enter QR code">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="mb-3">
                                          <label for="edit_description" class="form-label">Description</label>
                                          <textarea class="form-control" id="edit_description" name="description" rows="3" placeholder="Enter product description"></textarea>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-4">
                                                <div class="mb-3">
                                                      <label for="edit_price" class="form-label">Price *</label>
                                                      <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0" required placeholder="0.00">
                                                      </div>
                                                </div>
                                          </div>
                                          <div class="col-md-4">
                                                <div class="mb-3">
                                                      <label for="edit_discount_price" class="form-label">Discount Price</label>
                                                      <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control" id="edit_discount_price" name="discount_price" step="0.01" min="0" placeholder="0.00">
                                                      </div>
                                                </div>
                                          </div>
                                          <div class="col-md-4">
                                                <div class="mb-3">
                                                      <label for="edit_stock_quantity" class="form-label">Stock Quantity</label>
                                                      <input type="number" class="form-control" id="edit_stock_quantity" name="stock_quantity" min="0" value="0">
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
                                                                  <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                                            <?php endforeach; ?>
                                                      </select>
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="mb-3">
                                                      <label for="edit_new_category" class="form-label">Or New Category</label>
                                                      <input type="text" class="form-control" id="edit_new_category" name="new_category" placeholder="Enter new category">
                                                </div>
                                          </div>
                                    </div>

                                    <div class="mb-3">
                                          <label for="edit_image" class="form-label">Product Image</label>
                                          <input type="file" class="form-control" id="edit_image" name="image" accept="image/*">
                                          <div class="form-text">Upload an image (JPEG, PNG, GIF, WebP) under 5MB. Leave empty to keep current image.</div>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Product</button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <script>
            function editProduct(product) {
                  document.getElementById('edit_id').value = product.id;
                  document.getElementById('edit_product_code').value = product.product_code || '';
                  document.getElementById('edit_name').value = product.name;
                  document.getElementById('edit_barcode').value = product.barcode || '';
                  document.getElementById('edit_qr_code').value = product.qr_code || '';
                  document.getElementById('edit_description').value = product.description || '';
                  document.getElementById('edit_price').value = product.price;
                  document.getElementById('edit_discount_price').value = product.discount_price || '';
                  document.getElementById('edit_stock_quantity').value = product.stock_quantity;
                  document.getElementById('edit_category').value = product.category || '';

                  const editModal = new bootstrap.Modal(document.getElementById('editProductModal'));
                  editModal.show();
            }

            function deleteProduct(id, name) {
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