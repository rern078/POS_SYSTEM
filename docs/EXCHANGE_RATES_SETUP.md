# Exchange Rate System Setup Guide

This guide explains how to set up and configure the exchange rate functionality in your POS system.

## Overview

The exchange rate system provides:
- Real-time currency conversion
- Support for multiple currencies
- Automatic rate updates from external APIs
- Manual rate management
- Historical rate tracking
- Admin interface for configuration

## Features

### Core Functionality
- **Multi-currency Support**: Support for 20+ currencies including USD, EUR, GBP, JPY, etc.
- **Real-time Conversion**: Automatic currency conversion during transactions
- **Rate Management**: Admin interface to view and edit exchange rates
- **API Integration**: Automatic updates from multiple exchange rate providers
- **Historical Data**: Track rate changes over time
- **Default Currency**: Set your preferred base currency

### Admin Features
- **Exchange Rate Dashboard**: View all current rates at a glance
- **Manual Rate Updates**: Edit individual exchange rates
- **API Integration**: Connect to external exchange rate services
- **Rate History**: View historical rate changes with charts
- **Default Currency Settings**: Configure your base currency
- **System Monitoring**: Track API updates and system status

## Installation

### 1. Database Setup

Run the SQL scripts in order:

```sql
-- 1. Basic exchange rate tables
SOURCE admin/sql/add_exchange_rate_functionality.sql

-- 2. Additional monitoring tables
SOURCE admin/sql/add_exchange_rate_logs.sql
```

### 2. API Configuration

#### Option A: Using API Keys File (Recommended for Development)

1. Edit `config/api_keys.php`
2. Add your API keys for the services you want to use:

```php
$apiKeys = [
    'exchangerate_api' => 'your_exchangerate_api_key_here',
    'fixer_io' => 'your_fixer_io_key_here',
    'currency_api' => 'your_currency_api_key_here',
    // ... other providers
];
```

#### Option B: Using Environment Variables (Recommended for Production)

Set environment variables:

```bash
export EXCHANGERATE_API_KEY=your_key_here
export FIXER_IO_KEY=your_key_here
export CURRENCY_API_KEY=your_key_here
```

### 3. File Permissions

Ensure the logs directory is writable:

```bash
mkdir -p logs
chmod 755 logs
```

## API Providers

### Supported Providers

