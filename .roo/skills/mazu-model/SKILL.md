---
name: mazu-model
description: Mazu Framework - Model Patterns (Schema, Migration, Database)
---

# Mazu Framework - Model Patterns

## 🏗️ Creating Models

**Gunakan CLI - JANGAN manual!**

```bash
php mazu make:model User    # Buat UserModel
php mazu make:model Post    # Buat PostModel
```

## 📐 Basic Model Structure

```php
<?php

namespace Addon\Models;

use App\Core\Database\Model;
use App\Core\Database\DatabaseManager;

class UserModel extends Model
{
    protected ?string $connection = null;
    protected string $table = 'users';
    protected bool $timestamps = true;

    protected array $schema = [
        'id' => ['type' => 'id', 'primary' => true, 'auto_increment' => true],
        'email' => ['type' => 'varchar', 'length' => 255, 'nullable' => false],
        'created_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'],
        'updated_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP'],
    ];

    protected array $seed = [];

    public function __construct(DatabaseManager $dbManager)
    {
        parent::__construct($dbManager);
    }

    public function findByEmail(string $email): ?array { ... }
}
```

## 📊 Supported Schema Types

| Type                             | SQL Equivalent                 | Notes                    |
| -------------------------------- | ------------------------------ | ------------------------ |
| `id`                             | BIGINT UNSIGNED AUTO_INCREMENT | Primary key              |
| `ulid`                           | CHAR(26)                       | ULID primary key         |
| `uuid`                           | CHAR(36)                       | UUID primary key         |
| `bigint`                         | BIGINT [UNSIGNED]              | Set `unsigned: true`     |
| `int`                            | INT [UNSIGNED]                 | Set `unsigned: true`     |
| `varchar`                        | VARCHAR(length)                | Set `length: 255`        |
| `text`, `mediumtext`, `longtext` | TEXT variants                  | -                        |
| `enum`                           | ENUM(...)                      | Set `values: ['a', 'b']` |
| `json`                           | JSON                           | -                        |
| `timestamp`                      | TIMESTAMP                      | -                        |
| `datetime`                       | DATETIME                       | -                        |
| `date`                           | DATE                           | -                        |
| `boolean`                        | TINYINT(1)                     | -                        |
| `decimal`                        | DECIMAL(precision, scale)      | Set `precision`, `scale` |

## 🔧 Additional Schema Options

```php
'schema' => [
    // Foreign key
    'user_id' => [
        'type' => 'bigint', 'unsigned' => true,
        'foreign' => 'users.id',
        'on_delete' => 'cascade',  // cascade, restrict, set null, no action
        'on_update' => 'cascade'
    ],

    // Unique constraint
    'email' => ['type' => 'varchar', 'length' => 255, 'unique' => true],

    // Default values
    'status' => ['type' => 'enum', 'values' => ['active', 'inactive'], 'default' => 'active'],

    // Timestamp with ON UPDATE
    'updated_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP'],

    // Timestamp only (no auto-update)
    'deleted_at' => ['type' => 'timestamp', 'nullable' => true],
]
```

## ⚠️ IMPORTANT - Timestamp Format

```php
// ✅ BENAR - Pisahkan default dan on_update
'updated_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP'],

// ❌ SALAH - Jangan gabungkan dalam satu string
'updated_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
```

**Format yang salah akan menyebabkan error MySQL:** `Invalid default value for 'updated_at'`

## 🔄 Migration Behavior

- `ModelSchemaMigrator` scan semua model di `addon/Models/`
- `UserModel` selalu di-migrate **pertama** (foreign key safety)
- Jika `timestamps = true`, `created_at` & `updated_at` ditambahkan otomatis
- Tabel yang sudah ada akan di-skip
- **Schema kosong = tabel tidak dibuat**

## 💾 Database Transactions

```php
public function store(Request $request, Response $response): View | RedirectResponse {
    try {
        $db = $this->userModel->getDb();
        $db->beginTransaction();

        try {
            $this->userModel->create($data);
            $this->profileModel->create($relatedData);

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

## 📝 Model Best Practices

| Practice                  | Description                                                              |
| ------------------------- | ------------------------------------------------------------------------ |
| **Constructor Injection** | `DatabaseManager` via constructor                                        |
| **Define Full Schema**    | Lengkap untuk migration                                                  |
| **Helper Methods**        | Buat method untuk query umum                                             |
| **Return Types**          | `?array` (single), `array` (multiple), `int` (ID), `bool` (success/fail) |
| **Foreign Keys**          | Gunakan `on_delete => 'cascade'` untuk related tables                    |

## 📚 Related Skills

- [`mazu-core`](../mazu-core/SKILL.md) - Core reference & CLI
- [`mazu-controller`](../mazu-controller/SKILL.md) - Controller patterns
