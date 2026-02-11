<?php

namespace App\Console\Commands;

use App\Core\Foundation\Application;
use App\Console\Contracts\CommandInterface;

class MakeJobCommand implements CommandInterface
{
  public function __construct(
    private Application $app,
  ) {}

  public function getName(): string
  {
    return 'make:job';
  }

  public function getDescription(): string
  {
    return 'Membuat job baru di addon/Jobs';
  }

  public function handle(array $arguments): int
  {
    $name = $arguments[0] ?? null;
    if (!$name) {
      echo color("Error: Nama job harus diisi.\n", "red");
      return 1;
    }

    $name = ucfirst($name);

    if (!str_ends_with($name, 'Job')) {
      $name .= 'Job';
    }

    $root = __DIR__ . '/../../..';
    $path = $root . "/addon/Jobs/{$name}.php";

    if (file_exists($path)) {
      echo color("Error: Job sudah ada!\n", "red");
      return 1;
    }

    $template = <<<'PHP'
<?php

declare(strict_types=1);

namespace Addon\Jobs;

class {{CLASS_NAME}}
{
  /**
   * Execute the job.
   */
  public function handle(array $data = []): void
  {
    // Job logic here
  }
}

PHP;
    $content = str_replace('{{CLASS_NAME}}', $name, $template);

    file_put_contents($path, $content);
    echo color("SUCCESS:", "green") . " Job dibuat di " . color($path, "blue") . "\n";

    return 0;
  }
}
