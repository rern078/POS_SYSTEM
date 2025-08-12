<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
      header('Location: ../login.php');
      exit();
}

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle QR code generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
      switch ($_POST['action']) {
            case 'generate_qr':
                  $product_id = intval($_POST['product_id']);
                  $qr_data = trim($_POST['qr_data']);
                  $qr_type = $_POST['qr_type'] ?? 'text';
                  $qr_size = intval($_POST['qr_size'] ?? 200);
                  $qr_color = $_POST['qr_color'] ?? '#000000';
                  $qr_bg_color = $_POST['qr_bg_color'] ?? '#FFFFFF';

                  if (empty($qr_data)) {
                        $error = 'QR code data is required';
                        break;
                  }

                  // Format QR data based on type
                  $formatted_data = formatQRData($qr_data, $qr_type);

                  // Update product with QR code
                  $stmt = $pdo->prepare("UPDATE products SET qr_code = ? WHERE id = ?");
                  if ($stmt->execute([$formatted_data, $product_id])) {
                        $message = 'QR code generated successfully!';
                  } else {
                        $error = 'Failed to generate QR code';
                  }
                  break;

            case 'generate_barcode':
                  $product_id = intval($_POST['product_id']);
                  $barcode_data = trim($_POST['barcode_data']);

                  if (empty($barcode_data)) {
                        $error = 'Barcode data is required';
                        break;
                  }

                  // Update product with barcode
                  $stmt = $pdo->prepare("UPDATE products SET barcode = ? WHERE id = ?");
                  if ($stmt->execute([$barcode_data, $product_id])) {
                        $message = 'Barcode generated successfully!';
                  } else {
                        $error = 'Failed to generate barcode';
                  }
                  break;

            case 'bulk_generate_qr':
                  $product_ids = $_POST['product_ids'] ?? [];
                  $qr_type = $_POST['qr_type'] ?? 'product';
                  $qr_size = intval($_POST['qr_size'] ?? 200);
                  $qr_color = $_POST['qr_color'] ?? '#000000';
                  $qr_bg_color = $_POST['qr_bg_color'] ?? '#FFFFFF';

                  if (empty($product_ids)) {
                        $error = 'No products selected';
                        break;
                  }

                  $success_count = 0;
                  foreach ($product_ids as $product_id) {
                        // Get product info
                        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                        $stmt->execute([$product_id]);
                        $product = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($product) {
                              // Generate QR data based on type
                              $qr_data = generateQRDataForProduct($product, $qr_type);
                              $formatted_data = formatQRData($qr_data, $qr_type);

                              // Update product
                              $stmt = $pdo->prepare("UPDATE products SET qr_code = ? WHERE id = ?");
                              if ($stmt->execute([$formatted_data, $product_id])) {
                                    $success_count++;
                              }
                        }
                  }

                  if ($success_count > 0) {
                        $message = "Successfully generated QR codes for {$success_count} products!";
                  } else {
                        $error = 'Failed to generate QR codes';
                  }
                  break;

            case 'generate_qr_template':
                  $template_type = $_POST['template_type'] ?? 'product';
                  $template_data = $_POST['template_data'] ?? [];

                  // Generate QR code based on template
                  $qr_data = generateTemplateQRData($template_type, $template_data);

                  echo json_encode(['success' => true, 'qr_data' => $qr_data]);
                  exit;
      }
}

// Helper functions
function formatQRData($data, $type)
{
      switch ($type) {
            case 'url':
                  return $data;
            case 'email':
                  return "mailto:" . $data;
            case 'phone':
                  return "tel:" . $data;
            case 'sms':
                  return "sms:" . $data;
            case 'wifi':
                  return $data; // Already formatted
            case 'vcard':
                  return $data; // Already formatted
            case 'product':
                  return "PRODUCT:" . $data;
            default:
                  return $data;
      }
}

