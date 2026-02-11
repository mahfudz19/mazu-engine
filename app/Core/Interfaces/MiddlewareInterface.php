<?php

namespace App\Core\Interfaces;

interface MiddlewareInterface
{
  /**
   * Menjalankan logika middleware.
   *
   * @param \App\Core\Http\Request $request
   * @param \Closure $next
   * @param array $params Parameter dari rute (misal: ['admin', 'mahasiswa'])
   * @return mixed
   */
  public function handle($request, \Closure $next, array $params = []);
}