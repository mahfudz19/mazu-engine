<?php

namespace App\Core\Routing;

use App\Core\Foundation\Container;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Interfaces\RenderableInterface;
use App\Exceptions\HttpException;

class Router
{
  private $routes = [];
  private array $middlewareMap = [];
  private Container $container;
  private array $groupStack = [];

  // Terima Container melalui constructor
  public function __construct(Container $container)
  {
    $this->container = $container;
  }

  // Metode baru untuk mendaftarkan alias middleware
  public function mapMiddleware(string $alias, string $class): void
  {
    $this->middlewareMap[$alias] = $class;
  }

  public function getRoutes(): array
  {
    return $this->routes;
  }

  public function setRoutes(array $routes): void
  {
    $this->routes = $routes;
  }

  // Ubah semua metode pendaftaran untuk menerima array $middlewares
  public function get(string $uri, $handler, array $middlewares = [])
  {
    $this->register('GET', $uri, $handler, $middlewares);
  }
  public function post(string $uri, $handler, array $middlewares = [])
  {
    $this->register('POST', $uri, $handler, $middlewares);
  }
  public function put(string $uri, $handler, array $middlewares = [])
  {
    $this->register('PUT', $uri, $handler, $middlewares);
  }
  public function delete(string $uri, $handler, array $middlewares = [])
  {
    $this->register('DELETE', $uri, $handler, $middlewares);
  }
  public function patch(string $uri, $handler, array $middlewares = [])
  {
    $this->register('PATCH', $uri, $handler, $middlewares);
  }
  public function options(string $uri, $handler, array $middlewares = [])
  {
    $this->register('OPTIONS', $uri, $handler, $middlewares);
  }
  // any method
  public function any(string $uri, $handler, array $middlewares = [])
  {
    $this->register('*', $uri, $handler, $middlewares);
  }

  public function group(array $attributes, \Closure $callback)
  {
    // Ambil atribut dari grup induk (jika ada)
    $parentAttributes = end($this->groupStack);

    if ($parentAttributes) {
      // Gabungkan prefix secara robust
      $parentPrefix = $parentAttributes['prefix'] ?? '';
      $childPrefix = $attributes['prefix'] ?? '';
      $attributes['prefix'] = trim(implode('/', [trim($parentPrefix, '/'), trim($childPrefix, '/')]), '/');

      // Gabungkan middleware
      $parentMiddleware = $parentAttributes['middleware'] ?? [];
      $childMiddleware = $attributes['middleware'] ?? [];
      $attributes['middleware'] = array_merge($parentMiddleware, $childMiddleware);
    }

    // Dorong atribut grup yang sudah digabung ke stack
    $this->groupStack[] = $attributes;

    // Panggil callback, yang akan mendaftarkan rute di dalam grup
    $callback($this);

    // Hapus atribut grup saat ini dari stack setelah selesai
    array_pop($this->groupStack);
  }

  // internal register
  private function register(string $method, string $uri, $handler, array $middlewares = [])
  {
    $method = strtoupper($method);

    // Terapkan atribut grup jika ada
    $groupAttributes = end($this->groupStack);
    if ($groupAttributes) {
      // Terapkan prefix secara robust
      $prefix = $groupAttributes['prefix'] ?? '';
      $uri = trim(implode('/', [trim($prefix, '/'), trim($uri, '/')]), '/');

      // Terapkan middleware grup
      $groupMiddlewares = $groupAttributes['middleware'] ?? [];
      $middlewares = array_merge($groupMiddlewares, $middlewares);
    }

    $this->routes[$method][trim($uri, '/')] = [
      'handler'      => $handler,
      'middlewares'  => $middlewares,
    ];
  }

