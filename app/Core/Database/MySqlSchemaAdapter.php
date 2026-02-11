<?php

namespace App\Core\Database;

class MySqlSchemaAdapter implements SchemaAdapterInterface
{
  public function tableExists(Database $db, string $table): bool
  {
    // FIX: Gunakan information_schema agar support prepared statement
    // SHOW TABLES LIKE ? tidak disupport oleh PDO MySQL dengan placeholder
    $sql = "SELECT count(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$table]);

    return (bool) $stmt->fetchColumn();
  }

  public function createTable(Database $db, string $table, array $schema): void
  {
    $sql = $this->buildCreateTableSql($table, $schema);
    // Kita asumsikan Database punya method query() atau kita gunakan prepare/execute
    $db->query($sql);
  }

  private function buildCreateTableSql(string $table, array $schema): string
  {
    $columnsSql = [];
    $primaryKeys = [];

    foreach ($schema as $name => $def) {
      $type = strtolower($def['type'] ?? 'varchar');
      $columnType = $this->mapColumnType($type, $def);

      $parts = ["`{$name}` {$columnType}"];

      $nullable = $def['nullable'] ?? false;
      $parts[] = $nullable ? 'NULL' : 'NOT NULL';

      if (array_key_exists('default', $def)) {
        $parts[] = $this->buildDefaultClause($def['default']);
      }

      if (!empty($def['auto_increment'])) {
        $parts[] = 'AUTO_INCREMENT';
      }

      $columnsSql[] = implode(' ', $parts);

      if (!empty($def['primary'])) {
        $primaryKeys[] = "`{$name}`";
      }
    }

    if (!empty($primaryKeys)) {
      $columnsSql[] = 'PRIMARY KEY (' . implode(', ', $primaryKeys) . ')';
    }

    $columns = implode(",\n  ", $columnsSql);

    return "CREATE TABLE IF NOT EXISTS `{$table}` (\n  {$columns}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  }

  private function mapColumnType(string $type, array $def): string
  {
    switch ($type) {
      case 'id':
        return 'BIGINT UNSIGNED AUTO_INCREMENT';
      case 'ulid':
        return 'CHAR(26)';
      case 'uuid':
        return 'CHAR(36)';
      case 'bigint':
        $base = 'BIGINT';
        break;
      case 'int':
      case 'integer':
        $base = 'INT';
        break;
      case 'string': // Alias untuk varchar standar Laravel
      case 'varchar':
        $length = $def['length'] ?? 255;
        return "VARCHAR({$length})";
      case 'text':
      case 'longtext':
        return 'LONGTEXT';
      case 'mediumtext':
        return 'MEDIUMTEXT';
      case 'datetime':
      case 'timestamp':
        return 'DATETIME';
      case 'date':
        return 'DATE';
      case 'boolean':
      case 'bool':
        return 'TINYINT(1)';
      case 'json':
        return 'JSON';
      case 'decimal':
        $precision = $def['precision'] ?? 8;
        $scale = $def['scale'] ?? 2;
        return "DECIMAL({$precision}, {$scale})";
      default:
        return strtoupper($type);
    }

    if (!empty($def['unsigned']) && in_array($type, ['bigint', 'int', 'integer'], true)) {
      $base .= ' UNSIGNED';
    }

    return $base;
  }

  private function buildDefaultClause(mixed $default): string
  {
    if ($default === null) {
      return 'DEFAULT NULL';
    }

    if (is_int($default) || is_float($default)) {
      return "DEFAULT {$default}";
    }

    if (is_bool($default)) {
      return 'DEFAULT ' . ($default ? 1 : 0);
    }

    $escaped = str_replace("'", "''", (string) $default);
    return "DEFAULT '{$escaped}'";
  }
}
