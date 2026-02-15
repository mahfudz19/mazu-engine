<?php

namespace App\Services;

class ConfigService
{
  private array $config = [];

  public function __construct()
  {
    // Muat konfigurasi utama dari folder config
    $this->config = require __DIR__ . '/../../config/app.php';
  }

  public function get(string $key, $default = null)
  {
    // Mendukung notasi titik, cth: 'db.host'
    $keys = explode('.', $key);
    $value = $this->config;
    foreach ($keys as $k) {
      if (!isset($value[$k])) {
        return $default;
      }
      $value = $value[$k];
    }
    return $value;
  }
}
