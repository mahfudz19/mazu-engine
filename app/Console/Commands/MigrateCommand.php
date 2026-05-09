<?php

namespace App\Console\Commands;

use App\Core\Foundation\Application;
use App\Console\Contracts\CommandInterface;
use App\Core\Database\DatabaseManager;
use App\Core\Database\ModelSchemaMigrator;
use Throwable;

class MigrateCommand implements CommandInterface
{
  public function __construct(
    private Application $app,
  ) {}

  public function getName(): string
  {
    return 'migrate';
  }

  public function getDescription(): string
  {
    return 'Sinkronisasi schema tabel berdasarkan Model (opsional: nama model)';
  }

  public function handle(array $arguments): int
  {
    $container = $this->app->getContainer();
    $dbManager = $container->resolve(DatabaseManager::class);
    $migrator = new ModelSchemaMigrator($dbManager);

    $targetModel = $arguments[0] ?? null;

    if ($targetModel) {
      if (!str_contains($targetModel, '\\')) {
        $targetModel = 'Addon\\Models\\' . ltrim($targetModel, '\\');
      }

      $result = $migrator->migrateModel($targetModel, $container);

      if ($result === null) {
        echo color("Model tidak memiliki schema atau tidak ada perubahan.\n", "yellow");
      } else {
        echo color("Migrated model: ", "green") . $targetModel . " (table: {$result})\n";
      }

      return 0;
    }

    $modelsDir = __DIR__ . '/../../..' . '/addon/Models';

    try {
      $executed = $migrator->migrateAll($modelsDir, $container);
    } catch (Throwable $e) {
      echo color("Migrate dibatalkan karena error pada salah satu model.\n", "red");
      echo color($e->getMessage() . "\n", "red");
      return 1;
    }

    if (empty($executed)) {
      echo color("Tidak ada model berschema yang perlu dimigrate.\n", "yellow");
    } else {
      foreach ($executed as $info) {
        echo color("Migrated: ", "green") . $info['class'] . " (table: {$info['table']})\n";
      }
    }

    return 0;
  }
}
