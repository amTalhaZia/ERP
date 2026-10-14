# Production deployment

Reference setup: one Linux VM (Ubuntu 24.04) running **nginx**, **PHP 8.3-FPM**, **Node 20+** (for the Next.js server), a **supervisor**-managed queue worker, and a managed **MySQL 8** (e.g. Aiven — see `docs/DATABASE_SETUP.md`). Adapt freely; the important parts are the environment and the security checklist in `docs/SECURITY.md`.

```
erp.example.com      → Next.js (node, port 3000, behind nginx)
api.example.com      → Laravel (php-fpm, behind nginx)
```
Both hosts must share the parent domain so the session cookie (`SESSION_DOMAIN=.example.com`) reaches the API.

## 1. Backend

```bash
git clone <repo> /srv/erp && cd /srv/erp/backend
composer install --no-dev --optimize-autoloader
cp ../deployment/backend.env.production.example .env   # fill in every value
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force                 # permission catalogue only — no demo data
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan view:cache
php artisan db:check && php artisan db:selftest

# First administrator (prompts for name/email/password; then set the flag back to false)
ERP_ALLOW_ADMIN_BOOTSTRAP=true php artisan erp:create-admin --force
```

File permissions: `storage/` and `bootstrap/cache/` writable by the php-fpm user; everything else read-only.

## 2. Frontend

```bash
cd /srv/erp/frontend
npm ci
echo "NEXT_PUBLIC_API_URL=https://api.example.com" > .env.production
npm run build
# run with the supervisor program below (next start -p 3000)
```

## 3. Processes
- `deployment/nginx/erp.conf` — both virtual hosts, TLS, security headers, upload size.
- `deployment/supervisor/erp.conf` — queue worker (exports, emails, notifications) + Next.js server.
- `deployment/cron` — Laravel scheduler (low-stock alerts, retention, export cleanup).

## 4. Updates
```bash
cd /srv/erp && git pull
cd backend && composer install --no-dev -o && php artisan migrate --force && php artisan optimize:clear && php artisan config:cache route:cache event:cache view:cache
cd ../frontend && npm ci && npm run build
sudo supervisorctl restart erp-queue erp-web
```
Migrations are forward-only in production: never `migrate:fresh` or roll back a production database to fix a problem — write a corrective migration.

## 5. Backups
Nightly logical backup (keep 14 days, copy off-site, encrypt at rest):
```bash
mysqldump --single-transaction --routines --triggers --ssl-ca=/srv/erp/backend/storage/certs/ca.pem \
  -h "$DB_HOST" -P "$DB_PORT" -u "$DB_BACKUP_USER" -p"$DB_BACKUP_PASSWORD" erp | gzip > /backups/erp-$(date +%F).sql.gz
```
Also back up `backend/storage/app/private` (documents, receipts, contracts) — it is not in the database.
**Restore drill (quarterly):** restore into a scratch database, point a staging `.env` at it, run `php artisan migrate --force` and `php artisan db:check`.

## 6. Monitoring
- Laravel logs: `storage/logs/laravel.log` (set `LOG_CHANNEL=daily` or ship to your log platform; `LOG_LEVEL=warning`).
- Health: `GET https://api.example.com/up` (framework) and `/api/v1/health/db` (database, no secrets) for uptime checks.
- Failed jobs: `php artisan queue:failed`.
