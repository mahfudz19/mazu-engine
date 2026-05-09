<?php

namespace App\Core\Database;

use App\Core\Database\Database;
use App\Core\Database\DatabaseManager;
use App\Core\Database\Model;
use App\Core\Database\SchemaAdapterInterface;
use App\Core\Database\MySqlSchemaAdapter;
use App\Core\Foundation\Container;

class ModelSchemaMigrator
{
  public function __construct(private DatabaseManager $dbManager) {}

  public function migrateModel(string $className, Container $container): ?string
  {
    if (!class_exists($className)) {
      return null;
    }

    $instance = $container->resolve($className);

    if (!$instance instanceof Model) {
      return null;
    }

    $schema = $instance->getSchema();

    // Jika schema benar-benar kosong, jangan lakukan migrasi (meskipun timestamps true)
    if (empty($schema)) {
      return null;
    }

    if ($instance->usesTimestamps()) {
      $created = $instance->getCreatedAtColumn();
      $updated = $instance->getUpdatedAtColumn();

      if (!isset($schema[$created])) {
        $schema[$created] = [
          'type' => 'timestamp',
          'nullable' => false,
          'default' => 'CURRENT_TIMESTAMP',
        ];
      }

      if (!isset($schema[$updated])) {
        $schema[$updated] = [
          'type' => 'timestamp',
          'nullable' => false,
          'default' => 'CURRENT_TIMESTAMP',
          'on_update' => 'CURRENT_TIMESTAMP', // Fitur baru di adapter
        ];
      }
    }

    $table  = $instance->getTableName();

    if (empty($schema) || !$table) {
      return null;
    }

    $connectionName = $instance->getConnectionName();
    $db = $connectionName
      ? $this->dbManager->connection($connectionName)
      : $this->dbManager->connection();

    $adapter = $this->getAdapter($db);

    if ($adapter->tableExists($db, $table)) {
      // Tabel sudah ada, skip tapi log informasi
      echo color("Tabel '{$table}' sudah ada, dilewati.\n", "yellow");
      return null;
    }

    try {
      $adapter->createTable($db, $table, $schema);
      echo color("Tabel '{$table}' berhasil dibuat.\n", "green");
    } catch (\RuntimeException $e) {
      // Re-throw dengan informasi lebih detail
      throw $e;
    }

    $this->seedIfNeeded($instance, $db, $table);

    return $table;
  }

  private function getAdapter(Database $db): SchemaAdapterInterface
  {
    $driver = $db->getDriverName();

    return match ($driver) {
      'mysql' => new MySqlSchemaAdapter(),
      default => throw new \RuntimeException("Schema migration not supported for driver: {$driver}"),
    };
  }

  public function migrateAll(string $modelsDir, Container $container): array
  {
    $results = [];
    $files = glob($modelsDir . '/*.php') ?: [];

    // Kumpulkan semua class name dulu
    $models = [];
    foreach ($files as $file) {
      $base = basename($file, '.php');
      $models[] = 'Addon\\Models\\' . $base;
    }

    // Urutkan berdasarkan foreign key dependencies
    $models = $this->sortModelsByDependencies($models);

    foreach ($models as $className) {
      try {
        $table = $this->migrateModel($className, $container);
      } catch (\Throwable $e) {
        throw new \RuntimeException("Gagal migrate model {$className}: " . $e->getMessage(), 0, $e);
      }

      if ($table !== null) {
        $results[] = [
          'class' => $className,
          'table' => $table,
        ];
      }
    }

    return $results;
  }

