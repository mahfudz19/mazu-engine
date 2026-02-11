<?php

namespace App\Core\Database;

use App\Core\Database\Database;
use App\Core\Database\DatabaseManager;

abstract class Model
{
  protected Database $db;

  protected ?string $connection = null;
  protected string $table = '';
  protected array $schema = [];
  protected bool $timestamps = true;
  protected string $createdAtColumn = 'created_at';
  protected string $updatedAtColumn = 'updated_at';

  protected array $seed = [];

  public function __construct(DatabaseManager $manager)
  {
    $connectionName = $this->connection;

    if ($connectionName === null || $connectionName === '') {
      $this->db = $manager->connection();
    } else {
      $this->db = $manager->connection($connectionName);
    }
  }

  public function getDb(): Database
  {
    return $this->db;
  }

  public function getConnectionName(): ?string
  {
    return $this->connection;
  }

  public function getTableName(): string
  {
    return $this->table;
  }

  public function getSchema(): array
  {
    return $this->schema;
  }

  public function usesTimestamps(): bool
  {
    return $this->timestamps;
  }

  public function getCreatedAtColumn(): string
  {
    return $this->createdAtColumn;
  }

  public function getUpdatedAtColumn(): string
  {
    return $this->updatedAtColumn;
  }

  public function getSeed(): array
  {
    return $this->seed;
  }
}
