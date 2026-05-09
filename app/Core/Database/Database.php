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
      $this->handleConnectionError($e, $driver, $host, $port, $database, $username);
    }
  }

  private function handleConnectionError(\PDOException $e, $driver, $host, $port, $database, $username)
  {
    $errorMsg = $e->getMessage();
    $friendlyMessage = "Gagal terhubung ke database. ";
    $solution = "";

    // 1. Handle Error: Driver tidak ada (Sering terjadi di CLI/Worker)
    if (str_contains($errorMsg, 'could not find driver')) {
      $friendlyMessage .= "Driver PDO untuk '{$driver}' tidak ditemukan.";
      $solution = "Solusi: Install ekstensi PHP yang sesuai di server (contoh: 'sudo apt install php-mysql').";
    }
    // 2. Handle Error: Connection Refused / Port Salah (Sering terjadi saat migrasi ke server)
    elseif (str_contains($errorMsg, 'Connection refused') || str_contains($errorMsg, '2002')) {
      $friendlyMessage .= "Server menolak koneksi pada host '{$host}' port '{$port}'.";
      $solution = "Solusi: Pastikan service MySQL/MariaDB sedang menyala, dan cek nilai DB_HOST & DB_PORT di file .env.";
    }
    // 3. Handle Error: Access Denied / Password Salah
    elseif (str_contains($errorMsg, 'Access denied') || str_contains($errorMsg, '1045')) {
      $friendlyMessage .= "Akses ditolak untuk user '{$username}'.";
      $solution = "Solusi: Periksa kembali kecocokan DB_USER dan DB_PASS di file .env Anda.";
    }
    // 4. Handle Error: Database tidak ditemukan
    elseif (str_contains($errorMsg, 'Unknown database') || str_contains($errorMsg, '1049')) {
      $friendlyMessage .= "Database bernama '{$database}' tidak ditemukan.";
      $solution = "Solusi: Pastikan nama database di DB_NAME sudah benar, atau buat database tersebut di MySQL terlebih dahulu.";
    }
    // Default Fallback
    else {
      $friendlyMessage .= "Kesalahan tidak dikenal.";
      $solution = "Pesan teknis: " . $errorMsg;
    }

    // Gabungkan pesan menjadi satu string yang rapi
    $finalMessage = "\n[DATABASE ERROR] {$friendlyMessage}\n👉 {$solution}\n";

    // Jika sistem memiliki fungsi logger (misal bawaan Mazu), catat error-nya tanpa mengekspos password
    if (function_exists('logger')) {
      logger()->error("Database Connection Failed", [
        'host' => $host,
        'database' => $database,
        'user' => $username,
        'error' => $errorMsg
      ]);
    }

    throw new \RuntimeException($finalMessage, (int)$e->getCode(), $e);
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
      logger()->error("Database query failed: " . $sql . "; error =>" . $e->getMessage(), ['e' => $e]);
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
