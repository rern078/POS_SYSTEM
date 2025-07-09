<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/exchange_rate.php';

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || !isAdmin()) {
      http_response_code(401);
      echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
      exit();
}

$exchangeRate = new ExchangeRate();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      $action = $_GET['action'] ?? '';

      switch ($action) {
            case 'history':
                  $baseCurrency = $_GET['base'] ?? 'USD';
                  $targetCurrency = $_GET['target'] ?? 'EUR';
                  $days = (int)($_GET['days'] ?? 30);

                  $history = $exchangeRate->getRateHistory($baseCurrency, $targetCurrency, $days);

                  echo json_encode([
                        'success' => true,
                        'data' => $history
                  ]);
                  break;

            case 'current_rates':
                  $baseCurrency = $_GET['base'] ?? 'USD';
                  $currencies = $exchangeRate->getCurrencies();
                  $rates = [];

                  foreach ($currencies as $currency) {
                        if ($currency['code'] !== $baseCurrency) {
                              $rate = $exchangeRate->getExchangeRate($baseCurrency, $currency['code']);
                              $rates[$currency['code']] = [
                                    'code' => $currency['code'],
                                    'name' => $currency['name'],
                                    'symbol' => $currency['symbol'],
                                    'rate' => $rate
                              ];
                        }
                  }

                  echo json_encode([
                        'success' => true,
                        'data' => $rates
                  ]);
                  break;

            case 'currencies':
                  $currencies = $exchangeRate->getCurrencies();
                  echo json_encode([
                        'success' => true,
                        'data' => $currencies
                  ]);
                  break;

            default:
                  http_response_code(400);
                  echo json_encode(['success' => false, 'message' => 'Invalid action']);
                  break;
      }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $input = json_decode(file_get_contents('php://input'), true);
      $action = $input['action'] ?? '';

      switch ($action) {
            case 'update_rate':
                  $baseCurrency = $input['base_currency'] ?? '';
                  $targetCurrency = $input['target_currency'] ?? '';
                  $rate = (float)($input['rate'] ?? 0);

                  if (empty($baseCurrency) || empty($targetCurrency) || $rate <= 0) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                        exit();
                  }

                  if ($exchangeRate->updateExchangeRate($baseCurrency, $targetCurrency, $rate)) {
                        echo json_encode(['success' => true, 'message' => 'Exchange rate updated successfully']);
                  } else {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Failed to update exchange rate']);
                  }
                  break;

            case 'fetch_rates':
                  if ($exchangeRate->fetchRealTimeRates()) {
                        echo json_encode(['success' => true, 'message' => 'Exchange rates updated from API']);
                  } else {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Failed to fetch rates from API']);
                  }
                  break;

            case 'convert':
                  $amount = (float)($input['amount'] ?? 0);
                  $fromCurrency = $input['from_currency'] ?? '';
                  $toCurrency = $input['to_currency'] ?? '';

                  if ($amount <= 0 || empty($fromCurrency) || empty($toCurrency)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                        exit();
                  }

                  $convertedAmount = $exchangeRate->convertCurrency($amount, $fromCurrency, $toCurrency);
                  $rate = $exchangeRate->getExchangeRate($fromCurrency, $toCurrency);

                  echo json_encode([
                        'success' => true,
                        'data' => [
                              'original_amount' => $amount,
                              'converted_amount' => $convertedAmount,
                              'rate' => $rate,
                              'from_currency' => $fromCurrency,
                              'to_currency' => $toCurrency
                        ]
                  ]);
                  break;

            default:
                  http_response_code(400);
                  echo json_encode(['success' => false, 'message' => 'Invalid action']);
                  break;
      }
} else {
      http_response_code(405);
      echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
