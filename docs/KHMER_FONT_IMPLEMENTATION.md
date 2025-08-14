# Khmer Font Implementation Guide

## Overview

This document explains the implementation of Khmer font support in the POS system to ensure proper rendering of Khmer text content.

## Font Implementation

### 1. Google Fonts Integration

The system now includes Google Fonts for Khmer text support:

```css
@import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@300;400;500;600;700&display=swap');
@import url('https://fonts.googleapis.com/css2?family=Battambang:wght@300;400;500;600;700&display=swap');
```

### 2. Font Classes

Three main CSS classes are available for Khmer text:

#### `.khmer-text` (Default)
- Uses Noto Sans Khmer as primary font
- Falls back to system Khmer fonts
- Best for general Khmer text content

```css
.khmer-text, 
[lang="km"], 
[lang="kh"] {
    font-family: 'Noto Sans Khmer', 'Khmer OS', 'Khmer OS System', 'Khmer OS Content', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
```

#### `.khmer-text-battambang`
- Uses Battambang font for traditional Khmer styling
- Good for decorative or traditional content

```css
.khmer-text-battambang {
    font-family: 'Battambang', 'Khmer OS Fasthand', 'Khmer OS Freehand', 'Noto Sans Khmer', sans-serif;
}
```

#### `.khmer-text-system`
- Uses system-installed Khmer fonts
- Good for performance when system fonts are available

```css
.khmer-text-system {
    font-family: 'Khmer OS', 'Khmer OS System', 'Khmer OS Content', 'Khmer OS Fasthand', 'Khmer OS Freehand', 'Khmer OS Metal Chrieng', 'Khmer OS Muol', 'Khmer OS Siemreap', 'Khmer OS Muol Light', 'Khmer OS Muol Pali', 'Khmer OS Takhie', 'Khmer OS Titling', sans-serif;
}
```

## Implementation in Header

The header.php file has been updated to include the `khmer-text` class on elements that display translated content:

### Cart Elements
```php
<h5 class="mb-0 khmer-text">
    <i class="fas fa-shopping-cart me-2"></i><?php echo __('shopping_cart'); ?>
</h5>
```

### Navigation Elements
```php
<a class="nav-link khmer-text <?php echo isActivePage('home') ? 'active' : ''; ?>" href="/">
    <?php echo __('home'); ?>
</a>
```

### Dropdown Menus
```php
<a class="dropdown-item khmer-text" href="#products">
    <i class="fas fa-th-large me-2"></i><?php echo __('all_products'); ?>
</a>
```

## Testing Khmer Fonts

### Test Page
A dedicated test page has been created: `khmer-font-test.php`

This page allows you to:
- Compare different Khmer fonts side by side
- Test the current language system with Khmer text
- View sample content in different font styles
- Test form elements with Khmer text

### How to Test

1. **Access the test page**: Navigate to `khmer-font-test.php`
2. **Switch languages**: Use the language selector to test Khmer vs English
3. **Compare fonts**: View the font comparison grid
4. **Check rendering**: Ensure Khmer characters display properly

## Font Fallback System

The font implementation includes a comprehensive fallback system:

1. **Primary**: Noto Sans Khmer (Google Fonts)
2. **System**: Khmer OS fonts (if installed)
3. **Generic**: System sans-serif fonts
4. **Final**: Inter font (default system font)

## Browser Compatibility

### Supported Browsers
- Chrome/Chromium (excellent Khmer support)
- Firefox (good Khmer support)
- Safari (good Khmer support)
- Edge (excellent Khmer support)

### Font Loading
- Google Fonts are loaded asynchronously
- System fonts are used as fallback during loading
- Progressive enhancement ensures text is always visible

## Performance Considerations

### Font Loading Optimization
- Google Fonts are loaded with `display=swap`
- System fonts provide immediate fallback
- Font weights are limited to essential ones (300, 400, 500, 600, 700)

### CSS Optimization
- Font declarations are consolidated
- Fallback fonts are ordered by availability
- Unused font weights are not loaded

## Usage Examples

### Basic Khmer Text
```html
<div class="khmer-text">
    សូមស្វាគមន៍មកកាន់ប្រព័ន្ធគ្រប់គ្រងហាងគិតលុយ។
</div>
```

### Traditional Style
```html
<div class="khmer-text-battambang">
    ការកម្មង់ជោគជ័យ!
</div>
```

### System Fonts Only
```html
<div class="khmer-text-system">
    លេខកម្មង់: #12345
</div>
```

### Language Attribute
```html
<div lang="km" class="khmer-text">
    យើងនឹងផ្ញើការបញ្ជាក់អ៊ីមែលឆាប់ៗ។
</div>
```

## Troubleshooting

### Common Issues

1. **Khmer text not displaying properly**
   - Check if Google Fonts are loading
   - Verify internet connection
   - Check browser console for font loading errors

2. **Font not changing**
   - Ensure CSS class is applied correctly
   - Check for CSS specificity conflicts
   - Verify font-family declaration

3. **Performance issues**
   - Consider using system fonts only
   - Implement font preloading for critical fonts
   - Use font-display: swap for better loading

### Debug Steps

1. **Check font loading**:
   ```javascript
   document.fonts.ready.then(() => {
       console.log('Fonts loaded');
   });
   ```

2. **Verify font availability**:
   ```javascript
   document.fonts.check('1em Noto Sans Khmer');
   ```

3. **Test with different fonts**:
   - Try switching between `.khmer-text`, `.khmer-text-battambang`, and `.khmer-text-system`
   - Compare rendering across different browsers

## Future Enhancements

### Potential Improvements
1. **Font preloading**: Add `<link rel="preload">` for critical fonts
2. **Variable fonts**: Use Noto Sans Khmer variable font for better performance
3. **Local fonts**: Include local font files for offline support
4. **Font subsetting**: Create custom font subsets with only needed characters

### Monitoring
- Track font loading performance
- Monitor user experience with different font combinations
- Collect feedback on font readability and aesthetics

## Conclusion

The Khmer font implementation provides comprehensive support for Khmer text rendering across the POS system. The multi-layered fallback system ensures text is always visible, while Google Fonts provide high-quality typography when available.

For questions or issues with Khmer font rendering, refer to the test page or contact the development team.
