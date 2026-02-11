<?php

namespace App\Core\View;

/**
 * Class PageMeta
 * Sebuah container untuk semua metadata halaman yang relevan untuk SEO dan social sharing.
 */
class PageMeta
{
  public string $title;
  public ?string $description;
  public ?string $image;
  public ?string $keywords;
  public ?string $canonical;
  public string $type;
  public string $robots;

  public ?string $siteName;
  public ?string $locale;

  public ?string $ogTitle;
  public ?string $ogDescription;
  public ?string $ogImage;
  public ?string $ogType;
  public ?string $ogUrl;
  public ?string $ogSiteName;

  public ?string $twitterCard;
  public ?string $twitterSite;
  public ?string $twitterCreator;
  public ?string $twitterTitle;
  public ?string $twitterDescription;
  public ?string $twitterImage;

  public ?bool $noindex;
  public ?bool $nofollow;

  public function __construct(
    string $title,
    ?string $description = null,
    ?string $image = null,
    ?string $keywords = null,
    ?string $canonical = null,
    string $type = 'website',
    string $robots = 'index, follow',
    ?string $siteName = null,
    ?string $locale = null,
    ?string $ogTitle = null,
    ?string $ogDescription = null,
    ?string $ogImage = null,
    ?string $ogType = null,
    ?string $ogUrl = null,
    ?string $ogSiteName = null,
    ?string $twitterCard = null,
    ?string $twitterSite = null,
    ?string $twitterCreator = null,
    ?string $twitterTitle = null,
    ?string $twitterDescription = null,
    ?string $twitterImage = null,
    ?bool $noindex = null,
    ?bool $nofollow = null
  ) {
    $this->title = $title;
    $this->description = $description;
    $this->image = $image;
    $this->keywords = $keywords;
    $this->canonical = $canonical;
    $this->type = $type;
    $this->robots = $robots;

    $this->siteName = $siteName;
    $this->locale = $locale;

    $this->ogTitle = $ogTitle;
    $this->ogDescription = $ogDescription;
    $this->ogImage = $ogImage;
    $this->ogType = $ogType;
    $this->ogUrl = $ogUrl;
    $this->ogSiteName = $ogSiteName;

    $this->twitterCard = $twitterCard;
    $this->twitterSite = $twitterSite;
    $this->twitterCreator = $twitterCreator;
    $this->twitterTitle = $twitterTitle;
    $this->twitterDescription = $twitterDescription;
    $this->twitterImage = $twitterImage;

    $this->noindex = $noindex;
    $this->nofollow = $nofollow;

    if ($this->noindex === true || $this->nofollow === true) {
      $directives = [];
      $directives[] = $this->noindex === true ? 'noindex' : 'index';
      $directives[] = $this->nofollow === true ? 'nofollow' : 'follow';
      $this->robots = implode(', ', $directives);
    }
  }
}
