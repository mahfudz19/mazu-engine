<?php

/**
 * Environment Configuration Loader
 * Loads environment variables from .env file if exists
 */

if (!function_exists('getEnvPath')) {
  function getEnvPath($envFile = '.env')
  {
    return __DIR__ . '/../../' . ltrim($envFile, '/');
    // return __DIR__ . '/../../../.config/' . ltrim($envFile, '/');
  }
}

if (!function_exists('loadEnvironmentConfig')) {
  function loadEnvironmentConfig($envFile = '.env')
  {
    static $loaded = false;
    if ($loaded) {
      return;
    }

    $envPath = getEnvPath($envFile);
    if (!file_exists($envPath)) {
      $loaded = true;
      return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '' || strpos($line, '#') === 0) {
        continue;
      }
      if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        // Hanya set jika belum ada di environment (Sistem > .env)
        if (getenv($key) === false && !isset($_ENV[$key]) && !isset($_SERVER[$key])) {
          putenv("{$key}={$value}");
          $_ENV[$key] = $value;
        }
      }
    }
    $loaded = true;
  }
}

if (!function_exists('env')) {
  function env($key, $default = null)
  {
    loadEnvironmentConfig();

    // Check $_ENV first, then $_SERVER, then getenv()
    if (isset($_ENV[$key])) {
      return $_ENV[$key];
    }

    if (isset($_SERVER[$key])) {
      return $_SERVER[$key];
    }

    $value = getenv($key);
    return $value === false ? $default : $value;
  }
}

if (!function_exists('app_version')) {
  function app_version()
  {
    static $version = null;
    if ($version === null) {
      $packageJsonPath = __DIR__ . '/../../package.json';
      if (file_exists($packageJsonPath)) {
        $package = json_decode(file_get_contents($packageJsonPath), true);
        $version = $package['version'] ?? '1.0.0';
      } else {
        $version = '1.0.0';
      }
    }
    return $version;
  }
}

if (!function_exists('currentUrl')) {
  /**
   * Get the current URL path, including the subdirectory if present.
   *
   * @return string The current request URI.
   */
  function currentUrl(): string
  {
    return $_SERVER['REQUEST_URI'] ?? '/';
  }
}

/**
 * Check if application is in production
 */
function isProduction()
{
  return env('APP_ENV', 'development') === 'production';
}

/**
 * Get subdirectory for development environment
 */
function getSubdirectory()
{
  // Jika berjalan di PHP Built-in Server (php mazu serve), abaikan subdirectory
  if (php_sapi_name() === 'cli-server') {
    return '';
  }
  return env('APP_SUBDIRECTORY', '');
}

/**
 * Get base URL with or without subdirectory based on environment
 * @param string $path Optional path to append to the base URL
 * @return string The complete URL with subdirectory (if needed) and path
 */
function getBaseUrl($path = '/')
{
  $subdirectory = getSubdirectory();
  $baseUrl = $subdirectory ? '/' . $subdirectory : '';

  if (!empty($path)) {
    // Clean up the path: trim whitespace and remove multiple slashes
    $path = trim($path);
    $path = ltrim($path, '/'); // Remove leading slash
    $path = preg_replace('#/+#', '/', $path); // Replace multiple slashes with single slash

    if (!empty($path)) {
      $baseUrl .= '/' . $path;
    }
  }

  // Fallback: Jika baseUrl kosong, kembalikan '/'
  return empty($baseUrl) ? '/' : $baseUrl;
}

if (!isset($GLOBALS['dump_config'])) {
  $GLOBALS['dump_config'] = [
    'mode' => 'auto',
    'show_location' => true,
    'show_type' => true,
    'max_depth' => 4,
    'max_items_per_level' => 50,
    'max_string_length' => 2000,
    'max_total_output_length' => 20000,
    'sanitize_sensitive' => true,
    'sensitive_keys' => ['password', 'password_confirmation', 'token', 'api_key', 'secret'],
    'allow_in_production' => false,
    'dd_http_status_code' => 500,
    'dd_exit_code' => 1,
  ];
}

