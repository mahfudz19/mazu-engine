#!/usr/bin/env php
<?php

// Pastikan jalur ini sesuai dengan struktur folder Anda
// Karena scripts/ ada di root, sama seperti tools/, path ke vendor tetap sama
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Foundation\Application;

echo "Generating route cache...\n";

// Definisi path
// Mengarah ke storage/cache sesuai perubahan sebelumnya
$cacheDir = __DIR__ . '/../storage/cache';
$cacheFile = $cacheDir . '/routes.php';

// 1. Pastikan direktori cache ada
if (!is_dir($cacheDir)) {
  mkdir($cacheDir, 0755, true);
  echo "Created directory: {$cacheDir}\n";
}

// 2. Hapus cache lama (PENTING: agar aplikasi memuat ulang dari Router/index.php)
if (file_exists($cacheFile)) {
  unlink($cacheFile);
  echo "Removed old cache.\n";
}

// 3. Boot aplikasi
// Karena cache sudah dihapus, Application akan memuat rute dari file asli (Router/index.php)
$app = new Application();
$app->boot();

// 4. Ambil rute yang sudah terkompilasi dari memori
$router = $app->getRouter();

// Pastikan rute sistem disertakan dalam cache (fix untuk build/js yang hilang)
$router->get('/build/assets/(.*)', 'App\Core\Controllers\AssetController@serve');
$router->get('/build/js/(.*)', 'App\Core\Controllers\AssetController@serve');

$routes = $router->getRoutes();

// Validasi: Cek apakah ada Closure (fungsi anonim) dalam rute
// Route Cache TIDAK BISA menyimpan Closure.
array_walk_recursive($routes, function ($value) use ($cacheFile) {
  if ($value instanceof \Closure) {
    // Hapus file cache parsial jika ada
    if (file_exists($cacheFile)) unlink($cacheFile);

    echo "\n\033[31m[ERROR] Route Cache Failed!\033[0m\n";
    echo "Ditemukan 'Closure' (fungsi anonim) dalam definisi rute.\n";
    echo "Route Cache membutuhkan Controller Class, bukan function() { ... }.\n";
    echo "Silakan refactor rute Anda ke Controller.\n\n";
    exit(1);
  }
});

// 5. Simpan array rute ke file PHP
$content = "<?php\n\nreturn " . var_export($routes, true) . ";\n";
file_put_contents($cacheFile, $content);

echo "Route cache generated successfully at:\n{$cacheFile}\n";
echo "Total Routes Cached: " . (count($routes['GET'] ?? []) + count($routes['POST'] ?? [])) . "\n";
