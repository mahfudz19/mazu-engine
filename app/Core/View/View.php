<?php

namespace App\Core\View;

use App\Core\Foundation\Container;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Interfaces\RenderableInterface;
use App\Services\ViewService;

class View implements RenderableInterface
{
  public string $path;
  public array $props;
  public PageMeta $meta;

  /**
   * Menyimpan daftar path CSS yang ditemukan secara otomatis.
   * @var array<string>
   */
  protected static array $styles = [];

  public function __construct(
    Container $container,
    ?string $path = null,
    array $props = [],
    ?PageMeta $meta = null
  ) {
    if ($path === null) {
      /** @var Request $request */
      $request = $container->resolve(Request::class);
      $this->path = $request->getMatchedRoutePattern() ?? $request->getBasePath();
    } else {
      $this->path = $path;
    }

    $this->props = $props;
    $this->meta = $meta ?? new PageMeta('Untitled Page');
  }

  /**
   * Menambahkan path CSS ke antrian.
   * Path harus relatif terhadap folder Views, contoh: '(app)/layout/sidebar.css'
   */
  public static function addStyle(string $path): void
  {
    if (!in_array($path, self::$styles)) {
      self::$styles[] = $path;
    }
  }

  /**
   * Mengambil semua style yang terdaftar.
   * @return array<string>
   */
  public static function getStyles(): array
  {
    return self::$styles;
  }

  /**
   * Merender tag <link> untuk semua CSS yang terkumpul.
   */
  public static function renderStyles(): string
  {
    $html = '';
    foreach (self::$styles as $stylePath) {
      $url = asset('assets/' . $stylePath);
      $html .= '<link rel="stylesheet" href="' . $url . '">' . PHP_EOL;
    }
    return $html;
  }

  /**
   * Merender semua meta tag SEO dan tag head standar.
   */
  public static function renderMeta(PageMeta $meta): string
  {
    $html = '';

    // Basic Meta
    $html .= '<meta charset="UTF-8">' . PHP_EOL;
    $html .= '<title>' . htmlspecialchars($meta->title) . '</title>' . PHP_EOL;

    if ($meta->description) {
      $html .= '<meta name="description" content="' . htmlspecialchars($meta->description) . '">' . PHP_EOL;
    }
    if ($meta->keywords) {
      $html .= '<meta name="keywords" content="' . htmlspecialchars($meta->keywords) . '">' . PHP_EOL;
    }
    $html .= '<meta name="robots" content="' . htmlspecialchars($meta->robots) . '">' . PHP_EOL;
    if ($meta->canonical) {
      $html .= '<link rel="canonical" href="' . htmlspecialchars($meta->canonical) . '">' . PHP_EOL;
    }

    $html .= PHP_EOL . '  <!-- Open Graph / Facebook -->' . PHP_EOL;
    $html .= '<meta property="og:type" content="' . htmlspecialchars($meta->ogType ?? $meta->type) . '">' . PHP_EOL;
    $html .= '<meta property="og:title" content="' . htmlspecialchars($meta->ogTitle ?? $meta->title) . '">' . PHP_EOL;
    if ($meta->ogDescription ?? $meta->description) {
      $html .= '<meta property="og:description" content="' . htmlspecialchars($meta->ogDescription ?? $meta->description) . '">' . PHP_EOL;
    }
    if ($meta->ogImage ?? $meta->image) {
      $html .= '<meta property="og:image" content="' . htmlspecialchars($meta->ogImage ?? $meta->image) . '">' . PHP_EOL;
    }
    if ($meta->canonical || $meta->ogUrl) {
      $html .= '<meta property="og:url" content="' . htmlspecialchars($meta->ogUrl ?? $meta->canonical) . '">' . PHP_EOL;
    }
    if ($meta->ogSiteName ?? $meta->siteName) {
      $html .= '<meta property="og:site_name" content="' . htmlspecialchars($meta->ogSiteName ?? $meta->siteName) . '">' . PHP_EOL;
    }
    if ($meta->locale) {
      $html .= '<meta property="og:locale" content="' . htmlspecialchars($meta->locale) . '">' . PHP_EOL;
    }

    $html .= PHP_EOL . '  <!-- Twitter -->' . PHP_EOL;
    if ($meta->twitterCard) {
      $html .= '<meta name="twitter:card" content="' . htmlspecialchars($meta->twitterCard) . '">' . PHP_EOL;
    }
    if ($meta->twitterSite) {
      $html .= '<meta name="twitter:site" content="' . htmlspecialchars($meta->twitterSite) . '">' . PHP_EOL;
    }
    if ($meta->twitterCreator) {
      $html .= '<meta name="twitter:creator" content="' . htmlspecialchars($meta->twitterCreator) . '">' . PHP_EOL;
    }
    if ($meta->twitterTitle) {
      $html .= '<meta name="twitter:title" content="' . htmlspecialchars($meta->twitterTitle) . '">' . PHP_EOL;
    }
    if ($meta->twitterDescription) {
      $html .= '<meta name="twitter:description" content="' . htmlspecialchars($meta->twitterDescription) . '">' . PHP_EOL;
    }
    if ($meta->twitterImage ?? $meta->ogImage ?? $meta->image) {
      $html .= '<meta name="twitter:image" content="' . htmlspecialchars($meta->twitterImage ?? $meta->ogImage ?? $meta->image) . '">' . PHP_EOL;
    }

    $html .= PHP_EOL . '  <!-- CSRF Token -->' . PHP_EOL;
    $html .= '<meta name="csrf-token" content="' . csrf_token() . '">' . PHP_EOL;

    $html .= PHP_EOL . '  <!-- Viewport & Favicon -->' . PHP_EOL;
    $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . PHP_EOL;
    $html .= '<link rel="icon" type="image/x-icon" href="' . getBaseUrl('/logo_app/favicon.ico') . '">' . PHP_EOL;

    return $html;
  }

