<?php

namespace Addon\Middleware;

use App\Core\Interfaces\MiddlewareInterface;
use App\Exceptions\AuthorizationException;
use App\Services\SessionService;

class RoleMiddleware implements MiddlewareInterface
{
    public function __construct(private SessionService $session) {}

    public function handle($request, \Closure $next, array $params = [])
    {
        $allowedRoles = $params;
        $userRole = $this->session->get('role');

        if (!$userRole || !in_array($userRole, $allowedRoles)) {
            $e = new AuthorizationException("Forbidden. Anda tidak memiliki izin untuk mengakses halaman ini.");
            $e->hardRedirect();
            throw $e;
        }

        return $next($request);
    }
}