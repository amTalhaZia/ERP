# Enterprise ERP — Architecture

This document is the contract every module follows. Read it before adding a
module to either application.

## 1. Applications

```
enterprise-erp/
  frontend/     Next.js 16 (App Router, TypeScript strict, SCSS modules)
  backend/      Laravel 12 REST API (/api/v1), Sanctum SPA cookie auth
  database/     Local MySQL helper scripts (dev only, no XAMPP)
  docs/         Architecture, setup, API, security, operations
  deployment/   Production notes, env templates, nginx/Caddy examples
```

Frontend and backend are independently runnable. The frontend never holds
database credentials or privileged logic; it only talks to `/api/v1`.

## 2. Authentication model

* **Laravel Sanctum SPA (stateful cookie) auth.** The browser receives an
  `HttpOnly`, `SameSite=Lax`, `Secure` (prod) session cookie. Nothing secret is
  stored in `localStorage`.
* CSRF: the SPA calls `GET /sanctum/csrf-cookie` first; every mutating request
  sends the `X-XSRF-TOKEN` header (Axios does this automatically).
* CORS: `config/cors.php` allows only `FRONTEND_URL`, `supports_credentials =
  true`. `SANCTUM_STATEFUL_DOMAINS` and `SESSION_DOMAIN` must match the
  frontend host.
* Rate limiting: `throttle:login` (5/min per email+IP) on `/auth/login`,
  `/auth/forgot-password`, `/auth/reset-password`, `/auth/2fa/*`.
* Password change / reset → all other sessions for the user are invalidated.
* Login, logout, failed login, password reset, 2FA events → `audit_logs`.

## 3. Tenancy (multi-company)

* `companies` is the tenant root. Every tenant-owned table has a
  `company_id` FK (indexed) — directly, never only via parent.
* **Trusted company context** is resolved server-side in
  `ResolveCompanyContext` middleware:
  1. Read `X-Company-Id` header (or `company_id` query for downloads).
  2. Verify the authenticated user has an *active* `company_user` membership.
  3. Store the `Company` on the request container (`app('tenant')`).
  4. Reject with `403 tenant.forbidden` otherwise. Platform admins may
     impersonate a company only via an explicit, audited switch.
* `BelongsToCompany` trait adds a global scope `where company_id = tenant`
  and auto-fills `company_id` on create. **Every tenant model uses it.**
* Related-record validation: `SameCompany` rule (and `TenantExists` rule)
  checks that any referenced id (customer_id, warehouse_id, …) belongs to the
  current tenant. Never trust an id from the client.
* Composite uniques are tenant-scoped, e.g. `UNIQUE (company_id, code)`.
* Policies receive the tenant and the user; every controller action calls
  `authorize()`. Tests in `tests/Feature/Tenancy` assert Company A cannot read,
  update, delete or export Company B records for every module.
* Branch scoping: `branch_id` nullable on operational tables; users may have
  `company_user.branch_ids` restriction (NULL-branch rows stay visible) enforced
  by `App\Support\Tenancy\RestrictsToAllowedBranches`: lists call
  `->visibleBranches()`, policies check it via `canOn()` / `inAllowedBranch()`,
  and inputs use `AccessibleBranch` / `WithinAccessibleBranch`. Models without a
  `branch_id` derive it from a parent (attendance/leave → employee, sales order →
  warehouse, stock → warehouse, GRN/return → purchase order).
* Privilege escalation: only `company.owner` holders may assign/remove the owner
  role, assign roles beyond their own permissions, edit a role they hold, or
  grant permissions they lack (`App\Support\Access\PrivilegeEscalationGuard`,
  403 `access.escalation_denied`).

## 4. Authorization (RBAC)

* Tables: `roles` (company_id nullable → platform roles), `permissions`
  (global catalog, `module.action` slugs), `role_permission`, `role_user`
  (scoped by company_id).
* `User::hasPermission('invoices.approve')` resolves against the *current
  tenant* and caches per-request.
* Platform roles (`platform.owner`, `platform.admin`) have `company_id = NULL`
  and gate `/api/v1/platform/*`. Company roles never reach platform routes.
* Enforcement lives in Policies + `permission:` route middleware. Hiding a
  button is never the only check.
* Sensitive actions (payroll.process, invoices.approve, users.manage,
  reports.export, inventory.adjust, journal posting) → audit log entry.

## 5. API conventions

* Base: `/api/v1`. Resource-oriented, plural nouns. Verbs as sub-POSTs
  (`/invoices/{id}/approve`).
