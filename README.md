# Binary Business Management System

A Laravel platform for running a binary (left/right) network business:
members and sponsors, binary tree placement with spillover, package sales,
team volume matching, binary and referral commission, member wallets, and
withdrawals. It's built for Bangladesh (BDT, +880 phone numbers, bKash,
Nagad and SSLCommerz).

## Stack

| Layer       | Choice                                                                                                     |
| ----------- | ---------------------------------------------------------------------------------------------------------- |
| Framework   | Laravel 13, PHP 8.3+                                                                                       |
| Database    | MySQL 8 (InnoDB, needed for row locking)                                                                   |
| Member app  | Vue 3 + Inertia.js v3 + Tailwind 4, built with Vite+ (official Laravel Vue starter kit, auth via Fortify)  |
| Admin panel | AdminLTE 4 + Blade (`jeroennoten/laravel-adminlte`) under `/admin`, with its own `admin` guard             |
| Packages    | Spatie Permission, MediaLibrary, ActivityLog, Backup, Sluggable. Laravel Sanctum for the future mobile API |
| Payments    | bKash, SSLCommerz, Nagad (wired in Phase 4)                                                                |
| Tests       | PHPUnit against a MySQL test database                                                                      |

## Local setup

Requirements: PHP 8.3+ (`pdo_mysql`, `bcmath`, `gd`, `exif`, `zip`),
Composer 2, Node 22+, and MySQL 8. On Windows, Laragon covers all of these.

```bash
# 1. Create the app database and the test database
mysql -uroot -e "CREATE DATABASE binary_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                 CREATE DATABASE binary_system_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Install, configure, migrate, publish AdminLTE assets and build the frontend
composer setup          # copies .env.example → .env if it doesn't exist yet

# 3. Seed roles, permissions and the first admin
php artisan db:seed

# 4. Run it
composer dev            # server + queue + logs + Vite
```

| Area        | URL                               | Seeded login                                                                            |
| ----------- | --------------------------------- | --------------------------------------------------------------------------------------- |
| Member app  | http://localhost:8000/login       | `test@example.com` / `password`                                                         |
| Admin panel | http://localhost:8000/admin/login | `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env` (default `admin@example.com` / `password`) |

> Change the admin password before deploying anywhere public.

### Running tests

```bash
npm run build        # Inertia page tests need the Vite manifest
php artisan test     # PHPUnit, uses the binary_system_test MySQL database (see phpunit.xml)
composer test        # Pint + PHPStan (level 7) + tests, the same checks CI runs
```

CI (`.github/workflows/tests.yml`) runs `composer setup` and `composer ci:check`
against a MySQL 8.4 service container on every push to `main` and on every PR.

## Code layout

| Path                         | What goes there                                                                                                |
| ---------------------------- | -------------------------------------------------------------------------------------------------------------- |
| `app/Services`               | Business logic services (placement, matching, wallet, …)                                                       |
| `app/Actions`                | Single-purpose actions (Fortify auth actions live in `Actions/Fortify`)                                        |
| `app/DTOs`                   | Typed data objects passed between services                                                                     |
| `app/Support`                | Framework-agnostic helpers, e.g. `Money`                                                                       |
| `app/Enums`                  | Enums, e.g. `AdminPermission`                                                                                  |
| `app/Http/Controllers/Admin` | Admin panel (Blade) controllers                                                                                |
| `routes/admin.php`           | Admin routes. Registered in `bootstrap/app.php` with the `/admin` prefix, `admin.` names and the `admin` guard |
| `resources/js/pages`         | Member-facing Inertia pages                                                                                    |
| `resources/views/admin`      | Admin panel Blade views (extend `adminlte::page`)                                                              |

Keep controllers thin. Validate with Form Requests, authorize with Policies,
and put business logic in Services or Actions.

## Auth, roles and permissions

- **Members** use the `web` guard (`App\Models\User`) and get the `member` role on registration.
- **Admins** use the `admin` guard (`App\Models\Admin`, `admins` table). They log in at `/admin/login` and have their own session guard.
- Admin permissions (Spatie, `admin` guard): `manage-members`, `manage-tree`,
  `manage-sales`, `manage-withdrawals`, `manage-kyc`, `manage-settings`,
  `view-reports`. The `admin` role has all of them. Permissions are defined
  in `App\Enums\AdminPermission` and seeded by `RolesAndPermissionsSeeder`,
  which is idempotent and safe to re-run on deploy.

## Money

All money is stored as integer poysha (৳1 = 100 poysha) in `bigInteger`
columns. BV uses the same ×100 scale, and rates are basis points
(`1000` = 10%). Use `App\Support\Money` (`percentOf`, `fromTaka`, `format`)
and never floats.

## Configuration

`.env.example` lists every setting with placeholder values: database, mail,
SMS gateway (`SMS_*`), bKash (`BKASH_*`), SSLCommerz (`SSLCOMMERZ_*`),
Nagad (`NAGAD_*`) and `COMMISSION_CYCLE` (`daily` | `weekly`). Gateway
credentials are read through `config/services.php`, and business settings
through `config/business.php`. Admins will change rates, caps and minimums
in the database (`commission_rules` / `settings`), not in `.env`.

## Business rules

The business rules this system has to follow are the numbered list in
[`CLAUDE.md`](CLAUDE.md), which is the Phase 0 project context. Changing any
of them needs sign-off. The phase-by-phase build plan is in
[`binary-business-prompt-series.md`](binary-business-prompt-series.md).
