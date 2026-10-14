# Setup & Configuration

## 1. Runtimes (no XAMPP needed)

| Component | Version | Windows install | Check |
|---|---|---|---|
| PHP | 8.3+ (x64) | `winget install PHP.PHP.8.3` | `php -v` |
| Composer | 2.x | https://getcomposer.org/download (or `php composer-setup.php`) | `composer -V` |
| MySQL | 8.4 LTS | `winget install Oracle.MySQL` | `mysqld --version` |
| Node.js | 20+ | `winget install OpenJS.NodeJS.LTS` | `node -v` |

### PHP extensions
In `php.ini` (copy `php.ini-development` → `php.ini` next to `php.exe`):

```ini
extension_dir = "C:/path/to/php/ext"     ; absolute, forward slashes
extension=bcmath
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=sodium
extension=zip
memory_limit = 512M
upload_max_filesize = 20M
post_max_size = 25M
date.timezone = UTC
```

`php -m` must list all of them. `could not find driver` from `db:check` means `pdo_mysql` is missing.

> **Already have XAMPP?** Its bundled PHP 8.0 / MariaDB 10.4 are too old for Laravel 13.
> You can keep using XAMPP's **phpMyAdmin** to browse the ERP database: point
> `C:\xampp\phpMyAdmin\config.inc.php` at `127.0.0.1:3306` with `auth_type = cookie`,
> and move XAMPP's MariaDB to port 3307 (`C:\xampp\mysql\bin\my.ini`) so the two servers don't clash.

## 2. Backend environment (`backend/.env`)

| Variable | Purpose | Dev | Production |
|---|---|---|---|
| `APP_ENV` / `APP_DEBUG` | | `local` / `true` | `production` / **`false`** |
| `APP_URL` | API origin | `http://localhost:8000` | `https://api.example.com` |
| `FRONTEND_URL` | SPA origin (reset links, CORS) | `http://localhost:3000` | `https://erp.example.com` |
| `CORS_ALLOWED_ORIGINS` | comma list, no wildcards | `http://localhost:3000` | `https://erp.example.com` |
| `SANCTUM_STATEFUL_DOMAINS` | hosts that get cookie auth | `localhost:3000,127.0.0.1:3000` | `erp.example.com` |
| `SESSION_DOMAIN` | cookie domain shared by SPA+API | `localhost` | `.example.com` |
| `SESSION_SECURE_COOKIE` | HTTPS-only cookies | `false` | **`true`** |
| `SESSION_SAME_SITE` | | `lax` | `lax` |
| `SESSION_LIFETIME` | idle minutes before expiry | `120` | `60–120` |
| `AUTH_PASSWORD_TIMEOUT` | seconds a re-confirmation lasts | `900` | `900` |
| `DB_*`, `DB_SSL_CA` | see DATABASE_SETUP.md | | TLS CA for hosted DB |
| `MAIL_*` | password reset / invites / notifications | `MAIL_MAILER=log` | real SMTP/API |
| `QUEUE_CONNECTION` | exports & emails | `database` | `database` or `redis` + a worker |
| `ERP_ALLOW_ADMIN_BOOTSTRAP` | allow `erp:create-admin` in prod | `false` | `true` only once |

With `MAIL_MAILER=log`, reset and invitation emails are written to `storage/logs/laravel.log`.

### How SPA authentication works
1. Frontend calls `GET /sanctum/csrf-cookie` → receives `XSRF-TOKEN` cookie.
2. `POST /api/v1/auth/login` with credentials; Axios echoes the token in `X-XSRF-TOKEN`.
3. Laravel starts a session → `HttpOnly`, `SameSite=Lax` cookie. No token is stored in JS or localStorage.
4. Every API request sends the cookie + `X-Company-Id`; the server verifies membership before any tenant query.

Frontend and API must share a registrable domain in production (e.g. `erp.example.com` + `api.example.com`).

## 3. Frontend environment (`frontend/.env.local`)

| Variable | Purpose |
|---|---|
| `NEXT_PUBLIC_API_URL` | API origin, e.g. `http://localhost:8000` |
| `NEXT_PUBLIC_MAP_TILE_URL` | optional tile server (default OpenStreetMap — light use only, attribution required) |
| `NEXT_PUBLIC_MAP_ATTRIBUTION` | attribution HTML for the custom tile server |

Only `NEXT_PUBLIC_*` values reach the browser — never put secrets there.

## 4. Background work

```bash
php artisan queue:work --tries=3       # exports, emails, notifications
php artisan schedule:work              # dev; in prod a cron: * * * * * php artisan schedule:run
```

Scheduled: `inventory:check-reorder` (daily low-stock alerts), `erp:prune` (retention cleanup).

## 5. Useful commands

| Command | |
|---|---|
| `php artisan db:check` | Connection diagnostics with troubleshooting hints (no secrets) |
| `php artisan db:selftest` | Create/read/update/delete, FK and rollback checks on the live DB (temp tables) |
| `php artisan erp:create-admin` | Create the first platform owner |
| `php artisan erp:prune --dry-run` | Show what retention cleanup would delete |
| `php artisan db:seed --class=DemoSeeder` | Demo companies & users (refused in production) |
