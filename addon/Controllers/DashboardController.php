<?php

namespace Addon\Controllers;

use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View\View;

class DashboardController
{
  public function index(Request $request, Response $response): View | RedirectResponse
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    if (empty($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
      return $response->redirect('/login');
    }

    return $response->renderPage([
      'user' => $_SESSION['user'] ?? [],
      'role' => $_SESSION['role'] ?? 'GUEST'
    ]);
  }
}
