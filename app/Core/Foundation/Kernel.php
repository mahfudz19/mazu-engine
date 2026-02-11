<?php

namespace App\Core\Foundation;

use App\Core\Interfaces\MiddlewareInterface;
use App\Exceptions\HttpException;

class Kernel
{
  /**
   * Cache untuk middleware yang sudah diload agar tidak scan berulang kali.
   */
  private static array $middlewareCache = [];

  /**
   * Mengembalikan daftar alias middleware rute dengan memindai folder addon.
   *
   * @return array<string, string>
   */
  public function getRouteMiddleware(): array
  {
    if (!empty(self::$middlewareCache)) {
      return self::$middlewareCache;
    }

    $middlewareDir = __DIR__ . '/../../../addon/Middleware';
    $middlewares = [];

    if (!is_dir($middlewareDir)) {
      return [];
    }

    $files = scandir($middlewareDir);
    foreach ($files as $file) {
      if ($file === '.' || $file === '..') continue;
      if (!str_ends_with($file, '.php')) continue;

      $className = pathinfo($file, PATHINFO_FILENAME);
      $fullClassName = "Addon\\Middleware\\{$className}";

      // Validasi: Apakah class ada dan implement interface yang benar?
      // Kita perlu include file dulu jika autoload belum jalan untuk file baru
      // Tapi sebaiknya kita andalkan autoloader (setelah dump-autoload).
      // Untuk keamanan saat development, kita cek existence.

      if (class_exists($fullClassName)) {
        if (!is_subclass_of($fullClassName, MiddlewareInterface::class)) {
          throw new HttpException(500, "Class '{$fullClassName}' di folder Middleware harus mengimplementasikan App\Core\Interfaces\MiddlewareInterface.");
        }

        // Generate Alias:
        // AuthMiddleware -> auth
        // GuestMiddleware -> guest
        // RoleMiddleware -> role
        // SuperAdminMiddleware -> super_admin (opsional, tapi lowercase dulu cukup)

        $alias = strtolower(str_replace('Middleware', '', $className));
        $middlewares[$alias] = $fullClassName;
      }
    }

    self::$middlewareCache = $middlewares;
    return $middlewares;
  }
}