if (!function_exists('simple_dump_build')) {
  function simple_dump_build($var, int $level, array $config, array &$visitedObjects, ?string $keyName = null): string
  {
    $indent = str_repeat('  ', $level);
    $newline = PHP_EOL;

    $showType = $config['show_type'] ?? true;
    $sanitize = !empty($config['sanitize_sensitive']);
    $sensitiveKeys = $config['sensitive_keys'] ?? [];

    if ($sanitize && $keyName !== null && in_array((string) $keyName, $sensitiveKeys, true)) {
      return $indent . '*** HIDDEN ***' . $newline;
    }

    $type = gettype($var);

    if ($type === 'string') {
      $len = strlen($var);
      $maxLen = $config['max_string_length'] ?? 2000;
      $display = $var;
      if ($len > $maxLen) {
        $display = substr($var, 0, $maxLen) . '... (truncated)';
      }
      if ($showType) {
        return $indent . 'string(' . $len . ') "' . $display . '"' . $newline;
      }
      return $indent . '"' . $display . '"' . $newline;
    }

    if ($type === 'integer') {
      if ($showType) {
        return $indent . 'int(' . $var . ')' . $newline;
      }
      return $indent . $var . $newline;
    }

    if ($type === 'double') {
      if ($showType) {
        return $indent . 'float(' . $var . ')' . $newline;
      }
      return $indent . $var . $newline;
    }

    if ($type === 'boolean') {
      $value = $var ? 'true' : 'false';
      if ($showType) {
        return $indent . 'bool(' . $value . ')' . $newline;
      }
      return $indent . $value . $newline;
    }

    if ($type === 'NULL') {
      return $indent . 'NULL' . $newline;
    }

    if ($type === 'array') {
      $maxDepth = $config['max_depth'] ?? 4;
      if ($level >= $maxDepth) {
        return $indent . 'array(...) ...depth limit...' . $newline;
      }

      $count = count($var);
      $out = $indent . 'array(' . $count . ') [' . $newline;
      $maxItems = $config['max_items_per_level'] ?? 50;
      $index = 0;

      foreach ($var as $k => $v) {
        if ($index >= $maxItems) {
          $out .= $indent . '  ... (' . ($count - $index) . ' more items)' . $newline;
          break;
        }

        $keyStr = is_int($k) ? (string) $k : '"' . $k . '"';
        $out .= $indent . '  [' . $keyStr . '] =>' . $newline;
        $out .= simple_dump_build($v, $level + 2, $config, $visitedObjects, (string) $k);
        $index++;
      }

      $out .= $indent . ']' . $newline;
      return $out;
    }

    if ($type === 'object') {
      $maxDepth = $config['max_depth'] ?? 4;
      $class = get_class($var);
      if ($level >= $maxDepth) {
        return $indent . 'object(' . $class . ') {...depth limit...}' . $newline;
      }

      $id = spl_object_id($var);
      if (isset($visitedObjects[$id])) {
        return $indent . 'object(' . $class . ') {...recursive reference...}' . $newline;
      }
      $visitedObjects[$id] = true;

      if ($var instanceof \Throwable) {
        $out = $indent . 'object(' . $class . ') Throwable {' . $newline;

        $exceptionData = [
          'message' => $var->getMessage(),
          'code' => $var->getCode(),
          'file' => $var->getFile(),
          'line' => $var->getLine(),
        ];

        $trace = $var->getTrace();
        $maxTraceItems = $config['max_items_per_level'] ?? 50;
        if (!empty($trace)) {
          if (count($trace) > $maxTraceItems) {
            $exceptionData['trace'] = array_slice($trace, 0, $maxTraceItems);
          } else {
            $exceptionData['trace'] = $trace;
          }
        }

        $previous = $var->getPrevious();
        if ($previous !== null) {
          $exceptionData['previous'] = $previous;
        }

        $maxItems = $config['max_items_per_level'] ?? 50;
        $index = 0;
        foreach ($exceptionData as $name => $value) {
          if ($index >= $maxItems) {
            $out .= $indent . '  ... (' . (count($exceptionData) - $index) . ' more properties)' . $newline;
            break;
          }

          $out .= $indent . '  [' . $name . '] =>' . $newline;
          $out .= simple_dump_build($value, $level + 2, $config, $visitedObjects, (string) $name);
          $index++;
        }

        $out .= $indent . '}' . $newline;
        return $out;
      }

      $out = $indent . 'object(' . $class . ') {' . $newline;
      $props = get_object_vars($var);
      $maxItems = $config['max_items_per_level'] ?? 50;
      $index = 0;

      foreach ($props as $name => $value) {
        if ($index >= $maxItems) {
          $out .= $indent . '  ... (' . (count($props) - $index) . ' more properties)' . $newline;
          break;
        }

        $out .= $indent . '  [' . $name . '] =>' . $newline;
        $out .= simple_dump_build($value, $level + 2, $config, $visitedObjects, (string) $name);
        $index++;
      }

      $out .= $indent . '}' . $newline;
      return $out;
    }

    if ($type === 'resource' || $type === 'resource (closed)') {
      $resType = get_resource_type($var);
      return $indent . 'resource(' . $resType . ')' . $newline;
    }

    $out = print_r($var, true);
    if (substr($out, -1) !== PHP_EOL) {
      $out .= $newline;
    }
    return $indent . $out;
  }
}

