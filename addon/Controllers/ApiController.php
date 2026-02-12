<?php

namespace Addon\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;

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

  public function broadcastAgenda(Request $request, Response $response)
  {
    // Mazu menggunakan input() untuk mengambil data body (JSON/POST)
    $agenda = $request->input();
    $targetEmail = $agenda['target_email'] ?? null;

    // if (!$targetEmail) {
    //   return $response->json(['error' => 'Target email is required'], 400);
    // }

    try {
      $gcal = new \Addon\Services\GoogleCalendarService();
      // Impersonate user dan masukkan agenda
      $result = $gcal->impersonate($targetEmail)->insertEvent($agenda);

      return $response->json([
        'status'  => 'success',
        'event_id' => $result->getId()
      ]);
    } catch (\Exception $e) {
      return $response->json([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
      ], 500);
    }
  }
}