* Envelope (success):
  ```json
  { "data": …, "meta": { "pagination": { "page", "per_page", "total", "last_page" } } }
  ```
* Envelope (error):
  ```json
  { "message": "Human readable", "code": "validation.failed", "errors": { "field": ["…"] } }
  ```
* Status codes: 200/201/204, 401 unauthenticated, 403 forbidden (also for
  cross-tenant — never 404-leak vs 403 inconsistently: cross-tenant ids return
  **404** so existence is not disclosed), 404, 409 state conflict, 422
  validation, 429 throttled, 500 generic (no stack traces when
  `APP_DEBUG=false`).
* List params: `page`, `per_page` (≤100), `search`, `sort` (`-created_at`),
  `filter[status]=…`, `from`, `to`, `branch_id`.
* Dates in/out: ISO‑8601 UTC. Money: strings of `DECIMAL(18,4)`, currency
  code alongside. Never floats.
* OpenAPI 3.1 spec generated to `docs/api/openapi.yaml`.

## 6. Backend code layout

```
app/
  Http/Controllers/Api/V1/<Module>/…Controller.php   thin: validate→authorize→action→resource
  Http/Requests/<Module>/Store…Request.php          validation + SameCompany rules
  Http/Resources/<Module>/…Resource.php             output shaping
  Actions/<Module>/…Action.php                      single business operation, DB::transaction
  Services/<Module>/…Service.php                    cross-action orchestration (posting, payroll calc)
  Models/<Module>/…                                 BelongsToCompany, casts, relations
  Policies/<Module>/…Policy.php
  Support/Tenancy/  (middleware, trait, rules, scopes)
  Support/Audit/    (Auditable trait, AuditLogger)
  Support/Money/    (Money value object, bcmath)
```

## 7. Accounting invariants

* Posted journal entries are immutable; corrections are reversing entries.
* `sum(debit) == sum(credit)` enforced in `PostJournalEntryAction` inside a
  transaction; DB CHECK on lines `debit >= 0 AND credit >= 0 AND NOT (debit>0 AND credit>0)`.
* Sub-ledgers (invoices, bills, payments) create journal entries via services;
  reports read the GL, not the sub-ledgers.
* Financial periods can be closed; posting into closed period → 409.

## 8. Inventory invariants

* `products.quantity_on_hand` is **derived**; the source of truth is
  `stock_movements` (qty signed, type, reference). Every change goes through
  `RecordStockMovementAction` which updates `stock_levels` in the same
  transaction with row locks.
* Valuation: weighted average cost per (product, warehouse); documented
  limitation: FIFO not implemented.

## 9. Frontend layout

```
src/
  app/(auth)/login, forgot-password, reset-password
  app/(app)/dashboard, employees, payroll, finance/…, sales/…, …   route groups
  components/ui/        Button, Input, Select, Modal, Drawer, Badge, Table, …
  components/layout/    Sidebar, Topbar, Breadcrumbs, CompanySwitcher, NotificationCenter
  components/charts/    Recharts wrappers using tokens
  features/<module>/    api.ts (typed client), schemas.ts (zod), hooks, views
  lib/api/              axios instance, csrf bootstrap, error normaliser
  lib/auth/             session provider, permission hooks (`useCan`)
  store/                Redux Toolkit: session, tenant, ui (theme, sidebar)
  styles/               tokens (_colors, _spacing, _type), themes, globals
```

* Styling: SCSS modules + CSS variables; **no Tailwind**. Tokens live in
  `styles/tokens/*.scss` and are exported as CSS custom properties on
  `:root`, `[data-theme="dark"]`, `[data-contrast="high"]`.
* Server-side pagination everywhere; TanStack Table in `manualPagination`.
* Permission-aware nav: `navigation.ts` declares `permission` per item;
  `useCan()` filters. Route guards re-check on the server via API 403 handling.
* Motion: Framer Motion with `useReducedMotion()`; durations ≤ 250 ms.

## 10. Timezone & money

* DB and API are UTC. Company has `timezone` and `currency`; the UI formats
  with `Intl` using those settings.
* Money arithmetic on the backend uses `bcmath` via `Support\Money`.

## 11. Audit logs

`audit_logs(id, company_id, actor_id, action, entity_type, entity_id,
old_values json, new_values json, ip, user_agent, created_at)`. Append-only:
no update/delete routes, DB user for the app lacks UPDATE/DELETE on this table
in production (documented in `docs/SECURITY.md`). Secrets are redacted by
`AuditLogger::redact()`.
