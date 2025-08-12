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
                  $search_type = isset($_POST['search_type']) ? $_POST['search_type'] : 'auto';

                  if (empty($code)) {
                        echo json_encode(['success' => false, 'message' => 'No code provided']);
                        exit;
                  }

                  $product = null;
                  $search_method = '';

                  // Enhanced search based on type
                  switch ($search_type) {
                        case 'barcode':
                              $product = searchByBarcode($pdo, $code);
                              $search_method = 'barcode';
                              break;
                        case 'qr':
                              $parsed_code = parseQRCode($code);
                              $product = searchByQRCode($pdo, $parsed_code, $code);
                              $search_method = 'QR code';
                              break;
                        case 'product':
                              $product = searchByProductCode($pdo, $code);
                              $search_method = 'product code';
                              break;
                        case 'name':
                              $product = searchByProductName($pdo, $code);
                              $search_method = 'product name';
                              break;
                        case 'auto':
                        default:
                              // Auto-detect and search
                              $product = autoSearchProduct($pdo, $code);
                              $search_method = 'auto-detection';
                              break;
                  }

                  if ($product) {
                        echo json_encode([
                              'success' => true,
                              'product' => $product,
                              'message' => "Product found via {$search_method}: " . $product['name'],
                              'search_method' => $search_method
                        ]);
                  } else {
                        // Try alternative search methods if auto-search failed
                        if ($search_type === 'auto') {
                              $alternative_product = searchByProductName($pdo, $code);
                              if ($alternative_product) {
                                    echo json_encode([
                                          'success' => true,
                                          'product' => $alternative_product,
                                          'message' => 'Product found via name search: ' . $alternative_product['name'],
                                          'search_method' => 'name search'
                                    ]);
                                    exit;
                              }
                        }

                        echo json_encode(['success' => false, 'message' => 'Product not found for: ' . $code]);
                  }
                  exit;

            case 'upload_qr_code':
                  if (!isset($_FILES['qr_image']) || $_FILES['qr_image']['error'] !== UPLOAD_ERR_OK) {
                        echo json_encode(['success' => false, 'message' => 'No image uploaded or upload error']);
                        exit;
                  }

                  $uploaded_file = $_FILES['qr_image'];
                  $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp'];

                  if (!in_array($uploaded_file['type'], $allowed_types)) {
                        echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload an image file.']);
                        exit;
                  }

                  if ($uploaded_file['size'] > 5 * 1024 * 1024) { // 5MB limit
                        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
                        exit;
                  }

                  // Create uploads directory if it doesn't exist
                  $upload_dir = '../uploads/qr_codes/';
                  if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                  }

                  // Generate unique filename
                  $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
                  $filename = 'qr_' . time() . '_' . uniqid() . '.' . $file_extension;
                  $filepath = $upload_dir . $filename;

                  if (move_uploaded_file($uploaded_file['tmp_name'], $filepath)) {
                        // Try to decode QR code from image
                        $qr_data = decodeQRFromImage($filepath);

                        if ($qr_data) {
                              // Parse the QR code data
                              $parsed_code = parseQRCode($qr_data);

                              // Search for product
                              $stmt = $pdo->prepare("SELECT * FROM products WHERE (barcode = ? OR qr_code = ? OR product_code = ?) AND stock_quantity > 0");
                              $stmt->execute([$parsed_code, $parsed_code, $parsed_code]);
                              $product = $stmt->fetch(PDO::FETCH_ASSOC);

                              if (!$product) {
                                    // Try with original QR data
                                    $stmt = $pdo->prepare("SELECT * FROM products WHERE (barcode = ? OR qr_code = ? OR product_code = ?) AND stock_quantity > 0");
                                    $stmt->execute([$qr_data, $qr_data, $qr_data]);
                                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                              }

                              if ($product) {
                                    echo json_encode([
                                          'success' => true,
                                          'product' => $product,
                                          'message' => 'Product found: ' . $product['name'],
                                          'qr_data' => $qr_data,
                                          'image_path' => $filepath
                                    ]);
                              } else {
                                    // Product not found, but QR code was decoded
                                    $qr_info = getQRCodeInfo($qr_data);
                                    echo json_encode([
                                          'success' => false,
                                          'message' => 'Product not found for QR code',
                                          'qr_data' => $qr_data,
                                          'qr_info' => $qr_info,
                                          'image_path' => $filepath
                                    ]);
                              }
                        } else {
                              echo json_encode(['success' => false, 'message' => 'No QR code found in the uploaded image']);
                        }
                  } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
                  }
                  exit;

            case 'upload_barcode':
                  if (!isset($_FILES['barcode_image']) || $_FILES['barcode_image']['error'] !== UPLOAD_ERR_OK) {
                        echo json_encode(['success' => false, 'message' => 'No image uploaded or upload error']);
                        exit;
                  }

                  $uploaded_file = $_FILES['barcode_image'];
                  $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp'];

                  if (!in_array($uploaded_file['type'], $allowed_types)) {
                        echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload an image file.']);
                        exit;
                  }

                  if ($uploaded_file['size'] > 5 * 1024 * 1024) { // 5MB limit
                        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
                        exit;
                  }

                  // Create uploads directory if it doesn't exist
                  $upload_dir = '../uploads/barcodes/';
                  if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                  }

                  // Generate unique filename
                  $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
                  $filename = 'barcode_' . time() . '_' . uniqid() . '.' . $file_extension;
                  $filepath = $upload_dir . $filename;

                  if (move_uploaded_file($uploaded_file['tmp_name'], $filepath)) {
                        // Try to decode barcode from image using ZXing
                        $barcode_data = decodeBarcodeFromImage($filepath);

                        if ($barcode_data) {
                              // Search for product using barcode
                              $product = searchByBarcode($pdo, $barcode_data);

                              if ($product) {
                                    echo json_encode([
                                          'success' => true,
                                          'product' => $product,
                                          'message' => 'Product found: ' . $product['name'],
                                          'barcode_data' => $barcode_data,
                                          'image_path' => $filepath
                                    ]);
                              } else {
                                    // Product not found, but barcode was decoded
                                    $barcode_info = getBarcodeInfo($barcode_data);
                                    echo json_encode([
                                          'success' => false,
                                          'message' => 'Product not found for barcode',
                                          'barcode_data' => $barcode_data,
                                          'barcode_info' => $barcode_info,
                                          'image_path' => $filepath
                                    ]);
                              }
                        } else {
                              echo json_encode(['success' => false, 'message' => 'No barcode found in the uploaded image']);
                        }
                  } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
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
                                          <?php if (!empty($product['qr_code'])): ?>
                                                <div class="mt-2 text-center">
                                                      <button class="btn btn-sm btn-outline-primary" onclick="showProductQR(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['qr_code']); ?>', '<?php echo htmlspecialchars($product['name']); ?>')">
                                                            <i class="fas fa-qrcode"></i> View QR
                                                      </button>
                                                </div>
                                          <?php endif; ?>
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

