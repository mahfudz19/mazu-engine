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
          'type' => 'datetime',
          'nullable' => false,
        ];
      }

      if (!isset($schema[$updated])) {
        $schema[$updated] = [
          'type' => 'datetime',
          'nullable' => false,
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
      return null;
    }

    $adapter->createTable($db, $table, $schema);

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

    foreach ($files as $file) {
      $base = basename($file, '.php');
      $className = 'Addon\\Models\\' . $base;

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
