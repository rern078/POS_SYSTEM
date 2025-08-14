<?php
// session_start();

// Default language
$default_language = 'en';

// Available languages
$available_languages = [
      'en' => 'English',
      'cn' => 'Chinese',
      'kh' => 'Khmer',
      'vn' => 'Vietnamese'
];

// Get current language from session or default
function getCurrentLanguage()
{
      global $default_language, $available_languages;

      if (isset($_SESSION['language']) && array_key_exists($_SESSION['language'], $available_languages)) {
            return $_SESSION['language'];
      }

      return $default_language;
}

// Set language
function setLanguage($language_code)
{
      global $available_languages;

      if (array_key_exists($language_code, $available_languages)) {
            $_SESSION['language'] = $language_code;
            return true;
      }

      return false;
}

// Get translation
function __($key, $language = null)
{
      if ($language === null) {
            $language = getCurrentLanguage();
      }

      $translations = getTranslations($language);

      if (isset($translations[$key])) {
            return $translations[$key];
      }

      // Fallback to English if translation not found
      if ($language !== 'en') {
            $english_translations = getTranslations('en');
            if (isset($english_translations[$key])) {
                  return $english_translations[$key];
            }
      }

      // Return the key if no translation found
      return $key;
}

// Get all translations for a language
function getTranslations($language)
{
      $translation_file = __DIR__ . "/../lang/{$language}/translations.php";

      if (file_exists($translation_file)) {
            return include $translation_file;
      }

      return [];
}

// Handle language switching
if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $available_languages)) {
      setLanguage($_GET['lang']);

      // Redirect back to the same page without the lang parameter
      $redirect_url = $_SERVER['REQUEST_URI'];
      $redirect_url = preg_replace('/[?&]lang=[^&]*/', '', $redirect_url);
      $redirect_url = rtrim($redirect_url, '?&');

      // Add back any existing query parameters
      if (isset($_GET['lang'])) {
            unset($_GET['lang']);
      }

      if (!empty($_GET)) {
            $query_string = http_build_query($_GET);
            $redirect_url .= (strpos($redirect_url, '?') !== false ? '&' : '?') . $query_string;
      }

      header("Location: " . $redirect_url);
      exit;
}

// Get current language for use in templates
$current_language = getCurrentLanguage();
