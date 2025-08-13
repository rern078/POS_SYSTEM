# Include System Documentation

## Overview

The POS system now uses a modular include system to separate common elements (header, footer, navigation) from page-specific content. This makes the code more maintainable, reduces duplication, and follows the DRY (Don't Repeat Yourself) principle.

## Files Created

### 1. `includes/header.php`
Contains:
- HTML DOCTYPE and head section
- Meta tags and title
- CSS and JavaScript library includes
- Navigation bar
- Cart sidebar and overlay
- Session management

### 2. `includes/footer.php`
Contains:
- Bootstrap JavaScript
- Common JavaScript functions (cart, navigation)
- Closing HTML tags
- Page-specific JavaScript injection

### 3. `includes/page_title.php`
Contains:
- Page title and meta description variables
- Documentation and usage examples
- Default value handling

## Files Updated

### 4. `index.php`
- Refactored to use the include system
- Removed duplicate HTML structure
- Added page-specific variables
- Now uses `include 'includes/header.php'` and `include 'includes/footer.php'`

### 5. `product-list.php`
- Refactored to use the include system
- Removed duplicate HTML structure
- Added page-specific CSS and JavaScript
- Now uses `include 'includes/header.php'` and `include 'includes/footer.php'`

## How to Use

### Basic Usage

```php
<?php
// Set page-specific variables
$page_title = 'Your Page Title';
$page_description = 'Your page description for SEO';
$additional_css = '<style>/* Your CSS */</style>';
$additional_js = '<script>/* Your JavaScript */</script>';

// Include the header
include 'includes/header.php';
?>

<!-- Your page content goes here -->
<div class="container">
    <h1>Your Content</h1>
    <p>Your page content...</p>
</div>

<?php
// Include the footer
include 'includes/footer.php';
?>
```

### Advanced Usage

#### Setting Page Variables

```php
<?php
// Required variables
$page_title = 'Product List';
$page_description = 'Browse our complete collection of products';

// Optional variables
$additional_css = '
<style>
    .custom-class {
        background: #f0f0f0;
        padding: 1rem;
    }
</style>';

$additional_js = '
<script>
    // Page-specific JavaScript
    document.addEventListener("DOMContentLoaded", function() {
        console.log("Page loaded!");
    });
</script>';
?>
```

#### Using with Database Queries

```php
<?php
require_once 'config/database.php';

// Your database queries
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM products WHERE category = ?");
$stmt->execute(['Clothes']);
$products = $stmt->fetchAll();

// Set page variables based on data
$page_title = 'Clothing Products';
$page_description = 'Browse our ' . count($products) . ' clothing items';

// Include header
include 'includes/header.php';
?>

<!-- Display your data -->
<div class="container">
    <?php foreach ($products as $product): ?>
        <div class="product-card">
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
        </div>
    <?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>
```

## Benefits

### 1. **Code Reusability**
- Common elements are defined once and reused across all pages
- Changes to navigation or footer only need to be made in one place

### 2. **Maintainability**
- Easier to maintain and update common elements
- Reduced risk of inconsistencies between pages

### 3. **SEO Optimization**
- Consistent meta tags and page structure
- Easy to set page-specific titles and descriptions

### 4. **Performance**
- Reduced code duplication
- Faster development of new pages

### 5. **Consistency**
- All pages have the same navigation and footer
- Consistent user experience across the site

## Variables Available

### Required Variables
- `$page_title` - The title of the page (used in `<title>` tag)

### Optional Variables
- `$page_description` - Meta description for SEO
- `$additional_css` - Page-specific CSS (injected in `<head>`)
- `$additional_js` - Page-specific JavaScript (injected before closing `</body>`)

## Examples

### Simple Page
```php
<?php
$page_title = 'About Us';
include 'includes/header.php';
?>

<div class="container">
    <h1>About Our Company</h1>
    <p>We are a leading POS solution provider...</p>
</div>

<?php include 'includes/footer.php'; ?>
```

### Complex Page with Custom Styling
```php
<?php
$page_title = 'Product Dashboard';
$page_description = 'Manage your product inventory with our advanced dashboard';

$additional_css = '
<style>
    .dashboard-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 2rem;
    }
    
    .stat-number {
        font-size: 2.5rem;
        font-weight: bold;
    }
</style>';

$additional_js = '
<script>
    // Dashboard-specific functionality
    function updateStats() {
        fetch("api/stats.php")
            .then(response => response.json())
            .then(data => {
                document.getElementById("total-products").textContent = data.total;
            });
    }
    
    // Update stats every 30 seconds
    setInterval(updateStats, 30000);
</script>';

include 'includes/header.php';
?>

<div class="container">
    <div class="dashboard-card">
        <h2>Product Statistics</h2>
        <div class="stat-number" id="total-products">0</div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
```

## Migration Guide

### Converting Existing Pages

1. **Remove HTML head section** - Delete everything from `<!DOCTYPE html>` to `</head>`
2. **Remove navigation** - Delete the `<nav>` section
3. **Remove cart sidebar** - Delete the cart overlay and sidebar HTML
4. **Remove footer** - Delete everything from `<!-- Bootstrap JS -->` to `</html>`
5. **Add includes** - Add the include statements at the top and bottom
6. **Set variables** - Define `$page_title` and other variables before including header

### Before (Old Way)
```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Page</title>
    <!-- Lots of CSS and JS includes -->
</head>
<body>
    <!-- Navigation HTML -->
    <!-- Cart HTML -->
    
    <!-- Page content -->
    <div class="container">
        <h1>My Content</h1>
    </div>
    
    <!-- Bootstrap JS -->
    <!-- Other JS -->
</body>
</html>
```

### After (New Way)
```php
<?php
$page_title = 'My Page';
include 'includes/header.php';
?>

<div class="container">
    <h1>My Content</h1>
</div>

<?php include 'includes/footer.php'; ?>
```

## Best Practices

1. **Always set `$page_title`** - This is required for proper SEO
2. **Use descriptive page descriptions** - Help with search engine optimization
3. **Keep CSS and JS minimal** - Only add what's necessary for the specific page
4. **Use consistent naming** - Follow the established patterns
5. **Test thoroughly** - Ensure all functionality works after migration

## Troubleshooting

### Common Issues

1. **Page title not showing** - Make sure `$page_title` is set before including header
2. **CSS not loading** - Check that `$additional_css` is properly formatted as HTML
3. **JavaScript errors** - Ensure `$additional_js` is properly formatted as HTML
4. **Navigation not working** - Verify that the header include is working correctly

### Debug Mode

To debug include issues, you can temporarily add this to your page:

```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Your page code here
?>
```

## Future Enhancements

Potential improvements to consider:

1. **Template Engine** - Consider using a proper template engine like Twig
2. **Asset Management** - Implement CSS/JS minification and bundling
3. **Caching** - Add caching for common elements
4. **Modular Components** - Create more granular includes for specific components
