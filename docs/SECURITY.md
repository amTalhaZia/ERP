# Security

## Controls implemented

| Area | Control | Where |
|---|---|---|
| Authentication | Sanctum SPA session cookies (HttpOnly, SameSite=Lax, Secure in prod); no tokens in localStorage | `bootstrap/app.php` (`statefulApi`), `config/session.php` |
| Passwords | bcrypt via `hashed` cast; min 10 chars, letters+numbers, checked against breached-password corpus (`uncompromised`) | `AppServiceProvider` `Password::defaults` |
| Brute force | `throttle:login` 5/min per email+IP and 30/min per IP; 2FA 5/min; reset/forgot 10/min | `AppServiceProvider::configureRateLimiting` |
| Login errors | Identical message for unknown email and wrong password; reset request always returns 200 | `LoginController`, `PasswordController` |
| 2FA | TOTP (RFC 6238) with hashed single-use recovery codes; secret encrypted at rest | `TwoFactorController`, `User` casts |
| Session revocation | Password change/reset deletes other sessions and `logoutOtherDevices` | `PasswordController` |
| Re-authentication | `password.confirm` (15 min) for enabling 2FA / recovery codes | routes, `AUTH_PASSWORD_TIMEOUT` |
| Deactivation | Inactive users are logged out on their next request | `EnsureUserIsActive` |
| CSRF | XSRF-TOKEN cookie + header on all mutating requests | Sanctum |
| CORS | Explicit origin list, credentials, no wildcard | `config/cors.php` |
| Tenant isolation | Server-resolved company context, membership verified per request; global scope on every tenant model; `TenantExists` on every foreign id; cross-tenant ids → 404 | `ResolveCompanyContext`, `BelongsToCompany`, `Rules/*` |
| Authorization | Policies on every controller action + `permission:` route middleware; platform routes 404 for non-platform users | `app/Policies`, `EnsurePermission`, `EnsurePlatformAdmin` |
| Mass assignment | `#[Fillable]` allow-lists; `company_id` forced from context | models |
| SQL injection | Eloquent bindings; sort/filter columns allow-listed | `ApiController` |
| Uploads | MIME + extension + size validation; private disk for sensitive files; streamed downloads behind policies | module controllers, `config/erp.php` |
| Errors | Uniform JSON envelope; no stack traces when `APP_DEBUG=false` | `bootstrap/app.php` |
| Audit | Append-only `audit_logs` with secret redaction and diffs only | `AuditLogger`, `Auditable` |
| Headers | `X-Frame-Options: DENY`, `nosniff`, referrer & permissions policy | `frontend/next.config.ts` |
| Secrets | `.env` only, git-ignored; `config()` reads; nothing secret in `NEXT_PUBLIC_*` | |
| Backdoors | None. No default credentials; demo seeder refuses production; `erp:create-admin` gated | |

## Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, unique `APP_KEY`
- [ ] HTTPS everywhere; `SESSION_SECURE_COOKIE=true`; HSTS at the proxy
- [ ] `CORS_ALLOWED_ORIGINS` / `SANCTUM_STATEFUL_DOMAINS` / `SESSION_DOMAIN` set to real domains
- [ ] Database user without `DROP`/`ALTER` for runtime; separate migration user
- [ ] Revoke `UPDATE, DELETE` on `audit_logs` from the runtime user:
      `REVOKE UPDATE, DELETE ON erp.audit_logs FROM 'erp_app'@'%';`
- [ ] Hosted DB: TLS CA configured (`DB_SSL_CA`) and IP allow-list restricted
- [ ] Mail transport configured (password reset, invites)
- [ ] Queue worker and scheduler running under a supervisor
- [ ] `php artisan config:cache route:cache event:cache`
- [ ] Nightly encrypted backups, tested restore (see DATABASE_SETUP.md)
- [ ] `composer audit` and `npm audit --omit=dev` clean or triaged
- [ ] First admin created with `erp:create-admin`, 2FA enabled, `ERP_ALLOW_ADMIN_BOOTSTRAP` set back to `false`
- [ ] Demo data absent (`companies.is_demo = 0` everywhere)

## Reporting
Security issues: contact the system owner privately; do not open a public issue.