// Helper function to parse QR codes
function parseQRCode($code)
{
      // Handle different QR code formats

      // Product JSON format
      if (strpos($code, '{"type":"product"') === 0) {
            $data = json_decode($code, true);
            if ($data && isset($data['code'])) {
                  return $data['code'];
            }
      }

      // PRODUCT: prefix format
      if (strpos($code, 'PRODUCT:') === 0) {
            return substr($code, 8);
      }

      // WiFi format - extract SSID if it's a product code
      if (strpos($code, 'WIFI:') === 0) {
            // Parse WiFi QR code to extract SSID
            preg_match('/S:([^;]+)/', $code, $matches);
            if (isset($matches[1])) {
                  return $matches[1];
            }
      }

      // vCard format - extract name if it's a product code
      if (strpos($code, 'BEGIN:VCARD') === 0) {
            preg_match('/FN:([^\n]+)/', $code, $matches);
            if (isset($matches[1])) {
                  return $matches[1];
            }
      }

      // Email format - extract email if it's a product code
      if (strpos($code, 'mailto:') === 0) {
            $email = substr($code, 7);
            $email = strtok($email, '?');
            return $email;
      }

      // SMS format - extract phone if it's a product code
      if (strpos($code, 'sms:') === 0) {
            $phone = substr($code, 4);
            $phone = strtok($phone, '?');
            return $phone;
      }

      // Phone format
      if (strpos($code, 'tel:') === 0) {
            return substr($code, 4);
      }

      // Return original code if no special format detected
      return $code;
}

// Helper function to decode QR code from image
function decodeQRFromImage($image_path)
{
      // Check if ZXing library is available via command line
      $zxing_path = '/usr/local/bin/zxing'; // Common installation path

      if (file_exists($zxing_path) && is_executable($zxing_path)) {
            $command = escapeshellcmd($zxing_path) . ' ' . escapeshellarg($image_path) . ' 2>&1';
            $output = shell_exec($command);

            // Parse ZXing output
            if (preg_match('/Found (\d+) result/', $output, $matches)) {
                  // Extract the decoded text
                  $lines = explode("\n", $output);
                  foreach ($lines as $line) {
                        if (trim($line) && !preg_match('/^(Found|Parsed|Format)/', $line)) {
                              return trim($line);
                        }
                  }
            }
      }

      // Note: For production use, you would need to install a proper QR code decoder
      // such as ZXing command line tool or use a PHP QR code library
      // For now, this function returns false and relies on frontend processing

      return false;
}

// Helper function to decode barcode from image
function decodeBarcodeFromImage($image_path)
{
      // Check if ZXing library is available via command line
      $zxing_path = '/usr/local/bin/zxing'; // Common installation path

      if (file_exists($zxing_path) && is_executable($zxing_path)) {
            $command = escapeshellcmd($zxing_path) . ' ' . escapeshellarg($image_path) . ' 2>&1';
            $output = shell_exec($command);

            // Parse ZXing output
            if (preg_match('/Found (\d+) result/', $output, $matches)) {
                  // Extract the decoded text
                  $lines = explode("\n", $output);
                  foreach ($lines as $line) {
                        if (trim($line) && !preg_match('/^(Found|Parsed|Format)/', $line)) {
                              return trim($line);
                        }
                  }
            }
      }

      // Note: For production use, you would need to install a proper barcode decoder
      // such as ZXing command line tool or use a PHP barcode library
      // For now, this function returns false and relies on frontend processing

      return false;
}

