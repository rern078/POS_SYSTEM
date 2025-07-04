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
$message = '';
$error = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
      header('Content-Type: application/json');

      switch ($_POST['action']) {
            case 'add_to_cart':
                  $product_id = $_POST['product_id'];
                  $quantity = (int)$_POST['quantity'];

                  if (!isset($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                  }

                  // Get product details
                  $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                  $stmt->execute([$product_id]);
                  $product = $stmt->fetch(PDO::FETCH_ASSOC);

                  if ($product) {
                        if (isset($_SESSION['cart'][$product_id])) {
                              $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                        } else {
                              $_SESSION['cart'][$product_id] = [
                                    'id' => $product['id'],
                                    'name' => $product['name'],
                                    'price' => $product['discount_price'] ?: $product['price'],
                                    'quantity' => $quantity,
                                    'image' => $product['image_path']
                              ];
                        }
                        echo json_encode(['success' => true, 'message' => 'Product added to cart']);
                  } else {
                        echo json_encode(['success' => false, 'message' => 'Product not found']);
                  }
                  exit;

            case 'update_cart':
                  $product_id = $_POST['product_id'];
                  $quantity = (int)$_POST['quantity'];

                  if (isset($_SESSION['cart'][$product_id])) {
                        if ($quantity <= 0) {
                              unset($_SESSION['cart'][$product_id]);
                        } else {
                              $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                        }
                  }

                  $total = 0;
                  foreach ($_SESSION['cart'] as $item) {
                        $total += $item['price'] * $item['quantity'];
                  }

                  echo json_encode(['success' => true, 'total' => $total]);
                  exit;

            case 'remove_from_cart':
                  $product_id = $_POST['product_id'];

                  if (isset($_SESSION['cart'][$product_id])) {
                        unset($_SESSION['cart'][$product_id]);
                  }

                  $total = 0;
                  foreach ($_SESSION['cart'] as $item) {
                        $total += $item['price'] * $item['quantity'];
                  }

                  echo json_encode(['success' => true, 'total' => $total]);
                  exit;

            case 'process_payment':
                  if (empty($_SESSION['cart'])) {
                        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
                        exit;
                  }

                  $customer_name = trim($_POST['customer_name']);
                  $customer_email = trim($_POST['customer_email']);
                  $payment_method = $_POST['payment_method'];
                  $total_amount = 0;

                  // Calculate total
                  foreach ($_SESSION['cart'] as $item) {
                        $total_amount += $item['price'] * $item['quantity'];
                  }

                  try {
                        $pdo->beginTransaction();

                        // Create order
                        $stmt = $pdo->prepare("INSERT INTO orders (customer_name, customer_email, total_amount, payment_method, status) VALUES (?, ?, ?, ?, 'completed')");
                        $stmt->execute([$customer_name, $customer_email, $total_amount, $payment_method]);
                        $order_id = $pdo->lastInsertId();

                        // Add order items and update stock
                        foreach ($_SESSION['cart'] as $product_id => $item) {
                              // Add order item
                              $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                              $stmt->execute([$order_id, $product_id, $item['quantity'], $item['price']]);

                              // Update stock
                              $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                              $stmt->execute([$item['quantity'], $product_id]);
                        }

                        $pdo->commit();

                        // Clear cart
                        $_SESSION['cart'] = [];

                        echo json_encode(['success' => true, 'order_id' => $order_id, 'message' => 'Payment processed successfully']);
                  } catch (Exception $e) {
                        $pdo->rollBack();
                        echo json_encode(['success' => false, 'message' => 'Error processing payment: ' . $e->getMessage()]);
                  }
                  exit;

            case 'fetch_products':
                  $search = isset($_POST['search']) ? trim($_POST['search']) : '';
                  $category = isset($_POST['category']) ? $_POST['category'] : '';

                  $where_conditions = ["stock_quantity > 0"];
                  $params = [];

                  if (!empty($search)) {
                        $where_conditions[] = "(name LIKE ? OR product_code LIKE ?)";
                        $params[] = "%$search%";
                        $params[] = "%$search%";
                  }

                  if (!empty($category)) {
                        $where_conditions[] = "category = ?";
                        $params[] = $category;
                  }

                  $where_clause = implode(' AND ', $where_conditions);

                  $stmt = $pdo->prepare("SELECT * FROM products WHERE $where_clause ORDER BY name ASC");
                  $stmt->execute($params);
                  $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                  ob_start();
                  foreach ($products as $product) {
                        $img_path = !empty($product['image_path']) ? '../' . htmlspecialchars($product['image_path']) : '../images/placeholder.jpg';
?>
                        <div class="col-lg-3 col-md-4 col-sm-6">
                              <div class="card product-card" onclick="addToCart(<?php echo $product['id']; ?>)">
                                    <img src="<?php echo $img_path; ?>"
                                          class="card-img-top product-image"
                                          alt="<?php echo htmlspecialchars($product['name']); ?>"
                                          onerror="this.src='../images/placeholder.jpg'">
                                    <div class="card-body">
                                          <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                          <p class="card-text small text-muted"><?php echo htmlspecialchars($product['product_code']); ?></p>
                                          <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-bold text-primary">
                                                      $<?php echo number_format($product['discount_price'] ?: $product['price'], 2); ?>
                                                </span>
                                                <span class="badge bg-<?php echo $product['stock_quantity'] < 10 ? 'warning' : 'success'; ?>">
                                                      Stock: <?php echo $product['stock_quantity']; ?>
                                                </span>
                                          </div>
                                          <?php if ($product['discount_price'] && $product['discount_price'] < $product['price']): ?>
                                                <small class="text-muted text-decoration-line-through">
                                                      $<?php echo number_format($product['price'], 2); ?>
                                                </small>
                                          <?php endif; ?>
                                    </div>
                              </div>
                        </div>
                        <?php
                  }
                  $html = ob_get_clean();
                  echo json_encode(['success' => true, 'html' => $html]);
                  exit;

            case 'fetch_cart':
                  ob_start();
                  if (empty($_SESSION['cart'])) {
                        echo '<div class="text-center mt-4 justify-content-center align-items-center d-flex flex-column w-100">
                                <img src="../images/placeholder-cart.png" alt="Empty Cart" style="width:120px;opacity:0.5;" class="mb-2">
                                <div class="text-muted">Cart is empty</div>
                              </div>';
                  } else {
                        foreach ($_SESSION['cart'] as $product_id => $item) {
                        ?>
                              <div class="cart-item" id="cart-item-<?php echo $product_id; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                          <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                <p class="mb-1 text-muted">$<?php echo number_format($item['price'], 2); ?> each</p>
                                                <div class="quantity-control">
                                                      <button class="quantity-btn" onclick="updateQuantity(<?php echo $product_id; ?>, -1)">-</button>
                                                      <span class="mx-2"><?php echo $item['quantity']; ?></span>
                                                      <button class="quantity-btn" onclick="updateQuantity(<?php echo $product_id; ?>, 1)">+</button>
                                                </div>
                                          </div>
                                          <div class="text-end">
                                                <h6 class="mb-1">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></h6>
                                                <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(<?php echo $product_id; ?>)">
                                                      <i class="fas fa-trash"></i>
                                                </button>
                                          </div>
                                    </div>
                              </div>
<?php
                        }
                  }
                  $html = ob_get_clean();
                  $total = 0;
                  foreach ($_SESSION['cart'] as $item) {
                        $total += $item['price'] * $item['quantity'];
                  }
                  echo json_encode(['success' => true, 'html' => $html, 'total' => '$' . number_format($total, 2)]);
                  exit;

            case 'clear_cart':
                  $_SESSION['cart'] = [];
                  echo json_encode(['success' => true, 'message' => 'Cart cleared successfully']);
                  exit;
      }
}

