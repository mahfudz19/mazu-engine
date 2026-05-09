<?php

namespace App\Console\Commands;

use App\Core\Foundation\Application;
use App\Console\Contracts\CommandInterface;

class RouteCacheCommand implements CommandInterface
{
  public function __construct(
    private Application $app,
  ) {}

  public function getName(): string
  {
    return 'route:cache';
  }

  public function getDescription(): string
  {
    return 'Membuat cache routing untuk performa tinggi';
  }

  public function handle(array $arguments): int
  {
    echo color("Membangun cache rute...\n", "yellow");
    require_once __DIR__ . '/../../../scripts/route-cache.php';

    return 0;
  }
}
