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

    // Cek koneksi database terlebih dahulu
    try {
      $dbManager = $container->resolve(DatabaseManager::class);
      $db = $dbManager->connection();
      $stmt = $db->prepare('SELECT DATABASE()');
      $stmt->execute();
      $dbName = $stmt->fetchColumn();
      echo color("Terhubung ke database: ", "green") . $dbName . "\n";
    } catch (Throwable $e) {
      echo color("ERROR: Tidak dapat terhubung ke database!\n", "red");
      echo color("Error class: ", "red") . get_class($e) . "\n";
      echo color("Error message: ", "red") . $e->getMessage() . "\n";
      echo color("Error code: ", "red") . $e->getCode() . "\n";
      return 1;
    }

    try {
      $executed = $migrator->migrateAll($modelsDir, $container);
    } catch (Throwable $e) {
      echo color("Migrate dibatalkan karena error pada salah satu model.\n", "red");
      echo color("Error class: ", "red") . get_class($e) . "\n";
      echo color("Error message: ", "red") . $e->getMessage() . "\n";
      echo color("Error code: ", "red") . $e->getCode() . "\n";
      echo color("Stack trace:\n", "red") . $e->getTraceAsString() . "\n";

      // Tampilkan previous exception jika ada
      $previous = $e->getPrevious();
      while ($previous) {
        echo color("\nPrevious error:\n", "red");
        echo color("Message: ", "red") . $previous->getMessage() . "\n";
        echo color("Code: ", "red") . $previous->getCode() . "\n";
        $previous = $previous->getPrevious();
      }

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
