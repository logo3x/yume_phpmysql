# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

Spanish-language ("Control de Negocio") small-business management app: clients, inventory, purchases, sales, shipments, cash flow, reports. Migrated from Node.js/PostgreSQL to **plain PHP + MySQL** (no Composer, no npm, no build step). Designed to drop into WAMP/XAMPP/LAMP at e.g. `C:\wamp64\www\yume-main` and run.

User-facing text (UI strings, error messages, comments) is in **Spanish**. Keep new strings in Spanish to match.

## Run / install / "build"

There is no build, no package manager, no test suite. Workflow is:

```bash
# Windows one-shot: creates DB and imports schema
instalar.bat

# Or web-based installer (delete after use)
http://localhost/yume-main/setup-db.php

# Or manual
mysql -u root -p -e "CREATE DATABASE yume_negocio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p yume_negocio < config/schema.sql

# Then browse to
http://localhost/yume-main/
```

DB credentials live in [config/database.php](config/database.php) and are overridable via env vars `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.

Smoke checks: `GET /api/health` (no auth) and `api-diagnostic.php`. `test.php` / `test.html` are throwaway probes.

**Apache `mod_rewrite` is required.** Without it, every `/api/*` URL 404s — the rewriting in [.htaccess](.htaccess) is what turns `/api/products` into `api/index.php?_route=products`.

## Architecture

Two-layer flow: a single front controller serves the SPA shell and forwards API calls; the API router dispatches by route name to per-resource PHP files.

```
Request → .htaccess → index.php (static/SPA) ─┐
                                              ├─→ api/index.php (router)
                       /api/* paths ──────────┘         │
                                                        ├─→ auth.php / products.php / sales.php / ...
                                                        │
                                            all require helpers.php (auth + JSON + DB)
                                                        │
                                                        └─→ config/database.php → PDO singleton → MySQL
```

Key files to understand before changing routing or auth:

- [index.php](index.php) — front controller. Serves `public/*` static assets, falls through to `public/index.html` for SPA routes. Forwards anything starting with `api/` to the API router.
- [api/index.php](api/index.php) — switch-based router. Uses `$_GET['_route']` (set by `.htaccess`) or parses `REQUEST_URI`. Each `case` either `require`s a per-resource file or rewrites `$_GET['action']` and includes a multi-action file (auth, admin, reports, export).
- [api/helpers.php](api/helpers.php) — every API file `require_once`s this. It provides:
  - `getPDO()` (via `config/database.php`) — a static PDO singleton with `ATTR_ERRMODE=EXCEPTION` and `FETCH_ASSOC` defaults.
  - `requireAuth()` / `requireAdmin()` — call **at the top of every protected endpoint** before doing any work. They `errorResponse()`+`exit` on failure.
  - `setCorsHeaders()` — also handles OPTIONS preflight.
  - `jsonResponse()`, `successResponse()`, `errorResponse()` — all `exit` after writing.
  - `getJsonInput()` for POST JSON bodies; `parsePutInput()` for PUT (tries JSON, falls back to URL-encoded).
  - `hashPassword()`, `generateSalt()` — PBKDF2-SHA512, 100k iterations.
  - `setSessionCookie()` / `getSessionToken()` / `clearSessionCookie()` — HTTP-only `session_token` cookie, 24h.
  - `calcPricing($purchase, $extra, $marginPct)` and `getProductStatus($stock)` — shared business rules used by both `products.php` and `sales.php`.
  - `createBackup()` — shells out to `mysqldump`, writes to `backups/`.
  - A `set_exception_handler` that emits 503 for DB errors, 500 otherwise.

### Per-endpoint pattern (mirror this for new endpoints)

Every resource file follows the same shape:

```php
require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();              // or requireAdmin()
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getPDO();
if ($method === 'GET')  { ... jsonResponse($rows); }
if ($method === 'POST') { ... jsonResponse([...]); }
if ($method === 'PUT')  { $vars = parsePutInput(); ... successResponse(); }
if ($method === 'DELETE'){ ... successResponse(); }
errorResponse('Método no permitido', 405);
```

The response helpers `exit`, so successive `if` blocks act as a switch. Use prepared statements via PDO — never interpolate values into SQL.

### Multi-action vs single-resource files

Most files map 1:1 to a route (`products.php`, `clients.php`). Three are dispatched on a sub-action via `$_GET['action']`, set in the router by stripping a prefix:

- `auth.php` — `status`, `bootstrap`, `login`, `logout`. Note: `requireAuth()` is bypassed for `/api/auth/*` paths (skip is hardcoded inside `requireAuth()`), and `bootstrap` only succeeds when zero users exist.
- `admin.php` — `users`, `roles` (uses `requireAdmin()`).
- `reports.php` — `summary`, `charts`, `filtered`.
- `export.php` — also handles `import/*` from the same file via `$_GET['type']`.

When adding a new sub-action, update both `api/index.php` (the route case) and the action dispatch inside the resource file.

## Cross-cutting business rules (don't break these)

These behaviors live in code, not in schema constraints — read [api/sales.php](api/sales.php) and [api/products.php](api/products.php) before touching them:

- **Selling decrements stock and writes a cash movement.** A `POST /api/sales` (a) inserts the sale, (b) recomputes `products.stock` and `products.status` via `getProductStatus()`, and (c) inserts an `Ingreso`/`Ventas` row in `cash_movements`. Reversing/deleting a sale currently does **not** undo (b) or (c) — be aware when extending.
- **Product totals are denormalized.** `total_real_cost` and `sale_price` are computed by `calcPricing()` on insert/update. Always recompute via the helper rather than writing the columns directly, and recompute on every PUT — `products.php` already does.
- **Bootstrap-only first user.** `auth.php` checks `COUNT(users)` before allowing `/api/auth/bootstrap`; afterwards the only path to create users is `requireAdmin()` → `admin.php`. Don't add a public registration endpoint.
- **`requireAuth()` is mandatory** at the top of every API file except `auth.php` and `health.php`. It's not centralized in the router — each resource file must call it itself.
- **Backups are rotated to 20.** `cleanOldBackups()` keeps the 20 newest `negocio-*.sql`; uploads go in `uploads/`, backups in `backups/`. Both must be writable.

## Database

- MySQL/MariaDB, `utf8mb4_unicode_ci`, all tables `InnoDB`. Schema is the source of truth: [config/schema.sql](config/schema.sql).
- Date columns are stored as `VARCHAR(50)` (`entry_date`, `purchase_date`, `sale_date`, `movement_date`) — they are written as ISO `YYYY-MM-DD` strings from the frontend / `date('Y-m-d')`. Don't try to use them with native MySQL date functions without casting.
- `settings` is a single-row table pinned at `id=1` via `CHECK`.
- FK rules of note: `sales.product_id`/`purchases.product_id` cascade-delete; `sales.client_id` and `shipments.sale_id` set NULL.
- `schema.sql` uses `DROP TABLE IF EXISTS` — running it wipes existing data. Never run it against a populated DB without a backup.

## Frontend

- Single-page app in [public/](public/): `index.html` + `app.js` (~1400 lines, no framework, no bundler) + `styles.css`. Uses Chart.js loaded from CDN.
- `app.js` calls `/api/...` directly via `fetch(..., { credentials: 'include' })` — the session cookie is the auth mechanism. Tabs in the UI: `clientes`, `inventario`, `compras`, `ventas`, `envios`, `caja`, `reportes`.
- The `index.php` front controller falls back to `public/index.html` for unknown paths so deep-link refreshes work.

## Conventions for changes

- Edit existing per-resource files rather than creating new layers (no controllers/services/ORM — it's intentionally flat).
- Follow the four-`if`-blocks-then-405 pattern when adding methods.
- `jsonResponse`/`successResponse`/`errorResponse` always `exit`. Don't add code after them.
- Use `requireAdmin()` for anything that mutates users/roles or system settings; `requireAuth()` for everything else.
- File uploads use `$_FILES`, save under `uploads/`, store the public path (`/uploads/<name>`) in the DB. Generate filenames with `time() . '-' . rand()` to avoid collisions (see `products.php`).
- Don't add Composer or npm. The deployment target is shared PHP hosting — it must keep working without dependencies.
