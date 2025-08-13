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
    <?php include 'includes/title.php'; ?>
</head>

<body>
    <?php include 'includes/header.php'; ?>
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
                    <div class="row g-4">
                        <?php foreach ($products as $product): ?>
                            <?php
                            $img_path = !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : 'images/placeholder.jpg';
                            $display_price = $product['discount_price'] ? $product['discount_price'] : $product['price'];
                            $has_discount = $product['discount_price'] && $product['discount_price'] < $product['price'];
                            ?>
                            <div class="col-lg-4 col-md-6 product-item-container">
                                <div class="card product-card h-100 position-relative">
                                    <div class="quantity-overlay" id="quantity-overlay-<?php echo $product['id']; ?>" style="display: none;">
                                        <div class="quantity-controls">
                                            <button class="quantity-btn" onclick="event.stopPropagation(); updateProductQuantity(<?php echo $product['id']; ?>, -1)">-</button>
                                            <span class="quantity-display" id="quantity-<?php echo $product['id']; ?>">1</span>
                                            <button class="quantity-btn" onclick="event.stopPropagation(); updateProductQuantity(<?php echo $product['id']; ?>, 1)">+</button>
                                        </div>
                                        <div class="quantity-actions">
                                            <button class="btn btn-sm btn-success" onclick="event.stopPropagation(); addToGuestCartWithQuantity(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-cart-plus"></i> Add to Cart
                                            </button>
                                            <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); hideQuantityOverlay(<?php echo $product['id']; ?>)">Cancel</button>
                                        </div>
                                    </div>
                                    <div class="product-actions">
                                        <button class="add-to-cart-btn" onclick="event.stopPropagation(); showQuantityOverlay(<?php echo $product['id']; ?>)"><i class="fas fa-plus"></i></button>
                                        <button class="view-details-btn" onclick="event.stopPropagation(); showProductDetailModal(<?php echo $product['id']; ?>)"><i class="fas fa-info-circle"></i></button>
                                    </div>
                                    <div class="product-card-click-area" onclick="handleProductCardClick(<?php echo $product['id']; ?>)">
                                        <div class="product-image-container">
                                            <img src="<?php echo $img_path; ?>" class="card-img-top product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                            <?php if ($has_discount): ?>
                                                <div class="discount-badge">
                                                    <span class="badge bg-danger">
                                                        <?php $discount_percent = round((($product['price'] - $product['discount_price']) / $product['price']) * 100);
                                                        echo $discount_percent . '% OFF'; ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body d-flex flex-column">
                                            <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            <p class="card-text text-muted small"><?php echo htmlspecialchars(substr($product['description'], 0, 60)) . (strlen($product['description']) > 60 ? '...' : ''); ?></p>
                                            <div class="mt-auto">
                                                <div class="price-container">
                                                    <?php if ($has_discount): ?>
                                                        <span class="original-price text-muted text-decoration-line-through">$<?php echo number_format($product['price'], 2); ?></span>
                                                    <?php endif; ?>
                                                    <span class="current-price fw-bold text-primary">$<?php echo number_format($display_price, 2); ?></span>
                                                </div>
                                                <div class="stock-info small text-muted mt-1"><i class="fas fa-box me-1"></i><?php echo $product['stock_quantity']; ?> in stock</div>
                                                <div class="category-badge mt-2">
                                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></span>
                                                    <?php if (isset($product['type'])): ?>
                                                        <span class="badge bg-info text-white ms-1"><?php echo htmlspecialchars($product['type']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (isset($product['type']) && $product['type'] === 'Clothes'): ?>
                                                    <div class="clothing-attributes mt-2">
                                                        <?php if (!empty($product['size'])): ?>
                                                            <span class="badge bg-warning text-dark me-1"><i class="fas fa-ruler me-1"></i><?php echo htmlspecialchars($product['size']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($product['color'])): ?>
                                                            <span class="badge bg-success text-white me-1"><i class="fas fa-palette me-1"></i><?php echo htmlspecialchars($product['color']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($product['material'])): ?>
                                                            <span class="badge bg-secondary text-white me-1"><i class="fas fa-tshirt me-1"></i><?php echo htmlspecialchars($product['material']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
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


    <?php include 'includes/footer.php'; ?>
</body>

</html>