<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/exchange_rate.php';

// Check if user is logged in
if (!isLoggedIn()) {
      header('Location: ../login.php');
      exit();
}

$pdo = getDBConnection();
$exchangeRate = new ExchangeRate();
$currencies = $exchangeRate->getCurrencies();
$defaultCurrency = $exchangeRate->getDefaultCurrency();
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

            case 'convert_currency':
                  $amount = (float)($_POST['amount'] ?? 0);
                  $fromCurrency = $_POST['from_currency'] ?? 'USD';
                  $toCurrency = $_POST['to_currency'] ?? 'USD';

                  if ($amount <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Invalid amount']);
                        exit;
                  }

                  $convertedAmount = $exchangeRate->convertCurrency($amount, $fromCurrency, $toCurrency);
                  $rate = $exchangeRate->getExchangeRate($fromCurrency, $toCurrency);
                  $formattedAmount = $exchangeRate->formatAmount($convertedAmount, $toCurrency);

                  echo json_encode([
                        'success' => true,
                        'converted_amount' => $convertedAmount,
                        'formatted_amount' => $formattedAmount,
                        'rate' => $rate,
                        'from_currency' => $fromCurrency,
                        'to_currency' => $toCurrency
                  ]);
                  exit;

            case 'get_currencies':
                  echo json_encode([
                        'success' => true,
                        'currencies' => $currencies,
                        'default_currency' => $defaultCurrency
                  ]);
                  exit;

            case 'process_payment':
                  if (empty($_SESSION['cart'])) {
                        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
                        exit;
                  }

                  $customer_name = trim($_POST['customer_name']);
                  $customer_email = trim($_POST['customer_email']);
                  $payment_method = $_POST['payment_method'];
                  $currency_code = $_POST['currency_code'] ?? 'USD';
                  $amount_tendered = isset($_POST['amount_tendered']) ? (float)$_POST['amount_tendered'] : 0;
                  $change_amount = isset($_POST['change_amount']) ? (float)$_POST['change_amount'] : 0;
                  $total_amount = 0;

                  // Calculate total in USD (base currency)
                  foreach ($_SESSION['cart'] as $item) {
                        $total_amount += $item['price'] * $item['quantity'];
                  }

                  // Get exchange rate and convert amounts
                  $exchange_rate = $exchangeRate->getExchangeRate('USD', $currency_code);
                  $original_amount = $total_amount; // Store original USD amount
                  $converted_total = $exchangeRate->convertCurrency($total_amount, 'USD', $currency_code);

                  // Convert tendered and change amounts if they're in different currency
                  if ($currency_code !== 'USD') {
                        $amount_tendered = $exchangeRate->convertCurrency($amount_tendered, $currency_code, 'USD');
                        $change_amount = $exchangeRate->convertCurrency($change_amount, $currency_code, 'USD');
                  }

                  // Validate cash payment
                  if ($payment_method === 'cash') {
                        if ($amount_tendered < $total_amount) {
                              echo json_encode(['success' => false, 'message' => 'Amount tendered must be equal to or greater than total amount']);
                              exit;
                        }
                  }

                  try {
                        $pdo->beginTransaction();

                        // Create order - include user_id for logged-in customers
                        $user_id = null;
                        if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer') {
                              $user_id = $_SESSION['user_id'];
                        }

                        $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_email, total_amount, currency_code, exchange_rate, original_amount, payment_method, amount_tendered, change_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
                        $stmt->execute([$user_id, $customer_name, $customer_email, $converted_total, $currency_code, $exchange_rate, $original_amount, $payment_method, $amount_tendered, $change_amount]);
                        $order_id = $pdo->lastInsertId();

                        // Add order items and update stock
                        foreach ($_SESSION['cart'] as $product_id => $item) {
                              // Convert item price to selected currency
                              $item_price_usd = $item['price'];
                              $item_price_converted = $exchangeRate->convertCurrency($item_price_usd, 'USD', $currency_code);

                              // Add order item
                              $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, currency_code, exchange_rate) VALUES (?, ?, ?, ?, ?, ?)");
                              $stmt->execute([$order_id, $product_id, $item['quantity'], $item_price_converted, $currency_code, $exchange_rate]);

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
                                                <p class="mb-1 text-muted"><?php echo $exchangeRate->formatAmount($item['price'], 'USD'); ?> each</p>
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
                                                <h6 class="mb-1"><?php echo $exchangeRate->formatAmount($item['price'] * $item['quantity'], 'USD'); ?></h6>
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
                                    <h4 class="mb-0 text-primary">' . $exchangeRate->formatAmount($total, 'USD') . '</h4>
                              </div>
                              <button class="btn btn-success btn-lg w-100 mb-3" data-action="show-payment-modal">
                                    <i class="fas fa-credit-card me-2"></i>Process Payment
                              </button>
                              <button class="btn btn-outline-secondary w-100" data-action="clear-cart">
                                    <i class="fas fa-trash me-2"></i>Clear Cart
                              </button>';
                  }
                  $payment_html = ob_get_clean();

                  echo json_encode([
                        'success' => true,
                        'html' => $cart_html,
                        'total' => $exchangeRate->formatAmount($total, 'USD'),
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
                                                      <?php echo $exchangeRate->formatAmount($product['discount_price'] ?: $product['price'], 'USD'); ?>
                                                </span>
                                                <span class="badge bg-<?php echo $product['stock_quantity'] < 10 ? 'warning' : 'success'; ?>">
                                                      Stock: <?php echo $product['stock_quantity']; ?>
                                                </span>
                                          </div>
                                          <?php if ($product['discount_price'] && $product['discount_price'] < $product['price']): ?>
                                                <small class="text-muted text-decoration-line-through">
                                                      <?php echo $exchangeRate->formatAmount($product['price'], 'USD'); ?>
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
                                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                                <li class="breadcrumb-item active" aria-current="page">POS</li>
                                          </ol>
                                    </nav>
                              </div>
                        </div>
                  </nav>

                  <!-- Main Content Area -->
                  <div class="admin-content">
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
                                                                        <?php echo $exchangeRate->formatAmount($product['discount_price'] ?: $product['price'], 'USD'); ?>
                                                                  </span>
                                                                  <span class="badge bg-<?php echo $product['stock_quantity'] < 10 ? 'warning' : 'success'; ?>">
                                                                        Stock: <?php echo $product['stock_quantity']; ?>
                                                                  </span>
                                                            </div>
                                                            <?php if ($product['discount_price'] && $product['discount_price'] < $product['price']): ?>
                                                                  <small class="text-muted text-decoration-line-through">
                                                                        <?php echo $exchangeRate->formatAmount($product['price'], 'USD'); ?>
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
                                                                        <p class="mb-1 text-muted"><?php echo $exchangeRate->formatAmount($item['price'], 'USD'); ?> each</p>
                                                                        <div class="quantity-control">
                                                                              <button class="quantity-btn" onclick="updateQuantity(<?php echo $product_id; ?>, -1)">-</button>
                                                                              <span class="mx-2"><?php echo $item['quantity']; ?></span>
                                                                              <button class="quantity-btn" onclick="updateQuantity(<?php echo $product_id; ?>, 1)">+</button>
                                                                        </div>
                                                                  </div>
                                                                  <div class="text-end">
                                                                        <h6 class="mb-1"><?php echo $exchangeRate->formatAmount($item['price'] * $item['quantity'], 'USD'); ?></h6>
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
                                                <?php
                                                $total = 0;
                                                if (!empty($_SESSION['cart'])) {
                                                      foreach ($_SESSION['cart'] as $item) {
                                                            $total += $item['price'] * $item['quantity'];
                                                      }
                                                }
                                                echo $exchangeRate->formatAmount($total, 'USD');
                                                ?>
                                          </h4>
                                    </div>

                                    <?php if (!empty($_SESSION['cart'])): ?>
                                          <button class="btn btn-success btn-lg w-100 mb-3" data-action="show-payment-modal">
                                                <i class="fas fa-credit-card me-2"></i>Process Payment
                                          </button>
                                          <button class="btn btn-outline-secondary w-100" data-action="clear-cart">
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
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Process Payment</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="paymentForm">
                              <div class="modal-body">
                                    <div class="row">
                                          <div class="col-md-6">
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
                                                      <select class="form-select" id="payment_method" name="payment_method" required onchange="toggleCashFields()">
                                                            <option value="cash">Cash</option>
                                                            <option value="card">Credit/Debit Card</option>
                                                            <option value="mobile">Mobile Payment</option>
                                                            <option value="other">Other</option>
                                                      </select>
                                                </div>
                                                <div class="mb-3">
                                                      <label for="currency_code" class="form-label">Currency</label>
                                                      <select class="form-select" id="currency_code" name="currency_code" required onchange="convertCurrency()">
                                                            <?php foreach ($currencies as $currency): ?>
                                                                  <option value="<?php echo htmlspecialchars($currency['code']); ?>" <?php echo $currency['is_default'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($currency['code']); ?> - <?php echo htmlspecialchars($currency['name']); ?> (<?php echo htmlspecialchars($currency['symbol']); ?>)
                                                                  </option>
                                                            <?php endforeach; ?>
                                                      </select>
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="payment-summary">
                                                      <h6 class="text-primary mb-3">Payment Summary</h6>
                                                      <div class="alert alert-info">
                                                            <strong>Total Amount: <span id="modal-total-display">$0.00</span></strong>
                                                      </div>
                                                      <div class="alert alert-secondary" id="exchange-rate-info" style="display: none;">
                                                            <small>
                                                                  <i class="fas fa-exchange-alt me-1"></i>
                                                                  Exchange Rate: 1 USD = <span id="current-rate">1.000000</span> <span id="selected-currency-code">USD</span>
                                                            </small>
                                                      </div>

                                                      <!-- Cash Payment Fields -->
                                                      <div id="cash-fields" style="display: none;">
                                                            <div class="mb-3">
                                                                  <label for="amount_tendered" class="form-label">Amount Tendered</label>
                                                                  <div class="input-group">
                                                                        <span class="input-group-text" id="tendered-currency-symbol">$</span>
                                                                        <input type="number"
                                                                              class="form-control"
                                                                              id="amount_tendered"
                                                                              name="amount_tendered"
                                                                              step="0.01"
                                                                              min="0"
                                                                              placeholder="0.00"
                                                                              onchange="calculateChange()"
                                                                              onkeyup="calculateChange()">
                                                                  </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                  <label for="change_amount" class="form-label">Change</label>
                                                                  <div class="input-group">
                                                                        <span class="input-group-text" id="change-currency-symbol">$</span>
                                                                        <input type="text"
                                                                              class="form-control"
                                                                              id="change_amount"
                                                                              name="change_amount"
                                                                              readonly
                                                                              style="background-color: #f8f9fa; font-weight: bold;">
                                                                  </div>
                                                            </div>
                                                            <div class="alert alert-warning" id="insufficient-amount" style="display: none;">
                                                                  <i class="fas fa-exclamation-triangle me-2"></i>
                                                                  <small>Amount tendered is less than total amount!</small>
                                                            </div>
                                                      </div>

                                                      <!-- Quick Amount Buttons for Cash -->
                                                      <div id="quick-amounts" style="display: none;" class="mb-3">
                                                            <label class="form-label">Quick Amounts</label>
                                                            <div class="d-flex flex-wrap gap-2" id="quick-amount-buttons">
                                                                  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setQuickAmount(5)">$5</button>
                                                                  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setQuickAmount(10)">$10</button>
                                                                  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setQuickAmount(20)">$20</button>
                                                                  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setQuickAmount(50)">$50</button>
                                                                  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setQuickAmount(100)">$100</button>
                                                            </div>
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success" id="complete-payment-btn">
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
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="../admin/assets/js/admin.js"></script>

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
                                    // Refresh the page to update cart
                                    window.location.reload();
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
                  console.log('showPaymentModal called');

                  // Get cart total
                  const cartTotalElement = document.getElementById('cart-total');
                  console.log('Cart total element:', cartTotalElement);

                  if (!cartTotalElement) {
                        console.error('Cart total element not found');
                        alert('Cart total not found. Please try again.');
                        return;
                  }

                  const total = cartTotalElement.textContent;
                  console.log('Cart total text:', total);

                  if (!total || total.trim() === '') {
                        console.error('Cart total is empty');
                        alert('Cart total is empty. Please add items to cart first.');
                        return;
                  }

                  const usdAmount = parseFloat(total.replace(/[^0-9.-]+/g, ''));
                  console.log('USD amount:', usdAmount);

                  if (isNaN(usdAmount) || usdAmount <= 0) {
                        console.error('Invalid cart total:', total);
                        alert('Invalid cart total. Please try again.');
                        return;
                  }

                  console.log('Opening modal with total:', total);
                  openPaymentModalWithTotal(total);
            }

            function openPaymentModalWithTotal(totalText) {
                  const usdAmount = parseFloat(totalText.replace(/[^0-9.-]+/g, ''));

                  if (isNaN(usdAmount) || usdAmount <= 0) {
                        console.error('Invalid cart total:', totalText);
                        return;
                  }

                  // Store USD amount for currency conversion
                  window.currentUSDTotal = usdAmount;

                  // Initialize with default currency
                  convertCurrency();

                  // Reset cash fields when opening modal
                  const amountTendered = document.getElementById('amount_tendered');
                  const changeAmount = document.getElementById('change_amount');
                  const insufficientAmount = document.getElementById('insufficient-amount');

                  if (amountTendered) amountTendered.value = '';
                  if (changeAmount) changeAmount.value = '';
                  if (insufficientAmount) insufficientAmount.style.display = 'none';

                  // Show/hide cash fields based on payment method
                  toggleCashFields();

                  // Show the modal
                  const paymentModal = document.getElementById('paymentModal');
                  if (paymentModal) {
                        new bootstrap.Modal(paymentModal).show();
                  } else {
                        console.error('Payment modal not found');
                        return;
                  }

                  // Attach event listeners every time modal opens
                  if (amountTendered) {
                        amountTendered.removeEventListener('input', calculateChange); // Prevent duplicate listeners
                        amountTendered.addEventListener('input', calculateChange);
                        amountTendered.removeEventListener('change', calculateChange);
                        amountTendered.addEventListener('change', calculateChange);
                  }

                  // Calculate change immediately in case a quick amount is clicked
                  calculateChange();
            }

            function convertCurrency() {
                  const currencyCodeElement = document.getElementById('currency_code');
                  if (!currencyCodeElement) {
                        console.error('Currency code element not found');
                        return;
                  }

                  const currencyCode = currencyCodeElement.value;
                  const usdAmount = window.currentUSDTotal || 0;

                  if (usdAmount <= 0) {
                        return;
                  }

                  fetch('pos.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                              },
                              body: `action=convert_currency&amount=${usdAmount}&from_currency=USD&to_currency=${currencyCode}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    // Update total display
                                    const modalTotalDisplay = document.getElementById('modal-total-display');
                                    if (modalTotalDisplay) {
                                          modalTotalDisplay.textContent = data.formatted_amount;
                                    }

                                    // Update exchange rate info
                                    const exchangeRateInfo = document.getElementById('exchange-rate-info');
                                    const currentRate = document.getElementById('current-rate');
                                    const selectedCurrencyCode = document.getElementById('selected-currency-code');

                                    if (currencyCode !== 'USD') {
                                          if (exchangeRateInfo) exchangeRateInfo.style.display = 'block';
                                          if (currentRate) currentRate.textContent = data.rate.toFixed(6);
                                          if (selectedCurrencyCode) selectedCurrencyCode.textContent = currencyCode;
                                    } else {
                                          if (exchangeRateInfo) exchangeRateInfo.style.display = 'none';
                                    }

                                    // Update currency symbols
                                    const symbol = data.formatted_amount.replace(/[\d.,]/g, '').trim();
                                    const tenderedCurrencySymbol = document.getElementById('tendered-currency-symbol');
                                    const changeCurrencySymbol = document.getElementById('change-currency-symbol');

                                    if (tenderedCurrencySymbol) tenderedCurrencySymbol.textContent = symbol;
                                    if (changeCurrencySymbol) changeCurrencySymbol.textContent = symbol;

                                    // Update quick amount buttons
                                    updateQuickAmountButtons(currencyCode);
                              }
                        })
                        .catch(error => {
                              console.error('Error converting currency:', error);
                        });
            }

            function updateQuickAmountButtons(currencyCode) {
                  const quickAmounts = [5, 10, 20, 50, 100];
                  const container = document.getElementById('quick-amount-buttons');

                  if (!container) return;

                  container.innerHTML = '';

                  quickAmounts.forEach(amount => {
                        fetch('pos.php', {
                                    method: 'POST',
                                    headers: {
                                          'Content-Type': 'application/x-www-form-urlencoded'
                                    },
                                    body: `action=convert_currency&amount=${amount}&from_currency=USD&to_currency=${currencyCode}`
                              })
                              .then(response => response.json())
                              .then(data => {
                                    if (data.success) {
                                          const button = document.createElement('button');
                                          button.type = 'button';
                                          button.className = 'btn btn-outline-secondary btn-sm';
                                          button.onclick = () => setQuickAmount(data.converted_amount);
                                          button.textContent = data.formatted_amount;
                                          container.appendChild(button);
                                    }
                              })
                              .catch(error => {
                                    console.error('Error updating quick amount button:', error);
                              });
                  });
            }

            function toggleCashFields() {
                  const paymentMethodElement = document.getElementById('payment_method');
                  if (!paymentMethodElement) {
                        console.error('Payment method element not found');
                        return;
                  }

                  const paymentMethod = paymentMethodElement.value;
                  const cashFields = document.getElementById('cash-fields');
                  const quickAmounts = document.getElementById('quick-amounts');
                  const amountTendered = document.getElementById('amount_tendered');
                  const changeAmount = document.getElementById('change_amount');
                  const insufficientAmount = document.getElementById('insufficient-amount');

                  if (paymentMethod === 'cash') {
                        if (cashFields) cashFields.style.display = 'block';
                        if (quickAmounts) quickAmounts.style.display = 'block';
                        if (amountTendered) amountTendered.setAttribute('required', 'required');
                        if (changeAmount) {
                              changeAmount.setAttribute('readonly', 'readonly');
                              changeAmount.value = '';
                        }
                        if (insufficientAmount) insufficientAmount.style.display = 'none';
                  } else {
                        if (cashFields) cashFields.style.display = 'none';
                        if (quickAmounts) quickAmounts.style.display = 'none';
                        if (amountTendered) amountTendered.removeAttribute('required');
                        if (changeAmount) {
                              changeAmount.setAttribute('readonly', 'readonly');
                              changeAmount.value = '';
                        }
                        if (insufficientAmount) insufficientAmount.style.display = 'none';
                  }
            }

            function setQuickAmount(amount) {
                  document.getElementById('amount_tendered').value = amount;
                  calculateChange();
            }

            function calculateChange() {
                  const totalAmountDisplay = document.getElementById('modal-total-display');
                  const amountTenderedElement = document.getElementById('amount_tendered');
                  const changeAmount = document.getElementById('change_amount');
                  const insufficientAmount = document.getElementById('insufficient-amount');
                  const completeBtn = document.getElementById('complete-payment-btn');

                  if (!totalAmountDisplay || !amountTenderedElement || !changeAmount) {
                        console.error('Required elements not found');
                        return;
                  }

                  // Extract numeric value from formatted amount (remove currency symbol and commas)
                  const totalAmountText = totalAmountDisplay.textContent;
                  const totalAmount = parseFloat(totalAmountText.replace(/[^0-9.-]+/g, ''));
                  const amountTendered = parseFloat(amountTenderedElement.value.trim()) || 0;

                  console.log('Total Amount:', totalAmount);
                  console.log('Amount Tendered:', amountTendered);

                  if (isNaN(totalAmount) || isNaN(amountTendered)) {
                        changeAmount.value = '';
                        insufficientAmount.style.display = 'none';
                        if (completeBtn) completeBtn.disabled = false;
                        return;
                  }

                  const change = amountTendered - totalAmount;
                  console.log('Calculated Change:', change);

                  changeAmount.value = change >= 0 ? change.toFixed(2) : '';

                  if (change < 0) {
                        insufficientAmount.style.display = 'block';
                        if (completeBtn) completeBtn.disabled = true;
                  } else {
                        insufficientAmount.style.display = 'none';
                        if (completeBtn) completeBtn.disabled = false;
                  }
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
                                    // Update cart items
                                    document.getElementById('cart-items-container').innerHTML = data.html;

                                    // Update cart total
                                    const cartTotalElement = document.getElementById('cart-total');
                                    if (cartTotalElement) {
                                          cartTotalElement.textContent = data.total;
                                          console.log('Cart total updated:', data.total);
                                    } else {
                                          console.error('Cart total element not found!');
                                    }

                                    // Update payment section
                                    const paymentSection = document.querySelector('.payment-section');
                                    if (paymentSection && data.payment_html) {
                                          paymentSection.innerHTML = data.payment_html;
                                          console.log('Payment section updated with HTML:', data.payment_html);
                                    } else {
                                          console.error('Payment section or payment HTML not found!');
                                    }

                                    // Verify the payment button exists after update
                                    setTimeout(() => {
                                          const paymentButton = document.querySelector('button[data-action="show-payment-modal"]');
                                          if (paymentButton) {
                                                console.log('Payment button found and ready:', paymentButton);
                                          } else {
                                                console.error('Payment button not found after update!');
                                          }
                                    }, 50);
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

            // Handle scanner input enter key and payment form events
            document.addEventListener('DOMContentLoaded', function() {
                  const scannerInput = document.getElementById('scanner-input');
                  if (scannerInput) {
                        scannerInput.addEventListener('keypress', function(e) {
                              if (e.key === 'Enter') {
                                    searchByCode();
                              }
                        });
                  }

                  // Add event listeners for payment form
                  const paymentMethod = document.getElementById('payment_method');
                  const amountTendered = document.getElementById('amount_tendered');
                  const currencyCode = document.getElementById('currency_code');

                  if (paymentMethod) {
                        paymentMethod.addEventListener('change', toggleCashFields);
                  }

                  if (amountTendered) {
                        amountTendered.addEventListener('input', calculateChange);
                        amountTendered.addEventListener('change', calculateChange);
                  }

                  if (currencyCode) {
                        currencyCode.addEventListener('change', convertCurrency);
                  }
            });

            // Use event delegation for payment buttons - this works for both static and dynamic buttons
            document.addEventListener('click', function(e) {
                  // Handle payment modal button
                  if (e.target.closest('button[data-action="show-payment-modal"]')) {
                        e.preventDefault();
                        showPaymentModal();
                  }

                  // Handle clear cart button
                  if (e.target.closest('button[data-action="clear-cart"]')) {
                        e.preventDefault();
                        clearCart();
                  }
            });
      </script>
</body>

</html>