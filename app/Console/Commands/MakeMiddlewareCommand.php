<?php

namespace App\Console\Commands;

use App\Core\Foundation\Application;
use App\Console\Contracts\CommandInterface;

class MakeMiddlewareCommand implements CommandInterface
{
  public function __construct(
    private Application $app,
  ) {}

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

    $root = __DIR__ . '/../../..';
    $path = $root . "/addon/Middleware/{$name}.php";

    if (file_exists($path)) {
      echo color("Error: Middleware sudah ada!\n", "red");
      return 1;
    }

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

    $content = str_replace('{{CLASS_NAME}}', $name, $template);

    file_put_contents($path, $content);
    echo color("SUCCESS:", "green") . " Middleware dibuat di " . color($path, "blue") . "\n";

    return 0;
  }
}
