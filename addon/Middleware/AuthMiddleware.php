<?php

namespace Addon\Middleware;

use App\Core\Interfaces\MiddlewareInterface;
use App\Exceptions\AuthenticationException;
use App\Services\SessionService;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private SessionService $session) {}

    public function handle($request, \Closure $next, array $params = [])
    {
        if ($this->session->get('is_logged_in') !== true) {
            $e = new AuthenticationException('Unauthenticated');
            $e->hardRedirect();
            throw $e;
        }

        return $next($request);
    }
}