---
name: mazu-middleware
description: Mazu Framework - Middleware Patterns (Auth, Role, CSRF)
---

# Mazu Framework - Middleware Patterns

## 🏗️ Creating Middleware

**Gunakan CLI - JANGAN manual!**

```bash
php mazu make:middleware Auth    # Buat AuthMiddleware
php mazu make:middleware Role    # Buat RoleMiddleware
php mazu make:middleware Csrf    # Buat CsrfMiddleware
```

## 📐 Basic Structure

```php
class AuthMiddleware implements MiddlewareInterface {
    public function handle($request, \Closure $next, array $params = []) {
        // Cek authorization
        if (!$authorized) {
            throw new AuthorizationException('Forbidden');
        }
        return $next($request);
    }
}
```

## 🔐 Middleware dengan Dependency Injection

```php
class RoleMiddleware implements MiddlewareInterface {
    public function __construct(private SessionService $session) {}

    public function handle($request, \Closure $next, array $params = []) {
        // $params berisi array dari route: 'role:admin,user'
        $allowedRoles = $params;
        $userRole = $this->session->get('auth.user_role');

        if (!in_array($userRole, $allowedRoles)) {
            throw new AuthorizationException('Forbidden');
        }

        return $next($request);
    }
}
```

## ⚙️ Cara Kerja Middleware

1. **Auto-Discovery:** Middleware di-scan otomatis dari `addon/Middleware/` oleh `Kernel::getRouteMiddleware()`
2. **Alias Mapping:** `AuthMiddleware` → `auth`, `RoleMiddleware` → `role` (lowercase, tanpa suffix "Middleware")
3. **Pipeline:** Middleware dijalankan dalam pipeline (`array_reverse`) sebelum controller
4. **Parameter Parsing:** `role:admin,user` → alias=`role`, params=`['admin', 'user']`

## 📝 Konvensi Penamaan Alias

| Middleware Class     | Alias      |
| -------------------- | ---------- |
| `AuthMiddleware`     | `auth`     |
| `RoleMiddleware`     | `role`     |
| `CsrfMiddleware`     | `csrf`     |
| `MyCustomMiddleware` | `mycustom` |

**PENTING:** Gunakan alias lowercase tanpa hyphen.

## 🛣️ Usage in Routes

```php
// Single middleware
$router->get('/admin', [AdminController::class, 'index'], ['auth']);

// Multiple middleware
$router->group(['middleware' => ['auth', 'role:admin', 'csrf']], function ($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
});

// Parameter middleware
$router->get('/users/:id', [UserController::class, 'show'], ['auth', 'role:admin,user']);
```

## 🔒 CSRF Middleware

### Cara Kerja

- **Opsional** - hanya aktif jika ditambahkan ke route
- Auto-skip untuk GET, HEAD, OPTIONS (safe methods)
- Auto-skip untuk API request dengan `Authorization: Bearer <token>`
- Validasi token dari 3 sumber:
  1. `$request->body['_token']` - dari form input hidden
  2. `$_POST['_token']` - fallback untuk FormData
  3. `$request->server['HTTP_X_CSRF_TOKEN']` - dari header (spa.js)

### Usage

```php
// Proteksi route POST/PUT/DELETE
$router->post('/users', [UserController::class, 'store'], ['csrf']);
$router->put('/users/:id', [UserController::class, 'update'], ['csrf']);
$router->delete('/users/:id', [UserController::class, 'delete'], ['csrf']);
```

### SPA Form (dengan `data-spa`)

```html
<!-- TIDAK PERLU input hidden - spa.js otomatis kirim token via header -->
<form data-spa action="/users" method="POST">
  <!-- form fields -->
</form>
```

### Traditional Form (tanpa `data-spa`)

```html
<!-- WAJIB input hidden untuk form non-SPA -->
<form action="/users" method="POST">
  <input type="hidden" name="_token" value="<?= csrf_token() ?>" />
  <!-- form fields -->
</form>
```

### Helper Functions

```php
csrf_token()   // Generate/get CSRF token dari session
csrf_field()   // Render hidden input: <input type="hidden" name="_token" value="...">
```

**Note:** Meta tag CSRF otomatis ditambahkan oleh View, tidak perlu manual di layout.

## ✅ Middleware Best Practices

| Practice                 | Description                                               |
| ------------------------ | --------------------------------------------------------- |
| **Lightweight**          | Hindari query database berat                              |
| **Dependency Injection** | Gunakan constructor untuk model/service                   |
| **Exception Handling**   | Throw `AuthenticationException`, `AuthorizationException` |
| **Use Cases**            | Authorization, logging, rate limiting                     |

## 📚 Related Skills

- [`mazu-core`](../mazu-core/SKILL.md) - Core reference & CLI
- [`mazu-controller`](../mazu-controller/SKILL.md) - Controller patterns
