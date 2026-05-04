<?php

use App\Core\Http\Request;
use App\Core\Http\Response;

/** @var \App\Core\Routing\Router $router */

// Home route
$router->get('/', function (Request $request, Response $response) {
    return $response->renderPage();
});
