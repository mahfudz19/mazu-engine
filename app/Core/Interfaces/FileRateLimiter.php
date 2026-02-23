<?php

namespace App\Services;

use App\Core\Interfaces\RateLimiterInterface;

class FileRateLimiter implements RateLimiterInterface
{
  private string $directory;

  public function __construct(?string $directory = null)
  {
    $base = $directory ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mazu_rate_limiter';
    $this->directory = rtrim($base, DIRECTORY_SEPARATOR);

    if (!is_dir($this->directory)) {
      @mkdir($this->directory, 0777, true);
    }
  }

  public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
  {
    $data = $this->read($key);
    $now = time();

    if ($data['expires_at'] <= $now) {
      return false;
    }

    return $data['hits'] >= $maxAttempts;
  }

  public function hit(string $key, int $decaySeconds): void
  {
    $data = $this->read($key);
    $now = time();

    if ($data['expires_at'] <= $now) {
      $data = [
        'hits' => 0,
        'expires_at' => 0,
      ];
    }

    $data['hits'] = ($data['hits'] ?? 0) + 1;

    if ($data['expires_at'] <= $now) {
      $data['expires_at'] = $now + $decaySeconds;
    }

    $this->write($key, $data);
  }

  public function remaining(string $key, int $maxAttempts, int $decaySeconds): int
  {
    $data = $this->read($key);
    $now = time();

    if ($data['expires_at'] <= $now) {
      return $maxAttempts;
    }

    $remaining = $maxAttempts - ($data['hits'] ?? 0);
    return $remaining > 0 ? $remaining : 0;
  }

  public function availableIn(string $key, int $decaySeconds): int
  {
    $data = $this->read($key);
    $now = time();

    if ($data['expires_at'] <= $now) {
      return 0;
    }

    return max(0, $data['expires_at'] - $now);
  }

  private function filePath(string $key): string
  {
    return $this->directory . DIRECTORY_SEPARATOR . sha1($key) . '.json';
  }

  private function read(string $key): array
  {
    $path = $this->filePath($key);
    if (!is_file($path)) {
      return [
        'hits' => 0,
        'expires_at' => 0,
      ];
    }

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
      return [
        'hits' => 0,
        'expires_at' => 0,
      ];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['hits'], $data['expires_at'])) {
      return [
        'hits' => 0,
        'expires_at' => 0,
      ];
    }

    return [
      'hits' => (int)$data['hits'],
      'expires_at' => (int)$data['expires_at'],
    ];
  }

  private function write(string $key, array $data): void
  {
    $path = $this->filePath($key);
    @file_put_contents($path, json_encode($data));
  }
}
