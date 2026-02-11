<?php

namespace App\Console\Commands;

use App\Core\Foundation\Application;
use App\Console\Contracts\CommandInterface;

class AboutCommand implements CommandInterface
{
  public function __construct(
    private Application $app,
  ) {}

  public function getName(): string
  {
    return 'about';
  }

  public function getDescription(): string
  {
    return 'Informasi framework';
  }

  public function handle(array $arguments): int
  {
    echo color("Mazu Framework CLI\n", "cyan");
    echo "Creator: Mahfudz\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Project Root: " . __DIR__ . "/../../../\n";

    return 0;
  }
}
