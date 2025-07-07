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

                  if (empty($qr_data)) {
                        $error = 'QR code data is required';
                        break;
                  }

                  // Update product with QR code
                  $stmt = $pdo->prepare("UPDATE products SET qr_code = ? WHERE id = ?");
                  if ($stmt->execute([$qr_data, $product_id])) {
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
      <!-- Custom CSS -->
      <link rel="stylesheet" href="assets/css/admin.css">

      <!-- QR Code Library -->
      <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
      <!-- Barcode Library -->
      <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
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
                                    <a class="nav-link active" href="generate_qr.php">
                                          <i class="fas fa-qrcode me-1"></i>QR Codes
                                    </a>
                              </li>
                        </ul>
                  </div>
            </div>
      </nav>

      <div class="container-fluid mt-4">
            <div class="row">
                  <div class="col-12">
                        <div class="card">
                              <div class="card-header">
                                    <h5 class="mb-0">
                                          <i class="fas fa-qrcode me-2"></i>Generate QR Codes & Barcodes
                                    </h5>
                              </div>
                              <div class="card-body">
                                    <?php if ($message): ?>
                                          <div class="alert alert-success"><?php echo $message; ?></div>
                                    <?php endif; ?>

                                    <?php if ($error): ?>
                                          <div class="alert alert-danger"><?php echo $error; ?></div>
                                    <?php endif; ?>

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
                                                                        <h6 class="text-primary">QR Code</h6>
                                                                        <div class="mb-2">
                                                                              <input type="text"
                                                                                    class="form-control form-control-sm"
                                                                                    id="qr-input-<?php echo $product['id']; ?>"
                                                                                    placeholder="QR Code data"
                                                                                    value="<?php echo htmlspecialchars($product['qr_code'] ?? ''); ?>">
                                                                        </div>
                                                                        <div class="d-flex gap-2">
                                                                              <button class="btn btn-sm btn-primary"
                                                                                    onclick="generateQR(<?php echo $product['id']; ?>)">
                                                                                    <i class="fas fa-qrcode"></i> Generate
                                                                              </button>
                                                                              <button class="btn btn-sm btn-outline-secondary"
                                                                                    onclick="showQRModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['qr_code'] ?? ''); ?>')">
                                                                                    <i class="fas fa-eye"></i> View
                                                                              </button>
                                                                        </div>
                                                                        <div id="qr-preview-<?php echo $product['id']; ?>" class="mt-2 text-center"></div>
                                                                  </div>

                                                                  <!-- Barcode Section -->
                                                                  <div class="mb-3">
                                                                        <h6 class="text-success">Barcode</h6>
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
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

      <script>
            function generateQR(productId) {
                  const input = document.getElementById(`qr-input-${productId}`);
                  const data = input.value.trim();

                  if (!data) {
                        alert('Please enter QR code data');
                        return;
                  }

                  // Generate QR code preview
                  const preview = document.getElementById(`qr-preview-${productId}`);
                  preview.innerHTML = '';

                  QRCode.toCanvas(preview, data, {
                        width: 100
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
                        width: 200
                  }, function(error) {
                        if (error) {
                              console.error('QR Code generation error:', error);
                              modalContent.innerHTML = '<p class="text-danger">Error generating QR code</p>';
                        }
                  });

                  new bootstrap.Modal(document.getElementById('qrModal')).show();
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

            function downloadQR() {
                  const canvas = document.querySelector('#qr-modal-content canvas');
                  if (canvas) {
                        const link = document.createElement('a');
                        link.download = 'qr-code.png';
                        link.href = canvas.toDataURL();
                        link.click();
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
</body>

</html>