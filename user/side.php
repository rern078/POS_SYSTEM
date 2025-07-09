<!-- side.php: User Sidebar Navigation Include -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
      <a class="navbar-brand" href="index.php"><i class="fas fa-store"></i> POS User</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                  <?php if ($_SESSION['role'] === 'customer'): ?>
                        <!-- Customer Navigation -->
                        <li class="nav-item"><a class="nav-link" href="customer_dashboard.php"><i class="fas fa-user"></i> My Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="../index.php"><i class="fas fa-shopping-cart"></i> Shop</a></li>
                        <li class="nav-item"><a class="nav-link" href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
                        <li class="nav-item"><a class="nav-link" href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                  <?php else: ?>
                        <!-- Staff Navigation -->
                        <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="pos.php"><i class="fas fa-cash-register"></i> POS</a></li>
                        <li class="nav-item"><a class="nav-link" href="products.php"><i class="fas fa-box"></i> Products</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders.php"><i class="fas fa-shopping-basket"></i> Orders</a></li>
                        <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-file-alt"></i> Reports</a></li>
                  <?php endif; ?>
            </ul>
      </div>
</nav>
<!-- End User Sidebar -->