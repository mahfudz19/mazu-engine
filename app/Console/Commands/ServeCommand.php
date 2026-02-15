<?php

namespace App\Console\Commands;

use App\Core\Foundation\Application;
use App\Console\Contracts\CommandInterface;

class ServeCommand implements CommandInterface
{
  public function __construct(
    private Application $app,
  ) {}

  public function getName(): string
  {
    return 'serve';
  }

  public function getDescription(): string
  {
    return 'Menjalankan server development lokal';
  }

  public function handle(array $arguments): int
  {
    $host = 'localhost';
    $port = 8000;

    // Parse arguments logic simple
    foreach ($arguments as $arg) {
      if (str_starts_with($arg, '--port=')) {
        $port = (int) substr($arg, 7);
      }
      if (str_starts_with($arg, '--host=')) {
        $host = substr($arg, 7);
      }
    }

    $basePath = realpath(__DIR__ . '/../../../public');
    $serverScript = realpath(__DIR__ . '/../server.php');

    // Check if port is already in use
    $connection = @fsockopen($host, $port);
    if (is_resource($connection)) {
      fclose($connection);
      echo " " . color("ERROR", "red") . " Port {$port} is already in use.\n";
      echo " Try running with a different port: " . color("php mazu serve --port=" . ($port + 1), "yellow") . "\n\n";
      return 1;
    }

    echo " " . color("INFO", "green") . " Server running on " . color("http://{$host}:{$port}", "cyan") . "\n";
    echo " " . color("INFO", "green") . " Press " . color("Ctrl+C", "yellow") . " to stop the server\n\n";

    $command = sprintf(
      '%s -S %s:%d -t %s %s',
      PHP_BINARY,
      $host,
      $port,
      escapeshellarg($basePath),
      escapeshellarg($serverScript)
    );

    // Using proc_open to capture and format logs
    $descriptorspec = [
      0 => STDIN,
      1 => STDOUT,
      2 => ["pipe", "w"]
    ];

    // Environment variables for the child process
    // Filter $_SERVER and $_ENV to remove non-scalar values to avoid warnings
    $cleanServerEnv = array_filter($_SERVER, fn($v) => is_scalar($v));
    $cleanEnv = array_filter($_ENV, fn($v) => is_scalar($v));

    $env = array_merge($cleanServerEnv, $cleanEnv, [
      'APP_ENV' => 'development',
      'APP_DEBUG' => 'true'
    ]);

    $process = proc_open($command, $descriptorspec, $pipes, null, $env);

    if (is_resource($process)) {
      $requestStartTimes = [];

      while (!feof($pipes[2])) {
        $line = fgets($pipes[2]);
        if (!$line) continue;

        // Parse PHP Built-in server logs
        // Format: [Wed Jan 21 05:24:17 2026] [::1]:53111 [200]: GET /css/global.css
        // Format Start: [Wed Jan 21 05:24:17 2026] [::1]:53111 Accepted
        // Format End: [Wed Jan 21 05:24:17 2026] [::1]:53111 Closing

        if (preg_match('/\[(.*?)\] (.*?):(\d+) (Accepted|Closing)/', $line, $matches)) {
          // Kita bisa menggunakan Accepted/Closing untuk menghitung durasi akurat per koneksi
          // Tapi karena PHP built-in server bisa handle multiple requests dalam satu koneksi (keep-alive),
          // metode ini kurang akurat untuk per-request timing.
          // Namun, untuk simplicity, kita skip log ini agar tidak berisik.
          continue;
        }

        if (preg_match('/\[(.*?)\] (.*?):(\d+) \[(\d+)\]: (GET|POST|PUT|DELETE|PATCH|OPTIONS) (.*)/', $line, $matches)) {
          $timestamp = $matches[1];
          $ip = $matches[2];
          $port = $matches[3];
          $code = $matches[4];
          $method = $matches[5];
          $path = $matches[6];

          $time = date('H:i:s');

          // Warna Status Code
          if ($code >= 500) $codeColor = 'red';
          elseif ($code >= 400) $codeColor = 'yellow';
          elseif ($code >= 300) $codeColor = 'cyan';
          else $codeColor = 'green';

          // Warna Method
          $methodColors = [
            'GET' => 'blue',
            'POST' => 'yellow',
            'PUT' => 'cyan',
            'DELETE' => 'red',
            'PATCH' => 'cyan',
            'OPTIONS' => 'reset'
          ];
          $methodColor = $methodColors[$method] ?? 'reset';

          // Response Time Simulation (PHP Built-in server doesn't output duration)
          // Kita akan buat estimasi visual atau biarkan kosong dulu.
          // Untuk best practice tanpa mengubah core PHP, kita tampilkan log bersih saja.

          echo sprintf(
            "   %s %s %s    %s\n",
            color($time, "reset"),
            color($code, $codeColor),
            color(str_pad($method, 6), $methodColor),
            $path
          );
        } elseif (str_contains($line, 'started')) {
          // Log server start
          continue;
        } else {
          // Log error PHP (Stack trace, Warning, Notice)
          // Tampilkan apa adanya tapi beri warna merah/kuning
          if (str_contains($line, 'Fatal error') || str_contains($line, 'Parse error')) {
            echo color(trim($line), "red") . "\n";
          } elseif (str_contains($line, 'Warning') || str_contains($line, 'Notice')) {
            echo color(trim($line), "yellow") . "\n";
          }
        }
      }
      proc_close($process);
    }

    return 0;
  }
}
