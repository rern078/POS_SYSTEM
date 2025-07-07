# Barcode and QR Code Scanning Functionality

This document describes the barcode and QR code scanning functionality implemented in the POS system.

## Features

### 1. Database Structure
- Added `barcode` and `qr_code` fields to the `products` table
- Both fields are unique and can be auto-generated or manually entered
- Existing products are automatically assigned barcode and QR code values

### 2. Admin Panel Features

#### Product Management
- **Add/Edit Products**: New fields for barcode and QR code in product forms
- **Product Table**: Displays barcode and QR code columns
- **Search**: Enhanced search functionality to include barcode and QR code fields

#### QR Code Generator (`admin/generate_qr.php`)
- Generate QR codes and barcodes for all products
- Preview generated codes
- Download QR codes and barcodes as images
- Real-time generation using JavaScript libraries

### 3. POS System Features

#### Scanner Interface
- **Camera Scanner**: Use device camera to scan barcodes and QR codes
- **Manual Input**: Enter codes manually for testing
- **Real-time Scanning**: Instant product lookup and cart addition
- **Multiple Scanner Libraries**: 
  - ZXing for QR codes and modern barcodes
  - Quagga for traditional barcodes

#### Scanning Process
1. Click "Scan" button to activate camera
2. Point camera at barcode or QR code
3. System automatically detects and processes the code
4. Product is found and added to cart
5. Success notification is displayed

## Technical Implementation

### Backend (PHP)
- **New AJAX Action**: `scan_code` for processing scanned codes
- **Database Queries**: Search products by barcode, QR code, or product code
- **Cart Integration**: Automatically add scanned products to cart

### Frontend (JavaScript)
- **Scanner Libraries**: 
  - ZXing Library for QR codes
  - Quagga Library for barcodes
- **Camera Access**: Uses `getUserMedia` API
- **Real-time Processing**: Instant code detection and processing

### Supported Code Types
- **QR Codes**: All standard QR code formats
- **Barcodes**: 
  - Code 128
  - EAN-13
  - EAN-8
  - Code 39
  - UPC-A
  - UPC-E
  - Codabar
  - Interleaved 2 of 5

## Setup Instructions

### 1. Database Setup
Run the SQL script to add barcode and QR code fields:
```sql
-- Execute admin/sql/add_barcode_fields.sql
```

### 2. File Structure
Ensure the following files are in place:
- `admin/generate_qr.php` - QR code generator
- Updated `admin/products.php` - Enhanced product management
- Updated `user/pos.php` - Scanner interface

### 3. Browser Requirements
- Modern browser with camera access support
- HTTPS connection (required for camera access)
- User permission for camera access

## Usage Guide

### For Administrators
1. **Add Products**: Include barcode and QR code when adding products
2. **Generate Codes**: Use the QR Code Generator to create codes for existing products
3. **Manage Codes**: Edit barcode and QR code values in the product management interface

### For Cashiers
1. **Start Scanner**: Click the "Scan" button in the POS interface
2. **Scan Products**: Point camera at product barcode or QR code
3. **Verify**: Check that the correct product is added to cart
4. **Manual Entry**: Use the input field for manual code entry if needed

## Troubleshooting

### Camera Access Issues
- Ensure HTTPS connection
- Check browser permissions
- Try refreshing the page
- Use manual input as fallback

### Scanner Not Working
- Check browser console for errors
- Verify scanner libraries are loaded
- Try different browser
- Ensure good lighting for scanning

### Product Not Found
- Verify barcode/QR code is correctly assigned to product
- Check product is in stock
- Try searching manually to confirm product exists

## Security Considerations

- Camera access requires user permission
- Scanned data is processed locally
- No sensitive data is stored in barcodes/QR codes
- HTTPS required for camera functionality

## Future Enhancements

- Bulk QR code generation
- Custom QR code designs
- Advanced barcode formats
- Offline scanning capability
- Integration with external barcode databases 