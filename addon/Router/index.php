<?php

use Addon\Controllers\ApiController;

$router->get('/', [ApiController::class, 'index']);
$router->get('/api/test', [ApiController::class, 'test']);
$router->get('/api/broadcast-agenda', [ApiController::class, 'broadcastAgenda']);
$router->get('/api/test-calendar', [ApiController::class, 'testBroadcast']);
