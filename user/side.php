<!-- side.php: User Left Sidebar Navigation Include -->
<!-- Sidebar -->
<nav class="admin-sidebar" id="adminSidebar">
      <div class="sidebar-header">
            <a class="sidebar-brand" href="index.php">
                  <div class="brand-icon">
                        <i class="fas fa-store"></i>
                  </div>
                  <div class="brand-text">
                        <span class="brand-title">POS System</span>
                        <span class="brand-subtitle">User Panel</span>
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
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'active' : ''; ?>" href="pos.php">
                                    <i class="fas fa-cash-register"></i>
                                    <span>POS</span>
                              </a>
                        </li>
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>" href="products.php">
                                    <i class="fas fa-box"></i>
                                    <span>Products</span>
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
                  </ul>
            </div>

            <?php if (isManager()): ?>
                  <div class="sidebar-section">
                        <h6 class="sidebar-section-title">MANAGEMENT</h6>
                        <ul class="sidebar-nav">
                              <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                                          <i class="fas fa-chart-bar"></i>
                                          <span>Reports</span>
                                    </a>
                              </li>
                              <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link" href="../admin/inventory.php">
                                          <i class="fas fa-warehouse"></i>
                                          <span>Inventory</span>
                                    </a>
                              </li>
                              <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link" href="../admin/stock_movements.php">
                                          <i class="fas fa-exchange-alt"></i>
                                          <span>Stock Movements</span>
                                    </a>
                              </li>
                        </ul>
                  </div>
            <?php endif; ?>

            <div class="sidebar-section">
                  <h6 class="sidebar-section-title">ACCOUNT</h6>
                  <ul class="sidebar-nav">
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                                    <i class="fas fa-user"></i>
                                    <span>Profile</span>
                              </a>
                        </li>
                        <li class="sidebar-nav-item">
                              <a class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                                    <i class="fas fa-cog"></i>
                                    <span>Settings</span>
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
                        <div class="user-role"><?php echo ucfirst($_SESSION['role']); ?></div>
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
                                    <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                              </ul>
                        </div>
                  </div>
            </div>
      </div>
</nav>