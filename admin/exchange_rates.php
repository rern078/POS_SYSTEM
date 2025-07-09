<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/exchange_rate.php';

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || !isAdmin()) {
      header('Location: login.php');
      exit();
}

$exchangeRate = new ExchangeRate();
$currencies = $exchangeRate->getCurrencies();
$defaultCurrency = $exchangeRate->getDefaultCurrency();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                  case 'update_rate':
                        $baseCurrency = $_POST['base_currency'];
                        $targetCurrency = $_POST['target_currency'];
                        $rate = (float)$_POST['rate'];

                        if ($exchangeRate->updateExchangeRate($baseCurrency, $targetCurrency, $rate)) {
                              $success_message = "Exchange rate updated successfully!";
                        } else {
                              $error_message = "Failed to update exchange rate.";
                        }
                        break;

                  case 'fetch_rates':
                        if ($exchangeRate->fetchRealTimeRates()) {
                              $success_message = "Exchange rates updated from API successfully!";
                        } else {
                              $error_message = "Failed to fetch rates from API. Please check your API configuration.";
                        }
                        break;

                  case 'set_default_currency':
                        $currencyCode = $_POST['currency_code'];
                        $pdo = getDBConnection();

                        // Reset all currencies to not default
                        $stmt = $pdo->prepare("UPDATE currencies SET is_default = 0");
                        $stmt->execute();

                        // Set selected currency as default
                        $stmt = $pdo->prepare("UPDATE currencies SET is_default = 1 WHERE code = ?");
                        if ($stmt->execute([$currencyCode])) {
                              $success_message = "Default currency updated successfully!";
                              $defaultCurrency = $exchangeRate->getDefaultCurrency();
                        } else {
                              $error_message = "Failed to update default currency.";
                        }
                        break;
            }
      }
}

