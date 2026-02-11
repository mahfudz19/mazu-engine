<?php

namespace App\Core\Http;

use App\Core\Foundation\Container;

class JsonResponse extends Response
{
  public function __construct(?Container $container, mixed $data, int $statusCode = 200)
  {
    $content = json_encode($data);
    $headers = [
      'Content-Type' => 'application/json',
      'Cache-Control' => 'no-cache',
    ];

    // Teruskan container ke parent constructor
    parent::__construct($container, $content, $statusCode, $headers);
  }
}