// Helper function to get QR code information
function getQRCodeInfo($qr_data)
{
      if (strpos($qr_data, 'WIFI:') === 0) {
            preg_match('/S:([^;]+)/', $qr_data, $matches);
            $ssid = isset($matches[1]) ? $matches[1] : 'Unknown';
            return [
                  'type' => 'WiFi',
                  'title' => 'WiFi Network',
                  'data' => "Network: $ssid"
            ];
      } elseif (strpos($qr_data, 'BEGIN:VCARD') === 0) {
            preg_match('/FN:([^\n]+)/', $qr_data, $matches);
            $name = isset($matches[1]) ? $matches[1] : 'Unknown';
            return [
                  'type' => 'Contact',
                  'title' => 'Contact Card',
                  'data' => "Name: $name"
            ];
      } elseif (strpos($qr_data, 'mailto:') === 0) {
            $email = substr($qr_data, 7);
            $email = strtok($email, '?');
            return [
                  'type' => 'Email',
                  'title' => 'Email Address',
                  'data' => "Email: $email"
            ];
      } elseif (strpos($qr_data, 'sms:') === 0) {
            $phone = substr($qr_data, 4);
            $phone = strtok($phone, '?');
            return [
                  'type' => 'SMS',
                  'title' => 'SMS Message',
                  'data' => "Phone: $phone"
            ];
      } elseif (strpos($qr_data, 'tel:') === 0) {
            $phone = substr($qr_data, 4);
            return [
                  'type' => 'Phone',
                  'title' => 'Phone Number',
                  'data' => "Phone: $phone"
            ];
      } elseif (strpos($qr_data, 'http') === 0) {
            return [
                  'type' => 'URL',
                  'title' => 'Website URL',
                  'data' => "URL: $qr_data"
            ];
      } elseif (strpos($qr_data, '{"type":"product"') === 0) {
            try {
                  $data = json_decode($qr_data, true);
                  if ($data && isset($data['name'])) {
                        return [
                              'type' => 'Product',
                              'title' => 'Product Information',
                              'data' => "Product: {$data['name']}"
                        ];
                  }
            } catch (Exception $e) {
                  // Ignore JSON errors
            }
      }

      return [
            'type' => 'Text',
            'title' => 'Text Data',
            'data' => "Text: $qr_data"
      ];
}

// Helper function to get barcode information
function getBarcodeInfo($barcode_data)
{
      $code = trim($barcode_data);

      // EAN-13 (13 digits)
      if (preg_match('/^\d{13}$/', $code)) {
            return [
                  'type' => 'EAN-13',
                  'title' => 'EAN-13 Barcode',
                  'data' => "Barcode: $code"
            ];
      }

      // EAN-8 (8 digits)
      if (preg_match('/^\d{8}$/', $code)) {
            return [
                  'type' => 'EAN-8',
                  'title' => 'EAN-8 Barcode',
                  'data' => "Barcode: $code"
            ];
      }

      // UPC-A (12 digits)
      if (preg_match('/^\d{12}$/', $code)) {
            return [
                  'type' => 'UPC-A',
                  'title' => 'UPC-A Barcode',
                  'data' => "Barcode: $code"
            ];
      }

      // UPC-E (8 digits)
      if (preg_match('/^\d{8}$/', $code)) {
            return [
                  'type' => 'UPC-E',
                  'title' => 'UPC-E Barcode',
                  'data' => "Barcode: $code"
            ];
      }

      // Code 128 (alphanumeric)
      if (preg_match('/^[A-Za-z0-9]+$/', $code)) {
            return [
                  'type' => 'Code 128',
                  'title' => 'Code 128 Barcode',
                  'data' => "Barcode: $code"
            ];
      }

      // Code 39 (alphanumeric with asterisks)
      if (preg_match('/^[A-Z0-9\-\.\/\+\s\*]+$/', $code)) {
            return [
                  'type' => 'Code 39',
                  'title' => 'Code 39 Barcode',
                  'data' => "Barcode: $code"
            ];
      }

      return [
            'type' => 'Unknown',
            'title' => 'Unknown Barcode',
            'data' => "Barcode: $code"
      ];
}

