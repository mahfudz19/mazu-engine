<?php

namespace App\Core\Queue;

interface QueueInterface
{
  /**
   * Push a new job onto the queue.
   *
   * @param string $jobClass
   * @param array $data
   * @param string $queue
   * @return mixed
   */
  public function push(string $jobClass, array $data = [], string $queue = 'default');

  /**
   * Pop the next job off of the queue.
   *
   * @param string $queue
   * @param bool $blocking
   * @return array|null [jobClass, data] or null
   */
  public function pop(string $queue = 'default', bool $blocking = true);

  /**
   * Mark a job as failed.
   */
  public function fail(int $id, string $message): bool;

  /**
   * Mark a job as successful.
   */
  public function success(int $id): bool;
}
