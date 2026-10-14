# Database Setup (no XAMPP required)

The backend needs a MySQL 8.x-compatible server. Two supported paths:

| | Option A — Hosted (Aiven) | Option B — Local MySQL 8.4 |
|---|---|---|
| Install anything? | No | MySQL Community Server (≈ 300 MB) |
| Works offline | No | Yes |
| Cost | Free tier (1 GB) / paid | Free |
| TLS | Required | Optional (localhost) |
| Best for | Demo from anywhere, shared team DB | Day-to-day development, tests |

Both are configured purely through `backend/.env`. Nothing database-related
ever reaches the browser.

---

## Option A — Hosted MySQL on Aiven

**Verified 2026‑10:** Aiven offers a free MySQL plan with 1 node, 1 CPU,
1 GB RAM and **1 GB storage** (reduced from 5 GB in May 2025). No credit card.
Limits: no VPC, no static IP, no integrations/forking, one free service of each
type per organisation, and free services are shut off after prolonged
inactivity. Treat it as *development/demo*, not production — Aiven does not
promise the free tier is permanent. Check the current terms at
<https://aiven.io/docs/platform/concepts/free-plan> before relying on it.

### A.1 Create the service
1. Sign up at <https://console.aiven.io>.
2. **Create service → MySQL → Free plan**, pick the region closest to you.
3. Wait until the status is *Running* (2–4 min).

### A.2 Create a dedicated database and user
Aiven gives you an `avnadmin` superuser. Don't run the app as it. In the
service's **Users** tab (or via the MySQL shell using the avnadmin
credentials from the Overview tab):

```sql
CREATE DATABASE erp CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER 'erp_app'@'%' IDENTIFIED BY '<strong-random-password>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES,
      CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW,
      TRIGGER ON erp.* TO 'erp_app'@'%';
FLUSH PRIVILEGES;
```

For production, additionally create a *reporting* user with SELECT only and
revoke `UPDATE, DELETE` on `erp.audit_logs` from the app user (see
`docs/SECURITY.md`).

### A.3 Download the CA certificate
Overview tab → **CA certificate → Download**. Save it as
`backend/storage/certs/aiven-ca.pem` (this folder is git‑ignored).

### A.4 Configure Laravel
In `backend/.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql-xxxx-yyyy.aivencloud.com
DB_PORT=12345
DB_DATABASE=erp
DB_USERNAME=erp_app
DB_PASSWORD=<strong-random-password>
DB_SSL_CA=storage/certs/aiven-ca.pem
DB_SSL_VERIFY=true
```

`config/database.php` reads `DB_SSL_CA` and sets
`PDO::MYSQL_ATTR_SSL_CA` + `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT`. If
`DB_SSL_CA` is empty TLS options are not sent (local use).

### A.5 Restrict network access
Service → **Overview → Allowed IP addresses**: replace `0.0.0.0/0` with your
current public IP(s). Aiven free tier does not support VPC peering, so IP
allow-listing is the only control — update it when your IP changes.

### A.6 Verify
```bash
cd backend
php artisan db:check          # custom command: connects, reports version, TLS cipher
php artisan migrate --force   # creates schema
php artisan db:selftest       # create → read → update → delete round-trip with FK + transaction checks
```
And `GET http://localhost:8000/api/v1/health/db` returns
`{"status":"ok","driver":"mysql","version":"8.0.x","tls":true,"latency_ms":…}`
— never host, user or password.

### A.7 Inspecting data
* Aiven console → **Query editor** (built-in, free tier included).
* **DBeaver** (free): new connection → MySQL → host/port/db/user/password →
  *SSL* tab → tick *Use SSL*, CA certificate = `aiven-ca.pem`.

---

## Option B — Local MySQL 8.4 LTS (lightweight, no XAMPP)

### B.1 Runtimes (install separately)
| Tool | How | Verify |
|---|---|---|
| PHP 8.3 (x64) | `winget install PHP.PHP.8.3` | `php -v` |
| Composer 2 | `winget install Composer.Composer` | `composer -V` |
| MySQL 8.4 LTS | `winget install Oracle.MySQL` | `mysqld --version` |
| Node 20+ | `winget install OpenJS.NodeJS.LTS` | `node -v` |

