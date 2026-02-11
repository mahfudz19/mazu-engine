<?php

namespace App\Core\Http;

use App\Core\Foundation\Container;

class RedirectResponse extends Response
{
  protected string $targetUrl;
  protected bool $isHard = false;
  protected bool $isBlank = false;

  public function __construct(?Container $container, string $url, int $statusCode = 302)
  {
    $this->targetUrl = getBaseUrl($url);
    // Teruskan container ke parent constructor
    parent::__construct($container, '', $statusCode, ['Location' => $this->targetUrl]);
  }

  /**
   * Set redirect sebagai "Hard Redirect" (Full Page Reload).
   * Berguna untuk memaksa refresh browser atau reset state SPA.
   */
  public function hard(): self
  {
    $this->isHard = true;
    return $this;
  }

  /**
   * Membuka URL di tab baru (Client-side logic untuk SPA).
   * Note: Untuk non-SPA, ini tidak akan berpengaruh (browser akan redirect biasa).
   */
  public function blank(): self
  {
    $this->isBlank = true;
    return $this;
  }

  /**
   * Menggunakan status code 301 (Moved Permanently) agar browser men-cache redirect ini.
   */
  public function withCache(): self
  {
    $this->statusCode = 301;
    return $this;
  }

  public function send(): void
  {
    // Cek apakah ini request dari SPA
    $request = $this->container->resolve(Request::class);

    // Jika SPA Request, kita kirim instruksi JSON alih-alih header Location standard
    // KECUALI jika ini hard redirect yang diinginkan (tapi tetap lebih bersih pakai JSON agar SPA tau)
    if ($request->isSpaRequest()) {
      // Ubah ke 200 OK agar fetch tidak otomatis mengikuti redirect (yang akan me-load HTML page baru sebagai response text)
      // Kita ingin SPA client menangani redirect ini secara manual.
      $this->statusCode = 200;

      // Hapus header Location agar browser/fetch tidak bingung
      unset($this->headers['Location']);

      // Set Content-Type JSON
      $this->headers['Content-Type'] = 'application/json';

      // Kirim payload instruksi redirect
      $this->content = json_encode([
        'redirect' => $this->targetUrl,
        'force_reload' => $this->isHard,
        'new_tab' => $this->isBlank
      ]);
    }

    parent::send();
  }
}
