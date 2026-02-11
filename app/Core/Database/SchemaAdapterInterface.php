<?php

namespace App\Core\Database;

interface SchemaAdapterInterface
{
  /**
   * Cek apakah tabel/collection sudah ada.
   */
  public function tableExists(Database $db, string $table): bool;

  /**
   * Buat tabel/collection baru berdasarkan schema.
   */
  public function createTable(Database $db, string $table, array $schema): void;
}