function simple_dump_raw($var, int $level, array $config, array &$visitedObjects, ?string $keyName = null, bool $ignoreLimits = false)
{
  $maxDepth = $ignoreLimits ? 100 : ($config['max_depth'] ?? 4);
  $maxItems = $ignoreLimits ? 10000 : ($config['max_items_per_level'] ?? 50);
  $maxStr = $ignoreLimits ? 1000000 : ($config['max_string_length'] ?? 2000);
  $sanitize = !empty($config['sanitize_sensitive']);
  $sensitiveKeys = $config['sensitive_keys'] ?? [];

  if ($sanitize && $keyName !== null && in_array((string) $keyName, $sensitiveKeys, true)) {
    return '*** HIDDEN ***';
  }

  if ($level >= $maxDepth) {
    return '... (max depth reached)';
  }

  $type = gettype($var);

  if ($type === 'string') {
    return strlen($var) > $maxStr ? substr($var, 0, $maxStr) . '... (truncated)' : $var;
  }

  if (in_array($type, ['integer', 'double', 'boolean', 'NULL'])) {
    return $var;
  }

  if ($type === 'array') {
    $count = count($var);
    $out = [];
    $index = 0;
    foreach ($var as $k => $v) {
      if ($index >= $maxItems) {
        $out['__truncated__'] = ($count - $index) . ' more items';
        break;
      }
      $out[$k] = simple_dump_raw($v, $level + 1, $config, $visitedObjects, (string) $k, $ignoreLimits);
      $index++;
    }
    return $out;
  }

  if ($type === 'object') {
    $class = get_class($var);
    $id = spl_object_id($var);
    if (isset($visitedObjects[$id])) {
      return 'RECURSIVE: ' . $class;
    }
    $visitedObjects[$id] = true;

    $out = ['__class__' => $class];

    if ($var instanceof \Throwable) {
      $out['message'] = $var->getMessage();
      $out['file'] = $var->getFile() . ':' . $var->getLine();
      $trace = $var->getTrace();
      $out['trace'] = [];
      foreach (array_slice($trace, 0, $maxItems) as $t) {
        $out['trace'][] = ($t['file'] ?? 'unknown') . ':' . ($t['line'] ?? '?') . ' -> ' . ($t['function'] ?? 'unknown');
      }
    } else {
      $props = get_object_vars($var);
      $index = 0;
      foreach ($props as $name => $value) {
        if ($index >= $maxItems) {
          $out['__truncated__'] = (count($props) - $index) . ' more properties';
          break;
        }
        $out[$name] = simple_dump_raw($value, $level + 1, $config, $visitedObjects, (string) $name, $ignoreLimits);
        $index++;
      }
    }
    return $out;
  }

  return (string) $var;
}

