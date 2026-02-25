<?php

namespace App\Console\Commands;

use App\Core\Foundation\Application;
use App\Console\Contracts\CommandInterface;

class MakeMiddlewareCommand implements CommandInterface
{
  public function __construct(private Application $app) {}

  public function getName(): string
  {
    return 'make:middleware';
  }

  public function getDescription(): string
  {
    return 'Membuat middleware baru di addon/Middleware';
  }

  public function handle(array $arguments): int
  {
    $name = $arguments[0] ?? null;
    if (!$name) {
      echo color("Error: Nama middleware harus diisi.\n", "red");
      return 1;
    }

    $name = ucfirst($name);

    if (!str_ends_with($name, 'Middleware')) {
      $name .= 'Middleware';
    }

    // Pastikan folder addon/Middleware ada
    $middlewareDir = __DIR__ . '/../../../addon/Middleware';
    if (!is_dir($middlewareDir)) {
      if (!mkdir($middlewareDir, 0755, true)) {
        echo color("Error: Tidak dapat membuat folder addon/Middleware\n", "red");
        return 1;
      }
    }

    $path = $middlewareDir . "/{$name}.php";

    if (file_exists($path)) {
      echo color("Error: Middleware sudah ada!\n", "red");
      return 1;
    }

    $isCsrfMiddleware = strtolower($name) === 'csrfmiddleware';
    $isThrottleMiddleware = strtolower($name) === 'throttlemiddleware';
    $isAuthMiddleware = strtolower($name) === 'authmiddleware';
    $isRoleMiddleware = strtolower($name) === 'rolemiddleware';
    $isGuestMiddleware = strtolower($name) === 'guestmiddleware';

    if ($isCsrfMiddleware) {
      $template = <<<'PHP'
<?php

namespace Addon\Middleware;

use App\Core\Foundation\Container;
use App\Core\Interfaces\MiddlewareInterface;
use App\Core\Http\Request;
use App\Services\SessionService;
use App\Exceptions\HttpException;
use App\Core\Http\JsonResponse;
use Closure;

/**
 * CsrfMiddleware
 *
 * Middleware cerdas (Hybrid) untuk memproteksi aplikasi dari serangan CSRF.
 * Otomatis melewati pemeriksaan untuk request API yang menggunakan token Authorization (Bearer).
 *
 * Cara pakai di router:
 *
 *   // Terapkan pada route POST/PUT/DELETE yang membutuhkan proteksi
 *   $router->post('login', [AuthController::class, 'login'], ['csrf']);
 *
 *   // Bisa juga diterapkan ke group:
 *   $router->group(['middleware' => ['csrf']], function ($router) {
 *     $router->post('change-password', [AuthController::class, 'changePassword']);
 *     $router->post('delete-account', [AuthController::class, 'deleteAccount']);
 *   });
 */
class {{CLASS_NAME}} implements MiddlewareInterface
{
  public function __construct(private Container $container) {}

  protected $except = [
    // Tambahkan URI yang dikecualikan dari proteksi CSRF di sini
    // '/api/*',
  ];

  public function handle($request, Closure $next, array $params = [])
  {
    if (
      $this->isReading($request) ||
      $this->inExceptArray($request) ||
      $this->isStatelessApi($request) ||
      $this->tokensMatch($request)
    ) {
      return $next($request);
    }

    if ($this->expectsJson($request)) {
      return new JsonResponse($this->container, [
        'status' => 'error',
        'message' => 'CSRF token mismatch. Silakan refresh halaman dan coba lagi.',
        'code' => 419
      ], 419);
    }

    throw new HttpException(419, 'CSRF token mismatch. Silakan refresh halaman dan coba lagi.');
  }

  protected function expectsJson(Request $request): bool
  {
    $accept = $request->server['HTTP_ACCEPT'] ?? '';
    $requestedWith = $request->server['HTTP_X_REQUESTED_WITH'] ?? '';

    return str_contains($accept, 'application/json') || $requestedWith === 'XMLHttpRequest';
  }

  protected function isReading(Request $request): bool
  {
    return in_array($request->getMethod(), ['HEAD', 'GET', 'OPTIONS']);
  }

  protected function isStatelessApi(Request $request): bool
  {
    if (isset($request->server['HTTP_AUTHORIZATION'])) {
      return true;
    }

    return false;
  }

  protected function inExceptArray(Request $request): bool
  {
    $currentPath = $request->getPath();
    foreach ($this->except as $except) {
      if ($except !== '/') {
        $except = trim($except, '/');
      }

      if ($this->matches($except, $currentPath)) {
        return true;
      }
    }
    return false;
  }

  protected function matches($pattern, $path)
  {
    $pattern = preg_quote($pattern, '#');
    $pattern = str_replace('\*', '.*', $pattern);
    return preg_match('#^' . $pattern . '\\z#u', $path) === 1;
  }

  protected function tokensMatch(Request $request): bool
  {
    $token = $this->getTokenFromRequest($request);
    $session = new SessionService();

    return $session->validateCsrfToken($token);
  }

  protected function getTokenFromRequest(Request $request): ?string
  {
    if (isset($request->body['_token'])) {
      return $request->body['_token'];
    }

    if (isset($request->server['HTTP_X_CSRF_TOKEN'])) {
      return $request->server['HTTP_X_CSRF_TOKEN'];
    }

    return null;
  }
}
PHP;
    } elseif ($isThrottleMiddleware) {
      $template = <<<'PHP'
<?php

namespace Addon\Middleware;

use App\Core\Foundation\Container;
use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Interfaces\MiddlewareInterface;
use App\Core\Interfaces\RateLimiterInterface;
use App\Exceptions\HttpException;
use Closure;

/**
 * ThrottleMiddleware
 *
 * Middleware cerdas (Hybrid) untuk membatasi jumlah request per identitas.
 *
 * Cara pakai di router:
 *
 *   // 60 request per 1 menit (default)
 *   $router->post('login', [AuthController::class, 'login'], ['throttle']);
 *
 *   // 5 request per 1 menit
 *   $router->post('login', [AuthController::class, 'login'], ['throttle:5,1']);
 *
 *   // Bisa juga diterapkan ke group:
 *   $router->group(['middleware' => ['throttle:100,1']], function ($router) {
 *     // semua route di sini akan di-throttle 100 request per menit
 *   });
 */
class {{CLASS_NAME}} implements MiddlewareInterface
{
  public function __construct(
    private Container $container,
    private RateLimiterInterface $limiter
  ) {}

  public function handle($request, Closure $next, array $params = [])
  {
    $maxAttempts = isset($params[0]) && is_numeric($params[0]) ? (int)$params[0] : 60;
    $decayMinutes = isset($params[1]) && is_numeric($params[1]) ? (int)$params[1] : 1;
    $decaySeconds = max(1, $decayMinutes * 60);

    $key = $this->resolveKey($request);

    if ($this->limiter->tooManyAttempts($key, $maxAttempts, $decaySeconds)) {
      $retryAfter = $this->limiter->availableIn($key, $decaySeconds);

      if ($this->isApiRequest($request)) {
        return new JsonResponse($this->container, [
          'status' => 'error',
          'message' => 'Too Many Requests',
          'retry_after' => $retryAfter,
        ], 429);
      }

      throw new HttpException(429, 'Too Many Requests. Coba lagi dalam ' . $retryAfter . ' detik.');
    }

    $this->limiter->hit($key, $decaySeconds);

    return $next($request);
  }

  protected function resolveKey(Request $request): string
  {
    $route = $request->getMatchedRoutePattern() ?? 'global';

    // Prioritas 1: API Token (stateless microservice)
    $token = $request->bearerToken();
    if ($token) {
      return 'throttle:route:' . $route . ':token:' . sha1($token);
    }

    // Fallback: IP (web/guest)
    $ip = $request->server['REMOTE_ADDR'] ?? 'unknown';

    return 'throttle:route:' . $route . ':ip:' . $ip;
  }

  protected function isApiRequest(Request $request): bool
  {
    // Dianggap API jika pakai Bearer Token atau minta JSON
    return $request->bearerToken() !== null || $request->wantsJson();
  }
}
PHP;
    } else {
      if ($isAuthMiddleware) {
        $template = <<<'PHP'
<?php

namespace Addon\Middleware;

use App\Core\Interfaces\MiddlewareInterface;
use App\Services\SessionService;
use App\Exceptions\AuthenticationException;
use Closure;

/**
 * AuthMiddleware
 *
 * Middleware standar untuk memastikan pengguna sudah login.
 *
 * Contoh penggunaan di router:
 *
 *   // Proteksi satu route
 *   $router->get('dashboard', [DashboardController::class, 'index'], ['auth']);
 *
 *   // Group dengan auth
 *   $router->group(['middleware' => ['auth']], function ($router) {
 *     $router->get('profile', [ProfileController::class, 'index']);
 *   });
 */
class {{CLASS_NAME}} implements MiddlewareInterface
{
  public function __construct(private SessionService $session) {}

  public function handle($request, Closure $next, array $params = [])
  {
    // Menggunakan key 'is_logged_in' sesuai standar proyek saat ini
    if ($this->session->get('is_logged_in') !== true) {
      throw new AuthenticationException('Unauthenticated');
    }

    return $next($request);
  }
}
PHP;
      } elseif ($isRoleMiddleware) {
        $template = <<<'PHP'
<?php

namespace Addon\Middleware;

use App\Core\Interfaces\MiddlewareInterface;
use App\Services\SessionService;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use Closure;

/**
 * RoleMiddleware
 *
 * Middleware standar untuk memeriksa role user.
 *
 * Contoh penggunaan di router:
 *
 *   // Hanya admin
 *   $router->get('admin/dashboard', [AdminController::class, 'index'], ['auth', 'role:admin']);
 *
 *   // Multi role
 *   $router->get('reports', [ReportController::class, 'index'], ['auth', 'role:admin,manager']);
 *
 *   // Group dengan auth + role
 *   $router->group(['middleware' => ['auth', 'role:admin']], function ($router) {
 *     // route khusus admin
 *   });
 */
class {{CLASS_NAME}} implements MiddlewareInterface
{
  public function __construct(private SessionService $session) {}

  public function handle($request, Closure $next, array $params = [])
  {
    if ($this->session->get('is_logged_in') !== true) {
      throw new AuthenticationException('Unauthenticated');
    }

    // Sesuaikan dengan struktur session user di aplikasi Anda
    // Default: $_SESSION['user'] = ['role' => 'admin', ...]
    // Atau jika disimpan langsung: $_SESSION['role'] = 'admin'
    
    $user = $this->session->get('user');
    $role = null;

    if (is_array($user)) {
        $role = $user['role'] ?? null;
    } else {
        // Fallback jika role disimpan terpisah
        $role = $this->session->get('role');
    }

    if (!empty($params)) {
      if (!$role || !in_array($role, $params, true)) {
        throw new AuthorizationException('Forbidden');
      }
    }

    return $next($request);
  }
}
PHP;
      } elseif ($isGuestMiddleware) {
        $template = <<<'PHP'
<?php

namespace Addon\Middleware;

use App\Core\Interfaces\MiddlewareInterface;
use App\Services\SessionService;
use App\Exceptions\AuthorizationException;
use Closure;

/**
 * GuestMiddleware
 *
 * Middleware standar untuk memastikan pengguna BELUM login.
 * Biasanya digunakan pada halaman login/register agar user yang sudah login diredirect.
 *
 * Contoh penggunaan di router:
 *
 *   $router->get('login', [AuthController::class, 'login'], ['guest']);
 *   $router->get('register', [AuthController::class, 'register'], ['guest']);
 */
class {{CLASS_NAME}} implements MiddlewareInterface
{
  public function __construct(private SessionService $session) {}

  public function handle($request, Closure $next, array $params = [])
  {
    // Cek key session yang sama dengan AuthMiddleware
    if ($this->session->get('is_logged_in') === true) {
       // Lempar exception yang akan ditangkap oleh Handler untuk redirect ke dashboard/home
       // Pesan 'RedirectIfAuthenticated' adalah sinyal khusus untuk Exception Handler
       throw new AuthorizationException('RedirectIfAuthenticated');
    }

    return $next($request);
  }
}
PHP;
      } else {
        $template = <<<'PHP'
<?php

namespace Addon\Middleware;

use App\Core\Interfaces\MiddlewareInterface;
use App\Core\Http\Request;

class {{CLASS_NAME}} implements MiddlewareInterface
{
  public function handle($request, \Closure $next, array $params = [])
  {
    // Logika middleware di sini
    
    return $next($request);
  }
}
PHP;
      }
    }

    $content = str_replace('{{CLASS_NAME}}', $name, $template);

    // Pastikan folder ada sebelum file_put_contents
    $dir = dirname($path);
    if (!is_dir($dir)) {
      if (!mkdir($dir, 0755, true)) {
        echo color("Error: Tidak dapat membuat folder untuk middleware\n", "red");
        return 1;
      }
    }

    if (file_put_contents($path, $content) === false) {
      echo color("Error: Gagal membuat file middleware\n", "red");
      return 1;
    }

    echo color("SUCCESS:", "green") . " Middleware dibuat di " . color($path, "blue") . "\n";

    return 0;
  }
}
