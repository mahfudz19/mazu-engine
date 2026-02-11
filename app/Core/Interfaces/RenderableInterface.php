<?php

namespace App\Core\Interfaces;

use App\Core\Foundation\Container;
use App\Core\Http\Response;

interface RenderableInterface
{
  /**
   * Merender objek ini menjadi sebuah Response.
   *
   * @param Container $container
   * @return Response
   */
  public function render(Container $container): Response;
}
