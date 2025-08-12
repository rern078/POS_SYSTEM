# QR Code Upload Feature

## Overview
The POS system now includes a QR code upload feature that allows users to upload QR code images and automatically detect products. This feature works entirely in the browser using JavaScript, making it fast and secure.

## Features

### QR Code Image Upload
- **File Support**: JPEG, JPG, PNG, GIF, BMP formats
- **Size Limit**: Maximum 5MB per image
- **Real-time Processing**: Instant QR code detection and product lookup
- **Image Preview**: Shows uploaded image thumbnail
- **Status Updates**: Real-time status indicators

### Automatic Product Detection
- **Product Lookup**: Automatically searches for products using QR code data
- **Multiple Formats**: Supports various QR code formats (product codes, URLs, WiFi, etc.)
- **Smart Parsing**: Extracts relevant information from different QR code types
- **Cart Integration**: One-click add to cart functionality

### User Interface
- **Upload Section**: Dedicated upload area in the scanner section
- **Status Indicators**: Visual feedback for upload and processing status
- **Result Display**: Shows product information or QR code details
- **Quick Actions**: Add to cart button for found products

## How to Use

### 1. Upload QR Code Image
1. Navigate to the POS interface
2. In the scanner section, find the "Upload QR Code Image" area
3. Click "Choose File" or drag and drop an image
4. Select a QR code image from your device

### 2. Automatic Processing
- The system will automatically:
  - Validate the image file
  - Show a preview thumbnail
  - Process the QR code using JavaScript
  - Search for matching products
  - Display results

### 3. View Results
- **Product Found**: Shows product name, code, price, and stock
- **Special QR Code**: Shows QR code type and extracted information
- **No Match**: Shows appropriate error message

### 4. Add to Cart
- If a product is found, click "Add to Cart" button
- Product will be added to the shopping cart
- Upload area will be cleared for next use

## Supported QR Code Types

### Product QR Codes
- **Product Codes**: Direct product identification
- **JSON Format**: Structured product data
- **URL Format**: Product page links

### Special QR Codes
- **WiFi Networks**: Network name and connection details
- **Contact Cards**: Name, phone, email information
- **Email Addresses**: Pre-filled email composition
- **SMS Messages**: Pre-filled text messages
- **Phone Numbers**: Direct dialing
- **Website URLs**: Web page links

## Technical Implementation

### Frontend Processing
- **JavaScript QR Decoder**: Uses jsQR library for image processing
- **Canvas API**: Converts images to pixel data for analysis
- **Real-time Feedback**: Status updates throughout the process
- **Error Handling**: Comprehensive error messages and validation

### Backend Integration
- **Product Search**: Uses existing scan_code endpoint
- **QR Code Parsing**: Leverages existing parseQRCode function
- **Format Detection**: Identifies different QR code types
- **Data Extraction**: Extracts relevant information from QR codes

### Security Features
- **File Validation**: Type and size checking
- **Client-side Processing**: No server upload required
- **Input Sanitization**: Safe handling of QR code data
- **Error Boundaries**: Graceful error handling

## Benefits

1. **Convenience**: Upload QR codes from photos or screenshots
2. **Speed**: Instant processing without server upload
3. **Accuracy**: Reliable QR code detection and parsing
4. **Flexibility**: Works with various QR code formats
5. **Integration**: Seamless cart integration
6. **User-friendly**: Simple and intuitive interface

## Troubleshooting

### Common Issues

**"No QR Code Found"**
- Ensure the image contains a clear, readable QR code
- Check that the QR code is not damaged or partially obscured
- Try uploading a higher resolution image

**"Product Not Found"**
- Verify the QR code contains valid product information
- Check if the product exists in the system
- Ensure the QR code format is supported

**"File Too Large"**
- Compress the image to under 5MB
- Use a lower resolution version
- Convert to a more efficient format (JPEG)

**"Invalid File Type"**
- Use supported image formats: JPEG, JPG, PNG, GIF, BMP
- Check file extension and format
- Convert image if necessary

### Best Practices

1. **Image Quality**: Use clear, well-lit images
2. **QR Code Size**: Ensure QR code is large enough to be readable
3. **File Format**: Prefer JPEG or PNG for best compatibility
4. **File Size**: Keep images under 5MB for faster processing
5. **QR Code Placement**: Center the QR code in the image

## Future Enhancements

- **Batch Upload**: Process multiple QR codes at once
- **QR Code Generation**: Create QR codes for products
- **History**: Track uploaded QR codes and results
- **Analytics**: Usage statistics and popular products
- **Mobile Optimization**: Better mobile device support
- **Advanced Formats**: Support for more QR code types 