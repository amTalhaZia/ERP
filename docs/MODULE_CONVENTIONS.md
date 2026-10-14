# Module Conventions (backend)

Read `docs/ARCHITECTURE.md` first. This file is the *operational* checklist
for adding a module to `backend/`. Existing reference implementation:
**Company / Branch / Department** — copy its shape exactly.

| Concern | Reference file |
|---|---|
| Migration (tenant table) | `database/migrations/2026_10_13_190001_create_core_tenancy_tables.php` |
| Model | `app/Models/Branch.php` |
| Policy | `app/Policies/BranchPolicy.php` (+ `Concerns/ChecksPermissions.php`) |
| Form request | `app/Http/Requests/Company/StoreBranchRequest.php`, `StoreDepartmentRequest.php` |
| Resource | `app/Http/Resources/Company/BranchResource.php` |
| Controller | `app/Http/Controllers/Api/V1/Company/BranchController.php` |
| Routes | `routes/api/v1/company.php` (auto-loaded, already inside `auth:sanctum`+`active`+`tenant`) |
| Tests | `tests/Feature/Tenancy/BranchTenantIsolationTest.php`, `tests/Feature/Access/PermissionEnforcementTest.php` |
| Factory | `database/factories/BranchFactory.php`, `UserFactory::inCompany()` |

## Toolchain (Windows, this machine)
```
PHP:      "$LOCALAPPDATA/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe"
Composer: "$LOCALAPPDATA/Programs/composer/composer.phar"  (run via php)
cd /c/Users/Adnan/ERP/backend
$PHP artisan make:migration create_x_table --no-interaction
$PHP artisan migrate                       # dev DB "erp" (MySQL 8.4 on 127.0.0.1:3306)
$PHP vendor/bin/pint --dirty --format agent
$PHP artisan test --compact tests/Feature/<Module>
```
Tests run on real MySQL (`erp_test`), seeded with `PermissionSeeder`. Always use
absolute paths; the shell cwd can reset between commands.

## Rules (non-negotiable)

1. **Tenant tables**: `foreignId('company_id')->constrained()->cascadeOnDelete()` on every
   tenant-owned table, even when the parent already has it. Models `use BelongsToCompany`.
   Tenant-scoped uniqueness is composite: `unique(['company_id', 'code'])`.
2. **Models**: `#[Fillable([...])]` attribute (Laravel 13 style — see `Branch`), `casts()` method,
   typed relationships, `use Auditable` on anything a user edits. Money columns:
   `decimal('amount', 18, 4)` cast `'decimal:4'`; quantities `decimal(18,4)`; rates `decimal(8,4)`.
   Never `float`. Use `App\Support\Money\Money` for arithmetic.
3. **Status fields**: `string('status', 20)` + a PHP backed enum in `app/Enums/<Module>/` with
   TitleCase cases. Index `['company_id', 'status']` when lists filter by it.
4. **Foreign references in requests**: `new TenantExists(Customer::class)` (never `exists:customers,id`).
   Branch ids: `new AccessibleBranch`. Validate that every related id is same-company.
5. **Policies**: use `ChecksPermissions`: `viewAny → can()`, record abilities → `canOn()` (same
   tenant + permission). Register nothing — Laravel auto-discovers `App\Policies\<Model>Policy`.
   Controllers call `Gate::authorize(...)` on **every** action. Business-state refusals
   (e.g. editing a posted invoice) → `409` with `code: conflict`.
6. **Controllers** extend `ApiController`; lists use `applySearch / applyFilters / applySort /
   applyDateRange` with explicit allow-lists, eager-load relations (`preventLazyLoading` is on),
   return `$this->paginated($query->paginate($this->perPage($request)), XResource::class)`.
   Create → `->response()->setStatusCode(201)`; delete → `$this->noContent()`.
7. **Routes**: one file `routes/api/v1/<module>.php`, `Route::apiResource` + explicit action POSTs
   (`invoices/{invoice}/approve`). Names are auto-prefixed `api.v1.`. Protect sensitive verbs with
   `->middleware('permission:invoices.approve')` in addition to the policy.
8. **Business operations** that touch >1 table go in `app/Actions/<Module>/XAction.php` with a
   `handle()` wrapped in `DB::transaction()`. Use `lockForUpdate()` for stock levels / balances.
   Posting-style operations also write an explicit audit entry via `AuditLogger::log()`.
9. **Numbers**: `app(DocumentNumberer::class)->next('invoice')` inside the creating transaction.
10. **Approvals** (Phase A): keep `status` on the record plus `approve`/`reject` endpoints requiring
    the `x.approve` permission, storing `approved_by`, `approved_at`, `rejection_reason`. Notify the
    requester with a database notification (`App\Notifications\...` implementing `toDatabase` →
    `['title','body','url','type']`). A central approval inbox aggregates these later.
11. **Resources** output ISO‑8601 (`->toJSON()`), money as strings, nested minimal relations
    (`['id','name']`), `whenLoaded`/`whenCounted`.
12. **Factories** for every model with realistic states; `User::factory()->inCompany($company, [...perms])`.
13. **Tests** (PHPUnit 12, `tests/Feature/<Module>/`): for every resource —
    cross-tenant read/update/delete → **404**; missing permission → **403** + no DB change;
    empty payload → **422** with the expected field errors; happy path asserts response **and**
    DB state (**and** audit row for sensitive actions). Use `$this->userWith([...])`,
    `$this->actingAsIn($user, $company)`, `$this->grantInCompany(...)`. Name tests as behaviour
    specs (`test_returns_404_when_...`). Run only your module's tests.
14. **Pint** before finishing. No new Composer packages without noting it in the final report.
15. **Storage discipline**: bounded `string(n)` lengths, `json` only for sparse data, indexes only
    for real query patterns, no per-row blobs (files go to `Storage` disk `local`, path in DB).
16. **No documentation files** beyond what you are asked for; put the API surface summary in your
    final report so `docs/api/openapi.yaml` can be assembled.