  /**
   * Urutkan model berdasarkan foreign key dependencies
   * Model yang direferensikan oleh model lain harus di-migrate terlebih dahulu
   */
  private function sortModelsByDependencies(array $models): array
  {
    // Ambil instance dari setiap model untuk mendapatkan schema
    $container = $this->dbManager->connection(); // Dummy untuk container check

    // Bangun dependency graph
    $dependencies = [];
    foreach ($models as $modelClass) {
      if (!class_exists($modelClass)) {
        continue;
      }

      // Dapatkan nama tabel yang direferensikan oleh foreign keys
      $reflection = new \ReflectionClass($modelClass);
      $instance = $reflection->newInstanceWithoutConstructor();
      $schema = $instance->getSchema() ?? [];

      $referencedTables = [];
      foreach ($schema as $field => $def) {
        if (isset($def['foreign']) && is_string($def['foreign'])) {
          // Parse 'table.column' format
          $parts = explode('.', $def['foreign']);
          if (count($parts) === 2) {
            $referencedTables[] = $parts[0];
          }
        }
      }

      $dependencies[$modelClass] = $referencedTables;
    }

    // Topological sort - model tanpa dependensi pertama, model dengan dependensi terakhir
    $sorted = [];
    $visited = [];
    $visiting = []; // Untuk detect circular dependencies

    $visit = function ($modelClass) use (&$visit, &$sorted, &$visited, &$visiting, $dependencies) {
      if (isset($visited[$modelClass])) {
        return;
      }

      if (isset($visiting[$modelClass])) {
        // Circular dependency detected, skip
        return;
      }

      $visiting[$modelClass] = true;

      // Visit dependencies first
      $modelTable = $this->getModelTableName($modelClass);
      foreach ($dependencies[$modelClass] ?? [] as $referencedTable) {
        // Cari model yang memiliki tabel ini
        foreach ($dependencies as $depClass => $depTables) {
          if ($this->getModelTableName($depClass) === $referencedTable) {
            $visit($depClass);
            break;
          }
        }
      }

      unset($visiting[$modelClass]);
      $visited[$modelClass] = true;
      $sorted[] = $modelClass;
    };

    foreach ($models as $modelClass) {
      $visit($modelClass);
    }

    return $sorted;
  }

  /**
   * Dapatkan nama tabel dari model class
   */
  private function getModelTableName(string $modelClass): string
  {
    try {
      $reflection = new \ReflectionClass($modelClass);
      $instance = $reflection->newInstanceWithoutConstructor();
      return $instance->getTableName();
    } catch (\Throwable $e) {
      // Fallback: convert class name to table name
      $baseName = str_replace(['Addon\\Models\\', 'Model'], '', $modelClass);
      return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $baseName)) . 's';
    }
  }

  private function seedIfNeeded(Model $model, Database $db, string $table): void
  {
    $rows = $model->getSeed();

    if (empty($rows)) {
      return;
    }

    $schema = $model->getSchema();
    $primaryIdField = null;
    $primaryIdType = null;

    foreach ($schema as $name => $def) {
      $type = strtolower($def['type'] ?? '');
      if (!empty($def['primary']) && in_array($type, ['ulid', 'uuid'], true)) {
        $primaryIdField = $name;
        $primaryIdType = $type;
        break;
      }
    }

    if ($primaryIdField !== null) {
      foreach ($rows as &$row) {
        if (!array_key_exists($primaryIdField, $row) || $row[$primaryIdField] === null) {
          if ($primaryIdType === 'ulid' && function_exists('ulid')) {
            $row[$primaryIdField] = ulid();
          } elseif ($primaryIdType === 'uuid' && function_exists('uuidv4')) {
            $row[$primaryIdField] = uuidv4();
          }
        }
      }
      unset($row);
    }

    if ($model->usesTimestamps()) {
      $created = $model->getCreatedAtColumn();
      $updated = $model->getUpdatedAtColumn();

      foreach ($rows as &$row) {
        $now = date('Y-m-d H:i:s');

        if (!array_key_exists($created, $row)) {
          $row[$created] = $now;
        }

        if (!array_key_exists($updated, $row)) {
          $row[$updated] = $now;
        }
      }
      unset($row);
    }

    $columns = array_keys(reset($rows));

    $columnList = '`' . implode('`, `', $columns) . '`';
    $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $valuesSql = implode(', ', array_fill(0, count($rows), $placeholders));

    $sql = "INSERT INTO `{$table}` ({$columnList}) VALUES {$valuesSql}";

    $values = [];
    foreach ($rows as $row) {
      foreach ($columns as $col) {
        $values[] = $row[$col] ?? null;
      }
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($values);
  }
}
