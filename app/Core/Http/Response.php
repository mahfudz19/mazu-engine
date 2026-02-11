<?php

namespace App\Core\Http;

use App\Core\Foundation\Container;
use App\Core\View\PageMeta;
use App\Core\View\View;

class Response
{
  protected string $content;
  protected int $statusCode;
  protected array $headers = [];
  protected ?Container $container;

  public function __construct(?Container $container = null, string $content = '', int $statusCode = 200, array $headers = [])
  {
    $this->container = $container;
    $this->content = $content;
    $this->statusCode = $statusCode;
    $this->headers = $headers;
  }

  public function setStatusCode(int $code): self
  {
    $this->statusCode = $code;
    return $this;
  }

  public function setHeader(string $name, string $value): self
  {
    $this->headers[$name] = $value;
    return $this;
  }

  public function setContent(string $content): self
  {
    $this->content = $content;
    return $this;
  }

  public function send(): void
  {
    $this->prepare();

    // Kirim status code
    http_response_code($this->statusCode);

    // Kirim headers
    foreach ($this->headers as $name => $value) {
      header("$name: $value");
    }

    // Kirim konten
    echo $this->content;
  }

  protected function prepare(): void
  {
    if (!$this->container) return;

    /** @var Request $request */
    $request = $this->container->resolve(Request::class);

    if ($this->statusCode === 200 && !empty($this->content)) {
      $etag = '"' . md5($this->content) . '"';
      $this->setHeader('ETag', $etag);
      $this->setHeader('Cache-Control', 'no-cache, must-revalidate');

      $ifNoneMatch = $request->header('If-None-Match');
      if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
        $this->setStatusCode(304);
        $this->setContent('');
        return;
      }
    }

    // 2. GZIP Implementation
    if ($this->statusCode === 200 && !empty($this->content) && strlen($this->content) > 2048) {
      $acceptEncoding = $request->server['HTTP_ACCEPT_ENCODING'] ?? '';
      if (str_contains($acceptEncoding, 'gzip') && function_exists('gzencode')) {
        // Jangan kompres jika sudah dikompres (misal dari View)
        if (!isset($this->headers['Content-Encoding'])) {
          $compressed = gzencode($this->content, 9);
          if ($compressed !== false) {
            $this->setContent($compressed);
            $this->setHeader('Content-Encoding', 'gzip');
            $this->setHeader('Content-Length', (string)strlen($this->content));
          }
        }
      }
    }
  }

  public function redirect(string $url, int $statusCode = 302): RedirectResponse
  {
    return new RedirectResponse($this->container, $url, $statusCode);
  }

  /**
   * Membuat objek View untuk dirender dengan pendekatan berbasis opsi.
   *
   * @param array $props Data yang akan dikirim ke view.
   * @param array $options Opsi tambahan seperti ['path' => string, 'meta' => array].
   * @return View
   */
  public function renderPage(array $props = [], array $options = []): View
  {
    if (!$this->container) {
      throw new \LogicException('Container tidak tersedia di dalam Response object.');
    }

    // Ekstrak path dari opsi, jika tidak ada, biarkan null (akan di-handle oleh View)
    $path = $options['path'] ?? null;

    // Ekstrak meta dari opsi
    $metaArray = $options['meta'] ?? [];

    // Buat objek PageMeta dari array yang diberikan
    $pageMeta = new PageMeta(
      $metaArray['title'] ?? env('APP_NAME', 'Talent'),
      $metaArray['description'] ?? null,
      $metaArray['image'] ?? null,
      $metaArray['keywords'] ?? null,
      $metaArray['canonical'] ?? null,
      $metaArray['type'] ?? 'website',
      $metaArray['robots'] ?? 'index, follow'
    );

    // Panggil constructor View yang sudah "pintar"
    return new View($this->container, $path, $props, $pageMeta);
  }

  public function json(mixed $data, int $status = 200): JsonResponse
  {
    return new JsonResponse($this->container, $data, $status);
  }

  /**
   * Menghasilkan response XML untuk Sitemap.
   *
   * @param array $data Array of ['url' => string, 'lastmod' => string, 'changefreq' => string, 'priority' => float]
   * @return self
   */
  public function generateSitemap(array $data): self
  {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($data as $item) {
      $xml .= "  <url>\n";
      $xml .= "    <loc>" . htmlspecialchars($item['url'], ENT_XML1, 'UTF-8') . "</loc>\n";
      if (isset($item['lastmod'])) {
        $xml .= "    <lastmod>" . htmlspecialchars($item['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
      }
      if (isset($item['changefreq'])) {
        $xml .= "    <changefreq>" . htmlspecialchars($item['changefreq'], ENT_XML1, 'UTF-8') . "</changefreq>\n";
      }
      if (isset($item['priority'])) {
        $xml .= "    <priority>" . number_format($item['priority'], 1) . "</priority>\n";
      }
      $xml .= "  </url>\n";
    }

    $xml .= '</urlset>';

    $this->setContent($xml);
    $this->setHeader('Content-Type', 'application/xml; charset=utf-8');
    return $this;
  }

  /**
   * Menghasilkan response text untuk robots.txt.
   *
   * @param array $data Array of ['user_agent' => string, 'allow' => array, 'disallow' => array, 'sitemap' => string]
   * @return self
   */
  public function generateRobots(array $data): self
  {
    $content = "";
    foreach ($data as $group) {
      if (isset($group['user_agent'])) {
        $content .= "User-agent: " . $group['user_agent'] . "\n";
      }

      if (isset($group['disallow'])) {
        foreach ((array)$group['disallow'] as $path) {
          $content .= "Disallow: " . $path . "\n";
        }
      }

      if (isset($group['allow'])) {
        foreach ((array)$group['allow'] as $path) {
          $content .= "Allow: " . $path . "\n";
        }
      }

      if (isset($group['sitemap'])) {
        $content .= "Sitemap: " . $group['sitemap'] . "\n";
      }
      $content .= "\n";
    }

    $this->setContent(trim($content) . "\n");
    $this->setHeader('Content-Type', 'text/plain; charset=utf-8');
    return $this;
  }
}
