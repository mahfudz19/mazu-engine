# 🚀 Mazu Framework - Starter Template

Starter template untuk aplikasi PHP modern berbasis **Mazu Framework**. Framework ini terinspirasi dari Laravel (MVC, CLI) dan Next.js (nested layout system).

## ✨ Fitur Utama

- **MVC Architecture** - Struktur Laravel-inspired dengan Model, View, Controller
- **Nested Layout System** - Next.js-style layout dengan auto-discovery
- **CLI Tools** - `php mazu` commands untuk generate code
- **Dependency Injection** - Auto-wiring container
- **Middleware System** - Auth, CSRF, Throttle middleware
- **SPA Engine** - Hybrid SPA navigation untuk UX yang cepat
- **Database Migration** - Schema-based migration system
- **Queue System** - Background job processing

## 📦 Instalasi

### 1. Clone & Setup

```bash
# Clone repository
git clone <repository-url>
cd <project-folder>

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate key (jika diperlukan)
php mazu key:generate
```

### 2. Konfigurasi Database

Edit file `.env`:

```env
APP_ENV=development
APP_DEBUG=true
APP_NAME=Mazu

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=mazu_db
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Jalankan Migration

```bash
php mazu migrate
```

### 4. Start Development Server

```bash
php mazu serve
```

Akses aplikasi di `http://localhost:8000` (atau port yang dikonfigurasi).

## 🛠️ CLI Commands

Gunakan `php mazu` untuk development:

```bash
# Generate code
php mazu make:controller User      # Buat controller baru
php mazu make:model User           # Buat model baru
php mazu make:middleware Auth      # Buat middleware baru
php mazu make:job SendEmail        # Buat queue job

# Database
php mazu migrate                   # Jalankan migration

# Development
php mazu serve                     # Start dev server
php mazu build                     # Build assets (Tailwind, etc)
php mazu route:cache               # Cache routes untuk production

# Info
php mazu about                     # Tampilkan info framework
```

## 📁 Struktur Folder

```
project-root/
├── app/                    # Core Engine (JANGAN MODIFIKASI)
│   ├── Console/            # CLI Commands
│   ├── Core/               # Framework Core
│   ├── Helpers/            # Helper Functions
│   └── Services/           # Core Services
├── addon/                  # Application Code (BOLEH DIMODIFIKASI)
│   ├── Controllers/        # Controllers
│   ├── Middleware/         # Middleware
│   ├── Models/             # Models
│   ├── Providers/          # Service Providers
│   ├── Router/
│   │   └── index.php       # Route Definitions
│   └── Views/              # View Templates
│       ├── layout.php      # Root layout
│       └── index.php       # Home page
├── config/                 # Configuration Files
├── public/                 # Public Assets
└── storage/                # Cache, Logs, Secrets
```

## 🎯 Quick Start

### 1. Buat Route Baru

Edit `addon/Router/index.php`:

```php
<?php

use App\Core\Http\Request;
use App\Core\Http\Response;

// Simple route
$router->get('/hello', function (Request $request, Response $response) {
    return $response->renderPage([
        'message' => 'Hello World!'
    ]);
});

// Controller route
$router->get('/users', [UserController::class, 'index']);

// Route dengan middleware
$router->post('/users', [UserController::class, 'store'], ['auth', 'csrf']);
```

### 2. Buat Controller

```bash
php mazu make:controller User
```

Ini akan membuat `addon/Controllers/UserController.php` dengan template CRUD.

### 3. Buat Model

```bash
php mazu make:model User
```

Ini akan membuat `addon/Models/UserModel.php` dengan schema definition.

### 4. Buat View

Buat file di `addon/Views/`:

```
addon/Views/
├── layout.php          # Root layout (header, footer, nav)
├── (app)/
│   ├── layout.php      # Group layout
│   └── users/
│       └── index.php   # View untuk /users
```

CSS/JS akan auto-discovered jika filename sama:

- `index.php` → `index.css`, `index.js`

## 🔧 Helper Functions

```php
// Environment
env('APP_NAME', 'Default')

// URL
getBaseUrl('/users')
asset('css/style.css')
currentUrl()

// Security
csrf_token()
csrf_field()
e($value)  // Escape HTML

// Debug
dump($variable)
logger()->info('Message', ['context' => $data])

// Utils
ulid()      // Generate ULID
uuidv4()    // Generate UUID
```

## 📚 Dokumentasi Lengkap

- **Skill File:** `.roo/skills/mazu-fullstack-developer/SKILL.md`
- **CLI Commands:** `app/Console/Commands/`
- **Core Classes:** `app/Core/`

## 🧪 Testing

```bash
# Run tests (jika tersedia)
php vendor/bin/phpunit

# Check code style
php vendor/bin/phpcs
```

## 🚀 Deployment

### Production Setup

```bash
# Install production dependencies
composer install --no-dev --optimize-autoloader

# Cache routes
php mazu route:cache

# Build assets
php mazu build

# Set environment
APP_ENV=production
APP_DEBUG=false
```

### Server Requirements

- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Composer
- Extension: pdo_mysql, json, mbstring

## 📄 License

Mazu Framework - Personal/Commercial Use

---

**Mazu Framework** - _Build Faster. Scale Better._
