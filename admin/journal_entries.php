<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || !isAdmin()) {
      header('Location: login.php');
      exit();
}

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                  case 'add_entry':
                        $entry_date = $_POST['entry_date'];
                        $reference = trim($_POST['reference']);
                        $description = trim($_POST['description']);
                        $entry_type = $_POST['entry_type'];
                        $accounts = $_POST['accounts'];
                        $debits = $_POST['debits'];
                        $credits = $_POST['credits'];
                        $descriptions = $_POST['line_descriptions'];

                        // Validate entry
                        $total_debit = array_sum(array_map('floatval', $debits));
                        $total_credit = array_sum(array_map('floatval', $credits));

                        if (abs($total_debit - $total_credit) > 0.01) {
                              $error = 'Total debits and credits must be equal. Difference: $' . number_format(abs($total_debit - $total_credit), 2);
                        } else {
                              try {
                                    $pdo->beginTransaction();

                                    // Generate entry number
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM journal_entries WHERE DATE(entry_date) = ?");
                                    $stmt->execute([$entry_date]);
                                    $count = $stmt->fetchColumn() + 1;
                                    $entry_number = 'JE-' . date('Ymd', strtotime($entry_date)) . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

                                    // Insert journal entry
                                    $stmt = $pdo->prepare("INSERT INTO journal_entries (entry_number, entry_date, reference, description, entry_type, total_debit, total_credit, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                    $stmt->execute([$entry_number, $entry_date, $reference, $description, $entry_type, $total_debit, $total_credit, $_SESSION['user_id']]);
                                    $entry_id = $pdo->lastInsertId();

                                    // Insert journal entry details
                                    $stmt = $pdo->prepare("INSERT INTO journal_entry_details (journal_entry_id, account_id, debit_amount, credit_amount, description) VALUES (?, ?, ?, ?, ?)");

                                    for ($i = 0; $i < count($accounts); $i++) {
                                          if (!empty($accounts[$i]) && (floatval($debits[$i]) > 0 || floatval($credits[$i]) > 0)) {
                                                $stmt->execute([
                                                      $entry_id,
                                                      $accounts[$i],
                                                      floatval($debits[$i]),
                                                      floatval($credits[$i]),
                                                      $descriptions[$i] ?? ''
                                                ]);
                                          }
                                    }

                                    $pdo->commit();
                                    $message = 'Journal entry created successfully!';
                              } catch (Exception $e) {
                                    $pdo->rollBack();
                                    $error = 'Error creating journal entry: ' . $e->getMessage();
                              }
                        }
                        break;

                  case 'delete_entry':
                        $entry_id = intval($_POST['entry_id']);

                        try {
                              $pdo->beginTransaction();

                              // Delete journal entry details first
                              $stmt = $pdo->prepare("DELETE FROM journal_entry_details WHERE journal_entry_id = ?");
                              $stmt->execute([$entry_id]);

                              // Delete journal entry
                              $stmt = $pdo->prepare("DELETE FROM journal_entries WHERE id = ?");
                              $stmt->execute([$entry_id]);

                              $pdo->commit();
                              $message = 'Journal entry deleted successfully!';
                        } catch (Exception $e) {
                              $pdo->rollBack();
                              $error = 'Error deleting journal entry: ' . $e->getMessage();
                        }
                        break;
            }
      }
}

// Get chart of accounts
$stmt = $pdo->prepare("SELECT * FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_code");
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get journal entries with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$entry_type = isset($_GET['entry_type']) ? $_GET['entry_type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
      $where_conditions[] = "(je.entry_number LIKE ? OR je.reference LIKE ? OR je.description LIKE ?)";
      $params[] = "%$search%";
      $params[] = "%$search%";
      $params[] = "%$search%";
}

if (!empty($entry_type)) {
      $where_conditions[] = "je.entry_type = ?";
      $params[] = $entry_type;
}

if (!empty($date_from)) {
      $where_conditions[] = "je.entry_date >= ?";
      $params[] = $date_from;
}

if (!empty($date_to)) {
      $where_conditions[] = "je.entry_date <= ?";
      $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) FROM journal_entries je $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_entries = $stmt->fetchColumn();
$total_pages = ceil($total_entries / $limit);