if (!function_exists('dump')) {
  function dump(...$vars)
  {
    $config = $GLOBALS['dump_config'] ?? [];

    if (function_exists('isProduction') && isProduction() && empty($config['allow_in_production'])) {
      return;
    }

    $mode = $config['mode'] ?? 'auto';
    $isCli = PHP_SAPI === 'cli';
    if ($mode === 'cli') {
      $isCli = true;
    } elseif ($mode === 'html') {
      $isCli = false;
    }

    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = $trace[1] ?? $trace[0] ?? null;

    $location = '';
    if ($caller) {
      $file = $caller['file'] ?? '';
      $line = $caller['line'] ?? null;
      if ($file !== '') {
        $location = $file;
      }
      if ($line !== null) {
        $location = ($location !== '' ? $location : 'unknown') . ':' . $line;
      }
    }

    $maxTotal = $config['max_total_output_length'] ?? 20000;

    foreach ($vars as $index => $var) {
      $visitedObjects = [];
      $output = simple_dump_build($var, 0, $config, $visitedObjects);

      $visitedObjectsRaw = [];
      $rawData = simple_dump_raw($var, 0, $config, $visitedObjectsRaw, null, true);

      if (strlen($output) > $maxTotal) {
        $output = substr($output, 0, $maxTotal) . PHP_EOL . '... (output truncated)';
      }

      if ($isCli) {
        $header = '==== dump';
        if ($location !== '') {
          $header .= ' (' . $location . ')';
        }
        $header .= ' #' . ($index + 1) . ' ====';
        echo $header . PHP_EOL;
        echo $output . PHP_EOL;
      } else {
        // Output ke HTML (Browser)
        echo '<pre style="background:#1e1e1e;color:#f5f5f5;padding:12px;margin:10px 0;font-size:12px;line-height:1.4;overflow:auto;border-radius:6px;border-left:4px solid #3498db;box-shadow:0 2px 5px rgba(0,0,0,0.2);">';
        if ($location !== '') {
          echo '<span style="color:#3498db;font-weight:bold;display:block;margin-bottom:8px;border-bottom:1px solid #333;padding-bottom:4px;">[' . htmlspecialchars($location, ENT_QUOTES, 'UTF-8') . '] #' . ($index + 1) . '</span>';
        }
        echo htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
        echo '</pre>';

        // Output ke JavaScript Console (Expandable)
        $jsonRawData = json_encode($rawData);
        $jsonLocation = json_encode($location);
        $label = 'PHP Dump #' . ($index + 1);

        echo "<script>
          (function() {
            const loc = $jsonLocation;
            const data = $jsonRawData;
            console.group('%c' + '$label' + (loc ? ' [' + loc + ']' : ''), 'color: #3498db; font-weight: bold;');
            console.log(data);
            console.groupEnd();
          })();
        </script>";
      }
    }
  }
}

if (!function_exists('csrf_token')) {
  /**
   * Get the CSRF token.
   *
   * @return string
   */
  function csrf_token()
  {
    $session = new \App\Services\SessionService();
    return $session->getCsrfToken();
  }
}

if (!function_exists('csrf_field')) {
  /**
   * Generate a CSRF token hidden input field.
   *
   * @return string
   */
  function csrf_field()
  {
    return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
  }
}

if (!function_exists('e')) {
  function e($value)
  {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('ulid')) {
  function ulid(): string
  {
    $time = (int) floor(microtime(true) * 1000);
    $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    $timeChars = '';
    for ($i = 0; $i < 10; $i++) {
      $mod = $time % 32;
      $timeChars = $alphabet[$mod] . $timeChars;
      $time = intdiv($time, 32);
    }

    $randomChars = '';
    for ($i = 0; $i < 16; $i++) {
      $randomChars .= $alphabet[random_int(0, 31)];
    }

    return $timeChars . $randomChars;
  }
}

if (!function_exists('uuidv4')) {
  function uuidv4(): string
  {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
  }
}

if (!function_exists('color')) {
  function color($text, $color)
  {
    $codes = [
      'green'  => "\033[32m",
      'yellow' => "\033[33m",
      'red'    => "\033[31m",
      'blue'   => "\033[34m",
      'cyan'   => "\033[36m",
      'bold'   => "\033[1m",
      'dim'    => "\033[2m",
      'gray'   => "\033[90m",
      'reset'  => "\033[0m"
    ];

    // Dukungan untuk multiple styles (misal: 'bold,green')
    $requested = explode(',', $color);
    $prefix = '';
    foreach ($requested as $style) {
      $prefix .= ($codes[trim($style)] ?? '');
    }

    return $prefix . $text . $codes['reset'];
  }
}

if (!function_exists('asset')) {
  /**
   * Get the path to a versioned asset file.
   *
   * @param string $path
   * @return string
   */
  function asset($path)
  {
    static $manifest = null;

    if ($manifest === null) {
      $manifestPath = __DIR__ . '/../../public/build/manifest.json';
      if (file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
      } else {
        $manifest = [];
      }
    }

    $path = ltrim($path, '/');

    // Cek apakah ada di manifest
    if (isset($manifest[$path])) {
      return getBaseUrl('build/' . $manifest[$path]);
    }

    // Fallback development (langsung ke build path)
    return getBaseUrl('build/' . $path);
  }
}
