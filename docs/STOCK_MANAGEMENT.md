# Stock Management System

## Overview
The POS system now includes comprehensive stock management features with dedicated Stock In and Stock Out functionality for better inventory tracking and control.

## Features

### 1. Stock In Management
- **Purpose**: Add new stock to inventory from suppliers, returns, or other sources
- **Features**:
  - Quick stock in from inventory page
  - Detailed stock in from stock movements page
  - Supplier tracking
  - Reference number tracking (invoice, PO, etc.)
  - Notes and comments
  - Automatic stock level updates
  - Complete audit trail

### 2. Stock Out Management
- **Purpose**: Remove stock from inventory due to damage, expiry, theft, or other reasons
- **Features**:
  - Quick stock out from inventory page
  - Detailed stock out from stock movements page
  - Reason categorization (damage, expiry, theft, etc.)
  - Reference tracking
  - Stock availability validation
  - Automatic stock level updates
  - Complete audit trail

### 3. Stock Movements Tracking
- **Purpose**: Complete history of all stock movements
- **Features**:
  - Real-time movement history
  - Filter by movement type, product, date range
  - Search functionality
  - Export capabilities
  - Statistics and reporting
  - User tracking (who made the change)

## Access Points

### From Inventory Page (`admin/inventory.php`)
- **Quick Stock In Button**: Green button with plus icon
- **Quick Stock Out Button**: Red button with minus icon
- **View Movements Link**: Blue button to access detailed movements

### From Stock Movements Page (`admin/stock_movements.php`)
- **Dedicated Stock In**: Full-featured stock in form
- **Dedicated Stock Out**: Full-featured stock out form
- **Movement History**: Complete list of all stock movements
- **Statistics**: 30-day movement summary

## Stock In Process

1. **Select Product**: Choose from dropdown with current stock levels
2. **Enter Quantity**: Specify how many units to add
3. **Optional Fields**:
   - Supplier name
   - Reference number (invoice, PO, etc.)
   - Notes
4. **Submit**: System updates stock and logs the movement

## Stock Out Process

1. **Select Product**: Choose from dropdown with current stock levels
2. **Enter Quantity**: Specify how many units to remove
3. **Select Reason**: Choose from predefined reasons
4. **Optional Fields**:
   - Reference number
   - Notes
5. **Validation**: System checks if sufficient stock is available
6. **Submit**: System updates stock and logs the movement

## Movement Types

The system tracks these movement types:
- `stock_in`: Adding stock to inventory
- `stock_out`: Removing stock from inventory
- `sale`: Automatic stock reduction from sales
- `return`: Automatic stock increase from returns
- `damage`: Stock loss due to damage
- `manual`: Manual adjustments
- `restock`: Restocking operations
- `correction`: Correction adjustments

## Database Structure

### inventory_adjustments Table
```sql
CREATE TABLE inventory_adjustments (
  id int(11) NOT NULL AUTO_INCREMENT,
  product_id int(11) NOT NULL,
  adjustment_type enum('manual','restock','damage','correction','sale','return','stock_in','stock_out') NOT NULL,
  old_quantity int(11) NOT NULL,
  new_quantity int(11) NOT NULL,
  quantity_change int(11) NOT NULL,
  notes text DEFAULT NULL,
  adjusted_by int(11) NOT NULL,
  adjusted_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id)
);
```

## Benefits

1. **Complete Audit Trail**: Every stock movement is logged with user, timestamp, and details
2. **Better Control**: Separate stock in/out processes prevent accidental stock changes
3. **Reporting**: Detailed reports on stock movements and trends
4. **Compliance**: Proper documentation for inventory management
5. **User Accountability**: Track who made what changes and when
6. **Data Integrity**: Transaction-based updates prevent data corruption

## Setup Instructions

1. **Database Setup**: Run the SQL scripts in `admin/sql/`:
   - `add_inventory_adjustments_table.sql` (if table doesn't exist)
   - `update_inventory_adjustments_stock_types.sql` (if table exists)

2. **Access**: Navigate to Inventory page in admin panel
3. **Usage**: Use the Quick Stock In/Out buttons or visit Stock Movements page for detailed operations

## Security

- Only admin users can perform stock in/out operations
- All operations are logged with user ID
- Stock out operations validate available stock
- Transaction-based updates ensure data consistency

## Troubleshooting

### Common Issues
1. **Insufficient Stock Error**: Check current stock levels before stock out
2. **Database Errors**: Ensure inventory_adjustments table exists and has correct structure
3. **Permission Errors**: Verify user has admin privileges

### Support
For issues or questions, check the system logs or contact the system administrator. 