The MySQL MSI installs binaries to
`C:\Program Files\MySQL\MySQL Server 8.4\bin` but does **not** configure a
server. This project runs MySQL as a plain user process from a project-local
data directory — no Windows service, no admin rights, trivially removable.

### B.2 Initialise and run
```powershell
# one-time: creates database/local/data with a root account that has no password (localhost only)
.\database\local\init.ps1

# start (keeps running in this window; Ctrl+C to stop)
.\database\local\start.ps1

# in another terminal — create app DB + user
.\database\local\create-app-db.ps1
```
`create-app-db.ps1` creates database `erp`, user `erp_app@localhost` with a
generated password, and prints the `.env` lines to paste.

`database/local/my.ini` binds to `127.0.0.1:3306`, enables
`utf8mb4`, strict SQL mode, and `innodb_buffer_pool_size=256M`. Data lives in
`database/local/data/` (git‑ignored).

### B.3 Configure Laravel
```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp
DB_USERNAME=erp_app
DB_PASSWORD=<printed by create-app-db.ps1>
DB_SSL_CA=
```

### B.4 Verify
Same as A.6. All eight verification steps from the brief are covered:

| # | Check | Where |
|---|---|---|
| 1 | Laravel connects | `php artisan db:check`, `/api/v1/health/db` |
| 2 | Migrations create schema | `php artisan migrate`, `tests/Feature/Database/SchemaTest` |
| 3 | Create a record | `db:selftest`, `tests/Feature/Database/RoundTripTest` |
| 4 | Read it back | same |
| 5 | Update/delete respect authorization | `tests/Feature/Tenancy/*` |
| 6 | FK + transaction behaviour | `db:selftest` (FK violation expected; rollback asserted), `tests/Feature/Database/TransactionTest` |
| 7 | Safe health endpoint | `/api/v1/health/db` (no secrets) |
| 8 | Understandable failure messages | `db:check` maps common SQLSTATEs → hints (see below) |

### B.5 Troubleshooting (what `db:check` prints)
| Symptom | Hint printed |
|---|---|
| `SQLSTATE[HY000] [2002]` connection refused | "MySQL is not running or wrong host/port. Local: run database/local/start.ps1" |
| `[1045] Access denied` | "Wrong DB_USERNAME/DB_PASSWORD, or user not allowed from this host" |
| `[1049] Unknown database` | "Database does not exist — run create-app-db.ps1 or CREATE DATABASE" |
| `[2026]/[HY000] SSL connection error` | "TLS required by provider — set DB_SSL_CA to the downloaded CA file" |
| `could not find driver` | "PHP pdo_mysql extension not enabled — see docs/SETUP.md §PHP" |
| `[1044] Access denied for user to database` | "User lacks privileges on this DB — re-run the GRANT" |

### B.6 Inspecting data locally
* **DBeaver** (recommended GUI): `winget install dbeaver.dbeaver`, connect to
  `127.0.0.1:3306` as `erp_app`.
* **MySQL Shell**: `mysqlsh erp_app@127.0.0.1:3306/erp --sql`.
* **phpMyAdmin** (optional): download the zip, point `config.inc.php` at
  `127.0.0.1`, serve with `php -S 127.0.0.1:8080 -t phpmyadmin/`. Only bind to
  localhost; never deploy it alongside production.
* **In-app**: platform administrators get a read-only *System → Database*
  page (`/platform/database`) that lists tables and row counts via the API —
  no SQL console, no credentials.

---

## Backups
* Hosted: Aiven keeps automatic backups on paid plans only (free tier: none —
  take your own). `php artisan backup:run` wraps `mysqldump` with the current
  `.env` (TLS aware) and writes to `storage/app/private/backups/`.
* Local: same command; schedule with Task Scheduler if desired.
* Restore: `mysql --ssl-ca=… erp < backup.sql`, then `php artisan migrate` to
  apply any newer migrations. Never drop a production database to "fix" a
  migration; write a corrective migration instead.
