<?php

namespace App\Console\Commands;

use App\Console\Contracts\CommandInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class BuildCommand implements CommandInterface
{
  public function __construct() {}

  public function getName(): string
  {
    return 'build';
  }

  public function getDescription(): string
  {
    return 'Build assets dan cache untuk produksi (JS, CSS, Routes)';
  }

  private $termWidth = null;

  public function handle(array $arguments): int
  {
    $start = microtime(true);
    echo color(" ❯ ", "yellow") . color("BUILDING ASSETS ", "bold") . color("[ production ]", "dim") . "\n\n";

    // 1. Environment Validation
    echo "  1. ENVIRONMENT CHECK\n";
    if (!$this->checkEnvironment()) {
      echo "\n     " . color("✖", "red") . " Build aborted due to environment issues.\n";
      return 1;
    }
    echo "     " . color("✔", "green") . " Environment valid\n";

    // 2. PHP Linting
    echo "  2. PHP LINTING (Syntax Check)\n";
    $lintStart = microtime(true);
    $lintResult = $this->lintPhpFiles();
    if (!$lintResult['success']) {
      echo "\n     " . color("✖", "red") . " Build aborted due to syntax errors.\n";
      return 1;
    }
    $lintTime = number_format(microtime(true) - $lintStart, 2) . "s";
    $this->printLineWithDots(color("✔", "green") . " No syntax errors found in " . $lintResult['count'] . " files", $lintTime);

    // 3. Build Route Cache
    echo "  3. ROUTE CACHE\n";
    $routeStart = microtime(true);
    ob_start();
    require_once __DIR__ . '/../../../scripts/route-cache.php';
    ob_end_clean();
    $routeTime = number_format(microtime(true) - $routeStart, 2) . "s";
    $this->printLineWithDots(color("✔", "green") . " Route cache generated", $routeTime);

    // 4. Publish Assets
    echo "  4. ASSET MANAGEMENT\n";
    $pubStart = microtime(true);
    $coreCount = $this->publishCoreAssets();
    $addonCount = $this->publishAddonAssets();
    $totalAssets = $coreCount + $addonCount;
    $pubTime = number_format(microtime(true) - $pubStart, 2) . "s";
    $this->printLineWithDots(color("✔", "green") . " {$totalAssets} assets published", $pubTime);

    // 5. Minify Assets
    echo "  5. MINIFICATION (Native PHP)\n";
    $minStart = microtime(true);
    $stats = $this->minifyAssets();
    $minTime = number_format(microtime(true) - $minStart, 2) . "s";

    if ($stats['js'] > 0) {
      $this->printLineWithDots(color("●", "cyan") . " JS  [" . $stats['js'] . " files]", $minTime);
    }
    if ($stats['css'] > 0) {
      $this->printLineWithDots(color("●", "cyan") . " CSS [" . $stats['css'] . " files]", $minTime);
    }

    // 6. Versioning (Content Hashing)
    echo "  6. VERSIONING (Cache Busting)\n";
    $verStart = microtime(true);
    $manifestCount = $this->versionAssets();
    $verTime = number_format(microtime(true) - $verStart, 2) . "s";
    $this->printLineWithDots(color("✔", "green") . " Manifest generated ({$manifestCount} assets)", $verTime);

    // 7. Size Report
    echo "  7. SIZE REPORT\n";
    $this->reportSizes();

    $totalTime = number_format(microtime(true) - $start, 2);
    echo "\n " . color(str_repeat("─", $this->getTerminalWidth() - 2), "dim") . "\n";
    echo " " . color(" DONE ", "bold,green") . " Build finished successfully in " . color($totalTime . "s", "bold") . " 🚀\n";

    return 0;
  }

  private function checkEnvironment(): bool
  {
    $envPath = getEnvPath();
    if (!file_exists($envPath)) {
      echo "     " . color("!", "red") . " .env file not found at: " . color($envPath, "dim") . "\n";
      return false;
    }

    // Cek key kritikal
    $content = file_get_contents($envPath);
    $criticalKeys = ['APP_ENV', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
    $missing = [];

    foreach ($criticalKeys as $key) {
      if (!preg_match("/^{$key}=.+/m", $content)) {
        $missing[] = $key;
      }
    }

    if (!empty($missing)) {
      echo "     " . color("!", "red") . " Missing critical env keys: " . implode(', ', $missing) . "\n";
      return false;
    }

    return true;
  }

  private function lintPhpFiles(): array
  {
    $targetDir = realpath(__DIR__ . '/../../'); // App folder
    if (!$targetDir) return ['success' => true, 'count' => 0];

    $files = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $hasError = false;
    $count = 0;
    $spinner = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    foreach ($files as $file) {
      if ($file->isFile() && $file->getExtension() === 'php') {
        $count++;
        $frame = $spinner[$count % count($spinner)];
        echo "\r     " . color($frame, "cyan") . " Checking files... " . color($count, "bold");

        // Jalankan php -l
        $returnVar = 0;
        $output = [];
        exec("php -l " . escapeshellarg($file->getRealPath()), $output, $returnVar);

        if ($returnVar !== 0) {
          $hasError = true;
          echo "     " . color("✖", "red") . " Syntax error in: " . $file->getFilename() . "\n";
          foreach ($output as $line) {
            if (strpos($line, 'Errors parsing') === false && trim($line) !== '') {
              echo "       " . color($line, "red") . "\n";
            }
          }
        }
      }
    }

    return [
      'success' => !$hasError,
      'count' => $count
    ];
  }

  private function reportSizes(): void
  {
    $buildDir = realpath(__DIR__ . '/../../../public/build');
    if (!$buildDir) return;

    $files = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $assets = [];
    foreach ($files as $file) {
      if ($file->isFile() && in_array($file->getExtension(), ['js', 'css'])) {
        $assets[] = [
          'name' => $file->getFilename(),
          'size' => $file->getSize(),
          'path' => $file->getPathname()
        ];
      }
    }

    // Sort by size descending
    usort($assets, fn($a, $b) => $b['size'] <=> $a['size']);

    // Ambil top 10 terbesar
    $topAssets = array_slice($assets, 0, 10);

    foreach ($topAssets as $asset) {
      $sizeFormatted = $this->formatBytes($asset['size']);
      $name = $asset['name'];

      // Warna berdasarkan ukuran
      $sizeColor = 'green';
      if ($asset['size'] > 100 * 1024) $sizeColor = 'yellow'; // > 100KB
      if ($asset['size'] > 500 * 1024) $sizeColor = 'red';    // > 500KB

      $this->printLineWithDots($name, $sizeFormatted, $sizeColor);
    }

    if (count($assets) > 10) {
      echo "     " . color("... and " . (count($assets) - 10) . " more files.", "dim") . "\n";
    }
  }

  private function printLineWithDots(string $label, string $value, string $valueColor = 'dim', int $indent = 5): void
  {
    $termWidth = $this->getTerminalWidth();

    // Strip ANSI colors to get real visible length
    $labelClean = preg_replace('/\e[[][A-Za-z0-9];?[0-9]*m/', '', $label);
    $valueClean = preg_replace('/\e[[][A-Za-z0-9];?[0-9]*m/', '', $value);

    // Calculate dots: width - indent - label - value - 2 spaces - small margin
    $dotsCount = $termWidth - $indent - strlen($labelClean) - strlen($valueClean) - 2 - 1;
    $dots = str_repeat('.', max(2, $dotsCount));

    echo "\r" . str_repeat(" ", $indent) . $label . " " . color($dots, "dim") . " " . color($value, $valueColor) . "\n";
  }

  private function getTerminalWidth(): int
  {
    if ($this->termWidth !== null) return $this->termWidth;

    // Try environment variable
    if (isset($_ENV['COLUMNS'])) {
      return $this->termWidth = (int)$_ENV['COLUMNS'];
    }

    // Try stty (Mac/Linux)
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
      $stty = exec('stty size 2>/dev/null');
      if ($stty) {
        $parts = explode(' ', $stty);
        if (isset($parts[1])) return $this->termWidth = (int)$parts[1];
      }

      $width = (int)exec('tput cols 2>/dev/null');
      if ($width > 0) return $this->termWidth = $width;
    }

    return $this->termWidth = 80; // Fallback
  }

  private function formatBytes($bytes, $precision = 2)
  {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
  }

  private function versionAssets(): int
  {
    $buildDir = realpath(__DIR__ . '/../../../public/build');
    if (!$buildDir || !is_dir($buildDir)) {
      return 0;
    }

    $manifest = [];
    $files = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
      if ($file->isFile()) {
        $ext = $file->getExtension();
        if (in_array($ext, ['js', 'css'])) {
          $path = $file->getRealPath();
          $content = file_get_contents($path);
          $hash = substr(md5($content), 0, 8); // 8 char hash

          $filename = $file->getFilename();
          // Skip file yang sudah di-hash (jika build dijalankan berulang tanpa clean)
          if (preg_match('/\.([a-f0-9]{8})\.(js|css)$/', $filename)) {
            continue;
          }

          $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
          $newFilename = "{$nameWithoutExt}.{$hash}.{$ext}";

          // Path relatif dari folder build (untuk key manifest)
          // Contoh: js/spa.js atau assets/auth/style.css
          $relativePath = substr($path, strlen($buildDir) + 1);

          // Rename file
          $newPath = $file->getPath() . '/' . $newFilename;
          rename($path, $newPath);

          // Simpan ke manifest
          // Key: path asli (js/spa.js), Value: path baru (js/spa.abcdef12.js)
          $manifest[$relativePath] = dirname($relativePath) === '.'
            ? $newFilename
            : dirname($relativePath) . '/' . $newFilename;
        }
      }
    }

    // Tulis manifest.json
    file_put_contents(
      $buildDir . '/manifest.json',
      json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    return count($manifest);
  }

  private function minifyAssets(): array
  {
    $buildDir = realpath(__DIR__ . '/../../../public/build');
    $stats = ['js' => 0, 'css' => 0];
    $spinner = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
    $totalProcessed = 0;

    if (!$buildDir || !is_dir($buildDir)) {
      return $stats;
    }

    $files = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
      if ($file->isFile()) {
        $ext = $file->getExtension();
        $totalProcessed++;
        $frame = $spinner[$totalProcessed % count($spinner)];

        if ($ext === 'css') {
          echo "\r     " . color($frame, "cyan") . " Minifying assets... " . color($stats['css'] + $stats['js'] + 1, "bold");
          $this->minifyCss($file->getRealPath());
          $stats['css']++;
        } elseif ($ext === 'js') {
          echo "\r     " . color($frame, "cyan") . " Minifying assets... " . color($stats['css'] + $stats['js'] + 1, "bold");
          $this->minifyJs($file->getRealPath());
          $stats['js']++;
        }
      }
    }

    echo "\r" . str_repeat(" ", 40) . "\r"; // Clean up
    return $stats;
  }

  private function minifyCss(string $path): void
  {
    $content = file_get_contents($path);
    $content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
    $content = str_replace(["\r\n", "\r", "\n", "\t"], '', $content);
    $content = preg_replace('/\s{2,}/', ' ', $content);
    $content = str_replace([': ', ' :'], ':', $content);
    $content = str_replace([' {', '{ '], '{', $content);
    $content = str_replace(['; ', ' ;'], ';', $content);
    $content = str_replace([', ', ' ,'], ',', $content);
    file_put_contents($path, $content);
  }

  private function minifyJs(string $path): void
  {
    // 1. Coba gunakan esbuild untuk hasil minifikasi terbaik (satu baris, aman)
    $rootDir = realpath(__DIR__ . '/../../../');
    $command = "cd " . escapeshellarg($rootDir) . " && npx esbuild " . escapeshellarg($path) . " --minify --outfile=" . escapeshellarg($path) . " --allow-overwrite 2>&1";

    $returnVar = 0;
    exec($command, $_, $returnVar);

    if ($returnVar === 0) {
      return;
    }

    // 2. Fallback: Naive PHP Minification (jika esbuild gagal)
    $content = file_get_contents($path);
    $content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
    $lines = explode("\n", $content);
    $newLines = [];
    foreach ($lines as $line) {
      $trim = trim($line);
      if (empty($trim) || str_starts_with($trim, '//')) {
        continue;
      }
      $newLines[] = $trim;
    }
    $content = implode("\n", $newLines);
    file_put_contents($path, $content);
  }

  private function publishCoreAssets(): int
  {
    $source = __DIR__ . '/../../Core/Assets/js/spa.js';
    $dest = __DIR__ . '/../../../public/build/js/spa.js';

    if (!file_exists($source)) {
      return 0;
    }

    $this->ensureDir(dirname($dest));
    return copy($source, $dest) ? 1 : 0;
  }

  private function publishAddonAssets(): int
  {
    $sourceDir = __DIR__ . '/../../../addon/Views';
    $destDir = __DIR__ . '/../../../public/build/assets';

    if (!is_dir($sourceDir)) {
      return 0;
    }

    $sourceDir = realpath($sourceDir);
    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST
    );

    $count = 0;
    foreach ($iterator as $item) {
      if ($item->isFile()) {
        $ext = $item->getExtension();
        if (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'svg', 'woff', 'woff2'])) {
          $subPath = substr($item->getPathname(), strlen($sourceDir) + 1);
          $target = $destDir . '/' . $subPath;
          $this->ensureDir(dirname($target));
          copy($item->getPathname(), $target);
          $count++;
        }
      }
    }
    return $count;
  }

  private function ensureDir(string $path): void
  {
    if (!is_dir($path)) {
      mkdir($path, 0755, true);
    }
  }
}
