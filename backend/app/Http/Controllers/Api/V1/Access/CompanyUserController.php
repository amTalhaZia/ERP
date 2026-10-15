<?php

namespace App\Http\Controllers\Api\V1\Access;

use App\Actions\Access\InviteUserAction;
use App\Actions\Access\UpdateMembershipAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Access\InviteUserRequest;
use App\Http\Requests\Access\UpdateCompanyUserRequest;
use App\Http\Resources\Access\CompanyUserResource;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\PrivilegeEscalationGuard;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;

/**
 * Members of the current company. `{user}` is resolved through the
 * membership table scoped to the tenant, so a user id from another company
 * (or a non-member) is a 404 and nothing is disclosed about them.
 */
class CompanyUserController extends ApiController
{
    /** @var array<string, string> sortable keys → qualified columns */
    private const SORTS = [
        'name' => 'users.name',
        'email' => 'users.email',
        'last_login_at' => 'users.last_login_at',
        'joined_at' => 'company_user.joined_at',
        'is_active' => 'company_user.is_active',
    ];

    public function __construct(private readonly TenantContext $tenant, private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', CompanyUser::class);

        $companyId = $this->tenant->id();

        $query = CompanyUser::query()
            ->join('users', 'users.id', '=', 'company_user.user_id')
            ->where('company_user.company_id', $companyId)
            ->select('company_user.*')
            ->with('user');

        $this->applySearch($query, $request, ['users.name', 'users.email']);
        $this->applyFilters($query, $request, ['is_active', 'is_default']);

        $roleId = $request->input('filter.role_id');
        if (is_numeric($roleId)) {
            $query->whereExists(fn ($q) => $q->from('role_user')
                ->whereColumn('role_user.user_id', 'company_user.user_id')
                ->where('role_user.company_id', $companyId)
                ->where('role_user.role_id', (int) $roleId));
        }

        $this->applyMemberSort($query, $request);

        $paginator = $query->paginate($this->perPage($request));
        $this->attachRoles($paginator->getCollection(), $companyId);

        return $this->paginated($paginator, CompanyUserResource::class);
    }

    public function store(InviteUserRequest $request, InviteUserAction $invite): JsonResponse
    {
        Gate::authorize('create', CompanyUser::class);

        /** @var User $actor */
        $actor = $request->user();

        $membership = $invite->handle($this->tenant->company(), $request->validated(), $actor);
        $this->attachRoles(collect([$membership]), $membership->company_id);

        return (new CompanyUserResource($membership))->response()->setStatusCode(201);
    }

    public function show(int $user): CompanyUserResource
    {
        $membership = $this->member($user);
        Gate::authorize('view', $membership);

        return new CompanyUserResource($membership);
    }

    public function update(UpdateCompanyUserRequest $request, int $user, UpdateMembershipAction $action): CompanyUserResource
    {
        $membership = $this->member($user);
        Gate::authorize('update', $membership);

        /** @var User $actor */
        $actor = $request->user();

        $membership = $action->handle($membership, $request->validated(), $actor);
        $this->attachRoles(collect([$membership]), $membership->company_id);

        return new CompanyUserResource($membership);
    }

    public function destroy(Request $request, int $user, PrivilegeEscalationGuard $guard): JsonResponse
    {
        $membership = $this->member($user);
        Gate::authorize('delete', $membership);
        $guard->assertCanManageMember($request->user(), $membership->company_id, $membership->user);

        DB::transaction(function () use ($membership): void {
            $roleNames = Role::query()
                ->join('role_user', 'role_user.role_id', '=', 'roles.id')
                ->where('role_user.user_id', $membership->user_id)
                ->where('role_user.company_id', $membership->company_id)
                ->pluck('roles.name')
                ->all();

            DB::table('role_user')->where('user_id', $membership->user_id)->where('company_id', $membership->company_id)->delete();
            $membership->delete();

            $this->audit->log('users.membership_removed', $membership->user, [
                'roles' => $roleNames,
                'branch_ids' => $membership->branch_ids,
                'is_active' => $membership->is_active,
            ], null, ['membership_id' => $membership->id], companyId: $membership->company_id);
        });

        return $this->noContent();
    }

    /** Re-sends the password-reset link so an invited user can (re)set their password. */
    public function resendInvite(int $user): JsonResponse
    {
        $membership = $this->member($user);
        Gate::authorize('update', $membership);

        $status = Password::sendResetLink(['email' => $membership->user->email]);

        $this->audit->log('users.invite_resent', $membership->user, context: [
            'sent' => $status === Password::RESET_LINK_SENT,
        ], companyId: $membership->company_id);

        return $this->message('If the account can receive e-mail, a new link has been sent.');
    }

    private function member(int $userId): CompanyUser
    {
        $membership = CompanyUser::query()
            ->with('user')
            ->where('company_id', $this->tenant->id())
            ->where('user_id', $userId)
            ->firstOrFail();

        $this->attachRoles(collect([$membership]), $membership->company_id);

        return $membership;
    }

    /**
     * @param  Builder<CompanyUser>  $query
     */
    private function applyMemberSort(Builder $query, Request $request): void
    {
        $sort = (string) $request->string('sort', 'name');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $key = ltrim($sort, '-');

        $column = self::SORTS[$key] ?? self::SORTS['name'];

        $query->orderBy($column, $direction)->orderBy('company_user.id', $direction);
    }

    /**
     * Attach the roles each member holds in this company as a loaded
     * "relation" (CompanyUser has no roles relation of its own).
     *
     * @param  Collection<int, CompanyUser>  $memberships
     */
    private function attachRoles(Collection $memberships, int $companyId): void
    {
        if ($memberships->isEmpty()) {
            return;
        }

        $rows = Role::query()
            ->join('role_user', 'role_user.role_id', '=', 'roles.id')
            ->where('role_user.company_id', $companyId)
            ->whereIn('role_user.user_id', $memberships->pluck('user_id')->all())
            ->orderBy('roles.name')
            ->get(['roles.id', 'roles.name', 'roles.slug', 'role_user.user_id'])
            ->groupBy('user_id');

        $memberships->each(function (CompanyUser $membership) use ($rows): void {
            $roles = $rows->get($membership->user_id, collect())
                ->map(fn (Role $role) => ['id' => $role->id, 'name' => $role->name, 'slug' => $role->slug])
                ->values();

            $membership->setRelation('roles', $roles);
        });
    }
}
