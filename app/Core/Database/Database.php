<?php

namespace App\Core\Database;

use \PDO;
use \PDOException;
use App\Services\ConfigService;

class Database
{
  private $connection;
  private string $driverName = 'mysql';

  public function __construct($config)
  {
    if ($config instanceof ConfigService) {
      $dbConfig = $config->get('db');
    } else {
      $dbConfig = $config;
    }

    if (!$dbConfig) {
      throw new \RuntimeException("Konfigurasi database tidak ditemukan.");
    }

    $driver   = $dbConfig['driver'] ?? 'mysql';
    $host     = $dbConfig['host'] ?? null;
    $port     = $dbConfig['port'] ?? '3306';
    $username = $dbConfig['username'] ?? null;
    $password = $dbConfig['password'] ?? null;
    $database = $dbConfig['dbname'] ?? $dbConfig['database'] ?? null;
    $socket   = $dbConfig['unix_socket'] ?? null;
    $options  = $dbConfig['options'] ?? [];

    $this->driverName = $driver;
    $this->connect($driver, $host, $port, $username, $password, $database, $socket, $options);
  }

  public function getDriverName(): string
  {
    return $this->driverName;
  }

  private function connect($driver, $host, $port, $username, $password, $database, $socket = null, $options = [])
  {
    try {
      if ($driver === 'sqlite') {
        $dsn = "sqlite:{$database}";
      } elseif ($driver === 'pgsql') {
        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
      } elseif ($socket) {
        $dsn = "mysql:unix_socket={$socket};dbname={$database};charset=utf8mb4";
      } else {
        // Default mysql
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
      }

      $defaultOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true,
      ];

      // Merge default options with provided options
      $finalOptions = $options + $defaultOptions;

      $this->connection = new PDO($dsn, $username, $password, $finalOptions);
    } catch (PDOException $e) {
      $this->handleConnectionError($e);
    }
  }

  private function handleConnectionError(PDOException $e)
  {
    throw new \RuntimeException(
      "Koneksi database gagal: " . $e->getMessage(),
      (int)$e->getCode(),
      $e
    );
  }

  public function prepare($sql)
  {
    return $this->connection->prepare($sql);
  }

  /**
   * Menjalankan query dengan parameter binding.
   * @param string $sql Query SQL yang akan dijalankan.
   * @param array $params Parameter untuk di-bind.
   * @return bool True jika berhasil, false jika gagal.
   */
  public function query(string $sql, array $params = []): bool
  {
    try {
      $stmt = $this->prepare($sql);
      return $stmt->execute($params);
    } catch (PDOException $e) {
      // anda bisa menambahkan logging di sini jika perlu
      error_log("Database query failed: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Mengembalikan ID dari baris terakhir yang dimasukkan atau nilai urutan.
   * @param string|null $name Nama objek urutan dari mana ID harus diambil.
   * @return string
   */
  public function lastInsertId(?string $name = null): string
  {
    return $this->connection->lastInsertId($name);
  }

  /**
   * Memulai transaksi.
   * @return bool
   */
  public function beginTransaction(): bool
  {
    return $this->connection->beginTransaction();
  }

  /**
   * Melakukan commit transaksi.
   * @return bool
   */
  public function commit(): bool
  {
    return $this->connection->commit();
  }

  /**
   * Melakukan rollback transaksi.
   * @return bool
   */
  public function rollBack(): bool
  {
    return $this->connection->rollBack();
  }

  /**
   * Memeriksa apakah transaksi sedang aktif.
   * @return bool
   */
  public function inTransaction(): bool
  {
    return $this->connection->inTransaction();
  }
}
