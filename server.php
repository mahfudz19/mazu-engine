<?php

/**
 * Mazu Development Server Router
 * Meniru logika Apache/Nginx untuk menangani request ke file statis vs index.php
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// Jika file statis ada di folder public, biarkan PHP Built-in Server menyajikannya (return false)
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false;
}

// Jika tidak ada, alihkan ke front controller (index.php)
// Ini memungkinkan AssetController menangani request ke 'build/...' yang filenya belum ada/dihapus
require_once __DIR__ . '/public/index.php';