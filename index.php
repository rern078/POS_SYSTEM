<?php
session_start();
require_once 'config/database.php';

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

            case 'process_guest_payment':
                  if (empty($_SESSION['guest_cart'])) {
                        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
                        exit;
                  }

                  $customer_name = trim($_POST['customer_name']);
                  $customer_email = trim($_POST['customer_email']);
                  $payment_method = $_POST['payment_method'];
                  $total_amount = 0;

                  // Calculate total
                  foreach ($_SESSION['guest_cart'] as $item) {
                        $total_amount += $item['price'] * $item['quantity'];
                  }

                  try {
                        $pdo = getDBConnection();
                        $pdo->beginTransaction();

                        // Create order - include user_id if customer is logged in
                        $user_id = null;
                        if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer') {
                              $user_id = $_SESSION['user_id'];
                        }

                        $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_email, total_amount, payment_method, status) VALUES (?, ?, ?, ?, ?, 'completed')");
                        $stmt->execute([$user_id, $customer_name, $customer_email, $total_amount, $payment_method]);
                        $order_id = $pdo->lastInsertId();

                        // Add order items and update stock
                        foreach ($_SESSION['guest_cart'] as $product_id => $item) {
                              // Add order item
                              $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                              $stmt->execute([$order_id, $product_id, $item['quantity'], $item['price']]);

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
                        <i class="fas fa-store text-primary me-2"></i>MCH-DIGITAL
                  </a>

                  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                        <span class="navbar-toggler-icon"></span>
                  </button>

                  <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav ms-auto">
                              <li class="nav-item">
                                    <a class="nav-link" href="#features">Features</a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="#products">Products</a>
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
                                                <li><a class="dropdown-item" href="user/profile.php">
                                                            <i class="fas fa-user me-2"></i>Profile
                                                      </a></li>
                                                <li><a class="dropdown-item" href="user/settings.php">
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
                                          <a class="nav-link btn btn-primary btn-custom ms-2 dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-user-plus me-2"></i>Register
                                          </a>
                                          <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="customer_register.php">
                                                            <i class="fas fa-user me-2"></i>Customer Account
                                                      </a></li>
                                                <li><a class="dropdown-item" href="register.php">
                                                            <i class="fas fa-user-tie me-2"></i>Staff Account
                                                      </a></li>
                                          </ul>
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

                  <div class="row g-4">
                        <?php
                        // Get featured products from database
                        $pdo = getDBConnection();
                        $stmt = $pdo->prepare("SELECT * FROM products WHERE stock_quantity > 0 ORDER BY RAND() LIMIT 8");
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
                                          <button class="add-to-cart-btn" onclick="event.stopPropagation(); showQuantityOverlay(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-plus"></i>
                                          </button>
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
                                                            </div>
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
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Complete Your Order</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="paymentForm">
                              <div class="modal-body">
                                    <!-- Customer Login Option -->
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
                                    </div>

                                    <div class="mb-3">
                                          <label for="customer_name" class="form-label">Full Name *</label>
                                          <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                                    </div>
                                    <div class="mb-3">
                                          <label for="customer_email" class="form-label">Email Address *</label>
                                          <input type="email" class="form-control" id="customer_email" name="customer_email" required>
                                    </div>
                                    <div class="mb-3">
                                          <label for="payment_method" class="form-label">Payment Method *</label>
                                          <select class="form-select" id="payment_method" name="payment_method" required>
                                                <option value="">Select payment method</option>
                                                <option value="cash">Cash on Delivery</option>
                                                <option value="card">Credit/Debit Card</option>
                                                <option value="mobile">Mobile Payment</option>
                                                <option value="bank">Bank Transfer</option>
                                          </select>
                                    </div>
                                    <div class="alert alert-info">
                                          <strong>Total Amount: $<span id="modal-total">0.00</span></strong>
                                    </div>
                                    <div class="alert alert-warning">
                                          <i class="fas fa-info-circle me-2"></i>
                                          <small>Please note: This is a demo payment system. No actual payment will be processed.</small>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">
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

                  // Show quantity overlay when card is clicked
                  showQuantityOverlay(productId);
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
                        document.getElementById('modal-total').textContent = total.replace('$', '');
                        const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
                        paymentModal.show();
                        console.log('Payment modal opened successfully'); // Debug log
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
      </script>
</body>

</html>