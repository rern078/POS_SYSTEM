# POS Accounting System

This document describes the comprehensive accounting functionality implemented in the POS system.

## 🏗️ **System Architecture**

### **Database Structure**
The accounting system includes the following new tables:

#### **Core Accounting Tables**
- `chart_of_accounts` - Chart of accounts with account types (asset, liability, equity, revenue, expense)
- `journal_entries` - Main journal entries with entry types and totals
- `journal_entry_details` - Individual line items for each journal entry
- `general_ledger` - Account balances by financial period
- `financial_periods` - Accounting periods for closing books

#### **Expense Management**
- `expenses` - Business expenses with vendor information and receipts
- `vendors` - Supplier/vendor management
- `purchase_orders` - Purchase order management
- `purchase_order_items` - Individual items in purchase orders

#### **Tax Management**
- `tax_rates` - Configurable tax rates for different jurisdictions

#### **Enhanced Existing Tables**
- `orders` - Added subtotal, tax_amount, discount_amount, journal_entry_id
- `order_items` - Added cost_price and profit_margin for cost tracking

## 📊 **Features Overview**

### **1. Chart of Accounts**
- **Hierarchical Structure**: Parent-child account relationships
- **Account Types**: Asset, Liability, Equity, Revenue, Expense
- **Auto-Generated Codes**: Systematic account numbering
- **Active/Inactive Management**: Toggle account status

### **2. Journal Entries**
- **Double-Entry Bookkeeping**: Debits and credits must balance
- **Entry Types**: Sale, Purchase, Expense, Adjustment, Transfer, Opening Balance
- **Auto-Numbering**: Sequential entry numbers by date
- **Reference Tracking**: Link to source documents
- **Audit Trail**: User tracking and timestamps

### **3. Expense Management**
- **Vendor Integration**: Link expenses to vendors
- **Receipt Upload**: Digital receipt storage
- **Payment Tracking**: Paid, Pending, Cancelled status
- **Category Classification**: Expense categorization
- **Tax Handling**: Separate tax amount tracking

### **4. Vendor Management**
- **Complete Vendor Profiles**: Contact info, addresses, payment terms
- **Credit Limits**: Vendor credit management
- **Purchase History**: Track vendor performance
- **Status Management**: Active/Inactive vendors

### **5. Purchase Orders**
- **Multi-Item Support**: Multiple products per PO
- **Status Tracking**: Draft, Sent, Received, Cancelled
- **Delivery Scheduling**: Expected delivery dates
- **Cost Tracking**: Unit prices and totals
- **Vendor Integration**: Link to vendor records

### **6. Financial Reporting**
- **Real-time Balances**: Current account balances
- **Period Management**: Monthly/quarterly/yearly periods
- **Profit & Loss**: Revenue vs expense tracking
- **Balance Sheet**: Asset, liability, equity reporting

## 🚀 **Getting Started**

### **1. Database Setup**
Run the accounting tables creation script:
```sql
-- Execute the accounting tables script
source admin/sql/add_accounting_tables.sql;
```

### **2. Initial Configuration**
1. **Chart of Accounts**: Default accounts are created automatically
2. **Tax Rates**: Default tax rates are pre-configured
3. **Financial Periods**: Create your first accounting period

### **3. First Steps**
1. **Review Chart of Accounts**: Verify default accounts meet your needs
2. **Set Opening Balances**: Create opening balance journal entries
3. **Configure Tax Rates**: Update tax rates for your jurisdiction
4. **Add Vendors**: Create vendor records for your suppliers

## 📋 **User Guide**

### **Accounting Dashboard** (`admin/accounting.php`)
- **Financial Overview**: Revenue, expenses, net income
- **Account Balances**: Real-time account balances
- **Recent Activity**: Latest journal entries and expenses
- **Quick Actions**: Links to all accounting functions

### **Journal Entries** (`admin/journal_entries.php`)
- **Create Entries**: Add new journal entries with multiple line items
- **Search & Filter**: Find entries by date, type, reference
- **Edit/Delete**: Modify or remove entries
- **Balance Validation**: Automatic debit/credit balance checking