// Get journal entries
$sql = "SELECT je.*, u.username as created_by_name 
        FROM journal_entries je 
        LEFT JOIN users u ON je.created_by = u.id 
        $where_clause 
        ORDER BY je.entry_date DESC, je.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$journal_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <?php include 'include/title.inc.php'; ?>
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
                                                <li class="breadcrumb-item active" aria-current="page">Journal Entries</li>
                                          </ol>
                                    </nav>
                              </div>
                        </div>
                        <div class="topbar-right">
                              <div class="topbar-actions">
                                    <div class="dropdown">
                                          <button class="btn btn-link notification-btn" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-bell"></i>
                                                <span class="notification-badge">3</span>
                                          </button>
                                          <ul class="dropdown-menu dropdown-menu-end notification-menu" aria-labelledby="notificationDropdown">
                                                <li>
                                                      <h6 class="dropdown-header">Notifications</h6>
                                                </li>
                                                <li><a class="dropdown-item" href="#"><i class="fas fa-exclamation-circle text-warning me-2"></i>Low stock alert</a></li>
                                                <li><a class="dropdown-item" href="#"><i class="fas fa-check-circle text-success me-2"></i>Order completed</a></li>
                                                <li><a class="dropdown-item" href="#"><i class="fas fa-info-circle text-info me-2"></i>New user registered</a></li>
                                                <li>
                                                      <hr class="dropdown-divider">
                                                </li>
                                                <li><a class="dropdown-item text-center" href="#">View all notifications</a></li>
                                          </ul>
                                    </div>
                              </div>
                        </div>
                  </nav>

                  <!-- Page Content -->
                  <div class="admin-content">
                        <!-- Page Header -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                          <div>
                                                <h1 class="content-card-title">
                                                      <i class="fas fa-book"></i>
                                                      Journal Entries
                                                </h1>
                                                <p class="text-muted mb-0">Manage accounting journal entries</p>
                                          </div>
                                          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEntryModal">
                                                <i class="fas fa-plus me-2"></i>New Entry
                                          </button>
                                    </div>
                              </div>
                        </div>

                        <?php if ($message): ?>
                              <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                              <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <!-- Search and Filter -->
                        <div class="content-card">
                              <div class="content-card-body">
                                    <form method="GET" class="row g-3">
                                          <div class="col-md-3">
                                                <label for="search" class="form-label">Search</label>
                                                <input type="text" class="form-control" id="search" name="search"
                                                      value="<?php echo htmlspecialchars($search); ?>"
                                                      placeholder="Entry number, reference, description...">
                                          </div>
                                          <div class="col-md-2">
                                                <label for="entry_type" class="form-label">Entry Type</label>
                                                <select class="form-select" id="entry_type" name="entry_type">
                                                      <option value="">All Types</option>
                                                      <option value="sale" <?php echo $entry_type === 'sale' ? 'selected' : ''; ?>>Sale</option>
                                                      <option value="purchase" <?php echo $entry_type === 'purchase' ? 'selected' : ''; ?>>Purchase</option>
                                                      <option value="expense" <?php echo $entry_type === 'expense' ? 'selected' : ''; ?>>Expense</option>
                                                      <option value="adjustment" <?php echo $entry_type === 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                                                      <option value="transfer" <?php echo $entry_type === 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                                                </select>
                                          </div>
                                          <div class="col-md-2">
                                                <label for="date_from" class="form-label">From Date</label>
                                                <input type="date" class="form-control" id="date_from" name="date_from"
                                                      value="<?php echo htmlspecialchars($date_from); ?>">
                                          </div>
                                          <div class="col-md-2">
                                                <label for="date_to" class="form-label">To Date</label>
                                                <input type="date" class="form-control" id="date_to" name="date_to"
                                                      value="<?php echo htmlspecialchars($date_to); ?>">
                                          </div>
                                          <div class="col-md-3">
                                                <label class="form-label">&nbsp;</label>
                                                <div class="d-flex gap-2">
                                                      <button type="submit" class="btn btn-primary">
                                                            <i class="fas fa-search me-2"></i>Search
                                                      </button>
                                                      <a href="journal_entries.php" class="btn btn-outline-secondary">
                                                            <i class="fas fa-times me-2"></i>Clear
                                                      </a>
                                                </div>
                                          </div>
                                    </form>
                              </div>
                        </div>

                        <!-- Journal Entries Table -->
                        <div class="content-card">
                              <div class="content-card-header">
                                    <h6 class="content-card-title">Journal Entries</h6>
                              </div>
                              <div class="content-card-body">
                                    <div class="table-responsive">
                                          <table class="table table-bordered" id="journalEntriesTable" width="100%" cellspacing="0">
                                                <thead>
                                                      <tr>
                                                            <th>Date</th>
                                                            <th>Entry #</th>
                                                            <th>Reference</th>
                                                            <th>Description</th>
                                                            <th>Type</th>
                                                            <th>Debit</th>
                                                            <th>Credit</th>
                                                            <th>Created By</th>
                                                            <th>Actions</th>
                                                      </tr>
                                                </thead>
                                                <tbody>
                                                      <?php foreach ($journal_entries as $entry): ?>
                                                            <tr>
                                                                  <td><?php echo date('M d, Y', strtotime($entry['entry_date'])); ?></td>
                                                                  <td>
                                                                        <strong><?php echo htmlspecialchars($entry['entry_number']); ?></strong>
                                                                  </td>
                                                                  <td><?php echo htmlspecialchars($entry['reference'] ?? ''); ?></td>
                                                                  <td><?php echo htmlspecialchars(substr($entry['description'], 0, 50)) . (strlen($entry['description']) > 50 ? '...' : ''); ?></td>
                                                                  <td>
                                                                        <span class="badge bg-secondary">
                                                                              <?php echo ucfirst($entry['entry_type']); ?>
                                                                        </span>
                                                                  </td>
                                                                  <td class="text-end">$<?php echo number_format($entry['total_debit'], 2); ?></td>
                                                                  <td class="text-end">$<?php echo number_format($entry['total_credit'], 2); ?></td>
                                                                  <td><?php echo htmlspecialchars($entry['created_by_name']); ?></td>
                                                                  <td>
                                                                        <div class="btn-group btn-group-sm">
                                                                              <button class="btn btn-outline-primary" onclick="viewEntryDetails(<?php echo $entry['id']; ?>)">
                                                                                    <i class="fas fa-eye"></i>
                                                                              </button>
                                                                              <button class="btn btn-outline-danger" onclick="deleteEntry(<?php echo $entry['id']; ?>)">
                                                                                    <i class="fas fa-trash"></i>
                                                                              </button>
                                                                        </div>
                                                                  </td>
                                                            </tr>
                                                      <?php endforeach; ?>
                                                </tbody>
                                          </table>
                                    </div>

                                    <!-- Pagination -->
                                    <?php if ($total_pages > 1): ?>
                                          <div class="d-flex justify-content-between align-items-center mt-4">
                                                <div class="text-muted">
                                                      Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_entries); ?> of <?php echo $total_entries; ?> entries
                                                </div>
                                                <nav aria-label="Journal entries pagination">
                                                      <ul class="pagination mb-0">
                                                            <?php if ($page > 1): ?>
                                                                  <li class="page-item">
                                                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                                                  </li>
                                                            <?php endif; ?>

                                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                                  <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                                                  </li>
                                                            <?php endfor; ?>

                                                            <?php if ($page < $total_pages): ?>
                                                                  <li class="page-item">
                                                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                                                  </li>
                                                            <?php endif; ?>
                                                      </ul>
                                                </nav>
                                          </div>
                                    <?php endif; ?>
                              </div>
                        </div>
                  </div>
            </div>
      </div>

      <!-- Add Journal Entry Modal -->
      <div class="modal fade" id="addEntryModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">
                                    <i class="fas fa-plus me-2"></i>New Journal Entry
                              </h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                              <input type="hidden" name="action" value="add_entry">
                              <div class="modal-body">
                                    <div class="row mb-3">
                                          <div class="col-md-3">
                                                <label for="entry_date" class="form-label">Entry Date *</label>
                                                <input type="date" class="form-control" id="entry_date" name="entry_date"
                                                      value="<?php echo date('Y-m-d'); ?>" required>
                                          </div>
                                          <div class="col-md-3">
                                                <label for="entry_type" class="form-label">Entry Type *</label>
                                                <select class="form-select" id="entry_type" name="entry_type" required>
                                                      <option value="">Select Type</option>
                                                      <option value="sale">Sale</option>
                                                      <option value="purchase">Purchase</option>
                                                      <option value="expense">Expense</option>
                                                      <option value="adjustment">Adjustment</option>
                                                      <option value="transfer">Transfer</option>
                                                </select>
                                          </div>
                                          <div class="col-md-3">
                                                <label for="reference" class="form-label">Reference</label>
                                                <input type="text" class="form-control" id="reference" name="reference"
                                                      placeholder="Invoice #, PO #, etc.">
                                          </div>
                                          <div class="col-md-3">
                                                <label for="description" class="form-label">Description</label>
                                                <input type="text" class="form-control" id="description" name="description"
                                                      placeholder="Brief description">
                                          </div>
                                    </div>

                                    <div class="row mb-3">
                                          <div class="col-12">
                                                <h6>Journal Entry Lines</h6>
                                                <div id="entry-lines">
                                                      <div class="row entry-line mb-2">
                                                            <div class="col-md-4">
                                                                  <select class="form-select" name="accounts[]" required>
                                                                        <option value="">Select Account</option>
                                                                        <?php foreach ($accounts as $account): ?>
                                                                              <option value="<?php echo $account['id']; ?>">
                                                                                    <?php echo $account['account_code'] . ' - ' . $account['account_name']; ?>
                                                                              </option>
                                                                        <?php endforeach; ?>
                                                                  </select>
                                                            </div>
                                                            <div class="col-md-2">
                                                                  <input type="number" class="form-control" name="debits[]"
                                                                        placeholder="Debit" step="0.01" min="0">
                                                            </div>
                                                            <div class="col-md-2">
                                                                  <input type="number" class="form-control" name="credits[]"
                                                                        placeholder="Credit" step="0.01" min="0">
                                                            </div>
                                                            <div class="col-md-3">
                                                                  <input type="text" class="form-control" name="line_descriptions[]"
                                                                        placeholder="Line description">
                                                            </div>
                                                            <div class="col-md-1">
                                                                  <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeEntryLine(this)">
                                                                        <i class="fas fa-trash"></i>
                                                                  </button>
                                                            </div>
                                                      </div>
                                                </div>
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addEntryLine()">
                                                      <i class="fas fa-plus me-2"></i>Add Line
                                                </button>
                                          </div>
                                    </div>

                                    <div class="row">
                                          <div class="col-md-6">
                                                <div class="alert alert-info">
                                                      <strong>Total Debit: $<span id="total-debit">0.00</span></strong>
                                                </div>
                                          </div>
                                          <div class="col-md-6">
                                                <div class="alert alert-info">
                                                      <strong>Total Credit: $<span id="total-credit">0.00</span></strong>
                                                </div>
                                          </div>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                          <i class="fas fa-save me-2"></i>Save Entry
                                    </button>
                              </div>
                        </form>
                  </div>
            </div>
      </div>

      <!-- Entry Details Modal -->
      <div class="modal fade" id="entryDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title">Journal Entry Details</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="entry-details-content">
                              <!-- Content will be loaded here -->
                        </div>
                  </div>
            </div>
      </div>

      <!-- Delete Entry Form -->
      <form id="deleteEntryForm" method="POST" style="display: none;">
            <input type="hidden" name="action" value="delete_entry">
            <input type="hidden" name="entry_id" id="delete_entry_id">
      </form>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- Custom JS -->
      <script src="assets/js/admin.js"></script>

      <script>
            function addEntryLine() {
                  const container = document.getElementById('entry-lines');
                  const newLine = document.createElement('div');
                  newLine.className = 'row entry-line mb-2';
                  newLine.innerHTML = `
                        <div class="col-md-4">
                              <select class="form-select" name="accounts[]" required>
                                    <option value="">Select Account</option>
                                    <?php foreach ($accounts as $account): ?>
                                          <option value="<?php echo $account['id']; ?>">
                                                <?php echo $account['account_code'] . ' - ' . $account['account_name']; ?>
                                          </option>
                                    <?php endforeach; ?>
                              </select>
                        </div>
                        <div class="col-md-2">
                              <input type="number" class="form-control" name="debits[]" 
                                     placeholder="Debit" step="0.01" min="0" onchange="calculateTotals()">
                        </div>
                        <div class="col-md-2">
                              <input type="number" class="form-control" name="credits[]" 
                                     placeholder="Credit" step="0.01" min="0" onchange="calculateTotals()">
                        </div>
                        <div class="col-md-3">
                              <input type="text" class="form-control" name="line_descriptions[]" 
                                     placeholder="Line description">
                        </div>
                        <div class="col-md-1">
                              <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeEntryLine(this)">
                                    <i class="fas fa-trash"></i>
                              </button>
                        </div>
                  `;
                  container.appendChild(newLine);
            }

            function removeEntryLine(button) {
                  button.closest('.entry-line').remove();
                  calculateTotals();
            }

            function calculateTotals() {
                  let totalDebit = 0;
                  let totalCredit = 0;

                  document.querySelectorAll('input[name="debits[]"]').forEach(input => {
                        totalDebit += parseFloat(input.value) || 0;
                  });

                  document.querySelectorAll('input[name="credits[]"]').forEach(input => {
                        totalCredit += parseFloat(input.value) || 0;
                  });

                  document.getElementById('total-debit').textContent = totalDebit.toFixed(2);
                  document.getElementById('total-credit').textContent = totalCredit.toFixed(2);
            }

            function viewEntryDetails(entryId) {
                  // Load entry details via AJAX
                  fetch(`get_entry_details.php?id=${entryId}`)
                        .then(response => response.text())
                        .then(html => {
                              document.getElementById('entry-details-content').innerHTML = html;
                              new bootstrap.Modal(document.getElementById('entryDetailsModal')).show();
                        });
            }

            function deleteEntry(entryId) {
                  if (confirm('Are you sure you want to delete this journal entry? This action cannot be undone.')) {
                        document.getElementById('delete_entry_id').value = entryId;
                        document.getElementById('deleteEntryForm').submit();
                  }
            }

            // Add event listeners for total calculation
            document.addEventListener('DOMContentLoaded', function() {
                  document.addEventListener('input', function(e) {
                        if (e.target.name === 'debits[]' || e.target.name === 'credits[]') {
                              calculateTotals();
                        }
                  });
            });
      </script>
</body>

</html>