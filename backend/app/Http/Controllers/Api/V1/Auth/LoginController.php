<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\SessionResource;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Step 1 of login. With 2FA enabled the user is NOT authenticated yet; a
     * short-lived challenge is stored in the session instead.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $remember = $request->boolean('remember');

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        // Same generic message for unknown email and wrong password.
        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            $this->audit->log('auth.login_failed', context: ['email' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->is_active) {
            $this->audit->log('auth.login_blocked_inactive', $user);

            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated. Contact your administrator.'],
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('two_factor', [
                'user_id' => $user->id,
                'remember' => $remember,
                'expires_at' => now()->addMinutes(config('erp.security.two_factor_challenge_ttl'))->timestamp,
            ]);

            return response()->json(['two_factor' => true]);
        }

        return $this->completeLogin($request, $user, $remember);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->audit->log('auth.logout', $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->noContent();
    }

    public function completeLogin(Request $request, User $user, bool $remember): JsonResponse
    {
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        $request->session()->forget('two_factor');

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        $this->audit->log('auth.login', $user, context: ['remember' => $remember]);

        $this->resolveDefaultTenant($user);

        return response()->json([
            'two_factor' => false,
            'session' => (new SessionResource($user))->resolve($request),
        ]);
    }

    /**
     * After login the tenant middleware has not run, so resolve the default
     * membership here for the session payload.
     */
    private function resolveDefaultTenant(User $user): void
    {
        $membership = $user->memberships()
            ->with('company')
            ->where('is_active', true)
            ->whereHas('company', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_default')->orderBy('id')
            ->first();

        if ($membership !== null) {
            app(TenantContext::class)->set($membership->company, $membership);
        }
    }
}
