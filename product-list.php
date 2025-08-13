<?php
session_start();
require_once 'config/database.php';
require_once 'includes/exchange_rate.php';

// Initialize exchange rate handler
$exchangeRate = new ExchangeRate();

// Get category filter from URL parameter
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get sorting parameter
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';

// Get page parameter for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$pdo = getDBConnection();

// Build query conditions
$where_conditions = ["stock_quantity > 0"];
$params = [];

// Add category filter
if (!empty($category_filter)) {
    if ($category_filter === 'Clothes') {
        $where_conditions[] = "type = 'Clothes'";
    } elseif ($category_filter === 'Food') {
        $where_conditions[] = "type = 'Food'";
    } else {
        $where_conditions[] = "category = ?";
        $params[] = $category_filter;
    }
}

// Add search filter
if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR description LIKE ? OR product_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM products WHERE $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_products = $stmt->fetchColumn();
$total_pages = ceil($total_products / $limit);

// Get products with pagination
$order_clause = match ($sort) {
    'price_low' => 'ORDER BY price ASC',
    'price_high' => 'ORDER BY price DESC',
    'name' => 'ORDER BY name ASC',
    'newest' => 'ORDER BY created_at DESC',
    default => 'ORDER BY name ASC'
};

$sql = "SELECT *, COALESCE(type, 'Food') as type FROM products WHERE $where_clause $order_clause LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for filter sidebar
$stmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE stock_quantity > 0 AND category IS NOT NULL ORDER BY category");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get page title based on category
$page_title = match ($category_filter) {
    'Clothes' => 'All Clothing Products',
    'Food' => 'All Food Products',
    '' => 'All Products',
    default => $category_filter . ' Products'
};
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - POS System</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Main CSS -->
    <link rel="stylesheet" href="assets/css/main.css">
    <!-- Cart CSS -->
    <link rel="stylesheet" href="assets/css/cart.css">

    <style>
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .product-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .product-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }

        .product-info {
            padding: 1rem;
        }

        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .product-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .product-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #007bff;
            margin-bottom: 0.5rem;
        }

        .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 0.9rem;
            margin-right: 0.5rem;
        }

        .product-badges {
            margin-top: 0.5rem;
        }

        .clothing-attributes {
            margin-top: 0.5rem;
        }

        .filter-sidebar {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            height: fit-content;
        }

        .sort-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 0.5rem;
        }

        .pagination {
            margin-top: 2rem;
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1rem;
        }

        .search-box {
            border-radius: 25px;
            border: 2px solid #e0e0e0;
            padding: 0.75rem 1rem;
            transition: border-color 0.3s ease;
        }

        .search-box:focus {
            border-color: #007bff;
            box-shadow: none;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-store text-primary me-2"></i>CH-FASHION
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Products
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="product-list.php">
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
                        <a class="nav-link" href="index.php#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#contact">Contact</a>
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
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary btn-custom ms-2" href="register.php">
                                <i class="fas fa-user-plus me-2"></i>Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

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

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($page_title); ?></li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h2 mb-3"><?php echo htmlspecialchars($page_title); ?></h1>
                <p class="text-muted">Showing <?php echo $total_products; ?> product<?php echo $total_products != 1 ? 's' : ''; ?></p>
            </div>
        </div>

        <div class="row">
            <!-- Filter Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="filter-sidebar">
                    <h5 class="mb-3">Filters</h5>

                    <!-- Search -->
                    <div class="mb-3">
                        <label for="search" class="form-label">Search Products</label>
                        <form method="GET" action="">
                            <?php if (!empty($category_filter)): ?>
                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                            <?php endif; ?>
                            <input type="text" class="form-control search-box" id="search" name="search"
                                placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                        </form>
                    </div>

                    <!-- Categories -->
                    <div class="mb-3">
                        <label class="form-label">Categories</label>
                        <div class="list-group list-group-flush">
                            <a href="product-list.php" class="list-group-item list-group-item-action <?php echo empty($category_filter) ? 'active' : ''; ?>">
                                All Products
                            </a>
                            <a href="product-list.php?category=Food" class="list-group-item list-group-item-action <?php echo $category_filter === 'Food' ? 'active' : ''; ?>">
                                <i class="fas fa-utensils me-2"></i>Food
                            </a>
                            <a href="product-list.php?category=Clothes" class="list-group-item list-group-item-action <?php echo $category_filter === 'Clothes' ? 'active' : ''; ?>">
                                <i class="fas fa-tshirt me-2"></i>All Clothing
                            </a>
                            <?php foreach ($categories as $cat): ?>
                                <?php if ($cat !== 'Food' && $cat !== 'Clothes'): ?>
                                    <a href="product-list.php?category=<?php echo urlencode($cat); ?>"
                                        class="list-group-item list-group-item-action <?php echo $category_filter === $cat ? 'active' : ''; ?>">
                                        <?php echo htmlspecialchars($cat); ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Sort -->
                    <div class="mb-3">
                        <label for="sort" class="form-label">Sort By</label>
                        <select class="form-select sort-select" id="sort" onchange="updateSort(this.value)">
                            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                            <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="col-lg-9">
                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4>No products found</h4>
                        <p class="text-muted">Try adjusting your search or filter criteria.</p>
                        <a href="product-list.php" class="btn btn-primary">View All Products</a>
                    </div>
                <?php else: ?>
                    <div class="product-grid">
                        <?php foreach ($products as $product): ?>
                            <?php
                            $img_path = !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : 'images/placeholder.jpg';
                            $display_price = $product['discount_price'] ? $product['discount_price'] : $product['price'];
                            $has_discount = $product['discount_price'] && $product['discount_price'] < $product['price'];
                            ?>
                            <div class="product-card">
                                <div class="position-relative">
                                    <img src="<?php echo $img_path; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                    <?php if ($has_discount): ?>
                                        <div class="position-absolute top-0 end-0 m-2">
                                            <span class="badge bg-danger">
                                                <?php
                                                $discount_percent = round((($product['price'] - $product['discount_price']) / $product['price']) * 100);
                                                echo $discount_percent . '% OFF';
                                                ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="product-info">
                                    <h5 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="product-description">
                                        <?php echo htmlspecialchars(substr($product['description'], 0, 80)) . (strlen($product['description']) > 80 ? '...' : ''); ?>
                                    </p>

                                    <div class="product-price">
                                        <?php if ($has_discount): ?>
                                            <span class="original-price">$<?php echo number_format($product['price'], 2); ?></span>
                                        <?php endif; ?>
                                        $<?php echo number_format($display_price, 2); ?>
                                    </div>

                                    <div class="product-badges">
                                        <span class="badge bg-light text-dark me-1">
                                            <?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?>
                                        </span>
                                        <?php if (isset($product['type'])): ?>
                                            <span class="badge bg-info text-white me-1">
                                                <?php echo htmlspecialchars($product['type']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (isset($product['type']) && $product['type'] === 'Clothes'): ?>
                                        <div class="clothing-attributes">
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

                                    <div class="mt-3">
                                        <button class="btn btn-primary btn-sm w-100" onclick="addToCart(<?php echo $product['id']; ?>)">
                                            <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Product pagination">
                            <ul class="pagination justify-content-center">
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
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Cart functions (simplified version)
        function openCart() {
            document.getElementById('cartSidebar').classList.add('active');
            document.getElementById('cartOverlay').classList.add('active');
        }

        function closeCart() {
            document.getElementById('cartSidebar').classList.remove('active');
            document.getElementById('cartOverlay').classList.remove('active');
        }

        function addToCart(productId) {
            // Add to cart functionality
            fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=add_to_cart&product_id=${productId}&quantity=1`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Product added to cart!');
                        // You can add more sophisticated cart update logic here
                    } else {
                        alert(data.message || 'Error adding product to cart');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error adding product to cart');
                });
        }

        function updateSort(sortValue) {
            const url = new URL(window.location);
            url.searchParams.set('sort', sortValue);
            url.searchParams.set('page', '1'); // Reset to first page when sorting
            window.location.href = url.toString();
        }

        // Auto-submit search form on input
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    </script>
</body>

</html>