  // dispatch sekarang menerima Request dan mengembalikan "blueprint" (bukan hanya Response)
  public function dispatch(Request $request, Response $response): object
  {
    $method = strtoupper($request->getMethod());

    // Logika untuk menangani subdirectory
    $rawPath = $request->getPath();
    $subdirectory = '/' . trim((string)(getSubdirectory() ?? ''), '/');
    if ($subdirectory !== '/' && str_starts_with($rawPath, $subdirectory)) {
      $rawPath = substr($rawPath, strlen($subdirectory));
    }
    $uri = trim($rawPath, '/');

    $route = $this->findRoute($method, $uri) ?? $this->findRoute('*', $uri);

    if (!$route) {
      throw new HttpException(404, "Halaman yang anda minta tidak ditemukan.");
    }

    $request->setMatchedRoutePattern($route['pattern']);

    return $this->handleRoute($route['route'], $route['params'], $request, $response);
  }

  // find route by method + uri (supports :param)
  private function findRoute(string $method, string $uri)
  {
    if (empty($this->routes[$method])) {
      return null;
    }

    // exact match first
    if (isset($this->routes[$method][$uri])) {
      return ['route' => $this->routes[$method][$uri], 'params' => [], 'pattern' => $uri];
    }

    // dynamic route match
    foreach ($this->routes[$method] as $routePattern => $route) {
      $paramNames = [];
      $regex = preg_replace_callback(
        '/:([A-Za-z0-9_]+)/',
        function ($m) use (&$paramNames) {
          $paramNames[] = $m[1];
          return '([^\/]+)';
        },
        $routePattern
      );
      $regex = '#^' . $regex . '$#';
      if (preg_match($regex, $uri, $matches)) {
        array_shift($matches);
        $params = [];
        foreach ($paramNames as $i => $name) {
          $params[$name] = isset($matches[$i]) ? urldecode($matches[$i]) : null;
        }
        return ['route' => $route, 'params' => $params, 'pattern' => $routePattern];
      }
    }

    return null;
  }

  // handleRoute sekarang akan mengembalikan object apa pun yang diberikan controller
  private function handleRoute($route, array $params, Request $request, Response $response): object
  {
    // Atur parameter rute pada objek Request
    $request->setRouteParams($params);

    $middlewares = $route['middlewares'] ?? [];

    // Buat pipeline middleware
    $pipeline = array_reduce(
      array_reverse($middlewares),
      function ($next, $middlewareAlias) {
        return function ($request) use ($next, $middlewareAlias) {
          $parts = explode(':', $middlewareAlias, 2);
          $aliasKey = $parts[0];
          $paramsMiddleware = isset($parts[1]) ? explode(',', $parts[1]) : [];

          $class = $this->middlewareMap[$aliasKey] ?? null;
          if (!$class || !class_exists($class)) {
            throw new HttpException(500, "Middleware '{$aliasKey}' tidak terdaftar.");
          }

          $middlewareInstance = $this->container->resolve($class);
          return $middlewareInstance->handle($request, $next, $paramsMiddleware);
        };
      },
      function ($request) use ($route, $response) {
        return $this->runHandler($route['handler'], $request, $response);
      }
    );

    return $pipeline($request);
  }

  private function runHandler($handler, Request $request, Response $response)
  {
    // Kondisi 1: Handler adalah [Controller::class, 'method']
    if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1])) {
      $controllerClass = $handler[0];
      $method = $handler[1];

      if (class_exists($controllerClass) && method_exists($controllerClass, $method)) {
        $controllerObj = $this->container->resolve($controllerClass);
        return $controllerObj->$method($request, $response);
      }
    }

    // Kondisi 2: Handler adalah sebuah Closure (fungsi anonim)
    elseif ($handler instanceof \Closure) {
      $reflection = new \ReflectionFunction($handler);
      $args = [];
      foreach ($reflection->getParameters() as $param) {
        $type = $param->getType();
        if ($type && !$type->isBuiltin()) {
          $args[] = $this->container->resolve($type->getName());
        }
      }
      $result = call_user_func_array($handler, $args);

      if (is_string($result)) {
        return new Response($this->container, $result);
      }
      if ($result instanceof Response || $result instanceof RenderableInterface) {
        return $result;
      }
      return new Response($this->container, '');
    }

    throw new HttpException(500, "Route handler tidak dikonfigurasi dengan benar.");
  }
}
