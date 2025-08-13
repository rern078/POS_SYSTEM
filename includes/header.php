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

<?php
// Function to check if current page matches the nav link
function isActivePage($page_name)
{
      $current_page = basename($_SERVER['PHP_SELF']);
      $current_url = $_SERVER['REQUEST_URI'];
      $query_string = $_SERVER['QUERY_STRING'] ?? '';

      // Special cases for different pages
      switch ($page_name) {
            case 'home':
                  // Check if we're on index.php without query parameters (except for anchor links)
                  return $current_page === 'index.php' && empty($query_string);
            case 'products':
                  // Check if we're on product-list.php or any product-related page
                  return $current_page === 'product-list.php' ||
                        strpos($current_url, 'product-list.php') !== false ||
                        strpos($current_url, 'products') !== false;
            case 'features':
                  // Check for features section anchor
                  return strpos($current_url, '#features') !== false;
            case 'about':
                  // Check for about section anchor
                  return strpos($current_url, '#about') !== false;
            case 'contact':
                  // Check for contact section anchor
                  return strpos($current_url, '#contact') !== false;
            default:
                  return false;
      }
}

// Function to get current page info for debugging (optional)
function getCurrentPageInfo()
{
      return [
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? '',
            'php_self' => $_SERVER['PHP_SELF'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'query_string' => $_SERVER['QUERY_STRING'] ?? '',
            'basename' => basename($_SERVER['PHP_SELF'])
      ];
}
?>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-light fixed-top">
      <div class="container">
            <a class="navbar-brand" href="/">
                  <i class="fas fa-store text-primary me-2"></i>CH-FASHION
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                  <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                  <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                              <a class="nav-link <?php echo isActivePage('home') ? 'active' : ''; ?>" href="/">Home</a>
                        </li>
                        <li class="nav-item">
                              <a class="nav-link <?php echo isActivePage('features') ? 'active' : ''; ?>" href="#features">Features</a>
                        </li>
                        <li class="nav-item dropdown">
                              <a class="nav-link dropdown-toggle <?php echo isActivePage('products') ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
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
                              <a class="nav-link <?php echo isActivePage('about') ? 'active' : ''; ?>" href="#about">About</a>
                        </li>
                        <li class="nav-item">
                              <a class="nav-link <?php echo isActivePage('contact') ? 'active' : ''; ?>" href="#contact">Contact</a>
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