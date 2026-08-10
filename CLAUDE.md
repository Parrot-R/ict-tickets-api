# CLAUDE.md

This file gives Claude Code (and other AI assistants) the context needed to work effectively in this repository.

## What this repo is

A small, dependency-free **PHP 8.2 REST API backend** for "IT OpsDesk" (also referenced internally as "IT-Task-Management") — an internal IT ticketing/helpdesk system that tracks organizations, tasks (tickets), incidents, assets, and an activity feed. There is **no frontend code in this repo**; it is designed to be consumed by a separate React + Vite frontend (see `config/database.php.example`, which is actually a leftover Vite config — see "Known quirks" below) that proxies `/api/*` requests to this backend.

The whole backend is ~700 lines of plain PHP with no framework and no Composer dependencies — just the built-in PDO/MySQL extension.

## Repository layout

```
.
├── api/
│   ├── index.php        # Single-file router + all REST handlers (organizations, tasks, incidents, assets, activity, search, health)
│   └── health.php        # Standalone trivial health check (not routed through index.php's own /health)
├── config/
│   ├── database.php          # Real DB config, reads env vars with local (XAMPP) fallbacks — safe to commit, no secrets
│   └── database.php.example  # NOTE: mislabeled — contains a Vite config for the companion frontend, not a PHP config example
├── database/
│   ├── schema.sql        # Full schema: organizations, tasks, incidents, assets, activity (DROP + CREATE)
│   └── seed.sql           # Demo/sample data (TRUNCATE + INSERT) for local development
├── includes/
│   ├── cors.php           # CORS headers + OPTIONS preflight short-circuit
│   ├── db.php             # Builds the PDO connection from config/database.php
│   └── helpers.php        # json_response/json_error, read_json_body, require_fields, slug_id, map_* row-to-JSON transformers, date helpers
├── router.php             # Router for `php -S` (PHP's built-in dev server) — dispatches /api/* and /seed.php
├── seed.php               # Runs schema.sql then seed.sql against the configured database (browser or CLI)
├── .htaccess               # Apache/XAMPP routing (RewriteBase /backend/) — alternative to router.php
└── Dockerfile              # php:8.2-cli image, installs pdo_mysql, runs `php -S 0.0.0.0:8080 router.php`
```

There are no tests, no linter config, and no `composer.json` in this repo — it's intentionally minimal, plain PHP.

## How requests flow

1. Entry point is always `api/index.php` (reached via `router.php` in dev/Docker, or `.htaccess` rewrite under Apache).
2. `index.php` requires `includes/cors.php` (sets CORS headers, handles `OPTIONS`), then `includes/helpers.php`, then builds a `$pdo` from `includes/db.php`.
3. The URL path after `/api/` is split into `$resource` (e.g. `tasks`) and an optional `$id` (e.g. `T-1042`).
4. A single `match ($resource)` statement dispatches to one `handle_*()` function per resource, defined further down in the same file.
5. Each handler is a flat `if ($method === 'X' ...)` chain covering GET (list/single), POST (create), PUT (update), DELETE — falling through to `json_error('Method not allowed', 405)`.
6. Handlers use raw SQL via `PDO::prepare`/`execute` (parameterized — no query-building library), then map DB rows to API JSON shape via `map_task`, `map_incident`, `map_asset`, `map_organization`, `map_activity` in `includes/helpers.php`.
7. All responses go through `json_response()` / `json_error()`, which set `Content-Type: application/json`, encode with `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`, and `exit`.

## API surface

Base path: `/api/`

| Resource | Methods | Notes |
|---|---|---|
| `health` | GET | Row counts per table (different from the standalone `api/health.php`) |
| `organizations` | GET (list/one), POST, PUT, DELETE | id format `org-*` |
| `tasks` | GET (list/one, `?orgId=`), POST, PUT, DELETE | id format `T-####`, `tags` stored as JSON column |
| `incidents` | GET (list/one, `?orgId=`), POST, PUT, DELETE | id format `INC-###` |
| `assets` | GET (list/one, `?orgId=`), POST, PUT, DELETE | id format `AST-####` |
| `activity` | GET (`?orgId=`, `?limit=`, default 80, max 100), POST | id format `a#`; append-only, no update/delete |
| `search` | GET `?q=` | `LIKE`-based search across tasks/incidents/assets, 20 results each |

