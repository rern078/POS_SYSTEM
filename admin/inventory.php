<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || !isAdmin()) {
      header('Location: login.php');
      exit();
}

// Get inventory data
$pdo = getDBConnection();

// Pagination settings
$items_per_page = 10; // Number of items per page
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure page is at least 1
$offset = ($current_page - 1) * $items_per_page;

// Get total number of products for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products");
$stmt->execute();
$total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_products / $items_per_page);

// Get inventory statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_products,
    SUM(stock_quantity) as total_stock,
    COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
    COUNT(CASE WHEN stock_quantity < 10 THEN 1 END) as low_stock,
    AVG(stock_quantity) as avg_stock
FROM products");
$stmt->execute();
$inventory_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get low stock products
$stmt = $pdo->prepare("
    SELECT * FROM products 
    WHERE stock_quantity < 10 
    ORDER BY stock_quantity ASC 
    LIMIT 10
");
$stmt->execute();
$low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get out of stock products
$stmt = $pdo->prepare("
    SELECT * FROM products 
    WHERE stock_quantity = 0 
    ORDER BY name ASC
");
$stmt->execute();
$out_of_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get paginated products for inventory table
$stmt = $pdo->prepare("
    SELECT * FROM products 
    ORDER BY stock_quantity ASC, name ASC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$all_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stock by category
$stmt = $pdo->prepare("
    SELECT category, COUNT(*) as product_count, SUM(stock_quantity) as total_stock
    FROM products
    GROUP BY category
    ORDER BY total_stock ASC
");
$stmt->execute();
$stock_by_category = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to generate pagination links
function generatePaginationLinks($current_page, $total_pages, $base_url = 'inventory.php')
{
      $links = '';

      // Previous button
      if ($current_page > 1) {
            $prev_page = $current_page - 1;
            $links .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . $prev_page . '"><i class="fas fa-chevron-left"></i></a></li>';
      } else {
            $links .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i></span></li>';
      }

      // Page numbers
      $start_page = max(1, $current_page - 2);
      $end_page = min($total_pages, $current_page + 2);

      // Show first page if not in range
      if ($start_page > 1) {
            $links .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=1">1</a></li>';
            if ($start_page > 2) {
                  $links .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
      }

      // Show page numbers in range
      for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $current_page) {
                  $links .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                  $links .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . $i . '">' . $i . '</a></li>';
            }
      }

      // Show last page if not in range
      if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                  $links .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $links .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . $total_pages . '">' . $total_pages . '</a></li>';
      }

      // Next button
      if ($current_page < $total_pages) {
            $next_page = $current_page + 1;
            $links .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . $next_page . '"><i class="fas fa-chevron-right"></i></a></li>';
      } else {
            $links .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-right"></i></span></li>';
      }

      return $links;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Inventory Management - POS System</title>

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
                                    <a class="nav-link active" href="inventory.php">
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
                  <div class="col-12">
                        <h1 class="h3 mb-0 text-gray-800">Inventory Management</h1>
                        <p class="text-muted">Monitor and manage your product inventory</p>
                  </div>
            </div>

            <!-- Inventory Statistics Cards -->
            <div class="row mb-4">
                  <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                      Total Products
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      <?php echo $inventory_stats['total_products']; ?>
                                                </div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-box fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>

                  <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                              <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                          <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                      Total Stock
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      <?php echo number_format($inventory_stats['total_stock']); ?>
                                                </div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-warehouse fa-2x text-gray-300"></i>
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
                                                      Low Stock Items
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      <?php echo $inventory_stats['low_stock']; ?>
                                                </div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                                                      Out of Stock
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                      <?php echo $inventory_stats['out_of_stock']; ?>
                                                </div>
                                          </div>
                                          <div class="col-auto">
                                                <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                  <!-- Stock by Category Chart -->
                  <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                              <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Stock by Category</h6>
                              </div>
                              <div class="card-body">
                                    <div class="chart-area">
                                          <canvas id="categoryChart"></canvas>
                                    </div>
                              </div>
                        </div>
                  </div>

                  <!-- Stock Status Chart -->
                  <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                              <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Stock Status</h6>
                              </div>
                              <div class="card-body">
                                    <div class="chart-pie pt-4 pb-2">
                                          <canvas id="stockStatusChart"></canvas>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>

            <!-- Low Stock Alerts -->
            <?php if (!empty($low_stock_products)): ?>
                  <div class="row mb-4">
                        <div class="col-12">
                              <div class="card shadow mb-4 border-left-warning">
                                    <div class="card-header py-3">
                                          <h6 class="m-0 font-weight-bold text-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alerts
                                          </h6>
                                    </div>
                                    <div class="card-body">
                                          <div class="table-responsive">
                                                <table class="table table-bordered">
                                                      <thead>
                                                            <tr>
                                                                  <th>Product</th>
                                                                  <th>Category</th>
                                                                  <th>Current Stock</th>
                                                                  <th>Price</th>
                                                                  <th>Actions</th>
                                                            </tr>
                                                      </thead>
                                                      <tbody>
                                                            <?php foreach ($low_stock_products as $product): ?>
                                                                  <tr>
                                                                        <td>
                                                                              <div class="d-flex align-items-center">
                                                                                    <?php if ($product['image_path']): ?>
                                                                                          <img src="../<?php echo $product['image_path']; ?>"
                                                                                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                                                                class="me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                                                    <?php endif; ?>
                                                                                    <div>
                                                                                          <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                                                          <br><small class="text-muted"><?php echo $product['product_code']; ?></small>
                                                                                    </div>
                                                                              </div>
                                                                        </td>
                                                                        <td><?php echo htmlspecialchars($product['category']); ?></td>
                                                                        <td>
                                                                              <span class="badge bg-warning">
                                                                                    <?php echo $product['stock_quantity']; ?> units
                                                                              </span>
                                                                        </td>
                                                                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                                                                        <td>
                                                                              <a href="products.php?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-warning">
                                                                                    <i class="fas fa-edit"></i> Update Stock
                                                                              </a>
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
            <?php endif; ?>

            <!-- Complete Inventory Table -->
            <div class="row">
                  <div class="col-12">
                        <div class="card shadow mb-4">
                              <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold text-primary">Complete Inventory</h6>
                                    <a href="products.php" class="btn btn-primary btn-sm">
                                          <i class="fas fa-plus me-1"></i>Add Product
                                    </a>
                              </div>
                              <div class="card-body">
                                    <div class="table-responsive">
                                          <table class="table table-bordered" id="inventoryTable" width="100%" cellspacing="0">
                                                <thead>
                                                      <tr>
                                                            <th>Product</th>
                                                            <th>Category</th>
                                                            <th>Stock Level</th>
                                                            <th>Price</th>
                                                            <th>Status</th>
                                                            <th>Last Updated</th>
                                                            <th>Actions</th>
                                                      </tr>
                                                </thead>
                                                <tbody>
                                                      <?php foreach ($all_products as $product): ?>
                                                            <tr>
                                                                  <td>
                                                                        <div class="d-flex align-items-center">
                                                                              <?php if ($product['image_path']): ?>
                                                                                    <img src="../<?php echo $product['image_path']; ?>"
                                                                                          alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                                                          class="me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                                              <?php endif; ?>
                                                                              <div>
                                                                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                                                    <br><small class="text-muted"><?php echo $product['product_code']; ?></small>
                                                                              </div>
                                                                        </div>
                                                                  </td>
                                                                  <td><?php echo htmlspecialchars($product['category']); ?></td>
                                                                  <td>
                                                                        <?php
                                                                        $stock_level = $product['stock_quantity'];
                                                                        $badge_class = $stock_level == 0 ? 'danger' : ($stock_level < 10 ? 'warning' : 'success');
                                                                        ?>
                                                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                                                              <?php echo $stock_level; ?> units
                                                                        </span>
                                                                  </td>
                                                                  <td>$<?php echo number_format($product['price'], 2); ?></td>
                                                                  <td>
                                                                        <?php
                                                                        if ($stock_level == 0) {
                                                                              echo '<span class="badge bg-danger">Out of Stock</span>';
                                                                        } elseif ($stock_level < 10) {
                                                                              echo '<span class="badge bg-warning">Low Stock</span>';
                                                                        } else {
                                                                              echo '<span class="badge bg-success">In Stock</span>';
                                                                        }
                                                                        ?>
                                                                  </td>
                                                                  <td><?php echo date('M d, Y H:i', strtotime($product['updated_at'])); ?></td>
                                                                  <td>
                                                                        <a href="products.php?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                                                              <i class="fas fa-edit"></i>
                                                                        </a>
                                                                        <a href="products.php?view=<?php echo $product['id']; ?>" class="btn btn-sm btn-info">
                                                                              <i class="fas fa-eye"></i>
                                                                        </a>
                                                                  </td>
                                                            </tr>
                                                      <?php endforeach; ?>
                                                </tbody>
                                          </table>
                                    </div>

                                    <!-- Pagination Controls -->
                                    <?php if ($total_pages > 1): ?>
                                          <div class="d-flex justify-content-between align-items-center mt-3">
                                                <div class="text-muted">
                                                      Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_products); ?> of <?php echo $total_products; ?> products
                                                </div>
                                                <nav aria-label="Inventory pagination">
                                                      <ul class="pagination pagination-sm mb-0">
                                                            <?php echo generatePaginationLinks($current_page, $total_pages); ?>
                                                      </ul>
                                                </nav>
                                          </div>
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

      <script>
            // Category Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            const categoryChart = new Chart(categoryCtx, {
                  type: 'bar',
                  data: {
                        labels: <?php echo json_encode(array_column($stock_by_category, 'category')); ?>,
                        datasets: [{
                              label: 'Total Stock',
                              data: <?php echo json_encode(array_column($stock_by_category, 'total_stock')); ?>,
                              backgroundColor: 'rgba(75, 192, 192, 0.6)',
                              borderColor: 'rgb(75, 192, 192)',
                              borderWidth: 1
                        }]
                  },
                  options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                              y: {
                                    beginAtZero: true
                              }
                        }
                  }
            });

            // Stock Status Chart
            const stockStatusCtx = document.getElementById('stockStatusChart').getContext('2d');
            const stockStatusChart = new Chart(stockStatusCtx, {
                  type: 'doughnut',
                  data: {
                        labels: ['In Stock', 'Low Stock', 'Out of Stock'],
                        datasets: [{
                              data: [
                                    <?php echo $inventory_stats['total_products'] - $inventory_stats['low_stock'] - $inventory_stats['out_of_stock']; ?>,
                                    <?php echo $inventory_stats['low_stock']; ?>,
                                    <?php echo $inventory_stats['out_of_stock']; ?>
                              ],
                              backgroundColor: [
                                    '#28a745',
                                    '#ffc107',
                                    '#dc3545'
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
      </script>
</body>

</html>