<?php

// File ini sekarang hanya mengembalikan array konfigurasi utama aplikasi.

// Fungsi untuk mendeteksi environment
if (!function_exists('detectEnvironment')) {
  function detectEnvironment(): string
  {
    if (env('APP_ENV')) {
      return env('APP_ENV');
    }
    if (isset($_SERVER['HTTP_HOST'])) {
      $host = $_SERVER['HTTP_HOST'];
      if (
        strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false ||
        strpos($host, '.local') !== false || strpos($host, ':8080') !== false
      ) {
        return 'development';
      }
    }
    return 'production';
  }
}

$environment = detectEnvironment();
$isDev = $environment === 'development';

return [
  'environment' => $environment,

  'debug' => env('APP_DEBUG', $isDev ? 'true' : 'false') === 'true',
  'app_name' => env('APP_NAME', 'MAZU'),
  'timezone' => env('APP_TIMEZONE', date_default_timezone_get()),

  'queue' => [
    'driver' => env('QUEUE_DRIVER', 'redis'),
    'connection' => env('QUEUE_CONNECTION', 'redis_queue'),
    'default' => env('QUEUE_NAME', 'default'),
  ],

  'spa' => [
    'prefetch' => env('SPA_PREFETCH', 'false') === 'true',
    'prefetch_limit' => (int) env('SPA_PREFETCH_LIMIT', 0),
  ],

  // 'auth' => [
  //   'mode' => env('AUTH_MODE', 'token'),
  //   'token_header' => env('AUTH_TOKEN_HEADER', 'Authorization'),
  //   'token_prefix' => env('AUTH_TOKEN_PREFIX', 'Bearer'),
  //   'token_key' => env('AUTH_TOKEN_KEY', 'token'),
  //   'token_cookie' => env('AUTH_TOKEN_COOKIE', 'token'),
  //   'user_key' => env('AUTH_USER_KEY', 'user'),
  //   'token_storage' => env('AUTH_TOKEN_STORAGE', 'cookie'),
  //   'session_key' => env('AUTH_SESSION_KEY', 'user_id'),
  //   'redirect_login' => env('AUTH_REDIRECT_LOGIN', '/login'),
  //   'auto_attach' => env('AUTH_AUTO_ATTACH', 'true') === 'true',
  //   'auto_logout' => env('AUTH_AUTO_LOGOUT', 'true') === 'true',
  //   'token_value' => env('TOKEN', 'secret-token-123'),
  //   'custom_guard' => null,
  // ],

  // Modular Configurations
  'database' => require __DIR__ . '/database.php',
  'view' => require __DIR__ . '/view.php',
];
