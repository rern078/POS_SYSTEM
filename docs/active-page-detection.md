# Active Page Detection System

## Overview

The active page detection system has been implemented to provide visual feedback to users about which page or section they are currently viewing in the navigation menu.

## Implementation Details

### 1. PHP Backend Detection (`includes/header.php`)

The system uses a PHP function `isActivePage()` to determine the current page:

```php
function isActivePage($page_name) {
    $current_page = basename($_SERVER['PHP_SELF']);
    $current_url = $_SERVER['REQUEST_URI'];
    $query_string = $_SERVER['QUERY_STRING'] ?? '';
    
    switch ($page_name) {
        case 'home':
            return $current_page === 'index.php' && empty($query_string);
        case 'products':
            return $current_page === 'product-list.php' || 
                   strpos($current_url, 'product-list.php') !== false ||
                   strpos($current_url, 'products') !== false;
        case 'features':
            return strpos($current_url, '#features') !== false;
        case 'about':
            return strpos($current_url, '#about') !== false;
        case 'contact':
            return strpos($current_url, '#contact') !== false;
        default:
            return false;
    }
}
```

### 2. Navigation Links

Each navigation link now includes the active class condition:

```php
<a class="nav-link <?php echo isActivePage('home') ? 'active' : ''; ?>" href="/">Home</a>
<a class="nav-link <?php echo isActivePage('features') ? 'active' : ''; ?>" href="#features">Features</a>
<a class="nav-link dropdown-toggle <?php echo isActivePage('products') ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">Products</a>
<a class="nav-link <?php echo isActivePage('about') ? 'active' : ''; ?>" href="#about">About</a>
<a class="nav-link <?php echo isActivePage('contact') ? 'active' : ''; ?>" href="#contact">Contact</a>
```

### 3. CSS Styling (`assets/css/main.css`)

Active navigation links are styled with:

```css
.nav-link.active {
    color: var(--primary-color) !important;
    font-weight: 600;
    position: relative;
}

.nav-link.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 50%;
    transform: translateX(-50%);
    width: 30px;
    height: 3px;
    background: linear-gradient(90deg, var(--primary-color), var(--info-color));
    border-radius: 2px;
    animation: activeLinkGlow 2s ease-in-out infinite;
}
```

### 4. JavaScript Enhancement (`assets/js/main.js`)

JavaScript provides additional functionality:

- Dynamic active state updates for anchor links
- Smooth scrolling to sections
- Browser back/forward button support
- Page visibility change handling

## Supported Pages/Sections

1. **Home** (`/` or `/index.php`) - Main landing page
2. **Products** (`/product-list.php`) - Product listing page
3. **Features** (`#features`) - Features section anchor
4. **About** (`#about`) - About section anchor
5. **Contact** (`#contact`) - Contact section anchor

## Features

### Visual Indicators
- Active links are highlighted with primary color
- Animated underline with glow effect
- Font weight changes to bold
- Special styling for dropdown menus

### User Experience
- Smooth scrolling to sections
- Browser navigation support
- Responsive design
- Accessibility friendly

### Technical Features
- Server-side detection for initial page load
- Client-side updates for dynamic navigation
- Fallback handling for edge cases
- Debug functions for troubleshooting

## Usage

The system works automatically once implemented. No additional configuration is required.

### Testing Active States

1. Navigate to different pages to see active states
2. Use browser back/forward buttons
3. Click on anchor links to see smooth scrolling
4. Check dropdown menu active states

### Debug Information

To debug active page detection, open browser console and run:

```javascript
window.navigationUtils.debugPageInfo();
```

This will show current page information and active navigation links.

## Browser Compatibility

- Modern browsers with ES6+ support
- Bootstrap 5 compatibility
- Responsive design support
- Progressive enhancement approach

## Future Enhancements

Potential improvements:
- Support for more complex URL patterns
- Custom active states for different user roles
- Animation customization options
- Integration with analytics tracking
