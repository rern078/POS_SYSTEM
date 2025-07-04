<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
      header('Location: ../login.php');
      exit();
}

$pdo = getDBConnection();

// Get products with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
      $where_conditions[] = "(name LIKE ? OR product_code LIKE ? OR description LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      $params[] = "%$search%";
}

if (!empty($category)) {
      $where_conditions[] = "category = ?";
      $params[] = $category;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) FROM products $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_products = $stmt->fetchColumn();
$total_pages = ceil($total_products / $limit);

// Get products
$sql = "SELECT * FROM products $where_clause ORDER BY name ASC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Products - POS System</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="../admin/assets/css/admin.css">

      <style>
            .product-card {
                  transition: transform 0.2s, box-shadow 0.2s;
                  height: 100%;
            }

            .product-card:hover {
                  transform: translateY(-5px);
                  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            }

            .product-image {
                  height: 200px;
                  object-fit: cover;
            }

            .stock-badge {
                  position: absolute;
                  top: 10px;
                  right: 10px;
            }
      </style>
</head>

<body>
      <!-- Navigation -->
      <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                  <a class="navbar-brand" href="index.php">
                        <i class="fas fa-store me-2"></i>POS System
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
                                    <a class="nav-link" href="pos.php">
                                          <i class="fas fa-cash-register me-1"></i>POS
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link active" href="products.php">
                                          <i class="fas fa-box me-1"></i>Products
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="orders.php">
                                          <i class="fas fa-shopping-cart me-1"></i>Orders
                                    </a>
                              </li>
                              <?php if (isManager()): ?>
                                    <li class="nav-item">
                                          <a class="nav-link" href="reports.php">
                                                <i class="fas fa-chart-bar me-1"></i>Reports
                                          </a>
                                    </li>
                              <?php endif; ?>
                        </ul>

                        <ul class="navbar-nav">
                              <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                          <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['username']; ?>
                                          <span class="badge bg-light text-dark ms-1"><?php echo ucfirst($_SESSION['role']); ?></span>
                                    </a>
                                    <ul class="dropdown-menu">
                                          <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                          <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                          <li>
                                                <hr class="dropdown-divider">
                                          </li>
                                          <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                    </ul>
                              </li>
                        </ul>
                  </div>
            </div>
      </nav>

      <!-- Main Content -->
      <div class="container-fluid mt-4">
            <div class="row">
                  <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                              <h2><i class="fas fa-box me-2"></i>Products</h2>
                              <a href="pos.php" class="btn btn-primary">
                                    <i class="fas fa-cash-register me-2"></i>Go to POS
                              </a>
                        </div>

                        <!-- Search and Filter -->
                        <div class="card mb-4">
                              <div class="card-body">
                                    <form method="GET" class="row g-3">
                                          <div class="col-md-4">
                                                <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                                          </div>
                                          <div class="col-md-3">
                                                <select name="category" class="form-select">
                                                      <option value="">All Categories</option>
                                                      <?php foreach ($categories as $cat): ?>
                                                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                                                  <?php echo htmlspecialchars($cat); ?>
                                                            </option>
                                                      <?php endforeach; ?>
                                                </select>
                                          </div>
                                          <div class="col-md-2">
                                                <button type="submit" class="btn btn-primary w-100">
                                                      <i class="fas fa-search me-2"></i>Search
                                                </button>
                                          </div>
                                          <div class="col-md-2">
                                                <a href="products.php" class="btn btn-outline-secondary w-100">
                                                      <i class="fas fa-times me-2"></i>Clear
                                                </a>
                                          </div>
                                    </form>
                              </div>
                        </div>

                        <!-- Products Grid -->
                        <div class="row g-4">
                              <?php if (empty($products)): ?>
                                    <div class="col-12">
                                          <div class="text-center py-5">
                                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                                <h4 class="text-muted">No products found</h4>
                                                <p class="text-muted">Try adjusting your search criteria</p>
                                          </div>
                                    </div>
                              <?php else: ?>
                                    <?php foreach ($products as $product): ?>
                                          <div class="col-lg-3 col-md-4 col-sm-6">
                                                <div class="card product-card h-100">
                                                      <div class="position-relative">
                                                            <?php
                                                            $img_path = !empty($product['image_path']) ? '../' . htmlspecialchars($product['image_path']) : '../images/placeholder.jpg';
                                                            ?>
                                                            <img src="<?php echo $img_path; ?>" class="card-img-top product-image" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='../images/placeholder.jpg'">
                                                            <span class="badge stock-badge bg-<?php echo $product['stock_quantity'] < 10 ? 'warning' : 'success'; ?>">
                                                                  Stock: <?php echo $product['stock_quantity']; ?>
                                                            </span>
                                                      </div>
                                                      <div class="card-body d-flex flex-column">
                                                            <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                            <p class="card-text small text-muted"><?php echo htmlspecialchars($product['product_code']); ?></p>
                                                            <?php if (!empty($product['description'])): ?>
                                                                  <p class="card-text small"><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?><?php echo strlen($product['description']) > 100 ? '...' : ''; ?></p>
                                                            <?php endif; ?>
                                                            <div class="mt-auto">
                                                                  <div class="d-flex justify-content-between align-items-center mb-2">
                                                                        <span class="fw-bold text-primary fs-5">
                                                                              $<?php echo number_format($product['discount_price'] ?: $product['price'], 2); ?>
                                                                        </span>
                                                                        <span class="text-muted small"><?php echo htmlspecialchars($product['category']); ?></span>
                                                                  </div>
                                                                  <?php if ($product['discount_price'] && $product['discount_price'] < $product['price']): ?>
                                                                        <small class="text-muted text-decoration-line-through">
                                                                              $<?php echo number_format($product['price'], 2); ?>
                                                                        </small>
                                                                  <?php endif; ?>
                                                            </div>
                                                      </div>
                                                </div>
                                          </div>
                                    <?php endforeach; ?>
                              <?php endif; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                              <nav aria-label="Products pagination" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                          <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                      <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>">Previous</a>
                                                </li>
                                          <?php endif; ?>

                                          <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                      <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>"><?php echo $i; ?></a>
                                                </li>
                                          <?php endfor; ?>

                                          <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                      <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>">Next</a>
                                                </li>
                                          <?php endif; ?>
                                    </ul>
                              </nav>
                        <?php endif; ?>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>