Conventions to follow when extending the API:
- IDs are app-generated strings (`slug_id($prefix)` → `prefix-<8 hex chars>`), not auto-increment ints. Clients may also supply their own `id`.
- JSON keys returned to clients are **camelCase** (`orgId`, `dueDate`, `entityTitle`); DB columns are **snake_case** (`org_id`, `due_date`). The `map_*` functions in `includes/helpers.php` are the single translation layer — always add new fields there, not ad hoc in handlers.
- Dates: DB stores `DATETIME`/`DATE`; API returns ISO 8601 via `date('c', strtotime(...))`. Use `to_mysql_datetime()`/`to_mysql_date()` helpers when writing incoming ISO strings back to the DB.
- `require_fields()` enforces required fields and calls `json_error(..., 422)` on failure — use it at the top of POST/PUT branches rather than hand-rolled checks.
- Every table with an `org_id` has `ON DELETE CASCADE` to `organizations` — deleting an org cascades to its tasks/incidents/assets/activity.
- All DB access is via parameterized `PDO` queries. Never interpolate user input directly into SQL (the one exception, `$limit` in `handle_activity`, is already cast with `min()`/`max()`/`(int)` before interpolation — keep that pattern if you touch it).

## Local development

Two supported ways to run this:

**Built-in PHP server (matches Dockerfile/Railway behavior):**
```bash
php -S localhost:8080 router.php
```
A companion Vite frontend proxies `/api` to `http://localhost:8080/api` (or similar — see `config/database.php.example`, which is actually the frontend's `vite.config.ts`).

**XAMPP/Apache:**
Rely on `.htaccess` (`RewriteBase /backend/`), placing this repo at `.../htdocs/IT-Task-Management/backend/`.

**Database setup:**
```bash
cp config/database.php  # already has env-var + localhost fallbacks; edit values in config/database.php directly for local XAMPP creds if needed
php seed.php             # creates schema (drops existing tables!) and loads demo data
# or import database/schema.sql then database/seed.sql manually (e.g. via phpMyAdmin)
```
`seed.php` and `database/schema.sql` are destructive — they `DROP TABLE IF EXISTS` / `TRUNCATE` before loading. Never point `seed.php` at a database with real data.

**Docker:**
```bash
docker build -t ict-tickets-api .
docker run -p 8080:8080 -e MYSQLHOST=... -e MYSQLPORT=... -e MYSQLDATABASE=... -e MYSQLUSER=... -e MYSQLPASSWORD=... ict-tickets-api
```

**Production (Railway):** `config/database.php` reads `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD` from the environment — these are Railway's default MySQL plugin variable names.

## Known quirks (don't "fix" without checking with the user first)

- **`config/database.php.example` contains a JavaScript/Vite config, not a PHP example.** This has been true since the initial commit — it looks like a copy-paste mistake from the frontend repo. Be aware of this when someone asks to "check the example config" or when writing setup docs; don't assume it documents `config/database.php`'s shape.
- `api/health.php` and the `/api/health` route in `api/index.php` are two different, overlapping health checks (one trivial static check, one with row counts). Consolidate only if asked.
- No `.gitignore`, no `composer.json`, no automated tests exist. If you add PHP dependencies, you'll need to introduce Composer from scratch; if you add tests, there's no existing framework/convention to follow — ask the user's preference (PHPUnit is the natural default).

## Making changes

- Keep the no-framework, no-dependency style unless explicitly asked to introduce one (e.g. Composer, PHPUnit, a router library) — this is a deliberately minimal project.
- Match existing formatting: 4-space indentation, `declare(strict_types=1);` at the top of every PHP file, arrays aligned with `=>` for readability in `index.php`.
- When adding a new resource/table: add the table to `database/schema.sql` (with `org_id` FK + cascade if org-scoped), add a `map_*()` helper, add a `handle_*()` function, and wire it into the `match ($resource)` block in `api/index.php`. Add matching seed rows to `database/seed.sql` if useful for local dev.
- There's no build step for the backend — changes to `.php` files take effect immediately on the next request under `php -S` or Apache.
