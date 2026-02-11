<?php

namespace App\Core\Foundation;

/**
 * Base class untuk semua Service Providers di aplikasi.
 * Setiap Service Provider harus meng-extend class ini.
 */
abstract class ServiceProvider
{
  /**
   * Register any application services.
   *
   * @param Container $container
   * @return void
   */
  abstract public function register(Container $container): void;

  /**
   * Boot any application services.
   * (Opsional: Method ini bisa ditambahkan jika anda membutuhkan logika yang berjalan
   * setelah semua provider telah diregister, misalnya untuk mendaftarkan event listener).
   *
   * @param \App\Core\Container $container
   * @return void
   */
  // public function boot(Container $container): void
  // {
  //     // Default implementation does nothing
  // }
}
