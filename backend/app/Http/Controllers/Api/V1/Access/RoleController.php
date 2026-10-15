<?php

namespace App\Http\Controllers\Api\V1\Access;

use App\Actions\Access\SyncRolePermissionsAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Access\StoreRoleRequest;
use App\Http\Resources\Access\RoleResource;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\PrivilegeEscalationGuard;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Company roles. Roles are looked up with `Role::forTenant()` explicitly
 * (the model is not globally scoped), so another company's role id is a 404.
 *
 * System roles keep their name/description; their permissions may be tuned
 * except for `company.owner`, which is fully locked. PrivilegeEscalationGuard
 * stops non-owners granting permissions they lack or editing a role they hold.
 */
class RoleController extends ApiController
{
    private const LOCKED_SLUG = 'company.owner';

    public function __construct(private readonly TenantContext $tenant, private readonly PrivilegeEscalationGuard $guard) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Role::class);

        $query = $this->tenantRoles()->withCount([
            'users' => fn (Builder $q) => $q->where('role_user.company_id', $this->tenant->id()),
            'permissions',
        ]);

        $this->applySearch($query, $request, ['name', 'slug', 'description']);
        $this->applyFilters($query, $request, ['is_system']);
        $this->applySort($query, $request, ['name', 'slug', 'created_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), RoleResource::class);
    }

    public function store(StoreRoleRequest $request, SyncRolePermissionsAction $sync): JsonResponse
    {
        Gate::authorize('create', Role::class);

        $data = $request->validated();

        /** @var User $actor */
        $actor = $request->user();
        $this->guard->assertCanSetRolePermissions($actor, (int) $this->tenant->id(), null, $data['permission_ids'] ?? []);

        $role = DB::transaction(function () use ($data, $sync): Role {
            $role = Role::query()->create([
                'company_id' => $this->tenant->id(),
                'slug' => $this->uniqueSlug($data['name']),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);

            $sync->handle($role, $data['permission_ids'] ?? []);

            return $role;
        });

        return (new RoleResource($this->present($role)))->response()->setStatusCode(201);
    }

    public function show(int $role): RoleResource
    {
        $role = $this->findRole($role);
        Gate::authorize('view', $role);

        return new RoleResource($this->present($role));
    }

    public function update(StoreRoleRequest $request, int $role, SyncRolePermissionsAction $sync): RoleResource|JsonResponse
    {
        $role = $this->findRole($role);
        Gate::authorize('update', $role);

        if ($role->slug === self::LOCKED_SLUG) {
            return $this->message('The Company Owner role cannot be modified.', ['code' => 'conflict'], 409);
        }

        $data = $request->validated();

        if ($role->is_system) {
            $errors = [];
            if (array_key_exists('name', $data) && $data['name'] !== $role->name) {
                $errors['name'] = ['System role names cannot be changed.'];
            }
            if (array_key_exists('description', $data) && $data['description'] !== $role->description) {
                $errors['description'] = ['System role descriptions cannot be changed.'];
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        if (array_key_exists('permission_ids', $data)) {
            /** @var User $actor */
            $actor = $request->user();
            $this->guard->assertCanSetRolePermissions($actor, (int) $this->tenant->id(), $role, $data['permission_ids']);
        }

        DB::transaction(function () use ($role, $data, $sync): void {
            if (! $role->is_system) {
                $role->fill([
                    'name' => $data['name'] ?? $role->name,
                    'description' => array_key_exists('description', $data) ? $data['description'] : $role->description,
                ])->save();
            }

            if (array_key_exists('permission_ids', $data)) {
                $sync->handle($role, $data['permission_ids']);
            }
        });

        return new RoleResource($this->present($role->refresh()));
    }

    public function destroy(int $role): JsonResponse
    {
        $role = $this->findRole($role);
        Gate::authorize('delete', $role);

        if ($role->is_system) {
            return $this->message('System roles cannot be deleted.', ['code' => 'conflict'], 409);
        }

        $assigned = DB::table('role_user')->where('role_id', $role->id)->count();

        if ($assigned > 0) {
            return $this->message("This role is still assigned to {$assigned} user(s). Reassign them first.", ['code' => 'conflict'], 409);
        }

        $role->delete();

        return $this->noContent();
    }

    /**
     * @return Builder<Role>
     */
    private function tenantRoles(): Builder
    {
        return Role::forTenant((int) $this->tenant->id());
    }

    private function findRole(int $id): Role
    {
        return $this->tenantRoles()->findOrFail($id);
    }

    private function present(Role $role): Role
    {
        return $role
            ->load('permissions')
            ->loadCount([
                'users' => fn (Builder $q) => $q->where('role_user.company_id', $this->tenant->id()),
                'permissions',
            ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'role', 50, '');
        $slug = $base;
        $i = 2;

        while ($this->tenantRoles()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