function generateQRDataForProduct($product, $type)
{
      switch ($type) {
            case 'product':
                  return json_encode([
                        'type' => 'product',
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'code' => $product['product_code'],
                        'price' => $product['price']
                  ]);
            case 'url':
                  return "https://yourstore.com/product/" . $product['id'];
            case 'text':
            default:
                  return $product['product_code'];
      }
}

function generateTemplateQRData($type, $data)
{
      switch ($type) {
            case 'wifi':
                  $ssid = $data['ssid'] ?? '';
                  $password = $data['password'] ?? '';
                  $encryption = $data['encryption'] ?? 'WPA';
                  return "WIFI:T:{$encryption};S:{$ssid};P:{$password};;";
            case 'vcard':
                  $name = $data['name'] ?? '';
                  $phone = $data['phone'] ?? '';
                  $email = $data['email'] ?? '';
                  $company = $data['company'] ?? '';
                  return "BEGIN:VCARD\nVERSION:3.0\nFN:{$name}\nTEL:{$phone}\nEMAIL:{$email}\nORG:{$company}\nEND:VCARD";
            case 'email':
                  $email = $data['email'] ?? '';
                  $subject = $data['subject'] ?? '';
                  $body = $data['body'] ?? '';
                  return "mailto:{$email}?subject=" . urlencode($subject) . "&body=" . urlencode($body);
            case 'sms':
                  $phone = $data['phone'] ?? '';
                  $message = $data['message'] ?? '';
                  return "sms:{$phone}?body=" . urlencode($message);
            default:
                  return $data['text'] ?? '';
      }
}

