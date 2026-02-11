<?php

namespace App\Core\Http;

class Request
{
  public readonly array $query;
  public readonly array $body;
  public readonly array $files;
  public readonly array $server;
  private ?string $matchedRoutePattern = null;
  private array $params = [];

  protected array $jsonInput;
  protected array $input;

  public function __construct()
  {
    $this->query = $_GET;
    $this->body = $_POST;
    $this->server = $_SERVER;

    $this->files = $this->normalizeFiles($_FILES);

    $this->parseJsonBody();
    $this->compileInput();
  }

  /**
   * Menetapkan parameter yang cocok dari rute.
   * @param array $params Parameter rute (misal: ['id' => 123])
   */
  public function setRouteParams(array $params): void
  {
    $this->params = $params;
  }

  /**
   * Mengambil satu parameter rute berdasarkan nama.
   * @param string $key Nama parameter
   * @param mixed|null $default Nilai default jika tidak ditemukan
   * @return mixed
   */
  public function param(string $key, $default = null): mixed
  {
    return $this->params[$key] ?? $default;
  }

  public function isSpaRequest(): bool
  {
    // Gunakan method header() yang lebih robust (mendukung getallheaders())
    return $this->header('X-SPA-REQUEST') === 'true';
  }

  public function wantsJson(): bool
  {
    $accept = $this->header('Accept') ?? '';
    return str_contains($accept, '/json') || str_contains($accept, '+json');
  }

  /**
   * Mengambil Bearer Token dari header Authorization.
   * @return string|null
   */
  public function bearerToken(): ?string
  {
    $header = $this->header('Authorization', '');
    if (str_starts_with($header, 'Bearer ')) {
      return substr($header, 7);
    }
    return null;
  }

  public function getSpaTargetLayout(): ?string
  {
    return $this->header('X-SPA-TARGET-LAYOUT');
  }

  public function getSpaLayouts(): array
  {
    $header = $this->header('X-SPA-LAYOUTS');
    if (!$header) return [];
    return json_decode($header, true) ?? [];
  }

  // NEW: Method untuk parsing JSON body
  protected function parseJsonBody(): void
  {
    $this->jsonInput = [];
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($contentType, 'application/json')) {
      $rawBody = file_get_contents('php://input');
      $decoded = json_decode($rawBody, true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $this->jsonInput = $decoded;
      }
    }
  }

  // NEW: Method untuk mengkompilasi semua input
  protected function compileInput(): void
  {
    $this->input = array_merge($_GET, $_POST, $this->jsonInput);
  }

  /**
   * Mengambil satu file yang di-upload berdasarkan kuncinya.
   *
   * @param string $key Kunci dari input file (misal: 'avatar').
   * @return UploadedFile|null
   */
  public function file(string $key): ?UploadedFile
  {
    return $this->files[$key] ?? null;
  }

  /**
   * Mengubah array $_FILES yang aneh menjadi struktur yang lebih intuitif
   * dan mengubah setiap entri menjadi objek UploadedFile.
   */
  private function normalizeFiles(array $filesArray): array
  {
    $normalized = [];
    foreach ($filesArray as $key => $file) {
      $normalized[$key] = null;

      if (isset($file['name']) && is_array($file['name'])) {
        $normalized[$key] = [];
        foreach ($file['name'] as $index => $name) {
          if ($file['error'][$index] !== UPLOAD_ERR_NO_FILE) {
            $normalized[$key][] = new UploadedFile([
              'name' => $name,
              'type' => $file['type'][$index],
              'tmp_name' => $file['tmp_name'][$index],
              'error' => $file['error'][$index],
              'size' => $file['size'][$index],
            ]);
          }
        }
      } elseif (isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        $normalized[$key] = new UploadedFile($file);
      }
    }
    return $normalized;
  }

  public function getPath(): string
  {
    return $this->getBasePath();
  }

  public function getBasePath(): string
  {
    $raw = $this->server['REQUEST_URI'] ?? '/';

    $qPos = strpos($raw, '?');
    if ($qPos !== false) {
      $raw = substr($raw, 0, $qPos);
    }

    $subdirectory = '/' . trim((string)(getSubdirectory() ?? ''), '/');
    if ($subdirectory !== '/' && str_starts_with($raw, $subdirectory)) {
      $raw = substr($raw, strlen($subdirectory));
    }

    $path = '/' . trim($raw, '/');
    return $path === '/' ? '/' : $path;
  }

  public function getMethod(): string
  {
    return strtolower($this->server['REQUEST_METHOD'] ?? 'get');
  }

  public function input(?string $key = null, mixed $default = null): mixed
  {
    if ($key === null) {
      return $this->input;
    }
    return $this->input[$key] ?? $default;
  }

  public function getBody(): array
  {
    $value = $this->input();
    return is_array($value) ? $value : [];
  }

  public function post(?string $key = null, mixed $default = null): mixed
  {
    // Utamakan JSON input untuk POST requests
    if ($key === null) {
      return array_merge($_POST, $this->jsonInput);
    }
    return $this->jsonInput[$key] ?? $_POST[$key] ?? $default;
  }

  public function get(?string $key = null, mixed $default = null): mixed
  {
    if ($key === null) {
      return $_GET;
    }
    return $_GET[$key] ?? $default;
  }

  public function setMatchedRoutePattern(string $pattern): void
  {
    $this->matchedRoutePattern = $pattern;
  }

  public function getMatchedRoutePattern(): ?string
  {
    return $this->matchedRoutePattern;
  }

  /**
   * Mengambil nilai dari HTTP request header.
   *
   * @param string $key Nama header (case-insensitive, cth: 'Content-Type' atau 'if-none-match').
   * @param mixed|null $default Nilai default jika header tidak ditemukan.
   * @return string|null
   */
  public function header(string $key, $default = null): ?string
  {
    // Cara paling andal adalah menggunakan getallheaders() jika tersedia
    if (function_exists('getallheaders')) {
      $headers = getallheaders();
      // Lakukan pencarian case-insensitive
      $lowerKey = strtolower($key);
      foreach ($headers as $headerName => $headerValue) {
        if (strtolower($headerName) === $lowerKey) {
          return $headerValue;
        }
      }
    }

    // Fallback manual jika getallheaders() tidak tersedia
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
    if (isset($this->server[$serverKey])) {
      return $this->server[$serverKey];
    }

    // Fallback untuk header spesial yang tidak diawali HTTP_
    $specialHeaders = [
      'Content-Type' => 'CONTENT_TYPE',
      'Content-Length' => 'CONTENT_LENGTH',
      'Content-Md5' => 'CONTENT_MD5',
    ];
    $normalizedKey = ucwords(strtolower(str_replace(['-', '_'], ' ', $key)));
    $normalizedKey = str_replace(' ', '-', $normalizedKey);

    if (isset($specialHeaders[$normalizedKey]) && isset($this->server[$specialHeaders[$normalizedKey]])) {
      return $this->server[$specialHeaders[$normalizedKey]];
    }

    return $default;
  }
}
