<?php

use Addon\Controllers\ApiController;
use Addon\Controllers\AuthController;
use Addon\Controllers\DashboardController;

$router->get('/', [ApiController::class, 'index']);
$router->get('/login', [AuthController::class, 'login']);
$router->get('/auth/callback', [AuthController::class, 'callback']);
$router->get('/logout', [AuthController::class, 'logout']);
// Dashboard
$router->get('/dashboard', [DashboardController::class, 'index']);

$router->get('/api/test', [ApiController::class, 'test']);
$router->get('/api/broadcast-agenda', [ApiController::class, 'broadcastAgenda']);
$router->get('/api/test-calendar', [ApiController::class, 'testBroadcast']);
$router->get('/api/test-directory', [ApiController::class, 'testDirectory']);
$router->get('/api/test-schedule', [ApiController::class, 'checkSchedule']);
