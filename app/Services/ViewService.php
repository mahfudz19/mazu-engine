<?php

namespace App\Services;

use App\Core\Http\Request;
use App\Core\View\View;
use App\Exceptions\HttpException;

class ViewService
{
  private Request $request;
  private ConfigService $config;

  public function __construct(Request $request, ConfigService $config)
  {
    $this->request = $request;
    $this->config = $config;
  }

  /**
   * Merakit objek View menjadi string HTML lengkap menggunakan sistem layout bersarang.
   *
   * @param View $view Objek View yang dikembalikan oleh controller.
   * @return string HTML yang sudah jadi.
   * @throws HttpException Jika file view atau layout tidak ditemukan.
   */
  public function render(View $view): string
  {
    $props = $view->props;
    $props['meta'] = $view->meta;
    extract($props, EXTR_SKIP);

    $rootViewsPath = realpath(__DIR__ . '/../../addon/Views');

    $processedPath = preg_replace('/:(\w+)/', '[$1]', $view->path);

    $routeGroupFolders = [];
    $this->findRouteGroupPaths($rootViewsPath, $rootViewsPath, $routeGroupFolders);

    // Urutkan agar path yang lebih spesifik (lebih dalam) dicari terlebih dahulu
    usort($routeGroupFolders, function ($a, $b) {
      return strlen($b) <=> strlen($a);
    });

    // Tambahkan root '' sebagai fallback terakhir setelah semua grup
    $routeGroupFolders[] = '';

    $contentPath = null;
    $searchedPaths = [];

    foreach ($routeGroupFolders as $group) {
      // Gunakan $processedPath, bukan $view->path
      $pathWithGroup = $group ? rtrim($group, '/') . '/' . ltrim($processedPath, '/') : ltrim($processedPath, '/');

      // Bersihkan double slash jika path view kosong
      $pathWithGroup = str_replace('//', '/', $pathWithGroup);

      $directFilePath = $rootViewsPath . '/' . $pathWithGroup . '.php';
      $indexPath = $rootViewsPath . '/' . $pathWithGroup . '/index.php';

      // Simpan path yang dicari untuk pesan error
      $searchedPaths[] = $directFilePath;
      $searchedPaths[] = $indexPath;

      if (file_exists($directFilePath)) {
        $contentPath = $directFilePath;
        break; // Ditemukan, hentikan pencarian
      }
      if (file_exists($indexPath)) {
        $contentPath = $indexPath;
        break; // Ditemukan, hentikan pencarian
      }
    }

    if ($contentPath === null) {
      $searchedPathsList = implode(" DAN ", array_unique($searchedPaths));
      throw new HttpException(500, "File view konten tidak ditemukan. Telah dicari di: {$searchedPathsList}");
    }

    // [AUTO-DISCOVERY] Cek CSS untuk Content View
    $this->detectAndRegisterStyle($contentPath, $rootViewsPath);

    ob_start();
    require $contentPath;
    $html = ob_get_clean();

    $this->detectAndInjectScript($contentPath, $rootViewsPath, $html);

    // 2. Berjalan ke atas dari direktori konten, terapkan setiap layout yang ditemukan.
    $currentDir = dirname($contentPath);

    // Cek apakah ini request SPA
    $isSpaRequest = $this->request->isSpaRequest();
    $spaTargetLayout = $this->request->getSpaTargetLayout();
    $spaClientLayouts = $this->request->getSpaLayouts(); // Array layout yang dimiliki client

    // [SMART NEGOTIATION] Kita perlu melacak hirarki layout untuk dikirim ke client
    $layoutHierarchy = [];
    $foundMatch = false;
    $matchedLayoutId = null;

    while ($currentDir && strpos($currentDir, $rootViewsPath) === 0) {
      // --- PERUBAHAN DI SINI ---
      // Tentukan dua kemungkinan path untuk layout.
      $layoutFilePath = $currentDir . '/layout.php';
      $layoutIndexPath = $currentDir . '/layout/index.php';

      $layoutToUse = null;
      if (file_exists($layoutFilePath)) {
        $layoutToUse = $layoutFilePath;
      }
      // Jika tidak, cari 'layout/index.php'.
      elseif (file_exists($layoutIndexPath)) {
        $layoutToUse = $layoutIndexPath;
      }

      // Jika salah satu dari layout ditemukan, terapkan.
      if ($layoutToUse) {
        // [AUTO-DISCOVERY] Cek CSS untuk Layout
        // Kita daftarkan style untuk SETIAP layout dalam hirarki (parent maupun child)
        // agar daftar 'styles' di respon JSON selalu konsisten, tidak peduli di level mana layout match terjadi.
        $this->detectAndRegisterStyle($layoutToUse, $rootViewsPath);

        $relativeLayoutPath = ltrim(str_replace($rootViewsPath, '', $layoutToUse), '/');
        $suffixGenerator = $this->config->get('view.layout_suffix_generator');
        $layoutSuffix = is_callable($suffixGenerator) ? $suffixGenerator() : '';
        if ($relativeLayoutPath === 'layout.php') {
          $layoutSuffix = '';
        }

        $layoutId = $relativeLayoutPath . ($layoutSuffix ? '-' . $layoutSuffix : '');

        // Tambahkan ke hirarki (urutan: dari dalam ke luar)
        $layoutHierarchy[] = $layoutId;

        // Cek apakah layout ini ada di client
        $isClientHasThisLayout = !empty($spaClientLayouts)
          ? in_array($layoutId, $spaClientLayouts)
          : ($spaTargetLayout === $layoutId);

        if ($isSpaRequest && $isClientHasThisLayout && !$foundMatch) {
          // Kita menemukan layout terdalam yang dimiliki client.
          // Kita tandai sebagai match, tapi JANGAN return dulu.
          // Kita perlu lanjut looping ke atas untuk melengkapi $layoutHierarchy (parent layouts)
          $foundMatch = true;
          $matchedLayoutId = $layoutId;

          // STOP RENDERING: Jangan render layout ini ke dalam $html
          // Karena client sudah punya container ini.
        } elseif (!$foundMatch) {
          // Belum ketemu match, jadi kita harus render layout ini (membungkus content)

          // Variabel $children akan berisi HTML dari level di bawahnya.
          $children = $html;

          ob_start();
          require $layoutToUse; // Gunakan path yang ditemukan.
          $html = ob_get_clean(); // HTML yang baru adalah hasil dari layout yang membungkus children.

          $this->detectAndInjectScript($layoutToUse, $rootViewsPath, $html);
        }
      }
      // --- AKHIR PERUBAHAN ---

      // Berhenti jika kita sudah mencapai root Views
      if ($currentDir === $rootViewsPath) {
        break;
      }

      // Pindah ke direktori induk
      $currentDir = dirname($currentDir);
    }

    // Jika ini request SPA dan kita menemukan match di tengah jalan
    if ($isSpaRequest && $foundMatch) {
      // Resolusi hash untuk styles agar cache busting bekerja di SPA
      $styles = array_map(function ($style) {
        // Load manifest manual karena helper asset() mengembalikan Full URL
        static $manifest = null;
        if ($manifest === null) {
          $manifestPath = __DIR__ . '/../../public/build/manifest.json';
          if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
          } else {
            $manifest = [];
          }
        }

        $key = 'assets/' . $style;
        if (isset($manifest[$key])) {
          // Manifest value: assets/(app)/.../style.hash.css
          // Kita butuh: (app)/.../style.hash.css (buang 'assets/' prefix)
          // Karena spa.js akan menambahkan 'build/assets/' di depannya.
          return substr($manifest[$key], 7); // 7 = strlen('assets/')
        }
        
        return $style;
      }, View::getStyles());

      return json_encode([
        'html' => $html,
        'meta' => [
          'title' => $view->meta->title,
          'csrf_token' => csrf_token(),
          'layout' => $layoutHierarchy, // Mengirim ARRAY layout [child, parent, root]
          'styles' => $styles
        ]
      ]);
    }

