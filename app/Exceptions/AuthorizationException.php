<?php

namespace App\Exceptions;

use App\Core\Foundation\Container;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Response;
use App\Core\Interfaces\RenderableInterface;
use App\Core\View\PageMeta;
use App\Core\View\View;
use App\Services\ViewService;

class AuthorizationException extends \Exception implements RenderableInterface
{
  /**
   * Membuat Response yang sesuai untuk exception ini.
   *
   * @param Container $container
   * @return Response
   */
  public function render(Container $container): Response
  {
    // Kasus 1: User sudah login dan mencoba mengakses halaman 'guest'.
    if ($this->getMessage() === 'RedirectIfAuthenticated') {
      return new RedirectResponse($container, '');
    }

    // Kasus 2: User tidak memiliki role yang tepat.
    // Kita butuh ViewService untuk merender halaman error.
    /** @var ViewService $viewService */
    $viewService = $container->resolve(ViewService::class);

    $errorMeta = new PageMeta('Error 403');
    $errorView = new View(
      $container,
      'error',
      [
        'code' => 403,
        'message' => $this->getMessage() ?: 'Forbidden. Anda tidak memiliki izin untuk mengakses halaman ini.'
      ],
      $errorMeta
    );

    $content = $viewService->render($errorView);

    return new Response($container, $content, 403);
  }
}
