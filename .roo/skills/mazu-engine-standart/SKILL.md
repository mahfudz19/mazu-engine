---
name: mazu-engine-standart
description: Mazu Framework - Fullstack Developer (Quick Reference)
---

# Mazu Framework - Quick Reference

## 📚 Available Skills

| Skill                                            | Description                                |
| ------------------------------------------------ | ------------------------------------------ |
| [`mazu-core`](../mazu-core/SKILL.md)             | Folder structure, CLI, important rules     |
| [`mazu-controller`](../mazu-controller/SKILL.md) | Controller patterns, routing, transactions |
| [`mazu-model`](../mazu-model/SKILL.md)           | Model schema, migration, database          |
| [`mazu-middleware`](../mazu-middleware/SKILL.md) | Auth, Role, CSRF middleware                |
| [`mazu-views`](../mazu-views/SKILL.md)           | View system, layouts, SPA, auto-discovery  |

## ⚡ Quick Start

```bash
# Create new files using CLI
php mazu make:controller User
php mazu make:model User
php mazu make:middleware Auth
php mazu migrate
php mazu serve
```

## 🎯 Key Concepts

**Controller:**

- 2 parameters only: `(Request $request, Response $response)`
- Use `$request->param('id')` for route params
- Return `View | RedirectResponse | JsonResponse`
- Always use try-catch

**Model:**

- Extend `App\Core\Database\Model`
- Use constructor injection for `DatabaseManager`
- Timestamp format: `['default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP']`

**Middleware:**

- Implement `MiddlewareInterface`
- Auto-discovered from `addon/Middleware/`
- Alias: lowercase, no "Middleware" suffix

**Views:**

- Nested layout (Next.js style)
- Auto-discovered CSS/JS
- SPA navigation with `data-spa`

## ⚠️ Important Rules

1. **JANGAN MODIFIKASI** folder `app/`
2. **GUNAKAN CLI** untuk membuat files
3. Gunakan **Bahasa Indonesia** untuk UI & comments

## 📖 Project Context

See [`plans/psyco-test-overview.md`](../../../plans/psyco-test-overview.md) for project-specific documentation.