| Provider | Free Tier | Update Frequency | Setup |
|----------|-----------|------------------|-------|
| **ExchangeRate-API** | 1,500 req/month | Daily | [Get Key](https://www.exchangerate-api.com/) |
| **Fixer.io** | 100 req/month | Hourly | [Get Key](https://fixer.io/) |
| **CurrencyAPI** | 1,000 req/month | Daily | [Get Key](https://currencyapi.com/) |
| **Open Exchange Rates** | 1,000 req/month | Hourly | [Get Key](https://openexchangerates.org/) |
| **Currency Layer** | 100 req/month | Hourly | [Get Key](https://currencylayer.com/) |

### Recommended Setup

For most businesses, we recommend:
1. **Primary**: ExchangeRate-API (1,500 free requests/month)
2. **Backup**: CurrencyAPI (1,000 free requests/month)

## Configuration

### 1. Default Currency

Set your default currency in the admin panel:
1. Go to Admin → Exchange Rates
2. Select your preferred default currency
3. Click "Update Default Currency"

### 2. Update Frequency

Configure automatic updates in `config/api_keys.php`:

```php
// Update every 6 hours (recommended for free tiers)
'update_frequency_hours' => '6'

// Update every hour (for paid tiers)
'update_frequency_hours' => '1'
```

### 3. Alert Thresholds

Set rate change alerts:

```php
// Alert when rate changes by 5% or more
'alert_rate_change_threshold' => '5.0'
```

## Usage

### Admin Interface

Access the exchange rate management at: `admin/exchange_rates.php`

#### Features:
- **Current Rates**: View all exchange rates relative to your default currency
- **Edit Rates**: Click "Edit" on any rate to manually update it
- **Update from API**: Click "Update from API" to fetch latest rates
- **Rate History**: View historical rate changes with interactive charts
- **Default Currency**: Change your base currency

### API Endpoints

The system provides REST API endpoints for programmatic access:

#### Get Current Rates
```
GET admin/api/exchange_rates.php?action=current_rates&base=USD
```

#### Get Rate History
```
GET admin/api/exchange_rates.php?action=history&base=USD&target=EUR&days=30
```

#### Convert Currency
```
POST admin/api/exchange_rates.php
{
    "action": "convert",
    "amount": 100,
    "from_currency": "USD",
    "to_currency": "EUR"
}
```

### Cron Job Setup

Set up automatic rate updates:

```bash
# Edit crontab
crontab -e

# Add this line to update rates every 6 hours
0 */6 * * * /usr/bin/php /path/to/your/pos/cron/update_exchange_rates.php

# Or update every hour (for paid API plans)
0 * * * * /usr/bin/php /path/to/your/pos/cron/update_exchange_rates.php
```

#### Manual Updates

You can also run updates manually:

```bash
# Update rates
php cron/update_exchange_rates.php

# Check system status
php cron/update_exchange_rates.php status

# Force manual update
php cron/update_exchange_rates.php manual

# Show help
php cron/update_exchange_rates.php help
```

## Integration with POS

### Frontend Integration

The exchange rate system is automatically integrated into:

1. **Product Display**: Prices shown in selected currency
2. **Shopping Cart**: Real-time currency conversion
3. **Checkout**: Final amounts in customer's preferred currency
4. **Receipts**: Printed in transaction currency with exchange rate info

### Backend Integration

The system handles:

1. **Order Processing**: Automatic currency conversion during checkout
2. **Reporting**: Sales reports in multiple currencies
3. **Inventory**: Product costs tracked in base currency
4. **Analytics**: Multi-currency sales analysis

## Monitoring

### Log Files

Check the logs for system status:

```bash
# View exchange rate logs
tail -f logs/exchange_rates.log

# View recent API updates
grep "Successfully updated" logs/exchange_rates.log
```

### Admin Dashboard

Monitor system health in the admin panel:
- API connection status
- Last update times
- Rate change alerts
- System errors

### Alerts

The system can alert you when:
- API updates fail
- Rates haven't been updated recently
- Significant rate changes occur
- Manual updates are performed

## Troubleshooting

### Common Issues

#### 1. API Key Not Working
- Verify your API key is correct
- Check if you've exceeded your free tier limits
- Ensure your API key is active

#### 2. Rates Not Updating
- Check if cron job is running: `crontab -l`
- Verify file permissions on logs directory
- Check PHP error logs for issues

#### 3. Currency Not Converting
- Ensure the currency is enabled in the admin panel
- Check if exchange rate exists for the currency pair
- Verify the default currency is set correctly

#### 4. Performance Issues
- Reduce update frequency for free API tiers
- Consider upgrading to paid API plans
- Monitor API usage to avoid rate limits

### Debug Mode

Enable debug logging by editing `cron/update_exchange_rates.php`:

```php
// Change this line
ini_set('display_errors', 0);

// To this
ini_set('display_errors', 1);
```

### Testing API Keys

Test your API keys using the admin interface:
1. Go to Exchange Rates page
2. Click "Update from API"
3. Check for success/error messages

## Security Considerations

### API Key Security
- Never commit API keys to version control
- Use environment variables in production
- Rotate API keys regularly
- Monitor API usage for unusual activity

### Access Control
- Only admin users can access exchange rate management
- API endpoints require admin authentication
- Log all rate changes for audit purposes

### Data Validation
- All exchange rates are validated before saving
- Currency codes are sanitized
- Rate values are checked for reasonable ranges

## Performance Optimization

### Caching
- Exchange rates are cached in the database
- API calls are minimized through intelligent scheduling
- Historical data is optimized for quick retrieval

### Database Optimization
- Indexes on currency pairs and timestamps
- Regular cleanup of old log entries
- Efficient queries for rate lookups

### API Usage
- Batch updates to minimize API calls
- Fallback providers for reliability
- Rate limiting to avoid API restrictions

## Support

### Getting Help
1. Check the logs for error messages
2. Verify your API configuration
3. Test with a different API provider
4. Review this documentation

### API Provider Support
- ExchangeRate-API: [Support](https://www.exchangerate-api.com/support)
- Fixer.io: [Support](https://fixer.io/support)
- CurrencyAPI: [Support](https://currencyapi.com/support)

### System Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- cURL extension enabled
- JSON extension enabled
- File write permissions for logs

## Updates and Maintenance

### Regular Maintenance
- Monitor API usage and limits
- Review and clean old log entries
- Update API keys if needed
- Check for new currency additions

### Backup
- Backup exchange rate data regularly
- Export currency configurations
- Keep API key backups in secure location

### Updates
- Check for system updates regularly
- Review API provider changes
- Update documentation as needed 