  /**
   * Merender script core engine (SPA, Ripple, Component Loader).
   */
  public static function renderScripts(): string
  {
    // Menggunakan env() helper secara langsung karena App::config() tidak tersedia
    $config = require __DIR__ . '/../../../config/app.php';
    $spaConfig = $config['spa'] ?? [];
    $authConfig = $config['auth'] ?? [];

    $authPublic = [
      'mode' => $authConfig['mode'] ?? 'token',
      'token_header' => $authConfig['token_header'] ?? 'Authorization',
      'token_prefix' => $authConfig['token_prefix'] ?? 'Bearer',
      'token_key' => $authConfig['token_key'] ?? 'token',
      'token_cookie' => $authConfig['token_cookie'] ?? 'token',
      'user_key' => $authConfig['user_key'] ?? 'user',
      'token_storage' => $authConfig['token_storage'] ?? 'cookie',
      'redirect_login' => $authConfig['redirect_login'] ?? '/login',
      'auto_attach' => $authConfig['auto_attach'] ?? true,
      'auto_logout' => $authConfig['auto_logout'] ?? true,
    ];

    $html = '';
    $html .= '<script>window.mazuConfig = ' . json_encode(['spa' => $spaConfig, 'auth' => $authPublic]) . ';</script>' . PHP_EOL;
    $html .= '<script src="' . asset('js/spa.js') . '"></script>' . PHP_EOL;
    return $html;
  }

  /**
   * Merender view ini menjadi sebuah Response.
   *
   * @param Container $container
   * @return Response
   */
  public function render(Container $container): Response
  {
    /** @var ViewService $viewService */
    $viewService = $container->resolve(ViewService::class);
    $output = $viewService->render($this);

    /** @var Request $request */
    $request = $container->resolve(Request::class);

    if ($request->isSpaRequest()) {
      // 1. Minify HTML Output (Hapus spasi berlebih, enter, dan tab)
      $output = preg_replace('/>\s+</', '><', $output);
      $output = preg_replace('/\s+/', ' ', $output);
      $output = trim($output);

      $response = new Response($container, $output);
      $response->setHeader('Content-Type', 'application/json');
      $response->setHeader('Cache-Control', 'no-cache');
      $response->setHeader('Vary', 'X-SPA-REQUEST, X-SPA-TARGET-LAYOUT, X-SPA-LAYOUTS');
      return $response;
    }

    return new Response($container, $output);
  }
}
