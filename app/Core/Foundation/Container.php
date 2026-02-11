<?php

namespace App\Core\Foundation;

class Container
{
  protected array $bindings = [];
  protected array $instances = [];

  /**
   * Daftarkan cara membuat sebuah service/class.
   * @param string $key   Nama class atau alias
   * @param \Closure $resolver Fungsi yang akan membuat instance class
   */
  public function bind(string $key, \Closure $resolver): void
  {
    $this->bindings[$key] = $resolver;
  }

  /**
   * Daftarkan service sebagai singleton.
   * @param string $key Nama class atau alias
   * @param \Closure $resolver Fungsi yang akan membuat instance class
   */
  public function singleton(string $key, \Closure $resolver): void
  {
    // Bungkus resolver untuk menangani logika singleton
    $this->bind($key, function () use ($key, $resolver) {
      if (!isset($this->instances[$key])) {
        // Panggil resolver asli untuk membuat instance
        $this->instances[$key] = $resolver();
      }
      // Kembalikan instance yang sudah disimpan pada pemanggilan berikutnya
      return $this->instances[$key];
    });
  }

  /**
   * Dapatkan instance dari sebuah service, atau buat secara otomatis.
   * @param string $key Nama class atau alias
   * @return object
   * @throws \Exception Jika class tidak ada atau dependensi tidak bisa di-resolve
   */
  public function resolve(string $key): object
  {
    // 1. Jika ada binding manual (seperti Database singleton), gunakan itu.
    if (isset($this->bindings[$key])) {
      $resolver = $this->bindings[$key];
      return $resolver();
    }

    // 2. Jika tidak ada, coba buat secara otomatis (Auto-Wiring).
    if (!class_exists($key)) {
      throw new \Exception("Class '{$key}' tidak ditemukan.");
    }

    $reflectionClass = new \ReflectionClass($key);
    $constructor = $reflectionClass->getConstructor();

    // 3. Jika tidak ada constructor, buat saja instance baru.
    if (!$constructor) {
      return new $key();
    }

    // 4. Jika ada constructor, periksa dependensinya.
    $parameters = $constructor->getParameters();
    $dependencies = [];

    foreach ($parameters as $param) {
      $type = $param->getType();
      if (!$type || $type->isBuiltin() || !($type instanceof \ReflectionNamedType)) {
        throw new \Exception("Tidak bisa me-resolve dependensi '{$param->getName()}' di constructor {$key}.");
      }
      // Secara rekursif, resolve setiap dependensi yang dibutuhkan.
      $dependencies[] = $this->resolve($type->getName());
    }

    // 5. Buat instance baru dengan semua dependensi yang sudah di-resolve.
    return $reflectionClass->newInstanceArgs($dependencies);
  }
}
