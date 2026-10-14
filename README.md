# Enterprise ERP

A multi-company ERP: **Laravel 13 REST API** + **Next.js 16 (React 19, TypeScript, SCSS)** frontend, on **MySQL 8**.

Modules: company & branches · users, roles & permissions · HR (employees, attendance, leave, shifts, performance, documents) · payroll · finance (chart of accounts, double-entry journals, AR/AP, bank, tax, periods, financial statements) · sales & CRM · procurement · inventory & warehouses · projects & tasks · documents · notifications & approvals · reports & exports · audit log · platform administration.

```
enterprise-erp/
  backend/      Laravel API (/api/v1), Sanctum cookie auth, MySQL
  frontend/     Next.js app (App Router, SCSS modules + design tokens, no Tailwind)
  database/     Local MySQL helper scripts (no XAMPP required)
  docs/         Architecture, database setup, security, API
  deployment/   Production notes & templates
```

## Quick start (Windows, local MySQL)

Prerequisites — see [docs/SETUP.md](docs/SETUP.md) for details:
PHP 8.3+ (pdo_mysql, mbstring, openssl, intl, zip, bcmath, fileinfo, gd), Composer 2, Node 20+, MySQL 8.4.

```powershell
# 1. Database (one time)
.\database\local\init.ps1
.\database\local\start.ps1 -Background
.\database\local\create-app-db.ps1          # prints DB_* lines for backend/.env

# 2. Backend
cd backend
composer install
copy .env.example .env                      # paste the DB_* lines
php artisan key:generate
php artisan db:check                        # connection diagnostics
php artisan migrate --seed                  # schema + permission catalogue
php artisan db:selftest                     # CRUD / FK / transaction round-trip
php artisan db:seed --class=DemoSeeder      # OPTIONAL demo companies (dev only)
php artisan serve                           # http://localhost:8000

# 3. Frontend (new terminal)
cd frontend
npm install
copy .env.example .env.local
npm run dev                                 # http://localhost:3000
```

The demo seeder prints the shared demo password and one account per role
(`owner@northwind.test`, `accountant@northwind.test`, …, `platform@erp.test`).
Demo companies are flagged `is_demo` and the seeder refuses to run in production.

For production, create the first administrator explicitly:

```bash
ERP_ALLOW_ADMIN_BOOTSTRAP=true php artisan erp:create-admin --force   # prompts for details
```

## Hosted database

Prefer not to run MySQL locally? Use a managed MySQL such as Aiven (free tier: 1 GB) —
see [docs/DATABASE_SETUP.md](docs/DATABASE_SETUP.md) for TLS, IP allow-listing and verification.

## Tests

```bash
cd backend && php artisan test            # PHPUnit against MySQL database erp_test
cd frontend && npm run lint && npx tsc --noEmit && npm run build
```

## Documentation

| | |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Tenancy, auth, RBAC, API conventions, accounting & inventory invariants |
| [docs/DATABASE_SETUP.md](docs/DATABASE_SETUP.md) | Local and hosted MySQL, verification, troubleshooting, backups |
| [docs/SETUP.md](docs/SETUP.md) | Runtime installation, environment variables, CORS/cookies |
| [docs/SECURITY.md](docs/SECURITY.md) | Security controls and production checklist |
| [docs/MODULE_CONVENTIONS.md](docs/MODULE_CONVENTIONS.md) | How to add a backend module |
| [docs/api/openapi.yaml](docs/api/openapi.yaml) | API reference |
