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
                        // Check stock availability
                        $current_cart_quantity = isset($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id]['quantity'] : 0;
                        $requested_quantity = $current_cart_quantity + $quantity;

                        if ($requested_quantity > $product['stock_quantity']) {
                              echo json_encode([
                                    'success' => false,
                                    'message' => "Insufficient stock! Available: {$product['stock_quantity']}, Requested: {$requested_quantity}"
                              ]);
                              exit;
                        }

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
                              // Check stock availability
                              $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                              $stmt->execute([$product_id]);
                              $product = $stmt->fetch(PDO::FETCH_ASSOC);

                              if ($product && $quantity > $product['stock_quantity']) {
                                    echo json_encode([
                                          'success' => false,
                                          'message' => "Insufficient stock! Available: {$product['stock_quantity']}, Requested: {$quantity}"
                                    ]);
                                    exit;
                              }

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

            case 'clear_cart':
                  $_SESSION['cart'] = [];
                  echo json_encode(['success' => true, 'message' => 'Cart cleared successfully']);
                  exit;

            case 'scan_code':
                  $code = isset($_POST['code']) ? trim($_POST['code']) : '';

                  if (empty($code)) {
                        echo json_encode(['success' => false, 'message' => 'No code provided']);
                        exit;
                  }

                  // Search for product by barcode, QR code, or product code
                  $stmt = $pdo->prepare("SELECT * FROM products WHERE (barcode = ? OR qr_code = ? OR product_code = ?) AND stock_quantity > 0");
                  $stmt->execute([$code, $code, $code]);
                  $product = $stmt->fetch(PDO::FETCH_ASSOC);

                  if ($product) {
                        echo json_encode([
                              'success' => true,
                              'product' => $product,
                              'message' => 'Product found: ' . $product['name']
                        ]);
                  } else {
                        echo json_encode(['success' => false, 'message' => 'Product not found for code: ' . $code]);
                  }
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
                              // Get current stock for validation
                              $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                              $stmt->execute([$product_id]);
                              $current_stock = $stmt->fetchColumn();
?>
                              <div class="cart-item" id="cart-item-<?php echo $product_id; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                          <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                <p class="mb-1 text-muted">$<?php echo number_format($item['price'], 2); ?> each</p>
                                                <div class="quantity-control">
                                                      <button class="quantity-btn" onclick="updateQuantity(<?php echo $product_id; ?>, -1)">-</button>
                                                      <input type="number"
                                                            class="form-control mx-2 quantity-input"
                                                            value="<?php echo $item['quantity']; ?>"
                                                            min="1"
                                                            max="<?php echo $current_stock; ?>"
                                                            style="width: 60px; text-align: center;"
                                                            onchange="updateQuantityDirect(<?php echo $product_id; ?>, this.value)"
                                                            onkeypress="return event.charCode >= 48 && event.charCode <= 57">
                                                      <button class="quantity-btn" onclick="updateQuantity(<?php echo $product_id; ?>, 1)">+</button>
                                                </div>
                                                <small class="text-muted">Available: <?php echo $current_stock; ?></small>
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
                  $cart_html = ob_get_clean();

                  $total = 0;
                  foreach ($_SESSION['cart'] as $item) {
                        $total += $item['price'] * $item['quantity'];
                  }

                  // Generate payment section HTML
                  ob_start();
                  if (!empty($_SESSION['cart'])) {
                        echo '<div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Total:</h5>
                                    <h4 class="mb-0 text-primary">$' . number_format($total, 2) . '</h4>
                              </div>
                              <button class="btn btn-success btn-lg w-100 mb-3" onclick="showPaymentModal()">
                                    <i class="fas fa-credit-card me-2"></i>Process Payment
                              </button>
                              <button class="btn btn-outline-secondary w-100" onclick="clearCart()">
                                    <i class="fas fa-trash me-2"></i>Clear Cart
                              </button>';
                  }
                  $payment_html = ob_get_clean();

                  echo json_encode([
                        'success' => true,
                        'html' => $cart_html,
                        'total' => '$' . number_format($total, 2),
                        'payment_html' => $payment_html
                  ]);
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
                        <div class="col-lg-3 col-md-4 col-6">
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
                              // Get current stock for this product
                              $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                              $stmt->execute([$product_id]);
                              $product = $stmt->fetch(PDO::FETCH_ASSOC);
                              $current_stock = $product ? $product['stock_quantity'] : 0;
                        ?>
                              <div class="cart-item" id="cart-item-<?php echo $product_id; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                          <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                <p class="mb-1 text-muted">$<?php echo number_format($item['price'], 2); ?> each</p>
                                                <div class="quantity-control">
                                                      <button class="quantity-btn" onclick="updateQuantity(<?php echo $product_id; ?>, -1)">-</button>
                                                      <input type="number"
                                                            class="form-control mx-2 quantity-input"
                                                            value="<?php echo $item['quantity']; ?>"
                                                            min="1"
                                                            max="<?php echo $current_stock; ?>"
                                                            style="width: 60px; text-align: center;"
                                                            onchange="updateQuantityDirect(<?php echo $product_id; ?>, this.value)"
                                                            onkeypress="return event.charCode >= 48 && event.charCode <= 57">
                                                      <button class="quantity-btn" onclick="updateQuantity(<?php echo $product_id; ?>, 1)">+</button>
                                                </div>
                                                <small class="text-muted">Available: <?php echo $current_stock; ?></small>
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
                  $cart_html = ob_get_clean();

                  // Generate payment section HTML
                  ob_start();
                  $total = 0;
                  foreach ($_SESSION['cart'] as $item) {
                        $total += $item['price'] * $item['quantity'];
                  }
                  ?>
                  <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Total:</h5>
                        <h4 class="mb-0 text-primary" id="cart-total">$<?php echo number_format($total, 2); ?></h4>
                  </div>

                  <?php if (!empty($_SESSION['cart'])): ?>
                        <button class="btn btn-success btn-lg w-100 mb-3" onclick="showPaymentModal()">
                              <i class="fas fa-credit-card me-2"></i>Process Payment
                        </button>
                        <button class="btn btn-outline-secondary w-100" onclick="clearCart()">
                              <i class="fas fa-trash me-2"></i>Clear Cart
                        </button>
                  <?php endif; ?>
