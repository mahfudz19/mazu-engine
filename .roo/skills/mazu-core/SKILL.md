---
name: mazu-core
description: Mazu Framework - Core Reference (Folder Structure, CLI, Important Rules)
---

# Mazu Framework - Core Reference

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

## 🛠️ CLI Commands

**Gunakan CLI untuk membuat file baru - JANGAN manual!**

```bash
php mazu make:controller User    # Buat UserController
php mazu make:model User         # Buat UserModel
php mazu make:middleware Auth    # Buat AuthMiddleware
php mazu make:job SendEmail      # Buat SendEmailJob
php mazu migrate                 # Jalankan migration
php mazu build                   # Build assets
php mazu serve                   # Dev server
```

**Template CLI ada di:** `app/Console/Commands/Make*.php`

## 🔧 Helper Functions

```php
env('APP_NAME')           // Environment variable
getBaseUrl('/users')      // Base URL dengan subdirectory
asset('css/style.css')    // Versioned asset
csrf_token(), csrf_field() // CSRF helpers
e($value)                 // Escape HTML
dump($var)                // Debug
```

## 📦 Response & Request Quick Reference

```php
// Response
$response->renderPage(['data' => $data], ['meta' => ['title' => 'Page']]);
$response->json(['status' => 'success']);
$response->redirect('/users');

// Request
$request->input('name');      // Input value
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
5. Controller return `View | RedirectResponse` via `$response->renderPage()`
6. CSS/JS auto-discovered (e.g., `index.php` → `index.css`)
7. Gunakan **Bahasa Indonesia** untuk komentar dan UI text

## 📚 Related Skills

- [`mazu-controller`](../mazu-controller/SKILL.md) - Controller patterns & routing
- [`mazu-model`](../mazu-model/SKILL.md) - Model schema & migration
- [`mazu-middleware`](../mazu-middleware/SKILL.md) - Middleware patterns
- [`mazu-views`](../mazu-views/SKILL.md) - View system & layouts

## 📚 Source of Truth

- **CLI Templates:** `app/Console/Commands/Make*.php`
- **Core Classes:** `app/Core/Http/Request.php`, `app/Core/Http/Response.php`
- **Base Model:** `app/Core/Database/Model.php`
- **View Engine:** `app/Services/ViewService.php`
