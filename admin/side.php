<!-- side.php: Admin Left Sidebar Navigation Include -->
<!-- Sidebar -->
<nav class="admin-sidebar" id="adminSidebar">
      <div class="sidebar-header">
            <a class="sidebar-brand" href="index.php">
                  <div class="brand-icon">
                        <i class="fas fa-store"></i>
                  </div>
                  <div class="brand-text">
                        <span class="brand-title">POS Admin</span>
                        <span class="brand-subtitle">Management System</span>
                  </div>
            </a>
            <button class="sidebar-toggle" id="sidebarToggle">
                  <i class="fas fa-bars"></i>
            </button>
      </div>

      <div class="sidebar-content">
            <div class="sidebar-section">
                  <h6 class="sidebar-section-title">MAIN NAVIGATION</h6>
                  <ul class="sidebar-nav">
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <span>Dashboard</span>
                              </a>
                        </li>
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>" href="products.php">
                                    <i class="fas fa-box"></i>
                                    <span>Products</span>
                              </a>
                        </li>
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : ''; ?>" href="sales.php">
                                    <i class="fas fa-chart-line"></i>
                                    <span>Sales</span>
                              </a>
                        </li>
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>" href="orders.php">
                                    <i class="fas fa-shopping-cart"></i>
                                    <span>Orders</span>
                              </a>
                        </li>
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'receipts.php' ? 'active' : ''; ?>" href="receipts.php">
                                    <i class="fas fa-receipt"></i>
                                    <span>Receipts</span>
                              </a>
                        </li>
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>" href="inventory.php">
                                    <i class="fas fa-warehouse"></i>
                                    <span>Inventory</span>
                              </a>
                        </li>
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'stock_movements.php' ? 'active' : ''; ?>" href="stock_movements.php">
                                    <i class="fas fa-exchange-alt"></i>
                                    <span>Stock Movements</span>
                              </a>
                        </li>
                  </ul>
            </div>

            <div class="sidebar-section">
                  <h6 class="sidebar-section-title">USER MANAGEMENT</h6>
                  <ul class="sidebar-nav">
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                                    <i class="fas fa-users"></i>
                                    <span>Users</span>
                              </a>
                        </li>
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'generate_qr.php' ? 'active' : ''; ?>" href="generate_qr.php">
                                    <i class="fas fa-qrcode"></i>
                                    <span>QR Codes</span>
                              </a>
                        </li>
                  </ul>
            </div>

            <div class="sidebar-section">
                  <h6 class="sidebar-section-title">FINANCIAL</h6>
                  <ul class="sidebar-nav">
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'accounting.php' ? 'active' : ''; ?>" href="accounting.php">
                                    <i class="fas fa-calculator"></i>
                                    <span>Accounting</span>
                              </a>
                        </li>
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'journal_entries.php' ? 'active' : ''; ?>" href="journal_entries.php">
                                    <i class="fas fa-book"></i>
                                    <span>Journal Entries</span>
                              </a>
                        </li>
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'active' : ''; ?>" href="expenses.php">
                                    <i class="fas fa-calendar"></i>
                                    <span>Expenses</span>
                              </a>
                        </li>
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'exchange_rates.php' ? 'active' : ''; ?>" href="exchange_rates.php">
                                    <i class="fas fa-exchange-alt"></i>
                                    <span>Exchange Rates</span>
                              </a>
                        </li>
                  </ul>
            </div>

            <div class="sidebar-section">
                  <h6 class="sidebar-section-title">PURCHASING</h6>
                  <ul class="sidebar-nav">
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'vendors.php' ? 'active' : ''; ?>" href="vendors.php">
                                    <i class="fas fa-truck"></i>
                                    <span>Vendors</span>
                              </a>
                        </li>
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'purchase_orders.php' ? 'active' : ''; ?>" href="purchase_orders.php">
                                    <i class="fas fa-shopping-cart"></i>
                                    <span>Purchase Orders</span>
                              </a>
                        </li>
                  </ul>
            </div>

            <div class="sidebar-section">
                  <h6 class="sidebar-section-title">REPORTS</h6>
                  <ul class="sidebar-nav">
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                                    <i class="fas fa-file-alt"></i>
                                    <span>Reports</span>
                              </a>
                        </li>
                  </ul>
            </div>
      </div>

      <div class="sidebar-footer">
            <div class="user-profile">
                  <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                  </div>
                  <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['username']; ?></div>
                        <div class="user-role">Administrator</div>
                  </div>
                  <div class="user-menu">
                        <div class="dropdown">
                              <button class="btn btn-link dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v"></i>
                              </button>
                              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                    <li>
                                          <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                              </ul>
                        </div>
                  </div>
            </div>
      </div>
</nav>