    return $html;
  }

  /**
   * Helper: Mendeteksi keberadaan file CSS pendamping dan mendaftarkannya ke View.
   */
  private function detectAndRegisterStyle(string $phpFilePath, string $rootViewsPath): void
  {
    // 1. Cek file CSS dengan nama yang sama (misal: header.php -> header.css)
    $cssPathSameName = preg_replace('/\.php$/', '.css', $phpFilePath);
    if (file_exists($cssPathSameName)) {
      $relativePath = ltrim(str_replace($rootViewsPath, '', $cssPathSameName), '/');
      View::addStyle($relativePath);
      return; // Prioritaskan nama yang sama
    }

    // 2. Jika tidak ada file CSS dengan nama spesifik, cek style.css di folder yang sama
    // Ini berlaku untuk SEMUA file (index.php, [id].php, detail.php, dll)
    $cssPathStyle = dirname($phpFilePath) . '/style.css';
    if (file_exists($cssPathStyle)) {
      $relativePath = ltrim(str_replace($rootViewsPath, '', $cssPathStyle), '/');
      View::addStyle($relativePath);
    }

    // 3. Khusus untuk layout/index.php, cek juga sidebar.css (Legacy support)
    if (basename($phpFilePath) === 'index.php') {
      $cssPathSidebar = dirname($phpFilePath) . '/sidebar.css';
      if (file_exists($cssPathSidebar)) {
        $relativePath = ltrim(str_replace($rootViewsPath, '', $cssPathSidebar), '/');
        View::addStyle($relativePath);
      }
    }
  }

  /**
   * Helper: Mendeteksi keberadaan file JS pendamping dan menyuntikkannya ke HTML.
   */
  private function detectAndInjectScript(string $phpFilePath, string $rootViewsPath, string &$html): void
  {
    $jsPath = null;

    // 1. Cek file JS dengan nama yang sama (misal: index.php -> index.js)
    $jsPathSameName = preg_replace('/\.php$/', '.js', $phpFilePath);
    if (file_exists($jsPathSameName)) {
      $jsPath = $jsPathSameName;
    } else {
      // 2. Jika tidak ada, cek script.js di folder yang sama
      $jsPathScript = dirname($phpFilePath) . '/script.js';
      if (file_exists($jsPathScript)) {
        $jsPath = $jsPathScript;
      }
    }

    if ($jsPath) {
      $relativePath = ltrim(str_replace($rootViewsPath, '', $jsPath), '/');
      // Gunakan helper asset() agar menggunakan versioning (hash)
      $url = asset('assets/' . $relativePath);
      $html .= PHP_EOL . '<script src="' . $url . '"></script>' . PHP_EOL;
    }
  }

  /**
   * Mencari secara rekursif semua path direktori grup (contoh: (app), (app)/(mahasiswa)).
   *
   * @param string $directory Direktori saat ini untuk dipindai.
   * @param string $rootPath Path root awal untuk menghitung path relatif.
   * @param array $results Array referensi untuk menyimpan hasil.
   * @param string $currentPrefix Prefix yang dibangun dari level sebelumnya.
   */
  private function findRouteGroupPaths(string $directory, string $rootPath, array &$results, string $currentPrefix = ''): void
  {
    $items = scandir($directory);

    foreach ($items as $item) {
      if ($item === '.' || $item === '..') {
        continue;
      }

      $fullPath = $directory . '/' . $item;

      if (is_dir($fullPath)) {
        $isGroupFolder = ($item[0] === '(' && substr($item, -1) === ')');

        // Hanya lanjutkan membangun prefix jika folder saat ini adalah folder grup
        if ($isGroupFolder) {
          $newPrefix = trim($currentPrefix . '/' . $item, '/');
          $results[] = $newPrefix;
          // Lanjutkan pencarian rekursif dari dalam folder grup ini
          $this->findRouteGroupPaths($fullPath, $rootPath, $results, $newPrefix);
        } else {
          // Jika bukan folder grup, tetap cari di dalamnya tapi jangan tambahkan ke prefix
          $this->findRouteGroupPaths($fullPath, $rootPath, $results, $currentPrefix);
        }
      }
    }
  }
}
