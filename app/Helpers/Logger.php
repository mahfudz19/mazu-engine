<?php

function logger()
{
  static $l;
  if ($l) return $l;

  $l = new class {
    // Tentukan path file log di sini
    private string $logFilePath;

    public function __construct()
    {
      $logDir = __DIR__ . '/../../storage/logs';
      if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true); // Buat direktori jika belum ada
      }
      $this->logFilePath = $logDir . '/app.log'; // Nama file log kustom
    }

    public function error($message, $context = [])
    {
      $msg = $message;
      if (!empty($context['exception']) && $context['exception'] instanceof \Throwable) {
        $msg .= ' | exception: ' . $context['exception']->getMessage() . ' in ' . $context['exception']->getFile() . ':' . $context['exception']->getLine();
      }

      error_log('[' . date('Y-m-d H:i:s') . '] [app] ERROR: ' . $msg . PHP_EOL, 3, $this->logFilePath);
    }
  };
  return $l;
}