### **Expenses** (`admin/expenses.php`)
- **Add Expenses**: Record business expenses with vendor details
- **Receipt Upload**: Attach digital receipts
- **Payment Tracking**: Monitor payment status
- **Category Management**: Organize expenses by category

### **Vendors** (`admin/vendors.php`)
- **Vendor Profiles**: Complete vendor information
- **Contact Management**: Multiple contact methods
- **Credit Limits**: Set and monitor credit limits
- **Purchase History**: Track vendor performance

### **Purchase Orders** (`admin/purchase_orders.php`)
- **Create POs**: Generate purchase orders with multiple items
- **Vendor Selection**: Choose from vendor database
- **Status Management**: Track PO lifecycle
- **Cost Calculation**: Automatic totals and tax calculations

## 🔧 **Technical Implementation**

### **Database Relationships**
```
chart_of_accounts (1) ←→ (many) journal_entry_details
journal_entries (1) ←→ (many) journal_entry_details
vendors (1) ←→ (many) purchase_orders
vendors (1) ←→ (many) expenses
purchase_orders (1) ←→ (many) purchase_order_items
products (1) ←→ (many) purchase_order_items
```

### **Key Functions**
- **Balance Validation**: Ensures debits equal credits
- **Auto-Numbering**: Generates sequential document numbers
- **Period Management**: Handles accounting period closures
- **Audit Trail**: Tracks all changes with user and timestamp

### **Security Features**
- **User Authentication**: Admin-only access to accounting functions
- **Input Validation**: Server-side validation of all inputs
- **SQL Injection Protection**: Prepared statements throughout
- **File Upload Security**: Restricted file types and sizes

## 📈 **Reporting Capabilities**

### **Financial Reports**
- **Income Statement**: Revenue, expenses, net income
- **Balance Sheet**: Assets, liabilities, equity
- **Cash Flow**: Operating, investing, financing activities
- **Trial Balance**: Account balances and totals

### **Operational Reports**
- **Expense Analysis**: By category, vendor, period
- **Vendor Performance**: Purchase history, payment patterns
- **Purchase Order Status**: Open, received, cancelled orders
- **Tax Reports**: Tax collected and paid

### **Custom Reports**
- **Date Range Reports**: Flexible period selection
- **Export Capabilities**: CSV, PDF export options
- **Chart Visualizations**: Graphical data representation

## 🔄 **Integration with POS**

### **Sales Integration**
- **Automatic Journal Entries**: Sales create revenue and cash entries
- **Cost Tracking**: Product costs tracked in sales
- **Tax Collection**: Sales tax automatically calculated and recorded
- **Inventory Updates**: Stock levels updated with sales

### **Inventory Integration**
- **Purchase Orders**: Automatically update inventory when received
- **Cost of Goods Sold**: Track product costs for profit calculation
- **Stock Valuation**: Current inventory value calculations
- **Reorder Points**: Low stock alerts and reorder suggestions

## 🛠️ **Maintenance & Administration**

### **Regular Tasks**
- **Period Closures**: Close accounting periods monthly/quarterly
- **Backup**: Regular database backups
- **Audit Trail**: Review user activities
- **Data Cleanup**: Archive old records

### **Troubleshooting**
- **Balance Issues**: Check for unbalanced journal entries
- **Missing Data**: Verify all transactions are recorded
- **Performance**: Monitor database performance
- **Security**: Regular security audits

## 📱 **Mobile Responsiveness**
All accounting interfaces are fully responsive and work on:
- **Desktop Computers**: Full functionality
- **Tablets**: Touch-optimized interface
- **Mobile Phones**: Simplified mobile layout

## 🔮 **Future Enhancements**
- **Multi-Currency Support**: Handle multiple currencies
- **Advanced Analytics**: Business intelligence dashboards
- **API Integration**: Connect with external accounting software
- **Automated Reconciliation**: Bank statement reconciliation
- **Advanced Tax Features**: Complex tax calculations
- **Budget Management**: Budget vs actual reporting

## 📞 **Support**
For technical support or questions about the accounting system:
1. Check this documentation
2. Review the database schema
3. Test in development environment
4. Contact system administrator

---

**Note**: This accounting system provides a solid foundation for small to medium-sized businesses. For larger organizations or complex accounting requirements, consider integration with professional accounting software. 