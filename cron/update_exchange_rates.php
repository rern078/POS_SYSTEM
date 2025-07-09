<?php
// Exchange Rate Update Cron Job
// This script can be scheduled to run periodically to update exchange rates
// from external APIs. Recommended schedule: every 6 hours for free APIs,
// every hour for paid APIs.
// 
// Usage:
// - Add to crontab: 0 */6 * * * /usr/bin/php /path/to/your/pos/cron/update_exchange_rates.php
// - Or run manually: php update_exchange_rates.php

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include required files
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/exchange_rate.php';

// Initialize logging
$logFile = dirname(__DIR__) . '/logs/exchange_rates.log';
$logDir = dirname($logFile);

// Create logs directory if it doesn't exist
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message, $type = 'INFO')
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
    
    // Write to log file
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also output to console if running from command line
    if (php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}

// Start the update process
logMessage("Starting exchange rate update process");

try {
    $exchangeRate = new ExchangeRate();
    
    // Check if rates need updating (older than 6 hours)
    if (!$exchangeRate->needsUpdate(6)) {
        logMessage("Exchange rates are up to date (less than 6 hours old)");
        exit(0);
    }
    
    logMessage("Fetching latest exchange rates from external APIs");
    
    // Attempt to fetch rates from external APIs
    $success = $exchangeRate->fetchRealTimeRates();
    
    if ($success) {
        logMessage("Successfully updated exchange rates from external API");
        
        // Get some statistics
        $defaultCurrency = $exchangeRate->getDefaultCurrency();
        $currencies = $exchangeRate->getCurrencies();
        $updatedRates = count($currencies) - 1; // Exclude base currency
        
        logMessage("Updated {$updatedRates} exchange rates for base currency: {$defaultCurrency['code']}");
        
        // Log some sample rates for monitoring
        $sampleRates = $exchangeRate->getAllRatesForBase($defaultCurrency['code']);
        foreach (array_slice($sampleRates, 0, 5) as $rate) {
            logMessage("Rate: 1 {$defaultCurrency['code']} = {$rate['rate']} {$rate['target_currency']}");
        }
        
        exit(0);
    } else {
        logMessage("Failed to fetch rates from external APIs", "ERROR");
        
        // Check if we have any existing rates to fall back on
        $defaultCurrency = $exchangeRate->getDefaultCurrency();
        $existingRates = $exchangeRate->getAllRatesForBase($defaultCurrency['code']);
        
        if (empty($existingRates)) {
            logMessage("No existing rates found - system may need manual setup", "WARNING");
        } else {
            logMessage("Using existing rates as fallback", "WARNING");
        }
        
        exit(1);
    }
    
} catch (Exception $e) {
    logMessage("Exception occurred: " . $e->getMessage(), "ERROR");
    logMessage("Stack trace: " . $e->getTraceAsString(), "ERROR");
    exit(1);
}

// Additional utility functions for manual operations

/**
 * Manual rate update function
 */
function manualUpdate($provider = null)
{
    global $exchangeRate;
    
    logMessage("Manual update requested" . ($provider ? " for provider: {$provider}" : ""));
    
    try {
        $success = $exchangeRate->fetchRealTimeRates();
        
        if ($success) {
            logMessage("Manual update completed successfully");
            return true;
        } else {
            logMessage("Manual update failed", "ERROR");
            return false;
        }
    } catch (Exception $e) {
        logMessage("Manual update exception: " . $e->getMessage(), "ERROR");
        return false;
    }
}

/**
 * Check system status
 */
function checkStatus()
{
    global $exchangeRate;
    
    logMessage("Checking exchange rate system status");
    
    try {
        $defaultCurrency = $exchangeRate->getDefaultCurrency();
        $currencies = $exchangeRate->getCurrencies();
        $needsUpdate = $exchangeRate->needsUpdate(6);
        $recentLogs = $exchangeRate->getRateLogs(5);
        
        logMessage("Default currency: {$defaultCurrency['code']}");
        logMessage("Supported currencies: " . count($currencies));
        logMessage("Needs update: " . ($needsUpdate ? 'Yes' : 'No'));
        
        if (!empty($recentLogs)) {
            $lastUpdate = $recentLogs[0];
            logMessage("Last update: {$lastUpdate['created_at']} via {$lastUpdate['provider']}");
        }
        
        return true;
    } catch (Exception $e) {
        logMessage("Status check failed: " . $e->getMessage(), "ERROR");
        return false;
    }
}

// Handle command line arguments
if (php_sapi_name() === 'cli') {
    $args = $argv;
    $command = $args[1] ?? 'update';
    
    switch ($command) {
        case 'update':
            // Default behavior - already handled above
            break;
            
        case 'manual':
            manualUpdate();
            break;
            
        case 'status':
            checkStatus();
            break;
            
        case 'help':
            echo "Exchange Rate Update Script\n";
            echo "Usage: php update_exchange_rates.php [command]\n\n";
            echo "Commands:\n";
            echo "  update  - Update rates (default)\n";
            echo "  manual  - Force manual update\n";
            echo "  status  - Check system status\n";
            echo "  help    - Show this help\n";
            break;
            
        default:
            logMessage("Unknown command: {$command}", "ERROR");
            echo "Unknown command. Use 'help' for usage information.\n";
            exit(1);
    }
}
?> 