<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Platform\UpdatePlatformUserRequest;
use App\Http\Resources\Platform\PlatformUserResource;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Every account on the platform. Platform administrators may (de)activate
 * accounts; only a platform *owner* may grant or revoke platform roles, and
 * nobody may change their own role or deactivate themselves.
 */
class PlatformUserController extends ApiController
{
    private const PERMISSION = 'platform.users.manage';

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizePlatform($request);

        $query = User::query()->withCount('memberships');

        $this->applySearch($query, $request, ['name', 'email']);
        $this->applyFilters($query, $request, ['is_active']);

        $platformRole = $request->input('filter.platform_role');
        if ($platformRole === 'none') {
            $query->whereNull('platform_role');
        } elseif ($platformRole === 'any') {
            $query->whereNotNull('platform_role');
        } elseif (is_string($platformRole) && $platformRole !== '') {
            $query->where('platform_role', $platformRole);
        }

        $this->applySort($query, $request, ['name', 'email', 'created_at', 'last_login_at', 'is_active'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), PlatformUserResource::class);
    }

    public function show(Request $request, User $user): PlatformUserResource
    {
        $this->authorizePlatform($request);

        return new PlatformUserResource($user->load('memberships.company:id,name')->loadCount('memberships'));
    }

    public function update(UpdatePlatformUserRequest $request, User $user): PlatformUserResource
    {
        $this->authorizePlatform($request);

        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validated();
        $isSelf = $actor->id === $user->id;

        if (array_key_exists('platform_role', $data)) {
            if (! $actor->isPlatformOwner()) {
                throw new AuthorizationException('Only a platform owner can change platform roles.');
            }

            if ($isSelf && $data['platform_role'] !== $user->platform_role) {
                throw ValidationException::withMessages(['platform_role' => ['You cannot change your own platform role.']]);
            }
        }

        if ($isSelf && array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => ['You cannot deactivate your own account.']]);
        }

        $old = ['is_active' => $user->is_active, 'platform_role' => $user->platform_role];

        DB::transaction(function () use ($user, $data): void {
            if (array_key_exists('platform_role', $data)) {
                $user->platform_role = $data['platform_role'];
            }

            if (array_key_exists('is_active', $data)) {
                $active = (bool) $data['is_active'];
                $user->is_active = $active;
                $user->deactivated_at = $active ? null : now();
            }

            $user->save();

            if (! $user->is_active) {
                $this->revokeSessions($user);
            }
        });

        $new = ['is_active' => $user->is_active, 'platform_role' => $user->platform_role];
        [$oldDiff, $newDiff] = AuditLogger::diff($old, $new);

        $this->audit->log('platform.user_updated', $user, $oldDiff, $newDiff);

        return new PlatformUserResource($user->refresh()->loadCount('memberships'));
    }

    private function revokeSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
    }

    private function authorizePlatform(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();

        Gate::allowIf($user->hasPermission(self::PERMISSION));
    }
}
