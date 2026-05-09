<?php

namespace App\Console\Commands;

use App\Console\Contracts\CommandInterface;

class RouteCacheCommand implements CommandInterface
{
  public function __construct() {}

  public function getName(): string
  {
    return 'route:cache';
  }

  public function getDescription(): string
  {
    return 'Membuat cache routing untuk performa tinggi';
  }

  public function handle(array $arguments): int
  {
    echo color("Membangun cache rute...\n", "yellow");

    // Definisi path
    $cacheDir = __DIR__ . '/../../../storage/cache';
    $cacheFile = $cacheDir . '/routes.php';

    // 1. Pastikan direktori cache ada
    if (!is_dir($cacheDir)) {
      mkdir($cacheDir, 0755, true);
      echo "Created directory: {$cacheDir}\n";
    }

    // 2. Hapus cache lama
    if (file_exists($cacheFile)) {
      unlink($cacheFile);
      echo "Removed old cache.\n";
    }

    // 3. Boot aplikasi
    $app = new \App\Core\Foundation\Application();
    $app->boot();

    // 4. Ambil rute dari router
    $router = $app->getRouter();

    $routes = $router->getRoutes();

    // 5. Validasi: Cek apakah ada Closure atau objek yang tidak bisa di-cache
    $invalidRoutes = [];

    foreach ($routes as $method => $methodRoutes) {
      if (!is_array($methodRoutes)) continue;

      foreach ($methodRoutes as $uri => $route) {
        if (!is_array($route)) continue;

        $handler = $route['handler'] ?? null;

        // Cek jika handler adalah Closure
        if ($handler instanceof \Closure) {
          $invalidRoutes[] = [
            'method' => $method,
            'uri' => $uri,
            'reason' => 'Closure (fungsi anonim)',
          ];
        }
        // Cek jika handler adalah objek yang tidak bisa di-serialize
        elseif (is_object($handler) && !is_callable($handler)) {
          $invalidRoutes[] = [
            'method' => $method,
            'uri' => $uri,
            'reason' => 'Object tidak valid',
          ];
        }
      }
    }

    if (!empty($invalidRoutes)) {
      // Hapus file cache parsial jika ada
      if (file_exists($cacheFile)) unlink($cacheFile);

      echo "\n\033[31m[ERROR] Route Cache Failed!\033[0m\n\n";
      echo "Ditemukan " . count($invalidRoutes) . " rute dengan handler tidak valid:\n\n";

      foreach ($invalidRoutes as $route) {
        echo "  \033[33m{$route['method']}\033[0m  /{$route['uri']}\n";
        echo "    Alasan: {$route['reason']}\n";
      }

      echo "\n\033[31mSolusi:\033[0m Route handler harus menggunakan Controller Class.\n";
      echo "Contoh: \033[90m\$router->get('/settings', [SettingsController::class, 'index']);\033[0m\n\n";

      exit(1);
    }

    // 6. Simpan array rute ke file PHP
    $content = "<?php\n\nreturn " . var_export($routes, true) . ";\n";
    file_put_contents($cacheFile, $content);

    // Hitung total routes dari semua HTTP methods
    $totalRoutes = 0;
    foreach ($routes as $method => $methodRoutes) {
      if (is_array($methodRoutes)) {
        $totalRoutes += count($methodRoutes);
      }
    }

    echo "Route cache generated successfully at:\n{$cacheFile}\n";
    echo "Total Routes Cached: {$totalRoutes}\n";

    return 0;
  }
}
