<?php

namespace App\Console\Commands;

use App\Core\Foundation\Application;
use App\Console\Contracts\CommandInterface;
use App\Core\Database\DatabaseManager;
use App\Core\Queue\RedisQueue;
use App\Services\ConfigService;
use Throwable;

class QueueWorkCommand implements CommandInterface
{
  private const MAX_JOBS_PER_RUN = 1000;
  private const MAX_MEMORY_MB = 128;
  private const MAX_RUNTIME_SECONDS = 3600;

  private bool $shouldQuit = false;

  public function __construct(
    private Application $app,
  ) {}

  public function getName(): string
  {
    return 'queue:work';
  }

  public function getDescription(): string
  {
    return 'Menjalankan worker queue (Usage: php mazu queue:work [queue_name] [--daemon])';
  }

  public function handle(array $arguments): int
  {
    $queueName = 'default';
    $isDaemon = false;

    foreach ($arguments as $arg) {
      if ($arg === '--daemon') {
        $isDaemon = true;
      } else {
        $queueName = $arg;
      }
    }

    $logFileName = "worker-{$queueName}.log";
    $mode = $isDaemon ? 'DAEMON' : 'CRON';

    $this->log("{$mode} MODE: Worker started. Listening for jobs on 'queue:{$queueName}'...", $logFileName);

    if (function_exists('pcntl_async_signals')) {
      pcntl_async_signals(true);
      pcntl_signal(SIGTERM, function () {
        $this->shouldQuit = true;
        echo "[" . date('Y-m-d H:i:s') . "] SIGTERM received. Worker will exit after current job.\n";
      });
      pcntl_signal(SIGINT, function () {
        $this->shouldQuit = true;
        echo "[" . date('Y-m-d H:i:s') . "] SIGINT received. Worker will exit after current job.\n";
      });
    }

    $container = $this->app->getContainer();

    /** @var DatabaseManager $dbManager */
    $dbManager = $container->resolve(DatabaseManager::class);

    /** @var ConfigService $config */
    $config = $container->resolve(ConfigService::class);

    $connectionName = $config->get('queue.connection', 'redis_queue');
    $queueDriver = new RedisQueue($dbManager, $connectionName);

    // Redis for status updates
    $redis = $dbManager->connection($connectionName);

    $workerId = gethostname() . ':' . getmypid();
    $workerKey = "worker_status:{$queueName}:{$workerId}";

    $jobsProcessed = 0;
    $startTime = time();

    while (true) {
      // Heartbeat
      $redis->hmset($workerKey, [
        'status' => 'running',
        'queue' => $queueName,
        'worker_id' => $workerId,
        'last_seen_at' => date('Y-m-d H:i:s'),
      ]);
      $redis->expire($workerKey, 300);

      if ($this->shouldQuit) {
        $this->log("Shutdown signal received. Exiting...", $logFileName);
        break;
      }

      if (memory_get_usage(true) / 1024 / 1024 > self::MAX_MEMORY_MB) {
        $this->log("Memory limit reached. Restarting...", $logFileName);
        break;
      }

      if ((time() - $startTime) > self::MAX_RUNTIME_SECONDS) {
        $this->log("Time limit reached. Restarting...", $logFileName);
        break;
      }

      try {
        $payload = $queueDriver->pop($queueName, $isDaemon);
      } catch (Throwable $e) {
        $this->log("ERROR: Fetching job failed. " . $e->getMessage(), $logFileName);
        sleep(5);
        continue;
      }

      if ($payload) {
        $this->processJob($payload, $container, $logFileName);
        $jobsProcessed++;
      } else {
        // If cron mode (non-blocking) and no job, exit
        if (!$isDaemon) {
          break;
        }
      }

      if ($jobsProcessed >= self::MAX_JOBS_PER_RUN) {
        $this->log("Job limit reached. Restarting...", $logFileName);
        break;
      }
    }

    if (!$isDaemon) {
      $this->log("CRON MODE: Finished processing cycle.", $logFileName);
    }

    return 0;
  }

  private function processJob(array $payload, $container, string $logFileName): void
  {
    $jobClass = $payload['job_class'];
    $data = $payload['data'];

    $this->log("Processing job: {$jobClass}...", $logFileName);

    try {
      if (class_exists($jobClass)) {
        $jobInstance = $container->resolve($jobClass);
        if (method_exists($jobInstance, 'handle')) {
          $jobInstance->handle($data);
          $this->log("Job processed successfully: {$jobClass}", $logFileName);
        } else {
          $this->log("ERROR: Method 'handle' not found in {$jobClass}", $logFileName);
        }
      } else {
        $this->log("ERROR: Job class not found: {$jobClass}", $logFileName);
      }
    } catch (Throwable $e) {
      $this->log("ERROR PROCESSING JOB {$jobClass}: " . $e->getMessage(), $logFileName);
    }
  }

  private function log(string $message, string $logFileName): void
  {
    $logFile = __DIR__ . '/../../../storage/logs/' . $logFileName;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";

    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
      mkdir($logDir, 0775, true);
    }

    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage;
  }
}
