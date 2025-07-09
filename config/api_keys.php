<?php

/**
 * API Keys Configuration
 * 
 * This file contains API keys for external services.
 * IMPORTANT: Keep this file secure and never commit it to version control.
 * 
 * For production, consider using environment variables instead.
 */

// Exchange Rate API Keys
$apiKeys = [
      // ExchangeRate-API (https://www.exchangerate-api.com/)
      // Free tier: 1,500 requests/month
      'exchangerate_api' => '', // Add your ExchangeRate-API key here

      // Fixer.io (https://fixer.io/)
      // Free tier: 100 requests/month
      'fixer_io' => '', // Add your Fixer.io key here

      // CurrencyAPI (https://currencyapi.com/)
      // Free tier: 1,000 requests/month
      'currency_api' => '', // Add your CurrencyAPI key here

      // Alternative: Open Exchange Rates (https://openexchangerates.org/)
      // Free tier: 1,000 requests/month
      'open_exchange_rates' => '', // Add your Open Exchange Rates key here

      // Alternative: Currency Layer (https://currencylayer.com/)
      // Free tier: 100 requests/month
      'currency_layer' => '', // Add your Currency Layer key here
];

/**
 * Get API key for a specific provider
 */
function getAPIKey($provider)
{
      global $apiKeys;
      return $apiKeys[$provider] ?? '';
}

/**
 * Check if API key is configured for a provider
 */
function hasAPIKey($provider)
{
      return !empty(getAPIKey($provider));
}

/**
 * Get all configured API keys
 */
function getConfiguredProviders()
{
      global $apiKeys;
      $configured = [];

      foreach ($apiKeys as $provider => $key) {
            if (!empty($key)) {
                  $configured[] = $provider;
            }
      }

      return $configured;
}

/**
 * Get API provider information
 */
function getProviderInfo($provider)
{
      $providers = [
            'exchangerate_api' => [
                  'name' => 'ExchangeRate-API',
                  'url' => 'https://www.exchangerate-api.com/',
                  'free_tier' => '1,500 requests/month',
                  'base_url' => 'https://v6.exchangerate-api.com/v6/{key}/latest/{base}',
                  'response_format' => 'JSON',
                  'update_frequency' => 'Daily'
            ],
            'fixer_io' => [
                  'name' => 'Fixer.io',
                  'url' => 'https://fixer.io/',
                  'free_tier' => '100 requests/month',
                  'base_url' => 'http://data.fixer.io/api/latest?access_key={key}&base={base}&symbols={symbols}',
                  'response_format' => 'JSON',
                  'update_frequency' => 'Hourly'
            ],
            'currency_api' => [
                  'name' => 'CurrencyAPI',
                  'url' => 'https://currencyapi.com/',
                  'free_tier' => '1,000 requests/month',
                  'base_url' => 'https://api.currencyapi.com/v3/latest?apikey={key}&base_currency={base}',
                  'response_format' => 'JSON',
                  'update_frequency' => 'Daily'
            ],
            'open_exchange_rates' => [
                  'name' => 'Open Exchange Rates',
                  'url' => 'https://openexchangerates.org/',
                  'free_tier' => '1,000 requests/month',
                  'base_url' => 'https://openexchangerates.org/api/latest.json?app_id={key}',
                  'response_format' => 'JSON',
                  'update_frequency' => 'Hourly'
            ],
            'currency_layer' => [
                  'name' => 'Currency Layer',
                  'url' => 'https://currencylayer.com/',
                  'free_tier' => '100 requests/month',
                  'base_url' => 'http://api.currencylayer.com/live?access_key={key}&currencies={currencies}',
                  'response_format' => 'JSON',
                  'update_frequency' => 'Hourly'
            ]
      ];

      return $providers[$provider] ?? null;
}

/**
 * Validate API key format (basic validation)
 */
function validateAPIKey($provider, $key)
{
      if (empty($key)) {
            return false;
      }

      // Basic validation rules for different providers
      $validationRules = [
            'exchangerate_api' => '/^[a-zA-Z0-9]{32}$/', // 32 character alphanumeric
            'fixer_io' => '/^[a-zA-Z0-9]{32}$/', // 32 character alphanumeric
            'currency_api' => '/^[a-zA-Z0-9]{32}$/', // 32 character alphanumeric
            'open_exchange_rates' => '/^[a-zA-Z0-9]{32}$/', // 32 character alphanumeric
            'currency_layer' => '/^[a-zA-Z0-9]{32}$/' // 32 character alphanumeric
      ];

      if (isset($validationRules[$provider])) {
            return preg_match($validationRules[$provider], $key);
      }

      // Default validation - at least 10 characters
      return strlen($key) >= 10;
}

/**
 * Test API key connectivity
 */
function testAPIKey($provider, $key)
{
      if (!validateAPIKey($provider, $key)) {
            return ['valid' => false, 'message' => 'Invalid API key format'];
      }

      $providerInfo = getProviderInfo($provider);
      if (!$providerInfo) {
            return ['valid' => false, 'message' => 'Unknown provider'];
      }

      // Test the API key with a simple request
      try {
            $url = str_replace(
                  ['{key}', '{base}', '{symbols}', '{currencies}'],
                  [$key, 'USD', 'EUR,GBP', 'EUR,GBP'],
                  $providerInfo['base_url']
            );

            $context = stream_context_create([
                  'http' => [
                        'timeout' => 10,
                        'user_agent' => 'POS-System/1.0'
                  ]
            ]);

            $response = file_get_contents($url, false, $context);

            if ($response === false) {
                  return ['valid' => false, 'message' => 'Failed to connect to API'];
            }

            $data = json_decode($response, true);

            // Check for error responses
            if (isset($data['error'])) {
                  return ['valid' => false, 'message' => $data['error']['info'] ?? 'API error'];
            }

            if (isset($data['success']) && !$data['success']) {
                  return ['valid' => false, 'message' => $data['error']['info'] ?? 'API request failed'];
            }

            return ['valid' => true, 'message' => 'API key is valid'];
      } catch (Exception $e) {
            return ['valid' => false, 'message' => 'Connection error: ' . $e->getMessage()];
      }
}

// Security: Prevent direct access to this file
if (!defined('SECURE_ACCESS')) {
      http_response_code(403);
      exit('Direct access not allowed');
}
