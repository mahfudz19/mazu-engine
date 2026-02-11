<?php

namespace App\Exceptions;

use App\Core\Foundation\Container;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Response;
use App\Core\Interfaces\RenderableInterface;

class AuthenticationException extends \Exception implements RenderableInterface
{
  /**
   * Membuat Response yang sesuai untuk exception ini.
   *
   * @param Container $container
   * @return Response
   */
  public function render(Container $container): Response
  {
    // Tugas exception ini sederhana: selalu redirect ke halaman login.
    return new RedirectResponse($container, 'login');
  }
}
