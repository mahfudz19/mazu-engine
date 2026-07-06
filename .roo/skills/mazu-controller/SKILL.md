---
name: mazu-controller
description: Mazu Framework - Controller Patterns (Routing, Handler, Transactions)
---

# Mazu Framework - Controller Patterns

## 🎯 Routing (`addon/Router/index.php`)

### Format Route Dasar

```php
$router->get('/', fn(Request $r, Response $res) => $res->renderPage([]));
$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store'], ['auth', 'csrf']);
$router->group(['prefix' => 'api', 'middleware' => ['auth']], function($router) {
    $router->get('users', [ApiController::class, 'index']);
});
```

### Route dengan Parameter Dinamis

```php
// Gunakan format :param untuk parameter dinamis
$router->get('/users/:id', [UserController::class, 'show']);
$router->get('/users/:id/edit', [UserController::class, 'edit']);
$router->post('/users/:id', [UserController::class, 'update']);

// Multiple parameters
$router->get('/users/:userId/posts/:postId', [PostController::class, 'show']);
```

**Penting:**

- Gunakan format `:param` untuk parameter dinamis (contoh: `:id`, `:userId`)
- **BUKAN** format `{id}` atau `{param}`
- Parameter diambil di controller menggunakan `$request->param('paramName')`

## 👨‍💼 Controller Structure

### Struktur Dasar

```php
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View\View;

class UserController {
    public function index(Request $request, Response $response): View | RedirectResponse {
        return $response->renderPage(['data' => $data], ['meta' => ['title' => 'Page']]);
    }
}
```

### Constructor Property Promotion

```php
use Addon\Models\UserModel;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\RedirectResponse;
use App\Core\View\View;

class UserController {
    public function __construct(
        private UserModel $userModel
    ) {}

    public function index(Request $request, Response $response): View | RedirectResponse {
        return $response->renderPage(['users' => $this->userModel->all()], ['meta' => ['title' => 'Page']]);
    }
}
```

### Mengambil Route Parameter

```php
public function show(Request $request, Response $response): View | RedirectResponse {
    // Gunakan $request->param() untuk mengambil parameter dinamis dari route
    $id = $request->param('id');
    $user = $this->userModel->find($id);

    if (!$user) {
        // Redirect dengan query string untuk error
        return $response->redirect('/users?error=404&message=' . urlencode('User tidak ditemukan'));
    }

    return $response->renderPage(['user' => $user], ['meta' => ['title' => 'Page']]);
}
```

### Transaction dengan Model

```php
public function store(Request $request, Response $response): View | RedirectResponse {
    try {
        $data = $request->input();

        // Validasi
        if (empty($data['name'])) {
            return $response->redirect('/users/create?error=400&message=' . urlencode('Nama wajib diisi'));
        }

        // Mulai transaksi dari model manapun
        $db = $this->userModel->getDb();
        $db->beginTransaction();

        try {
            $this->userModel->create($data);
            $this->profileModel->create([...]);

            $db->commit();
            return $response->redirect('/users');
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    } catch (\Exception $e) {
        return $response->redirect('/users/create?error=500&message=' . urlencode($e->getMessage()));
    }
}
```

## ✅ Controller Best Practices

| Rule                        | Description                                                     |
| --------------------------- | --------------------------------------------------------------- | ---------------- | ------------- |
| **2 Parameters Only**       | `(Request $request, Response $response)`                        |
| **Use `$request->param()`** | Ambil route params, bukan sebagai function param                |
| **Use `$request->input()`** | Get all input data (BUKAN `all()`)                              |
| **Return Types**            | `View                                                           | RedirectResponse | JsonResponse` |
| **Error Redirect**          | `$response->redirect('/path?error=<code>&message=<pesan>')`     |
| **Dependency Injection**    | Constructor property promotion                                  |
| **Try-Catch Required**      | Setiap method WAJIB error handling                              |
| **Transactions**            | `$model->getDb()->beginTransaction()`, `commit()`, `rollBack()` |

## 📚 Related Skills

- [`mazu-core`](../mazu-core/SKILL.md) - Core reference & CLI
- [`mazu-model`](../mazu-model/SKILL.md) - Model & database patterns
- [`mazu-middleware`](../mazu-middleware/SKILL.md) - Middleware for routes
