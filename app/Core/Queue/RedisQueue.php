<?php

namespace App\Core\Queue;

use App\Core\Database\DatabaseManager;

class RedisQueue implements QueueInterface
{
  private $redis;

  public function __construct(DatabaseManager $db, string $connection = 'redis_queue')
  {
    $this->redis = $db->connection($connection);
  }

  public function push(string $jobClass, array $data = [], string $queue = 'default')
  {
    $payload = [
      'job_class' => $jobClass,
      'data' => $data,
      'attempts' => 0,
      'dispatched_at' => date('Y-m-d H:i:s')
    ];

    $queueKey = "queue:{$queue}";
    return $this->redis->lpush($queueKey, json_encode($payload));
  }

  public function pop(string $queue = 'default', bool $blocking = true)
  {
    $queueKey = "queue:{$queue}";

    if ($blocking) {
      // brpop returns [key, value] or null if timeout
      // timeout 5 seconds
      $result = $this->redis->brpop([$queueKey], 5);
    } else {
      // rpop returns value or null
      $value = $this->redis->rpop($queueKey);
      $result = $value ? [$queueKey, $value] : null;
    }

    if (!empty($result) && isset($result[1])) {
      return json_decode($result[1], true);
    }

    return null;
  }
}
