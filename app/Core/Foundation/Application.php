<?php

namespace App\Core\Foundation;

// Impor dari sub-direktori Core lainnya
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Router;
use App\Core\View\View;
use App\Core\View\PageMeta;

// Impor Interface dan Exception
use App\Core\Interfaces\RenderableInterface;
use App\Exceptions\HttpException;

class Application
{
  private Router $router;
  private Request $request;
  private Response $response;
  private Container $container;
  private bool $isBooted = false;

  public function __construct()
  {
    // Karena Container, Kernel, dan Application berada di namespace yang sama (Foundation),
    // mereka bisa langsung dipanggil tanpa 'use'.
    $this->container = new Container();
    $this->request = new Request();
    $this->response = new Response($this->container);
    $this->router = new Router($this->container);

    $this->registerServices();

    // Daftarkan instance Request sebagai singleton di container
    $this->container->singleton(Request::class, fn() => $this->request);
  }

  public function getContainer(): Container
  {
    return $this->container;
  }

  public function getRouter(): Router
  {
    return $this->router;
  }

  public function boot(): void
  {
    if ($this->isBooted) {
      return;
    }

    $this->registerServices();
    $this->registerMiddleware();
    $this->registerRoutes();

    $this->isBooted = true;
  }

  public function run(): void
  {
    try {
      $this->boot();
      $result = $this->router->dispatch($this->request, $this->response);

      if ($result instanceof Response) {
        $response = $result;
      } elseif ($result instanceof RenderableInterface) {
        $response = $result->render($this->container);
      } else {
        throw new \LogicException('Controller harus mengembalikan instance dari Response atau RenderableInterface.');
      }
    } catch (HttpException $e) {
      try {
        /** @var \App\Services\ViewService $viewService */
        $viewService = $this->container->resolve(\App\Services\ViewService::class);

        $errorMeta = new PageMeta('Error ' . $e->getStatusCode());

        $errorViewPath = 'error';
        if (file_exists(__DIR__ . '/../../../addon/Views/error/index.php')) {
          $errorViewPath = 'error/index';
        }

        $errorView = new View(
          $this->container,
          $errorViewPath,
          ['code' => $e->getStatusCode(), 'message' => $e->getMessage()],
          $errorMeta
        );

        $html = $viewService->render($errorView);
        $response = new Response($this->container, $html, $e->getStatusCode());
      } catch (\Throwable $renderError) {
        $response = $this->renderFallbackError(500, 'Terjadi kesalahan kritis saat menampilkan halaman error.', $renderError);
      }
    } catch (RenderableInterface $e) {
      $response = $e->render($this->container);
    } catch (\Throwable $e) {
      if (env('APP_DEBUG') === 'true') dump($e);

      $code = 500;
      $message = 'Terjadi kesalahan internal pada server.';

      // Deteksi error tabel database tidak ditemukan
      if (strpos($e->getMessage(), 'Base table or view not found') !== false) {
        $message = 'Terjadi kesalahan Database: Tabel tidak ditemukan. Pastikan Anda sudah menjalankan migrasi database.';

        // Coba ambil nama tabel yang hilang untuk info lebih detail
        if (preg_match("/Table '(.+?)' doesn't exist/", $e->getMessage(), $matches)) {
          $message .= " (Tabel yang hilang: {$matches[1]})";
        }
      }

      try {
        /** @var \App\Services\ViewService $viewService */
        $viewService = $this->container->resolve(\App\Services\ViewService::class);

        $errorMeta = new PageMeta('Error ' . $code);

        $errorViewPath = 'error';
        if (file_exists(__DIR__ . '/../../../addon/Views/error/index.php')) {
          $errorViewPath = 'error/index';
        }

        $errorView = new View(
          $this->container,
          $errorViewPath,
          ['code' => $code, 'message' => $message],
          $errorMeta
        );

        $html = $viewService->render($errorView);
        $response = new Response($this->container, $html, $code);
      } catch (\Throwable $renderError) {
        $response = $this->renderFallbackError($code, $message, $e);
      }
    }

    $response->send();
  }

  private function registerServices(): void
  {
    // Path ke config disesuaikan karena posisi file Application sekarang lebih dalam 1 level
    $providers = require __DIR__ . '/../../config/providers.php';

    foreach ($providers as $providerClass) {
      if (class_exists($providerClass)) {
        $providerInstance = new $providerClass();
        $providerInstance->register($this->container);
      }
    }
  }

  private function registerMiddleware(): void
  {
    $kernel = new Kernel();

    foreach ($kernel->getRouteMiddleware() as $alias => $class) {
      $this->router->mapMiddleware($alias, $class);
    }
  }

  private function registerRoutes(): void
  {
    // Path ke cache disesuaikan (naik 2 level ke root project, lalu ke storage)
    $cachePath = __DIR__ . '/../../../storage/cache/routes.php';

    if (isProduction() && file_exists($cachePath)) {
      $this->router->setRoutes(require $cachePath);
      $this->router->get('build/assets/(.*)', [\App\Core\Controllers\AssetController::class, 'serve']);
      $this->router->get('build/js/(.*)', [\App\Core\Controllers\AssetController::class, 'serve']);

      return;
    }

    $router = $this->router;

    // Asset Handler
    $router->get('build/assets/(.*)', [\App\Core\Controllers\AssetController::class, 'serve']);
    $router->get('build/js/(.*)', [\App\Core\Controllers\AssetController::class, 'serve']);

    // Path ke rute addon disesuaikan
    require_once __DIR__ . '/../../../addon/Router/index.php';
  }

  private function renderFallbackError(int $code, string $message, ?\Throwable $e = null): Response
  {
    $debugInfo = '';
    if ($e && env('APP_DEBUG') === 'true') {
      $debugInfo = "
        <div style='margin-top: 20px; padding: 10px; background: #f1f1f1; border-radius: 5px; overflow-x: auto;'>
          <strong>Debug Info:</strong><br>
          File: {$e->getFile()}:{$e->getLine()}<br>
          Message: {$e->getMessage()}<br>
          <pre>" . $e->getTraceAsString() . "</pre>
        </div>";
    }

    $html = "
      <!DOCTYPE html>
      <html>
        <head>
          <title>Critical Error {$code}</title>
          <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f8f9fa; color: #333; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 800px; width: 90%; }
            h1 { color: #dc3545; margin-top: 0; }
            p { font-size: 1.1em; line-height: 1.6; }
            code { background: #f1f1f1; padding: 2px 5px; border-radius: 3px; color: #d63384; }
          </style>
        </head>
        <body>
          <div class='container'>
            <h1>Critical Error ({$code})</h1>
            <p>{$message}</p>
            {$debugInfo}
          </div>
        </body>
      </html>
    ";

    return new Response($this->container, $html, $code);
  }
}
