<?php
session_start();
require_once 'config/database.php';
require_once 'includes/exchange_rate.php';

// Initialize exchange rate handler
$exchangeRate = new ExchangeRate();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
      if ($_SESSION['role'] === 'admin') {
            header('Location: admin/index.php');
      } elseif ($_SESSION['role'] === 'customer') {
            // Customers should stay on the main page (root)
            // No redirect needed - they can access the main page
      } else {
            // Staff members (cashier, manager) go to user dashboard
            header('Location: user/index.php');
            exit();
      }
}

// Handle AJAX requests for cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
      header('Content-Type: application/json');

      switch ($_POST['action']) {
            case 'add_to_cart':
                  $product_id = $_POST['product_id'];
                  $quantity = (int)$_POST['quantity'];

                  if (!isset($_SESSION['guest_cart'])) {
                        $_SESSION['guest_cart'] = [];
                  }

                  // Get product details
                  $pdo = getDBConnection();
                  $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND stock_quantity > 0");
                  $stmt->execute([$product_id]);
                  $product = $stmt->fetch(PDO::FETCH_ASSOC);

                  if ($product) {
                        if (isset($_SESSION['guest_cart'][$product_id])) {
                              $_SESSION['guest_cart'][$product_id]['quantity'] += $quantity;
                        } else {
                              $_SESSION['guest_cart'][$product_id] = [
                                    'id' => $product['id'],
                                    'name' => $product['name'],
                                    'price' => $product['discount_price'] ?: $product['price'],
                                    'quantity' => $quantity,
                                    'image' => $product['image_path'],
                                    'stock_quantity' => $product['stock_quantity']
                              ];
                        }
                        echo json_encode(['success' => true, 'message' => 'Product added to cart']);
                  } else {
                        echo json_encode(['success' => false, 'message' => 'Product not found or out of stock']);
                  }
                  exit;

            case 'update_cart':
                  $product_id = $_POST['product_id'];
                  $quantity = (int)$_POST['quantity'];

                  if (isset($_SESSION['guest_cart'][$product_id])) {
                        if ($quantity <= 0) {
                              unset($_SESSION['guest_cart'][$product_id]);
                        } else {
                              $_SESSION['guest_cart'][$product_id]['quantity'] = $quantity;

                              // Ensure stock_quantity is set
                              if (!isset($_SESSION['guest_cart'][$product_id]['stock_quantity'])) {
                                    $pdo = getDBConnection();
                                    $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                                    $stmt->execute([$product_id]);
                                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($product) {
                                          $_SESSION['guest_cart'][$product_id]['stock_quantity'] = $product['stock_quantity'];
                                    } else {
                                          $_SESSION['guest_cart'][$product_id]['stock_quantity'] = 0;
                                    }
                              }
                        }
                  }

                  $total = 0;
                  foreach ($_SESSION['guest_cart'] as $item) {
                        $total += $item['price'] * $item['quantity'];
                  }

                  echo json_encode(['success' => true, 'total' => $total]);
                  exit;

            case 'remove_from_cart':
                  $product_id = $_POST['product_id'];

                  if (isset($_SESSION['guest_cart'][$product_id])) {
                        unset($_SESSION['guest_cart'][$product_id]);
                  }

                  $total = 0;
                  foreach ($_SESSION['guest_cart'] as $item) {
                        $total += $item['price'] * $item['quantity'];
                  }

                  echo json_encode(['success' => true, 'total' => $total]);
                  exit;

            case 'get_product_details':
                  $product_id = $_POST['product_id'];

                  $pdo = getDBConnection();
                  $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                  $stmt->execute([$product_id]);
                  $product = $stmt->fetch(PDO::FETCH_ASSOC);

                  if ($product) {
                        $display_price = $product['discount_price'] ? $product['discount_price'] : $product['price'];
                        $has_discount = $product['discount_price'] && $product['discount_price'] < $product['price'];
                        $discount_percent = 0;

                        if ($has_discount) {
                              $discount_percent = round((($product['price'] - $product['discount_price']) / $product['price']) * 100);
                        }

                        $product_data = [
                              'id' => $product['id'],
                              'name' => $product['name'],
                              'description' => $product['description'],
                              'price' => $product['price'],
                              'discount_price' => $product['discount_price'],
                              'display_price' => $display_price,
                              'has_discount' => $has_discount,
                              'discount_percent' => $discount_percent,
                              'stock_quantity' => $product['stock_quantity'],
                              'category' => $product['category'] ?? 'Uncategorized',
                              'type' => $product['type'] ?? 'Food',
                              'image_path' => $product['image_path'] ?: 'images/placeholder.jpg',
                              'product_code' => $product['product_code'] ?? '',
                              'barcode' => $product['barcode'] ?? '',
                              'size' => $product['size'] ?? null,
                              'color' => $product['color'] ?? null,
                              'material' => $product['material'] ?? null,
                              'weight' => $product['weight'] ?? null
                        ];

                        echo json_encode(['success' => true, 'product' => $product_data]);
                  } else {
                        echo json_encode(['success' => false, 'message' => 'Product not found']);
                  }
                  exit;

            case 'process_guest_payment':
                  if (empty($_SESSION['guest_cart'])) {
                        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
                        exit;
                  }

                  $customer_name = trim($_POST['customer_name']);
                  $customer_email = trim($_POST['customer_email']);
                  $payment_method = $_POST['payment_method'];
                  $currency_code = isset($_POST['currency_code']) ? $_POST['currency_code'] : 'USD';
                  $amount_tendered = isset($_POST['amount_tendered']) ? (float)$_POST['amount_tendered'] : 0;
                  $change_amount = isset($_POST['change_amount']) ? (float)$_POST['change_amount'] : 0;
                  $total_amount = 0;

                  // Calculate total
                  foreach ($_SESSION['guest_cart'] as $item) {
                        $total_amount += $item['price'] * $item['quantity'];
                  }

                  // Validate cash payment
                  if ($payment_method === 'cash') {
                        if ($amount_tendered < $total_amount) {
                              echo json_encode(['success' => false, 'message' => 'Amount tendered must be equal to or greater than total amount']);
                              exit;
                        }
                  }

                  try {
                        $pdo = getDBConnection();
                        $pdo->beginTransaction();

                        // Create order - include user_id if customer is logged in
                        $user_id = null;
                        if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer') {
                              $user_id = $_SESSION['user_id'];
                        }

                        // Get exchange rate for the selected currency
                        $exchange_rate = $exchangeRate->getExchangeRate('USD', $currency_code);
                        $original_amount = $total_amount; // Store original USD amount

                        // Convert total amount to selected currency
                        $converted_total = $exchangeRate->convertCurrency($total_amount, 'USD', $currency_code);

                        $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_email, total_amount, currency_code, exchange_rate, original_amount, payment_method, amount_tendered, change_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
                        $stmt->execute([$user_id, $customer_name, $customer_email, $converted_total, $currency_code, $exchange_rate, $original_amount, $payment_method, $amount_tendered, $change_amount]);
                        $order_id = $pdo->lastInsertId();

                        // Add order items and update stock
                        foreach ($_SESSION['guest_cart'] as $product_id => $item) {
                              // Convert item price to selected currency
                              $converted_price = $exchangeRate->convertCurrency($item['price'], 'USD', $currency_code);

                              // Add order item
                              $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, currency_code, exchange_rate) VALUES (?, ?, ?, ?, ?, ?)");
                              $stmt->execute([$order_id, $product_id, $item['quantity'], $converted_price, $currency_code, $exchange_rate]);

                              // Update stock
                              $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                              $stmt->execute([$item['quantity'], $product_id]);
                        }

                        $pdo->commit();

                        // Clear cart
                        $_SESSION['guest_cart'] = [];

                        echo json_encode(['success' => true, 'order_id' => $order_id, 'message' => 'Payment processed successfully']);
                  } catch (Exception $e) {
                        $pdo->rollBack();
                        echo json_encode(['success' => false, 'message' => 'Error processing payment: ' . $e->getMessage()]);
                  }
                  exit;

            case 'fetch_guest_cart':
                  ob_start();
                  if (empty($_SESSION['guest_cart'])) {
                        echo '<div class="text-center mt-4 justify-content-center align-items-center d-flex flex-column w-100">
                                <img src="images/placeholder-cart.png" alt="Empty Cart" style="width:120px;opacity:0.5;" class="mb-2">
                                <div class="text-muted">Cart is empty</div>
                              </div>';
                  } else {
                        // Ensure all cart items have stock information
                        $pdo = getDBConnection();
                        foreach ($_SESSION['guest_cart'] as $product_id => $item) {
                              // If stock_quantity is not set, fetch it from database
                              if (!isset($item['stock_quantity'])) {
                                    $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                                    $stmt->execute([$product_id]);
                                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($product) {
                                          $_SESSION['guest_cart'][$product_id]['stock_quantity'] = $product['stock_quantity'];
                                    } else {
                                          $_SESSION['guest_cart'][$product_id]['stock_quantity'] = 0;
                                    }
                              }
                        }

                        foreach ($_SESSION['guest_cart'] as $product_id => $item) {
?>
                              <div class="cart-item" id="cart-item-<?php echo $product_id; ?>" data-stock="<?php echo $item['stock_quantity']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                          <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                <p class="mb-1 text-muted">$<?php echo number_format($item['price'], 2); ?> each</p>
                                                <div class="quantity-control">
                                                      <button class="quantity-btn" onclick="updateGuestQuantity(<?php echo $product_id; ?>, -1)">-</button>
                                                      <input type="number" class="form-control mx-2 cart-qty-input"
                                                            value="<?php echo $item['quantity']; ?>"
                                                            min="1"
                                                            max="<?php echo $item['stock_quantity']; ?>"
                                                            style="width: 60px; display: inline-block; text-align: center;"
                                                            onchange="updateGuestQuantityDirect(<?php echo $product_id; ?>, this.value)"
                                                            onkeypress="return event.charCode >= 48 && event.charCode <= 57">
                                                      <button class="quantity-btn" onclick="updateGuestQuantity(<?php echo $product_id; ?>, 1)">+</button>
                                                </div>
                                                <small class="text-muted">Stock: <?php echo $item['stock_quantity']; ?> available</small>
                                          </div>
                                          <div class="text-end">
                                                <h6 class="mb-1">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></h6>
                                                <button class="btn btn-sm btn-outline-danger" onclick="removeFromGuestCart(<?php echo $product_id; ?>)">
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
                  foreach ($_SESSION['guest_cart'] as $item) {
                        $total += $item['price'] * $item['quantity'];
                  }
                  $total_quantity = 0;
                  foreach ($_SESSION['guest_cart'] as $item) {
                        $total_quantity += $item['quantity'];
                  }
                  echo json_encode([
                        'success' => true,
                        'html' => $html,
                        'total' => '$' . number_format($total, 2),
                        'total_quantity' => $total_quantity
                  ]);
                  exit;

            case 'clear_guest_cart':
                  $_SESSION['guest_cart'] = [];
                  echo json_encode(['success' => true, 'message' => 'Cart cleared successfully']);
                  exit;

            case 'get_currencies':
                  $currencies = $exchangeRate->getCurrencies();
                  echo json_encode(['success' => true, 'currencies' => $currencies]);
                  exit;

            case 'convert_currency':
                  try {
                        $amount = (float)$_POST['amount'];
                        $from_currency = $_POST['from_currency'];
                        $to_currency = $_POST['to_currency'];

                        $converted_amount = $exchangeRate->convertCurrency($amount, $from_currency, $to_currency);
                        $formatted_amount = $exchangeRate->formatAmount($converted_amount, $to_currency);

                        echo json_encode([
                              'success' => true,
                              'converted_amount' => $converted_amount,
                              'formatted_amount' => $formatted_amount,
                              'rate' => $exchangeRate->getExchangeRate($from_currency, $to_currency)
                        ]);
                  } catch (Exception $e) {
                        echo json_encode([
                              'success' => false,
                              'message' => 'Currency conversion error: ' . $e->getMessage()
                        ]);
                  }
                  exit;
      }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>POS System - Modern Point of Sale Solution</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Google Fonts -->
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <!-- Main CSS -->
      <link rel="stylesheet" href="assets/css/main.css">

      <!-- Cart CSS -->
      <link rel="stylesheet" href="assets/css/cart.css">

      <!-- Custom Styles for Product Detail Modal -->
      <style>
            /* Category Filter Buttons Styling */
            .category-filter-buttons {
                  display: flex;
                  flex-wrap: wrap;
                  justify-content: center;
                  gap: 0.5rem;
            }

            .category-filter-buttons .btn {
                  transition: all 0.3s ease;
                  border-radius: 25px;
                  font-weight: 500;
                  text-transform: uppercase;
                  font-size: 0.8rem;
                  letter-spacing: 0.5px;
            }

            .category-filter-buttons .btn:hover {
                  transform: translateY(-2px);
                  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            }

            .category-filter-buttons .btn:active {
                  transform: translateY(0);
            }

            /* Active filter button styling */
            .category-filter-buttons .btn.active {
                  transform: translateY(-2px);
                  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            }

            /* Responsive adjustments */
            @media (max-width: 768px) {
                  .category-filter-buttons {
                        flex-direction: column;
                        align-items: center;
                  }

                  .category-filter-buttons .btn {
                        width: 200px;
                        margin-bottom: 0.5rem;
                  }
            }
      </style>

</head>

<body>
      <!-- Cart Overlay -->
      <div class="cart-overlay" id="cartOverlay" onclick="closeCart()"></div>

      <!-- Cart Sidebar -->
      <div class="cart-sidebar" id="cartSidebar">
            <div class="cart-header">
                  <h5 class="mb-0">
                        <i class="fas fa-shopping-cart me-2"></i>Shopping Cart
                  </h5>
                  <button type="button" class="btn-close" onclick="closeCart()"></button>
            </div>

            <div class="cart-items" id="cartItemsContainer">
                  <div class="text-center mt-4 justify-content-center align-items-center d-flex flex-column w-100">
                        <img src="images/placeholder-cart.png" alt="Empty Cart" style="width:120px;opacity:0.5;" class="mb-2">
                        <div class="text-muted">Cart is empty</div>
                  </div>
            </div>

            <div class="cart-footer">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Total:</h5>
                        <h4 class="mb-0 text-primary" id="cartTotal">$0.00</h4>
                  </div>

                  <button class="btn btn-success btn-lg w-100 mb-3" onclick="showPaymentModal()" id="checkoutBtn" style="display: none;">
                        <i class="fas fa-credit-card me-2"></i>Checkout
                  </button>
                  <button class="btn btn-outline-secondary w-100" onclick="clearGuestCart()" id="clearCartBtn" style="display: none;">
                        <i class="fas fa-trash me-2"></i>Clear Cart
                  </button>
            </div>
      </div>

      <!-- Navigation -->
      <nav class="navbar navbar-expand-lg navbar-light fixed-top">
            <div class="container">
                  <a class="navbar-brand" href="#">
                        <i class="fas fa-store text-primary me-2"></i>CH-FASHION
                  </a>

                  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                        <span class="navbar-toggler-icon"></span>
                  </button>

                  <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav ms-auto">
                              <li class="nav-item">
                                    <a class="nav-link" href="#features">Features</a>
                              </li>
                              <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                          Products
                                    </a>
                                    <ul class="dropdown-menu">
                                          <li><a class="dropdown-item" href="#products" onclick="filterByCategory('All Products')">
                                                      <i class="fas fa-th-large me-2"></i>All Products
                                                </a></li>
                                          <li>
                                                <hr class="dropdown-divider">
                                          </li>
                                          <li><a class="dropdown-item" href="product-list.php?category=Food">
                                                      <i class="fas fa-utensils me-2"></i>Food
                                                </a></li>
                                          <li class="dropdown-divider"></li>
                                          <li><a class="dropdown-item" href="product-list.php?category=Clothes">
                                                      <i class="fas fa-tshirt me-2"></i>All Clothing
                                                </a></li>
                                    </ul>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="#about">About</a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="#contact">Contact</a>
                              </li>
                              <li class="nav-item position-relative">
                                    <a class="nav-link" href="#" onclick="openCart(); return false;">
                                          <i class="fas fa-shopping-cart"></i>
                                          <span class="cart-badge" id="cartBadge" style="display: none;">0</span>
                                    </a>
                              </li>
                              <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>
                                    <li class="nav-item dropdown">
                                          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-user-circle me-2"></i><?php echo $_SESSION['username']; ?>
                                                <span class="badge bg-success ms-1">Customer</span>
                                          </a>
                                          <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="user/customer_dashboard.php">
                                                            <i class="fas fa-tachometer-alt me-2"></i>My Dashboard
                                                      </a></li>
                                                <li><a class="dropdown-item" href="user/customer_profile.php">
                                                            <i class="fas fa-user me-2"></i>Profile
                                                      </a></li>
                                                <li><a class="dropdown-item" href="user/customer_settings.php">
                                                            <i class="fas fa-cog me-2"></i>Settings
                                                      </a></li>
                                                <li>
                                                      <hr class="dropdown-divider">
                                                </li>
                                                <li><a class="dropdown-item" href="logout.php">
                                                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                                                      </a></li>
                                          </ul>
                                    </li>
                              <?php else: ?>
                                    <li class="nav-item">
                                          <a class="nav-link btn btn-outline-primary btn-custom ms-2" href="login.php">
                                                <i class="fas fa-sign-in-alt me-2"></i>Login
                                          </a>
                                    </li>
                              <?php endif; ?>
                              <?php if (!isset($_SESSION['user_id'])): ?>
                                    <li class="nav-item dropdown">
                                          <a class="nav-link btn btn-primary btn-custom ms-2" href="register.php">
                                                <i class="fas fa-user-plus me-2"></i>Register
                                          </a>
                                          <!-- <a class="nav-link btn btn-primary btn-custom ms-2 dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-user-plus me-2"></i>Register
                                          </a> -->
                                          <!-- <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="customer_register.php">
                                                            <i class="fas fa-user me-2"></i>Customer Account
                                                      </a></li>
                                                <li>
                                                      <a class="dropdown-item" href="register.php">
                                                            <i class="fas fa-user-tie me-2"></i>Staff Account
                                                      </a>
                                                </li>
                                          </ul> -->
                                    </li>
                              <?php endif; ?>
                        </ul>
                  </div>
            </div>
      </nav>

      <!-- Hero Section -->
      <section class="hero-section">
            <div class="floating-shapes">
                  <div class="shape"></div>
                  <div class="shape"></div>
                  <div class="shape"></div>
            </div>
            <div class="container">
                  <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>
                        <!-- Customer Welcome Section -->
                        <div class="row align-items-center hero-content">
                              <div class="col-lg-6">
                                    <h1 class="display-4 fw-bold mb-4">
                                          Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                                    </h1>
                                    <p class="lead mb-4">
                                          Continue shopping or check your order history. We're here to serve you!
                                    </p>
                                    <div class="d-flex flex-wrap gap-3">
                                          <a href="#products" class="btn btn-light btn-custom">
                                                <i class="fas fa-shopping-cart me-2"></i>Shop Now
                                          </a>
                                          <a href="user/customer_dashboard.php" class="btn btn-outline-light btn-custom">
                                                <i class="fas fa-tachometer-alt me-2"></i>My Dashboard
                                          </a>
                                    </div>
                              </div>
                              <div class="col-lg-6 text-center">
                                    <div class="position-relative">
                                          <i class="fas fa-user-circle" style="font-size: 15rem; opacity: 0.3;"></i>
                                    </div>
                              </div>
                        </div>
                  <?php else: ?>
                        <!-- Default Hero Section -->
                        <div class="row align-items-center hero-content">
                              <div class="col-lg-6">
                                    <h1 class="display-4 fw-bold mb-4">
                                          Modern Point of Sale System
                                    </h1>
                                    <p class="lead mb-4">
                                          Streamline your business operations with our comprehensive POS solution.
                                          Manage sales, inventory, and customers with ease.
                                    </p>
                                    <div class="d-flex flex-wrap gap-3">
                                          <a href="register.php" class="btn btn-light btn-custom">
                                                <i class="fas fa-rocket me-2"></i>Get Started
                                          </a>
                                          <a href="#features" class="btn btn-outline-light btn-custom">
                                                <i class="fas fa-play me-2"></i>Learn More
                                          </a>
                                    </div>
                              </div>
                              <div class="col-lg-6 text-center">
                                    <div class="position-relative">
                                          <i class="fas fa-cash-register" style="font-size: 15rem; opacity: 0.3;"></i>
                                    </div>
                              </div>
                        </div>
                  <?php endif; ?>
            </div>
      </section>

      <!-- Features Section -->
      <section id="features" class="py-5">
            <div class="container">
                  <div class="row text-center mb-5">
                        <div class="col-lg-8 mx-auto">
                              <h2 class="display-5 fw-bold mb-3">Why Choose Our POS System?</h2>
                              <p class="lead text-muted">
                                    Powerful features designed to help your business grow and succeed
                              </p>
                        </div>
                  </div>

                  <div class="row g-4">
                        <div class="col-lg-4 col-md-6 powerful-features">
                              <div class="card feature-card">
                                    <div class="card-body text-center">
                                          <div class="feature-icon bg-primary text-white">
                                                <i class="fas fa-cash-register"></i>
                                          </div>
                                          <h5 class="card-title">Easy Sales Management</h5>
                                          <p class="card-text">
                                                Process transactions quickly and efficiently with our intuitive interface.
                                                Support for multiple payment methods and receipt printing.
                                          </p>
                                    </div>
                              </div>
                        </div>

                        <div class="col-lg-4 col-md-6 powerful-features">
                              <div class="card feature-card">
                                    <div class="card-body text-center">
                                          <div class="feature-icon bg-success text-white">
                                                <i class="fas fa-box"></i>
                                          </div>
                                          <h5 class="card-title">Inventory Control</h5>
                                          <p class="card-text">
                                                Keep track of your stock levels in real-time. Get alerts for low inventory
                                                and manage product categories efficiently.
                                          </p>
                                    </div>
                              </div>
                        </div>

                        <div class="col-lg-4 col-md-6 powerful-features">
                              <div class="card feature-card">
                                    <div class="card-body text-center">
                                          <div class="feature-icon bg-info text-white">
                                                <i class="fas fa-chart-line"></i>
                                          </div>
                                          <h5 class="card-title">Analytics & Reports</h5>
                                          <p class="card-text">
                                                Generate detailed reports on sales, inventory, and customer behavior.
                                                Make data-driven decisions to grow your business.
                                          </p>
                                    </div>
                              </div>
                        </div>

                        <div class="col-lg-4 col-md-6 powerful-features">
                              <div class="card feature-card">
                                    <div class="card-body text-center">
                                          <div class="feature-icon bg-warning text-white">
                                                <i class="fas fa-users"></i>
                                          </div>
                                          <h5 class="card-title">User Management</h5>
                                          <p class="card-text">
                                                Manage multiple users with different access levels. Secure authentication
                                                and role-based permissions for your team.
                                          </p>
                                    </div>
                              </div>
                        </div>

                        <div class="col-lg-4 col-md-6 powerful-features">
                              <div class="card feature-card">
                                    <div class="card-body text-center">
                                          <div class="feature-icon bg-danger text-white">
                                                <i class="fas fa-mobile-alt"></i>
                                          </div>
                                          <h5 class="card-title">Mobile Responsive</h5>
                                          <p class="card-text">
                                                Access your POS system from any device. Works perfectly on desktop,
                                                tablet, and mobile devices.
                                          </p>
                                    </div>
                              </div>
                        </div>

                        <div class="col-lg-4 col-md-6 powerful-features">
                              <div class="card feature-card">
                                    <div class="card-body text-center">
                                          <div class="feature-icon bg-secondary text-white">
                                                <i class="fas fa-shield-alt"></i>
                                          </div>
                                          <h5 class="card-title">Secure & Reliable</h5>
                                          <p class="card-text">
                                                Enterprise-grade security with data encryption and regular backups.
                                                Your business data is safe with us.
                                          </p>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </section>

      <!-- Products Section -->
      <section id="products" class="py-5 bg-light">
            <div class="container">
                  <div class="row text-center mb-5">
                        <div class="col-lg-8 mx-auto">
                              <h2 class="display-5 fw-bold mb-3">Featured Products</h2>
                              <p class="lead text-muted">
                                    Discover our wide range of quality products available in our POS system
                              </p>
                        </div>
                  </div>

                  <!-- Category Filter Buttons -->
                  <div class="row mb-4">
                        <div class="col-12 text-center">
                              <div class="category-filter-buttons">
                                    <button class="btn btn-outline-primary btn-sm me-2 mb-2" onclick="filterByCategory('All Products')">
                                          <i class="fas fa-th-large me-1"></i>All Products
                                    </button>
                                    <button class="btn btn-outline-success btn-sm me-2 mb-2" onclick="filterByCategory('Food')">
                                          <i class="fas fa-utensils me-1"></i>Food
                                    </button>
                                    <button class="btn btn-outline-info btn-sm me-2 mb-2" onclick="filterByCategory('Clothes')">
                                          <i class="fas fa-tshirt me-1"></i>All Clothing
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm me-2 mb-2" onclick="filterByCategory('Apparel')">
                                          <i class="fas fa-tshirt me-1"></i>Apparel
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm me-2 mb-2" onclick="filterByCategory('Clothing')">
                                          <i class="fas fa-tshirt me-1"></i>Clothing
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm me-2 mb-2" onclick="filterByCategory('Fashion')">
                                          <i class="fas fa-tshirt me-1"></i>Fashion
                                    </button>
                                    <button class="btn btn-outline-dark btn-sm me-2 mb-2" onclick="filterByCategory('Sports Wear')">
                                          <i class="fas fa-running me-1"></i>Sports Wear
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm me-2 mb-2" onclick="filterByCategory('Uniforms')">
                                          <i class="fas fa-user-tie me-1"></i>Uniforms
                                    </button>
                              </div>
                        </div>
                  </div>

                  <div class="row g-4">
                        <?php
                        // Get featured products from database
                        $pdo = getDBConnection();
                        $stmt = $pdo->prepare("SELECT *, COALESCE(type, 'Food') as type FROM products WHERE stock_quantity > 0 ORDER BY RAND() LIMIT 8");
                        $stmt->execute();
                        $featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($featured_products as $product):
                              $img_path = !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : 'images/placeholder.jpg';
                              $display_price = $product['discount_price'] ? $product['discount_price'] : $product['price'];
                              $has_discount = $product['discount_price'] && $product['discount_price'] < $product['price'];
                        ?>
                              <div class="col-lg-3 col-md-6 product-item-container">
                                    <div class="card product-card h-100 position-relative">
                                          <div class="quantity-overlay" id="quantity-overlay-<?php echo $product['id']; ?>" style="display: none;">
                                                <div class="quantity-controls">
                                                      <button class="quantity-btn" onclick="event.stopPropagation(); updateProductQuantity(<?php echo $product['id']; ?>, -1)">-</button>
                                                      <span class="quantity-display" id="quantity-<?php echo $product['id']; ?>">1</span>
                                                      <button class="quantity-btn" onclick="event.stopPropagation(); updateProductQuantity(<?php echo $product['id']; ?>, 1)">+</button>
                                                </div>
                                                <div class="quantity-actions">
                                                      <button class="btn btn-sm btn-success" onclick="event.stopPropagation(); addToGuestCartWithQuantity(<?php echo $product['id']; ?>)">
                                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                                      </button>
                                                      <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); hideQuantityOverlay(<?php echo $product['id']; ?>)">
                                                            Cancel
                                                      </button>
                                                </div>
                                          </div>
                                          <div class="product-actions">
                                                <button class="add-to-cart-btn" onclick="event.stopPropagation(); showQuantityOverlay(<?php echo $product['id']; ?>)">
                                                      <i class="fas fa-plus"></i>
                                                </button>
                                                <button class="view-details-btn" onclick="event.stopPropagation(); showProductDetailModal(<?php echo $product['id']; ?>)">
                                                      <i class="fas fa-info-circle"></i>
                                                </button>
                                          </div>
                                          <div class="product-card-click-area" onclick="handleProductCardClick(<?php echo $product['id']; ?>)">
                                                <div class="product-image-container">
                                                      <img src="<?php echo $img_path; ?>"
                                                            class="card-img-top product-image"
                                                            alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                      <?php if ($has_discount): ?>
                                                            <div class="discount-badge">
                                                                  <span class="badge bg-danger">
                                                                        <?php
                                                                        $discount_percent = round((($product['price'] - $product['discount_price']) / $product['price']) * 100);
                                                                        echo $discount_percent . '% OFF';
                                                                        ?>
                                                                  </span>
                                                            </div>
                                                      <?php endif; ?>
                                                </div>
                                                <div class="card-body d-flex flex-column">
                                                      <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                      <p class="card-text text-muted small">
                                                            <?php echo htmlspecialchars(substr($product['description'], 0, 60)) . (strlen($product['description']) > 60 ? '...' : ''); ?>
                                                      </p>
                                                      <div class="mt-auto">
                                                            <div class="price-container">
                                                                  <?php if ($has_discount): ?>
                                                                        <span class="original-price text-muted text-decoration-line-through">
                                                                              $<?php echo number_format($product['price'], 2); ?>
                                                                        </span>
                                                                  <?php endif; ?>
                                                                  <span class="current-price fw-bold text-primary">
                                                                        $<?php echo number_format($display_price, 2); ?>
                                                                  </span>
                                                            </div>
                                                            <div class="stock-info small text-muted mt-1">
                                                                  <i class="fas fa-box me-1"></i>
                                                                  <?php echo $product['stock_quantity']; ?> in stock
                                                            </div>
                                                            <div class="category-badge mt-2">
                                                                  <span class="badge bg-light text-dark">
                                                                        <?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?>
                                                                  </span>
                                                                  <?php if (isset($product['type'])): ?>
                                                                        <span class="badge bg-info text-white ms-1">
                                                                              <?php echo htmlspecialchars($product['type']); ?>
                                                                        </span>
                                                                  <?php endif; ?>
                                                            </div>
                                                            <?php if (isset($product['type']) && $product['type'] === 'Clothes'): ?>
                                                                  <div class="clothing-attributes mt-2">
                                                                        <?php if (!empty($product['size'])): ?>
                                                                              <span class="badge bg-warning text-dark me-1">
                                                                                    <i class="fas fa-ruler me-1"></i><?php echo htmlspecialchars($product['size']); ?>
                                                                              </span>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($product['color'])): ?>
                                                                              <span class="badge bg-success text-white me-1">
                                                                                    <i class="fas fa-palette me-1"></i><?php echo htmlspecialchars($product['color']); ?>
                                                                              </span>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($product['material'])): ?>
                                                                              <span class="badge bg-secondary text-white me-1">
                                                                                    <i class="fas fa-tshirt me-1"></i><?php echo htmlspecialchars($product['material']); ?>
                                                                              </span>
                                                                        <?php endif; ?>
                                                                  </div>
                                                            <?php endif; ?>
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                        <?php endforeach; ?>
                  </div>

                  <div class="row mt-5">
                        <div class="col-12 text-center">
                              <a href="login.php" class="btn btn-primary btn-custom">
                                    <i class="fas fa-store me-2"></i>View All Products
                              </a>
                        </div>
                  </div>
            </div>
      </section>

      <!-- Online Order Section -->
      <section id="online-order" class="py-5">
            <div class="container">
                  <div class="row justify-content-center">
                        <div class="col-lg-8">
                              <div class="card shadow-lg border-0 online-order-card text-center p-4">
                                    <div class="card-body">
                                          <div class="mb-3">
                                                <i class="fas fa-shopping-cart fa-3x text-primary"></i>
                                          </div>
                                          <h2 class="fw-bold mb-3">Order Online</h2>
                                          <p class="lead text-muted mb-4">
                                                Experience the convenience of placing your orders online! Browse our products, add to cart, and complete your purchase from anywhere, anytime.
                                          </p>
                                          <div class="d-flex flex-wrap gap-3 justify-content-center">
                                                <button onclick="openCart()" class="btn btn-success btn-lg btn-custom">
                                                      <i class="fas fa-shopping-cart me-2"></i>View Cart
                                                </button>
                                                <a href="#products" class="btn btn-outline-primary btn-lg btn-custom">
                                                      <i class="fas fa-basket-shopping me-2"></i>Browse Products
                                                </a>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </section>

      <!-- Stats Section -->
      <section class="stats-section">
            <div class="container">
                  <div class="row">
                        <div class="col-lg-3 col-md-6">
                              <div class="stat-card">
                                    <div class="stat-number">1000+</div>
                                    <p class="text-muted">Happy Customers</p>
                              </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                              <div class="stat-card">
                                    <div class="stat-number">50K+</div>
                                    <p class="text-muted">Transactions Processed</p>
                              </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                              <div class="stat-card">
                                    <div class="stat-number">99.9%</div>
                                    <p class="text-muted">Uptime Guarantee</p>
                              </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                              <div class="stat-card">
                                    <div class="stat-number">24/7</div>
                                    <p class="text-muted">Customer Support</p>
                              </div>
                        </div>
                  </div>
            </div>
      </section>

      <!-- CTA Section -->
      <section class="cta-section">
            <div class="container text-center">
                  <h2 class="display-5 fw-bold mb-4">Ready to Get Started?</h2>
                  <p class="lead mb-4">
                        Join thousands of businesses that trust our POS system to manage their operations.
                  </p>
                  <a href="register.php" class="btn btn-light btn-custom btn-lg">
                        <i class="fas fa-rocket me-2"></i>Start Your Free Trial
                  </a>
            </div>
      </section>

      <!-- Footer -->
      <footer class="footer">
            <div class="container">
                  <div class="row">
                        <div class="col-lg-4 mb-4">
                              <h5><i class="fas fa-store me-2"></i>POS System</h5>
                              <p class="text-muted">
                                    Modern point of sale solution designed to help businesses grow and succeed.
                                    Simple, powerful, and reliable.
                              </p>
                              <div class="social-links">
                                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                                    <a href="#"><i class="fab fa-twitter"></i></a>
                                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                                    <a href="#"><i class="fab fa-instagram"></i></a>
                              </div>
                        </div>

                        <div class="col-lg-2 col-md-6 mb-4">
                              <h5>Features</h5>
                              <ul class="list-unstyled">
                                    <li><a href="#">Sales Management</a></li>
                                    <li><a href="#">Inventory Control</a></li>
                                    <li><a href="#">Analytics</a></li>
                                    <li><a href="#">User Management</a></li>
                              </ul>
                        </div>

                        <div class="col-lg-2 col-md-6 mb-4">
                              <h5>Support</h5>
                              <ul class="list-unstyled">
                                    <li><a href="#">Help Center</a></li>
                                    <li><a href="#">Documentation</a></li>
                                    <li><a href="#">Contact Us</a></li>
                                    <li><a href="#">Status</a></li>
                              </ul>
                        </div>

                        <div class="col-lg-2 col-md-6 mb-4">
                              <h5>Company</h5>
                              <ul class="list-unstyled">
                                    <li><a href="#">About Us</a></li>
                                    <li><a href="#">Careers</a></li>
                                    <li><a href="#">Blog</a></li>
                                    <li><a href="#">Press</a></li>
                              </ul>
                        </div>

                        <div class="col-lg-2 col-md-6 mb-4">
                              <h5>Legal</h5>
                              <ul class="list-unstyled">
                                    <li><a href="#">Privacy Policy</a></li>
                                    <li><a href="#">Terms of Service</a></li>
                                    <li><a href="#">Cookie Policy</a></li>
                                    <li><a href="#">GDPR</a></li>
                              </ul>
                        </div>
                  </div>

                  <hr class="my-4">

                  <div class="row align-items-center">
                        <div class="col-md-6">
                              <p class="mb-0 text-muted">
                                    &copy; 2024 POS System. All rights reserved.
                              </p>
                        </div>
                        <div class="col-md-6 text-md-end">
                              <p class="mb-0 text-muted">
                                    Made with <i class="fas fa-heart text-danger"></i> for businesses
                              </p>
                        </div>
                  </div>
            </div>
      </footer>

      <!-- Payment Modal -->
      <div class="modal fade" id="paymentModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Complete Your Order</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="paymentForm">
                              <div class="modal-body">
                                    <div class="row">
                                          <div class="col-md-6">
                                                <?php if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer'): ?>
                                                      <!-- Customer Login Option - Only show for non-logged-in users -->
                                                      <div class="mb-3">
                                                            <div class="alert alert-info">
                                                                  <i class="fas fa-info-circle me-2"></i>
                                                                  <strong>Optional:</strong> Login to save your order history and get faster checkout next time.
                                                            </div>
                                                            <div class="d-flex gap-2 mb-3">
                                                                  <button type="button" class="btn btn-outline-primary btn-sm" onclick="showCustomerLogin()">
                                                                        <i class="fas fa-sign-in-alt me-2"></i>Login as Customer
                                                                  </button>
                                                                  <button type="button" class="btn btn-outline-success btn-sm" onclick="showCustomerRegister()">
                                                                        <i class="fas fa-user-plus me-2"></i>Register as Customer
                                                                  </button>
                                                            </div>
                                                            <div class="text-center">
                                                                  <small class="text-muted">
                                                                        <i class="fas fa-arrow-down me-1"></i>Or continue as guest below
                                                                  </small>
                                                            </div>
                                                      </div>
                                                <?php else: ?>
                                                      <!-- Customer Info Display - Show for logged-in customers -->
                                                      <div class="mb-3">
                                                            <div class="alert alert-success">
                                                                  <i class="fas fa-check-circle me-2"></i>
                                                                  <strong>Welcome back!</strong> Your information will be automatically filled.
                                                            </div>
                                                      </div>
                                                <?php endif; ?>

                                                <div class="mb-3">
                                                      <label for="customer_name" class="form-label">Full Name *</label>
                                                      <input type="text" class="form-control" id="customer_name" name="customer_name"
                                                            value="<?php
                                                                        if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer') {
                                                                              // Try to get full_name from database
                                                                              try {
                                                                                    $pdo = getDBConnection();
                                                                                    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                                                                                    $stmt->execute([$_SESSION['user_id']]);
                                                                                    $full_name = $stmt->fetchColumn();
                                                                                    // Use full_name if it exists and is not empty, otherwise use username
                                                                                    echo htmlspecialchars(trim($full_name) ?: $_SESSION['username']);
                                                                              } catch (Exception $e) {
                                                                                    echo htmlspecialchars($_SESSION['username']);
                                                                              }
                                                                        }
                                                                        ?>"
                                                            <?php echo isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer' ? 'readonly' : ''; ?>
                                                            required>
                                                      <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>
                                                            <small class="text-muted">
                                                                  <i class="fas fa-user-check me-1"></i>Your name from your account
                                                            </small>
                                                      <?php endif; ?>
                                                </div>
                                                <div class="mb-3">
                                                      <label for="customer_email" class="form-label">Email Address *</label>
                                                      <input type="email" class="form-control" id="customer_email" name="customer_email"
                                                            value="<?php echo isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer' ? htmlspecialchars($_SESSION['email']) : ''; ?>"
                                                            <?php echo isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer' ? 'readonly' : ''; ?>
                                                            required>
                                                      <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>
                                                            <small class="text-muted">
                                                                  <i class="fas fa-envelope-check me-1"></i>Your email from your account
                                                            </small>
                                                      <?php endif; ?>
                                                </div>
                                                <div class="mb-3">
                                                      <label for="currency_code" class="form-label">Currency *</label>
                                                      <select class="form-select" id="currency_code" name="currency_code" required onchange="updateCurrencyDisplay()">
                                                            <option value="">Select currency</option>
                                                            <?php
                                                            $currencies = $exchangeRate->getCurrencies();
                                                            foreach ($currencies as $currency) {
                                                                  $selected = $currency['is_default'] ? 'selected' : '';
                                                                  echo "<option value='{$currency['code']}' {$selected}>{$currency['name']} ({$currency['symbol']})</option>";
                                                            }
                                                            ?>
                                                      </select>
                                                </div>
                                                <div class="mb-3">
                                                      <label for="payment_method" class="form-label">Payment Method *</label>
                                                      <select class="form-select" id="payment_method" name="payment_method" required onchange="toggleGuestCashFields()">
                                                            <option value="">Select payment method</option>
                                                            <option value="cash">Cash on Delivery</option>
                                                            <option value="card">Credit/Debit Card</option>
                                                            <option value="mobile">Mobile Payment</option>
                                                            <option value="bank">Bank Transfer</option>
                                                      </select>
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="payment-summary">
                                                      <h6 class="text-primary mb-3">Payment Summary</h6>
                                                      <div class="alert alert-info">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                  <strong>Total Amount:</strong>
                                                                  <span id="modal-total-display">$0.00</span>
                                                            </div>
                                                            <div class="mt-2">
                                                                  <small class="text-muted">
                                                                        <span id="exchange-rate-info" style="display: none;">
                                                                              Exchange Rate: <span id="current-rate">1.00</span>
                                                                        </span>
                                                                  </small>
                                                            </div>
                                                            <!-- Hidden element to store USD total for calculations -->
                                                            <span id="modal-total" style="display: none;">0.00</span>
                                                      </div>

                                                      <!-- Cash Payment Fields -->
                                                      <div id="guest-cash-fields" style="display: none;">
                                                            <div class="mb-3">
                                                                  <label for="guest_amount_tendered" class="form-label">Amount Tendered</label>
                                                                  <div class="input-group">
                                                                        <span class="input-group-text" id="tendered-currency-symbol">$</span>
                                                                        <input type="number"
                                                                              class="form-control"
                                                                              id="guest_amount_tendered"
                                                                              name="amount_tendered"
                                                                              step="0.01"
                                                                              min="0"
                                                                              placeholder="0.00"
                                                                              onchange="calculateGuestChange()"
                                                                              onkeyup="calculateGuestChange()">
                                                                  </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                  <label for="guest_change_amount" class="form-label">Change</label>
                                                                  <div class="input-group">
                                                                        <span class="input-group-text" id="change-currency-symbol">$</span>
                                                                        <input type="text"
                                                                              class="form-control"
                                                                              id="guest_change_amount"
                                                                              name="change_amount"
                                                                              readonly
                                                                              style="background-color: #f8f9fa; font-weight: bold;">
                                                                  </div>
                                                            </div>
                                                            <div class="alert alert-warning" id="guest-insufficient-amount" style="display: none;">
                                                                  <i class="fas fa-exclamation-triangle me-2"></i>
                                                                  <small>Amount tendered is less than total amount!</small>
                                                            </div>
                                                      </div>

                                                      <!-- Quick Amount Buttons for Cash -->
                                                      <div id="guest-quick-amounts" style="display: none;" class="mb-3">
                                                            <label class="form-label">Quick Amounts</label>
                                                            <div class="d-flex flex-wrap gap-2">
                                                                  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setGuestQuickAmount(5)"><span id="quick-5">$5</span></button>
                                                                  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setGuestQuickAmount(10)"><span id="quick-10">$10</span></button>
                                                                  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setGuestQuickAmount(20)"><span id="quick-20">$20</span></button>
                                                                  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setGuestQuickAmount(50)"><span id="quick-50">$50</span></button>
                                                                  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setGuestQuickAmount(100)"><span id="quick-100">$100</span></button>
                                                            </div>
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                                    <div class="alert alert-warning">
                                          <i class="fas fa-info-circle me-2"></i>
                                          <small>Please note: This is a demo payment system. No actual payment will be processed.</small>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success" id="guest-complete-payment-btn">
                                          <i class="fas fa-check me-2"></i>Complete Order
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
                                    <i class="fas fa-check-circle me-2"></i>Order Successful
                              </h5>
                              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                              <h4 class="text-success mb-3">Thank You!</h4>
                              <p>Your order has been placed successfully.</p>
                              <p>Order ID: <strong id="order-id"></strong></p>
                              <p>We'll send you an email confirmation shortly.</p>
                        </div>
                        <div class="modal-footer">
                              <button type="button" class="btn btn-success" onclick="printReceipt()">
                                    <i class="fas fa-print me-2"></i>Print Receipt
                              </button>
                              <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Continue Shopping</button>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Product Detail Modal -->
      <div class="modal fade" id="productDetailModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">
                                    <i class="fas fa-info-circle me-2"></i>Product Details
                              </h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                              <div class="row">
                                    <div class="col-md-6">
                                          <div class="product-image-container mb-3">
                                                <img id="modal-product-image" src="" alt="Product Image" class="img-fluid rounded" style="max-height: 300px; width: 100%; object-fit: cover;">
                                                <div id="modal-discount-badge" class="discount-badge" style="display: none;">
                                                      <span class="badge bg-danger fs-6">
                                                            <span id="modal-discount-percent"></span> OFF
                                                      </span>
                                                </div>
                                          </div>
                                    </div>
                                    <div class="col-md-6">
                                          <h4 id="modal-product-name" class="mb-3"></h4>
                                          <p id="modal-product-description" class="text-muted mb-3"></p>

                                          <div class="product-info mb-3">
                                                <div class="row">
                                                      <div class="col-6">
                                                            <strong>Category:</strong>
                                                            <span id="modal-product-category" class="ms-2"></span>
                                                      </div>
                                                      <div class="col-6">
                                                            <strong>Stock:</strong>
                                                            <span id="modal-product-stock" class="ms-2"></span>
                                                      </div>
                                                </div>
                                                <div class="row mt-2">
                                                      <div class="col-6">
                                                            <strong>Product Code:</strong>
                                                            <span id="modal-product-code" class="ms-2"></span>
                                                      </div>
                                                      <div class="col-6">
                                                            <strong>Barcode:</strong>
                                                            <span id="modal-product-barcode" class="ms-2"></span>
                                                      </div>
                                                </div>
                                                <!-- Clothing-specific attributes -->
                                                <div id="modal-clothing-attributes" class="row mt-2" style="display: none;">
                                                      <div class="col-12">
                                                            <strong>Clothing Details:</strong>
                                                            <div class="mt-2">
                                                                  <span id="modal-product-size" class="badge bg-warning text-dark me-2" style="display: none;">
                                                                        <i class="fas fa-ruler me-1"></i>Size: <span class="size-value"></span>
                                                                  </span>
                                                                  <span id="modal-product-color" class="badge bg-success text-white me-2" style="display: none;">
                                                                        <i class="fas fa-palette me-1"></i>Color: <span class="color-value"></span>
                                                                  </span>
                                                                  <span id="modal-product-material" class="badge bg-secondary text-white me-2" style="display: none;">
                                                                        <i class="fas fa-tshirt me-1"></i>Material: <span class="material-value"></span>
                                                                  </span>
                                                                  <span id="modal-product-weight" class="badge bg-info text-white me-2" style="display: none;">
                                                                        <i class="fas fa-weight-hanging me-1"></i>Weight: <span class="weight-value"></span>g
                                                                  </span>
                                                            </div>
                                                      </div>
                                                </div>
                                          </div>

                                          <div class="price-section mb-4">
                                                <div class="price-container">
                                                      <span id="modal-original-price" class="original-price text-muted text-decoration-line-through" style="display: none;"></span>
                                                      <span id="modal-current-price" class="current-price fw-bold text-primary fs-4"></span>
                                                </div>
                                          </div>

                                          <div class="quantity-section mb-4">
                                                <label class="form-label"><strong>Quantity:</strong></label>
                                                <div class="d-flex align-items-center">
                                                      <button class="btn btn-outline-secondary" onclick="updateModalQuantity(-1)">
                                                            <i class="fas fa-minus"></i>
                                                      </button>
                                                      <input type="number" id="modal-quantity" class="form-control mx-2" value="1" min="1" style="width: 80px; text-align: center;">
                                                      <button class="btn btn-outline-secondary" onclick="updateModalQuantity(1)">
                                                            <i class="fas fa-plus"></i>
                                                      </button>
                                                </div>
                                          </div>

                                          <div class="action-buttons">
                                                <button type="button" class="btn btn-success btn-lg me-2" onclick="addToCartFromModal()">
                                                      <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                                      <i class="fas fa-times me-2"></i>Close
                                                </button>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

      <script>
            let currentOrderId = null;

            // Cart Functions
            function openCart() {
                  console.log('Opening cart...'); // Debug log

                  const cartSidebar = document.getElementById('cartSidebar');
                  const cartOverlay = document.getElementById('cartOverlay');

                  if (!cartSidebar || !cartOverlay) {
                        console.error('Cart elements not found');
                        return;
                  }

                  cartSidebar.classList.add('open');
                  cartOverlay.classList.add('show');

                  // Ensure buttons are properly initialized
                  const checkoutBtn = document.getElementById('checkoutBtn');
                  const clearCartBtn = document.getElementById('clearCartBtn');

                  if (checkoutBtn) {
                        checkoutBtn.disabled = false;
                        checkoutBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Checkout';
                  }

                  // Fetch cart data
                  fetchGuestCart();
            }

            function closeCart() {
                  document.getElementById('cartSidebar').classList.remove('open');
                  document.getElementById('cartOverlay').classList.remove('show');
            }

            function handleProductCardClick(productId) {
                  // Check if quantity overlay is already visible
                  const overlay = document.getElementById(`quantity-overlay-${productId}`);
                  if (overlay && overlay.style.display === 'flex') {
                        return; // Don't do anything if overlay is already visible
                  }

                  // Show product detail modal instead of quantity overlay
                  showProductDetailModal(productId);
            }

            function addToGuestCart(productId) {
                  addToGuestCartWithQuantity(productId, 1);
            }

            function addToGuestCartWithQuantity(productId, quantity = null) {
                  if (quantity === null) {
                        quantity = parseInt(document.getElementById(`quantity-${productId}`).textContent);
                  }

                  fetch('index.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                              },
                              body: `action=add_to_cart&product_id=${productId}&quantity=${quantity}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    showNotification(`${quantity} item(s) added to cart!`, 'success');
                                    updateCartBadge();
                                    fetchGuestCart();
                                    hideQuantityOverlay(productId);
                              } else {
                                    showNotification(data.message, 'error');
                              }
                        })
                        .catch(error => {
                              showNotification('Error adding product to cart', 'error');
                        });
            }

            function showQuantityOverlay(productId) {
                  document.getElementById(`quantity-overlay-${productId}`).style.display = 'flex';
                  document.getElementById(`quantity-${productId}`).textContent = '1';
            }

            function hideQuantityOverlay(productId) {
                  document.getElementById(`quantity-overlay-${productId}`).style.display = 'none';
            }

            function updateProductQuantity(productId, change) {
                  const quantityElement = document.getElementById(`quantity-${productId}`);
                  let currentQuantity = parseInt(quantityElement.textContent);
                  let newQuantity = currentQuantity + change;

                  // Ensure quantity doesn't go below 1
                  if (newQuantity < 1) {
                        newQuantity = 1;
                  }

                  quantityElement.textContent = newQuantity;
            }

            // Add keyboard support for quantity overlay
            document.addEventListener('keydown', function(e) {
                  if (e.key === 'Escape') {
                        // Close all quantity overlays when Escape is pressed
                        document.querySelectorAll('.quantity-overlay').forEach(overlay => {
                              overlay.style.display = 'none';
                        });
                  }
            });

            function updateGuestQuantity(productId, change) {
                  const cartItem = document.querySelector(`#cart-item-${productId}`);
                  const currentQty = parseInt(cartItem.querySelector('.cart-qty-input').value);
                  const stockQty = parseInt(cartItem.dataset.stock);
                  const newQty = currentQty + change;

                  if (newQty <= 0) {
                        removeFromGuestCart(productId);
                        return;
                  }

                  // Check if new quantity exceeds stock
                  if (newQty > stockQty) {
                        showNotification(`Cannot add more than ${stockQty} items. Only ${stockQty} available in stock.`, 'error');
                        return;
                  }

                  fetch('index.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                              },
                              body: `action=update_cart&product_id=${productId}&quantity=${newQty}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    fetchGuestCart();
                                    updateCartBadge();
                              }
                        });
            }

            function removeFromGuestCart(productId) {
                  fetch('index.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                              },
                              body: `action=remove_from_cart&product_id=${productId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    fetchGuestCart();
                                    updateCartBadge();
                              }
                        });
            }

            function clearGuestCart() {
                  if (confirm('Are you sure you want to clear the cart?')) {
                        fetch('index.php', {
                                    method: 'POST',
                                    headers: {
                                          'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: 'action=clear_guest_cart'
                              })
                              .then(response => response.json())
                              .then(data => {
                                    if (data.success) {
                                          fetchGuestCart();
                                          updateCartBadge();
                                          showNotification('Cart cleared successfully', 'success');
                                    }
                              });
                  }
            }

            function fetchGuestCart() {
                  fetch('index.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                              },
                              body: 'action=fetch_guest_cart'
                        })
                        .then(res => res.json())
                        .then(data => {
                              if (data.success) {
                                    document.getElementById('cartItemsContainer').innerHTML = data.html;
                                    document.getElementById('cartTotal').textContent = data.total;

                                    // Show/hide checkout and clear buttons
                                    const checkoutBtn = document.getElementById('checkoutBtn');
                                    const clearCartBtn = document.getElementById('clearCartBtn');

                                    console.log('Cart data:', data); // Debug log

                                    if (data.html.includes('Cart is empty') || data.total === '$0.00' || data.total === '$0') {
                                          if (checkoutBtn) {
                                                checkoutBtn.style.display = 'none';
                                                checkoutBtn.disabled = true;
                                          }
                                          if (clearCartBtn) {
                                                clearCartBtn.style.display = 'none';
                                          }
                                    } else {
                                          if (checkoutBtn) {
                                                checkoutBtn.style.display = 'block';
                                                checkoutBtn.disabled = false;
                                                checkoutBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Checkout';

                                                // Add a small delay to ensure button is fully ready
                                                setTimeout(() => {
                                                      if (checkoutBtn) {
                                                            checkoutBtn.disabled = false;
                                                      }
                                                }, 100);
                                          }
                                          if (clearCartBtn) {
                                                clearCartBtn.style.display = 'block';
                                          }
                                    }
                              }
                        })
                        .catch(error => {
                              console.error('Error fetching cart:', error);
                        });
            }

            function updateCartBadge() {
                  fetch('index.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                              },
                              body: 'action=fetch_guest_cart'
                        })
                        .then(res => res.json())
                        .then(data => {
                              const badge = document.getElementById('cartBadge');
                              let itemCount = 0;

                              if (data.success && data.html && !data.html.includes('Cart is empty')) {
                                    // Parse the cart HTML to count quantities
                                    // But better: add a new field in your PHP response with the total quantity
                                    if (data.total_quantity !== undefined) {
                                          itemCount = data.total_quantity;
                                    }
                              }

                              if (itemCount > 0) {
                                    badge.textContent = itemCount;
                                    badge.style.display = 'flex';
                              } else {
                                    badge.style.display = 'none';
                              }
                        });
            }

            function showPaymentModal() {
                  console.log('showPaymentModal called'); // Debug log

                  const total = document.getElementById('cartTotal').textContent;
                  const checkoutBtn = document.getElementById('checkoutBtn');

                  console.log('Cart total:', total); // Debug log
                  console.log('Checkout button:', checkoutBtn); // Debug log

                  // Check if checkout button exists
                  if (!checkoutBtn) {
                        console.error('Checkout button not found');
                        showNotification('Error: Checkout button not found', 'error');
                        return;
                  }

                  // Check if cart total is null, empty, or zero
                  if (!total || total === '$0.00' || total === '$0' || total.trim() === '') {
                        showNotification('Cart is empty. Please add items before checkout.', 'error');
                        return;
                  }

                  // Check if button is visible
                  if (checkoutBtn.style.display === 'none') {
                        showNotification('Please wait while cart is loading...', 'error');
                        return;
                  }

                  // Check if button is already disabled
                  if (checkoutBtn.disabled) {
                        console.log('Checkout button is disabled, skipping...');
                        return;
                  }

                  // Disable checkout button to prevent multiple clicks
                  checkoutBtn.disabled = true;
                  checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';

                  try {
                        const usdAmount = parseFloat(total.replace('$', ''));
                        const modalTotalElement = document.getElementById('modal-total');

                        if (!modalTotalElement) {
                              throw new Error('modal-total element not found');
                        }

                        modalTotalElement.textContent = usdAmount.toFixed(2);

                        // Initialize currency display
                        updateCurrencyDisplay();

                        // Reset cash fields when opening modal
                        document.getElementById('guest_amount_tendered').value = '';
                        document.getElementById('guest_change_amount').value = '';
                        document.getElementById('guest-insufficient-amount').style.display = 'none';

                        // Show/hide cash fields based on payment method
                        toggleGuestCashFields();

                        const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
                        paymentModal.show();
                        console.log('Payment modal opened successfully'); // Debug log

                        // Add visual feedback for logged-in customers
                        const customerNameField = document.getElementById('customer_name');
                        const customerEmailField = document.getElementById('customer_email');

                        if (customerNameField && customerNameField.readOnly) {
                              customerNameField.style.backgroundColor = '#f8f9fa';
                              customerNameField.style.borderColor = '#28a745';
                        }

                        if (customerEmailField && customerEmailField.readOnly) {
                              customerEmailField.style.backgroundColor = '#f8f9fa';
                              customerEmailField.style.borderColor = '#28a745';
                        }

                  } catch (error) {
                        console.error('Error showing payment modal:', error);
                        showNotification('Error opening payment modal', 'error');

                        // Re-enable button on error
                        checkoutBtn.disabled = false;
                        checkoutBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Checkout';
                        return;
                  }

                  // Re-enable button after modal is shown
                  setTimeout(() => {
                        if (checkoutBtn) {
                              checkoutBtn.disabled = false;
                              checkoutBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Checkout';
                              console.log('Checkout button re-enabled'); // Debug log
                        }
                  }, 1000);
            }

            function toggleGuestCashFields() {
                  const paymentMethod = document.getElementById('payment_method').value;
                  const cashFields = document.getElementById('guest-cash-fields');
                  const quickAmounts = document.getElementById('guest-quick-amounts');
                  const amountTendered = document.getElementById('guest_amount_tendered');
                  const changeAmount = document.getElementById('guest_change_amount');
                  const insufficientAmount = document.getElementById('guest-insufficient-amount');

                  if (paymentMethod === 'cash') {
                        cashFields.style.display = 'block';
                        quickAmounts.style.display = 'block';
                        amountTendered.setAttribute('required', 'required');
                        changeAmount.setAttribute('readonly', 'readonly');
                        changeAmount.value = '';
                        insufficientAmount.style.display = 'none';
                  } else {
                        cashFields.style.display = 'none';
                        quickAmounts.style.display = 'none';
                        amountTendered.removeAttribute('required');
                        changeAmount.setAttribute('readonly', 'readonly');
                        changeAmount.value = '';
                        insufficientAmount.style.display = 'none';
                  }
            }

            function setGuestQuickAmount(amount) {
                  document.getElementById('guest_amount_tendered').value = amount;
                  calculateGuestChange();
            }

            function updateCurrencyDisplay() {
                  const currencyCode = document.getElementById('currency_code').value;
                  const modalTotalElement = document.getElementById('modal-total');

                  if (!modalTotalElement) {
                        console.error('modal-total element not found');
                        return;
                  }

                  const usdAmount = parseFloat(modalTotalElement.textContent);

                  if (!currencyCode || isNaN(usdAmount)) {
                        return;
                  }

                  // Convert amount to selected currency
                  fetch('index.php', {
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
                                    document.getElementById('modal-total-display').textContent = data.formatted_amount;

                                    // Update exchange rate info
                                    if (currencyCode !== 'USD') {
                                          document.getElementById('exchange-rate-info').style.display = 'block';
                                          document.getElementById('current-rate').textContent = data.rate.toFixed(6);
                                    } else {
                                          document.getElementById('exchange-rate-info').style.display = 'none';
                                    }

                                    // Update currency symbols
                                    const symbol = data.formatted_amount.replace(/[\d.,]/g, '').trim();
                                    document.getElementById('tendered-currency-symbol').textContent = symbol;
                                    document.getElementById('change-currency-symbol').textContent = symbol;

                                    // Update quick amount buttons
                                    updateQuickAmountButtons(currencyCode);
                              }
                        })
                        .catch(error => {
                              console.error('Error converting currency:', error);
                        });
            }

            function updateQuickAmountButtons(currencyCode) {
                  const amounts = [5, 10, 20, 50, 100];

                  amounts.forEach(amount => {
                        fetch('index.php', {
                                    method: 'POST',
                                    headers: {
                                          'Content-Type': 'application/x-www-form-urlencoded'
                                    },
                                    body: `action=convert_currency&amount=${amount}&from_currency=USD&to_currency=${currencyCode}`
                              })
                              .then(response => response.json())
                              .then(data => {
                                    if (data.success) {
                                          document.getElementById(`quick-${amount}`).textContent = data.formatted_amount;
                                    }
                              })
                              .catch(error => {
                                    console.error('Error updating quick amount:', error);
                              });
                  });
            }

            function calculateGuestChange() {
                  const currencyCode = document.getElementById('currency_code').value;
                  const modalTotalElement = document.getElementById('modal-total');

                  if (!modalTotalElement) {
                        console.error('modal-total element not found');
                        return;
                  }

                  const usdAmount = parseFloat(modalTotalElement.textContent);
                  const amountTendered = parseFloat(document.getElementById('guest_amount_tendered').value) || 0;
                  const changeAmount = document.getElementById('guest_change_amount');
                  const insufficientAmount = document.getElementById('guest-insufficient-amount');
                  const completeBtn = document.getElementById('guest-complete-payment-btn');

                  if (isNaN(usdAmount) || isNaN(amountTendered) || !currencyCode) {
                        changeAmount.value = '';
                        insufficientAmount.style.display = 'none';
                        completeBtn.disabled = false;
                        return;
                  }

                  // Convert USD amount to selected currency for comparison
                  fetch('index.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                              },
                              body: `action=convert_currency&amount=${usdAmount}&from_currency=USD&to_currency=${currencyCode}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    const convertedTotal = data.converted_amount;
                                    const change = amountTendered - convertedTotal;

                                    changeAmount.value = change >= 0 ? change.toFixed(2) : '';

                                    if (change < 0) {
                                          insufficientAmount.style.display = 'block';
                                          completeBtn.disabled = true;
                                    } else {
                                          insufficientAmount.style.display = 'none';
                                          completeBtn.disabled = false;
                                    }
                              }
                        })
                        .catch(error => {
                              console.error('Error calculating change:', error);
                        });
            }

            function showNotification(message, type) {
                  // Create notification element
                  const notification = document.createElement('div');
                  notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
                  notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                  notification.innerHTML = `
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                        ${message}
                        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
                  `;

                  document.body.appendChild(notification);

                  // Auto remove after 3 seconds
                  setTimeout(() => {
                        if (notification.parentElement) {
                              notification.remove();
                        }
                  }, 3000);
            }

            function printReceipt() {
                  if (currentOrderId) {
                        window.open(`guest_receipt.php?order_id=${currentOrderId}`, '_blank');
                  }
            }

            function showCustomerLogin() {
                  // Store current cart data in sessionStorage
                  const cartData = JSON.stringify({
                        total: document.getElementById('cartTotal').textContent,
                        items: document.getElementById('cartItemsContainer').innerHTML
                  });
                  sessionStorage.setItem('pendingCart', cartData);

                  // Redirect to login page
                  window.location.href = 'login.php?redirect=checkout';
            }

            function showCustomerRegister() {
                  // Store current cart data in sessionStorage
                  const cartData = JSON.stringify({
                        total: document.getElementById('cartTotal').textContent,
                        items: document.getElementById('cartItemsContainer').innerHTML
                  });
                  sessionStorage.setItem('pendingCart', cartData);

                  // Redirect to customer registration page
                  window.location.href = 'customer_register.php?redirect=checkout';
            }

            // Payment form submission
            document.getElementById('paymentForm').addEventListener('submit', function(e) {
                  e.preventDefault();

                  const formData = new FormData(this);
                  formData.append('action', 'process_guest_payment');

                  fetch('index.php', {
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
                                    fetchGuestCart();
                                    updateCartBadge();
                                    closeCart();
                              } else {
                                    showNotification(data.message, 'error');
                              }
                        })
                        .catch(error => {
                              showNotification('Error processing payment', 'error');
                        });
            });

            // Initialize cart badge on page load
            document.addEventListener('DOMContentLoaded', function() {
                  updateCartBadge();

                  // Check if we need to restore cart from sessionStorage
                  if (window.location.search.includes('restore_cart=1')) {
                        const pendingCart = sessionStorage.getItem('pendingCart');
                        if (pendingCart) {
                              try {
                                    const cartData = JSON.parse(pendingCart);
                                    // Clear the stored cart data
                                    sessionStorage.removeItem('pendingCart');

                                    // Show notification
                                    showNotification('Welcome back! Your cart has been restored.', 'success');

                                    // Refresh cart display
                                    fetchGuestCart();
                              } catch (e) {
                                    console.error('Error restoring cart:', e);
                              }
                        }
                  }

                  // Show welcome message for newly registered customers
                  if (window.location.search.includes('registered=1')) {
                        showNotification('Registration successful! Welcome to our store.', 'success');
                        // Clean up URL
                        window.history.replaceState({}, document.title, window.location.pathname);
                  }

                  // Show welcome message for logged in customers
                  if (window.location.search.includes('logged_in=1')) {
                        showNotification('Welcome back! You are now logged in.', 'success');
                        // Clean up URL
                        window.history.replaceState({}, document.title, window.location.pathname);
                  }
            });

            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                  anchor.addEventListener('click', function(e) {
                        e.preventDefault();
                        const target = document.querySelector(this.getAttribute('href'));
                        if (target) {
                              target.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'start'
                              });
                        }
                  });
            });

            // Navbar background change on scroll
            window.addEventListener('scroll', function() {
                  const navbar = document.querySelector('.navbar');
                  if (window.scrollY > 50) {
                        navbar.style.background = 'rgba(255,255,255,0.98)';
                  } else {
                        navbar.style.background = 'rgba(255,255,255,0.95)';
                  }
            });

            // Animate stats on scroll
            const observerOptions = {
                  threshold: 0.5
            };
            const observer = new IntersectionObserver(function(entries) {
                  entries.forEach(entry => {
                        if (entry.isIntersecting) {
                              entry.target.style.opacity = '1';
                              entry.target.style.transform = 'translateY(0)';
                        } else {
                              entry.target.style.opacity = '0';
                              entry.target.style.transform = 'translateY(20px)';
                        }
                  });
            }, observerOptions);

            document.querySelectorAll('.stat-number, .product-item-container, .powerful-features').forEach(stat => {
                  stat.style.opacity = '0';
                  stat.style.transform = 'translateY(20px)';
                  stat.style.transition = 'all 0.6s ease';
                  observer.observe(stat);
            });

            function updateGuestQuantityDirect(productId, newQuantity) {
                  const cartItem = document.querySelector(`#cart-item-${productId}`);
                  const quantity = parseInt(newQuantity);
                  const stockQty = parseInt(cartItem.dataset.stock);

                  if (isNaN(quantity) || quantity <= 0) {
                        // Reset to 1 if invalid input
                        cartItem.querySelector('.cart-qty-input').value = 1;
                        return;
                  }

                  // Check if quantity exceeds stock
                  if (quantity > stockQty) {
                        showNotification(`Cannot add more than ${stockQty} items. Only ${stockQty} available in stock.`, 'error');
                        // Reset to stock quantity
                        cartItem.querySelector('.cart-qty-input').value = stockQty;
                        return;
                  }

                  fetch('index.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                              },
                              body: `action=update_cart&product_id=${productId}&quantity=${quantity}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    fetchGuestCart();
                                    updateCartBadge();
                              }
                        });
            }

            // Function removed - no longer needed since we use span instead of input

            // Product Detail Modal Functions
            let currentProductId = null;

            function showProductDetailModal(productId) {
                  currentProductId = productId;

                  // Fetch product details
                  fetch('index.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                              },
                              body: `action=get_product_details&product_id=${productId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    populateProductModal(data.product);
                                    const modal = new bootstrap.Modal(document.getElementById('productDetailModal'));
                                    modal.show();
                              } else {
                                    showNotification(data.message, 'error');
                              }
                        })
                        .catch(error => {
                              console.error('Error fetching product details:', error);
                              showNotification('Error loading product details', 'error');
                        });
            }

            function populateProductModal(product) {
                  // Set product image
                  document.getElementById('modal-product-image').src = product.image_path;
                  document.getElementById('modal-product-image').alt = product.name;

                  // Set product name and description
                  document.getElementById('modal-product-name').textContent = product.name;
                  document.getElementById('modal-product-description').textContent = product.description;

                  // Set product info
                  document.getElementById('modal-product-category').textContent = product.category;
                  document.getElementById('modal-product-stock').textContent = product.stock_quantity;
                  document.getElementById('modal-product-code').textContent = product.product_code || 'N/A';
                  document.getElementById('modal-product-barcode').textContent = product.barcode || 'N/A';

                  // Set prices
                  const currentPriceElement = document.getElementById('modal-current-price');
                  const originalPriceElement = document.getElementById('modal-original-price');
                  const discountBadge = document.getElementById('modal-discount-badge');
                  const discountPercent = document.getElementById('modal-discount-percent');

                  currentPriceElement.textContent = `$${parseFloat(product.display_price).toFixed(2)}`;

                  if (product.has_discount) {
                        originalPriceElement.textContent = `$${parseFloat(product.price).toFixed(2)}`;
                        originalPriceElement.style.display = 'block';
                        discountBadge.style.display = 'block';
                        discountPercent.textContent = `${product.discount_percent}%`;
                  } else {
                        originalPriceElement.style.display = 'none';
                        discountBadge.style.display = 'none';
                  }

                  // Handle clothing-specific attributes
                  const clothingAttributes = document.getElementById('modal-clothing-attributes');
                  const sizeBadge = document.getElementById('modal-product-size');
                  const colorBadge = document.getElementById('modal-product-color');
                  const materialBadge = document.getElementById('modal-product-material');
                  const weightBadge = document.getElementById('modal-product-weight');

                  if (product.type === 'Clothes') {
                        clothingAttributes.style.display = 'block';

                        // Show size if available
                        if (product.size) {
                              sizeBadge.style.display = 'inline-block';
                              sizeBadge.querySelector('.size-value').textContent = product.size;
                        } else {
                              sizeBadge.style.display = 'none';
                        }

                        // Show color if available
                        if (product.color) {
                              colorBadge.style.display = 'inline-block';
                              colorBadge.querySelector('.color-value').textContent = product.color;
                        } else {
                              colorBadge.style.display = 'none';
                        }

                        // Show material if available
                        if (product.material) {
                              materialBadge.style.display = 'inline-block';
                              materialBadge.querySelector('.material-value').textContent = product.material;
                        } else {
                              materialBadge.style.display = 'none';
                        }

                        // Show weight if available
                        if (product.weight) {
                              weightBadge.style.display = 'inline-block';
                              weightBadge.querySelector('.weight-value').textContent = product.weight;
                        } else {
                              weightBadge.style.display = 'none';
                        }
                  } else {
                        clothingAttributes.style.display = 'none';
                  }

                  // Reset quantity to 1
                  document.getElementById('modal-quantity').value = 1;
            }

            function updateModalQuantity(change) {
                  const quantityInput = document.getElementById('modal-quantity');
                  let currentQuantity = parseInt(quantityInput.value);
                  let newQuantity = currentQuantity + change;

                  // Ensure quantity doesn't go below 1
                  if (newQuantity < 1) {
                        newQuantity = 1;
                  }

                  quantityInput.value = newQuantity;
            }

            function addToCartFromModal() {
                  if (!currentProductId) return;

                  const quantity = parseInt(document.getElementById('modal-quantity').value);

                  fetch('index.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                              },
                              body: `action=add_to_cart&product_id=${currentProductId}&quantity=${quantity}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    showNotification(`${quantity} item(s) added to cart!`, 'success');
                                    updateCartBadge();
                                    fetchGuestCart();

                                    // Close the modal
                                    const modal = bootstrap.Modal.getInstance(document.getElementById('productDetailModal'));
                                    modal.hide();
                              } else {
                                    showNotification(data.message, 'error');
                              }
                        })
                        .catch(error => {
                              showNotification('Error adding product to cart', 'error');
                        });
            }

            // Add keyboard support for modal quantity
            document.addEventListener('keydown', function(e) {
                  if (e.key === 'Escape') {
                        // Close product detail modal when Escape is pressed
                        const modal = bootstrap.Modal.getInstance(document.getElementById('productDetailModal'));
                        if (modal) {
                              modal.hide();
                        }
                  }
            });

            // Category filtering function
            function filterByCategory(category) {
                  // Scroll to products section
                  document.getElementById('products').scrollIntoView({
                        behavior: 'smooth'
                  });

                  // Update active button state
                  updateActiveFilterButton(category);

                  // Add a small delay to ensure the section is visible
                  setTimeout(() => {
                        const productContainers = document.querySelectorAll('.product-item-container');

                        productContainers.forEach(container => {
                              const categoryBadges = container.querySelectorAll('.category-badge .badge');
                              let shouldShow = false;

                              if (category === 'All Products') {
                                    shouldShow = true;
                              } else if (category === 'Clothes') {
                                    // For Clothes filter, check if any badge contains "Clothes" (type badge)
                                    categoryBadges.forEach(badge => {
                                          const badgeText = badge.textContent.trim();
                                          if (badgeText === 'Clothes') {
                                                shouldShow = true;
                                          }
                                    });
                              } else if (category === 'Food') {
                                    // For Food filter, check if any badge contains "Food" (type badge)
                                    categoryBadges.forEach(badge => {
                                          const badgeText = badge.textContent.trim();
                                          if (badgeText === 'Food') {
                                                shouldShow = true;
                                          }
                                    });
                              } else {
                                    // For specific categories, check category badges
                                    categoryBadges.forEach(badge => {
                                          const badgeText = badge.textContent.trim();
                                          if (badgeText === category) {
                                                shouldShow = true;
                                          }
                                    });
                              }

                              container.style.display = shouldShow ? 'block' : 'none';
                        });

                        // Show notification
                        const categoryName = category === 'All Products' ? 'All Products' : category;
                        showNotification(`Showing ${categoryName}`, 'info');
                  }, 500);
            }

            // Function to update active filter button
            function updateActiveFilterButton(activeCategory) {
                  // Remove active class from all filter buttons
                  const allButtons = document.querySelectorAll('.category-filter-buttons .btn');
                  allButtons.forEach(btn => {
                        btn.classList.remove('active');
                        // Reset button styles
                        btn.classList.remove('btn-primary', 'btn-success', 'btn-info', 'btn-warning', 'btn-secondary', 'btn-danger', 'btn-dark');
                        btn.classList.add('btn-outline-primary', 'btn-outline-success', 'btn-outline-info', 'btn-outline-warning', 'btn-outline-secondary', 'btn-outline-danger', 'btn-outline-dark');
                  });

                  // Add active class to the clicked button
                  const activeButton = document.querySelector(`.category-filter-buttons .btn[onclick*="${activeCategory}"]`);
                  if (activeButton) {
                        activeButton.classList.add('active');
                        // Change button style to solid
                        const currentClasses = activeButton.className;
                        if (currentClasses.includes('btn-outline-primary')) {
                              activeButton.classList.remove('btn-outline-primary');
                              activeButton.classList.add('btn-primary');
                        } else if (currentClasses.includes('btn-outline-success')) {
                              activeButton.classList.remove('btn-outline-success');
                              activeButton.classList.add('btn-success');
                        } else if (currentClasses.includes('btn-outline-info')) {
                              activeButton.classList.remove('btn-outline-info');
                              activeButton.classList.add('btn-info');
                        } else if (currentClasses.includes('btn-outline-warning')) {
                              activeButton.classList.remove('btn-outline-warning');
                              activeButton.classList.add('btn-warning');
                        } else if (currentClasses.includes('btn-outline-secondary')) {
                              activeButton.classList.remove('btn-outline-secondary');
                              activeButton.classList.add('btn-secondary');
                        } else if (currentClasses.includes('btn-outline-danger')) {
                              activeButton.classList.remove('btn-outline-danger');
                              activeButton.classList.add('btn-danger');
                        } else if (currentClasses.includes('btn-outline-dark')) {
                              activeButton.classList.remove('btn-outline-dark');
                              activeButton.classList.add('btn-dark');
                        }
                  }
            }
      </script>
</body>

</html>