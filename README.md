# Binary Business Management System

A Laravel platform for running a binary (left/right) network business:
members and sponsors, binary tree placement with spillover, package sales,
team volume matching, binary and referral commission, member wallets, and
withdrawals. It's built for Bangladesh (BDT, +880 phone numbers, bKash,
Nagad and SSLCommerz).

- [Stack](#stack)
- [Local setup](#local-setup)
- [Code layout](#code-layout)
- [Business rules, money and permissions](#business-rules-money-and-permissions)
- [Production](#production): server setup, `.env` checklist, deploying, rollback
- [Operations](#operations): the nightly jobs, queue workers, monitoring, backups and restores

## Stack

| Layer       | Choice                                                                                                    |
| ----------- | --------------------------------------------------------------------------------------------------------- |
| Framework   | Laravel 13, PHP 8.3+                                                                                      |
| Database    | MySQL 8 (InnoDB, needed for row locking)                                                                  |
| Member app  | Vue 3 + Inertia.js v3 + Tailwind 4, built with Vite+ (official Laravel Vue starter kit, auth via Fortify) |
| Admin panel | AdminLTE 4 + Blade (`jeroennoten/laravel-adminlte`) under `/admin`, with its own `admin` guard            |
| Packages    | Spatie Permission, MediaLibrary, ActivityLog, Backup. Sentry for error tracking                           |
| Payments    | bKash, SSLCommerz, Nagad. Every callback is verified server-to-server                                     |
| Tests       | PHPUnit against a MySQL test database                                                                     |

## Local setup

Requirements: PHP 8.3+ (`pdo_mysql`, `bcmath`, `gd`, `exif`, `zip`),
Composer 2, Node 22+, and MySQL 8. On Windows, Laragon covers all of these.

```bash
# 1. Create the app database and the test database
mysql -uroot -e "CREATE DATABASE binary_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                 CREATE DATABASE binary_system_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Install, configure, migrate, publish AdminLTE assets and build the frontend
composer setup          # copies .env.example → .env if it doesn't exist yet

# 3. Seed reference data, the first admin, a 20-member demo network and a
#    demo electronics catalog (19 products with photos)
php artisan db:seed

# 4. Run it
composer dev            # server + queue worker + logs + Vite
```

| Area        | URL                               | Seeded login                                                                            |
| ----------- | --------------------------------- | --------------------------------------------------------------------------------------- |
| Member app  | http://localhost:8000/login       | `test@example.com` / `password`                                                         |
| Admin panel | http://localhost:8000/admin/login | `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env` (default `admin@example.com` / `password`) |

On Laragon, `mysqldump`/`mysql` aren't on `PATH`: set
`DB_DUMP_BINARY_PATH="C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/"` in `.env`
for backups and the backup tests.

### Running tests

```bash
npm run build            # Inertia page tests need the Vite manifest
php artisan test         # PHPUnit, uses the binary_system_test MySQL database (see phpunit.xml)
composer test            # Pint + PHPStan (level 7) + tests
php artisan test --group=rule-6   # only the tests for business rule #6
```

This project deliberately runs no jobs on GitHub (no Actions CI). Run
`composer ci:check` before every push: it runs the frontend lint and type
checks, Pint, PHPStan and the full suite. For a coverage report, install PCOV
or Xdebug locally and run `php artisan test --coverage`.

A 1,000+ member network for performance checks:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=LoadTestNetworkSeeder   # LOAD_TEST_MEMBERS, default 1000
php artisan commission:run                           # ~15 s at 1,020 members
```

## Code layout

| Path                         | What goes there                                                                                                |
| ---------------------------- | -------------------------------------------------------------------------------------------------------------- |
| `app/Services`               | Business logic (placement, matching, wallet, withdrawals, fraud scan, backups, health, …)                      |
| `app/Actions`                | Single-purpose actions (Fortify auth actions live in `Actions/Fortify`)                                        |
| `app/Payments`               | Gateway contract and the bKash / SSLCommerz / Nagad / simulator gateways                                       |
| `app/Notifications`          | Member notifications and their channels (`Channels/`: email, BulkSMSBD SMS, Meta WhatsApp, log-only)           |
| `app/Support`                | Framework-agnostic helpers, e.g. `Money`, `PhoneNumber`                                                        |
| `app/Enums`                  | Enums, e.g. `AdminPermission`, statuses                                                                        |
| `app/Http/Controllers/Admin` | Admin panel (Blade) controllers                                                                                |
| `routes/admin.php`           | Admin routes. Registered in `bootstrap/app.php` with the `/admin` prefix, `admin.` names and the `admin` guard |
| `routes/console.php`         | The schedule (see [Operations](#operations))                                                                   |
| `resources/js/pages`         | Member-facing Inertia pages                                                                                    |
| `resources/views/admin`      | Admin panel Blade views (extend `adminlte::page`)                                                              |
| `deploy/`                    | Deploy and rollback scripts, nginx, supervisor and cron configs                                                |

Keep controllers thin. Validate with Form Requests, authorize with Policies
and permissions, and put business logic in Services or Actions. The
decisions every phase follows are recorded in [`CLAUDE.md`](CLAUDE.md).

## Business rules, money and permissions

- **Business rules:** the numbered list in [`CLAUDE.md`](CLAUDE.md) (the
  Phase 0 project context). Changing any of them needs sign-off. The build
  plan is in [`binary-business-prompt-series.md`](binary-business-prompt-series.md).
- **Money** is integer poysha (৳1 = 100 poysha) in `bigInteger` columns. BV
  uses the same ×100 scale and rates are basis points (`1000` = 10%). Use
  `App\Support\Money` (`percentOf`, `fromTaka`, `bpsFromPercent`, `format`),
  never floats.
- **Business configuration** lives in the database, not `.env`, and is
  edited in the admin panel under _Settings_. Every change is audit-logged:
    - _Business rules_ (`/admin/settings`): commission and referral rates,
      caps, cap overflow, carry-forward, minimum withdrawal.
    - _Packages_ (`/admin/packages`): price, BV, cost of goods, qualifying,
      on sale, and the product photo shown in the shop on the home page.
      Changes apply to new orders only; packages are deactivated, never
      deleted.
    - _Ranks & bonuses_ (`/admin/ranks`): rank thresholds and bonuses (each
      rank must ask at least as much as the one below; names are fixed),
      plus leadership and sales bonus rules.
    - _Admins & roles_ (`/admin/admins`): admin accounts, their roles, and
      what each role may do.
- **Shop catalog** (_Shop catalog_ menu, `manage-catalog`): categories and
  products with photos, prices, an optional "was" price, key features and a
  featured flag. The public shop (`/`, `/shop`, `/shop/{product}`) shows
  them by category. Products are bought through packages: each package's
  form lists what's inside, and every product page lists the packages that
  include it.
- **Members** use the `web` guard (`App\Models\User`, role `member`).
  **Admins** use the `admin` guard (`App\Models\Admin`) at `/admin/login`.
  Every admin can change their own password under _My account_.
- **Admin permissions** (`App\Enums\AdminPermission`): `manage-members`,
  `manage-tree`, `manage-sales`, `manage-withdrawals`, `manage-kyc`,
  `manage-settings`, `view-reports`, `send-announcements`, `manage-admins`,
  `manage-catalog`.
    - The `admin` role always holds all of them. `support` and `finance` are
      seeded examples; edit them or add roles at `/admin/admins`.
    - No change can lock the panel out: admins can't deactivate or re-role
      themselves, and at least one active admin always keeps
      `manage-admins`.
    - `RolesAndPermissionsSeeder` is idempotent and runs on every deploy.

## Production

### What runs where

```
nginx ──► PHP-FPM ──► /var/www/binary-system/current/public   (member app + /admin)
cron (every minute) ──► php artisan schedule:run              (commission, ranks, backups, heartbeats)
supervisor ──► 2 × php artisan queue:work                     (notifications, refunds, broadcasts)
MySQL 8 · Redis (cache, sessions, queue)
```

`current` is a symlink to the live release. Each deploy builds a new release
next to it and switches the symlink only once everything succeeded.

| Path                                    | Contents                                                 |
| --------------------------------------- | -------------------------------------------------------- |
| `/var/www/binary-system/releases/<ts>/` | one directory per deploy (last 5 kept, for rollback)     |
| `/var/www/binary-system/shared/.env`    | production settings (never in git)                       |
| `/var/www/binary-system/shared/storage` | uploads (KYC documents), logs, sessions, backup archives |
| `/var/www/binary-system/current`        | symlink → the live release                               |

### One-time server setup (Ubuntu 24.04)

1. **Packages:**

    ```bash
    sudo apt install nginx mysql-server redis-server supervisor git unzip certbot python3-certbot-nginx \
      php8.3-fpm php8.3-cli php8.3-mysql php8.3-bcmath php8.3-gd php8.3-intl php8.3-mbstring \
      php8.3-xml php8.3-curl php8.3-zip php8.3-redis
    ```

    Then install Composer 2 and Node 22 (NodeSource).

2. **Deploy user.** Run PHP-FPM as this user too, so the web server, cron
   and the queue workers share file ownership: in
   `/etc/php/8.3/fpm/pool.d/www.conf` set `user = deploy`, `group = deploy`,
   keeping `listen.owner = www-data`.

    ```bash
    sudo adduser --disabled-password --gecos "" deploy
    sudo mkdir -p /var/www/binary-system && sudo chown deploy:deploy /var/www/binary-system
    ```

3. **Database:**

    ```sql
    CREATE DATABASE binary_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER 'binary'@'localhost' IDENTIFIED BY '<strong password>';
    GRANT ALL ON binary_system.* TO 'binary'@'localhost';
    -- Only on staging, or wherever the weekly restore test runs:
    GRANT ALL ON binary_system_restore_check.* TO 'binary'@'localhost';
    ```

4. **Repository access.** As `deploy`, run `ssh-keygen -t ed25519`. Add
   `~/.ssh/id_ed25519.pub` to GitHub as a **read-only deploy key**.

5. **Settings.** Copy `.env.example` to `/var/www/binary-system/shared/.env`
   and fill it in using the [checklist](#production-env-checklist). For
   `APP_KEY`, run
   `php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"`.
   Then `chmod 600` the file.

6. **First deploy** (see [Deploying](#deploying)):

    ```bash
    APP_DIR=/var/www/binary-system bash deploy/deploy.sh main
    ```

    This runs the migrations and seeds reference data and the first admin
    (`ADMIN_EMAIL` / `ADMIN_PASSWORD`). Sign in at `/admin`, change that
    password straight away and turn on two-factor sign-in under My account.

7. **Web server + SSL:** copy `deploy/nginx.conf` to
   `/etc/nginx/sites-available/binary-system`, replace `example.com`, and
   enable the site. Then:

    ```bash
    sudo certbot --nginx -d example.com -d www.example.com   # certificate + auto-renewal
    sudo nginx -t && sudo systemctl reload nginx
    ```

8. **Queue workers:** copy `deploy/supervisor.conf` to
   `/etc/supervisor/conf.d/binary-system.conf`, then
   `sudo supervisorctl reread && sudo supervisorctl update`.

9. **Cron:** copy `deploy/cron` to `/etc/cron.d/binary-system` (owner root,
   mode 0644).

10. **Let deploys reload PHP-FPM** (this resets OPcache). Run
    `sudo visudo -f /etc/sudoers.d/deploy` and add:

    ```
    deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm
    ```

11. **Verify, then turn on worker monitoring:**

    ```bash
    cd /var/www/binary-system/current
    php artisan schedule:list          # the jobs in "Operations" below
    sudo supervisorctl status          # two RUNNING workers
    curl -fsS https://example.com/up   # 200
    ```

    Wait 5 minutes for the heartbeats, then check `/admin/health` shows
    everything green. Set `HEALTH_REQUIRE_WORKERS=true` in `shared/.env` and
    run `php artisan optimize`. From then on `/up` fails if cron or the
    workers stop.

12. **Monitoring:** point an uptime monitor (UptimeRobot, Better Stack, …)
    at `https://example.com/up`. Create a Sentry project and put its DSN in
    `SENTRY_LARAVEL_DSN`.

After any later edit to `shared/.env`, run `php artisan optimize` in
`current` (config is cached), then `php artisan queue:restart`.

### Production `.env` checklist

| Setting                                               | Production value                                                                                   |
| ----------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| `APP_ENV` / `APP_DEBUG`                               | `production` / `false`                                                                             |
| `APP_URL`                                             | `https://your-domain` (used in emails and payment callbacks)                                       |
| `APP_KEY`                                             | generated once (step 5), never changed: it decrypts sessions and encrypted data                    |
| `APP_TIMEZONE`                                        | `Asia/Dhaka`. The commission day, caps, reports and schedule follow it                             |
| `LOG_STACK` / `LOG_LEVEL`                             | `daily` / `warning` (`LOG_DAILY_DAYS=14`)                                                          |
| `DB_*`                                                | the `binary` user from step 3                                                                      |
| `CACHE_STORE` / `SESSION_DRIVER` / `QUEUE_CONNECTION` | `redis` / `redis` / `redis` (plus `REDIS_*`)                                                       |
| `SESSION_SECURE_COOKIE` / `SESSION_ENCRYPT`           | `true` / `true`                                                                                    |
| `MAIL_*`                                              | a real SMTP/API mailer. Notifications and password resets go out by email                          |
| `PAYMENT_SIMULATOR`                                   | `false` (the simulator is also blocked in code whenever `APP_ENV=production`)                      |
| `PAYMENT_GATEWAYS`                                    | the gateways you have live merchant accounts for                                                   |
| `BKASH_*`                                             | `BKASH_SANDBOX=false`, live base URL and credentials from bKash                                    |
| `SSLCOMMERZ_*`                                        | `SSLCOMMERZ_SANDBOX=false`, `https://securepay.sslcommerz.com`, live store                         |
| `NAGAD_*`                                             | `NAGAD_SANDBOX=false`, live base URL, merchant ID/number, key pair                                 |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD`                      | the first admin's login. Change the password after the first sign-in                               |
| `ADMIN_REQUIRE_TWO_FACTOR`                            | `true`: each admin must turn on two-factor sign-in (My account) before working                     |
| `NOTIFY_MAIL` / `NOTIFY_SMS` / `NOTIFY_WHATSAPP`      | `true`; SMS/WhatsApp `true` once their gateway below is set up and tested                          |
| `SMS_DRIVER` / `BULKSMSBD_*`                          | `bulksmsbd`, API key and approved sender ID (see [Text messages](#text-messages-sms-and-whatsapp)) |
| `WHATSAPP_DRIVER` / `WHATSAPP_*`                      | `meta`, phone number ID, permanent token, approved template                                        |
| `BACKUP_DISKS`                                        | `backups,s3` for an offsite copy (set `AWS_*`), or at least `backups`                              |
| `BACKUP_ARCHIVE_PASSWORD`                             | long random string, stored outside the server (the archives hold KYC documents)                    |
| `BACKUP_NOTIFICATION_EMAIL`                           | who gets told when a backup fails                                                                  |
| `BACKUP_VERIFY_RESTORE`                               | `true` on staging (weekly restore test)                                                            |
| `HEALTH_REQUIRE_WORKERS`                              | `true` once cron and workers run (step 11)                                                         |
| `SENTRY_LARAVEL_DSN` / `SENTRY_ENVIRONMENT`           | your Sentry project DSN / `production`                                                             |
| `SUPPORT_PHONE` / `_EMAIL` / `_ADDRESS` / `_HOURS`    | shown in the shop footer; leave blank to hide                                                      |

Confirm the live gateway base URLs with each provider's merchant
documentation before launch. Test one real low-value payment per gateway.

### Text messages (SMS and WhatsApp)

Urgent member notifications (activation, withdrawals, password change,
new-device sign-in, announcements marked for SMS/WhatsApp) can also go by
text. Each channel needs its switch (`NOTIFY_SMS` / `NOTIFY_WHATSAPP`) and a
driver; with the default `log` driver the message is only written to the log.

**SMS — BulkSMSBD** (`SMS_DRIVER=bulksmsbd`)

1. In the BulkSMSBD panel, copy the API key and get a sender ID approved.
2. Whitelist the server's public IP there (otherwise every send fails with
   code 1032).
3. Set `BULKSMSBD_API_KEY` and `BULKSMSBD_SENDER_ID`, then `NOTIFY_SMS=true`.

**WhatsApp — Meta Cloud API** (`WHATSAPP_DRIVER=meta`)

1. In Meta Business Manager, add a WhatsApp Business phone number and create
   a system user with a permanent access token (`whatsapp_business_messaging`).
2. In WhatsApp Manager create a **utility** template named as in
   `WHATSAPP_TEMPLATE` (default `account_update`), with a body that has
   exactly two values — e.g. `*{{1}}*` on one line and `{{2}}` below — in
   **Bengali (`bn`)** and **English (`en`)**. Wait for both to be approved.
3. Set `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`, then
   `NOTIFY_WHATSAPP=true`.

Both run in the queue. Timeouts, provider errors and rate limits are retried
by the worker (3 tries); permanent refusals (bad number, low balance,
missing template, expired token) are logged as errors — keep an eye on the
log or Sentry after switching a channel on, and send yourself a test
(changing your own member password sends an urgent notification).

### Deploying

Deploys are run by hand; nothing deploys automatically. Push to GitHub
first: the server builds from the repository, not from your working copy.
Then, from your machine (Git Bash works on Windows):

```bash
ssh deploy@example.com "APP_DIR=/var/www/binary-system bash -s -- main" < deploy/deploy.sh
curl -fsS https://example.com/up && echo OK
```

Replace `main` with a tag or commit to deploy something else. On the server
itself, `APP_DIR=/var/www/binary-system bash deploy.sh <ref>` does the same.

What `deploy/deploy.sh` does:

1. Builds a new release from `<ref>`: `composer install --no-dev`, the
   AdminLTE assets, `npm ci && npm run build`, `storage:link`, and
   `php artisan optimize`.
2. **If migrations are pending**, puts the site into maintenance mode, runs
   them, and takes it out again after the switch. Otherwise there is no
   downtime at all.
3. Seeds reference data (idempotent; admin edits are kept).
4. Switches `current` atomically, reloads PHP-FPM and runs `queue:restart`.
5. Prunes all but the last 5 releases.

If anything fails, `current` is not switched and the site keeps running the
old release.

**Rollback:**
`ssh deploy@example.com "APP_DIR=/var/www/binary-system bash -s" < deploy/rollback.sh`
switches back to the previous release. It rolls back code only, never the
database. If the bad release ran a migration the old code can't handle,
[restore the pre-deploy backup](#restoring-a-backup).

## Operations

### The nightly cycle (cron → `php artisan schedule:run`)

All times are `APP_TIMEZONE` (Asia/Dhaka). `php artisan schedule:list`
shows the live list.

| When          | Job                     | What it does                                                                                                                                                                                                                                                                                                                            |
| ------------- | ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 00:15         | `commission:run`        | Binary matching for **yesterday** (or the past week if `COMMISSION_CYCLE=weekly`). Per member: matched = min(left, right) BV, commission = matched × rate, capped by the daily/weekly/monthly caps (the excess is voided or deferred, per settings). The unmatched side carries forward or is flushed, per settings. Pays into wallets. |
| 00:45         | `ranks:evaluate`        | Promotes members whose personal sales, team sales and active team meet a rank's thresholds; pays each rank bonus once. Also pays leadership and sales threshold bonuses.                                                                                                                                                                |
| hourly        | `fraud:scan`            | Flags rapid withdrawals and withdrawals above earned income for review at `/admin/fraud`. Never blocks.                                                                                                                                                                                                                                 |
| 01:30         | `backup:clean`          | Prunes old backup archives.                                                                                                                                                                                                                                                                                                             |
| 02:00         | `backup:run`            | Database dump + `storage/app` (KYC uploads), zipped (encrypted if `BACKUP_ARCHIVE_PASSWORD` is set) onto `BACKUP_DISKS`.                                                                                                                                                                                                                |
| 03:00         | `backup:monitor`        | Emails `BACKUP_NOTIFICATION_EMAIL` if the newest backup is missing or older than a day.                                                                                                                                                                                                                                                 |
| Fri 04:00     | `backup:verify-restore` | Only with `BACKUP_VERIFY_RESTORE=true`. See [Backups](#backups).                                                                                                                                                                                                                                                                        |
| every min / 5 | heartbeats              | Prove cron and a queue worker are alive (read by `/up` and `/admin/health`).                                                                                                                                                                                                                                                            |

Every job is idempotent and `onOneServer`. Running `commission:run` twice for
one date pays nothing twice, and an interrupted run resumes where it
stopped. To re-run a missed day by hand: `php artisan commission:run 2026-10-14`.
Run missed days oldest first; the command refuses a date before an
already-closed cycle.

### Queue workers

The workers (supervisor, `deploy/supervisor.conf`) handle:

- every member notification (email and in-app, plus SMS/WhatsApp once enabled)
- sale refund reversals
- announcement broadcasts
- the queue heartbeat

Failed jobs are listed by `php artisan queue:failed`. Retry them with
`php artisan queue:retry all`. `/admin/health` shows how many have failed.

### Monitoring

- **`GET /up`** returns 500 when the database or cache is unreachable. With
  `HEALTH_REQUIRE_WORKERS=true`, it also fails when cron has been silent
  for 3 minutes or the queue worker for 15. Point an uptime monitor at it.
- **`/admin/health`** (`manage-settings`) shows those checks plus failed
  jobs, the last closed commission cycle, the last backup and the last
  restore test.
- **Sentry** captures unhandled exceptions once `SENTRY_LARAVEL_DSN` is set.
  Request bodies and personal data are never sent.
- **Logs** are in `shared/storage/logs/`: `laravel-YYYY-MM-DD.log` and
  `worker.log`.

### Backups

`backup:run` stores the database dump (a consistent InnoDB snapshot via
`--single-transaction`) and `storage/app` (KYC documents and other uploads).
Code is not backed up: every release is rebuilt from git.

`php artisan backup:verify-restore` proves a backup can actually be restored.
It:

1. takes the newest archive and imports its dump into a scratch database,
   `<database>_restore_check`
2. checks the restored data: the schema is complete, key tables have rows,
   every wallet balance equals its ledger, and the tree placements agree
3. drops the scratch database

It exits non-zero on any failure, and its result appears on
`/admin/health`. Run it on staging (weekly via `BACKUP_VERIFY_RESTORE=true`)
and by hand after changing anything about backups. It needs the `mysql`
client and the `GRANT` from setup step 3.

### Restoring a backup

1. Put the site into maintenance: `php artisan down` in `current`.
2. Download the archive from the backup disk and unzip it. Use
   `BACKUP_ARCHIVE_PASSWORD` if it is encrypted.
3. Import the database:
   `gunzip -c db-dumps/mysql-binary_system.sql.gz | mysql -u binary -p binary_system`.
4. Restore uploads: copy the archive's `storage/app/` over
   `shared/storage/app/`.
5. `php artisan up`. Then check `/admin/health` and a member's wallet.

Commissions paid between the backup and the restore are gone from the
database. Reconcile with the gateway and bank statements before paying
withdrawals again.
