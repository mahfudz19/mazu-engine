---
name: mazu-fullstack-developer
description: Mazu Framework - Fullstack Developer Skill (Ringkas)
---

# Mazu Framework - Quick Reference

## 📁 Struktur Folder

```
project-root/
├── app/           # Core Engine - JANGAN MODIFIKASI
│   ├── Console/   # CLI Commands (php mazu)
│   └── Core/      # Framework Core
├── addon/         # Application Code - BOLEH DIMODIFIKASI
│   ├── Controllers/
│   ├── Middleware/
│   ├── Models/
│   ├── Providers/
│   ├── Router/index.php   # Route definitions
│   └── Views/             # View templates (nested layout)
└── config/
```

## 🛠️ CLI Commands - Gunakan Ini!

**Untuk membuat file baru, JANGAN manual - gunakan CLI:**

```bash
php mazu make:controller User    # Buat UserController di addon/Controllers/
php mazu make:model User         # Buat UserModel di addon/Models/
php mazu make:middleware Auth    # Buat AuthMiddleware di addon/Middleware/
php mazu make:job SendEmail      # Buat SendEmailJob
php mazu migrate                 # Jalankan migration
php mazu build                   # Build assets
php mazu serve                   # Dev server
```

**Template sudah ada di:** `app/Console/Commands/Make*.php`

## 🎯 Konsep Dasar

### 1. Routing (`addon/Router/index.php`)

```php
$router->get('/', fn(Request $r, Response $res) => $res->renderPage([]));
$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store'], ['auth', 'csrf']);
$router->group(['prefix' => 'api', 'middleware' => ['auth']], function($router) {
    $router->get('users', [ApiController::class, 'index']);
});
```

### 2. Controller (gunakan `php mazu make:controller`)

```php
class UserController {
    public function index(Request $r, Response $res): View {
        return $res->renderPage(['data' => $data]);
    }
}
```

### 3. Model (gunakan `php mazu make:model`)

```php
class UserModel extends Model {
    protected string $table = 'users';
    protected array $schema = [...];
    public function findByEmail(string $email): ?array { ... }
}
```

### 4. Middleware (gunakan `php mazu make:middleware`)

```php
class AuthMiddleware implements MiddlewareInterface {
    public function handle($request, Closure $next, array $params = []) {
        if (!isset($_SESSION['user_id'])) throw new AuthenticationException();
        return $next($request);
    }
}
```

### 5. Nested Layout (Next.js style)

```
addon/Views/
├── layout.php         # Root layout
├── (app)/layout.php   # Group layout
└── (app)/dashboard/index.php
```

## 🔧 Helper Functions

```php
env('APP_NAME')           // Environment variable
getBaseUrl('/users')      // Base URL dengan subdirectory
asset('css/style.css')    // Versioned asset
csrf_token(), csrf_field() // CSRF helpers
e($value)                 // Escape HTML
dump($var)                // Debug
```

## 📦 Response & Request

```php
// Response
$response->renderPage(['data' => $data], ['meta' => ['title' => 'Page']]);
$response->json(['status' => 'success']);
$response->redirect('/users');

// Request
$request->input('name');      // Input
$request->getBody();           // POST/JSON body
$request->param('id');         // Route param
$request->get('search');       // Query param
$request->header('Accept');    // Header
```

## ⚠️ Important Rules

1. **JANGAN MODIFIKASI** folder `app/` - ini core engine
2. **GUNAKAN CLI** untuk membuat controller/model/middleware
3. Model harus extend `App\Core\Database\Model`
4. Middleware harus implement `MiddlewareInterface`
5. Controller return `View` via `$response->renderPage()`
6. CSS/JS auto-discovered (e.g., `index.php` → `index.css`)

## 📚 Source of Truth

- **CLI Templates:** `app/Console/Commands/Make*.php`
- **Core Classes:** `app/Core/Http/Request.php`, `app/Core/Http/Response.php`
- **Base Model:** `app/Core/Database/Model.php`
- **View Engine:** `app/Services/ViewService.php`
