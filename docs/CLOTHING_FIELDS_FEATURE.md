# Clothing Fields Feature

## Overview
This feature adds clothing-specific fields to the product management system. When a product type is set to "Clothes", additional fields become available for size, weight, color, and material.

## Features Added

### 1. Database Changes
- Added `size` field (VARCHAR(20)) for clothing sizes
- Added `weight` field (DECIMAL(8,2)) for weight in grams
- Added `color` field (VARCHAR(50)) for color information
- Added `material` field (VARCHAR(100)) for material type

### 2. Form Enhancements
- **Add Product Modal**: Shows clothing fields when "Clothes" is selected as product type
- **Edit Product Modal**: Shows clothing fields when editing clothing products
- **Dynamic Display**: Clothing fields are hidden for non-clothing products

### 3. Available Sizes
- XS, S, M, L, XL, XXL, XXXL, Custom

### 4. Table Display
- New "Attributes" column shows clothing-specific information
- Displays size, color, material, and weight as badges
- Only shows for clothing products

## How to Use

### Adding a Clothing Product
1. Click "Add Product"
2. Select "Clothes" as the product type
3. Clothing fields will automatically appear
4. Fill in size, weight, color, and material as needed
5. Save the product

### Editing a Clothing Product
1. Click the edit button on any clothing product
2. Clothing fields will be populated with existing data
3. Modify as needed
4. Save changes

### Switching Product Types
- When changing from "Clothes" to "Food", clothing fields are hidden and cleared
- When changing from "Food" to "Clothes", clothing fields become visible

## Database Setup
Run the SQL script to add the required fields:
```sql
-- Run admin/sql/add_clothing_fields.sql
```

## Technical Details
- Clothing fields are only processed when product type is "Clothes"
- Fields are stored as NULL for non-clothing products
- JavaScript handles dynamic showing/hiding of fields
- Form validation ensures proper data types
