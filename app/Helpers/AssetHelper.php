<?php

if (!function_exists('asset')) {
  /**
   * Get the path to a versioned asset file.
   *
   * @param string $path
   * @return string
   */
  function asset($path)
  {
    static $manifest = null;

    if ($manifest === null) {
      $manifestPath = __DIR__ . '/../../public/build/manifest.json';
      if (file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
      } else {
        $manifest = [];
      }
    }

    $path = ltrim($path, '/');

    // Cek apakah ada di manifest
    if (isset($manifest[$path])) {
      return getBaseUrl('build/' . $manifest[$path]);
    }

    // Fallback development (langsung ke build path)
    return getBaseUrl('build/' . $path);
  }
}
