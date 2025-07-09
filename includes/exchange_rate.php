<?php
// Use a more robust path resolution
$configPath = dirname(__DIR__) . '/config/database.php';
require_once $configPath;

class ExchangeRate
{
      private $pdo;

      public function __construct()
      {
            $this->pdo = getDBConnection();
      }

      /**
       * Get exchange rate between two currencies
       */
      public function getExchangeRate($fromCurrency, $toCurrency)
      {
            if ($fromCurrency === $toCurrency) {
                  return 1.0;
            }

            $stmt = $this->pdo->prepare("
            SELECT rate FROM exchange_rates 
            WHERE base_currency = ? AND target_currency = ? AND is_active = 1
        ");
            $stmt->execute([$fromCurrency, $toCurrency]);
            $rate = $stmt->fetchColumn();

            if ($rate === false) {
                  // Try reverse rate
                  $stmt = $this->pdo->prepare("
                SELECT rate FROM exchange_rates 
                WHERE base_currency = ? AND target_currency = ? AND is_active = 1
            ");
                  $stmt->execute([$toCurrency, $fromCurrency]);
                  $reverseRate = $stmt->fetchColumn();

                  if ($reverseRate !== false) {
                        return 1 / $reverseRate;
                  }

                  // If no rate found, return 1 (no conversion)
                  return 1.0;
            }

            return (float)$rate;
      }

      /**
       * Convert amount from one currency to another
       */
      public function convertCurrency($amount, $fromCurrency, $toCurrency)
      {
            $rate = $this->getExchangeRate($fromCurrency, $toCurrency);
            return $amount * $rate;
      }

      /**
       * Get all available currencies
       */
      public function getCurrencies()
      {
            $stmt = $this->pdo->prepare("
            SELECT code, name, symbol, is_default 
            FROM currencies 
            WHERE is_active = 1 
            ORDER BY is_default DESC, name ASC
        ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
      }

      /**
       * Get default currency
       */
      public function getDefaultCurrency()
      {
            $stmt = $this->pdo->prepare("
            SELECT code, name, symbol 
            FROM currencies 
            WHERE is_default = 1 AND is_active = 1
        ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
      }

      /**
       * Update exchange rate
       */
      public function updateExchangeRate($baseCurrency, $targetCurrency, $rate)
      {
            $stmt = $this->pdo->prepare("
            INSERT INTO exchange_rates (base_currency, target_currency, rate) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE rate = ?, last_updated = CURRENT_TIMESTAMP
        ");
            return $stmt->execute([$baseCurrency, $targetCurrency, $rate, $rate]);
      }

      /**
       * Get currency symbol by code
       */
      public function getCurrencySymbol($currencyCode)
      {
            $stmt = $this->pdo->prepare("
            SELECT symbol FROM currencies WHERE code = ? AND is_active = 1
        ");
            $stmt->execute([$currencyCode]);
            $symbol = $stmt->fetchColumn();
            return $symbol ?: '$';
      }

      /**
       * Format amount with currency symbol
       */
      public function formatAmount($amount, $currencyCode)
      {
            $symbol = $this->getCurrencySymbol($currencyCode);
            return $symbol . number_format($amount, 2);
      }

      /**
       * Fetch real-time exchange rates from external API
       * Supports multiple API providers with fallback
       */
      public function fetchRealTimeRates()
      {
            $baseCurrency = $this->getDefaultCurrency()['code'] ?? 'USD';
            $currencies = $this->getCurrencies();
            $targetCurrencies = array_column($currencies, 'code');
            
            // Try multiple API providers in order of preference
            $providers = [
                'exchangerate_api' => $this->fetchFromExchangeRateAPI($baseCurrency, $targetCurrencies),
                'fixer_io' => $this->fetchFromFixerIO($baseCurrency, $targetCurrencies),
                'currency_api' => $this->fetchFromCurrencyAPI($baseCurrency, $targetCurrencies)
            ];
            
            foreach ($providers as $provider => $result) {
                if ($result !== false) {
                    // Log successful update
                    $this->logRateUpdate($provider, $baseCurrency, count($result));
                    return true;
                }
            }
            
            return false;
      }
      
      /**
       * Fetch rates from ExchangeRate-API (free tier available)
       */
      private function fetchFromExchangeRateAPI($baseCurrency, $targetCurrencies)
      {
            $apiKey = $this->getAPIKey('exchangerate_api');
            if (empty($apiKey)) {
                return false;
            }
            
            $url = "https://v6.exchangerate-api.com/v6/{$apiKey}/latest/{$baseCurrency}";
            
            try {
                $response = file_get_contents($url);
                $data = json_decode($response, true);
                
                if ($data && isset($data['conversion_rates'])) {
                    $updatedRates = 0;
                    foreach ($data['conversion_rates'] as $currency => $rate) {
                        if (in_array($currency, $targetCurrencies)) {
                            $this->updateExchangeRate($baseCurrency, $currency, $rate);
                            $updatedRates++;
                        }
                    }
                    return $updatedRates > 0 ? $data['conversion_rates'] : false;
                }
            } catch (Exception $e) {
                error_log("ExchangeRate-API error: " . $e->getMessage());
            }
            
            return false;
      }
      
      /**
       * Fetch rates from Fixer.io (requires API key)
       */
      private function fetchFromFixerIO($baseCurrency, $targetCurrencies)
      {
            $apiKey = $this->getAPIKey('fixer_io');
            if (empty($apiKey)) {
                return false;
            }
            
            $symbols = implode(',', $targetCurrencies);
            $url = "http://data.fixer.io/api/latest?access_key={$apiKey}&base={$baseCurrency}&symbols={$symbols}";
            
            try {
                $response = file_get_contents($url);
                $data = json_decode($response, true);
                
                if ($data && isset($data['success']) && $data['success'] && isset($data['rates'])) {
                    $updatedRates = 0;
                    foreach ($data['rates'] as $currency => $rate) {
                        $this->updateExchangeRate($baseCurrency, $currency, $rate);
                        $updatedRates++;
                    }
                    return $updatedRates > 0 ? $data['rates'] : false;
                }
            } catch (Exception $e) {
                error_log("Fixer.io error: " . $e->getMessage());
            }
            
            return false;
      }
      
      /**
       * Fetch rates from CurrencyAPI (free tier available)
       */
      private function fetchFromCurrencyAPI($baseCurrency, $targetCurrencies)
      {
            $apiKey = $this->getAPIKey('currency_api');
            if (empty($apiKey)) {
                return false;
            }
            
            $url = "https://api.currencyapi.com/v3/latest?apikey={$apiKey}&base_currency={$baseCurrency}";
            
            try {
                $response = file_get_contents($url);
                $data = json_decode($response, true);
                
                if ($data && isset($data['data'])) {
                    $updatedRates = 0;
                    foreach ($data['data'] as $currency => $currencyData) {
                        if (in_array($currency, $targetCurrencies) && isset($currencyData['value'])) {
                            $this->updateExchangeRate($baseCurrency, $currency, $currencyData['value']);
                            $updatedRates++;
                        }
                    }
                    return $updatedRates > 0 ? $data['data'] : false;
                }
            } catch (Exception $e) {
                error_log("CurrencyAPI error: " . $e->getMessage());
            }
            
            return false;
      }
      
      /**
       * Get API key from configuration
       */
      public function getAPIKey($provider)
      {
            // Define secure access constant
            define('SECURE_ACCESS', true);
            
            // Include API keys configuration
            $configPath = dirname(__DIR__) . '/config/api_keys.php';
            if (file_exists($configPath)) {
                require_once $configPath;
                return getAPIKey($provider);
            }
            
            // Fallback to environment variables
            $envKey = strtoupper($provider) . '_API_KEY';
            return $_ENV[$envKey] ?? '';
      }
      
      /**
       * Log rate update for monitoring
       */
      private function logRateUpdate($provider, $baseCurrency, $ratesCount)
      {
            $stmt = $this->pdo->prepare("
                INSERT INTO exchange_rate_logs (provider, base_currency, rates_updated, status, created_at) 
                VALUES (?, ?, ?, 'success', CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$provider, $baseCurrency, $ratesCount]);
      }

      /**
       * Get exchange rate history for a currency pair
       */
      public function getRateHistory($baseCurrency, $targetCurrency, $days = 30)
      {
            $stmt = $this->pdo->prepare("
            SELECT rate, last_updated, base_currency, target_currency
            FROM exchange_rates 
            WHERE base_currency = ? AND target_currency = ? 
            AND last_updated >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY last_updated DESC
        ");
            $stmt->execute([$baseCurrency, $targetCurrency, $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
      }
      
      /**
       * Get all exchange rates for a base currency
       */
      public function getAllRatesForBase($baseCurrency)
      {
            $stmt = $this->pdo->prepare("
            SELECT target_currency, rate, last_updated 
            FROM exchange_rates 
            WHERE base_currency = ? AND is_active = 1
            ORDER BY target_currency ASC
        ");
            $stmt->execute([$baseCurrency]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
      }
      
      /**
       * Get rate change percentage over a period
       */
      public function getRateChange($baseCurrency, $targetCurrency, $days = 7)
      {
            $stmt = $this->pdo->prepare("
            SELECT rate, last_updated 
            FROM exchange_rates 
            WHERE base_currency = ? AND target_currency = ? 
            AND last_updated >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY last_updated ASC
            LIMIT 2
        ");
            $stmt->execute([$baseCurrency, $targetCurrency, $days]);
            $rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rates) >= 2) {
                $oldRate = $rates[0]['rate'];
                $newRate = $rates[count($rates) - 1]['rate'];
                return (($newRate - $oldRate) / $oldRate) * 100;
            }
            
            return 0;
      }
      
      /**
       * Get exchange rate statistics
       */
      public function getRateStats($baseCurrency, $targetCurrency, $days = 30)
      {
            $stmt = $this->pdo->prepare("
            SELECT 
                MIN(rate) as min_rate,
                MAX(rate) as max_rate,
                AVG(rate) as avg_rate,
                COUNT(*) as total_updates
            FROM exchange_rates 
            WHERE base_currency = ? AND target_currency = ? 
            AND last_updated >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
            $stmt->execute([$baseCurrency, $targetCurrency, $days]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
      }
      
      /**
       * Bulk update exchange rates
       */
      public function bulkUpdateRates($rates)
      {
            $this->pdo->beginTransaction();
            
            try {
                foreach ($rates as $rate) {
                    $this->updateExchangeRate(
                        $rate['base_currency'],
                        $rate['target_currency'],
                        $rate['rate']
                    );
                }
                
                $this->pdo->commit();
                return true;
            } catch (Exception $e) {
                $this->pdo->rollBack();
                error_log("Bulk update error: " . $e->getMessage());
                return false;
            }
      }
      
      /**
       * Get exchange rate logs
       */
      public function getRateLogs($limit = 50)
      {
            $stmt = $this->pdo->prepare("
            SELECT provider, base_currency, rates_updated, status, created_at 
            FROM exchange_rate_logs 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
      }
      
      /**
       * Check if rates need updating (older than specified hours)
       */
      public function needsUpdate($hours = 24)
      {
            $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM exchange_rates 
            WHERE last_updated < DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
            $stmt->execute([$hours]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
      }
      
      /**
       * Get currency conversion matrix
       */
      public function getConversionMatrix($baseCurrency)
      {
            $currencies = $this->getCurrencies();
            $matrix = [];
            
            foreach ($currencies as $currency) {
                if ($currency['code'] !== $baseCurrency) {
                    $rate = $this->getExchangeRate($baseCurrency, $currency['code']);
                    $matrix[$currency['code']] = [
                        'code' => $currency['code'],
                        'name' => $currency['name'],
                        'symbol' => $currency['symbol'],
                        'rate' => $rate,
                        'change_24h' => $this->getRateChange($baseCurrency, $currency['code'], 1)
                    ];
                }
            }
            
            return $matrix;
      }
}
