<?php

namespace App\Core\Database;

use App\Core\Database\Database;
use App\Services\ConfigService;
use InvalidArgumentException;

class DatabaseManager
{
  /**
   * Array koneksi database yang aktif.
   * @var array
   */
  private array $connections = [];

  /**
   * Service untuk mengambil konfigurasi.
   * @var ConfigService
   */
  private ConfigService $config;

  public function __construct(ConfigService $config)
  {
    $this->config = $config;
  }

  /**
   * Mengambil instance koneksi database.
   *
   * @param string|null $name Nama koneksi (opsional). Jika null, menggunakan default.
   * @return mixed
   * @throws InvalidArgumentException
   */
  public function connection(?string $name = null)
  {
    $name = $name ?: $this->config->get('database.default');

    if (!isset($this->connections[$name])) {
      $this->connections[$name] = $this->makeConnection($name);
    }

    return $this->connections[$name];
  }

  /**
   * Membuat koneksi database baru berdasarkan konfigurasi.
   *
   * @param string $name
   * @return mixed
   * @throws InvalidArgumentException
   */
  protected function makeConnection(string $name)
  {
    $config = $this->config->get("database.connections.{$name}");

    if (!$config) {
      throw new InvalidArgumentException("Database connection [{$name}] not configured.");
    }

    $driver = $config['driver'] ?? 'mysql';

    if ($driver === 'redis') {
      return $this->createRedisConnection($config);
    }

    // Default to PDO Database class (MySQL, SQLite, etc)
    return $this->createPdoConnection($config);
  }

  protected function createRedisConnection(array $config)
  {
    $parameters = [
      'scheme' => 'tcp',
      'host'   => $config['host'],
      'port'   => $config['port'] ?? 6379,
      'database' => $config['database'] ?? 0,
      'read_write_timeout' => 0,
    ];

    if (!empty($config['password'])) {
      $parameters['password'] = $config['password'];
    }

    return new \Predis\Client($parameters);
  }

  protected function createPdoConnection(array $config)
  {
    return new Database($config);
  }

  /**
   * Proxy call to default connection.
   * Memudahkan jika ingin memanggil method Database langsung dari Manager.
   */
  public function __call($method, $parameters)
  {
    return $this->connection()->$method(...$parameters);
  }
}
