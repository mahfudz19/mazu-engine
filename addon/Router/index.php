<?php

use Addon\Controllers\ApiController;

$router->get('/', [ApiController::class, 'index']);
$router->get('/api/test', [ApiController::class, 'test']);
