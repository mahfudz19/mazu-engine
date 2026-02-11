<?php

namespace App\Services;

class SessionService
{
  private bool $isStarted = false;

  public function __construct() {}

  private function ensureSessionStarted(): void
  {
    if ($this->isStarted) {
      return;
    }

    if (session_status() === PHP_SESSION_NONE) {
      $lifetime = 12 * 60 * 60;
      ini_set('session.gc_maxlifetime', (string)$lifetime);
      ini_set('session.cookie_lifetime', (string)$lifetime);

      $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
      $params = session_get_cookie_params();
      session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
      ]);

      session_start();
    }

    $this->isStarted = true;
  }

  public function get(string $key, $default = null)
  {
    $this->ensureSessionStarted();
    return $_SESSION[$key] ?? $default;
  }

  public function getAll()
  {
    $this->ensureSessionStarted();
    return $_SESSION;
  }

  public function set(string $key, $value): void
  {
    $this->ensureSessionStarted();
    $_SESSION[$key] = $value;
  }

  public function destroy(): void
  {
    $this->ensureSessionStarted();

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', !empty($params['secure']), !empty($params['httponly']));
    }

    session_destroy();
    $this->isStarted = false;
  }

  /**
   * Menghapus sebuah kunci dari sesi.
   *
   * @param string $key Kunci yang akan dihapus dari sesi.
   * @return void
   */
  public function remove(string $key): void
  {
    $this->ensureSessionStarted();
    unset($_SESSION[$key]);
  }

  /**
   * Menghasilkan token CSRF baru jika belum ada, atau mengembalikan yang sudah ada.
   *
   * @return string
   */
  public function getCsrfToken(): string
  {
    $this->ensureSessionStarted();
    if (!isset($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
  }

  /**
   * Memvalidasi token CSRF.
   *
   * @param string|null $token Token yang akan divalidasi.
   * @return bool
   */
  public function validateCsrfToken(?string $token): bool
  {
    $this->ensureSessionStarted();
    if (empty($token) || !isset($_SESSION['csrf_token'])) {
      return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
  }

  /**
   * Regenerasi token CSRF.
   *
   * @return string Token baru.
   */
  public function regenerateCsrfToken(): string
  {
    $this->ensureSessionStarted();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
  }

  /**
   * Menyetel flash message yang hanya akan tersedia untuk request berikutnya.
   *
   * @param string $key Kunci untuk flash message.
   * @param mixed $value Nilai flash message.
   * @return void
   */
  public function setFlash(string $key, $value): void
  {
    $this->ensureSessionStarted();
    // Simpan flash message di bawah kunci khusus
    $_SESSION['_flash'][$key] = $value;
  }

  /**
   * Mengambil flash message dan menghapusnya dari sesi.
   *
   * @param string $key Kunci flash message.
   * @param mixed $default Nilai default jika flash message tidak ditemukan.
   * @return mixed
   */
  public function getFlash(string $key, $default = null)
  {
    $this->ensureSessionStarted();
    $value = $_SESSION['_flash'][$key] ?? $default;
    // Hapus flash message setelah diambil
    unset($_SESSION['_flash'][$key]);
    return $value;
  }

  /**
   * Memeriksa apakah ada flash message yang tersedia untuk kunci tertentu.
   *
   * @param string $key Kunci flash message.
   * @return bool
   */
  public function hasFlash(string $key): bool
  {
    $this->ensureSessionStarted();
    return isset($_SESSION['_flash'][$key]);
  }
}
