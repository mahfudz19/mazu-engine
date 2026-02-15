<?php

namespace App\Services;

use App\Core\View\PageMeta;
use App\Core\Http\Request;

class SeoService
{
  private ConfigService $config;
  private Request $request;

  public function __construct(ConfigService $config, Request $request)
  {
    $this->config = $config;
    $this->request = $request;
  }

  public function forPage(string $title, ?string $description = null, array $options = []): PageMeta
  {
    $seoConfig = $this->config->get('view.seo', []);

    $siteName = $options['siteName'] ?? $this->config->get('app_name');
    $appendSiteName = $options['appendSiteName'] ?? true;
    $separator = $options['titleSeparator'] ?? ' | ';

    $fullTitle = $appendSiteName && $siteName
      ? $title . $separator . $siteName
      : $title;

    $canonical = $options['canonical'] ?? null;

    if ($canonical === null) {
      $canonical = $this->canonicalFromRequest();
    }

    if ($canonical !== null && !$this->isAbsoluteUrl($canonical)) {
      $canonical = $this->makeAbsoluteUrl($canonical);
    }

    $image = $options['image'] ?? null;
    if ($image === null && !empty($seoConfig['default_og_image'])) {
      $image = $seoConfig['default_og_image'];
    }
    if ($image !== null && !$this->isAbsoluteUrl($image)) {
      $image = $this->makeAbsoluteUrl($image);
    }

    $ogImage = $options['ogImage'] ?? null;
    if ($ogImage === null && !empty($seoConfig['default_og_image'])) {
      $ogImage = $seoConfig['default_og_image'];
    }
    if ($ogImage !== null && !$this->isAbsoluteUrl($ogImage)) {
      $ogImage = $this->makeAbsoluteUrl($ogImage);
    }

    $twitterImage = $options['twitterImage'] ?? null;
    if ($twitterImage === null && !empty($seoConfig['default_og_image'])) {
      $twitterImage = $seoConfig['default_og_image'];
    }
    if ($twitterImage !== null && !$this->isAbsoluteUrl($twitterImage)) {
      $twitterImage = $this->makeAbsoluteUrl($twitterImage);
    }

    $type = $options['type'] ?? 'website';
    $robots = $options['robots'] ?? 'index, follow';
    $locale = $options['locale'] ?? ($seoConfig['default_locale'] ?? null);

    $twitterCard = $options['twitterCard'] ?? ($seoConfig['twitter_card'] ?? null);
    $twitterSite = $options['twitterSite'] ?? ($seoConfig['twitter_site'] ?? null);

    $defaultNoindex = $seoConfig['noindex'] ?? null;
    $noindex = array_key_exists('noindex', $options) ? $options['noindex'] : $defaultNoindex;

    $pageMeta = new PageMeta(
      $fullTitle,
      $description,
      $image,
      $options['keywords'] ?? null,
      $canonical,
      $type,
      $robots,
      $siteName,
      $locale,
      $options['ogTitle'] ?? null,
      $options['ogDescription'] ?? null,
      $ogImage,
      $options['ogType'] ?? null,
      $options['ogUrl'] ?? null,
      $options['ogSiteName'] ?? null,
      $twitterCard,
      $twitterSite,
      $options['twitterCreator'] ?? null,
      $options['twitterTitle'] ?? null,
      $options['twitterDescription'] ?? null,
      $twitterImage,
      $noindex,
      $options['nofollow'] ?? null
    );

    return $pageMeta;
  }

  public function noIndex(PageMeta $meta, bool $nofollow = true): void
  {
    $meta->noindex = true;
    $meta->nofollow = $nofollow;

    $parts = [];
    $parts[] = 'noindex';
    $parts[] = $nofollow ? 'nofollow' : 'follow';
    $meta->robots = implode(', ', $parts);
  }

  private function canonicalFromRequest(): string
  {
    return $this->makeAbsoluteUrl($this->request->getPath());
  }

  private function isAbsoluteUrl(string $url): bool
  {
    return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
  }

  private function makeAbsoluteUrl(string $path): string
  {
    if ($this->isAbsoluteUrl($path)) {
      return $path;
    }

    $path = '/' . ltrim($path, '/');

    if (function_exists('getBaseUrl')) {
      return getBaseUrl($path);
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . $path;
  }

  /**
   * Render tag hreflang dari daftar alternatif.
   * Format input: array of ['hreflang' => 'id', 'href' => 'https://example.com/id/...']
   */
  public function buildHreflang(array $alternates): string
  {
    $out = [];
    foreach ($alternates as $alt) {
      $hreflang = isset($alt['hreflang']) ? (string)$alt['hreflang'] : '';
      $href = isset($alt['href']) ? (string)$alt['href'] : '';
      if ($hreflang !== '' && $href !== '') {
        if (!$this->isAbsoluteUrl($href)) {
          $href = $this->makeAbsoluteUrl($href);
        }
        $out[] = '<link rel="alternate" hreflang="' . htmlspecialchars($hreflang) . '" href="' . htmlspecialchars($href) . '">';
      }
    }
    return implode("\n", $out);
  }

  /**
   * Render JSON-LD script.
   * Input: associative array schema.org yang akan di-encode ke JSON.
   */
  public function buildJsonLd(array $data): string
  {
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return '<script type="application/ld+json">' . $json . '</script>';
  }
}