<?php
                  $payment_html = ob_get_clean();

                  echo json_encode([
                        'success' => true,
                        'html' => $cart_html,
                        'total' => '$' . number_format($total, 2),
                        'payment_html' => $payment_html
                  ]);
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

      <!-- POS CSS -->
      <link rel="stylesheet" href="../assets/css/pos.css">

      <!-- Barcode/QR Code Scanner Libraries -->
      <script src="https://unpkg.com/@zxing/library@latest"></script>
      <script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
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
                              <div class="search-box">
                                    <div class="search-container">
                                          <div class="search-header">
                                                <h6 class="search-title mb-3">
                                                      <i class="fas fa-search me-2"></i>Search & Filter Products
                                                </h6>
                                          </div>

                                          <!-- Barcode/QR Code Scanner Section -->
                                          <div class="scanner-section mb-3">
                                                <div class="card">
                                                      <div class="card-header bg-primary text-white">
                                                            <h6 class="mb-0">
                                                                  <i class="fas fa-barcode me-2"></i>Barcode/QR Code Scanner
                                                            </h6>
                                                      </div>
                                                      <div class="card-body">
                                                            <div class="row">
                                                                  <div class="col-md-8">
                                                                        <div class="mb-3">
                                                                              <label for="scanner-input" class="form-label">Scan or Enter Code</label>
                                                                              <div class="input-group">
                                                                                    <input type="text"
                                                                                          class="form-control"
                                                                                          id="scanner-input"
                                                                                          placeholder="Scan barcode or QR code..."
                                                                                          autocomplete="off">
                                                                                    <button class="btn btn-outline-primary" type="button" onclick="scanCode()">
                                                                                          <i class="fas fa-camera"></i> Scan
                                                                                    </button>
                                                                                    <button class="btn btn-outline-success" type="button" onclick="searchByCode()">
                                                                                          <i class="fas fa-search"></i> Search
                                                                                    </button>
                                                                              </div>
                                                                        </div>
                                                                  </div>
                                                                  <div class="col-md-4">
                                                                        <div class="mb-3">
                                                                              <label class="form-label">Scanner Status</label>
                                                                              <div class="d-flex align-items-center">
                                                                                    <div id="scanner-status" class="badge bg-secondary me-2">Ready</div>
                                                                                    <button class="btn btn-sm btn-outline-info" onclick="toggleScanner()">
                                                                                          <i class="fas fa-camera"></i> Toggle Camera
                                                                                    </button>
                                                                              </div>
                                                                        </div>
                                                                  </div>
                                                            </div>
                                                            <div id="scanner-video-container" class="text-center" style="display: none;">
                                                                  <video id="scanner-video" width="100%" height="200" style="border: 1px solid #ddd; border-radius: 8px;"></video>
                                                                  <div class="mt-2">
                                                                        <button class="btn btn-sm btn-danger" onclick="stopScanner()">
                                                                              <i class="fas fa-stop"></i> Stop Scanner
                                                                        </button>
                                                                  </div>
                                                            </div>
                                                      </div>
                                                </div>
                                          </div>
                                          <div class="search-controls">
                                                <div class="search-row">
                                                      <div class="search-input-group">
                                                            <label for="search-input" class="search-label">Search</label>
                                                            <div class="input-with-icon">
                                                                  <i class="fas fa-search search-icon"></i>
                                                                  <input type="text"
                                                                        id="search-input"
                                                                        name="search"
                                                                        class="form-control search-input"
                                                                        placeholder="Search by name or code..."
                                                                        value="<?php echo htmlspecialchars($search); ?>">
                                                            </div>
                                                      </div>

                                                      <div class="search-input-group">
                                                            <label for="category-select" class="search-label">Category</label>
                                                            <select id="category-select" name="category" class="form-select category-select">
                                                                  <option value="">All Categories</option>
                                                                  <?php foreach ($categories as $cat): ?>
                                                                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                                                              <?php echo htmlspecialchars($cat); ?>
                                                                        </option>
                                                                  <?php endforeach; ?>
                                                            </select>
                                                      </div>

                                                      <div class="search-actions">
                                                            <button type="button" class="btn btn-primary search-btn" onclick="fetchProducts()">
                                                                  <i class="fas fa-search me-2"></i>Search
                                                            </button>
                                                            <button type="button" class="btn btn-outline-secondary clear-btn" onclick="clearFilters()">
                                                                  <i class="fas fa-times me-2"></i>Clear
                                                            </button>
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                              </div>

                              <!-- Products Grid -->
                              <div id="products-grid" class="row g-3 p-3">
                                    <?php foreach ($products as $product): ?>
                                          <div class="col-lg-3 col-md-4 col-6">
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
                  const currentQty = parseInt(document.querySelector(`#cart-item-${productId} .quantity-control input`).value);
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
                              } else {
                                    alert(data.message);
                                    // Reset to previous value if stock validation fails
                                    fetchCart();
                              }
                        });
            }

            function updateQuantityDirect(productId, newQuantity) {
                  const quantity = parseInt(newQuantity);

                  if (isNaN(quantity) || quantity <= 0) {
                        // Reset to 1 if invalid input
                        document.querySelector(`#cart-item-${productId} .quantity-control input`).value = 1;
                        return;
                  }

                  fetch('pos.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                              },
                              body: `action=update_cart&product_id=${productId}&quantity=${quantity}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    fetchCart();
                              } else {
                                    alert(data.message);
                                    // Reset to previous value if stock validation fails
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

                                    // Update payment section
                                    const paymentSection = document.querySelector('.payment-section');
                                    if (paymentSection && data.payment_html) {
                                          paymentSection.innerHTML = data.payment_html;
                                    }
                              }
                        });
            }

            // Barcode/QR Code Scanner Functions
            let scanner = null;
            let isScanning = false;

            function scanCode() {
                  const code = document.getElementById('scanner-input').value.trim();
                  if (code) {
                        searchByCode();
                  } else {
                        toggleScanner();
                  }
            }

            function searchByCode() {
                  const code = document.getElementById('scanner-input').value.trim();
                  if (!code) {
                        alert('Please enter a code to search');
                        return;
                  }

                  updateScannerStatus('Searching...', 'info');

                  fetch('pos.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                              },
                              body: `action=scan_code&code=${encodeURIComponent(code)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    updateScannerStatus('Product found!', 'success');
                                    // Add product to cart
                                    addToCart(data.product.id);
                                    // Clear scanner input
                                    document.getElementById('scanner-input').value = '';
                                    // Show success message
                                    showNotification(data.message, 'success');
                              } else {
                                    updateScannerStatus('Product not found', 'danger');
                                    showNotification(data.message, 'error');
                              }
                        })
                        .catch(error => {
                              updateScannerStatus('Error', 'danger');
                              showNotification('Error searching for product', 'error');
                        });
            }

            function toggleScanner() {
                  if (isScanning) {
                        stopScanner();
                  } else {
                        startScanner();
                  }
            }

            function startScanner() {
                  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        alert('Camera access is not supported in this browser');
                        return;
                  }

                  updateScannerStatus('Starting...', 'warning');

                  // Try ZXing first (better for QR codes)
                  if (typeof ZXing !== 'undefined') {
                        startZXingScanner();
                  } else {
                        // Fallback to Quagga for barcodes
                        startQuaggaScanner();
                  }
            }

            function startZXingScanner() {
                  const video = document.getElementById('scanner-video');
                  const container = document.getElementById('scanner-video-container');

                  navigator.mediaDevices.getUserMedia({
                              video: {
                                    facingMode: 'environment'
                              }
                        })
                        .then(stream => {
                              video.srcObject = stream;
                              video.play();

                              container.style.display = 'block';
                              isScanning = true;
                              updateScannerStatus('Scanning...', 'success');

                              // Initialize ZXing
                              const codeReader = new ZXing.BrowserMultiFormatReader();
                              codeReader.decodeFromVideoDevice(null, 'scanner-video', (result, err) => {
                                    if (result) {
                                          console.log('Scanned:', result.text);
                                          document.getElementById('scanner-input').value = result.text;
                                          searchByCode();
                                          stopScanner();
                                    }
                                    if (err && !(err instanceof ZXing.NotFoundException)) {
                                          console.error('Scanning error:', err);
                                    }
                              });
                        })
                        .catch(error => {
                              console.error('Camera access error:', error);
                              updateScannerStatus('Camera access denied', 'danger');
                              alert('Unable to access camera. Please check permissions.');
                        });
            }

            function startQuaggaScanner() {
                  const container = document.getElementById('scanner-video-container');
                  container.style.display = 'block';

                  Quagga.init({
                        inputStream: {
                              name: "Live",
                              type: "LiveStream",
                              target: container,
                              constraints: {
                                    facingMode: "environment"
                              },
                        },
                        decoder: {
                              readers: [
                                    "code_128_reader",
                                    "ean_reader",
                                    "ean_8_reader",
                                    "code_39_reader",
                                    "code_39_vin_reader",
                                    "codabar_reader",
                                    "upc_reader",
                                    "upc_e_reader",
                                    "i2of5_reader"
                              ]
                        }
                  }, function(err) {
                        if (err) {
                              console.error('Quagga initialization error:', err);
                              updateScannerStatus('Scanner error', 'danger');
                              return;
                        }

                        isScanning = true;
                        updateScannerStatus('Scanning...', 'success');
                        Quagga.start();

                        Quagga.onDetected(function(result) {
                              console.log('Scanned:', result.codeResult.code);
                              document.getElementById('scanner-input').value = result.codeResult.code;
                              searchByCode();
                              stopScanner();
                        });
                  });
            }

            function stopScanner() {
                  const video = document.getElementById('scanner-video');
                  const container = document.getElementById('scanner-video-container');

                  if (video.srcObject) {
                        const tracks = video.srcObject.getTracks();
                        tracks.forEach(track => track.stop());
                        video.srcObject = null;
                  }

                  if (typeof Quagga !== 'undefined') {
                        Quagga.stop();
                  }

                  container.style.display = 'none';
                  isScanning = false;
                  updateScannerStatus('Ready', 'secondary');
            }

            function updateScannerStatus(message, type) {
                  const statusElement = document.getElementById('scanner-status');
                  statusElement.textContent = message;
                  statusElement.className = `badge bg-${type} me-2`;
            }

            function showNotification(message, type) {
                  // Create notification element
                  const notification = document.createElement('div');
                  notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
                  notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                  notification.innerHTML = `
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  `;

                  document.body.appendChild(notification);

                  // Auto remove after 3 seconds
                  setTimeout(() => {
                        if (notification.parentNode) {
                              notification.remove();
                        }
                  }, 3000);
            }

            // Handle scanner input enter key
            document.addEventListener('DOMContentLoaded', function() {
                  const scannerInput = document.getElementById('scanner-input');
                  if (scannerInput) {
                        scannerInput.addEventListener('keypress', function(e) {
                              if (e.key === 'Enter') {
                                    searchByCode();
                              }
                        });
                  }
            });
      </script>
</body>

</html>