// Get products for display
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';

$where_conditions = ["stock_quantity > 0"];
$params = [];

if (!empty($search)) {
      $where_conditions[] = "(name LIKE ? OR product_code LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
}

if (!empty($category)) {
      $where_conditions[] = "category = ?";
      $params[] = $category;
}

$where_clause = implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("SELECT * FROM products WHERE $where_clause ORDER BY name ASC");
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE stock_quantity > 0 AND category IS NOT NULL ORDER BY category");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>POS - Point of Sale</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="../admin/assets/css/admin.css">

      <style>
            body {
                  background: #f4f6fa;
            }

            .pos-container {
                  height: calc(100vh - 80px);
                  overflow: hidden;
            }

            .products-section {
                  height: 100%;
                  overflow-x: hidden;
                  overflow-y: auto;
                  background: #fff;
                  border-radius: 12px;
                  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
                  padding-bottom: 20px;
                  scrollbar-width: none;
            }

            .cart-section {
                  height: 100%;
                  display: flex;
                  flex-direction: column;
                  background: #fff;
                  border-radius: 12px;
                  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            }

            .cart-items {
                  flex: 1;
                  overflow-y: auto;
            }

            .product-card {
                  cursor: pointer;
                  transition: box-shadow 0.2s, transform 0.2s;
                  height: 100%;
                  border: none;
                  border-radius: 12px;
                  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
                  background: #f9fafb;
                  display: flex;
                  flex-direction: column;
            }

            .product-card:hover {
                  transform: translateY(-2px) scale(1.03);
                  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.10);
                  background: #f1f3f7;
            }

            .product-image {
                  width: 100%;
                  height: 140px;
                  object-fit: cover;
                  border-radius: 12px 12px 0 0;
                  background: #e9ecef;
            }

            .card-body {
                  flex: 1 1 auto;
                  display: flex;
                  flex-direction: column;
                  justify-content: space-between;
            }

            .card-title {
                  font-size: 1.05rem;
                  font-weight: 600;
            }

            .card-text {
                  font-size: 0.92rem;
            }

            .cart-item {
                  border-bottom: 1px solid #eee;
                  padding: 10px 0;
            }

            .quantity-control {
                  display: flex;
                  align-items: center;
                  gap: 5px;
            }

            .quantity-btn {
                  width: 32px;
                  height: 32px;
                  border: none;
                  background: #f1f3f7;
                  border-radius: 6px;
                  cursor: pointer;
                  font-size: 1.2rem;
                  color: #333;
                  transition: background 0.15s;
            }

            .quantity-btn:hover {
                  background: #e2e6ea;
            }

            .payment-section {
                  border-top: 2px solid #dee2e6;
                  padding-top: 15px;
                  background: #f8f9fa;
                  border-radius: 0 0 12px 12px;
            }

            .search-box {
                  position: sticky;
                  top: 0;
                  background: #fff;
                  z-index: 10;
                  padding: 18px 0 10px 0;
                  border-bottom: 1px solid #dee2e6;
                  margin-bottom: 10px;
            }

            .search-box input,
            .search-box select {
                  border-radius: 8px;
            }

            .btn-success.btn-lg {
                  font-size: 1.1rem;
                  padding: 12px 0;
                  border-radius: 8px;
            }

            .btn-outline-secondary {
                  border-radius: 8px;
            }

            @media (max-width: 991px) {

                  .products-section,
                  .cart-section {
                        border-radius: 0;
                        box-shadow: none;
                  }
            }

            @media (max-width: 767px) {
                  .pos-container {
                        height: auto;
                  }

                  .products-section,
                  .cart-section {
                        height: auto;
                        min-height: 300px;
                  }

                  .row.g-3.p-3 {
                        padding: 0 !important;
                  }
            }

            /* For Webkit browsers */
            .products-section::-webkit-scrollbar,
            .cart-items::-webkit-scrollbar {
                  width: 8px;
                  background: #f4f6fa;
            }

            .products-section::-webkit-scrollbar-thumb,
            .cart-items::-webkit-scrollbar-thumb {
                  background: #cfd8dc;
                  border-radius: 8px;
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
                                    <a class="nav-link active" href="pos.php">
                                          <i class="fas fa-cash-register me-1"></i>POS
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="products.php">
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
      <div class="container-fluid pos-container">
            <div class="row h-100">
                  <!-- Products Section -->
                  <div class="col-md-8 h-100">
                        <div class="products-section">
                              <!-- Search and Filter -->
                              <div class="search-box p-2">
                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="d-flex">
                                                      <input type="text" id="search-input" name="search" class="form-control me-2" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                                                      <select id="category-select" name="category" class="form-select">
                                                            <option value="">All Categories</option>
                                                            <?php foreach ($categories as $cat): ?>
                                                                  <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($cat); ?>
                                                                  </option>
                                                            <?php endforeach; ?>
                                                      </select>
                                                      <button type="button" class="btn btn-outline-primary" onclick="fetchProducts()">
                                                            <i class="fas fa-search"></i>
                                                      </button>
                                                </div>
                                          </div>
                                          <div class="col-md-2 d-flex justify-center">
                                                <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                                                      <i class="fas fa-times"></i> Clear
                                                </button>
                                          </div>
                                    </div>
                              </div>

                              <!-- Products Grid -->
                              <div id="products-grid" class="row g-3 p-3">
                                    <?php foreach ($products as $product): ?>
                                          <div class="col-lg-3 col-md-4 col-sm-6">
                                                <div class="card product-card" onclick="addToCart(<?php echo $product['id']; ?>)">
                                                      <?php
                                                      $img_path = !empty($product['image_path']) ? '../' . htmlspecialchars($product['image_path']) : '../images/placeholder.jpg';
                                                      ?>
                                                      <img src="<?php echo $img_path; ?>"
                                                            class="card-img-top product-image"
                                                            alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                            onerror="this.src='../images/placeholder.jpg'">
                                                      <div class="card-body">
                                                            <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                            <p class="card-text small text-muted"><?php echo htmlspecialchars($product['product_code']); ?></p>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                  <span class="fw-bold text-primary">
                                                                        $<?php echo number_format($product['discount_price'] ?: $product['price'], 2); ?>
                                                                  </span>
                                                                  <span class="badge bg-<?php echo $product['stock_quantity'] < 10 ? 'warning' : 'success'; ?>">
                                                                        Stock: <?php echo $product['stock_quantity']; ?>
                                                                  </span>
                                                            </div>
                                                            <?php if ($product['discount_price'] && $product['discount_price'] < $product['price']): ?>
                                                                  <small class="text-muted text-decoration-line-through">
                                                                        $<?php echo number_format($product['price'], 2); ?>
                                                                  </small>
                                                            <?php endif; ?>
                                                      </div>
                                                </div>
                                          </div>
                                    <?php endforeach; ?>
                              </div>
                        </div>
                  </div>

                  <!-- Cart Section -->
                  <div class="col-md-4 h-100">
                        <div class="cart-section bg-light">
                              <div class="p-3 border-bottom m-2">
                                    <h5 class="mb-0">
                                          <i class="fas fa-shopping-cart me-2"></i>Shopping Cart
                                    </h5>
                              </div>

                              <!-- Cart Items -->
                              <div class="cart-items p-3">
                                    <div id="cart-items-container">
                                          <?php if (empty($_SESSION['cart'])): ?>
                                                <div class="text-center mt-4 justify-content-center align-items-center d-flex flex-column w-100">
                                                      <img src="../images/placeholder-cart.png" alt="Empty Cart" style="width:120px;opacity:0.5;" class="mb-2">
                                                      <div class="text-muted">Cart is empty</div>
                                                </div>
                                          <?php else: ?>
                                                <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                                                      <div class="cart-item" id="cart-item-<?php echo $product_id; ?>">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                  <div class="flex-grow-1">
                                                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                                        <p class="mb-1 text-muted">$<?php echo number_format($item['price'], 2); ?> each</p>
                                                                        <div class="quantity-control">
                                                                              <button class="quantity-btn" onclick="updateQuantity(<?php echo $product_id; ?>, -1)">-</button>
                                                                              <span class="mx-2"><?php echo $item['quantity']; ?></span>
                                                                              <button class="quantity-btn" onclick="updateQuantity(<?php echo $product_id; ?>, 1)">+</button>
                                                                        </div>
                                                                  </div>
                                                                  <div class="text-end">
                                                                        <h6 class="mb-1">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></h6>
                                                                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(<?php echo $product_id; ?>)">
                                                                              <i class="fas fa-trash"></i>
                                                                        </button>
                                                                  </div>
                                                            </div>
                                                      </div>
                                                <?php endforeach; ?>
                                          <?php endif; ?>
                                    </div>
                              </div>

                              <!-- Payment Section -->
                              <div class="payment-section p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                          <h5 class="mb-0">Total:</h5>
                                          <h4 class="mb-0 text-primary" id="cart-total">
                                                $<?php
                                                      $total = 0;
                                                      if (!empty($_SESSION['cart'])) {
                                                            foreach ($_SESSION['cart'] as $item) {
                                                                  $total += $item['price'] * $item['quantity'];
                                                            }
                                                      }
                                                      echo number_format($total, 2);
                                                      ?>
                                          </h4>
                                    </div>

                                    <?php if (!empty($_SESSION['cart'])): ?>
                                          <button class="btn btn-success btn-lg w-100 mb-3" onclick="showPaymentModal()">
                                                <i class="fas fa-credit-card me-2"></i>Process Payment
                                          </button>
                                          <button class="btn btn-outline-secondary w-100" onclick="clearCart()">
                                                <i class="fas fa-trash me-2"></i>Clear Cart
                                          </button>
                                    <?php endif; ?>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Payment Modal -->
      <div class="modal fade" id="paymentModal" tabindex="-1">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Process Payment</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="paymentForm">
                              <div class="modal-body">
                                    <div class="mb-3">
                                          <label for="customer_name" class="form-label">Customer Name</label>
                                          <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                                    </div>
                                    <div class="mb-3">
                                          <label for="customer_email" class="form-label">Customer Email</label>
                                          <input type="email" class="form-control" id="customer_email" name="customer_email">
                                    </div>
                                    <div class="mb-3">
                                          <label for="payment_method" class="form-label">Payment Method</label>
                                          <select class="form-select" id="payment_method" name="payment_method" required>
                                                <option value="cash">Cash</option>
                                                <option value="card">Credit/Debit Card</option>
                                                <option value="mobile">Mobile Payment</option>
                                                <option value="other">Other</option>
                                          </select>
                                    </div>
                                    <div class="alert alert-info">
                                          <strong>Total Amount: $<span id="modal-total">0.00</span></strong>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">
                                          <i class="fas fa-check me-2"></i>Complete Payment
                                    </button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Success Modal -->
      <div class="modal fade" id="successModal" tabindex="-1">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                              <h5 class="modal-title">
                                    <i class="fas fa-check-circle me-2"></i>Payment Successful
                              </h5>
                              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                              <h4 class="text-success mb-3">Transaction Completed!</h4>
                              <p>Order ID: <strong id="order-id"></strong></p>
                              <p>Thank you for your purchase!</p>
                        </div>
                        <div class="modal-footer">
                              <button type="button" class="btn btn-success" onclick="printReceipt()">
                                    <i class="fas fa-print me-2"></i>Print Receipt
                              </button>
                              <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Continue</button>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

      <script>
            let currentOrderId = null;
            let searchTimeout = null;

            function fetchProducts() {
                  const search = document.getElementById('search-input').value;
                  const category = document.getElementById('category-select').value;
                  const formData = new URLSearchParams();
                  formData.append('action', 'fetch_products');
                  formData.append('search', search);
                  formData.append('category', category);

                  fetch('pos.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                              },
                              body: formData.toString()
                        })
                        .then(res => res.json())
                        .then(data => {
                              if (data.success) {
                                    document.getElementById('products-grid').innerHTML = data.html;
                              }
                        });
            }

            function debouncedSearch() {
                  clearTimeout(searchTimeout);
                  searchTimeout = setTimeout(fetchProducts, 300);
            }

            document.getElementById('search-input').addEventListener('input', debouncedSearch);
            document.getElementById('category-select').addEventListener('change', fetchProducts);

            function addToCart(productId) {
                  fetch('pos.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                              },
                              body: `action=add_to_cart&product_id=${productId}&quantity=1`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    // Fetch and update cart section
                                    fetchCart();
                              } else {
                                    alert(data.message);
                              }
                        });
            }

            function updateQuantity(productId, change) {
                  const currentQty = parseInt(document.querySelector(`#cart-item-${productId} .quantity-control span`).textContent);
                  const newQty = currentQty + change;

                  if (newQty <= 0) {
                        removeFromCart(productId);
                        return;
                  }

                  fetch('pos.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                              },
                              body: `action=update_cart&product_id=${productId}&quantity=${newQty}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    fetchCart();
                              }
                        });
            }

            function removeFromCart(productId) {
                  fetch('pos.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                              },
                              body: `action=remove_from_cart&product_id=${productId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    fetchCart();
                              }
                        });
            }

            function clearCart() {
                  if (confirm('Are you sure you want to clear the cart?')) {
                        fetch('pos.php', {
                                    method: 'POST',
                                    headers: {
                                          'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: 'action=clear_cart'
                              })
                              .then(response => response.json())
                              .then(data => {
                                    if (data.success) {
                                          fetchCart();
                                    }
                              });
                  }
            }

            function showPaymentModal() {
                  const total = document.getElementById('cart-total').textContent;
                  document.getElementById('modal-total').textContent = total.replace('$', '');
                  new bootstrap.Modal(document.getElementById('paymentModal')).show();
            }

            function clearFilters() {
                  window.location.href = 'pos.php';
            }

            document.getElementById('paymentForm').addEventListener('submit', function(e) {
                  e.preventDefault();

                  const formData = new FormData(this);
                  formData.append('action', 'process_payment');

                  fetch('pos.php', {
                              method: 'POST',
                              body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    currentOrderId = data.order_id;
                                    document.getElementById('order-id').textContent = data.order_id;
                                    bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
                                    new bootstrap.Modal(document.getElementById('successModal')).show();
                                    // Update cart after successful payment
                                    fetchCart();
                              } else {
                                    alert(data.message);
                              }
                        });
            });

            function printReceipt() {
                  if (currentOrderId) {
                        window.open(`receipt.php?order_id=${currentOrderId}`, '_blank');
                  }
            }

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                  if (e.ctrlKey && e.key === 'p') {
                        e.preventDefault();
                        showPaymentModal();
                  }
            });

            function fetchCart() {
                  fetch('pos.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                              },
                              body: 'action=fetch_cart'
                        })
                        .then(res => res.json())
                        .then(data => {
                              if (data.success) {
                                    document.getElementById('cart-items-container').innerHTML = data.html;
                                    document.getElementById('cart-total').textContent = data.total;
                              }
                        });
            }
      </script>
</body>

</html>