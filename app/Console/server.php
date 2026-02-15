<?php

/**
 * Mazu Development Server Router
 * Meniru logika Apache/Nginx untuk menangani request ke file statis vs index.php
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// Jika file statis ada di folder public, biarkan PHP Built-in Server menyajikannya (return false)
// KECUALI untuk 'build/assets/' agar selalu ditangani oleh AssetController (untuk hot-reload di dev)
if ($uri !== '/' && file_exists(__DIR__ . '/../../public' . $uri)) {
    // Jika request mengarah ke build/assets, jangan sajikan file statisnya (bypass ke index.php)
    if (strpos($uri, '/build/assets/') !== false) {
        // Lanjut ke require index.php
    } else {
        return false;
    }
}

// Jika tidak ada, alihkan ke front controller (index.php)
// Ini memungkinkan AssetController menangani request ke 'build/...' yang filenya belum ada/dihapus
require_once __DIR__ . '/../../public/index.php';
