<?php

namespace Addon\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View\View;

class ApiController
{
  public function index(Request $request, Response $response)
  {
    return $response->setContent('Rest API Connection Success!');
  }
  public function test(Request $request, Response $response): JsonResponse
  {
    return $response->json([
      'message' => 'Hello World!',
    ]);
  }
}