// Enhanced search functions
function searchByBarcode($pdo, $barcode)
{
      // Clean barcode (remove spaces, dashes, etc.)
      $clean_barcode = preg_replace('/[^0-9A-Za-z]/', '', $barcode);

      // Search for exact barcode match
      $stmt = $pdo->prepare("SELECT * FROM products WHERE barcode = ? AND stock_quantity > 0");
      $stmt->execute([$clean_barcode]);
      $product = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($product) {
            return $product;
      }

      // Try with original barcode (with formatting)
      $stmt = $pdo->prepare("SELECT * FROM products WHERE barcode = ? AND stock_quantity > 0");
      $stmt->execute([$barcode]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
}

function searchByQRCode($pdo, $parsed_code, $original_code)
{
      // Search with parsed code first
      $stmt = $pdo->prepare("SELECT * FROM products WHERE (qr_code = ? OR barcode = ? OR product_code = ?) AND stock_quantity > 0");
      $stmt->execute([$parsed_code, $parsed_code, $parsed_code]);
      $product = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($product) {
            return $product;
      }

      // Try with original code
      $stmt = $pdo->prepare("SELECT * FROM products WHERE (qr_code = ? OR barcode = ? OR product_code = ?) AND stock_quantity > 0");
      $stmt->execute([$original_code, $original_code, $original_code]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
}

function searchByProductCode($pdo, $product_code)
{
      // Clean product code
      $clean_code = trim($product_code);

      $stmt = $pdo->prepare("SELECT * FROM products WHERE product_code = ? AND stock_quantity > 0");
      $stmt->execute([$clean_code]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
}

function searchByProductName($pdo, $name)
{
      // Search by product name (partial match)
      $search_term = '%' . trim($name) . '%';

      $stmt = $pdo->prepare("SELECT * FROM products WHERE name LIKE ? AND stock_quantity > 0 ORDER BY name ASC LIMIT 1");
      $stmt->execute([$search_term]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
}

function autoSearchProduct($pdo, $code)
{
      // Try different search methods in order of likelihood

      // 1. Try barcode search first
      $product = searchByBarcode($pdo, $code);
      if ($product) {
            return $product;
      }

      // 2. Try QR code search
      $parsed_code = parseQRCode($code);
      $product = searchByQRCode($pdo, $parsed_code, $code);
      if ($product) {
            return $product;
      }

      // 3. Try product code search
      $product = searchByProductCode($pdo, $code);
      if ($product) {
            return $product;
      }

      // 4. Try name search as last resort
      return searchByProductName($pdo, $code);
}
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
      <!-- QR Code Library -->
      <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
      <!-- QR Code Decoder Library -->
      <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
      <!-- Barcode Decoder Library -->
      <script src="https://cdn.jsdelivr.net/npm/@zxing/library@latest/umd/index.min.js"></script>
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
                                                                                    <i class="fas fa-barcode me-2"></i>Barcode & QR Code Scanner
                                                                              </h6>
                                                                        </div>
                                                                        <div class="card-body">
                                                                              <div class="row">
                                                                                    <div class="col-md-8">
                                                                                          <div class="mb-3">
                                                                                                <label for="scanner-input" class="form-label">
                                                                                                      <i class="fas fa-search me-1"></i>Search by Barcode, QR Code, or Product Code
                                                                                                </label>
                                                                                                <div class="input-group">
                                                                                                      <select class="form-select" id="search-type" style="max-width: 120px;">
                                                                                                            <option value="auto">Auto Detect</option>
                                                                                                            <option value="barcode">Barcode</option>
                                                                                                            <option value="qr">QR Code</option>
                                                                                                            <option value="product">Product Code</option>
                                                                                                            <option value="name">Product Name</option>
                                                                                                      </select>
                                                                                                      <input type="text"
                                                                                                            class="form-control"
                                                                                                            id="scanner-input"
                                                                                                            placeholder="Enter barcode, QR code, product code, or product name..."
                                                                                                            autocomplete="off">
                                                                                                      <button class="btn btn-outline-primary" type="button" onclick="scanCode()">
                                                                                                            <i class="fas fa-camera"></i> Scan
                                                                                                      </button>
                                                                                                      <button class="btn btn-outline-success" type="button" onclick="searchByCode()">
                                                                                                            <i class="fas fa-search"></i> Search
                                                                                                      </button>
                                                                                                </div>
                                                                                                <small class="text-muted">
                                                                                                      <i class="fas fa-info-circle me-1"></i>
                                                                                                      Supports: UPC, EAN, Code 128, Code 39, QR Codes, and product names
                                                                                                </small>
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

                                                                              <!-- Code Upload Section -->
                                                                              <div class="mt-3 pt-3 border-top">
                                                                                    <div class="row">
                                                                                          <div class="col-md-6">
                                                                                                <div class="mb-3">
                                                                                                      <label for="qr-upload" class="form-label">
                                                                                                            <i class="fas fa-qrcode me-2"></i>Upload QR Code Image
                                                                                                      </label>
                                                                                                      <div class="input-group">
                                                                                                            <input type="file"
                                                                                                                  class="form-control"
                                                                                                                  id="qr-upload"
                                                                                                                  accept="image/*"
                                                                                                                  onchange="handleQRUpload(event)">
                                                                                                            <button class="btn btn-outline-secondary" type="button" onclick="clearQRUpload()">
                                                                                                                  <i class="fas fa-times"></i> Clear
                                                                                                            </button>
                                                                                                      </div>
                                                                                                      <small class="text-muted">Upload a QR code image to automatically detect and add the product</small>
                                                                                                </div>
                                                                                          </div>
                                                                                          <div class="col-md-6">
                                                                                                <div class="mb-3">
                                                                                                      <label for="barcode-upload" class="form-label">
                                                                                                            <i class="fas fa-barcode me-2"></i>Upload Barcode Image
                                                                                                      </label>
                                                                                                      <div class="input-group">
                                                                                                            <input type="file"
                                                                                                                  class="form-control"
                                                                                                                  id="barcode-upload"
                                                                                                                  accept="image/*"
                                                                                                                  onchange="handleBarcodeUpload(event)">
                                                                                                            <button class="btn btn-outline-secondary" type="button" onclick="clearBarcodeUpload()">
                                                                                                                  <i class="fas fa-times"></i> Clear
                                                                                                            </button>
                                                                                                      </div>
                                                                                                      <small class="text-muted">Upload a barcode image to automatically detect and add the product</small>
                                                                                                </div>
                                                                                          </div>
                                                                                    </div>

                                                                                    <!-- Upload Status and Preview -->
                                                                                    <div class="row">
                                                                                          <div class="col-md-6">
                                                                                                <div class="mb-3">
                                                                                                      <label class="form-label">QR Upload Status</label>
                                                                                                      <div class="d-flex align-items-center">
                                                                                                            <div id="qr-upload-status" class="badge bg-secondary me-2">Ready</div>
                                                                                                            <div id="qr-upload-preview" class="ms-2" style="display: none;">
                                                                                                                  <img id="qr-uploaded-image" src="" alt="Uploaded QR" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                                                                            </div>
                                                                                                      </div>
                                                                                                </div>
                                                                                          </div>
                                                                                          <div class="col-md-6">
                                                                                                <div class="mb-3">
                                                                                                      <label class="form-label">Barcode Upload Status</label>
                                                                                                      <div class="d-flex align-items-center">
                                                                                                            <div id="barcode-upload-status" class="badge bg-secondary me-2">Ready</div>
                                                                                                            <div id="barcode-upload-preview" class="ms-2" style="display: none;">
                                                                                                                  <img id="barcode-uploaded-image" src="" alt="Uploaded Barcode" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                                                                            </div>
                                                                                                      </div>
                                                                                                </div>
                                                                                          </div>
                                                                                    </div>

                                                                                    <!-- Upload Results -->
                                                                                    <div id="qr-upload-result" class="mt-2" style="display: none;">
                                                                                          <div class="alert alert-info">
                                                                                                <div class="d-flex align-items-center">
                                                                                                      <div class="flex-grow-1">
                                                                                                            <strong id="qr-upload-product-name"></strong>
                                                                                                            <br>
                                                                                                            <small id="qr-upload-product-details"></small>
                                                                                                      </div>
                                                                                                      <button class="btn btn-sm btn-success" onclick="addQRUploadedProduct()">
                                                                                                            <i class="fas fa-plus"></i> Add to Cart
                                                                                                      </button>
                                                                                                </div>
                                                                                          </div>
                                                                                    </div>

                                                                                    <div id="barcode-upload-result" class="mt-2" style="display: none;">
                                                                                          <div class="alert alert-info">
                                                                                                <div class="d-flex align-items-center">
                                                                                                      <div class="flex-grow-1">
                                                                                                            <strong id="barcode-upload-product-name"></strong>
                                                                                                            <br>
                                                                                                            <small id="barcode-upload-product-details"></small>
                                                                                                      </div>
                                                                                                      <button class="btn btn-sm btn-success" onclick="addBarcodeUploadedProduct()">
                                                                                                            <i class="fas fa-plus"></i> Add to Cart
                                                                                                      </button>
                                                                                                </div>
                                                                                          </div>
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

                        <!-- Product QR Code Modal -->
                        <div class="modal fade" id="productQRModal" tabindex="-1">
                              <div class="modal-dialog">
                                    <div class="modal-content">
                                          <div class="modal-header">
                                                <h5 class="modal-title">
                                                      <i class="fas fa-qrcode me-2"></i>Product QR Code
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                          </div>
                                          <div class="modal-body text-center">
                                                <h6 id="product-qr-name" class="mb-3"></h6>
                                                <div id="product-qr-display" class="mb-3"></div>
                                                <div class="alert alert-info">
                                                      <small>
                                                            <i class="fas fa-info-circle me-1"></i>
                                                            Scan this QR code to quickly add this product to cart
                                                      </small>
                                                </div>
                                          </div>
                                          <div class="modal-footer">
                                                <button type="button" class="btn btn-primary" onclick="downloadProductQR()">
                                                      <i class="fas fa-download me-2"></i>Download
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" onclick="printProductQR()">
                                                      <i class="fas fa-print me-2"></i>Print
                                                </button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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

                        function showProductQR(productId, qrData, productName) {
                              const modal = document.getElementById('productQRModal');
                              const nameElement = document.getElementById('product-qr-name');
                              const displayElement = document.getElementById('product-qr-display');

                              nameElement.textContent = productName;
                              displayElement.innerHTML = '';

                              // Generate QR code
                              QRCode.toCanvas(displayElement, qrData, {
                                    width: 200,
                                    color: {
                                          dark: '#000000',
                                          light: '#FFFFFF'
                                    }
                              }, function(error) {
                                    if (error) {
                                          console.error('QR Code generation error:', error);
                                          displayElement.innerHTML = '<p class="text-danger">Error generating QR code</p>';
                                    }
                              });

                              new bootstrap.Modal(modal).show();
                        }

                        function downloadProductQR() {
                              const canvas = document.querySelector('#product-qr-display canvas');
                              if (canvas) {
                                    const link = document.createElement('a');
                                    link.download = 'product-qr-code.png';
                                    link.href = canvas.toDataURL();
                                    link.click();
                              }
                        }

                        function printProductQR() {
                              const canvas = document.querySelector('#product-qr-display canvas');
                              const productName = document.getElementById('product-qr-name').textContent;

                              if (canvas) {
                                    const printWindow = window.open('', '_blank');
                                    printWindow.document.write(`
                                          <html>
                                                <head><title>Print Product QR Code</title></head>
                                                <body style="text-align: center; padding: 20px;">
                                                      <h3>${productName}</h3>
                                                      <img src="${canvas.toDataURL()}" style="max-width: 100%;">
                                                      <p><small>Scan to add to cart</small></p>
                                                </body>
                                          </html>
                                    `);
                                    printWindow.document.close();
                                    printWindow.print();
                              }
                        }

                        // Keyboard shortcuts
                        document.addEventListener('keydown', function(e) {
                              if (e.ctrlKey && e.key === 'p') {
                                    e.preventDefault();
                                    showPaymentModal();
                              }

                              // Enter key in scanner input
                              if (e.key === 'Enter' && document.activeElement.id === 'scanner-input') {
                                    e.preventDefault();
                                    searchByCode();
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
                              const searchType = document.getElementById('search-type').value;

                              if (!code) {
                                    alert('Please enter a code to search');
                                    return;
                              }

                              updateScannerStatus('Searching...', 'info');

                              const formData = new FormData();
                              formData.append('action', 'scan_code');
                              formData.append('code', code);
                              formData.append('search_type', searchType);

                              fetch('pos.php', {
                                          method: 'POST',
                                          body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                          if (data.success) {
                                                updateScannerStatus('Product found!', 'success');
                                                // Add product to cart
                                                addToCart(data.product.id);
                                                // Clear scanner input
                                                document.getElementById('scanner-input').value = '';
                                                // Show success message with search method
                                                const method = data.search_method ? ` (via ${data.search_method})` : '';
                                                showNotification(data.message + method, 'success');
                                          } else {
                                                // Check if it's a special QR code format
                                                const qrInfo = parseQRCodeInfo(code);
                                                if (qrInfo.type !== 'unknown') {
                                                      updateScannerStatus('Special QR detected', 'warning');
                                                      showNotification(`Detected ${qrInfo.type} QR code: ${qrInfo.data}`, 'info');
                                                } else {
                                                      updateScannerStatus('Product not found', 'danger');
                                                      // Show barcode format information if it's a barcode
                                                      const barcodeInfo = detectBarcodeFormat(code);
                                                      if (barcodeInfo.type !== 'Unknown') {
                                                            showNotification(`Detected ${barcodeInfo.type}: ${barcodeInfo.format}`, 'info');
                                                      } else {
                                                            showNotification(data.message, 'error');
                                                      }
                                                }
                                          }
                                    })
                                    .catch(error => {
                                          updateScannerStatus('Error', 'danger');
                                          showNotification('Error searching for product', 'error');
                                    });
                        }

                        function parseQRCodeInfo(code) {
                              // Parse different QR code formats and return info
                              if (code.startsWith('WIFI:')) {
                                    const ssidMatch = code.match(/S:([^;]+)/);
                                    const ssid = ssidMatch ? ssidMatch[1] : 'Unknown';
                                    return {
                                          type: 'WiFi',
                                          data: `Network: ${ssid}`
                                    };
                              } else if (code.startsWith('BEGIN:VCARD')) {
                                    const nameMatch = code.match(/FN:([^\n]+)/);
                                    const name = nameMatch ? nameMatch[1] : 'Unknown';
                                    return {
                                          type: 'Contact',
                                          data: `Name: ${name}`
                                    };
                              } else if (code.startsWith('mailto:')) {
                                    const email = code.substring(7).split('?')[0];
                                    return {
                                          type: 'Email',
                                          data: `Email: ${email}`
                                    };
                              } else if (code.startsWith('sms:')) {
                                    const phone = code.substring(4).split('?')[0];
                                    return {
                                          type: 'SMS',
                                          data: `Phone: ${phone}`
                                    };
                              } else if (code.startsWith('tel:')) {
                                    const phone = code.substring(4);
                                    return {
                                          type: 'Phone',
                                          data: `Phone: ${phone}`
                                    };
                              } else if (code.startsWith('http')) {
                                    return {
                                          type: 'URL',
                                          data: `URL: ${code}`
                                    };
                              } else if (code.startsWith('{"type":"product"')) {
                                    try {
                                          const data = JSON.parse(code);
                                          return {
                                                type: 'Product',
                                                data: `Product: ${data.name}`
                                          };
                                    } catch (e) {
                                          return {
                                                type: 'unknown',
                                                data: code
                                          };
                                    }
                              } else {
                                    return {
                                          type: 'unknown',
                                          data: code
                                    };
                              }
                        }

                        function parseBarcodeInfo(barcodeData) {
                              // Try to parse barcode data to determine type
                              const code = barcodeData.trim();

                              // EAN-13 (13 digits)
                              if (/^\d{13}$/.test(code)) {
                                    return {
                                          type: 'EAN-13',
                                          data: `Barcode: ${code}`
                                    };
                              }

                              // EAN-8 (8 digits)
                              if (/^\d{8}$/.test(code)) {
                                    return {
                                          type: 'EAN-8',
                                          data: `Barcode: ${code}`
                                    };
                              }

                              // UPC-A (12 digits)
                              if (/^\d{12}$/.test(code)) {
                                    return {
                                          type: 'UPC-A',
                                          data: `Barcode: ${code}`
                                    };
                              }

                              // UPC-E (8 digits)
                              if (/^\d{8}$/.test(code)) {
                                    return {
                                          type: 'UPC-E',
                                          data: `Barcode: ${code}`
                                    };
                              }

                              // Code 128 (alphanumeric)
                              if (/^[A-Za-z0-9]+$/.test(code)) {
                                    return {
                                          type: 'Code 128',
                                          data: `Barcode: ${code}`
                                    };
                              }

                              // Code 39 (alphanumeric with asterisks)
                              if (/^[A-Z0-9\-\.\/\+\s\*]+$/.test(code)) {
                                    return {
                                          type: 'Code 39',
                                          data: `Barcode: ${code}`
                                    };
                              }

                              return {
                                    type: 'unknown',
                                    data: `Barcode: ${code}`
                              };
                        }

                        function detectBarcodeFormat(code) {
                              // Remove non-alphanumeric characters for analysis
                              const cleanCode = code.replace(/[^0-9A-Za-z]/g, '');

                              // UPC-A: 12 digits
                              if (/^\d{12}$/.test(cleanCode)) {
                                    return {
                                          type: 'UPC-A',
                                          format: '12-digit product code'
                                    };
                              }

                              // UPC-E: 8 digits
                              if (/^\d{8}$/.test(cleanCode)) {
                                    return {
                                          type: 'UPC-E',
                                          format: '8-digit compressed UPC'
                                    };
                              }

                              // EAN-13: 13 digits
                              if (/^\d{13}$/.test(cleanCode)) {
                                    return {
                                          type: 'EAN-13',
                                          format: '13-digit international product code'
                                    };
                              }

                              // EAN-8: 8 digits
                              if (/^\d{8}$/.test(cleanCode)) {
                                    return {
                                          type: 'EAN-8',
                                          format: '8-digit international product code'
                                    };
                              }

                              // Code 128: Variable length alphanumeric
                              if (/^[A-Za-z0-9]+$/.test(cleanCode) && cleanCode.length >= 4) {
                                    return {
                                          type: 'Code 128',
                                          format: 'Alphanumeric code'
                                    };
                              }

                              // Code 39: Variable length alphanumeric with asterisks
                              if (code.includes('*') && /^[A-Z0-9*\-\.\/\+\s]+$/i.test(code)) {
                                    return {
                                          type: 'Code 39',
                                          format: 'Alphanumeric code with asterisks'
                                    };
                              }

                              // ISBN: 10 or 13 digits (book codes)
                              if (/^\d{10}$/.test(cleanCode) || /^\d{13}$/.test(cleanCode)) {
                                    return {
                                          type: 'ISBN',
                                          format: 'Book identification code'
                                    };
                              }

                              // Generic numeric code
                              if (/^\d+$/.test(cleanCode)) {
                                    return {
                                          type: 'Numeric Code',
                                          format: 'Numeric product code'
                                    };
                              }

                              // Generic alphanumeric code
                              if (/^[A-Za-z0-9]+$/.test(cleanCode)) {
                                    return {
                                          type: 'Alphanumeric Code',
                                          format: 'Alphanumeric product code'
                                    };
                              }

                              return {
                                    type: 'Unknown',
                                    format: 'Unrecognized format'
                              };
                        }

                        // Code Upload Functions
                        let qrUploadedProduct = null;
                        let barcodeUploadedProduct = null;

                        function handleQRUpload(event) {
                              const file = event.target.files[0];
                              if (!file) {
                                    clearQRUpload();
                                    return;
                              }

                              // Validate file type
                              if (!file.type.startsWith('image/')) {
                                    alert('Please select an image file');
                                    clearQRUpload();
                                    return;
                              }

                              // Validate file size (5MB limit)
                              if (file.size > 5 * 1024 * 1024) {
                                    alert('File too large. Maximum size is 5MB.');
                                    clearQRUpload();
                                    return;
                              }

                              updateQRUploadStatus('Processing...', 'warning');

                              // Show image preview
                              const reader = new FileReader();
                              reader.onload = function(e) {
                                    const preview = document.getElementById('qr-uploaded-image');
                                    preview.src = e.target.result;
                                    document.getElementById('qr-upload-preview').style.display = 'block';
                              };
                              reader.readAsDataURL(file);

                              // Process QR code using JavaScript decoder
                              const canvas = document.createElement('canvas');
                              const ctx = canvas.getContext('2d');
                              const img = new Image();

                              img.onload = function() {
                                    canvas.width = img.width;
                                    canvas.height = img.height;
                                    ctx.drawImage(img, 0, 0);

                                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                    const code = jsQR(imageData.data, imageData.width, imageData.height);

                                    if (code) {
                                          // QR code found, search for product
                                          searchProductFromQRCode(code.data);
                                    } else {
                                          updateQRUploadStatus('No QR Code Found', 'danger');
                                          showNotification('No QR code found in the uploaded image', 'error');
                                    }
                              };

                              img.src = URL.createObjectURL(file);
                        }

                        function handleBarcodeUpload(event) {
                              const file = event.target.files[0];
                              if (!file) {
                                    clearBarcodeUpload();
                                    return;
                              }

                              // Validate file type
                              if (!file.type.startsWith('image/')) {
                                    alert('Please select an image file');
                                    clearBarcodeUpload();
                                    return;
                              }

                              // Validate file size (5MB limit)
                              if (file.size > 5 * 1024 * 1024) {
                                    alert('File too large. Maximum size is 5MB.');
                                    clearBarcodeUpload();
                                    return;
                              }

                              updateBarcodeUploadStatus('Processing...', 'warning');

                              // Show image preview
                              const reader = new FileReader();
                              reader.onload = function(e) {
                                    const preview = document.getElementById('barcode-uploaded-image');
                                    preview.src = e.target.result;
                                    document.getElementById('barcode-upload-preview').style.display = 'block';
                              };
                              reader.readAsDataURL(file);

                              // Process barcode using ZXing decoder
                              const canvas = document.createElement('canvas');
                              const ctx = canvas.getContext('2d');
                              const img = new Image();

                              img.onload = function() {
                                    canvas.width = img.width;
                                    canvas.height = img.height;
                                    ctx.drawImage(img, 0, 0);

                                    // Use ZXing to decode barcode
                                    const codeReader = new ZXing.BrowserMultiFormatReader();
                                    codeReader.decodeFromCanvas(canvas)
                                          .then(result => {
                                                // Barcode found, search for product
                                                searchProductFromBarcode(result.text);
                                          })
                                          .catch(error => {
                                                updateBarcodeUploadStatus('No Barcode Found', 'danger');
                                                showNotification('No barcode found in the uploaded image', 'error');
                                          });
                              };

                              img.src = URL.createObjectURL(file);
                        }

                        function searchProductFromQRCode(qrData) {
                              updateQRUploadStatus('Searching Product...', 'info');

                              const formData = new FormData();
                              formData.append('action', 'scan_code');
                              formData.append('code', qrData);
                              formData.append('search_type', 'qr');

                              fetch('pos.php', {
                                          method: 'POST',
                                          body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                          if (data.success) {
                                                updateQRUploadStatus('Product Found!', 'success');
                                                qrUploadedProduct = data.product;
                                                showQRUploadResult(data.product, qrData);
                                          } else {
                                                updateQRUploadStatus('No Product Found', 'danger');
                                                const qrInfo = parseQRCodeInfo(qrData);
                                                if (qrInfo.type !== 'unknown') {
                                                      showQRUploadResult(null, qrData, {
                                                            type: qrInfo.type,
                                                            title: qrInfo.type + ' QR Code',
                                                            data: qrInfo.data
                                                      });
                                                } else {
                                                      showNotification('Product not found for this QR code', 'error');
                                                }
                                          }
                                    })
                                    .catch(error => {
                                          console.error('Search error:', error);
                                          updateQRUploadStatus('Search Error', 'danger');
                                          showNotification('Error searching for product', 'error');
                                    });
                        }

                        function searchProductFromBarcode(barcodeData) {
                              updateBarcodeUploadStatus('Searching Product...', 'info');

                              const formData = new FormData();
                              formData.append('action', 'scan_code');
                              formData.append('code', barcodeData);
                              formData.append('search_type', 'barcode');

                              fetch('pos.php', {
                                          method: 'POST',
                                          body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                          if (data.success) {
                                                updateBarcodeUploadStatus('Product Found!', 'success');
                                                barcodeUploadedProduct = data.product;
                                                showBarcodeUploadResult(data.product, barcodeData);
                                          } else {
                                                updateBarcodeUploadStatus('No Product Found', 'danger');
                                                const barcodeInfo = parseBarcodeInfo(barcodeData);
                                                if (barcodeInfo.type !== 'unknown') {
                                                      showBarcodeUploadResult(null, barcodeData, {
                                                            type: barcodeInfo.type,
                                                            title: barcodeInfo.type + ' Barcode',
                                                            data: barcodeInfo.data
                                                      });
                                                } else {
                                                      showNotification('Product not found for this barcode', 'error');
                                                }
                                          }
                                    })
                                    .catch(error => {
                                          console.error('Search error:', error);
                                          updateBarcodeUploadStatus('Search Error', 'danger');
                                          showNotification('Error searching for product', 'error');
                                    });
                        }

                        function clearQRUpload() {
                              document.getElementById('qr-upload').value = '';
                              document.getElementById('qr-upload-preview').style.display = 'none';
                              document.getElementById('qr-upload-result').style.display = 'none';
                              updateQRUploadStatus('Ready', 'secondary');
                              qrUploadedProduct = null;
                        }

                        function clearBarcodeUpload() {
                              document.getElementById('barcode-upload').value = '';
                              document.getElementById('barcode-upload-preview').style.display = 'none';
                              document.getElementById('barcode-upload-result').style.display = 'none';
                              updateBarcodeUploadStatus('Ready', 'secondary');
                              barcodeUploadedProduct = null;
                        }

                        function updateQRUploadStatus(message, type) {
                              const statusElement = document.getElementById('qr-upload-status');
                              statusElement.textContent = message;
                              statusElement.className = `badge bg-${type} me-2`;
                        }

                        function updateBarcodeUploadStatus(message, type) {
                              const statusElement = document.getElementById('barcode-upload-status');
                              statusElement.textContent = message;
                              statusElement.className = `badge bg-${type} me-2`;
                        }

                        function showQRUploadResult(product, qrData, qrInfo = null) {
                              const resultDiv = document.getElementById('qr-upload-result');
                              const productName = document.getElementById('qr-upload-product-name');
                              const productDetails = document.getElementById('qr-upload-product-details');

                              if (product) {
                                    productName.textContent = product.name;
                                    productDetails.textContent = `Code: ${product.product_code} | Price: $${product.price} | Stock: ${product.stock_quantity}`;
                                    resultDiv.style.display = 'block';
                              } else if (qrInfo) {
                                    productName.textContent = qrInfo.title;
                                    productDetails.textContent = qrInfo.data;
                                    resultDiv.style.display = 'block';
                              } else {
                                    resultDiv.style.display = 'none';
                              }
                        }

                        function showBarcodeUploadResult(product, barcodeData, barcodeInfo = null) {
                              const resultDiv = document.getElementById('barcode-upload-result');
                              const productName = document.getElementById('barcode-upload-product-name');
                              const productDetails = document.getElementById('barcode-upload-product-details');

                              if (product) {
                                    productName.textContent = product.name;
                                    productDetails.textContent = `Code: ${product.product_code} | Price: $${product.price} | Stock: ${product.stock_quantity}`;
                                    resultDiv.style.display = 'block';
                              } else if (barcodeInfo) {
                                    productName.textContent = barcodeInfo.title;
                                    productDetails.textContent = barcodeInfo.data;
                                    resultDiv.style.display = 'block';
                              } else {
                                    resultDiv.style.display = 'none';
                              }
                        }

                        function addQRUploadedProduct() {
                              if (qrUploadedProduct) {
                                    addToCart(qrUploadedProduct.id);
                                    clearQRUpload();
                                    showNotification(`Added ${qrUploadedProduct.name} to cart`, 'success');
                              }
                        }

                        function addBarcodeUploadedProduct() {
                              if (barcodeUploadedProduct) {
                                    addToCart(barcodeUploadedProduct.id);
                                    clearBarcodeUpload();
                                    showNotification(`Added ${barcodeUploadedProduct.name} to cart`, 'success');
                              }
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

                              setTimeout(() => {
                                    if (notification.parentNode) {
                                          notification.remove();
                                    }
                              }, 3000);
                        }

                        document.addEventListener('DOMContentLoaded', function() {
                              const scannerInput = document.getElementById('scanner-input');
                              if (scannerInput) {
                                    scannerInput.addEventListener('keypress', function(e) {
                                          if (e.key === 'Enter') {
                                                searchByCode();
                                          }
                                    });

                                    // Show barcode format as user types
                                    scannerInput.addEventListener('input', function(e) {
                                          const code = e.target.value.trim();
                                          if (code.length > 3) {
                                                const barcodeInfo = detectBarcodeFormat(code);
                                                if (barcodeInfo.type !== 'Unknown') {
                                                      updateScannerStatus(`${barcodeInfo.type} detected`, 'info');
                                                } else {
                                                      updateScannerStatus('Ready', 'secondary');
                                                }
                                          } else {
                                                updateScannerStatus('Ready', 'secondary');
                                          }
                                    });
                              }

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

                        document.addEventListener('click', function(e) {
                              if (e.target.closest('button[data-action="show-payment-modal"]')) {
                                    e.preventDefault();
                                    showPaymentModal();
                              }

                              if (e.target.closest('button[data-action="clear-cart"]')) {
                                    e.preventDefault();
                                    clearCart();
                              }
                        });
                  </script>
</body>

</html>

</html>