<?php

namespace App\Core\Queue;

use App\Core\Database\Database;
use PDO;

class DatabaseQueue implements QueueInterface
{
  private Database $db;
  private string $table;

  public function __construct(Database $db, string $table = 'queues')
  {
    $this->db = $db;
    $this->table = $table;
  }

  public function push(string $jobClass, array $data = [], string $queue = 'default')
  {
    $payload = json_encode([
      'job_class' => $jobClass,
      'data' => $data,
      'attempts' => 0,
      'dispatched_at' => date('Y-m-d H:i:s')
    ]);

    $sql = "INSERT INTO `{$this->table}` (`queue`, `payload`, `attempts`, `reserved_at`, `available_at`, `created_at`) 
            VALUES (:queue, :payload, 0, NULL, :available_at, :created_at)";

    $now = time();
    $params = [
      'queue' => $queue,
      'payload' => $payload,
      'available_at' => $now,
      'created_at' => $now
    ];

    $stmt = $this->db->prepare($sql);
    return $stmt->execute($params);
  }

  public function pop(string $queue = 'default', bool $blocking = true)
  {
    // Database queue doesn't truly support blocking in the same way Redis does
    // We'll just do a single check. The worker loop handles the sleep.

    $now = time();
    $expiry = $now - 60; // 60 seconds timeout for reserved jobs

    // 1. Find and reserve a job atomically (using a transaction or clever SQL)
    // We'll use a transaction for safety
    try {
      $this->db->query("START TRANSACTION");

      // Find an available job
      $sql = "SELECT * FROM `{$this->table}` 
              WHERE `queue` = :queue 
              AND (`reserved_at` IS NULL OR `reserved_at` <= :expiry)
              AND `available_at` <= :now
              ORDER BY `id` ASC 
              LIMIT 1 
              FOR UPDATE";

      $stmt = $this->db->prepare($sql);
      $stmt->execute(['queue' => $queue, 'now' => $now, 'expiry' => $expiry]);
      $job = $stmt->fetch();

      if (!$job) {
        $this->db->query("COMMIT");
        return null;
      }

      // Reserve it
      $reserveSql = "UPDATE `{$this->table}` 
                     SET `reserved_at` = :now, `attempts` = `attempts` + 1 
                     WHERE `id` = :id";

      $reserveStmt = $this->db->prepare($reserveSql);
      $reserveStmt->execute(['now' => $now, 'id' => $job['id']]);

      $this->db->query("COMMIT");

      $payload = json_decode($job['payload'], true);
      $payload['id'] = $job['id']; // Store DB ID to delete later

      return $payload;
    } catch (\Throwable $e) {
      $this->db->query("ROLLBACK");
      throw $e;
    }
  }

  /**
   * Delete a job from the queue after successful execution.
   */
  public function delete(int $id): bool
  {
    $sql = "DELETE FROM `{$this->table}` WHERE `id` = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute(['id' => $id]);
  }
}
