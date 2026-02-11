<?php

namespace App\Core\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;

class AssetController
{
  public function serve(Request $request, Response $response, array $params = [])
  {
    // Ambil path langsung dari Request URI
    $uri = $request->getPath();

    // [DEV MODE] Special Handling untuk Core SPA Engine (spa.js)
    // Melayani langsung dari source code agar tidak perlu 'php mazu build' saat development
    if (str_ends_with($uri, '/build/js/spa.js')) {
      $coreSpaPath = realpath(__DIR__ . '/../Assets/js/spa.js');
      if ($coreSpaPath && file_exists($coreSpaPath)) {
        header("Content-Type: application/javascript");
        // Disable cache agar perubahan code langsung terlihat
        header("Cache-Control: no-cache, no-store, must-revalidate");
        readfile($coreSpaPath);
        exit;
      }
    }

    // [DEV MODE] Handling untuk Asset Views (CSS/JS per modul)
    // Cari posisi 'build/assets/' dan ambil sisanya
    $prefix = 'build/assets/';
    $pos = strpos($uri, $prefix);

    if ($pos !== false) {
      $path = substr($uri, $pos + strlen($prefix));
    } else {
      $path = $params['path'] ?? $params[0] ?? '';
    }

    $path = urldecode($path);

    // Sanitasi path (mencegah ../..)
    if (strpos($path, '..') !== false) {
      return $response->setStatusCode(403)->setContent('Forbidden');
    }

    // Cek source location (addon/Views)
    $basePath = realpath(__DIR__ . '/../../../addon/Views');
    $fullPath = realpath($basePath . '/' . $path);

    // Pastikan file ada dan berada di dalam basePath (security check)
    if ($fullPath && file_exists($fullPath) && str_starts_with($fullPath, $basePath)) {
      $mime = $this->getMimeType($fullPath);
      header("Content-Type: $mime");
      // Disable cache for dev
      header("Cache-Control: no-cache, no-store, must-revalidate");
      readfile($fullPath);
      exit;
    }

    return $response->setStatusCode(404)->setContent("Asset not found: {$path}");
  }

  private function getMimeType($filename)
  {
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    return match ($ext) {
      'css' => 'text/css',
      'js' => 'application/javascript',
      'png' => 'image/png',
      'jpg', 'jpeg' => 'image/jpeg',
      'svg' => 'image/svg+xml',
      default => 'text/plain',
    };
  }
}
