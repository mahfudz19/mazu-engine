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

    // Database::query() menangkapPDOException dan return false, jadi kita harus cek return value
    $result = $db->query($sql);

    if ($result === false) {
      // Query gagal tapi exception ditangkap di Database::query()
      // Kita lempar exception agar error terlihat di migration
      throw new \RuntimeException(
        "Gagal membuat tabel '{$table}': Query SQL gagal. " .
          "Periksa log error di storage/logs/app.log untuk detail. " .
          "\nSQL: " . $sql
      );
    }
  }

  private function buildCreateTableSql(string $table, array $schema): string
  {
    $columnsSql = [];
    $primaryKeys = [];
    $foreignKeys = [];
    $uniqueKeys = [];

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

      // Support ON UPDATE (khusus timestamp/datetime)
      if (!empty($def['on_update'])) {
        $parts[] = "ON UPDATE {$def['on_update']}";
      }

      $columnsSql[] = implode(' ', $parts);

      if (!empty($def['primary'])) {
        $primaryKeys[] = "`{$name}`";
      }

      // Support unique constraint
      if (!empty($def['unique'])) {
        $uniqueKeys[] = "`{$name}`";
      }

      // Support foreign key constraint
      if (!empty($def['foreign'])) {
        $foreignRef = $def['foreign']; // format: 'table.column'
        [$foreignTable, $foreignColumn] = explode('.', $foreignRef);
        $onDelete = $def['on_delete'] ?? 'CASCADE';
        // Gunakan 'fk_on_update' untuk foreign key agar tidak konflik dengan 'on_update' untuk timestamp
        $fkOnUpdate = $def['fk_on_update'] ?? $def['on_update'] ?? 'RESTRICT';
        $foreignKeys[] = "CONSTRAINT `fk_{$table}_{$name}` FOREIGN KEY (`{$name}`) REFERENCES `{$foreignTable}`(`{$foreignColumn}`) ON DELETE {$onDelete} ON UPDATE {$fkOnUpdate}";
      }
    }

    if (!empty($primaryKeys)) {
      $columnsSql[] = 'PRIMARY KEY (' . implode(', ', $primaryKeys) . ')';
    }

    if (!empty($uniqueKeys)) {
      $columnsSql[] = 'UNIQUE KEY `uq_' . $table . '_' . implode('_', array_map(fn($k) => str_replace('`', '', $k), $uniqueKeys)) . '` (' . implode(', ', $uniqueKeys) . ')';
    }

    // Add foreign key constraints
    foreach ($foreignKeys as $fk) {
      $columnsSql[] = $fk;
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
        return 'DATETIME';
      case 'timestamp':
        return 'TIMESTAMP';
      case 'date':
        return 'DATE';
      case 'boolean':
      case 'bool':
        return 'TINYINT(1)';
      case 'enum':
        if (empty($def['values']) || !is_array($def['values'])) {
          throw new \RuntimeException("Field type 'enum' requires 'values' array definition.");
        }
        // Format: ENUM('A', 'B', 'C')
        $values = array_map(fn($v) => "'$v'", $def['values']);
        return "ENUM(" . implode(", ", $values) . ")";
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

    // Pengecualian untuk ekspresi SQL function
    if ($default === 'CURRENT_TIMESTAMP') {
      return "DEFAULT CURRENT_TIMESTAMP";
    }

    $escaped = str_replace("'", "''", (string) $default);
    return "DEFAULT '{$escaped}'";
  }
}
