<?php

namespace App\Core\Queue;

use App\Core\Database\DatabaseManager;
use App\Core\Queue\RedisQueue;
use App\Core\Queue\QueueInterface;
use App\Services\ConfigService;
use InvalidArgumentException;

class JobDispatcher
{
  private QueueInterface $queue;

  public function __construct(DatabaseManager $db, ConfigService $config)
  {
    $driver = $config->get('queue.driver', 'redis');
    $connection = $config->get('queue.connection', 'redis_queue');

    if ($driver === 'redis') {
      $this->queue = new RedisQueue($db, $connection);
      return;
    }

    throw new InvalidArgumentException("Queue driver [{$driver}] not supported.");
  }

  public function dispatch(string $jobClass, array $data = [], string $queue = 'default'): void
  {
    try {
      $this->queue->push($jobClass, $data, $queue);
    } catch (\Throwable $e) {
      $payloadJson = json_encode(['job' => $jobClass, 'data' => $data]);
      $message = "[JobDispatcher] ERROR DISPATCHING: Job '{$jobClass}' to queue '{$queue}'. Error: " . $e->getMessage() . ". Payload: " . substr($payloadJson, 0, 200) . "...\nException Trace:\n" . $e->getTraceAsString();
      error_log($message);
      throw $e;
    }
  }
}
