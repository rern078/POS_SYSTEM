# Language System Documentation

## Overview

The POS system now supports multiple languages: English (en), Chinese (cn), Khmer (kh), and Vietnamese (vn). The language system is built with PHP and uses session-based language switching.

## File Structure

```
lang/
├── en/
│   └── translations.php
├── cn/
│   └── translations.php
├── kh/
│   └── translations.php
└── vn/
    └── translations.php
```

## How to Use

### 1. Including the Language System

Add this line to any PHP file where you want to use translations:

```php
include 'includes/language.php';
```

### 2. Using Translations

Use the `__()` function to translate text:

```php
echo __('home');           // Outputs: Home (en), 首页 (cn), ទំព័រដើម (kh), Trang chủ (vn)
echo __('shopping_cart');  // Outputs: Shopping Cart (en), 购物车 (cn), រទេះទិញទំនិញ (kh), Giỏ hàng (vn)
```

### 3. Language Switching

The language selector in the header automatically handles language switching. Users can click on flag icons to change the language:

```php
<a href="?lang=en" class="language-link">English</a>
<a href="?lang=cn" class="language-link">中文</a>
<a href="?lang=kh" class="language-link">ខ្មែរ</a>
<a href="?lang=vn" class="language-link">Tiếng Việt</a>
```

### 4. Getting Current Language

```php
$current_language = getCurrentLanguage(); // Returns: 'en', 'cn', 'kh', or 'vn'
```

### 5. Setting Language Programmatically

```php
setLanguage('cn'); // Sets language to Chinese
```

## Translation File Structure

Each language file (`translations.php`) contains an array of key-value pairs:

```php
<?php
return [
    // Navigation
    'home' => 'Home',
    'products' => 'Products',
    'about' => 'About',
    
    // Cart
    'shopping_cart' => 'Shopping Cart',
    'cart_empty' => 'Cart is empty',
    'total' => 'Total',
    
    // Add more translations...
];
?>
```

## Adding New Translations

### 1. Add to English File First

Always add new translation keys to the English file first (`lang/en/translations.php`):

```php
'new_key' => 'English Text',
```

### 2. Add to Other Languages

Then add the same key to all other language files with appropriate translations:

**Chinese (`lang/cn/translations.php`):**
```php
'new_key' => '中文文本',
```

**Khmer (`lang/kh/translations.php`):**
```php
'new_key' => 'អត្ថបទខ្មែរ',
```

**Vietnamese (`lang/vn/translations.php`):**
```php
'new_key' => 'Văn bản tiếng Việt',
```

### 3. Use in Your Code

```php
echo __('new_key');
```

## Fallback System

If a translation is not found in the current language, the system will:

1. First try to find it in English
2. If not found in English, return the key itself

This ensures the system never breaks if a translation is missing.

## Testing the Language System

Visit `language-test.php` to see all translations in action. This page demonstrates:

- Language switching
- Navigation translations
- Product category translations
- Payment method translations
- Status translations
- Common action translations

## CSS Styling

The language selector has built-in CSS styling in `assets/css/main.css`:

```css
.language-selector {
    display: flex;
    gap: 10px;
    align-items: center;
}

.language-link {
    display: inline-block;
    padding: 5px;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.language-link.active {
    background-color: rgba(255, 255, 255, 0.2);
    box-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
}
```

## Best Practices

1. **Always use the `__()` function** for user-facing text
2. **Add new translations to all languages** when adding new features
3. **Use descriptive keys** that make sense in context
4. **Group related translations** with comments in the translation files
5. **Test all languages** when making changes
6. **Keep translations consistent** across the application

## Example Implementation

Here's how to implement translations in a typical page:

```php
<?php
include 'includes/language.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">
<head>
    <title><?php echo __('page_title'); ?></title>
</head>
<body>
    <h1><?php echo __('welcome_message'); ?></h1>
    <p><?php echo __('description'); ?></p>
    
    <button><?php echo __('save'); ?></button>
    <button><?php echo __('cancel'); ?></button>
</body>
</html>
```

## Troubleshooting

### Translation Not Showing
- Check if the key exists in the translation file
- Verify the key spelling matches exactly
- Make sure the language file is properly formatted

### Language Not Switching
- Check if the session is working
- Verify the language code is valid
- Ensure the language file exists

### Missing Translations
- Add the missing key to all language files
- Use English as fallback for missing translations
- Check the browser console for any JavaScript errors
