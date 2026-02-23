<?php

namespace App\Core\Interfaces;

interface RateLimiterInterface
{
  public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool;

  public function hit(string $key, int $decaySeconds): void;

  public function remaining(string $key, int $maxAttempts, int $decaySeconds): int;

  public function availableIn(string $key, int $decaySeconds): int;
}
