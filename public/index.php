<?php

declare(strict_types=1);

// composer autoload (recommended)
require_once __DIR__ . '/../vendor/autoload.php';

// Load Environment Variables first to determine environment
loadEnvironmentConfig();

if (isProduction()) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// 1. Buat instance aplikasi
$app = new App\Core\Foundation\Application();

// 2. Jalankan aplikasi
$app->run();