// Get all products
$stmt = $pdo->prepare("SELECT * FROM products ORDER BY name ASC");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Generate QR Codes & Barcodes - POS Admin</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Google Fonts -->
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="assets/css/admin.css">

      <!-- QR Code Library -->
      <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
      <!-- Barcode Library -->
      <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
      <!-- Color Picker -->
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonwep/pickr@1.8.2/dist/themes/classic.min.css">
      <script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr@1.8.2/dist/pickr.min.js"></script>
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
                                                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                                                <li class="breadcrumb-item active" aria-current="page">QR Codes & Barcodes</li>
                                          </ol>
                                    </nav>
                              </div>
                        </div>
                        <div class="topbar-right">
                              <div class="topbar-actions">
                                    <button class="btn btn-primary" onclick="showBulkGenerateModal()">
                                          <i class="fas fa-qrcode me-2"></i>Bulk Generate
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="showTemplateModal()">
                                          <i class="fas fa-magic me-2"></i>QR Templates
                                    </button>
                              </div>
                        </div>
                  </nav>

                  <!-- Page Content -->
                  <div class="admin-content">
                        <!-- Page Header -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h1 class="content-card-title">
                                          <i class="fas fa-qrcode me-2"></i>Generate QR Codes & Barcodes
                                    </h1>
                                    <p class="text-muted mb-0">Create and manage QR codes and barcodes for your products to streamline inventory management and sales processes.</p>
                              </div>
                        </div>

                        <!-- Messages -->
                        <?php if ($message): ?>
                              <div class="alert alert-modern alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                              </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                              <div class="alert alert-modern alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                              </div>
                        <?php endif; ?>

                        <!-- Products Grid -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h6 class="content-card-title">
                                          <i class="fas fa-box me-2"></i>Products
                                    </h6>
                              </div>
                              <div class="content-card-body">
                                    <div class="row">
                                          <?php foreach ($products as $product): ?>
                                                <div class="col-md-6 col-lg-4 mb-4">
                                                      <div class="card h-100">
                                                            <div class="card-body">
                                                                  <div class="d-flex align-items-center mb-3">
                                                                        <?php if ($product['image_path']): ?>
                                                                              <img src="../<?php echo $product['image_path']; ?>"
                                                                                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                                                    class="me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                                                        <?php endif; ?>
                                                                        <div>
                                                                              <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                                              <small class="text-muted"><?php echo htmlspecialchars($product['product_code']); ?></small>
                                                                        </div>
                                                                  </div>

                                                                  <!-- QR Code Section -->
                                                                  <div class="mb-3">
                                                                        <h6 class="text-primary">
                                                                              <i class="fas fa-qrcode me-1"></i>QR Code
                                                                        </h6>
                                                                        <div class="mb-2">
                                                                              <select class="form-select form-select-sm mb-2" id="qr-type-<?php echo $product['id']; ?>">
                                                                                    <option value="text">Text</option>
                                                                                    <option value="product">Product Info</option>
                                                                                    <option value="url">URL</option>
                                                                                    <option value="email">Email</option>
                                                                                    <option value="phone">Phone</option>
                                                                                    <option value="sms">SMS</option>
                                                                                    <option value="wifi">WiFi</option>
                                                                                    <option value="vcard">Contact Card</option>
                                                                              </select>
                                                                              <input type="text"
                                                                                    class="form-control form-control-sm"
                                                                                    id="qr-input-<?php echo $product['id']; ?>"
                                                                                    placeholder="QR Code data"
                                                                                    value="<?php echo htmlspecialchars($product['qr_code'] ?? ''); ?>">
                                                                        </div>
                                                                        <div class="d-flex gap-2 mb-2">
                                                                              <button class="btn btn-sm btn-primary"
                                                                                    onclick="generateQR(<?php echo $product['id']; ?>)">
                                                                                    <i class="fas fa-qrcode"></i> Generate
                                                                              </button>
                                                                              <button class="btn btn-sm btn-outline-secondary"
                                                                                    onclick="showQRModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['qr_code'] ?? ''); ?>')">
                                                                                    <i class="fas fa-eye"></i> View
                                                                              </button>
                                                                              <button class="btn btn-sm btn-outline-info"
                                                                                    onclick="showQRCustomizeModal(<?php echo $product['id']; ?>)">
                                                                                    <i class="fas fa-palette"></i> Customize
                                                                              </button>
                                                                        </div>
                                                                        <div id="qr-preview-<?php echo $product['id']; ?>" class="mt-2 text-center"></div>
                                                                  </div>

                                                                  <!-- Barcode Section -->
                                                                  <div class="mb-3">
                                                                        <h6 class="text-success">
                                                                              <i class="fas fa-barcode me-1"></i>Barcode
                                                                        </h6>
                                                                        <div class="mb-2">
                                                                              <input type="text"
                                                                                    class="form-control form-control-sm"
                                                                                    id="barcode-input-<?php echo $product['id']; ?>"
                                                                                    placeholder="Barcode data"
                                                                                    value="<?php echo htmlspecialchars($product['barcode'] ?? ''); ?>">
                                                                        </div>
                                                                        <div class="d-flex gap-2">
                                                                              <button class="btn btn-sm btn-success"
                                                                                    onclick="generateBarcode(<?php echo $product['id']; ?>)">
                                                                                    <i class="fas fa-barcode"></i> Generate
                                                                              </button>
                                                                              <button class="btn btn-sm btn-outline-secondary"
                                                                                    onclick="showBarcodeModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['barcode'] ?? ''); ?>')">
                                                                                    <i class="fas fa-eye"></i> View
                                                                              </button>
                                                                        </div>
                                                                        <div id="barcode-preview-<?php echo $product['id']; ?>" class="mt-2 text-center"></div>
                                                                  </div>
                                                            </div>
                                                      </div>
                                                </div>
                                          <?php endforeach; ?>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- QR Code Modal -->
      <div class="modal fade" id="qrModal" tabindex="-1">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">QR Code Preview</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                              <div id="qr-modal-content"></div>
                              <div class="mt-3">
                                    <button class="btn btn-primary" onclick="downloadQR()">
                                          <i class="fas fa-download"></i> Download
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="printQR()">
                                          <i class="fas fa-print"></i> Print
                                    </button>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- QR Code Customize Modal -->
      <div class="modal fade" id="qrCustomizeModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Customize QR Code</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                              <div class="row">
                                    <div class="col-md-6">
                                          <h6>QR Code Settings</h6>
                                          <div class="mb-3">
                                                <label class="form-label">Size</label>
                                                <input type="range" class="form-range" id="qr-size-slider" min="100" max="400" value="200">
                                                <div class="text-center">
                                                      <span id="qr-size-value">200px</span>
                                                </div>
                                          </div>
                                          <div class="mb-3">
                                                <label class="form-label">Foreground Color</label>
                                                <input type="color" class="form-control form-control-color" id="qr-color-picker" value="#000000">
                                          </div>
                                          <div class="mb-3">
                                                <label class="form-label">Background Color</label>
                                                <input type="color" class="form-control form-control-color" id="qr-bg-color-picker" value="#FFFFFF">
                                          </div>
                                          <div class="mb-3">
                                                <label class="form-label">Error Correction Level</label>
                                                <select class="form-select" id="qr-error-correction">
                                                      <option value="L">Low (7%)</option>
                                                      <option value="M" selected>Medium (15%)</option>
                                                      <option value="Q">Quartile (25%)</option>
                                                      <option value="H">High (30%)</option>
                                                </select>
                                          </div>
                                    </div>
                                    <div class="col-md-6">
                                          <h6>Preview</h6>
                                          <div id="qr-customize-preview" class="text-center border p-3 rounded"></div>
                                          <div class="mt-3">
                                                <button class="btn btn-primary" onclick="applyQRCustomization()">
                                                      <i class="fas fa-check"></i> Apply
                                                </button>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bulk Generate Modal -->
      <div class="modal fade" id="bulkGenerateModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Bulk Generate QR Codes</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                              <div class="mb-3">
                                    <label class="form-label">Select Products</label>
                                    <div class="row">
                                          <?php foreach ($products as $product): ?>
                                                <div class="col-md-6 mb-2">
                                                      <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" value="<?php echo $product['id']; ?>" id="product-<?php echo $product['id']; ?>">
                                                            <label class="form-check-label" for="product-<?php echo $product['id']; ?>">
                                                                  <?php echo htmlspecialchars($product['name']); ?>
                                                            </label>
                                                      </div>
                                                </div>
                                          <?php endforeach; ?>
                                    </div>
                              </div>
                              <div class="mb-3">
                                    <label class="form-label">QR Code Type</label>
                                    <select class="form-select" id="bulk-qr-type">
                                          <option value="product">Product Info</option>
                                          <option value="text">Text</option>
                                          <option value="url">URL</option>
                                    </select>
                              </div>
                              <div class="mb-3">
                                    <button class="btn btn-outline-secondary" onclick="selectAllProducts()">Select All</button>
                                    <button class="btn btn-outline-secondary" onclick="deselectAllProducts()">Deselect All</button>
                              </div>
                        </div>
                        <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button type="button" class="btn btn-primary" onclick="bulkGenerateQR()">
                                    <i class="fas fa-qrcode"></i> Generate QR Codes
                              </button>
                        </div>
                  </div>
            </div>
      </div>

      <!-- QR Templates Modal -->
      <div class="modal fade" id="qrTemplatesModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">QR Code Templates</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                              <div class="row">
                                    <div class="col-md-6 mb-3">
                                          <div class="card">
                                                <div class="card-body">
                                                      <h6 class="card-title">WiFi Network</h6>
                                                      <div class="mb-2">
                                                            <input type="text" class="form-control form-control-sm" id="wifi-ssid" placeholder="Network Name (SSID)">
                                                      </div>
                                                      <div class="mb-2">
                                                            <input type="password" class="form-control form-control-sm" id="wifi-password" placeholder="Password">
                                                      </div>
                                                      <div class="mb-2">
                                                            <select class="form-select form-select-sm" id="wifi-encryption">
                                                                  <option value="WPA">WPA/WPA2</option>
                                                                  <option value="WEP">WEP</option>
                                                                  <option value="nopass">No Password</option>
                                                            </select>
                                                      </div>
                                                      <button class="btn btn-sm btn-primary" onclick="generateWiFiQR()">
                                                            <i class="fas fa-wifi"></i> Generate WiFi QR
                                                      </button>
                                                </div>
                                          </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                          <div class="card">
                                                <div class="card-body">
                                                      <h6 class="card-title">Contact Card (vCard)</h6>
                                                      <div class="mb-2">
                                                            <input type="text" class="form-control form-control-sm" id="vcard-name" placeholder="Full Name">
                                                      </div>
                                                      <div class="mb-2">
                                                            <input type="tel" class="form-control form-control-sm" id="vcard-phone" placeholder="Phone Number">
                                                      </div>
                                                      <div class="mb-2">
                                                            <input type="email" class="form-control form-control-sm" id="vcard-email" placeholder="Email">
                                                      </div>
                                                      <div class="mb-2">
                                                            <input type="text" class="form-control form-control-sm" id="vcard-company" placeholder="Company">
                                                      </div>
                                                      <button class="btn btn-sm btn-primary" onclick="generateVCardQR()">
                                                            <i class="fas fa-address-card"></i> Generate vCard QR
                                                      </button>
                                                </div>
                                          </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                          <div class="card">
                                                <div class="card-body">
                                                      <h6 class="card-title">Email</h6>
                                                      <div class="mb-2">
                                                            <input type="email" class="form-control form-control-sm" id="email-address" placeholder="Email Address">
                                                      </div>
                                                      <div class="mb-2">
                                                            <input type="text" class="form-control form-control-sm" id="email-subject" placeholder="Subject">
                                                      </div>
                                                      <div class="mb-2">
                                                            <textarea class="form-control form-control-sm" id="email-body" placeholder="Message Body" rows="2"></textarea>
                                                      </div>
                                                      <button class="btn btn-sm btn-primary" onclick="generateEmailQR()">
                                                            <i class="fas fa-envelope"></i> Generate Email QR
                                                      </button>
                                                </div>
                                          </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                          <div class="card">
                                                <div class="card-body">
                                                      <h6 class="card-title">SMS</h6>
                                                      <div class="mb-2">
                                                            <input type="tel" class="form-control form-control-sm" id="sms-phone" placeholder="Phone Number">
                                                      </div>
                                                      <div class="mb-2">
                                                            <textarea class="form-control form-control-sm" id="sms-message" placeholder="Message" rows="2"></textarea>
                                                      </div>
                                                      <button class="btn btn-sm btn-primary" onclick="generateSMSQR()">
                                                            <i class="fas fa-sms"></i> Generate SMS QR
                                                      </button>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                              <div class="mt-3">
                                    <h6>Generated QR Code</h6>
                                    <div id="template-qr-preview" class="text-center border p-3 rounded"></div>
                                    <div class="mt-2">
                                          <input type="text" class="form-control" id="template-qr-data" readonly>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Barcode Modal -->
      <div class="modal fade" id="barcodeModal" tabindex="-1">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Barcode Preview</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                              <div id="barcode-modal-content"></div>
                              <div class="mt-3">
                                    <button class="btn btn-primary" onclick="downloadBarcode()">
                                          <i class="fas fa-download"></i> Download
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="printBarcode()">
                                          <i class="fas fa-print"></i> Print
                                    </button>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

      <script>
            let currentProductId = null;
            let currentQRData = '';

            function generateQR(productId) {
                  const input = document.getElementById(`qr-input-${productId}`);
                  const typeSelect = document.getElementById(`qr-type-${productId}`);
                  const data = input.value.trim();
                  const type = typeSelect.value;

                  if (!data) {
                        alert('Please enter QR code data');
                        return;
                  }

                  // Generate QR code preview
                  const preview = document.getElementById(`qr-preview-${productId}`);
                  preview.innerHTML = '';

                  QRCode.toCanvas(preview, data, {
                        width: 100,
                        color: {
                              dark: '#000000',
                              light: '#FFFFFF'
                        }
                  }, function(error) {
                        if (error) {
                              console.error('QR Code generation error:', error);
                              alert('Error generating QR code');
                        }
                  });

                  // Save to database
                  const formData = new FormData();
                  formData.append('action', 'generate_qr');
                  formData.append('product_id', productId);
                  formData.append('qr_data', data);
                  formData.append('qr_type', type);

                  fetch('generate_qr.php', {
                              method: 'POST',
                              body: formData
                        })
                        .then(response => response.text())
                        .then(() => {
                              // Reload page to show success message
                              window.location.reload();
                        })
                        .catch(error => {
                              console.error('Error:', error);
                              alert('Error saving QR code');
                        });
            }

            function generateBarcode(productId) {
                  const input = document.getElementById(`barcode-input-${productId}`);
                  const data = input.value.trim();

                  if (!data) {
                        alert('Please enter barcode data');
                        return;
                  }

                  // Generate barcode preview
                  const preview = document.getElementById(`barcode-preview-${productId}`);
                  preview.innerHTML = `<svg id="barcode-${productId}"></svg>`;

                  JsBarcode(`#barcode-${productId}`, data, {
                        format: "CODE128",
                        width: 2,
                        height: 50,
                        displayValue: true
                  });

                  // Save to database
                  const formData = new FormData();
                  formData.append('action', 'generate_barcode');
                  formData.append('product_id', productId);
                  formData.append('barcode_data', data);

                  fetch('generate_qr.php', {
                              method: 'POST',
                              body: formData
                        })
                        .then(response => response.text())
                        .then(() => {
                              // Reload page to show success message
                              window.location.reload();
                        })
                        .catch(error => {
                              console.error('Error:', error);
                              alert('Error saving barcode');
                        });
            }

            function showQRModal(productId, qrData) {
                  if (!qrData) {
                        alert('No QR code data available');
                        return;
                  }

                  const modalContent = document.getElementById('qr-modal-content');
                  modalContent.innerHTML = '';

                  QRCode.toCanvas(modalContent, qrData, {
                        width: 200,
                        color: {
                              dark: '#000000',
                              light: '#FFFFFF'
                        }
                  }, function(error) {
                        if (error) {
                              console.error('QR Code generation error:', error);
                              modalContent.innerHTML = '<p class="text-danger">Error generating QR code</p>';
                        }
                  });

                  new bootstrap.Modal(document.getElementById('qrModal')).show();
            }

            function showQRCustomizeModal(productId) {
                  currentProductId = productId;
                  const input = document.getElementById(`qr-input-${productId}`);
                  currentQRData = input.value.trim();

                  if (!currentQRData) {
                        alert('Please generate a QR code first');
                        return;
                  }

                  // Initialize color pickers
                  const colorPicker = Pickr.create({
                        el: '#qr-color-picker',
                        theme: 'classic',
                        default: '#000000',
                        onChange: updateQRPreview
                  });

                  const bgColorPicker = Pickr.create({
                        el: '#qr-bg-color-picker',
                        theme: 'classic',
                        default: '#FFFFFF',
                        onChange: updateQRPreview
                  });

                  // Initialize size slider
                  const sizeSlider = document.getElementById('qr-size-slider');
                  const sizeValue = document.getElementById('qr-size-value');

                  sizeSlider.addEventListener('input', function() {
                        sizeValue.textContent = this.value + 'px';
                        updateQRPreview();
                  });

                  updateQRPreview();
                  new bootstrap.Modal(document.getElementById('qrCustomizeModal')).show();
            }

            function updateQRPreview() {
                  const size = document.getElementById('qr-size-slider').value;
                  const color = document.getElementById('qr-color-picker').value;
                  const bgColor = document.getElementById('qr-bg-color-picker').value;
                  const preview = document.getElementById('qr-customize-preview');

                  preview.innerHTML = '';

                  QRCode.toCanvas(preview, currentQRData, {
                        width: parseInt(size),
                        color: {
                              dark: color,
                              light: bgColor
                        }
                  }, function(error) {
                        if (error) {
                              console.error('QR Code generation error:', error);
                        }
                  });
            }

            function applyQRCustomization() {
                  // Here you would save the customized QR code
                  // For now, just close the modal
                  bootstrap.Modal.getInstance(document.getElementById('qrCustomizeModal')).hide();
                  alert('QR code customization applied!');
            }

            function showBarcodeModal(productId, barcodeData) {
                  if (!barcodeData) {
                        alert('No barcode data available');
                        return;
                  }

                  const modalContent = document.getElementById('barcode-modal-content');
                  modalContent.innerHTML = `<svg id="modal-barcode"></svg>`;

                  JsBarcode('#modal-barcode', barcodeData, {
                        format: "CODE128",
                        width: 3,
                        height: 100,
                        displayValue: true
                  });

                  new bootstrap.Modal(document.getElementById('barcodeModal')).show();
            }

            function showBulkGenerateModal() {
                  new bootstrap.Modal(document.getElementById('bulkGenerateModal')).show();
            }

            function showTemplateModal() {
                  new bootstrap.Modal(document.getElementById('qrTemplatesModal')).show();
            }

            function selectAllProducts() {
                  document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                        checkbox.checked = true;
                  });
            }

            function deselectAllProducts() {
                  document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                        checkbox.checked = false;
                  });
            }

            function bulkGenerateQR() {
                  const selectedProducts = Array.from(document.querySelectorAll('input[type="checkbox"]:checked'))
                        .map(checkbox => checkbox.value);

                  if (selectedProducts.length === 0) {
                        alert('Please select at least one product');
                        return;
                  }

                  const qrType = document.getElementById('bulk-qr-type').value;

                  const formData = new FormData();
                  formData.append('action', 'bulk_generate_qr');
                  formData.append('product_ids', JSON.stringify(selectedProducts));
                  formData.append('qr_type', qrType);

                  fetch('generate_qr.php', {
                              method: 'POST',
                              body: formData
                        })
                        .then(response => response.text())
                        .then(() => {
                              window.location.reload();
                        })
                        .catch(error => {
                              console.error('Error:', error);
                              alert('Error generating QR codes');
                        });
            }

            // Template QR Code Generation Functions
            function generateWiFiQR() {
                  const ssid = document.getElementById('wifi-ssid').value;
                  const password = document.getElementById('wifi-password').value;
                  const encryption = document.getElementById('wifi-encryption').value;

                  if (!ssid) {
                        alert('Please enter WiFi network name');
                        return;
                  }

                  const qrData = `WIFI:T:${encryption};S:${ssid};P:${password};;`;
                  displayTemplateQR(qrData);
            }

            function generateVCardQR() {
                  const name = document.getElementById('vcard-name').value;
                  const phone = document.getElementById('vcard-phone').value;
                  const email = document.getElementById('vcard-email').value;
                  const company = document.getElementById('vcard-company').value;

                  if (!name) {
                        alert('Please enter a name');
                        return;
                  }

                  const qrData = `BEGIN:VCARD\nVERSION:3.0\nFN:${name}\nTEL:${phone}\nEMAIL:${email}\nORG:${company}\nEND:VCARD`;
                  displayTemplateQR(qrData);
            }

            function generateEmailQR() {
                  const email = document.getElementById('email-address').value;
                  const subject = document.getElementById('email-subject').value;
                  const body = document.getElementById('email-body').value;

                  if (!email) {
                        alert('Please enter an email address');
                        return;
                  }

                  const qrData = `mailto:${email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
                  displayTemplateQR(qrData);
            }

            function generateSMSQR() {
                  const phone = document.getElementById('sms-phone').value;
                  const message = document.getElementById('sms-message').value;

                  if (!phone) {
                        alert('Please enter a phone number');
                        return;
                  }

                  const qrData = `sms:${phone}?body=${encodeURIComponent(message)}`;
                  displayTemplateQR(qrData);
            }

            function displayTemplateQR(qrData) {
                  const preview = document.getElementById('template-qr-preview');
                  const dataInput = document.getElementById('template-qr-data');

                  preview.innerHTML = '';
                  dataInput.value = qrData;

                  QRCode.toCanvas(preview, qrData, {
                        width: 200,
                        color: {
                              dark: '#000000',
                              light: '#FFFFFF'
                        }
                  }, function(error) {
                        if (error) {
                              console.error('QR Code generation error:', error);
                        }
                  });
            }

            function downloadQR() {
                  const canvas = document.querySelector('#qr-modal-content canvas');
                  if (canvas) {
                        const link = document.createElement('a');
                        link.download = 'qr-code.png';
                        link.href = canvas.toDataURL();
                        link.click();
                  }
            }

            function printQR() {
                  const canvas = document.querySelector('#qr-modal-content canvas');
                  if (canvas) {
                        const printWindow = window.open('', '_blank');
                        printWindow.document.write(`
                              <html>
                                    <head><title>Print QR Code</title></head>
                                    <body style="text-align: center; padding: 20px;">
                                          <img src="${canvas.toDataURL()}" style="max-width: 100%;">
                                    </body>
                              </html>
                        `);
                        printWindow.document.close();
                        printWindow.print();
                  }
            }

            function downloadBarcode() {
                  const svg = document.querySelector('#barcode-modal-content svg');
                  if (svg) {
                        const svgData = new XMLSerializer().serializeToString(svg);
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        const img = new Image();

                        img.onload = function() {
                              canvas.width = img.width;
                              canvas.height = img.height;
                              ctx.drawImage(img, 0, 0);

                              const link = document.createElement('a');
                              link.download = 'barcode.png';
                              link.href = canvas.toDataURL();
                              link.click();
                        };

                        img.src = 'data:image/svg+xml;base64,' + btoa(svgData);
                  }
            }

            function printBarcode() {
                  const svg = document.querySelector('#barcode-modal-content svg');
                  if (svg) {
                        const printWindow = window.open('', '_blank');
                        printWindow.document.write(`
                              <html>
                                    <head><title>Print Barcode</title></head>
                                    <body style="text-align: center; padding: 20px;">
                                          ${svg.outerHTML}
                                    </body>
                              </html>
                        `);
                        printWindow.document.close();
                        printWindow.print();
                  }
            }

            // Generate previews for existing codes on page load
            document.addEventListener('DOMContentLoaded', function() {
                  <?php foreach ($products as $product): ?>
                        <?php if (!empty($product['qr_code'])): ?>
                              const qrPreview<?php echo $product['id']; ?> = document.getElementById('qr-preview-<?php echo $product['id']; ?>');
                              if (qrPreview<?php echo $product['id']; ?>) {
                                    QRCode.toCanvas(qrPreview<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['qr_code']); ?>', {
                                          width: 100
                                    });
                              }
                        <?php endif; ?>

                        <?php if (!empty($product['barcode'])): ?>
                              const barcodePreview<?php echo $product['id']; ?> = document.getElementById('barcode-preview-<?php echo $product['id']; ?>');
                              if (barcodePreview<?php echo $product['id']; ?>) {
                                    barcodePreview<?php echo $product['id']; ?>.innerHTML = '<svg id="barcode-preview-<?php echo $product['id']; ?>-svg"></svg>';
                                    JsBarcode('#barcode-preview-<?php echo $product['id']; ?>-svg', '<?php echo htmlspecialchars($product['barcode']); ?>', {
                                          format: "CODE128",
                                          width: 1.5,
                                          height: 40,
                                          displayValue: true
                                    });
                              }
                        <?php endif; ?>
                  <?php endforeach; ?>
            });
      </script>
      <script src="assets/js/admin.js"></script>
</body>

</html>