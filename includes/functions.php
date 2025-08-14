<?php
// Function to check if current page matches the nav link
function isActivePage($page_name)
{
      $current_page = basename($_SERVER['PHP_SELF']);
      $current_url = $_SERVER['REQUEST_URI'];
      $query_string = $_SERVER['QUERY_STRING'] ?? '';

      // Special cases for different pages
      switch ($page_name) {
            case 'home':
                  // Check if we're on index.php without query parameters (except for anchor links)
                  return $current_page === 'index.php' && empty($query_string);
            case 'products':
                  // Check if we're on product-list.php or any product-related page
                  return $current_page === 'product-list.php' ||
                        strpos($current_url, 'product-list.php') !== false ||
                        strpos($current_url, 'products') !== false;
            case 'features':
                  // Check for features section anchor
                  return strpos($current_url, '#features') !== false;
            case 'about':
                  // Check for about section anchor
                  return strpos($current_url, '#about') !== false;
            case 'contact':
                  // Check for contact section anchor
                  return strpos($current_url, '#contact') !== false;
            default:
                  return false;
      }
}

// Function to get current page info for debugging (optional)
function getCurrentPageInfo()
{
      return [
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? '',
            'php_self' => $_SERVER['PHP_SELF'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'query_string' => $_SERVER['QUERY_STRING'] ?? '',
            'basename' => basename($_SERVER['PHP_SELF'])
      ];
}