// Get current exchange rates
$currentRates = [];
foreach ($currencies as $currency) {
      if ($currency['code'] !== $defaultCurrency['code']) {
            $rate = $exchangeRate->getExchangeRate($defaultCurrency['code'], $currency['code']);
            $currentRates[$currency['code']] = $rate;
      }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Exchange Rates Management - Admin Dashboard</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Google Fonts -->
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <!-- Custom CSS -->
      <link rel="stylesheet" href="assets/css/admin.css">
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
                                                <li class="breadcrumb-item active" aria-current="page">Exchange Rates</li>
                                          </ol>
                                    </nav>
                              </div>
                        </div>
                        <div class="topbar-right">
                              <div class="topbar-actions">
                                    <button class="btn btn-modern btn-primary" onclick="fetchRatesFromAPI()">
                                          <i class="fas fa-sync-alt me-2"></i>Update from API
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
                                          <i class="fas fa-exchange-alt"></i>
                                          Exchange Rates Management
                                    </h1>
                                    <p class="text-muted mb-0">Manage currency exchange rates and default currency settings.</p>
                              </div>
                        </div>

                        <!-- Alert Messages -->
                        <?php if (isset($success_message)): ?>
                              <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                              </div>
                        <?php endif; ?>

                        <?php if (isset($error_message)): ?>
                              <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                              </div>
                        <?php endif; ?>

                        <!-- Default Currency Settings -->
                        <div class="row mb-4">
                              <div class="col-12">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-cog"></i>
                                                      Default Currency Settings
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <form method="POST" class="row align-items-end">
                                                      <input type="hidden" name="action" value="set_default_currency">
                                                      <div class="col-md-6">
                                                            <label for="default_currency" class="form-label">Default Currency</label>
                                                            <select class="form-select" id="default_currency" name="currency_code" required>
                                                                  <?php foreach ($currencies as $currency): ?>
                                                                        <option value="<?php echo $currency['code']; ?>"
                                                                              <?php echo $currency['is_default'] ? 'selected' : ''; ?>>
                                                                              <?php echo $currency['code']; ?> - <?php echo $currency['name']; ?> (<?php echo $currency['symbol']; ?>)
                                                                        </option>
                                                                  <?php endforeach; ?>
                                                            </select>
                                                      </div>
                                                      <div class="col-md-6">
                                                            <button type="submit" class="btn btn-modern btn-primary">
                                                                  <i class="fas fa-save me-2"></i>Update Default Currency
                                                            </button>
                                                      </div>
                                                </form>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Current Exchange Rates -->
                        <div class="row mb-4">
                              <div class="col-12">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-chart-line"></i>
                                                      Current Exchange Rates (Base: <?php echo $defaultCurrency['code']; ?>)
                                                </h6>
                                                <small class="text-muted">Last updated: <?php echo date('M d, Y H:i:s'); ?></small>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="row">
                                                      <?php foreach ($currencies as $currency): ?>
                                                            <?php if ($currency['code'] !== $defaultCurrency['code']): ?>
                                                                  <div class="col-xl-3 col-md-4 col-sm-6 mb-3">
                                                                        <div class="rate-card">
                                                                              <div class="rate-card-header">
                                                                                    <div class="rate-currency-info">
                                                                                          <h6 class="rate-currency-code"><?php echo $currency['code']; ?></h6>
                                                                                          <small class="text-muted"><?php echo $currency['name']; ?></small>
                                                                                    </div>
                                                                                    <div class="rate-currency-symbol">
                                                                                          <?php echo $currency['symbol']; ?>
                                                                                    </div>
                                                                              </div>
                                                                              <div class="rate-value">
                                                                                    <span class="rate-number"><?php echo number_format($currentRates[$currency['code']], 6); ?></span>
                                                                              </div>
                                                                              <div class="rate-actions">
                                                                                    <button class="btn btn-sm btn-outline-primary"
                                                                                          onclick="editRate('<?php echo $defaultCurrency['code']; ?>', '<?php echo $currency['code']; ?>', '<?php echo $currentRates[$currency['code']]; ?>')">
                                                                                          <i class="fas fa-edit"></i> Edit
                                                                                    </button>
                                                                              </div>
                                                                        </div>
                                                                  </div>
                                                            <?php endif; ?>
                                                      <?php endforeach; ?>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                        </div>

                        <!-- Exchange Rate History -->
                        <div class="row">
                              <div class="col-12">
                                    <div class="content-card">
                                          <div class="content-card-header">
                                                <h6 class="content-card-title">
                                                      <i class="fas fa-history"></i>
                                                      Exchange Rate History
                                                </h6>
                                          </div>
                                          <div class="content-card-body">
                                                <div class="row mb-3">
                                                      <div class="col-md-4">
                                                            <label for="history_base" class="form-label">Base Currency</label>
                                                            <select class="form-select" id="history_base">
                                                                  <?php foreach ($currencies as $currency): ?>
                                                                        <option value="<?php echo $currency['code']; ?>"><?php echo $currency['code']; ?></option>
                                                                  <?php endforeach; ?>
                                                            </select>
                                                      </div>
                                                      <div class="col-md-4">
                                                            <label for="history_target" class="form-label">Target Currency</label>
                                                            <select class="form-select" id="history_target">
                                                                  <?php foreach ($currencies as $currency): ?>
                                                                        <option value="<?php echo $currency['code']; ?>"><?php echo $currency['code']; ?></option>
                                                                  <?php endforeach; ?>
                                                            </select>
                                                      </div>
                                                      <div class="col-md-4">
                                                            <label for="history_days" class="form-label">Days</label>
                                                            <select class="form-select" id="history_days">
                                                                  <option value="7">Last 7 days</option>
                                                                  <option value="30" selected>Last 30 days</option>
                                                                  <option value="90">Last 90 days</option>
                                                            </select>
                                                      </div>
                                                </div>
                                                <div id="rateHistoryChart" style="height: 300px;">
                                                      <div class="text-center text-muted py-5">
                                                            <i class="fas fa-chart-line fa-3x mb-3"></i>
                                                            <p>Select currencies to view exchange rate history</p>
                                                      </div>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Edit Rate Modal -->
      <div class="modal fade" id="editRateModal" tabindex="-1">
            <div class="modal-dialog">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Edit Exchange Rate</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                              <input type="hidden" name="action" value="update_rate">
                              <input type="hidden" name="base_currency" id="edit_base_currency">
                              <input type="hidden" name="target_currency" id="edit_target_currency">
                              <div class="modal-body">
                                    <div class="mb-3">
                                          <label class="form-label">Currency Pair</label>
                                          <div class="form-control-plaintext" id="edit_currency_pair"></div>
                                    </div>
                                    <div class="mb-3">
                                          <label for="edit_rate" class="form-label">Exchange Rate</label>
                                          <input type="number" class="form-control" id="edit_rate" name="rate" step="0.000001" required>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Rate</button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Chart.js -->
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>

      <script>
            let rateHistoryChart = null;

            function editRate(baseCurrency, targetCurrency, currentRate) {
                  document.getElementById('edit_base_currency').value = baseCurrency;
                  document.getElementById('edit_target_currency').value = targetCurrency;
                  document.getElementById('edit_currency_pair').textContent = `${baseCurrency} → ${targetCurrency}`;
                  document.getElementById('edit_rate').value = currentRate;

                  const modal = new bootstrap.Modal(document.getElementById('editRateModal'));
                  modal.show();
            }

            function fetchRatesFromAPI() {
                  const form = document.createElement('form');
                  form.method = 'POST';
                  form.innerHTML = '<input type="hidden" name="action" value="fetch_rates">';
                  document.body.appendChild(form);
                  form.submit();
            }

            // Load rate history when selections change
            document.getElementById('history_base').addEventListener('change', loadRateHistory);
            document.getElementById('history_target').addEventListener('change', loadRateHistory);
            document.getElementById('history_days').addEventListener('change', loadRateHistory);

            function loadRateHistory() {
                  const baseCurrency = document.getElementById('history_base').value;
                  const targetCurrency = document.getElementById('history_target').value;
                  const days = document.getElementById('history_days').value;

                  if (baseCurrency === targetCurrency) {
                        return;
                  }

                  // Fetch rate history via AJAX
                  fetch(`api/exchange_rates.php?action=history&base=${baseCurrency}&target=${targetCurrency}&days=${days}`)
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    displayRateHistory(data.data);
                              } else {
                                    console.error('Failed to load rate history:', data.message);
                              }
                        })
                        .catch(error => {
                              console.error('Error loading rate history:', error);
                        });
            }

            function displayRateHistory(data) {
                  const ctx = document.getElementById('rateHistoryChart');

                  if (rateHistoryChart) {
                        rateHistoryChart.destroy();
                  }

                  if (data.length === 0) {
                        ctx.innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-chart-line fa-3x mb-3"></i><p>No historical data available</p></div>';
                        return;
                  }

                  ctx.innerHTML = '<canvas id="rateChart"></canvas>';
                  const canvas = document.getElementById('rateChart');

                  rateHistoryChart = new Chart(canvas, {
                        type: 'line',
                        data: {
                              labels: data.map(item => new Date(item.last_updated).toLocaleDateString()),
                              datasets: [{
                                    label: `Exchange Rate (${data[0].base_currency} → ${data[0].target_currency})`,
                                    data: data.map(item => item.rate),
                                    borderColor: '#4e73df',
                                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4
                              }]
                        },
                        options: {
                              responsive: true,
                              maintainAspectRatio: false,
                              plugins: {
                                    legend: {
                                          display: true
                                    }
                              },
                              scales: {
                                    y: {
                                          beginAtZero: false,
                                          grid: {
                                                color: 'rgba(0, 0, 0, 0.05)'
                                          }
                                    },
                                    x: {
                                          grid: {
                                                display: false
                                          }
                                    }
                              }
                        }
                  });
            }
      </script>
</body>

</html>