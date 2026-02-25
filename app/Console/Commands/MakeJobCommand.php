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

    // Pastikan folder addon/Jobs ada
    $jobsDir = __DIR__ . '/../../../addon/Jobs';
    if (!is_dir($jobsDir)) {
      if (!mkdir($jobsDir, 0755, true)) {
        echo color("Error: Tidak dapat membuat folder addon/Jobs\n", "red");
        return 1;
      }
    }

    $path = $jobsDir . "/{$name}.php";

    if (file_exists($path)) {
      echo color("Error: Job sudah ada!\n", "red");
      return 1;
    }

    $template = <<<'PHP'
<?php

declare(strict_types=1);

namespace Addon\Jobs;

use App\Core\Foundation\Application;

class {{CLASS_NAME}}
{
  public function __construct(
    private Application $app,
  ) {}

  /**
   * Execute the job.
   */
  public function handle(array $data = []): void
  {
    // Job logic here
    // TODO: Implement your job logic
    echo "Job {{CLASS_NAME}} is running with data: " . json_encode($data) . "\n";
  }
}
PHP;

    $content = str_replace('{{CLASS_NAME}}', $name, $template);

    // Pastikan folder ada sebelum file_put_contents
    $dir = dirname($path);
    if (!is_dir($dir)) {
      if (!mkdir($dir, 0755, true)) {
        echo color("Error: Tidak dapat membuat folder untuk job\n", "red");
        return 1;
      }
    }

    if (file_put_contents($path, $content) === false) {
      echo color("Error: Gagal membuat file job\n", "red");
      return 1;
    }

    echo color("SUCCESS:", "green") . " Job dibuat di " . color($path, "blue") . "\n";

    return 0;
  }
}
