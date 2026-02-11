<?php

namespace App\Console\Commands;

use App\Core\Foundation\Application;
use App\Console\Contracts\CommandInterface;

class MakeControllerCommand implements CommandInterface
{
  public function __construct(
    private Application $app,
  ) {}

  public function getName(): string
  {
    return 'make:controller';
  }

  public function getDescription(): string
  {
    return 'Membuat controller baru di addon/Controllers';
  }

  public function handle(array $arguments): int
  {
    $name = $arguments[0] ?? null;
    if (!$name) {
      echo color("Error: Nama controller harus diisi.\n", "red");
      return 1;
    }

    $name = ucfirst($name);

    if (!str_ends_with($name, 'Controller')) {
      $name .= 'Controller';
    }

    $path = __DIR__ . '/../../..' . "/addon/Controllers/{$name}.php";

    if (file_exists($path)) {
      echo color("Error: Controller sudah ada!\n", "red");
      return 1;
    }

    $baseName = substr($name, 0, -10); // Remove 'Controller' suffix
    $modelName = $baseName . 'Model';
    $modelPath = __DIR__ . '/../../..' . "/addon/Models/{$modelName}.php";

    if (file_exists($modelPath)) {
      $template = <<<'PHP'
<?php

namespace Addon\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View\View;
use App\Core\Http\RedirectResponse;
use Addon\Models\{{MODEL_NAME}};

class {{CLASS_NAME}}
{
  private {{MODEL_NAME}} $model;

  public function __construct({{MODEL_NAME}} $model)
  {
    $this->model = $model;
  }

  public function index(Request $request, Response $response): View
  {
    $items = $this->model->all();

    return $response->renderPage([
      'items' => $items,
    ]);
  }

  public function create(Request $request, Response $response): View
  {
    return $response->renderPage([]);
  }

  public function store(Request $request, Response $response): RedirectResponse
  {
    $data = $request->getBody();
    $this->model->create($data);

    return $response->redirect('/');
  }

  public function edit(Request $request, Response $response): View
  {
    $id = $request->param('id');
    $item = $this->model->find($id);

    return $response->renderPage([
      'item' => $item,
    ]);
  }

  public function update(Request $request, Response $response): RedirectResponse
  {
    $id = $request->param('id');
    $data = $request->getBody();
    $this->model->updateById($id, $data);

    return $response->redirect('/');
  }

  public function destroy(Request $request, Response $response): RedirectResponse
  {
    $id = $request->param('id');
    $this->model->deleteById($id);

    return $response->redirect('/');
  }
}

PHP;

      $content = str_replace(
        ['{{CLASS_NAME}}', '{{MODEL_NAME}}'],
        [$name, $modelName],
        $template
      );
    } else {
      $template = <<<'PHP'
<?php

namespace Addon\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View\View;

class {{CLASS_NAME}}
{
  public function index(Request $request, Response $response): View
  {
    return $response->renderPage([]);
  }
}

PHP;

      $content = str_replace(
        '{{CLASS_NAME}}',
        $name,
        $template
      );
    }

    file_put_contents($path, $content);
    echo color("SUCCESS:", "green") . " Controller dibuat di " . color($path, "blue") . "\n";

    return 0;
  }
}
