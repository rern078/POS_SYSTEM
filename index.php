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

            // Card payment fields
            $card_type = isset($_POST['card_type']) ? trim($_POST['card_type']) : null;
            $card_number = isset($_POST['card_number']) ? trim($_POST['card_number']) : null;
            $card_expiry = isset($_POST['card_expiry']) ? trim($_POST['card_expiry']) : null;
            $card_cvv = isset($_POST['card_cvv']) ? trim($_POST['card_cvv']) : null;
            $card_holder = isset($_POST['card_holder']) ? trim($_POST['card_holder']) : null;

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

                $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_email, total_amount, currency_code, exchange_rate, original_amount, payment_method, card_type, card_number, card_expiry, card_cvv, card_holder, amount_tendered, change_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$user_id, $customer_name, $customer_email, $converted_total, $currency_code, $exchange_rate, $original_amount, $payment_method, $card_type, $card_number, $card_expiry, $card_cvv, $card_holder, $amount_tendered, $change_amount]);
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
    <?php include 'includes/title.php'; ?>
</head>

<body>
    <?php include 'includes/header.php'; ?>

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

    <?php include 'includes/footer.php'; ?>


</body>

</html>