# Category Management Feature

## Overview
The Category Management feature allows for hierarchical organization of products with main categories and subcategories. This is particularly useful for clothing items where you can have main categories like "Men's Clothing" and subcategories like "T-Shirts", "Shirts", "Pants", etc.

## Database Structure

### Categories Table
```sql
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('Food','Clothes') NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int(11) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `parent_id` (`parent_id`),
  KEY `is_active` (`is_active`)
);
```

### Products Table Updates
- Added `category_id` column to link products to specific categories
- Maintains backward compatibility with existing `category` field

## Features

### 1. Dynamic Category Loading
- When a product type is selected (Food/Clothes), the system automatically loads relevant main categories
- When a main category is selected, the system loads its subcategories
- Categories are loaded via AJAX calls to prevent page reloads

### 2. Hierarchical Category Structure

#### Food Categories
- **Beverages** - Drinks and beverages
- **Snacks** - Snack foods and treats
- **Groceries** - Basic grocery items
- **Personal Care** - Personal care products

#### Clothes Categories
- **Men's Clothing**
  - T-Shirts
  - Shirts
  - Pants
  - Jackets
  - Shorts

- **Women's Clothing**
  - Dresses
  - Tops
  - Jeans
  - Skirts
  - Pants

- **Kids Clothing**
  - Boys Clothing
  - Girls Clothing
  - School Uniforms
  - Baby Clothing

- **Shoes**
  - Men's Shoes
  - Women's Shoes
  - Kids Shoes
  - Sports Shoes
  - Formal Shoes

- **Accessories**
  - Bags
  - Jewelry
  - Watches
  - Belts
  - Hats

### 3. Auto-filled Category Field
- The system automatically combines main category and subcategory names
- Format: "Main Category > Sub Category" (e.g., "Men's Clothing > T-Shirts")
- Users can still manually enter custom categories if needed

## API Endpoints

### Get Categories
**Endpoint:** `admin/api/get_categories.php`

**Parameters:**
- `type` (required): Product type ('Food' or 'Clothes')
- `parent_id` (optional): Parent category ID for subcategories

**Response:**
```json
{
  "success": true,
  "categories": [
    {
      "id": 1,
      "name": "Men's Clothing",
      "description": "Clothing for men",
      "parent_id": null
    }
  ]
}
```

## Usage Instructions

### Adding a New Product

1. **Select Product Type**
   - Choose "Food" or "Clothes" from the dropdown

2. **Select Main Category**
   - The dropdown will populate with relevant main categories based on the selected type

3. **Select Sub Category** (Optional)
   - Choose a subcategory if available
   - The category field will auto-fill with the combined category name

4. **Manual Category Entry** (Alternative)
   - Users can still manually enter custom categories in the "Or New Category" field

### Editing a Product

1. **Change Product Type**
   - Selecting a different type will reset the category dropdowns

2. **Update Categories**
   - The same dynamic loading applies when editing products

## Technical Implementation

### Frontend (JavaScript)
- Event listeners for type, main category, and subcategory changes
- AJAX calls to fetch categories dynamically
- Auto-filling of the category field based on selections

### Backend (PHP)
- Enhanced category handling in add/update operations
- Database queries to fetch category names from IDs
- Backward compatibility with existing category field

## Files Modified

1. **Database:**
   - `admin/sql/create_categories_table.sql` - New categories table
   - `admin/sql/sample_clothes_products.sql` - Sample clothing products

2. **API:**
   - `admin/api/get_categories.php` - Category fetching endpoint

3. **Admin Panel:**
   - `admin/products.php` - Updated forms and logic

4. **Documentation:**
   - `docs/CATEGORY_MANAGEMENT_FEATURE.md` - This file

## Setup Instructions

1. **Run Database Migration:**
   ```sql
   source admin/sql/create_categories_table.sql;
   ```

2. **Add Sample Data:**
   ```sql
   source admin/sql/sample_clothes_products.sql;
   ```

3. **Test the Feature:**
   - Go to Admin Panel > Products
   - Try adding a new product with type "Clothes"
   - Verify that categories load dynamically

## Benefits

1. **Better Organization:** Hierarchical structure makes it easier to organize products
2. **User-Friendly:** Dynamic loading provides a smooth user experience
3. **Flexible:** Supports both structured categories and custom entries
4. **Scalable:** Easy to add new categories and subcategories
5. **Backward Compatible:** Existing products continue to work

## Future Enhancements

1. **Category Management Interface:** Admin panel to manage categories
2. **Bulk Category Updates:** Update multiple products' categories at once
3. **Category Analytics:** Reports based on category performance
4. **Multi-level Categories:** Support for deeper category hierarchies
5. **Category Images:** Icons or images for categories

## Troubleshooting

### Categories Not Loading
- Check if the `categories` table exists
- Verify the API endpoint is accessible
- Check browser console for JavaScript errors

### Category Names Not Displaying
- Ensure category IDs are correctly stored
- Verify the category lookup queries are working
- Check for database connection issues

### Form Validation Issues
- Ensure required fields are properly marked
- Check that category combinations are valid
- Verify form submission handling
